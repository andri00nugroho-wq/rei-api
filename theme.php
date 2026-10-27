<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) { header("Location: login.php"); exit; }
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare("UPDATE theme_settings SET bg_color_1=?, bg_color_2=?, glass_opacity=?, blur_px=?, text_color=? WHERE id=1");
    $stmt->execute([$_POST['bg_color_1'], $_POST['bg_color_2'], $_POST['glass_opacity'], $_POST['blur_px'], $_POST['text_color']]);
}
$theme = $pdo->query("SELECT * FROM theme_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <title>Edit Tema - REI-CMS</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css">
    <style>
        body { padding: 20px; background: #f5f5f5; }
        .theme-preview { padding: 30px; border-radius: 15px; background: linear-gradient(135deg, <?= $theme['bg_color_1'] ?>, <?= $theme['bg_color_2'] ?>); }
        .glass-box { background: rgba(255, 255, 255, <?= $theme['glass_opacity'] ?>); backdrop-filter: blur(<?= $theme['blur_px'] ?>px); border: 1px solid rgba(255,255,255,0.3); padding: 20px; border-radius: 10px; color: <?= $theme['text_color'] ?>; }
    </style>
</head>
<body>
    <div class="container">
        <h4>Edit Tema Glassmorphism</h4>
        <a href="admin.php" class="btn grey mb-3">Kembali ke Dashboard</a>
        <div class="row" style="margin-top:20px;">
            <div class="col s12 m6">
                <div class="card-panel">
                    <form method="POST">
                        <label>Warna Background 1</label><input type="color" name="bg_color_1" value="<?= $theme['bg_color_1'] ?>" style="width:100%;height:40px;">
                        <label>Warna Background 2</label><input type="color" name="bg_color_2" value="<?= $theme['bg_color_2'] ?>" style="width:100%;height:40px;">
                        <label>Warna Teks</label><input type="color" name="text_color" value="<?= $theme['text_color'] ?>" style="width:100%;height:40px;">
                        <label>Glass Opacity (0.00 - 1.00)</label><p class="range-field"><input type="range" name="glass_opacity" min="0" max="1" step="0.05" value="<?= $theme['glass_opacity'] ?>"></p>
                        <label>Efek Blur (px)</label><p class="range-field"><input type="range" name="blur_px" min="0" max="30" value="<?= $theme['blur_px'] ?>"></p>
                        <button type="submit" class="btn blue">Simpan Tema</button>
                    </form>
                </div>
            </div>
            <div class="col s12 m6">
                <div class="theme-preview"><div class="glass-box"><h5>Preview Glassmorphism</h5><p>Beginilah tampilan komponen website pengunjung.</p></div></div>
            </div>
        </div>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
</body>
</html>
