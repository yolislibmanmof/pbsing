/**
 * =====================================================================
 * PBSI UMMaumere - Ultimate Public JS (Refactored & Synchronized)
 * =====================================================================
 */

// ===== 1. NAVBAR SCROLL & HIDE/SHOW =====
const navbar = document.getElementById('navbar');
let lastScroll = 0;

window.addEventListener('scroll', function () {
    const currentScroll = window.scrollY;
    if (navbar) {
        if (currentScroll > 50) navbar.classList.add('scrolled');
        else navbar.classList.remove('scrolled');

        // Hide navbar on scroll down, show on scroll up
        if (currentScroll > lastScroll && currentScroll > 200) {
            navbar.style.transform = 'translateY(-100%)';
        } else {
            navbar.style.transform = 'translateY(0)';
        }
    }
    lastScroll = currentScroll;
});

// ===== 2. MOBILE MENU & DROPDOWN =====
const navToggle = document.getElementById('navToggle');
const navMenu = document.getElementById('navMenu');

if (navToggle && navMenu) {
    navToggle.addEventListener('click', function () {
        navMenu.classList.toggle('active');
        const icon = navToggle.querySelector('i');
        if (navMenu.classList.contains('active')) {
            icon.classList.replace('fa-bars', 'fa-xmark');
            document.body.style.overflow = 'hidden';
        } else {
            icon.classList.replace('fa-xmark', 'fa-bars');
            document.body.style.overflow = '';
        }
    });

    // Close menu when clicking links (mobile)
    document.querySelectorAll('.nav-menu a').forEach(function (link) {
        link.addEventListener('click', function () {
            if (window.innerWidth <= 900 && navMenu.classList.contains('active')) {
                navMenu.classList.remove('active');
                navToggle.querySelector('i').classList.replace('fa-xmark', 'fa-bars');
                document.body.style.overflow = '';
            }
        });
    });
}

// Dropdown toggle for mobile
document.querySelectorAll('.has-dropdown > a').forEach(function (trigger) {
    trigger.addEventListener('click', function (e) {
        if (window.innerWidth <= 900) {
            e.preventDefault();
            this.parentElement.classList.toggle('open');
        }
    });
});

// ===== 3. SCROLL ANIMATIONS (UPDATED FOR hx- CLASSES) =====
function triggerScrollAnimations() {
    const observerOptions = { threshold: 0.1, rootMargin: '0px 0px -50px 0px' };
    
    const observer = new IntersectionObserver(function (entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);
    
    // Gabungan class lama dan class baru (hx-, home-)
    const animatables = document.querySelectorAll(`
        .feature-card, .stat-item, .news-card, .contact-card, .testimonial-card, .section-title,
        .hx-news-main, .hx-news-mini, .home-dosen-card, .hx-testi-card, .hx-float-card, 
        .hx-about-text, .hx-collage, .mk-card, .org-card, .misi-card, .tujuan-item, .nilai-card
    `);
    
    animatables.forEach(el => {
        if (!el.classList.contains('fade-in-up')) {
            el.classList.add('fade-in-up');
        }
        observer.observe(el);
    });
}

// ===== 4. COUNTER ANIMATION (UNIFIED & OPTIMIZED) =====
(function () {
    function animateCount(el, target) {
        var dur = 1500, start = null;
        function step(ts) {
            if (!start) start = ts;
            var p = Math.min((ts - start) / dur, 1);
            // Easing function untuk animasi yang lebih premium (ease-out)
            var ease = p < 0.5 ? 2 * p * p : -1 + (4 - 2 * p) * p; 
            el.textContent = Math.floor(ease * target).toLocaleString('id-ID');
            if (p < 1) { 
                requestAnimationFrame(step); 
            } else { 
                el.textContent = target.toLocaleString('id-ID'); 
            }
        }
        requestAnimationFrame(step);
    }

    var obs = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting && !entry.target.dataset.done) {
                entry.target.dataset.done = '1';
                var target = parseInt(entry.target.getAttribute('data-target'), 10);
                if(!isNaN(target)) animateCount(entry.target, target);
                obs.unobserve(entry.target);
            }
        });
    }, { threshold: 0.4 });
    
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-target]').forEach(function (el) { obs.observe(el); });
    });
})();

