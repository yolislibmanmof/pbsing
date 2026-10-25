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

$page_title = 'Struktur Organisasi';
require_once '../includes/public_header.php';

$struktur = get_all('struktur', 'aktif = 1', 'level ASC, urutan ASC');
$setting  = get_all('settings', 'id = 1')[0];

$current_lang = $_COOKIE['pbsi_lang'] ?? 'id';

$levels = [];
foreach ($struktur as $s) { $levels[(int)$s['level']][] = $s; }
ksort($levels);

// Statistics per level
$level_stats = [];
foreach ($levels as $lv => $rows) {
    $level_stats[$lv] = count($rows);
}
$total_pejabat = count($struktur);
$total_levels = count($levels);

// Level labels (bisa disesuaikan)
$level_labels = [
    1 => ['label' => 'Pimpinan', 'icon' => 'fa-crown', 'color' => 'crimson'],
    2 => ['label' => 'Koordinator', 'icon' => 'fa-user-tie', 'color' => 'sky'],
    3 => ['label' => 'Staf Inti', 'icon' => 'fa-user-shield', 'color' => 'emerald'],
    4 => ['label' => 'Staf Pendukung', 'icon' => 'fa-user-gear', 'color' => 'warning'],
];
?>

<!-- Reading Progress Bar -->
<div class="reading-progress" id="readingProgress" aria-hidden="true">
    <div class="reading-progress-fill"></div>
</div>

<!-- JSON-LD Structured Data -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "EducationalOrganization",
  "name": "<?= sanitize($setting['site_name'] ?? 'PBSI UMMaumere') ?>",
  "description": "Struktur Organisasi Program Studi Bahasa dan Sastra Inggris UMMaumere",
  "url": "<?= PUBLIC_URL ?>struktur.php",
  "employee": <?= json_encode(array_map(function($s) {
      return [
          "@type" => "Person",
          "name" => $s['nama'],
          "jobTitle" => $s['jabatan'],
          "email" => $s['email'] ?? null
      ];
  }, $struktur), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?>,
  "breadcrumb": {
    "@type": "BreadcrumbList",
    "itemListElement": [
      { "@type": "ListItem", "position": 1, "name": "<?= $current_lang === 'en' ? 'Home' : 'Beranda' ?>", "item": "<?= PUBLIC_URL ?>" },
      { "@type": "ListItem", "position": 2, "name": "<?= $current_lang === 'en' ? 'Organization Structure' : 'Struktur Organisasi' ?>" }
    ]
  }
}
</script>

<main id="main-content" role="main">

<!-- ============ PAGE HERO ============ -->
<section class="page-hero org-hero">
    <div class="org-hero-content reveal-up">
        <span class="org-hero-badge text-reveal">
            <i class="fa-solid fa-sitemap" aria-hidden="true"></i>
            <span data-i18n="team_structure">TIM PENGURUS</span>
        </span>
        <h1 class="org-hero-title text-reveal" data-i18n="page_title">Struktur Organisasi</h1>
        <p class="org-hero-subtitle fade-in-up" data-i18n="page_subtitle">
            Tata kelola program studi yang profesional, transparan, dan kolaboratif.
        </p>
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="<?= PUBLIC_URL ?>index.php" data-i18n="home">Beranda</a>
            <span aria-hidden="true"><i class="fa-solid fa-chevron-right"></i></span>
            <span aria-current="page" data-i18n="structure_breadcrumb">Struktur Organisasi</span>
        </nav>
    </div>

    <!-- Decorative Orbs -->
    <div class="org-hero-decor" aria-hidden="true">
        <div class="org-orb org-orb-1"></div>
        <div class="org-orb org-orb-2"></div>
    </div>

    <div class="org-scroll-hint" aria-hidden="true">
        <div class="org-mouse"><span></span></div>
        <small data-i18n="scroll">SCROLL</small>
    </div>
</section>

<!-- ============ STATS OVERVIEW ============ -->
<?php if (!empty($struktur)): ?>
<section class="org-stats-section" aria-label="Statistik Struktur">
    <div class="section">
        <div class="org-stats-grid">
            <div class="org-stat-card reveal-up accent-crimson">
                <div class="org-stat-icon"><i class="fa-solid fa-users"></i></div>
                <div>
                    <div class="org-stat-value" data-counter="<?= $total_pejabat ?>">0</div>
                    <div class="org-stat-label" data-i18n="total_officials">Total Pejabat</div>
                </div>
            </div>
            <div class="org-stat-card reveal-up accent-sky" style="transition-delay: 0.1s;">
                <div class="org-stat-icon"><i class="fa-solid fa-layer-group"></i></div>
                <div>
                    <div class="org-stat-value" data-counter="<?= $total_levels ?>">0</div>
                    <div class="org-stat-label" data-i18n="hierarchy_levels">Level Hierarki</div>
                </div>
            </div>
            <div class="org-stat-card reveal-up accent-emerald" style="transition-delay: 0.2s;">
                <div class="org-stat-icon"><i class="fa-solid fa-crown"></i></div>
                <div>
                    <div class="org-stat-value"><?= $level_stats[1] ?? 0 ?></div>
                    <div class="org-stat-label" data-i18n="leadership">Pimpinan</div>
                </div>
            </div>
            <div class="org-stat-card reveal-up accent-warning" style="transition-delay: 0.3s;">
                <div class="org-stat-icon"><i class="fa-solid fa-user-tie"></i></div>
                <div>
                    <div class="org-stat-value"><?= ($level_stats[2] ?? 0) + ($level_stats[3] ?? 0) ?></div>
                    <div class="org-stat-label" data-i18n="coordinators">Koordinator & Staf</div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============ STRUCTURE SECTION ============ -->
