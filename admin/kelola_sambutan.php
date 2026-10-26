<?php
require_once '../includes/config.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';
require_once 'includes/upload_helper.php';
require_login();

$page_title = 'Kelola Sambutan Kaprodi';
$current_page = 'sambutan';
$table = 'sambutan';

// ===== AUTO-MIGRATION (Self-Healing Database) =====
// Otomatis membuat kolom baru jika belum ada di tabel sambutan.
// Aman dijalankan berulang kali (idempotent) - tidak perlu ALTER TABLE manual!
$required_cols = [
    'nidn'          => "varchar(50) DEFAULT ''",
    'email'         => "varchar(100) DEFAULT ''",
    'link_scholar'  => "varchar(255) DEFAULT ''",
    'link_linkedin' => "varchar(255) DEFAULT ''",
    'video_url'     => "varchar(255) DEFAULT ''",
];
$existing_cols = $pdo->query("SHOW COLUMNS FROM $table")->fetchAll(PDO::FETCH_COLUMN);
foreach ($required_cols as $col => $definition) {
    if (!in_array($col, $existing_cols, true)) {
        $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$col` $definition");
    }
}

// ===== FETCH + NORMALISASI DATA =====
$id = 1; // Sambutan hanya 1 record
$stmt = $pdo->prepare("SELECT * FROM $table WHERE id = ?");
$stmt->execute([$id]);
$fetched = $stmt->fetch();

$data_defaults = [
    'id'=>1, 'nama'=>'', 'jabatan'=>'Ketua Program Studi',
    'nidn'=>'', 'foto'=>'', 'isi'=>'', 'tanda_tangan'=>'',
    'email'=>'', 'link_scholar'=>'', 'link_linkedin'=>'', 'video_url'=>''
];

if ($fetched) {
    // ✅ FIX #1: Konversi semua nilai NULL → '' agar htmlspecialchars() tidak deprecated
    $clean = [];
    foreach ($fetched as $key => $value) {
        $clean[$key] = ($value === null) ? '' : $value;
    }
    // ✅ FIX #2: array_merge menutup "Undefined array key" untuk kolom yang belum ada di DB
    $data = array_merge($data_defaults, $clean);
} else {
    $data = $data_defaults;
}

// ===== HANDLE SAVE =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $nama = sanitize($_POST['nama']);
    $jabatan = sanitize($_POST['jabatan']);
    $nidn = sanitize($_POST['nidn']);
    $isi = $_POST['isi'];
    $tanda_tangan = sanitize($_POST['tanda_tangan']);
    $email = sanitize($_POST['email']);
    $link_scholar = sanitize($_POST['link_scholar']);
    $link_linkedin = sanitize($_POST['link_linkedin']);
    $video_url = sanitize($_POST['video_url']);

    // Validasi
    if (empty($nama)) {
        $error = "Nama lengkap wajib diisi.";
    } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Format email tidak valid.";
    } elseif (!empty($link_scholar) && !filter_var($link_scholar, FILTER_VALIDATE_URL)) {
        $error = "URL Google Scholar tidak valid.";
    } elseif (!empty($link_linkedin) && !filter_var($link_linkedin, FILTER_VALIDATE_URL)) {
        $error = "URL LinkedIn tidak valid.";
    } else {
        $fields = "nama=:nama, jabatan=:jabatan, nidn=:nidn, isi=:isi, tanda_tangan=:tanda_tangan, email=:email, link_scholar=:link_scholar, link_linkedin=:link_linkedin, video_url=:video_url";
        $params = [
            ':nama'=>$nama, ':jabatan'=>$jabatan, ':nidn'=>$nidn,
            ':isi'=>$isi, ':tanda_tangan'=>$tanda_tangan,
            ':email'=>$email, ':link_scholar'=>$link_scholar,
            ':link_linkedin'=>$link_linkedin, ':video_url'=>$video_url
        ];

        if (!empty($_FILES['foto']['name'])) {
            $upload = handle_upload($_FILES['foto'], '../assets/uploads/sambutan/', ['jpg', 'jpeg', 'png', 'webp']);
            if ($upload['success']) {
                $fields .= ", foto=:foto";
                $params[':foto'] = $upload['path'];
                if (!empty($data['foto']) && file_exists('../' . $data['foto'])) @unlink('../' . $data['foto']);
            } else {
                $error = $upload['error'];
            }
        }

        if (!isset($error)) {
            $check = $pdo->prepare("SELECT id FROM $table WHERE id = ?");
            $check->execute([$id]);
            
            if ($check->fetch()) {
                $pdo->prepare("UPDATE $table SET $fields WHERE id=:id")->execute(array_merge($params, [':id'=>$id]));
                $action = 'UPDATE_SAMBUTAN';
            } else {
                $pdo->prepare("INSERT INTO $table SET id=:id, $fields")->execute(array_merge($params, [':id'=>$id]));
                $action = 'CREATE_SAMBUTAN';
            }
            
            $pdo->prepare("INSERT INTO admin_logs (user_id, user_name, action, detail, ip) VALUES (?, ?, ?, ?, ?)")
                ->execute([$_SESSION['admin_id'], $_SESSION['admin_name'], $action, "Memperbarui sambutan Kaprodi: $nama", $_SERVER['REMOTE_ADDR']]);
            
            header("Location: kelola_sambutan.php?msg=saved");
            exit;
        }
    }
}

ob_start();
?>

<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<style>
/* ===== QUILL DARK THEME ===== */
.ql-toolbar { border: 1px solid var(--border-dark) !important; border-radius: var(--radius-md) var(--radius-md) 0 0 !important; background: var(--bg-darker); }
.ql-container { border: 1px solid var(--border-dark) !important; border-radius: 0 0 var(--radius-md) var(--radius-md) !important; background: var(--bg-darker); color: var(--text-main); font-family: inherit; font-size: 0.95rem; }
.ql-editor { min-height: 250px; color: var(--text-main); }
.ql-stroke { stroke: var(--text-muted) !important; }
.ql-fill { fill: var(--text-muted) !important; }
.ql-picker { color: var(--text-muted) !important; }
.ql-picker-label:hover, .ql-picker-label.ql-active { color: var(--crimson) !important; }
.ql-picker-label:hover .ql-stroke, .ql-picker-label.ql-active .ql-stroke { stroke: var(--crimson) !important; }
.ql-toolbar button:hover .ql-stroke, .ql-toolbar button.ql-active .ql-stroke { stroke: var(--crimson) !important; }
.ql-toolbar button:hover .ql-fill, .ql-toolbar button.ql-active .ql-fill { fill: var(--crimson) !important; }

/* ===== FORM SECTIONS ===== */
.form-section-title {
    font-size: .82rem;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    color: var(--sky-blue);
    margin: 1.8rem 0 1rem;
    padding-bottom: .5rem;
    border-bottom: 1px solid var(--border-dark);
    display: flex;
    align-items: center;
    gap: .5rem;
    font-weight: 700;
}
.form-section-title:first-child { margin-top: 0; }
.form-section-title i { color: var(--crimson); }

/* ===== INPUT ICON ===== */
.input-icon-wrap { position: relative; }
.input-icon-wrap i {
    position: absolute; left: 14px; top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted); font-size: .9rem;
    pointer-events: none;
}
.input-icon-wrap .form-control { padding-left: 42px; }

/* ===== FORM GRID ===== */
.sambutan-form-grid {
    display: grid;
    grid-template-columns: 1.4fr 1fr;
    gap: 2rem;
}
@media (max-width: 1100px) {
    .sambutan-form-grid { grid-template-columns: 1fr; }
}

/* ===== LIVE PREVIEW (Meniru Public Sambutan) ===== */
.live-preview-wrapper {
    background: linear-gradient(135deg, var(--bg-darker), #1a1a2e);
    border: 1px solid var(--border-dark);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    position: sticky;
    top: 20px;
    align-self: start;
    box-shadow: 0 10px 40px rgba(0,0,0,0.3);
}
.live-preview-title {
    font-size: .82rem;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    color: var(--sky-blue);
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: .5rem;
    font-weight: 700;
    padding-bottom: .5rem;
    border-bottom: 1px solid var(--border-dark);
}
.live-preview-title i { color: var(--crimson); }
.live-preview-badge {
    margin-left: auto;
    background: rgba(138,21,56,0.15);
    color: var(--crimson);
    padding: .2rem .6rem;
    border-radius: 50px;
    font-size: .7rem;
    font-weight: 700;
}

/* Mini Sambutan Card */
.preview-sambutan-card {
    background: #fff;
    border-radius: 16px;
    overflow: hidden;
    position: relative;
    box-shadow: 0 15px 40px rgba(0,0,0,0.2);
}
.preview-sambutan-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--crimson), var(--sky-blue), var(--emerald));
}
.preview-sambutan-grid {
    display: grid;
    grid-template-columns: 110px 1fr;
    gap: 1rem;
    padding: 1.2rem;
}
.preview-photo-wrap {
    position: relative;
}
.preview-photo {
    width: 110px;
    height: 140px;
    border-radius: 10px;
    overflow: hidden;
    background: linear-gradient(135deg, var(--crimson), var(--crimson-light));
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 2.5rem;
    box-shadow: 0 6px 15px rgba(0,0,0,0.15);
    position: relative;
    z-index: 2;
}
.preview-photo img { width: 100%; height: 100%; object-fit: cover; }
.preview-deco-1, .preview-deco-2 {
    position: absolute;
    border-radius: 10px;
    z-index: 1;
}
.preview-deco-1 {
    width: 100%; height: 100%;
    background: var(--crimson);
    top: 5px; left: 5px;
    opacity: .2;
}
.preview-deco-2 {
    width: 100%; height: 100%;
    background: var(--sky-blue);
    top: 10px; left: 10px;
    opacity: .15;
}
.preview-content h3 {
    font-size: 1rem;
    font-weight: 800;
    color: #222;
    margin: 0 0 .2rem;
    line-height: 1.2;
}
.preview-jab {
    display: inline-block;
    background: linear-gradient(135deg, var(--crimson), var(--crimson-light));
    color: #fff;
    padding: .15rem .6rem;
    border-radius: 50px;
    font-size: .65rem;
    font-weight: 700;
    margin-bottom: .3rem;
}
.preview-nidn {
    font-size: .7rem;
    color: #666;
    display: flex;
    align-items: center;
    gap: .3rem;
    margin-bottom: .3rem;
}
.preview-nidn i { color: var(--sky-blue-dark); font-size: .65rem; }
.preview-quote {
    position: relative;
    font-size: .72rem;
    color: #555;
    line-height: 1.5;
    font-style: italic;
    max-height: 90px;
    overflow: hidden;
    text-overflow: ellipsis;
}
.preview-quote::before {
    content: '"';
    position: absolute;
    top: -8px; left: -5px;
    font-size: 2rem;
    color: rgba(138,21,56,.15);
    font-family: Georgia, serif;
    line-height: 1;
}
.preview-signature {
    margin-top: .6rem;
    padding-top: .5rem;
    border-top: 1px dashed #ddd;
}
.preview-signature-text {
    font-family: 'Brush Script MT', 'Lucida Handwriting', cursive;
    font-size: 1.3rem;
    color: var(--crimson);
    line-height: 1;
    margin-bottom: .2rem;
}
.preview-signature-line {
    width: 80px; height: 1px;
    background: linear-gradient(90deg, var(--crimson), transparent);
    margin-bottom: .2rem;
}
.preview-signature-name {
    font-size: .7rem;
    color: #222;
    font-weight: 700;
}

/* Social Links Preview */
.preview-socials {
    display: flex;
    gap: .4rem;
    justify-content: center;
    padding: .7rem;
    background: var(--bg-light);
    border-top: 1px solid #f0f0f0;
}
.preview-socials a {
    width: 30px; height: 30px;
    border-radius: 50%;
    background: #fff;
    color: var(--rich-black);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: .8rem;
    text-decoration: none;
    box-shadow: 0 2px 8px rgba(0,0,0,.08);
    transition: all .2s;
}
.preview-socials a:hover {
    background: var(--crimson);
    color: #fff;
    transform: translateY(-2px);
}
.preview-socials .no-social {
    color: #aaa;
    font-size: .75rem;
    font-style: italic;
}

/* Video Preview */
.preview-video-box {
    margin-top: 1rem;
    background: var(--bg-darker);
    border-radius: var(--radius-md);
    padding: 1rem;
    display: none;
}
.preview-video-box.visible { display: block; }
.preview-video-label {
    font-size: .75rem;
    color: var(--sky-blue);
    margin-bottom: .5rem;
    display: flex;
    align-items: center;
    gap: .4rem;
    font-weight: 700;
}
.preview-video-thumb {
    aspect-ratio: 16/9;
    border-radius: var(--radius-sm);
    overflow: hidden;
    background: #000;
    position: relative;
}
.preview-video-thumb img { width: 100%; height: 100%; object-fit: cover; }
.preview-video-thumb .play-btn {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(0,0,0,.3);
    color: #fff;
    font-size: 2rem;
}

/* Word Counter */
.word-counter {
    display: flex;
    gap: 1rem;
    padding: .8rem 1rem;
    background: var(--bg-darker);
    border-radius: var(--radius-md);
    border: 1px solid var(--border-dark);
    margin-top: .5rem;
    font-size: .8rem;
    flex-wrap: wrap;
}
.word-counter-item {
    display: flex;
    align-items: center;
    gap: .4rem;
    color: var(--text-muted);
}
.word-counter-item strong {
    color: #fff;
    font-weight: 800;
}
.word-counter-item i { color: var(--sky-blue); }

/* Profile Upload */
.profile-upload-wrapper {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: .8rem;
    padding: 1rem;
    background: var(--bg-darker);
    border-radius: var(--radius-md);
    border: 1px solid var(--border-dark);
}
.profile-preview {
    width: 140px; height: 180px;
    border-radius: 10px;
    border: 3px solid var(--border-dark);
    background: linear-gradient(135deg, var(--crimson), var(--crimson-light));
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    transition: all 0.3s ease;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    position: relative;
}
.profile-preview:hover { border-color: var(--crimson); transform: scale(1.02); }
.profile-preview img { width: 100%; height: 100%; object-fit: cover; }
.profile-preview .placeholder-icon { font-size: 3rem; color: #fff; opacity: .8; }
.profile-upload-btn { position: relative; overflow: hidden; display: inline-block; }
.profile-upload-btn input[type=file] { 
    position: absolute; left: 0; top: 0; opacity: 0; 
    width: 100%; height: 100%; cursor: pointer; 
}

/* Specs Info Box */
.specs-info-box {
    background: linear-gradient(135deg, rgba(135,206,235,0.08), rgba(138,21,56,0.08));
    border: 1px solid var(--border-dark);
    border-radius: var(--radius-md);
    padding: 1rem;
    margin-top: 1rem;
}
.specs-info-box h4 {
    color: var(--sky-blue);
    font-size: .85rem;
    margin-bottom: .6rem;
    display: flex;
    align-items: center;
    gap: .4rem;
}
.specs-info-box ul {
    list-style: none;
    padding: 0; margin: 0;
    font-size: .78rem;
    color: var(--text-muted);
    line-height: 1.7;
}
.specs-info-box ul li {
    display: flex;
    align-items: flex-start;
    gap: .4rem;
}
.specs-info-box ul li i { 
    color: var(--success); 
    margin-top: .2rem; 
    flex-shrink: 0; 
}

/* Quick Actions Preview */
.preview-actions {
    display: flex;
    gap: .5rem;
    padding: .7rem 1rem;
    background: var(--bg-darker);
    border-top: 1px solid var(--border-dark);
}
.preview-action-btn {
    flex: 1;
    padding: .4rem;
    background: transparent;
    border: 1px solid var(--border-dark);
    border-radius: var(--radius-sm);
    color: var(--text-muted);
    font-size: .72rem;
    cursor: pointer;
    transition: all .2s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: .3rem;
}
.preview-action-btn:hover {
    border-color: var(--crimson);
    color: var(--crimson);
}

/* Info Alert */
.info-alert {
    background: rgba(135,206,235,0.1);
    border: 1px solid rgba(135,206,235,0.3);
    border-left: 4px solid var(--sky-blue);
    border-radius: var(--radius-md);
    padding: 1rem 1.2rem;
    margin-bottom: 1.5rem;
    color: var(--text-main);
    font-size: .9rem;
    display: flex;
    align-items: center;
    gap: .8rem;
}
.info-alert i { color: var(--sky-blue); font-size: 1.3rem; }
.info-alert strong { color: var(--sky-blue); }
</style>

<?php if (isset($_GET['msg']) && $_GET['msg'] === 'saved'): ?>
<script>Swal.fire({icon:'success', title:'Berhasil!', text:'Sambutan Kaprodi berhasil diperbarui!', timer:2500, showConfirmButton:false});</script>
<?php endif; ?>

<?php if (isset($error)): ?>
<script>Swal.fire({icon:'error', title:'Validasi Gagal', text:'<?= addslashes($error) ?>'});</script>
<?php endif; ?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;">
    <div>
        <h2 class="section-title"><i class="fa-solid fa-user-tie"></i> Edit Sambutan Kaprodi</h2>
        <p style="color:var(--text-muted); font-size:.88rem; margin-top:.3rem;">
            <i class="fa-solid fa-circle-info"></i> Pesan hangat dari pimpinan prodi yang tampil di halaman sambutan website
        </p>
    </div>
    <a href="../sambutan.php" target="_blank" class="btn btn-secondary">
        <i class="fa-solid fa-external-link"></i> Lihat di Website
    </a>
</div>

<!-- Info Alert -->
<div class="info-alert">
    <i class="fa-solid fa-circle-info"></i>
    <div>
        <strong>Tips:</strong> Sambutan yang baik memiliki 200-400 kata dengan nada hangat dan profesional. Gunakan Quill Editor untuk memformat teks dengan heading, bold, dan list.
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <form method="POST" enctype="multipart/form-data" id="sambutanForm">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="isi" id="hiddenIsi" value="<?= htmlspecialchars($data['isi']) ?>">
            
            <div class="sambutan-form-grid">
                <!-- FORM FIELDS -->
                <div>
                    <div class="form-section-title"><i class="fa-solid fa-user"></i> Informasi Pribadi</div>
                    
                    <div style="display:grid; grid-template-columns: 2fr 1fr; gap:1rem;">
                        <div class="form-group">
                            <label>Nama Lengkap <span class="required">*</span></label>
                            <div class="input-icon-wrap">
                                <i class="fa-solid fa-user"></i>
                                <input type="text" name="nama" id="f_nama" class="form-control" value="<?= htmlspecialchars($data['nama']) ?>" required placeholder="Dr. John Doe, M.Hum.">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>NIDN</label>
                            <div class="input-icon-wrap">
                                <i class="fa-solid fa-id-card"></i>
                                <input type="text" name="nidn" id="f_nidn" class="form-control" value="<?= htmlspecialchars($data['nidn']) ?>" placeholder="08123456789">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Jabatan</label>
                        <div class="input-icon-wrap">
                            <i class="fa-solid fa-briefcase"></i>
                            <input type="text" name="jabatan" id="f_jabatan" class="form-control" value="<?= htmlspecialchars($data['jabatan']) ?>" placeholder="Ketua Program Studi">
                        </div>
                    </div>

                    <div class="form-section-title"><i class="fa-solid fa-photo-film"></i> Media</div>
                    
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem;">
                        <div>
                            <label style="display:block; margin-bottom:.5rem; font-weight:600;">Foto Kaprodi</label>
                            <div class="profile-upload-wrapper">
                                <div class="profile-preview" id="profilePreview">
                                    <?php if (!empty($data['foto'])): 
                                        $fotoSrc = filter_var($data['foto'], FILTER_VALIDATE_URL) ? $data['foto'] : '../' . $data['foto'];
                                    ?>
                                        <img src="<?= htmlspecialchars($fotoSrc) ?>" id="previewImg" onerror="this.style.display='none'; this.parentElement.querySelector('.placeholder-icon').style.display='flex';">
                                    <?php else: ?>
                                        <i class="fa-solid fa-user-tie placeholder-icon"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="profile-upload-btn">
                                    <button type="button" class="btn btn-secondary btn-sm"><i class="fa-solid fa-camera"></i> Ganti Foto</button>
                                    <input type="file" name="foto" accept="image/*" id="fileInput">
                                </div>
                            </div>
                        </div>
                        <div>
                            <div class="form-group">
                                <label>Video Sambutan (URL Embed)</label>
                                <div class="input-icon-wrap">
                                    <i class="fa-brands fa-youtube"></i>
                                    <input type="url" name="video_url" id="f_video" class="form-control" value="<?= htmlspecialchars($data['video_url']) ?>" placeholder="https://youtube.com/embed/...">
                                </div>
                                <small style="color:var(--text-muted); font-size:.78rem;"><i class="fa-solid fa-info-circle"></i> Gunakan URL embed YouTube/Vimeo</small>
                            </div>
                            
                            <div class="specs-info-box">
                                <h4><i class="fa-solid fa-lightbulb"></i> Spesifikasi Ideal</h4>
                                <ul>
                                    <li><i class="fa-solid fa-check"></i> <span><strong>Foto:</strong> Rasio 3:4, min 400x500px</span></li>
                                    <li><i class="fa-solid fa-check"></i> <span><strong>Format:</strong> JPG/WebP (max 2MB)</span></li>
                                    <li><i class="fa-solid fa-check"></i> <span><strong>Video:</strong> YouTube embed 16:9</span></li>
                                    <li><i class="fa-solid fa-check"></i> <span><strong>Pose:</strong> Formal, tersenyum, background netral</span></li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="form-section-title"><i class="fa-solid fa-share-nodes"></i> Kontak & Sosial Media</div>
                    
                    <div class="form-group">
                        <label>Email</label>
                        <div class="input-icon-wrap">
                            <i class="fa-solid fa-envelope"></i>
                            <input type="email" name="email" id="f_email" class="form-control" value="<?= htmlspecialchars($data['email']) ?>" placeholder="kaprodi@ummaumere.ac.id">
                        </div>
                    </div>
                    
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem;">
                        <div class="form-group">
                            <label>Google Scholar</label>
                            <div class="input-icon-wrap">
                                <i class="fa-solid fa-graduation-cap"></i>
                                <input type="url" name="link_scholar" id="f_scholar" class="form-control" value="<?= htmlspecialchars($data['link_scholar']) ?>" placeholder="https://scholar.google.com/...">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>LinkedIn</label>
                            <div class="input-icon-wrap">
                                <i class="fa-brands fa-linkedin"></i>
                                <input type="url" name="link_linkedin" id="f_linkedin" class="form-control" value="<?= htmlspecialchars($data['link_linkedin']) ?>" placeholder="https://linkedin.com/in/...">
                            </div>
                        </div>
                    </div>

                    <div class="form-section-title"><i class="fa-solid fa-pen-fancy"></i> Isi Sambutan</div>
                    
                    <div class="form-group">
                        <label>Konten Sambutan <span class="required">*</span></label>
                        <div id="quillEditor" style="min-height: 250px;"><?= $data['isi'] ?></div>
                        <div class="word-counter">
                            <div class="word-counter-item">
                                <i class="fa-solid fa-font"></i>
                                <span>Kata: <strong id="wordCount">0</strong></span>
                            </div>
                            <div class="word-counter-item">
                                <i class="fa-solid fa-text-width"></i>
                                <span>Karakter: <strong id="charCount">0</strong></span>
                            </div>
                            <div class="word-counter-item">
                                <i class="fa-solid fa-clock"></i>
                                <span>Estimasi baca: <strong id="readTime">0</strong> menit</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Nama Tanda Tangan</label>
                        <div class="input-icon-wrap">
                            <i class="fa-solid fa-signature"></i>
                            <input type="text" name="tanda_tangan" id="f_ttd" class="form-control" value="<?= htmlspecialchars($data['tanda_tangan']) ?>" placeholder="Dr. John Doe, M.Hum." style="font-family: 'Brush Script MT', cursive; font-size: 1.2rem;">
                        </div>
                        <small style="color:var(--text-muted);"><i class="fa-solid fa-info-circle"></i> Akan ditampilkan dengan font signature di bawah sambutan</small>
                    </div>

                    <div style="margin-top:2rem; display:flex; gap:1rem; padding-top:1.5rem; border-top:1px solid var(--border-dark);">
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Simpan Perubahan</button>
                        <a href="dashboard.php" class="btn btn-secondary"><i class="fa-solid fa-xmark"></i> Batal</a>
                    </div>
                </div>

                <!-- LIVE PREVIEW -->
                <div class="live-preview-wrapper">
                    <div class="live-preview-title">
                        <i class="fa-solid fa-eye"></i> Live Preview
                        <span class="live-preview-badge">REAL-TIME</span>
                    </div>
                    
                    <!-- Mini Sambutan Card -->
                    <div class="preview-sambutan-card">
                        <div class="preview-sambutan-grid">
                            <div class="preview-photo-wrap">
                                <div class="preview-deco-1"></div>
                                <div class="preview-deco-2"></div>
                                <div class="preview-photo" id="prevPhoto">
                                    <img src="<?= !empty($data['foto']) ? '../'.htmlspecialchars($data['foto']) : '' ?>" id="prevPhotoImg" style="<?= empty($data['foto']) ? 'display:none;' : '' ?>"><?php 
                                    $prevFotoSrc = '';
                                    if (!empty($data['foto'])) {
                                        $prevFotoSrc = filter_var($data['foto'], FILTER_VALIDATE_URL) ? $data['foto'] : '../' . $data['foto'];
                                    }
                                    ?>
<img src="<?= htmlspecialchars($prevFotoSrc) ?>" id="prevPhotoImg" style="<?= empty($data['foto']) ? 'display:none;' : '' ?>" onerror="this.style.display='none'; document.getElementById('prevPhotoIcon').style.display='flex';">
                                </div>
                            </div>
                            <div class="preview-content">
                                <span class="preview-jab" id="prevJab"><?= htmlspecialchars($data['jabatan'] ?: 'Ketua Program Studi') ?></span>
                                <h3 id="prevNama"><?= htmlspecialchars($data['nama'] ?: 'Nama Kaprodi') ?></h3>
                                <div class="preview-nidn" id="prevNidnWrap" style="<?= empty($data['nidn']) ? 'display:none;' : '' ?>">
                                    <i class="fa-solid fa-id-card"></i>
                                    NIDN: <span id="prevNidn"><?= htmlspecialchars($data['nidn']) ?></span>
                                </div>
                                <div class="preview-quote" id="prevQuote">
                                    <span id="prevQuoteText"><?= htmlspecialchars(strip_tags(substr($data['isi'] ?? '', 0, 120))) ?: 'Isi sambutan akan muncul di sini...' ?></span>...
                                </div>
                                <div class="preview-signature" id="prevSignatureWrap" style="<?= empty($data['tanda_tangan']) ? 'display:none;' : '' ?>">
                                    <div class="preview-signature-text" id="prevSignature"><?= htmlspecialchars($data['tanda_tangan']) ?></div>
                                    <div class="preview-signature-line"></div>
                                    <div class="preview-signature-name" id="prevSignatureName"><?= htmlspecialchars($data['nama']) ?></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="preview-socials" id="prevSocials">
                            <?php 
                            $hasSocial = !empty($data['email']) || !empty($data['link_scholar']) || !empty($data['link_linkedin']);
                            if (!$hasSocial): ?>
                            <span class="no-social">Belum ada sosial media</span>
                            <?php endif; ?>
                            <?php if (!empty($data['email'])): ?>
                            <a href="#" id="prevEmail" title="Email"><i class="fa-solid fa-envelope"></i></a>
                            <?php endif; ?>
                            <?php if (!empty($data['link_scholar'])): ?>
                            <a href="#" id="prevScholar" title="Google Scholar"><i class="fa-solid fa-graduation-cap"></i></a>
                            <?php endif; ?>
                            <?php if (!empty($data['link_linkedin'])): ?>
                            <a href="#" id="prevLinkedinIcon" title="LinkedIn"><i class="fa-brands fa-linkedin"></i></a>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Video Preview -->
                    <div class="preview-video-box" id="prevVideoBox" style="<?= !empty($data['video_url']) ? 'display:block;' : '' ?>">
                        <div class="preview-video-label">
                            <i class="fa-solid fa-video"></i> Video Sambutan
                        </div>
                        <div class="preview-video-thumb" id="prevVideoThumb">
                            <img src="" id="prevVideoImg" style="display:none;">
                            <div class="play-btn"><i class="fa-solid fa-play"></i></div>
                        </div>
                    </div>
                    
                    <!-- Preview Actions -->
                    <div class="preview-actions">
                        <button type="button" class="preview-action-btn" onclick="window.print()">
                            <i class="fa-solid fa-print"></i> Cetak
                        </button>
                        <button type="button" class="preview-action-btn" onclick="shareSambutan()">
                            <i class="fa-solid fa-share-nodes"></i> Bagikan
                        </button>
                        <a href="../sambutan.php" target="_blank" class="preview-action-btn" style="text-decoration:none;">
                            <i class="fa-solid fa-external-link"></i> Website
                        </a>
                    </div>
                    
                    <div style="margin-top:1rem; padding:.8rem; background:rgba(46,139,87,0.1); border:1px solid rgba(46,139,87,0.3); border-radius:var(--radius-md); font-size:.78rem; color:var(--text-muted);">
                        <i class="fa-solid fa-circle-check" style="color:var(--success);"></i>
                        <strong style="color:var(--success);"> Tip:</strong> Preview ini menampilkan tampilan persis seperti di halaman sambutan website. Perubahan akan terlihat otomatis.
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // ===== QUILL EDITOR =====
    var quill = new Quill('#quillEditor', {
        theme: 'snow',
        placeholder: 'Tulis sambutan hangat di sini. Mulai dengan sapaan, visi prodi, harapan untuk mahasiswa baru, dan penutup yang menginspirasi...',
        modules: {
            toolbar: [
                [{ 'header': [1, 2, 3, false] }],
                ['bold', 'italic', 'underline'],
                [{ 'color': [] }, { 'background': [] }],
                [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                ['link', 'image'],
                ['blockquote'],
                ['clean']
            ]
        }
    });
    
    // Sync Quill to hidden input + update word counter
    function updateContent() {
        document.getElementById('hiddenIsi').value = quill.root.innerHTML;
        updateWordCount();
        updateQuotePreview();
    }
    
    quill.on('text-change', updateContent);
    
    function updateWordCount() {
        const text = quill.getText().trim();
        const words = text ? text.split(/\s+/).length : 0;
        const chars = text.length;
        const readTime = Math.max(1, Math.ceil(words / 200));
        
        document.getElementById('wordCount').textContent = words;
        document.getElementById('charCount').textContent = chars;
        document.getElementById('readTime').textContent = readTime;
    }
    
    function updateQuotePreview() {
        const text = quill.getText().trim();
        const previewText = text ? text.substring(0, 120) : 'Isi sambutan akan muncul di sini...';
        document.getElementById('prevQuoteText').textContent = previewText;
    }
    
    // Initial count
    updateWordCount();
    updateQuotePreview();
    
    // Form submit handler
    document.querySelector('form').addEventListener('submit', function(e) {
        updateContent();
    });
    
    // ===== LIVE PREVIEW BINDING =====
    const fields = {
        nama: document.getElementById('f_nama'),
        jabatan: document.getElementById('f_jabatan'),
        nidn: document.getElementById('f_nidn'),
        tanda_tangan: document.getElementById('f_ttd'),
        email: document.getElementById('f_email'),
        scholar: document.getElementById('f_scholar'),
        linkedin: document.getElementById('f_linkedin'),
        video: document.getElementById('f_video'),
        file: document.getElementById('fileInput')
    };
    
    const prevNama = document.getElementById('prevNama');
    const prevJab = document.getElementById('prevJab');
    const prevNidn = document.getElementById('prevNidn');
    const prevNidnWrap = document.getElementById('prevNidnWrap');
    const prevSignature = document.getElementById('prevSignature');
    const prevSignatureName = document.getElementById('prevSignatureName');
    const prevSignatureWrap = document.getElementById('prevSignatureWrap');
    const prevPhotoImg = document.getElementById('prevPhotoImg');
    const prevPhotoIcon = document.getElementById('prevPhotoIcon');
    const prevEmail = document.getElementById('prevEmail');
    const prevScholar = document.getElementById('prevScholar');
    const prevLinkedinIcon = document.getElementById('prevLinkedinIcon');
    const prevSocials = document.getElementById('prevSocials');
    
    fields.nama?.addEventListener('input', e => {
        prevNama.textContent = e.target.value || 'Nama Kaprodi';
        if (prevSignatureName) prevSignatureName.textContent = e.target.value || 'Nama Kaprodi';
    });
    fields.jabatan?.addEventListener('input', e => {
        prevJab.textContent = e.target.value || 'Ketua Program Studi';
    });
    fields.nidn?.addEventListener('input', e => {
        if (e.target.value) {
            prevNidnWrap.style.display = 'flex';
            prevNidn.textContent = e.target.value;
        } else {
            prevNidnWrap.style.display = 'none';
        }
    });
    fields.tanda_tangan?.addEventListener('input', e => {
        if (e.target.value) {
            prevSignatureWrap.style.display = 'block';
            prevSignature.textContent = e.target.value;
        } else {
            prevSignatureWrap.style.display = 'none';
        }
    });
    
    // Social links management
    function updateSocials() {
        const emailVal = fields.email?.value || '';
        const scholarVal = fields.scholar?.value || '';
        const linkedinVal = fields.linkedin?.value || '';
        
        // Rebuild socials
        let html = '';
        if (emailVal) html += `<a href="mailto:${emailVal}" title="Email"><i class="fa-solid fa-envelope"></i></a>`;
        if (scholarVal) html += `<a href="${scholarVal}" target="_blank" title="Google Scholar"><i class="fa-solid fa-graduation-cap"></i></a>`;
        if (linkedinVal) html += `<a href="${linkedinVal}" target="_blank" title="LinkedIn"><i class="fa-brands fa-linkedin"></i></a>`;
        
        if (!html) html = '<span class="no-social">Belum ada sosial media</span>';
        if (prevSocials) prevSocials.innerHTML = html;
    }
    
    fields.email?.addEventListener('input', updateSocials);
    fields.scholar?.addEventListener('input', updateSocials);
    fields.linkedin?.addEventListener('input', updateSocials);
    
    // Video URL -> auto thumbnail
    let videoDebounce = null;
    fields.video?.addEventListener('input', e => {
        clearTimeout(videoDebounce);
        videoDebounce = setTimeout(() => {
            const url = e.target.value;
            const videoBox = document.getElementById('prevVideoBox');
            const videoImg = document.getElementById('prevVideoImg');
            
            if (!url) {
                videoBox.style.display = 'none';
                return;
            }
            
            videoBox.style.display = 'block';
            
            // Extract thumbnail from YouTube/Vimeo
            const ytMatch = url.match(/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/);
            const vimeoMatch = url.match(/vimeo\.com\/(\d+)/);
            
            if (ytMatch) {
                videoImg.src = `https://img.youtube.com/vi/${ytMatch[1]}/maxresdefault.jpg`;
                videoImg.style.display = 'block';
            } else if (vimeoMatch) {
                videoImg.src = `https://vumbnail.com/${vimeoMatch[1]}.jpg`;
                videoImg.style.display = 'block';
            } else {
                videoImg.style.display = 'none';
            }
        }, 500);
    });
    
    // File upload preview
    fields.file?.addEventListener('change', e => {
        if (e.target.files.length) {
            const reader = new FileReader();
            reader.onload = ev => {
                // Update profile preview
                let profileImg = document.getElementById('previewImg');
                const previewBox = document.getElementById('profilePreview');
                if (!profileImg) {
                    profileImg = document.createElement('img');
                    profileImg.id = 'previewImg';
                    previewBox.innerHTML = '';
                    previewBox.appendChild(profileImg);
                }
                profileImg.src = ev.target.result;
                
                // Update card preview
                if (prevPhotoImg) {
                    prevPhotoImg.src = ev.target.result;
                    prevPhotoImg.style.display = 'block';
                }
                if (prevPhotoIcon) prevPhotoIcon.style.display = 'none';
            };
            reader.readAsDataURL(e.target.files[0]);
        }
    });
});

// Share function
function shareSambutan() {
    if (navigator.share) {
        navigator.share({
            title: 'Sambutan Kaprodi - PBSI UMMaumere',
            text: 'Baca sambutan dari Ketua Program Studi Bahasa dan Sastra Inggris UMMaumere',
            url: window.location.origin + '/sambutan.php'
        });
    } else {
        navigator.clipboard.writeText(window.location.origin + '/sambutan.php').then(() => {
            Swal.fire({icon:'success', title:'Link Disalin!', text:'Link sambutan berhasil disalin ke clipboard.', timer:2000, showConfirmButton:false});
        });
    }
}
</script>

<?php
$page_content = ob_get_clean();
require_once 'includes/admin_template.php';
?>