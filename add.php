<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}
require 'config.php';

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
    $content = $_POST['content'];
    
    // Tangkap Upload Gambar Utama
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

    $stmt = $pdo->prepare("INSERT INTO posts (title, slug, content, featured_image, type) VALUES (?, ?, ?, ?, 'post')");
    $stmt->execute([$title, $slug, $content, $featured_image]);
    header("Location: admin.php?page=posts");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tambah Artikel - REI-CMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.8.2/tinymce.min.js"></script>
    <script>tinymce.init({ selector: 'textarea.editor', height: 350, menubar: false, plugins: 'lists link code', toolbar: 'undo redo | bold italic | alignleft aligncenter | bullist numlist' });</script>
</head>
<body class="bg-gray-50 text-gray-800 p-8">
    <div class="max-w-2xl mx-auto bg-white p-6 rounded-2xl border border-gray-200 shadow-sm">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-bold text-gray-900">Tambah Artikel Baru</h2>
            <a href="admin.php?page=posts" class="text-sm text-gray-500 hover:text-gray-700">&larr; Kembali</a>
        </div>
        <form method="POST" enctype="multipart/form-data" class="space-y-4">
            <div>
                <label class="text-xs font-medium text-gray-500">Judul Artikel</label>
                <input type="text" name="title" required class="w-full mt-1 p-2.5 border rounded-xl text-sm">
            </div>
            <div>
                <label class="text-xs font-medium text-gray-500">Gambar Utama (Featured Image)</label>
                <input type="file" name="featured_image" accept="image/*" class="w-full mt-1 text-sm file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
            </div>
            <div>
                <label class="text-xs font-medium text-gray-500">Konten Artikel</label>
                <textarea name="content" class="editor"></textarea>
            </div>
            <button type="submit" class="w-full bg-indigo-600 text-white text-sm font-medium py-2.5 rounded-xl hover:bg-indigo-700 transition">Simpan Artikel</button>
        </form>
    </div>
</body>
</html>