<?php
require_once '../includes/config.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';
require_login();

$page_title = 'Log Keamanan & Aktivitas';
$current_page = 'logs';

// ===== HANDLE EXPORT CSV =====
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="admin_logs_' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Waktu', 'User', 'Aksi', 'Detail', 'IP Address', 'User Agent']);
    
    // Re-use filters for export
    $sql = "SELECT created_at, user_name, action, detail, ip, ua FROM admin_logs WHERE 1=1 ORDER BY created_at DESC LIMIT 1000";
    $stmt = $pdo->query($sql);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}

// ===== HANDLE CLEAR OLD LOGS =====
if (isset($_GET['clear_old']) && isset($_GET['token']) && hash_equals($_SESSION['csrf_token'], $_GET['token'])) {
    if ($_SESSION['admin_role'] === 'superadmin') {
        $stmt = $pdo->prepare("DELETE FROM admin_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $stmt->execute();
        header("Location: admin_logs.php?msg=cleared_old");
        exit;
    }
}

// Get filter parameters
$filter_action = $_GET['action_filter'] ?? '';
$filter_user = $_GET['user_filter'] ?? '';
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');

// Build query
$sql = "SELECT * FROM admin_logs WHERE 1=1";
$params = [];

if ($filter_action) {
    $sql .= " AND action LIKE :action";
    $params[':action'] = "%$filter_action%";
}
if ($filter_user) {
    $sql .= " AND user_name LIKE :user";
    $params[':user'] = "%$filter_user%";
}
if ($date_from) {
    $sql .= " AND DATE(created_at) >= :date_from";
    $params[':date_from'] = $date_from;
}
if ($date_to) {
    $sql .= " AND DATE(created_at) <= :date_to";
    $params[':date_to'] = $date_to;
}

$sql .= " ORDER BY created_at DESC LIMIT 100";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Statistics
$total_logs = $pdo->query("SELECT COUNT(*) FROM admin_logs")->fetchColumn();
$today_logs = $pdo->query("SELECT COUNT(*) FROM admin_logs WHERE DATE(created_at) = CURDATE()")->fetchColumn();
$failed_logins = $pdo->query("SELECT COUNT(*) FROM admin_logs WHERE action LIKE '%LOGIN%' AND detail LIKE '%gagal%'")->fetchColumn();
$unique_users = $pdo->query("SELECT COUNT(DISTINCT user_name) FROM admin_logs")->fetchColumn();

ob_start();
?>

<style>
/* Premium Log Cards */
.log-card {
    background: var(--panel-bg);
    border: 1px solid var(--border-dark);
    border-radius: var(--radius-md);
    padding: 1.2rem;
    margin-bottom: 1rem;
    transition: all 0.3s ease;
    border-left: 4px solid var(--border-light);
    position: relative;
}
.log-card:hover {
    transform: translateX(4px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}
.log-card.login { border-left-color: var(--success); }
.log-card.logout { border-left-color: var(--sky-blue); }
.log-card.delete { border-left-color: var(--danger); }
.log-card.update { border-left-color: var(--warning); }
.log-card.add { border-left-color: var(--sky-blue); }
.log-card.warning { border-left-color: var(--danger); }

.log-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 0.8rem;
    flex-wrap: wrap;
    gap: 1rem;
}
.log-title {
    font-weight: 700;
    color: var(--text-main);
    font-size: 0.95rem;
    display: flex;
    align-items: center;
    gap: 0.6rem;
}
.log-time {
    font-size: 0.8rem;
    color: var(--text-muted);
    display: flex;
    align-items: center;
    gap: 0.4rem;
    background: var(--bg-darker);
    padding: 0.2rem 0.6rem;
    border-radius: 50px;
}
.log-detail {
    color: var(--text-muted);
    font-size: 0.88rem;
    line-height: 1.6;
    margin-bottom: 0.8rem;
    padding: 0.6rem;
    background: rgba(0,0,0,0.1);
    border-radius: var(--radius-sm);
    font-family: monospace;
}
.log-meta {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    font-size: 0.8rem;
    color: var(--text-muted);
}
.log-meta span {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    background: var(--bg-darker);
    padding: 0.2rem 0.6rem;
    border-radius: 4px;
}
.log-meta i { color: var(--crimson); }

.badge-action {
    display: inline-block;
    padding: 0.2rem 0.6rem;
    border-radius: 50px;
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.badge-action.login { background: rgba(46,139,87,0.15); color: var(--success); }
.badge-action.logout { background: rgba(135,206,235,0.15); color: var(--sky-blue); }
.badge-action.delete { background: rgba(231,76,60,0.15); color: var(--danger); }
.badge-action.update { background: rgba(243,156,18,0.15); color: var(--warning); }
.badge-action.add { background: rgba(135,206,235,0.15); color: var(--sky-blue); }
.badge-action.warning { background: rgba(231,76,60,0.15); color: var(--danger); }
</style>

<?php if (isset($_GET['msg'])): 
    $msgs = [
        'cleared' => ['success', 'Berhasil!', 'Semua log telah dihapus.'],
        'cleared_old' => ['success', 'Berhasil!', 'Log yang lebih tua dari 30 hari telah dihapus.']
    ];
    if (isset($msgs[$_GET['msg']])): ?>
    <script>Swal.fire({icon:'<?= $msgs[$_GET['msg']][0] ?>', title:'<?= $msgs[$_GET['msg']][1] ?>', text:'<?= $msgs[$_GET['msg']][2] ?>', timer:2500, showConfirmButton:false});</script>
<?php endif; endif; ?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;">
    <h2 class="section-title"><i class="fa-solid fa-shield-halved"></i> Log Keamanan & Aktivitas</h2>
    <div style="display:flex; gap:.5rem;">
        <a href="?export=csv" class="btn btn-secondary"><i class="fa-solid fa-file-csv"></i> Export CSV</a>
        <?php if ($_SESSION['admin_role'] === 'superadmin'): ?>
        <a href="?clear_old=1&token=<?= hash_hmac('sha256', 'clear_old', $_SESSION['csrf_token']) ?>" 
           class="btn btn-secondary" 
           onclick="return confirm('PERINGATAN: Ini akan menghapus log yang lebih tua dari 30 hari. Yakin?')"
           style="color: var(--warning); border-color: var(--warning);">
            <i class="fa-solid fa-broom"></i> Bersihkan Log Lama
        </a>
        <a href="?clear=1&token=<?= hash_hmac('sha256', 'clear', $_SESSION['csrf_token']) ?>" 
           class="btn btn-secondary" 
           onclick="return confirm('PERINGATAN: Ini akan menghapus SEMUA log secara permanen. Yakin?')"
           style="color: var(--danger); border-color: var(--danger);">
            <i class="fa-solid fa-trash"></i> Hapus Semua
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Statistics Cards -->
<div class="stats-grid" style="margin-bottom: 2rem;">
    <div class="stat-card accent-sky">
        <div class="stat-header"><div class="stat-icon"><i class="fa-solid fa-list"></i></div></div>
        <div class="stat-value"><?= number_format($total_logs) ?></div>
        <div class="stat-label">Total Log Tersimpan</div>
    </div>
    <div class="stat-card accent-emerald">
        <div class="stat-header"><div class="stat-icon"><i class="fa-solid fa-calendar-day"></i></div></div>
        <div class="stat-value"><?= number_format($today_logs) ?></div>
        <div class="stat-label">Aktivitas Hari Ini</div>
    </div>
    <div class="stat-card accent-warning">
        <div class="stat-header"><div class="stat-icon"><i class="fa-solid fa-triangle-exclamation"></i></div></div>
        <div class="stat-value"><?= number_format($failed_logins) ?></div>
        <div class="stat-label">Percobaan Login Gagal (24j)</div>
    </div>
    <div class="stat-card accent-crimson">
        <div class="stat-header"><div class="stat-icon"><i class="fa-solid fa-users"></i></div></div>
        <div class="stat-value"><?= number_format($unique_users) ?></div>
        <div class="stat-label">User Unik Terdeteksi</div>
    </div>
</div>

<!-- Filters -->
<div class="panel" style="margin-bottom: 2rem;">
    <div class="panel-body">
        <form method="GET" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: end;">
            <div class="form-group" style="margin: 0;">
                <label>Filter Aksi</label>
                <select name="action_filter" class="form-control select2-premium" style="width:100%;">
                    <option value="">Semua Aksi</option>
                    <?php 
                    $actions = $pdo->query("SELECT DISTINCT action FROM admin_logs ORDER BY action ASC")->fetchAll(PDO::FETCH_COLUMN);
                    foreach ($actions as $action): ?>
                    <option value="<?= htmlspecialchars($action) ?>" <?= $filter_action == $action ? 'selected' : '' ?>><?= htmlspecialchars($action) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin: 0;">
                <label>Filter User</label>
                <select name="user_filter" class="form-control select2-premium" style="width:100%;">
                    <option value="">Semua User</option>
                    <?php 
                    $users = $pdo->query("SELECT DISTINCT user_name FROM admin_logs ORDER BY user_name ASC")->fetchAll(PDO::FETCH_COLUMN);
                    foreach ($users as $user): ?>
                    <option value="<?= htmlspecialchars($user) ?>" <?= $filter_user == $user ? 'selected' : '' ?>><?= htmlspecialchars($user) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin: 0;">
                <label>Dari Tanggal</label>
                <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($date_from) ?>">
            </div>
            <div class="form-group" style="margin: 0;">
                <label>Sampai Tanggal</label>
                <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($date_to) ?>">
            </div>
            <div style="display: flex; gap: 0.5rem;">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter"></i> Filter</button>
                <a href="admin_logs.php" class="btn btn-secondary"><i class="fa-solid fa-rotate-right"></i> Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Logs List -->
<div class="panel">
    <div class="panel-body">
        <?php if (empty($logs)): ?>
        <div style="text-align: center; padding: 3rem; color: var(--text-muted);">
            <i class="fa-solid fa-clipboard-check" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3;"></i>
            <p>Tidak ada log yang ditemukan dengan filter ini.</p>
        </div>
        <?php else: ?>
        <div style="display: flex; flex-direction: column; gap: 1rem;">
            <?php foreach ($logs as $log): 
                $action_class = 'add';
                $action_lower = strtolower($log['action']);
                $icon_fa = 'fa-circle-info';
                
                if (strpos($action_lower, 'login') !== false) { $action_class = 'login'; $icon_fa = 'fa-right-to-bracket'; }
                elseif (strpos($action_lower, 'logout') !== false) { $action_class = 'logout'; $icon_fa = 'fa-right-from-bracket'; }
                elseif (strpos($action_lower, 'delete') !== false || strpos($action_lower, 'hapus') !== false) { $action_class = 'delete'; $icon_fa = 'fa-trash'; }
                elseif (strpos($action_lower, 'update') !== false || strpos($action_lower, 'edit') !== false) { $action_class = 'update'; $icon_fa = 'fa-pen'; }
                elseif (strpos($action_lower, 'add') !== false || strpos($action_lower, 'tambah') !== false || strpos($action_lower, 'create') !== false) { $action_class = 'add'; $icon_fa = 'fa-plus'; }
                elseif (strpos($action_lower, 'warning') !== false || strpos($action_lower, 'failed') !== false) { $action_class = 'warning'; $icon_fa = 'fa-triangle-exclamation'; }
            ?>
            <div class="log-card <?= $action_class ?>">
                <div class="log-header">
                    <div class="log-title">
                        <i class="fa-solid <?= $icon_fa ?>"></i>
                        <span><?= htmlspecialchars($log['action']) ?></span>
                        <span class="badge-action <?= $action_class ?>"><?= $action_class ?></span>
                    </div>
                    <div class="log-time">
                        <i class="fa-regular fa-clock"></i>
                        <?= date('d M Y, H:i:s', strtotime($log['created_at'])) ?>
                    </div>
                </div>
                <div class="log-detail">
                    <?= nl2br(htmlspecialchars($log['detail'])) ?>
                </div>
                <div class="log-meta">
                    <span><i class="fa-solid fa-user"></i> <?= htmlspecialchars($log['user_name'] ?: 'System') ?></span>
                    <span title="IP Address"><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($log['ip']) ?></span>
                    <?php if (!empty($log['ua'])): ?>
                    <span title="User Agent"><i class="fa-solid fa-browser"></i> <?= htmlspecialchars(substr($log['ua'], 0, 60)) ?><?= strlen($log['ua']) > 60 ? '...' : '' ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    $('.select2-premium').select2({
        dropdownParent: $('.panel-body'),
        language: { noResults: function() { return "Tidak ditemukan"; } }
    });
});
</script>

<?php
$page_content = ob_get_clean();
require_once 'includes/admin_template.php';
?>