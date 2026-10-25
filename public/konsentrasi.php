<?php
require_once '../includes/config.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';
check_ip_security($pdo);

$page_title = 'Konsentrasi Studi';
require_once '../includes/public_header.php';

$setting = get_all('settings', 'id = 1')[0];
$current_lang = $_COOKIE['pbsi_lang'] ?? 'id';

$konsentrasi = $pdo->query("SELECT * FROM konsentrasi WHERE status = 'aktif' ORDER BY urutan ASC, id ASC")->fetchAll();

// Hitung jumlah MK per konsentrasi
$mk_counts = [];
foreach ($konsentrasi as $k) {
    $c = $pdo->prepare("SELECT COUNT(*) FROM mata_kuliah WHERE konsentrasi_id = ?");
    $c->execute([$k['id']]);
    $mk_counts[$k['id']] = (int)$c->fetchColumn();
}

// Ambil beberapa MK contoh per konsentrasi (max 3)
$mk_samples = [];
foreach ($konsentrasi as $k) {
    $s = $pdo->prepare("SELECT kode_mk, nama_mk, sks FROM mata_kuliah WHERE konsentrasi_id = ? ORDER BY semester ASC, urutan ASC LIMIT 3");
    $s->execute([$k['id']]);
    $mk_samples[$k['id']] = $s->fetchAll();
}

$total_kon = count($konsentrasi);
$total_mk_terhubung = array_sum($mk_counts);
?>

<main id="main-content" role="main">

<!-- ============ PAGE HERO ============ -->
<section class="page-hero kon-hero">
    <div class="kon-hero-content">
        <span class="kon-hero-badge">
            <i class="fa-solid fa-layer-group" aria-hidden="true"></i>
            <span data-i18n="kon_badge">KONSENTRASI STUDI</span>
        </span>
        <h1 class="kon-hero-title" data-i18n="kon_title">Bidang Peminatan</h1>
        <p class="kon-hero-subtitle" data-i18n="kon_subtitle">
            Pilih jalur spesialisasi sesuai minat dan bakatmu: Linguistik, Sastra, Penerjemahan, atau Pengajaran Bahasa Inggris.
        </p>
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="<?= PUBLIC_URL ?>index.php" data-i18n="home">Beranda</a>
            <span aria-hidden="true"><i class="fa-solid fa-chevron-right"></i></span>
            <span aria-current="page" data-i18n="kon_breadcrumb">Konsentrasi</span>
        </nav>
    </div>
    <div class="kon-hero-decor" aria-hidden="true">
        <div class="kon-orb kon-orb-1"></div>
        <div class="kon-orb kon-orb-2"></div>
    </div>
</section>

<!-- ============ STATS BAR ============ -->
<section class="kon-stats-section">
    <div class="section">
        <div class="kon-stats-grid">
            <div class="kon-stat-card">
                <div class="kon-stat-icon"><i class="fa-solid fa-layer-group"></i></div>
                <div class="kon-stat-value"><?= $total_kon ?></div>
                <div class="kon-stat-label" data-i18n="kon_total">Konsentrasi</div>
            </div>
            <div class="kon-stat-card accent-sky">
                <div class="kon-stat-icon"><i class="fa-solid fa-book"></i></div>
                <div class="kon-stat-value"><?= $total_mk_terhubung ?></div>
                <div class="kon-stat-label" data-i18n="kon_mk">Mata Kuliah Spesialis</div>
            </div>
            <div class="kon-stat-card accent-emerald">
                <div class="kon-stat-icon"><i class="fa-solid fa-route"></i></div>
                <div class="kon-stat-value">4</div>
                <div class="kon-stat-label" data-i18n="kon_paths">Jalur Karir</div>
            </div>
        </div>
    </div>
</section>

