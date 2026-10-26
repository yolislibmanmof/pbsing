<?php
if (!function_exists('get_all')) { require_once __DIR__ . '/functions.php'; }
$setting = get_all('settings', 'id = 1')[0];
// ===== NAVBAR SUMBER: FILE KONFIGURASI (includes/navbar_menu.php) =====
$navbar_config = require __DIR__ . '/navbar_menu.php';
$menus = [];
$menu_children = [];
$__nid = 0;
foreach ($navbar_config as $__item) {
    if (!empty($__item['hidden'])) continue;
    $__nid++;
    $__pid = $__nid;
    $menus[] = [
        'id'    => $__pid,
        'label' => $__item['label'],
        'url'   => $__item['url'],
        'icon'  => $__item['icon'] ?? 'fa-solid fa-circle',
    ];
    foreach (($__item['children'] ?? []) as $__child) {
        if (!empty($__child['hidden'])) continue;
        $__nid++;
        $menu_children[$__pid][] = [
            'id'    => $__nid,
            'label' => $__child['label'],
            'url'   => $__child['url'],
            'icon'  => $__child['icon'] ?? 'fa-solid fa-angle-right',
        ];
    }
}
$page_title = $page_title ?? $setting['site_name'];
$current_page = $current_page ?? 'home';
$current_lang = $_COOKIE['pbsi_lang'] ?? 'id';

function menu_url($url) {
    return ($url === '' || $url === '#') ? '#' : PUBLIC_URL . sanitize($url);
}

function is_menu_active($menu, $current_page, $children = []) {
    $url = $menu['url'] ?? '';
    if (strpos($url, $current_page . '.php') !== false) return true;
    if ($current_page === 'home' && ($url === 'index.php' || $url === '')) return true;
    foreach ($children as $child) {
        if (strpos($child['url'] ?? '', $current_page . '.php') !== false) return true;
    }
    return false;
}
?>
<!DOCTYPE html>
<html lang="<?= $current_lang ?>" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="<?= sanitize($setting['seo_description'] ?? $setting['site_tagline'] ?? '') ?>">
<meta name="keywords" content="<?= sanitize($setting['seo_keywords'] ?? '') ?>">
<meta name="author" content="<?= sanitize($setting['site_name']) ?>">
<meta property="og:title" content="<?= sanitize($page_title) ?> | <?= sanitize($setting['site_name']) ?>">
<meta property="og:description" content="<?= sanitize($setting['seo_description'] ?? $setting['site_tagline'] ?? '') ?>">
<meta property="og:image" content="<?= !empty($setting['og_image']) ? BASE_URL . sanitize($setting['og_image']) : '' ?>">
<meta property="og:type" content="website">
<meta name="twitter:card" content="summary_large_image">
<title><?= sanitize($page_title) ?> | <?= sanitize($setting['site_name']) ?></title>

<?php if (!empty($setting['favicon'])): ?>
<link rel="icon" type="image/x-icon" href="<?= BASE_URL . sanitize($setting['favicon']) ?>">
<link rel="shortcut icon" type="image/png" href="<?= BASE_URL . sanitize($setting['favicon']) ?>">
<link rel="apple-touch-icon" href="<?= BASE_URL . sanitize($setting['favicon']) ?>">
<?php endif; ?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Playfair+Display:wght@700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= PUBLIC_URL ?>assets/css/public.css?v=<?= (@filemtime(__DIR__ . '/../public/assets/css/public.css') ?: time()) ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

<?php if (!empty($setting['logo'])): ?>
<link rel="preload" as="image" href="<?= BASE_URL . sanitize($setting['logo']) ?>">
<?php endif; ?>
</head>
<body class="page-<?= htmlspecialchars($current_page) ?>">

<!-- ============================================================
     PRELOADER v2 CINEMATIC
