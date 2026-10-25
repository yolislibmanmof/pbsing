<?php
require_once '../includes/config.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';
require_once 'includes/upload_helper.php';
require_login();

$page_title = 'Kelola Dosen';
$current_page = 'dosen';
$table = 'dosen';

// ===== AUTO-MIGRATION (Self-Healing Database) =====
$required_cols = [
    'bio'            => "text DEFAULT NULL",
    'social_media'   => "json DEFAULT NULL",
    'is_featured'    => "tinyint(1) DEFAULT 0",
    'pendidikan'     => "varchar(255) DEFAULT ''",
    'orcid'          => "varchar(100) DEFAULT ''",
    'scopus_id'      => "varchar(100) DEFAULT ''",
    'researchgate'   => "varchar(255) DEFAULT ''",
    'linkedin'       => "varchar(255) DEFAULT ''",
    'google_scholar' => "varchar(255) DEFAULT ''",
];
$existing_cols = $pdo->query("SHOW COLUMNS FROM $table")->fetchAll(PDO::FETCH_COLUMN);
foreach ($required_cols as $col => $def) {
    if (!in_array($col, $existing_cols, true)) {
        $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$col` $def");
    }
}

// ===== HANDLE DELETE =====
if (isset($_GET['delete']) && isset($_GET['token']) && hash_equals($_SESSION['csrf_token'], $_GET['token'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("SELECT nama, foto FROM $table WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if ($row) {
        if (!empty($row['foto']) && !filter_var($row['foto'], FILTER_VALIDATE_URL) && file_exists('../' . $row['foto'])) {
            @unlink('../' . $row['foto']);
        }
        $pdo->prepare("DELETE FROM $table WHERE id = ?")->execute([$id]);
        $pdo->prepare("INSERT INTO admin_logs (user_id, user_name, action, detail, ip) VALUES (?, ?, 'DELETE_DOSEN', ?, ?)")
            ->execute([$_SESSION['admin_id'], $_SESSION['admin_name'], 'Menghapus dosen: ' . $row['nama'], $_SERVER['REMOTE_ADDR']]);
    }
    header("Location: kelola_dosen.php?msg=deleted");
    exit;
}

// ===== HANDLE DUPLICATE =====
if (isset($_GET['duplicate']) && isset($_GET['token']) && hash_equals($_SESSION['csrf_token'], $_GET['token'])) {
    $id = (int)$_GET['duplicate'];
    $stmt = $pdo->prepare("SELECT * FROM $table WHERE id = ?");
    $stmt->execute([$id]);
    $orig = $stmt->fetch();
    if ($orig) {
        $pdo->prepare("INSERT INTO $table (nama, nidn, gelar_depan, gelar_belakang, bidang_keahlian, email, urutan, aktif, social_media, bio, pendidikan, orcid, scopus_id, researchgate, linkedin, google_scholar) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
            ->execute([
                $orig['nama'] . ' (Copy)',
                $orig['nidn'], $orig['gelar_depan'], $orig['gelar_belakang'],
                $orig['bidang_keahlian'], $orig['email'],
                ($orig['urutan'] ?? 0) + 1, 0,
                $orig['social_media'] ?? null,
                $orig['bio'] ?? '',
                $orig['pendidikan'] ?? '',
                $orig['orcid'] ?? '', $orig['scopus_id'] ?? '',
                $orig['researchgate'] ?? '', $orig['linkedin'] ?? '',
                $orig['google_scholar'] ?? ''
            ]);
        $pdo->prepare("INSERT INTO admin_logs (user_id, user_name, action, detail, ip) VALUES (?, ?, 'DUPLICATE_DOSEN', ?, ?)")
            ->execute([$_SESSION['admin_id'], $_SESSION['admin_name'], 'Menduplikasi dosen: ' . $orig['nama'], $_SERVER['REMOTE_ADDR']]);
    }
    header("Location: kelola_dosen.php?msg=duplicated");
    exit;
}

// ===== HANDLE BULK DELETE =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete']) && isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $ids = $_POST['bulk_ids'] ?? [];
    $deleted = 0;
    foreach ($ids as $id) {
        $id = (int)$id;
        $stmt = $pdo->prepare("SELECT foto FROM $table WHERE id = ?");
        $stmt->execute([$id]);
        $ft = $stmt->fetchColumn();
        if ($ft && !filter_var($ft, FILTER_VALIDATE_URL) && file_exists('../' . $ft)) @unlink('../' . $ft);
        $pdo->prepare("DELETE FROM $table WHERE id = ?")->execute([$id]);
        $deleted++;
    }
    if ($deleted > 0) {
        $pdo->prepare("INSERT INTO admin_logs (user_id, user_name, action, detail, ip) VALUES (?, ?, 'BULK_DELETE_DOSEN', ?, ?)")
            ->execute([$_SESSION['admin_id'], $_SESSION['admin_name'], "Menghapus $deleted dosen", $_SERVER['REMOTE_ADDR']]);
    }
    header("Location: kelola_dosen.php?msg=bulk_deleted&count=$deleted");
    exit;
}

// ===== HANDLE TOGGLE STATUS =====
if (isset($_POST['toggle_status']) && isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $id = (int)$_POST['id'];
    $aktif = (int)$_POST['aktif'];
    $pdo->prepare("UPDATE $table SET aktif = ? WHERE id = ?")->execute([$aktif, $id]);
    $pdo->prepare("INSERT INTO admin_logs (user_id, user_name, action, detail, ip) VALUES (?, ?, 'TOGGLE_DOSEN', ?, ?)")
        ->execute([$_SESSION['admin_id'], $_SESSION['admin_name'], "Toggle status dosen ID $id → " . ($aktif ? 'Aktif' : 'Nonaktif'), $_SERVER['REMOTE_ADDR']]);
    header("Location: kelola_dosen.php?msg=toggled");
    exit;
}

// ===== HANDLE TOGGLE FEATURED =====
if (isset($_POST['toggle_featured']) && isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $id = (int)$_POST['id'];
    $is_featured = (int)$_POST['is_featured'];
    $pdo->prepare("UPDATE $table SET is_featured = ? WHERE id = ?")->execute([$is_featured, $id]);
    header("Location: kelola_dosen.php?msg=featured");
    exit;
}

// ===== HANDLE ADD/EDIT =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['toggle_status']) && !isset($_POST['toggle_featured']) && !isset($_POST['bulk_delete']) && isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $nama = sanitize($_POST['nama']);
    $nidn = sanitize($_POST['nidn']);
    $gelar_depan = sanitize($_POST['gelar_depan']);
    $gelar_belakang = sanitize($_POST['gelar_belakang']);
    $bidang_keahlian = sanitize($_POST['bidang_keahlian']);
    $email = sanitize($_POST['email']);
    $urutan = (int)$_POST['urutan'];
    $aktif = isset($_POST['aktif']) ? 1 : 0;
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    $bio = $_POST['bio'] ?? '';
    $pendidikan = sanitize($_POST['pendidikan']);
    $google_scholar = sanitize($_POST['google_scholar']);
    $orcid = sanitize($_POST['orcid']);
    $scopus_id = sanitize($_POST['scopus_id']);
    $researchgate = sanitize($_POST['researchgate']);
    $linkedin = sanitize($_POST['linkedin']);

    // Validasi
    if (empty($nama)) {
        $error = "Nama lengkap wajib diisi.";
    } else {
        $data_save = [
            'nama' => $nama, 'nidn' => $nidn,
            'gelar_depan' => $gelar_depan, 'gelar_belakang' => $gelar_belakang,
            'bidang_keahlian' => $bidang_keahlian, 'email' => $email,
            'urutan' => $urutan, 'aktif' => $aktif, 'is_featured' => $is_featured,
            'bio' => $bio, 'pendidikan' => $pendidikan,
            'google_scholar' => $google_scholar, 'orcid' => $orcid,
            'scopus_id' => $scopus_id, 'researchgate' => $researchgate,
            'linkedin' => $linkedin
        ];

        // Handle foto upload
        if (!empty($_FILES['foto']['name'])) {
            $upload = handle_upload($_FILES['foto'], '../assets/uploads/dosen/', ['jpg', 'jpeg', 'png', 'webp']);
            if ($upload['success']) {
                $data_save['foto'] = $upload['path'];
                if ($id > 0) {
                    $stmt = $pdo->prepare("SELECT foto FROM $table WHERE id = ?");
                    $stmt->execute([$id]);
                    $old = $stmt->fetch();
                    if ($old && !empty($old['foto']) && !filter_var($old['foto'], FILTER_VALIDATE_URL) && file_exists('../' . $old['foto'])) {
                        @unlink('../' . $old['foto']);
                    }
                }
            } else {
                $error = $upload['error'];
            }
        }

        if (!isset($error)) {
            $sets = [];
            $params = [];
            foreach ($data_save as $k => $v) {
                $sets[] = "$k = :$k";
                $params[":$k"] = $v;
            }

            if ($id > 0) {
                $sql = "UPDATE $table SET " . implode(', ', $sets) . " WHERE id = :id";
                $params[':id'] = $id;
                $pdo->prepare($sql)->execute($params);
                $action = 'UPDATE_DOSEN';
                $detail = "Memperbarui dosen: $nama";
            } else {
                $sql = "INSERT INTO $table SET " . implode(', ', $sets);
                $pdo->prepare($sql)->execute($params);
                $action = 'ADD_DOSEN';
                $detail = "Menambah dosen baru: $nama";
            }

            $pdo->prepare("INSERT INTO admin_logs (user_id, user_name, action, detail, ip) VALUES (?, ?, ?, ?, ?)")
                ->execute([$_SESSION['admin_id'], $_SESSION['admin_name'], $action, $detail, $_SERVER['REMOTE_ADDR']]);

            header("Location: kelola_dosen.php?msg=saved");
            exit;
        }
    }
}

// ===== FETCH DATA =====
$items = $pdo->query("SELECT * FROM $table ORDER BY urutan ASC, id DESC")->fetchAll();

// Normalize NULL
foreach ($items as &$it) {
    foreach ($it as $k => $v) {
        if ($v === null) $it[$k] = '';
    }
}
unset($it);

// Statistics
$total_dosen = count($items);
$aktif_count = count(array_filter($items, fn($i) => ($i['aktif'] ?? 1) == 1));
$featured_count = count(array_filter($items, fn($i) => ($i['is_featured'] ?? 0) == 1));

// Count by bidang keahlian
$by_bidang = [];
foreach ($items as $it) {
    $b = $it['bidang_keahlian'] ?? 'Lainnya';
    $by_bidang[$b] = ($by_bidang[$b] ?? 0) + 1;
}
arsort($by_bidang);

// Unique bidang for filter
$unique_bidang = array_keys($by_bidang);
sort($unique_bidang);

// Form data
$data = [
    'id'=>0, 'nama'=>'', 'nidn'=>'', 'gelar_depan'=>'', 'gelar_belakang'=>'',
    'bidang_keahlian'=>'Linguistics', 'foto'=>'', 'email'=>'',
    'urutan'=>1, 'aktif'=>1, 'is_featured'=>0, 'bio'=>'', 'pendidikan'=>'',
    'google_scholar'=>'', 'orcid'=>'', 'scopus_id'=>'', 'researchgate'=>'', 'linkedin'=>''
];

if (isset($_GET['edit']) && isset($_GET['token']) && hash_equals($_SESSION['csrf_token'], $_GET['token'])) {
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
.ql-stroke { stroke: var(--text-muted) !important; }
.ql-fill { fill: var(--text-muted) !important; }
.ql-picker { color: var(--text-muted) !important; }
.ql-editor { color: var(--text-main); min-height: 180px; }
.ql-editor.ql-blank::before { color: var(--text-dim); font-style: italic; }

/* ===== STATS GRID ===== */
.dosen-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}
.dosen-stat-card {
    background: var(--panel-bg);
    border: 1px solid var(--border-dark);
    border-radius: var(--radius-md);
    padding: 1.3rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: all .3s ease;
    border-left: 4px solid var(--accent-color, var(--crimson));
    cursor: pointer;
}
.dosen-stat-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); }
.dosen-stat-icon {
    width: 52px; height: 52px;
    border-radius: var(--radius-md);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.4rem; flex-shrink: 0;
}
.dosen-stat-card.accent-crimson { --accent-color: var(--crimson); }
.dosen-stat-card.accent-crimson .dosen-stat-icon { background: rgba(138,21,56,0.15); color: var(--crimson); }
.dosen-stat-card.accent-sky { --accent-color: var(--sky-blue); }
.dosen-stat-card.accent-sky .dosen-stat-icon { background: rgba(135,206,235,0.15); color: var(--sky-blue); }
.dosen-stat-card.accent-emerald { --accent-color: var(--success); }
.dosen-stat-card.accent-emerald .dosen-stat-icon { background: rgba(46,139,87,0.15); color: var(--success); }
.dosen-stat-card.accent-warning { --accent-color: var(--warning); }
.dosen-stat-card.accent-warning .dosen-stat-icon { background: rgba(243,156,18,0.15); color: var(--warning); }
.dosen-stat-value { font-size: 1.9rem; font-weight: 900; color: #fff; line-height: 1; }
.dosen-stat-label { color: var(--text-muted); font-size: .82rem; margin-top: .2rem; }

/* ===== BIDANG DISTRIBUTION ===== */
.bidang-distribution {
    background: var(--panel-bg);
    border: 1px solid var(--border-dark);
    border-radius: var(--radius-md);
    padding: 1.5rem;
    margin-bottom: 2rem;
}
.bidang-dist-title {
    font-size: 1rem; font-weight: 700; color: #fff;
    margin-bottom: 1.2rem;
    display: flex; align-items: center; gap: .6rem;
}
.bidang-dist-title i { color: var(--crimson); }
.bidang-list { display: flex; flex-direction: column; gap: 1rem; }
.bidang-item { display: flex; flex-direction: column; gap: .4rem; }
.bidang-header { display: flex; justify-content: space-between; align-items: center; }
.bidang-name { font-weight: 600; color: var(--text-main); font-size: .9rem; }
.bidang-count { font-weight: 700; font-size: .85rem; color: var(--sky-blue); }
.bidang-bar { height: 8px; background: var(--bg-darker); border-radius: 50px; overflow: hidden; }
.bidang-fill {
    height: 100%; border-radius: 50px;
    transition: width 1s ease; width: 0;
    background: linear-gradient(90deg, var(--crimson), var(--crimson-light));
}

/* ===== FILTER BAR ===== */
.dosen-filter-bar {
    background: var(--panel-bg);
    border: 1px solid var(--border-dark);
    border-radius: var(--radius-md);
    padding: 1.2rem;
    margin-bottom: 1.5rem;
    display: grid;
    grid-template-columns: 2fr 1fr 1fr auto;
    gap: 1rem;
    align-items: end;
}
.dosen-filter-bar .form-group { margin: 0; }
.dosen-filter-bar label { font-size: .85rem; color: var(--text-muted); margin-bottom: .4rem; display: block; font-weight: 600; }

/* ===== BULK ACTIONS BAR ===== */
.bulk-actions-bar {
    background: linear-gradient(135deg, rgba(138,21,56,.15), rgba(138,21,56,.05));
    border: 1px solid var(--crimson);
    border-radius: var(--radius-md);
    padding: .8rem 1.2rem;
    margin-bottom: 1rem;
    display: none;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    animation: slideDown .3s ease;
}
.bulk-actions-bar.active { display: flex; }
@keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
.bulk-info { display: flex; align-items: center; gap: .6rem; color: var(--text-main); font-weight: 600; font-size: .88rem; }
.bulk-count { background: var(--crimson); color: #fff; padding: .15rem .7rem; border-radius: 50px; font-size: .78rem; font-weight: 800; }

/* ===== DOSEN CARD (Grid Layout) ===== */
.dosen-card-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}
.dosen-card {
    background: var(--panel-bg);
    border: 1px solid var(--border-dark);
    border-radius: var(--radius-md);
    overflow: hidden;
    transition: all .3s ease;
    position: relative;
}
.dosen-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-lg);
    border-color: var(--crimson);
}
.dosen-card.featured { border-color: rgba(243,156,18,0.5); }
.dosen-card.featured::before {
    content: '⭐ Featured';
    position: absolute;
    top: 10px; right: 10px;
    background: linear-gradient(135deg, #f39c12, #e67e22);
    color: #fff;
    padding: .2rem .6rem;
    border-radius: 50px;
    font-size: .68rem;
    font-weight: 800;
    z-index: 2;
    text-transform: uppercase;
    letter-spacing: .5px;
    box-shadow: 0 2px 8px rgba(243,156,18,0.3);
}
.dosen-card-header {
    padding: 1.5rem;
    background: linear-gradient(135deg, rgba(138,21,56,0.08), rgba(135,206,235,0.05));
    display: flex;
    gap: 1rem;
    align-items: center;
    border-bottom: 1px solid var(--border-dark);
}
.dosen-card-avatar {
    width: 72px; height: 72px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--crimson), var(--crimson-light));
    display: flex; align-items: center; justify-content: center;
    color: #fff;
    font-size: 1.8rem;
    font-weight: 900;
    flex-shrink: 0;
    overflow: hidden;
    border: 3px solid var(--border-dark);
    box-shadow: 0 4px 12px rgba(138,21,56,0.25);
}
.dosen-card-avatar img { width: 100%; height: 100%; object-fit: cover; }
.dosen-card-info { flex: 1; min-width: 0; }
.dosen-card-name {
    font-weight: 800;
    color: #fff;
    font-size: .98rem;
    line-height: 1.3;
    margin-bottom: .3rem;
}
.dosen-card-nidn {
    font-size: .72rem;
    color: var(--sky-blue);
    font-family: monospace;
    margin-bottom: .3rem;
}
.dosen-card-bidang {
    display: inline-block;
    background: rgba(138,21,56,0.15);
    color: var(--crimson);
    padding: .2rem .6rem;
    border-radius: 50px;
    font-size: .7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .5px;
}
.dosen-card-body { padding: 1.2rem; }
.dosen-card-meta {
    display: flex;
    flex-direction: column;
    gap: .5rem;
    font-size: .82rem;
    color: var(--text-muted);
    margin-bottom: 1rem;
}
.dosen-card-meta-row {
    display: flex;
    align-items: center;
    gap: .5rem;
}
.dosen-card-meta-row i {
    color: var(--crimson);
    width: 16px;
    text-align: center;
    flex-shrink: 0;
}
.dosen-card-meta-row a { color: var(--sky-blue); text-decoration: none; word-break: break-all; }
.dosen-card-meta-row a:hover { color: #fff; text-decoration: underline; }
.dosen-card-bio {
    font-size: .82rem;
    color: var(--text-muted);
    line-height: 1.5;
    max-height: 3em;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-bottom: 1rem;
    padding-bottom: .8rem;
    border-bottom: 1px solid var(--border-dark);
}
.dosen-card-footer {
    display: flex;
    gap: .3rem;
    padding-top: .8rem;
    border-top: 1px solid var(--border-dark);
    align-items: center;
}
.dosen-card-footer .toggle-switch { margin-right: auto; }
.dosen-card-actions { display: flex; gap: .3rem; }

/* ===== MODERN TOGGLE ===== */
.toggle-switch { position: relative; display: inline-block; width: 42px; height: 22px; flex-shrink: 0; }
.toggle-switch input { opacity: 0; width: 0; height: 0; }
.toggle-slider { position: absolute; cursor: pointer; inset: 0; background: var(--bg-darker); border: 1px solid var(--border-dark); transition: .3s; border-radius: 50px; }
.toggle-slider:before { position: absolute; content: ""; height: 16px; width: 16px; left: 2px; bottom: 2px; background: var(--text-muted); transition: .3s; border-radius: 50%; }
.toggle-switch input:checked + .toggle-slider { background: var(--success); border-color: var(--success); }
.toggle-switch input:checked + .toggle-slider:before { transform: translateX(20px); background: #fff; }

/* ===== PROFILE UPLOAD PREMIUM ===== */
.profile-upload-wrapper {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1rem;
}
.profile-preview {
    width: 180px; height: 180px;
    border-radius: 50%;
    border: 4px solid var(--border-dark);
    background: var(--bg-darker);
    display: flex; align-items: center; justify-content: center;
    overflow: hidden;
    position: relative;
    transition: all .3s ease;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
}
.profile-preview:hover { border-color: var(--crimson); transform: scale(1.02); }
.profile-preview img { width: 100%; height: 100%; object-fit: cover; }
.profile-preview .placeholder-icon { font-size: 4rem; color: var(--text-muted); opacity: .3; }
.profile-upload-btn {
    position: relative; overflow: hidden; display: inline-block;
    padding: .6rem 1.2rem;
    background: var(--bg-darker);
    border: 1px solid var(--border-dark);
    color: var(--text-main);
    border-radius: var(--radius-md);
    cursor: pointer;
    font-weight: 600;
    font-size: .85rem;
    transition: all .2s;
    display: inline-flex;
    align-items: center;
    gap: .5rem;
}
.profile-upload-btn:hover { border-color: var(--crimson); color: var(--crimson); }
.profile-upload-btn input[type="file"] {
    position: absolute; left: 0; top: 0; opacity: 0;
    width: 100%; height: 100%; cursor: pointer;
}
.profile-upload-helper {
    font-size: .78rem;
    color: var(--text-muted);
    text-align: center;
}

/* ===== SOCIAL LINK GRID ===== */
.social-link-group {
    display: grid;
    grid-template-columns: 42px 1fr;
    gap: .5rem;
    align-items: center;
    margin-bottom: .8rem;
    padding: .6rem .8rem;
    background: var(--bg-darker);
    border: 1px solid var(--border-dark);
    border-radius: var(--radius-md);
    transition: all .2s;
}
.social-link-group:focus-within { border-color: var(--crimson); background: rgba(138,21,56,0.05); }
.soc-icon {
    width: 32px; height: 32px;
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    color: #fff;
    font-size: .95rem;
    flex-shrink: 0;
}
.soc-icon.scholar { background: #4285F4; }
.soc-icon.orcid { background: #A6CE39; }
.soc-icon.scopus { background: #E9711C; }
.soc-icon.rgate { background: #00D0BB; }
.soc-icon.linkedin { background: #0A66C2; }
.social-link-group input {
    background: transparent;
    border: none;
    color: var(--text-main);
    font-size: .88rem;
    outline: none;
    padding: .3rem 0;
}

/* ===== FORM GRID ===== */
.dosen-form-grid {
    display: grid;
    grid-template-columns: 1.5fr 1fr;
    gap: 2rem;
}
@media (max-width: 1100px) { .dosen-form-grid { grid-template-columns: 1fr; } }

/* ===== SECTION TITLE ===== */
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

/* ===== INPUT ICON ===== */
.input-icon-wrap { position: relative; }
.input-icon-wrap i.input-icon {
    position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
    color: var(--text-muted); font-size: .9rem; pointer-events: none;
    transition: color .2s;
}
.input-icon-wrap:focus-within i.input-icon { color: var(--crimson); }
.input-icon-wrap .form-control { padding-left: 42px; }

/* ===== LIVE PREVIEW ===== */
.dosen-live-preview {
    position: sticky;
    top: 80px;
    align-self: start;
    background: var(--bg-darker);
    border: 2px dashed var(--border-light);
    border-radius: var(--radius-md);
    padding: 1.5rem;
}
.preview-title {
    font-size: .78rem;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    color: var(--sky-blue);
    margin-bottom: 1rem;
    display: flex; align-items: center; gap: .5rem;
    font-weight: 700;
}
.preview-title i { color: var(--crimson); }

.preview-dosen-card {
    background: #fff;
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 15px 40px rgba(0,0,0,0.3);
    color: #333;
}
.preview-dosen-header {
    background: linear-gradient(135deg, #8A1538, #A01A42);
    padding: 2rem 1.5rem;
    text-align: center;
    position: relative;
}
.preview-dosen-avatar {
    width: 120px; height: 120px;
    border-radius: 50%;
    background: #fff;
    margin: 0 auto 1rem;
    display: flex; align-items: center; justify-content: center;
    overflow: hidden;
    border: 4px solid #fff;
    box-shadow: 0 8px 24px rgba(0,0,0,0.3);
    color: #8A1538;
    font-size: 3rem;
    font-weight: 900;
}
.preview-dosen-avatar img { width: 100%; height: 100%; object-fit: cover; }
.preview-dosen-name {
    color: #fff;
    font-size: 1.15rem;
    font-weight: 800;
    line-height: 1.3;
    margin-bottom: .3rem;
}
.preview-dosen-nidn {
    color: rgba(255,255,255,.8);
    font-size: .78rem;
    font-family: monospace;
}
.preview-dosen-body { padding: 1.5rem; }
.preview-dosen-bidang {
    display: inline-block;
    background: rgba(138,21,56,.1);
    color: #8A1538;
    padding: .3rem .8rem;
    border-radius: 50px;
    font-size: .72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .5px;
    margin-bottom: .8rem;
}
.preview-dosen-bio {
    font-size: .82rem;
    color: #555;
    line-height: 1.6;
    min-height: 3em;
}
.preview-dosen-social {
    display: flex;
    gap: .5rem;
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid #eee;
    flex-wrap: wrap;
}
.preview-social-link {
    width: 36px; height: 36px;
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    color: #fff;
    text-decoration: none;
    font-size: .95rem;
    transition: transform .2s;
}
.preview-social-link:hover { transform: translateY(-2px); }

/* ===== INFO BOX ===== */
.info-box {
    padding: 1rem 1.2rem;
    border-radius: var(--radius-md);
    display: flex;
    align-items: flex-start;
    gap: .8rem;
    margin-bottom: 1.5rem;
    font-size: .85rem;
    line-height: 1.5;
}
.info-box.tip {
    background: rgba(46,139,87,0.1);
    border: 1px solid rgba(46,139,87,0.3);
    border-left: 4px solid var(--success);
    color: var(--text-main);
}
.info-box.info {
    background: rgba(135,206,235,0.1);
    border: 1px solid rgba(135,206,235,0.3);
    border-left: 4px solid var(--sky-blue);
    color: var(--text-main);
}
.info-box i { font-size: 1.1rem; margin-top: 2px; flex-shrink: 0; }
.info-box.tip i { color: var(--success); }
.info-box.info i { color: var(--sky-blue); }
.info-box strong { display: block; margin-bottom: .2rem; font-size: .9rem; }

/* ===== EMPTY STATE ===== */
.dosen-empty {
    text-align: center;
    padding: 4rem 2rem;
    background: var(--panel-bg);
    border: 2px dashed var(--border-dark);
    border-radius: var(--radius-lg);
}
.dosen-empty-icon {
    width: 100px; height: 100px;
    margin: 0 auto 1.5rem;
    background: linear-gradient(135deg, var(--crimson), var(--crimson-light));
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 3rem;
    color: #fff;
    box-shadow: 0 10px 30px rgba(138,21,56,.3);
}
.dosen-empty h3 { color: #fff; margin-bottom: .5rem; font-size: 1.3rem; }
.dosen-empty p { color: var(--text-muted); margin-bottom: 1.5rem; max-width: 500px; margin-left: auto; margin-right: auto; }

/* ===== RESPONSIVE ===== */
@media (max-width: 992px) {
    .dosen-filter-bar { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 576px) {
    .dosen-filter-bar { grid-template-columns: 1fr; }
    .dosen-stats-grid { grid-template-columns: repeat(2, 1fr); }
}
</style>

<?php if (isset($_GET['msg'])): 
    $msgs = [
        'saved' => ['success', 'Data dosen berhasil disimpan!'],
        'deleted' => ['success', 'Data dosen berhasil dihapus!'],
        'duplicated' => ['success', 'Dosen berhasil diduplikasi!'],
        'toggled' => ['success', 'Status dosen diperbarui!'],
        'featured' => ['success', 'Status featured diperbarui!']
    ];
    if (isset($msgs[$_GET['msg']])): ?>
    <script>Swal.fire({icon:'<?=$msgs[$_GET['msg']][0]?>', title:'Berhasil!', text:'<?=$msgs[$_GET['msg']][1]?>', timer:2500, showConfirmButton:false});</script>
<?php endif; 
    if ($_GET['msg'] === 'bulk_deleted'): ?>
    <script>Swal.fire({icon:'success', title:'Berhasil!', text:'<?= (int)($_GET['count'] ?? 0) ?> dosen berhasil dihapus.', timer:2500, showConfirmButton:false});</script>
<?php endif; 
endif; ?>

<?php if (isset($error)): ?>
<script>Swal.fire({icon:'error', title:'Validasi Gagal', text:'<?= addslashes($error) ?>'});</script>
<?php endif; ?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;">
    <div>
        <h2 class="section-title"><i class="fa-solid fa-chalkboard-user"></i> <?= isset($_GET['edit']) ? 'Edit' : 'Kelola' ?> Dosen & Pengajar</h2>
        <p style="color:var(--text-muted); font-size:.88rem; margin-top:.3rem;">
            <i class="fa-solid fa-circle-info"></i> Tenaga pendidik profesional PBSI UMMaumere
        </p>
    </div>
    <div style="display:flex; gap:.8rem;">
        <?php if (isset($_GET['edit'])): ?>
        <a href="kelola_dosen.php" class="btn btn-secondary"><i class="fa-solid fa-xmark"></i> Batal</a>
        <?php else: ?>
        <a href="?action=add" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Tambah Dosen</a>
        <a href="../dosen.php" target="_blank" class="btn btn-secondary"><i class="fa-solid fa-external-link"></i> Lihat di Website</a>
        <?php endif; ?>
    </div>
</div>

<?php if (!isset($_GET['edit']) && !isset($_GET['action'])): ?>
<!-- ===== STATS DASHBOARD ===== -->
<div class="dosen-stats-grid">
    <div class="dosen-stat-card accent-crimson">
        <div class="dosen-stat-icon"><i class="fa-solid fa-chalkboard-user"></i></div>
        <div>
            <div class="dosen-stat-value" data-counter="<?= $total_dosen ?>">0</div>
            <div class="dosen-stat-label">Total Dosen</div>
        </div>
    </div>
    <div class="dosen-stat-card accent-emerald" onclick="document.getElementById('statusFilter').value='active'; document.getElementById('statusFilter').dispatchEvent(new Event('change'));">
        <div class="dosen-stat-icon"><i class="fa-solid fa-user-check"></i></div>
        <div>
            <div class="dosen-stat-value" data-counter="<?= $aktif_count ?>">0</div>
            <div class="dosen-stat-label">Aktif</div>
        </div>
    </div>
    <div class="dosen-stat-card accent-warning" onclick="document.getElementById('featuredFilter').value='1'; document.getElementById('featuredFilter').dispatchEvent(new Event('change'));">
        <div class="dosen-stat-icon"><i class="fa-solid fa-star"></i></div>
        <div>
            <div class="dosen-stat-value" data-counter="<?= $featured_count ?>">0</div>
            <div class="dosen-stat-label">Featured</div>
        </div>
    </div>
    <div class="dosen-stat-card accent-sky">
        <div class="dosen-stat-icon"><i class="fa-solid fa-graduation-cap"></i></div>
        <div>
            <div class="dosen-stat-value" data-counter="<?= count($by_bidang) ?>">0</div>
            <div class="dosen-stat-label">Bidang Keahlian</div>
        </div>
    </div>
</div>

<!-- ===== BIDANG DISTRIBUTION ===== -->
<?php if (count($by_bidang) > 0): ?>
<div class="bidang-distribution">
    <div class="bidang-dist-title">
        <i class="fa-solid fa-chart-pie"></i> Distribusi Dosen per Bidang Keahlian
    </div>
    <div class="bidang-list">
        <?php foreach ($by_bidang as $bidang => $count): 
            $percent = $total_dosen > 0 ? round(($count / $total_dosen) * 100, 1) : 0;
        ?>
        <div class="bidang-item">
            <div class="bidang-header">
                <span class="bidang-name"><i class="fa-solid fa-book" style="color:var(--crimson); margin-right:.4rem;"></i><?= htmlspecialchars($bidang) ?></span>
                <span class="bidang-count"><?= $count ?> Dosen (<?= $percent ?>%)</span>
            </div>
            <div class="bidang-bar">
                <div class="bidang-fill" data-target="<?= $percent ?>"></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<?php if (isset($_GET['action']) && $_GET['action'] === 'add' || isset($_GET['edit'])): ?>
<!-- ===== FORM ADD/EDIT ===== -->
<div class="panel">
    <div class="panel-header">
        <h3 class="panel-title">
            <i class="fa-solid fa-pen-to-square"></i> 
            <?= isset($_GET['edit']) ? 'Edit' : 'Tambah' ?> Dosen
        </h3>
    </div>
    <div class="panel-body">
        <form method="POST" enctype="multipart/form-data" id="dosenForm">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="id" value="<?= $data['id'] ?>">
            <input type="hidden" name="bio" id="hiddenBio" value="<?= htmlspecialchars($data['bio']) ?>">
            
            <div class="dosen-form-grid">
                <!-- LEFT: FORM FIELDS -->
                <div>
                    <div class="info-box info">
                        <i class="fa-solid fa-circle-info"></i>
                        <div>
                            <strong>Data Dosen Profesional</strong>
                            Lengkapi informasi akademik dan sosial media untuk ditampilkan di halaman profil dosen. Dosen "Featured" akan disorot di halaman beranda.
                        </div>
                    </div>
                    
                    <!-- FOTO -->
                    <div class="form-section-title"><i class="fa-solid fa-camera-retro"></i> Foto Profil</div>
                    <div class="profile-upload-wrapper">
                        <div class="profile-preview" id="profilePreview">
                            <?php if (!empty($data['foto'])): ?>
                                <img src="../<?= htmlspecialchars($data['foto']) ?>" id="previewImg" alt="Foto">
                            <?php else: ?>
                                <i class="fa-solid fa-user placeholder-icon"></i>
                            <?php endif; ?>
                        </div>
                        <label class="profile-upload-btn">
                            <i class="fa-solid fa-camera"></i>
                            <?= !empty($data['foto']) ? 'Ganti Foto' : 'Upload Foto' ?>
                            <input type="file" name="foto" accept="image/*" id="fileInput">
                        </label>
                        <div class="profile-upload-helper">
                            <i class="fa-solid fa-info-circle"></i>
                            JPG/PNG/WebP. Maks 2MB. Rasio 1:1 (persegi) direkomendasikan.
                        </div>
                    </div>
                    
                    <!-- IDENTITAS AKADEMIK -->
                    <div class="form-section-title"><i class="fa-solid fa-id-card"></i> Identitas Akademik</div>
                    
                    <div style="display:grid; grid-template-columns: 1fr 2fr 1fr; gap:1rem;">
                        <div class="form-group">
                            <label>Gelar Depan</label>
                            <div class="input-icon-wrap">
                                <i class="fa-solid fa-award input-icon"></i>
                                <input type="text" name="gelar_depan" class="form-control" value="<?= htmlspecialchars($data['gelar_depan']) ?>" placeholder="Dr." data-preview="gelar_depan">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Nama Lengkap <span class="required">*</span></label>
                            <div class="input-icon-wrap">
                                <i class="fa-solid fa-user input-icon"></i>
                                <input type="text" name="nama" id="f_nama" class="form-control" value="<?= htmlspecialchars($data['nama']) ?>" required data-preview="nama" placeholder="Nama Lengkap">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Gelar Belakang</label>
                            <div class="input-icon-wrap">
                                <i class="fa-solid fa-award input-icon"></i>
                                <input type="text" name="gelar_belakang" class="form-control" value="<?= htmlspecialchars($data['gelar_belakang']) ?>" placeholder="M.Hum." data-preview="gelar_belakang">
                            </div>
                        </div>
                    </div>
                    
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem;">
                        <div class="form-group">
                            <label>NIDN</label>
                            <div class="input-icon-wrap">
                                <i class="fa-solid fa-hashtag input-icon"></i>
                                <input type="text" name="nidn" class="form-control" value="<?= htmlspecialchars($data['nidn']) ?>" placeholder="08123456789" data-preview="nidn">
                            </div>
                            <small style="color:var(--text-muted);"><i class="fa-solid fa-info-circle"></i> Nomor Induk Dosen Nasional</small>
                        </div>
                        <div class="form-group">
                            <label>Bidang Keahlian</label>
                            <select name="bidang_keahlian" id="f_bidang" class="form-control select2-premium" style="width:100%;">
                                <?php foreach (['Linguistics', 'Literature', 'Translation', 'TEFL', 'Applied Linguistics', 'Creative Writing', 'Sociolinguistics', 'Post-Colonial Studies'] as $bidang): ?>
                                <option value="<?= $bidang ?>" <?= ($data['bidang_keahlian'] ?? '') == $bidang ? 'selected' : '' ?>><?= $bidang ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Pendidikan Terakhir</label>
                        <div class="input-icon-wrap">
                            <i class="fa-solid fa-graduation-cap input-icon"></i>
                            <input type="text" name="pendidikan" class="form-control" value="<?= htmlspecialchars($data['pendidikan'] ?? '') ?>" placeholder="S3 Linguistik - Universitas Indonesia">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Email Institusi</label>
                        <div class="input-icon-wrap">
                            <i class="fa-solid fa-envelope input-icon"></i>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($data['email']) ?>" placeholder="dosen@ummaumere.ac.id">
                        </div>
                    </div>
                    
                    <!-- BIO -->
                    <div class="form-section-title"><i class="fa-solid fa-feather-pointed"></i> Profil Singkat</div>
                    <div class="form-group">
                        <label>Biografi / Profil</label>
                        <div id="bioEditor" style="height:180px;"><?= $data['bio'] ?></div>
                        <small style="color:var(--text-muted); margin-top:.3rem; display:block;">
                            <i class="fa-solid fa-info-circle"></i> Deskripsi singkat tentang dosen, minat riset, dan pengalaman.
                        </small>
                    </div>
                    
                    <!-- SOSIAL MEDIA -->
                    <div class="form-section-title"><i class="fa-solid fa-share-nodes"></i> Profil Akademik & Sosial</div>
                    
                    <div class="social-link-group">
                        <div class="soc-icon scholar"><i class="fa-solid fa-graduation-cap"></i></div>
                        <input type="url" name="google_scholar" value="<?= htmlspecialchars($data['google_scholar'] ?? '') ?>" placeholder="https://scholar.google.com/citations?user=...">
                    </div>
                    
                    <div class="social-link-group">
                        <div class="soc-icon orcid"><i class="fa-brands fa-orcid"></i></div>
                        <input type="text" name="orcid" value="<?= htmlspecialchars($data['orcid'] ?? '') ?>" placeholder="0000-0000-0000-0000">
                    </div>
                    
                    <div class="social-link-group">
                        <div class="soc-icon scopus"><i class="fa-solid fa-book-open-reader"></i></div>
                        <input type="text" name="scopus_id" value="<?= htmlspecialchars($data['scopus_id'] ?? '') ?>" placeholder="Scopus Author ID">
                    </div>
                    
                    <div class="social-link-group">
                        <div class="soc-icon rgate"><i class="fa-solid fa-flask"></i></div>
                        <input type="url" name="researchgate" value="<?= htmlspecialchars($data['researchgate'] ?? '') ?>" placeholder="https://www.researchgate.net/profile/...">
                    </div>
                    
                    <div class="social-link-group">
                        <div class="soc-icon linkedin"><i class="fa-brands fa-linkedin-in"></i></div>
                        <input type="url" name="linkedin" value="<?= htmlspecialchars($data['linkedin'] ?? '') ?>" placeholder="https://linkedin.com/in/...">
                    </div>
                    
                    <!-- PENGATURAN -->
                    <div class="form-section-title"><i class="fa-solid fa-sliders"></i> Pengaturan</div>
                    
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem;">
                        <div class="form-group">
                            <label>Urutan Tampil</label>
                            <div class="input-icon-wrap">
                                <i class="fa-solid fa-list-ol input-icon"></i>
                                <input type="number" name="urutan" class="form-control" value="<?= (int)($data['urutan'] ?? 1) ?>" min="1">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <label class="toggle-switch" style="margin-top:.6rem;">
                                <input type="checkbox" name="aktif" value="1" <?= ($data['aktif'] ?? 1) == 1 ? 'checked' : '' ?>>
                                <span class="toggle-slider"></span>
                            </label>
                            <span style="margin-left:.5rem; font-size:.88rem; font-weight:600;">Aktif</span>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="toggle-switch">
                            <input type="checkbox" name="is_featured" value="1" <?= ($data['is_featured'] ?? 0) == 1 ? 'checked' : '' ?>>
                            <span class="toggle-slider"></span>
                        </label>
                        <span style="margin-left:.5rem; font-size:.88rem; font-weight:600;">
                            <i class="fa-solid fa-star" style="color:var(--warning);"></i>
                            Dosen Featured (disorot di beranda)
                        </span>
                    </div>
                    
                    <div style="margin-top:2rem; display:flex; gap:1rem; padding-top:1.5rem; border-top:1px solid var(--border-dark);">
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> <?= $data['id']>0?'Perbarui':'Simpan' ?> Dosen</button>
                        <a href="kelola_dosen.php" class="btn btn-secondary"><i class="fa-solid fa-xmark"></i> Batal</a>
                    </div>
                </div>
                
                <!-- RIGHT: LIVE PREVIEW -->
                <div class="dosen-live-preview">
                    <div class="preview-title"><i class="fa-solid fa-eye"></i> Live Preview (Tampilan di Website)</div>
                    
                    <div class="preview-dosen-card">
                        <div class="preview-dosen-header">
                            <div class="preview-dosen-avatar" id="prevAvatar">
                                <?php if (!empty($data['foto'])): ?>
                                    <img src="../<?= htmlspecialchars($data['foto']) ?>" id="prevAvatarImg" alt="Avatar">
                                <?php else: ?>
                                    <i class="fa-solid fa-user" id="prevAvatarIcon"></i>
                                <?php endif; ?>
                            </div>
                            <div class="preview-dosen-name" id="prevName">
                                <span id="prevGelarDepan"><?= htmlspecialchars($data['gelar_depan']) ?></span>
                                <span id="prevNama" style="margin:0 .2rem;"><?= htmlspecialchars($data['nama'] ?: 'Nama Dosen') ?></span>
                                <span id="prevGelarBelakang"><?= htmlspecialchars($data['gelar_belakang']) ?></span>
                            </div>
                            <div class="preview-dosen-nidn" id="prevNidn">
                                NIDN: <span id="prevNidnVal"><?= htmlspecialchars($data['nidn'] ?: '08123456789') ?></span>
                            </div>
                        </div>
                        <div class="preview-dosen-body">
                            <span class="preview-dosen-bidang" id="prevBidang"><?= htmlspecialchars($data['bidang_keahlian'] ?: 'Linguistics') ?></span>
                            <div class="preview-dosen-bio" id="prevBio">
                                <?= !empty($data['bio']) ? $data['bio'] : 'Biografi singkat dosen akan muncul di sini...' ?>
                            </div>
                            <div class="preview-dosen-social" id="prevSocial"></div>
                        </div>
                    </div>
                    
                    <div style="margin-top:1rem; padding:.8rem; background:rgba(46,139,87,0.08); border:1px solid rgba(46,139,87,0.2); border-radius:var(--radius-md); font-size:.78rem; color:var(--text-muted);">
                        <i class="fa-solid fa-circle-check" style="color:var(--success);"></i>
                        <strong style="color:var(--success);"> Tips:</strong> Dosen dengan ORCID dan Scopus ID yang lengkap akan meningkatkan kredibilitas prodi di mata akreditasi.
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // ===== QUILL EDITOR FOR BIO =====
    const bioEditor = new Quill('#bioEditor', {
        theme: 'snow',
        placeholder: 'Tulis profil singkat dosen...',
        modules: {
            toolbar: [
                ['bold', 'italic', 'underline'],
                [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                ['link'],
                ['clean']
            ]
        }
    });
    
    // Sync to hidden input on submit
    document.getElementById('dosenForm').addEventListener('submit', function() {
        document.getElementById('hiddenBio').value = bioEditor.root.innerHTML;
    });
    
    // ===== LIVE PREVIEW BINDINGS =====
    document.querySelectorAll('[data-preview]').forEach(input => {
        input.addEventListener('input', function() {
            const key = this.dataset.preview;
            const val = this.value;
            if (key === 'gelar_depan') document.getElementById('prevGelarDepan').textContent = val + (val ? ' ' : '');
            else if (key === 'gelar_belakang') document.getElementById('prevGelarBelakang').textContent = (val ? ', ' : '') + val;
            else if (key === 'nama') document.getElementById('prevNama').textContent = val || 'Nama Dosen';
            else if (key === 'nidn') document.getElementById('prevNidnVal').textContent = val || '08123456789';
        });
    });
    
    // Bio preview
    bioEditor.on('text-change', function() {
        document.getElementById('prevBio').innerHTML = bioEditor.root.innerHTML || 'Biografi singkat dosen akan muncul di sini...';
    });
    
    // Bidang preview
    document.getElementById('f_bidang')?.addEventListener('change', function() {
        document.getElementById('prevBidang').textContent = this.value || 'Linguistics';
    });
    
    // Avatar preview
    const fileInput = document.getElementById('fileInput');
    if (fileInput) {
        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length) {
                const reader = new FileReader();
                reader.onload = (ev) => {
                    const preview = document.getElementById('profilePreview');
                    preview.innerHTML = `<img src="${ev.target.result}" id="previewImg">`;
                    const prevAvatar = document.getElementById('prevAvatar');
                    prevAvatar.innerHTML = `<img src="${ev.target.result}" id="prevAvatarImg">`;
                };
                reader.readAsDataURL(e.target.files[0]);
            }
        });
    }
    
    // Social links preview
    function updateSocialPreview() {
        const socials = {
            google_scholar: { icon: 'fa-graduation-cap', color: '#4285F4' },
            orcid: { icon: 'fa-brands fa-orcid', color: '#A6CE39' },
            scopus_id: { icon: 'fa-book-open-reader', color: '#E9711C' },
            researchgate: { icon: 'fa-flask', color: '#00D0BB' },
            linkedin: { icon: 'fa-brands fa-linkedin-in', color: '#0A66C2' }
        };
        const container = document.getElementById('prevSocial');
        let html = '';
        for (const [key, info] of Object.entries(socials)) {
            const input = document.querySelector(`input[name="${key}"]`);
            if (input && input.value) {
                const href = key === 'orcid' ? `https://orcid.org/${input.value}` : (key === 'scopus_id' ? `https://www.scopus.com/authid/detail.uri?authorId=${input.value}` : input.value);
                html += `<a href="${href}" target="_blank" class="preview-social-link" style="background:${info.color};" title="${key.replace(/_/g,' ')}"><i class="fa-solid ${info.icon}"></i></a>`;
            }
        }
        container.innerHTML = html;
    }
    
    document.querySelectorAll('.social-link-group input').forEach(inp => {
        inp.addEventListener('input', updateSocialPreview);
    });
    updateSocialPreview();
});
</script>

