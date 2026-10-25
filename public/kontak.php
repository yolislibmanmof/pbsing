<?php
require_once '../includes/config.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';
check_ip_security($pdo);

$page_title = 'Kontak';
require_once '../includes/public_header.php';

$setting = get_all('settings', 'id = 1')[0];
?>

<!-- JSON-LD for LocalBusiness -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "EducationalOrganization",
  "name": "<?= sanitize($setting['site_name'] ?? 'PBSI UMMaumere') ?>",
  "image": "<?= PUBLIC_URL ?>assets/images/logo.png",
  "telephone": "<?= sanitize($setting['telepon'] ?? '') ?>",
  "email": "<?= sanitize($setting['email'] ?? '') ?>",
  "address": {
    "@type": "PostalAddress",
    "streetAddress": "<?= sanitize($setting['alamat'] ?? '') ?>",
    "addressLocality": "Maumere",
    "addressRegion": "Nusa Tenggara Timur",
    "addressCountry": "ID"
  },
  "openingHours": "Mo-Fr 08:00-16:00",
  "sameAs": [
    "<?= sanitize($setting['facebook'] ?? '') ?>",
    "<?= sanitize($setting['instagram'] ?? '') ?>",
    "<?= sanitize($setting['youtube'] ?? '') ?>"
  ],
  "contactPoint": {
    "@type": "ContactPoint",
    "telephone": "<?= sanitize($setting['telepon'] ?? '') ?>",
    "contactType": "Admissions",
    "availableLanguage": ["Indonesian", "English"]
  }
}
</script>

<main id="main-content" role="main">

<!-- PAGE HERO -->
<section class="page-hero">
    <h1>Kontak Kami</h1>
    <p>Kami siap menjawab pertanyaan Anda seputar penerimaan mahasiswa baru dan kerja sama.</p>
    <nav class="breadcrumb" aria-label="Breadcrumb">
        <a href="<?= PUBLIC_URL ?>index.php">Beranda</a>
        <span aria-hidden="true">/</span>
        <span aria-current="page">Kontak</span>
    </nav>
</section>

<!-- CONTACT INFO GRID -->
<section class="section contact-info-section">
    <header class="sec-head">
        <span class="sec-eyebrow ey-crimson">
            <i class="fa-solid fa-headset" aria-hidden="true"></i> 
            INFORMASI KONTAK
        </span>
        <h2 class="section-title">Hubungi Kami</h2>
        <p class="section-subtitle">Tim kami siap membantu Anda dalam jam kerja.</p>
    </header>

    <div class="contact-grid" role="list">
        <article class="contact-card" role="listitem" data-aos="fade-up" data-aos-delay="0">
            <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
            <h3>Alamat Kampus</h3>
            <p><?= sanitize($setting['alamat'] ?? 'Jl. Pendidikan No. 1, Maumere, NTT') ?></p>
            <a href="https://maps.google.com/?q=<?= urlencode($setting['alamat'] ?? '') ?>" 
               target="_blank" 
               rel="noopener noreferrer"
               class="contact-action"
               aria-label="Buka lokasi di Google Maps">
                <i class="fa-solid fa-map-location-dot" aria-hidden="true"></i> 
                Lihat Peta
            </a>
        </article>

        <article class="contact-card" role="listitem" data-aos="fade-up" data-aos-delay="100">
            <i class="fa-solid fa-phone" aria-hidden="true"></i>
            <h3>Telepon</h3>
            <p><?= sanitize($setting['telepon'] ?? '+62 xxx-xxxx-xxxx') ?></p>
            <a href="tel:<?= sanitize($setting['telepon'] ?? '') ?>" 
               class="contact-action"
               aria-label="Hubungi via telepon">
                <i class="fa-solid fa-phone-volume" aria-hidden="true"></i> 
                Hubungi Sekarang
            </a>
        </article>

        <article class="contact-card" role="listitem" data-aos="fade-up" data-aos-delay="200">
            <i class="fa-solid fa-envelope" aria-hidden="true"></i>
            <h3>Email Resmi</h3>
            <p><?= sanitize($setting['email'] ?? 'info@pbsi-ummaumere.ac.id') ?></p>
            <a href="mailto:<?= sanitize($setting['email'] ?? '') ?>" 
               class="contact-action"
               aria-label="Kirim email">
                <i class="fa-solid fa-paper-plane" aria-hidden="true"></i> 
                Kirim Email
            </a>
        </article>

        <article class="contact-card" role="listitem" data-aos="fade-up" data-aos-delay="300">
            <i class="fa-solid fa-clock" aria-hidden="true"></i>
            <h3>Jam Layanan</h3>
            <p><strong>Senin - Jumat</strong><br>08.00 - 16.00 WITA</p>
            <span class="contact-action" style="cursor:default;">
                <i class="fa-solid fa-circle-check" aria-hidden="true"></i> 
                <span id="office-status">Memeriksa Status...</span>
            </span>
        </article>
    </div>
