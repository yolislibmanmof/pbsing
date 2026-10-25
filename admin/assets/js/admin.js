/**
 * =====================================================================
 * PBSI UMMaumere - Admin Command Center JavaScript
 * FINAL ULTIMATE VERSION
 * =====================================================================
 * Fitur:
 * ✅ Single initialization (anti double-load)
 * ✅ Null guards di SEMUA fungsi (anti "Cannot read properties of null")
 * ✅ Sidebar toggle + collapse persistence (localStorage)
 * ✅ Form validation + auto-slug + image preview cerdas
 * ✅ Table search & sorting
 * ✅ Toast notification system
 * ✅ Keyboard shortcuts (Ctrl+K, Ctrl+S, Esc)
 * ✅ Unsaved changes protection
 * ✅ SweetAlert2 fallback jika CDN gagal
 * =====================================================================
 */

// ===== GLOBAL VARIABLES =====
const AdminApp = {
    config: {
        sidebarBreakpoint: 992,
        toastDuration: 3000
    },
    state: {
        sidebarOpen: false,
        currentModal: null,
        unsavedChanges: false
    }
};

// ===== INITIALIZATION (SATU KALI SAJA) =====
AdminApp.init = function () {
    console.log('%c🎓 PBSI Command Center Loaded', 'color: #87CEEB; font-weight: bold; font-size: 14px;');

    this.initSidebar();
    this.initSidebarPersistence();
    this.initForms();
    this.initTables();
    this.initEditors();
    this.initModals();
    this.initSearch();
    this.initNotifications();
    this.initKeyboardShortcuts();
    this.checkUnsavedChanges();
};

// ===== SIDEBAR FUNCTIONALITY =====
AdminApp.initSidebar = function () {
    const sidebar = document.getElementById('sidebar');
    const toggleBtn = document.querySelector('.mobile-toggle');

    // GUARD: exit jika sidebar tidak ada
    if (!sidebar) return;

    // Create overlay untuk mobile (sekali saja)
    let overlay = document.querySelector('.sidebar-overlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.className = 'sidebar-overlay';
        overlay.style.cssText = `
            position: fixed; inset: 0; background: rgba(0,0,0,0.5);
            z-index: 99; opacity: 0; visibility: hidden; transition: all 0.3s ease;
        `;
        document.body.appendChild(overlay);
    }

    // Toggle sidebar (guard tombol)
    if (toggleBtn) {
        toggleBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            this.toggleSidebar(sidebar, overlay);
        });
    }

    // Tutup saat overlay diklik
    overlay.addEventListener('click', () => this.closeSidebar(sidebar, overlay));

    // Tutup dengan tombol Escape
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && AdminApp.state.sidebarOpen) {
            this.closeSidebar(sidebar, overlay);
        }
    });

    // Reset state mobile saat resize ke desktop
    window.addEventListener('resize', () => {
        if (window.innerWidth > this.config.sidebarBreakpoint && AdminApp.state.sidebarOpen) {
            this.closeSidebar(sidebar, overlay);
        }
    });

    // Highlight menu aktif sesuai URL
    const currentPath = window.location.pathname.split('/').pop();
    document.querySelectorAll('.sidebar-nav a').forEach(link => {
        const href = link.getAttribute('href');
        if (href && href === currentPath) link.classList.add('active');
    });
};

AdminApp.initSidebarPersistence = function () {
    const sidebar = document.getElementById('sidebar');
    const collapseBtn = document.getElementById('collapseBtn');
    if (!sidebar) return;

    // Terapkan state collapse yang tersimpan
    try {
        if (localStorage.getItem('pbsi_sidebar_collapsed') === '1') {
            sidebar.classList.add('collapsed');
        }
    } catch (e) { /* localStorage diblokir - abaikan */ }

    // Simpan state setiap kali tombol collapse diklik
    if (collapseBtn) {
        collapseBtn.addEventListener('click', () => {
            try {
                localStorage.setItem('pbsi_sidebar_collapsed', sidebar.classList.contains('collapsed') ? '1' : '0');
            } catch (e) { /* abaikan */ }
        });
    }
};

