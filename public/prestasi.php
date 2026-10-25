<?php
require_once '../includes/config.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';
check_ip_security($pdo);

$page_title = 'Prestasi';
require_once '../includes/public_header.php';

$setting = get_all('settings', 'id = 1')[0];
$current_lang = $_COOKIE['pbsi_lang'] ?? 'id';

// Filters
$fkat = sanitize($_GET['kategori'] ?? 'all');
$fting = sanitize($_GET['tingkat'] ?? 'all');
$ftahun = sanitize($_GET['tahun'] ?? 'all');

$sql = "SELECT * FROM prestasi WHERE status = 'aktif'";
$params = [];
if ($fkat !== 'all') { $sql .= " AND kategori = ?"; $params[] = $fkat; }
if ($fting !== 'all') { $sql .= " AND tingkat = ?"; $params[] = $fting; }
if ($ftahun !== 'all') { $sql .= " AND tahun = ?"; $params[] = (int)$ftahun; }
$sql .= " ORDER BY tahun DESC, urutan ASC, id DESC";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$prestasi = $stmt->fetchAll();

// Stats
$total_all = count($prestasi);
$total_intl = count(array_filter($prestasi, fn($p) => $p['tingkat'] === 'internasional'));
$total_nas = count(array_filter($prestasi, fn($p) => $p['tingkat'] === 'nasional'));
$tahun_list = array_values(array_unique(array_filter(array_column($prestasi, 'tahun'))));
rsort($tahun_list);

$kategori_list = ['akademik','nonakademik','kompetisi','penelitian','sastra','bahasa'];
$tingkat_list = ['internal','regional','nasional','internasional'];

function tingkat_style($t) {
    $map = ['internasional' => ['bg' => 'linear-gradient(135deg,#f39c12,#f1c40f)', 'color' => '#000'],
            'nasional' => ['bg' => 'linear-gradient(135deg,#8A1538,#A02050)', 'color' => '#fff'],
            'regional' => ['bg' => 'rgba(135,206,235,.2)', 'color' => '#5BA0C9'],
            'internal' => ['bg' => 'rgba(255,255,255,.08)', 'color' => '#8a8a9e']];
    return $map[$t] ?? $map['internal'];
}
function kategori_color($k) {
    $map = ['akademik'=>'#5BA0C9','nonakademik'=>'#2E8B57','kompetisi'=>'#8A1538','penelitian'=>'#9b59b6','sastra'=>'#EC4899','bahasa'=>'#f39c12'];
    return $map[$k] ?? '#8A1538';
}
function img_url_pst($path) {
    $path = trim((string)$path);
    if ($path === '') return '';
    if (preg_match('~^https?://~i', $path)) return $path;
    $clean = ltrim(str_replace('\\', '/', $path), '/');
    if (file_exists(__DIR__ . '/' . $clean)) return PUBLIC_URL . $clean;
    if (file_exists(__DIR__ . '/../' . $clean)) return BASE_URL . $clean;
    return '';
}
?>

<main id="main-content" role="main">

<!-- ============ PAGE HERO ============ -->
<section class="page-hero pst-hero">
    <div class="pst-hero-content">
        <span class="pst-hero-badge">
            <i class="fa-solid fa-trophy" aria-hidden="true"></i>
            <span data-i18n="pst_badge">PRESTASI</span>
        </span>
        <h1 class="pst-hero-title" data-i18n="pst_title">Prestasi & Pencapaian</h1>
        <p class="pst-hero-subtitle" data-i18n="pst_subtitle">
            Deretan prestasi membanggakan mahasiswa dan dosen di ajang kompetisi, penelitian, sastra, dan bahasa.
        </p>
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="<?= PUBLIC_URL ?>index.php" data-i18n="home">Beranda</a>
            <span aria-hidden="true"><i class="fa-solid fa-chevron-right"></i></span>
            <span aria-current="page" data-i18n="pst_breadcrumb">Prestasi</span>
        </nav>
    </div>
    <div class="pst-hero-decor" aria-hidden="true">
        <div class="pst-orb pst-orb-1"></div>
        <div class="pst-orb pst-orb-2"></div>
    </div>
</section>

