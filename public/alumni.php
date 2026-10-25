<?php
require_once '../includes/config.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';
check_ip_security($pdo);

$page_title = 'Alumni & Tracer Study';
require_once '../includes/public_header.php';

$alumni = get_all('alumni', 'ditampilkan = 1', 'urutan ASC');
$setting = get_all('settings', 'id = 1')[0];

// Extract unique sectors and years
$sectors = [];
$years = [];
if (!empty($alumni)) {
    foreach ($alumni as $a) {
        if (!empty($a['sektor'])) {
            $sectors[] = $a['sektor'];
        }
        if (!empty($a['angkatan'])) {
            $years[] = $a['angkatan'];
        }
    }
    $sectors = array_unique($sectors);
    $years = array_unique($years);
    sort($years);
}

// Statistics
$totalAlumni = count($alumni);
$totalSectors = count($sectors);
$totalYears = count($years);
?>

<!-- Skip Link -->
<a href="#main-content" class="skip-link">Skip to main content</a>

<!-- JSON-LD for Alumni -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "CollectionPage",
  "name": "Alumni & Tracer Study - <?= sanitize($setting['site_name'] ?? 'PBSI UMMaumere') ?>",
  "description": "Jejak sukses lulusan kami di dunia kerja",
  "breadcrumb": {
    "@type": "BreadcrumbList",
    "itemListElement": [
      { "@type": "ListItem", "position": 1, "name": "Beranda", "item": "<?= PUBLIC_URL ?>" },
      { "@type": "ListItem", "position": 2, "name": "Alumni" }
    ]
  }
}
</script>

<main id="main-content" role="main">

<!-- PAGE HERO -->
<section class="page-hero">
    <h1>Alumni &amp; Tracer Study</h1>
    <p>Jejak sukses lulusan kami di dunia kerja.</p>
    <nav class="breadcrumb" aria-label="Breadcrumb">
        <a href="<?= PUBLIC_URL ?>index.php">Beranda</a>
        <span aria-hidden="true">/</span>
        <span aria-current="page">Alumni</span>
    </nav>
</section>

<!-- STATS SECTION -->
<?php if (!empty($alumni)): ?>
<section class="alumni-stats-section">
    <div class="section">
        <div class="stats-grid" role="list">
            <div class="stat-card" role="listitem" data-aos="fade-up" data-aos-delay="0">
                <i class="fa-solid fa-users" aria-hidden="true"></i>
                <h3 data-target="<?= $totalAlumni ?>">0</h3>
                <p>Total Alumni Terdaftar</p>
            </div>
            <div class="stat-card" role="listitem" data-aos="fade-up" data-aos-delay="100">
                <i class="fa-solid fa-building" aria-hidden="true"></i>
                <h3 data-target="<?= $totalSectors ?>">0</h3>
                <p>Sektor Industri</p>
            </div>
            <div class="stat-card" role="listitem" data-aos="fade-up" data-aos-delay="200">
                <i class="fa-solid fa-graduation-cap" aria-hidden="true"></i>
                <h3 data-target="<?= $totalYears ?>">0</h3>
                <p>Angkatan Lulusan</p>
            </div>
            <div class="stat-card" role="listitem" data-aos="fade-up" data-aos-delay="300">
                <i class="fa-solid fa-earth-asia" aria-hidden="true"></i>
                <h3>15+</h3>
                <p>Negara Penempatan</p>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ALUMNI SECTION -->
