<?php
// ===== MODE DEBUG DEVELOPMENT (HAPUS SAAT PRODUCTION) =====
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
// Force no cache (Hanya untuk development, hapus saat production)
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
if (function_exists('opcache_reset')) { opcache_reset(); }

require_once '../includes/config.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';
check_ip_security($pdo);

// ===== HELPER GAMBAR PRESISI =====
if (!function_exists('img_url')) {
    function img_placeholder() {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="900" height="600">'
             . '<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1">'
             . '<stop offset="0" stop-color="#8A1538"/><stop offset=".55" stop-color="#4b1024"/>'
             . '<stop offset="1" stop-color="#1a1a2e"/></linearGradient></defs>'
             . '<rect width="900" height="600" fill="url(#g)"/>'
             . '<circle cx="450" cy="250" r="64" fill="rgba(255,255,255,.10)"/>'
             . '<path d="M450 214 l52 22 -52 22 -52 -22 z" fill="rgba(255,255,255,.55)"/>'
             . '<path d="M424 246 v20 c0 10 12 16 26 16 s26 -6 26 -16 v-20" fill="none" stroke="rgba(255,255,255,.55)" stroke-width="4" stroke-linecap="round"/>'
             . '<text x="450" y="380" font-family="Arial,sans-serif" font-size="24" letter-spacing="8" fill="rgba(255,255,255,.4)" text-anchor="middle">PBSI UMMaumere</text>'
             . '</svg>';
        return 'data:image/svg+xml;utf8,' . rawurlencode($svg);
    }
    function img_url($path) {
        $path = trim((string)$path);
        if ($path === '') return img_placeholder();
        if (preg_match('~^https?://~i', $path)) return $path;
        $clean = ltrim(str_replace('\\', '/', $path), '/');
        if (file_exists(__DIR__ . '/' . $clean)) return PUBLIC_URL . $clean;
        if (file_exists(__DIR__ . '/../' . $clean)) return BASE_URL . $clean;
        return img_placeholder();
    }
}

$page_title = 'Beranda';
require_once '../includes/public_header.php';

$features = get_all('fitur', 'aktif = 1', 'urutan ASC');
$stats    = get_all('stats', null, 'urutan ASC');
$sliders  = get_all('slider_video', 'aktif = 1', 'urutan ASC');
$news     = get_all('berita', 'status = 1', 'tanggal DESC LIMIT 3');
$dosen    = get_all('dosen', 'aktif = 1', 'urutan ASC LIMIT 6');
$alumni   = get_all('alumni', 'ditampilkan = 1', 'urutan ASC');
$setting  = get_all('settings', 'id = 1')[0];
$total_sl = is_array($sliders) ? max(count($sliders), 1) : 1;
$news_count = is_array($news) ? count($news) : 0;
$ph = img_placeholder();

// Preload hero image pertama untuk performa
$first_hero = !empty($sliders) ? img_url($sliders[0]['file']) : $ph;

$current_lang = $_COOKIE['pbsi_lang'] ?? 'id';
?>

<!-- Preload Critical Assets -->
<link rel="preload" as="image" href="<?= $first_hero ?>" fetchpriority="high">

<!-- Skip Link for Accessibility -->
<a href="#main-content" class="skip-link">Skip to main content</a>

<!-- Reading Progress Bar -->
<div class="reading-progress" id="readingProgress" aria-hidden="true">
    <div class="reading-progress-fill"></div>
</div>

<!-- Custom Cursor for CTA -->
<div class="custom-cursor" id="customCursor" aria-hidden="true">
    <div class="cursor-dot"></div>
    <div class="cursor-ring"></div>
</div>

<!-- JSON-LD Structured Data for SEO -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "EducationalOrganization",
  "name": "<?= sanitize($setting['site_name'] ?? 'PBSI UMMaumere') ?>",
  "alternateName": "Program Studi Bahasa dan Sastra Inggris",
  "url": "<?= PUBLIC_URL ?>",
  "description": "<?= sanitize($setting['site_tagline'] ?? 'Pusat Keunggulan Bahasa & Sastra di Indonesia Timur') ?>",
  "address": {
    "@type": "PostalAddress",
    "streetAddress": "<?= sanitize($setting['alamat'] ?? '') ?>",
    "addressLocality": "Maumere",
    "addressRegion": "Nusa Tenggara Timur",
    "addressCountry": "ID"
  },
  "contactPoint": {
    "@type": "ContactPoint",
    "telephone": "<?= sanitize($setting['telepon'] ?? '') ?>",
    "email": "<?= sanitize($setting['email'] ?? '') ?>",
    "contactType": "Admissions"
  }
}
</script>

<main id="main-content" role="main">

