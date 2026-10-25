<?php
require_once '../includes/config.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';
check_ip_security($pdo);

$page_title = 'FAQ - Pertanyaan Umum';
require_once '../includes/public_header.php';

$setting = get_all('settings', 'id = 1')[0];
$current_lang = $_COOKIE['pbsi_lang'] ?? 'id';

// Fetch FAQ grouped by category
$faq_kategori = sanitize($_GET['kategori'] ?? 'all');
$sql = "SELECT * FROM faq WHERE status = 'aktif'";
$params = [];
if ($faq_kategori !== 'all') { $sql .= " AND kategori = ?"; $params[] = $faq_kategori; }
$sql .= " ORDER BY kategori ASC, urutan ASC, id ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$faq_items = $stmt->fetchAll();

// Group by category
$faq_grouped = [];
foreach ($faq_items as $item) {
    $faq_grouped[$item['kategori']][] = $item;
}

$all_categories = $pdo->query("SELECT DISTINCT kategori FROM faq WHERE status = 'aktif' ORDER BY kategori")->fetchAll(PDO::FETCH_COLUMN);
$total_faq = count($faq_items);
?>

<main id="main-content" role="main">

<!-- ============ PAGE HERO ============ -->
<section class="page-hero faq-hero">
    <div class="faq-hero-content">
        <span class="faq-hero-badge">
            <i class="fa-solid fa-circle-question" aria-hidden="true"></i>
            <span data-i18n="faq_badge">FAQ</span>
        </span>
        <h1 class="faq-hero-title" data-i18n="faq_title">Pertanyaan yang Sering Diajukan</h1>
        <p class="faq-hero-subtitle" data-i18n="faq_subtitle">
            Temukan jawaban untuk pertanyaan umum seputar Program Studi Bahasa dan Sastra Inggris.
        </p>
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="<?= PUBLIC_URL ?>index.php" data-i18n="home">Beranda</a>
            <span aria-hidden="true"><i class="fa-solid fa-chevron-right"></i></span>
            <span aria-current="page" data-i18n="faq_breadcrumb">FAQ</span>
        </nav>
    </div>
    <div class="faq-hero-decor" aria-hidden="true">
        <div class="faq-orb faq-orb-1"></div>
        <div class="faq-orb faq-orb-2"></div>
    </div>
</section>

<!-- ============ SEARCH & STATS ============ -->
<section class="section faq-search-section">
    <div class="faq-search-box">
        <div class="faq-search-wrap">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" id="faqSearch" placeholder="<?= $current_lang === 'en' ? 'Search questions...' : 'Cari pertanyaan...' ?>" class="faq-search-input">
        </div>
        <div class="faq-search-stats">
            <span><strong><?= $total_faq ?></strong> <?= $current_lang === 'en' ? 'questions' : 'pertanyaan' ?></span>
            <span><strong><?= count($all_categories) ?></strong> <?= $current_lang === 'en' ? 'categories' : 'kategori' ?></span>
        </div>
    </div>
    <div class="faq-cat-filters">
        <a href="?kategori=all" class="faq-cat-btn <?= $faq_kategori === 'all' ? 'active' : '' ?>">
            <i class="fa-solid fa-border-all"></i> <?= $current_lang === 'en' ? 'All' : 'Semua' ?>
        </a>
        <?php foreach ($all_categories as $cat): ?>
        <a href="?kategori=<?= urlencode($cat) ?>" class="faq-cat-btn <?= $faq_kategori === $cat ? 'active' : '' ?>">
            <?= htmlspecialchars($cat) ?>
        </a>
        <?php endforeach; ?>
    </div>
</section>

<!-- ============ FAQ ACCORDION ============ -->
<section class="section faq-list-section">
    <?php if (empty($faq_grouped)): ?>
    <div class="faq-empty">
        <div class="faq-empty-icon"><i class="fa-solid fa-circle-question"></i></div>
        <h2 data-i18n="faq_empty_title">Belum Ada FAQ</h2>
        <p data-i18n="faq_empty_desc">Pertanyaan umum sedang dalam proses penyusunan.</p>
    </div>
    <?php else: ?>
    <?php foreach ($faq_grouped as $kategori => $items): ?>
    <div class="faq-category-block" data-category="<?= htmlspecialchars($kategori) ?>">
        <h2 class="faq-category-title">
            <i class="fa-solid fa-folder-open"></i>
            <?= htmlspecialchars($kategori) ?>
            <span class="faq-category-count"><?= count($items) ?></span>
        </h2>
        <div class="faq-accordion">
            <?php foreach ($items as $idx => $faq): ?>
            <div class="faq-item" data-question="<?= strtolower(htmlspecialchars($faq['pertanyaan'] . ' ' . $faq['jawaban'])) ?>">
                <button class="faq-question" onclick="toggleFaq(this)" aria-expanded="false">
                    <span class="faq-q-icon"><i class="fa-solid fa-circle-question"></i></span>
                    <span class="faq-q-text"><?= htmlspecialchars($faq['pertanyaan']) ?></span>
                    <span class="faq-q-arrow"><i class="fa-solid fa-chevron-down"></i></span>
                </button>
                <div class="faq-answer">
                    <div class="faq-answer-inner">
                        <p><?= nl2br(htmlspecialchars($faq['jawaban'])) ?></p>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</section>

