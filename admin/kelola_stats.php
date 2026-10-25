<?php
require_once '../includes/config.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';
require_login();

$page_title = 'Kelola Statistik Utama';
$current_page = 'stats';
$table = 'stats';

// ===== AUTO-MIGRATION (Self-Healing Database) =====
$required_cols = [
    'accent_color' => "varchar(20) DEFAULT 'crimson'",
    'url'          => "varchar(255) DEFAULT ''",
    'is_active'    => "tinyint(1) DEFAULT 1",
];
$existing_cols = $pdo->query("SHOW COLUMNS FROM $table")->fetchAll(PDO::FETCH_COLUMN);
foreach ($required_cols as $col => $def) {
    if (!in_array($col, $existing_cols, true)) {
        $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$col` $def");
    }
}

// ===== HANDLE DELETE =====
if (isset($_GET['delete']) && isset($_GET['token']) && hash_equals($_SESSION['csrf_token'], $_GET['token'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("SELECT label FROM $table WHERE id = ?");
    $stmt->execute([$id]);
    $stat = $stmt->fetch();
    
    $pdo->prepare("DELETE FROM $table WHERE id = ?")->execute([$id]);
    
    if ($stat) {
        $pdo->prepare("INSERT INTO admin_logs (user_id, user_name, action, detail, ip) VALUES (?, ?, 'DELETE_STATS', ?, ?)")
            ->execute([$_SESSION['admin_id'], $_SESSION['admin_name'], 'Menghapus statistik: ' . $stat['label'], $_SERVER['REMOTE_ADDR']]);
    }
    header("Location: kelola_stats.php?msg=deleted");
    exit;
}

// ===== HANDLE DUPLICATE =====
if (isset($_GET['duplicate']) && isset($_GET['token']) && hash_equals($_SESSION['csrf_token'], $_GET['token'])) {
    $id = (int)$_GET['duplicate'];
    $stmt = $pdo->prepare("SELECT * FROM $table WHERE id = ?");
    $stmt->execute([$id]);
    $orig = $stmt->fetch();
    if ($orig) {
        $pdo->prepare("INSERT INTO $table (label, nilai, suffix, icon, urutan, accent_color, url, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
            ->execute([
                $orig['label'] . ' (Copy)', $orig['nilai'], $orig['suffix'], $orig['icon'],
                ($orig['urutan'] ?? 0) + 1, $orig['accent_color'] ?? 'crimson', $orig['url'] ?? '', 0
            ]);
        $pdo->prepare("INSERT INTO admin_logs (user_id, user_name, action, detail, ip) VALUES (?, ?, 'DUPLICATE_STATS', ?, ?)")
            ->execute([$_SESSION['admin_id'], $_SESSION['admin_name'], 'Menduplikasi statistik: ' . $orig['label'], $_SERVER['REMOTE_ADDR']]);
    }
    header("Location: kelola_stats.php?msg=duplicated");
    exit;
}

// ===== HANDLE TOGGLE STATUS =====
if (isset($_POST['toggle_status']) && isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $id = (int)$_POST['id'];
    $is_active = (int)$_POST['is_active'];
    $pdo->prepare("UPDATE $table SET is_active = ? WHERE id = ?")->execute([$is_active, $id]);
    header("Location: kelola_stats.php?msg=toggled");
    exit;
}

// ===== HANDLE BULK DELETE =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete']) && isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $ids = $_POST['bulk_ids'] ?? [];
    $deleted = 0;
    foreach ($ids as $id) {
        $pdo->prepare("DELETE FROM $table WHERE id = ?")->execute([(int)$id]);
        $deleted++;
    }
    if ($deleted > 0) {
        $pdo->prepare("INSERT INTO admin_logs (user_id, user_name, action, detail, ip) VALUES (?, ?, 'BULK_DELETE_STATS', ?, ?)")
            ->execute([$_SESSION['admin_id'], $_SESSION['admin_name'], "Menghapus $deleted statistik", $_SERVER['REMOTE_ADDR']]);
    }
    header("Location: kelola_stats.php?msg=bulk_deleted&count=$deleted");
    exit;
}

// ===== HANDLE SAVE =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['toggle_status']) && !isset($_POST['bulk_delete']) && isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $label = sanitize($_POST['label']);
    $nilai = (int)$_POST['nilai'];
    $suffix = sanitize($_POST['suffix']);
    $icon = sanitize($_POST['icon']);
    $urutan = (int)$_POST['urutan'];
    $accent_color = sanitize($_POST['accent_color']);
    $url = sanitize($_POST['url']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if (empty($label)) {
        $error = "Label statistik wajib diisi.";
    } else {
        $data_save = [
            'label' => $label, 'nilai' => $nilai, 'suffix' => $suffix,
            'icon' => $icon, 'urutan' => $urutan, 'accent_color' => $accent_color,
            'url' => $url, 'is_active' => $is_active
        ];

        $sets = [];
        $params = [];
        foreach ($data_save as $k => $v) {
            $sets[] = "$k = :$k";
            $params[":$k"] = $v;
        }

        if ($id > 0) {
            $sql = "UPDATE $table SET " . implode(', ', $sets) . " WHERE id = :id";
            $params[':id'] = $id;
            $pdo->prepare($sql)->execute($params);
            $action = 'UPDATE_STATS';
            $detail = "Memperbarui statistik: $label";
        } else {
            $sql = "INSERT INTO $table SET " . implode(', ', $sets);
            $pdo->prepare($sql)->execute($params);
            $action = 'ADD_STATS';
            $detail = "Menambah statistik baru: $label";
        }
        
        $pdo->prepare("INSERT INTO admin_logs (user_id, user_name, action, detail, ip) VALUES (?, ?, ?, ?, ?)")
            ->execute([$_SESSION['admin_id'], $_SESSION['admin_name'], $action, $detail, $_SERVER['REMOTE_ADDR']]);
            
        header("Location: kelola_stats.php?msg=saved");
        exit;
    }
}

$items = $pdo->query("SELECT * FROM $table ORDER BY urutan ASC, id DESC")->fetchAll();
$total_stats = count($items);
$active_stats = count(array_filter($items, fn($i) => ($i['is_active'] ?? 1) == 1));
$total_value = array_sum(array_column($items, 'nilai'));

$data = ['id'=>0, 'label'=>'', 'nilai'=>0, 'suffix'=>'+', 'icon'=>'fa-solid fa-users', 'urutan'=>1, 'accent_color'=>'crimson', 'url'=>'', 'is_active'=>1];

if (isset($_GET['edit']) && isset($_GET['token']) && hash_equals($_SESSION['csrf_token'], $_GET['token'])) {
    $stmt = $pdo->prepare("SELECT * FROM $table WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $fetched = $stmt->fetch();
    if ($fetched) $data = $fetched;
}

$common_icons = [
    'fa-users' => 'Mahasiswa', 'fa-chalkboard-user' => 'Dosen', 'fa-user-graduate' => 'Alumni', 
    'fa-eye' => 'Views', 'fa-download' => 'Unduhan', 'fa-flask' => 'Penelitian', 
    'fa-newspaper' => 'Berita', 'fa-calendar-days' => 'Agenda', 'fa-images' => 'Galeri', 
    'fa-book-open' => 'Kurikulum', 'fa-globe' => 'Mitra', 'fa-award' => 'Akreditasi',
    'fa-star' => 'Rating', 'fa-heart' => 'Kepuasan', 'fa-briefcase' => 'Karir', 'fa-building' => 'Fakultas'
];

$color_options = [
    'crimson' => ['label' => 'Crimson', 'hex' => '#8A1538'],
    'sky' => ['label' => 'Sky Blue', 'hex' => '#87CEEB'],
    'emerald' => ['label' => 'Emerald', 'hex' => '#2E8B57'],
    'warning' => ['label' => 'Amber', 'hex' => '#F39C12'],
    'purple' => ['label' => 'Purple', 'hex' => '#9b59b6'],
    'info' => ['label' => 'Ocean', 'hex' => '#3498db']
];

ob_start();
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
/* ===== STATS GRID ===== */
.stats-meta-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}
.stats-meta-card {
    background: var(--panel-bg);
    border: 1px solid var(--border-dark);
    border-radius: var(--radius-md);
    padding: 1.2rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    border-left: 4px solid var(--crimson);
}
.stats-meta-icon {
    width: 48px; height: 48px;
    border-radius: var(--radius-md);
    background: rgba(138,21,56,0.15);
    color: var(--crimson);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.3rem;
}
.stats-meta-value { font-size: 1.6rem; font-weight: 900; color: #fff; line-height: 1; }
.stats-meta-label { color: var(--text-muted); font-size: .82rem; margin-top: .2rem; }

/* ===== ICON PICKER ENHANCED ===== */
.icon-picker-wrapper {
    background: var(--bg-darker);
    border: 1px solid var(--border-dark);
    border-radius: var(--radius-md);
    padding: 1rem;
    margin-top: .5rem;
}
.icon-picker-search {
    width: 100%;
    padding: .5rem .8rem;
    background: var(--panel-bg);
    border: 1px solid var(--border-dark);
    border-radius: var(--radius-sm);
    color: var(--text-main);
    margin-bottom: .8rem;
    font-size: .85rem;
}
.icon-picker-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
    gap: .5rem;
    max-height: 200px;
    overflow-y: auto;
}
.icon-picker-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: .6rem .3rem;
    border-radius: var(--radius-sm);
    cursor: pointer;
    transition: all .2s;
    border: 1px solid transparent;
    color: var(--text-muted);
    font-size: .7rem;
    text-align: center;
    gap: .3rem;
}
.icon-picker-item:hover { background: rgba(255,255,255,0.05); color: #fff; }
.icon-picker-item.active {
    background: rgba(138,21,56,0.2);
    color: var(--crimson);
    border-color: var(--crimson);
}
.icon-picker-item i { font-size: 1.3rem; }

/* ===== COLOR PICKER ===== */
.color-picker {
    display: flex;
    gap: .8rem;
    flex-wrap: wrap;
    margin-top: .5rem;
}
.color-dot-wrap {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: .3rem;
    cursor: pointer;
}
.color-dot {
    width: 36px; height: 36px;
    border-radius: 50%;
    border: 3px solid transparent;
    transition: all .2s;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
}
.color-dot-wrap.active .color-dot {
    border-color: #fff;
    transform: scale(1.15);
    box-shadow: 0 0 0 2px var(--crimson), 0 4px 12px rgba(0,0,0,0.3);
}
.color-dot-label { font-size: .7rem; color: var(--text-muted); font-weight: 600; }

/* ===== LIVE PREVIEW STAT ===== */
.live-preview-stat {
    background: var(--panel-bg);
    border: 1px solid var(--border-dark);
    border-radius: var(--radius-lg);
    padding: 2rem 1.5rem;
    text-align: center;
    position: relative;
    overflow: hidden;
    transition: all .3s;
    border-left: 5px solid var(--accent-color, var(--crimson));
}
.live-preview-stat::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0; height: 3px;
    background: var(--accent-color, var(--crimson));
}
.preview-stat-icon {
    width: 64px; height: 64px;
    border-radius: 16px;
    background: rgba(138,21,56,0.15);
    color: var(--accent-color, var(--crimson));
    display: flex; align-items: center; justify-content: center;
    font-size: 1.8rem;
    margin: 0 auto 1.2rem;
    transition: all .3s;
}
.preview-stat-value {
    font-size: 2.8rem;
    font-weight: 900;
    color: #fff;
    line-height: 1;
    margin-bottom: .4rem;
    font-family: 'Inter', sans-serif;
}
.preview-stat-suffix {
    font-size: 1.5rem;
    color: var(--accent-color, var(--crimson));
    font-weight: 700;
    margin-left: .2rem;
}
.preview-stat-label {
    color: var(--text-muted);
    font-size: .95rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .5px;
}
.preview-stat-link {
    display: inline-block;
    margin-top: 1rem;
    font-size: .75rem;
    color: var(--sky-blue);
    text-decoration: none;
}

/* ===== TABLE ENHANCEMENTS ===== */
.stat-row-icon {
    width: 40px; height: 40px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    color: #fff;
}
.stat-row-icon.crimson { background: rgba(138,21,56,0.2); color: var(--crimson); }
.stat-row-icon.sky { background: rgba(135,206,235,0.2); color: var(--sky-blue); }
.stat-row-icon.emerald { background: rgba(46,139,87,0.2); color: var(--success); }
.stat-row-icon.warning { background: rgba(243,156,18,0.2); color: var(--warning); }
.stat-row-icon.purple { background: rgba(155,89,182,0.2); color: #9b59b6; }
.stat-row-icon.info { background: rgba(52,152,219,0.2); color: var(--info); }

.stat-value-cell {
    font-size: 1.2rem;
    font-weight: 800;
    color: #fff;
}
.stat-suffix-cell {
    font-size: .85rem;
    color: var(--text-muted);
    margin-left: .2rem;
}

/* ===== BULK ACTIONS ===== */
.bulk-actions-bar {
    background: linear-gradient(135deg, rgba(138,21,56,.15), rgba(138,21,56,.05));
    border: 1px solid var(--crimson);
    border-radius: var(--radius-md);
    padding: .8rem 1.2rem;
    margin-bottom: 1rem;
    display: none;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    animation: slideDown .3s ease;
}
.bulk-actions-bar.active { display: flex; }
@keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

/* ===== FORM LAYOUT ===== */
.stats-form-grid {
    display: grid;
    grid-template-columns: 1.4fr 1fr;
    gap: 2rem;
}
@media (max-width: 992px) { .stats-form-grid { grid-template-columns: 1fr; } }

.form-section-title {
    font-size: .82rem;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    color: var(--sky-blue);
    margin: 1.5rem 0 1rem;
    padding-bottom: .5rem;
    border-bottom: 1px solid var(--border-dark);
    display: flex; align-items: center; gap: .5rem;
    font-weight: 700;
}
.form-section-title:first-child { margin-top: 0; }
.form-section-title i { color: var(--crimson); }

.input-icon-wrap { position: relative; }
.input-icon-wrap i.input-icon {
    position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
    color: var(--text-muted); font-size: .9rem; pointer-events: none;
}
.input-icon-wrap .form-control { padding-left: 42px; }

.info-box {
    padding: 1rem 1.2rem;
    border-radius: var(--radius-md);
    display: flex;
    align-items: flex-start;
    gap: .8rem;
    margin-bottom: 1.5rem;
    font-size: .85rem;
    line-height: 1.5;
}
.info-box.info {
    background: rgba(135,206,235,0.1);
    border: 1px solid rgba(135,206,235,0.3);
    border-left: 4px solid var(--sky-blue);
    color: var(--text-main);
}
.info-box i { font-size: 1.1rem; margin-top: 2px; flex-shrink: 0; color: var(--sky-blue); }
.info-box strong { display: block; margin-bottom: .2rem; font-size: .9rem; }

.toggle-switch { position: relative; display: inline-block; width: 42px; height: 22px; flex-shrink: 0; }
.toggle-switch input { opacity: 0; width: 0; height: 0; }
.toggle-slider { position: absolute; cursor: pointer; inset: 0; background: var(--bg-darker); border: 1px solid var(--border-dark); transition: .3s; border-radius: 50px; }
.toggle-slider:before { position: absolute; content: ""; height: 16px; width: 16px; left: 2px; bottom: 2px; background: var(--text-muted); transition: .3s; border-radius: 50%; }
.toggle-switch input:checked + .toggle-slider { background: var(--success); border-color: var(--success); }
.toggle-switch input:checked + .toggle-slider:before { transform: translateX(20px); background: #fff; }

.status-pill {
    display: inline-flex; align-items: center; gap: .3rem;
    padding: .2rem .6rem; border-radius: 50px;
    font-size: .7rem; font-weight: 700; text-transform: uppercase;
}
.status-pill.active { background: rgba(46,139,87,0.15); color: var(--success); }
.status-pill.inactive { background: rgba(231,76,60,0.15); color: var(--danger); }
.status-pill::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
</style>

<?php if (isset($_GET['msg'])): 
    $msgs = [
        'saved' => ['success', 'Berhasil!', 'Data statistik berhasil disimpan.'],
        'deleted' => ['success', 'Berhasil!', 'Statistik berhasil dihapus.'],
        'duplicated' => ['success', 'Berhasil!', 'Statistik berhasil diduplikasi.'],
        'toggled' => ['success', 'Berhasil!', 'Status statistik diperbarui.']
    ];
    if (isset($msgs[$_GET['msg']])): ?>
    <script>Swal.fire({icon:'<?=$msgs[$_GET['msg']][0]?>', title:'<?=$msgs[$_GET['msg']][1]?>', text:'<?=$msgs[$_GET['msg']][2]?>', timer:2500, showConfirmButton:false});</script>
<?php endif; 
    if ($_GET['msg'] === 'bulk_deleted'): ?>
    <script>Swal.fire({icon:'success', title:'Berhasil!', text:'<?= (int)($_GET['count'] ?? 0) ?> statistik berhasil dihapus.', timer:2500, showConfirmButton:false});</script>
<?php endif; 
endif; ?>

<?php if (isset($error)): ?>
<script>Swal.fire({icon:'error', title:'Validasi Gagal', text:'<?= addslashes($error) ?>'});</script>
<?php endif; ?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;">
    <div>
        <h2 class="section-title"><i class="fa-solid fa-chart-pie"></i> <?= isset($_GET['edit']) ? 'Edit' : 'Kelola' ?> Statistik Utama</h2>
        <p style="color:var(--text-muted); font-size:.88rem; margin-top:.3rem;">
            <i class="fa-solid fa-circle-info"></i> Angka kunci yang ditampilkan di halaman beranda website
        </p>
    </div>
    <div style="display:flex; gap:.8rem;">
        <?php if (isset($_GET['edit'])): ?>
        <a href="kelola_stats.php" class="btn btn-secondary"><i class="fa-solid fa-xmark"></i> Batal</a>
        <?php else: ?>
        <a href="?action=add" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Tambah Statistik</a>
        <a href="../" target="_blank" class="btn btn-secondary"><i class="fa-solid fa-external-link"></i> Lihat di Website</a>
        <?php endif; ?>
    </div>
</div>

<?php if (!isset($_GET['edit']) && !isset($_GET['action'])): ?>
<!-- ===== STATS DASHBOARD ===== -->
<div class="stats-meta-grid">
    <div class="stats-meta-card">
        <div class="stats-meta-icon"><i class="fa-solid fa-list-ol"></i></div>
        <div>
            <div class="stats-meta-value" data-counter="<?= $total_stats ?>">0</div>
            <div class="stats-meta-label">Total Statistik</div>
        </div>
    </div>
    <div class="stats-meta-card" style="border-left-color:var(--success);">
        <div class="stats-meta-icon" style="background:rgba(46,139,87,0.15); color:var(--success);"><i class="fa-solid fa-circle-check"></i></div>
        <div>
            <div class="stats-meta-value" data-counter="<?= $active_stats ?>">0</div>
            <div class="stats-meta-label">Aktif Ditampilkan</div>
        </div>
    </div>
    <div class="stats-meta-card" style="border-left-color:var(--sky-blue);">
        <div class="stats-meta-icon" style="background:rgba(135,206,235,0.15); color:var(--sky-blue);"><i class="fa-solid fa-calculator"></i></div>
        <div>
            <div class="stats-meta-value" data-counter="<?= $total_value ?>">0</div>
            <div class="stats-meta-label">Total Nilai Agregat</div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (isset($_GET['action']) && $_GET['action'] === 'add' || isset($_GET['edit'])): ?>
<!-- ===== FORM ADD/EDIT ===== -->
<div class="panel">
    <div class="panel-header">
        <h3 class="panel-title">
            <i class="fa-solid fa-pen-to-square"></i> 
            <?= isset($_GET['edit']) ? 'Edit' : 'Tambah' ?> Statistik
        </h3>
    </div>
    <div class="panel-body">
        <form method="POST" id="statsForm">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="id" value="<?= $data['id'] ?>">
            
            <div class="stats-form-grid">
                <!-- LEFT: FORM FIELDS -->
                <div>
                    <div class="info-box info">
                        <i class="fa-solid fa-lightbulb"></i>
                        <div>
                            <strong>Tips Visual:</strong> Pilih aksen warna yang berbeda untuk setiap statistik agar tampilan di beranda lebih dinamis dan mudah dibedakan secara visual.
                        </div>
                    </div>
                    
                    <div class="form-section-title"><i class="fa-solid fa-heading"></i> Informasi Dasar</div>
                    
                    <div class="form-group">
                        <label>Label Statistik <span class="required">*</span></label>
                        <div class="input-icon-wrap">
                            <i class="fa-solid fa-tag input-icon"></i>
                            <input type="text" name="label" id="f_label" class="form-control" value="<?= htmlspecialchars($data['label']) ?>" required placeholder="Contoh: Mahasiswa Aktif" data-preview="label">
                        </div>
                    </div>
                    
                    <div style="display:grid; grid-template-columns: 2fr 1fr; gap:1rem;">
                        <div class="form-group">
                            <label>Nilai (Angka Utama)</label>
                            <div class="input-icon-wrap">
                                <i class="fa-solid fa-hashtag input-icon"></i>
                                <input type="number" name="nilai" id="f_nilai" class="form-control" value="<?= $data['nilai'] ?>" required data-preview="nilai">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Suffix</label>
                            <div class="input-icon-wrap">
                                <i class="fa-solid fa-plus input-icon"></i>
                                <input type="text" name="suffix" id="f_suffix" class="form-control" value="<?= htmlspecialchars($data['suffix']) ?>" placeholder="+" data-preview="suffix">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Tautan URL (Opsional)</label>
                        <div class="input-icon-wrap">
                            <i class="fa-solid fa-link input-icon"></i>
                            <input type="url" name="url" class="form-control" value="<?= htmlspecialchars($data['url'] ?? '') ?>" placeholder="https://pbsi.ummaumere.ac.id/mahasiswa">
                        </div>
                        <small style="color:var(--text-muted);"><i class="fa-solid fa-info-circle"></i> Jika diisi, kartu statistik di beranda dapat diklik menuju halaman ini.</small>
                    </div>
                    
                    <div class="form-section-title"><i class="fa-solid fa-palette"></i> Tampilan & Ikon</div>
                    
                    <div class="form-group">
                        <label>Aksen Warna</label>
                        <div class="color-picker" id="colorPicker">
                            <?php foreach ($color_options as $key => $opt): ?>
                            <div class="color-dot-wrap <?= ($data['accent_color'] ?? 'crimson') === $key ? 'active' : '' ?>" data-color="<?= $key ?>" onclick="selectColor('<?= $key ?>', '<?= $opt['hex'] ?>')">
                                <div class="color-dot" style="background: <?= $opt['hex'] ?>;"></div>
                                <span class="color-dot-label"><?= $opt['label'] ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" name="accent_color" id="f_color" value="<?= htmlspecialchars($data['accent_color'] ?? 'crimson') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Pilih Ikon</label>
                        <div class="input-icon-wrap">
                            <i class="<?= htmlspecialchars($data['icon']) ?> input-icon" id="selectedIconPreview"></i>
                            <input type="text" name="icon" id="f_icon" class="form-control" value="<?= htmlspecialchars($data['icon']) ?>" placeholder="fa-solid fa-users" style="font-family:monospace; padding-left:42px;">
                        </div>
                        <div class="icon-picker-wrapper">
                            <input type="text" class="icon-picker-search" id="iconSearch" placeholder="Cari ikon... (contoh: user, book, globe)">
                            <div class="icon-picker-grid" id="iconPicker">
                                <?php foreach ($common_icons as $icon => $label): ?>
                                <div class="icon-picker-item <?= ($data['icon'] ?? '') === 'fa-solid fa-' . $icon ? 'active' : '' ?>" data-icon="fa-solid fa-<?= $icon ?>" data-label="<?= strtolower($label) ?>" onclick="selectStatIcon('fa-solid fa-<?= $icon ?>')">
                                    <i class="fa-solid fa-<?= $icon ?>"></i>
                                    <span><?= $label ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section-title"><i class="fa-solid fa-sliders"></i> Pengaturan</div>
                    
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem;">
                        <div class="form-group">
                            <label>Urutan Tampil</label>
                            <div class="input-icon-wrap">
                                <i class="fa-solid fa-list-ol input-icon"></i>
                                <input type="number" name="urutan" class="form-control" value="<?= (int)($data['urutan'] ?? 1) ?>" min="1">
                            </div>
                        </div>
                        <div class="form-group">
                            <label style="display:block; margin-bottom:.5rem; font-weight:600; font-size:.88rem;">Status</label>
                            <label class="toggle-switch" style="margin-top:.4rem;">
                                <input type="checkbox" name="is_active" value="1" <?= ($data['is_active'] ?? 1) == 1 ? 'checked' : '' ?>>
                                <span class="toggle-slider"></span>
                            </label>
                            <span style="margin-left:.5rem; font-weight:600; font-size:.88rem;">Aktif</span>
                        </div>
                    </div>
                    
                    <div style="margin-top:2rem; display:flex; gap:1rem; padding-top:1.5rem; border-top:1px solid var(--border-dark);">
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> <?= $data['id']>0?'Perbarui':'Simpan' ?> Statistik</button>
                        <a href="kelola_stats.php" class="btn btn-secondary"><i class="fa-solid fa-xmark"></i> Batal</a>
                    </div>
                </div>
                
                <!-- RIGHT: LIVE PREVIEW -->
                <div style="position:sticky; top:20px; align-self:start;">
                    <div class="form-section-title" style="margin-top:0;">
                        <i class="fa-solid fa-eye"></i> Live Preview (Tampilan di Beranda)
                    </div>
                    
                    <div class="live-preview-stat" id="previewCard" style="--accent-color: <?= $color_options[$data['accent_color'] ?? 'crimson']['hex'] ?>;">
                        <div class="preview-stat-icon" id="prevIconWrap">
                            <i class="<?= htmlspecialchars($data['icon']) ?>" id="prevIcon"></i>
                        </div>
                        <div class="preview-stat-value">
                            <span id="prevNilai"><?= number_format($data['nilai']) ?></span><span class="preview-stat-suffix" id="prevSuffix"><?= htmlspecialchars($data['suffix']) ?></span>
                        </div>
                        <div class="preview-stat-label" id="prevLabel"><?= htmlspecialchars($data['label'] ?: 'Label Statistik') ?></div>
                        <?php if (!empty($data['url'])): ?>
                        <a href="#" class="preview-stat-link"><i class="fa-solid fa-arrow-right"></i> Lihat Detail</a>
                        <?php endif; ?>
                    </div>
                    
                    <div style="margin-top:1rem; padding:.8rem; background:rgba(46,139,87,0.08); border:1px solid rgba(46,139,87,0.2); border-radius:var(--radius-md); font-size:.78rem; color:var(--text-muted);">
                        <i class="fa-solid fa-circle-check" style="color:var(--success);"></i>
                        <strong style="color:var(--success);"> Tip:</strong> Statistik dengan tautan URL akan menampilkan indikator panah kecil di sudut kartu saat di-hover oleh pengunjung.
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // ===== LIVE PREVIEW BINDINGS =====
    const previewCard = document.getElementById('previewCard');
    const prevIconWrap = document.getElementById('prevIconWrap');
    
    document.getElementById('f_label')?.addEventListener('input', e => {
        document.getElementById('prevLabel').textContent = e.target.value || 'Label Statistik';
    });
    
    document.getElementById('f_nilai')?.addEventListener('input', e => {
        const val = parseInt(e.target.value) || 0;
        document.getElementById('prevNilai').textContent = val.toLocaleString('id-ID');
    });
    
    document.getElementById('f_suffix')?.addEventListener('input', e => {
        document.getElementById('prevSuffix').textContent = e.target.value;
    });
    
    document.getElementById('f_icon')?.addEventListener('input', e => {
        document.getElementById('selectedIconPreview').className = e.target.value + ' input-icon';
        document.getElementById('prevIcon').className = e.target.value;
        // Update active state in picker
        document.querySelectorAll('.icon-picker-item').forEach(item => {
            item.classList.toggle('active', item.dataset.icon === e.target.value);
        });
    });
    
    // ===== ICON SEARCH =====
    document.getElementById('iconSearch')?.addEventListener('input', function() {
        const term = this.value.toLowerCase();
        document.querySelectorAll('.icon-picker-item').forEach(item => {
            const label = item.dataset.label || '';
            const iconClass = item.dataset.icon || '';
            const match = label.includes(term) || iconClass.includes(term);
            item.style.display = match ? '' : 'none';
        });
    });
});

function selectStatIcon(iconClass) {
    document.getElementById('f_icon').value = iconClass;
    document.getElementById('selectedIconPreview').className = iconClass + ' input-icon';
    document.getElementById('prevIcon').className = iconClass;
    
    document.querySelectorAll('.icon-picker-item').forEach(item => {
        item.classList.toggle('active', item.dataset.icon === iconClass);
    });
}

function selectColor(key, hex) {
    document.getElementById('f_color').value = key;
    document.getElementById('previewCard').style.setProperty('--accent-color', hex);
    
    document.querySelectorAll('.color-dot-wrap').forEach(wrap => {
        wrap.classList.toggle('active', wrap.dataset.color === key);
    });
}
</script>

<?php else: ?>
<!-- ===== LIST VIEW ===== -->
<?php if (!empty($items)): ?>
<div class="bulk-actions-bar" id="bulkActionsBar">
    <div class="bulk-info">
        <i class="fa-solid fa-check-double" style="color:var(--crimson);"></i>
        <span><span class="bulk-count" id="bulkCount">0</span> statistik dipilih</span>
    </div>
    <div style="display:flex; gap:.5rem;">
        <button type="button" class="btn btn-sm btn-secondary" onclick="clearSelection()">
            <i class="fa-solid fa-xmark"></i> Batal
        </button>
        <button type="button" class="btn btn-sm btn-primary" onclick="executeBulkDelete()" style="background:var(--danger);">
            <i class="fa-solid fa-trash"></i> Hapus Terpilih
        </button>
    </div>
</div>

<form method="POST" id="bulkForm">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="bulk_delete" value="1">
    <div id="bulkIdsContainer"></div>
</form>
<?php endif; ?>

<div class="panel">
    <div class="panel-body no-padding">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th style="width:40px;">
                            <input type="checkbox" id="selectAll" style="width:16px; height:16px; accent-color:var(--crimson);">
                        </th>
                        <th style="width:60px;">Urut</th>
                        <th style="width:70px;">Ikon</th>
                        <th>Label & Nilai</th>
                        <th style="width:100px;">Warna</th>
                        <th style="width:100px;">Status</th>
                        <th style="width:160px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)): ?>
                    <tr><td colspan="7" style="text-align:center; padding:3rem; color:var(--text-muted);">
                        <i class="fa-solid fa-chart-pie" style="font-size:3rem; margin-bottom:1rem; display:block; opacity:.3;"></i>
                        Belum ada data statistik.
                    </td></tr>
                    <?php else: foreach ($items as $item): 
                        $token = hash_hmac('sha256', $item['id'], $_SESSION['csrf_token']);
                        $colorKey = $item['accent_color'] ?? 'crimson';
                        $colorHex = $color_options[$colorKey]['hex'] ?? '#8A1538';
                        $isActive = ($item['is_active'] ?? 1) == 1;
                    ?>
                    <tr class="stat-row">
                        <td><input type="checkbox" class="row-checkbox" value="<?= $item['id'] ?>" style="width:16px; height:16px; accent-color:var(--crimson);"></td>
                        <td style="text-align:center; font-weight:800; color:var(--text-muted);">#<?= $item['urutan'] ?></td>
                        <td>
                            <div class="stat-row-icon <?= htmlspecialchars($colorKey) ?>">
                                <i class="<?= htmlspecialchars($item['icon']) ?>"></i>
                            </div>
                        </td>
                        <td>
                            <div style="font-weight:700; color:#fff; font-size:.95rem; margin-bottom:.2rem;">
                                <?= htmlspecialchars($item['label']) ?>
                                <?php if (!empty($item['url'])): ?>
                                <i class="fa-solid fa-link" style="color:var(--sky-blue); font-size:.7rem; margin-left:.3rem;" title="Memiliki tautan"></i>
                                <?php endif; ?>
                            </div>
                            <div>
                                <span class="stat-value-cell"><?= number_format($item['nilai']) ?></span>
                                <span class="stat-suffix-cell"><?= htmlspecialchars($item['suffix']) ?></span>
                            </div>
                        </td>
                        <td>
                            <div style="display:flex; align-items:center; gap:.5rem;">
                                <div style="width:18px; height:18px; border-radius:50%; background:<?= htmlspecialchars($colorHex) ?>; border:2px solid var(--border-dark);"></div>
                                <span style="font-size:.78rem; color:var(--text-muted); text-transform:capitalize;"><?= htmlspecialchars($colorKey) ?></span>
                            </div>
                        </td>
                        <td>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="toggle_status" value="1">
                                <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                <input type="hidden" name="is_active" value="<?= $isActive ? 0 : 1 ?>">
                                <button type="submit" class="status-pill <?= $isActive ? 'active' : 'inactive' ?>" style="border:none; cursor:pointer;" title="Klik untuk toggle">
                                    <?= $isActive ? 'Aktif' : 'Nonaktif' ?>
                                </button>
                            </form>
                        </td>
                        <td>
                            <div class="table-actions">
                                <a href="?edit=<?= $item['id'] ?>&token=<?= $token ?>" class="table-action-btn edit" title="Edit"><i class="fa-solid fa-pen"></i></a>
                                <a href="?duplicate=<?= $item['id'] ?>&token=<?= $token ?>" class="table-action-btn" title="Duplikasi" onclick="return confirm('Duplikasi statistik ini?')"><i class="fa-solid fa-copy"></i></a>
                                <a href="?delete=<?= $item['id'] ?>&token=<?= $token ?>" class="table-action-btn delete" onclick="return confirm('Yakin ingin menghapus?')" title="Hapus"><i class="fa-solid fa-trash"></i></a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAll = document.getElementById('selectAll');
    const bulkBar = document.getElementById('bulkActionsBar');
    const bulkCount = document.getElementById('bulkCount');
    const bulkIdsContainer = document.getElementById('bulkIdsContainer');
    
    function updateBulkBar() {
        const checked = document.querySelectorAll('.row-checkbox:checked');
        if (checked.length > 0) {
            bulkBar?.classList.add('active');
            if (bulkCount) bulkCount.textContent = checked.length;
            if (bulkIdsContainer) {
                bulkIdsContainer.innerHTML = '';
                checked.forEach(cb => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'bulk_ids[]';
                    input.value = cb.value;
                    bulkIdsContainer.appendChild(input);
                });
            }
        } else {
            bulkBar?.classList.remove('active');
        }
    }
    
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = this.checked);
            updateBulkBar();
        });
    }
    
    document.querySelectorAll('.row-checkbox').forEach(cb => {
        cb.addEventListener('change', updateBulkBar);
    });
    
    window.clearSelection = function() {
        document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = false);
        if (selectAll) selectAll.checked = false;
        updateBulkBar();
    };
    
    window.executeBulkDelete = function() {
        const count = document.querySelectorAll('.row-checkbox:checked').length;
        Swal.fire({
            icon: 'warning', title: 'Hapus ' + count + ' Statistik?',
            text: 'Tindakan ini tidak dapat dibatalkan.',
            showCancelButton: true, confirmButtonColor: '#e74c3c',
            confirmButtonText: 'Ya, Hapus', cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) document.getElementById('bulkForm').submit();
        });
    };
});
</script>
<?php endif; ?>

<script>
$(document).ready(function() {
    // Animated counters
    document.querySelectorAll('[data-counter]').forEach(el => {
        const target = parseInt(el.dataset.counter);
        if (target === 0) { el.textContent = '0'; return; }
        const duration = 1500;
        const step = target / (duration / 16);
        let current = 0;
        const timer = setInterval(() => {
            current += step;
            if (current >= target) { current = target; clearInterval(timer); }
            el.textContent = Math.floor(current).toLocaleString('id-ID');
        }, 16);
    });
});
</script>

<?php
$page_content = ob_get_clean();
require_once 'includes/admin_template.php';
?>