<!-- ============ 1. SLIDER SUPER ULTIMATE ============ -->
<section class="hx-slider" id="hxSlider" role="region" aria-label="Featured Slides" aria-roledescription="carousel">
    <?php if (empty($sliders)): ?>
    <article class="hx-slide active" role="group" aria-roledescription="slide" aria-label="Slide 1 of 1">
        <div class="hx-slide-bg"><div class="hx-slide-img" style="background-image:url('<?= $ph ?>')"></div></div>
        <div class="hx-slide-overlay"></div>
        <div class="hx-slide-video"></div>
        <div class="hx-slide-content">
            <span class="hx-badge"><i class="fa-solid fa-star"></i> <span data-i18n="welcome">SELAMAT DATANG</span></span>
            <h1 class="hx-title"><?= sanitize($setting['site_name'] ?? 'PBSI UMMaumere') ?></h1>
            <p class="hx-subtitle"><?= sanitize($setting['site_tagline'] ?? 'Pusat Keunggulan Bahasa & Sastra di Indonesia Timur') ?></p>
            <a href="<?= PUBLIC_URL ?>kontak.php" class="hx-cta magnetic-btn" data-cursor="pointer">
                <span data-i18n="apply_now">Daftar Sekarang</span>
                <i class="fa-solid fa-arrow-right"></i>
            </a>
        </div>
    </article>
    <?php $total_sl = 1; else: foreach ($sliders as $i => $s): ?>
    <article class="hx-slide <?= $i === 0 ? 'active' : '' ?>"
             data-video="<?= sanitize($s['video_url']) ?>"
             role="group"
             aria-roledescription="slide"
             aria-label="Slide <?= $i + 1 ?> of <?= $total_sl ?>">
        <div class="hx-slide-bg"><div class="hx-slide-img" style="background-image:url('<?= img_url($s['file']) ?>')"></div></div>
        <div class="hx-slide-overlay"></div>
        <div class="hx-slide-video"></div>
        <div class="hx-slide-content">
            <?php if (!empty($s['badge'])): ?>
            <span class="hx-badge"><i class="fa-solid fa-star"></i> <?= sanitize($s['badge']) ?></span>
            <?php endif; ?>
            <h1 class="hx-title text-reveal"><?= sanitize($s['judul']) ?></h1>
            <p class="hx-subtitle"><?= sanitize($s['subtitle']) ?></p>
            <?php if (!empty($s['cta_text']) && $s['cta_url'] !== '#'): ?>
            <a href="<?= PUBLIC_URL . sanitize($s['cta_url']) ?>" class="hx-cta magnetic-btn" data-cursor="pointer" data-analytics="slider-cta">
                <span><?= sanitize($s['cta_text']) ?></span>
                <i class="fa-solid fa-arrow-right"></i>
            </a>
            <?php endif; ?>
        </div>
    </article>
    <?php endforeach; endif; ?>

    <div class="hx-counter" aria-live="polite">
        <span id="hxNow">01</span><em>/</em><span><?= str_pad((int)$total_sl, 2, '0', STR_PAD_LEFT) ?></span>
    </div>
    <div class="hx-dots" role="tablist" aria-label="Slide navigation">
        <?php for ($d = 0; $d < $total_sl; $d++): ?>
        <button class="hx-dot <?= $d === 0 ? 'active' : '' ?>" data-i="<?= $d ?>" role="tab"
                aria-label="Go to slide <?= $d + 1 ?>" aria-selected="<?= $d === 0 ? 'true' : 'false' ?>"></button>
        <?php endfor; ?>
    </div>
    <button class="hx-arrow hx-prev" aria-label="Previous slide"><i class="fa-solid fa-chevron-left"></i></button>
    <button class="hx-arrow hx-next" aria-label="Next slide"><i class="fa-solid fa-chevron-right"></i></button>
    <div class="hx-progress" role="progressbar" aria-label="Slide progress"><div class="hx-progress-bar" id="hxBar"></div></div>
    <div class="hx-scroll-hint" aria-hidden="true">
        <div class="hx-mouse"><span></span></div>
        <small data-i18n="scroll">SCROLL</small>
    </div>
</section>

<!-- ============ 2. MARQUEE BAND ============ -->
<div class="hx-marquee" aria-label="Program highlights">
    <div class="hx-marquee-track">
        <?php
        $words = ['LINGUISTICS', 'LITERATURE', 'TRANSLATION', 'TEFL', 'CREATIVE WRITING', 'INTERPRETING', 'PUBLIC SPEAKING', 'POST-COLONIAL STUDIES'];
        for ($r = 0; $r < 2; $r++) {
            foreach ($words as $w) { echo '<span class="hx-mq-item" aria-hidden="true"><i class="fa-solid fa-star-of-life"></i>' . $w . '</span>'; }
        }
        ?>
    </div>
</div>

<!-- ============ 3. ABOUT / PROFIL SINGKAT ============ -->
<section class="band band-tint parallax-section" aria-labelledby="about-heading" data-speed="0.3">
    <div class="section hx-about">
        <div class="hx-about-grid">
            <div class="hx-collage reveal-left">
                <img class="hx-col-img hx-col-1"
                     src="https://images.unsplash.com/photo-1523050854058-8df90110c9f1?w=900&q=70&auto=format&fit=crop"
                     alt="Suasana perkuliahan Program Studi Bahasa dan Sastra Inggris"
                     loading="lazy"
                     onerror="this.onerror=null;this.src='<?= $ph ?>';">
                <img class="hx-col-img hx-col-2"
                     src="https://images.unsplash.com/photo-1513464962467-1fc2d04710b0?w=900&q=70&auto=format&fit=crop"
                     alt="Gedung kampus Universitas Muhammadiyah Maumere"
                     loading="lazy"
                     onerror="this.onerror=null;this.src='<?= $ph ?>';">
                <div class="hx-float-card hx-float-1 floating-card">
                    <i class="fa-solid fa-award" aria-hidden="true"></i>
                    <div>
                        <strong><?= sanitize($setting['akreditasi'] ?? 'BAIK SEKALI') ?></strong>
                        <small data-i18n="accredited">Terakreditasi</small>
                    </div>
                </div>
                <div class="hx-float-card hx-float-2 floating-card">
                    <i class="fa-solid fa-earth-asia" aria-hidden="true"></i>
                    <div>
                        <strong>15+</strong>
                        <small data-i18n="global_partners">Mitra Global</small>
                    </div>
                </div>
            </div>
            <div class="hx-about-text reveal-right">
                <span class="hx-eyebrow text-reveal">
                    <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                    <span data-i18n="about_prodi">TENTANG PRODI</span>
                </span>
                <h2 class="hx-h2 text-reveal" id="about-heading" data-i18n="about_title">Pusat Keunggulan Bahasa &amp; Sastra di Indonesia Timur</h2>
                <p class="fade-in-up" data-i18n="about_desc">Sejak 2010, Program Studi Bahasa dan Sastra Inggris Universitas Muhammadiyah Maumere konsisten melahirkan linguist, penerjemah, pendidik, dan sastrawan yang berdaya saing global dengan berpijak pada nilai-nilai keislaman dan kemanusiaan.</p>
                <ul class="hx-checklist" role="list">
                    <li class="fade-in-up" data-i18n="check_1"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Kurikulum OBE selaras kebutuhan industri bahasa</li>
                    <li class="fade-in-up" data-i18n="check_2"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Language Lab &amp; Translation Studio berstandar profesional</li>
                    <li class="fade-in-up" data-i18n="check_3"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Dosen lulusan S2–S3 dalam &amp; luar negeri</li>
                    <li class="fade-in-up" data-i18n="check_4"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Program magang, MBKM, dan pertukaran pelajar</li>
                </ul>
                <div class="hx-about-btns">
                    <a href="<?= PUBLIC_URL ?>page.php?slug=sejarah" class="btn-primary magnetic-btn" data-cursor="pointer" data-analytics="about-profile" data-i18n="full_profile">Profil Lengkap</a>
                    <a href="<?= PUBLIC_URL ?>visi_misi.php" class="hx-btn-ghost magnetic-btn" data-cursor="pointer" data-analytics="about-visi-misi" data-i18n="visi_misi">Visi &amp; Misi</a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============ 4. STATS ============ -->