<!-- ============ CTA SECTION ============ -->
<section class="faq-cta-section">
    <div class="faq-cta-bg" aria-hidden="true">
        <div class="faq-cta-orb faq-cta-orb-1"></div>
        <div class="faq-cta-orb faq-cta-orb-2"></div>
    </div>
    <div class="faq-cta-box">
        <div class="faq-cta-icon"><i class="fa-solid fa-headset"></i></div>
        <h2 data-i18n="faq_cta_title">Masih Punya Pertanyaan?</h2>
        <p data-i18n="faq_cta_desc">Tim kami siap membantu menjawab pertanyaan Anda seputar program studi.</p>
        <div class="faq-cta-buttons">
            <a href="<?= PUBLIC_URL ?>kontak.php" class="btn-primary magnetic-btn">
                <i class="fa-solid fa-envelope"></i>
                <span data-i18n="faq_cta_contact">Hubungi Kami</span>
            </a>
            <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $setting['whatsapp'] ?? '6281234567890') ?>" target="_blank" class="btn-secondary magnetic-btn" style="border-color:#fff;color:#fff;">
                <i class="fa-brands fa-whatsapp"></i>
                <span>WhatsApp</span>
            </a>
        </div>
    </div>
</section>

</main>

<!-- ============================================================
     PREMIUM CSS — FAQ
