<?php
require_once '../includes/config.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';
check_ip_security($pdo);

$page_title = 'Publikasi Ilmiah';
require_once '../includes/public_header.php';

$pub = get_all('publikasi', null, 'tahun DESC');
$setting = get_all('settings', 'id = 1')[0];

// Extract unique years and indexes
$years = [];
$indexes = [];
if (!empty($pub)) {
    foreach ($pub as $p) {
        if (!empty($p['tahun'])) $years[] = $p['tahun'];
        if (!empty($p['indeks'])) $indexes[] = $p['indeks'];
    }
    $years = array_unique($years);
    $indexes = array_unique($indexes);
    rsort($years);
}

// Statistics
$totalPub = count($pub);
$byIndex = [];
foreach ($pub as $p) {
    $idx = $p['indeks'] ?? 'Lainnya';
    $byIndex[$idx] = ($byIndex[$idx] ?? 0) + 1;
}

// Group by year
$byYear = [];
foreach ($pub as $p) {
    $year = $p['tahun'] ?? 'Tidak Diketahui';
    $byYear[$year][] = $p;
}
?>

<!-- JSON-LD for ScholarlyArticle -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "CollectionPage",
  "name": "Publikasi Ilmiah - <?= sanitize($setting['site_name'] ?? 'PBSI UMMaumere') ?>",
  "description": "Karya riset dosen pada jurnal bereputasi nasional dan internasional",
  "breadcrumb": {
    "@type": "BreadcrumbList",
    "itemListElement": [
      { "@type": "ListItem", "position": 1, "name": "Beranda", "item": "<?= PUBLIC_URL ?>" },
      { "@type": "ListItem", "position": 2, "name": "Publikasi" }
    ]
  }
}
</script>

<main id="main-content" role="main">

<!-- PAGE HERO -->
<section class="page-hero">
    <h1>Publikasi Ilmiah</h1>
    <p>Karya riset dosen pada jurnal bereputasi nasional dan internasional.</p>
    <nav class="breadcrumb" aria-label="Breadcrumb">
        <a href="<?= PUBLIC_URL ?>index.php">Beranda</a>
        <span aria-hidden="true">/</span>
        <span aria-current="page">Publikasi</span>
    </nav>
</section>

