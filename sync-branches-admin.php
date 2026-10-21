<?php
declare(strict_types=1);

session_start();

// Sesuaikan kondisi ini bila sistem login REI CMS Anda memakai nama session lain.
if (empty($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

if (empty($_SESSION['branch_sync_csrf'])) {
    $_SESSION['branch_sync_csrf'] = bin2hex(random_bytes(32));
}

$result = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals((string) $_SESSION['branch_sync_csrf'], $token)) {
        http_response_code(403);
        $error = 'Permintaan tidak valid. Muat ulang halaman lalu coba lagi.';
    } else {
        // File scraper harus berada satu folder dengan halaman ini.
        $scraper = __DIR__ . DIRECTORY_SEPARATOR . 'scrape-branches.php';
        if (!is_file($scraper)) {
            $error = 'File scrape-branches.php tidak ditemukan. Simpan file scraper yang sudah diperbarui di folder yang sama.';
        } else {
            @set_time_limit(180);
            ob_start();
            try {
                require_once $scraper;
                main();
                $result = (string) ob_get_contents();
            } catch (Throwable $e) {
                $error = $e->getMessage();
                $result = (string) ob_get_contents();
            } finally {
                ob_end_clean();
            }
            $_SESSION['branch_sync_csrf'] = bin2hex(random_bytes(32));
        }
    }
}

function h(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sinkronisasi Cabang | REI CMS</title>
<style>
:root{color-scheme:dark;--bg:#100d18;--panel:#1c1728;--line:#393047;--text:#f7f4fb;--muted:#b8aec8;--gold:#d4ad55}
*{box-sizing:border-box}body{margin:0;background:radial-gradient(circle at top right,#352044 0,transparent 38%),var(--bg);color:var(--text);font:15px/1.6 Inter,Segoe UI,Arial,sans-serif}
.wrap{max-width:1050px;margin:0 auto;padding:28px 18px 48px}.top{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:22px}
.brand{font-size:13px;letter-spacing:.12em;color:var(--gold);font-weight:800}.top h1{font-size:clamp(25px,4vw,36px);line-height:1.2;margin:8px 0}.muted{color:var(--muted)}
.panel{background:rgba(28,23,40,.94);border:1px solid var(--line);border-radius:18px;padding:22px;margin:16px 0;box-shadow:0 18px 55px #0002}
.row{display:flex;align-items:center;gap:12px;flex-wrap:wrap}.btn{appearance:none;border:0;border-radius:12px;padding:12px 18px;background:linear-gradient(135deg,#d4ad55,#f0d18a);color:#21172a;font-weight:800;font-size:15px;cursor:pointer}.btn:disabled{opacity:.6;cursor:wait}
.tag{display:inline-block;padding:4px 9px;border-radius:999px;background:#342b42;color:#e8d9ff;font-size:12px}.notice{padding:12px 14px;border-radius:12px;margin:14px 0}.error{background:#4b202b;border:1px solid #8f3949}.success{background:#183b31;border:1px solid #2e765e}
pre{white-space:pre-wrap;overflow-wrap:anywhere;background:#100d18;border:1px solid var(--line);border-radius:12px;padding:16px;max-height:65vh;overflow:auto;font:13px/1.55 Consolas,monospace}
small{color:var(--muted)}@media(max-width:600px){.panel{padding:16px}.wrap{padding:20px 12px}}
</style>
</head>
<body>
<main class="wrap">
<header class="top"><div><div class="brand">RAJA EMAS INDONESIA · CMS</div><h1>Sinkronisasi Cabang</h1><div class="muted">Ambil data cabang dari website dan perbarui tabel <code>branches</code>.</div></div><span class="tag">Admin</span></header>
<section class="panel">
<h2 style="margin-top:0">Jalankan sinkronisasi</h2>
<p class="muted">Proses ini bisa menambah cabang baru dan memperbarui data yang sudah ada. Data lama dipertahankan bila hasil scraping kosong. Pastikan konfigurasi database di scraper benar.</p>
<div class="row">
<form method="post" onsubmit="const b=this.querySelector('button');b.disabled=true;b.textContent='Sedang menyinkronkan…';">
<input type="hidden" name="csrf_token" value="<?= h((string) $_SESSION['branch_sync_csrf']) ?>">
<button class="btn" type="submit">↻ Sinkronisasi Cabang Sekarang</button>
</form><small>Sumber: rajaemasindonesia.co.id</small>
</div>
<?php if ($error !== ''): ?><div class="notice error"><strong>Sinkronisasi mengalami kendala:</strong><br><?= h($error) ?></div>
<?php elseif ($result !== ''): ?><div class="notice success"><strong>Proses selesai.</strong> Periksa ringkasan dan peringatan di bawah.</div><?php endif; ?>
</section>
<?php if ($result !== ''): ?><section class="panel"><h2 style="margin-top:0">Log sinkronisasi</h2><pre><?= h($result) ?></pre></section><?php endif; ?>
<?php if ($error !== '' && $result === ''): ?><section class="panel"><p class="muted">Pastikan ekstensi PHP cURL, DOM, dan mbstring aktif; MySQL berjalan; dan akun database memiliki izin ke tabel <code>branches</code>.</p></section><?php endif; ?>
<p class="muted"><small>Halaman ini mensyaratkan session <code>admin_logged_in</code> dan token CSRF. Batasi akses hanya untuk administrator.</small></p>
</main></body></html>