</section>

<!-- CONTACT FORM & MAP -->
<section class="contact-form-section" aria-labelledby="form-heading">
    <div class="contact-form-container">
        <div class="contact-form-wrapper" data-aos="fade-right" data-aos-duration="1000">
            <header class="sec-head" style="text-align:left;">
                <span class="sec-eyebrow ey-sky">
                    <i class="fa-solid fa-message" aria-hidden="true"></i> 
                    KIRIM PESAN
                </span>
                <h2 id="form-heading" class="section-title" style="text-align:left;">Ada Pertanyaan?</h2>
                <p class="section-subtitle" style="text-align:left;margin:0;">Isi formulir di bawah dan kami akan merespons dalam 1x24 jam.</p>
            </header>

            <form class="contact-form" 
                  id="contactForm" 
                  data-validate
                  action="<?= PUBLIC_URL ?>proses_kontak.php" 
                  method="POST">
                <div class="form-group">
                    <label for="nama">Nama Lengkap <span class="required">*</span></label>
                    <input type="text" 
                           id="nama" 
                           name="nama" 
                           required 
                           aria-required="true"
                           placeholder="Masukkan nama lengkap Anda">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="email">Email <span class="required">*</span></label>
                        <input type="email" 
                               id="email" 
                               name="email" 
                               required 
                               aria-required="true"
                               placeholder="email@contoh.com">
                    </div>
                    <div class="form-group">
                        <label for="telepon">No. Telepon</label>
                        <input type="tel" 
                               id="telepon" 
                               name="telepon" 
                               placeholder="+62 xxx-xxxx-xxxx">
                    </div>
                </div>

                <div class="form-group">
                    <label for="subjek">Subjek <span class="required">*</span></label>
                    <select id="subjek" name="subjek" required aria-required="true">
                        <option value="">Pilih Subjek</option>
                        <option value="pmb">Pendaftaran Mahasiswa Baru</option>
                        <option value="kerjasama">Kerja Sama Institusi</option>
                        <option value="akademik">Informasi Akademik</option>
                        <option value="umum">Pertanyaan Umum</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="pesan">Pesan <span class="required">*</span></label>
                    <textarea id="pesan" 
                              name="pesan" 
                              rows="6" 
                              required 
                              aria-required="true"
                              placeholder="Tulis pesan Anda di sini..."></textarea>
                </div>

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" 
                               name="privacy" 
                               required 
                               aria-required="true">
                        <span>Saya menyetujui <a href="#">Kebijakan Privasi</a> dan data saya akan diproses sesuai ketentuan.</span>
                    </label>
                </div>

                <button type="submit" class="btn-primary btn-full">
                    <i class="fa-solid fa-paper-plane" aria-hidden="true"></i> 
                    Kirim Pesan
                </button>

                <div class="form-message" id="formMessage" role="alert" aria-live="polite"></div>
            </form>
        </div>

        <div class="contact-map-wrapper" data-aos="fade-left" data-aos-duration="1000">
            <div class="map-placeholder">
                <i class="fa-solid fa-map-location-dot" aria-hidden="true"></i>
                <h3>Lokasi Kami</h3>
                <p>Program Studi Bahasa dan Sastra Inggris<br>Universitas Muhammadiyah Maumere</p>
                <a href="https://maps.google.com/?q=<?= urlencode($setting['alamat'] ?? 'Maumere, NTT') ?>" 
                   target="_blank" 
                   rel="noopener noreferrer"
                   class="btn-primary btn-small">
                    <i class="fa-solid fa-directions" aria-hidden="true"></i> 
                    Buka di Google Maps
                </a>
            </div>

            <div class="quick-contact">
                <h3><i class="fa-solid fa-bolt" aria-hidden="true"></i> Kontak Cepat</h3>
                <?php if (!empty($setting['whatsapp'])): ?>
                <a href="https://wa.me/<?= sanitize($setting['whatsapp']) ?>" 
                   target="_blank" 
                   rel="noopener noreferrer"
                   class="quick-btn whatsapp">
                    <i class="fa-brands fa-whatsapp" aria-hidden="true"></i>
                    <span>Chat WhatsApp</span>
                </a>
                <?php endif; ?>
                <a href="tel:<?= sanitize($setting['telepon'] ?? '') ?>" class="quick-btn phone">
                    <i class="fa-solid fa-phone" aria-hidden="true"></i>
                    <span>Telepon Langsung</span>
                </a>
                <a href="mailto:<?= sanitize($setting['email'] ?? '') ?>" class="quick-btn email">
                    <i class="fa-solid fa-envelope" aria-hidden="true"></i>
                    <span>Kirim Email</span>
                </a>
            </div>
        </div>
    </div>
