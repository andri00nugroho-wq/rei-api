<?php
declare(strict_types=1);

session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

require __DIR__ . '/config.php'; // Harus menyediakan koneksi PDO dalam variabel $pdo.

if (empty($_SESSION['add_user_csrf'])) {
    $_SESSION['add_user_csrf'] = bin2hex(random_bytes(32));
}

$message = '';
$messageType = 'error';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = (string)($_POST['csrf_token'] ?? '');
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $passwordConfirm = (string)($_POST['password_confirm'] ?? '');

    if (!hash_equals((string)$_SESSION['add_user_csrf'], $csrf)) {
        $message = 'Token keamanan tidak valid. Muat ulang halaman dan coba lagi.';
    } elseif ($username === '' || $password === '' || $passwordConfirm === '') {
        $message = 'Semua kolom wajib diisi.';
    } elseif (mb_strlen($username) < 3 || mb_strlen($username) > 50) {
        $message = 'Username harus terdiri dari 3–50 karakter.';
    } elseif (!preg_match('/^[A-Za-z0-9._-]+$/', $username)) {
        $message = 'Username hanya boleh berisi huruf, angka, titik, garis bawah, dan tanda hubung.';
    } elseif (strlen($password) < 8) {
        $message = 'Password minimal 8 karakter.';
    } elseif ($password !== $passwordConfirm) {
        $message = 'Konfirmasi password tidak cocok.';
    } else {
        try {
            $check = $pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
            $check->execute([$username]);

            if ($check->fetchColumn()) {
                $message = 'Username sudah digunakan. Silakan pilih username lain.';
            } else {
                // Password disimpan dalam bentuk hash, bukan teks biasa.
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $insert = $pdo->prepare('INSERT INTO users (username, password) VALUES (?, ?)');
                $insert->execute([$username, $passwordHash]);

                $message = 'User berhasil ditambahkan.';
                $messageType = 'success';
                $_SESSION['add_user_csrf'] = bin2hex(random_bytes(32));
                $_POST = [];
            }
        } catch (PDOException $e) {
            // Detail error database tidak ditampilkan kepada pengguna.
            error_log('Gagal menambahkan user: ' . $e->getMessage());
            $message = 'User gagal ditambahkan. Periksa struktur tabel users dan konfigurasi database.';
        }
    }
}