============================================================= -->
<div id="preloader" aria-hidden="true" aria-label="Loading">
    <div class="pl-panel pl-panel-top"></div>
    <div class="pl-panel pl-panel-bottom"></div>
    <div class="pl-seam"></div>
    <div class="pl-core">
        <div class="pl-emblem">
            <svg viewBox="0 0 140 140" class="pl-svg" aria-hidden="true">
                <defs>
                    <linearGradient id="plGrad" x1="0" y1="0" x2="1" y2="1">
                        <stop offset="0" stop-color="#8A1538"/>
                        <stop offset=".5" stop-color="#87CEEB"/>
                        <stop offset="1" stop-color="#2E8B57"/>
                    </linearGradient>
                </defs>
                <circle class="pl-ring-track" cx="70" cy="70" r="58"/>
                <circle class="pl-ring-dash" cx="70" cy="70" r="58"/>
                <path class="pl-cap" d="M70 44 L104 58 L70 72 L36 58 Z"/>
                <path class="pl-cap2" d="M52 66 v14 c0 6 8 10 18 10 s18 -4 18 -10 v-14"/>
                <path class="pl-tassel" d="M104 58 v16"/>
                <circle class="pl-orbit" cx="70" cy="12" r="4"/>
            </svg>
        </div>
        <div class="pl-title">PBSING<span>UNIMOF</span></div>
        <div class="pl-sub">PROGRAM STUDI BAHASA &amp; SASTRA INGGRIS</div>
        <div class="pl-track">
            <div class="pl-fill"></div>
            <div class="pl-glow"></div>
        </div>
        <div class="pl-meta">
            <span class="pl-status">INITIALIZING</span>
            <span class="pl-percent">0%</span>
        </div>
    </div>
</div>

