<?php
require_once '../includes/config.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';
require_once 'includes/upload_helper.php';
require_login();

$page_title = 'Kelola Mitra';
$current_page = 'mitra';
$table = 'mitra';
$jenis_list = ['industri','universitas','pemerintah','komunitas','penerbit','organisasi'];

// ===== DELETE =====
if (isset($_GET['delete']) && isset($_GET['token'])) {
    $id = (int)$_GET['delete'];
    if (hash_equals(hash_hmac('sha256', $id, $_SESSION['csrf_token']), $_GET['token'])) {
        $stmt = $pdo->prepare("SELECT logo, nama FROM $table WHERE id = ?");
        $stmt->execute([$id]);
        $old = $stmt->fetch();
        if ($old) {
            if (!empty($old['logo']) && file_exists('../' . $old['logo'])) @unlink('../' . $old['logo']);
            $pdo->prepare("DELETE FROM $table WHERE id = ?")->execute([$id]);
            $pdo->prepare("INSERT INTO admin_logs (user_id, user_name, action, detail, ip) VALUES (?, ?, ?, ?, ?)")
                ->execute([$_SESSION['admin_id'], $_SESSION['admin_name'], 'DELETE_MITRA', 'Menghapus mitra: ' . $old['nama'], $_SERVER['REMOTE_ADDR']]);
        }
        header("Location: kelola_mitra.php?msg=deleted"); exit;
    }
}

// ===== ADD / EDIT =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $id = (int)($_POST['id'] ?? 0);
    $nama = sanitize($_POST['nama'] ?? '');
    $jenis = in_array($_POST['jenis'] ?? '', $jenis_list) ? $_POST['jenis'] : 'industri';
    $website = trim($_POST['website'] ?? '');
    $deskripsi = sanitize($_POST['deskripsi'] ?? '');
    $status = ($_POST['status'] ?? 'aktif') === 'nonaktif' ? 'nonaktif' : 'aktif';
    $urutan = (int)($_POST['urutan'] ?? 0);
    $logo = $_POST['old_logo'] ?? '';

    if (strlen($nama) < 3) $error = "Nama mitra minimal 3 karakter.";
    elseif ($website !== '' && !filter_var($website, FILTER_VALIDATE_URL)) $error = "Format URL website tidak valid.";
    else {
        if (!empty($_FILES['logo']['name'])) {
            $upload = handle_upload($_FILES['logo'], '../assets/uploads/mitra/', ['jpg','jpeg','png','webp','svg']);
            if ($upload['success']) {
                if (!empty($logo) && file_exists('../' . $logo)) @unlink('../' . $logo);
                $logo = $upload['path'];
            } else $error = $upload['error'];
        }
        if (!isset($error)) {
            if ($id > 0) {
                $pdo->prepare("UPDATE $table SET nama=?, jenis=?, website=?, deskripsi=?, status=?, urutan=?, logo=? WHERE id=?")
                    ->execute([$nama,$jenis,$website ?: null,$deskripsi,$status,$urutan,$logo ?: null,$id]);
                $action = 'UPDATE_MITRA'; $detail = "Memperbarui mitra: $nama";
            } else {
                $pdo->prepare("INSERT INTO $table (nama, jenis, website, deskripsi, status, urutan, logo) VALUES (?,?,?,?,?,?,?)")
                    ->execute([$nama,$jenis,$website ?: null,$deskripsi,$status,$urutan,$logo ?: null]);
                $action = 'ADD_MITRA'; $detail = "Menambah mitra: $nama";
            }
            $pdo->prepare("INSERT INTO admin_logs (user_id, user_name, action, detail, ip) VALUES (?, ?, ?, ?, ?)")
                ->execute([$_SESSION['admin_id'], $_SESSION['admin_name'], $action, $detail, $_SERVER['REMOTE_ADDR']]);
            header("Location: kelola_mitra.php?msg=saved"); exit;
        }
    }
}