</section>

<!-- FAQ SECTION -->
<section class="faq-section" aria-labelledby="faq-heading">
    <div class="section">
        <header class="sec-head">
            <span class="sec-eyebrow ey-emerald">
                <i class="fa-solid fa-circle-question" aria-hidden="true"></i> 
                PERTANYAAN UMUM
            </span>
            <h2 id="faq-heading" class="section-title">FAQ (Pertanyaan yang Sering Diajukan)</h2>
            <p class="section-subtitle">Jawaban untuk pertanyaan umum seputar PBSI UMMaumere.</p>
        </header>

        <div class="faq-grid">
            <details class="faq-item" data-aos="fade-up" data-aos-delay="0">
                <summary>
                    <span class="faq-q">Kapan pendaftaran mahasiswa baru dibuka?</span>
                    <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                </summary>
                <div class="faq-a">
                    <p>Pendaftaran mahasiswa baru biasanya dibuka pada bulan Januari hingga Juli setiap tahunnya. Untuk informasi terkini, silakan pantau website kami atau hubungi bagian admisi.</p>
                </div>
            </details>

            <details class="faq-item" data-aos="fade-up" data-aos-delay="100">
                <summary>
                    <span class="faq-q">Apakah ada program beasiswa?</span>
                    <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                </summary>
                <div class="faq-a">
                    <p>Ya, kami menyediakan beberapa jenis beasiswa termasuk beasiswa prestasi akademik, beasiswa kurang mampu, dan beasiswa dari mitra eksternal. Informasi lengkap tersedia di halaman Pendaftaran.</p>
                </div>
            </details>

            <details class="faq-item" data-aos="fade-up" data-aos-delay="200">
                <summary>
                    <span class="faq-q">Berapa lama masa studi normal?</span>
                    <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                </summary>
                <div class="faq-a">
                    <p>Masa studi normal untuk program sarjana (S1) adalah 8 semester (4 tahun). Mahasiswa dapat menyelesaikan studi dalam 3.5 hingga 4.5 tahun tergantung kinerja akademik.</p>
                </div>
            </details>

            <details class="faq-item" data-aos="fade-up" data-aos-delay="300">
                <summary>
                    <span class="faq-q">Apakah ada program pertukaran pelajar?</span>
                    <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                </summary>
                <div class="faq-a">
                    <p>Ya, kami memiliki program MBKM (Merdeka Belajar Kampus Merdeka) dan kerja sama dengan universitas mitra di luar negeri untuk program pertukaran pelajar dan magang internasional.</p>
                </div>
            </details>

            <details class="faq-item" data-aos="fade-up" data-aos-delay="400">
                <summary>
                    <span class="faq-q">Bagaimana prospek kerja lulusan PBSI?</span>
                    <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                </summary>
                <div class="faq-a">
                    <p>Lulusan PBSI memiliki prospek kerja yang luas: penerjemah profesional, pengajar bahasa Inggris, editor, content writer, diplomat, researcher, dan berbagai posisi di industri kreatif dan media.</p>
                </div>
            </details>

            <details class="faq-item" data-aos="fade-up" data-aos-delay="500">
                <summary>
                    <span class="faq-q">Apakah tersedia fasilitas Language Lab?</span>
                    <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                </summary>
                <div class="faq-a">
                    <p>Ya, kami memiliki Language Lab modern dengan peralatan audio-visual lengkap, software pembelajaran bahasa, dan studio rekaman untuk praktik translation dan interpreting.</p>
                </div>
            </details>
        </div>
    </div>