<?php if (!empty($struktur)): ?>
<section class="org-section" aria-labelledby="org-heading">
    <div class="section">
        <header class="sec-head">
            <span class="sec-eyebrow ey-crimson text-reveal">
                <i class="fa-solid fa-sitemap" aria-hidden="true"></i>
                <span data-i18n="our_structure">STRUKTUR KAMI</span>
            </span>
            <h2 class="section-title text-reveal" id="org-heading" data-i18n="structure_heading">Bagan Organisasi</h2>
            <p class="section-subtitle fade-in-up" data-i18n="structure_subtitle">
                Jajaran pimpinan dan koordinator yang menjalankan roda organisasi program studi.
            </p>
        </header>

        <!-- Search & Actions Bar -->
        <div class="org-actions-bar">
            <div class="org-search-wrap">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <input type="text"
                       id="orgSearch"
                       class="org-search-input"
                       placeholder="<?= $current_lang === 'en' ? 'Search officials by name or position...' : 'Cari pejabat berdasarkan nama atau jabatan...' ?>"
                       aria-label="Search officials">
            </div>
            <div class="org-actions">
                <button class="btn-ghost-action magnetic-btn" onclick="window.print()" aria-label="Print structure">
                    <i class="fa-solid fa-print" aria-hidden="true"></i>
                    <span data-i18n="print">Cetak</span>
                </button>
                <button class="btn-ghost-action magnetic-btn" onclick="downloadStructure()" aria-label="Download structure">
                    <i class="fa-solid fa-download" aria-hidden="true"></i>
                    <span data-i18n="download">Unduh</span>
                </button>
            </div>
        </div>

        <!-- Level Legend -->
        <div class="org-legend reveal-up">
            <?php foreach ($level_stats as $lv => $count):
                $info = $level_labels[$lv] ?? ['label' => 'Level ' . $lv, 'icon' => 'fa-user', 'color' => 'crimson'];
            ?>
            <div class="org-legend-item legend-<?= $info['color'] ?>">
                <div class="org-legend-dot"></div>
                <span class="org-legend-label">
                    <i class="fa-solid <?= $info['icon'] ?>"></i>
                    <?= $info['label'] ?>
                </span>
                <span class="org-legend-count"><?= $count ?></span>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Tree Structure -->
        <div class="org-chart-wrapper" id="orgChartWrapper">
            <div class="org-chart" role="tree" aria-label="Struktur Organisasi PBSI">
                <?php foreach ($levels as $lv => $rows):
                    $info = $level_labels[$lv] ?? ['label' => 'Level ' . $lv, 'icon' => 'fa-user', 'color' => 'crimson'];
                ?>
                <div class="org-level-block" data-level="<?= $lv ?>">
                    <div class="org-level-header reveal-up">
                        <div class="org-level-badge legend-<?= $info['color'] ?>">
                            <i class="fa-solid <?= $info['icon'] ?>"></i>
                            <span><?= $info['label'] ?></span>
                            <span class="org-level-count"><?= count($rows) ?></span>
                        </div>
                        <?php if ($lv > 1): ?>
                        <div class="org-level-connector" aria-hidden="true">
                            <svg class="org-connector-svg" viewBox="0 0 100 40" preserveAspectRatio="none">
                                <path class="org-connector-path" d="M50 0 L50 40" />
                            </svg>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="org-level <?= count($rows) > 1 ? 'multi' : '' ?>" role="group" aria-label="<?= $info['label'] ?>">
                        <?php foreach ($rows as $i => $s):
                            $delay = ($lv * 80) + ($i * 60);
                        ?>
                        <div class="org-node reveal-up"
                             role="treeitem"
                             style="transition-delay: <?= $delay ?>ms;"
                             data-search="<?= strtolower(sanitize($s['nama'] . ' ' . $s['jabatan'] . ' ' . ($s['nidn'] ?? ''))) ?>"
                             data-level="<?= $lv ?>">
                            <article class="org-card lvl-<?= $lv ?> magnetic-card"
                                     data-cursor="hover"
                                     tabindex="0"
                                     onclick="openOrgModal(<?= json_encode($s) ?>)"
                                     onkeypress="if(event.key==='Enter') openOrgModal(<?= json_encode($s) ?>)">
                                <div class="org-card-top">
                                    <div class="org-photo">
                                        <img src="<?= img_url($s['foto'] ?? '') ?>"
                                             alt="Foto <?= sanitize($s['nama']) ?>"
                                             loading="lazy"
                                             onerror="this.onerror=null;this.src='<?= img_placeholder() ?>';">
                                        <div class="org-photo-overlay">
                                            <i class="fa-solid fa-eye"></i>
                                            <span data-i18n="view_profile">Lihat Profil</span>
                                        </div>
                                    </div>
                                    <div class="org-level-indicator legend-<?= $info['color'] ?>">
                                        <i class="fa-solid <?= $info['icon'] ?>"></i>
                                    </div>
                                </div>
                                <div class="org-info">
                                    <h3><?= sanitize($s['nama']) ?></h3>
                                    <span class="org-jab"><?= sanitize($s['jabatan']) ?></span>
                                    <?php if (!empty($s['nidn'])): ?>
                                    <p class="org-nidn">
                                        <i class="fa-solid fa-id-card" aria-hidden="true"></i>
                                        NIDN: <?= sanitize($s['nidn']) ?>
                                    </p>
                                    <?php endif; ?>
                                    <div class="org-socials">
                                        <?php if (!empty($s['email'])): ?>
                                        <a href="mailto:<?= sanitize($s['email']) ?>"
                                           onclick="event.stopPropagation()"
                                           aria-label="Email <?= sanitize($s['nama']) ?>"
                                           title="Email">
                                            <i class="fa-solid fa-envelope" aria-hidden="true"></i>
                                        </a>
                                        <?php endif; ?>
                                        <?php if (!empty($s['link_scholar'])): ?>
                                        <a href="<?= sanitize($s['link_scholar']) ?>"
                                           target="_blank"
                                           rel="noopener noreferrer"
                                           onclick="event.stopPropagation()"
                                           aria-label="Google Scholar <?= sanitize($s['nama']) ?>"
                                           title="Google Scholar">
                                            <i class="fa-solid fa-graduation-cap" aria-hidden="true"></i>
                                        </a>
                                        <?php endif; ?>
                                        <?php if (!empty($s['whatsapp'])): ?>
                                        <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $s['whatsapp']) ?>"
                                           target="_blank"
                                           rel="noopener noreferrer"
                                           onclick="event.stopPropagation()"
                                           aria-label="WhatsApp <?= sanitize($s['nama']) ?>"
                                           title="WhatsApp"
                                           class="wa-link">
                                            <i class="fa-brands fa-whatsapp" aria-hidden="true"></i>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </article>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Empty Search State -->
        <div class="org-search-empty" id="orgSearchEmpty" style="display:none;">
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
            <h3 data-i18n="no_results">Tidak Ada Hasil</h3>
            <p data-i18n="no_results_desc">Coba kata kunci lain atau reset pencarian.</p>
        </div>
    </div>
</section>

<!-- ============ PRINSIP TATA KELOLA ============ -->
<section class="path-section" aria-labelledby="path-heading">
    <div class="section">
        <header class="sec-head">
            <span class="sec-eyebrow ey-glass text-reveal">
                <i class="fa-solid fa-scale-balanced" aria-hidden="true"></i>
                <span data-i18n="our_principles">PRINSIP KAMI</span>
            </span>
            <h2 class="section-title text-reveal" id="path-heading" style="color:#fff;" data-i18n="principles_heading">Prinsip Tata Kelola Kami</h2>
            <p class="section-subtitle fade-in-up" style="color:#bdc3c7;" data-i18n="principles_subtitle">
                Empat pilar yang menjadi landasan operasional program studi.
            </p>
        </header>
        <div class="principles-grid" role="list">
            <?php
            $principles = [
                [
                    'icon' => 'fa-eye',
                    'title' => 'Transparan',
                    'title_en' => 'Transparent',
                    'desc' => 'Setiap keputusan akademik terbuka dan dapat dipertanggungjawabkan kepada seluruh pemangku kepentingan.',
                    'desc_en' => 'Every academic decision is open and accountable to all stakeholders.',
                    'color' => 'crimson',
                    'number' => '01'
                ],
                [
                    'icon' => 'fa-handshake-angle',
                    'title' => 'Kolegial',
                    'title_en' => 'Collegial',
                    'desc' => 'Musyawarah dosen dan koordinasi lintas koordinator setiap pekan demi sinergi yang optimal.',
                    'desc_en' => 'Weekly faculty meetings and cross-coordinator coordination for optimal synergy.',
                    'color' => 'sky',
                    'number' => '02'
                ],
                [
                    'icon' => 'fa-award',
                    'title' => 'Berbasis Mutu',
                    'title_en' => 'Quality-Based',
                    'desc' => 'Siklus PPEPP (Penetapan–Pelaksanaan–Evaluasi–Pengendalian–Peningkatan) diterapkan konsisten.',
                    'desc_en' => 'PPEPP cycle (Plan-Do-Check-Act-Improve) applied consistently.',
                    'color' => 'emerald',
                    'number' => '03'
                ],
                [
                    'icon' => 'fa-hands-helping',
                    'title' => 'Melayani',
                    'title_en' => 'Service-Oriented',
                    'desc' => 'Administrasi cepat, ramah, dan berbasis digital untuk kenyamanan seluruh mahasiswa.',
                    'desc_en' => 'Fast, friendly, and digital-based administration for student comfort.',
                    'color' => 'warning',
                    'number' => '04'
                ]
            ];
            foreach ($principles as $i => $p):
            ?>
            <article class="principle-card reveal-up magnetic-card"
                     role="listitem"
                     style="transition-delay: <?= $i * 0.1 ?>s;"
                     data-color="<?= $p['color'] ?>">
                <div class="principle-number"><?= $p['number'] ?></div>
                <div class="principle-icon">
                    <i class="fa-solid <?= $p['icon'] ?>" aria-hidden="true"></i>
                </div>
                <h4 class="principle-title"
                    data-i18n="principle_<?= strtolower(str_replace(' ', '_', $p['title'])) ?>_title"><?= $p['title'] ?></h4>
                <p class="principle-desc"
                   data-i18n="principle_<?= strtolower(str_replace(' ', '_', $p['title'])) ?>_desc"><?= $p['desc'] ?></p>
                <div class="principle-line"></div>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php else: ?>
