<?php
require_once '../includes/config.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';
require_login();

$page_title = 'Kelola Kurikulum & Mata Kuliah';
$current_page = 'kurikulum';
$table = 'mata_kuliah';

// ===== HANDLE DELETE =====
if (isset($_GET['delete']) && isset($_GET['token']) && hash_equals(hash_hmac('sha256', (int)$_GET['delete'], $_SESSION['csrf_token']), $_GET['token'])) {
    $id = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM $table WHERE id = ?")->execute([$id]);
    $pdo->prepare("INSERT INTO admin_logs (user_id, user_name, action, detail, ip) VALUES (?, ?, 'DELETE_MK', 'Menghapus MK ID $id', ?)")
        ->execute([$_SESSION['admin_id'], $_SESSION['admin_name'], $_SERVER['REMOTE_ADDR']]);
    header("Location: kelola_kurikulum.php?msg=deleted");
    exit;
}

// ===== HANDLE DUPLICATE =====
if (isset($_GET['duplicate']) && isset($_GET['token']) && hash_equals(hash_hmac('sha256', (int)$_GET['duplicate'], $_SESSION['csrf_token']), $_GET['token'])) {
    $id = (int)$_GET['duplicate'];
    $stmt = $pdo->prepare("SELECT * FROM $table WHERE id = ?");
    $stmt->execute([$id]);
    $orig = $stmt->fetch();
    if ($orig) {
        $pdo->prepare("INSERT INTO $table (kode_mk, nama_mk, semester, sks, kategori, deskripsi, urutan) VALUES (?, ?, ?, ?, ?, ?, ?)")
            ->execute([
                $orig['kode_mk'] . '-COPY',
                $orig['nama_mk'] . ' (Copy)',
                $orig['semester'],
                $orig['sks'],
                $orig['kategori'],
                $orig['deskripsi'],
                $orig['urutan']
            ]);
    }
    header("Location: kelola_kurikulum.php?msg=duplicated");
    exit;
}

// ===== HANDLE ADD/EDIT =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $kode_mk = strtoupper(sanitize($_POST['kode_mk']));
    $nama_mk = sanitize($_POST['nama_mk']);
    $semester = (int)$_POST['semester'];
    $sks = (int)$_POST['sks'];
    $kategori = sanitize($_POST['kategori']);
    $deskripsi = sanitize($_POST['deskripsi']);
    $urutan = (int)$_POST['urutan'];

    // Validasi
    if (strlen($kode_mk) < 3) {
        $error = "Kode MK minimal 3 karakter.";
    } elseif (empty($nama_mk)) {
        $error = "Nama MK wajib diisi.";
    } elseif ($semester < 1 || $semester > 8) {
        $error = "Semester harus antara 1-8.";
    } elseif ($sks < 1 || $sks > 6) {
        $error = "SKS harus antara 1-6.";
    } else {
        $fields = "kode_mk=:kode_mk, nama_mk=:nama_mk, semester=:semester, sks=:sks, kategori=:kategori, deskripsi=:deskripsi, urutan=:urutan";
        $params = [':kode_mk'=>$kode_mk, ':nama_mk'=>$nama_mk, ':semester'=>$semester, ':sks'=>$sks, ':kategori'=>$kategori, ':deskripsi'=>$deskripsi, ':urutan'=>$urutan];
        
        if ($id > 0) {
            $pdo->prepare("UPDATE $table SET $fields WHERE id=:id")->execute(array_merge($params, [':id'=>$id]));
            $action = 'UPDATE_MK';
            $detail = "Memperbarui MK: $kode_mk - $nama_mk";
        } else {
            $pdo->prepare("INSERT INTO $table SET $fields")->execute($params);
            $action = 'ADD_MK';
            $detail = "Menambah MK baru: $kode_mk - $nama_mk";
        }
        
        $pdo->prepare("INSERT INTO admin_logs (user_id, user_name, action, detail, ip) VALUES (?, ?, ?, ?, ?)")
            ->execute([$_SESSION['admin_id'], $_SESSION['admin_name'], $action, $detail, $_SERVER['REMOTE_ADDR']]);
        
        header("Location: kelola_kurikulum.php?msg=saved");
        exit;
    }
}

// ===== FETCH DATA =====
$items = $pdo->query("SELECT * FROM $table ORDER BY semester ASC, urutan ASC")->fetchAll();

// Statistics
$total_mk = count($items);
$total_sks = array_sum(array_column($items, 'sks'));
$by_semester = [];
$sks_by_category = [];
foreach ($items as $m) {
    $sem = (int)$m['semester'];
    $by_semester[$sem][] = $m;
    $cat = $m['kategori'] ?? 'Lainnya';
    $sks_by_category[$cat] = ($sks_by_category[$cat] ?? 0) + (int)$m['sks'];
}

