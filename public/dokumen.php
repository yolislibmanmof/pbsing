<?php
ob_start();
require_once '../includes/config.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';
check_ip_security($pdo);

// ===== HANDLE DOWNLOAD — WAJIB dijalankan sebelum HTML apa pun =====
if (isset($_GET['download'])) {
    $id = (int)$_GET['download'];
    $stmt = $pdo->prepare("SELECT filename FROM dokumen WHERE id = ? AND status = 'aktif'");
    $stmt->execute([$id]);
    $doc = $stmt->fetch();
    if ($doc && !empty($doc['filename'])) {
        $file_path = __DIR__ . '/../' . $doc['filename'];
        if (file_exists($file_path)) {
            // Catat unduhan
            $pdo->prepare("UPDATE dokumen SET downloads = downloads + 1 WHERE id = ?")->execute([$id]);
            // BUANG seluruh buffer HTML (preloader/navbar) agar header benar-benar bersih
            while (ob_get_level() > 0) { ob_end_clean(); }
            // Kirim header file binary
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Transfer-Encoding: binary');
            header('Cache-Control: must-revalidate, no-transform');
            header('Pragma: public');
            header('Expires: 0');
            header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
            header('Content-Length: ' . filesize($file_path));
            readfile($file_path);
            exit;
        }
    }
    // Jika file tidak ditemukan/tidak aktif → lanjut渲染 halaman normal
}

$page_title = 'Pusat Dokumen';
require_once '../includes/public_header.php';

$setting = get_all('settings', 'id = 1')[0];
$current_lang = $_COOKIE['pbsi_lang'] ?? 'id';

// Fetch documents with filter
$kategori = sanitize($_GET['kategori'] ?? 'all');
$search = sanitize($_GET['q'] ?? '');
$sql = "SELECT * FROM dokumen WHERE status = 'aktif'";
$params = [];
if ($kategori !== 'all') { $sql .= " AND kategori = ?"; $params[] = $kategori; }
if ($search !== '') { $sql .= " AND (judul LIKE ? OR deskripsi LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
$sql .= " ORDER BY kategori ASC, judul ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$dokumen = $stmt->fetchAll();

// Stats
$total_docs = count($dokumen);
$total_downloads = array_sum(array_column($dokumen, 'downloads'));
$categories = $pdo->query("SELECT DISTINCT kategori FROM dokumen WHERE status = 'aktif' ORDER BY kategori")->fetchAll(PDO::FETCH_COLUMN);

function fmt_size($bytes) {
    if ($bytes <= 0) return '-';
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < 3) { $bytes /= 1024; $i++; }
    return round($bytes, 1) . ' ' . $units[$i];
}

function file_icon($ext) {
    $map = ['pdf'=>'fa-file-pdf','doc'=>'fa-file-word','docx'=>'fa-file-word','xls'=>'fa-file-excel','xlsx'=>'fa-file-excel','ppt'=>'fa-file-powerpoint','pptx'=>'fa-file-powerpoint','zip'=>'fa-file-zipper'];
    return $map[strtolower($ext)] ?? 'fa-file';
}

function file_color($ext) {
    $map = ['pdf'=>'#e74c3c','doc'=>'#2b579a','docx'=>'#2b579a','xls'=>'#217346','xlsx'=>'#217346','ppt'=>'#d24726','pptx'=>'#d24726','zip'=>'#f39c12'];
    return $map[strtolower($ext)] ?? '#8A1538';
}
?>

<main id="main-content" role="main">

<!-- ============ PAGE HERO ============ -->
<section class="page-hero doc-hero">
    <div class="doc-hero-content">
        <span class="doc-hero-badge">
            <i class="fa-solid fa-folder-open" aria-hidden="true"></i>
            <span data-i18n="doc_badge">PUSAT DOKUMEN</span>
        </span>
        <h1 class="doc-hero-title" data-i18n="doc_title">Dokumen Akademik & Administratif</h1>
        <p class="doc-hero-subtitle" data-i18n="doc_subtitle">
            Unduh kurikulum, panduan akademik, template skripsi, SOP, dan dokumen resmi lainnya.
        </p>
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="<?= PUBLIC_URL ?>index.php" data-i18n="home">Beranda</a>
            <span aria-hidden="true"><i class="fa-solid fa-chevron-right"></i></span>
            <span aria-current="page" data-i18n="doc_breadcrumb">Pusat Dokumen</span>
        </nav>
    </div>
    <div class="doc-hero-decor" aria-hidden="true">
        <div class="doc-orb doc-orb-1"></div>
        <div class="doc-orb doc-orb-2"></div>
    </div>