// ===== 5. SMOOTH SCROLL =====
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        const href = this.getAttribute('href');
        if (href !== '#' && document.querySelector(href)) {
            e.preventDefault();
            const target = document.querySelector(href);
            const offsetTop = target.offsetTop - 80;
            window.scrollTo({ top: offsetTop, behavior: 'smooth' });
        }
    });
});

// ===== 6. VIDEO PLACEHOLDER (FIXED: klik play-button, keyboard, autoplay-safe) =====
window.activateVideoPlaceholder = function (trigger) {
    // Naik ke elemen placeholder sesungguhnya (pemilik data-video)
    var placeholder = trigger.closest('.home-video-placeholder, .video-placeholder') || trigger;
    var wrapper = placeholder.closest('.home-video-wrapper, .video-wrapper') || placeholder.parentElement;
    if (!wrapper || wrapper.querySelector('iframe')) return; // cegah duplikat iframe

    var videoUrl = placeholder.getAttribute('data-video')
                || wrapper.getAttribute('data-video')
                || wrapper.getAttribute('data-video-url');
    if (!videoUrl) return;

    // autoplay wajib mute agar tidak diblokir kebijakan browser
    videoUrl += (videoUrl.indexOf('?') === -1 ? '?' : '&') + 'autoplay=1&mute=1&playsinline=1';

    var iframe = document.createElement('iframe');
    iframe.src = videoUrl;
    iframe.allow = 'autoplay; encrypted-media; picture-in-picture; fullscreen';
    iframe.allowFullscreen = true;
    iframe.style.cssText = 'position:absolute; inset:0; width:100%; height:100%; border:none; z-index:2;';
    wrapper.appendChild(iframe);
    placeholder.style.display = 'none'; // singkirkan placeholder dengan aman
};

// Delegasi klik: tombol play, ikon di dalamnya, maupun area placeholder
document.addEventListener('click', function (e) {
    var trigger = e.target.closest('.home-video-placeholder, .video-placeholder, .play-button');
    if (trigger) window.activateVideoPlaceholder(trigger);
});

// Dukungan keyboard (Enter / Spasi) karena placeholder ber-role="button"
document.addEventListener('keydown', function (e) {
    if (e.key !== 'Enter' && e.key !== ' ') return;
    var trigger = e.target.closest ? e.target.closest('.home-video-placeholder, .video-placeholder') : null;
    if (trigger) {
        e.preventDefault();
        window.activateVideoPlaceholder(trigger);
    }
});

// ===== 7. FORM VALIDATION =====
document.querySelectorAll('form[data-validate]').forEach(form => {
    form.addEventListener('submit', function(e) {
        let isValid = true;
        const inputs = this.querySelectorAll('input[required], textarea[required], select[required]');
        
        inputs.forEach(input => {
            if (!input.value.trim()) {
                isValid = false;
                input.style.borderColor = 'var(--crimson)';
                input.style.animation = 'shake 0.5s';
                setTimeout(() => { input.style.animation = ''; }, 500);
            } else {
                input.style.borderColor = 'var(--emerald)';
            }
        });
        
        if (!isValid) {
            e.preventDefault();
            alert('Mohon lengkapi semua field yang diperlukan.');
        }
    });
});

// ===== 8. TAB SEMESTER KURIKULUM =====
document.querySelectorAll('.sem-tab').forEach(function (tab) {
    tab.addEventListener('click', function () {
        document.querySelectorAll('.sem-tab').forEach(function (t) { t.classList.remove('active'); });
        document.querySelectorAll('.sem-panel').forEach(function (p) { p.classList.remove('active'); });
        this.classList.add('active');
        var panel = document.getElementById('sem-' + this.getAttribute('data-sem'));
        if (panel) panel.classList.add('active');
    });
});

// ===== 9. LOADING STATE FOR INTERNAL LINKS =====
document.querySelectorAll('a[href]').forEach(link => {
    if (!link.getAttribute('href').startsWith('#') && !link.getAttribute('href').startsWith('javascript')) {
        link.addEventListener('click', function() {
            if (this.hostname === window.location.hostname) {
                document.body.style.cursor = 'wait';
            }
        });
    }
});

