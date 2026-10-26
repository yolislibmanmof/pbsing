<?php
require_once '../includes/config.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';
require_login();

$page_title = 'Manajemen Menu Navigasi';
$current_page = 'menu';
$table = 'menus';

// ===== HANDLE DELETE (Dengan CSRF) =====
if (isset($_GET['delete']) && isset($_GET['token']) && hash_equals(hash_hmac('sha256', (int)$_GET['delete'], $_SESSION['csrf_token']), $_GET['token'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("SELECT label FROM $table WHERE id = ?");
    $stmt->execute([$id]);
    $menu = $stmt->fetch();
    
    $pdo->prepare("DELETE FROM $table WHERE id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM $table WHERE parent_id = ?")->execute([$id]);
    
    if ($menu) {
        $pdo->prepare("INSERT INTO admin_logs (user_id, user_name, action, detail, ip) VALUES (?, ?, 'DELETE_MENU', 'Menghapus menu: ' . ?, ?)")
            ->execute([$_SESSION['admin_id'], $_SESSION['admin_name'], $menu['label'], $_SERVER['REMOTE_ADDR']]);
    }
    
    header("Location: kelola_menu.php?msg=deleted");
    exit;
}

// ===== HANDLE ADD / EDIT =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $label = sanitize($_POST['label']);
    $icon = sanitize($_POST['icon']);
    $url = sanitize($_POST['url']);
    $parent_id = (int)$_POST['parent_id'];
    $urutan = (int)$_POST['urutan'];
    $aktif = isset($_POST['aktif']) ? 1 : 0;

    if (empty($label)) {
        $error = "Label menu wajib diisi.";
    } else {
        if ($id > 0) {
            $sql = "UPDATE $table SET label=?, icon=?, url=?, parent_id=?, urutan=?, aktif=? WHERE id=?";
            $pdo->prepare($sql)->execute([$label, $icon, $url, $parent_id, $urutan, $aktif, $id]);
            $action = 'UPDATE_MENU';
            $detail = "Memperbarui menu: $label";
        } else {
            $sql = "INSERT INTO $table (label, icon, url, parent_id, urutan, aktif) VALUES (?, ?, ?, ?, ?, ?)";
            $pdo->prepare($sql)->execute([$label, $icon, $url, $parent_id, $urutan, $aktif]);
            $action = 'ADD_MENU';
            $detail = "Menambah menu baru: $label";
        }
        
        $pdo->prepare("INSERT INTO admin_logs (user_id, user_name, action, detail, ip) VALUES (?, ?, ?, ?, ?)")
            ->execute([$_SESSION['admin_id'], $_SESSION['admin_name'], $action, $detail, $_SERVER['REMOTE_ADDR']]);
            
        header("Location: kelola_menu.php?msg=saved");
        exit;
    }
}

// Ambil semua menu
$menus = $pdo->query("SELECT * FROM $table ORDER BY parent_id ASC, urutan ASC")->fetchAll();

$menu_lookup = [];
foreach ($menus as $m) {
    $menu_lookup[$m['id']] = $m;
}

function foreach_menu_options($menus, $selected_id = 0) {
    $html = '';
    foreach ($menus as $m) {
        if ($m['parent_id'] == 0) {
            $selected = ($selected_id == $m['id']) ? 'selected' : '';
            $html .= "<option value='{$m['id']}' $selected>" . htmlspecialchars($m['label']) . "</option>";
        }
    }
    return $html;
}

$data = ['id'=>0, 'label'=>'', 'icon'=>'fa-solid fa-circle', 'url'=>'#', 'parent_id'=>0, 'urutan'=>1, 'aktif'=>1];
if (isset($_GET['edit']) && isset($_GET['token']) && hash_equals(hash_hmac('sha256', (int)$_GET['edit'], $_SESSION['csrf_token']), $_GET['token'])) {
    $stmt = $pdo->prepare("SELECT * FROM $table WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $fetched = $stmt->fetch();
    if ($fetched) $data = $fetched;
}

$common_icons = [
    'fa-house', 'fa-newspaper', 'fa-calendar-days', 'fa-images', 'fa-cloud-arrow-down',
    'fa-chalkboard-user', 'fa-user-graduate', 'fa-book-open', 'fa-flask', 'fa-sitemap',
    'fa-bullseye', 'fa-user-tie', 'fa-star', 'fa-chart-pie', 'fa-bars-staggered',
    'fa-users-gear', 'fa-gear', 'fa-shield-halved', 'fa-globe', 'fa-link'
];

ob_start();
?>
<style>
/* Icon Picker for Menu */
.icon-picker-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(70px, 1fr));
    gap: 0.4rem;
    max-height: 150px;
    overflow-y: auto;
    padding: 0.5rem;
    background: var(--bg-darker);
    border: 1px solid var(--border-dark);
    border-radius: var(--radius-sm);
    margin-top: 0.5rem;
}
.icon-picker-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 0.5rem;
    border-radius: var(--radius-sm);
    cursor: pointer;
    transition: all 0.2s;
    border: 1px solid transparent;
    color: var(--text-muted);
    font-size: 0.7rem;
}
.icon-picker-item:hover, .icon-picker-item.active {
    background: rgba(138,21,56,0.15);
    color: var(--crimson);
    border-color: var(--crimson);
}
.icon-picker-item i { font-size: 1.2rem; margin-bottom: 0.2rem; }