<?php if (!empty($stats)): ?>
<section class="stats-strip" aria-label="Statistik Program Studi">
    <div class="stats-grid" role="list">
        <?php foreach ($stats as $i => $s): ?>
        <div class="stat-item reveal-up" role="listitem" style="transition-delay: <?= $i * 0.1 ?>s;">
            <i class="<?= sanitize($s['icon']) ?>" aria-hidden="true"></i>
            <h3 data-target="<?= (int)$s['nilai'] ?>" data-suffix="<?= sanitize($s['suffix']) ?>">0</h3>
            <p><?= sanitize($s['label']) ?><span class="stat-suffix"></span></p>
        </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- ============ 5. FITUR (VARIASI WARNA PER KARTU) ============ -->
<?php if (!empty($features)): ?>
<section class="band band-light band-dots" aria-labelledby="features-heading">
    <div class="section">
        <header class="sec-head">
            <span class="sec-eyebrow ey-crimson text-reveal">
                <i class="fa-solid fa-gem" aria-hidden="true"></i>
                <span data-i18n="our_strength">KEUNGGULAN KAMI</span>
            </span>
            <h2 class="section-title text-reveal" id="features-heading" data-i18n="why_choose">Mengapa Memilih PBSI UMMaumere?</h2>
            <p class="section-subtitle fade-in-up" data-i18n="why_desc">Keunggulan yang menjadikan kami pusat unggulan bahasa dan sastra di Indonesia Timur.</p>
        </header>
        <div class="features-grid" role="list">
            <?php foreach ($features as $i => $f): ?>
            <article class="feature-card reveal-up magnetic-card" role="listitem" style="transition-delay: <?= $i * 0.08 ?>s;" data-cursor="hover">
                <div class="feature-icon"><i class="<?= sanitize($f['icon']) ?>" aria-hidden="true"></i></div>
                <h3><?= sanitize($f['judul']) ?></h3>
                <p><?= sanitize($f['deskripsi']) ?></p>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============ 6. VIDEO PROFIL ============ -->
<?php if (!empty($setting['video_promo'])): ?>
<section class="home-video-section" aria-labelledby="video-heading">
    <div class="home-video-container">
        <header class="sec-head">
            <span class="sec-eyebrow ey-glass text-reveal">
                <i class="fa-solid fa-clapperboard" aria-hidden="true"></i>
                <span data-i18n="profile_video">VIDEO PROFIL</span>
            </span>
            <h2 class="section-title text-reveal" id="video-heading" style="color:#fff;" data-i18n="watch_profile">Saksikan Profil Kami</h2>
            <p class="section-subtitle fade-in-up" style="color:#bdc3c7;" data-i18n="watch_desc">Kenali lebih dekat keluarga besar PBSI UMMaumere dalam satu menit.</p>
        </header>
        <div class="home-video-wrapper reveal-up">
            <div class="home-video-placeholder"
                 data-video="<?= sanitize($setting['video_promo']) ?>"
                 role="button" tabindex="0"
                 aria-label="Putar video profil"
                 data-cursor="play">
                <div class="play-button"><i class="fa-solid fa-play" aria-hidden="true"></i></div>
                <span data-i18n="click_play">Klik untuk Memutar</span>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============ 7. BERITA FEATURED ============ -->
