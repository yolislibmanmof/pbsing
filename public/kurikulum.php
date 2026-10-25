<?php
require_once '../includes/config.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';
check_ip_security($pdo);

$page_title = 'Kurikulum & Jalur Studi';
require_once '../includes/public_header.php';

$mk = get_all('mata_kuliah', null, 'semester ASC, urutan ASC');
$setting = get_all('settings', 'id = 1')[0];

$by_sem = [];
foreach ($mk as $m) { $by_sem[(int)$m['semester']][] = $m; }
$total_sks = array_sum(array_column($mk, 'sks'));
$total_mk = count($mk);
$semesters = range(1, 8);

// Extract unique categories
$categories = [];
if (!empty($mk)) {
    foreach ($mk as $m) {
        if (!empty($m['kategori'])) {
            $categories[] = $m['kategori'];
        }
    }
    $categories = array_unique($categories);
}

// SKS breakdown by category
$sksByCat = [];
foreach ($mk as $m) {
    $cat = $m['kategori'] ?? 'Lainnya';
    $sksByCat[$cat] = ($sksByCat[$cat] ?? 0) + (int)$m['sks'];
}
?>

<!-- JSON-LD for Course / Curriculum -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Course",
  "name": "Program Studi Bahasa dan Sastra Inggris - <?= sanitize($setting['site_name'] ?? 'PBSI UMMaumere') ?>",
  "description": "Kurikulum berbasis OBE — 8 semester, <?= $total_sks ?> SKS, 3 peminatan: Linguistik, Sastra, Penerjemahan",
  "provider": {
    "@type": "EducationalOrganization",
    "name": "<?= sanitize($setting['site_name'] ?? 'PBSI UMMaumere') ?>",
    "url": "<?= PUBLIC_URL ?>"
  },
  "educationalLevel": "Undergraduate",
  "timeRequired": "P4Y",
  "teaches": <?= json_encode(array_slice(array_column($mk, 'nama_mk'), 0, 20), JSON_UNESCAPED_SLASHES) ?>,
  "breadcrumb": {
    "@type": "BreadcrumbList",
    "itemListElement": [
      { "@type": "ListItem", "position": 1, "name": "Beranda", "item": "<?= PUBLIC_URL ?>" },
      { "@type": "ListItem", "position": 2, "name": "Kurikulum" }
    ]
  }
}
</script>

<main id="main-content" role="main">

<!-- PAGE HERO -->
<section class="page-hero">
    <h1>Kurikulum &amp; Jalur Studi</h1>
    <p>Kurikulum berbasis OBE — 8 semester, <?= $total_sks ?> SKS, 3 peminatan: Linguistik, Sastra, Penerjemahan.</p>
    <nav class="breadcrumb" aria-label="Breadcrumb">
        <a href="<?= PUBLIC_URL ?>index.php">Beranda</a>
        <span aria-hidden="true">/</span>
        <span aria-current="page">Kurikulum</span>
    </nav>
</section>