// Form data
$data = ['id'=>0, 'kode_mk'=>'', 'nama_mk'=>'', 'semester'=>1, 'sks'=>2, 'kategori'=>'Wajib', 'deskripsi'=>'', 'urutan'=>1];
if (isset($_GET['edit']) && isset($_GET['token']) && hash_equals(hash_hmac('sha256', (int)$_GET['edit'], $_SESSION['csrf_token']), $_GET['token'])) {
    $stmt = $pdo->prepare("SELECT * FROM $table WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $fetched = $stmt->fetch();
    if ($fetched) $data = $fetched;
}

ob_start();
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
/* ===== SELECT2 DARK THEME ===== */
.select2-container--default .select2-selection--single { background-color: var(--bg-darker); border: 1px solid var(--border-dark); border-radius: var(--radius-md); height: 42px; display: flex; align-items: center; }
.select2-container--default .select2-selection--single .select2-selection__rendered { color: var(--text-main); padding-left: 14px; }
.select2-container--default .select2-selection--single .select2-selection__arrow { height: 40px; }
.select2-dropdown { background-color: var(--panel-bg); border: 1px solid var(--border-dark); border-radius: var(--radius-md); }
.select2-search__field { background-color: var(--bg-darker) !important; color: var(--text-main) !important; border: 1px solid var(--border-dark) !important; }
.select2-results__option { color: var(--text-main); }
.select2-results__option--highlighted[aria-selected] { background-color: var(--crimson) !important; }

/* ===== KURIKULUM STATS ===== */
.kur-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 1.2rem;
    margin-bottom: 2rem;
}
.kur-stat-card {
    background: var(--panel-bg);
    border: 1px solid var(--border-dark);
    border-radius: var(--radius-md);
    padding: 1.5rem;
    position: relative;
    overflow: hidden;
    transition: all .3s ease;
    cursor: pointer;
    border-left: 4px solid var(--accent-color, var(--crimson));
}
.kur-stat-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-md);
    border-left-color: var(--sky-blue);
}
.kur-stat-card .stat-icon {
    width: 48px;
    height: 48px;
    border-radius: var(--radius-md);
    background: rgba(138,21,56,0.12);
    color: var(--crimson);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
    margin-bottom: 1rem;
}
.kur-stat-card.accent-sky .stat-icon { background: rgba(135,206,235,0.12); color: var(--sky-blue); }
.kur-stat-card.accent-emerald .stat-icon { background: rgba(46,139,87,0.12); color: var(--success); }
.kur-stat-card.accent-warning .stat-icon { background: rgba(243,156,18,0.12); color: var(--warning); }
.kur-stat-value { font-size: 2.2rem; font-weight: 900; color: #fff; line-height: 1; margin-bottom: .3rem; }
.kur-stat-label { color: var(--text-muted); font-size: .9rem; font-weight: 500; }

/* ===== SEMESTER OVERVIEW GRID ===== */
.sem-overview {
    display: grid;
    grid-template-columns: repeat(8, 1fr);
    gap: .5rem;
    margin-bottom: 2rem;
}
.sem-box {
    background: var(--bg-darker);
    border: 1px solid var(--border-dark);
    border-radius: var(--radius-md);
    padding: 1rem .5rem;
    text-align: center;
    transition: all .3s ease;
    cursor: pointer;
    position: relative;
}
.sem-box:hover {
    border-color: var(--sky-blue);
    transform: translateY(-2px);
}
.sem-box.has-data { border-color: var(--crimson); }
.sem-box .sem-num { font-size: 1.5rem; font-weight: 900; color: var(--crimson); }
.sem-box .sem-count { font-size: .75rem; color: var(--text-muted); margin-top: .3rem; }
.sem-box .sem-sks { 
    display: inline-block; margin-top: .4rem; 
    background: var(--crimson); color: #fff; 
    font-size: .7rem; padding: .2rem .5rem; border-radius: 50px; 
    font-weight: 700;
}
.sem-box.empty .sem-num { color: var(--text-muted); opacity: .4; }

/* ===== SKS BREAKDOWN ===== */
.sks-breakdown {
    background: var(--panel-bg);
    border: 1px solid var(--border-dark);
    border-radius: var(--radius-md);
    padding: 1.5rem;
    margin-bottom: 2rem;
}
.sks-breakdown-title {
    font-size: 1rem;
    font-weight: 700;
    color: #fff;
    margin-bottom: 1.2rem;
    display: flex;
    align-items: center;
    gap: .6rem;
}
.sks-breakdown-title i { color: var(--crimson); }
.breakdown-list { display: flex; flex-direction: column; gap: 1rem; }
.breakdown-item { display: flex; flex-direction: column; gap: .4rem; }
.breakdown-header { display: flex; justify-content: space-between; align-items: center; }
.breakdown-cat { font-weight: 600; color: var(--text-main); font-size: .9rem; }
.breakdown-sks { color: var(--sky-blue); font-weight: 700; font-size: .85rem; }
.breakdown-bar {
    height: 8px;
    background: var(--bg-darker);
    border-radius: 50px;
    overflow: hidden;
}
.breakdown-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--crimson), var(--sky-blue));
    border-radius: 50px;
    transition: width 1s ease;
    position: relative;
}

