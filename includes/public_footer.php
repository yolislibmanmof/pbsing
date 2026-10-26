<?php
if (!function_exists('get_all')) { require_once __DIR__ . '/functions.php'; }
$setting = get_all('settings', 'id = 1')[0];
$navbar_config = require __DIR__ . '/navbar_menu.php';
$all_menus = [];
foreach ($navbar_config as $__item) {
if (!empty($__item['hidden'])) continue;
$all_menus[] = ['label' => $__item['label'], 'url' => $__item['url'], 'icon' => $__item['icon'] ?? 'fa-solid fa-circle'];
}
$latest_news = get_all('berita', 'status = 1', 'tanggal DESC LIMIT 3');

// Parse social media dari JSON
$social_media = [];
if (!empty($setting['social_media'])) {
    $social_media = json_decode($setting['social_media'], true) ?: [];
}

$social_platforms = [
    'facebook'  => ['icon' => 'fa-brands fa-facebook-f', 'color' => '#1877f2', 'label' => 'Facebook'],
    'instagram' => ['icon' => 'fa-brands fa-instagram',  'color' => 'linear-gradient(45deg, #f09433, #e6683c, #dc2743, #cc2366, #bc1888)', 'label' => 'Instagram'],
    'twitter'   => ['icon' => 'fa-brands fa-x-twitter',  'color' => '#000', 'label' => 'Twitter/X'],
    'youtube'   => ['icon' => 'fa-brands fa-youtube',    'color' => '#ff0000', 'label' => 'YouTube'],
    'linkedin'  => ['icon' => 'fa-brands fa-linkedin-in','color' => '#0a66c2', 'label' => 'LinkedIn'],
    'tiktok'    => ['icon' => 'fa-brands fa-tiktok',     'color' => '#000', 'label' => 'TikTok'],
    'github'    => ['icon' => 'fa-brands fa-github',     'color' => '#24292e', 'label' => 'GitHub']
];

$current_year = date('Y');
?>

