<?php
require_once '../includes/config.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';
require_once 'includes/upload_helper.php';
require_login();

$page_title = 'Kelola Galeri Kegiatan';
$current_page = 'galeri';
$table = 'galeri';

// ===== AUTO-MIGRATION (Self-Healing Database) =====
$required_cols = [
    'deskripsi' => "text DEFAULT NULL",
    'urutan'    => "int(11) DEFAULT 1",
    'tipe'      => "varchar(20) DEFAULT 'image'",
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
    $stmt = $pdo->prepare("SELECT file, tipe, judul FROM $table WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if ($row && $row['tipe'] === 'image' && !empty($row['file']) && !filter_var($row['file'], FILTER_VALIDATE_URL) && file_exists('../' . $row['file'])) {
        @unlink('../' . $row['file']);
    }
    $pdo->prepare("DELETE FROM $table WHERE id = ?")->execute([$id]);
    
    $pdo->prepare("INSERT INTO admin_logs (user_id, user_name, action, detail, ip) VALUES (?, ?, 'DELETE_GALERI', ?, ?)")
        ->execute([$_SESSION['admin_id'], $_SESSION['admin_name'], 'Menghapus galeri: ' . ($row['judul'] ?? 'ID '.$id), $_SERVER['REMOTE_ADDR']]);
    
    header("Location: kelola_galeri.php?msg=deleted");
    exit;
}

// ===== HANDLE DUPLICATE =====
if (isset($_GET['duplicate']) && isset($_GET['token']) && hash_equals($_SESSION['csrf_token'], $_GET['token'])) {
    $id = (int)$_GET['duplicate'];
    $stmt = $pdo->prepare("SELECT * FROM $table WHERE id = ?");
    $stmt->execute([$id]);
    $orig = $stmt->fetch();
    if ($orig) {
        $pdo->prepare("INSERT INTO $table (judul, kategori, tipe, file, tanggal, urutan, deskripsi) VALUES (?, ?, ?, ?, ?, ?, ?)")
            ->execute([
                $orig['judul'] . ' (Copy)',
                $orig['kategori'],
                $orig['tipe'],
                $orig['file'],
                $orig['tanggal'],
                ($orig['urutan'] ?? 0) + 1,
                $orig['deskripsi'] ?? ''
            ]);
    }
    header("Location: kelola_galeri.php?msg=duplicated");
    exit;
}

// ===== HANDLE ADD/EDIT =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $judul = sanitize($_POST['judul']);
    $kategori = sanitize($_POST['kategori']);
    $tipe = sanitize($_POST['tipe']);
    $tanggal = sanitize($_POST['tanggal']);
    $deskripsi = sanitize($_POST['deskripsi']);
    $urutan = (int)$_POST['urutan'];
    $file_path = $data['file'] ?? '';
    $video_url = '';

    // Validasi
    if (empty($judul)) {
        $error = "Judul wajib diisi.";
    } else {
        if ($tipe === 'image' && !empty($_FILES['file']['name'])) {
            $upload = handle_upload($_FILES['file'], '../assets/uploads/galeri/', ['jpg', 'jpeg', 'png', 'webp']);
            if ($upload['success']) {
                $file_path = $upload['path'];
                if ($id > 0) {
                    $stmt = $pdo->prepare("SELECT file, tipe FROM $table WHERE id = ?");
                    $stmt->execute([$id]);
                    $old = $stmt->fetch();
                    if ($old && $old['tipe'] === 'image' && !empty($old['file']) && !filter_var($old['file'], FILTER_VALIDATE_URL) && file_exists('../' . $old['file'])) {
                        @unlink('../' . $old['file']);
                    }
                }
            } else {
                $error = $upload['error'];
            }
        } elseif ($tipe === 'video') {
            $video_url = sanitize($_POST['video_url']);
            if (empty($video_url)) {
                $error = "URL video wajib diisi untuk tipe video.";
            } else {
                // Extract thumbnail untuk preview
                if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $video_url, $m)) {
                    $file_path = 'https://img.youtube.com/vi/' . $m[1] . '/maxresdefault.jpg';
                } elseif (preg_match('/vimeo\.com\/(\d+)/', $video_url, $m)) {
                    $file_path = 'https://vumbnail.com/' . $m[1] . '.jpg';
                } else {
                    $file_path = $video_url;
                }
            }
        }

        if (!isset($error)) {
            $fields = "judul=:judul, kategori=:kategori, tipe=:tipe, file=:file, tanggal=:tanggal, urutan=:urutan, deskripsi=:deskripsi";
            $params = [':judul'=>$judul, ':kategori'=>$kategori, ':tipe'=>$tipe, ':file'=>$file_path, ':tanggal'=>$tanggal, ':urutan'=>$urutan, ':deskripsi'=>$deskripsi];

            if ($id > 0) {
                $pdo->prepare("UPDATE $table SET $fields WHERE id=:id")->execute(array_merge($params, [':id'=>$id]));
                $action = 'UPDATE_GALERI';
                $detail = "Memperbarui galeri: $judul";
            } else {
                $pdo->prepare("INSERT INTO $table SET $fields")->execute($params);
                $action = 'ADD_GALERI';
                $detail = "Menambah galeri baru: $judul";
            }
            
            $pdo->prepare("INSERT INTO admin_logs (user_id, user_name, action, detail, ip) VALUES (?, ?, ?, ?, ?)")
                ->execute([$_SESSION['admin_id'], $_SESSION['admin_name'], $action, $detail, $_SERVER['REMOTE_ADDR']]);
            
            header("Location: kelola_galeri.php?msg=saved");
            exit;
        }
    }
}

// ===== FETCH DATA =====
$items = $pdo->query("SELECT * FROM $table ORDER BY urutan ASC, id DESC")->fetchAll();

// Normalize NULL values
foreach ($items as &$it) {
    foreach ($it as $k => $v) {
        if ($v === null) $it[$k] = '';
    }
}
unset($it);

// Group by category
$by_category = [];
foreach ($items as $it) {
    $cat = $it['kategori'] ?? 'Lainnya';
    $by_category[$cat][] = $it;
}

// Statistics
$total_items = count($items);
$image_count = count(array_filter($items, fn($i) => ($i['tipe'] ?? 'image') === 'image'));
$video_count = count(array_filter($items, fn($i) => ($i['tipe'] ?? '') === 'video'));
$total_categories = count($by_category);

// Unique categories for filter
$unique_cats = array_keys($by_category);
sort($unique_cats);