/* Menu Preview in Modal */
.menu-preview-box {
    background: var(--bg-darker);
    border: 1px solid var(--border-dark);
    border-radius: var(--radius-md);
    padding: 1rem;
    margin-top: 1rem;
    display: flex;
    align-items: center;
    gap: 0.8rem;
    color: var(--text-main);
}
.menu-preview-box i { font-size: 1.2rem; color: var(--crimson); width: 24px; text-align: center; }
.menu-preview-box .preview-label { font-weight: 600; }
.menu-preview-box .preview-url { font-size: 0.75rem; color: var(--text-muted); font-family: monospace; }
</style>

<?php if (isset($_GET['msg'])): 
    $msgs = ['saved'=>['success', 'Berhasil!', 'Data menu berhasil disimpan.'], 'deleted'=>['success', 'Berhasil!', 'Menu berhasil dihapus.']];
    if (isset($msgs[$_GET['msg']])): ?>
    <script>Swal.fire({icon:'<?= $msgs[$_GET['msg']][0] ?>', title:'<?= $msgs[$_GET['msg']][1] ?>', text:'<?= $msgs[$_GET['msg']][2] ?>', timer:2500, showConfirmButton:false});</script>
<?php endif; endif; ?>

<?php if (isset($error)): ?>
<script>Swal.fire({icon:'error', title:'Validasi Gagal', text:'<?= addslashes($error) ?>'});</script>
<?php endif; ?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;">
    <h2 class="section-title"><i class="fa-solid fa-bars-staggered"></i> Manajemen Menu Navigasi</h2>
    <button class="btn btn-primary" onclick="openMenuModal()"><i class="fa-solid fa-plus"></i> Tambah Menu</button>
</div>

<div class="panel">
    <div class="panel-body no-padding">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th style="width:60px;">Urutan</th>
                        <th>Label Menu</th>
                        <th style="width:100px;">Ikon</th>
                        <th>URL / Link</th>
                        <th>Induk (Parent)</th>
                        <th style="width:80px;">Status</th>
                        <th style="width:120px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($menus as $m): 
                        $parent = ($m['parent_id'] > 0 && isset($menu_lookup[$m['parent_id']])) ? $menu_lookup[$m['parent_id']] : null;
                        $token = hash_hmac('sha256', $m['id'], $_SESSION['csrf_token']);
                    ?>
                    <tr style="<?= $m['parent_id'] > 0 ? 'background:rgba(255,255,255,.02);' : '' ?>">
                        <td style="text-align:center; font-weight:700; color:var(--crimson);">#<?= $m['urutan'] ?></td>
                        <td>
                            <?= $m['parent_id'] > 0 ? '<span style="color:var(--text-muted); margin-right:8px;">└─</span>' : '' ?>
                            <strong><?= htmlspecialchars($m['label']) ?></strong>
                        </td>
                        <td style="text-align:center;">
                            <div style="width:36px; height:36px; border-radius:8px; background:rgba(138,21,56,0.1); color:var(--crimson); display:inline-flex; align-items:center; justify-content:center; font-size:1.1rem;">
                                <i class="<?= htmlspecialchars($m['icon']) ?>"></i>
                            </div>
                        </td>
                        <td><code style="font-size:0.8rem; color:var(--sky-blue);"><?= htmlspecialchars($m['url']) ?></code></td>
                        <td><?= $parent ? '<span style="color:var(--text-muted);">↳ ' . htmlspecialchars($parent['label']) . '</span>' : '<span style="color:var(--success); font-weight:600;">Utama</span>' ?></td>
                        <td>
                            <span class="status-badge <?= $m['aktif'] ? 'success' : 'warning' ?>">
                                <?= $m['aktif'] ? 'Aktif' : 'Nonaktif' ?>
                            </span>
                        </td>
                        <td>
                            <div class="table-actions">
                                <button class="table-action-btn edit" onclick='editMenu(<?= htmlspecialchars(json_encode($m), ENT_QUOTES, 'UTF-8') ?>)' title="Edit"><i class="fa-solid fa-pen"></i></button>
                                <a href="?delete=<?= $m['id'] ?>&token=<?= $token ?>" class="table-action-btn delete" onclick="return confirm('Hapus menu ini? Child menu juga akan terhapus!')" title="Hapus"><i class="fa-solid fa-trash"></i></a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
const commonIcons = <?= json_encode($common_icons) ?>;