<?php if (!empty($alumni)): ?>
<section class="alumni-section" aria-labelledby="alumni-heading">
    <div class="section">
        <header class="sec-head">
            <span class="sec-eyebrow ey-crimson">
                <i class="fa-solid fa-user-graduate" aria-hidden="true"></i> 
                JEJAK SUKSES
            </span>
            <h2 id="alumni-heading" class="section-title">Alumni &amp; Tracer Study</h2>
            <p class="section-subtitle">Kisah inspiratif lulusan PBSI UMMaumere yang berhasil di berbagai bidang profesi.</p>
        </header>

        <!-- SEARCH & FILTER -->
        <div class="alumni-controls">
            <div class="search-box">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <input type="text" 
                       id="alumniSearch" 
                       placeholder="Cari nama alumni..." 
                       aria-label="Cari alumni">
            </div>
            
            <?php if (count($sectors) > 1): ?>
            <div class="filter-dropdown">
                <label for="sectorFilter">
                    <i class="fa-solid fa-filter" aria-hidden="true"></i> Sektor:
                </label>
                <select id="sectorFilter" aria-label="Filter berdasarkan sektor">
                    <option value="all">Semua Sektor</option>
                    <?php foreach ($sectors as $sec): ?>
                    <option value="<?= sanitize(strtolower($sec)) ?>"><?= sanitize($sec) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            
            <?php if (count($years) > 1): ?>
            <div class="filter-dropdown">
                <label for="yearFilter">
                    <i class="fa-solid fa-calendar" aria-hidden="true"></i> Angkatan:
                </label>
                <select id="yearFilter" aria-label="Filter berdasarkan angkatan">
                    <option value="all">Semua Angkatan</option>
                    <?php foreach ($years as $year): ?>
                    <option value="<?= $year ?>"><?= $year ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
        </div>

        <!-- ALUMNI GRID -->
        <div class="alumni-grid" id="alumniGrid" role="list">
            <?php foreach ($alumni as $i => $a): 
                $sector = !empty($a['sektor']) ? sanitize(strtolower($a['sektor'])) : '';
                $year = !empty($a['angkatan']) ? $a['angkatan'] : '';
            ?>
            <article class="alumni-card" 
                     data-sector="<?= $sector ?>"
                     data-year="<?= $year ?>"
                     data-name="<?= sanitize(strtolower($a['nama'])) ?>"
                     role="listitem"
                     data-aos="fade-up" 
                     data-aos-delay="<?= ($i % 6) * 80 ?>">
                <div class="alumni-head">
                    <div class="alumni-avatar" aria-hidden="true">
                        <?= strtoupper(substr($a['nama'], 0, 1)) ?>
                    </div>
                    <div class="alumni-info">
                        <h3><?= sanitize($a['nama']) ?></h3>
                        <p class="alumni-position">
                            <i class="fa-solid fa-briefcase" aria-hidden="true"></i> 
                            <?= sanitize($a['pekerjaan']) ?>
                        </p>
                        <p class="alumni-company">
                            @ <?= sanitize($a['perusahaan']) ?>
                        </p>
                    </div>
                </div>
                
                <div class="alumni-meta">
                    <span class="badge-green">
                        <i class="fa-solid fa-building" aria-hidden="true"></i> 
                        <?= sanitize($a['sektor']) ?>
                    </span>
                    <span class="badge-blue">
                        <i class="fa-solid fa-graduation-cap" aria-hidden="true"></i> 
                        Angkatan <?= sanitize($a['angkatan']) ?>
                    </span>
                </div>
                
                <blockquote class="alumni-testi">
                    <i class="fa-solid fa-quote-left" aria-hidden="true"></i>
                    <p><?= sanitize($a['testimoni']) ?></p>
                </blockquote>
                
                <?php if (!empty($a['linkedin'])): ?>
                <a href="<?= sanitize($a['linkedin']) ?>" 
                   target="_blank" 
                   rel="noopener noreferrer"
                   class="alumni-linkedin"
                   aria-label="Lihat profil LinkedIn <?= sanitize($a['nama']) ?>">
                    <i class="fa-brands fa-linkedin" aria-hidden="true"></i> 
                    Lihat Profil LinkedIn
                </a>
                <?php endif; ?>
            </article>
            <?php endforeach; ?>
        </div>

        <!-- ALUMNI STATS -->
        <div class="alumni-stats" aria-live="polite">
            <p>Menampilkan <strong id="alumni-count"><?= $totalAlumni ?></strong> dari <strong><?= $totalAlumni ?></strong> alumni</p>
        </div>
    </div>
</section>

<!-- CTA SECTION -->
<section class="alumni-cta" data-aos="fade-up" data-aos-duration="1000">
    <div class="alumni-cta-box">
        <i class="fa-solid fa-user-plus" aria-hidden="true"></i>
        <h2>Anda Alumni PBSI UMMaumere?</h2>
        <p>Bergabunglah dengan jaringan alumni kami dan bantu adik-adik tingkat dalam meniti karir.</p>
        <div class="alumni-cta-buttons">
            <a href="<?= PUBLIC_URL ?>kontak.php" class="btn-primary">
                <i class="fa-solid fa-handshake" aria-hidden="true"></i> 
                Daftar sebagai Alumni
            </a>
            <a href="<?= PUBLIC_URL ?>tracer_study.php" class="btn-secondary">
                <i class="fa-solid fa-chart-line" aria-hidden="true"></i> 
                Isi Tracer Study
            </a>
        </div>
    </div>
</section>
<?php else: ?>
<!-- EMPTY STATE -->
<section class="section">
    <div class="empty-state" style="text-align:center;padding:5rem 2rem;">
        <i class="fa-solid fa-user-graduate" style="font-size:5rem;color:#ddd;margin-bottom:1.5rem;" aria-hidden="true"></i>
        <h2 style="color:#999;margin-bottom:.5rem;">Data Alumni Belum Tersedia</h2>
        <p style="color:#aaa;max-width:500px;margin:0 auto;">Informasi alumni sedang dalam proses pengumpulan. Silakan kunjungi kembali nanti.</p>
        <a href="<?= PUBLIC_URL ?>kontak.php" class="btn-primary" style="margin-top:2rem;">
            <i class="fa-solid fa-headset" aria-hidden="true"></i> Hubungi Kami
        </a>
    </div>
