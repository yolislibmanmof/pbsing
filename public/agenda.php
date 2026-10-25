<?php
require_once '../includes/config.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';
check_ip_security($pdo);

$page_title = 'Agenda & Kalender';
require_once '../includes/public_header.php';

$agenda = get_all('agenda', null, 'tanggal_mulai DESC');
$setting = get_all('settings', 'id = 1')[0];

// Extract unique types and statuses
$types = [];
$statuses = [];
if (!empty($agenda)) {
    foreach ($agenda as $a) {
        if (!empty($a['tipe'])) $types[] = $a['tipe'];
        if (!empty($a['status'])) $statuses[] = $a['status'];
    }
    $types = array_unique($types);
    $statuses = array_unique($statuses);
}

// Statistics
$totalAgenda = count($agenda);
$upcomingCount = 0;
$ongoingCount = 0;
$completedCount = 0;
$now = date('Y-m-d');

foreach ($agenda as $a) {
    $startDate = $a['tanggal_mulai'];
    $endDate = $a['tanggal_selesai'] ?? $startDate;
    
    if ($startDate > $now) $upcomingCount++;
    elseif ($endDate >= $now && $startDate <= $now) $ongoingCount++;
    else $completedCount++;
}
?>

<!-- JSON-LD for Events -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "CollectionPage",
  "name": "Agenda & Kalender - <?= sanitize($setting['site_name'] ?? 'PBSI UMMaumere') ?>",
  "description": "Jadwal kegiatan akademik dan kemahasiswaan",
  "breadcrumb": {
    "@type": "BreadcrumbList",
    "itemListElement": [
      { "@type": "ListItem", "position": 1, "name": "Beranda", "item": "<?= PUBLIC_URL ?>" },
      { "@type": "ListItem", "position": 2, "name": "Agenda" }
    ]
  }
}
</script>

<main id="main-content" role="main">

<!-- PAGE HERO -->
<section class="page-hero">
    <h1>Agenda &amp; Kalender</h1>
    <p>Jadwal kegiatan akademik dan kemahasiswaan.</p>
    <nav class="breadcrumb" aria-label="Breadcrumb">
        <a href="<?= PUBLIC_URL ?>index.php">Beranda</a>
        <span aria-hidden="true">/</span>
        <span aria-current="page">Agenda</span>
    </nav>
</section>

