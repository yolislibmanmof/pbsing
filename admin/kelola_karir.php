<?php
require_once '../includes/config.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';
require_login();

$page_title = 'Kelola Prospek Karir';
$current_page = 'karir';
$table = 'karir_paths';
$permintaan_list = ['rendah','sedang','tinggi','sangat_tinggi'];

function fmt_rupiah($n) { return 'Rp ' . number_format((float)$n, 0, ',', '.'); }

// ===== DELETE =====
if (isset($_GET['delete']) && isset($_GET['token'])) {
    $id = (int)$_GET['delete'];
    if (hash_equals(hash_hmac('sha256', $id, $_SESSION['csrf_token']), $_GET['token'])) {
        $stmt = $pdo->prepare("SELECT jabatan FROM $table WHERE id = ?");
        $stmt->execute([$id]);
        $old = $stmt->fetch();
        if ($old) {
            $pdo->prepare("DELETE FROM $table WHERE id = ?")->execute([$id]);
            $pdo->prepare("INSERT INTO admin_logs (user_id, user_name, action, detail, ip) VALUES (?, ?, ?, ?, ?)")
                ->execute([$_SESSION['admin_id'], $_SESSION['admin_name'], 'DELETE_KARIR', 'Menghapus karir: ' . $old['jabatan'], $_SERVER['REMOTE_ADDR']]);
        }
        header("Location: kelola_karir.php?msg=deleted"); exit;
    }
}

// ===== ADD / EDIT =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $id = (int)($_POST['id'] ?? 0);
    $jabatan = sanitize($_POST['jabatan'] ?? '');
    $ikon = sanitize($_POST['ikon'] ?? 'fa-briefcase');
    $deskripsi = sanitize($_POST['deskripsi'] ?? '');
    $gaji_min = !empty($_POST['gaji_min']) ? (int)$_POST['gaji_min'] : null;
    $gaji_max = !empty($_POST['gaji_max']) ? (int)$_POST['gaji_max'] : null;
    $permintaan = in_array($_POST['permintaan'] ?? '', $permintaan_list) ? $_POST['permintaan'] : 'tinggi';
    $status = ($_POST['status'] ?? 'aktif') === 'nonaktif' ? 'nonaktif' : 'aktif';
    $urutan = (int)($_POST['urutan'] ?? 0);

    if (strlen($jabatan) < 3) $error = "Nama jabatan minimal 3 karakter.";
    elseif ($gaji_min !== null && $gaji_max !== null && $gaji_min > $gaji_max) $error = "Gaji minimum tidak boleh lebih besar dari gaji maksimum.";
    else {
        if ($id > 0) {
            $pdo->prepare("UPDATE $table SET jabatan=?, ikon=?, deskripsi=?, gaji_min=?, gaji_max=?, permintaan=?, status=?, urutan=? WHERE id=?")
                ->execute([$jabatan,$ikon,$deskripsi,$gaji_min,$gaji_max,$permintaan,$status,$urutan,$id]);
            $action = 'UPDATE_KARIR'; $detail = "Memperbarui karir: $jabatan";
        } else {
            $pdo->prepare("INSERT INTO $table (jabatan, ikon, deskripsi, gaji_min, gaji_max, permintaan, status, urutan) VALUES (?,?,?,?,?,?,?,?)")
                ->execute([$jabatan,$ikon,$deskripsi,$gaji_min,$gaji_max,$permintaan,$status,$urutan]);
            $action = 'ADD_KARIR'; $detail = "Menambah karir: $jabatan";
        }
        $pdo->prepare("INSERT INTO admin_logs (user_id, user_name, action, detail, ip) VALUES (?, ?, ?, ?, ?)")
            ->execute([$_SESSION['admin_id'], $_SESSION['admin_name'], $action, $detail, $_SERVER['REMOTE_ADDR']]);
        header("Location: kelola_karir.php?msg=saved"); exit;
    }
}

