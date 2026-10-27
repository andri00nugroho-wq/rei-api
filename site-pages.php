<?php
/*
 * Halaman bawaan website.
 *
 * Setiap halaman di sini terhubung ke satu baris di tabel posts (type = 'page'),
 * sehingga bisa diatur dari menu Page di dashboard:
 *   - Judul        -> label di navigasi dan judul tab browser
 *   - Konten       -> teks pembuka di bagian atas halaman
 *   - Gambar       -> banner di bawah bagian pembuka
 *   - HTML kustom  -> jika diisi, menggantikan seluruh isi halaman bawaan
 *   - CSS/JS kustom -> disisipkan ke halaman
 *
 * "aliases" adalah slug lama yang masih dipakai data atau proyek lain (rei-cms),
 * jadi slug itu dikenali tanpa diubah.
 */

const REI_SITE_PAGES = [
    'home' => [
        'title' => 'Beranda',
        'icon' => 'home',
        'aliases' => ['beranda'],
        'intro' => 'Dapatkan penawaran terbaik disertai pengecekan keaslian emas dengan teknologi XRF gratis.',
    ],
    'about-us' => [
        'title' => 'Tentang Kami',
        'icon' => 'business',
        'aliases' => ['tentang-kami'],
        'intro' => 'Jaringan toko emas yang membantu masyarakat menjual emas, membeli logam mulia, dan memahami nilai emasnya dengan proses yang jelas, aman, dan profesional.',
    ],
    'service' => [
        'title' => 'Layanan',
        'icon' => 'diamond',
        'aliases' => ['layanan'],
        'intro' => 'Raja Emas Indonesia membantu Anda menjual emas, memeriksa keaslian dan kadar dengan teknologi XRF, serta mendapatkan informasi produk langsung dari outlet resmi.',
    ],
    'cabang' => [
        'title' => 'Cabang',
        'icon' => 'storefront',
        'aliases' => ['branch'],
        'intro' => 'Pilih cabang untuk melihat informasi alamat, jam operasional, kontak, dan petunjuk arah. Silakan hubungi cabang terlebih dahulu untuk memastikan informasi kunjungan.',
    ],
    'blog' => [
        'title' => 'Blog',
        'icon' => 'auto_stories',
        'aliases' => [],
        'intro' => 'Temukan artikel, informasi, dan inspirasi terbaru dari Raja Emas Indonesia.',
    ],
    'contact' => [
        'title' => 'Kontak',
        'icon' => 'chat',
        'aliases' => ['kontak'],
        'intro' => 'Punya pertanyaan tentang koleksi, layanan, atau rencana berkunjung? Hubungi cabang Raja Emas Indonesia yang paling sesuai untuk Anda.',
    ],
];

/** Mengembalikan key halaman bawaan untuk slug (termasuk alias), atau null. */
function rei_site_page_key(?string $slug): ?string
{
    $slug = strtolower(trim((string) $slug));
    if ($slug === '') {
        return null;
    }
    if (isset(REI_SITE_PAGES[$slug])) {
        return $slug;
    }
    foreach (REI_SITE_PAGES as $key => $def) {
        if (in_array($slug, $def['aliases'], true)) {
            return $key;
        }
    }
    return null;
}

/** Semua slug (utama + alias) yang dipakai halaman bawaan. */
function rei_site_page_slugs(): array
{
    $slugs = [];
    foreach (REI_SITE_PAGES as $key => $def) {
        $slugs[] = $key;
        array_push($slugs, ...$def['aliases']);
    }
    return $slugs;
}

/**
 * Mengambil baris database untuk tiap halaman bawaan.
 * Hasil: [key => row|null]. Slug utama didahulukan dari alias.
 */
function rei_load_site_pages(PDO $pdo): array
{
    $result = array_fill_keys(array_keys(REI_SITE_PAGES), null);
    $slugs = rei_site_page_slugs();

    try {
        $placeholders = implode(',', array_fill(0, count($slugs), '?'));
        $stmt = $pdo->prepare("SELECT * FROM posts WHERE type = 'page' AND slug IN ($placeholders)");
        $stmt->execute($slugs);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return $result;
    }

    foreach ($rows as $row) {
        $key = rei_site_page_key($row['slug']);
        if ($key === null) {
            continue;
        }
        if ($result[$key] === null || $row['slug'] === $key) {
            $result[$key] = $row;
        }
    }
    return $result;
}

/**
 * Membuat baris database untuk halaman bawaan yang belum ada,
 * diisi judul dan teks pembuka bawaan. Data yang sudah ada tidak diubah.
 */
function rei_ensure_site_pages(PDO $pdo): array
{
    $pages = rei_load_site_pages($pdo);

    foreach ($pages as $key => $row) {
        if ($row !== null) {
            continue;
        }
        $def = REI_SITE_PAGES[$key];
        try {
            $stmt = $pdo->prepare("INSERT INTO posts (title, slug, content, type) VALUES (?, ?, ?, 'page')");
            $stmt->execute([$def['title'], $key, '<p>' . htmlspecialchars($def['intro'], ENT_QUOTES, 'UTF-8') . '</p>']);
        } catch (PDOException $e) {
            // Slug dipakai baris lain (misalnya type = 'post'); biarkan memakai teks bawaan.
        }
    }

    return rei_load_site_pages($pdo);
}

/** Judul halaman bawaan: dari dashboard jika diisi, jika tidak memakai bawaan. */
function rei_site_page_title(string $key, ?array $row): string
{
    $title = trim((string) ($row['title'] ?? ''));
    return $title !== '' ? $title : REI_SITE_PAGES[$key]['title'];
}

/** HTML teks pembuka: konten dari dashboard jika ada isinya, jika tidak memakai bawaan. */
function rei_site_page_intro(string $key, ?array $row): string
{
    $content = (string) ($row['content'] ?? '');
    if (trim(strip_tags($content, '<img>')) !== '') {
        return $content;
    }
    return '<p>' . htmlspecialchars(REI_SITE_PAGES[$key]['intro'], ENT_QUOTES, 'UTF-8') . '</p>';
}
