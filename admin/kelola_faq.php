<?php
require_once '../includes/config.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';
require_login();

$page_title = 'Kelola FAQ';
$current_page = 'faq';
$table = 'faq';

// ===== DELETE =====
if (isset($_GET['delete']) && isset($_GET['token'])) {
    $id = (int)$_GET['delete'];
    if (hash_equals(hash_hmac('sha256', $id, $_SESSION['csrf_token']), $_GET['token'])) {
        $pdo->prepare("DELETE FROM $table WHERE id = ?")->execute([$id]);
        $pdo->prepare("INSERT INTO admin_logs (user_id, user_name, action, detail, ip) VALUES (?, ?, ?, ?, ?)")
            ->execute([$_SESSION['admin_id'], $_SESSION['admin_name'], 'DELETE_FAQ', "Menghapus FAQ ID $id", $_SERVER['REMOTE_ADDR']]);
        header("Location: kelola_faq.php?msg=deleted"); exit;
    }
}

// ===== ADD / EDIT =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $id = (int)($_POST['id'] ?? 0);
    $pertanyaan = sanitize($_POST['pertanyaan'] ?? '');
    $jawaban = sanitize($_POST['jawaban'] ?? '');
    $kategori = sanitize($_POST['kategori'] ?? 'Umum');
    $urutan = (int)($_POST['urutan'] ?? 0);
    $status = ($_POST['status'] ?? 'aktif') === 'nonaktif' ? 'nonaktif' : 'aktif';

    if (strlen($pertanyaan) < 5) $error = "Pertanyaan minimal 5 karakter.";
    elseif (strlen($jawaban) < 10) $error = "Jawaban minimal 10 karakter.";
    else {
        if ($id > 0) {
            $pdo->prepare("UPDATE $table SET pertanyaan=?, jawaban=?, kategori=?, urutan=?, status=? WHERE id=?")
                ->execute([$pertanyaan,$jawaban,$kategori,$urutan,$status,$id]);
            $action = 'UPDATE_FAQ'; $detail = "Memperbarui FAQ: $pertanyaan";
        } else {
            $pdo->prepare("INSERT INTO $table (pertanyaan, jawaban, kategori, urutan, status) VALUES (?,?,?,?,?)")
                ->execute([$pertanyaan,$jawaban,$kategori,$urutan,$status]);
            $action = 'ADD_FAQ'; $detail = "Menambah FAQ: $pertanyaan";
        }
        $pdo->prepare("INSERT INTO admin_logs (user_id, user_name, action, detail, ip) VALUES (?, ?, ?, ?, ?)")
            ->execute([$_SESSION['admin_id'], $_SESSION['admin_name'], $action, $detail, $_SERVER['REMOTE_ADDR']]);
        header("Location: kelola_faq.php?msg=saved"); exit;
    }
}

