<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}
require 'config.php';

// Otomatis buat tabel branches jika belum ada
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `branches` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `branch_number` VARCHAR(10) NOT NULL,
      `title` VARCHAR(255) NOT NULL,
      `address` TEXT NOT NULL,
      `phone` VARCHAR(50) NOT NULL,
      `hours` VARCHAR(100) NOT NULL,
      `notes` VARCHAR(255) DEFAULT NULL,
      `map_embed_url` TEXT NOT NULL,
      `map_link` TEXT NOT NULL,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) {}

$page = $_GET['page'] ?? 'dashboard';
$msg = '';
$msg_setting = '';
$sync_log = '';
$sync_error = '';
if (empty($_SESSION['branch_sync_csrf'])) {
    $_SESSION['branch_sync_csrf'] = bin2hex(random_bytes(32));
}

// A. Proses Simpan Tema
if ($page === 'theme' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare("UPDATE theme_settings SET bg_color_1=?, bg_color_2=?, glass_opacity=?, blur_px=?, text_color=? WHERE id=1");
    $stmt->execute([$_POST['bg_color_1'], $_POST['bg_color_2'], $_POST['glass_opacity'], $_POST['blur_px'], $_POST['text_color']]);
    $msg_setting = "Tema berhasil diperbarui!";
}

// B. Proses Simpan Pengaturan Slider & Indikator
if ($page === 'slider' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    $indicator_show = isset($_POST['indicator_show']) ? 1 : 0;
    
    $stmt = $pdo->prepare("UPDATE slider_settings SET height=?, border_radius=?, transition_ms=?, interval_ms=?, indicator_mode=?, indicator_size=?, indicator_show=? WHERE id=1");
    $stmt->execute([
        $_POST['height'], 
        $_POST['border_radius'], 
        $_POST['transition_ms'], 
        $_POST['interval_ms'],
        $_POST['indicator_mode'],
        $_POST['indicator_size'],
        $indicator_show
    ]);
    $msg = "Pengaturan tampilan & indikator slider berhasil disimpan!";
}

// C. Proses Upload Slider
if ($page === 'slider' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['slider_image'])) {
    $target_dir = "uploads/";
    if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
    $file_name = time() . "_" . preg_replace("/[^a-zA-Z0-9.]/", "_", basename($_FILES["slider_image"]["name"])); 
    $target_file = $target_dir . $file_name;
    $show_on_page = $_POST['show_on_page'] ?? 'home';

    if(in_array(strtolower(pathinfo($target_file, PATHINFO_EXTENSION)), ['jpg', 'png', 'jpeg', 'webp'])) {
        if (move_uploaded_file($_FILES["slider_image"]["tmp_name"], $target_file)) {
            $stmt = $pdo->prepare("INSERT INTO sliders (image_path, title, caption, show_on_page) VALUES (?, ?, ?, ?)");
            $stmt->execute([$target_file, $_POST['title'], $_POST['caption'], $show_on_page]);
            $msg = "Slider berhasil ditambahkan!";
        }
    }
}
if ($page === 'slider' && isset($_GET['delete_slider'])) {
    $id = $_GET['delete_slider'];
    $slider = $pdo->prepare("SELECT image_path FROM sliders WHERE id = ?");
    $slider->execute([$id]);
    $s = $slider->fetch();
    if ($s) {
        if(file_exists($s['image_path'])) unlink($s['image_path']);
        $pdo->prepare("DELETE FROM sliders WHERE id = ?")->execute([$id]);
    }
    header("Location: admin.php?page=slider");
    exit;
}

// D. Proses Kelola Menu
if ($page === 'menus' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare("INSERT INTO menus (title, url, parent_id, sort_order) VALUES (?, ?, ?, ?)");
    $stmt->execute([$_POST['title'], $_POST['url'], $_POST['parent_id'], $_POST['sort_order'] ?? 0]);
    $msg = "Menu berhasil ditambahkan!";
}
if ($page === 'menus' && isset($_GET['delete_menu'])) {
    $id = $_GET['delete_menu'];
    $pdo->prepare("DELETE FROM menus WHERE id = ? OR parent_id = ?")->execute([$id, $id]);
    header("Location: admin.php?page=menus");
    exit;
}

// E. Proses Kelola Kategori Post
if ($page === 'categories' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
    try {
        $stmt = $pdo->prepare("INSERT INTO post_categories (name, slug) VALUES (?, ?)");
        $stmt->execute([$name, $slug]);
        $msg = "Kategori berhasil ditambahkan!";
    } catch (PDOException $e) {
        $msg = "Error: Kategori sudah ada atau gagal disimpan.";
    }
}
if ($page === 'categories' && isset($_GET['delete_cat'])) {
    $cat_id = $_GET['delete_cat'];
    $pdo->prepare("DELETE FROM post_categories WHERE id = ?")->execute([$cat_id]);
    header("Location: admin.php?page=categories");
    exit;
}

// F. Proses Kelola Halaman (Pages)
if ($page === 'pages' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
    $content = $_POST['content'] ?? '';
    $custom_html = $_POST['custom_html'] ?? '';
    $custom_css = $_POST['custom_css'] ?? '';
    $custom_js = $_POST['custom_js'] ?? '';
    
    $featured_image = null;
    if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === UPLOAD_ERR_OK) {
        $target_dir = "uploads/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        $file_name = time() . "_" . preg_replace("/[^a-zA-Z0-9.]/", "_", basename($_FILES["featured_image"]["name"]));
        $target_file = $target_dir . $file_name;
        if (in_array(strtolower(pathinfo($target_file, PATHINFO_EXTENSION)), ['jpg', 'png', 'jpeg', 'webp'])) {
            if (move_uploaded_file($_FILES["featured_image"]["tmp_name"], $target_file)) {
                $featured_image = $target_file;
            }
        }
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO posts (title, slug, content, custom_html, custom_css, custom_js, featured_image, type) VALUES (?, ?, ?, ?, ?, ?, ?, 'page')");
        $stmt->execute([$title, $slug, $content, $custom_html, $custom_css, $custom_js, $featured_image]);
        $msg = "Halaman kustom berhasil dibuat!";
    } catch (PDOException $e) {
        $msg = "Error Database: " . $e->getMessage();
    }
}
if ($page === 'pages' && isset($_GET['delete_page'])) {
    $id = $_GET['delete_page'];
    $pdo->prepare("DELETE FROM posts WHERE id = ? AND type = 'page'")->execute([$id]);
    header("Location: admin.php?page=pages");
    exit;
}