<!-- KURIKULUM SECTION -->
<?php if (!empty($mk)): ?>
<section class="kur-section" aria-labelledby="kur-heading">
    <div class="section">
        <header class="sec-head">
            <span class="sec-eyebrow ey-crimson">
                <i class="fa-solid fa-book-open" aria-hidden="true"></i> 
                STRUKTUR KURIKULUM
            </span>
            <h2 class="section-title" id="kur-heading">Kurikulum Berbasis OBE</h2>
            <p class="section-subtitle">Dirancang untuk menghasilkan lulusan yang kompeten, adaptif, dan siap kerja di era global.</p>
        </header>

        <!-- STATS GRID -->
        <div class="kur-stats" role="list">
            <div class="kur-stat" role="listitem" data-aos="fade-up" data-aos-delay="0">
                <i class="fa-solid fa-calendar-days" aria-hidden="true"></i>
                <h3 data-target="8">0</h3>
                <p>Semester</p>
            </div>
            <div class="kur-stat" role="listitem" data-aos="fade-up" data-aos-delay="100">
                <i class="fa-solid fa-book" aria-hidden="true"></i>
                <h3 data-target="<?= $total_mk ?>">0</h3>
                <p>Mata Kuliah</p>
            </div>
            <div class="kur-stat" role="listitem" data-aos="fade-up" data-aos-delay="200">
                <i class="fa-solid fa-graduation-cap" aria-hidden="true"></i>
                <h3 data-target="<?= $total_sks ?>">0</h3>
                <p>Total SKS</p>
            </div>
            <div class="kur-stat" role="listitem" data-aos="fade-up" data-aos-delay="300">
                <i class="fa-solid fa-route" aria-hidden="true"></i>
                <h3 data-target="3">0</h3>
                <p>Peminatan</p>
            </div>
        </div>

        <!-- SKS BREAKDOWN -->
        <?php if (count($sksByCat) > 1): ?>
        <div class="kur-breakdown" data-aos="fade-up">
            <h3 class="breakdown-title">
                <i class="fa-solid fa-chart-pie" aria-hidden="true"></i> 
                Distribusi SKS per Kategori
            </h3>
            <div class="breakdown-grid">
                <?php foreach ($sksByCat as $cat => $sks): 
                    $percent = round(($sks / $total_sks) * 100, 1);
                ?>
                <div class="breakdown-item">
                    <div class="breakdown-header">
                        <span class="breakdown-cat"><?= sanitize($cat) ?></span>
                        <span class="breakdown-sks"><?= $sks ?> SKS</span>
                    </div>
                    <div class="breakdown-bar">
                        <div class="breakdown-fill" 
                             style="width:<?= $percent ?>%" 
                             data-percent="<?= $percent ?>"
                             role="progressbar"
                             aria-valuenow="<?= $percent ?>"
                             aria-valuemin="0"
                             aria-valuemax="100"
                             aria-label="<?= sanitize($cat) ?>: <?= $percent ?>%">
                            <span><?= $percent ?>%</span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- SEARCH & FILTER -->
        <div class="kur-controls">
            <div class="search-box">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <input type="text" 
                       id="mkSearch" 
                       placeholder="Cari mata kuliah..." 
                       aria-label="Cari mata kuliah">
            </div>
            
            <?php if (count($categories) > 1): ?>
            <div class="filter-dropdown">
                <label for="catFilter">
                    <i class="fa-solid fa-filter" aria-hidden="true"></i> Kategori:
                </label>
                <select id="catFilter" aria-label="Filter berdasarkan kategori">
                    <option value="all">Semua Kategori</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?= sanitize(strtolower($cat)) ?>"><?= sanitize($cat) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
        </div>

        <!-- SEMESTER TABS -->
        <div class="sem-tabs" role="tablist" aria-label="Pilih semester">
            <?php foreach ($semesters as $i): ?>
            <button class="sem-tab <?= $i === 1 ? 'active' : '' ?>" 
                    data-sem="<?= $i ?>"
                    role="tab"
                    aria-selected="<?= $i === 1 ? 'true' : 'false' ?>"
                    aria-controls="sem-<?= $i ?>">
                <span class="sem-dot" aria-hidden="true"></span> 
                <span>SMT <?= $i ?></span>
            </button>
            <?php endforeach; ?>
        </div>

        <!-- SEMESTER PANELS -->
        <?php foreach ($semesters as $i):
            $sks_sem = 0;
            $mk_sem = $by_sem[$i] ?? [];
            foreach ($mk_sem as $m) { $sks_sem += (int)$m['sks']; }
        ?>
        <div class="sem-panel <?= $i === 1 ? 'active' : '' ?>" 
             id="sem-<?= $i ?>" 
             role="tabpanel"
             aria-labelledby="sem-tab-<?= $i ?>">
            <div class="sem-header">
                <h2>
                    <i class="fa-solid fa-layer-group" aria-hidden="true"></i> 
                    Semester <?= $i ?>
                </h2>
                <div class="sem-meta">
                    <span class="sem-sks">
                        <i class="fa-solid fa-graduation-cap" aria-hidden="true"></i> 
                        <?= $sks_sem ?> SKS
                    </span>
                    <span class="sem-count">
                        <i class="fa-solid fa-book" aria-hidden="true"></i> 
                        <span class="mk-visible-count"><?= count($mk_sem) ?></span> MK
                    </span>
                </div>
            </div>
            <?php if (empty($mk_sem)): ?>
            <div class="mk-empty">
                <i class="fa-solid fa-folder-open" aria-hidden="true"></i>
                <p>Belum ada mata kuliah untuk semester ini</p>
            </div>
            <?php else: ?>
            <div class="mk-grid">
                <?php foreach ($mk_sem as $j => $m): 
                    $cat = !empty($m['kategori']) ? sanitize(strtolower($m['kategori'])) : 'umum';
                ?>
                <article class="mk-card cat-<?= $cat ?>" 
                         data-category="<?= $cat ?>"
                         data-name="<?= sanitize(strtolower($m['nama_mk'])) ?>"
                         data-aos="fade-up" 
                         data-aos-delay="<?= ($j % 6) * 60 ?>">
                    <div class="mk-top">
                        <span class="mk-code"><?= sanitize($m['kode_mk']) ?></span>
                        <span class="sks-badge"><?= (int)$m['sks'] ?> SKS</span>
                    </div>
                    <h3><?= sanitize($m['nama_mk']) ?></h3>
                    <p><?= sanitize($m['deskripsi']) ?></p>
                    <span class="mk-cat"><?= sanitize($m['kategori']) ?></span>
                </article>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- PETA PERJALANAN STUDI -->