AdminApp.toggleSidebar = function (sidebar, overlay) {
    AdminApp.state.sidebarOpen = !AdminApp.state.sidebarOpen;
    if (AdminApp.state.sidebarOpen) {
        sidebar.classList.add('open');
        overlay.style.opacity = '1';
        overlay.style.visibility = 'visible';
        document.body.style.overflow = 'hidden';
    } else {
        this.closeSidebar(sidebar, overlay);
    }
};

AdminApp.closeSidebar = function (sidebar, overlay) {
    AdminApp.state.sidebarOpen = false;
    sidebar.classList.remove('open');
    if (overlay) {
        overlay.style.opacity = '0';
        overlay.style.visibility = 'hidden';
    }
    document.body.style.overflow = '';
};

// ===== FORM HANDLING =====
AdminApp.initForms = function () {
    // Auto-generate slug dari judul/nama
    const titleInput = document.querySelector('input[name="judul"], input[name="nama"]');
    const slugInput = document.querySelector('input[name="slug"]');

    if (titleInput && slugInput) {
        titleInput.addEventListener('input', function () {
            if (slugInput.dataset.auto !== 'false') {
                slugInput.value = this.value.toLowerCase()
                    .replace(/[^a-z0-9]+/g, '-')
                    .replace(/(^-|-$)+/g, '');
            }
        });
        slugInput.addEventListener('input', function () {
            this.dataset.auto = 'false';
        });
    }

    // Image preview generik (dengan guard zona khusus modul)
    const fileInputs = document.querySelectorAll('input[type="file"][accept*="image"]');
    if (fileInputs.length > 0) {
        fileInputs.forEach(input => {
            input.addEventListener('change', function () {
                AdminApp.handleImagePreview(this);
            });
        });
    }

    // Validasi form + loading state saat submit
    const forms = document.querySelectorAll('form[method="POST"]');
    if (forms.length > 0) {
        forms.forEach(form => {
            form.addEventListener('submit', function (e) {
                if (!AdminApp.validateForm(this)) {
                    e.preventDefault();
                    return false;
                }
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn && !submitBtn.disabled) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Menyimpan...';
                    // Safety: re-enable setelah 10 detik jika redirect gagal
                    setTimeout(() => {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<i class="fa-solid fa-save"></i> Simpan';
                    }, 10000);
                }
            });
        });
    }

    // Peringatan sebelum meninggalkan halaman dengan perubahan belum disimpan
    window.addEventListener('beforeunload', function (e) {
        if (AdminApp.state.unsavedChanges) {
            e.preventDefault();
            e.returnValue = '';
        }
    });
};

AdminApp.handleImagePreview = function (input) {
    // SKIP: zona yang sudah ditangani script modul sendiri (anti preview ganda)
    if (input.closest('.upload-zone, .profile-upload-btn, .profile-upload-wrapper')) return;

    const file = input.files[0];
    if (!file) return;

    if (!file.type.startsWith('image/')) {
        AdminApp.showToast('error', 'File harus berupa gambar!');
        input.value = '';
        return;
    }

    if (file.size > 5 * 1024 * 1024) {
        AdminApp.showToast('error', 'Ukuran gambar maksimal 5MB!');
        input.value = '';
        return;
    }

    const reader = new FileReader();
    reader.onload = function (e) {
        let preview = input.parentElement.querySelector('.image-preview');
        if (!preview) {
            preview = document.createElement('div');
            preview.className = 'image-preview';
            preview.style.cssText = 'margin-top: 10px; max-width: 300px;';
            input.parentElement.appendChild(preview);
        }
        preview.innerHTML = `<img src="${e.target.result}" style="max-width: 100%; border-radius: 8px; border: 2px solid var(--border-dark);">`;
    };
    reader.readAsDataURL(file);
};

