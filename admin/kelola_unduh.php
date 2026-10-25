<?php
require_once '../includes/config.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';
require_once 'includes/upload_helper.php';
require_login();

$page_title = 'Kelola Unduhan & RPS';
$current_page = 'unduh';

// ===== SELF-HEALING: Pastikan tabel unduh & rps_kurikulum ada =====
$pdo->exec("CREATE TABLE IF NOT EXISTS `unduh` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `judul` varchar(255) NOT NULL,
    `deskripsi` text DEFAULT NULL,
    `kategori` varchar(100) DEFAULT 'Akademik',
    `file_path` varchar(255) DEFAULT '',
    `ukuran_file` varchar(50) DEFAULT '',
    `tanggal_upload` datetime DEFAULT CURRENT_TIMESTAMP,
    `jumlah_unduh` int(11) DEFAULT 0,
    `urutan` int(11) DEFAULT 1,
    `aktif` tinyint(1) DEFAULT 1,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS `rps_kurikulum` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `kode_mk` varchar(50) DEFAULT '',
    `mata_kuliah` varchar(255) NOT NULL,
    `semester` varchar(20) DEFAULT '1',
    `tahun_akademik` varchar(20) DEFAULT '',
    `file_path` varchar(255) DEFAULT '',
    `ukuran_file` varchar(50) DEFAULT '',
    `jumlah_unduh` int(11) DEFAULT 0,
    `urutan` int(11) DEFAULT 1,
    `aktif` tinyint(1) DEFAULT 1,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Auto-add kolom yang mungkin hilang
$unduh_cols = $pdo->query("SHOW COLUMNS FROM unduh")->fetchAll(PDO::FETCH_COLUMN);
$required_unduh = [
    'deskripsi' => "text DEFAULT NULL",
    'kategori' => "varchar(100) DEFAULT 'Akademik'",
    'ukuran_file' => "varchar(50) DEFAULT ''",
    'jumlah_unduh' => "int(11) DEFAULT 0",
    'urutan' => "int(11) DEFAULT 1",
    'aktif' => "tinyint(1) DEFAULT 1",
];
foreach ($required_unduh as $col => $def) {
    if (!in_array($col, $unduh_cols)) $pdo->exec("ALTER TABLE `unduh` ADD COLUMN `$col` $def");
}

$rps_cols = $pdo->query("SHOW COLUMNS FROM rps_kurikulum")->fetchAll(PDO::FETCH_COLUMN);
$required_rps = [
    'kode_mk' => "varchar(50) DEFAULT ''",
    'semester' => "varchar(20) DEFAULT '1'",
    'tahun_akademik' => "varchar(20) DEFAULT ''",
    'ukuran_file' => "varchar(50) DEFAULT ''",
    'jumlah_unduh' => "int(11) DEFAULT 0",
    'urutan' => "int(11) DEFAULT 1",
    'aktif' => "tinyint(1) DEFAULT 1",
];
foreach ($required_rps as $col => $def) {
    if (!in_array($col, $rps_cols)) $pdo->exec("ALTER TABLE `rps_kurikulum` ADD COLUMN `$col` $def");
}

// ===== FILE ICON HELPER =====
function file_icon($path) {
    $ext = strtolower(pathinfo($path ?? '', PATHINFO_EXTENSION));
    $map = [
        'pdf'  => ['fa-file-pdf',        '#e74c3c', 'PDF',  'pdf'],
        'doc'  => ['fa-file-word',       '#2b579a', 'DOC',  'doc'],
        'docx' => ['fa-file-word',       '#2b579a', 'DOCX', 'doc'],
        'xls'  => ['fa-file-excel',      '#217346', 'XLS',  'xls'],
        'xlsx' => ['fa-file-excel',      '#217346', 'XLSX', 'xls'],
        'ppt'  => ['fa-file-powerpoint', '#d24726', 'PPT',  'ppt'],
        'pptx' => ['fa-file-powerpoint', '#d24726', 'PPTX', 'ppt'],
        'zip'  => ['fa-file-zipper',     '#f39c12', 'ZIP',  'zip'],
        'rar'  => ['fa-file-zipper',     '#f39c12', 'RAR',  'zip'],
        'jpg'  => ['fa-file-image',      '#9b59b6', 'JPG',  'img'],
        'jpeg' => ['fa-file-image',      '#9b59b6', 'JPEG', 'img'],
        'png'  => ['fa-file-image',      '#9b59b6', 'PNG',  'img'],
    ];
    return $map[$ext] ?? ['fa-file', '#95a5a6', strtoupper($ext), 'other'];
}

// ===== HANDLE DELETE =====
if (isset($_GET['delete']) && isset($_GET['token']) && hash_equals($_SESSION['csrf_token'], $_GET['token'])) {
    $id = (int)$_GET['delete'];
    $tab = $_GET['tab'] === 'rps' ? 'rps_kurikulum' : 'unduh';
    $stmt = $pdo->prepare("SELECT file_path, judul, mata_kuliah FROM `$tab` WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if ($row && !empty($row['file_path']) && file_exists('../' . $row['file_path'])) @unlink('../' . $row['file_path']);
    $pdo->prepare("DELETE FROM `$tab` WHERE id = ?")->execute([$id]);
    
    $label = $tab === 'unduh' ? ($row['judul'] ?? '') : ($row['mata_kuliah'] ?? '');
    $pdo->prepare("INSERT INTO admin_logs (user_id, user_name, action, detail, ip) VALUES (?, ?, ?, ?, ?)")
        ->execute([$_SESSION['admin_id'], $_SESSION['admin_name'], 'DELETE_UNDUH', "Menghapus $tab: $label", $_SERVER['REMOTE_ADDR']]);
    
    header("Location: kelola_unduh.php?tab=" . ($_GET['tab'] ?? 'unduh') . "&msg=deleted");
    exit;
}

// ===== HANDLE DUPLICATE =====
if (isset($_GET['duplicate']) && isset($_GET['token']) && hash_equals($_SESSION['csrf_token'], $_GET['token'])) {
    $id = (int)$_GET['duplicate'];
    $tab = $_GET['tab'] === 'rps' ? 'rps_kurikulum' : 'unduh';
    $stmt = $pdo->prepare("SELECT * FROM `$tab` WHERE id = ?");
    $stmt->execute([$id]);
    $orig = $stmt->fetch();
    
    if ($orig) {
        if ($tab === 'unduh') {
            $pdo->prepare("INSERT INTO unduh (judul, deskripsi, kategori, file_path, ukuran_file, urutan, aktif) VALUES (?, ?, ?, ?, ?, ?, 0)")
                ->execute([$orig['judul'].' (Copy)', $orig['deskripsi'] ?? '', $orig['kategori'], $orig['file_path'], $orig['ukuran_file'] ?? '', ($orig['urutan'] ?? 0) + 1]);
        } else {
            $pdo->prepare("INSERT INTO rps_kurikulum (kode_mk, mata_kuliah, semester, tahun_akademik, file_path, ukuran_file, urutan, aktif) VALUES (?, ?, ?, ?, ?, ?, ?, 0)")
                ->execute([$orig['kode_mk'] ?? '', $orig['mata_kuliah'].' (Copy)', $orig['semester'], $orig['tahun_akademik'] ?? '', $orig['file_path'], $orig['ukuran_file'] ?? '', ($orig['urutan'] ?? 0) + 1]);
        }
    }
    header("Location: kelola_unduh.php?tab=" . ($_GET['tab'] ?? 'unduh') . "&msg=duplicated");
    exit;
}

// ===== HANDLE TOGGLE STATUS =====
if (isset($_POST['toggle_status']) && isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $id = (int)$_POST['id'];
    $aktif = (int)$_POST['aktif'];
    $tab = $_POST['tab'] === 'rps' ? 'rps_kurikulum' : 'unduh';
    $pdo->prepare("UPDATE `$tab` SET aktif = ? WHERE id = ?")->execute([$aktif, $id]);
    header("Location: kelola_unduh.php?tab=" . $_POST['tab'] . "&msg=toggled");
    exit;
}

// ===== HANDLE ADD/EDIT =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['toggle_status']) && isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $tab_type = $_POST['tab_type'] ?? 'unduh';
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $table = $tab_type === 'rps' ? 'rps_kurikulum' : 'unduh';
    
    $file_path = '';
    $upload_error = null;
    
    if (!empty($_FILES['file_path']['name'])) {
        $allowed = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip', 'rar', 'jpg', 'jpeg', 'png'];
        $upload = handle_upload($_FILES['file_path'], '../assets/uploads/dokumen/', $allowed, 10485760);
        if ($upload['success']) {
            $file_path = $upload['path'];
            if ($id > 0) {
                $stmt = $pdo->prepare("SELECT file_path FROM `$table` WHERE id = ?");
                $stmt->execute([$id]);
                $old = $stmt->fetch();
                if ($old && !empty($old['file_path']) && file_exists('../' . $old['file_path'])) @unlink('../' . $old['file_path']);
            }
        } else {
            $upload_error = $upload['error'];
        }
    }
    
    if (!$upload_error) {
        if ($tab_type === 'rps') {
            $kode_mk = sanitize($_POST['kode_mk']);
            $mata_kuliah = sanitize($_POST['mata_kuliah']);
            $semester = sanitize($_POST['semester']);
            $tahun_akademik = sanitize($_POST['tahun_akademik']);
            $ukuran_file = sanitize($_POST['ukuran_file']);
            $urutan = (int)$_POST['urutan'];
            $aktif = isset($_POST['aktif']) ? 1 : 0;
            
            if (empty($mata_kuliah)) {
                $error = "Nama mata kuliah wajib diisi.";
            } else {
                $fields = "kode_mk=:kode_mk, mata_kuliah=:mata_kuliah, semester=:semester, tahun_akademik=:tahun_akademik, ukuran_file=:ukuran_file, urutan=:urutan, aktif=:aktif";
                $params = [':kode_mk'=>$kode_mk, ':mata_kuliah'=>$mata_kuliah, ':semester'=>$semester, ':tahun_akademik'=>$tahun_akademik, ':ukuran_file'=>$ukuran_file, ':urutan'=>$urutan, ':aktif'=>$aktif];
                
                if ($file_path) { $fields .= ", file_path=:file_path"; $params[':file_path'] = $file_path; }
                
                if ($id > 0) {
                    $pdo->prepare("UPDATE `$table` SET $fields WHERE id=:id")->execute(array_merge($params, [':id'=>$id]));
                    $action = 'UPDATE_RPS';
                    $detail = "Memperbarui RPS: $mata_kuliah";
                } else {
                    $pdo->prepare("INSERT INTO `$table` SET $fields")->execute($params);
                    $action = 'ADD_RPS';
                    $detail = "Menambah RPS: $mata_kuliah";
                }
            }
        } else {
            $judul = sanitize($_POST['judul']);
            $deskripsi = sanitize($_POST['deskripsi']);
            $kategori = sanitize($_POST['kategori']);
            $ukuran_file = sanitize($_POST['ukuran_file']);
            $urutan = (int)$_POST['urutan'];
            $aktif = isset($_POST['aktif']) ? 1 : 0;
            
            if (empty($judul)) {
                $error = "Judul dokumen wajib diisi.";
            } else {
                $fields = "judul=:judul, deskripsi=:deskripsi, kategori=:kategori, ukuran_file=:ukuran_file, urutan=:urutan, aktif=:aktif";
                $params = [':judul'=>$judul, ':deskripsi'=>$deskripsi, ':kategori'=>$kategori, ':ukuran_file'=>$ukuran_file, ':urutan'=>$urutan, ':aktif'=>$aktif];
                
                if ($file_path) { $fields .= ", file_path=:file_path"; $params[':file_path'] = $file_path; }
                
                if ($id > 0) {
                    $pdo->prepare("UPDATE `$table` SET $fields WHERE id=:id")->execute(array_merge($params, [':id'=>$id]));
                    $action = 'UPDATE_UNDUH';
                    $detail = "Memperbarui dokumen: $judul";
                } else {
                    $pdo->prepare("INSERT INTO `$table` SET $fields, tanggal_upload=NOW()")->execute($params);
                    $action = 'ADD_UNDUH';
                    $detail = "Menambah dokumen: $judul";
                }
            }
        }
        
        if (!isset($error)) {
            $pdo->prepare("INSERT INTO admin_logs (user_id, user_name, action, detail, ip) VALUES (?, ?, ?, ?, ?)")
                ->execute([$_SESSION['admin_id'], $_SESSION['admin_name'], $action, $detail, $_SERVER['REMOTE_ADDR']]);
            header("Location: kelola_unduh.php?tab=$tab_type&msg=saved");
            exit;
        }
    } else {
        $error = $upload_error;
    }
}

// ===== FETCH DATA =====
$unduh_items = $pdo->query("SELECT * FROM unduh ORDER BY tanggal_upload DESC, urutan ASC")->fetchAll();
$rps_items = $pdo->query("SELECT * FROM rps_kurikulum ORDER BY semester ASC, urutan ASC, id DESC")->fetchAll();

// Normalize NULL
foreach ($unduh_items as &$it) { foreach ($it as $k => $v) { if ($v === null) $it[$k] = ''; } }
foreach ($rps_items as &$it) { foreach ($it as $k => $v) { if ($v === null) $it[$k] = ''; } }
unset($it);

// Stats
$total_docs = count($unduh_items);
$total_rps = count($rps_items);
$total_downloads = array_sum(array_column($unduh_items, 'jumlah_unduh')) + array_sum(array_column($rps_items, 'jumlah_unduh'));

// File type distribution
$file_type_counts = [];
foreach (array_merge($unduh_items, $rps_items) as $item) {
    $ext = strtolower(pathinfo($item['file_path'] ?? '', PATHINFO_EXTENSION));
    if ($ext) $file_type_counts[$ext] = ($file_type_counts[$ext] ?? 0) + 1;
}

// Category distribution (unduh)
$category_counts = [];
foreach ($unduh_items as $it) {
    $cat = $it['kategori'] ?? 'Lainnya';
    $category_counts[$cat] = ($category_counts[$cat] ?? 0) + 1;
}

// Semester distribution (rps)
$semester_counts = [];
foreach ($rps_items as $it) {
    $sem = $it['semester'] ?? '?';
    $semester_counts[$sem] = ($semester_counts[$sem] ?? 0) + 1;
}
ksort($semester_counts);

// Group by category (unduh)
$by_category = [];
foreach ($unduh_items as $it) {
    $cat = $it['kategori'] ?? 'Lainnya';
    $by_category[$cat][] = $it;
}

// Group by semester (rps)
$by_semester = [];
foreach ($rps_items as $it) {
    $sem = $it['semester'] ?? '?';
    $by_semester[$sem][] = $it;
}

// Unique values for filters
$unique_cats = array_unique(array_column($unduh_items, 'kategori'));
sort($unique_cats);

// Form data
$data_unduh = ['id'=>0, 'judul'=>'', 'deskripsi'=>'', 'kategori'=>'Akademik', 'file_path'=>'', 'ukuran_file'=>'', 'urutan'=>1, 'aktif'=>1];
$data_rps = ['id'=>0, 'kode_mk'=>'', 'mata_kuliah'=>'', 'semester'=>'1', 'tahun_akademik'=>date('Y').'/'.(date('Y')+1), 'file_path'=>'', 'ukuran_file'=>'', 'urutan'=>1, 'aktif'=>1];

$current_tab = $_GET['tab'] ?? 'unduh';
if (isset($_GET['edit']) && isset($_GET['token']) && hash_equals($_SESSION['csrf_token'], $_GET['token'])) {
    $tab_type = $_GET['tab'] === 'rps' ? 'rps_kurikulum' : 'unduh';
    $stmt = $pdo->prepare("SELECT * FROM `$tab_type` WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $fetched = $stmt->fetch();
    if ($fetched) {
        if ($_GET['tab'] === 'rps') {
            foreach ($fetched as $k => $v) { $data_rps[$k] = ($v === null) ? '' : $v; }
        } else {
            foreach ($fetched as $k => $v) { $data_unduh[$k] = ($v === null) ? '' : $v; }
        }
    }
}

// Category options
$kategori_options = ['Akademik', 'Administrasi', 'Pedoman', 'Formulir', 'Kurikulum', 'Kemahasiswaan', 'Lainnya'];
$semester_options = ['1', '2', '3', '4', '5', '6', '7', '8', 'Pendek'];

ob_start();
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
/* ===== SELECT2 DARK THEME ===== */
.select2-container--default .select2-selection--single { background-color: var(--bg-darker); border: 1px solid var(--border-dark); border-radius: var(--radius-md); height: 42px; display: flex; align-items: center; }
.select2-container--default .select2-selection--single .select2-selection__rendered { color: var(--text-main); padding-left: 14px; }
.select2-dropdown { background-color: var(--panel-bg); border: 1px solid var(--border-dark); border-radius: var(--radius-md); }
.select2-search__field { background-color: var(--bg-darker) !important; color: var(--text-main) !important; border: 1px solid var(--border-dark) !important; }
.select2-results__option { color: var(--text-main); }
.select2-results__option--highlighted[aria-selected] { background-color: var(--crimson) !important; }

/* ===== TAB NAVIGATION ===== */
.unduh-tabs {
    display: flex;
    gap: .5rem;
    margin-bottom: 1.5rem;
    background: var(--bg-darker);
    border: 1px solid var(--border-dark);
    border-radius: var(--radius-md);
    padding: .5rem;
}
.unduh-tab {
    flex: 1;
    padding: 1rem 1.5rem;
    border-radius: var(--radius-sm);
    cursor: pointer;
    transition: all .3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: .8rem;
    background: transparent;
    border: none;
    color: var(--text-muted);
    font-weight: 700;
    font-size: .95rem;
    position: relative;
}
.unduh-tab:hover { background: rgba(255,255,255,0.03); color: var(--text-main); }
.unduh-tab.active {
    background: linear-gradient(135deg, var(--crimson), var(--crimson-light));
    color: #fff;
    box-shadow: 0 6px 20px rgba(138,21,56,.3);
}
.unduh-tab.active.sky {
    background: linear-gradient(135deg, var(--sky-blue-dark), var(--sky-blue));
    box-shadow: 0 6px 20px rgba(135,206,235,.3);
}
.unduh-tab-icon {
    width: 36px; height: 36px;
    border-radius: 8px;
    background: rgba(255,255,255,0.15);
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem;
}
.unduh-tab-info { display: flex; flex-direction: column; align-items: flex-start; text-align: left; }
.unduh-tab-label { font-size: .72rem; opacity: .8; font-weight: 500; }
.unduh-tab-title { font-size: .95rem; font-weight: 800; }
.unduh-tab-count {
    background: rgba(255,255,255,0.2);
    padding: .15rem .5rem;
    border-radius: 50px;
    font-size: .7rem;
    font-weight: 800;
    margin-left: auto;
}

.tab-panel { display: none; }
.tab-panel.active { display: block; }

/* ===== STATS GRID ===== */
.unduh-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}
.unduh-stat-card {
    background: var(--panel-bg);
    border: 1px solid var(--border-dark);
    border-radius: var(--radius-md);
    padding: 1.3rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: all .3s ease;
    border-left: 4px solid var(--accent-color);
    cursor: pointer;
}
.unduh-stat-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); }
.unduh-stat-card.crimson { border-left-color: var(--crimson); }
.unduh-stat-card.sky { border-left-color: var(--sky-blue); }
.unduh-stat-card.emerald { border-left-color: var(--success); }
.unduh-stat-card.warning { border-left-color: var(--warning); }
.unduh-stat-icon {
    width: 52px; height: 52px;
    border-radius: var(--radius-md);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.4rem; flex-shrink: 0;
}
.unduh-stat-card.crimson .unduh-stat-icon { background: rgba(138,21,56,0.15); color: var(--crimson); }
.unduh-stat-card.sky .unduh-stat-icon { background: rgba(135,206,235,0.15); color: var(--sky-blue); }
.unduh-stat-card.emerald .unduh-stat-icon { background: rgba(46,139,87,0.15); color: var(--success); }
.unduh-stat-card.warning .unduh-stat-icon { background: rgba(243,156,18,0.15); color: var(--warning); }
.unduh-stat-value { font-size: 1.9rem; font-weight: 900; color: #fff; line-height: 1; }
.unduh-stat-label { color: var(--text-muted); font-size: .82rem; margin-top: .2rem; }

/* ===== DISTRIBUTION CHARTS ===== */
.distributions-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    margin-bottom: 2rem;
}
.dist-card {
    background: var(--panel-bg);
    border: 1px solid var(--border-dark);
    border-radius: var(--radius-md);
    padding: 1.3rem;
}
.dist-card-title {
    font-size: .95rem;
    font-weight: 700;
    color: #fff;
    margin-bottom: 1rem;
    display: flex; align-items: center; gap: .5rem;
}
.dist-card-title i { color: var(--crimson); }
.dist-list { display: flex; flex-direction: column; gap: .8rem; }
.dist-item { display: flex; flex-direction: column; gap: .3rem; }
.dist-header { display: flex; justify-content: space-between; align-items: center; }
.dist-label {
    display: flex; align-items: center; gap: .5rem;
    font-weight: 600; color: var(--text-main); font-size: .85rem;
}
.dist-label-icon {
    width: 28px; height: 28px;
    border-radius: 6px;
    display: flex; align-items: center; justify-content: center;
    font-size: .8rem;
    flex-shrink: 0;
}
.dist-count { font-weight: 700; font-size: .85rem; color: var(--sky-blue); }
.dist-bar { height: 6px; background: var(--bg-darker); border-radius: 50px; overflow: hidden; }
.dist-fill { height: 100%; border-radius: 50px; transition: width 1s ease; width: 0; }
.dist-fill.pdf { background: linear-gradient(90deg, #e74c3c, #c0392b); }
.dist-fill.doc { background: linear-gradient(90deg, #2b579a, #1e3a6f); }
.dist-fill.xls { background: linear-gradient(90deg, #217346, #0f4d2e); }
.dist-fill.ppt { background: linear-gradient(90deg, #d24726, #a83a1f); }
.dist-fill.zip { background: linear-gradient(90deg, #f39c12, #d68910); }
.dist-fill.other { background: linear-gradient(90deg, #95a5a6, #7f8c8d); }
.dist-fill.crimson { background: linear-gradient(90deg, var(--crimson), var(--crimson-light)); }
.dist-fill.sky { background: linear-gradient(90deg, var(--sky-blue-dark), var(--sky-blue)); }
.dist-fill.emerald { background: linear-gradient(90deg, var(--success), #27ae60); }

/* ===== FILTER BAR ===== */
.unduh-filter-bar {
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
.unduh-filter-bar .form-group { margin: 0; }
.unduh-filter-bar label { font-size: .85rem; color: var(--text-muted); margin-bottom: .4rem; display: block; font-weight: 600; }

/* ===== GROUPED TABLE ===== */
.unduh-table thead th {
    background: var(--bg-darker);
    color: var(--sky-blue);
    font-size: .78rem;
    text-transform: uppercase;
    letter-spacing: .5px;
    padding: 12px 16px;
    font-weight: 700;
    border-bottom: 1px solid var(--border-dark);
}
.cat-group-header { background: linear-gradient(90deg, rgba(138,21,56,.15), transparent); border-left: 4px solid var(--crimson); }
.cat-group-header td {
    padding: 12px 16px;
    font-weight: 800;
    color: #fff;
    font-size: .95rem;
}
.cat-group-header .cat-stats {
    float: right;
    font-size: .82rem;
    font-weight: 500;
    color: var(--text-muted);
}
.sem-group-header { background: linear-gradient(90deg, rgba(135,206,235,.12), transparent); border-left: 4px solid var(--sky-blue); }
.sem-group-header td {
    padding: 12px 16px;
    font-weight: 800;
    color: #fff;
    font-size: .95rem;
}

/* ===== FILE ICON CELL ===== */
.file-icon-cell {
    width: 50px; height: 50px;
    border-radius: 10px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
    position: relative;
    flex-shrink: 0;
}
.file-icon-cell .ext-badge-sm {
    position: absolute;
    bottom: -4px; right: -4px;
    background: var(--crimson);
    color: #fff;
    font-size: .55rem;
    font-weight: 800;
    padding: .15rem .35rem;
    border-radius: 4px;
    letter-spacing: .5px;
}
.file-title-cell { flex: 1; min-width: 0; }
.file-title-cell .file-main-title {
    font-weight: 700; color: #fff;
    font-size: .9rem; line-height: 1.3;
    margin-bottom: .2rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.file-title-cell .file-subtitle {
    color: var(--text-muted); font-size: .75rem;
    font-family: monospace;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.file-meta-cell {
    display: flex; gap: .5rem; flex-wrap: wrap;
    font-size: .75rem; color: var(--text-muted);
    margin-top: .3rem;
}
.file-meta-cell span {
    display: inline-flex; align-items: center; gap: .3rem;
}
.file-meta-cell i { color: var(--sky-blue); font-size: .7rem; }

.cat-badge {
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    padding: .2rem .6rem;
    border-radius: 50px;
    font-size: .7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .5px;
    background: rgba(138,21,56,0.15);
    color: var(--crimson);
}
.sem-badge {
    display: inline-block;
    background: linear-gradient(135deg, var(--sky-blue-dark), var(--sky-blue));
    color: #fff;
    padding: .2rem .6rem;
    border-radius: 50px;
    font-size: .7rem;
    font-weight: 800;
    letter-spacing: .5px;
}
.mk-code-badge {
    display: inline-block;
    background: rgba(138,21,56,0.1);
    color: var(--crimson);
    padding: .15rem .5rem;
    border-radius: 4px;
    font-size: .7rem;
    font-weight: 800;
    font-family: monospace;
    letter-spacing: .5px;
}

/* ===== MODERN TOGGLE SWITCH ===== */
.toggle-switch { position: relative; display: inline-block; width: 42px; height: 22px; flex-shrink: 0; }
.toggle-switch input { opacity: 0; width: 0; height: 0; }
.toggle-slider { position: absolute; cursor: pointer; inset: 0; background: var(--bg-darker); border: 1px solid var(--border-dark); transition: .3s; border-radius: 50px; }
.toggle-slider:before { position: absolute; content: ""; height: 16px; width: 16px; left: 2px; bottom: 2px; background: var(--text-muted); transition: .3s; border-radius: 50%; }
.toggle-switch input:checked + .toggle-slider { background: var(--success); border-color: var(--success); }
.toggle-switch input:checked + .toggle-slider:before { transform: translateX(20px); background: #fff; }

/* ===== UPLOAD ZONE ===== */
.upload-zone {
    border: 2px dashed var(--border-light);
    border-radius: var(--radius-md);
    padding: 2.5rem 1.5rem;
    text-align: center;
    cursor: pointer;
    transition: all .3s ease;
    background: var(--bg-darker);
    position: relative;
}
.upload-zone:hover, .upload-zone.dragover { border-color: var(--crimson); background: rgba(138,21,56,0.08); }
.upload-zone i.upload-icon { font-size: 3rem; color: var(--text-muted); margin-bottom: .8rem; display: block; transition: all .3s; }
.upload-zone:hover i.upload-icon { color: var(--crimson); transform: translateY(-4px); }
.upload-zone input[type="file"] { position: absolute; inset: 0; opacity: 0; cursor: pointer; }

.file-info-preview {
    margin-top: 1rem;
    padding: 1rem;
    background: var(--bg-darker);
    border: 1px solid var(--border-dark);
    border-radius: var(--radius-md);
    display: none;
    align-items: center;
    gap: 1rem;
}
.file-info-preview.active { display: flex; }
.file-info-preview .file-icon-big {
    width: 50px; height: 50px;
    border-radius: 10px;
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    font-size: 1.3rem;
    position: relative;
    flex-shrink: 0;
}
.file-info-preview .file-icon-big .ext-badge-sm {
    position: absolute;
    bottom: -4px; right: -4px;
    background: var(--crimson);
    color: #fff;
    font-size: .55rem;
    font-weight: 800;
    padding: .15rem .35rem;
    border-radius: 4px;
}
.file-info-preview .file-details { flex: 1; min-width: 0; }
.file-info-preview .file-name {
    font-weight: 700; color: #fff;
    font-size: .9rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.file-info-preview .file-size { color: var(--text-muted); font-size: .78rem; }
.file-info-preview .remove-file {
    background: rgba(231,76,60,0.15);
    color: var(--danger);
    border: none;
    width: 32px; height: 32px;
    border-radius: 50%;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    transition: all .2s;
}
.file-info-preview .remove-file:hover { background: var(--danger); color: #fff; }

/* ===== LIVE PREVIEW ===== */
.live-preview-wrapper {
    background: var(--bg-darker);
    border: 2px dashed var(--border-light);
    border-radius: var(--radius-md);
    padding: 1.5rem;
    position: sticky;
    top: 20px;
    align-self: start;
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

.preview-file-card {
    background: #fff;
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: 0 10px 30px rgba(0,0,0,.25);
    display: grid;
    grid-template-columns: 80px 1fr auto;
    gap: 1rem;
    align-items: center;
    color: #333;
    border-left: 4px solid var(--crimson);
}
.preview-file-card.rps { border-left-color: var(--sky-blue); }
.preview-file-icon {
    width: 70px; height: 70px;
    border-radius: 14px;
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    font-size: 2rem;
    position: relative;
    flex-shrink: 0;
}
.preview-file-icon .ext-badge {
    position: absolute;
    bottom: -6px; right: -6px;
    background: var(--crimson);
    color: #fff;
    font-size: .65rem;
    font-weight: 800;
    padding: .2rem .5rem;
    border-radius: 6px;
    letter-spacing: .5px;
}
.preview-file-info h3 {
    font-size: 1rem;
    font-weight: 700;
    color: #222;
    margin-bottom: .3rem;
    line-height: 1.3;
}
.preview-file-desc {
    color: #666;
    font-size: .8rem;
    line-height: 1.5;
    margin-bottom: .5rem;
    max-height: 3em;
    overflow: hidden;
}
.preview-file-meta {
    display: flex; flex-wrap: wrap; gap: .5rem;
    font-size: .72rem; color: #666;
}
.preview-file-meta span {
    display: inline-flex; align-items: center; gap: .3rem;
    background: #f0f0f0;
    padding: .2rem .6rem;
    border-radius: 50px;
}
.preview-file-meta i { color: var(--crimson); font-size: .65rem; }
.preview-download-btn {
    background: linear-gradient(135deg, var(--crimson), var(--crimson-light));
    color: #fff;
    padding: .6rem 1.2rem;
    border-radius: 50px;
    font-weight: 700;
    font-size: .8rem;
    text-decoration: none;
    display: flex; align-items: center; gap: .4rem;
    white-space: nowrap;
    box-shadow: 0 6px 15px rgba(138,21,56,.25);
}
.preview-download-btn.sky {
    background: linear-gradient(135deg, var(--sky-blue-dark), var(--sky-blue));
    box-shadow: 0 6px 15px rgba(135,206,235,.3);
}

/* Preview RPS Card */
.preview-rps-card {
    background: #fff;
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: 0 10px 30px rgba(0,0,0,.25);
    color: #333;
    border-top: 4px solid var(--sky-blue);
}
.preview-rps-header {
    display: flex; justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
}
.preview-rps-icon {
    width: 50px; height: 50px;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.4rem;
    position: relative;
}
.preview-rps-sem {
    background: linear-gradient(135deg, var(--sky-blue-dark), var(--sky-blue));
    color: #fff;
    padding: .3rem .8rem;
    border-radius: 50px;
    font-size: .72rem;
    font-weight: 800;
    letter-spacing: .5px;
}
.preview-rps-code {
    display: inline-block;
    background: rgba(138,21,56,0.08);
    color: var(--crimson);
    padding: .2rem .6rem;
    border-radius: 5px;
    font-size: .72rem;
    font-weight: 800;
    font-family: monospace;
    margin-bottom: .4rem;
}
.preview-rps-title {
    font-size: 1rem;
    font-weight: 700;
    color: #222;
    margin-bottom: .5rem;
    line-height: 1.3;
}
.preview-rps-meta {
    display: flex; flex-direction: column; gap: .3rem;
    font-size: .78rem; color: #666;
    margin-bottom: .8rem;
}
.preview-rps-meta span {
    display: flex; align-items: center; gap: .4rem;
}
.preview-rps-meta i { color: var(--sky-blue); width: 14px; }

/* ===== INPUT ICON ===== */
.input-icon-wrap { position: relative; }
.input-icon-wrap i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: .9rem; pointer-events: none; }
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
    font-weight: 700;
}
.form-section-title:first-child { margin-top: 0; }
.form-section-title i { color: var(--crimson); }

.info-alert {
    background: rgba(135,206,235,0.08);
    border: 1px solid rgba(135,206,235,0.25);
    border-left: 4px solid var(--sky-blue);
    border-radius: var(--radius-md);
    padding: .8rem 1rem;
    margin-bottom: 1.2rem;
    color: var(--text-main);
    font-size: .85rem;
    display: flex; align-items: flex-start; gap: .6rem;
}
.info-alert i { color: var(--sky-blue); margin-top: .15rem; flex-shrink: 0; }
.info-alert strong { color: var(--sky-blue); }

/* ===== EMPTY STATE ===== */
.unduh-empty {
    text-align: center;
    padding: 4rem 2rem;
    background: var(--panel-bg);
    border: 2px dashed var(--border-dark);
    border-radius: var(--radius-lg);
}
.unduh-empty-icon {
    width: 100px; height: 100px;
    margin: 0 auto 1.5rem;
    background: linear-gradient(135deg, var(--crimson), var(--crimson-light));
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 3rem;
    color: #fff;
    box-shadow: 0 10px 30px rgba(138,21,56,.3);
}
.unduh-empty-icon.sky { background: linear-gradient(135deg, var(--sky-blue-dark), var(--sky-blue)); box-shadow: 0 10px 30px rgba(135,206,235,.3); }
.unduh-empty h3 { color: #fff; margin-bottom: .5rem; font-size: 1.3rem; }
.unduh-empty p { color: var(--text-muted); margin-bottom: 1.5rem; max-width: 500px; margin-left: auto; margin-right: auto; }

/* ===== FORM GRID ===== */
.unduh-form-grid {
    display: grid;
    grid-template-columns: 1.4fr 1fr;
    gap: 2rem;
}
@media (max-width: 1100px) { .unduh-form-grid { grid-template-columns: 1fr; } }
@media (max-width: 992px) {
    .unduh-stats-grid { grid-template-columns: repeat(2, 1fr); }
    .distributions-row { grid-template-columns: 1fr; }
    .unduh-filter-bar { grid-template-columns: 1fr; }
    .unduh-tab-info { display: none; }
    .unduh-tab { flex-direction: column; padding: .8rem; }
}
@media (max-width: 576px) { .unduh-stats-grid { grid-template-columns: 1fr; } }
</style>

<?php if (isset($_GET['msg'])): 
    $msgs = [
        'saved' => ['success', 'Data berhasil disimpan!'],
        'deleted' => ['success', 'Data berhasil dihapus!'],
        'duplicated' => ['success', 'Data berhasil diduplikasi!'],
        'toggled' => ['success', 'Status berhasil diperbarui!']
    ];
    if (isset($msgs[$_GET['msg']])): ?>
    <script>Swal.fire({icon:'<?=$msgs[$_GET['msg']][0]?>', title:'Berhasil!', text:'<?=$msgs[$_GET['msg']][1]?>', timer:2500, showConfirmButton:false});</script>
<?php endif; endif; ?>

<?php if (isset($error)): ?>
<script>Swal.fire({icon:'error', title:'Validasi Gagal', text:'<?= addslashes($error) ?>'});</script>
<?php endif; ?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;">
    <div>
        <h2 class="section-title"><i class="fa-solid fa-cloud-arrow-down"></i> <?= isset($_GET['edit']) ? 'Edit' : 'Kelola' ?> Pusat Unduhan & RPS</h2>
        <p style="color:var(--text-muted); font-size:.88rem; margin-top:.3rem;">
            <i class="fa-solid fa-circle-info"></i> Dokumen akademik, pedoman, formulir, dan Rencana Pembelajaran Semester
        </p>
    </div>
    <div style="display:flex; gap:.8rem;">
        <?php if (isset($_GET['edit'])): ?>
        <a href="kelola_unduh.php?tab=<?= $current_tab ?>" class="btn btn-secondary"><i class="fa-solid fa-xmark"></i> Batal</a>
        <?php else: ?>
        <a href="?action=add&tab=<?= $current_tab ?>" class="btn btn-primary" id="addBtn"><i class="fa-solid fa-plus"></i> <span id="addBtnText">Tambah Dokumen</span></a>
        <a href="../unduh.php" target="_blank" class="btn btn-secondary"><i class="fa-solid fa-external-link"></i> Lihat di Website</a>
        <?php endif; ?>
    </div>
</div>

<!-- ===== TAB NAVIGATION ===== -->
<div class="unduh-tabs">
    <button class="unduh-tab <?= $current_tab === 'unduh' ? 'active' : '' ?>" data-tab="unduh" onclick="switchTab('unduh')">
        <div class="unduh-tab-icon"><i class="fa-solid fa-file-lines"></i></div>
        <div class="unduh-tab-info">
            <span class="unduh-tab-label">Dokumen</span>
            <span class="unduh-tab-title">Akademik & Pedoman</span>
        </div>
        <span class="unduh-tab-count"><?= $total_docs ?></span>
    </button>
    <button class="unduh-tab <?= $current_tab === 'rps' ? 'active sky' : '' ?>" data-tab="rps" onclick="switchTab('rps')">
        <div class="unduh-tab-icon"><i class="fa-solid fa-book-open"></i></div>
        <div class="unduh-tab-info">
            <span class="unduh-tab-label">Kurikulum</span>
            <span class="unduh-tab-title">Rencana Pembelajaran</span>
        </div>
        <span class="unduh-tab-count"><?= $total_rps ?></span>
    </button>
</div>

<!-- ===== STATS DASHBOARD ===== -->
<div class="unduh-stats-grid">
    <div class="unduh-stat-card crimson">
        <div class="unduh-stat-icon"><i class="fa-solid fa-file-lines"></i></div>
        <div>
            <div class="unduh-stat-value" data-counter="<?= $total_docs ?>">0</div>
            <div class="unduh-stat-label">Dokumen Akademik</div>
        </div>
    </div>
    <div class="unduh-stat-card sky">
        <div class="unduh-stat-icon"><i class="fa-solid fa-book-open"></i></div>
        <div>
            <div class="unduh-stat-value" data-counter="<?= $total_rps ?>">0</div>
            <div class="unduh-stat-label">RPS Tersedia</div>
        </div>
    </div>
    <div class="unduh-stat-card emerald">
        <div class="unduh-stat-icon"><i class="fa-solid fa-download"></i></div>
        <div>
            <div class="unduh-stat-value" data-counter="<?= $total_downloads ?>">0</div>
            <div class="unduh-stat-label">Total Unduhan</div>
        </div>
    </div>
    <div class="unduh-stat-card warning">
        <div class="unduh-stat-icon"><i class="fa-solid fa-folder"></i></div>
        <div>
            <div class="unduh-stat-value" data-counter="<?= count($category_counts) + count($semester_counts) ?>">0</div>
            <div class="unduh-stat-label">Kategori & Semester</div>
        </div>
    </div>
</div>

<!-- ===== DISTRIBUTION CHARTS ===== -->
<?php if ($total_docs + $total_rps > 0): ?>
<div class="distributions-row">
    <!-- File Type Distribution -->
    <div class="dist-card">
        <div class="dist-card-title">
            <i class="fa-solid fa-file"></i> Distribusi Tipe File
        </div>
        <div class="dist-list">
            <?php 
            $total_files = array_sum($file_type_counts);
            $ext_groups = [
                'pdf' => ['color'=>'pdf', 'icon'=>'fa-file-pdf', 'label'=>'PDF Document'],
                'doc' => ['color'=>'doc', 'icon'=>'fa-file-word', 'label'=>'Word Document'],
                'xls' => ['color'=>'xls', 'icon'=>'fa-file-excel', 'label'=>'Excel Spreadsheet'],
                'ppt' => ['color'=>'ppt', 'icon'=>'fa-file-powerpoint', 'label'=>'PowerPoint'],
                'zip' => ['color'=>'zip', 'icon'=>'fa-file-zipper', 'label'=>'Archive (ZIP/RAR)'],
                'img' => ['color'=>'other', 'icon'=>'fa-file-image', 'label'=>'Image'],
            ];
            $grouped_ext = [];
            foreach ($file_type_counts as $ext => $count) {
                if (in_array($ext, ['pdf'])) $grp = 'pdf';
                elseif (in_array($ext, ['doc', 'docx'])) $grp = 'doc';
                elseif (in_array($ext, ['xls', 'xlsx'])) $grp = 'xls';
                elseif (in_array($ext, ['ppt', 'pptx'])) $grp = 'ppt';
                elseif (in_array($ext, ['zip', 'rar'])) $grp = 'zip';
                elseif (in_array($ext, ['jpg', 'jpeg', 'png'])) $grp = 'img';
                else $grp = 'other';
                $grouped_ext[$grp] = ($grouped_ext[$grp] ?? 0) + $count;
            }
            foreach ($ext_groups as $grpKey => $grpInfo):
                $count = $grouped_ext[$grpKey] ?? 0;
                if ($count == 0) continue;
                $percent = $total_files > 0 ? round(($count / $total_files) * 100, 1) : 0;
            ?>
            <div class="dist-item">
                <div class="dist-header">
                    <span class="dist-label">
                        <span class="dist-label-icon" style="background:rgba(138,21,56,0.1);">
                            <i class="fa-solid <?= $grpInfo['icon'] ?>" style="color:var(--crimson);"></i>
                        </span>
                        <?= $grpInfo['label'] ?>
                    </span>
                    <span class="dist-count"><?= $count ?> file (<?= $percent ?>%)</span>
                </div>
                <div class="dist-bar">
                    <div class="dist-fill <?= $grpInfo['color'] ?>" data-target="<?= $percent ?>"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Category / Semester Distribution -->
    <div class="dist-card">
        <div class="dist-card-title">
            <i class="fa-solid fa-tags"></i> Distribusi Kategori & Semester
        </div>
        <div class="dist-list">
            <?php foreach ($category_counts as $cat => $count):
                $percent = $total_docs > 0 ? round(($count / $total_docs) * 100, 1) : 0;
            ?>
            <div class="dist-item">
                <div class="dist-header">
                    <span class="dist-label">
                        <span class="dist-label-icon" style="background:rgba(138,21,56,0.1);">
                            <i class="fa-solid fa-folder" style="color:var(--crimson);"></i>
                        </span>
                        <?= htmlspecialchars($cat) ?> <small style="color:var(--text-muted); font-size:.65rem;">(Dokumen)</small>
                    </span>
                    <span class="dist-count"><?= $count ?></span>
                </div>
                <div class="dist-bar">
                    <div class="dist-fill crimson" data-target="<?= $percent ?>"></div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php foreach ($semester_counts as $sem => $count):
                $percent = $total_rps > 0 ? round(($count / $total_rps) * 100, 1) : 0;
            ?>
            <div class="dist-item">
                <div class="dist-header">
                    <span class="dist-label">
                        <span class="dist-label-icon" style="background:rgba(135,206,235,0.15);">
                            <i class="fa-solid fa-graduation-cap" style="color:var(--sky-blue);"></i>
                        </span>
                        Semester <?= htmlspecialchars($sem) ?> <small style="color:var(--text-muted); font-size:.65rem;">(RPS)</small>
                    </span>
                    <span class="dist-count"><?= $count ?></span>
                </div>
                <div class="dist-bar">
                    <div class="dist-fill sky" data-target="<?= $percent ?>"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (isset($_GET['action']) && $_GET['action'] === 'add' || isset($_GET['edit'])): 
    $is_rps = $current_tab === 'rps';
    $data = $is_rps ? $data_rps : $data_unduh;
?>
<!-- ===== FORM ADD/EDIT ===== -->
<div class="panel">
    <div class="panel-header">
        <h3 class="panel-title">
            <i class="fa-solid fa-pen-to-square"></i> 
            <?= isset($_GET['edit']) ? 'Edit' : 'Tambah' ?> <?= $is_rps ? 'RPS' : 'Dokumen' ?>
        </h3>
    </div>
    <div class="panel-body">
        <form method="POST" enctype="multipart/form-data" id="unduhForm">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="id" value="<?= $data['id'] ?>">
            <input type="hidden" name="tab_type" value="<?= $is_rps ? 'rps' : 'unduh' ?>">
            
            <div class="unduh-form-grid">
                <div>
                    <div class="info-alert">
                        <i class="fa-solid fa-lightbulb"></i>
                        <div>
                            <strong>Tips:</strong> <?= $is_rps ? 'RPS harus dalam format PDF dan sesuai dengan kurikulum yang berlaku. Pastikan nama mata kuliah sesuai dengan database.' : 'Gunakan nama file yang deskriptif tanpa karakter khusus. Format PDF sangat direkomendasikan untuk dokumen resmi.' ?>
                        </div>
                    </div>

                    <div class="form-section-title">
                        <i class="fa-solid <?= $is_rps ? 'fa-book-open' : 'fa-heading' ?>"></i> 
                        <?= $is_rps ? 'Informasi Mata Kuliah' : 'Informasi Dokumen' ?>
                    </div>
                    
                    <?php if ($is_rps): ?>
                    <div style="display:grid; grid-template-columns: 1fr 2fr; gap:1rem;">
                        <div class="form-group">
                            <label>Kode MK</label>
                            <div class="input-icon-wrap">
                                <i class="fa-solid fa-hashtag"></i>
                                <input type="text" name="kode_mk" id="f_kode_mk" class="form-control" value="<?= htmlspecialchars((string)($data['kode_mk'] ?? '')) ?>" placeholder="ENG101" style="text-transform:uppercase; font-family:monospace; font-weight:700;">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Nama Mata Kuliah <span class="required">*</span></label>
                            <div class="input-icon-wrap">
                                <i class="fa-solid fa-book"></i>
                                <input type="text" name="mata_kuliah" id="f_mata_kuliah" class="form-control" value="<?= htmlspecialchars((string)($data['mata_kuliah'] ?? '')) ?>" required placeholder="Comprehensive English I">
                            </div>
                        </div>
                    </div>
                    
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem;">
                        <div class="form-group">
                            <label>Semester</label>
                            <select name="semester" id="f_semester" class="form-control select2-premium" style="width:100%;">
                                <?php foreach ($semester_options as $s): ?>
                                <option value="<?= $s ?>" <?= ($data['semester'] ?? '1')==$s?'selected':'' ?>>Semester <?= $s ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Tahun Akademik</label>
                            <div class="input-icon-wrap">
                                <i class="fa-regular fa-calendar"></i>
                                <input type="text" name="tahun_akademik" id="f_ta" class="form-control" value="<?= htmlspecialchars((string)($data['tahun_akademik'] ?? '')) ?>" placeholder="2026/2027">
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="form-group">
                        <label>Judul Dokumen <span class="required">*</span></label>
                        <div class="input-icon-wrap">
                            <i class="fa-solid fa-heading"></i>
                            <input type="text" name="judul" id="f_judul" class="form-control" value="<?= htmlspecialchars((string)($data['judul'] ?? '')) ?>" required placeholder="Contoh: Panduan Akademik 2026/2027">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Deskripsi (Opsional)</label>
                        <textarea name="deskripsi" id="f_deskripsi" rows="3" class="form-control" placeholder="Deskripsi singkat dokumen..."><?= htmlspecialchars((string)($data['deskripsi'] ?? '')) ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Kategori</label>
                        <select name="kategori" id="f_kategori" class="form-control select2-premium" style="width:100%;">
                            <?php foreach ($kategori_options as $kat): ?>
                            <option value="<?= $kat ?>" <?= ($data['kategori'] ?? '')==$kat?'selected':'' ?>><?= $kat ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <div class="form-section-title"><i class="fa-solid fa-cloud-arrow-up"></i> Upload File</div>
                    
                    <div class="form-group">
                        <label>File <?= $is_rps ? 'RPS' : 'Dokumen' ?> <span class="required">*</span></label>
                        <div class="upload-zone" id="dropZone">
                            <i class="fa-solid fa-cloud-arrow-up upload-icon"></i>
                            <p style="margin:0; font-weight:700; color:#fff;">Klik atau Drag & Drop file di sini</p>
                            <small style="color:var(--text-muted);">PDF, DOC, XLS, PPT, ZIP, JPG (Maks 10MB)</small>
                            <input type="file" name="file_path" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.rar,.jpg,.jpeg,.png" id="fileInput">
                        </div>
                        <div class="file-info-preview" id="fileInfo">
                            <div class="file-icon-big" id="fileIconBig" style="background:rgba(231,76,60,0.1);">
                                <i class="fa-solid fa-file-pdf" id="fileIcon" style="color:#e74c3c;"></i>
                                <span class="ext-badge-sm" id="fileExtBadge">PDF</span>
                            </div>
                            <div class="file-details">
                                <div class="file-name" id="fileName">-</div>
                                <div class="file-size" id="fileSize">-</div>
                            </div>
                            <button type="button" class="remove-file" onclick="removeSelectedFile()" title="Hapus">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </div>
                        <?php if (!empty($data['file_path'])): 
                            $icon = file_icon($data['file_path']);
                        ?>
                        <div class="file-info-preview active">
                            <div class="file-icon-big" style="background:<?= $icon[1] ?>20;">
                                <i class="fa-solid <?= $icon[0] ?>" style="color:<?= $icon[1] ?>;"></i>
                                <span class="ext-badge-sm" style="background:<?= $icon[1] ?>;"><?= $icon[2] ?></span>
                            </div>
                            <div class="file-details">
                                <div class="file-name">File saat ini: <?= htmlspecialchars(basename($data['file_path'])) ?></div>
                                <div class="file-size">Akan diganti jika upload file baru</div>
                            </div>
                            <a href="../<?= htmlspecialchars($data['file_path']) ?>" target="_blank" class="remove-file" style="background:rgba(135,206,235,0.15); color:var(--sky-blue); text-decoration:none;">
                                <i class="fa-solid fa-download"></i>
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem;">
                        <div class="form-group">
                            <label>Ukuran File</label>
                            <div class="input-icon-wrap">
                                <i class="fa-solid fa-weight-hanging"></i>
                                <input type="text" name="ukuran_file" id="f_ukuran" class="form-control" value="<?= htmlspecialchars((string)($data['ukuran_file'] ?? '')) ?>" placeholder="2.4 MB">
                            </div>
                            <small style="color:var(--text-muted);"><i class="fa-solid fa-info-circle"></i> Auto-fill saat upload</small>
                        </div>
                        <div class="form-group">
                            <label>Urutan Tampil</label>
                            <input type="number" name="urutan" class="form-control" value="<?= (int)($data['urutan'] ?? 1) ?>" min="1">
                        </div>
                    </div>

                    <div class="form-section-title"><i class="fa-solid fa-sliders"></i> Pengaturan</div>
                    
                    <div class="form-group">
                        <label class="checkbox-wrap" style="padding:1rem; background:var(--bg-darker); border-radius:var(--radius-md); border:1px solid var(--border-dark);">
                            <input type="checkbox" name="aktif" value="1" <?= ($data['aktif'] ?? 1)==1?'checked':'' ?> style="width:18px; height:18px;">
                            <span style="font-weight:600;">Tampilkan di Website</span>
                        </label>
                    </div>

                    <div style="margin-top:2rem; display:flex; gap:1rem; padding-top:1.5rem; border-top:1px solid var(--border-dark);">
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> <?= $data['id']>0?'Perbarui':'Simpan' ?> <?= $is_rps ? 'RPS' : 'Dokumen' ?></button>
                        <a href="kelola_unduh.php?tab=<?= $current_tab ?>" class="btn btn-secondary"><i class="fa-solid fa-xmark"></i> Batal</a>
                    </div>
                </div>

                <!-- LIVE PREVIEW -->
                <div class="live-preview-wrapper">
                    <div class="live-preview-title">
                        <i class="fa-solid fa-eye"></i> LIVE PREVIEW (Tampilan di Website)
                    </div>
                    
                    <?php if ($is_rps): ?>
                    <div class="preview-rps-card">
                        <div class="preview-rps-header">
                            <div class="preview-rps-icon" id="prevRpsIcon" style="background:rgba(231,76,60,0.1);">
                                <i class="fa-solid fa-file-pdf" id="prevRpsIconI" style="color:#e74c3c;"></i>
                            </div>
                            <span class="preview-rps-sem" id="prevSem">SMT 1</span>
                        </div>
                        <span class="preview-rps-code" id="prevKode">ENG101</span>
                        <h3 class="preview-rps-title" id="prevMK">Nama Mata Kuliah</h3>
                        <div class="preview-rps-meta">
                            <span><i class="fa-solid fa-calendar-days"></i> TA <span id="prevTA"><?= htmlspecialchars($data['tahun_akademik'] ?: date('Y').'/'.(date('Y')+1)) ?></span></span>
                            <span><i class="fa-solid fa-weight-hanging"></i> <span id="prevUkuran">-</span></span>
                        </div>
                        <a href="#" class="preview-download-btn sky" onclick="event.preventDefault();">
                            <i class="fa-solid fa-download"></i> Unduh RPS
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="preview-file-card">
                        <div class="preview-file-icon" id="prevIcon" style="background:rgba(231,76,60,0.1);">
                            <i class="fa-solid fa-file-pdf" id="prevIconI" style="color:#e74c3c;"></i>
                            <span class="ext-badge" id="prevExt">PDF</span>
                        </div>
                        <div class="preview-file-info">
                            <h3 id="prevJudul">Judul Dokumen</h3>
                            <p class="preview-file-desc" id="prevDesc">Deskripsi dokumen akan muncul di sini...</p>
                            <div class="preview-file-meta">
                                <span id="prevKategori"><i class="fa-solid fa-tag"></i> <span id="prevKatText"><?= htmlspecialchars($data['kategori'] ?: 'Akademik') ?></span></span>
                                <span id="prevUkuranWrap"><i class="fa-solid fa-weight-hanging"></i> <span id="prevUkuranDoc">-</span></span>
                            </div>
                        </div>
                        <a href="#" class="preview-download-btn" onclick="event.preventDefault();">
                            <i class="fa-solid fa-download"></i> Unduh
                        </a>
                    </div>
                    <?php endif; ?>
                    
                    <div style="margin-top:1rem; padding:.8rem; background:rgba(46,139,87,0.1); border:1px solid rgba(46,139,87,0.3); border-radius:var(--radius-md); font-size:.78rem; color:var(--text-muted);">
                        <i class="fa-solid fa-circle-check" style="color:var(--success);"></i>
                        <strong style="color:var(--success);"> Tip:</strong> Preview menampilkan tampilan persis seperti di halaman Unduhan website. File icon otomatis berubah sesuai ekstensi.
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const isRPS = <?= $is_rps ? 'true' : 'false' ?>;
    
    // File type map
    const fileMap = {
        'pdf': {icon:'fa-file-pdf', color:'#e74c3c', ext:'PDF', group:'pdf'},
        'doc': {icon:'fa-file-word', color:'#2b579a', ext:'DOC', group:'doc'},
        'docx': {icon:'fa-file-word', color:'#2b579a', ext:'DOCX', group:'doc'},
        'xls': {icon:'fa-file-excel', color:'#217346', ext:'XLS', group:'xls'},
        'xlsx': {icon:'fa-file-excel', color:'#217346', ext:'XLSX', group:'xls'},
        'ppt': {icon:'fa-file-powerpoint', color:'#d24726', ext:'PPT', group:'ppt'},
        'pptx': {icon:'fa-file-powerpoint', color:'#d24726', ext:'PPTX', group:'ppt'},
        'zip': {icon:'fa-file-zipper', color:'#f39c12', ext:'ZIP', group:'zip'},
        'rar': {icon:'fa-file-zipper', color:'#f39c12', ext:'RAR', group:'zip'},
        'jpg': {icon:'fa-file-image', color:'#9b59b6', ext:'JPG', group:'img'},
        'jpeg': {icon:'fa-file-image', color:'#9b59b6', ext:'JPEG', group:'img'},
        'png': {icon:'fa-file-image', color:'#9b59b6', ext:'PNG', group:'img'},
    };
    const defaultFile = {icon:'fa-file', color:'#95a5a6', ext:'FILE', group:'other'};
    
    function getFileInfo(filename) {
        const ext = filename.split('.').pop().toLowerCase();
        return fileMap[ext] || defaultFile;
    }
    
    function formatFileSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024*1024) return (bytes/1024).toFixed(1) + ' KB';
        if (bytes < 1024*1024*1024) return (bytes/(1024*1024)).toFixed(2) + ' MB';
        return (bytes/(1024*1024*1024)).toFixed(2) + ' GB';
    }
    
    function updatePreviewIcon(info, targetPrefix) {
        const iconEl = document.getElementById(targetPrefix + 'IconI');
        const extEl = document.getElementById(targetPrefix + 'Ext');
        const iconWrap = document.getElementById(targetPrefix + 'Icon') || document.getElementById(targetPrefix + 'IconBig');
        if (iconEl) iconEl.className = 'fa-solid ' + info.icon;
        if (iconEl) iconEl.style.color = info.color;
        if (extEl) extEl.textContent = info.ext;
        if (extEl) extEl.style.background = info.color;
        if (iconWrap) iconWrap.style.background = info.color + '20';
    }
    
    function updatePreview() {
        if (isRPS) {
            const mk = document.getElementById('f_mata_kuliah');
            const kode = document.getElementById('f_kode_mk');
            const sem = document.getElementById('f_semester');
            const ta = document.getElementById('f_ta');
            const ukuran = document.getElementById('f_ukuran');
            
            document.getElementById('prevMK').textContent = mk?.value || 'Nama Mata Kuliah';
            document.getElementById('prevKode').textContent = (kode?.value || 'ENG101').toUpperCase();
            document.getElementById('prevSem').textContent = 'SMT ' + (sem?.value || '1');
            document.getElementById('prevTA').textContent = ta?.value || '-';
            document.getElementById('prevUkuran').textContent = ukuran?.value || '-';
        } else {
            const judul = document.getElementById('f_judul');
            const desc = document.getElementById('f_deskripsi');
            const kategori = document.getElementById('f_kategori');
            const ukuran = document.getElementById('f_ukuran');
            
            document.getElementById('prevJudul').textContent = judul?.value || 'Judul Dokumen';
            document.getElementById('prevDesc').textContent = desc?.value || 'Deskripsi dokumen akan muncul di sini...';
            document.getElementById('prevKatText').textContent = kategori?.value || 'Akademik';
            document.getElementById('prevUkuranDoc').textContent = ukuran?.value || '-';
        }
    }
    
    // Bind events
    document.querySelectorAll('#unduhForm input, #unduhForm textarea, #unduhForm select').forEach(el => {
        el.addEventListener('input', updatePreview);
        el.addEventListener('change', updatePreview);
    });
    
    // Initial render
    updatePreview();
    
    // File upload handler
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('fileInput');
    const fileInfo = document.getElementById('fileInfo');
    const fileName = document.getElementById('fileName');
    const fileSize = document.getElementById('fileSize');
    const ukuranInput = document.getElementById('f_ukuran');
    
    function handleFile(file) {
        const info = getFileInfo(file.name);
        fileName.textContent = file.name;
        fileSize.textContent = formatFileSize(file.size);
        
        // Update icon in file info
        document.getElementById('fileIcon').className = 'fa-solid ' + info.icon;
        document.getElementById('fileIcon').style.color = info.color;
        document.getElementById('fileExtBadge').textContent = info.ext;
        document.getElementById('fileExtBadge').style.background = info.color;
        document.getElementById('fileIconBig').style.background = info.color + '20';
        
        fileInfo.classList.add('active');
        
        // Auto-fill ukuran
        if (ukuranInput && !ukuranInput.value) {
            ukuranInput.value = formatFileSize(file.size);
            updatePreview();
        }
        
        // Update preview icon
        updatePreviewIcon(info, isRPS ? 'prevRps' : 'prev');
    }
    
    if (dropZone) {
        ['dragenter', 'dragover'].forEach(evt => {
            dropZone.addEventListener(evt, e => { e.preventDefault(); dropZone.classList.add('dragover'); });
        });
        ['dragleave', 'drop'].forEach(evt => {
            dropZone.addEventListener(evt, e => { e.preventDefault(); dropZone.classList.remove('dragover'); });
        });
        dropZone.addEventListener('drop', e => {
            if (e.dataTransfer.files.length) {
                fileInput.files = e.dataTransfer.files;
                handleFile(e.dataTransfer.files[0]);
            }
        });
    }
    
    if (fileInput) {
        fileInput.addEventListener('change', e => {
            if (e.target.files.length) handleFile(e.target.files[0]);
        });
    }
    
    // Auto uppercase kode MK
    const kodeInput = document.getElementById('f_kode_mk');
    if (kodeInput) {
        kodeInput.addEventListener('input', function() {
            this.value = this.value.toUpperCase();
        });
    }
});

// Global remove function
window.removeSelectedFile = function() {
    const fileInput = document.getElementById('fileInput');
    const fileInfo = document.getElementById('fileInfo');
    if (fileInput) fileInput.value = '';
    if (fileInfo) fileInfo.classList.remove('active');
};
</script>

<?php else: ?>
<!-- ===== LIST VIEW ===== -->

<!-- UNDUH TAB CONTENT -->
<div class="tab-panel <?= $current_tab === 'unduh' ? 'active' : '' ?>" id="panel-unduh">
    <?php if (!empty($unduh_items)): ?>
    <div class="unduh-filter-bar">
        <div class="form-group">
            <label><i class="fa-solid fa-search"></i> Cari Dokumen</label>
            <input type="text" id="docSearch" class="form-control" placeholder="Ketik judul dokumen...">
        </div>
        <div class="form-group">
            <label><i class="fa-solid fa-tag"></i> Kategori</label>
            <select id="docCatFilter" class="form-control">
                <option value="all">Semua Kategori</option>
                <?php foreach ($unique_cats as $c): ?>
                <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label><i class="fa-solid fa-eye"></i> Status</label>
            <select id="docStatusFilter" class="form-control">
                <option value="all">Semua</option>
                <option value="1">Aktif</option>
                <option value="0">Nonaktif</option>
            </select>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (empty($unduh_items)): ?>
    <div class="unduh-empty">
        <div class="unduh-empty-icon"><i class="fa-solid fa-file-lines"></i></div>
        <h3>Belum Ada Dokumen Akademik</h3>
        <p>Mulai upload dokumen akademik, pedoman, formulir, dan dokumen resmi lainnya untuk diakses mahasiswa dan civitas akademika</p>
        <a href="?action=add&tab=unduh" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Upload Dokumen Pertama</a>
    </div>
    <?php else: ?>
    <div class="panel">
        <div class="panel-body no-padding">
            <div class="table-wrapper">
                <table class="unduh-table">
                    <thead>
                        <tr>
                            <th style="width:60px;">File</th>
                            <th>Dokumen</th>
                            <th style="width:120px;">Kategori</th>
                            <th style="width:90px;">Ukuran</th>
                            <th style="width:80px;">Unduh</th>
                            <th style="width:80px;">Aktif</th>
                            <th style="width:160px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="docTableBody">
                        <?php 
                        foreach ($by_category as $cat => $catItems):
                            $activeCount = count(array_filter($catItems, fn($x) => ($x['aktif'] ?? 1) == 1));
                        ?>
                        <tr class="cat-group-header" data-cat="<?= htmlspecialchars($cat) ?>">
                            <td colspan="7">
                                <i class="fa-solid fa-folder"></i> 
                                <?= htmlspecialchars($cat) ?>
                                <span class="cat-stats">
                                    <i class="fa-solid fa-file"></i> <?= count($catItems) ?> dokumen · 
                                    <i class="fa-solid fa-check-circle"></i> <?= $activeCount ?> aktif
                                </span>
                            </td>
                        </tr>
                        <?php foreach ($catItems as $item): 
                            $token = hash_hmac('sha256', $item['id'], $_SESSION['csrf_token']);
                            $icon = file_icon($item['file_path']);
                            $searchText = strtolower(($item['judul'] ?? '') . ' ' . ($item['deskripsi'] ?? ''));
                        ?>
                        <tr class="doc-row"
                             data-search="<?= htmlspecialchars($searchText) ?>"
                             data-cat="<?= htmlspecialchars($cat) ?>"
                             data-aktif="<?= $item['aktif'] ?? 1 ?>">
                            <td>
                                <div class="file-icon-cell" style="background:<?= $icon[1] ?>20;">
                                    <i class="fa-solid <?= $icon[0] ?>" style="color:<?= $icon[1] ?>;"></i>
                                    <span class="ext-badge-sm" style="background:<?= $icon[1] ?>;"><?= $icon[2] ?></span>
                                </div>
                            </td>
                            <td class="file-title-cell">
                                <div class="file-main-title"><?= htmlspecialchars($item['judul']) ?></div>
                                <?php if (!empty($item['file_path'])): ?>
                                <div class="file-subtitle" title="<?= htmlspecialchars($item['file_path']) ?>"><?= htmlspecialchars(basename($item['file_path'])) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($item['deskripsi'])): ?>
                                <div class="file-meta-cell">
                                    <span><i class="fa-regular fa-calendar"></i> <?= date('d M Y', strtotime($item['tanggal_upload'] ?? 'now')) ?></span>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td><span class="cat-badge"><?= htmlspecialchars($cat) ?></span></td>
                            <td><small><?= htmlspecialchars($item['ukuran_file'] ?: '-') ?></small></td>
                            <td>
                                <?php if (!empty($item['file_path'])): ?>
                                <a href="../<?= htmlspecialchars($item['file_path']) ?>" target="_blank" class="table-action-btn" title="Download" style="color:var(--crimson);">
                                    <i class="fa-solid fa-download"></i> <?= number_format($item['jumlah_unduh'] ?? 0) ?>x
                                </a>
                                <?php else: ?>
                                <small style="color:var(--text-muted);">-</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="toggle_status" value="1">
                                    <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                    <input type="hidden" name="tab" value="unduh">
                                    <input type="hidden" name="aktif" value="<?= ($item['aktif'] ?? 1) == 1 ? 0 : 1 ?>">
                                    <label class="toggle-switch" title="<?= ($item['aktif'] ?? 1) == 1 ? 'Sembunyikan' : 'Tampilkan' ?>">
                                        <input type="checkbox" <?= ($item['aktif'] ?? 1) == 1 ? 'checked' : '' ?> onchange="this.form.submit()">
                                        <span class="toggle-slider"></span>
                                    </label>
                                </form>
                            </td>
                            <td>
                                <div class="table-actions">
                                    <a href="?edit=<?= $item['id'] ?>&tab=unduh&token=<?= $token ?>" class="table-action-btn edit" title="Edit"><i class="fa-solid fa-pen"></i></a>
                                    <a href="?duplicate=<?= $item['id'] ?>&tab=unduh&token=<?= $token ?>" class="table-action-btn" title="Duplikasi" onclick="return confirm('Duplikasi dokumen ini?')"><i class="fa-solid fa-copy"></i></a>
                                    <a href="?delete=<?= $item['id'] ?>&tab=unduh&token=<?= $token ?>" class="table-action-btn delete" onclick="return confirm('Yakin ingin menghapus dokumen ini? File fisik akan ikut terhapus.')" title="Hapus"><i class="fa-solid fa-trash"></i></a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <div style="text-align:center; margin-top:1rem; padding:.8rem; background:rgba(138,21,56,.05); border-radius:8px; color:var(--text-muted); font-size:.88rem;">
        Menampilkan <strong id="docCount" style="color:var(--crimson);"><?= $total_docs ?></strong> dari <strong><?= $total_docs ?></strong> dokumen
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('docSearch');
        const catFilter = document.getElementById('docCatFilter');
        const statusFilter = document.getElementById('docStatusFilter');
        const countEl = document.getElementById('docCount');
        
        function filterDocs() {
            const term = (searchInput?.value || '').toLowerCase();
            const cat = catFilter?.value || 'all';
            const status = statusFilter?.value || 'all';
            
            const rows = document.querySelectorAll('.doc-row');
            const headers = document.querySelectorAll('.cat-group-header');
            let visibleCount = 0;
            
            rows.forEach(row => {
                const searchText = row.dataset.search || '';
                const rowCat = row.dataset.cat;
                const rowStatus = row.dataset.aktif;
                
                const matchSearch = !term || searchText.includes(term);
                const matchCat = cat === 'all' || rowCat === cat;
                const matchStatus = status === 'all' || rowStatus === status;
                
                if (matchSearch && matchCat && matchStatus) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            headers.forEach(header => {
                const hCat = header.dataset.cat;
                const hasVisible = Array.from(rows).some(r => r.dataset.cat === hCat && r.style.display !== 'none');
                header.style.display = (cat === 'all' || hCat === cat) && hasVisible ? '' : 'none';
            });
            
            if (countEl) countEl.textContent = visibleCount;
        }
        
        if (searchInput) searchInput.addEventListener('input', filterDocs);
        if (catFilter) catFilter.addEventListener('change', filterDocs);
        if (statusFilter) statusFilter.addEventListener('change', filterDocs);
    });
    </script>
    <?php endif; ?>
</div>

<!-- RPS TAB CONTENT -->
<div class="tab-panel <?= $current_tab === 'rps' ? 'active' : '' ?>" id="panel-rps">
    <?php if (!empty($rps_items)): ?>
    <div class="unduh-filter-bar">
        <div class="form-group">
            <label><i class="fa-solid fa-search"></i> Cari RPS</label>
            <input type="text" id="rpsSearch" class="form-control" placeholder="Ketik kode MK atau nama mata kuliah...">
        </div>
        <div class="form-group">
            <label><i class="fa-solid fa-graduation-cap"></i> Semester</label>
            <select id="rpsSemFilter" class="form-control">
                <option value="all">Semua Semester</option>
                <?php foreach (array_keys($semester_counts) as $s): ?>
                <option value="<?= $s ?>">Semester <?= $s ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label><i class="fa-solid fa-eye"></i> Status</label>
            <select id="rpsStatusFilter" class="form-control">
                <option value="all">Semua</option>
                <option value="1">Aktif</option>
                <option value="0">Nonaktif</option>
            </select>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (empty($rps_items)): ?>
    <div class="unduh-empty">
        <div class="unduh-empty-icon sky"><i class="fa-solid fa-book-open"></i></div>
        <h3>Belum Ada RPS</h3>
        <p>Mulai upload Rencana Pembelajaran Semester untuk setiap mata kuliah dalam kurikulum PBSI UMMaumere</p>
        <a href="?action=add&tab=rps" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Upload RPS Pertama</a>
    </div>
    <?php else: ?>
    <div class="panel">
        <div class="panel-body no-padding">
            <div class="table-wrapper">
                <table class="unduh-table">
                    <thead>
                        <tr>
                            <th style="width:60px;">File</th>
                            <th>Mata Kuliah</th>
                            <th style="width:80px;">Semester</th>
                            <th style="width:100px;">TA</th>
                            <th style="width:80px;">Ukuran</th>
                            <th style="width:80px;">Unduh</th>
                            <th style="width:80px;">Aktif</th>
                            <th style="width:160px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="rpsTableBody">
                        <?php 
                        foreach ($by_semester as $sem => $semItems):
                            $activeCount = count(array_filter($semItems, fn($x) => ($x['aktif'] ?? 1) == 1));
                        ?>
                        <tr class="sem-group-header" data-sem="<?= htmlspecialchars($sem) ?>">
                            <td colspan="8">
                                <i class="fa-solid fa-graduation-cap"></i> 
                                Semester <?= htmlspecialchars($sem) ?>
                                <span class="cat-stats">
                                    <i class="fa-solid fa-book"></i> <?= count($semItems) ?> RPS · 
                                    <i class="fa-solid fa-check-circle"></i> <?= $activeCount ?> aktif
                                </span>
                            </td>
                        </tr>
                        <?php foreach ($semItems as $item): 
                            $token = hash_hmac('sha256', $item['id'], $_SESSION['csrf_token']);
                            $icon = file_icon($item['file_path']);
                            $searchText = strtolower(($item['kode_mk'] ?? '') . ' ' . ($item['mata_kuliah'] ?? ''));
                        ?>
                        <tr class="rps-row"
                             data-search="<?= htmlspecialchars($searchText) ?>"
                             data-sem="<?= htmlspecialchars($sem) ?>"
                             data-aktif="<?= $item['aktif'] ?? 1 ?>">
                            <td>
                                <div class="file-icon-cell" style="background:<?= $icon[1] ?>20;">
                                    <i class="fa-solid <?= $icon[0] ?>" style="color:<?= $icon[1] ?>;"></i>
                                    <span class="ext-badge-sm" style="background:<?= $icon[1] ?>;"><?= $icon[2] ?></span>
                                </div>
                            </td>
                            <td class="file-title-cell">
                                <?php if (!empty($item['kode_mk'])): ?>
                                <span class="mk-code-badge"><?= htmlspecialchars($item['kode_mk']) ?></span>
                                <?php endif; ?>
                                <div class="file-main-title" style="margin-top:.2rem;"><?= htmlspecialchars($item['mata_kuliah']) ?></div>
                            </td>
                            <td><span class="sem-badge">SMT <?= htmlspecialchars($sem) ?></span></td>
                            <td><small><?= htmlspecialchars($item['tahun_akademik'] ?: '-') ?></small></td>
                            <td><small><?= htmlspecialchars($item['ukuran_file'] ?: '-') ?></small></td>
                            <td>
                                <?php if (!empty($item['file_path'])): ?>
                                <a href="../<?= htmlspecialchars($item['file_path']) ?>" target="_blank" class="table-action-btn" title="Download" style="color:var(--sky-blue);">
                                    <i class="fa-solid fa-download"></i> <?= number_format($item['jumlah_unduh'] ?? 0) ?>x
                                </a>
                                <?php else: ?>
                                <small style="color:var(--text-muted);">-</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="toggle_status" value="1">
                                    <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                    <input type="hidden" name="tab" value="rps">
                                    <input type="hidden" name="aktif" value="<?= ($item['aktif'] ?? 1) == 1 ? 0 : 1 ?>">
                                    <label class="toggle-switch" title="<?= ($item['aktif'] ?? 1) == 1 ? 'Sembunyikan' : 'Tampilkan' ?>">
                                        <input type="checkbox" <?= ($item['aktif'] ?? 1) == 1 ? 'checked' : '' ?> onchange="this.form.submit()">
                                        <span class="toggle-slider"></span>
                                    </label>
                                </form>
                            </td>
                            <td>
                                <div class="table-actions">
                                    <a href="?edit=<?= $item['id'] ?>&tab=rps&token=<?= $token ?>" class="table-action-btn edit" title="Edit"><i class="fa-solid fa-pen"></i></a>
                                    <a href="?duplicate=<?= $item['id'] ?>&tab=rps&token=<?= $token ?>" class="table-action-btn" title="Duplikasi" onclick="return confirm('Duplikasi RPS ini?')"><i class="fa-solid fa-copy"></i></a>
                                    <a href="?delete=<?= $item['id'] ?>&tab=rps&token=<?= $token ?>" class="table-action-btn delete" onclick="return confirm('Yakin ingin menghapus RPS ini? File fisik akan ikut terhapus.')" title="Hapus"><i class="fa-solid fa-trash"></i></a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <div style="text-align:center; margin-top:1rem; padding:.8rem; background:rgba(135,206,235,.05); border-radius:8px; color:var(--text-muted); font-size:.88rem;">
        Menampilkan <strong id="rpsCount" style="color:var(--sky-blue);"><?= $total_rps ?></strong> dari <strong><?= $total_rps ?></strong> RPS
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('rpsSearch');
        const semFilter = document.getElementById('rpsSemFilter');
        const statusFilter = document.getElementById('rpsStatusFilter');
        const countEl = document.getElementById('rpsCount');
        
        function filterRPS() {
            const term = (searchInput?.value || '').toLowerCase();
            const sem = semFilter?.value || 'all';
            const status = statusFilter?.value || 'all';
            
            const rows = document.querySelectorAll('.rps-row');
            const headers = document.querySelectorAll('.sem-group-header');
            let visibleCount = 0;
            
            rows.forEach(row => {
                const searchText = row.dataset.search || '';
                const rowSem = row.dataset.sem;
                const rowStatus = row.dataset.aktif;
                
                const matchSearch = !term || searchText.includes(term);
                const matchSem = sem === 'all' || rowSem === sem;
                const matchStatus = status === 'all' || rowStatus === status;
                
                if (matchSearch && matchSem && matchStatus) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            headers.forEach(header => {
                const hSem = header.dataset.sem;
                const hasVisible = Array.from(rows).some(r => r.dataset.sem === hSem && r.style.display !== 'none');
                header.style.display = (sem === 'all' || hSem === sem) && hasVisible ? '' : 'none';
            });
            
            if (countEl) countEl.textContent = visibleCount;
        }
        
        if (searchInput) searchInput.addEventListener('input', filterRPS);
        if (semFilter) semFilter.addEventListener('change', filterRPS);
        if (statusFilter) statusFilter.addEventListener('change', filterRPS);
    });
    </script>
    <?php endif; ?>
</div>

<script>
// Tab switching function
function switchTab(tab) {
    // Update URL tanpa reload
    const url = new URL(window.location);
    url.searchParams.set('tab', tab);
    if (!url.searchParams.has('action')) {
        url.searchParams.delete('action');
        url.searchParams.delete('edit');
        url.searchParams.delete('token');
    }
    window.history.pushState({}, '', url);
    
    // Update tab UI
    document.querySelectorAll('.unduh-tab').forEach(t => {
        t.classList.remove('active', 'sky');
        if (t.dataset.tab === tab) {
            t.classList.add('active');
            if (tab === 'rps') t.classList.add('sky');
        }
    });
    
    // Update panels
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    document.getElementById('panel-' + tab)?.classList.add('active');
    
    // Update add button text
    const addBtnText = document.getElementById('addBtnText');
    if (addBtnText) {
        addBtnText.textContent = tab === 'rps' ? 'Tambah RPS' : 'Tambah Dokumen';
    }
    const addBtn = document.getElementById('addBtn');
    if (addBtn) {
        addBtn.href = '?action=add&tab=' + tab;
    }
}

// Handle back/forward
window.addEventListener('popstate', () => {
    const url = new URL(window.location);
    const tab = url.searchParams.get('tab') || 'unduh';
    switchTab(tab);
});
</script>
<?php endif; ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    if ($('#unduhForm').length && $.fn.select2) {
        $('.select2-premium').select2({ dropdownParent: $('#unduhForm') });
    }
    
    // Animated counter
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
    
    // Animated distribution bars
    setTimeout(() => {
        document.querySelectorAll('.dist-fill').forEach(bar => {
            bar.style.width = bar.dataset.target + '%';
        });
    }, 300);
});
</script>

<?php
$page_content = ob_get_clean();
require_once 'includes/admin_template.php';
?>