<?php
require_once '../includes/config.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';
require_login();

$page_title = 'Kelola Visi, Misi, Tujuan & Nilai';
$current_page = 'visimisi';
$table = 'visi_misi';

// ===== AUTO-MIGRATION =====
$required_cols = [
    'judul'  => "varchar(255) DEFAULT ''",
    'isi'    => "text DEFAULT NULL",
    'ikon'   => "varchar(100) DEFAULT 'fa-solid fa-star'",
    'urutan' => "int(11) DEFAULT 1",
    'aktif'  => "tinyint(1) DEFAULT 1",
    'tipe'   => "varchar(20) DEFAULT 'misi'",
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
    $stmt = $pdo->prepare("SELECT judul, tipe FROM $table WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    $pdo->prepare("DELETE FROM $table WHERE id = ?")->execute([$id]);
    
    $pdo->prepare("INSERT INTO admin_logs (user_id, user_name, action, detail, ip) VALUES (?, ?, 'DELETE_VISIMISI', ?, ?)")
        ->execute([$_SESSION['admin_id'], $_SESSION['admin_name'], 'Menghapus ' . ($row['tipe'] ?? 'item') . ': ' . ($row['judul'] ?? strip_tags(substr($row['isi'] ?? '', 0, 40)) ?? 'ID '.$id), $_SERVER['REMOTE_ADDR']]);
    
    header("Location: kelola_visimisi.php?msg=deleted");
    exit;
}

// ===== HANDLE DUPLICATE =====
if (isset($_GET['duplicate']) && isset($_GET['token']) && hash_equals(hash_hmac('sha256', (int)$_GET['duplicate'], $_SESSION['csrf_token']), $_GET['token'])) {
    $id = (int)$_GET['duplicate'];
    $stmt = $pdo->prepare("SELECT * FROM $table WHERE id = ?");
    $stmt->execute([$id]);
    $orig = $stmt->fetch();
    if ($orig) {
        $pdo->prepare("INSERT INTO $table (tipe, judul, isi, ikon, urutan, aktif) VALUES (?, ?, ?, ?, ?, 0)")
            ->execute([
                $orig['tipe'],
                ($orig['judul'] ? $orig['judul'] . ' (Copy)' : ''),
                $orig['isi'],
                $orig['ikon'],
                ($orig['urutan'] ?? 0) + 1
            ]);
    }
    header("Location: kelola_visimisi.php?msg=duplicated");
    exit;
}

// ===== HANDLE TOGGLE STATUS =====
if (isset($_POST['toggle_status']) && isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $id = (int)$_POST['id'];
    $aktif = (int)$_POST['aktif'];
    $pdo->prepare("UPDATE $table SET aktif = ? WHERE id = ?")->execute([$aktif, $id]);
    header("Location: kelola_visimisi.php?msg=toggled");
    exit;
}

// ===== HANDLE ADD/EDIT =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['toggle_status']) && isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $tipe = sanitize($_POST['tipe']);
    $judul = sanitize($_POST['judul']);
    $isi = $_POST['isi'] ?? '';
    $ikon = sanitize($_POST['ikon']);
    $urutan = (int)$_POST['urutan'];
    $aktif = isset($_POST['aktif']) ? 1 : 0;

    // Validasi
    if (!in_array($tipe, ['visi', 'misi', 'tujuan', 'nilai'])) {
        $error = "Tipe tidak valid.";
    } elseif (empty($isi)) {
        $error = "Isi konten wajib diisi.";
    } elseif ($tipe !== 'visi' && empty($judul)) {
        $error = "Judul wajib diisi untuk Misi, Tujuan, dan Nilai.";
    } else {
        $fields = "tipe=:tipe, judul=:judul, isi=:isi, ikon=:ikon, urutan=:urutan, aktif=:aktif";
        $params = [':tipe'=>$tipe, ':judul'=>$judul, ':isi'=>$isi, ':ikon'=>$ikon, ':urutan'=>$urutan, ':aktif'=>$aktif];

        if ($id > 0) {
            $pdo->prepare("UPDATE $table SET $fields WHERE id=:id")->execute(array_merge($params, [':id'=>$id]));
            $action = 'UPDATE_VISIMISI';
            $detail = "Memperbarui $tipe: " . ($judul ?: substr(strip_tags($isi), 0, 40));
        } else {
            $pdo->prepare("INSERT INTO $table SET $fields")->execute($params);
            $action = 'ADD_VISIMISI';
            $detail = "Menambah $tipe baru: " . ($judul ?: substr(strip_tags($isi), 0, 40));
        }
        
        $pdo->prepare("INSERT INTO admin_logs (user_id, user_name, action, detail, ip) VALUES (?, ?, ?, ?, ?)")
            ->execute([$_SESSION['admin_id'], $_SESSION['admin_name'], $action, $detail, $_SERVER['REMOTE_ADDR']]);
        
        header("Location: kelola_visimisi.php?msg=saved");
        exit;
    }
}

// ===== FETCH DATA =====
$items = $pdo->query("SELECT * FROM $table ORDER BY tipe ASC, urutan ASC, id DESC")->fetchAll();

// Normalize NULL
foreach ($items as &$it) {
    foreach ($it as $k => $v) {
        if ($v === null) $it[$k] = '';
    }
}
unset($it);

// Group by type
$by_type = ['visi'=>[], 'misi'=>[], 'tujuan'=>[], 'nilai'=>[]];
foreach ($items as $it) {
    $t = $it['tipe'] ?? 'misi';
    if (isset($by_type[$t])) $by_type[$t][] = $it;
}

// Statistics
$count = [
    'visi'   => count($by_type['visi']),
    'misi'   => count($by_type['misi']),
    'tujuan' => count($by_type['tujuan']),
    'nilai'  => count($by_type['nilai']),
];
$total_items = array_sum($count);

