<?php
declare(strict_types=1);

/**
 * Scraper dan sinkronisasi cabang Raja Emas Indonesia.
 *
 * CLI:
 *   php scrape-branches.php
 *
 * Dari halaman admin (setelah cek login + CSRF):
 *   require_once __DIR__ . '/scrape-branches.php';
 *   $summary = runSync();   // array ringkasan, tidak mencetak apa pun
 *
 * Alur: ambil HTML langsung dari SOURCE_URL -> parse -> tulis ke DB.
 * Tidak ada file debug. Cabang di DB yang sudah tidak ada di website
 * akan dihapus (lihat pengaman di bagian 12).
 */

// =====================================================
// 1. KONFIGURASI
// =====================================================

const SOURCE_URL = 'https://rajaemasindonesia.co.id/#lokasi';

// Sesuaikan dengan konfigurasi database Laragon Anda.
const DB_HOST = '127.0.0.1';
const DB_PORT = '3306';
const DB_NAME = 'rei_cms_v1';
const DB_USER = 'radmin_cms';
const DB_PASS = 'M@juterusrajaemas';

const DB_TABLE = 'branches';

// Hapus cabang di DB yang sudah tidak ada di website.
const DELETE_MISSING = true;

// Pengaman: jika hasil parsing kurang dari rasio ini dibanding jumlah
// cabang di DB (dan DB berisi minimal DELETE_GUARD_MIN_ROWS baris),
// penghapusan dibatalkan karena kemungkinan besar parsing/halaman gagal.
const DELETE_MIN_KEEP_RATIO = 0.5;
const DELETE_GUARD_MIN_ROWS = 4;

// Kolom yang TIDAK boleh dikosongkan jika hasil scraping kosong.
// Kolom lain (phone, hours, notes) mengikuti website, termasuk jika dihapus di sana.
const PRESERVE_WHEN_EMPTY = ['title', 'address', 'map_embed_url', 'map_link'];

// =====================================================
// 2. KONEKSI DATABASE
// =====================================================

function connectDatabase(): PDO
{
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        DB_HOST,
        DB_PORT,
        DB_NAME
    );

    return new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
}

// =====================================================
// 3. AMBIL HTML LANGSUNG DARI WEBSITE
// =====================================================

function fetchWebsite(string $url, callable $log): string
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException(
            'Ekstensi cURL belum aktif. Aktifkan extension=curl di php.ini Laragon.'
        );
    }

    // Fragment (#lokasi) tidak pernah dikirim ke server, jadi dibuang.
    $cleanUrl = preg_replace('/#.*$/', '', $url) ?? $url;

    // Cache-buster agar tidak mengambil versi cache dari CDN/plugin cache.
    $separator = str_contains($cleanUrl, '?') ? '&' : '?';
    $requestUrl = $cleanUrl . $separator . '_=' . time();

    $ch = curl_init($requestUrl);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_ENCODING       => '',
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/130 Safari/537.36',
        CURLOPT_HTTPHEADER     => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: id-ID,id;q=0.9,en;q=0.8',
            'Cache-Control: no-cache, no-store, max-age=0',
            'Pragma: no-cache',
        ],
    ]);

    $html = curl_exec($ch);
    $error = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $finalUrl = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);

    curl_close($ch);

    $log("URL sumber : {$url}");
    $log("URL akhir  : {$finalUrl}");
    $log("HTTP       : {$httpCode}");

    if ($html === false || $html === '') {
        throw new RuntimeException('Gagal mengambil HTML website. ' . $error);
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        throw new RuntimeException("Website mengembalikan HTTP {$httpCode}.");
    }

    return (string) $html;
}

// =====================================================
// 4. BANTUAN PEMROSESAN TEKS
// =====================================================

function normalizeText(string $text): string
{
    $text = html_entity_decode(
        $text,
        ENT_QUOTES | ENT_HTML5,
        'UTF-8'
    );

    $text = str_replace(
        ["\xc2\xa0", "\u{200B}", "\u{FEFF}"],
        ' ',
        $text
    );

    $text = preg_replace('/[\t ]+/u', ' ', $text) ?? $text;
    $text = preg_replace('/\s*\n\s*/u', "\n", $text) ?? $text;

    return trim($text);
}