function openMenuModal(data = null) {
    const isEdit = data !== null;
    const currentIcon = isEdit ? data.icon : 'fa-solid fa-circle';
    
    let iconPickerHtml = '<div class="icon-picker-grid">';
    commonIcons.forEach(icon => {
        const fullIcon = 'fa-solid ' + icon;
        const isActive = currentIcon === fullIcon ? 'active' : '';
        iconPickerHtml += `<div class="icon-picker-item ${isActive}" onclick="selectMenuIcon(this, '${fullIcon}')">
            <i class="${fullIcon}"></i>
            <span>${icon.replace('fa-', '')}</span>
        </div>`;
    });
    iconPickerHtml += '</div>';

    Swal.fire({
        title: isEdit ? 'Edit Menu' : 'Tambah Menu Baru',
        html: `
            <input id="swal-id" type="hidden" value="${isEdit ? data.id : ''}">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <div style="text-align:left; display:grid; gap:12px;">
                <div>
                    <label style="font-size:0.85rem; color:var(--text-muted); display:block; margin-bottom:4px;">Label Menu *</label>
                    <input id="swal-label" class="swal2-input" placeholder="Contoh: Beranda" value="${isEdit ? data.label : ''}" style="margin:0; width:100%; box-sizing:border-box;" oninput="updateMenuPreview()">
                </div>
                <div>
                    <label style="font-size:0.85rem; color:var(--text-muted); display:block; margin-bottom:4px;">Class Ikon</label>
                    <input id="swal-icon" class="swal2-input" value="${currentIcon}" style="margin:0; width:100%; box-sizing:border-box; font-family:monospace;" oninput="updateMenuPreview()">
                    ${iconPickerHtml}
                </div>
                <div>
                    <label style="font-size:0.85rem; color:var(--text-muted); display:block; margin-bottom:4px;">URL / Link</label>
                    <input id="swal-url" class="swal2-input" placeholder="Contoh: index.php atau #" value="${isEdit ? data.url : '#'}" style="margin:0; width:100%; box-sizing:border-box;" oninput="updateMenuPreview()">
                </div>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
                    <div>
                        <label style="font-size:0.85rem; color:var(--text-muted); display:block; margin-bottom:4px;">Induk (Parent)</label>
                        <select id="swal-parent" class="swal2-input" style="margin:0; width:100%; box-sizing:border-box;">
                            <option value="0">-- Menu Utama --</option>
                            <?= foreach_menu_options($menus, $data['parent_id']) ?>
                        </select>
                    </div>
                    <div>
                        <label style="font-size:0.85rem; color:var(--text-muted); display:block; margin-bottom:4px;">Urutan</label>
                        <input id="swal-urutan" type="number" class="swal2-input" value="${isEdit ? data.urutan : '1'}" style="margin:0; width:100%; box-sizing:border-box;">
                    </div>
                </div>
                <label style="display:flex; align-items:center; gap:8px; color:var(--text-main); margin-top:8px; cursor:pointer;">
                    <input type="checkbox" id="swal-aktif" ${isEdit && data.aktif == 1 ? 'checked' : ''} style="width:16px; height:16px;"> 
                    <span>Aktifkan Menu</span>
                </label>
                
                <div class="menu-preview-box" id="menuPreview">
                    <i class="${currentIcon}" id="previewIcon"></i>
                    <div>
                        <div class="preview-label" id="previewLabel">${isEdit ? data.label : 'Label Menu'}</div>
                        <div class="preview-url" id="previewUrl">${isEdit ? data.url : '#'}</div>
                    </div>
                </div>
            </div>
        `,
        width: 600,
        showCancelButton: true,
        confirmButtonText: 'Simpan',
        cancelButtonText: 'Batal',
        preConfirm: () => {
            const label = document.getElementById('swal-label').value;
            if (!label) {
                Swal.showValidationMessage('Label menu wajib diisi!');
                return false;
            }
            return {
                id: document.getElementById('swal-id').value,
                label: label,
                icon: document.getElementById('swal-icon').value,
                url: document.getElementById('swal-url').value,
                parent_id: document.getElementById('swal-parent').value,
                urutan: document.getElementById('swal-urutan').value,
                aktif: document.getElementById('swal-aktif').checked ? 1 : 0
            }
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            const csrf = document.createElement('input');
            csrf.type = 'hidden';
            csrf.name = 'csrf_token';
            csrf.value = '<?= $_SESSION['csrf_token'] ?>';
            form.appendChild(csrf);
            
            for (const key in result.value) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = result.value[key];
                form.appendChild(input);
            }
            document.body.appendChild(form);
            form.submit();
        }
    });
}

function editMenu(data) {
    openMenuModal(data);
}

function selectMenuIcon(el, iconClass) {
    document.querySelectorAll('.icon-picker-item').forEach(item => item.classList.remove('active'));
    el.classList.add('active');
    document.getElementById('swal-icon').value = iconClass;
    updateMenuPreview();
}

function updateMenuPreview() {
    const label = document.getElementById('swal-label').value || 'Label Menu';
    const icon = document.getElementById('swal-icon').value || 'fa-solid fa-circle';
    const url = document.getElementById('swal-url').value || '#';
    
    document.getElementById('previewLabel').textContent = label;
    document.getElementById('previewIcon').className = icon;
    document.getElementById('previewUrl').textContent = url;
}
</script>

<?php
$page_content = ob_get_clean();
require_once 'includes/admin_template.php';
?>