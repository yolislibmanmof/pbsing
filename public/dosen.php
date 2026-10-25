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

$page_title = 'Dosen & Pengajar';
require_once '../includes/public_header.php';

$dosen = get_all('dosen', 'aktif = 1', 'urutan ASC');
$setting = get_all('settings', 'id = 1')[0];

// Extract unique expertise for filter
$expertises = [];
if (!empty($dosen)) {
    foreach ($dosen as $d) {
        if (!empty($d['bidang_keahlian'])) {
            $expertises[] = $d['bidang_keahlian'];
        }
    }
    $expertises = array_unique($expertises);
}

// Generate JSON-LD for all lecturers
$jsonLdPersons = [];
foreach ($dosen as $d) {
    $person = [
        "@type" => "Person",
        "name" => sanitize(trim(($d['gelar_depan'] ?? '') . ' ' . $d['nama'] . ', ' . ($d['gelar_belakang'] ?? ''))),
        "jobTitle" => sanitize($d['bidang_keahlian'] ?? ''),
        "email" => sanitize($d['email'] ?? ''),
        "worksFor" => [
            "@type" => "EducationalOrganization",
            "name" => sanitize($setting['site_name'] ?? 'PBSI UMMaumere')
        ]
    ];
    if (!empty($d['link_scholar'])) {
        $person["sameAs"] = [sanitize($d['link_scholar'])];
    }
    $jsonLdPersons[] = $person;
}
?>

<!-- Skip Link -->
<a href="#main-content" class="skip-link">Skip to main content</a>