<!-- PUBLIKASI SECTION -->
<?php if (!empty($pub)): ?>
<section class="pub-section" aria-labelledby="pub-heading">
    <div class="section">
        <header class="sec-head">
            <span class="sec-eyebrow ey-crimson">
                <i class="fa-solid fa-flask" aria-hidden="true"></i> 
                KARYA ILMIAH
            </span>
            <h2 class="section-title" id="pub-heading">Publikasi Ilmiah</h2>
            <p class="section-subtitle">Kontribusi dosen PBSI UMMaumere dalam pengembangan ilmu pengetahuan melalui publikasi bereputasi.</p>
        </header>

        <!-- STATS GRID -->
        <div class="pub-stats" role="list">
            <div class="pub-stat-card" role="listitem" data-aos="fade-up" data-aos-delay="0">
                <i class="fa-solid fa-file-lines" aria-hidden="true"></i>
                <h3 data-target="<?= $totalPub ?>">0</h3>
                <p>Total Publikasi</p>
            </div>
            <?php foreach (array_slice($byIndex, 0, 3) as $idx => $count): ?>
            <div class="pub-stat-card" role="listitem" data-aos="fade-up" data-aos-delay="<?= (array_search($idx, array_keys($byIndex)) + 1) * 100 ?>">
                <i class="fa-solid fa-award" aria-hidden="true"></i>
                <h3 data-target="<?= $count ?>">0</h3>
                <p><?= sanitize($idx) ?></p>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- SEARCH & FILTER -->
        <div class="pub-controls">
            <div class="search-box">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <input type="text" 
                       id="pubSearch" 
                       placeholder="Cari judul atau penulis..." 
                       aria-label="Cari publikasi">
            </div>
            
            <?php if (count($years) > 1): ?>
            <div class="filter-dropdown">
                <label for="yearFilter">
                    <i class="fa-solid fa-calendar" aria-hidden="true"></i> Tahun:
                </label>
                <select id="yearFilter" aria-label="Filter berdasarkan tahun">
                    <option value="all">Semua Tahun</option>
                    <?php foreach ($years as $year): ?>
                    <option value="<?= $year ?>"><?= $year ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            
            <?php if (count($indexes) > 1): ?>
            <div class="filter-dropdown">
                <label for="indexFilter">
                    <i class="fa-solid fa-filter" aria-hidden="true"></i> Indeks:
                </label>
                <select id="indexFilter" aria-label="Filter berdasarkan indeks">
                    <option value="all">Semua Indeks</option>
                    <?php foreach ($indexes as $idx): ?>
                    <option value="<?= sanitize(strtolower($idx)) ?>"><?= sanitize($idx) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
        </div>

        <!-- PUBLIKASI LIST BY YEAR -->
        <div class="pub-list" id="pubList">
            <?php foreach ($byYear as $year => $pubs): ?>
            <div class="pub-year-group" data-year="<?= $year ?>">
                <div class="pub-year-header">
                    <span class="year-badge"><?= $year ?></span>
                    <span class="year-count"><?= count($pubs) ?> publikasi</span>
                </div>
                
                <div class="pub-items">
                    <?php foreach ($pubs as $i => $p): 
                        $index = !empty($p['indeks']) ? sanitize(strtolower($p['indeks'])) : '';
                        $searchText = strtolower($p['judul'] . ' ' . $p['penulis']);
                    ?>
                    <article class="pub-item" 
                             data-index="<?= $index ?>"
                             data-search="<?= sanitize($searchText) ?>"
                             data-aos="fade-up" 
                             data-aos-delay="<?= ($i % 6) * 60 ?>">
                        
                        <div class="pub-info">
                            <div class="pub-header">
                                <span class="badge-index idx-<?= $index ?>">
                                    <i class="fa-solid fa-medal" aria-hidden="true"></i>
                                    <?= sanitize($p['indeks']) ?>
                                </span>
                                <span class="pub-year-inline">
                                    <i class="fa-regular fa-calendar" aria-hidden="true"></i>
                                    <?= $p['tahun'] ?>
                                </span>
                            </div>
                            
                            <h3 class="pub-title"><?= sanitize($p['judul']) ?></h3>
                            
                            <p class="pub-authors">
                                <i class="fa-solid fa-users" aria-hidden="true"></i>
                                <?= sanitize($p['penulis']) ?>
                            </p>
                            
                            <p class="pub-journal">
                                <i class="fa-solid fa-book" aria-hidden="true"></i>
                                <em><?= sanitize($p['nama_jurnal']) ?></em>
                                <?php if (!empty($p['volume'])): ?>
                                , Vol. <?= sanitize($p['volume']) ?>
                                <?php endif; ?>
                                <?php if (!empty($p['nomor'])): ?>
                                (<?= sanitize($p['nomor']) ?>)
                                <?php endif; ?>
                            </p>
                            
                            <div class="pub-actions">
                                <?php if (!empty($p['doi'])): ?>
                                <a href="https://doi.org/<?= sanitize($p['doi']) ?>" 
                                   target="_blank" 
                                   rel="noopener noreferrer"
                                   class="pub-link doi"
                                   aria-label="DOI Link">
                                    <i class="fa-solid fa-link" aria-hidden="true"></i>
                                    DOI
                                </a>
                                <?php endif; ?>
                                
                                <?php if (!empty($p['url'])): ?>
                                <a href="<?= sanitize($p['url']) ?>" 
                                   target="_blank" 
                                   rel="noopener noreferrer"
                                   class="pub-link url"
                                   aria-label="Full Text">
                                    <i class="fa-solid fa-file-pdf" aria-hidden="true"></i>
                                    Full Text
                                </a>
                                <?php endif; ?>
                                
                                <button class="pub-link citation" 
                                        onclick="copyCitation(<?= htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8') ?>)"
                                        aria-label="Copy citation">
                                    <i class="fa-solid fa-quote-left" aria-hidden="true"></i>
                                    Cite
                                </button>
                            </div>
                        </div>
                    </article>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- PUB COUNTER -->
        <div class="pub-counter" aria-live="polite">
            <p>Menampilkan <strong id="visible-count"><?= $totalPub ?></strong> dari <strong><?= $totalPub ?></strong> publikasi</p>
        </div>
    </div>