<?php if ($news_count > 0): ?>
<section class="band band-tint" aria-labelledby="news-heading">
    <div class="section">
        <header class="sec-head">
            <span class="sec-eyebrow ey-sky text-reveal">
                <i class="fa-solid fa-newspaper" aria-hidden="true"></i>
                <span data-i18n="latest_news">KABAR TERKINI</span>
            </span>
            <h2 class="section-title text-reveal" id="news-heading" data-i18n="news_title">Berita &amp; Kegiatan Terkini</h2>
            <p class="section-subtitle fade-in-up" data-i18n="news_desc">Sorotan aktivitas akademik, prestasi, dan peristiwa kampus.</p>
        </header>
        <div class="hx-news-layout <?= $news_count === 1 ? 'single' : '' ?>">
            <article class="hx-news-main reveal-up">
                <a href="<?= PUBLIC_URL ?>berita_detail.php?slug=<?= sanitize($news[0]['slug']) ?>" data-analytics="news-main" data-cursor="read"
                   aria-label="Baca selengkapnya: <?= sanitize($news[0]['judul']) ?>">
                    <div class="hx-news-main-img" style="background-image:url('<?= img_url($news[0]['gambar']) ?>')"
                         role="img" aria-label="<?= sanitize($news[0]['judul']) ?>"></div>
                    <div class="hx-news-main-body">
                        <span class="news-cat"><?= sanitize($news[0]['kategori']) ?></span>
                        <h3><?= sanitize($news[0]['judul']) ?></h3>
                        <p><?= sanitize(substr(strip_tags($news[0]['isi']), 0, 180)) ?>...</p>
                        <span class="news-date">
                            <i class="fa-regular fa-calendar" aria-hidden="true"></i>
                            <time datetime="<?= date('Y-m-d', strtotime($news[0]['tanggal'])) ?>"><?= date('d M Y', strtotime($news[0]['tanggal'])) ?></time>
                            <i class="fa-regular fa-eye" aria-hidden="true"></i> <?= number_format((int)$news[0]['views']) ?> views
                        </span>
                    </div>
                </a>
            </article>
            <?php if ($news_count > 1): ?>
            <aside class="hx-news-side" role="complementary" aria-label="Berita lainnya">
                <?php foreach (array_slice($news, 1) as $i => $b): ?>
                <a href="<?= PUBLIC_URL ?>berita_detail.php?slug=<?= sanitize($b['slug']) ?>" class="hx-news-mini reveal-up" data-analytics="news-side" data-cursor="read"
                   style="transition-delay: <?= ($i + 1) * 0.1 ?>s;"
                   aria-label="Baca selengkapnya: <?= sanitize($b['judul']) ?>">
                    <div class="hx-news-mini-img" style="background-image:url('<?= img_url($b['gambar']) ?>')"
                         role="img" aria-label="<?= sanitize($b['judul']) ?>"></div>
                    <div>
                        <span class="news-cat"><?= sanitize($b['kategori']) ?></span>
                        <h4><?= sanitize($b['judul']) ?></h4>
                        <span class="news-date">
                            <i class="fa-regular fa-calendar" aria-hidden="true"></i>
                            <time datetime="<?= date('Y-m-d', strtotime($b['tanggal'])) ?>"><?= date('d M Y', strtotime($b['tanggal'])) ?></time>
                        </span>
                    </div>
                </a>
                <?php endforeach; ?>
            </aside>
            <?php endif; ?>
        </div>
        <div class="sec-action reveal-up">
            <a href="<?= PUBLIC_URL ?>berita.php" class="btn-primary magnetic-btn" data-cursor="pointer" data-analytics="news-view-all" data-i18n="view_all_news">Lihat Semua Berita</a>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============ 8. DOSEN PREMIUM ============ -->
<?php if (!empty($dosen)): ?>
<section class="home-dosen-section" aria-labelledby="dosen-heading">
    <div class="container-custom">
        <header class="sec-head">
            <span class="sec-eyebrow ey-glass text-reveal">
                <i class="fa-solid fa-chalkboard-user" aria-hidden="true"></i>
                <span data-i18n="academic_team">TIM AKADEMIK</span>
            </span>
            <h2 class="section-title text-reveal" id="dosen-heading" style="color:#fff;" data-i18n="our_lecturers">Tenaga Pengajar Kami</h2>
            <p class="section-subtitle fade-in-up" style="color:#bdc3c7;" data-i18n="lecturers_desc">Dosen berpendidikan S2 dan S3 dari dalam dan luar negeri.</p>
        </header>
        <div class="home-dosen-grid" role="list">
            <?php foreach ($dosen as $i => $d): ?>
            <article class="home-dosen-card reveal-up magnetic-card" role="listitem" style="transition-delay: <?= $i * 0.1 ?>s;" data-cursor="hover">
                <div class="home-dosen-photo"
                     style="background-image:url('<?= img_url($d['foto'] ?? '') ?>')"
                     role="img" aria-label="Foto <?= sanitize($d['nama']) ?>">
                    <div class="dosen-overlay">
                        <div class="dosen-socials">
                            <?php if (!empty($d['email'])): ?>
                            <a href="mailto:<?= sanitize($d['email']) ?>" aria-label="Email <?= sanitize($d['nama']) ?>"><i class="fa-solid fa-envelope" aria-hidden="true"></i></a>
                            <?php endif; ?>
                            <?php if (!empty($d['link_scholar'])): ?>
                            <a href="<?= sanitize($d['link_scholar']) ?>" target="_blank" rel="noopener noreferrer" aria-label="Google Scholar <?= sanitize($d['nama']) ?>"><i class="fa-solid fa-graduation-cap" aria-hidden="true"></i></a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="home-dosen-info">
                    <h3><?= sanitize(trim(($d['gelar_depan'] ?? '') . ' ' . $d['nama'] . ', ' . ($d['gelar_belakang'] ?? ''))) ?></h3>
                    <span class="badge-blue"><?= sanitize($d['bidang_keahlian']) ?></span>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
        <div class="sec-action reveal-up">
            <a href="<?= PUBLIC_URL ?>dosen.php" class="btn-primary btn-sky magnetic-btn" data-cursor="pointer" data-analytics="dosen-view-all" data-i18n="view_all_lecturers">
                <i class="fa-solid fa-users" aria-hidden="true"></i> <span>Lihat Semua Dosen</span>
            </a>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============ 9. TESTIMONI ALUMNI ============ -->
