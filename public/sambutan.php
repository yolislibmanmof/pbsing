<?php
require_once '../includes/config.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';
check_ip_security($pdo);

// Helper gambar
if (!function_exists('img_url')) {
    function img_placeholder() {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="400" height="500">'
             . '<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1">'
             . '<stop offset="0" stop-color="#8A1538"/><stop offset="1" stop-color="#1a1a2e"/></linearGradient></defs>'
             . '<rect width="400" height="500" fill="url(#g)"/>'
             . '<circle cx="200" cy="180" r="60" fill="rgba(255,255,255,.15)"/>'
             . '<text x="200" y="350" font-family="Arial" font-size="20" fill="rgba(255,255,255,.4)" text-anchor="middle">Foto Kaprodi</text>'
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

$page_title = 'Sambutan Kaprodi';
require_once '../includes/public_header.php';

$sambutan = get_all('sambutan', null, 'id ASC');
$s = $sambutan[0] ?? null;
$setting = get_all('settings', 'id = 1')[0];
$current_lang = $_COOKIE['pbsi_lang'] ?? 'id';

// Calculate reading time
$reading_time = 0;
$word_count = 0;
if ($s && !empty($s['isi'])) {
    $plain = strip_tags($s['isi']);
    $word_count = str_word_count($plain);
    $reading_time = max(1, ceil($word_count / 200));
}

// Extract key sentences for highlights (kalimat pertama tiap paragraf)
$key_sentences = [];
if ($s && !empty($s['isi'])) {
    preg_match_all('/<p[^>]*>(.*?)<\/p>/is', $s['isi'], $paragraphs);
    if (!empty($paragraphs[1])) {
        foreach ($paragraphs[1] as $idx => $para) {
            if ($idx < 2) {
                // Ambil kalimat pertama
                $sentences = preg_split('/[.!?]\s+/', strip_tags($para));
                if (!empty($sentences[0]) && strlen($sentences[0]) > 40) {
                    $key_sentences[] = trim($sentences[0]);
                }
            }
        }
    }
}
?>
<!-- Reading Progress Bar -->
<div class="reading-progress" id="readingProgress" aria-hidden="true">
    <div class="reading-progress-fill"></div>
</div>

<!-- JSON-LD for Person -->
<?php if ($s): ?>
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Person",
  "name": "<?= sanitize($s['nama']) ?>",
  "jobTitle": "<?= sanitize($s['jabatan']) ?>",
  "worksFor": {
    "@type": "EducationalOrganization",
    "name": "<?= sanitize($setting['site_name'] ?? 'PBSI UMMaumere') ?>"
  },
  "image": "<?= img_url($s['foto'] ?? '') ?>",
  "description": "<?= sanitize(substr(strip_tags($s['isi']), 0, 160)) ?>",
  "sameAs": [
    <?= !empty($s['link_scholar']) ? '"' . sanitize($s['link_scholar']) . '"' : '' ?><?= !empty($s['link_linkedin']) ? (empty($s['link_scholar']) ? '' : ', ') . '"' . sanitize($s['link_linkedin']) . '"' : '' ?>
  ]
}
</script>
<?php endif; ?>

<main id="main-content" role="main">

<!-- ============ PAGE HERO ============ -->
<section class="page-hero samb-hero">
    <div class="samb-hero-content reveal-up">
        <span class="samb-hero-badge text-reveal">
            <i class="fa-solid fa-quote-right" aria-hidden="true"></i>
            <span data-i18n="leadership_message">PESAN PIMPINAN</span>
        </span>
        <h1 class="samb-hero-title text-reveal" data-i18n="page_title">Sambutan Ketua Program Studi</h1>
        <p class="samb-hero-subtitle fade-in-up" data-i18n="page_subtitle">
            Pesan hangat dari pimpinan Program Studi Bahasa dan Sastra Inggris.
        </p>
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="<?= PUBLIC_URL ?>index.php" data-i18n="home">Beranda</a>
            <span aria-hidden="true"><i class="fa-solid fa-chevron-right"></i></span>
            <span aria-current="page" data-i18n="sambutan_breadcrumb">Sambutan Kaprodi</span>
        </nav>
    </div>

    <!-- Decorative Orbs -->
    <div class="samb-hero-decor" aria-hidden="true">
        <div class="samb-orb samb-orb-1"></div>
        <div class="samb-orb samb-orb-2"></div>
    </div>

    <div class="samb-scroll-hint" aria-hidden="true">
        <div class="samb-mouse"><span></span></div>
        <small data-i18n="scroll">SCROLL</small>
    </div>
</section>

<!-- ============ SAMBUTAN SECTION ============ -->
<?php if ($s): ?>
<section class="sambutan-section" aria-labelledby="sambutan-heading">
    <div class="section">
        <header class="sec-head">
            <span class="sec-eyebrow ey-crimson text-reveal">
                <i class="fa-solid fa-user-tie" aria-hidden="true"></i>
                <span data-i18n="kaprodi_message">PESAN KAPRODI</span>
            </span>
            <h2 class="section-title text-reveal" id="sambutan-heading" data-i18n="sambutan_heading">Sambutan Ketua Program Studi</h2>
            <p class="section-subtitle fade-in-up" data-i18n="sambutan_subtitle">
                Visi, misi, dan harapan dari pimpinan Program Studi Bahasa dan Sastra Inggris.
            </p>

            <!-- Reading Time Meta -->
            <?php if ($word_count > 0): ?>
            <div class="samb-meta fade-in-up">
                <span class="samb-meta-item">
                    <i class="fa-solid fa-clock" aria-hidden="true"></i>
                    <span><strong><?= $reading_time ?></strong> <span data-i18n="min_read">menit baca</span></span>
                </span>
                <span class="samb-meta-divider">•</span>
                <span class="samb-meta-item">
                    <i class="fa-solid fa-font" aria-hidden="true"></i>
                    <span><strong><?= number_format($word_count) ?></strong> <span data-i18n="words">kata</span></span>
                </span>
                <span class="samb-meta-divider">•</span>
                <span class="samb-meta-item">
                    <i class="fa-solid fa-calendar" aria-hidden="true"></i>
                    <span><?= date('d F Y') ?></span>
                </span>
            </div>
            <?php endif; ?>
        </header>

        <!-- Main Container -->
        <div class="samb-container">

            <!-- Photo Column -->
            <div class="samb-photo-col reveal-left">
                <div class="samb-photo-frame" data-parallax="0.15">
                    <!-- Decorative Frame -->
                    <div class="samb-frame-decor" aria-hidden="true">
                        <svg viewBox="0 0 400 500" class="frame-svg">
                            <defs>
                                <pattern id="sambPattern" x="0" y="0" width="20" height="20" patternUnits="userSpaceOnUse">
                                    <circle cx="2" cy="2" r="1" fill="rgba(138,21,56,0.3)"/>
                                </pattern>
                            </defs>
                            <rect x="10" y="10" width="380" height="480" fill="url(#sambPattern)" opacity="0.5"/>
                        </svg>
                    </div>

                    <!-- Photo -->
                    <div class="samb-photo">
                        <img src="<?= img_url($s['foto'] ?? '') ?>"
                             alt="Foto <?= sanitize($s['nama']) ?>"
                             loading="eager"
                             onerror="this.onerror=null;this.src='<?= img_placeholder() ?>';">
                    </div>

                    <!-- Floating Jabatan Badge -->
                    <div class="samb-jab-badge">
                        <i class="fa-solid fa-crown" aria-hidden="true"></i>
                        <span><?= sanitize($s['jabatan']) ?></span>
                    </div>

                    <!-- Decorative Accent -->
                    <div class="samb-photo-accent" aria-hidden="true"></div>
                </div>

                <!-- Social Links -->
                <div class="samb-socials reveal-up" style="transition-delay: 0.2s;">
                    <?php if (!empty($s['email'])): ?>
                    <a href="mailto:<?= sanitize($s['email']) ?>"
                       class="samb-soc-link samb-soc-email"
                       aria-label="Email <?= sanitize($s['nama']) ?>"
                       title="Email">
                        <i class="fa-solid fa-envelope" aria-hidden="true"></i>
                    </a>
                    <?php endif; ?>
                    <?php if (!empty($s['link_scholar'])): ?>
                    <a href="<?= sanitize($s['link_scholar']) ?>"
                       target="_blank"
                       rel="noopener noreferrer"
                       class="samb-soc-link samb-soc-scholar"
                       aria-label="Google Scholar"
                       title="Google Scholar">
                        <i class="fa-solid fa-graduation-cap" aria-hidden="true"></i>
                    </a>
                    <?php endif; ?>
                    <?php if (!empty($s['link_linkedin'])): ?>
                    <a href="<?= sanitize($s['link_linkedin']) ?>"
                       target="_blank"
                       rel="noopener noreferrer"
                       class="samb-soc-link samb-soc-linkedin"
                       aria-label="LinkedIn"
                       title="LinkedIn">
                        <i class="fa-brands fa-linkedin-in" aria-hidden="true"></i>
                    </a>
                    <?php endif; ?>
                    <?php if (!empty($s['whatsapp'])): ?>
                    <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $s['whatsapp']) ?>"
                       target="_blank"
                       rel="noopener noreferrer"
                       class="samb-soc-link samb-soc-wa"
                       aria-label="WhatsApp"
                       title="WhatsApp">
                        <i class="fa-brands fa-whatsapp" aria-hidden="true"></i>
                    </a>
                    <?php endif; ?>
                </div>

                <!-- Quick Info Card -->
                <div class="samb-info-card reveal-up" style="transition-delay: 0.3s;">
                    <div class="samb-info-row">
                        <i class="fa-solid fa-id-card" aria-hidden="true"></i>
                        <div>
                            <span data-i18n="nidn_label">NIDN</span>
                            <strong><?= !empty($s['nidn']) ? sanitize($s['nidn']) : '-' ?></strong>
                        </div>
                    </div>
                    <div class="samb-info-row">
                        <i class="fa-solid fa-user-tie" aria-hidden="true"></i>
                        <div>
                            <span data-i18n="position_label">Jabatan</span>
                            <strong><?= sanitize($s['jabatan']) ?></strong>
                        </div>
                    </div>
                    <?php if (!empty($s['email'])): ?>
                    <div class="samb-info-row">
                        <i class="fa-solid fa-envelope" aria-hidden="true"></i>
                        <div>
                            <span>Email</span>
                            <strong style="word-break:break-all;"><?= sanitize($s['email']) ?></strong>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Content Column -->
            <div class="samb-content-col reveal-right">

                <!-- Header -->
                <div class="samb-header">
                    <div class="samb-title-block">
                        <h2 class="samb-name" data-i18n-prefix="kaprodi_name"><?= sanitize($s['nama']) ?></h2>
                        <?php if (!empty($s['gelar'])): ?>
                        <span class="samb-gelar"><?= sanitize($s['gelar']) ?></span>
                        <?php endif; ?>
                    </div>

                    <!-- Quote Icon -->
                    <div class="samb-quote-icon" aria-hidden="true">
                        <i class="fa-solid fa-quote-left"></i>
                    </div>
                </div>

                <!-- Text Content -->
                <div class="samb-text" id="sambText">
                    <div class="samb-isi drop-cap">
                        <?= $s['isi'] ?>
                    </div>
                </div>

                <!-- Key Highlights (kalimat penting) -->
                <?php if (!empty($key_sentences)): ?>
                <aside class="samb-highlights" aria-label="Highlight sambutan">
                    <div class="samb-highlights-header">
                        <i class="fa-solid fa-highlighter" aria-hidden="true"></i>
                        <span data-i18n="key_points">Poin Penting</span>
                    </div>
                    <ul class="samb-highlights-list">
                        <?php foreach (array_slice($key_sentences, 0, 3) as $sentence): ?>
                        <li class="samb-highlight-item">
                            <i class="fa-solid fa-bookmark" aria-hidden="true"></i>
                            <span><?= sanitize($sentence) ?>.</span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </aside>
                <?php endif; ?>

                <!-- Signature Section -->
                <div class="samb-signature" id="sambSignature">
                    <div class="samb-signature-content">
                        <div class="samb-signature-left">
                            <p class="signature-label" data-i18n="regards">Hormat kami,</p>
                            <?php if (!empty($s['tanda_tangan'])): ?>
                            <div class="signature-ink-wrap">
                                <p class="signature-ink"><?= sanitize($s['tanda_tangan']) ?></p>
                                <svg class="signature-ink-line" viewBox="0 0 200 20" preserveAspectRatio="none" aria-hidden="true">
                                    <path class="ink-path" d="M0,10 Q50,0 100,10 T200,10" />
                                </svg>
                            </div>
                            <?php endif; ?>
                            <p class="signature-name-final"><strong><?= sanitize($s['nama']) ?></strong></p>
                            <p class="signature-jab-final"><?= sanitize($s['jabatan']) ?></p>
                        </div>
                        <div class="samb-signature-right">
                            <!-- Digital Stamp -->
                            <div class="samb-stamp" aria-label="Stempel resmi">
                                <div class="samb-stamp-inner">
                                    <span class="stamp-text-top">PBSI</span>
                                    <span class="stamp-icon"><i class="fa-solid fa-graduation-cap"></i></span>
                                    <span class="stamp-text-bottom">UMMaumere</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="samb-actions">
                    <button class="btn-action magnetic-btn" onclick="window.print()" data-cursor="pointer">
                        <i class="fa-solid fa-print" aria-hidden="true"></i>
                        <span data-i18n="print">Cetak</span>
                    </button>
                    <button class="btn-action magnetic-btn" onclick="openShareModal()" data-cursor="pointer">
                        <i class="fa-solid fa-share-nodes" aria-hidden="true"></i>
                        <span data-i18n="share">Bagikan</span>
                    </button>
                    <button class="btn-action magnetic-btn" onclick="downloadAsPDF()" data-cursor="pointer">
                        <i class="fa-solid fa-file-pdf" aria-hidden="true"></i>
                        <span data-i18n="download_pdf">Unduh PDF</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- ============ VIDEO SAMBUTAN ============ -->
        <?php if (!empty($s['video_url'])): ?>
        <div class="samb-video-section reveal-up">
            <header class="sec-head" style="margin-bottom:2rem;">
                <span class="sec-eyebrow ey-sky text-reveal">
                    <i class="fa-solid fa-video" aria-hidden="true"></i>
                    <span data-i18n="video_message">VIDEO SAMBUTAN</span>
                </span>
                <h3 class="section-title text-reveal" data-i18n="video_heading">Saksikan Langsung</h3>
            </header>

            <div class="samb-video-wrapper">
                <div class="samb-video-frame">
                    <div class="samb-video-placeholder"
                         data-video="<?= sanitize($s['video_url']) ?>"
                         role="button"
                         tabindex="0"
                         aria-label="Putar video sambutan">
                        <div class="video-play-btn">
                            <i class="fa-solid fa-play" aria-hidden="true"></i>
                        </div>
                        <div class="video-label">
                            <span data-i18n="click_play">Klik untuk Memutar</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- ============ CTA SECTION ============ -->
