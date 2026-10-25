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

$page_title = 'Berita & Kegiatan';
require_once '../includes/public_header.php';

$berita = get_all('berita', 'status = 1', 'tanggal DESC');
$setting = get_all('settings', 'id = 1')[0];

// Extract unique categories
$categories = [];
if (!empty($berita)) {
    foreach ($berita as $b) {
        if (!empty($b['kategori'])) {
            $categories[] = $b['kategori'];
        }
    }
    $categories = array_unique($categories);
}

// Pagination
$itemsPerPage = 9;
$totalItems = count($berita);
$totalPages = ceil($totalItems / $itemsPerPage);
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($currentPage - 1) * $itemsPerPage;
$beritaPage = array_slice($berita, $offset, $itemsPerPage);
?>



<!-- JSON-LD for News Articles -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "CollectionPage",
  "name": "Berita & Kegiatan - <?= sanitize($setting['site_name'] ?? 'PBSI UMMaumere') ?>",
  "description": "Informasi terkini seputar aktivitas akademik dan prestasi mahasiswa",
  "breadcrumb": {
    "@type": "BreadcrumbList",
    "itemListElement": [
      { "@type": "ListItem", "position": 1, "name": "Beranda", "item": "<?= PUBLIC_URL ?>" },
      { "@type": "ListItem", "position": 2, "name": "Berita" }
    ]
  }
}
</script>

<main id="main-content" role="main">

<!-- PAGE HERO -->
<section class="page-hero">
    <h1>Berita &amp; Kegiatan</h1>
    <p>Informasi terkini seputar aktivitas akademik dan prestasi mahasiswa.</p>
    <nav class="breadcrumb" aria-label="Breadcrumb">
        <a href="<?= PUBLIC_URL ?>index.php">Beranda</a>
        <span aria-hidden="true">/</span>
        <span aria-current="page">Berita</span>
    </nav>
</section>

