<?php
require_once '../includes/config.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';
check_ip_security($pdo);

$page_title = 'Pusat Unduhan & RPS';
require_once '../includes/public_header.php';

$unduh = get_all('unduh', null, 'tanggal_upload DESC');
$rps   = get_all('rps_kurikulum', null, 'urutan ASC');
$setting = get_all('settings', 'id = 1')[0];

// ===== HELPER IKON FILE =====
if (!function_exists('file_icon')) {
    function file_icon($path) {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $map = [
            'pdf'  => ['fa-file-pdf',    '#e74c3c', 'PDF'],
            'doc'  => ['fa-file-word',   '#2b579a', 'DOC'],
            'docx' => ['fa-file-word',   '#2b579a', 'DOC'],
            'xls'  => ['fa-file-excel',  '#217346', 'XLS'],
            'xlsx' => ['fa-file-excel',  '#217346', 'XLS'],
            'ppt'  => ['fa-file-powerpoint', '#d24726', 'PPT'],
            'pptx' => ['fa-file-powerpoint', '#d24726', 'PPT'],
            'zip'  => ['fa-file-zipper', '#f39c12', 'ZIP'],
            'rar'  => ['fa-file-zipper', '#f39c12', 'RAR'],
            'jpg'  => ['fa-file-image',  '#9b59b6', 'IMG'],
            'jpeg' => ['fa-file-image',  '#9b59b6', 'IMG'],
            'png'  => ['fa-file-image',  '#9b59b6', 'IMG'],
        ];
        return $map[$ext] ?? ['fa-file', '#95a5a6', strtoupper($ext)];
    }
}

// Extract unique categories
$docCategories = [];
if (!empty($unduh)) {
    foreach ($unduh as $u) {
        if (!empty($u['kategori'])) $docCategories[] = $u['kategori'];
    }
    $docCategories = array_unique($docCategories);
}

// Statistics
$totalDocs = count($unduh);
$totalRPS = count($rps);
$totalDownloads = array_sum(array_column($unduh, 'jumlah_unduh'));

// Group RPS by semester
$rpsBySem = [];
foreach ($rps as $r) {
    $sem = $r['semester'] ?? 'Lainnya';
    $rpsBySem[$sem][] = $r;
}
?>

<!-- Skip Link -->
<a href="#main-content" class="skip-link">Skip to main content</a>

<!-- JSON-LD for DigitalDocument -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "CollectionPage",
  "name": "Pusat Unduhan & RPS - <?= sanitize($setting['site_name'] ?? 'PBSI UMMaumere') ?>",
  "description": "Dokumen akademik, pedoman, dan Rencana Pembelajaran Semester",
  "breadcrumb": {
    "@type": "BreadcrumbList",
    "itemListElement": [
      { "@type": "ListItem", "position": 1, "name": "Beranda", "item": "<?= PUBLIC_URL ?>" },
      { "@type": "ListItem", "position": 2, "name": "Pusat Unduhan" }
    ]
  }
}
</script>

<main id="main-content" role="main">

<!-- PAGE HERO -->
<section class="page-hero">
    <h1>Pusat Unduhan &amp; RPS</h1>
    <p>Dokumen akademik, pedoman, dan Rencana Pembelajaran Semester.</p>
    <nav class="breadcrumb" aria-label="Breadcrumb">
        <a href="<?= PUBLIC_URL ?>index.php">Beranda</a>
        <span aria-hidden="true">/</span>
        <span aria-current="page">Pusat Unduhan</span>
    </nav>
</section>

