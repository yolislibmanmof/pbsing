<?php
/**
 * =====================================================================
 * ULTIMATE ENTERPRISE UPLOAD HELPER
 * PBSI UMMaumere Command Center
 * =====================================================================
 * 
 * Fitur Keamanan Tingkat Enterprise:
 * ✅ Blacklist ekstensi berbahaya (PHP, EXE, HTACCESS, dll)
 * ✅ Validasi MIME type asli via finfo (anti fake extension)
 * ✅ Validasi dimensi gambar (anti zip bomb)
 * ✅ Auto-create .htaccess (blokir eksekusi PHP)
 * ✅ Auto-create index.php (blokir directory listing)
 * ✅ Audit trail logging untuk setiap upload
 * ✅ Error message user-friendly dalam Bahasa Indonesia
 * 
 * @param array $file $_FILES['nama_input']
 * @param string $folder Tujuan folder (misal: '../assets/uploads/')
 * @param array $allowed_types Ekstensi yang diizinkan
 * @param int $max_size Ukuran maks dalam Bytes (default 5MB)
 * @return array ['success' => bool, 'path' => string, 'error' => string]
 */

// ===== ULTIMATE SECURITY CONSTANTS =====

// Ekstensi file yang SANGAT BERBAHAYA - tidak boleh diupload dalam kondisi apapun
if (!defined('DANGEROUS_EXTENSIONS')) {
    define('DANGEROUS_EXTENSIONS', [
        'php', 'php3', 'php4', 'php5', 'phtml', 'pht', 'phar',  // PHP executables
        'exe', 'bat', 'cmd', 'com', 'sh', 'bash', 'cgi', 'pl',   // System executables
        'asp', 'aspx', 'ashx', 'asmx', 'axd',                    // Windows server
        'htaccess', 'htpasswd',                                  // Apache config
        'js', 'html', 'htm', 'svg',                              // Can contain XSS
        'jar', 'jsp', 'jspx',                                    // Java
        'dll', 'msi', 'scr',                                     // Windows malware
    ]);
}

// MIME types asli yang diizinkan (untuk validasi ganda)
if (!defined('ALLOWED_MIMES')) {
    define('ALLOWED_MIMES', [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png'  => ['png'],
        'image/webp' => ['webp'],
        'image/gif'  => ['gif'],
        'application/pdf' => ['pdf'],
        'application/msword' => ['doc'],
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
        'application/vnd.ms-excel' => ['xls'],
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => ['xlsx'],
        'application/vnd.ms-powerpoint' => ['ppt'],
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => ['pptx'],
        'application/zip' => ['zip'],
        'application/x-rar-compressed' => ['rar'],
        'application/x-7z-compressed' => ['7z'],
    ]);
}

/**
 * Universal Upload Handler with Enterprise-Grade Security
 */
