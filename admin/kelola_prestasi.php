<?php
require_once '../includes/config.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';
require_once 'includes/upload_helper.php';
require_login();

$page_title = 'Kelola Prestasi';
$current_page = 'prestasi';
$table = 'prestasi';
$kategori_list = ['akademik','nonakademik','kompetisi','penelitian','sastra','bahasa'];
$tingkat_list = ['internal','regional','nasional','internasional'];
$tahun_now = (int)date('Y');

// ===== DELETE =====
if (isset($_GET['delete']) && isset($_GET['token'])) {
    $id = (int)$_GET['delete'];
    if (hash_equals(hash_hmac('sha256', $id, $_SESSION['csrf_token']), $_GET['token'])) {
        $stmt = $pdo->prepare("SELECT gambar, judul FROM $table WHERE id = ?");
        $stmt->execute([$id]);
        $old = $stmt->fetch();
        if ($old) {
            if (!empty($old['gambar']) && file_exists('../' . $old['gambar'])) @unlink('../' . $old['gambar']);
            $pdo->prepare("DELETE FROM $table WHERE id = ?")->execute([$id]);
            $pdo->prepare("INSERT INTO admin_logs (user_id, user_name, action, detail, ip) VALUES (?, ?, ?, ?, ?)")
                ->execute([$_SESSION['admin_id'], $_SESSION['admin_name'], 'DELETE_PRESTASI', 'Menghapus prestasi: ' . $old['judul'], $_SERVER['REMOTE_ADDR']]);
        }
        header("Location: kelola_prestasi.php?msg=deleted"); exit;
    }
}

// ===== ADD / EDIT =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $id = (int)($_POST['id'] ?? 0);
    $judul = sanitize($_POST['judul'] ?? '');
    $pencapaian = sanitize($_POST['pencapaian'] ?? '');
    $kategori = in_array($_POST['kategori'] ?? '', $kategori_list) ? $_POST['kategori'] : 'kompetisi';
    $tingkat = in_array($_POST['tingkat'] ?? '', $tingkat_list) ? $_POST['tingkat'] : 'nasional';
    $nama_tim = sanitize($_POST['nama_tim'] ?? '');
    $tahun = !empty($_POST['tahun']) ? (int)$_POST['tahun'] : null;
    $deskripsi = sanitize($_POST['deskripsi'] ?? '');
    $status = ($_POST['status'] ?? 'aktif') === 'nonaktif' ? 'nonaktif' : 'aktif';
    $urutan = (int)($_POST['urutan'] ?? 0);
    $gambar = $_POST['old_gambar'] ?? '';

    if (strlen($judul) < 5) $error = "Judul prestasi minimal 5 karakter.";
    elseif (empty($pencapaian)) $error = "Pencapaian wajib diisi (contoh: Juara 1 Nasional).";
    else {
        if (!empty($_FILES['gambar']['name'])) {
            $upload = handle_upload($_FILES['gambar'], '../assets/uploads/prestasi/', ['jpg','jpeg','png','webp']);
            if ($upload['success']) {
                if (!empty($gambar) && file_exists('../' . $gambar)) @unlink('../' . $gambar);
                $gambar = $upload['path'];
            } else $error = $upload['error'];
        }
        if (!isset($error)) {
            if ($id > 0) {
                $pdo->prepare("UPDATE $table SET judul=?, pencapaian=?, kategori=?, tingkat=?, nama_tim=?, tahun=?, deskripsi=?, status=?, urutan=?, gambar=? WHERE id=?")
                    ->execute([$judul,$pencapaian,$kategori,$tingkat,$nama_tim,$tahun,$deskripsi,$status,$urutan,$gambar ?: null,$id]);
                $action = 'UPDATE_PRESTASI'; $detail = "Memperbarui prestasi: $judul";
            } else {
                $pdo->prepare("INSERT INTO $table (judul, pencapaian, kategori, tingkat, nama_tim, tahun, deskripsi, status, urutan, gambar) VALUES (?,?,?,?,?,?,?,?,?,?)")
                    ->execute([$judul,$pencapaian,$kategori,$tingkat,$nama_tim,$tahun,$deskripsi,$status,$urutan,$gambar ?: null]);
                $action = 'ADD_PRESTASI'; $detail = "Menambah prestasi: $judul";
            }
            $pdo->prepare("INSERT INTO admin_logs (user_id, user_name, action, detail, ip) VALUES (?, ?, ?, ?, ?)")
                ->execute([$_SESSION['admin_id'], $_SESSION['admin_name'], $action, $detail, $_SERVER['REMOTE_ADDR']]);
            header("Location: kelola_prestasi.php?msg=saved"); exit;
        }
    }
}