<!-- DOWNLOAD SECTION -->
<section class="unduh-section" aria-labelledby="unduh-heading">
    <div class="section">
        <header class="sec-head">
            <span class="sec-eyebrow ey-crimson">
                <i class="fa-solid fa-cloud-arrow-down" aria-hidden="true"></i> 
                PUSAT DOKUMEN
            </span>
            <h2 class="section-title" id="unduh-heading">Pusat Unduhan</h2>
            <p class="section-subtitle">Akses dokumen akademik, pedoman, formulir, dan RPS secara mudah dan cepat.</p>
        </header>

        <!-- STATS GRID -->
        <div class="unduh-stats" role="list">
            <div class="unduh-stat-card" role="listitem" data-aos="fade-up" data-aos-delay="0">
                <i class="fa-solid fa-file-lines" aria-hidden="true"></i>
                <h3 data-target="<?= $totalDocs ?>">0</h3>
                <p>Dokumen Akademik</p>
            </div>
            <div class="unduh-stat-card" role="listitem" data-aos="fade-up" data-aos-delay="100">
                <i class="fa-solid fa-book-open" aria-hidden="true"></i>
                <h3 data-target="<?= $totalRPS ?>">0</h3>
                <p>RPS Tersedia</p>
            </div>
            <div class="unduh-stat-card" role="listitem" data-aos="fade-up" data-aos-delay="200">
                <i class="fa-solid fa-download" aria-hidden="true"></i>
                <h3 data-target="<?= $totalDownloads ?>">0</h3>
                <p>Total Unduhan</p>
            </div>
            <div class="unduh-stat-card" role="listitem" data-aos="fade-up" data-aos-delay="300">
                <i class="fa-solid fa-folder" aria-hidden="true"></i>
                <h3 data-target="<?= count($docCategories) ?>">0</h3>
                <p>Kategori</p>
            </div>
        </div>

        <!-- DOKUMEN AKADEMIK -->
        <?php if (!empty($unduh)): ?>
        <div class="unduh-block" data-aos="fade-up">
            <div class="unduh-block-header">
                <h2 class="section-title" style="margin-bottom:0;">
                    <i class="fa-solid fa-file-lines" aria-hidden="true" style="color:var(--crimson);margin-right:.6rem;"></i>
                    Dokumen Akademik
                </h2>
                <div class="unduh-controls-mini">
                    <div class="search-box search-box-sm">
                        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                        <input type="text" 
                               id="docSearch" 
                               placeholder="Cari dokumen..." 
                               aria-label="Cari dokumen">
                    </div>
                    <?php if (count($docCategories) > 1): ?>
                    <div class="filter-dropdown">
                        <label for="docCatFilter" class="sr-only">Filter kategori</label>
                        <select id="docCatFilter" aria-label="Filter berdasarkan kategori">
                            <option value="all">Semua Kategori</option>
                            <?php foreach ($docCategories as $cat): ?>
                            <option value="<?= sanitize(strtolower($cat)) ?>"><?= sanitize($cat) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="unduh-list" id="docList" role="list">
                <?php foreach ($unduh as $i => $u): 
                    $icon = file_icon($u['file_path'] ?? '');
                    $cat = !empty($u['kategori']) ? sanitize(strtolower($u['kategori'])) : '';
                ?>
                <article class="unduh-item" 
                         data-category="<?= $cat ?>"
                         data-title="<?= sanitize(strtolower($u['judul'])) ?>"
                         role="listitem"
                         data-aos="fade-up" 
                         data-aos-delay="<?= ($i % 6) * 60 ?>">
                    <div class="unduh-icon" style="background:<?= $icon[1] ?>15;color:<?= $icon[1] ?>;">
                        <i class="fa-solid <?= $icon[0] ?>" aria-hidden="true"></i>
                        <span class="ext-badge" style="background:<?= $icon[1] ?>;"><?= $icon[2] ?></span>
                    </div>
                    <div class="unduh-info">
                        <h3><?= sanitize($u['judul']) ?></h3>
                        <?php if (!empty($u['deskripsi'])): ?>
                        <p class="unduh-desc"><?= sanitize($u['deskripsi']) ?></p>
                        <?php endif; ?>
                        <div class="unduh-meta">
                            <?php if (!empty($u['kategori'])): ?>
                            <span class="meta-tag">
                                <i class="fa-solid fa-tag" aria-hidden="true"></i>
                                <?= sanitize($u['kategori']) ?>
                            </span>
                            <?php endif; ?>
                            <?php if (!empty($u['ukuran_file'])): ?>
                            <span class="meta-tag">
                                <i class="fa-solid fa-weight-hanging" aria-hidden="true"></i>
                                <?= sanitize($u['ukuran_file']) ?>
                            </span>
                            <?php endif; ?>
                            <?php if (!empty($u['tanggal_upload'])): ?>
                            <span class="meta-tag">
                                <i class="fa-regular fa-calendar" aria-hidden="true"></i>
                                <?= date('d M Y', strtotime($u['tanggal_upload'])) ?>
                            </span>
                            <?php endif; ?>
                            <span class="meta-tag meta-popular">
                                <i class="fa-solid fa-arrow-down" aria-hidden="true"></i>
                                <?= number_format((int)$u['jumlah_unduh']) ?>x diunduh
                            </span>
                        </div>
                    </div>
                    <div class="unduh-actions">
                        <a href="<?= PUBLIC_URL . sanitize($u['file_path']) ?>" 
                           class="btn-download"
                           download
                           data-analytics="doc-download"
                           aria-label="Unduh <?= sanitize($u['judul']) ?>">
                            <i class="fa-solid fa-download" aria-hidden="true"></i>
                            <span>Unduh</span>
                        </a>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>

            <div class="unduh-counter" aria-live="polite">
                <p>Menampilkan <strong id="doc-visible-count"><?= $totalDocs ?></strong> dari <strong><?= $totalDocs ?></strong> dokumen</p>
            </div>
        </div>
        <?php endif; ?>

        <!-- RPS SECTION -->
        <?php if (!empty($rps)): ?>
        <div class="unduh-block rps-block" data-aos="fade-up">
            <div class="unduh-block-header">
                <h2 class="section-title" style="margin-bottom:0;">
                    <i class="fa-solid fa-book-open" aria-hidden="true" style="color:var(--sky-blue-dark);margin-right:.6rem;"></i>
                    Rencana Pembelajaran Semester (RPS)
                </h2>
                <div class="unduh-controls-mini">
                    <div class="search-box search-box-sm">
                        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                        <input type="text" 
                               id="rpsSearch" 
                               placeholder="Cari RPS..." 
                               aria-label="Cari RPS">
                    </div>
                    <div class="filter-dropdown">
                        <label for="rpsSemFilter" class="sr-only">Filter semester</label>
                        <select id="rpsSemFilter" aria-label="Filter berdasarkan semester">
                            <option value="all">Semua Semester</option>
                            <?php foreach (array_keys($rpsBySem) as $sem): ?>
                            <option value="<?= $sem ?>">Semester <?= $sem ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="rps-grid" id="rpsGrid" role="list">
                <?php foreach ($rps as $i => $r): 
                    $icon = file_icon($r['file_path'] ?? '');
                    $sem = $r['semester'] ?? '';
                ?>
                <article class="rps-card" 
                         data-semester="<?= $sem ?>"
                         data-title="<?= sanitize(strtolower($r['mata_kuliah'] . ' ' . ($r['kode_mk'] ?? ''))) ?>"
                         role="listitem"
                         data-aos="fade-up" 
                         data-aos-delay="<?= ($i % 6) * 60 ?>">
                    <div class="rps-header">
                        <div class="rps-icon" style="background:<?= $icon[1] ?>15;color:<?= $icon[1] ?>;">
                            <i class="fa-solid <?= $icon[0] ?>" aria-hidden="true"></i>
                        </div>
                        <span class="rps-sem-badge">SMT <?= sanitize($r['semester']) ?></span>
                    </div>
                    <div class="rps-body">
                        <span class="rps-code"><?= sanitize($r['kode_mk']) ?></span>
                        <h3><?= sanitize($r['mata_kuliah']) ?></h3>
                        <div class="rps-meta">
                            <span><i class="fa-solid fa-calendar-days" aria-hidden="true"></i> TA <?= sanitize($r['tahun_akademik']) ?></span>
                            <span><i class="fa-solid fa-weight-hanging" aria-hidden="true"></i> <?= sanitize($r['ukuran_file']) ?></span>
                        </div>
                    </div>
                    <a href="<?= PUBLIC_URL . sanitize($r['file_path']) ?>" 
                       class="btn-download-rps"
                       download
                       data-analytics="rps-download"
                       aria-label="Unduh RPS <?= sanitize($r['mata_kuliah']) ?>">
                        <i class="fa-solid fa-download" aria-hidden="true"></i>
                        <span>Unduh RPS</span>
                    </a>
                </article>
                <?php endforeach; ?>
            </div>

            <div class="unduh-counter" aria-live="polite">
                <p>Menampilkan <strong id="rps-visible-count"><?= $totalRPS ?></strong> dari <strong><?= $totalRPS ?></strong> RPS</p>
            </div>
        </div>
        <?php endif; ?>

        <!-- EMPTY STATE -->
        <?php if (empty($unduh) && empty($rps)): ?>
        <div class="empty-state" style="text-align:center;padding:5rem 2rem;">
            <i class="fa-solid fa-box-open" style="font-size:5rem;color:#ddd;margin-bottom:1.5rem;" aria-hidden="true"></i>
            <h2 style="color:#999;margin-bottom:.5rem;">Belum Ada Dokumen</h2>
            <p style="color:#aaa;max-width:500px;margin:0 auto;">Dokumen akademik dan RPS sedang dalam proses pengunggahan. Silakan kunjungi kembali nanti.</p>
            <a href="<?= PUBLIC_URL ?>kontak.php" class="btn-primary" style="margin-top:2rem;">
                <i class="fa-solid fa-headset" aria-hidden="true"></i> Hubungi Kami
            </a>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- INFO SECTION -->
