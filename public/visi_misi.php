<?php
require_once '../includes/config.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';
check_ip_security($pdo);

$page_title = 'Visi & Misi';
require_once '../includes/public_header.php';

$vm = get_all('visi_misi', 'aktif = 1', 'tipe ASC, urutan ASC');
$visi = array_values(array_filter($vm, function ($v) { return $v['tipe'] === 'visi'; }));
$misi = array_values(array_filter($vm, function ($v) { return $v['tipe'] === 'misi'; }));
$tujuan = array_values(array_filter($vm, function ($v) { return $v['tipe'] === 'tujuan'; }));
$nilai = array_values(array_filter($vm, function ($v) { return $v['tipe'] === 'nilai'; }));
$setting = get_all('settings', 'id = 1')[0];

$current_lang = $_COOKIE['pbsi_lang'] ?? 'id';
$current_year = (int)date('Y');
$target_year = 2035;
$years_remaining = max(0, $target_year - $current_year);
$total_years = $target_year - 2024; // asumsi basis 2024
$progress_percent = $total_years > 0 ? round((($total_years - $years_remaining) / $total_years) * 100) : 0;
?>

<!-- Skip Link -->
<a href="#main-content" class="skip-link">Skip to main content</a>

<!-- Reading Progress Bar (konsisten dengan homepage) -->
<div class="reading-progress" id="readingProgress" aria-hidden="true">
    <div class="reading-progress-fill"></div>
</div>

<!-- JSON-LD Structured Data -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "WebPage",
  "name": "Visi & Misi - <?= sanitize($setting['site_name'] ?? 'PBSI UMMaumere') ?>",
  "description": "Arah besar dan komitmen Program Studi Bahasa dan Sastra Inggris UMMaumere menuju tahun 2035",
  "breadcrumb": {
    "@type": "BreadcrumbList",
    "itemListElement": [
      { "@type": "ListItem", "position": 1, "name": "<?= $current_lang === 'en' ? 'Home' : 'Beranda' ?>", "item": "<?= PUBLIC_URL ?>" },
      { "@type": "ListItem", "position": 2, "name": "Visi & Misi" }
    ]
  },
  "mainEntity": {
    "@type": "EducationalOrganization",
    "name": "<?= sanitize($setting['site_name'] ?? 'PBSI UMMaumere') ?>",
    "description": "<?= !empty($visi) ? sanitize($visi[0]['isi']) : '' ?>"
  }
}
</script>

<main id="main-content" role="main">

<!-- ============ PAGE HERO ============ -->
<section class="page-hero vm-hero">
    <div class="vm-hero-content reveal-up">
        <span class="vm-hero-badge text-reveal">
            <i class="fa-solid fa-compass" aria-hidden="true"></i>
            <span data-i18n="compass">ARAH &amp; KOMITMEN</span>
        </span>
        <h1 class="vm-hero-title text-reveal" data-i18n="page_title">Visi &amp; Misi</h1>
        <p class="vm-hero-subtitle fade-in-up" data-i18n="page_subtitle">
            Arah besar dan komitmen Program Studi Bahasa dan Sastra Inggris UMMaumere menuju tahun 2035.
        </p>
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="<?= PUBLIC_URL ?>index.php" data-i18n="home">Beranda</a>
            <span aria-hidden="true"><i class="fa-solid fa-chevron-right"></i></span>
            <span aria-current="page" data-i18n="visi_misi_breadcrumb">Visi &amp; Misi</span>
        </nav>
    </div>

    <!-- Decorative Elements -->
    <div class="vm-hero-decor" aria-hidden="true">
        <div class="vm-orb vm-orb-1"></div>
        <div class="vm-orb vm-orb-2"></div>
        <div class="vm-orb vm-orb-3"></div>
    </div>

    <!-- Scroll Indicator -->
    <div class="vm-scroll-hint" aria-hidden="true">
        <div class="vm-mouse"><span></span></div>
        <small data-i18n="scroll">SCROLL</small>
    </div>
</section>

<!-- ============ STICKY TABLE OF CONTENTS ============ -->
<nav class="vm-toc" id="vmToc" aria-label="Table of Contents">
    <div class="vm-toc-inner">
        <div class="vm-toc-progress">
            <div class="vm-toc-progress-fill" id="tocProgressFill"></div>
        </div>
        <ul class="vm-toc-list">
            <?php if (!empty($visi)): ?>
            <li><a href="#section-visi" class="vm-toc-link active" data-section="visi"><i class="fa-solid fa-eye"></i> <span data-i18n="toc_visi">Visi</span></a></li>
            <?php endif; ?>
            <?php if (!empty($misi)): ?>
            <li><a href="#section-misi" class="vm-toc-link" data-section="misi"><i class="fa-solid fa-bullseye"></i> <span data-i18n="toc_misi">Misi</span></a></li>
            <?php endif; ?>
            <?php if (!empty($tujuan)): ?>
            <li><a href="#section-tujuan" class="vm-toc-link" data-section="tujuan"><i class="fa-solid fa-flag-checkered"></i> <span data-i18n="toc_tujuan">Tujuan 2035</span></a></li>
            <?php endif; ?>
            <?php if (!empty($nilai)): ?>
            <li><a href="#section-nilai" class="vm-toc-link" data-section="nilai"><i class="fa-solid fa-heart"></i> <span data-i18n="toc_nilai">Nilai</span></a></li>
            <?php endif; ?>
        </ul>
    </div>
</nav>