// Type info map
$type_info = [
    'visi'   => ['label'=>'Visi', 'color'=>'crimson', 'icon'=>'fa-eye', 'desc'=>'Arah besar prodi'],
    'misi'   => ['label'=>'Misi', 'color'=>'sky', 'icon'=>'fa-bullseye', 'desc'=>'Komitmen utama'],
    'tujuan' => ['label'=>'Tujuan', 'color'=>'emerald', 'icon'=>'fa-flag-checkered', 'desc'=>'Target terukur'],
    'nilai'  => ['label'=>'Nilai', 'color'=>'warning', 'icon'=>'fa-heart', 'desc'=>'Karakter lulusan']
];

// Icon picker set (20+ relevant icons)
$icon_set = [
    ['class'=>'fa-solid fa-eye', 'label'=>'Vision'],
    ['class'=>'fa-solid fa-bullseye', 'label'=>'Target'],
    ['class'=>'fa-solid fa-star', 'label'=>'Star'],
    ['class'=>'fa-solid fa-heart', 'label'=>'Heart'],
    ['class'=>'fa-solid fa-graduation-cap', 'label'=>'Grad'],
    ['class'=>'fa-solid fa-book-open', 'label'=>'Book'],
    ['class'=>'fa-solid fa-lightbulb', 'label'=>'Idea'],
    ['class'=>'fa-solid fa-rocket', 'label'=>'Rocket'],
    ['class'=>'fa-solid fa-globe', 'label'=>'Global'],
    ['class'=>'fa-solid fa-handshake', 'label'=>'Partner'],
    ['class'=>'fa-solid fa-award', 'label'=>'Award'],
    ['class'=>'fa-solid fa-users', 'label'=>'People'],
    ['class'=>'fa-solid fa-flask', 'label'=>'Research'],
    ['class'=>'fa-solid fa-pen-fancy', 'label'=>'Creative'],
    ['class'=>'fa-solid fa-language', 'label'=>'Language'],
    ['class'=>'fa-solid fa-brain', 'label'=>'Brain'],
    ['class'=>'fa-solid fa-seedling', 'label'=>'Growth'],
    ['class'=>'fa-solid fa-shield-halved', 'label'=>'Integrity'],
    ['class'=>'fa-solid fa-scale-balanced', 'label'=>'Justice'],
    ['class'=>'fa-solid fa-hands-holding-child', 'label'=>'Care'],
    ['class'=>'fa-solid fa-flag-checkered', 'label'=>'Goal'],
    ['class'=>'fa-solid fa-compass', 'label'=>'Direction'],
    ['class'=>'fa-solid fa-mountain-sun', 'label'=>'Peak'],
    ['class'=>'fa-solid fa-infinity', 'label'=>'Infinite']
];

// Form data
$data = ['id'=>0, 'tipe'=>'misi', 'judul'=>'', 'isi'=>'', 'ikon'=>'fa-solid fa-bullseye', 'urutan'=>1, 'aktif'=>1];
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
<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<style>
/* ===== SELECT2 DARK THEME ===== */
.select2-container--default .select2-selection--single { background-color: var(--bg-darker); border: 1px solid var(--border-dark); border-radius: var(--radius-md); height: 42px; display: flex; align-items: center; }
.select2-container--default .select2-selection--single .select2-selection__rendered { color: var(--text-main); padding-left: 14px; }
.select2-dropdown { background-color: var(--panel-bg); border: 1px solid var(--border-dark); border-radius: var(--radius-md); }
.select2-search__field { background-color: var(--bg-darker) !important; color: var(--text-main) !important; border: 1px solid var(--border-dark) !important; }
.select2-results__option { color: var(--text-main); }
.select2-results__option--highlighted[aria-selected] { background-color: var(--crimson) !important; }

/* ===== QUILL DARK THEME ===== */
.ql-toolbar { border: 1px solid var(--border-dark) !important; border-radius: var(--radius-md) var(--radius-md) 0 0 !important; background: var(--bg-darker); }
.ql-container { border: 1px solid var(--border-dark) !important; border-radius: 0 0 var(--radius-md) var(--radius-md) !important; background: var(--bg-darker); color: var(--text-main); font-family: inherit; }
.ql-editor { min-height: 180px; color: var(--text-main); }
.ql-stroke { stroke: var(--text-muted) !important; }
.ql-fill { fill: var(--text-muted) !important; }
.ql-picker { color: var(--text-muted) !important; }
.ql-picker-label:hover, .ql-picker-label.ql-active, .ql-toolbar button:hover, .ql-toolbar button.ql-active { color: var(--crimson) !important; }
.ql-toolbar button:hover .ql-stroke, .ql-toolbar button.ql-active .ql-stroke { stroke: var(--crimson) !important; }
.ql-toolbar button:hover .ql-fill, .ql-toolbar button.ql-active .ql-fill { fill: var(--crimson) !important; }