<section class="unduh-info" aria-labelledby="info-heading">
    <div class="section">
        <div class="info-grid">
            <div class="info-card" data-aos="fade-right">
                <div class="info-icon"><i class="fa-solid fa-circle-info" aria-hidden="true"></i></div>
                <h3>Butuh Bantuan?</h3>
                <p>Jika Anda kesulitan menemukan dokumen tertentu atau memerlukan dokumen yang belum tersedia di sini, silakan hubungi bagian administrasi prodi.</p>
                <a href="<?= PUBLIC_URL ?>kontak.php" class="info-link">
                    Hubungi Admin <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </a>
            </div>
            <div class="info-card" data-aos="fade-left">
                <div class="info-icon"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i></div>
                <h3>Hak Cipta & Penggunaan</h3>
                <p>Seluruh dokumen di sini adalah milik PBSI UMMaumere dan hanya untuk keperluan akademik. Dilarang menyebarluaskan tanpa izin tertulis.</p>
                <a href="<?= PUBLIC_URL ?>page.php?slug=kebijakan" class="info-link">
                    Kebijakan Penggunaan <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </a>
            </div>
        </div>
    </div>
</section>

</main>

<!-- Inline Styles for Unduh Page -->
<style>
/* Unduh Section */
.unduh-section {
    background: var(--bg-light);
    padding: 5rem 1.5rem;
}

