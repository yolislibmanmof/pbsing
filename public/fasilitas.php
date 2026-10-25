<?php
require_once '../includes/config.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';
check_ip_security($pdo);

$page_title = 'Fasilitas';
require_once '../includes/public_header.php';

$setting = get_all('settings', 'id = 1')[0];
$current_lang = $_COOKIE['pbsi_lang'] ?? 'id';

// Filter
$filter_jenis = sanitize($_GET['jenis'] ?? 'all');
$sql = "SELECT * FROM fasilitas WHERE status = 'aktif'";
$params = [];
if ($filter_jenis !== 'all') { $sql .= " AND jenis = ?"; $params[] = $filter_jenis; }
$sql .= " ORDER BY urutan ASC, nama ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$fasilitas = $stmt->fetchAll();

// Stats
$total_fas = count($fasilitas);
$total_lab = count(array_filter($fasilitas, fn($f) => in_array($f['jenis'], ['laboratorium', 'studio'])));
$total_kap = array_sum(array_column($fasilitas, 'kapasitas'));
$jenis_list = ['laboratorium', 'studio', 'ruangan', 'peralatan', 'lainnya'];

function jenis_icon($jenis) {
    $map = ['laboratorium' => 'fa-flask', 'studio' => 'fa-microphone-lines', 'ruangan' => 'fa-door-open', 'peralatan' => 'fa-laptop', 'lainnya' => 'fa-cube'];
    return $map[$jenis] ?? 'fa-building';
}

function jenis_color($jenis) {
    $map = ['laboratorium' => '#8A1538', 'studio' => '#9b59b6', 'ruangan' => '#5BA0C9', 'peralatan' => '#f39c12', 'lainnya' => '#2E8B57'];
    return $map[$jenis] ?? '#8A1538';
}

function img_url_fas($path) {
    $path = trim((string)$path);
    if ($path === '') return '';
    if (preg_match('~^https?://~i', $path)) return $path;
    $clean = ltrim(str_replace('\\', '/', $path), '/');
    if (file_exists(__DIR__ . '/' . $clean)) return PUBLIC_URL . $clean;
    if (file_exists(__DIR__ . '/../' . $clean)) return BASE_URL . $clean;
    return '';
}
?>

<main id="main-content" role="main">

<!-- ============ PAGE HERO ============ -->
<section class="page-hero fas-hero">
    <div class="fas-hero-content">
        <span class="fas-hero-badge">
            <i class="fa-solid fa-building" aria-hidden="true"></i>
            <span data-i18n="fas_badge">FASILITAS</span>
        </span>
        <h1 class="fas-hero-title" data-i18n="fas_title">Fasilitas Program Studi</h1>
        <p class="fas-hero-subtitle" data-i18n="fas_subtitle">
            Laboratorium bahasa, studio penerjemahan, perpustakaan, dan ruang kolaborasi modern untuk mendukung pembelajaran.
        </p>
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="<?= PUBLIC_URL ?>index.php" data-i18n="home">Beranda</a>
            <span aria-hidden="true"><i class="fa-solid fa-chevron-right"></i></span>
            <span aria-current="page" data-i18n="fas_breadcrumb">Fasilitas</span>
        </nav>
    </div>
    <div class="fas-hero-decor" aria-hidden="true">
        <div class="fas-orb fas-orb-1"></div>
        <div class="fas-orb fas-orb-2"></div>
    </div>
</section>

<!-- ============ STATS BAR ============ -->
<section class="fas-stats-section">
    <div class="section">
        <div class="fas-stats-grid">
            <div class="fas-stat-card">
                <div class="fas-stat-icon"><i class="fa-solid fa-building"></i></div>
                <div class="fas-stat-value"><?= $total_fas ?></div>
                <div class="fas-stat-label" data-i18n="fas_total">Total Fasilitas</div>
            </div>
            <div class="fas-stat-card accent-sky">
                <div class="fas-stat-icon"><i class="fa-solid fa-flask"></i></div>
                <div class="fas-stat-value"><?= $total_lab ?></div>
                <div class="fas-stat-label" data-i18n="fas_lab">Lab & Studio</div>
            </div>
            <div class="fas-stat-card accent-emerald">
                <div class="fas-stat-icon"><i class="fa-solid fa-users"></i></div>
                <div class="fas-stat-value"><?= number_format($total_kap) ?></div>
                <div class="fas-stat-label" data-i18n="fas_capacity">Total Kapasitas</div>
            </div>
        </div>
    </div>