// Form data
$data = ['id'=>0, 'judul'=>'', 'kategori'=>'Dokumentasi', 'file'=>'', 'tipe'=>'image', 'tanggal'=>date('Y-m-d'), 'urutan'=>1, 'deskripsi'=>''];
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

// Helper: get thumbnail preview
function getGalleryThumb($item) {
    if (($item['tipe'] ?? 'image') === 'video') {
        $url = $item['file'];
        if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $url, $m)) {
            return 'https://img.youtube.com/vi/' . $m[1] . '/hqdefault.jpg';
        }
        if (preg_match('/vimeo\.com\/(\d+)/', $url, $m)) {
            return 'https://vumbnail.com/' . $m[1] . '.jpg';
        }
    } elseif (!empty($item['file'])) {
        if (filter_var($item['file'], FILTER_VALIDATE_URL)) return $item['file'];
        return '../' . $item['file'];
    }
    return "data:image/svg+xml;utf8," . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="400" height="300"><rect width="400" height="300" fill="#1a1a2e"/><text x="200" y="150" font-family="Arial" font-size="20" fill="rgba(255,255,255,.4)" text-anchor="middle" dominant-baseline="middle">No Preview</text></svg>');
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
.galeri-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}
.galeri-stat-card {
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
.galeri-stat-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); }
.galeri-stat-icon {
    width: 52px; height: 52px;
    border-radius: var(--radius-md);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.4rem; flex-shrink: 0;
}
.galeri-stat-card.accent-crimson .galeri-stat-icon { background: rgba(138,21,56,0.15); color: var(--crimson); }
.galeri-stat-card.accent-sky .galeri-stat-icon { background: rgba(135,206,235,0.15); color: var(--sky-blue); }
.galeri-stat-card.accent-emerald .galeri-stat-icon { background: rgba(46,139,87,0.15); color: var(--success); }
.galeri-stat-card.accent-warning .galeri-stat-icon { background: rgba(243,156,18,0.15); color: var(--warning); }
.galeri-stat-value { font-size: 1.9rem; font-weight: 900; color: #fff; line-height: 1; }
.galeri-stat-label { color: var(--text-muted); font-size: .82rem; margin-top: .2rem; }

/* ===== CATEGORY DISTRIBUTION ===== */
.cat-distribution {
    background: var(--panel-bg);
    border: 1px solid var(--border-dark);
    border-radius: var(--radius-md);
    padding: 1.5rem;
    margin-bottom: 2rem;
}
.cat-dist-title {
    font-size: 1rem; font-weight: 700; color: #fff;
    margin-bottom: 1.2rem;
    display: flex; align-items: center; gap: .6rem;
}
.cat-dist-title i { color: var(--crimson); }
.cat-list { display: flex; flex-direction: column; gap: 1rem; }
.cat-item { display: flex; flex-direction: column; gap: .4rem; }
.cat-header { display: flex; justify-content: space-between; align-items: center; }
.cat-name { font-weight: 600; color: var(--text-main); font-size: .9rem; }
.cat-count { font-weight: 700; font-size: .85rem; color: var(--sky-blue); }
.cat-bar { height: 8px; background: var(--bg-darker); border-radius: 50px; overflow: hidden; }
.cat-fill {
    height: 100%;
    border-radius: 50px;
    transition: width 1s ease;
    width: 0;
    background: linear-gradient(90deg, var(--crimson), var(--crimson-light));
}

/* ===== GALLERY PREVIEW GRID ===== */
.gallery-preview-grid {
    background: var(--bg-darker);
    border: 1px solid var(--border-dark);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    margin-bottom: 2rem;
}
.gallery-preview-title {
    font-size: .85rem;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    color: var(--sky-blue);
    margin-bottom: 1.2rem;
    display: flex; align-items: center; gap: .5rem;
    font-weight: 700;
    justify-content: center;
    text-align: center;
}
.preview-grid-container {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 1rem;
    max-height: 400px;
    overflow-y: auto;
    padding-right: .5rem;
}
.preview-grid-container::-webkit-scrollbar { width: 6px; }
.preview-grid-container::-webkit-scrollbar-track { background: var(--bg-darker); }
.preview-grid-container::-webkit-scrollbar-thumb { background: var(--border-dark); border-radius: 3px; }
.preview-grid-item {
    position: relative;
    aspect-ratio: 4/3;
    border-radius: var(--radius-md);
    overflow: hidden;
    cursor: pointer;
    background: #000;
    border: 2px solid transparent;
    transition: all .3s ease;
}
.preview-grid-item:hover {
    border-color: var(--crimson);
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(138,21,56,.3);
}
.preview-grid-item img {
    width: 100%; height: 100%;
    object-fit: cover;
    transition: transform .5s ease;
}
.preview-grid-item:hover img { transform: scale(1.1); }
.preview-grid-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(to top, rgba(0,0,0,.85), transparent 60%);
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
    padding: .8rem;
    opacity: 0;
    transition: opacity .3s ease;
}
.preview-grid-item:hover .preview-grid-overlay { opacity: 1; }
.preview-grid-overlay .pg-title {
    color: #fff;
    font-size: .8rem;
    font-weight: 700;
    line-height: 1.2;
    max-height: 2.4em;
    overflow: hidden;
}
.preview-grid-overlay .pg-cat {
    display: inline-block;
    background: var(--crimson);
    color: #fff;
    padding: .1rem .5rem;
    border-radius: 50px;
    font-size: .65rem;
    font-weight: 700;
    margin-top: .3rem;
    width: fit-content;
}
.preview-grid-type {
    position: absolute;
    top: .5rem; right: .5rem;
    background: rgba(231,76,60,0.9);
    color: #fff;
    padding: .2rem .5rem;
    border-radius: 50px;
    font-size: .65rem;
    font-weight: 700;
    backdrop-filter: blur(4px);
    z-index: 2;
}
.preview-grid-type.image { background: rgba(135,206,235,0.9); color: #000; }

/* ===== FILTER BAR ===== */
.galeri-filter-bar {
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
.galeri-filter-bar .form-group { margin: 0; }
.galeri-filter-bar label { font-size: .85rem; color: var(--text-muted); margin-bottom: .4rem; display: block; font-weight: 600; }

/* ===== GALLERY CARD GRID (LIST MODE) ===== */
.galeri-card-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}
.galeri-card {
    background: var(--panel-bg);
    border: 1px solid var(--border-dark);
    border-radius: var(--radius-md);
    overflow: hidden;
    transition: all .3s ease;
    position: relative;
}
.galeri-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-lg);
    border-color: var(--crimson);
}
.galeri-card-thumb {
    position: relative;
    aspect-ratio: 4/3;
    overflow: hidden;
    background: var(--bg-darker);
    cursor: pointer;
}
.galeri-card-thumb img {
    width: 100%; height: 100%;
    object-fit: cover;
    transition: transform .5s ease;
}
.galeri-card:hover .galeri-card-thumb img { transform: scale(1.08); }
.galeri-card-thumb::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(to top, rgba(0,0,0,.7), transparent 60%);
    opacity: 0;
    transition: opacity .3s ease;
}
.galeri-card:hover .galeri-card-thumb::after { opacity: 1; }
.galeri-card-thumb .preview-icon {
    position: absolute;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%) scale(0.8);
    width: 50px; height: 50px;
    border-radius: 50%;
    background: rgba(138,21,56,.9);
    color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.2rem;
    opacity: 0;
    transition: all .3s ease;
    z-index: 2;
    backdrop-filter: blur(4px);
}
.galeri-card:hover .preview-icon { opacity: 1; transform: translate(-50%, -50%) scale(1); }
.galeri-card-type {
    position: absolute;
    top: 10px; left: 10px;
    padding: .25rem .7rem;
    border-radius: 50px;
    font-size: .7rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .5px;
    z-index: 2;
    backdrop-filter: blur(8px);
}
.galeri-card-type.image { background: rgba(135,206,235,0.9); color: #000; }
.galeri-card-type.video { background: rgba(231,76,60,0.9); color: #fff; }
.galeri-card-body { padding: 1.2rem; }
.galeri-card-title {
    font-size: .95rem;
    font-weight: 800;
    color: #fff;
    margin-bottom: .4rem;
    line-height: 1.3;
    max-height: 2.6em;
    overflow: hidden;
}
.galeri-card-meta {
    display: flex;
    gap: .8rem;
    font-size: .78rem;
    color: var(--text-muted);
    margin-bottom: .8rem;
    flex-wrap: wrap;
}
.galeri-card-meta span { display: flex; align-items: center; gap: .3rem; }
.galeri-card-meta i { color: var(--sky-blue); }
.galeri-card-cat {
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
.galeri-card-actions {
    display: flex;
    gap: .3rem;
    padding-top: .8rem;
    border-top: 1px solid var(--border-dark);
}
.galeri-card-actions .table-action-btn { flex: 1; }

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

.preview-container {
    margin-top: 1rem;
    border-radius: var(--radius-md);
    overflow: hidden;
    border: 1px solid var(--border-dark);
    display: none;
    aspect-ratio: 16/9;
    background: #000;
}
.preview-container img, .preview-container iframe {
    width: 100%; height: 100%;
    object-fit: cover;
    display: block;
}

/* ===== SPECS INFO ===== */
.specs-info-box {
    background: linear-gradient(135deg, rgba(135,206,235,0.08), rgba(138,21,56,0.08));
    border: 1px solid var(--border-dark);
    border-radius: var(--radius-md);
    padding: 1rem;
    margin-top: 1rem;
}
.specs-info-box h4 {
    color: var(--sky-blue);
    font-size: .85rem;
    margin-bottom: .6rem;
    display: flex; align-items: center; gap: .4rem;
}
.specs-info-box ul { list-style: none; padding: 0; margin: 0; font-size: .78rem; color: var(--text-muted); line-height: 1.7; }
.specs-info-box ul li { display: flex; align-items: flex-start; gap: .4rem; }
.specs-info-box ul li i { color: var(--success); margin-top: .2rem; flex-shrink: 0; }

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

/* ===== LIGHTBOX (Admin Preview) ===== */
.admin-lightbox {
    position: fixed;
    inset: 0;
    z-index: 10000;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    visibility: hidden;
    transition: all .3s ease;
}
.admin-lightbox.active { opacity: 1; visibility: visible; }
.admin-lightbox-backdrop {
    position: absolute;
    inset: 0;
    background: rgba(0,0,0,.95);
    backdrop-filter: blur(10px);
}
.admin-lightbox-content {
    position: relative;
    z-index: 1;
    max-width: 90vw;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    align-items: center;
}
.admin-lightbox-close {
    position: absolute;
    top: -50px;
    right: 0;
    width: 44px; height: 44px;
    border-radius: 50%;
    background: rgba(255,255,255,.1);
    border: 2px solid rgba(255,255,255,.3);
    color: #fff;
    font-size: 1.5rem;
    cursor: pointer;
    transition: all .3s ease;
    display: flex; align-items: center; justify-content: center;
}
.admin-lightbox-close:hover { background: var(--crimson); border-color: var(--crimson); transform: rotate(90deg); }
.admin-lightbox-img-wrap {
    position: relative;
    max-width: 100%;
    max-height: 70vh;
    display: flex; align-items: center; justify-content: center;
}
.admin-lightbox-img {
    max-width: 100%;
    max-height: 70vh;
    object-fit: contain;
    border-radius: 12px;
    box-shadow: 0 20px 60px rgba(0,0,0,.5);
}
.admin-lightbox-nav {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    width: 50px; height: 50px;
    border-radius: 50%;
    background: rgba(255,255,255,.1);
    border: 2px solid rgba(255,255,255,.3);
    color: #fff;
    font-size: 1.3rem;
    cursor: pointer;
    transition: all .3s ease;
    display: flex; align-items: center; justify-content: center;
    z-index: 2;
}
.admin-lightbox-nav:hover { background: var(--crimson); border-color: var(--crimson); }
.admin-lightbox-prev { left: -70px; }
.admin-lightbox-next { right: -70px; }
.admin-lightbox-info {
    margin-top: 1.5rem;
    text-align: center;
    color: #fff;
    max-width: 600px;
}
.admin-lightbox-title { font-size: 1.3rem; margin-bottom: .5rem; font-weight: 700; }
.admin-lightbox-desc { color: #bdc3c7; font-size: .95rem; line-height: 1.6; margin-bottom: 1rem; }
.admin-lightbox-counter { color: var(--sky-blue); font-weight: 700; font-size: .9rem; }

/* ===== EMPTY STATE ===== */
.galeri-empty {
    text-align: center;
    padding: 4rem 2rem;
    background: var(--panel-bg);
    border: 2px dashed var(--border-dark);
    border-radius: var(--radius-lg);
}
.galeri-empty-icon {
    width: 100px; height: 100px;
    margin: 0 auto 1.5rem;
    background: linear-gradient(135deg, var(--crimson), var(--crimson-light));
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 3rem;
    color: #fff;
    box-shadow: 0 10px 30px rgba(138,21,56,.3);
}
.galeri-empty h3 { color: #fff; margin-bottom: .5rem; font-size: 1.3rem; }
.galeri-empty p { color: var(--text-muted); margin-bottom: 1.5rem; max-width: 500px; margin-left: auto; margin-right: auto; }

/* ===== FORM GRID ===== */
.galeri-form-grid {
    display: grid;
    grid-template-columns: 1.4fr 1fr;
    gap: 2rem;
}
@media (max-width: 992px) {
    .galeri-form-grid { grid-template-columns: 1fr; }
    .galeri-filter-bar { grid-template-columns: 1fr; }
    .galeri-card-grid { grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); }
    .admin-lightbox-prev { left: 10px; }
    .admin-lightbox-next { right: 10px; }
    .admin-lightbox-close { top: 10px; right: 10px; }
}
@media (max-width: 576px) {
    .preview-grid-container { grid-template-columns: repeat(2, 1fr); }
}
</style>

<?php if (isset($_GET['msg'])): 
    $msgs = [
        'saved' => ['success', 'Galeri berhasil disimpan!'],
        'deleted' => ['success', 'Galeri berhasil dihapus!'],
        'duplicated' => ['success', 'Galeri berhasil diduplikasi!']
    ];
    if (isset($msgs[$_GET['msg']])): ?>
    <script>Swal.fire({icon:'<?=$msgs[$_GET['msg']][0]?>', title:'Berhasil!', text:'<?=$msgs[$_GET['msg']][1]?>', timer:2500, showConfirmButton:false});</script>
<?php endif; endif; ?>

<?php if (isset($error)): ?>
<script>Swal.fire({icon:'error', title:'Validasi Gagal', text:'<?= addslashes($error) ?>'});</script>
<?php endif; ?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;">
    <div>
        <h2 class="section-title"><i class="fa-solid fa-images"></i> <?= isset($_GET['edit']) ? 'Edit' : 'Kelola' ?> Galeri Kegiatan</h2>
        <p style="color:var(--text-muted); font-size:.88rem; margin-top:.3rem;">
            <i class="fa-solid fa-circle-info"></i> Dokumentasi momen terbaik keluarga besar PBSI UMMaumere
        </p>
    </div>
    <div style="display:flex; gap:.8rem;">
        <?php if (isset($_GET['edit'])): ?>
        <a href="kelola_galeri.php" class="btn btn-secondary"><i class="fa-solid fa-xmark"></i> Batal</a>
        <?php else: ?>
        <a href="?action=add" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Tambah Galeri</a>
        <a href="../galeri.php" target="_blank" class="btn btn-secondary"><i class="fa-solid fa-external-link"></i> Lihat di Website</a>
        <?php endif; ?>
    </div>
</div>

<!-- ===== STATS DASHBOARD ===== -->
<div class="galeri-stats-grid">
    <div class="galeri-stat-card accent-crimson">
        <div class="galeri-stat-icon"><i class="fa-solid fa-images"></i></div>
        <div>
            <div class="galeri-stat-value" data-counter="<?= $total_items ?>">0</div>
            <div class="galeri-stat-label">Total Galeri</div>
        </div>
    </div>
    <div class="galeri-stat-card accent-sky">
        <div class="galeri-stat-icon"><i class="fa-solid fa-image"></i></div>
        <div>
            <div class="galeri-stat-value" data-counter="<?= $image_count ?>">0</div>
            <div class="galeri-stat-label">Foto</div>
        </div>
    </div>
    <div class="galeri-stat-card accent-emerald">
        <div class="galeri-stat-icon"><i class="fa-solid fa-video"></i></div>
        <div>
            <div class="galeri-stat-value" data-counter="<?= $video_count ?>">0</div>
            <div class="galeri-stat-label">Video</div>
        </div>
    </div>
    <div class="galeri-stat-card accent-warning">
        <div class="galeri-stat-icon"><i class="fa-solid fa-tags"></i></div>
        <div>
            <div class="galeri-stat-value" data-counter="<?= $total_categories ?>">0</div>
            <div class="galeri-stat-label">Kategori</div>
        </div>
    </div>
</div>

<!-- ===== CATEGORY DISTRIBUTION ===== -->
<?php if (count($by_category) > 0): ?>
<div class="cat-distribution">
    <div class="cat-dist-title">
        <i class="fa-solid fa-chart-pie"></i> Distribusi Galeri per Kategori
    </div>
    <div class="cat-list">
        <?php foreach ($by_category as $cat => $catItems): 
            $count = count($catItems);
            $percent = $total_items > 0 ? round(($count / $total_items) * 100, 1) : 0;
        ?>
        <div class="cat-item">
            <div class="cat-header">
                <span class="cat-name"><i class="fa-solid fa-tag" style="color:var(--crimson); margin-right:.4rem;"></i><?= htmlspecialchars($cat) ?></span>
                <span class="cat-count"><?= $count ?> Item (<?= $percent ?>%)</span>
            </div>
            <div class="cat-bar">
                <div class="cat-fill" data-target="<?= $percent ?>"></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- ===== GALLERY PREVIEW GRID (VISUAL) ===== -->
<?php if (!empty($items)): ?>
<div class="gallery-preview-grid">
    <div class="gallery-preview-title">
        <i class="fa-solid fa-eye"></i> PREVIEW GALERI (Klik untuk Lightbox)
    </div>
    <div class="preview-grid-container">
        <?php foreach (array_slice($items, 0, 20) as $idx => $item): 
            $thumb = getGalleryThumb($item);
            $tipe = $item['tipe'] ?? 'image';
        ?>
        <div class="preview-grid-item" onclick="openAdminLightbox(<?= $idx ?>)">
            <span class="preview-grid-type <?= $tipe ?>">
                <i class="fa-solid <?= $tipe === 'video' ? 'fa-video' : 'fa-image' ?>"></i>
            </span>
            <img src="<?= htmlspecialchars($thumb) ?>" alt="<?= htmlspecialchars($item['judul']) ?>" loading="lazy">
            <div class="preview-grid-overlay">
                <div class="pg-title"><?= htmlspecialchars($item['judul']) ?></div>
                <span class="pg-cat"><?= htmlspecialchars($item['kategori']) ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php if (count($items) > 20): ?>
    <div style="text-align:center; margin-top:1rem; color:var(--text-muted); font-size:.82rem;">
        <i class="fa-solid fa-info-circle"></i> Menampilkan 20 dari <?= $total_items ?> item galeri
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if (isset($_GET['action']) && $_GET['action'] === 'add' || isset($_GET['edit'])): ?>
<!-- ===== FORM ADD/EDIT ===== -->
<div class="panel">
    <div class="panel-header">
        <h3 class="panel-title">
            <i class="fa-solid fa-pen-to-square"></i> 
            <?= isset($_GET['edit']) ? 'Edit' : 'Tambah' ?> Galeri
        </h3>
    </div>
    <div class="panel-body">
        <form method="POST" enctype="multipart/form-data" id="galeriForm">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="id" value="<?= $data['id'] ?>">
            
            <div class="galeri-form-grid">
                <!-- FORM FIELDS -->
                <div>
                    <div class="form-section-title"><i class="fa-solid fa-heading"></i> Informasi Dasar</div>
                    
                    <div class="form-group">
                        <label>Judul Foto / Video <span class="required">*</span></label>
                        <div class="input-icon-wrap">
                            <i class="fa-solid fa-heading"></i>
                            <input type="text" name="judul" class="form-control" value="<?= htmlspecialchars((string)($data['judul'] ?? '')) ?>" required placeholder="Contoh: Wisuda Periode II 2026">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Deskripsi (Opsional)</label>
                        <textarea name="deskripsi" rows="3" class="form-control" placeholder="Deskripsi singkat momen yang diabadikan..."><?= htmlspecialchars((string)($data['deskripsi'] ?? '')) ?></textarea>
                        <small style="color:var(--text-muted);"><i class="fa-solid fa-info-circle"></i> Akan tampil di lightbox saat user mengklik gambar</small>
                    </div>
                    
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem;">
                        <div class="form-group">
                            <label>Kategori</label>
                            <select name="kategori" class="form-control select2-premium" style="width:100%;">
                                <?php foreach (['Dokumentasi', 'Sastra', 'Prestasi', 'Akademik', 'Kegiatan', 'Wisuda', 'Seminar', 'Lomba'] as $kat): ?>
                                <option value="<?= $kat ?>" <?= ($data['kategori'] ?? '')==$kat?'selected':'' ?>><?= $kat ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Tanggal</label>
                            <input type="date" name="tanggal" class="form-control" value="<?= htmlspecialchars((string)($data['tanggal'] ?? date('Y-m-d'))) ?>" required>
                        </div>
                    </div>

                    <div class="form-section-title"><i class="fa-solid fa-photo-film"></i> Media</div>
                    
                    <div class="form-group">
                        <label>Tipe Media</label>
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:.8rem;">
                            <label style="display:flex; align-items:center; gap:.8rem; padding:1rem; background:var(--bg-darker); border:2px solid var(--border-dark); border-radius:var(--radius-md); cursor:pointer; transition:all .2s;">
                                <input type="radio" name="tipe" value="image" <?= ($data['tipe'] ?? 'image')==='image'?'checked':'' ?> id="tipeImage" style="width:18px; height:18px;">
                                <i class="fa-solid fa-image" style="color:var(--sky-blue); font-size:1.3rem;"></i>
                                <div>
                                    <div style="font-weight:700; color:#fff;">Gambar</div>
                                    <small style="color:var(--text-muted);">Upload foto</small>
                                </div>
                            </label>
                            <label style="display:flex; align-items:center; gap:.8rem; padding:1rem; background:var(--bg-darker); border:2px solid var(--border-dark); border-radius:var(--radius-md); cursor:pointer; transition:all .2s;">
                                <input type="radio" name="tipe" value="video" <?= ($data['tipe'] ?? '')==='video'?'checked':'' ?> id="tipeVideo" style="width:18px; height:18px;">
                                <i class="fa-solid fa-video" style="color:var(--crimson); font-size:1.3rem;"></i>
                                <div>
                                    <div style="font-weight:700; color:#fff;">Video</div>
                                    <small style="color:var(--text-muted);">YouTube/Vimeo</small>
                                </div>
                            </label>
                        </div>
                    </div>
                    
                    <div id="imageUploadZone" style="<?= ($data['tipe'] ?? 'image')==='video'?'display:none;':'' ?>">
                        <div class="form-group">
                            <label>Upload Gambar</label>
                            <div class="upload-zone" id="dropZone">
                                <i class="fa-solid fa-cloud-arrow-up upload-icon"></i>
                                <p style="margin:0; font-weight:700; color:#fff;">Klik atau Drag & Drop gambar</p>
                                <small style="color:var(--text-muted);">JPG, PNG, WebP (Maks 5MB)</small>
                                <input type="file" name="file" accept="image/*" id="fileInput">
                            </div>
                            <div class="preview-container" id="previewContainer">
                                <img src="<?= (!empty($data['file']) && ($data['tipe'] ?? 'image')==='image') ? (filter_var($data['file'], FILTER_VALIDATE_URL) ? htmlspecialchars($data['file']) : '../'.htmlspecialchars($data['file'])) : '' ?>" id="previewImg">
                            </div>
                            <?php if (!empty($data['file']) && ($data['tipe'] ?? 'image')==='image'): ?>
                            <script>document.getElementById('previewContainer').style.display='block';</script>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div id="videoUrlZone" style="<?= ($data['tipe'] ?? 'image')==='image'?'display:none;':'' ?>">
                        <div class="form-group">
                            <label>URL Video (YouTube/Vimeo)</label>
                            <div class="input-icon-wrap">
                                <i class="fa-brands fa-youtube"></i>
                                <input type="url" name="video_url" id="videoUrlInput" class="form-control" value="<?= htmlspecialchars((string)($data['file'] ?? '')) ?>" placeholder="https://www.youtube.com/watch?v=...">
                            </div>
                            <small style="color:var(--text-muted);"><i class="fa-solid fa-info-circle"></i> Thumbnail otomatis akan diambil dari YouTube/Vimeo</small>
                        </div>
                    </div>
                    
                    <div class="specs-info-box">
                        <h4><i class="fa-solid fa-lightbulb"></i> Spesifikasi Ideal</h4>
                        <ul>
                            <li><i class="fa-solid fa-check"></i> <span><strong>Gambar:</strong> 1200x900px (rasio 4:3) atau 1920x1080px (16:9)</span></li>
                            <li><i class="fa-solid fa-check"></i> <span><strong>Format:</strong> JPG atau WebP (lebih ringan)</span></li>
                            <li><i class="fa-solid fa-check"></i> <span><strong>Ukuran file:</strong> Maksimal 500KB untuk performa optimal</span></li>
                            <li><i class="fa-solid fa-check"></i> <span><strong>Video:</strong> Gunakan URL embed YouTube/Vimeo</span></li>
                        </ul>
                    </div>

                    <div class="form-section-title"><i class="fa-solid fa-sliders"></i> Pengaturan</div>
                    
                    <div class="form-group" style="max-width:200px;">
                        <label>Urutan Tampil</label>
                        <input type="number" name="urutan" class="form-control" value="<?= (int)($data['urutan'] ?? 1) ?>" min="1">
                    </div>

                    <div style="margin-top:2rem; display:flex; gap:1rem; padding-top:1.5rem; border-top:1px solid var(--border-dark);">
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> <?= $data['id']>0?'Perbarui':'Simpan' ?> Galeri</button>
                        <a href="kelola_galeri.php" class="btn btn-secondary"><i class="fa-solid fa-xmark"></i> Batal</a>
                    </div>
                </div>

                <!-- LIVE PREVIEW -->
                <div style="position:sticky; top:20px; align-self:start;">
                    <div class="form-section-title" style="margin-top:0;">
                        <i class="fa-solid fa-eye"></i> Live Preview (Tampilan di Website)
                    </div>
                    <div style="background:#fff; border-radius:16px; overflow:hidden; box-shadow:0 15px 40px rgba(0,0,0,.2); color:#333;">
                        <div style="aspect-ratio:4/3; background:var(--bg-darker); position:relative; overflow:hidden;">
                            <img src="<?= (!empty($data['file']) && ($data['tipe'] ?? 'image')==='image') ? (filter_var($data['file'], FILTER_VALIDATE_URL) ? htmlspecialchars($data['file']) : '../'.htmlspecialchars($data['file'])) : "data:image/svg+xml;utf8," . rawurlencode('<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'400\' height=\'300\'><rect width=\'400\' height=\'300\' fill=\'#1a1a2e\'/><text x=\'200\' y=\'150\' font-family=\'Arial\' font-size=\'18\' fill=\'rgba(255,255,255,.4)\' text-anchor=\'middle\' dominant-baseline=\'middle\'>Preview Area</text></svg>') ?>" 
                                 id="prevImg" 
                                 style="width:100%; height:100%; object-fit:cover;">
                            <div style="position:absolute; inset:0; background:linear-gradient(to top, rgba(10,10,10,.7), transparent 60%); display:flex; align-items:center; justify-content:center; opacity:0; transition:opacity .3s;" id="prevOverlay">
                                <i class="fa-solid fa-magnifying-glass-plus" style="font-size:2.5rem; color:#fff;"></i>
                            </div>
                        </div>
                        <div style="padding:1.2rem 1.5rem;">
                            <h3 id="prevJudul" style="font-size:1rem; margin:0 0 .5rem; color:#222; font-weight:700;"><?= htmlspecialchars((string)($data['judul'] ?? 'Judul Foto')) ?></h3>
                            <span id="prevKategori" style="display:inline-block; background:rgba(138,21,56,.1); color:var(--crimson); padding:.25rem .8rem; border-radius:50px; font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.5px; margin-bottom:.5rem;"><?= htmlspecialchars((string)($data['kategori'] ?? 'Dokumentasi')) ?></span>
                            <div id="prevTanggal" style="color:#999; font-size:.82rem; margin-top:.5rem;">
                                <i class="fa-regular fa-calendar"></i> 
                                <?= date('d M Y', strtotime($data['tanggal'] ?: date('Y-m-d'))) ?>
                            </div>
                        </div>
                    </div>
                    
                    <div style="margin-top:1rem; padding:.8rem; background:rgba(46,139,87,0.1); border:1px solid rgba(46,139,87,0.3); border-radius:var(--radius-md); font-size:.78rem; color:var(--text-muted);">
                        <i class="fa-solid fa-circle-check" style="color:var(--success);"></i>
                        <strong style="color:var(--success);"> Tip:</strong> Gunakan foto berkualitas tinggi dengan komposisi yang menarik. Foto dengan subjek yang jelas dan pencahayaan baik akan menghasilkan galeri yang profesional.
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tipeImage = document.getElementById('tipeImage');
    const tipeVideo = document.getElementById('tipeVideo');
    const imageZone = document.getElementById('imageUploadZone');
    const videoZone = document.getElementById('videoUrlZone');
    
    function toggleMediaType() {
        if (tipeImage.checked) {
            imageZone.style.display = 'block';
            videoZone.style.display = 'none';
        } else {
            imageZone.style.display = 'none';
            videoZone.style.display = 'block';
        }
    }
    tipeImage?.addEventListener('change', toggleMediaType);
    tipeVideo?.addEventListener('change', toggleMediaType);
    
    // Live preview
    const judulInput = document.querySelector('input[name="judul"]');
    const kategoriSelect = document.querySelector('select[name="kategori"]');
    const tanggalInput = document.querySelector('input[name="tanggal"]');
    const prevJudul = document.getElementById('prevJudul');
    const prevKategori = document.getElementById('prevKategori');
    const prevTanggal = document.getElementById('prevTanggal');
    const prevImg = document.getElementById('prevImg');
    
    judulInput?.addEventListener('input', e => {
        prevJudul.textContent = e.target.value || 'Judul Foto';
    });
    kategoriSelect?.addEventListener('change', e => {
        prevKategori.textContent = e.target.value;
    });
    tanggalInput?.addEventListener('change', e => {
        if (e.target.value) {
            const date = new Date(e.target.value);
            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
            prevTanggal.innerHTML = `<i class="fa-regular fa-calendar"></i> ${date.getDate()} ${months[date.getMonth()]} ${date.getFullYear()}`;
        }
    });
    
    // File upload preview
    const fileInput = document.getElementById('fileInput');
    const previewContainer = document.getElementById('previewContainer');
    const previewImg = document.getElementById('previewImg');
    
    fileInput?.addEventListener('change', e => {
        if (e.target.files.length) {
            const reader = new FileReader();
            reader.onload = ev => {
                previewImg.src = ev.target.result;
                previewContainer.style.display = 'block';
                if (prevImg) prevImg.src = ev.target.result;
            };
            reader.readAsDataURL(e.target.files[0]);
        }
    });
    
    // Video URL auto-thumbnail
    const videoUrlInput = document.getElementById('videoUrlInput');
    let videoDebounce = null;
    videoUrlInput?.addEventListener('input', e => {
        clearTimeout(videoDebounce);
        videoDebounce = setTimeout(() => {
            const url = e.target.value;
            let thumb = '';
            const ytMatch = url.match(/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/);
            const vimeoMatch = url.match(/vimeo\.com\/(\d+)/);
            if (ytMatch) thumb = `https://img.youtube.com/vi/${ytMatch[1]}/maxresdefault.jpg`;
            else if (vimeoMatch) thumb = `https://vumbnail.com/${vimeoMatch[1]}.jpg`;
            if (thumb && prevImg) prevImg.src = thumb;
        }, 500);
    });
    
    // Drag & Drop
    const dropZone = document.getElementById('dropZone');
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
                fileInput.dispatchEvent(new Event('change'));
            }
        });
    }
});
</script>

