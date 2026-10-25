<?php
require_once '../includes/config.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';
check_ip_security($pdo);

$page_title = 'Mitra & Kerja Sama';
require_once '../includes/public_header.php';

$setting = get_all('settings', 'id = 1')[0];
$current_lang = $_COOKIE['pbsi_lang'] ?? 'id';

$filter_jenis = sanitize($_GET['jenis'] ?? 'all');
$sql = "SELECT * FROM mitra WHERE status = 'aktif'";
$params = [];
if ($filter_jenis !== 'all') { $sql .= " AND jenis = ?"; $params[] = $filter_jenis; }
$sql .= " ORDER BY urutan ASC, nama ASC";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$mitra = $stmt->fetchAll();

$total_all = count($mitra);
$jenis_list = ['industri','universitas','pemerintah','komunitas','penerbit','organisasi'];
$count_per_jenis = [];
foreach ($jenis_list as $j) { $count_per_jenis[$j] = count(array_filter($mitra, fn($m) => $m['jenis'] === $j)); }

function jenis_mitra_icon($j) {
    $map = ['industri'=>'fa-industry','universitas'=>'fa-school','pemerintah'=>'fa-landmark','komunitas'=>'fa-people-group','penerbit'=>'fa-book','organisasi'=>'fa-sitemap'];
    return $map[$j] ?? 'fa-handshake';
}
function jenis_mitra_color($j) {
    $map = ['industri'=>'#8A1538','universitas'=>'#5BA0C9','pemerintah'=>'#f39c12','komunitas'=>'#2E8B57','penerbit'=>'#9b59b6','organisasi'=>'#EC4899'];
    return $map[$j] ?? '#8A1538';
}
function img_url_mitra($path) {
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
<section class="page-hero mtr-hero">
    <div class="mtr-hero-content">
        <span class="mtr-hero-badge">
            <i class="fa-solid fa-handshake" aria-hidden="true"></i>
            <span data-i18n="mtr_badge">MITRA & KERJA SAMA</span>
        </span>
        <h1 class="mtr-hero-title" data-i18n="mtr_title">Mitra & Jaringan Kerja Sama</h1>
        <p class="mtr-hero-subtitle" data-i18n="mtr_subtitle">
            Kolaborasi strategis dengan industri, universitas, pemerintah, dan organisasi untuk memperluas peluang mahasiswa.
        </p>
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="<?= PUBLIC_URL ?>index.php" data-i18n="home">Beranda</a>
            <span aria-hidden="true"><i class="fa-solid fa-chevron-right"></i></span>
            <span aria-current="page" data-i18n="mtr_breadcrumb">Mitra</span>
        </nav>
    </div>
    <div class="mtr-hero-decor" aria-hidden="true">
        <div class="mtr-orb mtr-orb-1"></div>
        <div class="mtr-orb mtr-orb-2"></div>
    </div>
</section>

<!-- ============ STATS BAR ============ -->
<section class="mtr-stats-section">
    <div class="section">
        <div class="mtr-stats-grid">
            <div class="mtr-stat-card">
                <div class="mtr-stat-icon"><i class="fa-solid fa-handshake"></i></div>
                <div class="mtr-stat-value"><?= $total_all ?></div>
                <div class="mtr-stat-label" data-i18n="mtr_total">Total Mitra</div>
            </div>
            <?php foreach (['industri','universitas','organisasi'] as $jk): if ($count_per_jenis[$jk] > 0): ?>
            <div class="mtr-stat-card" style="border-top-color: <?= jenis_mitra_color($jk) ?>;">
                <div class="mtr-stat-icon" style="background: <?= jenis_mitra_color($jk) ?>20; color: <?= jenis_mitra_color($jk) ?>;"><i class="fa-solid <?= jenis_mitra_icon($jk) ?>"></i></div>
                <div class="mtr-stat-value"><?= $count_per_jenis[$jk] ?></div>
                <div class="mtr-stat-label"><?= ucfirst($jk) ?></div>
            </div>
            <?php endif; endforeach; ?>
        </div>
    </div>
</section>

<!-- ============ LOGO MARQUEE (semua mitra) ============ -->
<?php if (!empty($mitra)): ?>
<section class="mtr-marquee-section">
    <div class="section">
        <p class="mtr-marquee-label" data-i18n="mtr_trusted">Dipercaya oleh mitra terkemuka</p>
        <div class="mtr-marquee">
            <div class="mtr-marquee-track">
                <?php for ($r = 0; $r < 2; $r++) { foreach ($mitra as $m): $logo = img_url_mitra($m['logo']); ?>
                <div class="mtr-marquee-item" title="<?= htmlspecialchars($m['nama']) ?>">
                    <?php if ($logo): ?><img src="<?= htmlspecialchars($logo) ?>" alt="<?= htmlspecialchars($m['nama']) ?>"><?php else: ?><i class="fa-solid <?= jenis_mitra_icon($m['jenis']) ?>" style="color:<?= jenis_mitra_color($m['jenis']) ?>;"></i><span><?= htmlspecialchars($m['nama']) ?></span><?php endif; ?>
                </div>
                <?php endforeach; } ?>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============ FILTER ============ -->
<section class="section mtr-filter-section">
    <div class="mtr-cat-filters">
        <a href="?jenis=all" class="mtr-cat-btn <?= $filter_jenis === 'all' ? 'active' : '' ?>">
            <i class="fa-solid fa-border-all"></i> <?= $current_lang === 'en' ? 'All' : 'Semua' ?>
        </a>
        <?php foreach ($jenis_list as $j): if ($count_per_jenis[$j] > 0): ?>
        <a href="?jenis=<?= urlencode($j) ?>" class="mtr-cat-btn <?= $filter_jenis === $j ? 'active' : '' ?>" style="--accent: <?= jenis_mitra_color($j) ?>;">
            <i class="fa-solid <?= jenis_mitra_icon($j) ?>"></i> <?= ucfirst($j) ?>
        </a>
        <?php endif; endforeach; ?>
    </div>
</section>

<!-- ============ MITRA GRID ============ -->
<section class="section mtr-list-section">
    <?php if (empty($mitra)): ?>
    <div class="mtr-empty">
        <div class="mtr-empty-icon"><i class="fa-solid fa-handshake"></i></div>
        <h2 data-i18n="mtr_empty_title">Belum Ada Mitra</h2>
        <p data-i18n="mtr_empty_desc">Data mitra kerja sama sedang dalam proses penyusunan.</p>
    </div>
    <?php else: ?>
    <div class="mtr-grid">
        <?php foreach ($mitra as $m): 
            $logo = img_url_mitra($m['logo']);
            $color = jenis_mitra_color($m['jenis']);
        ?>
        <div class="mtr-card">
            <div class="mtr-card-logo" style="<?= $logo ? '' : "background:linear-gradient(135deg, {$color}15, {$color}05);" ?>">
                <?php if ($logo): ?><img src="<?= htmlspecialchars($logo) ?>" alt="<?= htmlspecialchars($m['nama']) ?>"><?php else: ?><i class="fa-solid <?= jenis_mitra_icon($m['jenis']) ?>" style="font-size:2.5rem;color:<?= $color ?>;"></i><?php endif; ?>
            </div>
            <div class="mtr-card-body">
                <span class="mtr-card-jenis" style="background:<?= $color ?>20;color:<?= $color ?>;"><i class="fa-solid <?= jenis_mitra_icon($m['jenis']) ?>"></i> <?= ucfirst(htmlspecialchars($m['jenis'])) ?></span>
                <h3 class="mtr-card-name"><?= htmlspecialchars($m['nama']) ?></h3>
                <?php if ($m['deskripsi']): ?><p class="mtr-card-desc"><?= htmlspecialchars($m['deskripsi']) ?></p><?php endif; ?>
                <?php if ($m['website']): ?>
                <a href="<?= htmlspecialchars($m['website']) ?>" target="_blank" rel="noopener" class="mtr-card-link" style="color:<?= $color ?>;">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i> <?= htmlspecialchars(parse_url($m['website'], PHP_URL_HOST) ?: $m['website']) ?>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>

<!-- ============ CTA ============ -->
<section class="mtr-cta-section">
    <div class="mtr-cta-bg" aria-hidden="true">
        <div class="mtr-cta-orb mtr-cta-orb-1"></div>
        <div class="mtr-cta-orb mtr-cta-orb-2"></div>
    </div>
    <div class="mtr-cta-box">
        <div class="mtr-cta-icon"><i class="fa-solid fa-handshake"></i></div>
        <h2 data-i18n="mtr_cta_title">Tertarik Berkolaborasi?</h2>
        <p data-i18n="mtr_cta_desc">Kami terbuka untuk menjalin kerja sama dengan industri, institusi pendidikan, dan organisasi.</p>
        <div class="mtr-cta-buttons">
            <a href="<?= PUBLIC_URL ?>kontak.php" class="btn-primary magnetic-btn">
                <i class="fa-solid fa-envelope"></i>
                <span data-i18n="mtr_cta_contact">Ajukan Kerja Sama</span>
            </a>
        </div>
    </div>
</section>

</main>

<style>
/* ===== HERO ===== */
.mtr-hero { position: relative; padding: 11rem 1.5rem 5rem; overflow: hidden; }
.mtr-hero-content { max-width: 900px; margin: 0 auto; position: relative; z-index: 2; text-align: center; }
.mtr-hero-badge { display: inline-flex; align-items: center; gap: .6rem; padding: .5rem 1.3rem; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); backdrop-filter: blur(10px); border-radius: 50px; font-size: .75rem; font-weight: 800; letter-spacing: 3px; color: var(--sky-blue); margin-bottom: 1.5rem; }
.mtr-hero-badge i { color: var(--crimson); }
.mtr-hero-title { font-size: clamp(2.2rem, 7vw, 4.5rem); font-weight: 900; line-height: 1.05; margin-bottom: 1.2rem; background: linear-gradient(135deg, var(--sky-blue), #fff); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent; }
.mtr-hero-subtitle { font-size: 1.2rem; color: #cfd8dc; max-width: 700px; margin: 0 auto 2rem; line-height: 1.7; }
.mtr-hero .breadcrumb { justify-content: center; font-size: .9rem; }
.mtr-hero .breadcrumb i { font-size: .7rem; color: var(--sky-blue); }
.mtr-hero-decor { position: absolute; inset: 0; pointer-events: none; overflow: hidden; }
.mtr-orb { position: absolute; border-radius: 50%; filter: blur(80px); opacity: .4; }
.mtr-orb-1 { width: 400px; height: 400px; background: var(--crimson); top: -100px; left: -100px; animation: mtrOrbFloat 20s ease-in-out infinite; }
.mtr-orb-2 { width: 350px; height: 350px; background: var(--sky-blue-dark); bottom: -100px; right: -100px; animation: mtrOrbFloat 25s ease-in-out infinite reverse; }
@keyframes mtrOrbFloat { 0%, 100% { transform: translate(0, 0) scale(1); } 33% { transform: translate(50px, -30px) scale(1.1); } 66% { transform: translate(-30px, 50px) scale(0.9); } }

/* ===== STATS ===== */
.mtr-stats-section { background: var(--bg-light); padding: 3rem 0; }
[data-theme="dark"] .mtr-stats-section { background: var(--rich-black); }
.mtr-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1.5rem; }
.mtr-stat-card { background: #fff; border-radius: 16px; padding: 1.5rem; text-align: center; box-shadow: 0 8px 25px rgba(0,0,0,.06); border-top: 4px solid var(--crimson); transition: transform .3s ease; }
[data-theme="dark"] .mtr-stat-card { background: #1a1a2e; }
.mtr-stat-card:hover { transform: translateY(-5px); }
.mtr-stat-icon { width: 50px; height: 50px; border-radius: 12px; background: rgba(138,21,56,.1); color: var(--crimson); display: flex; align-items: center; justify-content: center; font-size: 1.3rem; margin: 0 auto 1rem; }
.mtr-stat-value { font-size: 2rem; font-weight: 900; color: var(--rich-black); line-height: 1; }
[data-theme="dark"] .mtr-stat-value { color: #fff; }
.mtr-stat-label { color: var(--text-grey); font-size: .85rem; margin-top: .3rem; }

/* ===== MARQUEE ===== */
.mtr-marquee-section { padding: 2rem 0; background: #fff; border-top: 1px solid #eee; border-bottom: 1px solid #eee; }
[data-theme="dark"] .mtr-marquee-section { background: #1a1a2e; border-color: #2a2a3e; }
.mtr-marquee-label { text-align: center; font-size: .8rem; text-transform: uppercase; letter-spacing: 2px; color: var(--text-grey); font-weight: 700; margin-bottom: 1.5rem; }
.mtr-marquee { overflow: hidden; position: relative; }
.mtr-marquee::before, .mtr-marquee::after { content:''; position:absolute; top:0; bottom:0; width:80px; z-index:2; pointer-events:none; }
.mtr-marquee::before { left:0; background:linear-gradient(90deg,#fff,transparent); }
.mtr-marquee::after { right:0; background:linear-gradient(-90deg,#fff,transparent); }
[data-theme="dark"] .mtr-marquee::before { background:linear-gradient(90deg,#1a1a2e,transparent); }
[data-theme="dark"] .mtr-marquee::after { background:linear-gradient(-90deg,#1a1a2e,transparent); }
.mtr-marquee-track { display: flex; gap: 3rem; width: max-content; animation: mtrScroll 35s linear infinite; align-items: center; }
.mtr-marquee:hover .mtr-marquee-track { animation-play-state: paused; }
@keyframes mtrScroll { to { transform: translateX(-50%); } }
.mtr-marquee-item { display: flex; align-items: center; gap: .6rem; opacity: .55; transition: opacity .3s ease; white-space: nowrap; }
.mtr-marquee-item:hover { opacity: 1; }
.mtr-marquee-item img { height: 45px; max-width: 140px; object-fit: contain; }
.mtr-marquee-item span { font-weight: 700; color: var(--text-grey); font-size: .9rem; }

/* ===== FILTER ===== */
.mtr-filter-section { padding-bottom: 0; }
.mtr-cat-filters { display: flex; flex-wrap: wrap; gap: .6rem; justify-content: center; }
.mtr-cat-btn { display: inline-flex; align-items: center; gap: .5rem; padding: .6rem 1.2rem; border-radius: 50px; background: #fff; color: var(--text-grey); font-size: .85rem; font-weight: 600; text-decoration: none; transition: all .25s ease; border: 1px solid #e0e0e0; }
[data-theme="dark"] .mtr-cat-btn { background: #1a1a2e; border-color: #2a2a3e; }
.mtr-cat-btn:hover, .mtr-cat-btn.active { background: var(--accent, var(--crimson)); color: #fff; border-color: var(--accent, var(--crimson)); transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,.15); }

/* ===== GRID ===== */
.mtr-list-section { padding-top: 2rem; }
.mtr-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.8rem; }
.mtr-card { background: #fff; border-radius: 20px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,.07); transition: all .35s ease; border: 1px solid transparent; text-align: center; }
[data-theme="dark"] .mtr-card { background: #1a1a2e; }
.mtr-card:hover { transform: translateY(-8px); box-shadow: 0 20px 50px rgba(0,0,0,.14); border-color: rgba(138,21,56,.2); }
.mtr-card-logo { height: 130px; display: flex; align-items: center; justify-content: center; padding: 1.5rem; background: #fafafa; }
[data-theme="dark"] .mtr-card-logo { background: #0f0f1a; }
.mtr-card-logo img { max-height: 90px; max-width: 80%; object-fit: contain; }
.mtr-card-body { padding: 1.3rem; }
.mtr-card-jenis { display: inline-flex; align-items: center; gap: .4rem; padding: .25rem .7rem; border-radius: 50px; font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; margin-bottom: .7rem; }
.mtr-card-name { font-size: 1.1rem; font-weight: 800; color: var(--rich-black); margin: 0 0 .5rem; line-height: 1.3; }
[data-theme="dark"] .mtr-card-name { color: #fff; }
.mtr-card-desc { font-size: .85rem; color: var(--text-grey); line-height: 1.6; margin: 0 0 .8rem; }
.mtr-card-link { display: inline-flex; align-items: center; gap: .4rem; font-size: .82rem; font-weight: 700; text-decoration: none; transition: gap .2s ease; }
.mtr-card-link:hover { gap: .7rem; }

/* ===== EMPTY ===== */
.mtr-empty { text-align: center; padding: 5rem 2rem; background: #fff; border-radius: 24px; box-shadow: 0 10px 40px rgba(0,0,0,.08); max-width: 700px; margin: 0 auto; }
[data-theme="dark"] .mtr-empty { background: #1a1a2e; }
.mtr-empty-icon { width: 120px; height: 120px; margin: 0 auto 1.5rem; background: linear-gradient(135deg, rgba(138,21,56,0.1), rgba(135,206,235,0.1)); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 3.5rem; color: var(--crimson); }
.mtr-empty h2 { color: var(--rich-black); margin-bottom: .5rem; font-size: 1.5rem; font-weight: 800; }
[data-theme="dark"] .mtr-empty h2 { color: #fff; }
.mtr-empty p { color: var(--text-grey); }

/* ===== CTA ===== */
.mtr-cta-section { padding: 6rem 1.5rem; background: linear-gradient(180deg, #fff 0%, var(--bg-light) 100%); position: relative; overflow: hidden; }
[data-theme="dark"] .mtr-cta-section { background: linear-gradient(180deg, var(--rich-black), #0f0f1a); }
.mtr-cta-bg { position: absolute; inset: 0; pointer-events: none; }
.mtr-cta-orb { position: absolute; border-radius: 50%; filter: blur(80px); opacity: .3; }
.mtr-cta-orb-1 { width: 400px; height: 400px; background: var(--crimson); top: -150px; left: -100px; animation: mtrOrbFloat 25s ease-in-out infinite; }
.mtr-cta-orb-2 { width: 350px; height: 350px; background: var(--sky-blue); bottom: -150px; right: -100px; animation: mtrOrbFloat 30s ease-in-out infinite reverse; }
.mtr-cta-box { max-width: 800px; margin: 0 auto; text-align: center; padding: 4rem 3rem; background: linear-gradient(135deg, var(--crimson), var(--crimson-light, #A02050)); border-radius: 28px; color: #fff; box-shadow: 0 30px 80px rgba(138,21,56,.35); position: relative; overflow: hidden; z-index: 2; }
.mtr-cta-icon { width: 90px; height: 90px; margin: 0 auto 1.5rem; border-radius: 50%; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; font-size: 2.5rem; backdrop-filter: blur(10px); position: relative; z-index: 2; }
.mtr-cta-box h2 { font-size: clamp(1.5rem, 4vw, 2.2rem); margin-bottom: 1rem; font-weight: 900; position: relative; z-index: 2; }
.mtr-cta-box p { color: rgba(255,255,255,.9); margin-bottom: 2rem; line-height: 1.8; max-width: 600px; margin-left: auto; margin-right: auto; position: relative; z-index: 2; }
.mtr-cta-buttons { display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap; position: relative; z-index: 2; }
.mtr-cta-buttons .btn-primary { background: #fff; color: var(--crimson); margin-top: 0; box-shadow: 0 12px 35px rgba(0,0,0,.25); }

@media (max-width: 768px) {
    .mtr-hero { padding: 9rem 1rem 4rem; }
    .mtr-grid { grid-template-columns: 1fr; }
    .mtr-cta-box { padding: 2.5rem 1.5rem; }
}
</style>

<script>
(function () {
    'use strict';
    const ro = new IntersectionObserver((es) => { es.forEach(e => { if (e.isIntersecting) { e.target.style.opacity='1'; e.target.style.transform='translateY(0)'; ro.unobserve(e.target); } }); }, { threshold: 0.1 });
    document.querySelectorAll('.mtr-card').forEach((el,i) => { el.style.opacity='0'; el.style.transform='translateY(30px)'; el.style.transition=`all 0.6s cubic-bezier(0.22,0.61,0.36,1) ${i*0.05}s`; ro.observe(el); });
    document.querySelectorAll('.magnetic-btn').forEach(el => { el.addEventListener('mousemove',(e)=>{const r=el.getBoundingClientRect();el.style.transform=`translate(${(e.clientX-r.left-r.width/2)*0.15}px,${(e.clientY-r.top-r.height/2)*0.15}px)`;}); el.addEventListener('mouseleave',()=>{el.style.transform='';}); });
})();
</script>

<script>
(function () {
    'use strict';
    const t = { en: { 'mtr_badge':'PARTNERS & COLLABORATION','mtr_title':'Partners & Collaboration Network','mtr_subtitle':'Strategic collaboration with industry, universities, government, and organizations to expand student opportunities.','home':'Home','mtr_breadcrumb':'Partners','mtr_total':'Total Partners','mtr_trusted':'Trusted by leading partners','mtr_empty_title':'No Partners Yet','mtr_empty_desc':'Partner data is being compiled.','mtr_cta_title':'Interested in Collaborating?','mtr_cta_desc':'We are open to establishing partnerships with industry, educational institutions, and organizations.','mtr_cta_contact':'Propose Collaboration' }};
    function apply(){ const l=document.documentElement.getAttribute('lang')||'id'; if(l==='en'&&t.en){document.querySelectorAll('[data-i18n]').forEach(el=>{const k=el.dataset.i18n;if(t.en[k])el.textContent=t.en[k];});} }
    new MutationObserver(m=>{m.forEach(x=>{if(x.attributeName==='lang')apply();});}).observe(document.documentElement,{attributes:true}); apply();
})();
</script>

<?php require_once '../includes/public_footer.php'; ?>