<section class="path-section" aria-labelledby="path-heading">
    <div class="section">
        <header class="sec-head">
            <span class="sec-eyebrow ey-glass">
                <i class="fa-solid fa-map-signs" aria-hidden="true"></i> 
                ROADMAP AKADEMIK
            </span>
            <h2 class="section-title" id="path-heading" style="color:#fff;">Peta Perjalanan Studi</h2>
            <p class="section-subtitle" style="color:#bdc3c7;">Empat fase pembelajaran yang akan Anda lalui selama 8 semester.</p>
        </header>
        <div class="path-timeline" role="list">
            <div class="path-item" role="listitem" data-aos="fade-up" data-aos-delay="0">
                <div class="path-icon"><i class="fa-solid fa-seedling" aria-hidden="true"></i></div>
                <div class="path-dot"></div>
                <h4>Semester 1-2</h4>
                <p>Fondasi empat keterampilan bahasa, grammar, dan mata kuliah dasar keislaman.</p>
            </div>
            <div class="path-item" role="listitem" data-aos="fade-up" data-aos-delay="100">
                <div class="path-icon"><i class="fa-solid fa-book-open-reader" aria-hidden="true"></i></div>
                <div class="path-dot"></div>
                <h4>Semester 3-4</h4>
                <p>Inti linguistik, sastra Inggris-Amerika, teori &amp; praktik penerjemahan.</p>
            </div>
            <div class="path-item" role="listitem" data-aos="fade-up" data-aos-delay="200">
                <div class="path-icon"><i class="fa-solid fa-briefcase" aria-hidden="true"></i></div>
                <div class="path-dot"></div>
                <h4>Semester 5-6</h4>
                <p>Peminatan, interpreting, KKN, dan magang profesi / MBKM.</p>
            </div>
            <div class="path-item" role="listitem" data-aos="fade-up" data-aos-delay="300">
                <div class="path-icon"><i class="fa-solid fa-user-graduate" aria-hidden="true"></i></div>
                <div class="path-dot"></div>
                <h4>Semester 7-8</h4>
                <p>Seminar proposal, publikasi ilmiah, skripsi, dan ujian komprehensif.</p>
            </div>
        </div>
    </div>
</section>

<!-- CTA SECTION -->
<section class="kur-cta" data-aos="fade-up" data-aos-duration="1000">
    <div class="kur-cta-box">
        <i class="fa-solid fa-file-pdf" aria-hidden="true"></i>
        <h2>Unduh Buku Kurikulum Lengkap</h2>
        <p>Dapatkan dokumen lengkap berisi Capaian Pembelajaran Lulusan (CPL), deskripsi mata kuliah, dan rencana pembelajaran semester (RPS).</p>
        <div class="kur-cta-buttons">
            <a href="<?= PUBLIC_URL ?>kontak.php" class="btn-primary" data-analytics="kurikulum-request">
                <i class="fa-solid fa-download" aria-hidden="true"></i> 
                Minta Dokumen Kurikulum
            </a>
            <a href="<?= PUBLIC_URL ?>kontak.php" class="btn-secondary" data-analytics="kurikulum-consult">
                <i class="fa-solid fa-comments" aria-hidden="true"></i> 
                Konsultasi Akademik
            </a>
        </div>
    </div>
</section>
<?php else: ?>
<!-- EMPTY STATE -->
<section class="section">
    <div class="empty-state" style="text-align:center;padding:5rem 2rem;">
        <i class="fa-solid fa-book" style="font-size:5rem;color:#ddd;margin-bottom:1.5rem;" aria-hidden="true"></i>
        <h2 style="color:#999;margin-bottom:.5rem;">Kurikulum Belum Tersedia</h2>
        <p style="color:#aaa;max-width:500px;margin:0 auto;">Data kurikulum sedang dalam proses penyusunan. Silakan hubungi kami untuk informasi lebih lanjut.</p>
        <a href="<?= PUBLIC_URL ?>kontak.php" class="btn-primary" style="margin-top:2rem;">
            <i class="fa-solid fa-headset" aria-hidden="true"></i> Hubungi Kami
        </a>
    </div>