<?php else: ?>
<!-- ===== FILTER BAR ===== -->
<?php if (!empty($items)): ?>
<div class="galeri-filter-bar">
    <div class="form-group">
        <label><i class="fa-solid fa-search"></i> Cari Galeri</label>
        <input type="text" id="galeriSearch" class="form-control" placeholder="Ketik judul...">
    </div>
    <div class="form-group">
        <label><i class="fa-solid fa-tag"></i> Kategori</label>
        <select id="catFilter" class="form-control">
            <option value="all">Semua Kategori</option>
            <?php foreach ($unique_cats as $cat): ?>
            <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label><i class="fa-solid fa-filter"></i> Tipe</label>
        <select id="tipeFilter" class="form-control">
            <option value="all">Semua Tipe</option>
            <option value="image">Gambar</option>
            <option value="video">Video</option>
        </select>
    </div>
</div>
<?php endif; ?>

<!-- ===== LIST VIEW ===== -->
<?php if (empty($items)): ?>
<div class="galeri-empty">
    <div class="galeri-empty-icon"><i class="fa-solid fa-images"></i></div>
    <h3>Belum Ada Galeri</h3>
    <p>Mulai dokumentasikan momen terbaik keluarga besar PBSI UMMaumere</p>
    <a href="?action=add" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Tambah Galeri Pertama</a>
