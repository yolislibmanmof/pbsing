<?php
require_once '../includes/config.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';
require_once 'includes/upload_helper.php';
require_login();

$page_title = 'Kelola Berita';
$current_page = 'berita';
$table = 'berita';

// ===== AUTO-MIGRATION =====
$required_cols = [
    'seo_title'       => "varchar(150) DEFAULT NULL",
    'seo_description' => "varchar(255) DEFAULT NULL",
    'seo_keywords'    => "varchar(255) DEFAULT NULL",
    'is_featured'     => "tinyint(1) DEFAULT 0",
    'tags'            => "varchar(500) DEFAULT ''",
    'read_time'       => "int(11) DEFAULT 0",
    'likes'           => "int(11) DEFAULT 0",
    'shares'          => "int(11) DEFAULT 0",
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
    $stmt = $pdo->prepare("SELECT judul, gambar FROM $table WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if ($row) {
        if (!empty($row['gambar']) && !filter_var($row['gambar'], FILTER_VALIDATE_URL) && file_exists('../' . $row['gambar'])) {
            @unlink('../' . $row['gambar']);
        }
        $pdo->prepare("DELETE FROM $table WHERE id = ?")->execute([$id]);
        $pdo->prepare("INSERT INTO admin_logs (user_id, user_name, action, detail, ip) VALUES (?, ?, 'DELETE_NEWS', ?, ?)")
            ->execute([$_SESSION['admin_id'], $_SESSION['admin_name'], 'Menghapus berita: ' . $row['judul'], $_SERVER['REMOTE_ADDR']]);
    }
    header("Location: kelola_berita.php?msg=deleted");
    exit;
}

// ===== HANDLE DUPLICATE =====
if (isset($_GET['duplicate']) && isset($_GET['token']) && hash_equals($_SESSION['csrf_token'], $_GET['token'])) {
    $id = (int)$_GET['duplicate'];
    $stmt = $pdo->prepare("SELECT * FROM $table WHERE id = ?");
    $stmt->execute([$id]);
    $orig = $stmt->fetch();
    if ($orig) {
        $newSlug = $orig['slug'] . '-copy-' . time();
        $pdo->prepare("INSERT INTO $table (judul, slug, kategori, isi, gambar, penulis, tanggal, views, status, seo_title, seo_description, seo_keywords, is_featured, tags, read_time) VALUES (?, ?, ?, ?, ?, ?, ?, 0, 0, ?, ?, ?, 0, ?, ?)")
            ->execute([
                $orig['judul'] . ' (Copy)',
                $newSlug, $orig['kategori'], $orig['isi'],
                $orig['gambar'], $orig['penulis'], date('Y-m-d'),
                $orig['seo_title'] ?? '', $orig['seo_description'] ?? '',
                $orig['seo_keywords'] ?? '', $orig['tags'] ?? '',
                $orig['read_time'] ?? 0
            ]);
        $pdo->prepare("INSERT INTO admin_logs (user_id, user_name, action, detail, ip) VALUES (?, ?, 'DUPLICATE_NEWS', ?, ?)")
            ->execute([$_SESSION['admin_id'], $_SESSION['admin_name'], 'Menduplikasi berita: ' . $orig['judul'], $_SERVER['REMOTE_ADDR']]);
    }
    header("Location: kelola_berita.php?msg=duplicated");
    exit;
}

// ===== HANDLE BULK DELETE =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete']) && isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $ids = $_POST['bulk_ids'] ?? [];
    $deleted = 0;
    foreach ($ids as $id) {
        $id = (int)$id;
        $stmt = $pdo->prepare("SELECT gambar FROM $table WHERE id = ?");
        $stmt->execute([$id]);
        $gbr = $stmt->fetchColumn();
        if ($gbr && !filter_var($gbr, FILTER_VALIDATE_URL) && file_exists('../' . $gbr)) @unlink('../' . $gbr);
        $pdo->prepare("DELETE FROM $table WHERE id = ?")->execute([$id]);
        $deleted++;
    }
    if ($deleted > 0) {
        $pdo->prepare("INSERT INTO admin_logs (user_id, user_name, action, detail, ip) VALUES (?, ?, 'BULK_DELETE_NEWS', ?, ?)")
            ->execute([$_SESSION['admin_id'], $_SESSION['admin_name'], "Menghapus $deleted berita", $_SERVER['REMOTE_ADDR']]);
    }
    header("Location: kelola_berita.php?msg=bulk_deleted&count=$deleted");
    exit;
}

// ===== HANDLE TOGGLE STATUS =====
if (isset($_POST['toggle_status']) && isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $id = (int)$_POST['id'];
    $status = (int)$_POST['status'];
    $pdo->prepare("UPDATE $table SET status = ? WHERE id = ?")->execute([$status, $id]);
    header("Location: kelola_berita.php?msg=toggled");
    exit;
}

// ===== HANDLE TOGGLE FEATURED =====
if (isset($_POST['toggle_featured']) && isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $id = (int)$_POST['id'];
    $is_featured = (int)$_POST['is_featured'];
    $pdo->prepare("UPDATE $table SET is_featured = ? WHERE id = ?")->execute([$is_featured, $id]);
    header("Location: kelola_berita.php?msg=featured");
    exit;
}

