-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Oct 21, 2026 at 06:17 AM
-- Server version: 8.4.3
-- PHP Version: 8.3.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `rei_cms_v1`
--

-- --------------------------------------------------------

--
-- Table structure for table `api_doc_users`
--

CREATE TABLE `api_doc_users` (
  `id` int UNSIGNED NOT NULL,
  `username` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(150) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `branches`
--

CREATE TABLE `branches` (
  `id` int NOT NULL,
  `branch_number` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `address` text NOT NULL,
  `phone` varchar(50) NOT NULL,
  `hours` varchar(255) NOT NULL,
  `notes` text,
  `map_embed_url` text,
  `map_link` text,
  `sort_order` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `branches`
--

INSERT INTO `branches` (`id`, `branch_number`, `title`, `address`, `phone`, `hours`, `notes`, `map_embed_url`, `map_link`, `sort_order`, `created_at`) VALUES
(164, '1', 'Jakarta Utara - Batavia PIK', 'Rukan Golf Island Blok C No.36, Pantai Indah Kapuk, Jakarta Utara.', '0822-9999-5125', 'Buka : Pukul 10.00 - 21.00 WIB', 'BUKA SETIAP HARI', '', 'https://maps.app.goo.gl/NPBtFPGBvs9uVhVdA', 1, '2026-10-12 23:33:10'),
(165, '2', 'Jakarta Utara - Kelapa Gading', 'LC6 / 38, Jl. Boulevard Bar. Raya No.53, Klp. Gading Bar. Jkt Utara, Daerah Khusus Ibukota Jakarta 14240', '0822 9999 5041', 'Buka : Pukul 09.00 - 20.00 WIB', 'BUKA SETIAP HARI', '', 'https://maps.app.goo.gl/UwXA5T1uNYX6rKWH7', 2, '2026-10-12 23:33:10'),
(166, '3', 'Jakarta Utara - Sunter', 'Metro Sunter Plaza, Jl. Danau Sunter Utara No.10, RT.10/RW.4, Papanggo, Kec. Tj. Priok, Jkt Utara, Daerah Khusus Ibukota Jakarta 14350', '0811-1927-9993', 'Buka : Pukul 10.00 - 21.00 WIB', 'BUKA SETIAP HARI', '', 'https://www.google.com/maps/place/Raja+Emas+Indonesia+Sunter/data=!4m2!3m1!1s0x0:0xa0627611e8f9480e?sa=X&ved=1t:2428&ictx=111', 3, '2026-10-12 23:33:10'),
(167, '4', 'Jakarta Utara - ITC Mangga Dua', 'ITC Mangga Dua, Lantai 5 Blok Iris No.23, Jkt Utara, Daerah Khusus Ibukota Jakarta 14430', '0822 9999 5064', 'Buka : Pukul 10.00 - 18.00 WIB', 'BUKA SETIAP HARI', '', 'https://maps.app.goo.gl/FML8NaGGyCya4Ahw6', 4, '2026-10-12 23:33:10'),
(168, '5', 'Jakarta Selatan - Pondok Indah', 'Jl. Metro Pondok Indah No.161 Blok Tb27, Pd. Pinang, Kec. Kby. Lama, akarta Selatan, 12310', '0822 9999 5034', 'Buka : Pukul 09.00 - 20.00 WIB', 'BUKA SETIAP HARI', '', 'https://share.google/Wl8UCJFmBjofvPHaC', 5, '2026-10-12 23:33:10'),
(169, '6', 'Jakarta Selatan - Plaza Melawai Blok M', 'Jakarta Selatan - Plaza Melawai Blok M, Melawai Plaza, Jl. Melawai Raya.13 18, Blok T No.264 Lantai 1, Melawai, Kec. Kby. Baru, Kota Jakarta Selatan, Daerah Khusus Ibukota Jakarta 12160', '0822 9999 5127', 'Buka : Pukul 10.00 - 19.00 WIB', 'BUKA SETIAP HARI', '', 'https://maps.app.goo.gl/3N5x7zDzEYxC9j567', 6, '2026-10-12 23:33:10'),
(170, '7', 'Jakarta Selatan - Plaza Melawai Blok M 2', 'Jakarta Selatan - Plaza Melawai Blok M 2, Melawai Plaza, Jl. Melawai Raya.13 18, Blok T No.264 Lantai 1, Melawai, Kec. Kby. Baru, Kota Jakarta Selatan, Daerah Khusus Ibukota Jakarta 12160', '0811 1119 9949', 'Buka : Pukul 10.00 - 19.00 WIB', 'BUKA SETIAP HARI', '', 'https://www.google.com/maps/place/Raja+Emas+Indonesia+Melawai+-+Blok+M/@-6.245359,106.7996181,17z/data=!3m1!4b1!4m6!3m5!1s0x2e69f101f624e837:0x9b7f3d7e6f130b5!8m2!3d-6.245359!4d106.802193!16s%2Fg%2F11wx16qksz?entry=ttu&g_ep=EgoyMDI1MDEyMi4wIKXMDSoASAFQAw%3D%3D', 7, '2026-10-12 23:33:10'),
(171, '8', 'Jakarta Selatan - Pejaten', 'Jl. Warung Jati Barat, RT.1/RW.5, Jati Padang, Ps. Minggu, Kota Jakarta Selatan, Daerah Khusus Ibukota Jakarta 12540', '+62822 9999 5029', 'Buka : Pukul 10.00 - 22.00 WIB', 'BUKA SETIAP HARI', '', 'https://maps.app.goo.gl/TcMPQz65FwJ6TnWu9?g_st=aw', 8, '2026-10-12 23:33:10'),
(172, '9', 'Jakarta Selatan - Pejaten 2', 'Jl. Warung Jati Barat, RT.1/RW.5, Jati Padang, Ps. Minggu, Kota Jakarta Selatan, Daerah Khusus Ibukota Jakarta 12540', '+6282299996850', 'Buka : Pukul 10.00 - 22.00 WIB', 'BUKA SETIAP HARI', '', 'https://maps.app.goo.gl/TcMPQz65FwJ6TnWu9?g_st=aw', 9, '2026-10-12 23:33:10'),
(173, '10', 'Jakarta Selatan - Mall Ambassador Kuningan', 'Jakarta Selatan - Mall Ambassador Kuningan, Mall Ambasador Kuningan - Jakarta Selatan (Lantai Dasar No.56 Patokan dekat dengan Pintu Timur sebelah ATM BCA )', '0822-9999-5031', 'Buka : Pukul 10.00 - 21.00 WIB', 'BUKA SETIAP HARI', '', 'https://maps.app.goo.gl/XmcSPiBEdvyWG7HdA', 10, '2026-10-12 23:33:10'),
(174, '11', 'Jakarta Selatan - Mall Kalibata City', 'Jakarta Selatan - Mall Kalibata City, Mal Kalibata City Square lantai dasar No. 1160 (Disebelah Gramedia)', '0811-1928-9994', 'Buka : Pukul 10.00 - 22.00 WIB', 'BUKA SETIAP HARI', '', 'https://maps.app.goo.gl/t4yG2f9wsjJ3eBLv7', 11, '2026-10-12 23:33:10'),
(175, '12', 'Jakarta Selatan - Mall Fx Sudirman', 'Jakarta Selatan - Mall Fx Sudirman, Mall FX Sudirman, Lt.F4 No.19, Jakarta Pusat', '0822-9999-6027', 'Buka : Pukul 10.00 - 22.00 WIB', 'BUKA SETIAP HARI', '', 'https://maps.app.goo.gl/ax8XcBQwW5gBEhMP9', 12, '2026-10-12 23:33:10'),
(176, '13', 'Jakarta Selatan - Blok M Square', 'Jakarta Selatan - Blok M Square, Blok M Square Lantai UG Blok A No.103 (Patokannya sebelah kartika gold)', '0822 9999 6153', 'Jam Operasional : 10.00 - 20.00 WIB', '', '', 'https://maps.app.goo.gl/RAwsEkygpeiGVmdk9', 13, '2026-10-12 23:33:10'),
(177, '14', 'Jakarta Pusat - Petojo', 'Jl. Petojo Melintang No.26A, Petojo Sel., Kecamatan Gambir, Kota Jakarta Pusat, Daerah Khusus Ibukota Jakarta 10160', '0822-9999-6182', 'Buka : Pukul 09.00 - 20.00 WIB', 'BUKA SETIAP HARI', '', 'https://maps.app.goo.gl/1yxkGNyyumMmcdNJ8', 14, '2026-10-12 23:33:10'),
(178, '15', 'Jakarta Pusat - Cikini', 'Cikini Gold Center Lt. UG.17, Jl. Pegangsaan Timur No.1, RT.1/RW.1, Menteng, Jakarta 10310', '021-21232275', 'Buka : Pukul 09.00 - 17.00 WIB', 'BUKA SETIAP HARI', '', 'https://www.google.com/maps/place/Raja+Emas+Indonesia+Cikini+Gold+Center/@-6.1985405,106.8414122,17z/data=!3m1!4b1!4m6!3m5!1s0x2e69f50cf2e192f5:0xbdcb801ada33ca93!8m2!3d-6.1985405!4d106.8414122!16s%2Fg%2F11k79kxv2s?entry=ttu&g_ep=EgoyMDI1MDEyMi4wIKXMDSoASAFQAw%3D%3D', 15, '2026-10-12 23:33:10'),
(179, '16', 'Jakarta Pusat - Cikini 2', 'Cikini Gold Center Lantai GF 25-26, Jl. Pegangsaan Timur No.1, RT.1/RW.1, Kota Jakarta Pusat, Daerah Khusus Ibukota Jakarta 10310', '0822-9999-4248', 'Buka : Pukul 09.00 - 17.00 WIB', 'BUKA SETIAP HARI', '', 'https://share.google/3Wxzf8Pa0yl0C9v6r', 16, '2026-10-12 23:33:10'),
(180, '17', 'Jakarta Barat - Cengkareng', 'Ruko Sedayu Square, Jalan Kamal Raya Outer Ring Road No.H08, Cengkareng Bar., Kecamatan Cengkareng, Kota Jakarta Barat, Daerah Khusus Ibukota Jakarta 11730', '021-52392506', 'Buka : Pukul 09.00 - 20.00 WIB', 'BUKA SETIAP HARI', '', 'https://share.google/LeK3UqxG2pt8nTZu5', 17, '2026-10-12 23:33:10'),
(181, '18', 'Jakarta Barat - Mall Ciputra Grogol', 'Jakarta Barat - Mall Ciputra Grogol, Mall Ciputra, Lantai 1 No.42, Jl. Letjen S. Parman, RT.11/RW.1, Tj. Duren Utara, Kec. Grogol petamburan, Kota Jakarta Barat, DKI Jakarta', '0822 9999 5032', 'Buka : 10.00 - 22.00 WIB', '', '', 'https://maps.app.goo.gl/YjDhDaRppvqp1sLe6', 18, '2026-10-12 23:33:10'),
(182, '19', 'Jakarta Timur - PGC', 'Mall PGC Cililitan, Lantai 2 Zona Biru No. 02 NM (Dekat Layanan Terpadu), Jl. Mayjen Sutoyo, Cililitan, Kec. Kramat Jati, Jakarta Timur, Daerah Khusus Ibukota Jakarta, 13640', '0811-1927-9995', 'Buka : Pukul 10.00 - 20.00 WIB', 'BUKA SETIAP HARI', '', 'https://maps.app.goo.gl/SJhgLuo4o6wB4XVW8', 19, '2026-10-12 23:33:10'),
(183, '20', 'Depok - ITC Depok', 'I TC Depok, Samping Terminal Terpadu Gedoran Depok, LT.dasar Blok C5, Jl. Margonda No.56, Depok, Kec. Pancoran Mas, Kota Depok, Jawa Barat 16431', '0822-9999-4206', 'Buka : Pukul 10.00 - 20.00 WIB', 'BUKA SETIAP HARI', '', 'https://www.google.com/maps/place/Raja+Emas+Indonesia+Depok/@-6.3919554,106.8184443,17z/data=!3m1!4b1!4m6!3m5!1s0x2e69eb89f8faa041:0xd46f3498dbcaef92!8m2!3d-6.3919554!4d106.8233152!16s%2Fg%2F11r259lghc?entry=ttu&g_ep=EgoyMDI1MDEyMi4wIKXMDSoASAFQAw%3D%3D', 20, '2026-10-12 23:33:10'),
(184, '21', 'Depok - Mall Cinere', 'Depok - Mall Cinere, Mal Cinere, Lantai 1 unit 34 A, Jl. Cinere Raya No.1, Pangkalan Jati, Kec. Cinere, Kota Depok, Jawa Barat 16514', '0822-9999-5062', 'Buka : Pukul 10.00 - 21.00 WIB', '', '', 'https://maps.app.goo.gl/rQSRW1rPoLanSvyLA?g_st=ac', 21, '2026-10-12 23:33:10'),
(185, '22', 'Depok - Sawangan', 'Jl. Nusantara Raya No.20, Depok Jaya, Kec.Pancoran Mas (di Depan Toko timbul jaya Nusantara furniture)', '0811-1119-9013', 'Buka : Pukul 09.00 - 19.00 WIB', '', '', 'https://maps.app.goo.gl/muvcrSi6HP4RHcuSA', 22, '2026-10-12 23:33:10'),
(186, '23', 'Bekasi - Mega Bekasi Hypermall', 'Mega Bekasi Hypermall,Lt.UG.133, Jl. Ahmad Yani No.1, RT.004/RW.001, Marga Jaya, Kota Bks, Jawa Barat 17141', '0822-9999-3167', 'Buka : Pukul 10.00 - 20.00 WIB', 'BUKA SETIAP HARI', '', 'https://www.google.com/maps/place/Raja+Emas+Indonesia+Mega+Bekasi+Hypermall/@-6.2495364,106.9900458,17z/data=!3m1!4b1!4m6!3m5!1s0x2e698d0fa51bfd25:0x9e7ca4c3fcdae4dd!8m2!3d-6.2495364!4d106.9926207!16s%2Fg%2F11vc6zpvql?entry=ttu&g_ep=EgoyMDI1MDEyMi4wIKXMDSoASAFQAw%3D%3D', 23, '2026-10-12 23:33:10'),
(187, '24', 'Bekasi - Cibubur', 'Plasa Cibubur Lt.1 No.19.,, Jl. Transyogi, RT.001/RW.003, Jatikarya, Kec. Jatisampurna, Kota Bks, Jawa Barat 17435', '0822-9999-5026', 'Buka : Pukul 10.00 - 21.00 WIB', 'BUKA SETIAP HARI', '', 'https://share.google/rzt2RT4M4gUGVgpBC', 24, '2026-10-12 23:33:10'),
(188, '25', 'Bekasi - Harapan Indah', 'Ruko Commersial Park, Jl Raya Harapan Indah, Pejuang, Kec Medan Satria, Kota Bekasi.', '0822 9999 6831', 'Buka : Pukul 10.00 - 20.00 WIB', 'BUKA SETIAP HARI', '', 'https://maps.app.goo.gl/FWor46Uj69e21ZoN8', 25, '2026-10-12 23:33:10'),
(189, '26', 'Kabupaten Bekasi - Aeon Mall Deltamas Cikarang', 'Kabupaten Bekasi - Aeon Mall Deltamas Cikarang, AEON Mall Deltamas, Jl. Ganesha Boulevard No.123 B Lantai 1, Hegarmukti, Kec. Cikarang Pusat, Kabupaten Bekasi, Jawa Barat 17530', '0811-1928-9991', 'Buka : Pukul 10.00 - 22.00 WIB', 'BUKA SETIAP HARI', '', 'https://maps.app.goo.gl/QsLwWit4niAJs7Sn9', 26, '2026-10-12 23:33:10'),
(190, '27', 'Kabupaten Bekasi - Grand Wisata Bekasi', 'Ruko Rivertown Boulevard, Jl. Grand Wisata No.16 Blok BA02, Lambangsari, Kec. Tambun Sel., Kabupaten Bekasi, Jawa Barat 17510', '0822-9999-6184', 'Buka : Pukul 10.00 - 22.00 WIB', 'BUKA SETIAP HARI', '', 'https://maps.app.goo.gl/YDJ3EFbtgyGnWmk16', 27, '2026-10-12 23:33:10'),
(191, '28', 'Karawang', 'KCP Mall Karawang – Lantai GF No. A6 (Masuk dari Lobby Timur, di depan Optik Melawai)', '0822-9999-6147', 'Buka : Pukul 10.00 - 22.00 WIB', 'BUKA SETIAP HARI', '', 'https://maps.app.goo.gl/a4g7CZkSxgpHPXMp6', 28, '2026-10-12 23:33:10'),
(192, '29', 'Tangerang - Aeropolis', 'ruko apartemen, Aeropolis Commercial Park 2 A2. GT.06, Jl. Aeropolis, RT.004/RW.008, Neglasari, Kec. Neglasari, Kota Tangerang, Banten 15129', '0822-9999-6124', 'Buka : Pukul 10.00 - 18.00 WIB', 'BUKA SETIAP HARI', '', 'https://maps.app.goo.gl/iAzV4Zu4aCTDV1Xe8', 29, '2026-10-12 23:33:10'),
(193, '30', 'Tangerang - Tangcity Mall', 'Tangerang - Tangcity Mall', '', '', '', 'https://www.google.com/maps?q=Tangerang%20-%20Tangcity%20Mall%20Tangerang%20-%20Tangcity%20Mall&output=embed', 'https://www.google.com/maps/search/?api=1&query=Tangerang%20-%20Tangcity%20Mall%20Tangerang%20-%20Tangcity%20Mall', 30, '2026-10-12 23:33:10'),
(194, '31', 'Tangerang - Tangcity 2', 'Tangcity Mall, Ruko Business Park E38, (patokannya dekat dengan indomaret tangcity)', '0822 9999 6157', 'Jam Operasional : 10.00 - 20.00 WIB', 'BUKA SETIAP HARI', '', 'https://maps.app.goo.gl/s9ohosNKPsYML1YH9', 31, '2026-10-12 23:33:10'),
(195, '32', 'Pasar Lama Tangerang Kota', 'Jl. Kisamaun, Sukasari, Tangerang, Kota Tangerang, Banten 15118', '0811-1119-9012', 'Jam Operasional : 10.00 - 20.00 WIB', 'BUKA SETIAP HARI', '', 'https://maps.app.goo.gl/sajZXyZsmJNGJKW57', 32, '2026-10-12 23:33:10'),
(196, '33', 'Tangerang - Cikokol', 'Jl. MH. Thamrin Ruko 8F, Panunggangan, Pinang, Kota Tangerang 15143', '0811-1118-2101', 'Jam Operasional : 10.00 - 20.00 WIB', 'BUKA SETIAP HARI', '', 'https://maps.app.goo.gl/t3hTpTpHJKpXpzPm9', 33, '2026-10-12 23:33:10'),
(197, '34', 'Tangerang - Ciledug', 'Jl. Raden Patah No.8A, RT.001/RW.004, Sudimara Bar., Kec. Ciledug, Kota Tangerang, Banten 15151', '0811-1119-9987', 'Jam Operasional : 10.00 - 20.00 WIB', 'BUKA SETIAP HARI', '', 'https://maps.app.goo.gl/W9oULM1GFQxzuB9B9', 34, '2026-10-12 23:33:10'),
(198, '35', 'Tangerang Selatan - Mall ITC BSD', 'Tangerang Selatan - Mall ITC BSD', '', '', '', '', '', 35, '2026-10-12 23:33:10'),
(199, '36', 'Tangerang Selatan - Gading Serpong', 'Jl. Raya Kelapa Gading Utara, Ruko Fluoride, No.75, Gading Serpong, Tangerang', '0822 9999 5074', 'Buka : Pukul 10.00 - 20.00 WIB', 'BUKA SETIAP HARI', '', 'https://maps.app.goo.gl/BqdYhjAbFT3V3qez9', 36, '2026-10-12 23:33:10'),
(200, '37', 'Tangerang Selatan - Pasar Modern Bintaro Jaya', 'Pasar Modern Bintaro Jaya, Pd. Jaya, Kec. Pd. Aren, Kota Tangerang Selatan, Banten 15220', '0822-9999-5039', '09.00 - 20.00 WIB', 'BUKA SETIAP HARI', '', 'https://maps.app.goo.gl/uwobRLyyYJvXQqCe9?g_st=aw', 37, '2026-10-12 23:33:10'),
(201, '38', 'Tangerang Selatan - Pamulang', 'Jl.Pajajaran No.9 pamulang, Kota Tangerang Selatan, Banten', '082299996083', 'Buka : 10.00 - 20.00 WIB', '', '', 'https://maps.app.goo.gl/Fd4NSRzQ6QGzm5qLA', 38, '2026-10-12 23:33:10'),
(202, '39', 'Serang', 'Jl. Mayor Safei No.13, Kotabaru, Kec. Serang, Kota Serang, Banten 42112 (Patokan depan rumah tahanan klas 2B serang)', '0821 1365 474', 'Buka : 10.00-20.00 WIB', 'BUKA SETIAP HARI', '', 'https://maps.app.goo.gl/Adjjj1mGhVFTmj4t8', 39, '2026-10-12 23:33:10'),
(203, '40', 'Cibinong', 'Jl Raya Bogor No. 320 KM 45, Cibinong, Kec Cibinong, Kab Bogor, Jawa Barat', '0822 9999 6835', 'Buka : 10.00-20.00 WIB', 'BUKA SETIAP HARI', '', 'https://share.google/ZRkZKpUTLHO7q67WD', 40, '2026-10-12 23:33:10'),
(204, '41', 'Bogor', 'Pasar Kebon Kembang, Blok F Trade Center Pasar Kebon Kembang Lantai Dasar No.194, Paledang, Bogor Tengah, Kota Bogor, Jawa Barat 16122', '0822 9999 5076', 'Buka : Pukul 09.00 - 17.00 WIB', 'BUKA SETIAP HARI', '', 'https://www.google.com/maps/place/Raja+Emas+Indonesia/@-6.5926602,106.789689,17z/data=!3m1!4b1!4m6!3m5!1s0x2e69c50e93b76f37:0x6431bc1e47856e14!8m2!3d-6.5926602!4d106.7922639!16s%2Fg%2F11tfn001gz?entry=ttu&g_ep=EgoyMDI1MDEyMi4wIKXMDSoASAFQAw%3D%3D', 41, '2026-10-12 23:33:10'),
(205, '42', 'Bogor - Citeureup', 'Alamat : Jl. Raya Mayor Oking Jaya Atmaja, Puspanegara, Kec. Citeureup, Kabupaten Bogor, Jawa Barat 16810', '0811-1464-8499', 'Jam Operasional : 10.00 - 20.00 WIB', 'BUKA SETIAP HARI', '', 'https://www.google.com/maps/place/Raja+Emas+Indonesia/@-6.5926602,106.789689,17z/data=!3m1!4b1!4m6!3m5!1s0x2e69c50e93b76f37:0x6431bc1e47856e14!8m2!3d-6.5926602!4d106.7922639!16s%2Fg%2F11tfn001gz?entry=ttu&g_ep=EgoyMDI1MDEyMi4wIKXMDSoASAFQAw%3D%3D', 42, '2026-10-12 23:33:10'),
(206, '43', 'Bogor - Cisarua', 'Jl Raya puncak No 128, Cisarua, Bogor, Jawa Barat.', '0813-1417-613', 'Buka : Pukul 10.00 - 20.00 WIB', 'BUKA SETIAP HARI', '', 'https://www.google.com/maps/place/Raja+Emas+Indonesia/@-6.5926602,106.789689,17z/data=!3m1!4b1!4m6!3m5!1s0x2e69c50e93b76f37:0x6431bc1e47856e14!8m2!3d-6.5926602!4d106.7922639!16s%2Fg%2F11tfn001gz?entry=ttu&g_ep=EgoyMDI1MDEyMi4wIKXMDSoASAFQAw%3D%3D', 43, '2026-10-12 23:33:10'),
(207, '44', 'Bogor - Surya Kencana', 'Jl.Suryakencana, RT.01/RW.05, Gudang, Kecamatan Bogor Tengah, Kota Bogor, Jawa Barat 16131', '0822-9999-5071', 'Buka : 10.00 - 20.00 WIB', 'BUKA SETIAP HARI', '', 'https://maps.app.goo.gl/Meb3LM2gEhnSwMso7', 44, '2026-10-12 23:33:10'),
(208, '45', 'Bogor - Dramaga', 'Jl. Raya Cibanteng, Cihideung Ilir, Kec. Ciampea, Kabupaten Bogor, Jawa Barat 16620', '0822-9999-7041', 'Buka : 10.00 - 20.00 WIB', 'BUKA SETIAP HARI', '', 'https://maps.app.goo.gl/Nk4CaG81bzfTvxabA', 45, '2026-10-12 23:33:10'),
(209, '46', 'Cianjur', 'Jl. HOS Cokro Aminoto No.176, Muka, Kec. Cianjur, Kabupaten Cianjur, Jawa Barat 43214', '0822-9999-7062', 'Buka : 09.00 - 20.00 WIB', 'BUKA SETIAP HARI', '', 'https://maps.app.goo.gl/BhKqEiq63A5M7oKu6', 46, '2026-10-12 23:33:10'),
(210, '47', 'Cikampek', 'Jl. Ahmad Yani, Cikampek Sel, Cikampek, Karawang (Patokan Bank BNI)', '0811-1119-9901', 'Buka : 10.00 - 20.00 WIB', 'BUKA SETIAP HARI', '', 'https://maps.app.goo.gl/AneVFUuWDhKBkoQX7', 47, '2026-10-12 23:33:10'),
(211, '48', 'Bandung-Kopo', 'Jl. Raya Kopo No.632, Babakan Ciparay, Kec. Babakan Ciparay, Kota Bandung, Jawa Barat 40225', '0822 9999 5042', 'Buka : Pukul 10.00 - 20.00 WIB', 'BUKA SETIAP HARI', '', 'https://maps.app.goo.gl/ZffLS9Q6uEUfRXWV6', 48, '2026-10-12 23:33:10'),
(212, '49', 'Bandung-Sukajadi', 'Jl. Sukajadi No.158, Cipedes, Kel. Pasteur, Kec. Sukajadi, Kota Bandung, Jawa Barat 40162', '0822-9999-7764', 'Buka : Pukul 09.00 - 20.00 WIB', 'BUKA SETIAP HARI', '', 'https://www.google.com/maps/place/Raja+Emas+Indonesia+Bandung/@-6.8871875,107.5920666,17z/data=!3m1!4b1!4m6!3m5!1s0x2e68e7a7c15acbd1:0x94cf82e2d4aa77a2!8m2!3d-6.8871875!4d107.5969375!16s%2Fg%2F11t88tdb0l?entry=ttu&g_ep=EgoyMDI1MDEyMi4wIKXMDSoASAFQAw%3D%3D', 49, '2026-10-12 23:33:10'),
(213, '50', 'Bandung-Otista', 'Jl. Otto Iskandar Dinata No.313, Balonggede, Kec. Regol, Kota Bandung, Jawa Barat 40251', '0822-9999-5075', 'Buka : Pukul 10.00 - 20.00 WIB', 'BUKA SETIAP HARI', '', 'https://maps.app.goo.gl/JFL3ZH6dnGaB9QjB7', 50, '2026-10-12 23:33:10'),
(214, '51', 'Jawa Barat - Cimahi', 'Jl. Jend. H. Amir Machmud, Karangmekar, Kec. Cimahi Tengah, Kota Cimahi, Jawa Barat 40523', '0811-1119-9973', 'Buka : Pukul 10.00 - 20.00 WIB', 'BUKA SETIAP HARI', '', 'https://maps.app.goo.gl/9CwzJsLXnpSXpLsq9', 51, '2026-10-12 23:33:10'),
(215, '52', 'Jawa Barat - Garut', 'Jl.Ciledug No.36, Regol, Garut Kota,Kab.Garut (Patokannya sebrang Mie Bakso Parahyangan Garut)', '0811-1119-9942', 'Buka : Pukul 10.00 - 20.00 WIB', 'BUKA SETIAP HARI', '', 'https://maps.app.goo.gl/iR7ihGBCmRG4jxic8', 52, '2026-10-12 23:33:10'),
(216, '53', 'Jawa Barat - Tasikmalaya', 'Jl. Pasar Wetan No.37, Argasari, Cihideung, Kota Tasikmalaya Jawa Barat (Sebrang TB Pelita)', '0811-1119-9923', 'Jam Operasional : Pukul 10.00-20.00 WIB', '', '', 'https://maps.app.goo.gl/vNG7AqzMDdP94UuL7', 53, '2026-10-12 23:33:10'),
(217, '54', 'Jawa Barat - Sukabumi', 'Jl. A. Yani, Gunungparang, Kec. Cikole, Kota Sukabumi, Jawa Barat 43111', '0811-1119-9792', 'Jam Operasional : Pukul 10.00-20.00 WIB', '', '', 'https://maps.app.goo.gl/fdMqSPG5JxkvJPDn7', 54, '2026-10-12 23:33:10'),
(218, '55', 'Jawa Barat - Subang', 'Jl. Letjen Suprapto No.40, Cigadung, Kec. Subang, Kabupaten Subang, Jawa Barat 41211', '0811-1119-9904', 'Jam Operasional : Pukul 10.00-20.00 WIB', '', '', 'https://maps.app.goo.gl/5vx7nu8CTahfWKvj6', 55, '2026-10-12 23:33:10'),
(219, '56', 'Jawa Barat - Cirebon', 'Jl. DR. Cipto Mangunkusumo No.53 (di sebrang Mall CSB, di samping louis)', '0822-9999-6042', 'Jam Operasional : Pukul 10.00-20.00 WIB', '', '', 'https://maps.app.goo.gl/VvqFNMdwqqJzv2qAA', 56, '2026-10-12 23:33:10'),
(220, '57', 'Jawa Barat - Cirebon 2', 'Jl. Raya Panembahan, Panembahan, Kec. Plered, Kabupaten Cirebon, Jawa Barat 45154', '085603098699', 'Jam Operasional : Pukul 10.00-20.00 WIB', '', '', 'https://maps.app.goo.gl/u8GGyGrCmzpmdcSv9', 57, '2026-10-12 23:33:10'),
(221, '58', 'Jawa Barat - Indramayu', 'Alamat : Jl. Siliwangi No.4, Jatibarang, Kec. Jatibarang, Kabupaten Indramayu, Jawa Barat 45273', '0811-1464-8532', 'Jam Operasional : 10.00 - 20.00 WIB', 'BUKA SETIAP HARI', '', 'https://maps.app.goo.gl/oFjfLmnoQJQbwedy5', 58, '2026-10-12 23:33:10'),
(222, '59', 'Surabaya - Pakuwon', 'Pakuwon Trade Center Mall Surabaya,, Lt.LG No.09-10', '0822-9999-4123', 'Buka : Pukul 10.00 - 21.30 WIB', 'BUKA SETIAP HARI', '', 'https://www.google.com/maps/place/Raja+Emas+Indonesia+Pakuwon+Trade+Center+Mall+Surabaya/@-7.2907206,112.6716722,17z/data=!3m1!4b1!4m6!3m5!1s0x2dd7fdfb49e8d22b:0xb7bc943f67a95de7!8m2!3d-7.2907206!4d112.6742471!16s%2Fg%2F11vyj8m9l6?entry=ttu&g_ep=EgoyMDI1MDEyMi4wIKXMDSoASAFQAw%3D%3D', 59, '2026-10-12 23:33:10'),
(223, '60', 'Surabaya 2', 'Jl. Bubutan No.16, Alun-alun Contong, Kec. Bubutan, Surabaya', '0822-9999-6125', 'Buka : Pukul 09.00 - 19.00 WIB', 'BUKA SETIAP HARI', '', 'https://maps.app.goo.gl/xGWWYiDTsgzvuxbp9?g_st=ipc', 60, '2026-10-12 23:33:10'),
(224, '61', 'Surabaya 3', 'Royal Plaza Surabaya, Jl. Ahmad Yani No.16-18, Lantai 2', '0822 99996048', 'Buka : 10.00-22.00 WIB', 'BUKA SETIAP HARI', '', 'https://maps.app.goo.gl/wi7nraxTDAnjoeKRA', 61, '2026-10-12 23:33:10'),
(225, '62', 'Surabaya 4 - PTC', 'Store kedua ada di PTC, Pakuwon Trade Center Mall Surabaya D2- 06 lt GF, Jl. Raya Lontar No.2 Lantai LG, Unit B6-09&10, Babatan, Kec. Wiyung, Surabaya, Jawa Timur 60123', '082299996173', 'Buka : 10.00-22.00 WIB', 'BUKA SETIAP HARI', '', 'https://maps.app.goo.gl/wi7nraxTDAnjoeKRA', 62, '2026-10-12 23:33:10'),
(226, '63', 'Surabaya 5 - San Antonio', 'JL. KALISARI UTARA 1 73 RUKO SAN ANTONIO N1-81, Surabaya, Jawa Timur 60112', '081111183301', 'Buka : 10.00-22.00 WIB', 'BUKA SETIAP HARI', '', 'https://maps.app.goo.gl/YkoxK2pQTKi9q34e6', 63, '2026-10-12 23:33:10'),
(227, '64', 'Surabaya 6 - Indragiri', 'Jl. Adityawarman, Darmo, Kec. Wonokromo, Surabaya, Jawa Timur 60242', '0882-2043-2984', 'Buka : 10.00-22.00 WIB', 'BUKA SETIAP HARI', '', 'https://maps.app.goo.gl/yLP5qLQYSZoqnagU8', 64, '2026-10-12 23:33:10'),
(228, '65', 'Surabaya 7 - Kedung Doro', 'Jl Kedung Doro No 4, Sawahan, Kota Surabaya, Jawa Timur, 60251', '0811-1118-1901', 'Buka : 10.00-22.00 WIB', 'BUKA SETIAP HARI', '', 'https://maps.app.goo.gl/yLP5qLQYSZoqnagU8', 65, '2026-10-12 23:33:10'),
(229, '66', 'Surabaya 8 - Dinoyo', 'Jl. Pandegiling No. 27, Tegalsari, Kota Surabaya, Jawa Timur', '0811-1118-3201', 'Buka : Pukul 10.00 - 20.00 WIT', 'BUKA SETIAP HARI', '', 'https://maps.app.goo.gl/CQ3RqBEJ5isvRi2s9', 66, '2026-10-12 23:33:10'),
(230, '67', 'Semarang', 'JL. KH Wahid Hasyim No. 97, Pecinan (depan Jangkrik Textile)', '081119289992', 'Buka : Pukul 09.00 - 20.00 WIB', 'BUKA SETIAP HARI', '', 'https://maps.app.goo.gl/eGNiibhd6QxExxK99?g_st=ac', 67, '2026-10-12 23:33:10'),
(231, '68', 'Semarang 2', 'Ruko Jl. Pandanaran No.kav.6, Pekunden, Kec. Semarang Tengah (patokan samping resto holiday)', '0822 9999 6170', 'Buka : Pukul 10.00 - 20.00 WIB', 'BUKA SETIAP HARI', '', 'https://maps.app.goo.gl/D1bRR1NqvBPMrYBz9', 68, '2026-10-12 23:33:10'),
(232, '69', 'Malang', 'Jl. S.W. Pranoto No. 2H, Sukoharjo – Klojen, Malang', '0822-9999-6145', 'Buka : Pukul 10.00 - 20.00 WIB', 'BUKA SETIAP HARI', '', 'https://maps.app.goo.gl/jpt4aM36keCps88N9', 69, '2026-10-12 23:33:10'),
(233, '70', 'Tegal', 'Jl. Ahmad Yani No.138, RT.9/RW.1, Mintaragen, Kec. Tegal Tim., Kota Tegal, Jawa Tengah 52121', '0811 1119 9913', 'Buka : Pukul 10.00 - 20.00 WIB', 'BUKA SETIAP HARI', '', 'https://maps.app.goo.gl/imZHFux2xhyvM5Hy9', 70, '2026-10-12 23:33:10'),
(234, '71', 'Kudus', '5RRR+2F6, Jl. Jend. Ahmad Yani, Magersari, Panjunan, Kec. Kota Kudus, Kabupaten Kudus, Jawa Tengah 59313', '0811 1118 1201', 'Buka : Pukul 09.00 - 20.00 WIB', 'BUKA SETIAP HARI', '', 'https://maps.app.goo.gl/7T9c2KfoJ7PYHuhP7', 71, '2026-10-12 23:33:10'),
(235, '72', 'Yogyakarta - Sleman', 'Jl. Laksda Adisucipto No.1, Papringan, Caturtunggal, Kec. Depok, Kabupaten Sleman, Daerah Istimewa Yogyakarta 55281 (Patokan Sebelah Kanan UIN Sunan Kalijaga)', '0822 9999 6038', 'Buka : 10.00 - 20.00 WIB', 'BUKA SETIAP HARI', '', 'https://maps.app.goo.gl/Kku4AzjkhNz1TJ8q7', 72, '2026-10-12 23:33:10'),
(236, '73', 'Yogyakarta - Plaza Malioboro', 'Yogyakarta - Plaza Malioboro, Plaza Malioboro, Jl. Malioboro No.52-58 Lantai UG, Suryatmajan, Kec. Danurejan, Kota Yogyakarta, Daerah Istimewa Yogyakarta 55213', '081119719996', 'Senin-jumat 10:00-22:00', 'BUKA SETIAP HARI', '', 'https://maps.app.goo.gl/8Ez15QkKHCDgigqFA', 73, '2026-10-12 23:33:10'),
(237, '74', 'Lamongan', 'Jl. DR. Wahidin No.17, Tumenggungan, Kec. Lamongan, Kabupaten Lamongan, Jawa Timur 62214', '0822-9999-6081', 'Buka : 10.00 - 20.00 WIB', 'BUKA SETIAP HARI', '', 'https://maps.app.goo.gl/kYvB3GDv9JjgQkVu8', 74, '2026-10-12 23:33:10'),
(238, '75', 'Pekalongan', 'JJl. Sultan Agung No.143, Sampangan, Kec. Pekalongan Tim., Kota Pekalongan, Jawa Tengah 51126', '0811-1119-9948', 'Buka : 10.00 - 20.00 WIB', 'BUKA SETIAP HARI', '', 'https://maps.app.goo.gl/kYvB3GDv9JjgQkVu8', 75, '2026-10-12 23:33:10'),
(239, '76', 'Purwokerto', 'Ruko Gede Pasar Wage Blok B Nomor 3, Jl. Pasar Wage, Kauman Lama, Purwokerto Lor, Purwokerto Tim', '0811-1119-9790', 'Buka : 10.00 - 20.00 WIB', 'BUKA SETIAP HARI', '', 'https://maps.app.goo.gl/nxy1X1c89YFFyPDR6', 76, '2026-10-12 23:33:10'),
(240, '77', 'Solo - Pakuwon Mall Solo', 'Solo - Pakuwon Mall Solo, Pakuwon Mall Solo Baru, Jl. Ir. Soekarno, Dusun II, Madegondo, Kec. Grogol, Kabupaten Sukoharjo, Jawa Tengah 57552', '0822 9999 6047', 'Senin-jumat 10:00-22:00', 'BUKA SETIAP HARI', '', 'https://share.google/zLeElYXjtmGJXvOT3', 77, '2026-10-12 23:33:10'),
(241, '78', 'Madiun', 'Jl. Mayjen Sungkono No.27C, Nambangan Lor, Kec. Manguharjo, Kota Madiun, Jawa Timur 63129', '0811-1119-9789', 'Senin-jumat 10:00-20:00 WIB', 'BUKA SETIAP HARI', '', 'https://maps.app.goo.gl/8HPDZ7bE4Znh4fat6', 78, '2026-10-12 23:33:10'),
(242, '79', 'Sidoarjo', 'Komplek Pertokoan Pasar Larangan, Jl. Sunandar Priyo Sudarmo No. 11, Larangan, Candi, Sidoarjo, Jawa Timur (Patokan samping Bank BNI)', '0811-1119-9794', 'Senin-jumat 10:00-20:00 WIB', 'BUKA SETIAP HARI', '', 'https://maps.app.goo.gl/qwvDvHimfQoAwoUQ9', 79, '2026-10-12 23:33:10'),
(243, '80', 'Kendal', 'Jl. Raya Utama Tengah No.211, Kedonsari, Karangdowo, Kec. Weleri, Kabupaten Kendal, Jawa Tengah 51355', '0821-1580-0821', 'Senin-jumat 10:00-20:00 WIB', 'BUKA SETIAP HARI', '', 'https://maps.app.goo.gl/DFT4nvv2i5hMRzsA8', 80, '2026-10-12 23:33:10'),
(244, '81', 'Magelang', 'Alamat : Jl. Ahmad Yani No.154, Kedungsari, Kec. Magelang Utara, Kota Magelang, Jawa Tengah 56116', '0811-1464-8485', 'Jam Operasional : 10.00 - 20.00 WIB', 'BUKA SETIAP HARI', '', 'https://maps.app.goo.gl/Sk22MbCCGcgiwJxC8', 81, '2026-10-12 23:33:10'),
(245, '82', 'Kediri', 'Alamat : Jl. Soekarno Hatta No.95, Tepus, Sukorejo, Kec. Ngasem, Kabupaten Kediri, Jawa Timur 64182', '0811-1464-8530', 'Jam Operasional : 10.00 - 20.00 WIB', '', '', 'https://maps.app.goo.gl/XbYsrVJ1UMLB2tkR6', 82, '2026-10-12 23:33:10'),
(246, '83', 'Madura - Bangkalan', 'Jl. Trunojoyo No.37, Pejagan, Kec. Bangkalan (di sebrang toko cat, sebelah toko Belikopi)', '0822-9999-6043', 'Buka : Pukul 10.00-20.00 WIB', 'BUKA SETIAP HARI', '', 'https://maps.app.goo.gl/dcdfYFxEha6yjEvZ6', 83, '2026-10-12 23:33:10'),
(247, '84', 'Madura - Pamekasan', 'Jl. Kabupaten No.50, Sumur Putih, Bugih, Kec. Pamekasan, Kabupaten Pamekasan, Jawa Timur 69316', '0811-1119-9917', 'Buka : Pukul 10.00-20.00 WIB', 'BUKA SETIAP HARI', '', 'https://share.google/5aQV51DBrVxuwetQG', 84, '2026-10-12 23:33:10'),
(248, '85', 'Medan - Medan Mall', 'Medan - Medan Mall', '0822 9999 6836', 'Buka : 10.00-20.00 WIB', '', '', 'https://maps.app.goo.gl/X69uNp7Vzb9y259U7', 85, '2026-10-12 23:33:10'),
(249, '86', 'Palembang', 'Jl. Letkol Iskandar No.27 A, B, C, 24 Ilir, Kec. Bukit Kecil, Kota Palembang, Sumatera Selatan 30113', '082299996073', 'Buka : Pukul 10.00-20.00 WIB', '', '', 'https://maps.app.goo.gl/ArfXF5Rt7ofVgpdK9', 86, '2026-10-12 23:33:10'),
(250, '87', 'Jambi', 'Pert. Glodok Makmur 27 C, Jl. Hayam Wuruk No.11180 Lt. Dasar, Jelutung, Jambi, Kota Jambi, Jambi 36124', '0822 9999 6825', 'Buka : Pukul 10.00-20.00 WIB', 'BUKA SETIAP HARI', '', 'https://maps.app.goo.gl/kbiZDMuw7UdmMbcg9', 87, '2026-10-12 23:33:10'),
(251, '88', 'Bengkulu', 'Jl Soeprapto No 4, Pengantungan, Kec Ratu Samban, Kota Bengkulu, Bengkulu', '0822 9999 6836', 'Buka : 10.00-20.00 WIB', '', '', 'https://maps.app.goo.gl/X69uNp7Vzb9y259U7', 88, '2026-10-12 23:33:10'),
(252, '89', 'Lampung', 'Jl. Jendral Sudirman, Kel/Kec. Enggal, kota bandar Lampung (Samping BRI)', '0822 9999 6082', 'Buka: 10.00 - 20.00 WIB', '', '', 'https://maps.app.goo.gl/HTVCwnF6fTwgH9LQ7', 89, '2026-10-12 23:33:10'),
(253, '90', 'Bali - Denpasar', 'Jl. Hasanuddin No.54, Dauh Puri Kangin, Kec. Denpasar Bar., Kota Denpasar, Bali 80232', '0822-9999-3136', 'Buka : Pukul 10.00 - 20.00 WITA', 'BUKA SETIAP HARI', '', 'https://www.google.com/maps/place/Raja+Emas+Indonesia+Bali/@-8.6585364,115.2113466,17z/data=!3m1!4b1!4m6!3m5!1s0x2dd2413e8862f02f:0xc8efda411499288e!8m2!3d-8.6585364!4d115.2139215!16s%2Fg%2F11sdxdv3vs?entry=ttu&g_ep=EgoyMDI1MDEyMi4wIKXMDSoASAFQAw%3D%3D', 90, '2026-10-12 23:33:10'),
(254, '91', 'Lubuk Linggau', 'Jl YOS Sudarso No 291 Kel Taba Jemekah Kec Lubuk Linggau Timur 1 ( Patokan Pom Bensin Sebelah Indomaret)', '0822 9999 6827', 'Buka: 10.00 - 20.00 WIB', '', '', 'https://maps.app.goo.gl/mLXD4SZayEoVCGKeA', 91, '2026-10-12 23:33:10'),
(255, '92', 'Bali Outlet 2', 'JL.Gn.Soputan No 11E, Pemecutan Klod, Kota Denpasar, Bali (Patokannya samping sego sambel)', '0822 9999 6037', 'Buka: 10.00 - 20.00 WITA', '', '', 'https://share.google/cHqedW3CogARpUHI0', 92, '2026-10-12 23:33:10'),
(256, '93', 'Medan - Medan Centre Point', 'Alamat : Lantai 3 No. 19 Mall Centre Point Medan, Jl. Jawa No. 8 Medan, Kel. Gg buntu, kec. Medan Timur - SUMUT', '0822 9999 6852', 'Jam Operasional : 10.00 - 22.00 WIB', '', '', 'https://maps.app.goo.gl/XxRZ6aPTK9hoEFm57', 93, '2026-10-12 23:33:10'),
(257, '94', 'Medan - Plaza Medan fair', 'Medan - Plaza Medan fair, Alamat : Plaza Medan Fairi Lantai 3, Kota Medan, Sumut', '0822 9999 7048', 'Jam Operasional : 10.00 - 22.00 WIB', '', '', 'https://maps.app.goo.gl/J8v8ogK6TfUk91ci8', 94, '2026-10-12 23:33:10'),
(258, '95', 'Riau - Pekanbaru', 'Alamat : Jl. Tuanku Tambusai No.223, Wonorejo, Kec. Marpoyan Damai, Kota Pekanbaru, Riau 28125', '0822-9999-6854', 'Jam Operasional : 09.00 - 19.00 WIB', 'BUKA SETIAP HARI', '', 'https://maps.app.goo.gl/hR7ZgEsew4xvgzBU7', 95, '2026-10-12 23:33:10'),
(259, '96', 'Aceh - Banda Aceh', 'Jl. Sri Ratu Syafiatuddin No.4, Banda Aceh City, Aceh ( Patokan Dekat Kodam Iskandar Muda )', '0822 9999 7092', 'Jam Operasional : 10.00 - 20.00 WIB', 'BUKA SETIAP HARI', '', 'https://share.google/uODQfOeySMXWNEP0w', 96, '2026-10-12 23:33:10'),
(260, '97', 'Tangerang - Citra Raya', 'Ruko Grand Boulevard Blok D 1 No 10 Citra Raya, Tangerang, Banten (Patokan Samping BRI Cikupa)', '0822 9999 7105', 'Jam Operasional : 10.00 - 20.00 WIB', 'BUKA SETIAP HARI', '', 'https://maps.app.goo.gl/zqVdZ24iKES4xVsp9', 97, '2026-10-12 23:33:10'),
(261, '98', 'Serang - Cikande', 'Jl. Raya Serang km 27, Cikande, Serang Banten (Sebrang Koramil Cikande/Samping Sorum Yamaha)', '0822-9999-7065', 'Buka : Pukul 10.00 - 18.00 WIB', 'BUKA SETIAP HARI', '', 'https://maps.app.goo.gl/JtyHS8FQ5ECoUWgF6', 98, '2026-10-12 23:33:10'),
(262, '99', 'Rangkas', 'Jl. Rt Hardiwinangun No.45, Muara Ciujung Bar., Kec. Rangkasbitung, Kabupaten Lebak, Banten 42312', '0822-9999-7052', 'Buka : Pukul 10.00 - 18.00 WIB', 'BUKA SETIAP HARI', '', 'https://maps.app.goo.gl/1dm2nRphfBA1jkuh9', 99, '2026-10-12 23:33:10'),
(263, '100', 'Lombok', 'Jl. Pejanggik No.8, Cakranegara Bar., Kec. Cakranegara, Mataram, Lombok, Nusa Tenggara Bar. 83231', '0822-9999-3018', 'Buka : Pukul 10.00 - 18.00 WITA', 'BUKA SETIAP HARI', '', 'https://www.google.com/maps/place/Raja+Emas+Indonesia+Lombok/@-8.587102,116.1243583,17z/data=!3m1!4b1!4m6!3m5!1s0x2dcdbf12d93a9423:0xe6b1acca3c8fd923!8m2!3d-8.587102!4d116.1292292!16s%2Fg%2F11th_s2g5d?entry=ttu&g_ep=EgoyMDI1MDEyMi4wIKXMDSoASAFQAw%3D%3D', 100, '2026-10-12 23:33:10'),
(264, '101', 'Makassar', 'Ruko Ruby, Jl. Boulevard No.26, Masale, Kec. Panakkukang, Kota Makassar, Sulawesi Selatan 90222', '0822-9999-6126', 'Buka : Pukul 10.00 - 20.00 WITA', 'BUKA SETIAP HARI', '', 'https://maps.app.goo.gl/qr6cFJrx5Ao7UyHK8', 101, '2026-10-12 23:33:10'),
(265, '102', 'Medan - Medan Mall 2', 'Medan - Medan Mall 2, Medan Mall, Jl. M. T. Haryono No.21 Lt Dasar, Pusat Ps., Kec. Medan Kota, Kota Medan, Sumatera Utara 20212', '082299996084', 'Buka : Pukul 10.00 - 20.00 WIB', 'BUKA SETIAP HARI', '', 'https://maps.app.goo.gl/27CDmdMHx83kxrem7', 102, '2026-10-12 23:33:10'),
(266, '103', 'Cilegon', 'Jl. Ahmad Yani No.15, Ketileng, Kec. Cilegon, Kota Cilegon, Banten 42416', '0822-9999-7049', 'Buka : Pukul 10.00 - 18.00 WIB', 'BUKA SETIAP HARI', '', 'https://www.google.com/maps/place/Raja+Emas+Indonesia+Lombok/@-8.587102,116.1243583,17z/data=!3m1!4b1!4m6!3m5!1s0x2dcdbf12d93a9423:0xe6b1acca3c8fd923!8m2!3d-8.587102!4d116.1292292!16s%2Fg%2F11th_s2g5d?entry=ttu&g_ep=EgoyMDI1MDEyMi4wIKXMDSoASAFQAw%3D%3D', 103, '2026-10-12 23:33:10'),
(267, '104', 'Balikpapan', 'MT Haryono No.79 B, Damai, Kecamatan Balikpapan Selatan, Kota Balikpapan, Kalimantan Timur 76114', '0822 9999 6851', 'Buka : Pukul 10.00 - 20.00 WIB', 'BUKA SETIAP HARI', '', 'https://maps.app.goo.gl/G9NAJfsDfpjtkDk68', 104, '2026-10-12 23:33:10'),
(268, '105', 'Samarinda', 'Jl. KH. Khalid No.19, Ps. Pagi, Kec. Samarinda Kota, Kota Samarinda, Kalimantan Timur 75111', '082299997058', 'Buka : Pukul 10.00 - 20.00 WITA', 'BUKA SETIAP HARI', '', 'https://maps.app.goo.gl/qFAKgRsjUNuCUqSC7', 105, '2026-10-12 23:33:10'),
(269, '106', 'Gorontalo', 'Jl. Nani Wartabone (eks jl DI panjaitan) No.166, Limba U I, Kota Gorontalo', '0823-9592-8211', 'Buka : Pukul 10.00 - 20.00 WITA', 'BUKA SETIAP HARI', '', 'https://maps.app.goo.gl/M7sWsLrGRJcGXxsz8', 106, '2026-10-12 23:33:10'),
(270, '107', 'Manado', 'Jl. Piere Tendean ruko No.7, Sario Utara, Kec. Sario, Kota Manado, Sulawesi Utara 95113', '0811-1119-9931', 'Buka : Pukul 10.00 - 20.00 WITA', 'BUKA SETIAP HARI', '', 'https://maps.app.goo.gl/s5tE2dCFWbNJEUFr5', 107, '2026-10-12 23:33:10'),
(271, '108', 'Palu- Sulteng', 'Jl. Imam Bonjol No.204, Siranindi, Kec. Palu Bar., Kota Palu, Sulawesi Tengah 94221', '0813-1417-596', 'Buka : Pukul 10.00 - 20.00 WITA', 'BUKA SETIAP HARI', '', 'https://maps.app.goo.gl/fG4bQtkBKX8tJtf69', 108, '2026-10-12 23:33:10'),
(272, '109', 'Kendari', 'Jl. Martandu, Anduonohu, Kec. Poasia, Kota Kendari, Sulawesi Tenggara 93231', '0811-1119-9941', 'Buka : Pukul 10.00 - 20.00 WITA', 'BUKA SETIAP HARI', '', 'https://maps.app.goo.gl/zr5sF2crzJcqU1gy6', 109, '2026-10-12 23:33:10'),
(273, '110', 'Makassar 2', 'Jl. Sulawesi No.263, Melayu Baru, Kec. Wajo, Kota Makassar, Sulawesi Selatan 90164', '0811-1118-2801', 'Buka : Pukul 10.00 - 20.00 WITA', 'BUKA SETIAP HARI', '', 'https://maps.app.goo.gl/ULzcfNwmj2QY7W2J7', 110, '2026-10-12 23:33:10'),
(274, '111', 'Banjarmasin', 'Jl. Ahmad Yani No.37, Sungai Baru, Kec. Banjarmasin Tengah, Kota Banjarmasin, Kalimantan Selatan 70122', '0811 1119 9956', 'Buka : Pukul 10.00 - 20.00 WITA', 'BUKA SETIAP HARI', '', 'https://maps.app.goo.gl/8UnhEbdwTTmjaKrw5', 111, '2026-10-12 23:33:10'),
(275, '112', 'Padang', 'Jl. Blk. Olo No.53 A, Olo, Kec. Padang Bar., Kota Padang, Sumatera Barat 25117', '0821-6630-997', 'Buka : Pukul 10.00 - 20.00 WIB', 'BUKA SETIAP HARI', '', 'https://maps.app.goo.gl/SetLqG4yJ17kTTzP7', 112, '2026-10-12 23:33:10'),
(276, '113', 'Palangka Raya', 'Komplek ruko city point, Jl. Letjend Suprapto No.10, Buliang, Kec. Batu Aji, Kota Batam, Kepulauan Riau, Buliang, Kec. Batu Aji, Kota Batam, Riau 29425', '0811-1119-9957', 'Buka : Pukul 10.00 - 20.00 WIB', 'BUKA SETIAP HARI', '', 'https://maps.app.goo.gl/oWDNnBEdrEFeBpdn9', 113, '2026-10-12 23:33:10'),
(277, '114', 'Pontianak', 'Jl. A.Yani, Bangka Belitung Laut, Kec. Pontianak Tenggara, Kota Pontianak, Kalimantan Barat 17157', '0811-1119-9010', 'Buka : Pukul 10.00 - 20.00 WITA', 'BUKA SETIAP HARI', '', 'https://maps.app.goo.gl/sXozSJJKBiW9KhLd7?g_st=ic', 114, '2026-10-12 23:33:10'),
(278, '115', 'Bangka Belitung', 'Jl. Pegadaian, Ps. Padi, Kec. Girimaya, Kota Pangkal Pinang, Kepulauan Bangka Belitung, 33684', '0811-1119-9921', 'Buka : Pukul 10.00 - 20.00 WIB', 'BUKA SETIAP HARI', '', 'https://maps.app.goo.gl/k9nSzKonU6VxxBPC8', 115, '2026-10-12 23:33:10'),
(281, '822', '8283-9999', 'Tangcity Mall, Jl. Jenderal Sudirman.1 Lantai LG D-09, Cikokol, Kec. Tangerang, Banten 15117', '0822-8283-9999', 'Buka : Pukul 10.00 - 18.00 WITA', 'BUKA SETIAP HARI', '', 'https://www.google.com/maps/place/Raja+Emas+Indonesia+Mall+ITC+BSD/@-6.2860989,106.6615573,17z/data=!3m2!4b1!5s0x2e69fb22ca888615:0xf30f174f8e9aff44!4m6!3m5!1s0x2e69fbba703e37ef:0x1781c609e507746d!8m2!3d-6.2860989!4d106.6641322!16s%2Fg%2F11sjlj3hfj?entry=ttu&g_ep=EgoyMDI1MDEyMi4wIKXMDSoASAFQAw%3D%3D', 822, '2026-10-12 23:33:10'),
(282, '882', '2043-2984', 'Jl. Adityawarman, Darmo, Kec. Wonokromo, Surabaya, Jawa Timur 60242', '0882-2043-2984', 'Buka : 10.00-22.00 WIB', 'BUKA SETIAP HARI', '', '', 882, '2026-10-12 23:33:10'),
(283, '999', 'Cabang Uji API', 'Alamat pengujian', '081234567890', '08:00 - 17:00', 'Data pengujian Postman', 'https://maps.app.goo.gl/Nu2HiT1ouKjHoqdD6', 'https://maps.app.goo.gl/Nu2HiT1ouKjHoqdD6', 0, '2026-10-13 00:20:21'),
(284, '116', 'Kepulauan Riau - Batam', 'Komplek ruko city point, Jl. Letjend Suprapto No.10, Buliang, Kec. Batu Aji, Kota Batam, Kepulauan Riau, Buliang, Kec. Batu Aji, Kota Batam, Riau 29425', '0811-1119-9957', 'Buka : Pukul 10.00 - 20.00 WIB', 'BUKA SETIAP HARI', '', 'https://maps.app.goo.gl/oWDNnBEdrEFeBpdn9', 116, '2026-10-19 03:21:06'),
(285, '117', 'Ambon', 'Jl. A. Y. Patty No.23, Kel Honipopu, Kec. Sirimau, Kota Ambon, Maluku 97126', '0811-1119-9960', 'Buka : Pukul 10.00 - 20.00 WIT', 'BUKA SETIAP HARI', '', 'https://maps.app.goo.gl/c2V1F3zP4Z6ZJ9CD8', 117, '2026-10-19 03:21:06'),
(286, '118', 'Tarakan', 'Jl. Yos Sudarso No 11, Selumit, Tarakan Tengah, Kota Tarakan, Kalimantan Utara, 77111', '0811-1118-3701', 'Jam Operasional : 10.00 - 20.00 WITA', 'BUKA SETIAP HARI', '', 'https://maps.app.goo.gl/pe4viF5SA3LtTvhx5', 118, '2026-10-19 03:21:06'),
(288, '811', '1927-9995', 'Jl. MH. Thamrin Ruko 8F, Panunggangan, Pinang, Kota Tangerang 15143', '0811-1927-9995', 'Jam Operasional : 10.00 - 20.00 WIB', 'BUKA SETIAP HARI', '', '', 811, '2026-10-21 04:35:24'),
(289, '821', '1580-0821', '', '0821-1580-0821', '', '', '', '', 821, '2026-10-21 04:35:24');

-- --------------------------------------------------------

--
-- Table structure for table `branches_blocklist`
--

CREATE TABLE `branches_blocklist` (
  `id` int UNSIGNED NOT NULL,
  `block_key` varchar(191) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `branch_number` varchar(50) DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `branches_blocklist`
--

INSERT INTO `branches_blocklist` (`id`, `block_key`, `title`, `branch_number`, `reason`, `created_at`) VALUES
(1, '1580-0821', '1580-0821', '821', 'Dihapus dari admin cabang', '2026-10-21 04:30:06'),
(2, '1927-9995', '1927-9995', '811', 'Dihapus dari admin cabang', '2026-10-21 04:31:30');

-- --------------------------------------------------------

--
-- Table structure for table `daily_visitors`
--

CREATE TABLE `daily_visitors` (
  `id` int NOT NULL,
  `visit_date` date NOT NULL,
  `views` int DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `daily_visitors`
--

INSERT INTO `daily_visitors` (`id`, `visit_date`, `views`) VALUES
(1, '2026-10-12', 7),
(8, '2026-10-13', 1),
(9, '2026-10-15', 1),
(10, '2026-10-21', 1);

-- --------------------------------------------------------

--
-- Table structure for table `menus`
--

CREATE TABLE `menus` (
  `id` int NOT NULL,
  `title` varchar(100) NOT NULL,
  `url` varchar(255) NOT NULL,
  `parent_id` int DEFAULT '0',
  `sort_order` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `posts`
--

CREATE TABLE `posts` (
  `id` int NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `content` text,
  `custom_html` longtext,
  `custom_css` text,
  `custom_js` text,
  `featured_image` varchar(255) DEFAULT NULL,
  `type` enum('page','post') DEFAULT 'page',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `category_id` int UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `posts`
--

INSERT INTO `posts` (`id`, `title`, `slug`, `content`, `custom_html`, `custom_css`, `custom_js`, `featured_image`, `type`, `created_at`, `updated_at`, `category_id`) VALUES
(1, 'Beranda', 'beranda', '<p>Selamat datang di website resmi Raja Emas Indonesia.</p>', '<br />\r\n<b>Deprecated</b>:  htmlspecialchars(): Passing null to parameter #1 ($string) of type string is deprecated in <b>C:\\laragon\\www\\rei\\edit-page.php</b> on line <b>107</b><br />\r\n', '<br />\r\n<b>Deprecated</b>:  htmlspecialchars(): Passing null to parameter #1 ($string) of type string is deprecated in <b>C:\\laragon\\www\\rei\\edit-page.php</b> on line <b>111</b><br />\r\n', '<br />\r\n<b>Deprecated</b>:  htmlspecialchars(): Passing null to parameter #1 ($string) of type string is deprecated in <b>C:\\laragon\\www\\rei\\edit-page.php</b> on line <b>115</b><br />\r\n', NULL, 'page', '2026-10-12 00:25:39', '2026-10-12 02:17:53', NULL),
(2, 'Mau Jual Emas Tanpa Surat? Kami Beli dengan Harga Terbaik', 'mau-jual-emas-tanpa-surat-kami-beli-dengan-harga-terbaik', '<div class=\"bde-columns-492-101 bde-columns\">\r\n<div class=\"bde-column-492-102 bde-column\">\r\n<div class=\"bde-text-492-131 bde-text\">Kami membeli berbagai jenis Logam Mulia seperti Emas Perhiasan, Emas Batangan, Emas Tanpa Surat, Emas Lama, Emas Warisan dan Emas Patah / Rusak.</div>\r\n<div class=\"bde-div-492-375 bde-div\">\r\n<div class=\"bde-div-492-376 bde-div\">&nbsp;</div>\r\n<div class=\"bde-div-492-378 bde-div\">&nbsp;</div>\r\n</div>\r\n</div>\r\n</div>\r\n<div class=\"bde-div-492-653 bde-div\">&nbsp;</div>', NULL, NULL, NULL, 'uploads/1791790523_logo_rei_2__1_.png', 'post', '2026-10-12 00:35:23', '2026-10-12 00:35:23', 1);

-- --------------------------------------------------------

--
-- Table structure for table `post_categories`
--

CREATE TABLE `post_categories` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `post_categories`
--

INSERT INTO `post_categories` (`id`, `name`, `slug`, `created_at`) VALUES
(1, 'Berita', 'berita', '2026-10-12 00:32:35');

-- --------------------------------------------------------

--
-- Table structure for table `site_settings`
--

CREATE TABLE `site_settings` (
  `id` int UNSIGNED NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` longtext,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sliders`
--

CREATE TABLE `sliders` (
  `id` int NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `caption` text,
  `image_path` varchar(255) NOT NULL,
  `show_on_page` varchar(100) DEFAULT 'all',
  `sort_order` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `slider_settings`
--

CREATE TABLE `slider_settings` (
  `id` int NOT NULL,
  `height` int DEFAULT '450',
  `border_radius` int DEFAULT '20',
  `transition_ms` int DEFAULT '500',
  `interval_ms` int DEFAULT '4000',
  `indicator_mode` varchar(50) DEFAULT 'default',
  `indicator_size` int DEFAULT '12',
  `indicator_show` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `slider_settings`
--

INSERT INTO `slider_settings` (`id`, `height`, `border_radius`, `transition_ms`, `interval_ms`, `indicator_mode`, `indicator_size`, `indicator_show`) VALUES
(1, 450, 20, 500, 4000, 'default', 12, 1);

-- --------------------------------------------------------

--
-- Table structure for table `theme_settings`
--

CREATE TABLE `theme_settings` (
  `id` int NOT NULL,
  `bg_color_1` varchar(50) DEFAULT '#4A00E0',
  `bg_color_2` varchar(50) DEFAULT '#8E2DE2',
  `glass_opacity` decimal(3,2) DEFAULT '0.15',
  `blur_px` int DEFAULT '15',
  `text_color` varchar(50) DEFAULT '#ffffff'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `theme_settings`
--

INSERT INTO `theme_settings` (`id`, `bg_color_1`, `bg_color_2`, `glass_opacity`, `blur_px`, `text_color`) VALUES
(1, '#3b1c32', '#5c2a4f', 0.15, 15, '#ffffff');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`) VALUES
(1, 'admin', '$2a$12$214xCCUMAbxo0FwIOksusOqeO79snQjW/Kk8LubxNvf0UR8WKAz7y'),
(2, 'AndriITDev', '$2a$12$vDuTb4ZHww0vqfoNG9nd0e72eUJBP1XuUFusCrW3OBluYIuxoKU4K'),
(3, 'DoniRamdhani', '$2a$12$iK5.nyQE8aaZZD6tilW9au9N1q4xlSVUsK06w6AmBxssWeICHBilK'),
(4, 'HanifSyuhada', '$2a$12$UVVdQj4Ld8UtW/5U49zGeOn44NbHigKOjG/PbiyLwuY56cigVhSGO');

-- --------------------------------------------------------

--
-- Table structure for table `visitor_stats`
--

CREATE TABLE `visitor_stats` (
  `id` int NOT NULL,
  `total_views` int DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `visitor_stats`
--

INSERT INTO `visitor_stats` (`id`, `total_views`) VALUES
(1, 110);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `api_doc_users`
--
ALTER TABLE `api_doc_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_username` (`username`);

--
-- Indexes for table `branches`
--
ALTER TABLE `branches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_branch_number` (`branch_number`);

--
-- Indexes for table `branches_blocklist`
--
ALTER TABLE `branches_blocklist`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_block_key` (`block_key`);

--
-- Indexes for table `daily_visitors`
--
ALTER TABLE `daily_visitors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `visit_date` (`visit_date`);

--
-- Indexes for table `menus`
--
ALTER TABLE `menus`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `posts`
--
ALTER TABLE `posts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `post_categories`
--
ALTER TABLE `post_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_category_name` (`name`),
  ADD UNIQUE KEY `unique_category_slug` (`slug`);

--
-- Indexes for table `site_settings`
--
ALTER TABLE `site_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `sliders`
--
ALTER TABLE `sliders`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `slider_settings`
--
ALTER TABLE `slider_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `theme_settings`
--
ALTER TABLE `theme_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `visitor_stats`
--
ALTER TABLE `visitor_stats`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `api_doc_users`
--
ALTER TABLE `api_doc_users`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `branches`
--
ALTER TABLE `branches`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=290;

--
-- AUTO_INCREMENT for table `branches_blocklist`
--
ALTER TABLE `branches_blocklist`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `daily_visitors`
--
ALTER TABLE `daily_visitors`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `menus`
--
ALTER TABLE `menus`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `posts`
--
ALTER TABLE `posts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `post_categories`
--
ALTER TABLE `post_categories`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `site_settings`
--
ALTER TABLE `site_settings`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sliders`
--
ALTER TABLE `sliders`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `slider_settings`
--
ALTER TABLE `slider_settings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `theme_settings`
--
ALTER TABLE `theme_settings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `visitor_stats`
--
ALTER TABLE `visitor_stats`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