<!-- ============ KONSENTRASI CARDS ============ -->
<section class="section kon-list-section">
    <?php if (empty($konsentrasi)): ?>
    <div class="kon-empty">
        <div class="kon-empty-icon"><i class="fa-solid fa-layer-group"></i></div>
        <h2 data-i18n="kon_empty_title">Konsentrasi Belum Tersedia</h2>
        <p data-i18n="kon_empty_desc">Informasi bidang peminatan sedang dalam proses penyusunan.</p>
    </div>
    <?php else: ?>
    <div class="kon-grid">
        <?php foreach ($konsentrasi as $idx => $k): 
            $warna = htmlspecialchars($k['warna'] ?: '#10B981');
        ?>
        <div class="kon-card" style="--kon-color: <?= $warna ?>;">
            <div class="kon-card-accent"></div>
            <div class="kon-card-header">
                <div class="kon-card-icon"><i class="fa-solid <?= htmlspecialchars($k['ikon']) ?>"></i></div>
                <span class="kon-card-kode"><?= htmlspecialchars($k['kode']) ?></span>
            </div>
            <h3 class="kon-card-name"><?= htmlspecialchars($k['nama']) ?></h3>
            <?php if ($k['deskripsi']): ?>
            <p class="kon-card-desc"><?= htmlspecialchars($k['deskripsi']) ?></p>
            <?php endif; ?>

            <div class="kon-card-stats">
                <div class="kon-mini-stat">
                    <i class="fa-solid fa-book"></i>
                    <span><strong><?= $mk_counts[$k['id']] ?? 0 ?></strong> <?= $current_lang === 'en' ? 'Courses' : 'MK' ?></span>
                </div>
            </div>

            <?php if (!empty($mk_samples[$k['id']])): ?>
            <div class="kon-card-courses">
                <div class="kon-courses-label"><i class="fa-solid fa-list-check"></i> <?= $current_lang === 'en' ? 'Sample Courses' : 'Contoh Mata Kuliah' ?></div>
                <ul class="kon-courses-list">
                    <?php foreach ($mk_samples[$k['id']] as $mk): ?>
                    <li>
                        <span class="kon-mk-code"><?= htmlspecialchars($mk['kode_mk']) ?></span>
                        <span class="kon-mk-name"><?= htmlspecialchars($mk['nama_mk']) ?></span>
                        <span class="kon-mk-sks"><?= $mk['sks'] ?> SKS</span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <a href="<?= PUBLIC_URL ?>kurikulum.php" class="kon-card-link">
                <span data-i18n="kon_view_curriculum">Lihat Kurikulum Lengkap</span>
                <i class="fa-solid fa-arrow-right"></i>
            </a>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>

<!-- ============ HOW TO CHOOSE ============ -->
<section class="kon-guide-section">
    <div class="section">
        <h2 class="kon-guide-title"><i class="fa-solid fa-compass"></i> <?= $current_lang === 'en' ? 'How to Choose?' : 'Bagaimana Memilih?' ?></h2>
        <div class="kon-guide-grid">
            <div class="kon-guide-card">
                <div class="kon-guide-num">1</div>
                <h3><?= $current_lang === 'en' ? 'Know Your Interest' : 'Kenali Minatmu' ?></h3>
                <p><?= $current_lang === 'en' ? 'Do you love analyzing language structure, reading literature, translating, or teaching?' : 'Apakah Anda suka menganalisis struktur bahasa, membaca sastra, menerjemahkan, atau mengajar?' ?></p>
            </div>
            <div class="kon-guide-card">
                <div class="kon-guide-num">2</div>
                <h3><?= $current_lang === 'en' ? 'Explore Career Goals' : 'Eksplorasi Tujuan Karir' ?></h3>
                <p><?= $current_lang === 'en' ? 'Each concentration leads to different career paths. Check our career prospects page.' : 'Setiap konsentrasi mengarah ke jalur karir berbeda. Lihat halaman prospek karir kami.' ?></p>
            </div>
            <div class="kon-guide-card">
                <div class="kon-guide-num">3</div>
                <h3><?= $current_lang === 'en' ? 'Consult Lecturers' : 'Konsultasi Dosen' ?></h3>
                <p><?= $current_lang === 'en' ? 'Our academic advisors will help you choose the concentration that suits you best.' : 'Dosen pembimbing akademik kami akan membantu memilih konsentrasi yang paling sesuai.' ?></p>
            </div>
        </div>
    </div>