<section class="section vm-main-section">

    <!-- ============ VISI SECTION ============ -->
    <?php if (!empty($visi)): ?>
    <div class="vm-section-block" id="section-visi">
        <header class="sec-head">
            <span class="sec-eyebrow ey-crimson text-reveal">
                <i class="fa-solid fa-eye" aria-hidden="true"></i>
                <span data-i18n="our_vision">VISI KAMI</span>
            </span>
            <h2 class="section-title text-reveal" data-i18n="vision_heading">Arah Besar Kami</h2>
            <p class="section-subtitle fade-in-up" data-i18n="vision_subtitle">
                Pandangan jauh ke depan yang menjadi kompas setiap langkah Program Studi.
            </p>
        </header>

        <article class="visi-card-parallax" data-parallax="0.2">
            <div class="visi-card">
                <div class="visi-icon-wrapper">
                    <div class="visi-icon">
                        <i class="<?= sanitize($visi[0]['ikon']) ?>" aria-hidden="true"></i>
                    </div>
                    <div class="visi-icon-ring"></div>
                </div>
                <span class="visi-label" data-i18n="vision_label">VISI</span>
                <p class="visi-text"><?= sanitize($visi[0]['isi']) ?></p>
                <div class="visi-share">
                    <button class="visi-share-btn" onclick="shareVisi('twitter')" aria-label="Share on Twitter">
                        <i class="fa-brands fa-x-twitter"></i>
                    </button>
                    <button class="visi-share-btn" onclick="shareVisi('facebook')" aria-label="Share on Facebook">
                        <i class="fa-brands fa-facebook-f"></i>
                    </button>
                    <button class="visi-share-btn" onclick="shareVisi('whatsapp')" aria-label="Share on WhatsApp">
                        <i class="fa-brands fa-whatsapp"></i>
                    </button>
                    <button class="visi-share-btn" onclick="shareVisi('copy')" aria-label="Copy link">
                        <i class="fa-solid fa-link"></i>
                    </button>
                </div>
            </div>
        </article>

        <!-- Vision Timeline -->
        <div class="vm-vision-timeline reveal-up">
            <div class="vm-timeline-item">
                <div class="vm-timeline-dot vm-tl-past"></div>
                <div class="vm-timeline-content">
                    <strong data-i18n="timeline_founded">2010</strong>
                    <p data-i18n="timeline_founded_desc">Prodi didirikan</p>
                </div>
            </div>
            <div class="vm-timeline-connector"></div>
            <div class="vm-timeline-item">
                <div class="vm-timeline-dot vm-tl-present"></div>
                <div class="vm-timeline-content">
                    <strong><?= $current_year ?></strong>
                    <p data-i18n="timeline_now_desc">Masa kini</p>
                </div>
            </div>
            <div class="vm-timeline-connector"></div>
            <div class="vm-timeline-item">
                <div class="vm-timeline-dot vm-tl-future"></div>
                <div class="vm-timeline-content">
                    <strong data-i18n="timeline_target">2035</strong>
                    <p data-i18n="timeline_target_desc">Target tercapai</p>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ============ MISI SECTION ============ -->
    <?php if (!empty($misi)): ?>
    <div class="vm-section-block" id="section-misi">
        <header class="sec-head">
            <span class="sec-eyebrow ey-crimson text-reveal">
                <i class="fa-solid fa-bullseye" aria-hidden="true"></i>
                <span data-i18n="our_commitment">KOMITMEN KAMI</span>
            </span>
            <h2 class="section-title text-reveal" data-i18n="mission_heading">Misi</h2>
            <p class="section-subtitle fade-in-up" data-i18n="mission_subtitle">
                Lima komitmen utama yang menggerakkan seluruh aktivitas tridharma perguruan tinggi.
            </p>
        </header>

        <div class="misi-timeline" role="list">
            <?php foreach ($misi as $i => $m):
                $colors = ['crimson', 'sky', 'emerald', 'warning', 'purple'];
                $color = $colors[$i % count($colors)];
            ?>
            <article class="misi-timeline-item reveal-up"
                     role="listitem"
                     style="transition-delay: <?= $i * 0.12 ?>s;"
                     data-color="<?= $color ?>">
                <div class="misi-timeline-connector" aria-hidden="true">
                    <div class="misi-timeline-line"></div>
                    <div class="misi-timeline-dot"></div>
                </div>
                <div class="misi-card-new">
                    <span class="misi-num" aria-hidden="true"><?= str_pad($i + 1, 2, '0', STR_PAD_LEFT) ?></span>
                    <div class="misi-icon-new misi-icon-<?= $color ?>">
                        <i class="<?= sanitize($m['ikon']) ?>" aria-hidden="true"></i>
                    </div>
                    <div class="misi-content-new">
                        <h3><?= sanitize($m['judul']) ?></h3>
                        <p><?= sanitize($m['isi']) ?></p>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ============ TUJUAN SECTION (dengan Progress Bar) ============ -->
    <?php if (!empty($tujuan)): ?>
    <div class="vm-section-block" id="section-tujuan">
        <header class="sec-head">
            <span class="sec-eyebrow ey-sky text-reveal">
                <i class="fa-solid fa-flag-checkered" aria-hidden="true"></i>
                <span data-i18n="target_2035">TARGET 2035</span>
            </span>
            <h2 class="section-title text-reveal" data-i18n="goals_heading">Tujuan</h2>
            <p class="section-subtitle fade-in-up" data-i18n="goals_subtitle">
                Target terukur yang ingin kami capai pada tahun 2035.
            </p>
        </header>

        <!-- Progress Overview -->
        <div class="vm-progress-overview reveal-up">
            <div class="vm-progress-header">
                <div>
                    <h3 data-i18n="progress_title">Perjalanan Menuju 2035</h3>
                    <p data-i18n="progress_desc"><?= $years_remaining ?> tahun lagi menuju target visi besar</p>
                </div>
                <div class="vm-progress-years">
                    <span>2024</span>
                    <span class="vm-progress-current"><?= $current_year ?></span>
                    <span>2035</span>
                </div>
            </div>
            <div class="vm-progress-bar">
                <div class="vm-progress-fill" data-progress="<?= $progress_percent ?>">
                    <div class="vm-progress-marker" style="left: <?= $progress_percent ?>%;">
                        <span class="vm-progress-percent"><?= $progress_percent ?>%</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="tujuan-wrap" role="list">
            <?php foreach ($tujuan as $i => $t): ?>
            <article class="tujuan-item-new reveal-up"
                     role="listitem"
                     style="transition-delay: <?= $i * 0.1 ?>s;">
                <div class="tujuan-icon-new">
                    <i class="<?= sanitize($t['ikon']) ?>" aria-hidden="true"></i>
                </div>
                <div class="tujuan-content-new">
                    <span class="tujuan-num">#<?= str_pad($i + 1, 2, '0', STR_PAD_LEFT) ?></span>
                    <p><?= sanitize($t['isi']) ?></p>
                </div>
                <div class="tujuan-check" aria-hidden="true">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ============ NILAI SECTION (dengan 3D Card Flip) ============ -->
    <?php if (!empty($nilai)): ?>
    <div class="vm-section-block" id="section-nilai">
        <header class="sec-head">
            <span class="sec-eyebrow ey-emerald text-reveal">
                <i class="fa-solid fa-heart" aria-hidden="true"></i>
                <span data-i18n="graduate_character">KARAKTER LULUSAN</span>
            </span>
            <h2 class="section-title text-reveal" data-i18n="values_heading">Nilai-Nilai Inti</h2>
            <p class="section-subtitle fade-in-up" data-i18n="values_subtitle">
                Karakter yang kami tanamkan pada setiap lulusan PBSI UMMaumere.
            </p>
        </header>

        <div class="nilai-flip-grid" role="list">
            <?php
            $nilai_colors = [
                ['from' => 'var(--crimson)', 'to' => 'var(--crimson-light)'],
                ['from' => '#2980b9', 'to' => 'var(--sky-blue-dark)'],
                ['from' => 'var(--emerald)', 'to' => 'var(--emerald-dark)'],
                ['from' => '#16213e', 'to' => 'var(--rich-black)']
            ];
            foreach ($nilai as $i => $n):
                $color = $nilai_colors[$i % count($nilai_colors)];
            ?>
            <article class="nilai-flip-card reveal-up"
                     role="listitem"
                     style="transition-delay: <?= $i * 0.12 ?>s;"
                     tabindex="0"
                     aria-label="<?= sanitize($n['judul']) ?>">
                <div class="nilai-flip-inner">
                    <!-- Front -->
                    <div class="nilai-flip-front" style="background: linear-gradient(135deg, <?= $color['from'] ?>, <?= $color['to'] ?>);">
                        <div class="nilai-flip-icon">
                            <i class="<?= sanitize($n['ikon']) ?>" aria-hidden="true"></i>
                        </div>
                        <h3><?= sanitize($n['judul']) ?></h3>
                        <span class="nilai-flip-hint" data-i18n="flip_hint">Hover untuk detail</span>
                    </div>
                    <!-- Back -->
                    <div class="nilai-flip-back">
                        <div class="nilai-flip-back-content">
                            <span class="nilai-flip-back-label"><?= sanitize($n['judul']) ?></span>
                            <p><?= sanitize($n['isi']) ?></p>
                            <div class="nilai-flip-number"><?= str_pad($i + 1, 2, '0', STR_PAD_LEFT) ?></div>
                        </div>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ============ EMPTY STATE PREMIUM ============ -->
    <?php if (empty($visi) && empty($misi) && empty($tujuan) && empty($nilai)): ?>
    <div class="vm-empty reveal-up">
        <div class="vm-empty-icon">
            <i class="fa-solid fa-compass" aria-hidden="true"></i>
        </div>
        <h3 data-i18n="empty_title">Data Belum Tersedia</h3>
        <p data-i18n="empty_desc">Informasi Visi &amp; Misi sedang dalam proses penyusunan oleh tim akademik.</p>
        <a href="<?= PUBLIC_URL ?>kontak.php" class="btn-primary magnetic-btn" data-i18n="contact_us">
            <i class="fa-solid fa-envelope" aria-hidden="true"></i>
            <span>Hubungi Kami</span>
        </a>
    </div>
    <?php endif; ?>