<?php if (!empty($alumni)): ?>
<section class="band band-light band-dots hx-testi-section" aria-labelledby="testi-heading">
    <div class="section">
        <header class="sec-head">
            <span class="sec-eyebrow ey-emerald text-reveal">
                <i class="fa-solid fa-quote-left" aria-hidden="true"></i>
                <span data-i18n="testimonial">TESTIMONI</span>
            </span>
            <h2 class="section-title text-reveal" id="testi-heading" data-i18n="testi_title">Kata Mereka, Alumni Kami</h2>
            <p class="section-subtitle fade-in-up" data-i18n="testi_desc">Jejak sukses lulusan PBSI di dunia kerja nasional dan internasional.</p>
        </header>
        <div class="hx-testi-marquee" role="region" aria-label="Testimoni alumni">
            <div class="hx-testi-track">
                <?php for ($r = 0; $r < 2; $r++) { foreach ($alumni as $a): ?>
                <blockquote class="hx-testi-card">
                    <i class="fa-solid fa-quote-left hx-testi-q" aria-hidden="true"></i>
                    <p><?= sanitize($a['testimoni']) ?></p>
                    <footer class="hx-testi-who">
                        <div class="hx-testi-ava" aria-hidden="true"><?= strtoupper(substr($a['nama'], 0, 1)) ?></div>
                        <div>
                            <strong><?= sanitize($a['nama']) ?></strong>
                            <small>Angkatan <?= (int)$a['angkatan'] ?> · <?= sanitize($a['pekerjaan']) ?></small>
                        </div>
                    </footer>
                </blockquote>
                <?php endforeach; } ?>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============ 10. MITRA MARQUEE ============ -->
<div class="hx-partners" role="region" aria-label="Mitra kerjasama">
    <div class="hx-marquee-track hx-partners-track">
        <?php
        $mitra = ['BRITISH COUNCIL', 'AMINEF FULLBRIGHT', 'CAMBRIDGE ASSESSMENT', 'KEMENDIKBUDRISTEK', 'LPDP', 'UNIVERSITAS MITRA LUAR NEGERI', 'MEDIA PARTNER NASIONAL'];
        for ($r = 0; $r < 2; $r++) { foreach ($mitra as $mt) { echo '<span class="hx-mq-item hx-mq-dark" aria-hidden="true"><i class="fa-solid fa-handshake"></i>' . $mt . '</span>'; } }
        ?>
    </div>
</div>

<!-- ============ 11. CTA PENDAFTARAN ============ -->
<section class="home-cta-section" aria-labelledby="cta-heading">
    <div class="home-cta-box reveal-up">
        <div class="home-cta-content">
            <span class="home-cta-badge text-reveal">
                <i class="fa-solid fa-bullhorn" aria-hidden="true"></i>
                <span data-i18n="pmb_open">PMB 2026/2027 DIBUKA</span>
            </span>
            <h2 id="cta-heading" class="text-reveal" data-i18n="join_family">Bergabunglah dengan Keluarga Besar PBSI</h2>
            <p class="fade-in-up" data-i18n="join_desc">Raih masa depan gemilang sebagai penerjemah, pengajar, sastrawan, atau diplomat global bersama kami.</p>
            <div class="home-cta-buttons">
                <a href="<?= PUBLIC_URL ?>kontak.php" class="btn-primary magnetic-btn" data-cursor="pointer" data-analytics="cta-register" data-i18n="register_now">
                    <i class="fa-solid fa-user-plus" aria-hidden="true"></i> <span>Daftar Sekarang</span>
                </a>
                <a href="<?= PUBLIC_URL ?>kurikulum.php" class="btn-secondary magnetic-btn" data-cursor="pointer" data-analytics="cta-curriculum" data-i18n="view_curriculum">
                    <i class="fa-solid fa-book" aria-hidden="true"></i> <span>Lihat Kurikulum</span>
                </a>
            </div>
        </div>
    </div>
</section>

</main>

<!-- ============================================================
     PREMIUM CSS ENRICHMENT
============================================================ -->
<style>
/* ===== READING PROGRESS BAR ===== */
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
.reading-progress-fill {
    height: 100%;
    width: 0%;
    background: linear-gradient(90deg, var(--crimson), var(--sky-blue), var(--emerald));
    box-shadow: 0 0 10px rgba(138,21,56,0.5);
    transition: width 0.1s ease-out;
}

/* ===== CUSTOM CURSOR ===== */
.custom-cursor {
    position: fixed;
    top: 0;
    left: 0;
    pointer-events: none;
    z-index: 99999;
    mix-blend-mode: difference;
}
.cursor-dot {
    position: absolute;
    width: 8px;
    height: 8px;
    background: #fff;
    border-radius: 50%;
    transform: translate(-50%, -50%);
    transition: transform 0.15s ease-out, width 0.3s, height 0.3s;
}
.cursor-ring {
    position: absolute;
    width: 40px;
    height: 40px;
    border: 2px solid rgba(255,255,255,0.5);
    border-radius: 50%;
    transform: translate(-50%, -50%);
    transition: transform 0.2s ease-out, width 0.3s, height 0.3s, border-color 0.3s;
}
.custom-cursor.hover .cursor-dot { width: 12px; height: 12px; }
.custom-cursor.hover .cursor-ring { width: 60px; height: 60px; border-color: var(--crimson); }
.custom-cursor.pointer .cursor-dot { width: 4px; height: 4px; background: var(--sky-blue); }
.custom-cursor.pointer .cursor-ring { width: 50px; height: 50px; border-color: var(--sky-blue); border-width: 3px; }
.custom-cursor.play .cursor-dot { width: 0; height: 0; }
.custom-cursor.play .cursor-ring {
    width: 80px; height: 80px;
    border-color: var(--crimson);
    border-width: 3px;
    background: rgba(138,21,56,0.1);
    backdrop-filter: blur(4px);
}
.custom-cursor.play .cursor-ring::after {
    content: '▶';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    color: #fff;
    font-size: 1.5rem;
}
.custom-cursor.read .cursor-dot { width: 6px; height: 6px; background: var(--emerald); }
.custom-cursor.read .cursor-ring {
    width: 70px; height: 70px;
    border-color: var(--emerald);
    border-style: dashed;
    animation: cursorRotate 8s linear infinite;
}
@keyframes cursorRotate {
    to { transform: translate(-50%, -50%) rotate(360deg); }
}