</section>

</main>

<!-- Inline Styles for Contact Page -->
<style>
/* Contact Info Section */
.contact-info-section { background: var(--bg-light); }

.contact-action {
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    margin-top: 1rem;
    color: var(--crimson);
    font-weight: 600;
    font-size: .9rem;
    transition: all .3s ease;
}
.contact-action:hover {
    color: var(--sky-blue-dark);
    transform: translateX(4px);
}

/* Contact Form Section */
.contact-form-section {
    padding: 5rem 1.5rem;
    background: #fff;
}
.contact-form-container {
    max-width: 1400px;
    margin: 0 auto;
    display: grid;
    grid-template-columns: 1.2fr 1fr;
    gap: 4rem;
    align-items: start;
}

.contact-form {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}
.form-group {
    display: flex;
    flex-direction: column;
    gap: .5rem;
}
.form-group label {
    font-weight: 600;
    color: var(--rich-black);
    font-size: .95rem;
}
.form-group .required {
    color: var(--crimson);
}
.form-group input,
.form-group select,
.form-group textarea {
    padding: .9rem 1.2rem;
    border: 2px solid #e0e0e0;
    border-radius: 12px;
    font-size: 1rem;
    font-family: inherit;
    transition: all .3s ease;
    background: #fff;
}
.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: var(--sky-blue);
    box-shadow: 0 0 0 4px rgba(135,206,235,.15);
}
.form-group textarea {
    resize: vertical;
    min-height: 120px;
}
.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
}
.checkbox-label {
    display: flex;
    align-items: flex-start;
    gap: .6rem;
    font-size: .9rem;
    color: #666;
    cursor: pointer;
}
.checkbox-label input[type="checkbox"] {
    width: 18px;
    height: 18px;
    margin-top: 2px;
    cursor: pointer;
}
.checkbox-label a {
    color: var(--crimson);
    text-decoration: underline;
}
.btn-full {
    width: 100%;
    margin-top: 1rem;
}
.form-message {
    padding: 1rem;
    border-radius: 10px;
    font-size: .95rem;
    display: none;
}
.form-message.success {
    display: block;
    background: rgba(46,139,87,.1);
    color: var(--emerald);
    border: 1px solid rgba(46,139,87,.3);
}
.form-message.error {
    display: block;
    background: rgba(138,21,56,.1);
    color: var(--crimson);
    border: 1px solid rgba(138,21,56,.3);
}

