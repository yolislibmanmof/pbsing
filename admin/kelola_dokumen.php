<?php
require_once '../includes/config.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';
require_once 'includes/upload_helper.php';
require_login();

$page_title = 'Kelola Dokumen';
$current_page = 'dokumen';
$table = 'dokumen';
$kategori_list = ['kurikulum','akademik','kemahasiswaan','sop','publikasi','lainnya'];

function fmt_size($b) {
    if ($b <= 0) return '-';
    $u = ['B','KB','MB','GB']; $i = 0;
    while ($b >= 1024 && $i < 3) { $b /= 1024; $i++; }
    return round($b, 1) . ' ' . $u[$i];
}
function file_icon($ext) {
    $map = ['pdf'=>'fa-file-pdf','doc'=>'fa-file-word','docx'=>'fa-file-word','xls'=>'fa-file-excel','xlsx'=>'fa-file-excel','ppt'=>'fa-file-powerpoint','pptx'=>'fa-file-powerpoint','zip'=>'fa-file-zipper'];
    return $map[strtolower($ext)] ?? 'fa-file';
}

// ===== DELETE =====
if (isset($_GET['delete']) && isset($_GET['token'])) {
    $id = (int)$_GET['delete'];
    if (hash_equals(hash_hmac('sha256', $id, $_SESSION['csrf_token']), $_GET['token'])) {
        $stmt = $pdo->prepare("SELECT filename, judul FROM $table WHERE id = ?");
        $stmt->execute([$id]);
        $old = $stmt->fetch();
        if ($old) {
            if (!empty($old['filename']) && file_exists('../' . $old['filename'])) @unlink('../' . $old['filename']);
            $pdo->prepare("DELETE FROM $table WHERE id = ?")->execute([$id]);
            $pdo->prepare("INSERT INTO admin_logs (user_id, user_name, action, detail, ip) VALUES (?, ?, ?, ?, ?)")
                ->execute([$_SESSION['admin_id'], $_SESSION['admin_name'], 'DELETE_DOKUMEN', 'Menghapus dokumen: ' . $old['judul'], $_SERVER['REMOTE_ADDR']]);
        }
        header("Location: kelola_dokumen.php?msg=deleted");
        exit;
    }
}

// ===== ADD / EDIT =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $id = (int)($_POST['id'] ?? 0);
    $judul = sanitize($_POST['judul'] ?? '');
    $kategori = sanitize($_POST['kategori'] ?? 'akademik');
    $deskripsi = sanitize($_POST['deskripsi'] ?? '');
    $status = ($_POST['status'] ?? 'aktif') === 'nonaktif' ? 'nonaktif' : 'aktif';
    $filename = $_POST['old_filename'] ?? '';
    $ukuran = (int)($_POST['old_size'] ?? 0);
    $tipe = $_POST['old_type'] ?? 'pdf';

    if (empty($judul)) {
        $error = "Judul dokumen wajib diisi.";
    } elseif (!in_array($kategori, $kategori_list)) {
        $error = "Kategori tidak valid.";
    } else {
        if (!empty($_FILES['file']['name'])) {
            $upload = handle_upload($_FILES['file'], '../assets/uploads/dokumen/', ['pdf','doc','docx','xls','xlsx','ppt','pptx','zip']);
            if ($upload['success']) {
                if (!empty($filename) && file_exists('../' . $filename)) @unlink('../' . $filename);
                $filename = $upload['path'];
                $ukuran = (int)@filesize('../' . $filename);
                $tipe = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            } else {
                $error = $upload['error'];
            }
        } elseif ($id === 0) {
            $error = "File dokumen wajib diunggah untuk dokumen baru.";
        }

        if (!isset($error)) {
            if (empty($filename)) { $error = "File tidak ditemukan."; }
            else {
                if ($id > 0) {
                    $pdo->prepare("UPDATE $table SET judul=:j, kategori=:k, deskripsi=:d, status=:s, filename=:f, ukuran=:u, tipe_file=:t WHERE id=:id")
                        ->execute([':j'=>$judul,':k'=>$kategori,':d'=>$deskripsi,':s'=>$status,':f'=>$filename,':u'=>$ukuran,':t'=>$tipe,':id'=>$id]);
                    $action = 'UPDATE_DOKUMEN'; $detail = "Memperbarui dokumen: $judul";
                } else {
                    $pdo->prepare("INSERT INTO $table (judul, kategori, deskripsi, status, filename, ukuran, tipe_file) VALUES (?,?,?,?,?,?,?)")
                        ->execute([$judul,$kategori,$deskripsi,$status,$filename,$ukuran,$tipe]);
                    $action = 'ADD_DOKUMEN'; $detail = "Menambah dokumen: $judul";
                }
                $pdo->prepare("INSERT INTO admin_logs (user_id, user_name, action, detail, ip) VALUES (?, ?, ?, ?, ?)")
                    ->execute([$_SESSION['admin_id'], $_SESSION['admin_name'], $action, $detail, $_SERVER['REMOTE_ADDR']]);
                header("Location: kelola_dokumen.php?msg=saved");
                exit;
            }
        }
    }
}

