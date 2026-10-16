\# REI CMS v1 — Admin \& API



Sistem manajemen konten Raja Emas Indonesia berbasis PHP dan MySQL.



\## 1. Persyaratan



\- PHP 8.3 atau kompatibel

\- MySQL/MariaDB

\- Laragon atau web server Apache/Nginx

\- Browser modern

\- Git (opsional)



\## 2. Cara Menjalankan Project



1\. Letakkan project di folder `C:\\laragon\\www\\rei`.

2\. Jalankan Apache dan MySQL melalui Laragon.

3\. Buat database MySQL untuk project.

4\. Impor file database SQL yang disediakan secara lokal jika tersedia dan sudah diperiksa.

5\. Sesuaikan konfigurasi database pada `config.php` dan konfigurasi terkait.

6\. Buka website melalui browser:



&#x20;  `http://localhost/rei/`



Pastikan konfigurasi database dan struktur tabel sudah sesuai sebelum digunakan.



\## 3. Cara Membuka Admin



Buka alamat:



`http://localhost/rei/admin/`



Jika halaman admin tidak terbuka, periksa struktur folder, konfigurasi Apache, dan file `admin/index.php`.



Masuk menggunakan akun admin yang telah dibuat pada database atau melalui mekanisme autentikasi project.



\*\*Keamanan:\*\* Jangan menggunakan password default atau membagikan kredensial admin.



\## 4. Penggunaan Dashboard Admin



Menu yang tersedia bergantung pada implementasi project:



\- \*\*Dashboard:\*\* melihat ringkasan statistik.

\- \*\*Post:\*\* mengelola artikel.

\- \*\*Halaman:\*\* membuat dan mengedit halaman website.

\- \*\*Menu:\*\* mengelola navigasi website.

\- \*\*Slider:\*\* mengelola gambar slider.

\- \*\*Cabang Toko:\*\* mengelola informasi cabang.

\- \*\*Tema:\*\* mengatur tampilan website.

\- \*\*Logout:\*\* mengakhiri sesi admin.



\### Pengelolaan cabang



1\. Buka menu Cabang Toko.

2\. Tambahkan atau edit informasi cabang.

3\. Lengkapi nomor cabang, judul, alamat, telepon, jam operasional, dan informasi peta sesuai kolom yang tersedia.

4\. Simpan perubahan.

5\. Periksa hasilnya pada website.



Nama kolom dan fitur dapat berbeda sesuai versi implementasi.



\## 5. API REI CMS



Folder endpoint API berada di:



`/api/`



Contoh alamat lokal:



`http://localhost/rei/api/health.php`



Gunakan endpoint health untuk memeriksa apakah API dapat diakses, sesuai implementasi yang tersedia.



\### Daftar endpoint



| Endpoint | Kegunaan |

|---|---|

| `api/health.php` | Pemeriksaan status API |

| `api/auth.php` | Autentikasi |

| `api/branches.php` | Data cabang |

| `api/categories.php` | Kategori |

| `api/posts.php` | Artikel |

| `api/pages.php` | Halaman |

| `api/menus.php` | Navigasi |

| `api/sliders.php` | Data slider |

| `api/media.php` | Media |

| `api/upload-media.php` | Unggah media |

| `api/dashboard.php` | Data dashboard |

| `api/dashboard-stats.php` | Statistik dashboard |

| `api/daily-visitors.php` | Statistik pengunjung |

| `api/settings.php` | Pengaturan |

| `api/theme-settings.php` | Pengaturan tema |

| `api/slider-settings.php` | Pengaturan slider |



Metode HTTP, parameter, format respons, dan autentikasi setiap endpoint harus mengikuti kode implementasi masing-masing.



\## 6. Cara Menguji API



Gunakan Postman, Insomnia, atau `curl`.



Contoh pemeriksaan endpoint health:



```bash

curl -i http://localhost/rei/api/health.php

```



Periksa kode status HTTP dan respons JSON atau teks yang dikembalikan.



Untuk endpoint yang memerlukan autentikasi, lakukan login sesuai implementasi API dan kirim kredensial atau token melalui mekanisme yang memang didukung oleh endpoint tersebut.



Jangan mengirim password melalui URL atau membagikan token autentikasi.



\## 7. Keamanan API



\- Gunakan autentikasi untuk endpoint administratif.

\- Validasi input dari semua permintaan.

\- Gunakan prepared statements untuk query SQL.

\- Batasi ukuran dan tipe file unggahan.

