<?php
require_once 'config.php';

// FUNGSI SANITIZE YANG 100% AMAN DARI ERROR PHP 8.2
function sanitize($data) {
    if ($data === null || $data === '') {
        return '';
    }
    return htmlspecialchars(trim((string)$data), ENT_QUOTES, 'UTF-8');
}

function check_ip_security($pdo) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $stmt = $pdo->prepare("SELECT * FROM blocked_ips WHERE ip = ? AND blocked_until > NOW()");
    $stmt->execute([$ip]);
    if ($stmt->fetch()) {
        die("<h1>403 Forbidden</h1><p>IP Anda diblokir sementara karena aktivitas mencurigakan.</p>");
    }
}

function log_login_attempt($pdo, $username, $success) {
    $ip = $_SERVER['REMOTE_ADDR'];
    if (!$success) {
        $stmt = $pdo->prepare("INSERT INTO login_attempts (ip, username) VALUES (?, ?)");
        $stmt->execute([$ip, $username]);
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)");
        $stmt->execute([$ip]);
        $attempts = $stmt->fetchColumn();
        
        if ($attempts >= 5) {
            $block_until = date('Y-m-d H:i:s', strtotime('+30 minutes'));
            $stmt = $pdo->prepare("INSERT INTO blocked_ips (ip, reason, blocked_until) VALUES (?, 'Brute Force', ?)");
            $stmt->execute([$ip, $block_until]);
        }
    } else {
        $pdo->prepare("DELETE FROM login_attempts WHERE ip = ?")->execute([$ip]);
    }
}

function require_login() {
    if (!isset($_SESSION['admin_id'])) {
        header("Location: " . ADMIN_URL . "login.php");
        exit;
    }
}
?>