</div>
<?php else: ?>
<div class="galeri-card-grid" id="galeriCardGrid">
    <?php foreach ($items as $idx => $item): 
        $token = hash_hmac('sha256', $item['id'], $_SESSION['csrf_token']);
        $thumb = getGalleryThumb($item);
        $tipe = $item['tipe'] ?? 'image';
    ?>
    <div class="galeri-card" 
         data-search="<?= strtolower(htmlspecialchars($item['judul'] ?? '')) ?>"
         data-category="<?= htmlspecialchars($item['kategori'] ?? '') ?>"
         data-tipe="<?= htmlspecialchars($tipe) ?>"
         data-full="<?= htmlspecialchars($thumb) ?>"
         data-title="<?= htmlspecialchars($item['judul'] ?? '') ?>"
         data-desc="<?= htmlspecialchars($item['deskripsi'] ?? '') ?>">
        <div class="galeri-card-thumb" onclick="openAdminLightboxFromCard(this.parentElement)">
            <span class="galeri-card-type <?= $tipe ?>">
                <i class="fa-solid <?= $tipe === 'video' ? 'fa-video' : 'fa-image' ?>"></i>
                <?= ucfirst($tipe) ?>
            </span>
            <img src="<?= htmlspecialchars($thumb) ?>" alt="<?= htmlspecialchars($item['judul']) ?>" loading="lazy" onerror="this.src='data:image/svg+xml;utf8,<?= rawurlencode('<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'400\' height=\'300\'><rect width=\'400\' height=\'300\' fill=\'#1a1a2e\'/><text x=\'200\' y=\'150\' font-family=\'Arial\' font-size=\'18\' fill=\'rgba(255,255,255,.4)\' text-anchor=\'middle\' dominant-baseline=\'middle\'>No Preview</text></svg>') ?>'">
            <div class="preview-icon"><i class="fa-solid fa-magnifying-glass-plus"></i></div>
        </div>
        <div class="galeri-card-body">
            <h4 class="galeri-card-title"><?= htmlspecialchars($item['judul']) ?></h4>
            <div class="galeri-card-meta">
                <span><i class="fa-regular fa-calendar"></i> <?= !empty($item['tanggal']) ? date('d M Y', strtotime($item['tanggal'])) : '-' ?></span>
                <span><i class="fa-solid fa-list-ol"></i> #<?= (int)($item['urutan'] ?? 0) ?></span>
            </div>
            <span class="galeri-card-cat"><?= htmlspecialchars($item['kategori'] ?? 'Lainnya') ?></span>
            <?php if (!empty($item['deskripsi'])): ?>
            <p style="color:var(--text-muted); font-size:.78rem; margin-top:.5rem; line-height:1.5; max-height:3em; overflow:hidden;">
                <i><?= htmlspecialchars(substr($item['deskripsi'], 0, 80)) ?><?= strlen($item['deskripsi'] ?? '')>80?'...':'' ?></i>
            </p>
            <?php endif; ?>
            <div class="galeri-card-actions">
                <a href="?edit=<?= $item['id'] ?>&token=<?= $token ?>" class="table-action-btn edit" title="Edit"><i class="fa-solid fa-pen"></i></a>
                <a href="?duplicate=<?= $item['id'] ?>&token=<?= $token ?>" class="table-action-btn" title="Duplikasi" onclick="return confirm('Duplikasi galeri ini?')"><i class="fa-solid fa-copy"></i></a>
                <a href="?delete=<?= $item['id'] ?>&token=<?= $token ?>" class="table-action-btn delete" onclick="return confirm('Yakin ingin menghapus galeri ini?')" title="Hapus"><i class="fa-solid fa-trash"></i></a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div style="text-align:center; margin-top:1rem; padding:.8rem; background:rgba(138,21,56,.05); border-radius:8px; color:var(--text-muted); font-size:.88rem;">
    Menampilkan <strong id="galeriCount" style="color:var(--crimson);"><?= $total_items ?></strong> dari <strong><?= $total_items ?></strong> item galeri
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('galeriSearch');
    const catFilter = document.getElementById('catFilter');
    const tipeFilter = document.getElementById('tipeFilter');
    const countEl = document.getElementById('galeriCount');
    
    function filterGaleri() {
        const term = (searchInput?.value || '').toLowerCase();
        const cat = catFilter?.value || 'all';
        const tipe = tipeFilter?.value || 'all';
        
        const cards = document.querySelectorAll('.galeri-card');
        let visibleCount = 0;
        
        cards.forEach(card => {
            const searchText = card.dataset.search || '';
            const rowCat = card.dataset.category;
            const rowTipe = card.dataset.tipe;
            
            const matchSearch = !term || searchText.includes(term);
            const matchCat = cat === 'all' || rowCat === cat;
            const matchTipe = tipe === 'all' || rowTipe === tipe;
            
            if (matchSearch && matchCat && matchTipe) {
                card.style.display = '';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });
        
        if (countEl) countEl.textContent = visibleCount;
    }
    
    if (searchInput) searchInput.addEventListener('input', filterGaleri);
    if (catFilter) catFilter.addEventListener('change', filterGaleri);
    if (tipeFilter) tipeFilter.addEventListener('change', filterGaleri);
});

