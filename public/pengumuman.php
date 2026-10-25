<?php
require_once '../includes/config.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';
check_ip_security($pdo);

$page_title = 'Pengumuman';
require_once '../includes/public_header.php';

$setting = get_all('settings', 'id = 1')[0];
$current_lang = $_COOKIE['pbsi_lang'] ?? 'id';
$today = date('Y-m-d');

// Filter
$filter_target = sanitize($_GET['target'] ?? 'semua');
$filter_status = sanitize($_GET['status'] ?? 'aktif');

$sql = "SELECT * FROM pengumuman WHERE status = ? AND (tanggal_berakhir IS NULL OR tanggal_berakhir >= ?)";
$params = [$filter_status, $today];
if ($filter_target !== 'semua') { $sql .= " AND (target = ? OR target = 'semua')"; $params[] = $filter_target; }
$sql .= " ORDER BY is_pinned DESC, prioritas DESC, created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$pengumuman = $stmt->fetchAll();

// Separate pinned and regular
$pinned = array_filter($pengumuman, fn($p) => $p['is_pinned']);
$regular = array_filter($pengumuman, fn($p) => !$p['is_pinned']);

function prioritas_badge($p) {
    $map = ['penting' => ['bg' => 'rgba(231,76,60,.15)', 'color' => '#e74c3c', 'label' => 'PENTING'],
            'tinggi' => ['bg' => 'rgba(243,156,18,.15)', 'color' => '#f39c12', 'label' => 'TINGGI'],
            'normal' => ['bg' => 'rgba(135,206,235,.15)', 'color' => '#5BA0C9', 'label' => 'NORMAL'],
            'rendah' => ['bg' => 'rgba(255,255,255,.08)', 'color' => '#8a8a9e', 'label' => 'RENDAH']];
    return $map[$p] ?? $map['normal'];
}
?>

<main id="main-content" role="main">

<!-- ============ PAGE HERO ============ -->
<section class="page-hero peng-hero">
    <div class="peng-hero-content">
        <span class="peng-hero-badge">
            <i class="fa-solid fa-bullhorn" aria-hidden="true"></i>
            <span data-i18n="peng_badge">PENGUMUMAN</span>
        </span>
        <h1 class="peng-hero-title" data-i18n="peng_title">Pengumuman Program Studi</h1>
        <p class="peng-hero-subtitle" data-i18n="peng_subtitle">
            Informasi terbaru dan penting seputar kegiatan akademik Program Studi Bahasa dan Sastra Inggris.
        </p>
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="<?= PUBLIC_URL ?>index.php" data-i18n="home">Beranda</a>
            <span aria-hidden="true"><i class="fa-solid fa-chevron-right"></i></span>
            <span aria-current="page" data-i18n="peng_breadcrumb">Pengumuman</span>
        </nav>
    </div>
    <div class="peng-hero-decor" aria-hidden="true">
        <div class="peng-orb peng-orb-1"></div>
        <div class="peng-orb peng-orb-2"></div>
    </div>
</section>

<!-- ============ FILTER ============ -->
<section class="section peng-filter-section">
    <form method="GET" class="peng-filter-bar">
        <div class="peng-filter-group">
            <label data-i18n="peng_target_label">Target:</label>
            <select name="target" class="peng-select">
                <option value="semua" <?= $filter_target === 'semua' ? 'selected' : '' ?>><?= $current_lang === 'en' ? 'All' : 'Semua' ?></option>
                <option value="mahasiswa" <?= $filter_target === 'mahasiswa' ? 'selected' : '' ?>><?= $current_lang === 'en' ? 'Students' : 'Mahasiswa' ?></option>
                <option value="dosen" <?= $filter_target === 'dosen' ? 'selected' : '' ?>><?= $current_lang === 'en' ? 'Lecturers' : 'Dosen' ?></option>
                <option value="staff" <?= $filter_target === 'staff' ? 'selected' : '' ?>>Staff</option>
            </select>
        </div>
        <button type="submit" class="peng-filter-btn"><i class="fa-solid fa-filter"></i> <?= $current_lang === 'en' ? 'Filter' : 'Terapkan' ?></button>
    </form>
</section>