/**
 * Mendapatkan nomor dari format:
 * # 01 Jakarta Utara
 * 01 Jakarta Utara
 * Cabang 01
 * 1. Jakarta Utara
 */
function extractBranchNumber(string $text): string
{
    $text = normalizeText($text);

    if (preg_match(
        '/^\s*(?:#\s*)?(?:cabang\s*)?0*(\d{1,3})(?=\s|[-.:]|$)/iu',
        $text,
        $matches
    )) {
        $number = (int) $matches[1];

        return $number > 0 ? (string) $number : '';
    }

    return '';
}

function removeBranchNumber(string $text): string
{
    $text = normalizeText($text);

    $text = preg_replace(
        '/^\s*(?:#\s*)?(?:cabang\s*)?0*\d{1,3}\s*(?:[-.:]\s*)?/iu',
        '',
        $text
    ) ?? $text;

    return trim($text, " \t\n\r\0\x0B-:.");
}

function containsBranchNumber(string $text): bool
{
    return extractBranchNumber($text) !== '';
}

/**
 * Penanda cabang yang ketat. Dipakai untuk mendeteksi nomor cabang
 * dan menghitung penanda dalam sebuah container.
 *
 * Lolos:
 *   "# 01 Jakarta Utara", "# 1", "01 Jakarta Utara",
 *   "Cabang 01 Bekasi", "1. Surabaya", "01" (nomor berawalan nol berdiri sendiri)
 * Ditolak:
 *   teks panjang, "24 jam", "1 gram", "08.00 - 17.00", nomor telepon
 */
function isBranchMarkerText(string $text): bool
{
    $text = normalizeText($text);

    if ($text === '' || mb_strlen($text, 'UTF-8') > 80) {
        return false;
    }

    // Format "# 01" dengan atau tanpa nama.
    if (preg_match('/^\s*#\s*\d{1,3}(?=\s|[-.:]|$)/u', $text)) {
        return true;
    }

    // Nomor berawalan nol yang berdiri sendiri, mis. "01".
    if (preg_match('/^\s*0\d{1,2}\s*$/u', $text)) {
        return true;
    }

    // Nomor diikuti nama (minimal 3 huruf) yang bukan satuan umum.
    return preg_match(
        '/^\s*(?:cabang\s*)?\d{1,3}(?=\s|[-.:])\s*[-.:]?\s*'
        . '(?!(?:jam|gram|gr|kg|kilo|hari|tahun|bulan|menit|detik|persen|karat|wib|wita|wit)\b)'
        . '\p{L}{3,}/iu',
        $text
    ) === 1;
}

// =====================================================
// 5. MEMBENTUK BARIS TEKS DARI ELEMEN HTML
// =====================================================

function collectTextLines(DOMNode $node): array
{
    $lines = [];

    $walk = function (DOMNode $current) use (&$walk, &$lines): void {
        if ($current instanceof DOMText) {
            $text = normalizeText($current->nodeValue ?? '');

            if ($text !== '') {
                $lines[] = $text;
            }

            return;
        }

        if (!$current instanceof DOMElement) {
            foreach ($current->childNodes as $child) {
                $walk($child);
            }

            return;
        }

        $tag = strtolower($current->tagName);

        if (in_array($tag, [
            'script', 'style', 'noscript', 'svg',
            'path', 'iframe',
        ], true)) {
            return;
        }

        $blockTags = [
            'address', 'article', 'aside', 'blockquote',
            'br', 'dd', 'div', 'dl', 'dt', 'fieldset',
            'figcaption', 'figure', 'footer', 'form',
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
            'header', 'li', 'main', 'ol', 'p', 'section',
            'table', 'td', 'th', 'tr', 'ul',
        ];

        $isBlock = in_array($tag, $blockTags, true);

        if ($isBlock) {
            $lines[] = "\n";
        }

        foreach ($current->childNodes as $child) {
            $walk($child);
        }

        if ($isBlock) {
            $lines[] = "\n";
        }
    };

    $walk($node);

    $text = implode(' ', $lines);
    $text = str_replace(["\r", "\t"], ' ', $text);
    $text = preg_replace('/[ ]*\n[ ]*/u', "\n", $text) ?? $text;
    $text = preg_replace('/\n{2,}/u', "\n", $text) ?? $text;

    $result = [];

    foreach (explode("\n", $text) as $line) {
        $line = normalizeText($line);

        if ($line !== '') {
            $result[] = $line;
        }
    }

    return array_values(array_unique($result));
}

