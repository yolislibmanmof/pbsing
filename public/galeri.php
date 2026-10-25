<?php
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

$page_title = 'Galeri Kegiatan';
require_once '../includes/public_header.php';

$galeri = get_all('galeri', null, 'urutan ASC');
$setting = get_all('settings', 'id = 1')[0];

// Extract unique categories for filter
$categories = [];
if (!empty($galeri)) {
    foreach ($galeri as $g) {
        if (!empty($g['kategori'])) {
            $categories[] = $g['kategori'];
        }
    }
    $categories = array_unique($categories);
}
?>

<!-- Skip Link -->
<a href="#main-content" class="skip-link">Skip to main content</a>

<!-- JSON-LD for ImageGallery -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "ImageGallery",
  "name": "Galeri Kegiatan - <?= sanitize($setting['site_name'] ?? 'PBSI UMMaumere') ?>",
  "description": "Dokumentasi momen terbaik keluarga besar PBSI",
  "breadcrumb": {
    "@type": "BreadcrumbList",
    "itemListElement": [
      { "@type": "ListItem", "position": 1, "name": "Beranda", "item": "<?= PUBLIC_URL ?>" },
      { "@type": "ListItem", "position": 2, "name": "Galeri" }
    ]
  }
}
</script>

<main id="main-content" role="main">

<!-- PAGE HERO -->
<section class="page-hero">
    <h1>Galeri Kegiatan</h1>
    <p>Dokumentasi momen terbaik keluarga besar PBSI.</p>
    <nav class="breadcrumb" aria-label="Breadcrumb">
        <a href="<?= PUBLIC_URL ?>index.php">Beranda</a>
        <span aria-hidden="true">/</span>
        <span aria-current="page">Galeri</span>
    </nav>
</section>

<!-- GALLERY SECTION -->
<?php if (!empty($galeri)): ?>
<section class="gallery-section" aria-labelledby="gallery-heading">
    <div class="section">
        <header class="sec-head">
            <span class="sec-eyebrow ey-crimson">
                <i class="fa-solid fa-images" aria-hidden="true"></i> 
                MOMEN TERBAIK
            </span>
            <h2 id="gallery-heading" class="section-title">Galeri Kegiatan</h2>
            <p class="section-subtitle">Kumpulan dokumentasi aktivitas akademik, prestasi, dan kebersamaan keluarga besar PBSI UMMaumere.</p>
        </header>

        <!-- FILTER BUTTONS -->
        <?php if (count($categories) > 1): ?>
        <div class="gallery-filter" role="tablist" aria-label="Filter galeri">
            <button class="filter-btn active" 
                    data-filter="all" 
                    role="tab" 
                    aria-selected="true"
                    aria-controls="gallery-grid">
                <i class="fa-solid fa-border-all" aria-hidden="true"></i> Semua
            </button>
            <?php foreach ($categories as $cat): ?>
            <button class="filter-btn" 
                    data-filter="<?= sanitize(strtolower(str_replace(' ', '-', $cat))) ?>" 
                    role="tab" 
                    aria-selected="false"
                    aria-controls="gallery-grid">
                <?= sanitize($cat) ?>
            </button>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- GALLERY GRID -->
        <div class="gallery-grid" id="gallery-grid" role="list">
            <?php foreach ($galeri as $i => $g): 
                $category = !empty($g['kategori']) ? sanitize(strtolower(str_replace(' ', '-', $g['kategori']))) : 'umum';
            ?>
            <figure class="gallery-item" 
                    data-category="<?= $category ?>"
                    role="listitem"
                    data-aos="zoom-in" 
                    data-aos-delay="<?= ($i % 6) * 50 ?>">
                <button class="gallery-trigger" 
                        onclick="openLightbox(this)" 
                        data-full="<?= img_url($g['file']) ?>"
                        data-title="<?= sanitize($g['judul']) ?>"
                        data-description="<?= sanitize($g['deskripsi'] ?? '') ?>"
                        aria-label="Lihat gambar: <?= sanitize($g['judul']) ?>">
                    <img src="<?= img_url($g['file']) ?>" 
                         alt="<?= sanitize($g['judul']) ?>" 
                         loading="lazy"
                         onerror="this.onerror=null;this.src='<?= img_placeholder() ?>';">
                    <div class="gallery-overlay">
                        <i class="fa-solid fa-magnifying-glass-plus" aria-hidden="true"></i>
                    </div>
                </button>
                <figcaption>
                    <h3><?= sanitize($g['judul']) ?></h3>
                    <?php if (!empty($g['kategori'])): ?>
                    <span class="gallery-cat"><?= sanitize($g['kategori']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($g['tanggal'])): ?>
                    <time datetime="<?= date('Y-m-d', strtotime($g['tanggal'])) ?>" class="gallery-date">
                        <i class="fa-regular fa-calendar" aria-hidden="true"></i> 
                        <?= date('d M Y', strtotime($g['tanggal'])) ?>
                    </time>
                    <?php endif; ?>
                </figcaption>
            </figure>
            <?php endforeach; ?>
        </div>

        <!-- GALLERY STATS -->
        <div class="gallery-stats" aria-live="polite">
            <p>Menampilkan <strong id="visible-count"><?= count($galeri) ?></strong> dari <strong><?= count($galeri) ?></strong> foto</p>
        </div>
    </div>