<!-- AGENDA SECTION -->
<?php if (!empty($agenda)): ?>
<section class="agenda-section" aria-labelledby="agenda-heading">
    <div class="section">
        <header class="sec-head">
            <span class="sec-eyebrow ey-crimson">
                <i class="fa-solid fa-calendar-days" aria-hidden="true"></i> 
                JADWAL KEGIATAN
            </span>
            <h2 class="section-title" id="agenda-heading">Agenda &amp; Kalender</h2>
            <p class="section-subtitle">Pantau seluruh kegiatan akademik dan kemahasiswaan PBSI UMMaumere.</p>
        </header>

        <!-- STATS GRID -->
        <div class="agenda-stats" role="list">
            <div class="agenda-stat-card" role="listitem" data-aos="fade-up" data-aos-delay="0">
                <i class="fa-solid fa-calendar-check" aria-hidden="true"></i>
                <h3><?= $totalAgenda ?></h3>
                <p>Total Agenda</p>
            </div>
            <div class="agenda-stat-card upcoming" role="listitem" data-aos="fade-up" data-aos-delay="100">
                <i class="fa-solid fa-clock" aria-hidden="true"></i>
                <h3><?= $upcomingCount ?></h3>
                <p>Akan Datang</p>
            </div>
            <div class="agenda-stat-card ongoing" role="listitem" data-aos="fade-up" data-aos-delay="200">
                <i class="fa-solid fa-play" aria-hidden="true"></i>
                <h3><?= $ongoingCount ?></h3>
                <p>Sedang Berjalan</p>
            </div>
            <div class="agenda-stat-card completed" role="listitem" data-aos="fade-up" data-aos-delay="300">
                <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                <h3><?= $completedCount ?></h3>
                <p>Selesai</p>
            </div>
        </div>

        <!-- SEARCH & FILTER -->
        <div class="agenda-controls">
            <div class="search-box">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <input type="text" 
                       id="agendaSearch" 
                       placeholder="Cari agenda..." 
                       aria-label="Cari agenda">
            </div>
            
            <?php if (count($types) > 1): ?>
            <div class="filter-dropdown">
                <label for="typeFilter">
                    <i class="fa-solid fa-tag" aria-hidden="true"></i> Tipe:
                </label>
                <select id="typeFilter" aria-label="Filter berdasarkan tipe">
                    <option value="all">Semua Tipe</option>
                    <?php foreach ($types as $type): ?>
                    <option value="<?= sanitize(strtolower($type)) ?>"><?= sanitize($type) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            
            <?php if (count($statuses) > 1): ?>
            <div class="filter-dropdown">
                <label for="statusFilter">
                    <i class="fa-solid fa-filter" aria-hidden="true"></i> Status:
                </label>
                <select id="statusFilter" aria-label="Filter berdasarkan status">
                    <option value="all">Semua Status</option>
                    <?php foreach ($statuses as $status): ?>
                    <option value="<?= sanitize(strtolower($status)) ?>"><?= ucwords(str_replace('_', ' ', $status)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
        </div>

        <!-- AGENDA LIST -->
        <div class="agenda-list" id="agendaList" role="list">
            <?php foreach ($agenda as $i => $a): 
                $type = !empty($a['tipe']) ? sanitize(strtolower($a['tipe'])) : '';
                $status = !empty($a['status']) ? sanitize(strtolower($a['status'])) : '';
                $startDate = strtotime($a['tanggal_mulai']);
                $endDate = isset($a['tanggal_selesai']) ? strtotime($a['tanggal_selesai']) : $startDate;
                $isUpcoming = $startDate > time();
            ?>
            <article class="agenda-item" 
                     data-type="<?= $type ?>"
                     data-status="<?= $status ?>"
                     data-title="<?= sanitize(strtolower($a['judul'])) ?>"
                     role="listitem"
                     data-aos="fade-up" 
                     data-aos-delay="<?= ($i % 6) * 60 ?>">
                
                <!-- Date Badge -->
                <div class="agenda-date">
                    <span class="day"><?= date('d', $startDate) ?></span>
                    <span class="month"><?= date('M', $startDate) ?></span>
                    <span class="year"><?= date('Y', $startDate) ?></span>
                </div>

                <!-- Agenda Info -->
                <div class="agenda-info">
                    <h3><?= sanitize($a['judul']) ?></h3>
                    
                    <?php if (!empty($a['deskripsi'])): ?>
                    <p class="agenda-desc"><?= sanitize($a['deskripsi']) ?></p>
                    <?php endif; ?>
                    
                    <div class="agenda-meta">
                        <span class="meta-item">
                            <i class="fa-solid fa-tag" aria-hidden="true"></i>
                            <?= sanitize($a['tipe']) ?>
                        </span>
                        <?php if (!empty($a['lokasi'])): ?>
                        <span class="meta-item">
                            <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                            <?= sanitize($a['lokasi']) ?>
                        </span>
                        <?php endif; ?>
                        <span class="meta-item">
                            <i class="fa-solid fa-clock" aria-hidden="true"></i>
                            <?= date('H:i', $startDate) ?> WITA
                            <?php if ($endDate > $startDate): ?>
                            - <?= date('d M Y H:i', $endDate) ?>
                            <?php endif; ?>
                        </span>
                    </div>

                    <!-- Countdown untuk agenda mendatang -->
                    <?php if ($isUpcoming): ?>
                    <div class="agenda-countdown" data-date="<?= date('Y-m-d\TH:i:s', $startDate) ?>">
                        <i class="fa-solid fa-hourglass-half" aria-hidden="true"></i>
                        <span class="countdown-text">Menghitung...</span>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Actions -->
                <div class="agenda-actions">
                    <span class="status-badge st-<?= $status ?>">
                        <?php 
                        $statusIcon = [
                            'upcoming' => 'fa-clock',
                            'ongoing' => 'fa-play',
                            'completed' => 'fa-circle-check',
                            'cancelled' => 'fa-circle-xmark'
                        ];
                        $icon = $statusIcon[$status] ?? 'fa-circle';
                        ?>
                        <i class="fa-solid <?= $icon ?>" aria-hidden="true"></i>
                        <?= ucwords(str_replace('_', ' ', $a['status'])) ?>
                    </span>
                    
                    <div class="agenda-buttons">
                        <button class="btn-calendar" 
                                onclick="addToCalendar(<?= htmlspecialchars(json_encode($a), ENT_QUOTES, 'UTF-8') ?>)"
                                aria-label="Tambahkan ke kalender"
                                title="Tambahkan ke kalender">
                            <i class="fa-solid fa-calendar-plus" aria-hidden="true"></i>
                        </button>
                        <?php if (!empty($a['link'])): ?>
                        <a href="<?= sanitize($a['link']) ?>" 
                           target="_blank" 
                           rel="noopener noreferrer"
                           class="btn-detail"
                           aria-label="Detail agenda">
                            <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>

        <!-- AGENDA COUNTER -->
        <div class="agenda-counter" aria-live="polite">
            <p>Menampilkan <strong id="visible-count"><?= $totalAgenda ?></strong> dari <strong><?= $totalAgenda ?></strong> agenda</p>
        </div>
    </div>
</section>
<?php else: ?>
<!-- EMPTY STATE -->
<section class="section">
    <div class="empty-state" style="text-align:center;padding:5rem 2rem;">
        <i class="fa-solid fa-calendar-xmark" style="font-size:5rem;color:#ddd;margin-bottom:1.5rem;" aria-hidden="true"></i>
        <h2 style="color:#999;margin-bottom:.5rem;">Belum Ada Agenda</h2>
        <p style="color:#aaa;max-width:500px;margin:0 auto;">Agenda kegiatan sedang dalam proses penjadwalan. Silakan kunjungi kembali nanti.</p>
        <a href="<?= PUBLIC_URL ?>berita.php" class="btn-primary" style="margin-top:2rem;">
            <i class="fa-solid fa-newspaper" aria-hidden="true"></i> Lihat Berita Terbaru
        </a>
    </div>
</section>
<?php endif; ?>

</main>

<!-- Inline Styles for Agenda Page -->
<style>
/* Agenda Section */
.agenda-section {
    background: var(--bg-light);
    padding: 5rem 1.5rem;
}

/* Agenda Stats */
.agenda-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-bottom: 3rem;
}
.agenda-stat-card {
    background: #fff;
    border-radius: 20px;
    padding: 2rem;
    text-align: center;
    box-shadow: 0 10px 35px rgba(0,0,0,.08);
    transition: all .3s ease;
    border-bottom: 4px solid var(--crimson);
}
.agenda-stat-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 20px 50px rgba(0,0,0,.15);
}
.agenda-stat-card.upcoming { border-bottom-color: var(--sky-blue); }
.agenda-stat-card.ongoing { border-bottom-color: var(--emerald); }
.agenda-stat-card.completed { border-bottom-color: #95a5a6; }
.agenda-stat-card i {
    font-size: 2.5rem;
    color: var(--crimson);
    margin-bottom: 1rem;
}
.agenda-stat-card.upcoming i { color: var(--sky-blue); }
.agenda-stat-card.ongoing i { color: var(--emerald); }
.agenda-stat-card.completed i { color: #95a5a6; }
.agenda-stat-card h3 {
    font-size: 2.5rem;
    font-weight: 900;
    color: var(--rich-black);
    margin-bottom: .5rem;
}
.agenda-stat-card p {
    color: var(--text-grey);
    font-size: .95rem;
    font-weight: 600;
}

/* Controls */
.agenda-controls {
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

/* Agenda List */
.agenda-list {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}
.agenda-item {
    background: #fff;
    border-radius: 20px;
    padding: 2rem;
    box-shadow: 0 10px 35px rgba(0,0,0,.08);
    transition: all .4s cubic-bezier(.4,0,.2,1);
    display: grid;
    grid-template-columns: 120px 1fr auto;
    gap: 2rem;
    align-items: start;
    border-left: 5px solid var(--crimson);
}
.agenda-item:hover {
    transform: translateX(8px);
    box-shadow: 0 20px 50px rgba(0,0,0,.12);
}
.agenda-item.hidden {
    display: none;
}

/* Date Badge */
.agenda-date {
    background: linear-gradient(135deg, var(--crimson), var(--crimson-light));
    color: #fff;
    border-radius: 16px;
    padding: 1.5rem 1rem;
    text-align: center;
    box-shadow: 0 8px 25px rgba(138,21,56,.3);
}
.agenda-date .day {
    display: block;
    font-size: 2.5rem;
    font-weight: 900;
    line-height: 1;
    margin-bottom: .3rem;
}
.agenda-date .month {
    display: block;
    font-size: .9rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: .2rem;
}
.agenda-date .year {
    display: block;
    font-size: .75rem;
    opacity: .8;
}

/* Agenda Info */
.agenda-info h3 {
    font-size: 1.3rem;
    margin-bottom: .8rem;
    color: var(--rich-black);
    font-weight: 700;
    line-height: 1.4;
}
.agenda-desc {
    color: var(--text-grey);
    font-size: .95rem;
    line-height: 1.7;
    margin-bottom: 1rem;
}
.agenda-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 1.2rem;
    margin-bottom: 1rem;
}
.meta-item {
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    color: var(--text-grey);
    font-size: .88rem;
}
.meta-item i {
    color: var(--crimson);
}

/* Countdown */
.agenda-countdown {
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    background: rgba(135,206,235,.1);
    color: var(--sky-blue-dark);
    padding: .5rem 1.2rem;
    border-radius: 50px;
    font-size: .85rem;
    font-weight: 600;
    margin-top: .5rem;
}
.agenda-countdown i {
    animation: spin 2s linear infinite;
}

/* Actions */
.agenda-actions {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    align-items: flex-end;
}
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    padding: .5rem 1.2rem;
    border-radius: 50px;
    font-size: .78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .8px;
}
.st-upcoming {
    background: rgba(135,206,235,.15);
    color: var(--sky-blue-dark);
}
.st-ongoing {
    background: rgba(46,139,87,.15);
    color: var(--emerald);
}
.st-completed {
    background: rgba(149,165,166,.15);
    color: #95a5a6;
}
.st-cancelled {
    background: rgba(231,76,60,.15);
    color: #e74c3c;
}
.agenda-buttons {
    display: flex;
    gap: .6rem;
}
.btn-calendar, .btn-detail {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--bg-light);
    color: var(--rich-black);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    transition: all .3s ease;
    border: none;
    cursor: pointer;
    text-decoration: none;
}
.btn-calendar:hover, .btn-detail:hover {
    background: var(--crimson);
    color: #fff;
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(138,21,56,.3);
}