AdminApp.validateForm = function (form) {
    let isValid = true;
    const requiredFields = form.querySelectorAll('[required]');

    requiredFields.forEach(field => {
        const existingError = field.parentElement ? field.parentElement.querySelector('.field-error') : null;
        if (existingError) existingError.remove();

        if (!field.value.trim()) {
            isValid = false;
            field.style.borderColor = 'var(--danger)';

            const errorMsg = document.createElement('div');
            errorMsg.className = 'field-error';
            errorMsg.style.cssText = 'color: var(--danger); font-size: 0.8rem; margin-top: 4px;';
            errorMsg.textContent = 'Field ini wajib diisi';
            if (field.parentElement) field.parentElement.appendChild(errorMsg);

            field.addEventListener('input', function () {
                this.style.borderColor = '';
                const err = this.parentElement ? this.parentElement.querySelector('.field-error') : null;
                if (err) err.remove();
            }, { once: true });
        } else {
            field.style.borderColor = '';
        }
    });

    return isValid;
};

// ===== TABLE FUNCTIONALITY =====
AdminApp.initTables = function () {
    // Live search tabel
    const searchInputs = document.querySelectorAll('.table-search');
    if (searchInputs.length > 0) {
        searchInputs.forEach(input => {
            input.addEventListener('input', function () {
                AdminApp.filterTable(this);
            });
        });
    }

    // Sortable tables
    const sortableTables = document.querySelectorAll('table[data-sortable]');
    if (sortableTables.length > 0) {
        sortableTables.forEach(table => AdminApp.makeTableSortable(table));
    }

    // Bulk select
    const bulkSelectAll = document.querySelector('.bulk-select-all');
    if (bulkSelectAll) {
        bulkSelectAll.addEventListener('change', function () {
            document.querySelectorAll('.bulk-select-item').forEach(cb => cb.checked = this.checked);
        });
    }

    // Konfirmasi hapus (untuk link yang belum punya onclick confirm)
    const deleteLinks = document.querySelectorAll('a[href*="delete="]:not([onclick])');
    if (deleteLinks.length > 0) {
        deleteLinks.forEach(link => {
            link.addEventListener('click', function (e) {
                if (!confirm('Apakah Anda yakin ingin menghapus data ini? Tindakan ini tidak dapat dibatalkan.')) {
                    e.preventDefault();
                    return false;
                }
            });
        });
    }
};

AdminApp.filterTable = function (searchInput) {
    const panel = searchInput.closest('.panel');
    if (!panel) return;
    const table = panel.querySelector('table');
    if (!table) return;

    const filter = searchInput.value.toLowerCase();
    table.querySelectorAll('tbody tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(filter) ? '' : 'none';
    });
};

AdminApp.makeTableSortable = function (table) {
    const headers = table.querySelectorAll('th[data-sortable]');
    if (headers.length === 0) return;

    headers.forEach(th => {
        th.style.cursor = 'pointer';
        if (!th.querySelector('.fa-sort')) {
            th.innerHTML += ' <i class="fa-solid fa-sort" style="opacity: 0.3;"></i>';
        }

        th.addEventListener('click', function () {
            const index = Array.from(this.parentElement.children).indexOf(this);
            const direction = this.dataset.direction === 'asc' ? 'desc' : 'asc';

            headers.forEach(h => {
                h.dataset.direction = '';
                const icon = h.querySelector('i');
                if (icon) icon.className = 'fa-solid fa-sort';
            });

            this.dataset.direction = direction;
            const currentIcon = this.querySelector('i');
            if (currentIcon) currentIcon.className = `fa-solid fa-sort-${direction}`;

            const tbody = table.querySelector('tbody');
            if (!tbody) return;
            const rows = Array.from(tbody.querySelectorAll('tr'));

            rows.sort((a, b) => {
                const aText = (a.children[index] ? a.children[index].textContent : '').trim();
                const bText = (b.children[index] ? b.children[index].textContent : '').trim();
                return direction === 'asc'
                    ? aText.localeCompare(bText, 'id', { numeric: true })
                    : bText.localeCompare(aText, 'id', { numeric: true });
            });

            rows.forEach(row => tbody.appendChild(row));
        });
    });
};