<footer class="footer-ultimate" id="footer" role="contentinfo">
    <!-- Wave Divider -->
    <div class="footer-wave" aria-hidden="true">
        <svg viewBox="0 0 1440 120" preserveAspectRatio="none">
            <path d="M0,60 C360,120 720,0 1440,60 L1440,120 L0,120 Z" fill="#0a0a0a"></path>
        </svg>
    </div>

    <!-- Newsletter Section -->
    <div class="footer-ultimate-top">
        <div class="footer-ultimate-container">
            <div class="footer-newsletter">
                <div class="newsletter-content">
                    <div class="newsletter-icon" aria-hidden="true">
                        <i class="fa-solid fa-envelope-open-text"></i>
                    </div>
                    <div>
                        <h3><?= $current_lang === 'en' ? 'Subscribe to Our Newsletter' : 'Berlangganan Newsletter' ?></h3>
                        <p><?= $current_lang === 'en' ? 'Get the latest academic news and events delivered straight to your inbox.' : 'Dapatkan info terbaru seputar akademik dan kegiatan PBSING langsung di inbox Anda.' ?></p>
                    </div>
                </div>
                <form class="newsletter-form" id="newsletterForm" novalidate>
                    <input type="email" name="email" placeholder="<?= $current_lang === 'en' ? 'Your email address...' : 'Email Anda...' ?>" required aria-label="Email address" autocomplete="email">
                    <button type="submit">
                        <i class="fa-solid fa-paper-plane"></i>
                        <span><?= $current_lang === 'en' ? 'Subscribe' : 'Langganan' ?></span>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Main Footer Grid -->
    <div class="footer-ultimate-main">
        <div class="footer-ultimate-container">
            <div class="footer-ultimate-grid">

                <!-- Column 1: Brand & Social -->
                <div class="footer-col-ultimate">
                    <div class="footer-brand-ultimate">
                        <span class="footer-brand-icon" aria-hidden="true">
                            <i class="fa-solid fa-graduation-cap"></i>
                        </span>
                        <div>
                            <h3>PBSING <em>UNIMOF</em></h3>
                            <span class="footer-prodi"><?= sanitize($setting['prodi_name'] ?? '') ?></span>
                        </div>
                    </div>
                    <p class="footer-desc"><?= sanitize($setting['site_tagline'] ?? '') ?></p>

                    <?php if (!empty($setting['akreditasi'])): ?>
                    <div class="footer-acc-badge" aria-label="Akreditasi: <?= sanitize($setting['akreditasi']) ?>">
                        <i class="fa-solid fa-award" aria-hidden="true"></i>
                        <span><?= $current_lang === 'en' ? 'Accredited' : 'Akreditasi' ?> <strong><?= sanitize($setting['akreditasi']) ?></strong></span>
                    </div>
                    <?php endif; ?>

                    <!-- Social Icons (Clean Classes) -->
                    <div class="footer-socials" role="list" aria-label="Social media links">
                        <?php foreach ($social_platforms as $key => $platform):
                            $url = $social_media[$key] ?? '';
                            if (empty($url)) continue;
                            $isGradient = strpos($platform['color'], 'gradient') !== false;
                        ?>
                        <a href="<?= sanitize($url) ?>"
                           class="footer-soc-link <?= $isGradient ? 'soc-gradient' : '' ?>"
                           style="<?= $isGradient ? 'background:' . $platform['color'] . ';' : 'background:' . $platform['color'] . ';' ?>"
                           target="_blank"
                           rel="noopener noreferrer"
                           aria-label="<?= $platform['label'] ?>"
                           role="listitem">
                            <i class="<?= $platform['icon'] ?>" aria-hidden="true"></i>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Column 2: Quick Menu -->
                <div class="footer-col-ultimate">
                    <h4>
                        <i class="fa-solid fa-compass" aria-hidden="true"></i>
                        <?= $current_lang === 'en' ? 'Quick Menu' : 'Menu Utama' ?>
                    </h4>
                    <ul class="footer-menu-list" role="list">
                        <?php foreach (array_slice($all_menus, 0, 8) as $m): ?>
                        <li role="listitem">
                            <a href="<?= $m['url'] === '#' ? '#' : PUBLIC_URL . sanitize($m['url']) ?>">
                                <i class="<?= sanitize($m['icon']) ?>" aria-hidden="true"></i>
                                <span><?= sanitize($m['label']) ?></span>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <!-- Column 3: Latest News -->
                <div class="footer-col-ultimate">
                    <h4>
                        <i class="fa-solid fa-newspaper" aria-hidden="true"></i>
                        <?= $current_lang === 'en' ? 'Latest News' : 'Berita Terkini' ?>
                    </h4>
                    <ul class="footer-news-list" role="list">
                        <?php if (empty($latest_news)): ?>
                            <li class="footer-news-empty">
                                <i class="fa-regular fa-newspaper" aria-hidden="true"></i>
                                <span><?= $current_lang === 'en' ? 'No news available' : 'Belum ada berita' ?></span>
                            </li>
                        <?php else: foreach ($latest_news as $n):
                            $thumb = !empty($n['gambar'])
                                ? (filter_var($n['gambar'], FILTER_VALIDATE_URL) ? $n['gambar'] : BASE_URL . sanitize($n['gambar']))
                                : PUBLIC_URL . 'assets/images/news-placeholder.jpg';
                        ?>
                        <li role="listitem">
                            <a href="<?= PUBLIC_URL ?>berita_detail.php?slug=<?= urlencode($n['slug']) ?>">
                                <div class="footer-news-img" style="background-image:url('<?= sanitize($thumb) ?>')" role="img" aria-label="<?= sanitize($n['judul']) ?>"></div>
                                <div class="footer-news-info">
                                    <h5><?= sanitize($n['judul']) ?></h5>
                                    <span>
                                        <i class="fa-regular fa-calendar" aria-hidden="true"></i>
                                        <?= date('d M Y', strtotime($n['tanggal'])) ?>
                                    </span>
                                </div>
                            </a>
                        </li>
                        <?php endforeach; endif; ?>
                    </ul>
                </div>

                <!-- Column 4: Contact -->
                <div class="footer-col-ultimate">
                    <h4>
                        <i class="fa-solid fa-headset" aria-hidden="true"></i>
                        <?= $current_lang === 'en' ? 'Contact Us' : 'Kontak Kami' ?>
                    </h4>
                    <ul class="footer-contact-list" role="list">
                        <?php if (!empty($setting['alamat'])): ?>
                        <li role="listitem">
                            <div class="footer-contact-icon" aria-hidden="true"><i class="fa-solid fa-location-dot"></i></div>
                            <div>
                                <strong><?= $current_lang === 'en' ? 'Address' : 'Alamat' ?></strong>
                                <p><?= sanitize($setting['alamat']) ?></p>
                            </div>
                        </li>
                        <?php endif; ?>

                        <?php if (!empty($setting['telepon'])): ?>
                        <li role="listitem">
                            <div class="footer-contact-icon" aria-hidden="true"><i class="fa-solid fa-phone"></i></div>
                            <div>
                                <strong><?= $current_lang === 'en' ? 'Phone' : 'Telepon' ?></strong>
                                <p><a href="tel:<?= preg_replace('/[^0-9+]/', '', $setting['telepon']) ?>" style="color:inherit; text-decoration:none;"><?= sanitize($setting['telepon']) ?></a></p>
                            </div>
                        </li>
                        <?php endif; ?>

                        <?php if (!empty($setting['email'])): ?>
                        <li role="listitem">
                            <div class="footer-contact-icon" aria-hidden="true"><i class="fa-solid fa-envelope"></i></div>
                            <div>
                                <strong>Email</strong>
                                <p><a href="mailto:<?= sanitize($setting['email']) ?>" style="color:inherit; text-decoration:none;"><?= sanitize($setting['email']) ?></a></p>
                            </div>
                        </li>
                        <?php endif; ?>

                        <li role="listitem">
                            <div class="footer-contact-icon" aria-hidden="true"><i class="fa-solid fa-clock"></i></div>
                            <div>
                                <strong><?= $current_lang === 'en' ? 'Service Hours' : 'Jam Layanan' ?></strong>
                                <p><?= $current_lang === 'en' ? 'Monday - Friday, 08:00 - 16:00 WITA' : 'Senin - Jumat, 08.00 - 16.00 WITA' ?></p>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Bottom Bar -->
    <div class="footer-ultimate-bottom">
        <div class="footer-ultimate-container">
            <p class="footer-copyright">
                © <?= $current_year ?> <strong style="color:#fff;"><?= sanitize($setting['site_name']) ?></strong>.
                <?= sanitize($setting['footer_text'] ?? 'All Rights Reserved.') ?>
            </p>
            <div class="footer-bottom-links" role="list">
                <a href="<?= PUBLIC_URL ?>page.php?slug=kebijakan-privasi" role="listitem">
                    <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                    <?= $current_lang === 'en' ? 'Privacy Policy' : 'Kebijakan Privasi' ?>
                </a>
                <a href="<?= PUBLIC_URL ?>page.php?slug=syarat-ketentuan" role="listitem">
                    <i class="fa-solid fa-file-contract" aria-hidden="true"></i>
                    <?= $current_lang === 'en' ? 'Terms & Conditions' : 'Syarat & Ketentuan' ?>
                </a>
                <a href="<?= PUBLIC_URL ?>page.php?slug=sitemap" role="listitem">
                    <i class="fa-solid fa-sitemap" aria-hidden="true"></i>
                    Sitemap
                </a>
                <a href="<?= ADMIN_URL ?>login.php" role="listitem">
                    <i class="fa-solid fa-user-shield" aria-hidden="true"></i>
                    Admin
                </a>
            </div>
        </div>
    </div>
