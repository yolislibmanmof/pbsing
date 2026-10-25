<?php
require_once '../includes/config.php';
require_once '../includes/security.php';

// ===== CSRF PROTECTION =====
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];
check_ip_security($pdo);

$error = "";
$warning = "";

// Cek apakah sudah login
if (isset($_SESSION['admin_id'])) {
    header("Location: dashboard.php");
    exit;
}

// Cek brute force
$ip = $_SERVER['REMOTE_ADDR'];
$stmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)");
$stmt->execute([$ip]);
$attemptCount = $stmt->fetchColumn();

if ($attemptCount >= 5) {
    $warning = "Terlalu banyak percobaan login. Silakan tunggu 10 menit atau hubungi administrator.";
}

// Rate limit info
$remainingAttempts = max(0, 5 - $attemptCount);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($warning)) {
    // Verifikasi CSRF
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = "Sesi tidak valid. Silakan refresh halaman.";
    } else {
        $user = sanitize($_POST['username'] ?? '');
        $pass = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']);

        // Validasi input
        if (empty($user) || empty($pass)) {
            $error = "Username dan password wajib diisi.";
        } else {
            // ✅ FIXED: Menghapus "AND active = 1" karena kolom 'active' tidak ada di tabel 'users'
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$user]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($pass, $admin['password'])) {
                // Login berhasil
                log_login_attempt($pdo, $user, true);
                
                session_regenerate_id(true); // Cegah session fixation
                
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_name'] = $admin['nama'];
                $_SESSION['admin_role'] = $admin['role'];
                $_SESSION['login_time'] = time();
                
                if ($remember) {
                    // Extend session
                    $lifetime = 30 * 24 * 60 * 60; // 30 hari
                    session_set_cookie_params($lifetime);
                    setcookie(session_name(), session_id(), time() + $lifetime, '/');
                }
                
                // ✅ FIXED: Menghapus query UPDATE last_login/last_ip karena kolom tersebut tidak ada di skema database
                
                // Log aktivitas
                $stmt = $pdo->prepare("INSERT INTO admin_logs (user_id, user_name, action, detail, ip) VALUES (?, ?, 'LOGIN', 'Login berhasil', ?)");
                $stmt->execute([$admin['id'], $admin['nama'], $ip]);
                
                header("Location: dashboard.php");
                exit;
            } else {
                log_login_attempt($pdo, $user, false);
                $remainingAttempts--;
                
                if ($remainingAttempts > 0) {
                    $error = "Username atau password salah! Sisa percobaan: <strong>$remainingAttempts</strong>";
                } else {
                    $warning = "Terlalu banyak percobaan login gagal. Akun dikunci sementara selama 10 menit.";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Login Admin - PBSI UMMaumere Command Center</title>
    <link rel="stylesheet" href="assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🎓</text></svg>">
</head>
<body class="auth-body">
    <div class="auth-wrapper">
        <!-- Brand Panel -->
        <div class="auth-brand">
            <div class="auth-brand-content">
                <div class="auth-brand-icon">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
                <h1>
                    PBSI <em>Command Center</em>
                </h1>
                <p>Sistem Manajemen Terpadu untuk Program Studi Bahasa dan Sastra Inggris Universitas Muhammadiyah Maumere.</p>
                <ul class="auth-features">
                    <li><i class="fa-solid fa-check"></i> Manajemen konten lengkap</li>
                    <li><i class="fa-solid fa-check"></i> Dashboard analitik real-time</li>
                    <li><i class="fa-solid fa-check"></i> Keamanan enterprise-grade</li>
                    <li><i class="fa-solid fa-check"></i> Audit log aktivitas lengkap</li>
                </ul>
            </div>
            <div class="auth-footer">
                © <?= date('Y') ?> PBSI UMMaumere. All rights reserved.
            </div>
        </div>

        <!-- Login Form -->
        <div class="auth-box">
            <div class="auth-box-header">
                <h2><i class="fa-solid fa-lock"></i> Masuk Sistem</h2>
                <p>Silakan masuk menggunakan kredensial admin Anda</p>
            </div>

            <?php if ($error): ?>
                <div class="alert-error" role="alert">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <span><?= $error ?></span>
                </div>
            <?php endif; ?>

            <?php if ($warning): ?>
                <div class="alert-warning" role="alert">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <span><?= $warning ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" id="loginForm" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                
                <div class="form-group">
                    <label for="username">Username <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-user input-icon"></i>
                        <input type="text" 
                               id="username" 
                               name="username" 
                               placeholder="Masukkan username" 
                               required 
                               autofocus
                               autocomplete="username"
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-key input-icon"></i>
                        <input type="password" 
                               id="password" 
                               name="password" 
                               placeholder="Masukkan password" 
                               required
                               autocomplete="current-password">
                        <button type="button" class="toggle-pass" onclick="togglePassword()" aria-label="Tampilkan password">
                            <i class="fa-solid fa-eye" id="toggleIcon"></i>
                        </button>
                    </div>
                </div>

                <div class="form-options">
                    <label class="checkbox-wrap">
                        <input type="checkbox" name="remember" id="remember">
                        <span>Ingat saya selama 30 hari</span>
                    </label>
                    <a href="#" class="forgot-link" onclick="alert('Hubungi administrator untuk reset password.'); return false;">Lupa password?</a>
                </div>

                <button type="submit" id="submitBtn" <?= !empty($warning) ? 'disabled' : '' ?>>
                    <i class="fa-solid fa-right-to-bracket"></i>
                    <span>Masuk ke Sistem</span>
                </button>
            </form>

            <div class="security-info">
                <i class="fa-solid fa-shield-halved"></i>
                <div>
                    <strong style="color:var(--sky-blue);display:block;margin-bottom:.2rem;">Koneksi Aman</strong>
                    Login Anda dilindungi dengan enkripsi SSL, CSRF protection, dan brute-force detection. IP Anda: <code style="background:rgba(255,255,255,.1);padding:2px 6px;border-radius:3px;"><?= htmlspecialchars($ip) ?></code>
                </div>
            </div>
        </div>
    </div>

    <script>
    function togglePassword() {
        const passInput = document.getElementById('password');
        const icon = document.getElementById('toggleIcon');
        
        if (passInput.type === 'password') {
            passInput.type = 'text';
            icon.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            passInput.type = 'password';
            icon.classList.replace('fa-eye-slash', 'fa-eye');
        }
    }

    // Loading state saat submit
    document.getElementById('loginForm').addEventListener('submit', function(e) {
        const btn = document.getElementById('submitBtn');
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Memverifikasi...';
        btn.disabled = true;
    });

    // Capslock warning
    document.getElementById('password').addEventListener('keyup', function(e) {
        if (e.getModifierState && e.getModifierState('CapsLock')) {
            if (!document.getElementById('capslock-warning')) {
                const warning = document.createElement('div');
                warning.id = 'capslock-warning';
                warning.className = 'alert-warning';
                warning.style.marginTop = '.5rem';
                warning.style.marginBottom = '0';
                warning.style.fontSize = '.82rem';
                warning.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> Caps Lock aktif!';
                this.parentElement.parentElement.appendChild(warning);
            }
        } else {
            const warning = document.getElementById('capslock-warning');
            if (warning) warning.remove();
        }
    });
    </script>
</body>
</html>