</section>

<!-- ============ CTA ============ -->
<section class="kon-cta-section">
    <div class="kon-cta-bg" aria-hidden="true">
        <div class="kon-cta-orb kon-cta-orb-1"></div>
        <div class="kon-cta-orb kon-cta-orb-2"></div>
    </div>
    <div class="kon-cta-box">
        <div class="kon-cta-icon"><i class="fa-solid fa-graduation-cap"></i></div>
        <h2 data-i18n="kon_cta_title">Temukan Jalur Spesialisasimu</h2>
        <p data-i18n="kon_cta_desc">Pelajari kurikulum lengkap setiap konsentrasi dan mulailah perjalanan akademismu bersama PBSI UMMaumere.</p>
        <div class="kon-cta-buttons">
            <a href="<?= PUBLIC_URL ?>kurikulum.php" class="btn-primary magnetic-btn">
                <i class="fa-solid fa-book-open"></i>
                <span data-i18n="kon_cta_kurikulum">Lihat Kurikulum</span>
            </a>
            <a href="<?= PUBLIC_URL ?>kontak.php" class="btn-secondary magnetic-btn" style="border-color:#fff;color:#fff;">
                <i class="fa-solid fa-headset"></i>
                <span data-i18n="kon_cta_contact">Konsultasi</span>
            </a>
        </div>
    </div>
</section>

</main>

