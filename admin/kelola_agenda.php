<?php
require_once '../includes/config.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';
require_login();

$page_title = 'Kelola Agenda & Kalender';
$current_page = 'agenda';
$table = 'agenda';

// ===== AUTO-MIGRATION (Self-Healing Database) =====
$required_cols = [
    'judul'           => "varchar(255) NOT NULL",
    'deskripsi'       => "text DEFAULT NULL",
    'tanggal_mulai'   => "datetime NOT NULL",
    'tanggal_selesai' => "datetime DEFAULT NULL",
    'lokasi'          => "varchar(200) DEFAULT ''",
    'tipe'            => "varchar(50) DEFAULT 'Lainnya'",
    'status'          => "varchar(30) DEFAULT 'akan_datang'",
    'link'            => "varchar(255) DEFAULT ''",
    'urutan'          => "int(11) DEFAULT 1",
    'aktif'           => "tinyint(1) DEFAULT 1",
];
$existing_cols = $pdo->query("SHOW COLUMNS FROM $table")->fetchAll(PDO::FETCH_COLUMN);
foreach ($required_cols as $col => $def) {
    if (!in_array($col, $existing_cols, true)) {
        $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$col` $def");
    }
}

// ===== HANDLE DELETE =====if (isset($_GET['delete']) && isset($_GET['token']) && hash_equals(hash_hmac('sha256', (int)$_GET['delete'], $_SESSION['csrf_token']), $_GET['token'])) {
    if (isset($_GET['delete']) && isset($_GET['token']) && hash_equals(hash_hmac('sha256', (int)$_GET['delete'], $_SESSION['csrf_token']), $_GET['token'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("SELECT judul FROM $table WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    $pdo->prepare("DELETE FROM $table WHERE id = ?")->execute([$id]);
    
    $pdo->prepare("INSERT INTO admin_logs (user_id, user_name, action, detail, ip) VALUES (?, ?, 'DELETE_AGENDA', ?, ?)")
        ->execute([$_SESSION['admin_id'], $_SESSION['admin_name'], 'Menghapus agenda: ' . ($row['judul'] ?? 'ID '.$id), $_SERVER['REMOTE_ADDR']]);
    
    header("Location: kelola_agenda.php?msg=deleted");
    exit;
}

// ===== HANDLE DUPLICATE =====
if (isset($_GET['duplicate']) && isset($_GET['token']) && hash_equals(hash_hmac('sha256', (int)$_GET['duplicate'], $_SESSION['csrf_token']), $_GET['token'])) {
    $id = (int)$_GET['duplicate'];
    $stmt = $pdo->prepare("SELECT * FROM $table WHERE id = ?");
    $stmt->execute([$id]);
    $orig = $stmt->fetch();
    if ($orig) {
        $pdo->prepare("INSERT INTO $table (judul, deskripsi, tanggal_mulai, tanggal_selesai, lokasi, tipe, status, link, urutan, aktif) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0)")
            ->execute([
                $orig['judul'] . ' (Copy)',
                $orig['deskripsi'],
                $orig['tanggal_mulai'],
                $orig['tanggal_selesai'],
                $orig['lokasi'],
                $orig['tipe'],
                'akan_datang',
                $orig['link'] ?? '',
                ($orig['urutan'] ?? 0) + 1
            ]);
    }
    header("Location: kelola_agenda.php?msg=duplicated");
    exit;
}

// ===== HANDLE TOGGLE STATUS =====
if (isset($_POST['toggle_status']) && isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $id = (int)$_POST['id'];
    $aktif = (int)$_POST['aktif'];
    $pdo->prepare("UPDATE $table SET aktif = ? WHERE id = ?")->execute([$aktif, $id]);
    header("Location: kelola_agenda.php?msg=toggled");
    exit;
}

// ===== HANDLE ADD/EDIT =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['toggle_status']) && isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $judul = sanitize($_POST['judul']);
    $deskripsi = sanitize($_POST['deskripsi']);
    $tanggal_mulai = sanitize($_POST['tanggal_mulai']);
    $tanggal_selesai = sanitize($_POST['tanggal_selesai']) ?: null;
    $lokasi = sanitize($_POST['lokasi']);
    $tipe = sanitize($_POST['tipe']);
    $status = sanitize($_POST['status']);
    $link = sanitize($_POST['link']);
    $urutan = (int)$_POST['urutan'];
    $aktif = isset($_POST['aktif']) ? 1 : 0;

    // Auto-calculate status if set to 'auto'
    if ($status === 'auto') {
        $now = date('Y-m-d H:i:s');
        $start = $tanggal_mulai;
        $end = $tanggal_selesai ?: $tanggal_mulai;
        if ($now < $start) $status = 'akan_datang';
        elseif ($now >= $start && $now <= $end) $status = 'berlangsung';
        else $status = 'selesai';
    }

    // Validasi
    if (empty($judul)) {
        $error = "Judul agenda wajib diisi.";
    } elseif (empty($tanggal_mulai)) {
        $error = "Tanggal mulai wajib diisi.";
    } elseif ($tanggal_selesai && strtotime($tanggal_selesai) < strtotime($tanggal_mulai)) {
        $error = "Tanggal selesai tidak boleh sebelum tanggal mulai.";
    } elseif (!empty($link) && !filter_var($link, FILTER_VALIDATE_URL)) {
        $error = "URL link tidak valid.";
    } else {
        $fields = "judul=:judul, deskripsi=:deskripsi, tanggal_mulai=:tanggal_mulai, tanggal_selesai=:tanggal_selesai, lokasi=:lokasi, tipe=:tipe, status=:status, link=:link, urutan=:urutan, aktif=:aktif";
        $params = [':judul'=>$judul, ':deskripsi'=>$deskripsi, ':tanggal_mulai'=>$tanggal_mulai, ':tanggal_selesai'=>$tanggal_selesai, ':lokasi'=>$lokasi, ':tipe'=>$tipe, ':status'=>$status, ':link'=>$link, ':urutan'=>$urutan, ':aktif'=>$aktif];

        if ($id > 0) {
            $pdo->prepare("UPDATE $table SET $fields WHERE id=:id")->execute(array_merge($params, [':id'=>$id]));
            $action = 'UPDATE_AGENDA';
            $detail = "Memperbarui agenda: $judul";
        } else {
            $pdo->prepare("INSERT INTO $table SET $fields")->execute($params);
            $action = 'ADD_AGENDA';
            $detail = "Menambah agenda baru: $judul";
        }
        
        $pdo->prepare("INSERT INTO admin_logs (user_id, user_name, action, detail, ip) VALUES (?, ?, ?, ?, ?)")
            ->execute([$_SESSION['admin_id'], $_SESSION['admin_name'], $action, $detail, $_SERVER['REMOTE_ADDR']]);
        
        header("Location: kelola_agenda.php?msg=saved");
        exit;
    }
}

// ===== FETCH DATA =====
$items = $pdo->query("SELECT * FROM $table ORDER BY tanggal_mulai DESC, urutan ASC, id DESC")->fetchAll();

// Normalize NULL
foreach ($items as &$it) {
    foreach ($it as $k => $v) {
        if ($v === null) $it[$k] = '';
    }
}
unset($it);

// Statistics
$now = date('Y-m-d H:i:s');
$total_agenda = count($items);
$upcoming_count = 0;
$ongoing_count = 0;
$completed_count = 0;
$cancelled_count = 0;

foreach ($items as $it) {
    $start = $it['tanggal_mulai'] ?? '';
    $end = $it['tanggal_selesai'] ?: $start;
    $status_raw = $it['status'] ?? 'akan_datang';
    
    if ($status_raw === 'batal') {
        $cancelled_count++;
    } elseif ($start && $start > $now) {
        $upcoming_count++;
    } elseif ($start && $end && $now >= $start && $now <= $end) {
        $ongoing_count++;
    } elseif ($start && $now > $end) {
        $completed_count++;
    }
}

// Group by status
$by_status = ['akan_datang'=>[], 'berlangsung'=>[], 'selesai'=>[], 'batal'=>[]];
foreach ($items as $it) {
    $s = $it['status'] ?? 'akan_datang';
    if (!isset($by_status[$s])) $by_status[$s] = [];
    $by_status[$s][] = $it;
}

// Unique types for filter
$unique_types = array_unique(array_column($items, 'tipe'));
sort($unique_types);

// Status info map
$status_info = [
    'akan_datang' => ['label'=>'Akan Datang', 'color'=>'sky', 'icon'=>'fa-clock'],
    'berlangsung' => ['label'=>'Berlangsung', 'color'=>'emerald', 'icon'=>'fa-play'],
    'selesai'     => ['label'=>'Selesai', 'color'=>'grey', 'icon'=>'fa-circle-check'],
    'batal'  => ['label'=>'batal', 'color'=>'danger', 'icon'=>'fa-circle-xmark']
];

// Tipe options
$tipe_options = ['Seminar', 'Ujian', 'Wisuda', 'Workshop', 'Kuliah Tamu', 'Rapat', 'Lomba', 'Webinar', 'Lainnya'];

// Form data
$data = ['id'=>0, 'judul'=>'', 'deskripsi'=>'', 'tanggal_mulai'=>date('Y-m-d\TH:i'), 'tanggal_selesai'=>'', 'lokasi'=>'', 'tipe'=>'Seminar', 'status'=>'auto', 'link'=>'', 'urutan'=>1, 'aktif'=>1];
if (isset($_GET['edit']) && isset($_GET['token']) && hash_equals(hash_hmac('sha256', (int)$_GET['edit'], $_SESSION['csrf_token']), $_GET['token'])) {
    $stmt = $pdo->prepare("SELECT * FROM $table WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $fetched = $stmt->fetch();
    if ($fetched) {
        foreach ($fetched as $k => $v) {
            $data[$k] = ($v === null) ? '' : $v;
        }
        // Convert to datetime-local format
        if (!empty($data['tanggal_mulai'])) $data['tanggal_mulai'] = date('Y-m-d\TH:i', strtotime($data['tanggal_mulai']));
        if (!empty($data['tanggal_selesai'])) $data['tanggal_selesai'] = date('Y-m-d\TH:i', strtotime($data['tanggal_selesai']));
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
.agenda-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}
.agenda-stat-card {
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
.agenda-stat-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); }
.agenda-stat-card.crimson { border-left-color: var(--crimson); }
.agenda-stat-card.sky { border-left-color: var(--sky-blue); }
.agenda-stat-card.emerald { border-left-color: var(--success); }
.agenda-stat-card.grey { border-left-color: #95a5a6; }
.agenda-stat-card.danger { border-left-color: var(--danger); }
.agenda-stat-icon {
    width: 52px; height: 52px;
    border-radius: var(--radius-md);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.4rem; flex-shrink: 0;
}
.agenda-stat-card.crimson .agenda-stat-icon { background: rgba(138,21,56,0.15); color: var(--crimson); }
.agenda-stat-card.sky .agenda-stat-icon { background: rgba(135,206,235,0.15); color: var(--sky-blue); }
.agenda-stat-card.emerald .agenda-stat-icon { background: rgba(46,139,87,0.15); color: var(--success); }
.agenda-stat-card.grey .agenda-stat-icon { background: rgba(149,165,166,0.15); color: #95a5a6; }
.agenda-stat-card.danger .agenda-stat-icon { background: rgba(231,76,60,0.15); color: var(--danger); }
.agenda-stat-value { font-size: 1.9rem; font-weight: 900; color: #fff; line-height: 1; }
.agenda-stat-label { color: var(--text-muted); font-size: .82rem; margin-top: .2rem; }

/* ===== STATUS DISTRIBUTION ===== */
.status-distribution {
    background: var(--panel-bg);
    border: 1px solid var(--border-dark);
    border-radius: var(--radius-md);
    padding: 1.5rem;
    margin-bottom: 2rem;
}
.sts-dist-title {
    font-size: 1rem; font-weight: 700; color: #fff;
    margin-bottom: 1.2rem;
    display: flex; align-items: center; gap: .6rem;
}
.sts-dist-title i { color: var(--crimson); }
.sts-list { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; }
.sts-item {
    background: var(--bg-darker);
    border: 1px solid var(--border-dark);
    border-radius: var(--radius-md);
    padding: 1rem;
    cursor: pointer;
    transition: all .3s ease;
}
.sts-item:hover { border-color: var(--crimson); transform: translateY(-2px); }
.sts-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: .6rem; }
.sts-info { display: flex; align-items: center; gap: .5rem; }
.sts-icon {
    width: 32px; height: 32px;
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: .9rem;
}
.sts-icon.sky { background: rgba(135,206,235,0.2); color: var(--sky-blue); }
.sts-icon.emerald { background: rgba(46,139,87,0.2); color: var(--success); }
.sts-icon.grey { background: rgba(149,165,166,0.2); color: #95a5a6; }
.sts-icon.danger { background: rgba(231,76,60,0.2); color: var(--danger); }
.sts-name { font-weight: 700; color: #fff; font-size: .9rem; }
.sts-count { font-weight: 800; font-size: .9rem; color: #fff; }
.sts-bar { height: 6px; background: var(--bg-darker); border-radius: 50px; overflow: hidden; margin-top: .4rem; }
.sts-fill { height: 100%; border-radius: 50px; transition: width 1s ease; width: 0; }
.sts-fill.sky { background: linear-gradient(90deg, var(--sky-blue-dark), var(--sky-blue)); }
.sts-fill.emerald { background: linear-gradient(90deg, var(--success), #27ae60); }
.sts-fill.grey { background: linear-gradient(90deg, #95a5a6, #7f8c8d); }
.sts-fill.danger { background: linear-gradient(90deg, var(--danger), #c0392b); }

/* ===== FILTER BAR ===== */
.agenda-filter-bar {
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
.agenda-filter-bar .form-group { margin: 0; }
.agenda-filter-bar label { font-size: .85rem; color: var(--text-muted); margin-bottom: .4rem; display: block; font-weight: 600; }

/* ===== TABLE GROUPED ===== */
.agenda-table thead th {
    background: var(--bg-darker);
    color: var(--sky-blue);
    font-size: .78rem;
    text-transform: uppercase;
    letter-spacing: .5px;
    padding: 12px 16px;
    font-weight: 700;
    border-bottom: 1px solid var(--border-dark);
}
.status-group-header { border-left: 4px solid var(--sky-blue); }
.status-group-header.sts-akan_datang { background: linear-gradient(90deg, rgba(135,206,235,.12), transparent); border-left-color: var(--sky-blue); }
.status-group-header.sts-berlangsung { background: linear-gradient(90deg, rgba(46,139,87,.12), transparent); border-left-color: var(--success); }
.status-group-header.sts-selesai { background: linear-gradient(90deg, rgba(149,165,166,.12), transparent); border-left-color: #95a5a6; }
.status-group-header.sts-batal { background: linear-gradient(90deg, rgba(231,76,60,.12), transparent); border-left-color: var(--danger); }
.status-group-header td {
    padding: 12px 16px;
    font-weight: 800;
    color: #fff;
    font-size: .95rem;
}
.status-group-header .sts-stats {
    float: right;
    font-size: .82rem;
    font-weight: 500;
    color: var(--text-muted);
}

/* ===== STATUS BADGES ===== */
.sts-badge {
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
.sts-badge.akan_datang { background: rgba(135,206,235,0.15); color: var(--sky-blue); }
.sts-badge.berlangsung { background: rgba(46,139,87,0.15); color: var(--success); }
.sts-badge.selesai { background: rgba(149,165,166,0.15); color: #95a5a6; }
.sts-badge.batal { background: rgba(231,76,60,0.15); color: var(--danger); }

/* ===== DATE BADGE (mirip public) ===== */
.date-badge {
    width: 60px;
    background: linear-gradient(135deg, var(--crimson), var(--crimson-light));
    color: #fff;
    border-radius: 10px;
    padding: .6rem .4rem;
    text-align: center;
    box-shadow: 0 4px 10px rgba(138,21,56,.25);
    flex-shrink: 0;
}
.date-badge.upcoming { background: linear-gradient(135deg, var(--sky-blue-dark), var(--sky-blue)); box-shadow: 0 4px 10px rgba(135,206,235,.3); }
.date-badge.ongoing { background: linear-gradient(135deg, var(--success), #27ae60); box-shadow: 0 4px 10px rgba(46,139,87,.3); }
.date-badge.completed { background: linear-gradient(135deg, #95a5a6, #7f8c8d); box-shadow: 0 4px 10px rgba(149,165,166,.2); }
.date-badge.cancelled { background: linear-gradient(135deg, var(--danger), #c0392b); box-shadow: 0 4px 10px rgba(231,76,60,.3); }
.date-badge .day {
    display: block;
    font-size: 1.4rem;
    font-weight: 900;
    line-height: 1;
    margin-bottom: .1rem;
}
.date-badge .month {
    display: block;
    font-size: .65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
}
.date-badge .year {
    display: block;
    font-size: .6rem;
    opacity: .8;
    margin-top: .1rem;
}

.agenda-row td { vertical-align: middle; }
.agenda-row .agenda-cell {
    display: flex;
    gap: 1rem;
    align-items: center;
}
.agenda-info-cell { flex: 1; }
.agenda-title {
    font-weight: 700;
    color: #fff;
    font-size: .9rem;
    margin-bottom: .3rem;
    line-height: 1.3;
}
.agenda-meta-cell {
    display: flex;
    flex-wrap: wrap;
    gap: .8rem;
    font-size: .78rem;
    color: var(--text-muted);
}
.agenda-meta-cell span {
    display: flex;
    align-items: center;
    gap: .3rem;
}
.agenda-meta-cell i { color: var(--crimson); }
.agenda-countdown-cell {
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    background: rgba(135,206,235,0.1);
    color: var(--sky-blue);
    padding: .2rem .6rem;
    border-radius: 50px;
    font-size: .72rem;
    font-weight: 600;
    margin-top: .3rem;
}
.agenda-countdown-cell i { animation: spin 2s linear infinite; }
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }

/* ===== MODERN TOGGLE SWITCH ===== */
.toggle-switch { position: relative; display: inline-block; width: 42px; height: 22px; flex-shrink: 0; }
.toggle-switch input { opacity: 0; width: 0; height: 0; }
.toggle-slider { position: absolute; cursor: pointer; inset: 0; background: var(--bg-darker); border: 1px solid var(--border-dark); transition: .3s; border-radius: 50px; }
.toggle-slider:before { position: absolute; content: ""; height: 16px; width: 16px; left: 2px; bottom: 2px; background: var(--text-muted); transition: .3s; border-radius: 50%; }
.toggle-switch input:checked + .toggle-slider { background: var(--success); border-color: var(--success); }
.toggle-switch input:checked + .toggle-slider:before { transform: translateX(20px); background: #fff; }

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

.preview-agenda-card {
    background: #fff;
    border-radius: 16px;
    padding: 1.2rem;
    box-shadow: 0 10px 30px rgba(0,0,0,.25);
    display: grid;
    grid-template-columns: 80px 1fr;
    gap: 1rem;
    align-items: start;
    border-left: 5px solid var(--crimson);
    color: #333;
    position: relative;
    overflow: hidden;
}
.preview-agenda-card.sts-akan_datang { border-left-color: var(--sky-blue); }
.preview-agenda-card.sts-berlangsung { border-left-color: var(--success); }
.preview-agenda-card.sts-selesai { border-left-color: #95a5a6; }
.preview-agenda-card.sts-batal { border-left-color: var(--danger); }

.preview-date-badge {
    background: linear-gradient(135deg, var(--crimson), var(--crimson-light));
    color: #fff;
    border-radius: 12px;
    padding: .8rem .5rem;
    text-align: center;
    box-shadow: 0 6px 15px rgba(138,21,56,.3);
}
.preview-date-badge.sts-akan_datang { background: linear-gradient(135deg, var(--sky-blue-dark), var(--sky-blue)); }
.preview-date-badge.sts-berlangsung { background: linear-gradient(135deg, var(--success), #27ae60); }
.preview-date-badge.sts-selesai { background: linear-gradient(135deg, #95a5a6, #7f8c8d); }
.preview-date-badge.sts-batal { background: linear-gradient(135deg, var(--danger), #c0392b); }
.preview-date-badge .day { display: block; font-size: 1.6rem; font-weight: 900; line-height: 1; }
.preview-date-badge .month { display: block; font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin-top: .2rem; }
.preview-date-badge .year { display: block; font-size: .6rem; opacity: .8; margin-top: .1rem; }

.preview-agenda-info h3 {
    font-size: .95rem;
    font-weight: 700;
    color: #222;
    line-height: 1.3;
    margin-bottom: .5rem;
    min-height: 2.4em;
}
.preview-desc {
    color: #666;
    font-size: .78rem;
    line-height: 1.5;
    margin-bottom: .5rem;
    max-height: 3em;
    overflow: hidden;
    min-height: 2.4em;
}
.preview-meta {
    display: flex;
    flex-wrap: wrap;
    gap: .6rem;
    font-size: .72rem;
    color: #666;
    margin-bottom: .5rem;
}
.preview-meta span {
    display: inline-flex;
    align-items: center;
    gap: .3rem;
}
.preview-meta i { color: var(--crimson); }
.preview-countdown {
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    background: rgba(135,206,235,.1);
    color: #0077b6;
    padding: .3rem .8rem;
    border-radius: 50px;
    font-size: .72rem;
    font-weight: 600;
    margin-top: .3rem;
}
.preview-countdown i { animation: spin 2s linear infinite; }
.preview-status-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-top: .6rem;
    margin-top: .6rem;
    border-top: 1px solid #eee;
    gap: .5rem;
    flex-wrap: wrap;
}
.preview-status-badge {
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    padding: .25rem .7rem;
    border-radius: 50px;
    font-size: .68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .5px;
}
.preview-status-badge.sts-akan_datang { background: rgba(135,206,235,.15); color: #0077b6; }
.preview-status-badge.sts-berlangsung { background: rgba(46,139,87,.15); color: var(--success); }
.preview-status-badge.sts-selesai { background: rgba(149,165,166,.15); color: #7f8c8d; }
.preview-status-badge.sts-batal { background: rgba(231,76,60,.15); color: var(--danger); }
.preview-cal-btn {
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    background: var(--crimson);
    color: #fff;
    padding: .3rem .8rem;
    border-radius: 50px;
    font-size: .72rem;
    font-weight: 700;
    border: none;
    cursor: pointer;
    text-decoration: none;
}
.preview-cal-btn:hover { background: var(--crimson-light); }

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
.agenda-empty {
    text-align: center;
    padding: 4rem 2rem;
    background: var(--panel-bg);
    border: 2px dashed var(--border-dark);
    border-radius: var(--radius-lg);
}
.agenda-empty-icon {
    width: 100px; height: 100px;
    margin: 0 auto 1.5rem;
    background: linear-gradient(135deg, var(--crimson), var(--crimson-light));
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 3rem;
    color: #fff;
    box-shadow: 0 10px 30px rgba(138,21,56,.3);
}
.agenda-empty h3 { color: #fff; margin-bottom: .5rem; font-size: 1.3rem; }
.agenda-empty p { color: var(--text-muted); margin-bottom: 1.5rem; max-width: 500px; margin-left: auto; margin-right: auto; }

/* ===== FORM GRID ===== */
.agenda-form-grid {
    display: grid;
    grid-template-columns: 1.4fr 1fr;
    gap: 2rem;
}
@media (max-width: 1100px) { .agenda-form-grid { grid-template-columns: 1fr; } }
@media (max-width: 992px) {
    .agenda-stats-grid { grid-template-columns: repeat(2, 1fr); }
    .sts-list { grid-template-columns: 1fr; }
    .agenda-filter-bar { grid-template-columns: 1fr; }
}
@media (max-width: 576px) { .agenda-stats-grid { grid-template-columns: 1fr; } }
/* Sinkronisasi warna date-badge dengan nilai status database */
.date-badge.akan_datang { background: linear-gradient(135deg, var(--sky-blue-dark), var(--sky-blue)); box-shadow: 0 4px 10px rgba(135,206,235,.3); }
.date-badge.berlangsung { background: linear-gradient(135deg, var(--success), #27ae60); box-shadow: 0 4px 10px rgba(46,139,87,.3); }
.date-badge.selesai { background: linear-gradient(135deg, #95a5a6, #7f8c8d); box-shadow: 0 4px 10px rgba(149,165,166,.2); }
.date-badge.batal { background: linear-gradient(135deg, var(--danger), #c0392b); box-shadow: 0 4px 10px rgba(231,76,60,.3); }
</style>

<?php if (isset($_GET['msg'])): 
    $msgs = [
        'saved' => ['success', 'Agenda berhasil disimpan!'],
        'deleted' => ['success', 'Agenda berhasil dihapus!'],
        'duplicated' => ['success', 'Agenda berhasil diduplikasi!'],
        'toggled' => ['success', 'Status agenda diperbarui!']
    ];
    if (isset($msgs[$_GET['msg']])): ?>
    <script>Swal.fire({icon:'<?=$msgs[$_GET['msg']][0]?>', title:'Berhasil!', text:'<?=$msgs[$_GET['msg']][1]?>', timer:2500, showConfirmButton:false});</script>
<?php endif; endif; ?>

<?php if (isset($error)): ?>
<script>Swal.fire({icon:'error', title:'Validasi Gagal', text:'<?= addslashes($error) ?>'});</script>
<?php endif; ?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;">
    <div>
        <h2 class="section-title"><i class="fa-solid fa-calendar-days"></i> <?= isset($_GET['edit']) ? 'Edit' : 'Kelola' ?> Agenda & Kalender</h2>
        <p style="color:var(--text-muted); font-size:.88rem; margin-top:.3rem;">
            <i class="fa-solid fa-circle-info"></i> Jadwal kegiatan akademik dan kemahasiswaan PBSI UMMaumere
        </p>
    </div>
    <div style="display:flex; gap:.8rem;">
        <?php if (isset($_GET['edit'])): ?>
        <a href="kelola_agenda.php" class="btn btn-secondary"><i class="fa-solid fa-xmark"></i> Batal</a>
        <?php else: ?>
        <a href="?action=add" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Tambah Agenda</a>
        <a href="../agenda.php" target="_blank" class="btn btn-secondary"><i class="fa-solid fa-external-link"></i> Lihat di Website</a>
        <?php endif; ?>
    </div>
</div>

<!-- ===== STATS DASHBOARD ===== -->
<div class="agenda-stats-grid">
    <div class="agenda-stat-card crimson">
        <div class="agenda-stat-icon"><i class="fa-solid fa-calendar-check"></i></div>
        <div>
            <div class="agenda-stat-value" data-counter="<?= $total_agenda ?>">0</div>
            <div class="agenda-stat-label">Total Agenda</div>
        </div>
    </div>
    <div class="agenda-stat-card sky" onclick="document.getElementById('statusFilter').value='akan_datang'; document.getElementById('statusFilter').dispatchEvent(new Event('change'));">
        <div class="agenda-stat-icon"><i class="fa-solid fa-clock"></i></div>
        <div>
            <div class="agenda-stat-value" data-counter="<?= $upcoming_count ?>">0</div>
            <div class="agenda-stat-label">Akan Datang</div>
        </div>
    </div>
    <div class="agenda-stat-card emerald" onclick="document.getElementById('statusFilter').value='berlangsung'; document.getElementById('statusFilter').dispatchEvent(new Event('change'));">
        <div class="agenda-stat-icon"><i class="fa-solid fa-play"></i></div>
        <div>
            <div class="agenda-stat-value" data-counter="<?= $ongoing_count ?>">0</div>
            <div class="agenda-stat-label">Berlangsung</div>
        </div>
    </div>
    <div class="agenda-stat-card grey" onclick="document.getElementById('statusFilter').value='selesai'; document.getElementById('statusFilter').dispatchEvent(new Event('change'));">
        <div class="agenda-stat-icon"><i class="fa-solid fa-circle-check"></i></div>
        <div>
            <div class="agenda-stat-value" data-counter="<?= $completed_count ?>">0</div>
            <div class="agenda-stat-label">Selesai</div>
        </div>
    </div>
</div>

<!-- ===== STATUS DISTRIBUTION ===== -->
<?php if ($total_agenda > 0): ?>
<div class="status-distribution">
    <div class="sts-dist-title">
        <i class="fa-solid fa-chart-pie"></i> Distribusi Agenda per Status
    </div>
    <div class="sts-list">
        <?php 
        $status_counts = [
            'akan_datang' => $upcoming_count,
            'berlangsung' => $ongoing_count,
            'selesai'     => $completed_count,
            'batal'  => $cancelled_count
        ];
        foreach ($status_info as $sKey => $sInfo):
            $count = $status_counts[$sKey] ?? 0;
            $percent = $total_agenda > 0 ? round(($count / $total_agenda) * 100, 1) : 0;
        ?>
        <div class="sts-item" onclick="document.getElementById('statusFilter').value='<?= $sKey ?>'; document.getElementById('statusFilter').dispatchEvent(new Event('change'));">
            <div class="sts-header">
                <div class="sts-info">
                    <div class="sts-icon <?= $sInfo['color'] ?>">
                        <i class="fa-solid <?= $sInfo['icon'] ?>"></i>
                    </div>
                    <div>
                        <div class="sts-name"><?= $sInfo['label'] ?></div>
                    </div>
                </div>
                <div class="sts-count"><?= $count ?></div>
            </div>
            <div class="sts-bar">
                <div class="sts-fill <?= $sInfo['color'] ?>" data-target="<?= $percent ?>"></div>
            </div>
            <div style="margin-top:.4rem; font-size:.72rem; color:var(--text-muted); text-align:right;">
                <?= $percent ?>% · <?= $count ?> agenda
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
            <?= isset($_GET['edit']) ? 'Edit' : 'Tambah' ?> Agenda
        </h3>
    </div>
    <div class="panel-body">
        <form method="POST" id="agendaForm">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="id" value="<?= $data['id'] ?>">
            
            <div class="agenda-form-grid">
                <!-- FORM FIELDS -->
                <div>
                    <div class="info-alert">
                        <i class="fa-solid fa-lightbulb"></i>
                        <div>
                            <strong>Tips:</strong> Pilih status "Otomatis" agar sistem menghitung status berdasarkan tanggal. Agenda yang akan datang akan menampilkan countdown timer di website.
                        </div>
                    </div>

                    <div class="form-section-title"><i class="fa-solid fa-heading"></i> Informasi Utama</div>
                    
                    <div class="form-group">
                        <label>Judul Agenda <span class="required">*</span></label>
                        <div class="input-icon-wrap">
                            <i class="fa-solid fa-heading"></i>
                            <input type="text" name="judul" id="f_judul" class="form-control" value="<?= htmlspecialchars((string)($data['judul'] ?? '')) ?>" required placeholder="Contoh: Yudisium Periode I 2026">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Deskripsi / Catatan</label>
                        <textarea name="deskripsi" id="f_deskripsi" rows="3" class="form-control" placeholder="Detail tambahan mengenai agenda..."><?= htmlspecialchars((string)($data['deskripsi'] ?? '')) ?></textarea>
                    </div>

                    <div class="form-section-title"><i class="fa-solid fa-calendar-days"></i> Waktu & Lokasi</div>
                    
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem;">
                        <div class="form-group">
                            <label>Tanggal & Waktu Mulai <span class="required">*</span></label>
                            <div class="input-icon-wrap">
                                <i class="fa-regular fa-calendar-plus"></i>
                                <input type="datetime-local" name="tanggal_mulai" id="f_tgl_mulai" class="form-control" value="<?= htmlspecialchars((string)($data['tanggal_mulai'] ?? '')) ?>" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Tanggal & Waktu Selesai</label>
                            <div class="input-icon-wrap">
                                <i class="fa-regular fa-calendar-check"></i>
                                <input type="datetime-local" name="tanggal_selesai" id="f_tgl_selesai" class="form-control" value="<?= htmlspecialchars((string)($data['tanggal_selesai'] ?? '')) ?>">
                            </div>
                            <small style="color:var(--text-muted);"><i class="fa-solid fa-info-circle"></i> Kosongkan jika agenda 1 hari</small>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Lokasi</label>
                        <div class="input-icon-wrap">
                            <i class="fa-solid fa-location-dot"></i>
                            <input type="text" name="lokasi" id="f_lokasi" class="form-control" value="<?= htmlspecialchars((string)($data['lokasi'] ?? '')) ?>" placeholder="Gedung FKIP, Aula, Zoom Meeting, dll">
                        </div>
                    </div>

                    <div class="form-section-title"><i class="fa-solid fa-tag"></i> Kategori & Status</div>
                    
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem;">
                        <div class="form-group">
                            <label>Tipe Kegiatan</label>
                            <select name="tipe" id="f_tipe" class="form-control select2-premium" style="width:100%;">
                                <?php foreach ($tipe_options as $t): ?>
                                <option value="<?= $t ?>" <?= ($data['tipe'] ?? '')==$t?'selected':'' ?>><?= $t ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status" id="f_status" class="form-control select2-premium" style="width:100%;">
                                <option value="auto" <?= ($data['status'] ?? '')==='auto'?'selected':'' ?>>🤖 Otomatis (Hitung dari Tanggal)</option>
                                <option value="akan_datang" <?= ($data['status'] ?? '')==='akan_datang'?'selected':'' ?>>🕒 Akan Datang</option>
                                <option value="berlangsung" <?= ($data['status'] ?? '')==='berlangsung'?'selected':'' ?>>🔴 Berlangsung</option>
                                <option value="selesai" <?= ($data['status'] ?? '')==='selesai'?'selected':'' ?>>✅ Selesai</option>
                                <option value="batal" <?= ($data['status'] ?? '')==='batal'?'selected':'' ?>>❌ batal</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-section-title"><i class="fa-solid fa-link"></i> Link & Pengaturan</div>
                    
                    <div class="form-group">
                        <label>Link URL Detail (Opsional)</label>
                        <div class="input-icon-wrap">
                            <i class="fa-solid fa-link"></i>
                            <input type="url" name="link" id="f_link" class="form-control" value="<?= htmlspecialchars((string)($data['link'] ?? '')) ?>" placeholder="https://... atau #">
                        </div>
                    </div>
                    
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem;">
                        <div class="form-group">
                            <label>Urutan Tampil</label>
                            <input type="number" name="urutan" class="form-control" value="<?= (int)($data['urutan'] ?? 1) ?>" min="1">
                        </div>
                        <div class="form-group">
                            <label>Status Aktif</label>
                            <label class="checkbox-wrap" style="padding:1rem; background:var(--bg-darker); border-radius:var(--radius-md); border:1px solid var(--border-dark); margin-top:.2rem;">
                                <input type="checkbox" name="aktif" value="1" <?= ($data['aktif'] ?? 1)==1?'checked':'' ?> style="width:18px; height:18px;">
                                <span style="font-weight:600;">Tampilkan di Website</span>
                            </label>
                        </div>
                    </div>

                    <div style="margin-top:2rem; display:flex; gap:1rem; padding-top:1.5rem; border-top:1px solid var(--border-dark);">
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> <?= $data['id']>0?'Perbarui':'Simpan' ?> Agenda</button>
                        <a href="kelola_agenda.php" class="btn btn-secondary"><i class="fa-solid fa-xmark"></i> Batal</a>
                    </div>
                </div>

                <!-- LIVE PREVIEW -->
                <div class="live-preview-wrapper">
                    <div class="live-preview-title">
                        <i class="fa-solid fa-eye"></i> LIVE PREVIEW (Tampilan di Website)
                    </div>
                    
                    <div class="preview-agenda-card" id="prevCard">
                        <div class="preview-date-badge" id="prevDateBadge">
                            <span class="day" id="prevDay">--</span>
                            <span class="month" id="prevMonth">---</span>
                            <span class="year" id="prevYear">----</span>
                        </div>
                        
                        <div class="preview-agenda-info">
                            <h3 id="prevJudul">Judul Agenda</h3>
                            <p class="preview-desc" id="prevDesc">Deskripsi agenda akan muncul di sini...</p>
                            
                            <div class="preview-meta" id="prevMeta">
                                <span id="prevTipeWrap"><i class="fa-solid fa-tag"></i> <span id="prevTipe">Seminar</span></span>
                                <span id="prevLokasiWrap" style="display:none;"><i class="fa-solid fa-location-dot"></i> <span id="prevLokasi"></span></span>
                                <span id="prevWaktuWrap"><i class="fa-solid fa-clock"></i> <span id="prevWaktu">--:--</span> WITA</span>
                            </div>
                            
                            <div class="preview-countdown" id="prevCountdown" style="display:none;">
                                <i class="fa-solid fa-hourglass-half"></i>
                                <span id="prevCountdownText">Menghitung...</span>
                            </div>
                            
                            <div class="preview-status-row">
                                <span class="preview-status-badge" id="prevStatusBadge">
                                    <i class="fa-solid fa-clock" id="prevStatusIcon"></i>
                                    <span id="prevStatusLabel">Akan Datang</span>
                                </span>
                                <button type="button" class="preview-cal-btn" onclick="previewAddToCalendar()" title="Tambah ke Google Calendar">
                                    <i class="fa-solid fa-calendar-plus"></i> Add to Calendar
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div style="margin-top:1rem; padding:.8rem; background:rgba(46,139,87,0.1); border:1px solid rgba(46,139,87,0.3); border-radius:var(--radius-md); font-size:.78rem; color:var(--text-muted);">
                        <i class="fa-solid fa-circle-check" style="color:var(--success);"></i>
                        <strong style="color:var(--success);"> Tip:</strong> Preview menampilkan tampilan persis seperti di halaman Agenda website. Status otomatis dihitung dari tanggal mulai & selesai.
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
        deskripsi: document.getElementById('f_deskripsi'),
        tgl_mulai: document.getElementById('f_tgl_mulai'),
        tgl_selesai: document.getElementById('f_tgl_selesai'),
        lokasi: document.getElementById('f_lokasi'),
        tipe: document.getElementById('f_tipe'),
        status: document.getElementById('f_status'),
        link: document.getElementById('f_link')
    };
    
    const prevJudul = document.getElementById('prevJudul');
    const prevDesc = document.getElementById('prevDesc');
    const prevDay = document.getElementById('prevDay');
    const prevMonth = document.getElementById('prevMonth');
    const prevYear = document.getElementById('prevYear');
    const prevDateBadge = document.getElementById('prevDateBadge');
    const prevTipe = document.getElementById('prevTipe');
    const prevLokasi = document.getElementById('prevLokasi');
    const prevLokasiWrap = document.getElementById('prevLokasiWrap');
    const prevWaktu = document.getElementById('prevWaktu');
    const prevCountdown = document.getElementById('prevCountdown');
    const prevCountdownText = document.getElementById('prevCountdownText');
    const prevCard = document.getElementById('prevCard');
    const prevStatusBadge = document.getElementById('prevStatusBadge');
    const prevStatusIcon = document.getElementById('prevStatusIcon');
    const prevStatusLabel = document.getElementById('prevStatusLabel');
    
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    
    const statusLabels = {
        'akan_datang': 'Akan Datang',
        'berlangsung': 'Berlangsung',
        'selesai': 'Selesai',
        'batal': 'Batal'
    };
    const statusIcons = {
        'akan_datang': 'fa-clock',
        'berlangsung': 'fa-play',
        'selesai': 'fa-circle-check',
        'batal': 'fa-circle-xmark'
    };
    
    function calculateStatus(startStr, endStr) {
        if (!startStr) return 'akan_datang';
        const now = new Date();
        const start = new Date(startStr);
        const end = endStr ? new Date(endStr) : start;
        if (now < start) return 'akan_datang';
        if (now >= start && now <= end) return 'berlangsung';
        return 'selesai';
    }
    
    function updateCountdown(startStr) {
        if (!startStr) {
            prevCountdown.style.display = 'none';
            return;
        }
        const start = new Date(startStr).getTime();
        const now = new Date().getTime();
        const distance = start - now;
        
        if (distance <= 0) {
            prevCountdown.style.display = 'none';
            return;
        }
        
        const days = Math.floor(distance / (1000 * 60 * 60 * 24));
        const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        
        let text = '';
        if (days > 0) text = `${days} hari lagi`;
        else if (hours > 0) text = `${hours} jam lagi`;
        else text = 'Segera dimulai!';
        
        prevCountdownText.textContent = text;
        prevCountdown.style.display = 'inline-flex';
    }
    
    function updatePreview() {
        // Judul & deskripsi
        prevJudul.textContent = fields.judul.value || 'Judul Agenda';
        prevDesc.textContent = fields.deskripsi.value || 'Deskripsi agenda akan muncul di sini...';
        
        // Tipe & lokasi
        prevTipe.textContent = fields.tipe.value || 'Seminar';
        if (fields.lokasi.value) {
            prevLokasi.textContent = fields.lokasi.value;
            prevLokasiWrap.style.display = 'inline-flex';
        } else {
            prevLokasiWrap.style.display = 'none';
        }
        
        // Tanggal
        const startStr = fields.tgl_mulai.value;
        if (startStr) {
            const d = new Date(startStr);
            prevDay.textContent = String(d.getDate()).padStart(2, '0');
            prevMonth.textContent = months[d.getMonth()];
            prevYear.textContent = d.getFullYear();
            prevWaktu.textContent = String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
        } else {
            prevDay.textContent = '--';
            prevMonth.textContent = '---';
            prevYear.textContent = '----';
            prevWaktu.textContent = '--:--';
        }
        
        // Status (hitung otomatis jika 'auto')
        let status = fields.status.value;
        if (status === 'auto') {
            status = calculateStatus(startStr, fields.tgl_selesai.value);
        }
        
        // Update visual classes
        prevCard.className = 'preview-agenda-card sts-' + status;
        prevDateBadge.className = 'preview-date-badge sts-' + status;
        prevStatusBadge.className = 'preview-status-badge sts-' + status;
        prevStatusIcon.className = 'fa-solid ' + statusIcons[status];
        prevStatusLabel.textContent = statusLabels[status];
        
        // Countdown (hanya untuk 'akan_datang')
        if (status === 'akan_datang') {
            updateCountdown(startStr);
        } else {
            prevCountdown.style.display = 'none';
        }
    }
    
    // Bind semua event
    Object.values(fields).forEach(f => {
        if (f) {
            f.addEventListener('input', updatePreview);
            f.addEventListener('change', updatePreview);
        }
    });
    
    // Initial render
    updatePreview();
    
    // Update countdown setiap menit
    setInterval(() => {
        if (fields.status.value === 'auto' || fields.status.value === 'akan_datang') {
            updatePreview();
        }
    }, 60000);
    
    // Global function untuk tombol Add to Calendar
    window.previewAddToCalendar = function() {
        const judul = fields.judul.value || 'Agenda';
        const lokasi = fields.lokasi.value || '';
        const desc = fields.deskripsi.value || '';
        const startStr = fields.tgl_mulai.value;
        const endStr = fields.tgl_selesai.value;
        
        if (!startStr) {
            Swal.fire({icon:'warning', title:'Tanggal Kosong', text:'Isi tanggal mulai terlebih dahulu'});
            return;
        }
        
        const start = new Date(startStr);
        const end = endStr ? new Date(endStr) : new Date(start.getTime() + 3600000);
        
        const format = (d) => d.toISOString().replace(/[-:]/g, '').split('.')[0] + 'Z';
        const googleUrl = `https://calendar.google.com/calendar/render?action=TEMPLATE&text=${encodeURIComponent(judul)}&dates=${format(start)}/${format(end)}&details=${encodeURIComponent(desc)}&location=${encodeURIComponent(lokasi)}`;
        window.open(googleUrl, '_blank');
    };
});
</script>

