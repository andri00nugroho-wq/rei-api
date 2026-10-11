<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) { header("Location: login.php"); exit; }
require 'config.php';

$id = $_GET['id'] ?? null;
$stmt = $pdo->prepare("SELECT * FROM posts WHERE id = :id");
$stmt->execute(['id' => $id]);
$post = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$post) die("Artikel tidak ditemukan!");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $updateStmt = $pdo->prepare("UPDATE posts SET title = :title, content = :content WHERE id = :id");
    $updateStmt->execute(['title' => $_POST['title'], 'content' => $_POST['content'], 'id' => $id]);
    header("Location: admin.php"); exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <title>Edit Artikel - REI-CMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.8.2/tinymce.min.js"></script>
    <script>tinymce.init({ selector: 'textarea', height: 400, menubar: false, plugins: 'lists link', toolbar: 'undo redo | bold italic | alignleft aligncenter | bullist numlist' });</script>
</head>
<body style="padding: 20px; background: #f5f5f5;">
    <div class="container card-panel">
        <h4>Edit Artikel</h4>
        <form method="POST">
            <label>Judul Artikel</label>
            <input type="text" name="title" value="<?= htmlspecialchars($post['title']) ?>" required>
            <label>Konten</label>
            <textarea name="content"><?= htmlspecialchars($post['content']) ?></textarea><br>
            <button type="submit" class="btn amber darken-2">Update</button>
            <a href="admin.php" class="btn grey">Batal</a>
        </form>
    </div>
</body>
</html>
