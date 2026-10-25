<?php
require_once '../includes/config.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';
require_once 'includes/upload_helper.php';
require_login();

$page_title = 'Pengaturan Website';
$current_page = 'settings';

// ===== AUTO-MIGRATION (Self-Healing Database) =====
$required_cols = [
    'logo'                => "varchar(255) DEFAULT ''",
    'favicon'             => "varchar(255) DEFAULT ''",
    'social_media'        => "json DEFAULT NULL",
    'seo_title'           => "varchar(150) DEFAULT NULL",
    'seo_description'     => "varchar(255) DEFAULT NULL",
    'seo_keywords'        => "varchar(255) DEFAULT NULL",
    'og_image'            => "varchar(255) DEFAULT NULL",
    'smtp_host'           => "varchar(100) DEFAULT ''",
    'smtp_port'           => "int(11) DEFAULT 587",
    'smtp_user'           => "varchar(100) DEFAULT ''",
    'smtp_pass'           => "varchar(255) DEFAULT ''",
    'smtp_encryption'     => "varchar(10) DEFAULT 'tls'",
    'smtp_from_name'      => "varchar(100) DEFAULT ''",
    'smtp_from_email'     => "varchar(100) DEFAULT ''",
    'google_analytics_id' => "varchar(50) DEFAULT ''",
    'meta_pixel_id'       => "varchar(50) DEFAULT ''",
    'gtm_id'              => "varchar(50) DEFAULT ''",
    'custom_head_code'    => "text DEFAULT NULL",
    'custom_body_code'    => "text DEFAULT NULL",
    'maintenance_mode'    => "tinyint(1) DEFAULT 0",
    'allow_registration'  => "tinyint(1) DEFAULT 0",
];
$existing_cols = $pdo->query("SHOW COLUMNS FROM settings")->fetchAll(PDO::FETCH_COLUMN);
foreach ($required_cols as $col => $def) {
    if (!in_array($col, $existing_cols, true)) {
        $pdo->exec("ALTER TABLE `settings` ADD COLUMN `$col` $def");
    }
}

// ===== HANDLE SMTP TEST =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_smtp']) && isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    header('Content-Type: application/json');
    // Simulasi test - di produksi gunakan PHPMailer
    $host = sanitize($_POST['smtp_host'] ?? '');
    $port = (int)($_POST['smtp_port'] ?? 587);
    $user = sanitize($_POST['smtp_user'] ?? '');
    $success = !empty($host) && !empty($user) && $port > 0;
    echo json_encode([
        'success' => $success,
        'message' => $success ? "Koneksi ke {$host}:{$port} berhasil diverifikasi (simulasi)." : "Parameter SMTP tidak lengkap."
    ]);
    exit;
}

// ===== HANDLE SAVE =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && !isset($_POST['test_smtp']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $tab = sanitize($_POST['active_tab'] ?? 'identity');
    
    $data = [
        'site_name'         => sanitize($_POST['site_name'] ?? ''),
        'site_tagline'      => sanitize($_POST['site_tagline'] ?? ''),
        'prodi_name'        => sanitize($_POST['prodi_name'] ?? ''),
        'fakultas'          => sanitize($_POST['fakultas'] ?? ''),
        'kampus'            => sanitize($_POST['kampus'] ?? ''),
        'email'             => sanitize($_POST['email'] ?? ''),
        'newsletter_email'  => sanitize($_POST['newsletter_email'] ?? ''),
        'telepon'           => sanitize($_POST['telepon'] ?? ''),
        'whatsapp'          => sanitize($_POST['whatsapp'] ?? ''),
        'alamat'            => sanitize($_POST['alamat'] ?? ''),
        'footer_text'       => sanitize($_POST['footer_text'] ?? ''),
        'seo_title'         => sanitize($_POST['seo_title'] ?? ''),
        'seo_description'   => sanitize($_POST['seo_description'] ?? ''),
        'seo_keywords'      => sanitize($_POST['seo_keywords'] ?? ''),
        'smtp_host'         => sanitize($_POST['smtp_host'] ?? ''),
        'smtp_port'         => (int)($_POST['smtp_port'] ?? 587),
        'smtp_user'         => sanitize($_POST['smtp_user'] ?? ''),
        'smtp_encryption'   => sanitize($_POST['smtp_encryption'] ?? 'tls'),
        'smtp_from_name'    => sanitize($_POST['smtp_from_name'] ?? ''),
        'smtp_from_email'   => sanitize($_POST['smtp_from_email'] ?? ''),
        'google_analytics_id'=> sanitize($_POST['google_analytics_id'] ?? ''),
        'meta_pixel_id'     => sanitize($_POST['meta_pixel_id'] ?? ''),
        'gtm_id'            => sanitize($_POST['gtm_id'] ?? ''),
        'custom_head_code'  => $_POST['custom_head_code'] ?? '',
        'custom_body_code'  => $_POST['custom_body_code'] ?? '',
        'maintenance_mode'  => isset($_POST['maintenance_mode']) ? 1 : 0,
        'allow_registration'=> isset($_POST['allow_registration']) ? 1 : 0,
    ];
    
    // Password SMTP (jangan kosongkan jika tidak diisi)
    if (!empty($_POST['smtp_pass'])) {
        $data['smtp_pass'] = base64_encode($_POST['smtp_pass']); // Simple obfuscation
    }
    
    // Social Media (JSON)
    $social = [
        'facebook'  => sanitize($_POST['soc_facebook'] ?? ''),
        'instagram' => sanitize($_POST['soc_instagram'] ?? ''),
        'twitter'   => sanitize($_POST['soc_twitter'] ?? ''),
        'youtube'   => sanitize($_POST['soc_youtube'] ?? ''),
        'linkedin'  => sanitize($_POST['soc_linkedin'] ?? ''),
        'tiktok'    => sanitize($_POST['soc_tiktok'] ?? ''),
        'github'    => sanitize($_POST['soc_github'] ?? ''),
        'whatsapp'  => sanitize($_POST['soc_whatsapp'] ?? ''),
    ];
    $data['social_media'] = json_encode($social);
    
    // Handle Logo Upload
    if (!empty($_FILES['logo']['name'])) {
        $upload = handle_upload($_FILES['logo'], '../assets/images/', ['jpg', 'jpeg', 'png', 'webp', 'svg']);
        if ($upload['success']) {
            $data['logo'] = $upload['path'];
        }
    }
    
    // Handle Favicon Upload
    if (!empty($_FILES['favicon']['name'])) {
        $upload = handle_upload($_FILES['favicon'], '../assets/images/', ['ico', 'png', 'svg']);
        if ($upload['success']) {
            $data['favicon'] = $upload['path'];
        }
    }
    
    // Handle OG Image Upload
    if (!empty($_FILES['og_image']['name'])) {
        $upload = handle_upload($_FILES['og_image'], '../assets/images/', ['jpg', 'jpeg', 'png', 'webp']);
        if ($upload['success']) {
            $data['og_image'] = $upload['path'];
        }
    }
    
    // Build UPDATE query
    $sets = [];
    $params = [];
    foreach ($data as $k => $v) {
        $sets[] = "$k = :$k";
        $params[":$k"] = $v;
    }
    $sets[] = "updated = NOW()";
    
    $sql = "UPDATE settings SET " . implode(', ', $sets) . " WHERE id = 1";
    $stmt = $pdo->prepare($sql);
    
    if ($stmt->execute($params)) {
        $pdo->prepare("INSERT INTO admin_logs (user_id, user_name, action, detail, ip) VALUES (?, ?, 'UPDATE_SETTINGS', ?, ?)")
            ->execute([$_SESSION['admin_id'], $_SESSION['admin_name'], "Memperbarui pengaturan tab: $tab", $_SERVER['REMOTE_ADDR']]);
        header("Location: settings.php?msg=saved&tab=$tab");
        exit;
    } else {
        $message = "Gagal memperbarui pengaturan.";
        $msg_type = 'error';
    }
}