<!-- NEWS SECTION -->
<?php if (!empty($berita)): ?>
<section class="news-section" aria-labelledby="news-heading">
    <div class="section">
        <header class="sec-head">
            <span class="sec-eyebrow ey-crimson">
                <i class="fa-solid fa-newspaper" aria-hidden="true"></i> 
                KABAR TERKINI
            </span>
            <h2 id="news-heading" class="section-title">Berita &amp; Kegiatan</h2>
            <p class="section-subtitle">Sorotan aktivitas akademik, prestasi, dan peristiwa kampus.</p>
        </header>

        <!-- SEARCH & FILTER -->
        <div class="news-controls">
            <div class="search-box">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <input type="text" 
                       id="newsSearch" 
                       placeholder="Cari berita..." 
                       aria-label="Cari berita">
            </div>
            
            <?php if (count($categories) > 1): ?>
            <div class="filter-dropdown">
                <label for="categoryFilter">
                    <i class="fa-solid fa-filter" aria-hidden="true"></i> Kategori:
                </label>
                <select id="categoryFilter" aria-label="Filter berdasarkan kategori">
                    <option value="all">Semua Kategori</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?= sanitize(strtolower($cat)) ?>"><?= sanitize($cat) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
        </div>

        <!-- NEWS GRID -->
        <div class="news-grid" id="newsGrid" role="list">
            <?php foreach ($beritaPage as $i => $b): 
                $category = !empty($b['kategori']) ? sanitize(strtolower($b['kategori'])) : 'umum';
            ?>
            <article class="news-card" 
                     data-category="<?= $category ?>"
                     data-title="<?= sanitize(strtolower($b['judul'])) ?>"
                     role="listitem"
                     data-aos="fade-up" 
                     data-aos-delay="<?= ($i % 3) * 100 ?>">
                <a href="<?= PUBLIC_URL ?>berita_detail.php?slug=<?= sanitize($b['slug']) ?>" 
                   data-analytics="news-card"
                   aria-label="Baca selengkapnya: <?= sanitize($b['judul']) ?>">
                    <div class="news-img" 
                         style="background-image:url('<?= img_url($b['gambar']) ?>')"
                         role="img"
                         aria-label="<?= sanitize($b['judul']) ?>">
                        <?php if (!empty($b['kategori'])): ?>
                        <span class="news-badge"><?= sanitize($b['kategori']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="news-body">
                        <h3><?= sanitize($b['judul']) ?></h3>
                        <p><?= sanitize(substr(strip_tags($b['isi']), 0, 120)) ?>...</p>
                        <div class="news-meta">
                            <span class="news-date">
                                <i class="fa-regular fa-calendar" aria-hidden="true"></i> 
                                <time datetime="<?= date('Y-m-d', strtotime($b['tanggal'])) ?>">
                                    <?= date('d M Y', strtotime($b['tanggal'])) ?>
                                </time>
                            </span>
                            <span class="news-views">
                                <i class="fa-regular fa-eye" aria-hidden="true"></i> 
                                <?= number_format((int)$b['views']) ?>
                            </span>
                        </div>
                        <span class="news-readmore">
                            Baca Selengkapnya <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                        </span>
                    </div>
                </a>
            </article>
            <?php endforeach; ?>
        </div>

        <!-- NEWS STATS -->
        <div class="news-stats" aria-live="polite">
            <p>Menampilkan <strong id="visible-count"><?= count($beritaPage) ?></strong> dari <strong><?= $totalItems ?></strong> berita</p>
        </div>

        <!-- PAGINATION -->
        <?php if ($totalPages > 1): ?>
        <nav class="pagination" aria-label="Navigasi halaman berita">
            <?php if ($currentPage > 1): ?>
            <a href="?page=<?= $currentPage - 1 ?>" class="page-btn" aria-label="Halaman sebelumnya">
                <i class="fa-solid fa-chevron-left" aria-hidden="true"></i> Sebelumnya
            </a>
            <?php endif; ?>
            
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <a href="?page=<?= $p ?>" 
               class="page-btn <?= $p === $currentPage ? 'active' : '' ?>"
               aria-label="Halaman <?= $p ?>"
               aria-current="<?= $p === $currentPage ? 'page' : 'false' ?>">
                <?= $p ?>
            </a>
            <?php endfor; ?>
            
            <?php if ($currentPage < $totalPages): ?>
            <a href="?page=<?= $currentPage + 1 ?>" class="page-btn" aria-label="Halaman berikutnya">
                Berikutnya <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
            </a>
            <?php endif; ?>
        </nav>
        <?php endif; ?>
    </div>
</section>
<?php else: ?>
<!-- EMPTY STATE -->
<section class="section">
    <div class="empty-state" style="text-align:center;padding:5rem 2rem;">
        <i class="fa-solid fa-newspaper" style="font-size:5rem;color:#ddd;margin-bottom:1.5rem;" aria-hidden="true"></i>
        <h2 style="color:#999;margin-bottom:.5rem;">Belum Ada Berita</h2>
        <p style="color:#aaa;max-width:500px;margin:0 auto;">Berita dan kegiatan sedang dalam proses penulisan. Silakan kunjungi kembali nanti.</p>
        <a href="<?= PUBLIC_URL ?>index.php" class="btn-primary" style="margin-top:2rem;">
            <i class="fa-solid fa-home" aria-hidden="true"></i> Kembali ke Beranda
        </a>
    </div>
</section>
<?php endif; ?>

</main>

<!-- Inline Styles for News Page -->
<style>
/* News Section */
.news-section {
    background: var(--bg-light);
    padding: 5rem 1.5rem;
}

/* Search & Filter Controls */
.news-controls {
    display: flex;
    gap: 1rem;
    justify-content: center;
    margin-bottom: 3rem;
    flex-wrap: wrap;
}
.search-box {
    position: relative;
    flex: 1;
    max-width: 400px;
}
.search-box i {
    position: absolute;
    left: 1.2rem;
    top: 50%;
    transform: translateY(-50%);
    color: #999;
}
.search-box input {
    width: 100%;
    padding: .9rem 1.2rem .9rem 3rem;
    border: 2px solid #e0e0e0;
    border-radius: 50px;
    font-size: 1rem;
    transition: all .3s ease;
}
.search-box input:focus {
    outline: none;
    border-color: var(--sky-blue);
    box-shadow: 0 0 0 4px rgba(135,206,235,.15);
}
.filter-dropdown {
    display: flex;
    align-items: center;
    gap: .6rem;
}
.filter-dropdown label {
    font-weight: 600;
    color: var(--text-grey);
    display: flex;
    align-items: center;
    gap: .4rem;
}
.filter-dropdown select {
    padding: .9rem 1.2rem;
    border: 2px solid #e0e0e0;
    border-radius: 50px;
    font-size: .95rem;
    cursor: pointer;
    transition: all .3s ease;
    background: #fff;
}
.filter-dropdown select:focus {
    outline: none;
    border-color: var(--sky-blue);
}

/* News Grid */
.news-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 2rem;
}
.news-card {
    background: #fff;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 10px 35px rgba(0,0,0,.08);
    transition: all .4s cubic-bezier(.4,0,.2,1);
    position: relative;
}
.news-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 25px 60px rgba(0,0,0,.15);
}
.news-card.hidden {
    display: none;
}
.news-card > a {
    display: flex;
    flex-direction: column;
    height: 100%;
    color: inherit;
    text-decoration: none;
}
.news-img {
    height: 220px;
    background-size: cover;
    background-position: center;
    background-color: #ddd;
    position: relative;
    overflow: hidden;
}
.news-img::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(to top, rgba(0,0,0,.4), transparent 60%);
    opacity: 0;
    transition: opacity .3s ease;
}
.news-card:hover .news-img::after {
    opacity: 1;
}
.news-badge {
    position: absolute;
    top: 1rem;
    left: 1rem;
    background: linear-gradient(135deg, var(--crimson), var(--crimson-light));
    color: #fff;
    padding: .4rem 1.2rem;
    border-radius: 50px;
    font-size: .75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .8px;
    box-shadow: 0 4px 15px rgba(138,21,56,.3);
    z-index: 1;
}
.news-body {
    padding: 1.8rem;
    flex: 1;
    display: flex;
    flex-direction: column;
}
.news-body h3 {
    font-size: 1.2rem;
    margin-bottom: .8rem;
    color: var(--rich-black);
    font-weight: 700;
    line-height: 1.4;
}
.news-body p {
    color: var(--text-grey);
    font-size: .92rem;
    line-height: 1.7;
    margin-bottom: 1.2rem;
    flex: 1;
}
.news-meta {
    display: flex;
    gap: 1rem;
    margin-bottom: 1rem;
    font-size: .85rem;
    color: #999;
}
.news-meta i {
    color: var(--crimson);
    margin-right: .3rem;
}
.news-readmore {
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    color: var(--crimson);
    font-weight: 600;
    font-size: .9rem;
    transition: all .3s ease;
}
.news-card:hover .news-readmore {
    gap: .8rem;
    color: var(--sky-blue-dark);
}