// =====================================================
// 6. MEMBACA ATRIBUT HREF BERDASARKAN TEKS TAUTAN
// =====================================================

function findLinkByText(DOMXPath $xpath, DOMNode $container, array $keywords): string
{
    $links = $xpath->query('.//a[@href]', $container);

    if ($links === false) {
        return '';
    }

    foreach ($links as $link) {
        if (!$link instanceof DOMElement) {
            continue;
        }

        $label = normalizeText($link->textContent);
        $labelLower = mb_strtolower($label, 'UTF-8');

        foreach ($keywords as $keyword) {
            if (str_contains($labelLower, mb_strtolower($keyword, 'UTF-8'))) {
                $href = trim($link->getAttribute('href'));

                if ($href === '' || $href === '#') {
                    continue;
                }

                // Ubah URL relatif menjadi URL absolut.
                if (str_starts_with($href, '//')) {
                    return 'https:' . $href;
                }

                if (preg_match('/^https?:\/\//i', $href)) {
                    return $href;
                }

                if (str_starts_with($href, '/')) {
                    return 'https://rajaemasindonesia.co.id' . $href;
                }

                return 'https://rajaemasindonesia.co.id/' . $href;
            }
        }
    }

    return '';
}

// =====================================================
// 7. MENDETEKSI NOMOR CABANG PADA BERBAGAI TAG HTML
// =====================================================

function directTextOf(DOMElement $element): string
{
    $directText = '';

    foreach ($element->childNodes as $child) {
        if ($child instanceof DOMText) {
            $directText .= ' ' . $child->nodeValue;
        }
    }

    return normalizeText($directText);
}

function findNumberNodes(DOMXPath $xpath): array
{
    $nodes = $xpath->query('//*[text()[normalize-space()]]');

    if ($nodes === false) {
        return [];
    }

    $results = [];

    foreach ($nodes as $node) {
        if (!$node instanceof DOMElement) {
            continue;
        }

        $tag = strtolower($node->tagName);

        if (in_array($tag, ['script', 'style', 'noscript', 'title', 'option'], true)) {
            continue;
        }

        // Nomor harus berada pada teks langsung elemen ini,
        // bukan sekadar teks dari elemen anak.
        $directText = directTextOf($node);

        if ($directText === '' || !isBranchMarkerText($directText)) {
            continue;
        }

        $number = extractBranchNumber($directText);

        if ($number === '') {
            continue;
        }

        $results[] = [
            'node' => $node,
            'number' => $number,
            'text' => $directText,
        ];
    }

    return $results;
}

// =====================================================
// 8. MEMILIH KARTU CABANG
// =====================================================

function countNumberMarkers(DOMXPath $xpath, DOMNode $node): int
{
    $count = 0;
    $all = $xpath->query('.//*[text()[normalize-space()]]', $node);

    if ($all !== false) {
        foreach ($all as $child) {
            if (!$child instanceof DOMElement) {
                continue;
            }

            if (isBranchMarkerText(directTextOf($child))) {
                $count++;
            }
        }
    }

    return $count;
}

/**
 * Naik dari penanda nomor ke ancestor terbesar yang masih hanya
 * memuat SATU penanda cabang (kartu cabang itu sendiri).
 */
function findBranchContainer(DOMXPath $xpath, DOMNode $numberNode): DOMNode
{
    $current = $numberNode;
    $best = $numberNode;

    for ($level = 0; $level < 10 && $current instanceof DOMNode; $level++) {
        if ($current instanceof DOMElement) {
            // Sudah mencakup cabang lain: berhenti, pakai yang sebelumnya.
            if (countNumberMarkers($xpath, $current) > 1) {
                break;
            }

            // Batasi ukuran supaya tidak memilih seluruh bagian lokasi.
            if (mb_strlen(normalizeText($current->textContent), 'UTF-8') > 5000) {
                break;
            }

            $best = $current;
        }

        $current = $current->parentNode;
    }

    return $best;
}

// =====================================================
// 9. EKSTRAKSI INFORMASI CABANG
// =====================================================