// G. Proses Kelola Cabang Toko (Tambah, Edit, Hapus, Sinkronisasi)
if ($page === 'branches' && $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'sync_branches') {
    $csrf = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals((string) $_SESSION['branch_sync_csrf'], $csrf)) {
        http_response_code(403);
        $sync_error = 'Token keamanan tidak valid. Muat ulang halaman lalu coba lagi.';
    } else {
        $scraper_file = __DIR__ . DIRECTORY_SEPARATOR . 'scrape-branches.php';
        if (!is_file($scraper_file)) {
            $sync_error = 'File scrape-branches.php tidak ditemukan di folder utama REI CMS.';
        } else {
            @set_time_limit(180);
            ob_start();
            try {
                require_once $scraper_file;
                main();
                $sync_log = (string) ob_get_contents();
                $msg = 'Sinkronisasi cabang selesai. Periksa ringkasan hasil di bawah.';
            } catch (Throwable $e) {
                $sync_log = (string) ob_get_contents();
                $sync_error = $e->getMessage();
            } finally {
                ob_end_clean();
            }
            $_SESSION['branch_sync_csrf'] = bin2hex(random_bytes(32));
        }
    }
} elseif ($page === 'branches' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'edit') {
        $stmt = $pdo->prepare("UPDATE branches SET branch_number = ?, title = ?, address = ?, phone = ?, hours = ?, notes = ?, map_embed_url = ?, map_link = ? WHERE id = ?");
        $stmt->execute([
            $_POST['branch_number'], $_POST['title'], $_POST['address'], 
            $_POST['phone'], $_POST['hours'], $_POST['notes'], 
            $_POST['map_embed_url'] ?? '', $_POST['map_link'] ?? '', $_POST['branch_id']
        ]);
        $msg = "Cabang toko berhasil diperbarui!";
    } else {
        $stmt = $pdo->prepare("INSERT INTO branches (branch_number, title, address, phone, hours, notes, map_embed_url, map_link) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['branch_number'], $_POST['title'], $_POST['address'], 
            $_POST['phone'], $_POST['hours'], $_POST['notes'], 
            $_POST['map_embed_url'] ?? '', $_POST['map_link'] ?? ''
        ]);
        $msg = "Cabang toko berhasil ditambahkan!";
    }
}
if ($page === 'branches' && isset($_GET['delete_branch'])) {
    $id = $_GET['delete_branch'];
    $pdo->prepare("DELETE FROM branches WHERE id = ?")->execute([$id]);
    header("Location: admin.php?page=branches");
    exit;
}

$edit_branch_data = null;
if ($page === 'branches' && isset($_GET['edit_branch'])) {
    $stmt_edit = $pdo->prepare("SELECT * FROM branches WHERE id = ?");
    $stmt_edit->execute([$_GET['edit_branch']]);
    $edit_branch_data = $stmt_edit->fetch(PDO::FETCH_ASSOC);
}