</section>
<?php else: ?>
<!-- EMPTY STATE -->
<section class="section">
    <div class="empty-state" style="text-align:center;padding:5rem 2rem;">
        <i class="fa-solid fa-images" style="font-size:5rem;color:#ddd;margin-bottom:1.5rem;" aria-hidden="true"></i>
        <h2 style="color:#999;margin-bottom:.5rem;">Galeri Belum Tersedia</h2>
        <p style="color:#aaa;max-width:500px;margin:0 auto;">Dokumentasi kegiatan sedang dalam proses pengumpulan. Silakan kunjungi kembali nanti.</p>
        <a href="<?= PUBLIC_URL ?>berita.php" class="btn-primary" style="margin-top:2rem;">
            <i class="fa-solid fa-newspaper" aria-hidden="true"></i> Lihat Berita Terbaru
        </a>
    </div>
</section>
<?php endif; ?>

</main>

<!-- LIGHTBOX MODAL -->
<div class="lightbox" id="lightbox" role="dialog" aria-modal="true" aria-label="Image viewer" aria-hidden="true">
    <div class="lightbox-backdrop" onclick="closeLightbox()"></div>
    <div class="lightbox-content">
        <button class="lightbox-close" onclick="closeLightbox()" aria-label="Tutup">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
        </button>
        
        <button class="lightbox-nav lightbox-prev" onclick="navigateLightbox(-1)" aria-label="Gambar sebelumnya">
            <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
        </button>
        
        <div class="lightbox-image-wrapper">
            <img src="" alt="" class="lightbox-img">
            <div class="lightbox-loading" aria-hidden="true">
                <i class="fa-solid fa-spinner fa-spin"></i>
            </div>
        </div>
        
        <button class="lightbox-nav lightbox-next" onclick="navigateLightbox(1)" aria-label="Gambar berikutnya">
            <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
        </button>
        
        <div class="lightbox-info">
            <h3 class="lightbox-title"></h3>
            <p class="lightbox-description"></p>
            <div class="lightbox-counter">
                <span id="lightbox-current">1</span> / <span id="lightbox-total">1</span>
            </div>
        </div>
    </div>
</div>

<!-- Inline Styles for Gallery -->
<style>
/* Gallery Section */
.gallery-section {
    background: var(--bg-light);
    padding: 5rem 1.5rem;
}

/* Filter Buttons */
.gallery-filter {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: .8rem;
    margin-bottom: 3rem;
}
.filter-btn {
    padding: .7rem 1.6rem;
    border: 2px solid #e0e0e0;
    background: #fff;
    border-radius: 50px;
    font-weight: 600;
    color: var(--text-grey);
    cursor: pointer;
    transition: all .3s ease;
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    font-size: .9rem;
}
.filter-btn:hover {
    border-color: var(--crimson);
    color: var(--crimson);
    transform: translateY(-2px);
}
.filter-btn.active {
    background: linear-gradient(135deg, var(--crimson), var(--crimson-light));
    color: #fff;
    border-color: transparent;
    box-shadow: 0 8px 20px rgba(138,21,56,.3);
}