/* ===== FILTER BAR ===== */
.filter-bar {
    background: var(--panel-bg);
    border: 1px solid var(--border-dark);
    border-radius: var(--radius-md);
    padding: 1.2rem;
    margin-bottom: 1.5rem;
    display: grid;
    grid-template-columns: 2fr 1fr 1fr;
    gap: 1rem;
    align-items: end;
}
.filter-bar .form-group { margin: 0; }
.filter-bar label { font-size: .85rem; color: var(--text-muted); margin-bottom: .4rem; display: block; font-weight: 600; }

/* ===== ENHANCED TABLE ===== */
.mk-table thead th {
    background: var(--bg-darker);
    color: var(--sky-blue);
    font-size: .78rem;
    text-transform: uppercase;
    letter-spacing: .5px;
    padding: 12px 16px;
    font-weight: 700;
    border-bottom: 1px solid var(--border-dark);
}
.sem-group-header {
    background: linear-gradient(90deg, rgba(138,21,56,.15), transparent);
    border-left: 4px solid var(--crimson);
}
.sem-group-header td {
    padding: 12px 16px;
    font-weight: 800;
    color: #fff;
    font-size: .95rem;
}
.sem-group-header .sem-stats {
    float: right;
    font-size: .82rem;
    font-weight: 500;
    color: var(--text-muted);
}
.sks-badge {
    display: inline-block;
    background: linear-gradient(135deg, var(--crimson), var(--crimson-light));
    color: #fff;
    padding: .25rem .6rem;
    border-radius: 50px;
    font-weight: 800;
    font-size: .78rem;
    min-width: 50px;
    text-align: center;
}
.sks-badge.sks-2 { background: linear-gradient(135deg, #2E8B57, #3CB371); }
.sks-badge.sks-3 { background: linear-gradient(135deg, var(--crimson), var(--crimson-light)); }
.sks-badge.sks-4 { background: linear-gradient(135deg, #f39c12, #f1c40f); color: #000; }
.sks-badge.sks-6 { background: linear-gradient(135deg, #9b59b6, #8e44ad); }

.cat-badge {
    display: inline-block;
    padding: .2rem .6rem;
    border-radius: 50px;
    font-size: .72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .5px;
}
.cat-badge.wajib { background: rgba(138,21,56,0.15); color: var(--crimson); }
.cat-badge.pilihan { background: rgba(155,89,182,0.15); color: #9b59b6; }
.cat-badge.mkku { background: rgba(46,139,87,0.15); color: var(--success); }

.mk-code {
    font-family: 'Courier New', monospace;
    background: var(--bg-darker);
    padding: .2rem .5rem;
    border-radius: 4px;
    font-size: .8rem;
    color: var(--sky-blue);
    font-weight: 700;
}

/* ===== LIVE PREVIEW CARD ===== */
.live-preview-wrapper {
    background: var(--bg-darker);
    border: 2px dashed var(--border-light);
    border-radius: var(--radius-md);
    padding: 1.5rem;
    position: sticky;
    top: 20px;
}
.live-preview-title {
    font-size: .82rem;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: var(--text-muted);
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: .5rem;
}
.live-preview-title i { color: var(--sky-blue); }
.preview-card {
    background: var(--panel-bg);
    border: 1px solid var(--border-dark);
    border-radius: var(--radius-md);
    padding: 1.2rem;
    transition: all .3s ease;
    position: relative;
    overflow: hidden;
}
.preview-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0;
    width: 4px; height: 100%;
    background: linear-gradient(180deg, var(--crimson), var(--sky-blue));
}
.preview-card .p-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: .8rem;
}
.preview-card .p-code {
    font-family: monospace;
    background: rgba(135,206,235,0.15);
    color: var(--sky-blue);
    padding: .25rem .6rem;
    border-radius: 4px;
    font-size: .8rem;
    font-weight: 800;
}
.preview-card .p-sks {
    background: var(--crimson);
    color: #fff;
    padding: .25rem .7rem;
    border-radius: 50px;
    font-size: .75rem;
    font-weight: 800;
}
.preview-card h4 {
    color: #fff;
    font-size: 1.05rem;
    font-weight: 800;
    margin-bottom: .6rem;
    line-height: 1.4;
}
.preview-card p {
    color: var(--text-muted);
    font-size: .85rem;
    line-height: 1.5;
    margin-bottom: .8rem;
    min-height: 60px;
}
.preview-card .p-cat {
    display: inline-block;
    background: rgba(138,21,56,0.15);
    color: var(--crimson);
    padding: .2rem .6rem;
    border-radius: 50px;
    font-size: .72rem;
    font-weight: 700;
    text-transform: uppercase;
}
.preview-meta {
    display: flex;
    gap: .8rem;
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid var(--border-dark);
    font-size: .78rem;
    color: var(--text-muted);
}
.preview-meta span { display: flex; align-items: center; gap: .3rem; }
.preview-meta i { color: var(--sky-blue); }

/* ===== INPUT ICON ===== */
.input-icon-wrap { position: relative; }
.input-icon-wrap i {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
    font-size: .9rem;
    pointer-events: none;
}
.input-icon-wrap .form-control { padding-left: 42px; }

/* ===== RESPONSIVE ===== */
@media (max-width: 992px) {
    .sem-overview { grid-template-columns: repeat(4, 1fr); }
    .filter-bar { grid-template-columns: 1fr; }
}
@media (max-width: 576px) {
    .sem-overview { grid-template-columns: repeat(2, 1fr); }
}
</style>

<?php if (isset($_GET['msg'])): 
    $msgs = [
        'saved' => ['success', 'Mata kuliah berhasil disimpan!'],
        'deleted' => ['success', 'Mata kuliah berhasil dihapus!'],
        'duplicated' => ['success', 'Mata kuliah berhasil diduplikasi!']
    ];
    if (isset($msgs[$_GET['msg']])): ?>
    <script>Swal.fire({icon:'<?=$msgs[$_GET['msg']][0]?>', title:'Berhasil!', text:'<?=$msgs[$_GET['msg']][1]?>', timer:2500, showConfirmButton:false});</script>
<?php endif; endif; ?>

<?php if (isset($error)): ?>
<script>Swal.fire({icon:'error', title:'Validasi Gagal', text:'<?= addslashes($error) ?>'});</script>
<?php endif; ?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;">
    <h2 class="section-title"><i class="fa-solid fa-book-open"></i> <?= isset($_GET['edit']) ? 'Edit' : 'Kelola' ?> Kurikulum</h2>
    <div style="display:flex; gap:.8rem;">
        <?php if (isset($_GET['edit'])): ?>
        <a href="kelola_kurikulum.php" class="btn btn-secondary"><i class="fa-solid fa-xmark"></i> Batal</a>
        <?php else: ?>
        <a href="?action=add" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Tambah Mata Kuliah</a>
        <?php endif; ?>
    </div>
</div>

<!-- ===== STATS DASHBOARD ===== -->
<div class="kur-stats-grid">
    <div class="kur-stat-card">
        <div class="stat-icon"><i class="fa-solid fa-book"></i></div>
        <div class="kur-stat-value" data-counter="<?= $total_mk ?>">0</div>
        <div class="kur-stat-label">Total Mata Kuliah</div>
    </div>
    <div class="kur-stat-card accent-sky">
        <div class="stat-icon"><i class="fa-solid fa-graduation-cap"></i></div>
        <div class="kur-stat-value" data-counter="<?= $total_sks ?>">0</div>
        <div class="kur-stat-label">Total SKS</div>
    </div>
    <div class="kur-stat-card accent-emerald">
        <div class="stat-icon"><i class="fa-solid fa-check-circle"></i></div>
        <div class="kur-stat-value" data-counter="<?= $sks_by_category['Wajib'] ?? 0 ?>">0</div>
        <div class="kur-stat-label">SKS Wajib</div>
    </div>
    <div class="kur-stat-card accent-warning">
        <div class="stat-icon"><i class="fa-solid fa-star"></i></div>
        <div class="kur-stat-value" data-counter="<?= ($sks_by_category['Pilihan'] ?? 0) + ($sks_by_category['MKKU'] ?? 0) ?>">0</div>
        <div class="kur-stat-label">SKS Pilihan & MKKU</div>
    </div>
</div>

<!-- ===== SEMESTER OVERVIEW ===== -->
<div class="panel" style="margin-bottom:2rem;">
    <div class="panel-header">
        <h3 class="panel-title"><i class="fa-solid fa-layer-group"></i> Peta Distribusi per Semester</h3>
        <span style="color:var(--text-muted); font-size:.85rem;">8 Semester · <?= $total_sks ?> Total SKS</span>
    </div>
    <div class="panel-body">
        <div class="sem-overview">
            <?php for ($i = 1; $i <= 8; $i++):
                $mk_sem = $by_semester[$i] ?? [];
                $sks_sem = array_sum(array_column($mk_sem, 'sks'));
                $has_data = count($mk_sem) > 0;
            ?>
            <div class="sem-box <?= $has_data ? 'has-data' : 'empty' ?>" onclick="document.getElementById('semFilter').value='<?= $i ?>'; document.getElementById('semFilter').dispatchEvent(new Event('change'));">
                <div class="sem-num"><?= $i ?></div>
                <div class="sem-count"><?= count($mk_sem) ?> MK</div>
                <?php if ($has_data): ?>
                <div class="sem-sks"><?= $sks_sem ?> SKS</div>
                <?php endif; ?>
            </div>
            <?php endfor; ?>
        </div>
    </div>
</div>

<!-- ===== SKS BREAKDOWN ===== -->
<?php if (count($sks_by_category) > 0): ?>
<div class="sks-breakdown">
    <div class="sks-breakdown-title">
        <i class="fa-solid fa-chart-pie"></i> Distribusi SKS per Kategori
    </div>
    <div class="breakdown-list">
        <?php foreach ($sks_by_category as $cat => $sks): 
            $percent = $total_sks > 0 ? round(($sks / $total_sks) * 100, 1) : 0;
        ?>
        <div class="breakdown-item">
            <div class="breakdown-header">
                <span class="breakdown-cat"><?= htmlspecialchars($cat) ?></span>
                <span class="breakdown-sks"><?= $sks ?> SKS (<?= $percent ?>%)</span>
            </div>
            <div class="breakdown-bar">
                <div class="breakdown-fill" style="width: 0%" data-target="<?= $percent ?>"></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php if (isset($_GET['action']) && $_GET['action'] === 'add' || isset($_GET['edit'])): ?>
<!-- ===== FORM ADD/EDIT WITH LIVE PREVIEW ===== -->
<div class="panel">
    <div class="panel-header">
        <h3 class="panel-title">
            <i class="fa-solid fa-pen-to-square"></i> 
            <?= isset($_GET['edit']) ? 'Edit' : 'Tambah' ?> Mata Kuliah
        </h3>
    </div>
    <div class="panel-body">
        <form method="POST" id="mkForm">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="id" value="<?= $data['id'] ?>">
            
            <div style="display:grid; grid-template-columns: 1.5fr 1fr; gap: 2rem;">
                <!-- FORM FIELDS -->
                <div>
                    <div style="display:grid; grid-template-columns: 1fr 2fr; gap:1rem;">
                        <div class="form-group">
                            <label>Kode MK <span class="required">*</span></label>
                            <div class="input-icon-wrap">
                                <i class="fa-solid fa-hashtag"></i>
                                <input type="text" name="kode_mk" id="f_kode" class="form-control" value="<?= htmlspecialchars($data['kode_mk']) ?>" required placeholder="ENG101" style="text-transform:uppercase; font-family:monospace; font-weight:700;">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Nama Mata Kuliah <span class="required">*</span></label>
                            <div class="input-icon-wrap">
                                <i class="fa-solid fa-book-open"></i>
                                <input type="text" name="nama_mk" id="f_nama" class="form-control" value="<?= htmlspecialchars($data['nama_mk']) ?>" required placeholder="Comprehensive English I">
                            </div>
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:1rem;">
                        <div class="form-group">
                            <label>Semester <span class="required">*</span></label>
                            <select name="semester" id="f_semester" class="form-control select2-premium" style="width:100%;">
                                <?php for ($i = 1; $i <= 8; $i++): ?>
                                <option value="<?= $i ?>" <?= $data['semester']==$i?'selected':'' ?>>Semester <?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>SKS <span class="required">*</span></label>
                            <select name="sks" id="f_sks" class="form-control select2-premium" style="width:100%;">
                                <?php for ($i = 1; $i <= 6; $i++): ?>
                                <option value="<?= $i ?>" <?= $data['sks']==$i?'selected':'' ?>><?= $i ?> SKS</option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Kategori</label>
                            <select name="kategori" id="f_kategori" class="form-control select2-premium" style="width:100%;">
                                <option value="Wajib" <?= $data['kategori']=='Wajib'?'selected':'' ?>>Wajib</option>
                                <option value="Pilihan" <?= $data['kategori']=='Pilihan'?'selected':'' ?>>Pilihan</option>
                                <option value="MKKU" <?= $data['kategori']=='MKKU'?'selected':'' ?>>MKKU</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Deskripsi Singkat</label>
                        <textarea name="deskripsi" id="f_deskripsi" rows="4" class="form-control" placeholder="Deskripsi singkat mata kuliah untuk ditampilkan di website..."><?= htmlspecialchars($data['deskripsi']) ?></textarea>
                        <small style="color:var(--text-muted);"><i class="fa-solid fa-info-circle"></i> Maksimal 200 karakter. Akan ditampilkan di card kurikulum website.</small>
                    </div>

                    <div class="form-group" style="max-width:200px;">
                        <label>Urutan Tampil</label>
                        <input type="number" name="urutan" class="form-control" value="<?= $data['urutan'] ?>" min="1">
                    </div>

                    <div style="margin-top:1.5rem; display:flex; gap:1rem;">
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> <?= $data['id']>0?'Perbarui':'Simpan' ?> Mata Kuliah</button>
                        <a href="kelola_kurikulum.php" class="btn btn-secondary"><i class="fa-solid fa-xmark"></i> Batal</a>
                    </div>
                </div>

                <!-- LIVE PREVIEW -->
                <div class="live-preview-wrapper">
                    <div class="live-preview-title">
                        <i class="fa-solid fa-eye"></i> LIVE PREVIEW (Tampilan di Website)
                    </div>
                    <div class="preview-card" id="previewCard">
                        <div class="p-top">
                            <span class="p-code" id="prev_code"><?= htmlspecialchars($data['kode_mk'] ?: 'ENG101') ?></span>
                            <span class="p-sks" id="prev_sks"><?= $data['sks'] ?> SKS</span>
                        </div>
                        <h4 id="prev_nama"><?= htmlspecialchars($data['nama_mk'] ?: 'Nama Mata Kuliah') ?></h4>
                        <p id="prev_desc"><?= htmlspecialchars($data['deskripsi'] ?: 'Deskripsi singkat mata kuliah akan muncul di sini...') ?></p>
                        <span class="p-cat" id="prev_cat"><?= htmlspecialchars($data['kategori']) ?></span>
                        <div class="preview-meta">
                            <span><i class="fa-solid fa-layer-group"></i> Semester <strong id="prev_sem"><?= $data['semester'] ?></strong></span>
                            <span><i class="fa-solid fa-list-ol"></i> Urutan <strong><?= $data['urutan'] ?></strong></span>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
// Live Preview Logic
document.addEventListener('DOMContentLoaded', function() {
    const fields = {
        kode: document.getElementById('f_kode'),
        nama: document.getElementById('f_nama'),
        semester: document.getElementById('f_semester'),
        sks: document.getElementById('f_sks'),
        kategori: document.getElementById('f_kategori'),
        deskripsi: document.getElementById('f_deskripsi')
    };
    
    function updatePreview() {
        document.getElementById('prev_code').textContent = fields.kode.value.toUpperCase() || 'ENG101';
        document.getElementById('prev_nama').textContent = fields.nama.value || 'Nama Mata Kuliah';
        document.getElementById('prev_sks').textContent = fields.sks.value + ' SKS';
        document.getElementById('prev_cat').textContent = fields.kategori.value;
        document.getElementById('prev_desc').textContent = fields.deskripsi.value || 'Deskripsi singkat mata kuliah akan muncul di sini...';
        document.getElementById('prev_sem').textContent = fields.semester.value;
    }
    
    Object.values(fields).forEach(f => {
        if (f) f.addEventListener('input', updatePreview);
        if (f) f.addEventListener('change', updatePreview);
    });
});
</script>

<?php else: ?>
<!-- ===== FILTER BAR ===== -->
<div class="filter-bar">
    <div class="form-group">
        <label><i class="fa-solid fa-search"></i> Cari Mata Kuliah</label>
        <input type="text" id="mkSearch" class="form-control" placeholder="Ketik kode atau nama MK...">
    </div>
    <div class="form-group">
        <label><i class="fa-solid fa-layer-group"></i> Filter Semester</label>
        <select id="semFilter" class="form-control">
            <option value="all">Semua Semester</option>
            <?php for ($i = 1; $i <= 8; $i++): ?>
            <option value="<?= $i ?>">Semester <?= $i ?></option>
            <?php endfor; ?>
        </select>
    </div>
    <div class="form-group">
        <label><i class="fa-solid fa-filter"></i> Kategori</label>
        <select id="catFilter" class="form-control">
            <option value="all">Semua Kategori</option>
            <option value="Wajib">Wajib</option>
            <option value="Pilihan">Pilihan</option>
            <option value="MKKU">MKKU</option>
        </select>
    </div>
</div>

<!-- ===== TABLE WITH GROUPING ===== -->
<div class="panel">
    <div class="panel-body no-padding">
        <div class="table-wrapper">
            <table class="mk-table">
                <thead>
                    <tr>
                        <th style="width:80px;">Kode</th>
                        <th>Nama Mata Kuliah</th>
                        <th style="width:80px;">SKS</th>
                        <th style="width:100px;">Kategori</th>
                        <th style="width:220px;">Aksi</th>
                    </tr>
                </thead>
                <tbody id="mkTableBody">
                    <?php if (empty($items)): ?>
                    <tr>
                        <td colspan="5" style="text-align:center; padding:4rem 2rem; color:var(--text-muted);">
                            <i class="fa-solid fa-book-open" style="font-size:4rem; margin-bottom:1rem; display:block; opacity:.2;"></i>
                            <h3 style="color:var(--text-muted); margin-bottom:.5rem;">Belum Ada Mata Kuliah</h3>
                            <p style="margin-bottom:1.5rem;">Mulai bangun kurikulum prodi Anda</p>
                            <a href="?action=add" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Tambah Mata Kuliah Pertama</a>
                        </td>
                    </tr>
                    <?php else: 
                        $current_sem = 0;
                        foreach ($items as $item): 
                            $token = hash_hmac('sha256', $item['id'], $_SESSION['csrf_token']);
                            $sem = (int)$item['semester'];
                            $cat = strtolower($item['kategori']);
                            $sks_class = 'sks-' . $item['sks'];
                            
                            // Semester group header
                            if ($sem !== $current_sem) {
                                $current_sem = $sem;
                                $mk_sem = $by_semester[$sem] ?? [];
                                $sks_sem = array_sum(array_column($mk_sem, 'sks'));
                                ?>
                                <tr class="sem-group-header" data-sem="<?= $sem ?>">
                                    <td colspan="5">
                                        <i class="fa-solid fa-layer-group"></i> Semester <?= $sem ?>
                                        <span class="sem-stats">
                                            <i class="fa-solid fa-book"></i> <?= count($mk_sem) ?> MK · 
                                            <i class="fa-solid fa-graduation-cap"></i> <?= $sks_sem ?> SKS
                                        </span>
                                    </td>
                                </tr>
                                <?php
                            }
                    ?>
                    <tr class="mk-row" data-sem="<?= $sem ?>" data-cat="<?= htmlspecialchars($item['kategori']) ?>" data-search="<?= strtolower(htmlspecialchars($item['kode_mk'] . ' ' . $item['nama_mk'])) ?>">
                        <td><span class="mk-code"><?= htmlspecialchars($item['kode_mk']) ?></span></td>
                        <td>
                            <strong><?= htmlspecialchars($item['nama_mk']) ?></strong>
                            <?php if (!empty($item['deskripsi'])): ?>
                            <br><small style="color:var(--text-muted);"><?= htmlspecialchars(substr($item['deskripsi'], 0, 80)) ?><?= strlen($item['deskripsi'])>80?'...':'' ?></small>
                            <?php endif; ?>
                        </td>
                        <td><span class="sks-badge <?= $sks_class ?>"><?= $item['sks'] ?> SKS</span></td>
                        <td><span class="cat-badge <?= $cat ?>"><?= htmlspecialchars($item['kategori']) ?></span></td>
                        <td>
                            <div class="table-actions">
                                <a href="?edit=<?= $item['id'] ?>&token=<?= $token ?>" class="table-action-btn edit" title="Edit"><i class="fa-solid fa-pen"></i></a>
                                <a href="?duplicate=<?= $item['id'] ?>&token=<?= $token ?>" class="table-action-btn" title="Duplikasi" onclick="return confirm('Duplikasi MK ini?')"><i class="fa-solid fa-copy"></i></a>
                                <button class="table-action-btn" title="Detail" onclick="showMKDetail(<?= htmlspecialchars(json_encode($item)) ?>)"><i class="fa-solid fa-eye"></i></button>
                                <a href="?delete=<?= $item['id'] ?>&token=<?= $token ?>" class="table-action-btn delete" onclick="return confirm('Yakin ingin menghapus MK <?= htmlspecialchars($item['kode_mk']) ?>?')" title="Hapus"><i class="fa-solid fa-trash"></i></a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ===== DETAIL MODAL ===== -->
<div id="mkDetailModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.7); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:var(--panel-bg); border:1px solid var(--border-dark); border-radius:var(--radius-md); max-width:600px; width:90%; max-height:90vh; overflow-y:auto; padding:2rem;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
            <h3 style="color:#fff; margin:0;"><i class="fa-solid fa-book-open"></i> Detail Mata Kuliah</h3>
            <button onclick="document.getElementById('mkDetailModal').style.display='none'" style="background:transparent; border:none; color:var(--text-muted); font-size:1.5rem; cursor:pointer;">&times;</button>
        </div>
        <div id="mkDetailContent"></div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    // Init Select2
    if ($('#mkForm').length && $.fn.select2) {
        $('.select2-premium').select2({ dropdownParent: $('#mkForm') });
    }
    
    // Auto uppercase kode MK
    $('#f_kode').on('input', function() {
        this.value = this.value.toUpperCase();
    });
    
    // Animated counter untuk stats
    document.querySelectorAll('[data-counter]').forEach(el => {
        const target = parseInt(el.dataset.counter);
        const duration = 1500;
        const step = target / (duration / 16);
        let current = 0;
        const timer = setInterval(() => {
            current += step;
            if (current >= target) { current = target; clearInterval(timer); }
            el.textContent = Math.floor(current).toLocaleString('id-ID');
        }, 16);
    });
    
    // Animated SKS breakdown bars
    setTimeout(() => {
        document.querySelectorAll('.breakdown-fill').forEach(bar => {
            bar.style.width = bar.dataset.target + '%';
        });
    }, 300);
    
    // Live Search & Filter
    const searchInput = document.getElementById('mkSearch');
    const semFilter = document.getElementById('semFilter');
    const catFilter = document.getElementById('catFilter');
    
    function filterMK() {
        const term = (searchInput?.value || '').toLowerCase();
        const sem = semFilter?.value || 'all';
        const cat = catFilter?.value || 'all';
        
        const rows = document.querySelectorAll('.mk-row');
        const headers = document.querySelectorAll('.sem-group-header');
        
        rows.forEach(row => {
            const searchText = row.dataset.search || '';
            const rowSem = row.dataset.sem;
            const rowCat = row.dataset.cat;
            
            const matchSearch = !term || searchText.includes(term);
            const matchSem = sem === 'all' || rowSem === sem;
            const matchCat = cat === 'all' || rowCat === cat;
            
            row.style.display = (matchSearch && matchSem && matchCat) ? '' : 'none';
        });
        
        // Hide headers yang tidak punya row visible
        headers.forEach(header => {
            const hSem = header.dataset.sem;
            const hasVisible = Array.from(rows).some(r => r.dataset.sem === hSem && r.style.display !== 'none');
            header.style.display = (sem === 'all' || hSem === sem) && hasVisible ? '' : 'none';
        });
    }
    
    if (searchInput) searchInput.addEventListener('input', filterMK);
    if (semFilter) semFilter.addEventListener('change', filterMK);
    if (catFilter) catFilter.addEventListener('change', filterMK);
});

function showMKDetail(mk) {
    const modal = document.getElementById('mkDetailModal');
    const content = document.getElementById('mkDetailContent');
    
    content.innerHTML = `
        <div style="background:var(--bg-darker); border-radius:var(--radius-md); padding:1.5rem; margin-bottom:1rem;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                <span class="mk-code" style="font-size:1rem; padding:.5rem 1rem;">${mk.kode_mk}</span>
                <span class="sks-badge sks-${mk.sks}" style="font-size:.9rem; padding:.4rem 1rem;">${mk.sks} SKS</span>
            </div>
            <h2 style="color:#fff; margin-bottom:.5rem;">${mk.nama_mk}</h2>
            <span class="cat-badge ${mk.kategori.toLowerCase()}" style="font-size:.85rem; padding:.3rem .8rem;">${mk.kategori}</span>
        </div>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1.5rem;">
            <div style="background:var(--bg-darker); padding:1rem; border-radius:var(--radius-md);">
                <div style="color:var(--text-muted); font-size:.8rem; margin-bottom:.3rem;">Semester</div>
                <div style="color:#fff; font-size:1.5rem; font-weight:800;">${mk.semester}</div>
            </div>
            <div style="background:var(--bg-darker); padding:1rem; border-radius:var(--radius-md);">
                <div style="color:var(--text-muted); font-size:.8rem; margin-bottom:.3rem;">Urutan</div>
                <div style="color:#fff; font-size:1.5rem; font-weight:800;">${mk.urutan}</div>
            </div>
        </div>
        <div style="margin-bottom:1.5rem;">
            <h4 style="color:var(--sky-blue); margin-bottom:.5rem;"><i class="fa-solid fa-align-left"></i> Deskripsi</h4>
            <p style="color:var(--text-main); line-height:1.7;">${mk.deskripsi || '<em style="color:var(--text-muted);">Belum ada deskripsi.</em>'}</p>
        </div>
        <div style="display:flex; gap:.8rem; justify-content:flex-end;">
            <a href="?edit=${mk.id}&token=<?= hash_hmac('sha256', 'edit', $_SESSION['csrf_token']) ?>" class="btn btn-primary btn-sm">
                <i class="fa-solid fa-pen"></i> Edit MK
            </a>
            <button onclick="document.getElementById('mkDetailModal').style.display='none'" class="btn btn-secondary btn-sm">Tutup</button>
        </div>
    `;
    modal.style.display = 'flex';
}

// Close modal on outside click
document.getElementById('mkDetailModal')?.addEventListener('click', function(e) {
    if (e.target === this) this.style.display = 'none';
});
</script>

<?php
$page_content = ob_get_clean();
require_once 'includes/admin_template.php';
?>