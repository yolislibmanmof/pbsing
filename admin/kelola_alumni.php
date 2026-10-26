<?php
require_once '../includes/config.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';
require_once 'includes/upload_helper.php';
require_login();

$page_title = 'Kelola Alumni & Tracer Study';
$current_page = 'alumni';
$table = 'alumni';

// ===== HANDLE DELETE =====
if (isset($_GET['delete']) && isset($_GET['token']) && hash_equals(hash_hmac('sha256', (int)$_GET['delete'], $_SESSION['csrf_token']), $_GET['token'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("SELECT foto, nama FROM $table WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if ($row && !empty($row['foto']) && file_exists('../' . $row['foto'])) @unlink('../' . $row['foto']);
    $pdo->prepare("DELETE FROM $table WHERE id = ?")->execute([$id]);
    
    $pdo->prepare("INSERT INTO admin_logs (user_id, user_name, action, detail, ip) VALUES (?, ?, 'DELETE_ALUMNI', ?, ?)")
        ->execute([$_SESSION['admin_id'], $_SESSION['admin_name'], 'Menghapus alumni: ' . ($row['nama'] ?? 'ID '.$id), $_SERVER['REMOTE_ADDR']]);
    
    header("Location: kelola_alumni.php?msg=deleted");
    exit;
}

// ===== HANDLE DUPLICATE =====
if (isset($_GET['duplicate']) && isset($_GET['token']) && hash_equals(hash_hmac('sha256', (int)$_GET['duplicate'], $_SESSION['csrf_token']), $_GET['token'])) {
    $id = (int)$_GET['duplicate'];
    $stmt = $pdo->prepare("SELECT * FROM $table WHERE id = ?");
    $stmt->execute([$id]);
    $orig = $stmt->fetch();
    if ($orig) {
        $pdo->prepare("INSERT INTO $table (nama, angkatan, pekerjaan, perusahaan, sektor, testimoni, linkedin, urutan, ditampilkan) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)")
            ->execute([
                $orig['nama'] . ' (Copy)',
                $orig['angkatan'],
                $orig['pekerjaan'],
                $orig['perusahaan'],
                $orig['sektor'],
                $orig['testimoni'],
                $orig['linkedin'],
                $orig['urutan'] + 1,
                0 // Nonaktif by default
            ]);
    }
    header("Location: kelola_alumni.php?msg=duplicated");
    exit;
}

// ===== HANDLE TOGGLE STATUS =====
if (isset($_POST['toggle_status']) && isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $id = (int)$_POST['id'];
    $ditampilkan = (int)$_POST['ditampilkan'];
    $pdo->prepare("UPDATE $table SET ditampilkan = ? WHERE id = ?")->execute([$ditampilkan, $id]);
    header("Location: kelola_alumni.php?msg=toggled");
    exit;
}

// ===== HANDLE ADD/EDIT =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['toggle_status']) && isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $nama = sanitize($_POST['nama']);
    $angkatan = (int)$_POST['angkatan'];
    $pekerjaan = sanitize($_POST['pekerjaan']);
    $perusahaan = sanitize($_POST['perusahaan']);
    $sektor = sanitize($_POST['sektor']);
    $testimoni = sanitize($_POST['testimoni']);
    $linkedin = sanitize($_POST['linkedin']);
    $urutan = (int)$_POST['urutan'];
    $ditampilkan = isset($_POST['ditampilkan']) ? 1 : 0;

    // Validasi
    if (empty($nama)) {
        $error = "Nama lengkap wajib diisi.";
    } elseif ($angkatan < 2000 || $angkatan > date('Y') + 5) {
        $error = "Tahun angkatan tidak valid.";
    } elseif (!empty($linkedin) && !filter_var($linkedin, FILTER_VALIDATE_URL)) {
        $error = "URL LinkedIn tidak valid.";
    } else {
        $fields = "nama=:nama, angkatan=:angkatan, pekerjaan=:pekerjaan, perusahaan=:perusahaan, sektor=:sektor, testimoni=:testimoni, linkedin=:linkedin, urutan=:urutan, ditampilkan=:ditampilkan";
        $params = [':nama'=>$nama, ':angkatan'=>$angkatan, ':pekerjaan'=>$pekerjaan, ':perusahaan'=>$perusahaan, ':sektor'=>$sektor, ':testimoni'=>$testimoni, ':linkedin'=>$linkedin, ':urutan'=>$urutan, ':ditampilkan'=>$ditampilkan];

        if (!empty($_FILES['foto']['name'])) {
            $upload = handle_upload($_FILES['foto'], '../assets/uploads/alumni/', ['jpg', 'jpeg', 'png', 'webp']);
            if ($upload['success']) {
                $fields .= ", foto=:foto";
                $params[':foto'] = $upload['path'];
                if ($id > 0) {
                    $stmt = $pdo->prepare("SELECT foto FROM $table WHERE id = ?");
                    $stmt->execute([$id]);
                    $old = $stmt->fetch();
                    if ($old && !empty($old['foto']) && file_exists('../' . $old['foto'])) @unlink('../' . $old['foto']);
                }
            } else {
                $error = $upload['error'];
            }
        }

        if (!isset($error)) {
            if ($id > 0) {
                $pdo->prepare("UPDATE $table SET $fields WHERE id=:id")->execute(array_merge($params, [':id'=>$id]));
                $action = 'UPDATE_ALUMNI';
                $detail = "Memperbarui data alumni: $nama (Angkatan $angkatan)";
            } else {
                $pdo->prepare("INSERT INTO $table SET $fields")->execute($params);
                $action = 'ADD_ALUMNI';
                $detail = "Menambah alumni baru: $nama (Angkatan $angkatan)";
            }
            
            $pdo->prepare("INSERT INTO admin_logs (user_id, user_name, action, detail, ip) VALUES (?, ?, ?, ?, ?)")
                ->execute([$_SESSION['admin_id'], $_SESSION['admin_name'], $action, $detail, $_SERVER['REMOTE_ADDR']]);
            
            header("Location: kelola_alumni.php?msg=saved");
            exit;
        }
    }
}

// ===== FETCH DATA =====
$items = $pdo->query("SELECT * FROM $table ORDER BY urutan ASC, id DESC")->fetchAll();

// Statistics
$total_alumni = count($items);
$displayed_alumni = count(array_filter($items, fn($i) => $i['ditampilkan'] == 1));
$sectors = [];
$years = [];
foreach ($items as $a) {
    $sec = $a['sektor'] ?? 'Lainnya';
    $sectors[$sec] = ($sectors[$sec] ?? 0) + 1;
    $year = (int)$a['angkatan'];
    if ($year > 0) $years[$year] = ($years[$year] ?? 0) + 1;
}
$total_sectors = count($sectors);
$total_years = count($years);
ksort($years); // Sort years ascending

// Unique sectors for filter
$unique_sectors = array_unique(array_column($items, 'sektor'));
sort($unique_sectors);

// Form data
$data = ['id'=>0, 'nama'=>'', 'angkatan'=>date('Y'), 'pekerjaan'=>'', 'perusahaan'=>'', 'sektor'=>'Swasta', 'testimoni'=>'', 'foto'=>'', 'linkedin'=>'', 'urutan'=>1, 'ditampilkan'=>1];
if (isset($_GET['edit']) && isset($_GET['token']) && hash_equals(hash_hmac('sha256', (int)$_GET['edit'], $_SESSION['csrf_token']), $_GET['token'])) {
    $stmt = $pdo->prepare("SELECT * FROM $table WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $fetched = $stmt->fetch();
    if ($fetched) $data = $fetched;
}

// Default avatar SVG
$defaultAvatar = 'data:image/svg+xml;utf8,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="120" height="120"><defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#8A1538"/><stop offset="1" stop-color="#1a1a2e"/></linearGradient></defs><rect width="120" height="120" rx="60" fill="url(#g)"/><circle cx="60" cy="45" r="20" fill="#fff" opacity=".85"/><path d="M28 105 c0-18 14-28 32-28 s32 10 32 28" fill="#fff" opacity=".85"/></svg>');

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

/* ===== STATS GRID ===== */
.alumni-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}
.alumni-stat-card {
    background: var(--panel-bg);
    border: 1px solid var(--border-dark);
    border-radius: var(--radius-md);
    padding: 1.3rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: all .3s ease;
    border-left: 4px solid var(--accent-color, var(--crimson));
}
.alumni-stat-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); }
.alumni-stat-icon {
    width: 52px; height: 52px;
    border-radius: var(--radius-md);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.4rem; flex-shrink: 0;
}
.alumni-stat-card.accent-crimson .alumni-stat-icon { background: rgba(138,21,56,0.15); color: var(--crimson); }
.alumni-stat-card.accent-sky .alumni-stat-icon { background: rgba(135,206,235,0.15); color: var(--sky-blue); }
.alumni-stat-card.accent-emerald .alumni-stat-icon { background: rgba(46,139,87,0.15); color: var(--success); }
.alumni-stat-card.accent-warning .alumni-stat-icon { background: rgba(243,156,18,0.15); color: var(--warning); }
.alumni-stat-value { font-size: 1.9rem; font-weight: 900; color: #fff; line-height: 1; }
.alumni-stat-label { color: var(--text-muted); font-size: .82rem; margin-top: .2rem; }

/* ===== SECTOR DISTRIBUTION ===== */
.sector-distribution {
    background: var(--panel-bg);
    border: 1px solid var(--border-dark);
    border-radius: var(--radius-md);
    padding: 1.5rem;
    margin-bottom: 2rem;
}
.sector-dist-title {
    font-size: 1rem; font-weight: 700; color: #fff;
    margin-bottom: 1.2rem;
    display: flex; align-items: center; gap: .6rem;
}
.sector-dist-title i { color: var(--crimson); }
.sector-list { display: flex; flex-direction: column; gap: 1rem; }
.sector-item { display: flex; flex-direction: column; gap: .4rem; }
.sector-header { display: flex; justify-content: space-between; align-items: center; }
.sector-name { font-weight: 600; color: var(--text-main); font-size: .9rem; display: flex; align-items: center; gap: .5rem; }
.sector-count { font-weight: 700; font-size: .85rem; }
.sector-bar {
    height: 8px;
    background: var(--bg-darker);
    border-radius: 50px;
    overflow: hidden;
}
.sector-fill {
    height: 100%;
    border-radius: 50px;
    transition: width 1s ease;
    width: 0;
}
.sector-fill.bumn { background: linear-gradient(90deg, #e74c3c, #c0392b); }
.sector-fill.swasta { background: linear-gradient(90deg, var(--crimson), var(--crimson-light)); }
.sector-fill.pns { background: linear-gradient(90deg, #3498db, #2980b9); }
.sector-fill.akademisi { background: linear-gradient(90deg, var(--success), #27ae60); }
.sector-fill.wirausaha { background: linear-gradient(90deg, #f39c12, #e67e22); }
.sector-fill.lainnya { background: linear-gradient(90deg, #9b59b6, #8e44ad); }

/* ===== YEAR TIMELINE ===== */
.year-timeline {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
    gap: .5rem;
    margin-bottom: 2rem;
}
.year-box {
    background: var(--bg-darker);
    border: 1px solid var(--border-dark);
    border-radius: var(--radius-md);
    padding: .8rem .4rem;
    text-align: center;
    cursor: pointer;
    transition: all .3s ease;
}
.year-box:hover { border-color: var(--crimson); transform: translateY(-2px); }
.year-box.has-data { border-color: var(--success); }
.year-box .year-num { font-size: 1rem; font-weight: 900; color: var(--success); }
.year-box .year-count { font-size: .7rem; color: var(--text-muted); margin-top: .2rem; }
.year-box .year-badge {
    display: inline-block;
    margin-top: .3rem;
    background: var(--success);
    color: #fff;
    font-size: .65rem;
    padding: .1rem .4rem;
    border-radius: 50px;
    font-weight: 700;
}
.year-box.empty { opacity: .4; }
.year-box.empty .year-num { color: var(--text-muted); }

/* ===== FILTER BAR ===== */
.alumni-filter-bar {
    background: var(--panel-bg);
    border: 1px solid var(--border-dark);
    border-radius: var(--radius-md);
    padding: 1.2rem;
    margin-bottom: 1.5rem;
    display: grid;
    grid-template-columns: 2fr 1fr 1fr 1fr;
    gap: 1rem;
    align-items: end;
}
.alumni-filter-bar .form-group { margin: 0; }
.alumni-filter-bar label { font-size: .85rem; color: var(--text-muted); margin-bottom: .4rem; display: block; font-weight: 600; }

/* ===== TABLE ===== */
.alumni-table thead th {
    background: var(--bg-darker);
    color: var(--sky-blue);
    font-size: .78rem;
    text-transform: uppercase;
    letter-spacing: .5px;
    padding: 12px 16px;
    font-weight: 700;
    border-bottom: 1px solid var(--border-dark);
}
.alumni-avatar-sm {
    width: 45px; height: 45px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid var(--border-dark);
    background: var(--bg-darker);
}
.alumni-initial {
    width: 45px; height: 45px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--crimson), var(--crimson-light));
    color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-weight: 900;
    font-size: 1.1rem;
    border: 2px solid var(--border-dark);
}

/* ===== SECTOR BADGES (6 Colors) ===== */
.sector-badge {
    display: inline-block;
    padding: .25rem .7rem;
    border-radius: 50px;
    font-size: .72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .5px;
}
.sector-badge.bumn { background: rgba(231,76,60,0.15); color: #e74c3c; }
.sector-badge.swasta { background: rgba(138,21,56,0.15); color: var(--crimson); }
.sector-badge.pns { background: rgba(52,152,219,0.15); color: #3498db; }
.sector-badge.akademisi { background: rgba(46,139,87,0.15); color: var(--success); }
.sector-badge.wirausaha { background: rgba(243,156,18,0.15); color: #f39c12; }
.sector-badge.lainnya { background: rgba(155,89,182,0.15); color: #9b59b6; }

/* ===== MODERN TOGGLE SWITCH ===== */
.toggle-switch {
    position: relative;
    display: inline-block;
    width: 42px; height: 22px;
    flex-shrink: 0;
}
.toggle-switch input { opacity: 0; width: 0; height: 0; }
.toggle-slider {
    position: absolute; cursor: pointer; inset: 0;
    background: var(--bg-darker);
    border: 1px solid var(--border-dark);
    transition: .3s;
    border-radius: 50px;
}
.toggle-slider:before {
    position: absolute; content: "";
    height: 16px; width: 16px;
    left: 2px; bottom: 2px;
    background: var(--text-muted);
    transition: .3s;
    border-radius: 50%;
}
.toggle-switch input:checked + .toggle-slider { background: var(--success); border-color: var(--success); }
.toggle-switch input:checked + .toggle-slider:before { transform: translateX(20px); background: #fff; }

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
    display: flex; align-items: center; gap: .5rem;
}
.live-preview-title i { color: var(--sky-blue); }
.preview-alumni-card {
    background: #fff;
    border-radius: 20px;
    padding: 1.5rem;
    box-shadow: 0 10px 35px rgba(0,0,0,.3);
    border-bottom: 4px solid var(--emerald);
    color: #333;
}
.preview-alumni-head {
    display: flex;
    gap: 1rem;
    margin-bottom: 1rem;
}
.preview-alumni-avatar {
    width: 60px; height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--crimson), var(--crimson-light));
    display: flex; align-items: center; justify-content: center;
    color: #fff;
    font-weight: 900;
    font-size: 1.5rem;
    flex-shrink: 0;
    overflow: hidden;
    box-shadow: 0 6px 15px rgba(138,21,56,.3);
}
.preview-alumni-avatar img { width: 100%; height: 100%; object-fit: cover; }
.preview-alumni-info h3 {
    font-size: 1.05rem;
    margin: 0 0 .3rem;
    color: #222;
    font-weight: 700;
}
.preview-position, .preview-company {
    color: #666;
    font-size: .82rem;
    margin-bottom: .2rem;
    display: flex; align-items: center; gap: .3rem;
}
.preview-position i { color: var(--crimson); }
.preview-meta {
    display: flex;
    gap: .5rem;
    margin-bottom: 1rem;
    flex-wrap: wrap;
}
.preview-badge-green, .preview-badge-blue {
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    padding: .25rem .7rem;
    border-radius: 50px;
    font-size: .7rem;
    font-weight: 700;
}
.preview-badge-green { background: rgba(46,139,87,.12); color: var(--emerald); }
.preview-badge-blue { background: rgba(135,206,235,.2); color: #0077b6; }
.preview-testi {
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 12px;
    margin-bottom: 1rem;
    position: relative;
    border-left: 3px solid var(--emerald);
    font-size: .82rem;
    color: #555;
    font-style: italic;
    line-height: 1.6;
    min-height: 60px;
}
.preview-testi i {
    position: absolute;
    top: .5rem; right: .5rem;
    color: rgba(138,21,56,.15);
    font-size: 1rem;
}
.preview-linkedin {
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    padding: .5rem 1rem;
    background: #0a66c2;
    color: #fff;
    border-radius: 50px;
    font-size: .78rem;
    font-weight: 600;
    text-decoration: none;
}

/* ===== PROFILE UPLOAD ===== */
.profile-upload-wrapper {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1rem;
}
.profile-preview {
    width: 140px; height: 140px;
    border-radius: 50%;
    border: 4px solid var(--border-dark);
    background: var(--bg-darker);
    display: flex; align-items: center; justify-content: center;
    overflow: hidden;
    transition: all 0.3s ease;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
}
.profile-preview:hover { border-color: var(--crimson); transform: scale(1.02); }
.profile-preview img { width: 100%; height: 100%; object-fit: cover; }
.profile-preview .placeholder-icon { font-size: 3rem; color: var(--text-muted); opacity: 0.3; }
.profile-upload-btn { position: relative; overflow: hidden; display: inline-block; }
.profile-upload-btn input[type=file] { position: absolute; left: 0; top: 0; opacity: 0; width: 100%; height: 100%; cursor: pointer; }

/* ===== INPUT ICON ===== */
.input-icon-wrap { position: relative; }
.input-icon-wrap i {
    position: absolute; left: 14px; top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted); font-size: .9rem;
    pointer-events: none;
}
.input-icon-wrap .form-control { padding-left: 42px; }

.form-section-title {
    font-size: .82rem;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    color: var(--sky-blue);
    margin: 1.5rem 0 1rem;
    padding-bottom: .5rem;
    border-bottom: 1px solid var(--border-dark);
    display: flex; align-items: center; gap: .5rem;
}
.form-section-title:first-child { margin-top: 0; }

/* ===== EMPTY STATE ===== */
.alumni-empty {
    text-align: center;
    padding: 4rem 2rem;
    background: var(--panel-bg);
    border: 2px dashed var(--border-dark);
    border-radius: var(--radius-lg);
}
.alumni-empty-icon {
    width: 100px; height: 100px;
    margin: 0 auto 1.5rem;
    background: linear-gradient(135deg, var(--success), #27ae60);
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 3rem;
    color: #fff;
    box-shadow: 0 10px 30px rgba(46,139,87,.3);
}
.alumni-empty h3 { color: #fff; margin-bottom: .5rem; font-size: 1.3rem; }
.alumni-empty p { color: var(--text-muted); margin-bottom: 1.5rem; max-width: 500px; margin-left: auto; margin-right: auto; }

/* ===== FORM GRID ===== */
.alumni-form-grid {
    display: grid;
    grid-template-columns: 1.5fr 1fr;
    gap: 2rem;
}
@media (max-width: 992px) {
    .alumni-form-grid { grid-template-columns: 1fr; }
    .alumni-filter-bar { grid-template-columns: 1fr; }
    .year-timeline { grid-template-columns: repeat(6, 1fr); }
}
@media (max-width: 576px) {
    .year-timeline { grid-template-columns: repeat(4, 1fr); }
}
</style>

<?php if (isset($_GET['msg'])): 
    $msgs = [
        'saved' => ['success', 'Data alumni berhasil disimpan!'],
        'deleted' => ['success', 'Data alumni berhasil dihapus!'],
        'duplicated' => ['success', 'Data alumni berhasil diduplikasi!'],
        'toggled' => ['success', 'Status alumni diperbarui!']
    ];
    if (isset($msgs[$_GET['msg']])): ?>
    <script>Swal.fire({icon:'<?=$msgs[$_GET['msg']][0]?>', title:'Berhasil!', text:'<?=$msgs[$_GET['msg']][1]?>', timer:2500, showConfirmButton:false});</script>
<?php endif; endif; ?>

<?php if (isset($error)): ?>
<script>Swal.fire({icon:'error', title:'Validasi Gagal', text:'<?= addslashes($error) ?>'});</script>
<?php endif; ?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;">
    <div>
        <h2 class="section-title"><i class="fa-solid fa-user-graduate"></i> <?= isset($_GET['edit']) ? 'Edit' : 'Kelola' ?> Alumni & Tracer Study</h2>
        <p style="color:var(--text-muted); font-size:.88rem; margin-top:.3rem;">
            <i class="fa-solid fa-circle-info"></i> Jejak sukses lulusan PBSI UMMaumere di dunia kerja
        </p>
    </div>
    <div style="display:flex; gap:.8rem;">
        <?php if (isset($_GET['edit'])): ?>
        <a href="kelola_alumni.php" class="btn btn-secondary"><i class="fa-solid fa-xmark"></i> Batal</a>
        <?php else: ?>
        <a href="?action=add" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Tambah Alumni</a>
        <?php endif; ?>
    </div>
</div>

<!-- ===== STATS DASHBOARD ===== -->
<div class="alumni-stats-grid">
    <div class="alumni-stat-card accent-crimson">
        <div class="alumni-stat-icon"><i class="fa-solid fa-users"></i></div>
        <div>
            <div class="alumni-stat-value" data-counter="<?= $total_alumni ?>">0</div>
            <div class="alumni-stat-label">Total Alumni</div>
        </div>
    </div>
    <div class="alumni-stat-card accent-emerald">
        <div class="alumni-stat-icon"><i class="fa-solid fa-eye"></i></div>
        <div>
            <div class="alumni-stat-value" data-counter="<?= $displayed_alumni ?>">0</div>
            <div class="alumni-stat-label">Ditampilkan</div>
        </div>
    </div>
    <div class="alumni-stat-card accent-sky">
        <div class="alumni-stat-icon"><i class="fa-solid fa-building"></i></div>
        <div>
            <div class="alumni-stat-value" data-counter="<?= $total_sectors ?>">0</div>
            <div class="alumni-stat-label">Sektor Industri</div>
        </div>
    </div>
    <div class="alumni-stat-card accent-warning">
        <div class="alumni-stat-icon"><i class="fa-solid fa-graduation-cap"></i></div>
        <div>
            <div class="alumni-stat-value" data-counter="<?= $total_years ?>">0</div>
            <div class="alumni-stat-label">Angkatan Lulusan</div>
        </div>
    </div>
</div>

<!-- ===== SECTOR DISTRIBUTION ===== -->
<?php if (count($sectors) > 0): ?>
<div class="sector-distribution">
    <div class="sector-dist-title">
        <i class="fa-solid fa-chart-pie"></i> Distribusi Alumni per Sektor
    </div>
    <div class="sector-list">
        <?php foreach ($sectors as $sec => $count): 
            $percent = $total_alumni > 0 ? round(($count / $total_alumni) * 100, 1) : 0;
            $secKey = strtolower(str_replace(['/', ' '], '', $sec));
            $colorClass = 'lainnya';
            if (stripos($sec, 'BUMN') !== false) $colorClass = 'bumn';
            elseif (stripos($sec, 'Swasta') !== false) $colorClass = 'swasta';
            elseif (stripos($sec, 'PNS') !== false || stripos($sec, 'TNI') !== false) $colorClass = 'pns';
            elseif (stripos($sec, 'Akademisi') !== false) $colorClass = 'akademisi';
            elseif (stripos($sec, 'Wirausaha') !== false) $colorClass = 'wirausaha';
        ?>
        <div class="sector-item">
            <div class="sector-header">
                <span class="sector-name">
                    <i class="fa-solid fa-building"></i>
                    <?= htmlspecialchars($sec) ?>
                </span>
                <span class="sector-count" style="color: var(--sky-blue);"><?= $count ?> Alumni (<?= $percent ?>%)</span>
            </div>
            <div class="sector-bar">
                <div class="sector-fill <?= $colorClass ?>" data-target="<?= $percent ?>"></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- ===== YEAR TIMELINE ===== -->
<?php if (count($years) > 0): ?>
<div class="panel" style="margin-bottom:2rem;">
    <div class="panel-header">
        <h3 class="panel-title"><i class="fa-solid fa-calendar-days"></i> Timeline Angkatan</h3>
        <span style="color:var(--text-muted); font-size:.85rem;">Klik untuk filter</span>
    </div>
    <div class="panel-body">
        <div class="year-timeline">
            <?php 
            $minYear = min(array_keys($years));
            $maxYear = max(array_keys($years));
            for ($y = $minYear; $y <= $maxYear; $y++):
                $count = $years[$y] ?? 0;
                $hasData = $count > 0;
            ?>
            <div class="year-box <?= $hasData ? 'has-data' : 'empty' ?>" onclick="document.getElementById('yearFilter').value='<?= $y ?>'; document.getElementById('yearFilter').dispatchEvent(new Event('change'));">
                <div class="year-num"><?= $y ?></div>
                <?php if ($hasData): ?>
                <div class="year-badge"><?= $count ?></div>
                <?php else: ?>
                <div class="year-count">-</div>
                <?php endif; ?>
            </div>
            <?php endfor; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (isset($_GET['action']) && $_GET['action'] === 'add' || isset($_GET['edit'])): ?>
<!-- ===== FORM ADD/EDIT WITH LIVE PREVIEW ===== -->
<div class="panel">
    <div class="panel-header">
        <h3 class="panel-title">
            <i class="fa-solid fa-pen-to-square"></i> 
            <?= isset($_GET['edit']) ? 'Edit' : 'Tambah' ?> Data Alumni
        </h3>
    </div>
    <div class="panel-body">
        <form method="POST" enctype="multipart/form-data" id="alumniForm">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="id" value="<?= $data['id'] ?>">
            
            <div class="alumni-form-grid">
                <!-- FORM FIELDS -->
                <div>
                    <div class="form-section-title"><i class="fa-solid fa-user"></i> Informasi Pribadi</div>
                    
                    <div class="form-group">
                        <label>Nama Lengkap <span class="required">*</span></label>
                        <div class="input-icon-wrap">
                            <i class="fa-solid fa-user"></i>
                            <input type="text" name="nama" id="f_nama" class="form-control" value="<?= htmlspecialchars($data['nama']) ?>" required placeholder="John Doe">
                        </div>
                    </div>
                    
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem;">
                        <div class="form-group">
                            <label>Angkatan (Tahun Lulus)</label>
                            <div class="input-icon-wrap">
                                <i class="fa-solid fa-calendar"></i>
                                <input type="number" name="angkatan" id="f_angkatan" class="form-control" value="<?= $data['angkatan'] ?>" min="2000" max="<?= date('Y')+5 ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Urutan Tampil</label>
                            <input type="number" name="urutan" class="form-control" value="<?= $data['urutan'] ?>" min="1">
                        </div>
                    </div>

                    <div class="form-section-title"><i class="fa-solid fa-briefcase"></i> Karir Saat Ini</div>
                    
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem;">
                        <div class="form-group">
                            <label>Jabatan / Pekerjaan</label>
                            <div class="input-icon-wrap">
                                <i class="fa-solid fa-briefcase"></i>
                                <input type="text" name="pekerjaan" id="f_pekerjaan" class="form-control" value="<?= htmlspecialchars($data['pekerjaan']) ?>" placeholder="English Teacher">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Perusahaan / Instansi</label>
                            <div class="input-icon-wrap">
                                <i class="fa-solid fa-building"></i>
                                <input type="text" name="perusahaan" id="f_perusahaan" class="form-control" value="<?= htmlspecialchars($data['perusahaan']) ?>" placeholder="SMA Negeri 1 Maumere">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Sektor Pekerjaan</label>
                        <select name="sektor" id="f_sektor" class="form-control select2-premium" style="width:100%;">
                            <?php foreach (['BUMN', 'Swasta', 'PNS/TNI/Polri', 'Akademisi', 'Wirausaha', 'Lainnya'] as $sek): ?>
                            <option value="<?= $sek ?>" <?= $data['sektor']==$sek?'selected':'' ?>><?= $sek ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-section-title"><i class="fa-solid fa-quote-left"></i> Testimoni & Sosial Media</div>
                    
                    <div class="form-group">
                        <label>Testimoni / Pesan</label>
                        <textarea name="testimoni" id="f_testimoni" rows="4" class="form-control" placeholder="Kesan dan pesan selama kuliah di PBSI UMMaumere..."><?= htmlspecialchars($data['testimoni']) ?></textarea>
                        <small style="color:var(--text-muted);"><i class="fa-solid fa-info-circle"></i> Akan ditampilkan di card alumni di website</small>
                    </div>
                    
                    <div class="form-group">
                        <label>LinkedIn URL (Opsional)</label>
                        <div class="input-icon-wrap">
                            <i class="fa-brands fa-linkedin"></i>
                            <input type="url" name="linkedin" id="f_linkedin" class="form-control" value="<?= htmlspecialchars($data['linkedin']) ?>" placeholder="https://linkedin.com/in/username">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="checkbox-wrap" style="padding:1rem; background:var(--bg-darker); border-radius:var(--radius-md); border:1px solid var(--border-dark);">
                            <input type="checkbox" name="ditampilkan" value="1" <?= $data['ditampilkan']==1?'checked':'' ?> style="width:18px; height:18px;">
                            <span style="font-weight:600;">Tampilkan di Website</span>
                        </label>
                    </div>

                    <div style="margin-top:2rem; display:flex; gap:1rem; padding-top:1.5rem; border-top:1px solid var(--border-dark);">
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> <?= $data['id']>0?'Perbarui':'Simpan' ?> Data Alumni</button>
                        <a href="kelola_alumni.php" class="btn btn-secondary"><i class="fa-solid fa-xmark"></i> Batal</a>
                    </div>
                </div>

                <!-- LIVE PREVIEW -->
                <div class="live-preview-wrapper">
                    <div class="live-preview-title">
                        <i class="fa-solid fa-eye"></i> LIVE PREVIEW (Tampilan di Website)
                    </div>
                    
                    <div class="preview-alumni-card" id="previewCard">
                        <div class="preview-alumni-head">
                            <div class="preview-alumni-avatar" id="prevAvatar">
                                <img src="<?= !empty($data['foto']) ? '../'.htmlspecialchars($data['foto']) : '' ?>" id="prevAvatarImg" style="<?= empty($data['foto']) ? 'display:none;' : '' ?>">
                                <span id="prevInitial" style="<?= !empty($data['foto']) ? 'display:none;' : '' ?>"><?= strtoupper(substr($data['nama'] ?: 'U', 0, 1)) ?></span>
                            </div>
                            <div class="preview-alumni-info">
                                <h3 id="prevNama"><?= htmlspecialchars($data['nama'] ?: 'Nama Alumni') ?></h3>
                                <p class="preview-position" id="prevPositionWrap">
                                    <i class="fa-solid fa-briefcase"></i>
                                    <span id="prevPekerjaan"><?= htmlspecialchars($data['pekerjaan'] ?: 'Jabatan Pekerjaan') ?></span>
                                </p>
                                <p class="preview-company">
                                    @ <span id="prevPerusahaan"><?= htmlspecialchars($data['perusahaan'] ?: 'Nama Perusahaan') ?></span>
                                </p>
                            </div>
                        </div>
                        
                        <div class="preview-meta">
                            <span class="preview-badge-green" id="prevSektorBadge">
                                <i class="fa-solid fa-building"></i>
                                <span id="prevSektor"><?= htmlspecialchars($data['sektor']) ?></span>
                            </span>
                            <span class="preview-badge-blue">
                                <i class="fa-solid fa-graduation-cap"></i>
                                Angkatan <span id="prevAngkatan"><?= $data['angkatan'] ?></span>
                            </span>
                        </div>
                        
                        <div class="preview-testi">
                            <i class="fa-solid fa-quote-left"></i>
                            <p id="prevTestimoni"><?= htmlspecialchars($data['testimoni'] ?: 'Testimoni alumni akan muncul di sini...') ?></p>
                        </div>
                        
                        <a href="#" class="preview-linkedin" id="prevLinkedin" style="<?= empty($data['linkedin']) ? 'display:none;' : '' ?>">
                            <i class="fa-brands fa-linkedin"></i> Lihat Profil LinkedIn
                        </a>
                    </div>
                    
                    <div style="margin-top:1rem;">
                        <label style="font-size:.82rem; color:var(--text-muted); display:block; margin-bottom:.5rem;">Foto Profil</label>
                        <div class="profile-upload-wrapper">
                            <div class="profile-preview" id="profilePreview">
                                <?php if (!empty($data['foto'])): ?>
                                    <img src="../<?= htmlspecialchars($data['foto']) ?>" id="previewImg">
                                <?php else: ?>
                                    <i class="fa-solid fa-user placeholder-icon"></i>
                                <?php endif; ?>
                            </div>
                            <div class="profile-upload-btn">
                                <button type="button" class="btn btn-secondary btn-sm"><i class="fa-solid fa-camera"></i> Ganti Foto</button>
                                <input type="file" name="foto" accept="image/*" id="fileInput">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Live preview binding
    const fields = {
        nama: document.getElementById('f_nama'),
        angkatan: document.getElementById('f_angkatan'),
        pekerjaan: document.getElementById('f_pekerjaan'),
        perusahaan: document.getElementById('f_perusahaan'),
        sektor: document.getElementById('f_sektor'),
        testimoni: document.getElementById('f_testimoni'),
        linkedin: document.getElementById('f_linkedin'),
        file: document.getElementById('fileInput')
    };
    
    const prevNama = document.getElementById('prevNama');
    const prevAngkatan = document.getElementById('prevAngkatan');
    const prevPekerjaan = document.getElementById('prevPekerjaan');
    const prevPerusahaan = document.getElementById('prevPerusahaan');
    const prevSektor = document.getElementById('prevSektor');
    const prevTestimoni = document.getElementById('prevTestimoni');
    const prevInitial = document.getElementById('prevInitial');
    const prevAvatarImg = document.getElementById('prevAvatarImg');
    const prevLinkedin = document.getElementById('prevLinkedin');
    
    fields.nama?.addEventListener('input', e => {
        prevNama.textContent = e.target.value || 'Nama Alumni';
        const initial = e.target.value ? e.target.value.charAt(0).toUpperCase() : 'U';
        if (prevInitial) prevInitial.textContent = initial;
    });
    fields.angkatan?.addEventListener('input', e => prevAngkatan.textContent = e.target.value || '----');
    fields.pekerjaan?.addEventListener('input', e => prevPekerjaan.textContent = e.target.value || 'Jabatan Pekerjaan');
    fields.perusahaan?.addEventListener('input', e => prevPerusahaan.textContent = e.target.value || 'Nama Perusahaan');
    fields.sektor?.addEventListener('change', e => prevSektor.textContent = e.target.value);
    fields.testimoni?.addEventListener('input', e => prevTestimoni.textContent = e.target.value || 'Testimoni alumni akan muncul di sini...');
    fields.linkedin?.addEventListener('input', e => {
        if (prevLinkedin) prevLinkedin.style.display = e.target.value ? 'inline-flex' : 'none';
    });
    
    // File upload preview (update both card avatar and profile preview)
    fields.file?.addEventListener('change', e => {
        if (e.target.files.length) {
            const reader = new FileReader();
            reader.onload = ev => {
                // Update card avatar
                if (prevAvatarImg) {
                    prevAvatarImg.src = ev.target.result;
                    prevAvatarImg.style.display = 'block';
                }
                if (prevInitial) prevInitial.style.display = 'none';
                
                // Update profile preview
                const profileImg = document.getElementById('previewImg');
                const previewBox = document.getElementById('profilePreview');
                if (!profileImg) {
                    const img = document.createElement('img');
                    img.id = 'previewImg';
                    previewBox.innerHTML = '';
                    previewBox.appendChild(img);
                    img.src = ev.target.result;
                } else {
                    profileImg.src = ev.target.result;
                }
            };
            reader.readAsDataURL(e.target.files[0]);
        }
    });
});
</script>

<?php else: ?>
<!-- ===== FILTER BAR ===== -->
<div class="alumni-filter-bar">
    <div class="form-group">
        <label><i class="fa-solid fa-search"></i> Cari Alumni</label>
        <input type="text" id="alumniSearch" class="form-control" placeholder="Ketik nama, pekerjaan, atau perusahaan...">
    </div>
    <div class="form-group">
        <label><i class="fa-solid fa-building"></i> Sektor</label>
        <select id="sectorFilter" class="form-control">
            <option value="all">Semua Sektor</option>
            <?php foreach ($unique_sectors as $sec): ?>
            <option value="<?= htmlspecialchars($sec) ?>"><?= htmlspecialchars($sec) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label><i class="fa-solid fa-calendar"></i> Angkatan</label>
        <select id="yearFilter" class="form-control">
            <option value="all">Semua Angkatan</option>
            <?php foreach (array_keys($years) as $y): ?>
            <option value="<?= $y ?>"><?= $y ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label><i class="fa-solid fa-eye"></i> Status</label>
        <select id="statusFilter" class="form-control">
            <option value="all">Semua</option>
            <option value="1">Ditampilkan</option>
            <option value="0">Disembunyikan</option>
        </select>
    </div>
</div>

<!-- ===== LIST VIEW ===== -->
<?php if (empty($items)): ?>
<div class="alumni-empty">
    <div class="alumni-empty-icon"><i class="fa-solid fa-user-graduate"></i></div>
    <h3>Belum Ada Data Alumni</h3>
    <p>Mulai dokumentasikan jejak sukses lulusan PBSI UMMaumere di dunia kerja</p>
    <a href="?action=add" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Tambah Alumni Pertama</a>
</div>
<?php else: ?>
<div class="panel">
    <div class="panel-body no-padding">
        <div class="table-wrapper">
            <table class="alumni-table">
                <thead>
                    <tr>
                        <th style="width:60px;">Foto</th>
                        <th>Nama</th>
                        <th>Karir</th>
                        <th style="width:80px;">Angkatan</th>
                        <th style="width:130px;">Sektor</th>
                        <th style="width:90px;">Status</th>
                        <th style="width:180px;">Aksi</th>
                    </tr>
                </thead>
                <tbody id="alumniTableBody">
                    <?php foreach ($items as $item): 
                        $token = hash_hmac('sha256', $item['id'], $_SESSION['csrf_token']);
                        $secKey = strtolower(str_replace(['/', ' '], '', $item['sektor'] ?? ''));
                        $colorClass = 'lainnya';
                        if (stripos($item['sektor'], 'BUMN') !== false) $colorClass = 'bumn';
                        elseif (stripos($item['sektor'], 'Swasta') !== false) $colorClass = 'swasta';
                        elseif (stripos($item['sektor'], 'PNS') !== false || stripos($item['sektor'], 'TNI') !== false) $colorClass = 'pns';
                        elseif (stripos($item['sektor'], 'Akademisi') !== false) $colorClass = 'akademisi';
                        elseif (stripos($item['sektor'], 'Wirausaha') !== false) $colorClass = 'wirausaha';
                    ?>
                    <tr class="alumni-row" 
                         data-search="<?= strtolower(htmlspecialchars($item['nama'] . ' ' . $item['pekerjaan'] . ' ' . $item['perusahaan'])) ?>"
                         data-sector="<?= htmlspecialchars($item['sektor']) ?>"
                         data-year="<?= $item['angkatan'] ?>"
                         data-status="<?= $item['ditampilkan'] ?>">
                        <td>
                            <?php if (!empty($item['foto'])): ?>
                            <img src="../<?= htmlspecialchars($item['foto']) ?>" class="alumni-avatar-sm" alt="<?= htmlspecialchars($item['nama']) ?>" onerror="this.outerHTML='<div class=alumni-initial><?= strtoupper(substr($item['nama'], 0, 1)) ?></div>'">
                            <?php else: ?>
                            <div class="alumni-initial"><?= strtoupper(substr($item['nama'], 0, 1)) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($item['nama']) ?></strong>
                            <?php if (!empty($item['testimoni'])): ?>
                            <br><small style="color:var(--text-muted);">"<i><?= htmlspecialchars(substr($item['testimoni'], 0, 60)) ?><?= strlen($item['testimoni'])>60?'...':'' ?></i>"</small>
                            <?php endif; ?>
                            <?php if (!empty($item['linkedin'])): ?>
                            <br><a href="<?= htmlspecialchars($item['linkedin']) ?>" target="_blank" style="color:var(--sky-blue); font-size:.75rem;"><i class="fa-brands fa-linkedin"></i> LinkedIn</a>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($item['pekerjaan']) ?: '-' ?></strong>
                            <?php if (!empty($item['perusahaan'])): ?>
                            <br><small style="color:var(--text-muted);">@ <?= htmlspecialchars($item['perusahaan']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><strong><?= $item['angkatan'] ?></strong></td>
                        <td><span class="sector-badge <?= $colorClass ?>"><?= htmlspecialchars($item['sektor']) ?></span></td>
                        <td>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="toggle_status" value="1">
                                <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                <input type="hidden" name="ditampilkan" value="<?= $item['ditampilkan'] == 1 ? 0 : 1 ?>">
                                <label class="toggle-switch" title="<?= $item['ditampilkan'] == 1 ? 'Sembunyikan' : 'Tampilkan' ?>">
                                    <input type="checkbox" <?= $item['ditampilkan'] == 1 ? 'checked' : '' ?> onchange="this.form.submit()">
                                    <span class="toggle-slider"></span>
                                </label>
                            </form>
                        </td>
                        <td>
                            <div class="table-actions">
                                <a href="?edit=<?= $item['id'] ?>&token=<?= $token ?>" class="table-action-btn edit" title="Edit"><i class="fa-solid fa-pen"></i></a>
                                <a href="?duplicate=<?= $item['id'] ?>&token=<?= $token ?>" class="table-action-btn" title="Duplikasi" onclick="return confirm('Duplikasi data alumni ini?')"><i class="fa-solid fa-copy"></i></a>
                                <a href="?delete=<?= $item['id'] ?>&token=<?= $token ?>" class="table-action-btn delete" onclick="return confirm('Yakin ingin menghapus alumni <?= htmlspecialchars($item['nama']) ?>?')" title="Hapus"><i class="fa-solid fa-trash"></i></a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Results count -->
<div style="text-align:center; margin-top:1rem; padding:.8rem; background:rgba(46,139,87,.05); border-radius:8px; color:var(--text-muted); font-size:.88rem;">
    Menampilkan <strong id="alumniCount" style="color:var(--success);"><?= $total_alumni ?></strong> dari <strong><?= $total_alumni ?></strong> alumni
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('alumniSearch');
    const sectorFilter = document.getElementById('sectorFilter');
    const yearFilter = document.getElementById('yearFilter');
    const statusFilter = document.getElementById('statusFilter');
    const countEl = document.getElementById('alumniCount');
    
    function filterAlumni() {
        const term = (searchInput?.value || '').toLowerCase();
        const sector = sectorFilter?.value || 'all';
        const year = yearFilter?.value || 'all';
        const status = statusFilter?.value || 'all';
        
        const rows = document.querySelectorAll('.alumni-row');
        let visibleCount = 0;
        
        rows.forEach(row => {
            const searchText = row.dataset.search || '';
            const rowSector = row.dataset.sector;
            const rowYear = row.dataset.year;
            const rowStatus = row.dataset.status;
            
            const matchSearch = !term || searchText.includes(term);
            const matchSector = sector === 'all' || rowSector === sector;
            const matchYear = year === 'all' || rowYear === year;
            const matchStatus = status === 'all' || rowStatus === status;
            
            if (matchSearch && matchSector && matchYear && matchStatus) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        
        if (countEl) countEl.textContent = visibleCount;
    }
    
    if (searchInput) searchInput.addEventListener('input', filterAlumni);
    if (sectorFilter) sectorFilter.addEventListener('change', filterAlumni);
    if (yearFilter) yearFilter.addEventListener('change', filterAlumni);
    if (statusFilter) statusFilter.addEventListener('change', filterAlumni);
});
</script>
<?php endif; ?>
<?php endif; ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    // Init Select2
    if ($('#alumniForm').length && $.fn.select2) {
        $('.select2-premium').select2({ dropdownParent: $('#alumniForm') });
    }
    
    // Animated counter untuk stats
    document.querySelectorAll('[data-counter]').forEach(el => {
        const target = parseInt(el.dataset.counter);
        if (target === 0) { el.textContent = '0'; return; }
        const duration = 1500;
        const step = target / (duration / 16);
        let current = 0;
        const timer = setInterval(() => {
            current += step;
            if (current >= target) { current = target; clearInterval(timer); }
            el.textContent = Math.floor(current).toLocaleString('id-ID');
        }, 16);
    });
    
    // Animated sector bars
    setTimeout(() => {
        document.querySelectorAll('.sector-fill').forEach(bar => {
            bar.style.width = bar.dataset.target + '%';
        });
    }, 300);
});
</script>

<?php
$page_content = ob_get_clean();
require_once 'includes/admin_template.php';
?>