// ===== WYSIWYG EDITOR (untuk textarea ber-atribut data-editor) =====
AdminApp.initEditors = function () {
    const textareas = document.querySelectorAll('textarea[data-editor="wysiwyg"]');
    if (textareas.length === 0) return;

    textareas.forEach(textarea => {
        const toolbar = document.createElement('div');
        toolbar.className = 'editor-toolbar';
        toolbar.style.cssText = `
            background: var(--bg-darker); border: 1px solid var(--border-dark);
            border-bottom: none; border-radius: var(--radius-md) var(--radius-md) 0 0;
            padding: 8px; display: flex; gap: 4px; flex-wrap: wrap;
        `;

        const buttons = [
            { cmd: 'bold', icon: 'fa-bold', title: 'Bold' },
            { cmd: 'italic', icon: 'fa-italic', title: 'Italic' },
            { cmd: 'underline', icon: 'fa-underline', title: 'Underline' },
            { cmd: 'insertUnorderedList', icon: 'fa-list-ul', title: 'Bullet List' },
            { cmd: 'insertOrderedList', icon: 'fa-list-ol', title: 'Numbered List' },
            { cmd: 'createLink', icon: 'fa-link', title: 'Link' },
            { cmd: 'removeFormat', icon: 'fa-eraser', title: 'Clear Format' }
        ];

        buttons.forEach(btn => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'editor-btn';
            button.innerHTML = `<i class="fa-solid ${btn.icon}"></i>`;
            button.title = btn.title;
            button.style.cssText = `
                background: transparent; border: 1px solid transparent; color: var(--text-main);
                padding: 4px 8px; border-radius: 4px; cursor: pointer; transition: all 0.2s;
            `;
            button.addEventListener('click', function (e) {
                e.preventDefault();
                textarea.focus();
                if (btn.cmd === 'createLink') {
                    const url = prompt('Masukkan URL:');
                    if (url) document.execCommand('createLink', false, url);
                } else {
                    document.execCommand(btn.cmd, false, null);
                }
                textarea.dispatchEvent(new Event('input'));
            });
            toolbar.appendChild(button);
        });

        const wrapper = document.createElement('div');
        wrapper.className = 'editor-wrapper';
        wrapper.style.cssText = 'border: 1px solid var(--border-dark); border-radius: var(--radius-md); overflow: hidden;';
        textarea.parentNode.insertBefore(wrapper, textarea);
        wrapper.appendChild(toolbar);
        wrapper.appendChild(textarea);
        textarea.style.cssText = `
            border: none; border-top: 1px solid var(--border-dark);
            border-radius: 0 0 var(--radius-md) var(--radius-md);
            resize: vertical; min-height: 200px; width: 100%;
        `;
    });
};

// ===== MODAL / ALERT SYSTEM =====
AdminApp.initModals = function () {
    const alerts = document.querySelectorAll('.alert');
    if (alerts.length === 0) return;
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.3s ease';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });
};

// ===== SEARCH FUNCTIONALITY =====
AdminApp.initSearch = function () {
    const globalSearch = document.getElementById('globalSearch');
    if (!globalSearch) return;

    let debounceTimer = null;
    globalSearch.addEventListener('input', function () {
        const query = this.value.toLowerCase().trim();
        clearTimeout(debounceTimer);
        if (query.length < 2) return;
        debounceTimer = setTimeout(() => {
            console.log('Searching for:', query);
        }, 300);
    });
};

// ===== NOTIFICATION SYSTEM =====
AdminApp.initNotifications = function () {
    const urlParams = new URLSearchParams(window.location.search);
    const msg = urlParams.get('msg');
    if (!msg) return;

    const messages = {
        'saved': ['success', 'Data berhasil disimpan!'],
        'deleted': ['success', 'Data berhasil dihapus!'],
        'cleared': ['success', 'Semua log berhasil dihapus!'],
        'error': ['error', 'Terjadi kesalahan!'],
        'unauthorized': ['warning', 'Akses ditolak!']
    };

    if (messages[msg]) {
        AdminApp.showToast(messages[msg][0], messages[msg][1]);
        // Bersihkan parameter dari URL tanpa reload
        const newUrl = window.location.pathname;
        window.history.replaceState({}, document.title, newUrl);
    }
};