</section>

<!-- CTA SECTION -->
<section class="pub-cta" data-aos="fade-up" data-aos-duration="1000">
    <div class="pub-cta-box">
        <i class="fa-solid fa-handshake-angle" aria-hidden="true"></i>
        <h2>Tertarik Berkolaborasi?</h2>
        <p>Kami terbuka untuk kerja sama riset, publikasi bersama, dan pertukaran pengetahuan.</p>
        <div class="pub-cta-buttons">
            <a href="<?= PUBLIC_URL ?>kontak.php" class="btn-primary">
                <i class="fa-solid fa-envelope" aria-hidden="true"></i> 
                Hubungi Peneliti
            </a>
            <a href="<?= PUBLIC_URL ?>dosen.php" class="btn-secondary">
                <i class="fa-solid fa-users" aria-hidden="true"></i> 
                Lihat Profil Dosen
            </a>
        </div>
    </div>
</section>
<?php else: ?>
<!-- EMPTY STATE -->
<section class="section">
    <div class="empty-state" style="text-align:center;padding:5rem 2rem;">
        <i class="fa-solid fa-flask" style="font-size:5rem;color:#ddd;margin-bottom:1.5rem;" aria-hidden="true"></i>
        <h2 style="color:#999;margin-bottom:.5rem;">Publikasi Belum Tersedia</h2>
        <p style="color:#aaa;max-width:500px;margin:0 auto;">Data publikasi ilmiah sedang dalam proses pengumpulan. Silakan kunjungi kembali nanti.</p>
        <a href="<?= PUBLIC_URL ?>dosen.php" class="btn-primary" style="margin-top:2rem;">
            <i class="fa-solid fa-users" aria-hidden="true"></i> Lihat Profil Dosen
        </a>
    </div>
</section>
<?php endif; ?>

</main>

<!-- Inline Styles for Publikasi Page -->
<style>
/* Pub Section */
.pub-section {
    background: var(--bg-light);
    padding: 5rem 1.5rem;
}

/* Stats */
.pub-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1.5rem;
    margin-bottom: 3rem;
}
.pub-stat-card {
    background: #fff;
    border-radius: 20px;
    padding: 2rem;
    text-align: center;
    box-shadow: 0 10px 35px rgba(0,0,0,.08);
    transition: all .3s ease;
    border-top: 4px solid var(--crimson);
}
.pub-stat-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 20px 50px rgba(0,0,0,.15);
}
.pub-stat-card i {
    font-size: 2.5rem;
    color: var(--crimson);
    margin-bottom: 1rem;
}
.pub-stat-card h3 {
    font-size: 2.5rem;
    font-weight: 900;
    color: var(--rich-black);
    margin-bottom: .5rem;
}
.pub-stat-card p {
    color: var(--text-grey);
    font-size: .95rem;
    font-weight: 600;
}

/* Controls */
.pub-controls {
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
    background: #fff;
    transition: all .3s ease;
}
.filter-dropdown select:focus {
    outline: none;
    border-color: var(--sky-blue);
}

/* Year Group */
.pub-year-group {
    margin-bottom: 3rem;
}
.pub-year-group.hidden {
    display: none;
}
.pub-year-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid #e0e0e0;
}
.year-badge {
    background: linear-gradient(135deg, var(--crimson), var(--crimson-light));
    color: #fff;
    padding: .6rem 1.5rem;
    border-radius: 50px;
    font-size: 1.2rem;
    font-weight: 900;
    box-shadow: 0 8px 20px rgba(138,21,56,.3);
}
.year-count {
    color: var(--text-grey);
    font-size: .95rem;
    font-weight: 600;
}

