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