// ===== ADMIN LIGHTBOX =====
let lightboxItems = [];
let currentLightboxIndex = 0;

window.openAdminLightbox = function(index) {
    lightboxItems = Array.from(document.querySelectorAll('.preview-grid-item'));
    currentLightboxIndex = index;
    showAdminLightbox();
};

window.openAdminLightboxFromCard = function(card) {
    const allCards = Array.from(document.querySelectorAll('.galeri-card'));
    const visibleCards = allCards.filter(c => c.style.display !== 'none');
    lightboxItems = visibleCards;
    currentLightboxIndex = visibleCards.indexOf(card);
    showAdminLightbox();
};

function showAdminLightbox() {
    const lb = document.getElementById('adminLightbox');
    if (!lb) return;
    updateAdminLightbox();
    lb.classList.add('active');
    document.body.style.overflow = 'hidden';
}

window.closeAdminLightbox = function() {
    const lb = document.getElementById('adminLightbox');
    if (lb) {
        lb.classList.remove('active');
        document.body.style.overflow = '';
    }
};

window.navigateAdminLightbox = function(direction) {
    currentLightboxIndex = (currentLightboxIndex + direction + lightboxItems.length) % lightboxItems.length;
    updateAdminLightbox();
};

function updateAdminLightbox() {
    const item = lightboxItems[currentLightboxIndex];
    if (!item) return;
    
    let imgSrc, title, desc;
    
    if (item.classList.contains('preview-grid-item')) {
        imgSrc = item.querySelector('img').src;
        title = item.querySelector('.pg-title')?.textContent || '';
        desc = '';
    } else {
        imgSrc = item.dataset.full;
        title = item.dataset.title;
        desc = item.dataset.desc;
    }
    
    const lbImg = document.getElementById('adminLightboxImg');
    const lbTitle = document.getElementById('adminLightboxTitle');
    const lbDesc = document.getElementById('adminLightboxDesc');
    const lbCurrent = document.getElementById('adminLightboxCurrent');
    const lbTotal = document.getElementById('adminLightboxTotal');
    
    if (lbImg) lbImg.src = imgSrc;
    if (lbTitle) lbTitle.textContent = title;
    if (lbDesc) lbDesc.textContent = desc || '';
    if (lbCurrent) lbCurrent.textContent = currentLightboxIndex + 1;
    if (lbTotal) lbTotal.textContent = lightboxItems.length;
}