// ===== 10. CONSOLE EASTER EGG =====
console.log('%c🎓 PBSI UMMaumere - Ultimate Edition', 'font-size: 20px; font-weight: bold; color: #8A1538;');
console.log('%cWebsite ini dibangun dengan ❤️ dan teknologi modern', 'font-size: 14px; color: #87CEEB;');

// ===== INITIALIZE ON DOM READY =====
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(triggerScrollAnimations, 100);
});

/* =========================================
   HOMEPAGE SLIDER & VIDEO ENGINE (Evakuasi dari index.php)
   ========================================= */
(function () {
    'use strict';
    var slider = document.getElementById('hxSlider');
    if (!slider) return; // Keluar jika bukan halaman beranda

    var slides = slider.querySelectorAll('.hx-slide');
    var dots = slider.querySelectorAll('.hx-dot');
    var bar = document.getElementById('hxBar');
    var nowEl = document.getElementById('hxNow');
    var cur = 0, timer = null, barTimer = null;
    var DUR = 7000, STEP = 40, prog = 0;

    /* Pecah judul jadi kata-kata untuk animasi stagger */
    slides.forEach(function (sl) {
        var h = sl.querySelector('.hx-title');
        if (!h) return;
        var words = h.textContent.trim().split(/\s+/);
        h.textContent = '';
        words.forEach(function (w, i) {
            var s = document.createElement('span');
            s.className = 'w';
            s.style.transitionDelay = (0.25 + i * 0.09) + 's';
            s.textContent = w;
            h.appendChild(s);
            h.appendChild(document.createTextNode(' '));
        });
    });

    function paint() {
        slides.forEach(function (s, i) { s.classList.toggle('active', i === cur); });
        dots.forEach(function (d, i) { d.classList.toggle('active', i === cur); });
        if (nowEl) nowEl.textContent = String(cur + 1).padStart(2, '0');
        slides.forEach(function (s, i) {
            var vc = s.querySelector('.hx-slide-video');
            if (vc && i !== cur) vc.innerHTML = '';
        });
        var v = slides[cur].getAttribute('data-video');
        if (v) {
            setTimeout(function () {
                var vc = slides[cur].querySelector('.hx-slide-video');
                if (vc && !vc.innerHTML) vc.innerHTML = '<iframe src="' + v + '" allow="autoplay; encrypted-media" allowfullscreen></iframe>';
            }, 600);
        }
    }

    function runBar() {
        prog = 0; bar.style.width = '0%';
        clearInterval(barTimer);
        barTimer = setInterval(function () {
            prog += (STEP / DUR) * 100;
            bar.style.width = Math.min(prog, 100) + '%';
        }, STEP);
    }
    function go(i) { cur = (i + slides.length) % slides.length; paint(); runBar(); }
    function play() { stop(); timer = setInterval(function () { go(cur + 1); }, DUR); runBar(); }
    function stop() { clearInterval(timer); clearInterval(barTimer); }

    dots.forEach(function (d) { d.addEventListener('click', function () { stop(); go(+this.getAttribute('data-i')); play(); }); });
    
    var prevBtn = slider.querySelector('.hx-prev');
    var nextBtn = slider.querySelector('.hx-next');
    if(prevBtn) prevBtn.addEventListener('click', function () { stop(); go(cur - 1); play(); });
    if(nextBtn) nextBtn.addEventListener('click', function () { stop(); go(cur + 1); play(); });
    
    slider.addEventListener('mouseenter', stop);
    slider.addEventListener('mouseleave', play);

    var tx = 0;
    slider.addEventListener('touchstart', function (e) { tx = e.touches[0].clientX; stop(); }, { passive: true });
    slider.addEventListener('touchend', function (e) {
        var d = tx - e.changedTouches[0].clientX;
        if (Math.abs(d) > 45) go(d > 0 ? cur + 1 : cur - 1);
        play();
    }, { passive: true });

    slider.addEventListener('mousemove', function (e) {
        var r = slider.getBoundingClientRect();
        var mx = ((e.clientX - r.left) / r.width - 0.5) * 2;
        var my = ((e.clientY - r.top) / r.height - 0.5) * 2;
        slider.style.setProperty('--mx', mx.toFixed(3));
        slider.style.setProperty('--my', my.toFixed(3));
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowRight') { stop(); go(cur + 1); play(); }
        if (e.key === 'ArrowLeft') { stop(); go(cur - 1); play(); }
    });

    paint(); play();
})();