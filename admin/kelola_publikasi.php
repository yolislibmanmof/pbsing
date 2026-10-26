<?php
require_once '../includes/config.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';
require_login();

$page_title = 'Kelola Publikasi Ilmiah';
$current_page = 'publikasi';
$table = 'publikasi';

// ===== AUTO-MIGRATION (Self-Healing Database) =====
$required_cols = [
    'volume'          => "varchar(20) DEFAULT ''",
    'nomor'           => "varchar(20) DEFAULT ''",
    'urutan'          => "int(11) DEFAULT 1",
    'dosen_pengusul'  => "varchar(150) DEFAULT ''",
    'aktif'           => "tinyint(1) DEFAULT 1",
];
$existing_cols = $pdo->query("SHOW COLUMNS FROM $table")->fetchAll(PDO::FETCH_COLUMN);
foreach ($required_cols as $col => $def) {
    if (!in_array($col, $existing_cols, true)) {
        $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$col` $def");
    }
}

// ===== HANDLE DELETE =====
if (isset($_GET['delete']) && isset($_GET['token']) && hash_equals(hash_hmac('sha256', (int)$_GET['delete'], $_SESSION['csrf_token']), $_GET['token'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("SELECT judul FROM $table WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    $pdo->prepare("DELETE FROM $table WHERE id = ?")->execute([$id]);
    
    $pdo->prepare("INSERT INTO admin_logs (user_id, user_name, action, detail, ip) VALUES (?, ?, 'DELETE_PUBLIKASI', ?, ?)")
        ->execute([$_SESSION['admin_id'], $_SESSION['admin_name'], 'Menghapus publikasi: ' . ($row['judul'] ?? 'ID '.$id), $_SERVER['REMOTE_ADDR']]);
    
    header("Location: kelola_publikasi.php?msg=deleted");
    exit;
}

// ===== HANDLE DUPLICATE =====
if (isset($_GET['duplicate']) && isset($_GET['token']) && hash_equals(hash_hmac('sha256', (int)$_GET['duplicate'], $_SESSION['csrf_token']), $_GET['token'])) {
    $id = (int)$_GET['duplicate'];
    $stmt = $pdo->prepare("SELECT * FROM $table WHERE id = ?");
    $stmt->execute([$id]);
    $orig = $stmt->fetch();
    if ($orig) {
        $pdo->prepare("INSERT INTO $table (judul, penulis, nama_jurnal, tahun, volume, nomor, doi, link_url, indeks, dosen_pengusul, urutan, aktif) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)")
            ->execute([
                $orig['judul'] . ' (Copy)',
                $orig['penulis'], $orig['nama_jurnal'], $orig['tahun'],
                $orig['volume'] ?? '', $orig['nomor'] ?? '',
                $orig['doi'], $orig['link_url'], $orig['indeks'],
                $orig['dosen_pengusul'] ?? '',
                ($orig['urutan'] ?? 0) + 1
            ]);
    }
    header("Location: kelola_publikasi.php?msg=duplicated");
    exit;
}

// ===== HANDLE TOGGLE STATUS =====
if (isset($_POST['toggle_status']) && isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $id = (int)$_POST['id'];
    $aktif = (int)$_POST['aktif'];
    $pdo->prepare("UPDATE $table SET aktif = ? WHERE id = ?")->execute([$aktif, $id]);
    header("Location: kelola_publikasi.php?msg=toggled");
    exit;
}

// ===== HANDLE ADD/EDIT =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['toggle_status']) && isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $judul = sanitize($_POST['judul']);
    $penulis = sanitize($_POST['penulis']);
    $nama_jurnal = sanitize($_POST['nama_jurnal']);
    $tahun = (int)$_POST['tahun'];
    $volume = sanitize($_POST['volume']);
    $nomor = sanitize($_POST['nomor']);
    $doi_raw = sanitize($_POST['doi']);
    // Auto-format DOI: strip https://doi.org/ prefix
    $doi = preg_replace('~^https?://(?:dx\.)?doi\.org/~i', '', $doi_raw);
    $link_url = sanitize($_POST['link_url']);
    $indeks = sanitize($_POST['indeks']);
    $dosen_pengusul = sanitize($_POST['dosen_pengusul']);
    $urutan = (int)$_POST['urutan'];
    $aktif = isset($_POST['aktif']) ? 1 : 0;

    // Validasi
    if (empty($judul)) {
        $error = "Judul publikasi wajib diisi.";
    } elseif ($tahun < 2000 || $tahun > date('Y') + 1) {
        $error = "Tahun terbit tidak valid.";
    } elseif (!empty($link_url) && !filter_var($link_url, FILTER_VALIDATE_URL)) {
        $error = "URL link publikasi tidak valid.";
    } else {
        $fields = "judul=:judul, penulis=:penulis, nama_jurnal=:nama_jurnal, tahun=:tahun, volume=:volume, nomor=:nomor, doi=:doi, link_url=:link_url, indeks=:indeks, dosen_pengusul=:dosen_pengusul, urutan=:urutan, aktif=:aktif";
        $params = [':judul'=>$judul, ':penulis'=>$penulis, ':nama_jurnal'=>$nama_jurnal, ':tahun'=>$tahun, ':volume'=>$volume, ':nomor'=>$nomor, ':doi'=>$doi, ':link_url'=>$link_url, ':indeks'=>$indeks, ':dosen_pengusul'=>$dosen_pengusul, ':urutan'=>$urutan, ':aktif'=>$aktif];

        if ($id > 0) {
            $pdo->prepare("UPDATE $table SET $fields WHERE id=:id")->execute(array_merge($params, [':id'=>$id]));
            $action = 'UPDATE_PUBLIKASI';
            $detail = "Memperbarui publikasi: $judul";
        } else {
            $pdo->prepare("INSERT INTO $table SET $fields")->execute($params);
            $action = 'ADD_PUBLIKASI';
            $detail = "Menambah publikasi: $judul";
        }
        
        $pdo->prepare("INSERT INTO admin_logs (user_id, user_name, action, detail, ip) VALUES (?, ?, ?, ?, ?)")
            ->execute([$_SESSION['admin_id'], $_SESSION['admin_name'], $action, $detail, $_SERVER['REMOTE_ADDR']]);
        
        header("Location: kelola_publikasi.php?msg=saved");
        exit;
    }
}

// ===== FETCH DATA =====
$items = $pdo->query("SELECT * FROM $table ORDER BY tahun DESC, urutan ASC, id DESC")->fetchAll();

// Normalize NULL
foreach ($items as &$it) {
    foreach ($it as $k => $v) {
        if ($v === null) $it[$k] = '';
    }
}
unset($it);

// Group by year
$by_year = [];
foreach ($items as $it) {
    $y = $it['tahun'] ?? date('Y');
    $by_year[$y][] = $it;
}
krsort($by_year); // Sort DESC

// Statistics
$total_pub = count($items);
$aktif_pub = count(array_filter($items, fn($i) => ($i['aktif'] ?? 1) == 1));
$total_years = count($by_year);

// Count by index type (group Scopus Q1-Q4 jadi "Scopus")
$by_index = [];
foreach ($items as $it) {
    $raw = $it['indeks'] ?? 'Lainnya';
    // Normalize ke kategori utama
    if (stripos($raw, 'scopus') !== false) $key = 'Scopus';
    elseif (stripos($raw, 'sinta') !== false) $key = 'Sinta';
    elseif (stripos($raw, 'garuda') !== false) $key = 'Garuda';
    elseif (stripos($raw, 'crossref') !== false) $key = 'CrossRef';
    else $key = 'Lainnya';
    $by_index[$key] = ($by_index[$key] ?? 0) + 1;
}

// Count by exact index
$by_exact_index = [];
foreach ($items as $it) {
    $raw = $it['indeks'] ?? 'Lainnya';
    $by_exact_index[$raw] = ($by_exact_index[$raw] ?? 0) + 1;
}

// Unique years for filter
$unique_years = array_keys($by_year);

// Index info map (semua key LOWERCASE untuk konsistensi dengan $idxKey)
$index_info = [
    'scopus'   => ['color'=>'scopus', 'icon'=>'fa-medal', 'label'=>'Scopus', 'desc'=>'Jurnal Internasional Bereputasi'],
    'sinta'    => ['color'=>'sinta', 'icon'=>'fa-award', 'label'=>'Sinta', 'desc'=>'Jurnal Nasional Terakreditasi'],
    'garuda'   => ['color'=>'garuda', 'icon'=>'fa-shield', 'label'=>'Garuda', 'desc'=>'Garba Rujukan Digital'],
    'crossref' => ['color'=>'crossref', 'icon'=>'fa-link', 'label'=>'CrossRef', 'desc'=>'DOI Registered'],
    'lainnya'  => ['color'=>'lainnya', 'icon'=>'fa-file-lines', 'label'=>'Lainnya', 'desc'=>'Publikasi Lainnya']
];

// Index options for form
$index_options = ['Scopus Q1', 'Scopus Q2', 'Scopus Q3', 'Scopus Q4', 'Sinta 1', 'Sinta 2', 'Sinta 3', 'Sinta 4', 'Garuda', 'CrossRef', 'Lainnya'];

// Form data
$data = ['id'=>0, 'judul'=>'', 'penulis'=>'', 'nama_jurnal'=>'', 'tahun'=>date('Y'), 'volume'=>'', 'nomor'=>'', 'doi'=>'', 'link_url'=>'', 'indeks'=>'Sinta 2', 'dosen_pengusul'=>'', 'urutan'=>1, 'aktif'=>1];
if (isset($_GET['edit']) && isset($_GET['token']) && hash_equals(hash_hmac('sha256', (int)$_GET['edit'], $_SESSION['csrf_token']), $_GET['token'])) {
    $stmt = $pdo->prepare("SELECT * FROM $table WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $fetched = $stmt->fetch();
    if ($fetched) {
        foreach ($fetched as $k => $v) {
            $data[$k] = ($v === null) ? '' : $v;
        }
    }
}

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

/* ===== STATS GRID ===== */
.pub-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}
.pub-stat-card {
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
.pub-stat-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); }
.pub-stat-card.crimson { border-left-color: var(--crimson); }
.pub-stat-card.scopus { border-left-color: #e74c3c; }
.pub-stat-card.sinta { border-left-color: var(--success); }
.pub-stat-card.garuda { border-left-color: var(--sky-blue); }
.pub-stat-icon {
    width: 52px; height: 52px;
    border-radius: var(--radius-md);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.4rem; flex-shrink: 0;
}
.pub-stat-card.crimson .pub-stat-icon { background: rgba(138,21,56,0.15); color: var(--crimson); }
.pub-stat-card.scopus .pub-stat-icon { background: rgba(231,76,60,0.15); color: #e74c3c; }
.pub-stat-card.sinta .pub-stat-icon { background: rgba(46,139,87,0.15); color: var(--success); }
.pub-stat-card.garuda .pub-stat-icon { background: rgba(135,206,235,0.15); color: var(--sky-blue); }
.pub-stat-value { font-size: 1.9rem; font-weight: 900; color: #fff; line-height: 1; }
.pub-stat-label { color: var(--text-muted); font-size: .82rem; margin-top: .2rem; }

/* ===== INDEX DISTRIBUTION ===== */
.index-distribution {
    background: var(--panel-bg);
    border: 1px solid var(--border-dark);
    border-radius: var(--radius-md);
    padding: 1.5rem;
    margin-bottom: 2rem;
}
.idx-dist-title {
    font-size: 1rem; font-weight: 700; color: #fff;
    margin-bottom: 1.2rem;
    display: flex; align-items: center; gap: .6rem;
}
.idx-dist-title i { color: var(--crimson); }
.idx-list { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; }
.idx-item {
    background: var(--bg-darker);
    border: 1px solid var(--border-dark);
    border-radius: var(--radius-md);
    padding: 1rem;
    cursor: pointer;
    transition: all .3s ease;
}
.idx-item:hover { border-color: var(--crimson); transform: translateY(-2px); }
.idx-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: .6rem; }
.idx-info { display: flex; align-items: center; gap: .5rem; }
.idx-icon {
    width: 32px; height: 32px;
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: .9rem;
}
.idx-icon.scopus { background: rgba(231,76,60,0.2); color: #e74c3c; }
.idx-icon.sinta { background: rgba(46,139,87,0.2); color: var(--success); }
.idx-icon.garuda { background: rgba(135,206,235,0.2); color: var(--sky-blue); }
.idx-icon.crossref { background: rgba(155,89,182,0.2); color: #9b59b6; }
.idx-icon.lainnya { background: rgba(149,165,166,0.2); color: #95a5a6; }
.idx-name { font-weight: 700; color: #fff; font-size: .9rem; }
.idx-desc { color: var(--text-muted); font-size: .7rem; }
.idx-count { font-weight: 800; font-size: .9rem; color: #fff; }
.idx-bar { height: 6px; background: var(--bg-darker); border-radius: 50px; overflow: hidden; margin-top: .4rem; }
.idx-fill { height: 100%; border-radius: 50px; transition: width 1s ease; width: 0; }
.idx-fill.scopus { background: linear-gradient(90deg, #e74c3c, #c0392b); }
.idx-fill.sinta { background: linear-gradient(90deg, var(--success), #27ae60); }
.idx-fill.garuda { background: linear-gradient(90deg, var(--sky-blue-dark), var(--sky-blue)); }
.idx-fill.crossref { background: linear-gradient(90deg, #9b59b6, #8e44ad); }
.idx-fill.lainnya { background: linear-gradient(90deg, #95a5a6, #7f8c8d); }

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
.pub-filter-bar {
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
.pub-filter-bar .form-group { margin: 0; }
.pub-filter-bar label { font-size: .85rem; color: var(--text-muted); margin-bottom: .4rem; display: block; font-weight: 600; }

/* ===== TABLE GROUPED ===== */
.pub-table thead th {
    background: var(--bg-darker);
    color: var(--sky-blue);
    font-size: .78rem;
    text-transform: uppercase;
    letter-spacing: .5px;
    padding: 12px 16px;
    font-weight: 700;
    border-bottom: 1px solid var(--border-dark);
}
.year-group-header {
    background: linear-gradient(90deg, rgba(138,21,56,.15), transparent);
    border-left: 4px solid var(--crimson);
}
.year-group-header td {
    padding: 12px 16px;
    font-weight: 800;
    color: #fff;
    font-size: .95rem;
}
.year-group-header .year-stats {
    float: right;
    font-size: .82rem;
    font-weight: 500;
    color: var(--text-muted);
}
.year-group-badge {
    display: inline-block;
    background: linear-gradient(135deg, var(--crimson), var(--crimson-light));
    color: #fff;
    padding: .25rem .9rem;
    border-radius: 50px;
    font-size: .85rem;
    font-weight: 900;
    margin-right: .8rem;
    box-shadow: 0 4px 10px rgba(138,21,56,.3);
}

/* ===== INDEX BADGES ===== */
.idx-badge {
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    padding: .25rem .7rem;
    border-radius: 50px;
    font-size: .72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .5px;
}
.idx-badge.scopus { background: rgba(231,76,60,0.15); color: #e74c3c; }
.idx-badge.sinta { background: rgba(46,139,87,0.15); color: var(--success); }
.idx-badge.garuda { background: rgba(135,206,235,0.15); color: var(--sky-blue-dark); }
.idx-badge.crossref { background: rgba(155,89,182,0.15); color: #9b59b6; }
.idx-badge.lainnya { background: rgba(149,165,166,0.15); color: #7f8c8d; }

.pub-doi-cell {
    font-family: 'Courier New', monospace;
    font-size: .78rem;
    color: var(--text-muted);
    max-width: 150px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.pub-link-cell a {
    color: var(--sky-blue);
    font-size: .85rem;
    display: inline-flex;
    align-items: center;
    gap: .3rem;
}
.pub-link-cell a:hover { color: var(--crimson); }

.pub-title-cell {
    max-width: 300px;
}
.pub-title-cell .pub-main-title {
    font-weight: 700;
    color: #fff;
    font-size: .9rem;
    line-height: 1.4;
    margin-bottom: .3rem;
}
.pub-title-cell .pub-journal-info {
    color: var(--text-muted);
    font-size: .78rem;
    font-style: italic;
}
.pub-title-cell .pub-author {
    color: var(--text-muted);
    font-size: .75rem;
    margin-top: .2rem;
}

/* ===== MODERN TOGGLE SWITCH ===== */
.toggle-switch { position: relative; display: inline-block; width: 42px; height: 22px; flex-shrink: 0; }
.toggle-switch input { opacity: 0; width: 0; height: 0; }
.toggle-slider { position: absolute; cursor: pointer; inset: 0; background: var(--bg-darker); border: 1px solid var(--border-dark); transition: .3s; border-radius: 50px; }
.toggle-slider:before { position: absolute; content: ""; height: 16px; width: 16px; left: 2px; bottom: 2px; background: var(--text-muted); transition: .3s; border-radius: 50%; }
.toggle-switch input:checked + .toggle-slider { background: var(--success); border-color: var(--success); }
.toggle-switch input:checked + .toggle-slider:before { transform: translateX(20px); background: #fff; }

/* ===== LIVE PREVIEW (Pub Item Card) ===== */
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

.preview-pub-card {
    background: #fff;
    border-radius: 14px;
    padding: 1.5rem;
    box-shadow: 0 10px 30px rgba(0,0,0,.25);
    color: #333;
    border-left: 5px solid var(--crimson);
    position: relative;
    overflow: hidden;
}
.preview-pub-header {
    display: flex;
    gap: .5rem;
    margin-bottom: .8rem;
    flex-wrap: wrap;
    align-items: center;
}
.preview-idx-badge {
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    padding: .3rem .8rem;
    border-radius: 50px;
    font-size: .7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .8px;
    background: rgba(46,139,87,0.15);
    color: var(--success);
}
.preview-idx-badge.scopus { background: rgba(231,76,60,.15); color: #e74c3c; }
.preview-idx-badge.garuda { background: rgba(135,206,235,.15); color: var(--sky-blue-dark); }
.preview-idx-badge.crossref { background: rgba(155,89,182,.15); color: #9b59b6; }
.preview-year-inline {
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    color: #666;
    font-size: .78rem;
}
.preview-pub-title {
    font-size: .95rem;
    font-weight: 700;
    color: #222;
    line-height: 1.4;
    margin-bottom: .6rem;
    min-height: 2.6em;
}
.preview-pub-authors {
    color: #666;
    font-size: .78rem;
    margin-bottom: .4rem;
    display: flex; align-items: flex-start; gap: .4rem;
}
.preview-pub-authors i { color: var(--crimson); margin-top: 2px; }
.preview-pub-journal {
    color: #666;
    font-size: .78rem;
    margin-bottom: .8rem;
    display: flex; align-items: flex-start; gap: .4rem;
}
.preview-pub-journal i { color: var(--crimson); margin-top: 2px; }
.preview-pub-journal em { font-style: italic; }
.preview-pub-actions {
    display: flex;
    gap: .4rem;
    flex-wrap: wrap;
    padding-top: .8rem;
    border-top: 1px solid #eee;
}
.preview-pub-btn {
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    padding: .35rem .8rem;
    border-radius: 50px;
    font-size: .72rem;
    font-weight: 600;
    text-decoration: none;
}
.preview-pub-btn.doi { background: rgba(231,76,60,.1); color: #e74c3c; }
.preview-pub-btn.url { background: rgba(46,139,87,.1); color: var(--success); }
.preview-pub-btn.citation { background: rgba(135,206,235,.1); color: var(--sky-blue-dark); }

.preview-citation-box {
    margin-top: .8rem;
    padding: .6rem .8rem;
    background: #f8f9fa;
    border-left: 3px solid var(--sky-blue);
    border-radius: 6px;
    font-size: .72rem;
    color: #555;
    line-height: 1.5;
    font-family: Georgia, serif;
    min-height: 40px;
}

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
.pub-empty {
    text-align: center;
    padding: 4rem 2rem;
    background: var(--panel-bg);
    border: 2px dashed var(--border-dark);
    border-radius: var(--radius-lg);
}
.pub-empty-icon {
    width: 100px; height: 100px;
    margin: 0 auto 1.5rem;
    background: linear-gradient(135deg, var(--crimson), var(--crimson-light));
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 3rem;
    color: #fff;
    box-shadow: 0 10px 30px rgba(138,21,56,.3);
}
.pub-empty h3 { color: #fff; margin-bottom: .5rem; font-size: 1.3rem; }
.pub-empty p { color: var(--text-muted); margin-bottom: 1.5rem; max-width: 500px; margin-left: auto; margin-right: auto; }

/* ===== FORM GRID ===== */
.pub-form-grid {
    display: grid;
    grid-template-columns: 1.4fr 1fr;
    gap: 2rem;
}
@media (max-width: 1100px) {
    .pub-form-grid { grid-template-columns: 1fr; }
}
@media (max-width: 992px) {
    .pub-stats-grid { grid-template-columns: repeat(2, 1fr); }
    .idx-list { grid-template-columns: 1fr; }
    .pub-filter-bar { grid-template-columns: 1fr; }
    .year-timeline { grid-template-columns: repeat(6, 1fr); }
}
@media (max-width: 576px) {
    .pub-stats-grid { grid-template-columns: 1fr; }
    .year-timeline { grid-template-columns: repeat(4, 1fr); }
}
</style>

<?php if (isset($_GET['msg'])): 
    $msgs = [
        'saved' => ['success', 'Publikasi berhasil disimpan!'],
        'deleted' => ['success', 'Publikasi berhasil dihapus!'],
        'duplicated' => ['success', 'Publikasi berhasil diduplikasi!'],
        'toggled' => ['success', 'Status publikasi diperbarui!']
    ];
    if (isset($msgs[$_GET['msg']])): ?>
    <script>Swal.fire({icon:'<?=$msgs[$_GET['msg']][0]?>', title:'Berhasil!', text:'<?=$msgs[$_GET['msg']][1]?>', timer:2500, showConfirmButton:false});</script>
<?php endif; endif; ?>

<?php if (isset($error)): ?>
<script>Swal.fire({icon:'error', title:'Validasi Gagal', text:'<?= addslashes($error) ?>'});</script>
<?php endif; ?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;">
    <div>
        <h2 class="section-title"><i class="fa-solid fa-flask"></i> <?= isset($_GET['edit']) ? 'Edit' : 'Kelola' ?> Publikasi Ilmiah</h2>
        <p style="color:var(--text-muted); font-size:.88rem; margin-top:.3rem;">
            <i class="fa-solid fa-circle-info"></i> Karya riset dosen pada jurnal bereputasi nasional dan internasional
        </p>
    </div>
    <div style="display:flex; gap:.8rem;">
        <?php if (isset($_GET['edit'])): ?>
        <a href="kelola_publikasi.php" class="btn btn-secondary"><i class="fa-solid fa-xmark"></i> Batal</a>
        <?php else: ?>
        <a href="?action=add" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Tambah Publikasi</a>
        <a href="../publikasi.php" target="_blank" class="btn btn-secondary"><i class="fa-solid fa-external-link"></i> Lihat di Website</a>
        <?php endif; ?>
    </div>
</div>

<!-- ===== STATS DASHBOARD ===== -->
<div class="pub-stats-grid">
    <div class="pub-stat-card crimson">
        <div class="pub-stat-icon"><i class="fa-solid fa-file-lines"></i></div>
        <div>
            <div class="pub-stat-value" data-counter="<?= $total_pub ?>">0</div>
            <div class="pub-stat-label">Total Publikasi</div>
        </div>
    </div>
    <div class="pub-stat-card scopus" onclick="document.getElementById('indexFilter').value='Scopus'; document.getElementById('indexFilter').dispatchEvent(new Event('change'));">
        <div class="pub-stat-icon"><i class="fa-solid fa-medal"></i></div>
        <div>
            <div class="pub-stat-value" data-counter="<?= $by_index['Scopus'] ?? 0 ?>">0</div>
            <div class="pub-stat-label">Scopus (Q1-Q4)</div>
        </div>
    </div>
    <div class="pub-stat-card sinta" onclick="document.getElementById('indexFilter').value='Sinta'; document.getElementById('indexFilter').dispatchEvent(new Event('change'));">
        <div class="pub-stat-icon"><i class="fa-solid fa-award"></i></div>
        <div>
            <div class="pub-stat-value" data-counter="<?= $by_index['Sinta'] ?? 0 ?>">0</div>
            <div class="pub-stat-label">Sinta (1-4)</div>
        </div>
    </div>
    <div class="pub-stat-card garuda" onclick="document.getElementById('indexFilter').value='Garuda'; document.getElementById('indexFilter').dispatchEvent(new Event('change'));">
        <div class="pub-stat-icon"><i class="fa-solid fa-shield"></i></div>
        <div>
            <div class="pub-stat-value" data-counter="<?= $by_index['Garuda'] ?? 0 ?>">0</div>
            <div class="pub-stat-label">Garuda</div>
        </div>
    </div>
</div>

<!-- ===== INDEX DISTRIBUTION ===== -->
<?php if (count($by_index) > 0): ?>
<div class="index-distribution">
    <div class="idx-dist-title">
        <i class="fa-solid fa-chart-pie"></i> Distribusi Publikasi per Indeksasi
    </div>
    <div class="idx-list">
        <?php foreach ($index_info as $iKey => $iInfo):
            // ✅ FIX 1: Pakai 'label' (proper case) sebagai key untuk akses $by_index
            $statsKey = $iInfo['label']; // 'Scopus', 'Sinta', 'Garuda', 'CrossRef', 'Lainnya'
            $count = $by_index[$statsKey] ?? 0;
            $percent = $total_pub > 0 ? round(($count / $total_pub) * 100, 1) : 0;
        ?>
        <div class="idx-item" onclick="document.getElementById('indexFilter').value='<?= htmlspecialchars($statsKey) ?>'; document.getElementById('indexFilter').dispatchEvent(new Event('change'));">
            <div class="idx-header">
                <div class="idx-info">
                    <div class="idx-icon <?= $iInfo['color'] ?>">
                        <i class="fa-solid <?= $iInfo['icon'] ?>"></i>
                    </div>
                    <div>
                        <!-- ✅ FIX 3: Tampilkan label proper case -->
                        <div class="idx-name"><?= htmlspecialchars($statsKey) ?></div>
                        <small class="idx-desc"><?= htmlspecialchars($iInfo['desc']) ?></small>
                    </div>
                </div>
                <div class="idx-count"><?= $count ?></div>
            </div>
            <div class="idx-bar">
                <div class="idx-fill <?= $iInfo['color'] ?>" data-target="<?= $percent ?>"></div>
            </div>
            <div style="margin-top:.4rem; font-size:.72rem; color:var(--text-muted); text-align:right;">
                <?= $percent ?>% · <?= $count ?> publikasi
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- ===== YEAR TIMELINE ===== -->
<?php if (count($by_year) > 0): ?>
<div class="panel" style="margin-bottom:2rem;">
    <div class="panel-header">
        <h3 class="panel-title"><i class="fa-solid fa-calendar-days"></i> Timeline Publikasi per Tahun</h3>
        <span style="color:var(--text-muted); font-size:.85rem;">Klik untuk filter</span>
    </div>
    <div class="panel-body">
        <div class="year-timeline">
            <?php 
            $years_sorted = array_keys($by_year);
            sort($years_sorted);
            $minYear = min($years_sorted);
            $maxYear = max($years_sorted);
            for ($y = $minYear; $y <= $maxYear; $y++):
                $count = count($by_year[$y] ?? []);
                $hasData = $count > 0;
            ?>
            <div class="year-box <?= $hasData ? 'has-data' : 'empty' ?>" onclick="document.getElementById('yearFilter').value='<?= $y ?>'; document.getElementById('yearFilter').dispatchEvent(new Event('change'));">
                <div class="year-num"><?= $y ?></div>
                <?php if ($hasData): ?>
                <div class="year-badge"><?= $count ?></div>
                <?php else: ?>
                <div class="year-count" style="font-size:.7rem; color:var(--text-muted); margin-top:.2rem;">-</div>
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
            <?= isset($_GET['edit']) ? 'Edit' : 'Tambah' ?> Publikasi Ilmiah
        </h3>
    </div>
    <div class="panel-body">
        <form method="POST" id="publikasiForm">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="id" value="<?= $data['id'] ?>">
            
            <div class="pub-form-grid">
                <!-- FORM FIELDS -->
                <div>
                    <div class="info-alert">
                        <i class="fa-solid fa-lightbulb"></i>
                        <div>
                            <strong>Tips:</strong> Untuk DOI, Anda cukup paste URL lengkap (https://doi.org/10.xxx/xxx) — sistem otomatis mengambil kode DOI-nya saja.
                        </div>
                    </div>

                    <div class="form-section-title"><i class="fa-solid fa-heading"></i> Informasi Utama</div>
                    
                    <div class="form-group">
                        <label>Judul Penelitian / Artikel <span class="required">*</span></label>
                        <div class="input-icon-wrap">
                            <i class="fa-solid fa-heading"></i>
                            <input type="text" name="judul" id="f_judul" class="form-control" value="<?= htmlspecialchars((string)($data['judul'] ?? '')) ?>" required placeholder="Judul lengkap publikasi ilmiah...">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Nama Penulis</label>
                        <div class="input-icon-wrap">
                            <i class="fa-solid fa-users"></i>
                            <input type="text" name="penulis" id="f_penulis" class="form-control" value="<?= htmlspecialchars((string)($data['penulis'] ?? '')) ?>" placeholder="Dr. John Doe, M.Hum., Jane Smith, Ph.D.">
                        </div>
                        <small style="color:var(--text-muted);"><i class="fa-solid fa-info-circle"></i> Pisahkan penulis dengan koma</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Dosen Pengusul (Internal)</label>
                        <div class="input-icon-wrap">
                            <i class="fa-solid fa-chalkboard-user"></i>
                            <input type="text" name="dosen_pengusul" class="form-control" value="<?= htmlspecialchars((string)($data['dosen_pengusul'] ?? '')) ?>" placeholder="Nama dosen PBSI yang mengusulkan">
                        </div>
                    </div>

                    <div class="form-section-title"><i class="fa-solid fa-book"></i> Jurnal & Publikasi</div>
                    
                    <div class="form-group">
                        <label>Nama Jurnal / Prosiding</label>
                        <div class="input-icon-wrap">
                            <i class="fa-solid fa-book"></i>
                            <input type="text" name="nama_jurnal" id="f_jurnal" class="form-control" value="<?= htmlspecialchars((string)($data['nama_jurnal'] ?? '')) ?>" placeholder="Journal of English Language and Literature">
                        </div>
                    </div>
                    
                    <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:1rem;">
                        <div class="form-group">
                            <label>Tahun Terbit</label>
                            <div class="input-icon-wrap">
                                <i class="fa-regular fa-calendar"></i>
                                <input type="number" name="tahun" id="f_tahun" class="form-control" value="<?= (int)($data['tahun'] ?? date('Y')) ?>" min="2000" max="<?= date('Y')+1 ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Volume</label>
                            <input type="text" name="volume" id="f_volume" class="form-control" value="<?= htmlspecialchars((string)($data['volume'] ?? '')) ?>" placeholder="10">
                        </div>
                        <div class="form-group">
                            <label>Nomor</label>
                            <input type="text" name="nomor" id="f_nomor" class="form-control" value="<?= htmlspecialchars((string)($data['nomor'] ?? '')) ?>" placeholder="2">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Indeksasi</label>
                        <select name="indeks" id="f_indeks" class="form-control select2-premium" style="width:100%;">
                            <?php foreach ($index_options as $ind): ?>
                            <option value="<?= $ind ?>" <?= ($data['indeks'] ?? '')==$ind?'selected':'' ?>><?= $ind ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-section-title"><i class="fa-solid fa-link"></i> Link & DOI</div>
                    
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem;">
                        <div class="form-group">
                            <label>DOI (Digital Object Identifier)</label>
                            <div class="input-icon-wrap">
                                <i class="fa-solid fa-fingerprint"></i>
                                <input type="text" name="doi" id="f_doi" class="form-control" value="<?= htmlspecialchars((string)($data['doi'] ?? '')) ?>" placeholder="10.xxxx/xxxxx atau URL lengkap">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Link URL Publikasi</label>
                            <div class="input-icon-wrap">
                                <i class="fa-solid fa-link"></i>
                                <input type="url" name="link_url" id="f_url" class="form-control" value="<?= htmlspecialchars((string)($data['link_url'] ?? '')) ?>" placeholder="https://journal.com/article/...">
                            </div>
                        </div>
                    </div>

                    <div class="form-section-title"><i class="fa-solid fa-sliders"></i> Pengaturan</div>
                    
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem;">
                        <div class="form-group">
                            <label>Urutan Tampil</label>
                            <input type="number" name="urutan" class="form-control" value="<?= (int)($data['urutan'] ?? 1) ?>" min="1">
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <label class="checkbox-wrap" style="padding:1rem; background:var(--bg-darker); border-radius:var(--radius-md); border:1px solid var(--border-dark); margin-top:.2rem;">
                                <input type="checkbox" name="aktif" value="1" <?= ($data['aktif'] ?? 1)==1?'checked':'' ?> style="width:18px; height:18px;">
                                <span style="font-weight:600;">Tampilkan di Website</span>
                            </label>
                        </div>
                    </div>

                    <div style="margin-top:2rem; display:flex; gap:1rem; padding-top:1.5rem; border-top:1px solid var(--border-dark);">
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> <?= $data['id']>0?'Perbarui':'Simpan' ?> Publikasi</button>
                        <a href="kelola_publikasi.php" class="btn btn-secondary"><i class="fa-solid fa-xmark"></i> Batal</a>
                    </div>
                </div>

                <!-- LIVE PREVIEW -->
                <div class="live-preview-wrapper">
                    <div class="live-preview-title">
                        <i class="fa-solid fa-eye"></i> LIVE PREVIEW (Tampilan di Website)
                    </div>
                    
                    <div class="preview-pub-card">
                        <div class="preview-pub-header">
                            <span class="preview-idx-badge" id="prevIdxBadge">
                                <i class="fa-solid fa-medal"></i>
                                <span id="prevIdx"><?= htmlspecialchars($data['indeks'] ?: 'Sinta 2') ?></span>
                            </span>
                            <span class="preview-year-inline">
                                <i class="fa-regular fa-calendar"></i>
                                <span id="prevTahun"><?= (int)($data['tahun'] ?? date('Y')) ?></span>
                            </span>
                        </div>
                        
                        <h3 class="preview-pub-title" id="prevJudul"><?= htmlspecialchars($data['judul'] ?: 'Judul Publikasi Ilmiah') ?></h3>
                        
                        <p class="preview-pub-authors" id="prevAuthorsWrap">
                            <i class="fa-solid fa-users"></i>
                            <span id="prevPenulis"><?= htmlspecialchars($data['penulis'] ?: 'Dr. John Doe, M.Hum.') ?></span>
                        </p>
                        
                        <p class="preview-pub-journal">
                            <i class="fa-solid fa-book"></i>
                            <span id="prevJournalInfo">
                                <em id="prevJurnal"><?= htmlspecialchars($data['nama_jurnal'] ?: 'Journal Name') ?></em><span id="prevVolNomor"></span>
                            </span>
                        </p>
                        
                        <div class="preview-pub-actions">
                            <span class="preview-pub-btn doi" id="prevDoiBtn" style="<?= empty($data['doi']) ? 'display:none;' : '' ?>">
                                <i class="fa-solid fa-link"></i> DOI
                            </span>
                            <span class="preview-pub-btn url" id="prevUrlBtn" style="<?= empty($data['link_url']) ? 'display:none;' : '' ?>">
                                <i class="fa-solid fa-file-pdf"></i> Full Text
                            </span>
                            <span class="preview-pub-btn citation">
                                <i class="fa-solid fa-quote-left"></i> Cite
                            </span>
                        </div>
                        
                        <div class="preview-citation-box" id="prevCitation">
                            <span id="prevCitationText"></span>
                        </div>
                    </div>
                    
                    <div style="margin-top:1rem; padding:.8rem; background:rgba(46,139,87,0.1); border:1px solid rgba(46,139,87,0.3); border-radius:var(--radius-md); font-size:.78rem; color:var(--text-muted);">
                        <i class="fa-solid fa-circle-check" style="color:var(--success);"></i>
                        <strong style="color:var(--success);"> Tip:</strong> Publikasi dengan indeks Scopus Q1/Q2 memberikan nilai tertinggi untuk akreditasi prodi.
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const fields = {
        judul: document.getElementById('f_judul'),
        penulis: document.getElementById('f_penulis'),
        jurnal: document.getElementById('f_jurnal'),
        tahun: document.getElementById('f_tahun'),
        volume: document.getElementById('f_volume'),
        nomor: document.getElementById('f_nomor'),
        indeks: document.getElementById('f_indeks'),
        doi: document.getElementById('f_doi'),
        url: document.getElementById('f_url')
    };
    
    const prevJudul = document.getElementById('prevJudul');
    const prevPenulis = document.getElementById('prevPenulis');
    const prevAuthorsWrap = document.getElementById('prevAuthorsWrap');
    const prevJurnal = document.getElementById('prevJurnal');
    const prevVolNomor = document.getElementById('prevVolNomor');
    const prevTahun = document.getElementById('prevTahun');
    const prevIdx = document.getElementById('prevIdx');
    const prevIdxBadge = document.getElementById('prevIdxBadge');
    const prevDoiBtn = document.getElementById('prevDoiBtn');
    const prevUrlBtn = document.getElementById('prevUrlBtn');
    const prevCitationText = document.getElementById('prevCitationText');
    
    function normalizeIdx(raw) {
        if (!raw) return 'lainnya';
        const lower = raw.toLowerCase();
        if (lower.includes('scopus')) return 'scopus';
        if (lower.includes('sinta')) return 'sinta';
        if (lower.includes('garuda')) return 'garuda';
        if (lower.includes('crossref')) return 'crossref';
        return 'lainnya';
    }
    
    function updateVolNomor() {
        const vol = fields.volume?.value || '';
        const nom = fields.nomor?.value || '';
        let text = '';
        if (vol) text += ', Vol. ' + vol;
        if (nom) text += ' (' + nom + ')';
        prevVolNomor.textContent = text;
    }
    
    function updateCitation() {
        const penulis = fields.penulis?.value || '';
        const tahun = fields.tahun?.value || '';
        const judul = fields.judul?.value || '';
        const jurnal = fields.jurnal?.value || '';
        const vol = fields.volume?.value || '';
        const nom = fields.nomor?.value || '';
        const doi = fields.doi?.value || '';
        
        if (!penulis && !judul) {
            prevCitationText.textContent = 'Isi data untuk melihat preview sitasi APA...';
            return;
        }
        
        let citation = '';
        if (penulis) citation += penulis;
        if (tahun) citation += ' (' + tahun + '). ';
        if (judul) citation += judul + '. ';
        if (jurnal) citation += jurnal;
        if (vol) citation += ', ' + vol;
        if (nom) citation += '(' + nom + ')';
        citation += '.';
        if (doi) {
            const cleanDoi = doi.replace(/^https?:\/\/(?:dx\.)?doi\.org\//i, '');
            citation += ' https://doi.org/' + cleanDoi;
        }
        prevCitationText.textContent = citation;
    }
    
    function updateIdxBadge() {
        const val = fields.indeks?.value || '';
        const normalized = normalizeIdx(val);
        prevIdx.textContent = val;
        prevIdxBadge.className = 'preview-idx-badge ' + normalized;
    }
    
    fields.judul?.addEventListener('input', e => {
        prevJudul.textContent = e.target.value || 'Judul Publikasi Ilmiah';
        updateCitation();
    });
    fields.penulis?.addEventListener('input', e => {
        if (e.target.value) {
            prevPenulis.textContent = e.target.value;
            prevAuthorsWrap.style.display = 'flex';
        } else {
            prevAuthorsWrap.style.display = 'none';
        }
        updateCitation();
    });
    fields.jurnal?.addEventListener('input', e => {
        prevJurnal.textContent = e.target.value || 'Journal Name';
        updateCitation();
    });
    fields.tahun?.addEventListener('input', e => {
        prevTahun.textContent = e.target.value || date('Y');
        updateCitation();
    });
    fields.volume?.addEventListener('input', () => { updateVolNomor(); updateCitation(); });
    fields.nomor?.addEventListener('input', () => { updateVolNomor(); updateCitation(); });
    fields.indeks?.addEventListener('change', updateIdxBadge);
    fields.doi?.addEventListener('input', e => {
        const val = e.target.value.trim();
        prevDoiBtn.style.display = val ? 'inline-flex' : 'none';
        updateCitation();
    });
    fields.url?.addEventListener('input', e => {
        prevUrlBtn.style.display = e.target.value ? 'inline-flex' : 'none';
    });
    
    // Initial render
    updateVolNomor();
    updateIdxBadge();
    updateCitation();
    
    // Hide author if empty
    if (!fields.penulis?.value) prevAuthorsWrap.style.display = 'none';
});
</script>

<?php else: ?>
<!-- ===== FILTER BAR ===== -->
<?php if (!empty($items)): ?>
<div class="pub-filter-bar">
    <div class="form-group">
        <label><i class="fa-solid fa-search"></i> Cari Publikasi</label>
        <input type="text" id="pubSearch" class="form-control" placeholder="Ketik judul, penulis, atau jurnal...">
    </div>
    <div class="form-group">
        <label><i class="fa-solid fa-calendar"></i> Tahun</label>
        <select id="yearFilter" class="form-control">
            <option value="all">Semua Tahun</option>
            <?php foreach ($unique_years as $y): ?>
            <option value="<?= $y ?>"><?= $y ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label><i class="fa-solid fa-medal"></i> Indeks</label>
        <select id="indexFilter" class="form-control">
            <option value="all">Semua Indeks</option>
            <?php foreach (['Scopus', 'Sinta', 'Garuda', 'CrossRef', 'Lainnya'] as $iKey): ?>
            <option value="<?= $iKey ?>"><?= $iKey ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</div>
<?php endif; ?>

<!-- ===== LIST VIEW (GROUPED BY YEAR) ===== -->
<?php if (empty($items)): ?>
<div class="pub-empty">
    <div class="pub-empty-icon"><i class="fa-solid fa-flask"></i></div>
    <h3>Belum Ada Publikasi Ilmiah</h3>
    <p>Mulai dokumentasikan karya riset dosen PBSI UMMaumere di jurnal bereputasi nasional dan internasional</p>
    <a href="?action=add" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Tambah Publikasi Pertama</a>
</div>
<?php else: ?>
<div class="panel">
    <div class="panel-body no-padding">
        <div class="table-wrapper">
            <table class="pub-table">
                <thead>
                    <tr>
                        <th style="width:90px;">Indeks</th>
                        <th>Judul & Penulis</th>
                        <th style="width:150px;">DOI/Link</th>
                        <th style="width:90px;">Status</th>
                        <th style="width:170px;">Aksi</th>
                    </tr>
                </thead>
                <tbody id="pubTableBody">
                    <?php 
                    foreach ($by_year as $year => $yearItems):
                        $activeCount = count(array_filter($yearItems, fn($x) => ($x['aktif'] ?? 1) == 1));
                        ?>
                        <tr class="year-group-header" data-year="<?= $year ?>">
                            <td colspan="5">
                                <span class="year-group-badge"><?= $year ?></span>
                                <i class="fa-solid fa-file-lines"></i> <?= count($yearItems) ?> publikasi
                                <span class="year-stats">
                                    <i class="fa-solid fa-check-circle"></i> <?= $activeCount ?> aktif
                                </span>
                            </td>
                        </tr>
                        <?php
                        foreach ($yearItems as $item): 
                            $token = hash_hmac('sha256', $item['id'], $_SESSION['csrf_token']);
                            $rawIdx = $item['indeks'] ?? 'Lainnya';
                            $idxKey = 'lainnya';
                            if (stripos($rawIdx, 'scopus') !== false) $idxKey = 'scopus';
                            elseif (stripos($rawIdx, 'sinta') !== false) $idxKey = 'sinta';
                            elseif (stripos($rawIdx, 'garuda') !== false) $idxKey = 'garuda';
                            elseif (stripos($rawIdx, 'crossref') !== false) $idxKey = 'crossref';
                            $searchText = strtolower(($item['judul'] ?? '') . ' ' . ($item['penulis'] ?? '') . ' ' . ($item['nama_jurnal'] ?? ''));
                        ?>
                        <tr class="pub-row"
                             data-search="<?= htmlspecialchars($searchText) ?>"
                             data-year="<?= $year ?>"
                             data-index="<?= htmlspecialchars($idxKey) ?>"
                             data-status="<?= $item['aktif'] ?? 1 ?>">
                            <td>
                                <span class="idx-badge <?= $idxKey ?>">
                                    <i class="fa-solid <?= $index_info[$idxKey]['icon'] ?>"></i>
                                    <?= htmlspecialchars($rawIdx) ?>
                                </span>
                            </td>
                            <td class="pub-title-cell">
                                <div class="pub-main-title"><?= htmlspecialchars($item['judul']) ?></div>
                                <?php if (!empty($item['penulis'])): ?>
                                <div class="pub-author"><i class="fa-solid fa-users" style="color:var(--crimson);"></i> <?= htmlspecialchars($item['penulis']) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($item['nama_jurnal'])): ?>
                                <div class="pub-journal-info">
                                    <i class="fa-solid fa-book" style="color:var(--sky-blue);"></i>
                                    <?= htmlspecialchars($item['nama_jurnal']) ?>
                                    <?php if (!empty($item['volume'])): ?>, Vol. <?= htmlspecialchars($item['volume']) ?><?php endif; ?>
                                    <?php if (!empty($item['nomor'])): ?> (<?= htmlspecialchars($item['nomor']) ?>)<?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td class="pub-link-cell">
                                <?php if (!empty($item['link_url'])): ?>
                                <a href="<?= htmlspecialchars($item['link_url']) ?>" target="_blank" style="margin-bottom:.3rem;">
                                    <i class="fa-solid fa-external-link-alt"></i> Full Text
                                </a><br>
                                <?php endif; ?>
                                <?php if (!empty($item['doi'])): ?>
                                <div class="pub-doi-cell" title="DOI: <?= htmlspecialchars($item['doi']) ?>">
                                    <i class="fa-solid fa-fingerprint" style="color:var(--crimson);"></i>
                                    <?= htmlspecialchars($item['doi']) ?>
                                </div>
                                <?php endif; ?>
                                <?php if (empty($item['link_url']) && empty($item['doi'])): ?>
                                <small style="color:var(--text-muted);">-</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="toggle_status" value="1">
                                    <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                    <input type="hidden" name="aktif" value="<?= ($item['aktif'] ?? 1) == 1 ? 0 : 1 ?>">
                                    <label class="toggle-switch" title="<?= ($item['aktif'] ?? 1) == 1 ? 'Sembunyikan' : 'Tampilkan' ?>">
                                        <input type="checkbox" <?= ($item['aktif'] ?? 1) == 1 ? 'checked' : '' ?> onchange="this.form.submit()">
                                        <span class="toggle-slider"></span>
                                    </label>
                                </form>
                            </td>
                            <td>
                                <div class="table-actions">
                                    <a href="?edit=<?= $item['id'] ?>&token=<?= $token ?>" class="table-action-btn edit" title="Edit"><i class="fa-solid fa-pen"></i></a>
                                    <a href="?duplicate=<?= $item['id'] ?>&token=<?= $token ?>" class="table-action-btn" title="Duplikasi" onclick="return confirm('Duplikasi publikasi ini?')"><i class="fa-solid fa-copy"></i></a>
                                    <a href="?delete=<?= $item['id'] ?>&token=<?= $token ?>" class="table-action-btn delete" onclick="return confirm('Yakin ingin menghapus publikasi ini?')" title="Hapus"><i class="fa-solid fa-trash"></i></a>
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
    Menampilkan <strong id="pubCount" style="color:var(--crimson);"><?= $total_pub ?></strong> dari <strong><?= $total_pub ?></strong> publikasi di <strong><?= $total_years ?></strong> tahun
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('pubSearch');
    const yearFilter = document.getElementById('yearFilter');
    const indexFilter = document.getElementById('indexFilter');
    const countEl = document.getElementById('pubCount');
    
    function filterPub() {
        const term = (searchInput?.value || '').toLowerCase();
        const year = yearFilter?.value || 'all';
        const index = indexFilter?.value || 'all';
        
        const rows = document.querySelectorAll('.pub-row');
        const headers = document.querySelectorAll('.year-group-header');
        let visibleCount = 0;
        
        rows.forEach(row => {
            const searchText = row.dataset.search || '';
            const rowYear = row.dataset.year;
            const rowIndex = row.dataset.index;
            const rowStatus = row.dataset.status;
            
            const matchSearch = !term || searchText.includes(term);
            const matchYear = year === 'all' || rowYear === year;
            const matchIndex = index === 'all' || rowIndex === index;
            
            if (matchSearch && matchYear && matchIndex) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        
        // Hide year headers tanpa row visible
        headers.forEach(header => {
            const hYear = header.dataset.year;
            const hasVisible = Array.from(rows).some(r => r.dataset.year === hYear && r.style.display !== 'none');
            header.style.display = (year === 'all' || hYear === year) && hasVisible ? '' : 'none';
        });
        
        if (countEl) countEl.textContent = visibleCount;
    }
    
    if (searchInput) searchInput.addEventListener('input', filterPub);
    if (yearFilter) yearFilter.addEventListener('change', filterPub);
    if (indexFilter) indexFilter.addEventListener('change', filterPub);
});
</script>
<?php endif; ?>
<?php endif; ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    if ($('#publikasiForm').length && $.fn.select2) {
        $('.select2-premium').select2({ dropdownParent: $('#publikasiForm') });
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
    
    // Animated index bars
    setTimeout(() => {
        document.querySelectorAll('.idx-fill').forEach(bar => {
            bar.style.width = bar.dataset.target + '%';
        });
    }, 300);
});
</script>

<?php
$page_content = ob_get_clean();
require_once 'includes/admin_template.php';
?>