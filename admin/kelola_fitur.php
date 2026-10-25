<?php
require_once '../includes/config.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';
require_login();

$page_title = 'Kelola Fitur Unggulan';
$current_page = 'fitur';
$table = 'fitur';

// ===== HANDLE DELETE =====
if (isset($_GET['delete']) && isset($_GET['token']) && hash_equals($_SESSION['csrf_token'], $_GET['token'])) {
    $pdo->prepare("DELETE FROM $table WHERE id = ?")->execute([(int)$_GET['delete']]);
    header("Location: kelola_fitur.php?msg=deleted");
    exit;
}

// ===== HANDLE SAVE =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $icon = sanitize($_POST['icon']);
    $judul = sanitize($_POST['judul']);
    $deskripsi = sanitize($_POST['deskripsi']);
    $urutan = (int)$_POST['urutan'];
    $aktif = isset($_POST['aktif']) ? 1 : 0;

    if (empty($judul) || empty($deskripsi)) {
        $error = "Judul dan Deskripsi wajib diisi.";
    } else {
        $fields = "icon=:icon, judul=:judul, deskripsi=:deskripsi, urutan=:urutan, aktif=:aktif";
        $params = [':icon'=>$icon, ':judul'=>$judul, ':deskripsi'=>$deskripsi, ':urutan'=>$urutan, ':aktif'=>$aktif];
        
        if ($id > 0) {
            $pdo->prepare("UPDATE $table SET $fields WHERE id=:id")->execute(array_merge($params, [':id'=>$id]));
            $action = 'UPDATE_FITUR';
            $detail = "Memperbarui fitur: $judul";
        } else {
            $pdo->prepare("INSERT INTO $table SET $fields")->execute($params);
            $action = 'ADD_FITUR';
            $detail = "Menambah fitur baru: $judul";
        }
        
        $pdo->prepare("INSERT INTO admin_logs (user_id, user_name, action, detail, ip) VALUES (?, ?, ?, ?, ?)")
            ->execute([$_SESSION['admin_id'], $_SESSION['admin_name'], $action, $detail, $_SERVER['REMOTE_ADDR']]);
            
        header("Location: kelola_fitur.php?msg=saved");
        exit;
    }
}

$items = $pdo->query("SELECT * FROM $table ORDER BY urutan ASC")->fetchAll();
$data = ['id'=>0, 'icon'=>'fa-solid fa-star', 'judul'=>'', 'deskripsi'=>'', 'urutan'=>1, 'aktif'=>1];

if (isset($_GET['edit']) && isset($_GET['token']) && hash_equals($_SESSION['csrf_token'], $_GET['token'])) {
    $stmt = $pdo->prepare("SELECT * FROM $table WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $fetched = $stmt->fetch();
    if ($fetched) $data = $fetched;
}

// Common icons for picker
$common_icons = [
    'fa-star', 'fa-heart', 'fa-book', 'fa-graduation-cap', 'fa-users', 
    'fa-chalkboard-user', 'fa-laptop-code', 'fa-globe', 'fa-language', 
    'fa-pen-fancy', 'fa-lightbulb', 'fa-award', 'fa-certificate', 'fa-handshake',
    'fa-rocket', 'fa-brain', 'fa-microscope', 'fa-palette', 'fa-music', 'fa-camera'
];

ob_start();
?>

<style>
/* Icon Picker Grid */
.icon-picker-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
    gap: 0.5rem;
    max-height: 200px;
    overflow-y: auto;
    padding: 0.5rem;
    background: var(--bg-darker);
    border: 1px solid var(--border-dark);
    border-radius: var(--radius-md);
    margin-top: 0.5rem;
}
.icon-picker-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 0.6rem;
    border-radius: var(--radius-sm);
    cursor: pointer;
    transition: all 0.2s;
    border: 1px solid transparent;
    color: var(--text-muted);
}
.icon-picker-item:hover {
    background: rgba(138,21,56,0.1);
    color: var(--crimson);
    border-color: var(--crimson);
}
.icon-picker-item i { font-size: 1.3rem; margin-bottom: 0.3rem; }
.icon-picker-item span { font-size: 0.65rem; text-align: center; line-height: 1.2; }

/* Live Preview Card */
.live-preview-box {
    background: #fff;
    border-radius: 16px;
    padding: 2rem 1.5rem;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    text-align: center;
    border-top: 4px solid var(--crimson);
    color: #333;
}
.preview-icon-wrap {
    width: 70px; height: 70px;
    border-radius: 20px;
    background: linear-gradient(135deg, rgba(138,21,56,0.1), rgba(135,206,235,0.1));
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 1.2rem;
    font-size: 2rem;
    color: var(--crimson);
    transition: all 0.3s;
}
.preview-title {
    font-size: 1.1rem;
    font-weight: 800;
    color: #222;
    margin-bottom: 0.6rem;
    line-height: 1.3;
}
.preview-desc {
    font-size: 0.9rem;
    color: #666;
    line-height: 1.6;
}