</footer>

<!-- Back to Top with Progress -->
<button class="scroll-top-btn" id="scrollTopBtn" aria-label="Back to top">
    <i class="fa-solid fa-arrow-up" aria-hidden="true"></i>
    <svg class="scroll-top-progress" viewBox="0 0 100 100" aria-hidden="true">
        <circle cx="50" cy="50" r="45"></circle>
    </svg>
</button>

<style>
/* ===== FOOTER ULTIMATE ENHANCEMENTS ===== */
.footer-ultimate { background: #0a0a0a; color: #b0b0b0; position: relative; overflow: hidden; margin-top: 0; font-family: 'Inter', sans-serif; }
.footer-ultimate::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 1px;
    background: linear-gradient(90deg, transparent, var(--crimson), var(--sky-blue), var(--emerald), transparent);
}
.footer-wave { position: relative; height: 60px; margin-bottom: -1px; }
.footer-wave svg { position: absolute; bottom: 0; left: 0; width: 100%; height: 100%; }
.footer-ultimate-container { max-width: 1400px; margin: 0 auto; padding: 0 2rem; }

.footer-ultimate-top { padding: 4rem 0; background: linear-gradient(135deg, rgba(138,21,56,0.1) 0%, rgba(135,206,235,0.05) 100%); border-bottom: 1px solid rgba(255,255,255,0.05); }
.footer-newsletter {
    background: linear-gradient(135deg, var(--crimson-dark), var(--crimson));
    border-radius: 24px; padding: 2.5rem;
    display: flex; align-items: center; justify-content: space-between; gap: 2rem; flex-wrap: wrap;
    position: relative; overflow: hidden;
    box-shadow: 0 20px 60px rgba(138,21,56,0.3);
}
.footer-newsletter::before {
    content: '';
    position: absolute;
    top: -50%; right: -10%;
    width: 400px; height: 400px;
    background: radial-gradient(circle, rgba(135,206,235,0.15), transparent 70%);
    border-radius: 50%;
    pointer-events: none;
}
.newsletter-content { display: flex; align-items: center; gap: 1.5rem; flex: 1; min-width: 300px; position: relative; z-index: 1; }
.newsletter-icon {
    width: 70px; height: 70px; border-radius: 50%;
    background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2);
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    backdrop-filter: blur(10px);
}
.newsletter-icon i { font-size: 1.8rem; color: var(--sky-blue); }
.newsletter-content h3 { color: #fff; font-size: 1.5rem; margin-bottom: .3rem; font-weight: 800; }
.newsletter-content p { color: rgba(255,255,255,0.9); font-size: .95rem; }
.newsletter-form { display: flex; gap: .5rem; flex: 1; min-width: 300px; position: relative; z-index: 1; }
.newsletter-form input {
    flex: 1; padding: 1rem 1.5rem;
    border: 2px solid rgba(255,255,255,0.2);
    background: rgba(255,255,255,0.1);
    border-radius: 50px;
    color: #fff; font-size: .95rem;
    transition: all .3s ease;
    backdrop-filter: blur(10px);
}
.newsletter-form input::placeholder { color: rgba(255,255,255,0.6); }
.newsletter-form input:focus { outline: none; border-color: var(--sky-blue); background: rgba(255,255,255,0.15); }
.newsletter-form button {
    padding: 1rem 2rem;
    background: #fff; color: var(--crimson);
    border: none; border-radius: 50px;
    font-weight: 700; cursor: pointer;
    transition: all .3s ease;
    display: flex; align-items: center; gap: .6rem;
    white-space: nowrap;
}
.newsletter-form button:hover {
    background: var(--sky-blue); color: var(--rich-black);
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(135,206,235,0.4);
}
.newsletter-form button:disabled { opacity: .7; cursor: not-allowed; }

.footer-ultimate-main { padding: 4rem 0 3rem; }
.footer-ultimate-grid { display: grid; grid-template-columns: 1.5fr 1fr 1.2fr 1.3fr; gap: 3rem; }
.footer-col-ultimate h4 {
    color: #fff; font-size: 1.1rem; margin-bottom: 1.5rem; font-weight: 700;
    display: flex; align-items: center; gap: .6rem;
    position: relative; padding-bottom: .8rem;
}
.footer-col-ultimate h4::after {
    content: ''; position: absolute; left: 0; bottom: 0;
    width: 40px; height: 3px;
    background: linear-gradient(90deg, var(--crimson), var(--sky-blue));
    border-radius: 2px;
}
.footer-col-ultimate h4 i { color: var(--sky-blue); font-size: 1rem; }

.footer-brand-ultimate { display: flex; align-items: center; gap: 1rem; margin-bottom: 1.2rem; }
.footer-brand-icon {
    width: 56px; height: 56px; border-radius: 14px;
    background: linear-gradient(135deg, var(--crimson), var(--crimson-light));
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 8px 20px rgba(138,21,56,0.4);
    color: #fff; font-size: 1.5rem;
}
.footer-brand-ultimate h3 { color: #fff; font-size: 1.3rem; font-weight: 800; margin: 0; }
.footer-brand-ultimate em { color: var(--sky-blue); font-style: normal; font-weight: 400; font-size: .85rem; display: block; }
.footer-prodi { font-size: .75rem; color: #888; text-transform: uppercase; letter-spacing: 1px; display: block; margin-top: .2rem; }
.footer-desc { line-height: 1.8; margin-bottom: 1.2rem; font-size: .92rem; }
.footer-acc-badge {
    display: inline-flex; align-items: center; gap: .6rem;
    background: rgba(135,206,235,0.1); color: var(--sky-blue);
    padding: .5rem 1.2rem; border-radius: 50px;
    font-size: .85rem; font-weight: 600;
    border: 1px solid rgba(135,206,235,0.2);
    backdrop-filter: blur(10px);
}

/* Social Icons Clean */
.footer-socials {
    display: flex; gap: 10px; margin-top: 20px; flex-wrap: wrap;
}
.footer-soc-link {
    width: 42px; height: 42px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-size: 1.1rem;
    text-decoration: none;
    transition: transform .3s ease, box-shadow .3s ease;
    position: relative;
    overflow: hidden;
}
.footer-soc-link::before {
    content: '';
    position: absolute; inset: 0;
    background: linear-gradient(135deg, rgba(255,255,255,0.15), transparent);
    opacity: 0;
    transition: opacity .3s ease;
}
.footer-soc-link:hover {
    transform: translateY(-5px) scale(1.1);
    box-shadow: 0 8px 20px rgba(0,0,0,0.4);
}
.footer-soc-link:hover::before { opacity: 1; }

.footer-menu-list, .footer-news-list, .footer-contact-list {
    display: flex; flex-direction: column; gap: .8rem;
    list-style: none; padding: 0; margin: 0;
}
.footer-menu-list li a, .footer-news-list li a {
    color: #b0b0b0; display: flex; align-items: center; gap: .7rem;
    font-size: .92rem; transition: all .3s ease; text-decoration: none;
}
.footer-menu-list li a i { color: var(--crimson); font-size: .85rem; width: 16px; transition: all .3s ease; }
.footer-menu-list li a:hover { color: var(--sky-blue); transform: translateX(8px); }
.footer-menu-list li a:hover i { color: var(--sky-blue); transform: scale(1.3); }

.footer-news-list li a { gap: 1rem; }
.footer-news-list li a:hover { transform: translateX(5px); }
.footer-news-img {
    width: 70px; height: 70px; border-radius: 12px;
    background-size: cover; background-position: center;
    background-color: #222; flex-shrink: 0;
    border: 1px solid rgba(255,255,255,0.05);
    transition: transform .3s ease;
}
.footer-news-list li a:hover .footer-news-img { transform: scale(1.05); }
.footer-news-info { flex: 1; min-width: 0; }
.footer-news-info h5 {
    color: #e0e0e0; font-size: .9rem; margin-bottom: .3rem; line-height: 1.3;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
    font-weight: 600;
}
.footer-news-list li a:hover .footer-news-info h5 { color: var(--sky-blue); }
.footer-news-info span { font-size: .78rem; color: #777; display: flex; align-items: center; gap: .3rem; }
.footer-news-info i { color: var(--crimson); }

.footer-news-empty {
    color: #666; font-size: .9rem;
    display: flex; flex-direction: column; align-items: center; gap: .8rem;
    padding: 2rem 1rem; text-align: center;
    border: 1px dashed rgba(255,255,255,0.1);
    border-radius: 12px;
}
.footer-news-empty i { font-size: 2rem; opacity: .3; }

.footer-contact-list li { display: flex; gap: 1rem; align-items: flex-start; }
.footer-contact-icon {
    width: 42px; height: 42px; border-radius: 10px;
    background: rgba(138,21,56,0.1); border: 1px solid rgba(138,21,56,0.2);
    display: flex; align-items: center; justify-content: center;
    color: var(--crimson); flex-shrink: 0;
    transition: all .3s ease;
}
.footer-contact-list li:hover .footer-contact-icon {
    background: var(--crimson); color: #fff;
    transform: rotate(10deg) scale(1.1);
}
.footer-contact-list strong { color: #fff; font-size: .88rem; display: block; margin-bottom: .2rem; }
.footer-contact-list p { font-size: .85rem; line-height: 1.5; margin: 0; }
.footer-contact-list a { transition: color .3s ease; }
.footer-contact-list a:hover { color: var(--sky-blue); }

.footer-ultimate-bottom {
    border-top: 1px solid rgba(255,255,255,0.05);
    padding: 1.5rem 0;
    background: rgba(0,0,0,0.3);
    backdrop-filter: blur(10px);
}
.footer-ultimate-bottom .footer-ultimate-container {
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 1rem;
}
.footer-copyright { font-size: .85rem; color: #777; margin: 0; }
.footer-copyright strong { color: #fff; }
.footer-bottom-links { display: flex; gap: 1.5rem; flex-wrap: wrap; }
.footer-bottom-links a {
    color: #888; font-size: .85rem;
    display: flex; align-items: center; gap: .4rem;
    transition: all .3s ease; text-decoration: none;
}
.footer-bottom-links a:hover { color: var(--sky-blue); transform: translateY(-2px); }

/* Scroll to Top */
.scroll-top-btn {
    position: fixed; bottom: 30px; right: 30px;
    width: 55px; height: 55px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--crimson), var(--crimson-light));
    color: #fff; border: none; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.2rem;
    box-shadow: 0 10px 30px rgba(138,21,56,0.4);
    opacity: 0; visibility: hidden;
    transform: translateY(20px);
    transition: opacity .3s ease, visibility .3s ease, transform .3s ease, box-shadow .3s ease;
    z-index: 997;
}
.scroll-top-btn.visible {
    opacity: 1; visibility: visible; transform: translateY(0);
}
.scroll-top-btn:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 40px rgba(138,21,56,0.6);
}
.scroll-top-progress {
    position: absolute; width: 100%; height: 100%;
    transform: rotate(-90deg);
}
.scroll-top-progress circle {
    fill: none; stroke: var(--sky-blue);
    stroke-width: 3;
    stroke-dasharray: 283;
    stroke-dashoffset: 283;
    stroke-linecap: round;
    transition: stroke-dashoffset .1s linear;
}

/* Responsive */
@media (max-width: 1024px) {
    .footer-ultimate-grid { grid-template-columns: 1fr 1fr; gap: 2.5rem; }
}
@media (max-width: 768px) {
    .footer-newsletter { flex-direction: column; text-align: center; padding: 2rem 1.5rem; }
    .newsletter-content { flex-direction: column; text-align: center; }
    .newsletter-form { flex-direction: column; width: 100%; }
    .footer-ultimate-grid { grid-template-columns: 1fr; gap: 2.5rem; }
    .footer-ultimate-bottom .footer-ultimate-container { flex-direction: column; text-align: center; }
    .footer-bottom-links { justify-content: center; }
    .scroll-top-btn { bottom: 20px; right: 20px; width: 50px; height: 50px; }
}
@media (max-width: 480px) {
    .footer-ultimate-container { padding: 0 1rem; }
    .footer-ultimate-top, .footer-ultimate-main { padding: 3rem 0; }
    .footer-newsletter { padding: 1.5rem 1rem; }
    .newsletter-content h3 { font-size: 1.2rem; }
}

/* Reduced motion */
@media (prefers-reduced-motion: reduce) {
    *, *::before, *::after {
        animation-duration: 0.01ms !important;
        transition-duration: 0.01ms !important;
    }
}
</style>

<script>
/* ===== NEWSLETTER + SCROLL TO TOP ENGINE ===== */
(function () {
    'use strict';

    // === Newsletter AJAX Handler ===
    var newsletterForm = document.getElementById('newsletterForm');
    if (newsletterForm) {
        newsletterForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var btn = this.querySelector('button');
            var input = this.querySelector('input[type="email"]');
            var email = input.value.trim();
            var originalHtml = btn.innerHTML;

            // Email validation
            var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Email Tidak Valid',
                    text: 'Mohon masukkan alamat email yang benar.',
                    confirmButtonColor: '#8A1538'
                });
                input.focus();
                return;
            }

            // Loading state
            btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Mengirim...';
            btn.disabled = true;
            input.disabled = true;

            // Simulate AJAX (di produksi ganti dengan fetch ke endpoint)
            setTimeout(function () {
                btn.innerHTML = '<i class="fa-solid fa-check"></i> Berhasil!';
                btn.style.background = 'var(--emerald)';
                btn.style.color = '#fff';

                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil Berlangganan!',
                    html: 'Terima kasih! Kami akan mengirimkan update terbaru ke <strong>' + email + '</strong>',
                    confirmButtonColor: '#8A1538',
                    timer: 3000,
                    timerProgressBar: true
                });

                newsletterForm.reset();

                setTimeout(function () {
                    btn.innerHTML = originalHtml;
                    btn.style.background = '';
                    btn.style.color = '';
                    btn.disabled = false;
                    input.disabled = false;
                }, 2500);
            }, 1200);
        });
    }

    // === Scroll to Top with Progress ===
    var scrollTopBtn = document.getElementById('scrollTopBtn');
    var progressCircle = document.querySelector('.scroll-top-progress circle');
    var circumference = 283; // 2 * PI * 45

    if (scrollTopBtn && progressCircle) {
        var ticking = false;

        function updateScrollUI() {
            var scrollTop = window.scrollY;
            var docHeight = document.documentElement.scrollHeight - window.innerHeight;
            var scrollPercent = docHeight > 0 ? (scrollTop / docHeight) * 100 : 0;

            scrollTopBtn.classList.toggle('visible', scrollTop > 300);
            progressCircle.style.strokeDashoffset = circumference - (scrollPercent / 100) * circumference;

            ticking = false;
        }

        window.addEventListener('scroll', function () {
            if (!ticking) {
                requestAnimationFrame(updateScrollUI);
                ticking = true;
            }
        }, { passive: true });

        scrollTopBtn.addEventListener('click', function () {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    // === Auto-hide floating elements when footer visible ===
    var footer = document.getElementById('footer');
    if (footer && scrollTopBtn) {
        var footerObserver = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    scrollTopBtn.style.opacity = '0.3';
                    scrollTopBtn.style.transform = 'translateY(0) scale(0.9)';
                } else {
                    scrollTopBtn.style.opacity = '';
                    scrollTopBtn.style.transform = '';
                }
            });
        }, { threshold: 0.1 });
        footerObserver.observe(footer);
    }
})();
</script>

<script src="<?= PUBLIC_URL ?>assets/js/public.js?v=<?= (@filemtime(__DIR__ . '/../public/assets/js/public.js') ?: time()) ?>"></script>
</body>
</html>