/* Map Wrapper */
.contact-map-wrapper {
    display: flex;
    flex-direction: column;
    gap: 2rem;
}
.map-placeholder {
    background: linear-gradient(135deg, var(--rich-black), #1a1a2e);
    border-radius: 20px;
    padding: 3rem 2rem;
    text-align: center;
    color: #fff;
    box-shadow: 0 15px 40px rgba(0,0,0,.2);
}
.map-placeholder i {
    font-size: 4rem;
    color: var(--sky-blue);
    margin-bottom: 1.5rem;
}
.map-placeholder h3 {
    font-size: 1.5rem;
    margin-bottom: 1rem;
}
.map-placeholder p {
    color: #bdc3c7;
    margin-bottom: 2rem;
    line-height: 1.6;
}
.btn-small {
    padding: .8rem 1.8rem;
    font-size: .9rem;
}

/* Quick Contact */
.quick-contact {
    background: linear-gradient(135deg, #f8f9fa, #fff);
    border-radius: 20px;
    padding: 2rem;
    box-shadow: 0 10px 30px rgba(0,0,0,.08);
    border: 2px solid var(--crimson);
}
.quick-contact h3 {
    font-size: 1.1rem;
    margin-bottom: 1.5rem;
    color: var(--rich-black);
    display: flex;
    align-items: center;
    gap: .6rem;
}
.quick-contact h3 i {
    color: var(--crimson);
}
.quick-btn {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem 1.5rem;
    border-radius: 12px;
    background: #fff;
    color: var(--rich-black);
    font-weight: 600;
    margin-bottom: 1rem;
    transition: all .3s ease;
    border: 2px solid #e0e0e0;
}
.quick-btn:hover {
    transform: translateX(6px);
    box-shadow: 0 8px 20px rgba(0,0,0,.1);
}
.quick-btn.whatsapp { border-color: #25D366; }
.quick-btn.whatsapp:hover { background: #25D366; color: #fff; }
.quick-btn.whatsapp i { color: #25D366; }
.quick-btn.whatsapp:hover i { color: #fff; }
.quick-btn.phone { border-color: var(--sky-blue); }
.quick-btn.phone:hover { background: var(--sky-blue); color: #fff; }
.quick-btn.phone i { color: var(--sky-blue); }
.quick-btn.phone:hover i { color: #fff; }
.quick-btn.email { border-color: var(--crimson); }
.quick-btn.email:hover { background: var(--crimson); color: #fff; }
.quick-btn.email i { color: var(--crimson); }
.quick-btn.email:hover i { color: #fff; }
.quick-btn i {
    font-size: 1.4rem;
}

/* FAQ Section */
.faq-section {
    background: linear-gradient(180deg, var(--bg-light), #fff);
    padding: 5rem 1.5rem;
}
.faq-grid {
    max-width: 900px;
    margin: 0 auto;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}
.faq-item {
    background: #fff;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 8px 25px rgba(0,0,0,.06);
    transition: all .3s ease;
}
.faq-item:hover {
    box-shadow: 0 12px 35px rgba(0,0,0,.1);
}
.faq-item summary {
    padding: 1.5rem 2rem;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-weight: 700;
    color: var(--rich-black);
    list-style: none;
}
.faq-item summary::-webkit-details-marker {
    display: none;
}
.faq-item summary .faq-q {
    flex: 1;
    padding-right: 1rem;
}
.faq-item summary i {
    color: var(--crimson);
    transition: transform .3s ease;
}
.faq-item[open] summary i {
    transform: rotate(180deg);
}
.faq-a {
    padding: 0 2rem 1.5rem;
    color: var(--text-grey);
    line-height: 1.8;
}

/* Responsive */
@media (max-width: 900px) {
    .contact-form-container {
        grid-template-columns: 1fr;
        gap: 3rem;
    }
    .form-row {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- Office Status Checker -->
<script>
(function() {
    const statusEl = document.getElementById('office-status');
    if (!statusEl) return;
    
    const now = new Date();
    const day = now.getDay(); // 0=Sunday, 6=Saturday
    const hour = now.getHours();
    
    let isOpen = false;
    if (day >= 1 && day <= 5 && hour >= 8 && hour < 16) {
        isOpen = true;
    }
    
    if (isOpen) {
        statusEl.textContent = 'Buka Sekarang';
        statusEl.style.color = 'var(--emerald)';
    } else {
        statusEl.textContent = 'Tutup';
        statusEl.style.color = 'var(--crimson)';
    }
})();

// Contact Form Handler
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('contactForm');
    const messageEl = document.getElementById('formMessage');
    
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Simulate submission
            messageEl.className = 'form-message success';
            messageEl.textContent = '✓ Pesan Anda berhasil dikirim! Kami akan merespons dalam 1x24 jam.';
            messageEl.style.display = 'block';
            
            form.reset();
            
            setTimeout(() => {
                messageEl.style.display = 'none';
            }, 5000);
        });
    }
});
</script>

<?php require_once '../includes/public_footer.php'; ?>