/* ===== STATS GRID ===== */
.vm-stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
    margin-bottom: 2rem;
}
.vm-stat-card {
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
.vm-stat-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); }
.vm-stat-card.crimson { border-left-color: var(--crimson); }
.vm-stat-card.sky { border-left-color: var(--sky-blue); }
.vm-stat-card.emerald { border-left-color: var(--success); }
.vm-stat-card.warning { border-left-color: var(--warning); }
.vm-stat-icon {
    width: 52px; height: 52px;
    border-radius: var(--radius-md);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.4rem; flex-shrink: 0;
}
.vm-stat-card.crimson .vm-stat-icon { background: rgba(138,21,56,0.15); color: var(--crimson); }
.vm-stat-card.sky .vm-stat-icon { background: rgba(135,206,235,0.15); color: var(--sky-blue); }
.vm-stat-card.emerald .vm-stat-icon { background: rgba(46,139,87,0.15); color: var(--success); }
.vm-stat-card.warning .vm-stat-icon { background: rgba(243,156,18,0.15); color: var(--warning); }
.vm-stat-value { font-size: 1.9rem; font-weight: 900; color: #fff; line-height: 1; }
.vm-stat-label { color: var(--text-muted); font-size: .82rem; margin-top: .2rem; }
.vm-stat-desc { color: var(--text-muted); font-size: .7rem; opacity: .7; }

/* ===== TYPE DISTRIBUTION ===== */
.type-distribution {
    background: var(--panel-bg);
    border: 1px solid var(--border-dark);
    border-radius: var(--radius-md);
    padding: 1.5rem;
    margin-bottom: 2rem;
}
.type-dist-title {
    font-size: 1rem; font-weight: 700; color: #fff;
    margin-bottom: 1.2rem;
    display: flex; align-items: center; gap: .6rem;
}
.type-dist-title i { color: var(--crimson); }
.type-list { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; }
.type-item {
    background: var(--bg-darker);
    border: 1px solid var(--border-dark);
    border-radius: var(--radius-md);
    padding: 1rem;
    cursor: pointer;
    transition: all .3s ease;
    text-align: center;
}
.type-item:hover { border-color: var(--crimson); transform: translateY(-2px); }
.type-item.active { border-color: var(--crimson); background: rgba(138,21,56,0.1); }
.type-item-icon {
    width: 48px; height: 48px;
    border-radius: 50%;
    margin: 0 auto .8rem;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.2rem;
}
.type-item.crimson .type-item-icon { background: rgba(138,21,56,0.2); color: var(--crimson); }
.type-item.sky .type-item-icon { background: rgba(135,206,235,0.2); color: var(--sky-blue); }
.type-item.emerald .type-item-icon { background: rgba(46,139,87,0.2); color: var(--success); }
.type-item.warning .type-item-icon { background: rgba(243,156,18,0.2); color: var(--warning); }
.type-item-label { font-weight: 700; color: #fff; font-size: .95rem; margin-bottom: .2rem; }
.type-item-count {
    font-size: 1.5rem; font-weight: 900; color: #fff;
    margin: .3rem 0;
}
.type-item-bar { height: 4px; background: var(--bg-darker); border-radius: 50px; overflow: hidden; margin-top: .6rem; }
.type-item-fill {
    height: 100%; border-radius: 50px;
    transition: width 1s ease;
    width: 0;
}
.type-item.crimson .type-item-fill { background: var(--crimson); }
.type-item.sky .type-item-fill { background: var(--sky-blue); }
.type-item.emerald .type-item-fill { background: var(--success); }
.type-item.warning .type-item-fill { background: var(--warning); }

/* ===== ICON PICKER ===== */
.icon-picker-grid {
    display: grid;
    grid-template-columns: repeat(8, 1fr);
    gap: .4rem;
    max-height: 200px;
    overflow-y: auto;
    padding: .8rem;
    background: var(--bg-darker);
    border: 1px solid var(--border-dark);
    border-radius: var(--radius-md);
}
.icon-picker-grid::-webkit-scrollbar { width: 6px; }
.icon-picker-grid::-webkit-scrollbar-track { background: var(--bg-darker); }
.icon-picker-grid::-webkit-scrollbar-thumb { background: var(--border-dark); border-radius: 3px; }
.icon-option {
    position: relative;
    cursor: pointer;
    aspect-ratio: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    border: 2px solid transparent;
    border-radius: var(--radius-sm);
    transition: all .2s ease;
    background: var(--panel-bg);
}
.icon-option input { display: none; }
.icon-option i { font-size: 1.1rem; color: var(--text-main); margin-bottom: .2rem; }
.icon-option span { font-size: .6rem; color: var(--text-muted); }
.icon-option:hover { border-color: var(--border-light); background: rgba(255,255,255,0.03); }
.icon-option:has(input:checked) {
    border-color: var(--crimson);
    background: rgba(138,21,56,0.15);
}
.icon-option:has(input:checked) i { color: var(--crimson); }
.icon-selected-preview {
    display: flex;
    align-items: center;
    gap: .8rem;
    padding: .8rem 1rem;
    background: var(--bg-darker);
    border: 1px solid var(--border-dark);
    border-radius: var(--radius-md);
    margin-bottom: .8rem;
}
.icon-selected-preview i { font-size: 1.5rem; color: var(--crimson); }
.icon-selected-preview .icon-info { flex: 1; }
.icon-selected-preview .icon-class { font-family: monospace; color: var(--sky-blue); font-size: .85rem; }
.icon-selected-preview .icon-label { color: var(--text-muted); font-size: .75rem; }

/* ===== FILTER BAR ===== */
.vm-filter-bar {
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
.vm-filter-bar .form-group { margin: 0; }
.vm-filter-bar label { font-size: .85rem; color: var(--text-muted); margin-bottom: .4rem; display: block; font-weight: 600; }

/* ===== GROUPED TABLE ===== */
.vm-table thead th {
    background: var(--bg-darker);
    color: var(--sky-blue);
    font-size: .78rem;
    text-transform: uppercase;
    letter-spacing: .5px;
    padding: 12px 16px;
    font-weight: 700;
    border-bottom: 1px solid var(--border-dark);
}
.type-group-header { border-left: 4px solid var(--crimson); }
.type-group-header.tipe-visi { background: linear-gradient(90deg, rgba(138,21,56,.15), transparent); border-left-color: var(--crimson); }
.type-group-header.tipe-misi { background: linear-gradient(90deg, rgba(135,206,235,.12), transparent); border-left-color: var(--sky-blue); }
.type-group-header.tipe-tujuan { background: linear-gradient(90deg, rgba(46,139,87,.12), transparent); border-left-color: var(--success); }
.type-group-header.tipe-nilai { background: linear-gradient(90deg, rgba(243,156,18,.12), transparent); border-left-color: var(--warning); }
.type-group-header td {
    padding: 12px 16px;
    font-weight: 800;
    color: #fff;
    font-size: .95rem;
}
.type-group-header .type-stats {
    float: right;
    font-size: .82rem;
    font-weight: 500;
    color: var(--text-muted);
}

.type-badge {
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
.type-badge.visi { background: rgba(138,21,56,0.2); color: var(--crimson); }
.type-badge.misi { background: rgba(135,206,235,0.2); color: var(--sky-blue); }
.type-badge.tujuan { background: rgba(46,139,87,0.2); color: var(--success); }
.type-badge.nilai { background: rgba(243,156,18,0.2); color: var(--warning); }

.isi-preview {
    max-width: 350px;
    color: var(--text-muted);
    font-size: .85rem;
    line-height: 1.5;
    max-height: 3em;
    overflow: hidden;
}

.icon-cell {
    display: flex;
    align-items: center;
    gap: .5rem;
}
.icon-cell-box {
    width: 32px; height: 32px;
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: .95rem;
    flex-shrink: 0;
}
.icon-cell-box.visi { background: rgba(138,21,56,0.15); color: var(--crimson); }
.icon-cell-box.misi { background: rgba(135,206,235,0.15); color: var(--sky-blue); }
.icon-cell-box.tujuan { background: rgba(46,139,87,0.15); color: var(--success); }
.icon-cell-box.nilai { background: rgba(243,156,18,0.15); color: var(--warning); }
.icon-cell-code { font-family: monospace; font-size: .72rem; color: var(--text-muted); }

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

/* ===== TYPE SELECTOR (FORM) ===== */
.type-selector {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: .6rem;
    margin-bottom: 1.5rem;
}
.type-selector-item {
    position: relative;
    cursor: pointer;
}
.type-selector-item input { display: none; }
.type-selector-card {
    padding: 1rem .8rem;
    border: 2px solid var(--border-dark);
    border-radius: var(--radius-md);
    text-align: center;
    transition: all .2s ease;
    background: var(--bg-darker);
}
.type-selector-card .ts-icon {
    width: 44px; height: 44px;
    border-radius: 50%;
    margin: 0 auto .5rem;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.2rem;
    background: rgba(255,255,255,.05);
}
.type-selector-card .ts-label { font-weight: 700; color: #fff; font-size: .9rem; }
.type-selector-card .ts-desc { font-size: .7rem; color: var(--text-muted); margin-top: .2rem; }
.type-selector-item:has(input:checked) .type-selector-card {
    border-color: var(--crimson);
    background: rgba(138,21,56,0.1);
    transform: translateY(-2px);
}
.type-selector-item.crimson:has(input:checked) .type-selector-card .ts-icon { background: rgba(138,21,56,0.25); color: var(--crimson); }
.type-selector-item.sky:has(input:checked) .type-selector-card .ts-icon { background: rgba(135,206,235,0.25); color: var(--sky-blue); }
.type-selector-item.emerald:has(input:checked) .type-selector-card .ts-icon { background: rgba(46,139,87,0.25); color: var(--success); }
.type-selector-item.warning:has(input:checked) .type-selector-card .ts-icon { background: rgba(243,156,18,0.25); color: var(--warning); }

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

/* Preview: Visi Card */
.prev-visi-card {
    background: linear-gradient(135deg, #f8f9fa, #fff);
    border-radius: 20px;
    padding: 2rem 1.5rem;
    text-align: center;
    box-shadow: 0 10px 30px rgba(0,0,0,.2);
    border-top: 4px solid var(--crimson);
}
.prev-visi-icon {
    width: 60px; height: 60px;
    border-radius: 50%;
    background: rgba(138,21,56,0.1);
    color: var(--crimson);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.8rem;
    margin: 0 auto 1rem;
}
.prev-visi-label {
    display: inline-block;
    background: var(--crimson);
    color: #fff;
    padding: .25rem .8rem;
    border-radius: 50px;
    font-size: .7rem;
    font-weight: 800;
    letter-spacing: 1.5px;
    margin-bottom: .8rem;
}
.prev-visi-text {
    color: #333;
    font-size: .95rem;
    line-height: 1.7;
    font-style: italic;
    min-height: 60px;
}

/* Preview: Misi Card */
.prev-misi-card {
    background: #fff;
    border-radius: 14px;
    padding: 1.2rem;
    box-shadow: 0 6px 20px rgba(0,0,0,.15);
    position: relative;
    overflow: hidden;
    border-left: 4px solid var(--sky-blue);
}
.prev-misi-num {
    position: absolute;
    top: -10px; right: 10px;
    font-size: 3rem;
    font-weight: 900;
    color: rgba(135,206,235,0.15);
    line-height: 1;
}
.prev-misi-icon {
    width: 40px; height: 40px;
    border-radius: 10px;
    background: rgba(135,206,235,0.15);
    color: var(--sky-blue);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.1rem;
    margin-bottom: .6rem;
}
.prev-misi-title {
    color: #222;
    font-size: .9rem;
    font-weight: 800;
    margin-bottom: .3rem;
    line-height: 1.3;
}
.prev-misi-text {
    color: #666;
    font-size: .78rem;
    line-height: 1.5;
    min-height: 40px;
}

/* Preview: Tujuan Item */
.prev-tujuan-item {
    background: #fff;
    border-radius: 10px;
    padding: .8rem 1rem;
    display: flex;
    align-items: flex-start;
    gap: .8rem;
    box-shadow: 0 4px 12px rgba(0,0,0,.1);
    border-left: 3px solid var(--success);
}
.prev-tujuan-icon {
    width: 36px; height: 36px;
    border-radius: 50%;
    background: rgba(46,139,87,0.1);
    color: var(--success);
    display: flex; align-items: center; justify-content: center;
    font-size: .95rem;
    flex-shrink: 0;
}
.prev-tujuan-text {
    color: #444;
    font-size: .8rem;
    line-height: 1.5;
    flex: 1;
    min-height: 40px;
}

/* Preview: Nilai Card */
.prev-nilai-card {
    background: linear-gradient(135deg, #fff8e7, #fff);
    border-radius: 14px;
    padding: 1.2rem;
    text-align: center;
    box-shadow: 0 6px 20px rgba(0,0,0,.15);
    border-top: 3px solid var(--warning);
}
.prev-nilai-icon {
    width: 50px; height: 50px;
    border-radius: 50%;
    background: rgba(243,156,18,0.15);
    color: var(--warning);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.3rem;
    margin: 0 auto .6rem;
}
.prev-nilai-title {
    color: #222;
    font-size: .9rem;
    font-weight: 800;
    margin-bottom: .3rem;
}
.prev-nilai-text {
    color: #666;
    font-size: .75rem;
    line-height: 1.5;
    min-height: 40px;
}

/* ===== FORM GRID ===== */
.vm-form-grid {
    display: grid;
    grid-template-columns: 1.4fr 1fr;
    gap: 2rem;
}

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
.vm-empty {
    text-align: center;
    padding: 4rem 2rem;
    background: var(--panel-bg);
    border: 2px dashed var(--border-dark);
    border-radius: var(--radius-lg);
}
.vm-empty-icon {
    width: 100px; height: 100px;
    margin: 0 auto 1.5rem;
    background: linear-gradient(135deg, var(--crimson), var(--crimson-light));
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 3rem;
    color: #fff;
    box-shadow: 0 10px 30px rgba(138,21,56,.3);
}
.vm-empty h3 { color: #fff; margin-bottom: .5rem; font-size: 1.3rem; }
.vm-empty p { color: var(--text-muted); margin-bottom: 1.5rem; max-width: 500px; margin-left: auto; margin-right: auto; }

/* Responsive */
@media (max-width: 1100px) {
    .vm-form-grid { grid-template-columns: 1fr; }
}
@media (max-width: 992px) {
    .vm-stats-grid { grid-template-columns: repeat(2, 1fr); }
    .type-list { grid-template-columns: repeat(2, 1fr); }
    .type-selector { grid-template-columns: repeat(2, 1fr); }
    .vm-filter-bar { grid-template-columns: 1fr; }
    .icon-picker-grid { grid-template-columns: repeat(6, 1fr); }
}
@media (max-width: 576px) {
    .vm-stats-grid { grid-template-columns: 1fr; }
    .type-list { grid-template-columns: 1fr; }
    .type-selector { grid-template-columns: 1fr; }
    .icon-picker-grid { grid-template-columns: repeat(4, 1fr); }
}
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
        <h2 class="section-title"><i class="fa-solid fa-bullseye"></i> <?= isset($_GET['edit']) ? 'Edit' : 'Kelola' ?> Visi, Misi, Tujuan & Nilai</h2>
        <p style="color:var(--text-muted); font-size:.88rem; margin-top:.3rem;">
            <i class="fa-solid fa-circle-info"></i> Arah besar dan komitmen Program Studi Bahasa dan Sastra Inggris UMMaumere
        </p>
    </div>
    <div style="display:flex; gap:.8rem;">
        <?php if (isset($_GET['edit'])): ?>
        <a href="kelola_visimisi.php" class="btn btn-secondary"><i class="fa-solid fa-xmark"></i> Batal</a>
        <?php else: ?>
        <a href="?action=add" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Tambah Poin</a>
        <a href="../visi_misi.php" target="_blank" class="btn btn-secondary"><i class="fa-solid fa-external-link"></i> Lihat di Website</a>
        <?php endif; ?>
    </div>
</div>

<!-- ===== STATS DASHBOARD ===== -->
<div class="vm-stats-grid">
    <?php foreach ($type_info as $tKey => $tInfo): ?>
    <div class="vm-stat-card <?= $tInfo['color'] ?>" onclick="window.location='?action=add&tipe=<?= $tKey ?>'">
        <div class="vm-stat-icon"><i class="fa-solid <?= $tInfo['icon'] ?>"></i></div>
        <div>
            <div class="vm-stat-value" data-counter="<?= $count[$tKey] ?>">0</div>
            <div class="vm-stat-label"><?= $tInfo['label'] ?></div>
            <div class="vm-stat-desc"><?= $tInfo['desc'] ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- ===== TYPE DISTRIBUTION ===== -->
<div class="type-distribution">
    <div class="type-dist-title">
        <i class="fa-solid fa-chart-pie"></i> Distribusi per Kategori
    </div>
    <div class="type-list">
        <?php foreach ($type_info as $tKey => $tInfo): 
            $percent = $total_items > 0 ? round(($count[$tKey] / $total_items) * 100, 1) : 0;
        ?>
        <div class="type-item <?= $tInfo['color'] ?>" onclick="document.getElementById('typeFilter').value='<?= $tKey ?>'; document.getElementById('typeFilter').dispatchEvent(new Event('change'));">
            <div class="type-item-icon"><i class="fa-solid <?= $tInfo['icon'] ?>"></i></div>
            <div class="type-item-label"><?= $tInfo['label'] ?></div>
            <div class="type-item-count"><?= $count[$tKey] ?></div>
            <small style="color:var(--text-muted); font-size:.7rem;"><?= $percent ?>% dari total</small>
            <div class="type-item-bar">
                <div class="type-item-fill" data-target="<?= $percent ?>"></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php if (isset($_GET['action']) && $_GET['action'] === 'add' || isset($_GET['edit'])): ?>
<!-- ===== FORM ADD/EDIT ===== -->
<div class="panel">
    <div class="panel-header">
        <h3 class="panel-title">
            <i class="fa-solid fa-pen-to-square"></i> 
            <?= isset($_GET['edit']) ? 'Edit' : 'Tambah' ?> Poin Visi/Misi/Tujuan/Nilai
        </h3>
    </div>
    <div class="panel-body">
        <form method="POST" id="visimisiForm">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="id" value="<?= $data['id'] ?>">
            <input type="hidden" name="isi" id="hiddenIsi" value="<?= htmlspecialchars((string)($data['isi'] ?? '')) ?>">
            
            <div class="vm-form-grid">
                <!-- FORM FIELDS -->
                <div>
                    <div class="form-section-title"><i class="fa-solid fa-tag"></i> Pilih Tipe Poin</div>
                    
                    <div class="info-alert" id="typeHint">
                        <i class="fa-solid fa-circle-info"></i>
                        <div id="typeHintText">
                            <strong>Misi:</strong> Komitmen utama yang menggerakkan aktivitas tridharma.
                        </div>
                    </div>
                    
                    <div class="type-selector">
                        <?php foreach ($type_info as $tKey => $tInfo): ?>
                        <label class="type-selector-item <?= $tInfo['color'] ?>">
                            <input type="radio" name="tipe" value="<?= $tKey ?>" <?= ($data['tipe'] ?? 'misi') === $tKey ? 'checked' : '' ?> data-hint="<strong><?= $tInfo['label'] ?>:</strong> <?= $tInfo['desc'] ?>">
                            <div class="type-selector-card">
                                <div class="ts-icon"><i class="fa-solid <?= $tInfo['icon'] ?>"></i></div>
                                <div class="ts-label"><?= $tInfo['label'] ?></div>
                                <div class="ts-desc"><?= $tInfo['desc'] ?></div>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>

                    <div class="form-section-title"><i class="fa-solid fa-heading"></i> Konten</div>
                    
                    <div class="form-group" id="judulGroup">
                        <label>Judul <span class="required">*</span> <small style="color:var(--text-muted);">(Wajib untuk Misi/Tujuan/Nilai)</small></label>
                        <input type="text" name="judul" id="f_judul" class="form-control" value="<?= htmlspecialchars((string)($data['judul'] ?? '')) ?>" placeholder="Contoh: Pendidikan & Pengajaran Unggul">
                    </div>
                    
                    <div class="form-group">
                        <label>Isi Konten <span class="required">*</span></label>
                        <div id="quillEditor" style="min-height: 180px;"><?= $data['isi'] ?? '' ?></div>
                        <small style="color:var(--text-muted);"><i class="fa-solid fa-info-circle"></i> Untuk Visi, cukup tulis 1 kalimat visioner. Untuk lainnya, 1-2 kalimat deskriptif.</small>
                    </div>

                    <div class="form-section-title"><i class="fa-solid fa-wand-magic-sparkles"></i> Pilih Ikon</div>
                    
                    <div class="icon-selected-preview" id="iconSelectedPreview">
                        <i class="<?= htmlspecialchars((string)($data['ikon'] ?? 'fa-solid fa-bullseye')) ?>" id="iconSelectedIcon"></i>
                        <div class="icon-info">
                            <div class="icon-class" id="iconSelectedClass"><?= htmlspecialchars((string)($data['ikon'] ?? 'fa-solid fa-bullseye')) ?></div>
                            <div class="icon-label">Ikon yang dipilih</div>
                        </div>
                    </div>
                    
                    <div class="icon-picker-grid">
                        <?php foreach ($icon_set as $ic): ?>
                        <label class="icon-option">
                            <input type="radio" name="ikon" value="<?= htmlspecialchars($ic['class']) ?>" <?= (($data['ikon'] ?? '') === $ic['class']) ? 'checked' : '' ?>>
                            <i class="<?= htmlspecialchars($ic['class']) ?>"></i>
                            <span><?= htmlspecialchars($ic['label']) ?></span>
                        </label>
                        <?php endforeach; ?>
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
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> <?= $data['id']>0?'Perbarui':'Simpan' ?> Data</button>
                        <a href="kelola_visimisi.php" class="btn btn-secondary"><i class="fa-solid fa-xmark"></i> Batal</a>
                    </div>
                </div>

                <!-- LIVE PREVIEW -->
                <div class="live-preview-wrapper">
                    <div class="live-preview-title">
                        <i class="fa-solid fa-eye"></i> LIVE PREVIEW (Tampilan di Website)
                    </div>
                    
                    <div id="previewContainer">
                        <!-- Preview akan dirender oleh JS sesuai tipe -->
                    </div>
                    
                    <div style="margin-top:1rem; padding:.8rem; background:rgba(46,139,87,0.1); border:1px solid rgba(46,139,87,0.3); border-radius:var(--radius-md); font-size:.78rem; color:var(--text-muted);">
                        <i class="fa-solid fa-circle-check" style="color:var(--success);"></i>
                        <strong style="color:var(--success);"> Tip:</strong> Preview di atas menampilkan tampilan persis seperti di halaman Visi & Misi website. Setiap tipe memiliki layout berbeda.
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Quill Editor
    var quill = new Quill('#quillEditor', {
        theme: 'snow',
        placeholder: 'Tulis konten di sini...',
        modules: {
            toolbar: [
                [{ 'header': [1, 2, 3, false] }],
                ['bold', 'italic', 'underline'],
                [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                ['link'],
                ['clean']
            ]
        }
    });
    
    function syncQuill() {
        document.getElementById('hiddenIsi').value = quill.root.innerHTML;
        updatePreview();
    }
    quill.on('text-change', syncQuill);
    
    document.querySelector('form').addEventListener('submit', syncQuill);
    
    // Fields
    const fJudul = document.getElementById('f_judul');
    const typeRadios = document.querySelectorAll('input[name="tipe"]');
    const iconRadios = document.querySelectorAll('input[name="ikon"]');
    const judulGroup = document.getElementById('judulGroup');
    const typeHintText = document.getElementById('typeHintText');
    const previewContainer = document.getElementById('previewContainer');
    const iconSelectedIcon = document.getElementById('iconSelectedIcon');
    const iconSelectedClass = document.getElementById('iconSelectedClass');
    
    function getCurrentType() {
        const checked = document.querySelector('input[name="tipe"]:checked');
        return checked ? checked.value : 'misi';
    }
    function getCurrentIcon() {
        const checked = document.querySelector('input[name="ikon"]:checked');
        return checked ? checked.value : 'fa-solid fa-bullseye';
    }
    function getQuillText() {
        return quill.getText().trim() || '';
    }
    function getQuillHtml() {
        return quill.root.innerHTML || '';
    }
    
    function updatePreview() {
        const type = getCurrentType();
        const icon = getCurrentIcon();
        const judul = fJudul.value || '';
        const isi = getQuillText() || getQuillHtml();
        const plainIsi = getQuillText();
        
        // Show/hide judul field based on type
        if (type === 'visi') {
            judulGroup.style.display = 'none';
        } else {
            judulGroup.style.display = 'block';
        }
        
        // Render preview
        let html = '';
        if (type === 'visi') {
            html = `
                <div class="prev-visi-card">
                    <div class="prev-visi-icon"><i class="${icon}"></i></div>
                    <span class="prev-visi-label">VISI KAMI</span>
                    <p class="prev-visi-text">${plainIsi || 'Tuliskan visi besar prodi Anda di sini...'}</p>
                </div>
            `;
        } else if (type === 'misi') {
            html = `
                <div class="prev-misi-card">
                    <span class="prev-misi-num">01</span>
                    <div class="prev-misi-icon"><i class="${icon}"></i></div>
                    <h3 class="prev-misi-title">${judul || 'Judul Misi'}</h3>
                    <p class="prev-misi-text">${plainIsi || 'Deskripsi misi akan muncul di sini...'}</p>
                </div>
            `;
        } else if (type === 'tujuan') {
            html = `
                <div class="prev-tujuan-item">
                    <div class="prev-tujuan-icon"><i class="${icon}"></i></div>
                    <p class="prev-tujuan-text">${plainIsi || 'Target terukur yang ingin dicapai...'}</p>
                </div>
            `;
        } else if (type === 'nilai') {
            html = `
                <div class="prev-nilai-card">
                    <div class="prev-nilai-icon"><i class="${icon}"></i></div>
                    <h3 class="prev-nilai-title">${judul || 'Judul Nilai'}</h3>
                    <p class="prev-nilai-text">${plainIsi || 'Deskripsi karakter yang ditanamkan...'}</p>
                </div>
            `;
        }
        previewContainer.innerHTML = html;
    }
    
    // Update type hint
    function updateTypeHint() {
        const checked = document.querySelector('input[name="tipe"]:checked');
        if (checked && checked.dataset.hint) {
            typeHintText.innerHTML = checked.dataset.hint;
        }
        updatePreview();
    }
    
    typeRadios.forEach(r => r.addEventListener('change', updateTypeHint));
    iconRadios.forEach(r => r.addEventListener('change', () => {
        const icon = r.value;
        iconSelectedIcon.className = icon;
        iconSelectedClass.textContent = icon;
        updatePreview();
    }));
    fJudul?.addEventListener('input', updatePreview);
    
    // Initial render
    updateTypeHint();
    updatePreview();
});
</script>

<?php else: ?>
<!-- ===== FILTER BAR ===== -->
<?php if (!empty($items)): ?>
<div class="vm-filter-bar">
    <div class="form-group">
        <label><i class="fa-solid fa-search"></i> Cari Konten</label>
        <input type="text" id="vmSearch" class="form-control" placeholder="Ketik judul atau isi...">
    </div>
    <div class="form-group">
        <label><i class="fa-solid fa-tag"></i> Tipe</label>
        <select id="typeFilter" class="form-control">
            <option value="all">Semua Tipe</option>
            <?php foreach ($type_info as $tKey => $tInfo): ?>
            <option value="<?= $tKey ?>"><?= $tInfo['label'] ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label><i class="fa-solid fa-eye"></i> Status</label>
        <select id="statusFilter" class="form-control">
            <option value="all">Semua</option>
            <option value="1">Aktif</option>
            <option value="0">Nonaktif</option>
        </select>
    </div>
</div>
<?php endif; ?>

<!-- ===== LIST VIEW ===== -->
<?php if (empty($items)): ?>
<div class="vm-empty">
    <div class="vm-empty-icon"><i class="fa-solid fa-bullseye"></i></div>
    <h3>Belum Ada Data Visi & Misi</h3>
    <p>Mulai bangun arah besar dan komitmen Program Studi Anda dengan menambahkan Visi, Misi, Tujuan, dan Nilai-Nilai Inti</p>
    <a href="?action=add" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Tambah Poin Pertama</a>
</div>
<?php else: ?>
<div class="panel">
    <div class="panel-body no-padding">
        <div class="table-wrapper">
            <table class="vm-table">
                <thead>
                    <tr>
                        <th style="width:100px;">Tipe</th>
                        <th style="width:80px;">Ikon</th>
                        <th>Judul & Isi</th>
                        <th style="width:70px;">Urutan</th>
                        <th style="width:90px;">Status</th>
                        <th style="width:180px;">Aksi</th>
                    </tr>
                </thead>
                <tbody id="vmTableBody">
                    <?php 
                    $current_type = '';
                    foreach ($items as $item): 
                        $token = hash_hmac('sha256', $item['id'], $_SESSION['csrf_token']);
                        $t = $item['tipe'] ?? 'misi';
                        $info = $type_info[$t] ?? ['label'=>'Unknown', 'color'=>'crimson', 'icon'=>'fa-star'];
                        
                        // Type group header
                        if ($t !== $current_type) {
                            $current_type = $t;
                            $countType = count($by_type[$t] ?? []);
                            $activeCount = count(array_filter($by_type[$t] ?? [], fn($x) => ($x['aktif'] ?? 0) == 1));
                            ?>
                            <tr class="type-group-header tipe-<?= $t ?>" data-tipe="<?= $t ?>">
                                <td colspan="6">
                                    <i class="fa-solid <?= $info['icon'] ?>"></i> 
                                    <?= $info['label'] ?>
                                    <span class="type-stats">
                                        <i class="fa-solid fa-list"></i> <?= $countType ?> poin · 
                                        <i class="fa-solid fa-check-circle"></i> <?= $activeCount ?> aktif
                                    </span>
                                </td>
                            </tr>
                            <?php
                        }
                        
                        $isiPreview = strip_tags($item['isi'] ?? '');
                        $isiShort = strlen($isiPreview) > 100 ? substr($isiPreview, 0, 100) . '...' : $isiPreview;
                        $judulDisplay = $item['judul'] ?: '<em style="color:var(--text-muted);">(Tanpa judul - Visi)</em>';
                    ?>
                    <tr class="vm-row" 
                         data-search="<?= strtolower(htmlspecialchars(($item['judul'] ?? '') . ' ' . $isiPreview)) ?>"
                         data-tipe="<?= $t ?>"
                         data-status="<?= $item['aktif'] ?? 0 ?>">
                        <td><span class="type-badge <?= $t ?>"><i class="fa-solid <?= $info['icon'] ?>"></i> <?= $info['label'] ?></span></td>
                        <td>
                            <div class="icon-cell">
                                <div class="icon-cell-box <?= $t ?>">
                                    <i class="<?= htmlspecialchars($item['ikon'] ?? 'fa-solid fa-star') ?>"></i>
                                </div>
                            </div>
                        </td>
                        <td>
                            <strong><?= $judulDisplay ?></strong>
                            <div class="isi-preview"><?= htmlspecialchars($isiShort) ?></div>
                        </td>
                        <td><strong>#<?= (int)($item['urutan'] ?? 0) ?></strong></td>
                        <td>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="toggle_status" value="1">
                                <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                <input type="hidden" name="aktif" value="<?= ($item['aktif'] ?? 0) == 1 ? 0 : 1 ?>">
                                <label class="toggle-switch" title="<?= ($item['aktif'] ?? 0) == 1 ? 'Nonaktifkan' : 'Aktifkan' ?>">
                                    <input type="checkbox" <?= ($item['aktif'] ?? 0) == 1 ? 'checked' : '' ?> onchange="this.form.submit()">
                                    <span class="toggle-slider"></span>
                                </label>
                            </form>
                        </td>
                        <td>
                            <div class="table-actions">
                                <a href="?edit=<?= $item['id'] ?>&token=<?= $token ?>" class="table-action-btn edit" title="Edit"><i class="fa-solid fa-pen"></i></a>
                                <a href="?duplicate=<?= $item['id'] ?>&token=<?= $token ?>" class="table-action-btn" title="Duplikasi" onclick="return confirm('Duplikasi poin ini?')"><i class="fa-solid fa-copy"></i></a>
                                <a href="?delete=<?= $item['id'] ?>&token=<?= $token ?>" class="table-action-btn delete" onclick="return confirm('Yakin ingin menghapus poin ini?')" title="Hapus"><i class="fa-solid fa-trash"></i></a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div style="text-align:center; margin-top:1rem; padding:.8rem; background:rgba(138,21,56,.05); border-radius:8px; color:var(--text-muted); font-size:.88rem;">
    Menampilkan <strong id="vmCount" style="color:var(--crimson);"><?= $total_items ?></strong> dari <strong><?= $total_items ?></strong> poin
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('vmSearch');
    const typeFilter = document.getElementById('typeFilter');
    const statusFilter = document.getElementById('statusFilter');
    const countEl = document.getElementById('vmCount');
    
    function filterVM() {
        const term = (searchInput?.value || '').toLowerCase();
        const type = typeFilter?.value || 'all';
        const status = statusFilter?.value || 'all';
        
        const rows = document.querySelectorAll('.vm-row');
        const headers = document.querySelectorAll('.type-group-header');
        let visibleCount = 0;
        
        rows.forEach(row => {
            const searchText = row.dataset.search || '';
            const rowType = row.dataset.tipe;
            const rowStatus = row.dataset.status;
            
            const matchSearch = !term || searchText.includes(term);
            const matchType = type === 'all' || rowType === type;
            const matchStatus = status === 'all' || rowStatus === status;
            
            if (matchSearch && matchType && matchStatus) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        
        // Hide headers tanpa row visible
        headers.forEach(header => {
            const hType = header.dataset.tipe;
            const hasVisible = Array.from(rows).some(r => r.dataset.tipe === hType && r.style.display !== 'none');
            header.style.display = (type === 'all' || hType === type) && hasVisible ? '' : 'none';
        });
        
        if (countEl) countEl.textContent = visibleCount;
    }
    
    if (searchInput) searchInput.addEventListener('input', filterVM);
    if (typeFilter) typeFilter.addEventListener('change', filterVM);
    if (statusFilter) statusFilter.addEventListener('change', filterVM);
});
</script>
<?php endif; ?>
<?php endif; ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    if ($('#visimisiForm').length && $.fn.select2) {
        $('.select2-premium').select2({ dropdownParent: $('#visimisiForm') });
    }
    
    // Auto set tipe from URL
    const urlParams = new URLSearchParams(window.location.search);
    const presetTipe = urlParams.get('tipe');
    if (presetTipe) {
        const radio = document.querySelector(`input[name="tipe"][value="${presetTipe}"]`);
        if (radio) radio.click();
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
    
    // Animated type bars
    setTimeout(() => {
        document.querySelectorAll('.type-item-fill').forEach(bar => {
            bar.style.width = bar.dataset.target + '%';
        });
    }, 300);
});
</script>

<?php
$page_content = ob_get_clean();
require_once 'includes/admin_template.php';
?>