// ===== FETCH + FILTER =====
$search = trim($_GET['q'] ?? '');
$fkat = $_GET['kategori'] ?? 'all';
$fstat = $_GET['status'] ?? 'all';
$sql = "SELECT * FROM $table WHERE 1=1";
$params = [];
if ($search !== '') { $sql .= " AND (judul LIKE ? OR deskripsi LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($fkat !== 'all') { $sql .= " AND kategori = ?"; $params[] = $fkat; }
if ($fstat !== 'all') { $sql .= " AND status = ?"; $params[] = $fstat; }
$sql .= " ORDER BY kategori ASC, judul ASC";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$items = $stmt->fetchAll();

$total_all = (int)$pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
$total_dl = (int)$pdo->query("SELECT COALESCE(SUM(downloads),0) FROM $table")->fetchColumn();
$total_aktif = (int)$pdo->query("SELECT COUNT(*) FROM $table WHERE status='aktif'")->fetchColumn();

// Form data
$data = ['id'=>0,'judul'=>'','kategori'=>'akademik','deskripsi'=>'','status'=>'aktif','filename'=>'','ukuran'=>0,'tipe_file'=>'pdf'];
if (isset($_GET['edit']) && isset($_GET['token']) && hash_equals(hash_hmac('sha256', $_GET['edit'], $_SESSION['csrf_token']), $_GET['token'])) {
    $stmt = $pdo->prepare("SELECT * FROM $table WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $f = $stmt->fetch();
    if ($f) $data = $f;
}
ob_start();
?>

<style>
.doc-badge{display:inline-block;padding:.2rem .6rem;border-radius:50px;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;}
.doc-kurikulum{background:rgba(138,21,56,.15);color:var(--crimson);}
.doc-akademik{background:rgba(135,206,235,.15);color:var(--sky-blue);}
.doc-kemahasiswaan{background:rgba(46,139,87,.15);color:var(--success);}
.doc-sop{background:rgba(243,156,18,.15);color:var(--warning);}
.doc-publikasi{background:rgba(155,89,182,.15);color:#9b59b6;}
.doc-lainnya{background:rgba(255,255,255,.08);color:var(--text-muted);}
.st-on{background:rgba(46,139,87,.15);color:var(--success);}
.st-off{background:rgba(255,255,255,.08);color:var(--text-muted);}
.file-chip{display:inline-flex;align-items:center;gap:.6rem;background:var(--bg-darker);border:1px solid var(--border-dark);border-radius:var(--radius-md);padding:.55rem .9rem;font-size:.85rem;color:var(--text-main);}
.file-chip i{color:var(--crimson);font-size:1.1rem;}
.doc-stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1.2rem;margin-bottom:2rem;}
.doc-stat-card{background:var(--panel-bg);border:1px solid var(--border-dark);border-left:4px solid var(--crimson);border-radius:var(--radius-md);padding:1.3rem;}
.doc-stat-card.accent-sky{border-left-color:var(--sky-blue);}
.doc-stat-card.accent-emerald{border-left-color:var(--success);}
.doc-stat-value{font-size:2rem;font-weight:900;color:#fff;line-height:1;}
.doc-stat-label{color:var(--text-muted);font-size:.85rem;margin-top:.3rem;}
</style>

<?php if (isset($_GET['msg'])):
    $msgs = ['saved'=>['success','Dokumen berhasil disimpan!'],'deleted'=>['success','Dokumen berhasil dihapus!']];
    if (isset($msgs[$_GET['msg']])): ?>
<script>Swal.fire({icon:'<?=$msgs[$_GET['msg']][0]?>',title:'Berhasil!',text:'<?=$msgs[$_GET['msg']][1]?>',timer:2500,showConfirmButton:false});</script>
<?php endif; endif; ?>
<?php if (isset($error)): ?>
<script>Swal.fire({icon:'error',title:'Validasi Gagal',text:'<?= addslashes($error) ?>'});</script>
<?php endif; ?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
    <h2 class="section-title"><i class="fa-solid fa-folder-open"></i> <?= $data['id'] ? 'Edit' : 'Kelola' ?> Dokumen</h2>
    <?php if (!$data['id']): ?>
    <a href="?action=add" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Unggah Dokumen</a>
    <?php else: ?>
    <a href="kelola_dokumen.php" class="btn btn-secondary"><i class="fa-solid fa-xmark"></i> Batal</a>
    <?php endif; ?>
</div>

<div class="doc-stats-grid">
    <div class="doc-stat-card"><div class="doc-stat-value"><?= $total_all ?></div><div class="doc-stat-label">Total Dokumen</div></div>
    <div class="doc-stat-card accent-sky"><div class="doc-stat-value"><?= $total_dl ?></div><div class="doc-stat-label">Total Unduhan</div></div>
    <div class="doc-stat-card accent-emerald"><div class="doc-stat-value"><?= $total_aktif ?></div><div class="doc-stat-label">Dokumen Aktif</div></div>
</div>

<?php if (isset($_GET['action']) && $_GET['action'] === 'add' || $data['id']): ?>
<!-- FORM -->
<div class="panel">
    <div class="panel-header"><h3 class="panel-title"><i class="fa-solid fa-upload"></i> <?= $data['id'] ? 'Edit' : 'Unggah' ?> Dokumen</h3></div>
    <div class="panel-body">
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="id" value="<?= $data['id'] ?>">
            <input type="hidden" name="old_filename" value="<?= htmlspecialchars($data['filename']) ?>">
            <input type="hidden" name="old_size" value="<?= $data['ukuran'] ?>">
            <input type="hidden" name="old_type" value="<?= htmlspecialchars($data['tipe_file']) ?>">
            <div style="display:grid;grid-template-columns:2fr 1fr;gap:1rem;">
                <div class="form-group">
                    <label>Judul Dokumen <span class="required">*</span></label>
                    <input type="text" name="judul" class="form-control" value="<?= htmlspecialchars($data['judul']) ?>" required placeholder="Contoh: Panduan Akademik 2026/2027">
                </div>
                <div class="form-group">
                    <label>Kategori</label>
                    <select name="kategori" class="form-control">
                        <?php foreach ($kategori_list as $k): ?>
                        <option value="<?= $k ?>" <?= $data['kategori']===$k?'selected':'' ?>><?= ucfirst($k) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Deskripsi Singkat</label>
                <textarea name="deskripsi" rows="3" class="form-control" placeholder="Deskripsi isi dokumen..."><?= htmlspecialchars($data['deskripsi']) ?></textarea>
            </div>
            <div class="form-group">
                <label>File Dokumen <?= $data['id'] ? '(kosongkan jika tidak diganti)' : '*' ?></label>
                <input type="file" name="file" class="form-control" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip">
                <?php if (!empty($data['filename'])): ?>
                <div style="margin-top:.6rem;">
                    <span class="file-chip"><i class="fa-solid <?= file_icon($data['tipe_file']) ?>"></i> <?= htmlspecialchars(basename($data['filename'])) ?> · <?= fmt_size($data['ukuran']) ?></span>
                </div>
                <?php endif; ?>
                <small style="color:var(--text-muted);">Format: PDF, DOC(X), XLS(X), PPT(X), ZIP. Maks sesuai konfigurasi upload.</small>
            </div>
            <div class="form-group" style="max-width:220px;">
                <label>Status</label>
                <select name="status" class="form-control">
                    <option value="aktif" <?= $data['status']==='aktif'?'selected':'' ?>>Aktif</option>
                    <option value="nonaktif" <?= $data['status']==='nonaktif'?'selected':'' ?>>Nonaktif</option>
                </select>
            </div>
            <div style="display:flex;gap:1rem;margin-top:1.5rem;">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Simpan Dokumen</button>
                <a href="kelola_dokumen.php" class="btn btn-secondary"><i class="fa-solid fa-xmark"></i> Batal</a>
            </div>
        </form>
    </div>
</div>
<?php else: ?>
<!-- FILTER + TABLE -->
<div class="panel" style="margin-bottom:1.5rem;">
    <div class="panel-body">
        <form method="GET" style="display:grid;grid-template-columns:2fr 1fr 1fr auto;gap:1rem;align-items:end;">
            <div class="form-group" style="margin:0;"><label>Cari</label><input type="text" name="q" class="form-control" value="<?= htmlspecialchars($search) ?>" placeholder="Judul dokumen..."></div>
            <div class="form-group" style="margin:0;"><label>Kategori</label>
                <select name="kategori" class="form-control"><option value="all">Semua</option>
                <?php foreach ($kategori_list as $k): ?><option value="<?= $k ?>" <?= $fkat===$k?'selected':'' ?>><?= ucfirst($k) ?></option><?php endforeach; ?></select>
            </div>
            <div class="form-group" style="margin:0;"><label>Status</label>
                <select name="status" class="form-control"><option value="all">Semua</option><option value="aktif" <?= $fstat==='aktif'?'selected':'' ?>>Aktif</option><option value="nonaktif" <?= $fstat==='nonaktif'?'selected':'' ?>>Nonaktif</option></select>
            </div>
            <button class="btn btn-primary" type="submit"><i class="fa-solid fa-filter"></i> Terapkan</button>
        </form>
    </div>
</div>

<div class="panel">
    <div class="panel-body no-padding">
        <div class="table-wrapper">
            <table>
                <thead><tr><th>Judul</th><th style="width:130px;">Kategori</th><th style="width:180px;">File</th><th style="width:100px;">Unduhan</th><th style="width:90px;">Status</th><th style="width:120px;">Aksi</th></tr></thead>
                <tbody>
                <?php if (empty($items)): ?>
                    <tr><td colspan="6" style="text-align:center;padding:3rem;color:var(--text-muted);"><i class="fa-solid fa-folder-open" style="font-size:3rem;opacity:.2;display:block;margin-bottom:1rem;"></i>Belum ada dokumen.</td></tr>
                <?php else: foreach ($items as $item): $token = hash_hmac('sha256', $item['id'], $_SESSION['csrf_token']); ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($item['judul']) ?></strong><?php if ($item['deskripsi']): ?><br><small style="color:var(--text-muted);"><?= htmlspecialchars(substr($item['deskripsi'],0,70)) ?><?= strlen($item['deskripsi'])>70?'...':'' ?></small><?php endif; ?></td>
                        <td><span class="doc-badge doc-<?= htmlspecialchars($item['kategori']) ?>"><?= htmlspecialchars($item['kategori']) ?></span></td>
                        <td><span class="file-chip"><i class="fa-solid <?= file_icon($item['tipe_file']) ?>"></i> <?= htmlspecialchars(strtoupper($item['tipe_file'])) ?> · <?= fmt_size($item['ukuran']) ?></span></td>
                        <td><strong style="color:var(--sky-blue);"><?= number_format($item['downloads']) ?></strong></td>
                        <td><span class="doc-badge <?= $item['status']==='aktif'?'st-on':'st-off' ?>"><?= $item['status'] ?></span></td>
                        <td><div class="table-actions">
                            <a href="?edit=<?= $item['id'] ?>&token=<?= $token ?>" class="table-action-btn edit" title="Edit"><i class="fa-solid fa-pen"></i></a>
                            <a href="?delete=<?= $item['id'] ?>&token=<?= $token ?>" class="table-action-btn delete" title="Hapus" onclick="return confirm('Hapus dokumen ini? File akan dihapus dari server.')"><i class="fa-solid fa-trash"></i></a>
                        </div></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
$page_content = ob_get_clean();
require_once 'includes/admin_template.php';
?>