<?php
require_once '../includes/config.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';
require_once 'includes/upload_helper.php';
require_login();

$page_title = 'Kelola Fasilitas';
$current_page = 'fasilitas';
$table = 'fasilitas';
$jenis_list = ['laboratorium','studio','ruangan','peralatan','lainnya'];

// ===== DELETE =====
if (isset($_GET['delete']) && isset($_GET['token'])) {
    $id = (int)$_GET['delete'];
    if (hash_equals(hash_hmac('sha256', $id, $_SESSION['csrf_token']), $_GET['token'])) {
        $stmt = $pdo->prepare("SELECT gambar, nama FROM $table WHERE id = ?");
        $stmt->execute([$id]);
        $old = $stmt->fetch();
        if ($old) {
            if (!empty($old['gambar']) && file_exists('../' . $old['gambar'])) @unlink('../' . $old['gambar']);
            $pdo->prepare("DELETE FROM $table WHERE id = ?")->execute([$id]);
            $pdo->prepare("INSERT INTO admin_logs (user_id, user_name, action, detail, ip) VALUES (?, ?, ?, ?, ?)")
                ->execute([$_SESSION['admin_id'], $_SESSION['admin_name'], 'DELETE_FASILITAS', 'Menghapus fasilitas: ' . $old['nama'], $_SERVER['REMOTE_ADDR']]);
        }
        header("Location: kelola_fasilitas.php?msg=deleted"); exit;
    }
}

// ===== ADD / EDIT =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $id = (int)($_POST['id'] ?? 0);
    $nama = sanitize($_POST['nama'] ?? '');
    $jenis = in_array($_POST['jenis'] ?? '', $jenis_list) ? $_POST['jenis'] : 'lainnya';
    $lokasi = sanitize($_POST['lokasi'] ?? '');
    $ikon = sanitize($_POST['ikon'] ?? 'fa-book');
    $kapasitas = !empty($_POST['kapasitas']) ? (int)$_POST['kapasitas'] : null;
    $deskripsi = sanitize($_POST['deskripsi'] ?? '');
    $status = ($_POST['status'] ?? 'aktif') === 'nonaktif' ? 'nonaktif' : 'aktif';
    $urutan = (int)($_POST['urutan'] ?? 0);
    $gambar = $_POST['old_gambar'] ?? '';

    if (strlen($nama) < 3) $error = "Nama fasilitas minimal 3 karakter.";
    else {
        if (!empty($_FILES['gambar']['name'])) {
            $upload = handle_upload($_FILES['gambar'], '../assets/uploads/fasilitas/', ['jpg','jpeg','png','webp']);
            if ($upload['success']) {
                if (!empty($gambar) && file_exists('../' . $gambar)) @unlink('../' . $gambar);
                $gambar = $upload['path'];
            } else $error = $upload['error'];
        }
        if (!isset($error)) {
            if ($id > 0) {
                $pdo->prepare("UPDATE $table SET nama=?, jenis=?, lokasi=?, ikon=?, kapasitas=?, deskripsi=?, status=?, urutan=?, gambar=? WHERE id=?")
                    ->execute([$nama,$jenis,$lokasi,$ikon,$kapasitas,$deskripsi,$status,$urutan,$gambar ?: null,$id]);
                $action = 'UPDATE_FASILITAS'; $detail = "Memperbarui fasilitas: $nama";
            } else {
                $pdo->prepare("INSERT INTO $table (nama, jenis, lokasi, ikon, kapasitas, deskripsi, status, urutan, gambar) VALUES (?,?,?,?,?,?,?,?,?)")
                    ->execute([$nama,$jenis,$lokasi,$ikon,$kapasitas,$deskripsi,$status,$urutan,$gambar ?: null]);
                $action = 'ADD_FASILITAS'; $detail = "Menambah fasilitas: $nama";
            }
            $pdo->prepare("INSERT INTO admin_logs (user_id, user_name, action, detail, ip) VALUES (?, ?, ?, ?, ?)")
                ->execute([$_SESSION['admin_id'], $_SESSION['admin_name'], $action, $detail, $_SERVER['REMOTE_ADDR']]);
            header("Location: kelola_fasilitas.php?msg=saved"); exit;
        }
    }
}