// Keyboard navigation
document.addEventListener('keydown', function(e) {
    const lb = document.getElementById('adminLightbox');
    if (!lb || !lb.classList.contains('active')) return;
    if (e.key === 'Escape') closeAdminLightbox();
    if (e.key === 'ArrowLeft') navigateAdminLightbox(-1);
    if (e.key === 'ArrowRight') navigateAdminLightbox(1);
});

// Touch/swipe
let touchStartX = 0;
document.addEventListener('touchstart', function(e) {
    const lb = document.getElementById('adminLightbox');
    if (!lb || !lb.classList.contains('active')) return;
    touchStartX = e.touches[0].clientX;
}, { passive: true });

document.addEventListener('touchend', function(e) {
    const lb = document.getElementById('adminLightbox');
    if (!lb || !lb.classList.contains('active')) return;
    const diff = touchStartX - e.changedTouches[0].clientX;
    if (Math.abs(diff) > 50) {
        if (diff > 0) navigateAdminLightbox(1);
        else navigateAdminLightbox(-1);
    }
}, { passive: true });
</script>

<!-- ADMIN LIGHTBOX MODAL -->
<div class="admin-lightbox" id="adminLightbox" role="dialog" aria-modal="true" aria-label="Image viewer">
    <div class="admin-lightbox-backdrop" onclick="closeAdminLightbox()"></div>
    <div class="admin-lightbox-content">
        <button class="admin-lightbox-close" onclick="closeAdminLightbox()" aria-label="Tutup">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <button class="admin-lightbox-nav admin-lightbox-prev" onclick="navigateAdminLightbox(-1)" aria-label="Sebelumnya">
            <i class="fa-solid fa-chevron-left"></i>
        </button>
        <div class="admin-lightbox-img-wrap">
            <img src="" alt="" class="admin-lightbox-img" id="adminLightboxImg">
        </div>
        <button class="admin-lightbox-nav admin-lightbox-next" onclick="navigateAdminLightbox(1)" aria-label="Berikutnya">
            <i class="fa-solid fa-chevron-right"></i>
        </button>
        <div class="admin-lightbox-info">
            <h3 class="admin-lightbox-title" id="adminLightboxTitle"></h3>
            <p class="admin-lightbox-desc" id="adminLightboxDesc"></p>
            <div class="admin-lightbox-counter">
                <span id="adminLightboxCurrent">1</span> / <span id="adminLightboxTotal">1</span>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    if ($('#galeriForm').length && $.fn.select2) {
        $('.select2-premium').select2({ dropdownParent: $('#galeriForm') });
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
    
    // Animated category bars
    setTimeout(() => {
        document.querySelectorAll('.cat-fill').forEach(bar => {
            bar.style.width = bar.dataset.target + '%';
        });
    }, 300);
});
</script>

<?php
$page_content = ob_get_clean();
require_once 'includes/admin_template.php';
?>