<!-- ============ STATS BAR ============ -->
<section class="pst-stats-section">
    <div class="section">
        <div class="pst-stats-grid">
            <div class="pst-stat-card">
                <div class="pst-stat-icon"><i class="fa-solid fa-trophy"></i></div>
                <div class="pst-stat-value"><?= $total_all ?></div>
                <div class="pst-stat-label" data-i18n="pst_total">Total Prestasi</div>
            </div>
            <div class="pst-stat-card accent-gold">
                <div class="pst-stat-icon"><i class="fa-solid fa-earth-asia"></i></div>
                <div class="pst-stat-value"><?= $total_intl ?></div>
                <div class="pst-stat-label" data-i18n="pst_intl">Internasional</div>
            </div>
            <div class="pst-stat-card accent-crimson">
                <div class="pst-stat-icon"><i class="fa-solid fa-flag"></i></div>
                <div class="pst-stat-value"><?= $total_nas ?></div>
                <div class="pst-stat-label" data-i18n="pst_nas">Nasional</div>
            </div>
        </div>
    </div>
</section>

<!-- ============ FILTERS ============ -->
<section class="section pst-filter-section">
    <form method="GET" class="pst-filter-bar">
        <div class="pst-filter-group">
            <label><i class="fa-solid fa-layer-group"></i> <?= $current_lang === 'en' ? 'Category' : 'Kategori' ?></label>
            <select name="kategori" class="pst-select">
                <option value="all"><?= $current_lang === 'en' ? 'All' : 'Semua' ?></option>
                <?php foreach ($kategori_list as $k): ?><option value="<?= $k ?>" <?= $fkat === $k ? 'selected' : '' ?>><?= ucfirst($k) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="pst-filter-group">
            <label><i class="fa-solid fa-ranking-star"></i> <?= $current_lang === 'en' ? 'Level' : 'Tingkat' ?></label>
            <select name="tingkat" class="pst-select">
                <option value="all"><?= $current_lang === 'en' ? 'All' : 'Semua' ?></option>
                <?php foreach ($tingkat_list as $t): ?><option value="<?= $t ?>" <?= $fting === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="pst-filter-group">
            <label><i class="fa-solid fa-calendar"></i> <?= $current_lang === 'en' ? 'Year' : 'Tahun' ?></label>
            <select name="tahun" class="pst-select">
                <option value="all"><?= $current_lang === 'en' ? 'All' : 'Semua' ?></option>
                <?php foreach ($tahun_list as $th): ?><option value="<?= $th ?>" <?= $ftahun == $th ? 'selected' : '' ?>><?= $th ?></option><?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="pst-filter-btn"><i class="fa-solid fa-filter"></i> <?= $current_lang === 'en' ? 'Filter' : 'Terapkan' ?></button>
    </form>
</section>

<!-- ============ PRESTASI GRID ============ -->
<section class="section pst-list-section">
    <?php if (empty($prestasi)): ?>
    <div class="pst-empty">
        <div class="pst-empty-icon"><i class="fa-solid fa-trophy"></i></div>
        <h2 data-i18n="pst_empty_title">Belum Ada Prestasi</h2>
        <p data-i18n="pst_empty_desc">Data prestasi sedang dalam proses penyusunan.</p>
    </div>
    <?php else: ?>
    <div class="pst-grid">
        <?php foreach ($prestasi as $p): 
            $img = img_url_pst($p['gambar']);
            $ts = tingkat_style($p['tingkat']);
            $kcolor = kategori_color($p['kategori']);
        ?>
        <div class="pst-card">
            <div class="pst-card-img" style="<?= $img ? "background-image:url('$img');" : "background:linear-gradient(135deg, {$kcolor}40, {$kcolor}15);" ?>">
                <?php if (!$img): ?><i class="fa-solid fa-trophy" style="font-size:3rem;color:<?= $kcolor ?>;opacity:.6;"></i><?php endif; ?>
                <span class="pst-card-tingkat" style="background:<?= $ts['bg'] ?>;color:<?= $ts['color'] ?>;"><?= strtoupper(htmlspecialchars($p['tingkat'])) ?></span>
                <?php if ($p['tahun']): ?><span class="pst-card-tahun"><?= $p['tahun'] ?></span><?php endif; ?>
            </div>
            <div class="pst-card-body">
                <div class="pst-card-top">
                    <span class="pst-card-kat" style="background:<?= $kcolor ?>20;color:<?= $kcolor ?>;"><?= ucfirst(htmlspecialchars($p['kategori'])) ?></span>
                    <span class="pst-card-pencapaian"><i class="fa-solid fa-medal"></i> <?= htmlspecialchars($p['pencapaian']) ?></span>
                </div>
                <h3 class="pst-card-title"><?= htmlspecialchars($p['judul']) ?></h3>
                <?php if ($p['nama_tim']): ?>
                <div class="pst-card-tim"><i class="fa-solid fa-users"></i> <?= htmlspecialchars($p['nama_tim']) ?></div>
                <?php endif; ?>
                <?php if ($p['deskripsi']): ?>
                <p class="pst-card-desc"><?= htmlspecialchars(substr($p['deskripsi'], 0, 110)) ?><?= strlen($p['deskripsi']) > 110 ? '...' : '' ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>

