<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_admin();

$user = $_SESSION['admin_user'] ?? [];
if (is_array($user)) {
    $displayName = $user['username'] ?? $user['name'] ?? 'Administrator';
} else {
    $displayName = is_string($user) && $user !== '' ? $user : 'Administrator';
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dashboard Admin — REI CMS</title>
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config={theme:{extend:{colors:{rei:{950:'#1b1026',900:'#261535',800:'#352044',700:'#4A00E0',gold:'#c99a2e'}}}}}</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
body{font-family:Inter,system-ui,sans-serif}.nav-btn.active{background:rgba(201,154,46,.15);color:#f2d27b;border:1px solid rgba(201,154,46,.22)}.nav-btn{border:1px solid transparent}.glass{background:rgba(255,255,255,.045);border:1px solid rgba(255,255,255,.08)}.field{width:100%;border:1px solid #44344f;background:#21152b;color:#fff;border-radius:.75rem;padding:.7rem .85rem;outline:none}.field:focus{border-color:#c99a2e}.btn-gold{background:#c99a2e;color:#24132c;font-weight:700;border-radius:.75rem;padding:.65rem 1rem}.btn-muted{background:#382843;color:#fff;border:1px solid #4b3856;border-radius:.75rem;padding:.6rem .9rem}.table-wrap{overflow:auto}.admin-table{width:100%;border-collapse:collapse;min-width:640px}.admin-table th,.admin-table td{padding:.85rem 1rem;text-align:left;border-bottom:1px solid #ffffff12;font-size:.875rem}.admin-table th{color:#c9b9d3;font-weight:600;background:#ffffff05}.admin-table td{color:#f4eef7}.tag{border:1px solid #ffffff18;border-radius:999px;padding:.2rem .55rem;font-size:.72rem;color:#d9cde1}
</style>
</head>
<body class="min-h-screen bg-[#170d20] text-white">
<div class="min-h-screen lg:flex">
<aside id="sidebar" class="fixed inset-y-0 left-0 z-40 flex w-72 -translate-x-full flex-col border-r border-white/10 bg-[#21132b] transition-transform lg:translate-x-0">
<div class="flex h-20 items-center gap-3 border-b border-white/10 px-6"><div class="grid h-11 w-11 place-items-center rounded-xl bg-[#c99a2e] font-extrabold text-[#24132c]">REI</div><div><p class="font-bold tracking-wide">RAJA EMAS</p><p class="text-xs text-white/45">CMS ADMIN</p></div><button id="closeSidebar" class="ml-auto rounded-lg p-2 text-white/60 lg:hidden" aria-label="Tutup menu">✕</button></div>
<nav class="flex-1 space-y-1 overflow-y-auto p-4">
<p class="px-3 pb-2 pt-3 text-[10px] font-bold uppercase tracking-[.2em] text-white/35">Workspace</p>
<button class="nav-btn active flex w-full items-center gap-3 rounded-xl px-3 py-3 text-left text-sm" data-page="overview">▦ Ringkasan Dashboard</button>
<p class="px-3 pb-2 pt-5 text-[10px] font-bold uppercase tracking-[.2em] text-white/35">Manajemen Konten</p>

<button class="nav-btn flex w-full items-center gap-3 rounded-xl px-3 py-3 text-left text-sm" data-page="api-list">▤ Daftar Semua API</button>
<button class="nav-btn flex w-full items-center gap-3 rounded-xl px-3 py-3 text-left text-sm" data-page="api-status">⌁ Status API</button>
</nav>
<div class="border-t border-white/10 p-4"><div class="mb-3 flex items-center gap-3 rounded-xl bg-white/5 p-3"><div class="grid h-9 w-9 place-items-center rounded-full bg-[#4A00E0] font-bold">A</div><div class="min-w-0"><p class="truncate text-sm font-semibold"><?= htmlspecialchars((string)$displayName, ENT_QUOTES, 'UTF-8') ?></p><p class="text-xs text-white/40">Administrator</p></div></div><a href="logout.php" class="block rounded-xl px-3 py-3 text-sm text-red-200 hover:bg-red-500/10">↪ Keluar</a></div>
</aside>
<div id="sidebarBackdrop" class="fixed inset-0 z-30 hidden bg-black/60 lg:hidden"></div>
<main class="min-w-0 flex-1 lg:ml-72">
<header class="sticky top-0 z-20 flex h-20 items-center justify-between border-b border-white/10 bg-[#170d20]/90 px-4 backdrop-blur-xl sm:px-8">
<div class="flex items-center gap-3"><button id="openSidebar" class="rounded-xl border border-white/10 px-3 py-2 lg:hidden">☰</button><div><p id="pageEyebrow" class="text-xs text-[#c99a2e]">WORKSPACE</p><h1 id="pageTitle" class="text-lg font-bold sm:text-xl">Ringkasan Dashboard</h1></div></div>
<div class="flex items-center gap-3"><span id="apiBadge" class="tag">Memeriksa API...</span><a href="../" target="_blank" class="hidden rounded-xl border border-white/10 px-3 py-2 text-sm text-white/70 hover:text-white sm:block">Lihat Website ↗</a></div>
</header>
<div class="p-4 sm:p-8"><div id="toast" class="mb-5 hidden rounded-xl border p-4 text-sm"></div><section id="view"></section><footer class="mt-10 border-t border-white/10 py-5 text-xs text-white/35">REI CMS Admin · PHP Native + Tailwind CSS · <span id="footerYear"></span></footer></div>
</main>
</div>
<div id="modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/70 p-4"><div class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl border border-white/10 bg-[#261535] p-6"><div class="mb-5 flex items-center justify-between"><h2 id="modalTitle" class="text-xl font-bold">Form</h2><button id="modalClose" class="rounded-lg px-3 py-2 text-white/60 hover:bg-white/10">✕</button></div><form id="recordForm" class="space-y-4"><div id="formFields" class="grid gap-4 sm:grid-cols-2"></div><div class="flex justify-end gap-3 pt-3"><button type="button" id="cancelForm" class="btn-muted">Batal</button><button class="btn-gold" type="submit">Simpan</button></div></form></div></div>
<script>window.REI_ADMIN_CONFIG={apiBase:<?= json_encode(REI_API_BASE, JSON_UNESCAPED_SLASHES) ?>};</script>
<script src="assets/admin.js"></script>
</body></html>
