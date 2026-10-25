<?php
require_once '../includes/config.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';
check_ip_security($pdo);

$page_title = 'Prospek Karir';
require_once '../includes/public_header.php';

$setting = get_all('settings', 'id = 1')[0];
$current_lang = $_COOKIE['pbsi_lang'] ?? 'id';

$filter_permintaan = sanitize($_GET['permintaan'] ?? 'all');
$sql = "SELECT * FROM karir_paths WHERE status = 'aktif'";
$params = [];
if ($filter_permintaan !== 'all') { $sql .= " AND permintaan = ?"; $params[] = $filter_permintaan; }
$sql .= " ORDER BY urutan ASC, jabatan ASC";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$karir = $stmt->fetchAll();

$total_all = count($karir);
$total_hot = count(array_filter($karir, fn($k) => in_array($k['permintaan'], ['tinggi', 'sangat_tinggi'])));
$gaji_max_all = array_filter(array_column($karir, 'gaji_max'));
$avg_gaji = !empty($gaji_max_all) ? (int)(array_sum($gaji_max_all) / count($gaji_max_all)) : 0;
$permintaan_list = ['rendah', 'sedang', 'tinggi', 'sangat_tinggi'];

function fmt_rupiah($n) { return 'Rp ' . number_format((float)$n, 0, ',', '.'); }
function permintaan_style($p) {
    $map = ['sangat_tinggi' => ['bg' => 'linear-gradient(135deg,#f39c12,#f1c40f)', 'color' => '#000', 'label' => 'Sangat Tinggi'],
            'tinggi' => ['bg' => 'linear-gradient(135deg,#8A1538,#A02050)', 'color' => '#fff', 'label' => 'Tinggi'],
            'sedang' => ['bg' => 'rgba(135,206,235,.2)', 'color' => '#5BA0C9', 'label' => 'Sedang'],
            'rendah' => ['bg' => 'rgba(255,255,255,.08)', 'color' => '#8a8a9e', 'label' => 'Rendah']];
    return $map[$p] ?? $map['sedang'];
}
?>

<main id="main-content" role="main">

<!-- ============ PAGE HERO ============ -->
<section class="page-hero kar-hero">
    <div class="kar-hero-content">
        <span class="kar-hero-badge">
            <i class="fa-solid fa-briefcase" aria-hidden="true"></i>
            <span data-i18n="kar_badge">PROSPEK KARIR</span>
        </span>
        <h1 class="kar-hero-title" data-i18n="kar_title">Prospek Karir Lulusan</h1>
        <p class="kar-hero-subtitle" data-i18n="kar_subtitle">
            Lulusan PBSI berkarir di berbagai bidang: penerjemahan, pendidikan, diplomasi, media, sastra, dan riset linguistik.
        </p>
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="<?= PUBLIC_URL ?>index.php" data-i18n="home">Beranda</a>
            <span aria-hidden="true"><i class="fa-solid fa-chevron-right"></i></span>
            <span aria-current="page" data-i18n="kar_breadcrumb">Prospek Karir</span>
        </nav>
    </div>
    <div class="kar-hero-decor" aria-hidden="true">
        <div class="kar-orb kar-orb-1"></div>
        <div class="kar-orb kar-orb-2"></div>
    </div>
</section>

<!-- ============ STATS BAR ============ -->
<section class="kar-stats-section">
    <div class="section">
        <div class="kar-stats-grid">
            <div class="kar-stat-card">
                <div class="kar-stat-icon"><i class="fa-solid fa-briefcase"></i></div>
                <div class="kar-stat-value"><?= $total_all ?></div>
                <div class="kar-stat-label" data-i18n="kar_total">Jalur Karir</div>
            </div>
            <div class="kar-stat-card accent-gold">
                <div class="kar-stat-icon"><i class="fa-solid fa-fire"></i></div>
                <div class="kar-stat-value"><?= $total_hot ?></div>
                <div class="kar-stat-label" data-i18n="kar_hot">Permintaan Tinggi</div>
            </div>
            <div class="kar-stat-card accent-emerald">
                <div class="kar-stat-icon"><i class="fa-solid fa-sack-dollar"></i></div>
                <div class="kar-stat-value"><?= $avg_gaji ? fmt_rupiah($avg_gaji) : '-' ?></div>
                <div class="kar-stat-label" data-i18n="kar_avg">Rata-rata Gaji Maks</div>
            </div>
        </div>
    </div>