</section>

<!-- ============ CTA SECTION ============ -->
<section class="vm-cta" aria-labelledby="vm-cta-heading">
    <div class="vm-cta-bg" aria-hidden="true">
        <div class="vm-cta-orb vm-cta-orb-1"></div>
        <div class="vm-cta-orb vm-cta-orb-2"></div>
    </div>
    <div class="vm-cta-box reveal-up">
        <div class="vm-cta-icon-wrap">
            <i class="fa-solid fa-graduation-cap" aria-hidden="true"></i>
        </div>
        <h2 id="vm-cta-heading" class="text-reveal" data-i18n="cta_heading">Siap Bergabung dengan Kami?</h2>
        <p class="fade-in-up" data-i18n="cta_desc">
            Wujudkan visi dan misi kami bersama Anda sebagai bagian dari keluarga besar PBSI UMMaumere.
        </p>
        <div class="vm-cta-buttons">
            <a href="<?= PUBLIC_URL ?>kontak.php" class="btn-primary magnetic-btn" data-cursor="pointer" data-i18n="register_now">
                <i class="fa-solid fa-user-plus" aria-hidden="true"></i>
                <span>Daftar Sekarang</span>
            </a>
            <a href="<?= PUBLIC_URL ?>kurikulum.php" class="btn-secondary magnetic-btn" data-cursor="pointer" data-i18n="view_curriculum">
                <i class="fa-solid fa-book" aria-hidden="true"></i>
                <span>Lihat Kurikulum</span>
            </a>
        </div>
    </div>
</section>

</main>

<!-- ============================================================
     PREMIUM CSS — VISI MISI
