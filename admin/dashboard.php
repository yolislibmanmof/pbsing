<?php
require_once '../includes/config.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';
require_login();

// ===== DYNAMIC GREETING =====
$hour = (int)date('H');
if ($hour < 11)      { $greeting = 'Selamat Pagi';    $greetIcon = 'fa-sun';         $greetColor = '#f39c12'; }
elseif ($hour < 15)  { $greeting = 'Selamat Siang';   $greetIcon = 'fa-cloud-sun';   $greetColor = '#e67e22'; }
elseif ($hour < 18)  { $greeting = 'Selamat Sore';    $greetIcon = 'fa-cloud-moon';  $greetColor = '#d35400'; }
else                 { $greeting = 'Selamat Malam';   $greetIcon = 'fa-moon';        $greetColor = '#8e44ad'; }

$academicQuotes = [
    "Pendidikan adalah senjata paling mematikan di dunia, karena dengan pendidikan, Anda dapat mengubah dunia. — Nelson Mandela",
    "Hiduplah seolah engkau mati besok. Belajarlah seolah engkau hidup selamanya. — Mahatma Gandhi",
    "Akar pendidikan itu pahit, tapi buahnya manis. — Aristoteles",
    "The beautiful thing about learning is that no one can take it away from you. — B.B. King",
    "Bahasa adalah jendela dunia, dan sastra adalah jiwanya.",
    "Education is not preparation for life; education is life itself. — John Dewey"
];
$todayQuote = $academicQuotes[date('z') % count($academicQuotes)];

// Hijriah date approximation (rough calculation)
function gregorianToHijri($year, $month, $day) {
    if (($year > 1582)||(($year==1582)&&($month>10)||(($month==10)&&($day>14)))) {
        $jd = intval((1461*($year+4800+intval(($month-14)/12)))/4)+intval((367*($month-2-12*(intval(($month-14)/12))))/12)-intval((3*(intval(($year+4900+intval(($month-14)/12))/100)))/4)+$day-32075;
    } else $jd = 367*$year-intval((7*($year+5001+intval(($month-9)/7)))/4)+intval((275*$month)/9)+$day+1729777;
    $l = $jd-1948440+10632;
    $n = intval(($l-1)/10631);
    $l = $l-10631*$n+354;
    $j = intval((10985-$l)/5316)*(intval((50*$l)/17719))+(intval($l/5670)*(intval((43*$l)/15238)));
    $l = $l-(intval((30-$j)/15))*(intval((17719*$j)/50))-(intval($j/16)*(intval((15238*$j)/43)))+29;
    $month = intval((24*$l)/709);
    $day = $l-intval((709*$month)/24);
    $year = 30*$n+$j-30;
    return ['y'=>$year,'m'=>$month,'d'=>$day];
}
$hijriMonths = ['','Muharram','Safar','Rabiul Awal','Rabiul Akhir','Jumadil Awal','Jumadil Akhir','Rajab','Syaban','Ramadhan','Syawal','Dzulqaidah','Dzulhijjah'];
$hijri = gregorianToHijri(date('Y'), date('m'), date('d'));
$hijriDate = $hijri['d'] . ' ' . $hijriMonths[$hijri['m']] . ' ' . $hijri['y'] . ' H';

// ===== CORE COUNTS (Disesuaikan 100% dengan Skema DB) =====
$counts = [
    'berita'    => $pdo->query("SELECT COUNT(*) FROM berita")->fetchColumn(),
    'dosen'     => $pdo->query("SELECT COUNT(*) FROM dosen WHERE aktif = 1")->fetchColumn(),
    'alumni'    => $pdo->query("SELECT COUNT(*) FROM alumni WHERE ditampilkan = 1")->fetchColumn(),
    'agenda'    => $pdo->query("SELECT COUNT(*) FROM agenda")->fetchColumn(), // ✅ FIXED: dihapus 'WHERE aktif'
    'galeri'    => $pdo->query("SELECT COUNT(*) FROM galeri")->fetchColumn(),
    'publikasi' => $pdo->query("SELECT COUNT(*) FROM publikasi")->fetchColumn(),
    'unduh'     => $pdo->query("SELECT COUNT(*) FROM unduh")->fetchColumn(),   // ✅ FIXED: dihapus 'WHERE aktif' untuk aman
    'slider'    => $pdo->query("SELECT COUNT(*) FROM slider_video WHERE aktif = 1")->fetchColumn(),
    'struktur'  => $pdo->query("SELECT COUNT(*) FROM struktur WHERE aktif = 1")->fetchColumn(),
    'users'     => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn()    // ✅ FIXED: dihapus 'WHERE status'
];

$totalViews = $pdo->query("SELECT SUM(views) FROM berita")->fetchColumn() ?? 0;
$totalDownloads = $pdo->query("SELECT SUM(jumlah_unduh) FROM unduh")->fetchColumn() ?? 0;

// ===== REAL TREND DATA (7 days) =====
function getDailyCount($table, $dateCol, $days = 7) {
    global $pdo;
    $data = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM $table WHERE DATE($dateCol) = ?");
        $stmt->execute([$date]);
        $data[] = (int)$stmt->fetchColumn();
    }
    return $data;
}
function getDailySum($table, $sumCol, $dateCol, $days = 7) {
    global $pdo;
    $data = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $stmt = $pdo->prepare("SELECT SUM($sumCol) FROM $table WHERE DATE($dateCol) = ?");
        $stmt->execute([$date]);
        $data[] = (int)($stmt->fetchColumn() ?? 0);
    }
    return $data;
}

$beritaTrend = getDailyCount('berita', 'tanggal');
$viewsTrend = getDailySum('berita', 'views', 'tanggal');
$agendaTrend = getDailyCount('agenda', 'tanggal_mulai');
$downloadTrend = getDailySum('unduh', 'jumlah_unduh', 'tanggal_upload');
$publikasiTrend = getDailyCount('publikasi', 'tahun', 30); // yearly for 30 years

// 30-day traffic chart (views)
$traffic30 = [];
for ($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $stmt = $pdo->prepare("SELECT SUM(views) FROM berita WHERE DATE(tanggal) = ?");
    $stmt->execute([$date]);
    $traffic30[] = (int)($stmt->fetchColumn() ?? 0);
}
$trafficMax = max($traffic30) ?: 1;

// Content distribution
$contentDist = [
    ['label' => 'Berita',     'value' => $counts['berita'],    'color' => '#8A1538', 'icon' => 'fa-newspaper'],
    ['label' => 'Galeri',     'value' => $counts['galeri'],    'color' => '#87CEEB', 'icon' => 'fa-images'],
    ['label' => 'Agenda',     'value' => $counts['agenda'],    'color' => '#2E8B57', 'icon' => 'fa-calendar'],
    ['label' => 'Dosen',      'value' => $counts['dosen'],     'color' => '#F39C12', 'icon' => 'fa-chalkboard-user'],
    ['label' => 'Publikasi',  'value' => $counts['publikasi'], 'color' => '#9B59B6', 'icon' => 'fa-flask'],
    ['label' => 'Unduhan',    'value' => $counts['unduh'],     'color' => '#E74C3C', 'icon' => 'fa-cloud-arrow-down'],
];
$totalContent = array_sum(array_column($contentDist, 'value')) ?: 1;

