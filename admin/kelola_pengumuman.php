<?php
require_once '../includes/config.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';
require_login();

$page_title = 'Kelola Pengumuman';
$current_page = 'pengumuman';
$table = 'pengumuman';

// ===== QUICK TOGGLE (pin / status) =====
if (isset($_GET['toggle']) && isset($_GET['token'])) {
    $id = (int)$_GET['toggle'];
    if (hash_equals(hash_hmac('sha256', $id, $_SESSION['csrf_token']), $_GET['token'])) {
        $col = ($_GET['toggle'] && isset($_GET['field']) && $_GET['field'] === 'pin') ? 'is_pinned' : 'status';
        if ($col === 'is_pinned') {
            $pdo->prepare("UPDATE $table SET is_pinned = 1 - is_pinned WHERE id = ?")->execute([$id]);
            $detail = "Mengubah pin pengumuman ID $id";
        } else {
            $pdo->prepare("UPDATE $table SET status = IF(status='aktif','nonaktif','aktif') WHERE id = ?")->execute([$id]);
            $detail = "Mengubah status pengumuman ID $id";
        }
        $pdo->prepare("INSERT INTO admin_logs (user_id, user_name, action, detail, ip) VALUES (?, ?, ?, ?, ?)")
            ->execute([$_SESSION['admin_id'], $_SESSION['admin_name'], 'TOGGLE_PENGUMUMAN', $detail, $_SERVER['REMOTE_ADDR']]);
        header("Location: kelola_pengumuman.php?msg=toggled"); exit;
    }
}

// ===== DELETE =====
if (isset($_GET['delete']) && isset($_GET['token'])) {
    $id = (int)$_GET['delete'];
    if (hash_equals(hash_hmac('sha256', $id, $_SESSION['csrf_token']), $_GET['token'])) {
        $pdo->prepare("DELETE FROM $table WHERE id = ?")->execute([$id]);
        $pdo->prepare("INSERT INTO admin_logs (user_id, user_name, action, detail, ip) VALUES (?, ?, ?, ?, ?)")
            ->execute([$_SESSION['admin_id'], $_SESSION['admin_name'], 'DELETE_PENGUMUMAN', "Menghapus pengumuman ID $id", $_SERVER['REMOTE_ADDR']]);
        header("Location: kelola_pengumuman.php?msg=deleted"); exit;
    }
}

// ===== ADD / EDIT =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $id = (int)($_POST['id'] ?? 0);
    $judul = sanitize($_POST['judul'] ?? '');
    $konten = sanitize($_POST['konten'] ?? '');
    $prioritas = in_array($_POST['prioritas'] ?? '', ['rendah','normal','tinggi','penting']) ? $_POST['prioritas'] : 'normal';
    $status = ($_POST['status'] ?? 'aktif') === 'nonaktif' ? 'nonaktif' : 'aktif';
    $target = in_array($_POST['target'] ?? '', ['semua','mahasiswa','dosen','staff']) ? $_POST['target'] : 'semua';
    $tgl_mulai = !empty($_POST['tanggal_mulai']) ? sanitize($_POST['tanggal_mulai']) : null;
    $tgl_akhir = !empty($_POST['tanggal_berakhir']) ? sanitize($_POST['tanggal_berakhir']) : null;
    $link = sanitize($_POST['link'] ?? '');
    $is_pinned = isset($_POST['is_pinned']) ? 1 : 0;

    if (strlen($judul) < 5) $error = "Judul minimal 5 karakter.";
    elseif (strlen($konten) < 10) $error = "Konten minimal 10 karakter.";
    elseif ($tgl_mulai && $tgl_akhir && $tgl_akhir < $tgl_mulai) $error = "Tanggal berakhir tidak boleh sebelum tanggal mulai.";
    else {
        if ($id > 0) {
            $pdo->prepare("UPDATE $table SET judul=?, konten=?, prioritas=?, status=?, tanggal_mulai=?, tanggal_berakhir=?, link=?, target=?, is_pinned=? WHERE id=?")
                ->execute([$judul,$konten,$prioritas,$status,$tgl_mulai,$tgl_akhir,$link ?: null,$target,$is_pinned,$id]);
            $action = 'UPDATE_PENGUMUMAN'; $detail = "Memperbarui pengumuman: $judul";
        } else {
            $pdo->prepare("INSERT INTO $table (judul, konten, prioritas, status, tanggal_mulai, tanggal_berakhir, link, target, is_pinned) VALUES (?,?,?,?,?,?,?,?,?)")
                ->execute([$judul,$konten,$prioritas,$status,$tgl_mulai,$tgl_akhir,$link ?: null,$target,$is_pinned]);
            $action = 'ADD_PENGUMUMAN'; $detail = "Menambah pengumuman: $judul";
        }
        $pdo->prepare("INSERT INTO admin_logs (user_id, user_name, action, detail, ip) VALUES (?, ?, ?, ?, ?)")
            ->execute([$_SESSION['admin_id'], $_SESSION['admin_name'], $action, $detail, $_SERVER['REMOTE_ADDR']]);
        header("Location: kelola_pengumuman.php?msg=saved"); exit;
    }
}

