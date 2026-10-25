<?php
session_start();
date_default_timezone_set('Asia/Makassar');

// ===== KONEKSI DATABASE (Default Laragon) =====
$host = "localhost";
$db   = "bahasa_inggris";
$user = "root";
$pass = "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

// ===== AUTO-DETECT BASE URL (Anti 404 saat folder diganti nama) =====
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
$host_url = $_SERVER['HTTP_HOST'];
$script_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
$parts = explode('/', trim($script_dir, '/'));
$last = end($parts);
if ($last === 'public' || $last === 'admin' || $last === 'includes') {
    array_pop($parts);
}
$base_path = implode('/', $parts);
define('BASE_URL', $protocol . '://' . $host_url . ($base_path ? '/' . $base_path : '') . '/');
define('PUBLIC_URL', BASE_URL . 'public/');
define('ADMIN_URL', BASE_URL . 'admin/');
// ===== PATH FISIK SERVER (Untuk filemtime, upload, dll) =====
define('ROOT_DIR',   dirname(__DIR__));
define('PUBLIC_DIR', ROOT_DIR . '/public');
define('ADMIN_DIR',  ROOT_DIR . '/admin');
define('INCLUDES_DIR', __DIR__);
?>