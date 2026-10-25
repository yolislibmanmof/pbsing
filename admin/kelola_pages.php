<?php
require_once '../includes/config.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';
require_once 'includes/upload_helper.php';
require_login();

$page_title = 'Kelola Halaman Statis';
$current_page = 'pages';
$table = 'pages';

// ===== AUTO-MIGRATION (Self-Healing Database) =====
$required_cols = [
    'seo_title'       => "varchar(150) DEFAULT NULL",
    'seo_description' => "varchar(255) DEFAULT NULL",
    'seo_keywords'    => "varchar(255) DEFAULT NULL",
    'template'        => "varchar(50) DEFAULT 'default'",
    'show_in_menu'    => "tinyint(1) DEFAULT 0",
    'show_in_footer'  => "tinyint(1) DEFAULT 0",
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
        $pdo->prepare("INSERT INTO admin_logs (user_id, user_name, action, detail, ip) VALUES (?, ?, 'DELETE_PAGE', ?, ?)")
            ->execute([$_SESSION['admin_id'], $_SESSION['admin_name'], 'Menghapus halaman: ' . $row['judul'], $_SERVER['REMOTE_ADDR']]);
    }
    header("Location: kelola_pages.php?msg=deleted");
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
        $pdo->prepare("INSERT INTO $table (slug, judul, konten, gambar, urutan, status, seo_title, seo_description, seo_keywords, template, show_in_menu, show_in_footer) VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?)")
            ->execute([
                $newSlug, $orig['judul'] . ' (Copy)', $orig['konten'],
                $orig['gambar'], ($orig['urutan'] ?? 0) + 1,
                $orig['seo_title'] ?? '', $orig['seo_description'] ?? '',
                $orig['seo_keywords'] ?? '', $orig['template'] ?? 'default',
                $orig['show_in_menu'] ?? 0, $orig['show_in_footer'] ?? 0
            ]);
        $pdo->prepare("INSERT INTO admin_logs (user_id, user_name, action, detail, ip) VALUES (?, ?, 'DUPLICATE_PAGE', ?, ?)")
            ->execute([$_SESSION['admin_id'], $_SESSION['admin_name'], 'Menduplikasi halaman: ' . $orig['judul'], $_SERVER['REMOTE_ADDR']]);
    }
    header("Location: kelola_pages.php?msg=duplicated");
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
        $pdo->prepare("INSERT INTO admin_logs (user_id, user_name, action, detail, ip) VALUES (?, ?, 'BULK_DELETE_PAGE', ?, ?)")
            ->execute([$_SESSION['admin_id'], $_SESSION['admin_name'], "Menghapus $deleted halaman", $_SERVER['REMOTE_ADDR']]);
    }
    header("Location: kelola_pages.php?msg=bulk_deleted&count=$deleted");
    exit;
}

// ===== HANDLE TOGGLE STATUS =====
if (isset($_POST['toggle_status']) && isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $id = (int)$_POST['id'];
    $status = (int)$_POST['status'];
    $pdo->prepare("UPDATE $table SET status = ? WHERE id = ?")->execute([$status, $id]);
    header("Location: kelola_pages.php?msg=toggled");
    exit;
}

// ===== HANDLE SAVE =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['toggle_status']) && !isset($_POST['bulk_delete']) && isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $judul = sanitize($_POST['judul']);
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $_POST['slug'] ?: $_POST['judul'])));
    $konten = $_POST['konten'] ?? '';
    $urutan = (int)$_POST['urutan'];
    $status = isset($_POST['status']) ? 1 : 0;
    $seo_title = sanitize($_POST['seo_title']);
    $seo_description = sanitize($_POST['seo_description']);
    $seo_keywords = sanitize($_POST['seo_keywords']);
    $template = sanitize($_POST['template']);
    $show_in_menu = isset($_POST['show_in_menu']) ? 1 : 0;
    $show_in_footer = isset($_POST['show_in_footer']) ? 1 : 0;
    
    if (empty($judul)) {
        $error = "Judul halaman wajib diisi.";
    } elseif (empty($konten) || $konten === '<p><br></p>') {
        $error = "Konten halaman tidak boleh kosong.";
    } else {
        $data_save = [
            'judul' => $judul, 'slug' => $slug, 'konten' => $konten,
            'urutan' => $urutan, 'status' => $status,
            'seo_title' => $seo_title, 'seo_description' => $seo_description,
            'seo_keywords' => $seo_keywords, 'template' => $template,
            'show_in_menu' => $show_in_menu, 'show_in_footer' => $show_in_footer
        ];
        
        // Handle gambar upload
        if (!empty($_FILES['gambar']['name'])) {
            $upload = handle_upload($_FILES['gambar'], '../assets/uploads/pages/', ['jpg', 'jpeg', 'png', 'webp']);
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
                $action = 'UPDATE_PAGE';
                $detail = "Memperbarui halaman: $judul";
            } else {
                $sql = "INSERT INTO $table SET " . implode(', ', $sets);
                $pdo->prepare($sql)->execute($params);
                $action = 'ADD_PAGE';
                $detail = "Menambah halaman baru: $judul";
            }
            
            $pdo->prepare("INSERT INTO admin_logs (user_id, user_name, action, detail, ip) VALUES (?, ?, ?, ?, ?)")
                ->execute([$_SESSION['admin_id'], $_SESSION['admin_name'], $action, $detail, $_SERVER['REMOTE_ADDR']]);
                
            header("Location: kelola_pages.php?msg=saved");
            exit;
        }
    }
}

$items = $pdo->query("SELECT * FROM $table ORDER BY urutan ASC, id DESC")->fetchAll();

