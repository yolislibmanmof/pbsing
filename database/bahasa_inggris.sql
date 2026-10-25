-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Oct 23, 2026 at 11:35 PM
-- Server version: 5.7.39
-- PHP Version: 8.2.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `bahasa_inggris`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_logs`
--

CREATE TABLE `admin_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT '0',
  `user_name` varchar(100) NOT NULL DEFAULT 'tamu',
  `action` varchar(60) NOT NULL,
  `detail` varchar(500) NOT NULL DEFAULT '',
  `ip` varchar(45) NOT NULL DEFAULT '',
  `ua` varchar(255) NOT NULL DEFAULT '',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `agenda`
--

CREATE TABLE `agenda` (
  `id` int(11) NOT NULL,
  `judul` varchar(200) NOT NULL,
  `tanggal_mulai` datetime NOT NULL,
  `tanggal_selesai` datetime DEFAULT NULL,
  `lokasi` varchar(150) DEFAULT 'Gedung FKIP',
  `deskripsi` text,
  `tipe` enum('Seminar','Ujian','Wisuda','Workshop','Lainnya') DEFAULT 'Lainnya',
  `status` enum('akan_datang','berlangsung','selesai') DEFAULT 'akan_datang'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `agenda`
--

INSERT INTO `agenda` (`id`, `judul`, `tanggal_mulai`, `tanggal_selesai`, `lokasi`, `deskripsi`, `tipe`, `status`) VALUES
(1, 'Yudisium Periode I 2026', '2026-11-10 08:00:00', '2026-11-10 12:00:00', 'Aula Kampus', 'Yudisium lulusan semester ganjil tahun akademik 2026/2027.', 'Wisuda', 'akan_datang'),
(2, 'Seminar Nasional Sastra & Budaya', '2026-11-25 09:00:00', NULL, 'Gedung FKIP', 'Membahas sastra lokal Nusa Tenggara dalam perspektif global.', 'Seminar', 'akan_datang'),
(3, 'Workshop CAT Tools untuk Penerjemah', '2026-10-15 09:00:00', '2026-10-15 15:00:00', 'Language Lab', 'Pelatihan dasar Trados bagi mahasiswa semester akhir.', 'Workshop', 'selesai');

-- --------------------------------------------------------

--
-- Table structure for table `alumni`
--

CREATE TABLE `alumni` (
  `id` int(11) NOT NULL,
  `nama` varchar(150) NOT NULL,
  `angkatan` year(4) NOT NULL,
  `pekerjaan` varchar(150) DEFAULT '',
  `perusahaan` varchar(150) DEFAULT '',
  `sektor` enum('BUMN','Swasta','PNS/TNI/Polri','Akademisi','Wirausaha','Lainnya') DEFAULT 'Lainnya',
  `testimoni` text,
  `foto` varchar(255) DEFAULT '',
  `linkedin` varchar(255) DEFAULT '',
  `ditampilkan` tinyint(4) DEFAULT '1',
  `urutan` int(11) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `alumni`
--

INSERT INTO `alumni` (`id`, `nama`, `angkatan`, `pekerjaan`, `perusahaan`, `sektor`, `testimoni`, `foto`, `linkedin`, `ditampilkan`, `urutan`) VALUES
(1, 'Markus Laga', '2018', 'English Teacher', 'SMA Negeri 1 Maumere', 'PNS/TNI/Polri', 'Kuliah di PBSI membentuk saya menjadi pendidik yang percaya diri dan kreatif.', '', '', 1, 1),
(2, 'Veronika Sina', '2019', 'Freelance Translator', 'Klien Internasional - Jakarta', 'Swasta', 'Skill CAT Tools yang saya pelajari di kampus langsung terpakai di dunia kerja.', '', '', 1, 2),
(3, 'Yohanes Kopong', '2017', 'Tour Operation Manager', 'PT Flores Wisata', 'Wirausaha', 'Bahasa Inggris membuka pintu rezeki saya di industri pariwisata Flores.', '', '', 1, 3);

-- --------------------------------------------------------

--
-- Table structure for table `berita`
--

CREATE TABLE `berita` (
  `id` int(11) NOT NULL,
  `judul` varchar(250) NOT NULL,
  `slug` varchar(250) NOT NULL,
  `kategori` varchar(100) DEFAULT 'Kegiatan',
  `isi` longtext,
  `gambar` varchar(255) DEFAULT '',
  `penulis` varchar(100) DEFAULT 'Admin',
  `tanggal` date DEFAULT NULL,
  `views` int(11) DEFAULT '0',
  `status` tinyint(4) DEFAULT '1',
  `dibuat` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `berita`
--

INSERT INTO `berita` (`id`, `judul`, `slug`, `kategori`, `isi`, `gambar`, `penulis`, `tanggal`, `views`, `status`, `dibuat`) VALUES
(1, 'Tim Debat PBSI Raih Emas di NUEDC 2026', 'tim-debat-pbsi-raih-emas', 'Prestasi', '<p>Tim Debat Bahasa Inggris berhasil menorehkan prestasi gemilang di tingkat nasional.</p>', 'https://images.unsplash.com/photo-1523050854058-8df90110c9f1?w=800', 'Humas', '2026-10-10', 150, 1, '2026-10-23 12:26:54');

-- --------------------------------------------------------

--
-- Table structure for table `blocked_ips`
--

CREATE TABLE `blocked_ips` (
  `id` int(11) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `reason` varchar(255) NOT NULL DEFAULT '',
  `blocked_until` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `dosen`
--

CREATE TABLE `dosen` (
  `id` int(11) NOT NULL,
  `nama` varchar(150) NOT NULL,
  `nidn` varchar(50) DEFAULT '',
  `gelar_depan` varchar(50) DEFAULT '',
  `gelar_belakang` varchar(50) DEFAULT '',
  `bidang_keahlian` varchar(100) DEFAULT 'Linguistics',
  `foto` varchar(255) DEFAULT '',
  `email` varchar(100) DEFAULT '',
  `urutan` int(11) DEFAULT '1',
  `aktif` tinyint(4) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `dosen`
--

INSERT INTO `dosen` (`id`, `nama`, `nidn`, `gelar_depan`, `gelar_belakang`, `bidang_keahlian`, `foto`, `email`, `urutan`, `aktif`) VALUES
(1, 'John Doe', '08123456789', 'Dr.', 'M.Hum.', 'English Literature', '', 'john@ummaumere.ac.id', 1, 1),
(2, 'Jane Smith', '08987654321', '', 'M.Pd.', 'Applied Linguistics', '', 'jane@ummaumere.ac.id', 2, 1),
(3, 'Maria Goreti', '08123456780', '', 'M.Pd.', 'Translation Studies', '', 'maria@ummaumere.ac.id', 3, 1),
(4, 'Petrus Bata', '08123456781', '', 'M.A.', 'American Literature', '', 'petrus@ummaumere.ac.id', 4, 1),
(5, 'Sinta Dewi', '08123456782', '', 'M.Li.', 'Linguistics', '', 'sinta@ummaumere.ac.id', 5, 1),
(6, 'Andres Fernandez', '08123456783', '', 'M.Hum.', 'Creative Writing', '', 'andres@ummaumere.ac.id', 6, 1);

-- --------------------------------------------------------

--
-- Table structure for table `fitur`
--

CREATE TABLE `fitur` (
  `id` int(11) NOT NULL,
  `icon` varchar(100) DEFAULT 'fa-solid fa-book',
  `judul` varchar(150) NOT NULL,
  `deskripsi` text,
  `urutan` int(11) DEFAULT '1',
  `aktif` tinyint(4) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `fitur`
--

INSERT INTO `fitur` (`id`, `icon`, `judul`, `deskripsi`, `urutan`, `aktif`) VALUES
(1, 'fa-solid fa-headset', 'Language Laboratory', 'Lab bahasa interaktif untuk mengasah pronunciation dan listening skills.', 1, 1),
(2, 'fa-solid fa-feather-pointed', 'Creative Writing Studio', 'Pusat kajian sastra dan penulisan kreatif berbahasa Inggris.', 2, 1),
(3, 'fa-solid fa-language', 'Translation Center', 'Praktik penerjemahan komersial dan tersumpah menggunakan CAT Tools.', 3, 1),
(4, 'fa-solid fa-globe', 'Global Exposure', 'Kelas internasional dan program pertukaran pelajar ke luar negeri.', 4, 1);

-- --------------------------------------------------------

--
-- Table structure for table `galeri`
--

CREATE TABLE `galeri` (
  `id` int(11) NOT NULL,
  `judul` varchar(200) NOT NULL,
  `kategori` varchar(100) DEFAULT 'Dokumentasi',
  `file` varchar(255) DEFAULT '',
  `tipe` enum('image','video') DEFAULT 'image',
  `tanggal` date DEFAULT NULL,
  `urutan` int(11) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `galeri`
--

INSERT INTO `galeri` (`id`, `judul`, `kategori`, `file`, `tipe`, `tanggal`, `urutan`) VALUES
(1, 'Pementasan Teater Shakespeare', 'Sastra', 'https://images.unsplash.com/photo-1507413245164-6160d8298b31?w=900', 'image', '2026-09-01', 1),
(2, 'Final English Debate Championship', 'Prestasi', 'https://images.unsplash.com/photo-1523050854058-8df90110c9f1?w=900', 'image', '2026-08-15', 2),
(3, 'Praktikum Translation Studio', 'Akademik', 'https://images.unsplash.com/photo-1513464962467-1fc2d04710b0?w=900', 'image', '2026-07-19', 3),
(4, 'Kuliah Tamu Dosen Luar Negeri', 'Akademik', 'https://images.unsplash.com/photo-1524178232363-1fb2b075b63e?w=900', 'image', '2026-06-11', 4),
(5, 'Wisuda & Pelepasan Lulusan', 'Kegiatan', 'https://images.unsplash.com/photo-1522202176988-66273c2fd55f?w=900', 'image', '2026-05-08', 5),
(6, 'Language Lab Session', 'Akademik', 'https://images.unsplash.com/photo-1581091226825-a6a2a5aee158?w=900', 'image', '2026-04-02', 6);

-- --------------------------------------------------------

--
-- Table structure for table `heroes`
--

CREATE TABLE `heroes` (
  `id` int(11) NOT NULL,
  `tipe` enum('image','video') NOT NULL DEFAULT 'image',
  `file` varchar(255) NOT NULL DEFAULT '',
  `judul` varchar(255) DEFAULT '',
  `subtitle` varchar(255) DEFAULT '',
  `badge` varchar(150) DEFAULT '',
  `urutan` int(11) DEFAULT '1',
  `aktif` tinyint(4) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `heroes`
--

INSERT INTO `heroes` (`id`, `tipe`, `file`, `judul`, `subtitle`, `badge`, `urutan`, `aktif`) VALUES
(1, 'image', 'https://images.unsplash.com/photo-1513464962467-1fc2d04710b0?w=1600', 'Connecting Worlds Through Words', 'Program Studi Bahasa dan Sastra Inggris UMMaumere', 'TERAKREDITASI BAIK SEKALI', 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `username` varchar(100) NOT NULL DEFAULT '',
  `attempted_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `login_attempts`
--

INSERT INTO `login_attempts` (`id`, `ip`, `username`, `attempted_at`) VALUES
(1, '::1', 'admin', '2026-10-23 17:33:38'),
(2, '::1', 'admin', '2026-10-23 17:33:42');

-- --------------------------------------------------------

--
-- Table structure for table `mata_kuliah`
--

CREATE TABLE `mata_kuliah` (
  `id` int(11) NOT NULL,
  `kode_mk` varchar(20) NOT NULL,
  `nama_mk` varchar(150) NOT NULL,
  `semester` tinyint(4) NOT NULL,
  `sks` tinyint(4) DEFAULT '2',
  `kategori` enum('Wajib','Pilihan','MKKU') DEFAULT 'Wajib',
  `deskripsi` text,
  `urutan` int(11) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `mata_kuliah`
--

INSERT INTO `mata_kuliah` (`id`, `kode_mk`, `nama_mk`, `semester`, `sks`, `kategori`, `deskripsi`, `urutan`) VALUES
(1, 'ENG101', 'Comprehensive English I', 1, 3, 'Wajib', 'Keterampilan bahasa terpadu: reading, listening, speaking, writing tingkat dasar.', 1),
(2, 'ENG102', 'Grammar in Use', 1, 3, 'Wajib', 'Tata bahasa Inggris fundamental dalam konteks autentik.', 2),
(3, 'ENG103', 'Academic Listening', 1, 3, 'Wajib', 'Strategi mendengar kuliah dan diskusi akademik berbahasa Inggris.', 3),
(4, 'ENG104', 'Introduction to Linguistics', 1, 3, 'Wajib', 'Pengantar ilmu bahasa: fonetik, morfologi, sintaksis, dan semantik.', 4),
(5, 'AIK101', 'Al-Islam & Kemuhammadiyahan I', 1, 2, 'MKKU', 'Dasar-dasar keislaman dan ideologi Muhammadiyah.', 5),
(6, 'MKKU101', 'Pancasila', 1, 2, 'MKKU', 'Nilai-nilai Pancasila dalam kehidupan berbangsa dan global.', 6),
(7, 'ENG201', 'Comprehensive English II', 2, 3, 'Wajib', 'Lanjutan keterampilan bahasa terpadu tingkat menengah.', 1),
(8, 'ENG202', 'Academic Writing', 2, 3, 'Wajib', 'Penulisan paragraf, esai, dan karya ilmiah berbahasa Inggris.', 2),
(9, 'ENG203', 'Speaking for Communication', 2, 3, 'Wajib', 'Latihan kelancaran berbicara untuk komunikasi harian dan profesional.', 3),
(10, 'ENG204', 'Phonetics & Phonology', 2, 3, 'Wajib', 'Analisis bunyi bahasa Inggris dan praktik pronunciation.', 4),
(11, 'ENG205', 'Critical Reading', 2, 2, 'Wajib', 'Strategi membaca kritis teks akademik dan media.', 5),
(12, 'AIK102', 'Al-Islam & Kemuhammadiyahan II', 2, 2, 'MKKU', 'Ibadah, muamalah, dan etika sosial Islam.', 6),
(13, 'MKKU102', 'Kewarganegaraan', 2, 2, 'MKKU', 'Hak dan kewajiban warga negara dalam negara demokratis.', 7),
(14, 'ENG301', 'Morphology & Syntax', 3, 3, 'Wajib', 'Struktur kata dan kalimat bahasa Inggris secara mendalam.', 1),
(15, 'ENG302', 'Semantics & Pragmatics', 3, 3, 'Wajib', 'Makna dalam bahasa dan penggunaan bahasa dalam konteks.', 2),
(16, 'ENG303', 'British Literature', 3, 3, 'Wajib', 'Karya besar sastra Inggris dari era Beowulf hingga modern.', 3),
(17, 'ENG304', 'Translation Theory', 3, 3, 'Wajib', 'Konsep, prinsip, dan pendekatan dalam studi penerjemahan.', 4),
(18, 'ENG305', 'Public Speaking', 3, 2, 'Wajib', 'Teknik presentasi dan pidato persuasif berbahasa Inggris.', 5),
(19, 'AIK103', 'Al-Islam & Kemuhammadiyahan III', 3, 2, 'MKKU', 'Islam dan isu-isu kontemporer kemanusiaan.', 6),
(20, 'MKKU103', 'Bahasa Indonesia', 3, 2, 'MKKU', 'Bahasa Indonesia akademik untuk publikasi ilmiah.', 7),
(21, 'ENG401', 'American Literature', 4, 3, 'Wajib', 'Sastra Amerika: kolonial, modernisme, hingga kontemporer.', 1),
(22, 'ENG402', 'Literary Criticism & Theory', 4, 3, 'Wajib', 'Teori dan aliran kritik sastra untuk analisis teks.', 2),
(23, 'ENG403', 'Translation Practice', 4, 3, 'Wajib', 'Praktik penerjemahan teks umum dan semi-spesialis.', 3),
(24, 'ENG404', 'TEFL Methodology', 4, 3, 'Wajib', 'Metodologi pengajaran bahasa Inggris sebagai bahasa asing.', 4),
(25, 'ENG405', 'Sociolinguistics', 4, 2, 'Wajib', 'Bahasa dan masyarakat: variasi, dialek, dan identitas.', 5),
(26, 'ENG406', 'Creative Writing', 4, 2, 'Pilihan', 'Menulis puisi, cerpen, dan naskah drama berbahasa Inggris.', 6),
(27, 'AIK104', 'Al-Islam & Kemuhammadiyahan IV', 4, 2, 'MKKU', 'Kepemimpinan dan gerakan pencerahan Muhammadiyah.', 7),
(28, 'ENG501', 'Applied Linguistics', 5, 3, 'Wajib', 'Penerapan linguistik dalam pengajaran dan penilaian bahasa.', 1),
(29, 'ENG502', 'Post-Colonial Literature', 5, 3, 'Wajib', 'Sastra pascakolonial Asia, Afrika, dan Pasifik.', 2),
(30, 'ENG503', 'Interpreting', 5, 3, 'Wajib', 'Teknik interpreting konsekutif untuk acara formal.', 3),
(31, 'ENG504', 'Curriculum & Materials Development', 5, 3, 'Wajib', 'Pengembangan kurikulum dan bahan ajar bahasa Inggris.', 4),
(32, 'ENG505', 'Discourse Analysis', 5, 3, 'Wajib', 'Analisis wacana lisan, tulis, dan digital.', 5),
(33, 'ENG506', 'Drama & Poetry Performance', 5, 2, 'Pilihan', 'Pementasan drama dan pembacaan puisi karya sastrawan dunia.', 6),
(34, 'ENG601', 'Research Methodology', 6, 3, 'Wajib', 'Metode penelitian linguistik, sastra, dan penerjemahan.', 1),
(35, 'ENG602', 'English for Specific Purposes', 6, 3, 'Wajib', 'Bahasa Inggris untuk bisnis, pariwisata, dan akademik.', 2),
(36, 'ENG603', 'Children\'s Literature', 6, 2, 'Pilihan', 'Kajian dan penulisan sastra anak berbahasa Inggris.', 3),
(37, 'ENG604', 'Media & Journalism English', 6, 2, 'Pilihan', 'Penulisan berita dan konten media berbahasa Inggris.', 4),
(38, 'ENG605', 'Kuliah Kerja Nyata', 6, 3, 'Wajib', 'Pengabdian masyarakat lintas disiplin berbasis pemberdayaan.', 5),
(39, 'ENG606', 'Magang Profesi / MBKM', 6, 3, 'Wajib', 'Magang di industri bahasa, media, atau institusi mitra.', 6),
(40, 'ENG701', 'Seminar Proposal Skripsi', 7, 3, 'Wajib', 'Penyusunan dan pemaparan proposal penelitian skripsi.', 1),
(41, 'ENG702', 'CAT Tools & Machine Translation', 7, 3, 'Pilihan', 'Trados, MemoQ, dan post-editing terjemahan mesin.', 2),
(42, 'ENG703', 'Forensic Linguistics', 7, 2, 'Pilihan', 'Bahasa sebagai barang bukti dalam proses hukum.', 3),
(43, 'ENG704', 'Film & Literary Adaptation', 7, 2, 'Pilihan', 'Kajian adaptasi karya sastra ke layar dan media baru.', 4),
(44, 'ENG705', 'Professional Communication', 7, 2, 'Wajib', 'Komunikasi profesional: negosiasi, email, dan wawancara kerja.', 5),
(45, 'ENG706', 'Academic Publication Writing', 7, 2, 'Wajib', 'Menulis artikel ilmiah untuk jurnal nasional dan internasional.', 6),
(46, 'ENG801', 'Skripsi', 8, 6, 'Wajib', 'Penelitian mandiri berbahasa Inggris di bawah bimbingan dosen.', 1),
(47, 'ENG802', 'Ujian Komprehensif', 8, 2, 'Wajib', 'Ujian lisan dan tulis penguasaan bidang studi menyeluruh.', 2);

-- --------------------------------------------------------

--
-- Table structure for table `menus`
--

CREATE TABLE `menus` (
  `id` int(11) NOT NULL,
  `label` varchar(100) NOT NULL,
  `icon` varchar(100) DEFAULT 'fa-solid fa-circle',
  `url` varchar(255) NOT NULL DEFAULT '',
  `parent_id` int(11) DEFAULT '0',
  `urutan` int(11) DEFAULT '1',
  `aktif` tinyint(4) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `menus`
--

INSERT INTO `menus` (`id`, `label`, `icon`, `url`, `parent_id`, `urutan`, `aktif`) VALUES
(1, 'Beranda', 'fa-solid fa-house', 'index.php', 0, 1, 1),
(2, 'Profil', 'fa-solid fa-users', '#', 0, 2, 1),
(3, 'Akademik', 'fa-solid fa-graduation-cap', '#', 0, 3, 1),
(4, 'Berita', 'fa-solid fa-newspaper', 'berita.php', 0, 4, 1),
(5, 'Galeri', 'fa-solid fa-images', 'galeri.php', 0, 5, 1),
(6, 'Kontak', 'fa-solid fa-envelope', 'kontak.php', 0, 6, 1),
(7, 'Sejarah', 'fa-solid fa-landmark', 'page.php?slug=sejarah', 2, 1, 1),
(8, 'Visi & Misi', 'fa-solid fa-bullseye', 'visi_misi.php', 2, 2, 1),
(9, 'Struktur Organisasi', 'fa-solid fa-sitemap', 'struktur.php', 2, 3, 1),
(10, 'Sambutan Kaprodi', 'fa-solid fa-comment-dots', 'sambutan.php', 2, 4, 1),
(11, 'Dosen & Pengajar', 'fa-solid fa-chalkboard-user', 'dosen.php', 2, 5, 1),
(12, 'Alumni & Tracer Study', 'fa-solid fa-user-graduate', 'alumni.php', 2, 6, 1),
(13, 'Kurikulum', 'fa-solid fa-book-open', 'kurikulum.php', 3, 1, 1),
(14, 'Language Laboratory', 'fa-solid fa-headset', 'page.php?slug=laboratorium', 3, 2, 1),
(15, 'Penelitian & Pengabdian', 'fa-solid fa-flask', 'page.php?slug=penelitian', 3, 3, 1),
(16, 'Publikasi Ilmiah', 'fa-solid fa-newspaper', 'publikasi.php', 3, 4, 1),
(17, 'Agenda & Kalender', 'fa-solid fa-calendar-days', 'agenda.php', 3, 5, 1),
(18, 'Pusat Unduhan & RPS', 'fa-solid fa-download', 'unduh.php', 3, 6, 1);

-- --------------------------------------------------------

--
-- Table structure for table `pages`
--

CREATE TABLE `pages` (
  `id` int(11) NOT NULL,
  `slug` varchar(150) NOT NULL,
  `judul` varchar(200) NOT NULL,
  `konten` longtext,
  `gambar` varchar(255) DEFAULT '',
  `urutan` int(11) DEFAULT '1',
  `status` tinyint(4) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `pages`
--

INSERT INTO `pages` (`id`, `slug`, `judul`, `konten`, `gambar`, `urutan`, `status`) VALUES
(1, 'sejarah', 'Sejarah Program Studi', '<p>Program Studi Bahasa dan Sastra Inggris Universitas Muhammadiyah Maumere didirikan pada tahun 2010 sebagai jawaban atas kebutuhan akan pusat keunggulan bahasa dan sastra di kawasan Indonesia Timur.</p><p>Perjalanan kami ditandai dengan pembukaan Language Laboratory berstandar internasional, kerja sama dengan mitra global, serta lahirnya ratusan alumni yang berkiprah sebagai pendidik, penerjemah, diplomat, dan sastrawan.</p>', '', 1, 1),
(2, 'visi-misi', 'Visi & Misi', '<h3>Visi</h3><p>Menjadi program studi bahasa dan sastra Inggris yang unggul, inovatif, dan berdaya saing global pada tahun 2030.</p><h3>Misi</h3><ul><li>Menyelenggarakan pendidikan bahasa dan sastra Inggris berkualitas berbasis OBE.</li><li>Mengembangkan riset linguistik dan sastra yang berdampak bagi masyarakat.</li><li>Menjalin kerja sama internasional di bidang pendidikan dan kebudayaan.</li><li>Menghasilkan lulusan yang berintegritas, komunikatif, dan adaptif.</li></ul>', '', 2, 1),
(3, 'struktur-organisasi', 'Struktur Organisasi', '<p>Struktur organisasi Prodi Bahasa dan Sastra Inggris terdiri dari Ketua Program Studi, Sekretaris Program Studi, Koordinator Language Laboratory, Koordinator Penelitian & Pengabdian, serta dosen dan tenaga kependidikan yang profesional.</p><ul><li>Ketua Program Studi: Dr. John Doe, M.Hum.</li><li>Sekretaris Program Studi: Jane Smith, M.Pd.</li><li>Koordinator Language Lab: Maria Goreti, M.Pd.</li><li>Koordinator Penelitian: Petrus Bata, M.A.</li></ul>', '', 3, 1),
(4, 'kurikulum', 'Kurikulum Berbasis OBE', '<p>Kurikulum disusun berbasis Outcome Based Education (OBE) dengan mata kuliah inti: Comprehensive English, Introduction to Linguistics, English Literature, Academic Writing, Translation Studies, TEFL Methodology, serta mata kuliah pilihan berbasis industri bahasa.</p>', '', 4, 1),
(5, 'laboratorium', 'Language Laboratory', '<p>Prodi memiliki Language Laboratory dengan 40 stasiun belajar multimedia, software pembelajaran interaktif, serta Translation Studio dilengkapi CAT Tools (Trados & MemoQ) untuk praktik penerjemahan profesional.</p>', '', 5, 1),
(6, 'penelitian', 'Penelitian & Pengabdian', '<p>Dosen dan mahasiswa aktif meneliti bidang linguistik terapan, kajian sastra post-kolonial, penerjemahan, dan pengajaran bahasa, dengan pendanaan internal universitas maupun hibah nasional.</p>', '', 6, 1);

-- --------------------------------------------------------

--
-- Table structure for table `publikasi`
--

CREATE TABLE `publikasi` (
  `id` int(11) NOT NULL,
  `judul` text NOT NULL,
  `penulis` varchar(255) NOT NULL,
  `nama_jurnal` varchar(200) NOT NULL,
  `tahun` year(4) NOT NULL,
  `doi` varchar(100) DEFAULT '',
  `link_url` varchar(255) DEFAULT '',
  `indeks` enum('Scopus Q1','Scopus Q2','Scopus Q3','Scopus Q4','Sinta 1','Sinta 2','Lainnya') DEFAULT 'Lainnya',
  `dosen_pengusul` varchar(150) DEFAULT '',
  `urutan` int(11) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `publikasi`
--

INSERT INTO `publikasi` (`id`, `judul`, `penulis`, `nama_jurnal`, `tahun`, `doi`, `link_url`, `indeks`, `dosen_pengusul`, `urutan`) VALUES
(1, 'Code-Switching in Multilingual Classrooms of Flores', 'Dr. John Doe, M.Hum.', 'Journal of Linguistics Research', '2025', '10.1234/jlr.2025.01', '', 'Sinta 2', 'Dr. John Doe', 1),
(2, 'Post-Colonial Identity in Contemporary Indonesian Novels in English', 'Jane Smith, M.Pd.', 'Asian Journal of Literature', '2024', '', '', 'Scopus Q3', 'Jane Smith', 2),
(3, 'Machine Translation Post-Editing Training for EFL Students', 'Maria Goreti, M.Pd.', 'Translation Studies Quarterly', '2026', '', '', 'Sinta 1', 'Maria Goreti', 3);

-- --------------------------------------------------------

--
-- Table structure for table `rps_kurikulum`
--

CREATE TABLE `rps_kurikulum` (
  `id` int(11) NOT NULL,
  `kode_mk` varchar(50) NOT NULL,
  `mata_kuliah` varchar(200) NOT NULL,
  `semester` varchar(20) DEFAULT 'Ganjil',
  `tahun_akademik` varchar(20) DEFAULT '2026/2027',
  `file_path` varchar(255) NOT NULL,
  `ukuran_file` varchar(20) DEFAULT '',
  `jumlah_unduh` int(11) DEFAULT '0',
  `urutan` int(11) DEFAULT '1',
  `tanggal_upload` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `rps_kurikulum`
--

INSERT INTO `rps_kurikulum` (`id`, `kode_mk`, `mata_kuliah`, `semester`, `tahun_akademik`, `file_path`, `ukuran_file`, `jumlah_unduh`, `urutan`, `tanggal_upload`) VALUES
(1, 'ENG101', 'Comprehensive English I', 'Ganjil', '2026/2027', 'uploads/rps-eng101.pdf', '500 KB', 0, 1, '2026-10-23 12:59:45'),
(2, 'ENG205', 'Introduction to Linguistics', 'Ganjil', '2026/2027', 'uploads/rps-eng205.pdf', '480 KB', 0, 2, '2026-10-23 12:59:45'),
(3, 'ENG310', 'American Literature', 'Genap', '2026/2027', 'uploads/rps-eng310.pdf', '520 KB', 0, 3, '2026-10-23 12:59:45');

-- --------------------------------------------------------

--
-- Table structure for table `sambutan`
--

CREATE TABLE `sambutan` (
  `id` int(11) NOT NULL DEFAULT '1',
  `nama` varchar(150) DEFAULT '',
  `jabatan` varchar(150) DEFAULT '',
  `foto` varchar(255) DEFAULT '',
  `isi` text,
  `tanda_tangan` varchar(150) DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `sambutan`
--

INSERT INTO `sambutan` (`id`, `nama`, `jabatan`, `foto`, `isi`, `tanda_tangan`) VALUES
(1, 'Dr. John Doe, M.Hum.', 'Ketua Program Studi Bahasa dan Sastra Inggris', 'https://images.unsplash.com/photo-1560250097-0b93528c311a?w=800', '<p>Assalamu\'alaikum warahmatullahi wabarakatuh, salam sejahtera bagi kita semua. Selamat datang di website resmi Program Studi Bahasa dan Sastra Inggris Universitas Muhammadiyah Maumere.</p><p>Kami berkomitmen menghadirkan pendidikan bahasa yang unggul, riset yang berdampak, serta lingkungan akademik yang kolaboratif. Semoga website ini menjadi jendela informasi yang bermanfaat bagi mahasiswa, mitra, dan masyarakat luas.</p>', 'Dr. John Doe, M.Hum.');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL DEFAULT '1',
  `site_name` varchar(150) NOT NULL DEFAULT 'Bahasa dan Sastra Inggris',
  `site_tagline` varchar(150) DEFAULT 'Mastering Language, Embracing Global Cultures',
  `prodi_name` varchar(150) DEFAULT 'BAHASA DAN SASTRA INGGRIS',
  `fakultas` varchar(150) DEFAULT 'Fakultas Keguruan dan Ilmu Pendidikan (FKIP)',
  `kampus` varchar(150) DEFAULT 'Universitas Muhammadiyah Maumere',
  `email` varchar(100) DEFAULT 'pbsi@ummaumere.ac.id',
  `newsletter_email` varchar(100) DEFAULT 'newsletter@ummaumere.ac.id',
  `telepon` varchar(50) DEFAULT '(0382) 23456',
  `alamat` text,
  `video_promo` varchar(255) DEFAULT 'https://www.youtube.com/embed/dQw4w9WgXcQ',
  `akreditasi` varchar(50) DEFAULT 'BAIK SEKALI',
  `footer_text` varchar(255) DEFAULT '© 2026 PBSI UMMaumere. Hak Cipta Dilindungi.',
  `updated` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `site_name`, `site_tagline`, `prodi_name`, `fakultas`, `kampus`, `email`, `newsletter_email`, `telepon`, `alamat`, `video_promo`, `akreditasi`, `footer_text`, `updated`) VALUES
(1, 'Bahasa dan Sastra Inggris', 'Mastering Language, Embracing Global Cultures', 'BAHASA DAN SASTRA INGGRIS', 'Fakultas Keguruan dan Ilmu Pendidikan (FKIP)', 'Universitas Muhammadiyah Maumere', 'pbsi@ummaumere.ac.id', 'newsletter@ummaumere.ac.id', '(0382) 23456', 'Jl. Angkasa, Maumere, Sikka, NTT', 'https://www.youtube.com/embed/dQw4w9WgXcQ', 'BAIK SEKALI', '© 2026 PBSI UMMaumere. Hak Cipta Dilindungi.', '2026-10-23 12:26:54');

-- --------------------------------------------------------

--
-- Table structure for table `slider_video`
--

CREATE TABLE `slider_video` (
  `id` int(11) NOT NULL,
  `judul` varchar(200) DEFAULT '',
  `subtitle` text,
  `tipe` enum('video','image') DEFAULT 'image',
  `file` varchar(255) NOT NULL,
  `video_url` varchar(255) DEFAULT '',
  `cta_text` varchar(100) DEFAULT 'Selengkapnya',
  `cta_url` varchar(255) DEFAULT '#',
  `badge` varchar(100) DEFAULT '',
  `urutan` int(11) DEFAULT '1',
  `aktif` tinyint(4) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `slider_video`
--

INSERT INTO `slider_video` (`id`, `judul`, `subtitle`, `tipe`, `file`, `video_url`, `cta_text`, `cta_url`, `badge`, `urutan`, `aktif`) VALUES
(1, 'Connecting Worlds Through Words & Literature', 'Program Studi Bahasa dan Sastra Inggris UMMaumere — Mastering Language, Embracing Global Cultures', 'image', 'https://images.unsplash.com/photo-1513464962467-1fc2d04710b0?w=1920', 'https://www.youtube.com/embed/dQw4w9WgXcQ?autoplay=1&mute=1', 'Jelajahi Program', 'berita.php', 'TERAKREDITASI BAIK SEKALI', 1, 1),
(2, 'Language Lab Berstandar Internasional', 'Fasilitas multimedia modern untuk mengasah empat keterampilan berbahasa', 'image', 'https://images.unsplash.com/photo-1581091226825-a6a2a5aee158?w=1920', '', 'Lihat Fasilitas', '#', 'FASILITAS UNGGULAN', 2, 1),
(3, 'Riset & Publikasi Bereputasi', 'Dosen dan mahasiswa aktif meneliti linguistik, sastra, dan penerjemahan', 'image', 'https://images.unsplash.com/photo-1524178232363-1fb2b075b63e?w=1920', '', 'Baca Publikasi', 'publikasi.php', 'PRESTASI DOSEN', 3, 1);

-- --------------------------------------------------------

--
-- Table structure for table `stats`
--

CREATE TABLE `stats` (
  `id` int(11) NOT NULL,
  `label` varchar(100) NOT NULL,
  `nilai` int(11) DEFAULT '0',
  `suffix` varchar(10) DEFAULT '+',
  `icon` varchar(100) DEFAULT 'fa-solid fa-users',
  `urutan` int(11) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `stats`
--

INSERT INTO `stats` (`id`, `label`, `nilai`, `suffix`, `icon`, `urutan`) VALUES
(1, 'Mahasiswa Aktif', 350, '+', 'fa-solid fa-users', 1),
(2, 'Dosen Ahli', 24, '+', 'fa-solid fa-chalkboard-user', 2),
(3, 'Mitra Global', 15, '+', 'fa-solid fa-handshake', 3),
(4, 'Alumni Sukses', 1200, '+', 'fa-solid fa-graduation-cap', 4);

-- --------------------------------------------------------

--
-- Table structure for table `struktur`
--

CREATE TABLE `struktur` (
  `id` int(11) NOT NULL,
  `nama` varchar(150) NOT NULL,
  `jabatan` varchar(150) NOT NULL,
  `foto` varchar(255) DEFAULT '',
  `level` tinyint(4) DEFAULT '1',
  `urutan` int(11) DEFAULT '1',
  `aktif` tinyint(4) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `struktur`
--

INSERT INTO `struktur` (`id`, `nama`, `jabatan`, `foto`, `level`, `urutan`, `aktif`) VALUES
(1, 'Dr. H. Ahmad Fauzi, M.Pd.', 'Dekan Fakultas Keguruan & Ilmu Pendidikan', 'https://images.unsplash.com/photo-1560250097-0b93528c311a?w=400', 1, 1, 1),
(2, 'Dr. John Doe, M.Hum.', 'Ketua Program Studi Bahasa & Sastra Inggris', 'https://images.unsplash.com/photo-1519085360753-af0119f7cbe7?w=400', 2, 1, 1),
(3, 'Jane Smith, M.Pd.', 'Sekretaris Program Studi', 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?w=400', 3, 1, 1),
(4, 'Maria Goreti, M.Pd.', 'Koordinator Language Lab & TIK', 'https://images.unsplash.com/photo-1580489944761-15a19d654956?w=400', 3, 2, 1),
(5, 'Petrus Bata, M.A.', 'Koordinator Penelitian & Pengabdian', 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=400', 3, 3, 1),
(6, 'Sinta Dewi, M.Li.', 'Koordinator Kemahasiswaan & Alumni', 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?w=400', 3, 4, 1),
(7, 'Andres Fernandez, M.Hum.', 'Ketua Kelompok Bidang Sastra & Creative Writing', 'https://images.unsplash.com/photo-1568602471122-7832951cc4c5?w=400', 4, 1, 1),
(8, 'Yuliana Ndiwa, M.Pd.', 'Ketua Kelompok Bidang Linguistik & Penerjemahan', 'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?w=400', 4, 2, 1),
(9, 'Benediktus Moi, S.Pd.', 'Staf Akademik & Administrasi', 'https://images.unsplash.com/photo-1560250097-0b93528c311a?w=400', 4, 3, 1);

-- --------------------------------------------------------

--
-- Table structure for table `unduh`
--

CREATE TABLE `unduh` (
  `id` int(11) NOT NULL,
  `judul` varchar(200) NOT NULL,
  `kategori` enum('Akademik','Administrasi','Pedoman','Lainnya') DEFAULT 'Akademik',
  `file_path` varchar(255) NOT NULL,
  `ukuran_file` varchar(20) DEFAULT '',
  `jumlah_unduh` int(11) DEFAULT '0',
  `tanggal_upload` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `unduh`
--

INSERT INTO `unduh` (`id`, `judul`, `kategori`, `file_path`, `ukuran_file`, `jumlah_unduh`, `tanggal_upload`) VALUES
(1, 'Panduan Akademik 2026/2027', 'Akademik', 'uploads/panduan-akademik.pdf', '2.4 MB', 0, '2026-10-23 12:59:45'),
(2, 'Formulir Pengajuan Judul Skripsi', 'Administrasi', 'uploads/form-skripsi.pdf', '350 KB', 0, '2026-10-23 12:59:45'),
(3, 'Pedoman Penulisan Skripsi PBSI', 'Pedoman', 'uploads/pedoman-skripsi.pdf', '1.8 MB', 0, '2026-10-23 12:59:45');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('superadmin','admin','editor') NOT NULL DEFAULT 'admin',
  `dibuat` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `nama`, `username`, `password`, `role`, `dibuat`) VALUES
(1, 'Super Administrator', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'superadmin', '2026-10-23 12:26:54');

-- --------------------------------------------------------

--
-- Table structure for table `visi_misi`
--

CREATE TABLE `visi_misi` (
  `id` int(11) NOT NULL,
  `tipe` enum('visi','misi','tujuan','nilai') NOT NULL DEFAULT 'misi',
  `judul` varchar(200) DEFAULT '',
  `isi` text NOT NULL,
  `ikon` varchar(100) DEFAULT 'fa-solid fa-star',
  `urutan` int(11) DEFAULT '1',
  `aktif` tinyint(4) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `visi_misi`
--

INSERT INTO `visi_misi` (`id`, `tipe`, `judul`, `isi`, `ikon`, `urutan`, `aktif`) VALUES
(1, 'visi', '', 'Menjadi pusat keunggulan studi bahasa dan sastra Inggris berdaya saing Asia Tenggara pada tahun 2035, yang menghasilkan lulusan intelektual, islami, dan berdaya saing global.', 'fa-solid fa-eye', 1, 1),
(2, 'misi', 'Pendidikan & Pengajaran', 'Menyelenggarakan pendidikan berbasis outcome (OBE) yang menghasilkan lulusan unggul dalam empat keterampilan berbahasa.', 'fa-solid fa-graduation-cap', 1, 1),
(3, 'misi', 'Penelitian', 'Mengembangkan riset inovatif bidang linguistik, sastra, dan penerjemahan yang bermanfaat bagi masyarakat.', 'fa-solid fa-flask', 2, 1),
(4, 'misi', 'Pengabdian Masyarakat', 'Melaksanakan pengabdian melalui penerapan ilmu bahasa dan sastra bagi pemberdayaan masyarakat.', 'fa-solid fa-handshake-angle', 3, 1),
(5, 'misi', 'Kerja Sama Global', 'Membangun kemitraan dengan universitas, industri, dan institusi internasional.', 'fa-solid fa-globe', 4, 1),
(6, 'misi', 'Nilai Keislaman', 'Mengintegrasikan nilai Al-Islam dan Kemuhammadiyahan dalam seluruh aktivitas akademik.', 'fa-solid fa-mosque', 5, 1),
(7, 'tujuan', '', 'Menghasilkan lulusan yang kompeten berbahasa Inggris dan berdaya saing tinggi di dunia kerja.', 'fa-solid fa-bullseye', 1, 1),
(8, 'tujuan', '', 'Menghasilkan riset dan publikasi ilmiah bereputasi nasional dan internasional.', 'fa-solid fa-book', 2, 1),
(9, 'tujuan', '', 'Mewujudkan sivitas akademika yang intelektual, mandiri, dan berakhlak mulia.', 'fa-solid fa-users', 3, 1),
(10, 'tujuan', '', 'Memperkuat peran universitas dalam kancah regional dan global.', 'fa-solid fa-chart-line', 4, 1),
(11, 'nilai', 'Islami', 'Menjunjung nilai Islam dan gerakan Muhammadiyah dalam setiap aktivitas.', 'fa-solid fa-star-and-crescent', 1, 1),
(12, 'nilai', 'Integritas', 'Kejujuran akademik, profesionalisme, dan tanggung jawab.', 'fa-solid fa-shield-halved', 2, 1),
(13, 'nilai', 'Progresif', 'Terbuka terhadap perubahan, inovasi, dan perbaikan berkelanjutan.', 'fa-solid fa-rocket', 3, 1),
(14, 'nilai', 'Kolaboratif', 'Bersinergi dalam keberagaman di tingkat lokal hingga global.', 'fa-solid fa-handshake', 4, 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_logs`
--
ALTER TABLE `admin_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `agenda`
--
ALTER TABLE `agenda`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `alumni`
--
ALTER TABLE `alumni`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `berita`
--
ALTER TABLE `berita`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `blocked_ips`
--
ALTER TABLE `blocked_ips`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `dosen`
--
ALTER TABLE `dosen`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `fitur`
--
ALTER TABLE `fitur`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `galeri`
--
ALTER TABLE `galeri`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `heroes`
--
ALTER TABLE `heroes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `mata_kuliah`
--
ALTER TABLE `mata_kuliah`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_semester` (`semester`);

--
-- Indexes for table `menus`
--
ALTER TABLE `menus`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `pages`
--
ALTER TABLE `pages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `publikasi`
--
ALTER TABLE `publikasi`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `rps_kurikulum`
--
ALTER TABLE `rps_kurikulum`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `sambutan`
--
ALTER TABLE `sambutan`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `slider_video`
--
ALTER TABLE `slider_video`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `stats`
--
ALTER TABLE `stats`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `struktur`
--
ALTER TABLE `struktur`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `unduh`
--
ALTER TABLE `unduh`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `visi_misi`
--
ALTER TABLE `visi_misi`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_logs`
--
ALTER TABLE `admin_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `agenda`
--
ALTER TABLE `agenda`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `alumni`
--
ALTER TABLE `alumni`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `berita`
--
ALTER TABLE `berita`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `blocked_ips`
--
ALTER TABLE `blocked_ips`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `dosen`
--
ALTER TABLE `dosen`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `fitur`
--
ALTER TABLE `fitur`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `galeri`
--
ALTER TABLE `galeri`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `heroes`
--
ALTER TABLE `heroes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `mata_kuliah`
--
ALTER TABLE `mata_kuliah`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT for table `menus`
--
ALTER TABLE `menus`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `pages`
--
ALTER TABLE `pages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `publikasi`
--
ALTER TABLE `publikasi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `rps_kurikulum`
--
ALTER TABLE `rps_kurikulum`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `slider_video`
--
ALTER TABLE `slider_video`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `stats`
--
ALTER TABLE `stats`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `struktur`
--
ALTER TABLE `struktur`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `unduh`
--
ALTER TABLE `unduh`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `visi_misi`
--
ALTER TABLE `visi_misi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
