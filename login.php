<?php
session_start();
require 'config.php';

if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: admin.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username");
    $stmt->execute(['username' => $_POST['username']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && password_verify($_POST['password'], $user['password'])) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['username'] = $user['username'];
        header("Location: admin.php");
        exit;
    } else {
        $error = "Username atau password salah!";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - REI-CMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }

        /* Efek perspektif 3D untuk pembungkus logo */
        .coin-container {
            perspective: 1000px;
        }

        /* Animasi Koin Berputar (Kiri-Kanan / Sumbu Y) */
        @keyframes coin-flip {
            0% {
                transform: rotateY(0deg);
            }
            50% {
                transform: rotateY(180deg);
            }
            100% {
                transform: rotateY(360deg);
            }
        }

        .animate-coin {
            animation: coin-flip 3s infinite linear; /* Durasi 3 detik per putaran */
            transform-style: preserve-3d;
        }
    </style>
</head>
<body class="flex justify-center items-center h-screen m-0 bg-gray-100">
    <div class="bg-white p-8 rounded-3xl shadow-xl border border-gray-100 w-full max-w-sm">
        <!-- Logo dengan animasi koin berputar kiri-kanan -->
        <div class="flex items-center justify-center mb-6 coin-container">
            <img src="logo-rei-2.png" alt="Logo" class="max-h-24 w-auto object-contain animate-coin">
        </div>

        <?php if ($error): ?>
            <div class="mb-4 p-3 bg-red-50 text-red-600 rounded-xl text-xs font-medium text-center"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            <div>
                <input type="text" name="username" placeholder="Username" required class="w-full px-4 py-3 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-purple-500/20 focus:border-purple-600 transition">
            </div>
            <div>
                <input type="password" name="password" placeholder="Password" required class="w-full px-4 py-3 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-purple-500/20 focus:border-purple-600 transition">
            </div>
            <button type="submit" class="w-full py-3 px-4 bg-gradient-to-r from-purple-600 to-black text-white text-sm font-medium rounded-xl hover:opacity-95 transition shadow-md">Masuk</button>
        </form>
        <p class="text-center mt-6 text-xs text-gray-500"><a href="index.php" class="hover:text-purple-600 transition">← Kembali ke Web</a></p>
    </div>
</body>
</html>