AdminApp.showToast = function (type, message) {
    const colors = {
        success: 'var(--success)',
        error: 'var(--danger)',
        warning: 'var(--warning)',
        info: 'var(--info)'
    };
    const icons = {
        success: 'fa-check-circle',
        error: 'fa-exclamation-circle',
        warning: 'fa-exclamation-triangle',
        info: 'fa-info-circle'
    };

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.style.cssText = `
        position: fixed; top: 20px; right: 20px; padding: 1rem 1.5rem;
        background: ${colors[type] || colors.info}; color: #fff;
        border-radius: var(--radius-md); box-shadow: var(--shadow-lg);
        z-index: 10000; display: flex; align-items: center; gap: 10px;
        animation: plToastIn 0.3s ease; max-width: 400px;
    `;
    toast.innerHTML = `<i class="fa-solid ${icons[type] || icons.info}"></i><span>${message}</span>`;
    document.body.appendChild(toast);

    setTimeout(() => {
        toast.style.animation = 'plToastOut 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, AdminApp.config.toastDuration);
};

// Animasi toast (namespace unik agar tidak bentrok)
(function () {
    const style = document.createElement('style');
    style.textContent = `
        @keyframes plToastIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        @keyframes plToastOut { from { transform: translateX(0); opacity: 1; } to { transform: translateX(100%); opacity: 0; } }
    `;
    document.head.appendChild(style);
})();

// ===== KEYBOARD SHORTCUTS =====
AdminApp.initKeyboardShortcuts = function () {
    document.addEventListener('keydown', function (e) {
        // Ctrl/Cmd + K: fokus ke search
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
            e.preventDefault();
            const searchInput = document.querySelector('.header-search input, .table-search, #sidebarSearch');
            if (searchInput) searchInput.focus();
        }

        // Ctrl/Cmd + S: submit form aktif
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
            const form = document.querySelector('form[method="POST"]');
            if (form) {
                e.preventDefault();
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn) submitBtn.click();
            }
        }

        // Escape: tutup sidebar mobile
        if (e.key === 'Escape') {
            const sidebar = document.getElementById('sidebar');
            if (sidebar && sidebar.classList.contains('open')) {
                AdminApp.closeSidebar(sidebar, document.querySelector('.sidebar-overlay'));
            }
        }
    });
};

// ===== UNSAVED CHANGES DETECTION =====
AdminApp.checkUnsavedChanges = function () {
    const forms = document.querySelectorAll('form[method="POST"]');
    if (forms.length === 0) return;

    forms.forEach(form => {
        const inputs = form.querySelectorAll('input, textarea, select');
        inputs.forEach(input => {
            input.addEventListener('change', function () {
                AdminApp.state.unsavedChanges = true;
            });
        });
        form.addEventListener('submit', function () {
            AdminApp.state.unsavedChanges = false;
        });
    });
};

// ===== UTILITY FUNCTIONS =====
AdminApp.confirm = function (message) {
    // Fallback jika SweetAlert2 gagal dimuat (CDN offline)
    if (typeof Swal === 'undefined') {
        return Promise.resolve({ isConfirmed: confirm(message) });
    }
    return Swal.fire({
        title: 'Konfirmasi',
        text: message,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#8A1538',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Ya, Lanjutkan',
        cancelButtonText: 'Batal'
    });
};

AdminApp.loading = function (show = true) {
    const existing = document.getElementById('loading-overlay');
    if (show) {
        if (!existing) {
            const div = document.createElement('div');
            div.id = 'loading-overlay';
            div.style.cssText = `
                position: fixed; inset: 0; background: rgba(0,0,0,0.7);
                z-index: 9999; display: flex; align-items: center; justify-content: center;
            `;
            div.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin" style="font-size: 3rem; color: var(--sky-blue);"></i>';
            document.body.appendChild(div);
        }
    } else if (existing) {
        existing.remove();
    }
};

// ===== EXPORT FOR GLOBAL USE =====
window.AdminApp = AdminApp;

// ===== AUTO-INIT ON DOM READY (SATU-SATUNYA LISTENER) =====
document.addEventListener('DOMContentLoaded', () => {
    AdminApp.init();
});