function extractBranchTitle(
    DOMXPath $xpath,
    DOMNode $container,
    string $markerText
): string {
    $title = removeBranchNumber($markerText);

    if ($title !== '') {
        return $title;
    }

    $headings = $xpath->query(
        './/h1 | .//h2 | .//h3 | .//h4 | .//h5 | .//h6',
        $container
    );

    if ($headings !== false) {
        foreach ($headings as $heading) {
            $text = normalizeText($heading->textContent);

            if ($text !== '' && !containsBranchNumber($text)) {
                return $text;
            }

            $cleaned = removeBranchNumber($text);

            if ($cleaned !== '') {
                return $cleaned;
            }
        }
    }

    return '';
}

function extractPhone(array $lines): string
{
    foreach ($lines as $line) {
        if (preg_match(
            '/(?:\+62|62|0)\s*[\d\s().-]{7,}/u',
            $line,
            $matches
        )) {
            $phone = trim($matches[0]);

            // Jangan mengambil angka cabang sebagai nomor telepon.
            $digits = preg_replace('/\D+/', '', $phone) ?? '';

            if (strlen($digits) >= 9) {
                return $phone;
            }
        }
    }

    return '';
}

function extractHours(array $lines): string
{
    foreach ($lines as $line) {
        if (
            preg_match('/\b(jam operasional|operasional|buka|senin|minggu)\b/iu', $line)
            && preg_match('/\d{1,2}[.:]\d{2}/u', $line)
        ) {
            return $line;
        }
    }

    return '';
}

function extractAddress(array $lines): string
{
    $addressCandidates = [];

    foreach ($lines as $line) {
        $line = normalizeText($line);

        if ($line === '' || mb_strlen($line, 'UTF-8') < 8) {
            continue;
        }

        // Abaikan baris yang hanya berisi nomor cabang.
        if (preg_match('/^\s*#?\s*\d{1,3}\s*$/u', $line)) {
            continue;
        }

        // Abaikan tautan atau label umum.
        if (preg_match(
            '/^(map|menuju lokasi|petunjuk arah|lihat peta|hubungi kami)$/iu',
            $line
        )) {
            continue;
        }

        // Hindari baris nomor telepon dan jam operasional.
        if (preg_match('/(?:\+62|62|08\d{1,3})[\d\s().-]{6,}/u', $line)) {
            continue;
        }

        if (preg_match('/\d{1,2}[.:]\d{2}/u', $line)) {
            continue;
        }

        if (preg_match(
            '/\b(jalan|jl\.?|rukan|ruko|mall|mal|lantai|blok|komplek|kompleks|kawasan|gedung|plaza|no\.?)\b/iu',
            $line
        )) {
            $addressCandidates[] = $line;
        }
    }

    if ($addressCandidates === []) {
        return '';
    }

    // Gabungkan potongan alamat berurutan dan hindari pengulangan.
    return implode(', ', array_values(array_unique($addressCandidates)));
}

function extractNotes(array $lines): string
{
    foreach ($lines as $line) {
        if (preg_match('/^(catatan|keterangan|notes)\s*[:\-]\s*(.+)$/iu', $line, $m)) {
            return trim($m[2]);
        }
    }

    return '';
}

// =====================================================
// 10. PARSING SEMUA CABANG
// =====================================================