\- Lindungi file konfigurasi dan kredensial.

\- Gunakan HTTPS pada server produksi.

\- Jangan publikasikan password, token API, atau data pribadi.

\- Hindari menampilkan detail error internal pada server produksi.



\## 8. Troubleshooting



\### Admin tidak bisa dibuka

Periksa Apache, alamat URL, struktur folder, dan log PHP.



\### Koneksi database gagal

Periksa nama database, host, username, password, serta apakah MySQL berjalan.



\### API mengembalikan 401 atau 403

Periksa autentikasi, token, izin akses, dan aturan `.htaccess`.



\### API mengembalikan 404

Pastikan nama file endpoint dan URL sesuai struktur project.



\### Data tidak tersimpan

Periksa validasi input, struktur tabel, hak akses database, dan log PHP.



\## 9. Repository



GitHub: https://github.com/andri00nugroho-wq/rei-cms-v1-admin



\## 10. Pengembangan



Setelah mengubah kode, jalankan pengujian lokal sebelum melakukan commit dan push ke GitHub.



\## 11. Database SQL



REI CMS menggunakan MySQL/MariaDB untuk menyimpan data website, akun admin, artikel, halaman, menu, slider, dan cabang toko.



\### A. Membuat database



1\. Jalankan Apache dan MySQL melalui Laragon.

2\. Buka phpMyAdmin: `http://localhost/phpmyadmin/`.

3\. Buat database baru bernama `rei\_cms\_v1`.

4\. Gunakan collation `utf8mb4\_unicode\_ci` jika tersedia.



Alternatif melalui SQL:



```sql

CREATE DATABASE rei\_cms\_v1

CHARACTER SET utf8mb4

COLLATE utf8mb4\_unicode\_ci;

```



\### B. Mengimpor database



Jika tersedia file dump database yang sudah diperiksa dan aman:



1\. Buka phpMyAdmin.

2\. Pilih database `rei\_cms\_v1`.

3\. Klik tab \*\*Import\*\*.

4\. Pilih file `.sql` dari komputer.

5\. Klik \*\*Import\*\* atau \*\*Go\*\*.

6\. Pastikan tabel berhasil dibuat.



Jangan mengunggah dump yang berisi password, token, atau data pribadi ke repository publik.



\### C. Konfigurasi koneksi PHP



Periksa file konfigurasi database pada project, misalnya `config.php`.



Sesuaikan host, nama database, username, dan password dengan konfigurasi MySQL lokal.



Contoh konfigurasi PDO:



```php

<?php

$host = '127.0.0.1';

$dbname = 'rei\_cms\_v1';

$username = 'root';

$password = '';



$dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";



$options = \[

&#x20;   PDO::ATTR\_ERRMODE => PDO::ERRMODE\_EXCEPTION,

&#x20;   PDO::ATTR\_DEFAULT\_FETCH\_MODE => PDO::FETCH\_ASSOC,

&#x20;   PDO::ATTR\_EMULATE\_PREPARES => false,

];



$pdo = new PDO($dsn, $username, $password, $options);

```



Contoh tersebut hanya untuk referensi. Sesuaikan dengan struktur konfigurasi project dan jangan menimpa konfigurasi yang sudah berfungsi tanpa membuat cadangan.



\### D. Memeriksa tabel



Setelah impor, jalankan di phpMyAdmin:



```sql

SHOW TABLES;

```



Untuk melihat struktur tabel tertentu:



```sql

DESCRIBE branches;

```



Perintah kedua hanya berlaku jika tabel `branches` tersedia.



\### E. Backup database



Untuk membuat cadangan:



1\. Buka phpMyAdmin.

2\. Pilih database `rei\_cms\_v1`.

3\. Klik \*\*Export\*\*.

4\. Pilih format SQL.

5\. Simpan file hasil ekspor di lokasi aman di luar folder publik website.



\### F. Troubleshooting SQL



\- \*\*Access denied:\*\* periksa username, password, dan hak akses database.

\- \*\*Unknown database:\*\* pastikan database sudah dibuat.

\- \*\*Table doesn't exist:\*\* periksa apakah file SQL sudah diimpor dengan benar.

\- \*\*Duplicate entry:\*\* periksa data yang sudah ada sebelum mengimpor ulang.

\- \*\*Unknown column:\*\* sesuaikan struktur tabel dengan kode aplikasi.



Jangan menjalankan `DROP DATABASE` atau menghapus tabel produksi tanpa backup dan konfirmasi yang jelas.