<!-- ============ EMPTY STATE PREMIUM ============ -->
<section class="section">
    <div class="org-empty reveal-up">
        <div class="org-empty-icon">
            <i class="fa-solid fa-sitemap" aria-hidden="true"></i>
        </div>
        <h2 data-i18n="empty_title">Struktur Belum Tersedia</h2>
        <p data-i18n="empty_desc">Data struktur organisasi sedang dalam proses penyusunan. Silakan kunjungi kembali nanti.</p>
        <a href="<?= PUBLIC_URL ?>kontak.php" class="btn-primary magnetic-btn" data-i18n="contact_us">
            <i class="fa-solid fa-headset" aria-hidden="true"></i>
            <span>Hubungi Kami</span>
        </a>
    </div>
</section>
<?php endif; ?>

</main>

<!-- ============ OFFICIAL DETAIL MODAL ============ -->
<div class="org-modal" id="orgModal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
    <div class="org-modal-backdrop" onclick="closeOrgModal()"></div>
    <div class="org-modal-content">
        <button class="org-modal-close" onclick="closeOrgModal()" aria-label="Close modal">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <div class="org-modal-header">
            <div class="org-modal-photo">
                <img src="" alt="" id="modalPhoto">
            </div>
            <div class="org-modal-info">
                <h2 id="modalTitle"></h2>
                <span class="org-modal-position" id="modalPosition"></span>
                <div class="org-modal-nidn" id="modalNidn" style="display:none;">
                    <i class="fa-solid fa-id-card"></i>
                    <span>NIDN: <strong id="modalNidnValue"></strong></span>
                </div>
            </div>
        </div>

        <div class="org-modal-body">
            <div class="org-modal-section" id="modalBioSection" style="display:none;">
                <h3 data-i18n="about">Tentang</h3>
                <p id="modalBio"></p>
            </div>

            <div class="org-modal-section">
                <h3 data-i18n="contact_info">Informasi Kontak</h3>
                <div class="org-modal-contacts">
                    <a href="" id="modalEmail" class="org-modal-contact-item" style="display:none;">
                        <i class="fa-solid fa-envelope"></i>
                        <div>
                            <strong>Email</strong>
                            <span id="modalEmailValue"></span>
                        </div>
                    </a>
                    <a href="" id="modalScholar" target="_blank" rel="noopener noreferrer" class="org-modal-contact-item" style="display:none;">
                        <i class="fa-solid fa-graduation-cap"></i>
                        <div>
                            <strong>Google Scholar</strong>
                            <span data-i18n="view_profile_scholar">Lihat Profil Akademik</span>
                        </div>
                    </a>
                    <a href="" id="modalWhatsapp" target="_blank" rel="noopener noreferrer" class="org-modal-contact-item" style="display:none;">
                        <i class="fa-brands fa-whatsapp"></i>
                        <div>
                            <strong>WhatsApp</strong>
                            <span data-i18n="chat_direct">Chat Langsung</span>
                        </div>
                    </a>
                </div>
            </div>

            <div class="org-modal-actions">
                <button class="btn-primary magnetic-btn" onclick="contactOfficial()">
                    <i class="fa-solid fa-paper-plane"></i>
                    <span data-i18n="contact_now">Hubungi Sekarang</span>
                </button>
                <button class="btn-secondary magnetic-btn" onclick="shareOfficial()">
                    <i class="fa-solid fa-share-nodes"></i>
                    <span data-i18n="share">Bagikan</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================
     PREMIUM CSS — STRUKTUR ORGANISASI