<style>
/* ===== HERO ===== */
.kon-hero { position: relative; padding: 11rem 1.5rem 5rem; overflow: hidden; }
.kon-hero-content { max-width: 900px; margin: 0 auto; position: relative; z-index: 2; text-align: center; }
.kon-hero-badge { display: inline-flex; align-items: center; gap: .6rem; padding: .5rem 1.3rem; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); backdrop-filter: blur(10px); border-radius: 50px; font-size: .75rem; font-weight: 800; letter-spacing: 3px; color: var(--sky-blue); margin-bottom: 1.5rem; }
.kon-hero-badge i { color: var(--crimson); }
.kon-hero-title { font-size: clamp(2.2rem, 7vw, 4.5rem); font-weight: 900; line-height: 1.05; margin-bottom: 1.2rem; background: linear-gradient(135deg, var(--sky-blue), #fff); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent; }
.kon-hero-subtitle { font-size: 1.2rem; color: #cfd8dc; max-width: 700px; margin: 0 auto 2rem; line-height: 1.7; }
.kon-hero .breadcrumb { justify-content: center; font-size: .9rem; }
.kon-hero .breadcrumb i { font-size: .7rem; color: var(--sky-blue); }
.kon-hero-decor { position: absolute; inset: 0; pointer-events: none; overflow: hidden; }
.kon-orb { position: absolute; border-radius: 50%; filter: blur(80px); opacity: .4; }
.kon-orb-1 { width: 400px; height: 400px; background: var(--crimson); top: -100px; left: -100px; animation: konOrbFloat 20s ease-in-out infinite; }
.kon-orb-2 { width: 350px; height: 350px; background: var(--sky-blue-dark); bottom: -100px; right: -100px; animation: konOrbFloat 25s ease-in-out infinite reverse; }
@keyframes konOrbFloat { 0%, 100% { transform: translate(0, 0) scale(1); } 33% { transform: translate(50px, -30px) scale(1.1); } 66% { transform: translate(-30px, 50px) scale(0.9); } }

/* ===== STATS ===== */
.kon-stats-section { background: var(--bg-light); padding: 3rem 0; }
[data-theme="dark"] .kon-stats-section { background: var(--rich-black); }
.kon-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; }
.kon-stat-card { background: #fff; border-radius: 16px; padding: 1.5rem; text-align: center; box-shadow: 0 8px 25px rgba(0,0,0,.06); border-top: 4px solid var(--crimson); transition: transform .3s ease; }
[data-theme="dark"] .kon-stat-card { background: #1a1a2e; }
.kon-stat-card:hover { transform: translateY(-5px); }
.kon-stat-card.accent-sky { border-top-color: var(--sky-blue); }
.kon-stat-card.accent-emerald { border-top-color: var(--emerald); }
.kon-stat-icon { width: 50px; height: 50px; border-radius: 12px; background: rgba(138,21,56,.1); color: var(--crimson); display: flex; align-items: center; justify-content: center; font-size: 1.3rem; margin: 0 auto 1rem; }
.kon-stat-card.accent-sky .kon-stat-icon { background: rgba(135,206,235,.1); color: var(--sky-blue); }
.kon-stat-card.accent-emerald .kon-stat-icon { background: rgba(46,139,87,.1); color: var(--emerald); }
.kon-stat-value { font-size: 2rem; font-weight: 900; color: var(--rich-black); line-height: 1; }
[data-theme="dark"] .kon-stat-value { color: #fff; }
.kon-stat-label { color: var(--text-grey); font-size: .85rem; margin-top: .3rem; }

/* ===== KONSENTRASI GRID ===== */
.kon-list-section { padding-top: 2rem; }
.kon-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 2rem; }
.kon-card { background: #fff; border-radius: 22px; padding: 2rem 1.8rem; box-shadow: 0 12px 35px rgba(0,0,0,.08); transition: all .4s ease; border: 1px solid transparent; position: relative; overflow: hidden; }
[data-theme="dark"] .kon-card { background: #1a1a2e; }
.kon-card-accent { position: absolute; top: 0; left: 0; right: 0; height: 5px; background: var(--kon-color); }
.kon-card:hover { transform: translateY(-10px); box-shadow: 0 25px 60px rgba(0,0,0,.16); border-color: var(--kon-color); }
.kon-card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.2rem; }
.kon-card-icon { width: 64px; height: 64px; border-radius: 18px; background: color-mix(in srgb, var(--kon-color) 15%, transparent); color: var(--kon-color); display: flex; align-items: center; justify-content: center; font-size: 1.7rem; transition: all .4s ease; }
.kon-card:hover .kon-card-icon { background: var(--kon-color); color: #fff; transform: rotate(-8deg) scale(1.05); }
.kon-card-kode { font-family: 'Courier New', monospace; font-weight: 900; font-size: 1rem; color: var(--kon-color); background: color-mix(in srgb, var(--kon-color) 12%, transparent); padding: .35rem .9rem; border-radius: 8px; letter-spacing: 1px; }
.kon-card-name { font-size: 1.35rem; font-weight: 800; color: var(--rich-black); margin: 0 0 .7rem; line-height: 1.25; }
[data-theme="dark"] .kon-card-name { color: #fff; }
.kon-card-desc { font-size: .9rem; color: var(--text-grey); line-height: 1.7; margin: 0 0 1.3rem; }
.kon-card-stats { display: flex; gap: 1rem; margin-bottom: 1.3rem; }
.kon-mini-stat { display: flex; align-items: center; gap: .5rem; font-size: .85rem; color: var(--text-grey); background: var(--bg-light); padding: .5rem .9rem; border-radius: 50px; }
[data-theme="dark"] .kon-mini-stat { background: #0f0f1a; }
.kon-mini-stat i { color: var(--kon-color); }
.kon-mini-stat strong { color: var(--rich-black); font-weight: 800; }
[data-theme="dark"] .kon-mini-stat strong { color: #fff; }

/* Sample courses */
.kon-card-courses { background: var(--bg-light); border-radius: 14px; padding: 1rem 1.2rem; margin-bottom: 1.3rem; }
[data-theme="dark"] .kon-card-courses { background: #0f0f1a; }
.kon-courses-label { display: flex; align-items: center; gap: .5rem; font-size: .78rem; font-weight: 700; color: var(--kon-color); text-transform: uppercase; letter-spacing: .5px; margin-bottom: .8rem; }
.kon-courses-list { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: .5rem; }
.kon-courses-list li { display: flex; align-items: center; gap: .6rem; font-size: .82rem; }
.kon-mk-code { font-family: 'Courier New', monospace; font-weight: 700; color: var(--kon-color); font-size: .75rem; min-width: 60px; }
.kon-mk-name { flex: 1; color: var(--rich-black); font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
[data-theme="dark"] .kon-mk-name { color: #e0e0e0; }
.kon-mk-sks { font-size: .72rem; color: var(--text-grey); background: rgba(0,0,0,.05); padding: .15rem .5rem; border-radius: 50px; }
[data-theme="dark"] .kon-mk-sks { background: rgba(255,255,255,.06); }

.kon-card-link { display: flex; align-items: center; justify-content: center; gap: .6rem; padding: .8rem; background: var(--kon-color); color: #fff; border-radius: 12px; font-weight: 700; font-size: .9rem; text-decoration: none; transition: all .3s ease; }
.kon-card-link:hover { gap: 1rem; box-shadow: 0 10px 25px color-mix(in srgb, var(--kon-color) 40%, transparent); }
.kon-card-link i { transition: transform .3s ease; }
.kon-card-link:hover i { transform: translateX(4px); }

/* ===== GUIDE ===== */
.kon-guide-section { background: var(--bg-light); padding: 5rem 0; }
[data-theme="dark"] .kon-guide-section { background: var(--rich-black); }
.kon-guide-title { display: flex; align-items: center; justify-content: center; gap: .8rem; font-size: 1.5rem; font-weight: 800; color: var(--rich-black); margin-bottom: 2.5rem; text-align: center; }
[data-theme="dark"] .kon-guide-title { color: #fff; }
.kon-guide-title i { color: var(--crimson); }
.kon-guide-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1.5rem; max-width: 1000px; margin: 0 auto; }
.kon-guide-card { background: #fff; border-radius: 18px; padding: 2rem 1.6rem; box-shadow: 0 8px 25px rgba(0,0,0,.06); position: relative; transition: transform .3s ease; }
[data-theme="dark"] .kon-guide-card { background: #1a1a2e; }
.kon-guide-card:hover { transform: translateY(-6px); }
.kon-guide-num { position: absolute; top: -18px; left: 1.6rem; width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, var(--crimson), var(--crimson-light, #A02050)); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 900; font-size: 1.1rem; box-shadow: 0 6px 15px rgba(138,21,56,.4); }
.kon-guide-card h3 { font-size: 1.1rem; font-weight: 800; color: var(--rich-black); margin: .8rem 0 .6rem; }
[data-theme="dark"] .kon-guide-card h3 { color: #fff; }
.kon-guide-card p { font-size: .88rem; color: var(--text-grey); line-height: 1.65; margin: 0; }

/* ===== EMPTY ===== */
.kon-empty { text-align: center; padding: 5rem 2rem; background: #fff; border-radius: 24px; box-shadow: 0 10px 40px rgba(0,0,0,.08); max-width: 700px; margin: 0 auto; }
[data-theme="dark"] .kon-empty { background: #1a1a2e; }
.kon-empty-icon { width: 120px; height: 120px; margin: 0 auto 1.5rem; background: linear-gradient(135deg, rgba(138,21,56,0.1), rgba(135,206,235,0.1)); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 3.5rem; color: var(--crimson); }
.kon-empty h2 { color: var(--rich-black); margin-bottom: .5rem; font-size: 1.5rem; font-weight: 800; }
[data-theme="dark"] .kon-empty h2 { color: #fff; }
.kon-empty p { color: var(--text-grey); }

/* ===== CTA ===== */
.kon-cta-section { padding: 6rem 1.5rem; background: linear-gradient(180deg, #fff 0%, var(--bg-light) 100%); position: relative; overflow: hidden; }
[data-theme="dark"] .kon-cta-section { background: linear-gradient(180deg, var(--rich-black), #0f0f1a); }
.kon-cta-bg { position: absolute; inset: 0; pointer-events: none; }
.kon-cta-orb { position: absolute; border-radius: 50%; filter: blur(80px); opacity: .3; }
.kon-cta-orb-1 { width: 400px; height: 400px; background: var(--crimson); top: -150px; left: -100px; animation: konOrbFloat 25s ease-in-out infinite; }
.kon-cta-orb-2 { width: 350px; height: 350px; background: var(--sky-blue); bottom: -150px; right: -100px; animation: konOrbFloat 30s ease-in-out infinite reverse; }
.kon-cta-box { max-width: 800px; margin: 0 auto; text-align: center; padding: 4rem 3rem; background: linear-gradient(135deg, var(--crimson), var(--crimson-light, #A02050)); border-radius: 28px; color: #fff; box-shadow: 0 30px 80px rgba(138,21,56,.35); position: relative; overflow: hidden; z-index: 2; }
.kon-cta-icon { width: 90px; height: 90px; margin: 0 auto 1.5rem; border-radius: 50%; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; font-size: 2.5rem; backdrop-filter: blur(10px); position: relative; z-index: 2; }
.kon-cta-box h2 { font-size: clamp(1.5rem, 4vw, 2.2rem); margin-bottom: 1rem; font-weight: 900; position: relative; z-index: 2; }
.kon-cta-box p { color: rgba(255,255,255,.9); margin-bottom: 2rem; line-height: 1.8; max-width: 600px; margin-left: auto; margin-right: auto; position: relative; z-index: 2; }
.kon-cta-buttons { display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap; position: relative; z-index: 2; }
.kon-cta-buttons .btn-primary { background: #fff; color: var(--crimson); margin-top: 0; box-shadow: 0 12px 35px rgba(0,0,0,.25); }

@media (max-width: 768px) {
    .kon-hero { padding: 9rem 1rem 4rem; }
    .kon-grid { grid-template-columns: 1fr; }
    .kon-cta-box { padding: 2.5rem 1.5rem; }
}
</style>

<script>
(function () {
    'use strict';
    const ro = new IntersectionObserver((es) => { es.forEach(e => { if (e.isIntersecting) { e.target.style.opacity='1'; e.target.style.transform='translateY(0)'; ro.unobserve(e.target); } }); }, { threshold: 0.1 });
    document.querySelectorAll('.kon-card, .kon-guide-card').forEach((el,i) => { el.style.opacity='0'; el.style.transform='translateY(30px)'; el.style.transition=`all 0.6s cubic-bezier(0.22,0.61,0.36,1) ${i*0.06}s`; ro.observe(el); });
    document.querySelectorAll('.magnetic-btn').forEach(el => { el.addEventListener('mousemove',(e)=>{const r=el.getBoundingClientRect();el.style.transform=`translate(${(e.clientX-r.left-r.width/2)*0.15}px,${(e.clientY-r.top-r.height/2)*0.15}px)`;}); el.addEventListener('mouseleave',()=>{el.style.transform='';}); });
})();
</script>

<script>
(function () {
    'use strict';
    const t = { en: { 'kon_badge':'STUDY CONCENTRATIONS','kon_title':'Fields of Specialization','kon_subtitle':'Choose a specialization path according to your interest and talent: Linguistics, Literature, Translation, or English Language Teaching.','home':'Home','kon_breadcrumb':'Concentrations','kon_total':'Concentrations','kon_mk':'Specialist Courses','kon_paths':'Career Paths','kon_empty_title':'Concentrations Not Available','kon_empty_desc':'Specialization information is being compiled.','kon_view_curriculum':'View Full Curriculum','kon_cta_title':'Find Your Specialization Path','kon_cta_desc':'Study the complete curriculum of each concentration and begin your academic journey with PBSI UMMaumere.','kon_cta_kurikulum':'View Curriculum','kon_cta_contact':'Consultation' }};
    function apply(){ const l=document.documentElement.getAttribute('lang')||'id'; if(l==='en'&&t.en){document.querySelectorAll('[data-i18n]').forEach(el=>{const k=el.dataset.i18n;if(t.en[k])el.textContent=t.en[k];});} }
    new MutationObserver(m=>{m.forEach(x=>{if(x.attributeName==='lang')apply();});}).observe(document.documentElement,{attributes:true}); apply();
})();
</script>

<?php require_once '../includes/public_footer.php'; ?>