$total_pages = count($items);
$published_count = count(array_filter($items, fn($i) => ($i['status'] ?? 0) == 1));
$draft_count = count(array_filter($items, fn($i) => ($i['status'] ?? 0) == 0));
$menu_count = count(array_filter($items, fn($i) => ($i['show_in_menu'] ?? 0) == 1));

$data = [
    'id'=>0, 'slug'=>'', 'judul'=>'', 'konten'=>'', 'gambar'=>'',
    'urutan'=>1, 'status'=>1, 'seo_title'=>'', 'seo_description'=>'',
    'seo_keywords'=>'', 'template'=>'default', 'show_in_menu'=>0, 'show_in_footer'=>0
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

$template_options = [
    'default' => ['label' => 'Default (Sidebar Kanan)', 'icon' => 'fa-columns', 'desc' => 'Layout standar dengan sidebar navigasi'],
    'full' => ['label' => 'Full Width', 'icon' => 'fa-expand', 'desc' => 'Tanpa sidebar, konten melebar penuh'],
    'landing' => ['label' => 'Landing Page', 'icon' => 'fa-rocket', 'desc' => 'Tanpa header/footer (untuk promosi)']
];

ob_start();
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<style>
/* ===== SELECT2 & QUILL DARK THEME ===== */
.select2-container--default .select2-selection--single { background-color: var(--bg-darker); border: 1px solid var(--border-dark); border-radius: var(--radius-md); height: 42px; display: flex; align-items: center; }
.select2-container--default .select2-selection--single .select2-selection__rendered { color: var(--text-main); padding-left: 14px; }
.select2-dropdown { background-color: var(--panel-bg); border: 1px solid var(--border-dark); border-radius: var(--radius-md); }
.select2-search__field { background-color: var(--bg-darker) !important; color: var(--text-main) !important; border: 1px solid var(--border-dark) !important; }
.select2-results__option { color: var(--text-main); }
.select2-results__option--highlighted[aria-selected] { background-color: var(--crimson) !important; }

.ql-toolbar { border: 1px solid var(--border-dark) !important; border-radius: var(--radius-md) var(--radius-md) 0 0 !important; background: var(--bg-darker); }
.ql-container { border: 1px solid var(--border-dark) !important; border-radius: 0 0 var(--radius-md) var(--radius-md) !important; background: var(--bg-darker); color: var(--text-main); font-family: inherit; }
.ql-stroke { stroke: var(--text-muted) !important; }
.ql-fill { fill: var(--text-muted) !important; }
.ql-picker { color: var(--text-muted) !important; }
.ql-editor { color: var(--text-main); min-height: 400px; line-height: 1.7; }
.ql-editor.ql-blank::before { color: var(--text-dim); font-style: italic; }
.ql-editor h1, .ql-editor h2, .ql-editor h3 { color: #fff; }
.ql-editor a { color: var(--sky-blue); }

/* ===== STATS GRID ===== */
.pages-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}
.pages-stat-card {
    background: var(--panel-bg);
    border: 1px solid var(--border-dark);
    border-radius: var(--radius-md);
    padding: 1.2rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    border-left: 4px solid var(--crimson);
    cursor: pointer;
    transition: all .3s;
}
.pages-stat-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); }
.pages-stat-icon {
    width: 48px; height: 48px;
    border-radius: var(--radius-md);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.3rem;
    background: rgba(138,21,56,0.15);
    color: var(--crimson);
}
.pages-stat-value { font-size: 1.6rem; font-weight: 900; color: #fff; line-height: 1; }
.pages-stat-label { color: var(--text-muted); font-size: .82rem; margin-top: .2rem; }

/* ===== FILTER BAR ===== */
.pages-filter-bar {
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
.pages-filter-bar .form-group { margin: 0; }
.pages-filter-bar label { font-size: .85rem; color: var(--text-muted); margin-bottom: .4rem; display: block; font-weight: 600; }

/* ===== BULK ACTIONS ===== */
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

/* ===== TEMPLATE SELECTOR ===== */
.template-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 1rem;
    margin-top: .5rem;
}
.template-card {
    background: var(--bg-darker);
    border: 2px solid var(--border-dark);
    border-radius: var(--radius-md);
    padding: 1.2rem 1rem;
    text-align: center;
    cursor: pointer;
    transition: all .2s;
    position: relative;
}
.template-card:hover { border-color: var(--border-light); transform: translateY(-2px); }
.template-card.active {
    border-color: var(--crimson);
    background: rgba(138,21,56,0.08);
    box-shadow: 0 4px 12px rgba(138,21,56,0.15);
}
.template-card.active::after {
    content: '\f00c';
    font-family: 'Font Awesome 6 Free';
    font-weight: 900;
    position: absolute;
    top: 8px; right: 8px;
    width: 20px; height: 20px;
    background: var(--crimson);
    color: #fff;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: .65rem;
}
.template-icon {
    font-size: 1.8rem;
    color: var(--crimson);
    margin-bottom: .6rem;
}
.template-label {
    font-weight: 700;
    color: #fff;
    font-size: .85rem;
    margin-bottom: .2rem;
}
.template-desc {
    font-size: .7rem;
    color: var(--text-muted);
    line-height: 1.3;
}

/* ===== SEO PREVIEW ===== */
.seo-preview-box {
    background: #fff;
    border-radius: 10px;
    padding: 1rem;
    margin-top: 1rem;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    color: #333;
}
.seo-preview-label {
    font-size: .68rem;
    color: #888;
    margin-bottom: .4rem;
    text-transform: uppercase;
    letter-spacing: .5px;
    display: flex;
    align-items: center;
    gap: .3rem;
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

/* ===== UPLOAD ZONE ===== */
.upload-zone {
    border: 2px dashed var(--border-light);
    border-radius: var(--radius-md);
    padding: 1.5rem;
    text-align: center;
    cursor: pointer;
    transition: all .3s ease;
    background: var(--bg-darker);
    position: relative;
    overflow: hidden;
}
.upload-zone:hover, .upload-zone.dragover { border-color: var(--crimson); background: rgba(138,21,56,0.05); }
.upload-zone i { font-size: 2rem; color: var(--text-muted); margin-bottom: .8rem; display: block; }
.upload-zone:hover i { color: var(--crimson); }
.upload-zone input[type="file"] { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
.preview-container {
    margin-top: 1rem;
    border-radius: var(--radius-md);
    overflow: hidden;
    border: 1px solid var(--border-dark);
    display: none;
    position: relative;
}
.preview-container img { width: 100%; display: block; max-height: 250px; object-fit: cover; }
.preview-remove {
    position: absolute; top: 8px; right: 8px;
    width: 28px; height: 28px; border-radius: 50%;
    background: var(--danger); color: #fff;
    border: none; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
}

/* ===== FORM LAYOUT ===== */
.pages-form-grid {
    display: grid;
    grid-template-columns: 1.6fr 1fr;
    gap: 2rem;
}
@media (max-width: 1100px) { .pages-form-grid { grid-template-columns: 1fr; } }

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

.input-icon-wrap { position: relative; }
.input-icon-wrap i.input-icon {
    position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
    color: var(--text-muted); font-size: .9rem; pointer-events: none;
}
.input-icon-wrap .form-control { padding-left: 42px; }
.input-icon-wrap textarea.form-control { padding-left: 14px; padding-top: 12px; }

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
.info-box.info {
    background: rgba(135,206,235,0.1);
    border: 1px solid rgba(135,206,235,0.3);
    border-left: 4px solid var(--sky-blue);
    color: var(--text-main);
}
.info-box i { font-size: 1.1rem; margin-top: 2px; flex-shrink: 0; color: var(--sky-blue); }
.info-box strong { display: block; margin-bottom: .2rem; font-size: .9rem; }

.toggle-switch { position: relative; display: inline-block; width: 42px; height: 22px; flex-shrink: 0; }
.toggle-switch input { opacity: 0; width: 0; height: 0; }
.toggle-slider { position: absolute; cursor: pointer; inset: 0; background: var(--bg-darker); border: 1px solid var(--border-dark); transition: .3s; border-radius: 50px; }
.toggle-slider:before { position: absolute; content: ""; height: 16px; width: 16px; left: 2px; bottom: 2px; background: var(--text-muted); transition: .3s; border-radius: 50%; }
.toggle-switch input:checked + .toggle-slider { background: var(--success); border-color: var(--success); }
.toggle-switch input:checked + .toggle-slider:before { transform: translateX(20px); background: #fff; }

.status-pill {
    display: inline-flex; align-items: center; gap: .3rem;
    padding: .25rem .7rem; border-radius: 50px;
    font-size: .72rem; font-weight: 700; text-transform: uppercase;
}
.status-pill.published { background: rgba(46,139,87,0.15); color: var(--success); }
.status-pill.draft { background: rgba(243,156,18,0.15); color: var(--warning); }
.status-pill::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }

.template-pill {
    display: inline-flex; align-items: center; gap: .3rem;
    padding: .2rem .6rem; border-radius: 50px;
    font-size: .7rem; font-weight: 600;
    background: rgba(135,206,235,0.1);
    color: var(--sky-blue);
}

.page-row-title {
    font-weight: 700;
    color: #fff;
    font-size: .92rem;
    margin-bottom: .2rem;
}
.page-row-slug {
    font-family: monospace;
    font-size: .75rem;
    color: var(--text-muted);
}
.page-row-badges {
    display: flex;
    gap: .3rem;
    margin-top: .4rem;
    flex-wrap: wrap;
}
.page-row-badge {
    font-size: .65rem;
    padding: .1rem .5rem;
    border-radius: 3px;
    background: var(--bg-darker);
    color: var(--text-muted);
    border: 1px solid var(--border-dark);
    display: inline-flex;
    align-items: center;
    gap: .2rem;
}
.page-row-badge i { font-size: .6rem; }
</style>

<?php if (isset($_GET['msg'])): 
    $msgs = [
        'saved' => ['success', 'Berhasil!', 'Halaman berhasil disimpan.'],
        'deleted' => ['success', 'Berhasil!', 'Halaman berhasil dihapus.'],
        'duplicated' => ['success', 'Berhasil!', 'Halaman berhasil diduplikasi.'],
        'toggled' => ['success', 'Berhasil!', 'Status halaman diperbarui.']
    ];
    if (isset($msgs[$_GET['msg']])): ?>
    <script>Swal.fire({icon:'<?=$msgs[$_GET['msg']][0]?>', title:'<?=$msgs[$_GET['msg']][1]?>', text:'<?=$msgs[$_GET['msg']][2]?>', timer:2500, showConfirmButton:false});</script>
<?php endif; 
    if ($_GET['msg'] === 'bulk_deleted'): ?>
    <script>Swal.fire({icon:'success', title:'Berhasil!', text:'<?= (int)($_GET['count'] ?? 0) ?> halaman berhasil dihapus.', timer:2500, showConfirmButton:false});</script>
<?php endif; 
endif; ?>

<?php if (isset($error)): ?>
<script>Swal.fire({icon:'error', title:'Validasi Gagal', text:'<?= addslashes($error) ?>'});</script>
<?php endif; ?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;">
    <div>
        <h2 class="section-title"><i class="fa-solid fa-file-lines"></i> <?= isset($_GET['edit']) ? 'Edit' : 'Kelola' ?> Halaman Statis</h2>
        <p style="color:var(--text-muted); font-size:.88rem; margin-top:.3rem;">
            <i class="fa-solid fa-circle-info"></i> Kelola halaman seperti Sejarah, Visi Misi, dan lainnya
        </p>
    </div>
    <div style="display:flex; gap:.8rem;">
        <?php if (isset($_GET['edit'])): ?>
        <a href="kelola_pages.php" class="btn btn-secondary"><i class="fa-solid fa-xmark"></i> Batal</a>
        <?php else: ?>
        <a href="?action=add" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Tambah Halaman</a>
        <a href="../" target="_blank" class="btn btn-secondary"><i class="fa-solid fa-external-link"></i> Lihat Website</a>
        <?php endif; ?>
    </div>
</div>

<?php if (!isset($_GET['edit']) && !isset($_GET['action'])): ?>
<!-- ===== STATS DASHBOARD ===== -->
<div class="pages-stats-grid">
    <div class="pages-stat-card">
        <div class="pages-stat-icon"><i class="fa-solid fa-file-lines"></i></div>
        <div>
            <div class="pages-stat-value" data-counter="<?= $total_pages ?>">0</div>
            <div class="pages-stat-label">Total Halaman</div>
        </div>
    </div>
    <div class="pages-stat-card" style="border-left-color:var(--success);" onclick="document.getElementById('statusFilter').value='published'; document.getElementById('statusFilter').dispatchEvent(new Event('change'));">
        <div class="pages-stat-icon" style="background:rgba(46,139,87,0.15); color:var(--success);"><i class="fa-solid fa-circle-check"></i></div>
        <div>
            <div class="pages-stat-value" data-counter="<?= $published_count ?>">0</div>
            <div class="pages-stat-label">Published</div>
        </div>
    </div>
    <div class="pages-stat-card" style="border-left-color:var(--warning);" onclick="document.getElementById('statusFilter').value='draft'; document.getElementById('statusFilter').dispatchEvent(new Event('change'));">
        <div class="pages-stat-icon" style="background:rgba(243,156,18,0.15); color:var(--warning);"><i class="fa-solid fa-file-pen"></i></div>
        <div>
            <div class="pages-stat-value" data-counter="<?= $draft_count ?>">0</div>
            <div class="pages-stat-label">Draft</div>
        </div>
    </div>
    <div class="pages-stat-card" style="border-left-color:var(--sky-blue);">
        <div class="pages-stat-icon" style="background:rgba(135,206,235,0.15); color:var(--sky-blue);"><i class="fa-solid fa-bars"></i></div>
        <div>
            <div class="pages-stat-value" data-counter="<?= $menu_count ?>">0</div>
            <div class="pages-stat-label">Di Menu Utama</div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (isset($_GET['action']) && $_GET['action'] === 'add' || isset($_GET['edit'])): ?>
<!-- ===== FORM ADD/EDIT ===== -->
<div class="panel">
    <div class="panel-header">
        <h3 class="panel-title">
            <i class="fa-solid fa-pen-to-square"></i> 
            <?= isset($_GET['edit']) ? 'Edit' : 'Tambah' ?> Halaman Statis
        </h3>
    </div>
    <div class="panel-body">
        <form method="POST" enctype="multipart/form-data" id="pagesForm">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="id" value="<?= $data['id'] ?>">
            <input type="hidden" name="konten" id="hiddenKonten" value="<?= htmlspecialchars($data['konten']) ?>">
            
            <div class="pages-form-grid">
                <!-- LEFT: FORM FIELDS -->
                <div>
                    <div class="info-box info">
                        <i class="fa-solid fa-lightbulb"></i>
                        <div>
                            <strong>Optimasi Halaman:</strong>
                            Gunakan Meta Title dan Description yang unik untuk setiap halaman agar mudah ditemukan di mesin pencari. Pilih template layout yang sesuai dengan konten Anda.
                        </div>
                    </div>
                    
                    <!-- KONTEN UTAMA -->
                    <div class="form-section-title"><i class="fa-solid fa-heading"></i> Konten Utama</div>
                    
                    <div class="form-group">
                        <label>Judul Halaman <span class="required">*</span></label>
                        <div class="input-icon-wrap">
                            <i class="fa-solid fa-heading input-icon"></i>
                            <input type="text" name="judul" id="f_judul" class="form-control" value="<?= htmlspecialchars($data['judul']) ?>" required oninput="generateSlug(this.value)" placeholder="Contoh: Sejarah Program Studi">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Slug URL <span class="required">*</span></label>
                        <div class="input-icon-wrap">
                            <i class="fa-solid fa-link input-icon"></i>
                            <input type="text" name="slug" id="f_slug" class="form-control" value="<?= htmlspecialchars($data['slug']) ?>" required style="font-family:monospace; color:var(--sky-blue);" placeholder="sejarah-program-studi">
                        </div>
                        <div style="margin-top:.4rem; padding:.4rem .7rem; background:var(--bg-darker); border:1px solid var(--border-dark); border-radius:var(--radius-sm); font-family:monospace; font-size:.75rem; color:var(--sky-blue); display:flex; align-items:center; gap:.3rem;">
                            <i class="fa-solid fa-globe" style="color:var(--success);"></i>
                            <span><?= htmlspecialchars(BASE_URL ?? '') ?><strong id="slugPreview"><?= htmlspecialchars($data['slug'] ?: 'slug-url') ?></strong></span>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Konten Halaman <span class="required">*</span></label>
                        <div id="quillEditor" style="height:400px;"><?= $data['konten'] ?></div>
                        <div class="field-counter" id="wordCounter">0 kata</div>
                    </div>
                    
                    <!-- GAMBAR -->
                    <div class="form-section-title"><i class="fa-solid fa-image"></i> Gambar Header</div>
                    
                    <div class="form-group">
                        <div class="upload-zone" id="dropZone">
                            <i class="fa-solid fa-cloud-arrow-up"></i>
                            <p style="margin:0; font-weight:600; color:var(--text-main);">Klik atau Drag & Drop gambar</p>
                            <small style="color:var(--text-muted);">JPG, PNG, WebP (Maks 5MB). Rasio 16:9 direkomendasikan.</small>
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
                            <input type="text" name="seo_title" id="f_seo_title" class="form-control" value="<?= htmlspecialchars($data['seo_title'] ?? '') ?>" maxlength="70" data-counter="seoTitleCounter" placeholder="Biarkan kosong untuk menggunakan judul halaman">
                        </div>
                        <div class="field-counter" id="seoTitleCounter">0 / 70</div>
                    </div>
                    
                    <div class="form-group">
                        <label>Meta Description</label>
                        <div class="input-icon-wrap">
                            <textarea name="seo_description" id="f_seo_desc" rows="3" class="form-control" maxlength="170" data-counter="seoDescCounter" placeholder="Ringkasan singkat halaman yang akan tampil di hasil pencarian Google..." style="padding-left:14px; padding-top:12px;"><?= htmlspecialchars($data['seo_description'] ?? '') ?></textarea>
                        </div>
                        <div class="field-counter" id="seoDescCounter">0 / 170</div>
                    </div>
                    
                    <div class="form-group">
                        <label>Meta Keywords</label>
                        <div class="input-icon-wrap">
                            <i class="fa-solid fa-tags input-icon"></i>
                            <input type="text" name="seo_keywords" class="form-control" value="<?= htmlspecialchars($data['seo_keywords'] ?? '') ?>" placeholder="kata,kunci,halaman">
                        </div>
                    </div>
                    
                    <!-- TEMPLATE & SETTINGS -->
                    <div class="form-section-title"><i class="fa-solid fa-table-cells"></i> Template & Pengaturan</div>
                    
                    <div class="form-group">
                        <label>Layout Template</label>
                        <div class="template-grid">
                            <?php foreach ($template_options as $key => $opt): ?>
                            <div class="template-card <?= ($data['template'] ?? 'default') === $key ? 'active' : '' ?>" data-template="<?= $key ?>" onclick="selectTemplate('<?= $key ?>')">
                                <div class="template-icon"><i class="fa-solid <?= $opt['icon'] ?>"></i></div>
                                <div class="template-label"><?= $opt['label'] ?></div>
                                <div class="template-desc"><?= $opt['desc'] ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" name="template" id="f_template" value="<?= htmlspecialchars($data['template'] ?? 'default') ?>">
                    </div>
                    
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem; margin-top:1rem;">
                        <div class="form-group">
                            <label style="display:block; margin-bottom:.5rem; font-weight:600; font-size:.88rem;">Urutan Tampil</label>
                            <div class="input-icon-wrap">
                                <i class="fa-solid fa-list-ol input-icon"></i>
                                <input type="number" name="urutan" class="form-control" value="<?= (int)($data['urutan'] ?? 1) ?>" min="1">
                            </div>
                        </div>
                        <div class="form-group">
                            <label style="display:block; margin-bottom:.5rem; font-weight:600; font-size:.88rem;">Status</label>
                            <label class="toggle-switch" style="margin-top:.4rem;">
                                <input type="checkbox" name="status" value="1" <?= ($data['status'] ?? 1) == 1 ? 'checked' : '' ?>>
                                <span class="toggle-slider"></span>
                            </label>
                            <span style="margin-left:.5rem; font-weight:600; font-size:.88rem;">Published</span>
                        </div>
                    </div>
                    
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem; margin-top:1rem;">
                        <div class="form-group">
                            <label class="toggle-switch">
                                <input type="checkbox" name="show_in_menu" value="1" <?= ($data['show_in_menu'] ?? 0) == 1 ? 'checked' : '' ?>>
                                <span class="toggle-slider"></span>
                            </label>
                            <span style="margin-left:.5rem; font-weight:600; font-size:.88rem;">
                                <i class="fa-solid fa-bars" style="color:var(--sky-blue);"></i>
                                Tampilkan di Menu Navigasi
                            </span>
                        </div>
                        <div class="form-group">
                            <label class="toggle-switch">
                                <input type="checkbox" name="show_in_footer" value="1" <?= ($data['show_in_footer'] ?? 0) == 1 ? 'checked' : '' ?>>
                                <span class="toggle-slider"></span>
                            </label>
                            <span style="margin-left:.5rem; font-weight:600; font-size:.88rem;">
                                <i class="fa-solid fa-shoe-prints" style="color:var(--warning);"></i>
                                Tampilkan di Footer
                            </span>
                        </div>
                    </div>
                    
                    <div style="margin-top:2rem; display:flex; gap:1rem; padding-top:1.5rem; border-top:1px solid var(--border-dark);">
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> <?= $data['id']>0?'Perbarui':'Simpan' ?> Halaman</button>
                        <a href="kelola_pages.php" class="btn btn-secondary"><i class="fa-solid fa-xmark"></i> Batal</a>
                    </div>
                </div>
                
                <!-- RIGHT: LIVE PREVIEW -->
                <div style="position:sticky; top:20px; align-self:start;">
                    <div class="form-section-title" style="margin-top:0;">
                        <i class="fa-solid fa-eye"></i> Live Preview
                    </div>
                    
                    <!-- Google Search Preview -->
                    <div class="seo-preview-box">
                        <div class="seo-preview-label"><i class="fa-brands fa-google" style="color:#4285F4;"></i> Tampilan di Google Search</div>
                        <div class="seo-title-line" id="prevSeoTitle"><?= htmlspecialchars($data['seo_title'] ?: $data['judul'] ?: 'Judul Halaman') ?></div>
                        <div class="seo-url-line" id="prevSeoUrl"><?= htmlspecialchars(BASE_URL ?? 'https://pbsi.ummaumere.ac.id/') ?>page/<span id="prevSlugVal"><?= htmlspecialchars($data['slug'] ?: 'slug-url') ?></span></div>
                        <div class="seo-desc-line" id="prevSeoDesc"><?= htmlspecialchars($data['seo_description'] ?: 'Deskripsi singkat halaman akan muncul di sini. Tulis ringkasan yang menarik untuk meningkatkan CTR.') ?></div>
                    </div>
                    
                    <!-- Page Layout Wireframe -->
                    <div style="margin-top:1rem; background:var(--panel-bg); border:1px solid var(--border-dark); border-radius:var(--radius-md); padding:1rem;">
                        <div style="font-size:.72rem; color:var(--text-muted); margin-bottom:.6rem; text-transform:uppercase; letter-spacing:.5px; font-weight:700;">Layout Preview</div>
                        <div id="layoutPreview" style="background:#fff; border-radius:6px; padding:.6rem; min-height:120px; display:grid; gap:.5rem; color:#333;">
                            <!-- Default Layout -->
                            <div style="height:16px; background:#eee; border-radius:3px;"></div>
                            <div style="display:grid; grid-template-columns: 2fr 1fr; gap:.4rem; flex:1;">
                                <div style="background:#f8f8f8; border-radius:3px; padding:.4rem;">
                                    <div style="height:8px; background:#ddd; width:80%; margin-bottom:.3rem;"></div>
                                    <div style="height:4px; background:#eee; width:100%; margin-bottom:.2rem;"></div>
                                    <div style="height:4px; background:#eee; width:90%;"></div>
                                </div>
                                <div style="background:#f0f0f0; border-radius:3px;"></div>
                            </div>
                        </div>
                        <div style="font-size:.75rem; color:var(--text-muted); margin-top:.5rem; text-align:center;" id="layoutLabel">
                            <i class="fa-solid fa-columns"></i> Default (Sidebar Kanan)
                        </div>
                    </div>
                    
                    <div style="margin-top:1rem; padding:.8rem; background:rgba(46,139,87,0.08); border:1px solid rgba(46,139,87,0.2); border-radius:var(--radius-md); font-size:.78rem; color:var(--text-muted);">
                        <i class="fa-solid fa-circle-check" style="color:var(--success);"></i>
                        <strong style="color:var(--success);"> Tips:</strong> Halaman dengan Meta Description yang baik dapat meningkatkan Click-Through Rate (CTR) dari hasil pencarian Google hingga 30%.
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
        placeholder: 'Tulis konten halaman di sini...',
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
    document.getElementById('pagesForm').addEventListener('submit', function() {
        document.getElementById('hiddenKonten').value = quill.root.innerHTML;
    });
    
    // Word counter
    function updateWordCount() {
        const text = quill.getText().trim();
        const words = text ? text.split(/\s+/).length : 0;
        const el = document.getElementById('wordCounter');
        if (el) el.textContent = `${words} kata`;
    }
    quill.on('text-change', updateWordCount);
    updateWordCount();
    
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
    
    const seoTitle = document.getElementById('f_seo_title');
    if (seoTitle) {
        updateCounter(seoTitle, 'seoTitleCounter', 70, 60);
        seoTitle.addEventListener('input', () => updateCounter(seoTitle, 'seoTitleCounter', 70, 60));
    }
    
    const seoDesc = document.getElementById('f_seo_desc');
    if (seoDesc) {
        updateCounter(seoDesc, 'seoDescCounter', 170, 160);
        seoDesc.addEventListener('input', () => updateCounter(seoDesc, 'seoDescCounter', 170, 160));
    }
    
    // ===== LIVE PREVIEW BINDINGS =====
    document.getElementById('f_judul')?.addEventListener('input', e => {
        const val = e.target.value || 'Judul Halaman';
        const seoTitleVal = document.getElementById('f_seo_title')?.value;
        document.getElementById('prevSeoTitle').textContent = seoTitleVal || val;
    });
    
    document.getElementById('f_slug')?.addEventListener('input', e => {
        const val = e.target.value || 'slug-url';
        document.getElementById('prevSlugVal').textContent = val;
        document.getElementById('slugPreview').textContent = val;
    });
    
    document.getElementById('f_seo_title')?.addEventListener('input', e => {
        const judulVal = document.getElementById('f_judul').value;
        document.getElementById('prevSeoTitle').textContent = e.target.value || judulVal || 'Judul Halaman';
    });
    
    document.getElementById('f_seo_desc')?.addEventListener('input', e => {
        document.getElementById('prevSeoDesc').textContent = e.target.value || 'Deskripsi singkat halaman akan muncul di sini. Tulis ringkasan yang menarik untuk meningkatkan CTR.';
    });
    
    // ===== IMAGE UPLOAD =====
    const fileInput = document.getElementById('fileInput');
    const dropZone = document.getElementById('dropZone');
    const previewContainer = document.getElementById('previewContainer');
    const previewImg = document.getElementById('previewImg');
    
    function handleFile(file) {
        const reader = new FileReader();
        reader.onload = (e) => {
            previewImg.src = e.target.result;
            previewContainer.style.display = 'block';
        };
        reader.readAsDataURL(file);
    }
    
    fileInput?.addEventListener('change', (e) => {
        if (e.target.files.length) handleFile(e.target.files[0]);
    });
    
    ['dragenter', 'dragover'].forEach(evt => {
        dropZone?.addEventListener(evt, e => { e.preventDefault(); dropZone.classList.add('dragover'); });
    });
    ['dragleave', 'drop'].forEach(evt => {
        dropZone?.addEventListener(evt, e => { e.preventDefault(); dropZone.classList.remove('dragover'); });
    });
    dropZone?.addEventListener('drop', e => {
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
    };
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
        document.getElementById('prevSlugVal').textContent = slug || 'slug-url';
        document.getElementById('slugPreview').textContent = slug || 'slug-url';
    }
}

function selectTemplate(key) {
    document.getElementById('f_template').value = key;
    document.querySelectorAll('.template-card').forEach(card => {
        card.classList.toggle('active', card.dataset.template === key);
    });
    
    // Update layout preview
    const layoutPreview = document.getElementById('layoutPreview');
    const layoutLabel = document.getElementById('layoutLabel');
    
    if (key === 'default') {
        layoutPreview.style.gridTemplateColumns = '1fr';
        layoutPreview.innerHTML = `
            <div style="height:16px; background:#eee; border-radius:3px;"></div>
            <div style="display:grid; grid-template-columns: 2fr 1fr; gap:.4rem; flex:1;">
                <div style="background:#f8f8f8; border-radius:3px; padding:.4rem;">
                    <div style="height:8px; background:#ddd; width:80%; margin-bottom:.3rem;"></div>
                    <div style="height:4px; background:#eee; width:100%; margin-bottom:.2rem;"></div>
                    <div style="height:4px; background:#eee; width:90%;"></div>
                </div>
                <div style="background:#f0f0f0; border-radius:3px;"></div>
            </div>
        `;
        layoutLabel.innerHTML = '<i class="fa-solid fa-columns"></i> Default (Sidebar Kanan)';
    } else if (key === 'full') {
        layoutPreview.innerHTML = `
            <div style="height:16px; background:#eee; border-radius:3px;"></div>
            <div style="background:#f8f8f8; border-radius:3px; padding:.4rem; flex:1;">
                <div style="height:8px; background:#ddd; width:60%; margin-bottom:.3rem;"></div>
                <div style="height:4px; background:#eee; width:100%; margin-bottom:.2rem;"></div>
                <div style="height:4px; background:#eee; width:95%; margin-bottom:.2rem;"></div>
                <div style="height:4px; background:#eee; width:85%;"></div>
            </div>
        `;
        layoutLabel.innerHTML = '<i class="fa-solid fa-expand"></i> Full Width (Tanpa Sidebar)';
    } else if (key === 'landing') {
        layoutPreview.innerHTML = `
            <div style="background:#f8f8f8; border-radius:3px; padding:.4rem; flex:1;">
                <div style="height:12px; background:#ddd; width:40%; margin:0 auto .4rem;"></div>
                <div style="height:4px; background:#eee; width:80%; margin:0 auto .2rem;"></div>
                <div style="height:4px; background:#eee; width:60%; margin:0 auto;"></div>
            </div>
        `;
        layoutLabel.innerHTML = '<i class="fa-solid fa-rocket"></i> Landing Page (Minimalis)';
    }
}
</script>

<?php else: ?>
<!-- ===== FILTER BAR ===== -->
<?php if (!empty($items)): ?>
<div class="pages-filter-bar">
    <div class="form-group">
        <label><i class="fa-solid fa-search"></i> Cari Halaman</label>
        <input type="text" id="pageSearch" class="form-control" placeholder="Ketik judul atau slug...">
    </div>
    <div class="form-group">
        <label><i class="fa-solid fa-table-cells"></i> Template</label>
        <select id="templateFilter" class="form-control">
            <option value="all">Semua Template</option>
            <?php foreach ($template_options as $key => $opt): ?>
            <option value="<?= $key ?>"><?= $opt['label'] ?></option>
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
        <button type="button" class="btn btn-sm btn-secondary" onclick="resetFilters()" style="width:100%;">
            <i class="fa-solid fa-rotate-left"></i> Reset
        </button>
    </div>
</div>

<!-- BULK ACTIONS BAR -->
<div class="bulk-actions-bar" id="bulkActionsBar">
    <div class="bulk-info">
        <i class="fa-solid fa-check-double" style="color:var(--crimson);"></i>
        <span><span class="bulk-count" id="bulkCount">0</span> halaman dipilih</span>
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

<!-- ===== TABLE VIEW ===== -->
<div class="panel">
    <div class="panel-body no-padding">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th style="width:40px;">
                            <input type="checkbox" id="selectAll" style="width:16px; height:16px; accent-color:var(--crimson);">
                        </th>
                        <th style="width:60px;">Urut</th>
                        <th>Halaman</th>
                        <th style="width:130px;">Template</th>
                        <th style="width:100px;">Status</th>
                        <th style="width:160px;">Aksi</th>
                    </tr>
                </thead>
                <tbody id="pagesTableBody">
                    <?php if (empty($items)): ?>
                    <tr><td colspan="6" style="text-align:center; padding:3rem; color:var(--text-muted);">
                        <i class="fa-solid fa-file-lines" style="font-size:3rem; margin-bottom:1rem; display:block; opacity:.3;"></i>
                        Belum ada halaman statis.
                    </td></tr>
                    <?php else: foreach ($items as $item): 
                        $token = hash_hmac('sha256', $item['id'], $_SESSION['csrf_token']);
                        $isPublished = ($item['status'] ?? 0) == 1;
                        $templateKey = $item['template'] ?? 'default';
                        $templateLabel = $template_options[$templateKey]['label'] ?? 'Default';
                        $templateIcon = $template_options[$templateKey]['icon'] ?? 'fa-columns';
                    ?>
                    <tr class="page-row"
                         data-search="<?= strtolower(htmlspecialchars(($item['judul'] ?? '') . ' ' . ($item['slug'] ?? ''))) ?>"
                         data-template="<?= htmlspecialchars($templateKey) ?>"
                         data-status="<?= $isPublished ? 'published' : 'draft' ?>">
                        <td><input type="checkbox" class="row-checkbox" value="<?= $item['id'] ?>" style="width:16px; height:16px; accent-color:var(--crimson);"></td>
                        <td style="text-align:center; font-weight:800; color:var(--text-muted);">#<?= $item['urutan'] ?></td>
                        <td>
                            <div class="page-row-title">
                                <?= htmlspecialchars($item['judul']) ?>
                            </div>
                            <div class="page-row-slug">/<?= htmlspecialchars($item['slug']) ?></div>
                            <div class="page-row-badges">
                                <?php if ($item['show_in_menu'] ?? 0): ?>
                                <span class="page-row-badge"><i class="fa-solid fa-bars"></i> Menu</span>
                                <?php endif; ?>
                                <?php if ($item['show_in_footer'] ?? 0): ?>
                                <span class="page-row-badge"><i class="fa-solid fa-shoe-prints"></i> Footer</span>
                                <?php endif; ?>
                                <?php if (!empty($item['seo_description'])): ?>
                                <span class="page-row-badge" style="color:var(--success); border-color:rgba(46,139,87,0.3);"><i class="fa-solid fa-magnifying-glass-chart"></i> SEO</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <span class="template-pill">
                                <i class="fa-solid <?= $templateIcon ?>"></i>
                                <?= htmlspecialchars($templateLabel) ?>
                            </span>
                        </td>
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
                            <div class="table-actions">
                                <a href="?edit=<?= $item['id'] ?>&token=<?= $token ?>" class="table-action-btn edit" title="Edit"><i class="fa-solid fa-pen"></i></a>
                                <a href="../page.php?slug=<?= urlencode($item['slug']) ?>" target="_blank" class="table-action-btn" title="Lihat"><i class="fa-solid fa-eye"></i></a>
                                <a href="?duplicate=<?= $item['id'] ?>&token=<?= $token ?>" class="table-action-btn" title="Duplikasi" onclick="return confirm('Duplikasi halaman ini?')"><i class="fa-solid fa-copy"></i></a>
                                <a href="?delete=<?= $item['id'] ?>&token=<?= $token ?>" class="table-action-btn delete" onclick="return confirm('Yakin ingin menghapus halaman ini?')" title="Hapus"><i class="fa-solid fa-trash"></i></a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div style="text-align:center; margin-top:1rem; padding:.8rem; background:rgba(138,21,56,.05); border-radius:8px; color:var(--text-muted); font-size:.88rem;">
    Menampilkan <strong id="pageCount" style="color:var(--crimson);"><?= $total_pages ?></strong> dari <strong><?= $total_pages ?></strong> halaman
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('pageSearch');
    const templateFilter = document.getElementById('templateFilter');
    const statusFilter = document.getElementById('statusFilter');
    const countEl = document.getElementById('pageCount');
    const selectAll = document.getElementById('selectAll');
    const bulkBar = document.getElementById('bulkActionsBar');
    const bulkCount = document.getElementById('bulkCount');
    const bulkIdsContainer = document.getElementById('bulkIdsContainer');
    
    function filterPages() {
        const term = (searchInput?.value || '').toLowerCase();
        const template = templateFilter?.value || 'all';
        const status = statusFilter?.value || 'all';
        
        const rows = document.querySelectorAll('.page-row');
        let visibleCount = 0;
        
        rows.forEach(row => {
            const searchText = row.dataset.search || '';
            const rowTemplate = row.dataset.template;
            const rowStatus = row.dataset.status;
            
            const matchSearch = !term || searchText.includes(term);
            const matchTemplate = template === 'all' || rowTemplate === template;
            const matchStatus = status === 'all' || rowStatus === status;
            
            if (matchSearch && matchTemplate && matchStatus) {
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
        if (templateFilter) templateFilter.value = 'all';
        if (statusFilter) statusFilter.value = 'all';
        filterPages();
    };
    
    if (searchInput) searchInput.addEventListener('input', filterPages);
    if (templateFilter) templateFilter.addEventListener('change', filterPages);
    if (statusFilter) statusFilter.addEventListener('change', filterPages);
    
    // Bulk selection
    function updateBulkBar() {
        const checked = document.querySelectorAll('.row-checkbox:checked');
        if (checked.length > 0) {
            bulkBar?.classList.add('active');
            if (bulkCount) bulkCount.textContent = checked.length;
            if (bulkIdsContainer) {
                bulkIdsContainer.innerHTML = '';
                checked.forEach(cb => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'bulk_ids[]';
                    input.value = cb.value;
                    bulkIdsContainer.appendChild(input);
                });
            }
        } else {
            bulkBar?.classList.remove('active');
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
            icon: 'warning', title: 'Hapus ' + count + ' Halaman?',
            text: 'Tindakan ini tidak dapat dibatalkan.',
            showCancelButton: true, confirmButtonColor: '#e74c3c',
            confirmButtonText: 'Ya, Hapus', cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) document.getElementById('bulkForm').submit();
        });
    };
});
</script>
<?php endif; ?>

<script>
$(document).ready(function() {
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
});
</script>

<?php
$page_content = ob_get_clean();
require_once 'includes/admin_template.php';
?>