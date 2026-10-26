<?php
require_once '../includes/config.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';
check_ip_security($pdo);

// ===== HELPER GAMBAR =====
if (!function_exists('img_url')) {
    function img_placeholder() {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="600">'
             . '<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1">'
             . '<stop offset="0" stop-color="#8A1538"/><stop offset="1" stop-color="#1a1a2e"/></linearGradient></defs>'
             . '<rect width="1200" height="600" fill="url(#g)"/>'
             . '<circle cx="600" cy="300" r="80" fill="rgba(255,255,255,.10)"/>'
             . '<text x="600" y="450" font-family="Arial" font-size="28" letter-spacing="10" fill="rgba(255,255,255,.4)" text-anchor="middle">PBSI UMMaumere</text>'
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

$slug = $_GET['slug'] ?? '';
$stmt = $pdo->prepare("SELECT * FROM berita WHERE slug = ? AND status = 1");
$stmt->execute([$slug]);
$b = $stmt->fetch();

if ($b) { 
    $pdo->prepare("UPDATE berita SET views = views + 1 WHERE id = ?")->execute([$b['id']]); 
    $b['views'] = (int)$b['views'] + 1;
}

$page_title = $b ? $b['judul'] : 'Berita Tidak Ditemukan';
require_once '../includes/public_header.php';

$setting = get_all('settings', 'id = 1')[0];

// Related news (same category, excluding current)
$related = [];
if ($b) {
    $stmt = $pdo->prepare("SELECT * FROM berita WHERE kategori = ? AND id != ? AND status = 1 ORDER BY tanggal DESC LIMIT 3");
    $stmt->execute([$b['kategori'], $b['id']]);
    $related = $stmt->fetchAll();
}

// Reading time calculation
$wordCount = str_word_count(strip_tags($b['isi'] ?? ''));
$readingTime = max(1, ceil($wordCount / 200));

// Estimated publish date
$publishedDate = $b ? date('Y-m-d', strtotime($b['tanggal'])) : '';
$publishedDisplay = $b ? date('d F Y', strtotime($b['tanggal'])) : '';
?>

<!-- Reading Progress Bar -->
<div class="reading-progress" id="readingProgress" aria-hidden="true">
    <div class="reading-progress-bar"></div>
</div>

<?php if (!$b): ?>
<!-- 404 NOT FOUND -->
<main id="main-content" role="main">
    <section class="error-404">
        <div class="error-content">
            <div class="error-code" data-aos="zoom-in">404</div>
            <h1 data-aos="fade-up" data-aos-delay="100">Berita Tidak Ditemukan</h1>
            <p data-aos="fade-up" data-aos-delay="200">Maaf, berita yang Anda cari tidak tersedia atau telah dihapus.</p>
            <div class="error-actions" data-aos="fade-up" data-aos-delay="300">
                <a href="<?= PUBLIC_URL ?>berita.php" class="btn-primary">
                    <i class="fa-solid fa-newspaper" aria-hidden="true"></i> 
                    Lihat Semua Berita
                </a>
                <a href="<?= PUBLIC_URL ?>index.php" class="btn-secondary">
                    <i class="fa-solid fa-home" aria-hidden="true"></i> 
                    Kembali ke Beranda
                </a>
            </div>
        </div>
    </section>
</main>


<style>
.error-404 {
    min-height: 80vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 5rem 1.5rem;
    background: linear-gradient(135deg, var(--bg-light), #fff);
}
.error-content {
    text-align: center;
    max-width: 600px;
}
.error-code {
    font-size: clamp(6rem, 20vw, 12rem);
    font-weight: 900;
    background: linear-gradient(135deg, var(--crimson), var(--crimson-light));
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
    line-height: 1;
    margin-bottom: 1rem;
}
.error-content h1 {
    font-size: clamp(1.8rem, 5vw, 2.5rem);
    margin-bottom: 1rem;
    color: var(--rich-black);
}
.error-content p {
    color: var(--text-grey);
    margin-bottom: 2rem;
    font-size: 1.1rem;
    line-height: 1.7;
}
.error-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
    flex-wrap: wrap;
}
</style>

<?php else: ?>

<!-- JSON-LD NewsArticle -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "NewsArticle",
  "headline": "<?= addslashes($b['judul']) ?>",
  "image": ["<?= img_url($b['gambar']) ?>"],
  "datePublished": "<?= $publishedDate ?>",
  "dateModified": "<?= date('Y-m-d', strtotime($b['tanggal'])) ?>",
  "author": {
    "@type": "Person",
    "name": "<?= addslashes($b['penulis'] ?? 'Redaksi PBSI') ?>"
  },
  "publisher": {
    "@type": "EducationalOrganization",
    "name": "<?= addslashes($setting['site_name'] ?? 'PBSI UMMaumere') ?>",
    "logo": {
      "@type": "ImageObject",
      "url": "<?= PUBLIC_URL ?>assets/images/logo.png"
    }
  },
  "description": "<?= addslashes(substr(strip_tags($b['isi']), 0, 160)) ?>"
}
</script>

<main id="main-content" role="main">

<!-- BREADCRUMB -->
<nav class="detail-breadcrumb" aria-label="Breadcrumb">
    <div class="container-detail">
        <a href="<?= PUBLIC_URL ?>index.php"><i class="fa-solid fa-home" aria-hidden="true"></i> Beranda</a>
        <span aria-hidden="true"><i class="fa-solid fa-chevron-right"></i></span>
        <a href="<?= PUBLIC_URL ?>berita.php">Berita</a>
        <span aria-hidden="true"><i class="fa-solid fa-chevron-right"></i></span>
        <span aria-current="page"><?= sanitize($b['kategori']) ?></span>
    </div>
</nav>

<!-- ARTICLE -->
<article class="detail-article" itemscope itemtype="https://schema.org/NewsArticle">
    <!-- Hero Image -->
    <div class="detail-hero">
        <div class="detail-hero-img" 
             style="background-image:url('<?= img_url($b['gambar']) ?>')"
             role="img"
             aria-label="<?= sanitize($b['judul']) ?>">
            <div class="detail-hero-overlay"></div>
            <div class="detail-hero-content">
                <span class="detail-cat">
                    <i class="fa-solid fa-tag" aria-hidden="true"></i> 
                    <?= sanitize($b['kategori']) ?>
                </span>
                <h1 class="detail-title" itemprop="headline"><?= sanitize($b['judul']) ?></h1>
                <div class="detail-meta-hero">
                    <span class="meta-item">
                        <i class="fa-regular fa-user" aria-hidden="true"></i>
                        <span itemprop="author"><?= sanitize($b['penulis'] ?? 'Redaksi PBSI') ?></span>
                    </span>
                    <span class="meta-item">
                        <i class="fa-regular fa-calendar" aria-hidden="true"></i>
                        <time datetime="<?= $publishedDate ?>" itemprop="datePublished">
                            <?= $publishedDisplay ?>
                        </time>
                    </span>
                    <span class="meta-item">
                        <i class="fa-regular fa-clock" aria-hidden="true"></i>
                        <?= $readingTime ?> menit baca
                    </span>
                    <span class="meta-item">
                        <i class="fa-regular fa-eye" aria-hidden="true"></i>
                        <?= number_format((int)$b['views']) ?> dilihat
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Article Body -->
    <div class="detail-body-wrapper">
        <div class="detail-layout">
            <!-- Main Content -->
            <div class="detail-main">
                <!-- Share Bar (Sticky) -->
                <aside class="share-bar" aria-label="Bagikan artikel">
                    <div class="share-label">Bagikan</div>
                    <button class="share-btn share-fb" 
                            onclick="shareToFacebook()" 
                            aria-label="Bagikan ke Facebook"
                            title="Facebook">
                        <i class="fa-brands fa-facebook-f" aria-hidden="true"></i>
                    </button>
                    <button class="share-btn share-tw" 
                            onclick="shareToTwitter()" 
                            aria-label="Bagikan ke Twitter"
                            title="Twitter">
                        <i class="fa-brands fa-x-twitter" aria-hidden="true"></i>
                    </button>
                    <button class="share-btn share-wa" 
                            onclick="shareToWhatsApp()" 
                            aria-label="Bagikan ke WhatsApp"
                            title="WhatsApp">
                        <i class="fa-brands fa-whatsapp" aria-hidden="true"></i>
                    </button>
                    <button class="share-btn share-link" 
                            onclick="copyLink(this)" 
                            aria-label="Salin tautan"
                            title="Salin Tautan">
                        <i class="fa-solid fa-link" aria-hidden="true"></i>
                    </button>
                    <button class="share-btn share-print" 
                            onclick="window.print()" 
                            aria-label="Cetak artikel"
                            title="Cetak">
                        <i class="fa-solid fa-print" aria-hidden="true"></i>
                    </button>
                </div>

                <!-- Article Content -->
                <div class="detail-content" itemprop="articleBody">
                    <?= $b['isi'] ?>
                </div>

                <!-- Tags -->
                <?php if (!empty($b['tags'])): ?>
                <div class="detail-tags">
                    <span class="tags-label"><i class="fa-solid fa-tags" aria-hidden="true"></i> Tag:</span>
                    <?php 
                    $tags = explode(',', $b['tags']);
                    foreach ($tags as $tag): 
                        $tag = trim($tag);
                        if ($tag):
                    ?>
                    <a href="<?= PUBLIC_URL ?>berita.php?tag=<?= urlencode($tag) ?>" class="tag-item">
                        #<?= sanitize($tag) ?>
                    </a>
                    <?php endif; endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Author Card -->
                <div class="author-card" data-aos="fade-up">
                    <div class="author-avatar">
                        <i class="fa-solid fa-user-pen" aria-hidden="true"></i>
                    </div>
                    <div class="author-info">
                        <span class="author-label">Ditulis oleh</span>
                        <h4><?= sanitize($b['penulis'] ?? 'Redaksi PBSI') ?></h4>
                        <p>Tim redaksi Program Studi Bahasa dan Sastra Inggris UMMaumere yang berkomitmen menyajikan informasi akurat dan terpercaya.</p>
                    </div>
                </div>

                <!-- Article Navigation -->
                <div class="article-nav">
                    <?php
                    // Previous article
                    $stmt = $pdo->prepare("SELECT * FROM berita WHERE tanggal < ? AND status = 1 ORDER BY tanggal DESC LIMIT 1");
                    $stmt->execute([$b['tanggal']]);
                    $prev = $stmt->fetch();
                    
                    // Next article
                    $stmt = $pdo->prepare("SELECT * FROM berita WHERE tanggal > ? AND status = 1 ORDER BY tanggal ASC LIMIT 1");
                    $stmt->execute([$b['tanggal']]);
                    $next = $stmt->fetch();
                    ?>
                    
                    <?php if ($prev): ?>
                    <a href="<?= PUBLIC_URL ?>berita_detail.php?slug=<?= sanitize($prev['slug']) ?>" class="article-nav-item nav-prev">
                        <span class="nav-label"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Sebelumnya</span>
                        <span class="nav-title"><?= sanitize($prev['judul']) ?></span>
                    </a>
                    <?php else: ?>
                    <div></div>
                    <?php endif; ?>
                    
                    <?php if ($next): ?>
                    <a href="<?= PUBLIC_URL ?>berita_detail.php?slug=<?= sanitize($next['slug']) ?>" class="article-nav-item nav-next">
                        <span class="nav-label">Berikutnya <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></span>
                        <span class="nav-title"><?= sanitize($next['judul']) ?></span>
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sidebar -->
            <aside class="detail-sidebar">
                <!-- Quick Info -->
                <div class="sidebar-card">
                    <h3><i class="fa-solid fa-circle-info" aria-hidden="true"></i> Informasi</h3>
                    <ul class="info-list">
                        <li>
                            <span class="info-label">Kategori</span>
                            <span class="info-value"><?= sanitize($b['kategori']) ?></span>
                        </li>
                        <li>
                            <span class="info-label">Penulis</span>
                            <span class="info-value"><?= sanitize($b['penulis'] ?? 'Redaksi') ?></span>
                        </li>
                        <li>
                            <span class="info-label">Diterbitkan</span>
                            <span class="info-value"><?= $publishedDisplay ?></span>
                        </li>
                        <li>
                            <span class="info-label">Waktu Baca</span>
                            <span class="info-value"><?= $readingTime ?> menit</span>
                        </li>
                        <li>
                            <span class="info-label">Jumlah Kata</span>
                            <span class="info-value"><?= number_format($wordCount) ?> kata</span>
                        </li>
                        <li>
                            <span class="info-label">Dilihat</span>
                            <span class="info-value"><?= number_format((int)$b['views']) ?> kali</span>
                        </li>
                    </ul>
                </div>

                <!-- Table of Contents (Auto-generated) -->
                <div class="sidebar-card toc-card">
                    <h3><i class="fa-solid fa-list" aria-hidden="true"></i> Daftar Isi</h3>
                    <nav class="toc" id="tocNav" aria-label="Daftar isi">
                        <p class="toc-empty">Tidak ada heading dalam artikel ini.</p>
                    </nav>
                </div>

                <!-- Share CTA -->
                <div class="sidebar-card share-card">
                    <i class="fa-solid fa-share-nodes" aria-hidden="true"></i>
                    <h3>Suka Artikel Ini?</h3>
                    <p>Bagikan ke teman dan kolega Anda.</p>
                    <div class="share-grid">
                        <button class="share-grid-btn" onclick="shareToFacebook()" aria-label="Facebook">
                            <i class="fa-brands fa-facebook-f" aria-hidden="true"></i>
                        </button>
                        <button class="share-grid-btn" onclick="shareToTwitter()" aria-label="Twitter">
                            <i class="fa-brands fa-x-twitter" aria-hidden="true"></i>
                        </button>
                        <button class="share-grid-btn" onclick="shareToWhatsApp()" aria-label="WhatsApp">
                            <i class="fa-brands fa-whatsapp" aria-hidden="true"></i>
                        </button>
                        <button class="share-grid-btn" onclick="copyLink(this)" aria-label="Copy Link">
                            <i class="fa-solid fa-link" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</article>

<!-- RELATED NEWS -->
<?php if (!empty($related)): ?>
<section class="related-section" aria-labelledby="related-heading">
    <div class="section">
        <header class="sec-head">
            <span class="sec-eyebrow ey-crimson">
                <i class="fa-solid fa-newspaper" aria-hidden="true"></i> 
                BACA JUGA
            </span>
            <h2 class="section-title" id="related-heading">Berita Terkait</h2>
            <p class="section-subtitle">Artikel lain dalam kategori yang sama.</p>
        </header>
        <div class="related-grid">
            <?php foreach ($related as $r): ?>
            <article class="related-card" data-aos="fade-up">
                <a href="<?= PUBLIC_URL ?>berita_detail.php?slug=<?= sanitize($r['slug']) ?>">
                    <div class="related-img" 
                         style="background-image:url('<?= img_url($r['gambar']) ?>')"
                         role="img"
                         aria-label="<?= sanitize($r['judul']) ?>"></div>
                    <div class="related-body">
                        <span class="news-cat"><?= sanitize($r['kategori']) ?></span>
                        <h3><?= sanitize($r['judul']) ?></h3>
                        <span class="related-date">
                            <i class="fa-regular fa-calendar" aria-hidden="true"></i>
                            <?= date('d M Y', strtotime($r['tanggal'])) ?>
                        </span>
                    </div>
                </a>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- BACK TO NEWS CTA -->
<section class="back-cta">
    <div class="back-cta-box">
        <a href="<?= PUBLIC_URL ?>berita.php" class="btn-back-news">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
            Kembali ke Semua Berita
        </a>
    </div>
</section>

</main>

<!-- Inline Styles for Berita Detail -->
<style>

/* ============================================================
   DARK MODE — BERITA DETAIL
   Responsif terhadap atribut data-theme="dark" di <html>
============================================================ */

/* === Body & Wrapper Utama === */
html[data-theme="dark"] .detail-body-wrapper { background: #0f0f1a; }
html[data-theme="dark"] .back-cta { background: #0a0a14; }
html[data-theme="dark"] .related-section { background: #0a0a14; }

/* === Breadcrumb === */
html[data-theme="dark"] .detail-breadcrumb {
    background: #1a1a2e;
    border-bottom-color: #2a2a3e;
}
html[data-theme="dark"] .detail-breadcrumb a { color: rgba(255,255,255,0.7); }
html[data-theme="dark"] .detail-breadcrumb a:hover { color: var(--sky-blue); }
html[data-theme="dark"] .detail-breadcrumb span { color: rgba(255,255,255,0.4); }
html[data-theme="dark"] .detail-breadcrumb span[aria-current="page"] { color: var(--crimson); }

/* === Share Bar (Sticky Kiri) === */
html[data-theme="dark"] .share-bar {
    background: #1a1a2e;
    border-color: rgba(255,255,255,0.08);
    box-shadow: 0 10px 30px rgba(0,0,0,0.5);
}
html[data-theme="dark"] .share-label { color: rgba(255,255,255,0.6); }
html[data-theme="dark"] .share-btn {
    background: rgba(255,255,255,0.06);
    color: rgba(255,255,255,0.8);
    border: 1px solid rgba(255,255,255,0.08);
}
html[data-theme="dark"] .share-btn:hover { color: #fff; }

/* === Isi Artikel (Typography) === */
html[data-theme="dark"] .detail-content { color: rgba(255,255,255,0.88); }
html[data-theme="dark"] .detail-content h2,
html[data-theme="dark"] .detail-content h3,
html[data-theme="dark"] .detail-content h4,
html[data-theme="dark"] .detail-content strong { color: #fff; }
html[data-theme="dark"] .detail-content a { color: var(--sky-blue); }
html[data-theme="dark"] .detail-content a:hover { color: #fff; }
html[data-theme="dark"] .detail-content blockquote {
    background: rgba(138,21,56,0.08);
    border-left-color: var(--crimson);
    color: rgba(255,255,255,0.85);
}
html[data-theme="dark"] .detail-content img {
    box-shadow: 0 10px 30px rgba(0,0,0,0.6);
    filter: brightness(0.92);
}
html[data-theme="dark"] .detail-content pre,
html[data-theme="dark"] .detail-content code {
    background: #0a0a14;
    color: var(--sky-blue);
    border: 1px solid rgba(255,255,255,0.1);
}
html[data-theme="dark"] .detail-content pre { background: #050510; }
html[data-theme="dark"] .detail-content ul li,
html[data-theme="dark"] .detail-content ol li { color: rgba(255,255,255,0.85); }

/* === Tags === */
html[data-theme="dark"] .detail-tags { border-top-color: rgba(255,255,255,0.08); }
html[data-theme="dark"] .tags-label { color: rgba(255,255,255,0.7); }
html[data-theme="dark"] .tag-item {
    background: rgba(255,255,255,0.06);
    color: rgba(255,255,255,0.85);
    border-color: rgba(255,255,255,0.1);
}
html[data-theme="dark"] .tag-item:hover {
    background: var(--crimson);
    color: #fff;
    border-color: var(--crimson);
}

/* === Author Card === */
html[data-theme="dark"] .author-card {
    background: linear-gradient(135deg, #1a1a2e, #141420);
    border-color: rgba(255,255,255,0.08);
}
html[data-theme="dark"] .author-label { color: rgba(255,255,255,0.6); }
html[data-theme="dark"] .author-info h4 { color: #fff; }
html[data-theme="dark"] .author-info p { color: rgba(255,255,255,0.7); }

/* === Article Navigation (Prev/Next) === */
html[data-theme="dark"] .article-nav-item {
    background: #1a1a2e;
    border-color: rgba(255,255,255,0.08);
}
html[data-theme="dark"] .article-nav-item:hover {
    border-color: var(--crimson);
    background: #22223a;
}
html[data-theme="dark"] .nav-label { color: var(--sky-blue); }
html[data-theme="dark"] .nav-title { color: #fff; }

/* === Sidebar Cards === */
html[data-theme="dark"] .sidebar-card {
    background: #1a1a2e;
    border-color: rgba(255,255,255,0.08);
    box-shadow: 0 10px 30px rgba(0,0,0,0.4);
}
html[data-theme="dark"] .sidebar-card h3 {
    color: #fff;
    border-bottom-color: rgba(255,255,255,0.1);
}
html[data-theme="dark"] .info-list li { border-bottom-color: rgba(255,255,255,0.08); }
html[data-theme="dark"] .info-label { color: rgba(255,255,255,0.6); }
html[data-theme="dark"] .info-value { color: #fff; }

/* === Table of Contents === */
html[data-theme="dark"] .toc a { color: rgba(255,255,255,0.7); }
html[data-theme="dark"] .toc a:hover {
    background: rgba(255,255,255,0.05);
    color: var(--crimson);
}
html[data-theme="dark"] .toc a.active {
    background: rgba(138,21,56,0.15);
    color: var(--crimson);
}
html[data-theme="dark"] .toc-empty { color: rgba(255,255,255,0.4); }

/* === Related News Cards === */
html[data-theme="dark"] .related-card {
    background: #1a1a2e;
    box-shadow: 0 10px 35px rgba(0,0,0,0.4);
}
html[data-theme="dark"] .related-card:hover {
    box-shadow: 0 20px 50px rgba(138,21,56,0.25);
    border-color: var(--crimson);
}
html[data-theme="dark"] .related-body h3 { color: #fff; }
html[data-theme="dark"] .related-date { color: rgba(255,255,255,0.5); }
html[data-theme="dark"] .news-cat {
    color: var(--crimson);
    font-size: .72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .5px;
}

/* === Section Headings === */
html[data-theme="dark"] .section-title { color: #fff; }
html[data-theme="dark"] .section-subtitle { color: rgba(255,255,255,0.7); }

/* === Tombol Back to News === */
html[data-theme="dark"] .btn-back-news {
    background: #1a1a2e;
    border: 1px solid rgba(255,255,255,0.1);
}
html[data-theme="dark"] .btn-back-news:hover {
    background: var(--crimson);
    border-color: var(--crimson);
}

/* === Halaman 404 === */
html[data-theme="dark"] .error-404 {
    background: linear-gradient(135deg, #0f0f1a, #141420);
}
html[data-theme="dark"] .error-content h1 { color: #fff; }
html[data-theme="dark"] .error-content p { color: rgba(255,255,255,0.7); }

/* === Perbaikan Skip Link (agar tidak menonjol di pojok kiri atas) === */
.skip-link {
    position: absolute;
    left: -9999px;
    top: auto;
    width: 1px;
    height: 1px;
    overflow: hidden;
}
.skip-link:focus {
    position: fixed;
    top: 10px;
    left: 10px;
    width: auto;
    height: auto;
    padding: .8rem 1.2rem;
    background: var(--crimson);
    color: #fff;
    border-radius: 8px;
    font-weight: 700;
    z-index: 9999;
    box-shadow: 0 5px 20px rgba(0,0,0,0.3);
}
/* Reading Progress */
.reading-progress {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 3px;
    background: transparent;
    z-index: 9998;
}
.reading-progress-bar {
    height: 100%;
    width: 0;
    background: linear-gradient(90deg, var(--crimson), var(--sky-blue), var(--emerald));
    box-shadow: 0 0 10px rgba(138,21,56,.5);
    transition: width .1s linear;
}

/* Breadcrumb */
.detail-breadcrumb {
    background: var(--bg-light);
    padding: 1rem 0;
    border-bottom: 1px solid #e0e0e0;
    margin-top: 70px;
}
.container-detail {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 2rem;
    display: flex;
    align-items: center;
    gap: .6rem;
    font-size: .88rem;
    flex-wrap: wrap;
}
.detail-breadcrumb a {
    color: var(--text-grey);
    text-decoration: none;
    transition: color .3s ease;
    display: inline-flex;
    align-items: center;
    gap: .4rem;
}
.detail-breadcrumb a:hover {
    color: var(--crimson);
}
.detail-breadcrumb span {
    color: #999;
    display: inline-flex;
    align-items: center;
    gap: .4rem;
}
.detail-breadcrumb span[aria-current="page"] {
    color: var(--crimson);
    font-weight: 600;
}
.detail-breadcrumb i.fa-chevron-right {
    font-size: .7rem;
}

/* Hero */
.detail-hero {
    position: relative;
    overflow: hidden;
}
.detail-hero-img {
    height: 500px;
    background-size: cover;
    background-position: center;
    position: relative;
}
.detail-hero-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(to top, rgba(18,18,18,.95) 0%, rgba(18,18,18,.6) 50%, rgba(18,18,18,.3) 100%);
}
.detail-hero-content {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 3rem 2rem;
    max-width: 1000px;
    margin: 0 auto;
    color: #fff;
    z-index: 2;
}
.detail-cat {
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    background: linear-gradient(135deg, var(--crimson), var(--crimson-light));
    color: #fff;
    padding: .5rem 1.2rem;
    border-radius: 50px;
    font-size: .82rem;
    font-weight: 700;
    letter-spacing: .5px;
    margin-bottom: 1.2rem;
    text-transform: uppercase;
}
.detail-title {
    font-size: clamp(1.8rem, 4vw, 2.8rem);
    font-weight: 900;
    line-height: 1.2;
    margin-bottom: 1.2rem;
    color: #fff;
}
.detail-meta-hero {
    display: flex;
    gap: 1.5rem;
    flex-wrap: wrap;
    font-size: .9rem;
    color: rgba(255,255,255,.85);
}
.detail-meta-hero .meta-item {
    display: inline-flex;
    align-items: center;
    gap: .5rem;
}
.detail-meta-hero i {
    color: var(--sky-blue);
}

/* Detail Layout */
.detail-body-wrapper {
    background: #fff;
    padding: 3rem 0;
}
.detail-layout {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 2rem;
    display: grid;
    grid-template-columns: 1fr 340px;
    gap: 3rem;
}

/* Main Content */
.detail-main {
    position: relative;
}

/* Share Bar */
.share-bar {
    position: sticky;
    top: 100px;
    float: left;
    margin-left: -80px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: .6rem;
    padding: 1rem .6rem;
    background: #fff;
    border-radius: 50px;
    box-shadow: 0 10px 30px rgba(0,0,0,.1);
    border: 1px solid #f0f0f0;
}
.share-label {
    font-size: .7rem;
    font-weight: 800;
    color: var(--text-grey);
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: .5rem;
}
.share-btn {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    background: var(--bg-light);
    color: var(--rich-black);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    border: none;
    cursor: pointer;
    transition: all .3s ease;
}
.share-btn:hover {
    transform: translateY(-3px);
    color: #fff;
}
.share-fb:hover { background: #1877f2; }
.share-tw:hover { background: #000; }
.share-wa:hover { background: #25D366; }
.share-link:hover { background: var(--crimson); }
.share-print:hover { background: var(--emerald); }

/* Detail Content Typography */
.detail-content {
    font-size: 1.08rem;
    line-height: 1.85;
    color: #333;
}
.detail-content p {
    margin-bottom: 1.4rem;
}
.detail-content h2, .detail-content h3, .detail-content h4 {
    color: var(--rich-black);
    font-weight: 800;
    margin: 2rem 0 1rem;
    line-height: 1.3;
}
.detail-content h2 { font-size: 1.6rem; }
.detail-content h3 { font-size: 1.3rem; }
.detail-content h4 { font-size: 1.1rem; }
.detail-content a {
    color: var(--crimson);
    text-decoration: underline;
    transition: color .3s ease;
}
.detail-content a:hover {
    color: var(--sky-blue-dark);
}
.detail-content ul, .detail-content ol {
    margin: 1rem 0 1.5rem 1.5rem;
}
.detail-content li {
    margin-bottom: .6rem;
}
.detail-content blockquote {
    border-left: 4px solid var(--crimson);
    padding: 1rem 1.5rem;
    margin: 1.5rem 0;
    background: var(--bg-light);
    border-radius: 0 12px 12px 0;
    font-style: italic;
    color: var(--text-grey);
}
.detail-content img {
    max-width: 100%;
    height: auto;
    border-radius: 12px;
    margin: 1.5rem 0;
    box-shadow: 0 10px 30px rgba(0,0,0,.1);
}
.detail-content pre, .detail-content code {
    background: #f5f5f5;
    padding: .2rem .5rem;
    border-radius: 4px;
    font-family: 'Courier New', monospace;
    font-size: .9em;
}
.detail-content pre {
    padding: 1rem;
    overflow-x: auto;
    border-radius: 8px;
    margin: 1.5rem 0;
}

/* Tags */
.detail-tags {
    margin-top: 3rem;
    padding-top: 2rem;
    border-top: 2px solid #f0f0f0;
    display: flex;
    flex-wrap: wrap;
    gap: .6rem;
    align-items: center;
}
.tags-label {
    color: var(--text-grey);
    font-weight: 700;
    margin-right: .5rem;
    display: inline-flex;
    align-items: center;
    gap: .4rem;
}
.tags-label i {
    color: var(--crimson);
}
.tag-item {
    display: inline-block;
    background: var(--bg-light);
    color: var(--text-grey);
    padding: .4rem 1rem;
    border-radius: 50px;
    font-size: .85rem;
    font-weight: 600;
    text-decoration: none;
    transition: all .3s ease;
    border: 1px solid #e0e0e0;
}
.tag-item:hover {
    background: var(--crimson);
    color: #fff;
    border-color: var(--crimson);
}

/* Author Card */
.author-card {
    display: flex;
    gap: 1.5rem;
    padding: 2rem;
    background: linear-gradient(135deg, #f8f9fa, #fff);
    border-radius: 20px;
    margin-top: 3rem;
    border: 2px solid #f0f0f0;
}
.author-avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--crimson), var(--crimson-light));
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    flex-shrink: 0;
    box-shadow: 0 10px 25px rgba(138,21,56,.25);
}
.author-label {
    display: block;
    color: var(--text-grey);
    font-size: .82rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: .3rem;
}
.author-info h4 {
    font-size: 1.2rem;
    margin-bottom: .5rem;
    color: var(--rich-black);
    font-weight: 800;
}
.author-info p {
    color: var(--text-grey);
    font-size: .92rem;
    line-height: 1.6;
}

/* Article Navigation */
.article-nav {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    margin-top: 2rem;
}
.article-nav-item {
    padding: 1.5rem;
    background: var(--bg-light);
    border-radius: 16px;
    text-decoration: none;
    transition: all .3s ease;
    border: 2px solid transparent;
}
.article-nav-item:hover {
    border-color: var(--crimson);
    transform: translateY(-3px);
    box-shadow: 0 10px 30px rgba(138,21,56,.1);
}
.nav-label {
    display: block;
    font-size: .78rem;
    color: var(--crimson);
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: .5rem;
}
.nav-title {
    display: block;
    color: var(--rich-black);
    font-weight: 700;
    line-height: 1.4;
    font-size: .95rem;
}
.nav-next {
    text-align: right;
}

/* Sidebar */
.detail-sidebar {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}
.sidebar-card {
    background: #fff;
    border-radius: 20px;
    padding: 1.8rem;
    box-shadow: 0 10px 30px rgba(0,0,0,.06);
    border: 1px solid #f0f0f0;
}
.sidebar-card h3 {
    font-size: 1.1rem;
    margin-bottom: 1.2rem;
    color: var(--rich-black);
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: .5rem;
    padding-bottom: .8rem;
    border-bottom: 2px solid #f0f0f0;
}
.sidebar-card h3 i {
    color: var(--crimson);
}
.info-list {
    list-style: none;
    display: flex;
    flex-direction: column;
    gap: .8rem;
}
.info-list li {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: .9rem;
    padding-bottom: .6rem;
    border-bottom: 1px dashed #f0f0f0;
}
.info-list li:last-child {
    border-bottom: none;
    padding-bottom: 0;
}
.info-label {
    color: var(--text-grey);
    font-weight: 500;
}
.info-value {
    color: var(--rich-black);
    font-weight: 700;
    text-align: right;
}

/* TOC */
.toc-card {
    position: sticky;
    top: 100px;
}
.toc {
    display: flex;
    flex-direction: column;
    gap: .5rem;
}
.toc a {
    display: block;
    padding: .5rem .8rem;
    color: var(--text-grey);
    text-decoration: none;
    font-size: .88rem;
    border-left: 3px solid transparent;
    border-radius: 0 8px 8px 0;
    transition: all .3s ease;
}
.toc a:hover {
    background: var(--bg-light);
    color: var(--crimson);
    border-left-color: var(--crimson);
}
.toc a.active {
    background: rgba(138,21,56,.08);
    color: var(--crimson);
    border-left-color: var(--crimson);
    font-weight: 700;
}
.toc-empty {
    color: #999;
    font-size: .85rem;
    font-style: italic;
}

/* Share Card */
.share-card {
    text-align: center;
    background: linear-gradient(135deg, var(--crimson), var(--crimson-light));
    color: #fff;
}
.share-card i {
    font-size: 2.5rem;
    margin-bottom: 1rem;
    opacity: .9;
}
.share-card h3 {
    color: #fff;
    border-color: rgba(255,255,255,.2);
    justify-content: center;
}
.share-card h3 i {
    color: #fff;
}
.share-card p {
    color: rgba(255,255,255,.9);
    margin-bottom: 1.5rem;
    font-size: .95rem;
}
.share-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: .5rem;
}
.share-grid-btn {
    width: 100%;
    height: 48px;
    border-radius: 12px;
    background: rgba(255,255,255,.15);
    border: 1px solid rgba(255,255,255,.25);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    cursor: pointer;
    transition: all .3s ease;
}
.share-grid-btn:hover {
    background: #fff;
    color: var(--crimson);
    transform: translateY(-3px);
}

/* Related Section */
.related-section {
    background: var(--bg-light);
    padding: 5rem 1.5rem;
}
.related-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 2rem;
    max-width: 1200px;
    margin: 0 auto;
}
.related-card {
    background: #fff;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 10px 35px rgba(0,0,0,.08);
    transition: all .4s ease;
}
.related-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 20px 50px rgba(0,0,0,.15);
}
.related-card > a {
    display: block;
    color: inherit;
    text-decoration: none;
}
.related-img {
    height: 200px;
    background-size: cover;
    background-position: center;
}
.related-body {
    padding: 1.5rem;
}
.related-body h3 {
    font-size: 1.05rem;
    margin: .8rem 0;
    color: var(--rich-black);
    font-weight: 700;
    line-height: 1.4;
}
.related-date {
    color: #999;
    font-size: .82rem;
    display: flex;
    align-items: center;
    gap: .4rem;
}

/* Back CTA */
.back-cta {
    padding: 3rem 1.5rem;
    background: #fff;
    text-align: center;
}
.btn-back-news {
    display: inline-flex;
    align-items: center;
    gap: .8rem;
    padding: 1rem 2rem;
    background: var(--rich-black);
    color: #fff;
    border-radius: 50px;
    font-weight: 700;
    text-decoration: none;
    transition: all .3s ease;
}
.btn-back-news:hover {
    background: var(--crimson);
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(138,21,56,.3);
}

/* Print Styles */
@media print {
    .reading-progress,
    .share-bar,
    .detail-sidebar,
    .related-section,
    .back-cta,
    .detail-breadcrumb,
    .skip-link {
        display: none !important;
    }
    .detail-layout {
        grid-template-columns: 1fr !important;
    }
    .detail-hero-img {
        height: 300px;
    }
}

/* Responsive */
@media (max-width: 1024px) {
    .share-bar {
        display: none;
    }
    .detail-layout {
        grid-template-columns: 1fr;
    }
    .toc-card {
        position: static;
    }
}
@media (max-width: 768px) {
    .detail-hero-img {
        height: 380px;
    }
    .detail-hero-content {
        padding: 2rem 1.5rem;
    }
    .detail-layout {
        padding: 0 1.5rem;
    }
    .author-card {
        flex-direction: column;
        text-align: center;
    }
    .article-nav {
        grid-template-columns: 1fr;
    }
    .container-detail {
        padding: 0 1.5rem;
    }
}
</style>

<!-- Berita Detail JavaScript -->
<script>
(function() {
    'use strict';
    
    // Reading Progress Bar
    const progressBar = document.querySelector('.reading-progress-bar');
    if (progressBar) {
        window.addEventListener('scroll', function() {
            const docHeight = document.documentElement.scrollHeight - window.innerHeight;
            const scrolled = (window.scrollY / docHeight) * 100;
            progressBar.style.width = Math.min(scrolled, 100) + '%';
        });
    }
    
    // Auto-generate Table of Contents
    const content = document.querySelector('.detail-content');
    const tocNav = document.getElementById('tocNav');
    if (content && tocNav) {
        const headings = content.querySelectorAll('h2, h3');
        if (headings.length > 0) {
            tocNav.innerHTML = '';
            headings.forEach((h, i) => {
                const id = 'section-' + i;
                h.id = id;
                const link = document.createElement('a');
                link.href = '#' + id;
                link.textContent = h.textContent;
                link.style.paddingLeft = (h.tagName === 'H3' ? '1.5rem' : '.8rem');
                tocNav.appendChild(link);
            });
            
            // Active TOC on scroll
            const tocLinks = tocNav.querySelectorAll('a');
            window.addEventListener('scroll', function() {
                let current = '';
                headings.forEach(h => {
                    if (window.scrollY >= h.offsetTop - 150) {
                        current = h.id;
                    }
                });
                tocLinks.forEach(link => {
                    link.classList.toggle('active', link.getAttribute('href') === '#' + current);
                });
            });
        }
    }
    
    // Share Functions
    const pageTitle = <?= json_encode($b['judul'] ?? '') ?>;
    const pageUrl = window.location.href;
    
    window.shareToFacebook = function() {
        window.open(`https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(pageUrl)}`, '_blank', 'width=600,height=400');
    };
    
    window.shareToTwitter = function() {
        window.open(`https://twitter.com/intent/tweet?url=${encodeURIComponent(pageUrl)}&text=${encodeURIComponent(pageTitle)}`, '_blank', 'width=600,height=400');
    };
    
    window.shareToWhatsApp = function() {
        window.open(`https://wa.me/?text=${encodeURIComponent(pageTitle + ' ' + pageUrl)}`, '_blank');
    };
    
    window.copyLink = function(btn) {
        navigator.clipboard.writeText(pageUrl).then(() => {
            const original = btn.innerHTML;
            btn.innerHTML = '<i class="fa-solid fa-check"></i>';
            btn.style.background = 'var(--emerald)';
            btn.style.color = '#fff';
            setTimeout(() => {
                btn.innerHTML = original;
                btn.style.background = '';
                btn.style.color = '';
            }, 2000);
        });
    };
    
    // Smooth scroll for TOC links
    document.querySelectorAll('.toc a').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                const offset = target.offsetTop - 100;
                window.scrollTo({ top: offset, behavior: 'smooth' });
            }
        });
    });
})();
</script>

<?php endif; require_once '../includes/public_footer.php'; ?>