============================================================ -->
<style>
/* ===== HERO ===== */
.faq-hero { position: relative; padding: 11rem 1.5rem 5rem; overflow: hidden; }
.faq-hero-content { max-width: 900px; margin: 0 auto; position: relative; z-index: 2; text-align: center; }
.faq-hero-badge { display: inline-flex; align-items: center; gap: .6rem; padding: .5rem 1.3rem; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); backdrop-filter: blur(10px); border-radius: 50px; font-size: .75rem; font-weight: 800; letter-spacing: 3px; color: var(--sky-blue); margin-bottom: 1.5rem; }
.faq-hero-badge i { color: var(--crimson); }
.faq-hero-title { font-size: clamp(2.2rem, 7vw, 4.5rem); font-weight: 900; line-height: 1.05; margin-bottom: 1.2rem; background: linear-gradient(135deg, var(--sky-blue), #fff); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent; }
.faq-hero-subtitle { font-size: 1.2rem; color: #cfd8dc; max-width: 700px; margin: 0 auto 2rem; line-height: 1.7; }
.faq-hero .breadcrumb { justify-content: center; font-size: .9rem; }
.faq-hero .breadcrumb i { font-size: .7rem; color: var(--sky-blue); }
.faq-hero-decor { position: absolute; inset: 0; pointer-events: none; overflow: hidden; }
.faq-orb { position: absolute; border-radius: 50%; filter: blur(80px); opacity: .4; }
.faq-orb-1 { width: 400px; height: 400px; background: var(--crimson); top: -100px; left: -100px; animation: faqOrbFloat 20s ease-in-out infinite; }
.faq-orb-2 { width: 350px; height: 350px; background: var(--sky-blue-dark); bottom: -100px; right: -100px; animation: faqOrbFloat 25s ease-in-out infinite reverse; }
@keyframes faqOrbFloat { 0%, 100% { transform: translate(0, 0) scale(1); } 33% { transform: translate(50px, -30px) scale(1.1); } 66% { transform: translate(-30px, 50px) scale(0.9); } }

/* ===== SEARCH ===== */
.faq-search-section { padding-bottom: 1rem; }
.faq-search-box { display: flex; align-items: center; gap: 1.5rem; background: #fff; padding: 1.2rem 1.5rem; border-radius: 16px; box-shadow: 0 8px 25px rgba(0,0,0,.06); margin-bottom: 1.2rem; flex-wrap: wrap; }
[data-theme="dark"] .faq-search-box { background: #1a1a2e; }
.faq-search-wrap { position: relative; flex: 1; min-width: 250px; }
.faq-search-wrap i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--text-grey); }
.faq-search-input { width: 100%; padding: .7rem 1rem .7rem 42px; border: 1px solid #e0e0e0; border-radius: 10px; background: var(--bg-light); color: var(--rich-black); font-size: .9rem; }
[data-theme="dark"] .faq-search-input { background: #0f0f1a; border-color: #2a2a3e; color: #fff; }
.faq-search-input:focus { outline: none; border-color: var(--crimson); }
.faq-search-stats { display: flex; gap: 1.5rem; font-size: .85rem; color: var(--text-grey); }
.faq-search-stats strong { color: var(--crimson); font-weight: 800; }
.faq-cat-filters { display: flex; flex-wrap: wrap; gap: .5rem; }
.faq-cat-btn { padding: .5rem 1rem; border-radius: 50px; background: #fff; color: var(--text-grey); font-size: .82rem; font-weight: 600; text-decoration: none; transition: all .2s ease; border: 1px solid #e0e0e0; }
[data-theme="dark"] .faq-cat-btn { background: #1a1a2e; border-color: #2a2a3e; }
.faq-cat-btn:hover, .faq-cat-btn.active { background: var(--crimson); color: #fff; border-color: var(--crimson); }

/* ===== FAQ ACCORDION ===== */
.faq-list-section { padding-top: 1rem; }
.faq-category-block { margin-bottom: 2.5rem; }
.faq-category-title { display: flex; align-items: center; gap: .8rem; font-size: 1.3rem; font-weight: 800; color: var(--rich-black); margin-bottom: 1.2rem; padding-bottom: .8rem; border-bottom: 2px solid var(--crimson); }
[data-theme="dark"] .faq-category-title { color: #fff; }
.faq-category-title i { color: var(--crimson); }
.faq-category-count { margin-left: auto; background: var(--crimson); color: #fff; font-size: .75rem; padding: .2rem .7rem; border-radius: 50px; font-weight: 700; }
.faq-accordion { display: flex; flex-direction: column; gap: .8rem; }
.faq-item { background: #fff; border-radius: 14px; box-shadow: 0 4px 15px rgba(0,0,0,.05); overflow: hidden; border: 1px solid transparent; transition: all .3s ease; }
[data-theme="dark"] .faq-item { background: #1a1a2e; }
.faq-item:hover { border-color: rgba(138,21,56,.3); }
.faq-item.open { border-color: var(--crimson); box-shadow: 0 8px 30px rgba(138,21,56,.12); }
.faq-question { width: 100%; display: flex; align-items: center; gap: 1rem; padding: 1.2rem 1.5rem; background: none; border: none; cursor: pointer; text-align: left; font-family: inherit; transition: background .2s ease; }
.faq-question:hover { background: rgba(138,21,56,.03); }
.faq-q-icon { width: 36px; height: 36px; border-radius: 10px; background: rgba(138,21,56,.1); color: var(--crimson); display: flex; align-items: center; justify-content: center; font-size: .9rem; flex-shrink: 0; transition: all .3s ease; }
.faq-item.open .faq-q-icon { background: var(--crimson); color: #fff; }
.faq-q-text { flex: 1; font-size: .95rem; font-weight: 700; color: var(--rich-black); line-height: 1.4; }
[data-theme="dark"] .faq-q-text { color: #fff; }
.faq-q-arrow { color: var(--text-grey); font-size: .8rem; transition: transform .3s ease; flex-shrink: 0; }
.faq-item.open .faq-q-arrow { transform: rotate(180deg); color: var(--crimson); }
.faq-answer { max-height: 0; overflow: hidden; transition: max-height .4s cubic-bezier(0.22, 0.61, 0.36, 1); }
.faq-answer-inner { padding: 0 1.5rem 1.2rem 4.2rem; color: var(--text-grey); font-size: .92rem; line-height: 1.8; }
[data-theme="dark"] .faq-answer-inner { color: #b0b0c0; }
.faq-item.open .faq-answer { max-height: 500px; }

/* ===== EMPTY ===== */
.faq-empty { text-align: center; padding: 5rem 2rem; background: #fff; border-radius: 24px; box-shadow: 0 10px 40px rgba(0,0,0,.08); max-width: 700px; margin: 0 auto; }
[data-theme="dark"] .faq-empty { background: #1a1a2e; }
.faq-empty-icon { width: 120px; height: 120px; margin: 0 auto 1.5rem; background: linear-gradient(135deg, rgba(138,21,56,0.1), rgba(135,206,235,0.1)); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 3.5rem; color: var(--crimson); }
.faq-empty h2 { color: var(--rich-black); margin-bottom: .5rem; font-size: 1.5rem; font-weight: 800; }
[data-theme="dark"] .faq-empty h2 { color: #fff; }
.faq-empty p { color: var(--text-grey); }

/* ===== CTA ===== */
.faq-cta-section { padding: 6rem 1.5rem; background: linear-gradient(180deg, #fff 0%, var(--bg-light) 100%); position: relative; overflow: hidden; }
[data-theme="dark"] .faq-cta-section { background: linear-gradient(180deg, var(--rich-black), #0f0f1a); }
.faq-cta-bg { position: absolute; inset: 0; pointer-events: none; }
.faq-cta-orb { position: absolute; border-radius: 50%; filter: blur(80px); opacity: .3; }
.faq-cta-orb-1 { width: 400px; height: 400px; background: var(--crimson); top: -150px; left: -100px; animation: faqOrbFloat 25s ease-in-out infinite; }
.faq-cta-orb-2 { width: 350px; height: 350px; background: var(--sky-blue); bottom: -150px; right: -100px; animation: faqOrbFloat 30s ease-in-out infinite reverse; }
.faq-cta-box { max-width: 800px; margin: 0 auto; text-align: center; padding: 4rem 3rem; background: linear-gradient(135deg, var(--crimson), var(--crimson-light, #A02050)); border-radius: 28px; color: #fff; box-shadow: 0 30px 80px rgba(138,21,56,.35); position: relative; overflow: hidden; z-index: 2; }
.faq-cta-icon { width: 90px; height: 90px; margin: 0 auto 1.5rem; border-radius: 50%; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; font-size: 2.5rem; backdrop-filter: blur(10px); position: relative; z-index: 2; }
.faq-cta-box h2 { font-size: clamp(1.5rem, 4vw, 2.2rem); margin-bottom: 1rem; font-weight: 900; position: relative; z-index: 2; }
.faq-cta-box p { color: rgba(255,255,255,.9); margin-bottom: 2rem; line-height: 1.8; max-width: 600px; margin-left: auto; margin-right: auto; position: relative; z-index: 2; }
.faq-cta-buttons { display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap; position: relative; z-index: 2; }
.faq-cta-buttons .btn-primary { background: #fff; color: var(--crimson); margin-top: 0; box-shadow: 0 12px 35px rgba(0,0,0,.25); }

/* ===== RESPONSIVE ===== */
@media (max-width: 768px) {
    .faq-hero { padding: 9rem 1rem 4rem; }
    .faq-search-box { flex-direction: column; }
    .faq-search-wrap { width: 100%; }
    .faq-question { padding: 1rem; }
    .faq-answer-inner { padding: 0 1rem 1rem 1rem; }
    .faq-cta-box { padding: 2.5rem 1.5rem; }
}
</style>

<!-- ============================================================
     JAVASCRIPT — FAQ
============================================================ -->
<script>
function toggleFaq(btn) {
    const item = btn.closest('.faq-item');
    const isOpen = item.classList.contains('open');
    // Close all in same accordion
    item.closest('.faq-accordion').querySelectorAll('.faq-item.open').forEach(el => {
        el.classList.remove('open');
        el.querySelector('.faq-question').setAttribute('aria-expanded', 'false');
    });
    if (!isOpen) {
        item.classList.add('open');
        btn.setAttribute('aria-expanded', 'true');
    }
}

(function () {
    'use strict';
    // Live search
    const searchInput = document.getElementById('faqSearch');
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            const q = this.value.toLowerCase().trim();
            document.querySelectorAll('.faq-item').forEach(item => {
                const text = item.getAttribute('data-question') || '';
                item.style.display = (!q || text.includes(q)) ? '' : 'none';
            });
            // Hide empty categories
            document.querySelectorAll('.faq-category-block').forEach(block => {
                const visible = block.querySelectorAll('.faq-item:not([style*="display: none"])');
                block.style.display = visible.length > 0 ? '' : 'none';
            });
        });
    }
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
            'faq_badge': 'FAQ',
            'faq_title': 'Frequently Asked Questions',
            'faq_subtitle': 'Find answers to common questions about the English Language and Literature Study Program.',
            'home': 'Home',
            'faq_breadcrumb': 'FAQ',
            'faq_empty_title': 'No FAQ Yet',
            'faq_empty_desc': 'Common questions are being compiled.',
            'faq_cta_title': 'Still Have Questions?',
            'faq_cta_desc': 'Our team is ready to help answer your questions about the study program.',
            'faq_cta_contact': 'Contact Us'
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