</section>

<!-- ============ FILTER ============ -->
<section class="section fas-filter-section">
    <div class="fas-cat-filters">
        <a href="?jenis=all" class="fas-cat-btn <?= $filter_jenis === 'all' ? 'active' : '' ?>">
            <i class="fa-solid fa-border-all"></i> <?= $current_lang === 'en' ? 'All' : 'Semua' ?>
        </a>
        <?php foreach ($jenis_list as $j): ?>
        <a href="?jenis=<?= urlencode($j) ?>" class="fas-cat-btn <?= $filter_jenis === $j ? 'active' : '' ?>" style="--accent: <?= jenis_color($j) ?>;">
            <i class="fa-solid <?= jenis_icon($j) ?>"></i> <?= ucfirst($j) ?>
        </a>
        <?php endforeach; ?>
    </div>
</section>

<!-- ============ FACILITIES GRID ============ -->
<section class="section fas-list-section">
    <?php if (empty($fasilitas)): ?>
    <div class="fas-empty">
        <div class="fas-empty-icon"><i class="fa-solid fa-building"></i></div>
        <h2 data-i18n="fas_empty_title">Fasilitas Belum Tersedia</h2>
        <p data-i18n="fas_empty_desc">Informasi fasilitas sedang dalam proses penyusunan.</p>
    </div>
    <?php else: ?>
    <div class="fas-grid">
        <?php foreach ($fasilitas as $f): 
            $img = img_url_fas($f['gambar']);
            $color = jenis_color($f['jenis']);
        ?>
        <div class="fas-card">
            <div class="fas-card-img" style="<?= $img ? "background-image:url('$img');" : "background:linear-gradient(135deg, {$color}30, {$color}10);" ?>">
                <?php if (!$img): ?><i class="fa-solid <?= jenis_icon($f['jenis']) ?>" style="font-size:3rem;color:<?= $color ?>;opacity:.5;"></i><?php endif; ?>
                <span class="fas-card-jenis" style="background:<?= $color ?>;"><i class="fa-solid <?= jenis_icon($f['jenis']) ?>"></i> <?= ucfirst(htmlspecialchars($f['jenis'])) ?></span>
            </div>
            <div class="fas-card-body">
                <h3 class="fas-card-title"><?= htmlspecialchars($f['nama']) ?></h3>
                <?php if ($f['lokasi']): ?>
                <div class="fas-card-location"><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($f['lokasi']) ?></div>
                <?php endif; ?>
                <?php if ($f['deskripsi']): ?>
                <p class="fas-card-desc"><?= htmlspecialchars($f['deskripsi']) ?></p>
                <?php endif; ?>
                <?php if ($f['kapasitas']): ?>
                <div class="fas-card-capacity"><i class="fa-solid fa-users"></i> <?= $current_lang === 'en' ? 'Capacity' : 'Kapasitas' ?>: <strong><?= number_format($f['kapasitas']) ?></strong> <?= $current_lang === 'en' ? 'people' : 'orang' ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>

<!-- ============ CTA SECTION ============ -->
<section class="fas-cta-section">
    <div class="fas-cta-bg" aria-hidden="true">
        <div class="fas-cta-orb fas-cta-orb-1"></div>
        <div class="fas-cta-orb fas-cta-orb-2"></div>
    </div>
    <div class="fas-cta-box">
        <div class="fas-cta-icon"><i class="fa-solid fa-calendar-check"></i></div>
        <h2 data-i18n="fas_cta_title">Ingin Melihat Langsung?</h2>
        <p data-i18n="fas_cta_desc">Jadwalkan kunjungan kampus untuk melihat fasilitas kami secara langsung.</p>
        <div class="fas-cta-buttons">
            <a href="<?= PUBLIC_URL ?>kontak.php" class="btn-primary magnetic-btn">
                <i class="fa-solid fa-envelope"></i>
                <span data-i18n="fas_cta_contact">Jadwalkan Kunjungan</span>
            </a>
        </div>
    </div>