============================================================ -->
<style>
/* ===== PAGE HERO ===== */
.org-hero {
    position: relative;
    padding: 11rem 1.5rem 5rem;
    overflow: hidden;
}
.org-hero-content {
    max-width: 900px;
    margin: 0 auto;
    position: relative;
    z-index: 2;
    text-align: center;
}
.org-hero-badge {
    display: inline-flex;
    align-items: center;
    gap: .6rem;
    padding: .5rem 1.3rem;
    background: rgba(255,255,255,0.1);
    border: 1px solid rgba(255,255,255,0.2);
    backdrop-filter: blur(10px);
    border-radius: 50px;
    font-size: .75rem;
    font-weight: 800;
    letter-spacing: 3px;
    color: var(--sky-blue);
    margin-bottom: 1.5rem;
}
.org-hero-badge i { color: var(--crimson); }
.org-hero-title {
    font-size: clamp(2.5rem, 8vw, 5rem);
    font-weight: 900;
    line-height: 1.05;
    margin-bottom: 1.2rem;
    background: linear-gradient(135deg, var(--sky-blue), #fff);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
}
.org-hero-subtitle {
    font-size: 1.2rem;
    color: #cfd8dc;
    max-width: 700px;
    margin: 0 auto 2rem;
    line-height: 1.7;
}
.org-hero .breadcrumb {
    justify-content: center;
    font-size: .9rem;
}
.org-hero .breadcrumb i { font-size: .7rem; color: var(--sky-blue); }

/* Hero Decorative */
.org-hero-decor {
    position: absolute;
    inset: 0;
    pointer-events: none;
    overflow: hidden;
}
.org-orb {
    position: absolute;
    border-radius: 50%;
    filter: blur(80px);
    opacity: .4;
}
.org-orb-1 {
    width: 400px; height: 400px;
    background: var(--crimson);
    top: -100px; left: -100px;
    animation: orbFloat 20s ease-in-out infinite;
}
.org-orb-2 {
    width: 350px; height: 350px;
    background: var(--sky-blue-dark);
    bottom: -100px; right: -100px;
    animation: orbFloat 25s ease-in-out infinite reverse;
}
@keyframes orbFloat {
    0%, 100% { transform: translate(0, 0) scale(1); }
    33% { transform: translate(50px, -30px) scale(1.1); }
    66% { transform: translate(-30px, 50px) scale(0.9); }
}

.org-scroll-hint {
    position: absolute;
    bottom: 2rem;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: .5rem;
    color: rgba(255,255,255,.7);
    z-index: 3;
}
.org-scroll-hint small { font-size: .7rem; letter-spacing: 3px; font-weight: 700; }
.org-mouse {
    width: 24px; height: 38px;
    border: 2px solid rgba(255,255,255,.6);
    border-radius: 14px;
    display: flex;
    justify-content: center;
    padding-top: 6px;
}
.org-mouse span {
    width: 3px; height: 8px;
    background: var(--sky-blue);
    border-radius: 3px;
    animation: orgWheel 1.6s ease-in-out infinite;
}
@keyframes orgWheel {
    0% { transform: translateY(0); opacity: 1; }
    70% { transform: translateY(12px); opacity: 0; }
    100% { opacity: 0; }
}

/* ===== STATS OVERVIEW ===== */
.org-stats-section {
    padding: 3rem 1.5rem;
    background: #fff;
    border-bottom: 1px solid rgba(0,0,0,0.05);
}
[data-theme="dark"] .org-stats-section { background: var(--rich-black); border-bottom-color: rgba(255,255,255,0.05); }
.org-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    max-width: 1200px;
    margin: 0 auto;
}
.org-stat-card {
    background: #fff;
    border: 1px solid rgba(0,0,0,0.08);
    border-radius: 16px;
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: all .3s ease;
    border-left: 4px solid var(--accent-color, var(--crimson));
}
[data-theme="dark"] .org-stat-card { background: #1a1a2e; border-color: rgba(255,255,255,0.08); }
.org-stat-card:hover { transform: translateY(-3px); box-shadow: 0 10px 30px rgba(0,0,0,0.08); }
.org-stat-card.accent-crimson { --accent-color: var(--crimson); }
.org-stat-card.accent-sky { --accent-color: var(--sky-blue-dark); }
.org-stat-card.accent-emerald { --accent-color: var(--emerald); }
.org-stat-card.accent-warning { --accent-color: var(--warning); }
.org-stat-icon {
    width: 48px; height: 48px;
    border-radius: 12px;
    background: rgba(138,21,56,0.1);
    color: var(--accent-color);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.3rem;
    flex-shrink: 0;
}
.org-stat-card.accent-sky .org-stat-icon { background: rgba(93,173,226,0.1); }
.org-stat-card.accent-emerald .org-stat-icon { background: rgba(46,139,87,0.1); }
.org-stat-card.accent-warning .org-stat-icon { background: rgba(243,156,18,0.1); }
.org-stat-value {
    font-size: 1.8rem;
    font-weight: 900;
    color: var(--rich-black);
    line-height: 1;
    font-family: 'Inter', sans-serif;
}
[data-theme="dark"] .org-stat-value { color: #fff; }
.org-stat-label { color: var(--text-grey); font-size: .85rem; margin-top: .2rem; }

/* ===== ORG SECTION ===== */
.org-section {
    background: var(--bg-light);
    padding: 5rem 1.5rem;
}
[data-theme="dark"] .org-section { background: var(--rich-black); }

/* Actions Bar */
.org-actions-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1.5rem;
    margin-bottom: 2rem;
    flex-wrap: wrap;
    max-width: 1400px;
    margin-left: auto;
    margin-right: auto;
}
.org-search-wrap {
    position: relative;
    flex: 1;
    max-width: 400px;
    min-width: 250px;
}
.org-search-wrap i {
    position: absolute;
    left: 1.2rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-grey);
    font-size: .9rem;
    pointer-events: none;
}
.org-search-input {
    width: 100%;
    padding: .9rem 1.2rem .9rem 3rem;
    background: #fff;
    border: 2px solid rgba(0,0,0,0.08);
    border-radius: 50px;
    font-size: .92rem;
    color: var(--rich-black);
    transition: all .3s ease;
    font-family: inherit;
}
[data-theme="dark"] .org-search-input {
    background: #1a1a2e;
    border-color: rgba(255,255,255,0.1);
    color: #fff;
}
.org-search-input:focus {
    outline: none;
    border-color: var(--crimson);
    box-shadow: 0 0 0 3px rgba(138,21,56,0.1);
}
.org-actions {
    display: flex;
    gap: .6rem;
}
.btn-ghost-action {
    padding: .7rem 1.4rem;
    border: 2px solid var(--crimson);
    background: transparent;
    color: var(--crimson);
    border-radius: 50px;
    font-weight: 700;
    cursor: pointer;
    transition: all .3s ease;
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    font-size: .88rem;
    font-family: inherit;
}
.btn-ghost-action:hover {
    background: var(--crimson);
    color: #fff;
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(138,21,56,.3);
}