// ===== FETCH + FILTER =====
$search = trim($_GET['q'] ?? '');
$fkat = $_GET['kategori'] ?? 'all';
$fstat = $_GET['status'] ?? 'all';
$sql = "SELECT * FROM $table WHERE 1=1"; $params = [];
if ($search !== '') { $sql .= " AND (pertanyaan LIKE ? OR jawaban LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($fkat !== 'all') { $sql .= " AND kategori = ?"; $params[] = $fkat; }
if ($fstat !== 'all') { $sql .= " AND status = ?"; $params[] = $fstat; }
$sql .= " ORDER BY kategori ASC, urutan ASC, id ASC";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$items = $stmt->fetchAll();

$total_all = (int)$pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
$total_aktif = (int)$pdo->query("SELECT COUNT(*) FROM $table WHERE status='aktif'")->fetchColumn();
$total_kategori = (int)$pdo->query("SELECT COUNT(DISTINCT kategori) FROM $table")->fetchColumn();
$all_kategori = $pdo->query("SELECT DISTINCT kategori FROM $table ORDER BY kategori")->fetchAll(PDO::FETCH_COLUMN);

$data = ['id'=>0,'pertanyaan'=>'','jawaban'=>'','kategori'=>'Umum','urutan'=>0,'status'=>'aktif'];
if (isset($_GET['edit']) && isset($_GET['token']) && hash_equals(hash_hmac('sha256', $_GET['edit'], $_SESSION['csrf_token']), $_GET['token'])) {
    $stmt = $pdo->prepare("SELECT * FROM $table WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $f = $stmt->fetch();
    if ($f) $data = $f;
}
ob_start();
?>

<style>
.faq-q{font-weight:700;color:#fff;display:flex;gap:.6rem;align-items:flex-start;}
.faq-q i{color:var(--crimson);margin-top:4px;}
.faq-a{color:var(--text-muted);font-size:.85rem;margin-top:.4rem;padding-left:1.6rem;line-height:1.6;}
.faq-cat{display:inline-block;padding:.2rem .6rem;border-radius:50px;font-size:.72rem;font-weight:700;background:rgba(135,206,235,.15);color:var(--sky-blue);text-transform:uppercase;letter-spacing:.5px;}
.faq-stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1.2rem;margin-bottom:2rem;}
.faq-stat-card{background:var(--panel-bg);border:1px solid var(--border-dark);border-left:4px solid var(--crimson);border-radius:var(--radius-md);padding:1.3rem;}
.faq-stat-card.accent-sky{border-left-color:var(--sky-blue);}
.faq-stat-card.accent-emerald{border-left-color:var(--success);}
.faq-stat-value{font-size:2rem;font-weight:900;color:#fff;line-height:1;}
.faq-stat-label{color:var(--text-muted);font-size:.85rem;margin-top:.3rem;}
</style>

<?php if (isset($_GET['msg'])):
    $msgs = ['saved'=>['success','FAQ berhasil disimpan!'],'deleted'=>['success','FAQ berhasil dihapus!']];
    if (isset($msgs[$_GET['msg']])): ?>
<script>Swal.fire({icon:'<?=$msgs[$_GET['msg']][0]?>',title:'Berhasil!',text:'<?=$msgs[$_GET['msg']][1]?>',timer:2500,showConfirmButton:false});</script>
<?php endif; endif; ?>
<?php if (isset($error)): ?>
<script>Swal.fire({icon:'error',title:'Validasi Gagal',text:'<?= addslashes($error) ?>'});</script>
<?php endif; ?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
    <h2 class="section-title"><i class="fa-solid fa-circle-question"></i> <?= $data['id'] ? 'Edit' : 'Kelola' ?> FAQ</h2>
    <?php if (!$data['id']): ?>
    <a href="?action=add" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Tambah FAQ</a>
    <?php else: ?>
    <a href="kelola_faq.php" class="btn btn-secondary"><i class="fa-solid fa-xmark"></i> Batal</a>
    <?php endif; ?>
</div>

<div class="faq-stats-grid">
    <div class="faq-stat-card"><div class="faq-stat-value"><?= $total_all ?></div><div class="faq-stat-label">Total FAQ</div></div>
    <div class="faq-stat-card accent-emerald"><div class="faq-stat-value"><?= $total_aktif ?></div><div class="faq-stat-label">FAQ Aktif</div></div>
    <div class="faq-stat-card accent-sky"><div class="faq-stat-value"><?= $total_kategori ?></div><div class="faq-stat-label">Kategori</div></div>
</div>

<?php if (isset($_GET['action']) && $_GET['action'] === 'add' || $data['id']): ?>
<div class="panel">
    <div class="panel-header"><h3 class="panel-title"><i class="fa-solid fa-pen-to-square"></i> <?= $data['id'] ? 'Edit' : 'Tambah' ?> FAQ</h3></div>
    <div class="panel-body">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="id" value="<?= $data['id'] ?>">
            <div class="form-group">
                <label>Pertanyaan <span class="required">*</span></label>
                <input type="text" name="pertanyaan" class="form-control" value="<?= htmlspecialchars($data['pertanyaan']) ?>" required placeholder="Contoh: Bagaimana cara mendaftar?">
            </div>
            <div class="form-group">
                <label>Jawaban <span class="required">*</span></label>
                <textarea name="jawaban" rows="5" class="form-control" required placeholder="Tulis jawaban yang jelas dan membantu..."><?= htmlspecialchars($data['jawaban']) ?></textarea>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;">
                <div class="form-group">
                    <label>Kategori</label>
                    <input type="text" name="kategori" list="faqKatList" class="form-control" value="<?= htmlspecialchars($data['kategori']) ?>" placeholder="Umum">
                    <datalist id="faqKatList"><?php foreach ($all_kategori as $k): ?><option value="<?= htmlspecialchars($k) ?>"><?php endforeach; ?></datalist>
                </div>
                <div class="form-group">
                    <label>Urutan</label>
                    <input type="number" name="urutan" class="form-control" value="<?= $data['urutan'] ?>">
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" class="form-control"><option value="aktif" <?= $data['status']==='aktif'?'selected':'' ?>>Aktif</option><option value="nonaktif" <?= $data['status']==='nonaktif'?'selected':'' ?>>Nonaktif</option></select>
                </div>
            </div>
            <div style="display:flex;gap:1rem;margin-top:1.5rem;">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Simpan FAQ</button>
                <a href="kelola_faq.php" class="btn btn-secondary"><i class="fa-solid fa-xmark"></i> Batal</a>
            </div>
        </form>
    </div>
</div>
<?php else: ?>
<div class="panel" style="margin-bottom:1.5rem;">
    <div class="panel-body">
        <form method="GET" style="display:grid;grid-template-columns:2fr 1fr 1fr auto;gap:1rem;align-items:end;">
            <div class="form-group" style="margin:0;"><label>Cari</label><input type="text" name="q" class="form-control" value="<?= htmlspecialchars($search) ?>" placeholder="Pertanyaan atau jawaban..."></div>
            <div class="form-group" style="margin:0;"><label>Kategori</label>
                <select name="kategori" class="form-control"><option value="all">Semua</option><?php foreach ($all_kategori as $k): ?><option value="<?= htmlspecialchars($k) ?>" <?= $fkat===$k?'selected':'' ?>><?= htmlspecialchars($k) ?></option><?php endforeach; ?></select>
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
                <thead><tr><th>Pertanyaan & Jawaban</th><th style="width:140px;">Kategori</th><th style="width:80px;">Urutan</th><th style="width:90px;">Status</th><th style="width:120px;">Aksi</th></tr></thead>
                <tbody>
                <?php if (empty($items)): ?>
                    <tr><td colspan="5" style="text-align:center;padding:3rem;color:var(--text-muted);"><i class="fa-solid fa-circle-question" style="font-size:3rem;opacity:.2;display:block;margin-bottom:1rem;"></i>Belum ada FAQ.</td></tr>
                <?php else: foreach ($items as $item): $token = hash_hmac('sha256', $item['id'], $_SESSION['csrf_token']); ?>
                    <tr>
                        <td>
                            <div class="faq-q"><i class="fa-solid fa-circle-question"></i> <?= htmlspecialchars($item['pertanyaan']) ?></div>
                            <div class="faq-a"><?= htmlspecialchars(substr($item['jawaban'],0,120)) ?><?= strlen($item['jawaban'])>120?'...':'' ?></div>
                        </td>
                        <td><span class="faq-cat"><?= htmlspecialchars($item['kategori']) ?></span></td>
                        <td><strong><?= $item['urutan'] ?></strong></td>
                        <td><span class="faq-cat" style="<?= $item['status']!=='aktif' ? 'background:rgba(255,255,255,.08);color:var(--text-muted);' : 'background:rgba(46,139,87,.15);color:var(--success);' ?>"><?= $item['status'] ?></span></td>
                        <td><div class="table-actions">
                            <a href="?edit=<?= $item['id'] ?>&token=<?= $token ?>" class="table-action-btn edit" title="Edit"><i class="fa-solid fa-pen"></i></a>
                            <a href="?delete=<?= $item['id'] ?>&token=<?= $token ?>" class="table-action-btn delete" title="Hapus" onclick="return confirm('Hapus FAQ ini?')"><i class="fa-solid fa-trash"></i></a>
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