/* Toggle Switch */
.toggle-switch {
    position: relative;
    display: inline-block;
    width: 48px;
    height: 26px;
}
.toggle-switch input { opacity: 0; width: 0; height: 0; }
.toggle-slider {
    position: absolute;
    cursor: pointer;
    inset: 0;
    background: var(--bg-darker);
    border: 1px solid var(--border-dark);
    transition: .3s;
    border-radius: 34px;
}
.toggle-slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background: var(--text-muted);
    transition: .3s;
    border-radius: 50%;
}
.toggle-switch input:checked + .toggle-slider { background: var(--success); border-color: var(--success); }
.toggle-switch input:checked + .toggle-slider:before { transform: translateX(22px); background: #fff; }
</style>

<?php if (isset($_GET['msg'])): 
    $msgs = ['saved'=>['success', 'Berhasil!', 'Data fitur berhasil disimpan!'], 'deleted'=>['success', 'Berhasil!', 'Fitur berhasil dihapus!']];
    if (isset($msgs[$_GET['msg']])): ?>
    <script>Swal.fire({icon:'<?= $msgs[$_GET['msg']][0] ?>', title:'<?= $msgs[$_GET['msg']][1] ?>', text:'<?= $msgs[$_GET['msg']][2] ?>', timer:2500, showConfirmButton:false});</script>
<?php endif; endif; ?>

<?php if (isset($error)): ?>
<script>Swal.fire({icon:'error', title:'Validasi Gagal', text:'<?= addslashes($error) ?>'});</script>
<?php endif; ?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;">
    <h2 class="section-title"><i class="fa-solid fa-star"></i> <?= isset($_GET['edit']) ? 'Edit' : 'Kelola' ?> Fitur Unggulan</h2>
    <div style="display:flex; gap:.8rem;">
        <?php if (isset($_GET['edit'])): ?>
        <a href="kelola_fitur.php" class="btn btn-secondary"><i class="fa-solid fa-xmark"></i> Batal</a>
        <?php else: ?>
        <a href="?action=add" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Tambah Fitur</a>
        <?php endif; ?>
    </div>
</div>

<?php if (isset($_GET['action']) && $_GET['action'] === 'add' || isset($_GET['edit'])): ?>
<div class="panel">
    <div class="panel-body">
        <form method="POST" id="fiturForm">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="id" value="<?= $data['id'] ?>">
            
            <div style="display:grid; grid-template-columns: 1.2fr 0.8fr; gap: 2rem;">
                <!-- FORM FIELDS -->
                <div>
                    <div class="form-section-title"><i class="fa-solid fa-pen-to-square"></i> Detail Fitur</div>
                    
                    <div class="form-group">
                        <label>Judul Fitur <span class="required">*</span></label>
                        <input type="text" name="judul" id="f_judul" class="form-control" value="<?= htmlspecialchars($data['judul']) ?>" required placeholder="Contoh: Language Laboratory">
                    </div>
                    
                    <div class="form-group">
                        <label>Deskripsi <span class="required">*</span></label>
                        <textarea name="deskripsi" id="f_deskripsi" rows="3" class="form-control" required placeholder="Deskripsi singkat keunggulan ini..."><?= htmlspecialchars($data['deskripsi']) ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>Pilih Ikon</label>
                        <div class="input-icon-wrap">
                            <i class="<?= htmlspecialchars($data['icon']) ?>" id="selectedIconPreview"></i>
                            <input type="text" name="icon" id="f_icon" class="form-control" value="<?= htmlspecialchars($data['icon']) ?>" placeholder="fa-solid fa-star" style="font-family:monospace;">
                        </div>
                        <div class="icon-picker-grid" id="iconPicker">
                            <?php foreach ($common_icons as $icon): ?>
                            <div class="icon-picker-item" data-icon="fa-solid <?= $icon ?>" onclick="selectIcon('fa-solid <?= $icon ?>')">
                                <i class="fa-solid <?= $icon ?>"></i>
                                <span><?= str_replace('fa-', '', $icon) ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1.5rem; margin-top:1rem;">
                        <div class="form-group">
                            <label>Urutan Tampil</label>
                            <input type="number" name="urutan" class="form-control" value="<?= $data['urutan'] ?>" min="1">
                        </div>
                        <div class="form-group">
                            <label style="margin-bottom:0.5rem; display:block;">Status Aktif</label>
                            <label class="toggle-switch">
                                <input type="checkbox" name="aktif" value="1" <?= $data['aktif']==1?'checked':'' ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                    </div>

                    <div style="margin-top:2rem; display:flex; gap:1rem; padding-top:1.5rem; border-top:1px solid var(--border-dark);">
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> <?= $data['id']>0?'Perbarui':'Simpan' ?> Fitur</button>
                        <a href="kelola_fitur.php" class="btn btn-secondary"><i class="fa-solid fa-xmark"></i> Batal</a>
                    </div>
                </div>

                <!-- LIVE PREVIEW -->
                <div>
                    <div class="form-section-title"><i class="fa-solid fa-eye"></i> Live Preview (Homepage)</div>
                    <div class="live-preview-box">
                        <div class="preview-icon-wrap">
                            <i class="<?= htmlspecialchars($data['icon']) ?>" id="prevIcon"></i>
                        </div>
                        <h3 class="preview-title" id="prevJudul"><?= htmlspecialchars($data['judul']) ?: 'Judul Fitur' ?></h3>
                        <p class="preview-desc" id="prevDeskripsi"><?= htmlspecialchars($data['deskripsi']) ?: 'Deskripsi fitur akan muncul di sini...' ?></p>
                    </div>
                    <div style="margin-top:1rem; padding:.8rem; background:rgba(46,139,87,0.1); border:1px solid rgba(46,139,87,0.3); border-radius:var(--radius-md); font-size:.78rem; color:var(--text-muted);">
                        <i class="fa-solid fa-circle-check" style="color:var(--success);"></i>
                        <strong style="color:var(--success);"> Tip:</strong> Preview di atas menampilkan tampilan persis seperti di bagian "Mengapa Memilih PBSI" pada homepage.
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function selectIcon(iconClass) {
    document.getElementById('f_icon').value = iconClass;
    document.getElementById('selectedIconPreview').className = iconClass;
    document.getElementById('prevIcon').className = iconClass;
}

document.getElementById('f_judul')?.addEventListener('input', function() {
    document.getElementById('prevJudul').textContent = this.value || 'Judul Fitur';
});
document.getElementById('f_deskripsi')?.addEventListener('input', function() {
    document.getElementById('prevDeskripsi').textContent = this.value || 'Deskripsi fitur akan muncul di sini...';
});
document.getElementById('f_icon')?.addEventListener('input', function() {
    document.getElementById('selectedIconPreview').className = this.value;
    document.getElementById('prevIcon').className = this.value;
});
</script>

<?php else: ?>
<!-- LIST VIEW -->
<div class="panel">
    <div class="panel-body no-padding">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th style="width:60px;">Urutan</th>
                        <th style="width:80px;">Ikon</th>
                        <th>Judul & Deskripsi</th>
                        <th style="width:100px;">Status</th>
                        <th style="width:140px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)): ?>
                    <tr><td colspan="5" style="text-align:center; padding:3rem; color:var(--text-muted);">
                        <i class="fa-solid fa-star" style="font-size:3rem; margin-bottom:1rem; display:block; opacity:.3;"></i>
                        Belum ada fitur yang ditambahkan.
                    </td></tr>
                    <?php else: foreach ($items as $item): 
                        $token = hash_hmac('sha256', $item['id'], $_SESSION['csrf_token']);
                    ?>
                    <tr>
                        <td style="text-align:center; font-weight:700; color:var(--crimson);">#<?= $item['urutan'] ?></td>
                        <td style="text-align:center;">
                            <div style="width:40px; height:40px; border-radius:10px; background:rgba(138,21,56,0.1); color:var(--crimson); display:inline-flex; align-items:center; justify-content:center; font-size:1.2rem;">
                                <i class="<?= htmlspecialchars($item['icon']) ?>"></i>
                            </div>
                        </td>
                        <td>
                            <strong style="color:#fff; font-size:.95rem;"><?= htmlspecialchars($item['judul']) ?></strong><br>
                            <small style="color:var(--text-muted); line-height:1.4;"><?= htmlspecialchars(substr($item['deskripsi'], 0, 100)) ?><?= strlen($item['deskripsi'])>100?'...':'' ?></small>
                        </td>
                        <td>
                            <span class="status-badge <?= $item['aktif']==1?'success':'warning' ?>">
                                <?= $item['aktif']==1?'Aktif':'Nonaktif' ?>
                            </span>
                        </td>
                        <td>
                            <div class="table-actions">
                                <a href="?edit=<?= $item['id'] ?>&token=<?= $token ?>" class="table-action-btn edit" title="Edit"><i class="fa-solid fa-pen"></i></a>
                                <a href="?delete=<?= $item['id'] ?>&token=<?= $token ?>" class="table-action-btn delete" onclick="return confirm('Yakin ingin menghapus fitur ini?')" title="Hapus"><i class="fa-solid fa-trash"></i></a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<?php
$page_content = ob_get_clean();
require_once 'includes/admin_template.php';
?>