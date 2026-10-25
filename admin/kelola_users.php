<?php
require_once '../includes/config.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';
require_once 'includes/upload_helper.php';
require_login();

// ===== PERMISSION GUARD =====
if (($_SESSION['admin_role'] ?? 'admin') !== 'superadmin') {
    header("Location: dashboard.php?msg=unauthorized");
    exit;
}

$page_title = 'Manajemen User Admin';
$current_page = 'users';
$table = 'users';

// ===== AUTO-MIGRATION (Self-Healing Database) =====
$required_cols = [
    'avatar'     => "varchar(255) DEFAULT ''",
    'email'      => "varchar(100) DEFAULT ''",
    'status'     => "tinyint(1) DEFAULT 1",
    'last_login' => "datetime DEFAULT NULL",
    'last_ip'    => "varchar(45) DEFAULT ''",
    'login_count'=> "int(11) DEFAULT 0",
];
$existing_cols = $pdo->query("SHOW COLUMNS FROM $table")->fetchAll(PDO::FETCH_COLUMN);
foreach ($required_cols as $col => $def) {
    if (!in_array($col, $existing_cols, true)) {
        $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$col` $def");
    }
}

// ===== HANDLE DELETE (Dengan CSRF & Proteksi Diri) =====
if (isset($_GET['delete']) && isset($_GET['token']) && hash_equals($_SESSION['csrf_token'], $_GET['token'])) {
    $id = (int)$_GET['delete'];
    
    if ($id == $_SESSION['admin_id']) {
        header("Location: kelola_users.php?msg=error_self");
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT nama, username, avatar FROM $table WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    
    if ($user) {
        // Hapus avatar jika ada
        if (!empty($user['avatar']) && !filter_var($user['avatar'], FILTER_VALIDATE_URL) && file_exists('../' . $user['avatar'])) {
            @unlink('../' . $user['avatar']);
        }
        
        $pdo->prepare("DELETE FROM $table WHERE id = ?")->execute([$id]);
        
        $pdo->prepare("INSERT INTO admin_logs (user_id, user_name, action, detail, ip) VALUES (?, ?, 'DELETE_USER', ?, ?)")
            ->execute([$_SESSION['admin_id'], $_SESSION['admin_name'], 'Menghapus user: ' . $user['username'], $_SERVER['REMOTE_ADDR']]);
        
        header("Location: kelola_users.php?msg=deleted");
        exit;
    }
}

// ===== HANDLE BULK DELETE =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete']) && isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $ids = $_POST['bulk_ids'] ?? [];
    $deleted = 0;
    foreach ($ids as $id) {
        $id = (int)$id;
        if ($id == $_SESSION['admin_id']) continue;
        
        $stmt = $pdo->prepare("SELECT avatar FROM $table WHERE id = ?");
        $stmt->execute([$id]);
        $av = $stmt->fetchColumn();
        if ($av && !filter_var($av, FILTER_VALIDATE_URL) && file_exists('../' . $av)) @unlink('../' . $av);
        
        $pdo->prepare("DELETE FROM $table WHERE id = ?")->execute([$id]);
        $deleted++;
    }
    
    if ($deleted > 0) {
        $pdo->prepare("INSERT INTO admin_logs (user_id, user_name, action, detail, ip) VALUES (?, ?, 'BULK_DELETE_USER', ?, ?)")
            ->execute([$_SESSION['admin_id'], $_SESSION['admin_name'], "Menghapus $deleted user", $_SERVER['REMOTE_ADDR']]);
    }
    
    header("Location: kelola_users.php?msg=bulk_deleted&count=$deleted");
    exit;
}

// ===== HANDLE TOGGLE STATUS =====
if (isset($_POST['toggle_status']) && isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $id = (int)$_POST['id'];
    $status = (int)$_POST['status'];
    
    if ($id == $_SESSION['admin_id']) {
        header("Location: kelola_users.php?msg=error_self");
        exit;
    }
    
    $pdo->prepare("UPDATE $table SET status = ? WHERE id = ?")->execute([$status, $id]);
    header("Location: kelola_users.php?msg=toggled");
    exit;
}

// ===== HANDLE ADD / EDIT =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['toggle_status']) && !isset($_POST['bulk_delete']) && isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $nama = sanitize($_POST['nama']);
    $username = sanitize($_POST['username']);
    $email = sanitize($_POST['email']);
    $role = sanitize($_POST['role']);
    $status = isset($_POST['status']) ? 1 : 0;
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['password_confirm'] ?? '';
    
    // Validasi
    if (empty($nama) || empty($username)) {
        $error = "Nama dan Username wajib diisi.";
    } elseif (!preg_match('/^[a-zA-Z0-9_.-]{3,30}$/', $username)) {
        $error = "Username hanya boleh berisi huruf, angka, titik, underscore, dan strip (3-30 karakter).";
    } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Format email tidak valid.";
    } elseif (!empty($password) && strlen($password) < 6) {
        $error = "Password minimal 6 karakter.";
    } elseif (!empty($password) && $password !== $confirm) {
        $error = "Konfirmasi password tidak cocok.";
    } elseif ($id == 0 && empty($password)) {
        $error = "Password wajib diisi untuk user baru.";
    } else {
        // Cek duplikasi username
        $dup_stmt = $pdo->prepare("SELECT id FROM $table WHERE username = ? AND id != ?");
        $dup_stmt->execute([$username, $id]);
        if ($dup_stmt->fetch()) {
            $error = "Username '$username' sudah digunakan.";
        } else {
            // Handle avatar upload
            $avatar_path = $data['avatar'] ?? '';
            if (!empty($_FILES['avatar']['name'])) {
                $upload = handle_upload($_FILES['avatar'], '../assets/uploads/avatars/', ['jpg', 'jpeg', 'png', 'webp']);
                if ($upload['success']) {
                    $avatar_path = $upload['path'];
                    // Hapus avatar lama
                    if ($id > 0) {
                        $old_stmt = $pdo->prepare("SELECT avatar FROM $table WHERE id = ?");
                        $old_stmt->execute([$id]);
                        $old_av = $old_stmt->fetchColumn();
                        if ($old_av && !filter_var($old_av, FILTER_VALIDATE_URL) && file_exists('../' . $old_av)) {
                            @unlink('../' . $old_av);
                        }
                    }
                } else {
                    $error = $upload['error'];
                }
            }
            
            if (!isset($error)) {
                if ($id > 0) {
                    if (!empty($password)) {
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE $table SET nama=:nama, username=:username, email=:email, role=:role, status=:status, avatar=:avatar, password=:password WHERE id=:id");
                        $stmt->execute([':nama'=>$nama, ':username'=>$username, ':email'=>$email, ':role'=>$role, ':status'=>$status, ':avatar'=>$avatar_path, ':password'=>$hash, ':id'=>$id]);
                        $detail = "Memperbarui user: $username (Password direset)";
                    } else {
                        $stmt = $pdo->prepare("UPDATE $table SET nama=:nama, username=:username, email=:email, role=:role, status=:status, avatar=:avatar WHERE id=:id");
                        $stmt->execute([':nama'=>$nama, ':username'=>$username, ':email'=>$email, ':role'=>$role, ':status'=>$status, ':avatar'=>$avatar_path, ':id'=>$id]);
                        $detail = "Memperbarui user: $username";
                    }
                    $action = 'UPDATE_USER';
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO $table (nama, username, email, password, role, status, avatar) VALUES (:nama, :username, :email, :password, :role, :status, :avatar)");
                    $stmt->execute([':nama'=>$nama, ':username'=>$username, ':email'=>$email, ':password'=>$hash, ':role'=>$role, ':status'=>$status, ':avatar'=>$avatar_path]);
                    $action = 'ADD_USER';
                    $detail = "Menambah user baru: $username (Role: $role)";
                }
                
                $pdo->prepare("INSERT INTO admin_logs (user_id, user_name, action, detail, ip) VALUES (?, ?, ?, ?, ?)")
                    ->execute([$_SESSION['admin_id'], $_SESSION['admin_name'], $action, $detail, $_SERVER['REMOTE_ADDR']]);
                
                header("Location: kelola_users.php?msg=saved");
                exit;
            }
        }
    }
}

// ===== FETCH DATA =====
$items = $pdo->query("SELECT * FROM $table ORDER BY 
    CASE WHEN role = 'superadmin' THEN 1 WHEN role = 'admin' THEN 2 ELSE 3 END,
    id DESC")->fetchAll();

// Statistics
$total_users = count($items);
$superadmin_count = count(array_filter($items, fn($u) => $u['role'] === 'superadmin'));
$admin_count = count(array_filter($items, fn($u) => $u['role'] === 'admin'));
$editor_count = count(array_filter($items, fn($u) => $u['role'] === 'editor'));
$active_count = count(array_filter($items, fn($u) => ($u['status'] ?? 1) == 1));

// Form data
$data = ['id'=>0, 'nama'=>'', 'username'=>'', 'email'=>'', 'role'=>'admin', 'status'=>1, 'avatar'=>''];
if (isset($_GET['edit']) && isset($_GET['token']) && hash_equals($_SESSION['csrf_token'], $_GET['token'])) {
    $stmt = $pdo->prepare("SELECT * FROM $table WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $fetched = $stmt->fetch();
    if ($fetched) {
        foreach ($fetched as $k => $v) {
            $data[$k] = ($v === null) ? '' : $v;
        }
    }
}

ob_start();
?>

<style>
/* ===== STATS GRID ===== */
.users-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}
.users-stat-card {
    background: var(--panel-bg);
    border: 1px solid var(--border-dark);
    border-radius: var(--radius-md);
    padding: 1.2rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: all .3s ease;
    cursor: pointer;
    border-left: 4px solid var(--accent, var(--crimson));
}
.users-stat-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); }
.users-stat-icon {
    width: 48px; height: 48px;
    border-radius: var(--radius-md);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.3rem; flex-shrink: 0;
}
.users-stat-card.accent-crimson { --accent: var(--crimson); }
.users-stat-card.accent-crimson .users-stat-icon { background: rgba(138,21,56,0.15); color: var(--crimson); }
.users-stat-card.accent-sky { --accent: var(--sky-blue); }
.users-stat-card.accent-sky .users-stat-icon { background: rgba(135,206,235,0.15); color: var(--sky-blue); }
.users-stat-card.accent-emerald { --accent: var(--success); }
.users-stat-card.accent-emerald .users-stat-icon { background: rgba(46,139,87,0.15); color: var(--success); }
.users-stat-card.accent-purple { --accent: #9b59b6; }
.users-stat-card.accent-purple .users-stat-icon { background: rgba(155,89,182,0.15); color: #9b59b6; }
.users-stat-card.accent-warning { --accent: var(--warning); }
.users-stat-card.accent-warning .users-stat-icon { background: rgba(243,156,18,0.15); color: var(--warning); }
.users-stat-value { font-size: 1.8rem; font-weight: 900; color: #fff; line-height: 1; }
.users-stat-label { color: var(--text-muted); font-size: .78rem; margin-top: .2rem; }

/* ===== FILTER BAR ===== */
.users-filter-bar {
    background: var(--panel-bg);
    border: 1px solid var(--border-dark);
    border-radius: var(--radius-md);
    padding: 1.2rem;
    margin-bottom: 1.5rem;
    display: grid;
    grid-template-columns: 2fr 1fr 1fr auto;
    gap: 1rem;
    align-items: end;
}
.users-filter-bar .form-group { margin: 0; }
.users-filter-bar label {
    font-size: .82rem; color: var(--text-muted); margin-bottom: .4rem;
    display: flex; align-items: center; gap: .4rem; font-weight: 600;
}

/* ===== AVATAR ===== */
.avatar-upload-wrap {
    display: flex;
    gap: 1.2rem;
    align-items: flex-start;
    margin-bottom: 1.5rem;
}
.avatar-preview {
    width: 100px; height: 100px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--crimson), var(--crimson-light));
    display: flex; align-items: center; justify-content: center;
    color: #fff;
    font-size: 2.5rem;
    font-weight: 900;
    flex-shrink: 0;
    overflow: hidden;
    border: 3px solid var(--border-dark);
    position: relative;
    box-shadow: 0 8px 20px rgba(138,21,56,0.25);
}
.avatar-preview img {
    width: 100%; height: 100%;
    object-fit: cover;
}
.avatar-upload-controls { flex: 1; }
.avatar-upload-btn {
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    padding: .6rem 1.2rem;
    background: var(--bg-darker);
    border: 1px solid var(--border-dark);
    color: var(--text-main);
    border-radius: var(--radius-md);
    cursor: pointer;
    font-weight: 600;
    font-size: .85rem;
    transition: all .2s;
}
.avatar-upload-btn:hover { border-color: var(--crimson); color: var(--crimson); }
.avatar-upload-btn input[type="file"] { display: none; }
.avatar-helper { font-size: .78rem; color: var(--text-muted); margin-top: .5rem; }

/* ===== ROLE BADGES (Enhanced) ===== */
.role-badge {
    display: inline-flex; align-items: center; gap: .4rem;
    padding: .3rem .8rem;
    border-radius: 50px;
    font-size: .72rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .6px;
}
.role-badge.superadmin { background: rgba(138,21,56,0.15); color: var(--crimson); border: 1px solid rgba(138,21,56,0.3); }
.role-badge.admin { background: rgba(135,206,235,0.15); color: var(--sky-blue); border: 1px solid rgba(135,206,235,0.3); }
.role-badge.editor { background: rgba(46,139,87,0.15); color: var(--success); border: 1px solid rgba(46,139,87,0.3); }

.status-badge {
    display: inline-flex; align-items: center; gap: .3rem;
    padding: .2rem .7rem;
    border-radius: 50px;
    font-size: .7rem;
    font-weight: 700;
}
.status-badge.active { background: rgba(46,139,87,0.15); color: var(--success); }
.status-badge.inactive { background: rgba(231,76,60,0.15); color: var(--danger); }
.status-badge::before {
    content: ''; width: 6px; height: 6px; border-radius: 50%;
    background: currentColor;
}

/* ===== TABLE USER CELL ===== */
.user-cell { display: flex; align-items: center; gap: .8rem; }
.user-avatar-sm {
    width: 42px; height: 42px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--crimson), var(--crimson-light));
    color: #fff; display: flex; align-items: center; justify-content: center;
    font-weight: 800; font-size: .95rem; flex-shrink: 0;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(138,21,56,0.2);
}
.user-avatar-sm img { width: 100%; height: 100%; object-fit: cover; }
.user-cell-info { min-width: 0; }
.user-cell-name {
    font-weight: 700; color: #fff; font-size: .9rem;
    display: flex; align-items: center; gap: .4rem; flex-wrap: wrap;
}
.user-cell-meta {
    font-size: .75rem; color: var(--text-muted);
    display: flex; align-items: center; gap: .4rem; flex-wrap: wrap;
    margin-top: .15rem;
}
.you-badge {
    font-size: .62rem; background: var(--success); color: #fff;
    padding: 1px 6px; border-radius: 3px; font-weight: 800;
    letter-spacing: .5px;
}

/* ===== PASSWORD STRENGTH METER ===== */
.strength-meter {
    display: flex; gap: 3px; margin-top: .5rem;
}
.strength-bar {
    flex: 1; height: 4px;
    background: var(--border-dark);
    border-radius: 2px;
    transition: background .2s;
}
.strength-label {
    font-size: .72rem;
    margin-top: .3rem;
    font-weight: 600;
}
.strength-0 .strength-label { color: var(--text-muted); }
.strength-1 .strength-bar:nth-child(-n+1) { background: var(--danger); }
.strength-1 .strength-label { color: var(--danger); }
.strength-2 .strength-bar:nth-child(-n+2) { background: var(--warning); }
.strength-2 .strength-label { color: var(--warning); }
.strength-3 .strength-bar:nth-child(-n+3) { background: var(--sky-blue); }
.strength-3 .strength-label { color: var(--sky-blue); }
.strength-4 .strength-bar { background: var(--success); }
.strength-4 .strength-label { color: var(--success); }

/* ===== ROLE PERMISSIONS CARD ===== */
.role-permissions {
    background: var(--bg-darker);
    border: 1px solid var(--border-dark);
    border-radius: var(--radius-md);
    padding: 1rem;
    margin-top: 1rem;
}
.role-permissions-title {
    font-size: .78rem;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: var(--sky-blue);
    margin-bottom: .8rem;
    display: flex; align-items: center; gap: .5rem;
    font-weight: 700;
}
.role-permissions-title i { color: var(--crimson); }
.permissions-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: .5rem;
}
.perm-item {
    display: flex; align-items: center; gap: .5rem;
    font-size: .78rem;
    color: var(--text-main);
    padding: .4rem .6rem;
    border-radius: 4px;
}
.perm-item i.yes { color: var(--success); }
.perm-item i.no { color: var(--danger); opacity: .5; }

/* ===== FORM GRID ===== */
.users-form-grid {
    display: grid;
    grid-template-columns: 1.4fr 1fr;
    gap: 2rem;
}
@media (max-width: 992px) { .users-form-grid { grid-template-columns: 1fr; } }

/* ===== SECTION TITLE ===== */
.form-section-title {
    font-size: .78rem;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    color: var(--sky-blue);
    margin: 1.8rem 0 1rem;
    padding-bottom: .5rem;
    border-bottom: 1px solid var(--border-dark);
    display: flex; align-items: center; gap: .5rem;
    font-weight: 700;
}
.form-section-title:first-child { margin-top: 0; }
.form-section-title i { color: var(--crimson); }

/* ===== INPUT ICON ===== */
.input-icon-wrap { position: relative; }
.input-icon-wrap i.input-icon {
    position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
    color: var(--text-muted); font-size: .9rem; pointer-events: none;
}
.input-icon-wrap .form-control { padding-left: 42px; }

/* ===== PASSWORD TOGGLE ===== */
.password-wrapper { position: relative; }
.password-toggle {
    position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
    background: none; border: none; color: var(--text-muted); cursor: pointer;
    padding: 4px; transition: color .2s;
}
.password-toggle:hover { color: var(--text-main); }

/* ===== INFO ALERT ===== */
.info-alert {
    background: rgba(135,206,235,0.08);
    border: 1px solid rgba(135,206,235,0.25);
    border-left: 4px solid var(--sky-blue);
    border-radius: var(--radius-md);
    padding: .8rem 1rem;
    margin-bottom: 1.5rem;
    color: var(--text-main);
    font-size: .85rem;
    display: flex; align-items: flex-start; gap: .6rem;
}
.info-alert i { color: var(--sky-blue); margin-top: .15rem; flex-shrink: 0; }
.info-alert strong { color: var(--sky-blue); }

/* ===== EMPTY STATE ===== */
.users-empty {
    text-align: center;
    padding: 4rem 2rem;
    background: var(--panel-bg);
    border: 2px dashed var(--border-dark);
    border-radius: var(--radius-lg);
}
.users-empty-icon {
    width: 100px; height: 100px;
    margin: 0 auto 1.5rem;
    background: linear-gradient(135deg, var(--crimson), var(--crimson-light));
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 3rem;
    color: #fff;
    box-shadow: 0 10px 30px rgba(138,21,56,.3);
}
.users-empty h3 { color: #fff; margin-bottom: .5rem; font-size: 1.3rem; }
.users-empty p { color: var(--text-muted); margin-bottom: 1.5rem; max-width: 500px; margin-left: auto; margin-right: auto; }

/* ===== MODERN TOGGLE ===== */
.toggle-switch { position: relative; display: inline-block; width: 42px; height: 22px; flex-shrink: 0; }
.toggle-switch input { opacity: 0; width: 0; height: 0; }
.toggle-slider { position: absolute; cursor: pointer; inset: 0; background: var(--bg-darker); border: 1px solid var(--border-dark); transition: .3s; border-radius: 50px; }
.toggle-slider:before { position: absolute; content: ""; height: 16px; width: 16px; left: 2px; bottom: 2px; background: var(--text-muted); transition: .3s; border-radius: 50%; }
.toggle-switch input:checked + .toggle-slider { background: var(--success); border-color: var(--success); }
.toggle-switch input:checked + .toggle-slider:before { transform: translateX(20px); background: #fff; }

/* ===== BULK ACTIONS BAR ===== */
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
.bulk-info {
    display: flex; align-items: center; gap: .6rem;
    color: var(--text-main);
    font-weight: 600;
    font-size: .88rem;
}
.bulk-count {
    background: var(--crimson);
    color: #fff;
    padding: .15rem .7rem;
    border-radius: 50px;
    font-size: .78rem;
    font-weight: 800;
}

/* ===== LAST LOGIN CELL ===== */
.last-login-cell {
    font-size: .78rem;
    color: var(--text-muted);
    line-height: 1.5;
}
.last-login-cell i { color: var(--sky-blue); margin-right: .3rem; }
.last-login-cell strong { color: var(--text-main); font-weight: 600; }

/* ===== RESPONSIVE ===== */
@media (max-width: 992px) {
    .users-filter-bar { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 576px) {
    .users-filter-bar { grid-template-columns: 1fr; }
    .users-stats-grid { grid-template-columns: repeat(2, 1fr); }
    .permissions-grid { grid-template-columns: 1fr; }
}
</style>

<?php if (isset($_GET['msg'])): 
    $msgs = [
        'saved'           => ['success', 'Berhasil!', 'Data user berhasil disimpan.'],
        'deleted'         => ['success', 'Berhasil!', 'User berhasil dihapus.'],
        'toggled'         => ['success', 'Berhasil!', 'Status user diperbarui.'],
        'error_self'      => ['error', 'Ditolak!', 'Anda tidak dapat menghapus/menonaktifkan akun Anda sendiri.'],
        'unauthorized'    => ['warning', 'Akses Ditolak!', 'Hanya Super Admin yang dapat mengakses modul ini.'],
    ];
    if (isset($msgs[$_GET['msg']])): ?>
    <script>Swal.fire({icon:'<?=$msgs[$_GET['msg']][0]?>', title:'<?=$msgs[$_GET['msg']][1]?>', text:'<?=$msgs[$_GET['msg']][2]?>', timer:2500, showConfirmButton:false});</script>
<?php endif; 
    if ($_GET['msg'] === 'bulk_deleted'): ?>
    <script>Swal.fire({icon:'success', title:'Berhasil!', text:'<?= (int)($_GET['count'] ?? 0) ?> user berhasil dihapus.', timer:2500, showConfirmButton:false});</script>
<?php endif; 
endif; ?>

<?php if (isset($error)): ?>
<script>Swal.fire({icon:'error', title:'Validasi Gagal', text:'<?= addslashes($error) ?>'});</script>
<?php endif; ?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;">
    <div>
        <h2 class="section-title"><i class="fa-solid fa-users-gear"></i> <?= isset($_GET['edit']) ? 'Edit' : 'Kelola' ?> User Admin</h2>
        <p style="color:var(--text-muted); font-size:.88rem; margin-top:.3rem;">
            <i class="fa-solid fa-shield-halved"></i> Manajemen akun dengan hak akses terkontrol
        </p>
    </div>
    <div style="display:flex; gap:.8rem;">
        <?php if (isset($_GET['edit'])): ?>
        <a href="kelola_users.php" class="btn btn-secondary"><i class="fa-solid fa-xmark"></i> Batal</a>
        <?php else: ?>
        <a href="?action=add" class="btn btn-primary"><i class="fa-solid fa-user-plus"></i> Tambah User</a>
        <a href="admin_logs.php" class="btn btn-secondary"><i class="fa-solid fa-shield-halved"></i> Log Aktivitas</a>
        <?php endif; ?>
    </div>
</div>

<?php if (!isset($_GET['edit']) && !isset($_GET['action'])): ?>
<!-- ===== STATS DASHBOARD ===== -->
<div class="users-stats-grid">
    <div class="users-stat-card accent-crimson">
        <div class="users-stat-icon"><i class="fa-solid fa-users"></i></div>
        <div>
            <div class="users-stat-value" data-counter="<?= $total_users ?>">0</div>
            <div class="users-stat-label">Total User</div>
        </div>
    </div>
    <div class="users-stat-card accent-purple" onclick="document.getElementById('roleFilter').value='superadmin'; document.getElementById('roleFilter').dispatchEvent(new Event('change'));">
        <div class="users-stat-icon"><i class="fa-solid fa-crown"></i></div>
        <div>
            <div class="users-stat-value" data-counter="<?= $superadmin_count ?>">0</div>
            <div class="users-stat-label">Super Admin</div>
        </div>
    </div>
    <div class="users-stat-card accent-sky" onclick="document.getElementById('roleFilter').value='admin'; document.getElementById('roleFilter').dispatchEvent(new Event('change'));">
        <div class="users-stat-icon"><i class="fa-solid fa-user-shield"></i></div>
        <div>
            <div class="users-stat-value" data-counter="<?= $admin_count ?>">0</div>
            <div class="users-stat-label">Admin</div>
        </div>
    </div>
    <div class="users-stat-card accent-emerald" onclick="document.getElementById('roleFilter').value='editor'; document.getElementById('roleFilter').dispatchEvent(new Event('change'));">
        <div class="users-stat-icon"><i class="fa-solid fa-pen-nib"></i></div>
        <div>
            <div class="users-stat-value" data-counter="<?= $editor_count ?>">0</div>
            <div class="users-stat-label">Editor</div>
        </div>
    </div>
    <div class="users-stat-card accent-warning">
        <div class="users-stat-icon"><i class="fa-solid fa-user-check"></i></div>
        <div>
            <div class="users-stat-value" data-counter="<?= $active_count ?>">0</div>
            <div class="users-stat-label">Aktif</div>
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
            <?= isset($_GET['edit']) ? 'Edit' : 'Tambah' ?> User Admin
        </h3>
    </div>
    <div class="panel-body">
        <form method="POST" enctype="multipart/form-data" id="userForm">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="id" value="<?= $data['id'] ?>">
            
            <div class="users-form-grid">
                <!-- LEFT: FORM FIELDS -->
                <div>
                    <div class="info-alert">
                        <i class="fa-solid fa-shield-halved"></i>
                        <div>
                            <strong>Keamanan Akun:</strong> Password akan di-hash menggunakan <code>bcrypt</code>. Untuk keamanan maksimal, gunakan kombinasi huruf besar, kecil, angka, dan simbol (minimal 8 karakter).
                        </div>
                    </div>
                    
                    <!-- AVATAR -->
                    <div class="form-section-title"><i class="fa-solid fa-user-circle"></i> Foto Profil</div>
                    <div class="avatar-upload-wrap">
                        <div class="avatar-preview" id="avatarPreview">
                            <?php if (!empty($data['avatar'])): ?>
                                <img src="../<?= htmlspecialchars($data['avatar']) ?>" alt="Avatar">
                            <?php else: ?>
                                <span id="avatarInitials"><?= strtoupper(substr($data['nama'] ?: 'U', 0, 1)) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="avatar-upload-controls">
                            <label class="avatar-upload-btn">
                                <i class="fa-solid fa-camera"></i>
                                <?= !empty($data['avatar']) ? 'Ganti Foto' : 'Upload Foto' ?>
                                <input type="file" name="avatar" accept="image/*" id="avatarInput">
                            </label>
                            <div class="avatar-helper">
                                <i class="fa-solid fa-info-circle"></i>
                                JPG/PNG/WebP. Maks 2MB. Direkomendasikan rasio 1:1 (persegi).
                            </div>
                            <?php if (!empty($data['avatar'])): ?>
                            <button type="button" class="avatar-upload-btn" onclick="removeAvatar()" style="margin-top:.5rem; color:var(--danger); border-color:var(--danger);">
                                <i class="fa-solid fa-trash"></i> Hapus Foto
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- IDENTITAS -->
                    <div class="form-section-title"><i class="fa-solid fa-id-card"></i> Identitas</div>
                    
                    <div class="field-group">
                        <label style="display:block; margin-bottom:.5rem; font-weight:600; font-size:.88rem;">
                            <i class="fa-solid fa-user" style="color:var(--crimson); margin-right:.3rem;"></i>
                            Nama Lengkap <span class="required" style="color:var(--crimson);">*</span>
                        </label>
                        <div class="input-icon-wrap">
                            <i class="fa-solid fa-user input-icon"></i>
                            <input type="text" name="nama" id="f_nama" class="form-control" value="<?= htmlspecialchars($data['nama']) ?>" required placeholder="Contoh: Budi Santoso">
                        </div>
                    </div>
                    
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="field-group">
                            <label style="display:block; margin-bottom:.5rem; font-weight:600; font-size:.88rem;">
                                <i class="fa-solid fa-at" style="color:var(--crimson); margin-right:.3rem;"></i>
                                Username <span class="required" style="color:var(--crimson);">*</span>
                            </label>
                            <div class="input-icon-wrap">
                                <i class="fa-solid fa-user-tag input-icon"></i>
                                <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($data['username']) ?>" required placeholder="budi.admin" pattern="[a-zA-Z0-9_.-]{3,30}">
                            </div>
                            <div style="font-size:.72rem; color:var(--text-muted); margin-top:.3rem;">
                                <i class="fa-solid fa-info-circle"></i> 3-30 karakter, huruf/angka/titik/underscore/strip
                            </div>
                        </div>
                        <div class="field-group">
                            <label style="display:block; margin-bottom:.5rem; font-weight:600; font-size:.88rem;">
                                <i class="fa-solid fa-envelope" style="color:var(--crimson); margin-right:.3rem;"></i>
                                Email
                            </label>
                            <div class="input-icon-wrap">
                                <i class="fa-solid fa-envelope input-icon"></i>
                                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($data['email'] ?? '') ?>" placeholder="user@example.com">
                            </div>
                        </div>
                    </div>
                    
                    <!-- KEAMANAN -->
                    <div class="form-section-title"><i class="fa-solid fa-lock"></i> Keamanan</div>
                    
                    <div class="field-group">
                        <label style="display:block; margin-bottom:.5rem; font-weight:600; font-size:.88rem;">
                            <i class="fa-solid fa-key" style="color:var(--crimson); margin-right:.3rem;"></i>
                            Password <?= $data['id'] > 0 ? '<small style="color:var(--text-muted); font-weight:400;">(kosongkan jika tidak diubah)</small>' : '<span class="required" style="color:var(--crimson);">*</span>' ?>
                        </label>
                        <div class="input-icon-wrap password-wrapper">
                            <i class="fa-solid fa-lock input-icon"></i>
                            <input type="password" name="password" id="passwordInput" class="form-control" <?= $data['id']==0?'required':'' ?> placeholder="Minimal 6 karakter" style="padding-right:45px;">
                            <button type="button" class="password-toggle" onclick="togglePassword('passwordInput', 'toggleIcon1')" title="Lihat Password">
                                <i class="fa-solid fa-eye" id="toggleIcon1"></i>
                            </button>
                        </div>
                        <div class="strength-meter strength-0" id="strengthMeter">
                            <div class="strength-bar"></div>
                            <div class="strength-bar"></div>
                            <div class="strength-bar"></div>
                            <div class="strength-bar"></div>
                        </div>
                        <div class="strength-label" id="strengthLabel">Masukkan password</div>
                    </div>
                    
                    <div class="field-group">
                        <label style="display:block; margin-bottom:.5rem; font-weight:600; font-size:.88rem;">
                            <i class="fa-solid fa-check-double" style="color:var(--crimson); margin-right:.3rem;"></i>
                            Konfirmasi Password
                        </label>
                        <div class="input-icon-wrap password-wrapper">
                            <i class="fa-solid fa-check-double input-icon"></i>
                            <input type="password" name="password_confirm" id="passwordConfirm" class="form-control" placeholder="Ulangi password" style="padding-right:45px;">
                            <button type="button" class="password-toggle" onclick="togglePassword('passwordConfirm', 'toggleIcon2')" title="Lihat Password">
                                <i class="fa-solid fa-eye" id="toggleIcon2"></i>
                            </button>
                        </div>
                        <div id="matchStatus" style="font-size:.75rem; margin-top:.3rem; color:var(--text-muted);"></div>
                    </div>
                    
                    <!-- HAK AKSES -->
                    <div class="form-section-title"><i class="fa-solid fa-user-shield"></i> Hak Akses</div>
                    
                    <div class="field-group">
                        <label style="display:block; margin-bottom:.5rem; font-weight:600; font-size:.88rem;">
                            <i class="fa-solid fa-user-tag" style="color:var(--crimson); margin-right:.3rem;"></i>
                            Role / Peran
                        </label>
                        <select name="role" id="f_role" class="form-control select2-premium" style="width:100%;">
                            <option value="superadmin" <?= $data['role']=='superadmin'?'selected':'' ?>>👑 Super Admin (Akses Penuh)</option>
                            <option value="admin" <?= $data['role']=='admin'?'selected':'' ?>>🛡️ Admin (Kelola Konten)</option>
                            <option value="editor" <?= $data['role']=='editor'?'selected':'' ?>>✏️ Editor (Hanya Tulis/Edit)</option>
                        </select>
                    </div>
                    
                    <!-- PERMISSION MATRIX -->
                    <div class="role-permissions" id="rolePermissions">
                        <div class="role-permissions-title">
                            <i class="fa-solid fa-key"></i> Matriks Hak Akses
                        </div>
                        <div class="permissions-grid" id="permissionsGrid"></div>
                    </div>
                    
                    <div class="field-group" style="margin-top:1.5rem;">
                        <label class="toggle-switch">
                            <input type="checkbox" name="status" value="1" <?= ($data['status'] ?? 1) == 1 ? 'checked' : '' ?>>
                            <span class="toggle-slider"></span>
                        </label>
                        <span style="margin-left:.8rem; font-weight:600; font-size:.88rem;">
                            <i class="fa-solid fa-circle-check" style="color:var(--success);"></i>
                            Akun Aktif (dapat login)
                        </span>
                    </div>
                    
                    <div style="margin-top:2.5rem; display:flex; gap:1rem; padding-top:1.5rem; border-top:1px solid var(--border-dark);">
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> <?= $data['id']>0?'Perbarui':'Simpan' ?> User</button>
                        <a href="kelola_users.php" class="btn btn-secondary"><i class="fa-solid fa-xmark"></i> Batal</a>
                    </div>
                </div>
                
                <!-- RIGHT: LIVE PREVIEW -->
                <div style="position:sticky; top:20px; align-self:start;">
                    <div class="form-section-title" style="margin-top:0;">
                        <i class="fa-solid fa-eye"></i> Preview Akun
                    </div>
                    
                    <div style="background:var(--bg-darker); border:1px solid var(--border-dark); border-radius:var(--radius-lg); padding:2rem; text-align:center;">
                        <div style="width:120px; height:120px; border-radius:50%; margin:0 auto 1rem; background:linear-gradient(135deg, var(--crimson), var(--crimson-light)); display:flex; align-items:center; justify-content:center; overflow:hidden; border:4px solid var(--border-dark); box-shadow:0 10px 30px rgba(138,21,56,0.3);" id="prevAvatarWrap">
                            <?php if (!empty($data['avatar'])): ?>
                                <img src="../<?= htmlspecialchars($data['avatar']) ?>" style="width:100%; height:100%; object-fit:cover;" id="prevAvatarImg">
                            <?php else: ?>
                                <span style="color:#fff; font-size:3rem; font-weight:900;" id="prevAvatarInitial"><?= strtoupper(substr($data['nama'] ?: 'U', 0, 1)) ?></span>
                            <?php endif; ?>
                        </div>
                        <h3 style="color:#fff; font-size:1.2rem; font-weight:800; margin-bottom:.3rem;" id="prevNama"><?= htmlspecialchars($data['nama'] ?: 'Nama User') ?></h3>
                        <div style="color:var(--text-muted); font-size:.85rem; margin-bottom:.8rem;">
                            <i class="fa-solid fa-at"></i>
                            <span id="prevUsername"><?= htmlspecialchars($data['username'] ?: 'username') ?></span>
                        </div>
                        <?php if (!empty($data['email'])): ?>
                        <div style="color:var(--text-muted); font-size:.78rem; margin-bottom:.8rem;">
                            <i class="fa-solid fa-envelope"></i>
                            <span id="prevEmail"><?= htmlspecialchars($data['email']) ?></span>
                        </div>
                        <?php endif; ?>
                        <div id="prevRoleBadge">
                            <span class="role-badge <?= htmlspecialchars($data['role']) ?>">
                                <?php 
                                $roleIcons = ['superadmin' => 'fa-crown', 'admin' => 'fa-user-shield', 'editor' => 'fa-pen-nib'];
                                ?>
                                <i class="fa-solid <?= $roleIcons[$data['role']] ?? 'fa-user' ?>"></i>
                                <?= ucfirst($data['role']) ?>
                            </span>
                        </div>
                        <div style="margin-top:1rem; padding-top:1rem; border-top:1px solid var(--border-dark);">
                            <div style="font-size:.72rem; color:var(--text-muted); margin-bottom:.3rem;">STATUS</div>
                            <span class="status-badge <?= ($data['status'] ?? 1) == 1 ? 'active' : 'inactive' ?>" id="prevStatusBadge">
                                <?= ($data['status'] ?? 1) == 1 ? 'Aktif' : 'Nonaktif' ?>
                            </span>
                        </div>
                    </div>
                    
                    <div style="margin-top:1rem; padding:1rem; background:rgba(46,139,87,0.08); border:1px solid rgba(46,139,87,0.2); border-radius:var(--radius-md); font-size:.78rem; color:var(--text-muted);">
                        <i class="fa-solid fa-circle-check" style="color:var(--success);"></i>
                        <strong style="color:var(--success);"> Tips:</strong> Super Admin memiliki akses penuh termasuk manajemen user dan pengaturan sistem. Berikan role ini hanya kepada orang yang sangat dipercaya.
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // ===== PERMISSIONS MAP =====
    const permissionsMap = {
        superadmin: [
            { action: 'Kelola semua konten', allowed: true },
            { action: 'Manajemen user', allowed: true },
            { action: 'Pengaturan sistem', allowed: true },
            { action: 'Log keamanan', allowed: true },
            { action: 'Hapus data permanen', allowed: true },
            { action: 'Akses API', allowed: true },
        ],
        admin: [
            { action: 'Kelola semua konten', allowed: true },
            { action: 'Manajemen user', allowed: false },
            { action: 'Pengaturan sistem', allowed: false },
            { action: 'Log keamanan', allowed: true },
            { action: 'Hapus data permanen', allowed: true },
            { action: 'Akses API', allowed: false },
        ],
        editor: [
            { action: 'Kelola semua konten', allowed: false },
            { action: 'Tulis & edit konten sendiri', allowed: true },
            { action: 'Manajemen user', allowed: false },
            { action: 'Pengaturan sistem', allowed: false },
            { action: 'Hapus data permanen', allowed: false },
            { action: 'Akses API', allowed: false },
        ]
    };
    
    function renderPermissions(role) {
        const grid = document.getElementById('permissionsGrid');
        const perms = permissionsMap[role] || permissionsMap.editor;
        grid.innerHTML = perms.map(p => `
            <div class="perm-item">
                <i class="fa-solid ${p.allowed ? 'fa-circle-check yes' : 'fa-circle-xmark no'}"></i>
                <span>${p.action}</span>
            </div>
        `).join('');
    }
    
    const roleSelect = document.getElementById('f_role');
    renderPermissions(roleSelect?.value || 'admin');
    roleSelect?.addEventListener('change', function() {
        renderPermissions(this.value);
        const prevBadge = document.getElementById('prevRoleBadge');
        const icons = { superadmin: 'fa-crown', admin: 'fa-user-shield', editor: 'fa-pen-nib' };
        prevBadge.innerHTML = `<span class="role-badge ${this.value}"><i class="fa-solid ${icons[this.value]}"></i> ${this.value.charAt(0).toUpperCase() + this.value.slice(1)}</span>`;
    });
    
    // ===== LIVE PREVIEW =====
    const namaInput = document.getElementById('f_nama');
    const usernameInputs = document.querySelector('input[name="username"]');
    const emailInput = document.querySelector('input[name="email"]');
    const avatarInput = document.getElementById('avatarInput');
    const avatarPreview = document.getElementById('avatarPreview');
    const prevAvatarImg = document.getElementById('prevAvatarImg');
    const prevAvatarInitial = document.getElementById('prevAvatarInitial');
    const prevNama = document.getElementById('prevNama');
    const prevUsername = document.getElementById('prevUsername');
    const prevEmail = document.getElementById('prevEmail');
    
    function updateInitials(name) {
        const initial = (name || 'U').charAt(0).toUpperCase();
        const avatarInitials = document.getElementById('avatarInitials');
        if (avatarInitials) avatarInitials.textContent = initial;
        if (prevAvatarInitial) prevAvatarInitial.textContent = initial;
    }
    
    namaInput?.addEventListener('input', e => {
        prevNama.textContent = e.target.value || 'Nama User';
        updateInitials(e.target.value);
    });
    usernameInputs?.addEventListener('input', e => {
        prevUsername.textContent = e.target.value || 'username';
    });
    emailInput?.addEventListener('input', e => {
        if (prevEmail) prevEmail.textContent = e.target.value || '';
    });
    
    // Avatar upload
    avatarInput?.addEventListener('change', function(e) {
        if (e.target.files.length) {
            const reader = new FileReader();
            reader.onload = function(ev) {
                avatarPreview.innerHTML = `<img src="${ev.target.result}" alt="Avatar">`;
                document.getElementById('prevAvatarWrap').innerHTML = `<img src="${ev.target.result}" style="width:100%; height:100%; object-fit:cover;">`;
            };
            reader.readAsDataURL(e.target.files[0]);
        }
    });
    
    // ===== PASSWORD STRENGTH =====
    const passInput = document.getElementById('passwordInput');
    const passConfirm = document.getElementById('passwordConfirm');
    const meter = document.getElementById('strengthMeter');
    const label = document.getElementById('strengthLabel');
    const matchStatus = document.getElementById('matchStatus');
    
    function checkStrength(password) {
        let score = 0;
        if (password.length === 0) return { score: 0, text: 'Masukkan password' };
        if (password.length >= 6) score++;
        if (password.length >= 10) score++;
        if (/[A-Z]/.test(password) && /[a-z]/.test(password)) score++;
        if (/\d/.test(password) && /[^A-Za-z0-9]/.test(password)) score++;
        
        const labels = ['Sangat Lemah', 'Lemah', 'Cukup', 'Kuat', 'Sangat Kuat'];
        return { score, text: labels[score] };
    }
    
    function updateStrength() {
        const { score, text } = checkStrength(passInput.value);
        meter.className = `strength-meter strength-${score}`;
        label.textContent = text;
    }
    
    function checkMatch() {
        if (!passConfirm.value) {
            matchStatus.textContent = '';
            matchStatus.style.color = 'var(--text-muted)';
            return;
        }
        if (passInput.value === passConfirm.value) {
            matchStatus.innerHTML = '<i class="fa-solid fa-circle-check"></i> Password cocok';
            matchStatus.style.color = 'var(--success)';
        } else {
            matchStatus.innerHTML = '<i class="fa-solid fa-circle-xmark"></i> Password tidak cocok';
            matchStatus.style.color = 'var(--danger)';
        }
    }
    
    passInput?.addEventListener('input', () => { updateStrength(); checkMatch(); });
    passConfirm?.addEventListener('input', checkMatch);
    
    // ===== STATUS TOGGLE PREVIEW =====
    document.querySelector('input[name="status"]')?.addEventListener('change', function() {
        const badge = document.getElementById('prevStatusBadge');
        if (this.checked) {
            badge.className = 'status-badge active';
            badge.textContent = 'Aktif';
        } else {
            badge.className = 'status-badge inactive';
            badge.textContent = 'Nonaktif';
        }
    });
});

function togglePassword(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}

function removeAvatar() {
    Swal.fire({
        icon: 'warning',
        title: 'Hapus Foto?',
        text: 'Foto profil akan dihapus dan kembali ke inisial.',
        showCancelButton: true,
        confirmButtonColor: '#e74c3c',
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('avatarPreview').innerHTML = '<span id="avatarInitials">U</span>';
            document.getElementById('prevAvatarWrap').innerHTML = '<span style="color:#fff; font-size:3rem; font-weight:900;" id="prevAvatarInitial">U</span>';
            document.getElementById('avatarInput').value = '';
        }
    });
}
</script>
<?php else: ?>
<!-- ===== FILTER BAR ===== -->
<?php if (!empty($items)): ?>
<div class="users-filter-bar">
    <div class="form-group">
        <label><i class="fa-solid fa-search"></i> Cari User</label>
        <input type="text" id="userSearch" class="form-control" placeholder="Nama, username, atau email...">
    </div>
    <div class="form-group">
        <label><i class="fa-solid fa-user-tag"></i> Role</label>
        <select id="roleFilter" class="form-control">
            <option value="all">Semua Role</option>
            <option value="superadmin">👑 Super Admin</option>
            <option value="admin">🛡️ Admin</option>
            <option value="editor">✏️ Editor</option>
        </select>
    </div>
    <div class="form-group">
        <label><i class="fa-solid fa-circle-check"></i> Status</label>
        <select id="statusFilter" class="form-control">
            <option value="all">Semua Status</option>
            <option value="active">Aktif</option>
            <option value="inactive">Nonaktif</option>
        </select>
    </div>
    <div class="form-group">
        <button type="button" class="btn btn-sm btn-secondary" onclick="resetFilters()" style="width:100%;">
            <i class="fa-solid fa-rotate-left"></i> Reset
        </button>
    </div>
</div>

<!-- BULK ACTIONS BAR -->
<div class="bulk-actions-bar" id="bulkActionsBar">
    <div class="bulk-info">
        <i class="fa-solid fa-check-double" style="color:var(--crimson);"></i>
        <span><span class="bulk-count" id="bulkCount">0</span> user dipilih</span>
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

<!-- ===== LIST VIEW ===== -->
<?php if (empty($items)): ?>
<div class="users-empty">
    <div class="users-empty-icon"><i class="fa-solid fa-users-gear"></i></div>
    <h3>Belum Ada User</h3>
    <p>Tambahkan user admin pertama untuk mulai mengelola website</p>
    <a href="?action=add" class="btn btn-primary"><i class="fa-solid fa-user-plus"></i> Tambah User Pertama</a>
</div>
<?php else: ?>
<div class="panel">
    <div class="panel-body no-padding">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th style="width:40px;">
                            <input type="checkbox" id="selectAll" style="width:16px; height:16px; accent-color:var(--crimson);">
                        </th>
                        <th>User</th>
                        <th style="width:130px;">Role</th>
                        <th style="width:100px;">Status</th>
                        <th style="width:180px;">Terakhir Login</th>
                        <th style="width:130px;">Dibuat</th>
                        <th style="width:150px;">Aksi</th>
                    </tr>
                </thead>
                <tbody id="usersTableBody">
                    <?php foreach ($items as $item): 
                        $token = hash_hmac('sha256', $item['id'], $_SESSION['csrf_token']);
                        $initials = strtoupper(substr($item['nama'], 0, 1)) . (strpos($item['nama'], ' ') !== false ? strtoupper(substr($item['nama'], strpos($item['nama'], ' ') + 1, 1)) : '');
                        $isActive = ($item['status'] ?? 1) == 1;
                        $isSelf = $item['id'] == $_SESSION['admin_id'];
                        $roleIcons = ['superadmin' => 'fa-crown', 'admin' => 'fa-user-shield', 'editor' => 'fa-pen-nib'];
                        $roleEmoji = ['superadmin' => '👑', 'admin' => '🛡️', 'editor' => '✏️'];
                    ?>
                    <tr class="user-row" 
                         data-search="<?= strtolower(htmlspecialchars(($item['nama'] ?? '') . ' ' . ($item['username'] ?? '') . ' ' . ($item['email'] ?? ''))) ?>"
                         data-role="<?= htmlspecialchars($item['role'] ?? '') ?>"
                         data-status="<?= $isActive ? 'active' : 'inactive' ?>">
                        <td>
                            <input type="checkbox" class="row-checkbox" value="<?= $item['id'] ?>" <?= $isSelf ? 'disabled' : '' ?> style="width:16px; height:16px; accent-color:var(--crimson);">
                        </td>
                        <td>
                            <div class="user-cell">
                                <div class="user-avatar-sm">
                                    <?php if (!empty($item['avatar'])): ?>
                                        <img src="../<?= htmlspecialchars($item['avatar']) ?>" alt="<?= htmlspecialchars($item['nama']) ?>">
                                    <?php else: ?>
                                        <?= htmlspecialchars($initials) ?>
                                    <?php endif; ?>
                                </div>
                                <div class="user-cell-info">
                                    <div class="user-cell-name">
                                        <?= htmlspecialchars($item['nama']) ?>
                                        <?php if ($isSelf): ?><span class="you-badge">ANDA</span><?php endif; ?>
                                    </div>
                                    <div class="user-cell-meta">
                                        <i class="fa-solid fa-at"></i>
                                        <code style="color:var(--sky-blue);"><?= htmlspecialchars($item['username']) ?></code>
                                        <?php if (!empty($item['email'])): ?>
                                        · <i class="fa-solid fa-envelope" style="color:var(--crimson);"></i>
                                        <span><?= htmlspecialchars($item['email']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="role-badge <?= htmlspecialchars($item['role']) ?>">
                                <i class="fa-solid <?= $roleIcons[$item['role']] ?? 'fa-user' ?>"></i>
                                <?= ucfirst(htmlspecialchars($item['role'])) ?>
                            </span>
                        </td>
                        <td>
                            <?php if (!$isSelf): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="toggle_status" value="1">
                                <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                <input type="hidden" name="status" value="<?= $isActive ? 0 : 1 ?>">
                                <label class="toggle-switch" title="<?= $isActive ? 'Nonaktifkan' : 'Aktifkan' ?>">
                                    <input type="checkbox" <?= $isActive ? 'checked' : '' ?> onchange="this.form.submit()">
                                    <span class="toggle-slider"></span>
                                </label>
                            </form>
                            <?php else: ?>
                            <span class="status-badge active">Aktif</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="last-login-cell">
                                <?php if (!empty($item['last_login'])): ?>
                                    <div><i class="fa-solid fa-clock"></i> <?= date('d M Y, H:i', strtotime($item['last_login'])) ?></div>
                                    <?php if (!empty($item['last_ip'])): ?>
                                    <div style="font-size:.68rem; opacity:.7;"><i class="fa-solid fa-network-wired"></i> <?= htmlspecialchars($item['last_ip']) ?></div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <em>Belum pernah login</em>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td style="color:var(--text-muted); font-size:.85rem;">
                            <?= !empty($item['dibuat']) ? date('d M Y', strtotime($item['dibuat'])) : '-' ?>
                        </td>
                        <td>
                            <div class="table-actions">
                                <a href="?edit=<?= $item['id'] ?>&token=<?= $token ?>" class="table-action-btn edit" title="Edit"><i class="fa-solid fa-pen"></i></a>
                                <?php if (!$isSelf): ?>
                                <a href="?delete=<?= $item['id'] ?>&token=<?= $token ?>" class="table-action-btn delete" onclick="return confirm('Yakin ingin menghapus user <?= htmlspecialchars($item['username']) ?>? Tindakan ini tidak dapat dibatalkan.')" title="Hapus"><i class="fa-solid fa-trash"></i></a>
                                <?php else: ?>
                                <button type="button" class="table-action-btn" disabled title="Tidak dapat menghapus akun sendiri" style="opacity:.3; cursor:not-allowed;"><i class="fa-solid fa-ban"></i></button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div style="text-align:center; margin-top:1rem; padding:.8rem; background:rgba(138,21,56,.05); border-radius:8px; color:var(--text-muted); font-size:.88rem;">
    Menampilkan <strong id="userCount" style="color:var(--crimson);"><?= $total_users ?></strong> dari <strong><?= $total_users ?></strong> user terdaftar
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('userSearch');
    const roleFilter = document.getElementById('roleFilter');
    const statusFilter = document.getElementById('statusFilter');
    const countEl = document.getElementById('userCount');
    const selectAll = document.getElementById('selectAll');
    const bulkBar = document.getElementById('bulkActionsBar');
    const bulkCount = document.getElementById('bulkCount');
    const bulkIdsContainer = document.getElementById('bulkIdsContainer');
    
    function filterUsers() {
        const term = (searchInput?.value || '').toLowerCase();
        const role = roleFilter?.value || 'all';
        const status = statusFilter?.value || 'all';
        
        const rows = document.querySelectorAll('.user-row');
        let visibleCount = 0;
        
        rows.forEach(row => {
            const searchText = row.dataset.search || '';
            const rowRole = row.dataset.role;
            const rowStatus = row.dataset.status;
            
            const matchSearch = !term || searchText.includes(term);
            const matchRole = role === 'all' || rowRole === role;
            const matchStatus = status === 'all' || rowStatus === status;
            
            if (matchSearch && matchRole && matchStatus) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        
        if (countEl) countEl.textContent = visibleCount;
    }
    
    window.resetFilters = function() {
        if (searchInput) searchInput.value = '';
        if (roleFilter) roleFilter.value = 'all';
        if (statusFilter) statusFilter.value = 'all';
        filterUsers();
    };
    
    if (searchInput) searchInput.addEventListener('input', filterUsers);
    if (roleFilter) roleFilter.addEventListener('change', filterUsers);
    if (statusFilter) statusFilter.addEventListener('change', filterUsers);
    
    // Bulk selection
    function updateBulkBar() {
        const checked = document.querySelectorAll('.row-checkbox:checked');
        if (checked.length > 0) {
            bulkBar.classList.add('active');
            bulkCount.textContent = checked.length;
            bulkIdsContainer.innerHTML = '';
            checked.forEach(cb => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'bulk_ids[]';
                input.value = cb.value;
                bulkIdsContainer.appendChild(input);
            });
        } else {
            bulkBar.classList.remove('active');
        }
    }
    
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            document.querySelectorAll('.row-checkbox:not(:disabled)').forEach(cb => cb.checked = this.checked);
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
            icon: 'warning',
            title: 'Hapus ' + count + ' User?',
            text: 'Tindakan ini tidak dapat dibatalkan.',
            showCancelButton: true,
            confirmButtonColor: '#e74c3c',
            confirmButtonText: 'Ya, Hapus',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('bulkForm').submit();
            }
        });
    };
});
</script>
<?php endif; ?>
<?php endif; ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    if ($('#userForm').length && $.fn.select2) {
        $('.select2-premium').select2({ 
            dropdownParent: $('#userForm'),
            minimumResultsForSearch: Infinity
        });
    }
    
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