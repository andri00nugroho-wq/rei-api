<?php
declare(strict_types=1);

/**
 * Scraper dan sinkronisasi cabang Raja Emas Indonesia.
 *
 * Jalankan:
 * php scrape-branches.php
 *
 * Sumber:
 * https://rajaemasindonesia.co.id/
 */

// =====================================================
// 1. KONFIGURASI
// =====================================================

const SOURCE_URL = 'https://rajaemasindonesia.co.id/';
const DEBUG_FILE = __DIR__ . '/debug-branches.html';

// Sesuaikan dengan konfigurasi database Laragon Anda.
const DB_HOST = '127.0.0.1';
const DB_PORT = '3306';
const DB_NAME = 'rei_cms_v1';
const DB_USER = 'root';
const DB_PASS = '';

const DB_TABLE = 'branches';

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
// 3. AMBIL HTML DARI WEBSITE
// =====================================================

function fetchWebsite(string $url): string
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException(
            'Ekstensi cURL belum aktif. Aktifkan extension=curl di php.ini Laragon.'
        );
    }

    $ch = curl_init($url);

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
            'Cache-Control: no-cache',
        ],
    ]);

    $html = curl_exec($ch);
    $error = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $finalUrl = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);

    curl_close($ch);

    echo "URL sumber : {$url}\n";
    echo "URL akhir  : {$finalUrl}\n";
    echo "HTTP       : {$httpCode}\n";

    if ($html === false || $html === '') {
        throw new RuntimeException(
            'Gagal mengambil HTML website. ' . $error
        );
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        throw new RuntimeException(
            "Website mengembalikan HTTP {$httpCode}."
        );
    }

    // Simpan HTML untuk pemeriksaan jika struktur website berubah.
    file_put_contents(DEBUG_FILE, $html);

    return $html;
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

        // Nomor harus berada pada teks langsung elemen ini,
        // bukan sekadar teks dari elemen anak.
        $directText = '';

        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMText) {
                $directText .= ' ' . $child->nodeValue;
            }
        }

        $directText = normalizeText($directText);

        if ($directText === '') {
            continue;
        }

        if (!preg_match(
            '/^\s*(?:#\s*)?(?:cabang\s*)?0*\d{1,3}(?=\s|[-.:]|$)/iu',
            $directText
        )) {
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

            $directText = '';

            foreach ($child->childNodes as $textNode) {
                if ($textNode instanceof DOMText) {
                    $directText .= ' ' . $textNode->nodeValue;
                }
            }

            if (containsBranchNumber(normalizeText($directText))) {
                $count++;
            }
        }
    }

    return $count;
}