/* Counter */
.agenda-counter {
    text-align: center;
    margin-top: 3rem;
    padding: 1.5rem;
    background: rgba(138,21,56,.05);
    border-radius: 12px;
    color: var(--text-grey);
}
.agenda-counter strong {
    color: var(--crimson);
}

/* Responsive */
@media (max-width: 900px) {
    .agenda-item {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }
    .agenda-date {
        max-width: 120px;
    }
    .agenda-actions {
        align-items: flex-start;
        flex-direction: row;
        justify-content: space-between;
    }
    .agenda-controls {
        flex-direction: column;
    }
    .search-box {
        max-width: 100%;
    }
}
</style>

<!-- Agenda Page JavaScript -->
<script>
(function() {
    'use strict';
    
    const agendaItems = document.querySelectorAll('.agenda-item');
    const searchInput = document.getElementById('agendaSearch');
    const typeFilter = document.getElementById('typeFilter');
    const statusFilter = document.getElementById('statusFilter');
    const visibleCountEl = document.getElementById('visible-count');
    
    function filterAgenda() {
        const searchTerm = searchInput ? searchInput.value.toLowerCase() : '';
        const typeValue = typeFilter ? typeFilter.value : 'all';
        const statusValue = statusFilter ? statusFilter.value : 'all';
        
        let visibleCount = 0;
        
        agendaItems.forEach(item => {
            const title = item.dataset.title || '';
            const type = item.dataset.type || '';
            const status = item.dataset.status || '';
            
            const matchesSearch = title.includes(searchTerm);
            const matchesType = typeValue === 'all' || type === typeValue;
            const matchesStatus = statusValue === 'all' || status === statusValue;
            
            if (matchesSearch && matchesType && matchesStatus) {
                item.classList.remove('hidden');
                visibleCount++;
            } else {
                item.classList.add('hidden');
            }
        });
        
        if (visibleCountEl) {
            visibleCountEl.textContent = visibleCount;
        }
    }
    
    if (searchInput) searchInput.addEventListener('input', filterAgenda);
    if (typeFilter) typeFilter.addEventListener('change', filterAgenda);
    if (statusFilter) statusFilter.addEventListener('change', filterAgenda);
    
    // Countdown Timer
    const countdowns = document.querySelectorAll('.agenda-countdown');
    countdowns.forEach(countdown => {
        const targetDate = new Date(countdown.dataset.date).getTime();
        
        function updateCountdown() {
            const now = new Date().getTime();
            const distance = targetDate - now;
            
            if (distance < 0) {
                countdown.querySelector('.countdown-text').textContent = 'Dimulai!';
                return;
            }
            
            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            
            let text = '';
            if (days > 0) text = `${days} hari lagi`;
            else if (hours > 0) text = `${hours} jam lagi`;
            else text = 'Segera dimulai!';
            
            countdown.querySelector('.countdown-text').textContent = text;
        }
        
        updateCountdown();
        setInterval(updateCountdown, 60000);
    });
    
    // Add to Calendar
    window.addToCalendar = function(agenda) {
        const startDate = new Date(agenda.tanggal_mulai);
        const endDate = agenda.tanggal_selesai ? new Date(agenda.tanggal_selesai) : new Date(startDate.getTime() + 3600000);
        
        const format = (d) => d.toISOString().replace(/[-:]/g, '').split('.')[0] + 'Z';
        
        const googleUrl = `https://calendar.google.com/calendar/render?action=TEMPLATE&text=${encodeURIComponent(agenda.judul)}&dates=${format(startDate)}/${format(endDate)}&details=${encodeURIComponent(agenda.deskripsi || '')}&location=${encodeURIComponent(agenda.lokasi || '')}`;
        
        window.open(googleUrl, '_blank');
    };
})();
</script>

<?php require_once '../includes/public_footer.php'; ?>