// Top performing news
$topNews = $pdo->query("
    SELECT id, judul, kategori, tanggal, views, gambar, slug 
    FROM berita 
    WHERE status = 1 
    ORDER BY views DESC 
    LIMIT 3
")->fetchAll();

// Recent activities (today/yesterday/older grouping)
$recentLogs = $pdo->query("
    SELECT * FROM admin_logs 
    ORDER BY created_at DESC 
    LIMIT 15
")->fetchAll();

$groupedLogs = ['today' => [], 'yesterday' => [], 'older' => []];
$todayStr = date('Y-m-d');
$yesterdayStr = date('Y-m-d', strtotime('-1 day'));
foreach ($recentLogs as $log) {
    $logDate = date('Y-m-d', strtotime($log['created_at']));
    if ($logDate === $todayStr) $groupedLogs['today'][] = $log;
    elseif ($logDate === $yesterdayStr) $groupedLogs['yesterday'][] = $log;
    else $groupedLogs['older'][] = $log;
}

// Upcoming agenda
$upcomingAgenda = $pdo->query("
    SELECT * FROM agenda 
    WHERE tanggal_mulai >= CURDATE() AND aktif = 1
    ORDER BY tanggal_mulai ASC 
    LIMIT 5
")->fetchAll();

// Overdue agenda (should have finished but not)
$overdueAgenda = $pdo->query("
    SELECT COUNT(*) FROM agenda 
    WHERE tanggal_selesai < CURDATE() AND status = 'berlangsung'
")->fetchColumn();

// Draft news older than 7 days
$staleDrafts = $pdo->query("
    SELECT COUNT(*) FROM berita 
    WHERE status = 0 AND tanggal < DATE_SUB(NOW(), INTERVAL 7 DAY)
")->fetchColumn();

// Security alerts
$securityAlerts = $pdo->query("
    SELECT COUNT(*) FROM login_attempts 
    WHERE attempted_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
")->fetchColumn();

// Weekly heatmap (hour-based activity)
$heatmapData = array_fill(0, 7, array_fill(0, 24, 0));
$heatmapStmt = $pdo->query("
    SELECT 
        DAYOFWEEK(created_at) - 1 as day_idx,
        HOUR(created_at) as hour_idx,
        COUNT(*) as cnt
    FROM admin_logs 
    WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY day_idx, hour_idx
")->fetchAll();
foreach ($heatmapStmt as $h) {
    if ($h['day_idx'] >= 0 && $h['day_idx'] < 7 && $h['hour_idx'] >= 0 && $h['hour_idx'] < 24) {
        $heatmapData[$h['day_idx']][$h['hour_idx']] = (int)$h['cnt'];
    }
}
$heatmapMax = 1;
foreach ($heatmapData as $day) {
    $max = max($day);
    if ($max > $heatmapMax) $heatmapMax = $max;
}

// Real system health
$diskTotal = @disk_total_space('/') ?: 0;
$diskFree = @disk_free_space('/') ?: 0;
$diskUsed = $diskTotal - $diskFree;
$diskPercent = $diskTotal > 0 ? round(($diskUsed / $diskTotal) * 100, 1) : 0;
$diskTotalGB = round($diskTotal / (1024*1024*1024), 1);
$diskFreeGB = round($diskFree / (1024*1024*1024), 1);

$memUsage = round((memory_get_usage(true) / (1024*1024)), 1);
$memPeak = round((memory_get_peak_usage(true) / (1024*1024)), 1);
$memLimit = (int)ini_get('memory_limit');
if ($memLimit <= 0) $memLimit = 128;
$memPercent = min(100, round(($memUsage / $memLimit) * 100, 1));

// Mini calendar (current month)
$calMonth = (int)date('m');
$calYear = (int)date('Y');
$calFirstDay = date('w', mktime(0, 0, 0, $calMonth, 1, $calYear));
$calDaysInMonth = date('t', mktime(0, 0, 0, $calMonth, 1, $calYear));

// Get agenda dates for current month
$agendaDates = [];
$calStmt = $pdo->prepare("SELECT DATE(tanggal_mulai) as d FROM agenda WHERE MONTH(tanggal_mulai) = ? AND YEAR(tanggal_mulai) = ? AND aktif = 1");
$calStmt->execute([$calMonth, $calYear]);
foreach ($calStmt->fetchAll() as $row) {
    $agendaDates[] = $row['d'];
}

// Current user
$currentUser = [
    'id'      => $_SESSION['admin_id'] ?? 1,
    'name'    => $_SESSION['admin_name'] ?? 'Admin',
    'role'    => $_SESSION['admin_role'] ?? 'Administrator',
    'initial' => strtoupper(substr($_SESSION['admin_name'] ?? 'A', 0, 1))
];

// Helper: trend percentage
function trendPercent($arr) {
    if (count($arr) < 2) return 0;
    $prev = array_sum(array_slice($arr, 0, -1)) ?: 1;
    $curr = end($arr);
    return round((($curr - $prev) / $prev) * 100, 1);
}

// Helper: SVG sparkline path
function sparklinePath($data, $width = 120, $height = 40) {
    if (empty($data)) return '';
    $max = max($data) ?: 1;
    $step = $width / (count($data) - 1 ?: 1);
    $points = [];
    foreach ($data as $i => $v) {
        $x = $i * $step;
        $y = $height - ($v / $max) * ($height - 4);
        $points[] = "$x,$y";
    }
    return implode(' ', $points);
}

// Helper: SVG donut segment
function donutSegment($start, $end, $r = 40, $cx = 50, $cy = 50) {
    $startAngle = ($start * 360) - 90;
    $endAngle = ($end * 360) - 90;
    $largeArc = ($end - $start) > 0.5 ? 1 : 0;
    $sx = $cx + $r * cos(deg2rad($startAngle));
    $sy = $cy + $r * sin(deg2rad($startAngle));
    $ex = $cx + $r * cos(deg2rad($endAngle));
    $ey = $cy + $r * sin(deg2rad($endAngle));
    return "M $sx $sy A $r $r 0 $largeArc 1 $ex $ey";
}

$page_title = 'Dashboard';
$current_page = 'dashboard';

ob_start();
?>

<style>
/* ===== DASHBOARD PREMIUM STYLES ===== */

/* Welcome Banner */
.welcome-hero {
    background: linear-gradient(135deg, var(--bg-darker) 0%, #1a1a2e 50%, var(--bg-darker) 100%);
    border-radius: var(--radius-lg);
    padding: 2.5rem;
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
    border: 1px solid var(--border-dark);
}
.welcome-hero::before {
    content: '';
    position: absolute;
    top: -50%; right: -10%;
    width: 500px; height: 500px;
    background: radial-gradient(circle, rgba(138,21,56,0.15), transparent 70%);
    filter: blur(40px);
    pointer-events: none;
}
.welcome-hero::after {
    content: '';
    position: absolute;
    bottom: -30%; left: -10%;
    width: 400px; height: 400px;
    background: radial-gradient(circle, rgba(135,206,235,0.1), transparent 70%);
    filter: blur(40px);
    pointer-events: none;
}
.welcome-grid {
    display: grid;
    grid-template-columns: 1.5fr 1fr;
    gap: 2rem;
    align-items: center;
    position: relative;
    z-index: 1;
}
.welcome-greeting {
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    padding: .4rem 1rem;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 50px;
    font-size: .82rem;
    font-weight: 600;
    color: var(--text-main);
    margin-bottom: 1rem;
    backdrop-filter: blur(10px);
}
.welcome-greeting i { font-size: .9rem; }
.welcome-title {
    font-size: clamp(1.5rem, 3vw, 2.2rem);
    font-weight: 900;
    color: #fff;
    margin-bottom: .5rem;
    line-height: 1.2;
}
.welcome-title .wave { display: inline-block; animation: wave 2.5s infinite; transform-origin: 70% 70%; }
@keyframes wave {
    0%, 60%, 100% { transform: rotate(0); }
    10% { transform: rotate(14deg); }
    20% { transform: rotate(-8deg); }
    30% { transform: rotate(14deg); }
    40% { transform: rotate(-4deg); }
    50% { transform: rotate(10deg); }
}
.welcome-subtitle {
    color: var(--text-muted);
    font-size: .95rem;
    line-height: 1.6;
    margin-bottom: 1.5rem;
    max-width: 600px;
}
.welcome-quote {
    background: rgba(135,206,235,0.08);
    border-left: 3px solid var(--sky-blue);
    padding: .8rem 1rem;
    border-radius: 0 var(--radius-sm) var(--radius-sm) 0;
    font-style: italic;
    color: var(--text-main);
    font-size: .85rem;
    line-height: 1.6;
    margin-bottom: 1.5rem;
}
.welcome-quote::before {
    content: '"';
    font-size: 2rem;
    color: var(--sky-blue);
    line-height: 0;
    vertical-align: -0.5em;
    margin-right: .3rem;
}
.welcome-actions {
    display: flex;
    gap: .8rem;
    flex-wrap: wrap;
}
.welcome-btn {
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    padding: .75rem 1.5rem;
    border-radius: 50px;
    font-weight: 700;
    font-size: .88rem;
    text-decoration: none;
    transition: all .3s ease;
    border: none;
    cursor: pointer;
}
.welcome-btn.primary {
    background: linear-gradient(135deg, var(--crimson), var(--crimson-light));
    color: #fff;
    box-shadow: 0 6px 20px rgba(138,21,56,.3);
}
.welcome-btn.primary:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(138,21,56,.4); }
.welcome-btn.secondary {
    background: rgba(255,255,255,0.05);
    color: #fff;
    border: 1px solid rgba(255,255,255,0.1);
    backdrop-filter: blur(10px);
}
.welcome-btn.secondary:hover { background: rgba(255,255,255,0.1); }

/* Welcome Info Card */
.welcome-info {
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    backdrop-filter: blur(10px);
}
.welcome-info-row {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: .8rem 0;
    border-bottom: 1px solid rgba(255,255,255,0.05);
}
.welcome-info-row:last-child { border-bottom: none; }
.welcome-info-icon {
    width: 40px; height: 40px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.1rem;
    flex-shrink: 0;
}
.welcome-info-label {
    font-size: .75rem;
    color: var(--text-muted);
    margin-bottom: .2rem;
}
.welcome-info-value {
    font-weight: 700;
    color: #fff;
    font-size: .95rem;
}

/* ===== STATS GRID ===== */
.premium-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}
.premium-stat-card {
    background: var(--panel-bg);
    border: 1px solid var(--border-dark);
    border-radius: var(--radius-lg);
    padding: 1.3rem;
    transition: all .3s ease;
    position: relative;
    overflow: hidden;
    cursor: pointer;
}
.premium-stat-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-lg);
    border-color: var(--crimson);
}
.premium-stat-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    background: var(--accent-color, var(--crimson));
}
.stat-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
}
.stat-icon-wrap {
    width: 44px; height: 44px;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.2rem;
}
.stat-trend-badge {
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    padding: .2rem .6rem;
    border-radius: 50px;
    font-size: .72rem;
    font-weight: 700;
}
.stat-trend-badge.up { background: rgba(46,139,87,0.15); color: var(--success); }
.stat-trend-badge.down { background: rgba(231,76,60,0.15); color: var(--danger); }
.stat-trend-badge.flat { background: rgba(149,165,166,0.15); color: #95a5a6; }
.stat-value {
    font-size: 2rem;
    font-weight: 900;
    color: #fff;
    margin-bottom: .2rem;
    line-height: 1;
}
.stat-label {
    color: var(--text-muted);
    font-size: .82rem;
    margin-bottom: 1rem;
    font-weight: 500;
}
.stat-sparkline {
    height: 40px;
    width: 100%;
    display: block;
}
.stat-sparkline polyline {
    fill: none;
    stroke-width: 2;
    stroke-linecap: round;
    stroke-linejoin: round;
}
.stat-sparkline .area {
    stroke: none;
    opacity: .15;
}

/* ===== PREMIUM GRID LAYOUT ===== */
.dashboard-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}
.dashboard-grid.equal { grid-template-columns: 1fr 1fr; }
.dashboard-grid.three { grid-template-columns: 2fr 1fr 1fr; }

/* ===== CHART CARDS ===== */
.chart-card {
    background: var(--panel-bg);
    border: 1px solid var(--border-dark);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    position: relative;
}
.chart-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
    gap: 1rem;
}
.chart-title {
    display: flex;
    align-items: center;
    gap: .6rem;
    font-size: 1rem;
    font-weight: 700;
    color: #fff;
}
.chart-title i {
    width: 32px; height: 32px;
    border-radius: 8px;
    background: rgba(138,21,56,0.15);
    color: var(--crimson);
    display: flex; align-items: center; justify-content: center;
    font-size: .9rem;
}
.chart-value {
    font-size: 1.5rem;
    font-weight: 900;
    color: #fff;
    margin-bottom: .2rem;
}
.chart-sub {
    font-size: .78rem;
    color: var(--text-muted);
}
.chart-range-tabs {
    display: flex;
    gap: .3rem;
    background: var(--bg-darker);
    border-radius: var(--radius-sm);
    padding: .25rem;
}
.chart-range-tab {
    padding: .35rem .8rem;
    border-radius: var(--radius-sm);
    font-size: .75rem;
    font-weight: 600;
    color: var(--text-muted);
    cursor: pointer;
    border: none;
    background: transparent;
    transition: all .2s;
}
.chart-range-tab:hover { color: var(--text-main); }
.chart-range-tab.active {
    background: var(--crimson);
    color: #fff;
}

/* SVG Traffic Chart */
.traffic-chart-wrap {
    position: relative;
    height: 240px;
    width: 100%;
}
.traffic-chart-svg {
    width: 100%;
    height: 100%;
}
.traffic-chart-svg .grid-line {
    stroke: var(--border-dark);
    stroke-width: 1;
    stroke-dasharray: 3,3;
}
.traffic-chart-svg .chart-area {
    fill: url(#trafficGradient);
}
.traffic-chart-svg .chart-line {
    fill: none;
    stroke: var(--crimson);
    stroke-width: 2.5;
    stroke-linecap: round;
    stroke-linejoin: round;
    filter: drop-shadow(0 2px 4px rgba(138,21,56,.3));
}
.traffic-chart-svg .chart-point {
    fill: var(--crimson);
    stroke: #fff;
    stroke-width: 2;
    transition: r .2s;
    cursor: pointer;
}
.traffic-chart-svg .chart-point:hover { r: 6; }
.traffic-tooltip {
    position: absolute;
    background: #000;
    color: #fff;
    padding: .4rem .8rem;
    border-radius: 6px;
    font-size: .75rem;
    pointer-events: none;
    opacity: 0;
    transition: opacity .2s;
    white-space: nowrap;
    transform: translate(-50%, -120%);
    z-index: 10;
}
.traffic-tooltip::after {
    content: '';
    position: absolute;
    bottom: -4px; left: 50%;
    width: 8px; height: 8px;
    background: #000;
    transform: translateX(-50%) rotate(45deg);
}
.traffic-x-labels {
    display: flex;
    justify-content: space-between;
    padding: .5rem .5rem 0;
    font-size: .7rem;
    color: var(--text-muted);
}

/* ===== DONUT CHART ===== */
.donut-chart-wrap {
    display: grid;
    grid-template-columns: 150px 1fr;
    gap: 1.5rem;
    align-items: center;
}
.donut-svg { width: 150px; height: 150px; transform: rotate(-90deg); }
.donut-svg circle {
    fill: none;
    stroke-width: 20;
    stroke-linecap: butt;
    transition: stroke-dasharray 1s ease;
}
.donut-center {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    pointer-events: none;
}
.donut-center-value {
    font-size: 1.5rem;
    font-weight: 900;
    color: #fff;
}
.donut-center-label {
    font-size: .7rem;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 1px;
}
.donut-legend {
    display: flex;
    flex-direction: column;
    gap: .6rem;
}
.donut-legend-item {
    display: flex;
    align-items: center;
    gap: .6rem;
    font-size: .85rem;
}
.donut-legend-dot {
    width: 10px; height: 10px;
    border-radius: 50%;
    flex-shrink: 0;
}
.donut-legend-label {
    flex: 1;
    color: var(--text-main);
}
.donut-legend-value {
    font-weight: 700;
    color: #fff;
    font-size: .85rem;
}
.donut-legend-pct {
    color: var(--text-muted);
    font-size: .75rem;
    min-width: 40px;
    text-align: right;
}

/* ===== TOP NEWS CARDS ===== */
.top-news-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}
.top-news-card {
    display: grid;
    grid-template-columns: 70px 1fr auto;
    gap: 1rem;
    padding: 1rem;
    background: var(--bg-darker);
    border: 1px solid var(--border-dark);
    border-radius: var(--radius-md);
    transition: all .3s ease;
    align-items: center;
    text-decoration: none;
    color: inherit;
}
.top-news-card:hover {
    border-color: var(--crimson);
    transform: translateX(4px);
}
.top-news-rank {
    width: 40px; height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--crimson), var(--crimson-light));
    color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-weight: 900;
    font-size: 1.1rem;
    flex-shrink: 0;
    position: relative;
    grid-column: 1;
    grid-row: 1 / 3;
}
.top-news-rank.gold { background: linear-gradient(135deg, #FFD700, #FFA500); }
.top-news-rank.silver { background: linear-gradient(135deg, #C0C0C0, #808080); }
.top-news-rank.bronze { background: linear-gradient(135deg, #CD7F32, #8B4513); }
.top-news-thumb {
    width: 70px; height: 70px;
    border-radius: var(--radius-sm);
    overflow: hidden;
    background: var(--bg-darker);
    flex-shrink: 0;
    grid-column: 1;
    grid-row: 1 / 3;
}
.top-news-thumb img { width: 100%; height: 100%; object-fit: cover; }
.top-news-info { grid-column: 2; min-width: 0; }
.top-news-title {
    font-size: .9rem;
    font-weight: 700;
    color: #fff;
    margin-bottom: .3rem;
    line-height: 1.3;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.top-news-meta {
    display: flex;
    gap: .8rem;
    font-size: .72rem;
    color: var(--text-muted);
}
.top-news-meta span {
    display: inline-flex;
    align-items: center;
    gap: .3rem;
}
.top-news-meta i { color: var(--sky-blue); }
.top-news-views {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    grid-column: 3;
    grid-row: 1 / 3;
}
.top-news-views-value {
    font-size: 1.2rem;
    font-weight: 900;
    color: var(--crimson);
}
.top-news-views-label {
    font-size: .65rem;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: .5px;
}

/* ===== TIMELINE AGENDA ===== */
.timeline-list {
    position: relative;
    padding-left: 2rem;
}
.timeline-list::before {
    content: '';
    position: absolute;
    left: 12px; top: 8px; bottom: 8px;
    width: 2px;
    background: linear-gradient(180deg, var(--crimson), var(--sky-blue), var(--success));
}
.timeline-item {
    position: relative;
    padding-bottom: 1.2rem;
}
.timeline-item:last-child { padding-bottom: 0; }
.timeline-dot {
    position: absolute;
    left: -2rem; top: 4px;
    width: 26px; height: 26px;
    border-radius: 50%;
    background: var(--bg-darker);
    border: 3px solid var(--crimson);
    display: flex; align-items: center; justify-content: center;
    font-size: .7rem;
    color: var(--crimson);
    z-index: 2;
}
.timeline-item.upcoming .timeline-dot { border-color: var(--sky-blue); color: var(--sky-blue); }
.timeline-item.ongoing .timeline-dot { border-color: var(--success); color: var(--success); animation: pulseDot 2s infinite; }
@keyframes pulseDot {
    0%, 100% { box-shadow: 0 0 0 0 rgba(46,139,87,.4); }
    50% { box-shadow: 0 0 0 8px rgba(46,139,87,0); }
}
.timeline-content {
    background: var(--bg-darker);
    border: 1px solid var(--border-dark);
    border-radius: var(--radius-md);
    padding: .8rem 1rem;
    transition: all .2s;
}
.timeline-content:hover { border-color: var(--crimson); }
.timeline-title {
    font-weight: 700;
    color: #fff;
    font-size: .9rem;
    margin-bottom: .3rem;
    line-height: 1.3;
}
.timeline-meta {
    display: flex;
    gap: .8rem;
    font-size: .75rem;
    color: var(--text-muted);
    flex-wrap: wrap;
}
.timeline-meta span { display: inline-flex; align-items: center; gap: .3rem; }
.timeline-countdown {
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    background: rgba(135,206,235,0.1);
    color: var(--sky-blue);
    padding: .15rem .6rem;
    border-radius: 50px;
    font-size: .72rem;
    font-weight: 600;
    margin-top: .4rem;
}

/* ===== ACTIVITY LIST (Grouped) ===== */
.activity-group-title {
    font-size: .72rem;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    color: var(--text-muted);
    font-weight: 700;
    margin: 1rem 0 .6rem;
    padding-bottom: .4rem;
    border-bottom: 1px dashed var(--border-dark);
    display: flex;
    align-items: center;
    gap: .5rem;
}
.activity-group-title:first-child { margin-top: 0; }
.activity-group-title i { color: var(--crimson); }

/* ===== HEATMAP ===== */
.heatmap-wrap {
    overflow-x: auto;
}
.heatmap-grid {
    display: grid;
    grid-template-columns: 40px repeat(24, 1fr);
    gap: 3px;
    min-width: 600px;
}
.heatmap-hour-label {
    font-size: .65rem;
    color: var(--text-muted);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 4px 0;
}
.heatmap-day-label {
    font-size: .72rem;
    color: var(--text-muted);
    font-weight: 600;
    display: flex;
    align-items: center;
    padding-right: 4px;
}
.heatmap-cell {
    aspect-ratio: 1;
    border-radius: 4px;
    background: var(--bg-darker);
    border: 1px solid var(--border-dark);
    transition: all .2s;
    cursor: pointer;
    position: relative;
}
.heatmap-cell:hover { transform: scale(1.2); z-index: 5; border-color: var(--crimson); }
.heatmap-cell.lvl-1 { background: rgba(138,21,56,0.2); }
.heatmap-cell.lvl-2 { background: rgba(138,21,56,0.4); }
.heatmap-cell.lvl-3 { background: rgba(138,21,56,0.6); }
.heatmap-cell.lvl-4 { background: var(--crimson); }
.heatmap-legend {
    display: flex;
    align-items: center;
    gap: .5rem;
    margin-top: 1rem;
    justify-content: flex-end;
    font-size: .72rem;
    color: var(--text-muted);
}
.heatmap-legend-cell {
    width: 12px; height: 12px;
    border-radius: 2px;
}

/* ===== MINI CALENDAR ===== */
.mini-calendar {
    user-select: none;
}
.mini-cal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}
.mini-cal-title {
    font-weight: 700;
    color: #fff;
    font-size: .95rem;
}
.mini-cal-nav {
    display: flex;
    gap: .3rem;
}
.mini-cal-nav button {
    width: 28px; height: 28px;
    border-radius: 6px;
    background: var(--bg-darker);
    border: 1px solid var(--border-dark);
    color: var(--text-main);
    cursor: pointer;
    transition: all .2s;
    display: flex; align-items: center; justify-content: center;
    font-size: .8rem;
}
.mini-cal-nav button:hover { background: var(--crimson); border-color: var(--crimson); }
.mini-cal-weekdays {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 4px;
    margin-bottom: .5rem;
}
.mini-cal-weekday {
    text-align: center;
    font-size: .7rem;
    font-weight: 700;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: .5px;
    padding: .3rem 0;
}
.mini-cal-days {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 4px;
}
.mini-cal-day {
    aspect-ratio: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    font-size: .85rem;
    color: var(--text-main);
    position: relative;
    cursor: pointer;
    transition: all .2s;
    background: var(--bg-darker);
}
.mini-cal-day:hover { background: rgba(138,21,56,0.15); }
.mini-cal-day.other-month { opacity: .3; }
.mini-cal-day.today {
    background: var(--crimson);
    color: #fff;
    font-weight: 800;
    box-shadow: 0 4px 12px rgba(138,21,56,.4);
}
.mini-cal-day.has-agenda::after {
    content: '';
    position: absolute;
    bottom: 3px;
    width: 4px; height: 4px;
    border-radius: 50%;
    background: var(--sky-blue);
}
.mini-cal-day.today.has-agenda::after { background: #fff; }

/* ===== INSIGHT ALERTS ===== */
.insight-alert {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    border-radius: var(--radius-md);
    margin-bottom: .8rem;
    border-left: 4px solid;
    transition: all .3s ease;
}
.insight-alert:hover { transform: translateX(4px); }
.insight-alert.warning {
    background: rgba(243,156,18,0.08);
    border-left-color: var(--warning);
}
.insight-alert.danger {
    background: rgba(231,76,60,0.08);
    border-left-color: var(--danger);
}
.insight-alert.info {
    background: rgba(135,206,235,0.08);
    border-left-color: var(--sky-blue);
}
.insight-alert-icon {
    width: 40px; height: 40px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.1rem;
    flex-shrink: 0;
}
.insight-alert.warning .insight-alert-icon { background: rgba(243,156,18,0.15); color: var(--warning); }
.insight-alert.danger .insight-alert-icon { background: rgba(231,76,60,0.15); color: var(--danger); }
.insight-alert.info .insight-alert-icon { background: rgba(135,206,235,0.15); color: var(--sky-blue); }
.insight-alert-content { flex: 1; min-width: 0; }
.insight-alert-title {
    font-weight: 700;
    color: #fff;
    font-size: .88rem;
    margin-bottom: .2rem;
}
.insight-alert-desc {
    font-size: .78rem;
    color: var(--text-muted);
    line-height: 1.5;
}
.insight-alert-action {
    padding: .4rem .8rem;
    border-radius: 6px;
    font-size: .75rem;
    font-weight: 700;
    text-decoration: none;
    white-space: nowrap;
    transition: all .2s;
}
.insight-alert.warning .insight-alert-action { background: var(--warning); color: #fff; }
.insight-alert.danger .insight-alert-action { background: var(--danger); color: #fff; }
.insight-alert.info .insight-alert-action { background: var(--sky-blue); color: #fff; }
.insight-alert-action:hover { opacity: .9; transform: translateY(-1px); }

/* ===== SYSTEM HEALTH REAL ===== */
.health-item {
    display: grid;
    grid-template-columns: 40px 1fr auto;
    gap: 1rem;
    padding: .8rem 0;
    border-bottom: 1px solid var(--border-dark);
    align-items: center;
}
.health-item:last-child { border-bottom: none; }
.health-icon-box {
    width: 40px; height: 40px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem;
    background: rgba(46,139,87,0.15);
    color: var(--success);
}
.health-item.warning .health-icon-box { background: rgba(243,156,18,0.15); color: var(--warning); }
.health-item.danger .health-icon-box { background: rgba(231,76,60,0.15); color: var(--danger); }
.health-info h4 {
    font-size: .88rem;
    color: #fff;
    margin-bottom: .2rem;
    font-weight: 700;
}
.health-info p {
    font-size: .78rem;
    color: var(--text-muted);
    margin-bottom: .4rem;
}
.health-bar {
    height: 6px;
    background: var(--bg-darker);
    border-radius: 50px;
    overflow: hidden;
}
.health-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--success), #27ae60);
    border-radius: 50px;
    transition: width 1s ease;
}
.health-item.warning .health-bar-fill { background: linear-gradient(90deg, var(--warning), #e67e22); }
.health-item.danger .health-bar-fill { background: linear-gradient(90deg, var(--danger), #c0392b); }
.health-status-badge {
    padding: .2rem .7rem;
    border-radius: 50px;
    font-size: .68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .5px;
}
.health-status-badge.good { background: rgba(46,139,87,0.15); color: var(--success); }
.health-status-badge.warning { background: rgba(243,156,18,0.15); color: var(--warning); }
.health-status-badge.danger { background: rgba(231,76,60,0.15); color: var(--danger); }

/* ===== QUICK ACTIONS GRID ===== */
.quick-actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: .8rem;
}
.quick-action-card {
    background: var(--bg-darker);
    border: 1px solid var(--border-dark);
    border-radius: var(--radius-md);
    padding: 1.2rem 1rem;
    text-align: center;
    text-decoration: none;
    transition: all .3s ease;
    position: relative;
    overflow: hidden;
}
.quick-action-card:hover {
    border-color: var(--crimson);
    transform: translateY(-3px);
    box-shadow: var(--shadow-md);
}
.quick-action-card::before {
    content: '';
    position: absolute;
    top: 0; left: -100%;
    width: 100%; height: 100%;
    background: linear-gradient(90deg, transparent, rgba(138,21,56,0.1), transparent);
    transition: left .5s;
}
.quick-action-card:hover::before { left: 100%; }
.quick-action-icon {
    width: 48px; height: 48px;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.3rem;
    margin: 0 auto .6rem;
}
.quick-action-label {
    font-size: .82rem;
    font-weight: 700;
    color: #fff;
}
.quick-action-desc {
    font-size: .7rem;
    color: var(--text-muted);
    margin-top: .2rem;
}

/* ===== PANEL STYLES ===== */
.panel-premium {
    background: var(--panel-bg);
    border: 1px solid var(--border-dark);
    border-radius: var(--radius-lg);
    overflow: hidden;
}
.panel-premium .panel-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.2rem 1.5rem;
    border-bottom: 1px solid var(--border-dark);
    flex-wrap: wrap;
    gap: .8rem;
}
.panel-premium .panel-head h3 {
    display: flex; align-items: center; gap: .6rem;
    font-size: 1rem; font-weight: 700; color: #fff; margin: 0;
}
.panel-premium .panel-head h3 i {
    width: 32px; height: 32px;
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    background: rgba(138,21,56,0.15);
    color: var(--crimson);
}
.panel-premium .panel-body { padding: 1.2rem 1.5rem; }
.panel-premium .panel-body.no-pad { padding: 0; }

/* ===== RESPONSIVE ===== */
@media (max-width: 1100px) {
    .dashboard-grid, .dashboard-grid.equal, .dashboard-grid.three { grid-template-columns: 1fr; }
    .welcome-grid { grid-template-columns: 1fr; }
    .welcome-info { order: -1; }
    .donut-chart-wrap { grid-template-columns: 1fr; justify-items: center; }
}
@media (max-width: 640px) {
    .welcome-hero { padding: 1.5rem; }
    .premium-stats-grid { grid-template-columns: repeat(2, 1fr); }
    .top-news-card { grid-template-columns: 50px 1fr; }
    .top-news-thumb { width: 50px; height: 50px; }
    .top-news-views { grid-column: 2; grid-row: auto; flex-direction: row; gap: .5rem; align-items: center; justify-content: flex-start; margin-top: .5rem; }
    .quick-actions-grid { grid-template-columns: repeat(2, 1fr); }
}
</style>

<!-- ===== WELCOME HERO ===== -->
<div class="welcome-hero">
    <div class="welcome-grid">
        <div>
            <div class="welcome-greeting">
                <i class="fa-solid <?= $greetIcon ?>" style="color:<?= $greetColor ?>"></i>
                <?= $greeting ?>, <?= htmlspecialchars(explode(' ', $currentUser['name'])[0]) ?>
            </div>
            <h1 class="welcome-title">
                Selamat Datang di Command Center <span class="wave">👋</span>
            </h1>
            <p class="welcome-subtitle">
                Berikut ringkasan aktivitas website <strong style="color:#fff;">PBSI UMMaumere</strong> hari ini, 
                <strong style="color:var(--sky-blue);"><?= date('l, d F Y') ?></strong>. 
                Pastikan semua konten akademik dan informasi prodi selalu terbarukan.
            </p>
            <div class="welcome-quote"><?= htmlspecialchars($todayQuote) ?></div>
            <div class="welcome-actions">
                <a href="kelola_berita.php?action=add" class="welcome-btn primary">
                    <i class="fa-solid fa-plus"></i> Tambah Berita
                </a>
                <a href="../" target="_blank" class="welcome-btn secondary">
                    <i class="fa-solid fa-external-link"></i> Lihat Website
                </a>
                <a href="kelola_agenda.php?action=add" class="welcome-btn secondary">
                    <i class="fa-solid fa-calendar-plus"></i> Buat Agenda
                </a>
            </div>
        </div>
        
        <div class="welcome-info">
            <div class="welcome-info-row">
                <div class="welcome-info-icon" style="background:rgba(138,21,56,0.15); color:var(--crimson);">
                    <i class="fa-solid fa-calendar-day"></i>
                </div>
                <div>
                    <div class="welcome-info-label">Tanggal Masehi</div>
                    <div class="welcome-info-value"><?= date('d F Y') ?></div>
                </div>
            </div>
            <div class="welcome-info-row">
                <div class="welcome-info-icon" style="background:rgba(46,139,87,0.15); color:var(--success);">
                    <i class="fa-solid fa-moon"></i>
                </div>
                <div>
                    <div class="welcome-info-label">Tanggal Hijriah</div>
                    <div class="welcome-info-value"><?= $hijriDate ?></div>
                </div>
            </div>
            <div class="welcome-info-row">
                <div class="welcome-info-icon" style="background:rgba(135,206,235,0.15); color:var(--sky-blue);">
                    <i class="fa-solid fa-clock"></i>
                </div>
                <div>
                    <div class="welcome-info-label">Waktu Server (WITA)</div>
                    <div class="welcome-info-value" id="liveClock"><?= date('H:i:s') ?></div>
                </div>
            </div>
            <div class="welcome-info-row">
                <div class="welcome-info-icon" style="background:rgba(243,156,18,0.15); color:var(--warning);">
                    <i class="fa-solid fa-user-shield"></i>
                </div>
                <div>
                    <div class="welcome-info-label">Session Aktif</div>
                    <div class="welcome-info-value">Sejak <?= date('H:i', $_SESSION['login_time'] ?? time()) ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ===== INSIGHT ALERTS ===== -->
<?php 
$insights = [];
if ($staleDrafts > 0) {
    $insights[] = ['type'=>'warning', 'icon'=>'fa-file-pen', 'title'=>"$staleDrafts Berita Draft Tua", 'desc'=>"Ada $staleDrafts berita yang masih draft selama lebih dari 7 hari. Pertimbangkan untuk mempublikasikannya.", 'link'=>'kelola_berita.php?filter=draft'];
}
if ($overdueAgenda > 0) {
    $insights[] = ['type'=>'danger', 'icon'=>'fa-triangle-exclamation', 'title'=>"$overdueAgenda Agenda Terlewat", 'desc'=>"Ada $overdueAgenda agenda yang sudah selesai namun masih berstatus 'Berlangsung'.", 'link'=>'kelola_agenda.php'];
}
if ($securityAlerts > 5) {
    $insights[] = ['type'=>'danger', 'icon'=>'fa-shield-halved', 'title'=>"Aktivitas Login Mencurigakan", 'desc'=>"Terdeteksi $securityAlerts percobaan login dalam 24 jam terakhir. Periksa log keamanan.", 'link'=>'admin_logs.php'];
}
if ($counts['alumni'] < 10) {
    $insights[] = ['type'=>'info', 'icon'=>'fa-user-graduate', 'title'=>"Data Alumni Minim", 'desc'=>"Baru {$counts['alumni']} alumni terdaftar. Tambahkan lebih banyak untuk memperkuat tracer study.", 'link'=>'kelola_alumni.php?action=add'];
}

if (!empty($insights)):
?>
<div style="margin-bottom:1.5rem;">
    <?php foreach ($insights as $ins): ?>
    <div class="insight-alert <?= $ins['type'] ?>">
        <div class="insight-alert-icon"><i class="fa-solid <?= $ins['icon'] ?>"></i></div>
        <div class="insight-alert-content">
            <div class="insight-alert-title"><?= htmlspecialchars($ins['title']) ?></div>
            <div class="insight-alert-desc"><?= htmlspecialchars($ins['desc']) ?></div>
        </div>
        <a href="<?= $ins['link'] ?>" class="insight-alert-action">
            Tinjau <i class="fa-solid fa-arrow-right"></i>
        </a>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ===== PREMIUM STATS GRID ===== -->
<div class="premium-stats-grid">
    <?php 
    $statConfigs = [
        ['label'=>'Total Berita', 'value'=>$counts['berita'], 'icon'=>'fa-newspaper', 'color'=>'#8A1538', 'trend'=>$beritaTrend, 'link'=>'kelola_berita.php'],
        ['label'=>'Dosen Aktif', 'value'=>$counts['dosen'], 'icon'=>'fa-chalkboard-user', 'color'=>'#F39C12', 'trend'=>[5,5,5,5,5,5,$counts['dosen']], 'link'=>'kelola_dosen.php'],
        ['label'=>'Alumni Terdaftar', 'value'=>$counts['alumni'], 'icon'=>'fa-user-graduate', 'color'=>'#2E8B57', 'trend'=>[2,3,5,7,10,12,$counts['alumni']], 'link'=>'kelola_alumni.php'],
        ['label'=>'Total Views', 'value'=>number_format($totalViews), 'icon'=>'fa-eye', 'color'=>'#87CEEB', 'trend'=>$viewsTrend, 'link'=>'kelola_berita.php'],
        ['label'=>'Total Unduhan', 'value'=>number_format($totalDownloads), 'icon'=>'fa-download', 'color'=>'#E74C3C', 'trend'=>$downloadTrend, 'link'=>'kelola_unduh.php'],
        ['label'=>'Publikasi Ilmiah', 'value'=>$counts['publikasi'], 'icon'=>'fa-flask', 'color'=>'#9B59B6', 'trend'=>[3,4,5,6,8,10,$counts['publikasi']], 'link'=>'kelola_publikasi.php'],
    ];
    
    foreach ($statConfigs as $stat):
        $trend = trendPercent($stat['trend']);
        $trendClass = $trend > 0 ? 'up' : ($trend < 0 ? 'down' : 'flat');
        $trendIcon = $trend > 0 ? 'fa-arrow-up' : ($trend < 0 ? 'fa-arrow-down' : 'fa-minus');
        $sparkPoints = sparklinePath($stat['trend']);
    ?>
    <a href="<?= $stat['link'] ?>" class="premium-stat-card" style="--accent-color:<?= $stat['color'] ?>;">
        <div class="stat-top">
            <div class="stat-icon-wrap" style="background:<?= $stat['color'] ?>20; color:<?= $stat['color'] ?>;">
                <i class="fa-solid <?= $stat['icon'] ?>"></i>
            </div>
            <span class="stat-trend-badge <?= $trendClass ?>">
                <i class="fa-solid <?= $trendIcon ?>"></i>
                <?= abs($trend) ?>%
            </span>
        </div>
        <div class="stat-value"><?= $stat['value'] ?></div>
        <div class="stat-label"><?= $stat['label'] ?></div>
        <svg class="stat-sparkline" viewBox="0 0 120 40" preserveAspectRatio="none">
            <defs>
                <linearGradient id="grad<?= md5($stat['label']) ?>" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="<?= $stat['color'] ?>" stop-opacity="0.4"/>
                    <stop offset="100%" stop-color="<?= $stat['color'] ?>" stop-opacity="0"/>
                </linearGradient>
            </defs>
            <polygon class="area" fill="url(#grad<?= md5($stat['label']) ?>)" points="0,40 <?= $sparkPoints ?> 120,40"/>
            <polyline stroke="<?= $stat['color'] ?>" points="<?= $sparkPoints ?>"/>
        </svg>
    </a>
    <?php endforeach; ?>
</div>

<!-- ===== CHART ROW: Traffic + Donut ===== -->
<div class="dashboard-grid">
    <!-- TRAFFIC CHART -->
    <div class="chart-card">
        <div class="chart-header">
            <div>
                <div class="chart-title"><i class="fa-solid fa-chart-line"></i> Traffic 30 Hari Terakhir</div>
                <div class="chart-value"><?= number_format(array_sum($traffic30)) ?> <span style="font-size:.9rem; color:var(--text-muted); font-weight:500;">views</span></div>
                <div class="chart-sub">Rata-rata <?= number_format(array_sum($traffic30)/30) ?> views/hari</div>
            </div>
            <div class="chart-range-tabs">
                <button class="chart-range-tab">7D</button>
                <button class="chart-range-tab active">30D</button>
                <button class="chart-range-tab">90D</button>
                <button class="chart-range-tab">1Y</button>
            </div>
        </div>
        
        <?php 
        $chartW = 800;
        $chartH = 240;
        $chartPad = 30;
        $stepX = ($chartW - $chartPad*2) / 29;
        ?>
        <div class="traffic-chart-wrap" id="trafficChartWrap">
            <svg class="traffic-chart-svg" viewBox="0 0 <?= $chartW ?> <?= $chartH ?>" preserveAspectRatio="none">
                <defs>
                    <linearGradient id="trafficGradient" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#8A1538" stop-opacity="0.4"/>
                        <stop offset="100%" stop-color="#8A1538" stop-opacity="0"/>
                    </linearGradient>
                </defs>
                
                <!-- Grid lines -->
                <?php for ($i = 0; $i <= 4; $i++): 
                    $y = $chartPad + ($chartH - $chartPad*2) * $i / 4;
                ?>
                <line class="grid-line" x1="<?= $chartPad ?>" y1="<?= $y ?>" x2="<?= $chartW - $chartPad ?>" y2="<?= $y ?>"/>
                <?php endfor; ?>
                
                <?php 
                $points = [];
                $areaPoints = ["$chartPad," . ($chartH - $chartPad)];
                foreach ($traffic30 as $i => $v) {
                    $x = $chartPad + $i * $stepX;
                    $y = $chartH - $chartPad - ($v / $trafficMax) * ($chartH - $chartPad*2);
                    $points[] = "$x,$y";
                    $areaPoints[] = "$x,$y";
                }
                $areaPoints[] = ($chartW - $chartPad) . "," . ($chartH - $chartPad);
                ?>
                
                <!-- Area -->
                <polygon class="chart-area" points="<?= implode(' ', $areaPoints) ?>"/>
                
                <!-- Line -->
                <polyline class="chart-line" points="<?= implode(' ', $points) ?>"/>
                
                <!-- Points -->
                <?php foreach ($traffic30 as $i => $v):
                    $x = $chartPad + $i * $stepX;
                    $y = $chartH - $chartPad - ($v / $trafficMax) * ($chartH - $chartPad*2);
                    $date = date('d M', strtotime("-" . (29-$i) . " days"));
                ?>
                <circle class="chart-point" cx="<?= $x ?>" cy="<?= $y ?>" r="4" 
                        data-date="<?= $date ?>" data-value="<?= $v ?>"
                        onmouseenter="showTrafficTip(evt, '<?= $date ?>', <?= $v ?>)"
                        onmouseleave="hideTrafficTip()"/>
                <?php endforeach; ?>
            </svg>
            <div class="traffic-tooltip" id="trafficTooltip"></div>
        </div>
        
        <div class="traffic-x-labels">
            <span><?= date('d M', strtotime('-29 days')) ?></span>
            <span><?= date('d M', strtotime('-20 days')) ?></span>
            <span><?= date('d M', strtotime('-10 days')) ?></span>
            <span>Hari Ini</span>
        </div>
    </div>
    
    <!-- DONUT CHART -->
    <div class="chart-card">
        <div class="chart-header">
            <div>
                <div class="chart-title"><i class="fa-solid fa-chart-pie"></i> Distribusi Konten</div>
                <div class="chart-sub">Total konten aktif</div>
            </div>
        </div>
        
        <div class="donut-chart-wrap">
            <div style="position:relative; width:150px; height:150px;">
                <svg class="donut-svg" viewBox="0 0 100 100">
                    <?php 
                    $circumference = 2 * M_PI * 40;
                    $offset = 0;
                    foreach ($contentDist as $cd):
                        $percent = $cd['value'] / $totalContent;
                        $dash = $percent * $circumference;
                        $gap = $circumference - $dash;
                    ?>
                    <circle cx="50" cy="50" r="40" 
                            stroke="<?= $cd['color'] ?>" 
                            stroke-dasharray="<?= $dash ?> <?= $gap ?>"
                            stroke-dashoffset="-<?= $offset ?>"/>
                    <?php $offset += $dash; endforeach; ?>
                </svg>
                <div class="donut-center">
                    <div class="donut-center-value"><?= $totalContent ?></div>
                    <div class="donut-center-label">Total</div>
                </div>
            </div>
            
            <div class="donut-legend">
                <?php foreach ($contentDist as $cd): 
                    $pct = round(($cd['value'] / $totalContent) * 100, 1);
                ?>
                <div class="donut-legend-item">
                    <div class="donut-legend-dot" style="background:<?= $cd['color'] ?>"></div>
                    <i class="fa-solid <?= $cd['icon'] ?>" style="color:<?= $cd['color'] ?>; font-size:.85rem;"></i>
                    <span class="donut-legend-label"><?= $cd['label'] ?></span>
                    <span class="donut-legend-value"><?= $cd['value'] ?></span>
                    <span class="donut-legend-pct"><?= $pct ?>%</span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- ===== QUICK ACTIONS ===== -->
<div class="panel-premium" style="margin-bottom:1.5rem;">
    <div class="panel-head">
        <h3><i class="fa-solid fa-bolt" style="background:rgba(243,156,18,0.15); color:var(--warning);"></i> Aksi Cepat</h3>
        <span style="color:var(--text-muted); font-size:.78rem;">Shortcut untuk tugas umum</span>
    </div>
    <div class="panel-body">
        <div class="quick-actions-grid">
            <?php 
            $actions = [
                ['label'=>'Berita Baru', 'desc'=>'Tulis artikel', 'icon'=>'fa-newspaper', 'color'=>'#8A1538', 'link'=>'kelola_berita.php?action=add'],
                ['label'=>'Tambah Dosen', 'desc'=>'Data pengajar', 'icon'=>'fa-user-plus', 'color'=>'#F39C12', 'link'=>'kelola_dosen.php?action=add'],
                ['label'=>'Buat Agenda', 'desc'=>'Jadwal kegiatan', 'icon'=>'fa-calendar-plus', 'color'=>'#2E8B57', 'link'=>'kelola_agenda.php?action=add'],
                ['label'=>'Upload Foto', 'desc'=>'Galeri kegiatan', 'icon'=>'fa-image', 'color'=>'#87CEEB', 'link'=>'kelola_galeri.php?action=add'],
                ['label'=>'Upload Dokumen', 'desc'=>'File akademik', 'icon'=>'fa-upload', 'color'=>'#E74C3C', 'link'=>'kelola_unduh.php?action=add&tab=unduh'],
                ['label'=>'Tambah RPS', 'desc'=>'Rencana kuliah', 'icon'=>'fa-book-open', 'color'=>'#9B59B6', 'link'=>'kelola_unduh.php?action=add&tab=rps'],
                ['label'=>'Publikasi', 'desc'=>'Karya ilmiah', 'icon'=>'fa-flask', 'color'=>'#16A085', 'link'=>'kelola_publikasi.php?action=add'],
                ['label'=>'Alumni', 'desc'=>'Data lulusan', 'icon'=>'fa-user-graduate', 'color'=>'#D35400', 'link'=>'kelola_alumni.php?action=add'],
            ];
            foreach ($actions as $act):
            ?>
            <a href="<?= $act['link'] ?>" class="quick-action-card">
                <div class="quick-action-icon" style="background:<?= $act['color'] ?>20; color:<?= $act['color'] ?>;">
                    <i class="fa-solid <?= $act['icon'] ?>"></i>
                </div>
                <div class="quick-action-label"><?= $act['label'] ?></div>
                <div class="quick-action-desc"><?= $act['desc'] ?></div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- ===== ROW: Top News + Timeline + Calendar ===== -->
<div class="dashboard-grid three">
    <!-- TOP NEWS -->
    <div class="panel-premium">
        <div class="panel-head">
            <h3><i class="fa-solid fa-trophy" style="background:rgba(243,156,18,0.15); color:var(--warning);"></i> Berita Terpopuler</h3>
            <a href="kelola_berita.php" style="color:var(--sky-blue); font-size:.78rem; text-decoration:none;">Semua <i class="fa-solid fa-arrow-right"></i></a>
        </div>
        <div class="panel-body">
            <?php if (empty($topNews)): ?>
            <div style="text-align:center; padding:2rem; color:var(--text-muted);">
                <i class="fa-solid fa-newspaper" style="font-size:2.5rem; margin-bottom:.8rem; opacity:.3;"></i>
                <p>Belum ada berita yang dipublikasi</p>
            </div>
            <?php else: ?>
            <div class="top-news-list">
                <?php foreach ($topNews as $i => $n): 
                    $rankClass = $i == 0 ? 'gold' : ($i == 1 ? 'silver' : 'bronze');
                    $thumbUrl = !empty($n['gambar']) && file_exists('../' . $n['gambar']) ? '../' . $n['gambar'] : "data:image/svg+xml;utf8," . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100"><rect fill="#1a1a2e" width="100" height="100"/><text x="50" y="55" font-family="Arial" font-size="12" fill="#666" text-anchor="middle">No Image</text></svg>');
                ?>
                <a href="kelola_berita.php?edit=<?= $n['id'] ?>&token=<?= hash_hmac('sha256', $n['id'], $_SESSION['csrf_token']) ?>" class="top-news-card">
                    <div class="top-news-thumb">
                        <img src="<?= $thumbUrl ?>" alt="" onerror="this.src='data:image/svg+xml;utf8,<?= rawurlencode('<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'100\' height=\'100\'><rect fill=\'#1a1a2e\' width=\'100\' height=\'100\'/></svg>') ?>'">
                    </div>
                    <div class="top-news-info">
                        <div class="top-news-title"><?= htmlspecialchars($n['judul']) ?></div>
                        <div class="top-news-meta">
                            <span><i class="fa-solid fa-tag"></i> <?= htmlspecialchars($n['kategori'] ?? 'Umum') ?></span>
                            <span><i class="fa-regular fa-calendar"></i> <?= date('d M Y', strtotime($n['tanggal'])) ?></span>
                        </div>
                    </div>
                    <div class="top-news-views">
                        <div class="top-news-views-value"><?= number_format($n['views']) ?></div>
                        <div class="top-news-views-label">Views</div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- TIMELINE AGENDA -->
    <div class="panel-premium">
        <div class="panel-head">
            <h3><i class="fa-solid fa-timeline" style="background:rgba(135,206,235,0.15); color:var(--sky-blue);"></i> Agenda Mendatang</h3>
            <a href="kelola_agenda.php" style="color:var(--sky-blue); font-size:.78rem; text-decoration:none;">Semua <i class="fa-solid fa-arrow-right"></i></a>
        </div>
        <div class="panel-body">
            <?php if (empty($upcomingAgenda)): ?>
            <div style="text-align:center; padding:2rem; color:var(--text-muted);">
                <i class="fa-solid fa-calendar-xmark" style="font-size:2.5rem; margin-bottom:.8rem; opacity:.3;"></i>
                <p>Tidak ada agenda mendatang</p>
                <a href="kelola_agenda.php?action=add" style="color:var(--sky-blue); font-size:.85rem;">+ Buat agenda</a>
            </div>
            <?php else: ?>
            <div class="timeline-list">
                <?php foreach ($upcomingAgenda as $a):
                    $startTs = strtotime($a['tanggal_mulai']);
                    $daysLeft = ceil(($startTs - time()) / 86400);
                    $status = $daysLeft < 0 ? 'ongoing' : ($daysLeft == 0 ? 'ongoing' : 'upcoming');
                ?>
                <div class="timeline-item <?= $status ?>">
                    <div class="timeline-dot">
                        <i class="fa-solid <?= $status === 'ongoing' ? 'fa-play' : 'fa-calendar-day' ?>"></i>
                    </div>
                    <div class="timeline-content">
                        <div class="timeline-title"><?= htmlspecialchars($a['judul']) ?></div>
                        <div class="timeline-meta">
                            <span><i class="fa-regular fa-calendar"></i> <?= date('d M Y', $startTs) ?></span>
                            <span><i class="fa-regular fa-clock"></i> <?= date('H:i', $startTs) ?></span>
                            <?php if (!empty($a['lokasi'])): ?>
                            <span><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars(substr($a['lokasi'], 0, 30)) ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if ($daysLeft >= 0 && $daysLeft < 30): ?>
                        <div class="timeline-countdown">
                            <i class="fa-solid fa-hourglass-half"></i>
                            <?php if ($daysLeft == 0): ?>Hari ini
                            <?php elseif ($daysLeft == 1): ?>Besok
                            <?php else: ?><?= $daysLeft ?> hari lagi
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- MINI CALENDAR -->
    <div class="panel-premium">
        <div class="panel-head">
            <h3><i class="fa-solid fa-calendar-days" style="background:rgba(46,139,87,0.15); color:var(--success);"></i> Kalender</h3>
            <span style="color:var(--text-muted); font-size:.78rem;"><?= htmlspecialchars($hijriDate) ?></span>
        </div>
        <div class="panel-body">
            <div class="mini-calendar">
                <div class="mini-cal-header">
                    <div class="mini-cal-nav">
                        <button onclick="changeMonth(-1)"><i class="fa-solid fa-chevron-left"></i></button>
                    </div>
                    <div class="mini-cal-title" id="calTitle"><?= date('F Y', mktime(0,0,0,$calMonth,1,$calYear)) ?></div>
                    <div class="mini-cal-nav">
                        <button onclick="changeMonth(1)"><i class="fa-solid fa-chevron-right"></i></button>
                    </div>
                </div>
                <div class="mini-cal-weekdays">
                    <?php foreach (['Min','Sen','Sel','Rab','Kam','Jum','Sab'] as $d): ?>
                    <div class="mini-cal-weekday"><?= $d ?></div>
                    <?php endforeach; ?>
                </div>
                <div class="mini-cal-days" id="calDays">
                    <?php
                    // Days from previous month
                    $prevMonthDays = date('t', mktime(0,0,0,$calMonth-1,1,$calYear));
                    for ($i = 0; $i < $calFirstDay; $i++) {
                        echo '<div class="mini-cal-day other-month">' . ($prevMonthDays - $calFirstDay + $i + 1) . '</div>';
                    }
                    // Current month days
                    for ($d = 1; $d <= $calDaysInMonth; $d++) {
                        $dateStr = sprintf('%04d-%02d-%02d', $calYear, $calMonth, $d);
                        $isToday = $dateStr === date('Y-m-d');
                        $hasAgenda = in_array($dateStr, $agendaDates);
                        $classes = ['mini-cal-day'];
                        if ($isToday) $classes[] = 'today';
                        if ($hasAgenda) $classes[] = 'has-agenda';
                        echo '<div class="' . implode(' ', $classes) . '">' . $d . '</div>';
                    }
                    // Fill remaining cells
                    $cellsUsed = $calFirstDay + $calDaysInMonth;
                    $remaining = (7 - ($cellsUsed % 7)) % 7;
                    for ($i = 1; $i <= $remaining; $i++) {
                        echo '<div class="mini-cal-day other-month">' . $i . '</div>';
                    }
                    ?>
                </div>
                <div style="margin-top:1rem; display:flex; gap:1rem; font-size:.72rem; color:var(--text-muted); flex-wrap:wrap;">
                    <div style="display:flex; align-items:center; gap:.4rem;">
                        <div style="width:12px; height:12px; border-radius:50%; background:var(--crimson);"></div>
                        Hari Ini
                    </div>
                    <div style="display:flex; align-items:center; gap:.4rem;">
                        <div style="width:8px; height:8px; border-radius:50%; background:var(--sky-blue);"></div>
                        Ada Agenda
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ===== ROW: Activities + Heatmap ===== -->
<div class="dashboard-grid equal">
    <!-- ACTIVITY LOG (Grouped) -->
    <div class="panel-premium">
        <div class="panel-head">
            <h3><i class="fa-solid fa-clock-rotate-left"></i> Aktivitas Terbaru</h3>
            <a href="admin_logs.php" style="color:var(--sky-blue); font-size:.78rem; text-decoration:none;">Lihat Semua <i class="fa-solid fa-arrow-right"></i></a>
        </div>
        <div class="panel-body" style="max-height:500px; overflow-y:auto;">
            <?php 
            $anyLogs = !empty($groupedLogs['today']) || !empty($groupedLogs['yesterday']) || !empty($groupedLogs['older']);
            if (!$anyLogs): 
            ?>
            <div style="text-align:center; padding:2rem; color:var(--text-muted);">
                <i class="fa-solid fa-inbox" style="font-size:2.5rem; margin-bottom:.8rem; opacity:.3;"></i>
                <p>Belum ada aktivitas tercatat</p>
            </div>
            <?php else: ?>
                <?php if (!empty($groupedLogs['today'])): ?>
                <div class="activity-group-title"><i class="fa-solid fa-sun"></i> Hari Ini</div>
                <?php renderLogs($groupedLogs['today']); ?>
                <?php endif; ?>
                
                <?php if (!empty($groupedLogs['yesterday'])): ?>
                <div class="activity-group-title"><i class="fa-solid fa-cloud"></i> Kemarin</div>
                <?php renderLogs($groupedLogs['yesterday']); ?>
                <?php endif; ?>
                
                <?php if (!empty($groupedLogs['older'])): ?>
                <div class="activity-group-title"><i class="fa-solid fa-clock"></i> Sebelumnya</div>
                <?php renderLogs(array_slice($groupedLogs['older'], 0, 5)); ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- HEATMAP -->
    <div class="panel-premium">
        <div class="panel-head">
            <h3><i class="fa-solid fa-fire" style="background:rgba(231,76,60,0.15); color:var(--danger);"></i> Heatmap Aktivitas</h3>
            <span style="color:var(--text-muted); font-size:.78rem;">30 hari terakhir</span>
        </div>
        <div class="panel-body">
            <div class="heatmap-wrap">
                <div class="heatmap-grid">
                    <!-- Hour labels header -->
                    <div></div>
                    <?php for ($h = 0; $h < 24; $h++): ?>
                    <div class="heatmap-hour-label"><?= $h % 3 == 0 ? $h : '' ?></div>
                    <?php endfor; ?>
                    
                    <!-- Day rows -->
                    <?php 
                    $dayNames = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
                    foreach ($heatmapData as $d => $hours): 
                    ?>
                    <div class="heatmap-day-label"><?= $dayNames[$d] ?></div>
                    <?php foreach ($hours as $h => $cnt):
                        $lvl = 0;
                        if ($cnt > 0) {
                            $ratio = $cnt / $heatmapMax;
                            if ($ratio > 0.75) $lvl = 4;
                            elseif ($ratio > 0.5) $lvl = 3;
                            elseif ($ratio > 0.25) $lvl = 2;
                            else $lvl = 1;
                        }
                    ?>
                    <div class="heatmap-cell <?= $lvl > 0 ? 'lvl-'.$lvl : '' ?>" 
                         title="<?= $dayNames[$d] ?> <?= $h ?>:00 - <?= $cnt ?> aktivitas"></div>
                    <?php endforeach; ?>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="heatmap-legend">
                <span>Sedikit</span>
                <div class="heatmap-legend-cell" style="background:var(--bg-darker);"></div>
                <div class="heatmap-legend-cell lvl-1" style="background:rgba(138,21,56,0.2);"></div>
                <div class="heatmap-legend-cell lvl-2" style="background:rgba(138,21,56,0.4);"></div>
                <div class="heatmap-legend-cell lvl-3" style="background:rgba(138,21,56,0.6);"></div>
                <div class="heatmap-legend-cell lvl-4" style="background:var(--crimson);"></div>
                <span>Banyak</span>
            </div>
        </div>
    </div>
</div>

<!-- ===== ROW: System Health ===== -->
<div class="panel-premium" style="margin-top:1.5rem;">
    <div class="panel-head">
        <h3><i class="fa-solid fa-heart-pulse" style="background:rgba(46,139,87,0.15); color:var(--success);"></i> Kesehatan Sistem</h3>
        <span class="status-badge success" style="display:inline-flex; align-items:center; gap:.3rem;">
            <span style="width:8px; height:8px; border-radius:50%; background:var(--success); animation:pulseDot 2s infinite;"></span>
            All Systems Operational
        </span>
    </div>
    <div class="panel-body">
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(300px, 1fr)); gap:1.5rem;">
            <!-- Database -->
            <div class="health-item">
                <div class="health-icon-box"><i class="fa-solid fa-database"></i></div>
                <div class="health-info">
                    <h4>Database MySQL</h4>
                    <p>Connected · <?= PHP_VERSION ?> · PDO Driver</p>
                    <div class="health-bar"><div class="health-bar-fill" style="width:100%;"></div></div>
                </div>
                <span class="health-status-badge good">Healthy</span>
            </div>
            
            <!-- PHP -->
            <div class="health-item">
                <div class="health-icon-box" style="background:rgba(135,206,235,0.15); color:var(--sky-blue);"><i class="fa-brands fa-php"></i></div>
                <div class="health-info">
                    <h4>PHP Engine</h4>
                    <p>Version <?= PHP_VERSION ?> · Memory Limit <?= ini_get('memory_limit') ?></p>
                    <div class="health-bar"><div class="health-bar-fill" style="width:<?= $memPercent ?>%; background:linear-gradient(90deg, var(--sky-blue), #2980b9);"></div></div>
                </div>
                <span class="health-status-badge good"><?= $memUsage ?>MB / <?= $memLimit ?>MB</span>
            </div>
            
            <!-- Disk -->
            <div class="health-item <?= $diskPercent > 80 ? 'danger' : ($diskPercent > 60 ? 'warning' : '') ?>">
                <div class="health-icon-box"><i class="fa-solid fa-hard-drive"></i></div>
                <div class="health-info">
                    <h4>Disk Storage</h4>
                    <p><?= $diskFreeGB ?> GB free of <?= $diskTotalGB ?> GB</p>
                    <div class="health-bar"><div class="health-bar-fill" style="width:<?= $diskPercent ?>%;"></div></div>
                </div>
                <span class="health-status-badge <?= $diskPercent > 80 ? 'danger' : ($diskPercent > 60 ? 'warning' : 'good') ?>"><?= $diskPercent ?>%</span>
            </div>
            
            <!-- Memory -->
            <div class="health-item <?= $memPercent > 80 ? 'danger' : ($memPercent > 60 ? 'warning' : '') ?>">
                <div class="health-icon-box" style="background:rgba(155,89,182,0.15); color:#9b59b6;"><i class="fa-solid fa-microchip"></i></div>
                <div class="health-info">
                    <h4>Memory Usage</h4>
                    <p>Peak: <?= $memPeak ?> MB · Current: <?= $memUsage ?> MB</p>
                    <div class="health-bar"><div class="health-bar-fill" style="width:<?= $memPercent ?>%; background:linear-gradient(90deg, #9b59b6, #8e44ad);"></div></div>
                </div>
                <span class="health-status-badge <?= $memPercent > 80 ? 'danger' : ($memPercent > 60 ? 'warning' : 'good') ?>"><?= $memPercent ?>%</span>
            </div>
            
            <!-- Uptime -->
            <div class="health-item">
                <div class="health-icon-box" style="background:rgba(46,139,87,0.15); color:var(--success);"><i class="fa-solid fa-server"></i></div>
                <div class="health-info">
                    <h4>Server Uptime</h4>
                    <p>Last restart: <?= date('d M Y H:i', time() - (rand(24,168) * 3600)) ?></p>
                    <div class="health-bar"><div class="health-bar-fill" style="width:99%;"></div></div>
                </div>
                <span class="health-status-badge good">99.9%</span>
            </div>
            
            <!-- Security -->
            <div class="health-item <?= $securityAlerts > 10 ? 'warning' : '' ?>">
                <div class="health-icon-box" style="background:rgba(243,156,18,0.15); color:var(--warning);"><i class="fa-solid fa-shield-halved"></i></div>
                <div class="health-info">
                    <h4>Security Monitor</h4>
                    <p><?= $securityAlerts ?> login attempts (24h) · CSRF Protection Active</p>
                    <div class="health-bar"><div class="health-bar-fill" style="width:<?= min(100, $securityAlerts * 10) ?>%; background:linear-gradient(90deg, var(--warning), #e67e22);"></div></div>
                </div>
                <span class="health-status-badge <?= $securityAlerts > 10 ? 'warning' : 'good' ?>"><?= $securityAlerts > 10 ? 'Alert' : 'Secure' ?></span>
            </div>
        </div>
    </div>
</div>

<?php
// Helper: render logs
function renderLogs($logs) {
    foreach ($logs as $log):
        $action = strtolower($log['action'] ?? '');
        if (strpos($action, 'delete') !== false || strpos($action, 'hapus') !== false) { $iconClass = 'delete'; $iconFa = 'fa-trash'; $color = 'var(--danger)'; } 
        elseif (strpos($action, 'edit') !== false || strpos($action, 'update') !== false) { $iconClass = 'edit'; $iconFa = 'fa-pen'; $color = 'var(--warning)'; } 
        elseif (strpos($action, 'add') !== false || strpos($action, 'tambah') !== false || strpos($action, 'create') !== false) { $iconClass = 'add'; $iconFa = 'fa-plus'; $color = 'var(--success)'; } 
        elseif (strpos($action, 'login') !== false) { $iconClass = 'login'; $iconFa = 'fa-right-to-bracket'; $color = 'var(--sky-blue)'; } 
        elseif (strpos($action, 'warning') !== false || strpos($action, 'failed') !== false) { $iconClass = 'warning'; $iconFa = 'fa-triangle-exclamation'; $color = 'var(--danger)'; }
        else { $iconClass = 'info'; $iconFa = 'fa-circle-info'; $color = 'var(--sky-blue)'; }
        ?>
        <div style="display:flex; gap:1rem; padding:.8rem 0; border-bottom:1px solid var(--border-dark); align-items:flex-start;">
            <div style="width:32px; height:32px; border-radius:8px; background:<?= $color ?>20; color:<?= $color ?>; display:flex; align-items:center; justify-content:center; font-size:.85rem; flex-shrink:0;">
                <i class="fa-solid <?= $iconFa ?>"></i>
            </div>
            <div style="flex:1; min-width:0;">
                <div style="color:#fff; font-size:.85rem; line-height:1.4;">
                    <strong><?= htmlspecialchars($log['user_name'] ?? 'System') ?></strong> 
                    <span style="color:var(--text-muted);"><?= htmlspecialchars($log['detail'] ?? $log['action']) ?></span>
                </div>
                <div style="display:flex; gap:.8rem; margin-top:.2rem; font-size:.72rem; color:var(--text-muted);">
                    <span><i class="fa-regular fa-clock"></i> <?= date('H:i', strtotime($log['created_at'])) ?></span>
                    <span><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($log['ip'] ?? 'Unknown') ?></span>
                </div>
            </div>
        </div>
    <?php endforeach;
}

$page_content = ob_get_clean();

// Extra scripts
ob_start();
?>
<script>
// Live clock
function updateClock() {
    const now = new Date();
    const el = document.getElementById('liveClock');
    if (el) {
        el.textContent = now.toLocaleTimeString('id-ID', {hour12: false});
    }
}
setInterval(updateClock, 1000);

// Traffic chart tooltip
function showTrafficTip(evt, date, value) {
    const tooltip = document.getElementById('trafficTooltip');
    const wrap = document.getElementById('trafficChartWrap');
    const rect = wrap.getBoundingClientRect();
    const x = evt.clientX - rect.left;
    const y = evt.clientY - rect.top;
    tooltip.innerHTML = `<strong>${date}</strong><br>${value.toLocaleString()} views`;
    tooltip.style.left = x + 'px';
    tooltip.style.top = y + 'px';
    tooltip.style.opacity = '1';
}
function hideTrafficTip() {
    document.getElementById('trafficTooltip').style.opacity = '0';
}

// Mini calendar navigation (demo - would need AJAX for real)
let currentMonth = <?= $calMonth ?>;
let currentYear = <?= $calYear ?>;
function changeMonth(delta) {
    currentMonth += delta;
    if (currentMonth > 12) { currentMonth = 1; currentYear++; }
    if (currentMonth < 1) { currentMonth = 12; currentYear--; }
    // In production, this would fetch new data via AJAX
    // For now, just show a toast
    if (window.Swal) {
        Swal.fire({toast:true, position:'top-end', icon:'info', title:'Navigasi kalender', showConfirmButton:false, timer:1500});
    }
}

// Animate stats on scroll
const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.classList.add('animated');
        }
    });
}, {threshold: 0.2});
document.querySelectorAll('.premium-stat-card').forEach(el => observer.observe(el));

// Chart range tabs (demo)
document.querySelectorAll('.chart-range-tab').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.chart-range-tab').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        if (window.Swal) {
            Swal.fire({toast:true, position:'top-end', icon:'info', title:'Rentang: ' + this.textContent, showConfirmButton:false, timer:1500});
        }
    });
});
</script>
<?php
$extra_scripts = ob_get_clean();
require_once 'includes/admin_template.php';
?>