@media (hover: none), (pointer: coarse) {
    .custom-cursor { display: none; }
}

/* ===== TEXT REVEAL ANIMATION ===== */
.text-reveal {
    position: relative;
    overflow: hidden;
}
.text-reveal > * {
    display: inline-block;
    opacity: 0;
    transform: translateY(100%);
    transition: all 0.8s cubic-bezier(0.22, 0.61, 0.36, 1);
}
.text-reveal.visible > * {
    opacity: 1;
    transform: translateY(0);
}
.text-reveal.visible > *:nth-child(1) { transition-delay: 0.1s; }
.text-reveal.visible > *:nth-child(2) { transition-delay: 0.2s; }
.text-reveal.visible > *:nth-child(3) { transition-delay: 0.3s; }
.text-reveal.visible > *:nth-child(4) { transition-delay: 0.4s; }
.text-reveal.visible > *:nth-child(5) { transition-delay: 0.5s; }

/* ===== REVEAL ANIMATIONS ===== */
.reveal-up {
    opacity: 0;
    transform: translateY(40px);
    transition: opacity 0.8s cubic-bezier(0.22, 0.61, 0.36, 1),
                transform 0.8s cubic-bezier(0.22, 0.61, 0.36, 1);
}
.reveal-up.visible {
    opacity: 1;
    transform: translateY(0);
}
.reveal-left {
    opacity: 0;
    transform: translateX(-40px);
    transition: opacity 0.8s cubic-bezier(0.22, 0.61, 0.36, 1),
                transform 0.8s cubic-bezier(0.22, 0.61, 0.36, 1);
}
.reveal-left.visible {
    opacity: 1;
    transform: translateX(0);
}
.reveal-right {
    opacity: 0;
    transform: translateX(40px);
    transition: opacity 0.8s cubic-bezier(0.22, 0.61, 0.36, 1),
                transform 0.8s cubic-bezier(0.22, 0.61, 0.36, 1);
}
.reveal-right.visible {
    opacity: 1;
    transform: translateX(0);
}

/* ===== MAGNETIC BUTTONS ===== */
.magnetic-btn, .magnetic-card {
    transition: transform 0.3s cubic-bezier(0.22, 0.61, 0.36, 1);
    will-change: transform;
}

/* ===== FLOATING CARDS ENHANCEMENT ===== */
.floating-card {
    animation: floatSoft 6s ease-in-out infinite;
}
.floating-card:nth-child(2) {
    animation-delay: -2s;
}
@keyframes floatSoft {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-12px); }
}

/* ===== STAT SUFFIX ANIMATION ===== */
.stat-suffix {
    display: inline-block;
    opacity: 0;
    transform: translateX(-10px);
    transition: all 0.6s cubic-bezier(0.22, 0.61, 0.36, 1) 0.3s;
}
.stat-item.visible .stat-suffix {
    opacity: 1;
    transform: translateX(0);
}

/* ===== DARK MODE ENHANCEMENTS ===== */
[data-theme="dark"] body {
    background: var(--rich-black);
    color: #e0e0e0;
}
[data-theme="dark"] .band-light {
    background: linear-gradient(180deg, #1a1a2e 0%, var(--rich-black) 100%);
}
[data-theme="dark"] .band-tint {
    background: linear-gradient(180deg, var(--rich-black) 0%, #1a1a2e 100%);
}
[data-theme="dark"] .feature-card,
[data-theme="dark"] .hx-news-main,
[data-theme="dark"] .hx-news-mini,
[data-theme="dark"] .hx-testi-card {
    background: #1a1a2e;
    color: #e0e0e0;
    border: 1px solid rgba(255,255,255,0.05);
}
[data-theme="dark"] .feature-card h3,
[data-theme="dark"] .hx-news-main-body h3,
[data-theme="dark"] .hx-news-mini h4,
[data-theme="dark"] .hx-testi-card strong {
    color: #fff;
}
[data-theme="dark"] .feature-card p,
[data-theme="dark"] .hx-news-main-body p,
[data-theme="dark"] .hx-testi-card p {
    color: #b0b0b0;
}
[data-theme="dark"] .sec-head .section-title {
    color: #fff;
}
[data-theme="dark"] .section-subtitle {
    color: #b0b0b0;
}
[data-theme="dark"] .hx-about-text p,
[data-theme="dark"] .hx-checklist li {
    color: #c0c0c0;
}
[data-theme="dark"] .reading-progress {
    background: rgba(255,255,255,0.05);
}
[data-theme="dark"] .hx-btn-ghost {
    border-color: #fff;
    color: #fff;
}
[data-theme="dark"] .hx-btn-ghost:hover {
    background: #fff;
    color: var(--rich-black);
}

/* ===== PARALLAX SUBTLE ===== */
.parallax-section {
    position: relative;
    overflow: hidden;
}
.parallax-section .hx-collage {
    will-change: transform;
}

/* ===== UTILITY CLASSES ===== */
.skip-link {
    position: absolute;
    top: -60px;
    left: 0;
    background: var(--crimson);
    color: #fff;
    padding: .6rem 1.2rem;
    border-radius: 0 0 10px 0;
    z-index: 10000;
    transition: top .2s;
}
.skip-link:focus {
    top: 0;
}
.sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0,0,0,0);
    white-space: nowrap;
    border: 0;
}
*:focus-visible {
    outline: 3px solid var(--sky-blue);
    outline-offset: 2px;
}