function parseBranches(string $html, callable $log): array
{
    if (!class_exists(DOMDocument::class)) {
        throw new RuntimeException(
            'Ekstensi PHP DOM belum aktif. Aktifkan extension=dom di php.ini.'
        );
    }

    libxml_use_internal_errors(true);

    $dom = new DOMDocument('1.0', 'UTF-8');

    $loaded = $dom->loadHTML(
        '<?xml encoding="UTF-8">' . $html,
        LIBXML_NOWARNING | LIBXML_NOERROR
    );

    libxml_clear_errors();

    if (!$loaded) {
        throw new RuntimeException('HTML website gagal diproses.');
    }

    $xpath = new DOMXPath($dom);
    $numberNodes = findNumberNodes($xpath);

    $log('Penanda nomor ditemukan: ' . count($numberNodes));

    $branches = [];

    foreach ($numberNodes as $marker) {
        /** @var DOMNode $markerNode */
        $markerNode = $marker['node'];
        $number = $marker['number'];
        $markerText = $marker['text'];

        $container = findBranchContainer($xpath, $markerNode);
        $lines = collectTextLines($container);

        $title = extractBranchTitle($xpath, $container, $markerText);

        $address = extractAddress($lines);
        $phone = extractPhone($lines);
        $hours = extractHours($lines);
        $notes = extractNotes($lines);

        $mapUrl = findLinkByText($xpath, $container, ['Map']);

        $directionUrl = findLinkByText(
            $xpath,
            $container,
            ['Menuju Lokasi', 'Petunjuk Arah', 'Directions']
        );

        // Kartu cabang harus punya alamat atau tautan lokasi,
        // bukan sekadar judul. Ini menyaring teks sampah di homepage.
        if ($address === '' && $mapUrl === '' && $directionUrl === '') {
            continue;
        }

        // Normalisasi nomor, agar "01" menjadi "1".
        $branch = [
            'branch_number' => (string) ((int) $number),
            'title'         => trim($title),
            'address'       => trim($address),
            'phone'         => trim($phone),
            'hours'         => trim($hours),
            'notes'         => trim($notes),
            'map_embed_url' => trim($mapUrl),
            'map_link'      => trim($directionUrl),
        ];

        $key = $branch['branch_number'];

        // Jika nomor yang sama ditemukan lebih dari sekali, pilih hasil
        // yang paling lengkap, bukan menggandakan data.
        if (!isset($branches[$key])) {
            $branches[$key] = $branch;
        } else {
            if ($branches[$key]['title'] !== $branch['title']) {
                $log(
                    "PERINGATAN: nomor {$key} dipakai lebih dari satu kartu: \""
                    . $branches[$key]['title'] . '" dan "' . $branch['title']
                    . '". Keduanya digabung jadi satu baris karena nomor harus unik.'
                );
            }

            foreach ($branch as $field => $value) {
                if (
                    $value !== ''
                    && (
                        $branches[$key][$field] === ''
                        || strlen($value) > strlen($branches[$key][$field])
                    )
                ) {
                    $branches[$key][$field] = $value;
                }
            }
        }
    }

    // Urutkan berdasarkan nomor cabang.
    uasort(
        $branches,
        static fn(array $a, array $b): int =>
            (int) $a['branch_number'] <=> (int) $b['branch_number']
    );

    return array_values($branches);
}

/**
 * Diagnosa hasil parsing, supaya jelas kenapa sebuah cabang tidak masuk:
 * - nomor tertinggi dan 3 cabang terakhir yang terbaca
 * - nomor yang bolong di tengah
 * - perbandingan dengan jumlah cabang yang diklaim teks website
 */
function diagnoseBranches(string $html, array $branches, callable $log): void
{
    if ($branches === []) {
        return;
    }

    $numbers = array_map(
        static fn(array $b): int => (int) $b['branch_number'],
        $branches
    );

    $max = max($numbers);
    $log("Nomor cabang tertinggi terbaca: {$max}");

    $last = array_slice($branches, -3);
    $labels = array_map(
        static fn(array $b): string => $b['branch_number'] . ' ' . $b['title'],
        $last
    );
    $log('3 cabang terakhir terbaca: ' . implode(' | ', $labels));

    $missing = [];

    for ($i = 1; $i <= $max; $i++) {
        if (!in_array($i, $numbers, true)) {
            $missing[] = $i;
        }
    }

    if ($missing !== []) {
        $log(
            'PERINGATAN: nomor tidak ditemukan di HTML: '
            . implode(', ', array_slice($missing, 0, 30))
            . (count($missing) > 30 ? ' ...' : '')
        );
    }

    $text = normalizeText(strip_tags($html));

    if (preg_match_all('/Tersebar\s+di\s+(\d{1,4})\s+Cabang/iu', $text, $m)) {
        $declared = max(array_map('intval', $m[1]));
        $log("Website menyatakan total cabang: {$declared}");

        if ($declared > count($branches)) {
            $log(
                "PERINGATAN: website menyatakan {$declared} cabang, tetapi HTML yang diterima server "
                . 'hanya memuat ' . count($branches) . '. Selisihnya kemungkinan dimuat lewat '
                . 'JavaScript/AJAX atau belum ada di HTML yang dikirim server (cache).'
            );
        }
    }
}

// =====================================================
// 11. PASTIKAN TABEL TERSEDIA
// =====================================================