/* Gallery Grid */
.gallery-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1.5rem;
}
.gallery-item {
    background: #fff;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 8px 25px rgba(0,0,0,.08);
    transition: all .4s cubic-bezier(.4,0,.2,1);
    position: relative;
}
.gallery-item:hover {
    transform: translateY(-8px);
    box-shadow: 0 20px 50px rgba(0,0,0,.15);
}
.gallery-item.hidden {
    display: none;
}
.gallery-trigger {
    display: block;
    width: 100%;
    aspect-ratio: 4/3;
    position: relative;
    overflow: hidden;
    cursor: pointer;
    background: none;
    border: none;
    padding: 0;
}
.gallery-trigger img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform .5s ease;
}
.gallery-trigger:hover img {
    transform: scale(1.1);
}
.gallery-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(to top, rgba(10,10,10,.7), transparent 60%);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity .3s ease;
}
.gallery-trigger:hover .gallery-overlay {
    opacity: 1;
}
.gallery-overlay i {
    font-size: 2.5rem;
    color: #fff;
    transform: scale(.8);
    transition: transform .3s ease;
}
.gallery-trigger:hover .gallery-overlay i {
    transform: scale(1);
}
.gallery-item figcaption {
    padding: 1.2rem 1.5rem;
}
.gallery-item figcaption h3 {
    font-size: 1rem;
    margin-bottom: .5rem;
    color: var(--rich-black);
    font-weight: 700;
    line-height: 1.4;
}
.gallery-cat {
    display: inline-block;
    background: rgba(138,21,56,.1);
    color: var(--crimson);
    padding: .25rem .8rem;
    border-radius: 50px;
    font-size: .72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .5px;
    margin-bottom: .5rem;
}
.gallery-date {
    display: block;
    color: #999;
    font-size: .82rem;
    margin-top: .5rem;
}
.gallery-date i {
    margin-right: .3rem;
}

/* Gallery Stats */
.gallery-stats {
    text-align: center;
    margin-top: 3rem;
    padding: 1.5rem;
    background: rgba(138,21,56,.05);
    border-radius: 12px;
    color: var(--text-grey);
}
.gallery-stats strong {
    color: var(--crimson);
}

/* Lightbox */
.lightbox {
    position: fixed;
    inset: 0;
    z-index: 10000;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    visibility: hidden;
    transition: all .3s ease;
}
.lightbox.active {
    opacity: 1;
    visibility: visible;
}
.lightbox[aria-hidden="false"] {
    opacity: 1;
    visibility: visible;
}
.lightbox-backdrop {
    position: absolute;
    inset: 0;
    background: rgba(0,0,0,.95);
    backdrop-filter: blur(10px);
}
.lightbox-content {
    position: relative;
    z-index: 1;
    max-width: 90vw;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    align-items: center;
}
.lightbox-close {
    position: absolute;
    top: -50px;
    right: 0;
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: rgba(255,255,255,.1);
    border: 2px solid rgba(255,255,255,.3);
    color: #fff;
    font-size: 1.5rem;
    cursor: pointer;
    transition: all .3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}
.lightbox-close:hover {
    background: var(--crimson);
    border-color: var(--crimson);
    transform: rotate(90deg);
}
.lightbox-image-wrapper {
    position: relative;
    max-width: 100%;
    max-height: 70vh;
    display: flex;
    align-items: center;
    justify-content: center;
}
.lightbox-img {
    max-width: 100%;
    max-height: 70vh;
    object-fit: contain;
    border-radius: 12px;
    box-shadow: 0 20px 60px rgba(0,0,0,.5);
    opacity: 0;
    transition: opacity .3s ease;
}
.lightbox-img.loaded {
    opacity: 1;
}
.lightbox-loading {
    position: absolute;
    color: #fff;
    font-size: 2rem;
}
.lightbox-nav {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: rgba(255,255,255,.1);
    border: 2px solid rgba(255,255,255,.3);
    color: #fff;
    font-size: 1.3rem;
    cursor: pointer;
    transition: all .3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 2;
}
.lightbox-nav:hover {
    background: var(--crimson);
    border-color: var(--crimson);
    transform: translateY(-50%) scale(1.1);
}
.lightbox-prev {
    left: -70px;
}
.lightbox-next {
    right: -70px;
}
.lightbox-info {
    margin-top: 1.5rem;
    text-align: center;
    color: #fff;
    max-width: 600px;
}
.lightbox-title {
    font-size: 1.3rem;
    margin-bottom: .5rem;
    font-weight: 700;
}
.lightbox-description {
    color: #bdc3c7;
    font-size: .95rem;
    line-height: 1.6;
    margin-bottom: 1rem;
}
.lightbox-counter {
    color: var(--sky-blue);
    font-weight: 700;
    font-size: .9rem;
}