/* Stats */
.unduh-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-bottom: 4rem;
}
.unduh-stat-card {
    background: #fff;
    border-radius: 20px;
    padding: 2rem;
    text-align: center;
    box-shadow: 0 10px 35px rgba(0,0,0,.08);
    transition: all .3s ease;
    border-top: 4px solid var(--crimson);
}
.unduh-stat-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 20px 50px rgba(0,0,0,.15);
}
.unduh-stat-card i {
    font-size: 2.5rem;
    color: var(--crimson);
    margin-bottom: 1rem;
}
.unduh-stat-card h3 {
    font-size: 2.5rem;
    font-weight: 900;
    color: var(--rich-black);
    margin-bottom: .5rem;
}
.unduh-stat-card p {
    color: var(--text-grey);
    font-size: .95rem;
    font-weight: 600;
}

/* Block Header */
.unduh-block {
    background: #fff;
    border-radius: 24px;
    padding: 3rem;
    box-shadow: 0 15px 45px rgba(0,0,0,.06);
    margin-bottom: 3rem;
}
.unduh-block-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    flex-wrap: wrap;
    gap: 1.5rem;
    padding-bottom: 1.5rem;
    border-bottom: 2px solid #f0f0f0;
}
.unduh-controls-mini {
    display: flex;
    gap: 1rem;
    align-items: center;
    flex-wrap: wrap;
}
.search-box-sm {
    position: relative;
    width: 280px;
}
.search-box-sm i {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: #999;
}
.search-box-sm input {
    width: 100%;
    padding: .7rem 1rem .7rem 2.8rem;
    border: 2px solid #e0e0e0;
    border-radius: 50px;
    font-size: .92rem;
    transition: all .3s ease;
}
.search-box-sm input:focus {
    outline: none;
    border-color: var(--sky-blue);
    box-shadow: 0 0 0 4px rgba(135,206,235,.15);
}
.unduh-controls-mini .filter-dropdown select {
    padding: .7rem 1rem;
    font-size: .92rem;
}
.unduh-controls-mini .filter-dropdown label {
    display: none;
}