<!-- JSON-LD for Person (Multiple) -->
<script type="application/ld+json">
<?= json_encode($jsonLdPersons, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?>
</script>

<main id="main-content" role="main">

<!-- PAGE HERO -->
<section class="page-hero">
    <h1>Dosen &amp; Pengajar</h1>
    <p>Tenaga pengajar berpendidikan S2-S3 dari dalam dan luar negeri.</p>
    <nav class="breadcrumb" aria-label="Breadcrumb">
        <a href="<?= PUBLIC_URL ?>index.php">Beranda</a>
        <span aria-hidden="true">/</span>
        <span aria-current="page">Dosen &amp; Pengajar</span>
    </nav>
</section>

<!-- DOSEN SECTION -->
<?php if (!empty($dosen)): ?>
<section class="dosen-section" aria-labelledby="dosen-heading">
    <div class="section">
        <header class="sec-head">
            <span class="sec-eyebrow ey-crimson">
                <i class="fa-solid fa-chalkboard-user" aria-hidden="true"></i> 
                TIM AKADEMIK
            </span>
            <h2 id="dosen-heading" class="section-title">Dosen &amp; Pengajar</h2>
            <p class="section-subtitle">Para pendidik berpengalaman yang berdedikasi membimbing mahasiswa menuju kesuksesan akademik dan profesional.</p>
        </header>

        <!-- SEARCH & FILTER -->
        <div class="dosen-controls">
            <div class="search-box">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <input type="text" 
                       id="dosenSearch" 
                       placeholder="Cari nama dosen..." 
                       aria-label="Cari dosen">
            </div>
            
            <?php if (count($expertises) > 1): ?>
            <div class="filter-dropdown">
                <label for="expertiseFilter">
                    <i class="fa-solid fa-filter" aria-hidden="true"></i> Filter:
                </label>
                <select id="expertiseFilter" aria-label="Filter berdasarkan keahlian">
                    <option value="all">Semua Keahlian</option>
                    <?php foreach ($expertises as $exp): ?>
                    <option value="<?= sanitize(strtolower($exp)) ?>"><?= sanitize($exp) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
        </div>

        <!-- DOSEN GRID -->
        <div class="dosen-grid" id="dosenGrid" role="list">
            <?php foreach ($dosen as $i => $d): 
                $expertise = !empty($d['bidang_keahlian']) ? sanitize(strtolower($d['bidang_keahlian'])) : '';
                $namaLengkap = sanitize(trim(($d['gelar_depan'] ?? '') . ' ' . $d['nama'] . ', ' . ($d['gelar_belakang'] ?? '')));
            ?>
            <article class="dosen-card" 
                     data-expertise="<?= $expertise ?>"
                     data-name="<?= sanitize(strtolower($d['nama'])) ?>"
                     role="listitem"
                     data-aos="fade-up" 
                     data-aos-delay="<?= ($i % 6) * 80 ?>">
                <div class="dosen-photo" 
                     style="background-image:url('<?= img_url($d['foto'] ?? '') ?>')"
                     role="img"
                     aria-label="Foto <?= $namaLengkap ?>">
                    <div class="dosen-overlay">
                        <div class="dosen-socials">
                            <?php if (!empty($d['email'])): ?>
                            <a href="mailto:<?= sanitize($d['email']) ?>" 
                               title="Email <?= $namaLengkap ?>"
                               aria-label="Kirim email ke <?= $namaLengkap ?>">
                                <i class="fa-solid fa-envelope" aria-hidden="true"></i>
                            </a>
                            <?php endif; ?>
                            <?php if (!empty($d['link_scholar'])): ?>
                            <a href="<?= sanitize($d['link_scholar']) ?>" 
                               target="_blank" 
                               rel="noopener noreferrer"
                               title="Google Scholar Profile"
                               aria-label="Lihat profil Google Scholar <?= $namaLengkap ?>">
                                <i class="fa-solid fa-graduation-cap" aria-hidden="true"></i>
                            </a>
                            <?php endif; ?>
                            <?php if (!empty($d['link_researchgate'])): ?>
                            <a href="<?= sanitize($d['link_researchgate']) ?>" 
                               target="_blank" 
                               rel="noopener noreferrer"
                               title="ResearchGate Profile"
                               aria-label="Lihat profil ResearchGate <?= $namaLengkap ?>">
                                <i class="fa-brands fa-researchgate" aria-hidden="true"></i>
                            </a>
                            <?php endif; ?>
                            <?php if (!empty($d['link_linkedin'])): ?>
                            <a href="<?= sanitize($d['link_linkedin']) ?>" 
                               target="_blank" 
                               rel="noopener noreferrer"
                               title="LinkedIn Profile"
                               aria-label="Lihat profil LinkedIn <?= $namaLengkap ?>">
                                <i class="fa-brands fa-linkedin" aria-hidden="true"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="dosen-info">
                    <h3><?= $namaLengkap ?></h3>
                    <span class="badge-blue"><?= sanitize($d['bidang_keahlian']) ?></span>
                    <?php if (!empty($d['email'])): ?>
                    <p class="dosen-mail">
                        <i class="fa-solid fa-envelope" aria-hidden="true"></i> 
                        <a href="mailto:<?= sanitize($d['email']) ?>"><?= sanitize($d['email']) ?></a>
                    </p>
                    <?php endif; ?>
                    <?php if (!empty($d['pendidikan'])): ?>
                    <p class="dosen-edu">
                        <i class="fa-solid fa-graduation-cap" aria-hidden="true"></i> 
                        <?= sanitize($d['pendidikan']) ?>
                    </p>
                    <?php endif; ?>
                    <?php if (!empty($d['bio'])): ?>
                    <button class="dosen-more" onclick="openDosenModal(<?= $i ?>)" aria-label="Lihat profil lengkap <?= $namaLengkap ?>">
                        Lihat Profil Lengkap <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                    </button>
                    <?php endif; ?>
                </div>
            </article>
            <?php endforeach; ?>
        </div>

        <!-- DOSEN STATS -->
        <div class="dosen-stats" aria-live="polite">
            <p>Menampilkan <strong id="dosen-count"><?= count($dosen) ?></strong> dari <strong><?= count($dosen) ?></strong> dosen</p>
        </div>
    </div>
</section>

<!-- DOSEN MODAL -->
<div class="dosen-modal" id="dosenModal" role="dialog" aria-modal="true" aria-labelledby="modal-title" aria-hidden="true">
    <div class="modal-backdrop" onclick="closeDosenModal()"></div>
    <div class="modal-content">
        <button class="modal-close" onclick="closeDosenModal()" aria-label="Tutup">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
        </button>
        <div class="modal-body">
            <div class="modal-photo" id="modal-photo"></div>
            <div class="modal-info">
                <h2 id="modal-title"></h2>
                <span class="badge-blue" id="modal-expertise"></span>
                <div class="modal-details" id="modal-details"></div>
                <div class="modal-bio" id="modal-bio"></div>
                <div class="modal-socials" id="modal-socials"></div>
            </div>
        </div>
    </div>
</div>
<?php else: ?>
<!-- EMPTY STATE -->
<section class="section">
    <div class="empty-state" style="text-align:center;padding:5rem 2rem;">
        <i class="fa-solid fa-users" style="font-size:5rem;color:#ddd;margin-bottom:1.5rem;" aria-hidden="true"></i>
        <h2 style="color:#999;margin-bottom:.5rem;">Data Dosen Belum Tersedia</h2>
        <p style="color:#aaa;max-width:500px;margin:0 auto;">Informasi dosen sedang dalam proses pembaruan. Silakan kunjungi kembali nanti.</p>
        <a href="<?= PUBLIC_URL ?>kontak.php" class="btn-primary" style="margin-top:2rem;">
            <i class="fa-solid fa-headset" aria-hidden="true"></i> Hubungi Kami
        </a>
    </div>
</section>
<?php endif; ?>

</main>

<!-- Inline Styles for Dosen Page -->
<style>
/* Dosen Section */
.dosen-section {
    background: var(--bg-light);
    padding: 5rem 1.5rem;
}

/* Search & Filter Controls */
.dosen-controls {
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

/* Dosen Grid */
.dosen-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 2rem;
}
.dosen-card {
    background: #fff;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 10px 35px rgba(0,0,0,.08);
    transition: all .4s cubic-bezier(.4,0,.2,1);
    position: relative;
}
.dosen-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 25px 60px rgba(0,0,0,.15);
}
.dosen-card.hidden {
    display: none;
}
.dosen-photo {
    height: 320px;
    background-size: cover;
    background-position: center top;
    background-color: #ddd;
    position: relative;
    overflow: hidden;
}
.dosen-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(to top, rgba(138,21,56,.95) 0%, transparent 60%);
    opacity: 0;
    transition: opacity .4s ease;
    display: flex;
    align-items: flex-end;
    justify-content: center;
    padding-bottom: 2rem;
}
.dosen-card:hover .dosen-overlay {
    opacity: 1;
}
.dosen-socials {
    display: flex;
    gap: .8rem;
}
.dosen-socials a {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    background: #fff;
    color: var(--crimson);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    transition: all .3s ease;
    transform: translateY(20px);
    opacity: 0;
}
.dosen-card:hover .dosen-socials a {
    transform: translateY(0);
    opacity: 1;
}
.dosen-socials a:hover {
    background: var(--sky-blue);
    color: #fff;
    transform: scale(1.1);
}
.dosen-info {
    padding: 1.8rem;
    text-align: center;
}
.dosen-info h3 {
    font-size: 1.1rem;
    margin-bottom: .8rem;
    color: var(--rich-black);
    font-weight: 700;
    line-height: 1.4;
}
.badge-blue {
    display: inline-block;
    background: rgba(135,206,235,.15);
    color: var(--sky-blue-dark);
    font-size: .8rem;
    font-weight: 600;
    padding: .4rem 1.2rem;
    border-radius: 50px;
    margin-bottom: 1rem;
}
.dosen-mail, .dosen-edu {
    color: var(--text-grey);
    font-size: .88rem;
    margin-bottom: .6rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: .5rem;
}
.dosen-mail a {
    color: var(--crimson);
    text-decoration: none;
    transition: color .3s ease;
}
.dosen-mail a:hover {
    color: var(--sky-blue-dark);
}
.dosen-more {
    margin-top: 1rem;
    padding: .6rem 1.4rem;
    background: linear-gradient(135deg, var(--crimson), var(--crimson-light));
    color: #fff;
    border: none;
    border-radius: 50px;
    font-weight: 600;
    font-size: .85rem;
    cursor: pointer;
    transition: all .3s ease;
    display: inline-flex;
    align-items: center;
    gap: .5rem;
}
.dosen-more:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(138,21,56,.3);
}