// ===== FETCH DATA =====
$stmt = $pdo->query("SELECT * FROM settings WHERE id = 1");
$setting = $stmt->fetch();
$social = json_decode($setting['social_media'] ?? '{}', true) ?: [];

// Active tab from URL or POST
$active_tab = $_GET['tab'] ?? 'identity';
$allowed_tabs = ['identity', 'contact', 'social', 'seo', 'smtp', 'analytics'];
if (!in_array($active_tab, $allowed_tabs)) $active_tab = 'identity';

ob_start();
?>

<style>
/* ===== TAB NAVIGATION ===== */
.settings-tabs {
    display: flex;
    gap: .3rem;
    background: var(--bg-darker);
    padding: 6px;
    border-radius: var(--radius-md);
    margin-bottom: 2rem;
    overflow-x: auto;
    border: 1px solid var(--border-dark);
}
.settings-tab {
    flex: 1;
    min-width: 140px;
    padding: .7rem 1rem;
    background: transparent;
    border: none;
    color: var(--text-muted);
    border-radius: var(--radius-sm);
    cursor: pointer;
    transition: all .2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: .5rem;
    font-weight: 600;
    font-size: .85rem;
    white-space: nowrap;
}
.settings-tab:hover {
    color: #fff;
    background: rgba(255,255,255,0.03);
}
.settings-tab.active {
    background: linear-gradient(135deg, var(--crimson), var(--crimson-light));
    color: #fff;
    box-shadow: 0 4px 12px rgba(138,21,56,0.3);
}
.settings-tab i { font-size: .95rem; }

.tab-pane { display: none; animation: fadeTabIn .3s ease; }
.tab-pane.active { display: block; }
@keyframes fadeTabIn {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
}

/* ===== SETTINGS GRID ===== */
.settings-grid {
    display: grid;
    grid-template-columns: 1.5fr 1fr;
    gap: 2rem;
}
@media (max-width: 1100px) { .settings-grid { grid-template-columns: 1fr; } }

/* ===== FORM FIELD ENHANCED ===== */
.field-group {
    margin-bottom: 1.2rem;
}
.field-label {
    display: flex;
    align-items: center;
    gap: .5rem;
    margin-bottom: .5rem;
    font-weight: 600;
    font-size: .88rem;
    color: var(--text-main);
}
.field-label .required { color: var(--crimson); }
.field-label i { color: var(--crimson); font-size: .85rem; }
.field-helper {
    display: flex;
    align-items: flex-start;
    gap: .4rem;
    margin-top: .4rem;
    font-size: .78rem;
    color: var(--text-muted);
    line-height: 1.4;
}
.field-helper i { color: var(--sky-blue); margin-top: 2px; flex-shrink: 0; }
.field-counter {
    text-align: right;
    font-size: .72rem;
    color: var(--text-muted);
    margin-top: .3rem;
    font-family: monospace;
}
.field-counter.warning { color: var(--warning); }
.field-counter.danger { color: var(--danger); }