/* Pub Items */
.pub-items {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}
.pub-item {
    background: #fff;
    border-radius: 20px;
    padding: 2rem;
    box-shadow: 0 10px 35px rgba(0,0,0,.08);
    transition: all .4s cubic-bezier(.4,0,.2,1);
    border-left: 5px solid var(--crimson);
}
.pub-item:hover {
    transform: translateX(8px);
    box-shadow: 0 20px 50px rgba(0,0,0,.12);
}
.pub-item.hidden {
    display: none;
}

/* Pub Header */
.pub-header {
    display: flex;
    gap: 1rem;
    margin-bottom: 1rem;
    flex-wrap: wrap;
}
.badge-index {
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    padding: .4rem 1.2rem;
    border-radius: 50px;
    font-size: .78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .8px;
}
.idx-scopus {
    background: rgba(231,76,60,.15);
    color: #e74c3c;
}
.idx-sinta {
    background: rgba(46,139,87,.15);
    color: var(--emerald);
}
.idx-garuda {
    background: rgba(135,206,235,.15);
    color: var(--sky-blue-dark);
}
.idx-crossref {
    background: rgba(155,89,182,.15);
    color: #9b59b6;
}
.pub-year-inline {
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    color: var(--text-grey);
    font-size: .88rem;
}

/* Pub Info */
.pub-title {
    font-size: 1.3rem;
    margin-bottom: 1rem;
    color: var(--rich-black);
    font-weight: 700;
    line-height: 1.4;
}
.pub-authors, .pub-journal {
    color: var(--text-grey);
    font-size: .95rem;
    margin-bottom: .8rem;
    display: flex;
    align-items: flex-start;
    gap: .6rem;
}
.pub-authors i, .pub-journal i {
    color: var(--crimson);
    margin-top: 4px;
}
.pub-journal em {
    font-style: italic;
}

/* Actions */
.pub-actions {
    display: flex;
    gap: .8rem;
    margin-top: 1.5rem;
    flex-wrap: wrap;
}
.pub-link {
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    padding: .6rem 1.4rem;
    border-radius: 50px;
    font-size: .88rem;
    font-weight: 600;
    transition: all .3s ease;
    text-decoration: none;
    border: none;
    cursor: pointer;
}
.pub-link.doi {
    background: rgba(231,76,60,.1);
    color: #e74c3c;
}
.pub-link.doi:hover {
    background: #e74c3c;
    color: #fff;
}
.pub-link.url {
    background: rgba(46,139,87,.1);
    color: var(--emerald);
}
.pub-link.url:hover {
    background: var(--emerald);
    color: #fff;
}
.pub-link.citation {
    background: rgba(135,206,235,.1);
    color: var(--sky-blue-dark);
}
.pub-link.citation:hover {
    background: var(--sky-blue);
    color: #fff;
}

/* Counter */
.pub-counter {
    text-align: center;
    margin-top: 3rem;
    padding: 1.5rem;
    background: rgba(138,21,56,.05);
    border-radius: 12px;
    color: var(--text-grey);
}
.pub-counter strong {
    color: var(--crimson);
}