</section>

<!-- ============ FILTER ============ -->
<section class="section kar-filter-section">
    <div class="kar-cat-filters">
        <a href="?permintaan=all" class="kar-cat-btn <?= $filter_permintaan === 'all' ? 'active' : '' ?>">
            <i class="fa-solid fa-border-all"></i> <?= $current_lang === 'en' ? 'All' : 'Semua' ?>
        </a>
        <?php foreach ($permintaan_list as $p): $ps = permintaan_style($p); ?>
        <a href="?permintaan=<?= urlencode($p) ?>" class="kar-cat-btn <?= $filter_permintaan === $p ? 'active' : '' ?>" style="--accent-bg: <?= $ps['bg'] ?>; --accent-color: <?= $ps['color'] ?>;">
            <i class="fa-solid fa-signal"></i> <?= $ps['label'] ?>
        </a>
        <?php endforeach; ?>
    </div>
</section>

<!-- ============ CAREER PATHS ============ -->
<section class="section kar-list-section">
    <?php if (empty($karir)): ?>
    <div class="kar-empty">
        <div class="kar-empty-icon"><i class="fa-solid fa-briefcase"></i></div>
        <h2 data-i18n="kar_empty_title">Data Karir Belum Tersedia</h2>
        <p data-i18n="kar_empty_desc">Informasi prospek karir sedang dalam proses penyusunan.</p>
    </div>
    <?php else: ?>
    <div class="kar-grid">
        <?php foreach ($karir as $k): 
            $ps = permintaan_style($k['permintaan']);
        ?>
        <div class="kar-card">
            <div class="kar-card-header">
                <div class="kar-card-icon"><i class="fa-solid <?= htmlspecialchars($k['ikon']) ?>"></i></div>
                <span class="kar-card-demand" style="background:<?= $ps['bg'] ?>;color:<?= $ps['color'] ?>;">
                    <i class="fa-solid fa-signal"></i> <?= $ps['label'] ?>
                </span>
            </div>
            <h3 class="kar-card-title"><?= htmlspecialchars($k['jabatan']) ?></h3>
            <?php if ($k['deskripsi']): ?>
            <p class="kar-card-desc"><?= htmlspecialchars($k['deskripsi']) ?></p>
            <?php endif; ?>
            <?php if ($k['gaji_min'] || $k['gaji_max']): ?>
            <div class="kar-card-salary">
                <div class="kar-salary-label"><i class="fa-solid fa-sack-dollar"></i> <?= $current_lang === 'en' ? 'Salary Range' : 'Rentang Gaji' ?></div>
                <div class="kar-salary-value">
                    <?= $k['gaji_min'] ? fmt_rupiah($k['gaji_min']) : '?' ?>
                    <span class="kar-salary-sep">–</span>
                    <?= $k['gaji_max'] ? fmt_rupiah($k['gaji_max']) : '?' ?>
                </div>
                <div class="kar-salary-bar">
                    <div class="kar-salary-fill" style="width: <?= $k['gaji_max'] ? min(100, ($k['gaji_max'] / 30000000) * 100) : 50 ?>%;"></div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>

<!-- ============ ALUMNI PROOF (testimoni singkat) ============ -->
<?php
$alumni_proof = $pdo->query("SELECT nama, pekerjaan, perusahaan FROM alumni WHERE ditampilkan = 1 AND pekerjaan IS NOT NULL AND pekerjaan != '' ORDER BY urutan ASC LIMIT 3")->fetchAll();
if (!empty($alumni_proof)): ?>
<section class="kar-proof-section">
    <div class="section">
        <h2 class="kar-proof-title"><i class="fa-solid fa-user-graduate"></i> <?= $current_lang === 'en' ? 'Our Alumni Work As' : 'Alumni Kami Berkarir Sebagai' ?></h2>
        <div class="kar-proof-grid">
            <?php foreach ($alumni_proof as $a): ?>
            <div class="kar-proof-card">
                <div class="kar-proof-ava"><?= strtoupper(substr($a['nama'], 0, 1)) ?></div>
                <div class="kar-proof-info">
                    <strong><?= htmlspecialchars($a['pekerjaan']) ?></strong>
                    <?php if ($a['perusahaan']): ?><span>@ <?= htmlspecialchars($a['perusahaan']) ?></span><?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============ CTA ============ -->