</section>

</main>

<style>
/* ===== HERO ===== */
.fas-hero { position: relative; padding: 11rem 1.5rem 5rem; overflow: hidden; }
.fas-hero-content { max-width: 900px; margin: 0 auto; position: relative; z-index: 2; text-align: center; }
.fas-hero-badge { display: inline-flex; align-items: center; gap: .6rem; padding: .5rem 1.3rem; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); backdrop-filter: blur(10px); border-radius: 50px; font-size: .75rem; font-weight: 800; letter-spacing: 3px; color: var(--sky-blue); margin-bottom: 1.5rem; }
.fas-hero-badge i { color: var(--crimson); }
.fas-hero-title { font-size: clamp(2.2rem, 7vw, 4.5rem); font-weight: 900; line-height: 1.05; margin-bottom: 1.2rem; background: linear-gradient(135deg, var(--sky-blue), #fff); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent; }
.fas-hero-subtitle { font-size: 1.2rem; color: #cfd8dc; max-width: 700px; margin: 0 auto 2rem; line-height: 1.7; }
.fas-hero .breadcrumb { justify-content: center; font-size: .9rem; }
.fas-hero .breadcrumb i { font-size: .7rem; color: var(--sky-blue); }
.fas-hero-decor { position: absolute; inset: 0; pointer-events: none; overflow: hidden; }
.fas-orb { position: absolute; border-radius: 50%; filter: blur(80px); opacity: .4; }
.fas-orb-1 { width: 400px; height: 400px; background: var(--crimson); top: -100px; left: -100px; animation: fasOrbFloat 20s ease-in-out infinite; }
.fas-orb-2 { width: 350px; height: 350px; background: var(--sky-blue-dark); bottom: -100px; right: -100px; animation: fasOrbFloat 25s ease-in-out infinite reverse; }
@keyframes fasOrbFloat { 0%, 100% { transform: translate(0, 0) scale(1); } 33% { transform: translate(50px, -30px) scale(1.1); } 66% { transform: translate(-30px, 50px) scale(0.9); } }

/* ===== STATS ===== */
.fas-stats-section { background: var(--bg-light); padding: 3rem 0; }
[data-theme="dark"] .fas-stats-section { background: var(--rich-black); }
.fas-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; }
.fas-stat-card { background: #fff; border-radius: 16px; padding: 1.5rem; text-align: center; box-shadow: 0 8px 25px rgba(0,0,0,.06); border-top: 4px solid var(--crimson); transition: transform .3s ease; }
[data-theme="dark"] .fas-stat-card { background: #1a1a2e; }
.fas-stat-card:hover { transform: translateY(-5px); }
.fas-stat-card.accent-sky { border-top-color: var(--sky-blue); }
.fas-stat-card.accent-emerald { border-top-color: var(--emerald); }
.fas-stat-icon { width: 50px; height: 50px; border-radius: 12px; background: rgba(138,21,56,.1); color: var(--crimson); display: flex; align-items: center; justify-content: center; font-size: 1.3rem; margin: 0 auto 1rem; }
.fas-stat-card.accent-sky .fas-stat-icon { background: rgba(135,206,235,.1); color: var(--sky-blue); }
.fas-stat-card.accent-emerald .fas-stat-icon { background: rgba(46,139,87,.1); color: var(--emerald); }
.fas-stat-value { font-size: 2rem; font-weight: 900; color: var(--rich-black); line-height: 1; }
[data-theme="dark"] .fas-stat-value { color: #fff; }
.fas-stat-label { color: var(--text-grey); font-size: .85rem; margin-top: .3rem; }

/* ===== FILTER ===== */
.fas-filter-section { padding-bottom: 0; }
.fas-cat-filters { display: flex; flex-wrap: wrap; gap: .6rem; justify-content: center; }
.fas-cat-btn { display: inline-flex; align-items: center; gap: .5rem; padding: .6rem 1.2rem; border-radius: 50px; background: #fff; color: var(--text-grey); font-size: .85rem; font-weight: 600; text-decoration: none; transition: all .25s ease; border: 1px solid #e0e0e0; }
[data-theme="dark"] .fas-cat-btn { background: #1a1a2e; border-color: #2a2a3e; }
.fas-cat-btn:hover, .fas-cat-btn.active { background: var(--accent, var(--crimson)); color: #fff; border-color: var(--accent, var(--crimson)); transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,.15); }

/* ===== GRID ===== */
.fas-list-section { padding-top: 2rem; }
.fas-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.8rem; }
.fas-card { background: #fff; border-radius: 20px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,.07); transition: all .35s ease; border: 1px solid transparent; }
[data-theme="dark"] .fas-card { background: #1a1a2e; }
.fas-card:hover { transform: translateY(-8px); box-shadow: 0 20px 50px rgba(0,0,0,.14); border-color: rgba(138,21,56,.2); }
.fas-card-img { height: 200px; background-size: cover; background-position: center; background-color: #f0f0f5; display: flex; align-items: center; justify-content: center; position: relative; }
[data-theme="dark"] .fas-card-img { background-color: #0f0f1a; }
.fas-card-jenis { position: absolute; top: 12px; left: 12px; display: inline-flex; align-items: center; gap: .4rem; padding: .3rem .8rem; border-radius: 50px; color: #fff; font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; box-shadow: 0 4px 12px rgba(0,0,0,.2); }
.fas-card-body { padding: 1.4rem; }
.fas-card-title { font-size: 1.15rem; font-weight: 800; color: var(--rich-black); margin: 0 0 .6rem; line-height: 1.3; }
[data-theme="dark"] .fas-card-title { color: #fff; }
.fas-card-location { display: flex; align-items: center; gap: .5rem; font-size: .82rem; color: var(--crimson); font-weight: 600; margin-bottom: .7rem; }
.fas-card-desc { font-size: .88rem; color: var(--text-grey); line-height: 1.6; margin: 0 0 .8rem; }
.fas-card-capacity { display: inline-flex; align-items: center; gap: .5rem; font-size: .82rem; color: var(--text-grey); background: var(--bg-light); padding: .4rem .9rem; border-radius: 50px; }
[data-theme="dark"] .fas-card-capacity { background: #0f0f1a; }
.fas-card-capacity strong { color: var(--emerald); }

/* ===== EMPTY ===== */
.fas-empty { text-align: center; padding: 5rem 2rem; background: #fff; border-radius: 24px; box-shadow: 0 10px 40px rgba(0,0,0,.08); max-width: 700px; margin: 0 auto; }
[data-theme="dark"] .fas-empty { background: #1a1a2e; }
.fas-empty-icon { width: 120px; height: 120px; margin: 0 auto 1.5rem; background: linear-gradient(135deg, rgba(138,21,56,0.1), rgba(135,206,235,0.1)); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 3.5rem; color: var(--crimson); }
.fas-empty h2 { color: var(--rich-black); margin-bottom: .5rem; font-size: 1.5rem; font-weight: 800; }
[data-theme="dark"] .fas-empty h2 { color: #fff; }
.fas-empty p { color: var(--text-grey); }

/* ===== CTA ===== */
.fas-cta-section { padding: 6rem 1.5rem; background: linear-gradient(180deg, #fff 0%, var(--bg-light) 100%); position: relative; overflow: hidden; }
[data-theme="dark"] .fas-cta-section { background: linear-gradient(180deg, var(--rich-black), #0f0f1a); }
.fas-cta-bg { position: absolute; inset: 0; pointer-events: none; }
.fas-cta-orb { position: absolute; border-radius: 50%; filter: blur(80px); opacity: .3; }
.fas-cta-orb-1 { width: 400px; height: 400px; background: var(--crimson); top: -150px; left: -100px; animation: fasOrbFloat 25s ease-in-out infinite; }
.fas-cta-orb-2 { width: 350px; height: 350px; background: var(--sky-blue); bottom: -150px; right: -100px; animation: fasOrbFloat 30s ease-in-out infinite reverse; }
.fas-cta-box { max-width: 800px; margin: 0 auto; text-align: center; padding: 4rem 3rem; background: linear-gradient(135deg, var(--crimson), var(--crimson-light, #A02050)); border-radius: 28px; color: #fff; box-shadow: 0 30px 80px rgba(138,21,56,.35); position: relative; overflow: hidden; z-index: 2; }
.fas-cta-icon { width: 90px; height: 90px; margin: 0 auto 1.5rem; border-radius: 50%; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; font-size: 2.5rem; backdrop-filter: blur(10px); position: relative; z-index: 2; }
.fas-cta-box h2 { font-size: clamp(1.5rem, 4vw, 2.2rem); margin-bottom: 1rem; font-weight: 900; position: relative; z-index: 2; }
.fas-cta-box p { color: rgba(255,255,255,.9); margin-bottom: 2rem; line-height: 1.8; max-width: 600px; margin-left: auto; margin-right: auto; position: relative; z-index: 2; }
.fas-cta-buttons { display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap; position: relative; z-index: 2; }
.fas-cta-buttons .btn-primary { background: #fff; color: var(--crimson); margin-top: 0; box-shadow: 0 12px 35px rgba(0,0,0,.25); }

@media (max-width: 768px) {
    .fas-hero { padding: 9rem 1rem 4rem; }
    .fas-grid { grid-template-columns: 1fr; }
    .fas-cta-box { padding: 2.5rem 1.5rem; }
}
</style>

<script>
(function () {
    'use strict';
    const revealObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) { entry.target.style.opacity = '1'; entry.target.style.transform = 'translateY(0)'; revealObserver.unobserve(entry.target); }
        });
    }, { threshold: 0.1 });
    document.querySelectorAll('.fas-card').forEach((el, i) => {
        el.style.opacity = '0'; el.style.transform = 'translateY(30px)';
        el.style.transition = `all 0.6s cubic-bezier(0.22, 0.61, 0.36, 1) ${i * 0.05}s`;
        revealObserver.observe(el);
    });
    document.querySelectorAll('.magnetic-btn').forEach(el => {
        el.addEventListener('mousemove', (e) => { const r = el.getBoundingClientRect(); el.style.transform = `translate(${(e.clientX-r.left-r.width/2)*0.15}px, ${(e.clientY-r.top-r.height/2)*0.15}px)`; });
        el.addEventListener('mouseleave', () => { el.style.transform = ''; });
    });
})();
</script>

<script>
(function () {
    'use strict';
    const translations = { en: {
        'fas_badge': 'FACILITIES', 'fas_title': 'Study Program Facilities', 'fas_subtitle': 'Language laboratories, translation studios, library, and modern collaboration spaces to support learning.',
        'home': 'Home', 'fas_breadcrumb': 'Facilities', 'fas_total': 'Total Facilities', 'fas_lab': 'Labs & Studios', 'fas_capacity': 'Total Capacity',
        'fas_empty_title': 'Facilities Not Available', 'fas_empty_desc': 'Facility information is being compiled.', 'fas_cta_title': 'Want to See in Person?', 'fas_cta_desc': 'Schedule a campus visit to see our facilities firsthand.', 'fas_cta_contact': 'Schedule a Visit'
    }};
    function applyTranslations() { const lang = document.documentElement.getAttribute('lang') || 'id'; if (lang === 'en' && translations.en) { document.querySelectorAll('[data-i18n]').forEach(el => { const k = el.dataset.i18n; if (translations.en[k]) el.textContent = translations.en[k]; }); } }
    const obs = new MutationObserver((m) => { m.forEach((x) => { if (x.attributeName === 'lang') applyTranslations(); }); });
    obs.observe(document.documentElement, { attributes: true }); applyTranslations();
})();
</script>

<?php require_once '../includes/public_footer.php'; ?>