// ===== HANDLE ADD/EDIT =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['toggle_status']) && !isset($_POST['toggle_featured']) && !isset($_POST['bulk_delete']) && isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $judul = sanitize($_POST['judul']);
    $kategori = sanitize($_POST['kategori']);
    $isi = $_POST['isi'] ?? '';
    $penulis = sanitize($_POST['penulis'] ?? $_SESSION['admin_name']);
    $tanggal = sanitize($_POST['tanggal']);
    $status = isset($_POST['status']) ? 1 : 0;
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    $seo_title = sanitize($_POST['seo_title']);
    $seo_description = sanitize($_POST['seo_description']);
    $seo_keywords = sanitize($_POST['seo_keywords']);
    $tags = sanitize($_POST['tags']);
    
    // Auto slug
    $slug = isset($_POST['slug']) && !empty($_POST['slug'])
        ? sanitize($_POST['slug'])
        : strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $judul)));
    
    // Calculate read time (200 words/minute)
    $word_count = str_word_count(strip_tags($isi));
    $read_time = max(1, ceil($word_count / 200));
    
    // Validasi
    if (empty($judul)) {
        $error = "Judul berita wajib diisi.";
    } elseif (empty($isi) || $isi === '<p><br></p>') {
        $error = "Isi konten wajib diisi.";
    } else {
        $data_save = [
            'judul' => $judul, 'slug' => $slug, 'kategori' => $kategori,
            'isi' => $isi, 'penulis' => $penulis, 'tanggal' => $tanggal,
            'status' => $status, 'is_featured' => $is_featured,
            'seo_title' => $seo_title, 'seo_description' => $seo_description,
            'seo_keywords' => $seo_keywords, 'tags' => $tags,
            'read_time' => $read_time
        ];
        
        // Handle gambar upload
        if (!empty($_FILES['gambar']['name'])) {
            $upload = handle_upload($_FILES['gambar'], '../assets/uploads/berita/', ['jpg', 'jpeg', 'png', 'webp']);
            if ($upload['success']) {
                $data_save['gambar'] = $upload['path'];
                if ($id > 0) {
                    $stmt = $pdo->prepare("SELECT gambar FROM $table WHERE id = ?");
                    $stmt->execute([$id]);
                    $old = $stmt->fetch();
                    if ($old && !empty($old['gambar']) && !filter_var($old['gambar'], FILTER_VALIDATE_URL) && file_exists('../' . $old['gambar'])) {
                        @unlink('../' . $old['gambar']);
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
                $action = 'UPDATE_NEWS';
                $detail = "Memperbarui berita: $judul";
            } else {
                $sql = "INSERT INTO $table SET " . implode(', ', $sets) . ", dibuat = NOW()";
                $pdo->prepare($sql)->execute($params);
                $action = 'ADD_NEWS';
                $detail = "Menambah berita baru: $judul";
            }
            
            $pdo->prepare("INSERT INTO admin_logs (user_id, user_name, action, detail, ip) VALUES (?, ?, ?, ?, ?)")
                ->execute([$_SESSION['admin_id'], $_SESSION['admin_name'], $action, $detail, $_SERVER['REMOTE_ADDR']]);
            
            header("Location: kelola_berita.php?msg=saved");
            exit;
        }
    }
}

// ===== FETCH DATA =====
$items = $pdo->query("SELECT * FROM $table ORDER BY id DESC")->fetchAll();

foreach ($items as &$it) {
    foreach ($it as $k => $v) {
        if ($v === null) $it[$k] = '';
    }
}
unset($it);

// Statistics
$total_berita = count($items);
$published_count = count(array_filter($items, fn($i) => ($i['status'] ?? 0) == 1));
$draft_count = count(array_filter($items, fn($i) => ($i['status'] ?? 0) == 0));
$featured_count = count(array_filter($items, fn($i) => ($i['is_featured'] ?? 0) == 1));
$total_views = array_sum(array_column($items, 'views'));

// Group by kategori
$by_kategori = [];
foreach ($items as $it) {
    $k = $it['kategori'] ?? 'Lainnya';
    $by_kategori[$k] = ($by_kategori[$k] ?? 0) + 1;
}
arsort($by_kategori);
$unique_kategori = array_keys($by_kategori);
sort($unique_kategori);

// Form data
$data = [
    'id'=>0, 'judul'=>'', 'slug'=>'', 'kategori'=>'Kegiatan',
    'isi'=>'', 'penulis'=>$_SESSION['admin_name'],
    'tanggal'=>date('Y-m-d'), 'status'=>1, 'gambar'=>'',
    'is_featured'=>0, 'seo_title'=>'', 'seo_description'=>'',
    'seo_keywords'=>'', 'tags'=>''
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
/* ===== SELECT2 DARK ===== */
.select2-container--default .select2-selection--single { background-color: var(--bg-darker); border: 1px solid var(--border-dark); border-radius: var(--radius-md); height: 42px; display: flex; align-items: center; }
.select2-container--default .select2-selection--single .select2-selection__rendered { color: var(--text-main); padding-left: 14px; }
.select2-dropdown { background-color: var(--panel-bg); border: 1px solid var(--border-dark); border-radius: var(--radius-md); }
.select2-search__field { background-color: var(--bg-darker) !important; color: var(--text-main) !important; border: 1px solid var(--border-dark) !important; }
.select2-results__option { color: var(--text-main); }
.select2-results__option--highlighted[aria-selected] { background-color: var(--crimson) !important; }

/* ===== QUILL DARK ===== */
.ql-toolbar { border: 1px solid var(--border-dark) !important; border-radius: var(--radius-md) var(--radius-md) 0 0 !important; background: var(--bg-darker); }
.ql-container { border: 1px solid var(--border-dark) !important; border-radius: 0 0 var(--radius-md) var(--radius-md) !important; background: var(--bg-darker); color: var(--text-main); font-family: inherit; }
.ql-stroke { stroke: var(--text-muted) !important; }
.ql-fill { fill: var(--text-muted) !important; }
.ql-picker { color: var(--text-muted) !important; }
.ql-editor { color: var(--text-main); min-height: 350px; line-height: 1.7; }
.ql-editor.ql-blank::before { color: var(--text-dim); font-style: italic; }
.ql-editor h1, .ql-editor h2, .ql-editor h3 { color: #fff; margin: 1rem 0 .5rem; }
.ql-editor p { margin-bottom: .8rem; }
.ql-editor a { color: var(--sky-blue); }

/* ===== STATS GRID ===== */
.berita-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}
.berita-stat-card {
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
.berita-stat-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); }
.berita-stat-icon {
    width: 52px; height: 52px;
    border-radius: var(--radius-md);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.4rem; flex-shrink: 0;
}
.berita-stat-card.accent-crimson { --accent-color: var(--crimson); }
.berita-stat-card.accent-crimson .berita-stat-icon { background: rgba(138,21,56,0.15); color: var(--crimson); }
.berita-stat-card.accent-sky { --accent-color: var(--sky-blue); }
.berita-stat-card.accent-sky .berita-stat-icon { background: rgba(135,206,235,0.15); color: var(--sky-blue); }
.berita-stat-card.accent-emerald { --accent-color: var(--success); }
.berita-stat-card.accent-emerald .berita-stat-icon { background: rgba(46,139,87,0.15); color: var(--success); }
.berita-stat-card.accent-warning { --accent-color: var(--warning); }
.berita-stat-card.accent-warning .berita-stat-icon { background: rgba(243,156,18,0.15); color: var(--warning); }
.berita-stat-card.accent-purple { --accent-color: #9b59b6; }
.berita-stat-card.accent-purple .berita-stat-icon { background: rgba(155,89,182,0.15); color: #9b59b6; }
.berita-stat-value { font-size: 1.9rem; font-weight: 900; color: #fff; line-height: 1; }
.berita-stat-label { color: var(--text-muted); font-size: .82rem; margin-top: .2rem; }

/* ===== KATEGORI DISTRIBUTION ===== */
.kategori-distribution {
    background: var(--panel-bg);
    border: 1px solid var(--border-dark);
    border-radius: var(--radius-md);
    padding: 1.5rem;
    margin-bottom: 2rem;
}
.kategori-dist-title {
    font-size: 1rem; font-weight: 700; color: #fff;
    margin-bottom: 1.2rem;
    display: flex; align-items: center; gap: .6rem;
}
.kategori-dist-title i { color: var(--crimson); }
.kategori-list { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; }
.kategori-item {
    background: var(--bg-darker);
    border: 1px solid var(--border-dark);
    border-radius: var(--radius-md);
    padding: 1rem;
    cursor: pointer;
    transition: all .3s ease;
}
.kategori-item:hover { border-color: var(--crimson); transform: translateY(-2px); }
.kategori-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: .6rem; }
.kategori-name { font-weight: 700; color: #fff; font-size: .9rem; display: flex; align-items: center; gap: .5rem; }
.kategori-name i { color: var(--crimson); }
.kategori-count { font-weight: 800; font-size: .9rem; color: var(--sky-blue); }
.kategori-bar { height: 6px; background: var(--bg-darker); border-radius: 50px; overflow: hidden; }
.kategori-fill {
    height: 100%; border-radius: 50px;
    transition: width 1s ease; width: 0;
    background: linear-gradient(90deg, var(--crimson), var(--crimson-light));
}

/* ===== FILTER BAR ===== */
.berita-filter-bar {
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
.berita-filter-bar .form-group { margin: 0; }
.berita-filter-bar label { font-size: .85rem; color: var(--text-muted); margin-bottom: .4rem; display: block; font-weight: 600; }

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

/* ===== NEWS TABLE ENHANCED ===== */
.berita-table thead th {
    background: var(--bg-darker);
    color: var(--sky-blue);
    font-size: .78rem;
    text-transform: uppercase;
    letter-spacing: .5px;
    padding: 12px 16px;
    font-weight: 700;
    border-bottom: 1px solid var(--border-dark);
}
.news-cell { display: flex; align-items: center; gap: .8rem; }
.news-thumb {
    width: 70px; height: 50px;
    border-radius: 6px;
    overflow: hidden;
    flex-shrink: 0;
    background: var(--bg-darker);
    border: 1px solid var(--border-dark);
}
.news-thumb img { width: 100%; height: 100%; object-fit: cover; }
.news-cell-info { min-width: 0; flex: 1; }
.news-cell-title {
    font-weight: 700;
    color: #fff;
    font-size: .9rem;
    line-height: 1.3;
    margin-bottom: .3rem;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    position: relative;
}
.news-cell-title .featured-star {
    color: var(--warning);
    font-size: .7rem;
    margin-left: .3rem;
}
.news-cell-slug {
    font-family: monospace;
    font-size: .72rem;
    color: var(--text-muted);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.news-cell-meta {
    display: flex;
    gap: .8rem;
    font-size: .72rem;
    color: var(--text-muted);
    margin-top: .3rem;
    flex-wrap: wrap;
}
.news-cell-meta span { display: flex; align-items: center; gap: .3rem; }
.news-cell-meta i { color: var(--crimson); }

.status-pill {
    display: inline-flex; align-items: center; gap: .3rem;
    padding: .25rem .7rem;
    border-radius: 50px;
    font-size: .72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .5px;
}
.status-pill.published { background: rgba(46,139,87,0.15); color: var(--success); }
.status-pill.draft { background: rgba(243,156,18,0.15); color: var(--warning); }
.status-pill::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }

.cat-pill {
    display: inline-flex;
    padding: .2rem .6rem;
    background: rgba(135,206,235,0.1);
    color: var(--sky-blue);
    border-radius: 50px;
    font-size: .7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .5px;
}

.views-cell {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: .2rem;
}
.views-count { font-weight: 800; color: #fff; font-size: .95rem; }
.views-meta { font-size: .68rem; color: var(--text-muted); display: flex; gap: .6rem; }
.views-meta span { display: flex; align-items: center; gap: .2rem; }

/* ===== UPLOAD ZONE ===== */
.upload-zone {
    border: 2px dashed var(--border-light);
    border-radius: var(--radius-md);
    padding: 2rem;
    text-align: center;
    cursor: pointer;
    transition: all .3s ease;
    background: var(--bg-darker);
    position: relative;
    overflow: hidden;
}
.upload-zone:hover, .upload-zone.dragover { border-color: var(--crimson); background: rgba(138,21,56,0.05); }
.upload-zone i { font-size: 2.5rem; color: var(--text-muted); margin-bottom: 1rem; transition: all .3s; }
.upload-zone:hover i { color: var(--crimson); transform: translateY(-4px); }
.upload-zone input[type="file"] { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
.preview-container {
    margin-top: 1rem;
    border-radius: var(--radius-md);
    overflow: hidden;
    border: 1px solid var(--border-dark);
    display: none;
    position: relative;
}
.preview-container img { width: 100%; display: block; max-height: 300px; object-fit: cover; }
.preview-remove {
    position: absolute;
    top: 8px; right: 8px;
    width: 32px; height: 32px;
    border-radius: 50%;
    background: var(--danger);
    color: #fff;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: .9rem;
}

/* ===== SLUG INPUT ===== */
.slug-preview {
    margin-top: .4rem;
    padding: .4rem .7rem;
    background: var(--bg-darker);
    border: 1px solid var(--border-dark);
    border-radius: var(--radius-sm);
    font-family: monospace;
    font-size: .75rem;
    color: var(--sky-blue);
    display: flex;
    align-items: center;
    gap: .3rem;
}
.slug-preview i { color: var(--success); }

/* ===== TAGS INPUT ===== */
.tags-input-wrap {
    display: flex;
    flex-wrap: wrap;
    gap: .4rem;
    padding: .5rem;
    background: var(--bg-darker);
    border: 1px solid var(--border-dark);
    border-radius: var(--radius-md);
    min-height: 44px;
    cursor: text;
}
.tags-input-wrap:focus-within { border-color: var(--crimson); }
.tag-chip {
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    background: rgba(138,21,56,0.2);
    color: var(--crimson);
    padding: .25rem .6rem;
    border-radius: 50px;
    font-size: .78rem;
    font-weight: 600;
}
.tag-chip button {
    background: none;
    border: none;
    color: var(--crimson);
    cursor: pointer;
    font-size: .9rem;
    padding: 0;
    line-height: 1;
}
.tags-input-wrap input {
    flex: 1;
    min-width: 100px;
    background: transparent;
    border: none;
    color: var(--text-main);
    outline: none;
    font-size: .85rem;
    padding: .3rem;
}

/* ===== CHARACTER COUNTER ===== */
.field-counter {
    text-align: right;
    font-size: .72rem;
    color: var(--text-muted);
    margin-top: .3rem;
    font-family: monospace;
}
.field-counter.warning { color: var(--warning); }
.field-counter.danger { color: var(--danger); }
.field-counter.good { color: var(--success); }

/* ===== FORM GRID ===== */
.berita-form-grid {
    display: grid;
    grid-template-columns: 1.5fr 1fr;
    gap: 2rem;
}
@media (max-width: 1100px) { .berita-form-grid { grid-template-columns: 1fr; } }

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
.input-icon-wrap textarea.form-control { padding-left: 14px; padding-top: 12px; }

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
.info-box.warning {
    background: rgba(243,156,18,0.1);
    border: 1px solid rgba(243,156,18,0.3);
    border-left: 4px solid var(--warning);
    color: var(--text-main);
}
.info-box i { font-size: 1.1rem; margin-top: 2px; flex-shrink: 0; }
.info-box.tip i { color: var(--success); }
.info-box.info i { color: var(--sky-blue); }
.info-box.warning i { color: var(--warning); }
.info-box strong { display: block; margin-bottom: .2rem; font-size: .9rem; }

/* ===== MODERN TOGGLE ===== */
.toggle-switch { position: relative; display: inline-block; width: 42px; height: 22px; flex-shrink: 0; }
.toggle-switch input { opacity: 0; width: 0; height: 0; }
.toggle-slider { position: absolute; cursor: pointer; inset: 0; background: var(--bg-darker); border: 1px solid var(--border-dark); transition: .3s; border-radius: 50px; }
.toggle-slider:before { position: absolute; content: ""; height: 16px; width: 16px; left: 2px; bottom: 2px; background: var(--text-muted); transition: .3s; border-radius: 50%; }
.toggle-switch input:checked + .toggle-slider { background: var(--success); border-color: var(--success); }
.toggle-switch input:checked + .toggle-slider:before { transform: translateX(20px); background: #fff; }

/* ===== LIVE PREVIEW ===== */
.berita-live-preview {
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

.preview-article-card {
    background: #fff;
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 15px 40px rgba(0,0,0,0.3);
    color: #333;
}
.preview-article-img {
    width: 100%;
    aspect-ratio: 16/9;
    background: linear-gradient(135deg, #8A1538, #1a1a2e);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 3rem;
    overflow: hidden;
    position: relative;
}
.preview-article-img img { width: 100%; height: 100%; object-fit: cover; }
.preview-article-img .featured-badge {
    position: absolute;
    top: 10px; right: 10px;
    background: linear-gradient(135deg, #f39c12, #e67e22);
    color: #fff;
    padding: .25rem .7rem;
    border-radius: 50px;
    font-size: .7rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .5px;
    box-shadow: 0 2px 8px rgba(243,156,18,0.4);
    display: none;
}
.preview-article-img .featured-badge.active { display: inline-block; }
.preview-article-body { padding: 1.2rem; }
.preview-article-cat {
    display: inline-block;
    background: rgba(138,21,56,.1);
    color: #8A1538;
    padding: .2rem .7rem;
    border-radius: 50px;
    font-size: .7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .5px;
    margin-bottom: .5rem;
}
.preview-article-title {
    font-size: 1.05rem;
    font-weight: 800;
    color: #222;
    line-height: 1.3;
    margin-bottom: .6rem;
    min-height: 2.6em;
}
.preview-article-meta {
    display: flex;
    gap: .8rem;
    font-size: .72rem;
    color: #888;
    flex-wrap: wrap;
}
.preview-article-meta span { display: flex; align-items: center; gap: .2rem; }
.preview-article-meta i { color: #8A1538; }
.preview-article-tags {
    display: flex;
    gap: .3rem;
    flex-wrap: wrap;
    margin-top: .8rem;
    padding-top: .8rem;
    border-top: 1px solid #eee;
}
.preview-tag {
    background: #f0f0f0;
    color: #555;
    padding: .15rem .5rem;
    border-radius: 4px;
    font-size: .68rem;
    font-weight: 600;
}

/* ===== SEO PREVIEW (Google Search) ===== */
.seo-preview {
    background: #fff;
    border-radius: 10px;
    padding: 1rem;
    margin-top: 1rem;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.seo-preview-title {
    font-size: .68rem;
    color: #888;
    margin-bottom: .4rem;
    text-transform: uppercase;
    letter-spacing: .5px;
}
.seo-title-line {
    color: #1a0dab;
    font-size: .95rem;
    font-weight: 600;
    line-height: 1.3;
    margin-bottom: .2rem;
    overflow: hidden;
    text-overflow: ellipsis;
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
}
.seo-url-line {
    color: #006621;
    font-size: .75rem;
    margin-bottom: .3rem;
    font-family: monospace;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.seo-desc-line {
    color: #545454;
    font-size: .78rem;
    line-height: 1.5;
    overflow: hidden;
    text-overflow: ellipsis;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
}

/* ===== EMPTY STATE ===== */
.berita-empty {
    text-align: center;
    padding: 4rem 2rem;
    background: var(--panel-bg);
    border: 2px dashed var(--border-dark);
    border-radius: var(--radius-lg);
}
.berita-empty-icon {
    width: 100px; height: 100px;
    margin: 0 auto 1.5rem;
    background: linear-gradient(135deg, var(--crimson), var(--crimson-light));
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 3rem;
    color: #fff;
    box-shadow: 0 10px 30px rgba(138,21,56,.3);
}
.berita-empty h3 { color: #fff; margin-bottom: .5rem; font-size: 1.3rem; }
.berita-empty p { color: var(--text-muted); margin-bottom: 1.5rem; max-width: 500px; margin-left: auto; margin-right: auto; }

/* ===== RESPONSIVE ===== */
@media (max-width: 992px) {
    .berita-filter-bar { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 576px) {
    .berita-filter-bar { grid-template-columns: 1fr; }
    .berita-stats-grid { grid-template-columns: repeat(2, 1fr); }
}
</style>

<?php if (isset($_GET['msg'])): 
    $msgs = [
        'saved' => ['success', 'Berhasil!', 'Berita berhasil disimpan.'],
        'deleted' => ['success', 'Berhasil!', 'Berita berhasil dihapus.'],
        'duplicated' => ['success', 'Berhasil!', 'Berita berhasil diduplikasi.'],
        'toggled' => ['success', 'Berhasil!', 'Status berita diperbarui.'],
        'featured' => ['success', 'Berhasil!', 'Status featured diperbarui.']
    ];
    if (isset($msgs[$_GET['msg']])): ?>
    <script>Swal.fire({icon:'<?=$msgs[$_GET['msg']][0]?>', title:'<?=$msgs[$_GET['msg']][1]?>', text:'<?=$msgs[$_GET['msg']][2]?>', timer:2500, showConfirmButton:false});</script>
<?php endif; 
    if ($_GET['msg'] === 'bulk_deleted'): ?>
    <script>Swal.fire({icon:'success', title:'Berhasil!', text:'<?= (int)($_GET['count'] ?? 0) ?> berita berhasil dihapus.', timer:2500, showConfirmButton:false});</script>
<?php endif; 
endif; ?>

<?php if (isset($error)): ?>
<script>Swal.fire({icon:'error', title:'Validasi Gagal', text:'<?= addslashes($error) ?>'});</script>
<?php endif; ?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;">
    <div>
        <h2 class="section-title"><i class="fa-solid fa-newspaper"></i> <?= isset($_GET['edit']) ? 'Edit' : 'Kelola' ?> Berita</h2>
        <p style="color:var(--text-muted); font-size:.88rem; margin-top:.3rem;">
            <i class="fa-solid fa-circle-info"></i> Kelola artikel, pengumuman, dan berita kegiatan
        </p>
    </div>
    <div style="display:flex; gap:.8rem;">
        <?php if (isset($_GET['edit'])): ?>
        <a href="kelola_berita.php" class="btn btn-secondary"><i class="fa-solid fa-xmark"></i> Batal</a>
        <?php else: ?>
        <a href="?action=add" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Tambah Berita</a>
        <a href="../berita.php" target="_blank" class="btn btn-secondary"><i class="fa-solid fa-external-link"></i> Lihat di Website</a>
        <?php endif; ?>
    </div>
</div>

<?php if (!isset($_GET['edit']) && !isset($_GET['action'])): ?>
<!-- ===== STATS DASHBOARD ===== -->
<div class="berita-stats-grid">
    <div class="berita-stat-card accent-crimson">
        <div class="berita-stat-icon"><i class="fa-solid fa-newspaper"></i></div>
        <div>
            <div class="berita-stat-value" data-counter="<?= $total_berita ?>">0</div>
            <div class="berita-stat-label">Total Berita</div>
        </div>
    </div>
    <div class="berita-stat-card accent-emerald" onclick="document.getElementById('statusFilter').value='published'; document.getElementById('statusFilter').dispatchEvent(new Event('change'));">
        <div class="berita-stat-icon"><i class="fa-solid fa-check-circle"></i></div>
        <div>
            <div class="berita-stat-value" data-counter="<?= $published_count ?>">0</div>
            <div class="berita-stat-label">Published</div>
        </div>
    </div>
    <div class="berita-stat-card accent-warning" onclick="document.getElementById('statusFilter').value='draft'; document.getElementById('statusFilter').dispatchEvent(new Event('change'));">
        <div class="berita-stat-icon"><i class="fa-solid fa-file-pen"></i></div>
        <div>
            <div class="berita-stat-value" data-counter="<?= $draft_count ?>">0</div>
            <div class="berita-stat-label">Draft</div>
        </div>
    </div>
    <div class="berita-stat-card accent-sky" onclick="document.getElementById('featuredFilter').value='1'; document.getElementById('featuredFilter').dispatchEvent(new Event('change'));">
        <div class="berita-stat-icon"><i class="fa-solid fa-star"></i></div>
        <div>
            <div class="berita-stat-value" data-counter="<?= $featured_count ?>">0</div>
            <div class="berita-stat-label">Featured</div>
        </div>
    </div>
    <div class="berita-stat-card accent-purple">
        <div class="berita-stat-icon"><i class="fa-solid fa-eye"></i></div>
        <div>
            <div class="berita-stat-value" data-counter="<?= $total_views ?>">0</div>
            <div class="berita-stat-label">Total Views</div>
        </div>
    </div>
</div>

<!-- ===== KATEGORI DISTRIBUTION ===== -->
<?php if (count($by_kategori) > 0): ?>
<div class="kategori-distribution">
    <div class="kategori-dist-title">
        <i class="fa-solid fa-chart-pie"></i> Distribusi Berita per Kategori
    </div>
    <div class="kategori-list">
        <?php foreach ($by_kategori as $kat => $count): 
            $percent = $total_berita > 0 ? round(($count / $total_berita) * 100, 1) : 0;
        ?>
        <div class="kategori-item" onclick="document.getElementById('kategoriFilter').value='<?= htmlspecialchars($kat) ?>'; document.getElementById('kategoriFilter').dispatchEvent(new Event('change'));">
            <div class="kategori-header">
                <div class="kategori-name"><i class="fa-solid fa-tag"></i> <?= htmlspecialchars($kat) ?></div>
                <div class="kategori-count"><?= $count ?></div>
            </div>
            <div class="kategori-bar">
                <div class="kategori-fill" data-target="<?= $percent ?>"></div>
            </div>
            <div style="margin-top:.4rem; font-size:.72rem; color:var(--text-muted); text-align:right;">
                <?= $percent ?>% · <?= $count ?> berita
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
            <?= isset($_GET['edit']) ? 'Edit' : 'Tambah' ?> Berita
        </h3>
    </div>
    <div class="panel-body">
        <form method="POST" enctype="multipart/form-data" id="beritaForm">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="id" value="<?= $data['id'] ?>">
            <input type="hidden" name="isi" id="hiddenIsi" value="<?= htmlspecialchars($data['isi']) ?>">
            
            <div class="berita-form-grid">
                <!-- LEFT: FORM FIELDS -->
                <div>
                    <div class="info-box info">
                        <i class="fa-solid fa-lightbulb"></i>
                        <div>
                            <strong>Tips Menulis Berita Efektif:</strong>
                            Judul optimal 60 karakter, Meta Description 150-160 karakter. Berita "Featured" akan ditampilkan di halaman beranda sebagai sorotan utama.
                        </div>
                    </div>
                    
                    <!-- KONTEN UTAMA -->
                    <div class="form-section-title"><i class="fa-solid fa-heading"></i> Konten Utama</div>
                    
                    <div class="form-group">
                        <label>Judul Berita <span class="required">*</span></label>
                        <div class="input-icon-wrap">
                            <i class="fa-solid fa-heading input-icon"></i>
                            <input type="text" name="judul" id="f_judul" class="form-control" value="<?= htmlspecialchars($data['judul']) ?>" required maxlength="150" data-counter="judulCounter" oninput="generateSlug(this.value)" placeholder="Judul berita yang menarik...">
                        </div>
                        <div class="field-counter" id="judulCounter">0 / 60 (ideal SEO)</div>
                    </div>
                    
                    <div class="form-group">
                        <label>Slug URL</label>
                        <div class="input-icon-wrap">
                            <i class="fa-solid fa-link input-icon"></i>
                            <input type="text" name="slug" id="f_slug" class="form-control" value="<?= htmlspecialchars($data['slug']) ?>" placeholder="judul-berita-url-friendly" data-preview="slug">
                        </div>
                        <div class="slug-preview" id="slugPreview">
                            <i class="fa-solid fa-globe"></i>
                            <span><?= htmlspecialchars(BASE_URL ?? '') ?>berita/<strong id="slugValue"><?= htmlspecialchars($data['slug'] ?: 'judul-berita') ?></strong></span>
                        </div>
                    </div>
                    
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem;">
                        <div class="form-group">
                            <label>Kategori</label>
                            <select name="kategori" id="f_kategori" class="form-control select2-premium" style="width:100%;">
                                <?php foreach (['Kegiatan', 'Prestasi', 'Pengumuman', 'Akademik', 'Umum', 'Opini', 'Wawancara'] as $cat): ?>
                                <option value="<?= $cat ?>" <?= ($data['kategori'] ?? '') == $cat ? 'selected' : '' ?>><?= $cat ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Tanggal Publikasi</label>
                            <div class="input-icon-wrap">
                                <i class="fa-regular fa-calendar input-icon"></i>
                                <input type="date" name="tanggal" id="f_tanggal" class="form-control" value="<?= htmlspecialchars($data['tanggal']) ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Isi Konten <span class="required">*</span></label>
                        <div id="quillEditor" style="height:350px;"><?= $data['isi'] ?></div>
                        <div class="field-counter" id="wordCounter">0 kata · ~1 menit baca</div>
                    </div>
                    
                    <div class="form-group">
                        <label>Tags / Kata Kunci</label>
                        <div class="tags-input-wrap" id="tagsWrap" onclick="document.getElementById('tagsInput').focus()">
                            <input type="text" id="tagsInput" placeholder="Ketik tag lalu tekan Enter...">
                        </div>
                        <input type="hidden" name="tags" id="tagsHidden" value="<?= htmlspecialchars($data['tags'] ?? '') ?>">
                        <small style="color:var(--text-muted); margin-top:.3rem; display:block;">
                            <i class="fa-solid fa-info-circle"></i> Tekan Enter atau koma untuk menambah tag.
                        </small>
                    </div>
                    
                    <!-- GAMBAR -->
                    <div class="form-section-title"><i class="fa-solid fa-image"></i> Gambar Utama</div>
                    
                    <div class="form-group">
                        <div class="upload-zone" id="dropZone">
                            <i class="fa-solid fa-cloud-arrow-up"></i>
                            <p style="margin:0; font-weight:600; color:var(--text-main);">Klik atau Drag & Drop gambar di sini</p>
                            <small style="color:var(--text-muted);">JPG, PNG, WebP (Maks 5MB). Rasio 16:9 direkomendasikan (1200×675px).</small>
                            <input type="file" name="gambar" accept="image/*" id="fileInput">
                        </div>
                        <div class="preview-container" id="previewContainer">
                            <img src="<?= !empty($data['gambar']) ? '../'.htmlspecialchars($data['gambar']) : '' ?>" id="previewImg">
                            <button type="button" class="preview-remove" onclick="removeImage(event)"><i class="fa-solid fa-xmark"></i></button>
                        </div>
                        <?php if (!empty($data['gambar'])): ?><script>document.getElementById('previewContainer').style.display='block';</script><?php endif; ?>
                    </div>
                    
                    <!-- SEO -->
                    <div class="form-section-title"><i class="fa-solid fa-magnifying-glass-chart"></i> Optimasi SEO</div>
                    
                    <div class="form-group">
                        <label>Meta Title (SEO)</label>
                        <div class="input-icon-wrap">
                            <i class="fa-solid fa-heading input-icon"></i>
                            <input type="text" name="seo_title" id="f_seo_title" class="form-control" value="<?= htmlspecialchars($data['seo_title'] ?? '') ?>" maxlength="70" data-counter="seoTitleCounter" placeholder="Biarkan kosong untuk menggunakan judul berita">
                        </div>
                        <div class="field-counter" id="seoTitleCounter">0 / 70</div>
                    </div>
                    
                    <div class="form-group">
                        <label>Meta Description</label>
                        <div class="input-icon-wrap">
                            <textarea name="seo_description" id="f_seo_desc" rows="3" class="form-control" maxlength="170" data-counter="seoDescCounter" placeholder="Ringkasan singkat berita yang akan tampil di hasil pencarian Google..." style="padding-left:14px; padding-top:12px;"><?= htmlspecialchars($data['seo_description'] ?? '') ?></textarea>
                        </div>
                        <div class="field-counter" id="seoDescCounter">0 / 170</div>
                    </div>
                    
                    <div class="form-group">
                        <label>Meta Keywords</label>
                        <div class="input-icon-wrap">
                            <i class="fa-solid fa-tags input-icon"></i>
                            <input type="text" name="seo_keywords" class="form-control" value="<?= htmlspecialchars($data['seo_keywords'] ?? '') ?>" placeholder="kata,kunci,berita,pbsi">
                        </div>
                    </div>
                    
                    <!-- META -->
                    <div class="form-section-title"><i class="fa-solid fa-sliders"></i> Pengaturan</div>
                    
                    <div class="form-group">
                        <label>Penulis</label>
                        <div class="input-icon-wrap">
                            <i class="fa-solid fa-user input-icon"></i>
                            <input type="text" name="penulis" id="f_penulis" class="form-control" value="<?= htmlspecialchars($data['penulis']) ?>" placeholder="Nama penulis">
                        </div>
                    </div>
                    
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem;">
                        <div class="form-group">
                            <label style="display:block; margin-bottom:.5rem; font-weight:600; font-size:.88rem;">Status</label>
                            <label class="toggle-switch">
                                <input type="checkbox" name="status" value="1" <?= ($data['status'] ?? 1) == 1 ? 'checked' : '' ?>>
                                <span class="toggle-slider"></span>
                            </label>
                            <span style="margin-left:.5rem; font-weight:600; font-size:.88rem;">
                                <i class="fa-solid fa-check-circle" style="color:var(--success);"></i>
                                Publikasikan
                            </span>
                        </div>
                        <div class="form-group">
                            <label style="display:block; margin-bottom:.5rem; font-weight:600; font-size:.88rem;">Featured</label>
                            <label class="toggle-switch">
                                <input type="checkbox" name="is_featured" value="1" <?= ($data['is_featured'] ?? 0) == 1 ? 'checked' : '' ?>>
                                <span class="toggle-slider"></span>
                            </label>
                            <span style="margin-left:.5rem; font-weight:600; font-size:.88rem;">
                                <i class="fa-solid fa-star" style="color:var(--warning);"></i>
                                Sorot di Beranda
                            </span>
                        </div>
                    </div>
                    
                    <div style="margin-top:2rem; display:flex; gap:1rem; padding-top:1.5rem; border-top:1px solid var(--border-dark);">
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> <?= $data['id']>0?'Perbarui':'Simpan' ?> Berita</button>
                        <a href="kelola_berita.php" class="btn btn-secondary"><i class="fa-solid fa-xmark"></i> Batal</a>
                    </div>
                </div>
                
                <!-- RIGHT: LIVE PREVIEW -->
                <div class="berita-live-preview">
                    <div class="preview-title"><i class="fa-solid fa-eye"></i> Live Preview</div>
                    
                    <!-- Article Card Preview -->
                    <div class="preview-article-card">
                        <div class="preview-article-img" id="prevImgWrap">
                            <i class="fa-solid fa-image" id="prevImgPlaceholder"></i>
                            <img src="" id="prevImg" style="display:none;">
                            <span class="featured-badge" id="prevFeaturedBadge">⭐ Featured</span>
                        </div>
                        <div class="preview-article-body">
                            <span class="preview-article-cat" id="prevKategori"><?= htmlspecialchars($data['kategori'] ?: 'Kegiatan') ?></span>
                            <h3 class="preview-article-title" id="prevJudul"><?= htmlspecialchars($data['judul'] ?: 'Judul Berita Anda') ?></h3>
                            <div class="preview-article-meta">
                                <span><i class="fa-solid fa-user"></i> <span id="prevPenulis"><?= htmlspecialchars($data['penulis'] ?: $_SESSION['admin_name']) ?></span></span>
                                <span><i class="fa-regular fa-calendar"></i> <span id="prevTanggal"><?= date('d M Y', strtotime($data['tanggal'] ?: date('Y-m-d'))) ?></span></span>
                                <span><i class="fa-solid fa-clock"></i> <span id="prevReadTime">~1 min</span></span>
                            </div>
                            <div class="preview-article-tags" id="prevTags"></div>
                        </div>
                    </div>
                    
                    <!-- SEO Preview -->
                    <div class="seo-preview">
                        <div class="seo-preview-title"><i class="fa-brands fa-google" style="color:#4285F4;"></i> Tampilan di Google Search</div>
                        <div class="seo-title-line" id="prevSeoTitle"><?= htmlspecialchars($data['seo_title'] ?: $data['judul'] ?: 'Judul Berita') ?></div>
                        <div class="seo-url-line" id="prevSeoUrl"><?= htmlspecialchars(BASE_URL ?? 'https://pbsi.ummaumere.ac.id/') ?>berita/<span id="prevSlugVal"><?= htmlspecialchars($data['slug'] ?: 'judul-berita') ?></span></div>
                        <div class="seo-desc-line" id="prevSeoDesc"><?= htmlspecialchars($data['seo_description'] ?: 'Deskripsi singkat berita akan muncul di sini. Tulis ringkasan yang menarik untuk meningkatkan CTR.') ?></div>
                    </div>
                    
                    <div style="margin-top:1rem; padding:.8rem; background:rgba(46,139,87,0.08); border:1px solid rgba(46,139,87,0.2); border-radius:var(--radius-md); font-size:.78rem; color:var(--text-muted);">
                        <i class="fa-solid fa-circle-check" style="color:var(--success);"></i>
                        <strong style="color:var(--success);"> Tips SEO:</strong> Judul di bawah 60 karakter, deskripsi 150-160 karakter, dan gambar dengan alt text akan meningkatkan ranking di Google.
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // ===== QUILL EDITOR =====
    const quill = new Quill('#quillEditor', {
        theme: 'snow',
        placeholder: 'Tulis konten berita yang menarik di sini...',
        modules: {
            toolbar: [
                [{ 'header': [1, 2, 3, false] }],
                ['bold', 'italic', 'underline', 'strike'],
                [{ 'color': [] }, { 'background': [] }],
                [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                ['blockquote', 'code-block'],
                ['link', 'image', 'video'],
                ['clean']
            ]
        }
    });
    
    // Sync to hidden input on submit
    document.getElementById('beritaForm').addEventListener('submit', function() {
        document.getElementById('hiddenIsi').value = quill.root.innerHTML;
    });
    
    // ===== CHARACTER COUNTERS =====
    function updateCounter(input, counterId, max, idealMax = null) {
        const counter = document.getElementById(counterId);
        if (!counter) return;
        const len = input.value.length;
        counter.textContent = `${len} / ${max}`;
        counter.className = 'field-counter';
        if (idealMax && len <= idealMax) counter.classList.add('good');
        else if (len > max * 0.9) counter.classList.add('warning');
        if (len > max) counter.classList.add('danger');
    }
    
    // Judul counter (ideal 60 chars for SEO)
    const judulInput = document.getElementById('f_judul');
    if (judulInput) {
        updateCounter(judulInput, 'judulCounter', 150, 60);
        judulInput.addEventListener('input', () => updateCounter(judulInput, 'judulCounter', 150, 60));
    }
    
    // SEO Title counter
    const seoTitle = document.getElementById('f_seo_title');
    if (seoTitle) {
        updateCounter(seoTitle, 'seoTitleCounter', 70, 60);
        seoTitle.addEventListener('input', () => updateCounter(seoTitle, 'seoTitleCounter', 70, 60));
    }
    
    // SEO Desc counter
    const seoDesc = document.getElementById('f_seo_desc');
    if (seoDesc) {
        updateCounter(seoDesc, 'seoDescCounter', 170, 160);
        seoDesc.addEventListener('input', () => updateCounter(seoDesc, 'seoDescCounter', 170, 160));
    }
    
    // Word counter
    function updateWordCount() {
        const text = quill.getText().trim();
        const words = text ? text.split(/\s+/).length : 0;
        const readTime = Math.max(1, Math.ceil(words / 200));
        const el = document.getElementById('wordCounter');
        if (el) el.textContent = `${words} kata · ~${readTime} menit baca`;
    }
    quill.on('text-change', updateWordCount);
    updateWordCount();
    
    // ===== LIVE PREVIEW BINDINGS =====
    judulInput?.addEventListener('input', e => {
        const val = e.target.value || 'Judul Berita Anda';
        document.getElementById('prevJudul').textContent = val;
        const seoTitle = document.getElementById('f_seo_title')?.value;
        document.getElementById('prevSeoTitle').textContent = seoTitle || val;
    });
    
    document.getElementById('f_kategori')?.addEventListener('change', e => {
        document.getElementById('prevKategori').textContent = e.target.value || 'Kegiatan';
    });
    
    document.getElementById('f_penulis')?.addEventListener('input', e => {
        document.getElementById('prevPenulis').textContent = e.target.value || '<?= $_SESSION['admin_name'] ?>';
    });
    
    document.getElementById('f_tanggal')?.addEventListener('change', e => {
        if (e.target.value) {
            const d = new Date(e.target.value);
            const months = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
            document.getElementById('prevTanggal').textContent = `${d.getDate()} ${months[d.getMonth()]} ${d.getFullYear()}`;
        }
    });
    
    document.getElementById('f_slug')?.addEventListener('input', e => {
        const val = e.target.value || 'judul-berita';
        document.getElementById('prevSlugVal').textContent = val;
        document.getElementById('slugValue').textContent = val;
    });
    
    document.getElementById('f_seo_title')?.addEventListener('input', e => {
        const judulVal = document.getElementById('f_judul').value;
        document.getElementById('prevSeoTitle').textContent = e.target.value || judulVal || 'Judul Berita';
    });
    
    document.getElementById('f_seo_desc')?.addEventListener('input', e => {
        document.getElementById('prevSeoDesc').textContent = e.target.value || 'Deskripsi singkat berita akan muncul di sini. Tulis ringkasan yang menarik untuk meningkatkan CTR.';
    });
    
    // Featured toggle
    document.querySelector('input[name="is_featured"]')?.addEventListener('change', function() {
        document.getElementById('prevFeaturedBadge').classList.toggle('active', this.checked);
    });
    // Initial state
    const initFeatured = document.querySelector('input[name="is_featured"]')?.checked;
    if (initFeatured) document.getElementById('prevFeaturedBadge').classList.add('active');
    
    // Image preview
    const fileInput = document.getElementById('fileInput');
    const dropZone = document.getElementById('dropZone');
    const previewContainer = document.getElementById('previewContainer');
    const previewImg = document.getElementById('previewImg');
    const prevImg = document.getElementById('prevImg');
    const prevImgPlaceholder = document.getElementById('prevImgPlaceholder');
    
    function handleFile(file) {
        const reader = new FileReader();
        reader.onload = (e) => {
            previewImg.src = e.target.result;
            previewContainer.style.display = 'block';
            prevImg.src = e.target.result;
            prevImg.style.display = 'block';
            prevImgPlaceholder.style.display = 'none';
        };
        reader.readAsDataURL(file);
    }
    
    fileInput?.addEventListener('change', (e) => {
        if (e.target.files.length) handleFile(e.target.files[0]);
    });
    
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
    
    window.removeImage = function(e) {
        e.preventDefault();
        e.stopPropagation();
        fileInput.value = '';
        previewContainer.style.display = 'none';
        previewImg.src = '';
        prevImg.src = '';
        prevImg.style.display = 'none';
        prevImgPlaceholder.style.display = '';
    };
    
    // ===== TAGS INPUT =====
    const tagsInput = document.getElementById('tagsInput');
    const tagsWrap = document.getElementById('tagsWrap');
    const tagsHidden = document.getElementById('tagsHidden');
    const prevTagsContainer = document.getElementById('prevTags');
    let tags = (tagsHidden.value || '').split(',').filter(t => t.trim());
    
    function renderTags() {
        tagsWrap.querySelectorAll('.tag-chip').forEach(c => c.remove());
        tags.forEach((tag, i) => {
            const chip = document.createElement('span');
            chip.className = 'tag-chip';
            chip.innerHTML = `#${tag} <button type="button" onclick="removeTag(${i})">×</button>`;
            tagsWrap.insertBefore(chip, tagsInput);
        });
        tagsHidden.value = tags.join(',');
        
        // Update preview
        if (prevTagsContainer) {
            prevTagsContainer.innerHTML = tags.map(t => `<span class="preview-tag">#${t}</span>`).join('');
        }
    }
    
    window.removeTag = function(index) {
        tags.splice(index, 1);
        renderTags();
    };
    
    tagsInput?.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' || e.key === ',') {
            e.preventDefault();
            const val = this.value.trim().replace(/^#/, '');
            if (val && !tags.includes(val)) {
                tags.push(val);
                renderTags();
            }
            this.value = '';
        } else if (e.key === 'Backspace' && !this.value && tags.length) {
            tags.pop();
            renderTags();
        }
    });
    
    // Initial render
    renderTags();
});

function generateSlug(text) {
    const slugInput = document.getElementById('f_slug');
    if (slugInput && (slugInput.value === '' || slugInput.dataset.auto !== 'false')) {
        slugInput.dataset.auto = 'true';
        const slug = text.toLowerCase()
            .replace(/[^a-z0-9\s-]/g, '')
            .replace(/\s+/g, '-')
            .replace(/-+/g, '-')
            .replace(/^-|-$/g, '');
        slugInput.value = slug;
        document.getElementById('prevSlugVal').textContent = slug || 'judul-berita';
        document.getElementById('slugValue').textContent = slug || 'judul-berita';
    }
}
</script>

<?php else: ?>
<!-- ===== FILTER BAR ===== -->
<?php if (!empty($items)): ?>
<div class="berita-filter-bar">
    <div class="form-group">
        <label><i class="fa-solid fa-search"></i> Cari Berita</label>
        <input type="text" id="beritaSearch" class="form-control" placeholder="Ketik judul atau penulis...">
    </div>
    <div class="form-group">
        <label><i class="fa-solid fa-tag"></i> Kategori</label>
        <select id="kategoriFilter" class="form-control">
            <option value="all">Semua Kategori</option>
            <?php foreach ($unique_kategori as $k): ?>
            <option value="<?= htmlspecialchars($k) ?>"><?= htmlspecialchars($k) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label><i class="fa-solid fa-filter"></i> Status</label>
        <select id="statusFilter" class="form-control">
            <option value="all">Semua Status</option>
            <option value="published">Published</option>
            <option value="draft">Draft</option>
        </select>
    </div>
    <div class="form-group">
        <label style="visibility:hidden;">&nbsp;</label>
        <button type="button" class="btn btn-sm btn-secondary" onclick="resetFilters()" style="width:100%;">
            <i class="fa-solid fa-rotate-left"></i> Reset
        </button>
    </div>
</div>

<input type="hidden" id="featuredFilter" value="all">

<!-- BULK ACTIONS BAR -->
<div class="bulk-actions-bar" id="bulkActionsBar">
    <div class="bulk-info">
        <i class="fa-solid fa-check-double" style="color:var(--crimson);"></i>
        <span><span class="bulk-count" id="bulkCount">0</span> berita dipilih</span>
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

<!-- ===== TABLE VIEW ===== -->
<div class="panel">
    <div class="panel-body no-padding">
        <div class="table-wrapper">
            <table class="berita-table">
                <thead>
                    <tr>
                        <th style="width:40px;">
                            <input type="checkbox" id="selectAll" style="width:16px; height:16px; accent-color:var(--crimson);">
                        </th>
                        <th>Berita</th>
                        <th style="width:110px;">Kategori</th>
                        <th style="width:100px;">Status</th>
                        <th style="width:120px;">Views</th>
                        <th style="width:120px;">Tanggal</th>
                        <th style="width:160px;">Aksi</th>
                    </tr>
                </thead>
                <tbody id="beritaTableBody">
                    <?php foreach ($items as $item): 
                        $token = hash_hmac('sha256', $item['id'], $_SESSION['csrf_token']);
                        $isPublished = ($item['status'] ?? 0) == 1;
                        $isFeatured = ($item['is_featured'] ?? 0) == 1;
                    ?>
                    <tr class="berita-row"
                         data-search="<?= strtolower(htmlspecialchars(($item['judul'] ?? '') . ' ' . ($item['penulis'] ?? ''))) ?>"
                         data-kategori="<?= htmlspecialchars($item['kategori'] ?? '') ?>"
                         data-status="<?= $isPublished ? 'published' : 'draft' ?>"
                         data-featured="<?= $isFeatured ? '1' : '0' ?>">
                        <td>
                            <input type="checkbox" class="row-checkbox" value="<?= $item['id'] ?>" style="width:16px; height:16px; accent-color:var(--crimson);">
                        </td>
                        <td>
                            <div class="news-cell">
                                <div class="news-thumb">
                                    <?php if (!empty($item['gambar'])): ?>
                                        <img src="<?= filter_var($item['gambar'], FILTER_VALIDATE_URL) ? htmlspecialchars($item['gambar']) : '../'.htmlspecialchars($item['gambar']) ?>" alt="">
                                    <?php else: ?>
                                        <div style="width:100%; height:100%; background:linear-gradient(135deg, var(--crimson), var(--crimson-light)); display:flex; align-items:center; justify-content:center; color:#fff;"><i class="fa-solid fa-newspaper"></i></div>
                                    <?php endif; ?>
                                </div>
                                <div class="news-cell-info">
                                    <div class="news-cell-title">
                                        <?= htmlspecialchars($item['judul']) ?>
                                        <?php if ($isFeatured): ?><i class="fa-solid fa-star featured-star"></i><?php endif; ?>
                                    </div>
                                    <div class="news-cell-slug"><?= htmlspecialchars($item['slug']) ?></div>
                                    <div class="news-cell-meta">
                                        <span><i class="fa-solid fa-user"></i> <?= htmlspecialchars($item['penulis'] ?: '-') ?></span>
                                        <?php if (!empty($item['read_time'])): ?>
                                        <span><i class="fa-solid fa-clock"></i> <?= (int)$item['read_time'] ?> min</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td><span class="cat-pill"><?= htmlspecialchars($item['kategori']) ?></span></td>
                        <td>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="toggle_status" value="1">
                                <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                <input type="hidden" name="status" value="<?= $isPublished ? 0 : 1 ?>">
                                <button type="submit" class="status-pill <?= $isPublished ? 'published' : 'draft' ?>" style="border:none; cursor:pointer;" title="Klik untuk toggle">
                                    <?= $isPublished ? 'Published' : 'Draft' ?>
                                </button>
                            </form>
                        </td>
                        <td>
                            <div class="views-cell">
                                <div class="views-count"><i class="fa-solid fa-eye" style="color:var(--sky-blue); font-size:.75rem; margin-right:.3rem;"></i><?= number_format($item['views'] ?? 0) ?></div>
                                <div class="views-meta">
                                    <span><i class="fa-solid fa-heart"></i> <?= (int)($item['likes'] ?? 0) ?></span>
                                    <span><i class="fa-solid fa-share"></i> <?= (int)($item['shares'] ?? 0) ?></span>
                                </div>
                            </div>
                        </td>
                        <td style="color:var(--text-muted); font-size:.85rem;"><?= !empty($item['tanggal']) ? date('d M Y', strtotime($item['tanggal'])) : '-' ?></td>
                        <td>
                            <div class="table-actions">
                                <a href="?edit=<?= $item['id'] ?>&token=<?= $token ?>" class="table-action-btn edit" title="Edit"><i class="fa-solid fa-pen"></i></a>
                                <a href="../berita_detail.php?slug=<?= urlencode($item['slug']) ?>" target="_blank" class="table-action-btn" title="Lihat"><i class="fa-solid fa-eye"></i></a>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="toggle_featured" value="1">
                                    <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                    <input type="hidden" name="is_featured" value="<?= $isFeatured ? 0 : 1 ?>">
                                    <button type="submit" class="table-action-btn" title="<?= $isFeatured ? 'Hapus Featured' : 'Jadikan Featured' ?>" style="<?= $isFeatured ? 'background:var(--warning); color:#fff; border-color:var(--warning);' : '' ?>">
                                        <i class="fa-solid fa-star"></i>
                                    </button>
                                </form>
                                <a href="?duplicate=<?= $item['id'] ?>&token=<?= $token ?>" class="table-action-btn" title="Duplikasi" onclick="return confirm('Duplikasi berita ini?')"><i class="fa-solid fa-copy"></i></a>
                                <a href="?delete=<?= $item['id'] ?>&token=<?= $token ?>" class="table-action-btn delete" onclick="return confirm('Yakin ingin menghapus berita ini?')" title="Hapus"><i class="fa-solid fa-trash"></i></a>
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
    Menampilkan <strong id="beritaCount" style="color:var(--crimson);"><?= $total_berita ?></strong> dari <strong><?= $total_berita ?></strong> berita
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('beritaSearch');
    const kategoriFilter = document.getElementById('kategoriFilter');
    const statusFilter = document.getElementById('statusFilter');
    const countEl = document.getElementById('beritaCount');
    const selectAll = document.getElementById('selectAll');
    const bulkBar = document.getElementById('bulkActionsBar');
    const bulkCount = document.getElementById('bulkCount');
    const bulkIdsContainer = document.getElementById('bulkIdsContainer');
    
    function filterBerita() {
        const term = (searchInput?.value || '').toLowerCase();
        const kategori = kategoriFilter?.value || 'all';
        const status = statusFilter?.value || 'all';
        
        const rows = document.querySelectorAll('.berita-row');
        let visibleCount = 0;
        
        rows.forEach(row => {
            const searchText = row.dataset.search || '';
            const rowKategori = row.dataset.kategori;
            const rowStatus = row.dataset.status;
            
            const matchSearch = !term || searchText.includes(term);
            const matchKategori = kategori === 'all' || rowKategori === kategori;
            const matchStatus = status === 'all' || rowStatus === status;
            
            if (matchSearch && matchKategori && matchStatus) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        
        if (countEl) countEl.textContent = visibleCount;
    }
    
    window.resetFilters = function() {
        if (searchInput) searchInput.value = '';
        if (kategoriFilter) kategoriFilter.value = 'all';
        if (statusFilter) statusFilter.value = 'all';
        filterBerita();
    };
    
    if (searchInput) searchInput.addEventListener('input', filterBerita);
    if (kategoriFilter) kategoriFilter.addEventListener('change', filterBerita);
    if (statusFilter) statusFilter.addEventListener('change', filterBerita);
    
    // Bulk selection
    function updateBulkBar() {
        const checked = document.querySelectorAll('.row-checkbox:checked');
        if (checked.length > 0) {
            bulkBar.classList.add('active');
            bulkCount.textContent = checked.length;
            bulkIdsContainer.innerHTML = '';
            checked.forEach(cb => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'bulk_ids[]';
                input.value = cb.value;
                bulkIdsContainer.appendChild(input);
            });
        } else {
            bulkBar.classList.remove('active');
        }
    }
    
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = this.checked);
            updateBulkBar();
        });
    }
    
    document.querySelectorAll('.row-checkbox').forEach(cb => {
        cb.addEventListener('change', updateBulkBar);
    });
    
    window.clearSelection = function() {
        document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = false);
        if (selectAll) selectAll.checked = false;
        updateBulkBar();
    };
    
    window.executeBulkDelete = function() {
        const count = document.querySelectorAll('.row-checkbox:checked').length;
        Swal.fire({
            icon: 'warning',
            title: 'Hapus ' + count + ' Berita?',
            text: 'Tindakan ini tidak dapat dibatalkan.',
            showCancelButton: true,
            confirmButtonColor: '#e74c3c',
            confirmButtonText: 'Ya, Hapus',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('bulkForm').submit();
            }
        });
    };
});
</script>
<?php endif; ?>
<?php endif; ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    if ($('#beritaForm').length && $.fn.select2) {
        $('.select2-premium').select2({ 
            dropdownParent: $('#beritaForm'),
            language: { noResults: function() { return "Tidak ditemukan"; } }
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
    
    // Animated kategori bars
    setTimeout(() => {
        document.querySelectorAll('.kategori-fill').forEach(bar => {
            bar.style.width = bar.dataset.target + '%';
        });
    }, 300);
});
</script>

<?php
$page_content = ob_get_clean();
require_once 'includes/admin_template.php';
?>