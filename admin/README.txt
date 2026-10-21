REI CMS ADMIN DASHBOARD — PHP NATIVE + TAILWIND CSS

INSTALASI
1. Salin folder admin ke:
   C:\laragon\www\rei\admin

2. Pastikan API tetap berada di:
   C:\laragon\www\rei\api

3. Buka:
   https://rei-api.rajaemas.dev/rei/admin/login.php

4. Login dengan akun admin yang sudah digunakan oleh api/auth.php.

KONFIGURASI PATH
- admin/config.php menggunakan REI_ADMIN_BASE = /rei.
- Jika nama folder atau base URL berbeda, sesuaikan konstanta tersebut.
- API base otomatis menjadi /rei/api.

API YANG DIGUNAKAN
auth.php, dashboard.php, posts.php, pages.php, categories.php, branches.php,
menus.php, sliders.php, media.php, upload-media.php, theme-settings.php,
slider-settings.php, settings.php, health.php, dashboard-stats.php, daily-visitors.php.

CATATAN PENTING
- Dashboard tidak membuat endpoint API baru.
- Operasi CRUD menggunakan pola umum GET, POST, PUT ?id=ID, DELETE ?id=ID.
- API Anda mungkin menggunakan parameter atau struktur respons yang berbeda.
  Jika data tidak tampil atau penyimpanan gagal, sesuaikan pemanggilan endpoint
  di admin/assets/admin.js dengan kontrak API yang sudah ada.
- Halaman PHP dilindungi sesi admin_user atau admin_logged_in.
- Setiap endpoint penulisan/penghapusan API tetap harus memvalidasi autentikasi
  sendiri di server. Proteksi halaman tidak otomatis mengamankan endpoint API.
- Tailwind dan Chart.js menggunakan CDN untuk kemudahan development. Untuk
  produksi, gunakan build lokal dan aktifkan HTTPS.
