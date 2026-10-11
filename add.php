<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}
require 'config.php';

$msg = '';

// Ambil daftar kategori untuk dropdown
try {
    $categories = $pdo->query("SELECT * FROM post_categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $categories = [];
}

// Proses Simpan Artikel Baru
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
    $content = $_POST['content'];
    $category_id = !empty($_POST['category_id']) ? $_POST['category_id'] : null;
    
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
        $stmt = $pdo->prepare("INSERT INTO posts (title, slug, content, featured_image, category_id, type) VALUES (?, ?, ?, ?, ?, 'post')");
        $stmt->execute([$title, $slug, $content, $featured_image, $category_id]);
        header("Location: admin.php?page=posts");
        exit;
    } catch (PDOException $e) {
        $msg = "Error Database: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Artikel Baru - REI-CMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.8.2/tinymce.min.js"></script>
    <script>
        tinymce.init({ 
            selector: 'textarea.editor', 
            height: 350, 
            menubar: false, 
            plugins: 'lists link code image', 
            toolbar: 'undo redo | bold italic | alignleft aligncenter alignright | bullist numlist | link image' 
        });
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f3f4f6; }
    </style>
</head>
<body class="text-gray-800 antialiased">

    <div class="flex h-screen overflow-hidden p-3 md:p-4 gap-4">
        
        <!-- Sidebar -->
        <aside class="hidden md:flex flex-col w-64 bg-white rounded-3xl shadow-xl border border-gray-100/80 z-10 overflow-hidden">
            <div class="flex items-center justify-center h-20 px-4 bg-purple-900 shadow-inner m-3 rounded-2xl">
                <img src="logo-rei.png" alt="Logo" class="max-h-8 w-auto object-contain filter brightness-150 contrast-125 drop-shadow-md">
            </div>
            
            <nav class="flex-1 px-3 py-2 space-y-1 overflow-y-auto">
                <a href="admin.php?page=dashboard" class="flex items-center gap-3 px-4 py-3 text-sm font-medium rounded-2xl text-gray-600 hover:bg-gray-50 transition">
                    <span class="material-icons text-lg">dashboard</span> Dashboard
                </a>
                <a href="admin.php?page=posts" class="flex items-center gap-3 px-4 py-3 text-sm font-medium rounded-2xl bg-teal-700 text-white shadow-md shadow-teal-200 transition">
                    <span class="material-icons text-lg">article</span> Post
                </a>
                <a href="admin.php?page=categories" class="flex items-center gap-3 px-4 py-3 text-sm font-medium rounded-2xl text-gray-600 hover:bg-gray-50 transition">
                    <span class="material-icons text-lg">category</span> Kategori
                </a>
                <a href="admin.php?page=pages" class="flex items-center gap-3 px-4 py-3 text-sm font-medium rounded-2xl text-gray-600 hover:bg-gray-50 transition">
                    <span class="material-icons text-lg">description</span> Halaman
                </a>
                <a href="admin.php?page=menus" class="flex items-center gap-3 px-4 py-3 text-sm font-medium rounded-2xl text-gray-600 hover:bg-gray-50 transition">
                    <span class="material-icons text-lg">menu</span> Menu
                </a>
                <a href="admin.php?page=slider" class="flex items-center gap-3 px-4 py-3 text-sm font-medium rounded-2xl text-gray-600 hover:bg-gray-50 transition">
                    <span class="material-icons text-lg">collections</span> Slider
                </a>
                <a href="admin.php?page=theme" class="flex items-center gap-3 px-4 py-3 text-sm font-medium rounded-2xl text-gray-600 hover:bg-gray-50 transition">
                    <span class="material-icons text-lg">palette</span> Tema
                </a>
            </nav>

            <div class="p-3 border-t border-gray-100 bg-gray-50/50">
                <a href="logout.php" class="flex items-center gap-3 px-4 py-2.5 text-sm font-medium text-red-600 rounded-xl hover:bg-red-50 transition">
                    <span class="material-icons text-lg">logout</span> Logout
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="flex flex-col flex-1 h-full bg-white rounded-3xl shadow-xl border border-gray-100/80 overflow-hidden">
            
            <header class="flex items-center justify-between h-20 px-8 bg-white border-b border-gray-100 z-10 shrink-0">
                <div class="flex items-center gap-3">
                    <a href="admin.php?page=posts" class="text-gray-400 hover:text-gray-700 flex items-center">
                        <span class="material-icons">arrow_back</span>
                    </a>
                    <h1 class="text-xl font-bold text-gray-800">Tambah Artikel Baru</h1>
                </div>
            </header>

            <main class="flex-1 overflow-y-auto p-6 md:p-8 bg-gray-50/40">
                <?php if($msg): ?>
                    <div class="mb-6 p-4 bg-red-50 text-red-700 rounded-2xl text-sm border border-red-100">
                        <?= htmlspecialchars($msg) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" class="max-w-4xl space-y-6">
                    <div class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm space-y-4">
                        <div>
                            <label class="block text-xs font-semibold uppercase text-gray-500 mb-1">Judul Artikel</label>
                            <input type="text" name="title" placeholder="Masukkan judul artikel..." required class="w-full p-3 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-teal-700">
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold uppercase text-gray-500 mb-1">Kategori Artikel</label>
                                <select name="category_id" class="w-full p-3 border border-gray-200 rounded-xl text-sm bg-white focus:outline-none focus:border-teal-700">
                                    <option value="">-- Pilih Kategori --</option>
                                    <?php foreach($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold uppercase text-gray-500 mb-1">Gambar Utama (Banner)</label>
                                <input type="file" name="featured_image" accept="image/*" class="w-full text-sm file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-teal-50 file:text-teal-700 hover:file:bg-teal-100 border border-gray-200 rounded-xl">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase text-gray-500 mb-1">Konten Artikel</label>
                            <textarea name="content" class="editor"></textarea>
                        </div>

                        <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                            <a href="admin.php?page=posts" class="px-5 py-2.5 rounded-xl border border-gray-200 text-sm font-medium text-gray-600 hover:bg-gray-50 transition">Batal</a>
                            <button type="submit" class="px-6 py-2.5 bg-teal-700 hover:bg-teal-800 text-white text-sm font-medium rounded-xl shadow-md shadow-teal-100 transition">Publikasikan Artikel</button>
                        </div>
                    </div>
                </form>
            </main>
        </div>
    </div>

</body>
</html>