/* Level Legend */
.org-legend {
    display: flex;
    justify-content: center;
    flex-wrap: wrap;
    gap: 1rem;
    margin-bottom: 3rem;
    max-width: 1200px;
    margin-left: auto;
    margin-right: auto;
}
.org-legend-item {
    display: flex;
    align-items: center;
    gap: .6rem;
    padding: .5rem 1rem;
    background: #fff;
    border-radius: 50px;
    border: 1px solid rgba(0,0,0,0.08);
    font-size: .85rem;
    transition: all .3s ease;
}
[data-theme="dark"] .org-legend-item { background: #1a1a2e; border-color: rgba(255,255,255,0.1); }
.org-legend-item:hover { transform: translateY(-2px); }
.org-legend-dot {
    width: 12px; height: 12px;
    border-radius: 50%;
    background: var(--crimson);
}
.legend-crimson .org-legend-dot, .legend-crimson { --legend-color: var(--crimson); }
.legend-sky .org-legend-dot, .legend-sky { --legend-color: var(--sky-blue-dark); }
.legend-emerald .org-legend-dot, .legend-emerald { --legend-color: var(--emerald); }
.legend-warning .org-legend-dot, .legend-warning { --legend-color: var(--warning); }
.legend-crimson .org-legend-dot { background: var(--crimson); }
.legend-sky .org-legend-dot { background: var(--sky-blue-dark); }
.legend-emerald .org-legend-dot { background: var(--emerald); }
.legend-warning .org-legend-dot { background: var(--warning); }
.org-legend-label {
    display: flex;
    align-items: center;
    gap: .4rem;
    color: var(--rich-black);
    font-weight: 600;
}
[data-theme="dark"] .org-legend-label { color: #fff; }
.org-legend-label i { color: var(--legend-color); font-size: .8rem; }
.org-legend-count {
    background: var(--legend-color);
    color: #fff;
    padding: .1rem .5rem;
    border-radius: 50px;
    font-size: .72rem;
    font-weight: 800;
    min-width: 22px;
    text-align: center;
}

/* Tree Chart */
.org-chart-wrapper {
    max-width: 1400px;
    margin: 0 auto;
    overflow-x: auto;
    padding: 2rem 0;
}
.org-chart {
    display: flex;
    flex-direction: column;
    gap: 3rem;
    min-width: 800px;
}
.org-level-block {
    position: relative;
}
.org-level-header {
    display: flex;
    justify-content: center;
    margin-bottom: 2rem;
    position: relative;
}
.org-level-badge {
    display: inline-flex;
    align-items: center;
    gap: .6rem;
    padding: .6rem 1.3rem;
    border-radius: 50px;
    background: #fff;
    border: 2px solid var(--legend-color);
    font-weight: 700;
    font-size: .88rem;
    color: var(--rich-black);
    box-shadow: 0 4px 15px rgba(0,0,0,0.05);
}
[data-theme="dark"] .org-level-badge { background: #1a1a2e; color: #fff; }
.org-level-badge i { color: var(--legend-color); }
.org-level-count {
    background: var(--legend-color);
    color: #fff;
    padding: .15rem .6rem;
    border-radius: 50px;
    font-size: .75rem;
    font-weight: 800;
}
.org-level-connector {
    position: absolute;
    top: -2rem;
    left: 50%;
    transform: translateX(-50%);
    width: 40px;
    height: 2rem;
}
.org-connector-svg {
    width: 100%;
    height: 100%;
    overflow: visible;
}
.org-connector-path {
    stroke: url(#orgGradient);
    stroke-width: 2;
    fill: none;
    stroke-dasharray: 4 4;
    animation: orgConnectorFlow 1.5s linear infinite;
}
@keyframes orgConnectorFlow {
    to { stroke-dashoffset: -16; }
}

.org-level {
    display: flex;
    justify-content: center;
    gap: 2rem;
    flex-wrap: wrap;
    position: relative;
}
.org-level.multi::before {
    content: '';
    position: absolute;
    top: -1rem;
    left: 10%;
    right: 10%;
    height: 2px;
    background: linear-gradient(90deg, transparent, var(--legend-color), transparent);
    opacity: .3;
}
.org-node {
    flex: 0 1 280px;
    max-width: 280px;
}
.org-card {
    background: #fff;
    border-radius: 20px;
    padding: 1.5rem;
    text-align: center;
    box-shadow: 0 10px 35px rgba(0,0,0,0.08);
    position: relative;
    transition: all .4s cubic-bezier(0.22, 0.61, 0.36, 1);
    cursor: pointer;
    overflow: hidden;
    border-top: 4px solid var(--crimson);
    height: 100%;
}
[data-theme="dark"] .org-card { background: #1a1a2e; }
.org-card.lvl-2 { border-top-color: var(--sky-blue-dark); }
.org-card.lvl-3 { border-top-color: var(--emerald); }
.org-card.lvl-4 { border-top-color: var(--warning); }
.org-card::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, transparent, rgba(138,21,56,0.03));
    opacity: 0;
    transition: opacity .3s ease;
}
.org-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 25px 60px rgba(0,0,0,0.15);
}
.org-card:hover::before { opacity: 1; }
.org-card-top {
    position: relative;
    display: inline-block;
    margin-bottom: 1rem;
}
.org-photo {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    overflow: hidden;
    margin: 0 auto;
    border: 4px solid #fff;
    box-shadow: 0 8px 20px rgba(0,0,0,0.15), 0 0 0 3px var(--crimson);
    position: relative;
    transition: all .4s ease;
}
.org-card.lvl-2 .org-photo { box-shadow: 0 8px 20px rgba(0,0,0,0.15), 0 0 0 3px var(--sky-blue-dark); }
.org-card.lvl-3 .org-photo { box-shadow: 0 8px 20px rgba(0,0,0,0.15), 0 0 0 3px var(--emerald); }
.org-card.lvl-4 .org-photo { box-shadow: 0 8px 20px rgba(0,0,0,0.15), 0 0 0 3px var(--warning); }
.org-card:hover .org-photo { transform: scale(1.05); }
.org-photo img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform .4s ease;
}
.org-photo-overlay {
    position: absolute;
    inset: 0;
    background: rgba(138,21,56,0.85);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: #fff;
    opacity: 0;
    transition: opacity .3s ease;
    font-size: .85rem;
    font-weight: 700;
    letter-spacing: 1px;
    border-radius: 50%;
}
.org-photo-overlay i {
    font-size: 1.8rem;
    margin-bottom: .3rem;
}
.org-card:hover .org-photo-overlay { opacity: 1; }
.org-level-indicator {
    position: absolute;
    bottom: -5px;
    right: -5px;
    width: 32px; height: 32px;
    border-radius: 50%;
    background: var(--legend-color);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: .8rem;
    box-shadow: 0 4px 10px rgba(0,0,0,0.2);
    border: 3px solid #fff;
}
[data-theme="dark"] .org-level-indicator { border-color: #1a1a2e; }

.org-info { padding-top: .5rem; }
.org-info h3 {
    font-size: 1.05rem;
    margin-bottom: .4rem;
    color: var(--rich-black);
    font-weight: 700;
    line-height: 1.3;
}
[data-theme="dark"] .org-info h3 { color: #fff; }
.org-jab {
    display: inline-block;
    font-size: .78rem;
    font-weight: 700;
    color: var(--crimson);
    background: rgba(138,21,56,.08);
    padding: .3rem .9rem;
    border-radius: 50px;
    margin-bottom: .5rem;
}
.org-card.lvl-2 .org-jab { color: var(--sky-blue-dark); background: rgba(93,173,226,.12); }
.org-card.lvl-3 .org-jab { color: var(--emerald); background: rgba(46,139,87,.1); }
.org-card.lvl-4 .org-jab { color: var(--warning); background: rgba(243,156,18,.1); }
.org-nidn {
    margin-top: .6rem;
    font-size: .78rem;
    color: var(--text-grey);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: .4rem;
}
.org-nidn i { color: var(--sky-blue-dark); }
.org-socials {
    display: flex;
    gap: .5rem;
    justify-content: center;
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid rgba(0,0,0,0.06);
}
[data-theme="dark"] .org-socials { border-top-color: rgba(255,255,255,0.08); }
.org-socials a {
    width: 34px; height: 34px;
    border-radius: 50%;
    background: var(--bg-light);
    color: var(--rich-black);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: .85rem;
    transition: all .3s ease;
    text-decoration: none;
}
[data-theme="dark"] .org-socials a { background: rgba(255,255,255,0.05); color: #fff; }
.org-socials a:hover {
    background: var(--crimson);
    color: #fff;
    transform: translateY(-3px) scale(1.1);
    box-shadow: 0 6px 15px rgba(138,21,56,0.3);
}
.org-socials a.wa-link:hover { background: #25D366; box-shadow: 0 6px 15px rgba(37,211,102,0.4); }

/* Search Empty */
.org-search-empty {
    text-align: center;
    padding: 4rem 2rem;
    background: #fff;
    border-radius: 20px;
    box-shadow: 0 10px 35px rgba(0,0,0,0.05);
    max-width: 600px;
    margin: 2rem auto;
}
[data-theme="dark"] .org-search-empty { background: #1a1a2e; }
.org-search-empty i {
    font-size: 4rem;
    color: var(--text-grey);
    opacity: .3;
    margin-bottom: 1rem;
}
.org-search-empty h3 { color: var(--rich-black); margin-bottom: .5rem; }
[data-theme="dark"] .org-search-empty h3 { color: #fff; }
.org-search-empty p { color: var(--text-grey); }

/* ===== PRINCIPLES SECTION ===== */
.principles-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 1.5rem;
    max-width: 1200px;
    margin: 0 auto;
}
.principle-card {
    background: rgba(255,255,255,0.05);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 20px;
    padding: 2rem;
    position: relative;
    overflow: hidden;
    transition: all .4s cubic-bezier(0.22, 0.61, 0.36, 1);
}
.principle-card:hover {
    transform: translateY(-8px);
    border-color: var(--accent-color, var(--crimson));
    box-shadow: 0 20px 50px rgba(0,0,0,0.3);
}
.principle-card[data-color="crimson"] { --accent-color: var(--crimson); }
.principle-card[data-color="sky"] { --accent-color: var(--sky-blue); }
.principle-card[data-color="emerald"] { --accent-color: var(--emerald); }
.principle-card[data-color="warning"] { --accent-color: var(--warning); }
.principle-number {
    position: absolute;
    top: -10px;
    right: 10px;
    font-size: 5rem;
    font-weight: 900;
    color: rgba(255,255,255,0.05);
    font-family: 'Inter', sans-serif;
    line-height: 1;
}
.principle-icon {
    width: 60px; height: 60px;
    border-radius: 50%;
    background: rgba(255,255,255,0.08);
    border: 2px solid var(--accent-color);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--accent-color);
    font-size: 1.5rem;
    margin-bottom: 1.2rem;
    transition: all .3s ease;
}
.principle-card:hover .principle-icon {
    background: var(--accent-color);
    color: #fff;
    transform: rotate(-10deg) scale(1.1);
}
.principle-title {
    color: #fff;
    font-size: 1.2rem;
    margin-bottom: .6rem;
    font-weight: 800;
}
.principle-desc {
    color: rgba(255,255,255,0.7);
    font-size: .92rem;
    line-height: 1.7;
    margin: 0;
}
.principle-line {
    width: 40px;
    height: 3px;
    background: var(--accent-color);
    border-radius: 3px;
    margin-top: 1.2rem;
    transition: width .3s ease;
}
.principle-card:hover .principle-line { width: 80px; }

/* ===== EMPTY STATE ===== */
.org-empty {
    text-align: center;
    padding: 5rem 2rem;
    background: #fff;
    border-radius: 24px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.08);
    max-width: 700px;
    margin: 3rem auto;
}
[data-theme="dark"] .org-empty { background: #1a1a2e; }
.org-empty-icon {
    width: 120px; height: 120px;
    margin: 0 auto 1.5rem;
    background: linear-gradient(135deg, rgba(138,21,56,0.1), rgba(135,206,235,0.1));
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 3.5rem;
    color: var(--crimson);
}
.org-empty h2 { color: var(--rich-black); margin-bottom: .5rem; font-size: 1.5rem; font-weight: 800; }
[data-theme="dark"] .org-empty h2 { color: #fff; }
.org-empty p { color: var(--text-grey); margin-bottom: 2rem; }

/* ===== MODAL ===== */
.org-modal {
    position: fixed;
    inset: 0;
    z-index: 9999;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 2rem 1rem;
}
.org-modal.active { display: flex; animation: modalFadeIn .3s ease; }
@keyframes modalFadeIn { from { opacity: 0; } to { opacity: 1; } }
.org-modal-backdrop {
    position: absolute;
    inset: 0;
    background: rgba(0,0,0,0.7);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
}
.org-modal-content {
    position: relative;
    background: #fff;
    border-radius: 24px;
    max-width: 600px;
    width: 100%;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 30px 80px rgba(0,0,0,0.4);
    animation: modalSlideIn .4s cubic-bezier(0.22, 0.61, 0.36, 1);
}
[data-theme="dark"] .org-modal-content { background: #1a1a2e; color: #fff; }
@keyframes modalSlideIn { from { transform: translateY(30px); opacity: 0; } to { transform: none; opacity: 1; } }
.org-modal-close {
    position: absolute;
    top: 1rem;
    right: 1rem;
    width: 40px; height: 40px;
    border-radius: 50%;
    background: rgba(0,0,0,0.05);
    border: none;
    color: var(--rich-black);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    z-index: 2;
    transition: all .3s ease;
}
[data-theme="dark"] .org-modal-close { background: rgba(255,255,255,0.1); color: #fff; }
.org-modal-close:hover {
    background: var(--crimson);
    color: #fff;
    transform: rotate(90deg);
}
.org-modal-header {
    background: linear-gradient(135deg, var(--crimson-dark), var(--crimson-light));
    padding: 2.5rem 2rem 2rem;
    text-align: center;
    color: #fff;
    position: relative;
    border-radius: 24px 24px 0 0;
}
.org-modal-header::before {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(circle at 30% 20%, rgba(135,206,235,0.2), transparent 60%);
    border-radius: 24px 24px 0 0;
}
.org-modal-photo {
    width: 140px; height: 140px;
    border-radius: 50%;
    overflow: hidden;
    margin: 0 auto 1.2rem;
    border: 5px solid #fff;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    position: relative;
    z-index: 1;
}
.org-modal-photo img { width: 100%; height: 100%; object-fit: cover; }
.org-modal-info { position: relative; z-index: 1; }
.org-modal-info h2 {
    font-size: 1.5rem;
    margin-bottom: .4rem;
    font-weight: 800;
}
.org-modal-position {
    display: inline-block;
    background: rgba(255,255,255,0.15);
    padding: .3rem 1rem;
    border-radius: 50px;
    font-size: .82rem;
    font-weight: 600;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.2);
}
.org-modal-nidn {
    margin-top: .8rem;
    font-size: .85rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: .5rem;
    color: rgba(255,255,255,0.9);
}
.org-modal-body { padding: 2rem; }
.org-modal-section { margin-bottom: 1.5rem; }
.org-modal-section h3 {
    font-size: 1rem;
    margin-bottom: .8rem;
    color: var(--rich-black);
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: .5rem;
}
[data-theme="dark"] .org-modal-section h3 { color: #fff; }
.org-modal-section h3::before {
    content: '';
    width: 4px; height: 18px;
    background: var(--crimson);
    border-radius: 2px;
}
.org-modal-section p {
    color: var(--text-grey);
    line-height: 1.7;
    font-size: .92rem;
}
.org-modal-contacts { display: flex; flex-direction: column; gap: .6rem; }
.org-modal-contact-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: var(--bg-light);
    border-radius: 12px;
    text-decoration: none;
    color: var(--rich-black);
    transition: all .3s ease;
    border: 1px solid transparent;
}
[data-theme="dark"] .org-modal-contact-item { background: rgba(255,255,255,0.05); color: #fff; }
.org-modal-contact-item:hover {
    background: rgba(138,21,56,0.05);
    border-color: var(--crimson);
    transform: translateX(5px);
}
.org-modal-contact-item i {
    width: 40px; height: 40px;
    border-radius: 10px;
    background: var(--crimson);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    flex-shrink: 0;
}
.org-modal-contact-item strong {
    display: block;
    font-size: .88rem;
    margin-bottom: .1rem;
}
.org-modal-contact-item span {
    font-size: .78rem;
    color: var(--text-grey);
}
.org-modal-actions {
    display: flex;
    gap: .8rem;
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 1px solid rgba(0,0,0,0.08);
    flex-wrap: wrap;
}
[data-theme="dark"] .org-modal-actions { border-top-color: rgba(255,255,255,0.1); }
.org-modal-actions .btn-primary,
.org-modal-actions .btn-secondary {
    flex: 1;
    min-width: 150px;
    margin: 0;
    justify-content: center;
    display: inline-flex;
    align-items: center;
    gap: .5rem;
}
.org-modal-actions .btn-secondary {
    border-color: var(--crimson);
    color: var(--crimson);
}
.org-modal-actions .btn-secondary:hover {
    background: var(--crimson);
    color: #fff;
}

/* ===== READING PROGRESS ===== */
.reading-progress {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 3px;
    background: rgba(0,0,0,0.05);
    z-index: 9999;
    pointer-events: none;
}
[data-theme="dark"] .reading-progress { background: rgba(255,255,255,0.05); }
.reading-progress-fill {
    height: 100%;
    width: 0%;
    background: linear-gradient(90deg, var(--crimson), var(--sky-blue), var(--emerald));
    transition: width 0.1s ease-out;
}

/* ===== REVEAL ANIMATIONS ===== */
.reveal-up {
    opacity: 0;
    transform: translateY(40px);
    transition: opacity 0.8s cubic-bezier(0.22, 0.61, 0.36, 1),
                transform 0.8s cubic-bezier(0.22, 0.61, 0.36, 1);
}
.reveal-up.visible { opacity: 1; transform: translateY(0); }
.text-reveal > * {
    display: inline-block;
    opacity: 0;
    transform: translateY(100%);
    transition: all 0.8s cubic-bezier(0.22, 0.61, 0.36, 1);
}
.text-reveal.visible > * { opacity: 1; transform: translateY(0); }
.text-reveal.visible > *:nth-child(1) { transition-delay: 0.1s; }
.text-reveal.visible > *:nth-child(2) { transition-delay: 0.2s; }
.text-reveal.visible > *:nth-child(3) { transition-delay: 0.3s; }
.fade-in-up {
    opacity: 0;
    transform: translateY(20px);
    transition: opacity 0.8s ease 0.3s, transform 0.8s ease 0.3s;
}
.fade-in-up.visible { opacity: 1; transform: translateY(0); }

/* ===== PRINT STYLES ===== */
@media print {
    .page-hero, .org-hero-decor, .org-scroll-hint,
    .reading-progress, .org-actions-bar, .path-section,
    .org-modal, .skip-link, .org-legend { display: none !important; }
    body { background: #fff !important; }
    .org-section { padding: 1rem 0; }
    .org-chart-wrapper { overflow: visible; }
    .org-chart { min-width: 0; }
    .org-card {
        box-shadow: none;
        border: 1px solid #ccc;
        break-inside: avoid;
        page-break-inside: avoid;
    }
    .org-card:hover { transform: none; }
    .org-photo-overlay { display: none; }
    .org-stats-section { padding: 1rem 0; }
}

/* ===== RESPONSIVE ===== */
@media (max-width: 900px) {
    .org-hero { padding: 9rem 1rem 4rem; }
    .org-chart-wrapper { overflow-x: auto; padding-bottom: 2rem; }
    .org-chart { min-width: 700px; }
    .org-actions-bar { flex-direction: column; align-items: stretch; }
    .org-search-wrap { max-width: 100%; }
}
@media (max-width: 768px) {
    .org-chart-wrapper { overflow-x: visible; }
    .org-chart { min-width: 0; gap: 2rem; }
    .org-level { flex-direction: column; align-items: center; gap: 1.5rem; }
    .org-level.multi::before { display: none; }
    .org-node { max-width: 320px; width: 100%; }
    .org-legend { flex-direction: column; align-items: center; }
    .principles-grid { grid-template-columns: 1fr; }
    .org-stats-grid { grid-template-columns: repeat(2, 1fr); }
    .org-modal-content { margin: 0; border-radius: 20px; max-height: 95vh; }
    .org-modal-header { padding: 2rem 1.5rem 1.5rem; }
    .org-modal-photo { width: 110px; height: 110px; }
    .org-modal-info h2 { font-size: 1.25rem; }
}
@media (max-width: 480px) {
    .org-stats-grid { grid-template-columns: 1fr 1fr; gap: .8rem; }
    .org-stat-card { padding: 1rem; }
    .org-stat-value { font-size: 1.4rem; }
    .org-modal-actions { flex-direction: column; }
    .org-modal-actions .btn-primary,
    .org-modal-actions .btn-secondary { min-width: 0; width: 100%; }
}

/* ===== REDUCED MOTION ===== */
@media (prefers-reduced-motion: reduce) {
    *, *::before, *::after {
        animation-duration: 0.01ms !important;
        transition-duration: 0.01ms !important;
    }
    .reveal-up, .text-reveal > *, .fade-in-up {
        opacity: 1; transform: none;
    }
}
</style>

<!-- SVG Gradient Definition (untuk connector lines) -->
<svg width="0" height="0" style="position:absolute;">
    <defs>
        <linearGradient id="orgGradient" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0" stop-color="#8A1538"/>
            <stop offset="1" stop-color="#87CEEB"/>
        </linearGradient>
    </defs>
</svg>

<!-- ============================================================
     PREMIUM JAVASCRIPT — STRUKTUR ORGANISASI
============================================================ -->
<script>
(function () {
    'use strict';

    // ===== READING PROGRESS BAR =====
    const progressBar = document.querySelector('.reading-progress-fill');
    if (progressBar) {
        let ticking = false;
        function updateProgress() {
            const scrollTop = window.scrollY;
            const docHeight = document.documentElement.scrollHeight - window.innerHeight;
            const progress = docHeight > 0 ? (scrollTop / docHeight) * 100 : 0;
            progressBar.style.width = progress + '%';
            ticking = false;
        }
        window.addEventListener('scroll', function () {
            if (!ticking) {
                requestAnimationFrame(updateProgress);
                ticking = true;
            }
        }, { passive: true });
    }

    // ===== TEXT REVEAL =====
    document.querySelectorAll('.text-reveal').forEach(el => {
        if (el.children.length > 0) return;
        const text = el.textContent.trim();
        const words = text.split(' ');
        el.innerHTML = words.map(w => `<span>${w}</span>`).join(' ');
    });

    // ===== INTERSECTION OBSERVER FOR REVEALS =====
    const revealObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                revealObserver.unobserve(entry.target);
            }
        });
    }, { threshold: 0.15, rootMargin: '0px 0px -80px 0px' });

    document.querySelectorAll('.reveal-up, .text-reveal, .fade-in-up').forEach(el => {
        revealObserver.observe(el);
    });

    // ===== COUNTER ANIMATION =====
    const counterObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting && !entry.target.dataset.counted) {
                entry.target.dataset.counted = '1';
                const target = parseInt(entry.target.dataset.counter);
                if (!isNaN(target) && target > 0) {
                    const duration = 1500;
                    const step = target / (duration / 16);
                    let current = 0;
                    const timer = setInterval(() => {
                        current += step;
                        if (current >= target) {
                            current = target;
                            clearInterval(timer);
                        }
                        entry.target.textContent = Math.floor(current).toLocaleString('id-ID');
                    }, 16);
                }
            }
        });
    }, { threshold: 0.3 });

    document.querySelectorAll('[data-counter]').forEach(el => counterObserver.observe(el));

    // ===== MAGNETIC BUTTONS & CARDS =====
    document.querySelectorAll('.magnetic-btn, .magnetic-card').forEach(el => {
        el.addEventListener('mousemove', (e) => {
            const rect = el.getBoundingClientRect();
            const x = e.clientX - rect.left - rect.width / 2;
            const y = e.clientY - rect.top - rect.height / 2;
            const strength = el.classList.contains('magnetic-card') ? 0.05 : 0.15;
            el.style.transform = `translate(${x * strength}px, ${y * strength}px)`;
        });
        el.addEventListener('mouseleave', () => {
            el.style.transform = '';
        });
    });

    // ===== SEARCH FUNCTIONALITY =====
    const searchInput = document.getElementById('orgSearch');
    const emptyState = document.getElementById('orgSearchEmpty');
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            const term = this.value.toLowerCase().trim();
            const nodes = document.querySelectorAll('.org-node');
            let visibleCount = 0;

            nodes.forEach(node => {
                const searchText = node.dataset.search || '';
                const match = !term || searchText.includes(term);
                node.style.display = match ? '' : 'none';
                if (match) visibleCount++;
            });

            // Hide empty level blocks
            document.querySelectorAll('.org-level-block').forEach(block => {
                const visibleNodes = block.querySelectorAll('.org-node:not([style*="display: none"])');
                block.style.display = visibleNodes.length > 0 ? '' : 'none';
            });

            if (emptyState) {
                emptyState.style.display = visibleCount === 0 && term ? '' : 'none';
            }
        });
    }

    // ===== INITIAL REVEAL =====
    window.addEventListener('load', () => {
        setTimeout(() => {
            document.querySelectorAll('.org-hero .text-reveal, .org-hero .reveal-up, .org-hero .fade-in-up').forEach(el => {
                el.classList.add('visible');
            });
        }, 600);
    });

})();