</section>
<?php endif; ?>

</main>

<!-- Inline Styles for Alumni Page -->
<style>
/* Alumni Stats Section */
.alumni-stats-section {
    background: linear-gradient(135deg, var(--crimson) 0%, var(--crimson-dark) 100%);
    padding: 5rem 1.5rem;
    position: relative;
    overflow: hidden;
}
.alumni-stats-section::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-image: url('data:image/svg+xml,<svg width="100" height="100" xmlns="http://www.w3.org/2000/svg"><path d="M0 0 L100 100 M100 0 L0 100" stroke="rgba(255,255,255,0.05)" stroke-width="1"/></svg>');
    opacity: 0.5;
}
.alumni-stats-section .stats-grid {
    position: relative;
    z-index: 1;
    max-width: 1200px;
    margin: 0 auto;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 2rem;
}
.stat-card {
    background: rgba(255, 255, 255, 0.05);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 20px;
    padding: 2.5rem 2rem;
    text-align: center;
    color: #fff;
    transition: all .3s ease;
}
.stat-card:hover {
    transform: translateY(-10px);
    background: rgba(255, 255, 255, 0.1);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
}
.stat-card i {
    font-size: 3rem;
    color: var(--sky-blue);
    margin-bottom: 1rem;
    filter: drop-shadow(0 0 10px rgba(135, 206, 235, 0.5));
}
.stat-card h3 {
    font-size: 3rem;
    font-weight: 900;
    margin-bottom: 0.5rem;
    background: linear-gradient(135deg, #fff, var(--sky-blue));
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
}
.stat-card p {
    opacity: .9;
    font-size: 1rem;
    font-weight: 500;
    letter-spacing: 0.5px;
}

/* Alumni Section */
.alumni-section {
    background: var(--bg-light);
    padding: 5rem 1.5rem;
}

/* Controls */
.alumni-controls {
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

/* Alumni Grid */
.alumni-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 2rem;
}
.alumni-card {
    background: #fff;
    border-radius: 20px;
    padding: 2rem;
    box-shadow: 0 10px 35px rgba(0,0,0,.08);
    transition: all .4s cubic-bezier(.4,0,.2,1);
    position: relative;
    border-bottom: 4px solid var(--emerald);
}
.alumni-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 25px 60px rgba(0,0,0,.15);
}
.alumni-card.hidden {
    display: none;
}
.alumni-head {
    display: flex;
    gap: 1rem;
    margin-bottom: 1.5rem;
}
.alumni-avatar {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--crimson), var(--crimson-light));
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-weight: 900;
    font-size: 1.8rem;
    flex-shrink: 0;
    box-shadow: 0 8px 20px rgba(138,21,56,.3);
}
.alumni-info h3 {
    font-size: 1.15rem;
    margin-bottom: .4rem;
    color: var(--rich-black);
    font-weight: 700;
}
.alumni-position, .alumni-company {
    color: var(--text-grey);
    font-size: .88rem;
    margin-bottom: .3rem;
    display: flex;
    align-items: center;
    gap: .4rem;
}
.alumni-position i {
    color: var(--crimson);
}
.alumni-meta {
    display: flex;
    gap: .6rem;
    margin-bottom: 1.2rem;
    flex-wrap: wrap;
}
.badge-green {
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    background: rgba(46,139,87,.12);
    color: var(--emerald);
    padding: .35rem 1rem;
    border-radius: 50px;
    font-size: .78rem;
    font-weight: 700;
}
.badge-blue {
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    background: rgba(135,206,235,.15);
    color: var(--sky-blue-dark);
    padding: .35rem 1rem;
    border-radius: 50px;
    font-size: .78rem;
    font-weight: 700;
}
.alumni-testi {
    background: linear-gradient(135deg, #f8f9fa, #fff);
    padding: 1.5rem;
    border-radius: 16px;
    margin-bottom: 1.2rem;
    position: relative;
    border-left: 4px solid var(--emerald);
}
.alumni-testi i {
    position: absolute;
    top: 1rem;
    right: 1rem;
    font-size: 1.5rem;
    color: rgba(138,21,56,.15);
}
.alumni-testi p {
    color: #555;
    font-style: italic;
    line-height: 1.7;
    font-size: .92rem;
}
.alumni-linkedin {
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    padding: .7rem 1.4rem;
    background: #0a66c2;
    color: #fff;
    border-radius: 50px;
    font-weight: 600;
    font-size: .88rem;
    transition: all .3s ease;
    text-decoration: none;
}
.alumni-linkedin:hover {
    background: #004182;
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(10,102,194,.3);
}

/* Alumni Stats */
.alumni-stats {
    text-align: center;
    margin-top: 3rem;
    padding: 1.5rem;
    background: rgba(46,139,87,.05);
    border-radius: 12px;
    color: var(--text-grey);
}
.alumni-stats strong {
    color: var(--emerald);
}

/* Alumni CTA */
.alumni-cta {
    padding: 5rem 1.5rem;
    background: linear-gradient(180deg, #fff, var(--bg-light));
}
.alumni-cta-box {
    max-width: 900px;
    margin: 0 auto;
    text-align: center;
    padding: 4rem 3rem;
    background: linear-gradient(135deg, var(--emerald-dark), var(--emerald));
    border-radius: 24px;
    color: #fff;
    box-shadow: 0 30px 80px rgba(46,139,87,.35);
    position: relative;
    overflow: hidden;
}
.alumni-cta-box::before, .alumni-cta-box::after {
    content: '';
    position: absolute;
    border-radius: 50%;
    filter: blur(70px);
}
.alumni-cta-box::before {
    width: 340px;
    height: 340px;
    background: var(--sky-blue-dark);
    opacity: .30;
    top: -120px;
    left: -100px;
}
.alumni-cta-box::after {
    width: 300px;
    height: 300px;
    background: #fff;
    opacity: .10;
    bottom: -140px;
    right: -80px;
}
.alumni-cta-box i {
    font-size: 4rem;
    margin-bottom: 1.5rem;
    opacity: .9;
    position: relative;
    z-index: 1;
}
.alumni-cta-box h2 {
    font-size: clamp(1.8rem, 4vw, 2.5rem);
    margin-bottom: 1rem;
    font-weight: 900;
    position: relative;
    z-index: 1;
}
.alumni-cta-box p {
    color: rgba(255,255,255,.9);
    max-width: 640px;
    margin: 0 auto 2.2rem;
    line-height: 1.8;
    position: relative;
    z-index: 1;
}
.alumni-cta-buttons {
    display: flex;
    gap: 1rem;
    justify-content: center;
    flex-wrap: wrap;
    position: relative;
    z-index: 1;
}
.alumni-cta-buttons .btn-primary {
    background: #fff;
    color: var(--emerald);
    margin-top: 0;
}
.alumni-cta-buttons .btn-secondary {
    border-color: #fff;
    color: #fff;
    margin-top: 0;
}
.alumni-cta-buttons .btn-secondary:hover {
    background: #fff;
    color: var(--emerald);
}

/* Responsive */
@media (max-width: 900px) {
    .alumni-grid {
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 1.5rem;
    }
    .alumni-controls {
        flex-direction: column;
    }
    .search-box {
        max-width: 100%;
    }
    .alumni-cta-box {
        padding: 3rem 2rem;
    }
}
@media (max-width: 560px) {
    .alumni-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- Alumni Page JavaScript -->
<script>
(function() {
    'use strict';
    
    const alumniCards = document.querySelectorAll('.alumni-card');
    const searchInput = document.getElementById('alumniSearch');
    const sectorFilter = document.getElementById('sectorFilter');
    const yearFilter = document.getElementById('yearFilter');
    const alumniCountEl = document.getElementById('alumni-count');
    
    function filterAlumni() {
        const searchTerm = searchInput ? searchInput.value.toLowerCase() : '';
        const sectorValue = sectorFilter ? sectorFilter.value : 'all';
        const yearValue = yearFilter ? yearFilter.value : 'all';
        
        let visibleCount = 0;
        
        alumniCards.forEach(card => {
            const name = card.dataset.name || '';
            const sector = card.dataset.sector || '';
            const year = card.dataset.year || '';
            
            const matchesSearch = name.includes(searchTerm);
            const matchesSector = sectorValue === 'all' || sector === sectorValue;
            const matchesYear = yearValue === 'all' || year === yearValue;
            
            if (matchesSearch && matchesSector && matchesYear) {
                card.classList.remove('hidden');
                visibleCount++;
            } else {
                card.classList.add('hidden');
            }
        });
        
        if (alumniCountEl) {
            alumniCountEl.textContent = visibleCount;
        }
    }
    
    if (searchInput) {
        searchInput.addEventListener('input', filterAlumni);
    }
    
    if (sectorFilter) {
        sectorFilter.addEventListener('change', filterAlumni);
    }
    
    if (yearFilter) {
        yearFilter.addEventListener('change', filterAlumni);
    }
})();
</script>

<?php require_once '../includes/public_footer.php'; ?>