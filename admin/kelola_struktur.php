<?php
require_once '../includes/config.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';
require_once 'includes/upload_helper.php';
require_login();

$page_title = 'Kelola Struktur Organisasi';
$current_page = 'struktur';
$table = 'struktur';

// ===== AUTO-MIGRATION (Self-Healing Database) =====
// Otomatis buat kolom NIDN, email, link_scholar jika belum ada di tabel
$required_cols = [
    'nidn'         => "varchar(50) DEFAULT ''",
    'email'        => "varchar(100) DEFAULT ''",
    'link_scholar' => "varchar(255) DEFAULT ''",
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
    $stmt = $pdo->prepare("SELECT foto, nama FROM $table WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if ($row && !empty($row['foto']) && file_exists('../' . $row['foto'])) @unlink('../' . $row['foto']);
    $pdo->prepare("DELETE FROM $table WHERE id = ?")->execute([$id]);
    
    $pdo->prepare("INSERT INTO admin_logs (user_id, user_name, action, detail, ip) VALUES (?, ?, 'DELETE_STRUKTUR', ?, ?)")
        ->execute([$_SESSION['admin_id'], $_SESSION['admin_name'], 'Menghapus anggota struktur: ' . ($row['nama'] ?? 'ID '.$id), $_SERVER['REMOTE_ADDR']]);
    
    header("Location: kelola_struktur.php?msg=deleted");
    exit;
}

// ===== HANDLE DUPLICATE =====
if (isset($_GET['duplicate']) && isset($_GET['token']) && hash_equals(hash_hmac('sha256', (int)$_GET['duplicate'], $_SESSION['csrf_token']), $_GET['token'])) {
    $id = (int)$_GET['duplicate'];
    $stmt = $pdo->prepare("SELECT * FROM $table WHERE id = ?");
    $stmt->execute([$id]);
    $orig = $stmt->fetch();
    if ($orig) {
        $pdo->prepare("INSERT INTO $table (nama, jabatan, level, urutan, aktif, foto, nidn, email, link_scholar) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)")
            ->execute([
                $orig['nama'] . ' (Copy)', $orig['jabatan'], $orig['level'],
                $orig['urutan'] + 1, 0, $orig['foto'],
                $orig['nidn'] ?? '', $orig['email'] ?? '', $orig['link_scholar'] ?? ''
            ]);
    }
    header("Location: kelola_struktur.php?msg=duplicated");
    exit;
}

// ===== HANDLE TOGGLE STATUS =====
if (isset($_POST['toggle_status']) && isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $id = (int)$_POST['id'];
    $aktif = (int)$_POST['aktif'];
    $pdo->prepare("UPDATE $table SET aktif = ? WHERE id = ?")->execute([$aktif, $id]);
    header("Location: kelola_struktur.php?msg=toggled");
    exit;
}

// ===== HANDLE ADD/EDIT =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['toggle_status']) && isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $nama = sanitize($_POST['nama']);
    $jabatan = sanitize($_POST['jabatan']);
    $nidn = sanitize($_POST['nidn']);
    $email = sanitize($_POST['email']);
    $link_scholar = sanitize($_POST['link_scholar']);
    $level = (int)$_POST['level'];
    $urutan = (int)$_POST['urutan'];
    $aktif = isset($_POST['aktif']) ? 1 : 0;

    // Validasi
    if (empty($nama)) {
        $error = "Nama lengkap wajib diisi.";
    } elseif (empty($jabatan)) {
        $error = "Jabatan wajib diisi.";
    } elseif ($level < 1 || $level > 4) {
        $error = "Level harus antara 1-4.";
    } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Format email tidak valid.";
    } elseif (!empty($link_scholar) && !filter_var($link_scholar, FILTER_VALIDATE_URL)) {
        $error = "URL Google Scholar tidak valid.";
    } else {
        $fields = "nama=:nama, jabatan=:jabatan, nidn=:nidn, email=:email, link_scholar=:link_scholar, level=:level, urutan=:urutan, aktif=:aktif";
        $params = [':nama'=>$nama, ':jabatan'=>$jabatan, ':nidn'=>$nidn, ':email'=>$email, ':link_scholar'=>$link_scholar, ':level'=>$level, ':urutan'=>$urutan, ':aktif'=>$aktif];

        if (!empty($_FILES['foto']['name'])) {
            $upload = handle_upload($_FILES['foto'], '../assets/uploads/struktur/', ['jpg', 'jpeg', 'png', 'webp']);
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
                $action = 'UPDATE_STRUKTUR';
                $detail = "Memperbarui anggota struktur: $nama ($jabatan)";
            } else {
                $pdo->prepare("INSERT INTO $table SET $fields")->execute($params);
                $action = 'ADD_STRUKTUR';
                $detail = "Menambah anggota struktur: $nama ($jabatan)";
            }
            
            $pdo->prepare("INSERT INTO admin_logs (user_id, user_name, action, detail, ip) VALUES (?, ?, ?, ?, ?)")
                ->execute([$_SESSION['admin_id'], $_SESSION['admin_name'], $action, $detail, $_SERVER['REMOTE_ADDR']]);
            
            header("Location: kelola_struktur.php?msg=saved");
            exit;
        }
    }
}