============================================================ -->
<style>
/* ===== PAGE HERO ENHANCED ===== */
.vm-hero {
    position: relative;
    padding: 11rem 1.5rem 5rem;
    overflow: hidden;
}
.vm-hero-content {
    max-width: 900px;
    margin: 0 auto;
    position: relative;
    z-index: 2;
    text-align: center;
}
.vm-hero-badge {
    display: inline-flex;
    align-items: center;
    gap: .6rem;
    padding: .5rem 1.3rem;
    background: rgba(255,255,255,0.1);
    border: 1px solid rgba(255,255,255,0.2);
    backdrop-filter: blur(10px);
    border-radius: 50px;
    font-size: .75rem;
    font-weight: 800;
    letter-spacing: 3px;
    color: var(--sky-blue);
    margin-bottom: 1.5rem;
}
.vm-hero-badge i { color: var(--crimson); }
.vm-hero-title {
    font-size: clamp(2.5rem, 8vw, 5rem);
    font-weight: 900;
    line-height: 1.05;
    margin-bottom: 1.2rem;
    background: linear-gradient(135deg, var(--sky-blue), #fff);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
}
.vm-hero-subtitle {
    font-size: 1.2rem;
    color: #cfd8dc;
    max-width: 700px;
    margin: 0 auto 2rem;
    line-height: 1.7;
}
.vm-hero .breadcrumb {
    justify-content: center;
    font-size: .9rem;
}
.vm-hero .breadcrumb i { font-size: .7rem; color: var(--sky-blue); }

/* Hero Decorative Orbs */
.vm-hero-decor {
    position: absolute;
    inset: 0;
    pointer-events: none;
    overflow: hidden;
}
.vm-orb {
    position: absolute;
    border-radius: 50%;
    filter: blur(80px);
    opacity: .4;
}
.vm-orb-1 {
    width: 400px; height: 400px;
    background: var(--crimson);
    top: -100px; left: -100px;
    animation: orbFloat 20s ease-in-out infinite;
}
.vm-orb-2 {
    width: 350px; height: 350px;
    background: var(--sky-blue-dark);
    bottom: -100px; right: -100px;
    animation: orbFloat 25s ease-in-out infinite reverse;
}
.vm-orb-3 {
    width: 250px; height: 250px;
    background: var(--emerald);
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    animation: orbFloat 30s ease-in-out infinite;
    opacity: .2;
}
@keyframes orbFloat {
    0%, 100% { transform: translate(0, 0) scale(1); }
    33% { transform: translate(50px, -30px) scale(1.1); }
    66% { transform: translate(-30px, 50px) scale(0.9); }
}

/* Hero Scroll Hint */
.vm-scroll-hint {
    position: absolute;
    bottom: 2rem;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: .5rem;
    color: rgba(255,255,255,.7);
    z-index: 3;
}
.vm-scroll-hint small {
    font-size: .7rem;
    letter-spacing: 3px;
    font-weight: 700;
}
.vm-mouse {
    width: 24px; height: 38px;
    border: 2px solid rgba(255,255,255,.6);
    border-radius: 14px;
    display: flex;
    justify-content: center;
    padding-top: 6px;
}
.vm-mouse span {
    width: 3px; height: 8px;
    background: var(--sky-blue);
    border-radius: 3px;
    animation: vmWheel 1.6s ease-in-out infinite;
}
@keyframes vmWheel {
    0% { transform: translateY(0); opacity: 1; }
    70% { transform: translateY(12px); opacity: 0; }
    100% { opacity: 0; }
}

/* ===== STICKY TABLE OF CONTENTS ===== */
.vm-toc {
    position: sticky;
    top: 80px;
    z-index: 90;
    background: rgba(255,255,255,0.95);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border-bottom: 1px solid rgba(0,0,0,0.08);
    padding: 1rem 1.5rem;
    margin-bottom: 3rem;
    transition: all .3s ease;
    box-shadow: 0 4px 20px rgba(0,0,0,0.05);
}
[data-theme="dark"] .vm-toc {
    background: rgba(18,18,18,0.95);
    border-bottom-color: rgba(255,255,255,0.08);
}
.vm-toc.scrolled {
    box-shadow: 0 8px 30px rgba(0,0,0,0.1);
}
.vm-toc-inner {
    max-width: 1200px;
    margin: 0 auto;
    position: relative;
}
.vm-toc-progress {
    position: absolute;
    bottom: -1rem;
    left: 0;
    width: 100%;
    height: 2px;
    background: rgba(0,0,0,0.05);
    overflow: hidden;
}
[data-theme="dark"] .vm-toc-progress {
    background: rgba(255,255,255,0.08);
}
.vm-toc-progress-fill {
    height: 100%;
    width: 0%;
    background: linear-gradient(90deg, var(--crimson), var(--sky-blue));
    transition: width .15s ease-out;
}
.vm-toc-list {
    display: flex;
    justify-content: center;
    gap: .5rem;
    list-style: none;
    margin: 0;
    padding: 0;
    flex-wrap: wrap;
}
.vm-toc-link {
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    padding: .6rem 1.2rem;
    border-radius: 50px;
    font-size: .85rem;
    font-weight: 600;
    color: var(--text-grey);
    text-decoration: none;
    transition: all .3s ease;
    background: transparent;
    border: 1px solid transparent;
}
.vm-toc-link:hover {
    color: var(--crimson);
    background: rgba(138,21,56,0.05);
}
.vm-toc-link.active {
    background: linear-gradient(135deg, var(--crimson), var(--crimson-light));
    color: #fff;
    box-shadow: 0 4px 15px rgba(138,21,56,0.3);
    transform: translateY(-2px);
}
.vm-toc-link i { font-size: .85rem; }

@media (max-width: 768px) {
    .vm-toc { padding: .8rem 1rem; top: 70px; }
    .vm-toc-list { gap: .3rem; }
    .vm-toc-link { padding: .5rem .8rem; font-size: .75rem; }
    .vm-toc-link span { display: none; }
}

/* ===== MAIN SECTION ===== */
.vm-main-section {
    padding-top: 0;
}
.vm-section-block {
    margin-bottom: 6rem;
    scroll-margin-top: 160px;
}
@media (max-width: 768px) {
    .vm-section-block { scroll-margin-top: 120px; }
}

/* ===== VISI CARD PARALLAX ===== */
.visi-card-parallax {
    max-width: 900px;
    margin: 0 auto;
    perspective: 1000px;
}
.visi-card {
    background: linear-gradient(135deg, var(--rich-black), #1a1a2e);
    border-radius: 28px;
    padding: 4rem 3rem;
    text-align: center;
    color: #fff;
    position: relative;
    overflow: hidden;
    box-shadow: 0 25px 60px rgba(0,0,0,.3);
    transform-style: preserve-3d;
    transition: transform .3s ease;
}
.visi-card::before {
    content: '';
    position: absolute;
    inset: 0;
    background:
        radial-gradient(circle at 20% 30%, rgba(138,21,56,0.2) 0%, transparent 50%),
        radial-gradient(circle at 80% 70%, rgba(135,206,235,0.15) 0%, transparent 50%);
    pointer-events: none;
}
.visi-icon-wrapper {
    position: relative;
    display: inline-block;
    margin-bottom: 1.5rem;
}
.visi-icon {
    width: 100px; height: 100px;
    margin: 0 auto;
    border-radius: 50%;
    background: rgba(255,255,255,.08);
    border: 1px solid rgba(255,255,255,.2);
    display: flex; align-items: center; justify-content: center;
    font-size: 2.6rem;
    color: var(--sky-blue);
    position: relative;
    z-index: 2;
    backdrop-filter: blur(10px);
}
.visi-icon-ring {
    position: absolute;
    inset: -10px;
    border-radius: 50%;
    border: 2px dashed rgba(135,206,235,.3);
    animation: spin 20s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
.visi-label {
    letter-spacing: 6px;
    font-size: .85rem;
    color: var(--sky-blue);
    text-transform: uppercase;
    font-weight: 700;
    position: relative;
    z-index: 2;
}
.visi-text {
    font-size: clamp(1.2rem, 2.5vw, 1.6rem);
    font-weight: 600;
    line-height: 1.6;
    margin-top: 1rem;
    position: relative;
    z-index: 2;
    max-width: 700px;
    margin-left: auto;
    margin-right: auto;
}
.visi-share {
    display: flex;
    justify-content: center;
    gap: .6rem;
    margin-top: 2rem;
    position: relative;
    z-index: 2;
}
.visi-share-btn {
    width: 40px; height: 40px;
    border-radius: 50%;
    background: rgba(255,255,255,0.1);
    border: 1px solid rgba(255,255,255,0.2);
    color: #fff;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem;
    transition: all .3s ease;
    backdrop-filter: blur(10px);
}
.visi-share-btn:hover {
    background: var(--crimson);
    border-color: var(--crimson);
    transform: translateY(-3px) scale(1.1);
}

/* Vision Timeline */
.vm-vision-timeline {
    max-width: 800px;
    margin: 3rem auto 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    position: relative;
}
.vm-timeline-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: .8rem;
    flex: 0 0 auto;
}
.vm-timeline-dot {
    width: 16px; height: 16px;
    border-radius: 50%;
    border: 3px solid #fff;
    background: var(--rich-black);
    box-shadow: 0 0 0 3px var(--crimson);
    transition: all .3s ease;
}
.vm-tl-past {
    background: var(--emerald);
    box-shadow: 0 0 0 3px var(--emerald);
}
.vm-tl-present {
    background: var(--crimson);
    box-shadow: 0 0 0 3px var(--crimson);
    width: 20px; height: 20px;
    animation: pulsePresent 2s ease-in-out infinite;
}
@keyframes pulsePresent {
    0%, 100% { box-shadow: 0 0 0 3px var(--crimson), 0 0 0 6px rgba(138,21,56,.3); }
    50% { box-shadow: 0 0 0 3px var(--crimson), 0 0 0 12px rgba(138,21,56,0); }
}
.vm-tl-future {
    background: var(--sky-blue);
    box-shadow: 0 0 0 3px var(--sky-blue);
}
.vm-timeline-connector {
    flex: 1;
    height: 3px;
    background: linear-gradient(90deg, var(--emerald), var(--crimson) 50%, var(--sky-blue));
    border-radius: 2px;
    margin-top: -2.5rem;
    position: relative;
}
.vm-timeline-content {
    text-align: center;
}
.vm-timeline-content strong {
    display: block;
    font-size: 1.3rem;
    color: var(--rich-black);
    margin-bottom: .2rem;
}
[data-theme="dark"] .vm-timeline-content strong { color: #fff; }
.vm-timeline-content p {
    font-size: .82rem;
    color: var(--text-grey);
}
@media (max-width: 640px) {
    .vm-vision-timeline { flex-wrap: wrap; gap: 2rem; justify-content: center; }
    .vm-timeline-connector { display: none; }
}

/* ===== MISI TIMELINE (Vertical) ===== */
.misi-timeline {
    max-width: 900px;
    margin: 3rem auto 0;
    position: relative;
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}
.misi-timeline-item {
    display: flex;
    gap: 1.5rem;
    align-items: flex-start;
    position: relative;
}
.misi-timeline-connector {
    display: flex;
    flex-direction: column;
    align-items: center;
    flex-shrink: 0;
    position: relative;
    width: 40px;
}
.misi-timeline-line {
    width: 2px;
    height: 100%;
    background: linear-gradient(180deg, var(--crimson), var(--sky-blue));
    position: absolute;
    top: 0;
    left: 50%;
    transform: translateX(-50%);
    opacity: .3;
}
.misi-timeline-item:last-child .misi-timeline-line { display: none; }
.misi-timeline-dot {
    width: 14px; height: 14px;
    border-radius: 50%;
    background: var(--crimson);
    border: 3px solid #fff;
    box-shadow: 0 0 0 3px var(--crimson), 0 4px 12px rgba(138,21,56,.3);
    flex-shrink: 0;
    position: relative;
    z-index: 2;
}
[data-theme="dark"] .misi-timeline-dot { border-color: var(--rich-black); }
.misi-timeline-item[data-color="sky"] .misi-timeline-dot { background: var(--sky-blue-dark); box-shadow: 0 0 0 3px var(--sky-blue-dark), 0 4px 12px rgba(93,173,226,.3); }
.misi-timeline-item[data-color="emerald"] .misi-timeline-dot { background: var(--emerald); box-shadow: 0 0 0 3px var(--emerald), 0 4px 12px rgba(46,139,87,.3); }
.misi-timeline-item[data-color="warning"] .misi-timeline-dot { background: var(--warning); box-shadow: 0 0 0 3px var(--warning), 0 4px 12px rgba(243,156,18,.3); }
.misi-timeline-item[data-color="purple"] .misi-timeline-dot { background: #9b59b6; box-shadow: 0 0 0 3px #9b59b6, 0 4px 12px rgba(155,89,182,.3); }

.misi-card-new {
    flex: 1;
    background: #fff;
    border-radius: 18px;
    padding: 2rem;
    box-shadow: 0 10px 35px rgba(0,0,0,.08);
    display: flex;
    gap: 1.5rem;
    align-items: flex-start;
    position: relative;
    transition: all .3s ease;
    border-top: 4px solid var(--crimson);
}
[data-theme="dark"] .misi-card-new { background: #1a1a2e; }
.misi-timeline-item[data-color="sky"] .misi-card-new { border-top-color: var(--sky-blue-dark); }
.misi-timeline-item[data-color="emerald"] .misi-card-new { border-top-color: var(--emerald); }
.misi-timeline-item[data-color="warning"] .misi-card-new { border-top-color: var(--warning); }
.misi-timeline-item[data-color="purple"] .misi-card-new { border-top-color: #9b59b6; }
.misi-card-new:hover {
    transform: translateY(-5px) translateX(5px);
    box-shadow: 0 20px 50px rgba(0,0,0,.12);
}
.misi-num {
    position: absolute;
    top: -15px; right: 15px;
    font-size: 4rem;
    font-weight: 900;
    color: rgba(138,21,56,.08);
    line-height: 1;
    font-family: 'Inter', sans-serif;
}
.misi-icon-new {
    width: 56px; height: 56px;
    border-radius: 14px;
    background: linear-gradient(135deg, var(--crimson), var(--crimson-light));
    color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.5rem;
    flex-shrink: 0;
    box-shadow: 0 8px 20px rgba(138,21,56,.3);
    transition: all .3s ease;
}
.misi-card-new:hover .misi-icon-new {
    transform: rotate(-8deg) scale(1.1);
}
.misi-icon-sky { background: linear-gradient(135deg, #2980b9, var(--sky-blue-dark)); box-shadow: 0 8px 20px rgba(93,173,226,.3); }
.misi-icon-emerald { background: linear-gradient(135deg, var(--emerald), var(--emerald-dark)); box-shadow: 0 8px 20px rgba(46,139,87,.3); }
.misi-icon-warning { background: linear-gradient(135deg, #e67e22, var(--warning)); box-shadow: 0 8px 20px rgba(243,156,18,.3); }
.misi-icon-purple { background: linear-gradient(135deg, #8e44ad, #9b59b6); box-shadow: 0 8px 20px rgba(155,89,182,.3); }
.misi-content-new { flex: 1; min-width: 0; }
.misi-content-new h3 {
    font-size: 1.15rem;
    margin-bottom: .6rem;
    color: var(--rich-black);
    font-weight: 700;
}
[data-theme="dark"] .misi-content-new h3 { color: #fff; }
.misi-content-new p {
    color: var(--text-grey);
    font-size: .93rem;
    line-height: 1.7;
    margin: 0;
}
@media (max-width: 640px) {
    .misi-timeline-connector { display: none; }
    .misi-card-new { flex-direction: column; gap: 1rem; padding: 1.5rem; }
}

/* ===== TUJUAN PROGRESS OVERVIEW ===== */
.vm-progress-overview {
    max-width: 900px;
    margin: 3rem auto 0;
    background: #fff;
    border-radius: 20px;
    padding: 2rem;
    box-shadow: 0 10px 35px rgba(0,0,0,.08);
    border-left: 5px solid var(--sky-blue);
}
[data-theme="dark"] .vm-progress-overview { background: #1a1a2e; }
.vm-progress-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 2rem;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
}
.vm-progress-header h3 {
    font-size: 1.3rem;
    color: var(--rich-black);
    margin-bottom: .3rem;
    font-weight: 800;
}
[data-theme="dark"] .vm-progress-header h3 { color: #fff; }
.vm-progress-header p {
    color: var(--text-grey);
    font-size: .9rem;
    margin: 0;
}
.vm-progress-years {
    display: flex;
    gap: 1rem;
    align-items: center;
    font-size: .85rem;
    color: var(--text-grey);
    font-weight: 600;
}
.vm-progress-current {
    background: var(--crimson);
    color: #fff;
    padding: .2rem .8rem;
    border-radius: 50px;
    font-size: .78rem;
}
.vm-progress-bar {
    height: 20px;
    background: rgba(0,0,0,0.06);
    border-radius: 50px;
    position: relative;
    overflow: visible;
}
[data-theme="dark"] .vm-progress-bar { background: rgba(255,255,255,0.08); }
.vm-progress-fill {
    height: 100%;
    width: 0%;
    background: linear-gradient(90deg, var(--crimson), var(--sky-blue), var(--emerald));
    border-radius: 50px;
    position: relative;
    transition: width 2s cubic-bezier(0.22, 0.61, 0.36, 1);
    box-shadow: 0 0 15px rgba(135,206,235,.5);
}
.vm-progress-marker {
    position: absolute;
    top: -25px;
    transform: translateX(-50%);
    white-space: nowrap;
}
.vm-progress-percent {
    background: var(--rich-black);
    color: #fff;
    padding: .2rem .6rem;
    border-radius: 4px;
    font-size: .75rem;
    font-weight: 700;
    position: relative;
}
.vm-progress-percent::after {
    content: '';
    position: absolute;
    bottom: -4px;
    left: 50%;
    transform: translateX(-50%);
    width: 0; height: 0;
    border-left: 4px solid transparent;
    border-right: 4px solid transparent;
    border-top: 4px solid var(--rich-black);
}

/* ===== TUJUAN ITEMS NEW ===== */
.tujuan-wrap {
    max-width: 900px;
    margin: 2rem auto 0;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}
.tujuan-item-new {
    background: #fff;
    border-radius: 16px;
    padding: 1.5rem;
    display: flex;
    gap: 1.2rem;
    align-items: flex-start;
    box-shadow: 0 8px 25px rgba(0,0,0,.05);
    border-left: 4px solid var(--emerald);
    transition: all .3s ease;
    position: relative;
    overflow: hidden;
}
[data-theme="dark"] .tujuan-item-new { background: #1a1a2e; }
.tujuan-item-new:hover {
    transform: translateX(8px);
    border-left-color: var(--crimson);
    box-shadow: 0 15px 40px rgba(0,0,0,.1);
}
.tujuan-item-new::before {
    content: '';
    position: absolute;
    top: 0; left: 0;
    width: 4px; height: 100%;
    background: linear-gradient(180deg, var(--emerald), var(--sky-blue));
    opacity: 0;
    transition: opacity .3s ease;
}
.tujuan-item-new:hover::before { opacity: 1; }
.tujuan-icon-new {
    width: 48px; height: 48px;
    border-radius: 12px;
    background: rgba(46,139,87,0.1);
    color: var(--emerald);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.3rem;
    flex-shrink: 0;
    transition: all .3s ease;
}
.tujuan-item-new:hover .tujuan-icon-new {
    background: var(--emerald);
    color: #fff;
    transform: rotate(-10deg) scale(1.1);
}
.tujuan-content-new {
    flex: 1;
    min-width: 0;
}
.tujuan-num {
    display: inline-block;
    background: rgba(138,21,56,.08);
    color: var(--crimson);
    padding: .15rem .6rem;
    border-radius: 4px;
    font-size: .72rem;
    font-weight: 700;
    letter-spacing: 1px;
    margin-bottom: .4rem;
}
.tujuan-content-new p {
    color: #444;
    font-size: .95rem;
    line-height: 1.6;
    margin: 0;
}
[data-theme="dark"] .tujuan-content-new p { color: #c0c0c0; }
.tujuan-check {
    color: var(--emerald);
    font-size: 1.3rem;
    flex-shrink: 0;
    opacity: 0;
    transform: scale(0.5);
    transition: all .4s cubic-bezier(0.22, 0.61, 0.36, 1) .2s;
}
.tujuan-item-new.visible .tujuan-check {
    opacity: 1;
    transform: scale(1);
}

/* ===== NILAI 3D FLIP CARDS ===== */
.nilai-flip-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 1.8rem;
    max-width: 1100px;
    margin: 3rem auto 0;
}
.nilai-flip-card {
    perspective: 1000px;
    height: 280px;
    outline: none;
}
.nilai-flip-inner {
    position: relative;
    width: 100%;
    height: 100%;
    transition: transform 0.8s cubic-bezier(0.22, 0.61, 0.36, 1);
    transform-style: preserve-3d;
    border-radius: 20px;
}
.nilai-flip-card:hover .nilai-flip-inner,
.nilai-flip-card:focus .nilai-flip-inner {
    transform: rotateY(180deg);
}
.nilai-flip-front, .nilai-flip-back {
    position: absolute;
    inset: 0;
    border-radius: 20px;
    backface-visibility: hidden;
    -webkit-backface-visibility: hidden;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 2rem 1.5rem;
    text-align: center;
    color: #fff;
    overflow: hidden;
}
.nilai-flip-front::before, .nilai-flip-back::before {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(circle at 30% 20%, rgba(255,255,255,0.15), transparent 50%);
    pointer-events: none;
}
.nilai-flip-back {
    transform: rotateY(180deg);
    background: linear-gradient(135deg, var(--rich-black), #1a1a2e);
}
.nilai-flip-icon {
    font-size: 2.8rem;
    margin-bottom: 1.2rem;
    opacity: .95;
    filter: drop-shadow(0 4px 12px rgba(0,0,0,0.3));
}
.nilai-flip-front h3 {
    font-size: 1.3rem;
    font-weight: 800;
    letter-spacing: 1px;
    margin-bottom: .5rem;
    position: relative;
    z-index: 2;
}
.nilai-flip-hint {
    font-size: .72rem;
    letter-spacing: 2px;
    text-transform: uppercase;
    color: rgba(255,255,255,0.7);
    margin-top: auto;
    padding-top: 1rem;
}
.nilai-flip-back-content {
    position: relative;
    z-index: 2;
}
.nilai-flip-back-label {
    display: block;
    font-size: .78rem;
    letter-spacing: 2px;
    text-transform: uppercase;
    color: var(--sky-blue);
    font-weight: 700;
    margin-bottom: .8rem;
}
.nilai-flip-back p {
    font-size: .92rem;
    line-height: 1.7;
    color: rgba(255,255,255,0.9);
    margin: 0;
}
.nilai-flip-number {
    position: absolute;
    bottom: 1rem;
    right: 1.5rem;
    font-size: 3rem;
    font-weight: 900;
    color: rgba(135,206,235,0.1);
    font-family: 'Inter', sans-serif;
}

/* Touch device fallback */
@media (hover: none) {
    .nilai-flip-card {
        min-height: auto;
    }
    .nilai-flip-inner {
        transform: none !important;
        position: relative;
    }
    .nilai-flip-back {
        position: relative;
        transform: none;
        backface-visibility: visible;
        border-radius: 0 0 20px 20px;
        margin-top: -1px;
    }
    .nilai-flip-front {
        border-radius: 20px 20px 0 0;
        padding: 1.5rem;
    }
    .nilai-flip-hint { display: none; }
}

/* ===== EMPTY STATE PREMIUM ===== */
.vm-empty {
    text-align: center;
    padding: 5rem 2rem;
    background: #fff;
    border-radius: 24px;
    box-shadow: 0 10px 40px rgba(0,0,0,.08);
    max-width: 700px;
    margin: 3rem auto;
}
[data-theme="dark"] .vm-empty { background: #1a1a2e; }
.vm-empty-icon {
    width: 120px; height: 120px;
    margin: 0 auto 1.5rem;
    background: linear-gradient(135deg, rgba(138,21,56,0.1), rgba(135,206,235,0.1));
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 3.5rem;
    color: var(--crimson);
}
.vm-empty h3 {
    color: var(--rich-black);
    margin-bottom: .5rem;
    font-size: 1.5rem;
    font-weight: 800;
}
[data-theme="dark"] .vm-empty h3 { color: #fff; }
.vm-empty p {
    color: var(--text-grey);
    margin-bottom: 2rem;
    max-width: 500px;
    margin-left: auto;
    margin-right: auto;
}

/* ===== CTA SECTION ENHANCED ===== */
.vm-cta {
    padding: 6rem 1.5rem;
    background: linear-gradient(180deg, #fff 0%, var(--bg-light) 100%);
    position: relative;
    overflow: hidden;
}
[data-theme="dark"] .vm-cta {
    background: linear-gradient(180deg, var(--rich-black) 0%, #0f0f1a 100%);
}
.vm-cta-bg {
    position: absolute;
    inset: 0;
    pointer-events: none;
}
.vm-cta-orb {
    position: absolute;
    border-radius: 50%;
    filter: blur(80px);
    opacity: .3;
}
.vm-cta-orb-1 {
    width: 400px; height: 400px;
    background: var(--crimson);
    top: -150px; left: -100px;
    animation: orbFloat 25s ease-in-out infinite;
}
.vm-cta-orb-2 {
    width: 350px; height: 350px;
    background: var(--sky-blue);
    bottom: -150px; right: -100px;
    animation: orbFloat 30s ease-in-out infinite reverse;
}
.vm-cta-box {
    max-width: 800px;
    margin: 0 auto;
    text-align: center;
    padding: 4rem 3rem;
    background: linear-gradient(135deg, var(--crimson-dark), var(--crimson-light));
    border-radius: 28px;
    color: #fff;
    box-shadow: 0 30px 80px rgba(138,21,56,.35);
    position: relative;
    overflow: hidden;
    z-index: 2;
}
.vm-cta-box::before {
    content: '';
    position: absolute;
    inset: 0;
    background:
        radial-gradient(circle at 20% 30%, rgba(255,255,255,0.1) 0%, transparent 50%),
        radial-gradient(circle at 80% 70%, rgba(135,206,235,0.15) 0%, transparent 50%);
    pointer-events: none;
}
.vm-cta-icon-wrap {
    width: 90px; height: 90px;
    margin: 0 auto 1.5rem;
    border-radius: 50%;
    background: rgba(255,255,255,0.1);
    border: 1px solid rgba(255,255,255,0.2);
    display: flex; align-items: center; justify-content: center;
    font-size: 2.5rem;
    backdrop-filter: blur(10px);
    position: relative;
    z-index: 2;
    animation: floatSoft 6s ease-in-out infinite;
}
@keyframes floatSoft {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
}
.vm-cta-box h2 {
    font-size: clamp(1.5rem, 4vw, 2.2rem);
    margin-bottom: 1rem;
    font-weight: 900;
    position: relative;
    z-index: 2;
}
.vm-cta-box p {
    color: rgba(255,255,255,.9);
    margin-bottom: 2rem;
    line-height: 1.8;
    max-width: 600px;
    margin-left: auto;
    margin-right: auto;
    position: relative;
    z-index: 2;
}
.vm-cta-buttons {
    display: flex;
    gap: 1rem;
    justify-content: center;
    flex-wrap: wrap;
    position: relative;
    z-index: 2;
}
.vm-cta-buttons .btn-primary {
    background: #fff;
    color: var(--crimson);
    margin-top: 0;
    box-shadow: 0 12px 35px rgba(0,0,0,.25);
}
.vm-cta-buttons .btn-secondary {
    border-color: #fff;
    color: #fff;
    margin-top: 0;
}
.vm-cta-buttons .btn-secondary:hover {
    background: #fff;
    color: var(--crimson);
}

/* ===== READING PROGRESS ===== */
.reading-progress {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 3px;
    background: rgba(0,0,0,0.05);
    z-index: 9999;
    pointer-events: none;
}
[data-theme="dark"] .reading-progress { background: rgba(255,255,255,0.05); }
.reading-progress-fill {
    height: 100%;
    width: 0%;
    background: linear-gradient(90deg, var(--crimson), var(--sky-blue), var(--emerald));
    box-shadow: 0 0 10px rgba(138,21,56,0.5);
    transition: width 0.1s ease-out;
}

/* ===== REVEAL ANIMATIONS ===== */
.reveal-up {
    opacity: 0;
    transform: translateY(40px);
    transition: opacity 0.8s cubic-bezier(0.22, 0.61, 0.36, 1),
                transform 0.8s cubic-bezier(0.22, 0.61, 0.36, 1);
}
.reveal-up.visible { opacity: 1; transform: translateY(0); }
.text-reveal > * {
    display: inline-block;
    opacity: 0;
    transform: translateY(100%);
    transition: all 0.8s cubic-bezier(0.22, 0.61, 0.36, 1);
}
.text-reveal.visible > * { opacity: 1; transform: translateY(0); }
.text-reveal.visible > *:nth-child(1) { transition-delay: 0.1s; }
.text-reveal.visible > *:nth-child(2) { transition-delay: 0.2s; }
.text-reveal.visible > *:nth-child(3) { transition-delay: 0.3s; }
.fade-in-up {
    opacity: 0;
    transform: translateY(20px);
    transition: opacity 0.8s ease 0.3s, transform 0.8s ease 0.3s;
}
.fade-in-up.visible { opacity: 1; transform: translateY(0); }

/* ===== RESPONSIVE ===== */
@media (max-width: 768px) {
    .vm-hero { padding: 9rem 1rem 4rem; }
    .visi-card { padding: 2.5rem 1.5rem; }
    .vm-cta-box { padding: 2.5rem 1.5rem; }
    .misi-card-new { padding: 1.5rem; }
    .vm-progress-overview { padding: 1.5rem; }
}

/* ===== PRINT STYLES ===== */
@media print {
    .vm-toc, .reading-progress, .vm-scroll-hint,
    .visi-share, .vm-cta { display: none !important; }
    .vm-hero { padding: 2rem 1rem; }
    .visi-card, .misi-card-new, .tujuan-item-new, .vm-empty {
        break-inside: avoid;
        box-shadow: none;
        border: 1px solid #ddd;
    }
    .nilai-flip-inner { transform: none !important; }
    .nilai-flip-back { display: none; }
    body { background: #fff; color: #000; }
}

/* ===== REDUCED MOTION ===== */
@media (prefers-reduced-motion: reduce) {
    *, *::before, *::after {
        animation-duration: 0.01ms !important;
        transition-duration: 0.01ms !important;
    }
    .reveal-up, .text-reveal > *, .fade-in-up {
        opacity: 1; transform: none;
    }
}
</style>

<!-- ============================================================
     PREMIUM JAVASCRIPT — VISI MISI
============================================================ -->
<script>
(function () {
    'use strict';

    // ===== READING PROGRESS BAR =====
    const progressBar = document.querySelector('.reading-progress-fill');
    if (progressBar) {
        let ticking = false;
        function updateProgress() {
            const scrollTop = window.scrollY;
            const docHeight = document.documentElement.scrollHeight - window.innerHeight;
            const progress = docHeight > 0 ? (scrollTop / docHeight) * 100 : 0;
            progressBar.style.width = progress + '%';
            ticking = false;
        }
        window.addEventListener('scroll', function () {
            if (!ticking) {
                requestAnimationFrame(updateProgress);
                ticking = true;
            }
        }, { passive: true });
    }

    // ===== TEXT REVEAL (Word by Word) =====
    document.querySelectorAll('.text-reveal').forEach(el => {
        if (el.children.length > 0) return; // sudah diproses
        const text = el.textContent.trim();
        const words = text.split(' ');
        el.innerHTML = words.map(w => `<span>${w}</span>`).join(' ');
    });

    // ===== INTERSECTION OBSERVER FOR REVEALS =====
    const revealObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                revealObserver.unobserve(entry.target);
            }
        });
    }, {
        threshold: 0.15,
        rootMargin: '0px 0px -80px 0px'
    });

    document.querySelectorAll('.reveal-up, .text-reveal, .fade-in-up, .tujuan-item-new').forEach(el => {
        revealObserver.observe(el);
    });

    // ===== STICKY TOC — Active State & Progress =====
    const toc = document.getElementById('vmToc');
    const tocLinks = document.querySelectorAll('.vm-toc-link');
    const tocProgress = document.getElementById('tocProgressFill');
    const sections = [];

    tocLinks.forEach(link => {
        const sectionId = link.getAttribute('data-section');
        const section = document.getElementById('section-' + sectionId);
        if (section) sections.push({ link, section, id: sectionId });
    });

    function updateToc() {
        if (!toc) return;

        // TOC visibility: hanya tampilkan jika ada section visi/misi/tujuan/nilai
        if (sections.length === 0) {
            toc.style.display = 'none';
            return;
        }

        const scrollTop = window.scrollY + 200;
        let activeId = sections[0]?.id;

        for (const s of sections) {
            const top = s.section.offsetTop;
            const bottom = top + s.section.offsetHeight;
            if (scrollTop >= top && scrollTop < bottom) {
                activeId = s.id;
                break;
            }
            if (scrollTop >= top) activeId = s.id;
        }

        tocLinks.forEach(link => {
            const isActive = link.getAttribute('data-section') === activeId;
            link.classList.toggle('active', isActive);
        });

        // Progress indicator
        if (tocProgress && sections.length > 0) {
            const firstTop = sections[0].section.offsetTop;
            const lastBottom = sections[sections.length - 1].section.offsetTop + sections[sections.length - 1].section.offsetHeight;
            const totalLength = lastBottom - firstTop;
            const currentPos = scrollTop - firstTop;
            const progress = Math.max(0, Math.min(100, (currentPos / totalLength) * 100));
            tocProgress.style.width = progress + '%';
        }

        // TOC scrolled state
        toc.classList.toggle('scrolled', window.scrollY > 100);
    }

    let tocTicking = false;
    window.addEventListener('scroll', function () {
        if (!tocTicking) {
            requestAnimationFrame(() => {
                updateToc();
                tocTicking = false;
            });
            tocTicking = true;
        }
    }, { passive: true });

    // Smooth scroll untuk TOC links
    tocLinks.forEach(link => {
        link.addEventListener('click', function (e) {
            e.preventDefault();
            const targetId = this.getAttribute('href').substring(1);
            const target = document.getElementById(targetId);
            if (target) {
                const offset = 180;
                window.scrollTo({
                    top: target.offsetTop - offset,
                    behavior: 'smooth'
                });
            }
        });
    });

    // ===== PROGRESS BAR ANIMATION =====
    const progressFill = document.querySelector('.vm-progress-fill');
    if (progressFill) {
        const progressObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting && !progressFill.dataset.animated) {
                    progressFill.dataset.animated = '1';
                    const target = parseFloat(progressFill.dataset.progress) || 0;
                    setTimeout(() => {
                        progressFill.style.width = target + '%';
                    }, 200);
                }
            });
        }, { threshold: 0.3 });
        progressObserver.observe(progressFill);
    }

    // ===== PARALLAX EFFECT pada Visi Card =====
    const visiCard = document.querySelector('.visi-card');
    if (visiCard && window.matchMedia('(hover: hover)').matches) {
        visiCard.addEventListener('mousemove', (e) => {
            const rect = visiCard.getBoundingClientRect();
            const x = (e.clientX - rect.left) / rect.width - 0.5;
            const y = (e.clientY - rect.top) / rect.height - 0.5;
            visiCard.style.transform = `perspective(1000px) rotateY(${x * 8}deg) rotateX(${-y * 8}deg) translateY(-5px)`;
        });
        visiCard.addEventListener('mouseleave', () => {
            visiCard.style.transform = 'perspective(1000px) rotateY(0) rotateX(0) translateY(0)';
        });
    }

    // ===== MAGNETIC BUTTONS =====
    document.querySelectorAll('.magnetic-btn').forEach(el => {
        el.addEventListener('mousemove', (e) => {
            const rect = el.getBoundingClientRect();
            const x = e.clientX - rect.left - rect.width / 2;
            const y = e.clientY - rect.top - rect.height / 2;
            el.style.transform = `translate(${x * 0.15}px, ${y * 0.15}px)`;
        });
        el.addEventListener('mouseleave', () => {
            el.style.transform = '';
        });
    });

    // ===== INITIAL REVEAL =====
    window.addEventListener('load', () => {
        setTimeout(() => {
            document.querySelectorAll('.vm-hero .text-reveal, .vm-hero .reveal-up, .vm-hero .fade-in-up').forEach(el => {
                el.classList.add('visible');
            });
            updateToc();
        }, 600);
    });

})();

// ===== SHARE FUNCTIONALITY =====
function shareVisi(platform) {
    const url = encodeURIComponent(window.location.href);
    const title = encodeURIComponent(document.title);
    const text = encodeURIComponent(document.querySelector('.visi-text')?.textContent || '');

    let shareUrl = '';
    switch(platform) {
        case 'twitter':
            shareUrl = `https://twitter.com/intent/tweet?text=${text}&url=${url}`;
            break;
        case 'facebook':
            shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${url}`;
            break;
        case 'whatsapp':
            shareUrl = `https://wa.me/?text=${text}%20${url}`;
            break;
        case 'copy':
            navigator.clipboard.writeText(window.location.href).then(() => {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'Link disalin!',
                    showConfirmButton: false,
                    timer: 2000,
                    timerProgressBar: true
                });
            });
            return;
    }

    if (shareUrl) {
        window.open(shareUrl, '_blank', 'width=600,height=500');
    }
}
</script>

<!-- Multi-language Content Support -->
<script>
(function () {
    'use strict';

    const translations = {
        en: {
            'compass': 'DIRECTION & COMMITMENT',
            'page_title': 'Vision & Mission',
            'page_subtitle': 'The grand direction and commitment of the English Language and Literature Study Program of UMMaumere towards 2035.',
            'home': 'Home',
            'visi_misi_breadcrumb': 'Vision & Mission',
            'scroll': 'SCROLL',
            'toc_visi': 'Vision',
            'toc_misi': 'Mission',
            'toc_tujuan': 'Goals 2035',
            'toc_nilai': 'Values',
            'our_vision': 'OUR VISION',
            'vision_heading': 'Our Grand Direction',
            'vision_subtitle': 'A forward-looking vision that serves as the compass for every step of the Study Program.',
            'vision_label': 'VISION',
            'timeline_founded': '2010',
            'timeline_founded_desc': 'Program founded',
            'timeline_now_desc': 'Present day',
            'timeline_target': '2035',
            'timeline_target_desc': 'Target achieved',
            'our_commitment': 'OUR COMMITMENT',
            'mission_heading': 'Mission',
            'mission_subtitle': 'Five core commitments that drive all three pillars of higher education.',
            'target_2035': 'TARGET 2035',
            'goals_heading': 'Goals',
            'goals_subtitle': 'Measurable targets we aim to achieve by 2035.',
            'progress_title': 'Journey to 2035',
            'progress_desc': 'Years remaining towards the grand vision',
            'graduate_character': 'GRADUATE CHARACTER',
            'values_heading': 'Core Values',
            'values_subtitle': 'Character traits we instill in every PBSI UMMaumere graduate.',
            'flip_hint': 'Hover for details',
            'empty_title': 'Data Not Yet Available',
            'empty_desc': 'Vision & Mission information is currently being prepared by the academic team.',
            'contact_us': 'Contact Us',
            'cta_heading': 'Ready to Join Us?',
            'cta_desc': 'Realize our vision and mission together as part of the PBSI UMMaumere family.',
            'register_now': 'Register Now',
            'view_curriculum': 'View Curriculum'
        }
    };

    function applyTranslations() {
        const lang = document.documentElement.getAttribute('lang') || 'id';
        if (lang === 'en' && translations.en) {
            document.querySelectorAll('[data-i18n]').forEach(el => {
                const key = el.dataset.i18n;
                if (translations.en[key]) {
                    if (el.children.length > 0) {
                        const walker = document.createTreeWalker(el, NodeFilter.SHOW_TEXT, null, false);
                        let node;
                        while (node = walker.nextNode()) {
                            if (node.nodeValue.trim()) {
                                node.nodeValue = translations.en[key];
                                break;
                            }
                        }
                    } else {
                        el.textContent = translations.en[key];
                    }
                }
            });
        }
    }

    const langObserver = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            if (mutation.attributeName === 'lang') applyTranslations();
        });
    });

    langObserver.observe(document.documentElement, { attributes: true });
    applyTranslations();
})();
</script>

<?php require_once '../includes/public_footer.php'; ?>