<style>
/* ===== PRELOADER v2 ===== */
html.pl-locked, html.pl-locked body { overflow: hidden !important; }
#preloader { position: fixed; inset: 0; z-index: 99999; background: #0a0a0a; display: block; transition: none; }
.pl-panel { position: absolute; left: 0; width: 100%; height: 50.4%; background: #0a0a0a; transition: transform .9s cubic-bezier(.77,0,.18,1) .1s; will-change: transform; }
.pl-panel-top { top: 0; }
.pl-panel-bottom { bottom: 0; }
.pl-seam { position: absolute; top: 50%; left: 0; width: 100%; height: 2px; transform: translateY(-50%) scaleX(0); opacity: 0; background: linear-gradient(90deg, transparent, var(--crimson), var(--sky-blue), var(--emerald), transparent); transition: transform .7s cubic-bezier(.22,.61,.36,1), opacity .4s; }
#preloader.pl-ready .pl-seam { transform: translateY(-50%) scaleX(1); opacity: 1; }
.pl-core { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; transition: opacity .35s ease, transform .35s ease; }
#preloader.pl-exit .pl-core { opacity: 0; transform: scale(.94); }
#preloader.pl-exit .pl-panel-top { transform: translateY(-101%); }
#preloader.pl-exit .pl-panel-bottom { transform: translateY(101%); }
#preloader.pl-exit .pl-seam { opacity: 0; }
#preloader.pl-exit { pointer-events: none; }
#preloader.pl-gone { display: none !important; }
@keyframes plKill { to { opacity: 0; visibility: hidden; } }
#preloader.hidden { animation: plKill .4s ease 1s forwards; }
.pl-emblem { width: 122px; height: 122px; margin-bottom: 1.3rem; }
.pl-svg { width: 100%; height: 100%; overflow: visible; }
.pl-ring-track { fill: none; stroke: rgba(255,255,255,.08); stroke-width: 2; }
.pl-ring-dash { fill: none; stroke: url(#plGrad); stroke-width: 3; stroke-linecap: round; stroke-dasharray: 70 295; transform-origin: 70px 70px; animation: plSpin 1.5s linear infinite; filter: drop-shadow(0 0 6px rgba(135,206,235,.45)); }
.pl-cap, .pl-cap2, .pl-tassel { fill: none; stroke: #fff; stroke-width: 3; stroke-linecap: round; stroke-linejoin: round; }
.pl-cap { stroke-dasharray: 150; stroke-dashoffset: 150; animation: plDraw .8s cubic-bezier(.65,0,.35,1) .25s forwards; }
.pl-cap2 { stroke-dasharray: 90; stroke-dashoffset: 90; animation: plDraw .7s cubic-bezier(.65,0,.35,1) .75s forwards; }
.pl-tassel { stroke: var(--sky-blue); stroke-dasharray: 20; stroke-dashoffset: 20; animation: plDraw .4s ease-out 1.15s forwards; }
.pl-orbit { fill: var(--crimson); transform-origin: 70px 70px; animation: plSpin 3s linear infinite; filter: drop-shadow(0 0 7px rgba(138,21,56,.9)); }
@keyframes plSpin { to { transform: rotate(360deg); } }
@keyframes plDraw { to { stroke-dashoffset: 0; } }
.pl-title { font-family: 'Playfair Display', serif; font-size: 2.3rem; font-weight: 900; letter-spacing: 3px; color: #fff; line-height: 1; text-align: center; }
.pl-title span { display: block; font-family: 'Inter', sans-serif; font-size: .78rem; font-weight: 600; letter-spacing: 7px; color: var(--sky-blue); margin-top: .45rem; }
.pl-sub { margin-top: .95rem; font-size: .66rem; letter-spacing: 4.5px; color: #6b6b6b; text-transform: uppercase; animation: plFade 1s ease .5s both; }
@keyframes plFade { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }
.pl-track { margin-top: 1.7rem; width: min(330px, 72vw); height: 3px; background: rgba(255,255,255,.08); border-radius: 99px; position: relative; }
.pl-fill { position: absolute; left: 0; top: 0; height: 100%; width: 0%; border-radius: 99px; background: linear-gradient(90deg, var(--crimson), var(--sky-blue), var(--emerald)); box-shadow: 0 0 14px rgba(135,206,235,.55); transition: width .22s cubic-bezier(.22,.61,.36,1); }
.pl-glow { position: absolute; top: 50%; left: 0%; width: 9px; height: 9px; border-radius: 50%; background: #fff; transform: translate(-50%,-50%); box-shadow: 0 0 16px 4px rgba(255,255,255,.6); transition: left .22s cubic-bezier(.22,.61,.36,1); }
.pl-meta { margin-top: .95rem; width: min(330px, 72vw); display: flex; justify-content: space-between; align-items: center; font-size: .66rem; letter-spacing: 2.5px; color: #8a8a8a; text-transform: uppercase; }
.pl-percent { color: var(--sky-blue); font-weight: 800; font-variant-numeric: tabular-nums; font-size: .8rem; }
.pl-status::after { content: ''; display: inline-block; width: 6px; height: 11px; background: var(--crimson); margin-left: 7px; vertical-align: -1px; animation: plBlink .9s steps(2) infinite; }
@keyframes plBlink { 50% { opacity: 0; } }

/* ===== NAVBAR PREMIUM ===== */
.navbar { font-family: 'Inter', sans-serif; }
.nav-link.active { color: var(--crimson) !important; font-weight: 700; }
.nav-link.active::after { width: 100%; }

.lang-switcher {
    display: inline-flex;
    background: rgba(255,255,255,0.08);
    border: 1px solid rgba(255,255,255,0.15);
    border-radius: 50px;
    padding: 3px;
    gap: 2px;
    backdrop-filter: blur(10px);
    flex-shrink: 0;
}
.lang-btn {
    padding: 5px 12px;
    border-radius: 50px;
    font-size: .72rem;
    font-weight: 700;
    color: rgba(255,255,255,0.6);
    cursor: pointer;
    transition: all .3s ease;
    letter-spacing: .5px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: transparent;
    border: none;
}
.lang-btn:hover { color: #fff; }
.lang-btn.active {
    background: linear-gradient(135deg, var(--crimson), var(--crimson-light));
    color: #fff;
    box-shadow: 0 2px 8px rgba(138,21,56,0.4);
}
.lang-btn img { width: 14px; height: 10px; border-radius: 2px; }

.nav-search-trigger {
    width: 40px; height: 40px;
    border-radius: 50%;
    background: rgba(255,255,255,0.08);
    border: 1px solid rgba(255,255,255,0.15);
    color: #fff;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all .3s ease;
    backdrop-filter: blur(10px);
    flex-shrink: 0;
}
.nav-search-trigger:hover { background: var(--crimson); border-color: var(--crimson); transform: scale(1.1); }

.theme-toggle {
    width: 40px; height: 40px;
    border-radius: 50%;
    background: rgba(255,255,255,0.08);
    border: 1px solid rgba(255,255,255,0.15);
    color: #fff;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all .3s ease;
    backdrop-filter: blur(10px);
    flex-shrink: 0;
}
.theme-toggle:hover { background: var(--sky-blue); color: var(--rich-black); transform: rotate(20deg) scale(1.1); }

.nav-actions {
    display: flex;
    align-items: center;
    gap: .5rem;
    margin-left: .75rem;
    flex-shrink: 0;
}

.navbar.scrolled .lang-switcher,
.navbar.scrolled .nav-search-trigger,
.navbar.scrolled .theme-toggle {
    background: rgba(18,18,18,0.6);
    border-color: rgba(255,255,255,0.1);
}

.nav-link { display: inline-flex !important; align-items: center; gap: 0.5rem; white-space: nowrap; }
.nav-link span { white-space: nowrap; }
.nav-icon { font-size: 1rem; color: var(--sky-blue); transition: all .3s ease; width: 20px; text-align: center; }
.nav-link:hover .nav-icon, .nav-link.active .nav-icon {
    color: var(--crimson);
    transform: scale(1.2) rotate(10deg);
    filter: drop-shadow(0 0 6px rgba(138,21,56,.5));
}
.dropdown li a { display: flex !important; align-items: center; gap: 0.8rem; }
.dropdown-icon { font-size: .9rem; color: var(--crimson); width: 20px; text-align: center; transition: transform .3s ease; }
.dropdown li a:hover .dropdown-icon { transform: scale(1.2) rotate(-10deg); color: var(--sky-blue); }

.mobile-drawer-backdrop {
    position: fixed; inset: 0;
    background: rgba(0,0,0,0.5);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    z-index: 998;
    opacity: 0; visibility: hidden;
    transition: all .3s ease;
}
.mobile-drawer-backdrop.active { opacity: 1; visibility: visible; }

@media (max-width: 900px) {
    .nav-icon, .dropdown-icon { display: none; }
    .nav-link, .dropdown li a { justify-content: flex-start; }
    .lang-switcher, .nav-search-trigger, .theme-toggle { display: none; }
    .nav-actions { display: none; }
    .nav-menu {
        position: fixed;
        top: 0; right: -100%;
        height: 100vh;
        width: min(320px, 85vw);
        background: var(--rich-black);
        flex-direction: column;
        align-items: stretch;
        padding: 5rem 1.5rem 2rem;
        gap: .5rem;
        transition: right .4s cubic-bezier(0.4, 0, 0.2, 1);
        border-left: 2px solid var(--crimson);
        overflow-y: auto;
        z-index: 999;
    }
    .nav-menu.active { right: 0; }
    .nav-menu .lang-switcher-mobile {
        display: flex;
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px solid rgba(255,255,255,0.1);
    }
}
@media (min-width: 901px) {
    .lang-switcher-mobile { display: none !important; }
}

.cmd-hint {
    position: fixed; bottom: 30px; left: 30px;
    padding: .5rem 1rem;
    background: rgba(10,10,10,0.9);
    color: #fff; border-radius: 50px;
    font-size: .78rem; font-weight: 600;
    z-index: 100;
    display: flex; align-items: center; gap: .5rem;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    border: 1px solid rgba(138,21,56,0.3);
    opacity: 0; transform: translateY(20px);
    transition: all .4s ease;
    pointer-events: none;
}
.cmd-hint.visible { opacity: 1; transform: translateY(0); pointer-events: auto; }
.cmd-hint kbd { background: var(--crimson); padding: 2px 8px; border-radius: 4px; font-family: monospace; font-size: .7rem; font-weight: 700; }

.floating-wa {
    position: fixed; bottom: 100px; right: 30px;
    width: 55px; height: 55px; border-radius: 50%;
    background: #25D366; color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.6rem; text-decoration: none;
    z-index: 998;
    box-shadow: 0 10px 30px rgba(37,211,102,0.4);
    transition: all .3s ease;
}
.floating-wa:hover { transform: translateY(-5px) scale(1.1); box-shadow: 0 15px 40px rgba(37,211,102,0.6); }
.floating-wa::before { content: ''; position: absolute; inset: -6px; border: 2px solid #25D366; border-radius: 50%; animation: waRipple 2s ease-out infinite; }
@keyframes waRipple { 0% { transform: scale(1); opacity: 1; } 100% { transform: scale(1.4); opacity: 0; } }
@media (max-width: 768px) {
    .floating-wa { bottom: 90px; right: 20px; width: 50px; height: 50px; font-size: 1.4rem; }
    .cmd-hint { display: none; }
}
</style>

<style>
/* ===== BRAND / LOGO ===== */
.nav-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 2rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.2rem;
}
.nav-brand { display: flex; align-items: center; gap: .75rem; min-width: 0; margin-right: .5rem; flex-shrink: 0; }
.brand-logo-wrap { flex-shrink: 0; display: flex; align-items: center; }
.brand-logo-img {
    width: 46px; height: 46px; border-radius: 50%; object-fit: cover; display: block;
    border: 2px solid rgba(255,255,255,.18);
    box-shadow: 0 4px 14px rgba(0,0,0,.35);
    background: rgba(255,255,255,.06);
}
.brand-text-stack { display: flex; flex-direction: column; line-height: 1.15; white-space: nowrap; }
.brand-title { color: #fff; font-weight: 800; font-size: 1.05rem; letter-spacing: .3px; }
.brand-title em { color: var(--sky-blue); font-style: normal; font-weight: 600; }
.brand-subtitle {
    color: rgba(255,255,255,.55); font-size: .55rem; letter-spacing: .8px;
    text-transform: uppercase; font-weight: 600; margin-top: 2px;
}
.navbar.scrolled .brand-logo-img { width: 40px; height: 40px; }
.navbar.scrolled .brand-subtitle { display: none; }

/* ===== MENU: TIDAK BOLEH DIPAKSA MENYUSUT (biar fitter yang kerja) ===== */
.nav-menu {
    display: flex;
    align-items: center;
    gap: 1.8rem;
    flex-wrap: nowrap;
    margin: 0; padding: 0;
    list-style: none;
    min-width: auto;
}
.nav-item { position: relative; }

/* Kelas darurat saat fitter merapatkan jarak */
.navbar.nav-fit-tight .nav-menu { gap: 1.05rem; }
.navbar.nav-fit-tight .nav-link { font-size: .88rem; }
.navbar.nav-fit-tight .nav-container { padding: 0 1rem; gap: .8rem; }

@media (max-width: 560px) {
    .brand-title { font-size: .95rem; }
    .nav-container { padding: 0 1rem; }
}
</style>

<!-- ============================================================
     NAVBAR FINAL (tanpa CTA, struktur benar, auto-fit)
============================================================= -->
<nav class="navbar" id="navbar" role="navigation" aria-label="Main navigation">
    <div class="nav-container">
        <a href="<?= PUBLIC_URL ?>index.php" class="nav-brand" aria-label="PBSING UNIMOF - Beranda">
            <span class="brand-logo-wrap">
                <?php if (!empty($setting['logo'])): ?>
                    <img src="<?= BASE_URL . sanitize($setting['logo']) ?>" alt="<?= sanitize($setting['site_name']) ?>" class="brand-logo-img">
                <?php else: ?>
                    <span class="brand-icon"><i class="fa-solid fa-graduation-cap"></i></span>
                <?php endif; ?>
            </span>
            <span class="brand-text-stack">
                <span class="brand-title">PBSING <em>UNIMOF</em></span>
                <span class="brand-subtitle">Program Studi Bahasa &amp; Sastra Inggris</span>
            </span>
        </a>

        <ul class="nav-menu" id="navMenu">
            <?php foreach ($menus as $m): $kids = $menu_children[$m['id']] ?? []; $isActive = is_menu_active($m, $current_page, $kids); ?>
                <?php if ($kids): ?>
                <li class="nav-item has-dropdown <?= $isActive ? 'active-parent' : '' ?>">
                    <a href="#" class="nav-link <?= $isActive ? 'active' : '' ?>" aria-haspopup="true" aria-expanded="false">
                        <i class="nav-icon <?= sanitize($m['icon']) ?>"></i>
                        <span><?= sanitize($m['label']) ?></span>
                        <i class="fa-solid fa-chevron-down"></i>
                    </a>
                    <ul class="dropdown" role="menu">
                        <?php foreach ($kids as $k): ?>
                        <li>
                            <a href="<?= menu_url($k['url']) ?>" role="menuitem">
                                <i class="dropdown-icon <?= sanitize($k['icon']) ?>"></i>
                                <span><?= sanitize($k['label']) ?></span>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </li>
                <?php else: ?>
                <li class="nav-item">
                    <a href="<?= menu_url($m['url']) ?>" class="nav-link <?= $isActive ? 'active' : '' ?>">
                        <i class="nav-icon <?= sanitize($m['icon']) ?>"></i>
                        <span><?= sanitize($m['label']) ?></span>
                    </a>
                </li>
                <?php endif; ?>
            <?php endforeach; ?>

            <li class="lang-switcher-mobile" style="display:none;">
                <div class="lang-switcher" style="width:100%; justify-content:center;">
                    <button class="lang-btn <?= $current_lang === 'id' ? 'active' : '' ?>" data-lang-btn="id">
                        <img src="https://flagcdn.com/16x12/id.png" alt="ID"> ID
                    </button>
                    <button class="lang-btn <?= $current_lang === 'en' ? 'active' : '' ?>" data-lang-btn="en">
                        <img src="https://flagcdn.com/16x12/gb.png" alt="EN"> EN
                    </button>
                </div>
            </li>
        </ul>

        <div class="nav-actions">
            <div class="lang-switcher" role="group" aria-label="Language selection">
                <button class="lang-btn <?= $current_lang === 'id' ? 'active' : '' ?>" data-lang-btn="id" aria-label="Bahasa Indonesia">
                    <img src="https://flagcdn.com/16x12/id.png" alt="ID" loading="lazy"> ID
                </button>
                <button class="lang-btn <?= $current_lang === 'en' ? 'active' : '' ?>" data-lang-btn="en" aria-label="English">
                    <img src="https://flagcdn.com/16x12/gb.png" alt="EN" loading="lazy"> EN
                </button>
            </div>
            <button class="nav-search-trigger" id="searchTrigger" aria-label="Search (Ctrl+K)" title="Command Palette">
                <i class="fa-solid fa-magnifying-glass"></i>
            </button>
            <button class="theme-toggle" id="themeToggle" aria-label="Toggle theme">
                <i class="fa-solid fa-moon" id="themeIcon"></i>
            </button>
        </div>

        <button class="nav-toggle" id="navToggle" aria-label="Menu" aria-expanded="false">
            <i class="fa-solid fa-bars"></i>
        </button>
    </div>
</nav>

<div class="mobile-drawer-backdrop" id="drawerBackdrop"></div>

<?php
$soc_whatsapp = '';
if (!empty($setting['social_media'])) {
    $soc = json_decode($setting['social_media'], true);
    $soc_whatsapp = $soc['whatsapp'] ?? '';
}
$fallback_wa = $setting['whatsapp'] ?? '';
$wa_number = !empty($soc_whatsapp) ? $soc_whatsapp : $fallback_wa;
if (!empty($wa_number)): ?>
<a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $wa_number) ?>?text=<?= urlencode('Halo PBSI UMMaumere, saya ingin bertanya tentang...') ?>"
   class="floating-wa" target="_blank" rel="noopener noreferrer" aria-label="Chat on WhatsApp">
    <i class="fa-brands fa-whatsapp"></i>
</a>
<?php endif; ?>

<div class="cmd-hint" id="cmdHint">
    <i class="fa-solid fa-terminal"></i>
    <span>Tekan <kbd>Ctrl+K</kbd> untuk mencari</span>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
/* ===== PRELOADER v2 ENGINE ===== */
(function () {
    'use strict';
    var pre = document.getElementById('preloader');
    if (!pre) return;
    document.documentElement.classList.add('pl-locked');
    var fill = pre.querySelector('.pl-fill'), glow = pre.querySelector('.pl-glow'),
        pct = pre.querySelector('.pl-percent'), st = pre.querySelector('.pl-status');
    var statuses = ['INITIALIZING', 'LOADING ASSETS', 'BUILDING INTERFACE', 'SYNCING DATA', 'ALMOST READY'];
    var si = 0;
    var stTimer = setInterval(function () { si = (si + 1) % statuses.length; st.textContent = statuses[si]; }, 620);
    var p = 0, target = 28, loaded = false, exiting = false;
    setTimeout(function () { target = 56; }, 320);
    setTimeout(function () { target = 76; }, 800);
    setTimeout(function () { target = 88; }, 1350);
    window.addEventListener('load', function () { loaded = true; });
    setTimeout(function () { loaded = true; }, 4000);
    var tick = setInterval(function () {
        if (loaded && target < 100) target = 100;
        p += (target - p) * 0.14;
        if (target === 100 && p > 99.2) p = 100;
        pct.textContent = Math.round(p) + '%';
        fill.style.width = p + '%';
        glow.style.left = p + '%';
        if (p === 100 && !exiting) {
            exiting = true;
            clearInterval(stTimer);
            st.textContent = 'READY';
            pre.classList.add('pl-ready');
            setTimeout(function () { pre.classList.add('pl-exit'); document.documentElement.classList.remove('pl-locked'); }, 320);
            setTimeout(function () { pre.classList.add('pl-gone'); clearInterval(tick); }, 1500);
        }
    }, 28);
})();

/* ============================================================
   NAVBAR AUTO-FITTER — MENGUKUR & BERKORBAN SAMPAI MUAT
   (Inilah jaminan anti-terpotong: tidak ada lagi tebakan pixel)
   ============================================================ */
(function () {
    'use strict';
    var nav = document.getElementById('navbar');
    if (!nav) return;
    var container = nav.querySelector('.nav-container');
    var searchBtn = document.getElementById('searchTrigger');
    var langBox = nav.querySelector('.nav-actions .lang-switcher');
    var subtitle = nav.querySelector('.brand-subtitle');

    function icons() { return nav.querySelectorAll('.nav-icon'); }
    function overflows() { return container.scrollWidth > container.clientWidth + 1; }

    function reset() {
        if (searchBtn) searchBtn.style.display = '';
        if (langBox) langBox.style.display = '';
        if (subtitle) subtitle.style.display = '';
        icons().forEach(function (i) { i.style.display = ''; });
        nav.classList.remove('nav-fit-tight');
    }

    function fit() {
        if (window.innerWidth <= 900) { reset(); return; } /* mobile pakai drawer */
        reset();
        /* Urutan pengorbanan: yang paling tidak penting dulu */
        if (overflows() && searchBtn) searchBtn.style.display = 'none';
        if (overflows() && langBox) langBox.style.display = 'none';
        if (overflows() && subtitle) subtitle.style.display = 'none';
        if (overflows()) icons().forEach(function (i) { i.style.display = 'none'; });
        if (overflows()) nav.classList.add('nav-fit-tight');
    }

    var t;
    window.addEventListener('resize', function () { clearTimeout(t); t = setTimeout(fit, 120); });
    window.addEventListener('load', fit);
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(fit);
    fit();
})();

/* ===== NAVBAR SCROLL, MOBILE MENU & ACTIONS ===== */
(function () {
    'use strict';
    var navbar = document.getElementById('navbar');
    var navToggle = document.getElementById('navToggle');
    var navMenu = document.getElementById('navMenu');
    var drawerBackdrop = document.getElementById('drawerBackdrop');
    var searchTrigger = document.getElementById('searchTrigger');
    var themeToggle = document.getElementById('themeToggle');
    var themeIcon = document.getElementById('themeIcon');
    var cmdHint = document.getElementById('cmdHint');
    var lastScroll = 0;

    window.addEventListener('scroll', function () {
        var currentScroll = window.scrollY;
        if (navbar) {
            navbar.classList.toggle('scrolled', currentScroll > 50);
            if (currentScroll > lastScroll && currentScroll > 200) {
                navbar.style.transform = 'translateY(-100%)';
            } else {
                navbar.style.transform = 'translateY(0)';
            }
        }
        lastScroll = currentScroll;
    }, { passive: true });

    function closeMenu() {
        if (!navMenu || !navToggle) return;
        navMenu.classList.remove('active');
        drawerBackdrop?.classList.remove('active');
        navToggle.setAttribute('aria-expanded', 'false');
        var icon = navToggle.querySelector('i');
        if (icon) icon.classList.replace('fa-xmark', 'fa-bars');
        document.body.style.overflow = '';
    }
    function openMenu() {
        if (!navMenu || !navToggle) return;
        navMenu.classList.add('active');
        drawerBackdrop?.classList.add('active');
        navToggle.setAttribute('aria-expanded', 'true');
        var icon = navToggle.querySelector('i');
        if (icon) icon.classList.replace('fa-bars', 'fa-xmark');
        document.body.style.overflow = 'hidden';
    }
    if (navToggle && navMenu) {
        navToggle.addEventListener('click', function () {
            navMenu.classList.contains('active') ? closeMenu() : openMenu();
        });
        drawerBackdrop?.addEventListener('click', closeMenu);
        document.querySelectorAll('.nav-menu a').forEach(function (link) {
            link.addEventListener('click', function () {
                if (window.innerWidth <= 900) closeMenu();
            });
        });
    }
    document.querySelectorAll('.has-dropdown > a').forEach(function (trigger) {
        trigger.addEventListener('click', function (e) {
            if (window.innerWidth <= 900) {
                e.preventDefault();
                var parent = this.parentElement;
                var isOpen = parent.classList.contains('open');
                document.querySelectorAll('.has-dropdown.open').forEach(function (d) {
                    if (d !== parent) d.classList.remove('open');
                });
                parent.classList.toggle('open', !isOpen);
            }
        });
    });
    if (searchTrigger) {
        searchTrigger.addEventListener('click', function () {
            window.dispatchEvent(new CustomEvent('openCommandPalette'));
            if (typeof window.openCommandPalette === 'undefined') {
                Swal.fire({
                    title: 'Pencarian', input: 'text', inputPlaceholder: 'Ketik untuk mencari...',
                    showCancelButton: true, confirmButtonColor: '#8A1538',
                    confirmButtonText: 'Cari', cancelButtonText: 'Batal'
                }).then(function (result) {
                    if (result.isConfirmed && result.value) {
                        window.location.href = '<?= PUBLIC_URL ?>search.php?q=' + encodeURIComponent(result.value);
                    }
                });
            }
        });
    }
    document.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); searchTrigger?.click(); }
        if (e.key === 'Escape') closeMenu();
    });
    if (cmdHint && window.innerWidth > 900) {
        setTimeout(function () {
            cmdHint.classList.add('visible');
            setTimeout(function () { cmdHint.classList.remove('visible'); }, 5000);
        }, 3000);
    }
    var savedTheme = localStorage.getItem('pbsi_theme') || 'light';
    document.documentElement.setAttribute('data-theme', savedTheme);
    if (themeIcon) themeIcon.className = savedTheme === 'dark' ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
    if (themeToggle) {
        themeToggle.addEventListener('click', function () {
            var current = document.documentElement.getAttribute('data-theme');
            var next = current === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', next);
            localStorage.setItem('pbsi_theme', next);
            if (themeIcon) themeIcon.className = next === 'dark' ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: next === 'dark' ? 'Mode Gelap Aktif' : 'Mode Terang Aktif', showConfirmButton: false, timer: 1500, timerProgressBar: true });
        });
    }
    document.querySelectorAll('.lang-btn').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            var lang = this.getAttribute('data-lang-btn');
            if (!lang || lang === document.documentElement.getAttribute('lang')) return;
            document.cookie = 'pbsi_lang=' + lang + ';path=/;max-age=31536000';
            document.documentElement.setAttribute('lang', lang);
            document.querySelectorAll('.lang-btn').forEach(function (b) {
                b.classList.toggle('active', b.getAttribute('data-lang-btn') === lang);
            });
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: lang === 'en' ? 'Language changed to English' : 'Bahasa diubah ke Indonesia', showConfirmButton: false, timer: 1500, timerProgressBar: true });
            setTimeout(function () {
                var url = new URL(window.location);
                url.searchParams.set('lang', lang);
                window.location.href = url.toString();
            }, 800);
        });
    });
})();
</script>