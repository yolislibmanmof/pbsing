<?php
require_once '../includes/config.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';
check_ip_security($pdo);
$slug = $_GET['slug'] ?? '';
$stmt = $pdo->prepare("SELECT * FROM pages WHERE slug = ? AND status = 1");
$stmt->execute([$slug]);
$page = $stmt->fetch();
$page_title = $page ? $page['judul'] : '404';
require_once '../includes/public_header.php';
if (!$page): ?>
<section class="page-hero"><h1>404</h1><p>Halaman tidak ditemukan.</p></section>
<section class="section" style="text-align:center;"><a class="btn-primary" href="<?= PUBLIC_URL ?>index.php">Kembali ke Beranda</a></section>
<?php else: ?>
<section class="page-hero"><h1><?= sanitize($page['judul']) ?></h1><p><?= sanitize($setting['prodi_name']) ?> — <?= sanitize($setting['kampus']) ?></p></section>
<section class="section"><div class="page-content"><?= $page['konten'] ?></div></section>
<?php endif; require_once '../includes/public_footer.php'; ?>