// ===== FETCH + FILTER =====
$search = trim($_GET['q'] ?? '');
$fstat = $_GET['status'] ?? 'all';
$fprio = $_GET['prioritas'] ?? 'all';
$sql = "SELECT * FROM $table WHERE 1=1"; $params = [];
if ($search !== '') { $sql .= " AND (judul LIKE ? OR konten LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($fstat !== 'all') { $sql .= " AND status = ?"; $params[] = $fstat; }
if ($fprio !== 'all') { $sql .= " AND prioritas = ?"; $params[] = $fprio; }
$sql .= " ORDER BY is_pinned DESC, prioritas DESC, created_at DESC";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$items = $stmt->fetchAll();

$total_aktif = (int)$pdo->query("SELECT COUNT(*) FROM $table WHERE status='aktif'")->fetchColumn();
$total_pin = (int)$pdo->query("SELECT COUNT(*) FROM $table WHERE is_pinned=1")->fetchColumn();
$total_penting = (int)$pdo->query("SELECT COUNT(*) FROM $table WHERE prioritas IN ('penting','tinggi')")->fetchColumn();

$data = ['id'=>0,'judul'=>'','konten'=>'','prioritas'=>'normal','status'=>'aktif','tanggal_mulai'=>null,'tanggal_berakhir'=>null,'link'=>'','target'=>'semua','is_pinned'=>0];
if (isset($_GET['edit']) && isset($_GET['token']) && hash_equals(hash_hmac('sha256', $_GET['edit'], $_SESSION['csrf_token']), $_GET['token'])) {
    $stmt = $pdo->prepare("SELECT * FROM $table WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $f = $stmt->fetch();
    if ($f) $data = array_merge($data, array_filter($f, fn($v) => $v !== null));
}
$today = date('Y-m-d');
ob_start();
?>

<style>
.prio-badge{display:inline-block;padding:.2rem .6rem;border-radius:50px;font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.5px;}
.prio-penting{background:rgba(138,21,56,.2);color:#ff6b8a;}
.prio-tinggi{background:rgba(243,156,18,.15);color:var(--warning);}
.prio-normal{background:rgba(135,206,235,.15);color:var(--sky-blue);}
.prio-rendah{background:rgba(255,255,255,.08);color:var(--text-muted);}
.pin-star{color:#f1c40f;margin-left:.4rem;}
.exp-badge{display:inline-block;margin-left:.4rem;padding:.15rem .5rem;border-radius:50px;font-size:.68rem;font-weight:700;background:rgba(231,76,60,.15);color:#e74c3c;}
.peng-stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1.2rem;margin-bottom:2rem;}
.peng-stat-card{background:var(--panel-bg);border:1px solid var(--border-dark);border-left:4px solid var(--crimson);border-radius:var(--radius-md);padding:1.3rem;}
.peng-stat-card.accent-sky{border-left-color:var(--sky-blue);}
.peng-stat-card.accent-warning{border-left-color:var(--warning);}
.peng-stat-value{font-size:2rem;font-weight:900;color:#fff;line-height:1;}
.peng-stat-label{color:var(--text-muted);font-size:.85rem;margin-top:.3rem;}
</style>

<?php if (isset($_GET['msg'])):
    $msgs = ['saved'=>['success','Pengumuman berhasil disimpan!'],'deleted'=>['success','Pengumuman berhasil dihapus!'],'toggled'=>['success','Status/pin diubah!']];
    if (isset($msgs[$_GET['msg']])): ?>
<script>Swal.fire({icon:'<?=$msgs[$_GET['msg']][0]?>',title:'Berhasil!',text:'<?=$msgs[$_GET['msg']][1]?>',timer:2500,showConfirmButton:false});</script>
<?php endif; endif; ?>
<?php if (isset($error)): ?>
<script>Swal.fire({icon:'error',title:'Validasi Gagal',text:'<?= addslashes($error) ?>'});</script>
<?php endif; ?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
    <h2 class="section-title"><i class="fa-solid fa-bullhorn"></i> <?= $data['id'] ? 'Edit' : 'Kelola' ?> Pengumuman</h2>
    <?php if (!$data['id']): ?>
    <a href="?action=add" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Buat Pengumuman</a>
    <?php else: ?>
    <a href="kelola_pengumuman.php" class="btn btn-secondary"><i class="fa-solid fa-xmark"></i> Batal</a>
    <?php endif; ?>
</div>

<div class="peng-stats-grid">
    <div class="peng-stat-card"><div class="peng-stat-value"><?= $total_aktif ?></div><div class="peng-stat-label">Pengumuman Aktif</div></div>
    <div class="peng-stat-card accent-sky"><div class="peng-stat-value"><?= $total_pin ?></div><div class="peng-stat-label">Di-Pin (Utama)</div></div>
    <div class="peng-stat-card accent-warning"><div class="peng-stat-value"><?= $total_penting ?></div><div class="peng-stat-label">Prioritas Tinggi/Penting</div></div>
</div>

<?php if (isset($_GET['action']) && $_GET['action'] === 'add' || $data['id']): ?>
<div class="panel">
    <div class="panel-header"><h3 class="panel-title"><i class="fa-solid fa-pen-to-square"></i> <?= $data['id'] ? 'Edit' : 'Buat' ?> Pengumuman</h3></div>
    <div class="panel-body">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="id" value="<?= $data['id'] ?>">
            <div class="form-group">
                <label>Judul <span class="required">*</span></label>
                <input type="text" name="judul" class="form-control" value="<?= htmlspecialchars($data['judul']) ?>" required placeholder="Contoh: Pendaftaran MABA 2026/2027 Dibuka">
            </div>
            <div class="form-group">
                <label>Konten <span class="required">*</span></label>
                <textarea name="konten" rows="5" class="form-control" required placeholder="Isi pengumuman lengkap..."><?= htmlspecialchars($data['konten']) ?></textarea>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;">
                <div class="form-group"><label>Prioritas</label>
                    <select name="prioritas" class="form-control">
                        <?php foreach (['rendah','normal','tinggi','penting'] as $p): ?><option value="<?= $p ?>" <?= $data['prioritas']===$p?'selected':'' ?>><?= ucfirst($p) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label>Target</label>
                    <select name="target" class="form-control">
                        <?php foreach (['semua','mahasiswa','dosen','staff'] as $t): ?><option value="<?= $t ?>" <?= $data['target']===$t?'selected':'' ?>><?= ucfirst($t) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label>Status</label>
                    <select name="status" class="form-control"><option value="aktif" <?= $data['status']==='aktif'?'selected':'' ?>>Aktif</option><option value="nonaktif" <?= $data['status']==='nonaktif'?'selected':'' ?>>Nonaktif</option></select>
                </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;">
                <div class="form-group"><label>Tanggal Mulai</label><input type="date" name="tanggal_mulai" class="form-control" value="<?= htmlspecialchars($data['tanggal_mulai'] ?? '') ?>"></div>
                <div class="form-group"><label>Tanggal Berakhir</label><input type="date" name="tanggal_berakhir" class="form-control" value="<?= htmlspecialchars($data['tanggal_berakhir'] ?? '') ?>"></div>
                <div class="form-group"><label>Link (opsional)</label><input type="url" name="link" class="form-control" value="<?= htmlspecialchars($data['link']) ?>" placeholder="https://..."></div>
            </div>
            <div class="form-group">
                <label style="display:flex;align-items:center;gap:.6rem;cursor:pointer;">
                    <input type="checkbox" name="is_pinned" value="1" <?= $data['is_pinned']?'checked':'' ?>>
                    <i class="fa-solid fa-thumbtack" style="color:#f1c40f;"></i> Pin pengumuman ini (tampil paling atas)
                </label>
            </div>
            <div style="display:flex;gap:1rem;margin-top:1.5rem;">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Simpan Pengumuman</button>
                <a href="kelola_pengumuman.php" class="btn btn-secondary"><i class="fa-solid fa-xmark"></i> Batal</a>
            </div>
        </form>
    </div>
</div>
<?php else: ?>
<div class="panel" style="margin-bottom:1.5rem;">
    <div class="panel-body">
        <form method="GET" style="display:grid;grid-template-columns:2fr 1fr 1fr auto;gap:1rem;align-items:end;">
            <div class="form-group" style="margin:0;"><label>Cari</label><input type="text" name="q" class="form-control" value="<?= htmlspecialchars($search) ?>" placeholder="Judul pengumuman..."></div>
            <div class="form-group" style="margin:0;"><label>Prioritas</label>
                <select name="prioritas" class="form-control"><option value="all">Semua</option><?php foreach (['rendah','normal','tinggi','penting'] as $p): ?><option value="<?= $p ?>" <?= $fprio===$p?'selected':'' ?>><?= ucfirst($p) ?></option><?php endforeach; ?></select>
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
                <thead><tr><th>Judul</th><th style="width:110px;">Prioritas</th><th style="width:110px;">Target</th><th style="width:190px;">Periode</th><th style="width:110px;">Status</th><th style="width:160px;">Aksi</th></tr></thead>
                <tbody>
                <?php if (empty($items)): ?>
                    <tr><td colspan="6" style="text-align:center;padding:3rem;color:var(--text-muted);"><i class="fa-solid fa-bullhorn" style="font-size:3rem;opacity:.2;display:block;margin-bottom:1rem;"></i>Belum ada pengumuman.</td></tr>
                <?php else: foreach ($items as $item): $token = hash_hmac('sha256', $item['id'], $_SESSION['csrf_token']);
                    $expired = !empty($item['tanggal_berakhir']) && $item['tanggal_berakhir'] < $today;
                ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($item['judul']) ?></strong>
                            <?php if ($item['is_pinned']): ?><i class="fa-solid fa-thumbtack pin-star" title="Di-pin"></i><?php endif; ?>
                            <br><small style="color:var(--text-muted);"><?= htmlspecialchars(substr($item['konten'],0,80)) ?><?= strlen($item['konten'])>80?'...':'' ?></small>
                        </td>
                        <td><span class="prio-badge prio-<?= $item['prioritas'] ?>"><?= $item['prioritas'] ?></span></td>
                        <td><span style="color:var(--text-muted);font-size:.85rem;"><?= ucfirst(htmlspecialchars($item['target'])) ?></span></td>
                        <td style="font-size:.85rem;color:var(--text-muted);">
                            <?= $item['tanggal_mulai'] ? date('d M Y', strtotime($item['tanggal_mulai'])) : '—' ?> → <?= $item['tanggal_berakhir'] ? date('d M Y', strtotime($item['tanggal_berakhir'])) : '—' ?>
                            <?php if ($expired): ?><span class="exp-badge">Berakhir</span><?php endif; ?>
                        </td>
                        <td><span class="prio-badge" style="<?= $item['status']==='aktif' ? 'background:rgba(46,139,87,.15);color:var(--success);' : 'background:rgba(255,255,255,.08);color:var(--text-muted);' ?>"><?= $item['status'] ?></span></td>
                        <td><div class="table-actions">
                            <a href="?toggle=<?= $item['id'] ?>&field=pin&token=<?= $token ?>" class="table-action-btn" title="Pin/Unpin"><i class="fa-solid fa-thumbtack"></i></a>
                            <a href="?toggle=<?= $item['id'] ?>&token=<?= $token ?>" class="table-action-btn" title="Aktif/Nonaktif"><i class="fa-solid fa-power-off"></i></a>
                            <a href="?edit=<?= $item['id'] ?>&token=<?= $token ?>" class="table-action-btn edit" title="Edit"><i class="fa-solid fa-pen"></i></a>
                            <a href="?delete=<?= $item['id'] ?>&token=<?= $token ?>" class="table-action-btn delete" title="Hapus" onclick="return confirm('Hapus pengumuman ini?')"><i class="fa-solid fa-trash"></i></a>
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