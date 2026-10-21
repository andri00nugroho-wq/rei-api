<?php
declare(strict_types=1);


const USERS_TABLE  = '';
const API_TOKEN    = '';
const LOGO_URL     = 'https://rajaemasindonesia.co.id/wp-content/uploads/2025/01/2-Photoroom.png';
const MAX_ATTEMPTS = 5;
const LOCK_SECONDS = 300;

const API_CONFIG_PATH = __DIR__ . '/api-config.php';

const IT_WA_NUMBER = '6285739164347';
const IT_EMAIL     = 'tech@rajaemasindonesia.co.id';

session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])]);
session_start();
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
$csrf = $_SESSION['csrf'];
$error = '';
$dbError = false;

function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }

function dbConnect(): PDO
{
    static $pdoCache = null;
    if ($pdoCache instanceof PDO) { return $pdoCache; }

    $path = '';
    foreach ([__DIR__ . '/config.php', dirname(__DIR__) . '/config.php'] as $c) {
        if (is_file($c)) { $path = $c; break; }
    }
    if ($path === '') { throw new RuntimeException('config.php tidak ditemukan.'); }

    require $path;
    if (!isset($pdo) || !($pdo instanceof PDO)) { throw new RuntimeException('Variabel $pdo tidak ditemukan di config.php'); }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $pdoCache = $pdo;
}

function usersTable(PDO $pdo): string
{
    if (USERS_TABLE !== '') {
        if (!preg_match('/^[A-Za-z0-9_]+$/', USERS_TABLE)) { throw new RuntimeException('USERS_TABLE tidak valid.'); }
        return USERS_TABLE;
    }

    $rows = $pdo->query(
        "SELECT table_name AS t FROM information_schema.columns
         WHERE table_schema = DATABASE() AND column_name IN ('username','password')
         GROUP BY table_name HAVING COUNT(DISTINCT column_name) = 2"
    )->fetchAll();
    $names = array_map(static fn($r) => (string)$r['t'], $rows);

    foreach (['users', 'user', 'admin', 'admins', 'tbl_users', 'tbl_user', 'tb_user'] as $pref) {
        foreach ($names as $n) { if (strcasecmp($n, $pref) === 0) { return $n; } }
    }
    if ($names) {
        error_log('[REI DOC] Tabel user terdeteksi otomatis: ' . $names[0] . ' (kandidat: ' . implode(', ', $names) . ')');
        return $names[0];
    }
    throw new RuntimeException('Tabel dengan kolom username & password tidak ditemukan.');
}

// Mendukung bcrypt/argon (disarankan) serta format lama md5/sha1/sha256/teks biasa
function verifyPassword(string $plain, string $stored): bool
{
    $stored = trim($stored);
    if ($stored === '') { return false; }
    if (preg_match('/^\$(2[aby]|argon2)/', $stored)) { return password_verify($plain, $stored); }
    if (preg_match('/^[a-f0-9]{32}$/i', $stored)) { return hash_equals(strtolower($stored), md5($plain)); }
    if (preg_match('/^[a-f0-9]{40}$/i', $stored)) { return hash_equals(strtolower($stored), sha1($plain)); }
    if (preg_match('/^[a-f0-9]{64}$/i', $stored)) { return hash_equals(strtolower($stored), hash('sha256', $plain)); }
    return hash_equals($stored, $plain);
}

function itLink(string $reason): string
{
    $msg = "Halo Tim IT Raja Emas Indonesia,\nSaya mengalami kendala pada Dokumentasi API REI.\n\nKendala: " . $reason;
    if (IT_WA_NUMBER !== '') { return 'https://wa.me/' . preg_replace('/\D+/', '', IT_WA_NUMBER) . '?text=' . rawurlencode($msg); }
    if (IT_EMAIL !== '') { return 'mailto:' . IT_EMAIL . '?subject=' . rawurlencode('Kendala Dokumentasi API REI') . '&body=' . rawurlencode($msg); }
    return '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $okCsrf = hash_equals($csrf, (string)($_POST['csrf'] ?? ''));
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'logout' && $okCsrf) {
        $_SESSION = [];
        session_destroy();
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
        exit;
    }

    if ($action === 'login') {
        $lockUntil = (int)($_SESSION['lock_until'] ?? 0);
        if ($lockUntil > time()) {
            $error = 'Terlalu banyak percobaan. Coba lagi dalam ' . ceil(($lockUntil - time()) / 60) . ' menit.';
        } elseif (!$okCsrf) {
            $error = 'Sesi formulir kedaluwarsa. Muat ulang halaman lalu coba lagi.';
        } else {
            $u = trim((string)($_POST['username'] ?? ''));
            $p = (string)($_POST['password'] ?? '');
            $loginOk = false;
            $loginName = $u;
            $row = false;
            try {
                $db = dbConnect();
                $tbl = usersTable($db);

                $st = $db->prepare('SELECT `id`, `username`, `password` FROM `' . $tbl . '` WHERE `username` = :u LIMIT 1');
                $st->execute(['u' => $u]);
                $row = $st->fetch();

                if ($row !== false && $u !== '' && verifyPassword($p, (string)$row['password'])) {
                    $loginOk = true;
                    $loginName = (string)$row['username'];
                }
            } catch (Throwable $t) {
                error_log('[REI DOC][LOGIN] ' . $t->getMessage());
                $dbError = true;
            }

            if ($dbError) {
                $error = 'Tidak dapat terhubung ke database. Silakan coba lagi atau hubungi tim IT.';
            } elseif ($loginOk) {
                session_regenerate_id(true);
                $_SESSION['auth'] = true;
                $_SESSION['user'] = $loginName;
                $_SESSION['uid'] = (int)($row['id'] ?? 0);
                $_SESSION['attempts'] = 0;
                $_SESSION['csrf'] = bin2hex(random_bytes(32));
                header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
                exit;
            }
            if ($dbError) {
            } elseif (($_SESSION['attempts'] = (int)($_SESSION['attempts'] ?? 0) + 1) >= MAX_ATTEMPTS) {
                $_SESSION['lock_until'] = time() + LOCK_SECONDS;
                $_SESSION['attempts'] = 0;
                $error = 'Terlalu banyak percobaan. Akun dikunci sementara.';
            } else {
                $error = 'Username atau password salah.';
            }
        }
    }

    if ($action === 'getkey') {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        if (empty($_SESSION['auth'])) {
            http_response_code(401);
            echo json_encode(['ok' => false, 'message' => 'Sesi berakhir. Silakan masuk lagi.']);
            exit;
        }
        if (!$okCsrf) {
            http_response_code(419);
            echo json_encode(['ok' => false, 'message' => 'Sesi formulir kedaluwarsa. Muat ulang halaman lalu coba lagi.']);
            exit;
        }

        $key = '';
        if (is_file(API_CONFIG_PATH)) {
            try { require_once API_CONFIG_PATH; } catch (Throwable $t) { error_log('[REI DOC] ' . $t->getMessage()); }
        }
        if (defined('REI_API_TOKEN') && is_string(REI_API_TOKEN) && REI_API_TOKEN !== '') {
            $key = REI_API_TOKEN;
        } elseif (API_TOKEN !== '') {
            $key = API_TOKEN;
        }

        if ($key === '') {
            http_response_code(404);
            echo json_encode(['ok' => false, 'message' => 'API key belum dikonfigurasi di server (api-config.php tidak ditemukan atau REI_API_TOKEN kosong).']);
            exit;
        }

        error_log('[REI DOC] API key dilihat oleh ' . (string)($_SESSION['user'] ?? '?') . ' dari ' . (string)($_SERVER['REMOTE_ADDR'] ?? ''));
        echo json_encode(['ok' => true, 'key' => $key]);
        exit;
    }
}