<section class="samb-cta-section" aria-labelledby="cta-heading">
    <div class="samb-cta-bg" aria-hidden="true">
        <div class="samb-cta-orb samb-cta-orb-1"></div>
        <div class="samb-cta-orb samb-cta-orb-2"></div>
    </div>
    <div class="samb-cta-box reveal-up">
        <div class="samb-cta-icon">
            <i class="fa-solid fa-handshake" aria-hidden="true"></i>
        </div>
        <h2 id="cta-heading" class="text-reveal" data-i18n="cta_heading">Mari Berkolaborasi</h2>
        <p class="fade-in-up" data-i18n="cta_desc">
            Kami terbuka untuk kerja sama akademik, riset, dan pengembangan program studi.
        </p>
        <div class="samb-cta-buttons">
            <a href="<?= PUBLIC_URL ?>kontak.php" class="btn-primary magnetic-btn" data-cursor="pointer">
                <i class="fa-solid fa-envelope" aria-hidden="true"></i>
                <span data-i18n="contact_us">Hubungi Kami</span>
            </a>
            <a href="<?= PUBLIC_URL ?>visi_misi.php" class="btn-secondary magnetic-btn" data-cursor="pointer">
                <i class="fa-solid fa-bullseye" aria-hidden="true"></i>
                <span data-i18n="view_vision">Lihat Visi & Misi</span>
            </a>
        </div>
    </div>