/* ===== PRINT STYLES ===== */
@media print {
    .hx-slider, .hx-marquee, .hx-partners, .home-cta-section,
    .hx-scroll-hint, .reading-progress, .custom-cursor,
    .nav-toggle, .nav-actions { display: none !important; }
    body { background: #fff; color: #000; }
    .band, .section { break-inside: avoid; }
}

/* ===== REDUCED MOTION ===== */
@media (prefers-reduced-motion: reduce) {
    *, *::before, *::after {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
        scroll-behavior: auto !important;
    }
    .reading-progress-fill,
    .reveal-up, .reveal-left, .reveal-right,
    .text-reveal > *, .floating-card {
        transition: none !important;
        transform: none !important;
        opacity: 1 !important;
    }
}

/* ===== LANGUAGE SWITCHING ===== */
[data-lang] { display: none; }
[data-lang="id"] { display: inline; }
html[lang="en"] [data-lang="id"] { display: none; }
html[lang="en"] [data-lang="en"] { display: inline; }

/* For block elements */
div[data-lang="id"], p[data-lang="id"], li[data-lang="id"],
span[data-lang="id"], h1[data-lang="id"], h2[data-lang="id"],
h3[data-lang="id"], h4[data-lang="id"], small[data-lang="id"] {
    display: block;
}
html[lang="en"] div[data-lang="id"],
html[lang="en"] p[data-lang="id"],
html[lang="en"] li[data-lang="id"],
html[lang="en"] h1[data-lang="id"],
html[lang="en"] h2[data-lang="id"],
html[lang="en"] h3[data-lang="id"] {
    display: none;
}
html[lang="en"] div[data-lang="en"],
html[lang="en"] p[data-lang="en"],
html[lang="en"] li[data-lang="en"],
html[lang="en"] h1[data-lang="en"],
html[lang="en"] h2[data-lang="en"],
html[lang="en"] h3[data-lang="en"] {
    display: block;
}

/* ===== COUNTER ANIMATION ENHANCEMENT ===== */
.stat-item h3 {
    position: relative;
    display: inline-block;
}
.stat-item h3::after {
    content: attr(data-suffix);
    font-size: 0.6em;
    color: var(--sky-blue);
    margin-left: 0.2em;
    opacity: 0;
    transform: translateX(-10px);
    transition: all 0.6s cubic-bezier(0.22, 0.61, 0.36, 1) 0.3s;
}
.stat-item.visible h3::after {
    opacity: 1;
    transform: translateX(0);
}
</style>

<!-- ============================================================
     PREMIUM JAVASCRIPT ENRICHMENT
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

    // ===== CUSTOM CURSOR =====
    const cursor = document.getElementById('customCursor');
    const cursorDot = cursor?.querySelector('.cursor-dot');
    const cursorRing = cursor?.querySelector('.cursor-ring');

    if (cursor && window.matchMedia('(hover: hover)').matches) {
        let mouseX = 0, mouseY = 0;
        let cursorX = 0, cursorY = 0;
        let ringX = 0, ringY = 0;

        document.addEventListener('mousemove', (e) => {
            mouseX = e.clientX;
            mouseY = e.clientY;
        });

        function animateCursor() {
            // Dot follows instantly
            cursorX = mouseX;
            cursorY = mouseY;
            cursorDot.style.transform = `translate(${cursorX - 4}px, ${cursorY - 4}px)`;

            // Ring follows with ease
            ringX += (mouseX - ringX) * 0.15;
            ringY += (mouseY - ringY) * 0.15;
            cursorRing.style.transform = `translate(${ringX - 20}px, ${ringY - 20}px)`;

            requestAnimationFrame(animateCursor);
        }
        animateCursor();

        // Hover states
        document.querySelectorAll('[data-cursor]').forEach(el => {
            el.addEventListener('mouseenter', () => {
                cursor.className = 'custom-cursor ' + el.dataset.cursor;
            });
            el.addEventListener('mouseleave', () => {
                cursor.className = 'custom-cursor';
            });
        });
    }

    // ===== MAGNETIC BUTTONS =====
    document.querySelectorAll('.magnetic-btn, .magnetic-card').forEach(el => {
        el.addEventListener('mousemove', (e) => {
            const rect = el.getBoundingClientRect();
            const x = e.clientX - rect.left - rect.width / 2;
            const y = e.clientY - rect.top - rect.height / 2;
            const strength = el.classList.contains('magnetic-card') ? 0.05 : 0.15;
            el.style.transform = `translate(${x * strength}px, ${y * strength}px)`;
        });
        el.addEventListener('mouseleave', () => {
            el.style.transform = '';
        });
    });

    // ===== TEXT REVEAL (Word by Word) =====
    document.querySelectorAll('.text-reveal').forEach(el => {
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

    document.querySelectorAll('.reveal-up, .reveal-left, .reveal-right, .text-reveal').forEach(el => {
        revealObserver.observe(el);
    });

    // ===== PARALLAX EFFECT =====
    const parallaxSections = document.querySelectorAll('.parallax-section');
    if (parallaxSections.length > 0) {
        let parallaxTicking = false;
        window.addEventListener('scroll', () => {
            if (!parallaxTicking) {
                requestAnimationFrame(() => {
                    parallaxSections.forEach(section => {
                        const speed = parseFloat(section.dataset.speed) || 0.3;
                        const rect = section.getBoundingClientRect();
                        const scrolled = window.innerHeight - rect.top;
                        if (rect.top < window.innerHeight && rect.bottom > 0) {
                            const collage = section.querySelector('.hx-collage');
                            if (collage) {
                                collage.style.transform = `translateY(${scrolled * speed * 0.1}px)`;
                            }
                        }
                    });
                    parallaxTicking = false;
                });
                parallaxTicking = true;
            }
        }, { passive: true });
    }

    // ===== COUNTER ANIMATION WITH SUFFIX =====
    const counterObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting && !entry.target.dataset.counted) {
                entry.target.dataset.counted = '1';
                entry.target.classList.add('visible');

                const counters = entry.target.querySelectorAll('h3[data-target]');
                counters.forEach(counter => {
                    const target = parseInt(counter.dataset.target);
                    const suffix = counter.dataset.suffix || '';
                    const duration = 2000;
                    const step = target / (duration / 16);
                    let current = 0;

                    const timer = setInterval(() => {
                        current += step;
                        if (current >= target) {
                            current = target;
                            clearInterval(timer);
                        }
                        counter.textContent = Math.floor(current).toLocaleString('id-ID');
                    }, 16);
                });
            }
        });
    }, { threshold: 0.3 });

    document.querySelectorAll('.stats-grid').forEach(el => {
        counterObserver.observe(el);
    });

    // ===== STAGGER ANIMATIONS =====
    document.querySelectorAll('.features-grid .feature-card, .home-dosen-grid .home-dosen-card').forEach((el, i) => {
        el.style.transitionDelay = (i * 0.08) + 's';
    });

    // ===== INITIAL REVEAL FOR HERO =====
    window.addEventListener('load', () => {
        setTimeout(() => {
            document.querySelectorAll('.hx-slide.active .text-reveal').forEach(el => {
                el.classList.add('visible');
            });
        }, 800);
    });

})();
</script>

