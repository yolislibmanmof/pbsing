<?php
require_once '../includes/config.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';
require_login();

$page_title = 'Kelola Konsentrasi';
$current_page = 'konsentrasi';
$table = 'konsentrasi';

// ===== DELETE =====
if (isset($_GET['delete']) && isset($_GET['token'])) {
    $id = (int)$_GET['delete'];
    if (hash_equals(hash_hmac('sha256', $id, $_SESSION['csrf_token']), $_GET['token'])) {
        $stmt = $pdo->prepare("SELECT nama FROM $table WHERE id = ?");
        $stmt->execute([$id]);
        $old = $stmt->fetch();
        if ($old) {
            // FK mata_kuliah.konsentrasi_id = ON DELETE SET NULL → aman
            $pdo->prepare("DELETE FROM $table WHERE id = ?")->execute([$id]);
            $pdo->prepare("INSERT INTO admin_logs (user_id, user_name, action, detail, ip) VALUES (?, ?, ?, ?, ?)")
                ->execute([$_SESSION['admin_id'], $_SESSION['admin_name'], 'DELETE_KONSENTRASI', 'Menghapus konsentrasi: ' . $old['nama'], $_SERVER['REMOTE_ADDR']]);
        }
        header("Location: kelola_konsentrasi.php?msg=deleted"); exit;
    }
}

// ===== ADD / EDIT =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $id = (int)($_POST['id'] ?? 0);
    $kode = strtoupper(trim(sanitize($_POST['kode'] ?? '')));
    $nama = sanitize($_POST['nama'] ?? '');
    $deskripsi = sanitize($_POST['deskripsi'] ?? '');
    $ikon = sanitize($_POST['ikon'] ?? 'fa-graduation-cap');
    $warna = sanitize($_POST['warna'] ?? '#10B981');
    $status = ($_POST['status'] ?? 'aktif') === 'nonaktif' ? 'nonaktif' : 'aktif';
    $urutan = (int)($_POST['urutan'] ?? 0);

    if (!preg_match('/^[A-Z0-9\-]{2,20}$/', $kode)) $error = "Kode harus 2-20 karakter huruf/angka (contoh: LING, TRANS).";
    elseif (strlen($nama) < 3) $error = "Nama konsentrasi minimal 3 karakter.";
    elseif (!preg_match('/^#[0-9a-fA-F]{6}$/', $warna)) $error = "Format warna harus hex (#RRGGBB).";
    else {
        // Cek kode unik
        $chk = $pdo->prepare("SELECT id FROM $table WHERE kode = ? AND id != ?");
        $chk->execute([$kode, $id]);
        if ($chk->fetch()) {
            $error = "Kode '$kode' sudah dipakai konsentrasi lain.";
        } else {
            if ($id > 0) {
                $pdo->prepare("UPDATE $table SET kode=?, nama=?, deskripsi=?, ikon=?, warna=?, status=?, urutan=? WHERE id=?")
                    ->execute([$kode,$nama,$deskripsi,$ikon,$warna,$status,$urutan,$id]);
                $action = 'UPDATE_KONSENTRASI'; $detail = "Memperbarui konsentrasi: $kode - $nama";
            } else {
                $pdo->prepare("INSERT INTO $table (kode, nama, deskripsi, ikon, warna, status, urutan) VALUES (?,?,?,?,?,?,?)")
                    ->execute([$kode,$nama,$deskripsi,$ikon,$warna,$status,$urutan]);
                $action = 'ADD_KONSENTRASI'; $detail = "Menambah konsentrasi: $kode - $nama";
            }
            $pdo->prepare("INSERT INTO admin_logs (user_id, user_name, action, detail, ip) VALUES (?, ?, ?, ?, ?)")
                ->execute([$_SESSION['admin_id'], $_SESSION['admin_name'], $action, $detail, $_SERVER['REMOTE_ADDR']]);
            header("Location: kelola_konsentrasi.php?msg=saved"); exit;
        }
    }
}

