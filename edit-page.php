<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}
require 'config.php';
require_once __DIR__ . '/site-pages.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: admin.php?page=pages");
    exit;
}

$msg = '';

$stmt_current = $pdo->prepare("SELECT slug FROM posts WHERE id = ? AND type = 'page'");
$stmt_current->execute([$id]);
$current_slug = (string) $stmt_current->fetchColumn();
// Halaman website bawaan (Beranda, Tentang Kami, dst.) slug-nya tetap agar tidak terputus dari website.
$site_page_key = rei_site_page_key($current_slug);

// Proses Simpan Perubahan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $slug = $site_page_key !== null
        ? $current_slug
        : strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
    if ($site_page_key === null && rei_site_page_key($slug) !== null) {
        // Judul baru menghasilkan slug milik halaman website; pertahankan slug lama.
        $slug = $current_slug;
    }
    $content = $_POST['content'] ?? '';
    $custom_html = $_POST['custom_html'] ?? '';
    $custom_css = $_POST['custom_css'] ?? '';
    $custom_js = $_POST['custom_js'] ?? '';

    // Ambil data lama untuk gambar
    $stmt_old = $pdo->prepare("SELECT featured_image FROM posts WHERE id = ?");
    $stmt_old->execute([$id]);
    $old_data = $stmt_old->fetch(PDO::FETCH_ASSOC);
    $featured_image = $old_data['featured_image'] ?? null;
    if (!empty($_POST['remove_featured_image'])) {
        $featured_image = null;
    }

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

    $stmt = $pdo->prepare("UPDATE posts SET title = ?, slug = ?, content = ?, custom_html = ?, custom_css = ?, custom_js = ?, featured_image = ? WHERE id = ? AND type = 'page'");
    $stmt->execute([$title, $slug, $content, $custom_html, $custom_css, $custom_js, $featured_image, $id]);
    
    $msg = "Halaman berhasil diperbarui!";
}

$stmt = $pdo->prepare("SELECT * FROM posts WHERE id = ? AND type = 'page'");
$stmt->execute([$id]);
$page_data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$page_data) {
    header("Location: admin.php?page=pages");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Halaman - REI-CMS</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="logo-rei-2.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.8.2/tinymce.min.js"></script>
    <script>
        tinymce.init({ selector: 'textarea.editor', height: 250, menubar: false, plugins: 'lists link code', toolbar: 'undo redo | bold italic | alignleft aligncenter | bullist numlist' });
    </script>
    <style> body { font-family: 'Inter', sans-serif; background-color: #f3f4f6; } </style>
</head>
<body class="text-gray-800 antialiased p-6 md:p-10">
    <div class="max-w-3xl mx-auto bg-white p-8 rounded-3xl shadow-xl border border-gray-100">
        <div class="flex justify-between items-center mb-6 border-b pb-4">
            <h1 class="text-xl font-bold text-gray-800">Edit Halaman: <?= htmlspecialchars($page_data['title']) ?></h1>
            <a href="index.php?page=<?= rawurlencode($site_page_key ?? $page_data['slug']) ?>" target="_blank" class="text-sm text-cyan-600 hover:underline flex items-center gap-1">
                <span class="material-icons text-sm">visibility</span> Lihat
            </a>
            <a href="admin.php?page=pages" class="text-sm text-cyan-600 hover:underline flex items-center gap-1">
                <span class="material-icons text-sm">arrow_back</span> Kembali
            </a>
        </div>

        <?php if($msg): ?>
            <div class="mb-4 p-4 bg-emerald-50 text-emerald-600 rounded-xl text-sm font-medium"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>

        <?php if ($site_page_key !== null): ?>
            <div class="mb-4 p-4 bg-cyan-50 text-cyan-800 rounded-xl text-sm">
                Ini halaman website <b>/<?= htmlspecialchars($site_page_key) ?></b>.
                <b>Judul</b> tampil di menu navigasi, <b>Konten Utama</b> menjadi teks pembuka di bagian atas halaman,
                <b>Gambar</b> tampil sebagai banner, dan <b>HTML Kustom</b> (jika diisi) menggantikan seluruh isi halaman bawaan.
                Kosongkan Konten Utama untuk memakai teks bawaan.
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="space-y-4">
            <div>
                <label class="text-xs font-medium text-gray-500">Judul Halaman</label>
                <input type="text" name="title" value="<?= htmlspecialchars($page_data['title']) ?>" required class="w-full mt-1 p-2.5 border rounded-xl text-sm">
            </div>
            <div>
                <label class="text-xs font-medium text-gray-500">Gambar Unggulan (Featured Image)</label>
                <?php if(!empty($page_data['featured_image'])): ?>
                    <div class="mb-2"><img src="<?= htmlspecialchars($page_data['featured_image']) ?>" class="h-20 rounded-lg object-cover"></div>
                    <label class="inline-flex items-center gap-2 text-xs text-gray-600"><input type="checkbox" name="remove_featured_image" value="1"> Hapus gambar</label>
                <?php endif; ?>
                <input type="file" name="featured_image" accept="image/*" class="w-full mt-1 text-sm">
            </div>
            <div>
                <label class="text-xs font-medium text-gray-500">Konten Utama</label>
                <textarea name="content" class="editor"><?= htmlspecialchars($page_data['content'] ?? '') ?></textarea>
            </div>
            <div>
                <label class="text-xs font-medium text-gray-700 font-semibold">HTML Kustom</label>
                <textarea name="custom_html" class="w-full mt-1 p-3 border rounded-xl text-xs font-mono bg-gray-50" rows="3"><?= htmlspecialchars($page_data['custom_html'] ?? '') ?></textarea>
            </div>
            <div>
                <label class="text-xs font-medium text-gray-700 font-semibold">CSS Kustom</label>
                <textarea name="custom_css" class="w-full mt-1 p-2 border rounded-xl text-xs font-mono bg-gray-50" rows="2"><?= htmlspecialchars($page_data['custom_css'] ?? '') ?></textarea>
            </div>
            <div>
                <label class="text-xs font-medium text-gray-700 font-semibold">JS Kustom</label>
                <textarea name="custom_js" class="w-full mt-1 p-2 border rounded-xl text-xs font-mono bg-gray-50" rows="2"><?= htmlspecialchars($page_data['custom_js'] ?? '') ?></textarea>
            </div>
            <button type="submit" class="w-full bg-cyan-600 text-white text-sm font-medium py-3 rounded-xl hover:bg-cyan-700 transition shadow-md">Simpan Perubahan</button>
        </form>
    </div>
</body>
</html>