function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tambah User - REI CMS</title>
    <link rel="icon" type="image/png" href="logo-rei-2.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        body { font-family: Inter, sans-serif; }
        .input-field { width: 100%; border: 1px solid #e5e7eb; border-radius: 12px; padding: 12px 14px; outline: none; transition: .2s; }
        .input-field:focus { border-color: #0891b2; box-shadow: 0 0 0 3px rgba(8,145,178,.12); }
    </style>
</head>
<body class="min-h-screen bg-gray-100 text-gray-800">
    <div class="min-h-screen md:flex">
        <aside class="md:flex md:w-72 md:flex-col bg-white border-r border-gray-200">
            <div class="m-4 flex h-20 items-center justify-center rounded-2xl bg-gradient-to-br from-purple-700 to-black px-4">
                <img src="logo-rei.png" alt="Logo REI" class="max-h-14 max-w-full object-contain" onerror="this.style.display='none'">
                <span class="text-white font-bold tracking-wide">REI CMS</span>
            </div>
            <nav class="px-4 pb-4 space-y-2">
                <a href="admin.php?page=dashboard" class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium text-gray-600 hover:bg-gray-100">
                    <span class="material-icons">dashboard</span> Dashboard
                </a>
                <a href="add-user.php" class="flex items-center gap-3 rounded-xl bg-cyan-600 px-4 py-3 text-sm font-semibold text-white">
                    <span class="material-icons">person_add</span> Tambah User
                </a>
                <a href="admin.php?page=branches" class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium text-gray-600 hover:bg-gray-100">
                    <span class="material-icons">storefront</span> Cabang Toko
                </a>
                <a href="index.php" target="_blank" rel="noopener" class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium text-gray-600 hover:bg-gray-100">
                    <span class="material-icons">open_in_new</span> Website
                </a>
                <a href="logout.php" class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium text-red-600 hover:bg-red-50">
                    <span class="material-icons">logout</span> Logout
                </a>
            </nav>
        </aside>

        <main class="min-w-0 flex-1 p-4 md:p-8 lg:p-10">
            <header class="mb-8 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wider text-cyan-700">Manajemen akun</p>
                    <h1 class="mt-1 text-2xl font-extrabold tracking-tight text-gray-900 md:text-3xl">Tambah User</h1>
                    <p class="mt-2 text-sm text-gray-500">Buat akun baru untuk mengakses sistem REI CMS.</p>
                </div>
                <a href="admin.php?page=dashboard" class="inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50">
                    <span class="material-icons text-base">arrow_back</span> Kembali ke Admin
                </a>
            </header>

            <div class="grid max-w-5xl grid-cols-1 gap-6 lg:grid-cols-5">
                <section class="lg:col-span-3 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm md:p-8">
                    <div class="mb-6 flex items-center gap-3">
                        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-cyan-50 text-cyan-700">
                            <span class="material-icons">person_add</span>
                        </div>
                        <div>
                            <h2 class="font-bold text-gray-900">Informasi akun</h2>
                            <p class="text-sm text-gray-500">Isi data login pengguna baru.</p>
                        </div>
                    </div>

                    <?php if ($message !== ''): ?>
                        <div role="status" class="mb-5 rounded-xl border px-4 py-3 text-sm <?= $messageType === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-red-200 bg-red-50 text-red-700' ?>">
                            <?= e($message) ?>
                        </div>
                    <?php endif; ?>

                    <form method="post" autocomplete="off" class="space-y-5">
                        <input type="hidden" name="csrf_token" value="<?= e((string)$_SESSION['add_user_csrf']) ?>">

                        <div>
                            <label for="username" class="mb-2 block text-sm font-semibold text-gray-700">Username</label>
                            <input id="username" name="username" type="text" required minlength="3" maxlength="50"
                                pattern="[A-Za-z0-9._-]+" autocomplete="off"
                                value="<?= e((string)($_POST['username'] ?? '')) ?>"
                                placeholder="Contoh: admin_rei" class="input-field">
                            <p class="mt-2 text-xs text-gray-500">3–50 karakter; gunakan huruf, angka, titik, _ atau -.</p>
                        </div>

                        <div>
                            <label for="password" class="mb-2 block text-sm font-semibold text-gray-700">Password</label>
                            <div class="relative">
                                <input id="password" name="password" type="password" required minlength="8"
                                    autocomplete="new-password" placeholder="Minimal 8 karakter" class="input-field pr-12">
                                <button type="button" onclick="togglePassword('password', this)" aria-label="Tampilkan password"
                                    class="absolute inset-y-0 right-0 flex items-center px-4 text-gray-500 hover:text-cyan-700">
                                    <span class="material-icons text-xl">visibility</span>
                                </button>
                            </div>
                        </div>

                        <div>
                            <label for="password_confirm" class="mb-2 block text-sm font-semibold text-gray-700">Konfirmasi Password</label>
                            <div class="relative">
                                <input id="password_confirm" name="password_confirm" type="password" required minlength="8"
                                    autocomplete="new-password" placeholder="Ulangi password" class="input-field pr-12">
                                <button type="button" onclick="togglePassword('password_confirm', this)" aria-label="Tampilkan konfirmasi password"
                                    class="absolute inset-y-0 right-0 flex items-center px-4 text-gray-500 hover:text-cyan-700">
                                    <span class="material-icons text-xl">visibility</span>
                                </button>
                            </div>
                        </div>

                        <div class="flex flex-col gap-3 border-t border-gray-100 pt-5 sm:flex-row">
                            <button type="submit" class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl bg-cyan-600 px-5 py-3 font-semibold text-white shadow-sm transition hover:bg-cyan-700 focus:outline-none focus:ring-4 focus:ring-cyan-100">
                                <span class="material-icons text-lg">person_add</span> Simpan User
                            </button>
                            <button type="reset" class="rounded-xl border border-gray-200 px-5 py-3 font-semibold text-gray-600 hover:bg-gray-50">
                                Reset Form
                            </button>
                        </div>
                    </form>
                </section>

                <aside class="space-y-5 lg:col-span-2">
                    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                        <div class="mb-3 flex items-center gap-2 text-gray-900">
                            <span class="material-icons text-cyan-700">shield</span>
                            <h2 class="font-bold">Keamanan akun</h2>
                        </div>
                        <ul class="space-y-3 text-sm leading-6 text-gray-600">
                            <li class="flex gap-2"><span class="material-icons text-base text-emerald-600">check_circle</span><span>Password disimpan sebagai hash, bukan teks biasa.</span></li>
                            <li class="flex gap-2"><span class="material-icons text-base text-emerald-600">check_circle</span><span>Username harus unik dan tidak boleh sama dengan akun lain.</span></li>
                            <li class="flex gap-2"><span class="material-icons text-base text-emerald-600">check_circle</span><span>Halaman hanya dapat dibuka setelah admin login.</span></li>
                        </ul>
                    </div>
                    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5">
                        <div class="flex gap-3">
                            <span class="material-icons text-amber-700">info</span>
                            <div>
                                <h3 class="font-semibold text-amber-900">Catatan login</h3>
                                <p class="mt-1 text-sm leading-6 text-amber-800">Kode login perlu memverifikasi password menggunakan <code class="rounded bg-amber-100 px-1">password_verify()</code> karena password baru disimpan dengan <code class="rounded bg-amber-100 px-1">password_hash()</code>.</p>
                            </div>
                        </div>
                    </div>
                </aside>
            </div>
            <p class="mt-8 text-center text-xs text-gray-400">REI CMS · Manajemen Pengguna</p>
        </main>
    </div>

    <script>
        function togglePassword(id, button) {
            const input = document.getElementById(id);
            const icon = button.querySelector('.material-icons');
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            icon.textContent = show ? 'visibility_off' : 'visibility';
            button.setAttribute('aria-label', show ? 'Sembunyikan password' : 'Tampilkan password');
        }
    </script>
</body>
</html>
