<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}
require 'config.php';

// Proses Update Pengaturan Tampilan Slider
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    $stmt = $pdo->prepare("UPDATE slider_settings SET height=?, border_radius=?, transition_ms=?, interval_ms=? WHERE id=1");
    $stmt->execute([$_POST['height'], $_POST['border_radius'], $_POST['transition_ms'], $_POST['interval_ms']]);
    $msg_setting = "<span class='green-text'>Pengaturan tampilan slider berhasil disimpan!</span>";
}

// Proses Upload Gambar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['slider_image'])) {
    $title = $_POST['title'];
    $caption = $_POST['caption'];
    
    $target_dir = "uploads/";
    if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
    
    $file_name = time() . "_" . preg_replace("/[^a-zA-Z0-9.]/", "_", basename($_FILES["slider_image"]["name"])); 
    $target_file = $target_dir . $file_name;
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    
    if(in_array($imageFileType, ['jpg', 'png', 'jpeg', 'webp'])) {
        if (move_uploaded_file($_FILES["slider_image"]["tmp_name"], $target_file)) {
            $pdo->prepare("INSERT INTO sliders (image_path, title, caption) VALUES (?, ?, ?)")->execute([$target_file, $title, $caption]);
            $msg = "<span class='green-text'>Slider berhasil ditambahkan!</span>";
        }
    } else {
        $msg = "<span class='red-text'>Format file tidak didukung!</span>";
    }
}

// Proses Hapus Gambar
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $slider = $pdo->prepare("SELECT image_path FROM sliders WHERE id = ?");
    $slider->execute([$id]);
    $slider = $slider->fetch();
    
    if ($slider) {
        if(file_exists($slider['image_path'])) unlink($slider['image_path']);
        $pdo->prepare("DELETE FROM sliders WHERE id = ?")->execute([$id]);
    }
    header("Location: slider.php");
    exit;
}

// Ambil data dari database
$sliders = $pdo->query("SELECT * FROM sliders ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$set = $pdo->query("SELECT * FROM slider_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <title>Pengaturan Slider - REI-CMS</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css">
    <style>body { padding: 20px; background: #f5f5f5; }</style>
</head>
<body>
    <div class="container">
        <h4>Kelola Slider Website</h4>
        <a href="admin.php" class="btn grey mb-3"><i class="material-icons left">arrow_back</i>Dashboard</a>
        
        <!-- Panel Pengaturan Tampilan (Baru) -->
        <div class="card-panel" style="margin-top: 20px;">
            <h5>Pengaturan Tampilan & Animasi</h5>
            <?php if(isset($msg_setting)) echo "<p><b>$msg_setting</b></p>"; ?>
            <form method="POST">
                <input type="hidden" name="update_settings" value="1">
                <div class="row">
                    <div class="col s12 m3 input-field">
                        <input type="number" name="height" value="<?= $set['height'] ?>" required>
                        <label>Tinggi Slider (px)</label>
                    </div>
                    <div class="col s12 m3 input-field">
                        <input type="number" name="border_radius" value="<?= $set['border_radius'] ?>" required>
                        <label>Lengkungan / Rounded (px)</label>
                    </div>
                    <div class="col s12 m3 input-field">
                        <input type="number" name="transition_ms" value="<?= $set['transition_ms'] ?>" required>
                        <label>Kecepatan Transisi (ms)</label>
                        <span class="helper-text">Standar: 500 (0.5 detik)</span>
                    </div>
                    <div class="col s12 m3 input-field">
                        <input type="number" name="interval_ms" value="<?= $set['interval_ms'] ?>" required>
                        <label>Durasi Tampil (ms)</label>
                        <span class="helper-text">Standar: 4000 (4 detik)</span>
                    </div>
                </div>
                <button type="submit" class="btn blue">Simpan Pengaturan</button>
            </form>
        </div>

        <div class="row" style="margin-top:20px;">
            <!-- Form Upload -->
            <div class="col s12 m4">
                <div class="card-panel">
                    <h5>Tambah Gambar</h5>
                    <?php if(isset($msg)) echo "<p><b>$msg</b></p>"; ?>
                    <form method="POST" enctype="multipart/form-data">
                        <div class="file-field input-field">
                            <div class="btn pink accent-3">
                                <span>Pilih</span><input type="file" name="slider_image" required accept="image/*">
                            </div>
                            <div class="file-path-wrapper"><input class="file-path validate" type="text" placeholder="Upload Gambar"></div>
                        </div>
                        <div class="input-field">
                            <input type="text" name="title" required><label>Judul Slider</label>
                        </div>
                        <div class="input-field">
                            <input type="text" name="caption"><label>Deskripsi (Opsional)</label>
                        </div>
                        <button type="submit" class="btn green w100">Upload</button>
                    </form>
                </div>
            </div>
            
            <!-- Daftar Slider -->
            <div class="col s12 m8">
                <div class="card-panel">
                    <h5>Daftar Gambar Aktif</h5>
                    <table class="highlight responsive-table">
                        <thead><tr><th>Gambar</th><th>Judul</th><th>Aksi</th></tr></thead>
                        <tbody>
                            <?php foreach ($sliders as $s): ?>
                            <tr>
                                <td><img src="<?= $s['image_path'] ?>" width="120" style="border-radius: 5px;"></td>
                                <td><?= htmlspecialchars($s['title']) ?></td>
                                <td>
                                    <a href="?delete=<?= $s['id'] ?>" class="btn-small red" onclick="return confirm('Hapus?')"><i class="material-icons">delete</i></a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
</body>
</html>
