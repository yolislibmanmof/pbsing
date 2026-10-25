<?php
require_once '../includes/config.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';
require_once 'includes/upload_helper.php';
require_login();

$page_title = 'Kelola Slider / Hero Banner';
$current_page = 'slider';
$table = 'slider_video';

// ===== HANDLE DELETE =====
if (isset($_GET['delete']) && isset($_GET['token']) && hash_equals($_SESSION['csrf_token'], $_GET['token'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("SELECT file, tipe FROM $table WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    
    // Hapus file fisik jika image (bukan URL video)
    if ($row && $row['tipe'] === 'image' && !empty($row['file']) && !filter_var($row['file'], FILTER_VALIDATE_URL) && file_exists('../' . $row['file'])) {
        @unlink('../' . $row['file']);
    }
    
    $pdo->prepare("DELETE FROM $table WHERE id = ?")->execute([$id]);
    $pdo->prepare("INSERT INTO admin_logs (user_id, user_name, action, detail, ip) VALUES (?, ?, 'DELETE_SLIDER', 'Menghapus slide ID $id', ?)")
        ->execute([$_SESSION['admin_id'], $_SESSION['admin_name'], $_SERVER['REMOTE_ADDR']]);
    
    header("Location: kelola_slider.php?msg=deleted");
    exit;
}

// ===== HANDLE DUPLICATE =====
if (isset($_GET['duplicate']) && isset($_GET['token']) && hash_equals($_SESSION['csrf_token'], $_GET['token'])) {
    $id = (int)$_GET['duplicate'];
    $stmt = $pdo->prepare("SELECT * FROM $table WHERE id = ?");
    $stmt->execute([$id]);
    $orig = $stmt->fetch();
    
    if ($orig) {
        $pdo->prepare("INSERT INTO $table (judul, subtitle, tipe, file, video_url, cta_text, cta_url, badge, urutan, aktif) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
            ->execute([
                $orig['judul'] . ' (Copy)',
                $orig['subtitle'],
                $orig['tipe'],
                $orig['file'],
                $orig['video_url'] ?? '',
                $orig['cta_text'],
                $orig['cta_url'],
                $orig['badge'],
                $orig['urutan'] + 1,
                0 // Nonaktif by default
            ]);
        header("Location: kelola_slider.php?msg=duplicated");
        exit;
    }
}

// ===== HANDLE TOGGLE STATUS =====
if (isset($_POST['toggle_status']) && isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $id = (int)$_POST['id'];
    $aktif = (int)$_POST['aktif'];
    $pdo->prepare("UPDATE $table SET aktif = ? WHERE id = ?")->execute([$aktif, $id]);
    header("Location: kelola_slider.php?msg=toggled");
    exit;
}

// ===== HANDLE ADD/EDIT =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['toggle_status']) && isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $judul = sanitize($_POST['judul']);
    $subtitle = sanitize($_POST['subtitle']);
    $tipe = sanitize($_POST['tipe']);
    $cta_text = sanitize($_POST['cta_text']);
    $cta_url = sanitize($_POST['cta_url']);
    $cta_icon = sanitize($_POST['cta_icon']);
    $badge = sanitize($_POST['badge']);
    $badge_style = sanitize($_POST['badge_style'] ?? 'crimson');
    $urutan = (int)$_POST['urutan'];
    $aktif = isset($_POST['aktif']) ? 1 : 0;
    
    $file_path = $data['file'] ?? '';
    $video_url = '';

    if ($tipe === 'image' && !empty($_FILES['file']['name'])) {
        $upload = handle_upload($_FILES['file'], '../assets/uploads/slider/', ['jpg', 'jpeg', 'png', 'webp'], 10485760); // 10MB
        if ($upload['success']) {
            $file_path = $upload['path'];
            // Hapus file lama jika edit
            if ($id > 0) {
                $stmt = $pdo->prepare("SELECT file FROM $table WHERE id = ?");
                $stmt->execute([$id]);
                $old = $stmt->fetch();
                if ($old && !empty($old['file']) && !filter_var($old['file'], FILTER_VALIDATE_URL) && file_exists('../' . $old['file'])) {
                    @unlink('../' . $old['file']);
                }
            }
        } else {
            $error = $upload['error'];
        }
    } elseif ($tipe === 'video') {
        $video_url = sanitize($_POST['video_url']);
        // Parse YouTube ID untuk thumbnail preview
        if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $video_url, $matches)) {
            $file_path = 'https://img.youtube.com/vi/' . $matches[1] . '/maxresdefault.jpg';
        } elseif (preg_match('/vimeo\.com\/(\d+)/', $video_url, $matches)) {
            $file_path = 'https://vumbnail.com/' . $matches[1] . '.jpg';
        }
    }

    if (!isset($error)) {
        $fields = "judul=:judul, subtitle=:subtitle, tipe=:tipe, file=:file, video_url=:video_url, cta_text=:cta_text, cta_url=:cta_url, badge=:badge, urutan=:urutan, aktif=:aktif";
        $params = [
            ':judul'=>$judul, ':subtitle'=>$subtitle, ':tipe'=>$tipe, 
            ':file'=>$file_path, ':video_url'=>$video_url,
            ':cta_text'=>$cta_text, ':cta_url'=>$cta_url, 
            ':badge'=>$badge, ':urutan'=>$urutan, ':aktif'=>$aktif
        ];
        
        if ($id > 0) {
            $pdo->prepare("UPDATE $table SET $fields WHERE id=:id")->execute(array_merge($params, [':id'=>$id]));
            $action = 'UPDATE_SLIDER';
            $detail = "Memperbarui slide: $judul";
        } else {
            $pdo->prepare("INSERT INTO $table SET $fields")->execute($params);
            $action = 'ADD_SLIDER';
            $detail = "Menambah slide baru: $judul";
        }
        
        $pdo->prepare("INSERT INTO admin_logs (user_id, user_name, action, detail, ip) VALUES (?, ?, ?, ?, ?)")
            ->execute([$_SESSION['admin_id'], $_SESSION['admin_name'], $action, $detail, $_SERVER['REMOTE_ADDR']]);
        
        header("Location: kelola_slider.php?msg=saved");
        exit;
    }
}

// ===== FETCH DATA =====
$items = $pdo->query("SELECT * FROM $table ORDER BY urutan ASC, id DESC")->fetchAll();

// Statistics
$total_slides = count($items);
$active_slides = count(array_filter($items, fn($i) => $i['aktif'] == 1));
$image_slides = count(array_filter($items, fn($i) => $i['tipe'] === 'image'));
$video_slides = count(array_filter($items, fn($i) => $i['tipe'] === 'video'));

// Form data
$data = [
    'id'=>0, 'judul'=>'', 'subtitle'=>'', 'tipe'=>'image', 
    'file'=>'', 'video_url'=>'', 'cta_text'=>'Jelajahi Program', 
    'cta_url'=>'#', 'cta_icon'=>'fa-arrow-right',
    'badge'=>'', 'badge_style'=>'crimson', 'urutan'=>1, 'aktif'=>1
];

if (isset($_GET['edit']) && isset($_GET['token']) && hash_equals($_SESSION['csrf_token'], $_GET['token'])) {
    $stmt = $pdo->prepare("SELECT * FROM $table WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $fetched = $stmt->fetch();
    if ($fetched) $data = array_merge($data, $fetched);
}

// Helper: get thumbnail preview
function getSlidePreview($item) {
    if ($item['tipe'] === 'video') {
        $url = $item['video_url'] ?? $item['file'];
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
    // Fallback SVG
    return "data:image/svg+xml;utf8," . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="800" height="450"><defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#8A1538"/><stop offset="1" stop-color="#1a1a2e"/></linearGradient></defs><rect width="800" height="450" fill="url(#g)"/><text x="400" y="225" font-family="Arial" font-size="32" fill="rgba(255,255,255,.5)" text-anchor="middle" dominant-baseline="middle">No Preview</text></svg>');
}

ob_start();
?>

<style>
/* ===== STATS CARDS ===== */
.slider-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}
.slider-stat-card {
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
.slider-stat-card:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-md);
}
.slider-stat-icon {
    width: 52px;
    height: 52px;
    border-radius: var(--radius-md);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
    flex-shrink: 0;
}
.slider-stat-card.accent-crimson .slider-stat-icon { background: rgba(138,21,56,0.15); color: var(--crimson); }
.slider-stat-card.accent-sky .slider-stat-icon { background: rgba(135,206,235,0.15); color: var(--sky-blue); }
.slider-stat-card.accent-emerald .slider-stat-icon { background: rgba(46,139,87,0.15); color: var(--success); }
.slider-stat-card.accent-warning .slider-stat-icon { background: rgba(243,156,18,0.15); color: var(--warning); }
.slider-stat-value { font-size: 1.8rem; font-weight: 900; color: #fff; line-height: 1; }
.slider-stat-label { color: var(--text-muted); font-size: .82rem; margin-top: .2rem; }

/* ===== SLIDER CARDS GRID ===== */
.slider-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}
.slider-card {
    background: var(--panel-bg);
    border: 1px solid var(--border-dark);
    border-radius: var(--radius-lg);
    overflow: hidden;
    transition: all .3s ease;
    position: relative;
}
.slider-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-lg);
    border-color: var(--crimson);
}
.slider-card-thumb {
    position: relative;
    aspect-ratio: 16/9;
    overflow: hidden;
    background: var(--bg-darker);
}
.slider-card-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform .5s ease;
}
.slider-card:hover .slider-card-thumb img { transform: scale(1.05); }
.slider-card-thumb::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(180deg, transparent 40%, rgba(0,0,0,0.85) 100%);
}
.slider-card-type-badge {
    position: absolute;
    top: 12px;
    left: 12px;
    padding: .3rem .7rem;
    border-radius: 50px;
    font-size: .72rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .5px;
    z-index: 2;
    backdrop-filter: blur(10px);
}
.slider-card-type-badge.image { background: rgba(135,206,235,0.9); color: #000; }
.slider-card-type-badge.video { background: rgba(231,76,60,0.9); color: #fff; }
.slider-card-type-badge.video i { margin-right: .3rem; }

.slider-card-overlay-info {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 1.2rem;
    z-index: 2;
    color: #fff;
}
.slider-card-badge {
    display: inline-block;
    padding: .2rem .6rem;
    border-radius: 50px;
    font-size: .7rem;
    font-weight: 700;
    margin-bottom: .5rem;
    background: rgba(138,21,56,0.9);
    backdrop-filter: blur(4px);
}
.slider-card-badge.sky { background: rgba(135,206,235,0.9); color: #000; }
.slider-card-badge.emerald { background: rgba(46,139,87,0.9); }
.slider-card-badge.warning { background: rgba(243,156,18,0.9); color: #000; }

.slider-card-title {
    font-size: 1.05rem;
    font-weight: 800;
    line-height: 1.3;
    margin-bottom: .3rem;
    color: #fff;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.slider-card-subtitle {
    font-size: .8rem;
    color: rgba(255,255,255,0.7);
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.slider-card-body {
    padding: 1.2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-top: 1px solid var(--border-dark);
}
.slider-card-meta {
    display: flex;
    gap: 1rem;
    font-size: .78rem;
    color: var(--text-muted);
}
.slider-card-meta span { display: flex; align-items: center; gap: .3rem; }
.slider-card-meta i { color: var(--sky-blue); }

.slider-card-actions {
    display: flex;
    gap: .3rem;
}
.slider-card-actions .table-action-btn {
    width: 32px;
    height: 32px;
    padding: 0;
}

/* ===== MODERN TOGGLE SWITCH ===== */
.toggle-switch {
    position: relative;
    display: inline-block;
    width: 42px;
    height: 22px;
    flex-shrink: 0;
}
.toggle-switch input { opacity: 0; width: 0; height: 0; }
.toggle-slider {
    position: absolute;
    cursor: pointer;
    inset: 0;
    background: var(--bg-darker);
    border: 1px solid var(--border-dark);
    transition: .3s;
    border-radius: 50px;
}
.toggle-slider:before {
    position: absolute;
    content: "";
    height: 16px;
    width: 16px;
    left: 2px;
    bottom: 2px;
    background: var(--text-muted);
    transition: .3s;
    border-radius: 50%;
}
.toggle-switch input:checked + .toggle-slider {
    background: var(--success);
    border-color: var(--success);
}
.toggle-switch input:checked + .toggle-slider:before {
    transform: translateX(20px);
    background: #fff;
}

/* ===== LIVE PREVIEW HERO (Meniru Public Slider) ===== */
.hero-preview {
    position: relative;
    aspect-ratio: 16/9;
    border-radius: var(--radius-lg);
    overflow: hidden;
    background: #000;
    box-shadow: 0 20px 60px rgba(0,0,0,0.5);
    border: 1px solid var(--border-dark);
}
.hero-preview-bg {
    position: absolute;
    inset: 0;
    background-size: cover;
    background-position: center;
    transition: background-image .3s ease;
}
.hero-preview-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(90deg, rgba(0,0,0,0.85) 0%, rgba(0,0,0,0.5) 50%, rgba(0,0,0,0.2) 100%);
}
.hero-preview-content {
    position: relative;
    z-index: 2;
    padding: 2.5rem;
    color: #fff;
    display: flex;
    flex-direction: column;
    justify-content: center;
    height: 100%;
    max-width: 70%;
}
.hero-preview-badge {
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    padding: .4rem .9rem;
    border-radius: 50px;
    font-size: .72rem;
    font-weight: 800;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    margin-bottom: 1rem;
    width: fit-content;
    background: var(--crimson);
    backdrop-filter: blur(4px);
}
.hero-preview-badge.sky { background: var(--sky-blue); color: #000; }
.hero-preview-badge.emerald { background: var(--success); }
.hero-preview-badge.warning { background: var(--warning); color: #000; }
.hero-preview-title {
    font-size: clamp(1.2rem, 2.5vw, 2rem);
    font-weight: 900;
    line-height: 1.2;
    margin-bottom: .8rem;
    text-shadow: 0 2px 20px rgba(0,0,0,0.5);
}
.hero-preview-subtitle {
    font-size: clamp(.85rem, 1.2vw, 1rem);
    color: rgba(255,255,255,0.85);
    margin-bottom: 1.5rem;
    line-height: 1.5;
    max-width: 600px;
}
.hero-preview-cta {
    display: inline-flex;
    align-items: center;
    gap: .6rem;
    background: var(--crimson);
    color: #fff;
    padding: .8rem 1.8rem;
    border-radius: 50px;
    font-weight: 700;
    font-size: .9rem;
    text-decoration: none;
    width: fit-content;
    transition: all .3s ease;
}
.hero-preview-cta:hover { background: var(--crimson-light); transform: translateX(4px); }
.hero-preview-cta i { transition: transform .3s; }
.hero-preview-cta:hover i { transform: translateX(3px); }

.hero-preview-video-badge {
    position: absolute;
    top: 1rem;
    right: 1rem;
    background: rgba(231,76,60,0.9);
    color: #fff;
    padding: .4rem .9rem;
    border-radius: 50px;
    font-size: .75rem;
    font-weight: 700;
    backdrop-filter: blur(10px);
    display: flex;
    align-items: center;
    gap: .4rem;
    z-index: 3;
}

/* ===== BADGE STYLE PICKER ===== */
.badge-style-picker {
    display: flex;
    gap: .6rem;
    flex-wrap: wrap;
}
.badge-style-option {
    position: relative;
    cursor: pointer;
    padding: .4rem .9rem;
    border-radius: 50px;
    font-size: .78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    border: 2px solid transparent;
    transition: all .2s ease;
    display: inline-flex;
    align-items: center;
    gap: .4rem;
}
.badge-style-option input { display: none; }
.badge-style-option.crimson { background: var(--crimson); color: #fff; }
.badge-style-option.sky { background: var(--sky-blue); color: #000; }
.badge-style-option.emerald { background: var(--success); color: #fff; }
.badge-style-option.warning { background: var(--warning); color: #000; }
.badge-style-option:has(input:checked) {
    border-color: #fff;
    transform: scale(1.08);
    box-shadow: 0 0 0 3px rgba(255,255,255,0.2);
}

/* ===== CTA ICON PICKER ===== */
.cta-icon-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: .5rem;
}
.cta-icon-option {
    position: relative;
    cursor: pointer;
}
.cta-icon-option input { display: none; }
.cta-icon-option .icon-box {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: .3rem;
    padding: .7rem .4rem;
    border: 2px solid var(--border-dark);
    border-radius: var(--radius-md);
    background: var(--bg-darker);
    transition: all .2s ease;
    color: var(--text-muted);
    font-size: .7rem;
}
.cta-icon-option .icon-box i { font-size: 1.2rem; }
.cta-icon-option:has(input:checked) .icon-box {
    border-color: var(--crimson);
    background: rgba(138,21,56,0.1);
    color: #fff;
}

/* ===== UPLOAD ZONE ===== */
.slider-upload-zone {
    border: 2px dashed var(--border-light);
    border-radius: var(--radius-md);
    padding: 2.5rem 1.5rem;
    text-align: center;
    cursor: pointer;
    transition: all .3s ease;
    background: var(--bg-darker);
    position: relative;
}
.slider-upload-zone:hover, .slider-upload-zone.dragover {
    border-color: var(--crimson);
    background: rgba(138,21,56,0.08);
}
.slider-upload-zone i.upload-icon {
    font-size: 3rem;
    color: var(--text-muted);
    margin-bottom: .8rem;
    display: block;
    transition: all .3s;
}
.slider-upload-zone:hover i.upload-icon { color: var(--crimson); transform: translateY(-4px); }
.slider-upload-zone input[type="file"] { position: absolute; inset: 0; opacity: 0; cursor: pointer; }

/* ===== RECOMMENDED SPECS ===== */
.specs-info-box {
    background: linear-gradient(135deg, rgba(135,206,235,0.08), rgba(138,21,56,0.08));
    border: 1px solid var(--border-dark);
    border-radius: var(--radius-md);
    padding: 1.2rem;
    margin-top: 1rem;
}
.specs-info-box h4 {
    color: var(--sky-blue);
    font-size: .9rem;
    margin-bottom: .8rem;
    display: flex;
    align-items: center;
    gap: .5rem;
}
.specs-info-box ul {
    list-style: none;
    padding: 0;
    margin: 0;
    font-size: .82rem;
    color: var(--text-muted);
    line-height: 1.8;
}
.specs-info-box ul li {
    display: flex;
    align-items: flex-start;
    gap: .5rem;
}
.specs-info-box ul li i { color: var(--success); margin-top: .25rem; }

/* ===== EMPTY STATE ===== */
.slider-empty {
    text-align: center;
    padding: 4rem 2rem;
    background: var(--panel-bg);
    border: 2px dashed var(--border-dark);
    border-radius: var(--radius-lg);
}
.slider-empty-icon {
    width: 100px;
    height: 100px;
    margin: 0 auto 1.5rem;
    background: linear-gradient(135deg, var(--crimson), var(--crimson-light));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 3rem;
    color: #fff;
    box-shadow: 0 10px 30px rgba(138,21,56,0.3);
}
.slider-empty h3 { color: #fff; margin-bottom: .5rem; font-size: 1.3rem; }
.slider-empty p { color: var(--text-muted); margin-bottom: 1.5rem; max-width: 500px; margin-left: auto; margin-right: auto; }

/* ===== FORM LAYOUT ===== */
.slider-form-grid {
    display: grid;
    grid-template-columns: 1fr 1.2fr;
    gap: 2rem;
}
@media (max-width: 992px) {
    .slider-form-grid { grid-template-columns: 1fr; }
    .cta-icon-grid { grid-template-columns: repeat(4, 1fr); }
}

.form-section-title {
    font-size: .82rem;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    color: var(--sky-blue);
    margin: 1.5rem 0 1rem;
    padding-bottom: .5rem;
    border-bottom: 1px solid var(--border-dark);
    display: flex;
    align-items: center;
    gap: .5rem;
}
.form-section-title:first-child { margin-top: 0; }

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
</style>

<?php if (isset($_GET['msg'])): 
    $msgs = [
        'saved' => ['success', 'Slide berhasil disimpan!'],
        'deleted' => ['success', 'Slide berhasil dihapus!'],
        'duplicated' => ['success', 'Slide berhasil diduplikasi!'],
        'toggled' => ['success', 'Status slide diperbarui!']
    ];
    if (isset($msgs[$_GET['msg']])): ?>
    <script>Swal.fire({icon:'<?=$msgs[$_GET['msg']][0]?>', title:'Berhasil!', text:'<?=$msgs[$_GET['msg']][1]?>', timer:2500, showConfirmButton:false});</script>
<?php endif; endif; ?>

<?php if (isset($error)): ?>
<script>Swal.fire({icon:'error', title:'Upload Gagal', text:'<?= addslashes($error) ?>'});</script>
<?php endif; ?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;">
    <div>
        <h2 class="section-title"><i class="fa-solid fa-images"></i> <?= isset($_GET['edit']) ? 'Edit' : 'Kelola' ?> Slider Hero</h2>
        <p style="color:var(--text-muted); font-size:.88rem; margin-top:.3rem;">
            <i class="fa-solid fa-circle-info"></i> Kelola banner utama yang tampil di halaman beranda website
        </p>
    </div>
    <div style="display:flex; gap:.8rem;">
        <?php if (isset($_GET['edit'])): ?>
        <a href="kelola_slider.php" class="btn btn-secondary"><i class="fa-solid fa-xmark"></i> Batal</a>
        <?php else: ?>
        <a href="?action=add" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Tambah Slide</a>
        <?php endif; ?>
    </div>
</div>

<!-- ===== STATS DASHBOARD ===== -->
<div class="slider-stats-grid">
    <div class="slider-stat-card accent-crimson">
        <div class="slider-stat-icon"><i class="fa-solid fa-images"></i></div>
        <div>
            <div class="slider-stat-value"><?= $total_slides ?></div>
            <div class="slider-stat-label">Total Slide</div>
        </div>
    </div>
    <div class="slider-stat-card accent-emerald">
        <div class="slider-stat-icon"><i class="fa-solid fa-check-circle"></i></div>
        <div>
            <div class="slider-stat-value"><?= $active_slides ?></div>
            <div class="slider-stat-label">Slide Aktif</div>
        </div>
    </div>
    <div class="slider-stat-card accent-sky">
        <div class="slider-stat-icon"><i class="fa-solid fa-image"></i></div>
        <div>
            <div class="slider-stat-value"><?= $image_slides ?></div>
            <div class="slider-stat-label">Tipe Gambar</div>
        </div>
    </div>
    <div class="slider-stat-card accent-warning">
        <div class="slider-stat-icon"><i class="fa-solid fa-video"></i></div>
        <div>
            <div class="slider-stat-value"><?= $video_slides ?></div>
            <div class="slider-stat-label">Tipe Video</div>
        </div>
    </div>
</div>

<?php if (isset($_GET['action']) && $_GET['action'] === 'add' || isset($_GET['edit'])): ?>
<!-- ===== FORM ADD/EDIT WITH LIVE HERO PREVIEW ===== -->
<div class="panel">
    <div class="panel-body">
        <form method="POST" enctype="multipart/form-data" id="sliderForm">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="id" value="<?= $data['id'] ?>">
            
            <div class="slider-form-grid">
                <!-- FORM FIELDS -->
                <div>
                    <div class="form-section-title"><i class="fa-solid fa-heading"></i> Konten Slide</div>
                    
                    <div class="form-group">
                        <label>Judul Utama <span class="required">*</span></label>
                        <div class="input-icon-wrap">
                            <i class="fa-solid fa-heading"></i>
                            <input type="text" name="judul" id="f_judul" class="form-control" value="<?= htmlspecialchars($data['judul']) ?>" required placeholder="Connecting Worlds Through Words">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Subtitle / Deskripsi</label>
                        <textarea name="subtitle" id="f_subtitle" rows="2" class="form-control" placeholder="Program Studi Bahasa dan Sastra Inggris UMMaumere"><?= htmlspecialchars($data['subtitle']) ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Teks Badge (Opsional)</label>
                        <div class="input-icon-wrap">
                            <i class="fa-solid fa-tag"></i>
                            <input type="text" name="badge" id="f_badge" class="form-control" value="<?= htmlspecialchars($data['badge']) ?>" placeholder="TERAKREDITASI BAIK SEKALI">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Style Badge</label>
                        <div class="badge-style-picker">
                            <?php foreach (['crimson'=>'Crimson', 'sky'=>'Sky Blue', 'emerald'=>'Emerald', 'warning'=>'Warning'] as $key => $label): ?>
                            <label class="badge-style-option <?= $key ?>">
                                <input type="radio" name="badge_style" value="<?= $key ?>" <?= ($data['badge_style'] ?? 'crimson') === $key ? 'checked' : '' ?>>
                                <i class="fa-solid fa-palette"></i> <?= $label ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="form-section-title"><i class="fa-solid fa-photo-film"></i> Media</div>
                    
                    <div class="form-group">
                        <label>Tipe Media</label>
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:.8rem;">
                            <label style="display:flex; align-items:center; gap:.8rem; padding:1rem; background:var(--bg-darker); border:2px solid var(--border-dark); border-radius:var(--radius-md); cursor:pointer; transition:all .2s;">
                                <input type="radio" name="tipe" value="image" <?= $data['tipe']==='image'?'checked':'' ?> id="tipeImage" style="width:18px; height:18px;">
                                <i class="fa-solid fa-image" style="color:var(--sky-blue); font-size:1.3rem;"></i>
                                <div>
                                    <div style="font-weight:700; color:#fff;">Gambar</div>
                                    <small style="color:var(--text-muted);">Upload foto</small>
                                </div>
                            </label>
                            <label style="display:flex; align-items:center; gap:.8rem; padding:1rem; background:var(--bg-darker); border:2px solid var(--border-dark); border-radius:var(--radius-md); cursor:pointer; transition:all .2s;">
                                <input type="radio" name="tipe" value="video" <?= $data['tipe']==='video'?'checked':'' ?> id="tipeVideo" style="width:18px; height:18px;">
                                <i class="fa-solid fa-video" style="color:var(--crimson); font-size:1.3rem;"></i>
                                <div>
                                    <div style="font-weight:700; color:#fff;">Video</div>
                                    <small style="color:var(--text-muted);">YouTube/Vimeo</small>
                                </div>
                            </label>
                        </div>
                    </div>
                    
                    <div id="imageUploadZone" style="<?= $data['tipe']==='video'?'display:none;':'' ?>">
                        <div class="form-group">
                            <label>Upload Gambar</label>
                            <div class="slider-upload-zone" id="dropZone">
                                <i class="fa-solid fa-cloud-arrow-up upload-icon"></i>
                                <p style="margin:0; font-weight:700; color:#fff;">Klik atau Drag & Drop gambar</p>
                                <small style="color:var(--text-muted);">JPG, PNG, WebP (Maks 10MB)</small>
                                <input type="file" name="file" accept="image/*" id="fileInput">
                            </div>
                            <?php if (!empty($data['file']) && $data['tipe']==='image'): ?>
                            <div style="margin-top:1rem; padding:1rem; background:var(--bg-darker); border-radius:var(--radius-md); border:1px solid var(--border-dark);">
                                <small style="color:var(--text-muted); display:block; margin-bottom:.5rem;">Gambar saat ini:</small>
                                <img src="<?= !filter_var($data['file'], FILTER_VALIDATE_URL) ? '../'.htmlspecialchars($data['file']) : htmlspecialchars($data['file']) ?>" style="width:100%; max-height:150px; object-fit:cover; border-radius:var(--radius-sm);">
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div id="videoUrlZone" style="<?= $data['tipe']==='image'?'display:none;':'' ?>">
                        <div class="form-group">
                            <label>URL Video (YouTube/Vimeo)</label>
                            <div class="input-icon-wrap">
                                <i class="fa-brands fa-youtube"></i>
                                <input type="url" name="video_url" id="f_video_url" class="form-control" value="<?= htmlspecialchars($data['video_url'] ?? '') ?>" placeholder="https://www.youtube.com/watch?v=...">
                            </div>
                            <small style="color:var(--text-muted);"><i class="fa-solid fa-info-circle"></i> Thumbnail otomatis diambil dari YouTube/Vimeo</small>
                        </div>
                    </div>
                    
                    <div class="specs-info-box">
                        <h4><i class="fa-solid fa-lightbulb"></i> Spesifikasi Ideal</h4>
                        <ul>
                            <li><i class="fa-solid fa-check"></i> <span><strong>Ukuran:</strong> 1920x1080px (rasio 16:9)</span></li>
                            <li><i class="fa-solid fa-check"></i> <span><strong>Format:</strong> JPG atau WebP (lebih ringan)</span></li>
                            <li><i class="fa-solid fa-check"></i> <span><strong>Ukuran file:</strong> Maksimal 500KB untuk performa optimal</span></li>
                            <li><i class="fa-solid fa-check"></i> <span><strong>Komposisi:</strong> Subjek di sisi kanan agar teks terbaca jelas</span></li>
                        </ul>
                    </div>

                    <div class="form-section-title"><i class="fa-solid fa-mouse-pointer"></i> Call-to-Action Button</div>
                    
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem;">
                        <div class="form-group">
                            <label>Teks Tombol</label>
                            <input type="text" name="cta_text" id="f_cta_text" class="form-control" value="<?= htmlspecialchars($data['cta_text']) ?>" placeholder="Jelajahi Program">
                        </div>
                        <div class="form-group">
                            <label>URL Tujuan</label>
                            <input type="text" name="cta_url" id="f_cta_url" class="form-control" value="<?= htmlspecialchars($data['cta_url']) ?>" placeholder="berita.php atau #">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Icon Tombol</label>
                        <div class="cta-icon-grid">
                            <?php 
                            $icons = [
                                'fa-arrow-right' => 'Arrow',
                                'fa-play' => 'Play',
                                'fa-book' => 'Book',
                                'fa-graduation-cap' => 'Grad',
                                'fa-user-plus' => 'Register',
                                'fa-download' => 'Download',
                                'fa-envelope' => 'Email',
                                'fa-globe' => 'Globe'
                            ];
                            foreach ($icons as $icon => $label): 
                            ?>
                            <label class="cta-icon-option">
                                <input type="radio" name="cta_icon" value="<?= $icon ?>" <?= ($data['cta_icon'] ?? 'fa-arrow-right') === $icon ? 'checked' : '' ?>>
                                <div class="icon-box">
                                    <i class="fa-solid <?= $icon ?>"></i>
                                    <span><?= $label ?></span>
                                </div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem; margin-top:1rem;">
                        <div class="form-group">
                            <label>Urutan Tampil</label>
                            <input type="number" name="urutan" class="form-control" value="<?= $data['urutan'] ?>" min="1">
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <label class="checkbox-wrap" style="padding:1rem; background:var(--bg-darker); border-radius:var(--radius-md); border:1px solid var(--border-dark); margin-top:.2rem;">
                                <input type="checkbox" name="aktif" value="1" <?= $data['aktif']==1?'checked':'' ?> style="width:18px; height:18px;">
                                <span style="font-weight:600;">Aktifkan Slide</span>
                            </label>
                        </div>
                    </div>

                    <div style="margin-top:2rem; display:flex; gap:1rem; padding-top:1.5rem; border-top:1px solid var(--border-dark);">
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> <?= $data['id']>0?'Perbarui':'Simpan' ?> Slide</button>
                        <a href="kelola_slider.php" class="btn btn-secondary"><i class="fa-solid fa-xmark"></i> Batal</a>
                    </div>
                </div>

                <!-- LIVE HERO PREVIEW -->
                <div style="position:sticky; top:20px; align-self:start;">
                    <div class="form-section-title" style="margin-top:0;">
                        <i class="fa-solid fa-eye"></i> Live Preview (Tampilan di Website)
                    </div>
                    <div class="hero-preview" id="heroPreview">
                        <div class="hero-preview-bg" id="prevBg" style="background-image:url('<?= !empty($data['file']) ? (filter_var($data['file'], FILTER_VALIDATE_URL) ? htmlspecialchars($data['file']) : '../'.htmlspecialchars($data['file'])) : "data:image/svg+xml;utf8," . rawurlencode('<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'800\' height=\'450\'><defs><linearGradient id=\'g\' x1=\'0\' y1=\'0\' x2=\'1\' y2=\'1\'><stop offset=\'0\' stop-color=\'#8A1538\'/><stop offset=\'1\' stop-color=\'#1a1a2e\'/></linearGradient></defs><rect width=\'800\' height=\'450\' fill=\'url(#g)\'/><text x=\'400\' y=\'225\' font-family=\'Arial\' font-size=\'24\' fill=\'rgba(255,255,255,.5)\' text-anchor=\'middle\' dominant-baseline=\'middle\'>Preview Area</text></svg>') ?>');"></div>
                        <div class="hero-preview-overlay"></div>
                        <div class="hero-preview-video-badge" id="prevVideoBadge" style="display:none;">
                            <i class="fa-solid fa-video"></i> <span>VIDEO</span>
                        </div>
                        <div class="hero-preview-content">
                            <div class="hero-preview-badge" id="prevBadge" style="display:none;">
                                <i class="fa-solid fa-star"></i> <span id="prevBadgeText"></span>
                            </div>
                            <h1 class="hero-preview-title" id="prevTitle"><?= htmlspecialchars($data['judul'] ?: 'Judul Utama Slide') ?></h1>
                            <p class="hero-preview-subtitle" id="prevSubtitle"><?= htmlspecialchars($data['subtitle'] ?: 'Subtitle deskriptif akan muncul di sini') ?></p>
                            <a href="#" class="hero-preview-cta" id="prevCta">
                                <span id="prevCtaText"><?= htmlspecialchars($data['cta_text'] ?: 'Jelajahi Program') ?></span>
                                <i class="fa-solid <?= htmlspecialchars($data['cta_icon'] ?? 'fa-arrow-right') ?>" id="prevCtaIcon"></i>
                            </a>
                        </div>
                    </div>
                    <div style="margin-top:1rem; padding:1rem; background:var(--bg-darker); border-radius:var(--radius-md); border:1px solid var(--border-dark); font-size:.82rem; color:var(--text-muted);">
                        <i class="fa-solid fa-circle-info" style="color:var(--sky-blue);"></i> 
                        <strong style="color:#fff;">Tip:</strong> Preview di atas menampilkan tampilan slide persis seperti di halaman beranda. Slider akan berganti otomatis setiap 6 detik.
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tipe media toggle
    const tipeImage = document.getElementById('tipeImage');
    const tipeVideo = document.getElementById('tipeVideo');
    const imageZone = document.getElementById('imageUploadZone');
    const videoZone = document.getElementById('videoUrlZone');
    const videoBadge = document.getElementById('prevVideoBadge');
    
    function toggleMediaType() {
        if (tipeImage.checked) {
            imageZone.style.display = 'block';
            videoZone.style.display = 'none';
            videoBadge.style.display = 'none';
        } else {
            imageZone.style.display = 'none';
            videoZone.style.display = 'block';
            videoBadge.style.display = 'flex';
        }
    }
    tipeImage.addEventListener('change', toggleMediaType);
    tipeVideo.addEventListener('change', toggleMediaType);
    toggleMediaType();

    // Live preview binding
    const fields = {
        judul: document.getElementById('f_judul'),
        subtitle: document.getElementById('f_subtitle'),
        badge: document.getElementById('f_badge'),
        cta_text: document.getElementById('f_cta_text'),
        cta_url: document.getElementById('f_cta_url'),
        video_url: document.getElementById('f_video_url'),
        file: document.getElementById('fileInput')
    };
    
    const prevTitle = document.getElementById('prevTitle');
    const prevSubtitle = document.getElementById('prevSubtitle');
    const prevBadge = document.getElementById('prevBadge');
    const prevBadgeText = document.getElementById('prevBadgeText');
    const prevCtaText = document.getElementById('prevCtaText');
    const prevCtaIcon = document.getElementById('prevCtaIcon');
    const prevBg = document.getElementById('prevBg');
    
    fields.judul?.addEventListener('input', e => prevTitle.textContent = e.target.value || 'Judul Utama Slide');
    fields.subtitle?.addEventListener('input', e => prevSubtitle.textContent = e.target.value || 'Subtitle deskriptif akan muncul di sini');
    fields.badge?.addEventListener('input', e => {
        if (e.target.value) {
            prevBadge.style.display = 'inline-flex';
            prevBadgeText.textContent = e.target.value;
        } else {
            prevBadge.style.display = 'none';
        }
    });
    fields.cta_text?.addEventListener('input', e => prevCtaText.textContent = e.target.value || 'Jelajahi Program');
    
    // CTA Icon radio
    document.querySelectorAll('input[name="cta_icon"]').forEach(radio => {
        radio.addEventListener('change', e => {
            prevCtaIcon.className = 'fa-solid ' + e.target.value;
        });
    });
    
    // Badge style radio
    document.querySelectorAll('input[name="badge_style"]').forEach(radio => {
        radio.addEventListener('change', e => {
            prevBadge.className = 'hero-preview-badge ' + e.target.value;
        });
    });
    // Trigger initial
    const initialBadgeStyle = document.querySelector('input[name="badge_style"]:checked');
    if (initialBadgeStyle) prevBadge.classList.add(initialBadgeStyle.value);
    
    // Initial badge visibility
    if (fields.badge?.value) {
        prevBadge.style.display = 'inline-flex';
        prevBadgeText.textContent = fields.badge.value;
    }
    
    // File upload preview
    fields.file?.addEventListener('change', e => {
        if (e.target.files.length) {
            const reader = new FileReader();
            reader.onload = ev => prevBg.style.backgroundImage = `url('${ev.target.result}')`;
            reader.readAsDataURL(e.target.files[0]);
        }
    });
    
    // Video URL -> thumbnail auto
    let videoDebounce = null;
    fields.video_url?.addEventListener('input', e => {
        clearTimeout(videoDebounce);
        videoDebounce = setTimeout(() => {
            const url = e.target.value;
            let thumb = '';
            const ytMatch = url.match(/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/);
            const vimeoMatch = url.match(/vimeo\.com\/(\d+)/);
            
            if (ytMatch) thumb = `https://img.youtube.com/vi/${ytMatch[1]}/maxresdefault.jpg`;
            else if (vimeoMatch) thumb = `https://vumbnail.com/${vimeoMatch[1]}.jpg`;
            
            if (thumb) prevBg.style.backgroundImage = `url('${thumb}')`;
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
                fields.file.files = e.dataTransfer.files;
                fields.file.dispatchEvent(new Event('change'));
            }
        });
    }
});
</script>

<?php else: ?>
<!-- ===== LIST VIEW (CARD GRID) ===== -->
<?php if (empty($items)): ?>
<div class="slider-empty">
    <div class="slider-empty-icon"><i class="fa-solid fa-images"></i></div>
    <h3>Belum Ada Slide</h3>
    <p>Buat slide pertama Anda untuk menampilkan banner yang memukau di halaman beranda website</p>
    <a href="?action=add" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Buat Slide Pertama</a>
</div>
<?php else: ?>
<div class="slider-grid">
    <?php foreach ($items as $item): 
        $token = hash_hmac('sha256', $item['id'], $_SESSION['csrf_token']);
        $preview = getSlidePreview($item);
        $badgeStyle = $item['badge_style'] ?? 'crimson';
    ?>
    <div class="slider-card">
        <div class="slider-card-thumb">
            <img src="<?= htmlspecialchars($preview) ?>" alt="<?= htmlspecialchars($item['judul']) ?>" loading="lazy" onerror="this.src='data:image/svg+xml;utf8,<?= rawurlencode('<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'800\' height=\'450\'><rect width=\'800\' height=\'450\' fill=\'#141414\'/><text x=\'400\' y=\'225\' font-family=\'Arial\' font-size=\'20\' fill=\'#666\' text-anchor=\'middle\' dominant-baseline=\'middle\'>No Preview</text></svg>') ?>'">
            <span class="slider-card-type-badge <?= $item['tipe'] ?>">
                <?php if ($item['tipe'] === 'video'): ?><i class="fa-solid fa-video"></i><?php else: ?><i class="fa-solid fa-image"></i><?php endif; ?>
                <?= ucfirst($item['tipe']) ?>
            </span>
            <div class="slider-card-overlay-info">
                <?php if (!empty($item['badge'])): ?>
                <span class="slider-card-badge <?= $badgeStyle ?>"><?= htmlspecialchars($item['badge']) ?></span>
                <?php endif; ?>
                <div class="slider-card-title"><?= htmlspecialchars($item['judul']) ?></div>
                <div class="slider-card-subtitle"><?= htmlspecialchars($item['subtitle']) ?></div>
            </div>
        </div>
        <div class="slider-card-body">
            <div class="slider-card-meta">
                <span><i class="fa-solid fa-list-ol"></i> #<?= $item['urutan'] ?></span>
                <?php if (!empty($item['cta_text'])): ?>
                <span><i class="fa-solid fa-mouse-pointer"></i> <?= htmlspecialchars($item['cta_text']) ?></span>
                <?php endif; ?>
            </div>
            <div class="slider-card-actions">
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
                <a href="?edit=<?= $item['id'] ?>&token=<?= $token ?>" class="table-action-btn edit" title="Edit"><i class="fa-solid fa-pen"></i></a>
                <a href="?duplicate=<?= $item['id'] ?>&token=<?= $token ?>" class="table-action-btn" title="Duplikasi" onclick="return confirm('Duplikasi slide ini?')"><i class="fa-solid fa-copy"></i></a>
                <a href="?delete=<?= $item['id'] ?>&token=<?= $token ?>" class="table-action-btn delete" onclick="return confirm('Yakin ingin menghapus slide ini?')" title="Hapus"><i class="fa-solid fa-trash"></i></a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<?php
$page_content = ob_get_clean();
require_once 'includes/admin_template.php';
?>