<!-- ============ PINNED ANNOUNCEMENTS ============ -->
<?php if (!empty($pinned)): ?>
<section class="section peng-pinned-section">
    <h2 class="peng-section-title"><i class="fa-solid fa-thumbtack"></i> <?= $current_lang === 'en' ? 'Pinned Announcements' : 'Pengumuman Utama' ?></h2>
    <div class="peng-pinned-grid">
        <?php foreach ($pinned as $p): $badge = prioritas_badge($p['prioritas']); ?>
        <div class="peng-pinned-card">
            <div class="peng-pinned-ribbon"><?= $badge['label'] ?></div>
            <div class="peng-pinned-body">
                <div class="peng-pinned-meta">
                    <span class="peng-target-badge"><i class="fa-solid fa-users"></i> <?= ucfirst(htmlspecialchars($p['target'])) ?></span>
                    <span class="peng-date"><i class="fa-regular fa-calendar"></i> <?= date('d M Y', strtotime($p['created_at'])) ?></span>
                </div>
                <h3 class="peng-pinned-title"><?= htmlspecialchars($p['judul']) ?></h3>
                <p class="peng-pinned-content"><?= nl2br(htmlspecialchars($p['konten'])) ?></p>
                <?php if ($p['tanggal_mulai'] || $p['tanggal_berakhir']): ?>
                <div class="peng-pinned-period">
                    <i class="fa-solid fa-clock"></i>
                    <?= $p['tanggal_mulai'] ? date('d M Y', strtotime($p['tanggal_mulai'])) : '—' ?> → <?= $p['tanggal_berakhir'] ? date('d M Y', strtotime($p['tanggal_berakhir'])) : ($current_lang === 'en' ? 'Ongoing' : 'Berlangsung') ?>
                </div>
                <?php endif; ?>
                <?php if ($p['link']): ?>
                <a href="<?= htmlspecialchars($p['link']) ?>" target="_blank" rel="noopener" class="peng-pinned-link">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i> <?= $current_lang === 'en' ? 'Read More' : 'Selengkapnya' ?>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- ============ REGULAR ANNOUNCEMENTS ============ -->
<section class="section peng-list-section">
    <h2 class="peng-section-title"><i class="fa-solid fa-list"></i> <?= $current_lang === 'en' ? 'All Announcements' : 'Semua Pengumuman' ?></h2>
    <?php if (empty($regular) && empty($pinned)): ?>
    <div class="peng-empty">
        <div class="peng-empty-icon"><i class="fa-solid fa-bullhorn"></i></div>
        <h2 data-i18n="peng_empty_title">Belum Ada Pengumuman</h2>
        <p data-i18n="peng_empty_desc">Pengumuman terbaru akan ditampilkan di sini.</p>
    </div>
    <?php elseif (empty($regular)): ?>
    <p style="text-align:center;color:var(--text-grey);padding:2rem;"><?= $current_lang === 'en' ? 'No additional announcements.' : 'Tidak ada pengumuman tambahan.' ?></p>
    <?php else: ?>
    <div class="peng-timeline">
        <?php foreach ($regular as $p): $badge = prioritas_badge($p['prioritas']); ?>
        <div class="peng-timeline-item">
            <div class="peng-timeline-dot" style="background: <?= $badge['color'] ?>;"></div>
            <div class="peng-timeline-card">
                <div class="peng-timeline-header">
                    <span class="peng-prio-badge" style="background:<?= $badge['bg'] ?>;color:<?= $badge['color'] ?>;"><?= $badge['label'] ?></span>
                    <span class="peng-target-badge"><i class="fa-solid fa-users"></i> <?= ucfirst(htmlspecialchars($p['target'])) ?></span>
                    <span class="peng-date"><i class="fa-regular fa-calendar"></i> <?= date('d M Y', strtotime($p['created_at'])) ?></span>
                </div>
                <h3 class="peng-timeline-title"><?= htmlspecialchars($p['judul']) ?></h3>
                <p class="peng-timeline-content"><?= nl2br(htmlspecialchars(substr($p['konten'], 0, 200))) ?><?= strlen($p['konten']) > 200 ? '...' : '' ?></p>
                <?php if ($p['tanggal_mulai'] || $p['tanggal_berakhir']): ?>
                <div class="peng-timeline-period">
                    <i class="fa-solid fa-clock"></i>
                    <?= $p['tanggal_mulai'] ? date('d M Y', strtotime($p['tanggal_mulai'])) : '—' ?> → <?= $p['tanggal_berakhir'] ? date('d M Y', strtotime($p['tanggal_berakhir'])) : ($current_lang === 'en' ? 'Ongoing' : 'Berlangsung') ?>
                </div>
                <?php endif; ?>
                <?php if ($p['link']): ?>
                <a href="<?= htmlspecialchars($p['link']) ?>" target="_blank" rel="noopener" class="peng-timeline-link">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i> <?= $current_lang === 'en' ? 'Read More' : 'Selengkapnya' ?>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>

</main>

<!-- ============================================================
     PREMIUM CSS — PENGUMUMAN