function ensureTable(PDO $pdo): void
{
    // Skema sama dengan tabel yang dibuat admin.php. Sengaja TANPA indeks unik
    // dan tanpa mengubah struktur tabel yang sudah ada: admin boleh menambah
    // cabang manual, dan kolom sort_order bersifat opsional (lihat syncBranch).
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `" . DB_TABLE . "` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `branch_number` VARCHAR(10) NOT NULL,
            `title` VARCHAR(255) NOT NULL,
            `address` TEXT NOT NULL,
            `phone` VARCHAR(50) NOT NULL,
            `hours` VARCHAR(100) NOT NULL,
            `notes` VARCHAR(255) DEFAULT NULL,
            `map_embed_url` TEXT NOT NULL,
            `map_link` TEXT NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function tableHasColumn(PDO $pdo, string $column): bool
{
    static $cache = [];

    $key = DB_TABLE . '.' . $column;

    if (!isset($cache[$key])) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = :table_name
              AND column_name = :column_name
        ");

        $stmt->execute([':table_name' => DB_TABLE, ':column_name' => $column]);
        $cache[$key] = (int) $stmt->fetchColumn() > 0;
    }

    return $cache[$key];
}

// =====================================================
// 12. SINKRONISASI DATABASE
// =====================================================

/**
 * Menambah atau memperbarui satu cabang.
 * Mengembalikan: 'inserted' | 'updated' | 'unchanged'
 *
 * - Pencocokan memakai nilai angka (CAST), jadi "01" di DB cocok dengan "1".
 * - Cabang baru disimpan dengan nomor 2 digit ("01", "02", ...) seperti di website/admin.
 * - Kolom sort_order hanya dipakai jika ada di tabel.
 * - Nilai dipotong sesuai batas kolom agar tidak gagal "Data too long".
 */
function syncBranch(PDO $pdo, array $branch): string
{
    $number = trim((string) ($branch['branch_number'] ?? ''));

    if ($number === '' || (int) $number < 1) {
        throw new InvalidArgumentException('Nomor cabang tidak valid.');
    }

    $numberInt = (int) $number;

    $limits = [
        'title'         => 255,
        'address'       => 60000,
        'phone'         => 50,
        'hours'         => 100,
        'notes'         => 255,
        'map_embed_url' => 60000,
        'map_link'      => 60000,
    ];

    $fields = array_keys($limits);
    $hasSort = tableHasColumn($pdo, 'sort_order');

    $check = $pdo->prepare(
        'SELECT * FROM `' . DB_TABLE . '`
         WHERE CAST(branch_number AS UNSIGNED) = :number
         ORDER BY id ASC
         LIMIT 1'
    );

    $check->execute([':number' => $numberInt]);
    $existing = $check->fetch();

    $values = [];

    foreach ($fields as $field) {
        $newValue = trim((string) ($branch[$field] ?? ''));

        if ($existing && $newValue === '' && in_array($field, PRESERVE_WHEN_EMPTY, true)) {
            // Jangan menimpa data penting dengan kosong jika scraping gagal membacanya.
            $values[$field] = trim((string) ($existing[$field] ?? ''));
        } else {
            $values[$field] = mb_substr($newValue, 0, $limits[$field], 'UTF-8');
        }
    }

    if ($existing) {
        $changed = $hasSort && (int) ($existing['sort_order'] ?? 0) !== $numberInt;

        foreach ($fields as $field) {
            if (trim((string) ($existing[$field] ?? '')) !== $values[$field]) {
                $changed = true;
                break;
            }
        }

        if (!$changed) {
            return 'unchanged';
        }

        $set = [];
        $params = [':id' => $existing['id']];

        foreach ($fields as $field) {
            $set[] = "`{$field}` = :{$field}";
            $params[':' . $field] = $values[$field];
        }

        if ($hasSort) {
            $set[] = '`sort_order` = :sort_order';
            $params[':sort_order'] = $numberInt;
        }

        $pdo->prepare(
            'UPDATE `' . DB_TABLE . '` SET ' . implode(', ', $set) . ' WHERE id = :id'
        )->execute($params);

        return 'updated';
    }

    $columns = array_merge(['branch_number'], $fields);

    $params = [
        ':branch_number' => str_pad((string) $numberInt, 2, '0', STR_PAD_LEFT),
    ];

    foreach ($fields as $field) {
        $params[':' . $field] = $values[$field];
    }

    if ($hasSort) {
        $columns[] = 'sort_order';
        $params[':sort_order'] = $numberInt;
    }

    $placeholders = array_map(
        static fn(string $column): string => ':' . $column,
        $columns
    );

    $pdo->prepare(
        'INSERT INTO `' . DB_TABLE . '` (`' . implode('`, `', $columns) . '`) '
        . 'VALUES (' . implode(', ', $placeholders) . ')'
    )->execute($params);

    return 'inserted';
}