function findBranchContainer(
    DOMXPath $xpath,
    DOMNode $numberNode
): DOMNode {
    $current = $numberNode;
    $best = $numberNode;
    $bestScore = -PHP_INT_MAX;

    for ($level = 0; $level < 10 && $current !== null; $level++) {
        if (!$current instanceof DOMElement) {
            $current = $current->parentNode;
            continue;
        }

        $text = normalizeText($current->textContent);

        // Batasi ukuran supaya tidak memilih seluruh bagian lokasi.
        if (mb_strlen($text, 'UTF-8') > 5000) {
            $current = $current->parentNode;
            continue;
        }

        $mapUrl = findLinkByText($xpath, $current, ['Map']);
        $directionUrl = findLinkByText(
            $xpath,
            $current,
            ['Menuju Lokasi', 'Petunjuk Arah', 'Directions']
        );

        $phoneFound = preg_match(
            '/(?:\+62|62|0)\s*[\d\s().-]{7,}/u',
            $text
        ) === 1;

        $addressFound = preg_match(
            '/\b(jalan|jl\.?|rukan|ruko|mall|mal|lantai|blok|komplek|kompleks|kawasan|gedung|plaza|no\.?)\b/iu',
            $text
        ) === 1;

        $numberMarkers = countNumberMarkers($xpath, $current);

        $score = 0;
        $score += $mapUrl !== '' ? 8 : 0;
        $score += $directionUrl !== '' ? 8 : 0;
        $score += $phoneFound ? 3 : 0;
        $score += $addressFound ? 3 : 0;
        $score -= max(0, $numberMarkers - 1) * 12;
        $score -= max(0, mb_strlen($text, 'UTF-8') - 1200) / 100;

        if ($score > $bestScore) {
            $bestScore = $score;
            $best = $current;
        }

        // Hentikan setelah menemukan container dengan dua tautan lokasi
        // dan tidak memuat penanda nomor cabang lain.
        if (
            $mapUrl !== ''
            && $directionUrl !== ''
            && $numberMarkers <= 1
        ) {
            return $current;
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

function parseBranches(string $html): array
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

    echo 'Penanda nomor ditemukan: ' . count($numberNodes) . "\n";

    $branches = [];

    foreach ($numberNodes as $marker) {
        /** @var DOMNode $markerNode */
        $markerNode = $marker['node'];
        $number = $marker['number'];
        $markerText = $marker['text'];

        $container = findBranchContainer($xpath, $markerNode);
        $lines = collectTextLines($container);

        $title = extractBranchTitle(
            $xpath,
            $container,
            $markerText
        );

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

        // Jika container berisi banyak nomor cabang, jangan menyimpan
        // satu container gabungan untuk banyak cabang.
        if (countNumberMarkers($xpath, $container) > 1) {
            $parent = $markerNode->parentNode;

            if ($parent instanceof DOMNode) {
                $smallerContainer = findBranchContainer($xpath, $markerNode);
                $smallerLines = collectTextLines($smallerContainer);

                $smallerTitle = extractBranchTitle(
                    $xpath,
                    $smallerContainer,
                    $markerText
                );

                $smallerAddress = extractAddress($smallerLines);
                $smallerPhone = extractPhone($smallerLines);
                $smallerHours = extractHours($smallerLines);
                $smallerNotes = extractNotes($smallerLines);

                $smallerMap = findLinkByText(
                    $xpath,
                    $smallerContainer,
                    ['Map']
                );

                $smallerDirection = findLinkByText(
                    $xpath,
                    $smallerContainer,
                    ['Menuju Lokasi', 'Petunjuk Arah', 'Directions']
                );

                if (
                    $smallerTitle !== ''
                    || $smallerAddress !== ''
                    || $smallerMap !== ''
                    || $smallerDirection !== ''
                ) {
                    $title = $smallerTitle;
                    $address = $smallerAddress;
                    $phone = $smallerPhone;
                    $hours = $smallerHours;
                    $notes = $smallerNotes;
                    $mapUrl = $smallerMap;
                    $directionUrl = $smallerDirection;
                }
            }
        }

        // Lewati hasil yang jelas bukan kartu cabang.
        if (
            $title === ''
            && $address === ''
            && $mapUrl === ''
            && $directionUrl === ''
        ) {
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

// =====================================================
// 11. PASTIKAN TABEL TERSEDIA
// =====================================================

function ensureTable(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `" . DB_TABLE . "` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `branch_number` VARCHAR(20) NOT NULL,
            `title` VARCHAR(255) NOT NULL DEFAULT '',
            `address` TEXT NOT NULL,
            `phone` VARCHAR(100) NOT NULL DEFAULT '',
            `hours` VARCHAR(255) NOT NULL DEFAULT '',
            `notes` TEXT NOT NULL,
            `map_embed_url` TEXT NOT NULL,
            `map_link` TEXT NOT NULL,
            `sort_order` INT NOT NULL DEFAULT 0,
            `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_branches_branch_number` (`branch_number`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Jangan otomatis mengubah struktur tabel yang sudah ada.
    // Periksa apakah branch_number memiliki indeks unik.
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name = :table_name
          AND column_name = 'branch_number'
          AND non_unique = 0
    ");

    $stmt->execute([':table_name' => DB_TABLE]);

    if ((int) $stmt->fetchColumn() === 0) {
        $duplicates = $pdo->query("
            SELECT branch_number, COUNT(*) AS total
            FROM `" . DB_TABLE . "`
            GROUP BY branch_number
            HAVING COUNT(*) > 1
            LIMIT 5
        ")->fetchAll();

        if ($duplicates !== []) {
            throw new RuntimeException(
                'Terdapat nomor cabang duplikat di database. ' .
                'Bersihkan duplikat terlebih dahulu sebelum membuat indeks unik.'
            );
        }

        $pdo->exec(
            'ALTER TABLE `' . DB_TABLE .
            '` ADD UNIQUE KEY `uq_branches_branch_number` (`branch_number`)'
        );
    }
}

// =====================================================
// 12. SINKRONISASI DATABASE
// =====================================================

function syncBranch(PDO $pdo, array $branch): string
{
    $number = trim((string) ($branch['branch_number'] ?? ''));

    if ($number === '' || (int) $number < 1) {
        throw new InvalidArgumentException('Nomor cabang tidak valid.');
    }

    // Periksa apakah cabang sudah ada.
    $check = $pdo->prepare(
        'SELECT * FROM `' . DB_TABLE . '` WHERE branch_number = :number LIMIT 1'
    );

    $check->execute([':number' => $number]);
    $existing = $check->fetch();

    $fields = [
        'title',
        'address',
        'phone',
        'hours',
        'notes',
        'map_embed_url',
        'map_link',
    ];

    $values = [];

    foreach ($fields as $field) {
        $newValue = trim((string) ($branch[$field] ?? ''));

        if ($existing) {
            $oldValue = trim((string) ($existing[$field] ?? ''));

            // Pertahankan data lama jika scraping tidak mendapatkan nilai.
            $values[$field] = $newValue !== '' ? $newValue : $oldValue;
        } else {
            // Kolom teks tidak boleh dikirim sebagai NULL.
            $values[$field] = $newValue;
        }
    }

    if ($existing) {
        $update = $pdo->prepare("
            UPDATE `" . DB_TABLE . "`
            SET
                title = :title,
                address = :address,
                phone = :phone,
                hours = :hours,
                notes = :notes,
                map_embed_url = :map_embed_url,
                map_link = :map_link
            WHERE branch_number = :branch_number
        ");

        $update->execute([
            ':branch_number' => $number,
            ':title'         => $values['title'],
            ':address'       => $values['address'],
            ':phone'         => $values['phone'],
            ':hours'         => $values['hours'],
            ':notes'         => $values['notes'],
            ':map_embed_url' => $values['map_embed_url'],
            ':map_link'      => $values['map_link'],
        ]);

        return 'updated';
    }

    $insert = $pdo->prepare("
        INSERT INTO `" . DB_TABLE . "` (
            branch_number,
            title,
            address,
            phone,
            hours,
            notes,
            map_embed_url,
            map_link,
            sort_order
        ) VALUES (
            :branch_number,
            :title,
            :address,
            :phone,
            :hours,
            :notes,
            :map_embed_url,
            :map_link,
            :sort_order
        )
    ");

    $insert->execute([
        ':branch_number' => $number,
        ':title'         => $values['title'],
        ':address'       => $values['address'],
        ':phone'         => $values['phone'],
        ':hours'         => $values['hours'],
        ':notes'         => $values['notes'],
        ':map_embed_url' => $values['map_embed_url'],
        ':map_link'      => $values['map_link'],
        ':sort_order'    => (int) $number,
    ]);

    return 'inserted';
}

// =====================================================
// 13. JALANKAN SCRAPING DAN SINKRONISASI
// =====================================================

function main(): void
{
    echo "========================================\n";
    echo " SCRAPER CABANG RAJA EMAS INDONESIA\n";
    echo "========================================\n\n";

    $pdo = connectDatabase();
    ensureTable($pdo);

    $html = fetchWebsite(SOURCE_URL);

    echo 'Ukuran HTML : ' . number_format(strlen($html)) . " bytes\n\n";

    $branches = parseBranches($html);

    echo 'Jumlah cabang berhasil diparsing: ' . count($branches) . "\n";

    if ($branches === []) {
        throw new RuntimeException(
            'Tidak ada cabang yang berhasil diparsing. ' .
            'Periksa file debug-branches.html dan struktur HTML website.'
        );
    }

    echo "\n";
    echo str_pad('NO', 6)
        . str_pad('NAMA CABANG', 42)
        . str_pad('ALAMAT', 12)
        . str_pad('MAP', 8)
        . "MENUJU LOKASI\n";

    echo str_repeat('-', 100) . "\n";

    $inserted = 0;
    $updated = 0;
    $failed = 0;
    $failures = [];

    $pdo->beginTransaction();

    try {
        foreach ($branches as $branch) {
            try {
                $status = syncBranch($pdo, $branch);

                if ($status === 'inserted') {
                    $inserted++;
                } else {
                    $updated++;
                }

                echo str_pad($branch['branch_number'], 6)
                    . str_pad(
                        mb_substr($branch['title'], 0, 39, 'UTF-8'),
                        42
                    )
                    . str_pad($branch['address'] !== '' ? 'YA' : 'KOSONG', 12)
                    . str_pad($branch['map_embed_url'] !== '' ? 'YA' : 'TIDAK', 8)
                    . ($branch['map_link'] !== '' ? 'YA' : 'TIDAK')
                    . "\n";

                if ($branch['address'] === '') {
                    echo "  PERINGATAN: alamat cabang {$branch['branch_number']} kosong pada hasil scraping.\n";
                }
            } catch (Throwable $e) {
                $failed++;
                $failures[] = [
                    'number' => $branch['branch_number'],
                    'message' => $e->getMessage(),
                ];

                echo "GAGAL cabang {$branch['branch_number']}: "
                    . $e->getMessage() . "\n";
            }
        }

        // Jika ada kegagalan, data yang sukses tetap disimpan.
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }

    $totalDb = (int) $pdo->query(
        'SELECT COUNT(*) FROM `' . DB_TABLE . '`'
    )->fetchColumn();

    echo "\n========================================\n";
    echo " HASIL SINKRONISASI\n";
    echo "========================================\n";
    echo "Cabang diparsing : " . count($branches) . "\n";
    echo "Cabang ditambah  : {$inserted}\n";
    echo "Cabang diperbarui: {$updated}\n";
    echo "Cabang gagal     : {$failed}\n";
    echo "Total di database: {$totalDb}\n";
    echo "HTML debug       : " . DEBUG_FILE . "\n";

    if ($failures !== []) {
        echo "\nRincian kegagalan:\n";

        foreach ($failures as $failure) {
            echo '- Cabang ' . $failure['number']
                . ': ' . $failure['message'] . "\n";
        }
    }

    echo "\nSelesai.\n";
}

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