<section class="kar-cta-section">
    <div class="kar-cta-bg" aria-hidden="true">
        <div class="kar-cta-orb kar-cta-orb-1"></div>
        <div class="kar-cta-orb kar-cta-orb-2"></div>
    </div>
    <div class="kar-cta-box">
        <div class="kar-cta-icon"><i class="fa-solid fa-rocket"></i></div>
        <h2 data-i18n="kar_cta_title">Siap Memulai Karirmu?</h2>
        <p data-i18n="kar_cta_desc">Bergabunglah dengan PBSI UMMaumere dan wujudkan masa depan profesionalmu di dunia bahasa global.</p>
        <div class="kar-cta-buttons">
            <a href="<?= PUBLIC_URL ?>kontak.php" class="btn-primary magnetic-btn">
                <i class="fa-solid fa-paper-plane"></i>
                <span data-i18n="kar_cta_contact">Daftar Sekarang</span>
            </a>
            <a href="<?= PUBLIC_URL ?>kurikulum.php" class="btn-secondary magnetic-btn" style="border-color:#fff;color:#fff;">
                <i class="fa-solid fa-book-open"></i>
                <span data-i18n="kar_cta_kurikulum">Lihat Kurikulum</span>
            </a>
        </div>
    </div>
</section>

</main>