// ===== FETCH + FILTER =====
$search = trim($_GET['q'] ?? '');
$fkat = $_GET['kategori'] ?? 'all';
$fting = $_GET['tingkat'] ?? 'all';
$sql = "SELECT * FROM $table WHERE 1=1"; $params = [];
if ($search !== '') { $sql .= " AND (judul LIKE ? OR nama_tim LIKE ? OR pencapaian LIKE ?)"; $params = array_merge($params, ["%$search%","%$search%","%$search%"]); }
if ($fkat !== 'all') { $sql .= " AND kategori = ?"; $params[] = $fkat; }
if ($fting !== 'all') { $sql .= " AND tingkat = ?"; $params[] = $fting; }
$sql .= " ORDER BY tahun DESC, urutan ASC";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$items = $stmt->fetchAll();

$total_all = (int)$pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
$total_intl = (int)$pdo->query("SELECT COUNT(*) FROM $table WHERE tingkat='internasional'")->fetchColumn();
$total_nas = (int)$pdo->query("SELECT COUNT(*) FROM $table WHERE tingkat='nasional'")->fetchColumn();
$total_thn = (int)$pdo->query("SELECT COUNT(*) FROM $table WHERE tahun = $tahun_now")->fetchColumn();

$data = ['id'=>0,'judul'=>'','pencapaian'=>'','kategori'=>'kompetisi','tingkat'=>'nasional','nama_tim'=>'','tahun'=>$tahun_now,'deskripsi'=>'','status'=>'aktif','urutan'=>0,'gambar'=>''];
if (isset($_GET['edit']) && isset($_GET['token']) && hash_equals(hash_hmac('sha256', $_GET['edit'], $_SESSION['csrf_token']), $_GET['token'])) {
    $stmt = $pdo->prepare("SELECT * FROM $table WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $f = $stmt->fetch();
    if ($f) $data = array_merge($data, array_map(fn($v) => $v === null ? '' : $v, $f));
}
ob_start();
?>

<style>
.pst-badge{display:inline-block;padding:.2rem .6rem;border-radius:50px;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;}
.pst-akademik{background:rgba(135,206,235,.15);color:var(--sky-blue);}
.pst-nonakademik{background:rgba(46,139,87,.15);color:var(--success);}
.pst-kompetisi{background:rgba(138,21,56,.15);color:var(--crimson);}
.pst-penelitian{background:rgba(155,89,182,.15);color:#9b59b6;}
.pst-sastra{background:rgba(236,72,153,.15);color:#EC4899;}
.pst-bahasa{background:rgba(243,156,18,.15);color:var(--warning);}
.tkt-internasional{background:linear-gradient(135deg,#f39c12,#f1c40f);color:#000;font-weight:800;}
.tkt-nasional{background:linear-gradient(135deg,var(--crimson),var(--crimson-light));color:#fff;font-weight:800;}
.tkt-regional{background:rgba(135,206,235,.2);color:var(--sky-blue);font-weight:800;}
.tkt-internal{background:rgba(255,255,255,.08);color:var(--text-muted);font-weight:800;}
.pst-tahun{font-family:'Courier New',monospace;background:var(--bg-darker);padding:.2rem .6rem;border-radius:6px;color:var(--sky-blue);font-weight:800;font-size:.85rem;}
.pst-thumb{width:46px;height:46px;border-radius:var(--radius-sm);object-fit:cover;background:var(--bg-darker);border:1px solid var(--border-dark);}
.pst-stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1.2rem;margin-bottom:2rem;}
.pst-stat-card{background:var(--panel-bg);border:1px solid var(--border-dark);border-left:4px solid var(--crimson);border-radius:var(--radius-md);padding:1.3rem;}
.pst-stat-card.accent-gold{border-left-color:#f1c40f;}
.pst-stat-card.accent-sky{border-left-color:var(--sky-blue);}
.pst-stat-card.accent-emerald{border-left-color:var(--success);}
.pst-stat-value{font-size:2rem;font-weight:900;color:#fff;line-height:1;}
.pst-stat-label{color:var(--text-muted);font-size:.85rem;margin-top:.3rem;}
</style>

<?php if (isset($_GET['msg'])):
    $msgs = ['saved'=>['success','Prestasi berhasil disimpan!'],'deleted'=>['success','Prestasi berhasil dihapus!']];
    if (isset($msgs[$_GET['msg']])): ?>
<script>Swal.fire({icon:'<?=$msgs[$_GET['msg']][0]?>',title:'Berhasil!',text:'<?=$msgs[$_GET['msg']][1]?>',timer:2500,showConfirmButton:false});</script>
<?php endif; endif; ?>
<?php if (isset($error)): ?>
<script>Swal.fire({icon:'error',title:'Validasi Gagal',text:'<?= addslashes($error) ?>'});</script>
<?php endif; ?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
    <h2 class="section-title"><i class="fa-solid fa-trophy"></i> <?= $data['id'] ? 'Edit' : 'Kelola' ?> Prestasi</h2>
    <?php if (!$data['id']): ?>
    <a href="?action=add" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Tambah Prestasi</a>
    <?php else: ?>
    <a href="kelola_prestasi.php" class="btn btn-secondary"><i class="fa-solid fa-xmark"></i> Batal</a>
    <?php endif; ?>
</div>

<div class="pst-stats-grid">
    <div class="pst-stat-card"><div class="pst-stat-value"><?= $total_all ?></div><div class="pst-stat-label">Total Prestasi</div></div>
    <div class="pst-stat-card accent-gold"><div class="pst-stat-value"><?= $total_intl ?></div><div class="pst-stat-label">Level Internasional</div></div>
    <div class="pst-stat-card accent-sky"><div class="pst-stat-value"><?= $total_nas ?></div><div class="pst-stat-label">Level Nasional</div></div>
    <div class="pst-stat-card accent-emerald"><div class="pst-stat-value"><?= $total_thn ?></div><div class="pst-stat-label">Prestasi <?= $tahun_now ?></div></div>
</div>

<?php if (isset($_GET['action']) && $_GET['action'] === 'add' || $data['id']): ?>
<div class="panel">
    <div class="panel-header"><h3 class="panel-title"><i class="fa-solid fa-pen-to-square"></i> <?= $data['id'] ? 'Edit' : 'Tambah' ?> Prestasi</h3></div>
    <div class="panel-body">
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="id" value="<?= $data['id'] ?>">
            <input type="hidden" name="old_gambar" value="<?= htmlspecialchars($data['gambar']) ?>">
            <div class="form-group"><label>Judul Lomba / Kegiatan <span class="required">*</span></label>
                <input type="text" name="judul" class="form-control" value="<?= htmlspecialchars($data['judul']) ?>" required placeholder="Contoh: National English Debate Competition"></div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                <div class="form-group"><label>Pencapaian <span class="required">*</span></label>
                    <input type="text" name="pencapaian" class="form-control" value="<?= htmlspecialchars($data['pencapaian']) ?>" required placeholder="Contoh: Juara 1 Nasional"></div>
                <div class="form-group"><label>Nama Tim / Individu</label>
                    <input type="text" name="nama_tim" class="form-control" value="<?= htmlspecialchars($data['nama_tim']) ?>" placeholder="Contoh: Tim PBSI UMMaumere"></div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:1rem;">
                <div class="form-group"><label>Kategori</label>
                    <select name="kategori" class="form-control"><?php foreach ($kategori_list as $k): ?><option value="<?= $k ?>" <?= $data['kategori']===$k?'selected':'' ?>><?= ucfirst($k) ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>Tingkat</label>
                    <select name="tingkat" class="form-control"><?php foreach ($tingkat_list as $t): ?><option value="<?= $t ?>" <?= $data['tingkat']===$t?'selected':'' ?>><?= ucfirst($t) ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>Tahun</label>
                    <select name="tahun" class="form-control"><?php for ($y = $tahun_now + 1; $y >= 2015; $y--): ?><option value="<?= $y ?>" <?= (int)$data['tahun']===$y?'selected':'' ?>><?= $y ?></option><?php endfor; ?></select></div>
                <div class="form-group"><label>Urutan</label>
                    <input type="number" name="urutan" class="form-control" value="<?= (int)$data['urutan'] ?>"></div>
            </div>
            <div class="form-group"><label>Deskripsi</label>
                <textarea name="deskripsi" rows="3" class="form-control" placeholder="Cerita singkat tentang prestasi ini..."><?= htmlspecialchars($data['deskripsi']) ?></textarea></div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                <div class="form-group"><label>Foto / Sertifikat (opsional)</label>
                    <input type="file" name="gambar" class="form-control" accept="image/*">
                    <?php if (!empty($data['gambar'])): ?><img src="../<?= htmlspecialchars($data['gambar']) ?>" class="pst-thumb" style="margin-top:.5rem;"><?php endif; ?></div>
                <div class="form-group"><label>Status</label>
                    <select name="status" class="form-control"><option value="aktif" <?= $data['status']==='aktif'?'selected':'' ?>>Aktif</option><option value="nonaktif" <?= $data['status']==='nonaktif'?'selected':'' ?>>Nonaktif</option></select></div>
            </div>
            <div style="display:flex;gap:1rem;margin-top:1.5rem;">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Simpan Prestasi</button>
                <a href="kelola_prestasi.php" class="btn btn-secondary"><i class="fa-solid fa-xmark"></i> Batal</a>
            </div>
        </form>
    </div>
</div>
<?php else: ?>
<div class="panel" style="margin-bottom:1.5rem;">
    <div class="panel-body">
        <form method="GET" style="display:grid;grid-template-columns:2fr 1fr 1fr auto;gap:1rem;align-items:end;">
            <div class="form-group" style="margin:0;"><label>Cari</label><input type="text" name="q" class="form-control" value="<?= htmlspecialchars($search) ?>" placeholder="Judul / tim / pencapaian..."></div>
            <div class="form-group" style="margin:0;"><label>Kategori</label>
                <select name="kategori" class="form-control"><option value="all">Semua</option><?php foreach ($kategori_list as $k): ?><option value="<?= $k ?>" <?= $fkat===$k?'selected':'' ?>><?= ucfirst($k) ?></option><?php endforeach; ?></select></div>
            <div class="form-group" style="margin:0;"><label>Tingkat</label>
                <select name="tingkat" class="form-control"><option value="all">Semua</option><?php foreach ($tingkat_list as $t): ?><option value="<?= $t ?>" <?= $fting===$t?'selected':'' ?>><?= ucfirst($t) ?></option><?php endforeach; ?></select></div>
            <button class="btn btn-primary" type="submit"><i class="fa-solid fa-filter"></i> Terapkan</button>
        </form>
    </div>
</div>

<div class="panel">
    <div class="panel-body no-padding">
        <div class="table-wrapper">
            <table>
                <thead><tr><th style="width:60px;"></th><th>Prestasi</th><th style="width:150px;">Pencapaian</th><th style="width:120px;">Kategori</th><th style="width:130px;">Tingkat</th><th style="width:80px;">Tahun</th><th style="width:120px;">Aksi</th></tr></thead>
                <tbody>
                <?php if (empty($items)): ?>
                    <tr><td colspan="7" style="text-align:center;padding:3rem;color:var(--text-muted);"><i class="fa-solid fa-trophy" style="font-size:3rem;opacity:.2;display:block;margin-bottom:1rem;"></i>Belum ada prestasi.</td></tr>
                <?php else: foreach ($items as $item): $token = hash_hmac('sha256', $item['id'], $_SESSION['csrf_token']); ?>
                    <tr>
                        <td><?php if (!empty($item['gambar'])): ?><img src="../<?= htmlspecialchars($item['gambar']) ?>" class="pst-thumb"><?php else: ?><span class="pst-thumb" style="display:flex;align-items:center;justify-content:center;color:var(--crimson);"><i class="fa-solid fa-trophy"></i></span><?php endif; ?></td>
                        <td><strong><?= htmlspecialchars($item['judul']) ?></strong><?php if ($item['nama_tim']): ?><br><small style="color:var(--text-muted);"><i class="fa-solid fa-users"></i> <?= htmlspecialchars($item['nama_tim']) ?></small><?php endif; ?></td>
                        <td><span style="color:var(--success);font-weight:700;font-size:.88rem;"><i class="fa-solid fa-medal"></i> <?= htmlspecialchars($item['pencapaian']) ?></span></td>
                        <td><span class="pst-badge pst-<?= htmlspecialchars($item['kategori']) ?>"><?= htmlspecialchars($item['kategori']) ?></span></td>
                        <td><span class="pst-badge tkt-<?= htmlspecialchars($item['tingkat']) ?>"><?= htmlspecialchars($item['tingkat']) ?></span></td>
                        <td><span class="pst-tahun"><?= $item['tahun'] ?: '-' ?></span></td>
                        <td><div class="table-actions">
                            <a href="?edit=<?= $item['id'] ?>&token=<?= $token ?>" class="table-action-btn edit" title="Edit"><i class="fa-solid fa-pen"></i></a>
                            <a href="?delete=<?= $item['id'] ?>&token=<?= $token ?>" class="table-action-btn delete" title="Hapus" onclick="return confirm('Hapus prestasi ini?')"><i class="fa-solid fa-trash"></i></a>
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