</section>
<?php endif; ?>

</main>

<!-- Inline Styles for Kurikulum Page -->
<style>
/* Kur Section */
.kur-section {
    background: var(--bg-light);
    padding: 5rem 1.5rem;
}

/* Enhanced Stats with Icons */
.kur-stat i {
    font-size: 2.2rem;
    color: var(--crimson);
    margin-bottom: 1rem;
    opacity: .8;
}

/* SKS Breakdown */
.kur-breakdown {
    max-width: 900px;
    margin: 3rem auto;
    background: #fff;
    border-radius: 20px;
    padding: 2.5rem;
    box-shadow: 0 10px 35px rgba(0,0,0,.08);
}
.breakdown-title {
    font-size: 1.2rem;
    margin-bottom: 1.8rem;
    color: var(--rich-black);
    display: flex;
    align-items: center;
    gap: .6rem;
}
.breakdown-title i {
    color: var(--crimson);
}
.breakdown-grid {
    display: flex;
    flex-direction: column;
    gap: 1.2rem;
}
.breakdown-item {
    display: flex;
    flex-direction: column;
    gap: .4rem;
}
.breakdown-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.breakdown-cat {
    font-weight: 700;
    color: var(--rich-black);
    font-size: .95rem;
}
.breakdown-sks {
    color: var(--crimson);
    font-weight: 700;
    font-size: .9rem;
}
.breakdown-bar {
    height: 10px;
    background: #f0f0f0;
    border-radius: 10px;
    overflow: hidden;
    position: relative;
}
.breakdown-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--crimson), var(--crimson-light));
    border-radius: 10px;
    width: 0;
    transition: width 1.5s cubic-bezier(.4,0,.2,1);
    position: relative;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    padding-right: 10px;
}
.breakdown-fill span {
    color: #fff;
    font-size: .72rem;
    font-weight: 800;
    opacity: 0;
    transition: opacity .3s ease .8s;
}
.breakdown-fill.animated span {
    opacity: 1;
}
.breakdown-fill.animated {
    width: var(--target-width, 100%);
}

/* Kur Controls */
.kur-controls {
    display: flex;
    gap: 1rem;
    justify-content: center;
    margin: 3rem 0 2rem;
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
    background: #fff;
    transition: all .3s ease;
}
.filter-dropdown select:focus {
    outline: none;
    border-color: var(--sky-blue);
}

/* Enhanced Semester Header */
.sem-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    max-width: 1200px;
    margin: 0 auto 1.5rem;
    background: var(--rich-black);
    color: #fff;
    padding: 1.2rem 2rem;
    border-radius: 14px;
    border-left: 5px solid var(--sky-blue);
    flex-wrap: wrap;
    gap: 1rem;
}
.sem-meta {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}
.sem-count {
    background: rgba(255,255,255,.1);
    padding: .3rem 1rem;
    border-radius: 50px;
    font-weight: 700;
    font-size: .85rem;
    display: inline-flex;
    align-items: center;
    gap: .4rem;
}

/* MK Empty State */
.mk-empty {
    text-align: center;
    padding: 3rem 1rem;
    color: #999;
}
.mk-empty i {
    font-size: 3rem;
    margin-bottom: 1rem;
    color: #ddd;
}

/* Enhanced Path Timeline */
.path-section .section {
    position: relative;
    z-index: 1;
}
.path-icon {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: rgba(255,255,255,.1);
    border: 2px solid rgba(135,206,235,.4);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--sky-blue);
    font-size: 1.2rem;
    margin: 0 auto 1rem;
    position: relative;
    z-index: 2;
}