</section>

<!-- ============ STATS BAR ============ -->
<section class="doc-stats-section">
    <div class="section">
        <div class="doc-stats-grid">
            <div class="doc-stat-card">
                <div class="doc-stat-icon"><i class="fa-solid fa-file-lines"></i></div>
                <div class="doc-stat-value"><?= $total_docs ?></div>
                <div class="doc-stat-label" data-i18n="doc_total">Total Dokumen</div>
            </div>
            <div class="doc-stat-card accent-sky">
                <div class="doc-stat-icon"><i class="fa-solid fa-download"></i></div>
                <div class="doc-stat-value"><?= number_format($total_downloads) ?></div>
                <div class="doc-stat-label" data-i18n="doc_downloads">Total Unduhan</div>
            </div>
            <div class="doc-stat-card accent-emerald">
                <div class="doc-stat-icon"><i class="fa-solid fa-layer-group"></i></div>
                <div class="doc-stat-value"><?= count($categories) ?></div>
                <div class="doc-stat-label" data-i18n="doc_categories">Kategori</div>
            </div>
        </div>
    </div>
</section>

<!-- ============ FILTER & SEARCH ============ -->
<section class="section doc-filter-section">
    <form method="GET" class="doc-filter-bar">
        <div class="doc-search-wrap">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="<?= $current_lang === 'en' ? 'Search documents...' : 'Cari dokumen...' ?>" class="doc-search-input">
        </div>
        <div class="doc-cat-filters">
            <a href="?kategori=all" class="doc-cat-btn <?= $kategori === 'all' ? 'active' : '' ?>">
                <i class="fa-solid fa-border-all"></i> <?= $current_lang === 'en' ? 'All' : 'Semua' ?>
            </a>
            <?php foreach ($categories as $cat): ?>
            <a href="?kategori=<?= urlencode($cat) ?><?= $search ? '&q=' . urlencode($search) : '' ?>" class="doc-cat-btn <?= $kategori === $cat ? 'active' : '' ?>">
                <?= ucfirst(htmlspecialchars($cat)) ?>
            </a>
            <?php endforeach; ?>
        </div>
        <button type="submit" class="doc-filter-btn"><i class="fa-solid fa-filter"></i> <?= $current_lang === 'en' ? 'Filter' : 'Terapkan' ?></button>
    </form>
</section>

<!-- ============ DOCUMENTS GRID ============ -->
<section class="section doc-list-section">
    <?php if (empty($dokumen)): ?>
    <div class="doc-empty">
        <div class="doc-empty-icon"><i class="fa-solid fa-folder-open"></i></div>
        <h2 data-i18n="doc_empty_title">Dokumen Belum Tersedia</h2>
        <p data-i18n="doc_empty_desc">Belum ada dokumen yang sesuai dengan pencarian Anda.</p>
    </div>
    <?php else: ?>
    <div class="doc-grid">
        <?php foreach ($dokumen as $doc): ?>
        <div class="doc-card" data-aos="fade-up">
            <div class="doc-card-icon" style="background: <?= file_color($doc['tipe_file']) ?>20; color: <?= file_color($doc['tipe_file']) ?>;">
                <i class="fa-solid <?= file_icon($doc['tipe_file']) ?>"></i>
            </div>
            <div class="doc-card-body">
                <span class="doc-card-cat"><?= ucfirst(htmlspecialchars($doc['kategori'])) ?></span>
                <h3 class="doc-card-title"><?= htmlspecialchars($doc['judul']) ?></h3>
                <?php if ($doc['deskripsi']): ?>
                <p class="doc-card-desc"><?= htmlspecialchars(substr($doc['deskripsi'], 0, 100)) ?><?= strlen($doc['deskripsi']) > 100 ? '...' : '' ?></p>
                <?php endif; ?>
                <div class="doc-card-meta">
                    <span><i class="fa-solid fa-file"></i> <?= strtoupper(htmlspecialchars($doc['tipe_file'])) ?></span>
                    <span><i class="fa-solid fa-weight-hanging"></i> <?= fmt_size($doc['ukuran']) ?></span>
                    <span><i class="fa-solid fa-download"></i> <?= number_format($doc['downloads']) ?></span>
                </div>
            </div>
            <a href="?download=<?= $doc['id'] ?>" class="doc-card-download" title="<?= $current_lang === 'en' ? 'Download' : 'Unduh' ?>">
                <i class="fa-solid fa-cloud-arrow-down"></i>
            </a>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>