============================================================ -->
<style>
/* ===== HERO ===== */
.peng-hero { position: relative; padding: 11rem 1.5rem 5rem; overflow: hidden; }
.peng-hero-content { max-width: 900px; margin: 0 auto; position: relative; z-index: 2; text-align: center; }
.peng-hero-badge { display: inline-flex; align-items: center; gap: .6rem; padding: .5rem 1.3rem; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); backdrop-filter: blur(10px); border-radius: 50px; font-size: .75rem; font-weight: 800; letter-spacing: 3px; color: var(--sky-blue); margin-bottom: 1.5rem; }
.peng-hero-badge i { color: var(--crimson); }
.peng-hero-title { font-size: clamp(2.2rem, 7vw, 4.5rem); font-weight: 900; line-height: 1.05; margin-bottom: 1.2rem; background: linear-gradient(135deg, var(--sky-blue), #fff); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent; }
.peng-hero-subtitle { font-size: 1.2rem; color: #cfd8dc; max-width: 700px; margin: 0 auto 2rem; line-height: 1.7; }
.peng-hero .breadcrumb { justify-content: center; font-size: .9rem; }
.peng-hero .breadcrumb i { font-size: .7rem; color: var(--sky-blue); }
.peng-hero-decor { position: absolute; inset: 0; pointer-events: none; overflow: hidden; }
.peng-orb { position: absolute; border-radius: 50%; filter: blur(80px); opacity: .4; }
.peng-orb-1 { width: 400px; height: 400px; background: var(--crimson); top: -100px; left: -100px; animation: pengOrbFloat 20s ease-in-out infinite; }
.peng-orb-2 { width: 350px; height: 350px; background: var(--sky-blue-dark); bottom: -100px; right: -100px; animation: pengOrbFloat 25s ease-in-out infinite reverse; }
@keyframes pengOrbFloat { 0%, 100% { transform: translate(0, 0) scale(1); } 33% { transform: translate(50px, -30px) scale(1.1); } 66% { transform: translate(-30px, 50px) scale(0.9); } }

/* ===== FILTER ===== */
.peng-filter-section { padding-bottom: 0; }
.peng-filter-bar { display: flex; flex-wrap: wrap; gap: 1rem; align-items: center; background: #fff; padding: 1rem 1.5rem; border-radius: 14px; box-shadow: 0 8px 25px rgba(0,0,0,.06); }
[data-theme="dark"] .peng-filter-bar { background: #1a1a2e; }
.peng-filter-group { display: flex; align-items: center; gap: .6rem; }
.peng-filter-group label { font-size: .85rem; font-weight: 600; color: var(--text-grey); }
.peng-select { padding: .6rem 1rem; border: 1px solid #e0e0e0; border-radius: 10px; background: var(--bg-light); color: var(--rich-black); font-size: .88rem; cursor: pointer; }
[data-theme="dark"] .peng-select { background: #0f0f1a; border-color: #2a2a3e; color: #fff; }
.peng-select:focus { outline: none; border-color: var(--crimson); }
.peng-filter-btn { padding: .6rem 1.5rem; background: var(--crimson); color: #fff; border: none; border-radius: 10px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: .5rem; transition: all .2s ease; margin-left: auto; }
.peng-filter-btn:hover { background: var(--crimson-light, #A02050); }

/* ===== SECTION TITLE ===== */
.peng-section-title { display: flex; align-items: center; gap: .8rem; font-size: 1.4rem; font-weight: 800; color: var(--rich-black); margin-bottom: 1.5rem; }
[data-theme="dark"] .peng-section-title { color: #fff; }
.peng-section-title i { color: var(--crimson); }

/* ===== PINNED ===== */
.peng-pinned-section { padding-bottom: 1rem; }
.peng-pinned-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 1.5rem; }
.peng-pinned-card { background: linear-gradient(135deg, var(--crimson), var(--crimson-light, #A02050)); border-radius: 20px; padding: 2rem; color: #fff; position: relative; overflow: hidden; box-shadow: 0 15px 40px rgba(138,21,56,.3); }
.peng-pinned-ribbon { position: absolute; top: 15px; right: -30px; background: #f1c40f; color: #000; font-size: .7rem; font-weight: 800; padding: .3rem 2.5rem; transform: rotate(45deg); letter-spacing: 1px; }
.peng-pinned-body { position: relative; z-index: 2; }
.peng-pinned-meta { display: flex; gap: 1rem; margin-bottom: 1rem; flex-wrap: wrap; }
.peng-target-badge { display: inline-flex; align-items: center; gap: .4rem; background: rgba(255,255,255,.15); padding: .25rem .7rem; border-radius: 50px; font-size: .75rem; font-weight: 600; }
.peng-date { display: inline-flex; align-items: center; gap: .4rem; font-size: .78rem; opacity: .85; }
.peng-pinned-title { font-size: 1.3rem; font-weight: 800; margin: 0 0 .8rem; line-height: 1.3; }
.peng-pinned-content { font-size: .9rem; line-height: 1.7; opacity: .92; margin-bottom: 1rem; }
.peng-pinned-period { display: flex; align-items: center; gap: .5rem; font-size: .82rem; opacity: .85; margin-bottom: 1rem; padding: .5rem .8rem; background: rgba(255,255,255,.1); border-radius: 8px; }
.peng-pinned-link { display: inline-flex; align-items: center; gap: .5rem; color: #fff; font-weight: 700; font-size: .88rem; text-decoration: none; padding: .5rem 1.2rem; background: rgba(255,255,255,.2); border-radius: 50px; transition: all .2s ease; }
.peng-pinned-link:hover { background: #fff; color: var(--crimson); }

/* ===== TIMELINE ===== */
.peng-timeline { position: relative; padding-left: 2rem; }
.peng-timeline::before { content: ''; position: absolute; left: 8px; top: 0; bottom: 0; width: 2px; background: linear-gradient(180deg, var(--crimson), var(--sky-blue), var(--emerald)); border-radius: 2px; }
.peng-timeline-item { position: relative; margin-bottom: 1.5rem; }
.peng-timeline-dot { position: absolute; left: -2rem; top: 1.5rem; width: 18px; height: 18px; border-radius: 50%; border: 3px solid #fff; box-shadow: 0 0 0 2px var(--crimson); z-index: 2; }
[data-theme="dark"] .peng-timeline-dot { border-color: #1a1a2e; }
.peng-timeline-card { background: #fff; border-radius: 16px; padding: 1.5rem; box-shadow: 0 8px 25px rgba(0,0,0,.06); transition: all .3s ease; border: 1px solid transparent; }
[data-theme="dark"] .peng-timeline-card { background: #1a1a2e; }
.peng-timeline-card:hover { transform: translateX(5px); border-color: rgba(138,21,56,.2); }
.peng-timeline-header { display: flex; gap: .8rem; align-items: center; margin-bottom: .8rem; flex-wrap: wrap; }
.peng-prio-badge { display: inline-block; padding: .2rem .6rem; border-radius: 50px; font-size: .7rem; font-weight: 800; letter-spacing: .5px; }
.peng-timeline-title { font-size: 1.1rem; font-weight: 700; color: var(--rich-black); margin: 0 0 .5rem; line-height: 1.3; }
[data-theme="dark"] .peng-timeline-title { color: #fff; }
.peng-timeline-content { font-size: .88rem; color: var(--text-grey); line-height: 1.7; margin-bottom: .8rem; }
.peng-timeline-period { display: flex; align-items: center; gap: .5rem; font-size: .8rem; color: var(--text-grey); margin-bottom: .8rem; }
.peng-timeline-link { display: inline-flex; align-items: center; gap: .4rem; color: var(--crimson); font-weight: 700; font-size: .85rem; text-decoration: none; }
.peng-timeline-link:hover { text-decoration: underline; }

/* ===== EMPTY ===== */
.peng-empty { text-align: center; padding: 5rem 2rem; background: #fff; border-radius: 24px; box-shadow: 0 10px 40px rgba(0,0,0,.08); max-width: 700px; margin: 0 auto; }
[data-theme="dark"] .peng-empty { background: #1a1a2e; }
.peng-empty-icon { width: 120px; height: 120px; margin: 0 auto 1.5rem; background: linear-gradient(135deg, rgba(138,21,56,0.1), rgba(135,206,235,0.1)); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 3.5rem; color: var(--crimson); }
.peng-empty h2 { color: var(--rich-black); margin-bottom: .5rem; font-size: 1.5rem; font-weight: 800; }
[data-theme="dark"] .peng-empty h2 { color: #fff; }
.peng-empty p { color: var(--text-grey); }

/* ===== RESPONSIVE ===== */
@media (max-width: 768px) {
    .peng-hero { padding: 9rem 1rem 4rem; }
    .peng-pinned-grid { grid-template-columns: 1fr; }
    .peng-timeline { padding-left: 1.5rem; }
    .peng-timeline-dot { left: -1.5rem; }
}
</style>

<!-- Multi-language Support -->
<script>
(function () {
    'use strict';
    const translations = {
        en: {
            'peng_badge': 'ANNOUNCEMENTS',
            'peng_title': 'Study Program Announcements',
            'peng_subtitle': 'Latest and important information about academic activities of the English Language and Literature Study Program.',
            'home': 'Home',
            'peng_breadcrumb': 'Announcements',
            'peng_target_label': 'Target:',
            'peng_empty_title': 'No Announcements Yet',
            'peng_empty_desc': 'Latest announcements will be displayed here.'
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