<!-- ============ CTA ============ -->
<section class="pst-cta-section">
    <div class="pst-cta-bg" aria-hidden="true">
        <div class="pst-cta-orb pst-cta-orb-1"></div>
        <div class="pst-cta-orb pst-cta-orb-2"></div>
    </div>
    <div class="pst-cta-box">
        <div class="pst-cta-icon"><i class="fa-solid fa-trophy"></i></div>
        <h2 data-i18n="pst_cta_title">Punya Prestasi untuk Dilaporkan?</h2>
        <p data-i18n="pst_cta_desc">Mahasiswa dan dosen yang meraih prestasi dapat melaporkannya ke program studi untuk didokumentasikan.</p>
        <div class="pst-cta-buttons">
            <a href="<?= PUBLIC_URL ?>kontak.php" class="btn-primary magnetic-btn">
                <i class="fa-solid fa-paper-plane"></i>
                <span data-i18n="pst_cta_contact">Laporkan Prestasi</span>
            </a>
        </div>
    </div>
</section>

</main>

<style>
/* ===== HERO ===== */
.pst-hero { position: relative; padding: 11rem 1.5rem 5rem; overflow: hidden; }
.pst-hero-content { max-width: 900px; margin: 0 auto; position: relative; z-index: 2; text-align: center; }
.pst-hero-badge { display: inline-flex; align-items: center; gap: .6rem; padding: .5rem 1.3rem; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); backdrop-filter: blur(10px); border-radius: 50px; font-size: .75rem; font-weight: 800; letter-spacing: 3px; color: var(--sky-blue); margin-bottom: 1.5rem; }
.pst-hero-badge i { color: #f1c40f; }
.pst-hero-title { font-size: clamp(2.2rem, 7vw, 4.5rem); font-weight: 900; line-height: 1.05; margin-bottom: 1.2rem; background: linear-gradient(135deg, #f1c40f, #fff, var(--sky-blue)); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent; }
.pst-hero-subtitle { font-size: 1.2rem; color: #cfd8dc; max-width: 700px; margin: 0 auto 2rem; line-height: 1.7; }
.pst-hero .breadcrumb { justify-content: center; font-size: .9rem; }
.pst-hero .breadcrumb i { font-size: .7rem; color: var(--sky-blue); }
.pst-hero-decor { position: absolute; inset: 0; pointer-events: none; overflow: hidden; }
.pst-orb { position: absolute; border-radius: 50%; filter: blur(80px); opacity: .4; }
.pst-orb-1 { width: 400px; height: 400px; background: #f39c12; top: -100px; left: -100px; animation: pstOrbFloat 20s ease-in-out infinite; }
.pst-orb-2 { width: 350px; height: 350px; background: var(--crimson); bottom: -100px; right: -100px; animation: pstOrbFloat 25s ease-in-out infinite reverse; }
@keyframes pstOrbFloat { 0%, 100% { transform: translate(0, 0) scale(1); } 33% { transform: translate(50px, -30px) scale(1.1); } 66% { transform: translate(-30px, 50px) scale(0.9); } }

/* ===== STATS ===== */
.pst-stats-section { background: var(--bg-light); padding: 3rem 0; }
[data-theme="dark"] .pst-stats-section { background: var(--rich-black); }
.pst-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; }
.pst-stat-card { background: #fff; border-radius: 16px; padding: 1.5rem; text-align: center; box-shadow: 0 8px 25px rgba(0,0,0,.06); border-top: 4px solid var(--crimson); transition: transform .3s ease; }
[data-theme="dark"] .pst-stat-card { background: #1a1a2e; }
.pst-stat-card:hover { transform: translateY(-5px); }
.pst-stat-card.accent-gold { border-top-color: #f1c40f; }
.pst-stat-card.accent-crimson { border-top-color: var(--crimson); }
.pst-stat-icon { width: 50px; height: 50px; border-radius: 12px; background: rgba(138,21,56,.1); color: var(--crimson); display: flex; align-items: center; justify-content: center; font-size: 1.3rem; margin: 0 auto 1rem; }
.pst-stat-card.accent-gold .pst-stat-icon { background: rgba(241,196,15,.15); color: #f39c12; }
.pst-stat-card.accent-crimson .pst-stat-icon { background: rgba(138,21,56,.15); color: var(--crimson); }
.pst-stat-value { font-size: 2rem; font-weight: 900; color: var(--rich-black); line-height: 1; }
[data-theme="dark"] .pst-stat-value { color: #fff; }
.pst-stat-label { color: var(--text-grey); font-size: .85rem; margin-top: .3rem; }

/* ===== FILTER ===== */
.pst-filter-section { padding-bottom: 0; }
.pst-filter-bar { display: flex; flex-wrap: wrap; gap: 1rem; align-items: end; background: #fff; padding: 1.2rem 1.5rem; border-radius: 16px; box-shadow: 0 8px 25px rgba(0,0,0,.06); }
[data-theme="dark"] .pst-filter-bar { background: #1a1a2e; }
.pst-filter-group { display: flex; flex-direction: column; gap: .4rem; }
.pst-filter-group label { font-size: .78rem; font-weight: 700; color: var(--text-grey); display: flex; align-items: center; gap: .4rem; }
.pst-filter-group label i { color: var(--crimson); }
.pst-select { padding: .6rem 1rem; border: 1px solid #e0e0e0; border-radius: 10px; background: var(--bg-light); color: var(--rich-black); font-size: .88rem; cursor: pointer; min-width: 150px; }
[data-theme="dark"] .pst-select { background: #0f0f1a; border-color: #2a2a3e; color: #fff; }
.pst-select:focus { outline: none; border-color: var(--crimson); }
.pst-filter-btn { padding: .65rem 1.5rem; background: var(--crimson); color: #fff; border: none; border-radius: 10px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: .5rem; transition: all .2s ease; margin-left: auto; }
.pst-filter-btn:hover { background: var(--crimson-light, #A02050); }

/* ===== GRID ===== */
.pst-list-section { padding-top: 2rem; }
.pst-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.8rem; }
.pst-card { background: #fff; border-radius: 20px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,.07); transition: all .35s ease; border: 1px solid transparent; }
[data-theme="dark"] .pst-card { background: #1a1a2e; }
.pst-card:hover { transform: translateY(-8px); box-shadow: 0 20px 50px rgba(0,0,0,.14); border-color: rgba(241,196,15,.3); }
.pst-card-img { height: 190px; background-size: cover; background-position: center; background-color: #f0f0f5; display: flex; align-items: center; justify-content: center; position: relative; }
[data-theme="dark"] .pst-card-img { background-color: #0f0f1a; }
.pst-card-tingkat { position: absolute; top: 12px; left: 12px; padding: .3rem .8rem; border-radius: 50px; font-size: .68rem; font-weight: 800; letter-spacing: .5px; box-shadow: 0 4px 12px rgba(0,0,0,.2); }
.pst-card-tahun { position: absolute; top: 12px; right: 12px; background: rgba(0,0,0,.55); backdrop-filter: blur(6px); color: #fff; padding: .25rem .7rem; border-radius: 50px; font-size: .75rem; font-weight: 800; font-family: 'Courier New', monospace; }
.pst-card-body { padding: 1.3rem; }
.pst-card-top { display: flex; justify-content: space-between; align-items: center; gap: .5rem; margin-bottom: .7rem; flex-wrap: wrap; }
.pst-card-kat { display: inline-block; padding: .2rem .6rem; border-radius: 50px; font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; }
.pst-card-pencapaian { display: inline-flex; align-items: center; gap: .35rem; font-size: .8rem; font-weight: 800; color: #f39c12; }
.pst-card-title { font-size: 1.05rem; font-weight: 800; color: var(--rich-black); margin: 0 0 .5rem; line-height: 1.35; }
[data-theme="dark"] .pst-card-title { color: #fff; }
.pst-card-tim { display: flex; align-items: center; gap: .4rem; font-size: .82rem; color: var(--sky-blue-dark, #5BA0C9); font-weight: 600; margin-bottom: .6rem; }
.pst-card-desc { font-size: .85rem; color: var(--text-grey); line-height: 1.6; margin: 0; }

/* ===== EMPTY ===== */
.pst-empty { text-align: center; padding: 5rem 2rem; background: #fff; border-radius: 24px; box-shadow: 0 10px 40px rgba(0,0,0,.08); max-width: 700px; margin: 0 auto; }
[data-theme="dark"] .pst-empty { background: #1a1a2e; }
.pst-empty-icon { width: 120px; height: 120px; margin: 0 auto 1.5rem; background: linear-gradient(135deg, rgba(241,196,15,0.15), rgba(138,21,56,0.1)); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 3.5rem; color: #f39c12; }
.pst-empty h2 { color: var(--rich-black); margin-bottom: .5rem; font-size: 1.5rem; font-weight: 800; }
[data-theme="dark"] .pst-empty h2 { color: #fff; }
.pst-empty p { color: var(--text-grey); }

/* ===== CTA ===== */
.pst-cta-section { padding: 6rem 1.5rem; background: linear-gradient(180deg, #fff 0%, var(--bg-light) 100%); position: relative; overflow: hidden; }
[data-theme="dark"] .pst-cta-section { background: linear-gradient(180deg, var(--rich-black), #0f0f1a); }
.pst-cta-bg { position: absolute; inset: 0; pointer-events: none; }
.pst-cta-orb { position: absolute; border-radius: 50%; filter: blur(80px); opacity: .3; }
.pst-cta-orb-1 { width: 400px; height: 400px; background: #f39c12; top: -150px; left: -100px; animation: pstOrbFloat 25s ease-in-out infinite; }
.pst-cta-orb-2 { width: 350px; height: 350px; background: var(--crimson); bottom: -150px; right: -100px; animation: pstOrbFloat 30s ease-in-out infinite reverse; }
.pst-cta-box { max-width: 800px; margin: 0 auto; text-align: center; padding: 4rem 3rem; background: linear-gradient(135deg, #f39c12, #e67e22); border-radius: 28px; color: #fff; box-shadow: 0 30px 80px rgba(243,156,18,.35); position: relative; overflow: hidden; z-index: 2; }
.pst-cta-icon { width: 90px; height: 90px; margin: 0 auto 1.5rem; border-radius: 50%; background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.25); display: flex; align-items: center; justify-content: center; font-size: 2.5rem; backdrop-filter: blur(10px); position: relative; z-index: 2; }
.pst-cta-box h2 { font-size: clamp(1.5rem, 4vw, 2.2rem); margin-bottom: 1rem; font-weight: 900; position: relative; z-index: 2; }
.pst-cta-box p { color: rgba(255,255,255,.95); margin-bottom: 2rem; line-height: 1.8; max-width: 600px; margin-left: auto; margin-right: auto; position: relative; z-index: 2; }
.pst-cta-buttons { display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap; position: relative; z-index: 2; }
.pst-cta-buttons .btn-primary { background: #fff; color: #e67e22; margin-top: 0; box-shadow: 0 12px 35px rgba(0,0,0,.25); }

@media (max-width: 768px) {
    .pst-hero { padding: 9rem 1rem 4rem; }
    .pst-filter-bar { flex-direction: column; align-items: stretch; }
    .pst-filter-btn { margin-left: 0; justify-content: center; }
    .pst-grid { grid-template-columns: 1fr; }
    .pst-cta-box { padding: 2.5rem 1.5rem; }
}
</style>

<script>
(function () {
    'use strict';
    const ro = new IntersectionObserver((es) => { es.forEach(e => { if (e.isIntersecting) { e.target.style.opacity='1'; e.target.style.transform='translateY(0)'; ro.unobserve(e.target); } }); }, { threshold: 0.1 });
    document.querySelectorAll('.pst-card').forEach((el,i) => { el.style.opacity='0'; el.style.transform='translateY(30px)'; el.style.transition=`all 0.6s cubic-bezier(0.22,0.61,0.36,1) ${i*0.05}s`; ro.observe(el); });
    document.querySelectorAll('.magnetic-btn').forEach(el => { el.addEventListener('mousemove',(e)=>{const r=el.getBoundingClientRect();el.style.transform=`translate(${(e.clientX-r.left-r.width/2)*0.15}px,${(e.clientY-r.top-r.height/2)*0.15}px)`;}); el.addEventListener('mouseleave',()=>{el.style.transform='';}); });
})();
</script>

<script>
(function () {
    'use strict';
    const t = { en: { 'pst_badge':'ACHIEVEMENTS','pst_title':'Achievements & Awards','pst_subtitle':'A proud series of student and lecturer achievements in competitions, research, literature, and language.','home':'Home','pst_breadcrumb':'Achievements','pst_total':'Total Achievements','pst_intl':'International','pst_nas':'National','pst_empty_title':'No Achievements Yet','pst_empty_desc':'Achievement data is being compiled.','pst_cta_title':'Have an Achievement to Report?','pst_cta_desc':'Students and lecturers who achieve awards can report them to the study program for documentation.','pst_cta_contact':'Report Achievement' }};
    function apply(){ const l=document.documentElement.getAttribute('lang')||'id'; if(l==='en'&&t.en){document.querySelectorAll('[data-i18n]').forEach(el=>{const k=el.dataset.i18n;if(t.en[k])el.textContent=t.en[k];});} }
    new MutationObserver(m=>{m.forEach(x=>{if(x.attributeName==='lang')apply();});}).observe(document.documentElement,{attributes:true}); apply();
})();
</script>

<?php require_once '../includes/public_footer.php'; ?>