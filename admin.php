<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}
require 'config.php';

$page = $_GET['page'] ?? 'dashboard';
$msg = '';
$msg_setting = '';

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

// C. Proses Upload Slider (Dengan Pilihan Halaman Target)
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

// E. Proses Kelola Halaman (Pages) dengan HTML/CSS/JS Kustom & Try-Catch
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

// --- AMBIL DATA ---
$posts = $pdo->query("SELECT * FROM posts WHERE type='post' ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$pages_list = $pdo->query("SELECT * FROM posts WHERE type='page' ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$sliders = $pdo->query("SELECT * FROM sliders ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
$theme = $pdo->query("SELECT * FROM theme_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
$s_set = $pdo->query("SELECT * FROM slider_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
$parent_menus = $pdo->query("SELECT * FROM menus WHERE parent_id = 0 ORDER BY sort_order ASC")->fetchAll(PDO::FETCH_ASSOC);
$all_menus = $pdo->query("SELECT * FROM menus ORDER BY parent_id ASC, sort_order ASC")->fetchAll(PDO::FETCH_ASSOC);
$total_visitors = $pdo->query("SELECT total_views FROM visitor_stats WHERE id = 1")->fetchColumn() ?: 0;

$chart_data = $pdo->query("SELECT visit_date, views FROM daily_visitors ORDER BY visit_date ASC LIMIT 7")->fetchAll(PDO::FETCH_ASSOC);
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
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 4px; }
    </style>
</head>
<body class="text-gray-800 antialiased">

    <!-- Container Utama Menggantung (Floating Card Layout) -->
    <div class="flex h-screen overflow-hidden p-3 md:p-4 gap-4">
        
        <!-- Sidebar Menggantung -->
        <aside class="hidden md:flex flex-col w-64 bg-white rounded-3xl shadow-xl border border-gray-100/80 z-10 overflow-hidden transition-all duration-300">
            <!-- Header Logo Ungu Tua -->
            <div class="flex items-center justify-center h-20 px-4 bg-purple-900 shadow-inner m-3 rounded-2xl">
                <img src="logo-rei.png" alt="Logo Raja Emas Indonesia" class="max-h-8 w-auto object-contain filter brightness-150 contrast-125 drop-shadow-md">
            </div>
            
            <!-- Navigasi Menu (Nuansa Turkish / Teal) -->
            <nav class="flex-1 px-3 py-2 space-y-1 overflow-y-auto">
                <a href="admin.php?page=dashboard" class="flex items-center gap-3 px-4 py-3 text-sm font-medium rounded-2xl <?= $page === 'dashboard' ? 'bg-teal-700 text-white shadow-md shadow-teal-200' : 'text-gray-600 hover:bg-gray-50' ?> transition">
                    <span class="material-icons text-lg">dashboard</span> Dashboard
                </a>
                <a href="admin.php?page=posts" class="flex items-center gap-3 px-4 py-3 text-sm font-medium rounded-2xl <?= $page === 'posts' ? 'bg-teal-700 text-white shadow-md shadow-teal-200' : 'text-gray-600 hover:bg-gray-50' ?> transition">
                    <span class="material-icons text-lg">article</span> Post
                </a>
                <a href="admin.php?page=pages" class="flex items-center gap-3 px-4 py-3 text-sm font-medium rounded-2xl <?= $page === 'pages' ? 'bg-teal-700 text-white shadow-md shadow-teal-200' : 'text-gray-600 hover:bg-gray-50' ?> transition">
                    <span class="material-icons text-lg">description</span> Halaman
                </a>
                <a href="admin.php?page=menus" class="flex items-center gap-3 px-4 py-3 text-sm font-medium rounded-2xl <?= $page === 'menus' ? 'bg-teal-700 text-white shadow-md shadow-teal-200' : 'text-gray-600 hover:bg-gray-50' ?> transition">
                    <span class="material-icons text-lg">menu</span> Menu
                </a>
                <a href="admin.php?page=slider" class="flex items-center gap-3 px-4 py-3 text-sm font-medium rounded-2xl <?= $page === 'slider' ? 'bg-teal-700 text-white shadow-md shadow-teal-200' : 'text-gray-600 hover:bg-gray-50' ?> transition">
                    <span class="material-icons text-lg">collections</span> Slider
                </a>
                <a href="admin.php?page=theme" class="flex items-center gap-3 px-4 py-3 text-sm font-medium rounded-2xl <?= $page === 'theme' ? 'bg-teal-700 text-white shadow-md shadow-teal-200' : 'text-gray-600 hover:bg-gray-50' ?> transition">
                    <span class="material-icons text-lg">palette</span> Tema
                </a>
                <a href="index.php" target="_blank" class="flex items-center gap-3 px-4 py-3 text-sm font-medium rounded-2xl text-gray-600 hover:bg-gray-50 transition">
                    <span class="material-icons text-lg">open_in_new</span> Website
                </a>
            </nav>

            <!-- Tombol Logout -->
            <div class="p-3 border-t border-gray-100 bg-gray-50/50">
                <a href="logout.php" class="flex items-center gap-3 px-4 py-2.5 text-sm font-medium text-red-600 rounded-xl hover:bg-red-50 transition">
                    <span class="material-icons text-lg">logout</span> Logout
                </a>
            </div>
        </aside>

        <!-- Area Konten Utama Kanan (Floating Card Panel) -->
        <div class="flex flex-col flex-1 h-full bg-white rounded-3xl shadow-xl border border-gray-100/80 overflow-hidden">
            
            <header class="flex items-center justify-between h-20 px-8 bg-white border-b border-gray-100 z-10 shrink-0">
                <h1 class="text-xl font-bold text-gray-800 capitalize">Panel: <?= htmlspecialchars($page) ?></h1>
                <?php if($page === 'posts'): ?>
                    <a href="add.php" class="inline-flex items-center gap-2 bg-teal-700 hover:bg-teal-800 text-white text-sm font-medium px-4 py-2.5 rounded-xl shadow-md shadow-teal-100 transition">
                        <span class="material-icons text-sm">add</span> Artikel Baru
                    </a>
                <?php endif; ?>
            </header>

            <main class="flex-1 overflow-y-auto p-6 md:p-8 bg-gray-50/40">
                
                <!-- 1. DASHBOARD UTAMA -->
                <?php if ($page === 'dashboard'): ?>
                    <div class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="bg-gradient-to-br from-teal-700 to-teal-900 text-white p-6 rounded-2xl shadow-sm flex flex-col justify-between">
                                <div class="flex justify-between items-center">
                                    <span class="text-xs uppercase tracking-wider font-semibold opacity-80">Waktu Lokal Server</span>
                                    <span class="material-icons opacity-80">schedule</span>
                                </div>
                                <div class="my-4">
                                    <h2 id="live-clock" class="text-4xl font-bold tracking-tight">00:00:00</h2>
                                    <p id="live-date" class="text-teal-100 text-sm mt-1">Memuat tanggal...</p>
                                </div>
                                <div class="text-xs opacity-75">Zona Waktu Aktif: Sistem Lokal</div>
                            </div>

                            <div class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm flex flex-col justify-between">
                                <div class="flex justify-between items-center mb-2">
                                    <span id="cal-month-year" class="text-sm font-bold text-gray-800">Bulan Tahun</span>
                                    <span class="material-icons text-teal-700">event</span>
                                </div>
                                <div class="grid grid-cols-7 gap-1 text-center text-xs font-semibold text-gray-400 mb-1">
                                    <span>Min</span><span>Sen</span><span>Sel</span><span>Rab</span><span>Kam</span><span>Jum</span><span>Sab</span>
                                </div>
                                <div id="cal-days" class="grid grid-cols-7 gap-1 text-center text-sm"></div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                            <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm flex items-center justify-between">
                                <div>
                                    <p class="text-xs font-medium text-gray-400 uppercase">Total Postingan</p>
                                    <h4 class="text-2xl font-bold text-gray-900 mt-1"><?= count($posts) ?></h4>
                                </div>
                                <div class="w-10 h-10 bg-teal-50 text-teal-700 rounded-xl flex items-center justify-center">
                                    <span class="material-icons text-sm">article</span>
                                </div>
                            </div>

                            <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm flex items-center justify-between">
                                <div>
                                    <p class="text-xs font-medium text-gray-400 uppercase">Halaman Statis</p>
                                    <h4 class="text-2xl font-bold text-gray-900 mt-1"><?= count($pages_list) ?></h4>
                                </div>
                                <div class="w-10 h-10 bg-cyan-50 text-cyan-700 rounded-xl flex items-center justify-center">
                                    <span class="material-icons text-sm">description</span>
                                </div>
                            </div>

                            <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm flex items-center justify-between">
                                <div>
                                    <p class="text-xs font-medium text-gray-400 uppercase">Slider Aktif</p>
                                    <h4 class="text-2xl font-bold text-gray-900 mt-1"><?= count($sliders) ?></h4>
                                </div>
                                <div class="w-10 h-10 bg-emerald-50 text-emerald-700 rounded-xl flex items-center justify-center">
                                    <span class="material-icons text-sm">collections</span>
                                </div>
                            </div>

                            <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm flex items-center justify-between">
                                <div>
                                    <p class="text-xs font-medium text-gray-400 uppercase">Total Pengunjung</p>
                                    <h4 class="text-2xl font-bold text-gray-900 mt-1"><?= number_format($total_visitors) ?></h4>
                                </div>
                                <div class="w-10 h-10 bg-amber-50 text-amber-700 rounded-xl flex items-center justify-center">
                                    <span class="material-icons text-sm">visibility</span>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="font-semibold text-gray-800">Grafik Statistik Pengunjung (7 Hari Terakhir)</h3>
                                <span class="text-xs font-medium text-gray-400 bg-gray-50 px-2.5 py-1 rounded-lg">Real-time Analytics</span>
                            </div>
                            <div style="height: 280px; position: relative;">
                                <canvas id="visitorChart"></canvas>
                            </div>
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
                                let isToday = (d === today) ? 'bg-teal-700 text-white rounded-full font-bold' : 'text-gray-700 hover:bg-gray-100 rounded-full';
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
                                datasets: [{
                                    label: 'Jumlah Pengunjung',
                                    data: <?= json_encode($chart_values) ?>,
                                    borderColor: '#0f766e',
                                    backgroundColor: 'rgba(15, 118, 110, 0.08)',
                                    borderWidth: 3,
                                    fill: true,
                                    tension: 0.35,
                                    pointBackgroundColor: '#0f766e',
                                    pointRadius: 4
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: { legend: { display: false } },
                                scales: {
                                    y: { beginAtZero: true, grid: { color: '#f3f4f6' } },
                                    x: { grid: { display: false } }
                                }
                            }
                        });
                    </script>

                <!-- 2. KELOLA POST -->
                <?php elseif ($page === 'posts'): ?>
                    <div class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center">
                            <h3 class="font-semibold text-gray-800">Daftar Artikel (Blog Post)</h3>
                            <span class="text-xs font-medium text-gray-500 bg-gray-100 px-3 py-1 rounded-full"><?= count($posts) ?> Postingan</span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="bg-gray-50 text-gray-400 text-xs font-semibold uppercase border-b border-gray-100">
                                        <th class="py-3 px-6">ID</th>
                                        <th class="py-3 px-6">Judul Artikel</th>
                                        <th class="py-3 px-6">Tanggal</th>
                                        <th class="py-3 px-6 text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 text-sm">
                                    <?php foreach ($posts as$post): ?>
                                    <tr class="hover:bg-gray-50 transition">
                                        <td class="py-4 px-6 text-gray-500">#<?= $post['id'] ?></td>
                                        <td class="py-4 px-6 font-medium text-gray-900"><?= htmlspecialchars($post['title']) ?></td>
                                        <td class="py-4 px-6 text-gray-500"><?= date('d M Y, H:i', strtotime($post['created_at'])) ?></td>
                                        <td class="py-4 px-6 text-center space-x-2">
                                            <a href="edit.php?id=<?= $post['id'] ?>" class="p-1.5 rounded-lg bg-amber-50 text-amber-700 hover:bg-amber-100 inline-block"><span class="material-icons text-sm">edit</span></a>
                                            <a href="delete.php?id=<?= $post['id'] ?>" class="p-1.5 rounded-lg bg-red-50 text-red-700 hover:bg-red-100 inline-block" onclick="return confirm('Hapus?')"><span class="material-icons text-sm">delete</span></a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                <!-- 3. KELOLA HALAMAN (PAGES) -->
                <?php elseif ($page === 'pages'): ?>
                    <?php if($msg): ?>
                        <div class='mb-4 p-4 <?= strpos($msg, "Error") !== false ? "bg-red-50 text-red-700" : "bg-emerald-50 text-emerald-700" ?> rounded-xl text-sm'>
                            <?= htmlspecialchars($msg) ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <div class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm">
                            <h3 class="font-semibold text-gray-800 mb-4">Buat Halaman</h3>
                            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                                <div>
                                    <label class="text-xs font-medium text-gray-500">Judul Halaman</label>
                                    <input type="text" name="title" placeholder="Contoh: Landing Page" required class="w-full mt-1 p-2.5 border rounded-xl text-sm">
                                </div>
                                <div>
                                    <label class="text-xs font-medium text-gray-500">Gambar Utama / Banner (Opsional)</label>
                                    <input type="file" name="featured_image" accept="image/*" class="w-full mt-1 text-sm file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-teal-50 file:text-teal-700 hover:file:bg-teal-100">
                                </div>
                                <div>
                                    <label class="text-xs font-medium text-gray-500">Konten Standar / Editor (Opsional)</label>
                                    <textarea name="content" class="editor"></textarea>
                                </div>
                                <div>
                                    <label class="text-xs font-medium text-gray-700 font-semibold">Custom HTML Code</label>
                                    <textarea name="custom_html" placeholder="<div class='box'><h1>Halo</h1></div>" class="w-full mt-1 p-3 border rounded-xl text-xs font-mono bg-gray-50" rows="4"></textarea>
                                </div>
                                <div class="grid grid-cols-1 gap-3">
                                    <div>
                                        <label class="text-xs font-medium text-gray-700 font-semibold">Custom CSS</label>
                                        <textarea name="custom_css" placeholder=".box { background: #333; }" class="w-full mt-1 p-2 border rounded-xl text-xs font-mono bg-gray-50" rows="3"></textarea>
                                    </div>
                                    <div>
                                        <label class="text-xs font-medium text-gray-700 font-semibold">Custom JavaScript</label>
                                        <textarea name="custom_js" placeholder="console.log('OK');" class="w-full mt-1 p-2 border rounded-xl text-xs font-mono bg-gray-50" rows="3"></textarea>
                                    </div>
                                </div>
                                <button type="submit" class="w-full bg-teal-700 text-white text-sm font-medium py-2.5 rounded-xl hover:bg-teal-800 transition">Publikasikan Halaman</button>
                            </form>
                        </div>

                        <div class="lg:col-span-2 bg-white p-6 rounded-2xl border border-gray-200 shadow-sm">
                            <h3 class="font-semibold text-gray-800 mb-4">Daftar Halaman Statis</h3>
                            <div class="overflow-x-auto">
                                <table class="w-full text-left border-collapse">
                                    <thead>
                                        <tr class="bg-gray-50 text-gray-400 text-xs font-semibold uppercase border-b border-gray-100">
                                            <th class="py-3 px-4">Judul Halaman</th>
                                            <th class="py-3 px-4">URL Slug</th>
                                            <th class="py-3 px-4 text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 text-sm">
                                        <?php foreach($pages_list as$p): ?>
                                        <tr class="hover:bg-gray-50 transition">
                                            <td class="py-3 px-4 font-medium text-gray-900"><?= htmlspecialchars($p['title']) ?></td>
                                            <td class="py-3 px-4 text-teal-700 text-xs font-mono">index.php?page=<?= $p['slug'] ?></td>
                                            <td class="py-3 px-4 text-center space-x-2">
                                                <a href="index.php?page=<?= $p['slug'] ?>" target="_blank" class="text-gray-500 hover:text-gray-700 text-xs font-medium">Lihat</a>
                                                <a href="admin.php?page=pages&delete_page=<?= $p['id'] ?>" class="text-red-500 hover:text-red-700 text-xs font-medium" onclick="return confirm('Hapus halaman ini?')">Hapus</a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php if(count($pages_list) === 0): ?>
                                            <tr><td colspan="3" class="py-6 text-center text-gray-400">Belum ada halaman statis.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                <!-- 4. KELOLA MENU -->
                <?php elseif ($page === 'menus'): ?>
                    <?php if($msg) echo "<div class='mb-4 p-4 bg-emerald-50 text-emerald-700 rounded-xl text-sm'>$msg</div>"; ?>
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <div class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm">
                            <h3 class="font-semibold text-gray-800 mb-4">Tambah Menu / Sub-Menu</h3>
                            <form method="POST" class="space-y-4">
                                <div>
                                    <label class="text-xs font-medium text-gray-500">Nama Menu</label>
                                    <input type="text" name="title" placeholder="Contoh: Tentang Kami" required class="w-full mt-1 p-2.5 border rounded-xl text-sm">
                                </div>
                                <div>
                                    <label class="text-xs font-medium text-gray-500">URL / Tautan</label>
                                    <input type="text" name="url" placeholder="index.php?page=..." value="#" required class="w-full mt-1 p-2.5 border rounded-xl text-sm">
                                </div>
                                <div>
                                    <label class="text-xs font-medium text-gray-500">Induk Menu (Parent)</label>
                                    <select name="parent_id" class="w-full mt-1 p-2.5 border rounded-xl text-sm bg-white">
                                        <option value="0">-- Menu Utama (Parent) --</option>
                                        <?php foreach($parent_menus as$pm): ?>
                                            <option value="<?= $pm['id'] ?>"><?= htmlspecialchars($pm['title']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-xs font-medium text-gray-500">Urutan</label>
                                    <input type="number" name="sort_order" value="0" class="w-full mt-1 p-2.5 border rounded-xl text-sm">
                                </div>
                                <button type="submit" class="w-full bg-teal-700 text-white text-sm font-medium py-2.5 rounded-xl hover:bg-teal-800 transition">Simpan Menu</button>
                            </form>
                        </div>
                        <div class="lg:col-span-2 bg-white p-6 rounded-2xl border border-gray-200 shadow-sm">
                            <h3 class="font-semibold text-gray-800 mb-4">Struktur Navigasi Website</h3>
                            <div class="overflow-x-auto">
                                <table class="w-full text-left border-collapse">
                                    <thead>
                                        <tr class="bg-gray-50 text-gray-400 text-xs font-semibold uppercase border-b border-gray-100">
                                            <th class="py-3 px-4">Nama Menu</th>
                                            <th class="py-3 px-4">Tipe</th>
                                            <th class="py-3 px-4">URL</th>
                                            <th class="py-3 px-4 text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 text-sm">
                                        <?php foreach($all_menus as$m): ?>
                                        <tr class="hover:bg-gray-50 transition">
                                            <td class="py-3 px-4 font-medium text-gray-900"><?= $m['parent_id'] > 0 ? '↳ &nbsp; ' : '' ?><?= htmlspecialchars($m['title']) ?></td>
                                            <td class="py-3 px-4"><span class="text-xs px-2 py-1 rounded-full <?= $m['parent_id'] == 0 ? 'bg-teal-50 text-teal-700' : 'bg-gray-100 text-gray-600' ?>"><?= $m['parent_id'] == 0 ? 'Utama' : 'Sub' ?></span></td>
                                            <td class="py-3 px-4 text-gray-500 text-xs"><?= htmlspecialchars($m['url']) ?></td>
                                            <td class="py-3 px-4 text-center"><a href="admin.php?page=menus&delete_menu=<?= $m['id'] ?>" class="text-red-500 hover:text-red-700 text-xs" onclick="return confirm('Hapus?')">Hapus</a></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                <!-- 5. KELOLA SLIDER -->
                <?php elseif ($page === 'slider'): ?>
                    <?php if($msg) echo "<div class='mb-4 p-4 bg-emerald-50 text-emerald-700 rounded-xl text-sm'>$msg</div>"; ?>
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <div class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm">
                            <h3 class="font-semibold text-gray-800 mb-4">Pengaturan Slider & Indikator</h3>
                            <form method="POST" class="space-y-3">
                                <input type="hidden" name="update_settings" value="1">
                                <div><label class="text-xs font-medium text-gray-500">Tinggi (px)</label><input type="number" name="height" value="<?= $s_set['height'] ?? 450 ?>" class="w-full mt-1 p-2 border rounded-xl text-sm"></div>
                                <div><label class="text-xs font-medium text-gray-500">Rounded (px)</label><input type="number" name="border_radius" value="<?= $s_set['border_radius'] ?? 20 ?>" class="w-full mt-1 p-2 border rounded-xl text-sm"></div>
                                <div><label class="text-xs font-medium text-gray-500">Transisi (ms)</label><input type="number" name="transition_ms" value="<?= $s_set['transition_ms'] ?? 500 ?>" class="w-full mt-1 p-2 border rounded-xl text-sm"></div>
                                <div><label class="text-xs font-medium text-gray-500">Durasi Tampil (ms)</label><input type="number" name="interval_ms" value="<?= $s_set['interval_ms'] ?? 4000 ?>" class="w-full mt-1 p-2 border rounded-xl text-sm"></div>
                                
                                <hr class="my-2 border-gray-100">
                                
                                <div>
                                    <label class="text-xs font-medium text-gray-500">Jenis Indikator</label>
                                    <select name="indicator_mode" class="w-full mt-1 p-2 border rounded-xl text-sm bg-white">
                                        <option value="default" <?= ($s_set['indicator_mode'] ?? '') === 'default' ? 'selected' : '' ?>>Standar (Lingkaran)</option>
                                        <option value="minimal" <?= ($s_set['indicator_mode'] ?? '') === 'minimal' ? 'selected' : '' ?>>Minimalis (Titik Kecil)</option>
                                        <option value="pill" <?= ($s_set['indicator_mode'] ?? '') === 'pill' ? 'selected' : '' ?>>Garis / Pil (Bar)</option>
                                        <option value="square" <?= ($s_set['indicator_mode'] ?? '') === 'square' ? 'selected' : '' ?>>Kotak (Square)</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-xs font-medium text-gray-500">Ukuran Indikator (px)</label>
                                    <input type="number" name="indicator_size" value="<?= $s_set['indicator_size'] ?? 12 ?>" class="w-full mt-1 p-2 border rounded-xl text-sm">
                                </div>
                                <div class="flex items-center gap-2 pt-2">
                                    <input type="checkbox" name="indicator_show" value="1" id="ind_show" <?= ($s_set['indicator_show'] ?? 1) == 1 ? 'checked' : '' ?> class="w-4 h-4 text-teal-700 rounded">
                                    <label for="ind_show" class="text-xs font-medium text-gray-700">Tampilkan Indikator Slider</label>
                                </div>

                                <button type="submit" class="w-full bg-teal-700 text-white text-sm font-medium py-2.5 rounded-xl hover:bg-teal-800 transition mt-2">Simpan Pengaturan</button>
                            </form>
                        </div>
                        <div class="lg:col-span-2 bg-white p-6 rounded-2xl border border-gray-200 shadow-sm">
                            <h3 class="font-semibold text-gray-800 mb-4">Upload Gambar Slider</h3>
                            <form method="POST" enctype="multipart/form-data" class="space-y-3 mb-6">
                                <input type="file" name="slider_image" required class="w-full text-sm file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-teal-50 file:text-teal-700 hover:file:bg-teal-100">
                                <input type="text" name="title" placeholder="Judul Slider" required class="w-full p-2.5 border rounded-xl text-sm">
                                <input type="text" name="caption" placeholder="Deskripsi Singkat (Opsional)" class="w-full p-2.5 border rounded-xl text-sm">
                                
                                <div>
                                    <label class="text-xs font-medium text-gray-500">Tampilkan di Halaman:</label>
                                    <select name="show_on_page" class="w-full mt-1 p-2.5 border rounded-xl text-sm bg-white">
                                        <option value="home">Hanya di Beranda (Home)</option>
                                        <option value="all">Semua Halaman</option>
                                        <?php 
                                        $all_pages_opt =$pdo->query("SELECT title, slug FROM posts WHERE type='page'")->fetchAll(PDO::FETCH_ASSOC);
                                        foreach($all_pages_opt as$apo): 
                                        ?>
                                            <option value="<?= $apo['slug'] ?>">Halaman: <?= htmlspecialchars($apo['title']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <button type="submit" class="bg-gray-900 text-white text-sm font-medium px-4 py-2.5 rounded-xl hover:bg-gray-800 transition">Upload Gambar</button>
                            </form>
                            <div class="border-t pt-4">
                                <h4 class="text-xs font-semibold text-gray-400 uppercase mb-3">Daftar Slider</h4>
                                <div class="space-y-2">
                                    <?php foreach($sliders as$s): ?>
                                    <div class="flex items-center justify-between p-3 border rounded-xl">
                                        <div class="flex items-center gap-3">
                                            <img src="<?= $s['image_path'] ?>" class="w-16 h-10 object-cover rounded-lg">
                                            <div>
                                                <span class="text-sm font-medium text-gray-800 block"><?= htmlspecialchars($s['title']) ?></span>
                                                <span class="text-[11px] text-teal-700 bg-teal-50 px-2 py-0.5 rounded-md">Target: <?= htmlspecialchars($s['show_on_page'] ?? 'home') ?></span>
                                            </div>
                                        </div>
                                        <a href="admin.php?page=slider&delete_slider=<?= $s['id'] ?>" class="text-red-500 hover:text-red-700 text-sm" onclick="return confirm('Hapus?')">Hapus</a>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                <!-- 6. EDIT TEMA -->
                <?php elseif ($page === 'theme'): ?>
                    <?php if($msg_setting) echo "<div class='mb-4 p-4 bg-emerald-50 text-emerald-700 rounded-xl text-sm'>$msg_setting</div>"; ?>
                    <div class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm max-w-xl">
                        <h3 class="font-semibold text-gray-800 mb-4">Pengaturan Tema Glassmorphism</h3>
                        <form method="POST" class="space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div><label class="text-xs font-medium text-gray-500">Warna BG 1</label><input type="color" name="bg_color_1" value="<?= $theme['bg_color_1'] ?? '#4A00E0' ?>" class="w-full h-10 mt-1 cursor-pointer rounded-xl border p-1"></div>
                                <div><label class="text-xs font-medium text-gray-500">Warna BG 2</label><input type="color" name="bg_color_2" value="<?= $theme['bg_color_2'] ?? '#8E2DE2' ?>" class="w-full h-10 mt-1 cursor-pointer rounded-xl border p-1"></div>
                            </div>
                            <div><label class="text-xs font-medium text-gray-500">Warna Teks</label><input type="color" name="text_color" value="<?= $theme['text_color'] ?? '#ffffff' ?>" class="w-full h-10 mt-1 cursor-pointer rounded-xl border p-1"></div>
                            <div><label class="text-xs font-medium text-gray-500">Glass Opacity (0.0 - 1.0)</label><input type="range" name="glass_opacity" min="0" max="1" step="0.05" value="<?= $theme['glass_opacity'] ?? 0.15 ?>" class="w-full mt-2"></div>
                            <div><label class="text-xs font-medium text-gray-500">Efek Blur (px)</label><input type="range" name="blur_px" min="0" max="30" value="<?= $theme['blur_px'] ?? 15 ?>" class="w-full mt-2"></div>
                            <button type="submit" class="w-full bg-teal-700 text-white text-sm font-medium py-2.5 rounded-xl hover:bg-teal-800 transition">Simpan Tema</button>
                        </form>
                    </div>
                <?php endif; ?>

            </main>
        </div>
    </div>

</body>
</html>