/* CTA */
.pub-cta {
    padding: 5rem 1.5rem;
    background: linear-gradient(180deg, #fff, var(--bg-light));
}
.pub-cta-box {
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
.pub-cta-box::before, .pub-cta-box::after {
    content: '';
    position: absolute;
    border-radius: 50%;
    filter: blur(70px);
}
.pub-cta-box::before {
    width: 340px;
    height: 340px;
    background: var(--sky-blue-dark);
    opacity: .30;
    top: -120px;
    left: -100px;
}
.pub-cta-box::after {
    width: 300px;
    height: 300px;
    background: #fff;
    opacity: .10;
    bottom: -140px;
    right: -80px;
}
.pub-cta-box i {
    font-size: 4rem;
    margin-bottom: 1.5rem;
    opacity: .9;
    position: relative;
    z-index: 1;
}
.pub-cta-box h2 {
    font-size: clamp(1.8rem, 4vw, 2.5rem);
    margin-bottom: 1rem;
    font-weight: 900;
    position: relative;
    z-index: 1;
}
.pub-cta-box p {
    color: rgba(255,255,255,.9);
    max-width: 640px;
    margin: 0 auto 2.2rem;
    line-height: 1.8;
    position: relative;
    z-index: 1;
}
.pub-cta-buttons {
    display: flex;
    gap: 1rem;
    justify-content: center;
    flex-wrap: wrap;
    position: relative;
    z-index: 1;
}
.pub-cta-buttons .btn-primary {
    background: #fff;
    color: var(--emerald);
    margin-top: 0;
}
.pub-cta-buttons .btn-secondary {
    border-color: #fff;
    color: #fff;
    margin-top: 0;
}
.pub-cta-buttons .btn-secondary:hover {
    background: #fff;
    color: var(--emerald);
}

/* Responsive */
@media (max-width: 900px) {
    .pub-controls {
        flex-direction: column;
    }
    .search-box {
        max-width: 100%;
    }
    .pub-cta-box {
        padding: 3rem 1.5rem;
    }
}
</style>

<!-- Publikasi Page JavaScript -->
<script>
(function() {
    'use strict';
    
    const pubItems = document.querySelectorAll('.pub-item');
    const pubYearGroups = document.querySelectorAll('.pub-year-group');
    const searchInput = document.getElementById('pubSearch');
    const yearFilter = document.getElementById('yearFilter');
    const indexFilter = document.getElementById('indexFilter');
    const visibleCountEl = document.getElementById('visible-count');
    
    function filterPub() {
        const searchTerm = searchInput ? searchInput.value.toLowerCase() : '';
        const yearValue = yearFilter ? yearFilter.value : 'all';
        const indexValue = indexFilter ? indexFilter.value : 'all';
        
        let visibleCount = 0;
        
        pubYearGroups.forEach(group => {
            const year = group.dataset.year;
            const matchesYear = yearValue === 'all' || year === yearValue;
            
            if (!matchesYear) {
                group.classList.add('hidden');
                return;
            }
            
            const items = group.querySelectorAll('.pub-item');
            let groupVisible = 0;
            
            items.forEach(item => {
                const searchText = item.dataset.search || '';
                const index = item.dataset.index || '';
                
                const matchesSearch = searchText.includes(searchTerm);
                const matchesIndex = indexValue === 'all' || index === indexValue;
                
                if (matchesSearch && matchesIndex) {
                    item.classList.remove('hidden');
                    groupVisible++;
                    visibleCount++;
                } else {
                    item.classList.add('hidden');
                }
            });
            
            if (groupVisible > 0) {
                group.classList.remove('hidden');
            } else {
                group.classList.add('hidden');
            }
        });
        
        if (visibleCountEl) {
            visibleCountEl.textContent = visibleCount;
        }
    }
    
    if (searchInput) searchInput.addEventListener('input', filterPub);
    if (yearFilter) yearFilter.addEventListener('change', filterPub);
    if (indexFilter) indexFilter.addEventListener('change', filterPub);
    
    // Copy Citation
    window.copyCitation = function(pub) {
        const citation = `${pub.penulis} (${pub.tahun}). ${pub.judul}. ${pub.nama_jurnal}${pub.volume ? ', ' + pub.volume : ''}${pub.nomor ? '(' + pub.nomor + ')' : ''}.${pub.doi ? ' https://doi.org/' + pub.doi : ''}`;
        
        navigator.clipboard.writeText(citation).then(() => {
            const btn = event.target.closest('.pub-link');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fa-solid fa-check"></i> Copied!';
            btn.style.background = 'var(--emerald)';
            btn.style.color = '#fff';
            
            setTimeout(() => {
                btn.innerHTML = originalText;
                btn.style.background = '';
                btn.style.color = '';
            }, 2000);
        });
    };
})();
</script>

<?php require_once '../includes/public_footer.php'; ?>