</section>

<?php else: ?>
<!-- ============ EMPTY STATE ============ -->
<section class="section">
    <div class="samb-empty reveal-up">
        <div class="samb-empty-icon">
            <i class="fa-solid fa-user-tie" aria-hidden="true"></i>
        </div>
        <h2 data-i18n="empty_title">Sambutan Belum Tersedia</h2>
        <p data-i18n="empty_desc">Sambutan dari Ketua Program Studi sedang dalam proses penulisan. Silakan kunjungi kembali nanti.</p>
        <a href="<?= PUBLIC_URL ?>kontak.php" class="btn-primary magnetic-btn" data-i18n="contact_us">
            <i class="fa-solid fa-headset" aria-hidden="true"></i>
            <span>Hubungi Kami</span>
        </a>
    </div>
</section>
<?php endif; ?>

</main>

<!-- ============ SHARE MODAL ============ -->
<div class="samb-share-modal" id="sambShareModal" role="dialog" aria-modal="true" aria-labelledby="shareTitle">
    <div class="samb-share-backdrop" onclick="closeShareModal()"></div>
    <div class="samb-share-content">
        <button class="samb-share-close" onclick="closeShareModal()" aria-label="Tutup">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <h3 id="shareTitle"><i class="fa-solid fa-share-nodes"></i> <span data-i18n="share_heading">Bagikan Sambutan</span></h3>

        <div class="samb-share-grid">
            <button class="samb-share-btn share-twitter" onclick="shareTo('twitter')">
                <i class="fa-brands fa-x-twitter"></i>
                <span>Twitter</span>
            </button>
            <button class="samb-share-btn share-facebook" onclick="shareTo('facebook')">
                <i class="fa-brands fa-facebook-f"></i>
                <span>Facebook</span>
            </button>
            <button class="samb-share-btn share-whatsapp" onclick="shareTo('whatsapp')">
                <i class="fa-brands fa-whatsapp"></i>
                <span>WhatsApp</span>
            </button>
            <button class="samb-share-btn share-linkedin" onclick="shareTo('linkedin')">
                <i class="fa-brands fa-linkedin-in"></i>
                <span>LinkedIn</span>
            </button>
            <button class="samb-share-btn share-email" onclick="shareTo('email')">
                <i class="fa-solid fa-envelope"></i>
                <span>Email</span>
            </button>
            <button class="samb-share-btn share-copy" onclick="shareTo('copy')">
                <i class="fa-solid fa-link"></i>
                <span data-i18n="copy_link">Salin Link</span>
            </button>
        </div>

        <!-- QR Code -->
        <div class="samb-share-qr">
            <p class="qr-label" data-i18n="scan_qr">Atau pindai QR Code:</p>
            <div class="qr-code-wrap">
                <img id="qrCodeImg" alt="QR Code" loading="lazy">
            </div>
        </div>
    </div>
</div>

<!-- ============================================================
     PREMIUM CSS — SAMBUTAN KAPRODI
============================================================ -->
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;1,700&family=Dancing+Script:wght@700&display=swap" rel="stylesheet">