// ===== MODAL FUNCTIONS =====
let currentOfficial = null;

function openOrgModal(official) {
    currentOfficial = official;
    const modal = document.getElementById('orgModal');

    // Populate modal
    document.getElementById('modalPhoto').src = official.foto ? '<?= BASE_URL ?>' + official.foto : '<?= img_placeholder() ?>';
    document.getElementById('modalPhoto').alt = 'Foto ' + official.nama;
    document.getElementById('modalTitle').textContent = official.nama;
    document.getElementById('modalPosition').textContent = official.jabatan;

    // NIDN
    const nidnEl = document.getElementById('modalNidn');
    if (official.nidn) {
        nidnEl.style.display = '';
        document.getElementById('modalNidnValue').textContent = official.nidn;
    } else {
        nidnEl.style.display = 'none';
    }

    // Bio (jika ada)
    const bioSection = document.getElementById('modalBioSection');
    if (official.bio) {
        bioSection.style.display = '';
        document.getElementById('modalBio').textContent = official.bio;
    } else {
        bioSection.style.display = 'none';
    }

    // Email
    const emailEl = document.getElementById('modalEmail');
    if (official.email) {
        emailEl.style.display = '';
        emailEl.href = 'mailto:' + official.email;
        document.getElementById('modalEmailValue').textContent = official.email;
    } else {
        emailEl.style.display = 'none';
    }

    // Scholar
    const scholarEl = document.getElementById('modalScholar');
    if (official.link_scholar) {
        scholarEl.style.display = '';
        scholarEl.href = official.link_scholar;
    } else {
        scholarEl.style.display = 'none';
    }

    // WhatsApp
    const waEl = document.getElementById('modalWhatsapp');
    if (official.whatsapp) {
        waEl.style.display = '';
        const cleanWA = official.whatsapp.replace(/[^0-9]/g, '');
        waEl.href = 'https://wa.me/' + cleanWA;
    } else {
        waEl.style.display = 'none';
    }

    // Show modal
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeOrgModal() {
    const modal = document.getElementById('orgModal');
    modal.classList.remove('active');
    document.body.style.overflow = '';
    currentOfficial = null;
}

function contactOfficial() {
    if (!currentOfficial) return;
    if (currentOfficial.email) {
        window.location.href = 'mailto:' + currentOfficial.email;
    } else if (currentOfficial.whatsapp) {
        const cleanWA = currentOfficial.whatsapp.replace(/[^0-9]/g, '');
        window.open('https://wa.me/' + cleanWA, '_blank');
    }
}

function shareOfficial() {
    if (!currentOfficial) return;
    const text = currentOfficial.nama + ' - ' + currentOfficial.jabatan + ' di PBSI UMMaumere';
    const url = window.location.href;

    if (navigator.share) {
        navigator.share({ title: text, text: text, url: url })
            .catch(() => {});
    } else {
        // Fallback: copy to clipboard
        navigator.clipboard.writeText(text + '\n' + url).then(() => {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'Link disalin!',
                showConfirmButton: false,
                timer: 2000,
                timerProgressBar: true
            });
        });
    }
}