// ===== FETCH DATA =====
$items = $pdo->query("SELECT * FROM $table ORDER BY level ASC, urutan ASC, id DESC")->fetchAll();

// Group by level
$by_level = [];
foreach ($items as $it) {
    $lv = (int)$it['level'];
    $by_level[$lv][] = $it;
}

// Statistics
$total_anggota = count($items);
$aktif_anggota = count(array_filter($items, fn($i) => $i['aktif'] == 1));
$total_level = count($by_level);
$max_level = !empty($by_level) ? max(array_keys($by_level)) : 0;

// Form data
$data = ['id'=>0, 'nama'=>'', 'jabatan'=>'', 'foto'=>'', 'nidn'=>'', 'email'=>'', 'link_scholar'=>'', 'level'=>1, 'urutan'=>1, 'aktif'=>1];
if (isset($_GET['edit']) && isset($_GET['token']) && hash_equals(hash_hmac('sha256', (int)$_GET['edit'], $_SESSION['csrf_token']), $_GET['token'])) {
    $stmt = $pdo->prepare("SELECT * FROM $table WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $fetched = $stmt->fetch();
    if ($fetched) {
        // Normalisasi NULL ke ''
        foreach ($fetched as $k => $v) {
            $data[$k] = ($v === null) ? '' : $v;
        }
    }
}

// Level info map
$level_info = [
    1 => ['label'=>'Pimpinan Tertinggi (Dekan)', 'color'=>'crimson', 'icon'=>'fa-crown'],
    2 => ['label'=>'Ketua Program Studi', 'color'=>'sky', 'icon'=>'fa-user-tie'],
    3 => ['label'=>'Sekretaris / Koordinator', 'color'=>'emerald', 'icon'=>'fa-user-shield'],
    4 => ['label'=>'Staf / Ketua Bidang', 'color'=>'warning', 'icon'=>'fa-user']
];

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
.struktur-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}
.struktur-stat-card {
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
.struktur-stat-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); }
.struktur-stat-icon {
    width: 52px; height: 52px;
    border-radius: var(--radius-md);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.4rem; flex-shrink: 0;
}
.struktur-stat-card.accent-crimson .struktur-stat-icon { background: rgba(138,21,56,0.15); color: var(--crimson); }
.struktur-stat-card.accent-sky .struktur-stat-icon { background: rgba(135,206,235,0.15); color: var(--sky-blue); }
.struktur-stat-card.accent-emerald .struktur-stat-icon { background: rgba(46,139,87,0.15); color: var(--success); }
.struktur-stat-card.accent-warning .struktur-stat-icon { background: rgba(243,156,18,0.15); color: var(--warning); }
.struktur-stat-value { font-size: 1.9rem; font-weight: 900; color: #fff; line-height: 1; }
.struktur-stat-label { color: var(--text-muted); font-size: .82rem; margin-top: .2rem; }

/* ===== LEVEL DISTRIBUTION ===== */
.level-distribution {
    background: var(--panel-bg);
    border: 1px solid var(--border-dark);
    border-radius: var(--radius-md);
    padding: 1.5rem;
    margin-bottom: 2rem;
}
.level-dist-title {
    font-size: 1rem; font-weight: 700; color: #fff;
    margin-bottom: 1.2rem;
    display: flex; align-items: center; gap: .6rem;
}
.level-dist-title i { color: var(--crimson); }
.level-list { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; }
.level-item {
    background: var(--bg-darker);
    border: 1px solid var(--border-dark);
    border-radius: var(--radius-md);
    padding: 1rem;
    cursor: pointer;
    transition: all .3s ease;
}
.level-item:hover { border-color: var(--crimson); transform: translateY(-2px); }
.level-header {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: .6rem;
}
.level-info { display: flex; align-items: center; gap: .5rem; }
.level-icon {
    width: 32px; height: 32px;
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: .9rem;
}
.level-icon.crimson { background: rgba(138,21,56,0.2); color: var(--crimson); }
.level-icon.sky { background: rgba(135,206,235,0.2); color: var(--sky-blue); }
.level-icon.emerald { background: rgba(46,139,87,0.2); color: var(--success); }
.level-icon.warning { background: rgba(243,156,18,0.2); color: var(--warning); }
.level-name { font-weight: 700; color: #fff; font-size: .85rem; line-height: 1.2; }
.level-count {
    font-weight: 800; font-size: .9rem;
    padding: .2rem .6rem;
    border-radius: 50px;
    background: var(--bg-darker);
    color: var(--text-main);
}
.level-bar {
    height: 6px;
    background: rgba(255,255,255,.05);
    border-radius: 50px;
    overflow: hidden;
}
.level-fill {
    height: 100%;
    border-radius: 50px;
    transition: width 1s ease;
    width: 0;
}
.level-fill.crimson { background: linear-gradient(90deg, var(--crimson), var(--crimson-light)); }
.level-fill.sky { background: linear-gradient(90deg, var(--sky-blue-dark), var(--sky-blue)); }
.level-fill.emerald { background: linear-gradient(90deg, var(--success), #27ae60); }
.level-fill.warning { background: linear-gradient(90deg, var(--warning), #e67e22); }

/* ===== ORG CHART PREVIEW ===== */
.org-chart-preview {
    background: linear-gradient(135deg, #1a1a2e, #16213e);
    border: 1px solid var(--border-dark);
    border-radius: var(--radius-lg);
    padding: 2rem 1rem;
    margin-bottom: 2rem;
    overflow-x: auto;
}
.org-chart-title {
    font-size: .85rem;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    color: var(--sky-blue);
    margin-bottom: 1.5rem;
    display: flex; align-items: center; gap: .5rem;
    font-weight: 700;
    text-align: center;
    justify-content: center;
}
.org-chart-title i { color: var(--crimson); }
.mini-chart {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1rem;
    min-width: 600px;
}
.mini-level {
    display: flex;
    gap: 1rem;
    justify-content: center;
    flex-wrap: wrap;
    position: relative;
    padding-top: 1rem;
}
.mini-level:not(:first-child)::before {
    content: '';
    position: absolute;
    top: 0; left: 50%;
    width: 2px; height: 1rem;
    background: linear-gradient(180deg, var(--sky-blue), transparent);
    transform: translateX(-50%);
}
.mini-card {
    background: var(--panel-bg);
    border: 1px solid var(--border-dark);
    border-radius: var(--radius-md);
    padding: .8rem;
    width: 140px;
    text-align: center;
    transition: all .3s ease;
    border-top: 3px solid var(--border-dark);
}
.mini-card:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,.3); }
.mini-card.lvl-1 { border-top-color: var(--crimson); }
.mini-card.lvl-2 { border-top-color: var(--sky-blue); }
.mini-card.lvl-3 { border-top-color: var(--success); }
.mini-card.lvl-4 { border-top-color: var(--warning); }
.mini-avatar {
    width: 50px; height: 50px;
    border-radius: 50%;
    margin: 0 auto .5rem;
    overflow: hidden;
    border: 2px solid var(--border-dark);
    background: linear-gradient(135deg, var(--crimson), var(--crimson-light));
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-weight: 900; font-size: 1.1rem;
}
.mini-avatar img { width: 100%; height: 100%; object-fit: cover; }
.mini-name {
    font-size: .75rem;
    font-weight: 700;
    color: #fff;
    margin-bottom: .2rem;
    line-height: 1.2;
    max-height: 2.4em;
    overflow: hidden;
}
.mini-jab {
    font-size: .65rem;
    color: var(--text-muted);
    line-height: 1.2;
}

/* ===== FILTER BAR ===== */
.struktur-filter-bar {
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
.struktur-filter-bar .form-group { margin: 0; }
.struktur-filter-bar label { font-size: .85rem; color: var(--text-muted); margin-bottom: .4rem; display: block; font-weight: 600; }

/* ===== TABLE GROUPED ===== */
.struktur-table thead th {
    background: var(--bg-darker);
    color: var(--sky-blue);
    font-size: .78rem;
    text-transform: uppercase;
    letter-spacing: .5px;
    padding: 12px 16px;
    font-weight: 700;
    border-bottom: 1px solid var(--border-dark);
}
.level-group-header {
    background: linear-gradient(90deg, rgba(138,21,56,.15), transparent);
    border-left: 4px solid var(--crimson);
}
.level-group-header.lvl-2 { background: linear-gradient(90deg, rgba(135,206,235,.12), transparent); border-left-color: var(--sky-blue); }
.level-group-header.lvl-3 { background: linear-gradient(90deg, rgba(46,139,87,.12), transparent); border-left-color: var(--success); }
.level-group-header.lvl-4 { background: linear-gradient(90deg, rgba(243,156,18,.12), transparent); border-left-color: var(--warning); }
.level-group-header td {
    padding: 12px 16px;
    font-weight: 800;
    color: #fff;
    font-size: .95rem;
}
.level-group-header .level-stats {
    float: right;
    font-size: .82rem;
    font-weight: 500;
    color: var(--text-muted);
}

.avatar-sm {
    width: 45px; height: 45px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid var(--border-dark);
    background: var(--bg-darker);
}
.avatar-initial {
    width: 45px; height: 45px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--crimson), var(--crimson-light));
    color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-weight: 900;
    font-size: 1.1rem;
    border: 2px solid var(--border-dark);
}

.level-badge {
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
.level-badge.crimson { background: rgba(138,21,56,0.2); color: var(--crimson); }
.level-badge.sky { background: rgba(135,206,235,0.2); color: var(--sky-blue); }
.level-badge.emerald { background: rgba(46,139,87,0.2); color: var(--success); }
.level-badge.warning { background: rgba(243,156,18,0.2); color: var(--warning); }

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

/* ===== LIVE PREVIEW CARD (Meniru Public Org Card) ===== */
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

.preview-org-card {
    background: #fff;
    border-radius: 16px;
    padding: 1.5rem;
    text-align: center;
    box-shadow: 0 10px 30px rgba(0,0,0,.3);
    color: #333;
    position: relative;
    overflow: hidden;
}
.preview-org-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 60px;
    background: linear-gradient(135deg, var(--crimson), var(--crimson-light));
}
.preview-photo {
    width: 100px; height: 100px;
    border-radius: 50%;
    margin: 0 auto 1rem;
    overflow: hidden;
    border: 4px solid #fff;
    position: relative;
    z-index: 2;
    background: linear-gradient(135deg, var(--crimson), var(--crimson-light));
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-weight: 900; font-size: 2.5rem;
    box-shadow: 0 6px 15px rgba(0,0,0,.15);
}
.preview-photo img { width: 100%; height: 100%; object-fit: cover; }
.preview-info h3 {
    font-size: 1.05rem;
    font-weight: 800;
    color: #222;
    margin-bottom: .3rem;
    line-height: 1.3;
}
.preview-jab {
    display: inline-block;
    background: rgba(138,21,56,.1);
    color: var(--crimson);
    padding: .25rem .8rem;
    border-radius: 50px;
    font-size: .75rem;
    font-weight: 700;
    margin-bottom: .5rem;
}
.preview-level-badge {
    display: inline-block;
    background: rgba(135,206,235,.2);
    color: #0077b6;
    padding: .2rem .6rem;
    border-radius: 50px;
    font-size: .68rem;
    font-weight: 700;
    margin-left: .3rem;
}
.preview-nidn {
    font-size: .78rem;
    color: #666;
    display: flex; align-items: center; justify-content: center;
    gap: .3rem;
    margin-top: .5rem;
}
.preview-nidn i { color: var(--sky-blue-dark); font-size: .7rem; }
.preview-socials {
    display: flex;
    gap: .4rem;
    justify-content: center;
    margin-top: .8rem;
}
.preview-socials a {
    width: 32px; height: 32px;
    border-radius: 50%;
    background: #f0f0f0;
    color: #333;
    display: flex; align-items: center; justify-content: center;
    font-size: .85rem;
    text-decoration: none;
    transition: all .2s;
}
.preview-socials a:hover { background: var(--crimson); color: #fff; transform: translateY(-2px); }

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
    font-weight: 700;
}
.form-section-title:first-child { margin-top: 0; }
.form-section-title i { color: var(--crimson); }

/* ===== FORM GRID ===== */
.struktur-form-grid {
    display: grid;
    grid-template-columns: 1.5fr 1fr;
    gap: 2rem;
}
@media (max-width: 992px) {
    .struktur-form-grid { grid-template-columns: 1fr; }
    .struktur-filter-bar { grid-template-columns: 1fr; }
    .level-list { grid-template-columns: 1fr; }
}
</style>

<?php if (isset($_GET['msg'])): 
    $msgs = [
        'saved' => ['success', 'Data struktur berhasil disimpan!'],
        'deleted' => ['success', 'Data struktur berhasil dihapus!'],
        'duplicated' => ['success', 'Anggota berhasil diduplikasi!'],
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
        <h2 class="section-title"><i class="fa-solid fa-sitemap"></i> <?= isset($_GET['edit']) ? 'Edit' : 'Kelola' ?> Struktur Organisasi</h2>
        <p style="color:var(--text-muted); font-size:.88rem; margin-top:.3rem;">
            <i class="fa-solid fa-circle-info"></i> Tata kelola program studi yang profesional, transparan, dan kolaboratif
        </p>
    </div>
    <div style="display:flex; gap:.8rem;">
        <?php if (isset($_GET['edit'])): ?>
        <a href="kelola_struktur.php" class="btn btn-secondary"><i class="fa-solid fa-xmark"></i> Batal</a>
        <?php else: ?>
        <a href="?action=add" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Tambah Anggota</a>
        <a href="../struktur.php" target="_blank" class="btn btn-secondary"><i class="fa-solid fa-external-link"></i> Lihat di Website</a>
        <?php endif; ?>
    </div>
</div>

<!-- ===== STATS DASHBOARD ===== -->
<div class="struktur-stats-grid">
    <div class="struktur-stat-card accent-crimson">
        <div class="struktur-stat-icon"><i class="fa-solid fa-users"></i></div>
        <div>
            <div class="struktur-stat-value" data-counter="<?= $total_anggota ?>">0</div>
            <div class="struktur-stat-label">Total Anggota</div>
        </div>
    </div>
    <div class="struktur-stat-card accent-emerald">
        <div class="struktur-stat-icon"><i class="fa-solid fa-check-circle"></i></div>
        <div>
            <div class="struktur-stat-value" data-counter="<?= $aktif_anggota ?>">0</div>
            <div class="struktur-stat-label">Anggota Aktif</div>
        </div>
    </div>
    <div class="struktur-stat-card accent-sky">
        <div class="struktur-stat-icon"><i class="fa-solid fa-layer-group"></i></div>
        <div>
            <div class="struktur-stat-value" data-counter="<?= $total_level ?>">0</div>
            <div class="struktur-stat-label">Level Aktif</div>
        </div>
    </div>
    <div class="struktur-stat-card accent-warning">
        <div class="struktur-stat-icon"><i class="fa-solid fa-crown"></i></div>
        <div>
            <div class="struktur-stat-value" data-counter="<?= $max_level ?>">0</div>
            <div class="struktur-stat-label">Level Tertinggi</div>
        </div>
    </div>
</div>

<!-- ===== LEVEL DISTRIBUTION ===== -->
<div class="level-distribution">
    <div class="level-dist-title">
        <i class="fa-solid fa-chart-pie"></i> Distribusi Anggota per Level
    </div>
    <div class="level-list">
        <?php for ($lv = 1; $lv <= 4; $lv++):
            $count = count($by_level[$lv] ?? []);
            $percent = $total_anggota > 0 ? round(($count / $total_anggota) * 100, 1) : 0;
            $info = $level_info[$lv];
        ?>
        <div class="level-item" onclick="document.getElementById('levelFilter').value='<?= $lv ?>'; document.getElementById('levelFilter').dispatchEvent(new Event('change'));">
            <div class="level-header">
                <div class="level-info">
                    <div class="level-icon <?= $info['color'] ?>">
                        <i class="fa-solid <?= $info['icon'] ?>"></i>
                    </div>
                    <div>
                        <div class="level-name">Level <?= $lv ?></div>
                        <small style="color:var(--text-muted); font-size:.7rem;"><?= $info['label'] ?></small>
                    </div>
                </div>
                <div class="level-count"><?= $count ?></div>
            </div>
            <div class="level-bar">
                <div class="level-fill <?= $info['color'] ?>" data-target="<?= $percent ?>"></div>
            </div>
            <div style="margin-top:.4rem; font-size:.72rem; color:var(--text-muted); text-align:right;">
                <?= $percent ?>%
            </div>
        </div>
        <?php endfor; ?>
    </div>
</div>

<!-- ===== ORG CHART PREVIEW ===== -->
<?php if (!empty($by_level)): ?>
<div class="org-chart-preview">
    <div class="org-chart-title">
        <i class="fa-solid fa-sitemap"></i> PREVIEW STRUKTUR ORGANISASI
    </div>
    <div class="mini-chart">
        <?php foreach ($by_level as $lv => $rows):
            $info = $level_info[$lv];
        ?>
        <div class="mini-level">
            <?php foreach ($rows as $m): 
                if ($m['aktif'] != 1) continue;
                $initial = strtoupper(substr($m['nama'] ?? 'U', 0, 1));
            ?>
            <div class="mini-card lvl-<?= $lv ?>" title="<?= htmlspecialchars($m['nama']) ?> - <?= htmlspecialchars($m['jabatan']) ?>">
                <div class="mini-avatar">
                    <?php if (!empty($m['foto'])): ?>
                        <img src="../<?= htmlspecialchars($m['foto']) ?>" alt="" onerror="this.outerHTML='<span><?= $initial ?></span>'">
                    <?php else: ?>
                        <span><?= $initial ?></span>
                    <?php endif; ?>
                </div>
                <div class="mini-name"><?= htmlspecialchars($m['nama']) ?></div>
                <div class="mini-jab"><?= htmlspecialchars($m['jabatan']) ?></div>
            </div>
            <?php endforeach; ?>
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
            <?= isset($_GET['edit']) ? 'Edit' : 'Tambah' ?> Anggota Struktur
        </h3>
    </div>
    <div class="panel-body">
        <form method="POST" enctype="multipart/form-data" id="strukturForm">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="id" value="<?= $data['id'] ?>">
            
            <div class="struktur-form-grid">
                <!-- FORM FIELDS -->
                <div>
                    <div class="form-section-title"><i class="fa-solid fa-user"></i> Informasi Pribadi</div>
                    
                    <div class="form-group">
                        <label>Nama Lengkap <span class="required">*</span></label>
                        <div class="input-icon-wrap">
                            <i class="fa-solid fa-user"></i>
                            <input type="text" name="nama" id="f_nama" class="form-control" value="<?= htmlspecialchars($data['nama'] ?? '') ?>" required placeholder="Dr. John Doe, M.Hum.">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Jabatan <span class="required">*</span></label>
                        <div class="input-icon-wrap">
                            <i class="fa-solid fa-briefcase"></i>
                            <input type="text" name="jabatan" id="f_jabatan" class="form-control" value="<?= htmlspecialchars($data['jabatan'] ?? '') ?>" required placeholder="Ketua Program Studi">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>NIDN</label>
                        <div class="input-icon-wrap">
                            <i class="fa-solid fa-id-card"></i>
                            <input type="text" name="nidn" id="f_nidn" class="form-control" value="<?= htmlspecialchars($data['nidn'] ?? '') ?>" placeholder="08123456789">
                        </div>
                    </div>

                    <div class="form-section-title"><i class="fa-solid fa-share-nodes"></i> Kontak & Sosial Media</div>
                    
                    <div class="form-group">
                        <label>Email</label>
                        <div class="input-icon-wrap">
                            <i class="fa-solid fa-envelope"></i>
                            <input type="email" name="email" id="f_email" class="form-control" value="<?= htmlspecialchars($data['email'] ?? '') ?>" placeholder="dosen@ummaumere.ac.id">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Google Scholar URL</label>
                        <div class="input-icon-wrap">
                            <i class="fa-solid fa-graduation-cap"></i>
                            <input type="url" name="link_scholar" id="f_scholar" class="form-control" value="<?= htmlspecialchars($data['link_scholar'] ?? '') ?>" placeholder="https://scholar.google.com/...">
                        </div>
                    </div>

                    <div class="form-section-title"><i class="fa-solid fa-sitemap"></i> Posisi Struktural</div>
                    
                    <div style="display:grid; grid-template-columns: 2fr 1fr; gap:1rem;">
                        <div class="form-group">
                            <label>Level Struktural</label>
                            <select name="level" id="f_level" class="form-control select2-premium" style="width:100%;">
                                <?php for ($i = 1; $i <= 4; $i++): $info = $level_info[$i]; ?>
                                <option value="<?= $i ?>" <?= ($data['level'] ?? 1)==$i?'selected':'' ?>>
                                    Level <?= $i ?> - <?= $info['label'] ?>
                                </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Urutan Tampil</label>
                            <input type="number" name="urutan" class="form-control" value="<?= $data['urutan'] ?? 1 ?>" min="1">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="checkbox-wrap" style="padding:1rem; background:var(--bg-darker); border-radius:var(--radius-md); border:1px solid var(--border-dark);">
                            <input type="checkbox" name="aktif" value="1" <?= ($data['aktif'] ?? 1)==1?'checked':'' ?> style="width:18px; height:18px;">
                            <span style="font-weight:600;">Tampilkan di Website (Status Aktif)</span>
                        </label>
                    </div>

                    <div style="margin-top:2rem; display:flex; gap:1rem; padding-top:1.5rem; border-top:1px solid var(--border-dark);">
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> <?= $data['id']>0?'Perbarui':'Simpan' ?> Data</button>
                        <a href="kelola_struktur.php" class="btn btn-secondary"><i class="fa-solid fa-xmark"></i> Batal</a>
                    </div>
                </div>

                <!-- LIVE PREVIEW -->
                <div class="live-preview-wrapper">
                    <div class="live-preview-title">
                        <i class="fa-solid fa-eye"></i> LIVE PREVIEW (Tampilan di Website)
                    </div>
                    
                    <div class="preview-org-card">
                        <div class="preview-photo" id="prevPhoto">
                            <img src="<?= !empty($data['foto']) ? '../'.htmlspecialchars($data['foto']) : '' ?>" id="prevPhotoImg" style="<?= empty($data['foto']) ? 'display:none;' : '' ?>">
                            <span id="prevInitial" style="<?= !empty($data['foto']) ? 'display:none;' : '' ?>"><?= strtoupper(substr($data['nama'] ?: 'U', 0, 1)) ?></span>
                        </div>
                        <div class="preview-info">
                            <h3 id="prevNama"><?= htmlspecialchars($data['nama'] ?: 'Nama Anggota') ?></h3>
                            <div>
                                <span class="preview-jab" id="prevJabatan"><?= htmlspecialchars($data['jabatan'] ?: 'Jabatan') ?></span>
                                <span class="preview-level-badge" id="prevLevel">Level <?= $data['level'] ?? 1 ?></span>
                            </div>
                            <div class="preview-nidn" id="prevNidnWrap" style="<?= empty($data['nidn']) ? 'display:none;' : '' ?>">
                                <i class="fa-solid fa-id-card"></i>
                                NIDN: <span id="prevNidn"><?= htmlspecialchars($data['nidn']) ?></span>
                            </div>
                            <div class="preview-socials" id="prevSocials">
                                <?php 
                                $hasSocial = !empty($data['email']) || !empty($data['link_scholar']);
                                if (!$hasSocial): ?>
                                <small style="color:#999; font-style:italic;">Belum ada kontak</small>
                                <?php endif; ?>
                                <?php if (!empty($data['email'])): ?>
                                <a href="#" id="prevEmail"><i class="fa-solid fa-envelope"></i></a>
                                <?php endif; ?>
                                <?php if (!empty($data['link_scholar'])): ?>
                                <a href="#" id="prevScholar"><i class="fa-solid fa-graduation-cap"></i></a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div style="margin-top:1rem;">
                        <label style="font-size:.82rem; color:var(--text-muted); display:block; margin-bottom:.5rem;">Foto Profil</label>
                        <div class="profile-upload-wrapper">
                            <div class="profile-preview" id="profilePreview">
                                <?php if (!empty($data['foto'])): ?>
                                    <img src="../<?= htmlspecialchars($data['foto']) ?>" id="previewImg">
                                <?php else: ?>
                                    <i class="fa-solid fa-user-tie placeholder-icon"></i>
                                <?php endif; ?>
                            </div>
                            <div class="profile-upload-btn">
                                <button type="button" class="btn btn-secondary btn-sm"><i class="fa-solid fa-camera"></i> Ganti Foto</button>
                                <input type="file" name="foto" accept="image/*" id="fileInput">
                            </div>
                            <small style="color:var(--text-muted);">JPG/PNG. Maks 2MB. Rasio 1:1.</small>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const fields = {
        nama: document.getElementById('f_nama'),
        jabatan: document.getElementById('f_jabatan'),
        nidn: document.getElementById('f_nidn'),
        email: document.getElementById('f_email'),
        scholar: document.getElementById('f_scholar'),
        level: document.getElementById('f_level'),
        file: document.getElementById('fileInput')
    };
    
    const prevNama = document.getElementById('prevNama');
    const prevJabatan = document.getElementById('prevJabatan');
    const prevLevel = document.getElementById('prevLevel');
    const prevNidn = document.getElementById('prevNidn');
    const prevNidnWrap = document.getElementById('prevNidnWrap');
    const prevInitial = document.getElementById('prevInitial');
    const prevPhotoImg = document.getElementById('prevPhotoImg');
    const prevSocials = document.getElementById('prevSocials');
    
    fields.nama?.addEventListener('input', e => {
        prevNama.textContent = e.target.value || 'Nama Anggota';
        if (prevInitial) prevInitial.textContent = e.target.value ? e.target.value.charAt(0).toUpperCase() : 'U';
    });
    fields.jabatan?.addEventListener('input', e => {
        prevJabatan.textContent = e.target.value || 'Jabatan';
    });
    fields.level?.addEventListener('change', e => {
        prevLevel.textContent = 'Level ' + e.target.value;
    });
    fields.nidn?.addEventListener('input', e => {
        if (e.target.value) {
            prevNidnWrap.style.display = 'flex';
            prevNidn.textContent = e.target.value;
        } else {
            prevNidnWrap.style.display = 'none';
        }
    });
    
    function updateSocials() {
        const emailVal = fields.email?.value || '';
        const scholarVal = fields.scholar?.value || '';
        let html = '';
        if (emailVal) html += `<a href="mailto:${emailVal}"><i class="fa-solid fa-envelope"></i></a>`;
        if (scholarVal) html += `<a href="${scholarVal}" target="_blank"><i class="fa-solid fa-graduation-cap"></i></a>`;
        if (!html) html = '<small style="color:#999; font-style:italic;">Belum ada kontak</small>';
        if (prevSocials) prevSocials.innerHTML = html;
    }
    fields.email?.addEventListener('input', updateSocials);
    fields.scholar?.addEventListener('input', updateSocials);
    
    fields.file?.addEventListener('change', e => {
        if (e.target.files.length) {
            const reader = new FileReader();
            reader.onload = ev => {
                if (prevPhotoImg) {
                    prevPhotoImg.src = ev.target.result;
                    prevPhotoImg.style.display = 'block';
                }
                if (prevInitial) prevInitial.style.display = 'none';
                
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
<div class="struktur-filter-bar">
    <div class="form-group">
        <label><i class="fa-solid fa-search"></i> Cari Anggota</label>
        <input type="text" id="strukturSearch" class="form-control" placeholder="Ketik nama atau jabatan...">
    </div>
    <div class="form-group">
        <label><i class="fa-solid fa-layer-group"></i> Filter Level</label>
        <select id="levelFilter" class="form-control">
            <option value="all">Semua Level</option>
            <?php for ($i = 1; $i <= 4; $i++): ?>
            <option value="<?= $i ?>">Level <?= $i ?> - <?= $level_info[$i]['label'] ?></option>
            <?php endfor; ?>
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

<!-- ===== TABLE GROUPED BY LEVEL ===== -->
<?php if (empty($items)): ?>
<div style="text-align:center; padding:4rem 2rem; background:var(--panel-bg); border:2px dashed var(--border-dark); border-radius:var(--radius-lg);">
    <div style="width:100px; height:100px; margin:0 auto 1.5rem; background:linear-gradient(135deg, var(--crimson), var(--crimson-light)); border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:3rem; color:#fff; box-shadow:0 10px 30px rgba(138,21,56,.3);">
        <i class="fa-solid fa-sitemap"></i>
    </div>
    <h3 style="color:#fff; margin-bottom:.5rem;">Belum Ada Anggota Struktur</h3>
    <p style="color:var(--text-muted); margin-bottom:1.5rem; max-width:500px; margin-left:auto; margin-right:auto;">Mulai bangun struktur organisasi prodi Anda dengan menambahkan anggota per level</p>
    <a href="?action=add" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Tambah Anggota Pertama</a>
</div>
<?php else: ?>
<div class="panel">
    <div class="panel-body no-padding">
        <div class="table-wrapper">
            <table class="struktur-table">
                <thead>
                    <tr>
                        <th style="width:60px;">Foto</th>
                        <th>Nama Lengkap</th>
                        <th style="width:100px;">Level</th>
                        <th style="width:80px;">Urutan</th>
                        <th style="width:90px;">Status</th>
                        <th style="width:180px;">Aksi</th>
                    </tr>
                </thead>
                <tbody id="strukturTableBody">
                    <?php 
                    $current_level = 0;
                    foreach ($items as $item): 
                        $token = hash_hmac('sha256', $item['id'], $_SESSION['csrf_token']);
                        $lv = (int)$item['level'];
                        $info = $level_info[$lv] ?? ['label'=>'Unknown', 'color'=>'crimson', 'icon'=>'fa-user'];
                        
                        // Level group header
                        if ($lv !== $current_level) {
                            $current_level = $lv;
                            $count = count($by_level[$lv] ?? []);
                            $active_count = count(array_filter($by_level[$lv] ?? [], fn($x) => $x['aktif'] == 1));
                            ?>
                            <tr class="level-group-header lvl-<?= $lv ?>" data-level="<?= $lv ?>">
                                <td colspan="6">
                                    <i class="fa-solid <?= $info['icon'] ?>"></i> 
                                    Level <?= $lv ?> - <?= $info['label'] ?>
                                    <span class="level-stats">
                                        <i class="fa-solid fa-users"></i> <?= $count ?> anggota · 
                                        <i class="fa-solid fa-check-circle"></i> <?= $active_count ?> aktif
                                    </span>
                                </td>
                            </tr>
                            <?php
                        }
                        
                        $initial = strtoupper(substr($item['nama'] ?? 'U', 0, 1));
                    ?>
                    <tr class="struktur-row" 
                         data-search="<?= strtolower(htmlspecialchars(($item['nama'] ?? '') . ' ' . ($item['jabatan'] ?? '') . ' ' . ($item['email'] ?? ''))) ?>"
                         data-level="<?= $lv ?>"
                         data-status="<?= $item['aktif'] ?>">
                        <td>
                            <?php if (!empty($item['foto'])): ?>
                            <img src="../<?= htmlspecialchars($item['foto']) ?>" class="avatar-sm" alt="<?= htmlspecialchars($item['nama']) ?>" onerror="this.outerHTML='<div class=avatar-initial><?= $initial ?></div>'">
                            <?php else: ?>
                            <div class="avatar-initial"><?= $initial ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($item['nama']) ?></strong>
                            <br><small style="color:var(--text-muted);">
                                <i class="fa-solid fa-briefcase" style="color:var(--crimson);"></i> 
                                <?= htmlspecialchars($item['jabatan']) ?>
                            </small>
                            <?php if (!empty($item['email'])): ?>
                            <br><small style="color:var(--text-muted); font-size:.72rem;">
                                <i class="fa-solid fa-envelope" style="color:var(--sky-blue);"></i> 
                                <?= htmlspecialchars($item['email']) ?>
                            </small>
                            <?php endif; ?>
                            <?php if (!empty($item['nidn'])): ?>
                            <br><small style="color:var(--text-muted); font-size:.72rem;">
                                <i class="fa-solid fa-id-card" style="color:var(--sky-blue);"></i> 
                                NIDN: <?= htmlspecialchars($item['nidn']) ?>
                            </small>
                            <?php endif; ?>
                        </td>
                        <td><span class="level-badge <?= $info['color'] ?>"><i class="fa-solid <?= $info['icon'] ?>"></i> Lv.<?= $lv ?></span></td>
                        <td><?= $item['urutan'] ?></td>
                        <td>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="toggle_status" value="1">
                                <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                <input type="hidden" name="aktif" value="<?= $item['aktif'] == 1 ? 0 : 1 ?>">
                                <label class="toggle-switch" title="<?= $item['aktif'] == 1 ? 'Nonaktifkan' : 'Aktifkan' ?>">
                                    <input type="checkbox" <?= $item['aktif'] == 1 ? 'checked' : '' ?> onchange="this.form.submit()">
                                    <span class="toggle-slider"></span>
                                </label>
                            </form>
                        </td>
                        <td>
                            <div class="table-actions">
                                <a href="?edit=<?= $item['id'] ?>&token=<?= $token ?>" class="table-action-btn edit" title="Edit"><i class="fa-solid fa-pen"></i></a>
                                <a href="?duplicate=<?= $item['id'] ?>&token=<?= $token ?>" class="table-action-btn" title="Duplikasi" onclick="return confirm('Duplikasi anggota ini?')"><i class="fa-solid fa-copy"></i></a>
                                <a href="?delete=<?= $item['id'] ?>&token=<?= $token ?>" class="table-action-btn delete" onclick="return confirm('Yakin ingin menghapus <?= htmlspecialchars($item['nama']) ?>?')" title="Hapus"><i class="fa-solid fa-trash"></i></a>
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
    Menampilkan <strong id="strukturCount" style="color:var(--crimson);"><?= $total_anggota ?></strong> dari <strong><?= $total_anggota ?></strong> anggota struktur
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('strukturSearch');
    const levelFilter = document.getElementById('levelFilter');
    const statusFilter = document.getElementById('statusFilter');
    const countEl = document.getElementById('strukturCount');
    
    function filterStruktur() {
        const term = (searchInput?.value || '').toLowerCase();
        const level = levelFilter?.value || 'all';
        const status = statusFilter?.value || 'all';
        
        const rows = document.querySelectorAll('.struktur-row');
        const headers = document.querySelectorAll('.level-group-header');
        let visibleCount = 0;
        
        rows.forEach(row => {
            const searchText = row.dataset.search || '';
            const rowLevel = row.dataset.level;
            const rowStatus = row.dataset.status;
            
            const matchSearch = !term || searchText.includes(term);
            const matchLevel = level === 'all' || rowLevel === level;
            const matchStatus = status === 'all' || rowStatus === status;
            
            if (matchSearch && matchLevel && matchStatus) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        
        // Hide headers yang tidak punya row visible
        headers.forEach(header => {
            const hLevel = header.dataset.level;
            const hasVisible = Array.from(rows).some(r => r.dataset.level === hLevel && r.style.display !== 'none');
            header.style.display = (level === 'all' || hLevel === level) && hasVisible ? '' : 'none';
        });
        
        if (countEl) countEl.textContent = visibleCount;
    }
    
    if (searchInput) searchInput.addEventListener('input', filterStruktur);
    if (levelFilter) levelFilter.addEventListener('change', filterStruktur);
    if (statusFilter) statusFilter.addEventListener('change', filterStruktur);
});
</script>
<?php endif; ?>
<?php endif; ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    if ($('#strukturForm').length && $.fn.select2) {
        $('.select2-premium').select2({ dropdownParent: $('#strukturForm') });
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
    
    // Animated level bars
    setTimeout(() => {
        document.querySelectorAll('.level-fill').forEach(bar => {
            bar.style.width = bar.dataset.target + '%';
        });
    }, 300);
});
</script>

<?php
$page_content = ob_get_clean();
require_once 'includes/admin_template.php';
?>