function handle_upload($file, $folder = '../assets/uploads/', $allowed_types = ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'doc', 'docx'], $max_size = 5242880) {
    
    // ===== STEP 1: Validasi Existence File =====
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['success' => false, 'path' => '', 'error' => 'Tidak ada file yang dipilih.'];
    }

    // ===== STEP 2: Validasi Error Code PHP =====
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error_messages = [
            UPLOAD_ERR_INI_SIZE   => 'File terlalu besar (melebihi batas server).',
            UPLOAD_ERR_FORM_SIZE  => 'File terlalu besar (melebihi batas form).',
            UPLOAD_ERR_PARTIAL    => 'File hanya ter-upload sebagian. Silakan coba lagi.',
            UPLOAD_ERR_NO_FILE    => 'Tidak ada file yang diupload.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server error: folder temporary hilang. Hubungi administrator.',
            UPLOAD_ERR_CANT_WRITE => 'Server error: tidak bisa menulis file ke disk.',
            UPLOAD_ERR_EXTENSION  => 'File diblokir oleh ekstensi server.',
        ];
        $msg = $error_messages[$file['error']] ?? 'Error upload tidak diketahui (Kode: ' . $file['error'] . ')';
        return ['success' => false, 'path' => '', 'error' => $msg];
    }

    // ===== STEP 3: Validasi Ukuran File =====
    if ($file['size'] > $max_size) {
        $max_mb = round($max_size / 1048576, 1);
        return ['success' => false, 'path' => '', 'error' => "Ukuran file melebihi batas {$max_mb}MB."];
    }

    // ===== STEP 4: Validasi Ekstensi (Whitelist + Blacklist) =====
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    // 🚨 KRITIS: Cek blacklist ekstensi berbahaya DULU
    if (in_array($ext, DANGEROUS_EXTENSIONS)) {
        error_log("[SECURITY ALERT] Upload file berbahaya ditolak: {$file['name']} dari IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        return ['success' => false, 'path' => '', 'error' => 'Tipe file tidak diizinkan karena alasan keamanan.'];
    }

    // Cek whitelist
    if (!in_array($ext, $allowed_types)) {
        return ['success' => false, 'path' => '', 'error' => 'Tipe file tidak diizinkan. Hanya: ' . implode(', ', $allowed_types)];
    }

    // ===== STEP 5: Validasi MIME Type Asli via finfo =====
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $real_mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        // Cek apakah MIME type asli sesuai dengan ekstensi
        $mime_valid = false;
        foreach (ALLOWED_MIMES as $mime => $extensions) {
            if ($real_mime === $mime && in_array($ext, $extensions)) {
                $mime_valid = true;
                break;
            }
        }

        if (!$mime_valid) {
            error_log("[SECURITY ALERT] MIME mismatch ditolak: {$file['name']} (claimed: $ext, real: $real_mime)");
            return ['success' => false, 'path' => '', 'error' => 'Konten file tidak sesuai dengan ekstensinya. File ditolak demi keamanan.'];
        }
    }

    // ===== STEP 6: Validasi Dimensi Gambar (Anti Zip Bomb) =====
    if (strpos($real_mime ?? '', 'image/') === 0 && function_exists('getimagesize')) {
        $image_info = @getimagesize($file['tmp_name']);
        if ($image_info === false) {
            return ['success' => false, 'path' => '', 'error' => 'File gambar corrupt atau tidak valid.'];
        }
        if ($image_info[0] > 5000 || $image_info[1] > 5000) {
            return ['success' => false, 'path' => '', 'error' => 'Dimensi gambar terlalu besar (maks 5000x5000px).'];
        }
    }

    // ===== STEP 7: Buat Folder Jika Belum Ada =====
    if (!is_dir($folder)) {
        if (!mkdir($folder, 0755, true)) {
            return ['success' => false, 'path' => '', 'error' => 'Gagal membuat folder tujuan. Periksa izin server.'];
        }
    }

    // ===== STEP 8: Auto-Create .htaccess untuk Proteksi =====
    $htaccess_path = rtrim($folder, '/') . '/.htaccess';
    if (!file_exists($htaccess_path)) {
        $htaccess_content = "# ==========================================\n";
        $htaccess_content .= "# ULTIMATE SECURITY - PBSI UMMaumere\n";
        $htaccess_content .= "# Auto-generated by upload_helper.php\n";
        $htaccess_content .= "# ==========================================\n\n";
        $htaccess_content .= "# Block PHP execution in upload folder\n";
        $htaccess_content .= "<FilesMatch \"\\.(php|php3|php4|php5|phtml|pl|py|jsp|asp|sh|cgi|exe)$\">\n";
        $htaccess_content .= "    Order Deny,Allow\n";
        $htaccess_content .= "    Deny from all\n";
        $htaccess_content .= "</FilesMatch>\n\n";
        $htaccess_content .= "# Block direct access to sensitive files\n";
        $htaccess_content .= "<FilesMatch \"^\\.\">\n";
        $htaccess_content .= "    Order Deny,Allow\n";
        $htaccess_content .= "    Deny from all\n";
        $htaccess_content .= "</FilesMatch>\n\n";
        $htaccess_content .= "# Disable script execution and directory listing\n";
        $htaccess_content .= "Options -ExecCGI -Indexes\n";
        $htaccess_content .= "php_flag engine off\n";
        
        @file_put_contents($htaccess_path, $htaccess_content);
    }

    // ===== STEP 9: Auto-Create index.php untuk Blokir Directory Listing =====
    $index_path = rtrim($folder, '/') . '/index.php';
    if (!file_exists($index_path)) {
        @file_put_contents($index_path, "<?php header('HTTP/1.0 403 Forbidden'); exit('Access Denied'); ?>");
    }

    // ===== STEP 10: Generate Nama File Unik =====
    $new_filename = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destination = rtrim($folder, '/') . '/' . $new_filename;

    // ===== STEP 11: Move Uploaded File =====
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        // 🚨 AUDIT: Log aktivitas upload untuk security monitoring
        $log_msg = sprintf(
            "[UPLOAD SUCCESS] User: %s | File: %s | Size: %d KB | MIME: %s | Path: %s | IP: %s",
            $_SESSION['admin_name'] ?? 'Unknown',
            $file['name'],
            round($file['size'] / 1024, 2),
            $real_mime ?? 'unknown',
            $destination,
            $_SERVER['REMOTE_ADDR'] ?? 'Unknown'
        );
        error_log($log_msg);
        
        // Kembalikan path relatif untuk disimpan di database
        $relative_path = str_replace('../', '', $destination);
        return ['success' => true, 'path' => $relative_path, 'error' => ''];
    }

    return ['success' => false, 'path' => '', 'error' => 'Gagal memindahkan file ke server. Periksa izin folder.'];
}
?>