/* Unduh List */
.unduh-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}
.unduh-item {
    display: grid;
    grid-template-columns: 80px 1fr auto;
    gap: 1.5rem;
    padding: 1.5rem;
    background: #fff;
    border: 2px solid #f0f0f0;
    border-radius: 16px;
    transition: all .3s ease;
    align-items: center;
}
.unduh-item:hover {
    border-color: var(--crimson);
    box-shadow: 0 10px 30px rgba(138,21,56,.08);
    transform: translateX(4px);
}
.unduh-item.hidden {
    display: none;
}
.unduh-icon {
    width: 80px;
    height: 80px;
    border-radius: 16px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    position: relative;
}
.ext-badge {
    position: absolute;
    bottom: -6px;
    right: -6px;
    background: var(--crimson);
    color: #fff;
    font-size: .65rem;
    font-weight: 800;
    padding: .2rem .5rem;
    border-radius: 6px;
    letter-spacing: .5px;
}
.unduh-info h3 {
    font-size: 1.1rem;
    margin-bottom: .5rem;
    color: var(--rich-black);
    font-weight: 700;
    line-height: 1.4;
}
.unduh-desc {
    color: var(--text-grey);
    font-size: .9rem;
    line-height: 1.6;
    margin-bottom: .8rem;
}
.unduh-meta {
    display: flex;
    flex-wrap: wrap;
    gap: .8rem;
    margin-top: .5rem;
}
.meta-tag {
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    background: var(--bg-light);
    padding: .3rem .8rem;
    border-radius: 50px;
    font-size: .78rem;
    color: var(--text-grey);
    font-weight: 600;
}
.meta-tag i {
    color: var(--crimson);
    font-size: .7rem;
}
.meta-popular i {
    color: var(--emerald);
}
.btn-download {
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    padding: .8rem 1.6rem;
    background: linear-gradient(135deg, var(--crimson), var(--crimson-light));
    color: #fff;
    border-radius: 50px;
    font-weight: 700;
    font-size: .9rem;
    transition: all .3s ease;
    text-decoration: none;
    white-space: nowrap;
    box-shadow: 0 8px 20px rgba(138,21,56,.25);
}
.btn-download:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 30px rgba(138,21,56,.4);
}

/* RPS Grid */
.rps-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1.5rem;
}
.rps-card {
    background: #fff;
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: 0 10px 30px rgba(0,0,0,.06);
    transition: all .4s cubic-bezier(.4,0,.2,1);
    display: flex;
    flex-direction: column;
    border-top: 4px solid var(--sky-blue);
}
.rps-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 20px 50px rgba(0,0,0,.12);
}
.rps-card.hidden {
    display: none;
}
.rps-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
}
.rps-icon {
    width: 56px;
    height: 56px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.6rem;
}
.rps-sem-badge {
    background: linear-gradient(135deg, var(--sky-blue), var(--sky-blue-dark));
    color: #fff;
    padding: .35rem .9rem;
    border-radius: 50px;
    font-size: .75rem;
    font-weight: 800;
    letter-spacing: .5px;
}
.rps-body {
    flex: 1;
    margin-bottom: 1.2rem;
}
.rps-code {
    display: inline-block;
    background: rgba(138,21,56,.08);
    color: var(--crimson);
    padding: .2rem .7rem;
    border-radius: 6px;
    font-size: .75rem;
    font-weight: 800;
    letter-spacing: .5px;
    margin-bottom: .5rem;
}
.rps-body h3 {
    font-size: 1.05rem;
    margin-bottom: .8rem;
    color: var(--rich-black);
    font-weight: 700;
    line-height: 1.4;
}
.rps-meta {
    display: flex;
    flex-direction: column;
    gap: .4rem;
    font-size: .85rem;
    color: var(--text-grey);
}
.rps-meta i {
    color: var(--sky-blue-dark);
    margin-right: .4rem;
    width: 14px;
}
.btn-download-rps {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: .5rem;
    padding: .8rem 1.5rem;
    background: linear-gradient(135deg, var(--sky-blue-dark), var(--sky-blue));
    color: #fff;
    border-radius: 50px;
    font-weight: 700;
    font-size: .9rem;
    transition: all .3s ease;
    text-decoration: none;
    box-shadow: 0 8px 20px rgba(135,206,235,.25);
}
.btn-download-rps:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 30px rgba(135,206,235,.4);
}