<?php else: ?>
<!-- ===== FILTER BAR ===== -->
<?php if (!empty($items)): ?>
<div class="dosen-filter-bar">
    <div class="form-group">
        <label><i class="fa-solid fa-search"></i> Cari Dosen</label>
        <input type="text" id="dosenSearch" class="form-control" placeholder="Ketik nama atau NIDN...">
    </div>
    <div class="form-group">
        <label><i class="fa-solid fa-book"></i> Bidang Keahlian</label>
        <select id="bidangFilter" class="form-control">
            <option value="all">Semua Bidang</option>
            <?php foreach ($unique_bidang as $b): ?>
            <option value="<?= htmlspecialchars($b) ?>"><?= htmlspecialchars($b) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label><i class="fa-solid fa-filter"></i> Status</label>
        <select id="statusFilter" class="form-control">
            <option value="all">Semua Status</option>
            <option value="active">Aktif</option>
            <option value="inactive">Nonaktif</option>
            <option value="featured">Featured Only</option>
        </select>
    </div>
    <div class="form-group">
        <button type="button" class="btn btn-sm btn-secondary" onclick="resetFilters()" style="width:100%;">
            <i class="fa-solid fa-rotate-left"></i> Reset
        </button>
    </div>
</div>

<!-- BULK ACTIONS BAR -->
<div class="bulk-actions-bar" id="bulkActionsBar">
    <div class="bulk-info">
        <i class="fa-solid fa-check-double" style="color:var(--crimson);"></i>
        <span><span class="bulk-count" id="bulkCount">0</span> dosen dipilih</span>
    </div>
    <div style="display:flex; gap:.5rem;">
        <button type="button" class="btn btn-sm btn-secondary" onclick="clearSelection()">
            <i class="fa-solid fa-xmark"></i> Batal
        </button>
        <button type="button" class="btn btn-sm btn-primary" onclick="executeBulkDelete()" style="background:var(--danger);">
            <i class="fa-solid fa-trash"></i> Hapus Terpilih
        </button>
    </div>