<style>
/* ===== HERO ===== */
.samb-hero {
    position: relative;
    padding: 11rem 1.5rem 5rem;
    overflow: hidden;
}
.samb-hero-content {
    max-width: 900px;
    margin: 0 auto;
    position: relative;
    z-index: 2;
    text-align: center;
}
.samb-hero-badge {
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
.samb-hero-badge i { color: var(--crimson); }
.samb-hero-title {
    font-size: clamp(2.2rem, 7vw, 4.5rem);
    font-weight: 900;
    line-height: 1.05;
    margin-bottom: 1.2rem;
    background: linear-gradient(135deg, var(--sky-blue), #fff);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
}
.samb-hero-subtitle {
    font-size: 1.2rem;
    color: #cfd8dc;
    max-width: 700px;
    margin: 0 auto 2rem;
    line-height: 1.7;
}
.samb-hero .breadcrumb { justify-content: center; font-size: .9rem; }
.samb-hero .breadcrumb i { font-size: .7rem; color: var(--sky-blue); }

.samb-hero-decor { position: absolute; inset: 0; pointer-events: none; overflow: hidden; }
.samb-orb { position: absolute; border-radius: 50%; filter: blur(80px); opacity: .4; }
.samb-orb-1 {
    width: 400px; height: 400px;
    background: var(--crimson);
    top: -100px; left: -100px;
    animation: orbFloat 20s ease-in-out infinite;
}
.samb-orb-2 {
    width: 350px; height: 350px;
    background: var(--sky-blue-dark);
    bottom: -100px; right: -100px;
    animation: orbFloat 25s ease-in-out infinite reverse;
}
@keyframes orbFloat {
    0%, 100% { transform: translate(0, 0) scale(1); }
    33% { transform: translate(50px, -30px) scale(1.1); }
    66% { transform: translate(-30px, 50px) scale(0.9); }
}

.samb-scroll-hint {
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
.samb-scroll-hint small { font-size: .7rem; letter-spacing: 3px; font-weight: 700; }
.samb-mouse {
    width: 24px; height: 38px;
    border: 2px solid rgba(255,255,255,.6);
    border-radius: 14px;
    display: flex;
    justify-content: center;
    padding-top: 6px;
}
.samb-mouse span {
    width: 3px; height: 8px;
    background: var(--sky-blue);
    border-radius: 3px;
    animation: sambWheel 1.6s ease-in-out infinite;
}
@keyframes sambWheel {
    0% { transform: translateY(0); opacity: 1; }
    70% { transform: translateY(12px); opacity: 0; }
    100% { opacity: 0; }
}

/* ===== READING META ===== */
.samb-meta {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1rem;
    margin-top: 2rem;
    flex-wrap: wrap;
}
.samb-meta-item {
    display: flex;
    align-items: center;
    gap: .5rem;
    color: var(--text-grey);
    font-size: .9rem;
}
.samb-meta-item i { color: var(--crimson); }
.samb-meta-item strong { color: var(--rich-black); font-weight: 800; }
[data-theme="dark"] .samb-meta-item strong { color: #fff; }
.samb-meta-divider { color: var(--text-grey); opacity: .4; }

/* ===== MAIN CONTAINER ===== */
.sambutan-section {
    background: var(--bg-light);
    padding: 5rem 1.5rem;
}
[data-theme="dark"] .sambutan-section { background: var(--rich-black); }
.samb-container {
    max-width: 1200px;
    margin: 0 auto;
    display: grid;
    grid-template-columns: 1fr 1.4fr;
    gap: 4rem;
    align-items: start;
}

/* ===== PHOTO COLUMN ===== */
.samb-photo-col {
    position: sticky;
    top: 120px;
}
.samb-photo-frame {
    position: relative;
    aspect-ratio: 4/5;
    border-radius: 24px;
    overflow: hidden;
    box-shadow: 0 30px 80px rgba(0,0,0,.2);
    will-change: transform;
    transition: transform .3s ease;
}
.samb-frame-decor {
    position: absolute;
    inset: 0;
    z-index: 1;
    pointer-events: none;
}
.frame-svg { width: 100%; height: 100%; }
.samb-photo {
    position: relative;
    z-index: 2;
    width: 100%;
    height: 100%;
    overflow: hidden;
}
.samb-photo img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform .6s ease;
}
.samb-photo-frame:hover img { transform: scale(1.05); }
.samb-photo-accent {
    position: absolute;
    inset: 0;
    background: linear-gradient(180deg, transparent 40%, rgba(138,21,56,0.4) 100%);
    z-index: 3;
    pointer-events: none;
}

/* Floating Jabatan Badge */
.samb-jab-badge {
    position: absolute;
    top: 20px;
    left: 20px;
    z-index: 4;
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    padding: .5rem 1rem;
    background: rgba(255,255,255,0.95);
    backdrop-filter: blur(10px);
    border-radius: 50px;
    font-size: .78rem;
    font-weight: 700;
    color: var(--crimson);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    animation: badgeFloat 4s ease-in-out infinite;
}
.samb-jab-badge i { color: var(--warning); }
@keyframes badgeFloat {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-8px); }
}

/* Social Links */
.samb-socials {
    display: flex;
    gap: .6rem;
    justify-content: center;
    margin-top: 1.5rem;
    flex-wrap: wrap;
}
.samb-soc-link {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: #fff;
    color: var(--rich-black);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    transition: all .3s cubic-bezier(0.22, 0.61, 0.36, 1);
    box-shadow: 0 4px 15px rgba(0,0,0,.08);
    border: 1px solid rgba(0,0,0,0.05);
}
[data-theme="dark"] .samb-soc-link { background: #1a1a2e; color: #fff; border-color: rgba(255,255,255,0.08); }
.samb-soc-link:hover {
    transform: translateY(-5px) scale(1.1);
    box-shadow: 0 12px 30px rgba(0,0,0,0.15);
    color: #fff;
}
.samb-soc-email:hover { background: var(--crimson); }
.samb-soc-scholar:hover { background: #4285F4; }
.samb-soc-linkedin:hover { background: #0a66c2; }
.samb-soc-wa:hover { background: #25D366; }

/* Info Card */
.samb-info-card {
    background: #fff;
    border-radius: 16px;
    padding: 1.5rem;
    margin-top: 1.5rem;
    box-shadow: 0 8px 25px rgba(0,0,0,.05);
    border: 1px solid rgba(0,0,0,0.05);
}
[data-theme="dark"] .samb-info-card { background: #1a1a2e; border-color: rgba(255,255,255,0.05); }
.samb-info-row {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: .8rem 0;
    border-bottom: 1px solid rgba(0,0,0,0.05);
}
[data-theme="dark"] .samb-info-row { border-bottom-color: rgba(255,255,255,0.05); }
.samb-info-row:last-child { border-bottom: none; padding-bottom: 0; }
.samb-info-row:first-child { padding-top: 0; }
.samb-info-row i {
    width: 36px; height: 36px;
    border-radius: 10px;
    background: rgba(138,21,56,0.1);
    color: var(--crimson);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.samb-info-row span {
    display: block;
    font-size: .72rem;
    color: var(--text-grey);
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: .2rem;
}
.samb-info-row strong {
    display: block;
    color: var(--rich-black);
    font-size: .92rem;
    font-weight: 700;
}
[data-theme="dark"] .samb-info-row strong { color: #fff; }

/* ===== CONTENT COLUMN ===== */
.samb-content-col {
    background: #fff;
    border-radius: 24px;
    padding: 3rem;
    box-shadow: 0 20px 60px rgba(0,0,0,.08);
    position: relative;
    overflow: hidden;
}
[data-theme="dark"] .samb-content-col { background: #1a1a2e; }
.samb-content-col::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 5px;
    background: linear-gradient(90deg, var(--crimson), var(--sky-blue), var(--emerald));
}

/* Header */
.samb-header {
    position: relative;
    margin-bottom: 2.5rem;
    padding-bottom: 2rem;
    border-bottom: 1px solid rgba(0,0,0,0.08);
}
[data-theme="dark"] .samb-header { border-bottom-color: rgba(255,255,255,0.08); }
.samb-title-block { position: relative; z-index: 2; }
.samb-name {
    font-family: 'Playfair Display', serif;
    font-size: 2.4rem;
    font-weight: 700;
    color: var(--rich-black);
    margin-bottom: .3rem;
    line-height: 1.1;
}
[data-theme="dark"] .samb-name { color: #fff; }
.samb-gelar {
    display: block;
    font-size: 1rem;
    color: var(--text-grey);
    font-style: italic;
    margin-bottom: .8rem;
}
.samb-quote-icon {
    position: absolute;
    top: -10px;
    right: 0;
    font-size: 6rem;
    color: var(--crimson);
    opacity: .08;
    line-height: 1;
    z-index: 1;
}

/* Text Content */
.samb-text {
    position: relative;
    margin-bottom: 2.5rem;
}
.samb-isi {
    color: #333;
    line-height: 1.9;
    font-size: 1.05rem;
}
[data-theme="dark"] .samb-isi { color: #c8c8c8; }
.samb-isi p {
    margin-bottom: 1.3rem;
    text-align: justify;
}
.samb-isi.drop-cap p:first-child::first-letter {
    font-family: 'Playfair Display', serif;
    font-size: 4.5rem;
    font-weight: 700;
    float: left;
    line-height: 0.9;
    padding: .3rem .8rem 0 0;
    color: var(--crimson);
    font-style: italic;
}
.samb-isi strong { color: var(--rich-black); font-weight: 700; }
[data-theme="dark"] .samb-isi strong { color: #fff; }
.samb-isi em { color: var(--crimson); font-style: italic; }
.samb-isi blockquote {
    border-left: 4px solid var(--crimson);
    padding: 1rem 1.5rem;
    margin: 1.5rem 0;
    background: rgba(138,21,56,0.03);
    border-radius: 0 12px 12px 0;
    font-style: italic;
    font-size: 1.1rem;
    color: var(--rich-black);
}
[data-theme="dark"] .samb-isi blockquote { color: #fff; background: rgba(138,21,56,0.1); }

/* Highlights */
.samb-highlights {
    background: linear-gradient(135deg, rgba(138,21,56,0.05), rgba(135,206,235,0.05));
    border-left: 4px solid var(--crimson);
    border-radius: 0 16px 16px 0;
    padding: 1.5rem;
    margin-bottom: 2.5rem;
}
[data-theme="dark"] .samb-highlights { background: rgba(138,21,56,0.1); }
.samb-highlights-header {
    display: flex;
    align-items: center;
    gap: .5rem;
    margin-bottom: 1rem;
    font-weight: 800;
    color: var(--crimson);
    font-size: .88rem;
    letter-spacing: 1px;
    text-transform: uppercase;
}
.samb-highlights-header i { font-size: 1rem; }
.samb-highlights-list {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: .8rem;
}
.samb-highlight-item {
    display: flex;
    gap: .8rem;
    align-items: flex-start;
    font-size: .95rem;
    color: var(--rich-black);
    line-height: 1.6;
}
[data-theme="dark"] .samb-highlight-item { color: #e0e0e0; }
.samb-highlight-item i {
    color: var(--crimson);
    margin-top: 4px;
    flex-shrink: 0;
    font-size: .85rem;
}

/* ===== SIGNATURE SECTION ===== */
.samb-signature {
    padding: 2rem 0;
    margin: 2rem 0;
    border-top: 2px dashed rgba(138,21,56,0.2);
}
.samb-signature-content {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    gap: 2rem;
    flex-wrap: wrap;
}
.samb-signature-left { flex: 1; min-width: 200px; }
.signature-label {
    font-size: .9rem;
    color: var(--text-grey);
    font-style: italic;
    margin-bottom: 1rem;
}
.signature-ink-wrap {
    position: relative;
    margin-bottom: .5rem;
}
.signature-ink {
    font-family: 'Dancing Script', cursive;
    font-size: 2.8rem;
    color: var(--crimson);
    line-height: 1;
    margin: 0;
    opacity: 0;
    transform: translateX(-20px);
    transition: all 1.5s cubic-bezier(0.22, 0.61, 0.36, 1);
    font-weight: 700;
}
.signature-ink.visible {
    opacity: 1;
    transform: translateX(0);
}
.signature-ink-line {
    position: absolute;
    bottom: -5px;
    left: 0;
    width: 100%;
    height: 20px;
}
.ink-path {
    fill: none;
    stroke: var(--crimson);
    stroke-width: 2;
    stroke-linecap: round;
    stroke-dasharray: 400;
    stroke-dashoffset: 400;
    transition: stroke-dashoffset 2s ease .5s;
}
.signature-ink.visible ~ .signature-ink-line .ink-path {
    stroke-dashoffset: 0;
}
.signature-name-final {
    font-weight: 800;
    color: var(--rich-black);
    font-size: 1.05rem;
    margin-bottom: .2rem;
}
[data-theme="dark"] .signature-name-final { color: #fff; }
.signature-jab-final {
    color: var(--text-grey);
    font-size: .85rem;
}

/* Digital Stamp */
.samb-signature-right { flex-shrink: 0; }
.samb-stamp {
    width: 110px;
    height: 110px;
    border-radius: 50%;
    border: 3px double var(--crimson);
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    opacity: 0;
    transform: scale(0.5) rotate(-180deg);
    transition: all 1.2s cubic-bezier(0.22, 0.61, 0.36, 1) 1s;
}
.samb-stamp.visible {
    opacity: .7;
    transform: scale(1) rotate(-15deg);
}
.samb-stamp::before, .samb-stamp::after {
    content: '';
    position: absolute;
    inset: 6px;
    border: 1px solid var(--crimson);
    border-radius: 50%;
}
.samb-stamp-inner {
    text-align: center;
    color: var(--crimson);
    line-height: 1.1;
}
.stamp-text-top {
    display: block;
    font-size: .65rem;
    font-weight: 800;
    letter-spacing: 2px;
    text-transform: uppercase;
    margin-bottom: .2rem;
}
.stamp-icon {
    display: block;
    font-size: 1.4rem;
    margin: .2rem 0;
}
.stamp-text-bottom {
    display: block;
    font-size: .55rem;
    font-weight: 700;
    letter-spacing: 1px;
    text-transform: uppercase;
}

/* Actions */
.samb-actions {
    display: flex;
    gap: .8rem;
    flex-wrap: wrap;
    margin-top: 2rem;
    padding-top: 2rem;
    border-top: 1px solid rgba(0,0,0,0.08);
}
[data-theme="dark"] .samb-actions { border-top-color: rgba(255,255,255,0.08); }
.btn-action {
    padding: .75rem 1.5rem;
    border: 2px solid var(--crimson);
    background: transparent;
    color: var(--crimson);
    border-radius: 50px;
    font-weight: 700;
    cursor: pointer;
    transition: all .3s ease;
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    font-size: .88rem;
    font-family: inherit;
}
.btn-action:hover {
    background: var(--crimson);
    color: #fff;
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(138,21,56,.3);
}

/* ===== VIDEO SECTION ===== */
.samb-video-section {
    max-width: 1000px;
    margin: 5rem auto 0;
}
.samb-video-wrapper {
    position: relative;
}
.samb-video-frame {
    position: relative;
    border-radius: 24px;
    overflow: hidden;
    box-shadow: 0 30px 80px rgba(0,0,0,.2);
    aspect-ratio: 16/9;
    background: linear-gradient(135deg, var(--rich-black), #1a1a2e);
}
.samb-video-frame iframe {
    width: 100%;
    height: 100%;
    border: none;
    position: absolute;
    inset: 0;
    z-index: 2;
}
.samb-video-placeholder {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 1.5rem;
    cursor: pointer;
    transition: all .3s ease;
    background:
        radial-gradient(circle at center, rgba(138,21,56,0.3), rgba(10,10,10,0.9)),
        linear-gradient(135deg, #1a1a2e, var(--rich-black));
    z-index: 3;
}
.samb-video-placeholder:hover { background: radial-gradient(circle at center, rgba(138,21,56,0.5), rgba(10,10,10,0.95)); }
.video-play-btn {
    width: 90px; height: 90px;
    border-radius: 50%;
    background: var(--crimson);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    box-shadow: 0 10px 40px rgba(138,21,56,0.6);
    transition: all .4s ease;
    position: relative;
}
.video-play-btn::before {
    content: '';
    position: absolute;
    inset: -15px;
    border: 2px solid rgba(255,255,255,0.3);
    border-radius: 50%;
    animation: videoRipple 2s ease-out infinite;
}
@keyframes videoRipple {
    0% { transform: scale(1); opacity: 1; }
    100% { transform: scale(1.4); opacity: 0; }
}
.samb-video-placeholder:hover .video-play-btn {
    transform: scale(1.1);
    box-shadow: 0 15px 50px rgba(138,21,56,0.8);
}
.video-label {
    color: rgba(255,255,255,.85);
    font-weight: 700;
    font-size: .85rem;
    letter-spacing: 2px;
    text-transform: uppercase;
}

/* ===== CTA SECTION ===== */
.samb-cta-section {
    padding: 6rem 1.5rem;
    background: linear-gradient(180deg, #fff 0%, var(--bg-light) 100%);
    position: relative;
    overflow: hidden;
}
[data-theme="dark"] .samb-cta-section { background: linear-gradient(180deg, var(--rich-black), #0f0f1a); }
.samb-cta-bg { position: absolute; inset: 0; pointer-events: none; }
.samb-cta-orb {
    position: absolute;
    border-radius: 50%;
    filter: blur(80px);
    opacity: .3;
}
.samb-cta-orb-1 {
    width: 400px; height: 400px;
    background: var(--crimson);
    top: -150px; left: -100px;
    animation: orbFloat 25s ease-in-out infinite;
}
.samb-cta-orb-2 {
    width: 350px; height: 350px;
    background: var(--sky-blue);
    bottom: -150px; right: -100px;
    animation: orbFloat 30s ease-in-out infinite reverse;
}
.samb-cta-box {
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
.samb-cta-box::before {
    content: '';
    position: absolute;
    inset: 0;
    background:
        radial-gradient(circle at 20% 30%, rgba(255,255,255,0.1), transparent 50%),
        radial-gradient(circle at 80% 70%, rgba(135,206,235,0.15), transparent 50%);
    pointer-events: none;
}
.samb-cta-icon {
    width: 90px; height: 90px;
    margin: 0 auto 1.5rem;
    border-radius: 50%;
    background: rgba(255,255,255,0.1);
    border: 1px solid rgba(255,255,255,0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    backdrop-filter: blur(10px);
    position: relative;
    z-index: 2;
    animation: iconFloat 6s ease-in-out infinite;
}
@keyframes iconFloat {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
}
.samb-cta-box h2 {
    font-size: clamp(1.5rem, 4vw, 2.2rem);
    margin-bottom: 1rem;
    font-weight: 900;
    position: relative;
    z-index: 2;
}
.samb-cta-box p {
    color: rgba(255,255,255,.9);
    margin-bottom: 2rem;
    line-height: 1.8;
    max-width: 600px;
    margin-left: auto;
    margin-right: auto;
    position: relative;
    z-index: 2;
}
.samb-cta-buttons {
    display: flex;
    gap: 1rem;
    justify-content: center;
    flex-wrap: wrap;
    position: relative;
    z-index: 2;
}
.samb-cta-buttons .btn-primary {
    background: #fff;
    color: var(--crimson);
    margin-top: 0;
    box-shadow: 0 12px 35px rgba(0,0,0,.25);
}
.samb-cta-buttons .btn-secondary {
    border-color: #fff;
    color: #fff;
    margin-top: 0;
}
.samb-cta-buttons .btn-secondary:hover {
    background: #fff;
    color: var(--crimson);
}

/* ===== EMPTY STATE ===== */
.samb-empty {
    text-align: center;
    padding: 5rem 2rem;
    background: #fff;
    border-radius: 24px;
    box-shadow: 0 10px 40px rgba(0,0,0,.08);
    max-width: 700px;
    margin: 3rem auto;
}
[data-theme="dark"] .samb-empty { background: #1a1a2e; }
.samb-empty-icon {
    width: 120px; height: 120px;
    margin: 0 auto 1.5rem;
    background: linear-gradient(135deg, rgba(138,21,56,0.1), rgba(135,206,235,0.1));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 3.5rem;
    color: var(--crimson);
}
.samb-empty h2 {
    color: var(--rich-black);
    margin-bottom: .5rem;
    font-size: 1.5rem;
    font-weight: 800;
}
[data-theme="dark"] .samb-empty h2 { color: #fff; }
.samb-empty p { color: var(--text-grey); margin-bottom: 2rem; }

/* ===== SHARE MODAL ===== */
.samb-share-modal {
    position: fixed;
    inset: 0;
    z-index: 9999;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 1.5rem;
}
.samb-share-modal.active { display: flex; animation: shareModalIn .3s ease; }
@keyframes shareModalIn { from { opacity: 0; } to { opacity: 1; } }
.samb-share-backdrop {
    position: absolute;
    inset: 0;
    background: rgba(0,0,0,0.7);
    backdrop-filter: blur(10px);
}
.samb-share-content {
    position: relative;
    background: #fff;
    border-radius: 24px;
    max-width: 500px;
    width: 100%;
    padding: 2.5rem;
    box-shadow: 0 30px 80px rgba(0,0,0,.3);
    animation: shareContentIn .4s cubic-bezier(0.22, 0.61, 0.36, 1);
}
[data-theme="dark"] .samb-share-content { background: #1a1a2e; color: #fff; }
@keyframes shareContentIn {
    from { transform: translateY(30px) scale(.95); opacity: 0; }
    to { transform: none; opacity: 1; }
}
.samb-share-close {
    position: absolute;
    top: 1rem;
    right: 1rem;
    width: 36px; height: 36px;
    border-radius: 50%;
    background: rgba(0,0,0,0.05);
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    color: var(--rich-black);
    transition: all .3s ease;
}
[data-theme="dark"] .samb-share-close { background: rgba(255,255,255,0.1); color: #fff; }
.samb-share-close:hover {
    background: var(--crimson);
    color: #fff;
    transform: rotate(90deg);
}
.samb-share-content h3 {
    font-size: 1.3rem;
    margin-bottom: 1.5rem;
    color: var(--rich-black);
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: .6rem;
}
[data-theme="dark"] .samb-share-content h3 { color: #fff; }
.samb-share-content h3 i { color: var(--crimson); }

.samb-share-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: .8rem;
    margin-bottom: 1.5rem;
}
.samb-share-btn {
    padding: 1rem .8rem;
    border: 1px solid rgba(0,0,0,0.08);
    background: #fff;
    border-radius: 12px;
    cursor: pointer;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: .4rem;
    transition: all .3s ease;
    font-family: inherit;
    color: var(--rich-black);
}
[data-theme="dark"] .samb-share-btn {
    background: rgba(255,255,255,0.05);
    color: #fff;
    border-color: rgba(255,255,255,0.08);
}
.samb-share-btn i { font-size: 1.4rem; }
.samb-share-btn span { font-size: .78rem; font-weight: 600; }
.samb-share-btn:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 25px rgba(0,0,0,.1);
    color: #fff;
}
.share-twitter:hover { background: #000; border-color: #000; }
.share-facebook:hover { background: #1877f2; border-color: #1877f2; }
.share-whatsapp:hover { background: #25D366; border-color: #25D366; }
.share-linkedin:hover { background: #0a66c2; border-color: #0a66c2; }
.share-email:hover { background: var(--crimson); border-color: var(--crimson); }
.share-copy:hover { background: var(--sky-blue-dark); border-color: var(--sky-blue-dark); }

.samb-share-qr {
    text-align: center;
    padding-top: 1.5rem;
    border-top: 1px solid rgba(0,0,0,0.08);
}
[data-theme="dark"] .samb-share-qr { border-top-color: rgba(255,255,255,0.08); }
.qr-label {
    font-size: .85rem;
    color: var(--text-grey);
    margin-bottom: 1rem;
}
.qr-code-wrap {
    display: inline-block;
    padding: 1rem;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,.08);
}
.qr-code-wrap img {
    width: 150px;
    height: 150px;
    display: block;
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
    transition: width 0.1s ease-out;
}

/* ===== REVEAL ANIMATIONS ===== */
.reveal-up {
    opacity: 0;
    transform: translateY(40px);
    transition: opacity 0.8s cubic-bezier(0.22, 0.61, 0.36, 1), transform 0.8s cubic-bezier(0.22, 0.61, 0.36, 1);
}
.reveal-up.visible { opacity: 1; transform: translateY(0); }
.reveal-left {
    opacity: 0;
    transform: translateX(-40px);
    transition: opacity 0.8s cubic-bezier(0.22, 0.61, 0.36, 1), transform 0.8s cubic-bezier(0.22, 0.61, 0.36, 1);
}
.reveal-left.visible { opacity: 1; transform: translateX(0); }
.reveal-right {
    opacity: 0;
    transform: translateX(40px);
    transition: opacity 0.8s cubic-bezier(0.22, 0.61, 0.36, 1), transform 0.8s cubic-bezier(0.22, 0.61, 0.36, 1);
}
.reveal-right.visible { opacity: 1; transform: translateX(0); }
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

/* ===== PRINT STYLES ===== */
@media print {
    .page-hero, .samb-cta-section, .samb-share-modal,
    .reading-progress, .samb-scroll-hint, .samb-actions,
    .samb-video-section, .samb-socials, .skip-link {
        display: none !important;
    }
    .sambutan-section { padding: 1rem 0; }
    .samb-container {
        box-shadow: none;
        background: transparent;
    }
    .samb-photo-col { position: static; }
    .samb-photo-frame {
        box-shadow: none;
        border: 1px solid #ccc;
    }
    .samb-signature, .samb-stamp {
        color: #000 !important;
    }
    .signature-ink { color: #000; opacity: 1; transform: none; }
    .samb-stamp { border-color: #000; opacity: 1; transform: none; }
    .samb-content-col { box-shadow: none; border: 1px solid #ccc; }
}

/* ===== RESPONSIVE ===== */
@media (max-width: 1024px) {
    .samb-container { grid-template-columns: 1fr; gap: 2.5rem; }
    .samb-photo-col { position: static; max-width: 450px; margin: 0 auto; width: 100%; }
}
@media (max-width: 768px) {
    .samb-hero { padding: 9rem 1rem 4rem; }
    .samb-content-col { padding: 2rem 1.5rem; }
    .samb-name { font-size: 1.8rem; }
    .samb-isi.drop-cap p:first-child::first-letter { font-size: 3.5rem; }
    .samb-signature-content { flex-direction: column; align-items: center; text-align: center; }
    .samb-stamp { width: 100px; height: 100px; }
    .samb-meta { flex-direction: column; gap: .5rem; }
    .samb-meta-divider { display: none; }
    .samb-share-grid { grid-template-columns: repeat(3, 1fr); }
    .samb-share-content { padding: 2rem 1.5rem; }
    .samb-cta-box { padding: 2.5rem 1.5rem; }
    .samb-actions { flex-direction: column; }
    .btn-action { justify-content: center; }
}
@media (max-width: 480px) {
    .samb-share-grid { grid-template-columns: repeat(2, 1fr); }
    .samb-quote-icon { font-size: 4rem; top: 0; }
    .signature-ink { font-size: 2rem; }
}

/* ===== REDUCED MOTION ===== */
@media (prefers-reduced-motion: reduce) {
    *, *::before, *::after {
        animation-duration: 0.01ms !important;
        transition-duration: 0.01ms !important;
    }
    .reveal-up, .reveal-left, .reveal-right, .text-reveal > *, .fade-in-up {
        opacity: 1; transform: none;
    }
    .signature-ink { opacity: 1; transform: none; }
    .samb-stamp { opacity: .7; transform: scale(1) rotate(-15deg); }
}
</style>

<!-- ============================================================
     PREMIUM JAVASCRIPT — SAMBUTAN KAPRODI
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

    // ===== TEXT REVEAL =====
    document.querySelectorAll('.text-reveal').forEach(el => {
        if (el.children.length > 0) return;
        const text = el.textContent.trim();
        const words = text.split(' ');
        el.innerHTML = words.map(w => `<span>${w}</span>`).join(' ');
    });

    // ===== INTERSECTION OBSERVER =====
    const revealObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                revealObserver.unobserve(entry.target);
            }
        });
    }, { threshold: 0.15, rootMargin: '0px 0px -80px 0px' });

    document.querySelectorAll('.reveal-up, .reveal-left, .reveal-right, .text-reveal, .fade-in-up').forEach(el => {
        revealObserver.observe(el);
    });

    // ===== SIGNATURE ANIMATION =====
    const signatureInk = document.querySelector('.signature-ink');
    const signatureStamp = document.querySelector('.samb-stamp');
    if (signatureInk) {
        const sigObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    setTimeout(() => signatureInk.classList.add('visible'), 300);
                    setTimeout(() => signatureStamp?.classList.add('visible'), 800);
                    sigObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.3 });
        sigObserver.observe(signatureInk);
    }

    // ===== PARALLAX PHOTO =====
    const photoFrame = document.querySelector('.samb-photo-frame');
    if (photoFrame && window.matchMedia('(hover: hover)').matches) {
        const speed = parseFloat(photoFrame.dataset.parallax) || 0.15;
        let parallaxTicking = false;
        window.addEventListener('scroll', () => {
            if (!parallaxTicking) {
                requestAnimationFrame(() => {
                    const rect = photoFrame.getBoundingClientRect();
                    if (rect.top < window.innerHeight && rect.bottom > 0) {
                        const scrolled = window.innerHeight - rect.top;
                        photoFrame.style.transform = `translateY(${scrolled * speed * -0.08}px)`;
                    }
                    parallaxTicking = false;
                });
                parallaxTicking = true;
            }
        }, { passive: true });
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

    // ===== VIDEO PLACEHOLDER (Lazy Load) =====
    document.addEventListener('click', function (e) {
        const trigger = e.target.closest('.samb-video-placeholder, .video-play-btn');
        if (trigger) {
            const placeholder = trigger.closest('.samb-video-placeholder');
            const frame = placeholder.closest('.samb-video-frame');
            if (!frame || frame.querySelector('iframe')) return;

            const videoUrl = placeholder.dataset.video;
            if (!videoUrl) return;

            let finalUrl = videoUrl;
            if (finalUrl.indexOf('?') === -1) {
                finalUrl += '?autoplay=1&mute=1&playsinline=1';
            } else {
                finalUrl += '&autoplay=1&mute=1&playsinline=1';
            }

            const iframe = document.createElement('iframe');
            iframe.src = finalUrl;
            iframe.allow = 'autoplay; encrypted-media; picture-in-picture; fullscreen';
            iframe.allowFullscreen = true;
            iframe.title = 'Video Sambutan';
            frame.appendChild(iframe);
            placeholder.style.display = 'none';
        }
    });

    // Video keyboard support
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' && e.key !== ' ') return;
        const placeholder = e.target.closest('.samb-video-placeholder');
        if (placeholder) {
            e.preventDefault();
            placeholder.click();
        }
    });

    // ===== INITIAL REVEAL =====
    window.addEventListener('load', () => {
        setTimeout(() => {
            document.querySelectorAll('.samb-hero .text-reveal, .samb-hero .reveal-up, .samb-hero .fade-in-up').forEach(el => {
                el.classList.add('visible');
            });
        }, 600);
    });
})();

// ===== SHARE MODAL =====
function openShareModal() {
    const modal = document.getElementById('sambShareModal');
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';

    // Generate QR Code
    const qrImg = document.getElementById('qrCodeImg');
    if (qrImg) {
        const url = encodeURIComponent(window.location.href);
        qrImg.src = `https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=${url}&color=8A1538`;
    }
}

function closeShareModal() {
    const modal = document.getElementById('sambShareModal');
    modal.classList.remove('active');
    document.body.style.overflow = '';
}

function shareTo(platform) {
    const url = encodeURIComponent(window.location.href);
    const title = encodeURIComponent(document.title);
    const text = encodeURIComponent(document.querySelector('.samb-name')?.textContent + ' - Sambutan Kaprodi PBSI UMMaumere');

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
        case 'linkedin':
            shareUrl = `https://www.linkedin.com/sharing/share-offsite/?url=${url}`;
            break;
        case 'email':
            shareUrl = `mailto:?subject=${title}&body=${text}%20${url}`;
            window.location.href = shareUrl;
            return;
        case 'copy':
            navigator.clipboard.writeText(window.location.href).then(() => {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'success',
                        title: 'Link berhasil disalin!',
                        showConfirmButton: false,
                        timer: 2000,
                        timerProgressBar: true
                    });
                } else {
                    alert('Link berhasil disalin!');
                }
                closeShareModal();
            });
            return;
    }
    if (shareUrl) {
        window.open(shareUrl, '_blank', 'width=600,height=500');
    }
}

function downloadAsPDF() {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            icon: 'info',
            title: 'Unduh sebagai PDF',
            html: 'Gunakan <strong>Ctrl+P</strong> (atau <strong>Cmd+P</strong> di Mac) lalu pilih <strong>"Save as PDF"</strong> sebagai tujuan pencetakan.',
            confirmButtonColor: '#8A1538',
            confirmButtonText: 'Mengerti'
        });
    } else {
        window.print();
    }
}

// Close modal on ESC / backdrop click
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeShareModal();
});
</script>

<!-- Multi-language Support -->
<script>
(function () {
    'use strict';
    const translations = {
        en: {
            'leadership_message': 'LEADERSHIP MESSAGE',
            'page_title': 'Head of Study Program Message',
            'page_subtitle': 'A warm message from the head of the English Language and Literature Study Program.',
            'home': 'Home',
            'sambutan_breadcrumb': 'Kaprodi Message',
            'scroll': 'SCROLL',
            'kaprodi_message': 'KAPRODI MESSAGE',
            'sambutan_heading': 'Head of Study Program Message',
            'sambutan_subtitle': 'Vision, mission, and hopes from the head of the English Language and Literature Study Program.',
            'min_read': 'min read',
            'words': 'words',
            'nidn_label': 'NIDN',
            'position_label': 'Position',
            'key_points': 'Key Points',
            'regards': 'Best regards,',
            'print': 'Print',
            'share': 'Share',
            'download_pdf': 'Download PDF',
            'video_message': 'VIDEO MESSAGE',
            'video_heading': 'Watch Live',
            'click_play': 'Click to Play',
            'cta_heading': 'Let\'s Collaborate',
            'cta_desc': 'We are open to academic cooperation, research, and study program development.',
            'contact_us': 'Contact Us',
            'view_vision': 'View Vision & Mission',
            'empty_title': 'Message Not Yet Available',
            'empty_desc': 'The Head of Study Program message is currently being drafted. Please visit again later.',
            'share_heading': 'Share Message',
            'copy_link': 'Copy Link',
            'scan_qr': 'Or scan QR Code:'
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