<style>
/* ===== HERO ===== */
.kar-hero { position: relative; padding: 11rem 1.5rem 5rem; overflow: hidden; }
.kar-hero-content { max-width: 900px; margin: 0 auto; position: relative; z-index: 2; text-align: center; }
.kar-hero-badge { display: inline-flex; align-items: center; gap: .6rem; padding: .5rem 1.3rem; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); backdrop-filter: blur(10px); border-radius: 50px; font-size: .75rem; font-weight: 800; letter-spacing: 3px; color: var(--sky-blue); margin-bottom: 1.5rem; }
.kar-hero-badge i { color: var(--crimson); }
.kar-hero-title { font-size: clamp(2.2rem, 7vw, 4.5rem); font-weight: 900; line-height: 1.05; margin-bottom: 1.2rem; background: linear-gradient(135deg, var(--sky-blue), #fff); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent; }
.kar-hero-subtitle { font-size: 1.2rem; color: #cfd8dc; max-width: 700px; margin: 0 auto 2rem; line-height: 1.7; }
.kar-hero .breadcrumb { justify-content: center; font-size: .9rem; }
.kar-hero .breadcrumb i { font-size: .7rem; color: var(--sky-blue); }
.kar-hero-decor { position: absolute; inset: 0; pointer-events: none; overflow: hidden; }
.kar-orb { position: absolute; border-radius: 50%; filter: blur(80px); opacity: .4; }
.kar-orb-1 { width: 400px; height: 400px; background: var(--crimson); top: -100px; left: -100px; animation: karOrbFloat 20s ease-in-out infinite; }
.kar-orb-2 { width: 350px; height: 350px; background: var(--sky-blue-dark); bottom: -100px; right: -100px; animation: karOrbFloat 25s ease-in-out infinite reverse; }
@keyframes karOrbFloat { 0%, 100% { transform: translate(0, 0) scale(1); } 33% { transform: translate(50px, -30px) scale(1.1); } 66% { transform: translate(-30px, 50px) scale(0.9); } }

/* ===== STATS ===== */
.kar-stats-section { background: var(--bg-light); padding: 3rem 0; }
[data-theme="dark"] .kar-stats-section { background: var(--rich-black); }
.kar-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; }
.kar-stat-card { background: #fff; border-radius: 16px; padding: 1.5rem; text-align: center; box-shadow: 0 8px 25px rgba(0,0,0,.06); border-top: 4px solid var(--crimson); transition: transform .3s ease; }
[data-theme="dark"] .kar-stat-card { background: #1a1a2e; }
.kar-stat-card:hover { transform: translateY(-5px); }
.kar-stat-card.accent-gold { border-top-color: #f1c40f; }
.kar-stat-card.accent-emerald { border-top-color: var(--emerald); }
.kar-stat-icon { width: 50px; height: 50px; border-radius: 12px; background: rgba(138,21,56,.1); color: var(--crimson); display: flex; align-items: center; justify-content: center; font-size: 1.3rem; margin: 0 auto 1rem; }
.kar-stat-card.accent-gold .kar-stat-icon { background: rgba(241,196,15,.15); color: #f39c12; }
.kar-stat-card.accent-emerald .kar-stat-icon { background: rgba(46,139,87,.1); color: var(--emerald); }
.kar-stat-value { font-size: 1.7rem; font-weight: 900; color: var(--rich-black); line-height: 1.2; }
[data-theme="dark"] .kar-stat-value { color: #fff; }
.kar-stat-label { color: var(--text-grey); font-size: .85rem; margin-top: .3rem; }

/* ===== FILTER ===== */
.kar-filter-section { padding-bottom: 0; }
.kar-cat-filters { display: flex; flex-wrap: wrap; gap: .6rem; justify-content: center; }
.kar-cat-btn { display: inline-flex; align-items: center; gap: .5rem; padding: .6rem 1.2rem; border-radius: 50px; background: #fff; color: var(--text-grey); font-size: .85rem; font-weight: 600; text-decoration: none; transition: all .25s ease; border: 1px solid #e0e0e0; }
[data-theme="dark"] .kar-cat-btn { background: #1a1a2e; border-color: #2a2a3e; }
.kar-cat-btn:hover, .kar-cat-btn.active { background: var(--accent-bg, var(--crimson)); color: var(--accent-color, #fff); border-color: transparent; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,.15); }

/* ===== CAREER GRID ===== */
.kar-list-section { padding-top: 2rem; }
.kar-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.8rem; }
.kar-card { background: #fff; border-radius: 20px; padding: 1.8rem; box-shadow: 0 10px 30px rgba(0,0,0,.07); transition: all .35s ease; border: 1px solid transparent; position: relative; overflow: hidden; }
[data-theme="dark"] .kar-card { background: #1a1a2e; }
.kar-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, var(--crimson), var(--sky-blue)); transform: scaleX(0); transform-origin: left; transition: transform .4s ease; }
.kar-card:hover { transform: translateY(-8px); box-shadow: 0 20px 50px rgba(0,0,0,.14); border-color: rgba(138,21,56,.2); }
.kar-card:hover::before { transform: scaleX(1); }
.kar-card-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.2rem; }
.kar-card-icon { width: 60px; height: 60px; border-radius: 16px; background: linear-gradient(135deg, rgba(138,21,56,.12), rgba(135,206,235,.12)); color: var(--crimson); display: flex; align-items: center; justify-content: center; font-size: 1.6rem; transition: all .35s ease; }
.kar-card:hover .kar-card-icon { background: linear-gradient(135deg, var(--crimson), var(--crimson-light, #A02050)); color: #fff; transform: rotate(-8deg) scale(1.05); }
.kar-card-demand { display: inline-flex; align-items: center; gap: .35rem; padding: .3rem .75rem; border-radius: 50px; font-size: .7rem; font-weight: 800; letter-spacing: .3px; }
.kar-card-title { font-size: 1.2rem; font-weight: 800; color: var(--rich-black); margin: 0 0 .6rem; line-height: 1.3; }
[data-theme="dark"] .kar-card-title { color: #fff; }
.kar-card-desc { font-size: .88rem; color: var(--text-grey); line-height: 1.65; margin: 0 0 1.2rem; }
.kar-card-salary { background: var(--bg-light); border-radius: 14px; padding: 1rem 1.2rem; }
[data-theme="dark"] .kar-card-salary { background: #0f0f1a; }
.kar-salary-label { display: flex; align-items: center; gap: .4rem; font-size: .75rem; color: var(--text-grey); font-weight: 600; margin-bottom: .5rem; }
.kar-salary-label i { color: var(--emerald); }
.kar-salary-value { font-size: 1.15rem; font-weight: 900; color: var(--emerald); font-family: 'Courier New', monospace; margin-bottom: .7rem; }
.kar-salary-sep { color: var(--text-grey); margin: 0 .3rem; font-weight: 400; }
.kar-salary-bar { height: 6px; background: rgba(0,0,0,.08); border-radius: 50px; overflow: hidden; }
[data-theme="dark"] .kar-salary-bar { background: rgba(255,255,255,.08); }
.kar-salary-fill { height: 100%; background: linear-gradient(90deg, var(--emerald), var(--sky-blue)); border-radius: 50px; transition: width 1s ease; }

/* ===== ALUMNI PROOF ===== */
.kar-proof-section { background: var(--bg-light); padding: 4rem 0; }
[data-theme="dark"] .kar-proof-section { background: var(--rich-black); }
.kar-proof-title { display: flex; align-items: center; justify-content: center; gap: .8rem; font-size: 1.4rem; font-weight: 800; color: var(--rich-black); margin-bottom: 2rem; text-align: center; }
[data-theme="dark"] .kar-proof-title { color: #fff; }
.kar-proof-title i { color: var(--crimson); }
.kar-proof-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.2rem; max-width: 900px; margin: 0 auto; }
.kar-proof-card { display: flex; align-items: center; gap: 1rem; background: #fff; border-radius: 14px; padding: 1rem 1.2rem; box-shadow: 0 6px 20px rgba(0,0,0,.06); transition: transform .3s ease; }
[data-theme="dark"] .kar-proof-card { background: #1a1a2e; }
.kar-proof-card:hover { transform: translateY(-4px); }
.kar-proof-ava { width: 48px; height: 48px; border-radius: 50%; background: linear-gradient(135deg, var(--crimson), var(--crimson-light, #A02050)); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 800; flex-shrink: 0; }
.kar-proof-info { display: flex; flex-direction: column; }
.kar-proof-info strong { font-size: .92rem; color: var(--rich-black); }
[data-theme="dark"] .kar-proof-info strong { color: #fff; }
.kar-proof-info span { font-size: .78rem; color: var(--text-grey); }

/* ===== EMPTY ===== */
.kar-empty { text-align: center; padding: 5rem 2rem; background: #fff; border-radius: 24px; box-shadow: 0 10px 40px rgba(0,0,0,.08); max-width: 700px; margin: 0 auto; }
[data-theme="dark"] .kar-empty { background: #1a1a2e; }
.kar-empty-icon { width: 120px; height: 120px; margin: 0 auto 1.5rem; background: linear-gradient(135deg, rgba(138,21,56,0.1), rgba(135,206,235,0.1)); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 3.5rem; color: var(--crimson); }
.kar-empty h2 { color: var(--rich-black); margin-bottom: .5rem; font-size: 1.5rem; font-weight: 800; }
[data-theme="dark"] .kar-empty h2 { color: #fff; }
.kar-empty p { color: var(--text-grey); }

/* ===== CTA ===== */
.kar-cta-section { padding: 6rem 1.5rem; background: linear-gradient(180deg, #fff 0%, var(--bg-light) 100%); position: relative; overflow: hidden; }
[data-theme="dark"] .kar-cta-section { background: linear-gradient(180deg, var(--rich-black), #0f0f1a); }
.kar-cta-bg { position: absolute; inset: 0; pointer-events: none; }
.kar-cta-orb { position: absolute; border-radius: 50%; filter: blur(80px); opacity: .3; }
.kar-cta-orb-1 { width: 400px; height: 400px; background: var(--crimson); top: -150px; left: -100px; animation: karOrbFloat 25s ease-in-out infinite; }
.kar-cta-orb-2 { width: 350px; height: 350px; background: var(--sky-blue); bottom: -150px; right: -100px; animation: karOrbFloat 30s ease-in-out infinite reverse; }
.kar-cta-box { max-width: 800px; margin: 0 auto; text-align: center; padding: 4rem 3rem; background: linear-gradient(135deg, var(--crimson), var(--crimson-light, #A02050)); border-radius: 28px; color: #fff; box-shadow: 0 30px 80px rgba(138,21,56,.35); position: relative; overflow: hidden; z-index: 2; }
.kar-cta-icon { width: 90px; height: 90px; margin: 0 auto 1.5rem; border-radius: 50%; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; font-size: 2.5rem; backdrop-filter: blur(10px); position: relative; z-index: 2; }
.kar-cta-box h2 { font-size: clamp(1.5rem, 4vw, 2.2rem); margin-bottom: 1rem; font-weight: 900; position: relative; z-index: 2; }
.kar-cta-box p { color: rgba(255,255,255,.9); margin-bottom: 2rem; line-height: 1.8; max-width: 600px; margin-left: auto; margin-right: auto; position: relative; z-index: 2; }
.kar-cta-buttons { display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap; position: relative; z-index: 2; }
.kar-cta-buttons .btn-primary { background: #fff; color: var(--crimson); margin-top: 0; box-shadow: 0 12px 35px rgba(0,0,0,.25); }

@media (max-width: 768px) {
    .kar-hero { padding: 9rem 1rem 4rem; }
    .kar-grid { grid-template-columns: 1fr; }
    .kar-cta-box { padding: 2.5rem 1.5rem; }
}
</style>

<script>
(function () {
    'use strict';
    const ro = new IntersectionObserver((es) => { es.forEach(e => { if (e.isIntersecting) { e.target.style.opacity='1'; e.target.style.transform='translateY(0)'; ro.unobserve(e.target); } }); }, { threshold: 0.1 });
    document.querySelectorAll('.kar-card, .kar-proof-card').forEach((el,i) => { el.style.opacity='0'; el.style.transform='translateY(30px)'; el.style.transition=`all 0.6s cubic-bezier(0.22,0.61,0.36,1) ${i*0.05}s`; ro.observe(el); });
    // Animate salary bars
    setTimeout(() => { document.querySelectorAll('.kar-salary-fill').forEach(b => { b.style.width = b.style.width; }); }, 400);
    document.querySelectorAll('.magnetic-btn').forEach(el => { el.addEventListener('mousemove',(e)=>{const r=el.getBoundingClientRect();el.style.transform=`translate(${(e.clientX-r.left-r.width/2)*0.15}px,${(e.clientY-r.top-r.height/2)*0.15}px)`;}); el.addEventListener('mouseleave',()=>{el.style.transform='';}); });
})();
</script>

<script>
(function () {
    'use strict';
    const t = { en: { 'kar_badge':'CAREER PROSPECTS','kar_title':'Graduate Career Prospects','kar_subtitle':'PBSI graduates build careers in various fields: translation, education, diplomacy, media, literature, and linguistic research.','home':'Home','kar_breadcrumb':'Career Prospects','kar_total':'Career Paths','kar_hot':'High Demand','kar_avg':'Avg. Max Salary','kar_empty_title':'Career Data Not Available','kar_empty_desc':'Career prospect information is being compiled.','kar_cta_title':'Ready to Start Your Career?','kar_cta_desc':'Join PBSI UMMaumere and realize your professional future in the global language world.','kar_cta_contact':'Register Now','kar_cta_kurikulum':'View Curriculum' }};
    function apply(){ const l=document.documentElement.getAttribute('lang')||'id'; if(l==='en'&&t.en){document.querySelectorAll('[data-i18n]').forEach(el=>{const k=el.dataset.i18n;if(t.en[k])el.textContent=t.en[k];});} }
    new MutationObserver(m=>{m.forEach(x=>{if(x.attributeName==='lang')apply();});}).observe(document.documentElement,{attributes:true}); apply();
})();
</script>

<?php require_once '../includes/public_footer.php'; ?>