/* News Stats */
.news-stats {
    text-align: center;
    margin-top: 3rem;
    padding: 1.5rem;
    background: rgba(138,21,56,.05);
    border-radius: 12px;
    color: var(--text-grey);
}
.news-stats strong {
    color: var(--crimson);
}

/* Pagination */
.pagination {
    display: flex;
    justify-content: center;
    gap: .5rem;
    margin-top: 3rem;
    flex-wrap: wrap;
}
.page-btn {
    padding: .7rem 1.2rem;
    border: 2px solid #e0e0e0;
    background: #fff;
    border-radius: 10px;
    color: var(--text-grey);
    font-weight: 600;
    transition: all .3s ease;
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    text-decoration: none;
}
.page-btn:hover {
    border-color: var(--crimson);
    color: var(--crimson);
    transform: translateY(-2px);
}
.page-btn.active {
    background: linear-gradient(135deg, var(--crimson), var(--crimson-light));
    color: #fff;
    border-color: transparent;
    box-shadow: 0 8px 20px rgba(138,21,56,.3);
}

/* Responsive */
@media (max-width: 900px) {
    .news-grid {
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 1.5rem;
    }
    .news-controls {
        flex-direction: column;
    }
    .search-box {
        max-width: 100%;
    }
}
@media (max-width: 560px) {
    .news-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- News Page JavaScript -->
<script>
(function() {
    'use strict';
    
    const newsCards = document.querySelectorAll('.news-card');
    const searchInput = document.getElementById('newsSearch');
    const categoryFilter = document.getElementById('categoryFilter');
    const visibleCountEl = document.getElementById('visible-count');
    
    function filterNews() {
        const searchTerm = searchInput ? searchInput.value.toLowerCase() : '';
        const categoryValue = categoryFilter ? categoryFilter.value : 'all';
        
        let visibleCount = 0;
        
        newsCards.forEach(card => {
            const title = card.dataset.title || '';
            const category = card.dataset.category || '';
            
            const matchesSearch = title.includes(searchTerm);
            const matchesCategory = categoryValue === 'all' || category === categoryValue;
            
            if (matchesSearch && matchesCategory) {
                card.classList.remove('hidden');
                visibleCount++;
            } else {
                card.classList.add('hidden');
            }
        });
        
        if (visibleCountEl) {
            visibleCountEl.textContent = visibleCount;
        }
    }
    
    if (searchInput) {
        searchInput.addEventListener('input', filterNews);
    }
    
    if (categoryFilter) {
        categoryFilter.addEventListener('change', filterNews);
    }
})();
</script>

<?php require_once '../includes/public_footer.php'; ?>