<?php else: ?>
<!-- ===== FILTER BAR ===== -->
<?php if (!empty($items)): ?>
<div class="agenda-filter-bar">
    <div class="form-group">
        <label><i class="fa-solid fa-search"></i> Cari Agenda</label>
        <input type="text" id="agendaSearch" class="form-control" placeholder="Ketik judul atau lokasi...">
    </div>
    <div class="form-group">
        <label><i class="fa-solid fa-tag"></i> Tipe</label>
        <select id="typeFilter" class="form-control">
            <option value="all">Semua Tipe</option>
            <?php foreach ($unique_types as $t): ?>
            <option value="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars($t) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label><i class="fa-solid fa-filter"></i> Status</label>
        <select id="statusFilter" class="form-control">
            <option value="all">Semua Status</option>
            <?php foreach ($status_info as $sKey => $sInfo): ?>
            <option value="<?= $sKey ?>"><?= $sInfo['label'] ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</div>
<?php endif; ?>

<!-- ===== LIST VIEW (GROUPED BY STATUS) ===== -->
<?php if (empty($items)): ?>
<div class="agenda-empty">
    <div class="agenda-empty-icon"><i class="fa-solid fa-calendar-days"></i></div>
    <h3>Belum Ada Agenda</h3>
    <p>Mulai jadwalkan kegiatan akademik dan kemahasiswaan PBSI UMMaumere</p>
    <a href="?action=add" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Tambah Agenda Pertama</a>