/**
 * Menghapus cabang di DB yang nomornya tidak ada lagi di hasil scraping.
 * Mengembalikan jumlah baris yang dihapus.
 */
function deleteMissingBranches(PDO $pdo, array $keepNumbers, callable $log): int
{
    if (!DELETE_MISSING) {
        return 0;
    }

    $rows = $pdo->query(
        'SELECT id, branch_number, title FROM `' . DB_TABLE . '`'
    )->fetchAll();

    $keep = array_map('intval', $keepNumbers);
    $toDelete = [];

    foreach ($rows as $row) {
        if (!in_array((int) $row['branch_number'], $keep, true)) {
            $toDelete[] = $row;
        }
    }

    if ($toDelete === []) {
        return 0;
    }

    $existingCount = count($rows);
    $keptCount = count($keep);

    if (
        $existingCount >= DELETE_GUARD_MIN_ROWS
        && $keptCount < $existingCount * DELETE_MIN_KEEP_RATIO
    ) {
        $log(
            "PERINGATAN: penghapusan DIBATALKAN. Website hanya terbaca {$keptCount} cabang, "
            . "sedangkan DB berisi {$existingCount}. Kemungkinan halaman/parsing bermasalah."
        );

        return 0;
    }

    $delete = $pdo->prepare('DELETE FROM `' . DB_TABLE . '` WHERE id = :id');
    $deleted = 0;

    foreach ($toDelete as $row) {
        $delete->execute([':id' => $row['id']]);
        $deleted++;

        $log(
            str_pad((string) $row['branch_number'], 6)
            . str_pad('DELETED', 10)
            . mb_substr((string) $row['title'], 0, 39, 'UTF-8')
        );
    }

    return $deleted;
}

// =====================================================
// 13. PROSES UTAMA
// =====================================================

/**
 * Ambil dari website -> parse -> sinkron ke DB (tambah, ubah, hapus).
 *
 * @param callable|null $logger fn(string $line): void, opsional
 * @return array{parsed:int,inserted:int,updated:int,unchanged:int,deleted:int,failed:int,total:int,failures:array,log:array}
 */