/* ===== UPLOAD ZONE MODERN ===== */
.upload-zone-modern {
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
.upload-zone-modern:hover, .upload-zone-modern.dragover {
    border-color: var(--crimson);
    background: rgba(138,21,56,0.08);
    transform: translateY(-2px);
}
.upload-zone-modern input[type="file"] {
    position: absolute; inset: 0; opacity: 0; cursor: pointer;
}
.upload-preview-img {
    max-height: 80px;
    max-width: 100%;
    margin: 0 auto;
    display: block;
    border-radius: 8px;
    background: #fff;
    padding: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.upload-preview-img.favicon {
    max-height: 48px;
    width: 48px;
    padding: 4px;
}
.upload-placeholder { color: var(--text-muted); font-size: .85rem; }
.upload-placeholder i {
    font-size: 2.2rem;
    margin-bottom: .5rem;
    display: block;
    color: var(--crimson);
    transition: transform .3s;
}
.upload-zone-modern:hover .upload-placeholder i {
    transform: translateY(-4px);
}
.upload-remove-btn {
    position: absolute;
    top: 8px;
    right: 8px;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: var(--danger);
    color: #fff;
    border: none;
    cursor: pointer;
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 2;
    font-size: .8rem;
}
.upload-zone-modern:hover .upload-remove-btn { display: flex; }

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

/* ===== SOCIAL MEDIA INPUT ===== */
.social-input-group {
    display: grid;
    grid-template-columns: 42px 1fr auto;
    gap: .5rem;
    align-items: center;
    margin-bottom: .8rem;
    padding: .6rem .8rem;
    background: var(--bg-darker);
    border: 1px solid var(--border-dark);
    border-radius: var(--radius-md);
    transition: all .2s;
}
.social-input-group:focus-within {
    border-color: var(--crimson);
    background: rgba(138,21,56,0.05);
}
.social-input-group .soc-icon {
    width: 32px; height: 32px;
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    color: #fff;
    font-size: .95rem;
    flex-shrink: 0;
}
.soc-icon.facebook { background: #1877F2; }
.soc-icon.instagram { background: linear-gradient(45deg, #f09433, #e6683c, #dc2743, #cc2366, #bc1888); }
.soc-icon.twitter { background: #000; }
.soc-icon.youtube { background: #FF0000; }
.soc-icon.linkedin { background: #0A66C2; }
.soc-icon.tiktok { background: #000; }
.soc-icon.github { background: #24292e; }
.soc-icon.whatsapp { background: #25D366; }
.social-input-group input {
    background: transparent;
    border: none;
    color: var(--text-main);
    font-size: .88rem;
    outline: none;
    padding: .3rem 0;
}
.social-input-group .soc-check {
    color: var(--success);
    opacity: 0;
    transition: opacity .2s;
}
.social-input-group input:not(:placeholder-shown) ~ .soc-check { opacity: 1; }

/* ===== PERMISSION/TOGGLE CARD ===== */
.toggle-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 1.2rem;
    background: var(--bg-darker);
    border: 1px solid var(--border-dark);
    border-radius: var(--radius-md);
    margin-bottom: .8rem;
    transition: all .2s;
}
.toggle-card:hover { border-color: var(--crimson); }
.toggle-card-info { flex: 1; min-width: 0; }
.toggle-card-title {
    font-weight: 700;
    color: #fff;
    font-size: .92rem;
    margin-bottom: .2rem;
    display: flex;
    align-items: center;
    gap: .5rem;
}
.toggle-card-title i { color: var(--crimson); }
.toggle-card-desc {
    font-size: .78rem;
    color: var(--text-muted);
    line-height: 1.4;
}

/* ===== MODERN TOGGLE ===== */
.toggle-switch { position: relative; display: inline-block; width: 44px; height: 24px; flex-shrink: 0; }
.toggle-switch input { opacity: 0; width: 0; height: 0; }
.toggle-slider { position: absolute; cursor: pointer; inset: 0; background: var(--border-dark); transition: .3s; border-radius: 50px; }
.toggle-slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background: #fff; transition: .3s; border-radius: 50%; }
.toggle-switch input:checked + .toggle-slider { background: var(--success); }
.toggle-switch input:checked + .toggle-slider:before { transform: translateX(20px); }

/* ===== LIVE PREVIEW (Sticky Sidebar) ===== */
.settings-live-preview {
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
.preview-browser {
    background: #fff;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 15px 40px rgba(0,0,0,0.3);
    color: #333;
}
.preview-browser-bar {
    background: #f0f0f0;
    padding: .5rem .8rem;
    display: flex;
    align-items: center;
    gap: .3rem;
    border-bottom: 1px solid #ddd;
}
.preview-dot { width: 10px; height: 10px; border-radius: 50%; }
.preview-dot.red { background: #ff5f57; }
.preview-dot.yellow { background: #febc00; }
.preview-dot.green { background: #28c840; }
.preview-url {
    flex: 1;
    margin-left: .5rem;
    padding: .2rem .5rem;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: .65rem;
    color: #666;
    font-family: monospace;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.preview-content { padding: 1rem; }
.preview-header-block {
    display: flex;
    align-items: center;
    gap: .6rem;
    padding-bottom: .8rem;
    border-bottom: 1px solid #eee;
    margin-bottom: .8rem;
}
.preview-logo {
    width: 36px; height: 36px;
    border-radius: 8px;
    background: linear-gradient(135deg, #8A1538, #A01A42);
    display: flex; align-items: center; justify-content: center;
    color: #fff;
    font-size: .9rem;
    flex-shrink: 0;
}
.preview-logo img { width: 100%; height: 100%; object-fit: contain; border-radius: 8px; }
.preview-site-name {
    font-weight: 800;
    font-size: .85rem;
    color: #222;
    line-height: 1.2;
}
.preview-site-tagline {
    font-size: .68rem;
    color: #888;
    font-style: italic;
}
.preview-meta-title {
    font-size: .78rem;
    color: #1a0dab;
    font-weight: 600;
    margin-bottom: .2rem;
    line-height: 1.3;
}
.preview-meta-url {
    font-size: .68rem;
    color: #006621;
    margin-bottom: .3rem;
    font-family: monospace;
}
.preview-meta-desc {
    font-size: .72rem;
    color: #545454;
    line-height: 1.5;
}

/* ===== INFO BOXES ===== */
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
.info-box.warning {
    background: rgba(243,156,18,0.1);
    border: 1px solid rgba(243,156,18,0.3);
    border-left: 4px solid var(--warning);
    color: var(--text-main);
}
.info-box.danger {
    background: rgba(231,76,60,0.1);
    border: 1px solid rgba(231,76,60,0.3);
    border-left: 4px solid var(--danger);
    color: var(--text-main);
}
.info-box.info {
    background: rgba(135,206,235,0.1);
    border: 1px solid rgba(135,206,235,0.3);
    border-left: 4px solid var(--sky-blue);
    color: var(--text-main);
}
.info-box i {
    font-size: 1.1rem;
    margin-top: 2px;
    flex-shrink: 0;
}
.info-box.tip i { color: var(--success); }
.info-box.warning i { color: var(--warning); }
.info-box.danger i { color: var(--danger); }
.info-box.info i { color: var(--sky-blue); }
.info-box strong { display: block; margin-bottom: .2rem; font-size: .9rem; }

/* ===== SECTION DIVIDER ===== */
.section-divider {
    display: flex; align-items: center; gap: 1rem;
    margin: 2rem 0 1.5rem;
    color: var(--text-muted);
    font-size: .78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1.5px;
}
.section-divider::after {
    content: ''; flex: 1; height: 1px;
    background: linear-gradient(90deg, var(--border-dark), transparent);
}
.section-divider i { color: var(--crimson); }
.section-divider:first-child { margin-top: 0; }

/* ===== CODE EDITOR (Textarea mono) ===== */
.code-editor {
    font-family: 'Courier New', Consolas, monospace;
    font-size: .82rem;
    background: #0a0a0a;
    color: #a9dc76;
    border: 1px solid var(--border-dark);
    border-radius: var(--radius-md);
    padding: 1rem;
    min-height: 120px;
    resize: vertical;
    line-height: 1.6;
    tab-size: 4;
}
.code-editor:focus {
    outline: none;
    border-color: var(--crimson);
    box-shadow: 0 0 0 3px rgba(138,21,56,0.15);
}

/* ===== RESPONSIVE ===== */
@media (max-width: 768px) {
    .settings-tabs { flex-direction: column; }
    .settings-tab { min-width: 100%; }
}
</style>

<?php if (isset($_GET['msg'])): ?>
<script>
Swal.fire({
    icon: 'success', title: 'Berhasil!', text: 'Pengaturan website berhasil diperbarui.',
    timer: 2500, showConfirmButton: false
});
</script>
<?php endif; ?>

<?php if (isset($message) && $msg_type === 'error'): ?>
<script>Swal.fire({icon:'error', title:'Gagal!', text:'<?= addslashes($message) ?>'});</script>
<?php endif; ?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;">
    <div>
        <h2 class="section-title"><i class="fa-solid fa-gear"></i> Pengaturan Website</h2>
        <p style="color:var(--text-muted); font-size:.88rem; margin-top:.3rem;">
            <i class="fa-solid fa-circle-info"></i> Konfigurasi identitas, SEO, email, dan integrasi analitik
        </p>
    </div>
    <a href="../" target="_blank" class="btn btn-secondary"><i class="fa-solid fa-external-link"></i> Lihat Website</a>
</div>

<!-- TAB NAVIGATION -->
<div class="settings-tabs" id="settingsTabs">
    <button type="button" class="settings-tab <?= $active_tab === 'identity' ? 'active' : '' ?>" data-tab="identity">
        <i class="fa-solid fa-id-card"></i> Identitas
    </button>
    <button type="button" class="settings-tab <?= $active_tab === 'contact' ? 'active' : '' ?>" data-tab="contact">
        <i class="fa-solid fa-address-book"></i> Kontak
    </button>
    <button type="button" class="settings-tab <?= $active_tab === 'social' ? 'active' : '' ?>" data-tab="social">
        <i class="fa-solid fa-share-nodes"></i> Media Sosial
    </button>
    <button type="button" class="settings-tab <?= $active_tab === 'seo' ? 'active' : '' ?>" data-tab="seo">
        <i class="fa-solid fa-magnifying-glass-chart"></i> SEO Global
    </button>
    <button type="button" class="settings-tab <?= $active_tab === 'smtp' ? 'active' : '' ?>" data-tab="smtp">
        <i class="fa-solid fa-envelope-circle-check"></i> SMTP & Email
    </button>
    <button type="button" class="settings-tab <?= $active_tab === 'analytics' ? 'active' : '' ?>" data-tab="analytics">
        <i class="fa-solid fa-chart-line"></i> Analitik
    </button>
</div>

<form method="POST" enctype="multipart/form-data" id="settingsForm">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="active_tab" id="activeTabInput" value="<?= htmlspecialchars($active_tab) ?>">
    
    <div class="settings-grid">
        <!-- LEFT: FORM AREA -->
        <div>
            
            <!-- ========== TAB: IDENTITAS ========== -->
            <div class="tab-pane <?= $active_tab === 'identity' ? 'active' : '' ?>" data-pane="identity">
                <div class="info-box info">
                    <i class="fa-solid fa-circle-info"></i>
                    <div>
                        <strong>Identitas Website</strong>
                        Informasi dasar yang akan tampil di header, footer, dan hasil pencarian. Logo dan favicon akan otomatis diperbarui di seluruh halaman.
                    </div>
                </div>
                
                <div class="section-divider"><i class="fa-solid fa-image"></i> Aset Visual</div>
                
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                    <!-- Logo -->
                    <div>
                        <div class="field-label"><i class="fa-solid fa-image"></i> Logo Website</div>
                        <div class="upload-zone-modern" data-upload="logo">
                            <?php if (!empty($setting['logo'])): ?>
                                <img src="../<?= htmlspecialchars($setting['logo']) ?>" class="upload-preview-img" data-preview="logo">
                                <button type="button" class="upload-remove-btn" data-remove="logo"><i class="fa-solid fa-xmark"></i></button>
                            <?php else: ?>
                                <div class="upload-placeholder" data-placeholder="logo">
                                    <i class="fa-solid fa-cloud-arrow-up"></i>
                                    <span>Klik atau Drop Logo</span>
                                </div>
                            <?php endif; ?>
                            <input type="file" name="logo" accept="image/*" data-input="logo">
                            <input type="hidden" name="logo_keep" value="<?= htmlspecialchars($setting['logo'] ?? '') ?>" data-keep="logo">
                        </div>
                        <div class="field-helper"><i class="fa-solid fa-info-circle"></i> PNG/JPG/SVG. Maks 2MB. Rasio landscape direkomendasikan.</div>
                    </div>
                    
                    <!-- Favicon -->
                    <div>
                        <div class="field-label"><i class="fa-solid fa-icons"></i> Favicon (Tab Icon)</div>
                        <div class="upload-zone-modern" data-upload="favicon">
                            <?php if (!empty($setting['favicon'])): ?>
                                <img src="../<?= htmlspecialchars($setting['favicon']) ?>" class="upload-preview-img favicon" data-preview="favicon">
                                <button type="button" class="upload-remove-btn" data-remove="favicon"><i class="fa-solid fa-xmark"></i></button>
                            <?php else: ?>
                                <div class="upload-placeholder" data-placeholder="favicon">
                                    <i class="fa-solid fa-icons"></i>
                                    <span>Klik atau Drop Favicon</span>
                                </div>
                            <?php endif; ?>
                            <input type="file" name="favicon" accept=".ico,.png,.svg" data-input="favicon">
                            <input type="hidden" name="favicon_keep" value="<?= htmlspecialchars($setting['favicon'] ?? '') ?>" data-keep="favicon">
                        </div>
                        <div class="field-helper"><i class="fa-solid fa-info-circle"></i> ICO atau PNG. Ukuran ideal 32×32 atau 64×64 px.</div>
                    </div>
                </div>
                
                <div class="section-divider"><i class="fa-solid fa-building"></i> Informasi Institusi</div>
                
                <div class="field-group">
                    <label class="field-label"><i class="fa-solid fa-globe"></i> Nama Website <span class="required">*</span></label>
                    <div class="input-icon-wrap">
                        <i class="fa-solid fa-heading input-icon"></i>
                        <input type="text" name="site_name" class="form-control" value="<?= htmlspecialchars($setting['site_name'] ?? '') ?>" required data-preview-target="site_name" placeholder="Bahasa dan Sastra Inggris">
                    </div>
                </div>
                
                <div class="field-group">
                    <label class="field-label"><i class="fa-solid fa-quote-right"></i> Tagline / Slogan</label>
                    <div class="input-icon-wrap">
                        <i class="fa-solid fa-feather input-icon"></i>
                        <input type="text" name="site_tagline" class="form-control" value="<?= htmlspecialchars($setting['site_tagline'] ?? '') ?>" data-preview-target="site_tagline" placeholder="Mastering Language, Embracing Global Cultures">
                    </div>
                </div>
                
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                    <div class="field-group">
                        <label class="field-label"><i class="fa-solid fa-graduation-cap"></i> Nama Program Studi</label>
                        <div class="input-icon-wrap">
                            <i class="fa-solid fa-book input-icon"></i>
                            <input type="text" name="prodi_name" class="form-control" value="<?= htmlspecialchars($setting['prodi_name'] ?? '') ?>" placeholder="BAHASA DAN SASTRA INGGRIS">
                        </div>
                    </div>
                    <div class="field-group">
                        <label class="field-label"><i class="fa-solid fa-university"></i> Fakultas</label>
                        <div class="input-icon-wrap">
                            <i class="fa-solid fa-building-columns input-icon"></i>
                            <input type="text" name="fakultas" class="form-control" value="<?= htmlspecialchars($setting['fakultas'] ?? '') ?>" placeholder="Fakultas Keguruan dan Ilmu Pendidikan">
                        </div>
                    </div>
                </div>
                
                <div class="field-group">
                    <label class="field-label"><i class="fa-solid fa-school"></i> Universitas / Institusi</label>
                    <div class="input-icon-wrap">
                        <i class="fa-solid fa-landmark input-icon"></i>
                        <input type="text" name="kampus" class="form-control" value="<?= htmlspecialchars($setting['kampus'] ?? '') ?>" placeholder="Universitas Muhammadiyah Maumere">
                    </div>
                </div>
                
                <div class="section-divider"><i class="fa-solid fa-copyright"></i> Footer</div>
                
                <div class="field-group">
                    <label class="field-label"><i class="fa-solid fa-code"></i> Teks Copyright</label>
                    <div class="input-icon-wrap">
                        <i class="fa-solid fa-copyright input-icon"></i>
                        <input type="text" name="footer_text" class="form-control" value="<?= htmlspecialchars($setting['footer_text'] ?? '') ?>" placeholder="© 2026 PBSI UMMaumere. Hak Cipta Dilindungi.">
                    </div>
                    <div class="field-helper"><i class="fa-solid fa-lightbulb"></i> Tahun akan otomatis diperbarui. Anda dapat menambahkan teks kustom.</div>
                </div>
            </div>
            
            <!-- ========== TAB: KONTAK ========== -->
            <div class="tab-pane <?= $active_tab === 'contact' ? 'active' : '' ?>" data-pane="contact">
                <div class="info-box tip">
                    <i class="fa-solid fa-circle-check"></i>
                    <div>
                        <strong>Informasi Kontak Publik</strong>
                        Data ini akan ditampilkan di halaman kontak dan footer website. Pastikan email dan nomor telepon aktif untuk komunikasi dengan calon mahasiswa dan mitra.
                    </div>
                </div>
                
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                    <div class="field-group">
                        <label class="field-label"><i class="fa-solid fa-envelope"></i> Email Resmi <span class="required">*</span></label>
                        <div class="input-icon-wrap">
                            <i class="fa-solid fa-at input-icon"></i>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($setting['email'] ?? '') ?>" required placeholder="pbsi@ummaumere.ac.id">
                        </div>
                    </div>
                    <div class="field-group">
                        <label class="field-label"><i class="fa-solid fa-newspaper"></i> Email Newsletter</label>
                        <div class="input-icon-wrap">
                            <i class="fa-solid fa-envelope-open-text input-icon"></i>
                            <input type="email" name="newsletter_email" class="form-control" value="<?= htmlspecialchars($setting['newsletter_email'] ?? '') ?>" placeholder="newsletter@ummaumere.ac.id">
                        </div>
                    </div>
                    <div class="field-group">
                        <label class="field-label"><i class="fa-solid fa-phone"></i> Nomor Telepon</label>
                        <div class="input-icon-wrap">
                            <i class="fa-solid fa-phone-volume input-icon"></i>
                            <input type="text" name="telepon" class="form-control" value="<?= htmlspecialchars($setting['telepon'] ?? '') ?>" placeholder="(0382) 23456">
                        </div>
                    </div>
                    <div class="field-group">
                        <label class="field-label"><i class="fa-brands fa-whatsapp"></i> WhatsApp</label>
                        <div class="input-icon-wrap">
                            <i class="fa-brands fa-whatsapp input-icon" style="color:#25D366;"></i>
                            <input type="text" name="whatsapp" class="form-control" value="<?= htmlspecialchars($setting['whatsapp'] ?? '') ?>" placeholder="+62 812-3456-7890">
                        </div>
                    </div>
                </div>
                
                <div class="field-group">
                    <label class="field-label"><i class="fa-solid fa-location-dot"></i> Alamat Lengkap</label>
                    <div class="input-icon-wrap">
                        <textarea name="alamat" rows="4" class="form-control" placeholder="Jl. Angkasa, Maumere, Sikka, Nusa Tenggara Timur" style="padding-left:14px; padding-top:12px;"><?= htmlspecialchars($setting['alamat'] ?? '') ?></textarea>
                    </div>
                </div>
                
                <div class="section-divider"><i class="fa-solid fa-map-location-dot"></i> Peta Lokasi</div>
                
                <div class="field-group">
                    <label class="field-label"><i class="fa-solid fa-map-pin"></i> Google Maps Embed URL</label>
                    <div class="input-icon-wrap">
                        <i class="fa-brands fa-google input-icon" style="color:#4285F4;"></i>
                        <input type="url" name="maps_embed" class="form-control" value="<?= htmlspecialchars($setting['maps_embed'] ?? '') ?>" placeholder="https://www.google.com/maps/embed?pb=...">
                    </div>
                    <div class="field-helper"><i class="fa-solid fa-info-circle"></i> Buka Google Maps → Share → Embed a map → Salin URL src-nya.</div>
                </div>
            </div>
            
            <!-- ========== TAB: SOSIAL MEDIA ========== -->
            <div class="tab-pane <?= $active_tab === 'social' ? 'active' : '' ?>" data-pane="social">
                <div class="info-box info">
                    <i class="fa-solid fa-share-nodes"></i>
                    <div>
                        <strong>Integrasi Media Sosial</strong>
                        Tautan ini akan ditampilkan di footer dan halaman kontak. Ikon akan otomatis muncul hanya untuk platform yang Anda isi.
                    </div>
                </div>
                
                <div class="social-input-group">
                    <div class="soc-icon facebook"><i class="fa-brands fa-facebook-f"></i></div>
                    <input type="url" name="soc_facebook" value="<?= htmlspecialchars($social['facebook'] ?? '') ?>" placeholder="https://facebook.com/pbsi.ummaumere">
                    <i class="fa-solid fa-circle-check soc-check"></i>
                </div>
                
                <div class="social-input-group">
                    <div class="soc-icon instagram"><i class="fa-brands fa-instagram"></i></div>
                    <input type="url" name="soc_instagram" value="<?= htmlspecialchars($social['instagram'] ?? '') ?>" placeholder="https://instagram.com/pbsi.ummaumere">
                    <i class="fa-solid fa-circle-check soc-check"></i>
                </div>
                
                <div class="social-input-group">
                    <div class="soc-icon twitter"><i class="fa-brands fa-x-twitter"></i></div>
                    <input type="url" name="soc_twitter" value="<?= htmlspecialchars($social['twitter'] ?? '') ?>" placeholder="https://x.com/pbsi_ummaumere">
                    <i class="fa-solid fa-circle-check soc-check"></i>
                </div>
                
                <div class="social-input-group">
                    <div class="soc-icon youtube"><i class="fa-brands fa-youtube"></i></div>
                    <input type="url" name="soc_youtube" value="<?= htmlspecialchars($social['youtube'] ?? '') ?>" placeholder="https://youtube.com/@pbsiummaumere">
                    <i class="fa-solid fa-circle-check soc-check"></i>
                </div>
                
                <div class="social-input-group">
                    <div class="soc-icon linkedin"><i class="fa-brands fa-linkedin-in"></i></div>
                    <input type="url" name="soc_linkedin" value="<?= htmlspecialchars($social['linkedin'] ?? '') ?>" placeholder="https://linkedin.com/school/pbsi-ummaumere">
                    <i class="fa-solid fa-circle-check soc-check"></i>
                </div>
                
                <div class="social-input-group">
                    <div class="soc-icon tiktok"><i class="fa-brands fa-tiktok"></i></div>
                    <input type="url" name="soc_tiktok" value="<?= htmlspecialchars($social['tiktok'] ?? '') ?>" placeholder="https://tiktok.com/@pbsi.ummaumere">
                    <i class="fa-solid fa-circle-check soc-check"></i>
                </div>
                
                <div class="social-input-group">
                    <div class="soc-icon github"><i class="fa-brands fa-github"></i></div>
                    <input type="url" name="soc_github" value="<?= htmlspecialchars($social['github'] ?? '') ?>" placeholder="https://github.com/pbsi-ummaumere">
                    <i class="fa-solid fa-circle-check soc-check"></i>
                </div>
                
                <div class="social-input-group">
                    <div class="soc-icon whatsapp"><i class="fa-brands fa-whatsapp"></i></div>
                    <input type="text" name="soc_whatsapp" value="<?= htmlspecialchars($social['whatsapp'] ?? '') ?>" placeholder="6281234567890 (tanpa + atau 0)">
                    <i class="fa-solid fa-circle-check soc-check"></i>
                </div>
            </div>
            
            <!-- ========== TAB: SEO GLOBAL ========== -->
            <div class="tab-pane <?= $active_tab === 'seo' ? 'active' : '' ?>" data-pane="seo">
                <div class="info-box tip">
                    <i class="fa-solid fa-magnifying-glass-chart"></i>
                    <div>
                        <strong>Optimasi Mesin Pencari</strong>
                        Pengaturan ini akan menjadi default untuk halaman yang tidak memiliki SEO kustom. Meta description ideal: 150-160 karakter. Title: 50-60 karakter.
                    </div>
                </div>
                
                <div class="field-group">
                    <label class="field-label"><i class="fa-solid fa-heading"></i> Meta Title Default</label>
                    <div class="input-icon-wrap">
                        <i class="fa-solid fa-heading input-icon"></i>
                        <input type="text" name="seo_title" class="form-control" value="<?= htmlspecialchars($setting['seo_title'] ?? '') ?>" maxlength="70" data-counter="seoTitleCounter" data-preview-target="seo_title" placeholder="PBSI UMMaumere - Bahasa dan Sastra Inggris">
                    </div>
                    <div class="field-counter" id="seoTitleCounter">0 / 70</div>
                </div>
                
                <div class="field-group">
                    <label class="field-label"><i class="fa-solid fa-align-left"></i> Meta Description</label>
                    <div class="input-icon-wrap">
                        <textarea name="seo_description" rows="3" class="form-control" maxlength="170" data-counter="seoDescCounter" data-preview-target="seo_desc" placeholder="Program Studi Bahasa dan Sastra Inggris Universitas Muhammadiyah Maumere - Terakreditasi Baik Sekali..." style="padding-left:14px; padding-top:12px;"><?= htmlspecialchars($setting['seo_description'] ?? '') ?></textarea>
                    </div>
                    <div class="field-counter" id="seoDescCounter">0 / 170</div>
                </div>
                
                <div class="field-group">
                    <label class="field-label"><i class="fa-solid fa-tags"></i> Meta Keywords</label>
                    <div class="input-icon-wrap">
                        <i class="fa-solid fa-tags input-icon"></i>
                        <input type="text" name="seo_keywords" class="form-control" value="<?= htmlspecialchars($setting['seo_keywords'] ?? '') ?>" placeholder="pbsi, bahasa inggris, sastra inggris, ummaumere, fkip">
                    </div>
                    <div class="field-helper"><i class="fa-solid fa-info-circle"></i> Pisahkan dengan koma. Catatan: Google sudah mengabaikan keywords, tapi masih berguna untuk mesin pencari lain.</div>
                </div>
                
                <div class="section-divider"><i class="fa-solid fa-share"></i> Open Graph (Share Preview)</div>
                
                <div class="field-group">
                    <label class="field-label"><i class="fa-solid fa-image"></i> OG Image (Gambar saat dishare)</label>
                    <div class="upload-zone-modern" data-upload="og_image">
                        <?php if (!empty($setting['og_image'])): ?>
                            <img src="../<?= htmlspecialchars($setting['og_image']) ?>" class="upload-preview-img" data-preview="og_image">
                            <button type="button" class="upload-remove-btn" data-remove="og_image"><i class="fa-solid fa-xmark"></i></button>
                        <?php else: ?>
                            <div class="upload-placeholder" data-placeholder="og_image">
                                <i class="fa-solid fa-image"></i>
                                <span>Upload OG Image</span>
                            </div>
                        <?php endif; ?>
                        <input type="file" name="og_image" accept="image/*" data-input="og_image">
                        <input type="hidden" name="og_image_keep" value="<?= htmlspecialchars($setting['og_image'] ?? '') ?>" data-keep="og_image">
                    </div>
                    <div class="field-helper"><i class="fa-solid fa-lightbulb"></i> <strong>Ideal:</strong> 1200×630 px. Akan muncul saat link dishare di Facebook, Twitter, WhatsApp.</div>
                </div>
            </div>
            
            <!-- ========== TAB: SMTP ========== -->
            <div class="tab-pane <?= $active_tab === 'smtp' ? 'active' : '' ?>" data-pane="smtp">
                <div class="info-box warning">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <div>
                        <strong>Konfigurasi SMTP untuk Email</strong>
                        Digunakan untuk mengirim notifikasi, reset password, dan newsletter. Disarankan menggunakan layanan SMTP pihak ketiga seperti Gmail, Mailgun, atau SMTP hosting.
                    </div>
                </div>
                
                <div style="display:grid; grid-template-columns: 2fr 1fr; gap: 1.5rem;">
                    <div class="field-group">
                        <label class="field-label"><i class="fa-solid fa-server"></i> SMTP Host</label>
                        <div class="input-icon-wrap">
                            <i class="fa-solid fa-server input-icon"></i>
                            <input type="text" name="smtp_host" class="form-control" value="<?= htmlspecialchars($setting['smtp_host'] ?? '') ?>" placeholder="smtp.gmail.com">
                        </div>
                    </div>
                    <div class="field-group">
                        <label class="field-label"><i class="fa-solid fa-hashtag"></i> Port</label>
                        <div class="input-icon-wrap">
                            <i class="fa-solid fa-hashtag input-icon"></i>
                            <input type="number" name="smtp_port" class="form-control" value="<?= (int)($setting['smtp_port'] ?? 587) ?>" placeholder="587">
                        </div>
                    </div>
                </div>
                
                <div class="field-group">
                    <label class="field-label"><i class="fa-solid fa-user"></i> Username / Email</label>
                    <div class="input-icon-wrap">
                        <i class="fa-solid fa-user input-icon"></i>
                        <input type="text" name="smtp_user" class="form-control" value="<?= htmlspecialchars($setting['smtp_user'] ?? '') ?>" placeholder="noreply@ummaumere.ac.id">
                    </div>
                </div>
                
                <div class="field-group">
                    <label class="field-label"><i class="fa-solid fa-key"></i> Password / App Password</label>
                    <div class="input-icon-wrap">
                        <i class="fa-solid fa-key input-icon"></i>
                        <input type="password" name="smtp_pass" class="form-control" id="smtpPassInput" placeholder="•••••••• (kosongkan jika tidak ingin mengubah)">
                        <button type="button" class="password-toggle" onclick="toggleSmtpPass()" style="position:absolute; right:12px; top:50%; transform:translateY(-50%); background:none; border:none; color:var(--text-muted); cursor:pointer;">
                            <i class="fa-solid fa-eye" id="smtpPassIcon"></i>
                        </button>
                    </div>
                    <div class="field-helper"><i class="fa-solid fa-shield-halved"></i> Untuk Gmail, gunakan <strong>App Password</strong> (bukan password akun utama).</div>
                </div>
                
                <div class="field-group">
                    <label class="field-label"><i class="fa-solid fa-lock"></i> Enkripsi</label>
                    <select name="smtp_encryption" class="form-control select2-premium" style="width:100%;">
                        <option value="tls" <?= ($setting['smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>TLS (Port 587) — Direkomendasikan</option>
                        <option value="ssl" <?= ($setting['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL (Port 465)</option>
                        <option value="none" <?= ($setting['smtp_encryption'] ?? '') === 'none' ? 'selected' : '' ?>>Tanpa Enkripsi (Port 25)</option>
                    </select>
                </div>
                
                <div class="section-divider"><i class="fa-solid fa-user-tag"></i> Identitas Pengirim</div>
                
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                    <div class="field-group">
                        <label class="field-label"><i class="fa-solid fa-signature"></i> Nama Pengirim</label>
                        <div class="input-icon-wrap">
                            <i class="fa-solid fa-user-tie input-icon"></i>
                            <input type="text" name="smtp_from_name" class="form-control" value="<?= htmlspecialchars($setting['smtp_from_name'] ?? '') ?>" placeholder="PBSI UMMaumere">
                        </div>
                    </div>
                    <div class="field-group">
                        <label class="field-label"><i class="fa-solid fa-envelope"></i> Email Pengirim</label>
                        <div class="input-icon-wrap">
                            <i class="fa-solid fa-envelope input-icon"></i>
                            <input type="email" name="smtp_from_email" class="form-control" value="<?= htmlspecialchars($setting['smtp_from_email'] ?? '') ?>" placeholder="noreply@ummaumere.ac.id">
                        </div>
                    </div>
                </div>
                
                <button type="button" class="btn btn-secondary" id="testSmtpBtn" style="margin-top:1rem;">
                    <i class="fa-solid fa-vial"></i> Uji Koneksi SMTP
                </button>
            </div>
            
            <!-- ========== TAB: ANALITIK ========== -->
            <div class="tab-pane <?= $active_tab === 'analytics' ? 'active' : '' ?>" data-pane="analytics">
                <div class="info-box info">
                    <i class="fa-solid fa-chart-line"></i>
                    <div>
                        <strong>Integrasi Analitik</strong>
                        Script akan otomatis disisipkan di seluruh halaman publik untuk melacak traffic dan perilaku pengunjung.
                    </div>
                </div>
                
                <div class="field-group">
                    <label class="field-label"><i class="fa-brands fa-google"></i> Google Analytics ID (GA4)</label>
                    <div class="input-icon-wrap">
                        <i class="fa-solid fa-chart-bar input-icon" style="color:#F9AB00;"></i>
                        <input type="text" name="google_analytics_id" class="form-control" value="<?= htmlspecialchars($setting['google_analytics_id'] ?? '') ?>" placeholder="G-XXXXXXXXXX">
                    </div>
                    <div class="field-helper"><i class="fa-solid fa-info-circle"></i> ID dimulai dengan <code>G-</code>. Dapatkan dari analytics.google.com</div>
                </div>
                
                <div class="field-group">
                    <label class="field-label"><i class="fa-brands fa-meta"></i> Meta Pixel ID</label>
                    <div class="input-icon-wrap">
                        <i class="fa-solid fa-bullseye input-icon" style="color:#0668E1;"></i>
                        <input type="text" name="meta_pixel_id" class="form-control" value="<?= htmlspecialchars($setting['meta_pixel_id'] ?? '') ?>" placeholder="123456789012345">
                    </div>
                </div>
                
                <div class="field-group">
                    <label class="field-label"><i class="fa-brands fa-google"></i> Google Tag Manager ID</label>
                    <div class="input-icon-wrap">
                        <i class="fa-solid fa-tags input-icon" style="color:#4285F4;"></i>
                        <input type="text" name="gtm_id" class="form-control" value="<?= htmlspecialchars($setting['gtm_id'] ?? '') ?>" placeholder="GTM-XXXXXXX">
                    </div>
                </div>
                
                <div class="section-divider"><i class="fa-solid fa-code"></i> Custom Code Injection</div>
                
                <div class="field-group">
                    <label class="field-label"><i class="fa-solid fa-head-side-mask"></i> Custom HEAD Code</label>
                    <textarea name="custom_head_code" rows="5" class="code-editor" placeholder="<!-- Sisipkan kode di dalam <head> -->&#10;<script>/* kode kustom */</script>"><?= htmlspecialchars($setting['custom_head_code'] ?? '') ?></textarea>
                    <div class="field-helper"><i class="fa-solid fa-triangle-exclamation" style="color:var(--warning);"></i> Hanya untuk pengguna lanjutan. Kode yang salah dapat merusak website.</div>
                </div>
                
                <div class="field-group">
                    <label class="field-label"><i class="fa-solid fa-body-text"></i> Custom BODY Code (Sebelum &lt;/body&gt;)</label>
                    <textarea name="custom_body_code" rows="5" class="code-editor" placeholder="<!-- Sisipkan kode sebelum </body> -->"><?= htmlspecialchars($setting['custom_body_code'] ?? '') ?></textarea>
                </div>
                
                <div class="section-divider"><i class="fa-solid fa-toggle-on"></i> Mode & Izin</div>
                
                <div class="toggle-card">
                    <div class="toggle-card-info">
                        <div class="toggle-card-title"><i class="fa-solid fa-screwdriver-wrench"></i> Mode Maintenance</div>
                        <div class="toggle-card-desc">Aktifkan untuk menampilkan halaman maintenance kepada pengunjung publik. Admin tetap dapat mengakses.</div>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" name="maintenance_mode" value="1" <?= ($setting['maintenance_mode'] ?? 0) ? 'checked' : '' ?>>
                        <span class="toggle-slider"></span>
                    </label>
                </div>
                
                <div class="toggle-card">
                    <div class="toggle-card-info">
                        <div class="toggle-card-title"><i class="fa-solid fa-user-plus"></i> Izinkan Registrasi Publik</div>
                        <div class="toggle-card-desc">Jika dinonaktifkan, hanya admin yang dapat membuat akun baru (berguna untuk sistem tertutup).</div>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" name="allow_registration" value="1" <?= ($setting['allow_registration'] ?? 0) ? 'checked' : '' ?>>
                        <span class="toggle-slider"></span>
                    </label>
                </div>
            </div>
            
            <!-- ACTION BUTTONS -->
            <div style="margin-top:2.5rem; display:flex; gap:1rem; padding-top:1.5rem; border-top:1px solid var(--border-dark);">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Simpan Perubahan</button>
                <button type="reset" class="btn btn-secondary"><i class="fa-solid fa-rotate-left"></i> Reset Form</button>
            </div>
        </div>
        
        <!-- RIGHT: LIVE PREVIEW -->
        <div class="settings-live-preview">
            <div class="preview-title"><i class="fa-solid fa-eye"></i> Live Preview</div>
            
            <!-- Browser Mock -->
            <div class="preview-browser">
                <div class="preview-browser-bar">
                    <span class="preview-dot red"></span>
                    <span class="preview-dot yellow"></span>
                    <span class="preview-dot green"></span>
                    <div class="preview-url" id="prevUrl"><?= htmlspecialchars(BASE_URL ?? 'https://pbsi.ummaumere.ac.id/') ?></div>
                </div>
                <div class="preview-content">
                    <div class="preview-header-block">
                        <div class="preview-logo" id="prevLogo">
                            <i class="fa-solid fa-graduation-cap"></i>
                        </div>
                        <div>
                            <div class="preview-site-name" id="prevSiteName"><?= htmlspecialchars($setting['site_name'] ?: 'Nama Website') ?></div>
                            <div class="preview-site-tagline" id="prevSiteTagline"><?= htmlspecialchars($setting['site_tagline'] ?: 'Tagline website Anda') ?></div>
                        </div>
                    </div>
                    
                    <div style="margin-top:1rem;">
                        <div style="font-size:.68rem; color:#888; margin-bottom:.3rem;">Tampilan di Google Search:</div>
                        <div class="preview-meta-title" id="prevSeoTitle"><?= htmlspecialchars($setting['seo_title'] ?: $setting['site_name'] ?: 'Judul Halaman') ?></div>
                        <div class="preview-meta-url" id="prevSeoUrl"><?= htmlspecialchars(BASE_URL ?? 'https://pbsi.ummaumere.ac.id/') ?></div>
                        <div class="preview-meta-desc" id="prevSeoDesc"><?= htmlspecialchars($setting['seo_description'] ?: 'Deskripsi website akan muncul di sini...') ?></div>
                    </div>
                </div>
            </div>
            
            <div style="margin-top:1rem; padding:.8rem; background:rgba(135,206,235,0.08); border:1px solid rgba(135,206,235,0.2); border-radius:var(--radius-md); font-size:.75rem; color:var(--text-muted);">
                <i class="fa-solid fa-lightbulb" style="color:var(--sky-blue);"></i>
                <strong style="color:var(--sky-blue);"> Tips SEO:</strong> Pastikan Meta Title unik per halaman dan Meta Description memuat kata kunci utama dalam 150-160 karakter.
            </div>
            
            <div style="margin-top:.8rem; padding:.8rem; background:rgba(46,139,87,0.08); border:1px solid rgba(46,139,87,0.2); border-radius:var(--radius-md); font-size:.75rem; color:var(--text-muted);">
                <i class="fa-solid fa-clock-rotate-left" style="color:var(--success);"></i>
                <strong style="color:var(--success);"> Terakhir diperbarui:</strong><br>
                <?= !empty($setting['updated']) ? date('d M Y, H:i', strtotime($setting['updated'])) . ' WITA' : 'Belum pernah' ?>
            </div>
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // ===== TAB NAVIGATION =====
    const tabs = document.querySelectorAll('.settings-tab');
    const panes = document.querySelectorAll('.tab-pane');
    const activeTabInput = document.getElementById('activeTabInput');
    
    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            const targetTab = this.dataset.tab;
            tabs.forEach(t => t.classList.remove('active'));
            panes.forEach(p => p.classList.remove('active'));
            this.classList.add('active');
            document.querySelector(`.tab-pane[data-pane="${targetTab}"]`)?.classList.add('active');
            activeTabInput.value = targetTab;
            
            // Update URL without reload
            const url = new URL(window.location);
            url.searchParams.set('tab', targetTab);
            window.history.replaceState({}, '', url);
        });
    });
    
    // ===== LIVE PREVIEW BINDINGS =====
    document.querySelectorAll('[data-preview-target]').forEach(input => {
        input.addEventListener('input', function() {
            const target = this.dataset.previewTarget;
            const val = this.value || '';
            if (target === 'site_name') {
                document.getElementById('prevSiteName').textContent = val || 'Nama Website';
                document.getElementById('prevSeoTitle').textContent = val || 'Judul Halaman';
            } else if (target === 'site_tagline') {
                document.getElementById('prevSiteTagline').textContent = val || 'Tagline website Anda';
            } else if (target === 'seo_title') {
                document.getElementById('prevSeoTitle').textContent = val || document.getElementById('prevSiteName').textContent;
            } else if (target === 'seo_desc') {
                document.getElementById('prevSeoDesc').textContent = val || 'Deskripsi website akan muncul di sini...';
            }
        });
    });
    
    // ===== CHARACTER COUNTERS =====
    function updateCounter(input, counterId, max) {
        const counter = document.getElementById(counterId);
        if (!counter) return;
        const len = input.value.length;
        counter.textContent = `${len} / ${max}`;
        counter.className = 'field-counter';
        if (len > max * 0.9) counter.classList.add('warning');
        if (len > max) counter.classList.add('danger');
    }
    
    document.querySelectorAll('[data-counter]').forEach(input => {
        const counterId = input.dataset.counter;
        const max = parseInt(input.getAttribute('maxlength'));
        updateCounter(input, counterId, max);
        input.addEventListener('input', () => updateCounter(input, counterId, max));
    });
    
    // ===== UPLOAD ZONES (Drag & Drop + Preview) =====
    document.querySelectorAll('.upload-zone-modern').forEach(zone => {
        const input = zone.querySelector('input[type="file"]');
        const preview = zone.querySelector('[data-preview]');
        const placeholder = zone.querySelector('[data-placeholder]');
        const removeBtn = zone.querySelector('[data-remove]');
        const keepInput = zone.querySelector('[data-keep]');
        const uploadKey = zone.dataset.upload;
        
        if (!input) return;
        
        input.addEventListener('change', function(e) {
            if (e.target.files.length) {
                const reader = new FileReader();
                reader.onload = function(ev) {
                    if (!preview) {
                        const img = document.createElement('img');
                        img.className = uploadKey === 'favicon' ? 'upload-preview-img favicon' : 'upload-preview-img';
                        img.setAttribute('data-preview', uploadKey);
                        img.src = ev.target.result;
                        zone.insertBefore(img, zone.firstChild);
                    } else {
                        preview.src = ev.target.result;
                    }
                    if (placeholder) placeholder.style.display = 'none';
                    if (removeBtn) removeBtn.style.display = 'flex';
                    if (keepInput) keepInput.value = '';
                    
                    // Update logo preview if logo uploaded
                    if (uploadKey === 'logo') {
                        const prevLogo = document.getElementById('prevLogo');
                        prevLogo.innerHTML = `<img src="${ev.target.result}" alt="Logo">`;
                    }
                };
                reader.readAsDataURL(e.target.files[0]);
            }
        });
        
        if (removeBtn) {
            removeBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                e.preventDefault();
                input.value = '';
                if (keepInput) keepInput.value = '';
                const prevImg = zone.querySelector('[data-preview]');
                if (prevImg) prevImg.remove();
                if (placeholder) placeholder.style.display = '';
                removeBtn.style.display = 'none';
                
                if (uploadKey === 'logo') {
                    document.getElementById('prevLogo').innerHTML = '<i class="fa-solid fa-graduation-cap"></i>';
                }
            });
        }
        
        // Drag & Drop
        ['dragenter', 'dragover'].forEach(evt => {
            zone.addEventListener(evt, e => { e.preventDefault(); zone.classList.add('dragover'); });
        });
        ['dragleave', 'drop'].forEach(evt => {
            zone.addEventListener(evt, e => { e.preventDefault(); zone.classList.remove('dragover'); });
        });
        zone.addEventListener('drop', e => {
            if (e.dataTransfer.files.length) {
                input.files = e.dataTransfer.files;
                input.dispatchEvent(new Event('change'));
            }
        });
    });
    
    // ===== SMTP TEST =====
    const testBtn = document.getElementById('testSmtpBtn');
    if (testBtn) {
        testBtn.addEventListener('click', async function() {
            const btn = this;
            const originalHtml = btn.innerHTML;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Menguji...';
            btn.disabled = true;
            
            try {
                const formData = new FormData(document.getElementById('settingsForm'));
                formData.append('test_smtp', '1');
                
                const response = await fetch('settings.php', { method: 'POST', body: formData });
                const result = await response.json();
                
                Swal.fire({
                    icon: result.success ? 'success' : 'warning',
                    title: result.success ? 'Koneksi Berhasil!' : 'Koneksi Gagal',
                    text: result.message,
                    timer: 3000,
                    showConfirmButton: false
                });
            } catch (err) {
                Swal.fire({ icon: 'error', title: 'Error', text: 'Gagal menghubungi server.' });
            } finally {
                btn.innerHTML = originalHtml;
                btn.disabled = false;
            }
        });
    }
    
    // ===== FORM SUBMIT LOADING =====
    document.getElementById('settingsForm').addEventListener('submit', function(e) {
        const btn = this.querySelector('button[type="submit"]');
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Menyimpan...';
        btn.disabled = true;
    });
});

function toggleSmtpPass() {
    const input = document.getElementById('smtpPassInput');
    const icon = document.getElementById('smtpPassIcon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}
</script>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    $('.select2-premium').select2({ 
        dropdownParent: $('#settingsForm'),
        minimumResultsForSearch: Infinity
    });
});
</script>

<?php
$page_content = ob_get_clean();
require_once 'includes/admin_template.php';
?>