</div>
<?php else: ?>
<div class="panel">
    <div class="panel-body no-padding">
        <div class="table-wrapper">
            <table class="agenda-table">
                <thead>
                    <tr>
                        <th style="width:80px;">Tanggal</th>
                        <th>Judul & Info</th>
                        <th style="width:110px;">Tipe</th>
                        <th style="width:130px;">Status</th>
                        <th style="width:80px;">Aktif</th>
                        <th style="width:170px;">Aksi</th>
                    </tr>
                </thead>
                <tbody id="agendaTableBody">
                    <?php 
                    foreach ($by_status as $sKey => $statusItems):
                        if (empty($statusItems)) continue;
                        $info = $status_info[$sKey] ?? ['label'=>'Unknown', 'color'=>'grey', 'icon'=>'fa-circle'];
                        $activeCount = count(array_filter($statusItems, fn($x) => ($x['aktif'] ?? 1) == 1));
                        ?>
                        <tr class="status-group-header sts-<?= $sKey ?>" data-status="<?= $sKey ?>">
                            <td colspan="6">
                                <i class="fa-solid <?= $info['icon'] ?>"></i> 
                                <?= $info['label'] ?>
                                <span class="sts-stats">
                                    <i class="fa-solid fa-list"></i> <?= count($statusItems) ?> agenda · 
                                    <i class="fa-solid fa-check-circle"></i> <?= $activeCount ?> aktif
                                </span>
                            </td>
                        </tr>
                        <?php
                        foreach ($statusItems as $item): 
                            $token = hash_hmac('sha256', $item['id'], $_SESSION['csrf_token']);
                            $startTs = !empty($item['tanggal_mulai']) ? strtotime($item['tanggal_mulai']) : 0;
                            $searchText = strtolower(($item['judul'] ?? '') . ' ' . ($item['lokasi'] ?? '') . ' ' . ($item['deskripsi'] ?? ''));
                            $infoItem = $status_info[$item['status'] ?? 'akan_datang'] ?? $status_info['akan_datang'];
                            
                            // Hitung countdown untuk upcoming
                            $countdownText = '';
                            if ($startTs > time()) {
                                $daysLeft = ceil(($startTs - time()) / 86400);
                                $hoursLeft = floor((($startTs - time()) % 86400) / 3600);
                                if ($daysLeft > 0) $countdownText = "$daysLeft hari lagi";
                                else $countdownText = "$hoursLeft jam lagi";
                            }
                        ?>
                        <tr class="agenda-row"
                             data-search="<?= htmlspecialchars($searchText) ?>"
                             data-type="<?= htmlspecialchars($item['tipe'] ?? '') ?>"
                             data-status="<?= htmlspecialchars($item['status'] ?? 'akan_datang') ?>"
                             data-aktif="<?= $item['aktif'] ?? 1 ?>"
                             data-start="<?= $startTs ?>">
                            <td>
                                <?php if ($startTs > 0): ?>
                                <div class="date-badge <?= $item['status'] ?? 'akan_datang' ?>">
                                    <span class="day"><?= date('d', $startTs) ?></span>
                                    <span class="month"><?= date('M', $startTs) ?></span>
                                    <span class="year"><?= date('Y', $startTs) ?></span>
                                </div>
                                <?php else: ?>
                                <small style="color:var(--text-muted);">-</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="agenda-info-cell">
                                    <div class="agenda-title"><?= htmlspecialchars($item['judul']) ?></div>
                                    <div class="agenda-meta-cell">
                                        <?php if (!empty($item['lokasi'])): ?>
                                        <span><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($item['lokasi']) ?></span>
                                        <?php endif; ?>
                                        <?php if ($startTs > 0): ?>
                                        <span><i class="fa-solid fa-clock"></i> <?= date('H:i', $startTs) ?> WITA</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($countdownText): ?>
                                    <div class="agenda-countdown-cell">
                                        <i class="fa-solid fa-hourglass-half"></i>
                                        <span class="countdown-text" data-start="<?= $startTs ?>"><?= $countdownText ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td><span class="status-badge info"><?= htmlspecialchars($item['tipe'] ?? 'Lainnya') ?></span></td>
                            <td>
                                <span class="sts-badge <?= htmlspecialchars($item['status'] ?? 'akan_datang') ?>">
                                    <i class="fa-solid <?= $infoItem['icon'] ?>"></i>
                                    <?= $infoItem['label'] ?>
                                </span>
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
                                    <a href="?duplicate=<?= $item['id'] ?>&token=<?= $token ?>" class="table-action-btn" title="Duplikasi" onclick="return confirm('Duplikasi agenda ini?')"><i class="fa-solid fa-copy"></i></a>
                                    <a href="?delete=<?= $item['id'] ?>&token=<?= $token ?>" class="table-action-btn delete" onclick="return confirm('Yakin ingin menghapus agenda ini?')" title="Hapus"><i class="fa-solid fa-trash"></i></a>
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
    Menampilkan <strong id="agendaCount" style="color:var(--crimson);"><?= $total_agenda ?></strong> dari <strong><?= $total_agenda ?></strong> agenda
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('agendaSearch');
    const typeFilter = document.getElementById('typeFilter');
    const statusFilter = document.getElementById('statusFilter');
    const countEl = document.getElementById('agendaCount');
    
    function filterAgenda() {
        const term = (searchInput?.value || '').toLowerCase();
        const type = typeFilter?.value || 'all';
        const status = statusFilter?.value || 'all';
        
        const rows = document.querySelectorAll('.agenda-row');
        const headers = document.querySelectorAll('.status-group-header');
        let visibleCount = 0;
        
        rows.forEach(row => {
            const searchText = row.dataset.search || '';
            const rowType = row.dataset.type;
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
            const hStatus = header.dataset.status;
            const hasVisible = Array.from(rows).some(r => r.dataset.status === hStatus && r.style.display !== 'none');
            header.style.display = (status === 'all' || hStatus === status) && hasVisible ? '' : 'none';
        });
        
        if (countEl) countEl.textContent = visibleCount;
    }
    
    if (searchInput) searchInput.addEventListener('input', filterAgenda);
    if (typeFilter) typeFilter.addEventListener('change', filterAgenda);
    if (statusFilter) statusFilter.addEventListener('change', filterAgenda);
    
    // Real-time countdown update setiap menit
    function updateCountdowns() {
        document.querySelectorAll('.countdown-text[data-start]').forEach(el => {
            const startTs = parseInt(el.dataset.start);
            const distance = (startTs * 1000) - Date.now();
            if (distance <= 0) {
                el.parentElement.style.display = 'none';
                return;
            }
            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            if (days > 0) el.textContent = `${days} hari lagi`;
            else if (hours > 0) el.textContent = `${hours} jam lagi`;
            else el.textContent = 'Segera dimulai!';
        });
    }
    setInterval(updateCountdowns, 60000);
});
</script>
<?php endif; ?>
<?php endif; ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    if ($('#agendaForm').length && $.fn.select2) {
        $('.select2-premium').select2({ 
            dropdownParent: $('#agendaForm'),
            language: { noResults: function() { return "Tidak ditemukan"; } }
        });
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
    
    // Animated status bars
    setTimeout(() => {
        document.querySelectorAll('.sts-fill').forEach(bar => {
            bar.style.width = bar.dataset.target + '%';
        });
    }, 300);
});
</script>

<?php
$page_content = ob_get_clean();
require_once 'includes/admin_template.php';
?>