</div>

<form method="POST" id="bulkForm">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="bulk_delete" value="1">
    <div id="bulkIdsContainer"></div>
</form>
<?php endif; ?>

<!-- ===== CARD GRID VIEW ===== -->
<?php if (empty($items)): ?>
<div class="dosen-empty">
    <div class="dosen-empty-icon"><i class="fa-solid fa-chalkboard-user"></i></div>
    <h3>Belum Ada Data Dosen</h3>
    <p>Tambahkan dosen dan pengajar PBSI UMMaumere untuk ditampilkan di halaman profil</p>
    <a href="?action=add" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Tambah Dosen Pertama</a>
</div>
<?php else: ?>
<div class="dosen-card-grid" id="dosenCardGrid">
    <?php foreach ($items as $item): 
        $token = hash_hmac('sha256', $item['id'], $_SESSION['csrf_token']);
        $namaLengkap = trim(($item['gelar_depan'] ? $item['gelar_depan'].' ' : '') . $item['nama'] . ($item['gelar_belakang'] ? ', '.$item['gelar_belakang'] : ''));
        $initials = strtoupper(substr($item['nama'], 0, 1));
        $isFeatured = ($item['is_featured'] ?? 0) == 1;
        $isActive = ($item['aktif'] ?? 1) == 1;
    ?>
    <div class="dosen-card <?= $isFeatured ? 'featured' : '' ?>"
         data-search="<?= strtolower(htmlspecialchars(($item['nama'] ?? '') . ' ' . ($item['nidn'] ?? '') . ' ' . ($item['email'] ?? ''))) ?>"
         data-bidang="<?= htmlspecialchars($item['bidang_keahlian'] ?? '') ?>"
         data-status="<?= $isActive ? 'active' : 'inactive' ?>"
         data-featured="<?= $isFeatured ? '1' : '0' ?>">
        <div class="dosen-card-header">
            <div class="dosen-card-avatar">
                <?php if (!empty($item['foto'])): ?>
                    <img src="../<?= htmlspecialchars($item['foto']) ?>" alt="<?= htmlspecialchars($item['nama']) ?>">
                <?php else: ?>
                    <?= htmlspecialchars($initials) ?>
                <?php endif; ?>
            </div>
            <div class="dosen-card-info">
                <div class="dosen-card-name"><?= htmlspecialchars($namaLengkap) ?></div>
                <?php if (!empty($item['nidn'])): ?>
                <div class="dosen-card-nidn">NIDN: <?= htmlspecialchars($item['nidn']) ?></div>
                <?php endif; ?>
                <span class="dosen-card-bidang"><?= htmlspecialchars($item['bidang_keahlian']) ?></span>
            </div>
        </div>
        <div class="dosen-card-body">
            <div class="dosen-card-meta">
                <?php if (!empty($item['email'])): ?>
                <div class="dosen-card-meta-row">
                    <i class="fa-solid fa-envelope"></i>
                    <a href="mailto:<?= htmlspecialchars($item['email']) ?>"><?= htmlspecialchars($item['email']) ?></a>
                </div>
                <?php endif; ?>
                <?php if (!empty($item['google_scholar'])): ?>
                <div class="dosen-card-meta-row">
                    <i class="fa-solid fa-graduation-cap" style="color:#4285F4;"></i>
                    <a href="<?= htmlspecialchars($item['google_scholar']) ?>" target="_blank">Google Scholar</a>
                </div>
                <?php endif; ?>
                <?php if (!empty($item['orcid'])): ?>
                <div class="dosen-card-meta-row">
                    <i class="fa-brands fa-orcid" style="color:#A6CE39;"></i>
                    <a href="https://orcid.org/<?= htmlspecialchars($item['orcid']) ?>" target="_blank">ORCID: <?= htmlspecialchars($item['orcid']) ?></a>
                </div>
                <?php endif; ?>
                <?php if (!empty($item['scopus_id'])): ?>
                <div class="dosen-card-meta-row">
                    <i class="fa-solid fa-book-open-reader" style="color:#E9711C;"></i>
                    <a href="https://www.scopus.com/authid/detail.uri?authorId=<?= htmlspecialchars($item['scopus_id']) ?>" target="_blank">Scopus ID</a>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($item['bio'])): ?>
            <div class="dosen-card-bio">
                <?= strip_tags(substr($item['bio'], 0, 150)) ?><?= strlen(strip_tags($item['bio'])) > 150 ? '...' : '' ?>
            </div>
            <?php endif; ?>
            
            <div class="dosen-card-footer">
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="toggle_status" value="1">
                    <input type="hidden" name="id" value="<?= $item['id'] ?>">
                    <input type="hidden" name="aktif" value="<?= $isActive ? 0 : 1 ?>">
                    <label class="toggle-switch" title="<?= $isActive ? 'Nonaktifkan' : 'Aktifkan' ?>">
                        <input type="checkbox" <?= $isActive ? 'checked' : '' ?> onchange="this.form.submit()">
                        <span class="toggle-slider"></span>
                    </label>
                </form>
                
                <form method="POST" style="display:inline; margin-left:.3rem;">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="toggle_featured" value="1">
                    <input type="hidden" name="id" value="<?= $item['id'] ?>">
                    <input type="hidden" name="is_featured" value="<?= $isFeatured ? 0 : 1 ?>">
                    <button type="submit" class="table-action-btn" title="<?= $isFeatured ? 'Hapus Featured' : 'Jadikan Featured' ?>" style="width:32px; height:32px; border-radius:var(--radius-sm); background:<?= $isFeatured ? 'var(--warning)' : 'var(--bg-darker)' ?>; color:<?= $isFeatured ? '#fff' : 'var(--text-muted)' ?>; border:1px solid var(--border-dark); cursor:pointer; display:flex; align-items:center; justify-content:center;">
                        <i class="fa-solid fa-star" style="font-size:.82rem;"></i>
                    </button>
                </form>
                
                <div class="dosen-card-actions" style="margin-left:auto;">
                    <a href="?edit=<?= $item['id'] ?>&token=<?= $token ?>" class="table-action-btn edit" title="Edit"><i class="fa-solid fa-pen"></i></a>
                    <a href="?duplicate=<?= $item['id'] ?>&token=<?= $token ?>" class="table-action-btn" title="Duplikasi" onclick="return confirm('Duplikasi data dosen ini?')"><i class="fa-solid fa-copy"></i></a>
                    <a href="?delete=<?= $item['id'] ?>&token=<?= $token ?>" class="table-action-btn delete" onclick="return confirm('Yakin ingin menghapus dosen <?= htmlspecialchars($item['nama']) ?>? Tindakan ini tidak dapat dibatalkan.')" title="Hapus"><i class="fa-solid fa-trash"></i></a>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div style="text-align:center; margin-top:1rem; padding:.8rem; background:rgba(138,21,56,.05); border-radius:8px; color:var(--text-muted); font-size:.88rem;">
    Menampilkan <strong id="dosenCount" style="color:var(--crimson);"><?= $total_dosen ?></strong> dari <strong><?= $total_dosen ?></strong> dosen terdaftar
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('dosenSearch');
    const bidangFilter = document.getElementById('bidangFilter');
    const statusFilter = document.getElementById('statusFilter');
    const countEl = document.getElementById('dosenCount');
    const selectAll = document.getElementById('selectAll');
    const bulkBar = document.getElementById('bulkActionsBar');
    const bulkCount = document.getElementById('bulkCount');
    const bulkIdsContainer = document.getElementById('bulkIdsContainer');
    
    function filterDosen() {
        const term = (searchInput?.value || '').toLowerCase();
        const bidang = bidangFilter?.value || 'all';
        const status = statusFilter?.value || 'all';
        
        const cards = document.querySelectorAll('.dosen-card');
        let visibleCount = 0;
        
        cards.forEach(card => {
            const searchText = card.dataset.search || '';
            const cardBidang = card.dataset.bidang;
            const cardStatus = card.dataset.status;
            const cardFeatured = card.dataset.featured;
            
            const matchSearch = !term || searchText.includes(term);
            const matchBidang = bidang === 'all' || cardBidang === bidang;
            let matchStatus = true;
            if (status === 'active') matchStatus = cardStatus === 'active';
            else if (status === 'inactive') matchStatus = cardStatus === 'inactive';
            else if (status === 'featured') matchStatus = cardFeatured === '1';
            
            if (matchSearch && matchBidang && matchStatus) {
                card.style.display = '';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });
        
        if (countEl) countEl.textContent = visibleCount;
    }
    
    window.resetFilters = function() {
        if (searchInput) searchInput.value = '';
        if (bidangFilter) bidangFilter.value = 'all';
        if (statusFilter) statusFilter.value = 'all';
        filterDosen();
    };
    
    if (searchInput) searchInput.addEventListener('input', filterDosen);
    if (bidangFilter) bidangFilter.addEventListener('change', filterDosen);
    if (statusFilter) statusFilter.addEventListener('change', filterDosen);
});
</script>
<?php endif; ?>
<?php endif; ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    if ($('#dosenForm').length && $.fn.select2) {
        $('.select2-premium').select2({ 
            dropdownParent: $('#dosenForm'),
            minimumResultsForSearch: Infinity
        });
    }
    
    // Animated counters
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
    
    // Animated bidang bars
    setTimeout(() => {
        document.querySelectorAll('.bidang-fill').forEach(bar => {
            bar.style.width = bar.dataset.target + '%';
        });
    }, 300);
});
</script>

<?php
$page_content = ob_get_clean();
require_once 'includes/admin_template.php';
?>