function downloadStructure() {
    Swal.fire({
        icon: 'info',
        title: 'Unduh Struktur',
        text: 'Gunakan Ctrl+P (atau Cmd+P di Mac) untuk menyimpan halaman sebagai PDF.',
        confirmButtonColor: '#8A1538',
        confirmButtonText: 'Mengerti'
    });
}

// Close modal on ESC
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeOrgModal();
});
</script>

<!-- Multi-language Support -->
<script>
(function () {
    'use strict';

    const translations = {
        en: {
            'team_structure': 'MANAGEMENT TEAM',
            'page_title': 'Organization Structure',
            'page_subtitle': 'Professional, transparent, and collaborative study program governance.',
            'home': 'Home',
            'structure_breadcrumb': 'Organization Structure',
            'scroll': 'SCROLL',
            'total_officials': 'Total Officials',
            'hierarchy_levels': 'Hierarchy Levels',
            'leadership': 'Leadership',
            'coordinators': 'Coordinators & Staff',
            'our_structure': 'OUR STRUCTURE',
            'structure_heading': 'Organization Chart',
            'structure_subtitle': 'The leadership and coordinators who run the study program organization.',
            'print': 'Print',
            'download': 'Download',
            'view_profile': 'View Profile',
            'no_results': 'No Results',
            'no_results_desc': 'Try different keywords or reset the search.',
            'our_principles': 'OUR PRINCIPLES',
            'principles_heading': 'Our Governance Principles',
            'principles_subtitle': 'Four pillars that serve as the foundation of study program operations.',
            'principle_transparan_title': 'Transparent',
            'principle_transparan_desc': 'Every academic decision is open and accountable to all stakeholders.',
            'principle_kolegial_title': 'Collegial',
            'principle_kolegial_desc': 'Weekly faculty meetings and cross-coordinator coordination for optimal synergy.',
            'principle_berbasis_mutu_title': 'Quality-Based',
            'principle_berbasis_mutu_desc': 'PPEPP cycle (Plan-Do-Check-Act-Improve) applied consistently.',
            'principle_melayani_title': 'Service-Oriented',
            'principle_melayani_desc': 'Fast, friendly, and digital-based administration for student comfort.',
            'empty_title': 'Structure Not Yet Available',
            'empty_desc': 'Organization structure data is currently being prepared. Please visit again later.',
            'contact_us': 'Contact Us',
            'about': 'About',
            'contact_info': 'Contact Information',
            'view_profile_scholar': 'View Academic Profile',
            'chat_direct': 'Chat Directly',
            'contact_now': 'Contact Now',
            'share': 'Share'
        }
    };

    function applyTranslations() {
        const lang = document.documentElement.getAttribute('lang') || 'id';
        if (lang === 'en' && translations.en) {
            document.querySelectorAll('[data-i18n]').forEach(el => {
                const key = el.dataset.i18n;
                if (translations.en[key]) {
                    if (el.children.length > 0) {
                        const walker = document.createTreeWalker(el, NodeFilter.SHOW_TEXT, null, false);
                        let node;
                        while (node = walker.nextNode()) {
                            if (node.nodeValue.trim()) {
                                node.nodeValue = translations.en[key];
                                break;
                            }
                        }
                    } else {
                        el.textContent = translations.en[key];
                    }
                }
            });
        }
    }

    const langObserver = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            if (mutation.attributeName === 'lang') applyTranslations();
        });
    });

    langObserver.observe(document.documentElement, { attributes: true });
    applyTranslations();
})();
</script>

<?php require_once '../includes/public_footer.php'; ?>