/* Dosen Stats */
.dosen-stats {
    text-align: center;
    margin-top: 3rem;
    padding: 1.5rem;
    background: rgba(138,21,56,.05);
    border-radius: 12px;
    color: var(--text-grey);
}
.dosen-stats strong {
    color: var(--crimson);
}

/* Dosen Modal */
.dosen-modal {
    position: fixed;
    inset: 0;
    z-index: 10000;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    visibility: hidden;
    transition: all .3s ease;
    padding: 2rem;
}
.dosen-modal.active {
    opacity: 1;
    visibility: visible;
}
.modal-backdrop {
    position: absolute;
    inset: 0;
    background: rgba(0,0,0,.85);
    backdrop-filter: blur(10px);
}
.modal-content {
    position: relative;
    z-index: 1;
    background: #fff;
    border-radius: 24px;
    max-width: 900px;
    width: 100%;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 30px 80px rgba(0,0,0,.4);
}
.modal-close {
    position: absolute;
    top: 1rem;
    right: 1rem;
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: rgba(0,0,0,.1);
    border: none;
    color: #333;
    font-size: 1.5rem;
    cursor: pointer;
    transition: all .3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 2;
}
.modal-close:hover {
    background: var(--crimson);
    color: #fff;
    transform: rotate(90deg);
}
.modal-body {
    display: grid;
    grid-template-columns: 1fr 1.5fr;
    gap: 2rem;
    padding: 2.5rem;
}
.modal-photo {
    width: 100%;
    aspect-ratio: 3/4;
    background-size: cover;
    background-position: center top;
    border-radius: 16px;
    box-shadow: 0 15px 40px rgba(0,0,0,.15);
}
.modal-info h2 {
    font-size: 1.8rem;
    margin-bottom: 1rem;
    color: var(--rich-black);
}
.modal-details {
    margin: 1.5rem 0;
    display: flex;
    flex-direction: column;
    gap: .8rem;
}
.modal-details p {
    display: flex;
    align-items: center;
    gap: .8rem;
    color: var(--text-grey);
    font-size: .95rem;
}
.modal-details i {
    color: var(--crimson);
    width: 20px;
}
.modal-bio {
    margin: 1.5rem 0;
    color: var(--text-grey);
    line-height: 1.8;
}
.modal-socials {
    display: flex;
    gap: .8rem;
    margin-top: 1.5rem;
}
.modal-socials a {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: var(--bg-light);
    color: var(--rich-black);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    transition: all .3s ease;
}
.modal-socials a:hover {
    background: var(--crimson);
    color: #fff;
    transform: translateY(-3px);
}