// ===== FETCH + FILTER =====
$search = trim($_GET['q'] ?? '');
$fstat = $_GET['status'] ?? 'all';
$sql = "SELECT * FROM $table WHERE 1=1"; $params = [];
if ($search !== '') { $sql .= " AND (kode LIKE ? OR nama LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($fstat !== 'all') { $sql .= " AND status = ?"; $params[] = $fstat; }
$sql .= " ORDER BY urutan ASC, id ASC";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$items = $stmt->fetchAll();

// Jumlah MK per konsentrasi
$mk_counts = [];
foreach ($items as $it) {
    $c = $pdo->prepare("SELECT COUNT(*) FROM mata_kuliah WHERE konsentrasi_id = ?");
    $c->execute([$it['id']]);
    $mk_counts[$it['id']] = (int)$c->fetchColumn();
}

$total_all = (int)$pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
$total_aktif = (int)$pdo->query("SELECT COUNT(*) FROM $table WHERE status='aktif'")->fetchColumn();
$total_mk = (int)$pdo->query("SELECT COUNT(*) FROM mata_kuliah WHERE konsentrasi_id IS NOT NULL")->fetchColumn();

$data = ['id'=>0,'kode'=>'','nama'=>'','deskripsi'=>'','ikon'=>'fa-graduation-cap','warna'=>'#10B981','status'=>'aktif','urutan'=>0];
if (isset($_GET['edit']) && isset($_GET['token']) && hash_equals(hash_hmac('sha256', $_GET['edit'], $_SESSION['csrf_token']), $_GET['token'])) {
    $stmt = $pdo->prepare("SELECT * FROM $table WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $f = $stmt->fetch();
    if ($f) $data = array_merge($data, array_map(fn($v) => $v === null ? '' : $v, $f));
}
ob_start();
?>

<style>
.kon-code{display:inline-block;padding:.3rem .8rem;border-radius:8px;font-family:'Courier New',monospace;font-weight:900;font-size:.9rem;color:#fff;letter-spacing:1px;box-shadow:0 4px 12px rgba(0,0,0,.25);}
.kon-icon-prev{width:52px;height:52px;border-radius:var(--radius-md);background:rgba(135,206,235,.12);color:var(--sky-blue);display:flex;align-items:center;justify-content:center;font-size:1.4rem;flex-shrink:0;}
.kon-color-prev{width:42px;height:42px;border-radius:var(--radius-md);border:2px solid var(--border-dark);flex-shrink:0;box-shadow:inset 0 0 0 2px rgba(255,255,255,.08);}
.kon-mk-chip{display:inline-flex;align-items:center;gap:.4rem;background:var(--bg-darker);border:1px solid var(--border-dark);border-radius:50px;padding:.2rem .7rem;font-size:.75rem;color:var(--text-muted);font-weight:700;}
.kon-mk-chip i{color:var(--sky-blue);}
.kon-stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1.2rem;margin-bottom:2rem;}
.kon-stat-card{background:var(--panel-bg);border:1px solid var(--border-dark);border-left:4px solid var(--crimson);border-radius:var(--radius-md);padding:1.3rem;}
.kon-stat-card.accent-emerald{border-left-color:var(--success);}
.kon-stat-card.accent-sky{border-left-color:var(--sky-blue);}
.kon-stat-value{font-size:2rem;font-weight:900;color:#fff;line-height:1;}
.kon-stat-label{color:var(--text-muted);font-size:.85rem;margin-top:.3rem;}
</style>

<?php if (isset($_GET['msg'])):
    $msgs = ['saved'=>['success','Konsentrasi berhasil disimpan!'],'deleted'=>['success','Konsentrasi berhasil dihapus!']];
    if (isset($msgs[$_GET['msg']])): ?>
<script>Swal.fire({icon:'<?=$msgs[$_GET['msg']][0]?>',title:'Berhasil!',text:'<?=$msgs[$_GET['msg']][1]?>',timer:2500,showConfirmButton:false});</script>
<?php endif; endif; ?>
<?php if (isset($error)): ?>
<script>Swal.fire({icon:'error',title:'Validasi Gagal',text:'<?= addslashes($error) ?>'});</script>
<?php endif; ?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
    <h2 class="section-title"><i class="fa-solid fa-layer-group"></i> <?= $data['id'] ? 'Edit' : 'Kelola' ?> Konsentrasi</h2>
    <?php if (!$data['id']): ?>
    <a href="?action=add" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Tambah Konsentrasi</a>
    <?php else: ?>
    <a href="kelola_konsentrasi.php" class="btn btn-secondary"><i class="fa-solid fa-xmark"></i> Batal</a>
    <?php endif; ?>
</div>

<div class="kon-stats-grid">
    <div class="kon-stat-card"><div class="kon-stat-value"><?= $total_all ?></div><div class="kon-stat-label">Total Konsentrasi</div></div>
    <div class="kon-stat-card accent-emerald"><div class="kon-stat-value"><?= $total_aktif ?></div><div class="kon-stat-label">Konsentrasi Aktif</div></div>
    <div class="kon-stat-card accent-sky"><div class="kon-stat-value"><?= $total_mk ?></div><div class="kon-stat-label">MK Terhubung</div></div>
</div>

<?php if (isset($_GET['action']) && $_GET['action'] === 'add' || $data['id']): ?>
<div class="panel">
    <div class="panel-header"><h3 class="panel-title"><i class="fa-solid fa-pen-to-square"></i> <?= $data['id'] ? 'Edit' : 'Tambah' ?> Konsentrasi</h3></div>
    <div class="panel-body">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="id" value="<?= $data['id'] ?>">
            <div style="display:grid;grid-template-columns:1fr 2fr;gap:1rem;">
                <div class="form-group"><label>Kode <span class="required">*</span></label>
                    <input type="text" name="kode" id="f_kode" class="form-control" value="<?= htmlspecialchars($data['kode']) ?>" required placeholder="LING" style="text-transform:uppercase;font-family:monospace;font-weight:800;"></div>
                <div class="form-group"><label>Nama Konsentrasi <span class="required">*</span></label>
                    <input type="text" name="nama" class="form-control" value="<?= htmlspecialchars($data['nama']) ?>" required placeholder="Contoh: Linguistics"></div>
            </div>
            <div class="form-group"><label>Deskripsi</label>
                <textarea name="deskripsi" rows="3" class="form-control" placeholder="Fokus kajian konsentrasi ini..."><?= htmlspecialchars($data['deskripsi']) ?></textarea></div>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;">
                <div class="form-group"><label>Ikon (Font Awesome)</label>
                    <div style="display:flex;gap:.8rem;align-items:center;">
                        <span class="kon-icon-prev"><i id="ikonPrevI" class="fa-solid <?= htmlspecialchars($data['ikon']) ?>"></i></span>
                        <input type="text" name="ikon" id="f_ikon" class="form-control" value="<?= htmlspecialchars($data['ikon']) ?>" list="ikonListKon" placeholder="fa-language">
                    </div>
                    <datalist id="ikonListKon">
                        <option value="fa-language"><option value="fa-book"><option value="fa-globe"><option value="fa-chalkboard-teacher">
                        <option value="fa-pen-fancy"><option value="fa-microphone"><option value="fa-brain"><option value="fa-masks-theater">
                    </datalist></div>
                <div class="form-group"><label>Warna Tema</label>
                    <div style="display:flex;gap:.8rem;align-items:center;">
                        <span class="kon-color-prev" id="warnaPrev" style="background:<?= htmlspecialchars($data['warna'] ?: '#10B981') ?>;"></span>
                        <input type="color" name="warna" id="f_warna" value="<?= htmlspecialchars($data['warna'] ?: '#10B981') ?>" style="width:60px;height:42px;border:1px solid var(--border-dark);border-radius:var(--radius-md);background:var(--bg-darker);cursor:pointer;">
                    </div></div>
                <div class="form-group"><label>Urutan</label>
                    <input type="number" name="urutan" class="form-control" value="<?= (int)$data['urutan'] ?>"></div>
            </div>
            <div class="form-group" style="max-width:220px;"><label>Status</label>
                <select name="status" class="form-control"><option value="aktif" <?= $data['status']==='aktif'?'selected':'' ?>>Aktif</option><option value="nonaktif" <?= $data['status']==='nonaktif'?'selected':'' ?>>Nonaktif</option></select></div>
            <div style="display:flex;gap:1rem;margin-top:1.5rem;">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Simpan Konsentrasi</button>
                <a href="kelola_konsentrasi.php" class="btn btn-secondary"><i class="fa-solid fa-xmark"></i> Batal</a>
            </div>
        </form>
    </div>
</div>
<script>
document.getElementById('f_ikon').addEventListener('input', function(){
    var v = this.value.trim() || 'fa-graduation-cap';
    document.getElementById('ikonPrevI').className = (v.indexOf('fa-solid') === 0 ? v : 'fa-solid ' + v);
});
document.getElementById('f_warna').addEventListener('input', function(){
    document.getElementById('warnaPrev').style.background = this.value;
});
document.getElementById('f_kode').addEventListener('input', function(){ this.value = this.value.toUpperCase(); });
</script>
<?php else: ?>
<div class="panel" style="margin-bottom:1.5rem;">
    <div class="panel-body">
        <form method="GET" style="display:grid;grid-template-columns:2fr 1fr auto;gap:1rem;align-items:end;">
            <div class="form-group" style="margin:0;"><label>Cari</label><input type="text" name="q" class="form-control" value="<?= htmlspecialchars($search) ?>" placeholder="Kode atau nama..."></div>
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
                <thead><tr><th style="width:90px;">Kode</th><th>Konsentrasi</th><th style="width:60px;">Ikon</th><th style="width:130px;">MK Terhubung</th><th style="width:90px;">Status</th><th style="width:120px;">Aksi</th></tr></thead>
                <tbody>
                <?php if (empty($items)): ?>
                    <tr><td colspan="6" style="text-align:center;padding:3rem;color:var(--text-muted);"><i class="fa-solid fa-layer-group" style="font-size:3rem;opacity:.2;display:block;margin-bottom:1rem;"></i>Belum ada konsentrasi.</td></tr>
                <?php else: foreach ($items as $item): $token = hash_hmac('sha256', $item['id'], $_SESSION['csrf_token']); ?>
                    <tr>
                        <td><span class="kon-code" style="background:<?= htmlspecialchars($item['warna']) ?>;"><?= htmlspecialchars($item['kode']) ?></span></td>
                        <td><strong><?= htmlspecialchars($item['nama']) ?></strong><?php if ($item['deskripsi']): ?><br><small style="color:var(--text-muted);"><?= htmlspecialchars(substr($item['deskripsi'],0,80)) ?><?= strlen($item['deskripsi'])>80?'...':'' ?></small><?php endif; ?></td>
                        <td><i class="fa-solid <?= htmlspecialchars($item['ikon']) ?>" style="color:<?= htmlspecialchars($item['warna']) ?>;font-size:1.2rem;"></i></td>
                        <td><span class="kon-mk-chip"><i class="fa-solid fa-book"></i> <?= $mk_counts[$item['id']] ?? 0 ?> MK</span></td>
                        <td><span class="kon-mk-chip" style="<?= $item['status']==='aktif' ? 'color:var(--success);border-color:rgba(46,139,87,.4);' : '' ?>"><?= $item['status'] ?></span></td>
                        <td><div class="table-actions">
                            <a href="?edit=<?= $item['id'] ?>&token=<?= $token ?>" class="table-action-btn edit" title="Edit"><i class="fa-solid fa-pen"></i></a>
                            <a href="?delete=<?= $item['id'] ?>&token=<?= $token ?>" class="table-action-btn delete" title="Hapus" onclick="return confirm('Hapus konsentrasi ini? MK terhubung akan menjadi tanpa konsentrasi.')"><i class="fa-solid fa-trash"></i></a>
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