<!-- Multi-language Content Support -->
<script>
(function () {
    'use strict';

    // Language translations
    const translations = {
        en: {
            'welcome': 'WELCOME',
            'apply_now': 'Apply Now',
            'scroll': 'SCROLL',
            'about_prodi': 'ABOUT THE PROGRAM',
            'about_title': 'Center of Excellence for Language & Literature in Eastern Indonesia',
            'about_desc': 'Since 2010, the English Language and Literature Study Program of Muhammadiyah University of Maumere has consistently produced globally competitive linguists, translators, educators, and literary scholars grounded in Islamic and humanitarian values.',
            'accredited': 'Accredited',
            'global_partners': 'Global Partners',
            'check_1': 'OBE curriculum aligned with language industry needs',
            'check_2': 'Professional-standard Language Lab & Translation Studio',
            'check_3': 'Faculty with M.A. and Ph.D. degrees from domestic and international institutions',
            'check_4': 'Internship, MBKM, and student exchange programs',
            'full_profile': 'Full Profile',
            'visi_misi': 'Vision & Mission',
            'our_strength': 'OUR STRENGTHS',
            'why_choose': 'Why Choose PBSI UMMaumere?',
            'why_desc': 'The advantages that make us the center of excellence for language and literature in Eastern Indonesia.',
            'profile_video': 'PROFILE VIDEO',
            'watch_profile': 'Watch Our Profile',
            'watch_desc': 'Get to know the PBSI UMMaumere family in one minute.',
            'click_play': 'Click to Play',
            'latest_news': 'LATEST NEWS',
            'news_title': 'Latest News & Events',
            'news_desc': 'Highlights of academic activities, achievements, and campus events.',
            'view_all_news': 'View All News',
            'academic_team': 'ACADEMIC TEAM',
            'our_lecturers': 'Our Lecturers',
            'lecturers_desc': 'Faculty with M.A. and Ph.D. degrees from domestic and international institutions.',
            'view_all_lecturers': 'View All Lecturers',
            'testimonial': 'TESTIMONIALS',
            'testi_title': 'What Our Alumni Say',
            'testi_desc': 'Success stories of PBSI graduates in national and international careers.',
            'pmb_open': 'ADMISSIONS 2026/2027 OPEN',
            'join_family': 'Join the PBSI Family',
            'join_desc': 'Achieve a brilliant future as a translator, educator, literary scholar, or global diplomat with us.',
            'register_now': 'Register Now',
            'view_curriculum': 'View Curriculum'
        }
    };

    // Apply translations based on current language
    function applyTranslations() {
        const lang = document.documentElement.getAttribute('lang') || 'id';
        if (lang === 'en' && translations.en) {
            document.querySelectorAll('[data-i18n]').forEach(el => {
                const key = el.dataset.i18n;
                if (translations.en[key]) {
                    // For elements with child nodes (buttons with icons), preserve icons
                    if (el.children.length > 0) {
                        // Find text nodes and replace
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

    // Observe language changes
    const langObserver = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            if (mutation.attributeName === 'lang') {
                applyTranslations();
            }
        });
    });

    langObserver.observe(document.documentElement, { attributes: true });

    // Initial apply
    applyTranslations();
})();
</script>

<?php require_once '../includes/public_footer.php'; ?>