// ===== FETCH + FILTER =====
$search = trim($_GET['q'] ?? '');
$fjenis = $_GET['jenis'] ?? 'all';
$fstat = $_GET['status'] ?? 'all';
$sql = "SELECT * FROM $table WHERE 1=1"; $params = [];
if ($search !== '') { $sql .= " AND (nama LIKE ? OR deskripsi LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($fjenis !== 'all') { $sql .= " AND jenis = ?"; $params[] = $fjenis; }
if ($fstat !== 'all') { $sql .= " AND status = ?"; $params[] = $fstat; }
$sql .= " ORDER BY urutan ASC, nama ASC";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$items = $stmt->fetchAll();

$total_all = (int)$pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
$total_aktif = (int)$pdo->query("SELECT COUNT(*) FROM $table WHERE status='aktif'")->fetchColumn();
$total_industri = (int)$pdo->query("SELECT COUNT(*) FROM $table WHERE jenis='industri'")->fetchColumn();
$total_univ = (int)$pdo->query("SELECT COUNT(*) FROM $table WHERE jenis='universitas'")->fetchColumn();

$data = ['id'=>0,'nama'=>'','jenis'=>'industri','website'=>'','deskripsi'=>'','status'=>'aktif','urutan'=>0,'logo'=>''];
if (isset($_GET['edit']) && isset($_GET['token']) && hash_equals(hash_hmac('sha256', $_GET['edit'], $_SESSION['csrf_token']), $_GET['token'])) {
    $stmt = $pdo->prepare("SELECT * FROM $table WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $f = $stmt->fetch();
    if ($f) $data = array_merge($data, array_map(fn($v) => $v === null ? '' : $v, $f));
}
ob_start();
?>

<style>
.mtr-badge{display:inline-block;padding:.2rem .6rem;border-radius:50px;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;}
.mtr-industri{background:rgba(138,21,56,.15);color:var(--crimson);}
.mtr-universitas{background:rgba(135,206,235,.15);color:var(--sky-blue);}
.mtr-pemerintah{background:rgba(243,156,18,.15);color:var(--warning);}
.mtr-komunitas{background:rgba(46,139,87,.15);color:var(--success);}
.mtr-penerbit{background:rgba(155,89,182,.15);color:#9b59b6;}
.mtr-organisasi{background:rgba(236,72,153,.15);color:#EC4899;}
.mtr-logo{width:52px;height:52px;border-radius:var(--radius-md);object-fit:contain;background:#fff;border:1px solid var(--border-dark);padding:4px;}
.mtr-logo-ph{width:52px;height:52px;border-radius:var(--radius-md);background:var(--bg-darker);border:1px solid var(--border-dark);display:flex;align-items:center;justify-content:center;color:var(--text-muted);font-size:1.2rem;}
.mtr-stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1.2rem;margin-bottom:2rem;}
.mtr-stat-card{background:var(--panel-bg);border:1px solid var(--border-dark);border-left:4px solid var(--crimson);border-radius:var(--radius-md);padding:1.3rem;}
.mtr-stat-card.accent-sky{border-left-color:var(--sky-blue);}
.mtr-stat-card.accent-emerald{border-left-color:var(--success);}
.mtr-stat-card.accent-warning{border-left-color:var(--warning);}
.mtr-stat-value{font-size:2rem;font-weight:900;color:#fff;line-height:1;}
.mtr-stat-label{color:var(--text-muted);font-size:.85rem;margin-top:.3rem;}
</style>

<?php if (isset($_GET['msg'])):
    $msgs = ['saved'=>['success','Mitra berhasil disimpan!'],'deleted'=>['success','Mitra berhasil dihapus!']];
    if (isset($msgs[$_GET['msg']])): ?>
<script>Swal.fire({icon:'<?=$msgs[$_GET['msg']][0]?>',title:'Berhasil!',text:'<?=$msgs[$_GET['msg']][1]?>',timer:2500,showConfirmButton:false});</script>
<?php endif; endif; ?>
<?php if (isset($error)): ?>
<script>Swal.fire({icon:'error',title:'Validasi Gagal',text:'<?= addslashes($error) ?>'});</script>
<?php endif; ?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
    <h2 class="section-title"><i class="fa-solid fa-handshake"></i> <?= $data['id'] ? 'Edit' : 'Kelola' ?> Mitra</h2>
    <?php if (!$data['id']): ?>
    <a href="?action=add" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Tambah Mitra</a>
    <?php else: ?>
    <a href="kelola_mitra.php" class="btn btn-secondary"><i class="fa-solid fa-xmark"></i> Batal</a>
    <?php endif; ?>
</div>

<div class="mtr-stats-grid">
    <div class="mtr-stat-card"><div class="mtr-stat-value"><?= $total_all ?></div><div class="mtr-stat-label">Total Mitra</div></div>
    <div class="mtr-stat-card accent-emerald"><div class="mtr-stat-value"><?= $total_aktif ?></div><div class="mtr-stat-label">Mitra Aktif</div></div>
    <div class="mtr-stat-card accent-sky"><div class="mtr-stat-value"><?= $total_industri ?></div><div class="mtr-stat-label">Mitra Industri</div></div>
    <div class="mtr-stat-card accent-warning"><div class="mtr-stat-value"><?= $total_univ ?></div><div class="mtr-stat-label">Mitra Universitas</div></div>
</div>

<?php if (isset($_GET['action']) && $_GET['action'] === 'add' || $data['id']): ?>
<div class="panel">
    <div class="panel-header"><h3 class="panel-title"><i class="fa-solid fa-pen-to-square"></i> <?= $data['id'] ? 'Edit' : 'Tambah' ?> Mitra</h3></div>
    <div class="panel-body">
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="id" value="<?= $data['id'] ?>">
            <input type="hidden" name="old_logo" value="<?= htmlspecialchars($data['logo']) ?>">
            <div style="display:grid;grid-template-columns:2fr 1fr;gap:1rem;">
                <div class="form-group"><label>Nama Mitra <span class="required">*</span></label>
                    <input type="text" name="nama" class="form-control" value="<?= htmlspecialchars($data['nama']) ?>" required placeholder="Contoh: British Council"></div>
                <div class="form-group"><label>Jenis</label>
                    <select name="jenis" class="form-control"><?php foreach ($jenis_list as $j): ?><option value="<?= $j ?>" <?= $data['jenis']===$j?'selected':'' ?>><?= ucfirst($j) ?></option><?php endforeach; ?></select></div>
            </div>
            <div class="form-group"><label>Website</label>
                <input type="url" name="website" class="form-control" value="<?= htmlspecialchars($data['website']) ?>" placeholder="https://..."></div>
            <div class="form-group"><label>Deskripsi Kerja Sama</label>
                <textarea name="deskripsi" rows="2" class="form-control" placeholder="Contoh: Magang MBKM dan riset terapan..."><?= htmlspecialchars($data['deskripsi']) ?></textarea></div>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;">
                <div class="form-group"><label>Logo (opsional, disarankan PNG transparan)</label>
                    <input type="file" name="logo" class="form-control" accept="image/*,.svg">
                    <?php if (!empty($data['logo'])): ?><img src="../<?= htmlspecialchars($data['logo']) ?>" class="mtr-logo" style="margin-top:.5rem;"><?php endif; ?></div>
                <div class="form-group"><label>Status</label>
                    <select name="status" class="form-control"><option value="aktif" <?= $data['status']==='aktif'?'selected':'' ?>>Aktif</option><option value="nonaktif" <?= $data['status']==='nonaktif'?'selected':'' ?>>Nonaktif</option></select></div>
                <div class="form-group"><label>Urutan Tampil</label>
                    <input type="number" name="urutan" class="form-control" value="<?= (int)$data['urutan'] ?>"></div>
            </div>
            <div style="display:flex;gap:1rem;margin-top:1.5rem;">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Simpan Mitra</button>
                <a href="kelola_mitra.php" class="btn btn-secondary"><i class="fa-solid fa-xmark"></i> Batal</a>
            </div>
        </form>
    </div>
</div>
<?php else: ?>
<div class="panel" style="margin-bottom:1.5rem;">
    <div class="panel-body">
        <form method="GET" style="display:grid;grid-template-columns:2fr 1fr 1fr auto;gap:1rem;align-items:end;">
            <div class="form-group" style="margin:0;"><label>Cari</label><input type="text" name="q" class="form-control" value="<?= htmlspecialchars($search) ?>" placeholder="Nama mitra..."></div>
            <div class="form-group" style="margin:0;"><label>Jenis</label>
                <select name="jenis" class="form-control"><option value="all">Semua</option><?php foreach ($jenis_list as $j): ?><option value="<?= $j ?>" <?= $fjenis===$j?'selected':'' ?>><?= ucfirst($j) ?></option><?php endforeach; ?></select></div>
            <div class="form-group" style="margin:0;"><label>Status</label>
                <select name="status" class="form-control"><option value="all">Semua</option><option value="aktif" <?= $fstat==='aktif'?'selected':'' ?>>Aktif</option><option value="nonaktif" <?= $fstat==='nonaktif'?'selected':'' ?>>Nonaktif</option></select></div>
            <button class="btn btn-primary" type="submit"><i class="fa-solid fa-filter"></i> Terapkan</button>
        </form>
    </div>
</div>

<div class="panel">
    <div class="panel-body no-padding">
        <div class="table-wrapper">
            <table>
                <thead><tr><th style="width:70px;">Logo</th><th>Mitra</th><th style="width:130px;">Jenis</th><th style="width:180px;">Website</th><th style="width:90px;">Status</th><th style="width:120px;">Aksi</th></tr></thead>
                <tbody>
                <?php if (empty($items)): ?>
                    <tr><td colspan="6" style="text-align:center;padding:3rem;color:var(--text-muted);"><i class="fa-solid fa-handshake" style="font-size:3rem;opacity:.2;display:block;margin-bottom:1rem;"></i>Belum ada mitra.</td></tr>
                <?php else: foreach ($items as $item): $token = hash_hmac('sha256', $item['id'], $_SESSION['csrf_token']); ?>
                    <tr>
                        <td><?php if (!empty($item['logo'])): ?><img src="../<?= htmlspecialchars($item['logo']) ?>" class="mtr-logo"><?php else: ?><span class="mtr-logo-ph"><i class="fa-solid fa-building"></i></span><?php endif; ?></td>
                        <td><strong><?= htmlspecialchars($item['nama']) ?></strong><?php if ($item['deskripsi']): ?><br><small style="color:var(--text-muted);"><?= htmlspecialchars(substr($item['deskripsi'],0,70)) ?><?= strlen($item['deskripsi'])>70?'...':'' ?></small><?php endif; ?></td>
                        <td><span class="mtr-badge mtr-<?= htmlspecialchars($item['jenis']) ?>"><?= htmlspecialchars($item['jenis']) ?></span></td>
                        <td><?php if ($item['website']): ?><a href="<?= htmlspecialchars($item['website']) ?>" target="_blank" rel="noopener" style="color:var(--sky-blue);font-size:.85rem;"><i class="fa-solid fa-link"></i> <?= htmlspecialchars(parse_url($item['website'], PHP_URL_HOST) ?: $item['website']) ?></a><?php else: ?><span style="color:var(--text-muted);">-</span><?php endif; ?></td>
                        <td><span class="mtr-badge" style="<?= $item['status']==='aktif' ? 'background:rgba(46,139,87,.15);color:var(--success);' : 'background:rgba(255,255,255,.08);color:var(--text-muted);' ?>"><?= $item['status'] ?></span></td>
                        <td><div class="table-actions">
                            <a href="?edit=<?= $item['id'] ?>&token=<?= $token ?>" class="table-action-btn edit" title="Edit"><i class="fa-solid fa-pen"></i></a>
                            <a href="?delete=<?= $item['id'] ?>&token=<?= $token ?>" class="table-action-btn delete" title="Hapus" onclick="return confirm('Hapus mitra ini?')"><i class="fa-solid fa-trash"></i></a>
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