/* Counter */
.unduh-counter {
    text-align: center;
    margin-top: 2rem;
    padding: 1rem;
    background: var(--bg-light);
    border-radius: 12px;
    color: var(--text-grey);
    font-size: .9rem;
}
.unduh-counter strong {
    color: var(--crimson);
}

/* Info Section */
.unduh-info {
    padding: 5rem 1.5rem;
    background: #fff;
}
.info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
    max-width: 1000px;
    margin: 0 auto;
}
.info-card {
    background: linear-gradient(135deg, #f8f9fa, #fff);
    padding: 2.5rem;
    border-radius: 20px;
    border: 2px solid #f0f0f0;
    transition: all .3s ease;
}
.info-card:hover {
    border-color: var(--crimson);
    box-shadow: 0 15px 40px rgba(138,21,56,.08);
}
.info-icon {
    width: 60px;
    height: 60px;
    border-radius: 16px;
    background: linear-gradient(135deg, var(--crimson), var(--crimson-light));
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 8px 20px rgba(138,21,56,.3);
}
.info-card h3 {
    font-size: 1.3rem;
    margin-bottom: 1rem;
    color: var(--rich-black);
    font-weight: 800;
}
.info-card p {
    color: var(--text-grey);
    line-height: 1.7;
    margin-bottom: 1.5rem;
}
.info-link {
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    color: var(--crimson);
    font-weight: 700;
    text-decoration: none;
    transition: all .3s ease;
}
.info-link:hover {
    gap: .8rem;
    color: var(--sky-blue-dark);
}

/* Responsive */
@media (max-width: 900px) {
    .unduh-item {
        grid-template-columns: 1fr;
        text-align: center;
    }
    .unduh-icon {
        margin: 0 auto;
    }
    .unduh-meta {
        justify-content: center;
    }
    .btn-download {
        justify-content: center;
    }
    .unduh-block-header {
        flex-direction: column;
        align-items: flex-start;
    }
    .unduh-controls-mini {
        width: 100%;
    }
    .search-box-sm {
        width: 100%;
    }
    .info-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- Unduh Page JavaScript -->
<script>
(function() {
    'use strict';
    
    // Document Filter
    const docItems = document.querySelectorAll('#docList .unduh-item');
    const docSearch = document.getElementById('docSearch');
    const docCatFilter = document.getElementById('docCatFilter');
    const docVisibleCount = document.getElementById('doc-visible-count');
    
    function filterDocs() {
        const searchTerm = docSearch ? docSearch.value.toLowerCase() : '';
        const catValue = docCatFilter ? docCatFilter.value : 'all';
        let visible = 0;
        
        docItems.forEach(item => {
            const title = item.dataset.title || '';
            const cat = item.dataset.category || '';
            if ((searchTerm === '' || title.includes(searchTerm)) && 
                (catValue === 'all' || cat === catValue)) {
                item.classList.remove('hidden');
                visible++;
            } else {
                item.classList.add('hidden');
            }
        });
        
        if (docVisibleCount) docVisibleCount.textContent = visible;
    }
    
    if (docSearch) docSearch.addEventListener('input', filterDocs);
    if (docCatFilter) docCatFilter.addEventListener('change', filterDocs);
    
    // RPS Filter
    const rpsItems = document.querySelectorAll('#rpsGrid .rps-card');
    const rpsSearch = document.getElementById('rpsSearch');
    const rpsSemFilter = document.getElementById('rpsSemFilter');
    const rpsVisibleCount = document.getElementById('rps-visible-count');
    
    function filterRPS() {
        const searchTerm = rpsSearch ? rpsSearch.value.toLowerCase() : '';
        const semValue = rpsSemFilter ? rpsSemFilter.value : 'all';
        let visible = 0;
        
        rpsItems.forEach(item => {
            const title = item.dataset.title || '';
            const sem = item.dataset.semester || '';
            if ((searchTerm === '' || title.includes(searchTerm)) && 
                (semValue === 'all' || sem === semValue)) {
                item.classList.remove('hidden');
                visible++;
            } else {
                item.classList.add('hidden');
            }
        });
        
        if (rpsVisibleCount) rpsVisibleCount.textContent = visible;
    }
    
    if (rpsSearch) rpsSearch.addEventListener('input', filterRPS);
    if (rpsSemFilter) rpsSemFilter.addEventListener('change', filterRPS);
})();
</script>

<?php require_once '../includes/public_footer.php'; ?>