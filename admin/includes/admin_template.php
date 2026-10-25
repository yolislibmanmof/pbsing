<?php
if (!isset($page_title)) $page_title = 'Dashboard';
if (!isset($current_page)) $current_page = 'dashboard';

$admin_name = $_SESSION['admin_name'] ?? 'Admin';
$admin_role = $_SESSION['admin_role'] ?? 'Admin';
$initial = strtoupper(substr($admin_name, 0, 1));

// Fetch notification counts
$notifCounts = [
    'draft' => $pdo->query("SELECT COUNT(*) FROM berita WHERE status = 0")->fetchColumn() ?? 0,
    'login' => $pdo->query("SELECT COUNT(*) FROM login_attempts WHERE attempted_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetchColumn() ?? 0,
    'agenda' => $pdo->query("SELECT COUNT(*) FROM agenda WHERE tanggal_mulai >= CURDATE() AND tanggal_mulai <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND aktif = 1")->fetchColumn() ?? 0,
];
$totalNotif = array_sum($notifCounts);
?>
<!DOCTYPE html>
<html lang="id" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= htmlspecialchars($page_title) ?> - PBSI Command Center</title>
    <link rel="stylesheet" href="assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --bg-darker: #0f0f1a;
            --bg-main: #14141f;
            --panel-bg: #1a1a2e;
            --border-dark: #2a2a3e;
            --border-light: #3a3a4e;
            --text-main: #e0e0e0;
            --text-muted: #8a8a9e;
            --crimson: #8A1538;
            --crimson-light: #A02050;
            --sky-blue: #87CEEB;
            --sky-blue-dark: #5BA0C9;
            --success: #2E8B57;
            --warning: #F39C12;
            --danger: #E74C3C;
            --shadow-md: 0 8px 24px rgba(0,0,0,0.3);
            --shadow-lg: 0 16px 40px rgba(0,0,0,0.4);
            --radius-sm: 6px;
            --radius-md: 10px;
            --radius-lg: 16px;
        }
        
        [data-theme="light"] {
            --bg-darker: #f5f5f7;
            --bg-main: #fafafa;
            --panel-bg: #ffffff;
            --border-dark: #e5e5ea;
            --border-light: #d5d5da;
            --text-main: #1a1a2e;
            --text-muted: #6a6a7e;
        }
        
        body {
            background: var(--bg-main);
            color: var(--text-main);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            transition: background .3s ease, color .3s ease;
        }
        
        /* Sidebar */
        .sidebar {
            position: fixed;
            left: 0; top: 0; bottom: 0;
            width: 260px;
            background: var(--panel-bg);
            border-right: 1px solid var(--border-dark);
            display: flex;
            flex-direction: column;
            z-index: 100;
            transition: width .3s ease, transform .3s ease;
        }
        .sidebar.collapsed { width: 72px; }
        .sidebar.collapsed .logo-text, 
        .sidebar.collapsed .nav-section-title, 
        .sidebar.collapsed .sidebar-nav a span:not(.badge),
        .sidebar.collapsed .user-info,
        .sidebar.collapsed .sidebar-search,
        .sidebar.collapsed .logout span { display: none; }
        .sidebar.collapsed .sidebar-nav a { justify-content: center; padding: 14px 0; }
        .sidebar.collapsed .sidebar-nav a i { margin: 0; }
        .sidebar.collapsed .logo { justify-content: center; padding: 1rem; }
        .sidebar.collapsed .user-avatar { margin: 0 auto; }
        .sidebar.collapsed .logout { justify-content: center; }
        
        .sidebar-header {
            padding: 1.2rem;
            border-bottom: 1px solid var(--border-dark);
        }
        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: inherit;
        }
        .logo-icon {
            width: 40px; height: 40px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--crimson), var(--crimson-light));
            color: #fff;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        .logo-text {
            font-weight: 800;
            color: #fff;
            font-size: .95rem;
        }
        .logo-text em { color: var(--sky-blue); font-style: normal; font-weight: 500; display: block; font-size: .7rem; }
        
        .sidebar-search {
            padding: 1rem;
            border-bottom: 1px solid var(--border-dark);
        }
        .sidebar-search input {
            width: 100%;
            padding: .6rem 1rem;
            background: var(--bg-darker);
            border: 1px solid var(--border-dark);
            border-radius: var(--radius-sm);
            color: var(--text-main);
            font-size: .85rem;
        }
        .sidebar-search input:focus {
            outline: none;
            border-color: var(--crimson);
        }
        
        .sidebar-nav {
            flex: 1;
            overflow-y: auto;
            padding: 1rem 0;
        }
        .nav-section-title {
            font-size: .68rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: var(--text-muted);
            padding: 1rem 1.2rem .5rem;
            font-weight: 700;
        }
        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: .8rem;
            padding: .7rem 1.2rem;
            color: var(--text-muted);
            text-decoration: none;
            font-size: .88rem;
            font-weight: 500;
            transition: all .2s ease;
            position: relative;
        }
        .sidebar-nav a:hover {
            background: rgba(138,21,56,0.08);
            color: var(--text-main);
        }
        .sidebar-nav a.active {
            background: linear-gradient(90deg, rgba(138,21,56,0.2), transparent);
            color: #fff;
            font-weight: 700;
        }
        .sidebar-nav a.active::before {
            content: '';
            position: absolute;
            left: 0; top: 0; bottom: 0;
            width: 3px;
            background: var(--crimson);
        }
        .sidebar-nav a i {
            width: 20px;
            text-align: center;
            font-size: .95rem;
            flex-shrink: 0;
        }
        .sidebar-nav .badge {
            margin-left: auto;
            background: var(--crimson);
            color: #fff;
            font-size: .65rem;
            padding: .15rem .5rem;
            border-radius: 50px;
            font-weight: 700;
        }
        
        .sidebar-footer {
            padding: 1rem;
            border-top: 1px solid var(--border-dark);
        }
        .sidebar-user {
            display: flex;
            align-items: center;
            gap: .8rem;
            margin-bottom: .8rem;
        }
        .user-avatar {
            width: 36px; height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--crimson), var(--crimson-light));
            color: #fff;
            display: flex; align-items: center; justify-content: center;
            font-weight: 800;
            font-size: .9rem;
            flex-shrink: 0;
        }
        .user-name { font-weight: 700; font-size: .85rem; color: #fff; }
        .user-role { font-size: .72rem; color: var(--text-muted); }
        .logout {
            display: flex;
            align-items: center;
            gap: .6rem;
            padding: .6rem;
            background: rgba(231,76,60,0.08);
            color: var(--danger);
            border-radius: var(--radius-sm);
            text-decoration: none;
            font-size: .85rem;
            font-weight: 600;
            transition: all .2s;
        }
        .logout:hover { background: var(--danger); color: #fff; }
        
        /* Main Content */
        .content {
            margin-left: 260px;
            min-height: 100vh;
            transition: margin-left .3s ease;
        }
        .sidebar.collapsed ~ .content { margin-left: 72px; }
        
        .top-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 1.5rem;
            background: var(--panel-bg);
            border-bottom: 1px solid var(--border-dark);
            position: sticky;
            top: 0;
            z-index: 50;
            backdrop-filter: blur(10px);
            gap: 1rem;
        }
        .header-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .page-title {
            font-size: 1.3rem;
            font-weight: 800;
            color: #fff;
            margin: 0;
        }
        .page-breadcrumb {
            font-size: .78rem;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: .4rem;
        }
        .page-breadcrumb i { font-size: .7rem; }
        
        .header-actions {
            display: flex;
            align-items: center;
            gap: .5rem;
        }
        .header-btn {
            width: 40px; height: 40px;
            border-radius: 10px;
            background: var(--bg-darker);
            border: 1px solid var(--border-dark);
            color: var(--text-main);
            cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            font-size: .95rem;
            transition: all .2s;
            position: relative;
        }
        .header-btn:hover { background: var(--crimson); color: #fff; border-color: var(--crimson); }
        .header-btn .notif-dot {
            position: absolute;
            top: 8px; right: 8px;
            width: 8px; height: 8px;
            background: var(--danger);
            border-radius: 50%;
            border: 2px solid var(--panel-bg);
        }
        .header-user {
            display: flex;
            align-items: center;
            gap: .8rem;
            padding: .3rem .8rem .3rem .3rem;
            background: var(--bg-darker);
            border: 1px solid var(--border-dark);
            border-radius: 50px;
            cursor: pointer;
            transition: all .2s;
        }
        .header-user:hover { border-color: var(--crimson); }
        .header-user-avatar {
            width: 32px; height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--crimson), var(--crimson-light));
            color: #fff;
            display: flex; align-items: center; justify-content: center;
            font-weight: 800;
            font-size: .8rem;
        }
        .header-user-name { font-weight: 700; font-size: .82rem; color: #fff; }
        .header-user-role { font-size: .68rem; color: var(--text-muted); }
        
        .main-area {
            padding: 1.5rem;
        }
        
        /* ===== COMMAND PALETTE ===== */
        .command-palette-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(8px);
            z-index: 1000;
            display: none;
            align-items: flex-start;
            justify-content: center;
            padding-top: 15vh;
        }
        .command-palette-overlay.active { display: flex; }
        .command-palette {
            width: 90%;
            max-width: 600px;
            background: var(--panel-bg);
            border: 1px solid var(--border-dark);
            border-radius: var(--radius-lg);
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
            overflow: hidden;
            animation: paletteIn .2s ease;
        }
        @keyframes paletteIn {
            from { opacity: 0; transform: translateY(-10px) scale(.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .command-palette-search {
            display: flex;
            align-items: center;
            gap: .8rem;
            padding: 1.2rem 1.5rem;
            border-bottom: 1px solid var(--border-dark);
        }
        .command-palette-search i { color: var(--crimson); font-size: 1.1rem; }
        .command-palette-search input {
            flex: 1;
            background: transparent;
            border: none;
            color: var(--text-main);
            font-size: 1rem;
            outline: none;
        }
        .command-palette-search kbd {
            padding: .15rem .5rem;
            background: var(--bg-darker);
            border: 1px solid var(--border-dark);
            border-radius: 4px;
            font-size: .7rem;
            color: var(--text-muted);
            font-family: monospace;
        }
        .command-palette-results {
            max-height: 400px;
            overflow-y: auto;
            padding: .5rem;
        }
        .command-group-title {
            font-size: .7rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: var(--text-muted);
            padding: .5rem 1rem;
            font-weight: 700;
        }
        .command-item {
            display: flex;
            align-items: center;
            gap: .8rem;
            padding: .7rem 1rem;
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: background .15s;
            color: var(--text-main);
            text-decoration: none;
        }
        .command-item:hover, .command-item.active {
            background: rgba(138,21,56,0.15);
        }
        .command-item-icon {
            width: 32px; height: 32px;
            border-radius: 8px;
            background: var(--bg-darker);
            display: flex; align-items: center; justify-content: center;
            color: var(--crimson);
            flex-shrink: 0;
        }
        .command-item-label { flex: 1; font-weight: 600; font-size: .88rem; }
        .command-item-hint {
            font-size: .72rem;
            color: var(--text-muted);
        }
        .command-palette-footer {
            padding: .6rem 1rem;
            border-top: 1px solid var(--border-dark);
            display: flex;
            gap: 1rem;
            font-size: .72rem;
            color: var(--text-muted);
        }
        .command-palette-footer kbd {
            padding: .1rem .4rem;
            background: var(--bg-darker);
            border: 1px solid var(--border-dark);
            border-radius: 3px;
            font-family: monospace;
            margin-right: .3rem;
        }
        
        /* ===== NOTIFICATION DROPDOWN ===== */
        .notif-dropdown {
            position: absolute;
            top: 50px; right: 0;
            width: 360px;
            background: var(--panel-bg);
            border: 1px solid var(--border-dark);
            border-radius: var(--radius-lg);
            box-shadow: 0 20px 60px rgba(0,0,0,0.4);
            z-index: 200;
            display: none;
            animation: dropIn .2s ease;
        }
        .notif-dropdown.active { display: block; }
        @keyframes dropIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .notif-header {
            padding: 1rem 1.2rem;
            border-bottom: 1px solid var(--border-dark);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .notif-header h4 { margin: 0; font-size: .95rem; color: #fff; }
        .notif-header a { font-size: .75rem; color: var(--sky-blue); text-decoration: none; }
        .notif-list { max-height: 400px; overflow-y: auto; }
        .notif-item {
            display: flex;
            gap: .8rem;
            padding: 1rem 1.2rem;
            border-bottom: 1px solid var(--border-dark);
            cursor: pointer;
            transition: background .15s;
            text-decoration: none;
            color: inherit;
        }
        .notif-item:hover { background: var(--bg-darker); }
        .notif-item:last-child { border-bottom: none; }
        .notif-icon {
            width: 36px; height: 36px;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .notif-icon.draft { background: rgba(243,156,18,0.15); color: var(--warning); }
        .notif-icon.login { background: rgba(231,76,60,0.15); color: var(--danger); }
        .notif-icon.agenda { background: rgba(135,206,235,0.15); color: var(--sky-blue); }
        .notif-content { flex: 1; min-width: 0; }
        .notif-title { font-weight: 700; font-size: .85rem; color: #fff; margin-bottom: .2rem; }
        .notif-desc { font-size: .75rem; color: var(--text-muted); line-height: 1.4; }
        .notif-time { font-size: .68rem; color: var(--text-muted); margin-top: .3rem; }
        
        /* ===== TOAST ===== */
        .toast-container {
            position: fixed;
            top: 20px; right: 20px;
            z-index: 2000;
            display: flex;
            flex-direction: column;
            gap: .5rem;
        }
        .toast {
            background: var(--panel-bg);
            border: 1px solid var(--border-dark);
            border-left: 4px solid var(--success);
            border-radius: var(--radius-md);
            padding: 1rem 1.2rem;
            min-width: 280px;
            display: flex;
            align-items: center;
            gap: .8rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            animation: toastIn .3s ease;
        }
        @keyframes toastIn {
            from { opacity: 0; transform: translateX(100%); }
            to { opacity: 1; transform: translateX(0); }
        }
        .toast.warning { border-left-color: var(--warning); }
        .toast.danger { border-left-color: var(--danger); }
        .toast-icon {
            width: 36px; height: 36px;
            border-radius: 10px;
            background: rgba(46,139,87,0.15);
            color: var(--success);
            display: flex; align-items: center; justify-content: center;
            font-size: 1rem;
            flex-shrink: 0;
        }
        .toast.warning .toast-icon { background: rgba(243,156,18,0.15); color: var(--warning); }
        .toast.danger .toast-icon { background: rgba(231,76,60,0.15); color: var(--danger); }
        .toast-content { flex: 1; }
        .toast-title { font-weight: 700; color: #fff; font-size: .88rem; margin-bottom: .2rem; }
        .toast-desc { font-size: .78rem; color: var(--text-muted); }
        
        /* Mobile */
        .mobile-toggle {
            display: none;
            position: fixed;
            top: 1rem; left: 1rem;
            z-index: 200;
            width: 44px; height: 44px;
            border-radius: 10px;
            background: var(--crimson);
            color: #fff;
            border: none;
            cursor: pointer;
            font-size: 1.2rem;
        }
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .content { margin-left: 0; }
            .sidebar.collapsed ~ .content { margin-left: 0; }
            .mobile-toggle { display: flex; align-items: center; justify-content: center; }
            .header-user-info { display: none; }
            .notif-dropdown { width: calc(100vw - 2rem); right: -60px; }
        }
        
        /* Scrollbar */
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: var(--bg-darker); }
        ::-webkit-scrollbar-thumb { background: var(--border-light); border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--crimson); }
    </style>
</head>
<body>

    <button class="mobile-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')" aria-label="Menu">
        <i class="fa-solid fa-bars"></i>
    </button>

    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="dashboard.php" class="logo">
                <div class="logo-icon"><i class="fa-solid fa-graduation-cap"></i></div>
                <div class="logo-text">PBSI <em>Command Center</em></div>
            </a>
        </div>
        
        <div class="sidebar-search">
            <input type="text" placeholder="Cari menu... (Ctrl+K)" id="sidebarSearch" readonly onclick="openCommandPalette()">
        </div>

        <nav class="sidebar-nav">
            <div class="nav-section-title">Utama</div>
            <a href="dashboard.php" class="<?= $current_page == 'dashboard' ? 'active' : '' ?>"><i class="fa-solid fa-gauge-high"></i> <span>Dashboard</span></a>
            <a href="../" target="_blank"><i class="fa-solid fa-globe"></i> <span>Lihat Website</span></a>

            <div class="nav-section-title">Manajemen Konten</div>
            <a href="kelola_berita.php" class="<?= $current_page == 'berita' ? 'active' : '' ?>"><i class="fa-solid fa-newspaper"></i> <span>Berita</span></a>
            <a href="kelola_agenda.php" class="<?= $current_page == 'agenda' ? 'active' : '' ?>"><i class="fa-solid fa-calendar-days"></i> <span>Agenda</span></a>
            <a href="kelola_galeri.php" class="<?= $current_page == 'galeri' ? 'active' : '' ?>"><i class="fa-solid fa-images"></i> <span>Galeri</span></a>
            <a href="kelola_slider.php" class="<?= $current_page == 'slider' ? 'active' : '' ?>"><i class="fa-solid fa-image"></i> <span>Slider / Hero</span></a>

            <div class="nav-section-title">Data Akademik</div>
            <a href="kelola_dosen.php" class="<?= $current_page == 'dosen' ? 'active' : '' ?>"><i class="fa-solid fa-chalkboard-user"></i> <span>Dosen</span></a>
            <a href="kelola_alumni.php" class="<?= $current_page == 'alumni' ? 'active' : '' ?>"><i class="fa-solid fa-user-graduate"></i> <span>Alumni</span></a>
            <a href="kelola_kurikulum.php" class="<?= $current_page == 'kurikulum' ? 'active' : '' ?>"><i class="fa-solid fa-book-open"></i> <span>Kurikulum</span></a>
            <a href="kelola_publikasi.php" class="<?= $current_page == 'publikasi' ? 'active' : '' ?>"><i class="fa-solid fa-flask"></i> <span>Publikasi</span></a>
            <a href="kelola_unduh.php" class="<?= $current_page == 'unduh' ? 'active' : '' ?>"><i class="fa-solid fa-cloud-arrow-down"></i> <span>Unduhan & RPS</span></a>

            <div class="nav-section-title">Profil Institusi</div>
            <a href="kelola_struktur.php" class="<?= $current_page == 'struktur' ? 'active' : '' ?>"><i class="fa-solid fa-sitemap"></i> <span>Struktur</span></a>
            <a href="kelola_visimisi.php" class="<?= $current_page == 'visimisi' ? 'active' : '' ?>"><i class="fa-solid fa-bullseye"></i> <span>Visi & Misi</span></a>
            <a href="kelola_sambutan.php" class="<?= $current_page == 'sambutan' ? 'active' : '' ?>"><i class="fa-solid fa-user-tie"></i> <span>Sambutan Kaprodi</span></a>

            <div class="nav-section-title">Sistem</div>
            <a href="kelola_users.php" class="<?= $current_page == 'users' ? 'active' : '' ?>"><i class="fa-solid fa-users-gear"></i> <span>User Management</span></a>
            <a href="settings.php" class="<?= $current_page == 'settings' ? 'active' : '' ?>"><i class="fa-solid fa-gear"></i> <span>Pengaturan</span></a>
            <a href="admin_logs.php" class="<?= $current_page == 'logs' ? 'active' : '' ?>">
                <i class="fa-solid fa-shield-halved"></i> 
                <span>Log Keamanan</span>
                <?php if ($notifCounts['login'] > 0): ?><span class="badge"><?= $notifCounts['login'] ?></span><?php endif; ?>
            </a>
        </nav>
        
        <div class="sidebar-footer">
            <div class="sidebar-user">
                <div class="user-avatar"><?= htmlspecialchars($initial) ?></div>
                <div class="user-info">
                    <div class="user-name"><?= htmlspecialchars($admin_name) ?></div>
                    <div class="user-role"><?= htmlspecialchars($admin_role) ?></div>
                </div>
            </div>
            <a href="logout.php" class="logout" onclick="return confirm('Yakin ingin keluar?');">
                <i class="fa-solid fa-right-from-bracket"></i> <span>Keluar</span>
            </a>
        </div>
    </aside>

    <main class="content">
        <header class="top-header">
            <div class="header-left">
                <button class="header-btn" onclick="document.getElementById('sidebar').classList.toggle('collapsed')" title="Toggle Sidebar">
                    <i class="fa-solid fa-bars-staggered"></i>
                </button>
                <div>
                    <h1 class="page-title"><?= htmlspecialchars($page_title) ?></h1>
                    <div class="page-breadcrumb">
                        <i class="fa-solid fa-house"></i> <span>Admin</span>
                        <i class="fa-solid fa-chevron-right"></i>
                        <span style="color:var(--crimson); font-weight:600;"><?= htmlspecialchars($page_title) ?></span>
                    </div>
                </div>
            </div>
            
            <div class="header-actions">
                <button class="header-btn" onclick="openCommandPalette()" title="Command Palette (Ctrl+K)">
                    <i class="fa-solid fa-terminal"></i>
                </button>
                <button class="header-btn" onclick="toggleTheme()" title="Toggle Theme">
                    <i class="fa-solid fa-moon" id="themeIcon"></i>
                </button>
                <div style="position:relative;">
                    <button class="header-btn" onclick="toggleNotifDropdown()" title="Notifications">
                        <i class="fa-solid fa-bell"></i>
                        <?php if ($totalNotif > 0): ?><span class="notif-dot"></span><?php endif; ?>
                    </button>
                    <div class="notif-dropdown" id="notifDropdown">
                        <div class="notif-header">
                            <h4>Notifikasi</h4>
                            <a href="#" onclick="markAllRead()">Tandai semua dibaca</a>
                        </div>
                        <div class="notif-list">
                            <?php if ($notifCounts['draft'] > 0): ?>
                            <a href="kelola_berita.php?filter=draft" class="notif-item">
                                <div class="notif-icon draft"><i class="fa-solid fa-file-pen"></i></div>
                                <div class="notif-content">
                                    <div class="notif-title"><?= $notifCounts['draft'] ?> Berita Draft</div>
                                    <div class="notif-desc">Ada <?= $notifCounts['draft'] ?> berita yang masih dalam status draft.</div>
                                    <div class="notif-time">Perlu ditinjau</div>
                                </div>
                            </a>
                            <?php endif; ?>
                            
                            <?php if ($notifCounts['login'] > 0): ?>
                            <a href="admin_logs.php" class="notif-item">
                                <div class="notif-icon login"><i class="fa-solid fa-shield-halved"></i></div>
                                <div class="notif-content">
                                    <div class="notif-title"><?= $notifCounts['login'] ?> Login Attempts</div>
                                    <div class="notif-desc">Terdeteksi <?= $notifCounts['login'] ?> percobaan login dalam 24 jam terakhir.</div>
                                    <div class="notif-time">Perlu perhatian</div>
                                </div>
                            </a>
                            <?php endif; ?>
                            
                            <?php if ($notifCounts['agenda'] > 0): ?>
                            <a href="kelola_agenda.php" class="notif-item">
                                <div class="notif-icon agenda"><i class="fa-solid fa-calendar-days"></i></div>
                                <div class="notif-content">
                                    <div class="notif-title"><?= $notifCounts['agenda'] ?> Agenda Minggu Ini</div>
                                    <div class="notif-desc">Anda memiliki <?= $notifCounts['agenda'] ?> agenda dalam 7 hari ke depan.</div>
                                    <div class="notif-time">Reminder</div>
                                </div>
                            </a>
                            <?php endif; ?>
                            
                            <?php if ($totalNotif == 0): ?>
                            <div style="padding:2rem; text-align:center; color:var(--text-muted);">
                                <i class="fa-regular fa-bell-slash" style="font-size:2rem; opacity:.3; margin-bottom:.5rem;"></i>
                                <p>Tidak ada notifikasi baru</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <button class="header-btn" onclick="toggleFullscreen()" title="Fullscreen">
                    <i class="fa-solid fa-expand"></i>
                </button>
                <div class="header-user">
                    <div class="header-user-avatar"><?= htmlspecialchars($initial) ?></div>
                    <div class="header-user-info">
                        <div class="header-user-name"><?= htmlspecialchars($admin_name) ?></div>
                        <div class="header-user-role"><?= htmlspecialchars($admin_role) ?></div>
                    </div>
                </div>
            </div>
        </header>
        
        <div class="main-area">
            <?= $page_content ?? '' ?>
        </div>
        
        <footer style="margin-top:3rem; padding:2rem 1.5rem; border-top:1px solid var(--border-dark); text-align:center; color:var(--text-muted); font-size:.85rem;">
            <p>© <?= date('Y') ?> <strong style="color:#fff;">PBSI UMMaumere</strong> Command Center · v3.0 SUPER ULTIMATE</p>
            <p style="margin-top:.5rem; font-size:.78rem; opacity:.7;">
                <i class="fa-solid fa-keyboard"></i> Tekan <kbd style="padding:.1rem .4rem; background:var(--bg-darker); border:1px solid var(--border-dark); border-radius:3px; font-family:monospace;">Ctrl+K</kbd> untuk Command Palette · Session aktif sejak <?= date('H:i', $_SESSION['login_time'] ?? time()) ?>
            </p>
        </footer>
    </main>

    <!-- COMMAND PALETTE -->
    <div class="command-palette-overlay" id="commandPalette" onclick="if(event.target===this) closeCommandPalette()">
        <div class="command-palette">
            <div class="command-palette-search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" placeholder="Ketik perintah atau cari menu..." id="commandInput" autocomplete="off">
                <kbd>ESC</kbd>
            </div>
            <div class="command-palette-results" id="commandResults"></div>
            <div class="command-palette-footer">
                <span><kbd>↑</kbd><kbd>↓</kbd> Navigasi</span>
                <span><kbd>↵</kbd> Pilih</span>
                <span><kbd>ESC</kbd> Tutup</span>
            </div>
        </div>
    </div>

    <!-- TOAST CONTAINER -->
    <div class="toast-container" id="toastContainer"></div>

    <script>
    // ===== COMMAND PALETTE =====
    const commands = [
        { group: 'Navigasi', items: [
            { label: 'Dashboard', icon: 'fa-gauge-high', link: 'dashboard.php' },
            { label: 'Berita', icon: 'fa-newspaper', link: 'kelola_berita.php' },
            { label: 'Agenda', icon: 'fa-calendar-days', link: 'kelola_agenda.php' },
            { label: 'Galeri', icon: 'fa-images', link: 'kelola_galeri.php' },
            { label: 'Dosen', icon: 'fa-chalkboard-user', link: 'kelola_dosen.php' },
            { label: 'Alumni', icon: 'fa-user-graduate', link: 'kelola_alumni.php' },
            { label: 'Kurikulum', icon: 'fa-book-open', link: 'kelola_kurikulum.php' },
            { label: 'Publikasi', icon: 'fa-flask', link: 'kelola_publikasi.php' },
            { label: 'Unduhan & RPS', icon: 'fa-cloud-arrow-down', link: 'kelola_unduh.php' },
            { label: 'Struktur Organisasi', icon: 'fa-sitemap', link: 'kelola_struktur.php' },
            { label: 'Visi & Misi', icon: 'fa-bullseye', link: 'kelola_visimisi.php' },
            { label: 'Sambutan Kaprodi', icon: 'fa-user-tie', link: 'kelola_sambutan.php' },
            { label: 'Slider / Hero', icon: 'fa-image', link: 'kelola_slider.php' },
        ]},
        { group: 'Aksi Cepat', items: [
            { label: 'Tambah Berita Baru', icon: 'fa-plus', link: 'kelola_berita.php?action=add', hint: 'Tulis artikel' },
            { label: 'Buat Agenda', icon: 'fa-calendar-plus', link: 'kelola_agenda.php?action=add', hint: 'Jadwal kegiatan' },
            { label: 'Upload Foto Galeri', icon: 'fa-image', link: 'kelola_galeri.php?action=add', hint: 'Dokumentasi' },
            { label: 'Tambah Dosen', icon: 'fa-user-plus', link: 'kelola_dosen.php?action=add', hint: 'Data pengajar' },
            { label: 'Upload Dokumen', icon: 'fa-upload', link: 'kelola_unduh.php?action=add&tab=unduh', hint: 'File akademik' },
            { label: 'Tambah RPS', icon: 'fa-book-open', link: 'kelola_unduh.php?action=add&tab=rps', hint: 'Rencana kuliah' },
            { label: 'Tambah Publikasi', icon: 'fa-flask', link: 'kelola_publikasi.php?action=add', hint: 'Karya ilmiah' },
        ]},
        { group: 'Sistem', items: [
            { label: 'Lihat Website', icon: 'fa-globe', link: '../', hint: 'Buka di tab baru', newTab: true },
            { label: 'Log Keamanan', icon: 'fa-shield-halved', link: 'admin_logs.php' },
            { label: 'Manajemen User', icon: 'fa-users-gear', link: 'kelola_users.php' },
            { label: 'Pengaturan', icon: 'fa-gear', link: 'settings.php' },
            { label: 'Keluar dari Sistem', icon: 'fa-right-from-bracket', link: 'logout.php', hint: 'Logout' },
        ]}
    ];
    
    let activeCmdIndex = 0;
    let filteredCommands = [];
    
    function openCommandPalette() {
        document.getElementById('commandPalette').classList.add('active');
        const input = document.getElementById('commandInput');
        input.value = '';
        input.focus();
        renderCommands('');
    }
    function closeCommandPalette() {
        document.getElementById('commandPalette').classList.remove('active');
    }
    
    function renderCommands(query) {
        const results = document.getElementById('commandResults');
        const q = query.toLowerCase();
        let html = '';
        filteredCommands = [];
        
        commands.forEach(group => {
            const items = group.items.filter(i => 
                i.label.toLowerCase().includes(q) || 
                (i.hint && i.hint.toLowerCase().includes(q))
            );
            if (items.length === 0) return;
            
            html += `<div class="command-group-title">${group.group}</div>`;
            items.forEach(item => {
                filteredCommands.push(item);
                const idx = filteredCommands.length - 1;
                html += `<a href="${item.link}" ${item.newTab ? 'target="_blank"' : ''} class="command-item ${idx === activeCmdIndex ? 'active' : ''}" data-idx="${idx}" onclick="event.preventDefault(); executeCommand(${idx})">
                    <div class="command-item-icon"><i class="fa-solid ${item.icon}"></i></div>
                    <span class="command-item-label">${item.label}</span>
                    ${item.hint ? `<span class="command-item-hint">${item.hint}</span>` : ''}
                </a>`;
            });
        });
        
        if (filteredCommands.length === 0) {
            html = `<div style="padding:2rem; text-align:center; color:var(--text-muted);">
                <i class="fa-solid fa-circle-question" style="font-size:2rem; opacity:.3; margin-bottom:.5rem;"></i>
                <p>Tidak ada hasil untuk "${query}"</p>
            </div>`;
        }
        
        results.innerHTML = html;
    }
    
    function executeCommand(idx) {
        const cmd = filteredCommands[idx];
        if (!cmd) return;
        closeCommandPalette();
        if (cmd.newTab) window.open(cmd.link, '_blank');
        else window.location.href = cmd.link;
    }
    
    document.getElementById('commandInput')?.addEventListener('input', function() {
        activeCmdIndex = 0;
        renderCommands(this.value);
    });
    
    document.addEventListener('keydown', function(e) {
        const palette = document.getElementById('commandPalette');
        if (e.ctrlKey && e.key === 'k') {
            e.preventDefault();
            palette.classList.contains('active') ? closeCommandPalette() : openCommandPalette();
        }
        if (e.key === 'Escape') closeCommandPalette();
        if (palette.classList.contains('active')) {
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                activeCmdIndex = Math.min(activeCmdIndex + 1, filteredCommands.length - 1);
                renderCommands(document.getElementById('commandInput').value);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                activeCmdIndex = Math.max(activeCmdIndex - 1, 0);
                renderCommands(document.getElementById('commandInput').value);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                executeCommand(activeCmdIndex);
            }
        }
    });
    
    // ===== THEME TOGGLE =====
    function toggleTheme() {
        const html = document.documentElement;
        const current = html.getAttribute('data-theme');
        const next = current === 'dark' ? 'light' : 'dark';
        html.setAttribute('data-theme', next);
        localStorage.setItem('theme', next);
        const icon = document.getElementById('themeIcon');
        icon.className = next === 'dark' ? 'fa-solid fa-moon' : 'fa-solid fa-sun';
        showToast('info', 'Theme diubah', `Mode ${next === 'dark' ? 'gelap' : 'terang'} aktif`);
    }
    
    // Load saved theme
    const savedTheme = localStorage.getItem('theme') || 'dark';
    document.documentElement.setAttribute('data-theme', savedTheme);
    const themeIcon = document.getElementById('themeIcon');
    if (themeIcon) themeIcon.className = savedTheme === 'dark' ? 'fa-solid fa-moon' : 'fa-solid fa-sun';
    
    // ===== NOTIFICATION DROPDOWN =====
    function toggleNotifDropdown() {
        document.getElementById('notifDropdown').classList.toggle('active');
    }
    document.addEventListener('click', function(e) {
        const dd = document.getElementById('notifDropdown');
        if (!dd.contains(e.target) && !e.target.closest('[onclick*="toggleNotifDropdown"]')) {
            dd.classList.remove('active');
        }
    });
    function markAllRead() {
        showToast('success', 'Notifikasi', 'Semua notifikasi telah ditandai dibaca');
        document.querySelector('.notif-dot')?.remove();
    }
    
    // ===== TOAST SYSTEM =====
    function showToast(type, title, desc) {
        const container = document.getElementById('toastContainer');
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        const iconMap = { success: 'fa-check', warning: 'fa-triangle-exclamation', danger: 'fa-xmark', info: 'fa-info' };
        toast.innerHTML = `
            <div class="toast-icon"><i class="fa-solid ${iconMap[type]}"></i></div>
            <div class="toast-content">
                <div class="toast-title">${title}</div>
                <div class="toast-desc">${desc}</div>
            </div>
        `;
        container.appendChild(toast);
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(100%)';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }
    window.showToast = showToast;
    
    // ===== FULLSCREEN =====
    function toggleFullscreen() {
        if (!document.fullscreenElement) document.documentElement.requestFullscreen().catch(err => console.log(err));
        else document.exitFullscreen();
    }
    
    // Close sidebar on mobile when clicking outside
    document.addEventListener('click', function(e) {
        const sidebar = document.getElementById('sidebar');
        if (window.innerWidth <= 992 && sidebar.classList.contains('open') && 
            !sidebar.contains(e.target) && !e.target.closest('.mobile-toggle')) {
            sidebar.classList.remove('open');
        }
    });
    </script>
    
    <?= $extra_scripts ?? '' ?>
</body>
</html>