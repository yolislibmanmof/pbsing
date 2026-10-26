<?php
/**
 * =====================================================================
 *  PUSAT KONTROL NAVBAR — PBSI UMMaumere
 * =====================================================================
 *  Edit menu CUKUP di file ini. TIDAK PERLU buka phpMyAdmin lagi.
 *
 *  CARA PAKAI:
 *  - Tambah menu utama   : copy satu blok [ ... ], tempel di urutan diinginkan
 *  - Tambah submenu      : copy satu blok di dalam 'children'
 *  - Ganti nama/icon     : ubah nilai 'label' / 'icon' (class Font Awesome)
 *  - Sembunyikan menu    : tambah baris  'hidden' => true,
 *  - Urutan              : sesuai urutan penulisan blok (atas = paling kiri)
 *
 *  Icon: https://fontawesome.com/search (pakai class lengkap, mis. fa-solid fa-house)
 * =====================================================================
 */

return [

    // ============ 1. BERANDA ============
    [
        'label' => 'Beranda',
        'url'   => 'index.php',
        'icon'  => 'fa-solid fa-house',
    ],

    // ============ 2. PROFIL (DROPDOWN) ============
    [
        'label' => 'Profil',
        'url'   => '#',
        'icon'  => 'fa-solid fa-users',
        'children' => [
            ['label' => 'Sejarah',             'url' => 'page.php?slug=sejarah', 'icon' => 'fa-solid fa-landmark'],
            ['label' => 'Visi & Misi',         'url' => 'visi_misi.php',         'icon' => 'fa-solid fa-bullseye'],
            ['label' => 'Struktur Organisasi', 'url' => 'struktur.php',          'icon' => 'fa-solid fa-sitemap'],
            ['label' => 'Sambutan Kaprodi',    'url' => 'sambutan.php',          'icon' => 'fa-solid fa-comment-dots'],
            ['label' => 'Dosen & Pengajar',    'url' => 'dosen.php',             'icon' => 'fa-solid fa-chalkboard-user'],
            ['label' => 'Alumni',              'url' => 'alumni.php',            'icon' => 'fa-solid fa-user-graduate'],
        ],
    ],

    // ============ 3. AKADEMIK (DROPDOWN) ============
    [
        'label' => 'Akademik',
        'url'   => '#',
        'icon'  => 'fa-solid fa-graduation-cap',
        'children' => [
            ['label' => 'Kurikulum',         'url' => 'kurikulum.php',   'icon' => 'fa-solid fa-book-open'],
            ['label' => 'Konsentrasi Studi', 'url' => 'konsentrasi.php', 'icon' => 'fa-solid fa-layer-group'],
            ['label' => 'Publikasi Ilmiah',  'url' => 'publikasi.php',   'icon' => 'fa-solid fa-flask'],
            ['label' => 'Agenda & Kalender', 'url' => 'agenda.php',      'icon' => 'fa-solid fa-calendar-days'],
            ['label' => 'Unduhan & RPS',     'url' => 'unduh.php',       'icon' => 'fa-solid fa-download'],
        ],
    ],

    // ============ 4. JEJAK & MITRA (DROPDOWN) ============
    [
        'label' => 'Jejak & Mitra',
        'url'   => '#',
        'icon'  => 'fa-solid fa-trophy',
        'children' => [
            ['label' => 'Fasilitas',         'url' => 'fasilitas.php', 'icon' => 'fa-solid fa-building'],
            ['label' => 'Mitra & Kerja Sama','url' => 'mitra.php',     'icon' => 'fa-solid fa-handshake'],
            ['label' => 'Prestasi',          'url' => 'prestasi.php',  'icon' => 'fa-solid fa-trophy'],
            ['label' => 'Prospek Karir',     'url' => 'karir.php',     'icon' => 'fa-solid fa-briefcase'],
        ],
    ],

    // ============ 5. INFORMASI (DROPDOWN) ============
    [
        'label' => 'Informasi',
        'url'   => '#',
        'icon'  => 'fa-solid fa-circle-info',
        'children' => [
            ['label' => 'FAQ',           'url' => 'faq.php',       'icon' => 'fa-solid fa-circle-question'],
            ['label' => 'Pengumuman',    'url' => 'pengumuman.php','icon' => 'fa-solid fa-bullhorn'],
            ['label' => 'Pusat Dokumen', 'url' => 'dokumen.php',   'icon' => 'fa-solid fa-folder-open'],
            ['label' => 'Galeri',        'url' => 'galeri.php',    'icon' => 'fa-solid fa-images'],
            ['label' => 'Berita',        'url' => 'berita.php',    'icon' => 'fa-solid fa-newspaper'],
        ],
    ],

    // ============ 6. KONTAK ============
    [
        'label' => 'Kontak',
        'url'   => 'kontak.php',
        'icon'  => 'fa-solid fa-envelope',
    ],

    // CONTOH menu disembunyikan (hapus tanda // untuk memakai):
    // [
    //     'label'  => 'Uji Coba',
    //     'url'    => 'uji.php',
    //     'icon'   => 'fa-solid fa-flask',
    //     'hidden' => true,
    // ],
];