// --- AMBIL DATA DENGAN AMAN ---
try {
    $posts = $pdo->query("SELECT posts.*, post_categories.name as category_name FROM posts LEFT JOIN post_categories ON posts.category_id = post_categories.id WHERE posts.type='post' ORDER BY posts.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $posts = []; }

try {
    $pages_list = $pdo->query("SELECT * FROM posts WHERE type='page' ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $pages_list = []; }

try {
    $sliders = $pdo->query("SELECT * FROM sliders ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $sliders = []; }

try {
    $branches_list = $pdo->query("SELECT * FROM branches ORDER BY branch_number ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $branches_list = []; }

try {
    $theme = $pdo->query("SELECT * FROM theme_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $theme = []; }

try {
    $s_set = $pdo->query("SELECT * FROM slider_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $s_set = []; }

try {
    $parent_menus = $pdo->query("SELECT * FROM menus WHERE parent_id = 0 ORDER BY sort_order ASC")->fetchAll(PDO::FETCH_ASSOC);
    $all_menus = $pdo->query("SELECT * FROM menus ORDER BY parent_id ASC, sort_order ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $parent_menus = []; $all_menus = []; }

try {
    $categories_list = $pdo->query("SELECT * FROM post_categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $categories_list = []; }

try {
    $total_visitors = $pdo->query("SELECT total_views FROM visitor_stats WHERE id = 1")->fetchColumn() ?: 0;
} catch (PDOException $e) { $total_visitors = 0; }

try {
    $chart_data = $pdo->query("SELECT visit_date, views FROM daily_visitors ORDER BY visit_date ASC LIMIT 7")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $chart_data = []; }

$chart_labels = [];
$chart_values = [];
foreach($chart_data as $cd) {
    $chart_labels[] = date('d M', strtotime($cd['visit_date']));
    $chart_values[] = $cd['views'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - REI-CMS</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="logo-rei-2.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.8.2/tinymce.min.js"></script>
    <script>
        tinymce.init({ selector: 'textarea.editor', height: 200, menubar: false, plugins: 'lists link code', toolbar: 'undo redo | bold italic | alignleft aligncenter | bullist numlist' });
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f3f4f6; }
        .sidebar-scroll::-webkit-scrollbar { width: 5px; }
        .sidebar-scroll::-webkit-scrollbar-track { background: transparent; }
        .sidebar-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .sidebar-scroll::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 4px; }
    </style>
</head>
<body class="text-gray-800 antialiased">
    <div class="flex h-screen overflow-hidden p-3 md:p-4 gap-4">
        
        <aside class="hidden md:flex flex-col w-72 bg-white rounded-3xl shadow-xl border border-gray-100/80 z-10 overflow-hidden transition-all duration-300">
            <div class="flex items-center justify-center h-20 px-4 bg-gradient-to-br from-purple-600 to-black shadow-inner m-3 rounded-2xl shrink-0">
                <img src="logo-rei.png" alt="Logo" class="max-h-14 w-auto object-contain filter brightness-150 contrast-125 drop-shadow-md">
            </div>
            
            <nav class="flex-1 px-3 py-2 space-y-1.5 overflow-y-auto sidebar-scroll">
                <a href="admin.php?page=dashboard" class="flex items-center gap-3 px-4 py-3 text-sm font-medium rounded-2xl <?= $page === 'dashboard' ? 'bg-cyan-600 text-white shadow-md shadow-cyan-200' : 'text-gray-600 hover:bg-gray-50' ?> transition">
                    <span class="material-icons text-lg">dashboard</span> Dashboard
                </a>
                
                <div class="space-y-1">
                    <button type="button" onclick="toggleSubmenu('post-submenu', 'post-arrow')" class="w-full flex items-center justify-between px-4 py-3 text-sm font-medium rounded-2xl <?= ($page === 'posts' || $page === 'categories') ? 'bg-cyan-600 text-white shadow-md shadow-cyan-200' : 'text-gray-600 hover:bg-gray-50' ?> transition">
                        <span class="flex items-center gap-3">
                            <span class="material-icons text-lg">article</span> Post
                        </span>
                        <span id="post-arrow" class="material-icons text-sm transform transition-transform duration-200 <?= ($page === 'posts' || $page === 'categories') ? 'rotate-180' : '' ?>">expand_more</span>
                    </button>
                    
                    <div id="post-submenu" class="<?= ($page === 'posts' || $page === 'categories') ? 'block' : 'hidden' ?> pl-3 space-y-1">
                        <a href="admin.php?page=posts" class="flex items-center gap-2 pl-8 pr-4 py-2.5 text-xs font-medium rounded-xl <?= $page === 'posts' ? 'bg-gray-200 text-gray-900 font-semibold shadow-sm' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' ?> transition">
                            <span class="material-icons text-xs">list</span> Semua Artikel
                        </a>
                        <a href="admin.php?page=categories" class="flex items-center gap-2 pl-8 pr-4 py-2.5 text-xs font-medium rounded-xl <?= $page === 'categories' ? 'bg-gray-200 text-gray-900 font-semibold shadow-sm' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' ?> transition">
                            <span class="material-icons text-xs">category</span> Kategori Post
                        </a>
                    </div>
                </div>

                <a href="admin.php?page=pages" class="flex items-center gap-3 px-4 py-3 text-sm font-medium rounded-2xl <?= $page === 'pages' ? 'bg-cyan-600 text-white shadow-md shadow-cyan-200' : 'text-gray-600 hover:bg-gray-50' ?> transition">
                    <span class="material-icons text-lg">description</span> Halaman
                </a>
                <a href="admin.php?page=menus" class="flex items-center gap-3 px-4 py-3 text-sm font-medium rounded-2xl <?= $page === 'menus' ? 'bg-cyan-600 text-white shadow-md shadow-cyan-200' : 'text-gray-600 hover:bg-gray-50' ?> transition">
                    <span class="material-icons text-lg">menu</span> Menu
                </a>
                <a href="admin.php?page=slider" class="flex items-center gap-3 px-4 py-3 text-sm font-medium rounded-2xl <?= $page === 'slider' ? 'bg-cyan-600 text-white shadow-md shadow-cyan-200' : 'text-gray-600 hover:bg-gray-50' ?> transition">
                    <span class="material-icons text-lg">collections</span> Slider
                </a>
                <a href="admin.php?page=branches" class="flex items-center gap-3 px-4 py-3 text-sm font-medium rounded-2xl <?= $page === 'branches' ? 'bg-cyan-600 text-white shadow-md shadow-cyan-200' : 'text-gray-600 hover:bg-gray-50' ?> transition">
                    <span class="material-icons text-lg">storefront</span> Cabang Toko
                </a>
                <a href="admin.php?page=theme" class="flex items-center gap-3 px-4 py-3 text-sm font-medium rounded-2xl <?= $page === 'theme' ? 'bg-cyan-600 text-white shadow-md shadow-cyan-200' : 'text-gray-600 hover:bg-gray-50' ?> transition">
                    <span class="material-icons text-lg">palette</span> Tema
                </a>
                <a href="index.php" target="_blank" class="flex items-center gap-3 px-4 py-3 text-sm font-medium rounded-2xl text-gray-600 hover:bg-gray-50 transition">
                    <span class="material-icons text-lg">open_in_new</span> Website
                </a>
            </nav>

            <div class="p-3 border-t border-gray-100 bg-gray-50/50 shrink-0">
                <a href="logout.php" class="flex items-center gap-3 px-4 py-3 text-sm font-medium text-white bg-gradient-to-r from-purple-600 to-black rounded-xl hover:opacity-95 transition shadow-sm">
                    <span class="material-icons text-lg">logout</span> Logout
                </a>
            </div>
        </aside>

        <div class="flex flex-col flex-1 h-full bg-white rounded-3xl shadow-xl border border-gray-100/80 overflow-hidden">
            <header class="flex items-center justify-between h-20 px-8 bg-white border-b border-gray-100 z-10 shrink-0">
                <h1 class="text-xl font-bold text-gray-800 capitalize">Panel: <?= htmlspecialchars($page) ?></h1>
                <?php if($page === 'posts'): ?>
                    <a href="add.php" class="inline-flex items-center gap-2 bg-cyan-600 hover:bg-cyan-700 text-white text-sm font-medium px-4 py-2.5 rounded-xl shadow-md shadow-cyan-100 transition">
                        <span class="material-icons text-sm">add</span> Artikel Baru
                    </a>
                <?php endif; ?>
            </header>

            <main class="flex-1 overflow-y-auto p-6 md:p-8 bg-gray-50/40">
                <?php if ($page === 'dashboard'): ?>
                    <div class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="bg-gradient-to-br from-cyan-600 to-cyan-900 text-white p-6 rounded-2xl shadow-sm flex flex-col justify-between">
                                <div class="flex justify-between items-center">
                                    <span class="text-xs uppercase tracking-wider font-semibold opacity-80">Waktu Lokal Server</span>
                                    <span class="material-icons opacity-80">schedule</span>
                                </div>
                                <div class="my-4">
                                    <h2 id="live-clock" class="text-4xl font-bold tracking-tight">00:00:00</h2>
                                    <p id="live-date" class="text-cyan-100 text-sm mt-1">Memuat tanggal...</p>
                                </div>
                                <div class="text-xs opacity-75">Zona Waktu Aktif: Sistem Lokal</div>
                            </div>
                            <div class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm flex flex-col justify-between">
                                <div class="flex justify-between items-center mb-2">
                                    <span id="cal-month-year" class="text-sm font-bold text-gray-800">Bulan Tahun</span>
                                    <span class="material-icons text-cyan-600">event</span>
                                </div>
                                <div class="grid grid-cols-7 gap-1 text-center text-xs font-semibold text-gray-400 mb-1">
                                    <span>Min</span><span>Sen</span><span>Sel</span><span>Rab</span><span>Kam</span><span>Jum</span><span>Sab</span>
                                </div>
                                <div id="cal-days" class="grid grid-cols-7 gap-1 text-center text-sm"></div>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                            <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm flex items-center justify-between">
                                <div><p class="text-xs font-medium text-gray-400 uppercase">Total Postingan</p><h4 class="text-2xl font-bold text-gray-900 mt-1"><?= count($posts) ?></h4></div>
                                <div class="w-10 h-10 bg-cyan-50 text-cyan-600 rounded-xl flex items-center justify-center"><span class="material-icons text-sm">article</span></div>
                            </div>
                            <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm flex items-center justify-between">
                                <div><p class="text-xs font-medium text-gray-400 uppercase">Kategori Post</p><h4 class="text-2xl font-bold text-gray-900 mt-1"><?= count($categories_list) ?></h4></div>
                                <div class="w-10 h-10 bg-sky-50 text-sky-600 rounded-xl flex items-center justify-center"><span class="material-icons text-sm">category</span></div>
                            </div>
                            <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm flex items-center justify-between">
                                <div><p class="text-xs font-medium text-gray-400 uppercase">Cabang Toko</p><h4 class="text-2xl font-bold text-gray-900 mt-1"><?= count($branches_list) ?></h4></div>
                                <div class="w-10 h-10 bg-purple-50 text-purple-600 rounded-xl flex items-center justify-center"><span class="material-icons text-sm">storefront</span></div>
                            </div>
                            <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm flex items-center justify-between">
                                <div><p class="text-xs font-medium text-gray-400 uppercase">Total Pengunjung</p><h4 class="text-2xl font-bold text-gray-900 mt-1"><?= number_format($total_visitors) ?></h4></div>
                                <div class="w-10 h-10 bg-amber-50 text-amber-600 rounded-xl flex items-center justify-center"><span class="material-icons text-sm">visibility</span></div>
                            </div>
                        </div>
                        <div class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm">
                            <div class="flex justify-between items-center mb-4"><h3 class="font-semibold text-gray-800">Grafik Statistik Pengunjung</h3></div>
                            <div style="height: 280px; position: relative;"><canvas id="visitorChart"></canvas></div>
                        </div>
                    </div>
                    <script>
                        function updateClock() {
                            const now = new Date();
                            document.getElementById('live-clock').innerText = now.toLocaleTimeString();
                            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
                            document.getElementById('live-date').innerText = now.toLocaleDateString('id-ID', options);
                        }
                        setInterval(updateClock, 1000);
                        updateClock();
                        function renderCalendar() {
                            const now = new Date();
                            const year = now.getFullYear();
                            const month = now.getMonth();
                            const today = now.getDate();
                            const firstDay = new Date(year, month, 1).getDay();
                            const totalDays = new Date(year, month + 1, 0).getDate();
                            const monthsNames = ["Januari", "Februari", "Maret", "April", "Mei", "Juni", "Juli", "Agustus", "September", "Oktober", "November", "Desember"];
                            document.getElementById('cal-month-year').innerText = `${monthsNames[month]} ${year}`;
                            let daysHTML = '';
                            for (let i = 0; i < firstDay; i++) { daysHTML += `<span></span>`; }
                            for (let d = 1; d <= totalDays; d++) {
                                let isToday = (d === today) ? 'bg-cyan-600 text-white rounded-full font-bold' : 'text-gray-700 hover:bg-gray-100 rounded-full';
                                daysHTML += `<span class="h-7 w-7 flex items-center justify-center mx-auto text-xs ${isToday}">${d}</span>`;
                            }
                            document.getElementById('cal-days').innerHTML = daysHTML;
                        }
                        renderCalendar();
                        const ctx = document.getElementById('visitorChart').getContext('2d');
                        new Chart(ctx, {
                            type: 'line',
                            data: {
                                labels: <?= json_encode($chart_labels) ?>,
                                datasets: [{ label: 'Pengunjung', data: <?= json_encode($chart_values) ?>, borderColor: '#0891b2', backgroundColor: 'rgba(8, 145, 178, 0.08)', borderWidth: 3, fill: true, tension: 0.35 }]
                            },
                            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, grid: { color: '#f3f4f6' } }, x: { grid: { display: false } } } }
                        });
                    </script>

                <?php elseif ($page === 'branches'): ?>
                    <?php if($msg): ?><div class='mb-4 p-4 bg-emerald-50 text-emerald-700 rounded-xl text-sm font-medium'><?= htmlspecialchars($msg) ?></div><?php endif; ?>
                    <?php if($sync_error): ?><div class='mb-4 p-4 bg-red-50 border border-red-200 text-red-700 rounded-xl text-sm font-medium'><strong>Sinkronisasi gagal:</strong> <?= htmlspecialchars($sync_error) ?></div><?php endif; ?>
                    <div class="mb-6 bg-white p-5 md:p-6 rounded-2xl border border-gray-200 shadow-sm flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                        <div>
                            <h3 class="font-semibold text-gray-800 flex items-center gap-2"><span class="material-icons text-cyan-600">sync</span> Sinkronisasi Data Cabang</h3>
                            <p class="text-sm text-gray-500 mt-1">Ambil data cabang terbaru dari website Raja Emas Indonesia dan perbarui database.</p>
                        </div>
                        <form method="POST" onsubmit="const b=this.querySelector('button');b.disabled=true;b.innerHTML='Menyinkronkan...';" class="shrink-0">
                            <input type="hidden" name="action" value="sync_branches">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) $_SESSION['branch_sync_csrf'], ENT_QUOTES, 'UTF-8') ?>">
                            <button type="submit" class="inline-flex items-center justify-center gap-2 bg-cyan-600 hover:bg-cyan-700 disabled:opacity-60 text-white text-sm font-semibold px-5 py-3 rounded-xl shadow-md transition"><span class="material-icons text-lg">sync</span> Sinkronisasi Cabang</button>
                        </form>
                    </div>
                    <?php if($sync_log !== ''): ?>
                        <div class="mb-6 bg-gray-950 text-green-300 p-5 rounded-2xl shadow-sm overflow-hidden">
                            <h3 class="text-white font-semibold mb-3">Log Sinkronisasi</h3>
                            <pre class="text-xs leading-relaxed whitespace-pre-wrap break-words overflow-x-auto max-h-[520px]"><?= htmlspecialchars($sync_log, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></pre>
                        </div>
                    <?php endif; ?>
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        
                        <div class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm h-fit">
                            <h3 class="font-semibold text-gray-800 mb-4 flex items-center gap-2">
                                <span class="material-icons text-cyan-600"><?= $edit_branch_data ? 'edit' : 'add_business' ?></span> 
                                <?= $edit_branch_data ? 'Edit Cabang Toko' : 'Tambah Cabang Toko' ?>
                            </h3>
                            <form method="POST" class="space-y-4">
                                <?php if($edit_branch_data): ?>
                                    <input type="hidden" name="action" value="edit">
                                    <input type="hidden" name="branch_id" value="<?= $edit_branch_data['id'] ?>">
                                <?php endif; ?>

                                <div>
                                    <label class="text-xs font-medium text-gray-500">No. Cabang (Contoh: 01, 02)</label>
                                    <input type="text" name="branch_number" required value="<?= htmlspecialchars($edit_branch_data['branch_number'] ?? '') ?>" placeholder="01" class="w-full mt-1 p-2.5 border rounded-xl text-sm">
                                </div>
                                <div>
                                    <label class="text-xs font-medium text-gray-500">Nama Cabang / Judul</label>
                                    <input type="text" name="title" required value="<?= htmlspecialchars($edit_branch_data['title'] ?? '') ?>" placeholder="Jakarta Utara - Batavia PIK" class="w-full mt-1 p-2.5 border rounded-xl text-sm">
                                </div>
                                <div>
                                    <label class="text-xs font-medium text-gray-500">Alamat Lengkap</label>
                                    <textarea name="address" required rows="2" placeholder="Rukan Golf Island..." class="w-full mt-1 p-2.5 border rounded-xl text-sm"><?= htmlspecialchars($edit_branch_data['address'] ?? '') ?></textarea>
                                </div>
                                <div>
                                    <label class="text-xs font-medium text-gray-500">No. HP / WhatsApp</label>
                                    <input type="text" name="phone" required value="<?= htmlspecialchars($edit_branch_data['phone'] ?? '') ?>" placeholder="0822-9999-5125" class="w-full mt-1 p-2.5 border rounded-xl text-sm">
                                </div>
                                <div>
                                    <label class="text-xs font-medium text-gray-500">Jam Operasional</label>
                                    <input type="text" name="hours" required value="<?= htmlspecialchars($edit_branch_data['hours'] ?? '') ?>" placeholder="Pukul 10.00 - 21.00 WIB" class="w-full mt-1 p-2.5 border rounded-xl text-sm">
                                </div>
                                <div>
                                    <label class="text-xs font-medium text-gray-500">Catatan Khusus</label>
                                    <input type="text" name="notes" value="<?= htmlspecialchars($edit_branch_data['notes'] ?? '') ?>" placeholder="BUKA SETIAP HARI" class="w-full mt-1 p-2.5 border rounded-xl text-sm">
                                </div>
                                <!-- <div>
                                    <label class="text-xs font-medium text-gray-500">Link URL Embed Google Maps (src iframe saja)</label>
                                    <textarea name="map_embed_url" rows="2" placeholder="https://www.google.com/maps/embed?pb=..." class="w-full mt-1 p-2.5 border rounded-xl text-xs font-mono"><?= htmlspecialchars($edit_branch_data['map_embed_url'] ?? '') ?></textarea>
                                </div> -->
                                <div>
                                    <label class="text-xs font-medium text-gray-500">Link Tombol "Menuju Lokasi"</label>
                                    <input type="text" name="map_link" value="<?= htmlspecialchars($edit_branch_data['map_link'] ?? '') ?>" placeholder="https://maps.google.com/?q=..." class="w-full mt-1 p-2.5 border rounded-xl text-sm">
                                </div>

                                <div class="flex gap-2">
                                    <button type="submit" class="flex-1 bg-cyan-600 text-white text-sm font-medium py-2.5 rounded-xl hover:bg-cyan-700 transition shadow-md">
                                        <?= $edit_branch_data ? 'Perbarui Cabang' : 'Simpan Cabang' ?>
                                    </button>
                                    <?php if($edit_branch_data): ?>
                                        <a href="admin.php?page=branches" class="px-4 bg-gray-200 text-gray-700 text-sm font-medium py-2.5 rounded-xl hover:bg-gray-300 transition text-center">Batal</a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>

                        <div class="lg:col-span-2 bg-white p-6 rounded-2xl border border-gray-200 shadow-sm">
                            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-5">
                                <div>
                                    <h3 class="font-semibold text-gray-800">Daftar & Pratinjau Cabang Toko</h3>
                                    <p class="text-xs text-gray-500 mt-1">Filter khusus berdasarkan nomor cabang, nama cabang, atau nomor WhatsApp.</p>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-2 w-full md:w-auto">
                                    <select id="branchFilterType" aria-label="Jenis filter cabang" class="w-full px-3 py-3 border border-gray-200 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-cyan-500">
                                        <option value="branch_number">No. Cabang</option>
                                        <option value="title">Nama Cabang</option>
                                        <option value="phone">No. WA Cabang</option>
                                    </select>
                                    <div class="relative">
                                        <span class="material-icons absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">search</span>
                                        <input type="search" id="branchSearchInput" placeholder="Masukkan kata kunci..." aria-label="Kata kunci filter cabang" class="w-full pl-10 pr-3 py-3 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500">
                                    </div>
                                    <button type="button" id="branchFilterButton" class="inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl bg-cyan-600 hover:bg-cyan-700 text-white text-sm font-semibold transition">
                                        <span class="material-icons text-lg">filter_list</span> Filter
                                    </button>
                                    <button type="button" id="branchResetButton" class="inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-semibold transition">
                                        <span class="material-icons text-lg">restart_alt</span> Reset
                                    </button>
                                </div>
                            </div>
                            <p id="branchSearchResult" class="text-sm text-gray-500 mb-4" aria-live="polite"></p>
                            <div id="branchCardsGrid" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <?php if(empty($branches_list)): ?>
                                    <p class="text-sm text-gray-400 italic col-span-2">Belum ada cabang toko yang ditambahkan.</p>
                                <?php else: ?>
                                    <?php foreach($branches_list as$b): ?>
                                    <div class="branch-card border-2 border-[#3b1c32] rounded-[30px] p-6 flex flex-col justify-between bg-white shadow-sm relative" data-branch-number="<?= htmlspecialchars(mb_strtolower((string)($b['branch_number'] ?? ''), 'UTF-8'), ENT_QUOTES, 'UTF-8') ?>" data-title="<?= htmlspecialchars(mb_strtolower((string)($b['title'] ?? ''), 'UTF-8'), ENT_QUOTES, 'UTF-8') ?>" data-phone="<?= htmlspecialchars(mb_strtolower((string)($b['phone'] ?? ''), 'UTF-8'), ENT_QUOTES, 'UTF-8') ?>">
                                        <div>
                                            <div class="flex justify-between items-start mb-3">
                                                <div>
                                                    <span class="text-2xl font-bold text-gray-900"><?= htmlspecialchars($b['branch_number']) ?></span>
                                                    <h4 class="text-xl font-bold text-gray-900 mt-1 leading-snug"><?= htmlspecialchars($b['title']) ?></h4>
                                                </div>
                                                <div class="flex items-center gap-1">
                                                    <a href="admin.php?page=branches&edit_branch=<?= $b['id'] ?>" class="text-amber-600 hover:text-amber-800 bg-amber-50 p-2 rounded-xl" title="Edit"><span class="material-icons text-sm">edit</span></a>
                                                    <a href="admin.php?page=branches&delete_branch=<?= $b['id'] ?>" onclick="return confirm('Hapus cabang ini?')" class="text-red-500 hover:text-red-700 bg-red-50 p-2 rounded-xl" title="Hapus"><span class="material-icons text-sm">delete</span></a>
                                                </div>
                                            </div>
                                            <p class="text-sm text-gray-700 mb-4"><?= nl2br(htmlspecialchars($b['address'])) ?></p>
                                            <p class="text-sm font-semibold text-[#3b1c32] mb-3 underline">Hp & Whatsapp: <?= htmlspecialchars($b['phone']) ?></p>
                                            <p class="text-sm text-gray-800">Buka : <?= htmlspecialchars($b['hours']) ?></p>
                                            <p class="text-sm text-gray-800 mb-4">Catatan: <?= htmlspecialchars($b['notes']) ?></p>

                                           
                                        </div>
                                        <a href="<?= !empty($b['map_link']) ? htmlspecialchars($b['map_link']) : '#' ?>" target="_blank" class="w-full py-3 bg-[#3b1c32] text-white rounded-2xl text-center text-sm font-medium flex items-center justify-center gap-2 hover:opacity-95 transition shadow-md">
                                            Menuju Lokasi <span class="material-icons text-sm">north_east</span>
                                        </a>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                <?php elseif ($page === 'posts'): ?>
                    <div class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center"><h3 class="font-semibold text-gray-800">Daftar Artikel</h3><span class="text-xs font-medium text-gray-500 bg-gray-100 px-3 py-1 rounded-full"><?= count($posts) ?> Postingan</span></div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead><tr class="bg-gray-50 text-gray-400 text-xs font-semibold uppercase border-b border-gray-100"><th class="py-3 px-6">ID</th><th class="py-3 px-6">Judul Artikel & Kategori</th><th class="py-3 px-6">Tanggal</th><th class="py-3 px-6 text-center">Aksi</th></tr></thead>
                                <tbody class="divide-y divide-gray-100 text-sm">
                                    <?php foreach ($posts as$post): ?>
                                    <tr class="hover:bg-gray-50 transition">
                                        <td class="py-4 px-6 text-gray-500">#<?= $post['id'] ?></td>
                                        <td class="py-4 px-6 font-medium text-gray-900"><?= htmlspecialchars($post['title']) ?><?php if(!empty($post['category_name'])): ?><span class="block text-[11px] font-normal text-cyan-600 mt-0.5">Kategori: <?= htmlspecialchars($post['category_name']) ?></span><?php endif; ?></td>
                                        <td class="py-4 px-6 text-gray-500"><?= date('d M Y, H:i', strtotime($post['created_at'])) ?></td>
                                        <td class="py-4 px-6 text-center space-x-2">
                                            <a href="edit.php?id=<?= $post['id'] ?>" class="p-1.5 rounded-lg bg-amber-50 text-amber-600 hover:bg-amber-100 inline-block"><span class="material-icons text-sm">edit</span></a>
                                            <a href="delete.php?id=<?= $post['id'] ?>" class="p-1.5 rounded-lg bg-red-50 text-red-600 hover:bg-red-100 inline-block" onclick="return confirm('Hapus?')"><span class="material-icons text-sm">delete</span></a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                <?php elseif ($page === 'categories'): ?>
                    <?php if($msg): ?><div class='mb-4 p-4 bg-emerald-50 text-emerald-600 rounded-xl text-sm'><?= htmlspecialchars($msg) ?></div><?php endif; ?>
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <div class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm">
                            <h3 class="font-semibold text-gray-800 mb-4">Tambah Kategori Baru</h3>
                            <form method="POST" class="space-y-4">
                                <div><label class="text-xs font-medium text-gray-500">Nama Kategori</label><input type="text" name="name" placeholder="Contoh: Berita" required class="w-full mt-1 p-2.5 border rounded-xl text-sm"></div>
                                <button type="submit" class="w-full bg-cyan-600 text-white text-sm font-medium py-2.5 rounded-xl hover:bg-cyan-700 transition">Simpan Kategori</button>
                            </form>
                        </div>
                        <div class="lg:col-span-2 bg-white p-6 rounded-2xl border border-gray-200 shadow-sm">
                            <h3 class="font-semibold text-gray-800 mb-4">Daftar Kategori</h3>
                            <table class="w-full text-left border-collapse">
                                <thead><tr class="bg-gray-50 text-gray-400 text-xs font-semibold uppercase border-b border-gray-100"><th class="py-3 px-4">Nama</th><th class="py-3 px-4">Slug</th><th class="py-3 px-4 text-center">Aksi</th></tr></thead>
                                <tbody class="divide-y divide-gray-100 text-sm">
                                    <?php foreach($categories_list as$cat): ?>
                                    <tr class="hover:bg-gray-50 transition">
                                        <td class="py-3 px-4 font-medium text-gray-900"><?= htmlspecialchars($cat['name']) ?></td>
                                        <td class="py-3 px-4 text-cyan-600 text-xs font-mono"><?= $cat['slug'] ?></td>
                                        <td class="py-3 px-4 text-center"><a href="admin.php?page=categories&delete_cat=<?= $cat['id'] ?>" class="text-red-500 hover:text-red-700 text-xs font-medium" onclick="return confirm('Hapus?')">Hapus</a></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                <?php elseif ($page === 'pages'): ?>
                    <?php if($msg): ?><div class='mb-4 p-4 bg-emerald-50 text-emerald-600 rounded-xl text-sm'><?= htmlspecialchars($msg) ?></div><?php endif; ?>
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <div class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm">
                            <h3 class="font-semibold text-gray-800 mb-4">Buat Halaman</h3>
                            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                                <div><label class="text-xs font-medium text-gray-500">Judul</label><input type="text" name="title" required class="w-full mt-1 p-2.5 border rounded-xl text-sm"></div>
                                <div><label class="text-xs font-medium text-gray-500">Gambar</label><input type="file" name="featured_image" accept="image/*" class="w-full mt-1 text-sm"></div>
                                <div><label class="text-xs font-medium text-gray-500">Konten</label><textarea name="content" class="editor"></textarea></div>
                                <div><label class="text-xs font-medium text-gray-700 font-semibold">HTML Kustom</label><textarea name="custom_html" class="w-full mt-1 p-3 border rounded-xl text-xs font-mono bg-gray-50" rows="3"></textarea></div>
                                <div><label class="text-xs font-medium text-gray-700 font-semibold">CSS Kustom</label><textarea name="custom_css" class="w-full mt-1 p-2 border rounded-xl text-xs font-mono bg-gray-50" rows="2"></textarea></div>
                                <div><label class="text-xs font-medium text-gray-700 font-semibold">JS Kustom</label><textarea name="custom_js" class="w-full mt-1 p-2 border rounded-xl text-xs font-mono bg-gray-50" rows="2"></textarea></div>
                                <button type="submit" class="w-full bg-cyan-600 text-white text-sm font-medium py-2.5 rounded-xl hover:bg-cyan-700 transition">Publikasikan</button>
                            </form>
                        </div>
                        <div class="lg:col-span-2 bg-white p-6 rounded-2xl border border-gray-200 shadow-sm">
                            <h3 class="font-semibold text-gray-800 mb-4">Daftar Halaman</h3>
                            <table class="w-full text-left border-collapse">
                                <thead><tr class="bg-gray-50 text-gray-400 text-xs font-semibold uppercase border-b border-gray-100"><th class="py-3 px-4">Judul</th><th class="py-3 px-4">Slug</th><th class="py-3 px-4 text-center">Aksi</th></tr></thead>
                                <tbody class="divide-y divide-gray-100 text-sm">
                                    <?php foreach($pages_list as$p): ?>
                                    <tr class="hover:bg-gray-50 transition">
                                        <td class="py-3 px-4 font-medium text-gray-900"><?= htmlspecialchars($p['title']) ?></td>
                                        <td class="py-3 px-4 text-cyan-600 text-xs font-mono">/<?= $p['slug'] ?></td>
                                        <td class="py-3 px-4 text-center space-x-2">
                                            <a href="https://rei-api.rajaemas.dev/rei/" target="_blank" class="text-cyan-600 hover:text-cyan-800 text-xs font-medium inline-flex items-center gap-0.5">
                                                <span class="material-icons text-xs">visibility</span> Lihat
                                            </a>
                                            <a href="edit-page.php?id=<?= $p['id'] ?>" class="text-amber-600 hover:text-amber-800 text-xs font-medium inline-flex items-center gap-0.5">
                                                <span class="material-icons text-xs">edit</span> Edit
                                            </a>
                                            <a href="admin.php?page=pages&delete_page=<?= $p['id'] ?>" class="text-red-500 hover:text-red-700 text-xs font-medium inline-flex items-center gap-0.5" onclick="return confirm('Hapus halaman ini?')">
                                                <span class="material-icons text-xs">delete</span> Hapus
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                <?php elseif ($page === 'menus'): ?>
                    <?php if($msg): ?><div class='mb-4 p-4 bg-emerald-50 text-emerald-600 rounded-xl text-sm'><?= htmlspecialchars($msg) ?></div><?php endif; ?>
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <div class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm">
                            <h3 class="font-semibold text-gray-800 mb-4">Tambah Menu</h3>
                            <form method="POST" class="space-y-4">
                                <div><label class="text-xs font-medium text-gray-500">Nama Menu</label><input type="text" name="title" required class="w-full mt-1 p-2.5 border rounded-xl text-sm"></div>
                                <div><label class="text-xs font-medium text-gray-500">URL</label><input type="text" name="url" value="#" required class="w-full mt-1 p-2.5 border rounded-xl text-sm"></div>
                                <div><label class="text-xs font-medium text-gray-500">Parent</label><select name="parent_id" class="w-full mt-1 p-2.5 border rounded-xl text-sm bg-white"><option value="0">-- Utama --</option><?php foreach($parent_menus as$pm): ?><option value="<?= $pm['id'] ?>"><?= htmlspecialchars($pm['title']) ?></option><?php endforeach; ?></select></div>
                                <div><label class="text-xs font-medium text-gray-500">Urutan</label><input type="number" name="sort_order" value="0" class="w-full mt-1 p-2.5 border rounded-xl text-sm"></div>
                                <button type="submit" class="w-full bg-cyan-600 text-white text-sm font-medium py-2.5 rounded-xl hover:bg-cyan-700 transition">Simpan</button>
                            </form>
                        </div>
                        <div class="lg:col-span-2 bg-white p-6 rounded-2xl border border-gray-200 shadow-sm">
                            <h3 class="font-semibold text-gray-800 mb-4">Daftar Menu</h3>
                            <table class="w-full text-left border-collapse">
                                <thead><tr class="bg-gray-50 text-gray-400 text-xs font-semibold uppercase border-b border-gray-100"><th class="py-3 px-4">Nama</th><th class="py-3 px-4">URL</th><th class="py-3 px-4 text-center">Aksi</th></tr></thead>
                                <tbody class="divide-y divide-gray-100 text-sm">
                                    <?php foreach($all_menus as$m): ?>
                                    <tr class="hover:bg-gray-50 transition">
                                        <td class="py-3 px-4 font-medium text-gray-900"><?= htmlspecialchars($m['title']) ?></td>
                                        <td class="py-3 px-4 text-gray-500 text-xs"><?= htmlspecialchars($m['url']) ?></td>
                                        <td class="py-3 px-4 text-center"><a href="admin.php?page=menus&delete_menu=<?= $m['id'] ?>" class="text-red-500 hover:text-red-700 text-xs font-medium" onclick="return confirm('Hapus?')">Hapus</a></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                <?php elseif ($page === 'slider'): ?>
                    <?php if($msg): ?><div class='mb-4 p-4 bg-emerald-50 text-emerald-600 rounded-xl text-sm'><?= htmlspecialchars($msg) ?></div><?php endif; ?>
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <div class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm">
                            <h3 class="font-semibold text-gray-800 mb-4">Pengaturan Slider</h3>
                            <form method="POST" class="space-y-3">
                                <input type="hidden" name="update_settings" value="1">
                                <div><label class="text-xs font-medium text-gray-500">Tinggi (px)</label><input type="number" name="height" value="<?= $s_set['height'] ?? 450 ?>" class="w-full mt-1 p-2 border rounded-xl text-sm"></div>
                                <div><label class="text-xs font-medium text-gray-500">Radius (px)</label><input type="number" name="border_radius" value="<?= $s_set['border_radius'] ?? 20 ?>" class="w-full mt-1 p-2 border rounded-xl text-sm"></div>
                                <div><label class="text-xs font-medium text-gray-500">Transisi (ms)</label><input type="number" name="transition_ms" value="<?= $s_set['transition_ms'] ?? 500 ?>" class="w-full mt-1 p-2 border rounded-xl text-sm"></div>
                                <div><label class="text-xs font-medium text-gray-500">Interval (ms)</label><input type="number" name="interval_ms" value="<?= $s_set['interval_ms'] ?? 4000 ?>" class="w-full mt-1 p-2 border rounded-xl text-sm"></div>
                                <button type="submit" class="w-full bg-cyan-600 text-white text-sm font-medium py-2.5 rounded-xl hover:bg-cyan-700 transition mt-2">Simpan</button>
                            </form>
                        </div>
                        <div class="lg:col-span-2 bg-white p-6 rounded-2xl border border-gray-200 shadow-sm">
                            <h3 class="font-semibold text-gray-800 mb-4">Upload Slider</h3>
                            <form method="POST" enctype="multipart/form-data" class="space-y-3 mb-6">
                                <input type="file" name="slider_image" required class="w-full text-sm">
                                <input type="text" name="title" placeholder="Judul" required class="w-full p-2.5 border rounded-xl text-sm">
                                <input type="text" name="caption" placeholder="Caption" class="w-full p-2.5 border rounded-xl text-sm">
                                <select name="show_on_page" class="w-full p-2.5 border rounded-xl text-sm bg-white"><option value="home">Hanya Beranda</option><option value="all">Semua Halaman</option></select>
                                <button type="submit" class="bg-gray-900 text-white text-sm font-medium px-4 py-2.5 rounded-xl hover:bg-gray-800 transition">Upload</button>
                            </form>
                        </div>
                    </div>

                <?php elseif ($page === 'theme'): ?>
                    <?php if($msg_setting): ?><div class='mb-4 p-4 bg-emerald-50 text-emerald-600 rounded-xl text-sm'><?= htmlspecialchars($msg_setting) ?></div><?php endif; ?>
                    <div class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm max-w-xl">
                        <h3 class="font-semibold text-gray-800 mb-4">Tema Glassmorphism</h3>
                        <form method="POST" class="space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div><label class="text-xs font-medium text-gray-500">Warna BG 1</label><input type="color" name="bg_color_1" value="<?= $theme['bg_color_1'] ?? '#4A00E0' ?>" class="w-full h-10 mt-1 cursor-pointer rounded-xl border p-1"></div>
                                <div><label class="text-xs font-medium text-gray-500">Warna BG 2</label><input type="color" name="bg_color_2" value="<?= $theme['bg_color_2'] ?? '#8E2DE2' ?>" class="w-full h-10 mt-1 cursor-pointer rounded-xl border p-1"></div>
                            </div>
                            <div><label class="text-xs font-medium text-gray-500">Warna Teks</label><input type="color" name="text_color" value="<?= $theme['text_color'] ?? '#ffffff' ?>" class="w-full h-10 mt-1 cursor-pointer rounded-xl border p-1"></div>
                            <div><label class="text-xs font-medium text-gray-500">Opacity</label><input type="range" name="glass_opacity" min="0" max="1" step="0.05" value="<?= $theme['glass_opacity'] ?? 0.15 ?>" class="w-full mt-2"></div>
                            <div><label class="text-xs font-medium text-gray-500">Blur</label><input type="range" name="blur_px" min="0" max="30" value="<?= $theme['blur_px'] ?? 15 ?>" class="w-full mt-2"></div>
                            <button type="submit" class="w-full bg-cyan-600 text-white text-sm font-medium py-2.5 rounded-xl hover:bg-cyan-700 transition">Simpan Tema</button>
                        </form>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script>
        // Filter daftar cabang berdasarkan nomor, nama, atau nomor WhatsApp.
        (function () {
            const input = document.getElementById('branchSearchInput');
            const type = document.getElementById('branchFilterType');
            const filterButton = document.getElementById('branchFilterButton');
            const resetButton = document.getElementById('branchResetButton');
            const result = document.getElementById('branchSearchResult');
            const cards = Array.from(document.querySelectorAll('.branch-card'));
            if (!input || !type || !filterButton || !resetButton || !result) return;

            const placeholders = {
                branch_number: 'Masukkan nomor cabang...',
                title: 'Masukkan nama cabang...',
                phone: 'Masukkan nomor WA cabang...'
            };
            const labels = {
                branch_number: 'nomor cabang',
                title: 'nama cabang',
                phone: 'nomor WA cabang'
            };

            function applyBranchFilter() {
                const keyword = input.value.trim().toLocaleLowerCase('id-ID');
                const field = 'branch' + (type.value === 'branch_number' ? 'Number' : type.value === 'title' ? 'Title' : 'Phone');
                let visible = 0;
                cards.forEach(function (card) {
                    const value = (card.dataset[field] || '').toLocaleLowerCase('id-ID');
                    const match = keyword === '' || value.includes(keyword);
                    card.classList.toggle('hidden', !match);
                    if (match) visible++;
                });
                result.textContent = keyword
                    ? 'Menampilkan ' + visible + ' dari ' + cards.length + ' cabang berdasarkan ' + labels[type.value] + ' “' + input.value.trim() + '”.'
                    : 'Menampilkan ' + visible + ' cabang. Pilih jenis filter dan masukkan kata kunci untuk menyaring daftar.';
                let empty = document.getElementById('branchNoResults');
                if (visible === 0 && cards.length > 0) {
                    if (!empty) {
                        empty = document.createElement('p');
                        empty.id = 'branchNoResults';
                        empty.className = 'col-span-full rounded-xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500';
                        empty.textContent = 'Tidak ada cabang yang cocok dengan filter tersebut.';
                        document.getElementById('branchCardsGrid')?.appendChild(empty);
                    }
                } else if (empty) {
                    empty.remove();
                }
            }

            type.addEventListener('change', function () {
                input.placeholder = placeholders[type.value] || 'Masukkan kata kunci...';
                if (input.value.trim()) applyBranchFilter();
            });
            filterButton.addEventListener('click', applyBranchFilter);
            input.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') { event.preventDefault(); applyBranchFilter(); }
            });
            resetButton.addEventListener('click', function () {
                input.value = '';
                type.value = 'branch_number';
                input.placeholder = placeholders.branch_number;
                applyBranchFilter();
                input.focus();
            });
            input.addEventListener('input', applyBranchFilter);
            applyBranchFilter();
        })();

        function toggleSubmenu(submenuId, arrowId) {
            const submenu = document.getElementById(submenuId);
            const arrow = document.getElementById(arrowId);
            if (submenu.classList.contains('hidden')) {
                submenu.classList.remove('hidden');
                arrow.classList.add('rotate-180');
            } else {
                submenu.classList.add('hidden');
                arrow.classList.remove('rotate-180');
            }
        }
    </script>
</body>
</html>