/* Kurikulum CTA */
.kur-cta {
    padding: 5rem 1.5rem;
    background: linear-gradient(180deg, #fff, var(--bg-light));
}
.kur-cta-box {
    max-width: 900px;
    margin: 0 auto;
    text-align: center;
    padding: 4rem 3rem;
    background: linear-gradient(135deg, var(--crimson-dark), var(--crimson-light));
    border-radius: 24px;
    color: #fff;
    box-shadow: 0 30px 80px rgba(138,21,56,.35);
    position: relative;
    overflow: hidden;
}
.kur-cta-box::before, .kur-cta-box::after {
    content: '';
    position: absolute;
    border-radius: 50%;
    filter: blur(70px);
}
.kur-cta-box::before {
    width: 340px;
    height: 340px;
    background: var(--sky-blue-dark);
    opacity: .30;
    top: -120px;
    left: -100px;
}
.kur-cta-box::after {
    width: 300px;
    height: 300px;
    background: #fff;
    opacity: .10;
    bottom: -140px;
    right: -80px;
}
.kur-cta-box i {
    font-size: 4rem;
    margin-bottom: 1.5rem;
    opacity: .9;
    position: relative;
    z-index: 1;
}
.kur-cta-box h2 {
    font-size: clamp(1.8rem, 4vw, 2.5rem);
    margin-bottom: 1rem;
    font-weight: 900;
    position: relative;
    z-index: 1;
}
.kur-cta-box p {
    color: rgba(255,255,255,.9);
    max-width: 640px;
    margin: 0 auto 2.2rem;
    line-height: 1.8;
    position: relative;
    z-index: 1;
}
.kur-cta-buttons {
    display: flex;
    gap: 1rem;
    justify-content: center;
    flex-wrap: wrap;
    position: relative;
    z-index: 1;
}
.kur-cta-buttons .btn-primary {
    background: #fff;
    color: var(--crimson);
    margin-top: 0;
}
.kur-cta-buttons .btn-secondary {
    border-color: #fff;
    color: #fff;
    margin-top: 0;
}
.kur-cta-buttons .btn-secondary:hover {
    background: #fff;
    color: var(--crimson);
}

/* Responsive */
@media (max-width: 900px) {
    .kur-controls {
        flex-direction: column;
    }
    .search-box {
        max-width: 100%;
    }
    .kur-cta-box {
        padding: 3rem 1.5rem;
    }
}
@media (max-width: 768px) {
    .path-timeline {
        grid-template-columns: 1fr 1fr;
    }
}
@media (max-width: 560px) {
    .path-timeline {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- Kurikulum Page JavaScript -->
<script>
(function() {
    'use strict';
    
    // Semester Tabs (Enhanced)
    document.querySelectorAll('.sem-tab').forEach(function(tab) {
        tab.addEventListener('click', function() {
            document.querySelectorAll('.sem-tab').forEach(function(t) {
                t.classList.remove('active');
                t.setAttribute('aria-selected', 'false');
            });
            document.querySelectorAll('.sem-panel').forEach(function(p) {
                p.classList.remove('active');
            });
            this.classList.add('active');
            this.setAttribute('aria-selected', 'true');
            var panel = document.getElementById('sem-' + this.getAttribute('data-sem'));
            if (panel) panel.classList.add('active');
        });
    });
    
    // Search & Filter
    const mkSearch = document.getElementById('mkSearch');
    const catFilter = document.getElementById('catFilter');
    const semPanels = document.querySelectorAll('.sem-panel');
    
    function filterMK() {
        const searchTerm = mkSearch ? mkSearch.value.toLowerCase() : '';
        const categoryValue = catFilter ? catFilter.value : 'all';
        
        semPanels.forEach(panel => {
            const cards = panel.querySelectorAll('.mk-card');
            let visibleCount = 0;
            
            cards.forEach(card => {
                const name = card.dataset.name || '';
                const category = card.dataset.category || '';
                
                const matchesSearch = name.includes(searchTerm);
                const matchesCategory = categoryValue === 'all' || category === categoryValue;
                
                if (matchesSearch && matchesCategory) {
                    card.classList.remove('hidden');
                    visibleCount++;
                } else {
                    card.classList.add('hidden');
                }
            });
            
            const countEl = panel.querySelector('.mk-visible-count');
            if (countEl) countEl.textContent = visibleCount;
        });
    }
    
    if (mkSearch) mkSearch.addEventListener('input', filterMK);
    if (catFilter) catFilter.addEventListener('change', filterMK);
    
    // Animated SKS Breakdown
    const breakdownFills = document.querySelectorAll('.breakdown-fill');
    if (breakdownFills.length > 0) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const fill = entry.target;
                    const percent = fill.dataset.percent;
                    fill.style.width = percent + '%';
                    fill.classList.add('animated');
                    observer.unobserve(fill);
                }
            });
        }, { threshold: 0.3 });
        
        breakdownFills.forEach(fill => observer.observe(fill));
    }
})();
</script>

<?php require_once '../includes/public_footer.php'; ?>