// ===== FETCH + FILTER =====
$search = trim($_GET['q'] ?? '');
$fperm = $_GET['permintaan'] ?? 'all';
$fstat = $_GET['status'] ?? 'all';
$sql = "SELECT * FROM $table WHERE 1=1"; $params = [];
if ($search !== '') { $sql .= " AND (jabatan LIKE ? OR deskripsi LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($fperm !== 'all') { $sql .= " AND permintaan = ?"; $params[] = $fperm; }
if ($fstat !== 'all') { $sql .= " AND status = ?"; $params[] = $fstat; }
$sql .= " ORDER BY urutan ASC, jabatan ASC";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$items = $stmt->fetchAll();

$total_all = (int)$pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
$total_aktif = (int)$pdo->query("SELECT COUNT(*) FROM $table WHERE status='aktif'")->fetchColumn();
$total_hot = (int)$pdo->query("SELECT COUNT(*) FROM $table WHERE permintaan='sangat_tinggi'")->fetchColumn();
$avg_gaji = (int)$pdo->query("SELECT COALESCE(AVG(gaji_max),0) FROM $table WHERE gaji_max IS NOT NULL")->fetchColumn();

$data = ['id'=>0,'jabatan'=>'','ikon'=>'fa-briefcase','deskripsi'=>'','gaji_min'=>'','gaji_max'=>'','permintaan'=>'tinggi','status'=>'aktif','urutan'=>0];
if (isset($_GET['edit']) && isset($_GET['token']) && hash_equals(hash_hmac('sha256', $_GET['edit'], $_SESSION['csrf_token']), $_GET['token'])) {
    $stmt = $pdo->prepare("SELECT * FROM $table WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $f = $stmt->fetch();
    if ($f) $data = array_merge($data, array_map(fn($v) => $v === null ? '' : $v, $f));
}
ob_start();
?>

<style>
.kar-badge{display:inline-block;padding:.2rem .6rem;border-radius:50px;font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.5px;}
.kar-sangat_tinggi{background:linear-gradient(135deg,#f39c12,#f1c40f);color:#000;}
.kar-tinggi{background:linear-gradient(135deg,var(--crimson),var(--crimson-light));color:#fff;}
.kar-sedang{background:rgba(135,206,235,.2);color:var(--sky-blue);}
.kar-rendah{background:rgba(255,255,255,.08);color:var(--text-muted);}
.kar-icon-prev{width:52px;height:52px;border-radius:var(--radius-md);background:rgba(138,21,56,.12);color:var(--crimson);display:flex;align-items:center;justify-content:center;font-size:1.4rem;flex-shrink:0;}
.kar-gaji{font-family:'Courier New',monospace;font-size:.85rem;color:var(--success);font-weight:700;}
.kar-stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1.2rem;margin-bottom:2rem;}
.kar-stat-card{background:var(--panel-bg);border:1px solid var(--border-dark);border-left:4px solid var(--crimson);border-radius:var(--radius-md);padding:1.3rem;}
.kar-stat-card.accent-gold{border-left-color:#f1c40f;}
.kar-stat-card.accent-emerald{border-left-color:var(--success);}
.kar-stat-card.accent-sky{border-left-color:var(--sky-blue);}
.kar-stat-value{font-size:1.7rem;font-weight:900;color:#fff;line-height:1.2;}
.kar-stat-label{color:var(--text-muted);font-size:.85rem;margin-top:.3rem;}
</style>

<?php if (isset($_GET['msg'])):
    $msgs = ['saved'=>['success','Prospek karir berhasil disimpan!'],'deleted'=>['success','Prospek karir berhasil dihapus!']];
    if (isset($msgs[$_GET['msg']])): ?>
<script>Swal.fire({icon:'<?=$msgs[$_GET['msg']][0]?>',title:'Berhasil!',text:'<?=$msgs[$_GET['msg']][1]?>',timer:2500,showConfirmButton:false});</script>
<?php endif; endif; ?>
<?php if (isset($error)): ?>
<script>Swal.fire({icon:'error',title:'Validasi Gagal',text:'<?= addslashes($error) ?>'});</script>
<?php endif; ?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
    <h2 class="section-title"><i class="fa-solid fa-briefcase"></i> <?= $data['id'] ? 'Edit' : 'Kelola' ?> Prospek Karir</h2>
    <?php if (!$data['id']): ?>
    <a href="?action=add" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Tambah Karir</a>
    <?php else: ?>
    <a href="kelola_karir.php" class="btn btn-secondary"><i class="fa-solid fa-xmark"></i> Batal</a>
    <?php endif; ?>
</div>

<div class="kar-stats-grid">
    <div class="kar-stat-card"><div class="kar-stat-value"><?= $total_all ?></div><div class="kar-stat-label">Total Jalur Karir</div></div>
    <div class="kar-stat-card accent-emerald"><div class="kar-stat-value"><?= $total_aktif ?></div><div class="kar-stat-label">Karir Aktif</div></div>
    <div class="kar-stat-card accent-gold"><div class="kar-stat-value"><?= $total_hot ?></div><div class="kar-stat-label">Permintaan Sangat Tinggi</div></div>
    <div class="kar-stat-card accent-sky"><div class="kar-stat-value"><?= $avg_gaji ? fmt_rupiah($avg_gaji) : '-' ?></div><div class="kar-stat-label">Rata-rata Gaji Maks</div></div>
</div>

<?php if (isset($_GET['action']) && $_GET['action'] === 'add' || $data['id']): ?>
<div class="panel">
    <div class="panel-header"><h3 class="panel-title"><i class="fa-solid fa-pen-to-square"></i> <?= $data['id'] ? 'Edit' : 'Tambah' ?> Jalur Karir</h3></div>
    <div class="panel-body">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="id" value="<?= $data['id'] ?>">
            <div style="display:grid;grid-template-columns:2fr 1fr;gap:1rem;">
                <div class="form-group"><label>Nama Jabatan <span class="required">*</span></label>
                    <input type="text" name="jabatan" class="form-control" value="<?= htmlspecialchars($data['jabatan']) ?>" required placeholder="Contoh: Penerjemah Profesional"></div>
                <div class="form-group"><label>Ikon (Font Awesome)</label>
                    <div style="display:flex;gap:.8rem;align-items:center;">
                        <span class="kar-icon-prev"><i id="ikonPrevI" class="fa-solid <?= htmlspecialchars($data['ikon']) ?>"></i></span>
                        <input type="text" name="ikon" id="f_ikon" class="form-control" value="<?= htmlspecialchars($data['ikon']) ?>" list="ikonListKar" placeholder="fa-language">
                    </div>
                    <datalist id="ikonListKar">
                        <option value="fa-briefcase"><option value="fa-language"><option value="fa-chalkboard-teacher"><option value="fa-globe">
                        <option value="fa-pen-fancy"><option value="fa-book"><option value="fa-microphone"><option value="fa-newspaper">
                        <option value="fa-microscope"><option value="fa-scale-balanced"><option value="fa-plane"><option value="fa-laptop">
                    </datalist></div>
            </div>
            <div class="form-group"><label>Deskripsi Pekerjaan</label>
                <textarea name="deskripsi" rows="3" class="form-control" placeholder="Deskripsi singkat pekerjaan..."><?= htmlspecialchars($data['deskripsi']) ?></textarea></div>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:1rem;">
                <div class="form-group"><label>Gaji Minimum (Rp)</label>
                    <input type="number" name="gaji_min" class="form-control" value="<?= htmlspecialchars($data['gaji_min']) ?>" min="0" step="100000" placeholder="6000000"></div>
                <div class="form-group"><label>Gaji Maksimum (Rp)</label>
                    <input type="number" name="gaji_max" class="form-control" value="<?= htmlspecialchars($data['gaji_max']) ?>" min="0" step="100000" placeholder="20000000"></div>
                <div class="form-group"><label>Permintaan Pasar</label>
                    <select name="permintaan" class="form-control"><?php foreach ($permintaan_list as $p): ?><option value="<?= $p ?>" <?= $data['permintaan']===$p?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$p)) ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>Urutan</label>
                    <input type="number" name="urutan" class="form-control" value="<?= (int)$data['urutan'] ?>"></div>
            </div>
            <div class="form-group" style="max-width:220px;"><label>Status</label>
                <select name="status" class="form-control"><option value="aktif" <?= $data['status']==='aktif'?'selected':'' ?>>Aktif</option><option value="nonaktif" <?= $data['status']==='nonaktif'?'selected':'' ?>>Nonaktif</option></select></div>
            <div style="display:flex;gap:1rem;margin-top:1.5rem;">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Simpan Karir</button>
                <a href="kelola_karir.php" class="btn btn-secondary"><i class="fa-solid fa-xmark"></i> Batal</a>
            </div>
        </form>
    </div>
</div>
<script>
document.getElementById('f_ikon').addEventListener('input', function(){
    var v = this.value.trim() || 'fa-briefcase';
    document.getElementById('ikonPrevI').className = (v.indexOf('fa-solid') === 0 ? v : 'fa-solid ' + v);
});
</script>
<?php else: ?>
<div class="panel" style="margin-bottom:1.5rem;">
    <div class="panel-body">
        <form method="GET" style="display:grid;grid-template-columns:2fr 1fr 1fr auto;gap:1rem;align-items:end;">
            <div class="form-group" style="margin:0;"><label>Cari</label><input type="text" name="q" class="form-control" value="<?= htmlspecialchars($search) ?>" placeholder="Nama jabatan..."></div>
            <div class="form-group" style="margin:0;"><label>Permintaan</label>
                <select name="permintaan" class="form-control"><option value="all">Semua</option><?php foreach ($permintaan_list as $p): ?><option value="<?= $p ?>" <?= $fperm===$p?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$p)) ?></option><?php endforeach; ?></select></div>
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
                <thead><tr><th style="width:60px;"></th><th>Jabatan</th><th style="width:220px;">Rentang Gaji</th><th style="width:140px;">Permintaan</th><th style="width:90px;">Status</th><th style="width:120px;">Aksi</th></tr></thead>
                <tbody>
                <?php if (empty($items)): ?>
                    <tr><td colspan="6" style="text-align:center;padding:3rem;color:var(--text-muted);"><i class="fa-solid fa-briefcase" style="font-size:3rem;opacity:.2;display:block;margin-bottom:1rem;"></i>Belum ada data karir.</td></tr>
                <?php else: foreach ($items as $item): $token = hash_hmac('sha256', $item['id'], $_SESSION['csrf_token']); ?>
                    <tr>
                        <td><span class="kar-icon-prev" style="width:46px;height:46px;font-size:1.1rem;"><i class="fa-solid <?= htmlspecialchars($item['ikon']) ?>"></i></span></td>
                        <td><strong><?= htmlspecialchars($item['jabatan']) ?></strong><?php if ($item['deskripsi']): ?><br><small style="color:var(--text-muted);"><?= htmlspecialchars(substr($item['deskripsi'],0,70)) ?><?= strlen($item['deskripsi'])>70?'...':'' ?></small><?php endif; ?></td>
                        <td><?php if ($item['gaji_min'] || $item['gaji_max']): ?><span class="kar-gaji"><?= $item['gaji_min'] ? fmt_rupiah($item['gaji_min']) : '?' ?> – <?= $item['gaji_max'] ? fmt_rupiah($item['gaji_max']) : '?' ?></span><?php else: ?><span style="color:var(--text-muted);">-</span><?php endif; ?></td>
                        <td><span class="kar-badge kar-<?= htmlspecialchars($item['permintaan']) ?>"><?= str_replace('_',' ',htmlspecialchars($item['permintaan'])) ?></span></td>
                        <td><span class="kar-badge" style="<?= $item['status']==='aktif' ? 'background:rgba(46,139,87,.15);color:var(--success);' : 'background:rgba(255,255,255,.08);color:var(--text-muted);' ?>"><?= $item['status'] ?></span></td>
                        <td><div class="table-actions">
                            <a href="?edit=<?= $item['id'] ?>&token=<?= $token ?>" class="table-action-btn edit" title="Edit"><i class="fa-solid fa-pen"></i></a>
                            <a href="?delete=<?= $item['id'] ?>&token=<?= $token ?>" class="table-action-btn delete" title="Hapus" onclick="return confirm('Hapus jalur karir ini?')"><i class="fa-solid fa-trash"></i></a>
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