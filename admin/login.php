<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

if (admin_logged_in()) {
    header('Location: index.php');
    exit;
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Login Admin — Raja Emas Indonesia</title>
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config={theme:{extend:{colors:{rei:{950:'#1b1026',900:'#261535',800:'#352044',700:'#4A00E0',gold:'#c99a2e'}}}}}</script>
</head>
<body class="min-h-screen bg-[#170d20] text-white">
<main class="grid min-h-screen lg:grid-cols-2">
<section class="hidden lg:flex flex-col justify-between bg-gradient-to-br from-[#4A00E0] via-[#352044] to-[#170d20] p-12">
  <div class="flex items-center gap-3"><div class="grid h-12 w-12 place-items-center rounded-2xl bg-[#c99a2e] font-black text-[#24132c]">REI</div><div><p class="font-bold tracking-wide">RAJA EMAS INDONESIA</p><p class="text-sm text-white/60">Content Management System</p></div></div>
  <div><p class="mb-4 text-sm font-semibold uppercase tracking-[.25em] text-[#e9c66d]">Admin workspace</p><h1 class="max-w-xl text-5xl font-bold leading-tight">Kelola website REI dari satu dashboard.</h1><p class="mt-5 max-w-lg text-white/70">Konten, cabang toko, media, tampilan, dan statistik dalam satu tempat.</p></div>
  <p class="text-xs text-white/50">© <?= date('Y') ?> Raja Emas Indonesia</p>
</section>
<section class="flex items-center justify-center p-6 sm:p-10">
  <div class="w-full max-w-md">
    <div class="mb-8 flex items-center gap-3 lg:hidden"><div class="grid h-11 w-11 place-items-center rounded-xl bg-[#c99a2e] font-black text-[#24132c]">REI</div><div><p class="font-bold">RAJA EMAS INDONESIA</p><p class="text-xs text-white/50">Admin CMS</p></div></div>
    <div class="rounded-3xl border border-white/10 bg-white/[.04] p-7 shadow-2xl sm:p-9">
      <p class="text-sm font-semibold text-[#e9c66d]">SELAMAT DATANG</p><h2 class="mt-2 text-3xl font-bold">Login Admin</h2><p class="mt-2 text-sm text-white/55">Masuk menggunakan akun admin REI CMS.</p>
      <form id="loginForm" class="mt-8 space-y-5">
        <div><label for="username" class="mb-2 block text-sm text-white/80">Username</label><input id="username" name="username" autocomplete="username" required class="w-full rounded-xl border border-white/10 bg-[#1e1427] px-4 py-3 outline-none focus:border-[#c99a2e]" placeholder="Masukkan username"></div>
        <div><label for="password" class="mb-2 block text-sm text-white/80">Password</label><input id="password" name="password" type="password" autocomplete="current-password" required class="w-full rounded-xl border border-white/10 bg-[#1e1427] px-4 py-3 outline-none focus:border-[#c99a2e]" placeholder="Masukkan password"></div>
        <p id="loginError" class="hidden rounded-lg border border-red-400/20 bg-red-400/10 p-3 text-sm text-red-200"></p>
        <button id="loginButton" class="w-full rounded-xl bg-[#c99a2e] px-4 py-3 font-bold text-[#24132c] transition hover:bg-[#e0b94e] disabled:opacity-60">Masuk ke Dashboard</button>
      </form>
      <p class="mt-6 text-center text-xs text-white/35">Akses terbatas untuk administrator.</p>
    </div>
  </div>
</section>
</main>
<script>
const form=document.getElementById('loginForm'), errorBox=document.getElementById('loginError'), button=document.getElementById('loginButton');
form.addEventListener('submit',async e=>{
 e.preventDefault(); errorBox.classList.add('hidden'); button.disabled=true; button.textContent='Memeriksa akun...';
 try{
  const response=await fetch('../api/auth.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','Accept':'application/json'},body:JSON.stringify({username:form.username.value.trim(),password:form.password.value})});
  const data=await response.json();
  if(!response.ok || data.success===false) throw new Error(data.message||'Username atau password tidak sesuai.');
  window.location.href='index.php';
 }catch(err){errorBox.textContent=err.message||'Login gagal. Periksa koneksi API.';errorBox.classList.remove('hidden');}
 finally{button.disabled=false;button.textContent='Masuk ke Dashboard';}
});
</script>
</body></html>