<!-- ============ CTA SECTION ============ -->
<section class="doc-cta-section">
    <div class="doc-cta-bg" aria-hidden="true">
        <div class="doc-cta-orb doc-cta-orb-1"></div>
        <div class="doc-cta-orb doc-cta-orb-2"></div>
    </div>
    <div class="doc-cta-box">
        <div class="doc-cta-icon"><i class="fa-solid fa-headset"></i></div>
        <h2 data-i18n="doc_cta_title">Tidak Menemukan Dokumen?</h2>
        <p data-i18n="doc_cta_desc">Hubungi kami jika Anda membutuhkan dokumen yang tidak tersedia di sini.</p>
        <div class="doc-cta-buttons">
            <a href="<?= PUBLIC_URL ?>kontak.php" class="btn-primary magnetic-btn">
                <i class="fa-solid fa-envelope"></i>
                <span data-i18n="doc_cta_contact">Hubungi Kami</span>
            </a>
        </div>
    </div>
</section>

</main>

<!-- ============================================================
     PREMIUM CSS — PUSAT DOKUMEN
============================================================ -->
<style>
/* ===== HERO ===== */
.doc-hero { position: relative; padding: 11rem 1.5rem 5rem; overflow: hidden; }
.doc-hero-content { max-width: 900px; margin: 0 auto; position: relative; z-index: 2; text-align: center; }
.doc-hero-badge { display: inline-flex; align-items: center; gap: .6rem; padding: .5rem 1.3rem; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); backdrop-filter: blur(10px); border-radius: 50px; font-size: .75rem; font-weight: 800; letter-spacing: 3px; color: var(--sky-blue); margin-bottom: 1.5rem; }
.doc-hero-badge i { color: var(--crimson); }
.doc-hero-title { font-size: clamp(2.2rem, 7vw, 4.5rem); font-weight: 900; line-height: 1.05; margin-bottom: 1.2rem; background: linear-gradient(135deg, var(--sky-blue), #fff); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent; }
.doc-hero-subtitle { font-size: 1.2rem; color: #cfd8dc; max-width: 700px; margin: 0 auto 2rem; line-height: 1.7; }
.doc-hero .breadcrumb { justify-content: center; font-size: .9rem; }
.doc-hero .breadcrumb i { font-size: .7rem; color: var(--sky-blue); }
.doc-hero-decor { position: absolute; inset: 0; pointer-events: none; overflow: hidden; }
.doc-orb { position: absolute; border-radius: 50%; filter: blur(80px); opacity: .4; }
.doc-orb-1 { width: 400px; height: 400px; background: var(--crimson); top: -100px; left: -100px; animation: docOrbFloat 20s ease-in-out infinite; }
.doc-orb-2 { width: 350px; height: 350px; background: var(--sky-blue-dark); bottom: -100px; right: -100px; animation: docOrbFloat 25s ease-in-out infinite reverse; }
@keyframes docOrbFloat { 0%, 100% { transform: translate(0, 0) scale(1); } 33% { transform: translate(50px, -30px) scale(1.1); } 66% { transform: translate(-30px, 50px) scale(0.9); } }

/* ===== STATS ===== */
.doc-stats-section { background: var(--bg-light); padding: 3rem 0; }
[data-theme="dark"] .doc-stats-section { background: var(--rich-black); }
.doc-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; }
.doc-stat-card { background: #fff; border-radius: 16px; padding: 1.5rem; text-align: center; box-shadow: 0 8px 25px rgba(0,0,0,.06); border-top: 4px solid var(--crimson); transition: transform .3s ease; }
[data-theme="dark"] .doc-stat-card { background: #1a1a2e; }
.doc-stat-card:hover { transform: translateY(-5px); }
.doc-stat-card.accent-sky { border-top-color: var(--sky-blue); }
.doc-stat-card.accent-emerald { border-top-color: var(--emerald); }
.doc-stat-icon { width: 50px; height: 50px; border-radius: 12px; background: rgba(138,21,56,.1); color: var(--crimson); display: flex; align-items: center; justify-content: center; font-size: 1.3rem; margin: 0 auto 1rem; }
.doc-stat-card.accent-sky .doc-stat-icon { background: rgba(135,206,235,.1); color: var(--sky-blue); }
.doc-stat-card.accent-emerald .doc-stat-icon { background: rgba(46,139,87,.1); color: var(--emerald); }
.doc-stat-value { font-size: 2rem; font-weight: 900; color: var(--rich-black); line-height: 1; }
[data-theme="dark"] .doc-stat-value { color: #fff; }
.doc-stat-label { color: var(--text-grey); font-size: .85rem; margin-top: .3rem; }

/* ===== FILTER ===== */
.doc-filter-section { padding-bottom: 0; }
.doc-filter-bar { display: flex; flex-wrap: wrap; gap: 1rem; align-items: center; background: #fff; padding: 1.2rem; border-radius: 16px; box-shadow: 0 8px 25px rgba(0,0,0,.06); }
[data-theme="dark"] .doc-filter-bar { background: #1a1a2e; }
.doc-search-wrap { position: relative; flex: 1; min-width: 250px; }
.doc-search-wrap i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--text-grey); }
.doc-search-input { width: 100%; padding: .7rem 1rem .7rem 42px; border: 1px solid var(--border-dark, #e0e0e0); border-radius: 10px; background: var(--bg-light); color: var(--rich-black); font-size: .9rem; }
[data-theme="dark"] .doc-search-input { background: var(--bg-darker, #0f0f1a); border-color: var(--border-dark); color: #fff; }
.doc-search-input:focus { outline: none; border-color: var(--crimson); }
.doc-cat-filters { display: flex; flex-wrap: wrap; gap: .5rem; }
.doc-cat-btn { padding: .5rem 1rem; border-radius: 50px; background: var(--bg-light); color: var(--text-grey); font-size: .82rem; font-weight: 600; text-decoration: none; transition: all .2s ease; border: 1px solid transparent; }
[data-theme="dark"] .doc-cat-btn { background: var(--bg-darker, #0f0f1a); }
.doc-cat-btn:hover, .doc-cat-btn.active { background: var(--crimson); color: #fff; }
.doc-filter-btn { padding: .7rem 1.5rem; background: var(--crimson); color: #fff; border: none; border-radius: 10px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: .5rem; transition: all .2s ease; }
.doc-filter-btn:hover { background: var(--crimson-light, #A02050); }

/* ===== DOCUMENTS GRID ===== */
.doc-list-section { padding-top: 2rem; }
.doc-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 1.5rem; }
.doc-card { display: flex; align-items: center; gap: 1rem; background: #fff; border-radius: 16px; padding: 1.2rem; box-shadow: 0 8px 25px rgba(0,0,0,.06); transition: all .3s ease; border: 1px solid transparent; }
[data-theme="dark"] .doc-card { background: #1a1a2e; }
.doc-card:hover { transform: translateY(-4px); box-shadow: 0 15px 40px rgba(0,0,0,.12); border-color: var(--crimson); }
.doc-card-icon { width: 56px; height: 56px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0; }
.doc-card-body { flex: 1; min-width: 0; }
.doc-card-cat { display: inline-block; font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: var(--crimson); background: rgba(138,21,56,.1); padding: .15rem .5rem; border-radius: 50px; margin-bottom: .4rem; }
.doc-card-title { font-size: .95rem; font-weight: 700; color: var(--rich-black); margin: 0 0 .3rem; line-height: 1.3; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
[data-theme="dark"] .doc-card-title { color: #fff; }
.doc-card-desc { font-size: .8rem; color: var(--text-grey); margin: 0 0 .5rem; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.doc-card-meta { display: flex; gap: .8rem; font-size: .72rem; color: var(--text-grey); }
.doc-card-meta span { display: flex; align-items: center; gap: .3rem; }
.doc-card-meta i { font-size: .65rem; }
.doc-card-download { width: 44px; height: 44px; border-radius: 12px; background: linear-gradient(135deg, var(--crimson), var(--crimson-light, #A02050)); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; text-decoration: none; flex-shrink: 0; transition: all .3s ease; }
.doc-card-download:hover { transform: scale(1.1); box-shadow: 0 8px 20px rgba(138,21,56,.4); }

/* ===== EMPTY STATE ===== */
.doc-empty { text-align: center; padding: 5rem 2rem; background: #fff; border-radius: 24px; box-shadow: 0 10px 40px rgba(0,0,0,.08); max-width: 700px; margin: 0 auto; }
[data-theme="dark"] .doc-empty { background: #1a1a2e; }
.doc-empty-icon { width: 120px; height: 120px; margin: 0 auto 1.5rem; background: linear-gradient(135deg, rgba(138,21,56,0.1), rgba(135,206,235,0.1)); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 3.5rem; color: var(--crimson); }
.doc-empty h2 { color: var(--rich-black); margin-bottom: .5rem; font-size: 1.5rem; font-weight: 800; }
[data-theme="dark"] .doc-empty h2 { color: #fff; }
.doc-empty p { color: var(--text-grey); }

/* ===== CTA ===== */
.doc-cta-section { padding: 6rem 1.5rem; background: linear-gradient(180deg, #fff 0%, var(--bg-light) 100%); position: relative; overflow: hidden; }
[data-theme="dark"] .doc-cta-section { background: linear-gradient(180deg, var(--rich-black), #0f0f1a); }
.doc-cta-bg { position: absolute; inset: 0; pointer-events: none; }
.doc-cta-orb { position: absolute; border-radius: 50%; filter: blur(80px); opacity: .3; }
.doc-cta-orb-1 { width: 400px; height: 400px; background: var(--crimson); top: -150px; left: -100px; animation: docOrbFloat 25s ease-in-out infinite; }
.doc-cta-orb-2 { width: 350px; height: 350px; background: var(--sky-blue); bottom: -150px; right: -100px; animation: docOrbFloat 30s ease-in-out infinite reverse; }
.doc-cta-box { max-width: 800px; margin: 0 auto; text-align: center; padding: 4rem 3rem; background: linear-gradient(135deg, var(--crimson), var(--crimson-light, #A02050)); border-radius: 28px; color: #fff; box-shadow: 0 30px 80px rgba(138,21,56,.35); position: relative; overflow: hidden; z-index: 2; }
.doc-cta-icon { width: 90px; height: 90px; margin: 0 auto 1.5rem; border-radius: 50%; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; font-size: 2.5rem; backdrop-filter: blur(10px); position: relative; z-index: 2; }
.doc-cta-box h2 { font-size: clamp(1.5rem, 4vw, 2.2rem); margin-bottom: 1rem; font-weight: 900; position: relative; z-index: 2; }
.doc-cta-box p { color: rgba(255,255,255,.9); margin-bottom: 2rem; line-height: 1.8; max-width: 600px; margin-left: auto; margin-right: auto; position: relative; z-index: 2; }
.doc-cta-buttons { display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap; position: relative; z-index: 2; }
.doc-cta-buttons .btn-primary { background: #fff; color: var(--crimson); margin-top: 0; box-shadow: 0 12px 35px rgba(0,0,0,.25); }

/* ===== RESPONSIVE ===== */
@media (max-width: 768px) {
    .doc-hero { padding: 9rem 1rem 4rem; }
    .doc-filter-bar { flex-direction: column; }
    .doc-search-wrap { width: 100%; }
    .doc-grid { grid-template-columns: 1fr; }
    .doc-cta-box { padding: 2.5rem 1.5rem; }
}
</style>

<!-- ============================================================
     JAVASCRIPT — PUSAT DOKUMEN
============================================================ -->
<script>
(function () {
    'use strict';
    // Reveal animation
    const revealObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
                revealObserver.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1 });
    document.querySelectorAll('.doc-card').forEach((el, i) => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(30px)';
        el.style.transition = `all 0.6s cubic-bezier(0.22, 0.61, 0.36, 1) ${i * 0.05}s`;
        revealObserver.observe(el);
    });
    // Magnetic buttons
    document.querySelectorAll('.magnetic-btn').forEach(el => {
        el.addEventListener('mousemove', (e) => {
            const rect = el.getBoundingClientRect();
            el.style.transform = `translate(${(e.clientX - rect.left - rect.width / 2) * 0.15}px, ${(e.clientY - rect.top - rect.height / 2) * 0.15}px)`;
        });
        el.addEventListener('mouseleave', () => { el.style.transform = ''; });
    });
})();
</script>

<!-- Multi-language Support -->
<script>
(function () {
    'use strict';
    const translations = {
        en: {
            'doc_badge': 'DOCUMENT CENTER',
            'doc_title': 'Academic & Administrative Documents',
            'doc_subtitle': 'Download curriculum, academic guides, thesis templates, SOPs, and other official documents.',
            'home': 'Home',
            'doc_breadcrumb': 'Document Center',
            'doc_total': 'Total Documents',
            'doc_downloads': 'Total Downloads',
            'doc_categories': 'Categories',
            'doc_empty_title': 'No Documents Available',
            'doc_empty_desc': 'No documents match your search.',
            'doc_cta_title': "Can't Find a Document?",
            'doc_cta_desc': 'Contact us if you need a document that is not available here.',
            'doc_cta_contact': 'Contact Us'
        }
    };
    function applyTranslations() {
        const lang = document.documentElement.getAttribute('lang') || 'id';
        if (lang === 'en' && translations.en) {
            document.querySelectorAll('[data-i18n]').forEach(el => {
                const key = el.dataset.i18n;
                if (translations.en[key]) el.textContent = translations.en[key];
            });
        }
    }
    const langObserver = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => { if (mutation.attributeName === 'lang') applyTranslations(); });
    });
    langObserver.observe(document.documentElement, { attributes: true });
    applyTranslations();
})();
</script>

<?php require_once '../includes/public_footer.php'; ?>