$authed = !empty($_SESSION['auth']);
?><!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= $authed ? 'Dokumentasi API REI' : 'Masuk · Dokumentasi API REI' ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config={theme:{extend:{fontFamily:{sans:['"Plus Jakarta Sans"','system-ui','sans-serif']},
colors:{brand:{50:'#f8f3f8',100:'#efe3ee',200:'#dcc3da',300:'#c29bbf',400:'#a26b9d',500:'#7d4678',600:'#612d5e',700:'#4f2150',800:'#421a40',900:'#2e102d',950:'#1c0a1b'},
gold:{300:'#e8cf84',400:'#dcb757',500:'#c9a23a',600:'#a98428'}}}}}
</script>
<style>
 body{font-family:'Plus Jakarta Sans',system-ui,sans-serif}
 .bg-pattern{background-image:radial-gradient(circle at 20% 20%,rgba(201,162,58,.18),transparent 40%),radial-gradient(circle at 85% 80%,rgba(201,162,58,.12),transparent 45%),linear-gradient(145deg,#2e102d,#421a40 55%,#612d5e)}
 .bg-grid{background-image:linear-gradient(rgba(255,255,255,.05) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.05) 1px,transparent 1px);background-size:36px 36px}
</style>
</head>

<?php if (!$authed): ?>
<body class="min-h-screen bg-brand-50 text-slate-800 antialiased">
<div class="grid min-h-screen lg:grid-cols-2">

  <section class="bg-pattern relative hidden overflow-hidden lg:flex lg:flex-col lg:justify-between p-12 text-white">
    <div class="bg-grid absolute inset-0 opacity-60"></div>
    <div class="relative flex items-center gap-3">
      <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white p-2 shadow-lg shadow-black/20">
        <img src="<?= e(LOGO_URL) ?>" alt="Logo REI" class="max-h-full max-w-full object-contain" onerror="this.replaceWith(Object.assign(document.createElement('b'),{textContent:'REI',className:'text-brand-800 font-extrabold'}))">
      </span>
      <div><p class="text-lg font-bold leading-tight">Raja Emas Indonesia</p><p class="text-sm text-brand-200">Portal dokumentasi API</p></div>
    </div>
    <div class="relative max-w-md">
      <h1 class="text-4xl font-extrabold leading-tight">Dokumentasi API cabang yang jelas dan bisa langsung dicoba.</h1>
      <p class="mt-4 text-brand-100/90">Baca panduan endpoint, salin contoh kode, lalu jalankan request langsung dari browser untuk memastikan semua data cabang terambil lengkap.</p>
      <ul class="mt-8 space-y-3 text-sm text-brand-100">
        <li class="flex items-center gap-3"><span class="h-2 w-2 rounded-full bg-gold-400"></span>Contoh kode cURL, PHP, JavaScript, dan Python</li>
        <li class="flex items-center gap-3"><span class="h-2 w-2 rounded-full bg-gold-400"></span>Penguji API dengan pagination otomatis</li>
        <li class="flex items-center gap-3"><span class="h-2 w-2 rounded-full bg-gold-400"></span>Akses hanya untuk pengguna terdaftar</li>
      </ul>
    </div>
    <p class="relative text-xs text-brand-200">© <?= date('Y') ?> Raja Emas Indonesia</p>
  </section>

  <main class="flex items-center justify-center px-5 py-10 sm:px-10">
    <div class="w-full max-w-md">
      <div class="mb-8 flex items-center gap-3 lg:hidden">
        <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-brand-800 p-2"><img src="<?= e(LOGO_URL) ?>" alt="Logo REI" class="max-h-full max-w-full object-contain"></span>
        <p class="font-bold text-brand-800">Raja Emas Indonesia</p>
      </div>
      <h2 class="text-3xl font-extrabold text-brand-900">Masuk</h2>
      <p class="mt-2 text-sm text-slate-500">Masuk untuk membuka dokumentasi dan penguji API cabang.</p>

      <?php if ($error): ?>
      <div role="alert" class="mt-6 flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        <svg class="mt-0.5 h-5 w-5 flex-none" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-4a1 1 0 00-1 1v3a1 1 0 002 0V7a1 1 0 00-1-1zm0 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
        <span><?= e($error) ?></span>
      </div>
      <?php if ($dbError && ($itl = itLink('Login gagal: tidak dapat terhubung ke database.')) !== ''): ?>
      <a href="<?= e($itl) ?>" target="_blank" rel="noopener" class="mt-3 inline-flex items-center justify-center rounded-xl bg-gold-500 px-4 py-2.5 text-sm font-bold text-brand-950 shadow hover:bg-gold-400">Hubungi Tim IT Raja Emas Indonesia</a>
      <?php endif; ?>
      <?php endif; ?>

      <form method="post" class="mt-8 space-y-5" autocomplete="off">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="action" value="login">
        <div>
          <label for="username" class="mb-1.5 block text-sm font-semibold text-slate-700">Username</label>
          <input id="username" name="username" required autofocus autocomplete="username" value="<?= e((string)($_POST['username'] ?? '')) ?>"
            class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm shadow-sm outline-none transition focus:border-brand-600 focus:ring-4 focus:ring-brand-200">
        </div>
        <div>
          <label for="password" class="mb-1.5 block text-sm font-semibold text-slate-700">Password</label>
          <div class="relative">
            <input id="password" name="password" type="password" required autocomplete="current-password"
              class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 pr-20 text-sm shadow-sm outline-none transition focus:border-brand-600 focus:ring-4 focus:ring-brand-200">
            <button type="button" id="togglePw" class="absolute inset-y-0 right-0 px-4 text-xs font-semibold text-brand-700 hover:text-brand-900">Tampilkan</button>
          </div>
        </div>
        <button type="submit" class="w-full rounded-xl bg-brand-800 px-4 py-3 text-sm font-bold text-white shadow-lg shadow-brand-800/25 transition hover:bg-brand-900 focus:outline-none focus:ring-4 focus:ring-brand-300">Masuk</button>
      </form>
      <p class="mt-8 text-center text-xs text-slate-400">Akses terbatas. Aktivitas login dapat dipantau.</p>
    </div>
  </main>
</div>
<script>
document.getElementById('togglePw').addEventListener('click',function(){var i=document.getElementById('password'),s=i.type==='password';i.type=s?'text':'password';this.textContent=s?'Sembunyikan':'Tampilkan'});
</script>
</body>

<?php else: ?>
<body class="bg-white text-slate-800 antialiased">

<header class="fixed inset-x-0 top-0 z-40 h-14 border-b border-brand-900 bg-brand-800 text-white">
  <div class="flex h-full items-center justify-between gap-3 px-4">
    <div class="flex min-w-0 items-center gap-3">
      <button id="menuBtn" class="rounded-lg p-2 hover:bg-white/10 lg:hidden" aria-label="Buka menu"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg></button>
      <span class="flex h-9 w-9 flex-none items-center justify-center rounded-lg bg-white p-1"><img src="<?= e(LOGO_URL) ?>" alt="Logo REI" class="max-h-full max-w-full object-contain"></span>
      <div class="min-w-0 leading-tight"><p class="truncate text-sm font-bold">Dokumentasi API REI</p><p class="hidden text-[11px] text-brand-200 sm:block">Raja Emas Indonesia · v1</p></div>
    </div>
    <div class="relative hidden max-w-md flex-1 md:block">
      <input id="docSearch" placeholder="Cari di dokumentasi…" class="w-full rounded-lg border border-white/15 bg-white/10 px-3 py-1.5 text-sm text-white placeholder-brand-200 outline-none focus:bg-white/15 focus:ring-2 focus:ring-gold-400">
    </div>
    <div class="flex items-center gap-2">
      <span class="hidden items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs sm:flex"><span class="h-2 w-2 rounded-full bg-emerald-400"></span><?= e((string)$_SESSION['user']) ?></span>
      <form method="post"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="logout">
        <button class="rounded-lg bg-gold-500 px-3 py-1.5 text-xs font-bold text-brand-950 hover:bg-gold-400">Keluar</button></form>
    </div>
  </div>
</header>

<div id="overlay" class="fixed inset-0 z-30 hidden bg-black/40 lg:hidden"></div>

<aside id="sidebar" class="fixed bottom-0 left-0 top-14 z-40 w-72 -translate-x-full overflow-y-auto border-r border-slate-200 bg-brand-50/60 px-4 py-6 transition-transform lg:z-20 lg:translate-x-0 lg:bg-brand-50/50">
  <div class="mb-5 md:hidden"><input id="docSearchM" placeholder="Cari…" class="inp"></div>
  <nav id="nav" class="space-y-6 text-sm">
    <div><p class="navh">Memulai</p>
      <a href="#pengantar" class="nl">Pengantar</a>
      <a href="#autentikasi" class="nl">Autentikasi</a>
      <a href="#api-key" class="nl">Dapatkan API Key</a>
      <a href="#pagination" class="nl">Pagination</a></div>
    <div><p class="navh">Referensi</p>
      <a href="#daftar-cabang" class="nl"><span class="mt get">GET</span>Daftar cabang</a>
      <a href="#skema" class="nl">Skema objek cabang</a>
      <a href="#kode-respons" class="nl">Kode respons</a></div>
    <div><p class="navh">Alat</p>
      <a href="#coba-api" class="nl">Coba API</a>
      <a href="#hasil" class="nl">Hasil diagnostik</a></div>
  </nav>
  <div class="mt-8 rounded-xl border border-gold-400/40 bg-white p-4 text-xs text-slate-600">
    <p class="mb-1 font-bold text-brand-800">Hanya baca</p>Semua contoh dan penguji memakai GET. Data tidak diubah.
  </div>
  <div class="mt-4 rounded-xl border border-slate-200 bg-white p-4 text-xs text-slate-600">
    <p class="mb-2 font-bold text-brand-800">Butuh bantuan?</p>
    <a class="itb it-btn" target="_blank" rel="noopener">Hubungi Tim IT Raja Emas Indonesia</a>
  </div>
</aside>

<main class="pt-14 lg:pl-72">
<div class="mx-auto max-w-6xl px-4 py-10 sm:px-8">

  <section id="pengantar" data-spy class="doc">
    <p class="mb-2 inline-flex items-center gap-2 rounded-full bg-gold-300/30 px-3 py-1 text-xs font-bold text-brand-800">Versi 1 · REST · JSON</p>
    <h1 class="text-3xl font-extrabold tracking-tight text-brand-900 sm:text-4xl">API Cabang REI</h1>
    <p class="mt-3 max-w-2xl text-slate-600">API ini menyediakan daftar cabang Raja Emas Indonesia: nama, alamat, telepon, jam buka, dan tautan peta. Respons berformat JSON dan dibagi per halaman.</p>
    <div class="mt-6 grid gap-8 xl:grid-cols-2">
      <div class="space-y-3 text-sm text-slate-600">
        <h3 class="h3">Base URL</h3>
        <p>Semua request dikirim ke alamat berikut. Alamat ini mengikuti kolom <em>URL endpoint</em> di bagian Coba API.</p>
        <p class="text-xs text-slate-500">Format: HTTPS disarankan di produksi. Request GET saja yang didukung dokumentasi ini.</p>
      </div>
      <div class="code"><div class="codeh"><span>Base URL</span><button class="cp" data-copy="#baseUrl">Salin</button></div><pre id="baseUrl" class="codeb"></pre></div>
    </div>
  </section>

  <section id="autentikasi" data-spy class="doc">
    <h2 class="h2">Autentikasi</h2>
    <div class="mt-4 grid gap-8 xl:grid-cols-2">
      <div class="space-y-3 text-sm text-slate-600">
        <p>Kirim Bearer token pada header <code class="ic">Authorization</code> di setiap request. Token bisa dilihat di bagian <a href="#api-key" class="font-semibold text-brand-700 underline">Dapatkan API Key</a>.</p>
        <p>Tanpa token, atau dengan token yang salah, server membalas <span class="pill bg-red-100 text-red-700">401</span>.</p>
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800">Jangan menaruh token di kode front-end publik. Simpan di server atau variabel lingkungan.</div>
      </div>
      <div class="code"><div class="codeh"><span>Header</span></div><pre class="codeb">Authorization: Bearer &lt;TOKEN_ANDA&gt;
Accept: application/json</pre></div>
    </div>
  </section>

  <section id="api-key" data-spy class="doc">
    <h2 class="h2">Dapatkan API Key</h2>
    <p class="mt-2 max-w-2xl text-sm text-slate-600">Lihat Bearer token untuk mengakses API. Key hanya ditampilkan untuk pengguna yang sudah masuk, dan otomatis disembunyikan setelah 60 detik.</p>

    <div class="mt-5 max-w-2xl rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <label for="keyField" class="lb">API Key / Bearer token</label>
      <input id="keyField" type="password" readonly autocomplete="off" placeholder="Klik “Lihat API Key” untuk menampilkan" class="inp font-mono">
      <div class="mt-4 flex flex-wrap gap-2">
        <button id="keyLoad" class="rounded-xl bg-brand-800 px-4 py-2.5 text-sm font-bold text-white shadow hover:bg-brand-900 disabled:opacity-50">Lihat API Key</button>
        <button id="keyToggle" class="rounded-xl bg-brand-100 px-4 py-2.5 text-sm font-bold text-brand-800 hover:bg-brand-200 disabled:opacity-50" disabled>Tampilkan</button>
        <button id="keyCopy" class="rounded-xl bg-brand-100 px-4 py-2.5 text-sm font-bold text-brand-800 hover:bg-brand-200 disabled:opacity-50" disabled>Salin</button>
        <button id="keyUse" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100 disabled:opacity-50" disabled>Pakai di Coba API</button>
      </div>
      <p id="keyInfo" class="mt-3 text-xs text-slate-400"></p>

      <div id="keyErr" class="mt-4 hidden rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
        <p id="keyErrMsg" class="font-medium"></p>
        <p class="mt-1 text-xs text-red-600/80">Jika masalah berlanjut, hubungi tim IT.</p>
        <div class="mt-3"><a class="itb it-btn" target="_blank" rel="noopener">Hubungi Tim IT Raja Emas Indonesia</a></div>
      </div>
    </div>
  </section>

  <section id="pagination" data-spy class="doc">
    <h2 class="h2">Pagination</h2>
    <div class="mt-4 grid gap-8 xl:grid-cols-2">
      <div class="space-y-3 text-sm text-slate-600">
        <p>Gunakan <code class="ic">limit</code> untuk jumlah data per halaman dan <code class="ic">offset</code> untuk titik mulai. Untuk mengambil semua data, naikkan <code class="ic">offset</code> sebesar jumlah data yang sudah diterima sampai mencapai <code class="ic">pagination.total</code>.</p>
        <p>Objek <code class="ic">pagination</code> pada respons berisi <code class="ic">total</code>, <code class="ic">count</code>, dan <code class="ic">offset</code>.</p>
      </div>
      <div class="code"><div class="codeh"><span>Contoh halaman kedua</span></div><pre class="codeb">GET branches.php?limit=50&amp;offset=50</pre></div>
    </div>
  </section>

  <section id="daftar-cabang" data-spy class="doc">
    <div class="flex flex-wrap items-center gap-3"><span class="mt get lg">GET</span><h2 class="h2 !m-0">Daftar cabang</h2></div>
    <p class="mt-2 font-mono text-sm text-slate-500">/branches.php</p>
    <p class="mt-3 max-w-2xl text-sm text-slate-600">Mengembalikan daftar cabang secara berhalaman.</p>
    <div class="mt-6 grid gap-8 xl:grid-cols-2">
      <div>
        <h3 class="h3">Parameter query</h3>
        <div class="tw mt-2"><table><thead><tr><th>Nama</th><th>Tipe</th><th>Keterangan</th></tr></thead><tbody>
          <tr><td><code class="ic">limit</code></td><td>integer</td><td>Jumlah data per halaman. Penguji menyediakan 10, 25, 50, 100.</td></tr>
          <tr><td><code class="ic">offset</code></td><td>integer</td><td>Jumlah data yang dilewati. Mulai dari 0.</td></tr>
        </tbody></table></div>
        <h3 class="h3 mt-6">Header</h3>
        <div class="tw mt-2"><table><thead><tr><th>Nama</th><th>Wajib</th><th>Nilai</th></tr></thead><tbody>
          <tr><td><code class="ic">Authorization</code></td><td>Ya</td><td>Bearer &lt;token&gt;</td></tr>
          <tr><td><code class="ic">Accept</code></td><td>Tidak</td><td>application/json</td></tr>
        </tbody></table></div>
      </div>
      <div class="space-y-4">
        <div class="code">
          <div class="codeh"><div id="langTabs" class="flex gap-1"></div><button class="cp" id="cpSample">Salin</button></div>
          <pre id="sample" class="codeb"></pre>
        </div>
        <div class="code">
          <div class="codeh"><span>Contoh respons</span><span class="pill bg-emerald-400/20 text-emerald-300">200 OK</span></div>
          <pre class="codeb">{
  "success": true,
  "data": [
    {
      "id": 1,
      "branch_number": "001",
      "title": "REI Cabang Contoh",
      "address": "Jl. Contoh No. 1, Jakarta",
      "phone": "0812-0000-0000",
      "hours": "09.00 - 17.00",
      "map_link": "https://maps.google.com/...",
      "map_embed_url": "https://www.google.com/maps/embed?..."
    }
  ],
  "pagination": { "total": 120, "count": 50, "offset": 0 }
}</pre>
        </div>
        <p class="text-xs text-slate-400">Data di atas hanya contoh bentuk respons.</p>
      </div>
    </div>
  </section>

  <section id="skema" data-spy class="doc">
    <h2 class="h2">Skema objek cabang</h2>
    <p class="mt-2 text-sm text-slate-600">Field yang diperiksa penguji. Field tambahan dari API tetap ditampilkan di kartu cabang.</p>
    <div class="tw mt-4"><table><thead><tr><th>Field</th><th>Tipe</th><th>Keterangan</th></tr></thead><tbody>
      <tr><td><code class="ic">id</code></td><td>integer</td><td>ID unik record. Dipakai untuk mendeteksi duplikat.</td></tr>
      <tr><td><code class="ic">branch_number</code></td><td>string</td><td>Nomor cabang. Harus unik.</td></tr>
      <tr><td><code class="ic">title</code></td><td>string</td><td>Nama cabang.</td></tr>
      <tr><td><code class="ic">address</code></td><td>string</td><td>Alamat lengkap.</td></tr>
      <tr><td><code class="ic">phone</code></td><td>string</td><td>Nomor telepon atau WhatsApp.</td></tr>
      <tr><td><code class="ic">hours</code></td><td>string</td><td>Jam operasional.</td></tr>
      <tr><td><code class="ic">map_link</code></td><td>string (URL)</td><td>Tautan Google Maps.</td></tr>
      <tr><td><code class="ic">map_embed_url</code></td><td>string (URL)</td><td>URL embed peta.</td></tr>
    </tbody></table></div>
  </section>

  <section id="kode-respons" data-spy class="doc">
    <h2 class="h2">Kode respons</h2>
    <div class="tw mt-4"><table><thead><tr><th>Kode</th><th>Arti</th><th>Tindakan</th></tr></thead><tbody>
      <tr><td><span class="pill bg-emerald-100 text-emerald-700">200</span></td><td>Berhasil.</td><td>Baca <code class="ic">data</code> dan <code class="ic">pagination</code>.</td></tr>
      <tr><td><span class="pill bg-red-100 text-red-700">401</span></td><td>Token hilang atau tidak valid.</td><td>Periksa header Authorization.</td></tr>
      <tr><td><span class="pill bg-amber-100 text-amber-800">404</span></td><td>Endpoint tidak ditemukan.</td><td>Periksa URL endpoint.</td></tr>
      <tr><td><span class="pill bg-red-100 text-red-700">5xx</span></td><td>Kesalahan server.</td><td>Cek log server lalu ulangi.</td></tr>
    </tbody></table></div>
  </section>

  <hr class="my-12 border-slate-200">


  <section id="coba-api" data-spy class="doc">
    <h2 class="h2">Coba API</h2>
    <p class="mt-2 max-w-2xl text-sm text-slate-600">Jalankan request langsung dari browser. Tes lengkap mengikuti pagination sampai semua data terkumpul.</p>
    <div class="mt-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-12">
        <div class="xl:col-span-4"><label for="endpoint" class="lb">URL endpoint</label><input id="endpoint" value="branches.php" class="inp"></div>
        <div class="xl:col-span-4"><label for="token" class="lb">Bearer token</label><input id="token" type="password" autocomplete="off" placeholder="Ambil dari bagian Dapatkan API Key" value="<?= e(API_TOKEN) ?>" class="inp"></div>
        <div class="xl:col-span-2"><label for="pageSize" class="lb">Ukuran halaman</label><select id="pageSize" class="inp"><option>10</option><option>25</option><option selected>50</option><option>100</option></select></div>
        <div class="xl:col-span-2"><label for="timeout" class="lb">Batas waktu (detik)</label><select id="timeout" class="inp"><option>10</option><option selected>20</option><option>30</option><option>60</option></select></div>
      </div>
      <div class="mt-5 flex flex-wrap gap-2">
        <button id="runBtn" class="rounded-xl bg-brand-800 px-5 py-2.5 text-sm font-bold text-white shadow hover:bg-brand-900 disabled:opacity-50">Jalankan tes lengkap</button>
        <button id="singleBtn" class="rounded-xl bg-brand-100 px-5 py-2.5 text-sm font-bold text-brand-800 hover:bg-brand-200 disabled:opacity-50">Tes satu request</button>
        <button id="clearBtn" class="rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100">Bersihkan</button>
      </div>
    </div>
    <div id="status" class="mt-4 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600">Siap. Isi Bearer token lalu jalankan tes.</div>
    <div id="itHelp" class="mt-3 hidden"><a class="itb it-btn" target="_blank" rel="noopener">Hubungi Tim IT Raja Emas Indonesia</a></div>
  </section>

  <section id="hasil" data-spy class="doc">
    <h2 class="h2">Hasil diagnostik</h2>
    <div class="mt-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
      <div class="metric"><p class="mlabel">Total menurut API</p><p id="mTotal" class="mval">—</p><p class="msub">pagination.total</p></div>
      <div class="metric"><p class="mlabel">Data terkumpul</p><p id="mFetched" class="mval">—</p><p class="msub">Record diterima</p></div>
      <div class="metric"><p class="mlabel">Field kosong</p><p id="mMissing" class="mval">—</p><p class="msub">Pada field penting</p></div>
      <div class="metric"><p class="mlabel">Data terindikasi hilang</p><p id="mLost" class="mval">—</p><p class="msub">Selisih total / ID ganda</p></div>
    </div>

    <div class="mt-6 rounded-2xl border border-slate-200 bg-white shadow-sm">
      <div id="tabs" class="flex gap-1 overflow-x-auto border-b border-slate-200 px-3 pt-3">
        <button class="tab on" data-tab="summaryP">Ringkasan <span id="overall" class="pill bg-slate-100 text-slate-600">Belum diuji</span></button>
        <button class="tab" data-tab="allP">Semua cabang <span id="allCount" class="pill bg-brand-100 text-brand-800">0</span></button>
        <button class="tab" data-tab="missingP">Field kosong <span id="missingCount" class="pill bg-slate-100 text-slate-600">0</span></button>
        <button class="tab" data-tab="dupP">Duplikat <span id="duplicateCount" class="pill bg-slate-100 text-slate-600">0</span></button>
        <button class="tab" data-tab="logP">Log request</button>
        <button class="tab" data-tab="rawP">JSON</button>
      </div>
      <div class="p-5">
        <div id="summaryP" class="tp"><div id="summary" class="text-sm text-slate-500">Hasil akan muncul di sini.</div></div>
        <div id="allP" class="tp hidden"><input id="q" placeholder="Cari cabang, alamat, telepon…" class="inp mb-4"><div id="allList" class="text-sm text-slate-500">Belum ada hasil.</div></div>
        <div id="missingP" class="tp hidden"><div id="missingTable" class="text-sm text-slate-500">Belum ada hasil.</div></div>
        <div id="dupP" class="tp hidden"><div id="duplicateTable" class="text-sm text-slate-500">Belum ada hasil.</div></div>
        <div id="logP" class="tp hidden"><div class="mb-3 text-right"><button id="copyLog" class="btn2">Salin log</button></div><div id="logTable" class="text-sm text-slate-500">Belum ada request.</div></div>
        <div id="rawP" class="tp hidden"><div class="mb-3 text-right"><button id="copyJson" class="btn2">Salin JSON</button></div><pre id="raw" class="codeb max-h-[430px] overflow-auto rounded-xl">{}</pre></div>
      </div>
    </div>
  </section>

  <footer class="mt-12 border-t border-slate-200 pt-6 text-center text-xs text-slate-400">© <?= date('Y') ?> Raja Emas Indonesia · Dokumentasi API</footer>
</div>
</main>

<style type="text/tailwindcss">
@layer components{
 .inp{@apply w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm shadow-sm outline-none transition focus:border-brand-600 focus:ring-4 focus:ring-brand-200}
 .lb{@apply mb-1.5 block text-sm font-semibold}
 .doc{@apply scroll-mt-20 pb-12}
 .h2{@apply mb-1 text-2xl font-extrabold text-brand-900}
 .h3{@apply text-sm font-bold text-brand-800}
 .ic{@apply rounded bg-brand-100 px-1.5 py-0.5 font-mono text-[12px] text-brand-800}
 .navh{@apply mb-2 px-3 text-xs font-bold text-brand-700}
 .nl{@apply flex items-center gap-2 rounded-lg px-3 py-2 text-slate-600 transition hover:bg-white hover:text-brand-800}
 .nl.on{@apply bg-brand-800 font-semibold text-white hover:bg-brand-800 hover:text-white}
 .mt{@apply inline-block rounded px-1.5 py-0.5 font-mono text-[10px] font-bold}
 .mt.get{@apply bg-emerald-100 text-emerald-700}
 .mt.lg{@apply px-2.5 py-1 text-xs}
 .nl.on .mt.get{@apply bg-emerald-400 text-brand-950}
 .code{@apply overflow-hidden rounded-xl bg-brand-950 shadow-md}
 .codeh{@apply flex items-center justify-between gap-2 border-b border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold text-brand-200}
 .codeb{@apply overflow-x-auto whitespace-pre bg-brand-950 p-4 font-mono text-[12.5px] leading-relaxed text-brand-100}
 .cp{@apply rounded-md bg-white/10 px-2.5 py-1 text-[11px] font-semibold text-white hover:bg-white/20}
 .lt{@apply rounded-md px-2.5 py-1 text-[11px] font-semibold text-brand-200 hover:bg-white/10}
 .lt.on{@apply bg-gold-500 text-brand-950}
 .pill{@apply inline-block whitespace-nowrap rounded-full px-2.5 py-0.5 text-[11px] font-bold}
 .metric{@apply rounded-2xl border border-slate-200 border-t-4 border-t-gold-500 bg-white p-4 shadow-sm}
 .mlabel{@apply text-xs font-semibold text-slate-500}
 .mval{@apply mt-1 break-words text-2xl font-extrabold text-brand-800 sm:text-3xl}
 .msub{@apply text-xs text-slate-400}
 .btn2{@apply rounded-lg bg-brand-100 px-3 py-1.5 text-xs font-bold text-brand-800 hover:bg-brand-200}
 .itb{@apply inline-flex cursor-pointer items-center justify-center rounded-xl bg-gold-500 px-4 py-2.5 text-center text-sm font-bold text-brand-950 shadow transition hover:bg-gold-400}
 .tw{@apply overflow-auto rounded-xl border border-slate-200 bg-white}
 .tw table{@apply w-full min-w-[460px] border-collapse text-left text-[13px]}
 .tw th{@apply bg-brand-50 px-3 py-2 text-xs font-bold text-brand-800}
 .tw td{@apply border-t border-slate-100 px-3 py-2 align-top}
 .tab{@apply flex flex-none items-center gap-2 rounded-t-lg border-b-2 border-transparent px-3 py-2.5 text-sm font-semibold text-slate-500 hover:text-brand-800}
 .tab.on{@apply border-brand-800 text-brand-800}
}
html{scroll-behavior:smooth}
</style>

<script>
(()=>{'use strict';
const $=id=>document.getElementById(id);let logs=[],busy=false,cache=[],lang='cURL';
const CSRF=<?= json_encode($csrf) ?>;
const IT={wa:<?= json_encode(IT_WA_NUMBER) ?>,email:<?= json_encode(IT_EMAIL) ?>,user:<?= json_encode((string)$_SESSION['user']) ?>};
const fields=[['branch_number','Nomor cabang'],['title','Nama cabang'],['address','Alamat'],['phone','Telepon/WhatsApp'],['hours','Jam operasional'],['map_link','Link peta'],['map_embed_url','Embed peta']];
const shown=[['address','Alamat'],['phone','Hp & WhatsApp'],['hours','Buka'],['notes','Catatan'],['note','Catatan'],['catatan','Catatan'],['map_link','Link peta'],['map_embed_url','Embed peta']];
const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const empty=v=>v===null||v===undefined||String(v).trim()==='';
const has=(o,k)=>Object.prototype.hasOwnProperty.call(o,k);
const P={ok:'bg-emerald-100 text-emerald-700',bad:'bg-red-100 text-red-700',warn:'bg-amber-100 text-amber-800',def:'bg-slate-100 text-slate-600'};
const ST={'':'border-slate-200 bg-white text-slate-600',ok:'border-emerald-200 bg-emerald-50 text-emerald-700',bad:'border-red-200 bg-red-50 text-red-700',warn:'border-amber-200 bg-amber-50 text-amber-800'};

/* ---------- Hubungi Tim IT ---------- */
function itUrl(reason){
  const msg=`Halo Tim IT Raja Emas Indonesia,\nSaya mengalami kendala pada Dokumentasi API REI.\n\nUser: ${IT.user}\nKendala: ${reason||'-'}\nHalaman: ${location.href}`;
  if(IT.wa)return 'https://wa.me/'+String(IT.wa).replace(/\D/g,'')+'?text='+encodeURIComponent(msg);
  if(IT.email)return 'mailto:'+IT.email+'?subject='+encodeURIComponent('Kendala Dokumentasi API REI')+'&body='+encodeURIComponent(msg);
  return '';
}
function setIt(reason){const u=itUrl(reason);document.querySelectorAll('.it-btn').forEach(a=>{if(u)a.href=u;else a.removeAttribute('href')})}
document.querySelectorAll('.it-btn').forEach(a=>a.addEventListener('click',e=>{if(!a.getAttribute('href')){e.preventDefault();alert('Kontak tim IT belum diatur. Isi IT_WA_NUMBER atau IT_EMAIL di bagian atas file ini.')}}));
setIt('');

function status(t,c=''){$('status').className='mt-4 rounded-xl border px-4 py-3 text-sm '+ST[c];$('status').textContent=t;$('itHelp').classList.toggle('hidden',c!=='bad');if(c==='bad')setIt(t)}
function setOverall(c,t){$('overall').className='pill '+P[c||'def'];$('overall').textContent=t}
function busySet(v){busy=v;$('runBtn').disabled=v;$('singleBtn').disabled=v}
function flash(btn,t='Tersalin'){const o=btn.textContent;btn.textContent=t;setTimeout(()=>btn.textContent=o,1200)}
async function copy(text,btn){try{await navigator.clipboard.writeText(text);flash(btn)}catch{flash(btn,'Gagal')}}

/* ---------- Dapatkan API Key ---------- */
let keyVal='',keyShown=false,keyTimer=null;
function keyUi(on){['keyToggle','keyCopy','keyUse'].forEach(id=>$(id).disabled=!on)}
function keyClear(msg){clearInterval(keyTimer);keyVal='';keyShown=false;$('keyField').value='';$('keyField').type='password';$('keyToggle').textContent='Tampilkan';keyUi(false);$('keyInfo').textContent=msg||''}
function keyError(msg){$('keyErrMsg').textContent=msg;$('keyErr').classList.remove('hidden');setIt('Gagal mengambil API key: '+msg)}
async function loadKey(){
  $('keyLoad').disabled=true;$('keyErr').classList.add('hidden');
  try{
    const fd=new FormData();fd.append('action','getkey');fd.append('csrf',CSRF);
    const r=await fetch(location.pathname,{method:'POST',body:fd,credentials:'same-origin',cache:'no-store'});
    let j=null;try{j=await r.json()}catch{}
    if(!r.ok||!j||!j.ok)throw new Error((j&&j.message)||('Gagal memuat API key (HTTP '+r.status+').'));
    keyVal=j.key;$('keyField').value=keyVal;$('keyField').type='password';keyShown=false;$('keyToggle').textContent='Tampilkan';keyUi(true);
    let left=60;clearInterval(keyTimer);
    const tick=()=>{$('keyInfo').textContent='Disembunyikan otomatis dalam '+left+' detik.';if(left--<=0)keyClear('API key disembunyikan. Klik “Lihat API Key” untuk menampilkan lagi.')};
    tick();keyTimer=setInterval(tick,1000);
  }catch(e){keyClear('');keyError(e.message)}
  finally{$('keyLoad').disabled=false}
}
$('keyLoad').onclick=loadKey;
$('keyToggle').onclick=()=>{keyShown=!keyShown;$('keyField').type=keyShown?'text':'password';$('keyToggle').textContent=keyShown?'Sembunyikan':'Tampilkan'};
$('keyCopy').onclick=e=>copy(keyVal,e.target);
$('keyUse').onclick=e=>{$('token').value=keyVal;flash(e.target,'Terpakai');document.getElementById('coba-api').scrollIntoView({behavior:'smooth'})};

/* ---------- Contoh kode ---------- */
function baseUrl(){try{return new URL($('endpoint').value.trim()||'branches.php',location.href).toString()}catch{return 'branches.php'}}
function samples(){const u=new URL(baseUrl());u.searchParams.set('limit',50);u.searchParams.set('offset',0);const url=u.toString();
return{
'cURL':`curl -X GET "${url}" \\\n  -H "Authorization: Bearer <TOKEN_ANDA>" \\\n  -H "Accept: application/json"`,
'PHP':`${'<'}?php\n$ch = curl_init("${url}");\ncurl_setopt_array($ch, [\n    CURLOPT_RETURNTRANSFER => true,\n    CURLOPT_HTTPHEADER => [\n        "Authorization: Bearer <TOKEN_ANDA>",\n        "Accept: application/json",\n    ],\n]);\n$body = curl_exec($ch);\ncurl_close($ch);\n\n$json = json_decode($body, true);\nprint_r($json["pagination"]);`,
'JavaScript':`const res = await fetch("${url}", {\n  method: "GET",\n  headers: {\n    "Authorization": "Bearer <TOKEN_ANDA>",\n    "Accept": "application/json"\n  }\n});\nconst json = await res.json();\nconsole.log(json.pagination);`,
'Python':`import requests\n\nres = requests.get(\n    "${url}",\n    headers={\n        "Authorization": "Bearer <TOKEN_ANDA>",\n        "Accept": "application/json",\n    },\n    timeout=20,\n)\nprint(res.json()["pagination"])`};}
function renderSample(){const s=samples();$('sample').textContent=s[lang];$('baseUrl').textContent=baseUrl().split('?')[0];
$('langTabs').innerHTML=Object.keys(s).map(k=>`<button class="lt ${k===lang?'on':''}" data-lang="${k}">${k}</button>`).join('')}
$('langTabs').addEventListener('click',e=>{const k=e.target.dataset.lang;if(k){lang=k;renderSample()}});
$('endpoint').addEventListener('input',renderSample);
$('cpSample').onclick=e=>copy($('sample').textContent,e.target);
document.querySelectorAll('[data-copy]').forEach(b=>b.onclick=()=>copy($(b.dataset.copy.slice(1)).textContent,b));
renderSample();

/* ---------- Navigasi ---------- */
const sb=$('sidebar'),ov=$('overlay');
function toggleMenu(open){sb.classList.toggle('-translate-x-full',!open);ov.classList.toggle('hidden',!open)}
$('menuBtn').onclick=()=>toggleMenu(sb.classList.contains('-translate-x-full'));ov.onclick=()=>toggleMenu(false);
$('nav').addEventListener('click',e=>{if(e.target.closest('a'))toggleMenu(false)});
const links=[...document.querySelectorAll('#nav a')];
const spy=new IntersectionObserver(es=>{es.forEach(en=>{if(en.isIntersecting){links.forEach(l=>l.classList.toggle('on',l.getAttribute('href')==='#'+en.target.id))}})},{rootMargin:'-20% 0px -70% 0px'});
document.querySelectorAll('[data-spy]').forEach(s=>spy.observe(s));
function filterNav(v){v=v.trim().toLowerCase();links.forEach(l=>l.style.display=!v||l.textContent.toLowerCase().includes(v)?'':'none')}
$('docSearch').oninput=e=>{$('docSearchM').value=e.target.value;filterNav(e.target.value)};
$('docSearchM').oninput=e=>{$('docSearch').value=e.target.value;filterNav(e.target.value)};

/* ---------- Tab hasil ---------- */
$('tabs').addEventListener('click',e=>{const b=e.target.closest('.tab');if(!b)return;document.querySelectorAll('.tab').forEach(t=>t.classList.toggle('on',t===b));document.querySelectorAll('.tp').forEach(p=>p.classList.toggle('hidden',p.id!==b.dataset.tab))});
function openTab(id){document.querySelector(`.tab[data-tab="${id}"]`).click()}

/* ---------- Penguji ---------- */
function fmt(k,v){if(empty(v))return '<i class="text-slate-400">(kosong)</i>';if(typeof v==='object')return esc(JSON.stringify(v));let s=String(v);if(/^https?:\/\//i.test(s)&&k!=='map_embed_url')return `<a class="text-brand-700 underline" href="${esc(s)}" target="_blank" rel="noopener">${esc(s)}</a>`;return esc(s).replace(/\r?\n/g,'<br>')}
function card(r){let used=new Set(['title','branch_number']),rows='';
shown.forEach(([k,l])=>{if(has(r,k)){used.add(k);rows+=`<div class="flex gap-2 text-[13px]"><b class="w-28 flex-none text-slate-500">${esc(l)}</b><span class="min-w-0 break-words">${fmt(k,r[k])}</span></div>`}});
Object.keys(r).filter(k=>!used.has(k)).forEach(k=>{rows+=`<div class="flex gap-2 text-xs text-slate-400"><b class="w-28 flex-none">${esc(k)}</b><span class="min-w-0 break-words">${fmt(k,r[k])}</span></div>`});
return `<article class="rounded-xl border border-slate-200 border-l-4 border-l-brand-700 bg-white p-4 transition hover:shadow-md"><h3 class="mb-2 flex items-start justify-between gap-2 text-[15px] font-bold text-brand-900"><span>${esc(r.title||'(tanpa nama)')}</span>${empty(r.branch_number)?'':`<span class="pill bg-gold-300/40 text-brand-800">No. ${esc(r.branch_number)}</span>`}</h3><div class="space-y-1">${rows}</div></article>`}
function renderAll(){let q=$('q').value.trim().toLowerCase(),list=cache.filter(r=>!q||JSON.stringify(r).toLowerCase().includes(q));
$('allCount').textContent=(q?list.length+' / ':'')+cache.length;
$('allList').className=list.length?'grid gap-3 sm:grid-cols-2 xl:grid-cols-3':'text-sm text-slate-500';
$('allList').innerHTML=list.length?list.map(card).join(''):(cache.length?'Tidak ada yang cocok dengan pencarian.':'Belum ada hasil.')}
function renderLogs(){if(!logs.length){$('logTable').textContent='Belum ada request.';return}
$('logTable').innerHTML='<div class="tw"><table><thead><tr><th>#</th><th>HTTP</th><th>Durasi</th><th>Offset</th><th>Data</th><th>Pesan</th></tr></thead><tbody>'+logs.map((x,i)=>{let u=new URL(x.url,location.href),j=x.json||{};return `<tr><td>${i+1}</td><td><span class="pill ${x.ok?P.ok:P.bad}">${x.status||'Gagal'}</span></td><td>${x.ms} ms</td><td>${esc(u.searchParams.get('offset')??'—')}</td><td>${Array.isArray(j.data)?j.data.length:'—'}</td><td>${esc(x.error||j.message||(x.ok?'OK':'HTTP error'))}</td></tr>`}).join('')+'</tbody></table></div>'}
async function get(url){let ctl=new AbortController(),timer=setTimeout(()=>ctl.abort(),Number($('timeout').value)*1000),start=performance.now();
try{let h={Accept:'application/json'},t=$('token').value.trim();if(t)h.Authorization='Bearer '+t;
let r=await fetch(url,{method:'GET',headers:h,cache:'no-store',signal:ctl.signal,credentials:'same-origin'}),txt=await r.text(),j=null;try{j=JSON.parse(txt)}catch{}
let x={url,status:r.status,ms:Math.round(performance.now()-start),ok:r.ok,json:j,text:txt,error:j?(!r.ok?'HTTP '+r.status:''):'Respons bukan JSON valid'};logs.push(x);$('raw').textContent=JSON.stringify(j??{raw:txt},null,2);renderLogs();return x
}catch(e){let x={url,status:0,ms:Math.round(performance.now()-start),ok:false,json:null,error:e.name==='AbortError'?'Request timeout':e.message};logs.push(x);$('raw').textContent=JSON.stringify({error:x.error,url},null,2);renderLogs();return x}finally{clearTimeout(timer)}}
function table(rows,heads,emptyText){if(!rows.length)return `<div class="text-sm text-slate-500">${esc(emptyText)}</div>`;return '<div class="tw"><table><thead><tr>'+heads.map(h=>`<th>${esc(h)}</th>`).join('')+'</tr></thead><tbody>'+rows.map(r=>'<tr>'+r.map(v=>`<td>${v}</td>`).join('')+'</tr>').join('')+'</tbody></table></div>'}
function resetPanels(){$('missingTable').textContent='Belum ada hasil.';$('duplicateTable').textContent='Belum ada hasil.';$('missingCount').textContent='0';$('duplicateCount').textContent='0';['mTotal','mFetched','mMissing','mLost'].forEach(k=>$(k).textContent='—')}
async function run(one=false){if(busy)return;busySet(true);logs=[];cache=[];renderLogs();renderAll();resetPanels();$('summary').textContent='Pengujian berjalan…';setOverall('warn','Berjalan');
document.getElementById('hasil').scrollIntoView({behavior:'smooth'});openTab('summaryP');
try{let raw=$('endpoint').value.trim();if(!raw)throw Error('URL endpoint belum diisi.');let base=new URL(raw,location.href).toString(),size=Number($('pageSize').value),u=new URL(base);u.searchParams.set('limit',size);u.searchParams.set('offset',0);
status('Mengirim request GET pertama…');let first=await get(u.toString());
if(!first.ok||!first.json||first.json.success!==true||!Array.isArray(first.json.data)){status(`Request gagal/format tidak sesuai. HTTP ${first.status||'tanpa respons'}. Periksa URL dan token.`,'bad');setOverall('bad','Gagal');$('summary').textContent=first.error||(first.json&&first.json.message)||'Respons perlu memiliki success=true dan data berupa array.';return}
let all=[...first.json.data],p=first.json.pagination||{},total=Number.isFinite(Number(p.total))?Number(p.total):all.length,issues=[];
if(!first.json.pagination)issues.push('Objek pagination tidak ditemukan.');
if(first.json.pagination&&Number(p.count)!==first.json.data.length)issues.push(`pagination.count ${p.count} berbeda dari data.length ${first.json.data.length}.`);
if(one){cache=all;renderAll();$('mTotal').textContent=total;$('mFetched').textContent=all.length;$('mMissing').textContent='—';$('mLost').textContent=Math.max(0,total-all.length);$('summary').textContent=`Satu request mengembalikan ${all.length} record; API melaporkan total ${total}. Jalankan tes lengkap untuk mengambil semua halaman.`;setOverall('warn','Tes satu halaman');status('Tes satu request selesai.','ok');return}
let offset=all.length,loops=0;
while(offset<total&&loops++<1000){let next=new URL(base);next.searchParams.set('limit',size);next.searchParams.set('offset',offset);status(`Mengambil data: ${all.length} dari ${total}…`);let r=await get(next.toString());
if(!r.ok||!r.json||r.json.success!==true||!Array.isArray(r.json.data)){issues.push(`Request offset ${offset} gagal: ${r.error||(r.json&&r.json.message)||'respons tidak valid'}.`);break}
let batch=r.json.data;if(!batch.length){issues.push(`Data kosong pada offset ${offset} sebelum total tercapai.`);break}
let rp=r.json.pagination||{};if(r.json.pagination){if(Number(rp.offset)!==offset)issues.push(`Offset diminta ${offset}, API melaporkan ${rp.offset}.`);if(Number(rp.count)!==batch.length)issues.push(`Offset ${offset}: count tidak sama dengan data.length.`);if(Number(rp.total)!==total)issues.push(`Total berubah pada offset ${offset}.`)}else issues.push(`Pagination hilang pada offset ${offset}.`);
all.push(...batch);if(batch.length<size&&all.length<total){issues.push(`Halaman offset ${offset} hanya ${batch.length} record, total belum tercapai.`);break}offset+=batch.length}
if(loops>=1000)issues.push('Dihentikan demi keamanan: terlalu banyak halaman.');
cache=all;renderAll();
let missing=[];all.forEach(r=>{let ef=fields.filter(([k])=>has(r,k)&&empty(r[k])).map(x=>x[1]);if(ef.length)missing.push({...r,ef})});
let ids=new Map(),nums=new Map();all.forEach(r=>{if(!empty(r.id)){let k=String(r.id);ids.set(k,[...(ids.get(k)||[]),r])}if(!empty(r.branch_number)){let k=String(r.branch_number).trim();nums.set(k,[...(nums.get(k)||[]),r])}});
let dups=[];ids.forEach((rs,k)=>{if(rs.length>1)dups.push({type:'ID duplikat',value:k,rs})});nums.forEach((rs,k)=>{if(rs.length>1)dups.push({type:'Nomor cabang duplikat',value:k,rs})});
let uniqueIds=new Set(all.filter(r=>!empty(r.id)).map(r=>String(r.id))).size,gap=Math.max(0,total-all.length),lost=Math.max(gap,all.length-uniqueIds);
$('mTotal').textContent=total;$('mFetched').textContent=all.length;$('mMissing').textContent=missing.reduce((n,r)=>n+r.ef.length,0);$('mLost').textContent=lost;
$('missingCount').textContent=missing.length;$('missingTable').innerHTML=table(missing.map(r=>[esc(r.branch_number||'—'),esc(r.title||'—'),esc(r.ef.join(', '))]),['Nomor','Nama','Field kosong'],'Tidak ditemukan field kosong pada field yang tersedia.');
$('duplicateCount').textContent=dups.length;$('duplicateTable').innerHTML=table(dups.map(d=>[esc(d.type),esc(d.value),esc(d.rs.map(r=>`ID ${r.id??'—'} / ${r.branch_number??'—'} / ${r.title??'tanpa nama'}`).join(' | '))]),['Jenis','Nilai','Record terkait'],'Tidak ditemukan ID atau nomor cabang duplikat.');
let notes=[`Total dilaporkan API: ${total}. Record terkumpul: ${all.length}.`,`ID unik: ${uniqueIds}; nomor cabang unik: ${nums.size}.`];if(gap)notes.push(`${gap} record belum terkumpul dibanding total API.`);if(all.length>total)notes.push(`${all.length-total} record melebihi total yang dilaporkan.`);if(dups.length)notes.push(`Ditemukan ${dups.length} grup duplikat.`);if(missing.length)notes.push(`${missing.length} cabang punya field penting kosong.`);if(issues.length)notes.push('Anomali pagination: '+issues.join(' '));if(!gap&&all.length<=total&&!dups.length&&!missing.length&&!issues.length)notes.push('Pagination dan data yang diterima terlihat konsisten. Tes ini tidak membuktikan sumber upstream berisi semua cabang yang seharusnya.');
$('summary').innerHTML='<ul class="list-disc space-y-1 pl-5 text-slate-700">'+notes.map(n=>`<li>${esc(n)}</li>`).join('')+'</ul>';let bad=gap>0||all.length>total||dups.length>0||issues.length>0;setOverall(bad?'bad':missing.length?'warn':'ok',bad?'Perlu diperiksa':missing.length?'Ada field kosong':'Konsisten');status(`Tes selesai: ${all.length} record terkumpul dari total ${total}.`,bad?'bad':missing.length?'warn':'ok');
$('raw').textContent=JSON.stringify({summary:{total,fetched:all.length,uniqueIds,uniqueBranchNumbers:nums.size,missingBranchCount:missing.length,duplicateGroups:dups.length,paginationIssues:issues},records:all},null,2);
}catch(e){status('Pengujian tidak dapat dijalankan: '+e.message,'bad');setOverall('bad','Error');$('summary').textContent=e.message}finally{busySet(false)}}
$('runBtn').onclick=()=>run(false);$('singleBtn').onclick=()=>run(true);$('q').oninput=renderAll;
$('clearBtn').onclick=()=>{logs=[];cache=[];renderLogs();renderAll();$('q').value='';$('raw').textContent='{}';$('summary').textContent='Hasil dibersihkan.';setOverall('','Belum diuji');resetPanels();status('Hasil dibersihkan.')};
$('copyLog').onclick=e=>copy(logs.map((x,i)=>`${i+1}. HTTP ${x.status||'GAGAL'} | ${x.ms}ms | ${x.url} | ${x.error||x.json?.message||''}`).join('\n'),e.target);
$('copyJson').onclick=e=>copy($('raw').textContent,e.target);
})();
</script>
</body>
<?php endif; ?>
</html>