/* Responsive */
@media (max-width: 900px) {
    .gallery-grid {
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 1rem;
    }
    .lightbox-prev {
        left: 10px;
    }
    .lightbox-next {
        right: 10px;
    }
    .lightbox-close {
        top: 10px;
        right: 10px;
    }
}
@media (max-width: 560px) {
    .gallery-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- Gallery JavaScript -->
<script>
(function() {
    'use strict';
    
    // Filter functionality
    const filterBtns = document.querySelectorAll('.filter-btn');
    const galleryItems = document.querySelectorAll('.gallery-item');
    const visibleCountEl = document.getElementById('visible-count');
    
    filterBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const filter = this.dataset.filter;
            
            // Update active state
            filterBtns.forEach(b => {
                b.classList.remove('active');
                b.setAttribute('aria-selected', 'false');
            });
            this.classList.add('active');
            this.setAttribute('aria-selected', 'true');
            
            // Filter items
            let visibleCount = 0;
            galleryItems.forEach(item => {
                const category = item.dataset.category;
                if (filter === 'all' || category === filter) {
                    item.classList.remove('hidden');
                    visibleCount++;
                } else {
                    item.classList.add('hidden');
                }
            });
            
            // Update counter
            if (visibleCountEl) {
                visibleCountEl.textContent = visibleCount;
            }
        });
    });
    
    // Lightbox functionality
    const lightbox = document.getElementById('lightbox');
    const lightboxImg = lightbox.querySelector('.lightbox-img');
    const lightboxTitle = lightbox.querySelector('.lightbox-title');
    const lightboxDesc = lightbox.querySelector('.lightbox-description');
    const lightboxCurrent = document.getElementById('lightbox-current');
    const lightboxTotal = document.getElementById('lightbox-total');
    const lightboxLoading = lightbox.querySelector('.lightbox-loading');
    
    let currentIndex = 0;
    let visibleItems = [];
    
    window.openLightbox = function(trigger) {
        visibleItems = Array.from(document.querySelectorAll('.gallery-item:not(.hidden)'));
        currentIndex = visibleItems.findIndex(item => item.querySelector('.gallery-trigger') === trigger);
        
        if (currentIndex === -1) currentIndex = 0;
        
        updateLightbox();
        lightbox.classList.add('active');
        lightbox.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        
        // Focus trap
        lightbox.querySelector('.lightbox-close').focus();
    };
    
    window.closeLightbox = function() {
        lightbox.classList.remove('active');
        lightbox.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    };
    
    window.navigateLightbox = function(direction) {
        currentIndex = (currentIndex + direction + visibleItems.length) % visibleItems.length;
        updateLightbox();
    };
    
    function updateLightbox() {
        const item = visibleItems[currentIndex];
        const trigger = item.querySelector('.gallery-trigger');
        const imgSrc = trigger.dataset.full;
        const title = trigger.dataset.title;
        const desc = trigger.dataset.description;
        
        lightboxLoading.style.display = 'block';
        lightboxImg.classList.remove('loaded');
        
        lightboxImg.onload = function() {
            lightboxLoading.style.display = 'none';
            lightboxImg.classList.add('loaded');
        };
        
        lightboxImg.src = imgSrc;
        lightboxImg.alt = title;
        lightboxTitle.textContent = title;
        lightboxDesc.textContent = desc || '';
        lightboxCurrent.textContent = currentIndex + 1;
        lightboxTotal.textContent = visibleItems.length;
    }
    
    // Keyboard navigation
    document.addEventListener('keydown', function(e) {
        if (!lightbox.classList.contains('active')) return;
        
        if (e.key === 'Escape') closeLightbox();
        if (e.key === 'ArrowLeft') navigateLightbox(-1);
        if (e.key === 'ArrowRight') navigateLightbox(1);
    });
    
    // Touch/swipe support
    let touchStartX = 0;
    lightbox.addEventListener('touchstart', function(e) {
        touchStartX = e.touches[0].clientX;
    }, { passive: true });
    
    lightbox.addEventListener('touchend', function(e) {
        const touchEndX = e.changedTouches[0].clientX;
        const diff = touchStartX - touchEndX;
        
        if (Math.abs(diff) > 50) {
            if (diff > 0) navigateLightbox(1);
            else navigateLightbox(-1);
        }
    }, { passive: true });
})();
</script>

<?php require_once '../includes/public_footer.php'; ?>