/* Responsive */
@media (max-width: 900px) {
    .dosen-grid {
        grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
        gap: 1.5rem;
    }
    .modal-body {
        grid-template-columns: 1fr;
        padding: 2rem 1.5rem;
    }
    .modal-photo {
        aspect-ratio: 1;
        max-height: 300px;
    }
}
@media (max-width: 560px) {
    .dosen-grid {
        grid-template-columns: 1fr;
    }
    .dosen-controls {
        flex-direction: column;
    }
    .search-box {
        max-width: 100%;
    }
}
</style>

<!-- Dosen Page JavaScript -->
<script>
(function() {
    'use strict';
    
    const dosenCards = document.querySelectorAll('.dosen-card');
    const searchInput = document.getElementById('dosenSearch');
    const expertiseFilter = document.getElementById('expertiseFilter');
    const dosenCountEl = document.getElementById('dosen-count');
    
    function filterDosen() {
        const searchTerm = searchInput ? searchInput.value.toLowerCase() : '';
        const expertiseValue = expertiseFilter ? expertiseFilter.value : 'all';
        
        let visibleCount = 0;
        
        dosenCards.forEach(card => {
            const name = card.dataset.name || '';
            const expertise = card.dataset.expertise || '';
            
            const matchesSearch = name.includes(searchTerm);
            const matchesExpertise = expertiseValue === 'all' || expertise === expertiseValue;
            
            if (matchesSearch && matchesExpertise) {
                card.classList.remove('hidden');
                visibleCount++;
            } else {
                card.classList.add('hidden');
            }
        });
        
        if (dosenCountEl) {
            dosenCountEl.textContent = visibleCount;
        }
    }
    
    if (searchInput) {
        searchInput.addEventListener('input', filterDosen);
    }
    
    if (expertiseFilter) {
        expertiseFilter.addEventListener('change', filterDosen);
    }
    
    // Dosen Modal
    const dosenModal = document.getElementById('dosenModal');
    const dosenData = <?= json_encode($dosen ?? [], JSON_UNESCAPED_SLASHES) ?>;
    
    window.openDosenModal = function(index) {
        const d = dosenData[index];
        if (!d) return;
        
        const namaLengkap = (d.gelar_depan ? d.gelar_depan + ' ' : '') + d.nama + (d.gelar_belakang ? ', ' + d.gelar_belakang : '');
        
        document.getElementById('modal-photo').style.backgroundImage = `url('${img_url(d.foto || '')}')`;
        document.getElementById('modal-title').textContent = namaLengkap;
        document.getElementById('modal-expertise').textContent = d.bidang_keahlian || '';
        
        let detailsHTML = '';
        if (d.email) detailsHTML += `<p><i class="fa-solid fa-envelope"></i> <a href="mailto:${d.email}">${d.email}</a></p>`;
        if (d.pendidikan) detailsHTML += `<p><i class="fa-solid fa-graduation-cap"></i> ${d.pendidikan}</p>`;
        document.getElementById('modal-details').innerHTML = detailsHTML;
        
        document.getElementById('modal-bio').textContent = d.bio || '';
        
        let socialsHTML = '';
        if (d.email) socialsHTML += `<a href="mailto:${d.email}" aria-label="Email"><i class="fa-solid fa-envelope"></i></a>`;
        if (d.link_scholar) socialsHTML += `<a href="${d.link_scholar}" target="_blank" rel="noopener noreferrer" aria-label="Google Scholar"><i class="fa-solid fa-graduation-cap"></i></a>`;
        if (d.link_researchgate) socialsHTML += `<a href="${d.link_researchgate}" target="_blank" rel="noopener noreferrer" aria-label="ResearchGate"><i class="fa-brands fa-researchgate"></i></a>`;
        if (d.link_linkedin) socialsHTML += `<a href="${d.link_linkedin}" target="_blank" rel="noopener noreferrer" aria-label="LinkedIn"><i class="fa-brands fa-linkedin"></i></a>`;
        document.getElementById('modal-socials').innerHTML = socialsHTML;
        
        dosenModal.classList.add('active');
        dosenModal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        
        dosenModal.querySelector('.modal-close').focus();
    };
    
    window.closeDosenModal = function() {
        dosenModal.classList.remove('active');
        dosenModal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    };
    
    // Keyboard navigation
    document.addEventListener('keydown', function(e) {
        if (!dosenModal.classList.contains('active')) return;
        if (e.key === 'Escape') closeDosenModal();
    });
})();

function img_url(path) {
    if (!path) return 'data:image/svg+xml;utf8,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="300" height="400"><rect width="300" height="400" fill="#8A1538"/><text x="150" y="200" font-family="Arial" font-size="16" fill="rgba(255,255,255,.4)" text-anchor="middle">No Photo</text></svg>');
    return path;
}
</script>

<?php require_once '../includes/public_footer.php'; ?>