function runSync(?callable $logger = null, ?PDO $pdo = null): array
{
    @set_time_limit(120);

    $lines = [];

    $log = static function (string $line) use (&$lines, $logger): void {
        $lines[] = $line;

        if ($logger !== null) {
            $logger($line);
        }
    };

    // Pakai koneksi dari pemanggil (admin.php) agar menulis ke DB yang sama dengan CMS.
    $pdo = $pdo ?? connectDatabase();
    ensureTable($pdo);

    $html = fetchWebsite(SOURCE_URL, $log);

    $log('Ukuran HTML : ' . number_format(strlen($html)) . ' bytes');

    $branches = parseBranches($html, $log);

    $log('Jumlah cabang berhasil diparsing: ' . count($branches));

    if ($branches === []) {
        throw new RuntimeException(
            'Tidak ada cabang yang berhasil diparsing, DB tidak diubah. ' .
            'Kemungkinan daftar cabang dimuat lewat JavaScript atau struktur HTML berubah.'
        );
    }

    diagnoseBranches($html, $branches, $log);

    $dupes = $pdo->query(
        'SELECT CAST(branch_number AS UNSIGNED) AS n, COUNT(*) AS c FROM `' . DB_TABLE . '`
         GROUP BY n HAVING COUNT(*) > 1'
    )->fetchAll();

    foreach ($dupes as $dupe) {
        $log(
            "PERINGATAN: di database nomor {$dupe['n']} dipakai {$dupe['c']} baris. "
            . 'Hanya baris dengan id terkecil yang disinkronkan; hapus duplikatnya di admin.'
        );
    }

    $dbCountBefore = (int) $pdo->query(
        'SELECT COUNT(*) FROM `' . DB_TABLE . '`'
    )->fetchColumn();

    if ($dbCountBefore > count($branches)) {
        $log(
            'CATATAN: DB berisi ' . $dbCountBefore . ' cabang, hasil scraping hanya '
            . count($branches) . '. Selisihnya akan dihapus jika lolos pengaman.'
        );
    }

    $log('');
    $log(
        str_pad('NO', 6)
        . str_pad('STATUS', 10)
        . str_pad('NAMA CABANG', 42)
        . str_pad('ALAMAT', 10)
        . str_pad('MAP', 8)
        . 'MENUJU LOKASI'
    );
    $log(str_repeat('-', 110));

    $inserted = 0;
    $updated = 0;
    $unchanged = 0;
    $deleted = 0;
    $failed = 0;
    $failures = [];

    $pdo->beginTransaction();

    try {
        foreach ($branches as $branch) {
            try {
                $status = syncBranch($pdo, $branch);

                if ($status === 'inserted') {
                    $inserted++;
                } elseif ($status === 'updated') {
                    $updated++;
                } else {
                    $unchanged++;
                }

                $log(
                    str_pad($branch['branch_number'], 6)
                    . str_pad(strtoupper($status), 10)
                    . str_pad(mb_substr($branch['title'], 0, 39, 'UTF-8'), 42)
                    . str_pad($branch['address'] !== '' ? 'YA' : 'KOSONG', 10)
                    . str_pad($branch['map_embed_url'] !== '' ? 'YA' : 'TIDAK', 8)
                    . ($branch['map_link'] !== '' ? 'YA' : 'TIDAK')
                );
            } catch (Throwable $e) {
                $failed++;
                $failures[] = [
                    'number'  => $branch['branch_number'],
                    'message' => $e->getMessage(),
                ];

                $log("GAGAL cabang {$branch['branch_number']}: " . $e->getMessage());
            }
        }

        // Hapus cabang yang sudah tidak ada di website.
        $deleted = deleteMissingBranches(
            $pdo,
            array_column($branches, 'branch_number'),
            $log
        );

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }

    $total = (int) $pdo->query(
        'SELECT COUNT(*) FROM `' . DB_TABLE . '`'
    )->fetchColumn();

    $log('');
    $log('========================================');
    $log(' HASIL SINKRONISASI');
    $log('========================================');
    $log('Cabang diparsing  : ' . count($branches));
    $log("Cabang ditambah   : {$inserted}");
    $log("Cabang diperbarui : {$updated}");
    $log("Tidak berubah     : {$unchanged}");
    $log("Cabang dihapus    : {$deleted}");
    $log("Cabang gagal      : {$failed}");
    $log("Total di database : {$total}");

    return [
        'parsed'    => count($branches),
        'inserted'  => $inserted,
        'updated'   => $updated,
        'unchanged' => $unchanged,
        'deleted'   => $deleted,
        'failed'    => $failed,
        'total'     => $total,
        'failures'  => $failures,
        'log'       => $lines,
    ];
}

/**
 * Versi yang langsung mencetak log (CLI atau halaman admin sederhana).
 */
function main(?PDO $pdo = null): void
{
    echo "========================================\n";
    echo " SCRAPER CABANG RAJA EMAS INDONESIA\n";
    echo "========================================\n\n";

    runSync(static function (string $line): void {
        echo $line . "\n";
    }, $pdo);

    echo "\nSelesai.\n";
}

// Jalankan otomatis hanya ketika file ini dipanggil langsung.
// Jika di-include oleh halaman admin, panggil runSync() atau main()
// setelah login dan CSRF diperiksa.
$isDirectExecution = PHP_SAPI === 'cli'
    || (isset($_SERVER['SCRIPT_FILENAME'])
        && realpath((string) $_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__));

if ($isDirectExecution) {
    try {
        main();
    } catch (Throwable $e) {
        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, "\nERROR: " . $e->getMessage() . "\n");
            exit(1);
        }

        http_response_code(500);
        echo 'ERROR: ' . htmlspecialchars(
            $e->getMessage(),
            ENT_QUOTES,
            'UTF-8'
        );
    }
}