// ===== FETCH + FILTER =====
$search = trim($_GET['q'] ?? '');
$fjenis = $_GET['jenis'] ?? 'all';
$fstat = $_GET['status'] ?? 'all';
$sql = "SELECT * FROM $table WHERE 1=1"; $params = [];
if ($search !== '') { $sql .= " AND (nama LIKE ? OR deskripsi LIKE ? OR lokasi LIKE ?)"; $params = array_merge($params, ["%$search%","%$search%","%$search%"]); }
if ($fjenis !== 'all') { $sql .= " AND jenis = ?"; $params[] = $fjenis; }
if ($fstat !== 'all') { $sql .= " AND status = ?"; $params[] = $fstat; }
$sql .= " ORDER BY urutan ASC, nama ASC";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$items = $stmt->fetchAll();

$total_all = (int)$pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
$total_aktif = (int)$pdo->query("SELECT COUNT(*) FROM $table WHERE status='aktif'")->fetchColumn();
$total_lab = (int)$pdo->query("SELECT COUNT(*) FROM $table WHERE jenis='laboratorium'")->fetchColumn();
$total_kap = (int)$pdo->query("SELECT COALESCE(SUM(kapasitas),0) FROM $table")->fetchColumn();

$data = ['id'=>0,'nama'=>'','jenis'=>'laboratorium','lokasi'=>'','ikon'=>'fa-book','kapasitas'=>null,'deskripsi'=>'','status'=>'aktif','urutan'=>0,'gambar'=>''];
if (isset($_GET['edit']) && isset($_GET['token']) && hash_equals(hash_hmac('sha256', $_GET['edit'], $_SESSION['csrf_token']), $_GET['token'])) {
    $stmt = $pdo->prepare("SELECT * FROM $table WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $f = $stmt->fetch();
    if ($f) $data = array_merge($data, array_map(fn($v) => $v === null ? '' : $v, $f));
}
ob_start();
?>

<style>
.fas-badge{display:inline-block;padding:.2rem .6rem;border-radius:50px;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;}
.fas-laboratorium{background:rgba(138,21,56,.15);color:var(--crimson);}
.fas-studio{background:rgba(155,89,182,.15);color:#9b59b6;}
.fas-ruangan{background:rgba(135,206,235,.15);color:var(--sky-blue);}
.fas-peralatan{background:rgba(243,156,18,.15);color:var(--warning);}
.fas-lainnya{background:rgba(255,255,255,.08);color:var(--text-muted);}
.fas-icon-prev{width:52px;height:52px;border-radius:var(--radius-md);background:rgba(138,21,56,.12);color:var(--crimson);display:flex;align-items:center;justify-content:center;font-size:1.4rem;flex-shrink:0;}
.fas-thumb{width:46px;height:46px;border-radius:var(--radius-sm);object-fit:cover;background:var(--bg-darker);border:1px solid var(--border-dark);}
.fas-stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1.2rem;margin-bottom:2rem;}
.fas-stat-card{background:var(--panel-bg);border:1px solid var(--border-dark);border-left:4px solid var(--crimson);border-radius:var(--radius-md);padding:1.3rem;}
.fas-stat-card.accent-sky{border-left-color:var(--sky-blue);}
.fas-stat-card.accent-emerald{border-left-color:var(--success);}
.fas-stat-card.accent-warning{border-left-color:var(--warning);}
.fas-stat-value{font-size:2rem;font-weight:900;color:#fff;line-height:1;}
.fas-stat-label{color:var(--text-muted);font-size:.85rem;margin-top:.3rem;}
</style>

<?php if (isset($_GET['msg'])):
    $msgs = ['saved'=>['success','Fasilitas berhasil disimpan!'],'deleted'=>['success','Fasilitas berhasil dihapus!']];
    if (isset($msgs[$_GET['msg']])): ?>
<script>Swal.fire({icon:'<?=$msgs[$_GET['msg']][0]?>',title:'Berhasil!',text:'<?=$msgs[$_GET['msg']][1]?>',timer:2500,showConfirmButton:false});</script>
<?php endif; endif; ?>
<?php if (isset($error)): ?>
<script>Swal.fire({icon:'error',title:'Validasi Gagal',text:'<?= addslashes($error) ?>'});</script>
<?php endif; ?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
    <h2 class="section-title"><i class="fa-solid fa-building"></i> <?= $data['id'] ? 'Edit' : 'Kelola' ?> Fasilitas</h2>
    <?php if (!$data['id']): ?>
    <a href="?action=add" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Tambah Fasilitas</a>
    <?php else: ?>
    <a href="kelola_fasilitas.php" class="btn btn-secondary"><i class="fa-solid fa-xmark"></i> Batal</a>
    <?php endif; ?>
</div>

<div class="fas-stats-grid">
    <div class="fas-stat-card"><div class="fas-stat-value"><?= $total_all ?></div><div class="fas-stat-label">Total Fasilitas</div></div>
    <div class="fas-stat-card accent-emerald"><div class="fas-stat-value"><?= $total_aktif ?></div><div class="fas-stat-label">Fasilitas Aktif</div></div>
    <div class="fas-stat-card accent-sky"><div class="fas-stat-value"><?= $total_lab ?></div><div class="fas-stat-label">Laboratorium/Studio</div></div>
    <div class="fas-stat-card accent-warning"><div class="fas-stat-value"><?= number_format($total_kap) ?></div><div class="fas-stat-label">Total Kapasitas</div></div>
</div>

<?php if (isset($_GET['action']) && $_GET['action'] === 'add' || $data['id']): ?>
<div class="panel">
    <div class="panel-header"><h3 class="panel-title"><i class="fa-solid fa-pen-to-square"></i> <?= $data['id'] ? 'Edit' : 'Tambah' ?> Fasilitas</h3></div>
    <div class="panel-body">
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="id" value="<?= $data['id'] ?>">
            <input type="hidden" name="old_gambar" value="<?= htmlspecialchars($data['gambar']) ?>">
            <div style="display:grid;grid-template-columns:2fr 1fr 1fr;gap:1rem;">
                <div class="form-group"><label>Nama Fasilitas <span class="required">*</span></label>
                    <input type="text" name="nama" class="form-control" value="<?= htmlspecialchars($data['nama']) ?>" required placeholder="Contoh: Language Laboratory"></div>
                <div class="form-group"><label>Jenis</label>
                    <select name="jenis" class="form-control"><?php foreach ($jenis_list as $j): ?><option value="<?= $j ?>" <?= $data['jenis']===$j?'selected':'' ?>><?= ucfirst($j) ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>Kapasitas (orang)</label>
                    <input type="number" name="kapasitas" class="form-control" value="<?= htmlspecialchars($data['kapasitas']) ?>" min="0" placeholder="40"></div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                <div class="form-group"><label>Lokasi</label>
                    <input type="text" name="lokasi" class="form-control" value="<?= htmlspecialchars($data['lokasi']) ?>" placeholder="Gedung A Lt. 2"></div>
                <div class="form-group"><label>Ikon (Font Awesome)</label>
                    <div style="display:flex;gap:.8rem;align-items:center;">
                        <span class="fas-icon-prev" id="ikonPrev"><i id="ikonPrevI" class="fa-solid <?= htmlspecialchars($data['ikon']) ?>"></i></span>
                        <input type="text" name="ikon" id="f_ikon" class="form-control" value="<?= htmlspecialchars($data['ikon']) ?>" list="ikonList" placeholder="fa-headphones">
                    </div>
                    <datalist id="ikonList">
                        <option value="fa-headphones"><option value="fa-language"><option value="fa-video"><option value="fa-book-open">
                        <option value="fa-users"><option value="fa-chalkboard-teacher"><option value="fa-microphone"><option value="fa-globe">
                        <option value="fa-flask"><option value="fa-music"><option value="fa-pen-fancy"><option value="fa-laptop">
                    </datalist></div>
            </div>
            <div class="form-group"><label>Deskripsi</label>
                <textarea name="deskripsi" rows="3" class="form-control" placeholder="Deskripsi fasilitas..."><?= htmlspecialchars($data['deskripsi']) ?></textarea></div>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;">
                <div class="form-group"><label>Gambar <?= $data['id'] ? '(opsional, kosongkan jika tidak diganti)' : '(opsional)' ?></label>
                    <input type="file" name="gambar" class="form-control" accept="image/*">
                    <?php if (!empty($data['gambar'])): ?><img src="../<?= htmlspecialchars($data['gambar']) ?>" class="fas-thumb" style="margin-top:.5rem;"><?php endif; ?></div>
                <div class="form-group"><label>Status</label>
                    <select name="status" class="form-control"><option value="aktif" <?= $data['status']==='aktif'?'selected':'' ?>>Aktif</option><option value="nonaktif" <?= $data['status']==='nonaktif'?'selected':'' ?>>Nonaktif</option></select></div>
                <div class="form-group"><label>Urutan Tampil</label>
                    <input type="number" name="urutan" class="form-control" value="<?= (int)$data['urutan'] ?>"></div>
            </div>
            <div style="display:flex;gap:1rem;margin-top:1.5rem;">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Simpan Fasilitas</button>
                <a href="kelola_fasilitas.php" class="btn btn-secondary"><i class="fa-solid fa-xmark"></i> Batal</a>
            </div>
        </form>
    </div>
</div>
<script>
document.getElementById('f_ikon').addEventListener('input', function(){
    var v = this.value.trim() || 'fa-book';
    var cls = v.indexOf('fa-solid') === 0 ? v : 'fa-solid ' + v;
    document.getElementById('ikonPrevI').className = cls;
});
</script>
<?php else: ?>
<div class="panel" style="margin-bottom:1.5rem;">
    <div class="panel-body">
        <form method="GET" style="display:grid;grid-template-columns:2fr 1fr 1fr auto;gap:1rem;align-items:end;">
            <div class="form-group" style="margin:0;"><label>Cari</label><input type="text" name="q" class="form-control" value="<?= htmlspecialchars($search) ?>" placeholder="Nama / lokasi..."></div>
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
                <thead><tr><th style="width:60px;"></th><th>Fasilitas</th><th style="width:130px;">Jenis</th><th style="width:140px;">Lokasi</th><th style="width:90px;">Kapasitas</th><th style="width:90px;">Status</th><th style="width:120px;">Aksi</th></tr></thead>
                <tbody>
                <?php if (empty($items)): ?>
                    <tr><td colspan="7" style="text-align:center;padding:3rem;color:var(--text-muted);"><i class="fa-solid fa-building" style="font-size:3rem;opacity:.2;display:block;margin-bottom:1rem;"></i>Belum ada fasilitas.</td></tr>
                <?php else: foreach ($items as $item): $token = hash_hmac('sha256', $item['id'], $_SESSION['csrf_token']); ?>
                    <tr>
                        <td><?php if (!empty($item['gambar'])): ?><img src="../<?= htmlspecialchars($item['gambar']) ?>" class="fas-thumb"><?php else: ?><span class="fas-icon-prev" style="width:46px;height:46px;font-size:1.1rem;"><i class="fa-solid <?= htmlspecialchars($item['ikon']) ?>"></i></span><?php endif; ?></td>
                        <td><strong><?= htmlspecialchars($item['nama']) ?></strong><?php if ($item['deskripsi']): ?><br><small style="color:var(--text-muted);"><?= htmlspecialchars(substr($item['deskripsi'],0,70)) ?><?= strlen($item['deskripsi'])>70?'...':'' ?></small><?php endif; ?></td>
                        <td><span class="fas-badge fas-<?= htmlspecialchars($item['jenis']) ?>"><?= htmlspecialchars($item['jenis']) ?></span></td>
                        <td style="color:var(--text-muted);font-size:.88rem;"><?= htmlspecialchars($item['lokasi'] ?: '-') ?></td>
                        <td><strong><?= $item['kapasitas'] ? number_format($item['kapasitas']) : '-' ?></strong></td>
                        <td><span class="fas-badge" style="<?= $item['status']==='aktif' ? 'background:rgba(46,139,87,.15);color:var(--success);' : 'background:rgba(255,255,255,.08);color:var(--text-muted);' ?>"><?= $item['status'] ?></span></td>
                        <td><div class="table-actions">
                            <a href="?edit=<?= $item['id'] ?>&token=<?= $token ?>" class="table-action-btn edit" title="Edit"><i class="fa-solid fa-pen"></i></a>
                            <a href="?delete=<?= $item['id'] ?>&token=<?= $token ?>" class="table-action-btn delete" title="Hapus" onclick="return confirm('Hapus fasilitas ini?')"><i class="fa-solid fa-trash"></i></a>
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