
<?php
declare(strict_types=1);

/**
 * Scraper dan sinkronisasi cabang Raja Emas Indonesia.
 *
 * Terminal:
 *   php scrape-branches.php
 *
 * Pemanggilan dari admin.php:
 *   require_once __DIR__ . '/scrape-branches.php';
 *   main();
 *
 * Koneksi database menggunakan config.php.
 */

require_once __DIR__ . '/config.php';

// =====================================================
// 1. KONFIGURASI SCRAPER
// =====================================================

const SOURCE_URL = 'https://rajaemasindonesia.co.id/';
const DEBUG_FILE = __DIR__ . '/debug-branches.html';

// DB_TABLE harus didefinisikan di config.php.
// Jangan mendefinisikannya lagi di file ini.

if (!defined('DB_TABLE')) {
    throw new RuntimeException(
        'Konstanta DB_TABLE belum didefinisikan di config.php.'
    );
}

// =====================================================
// 2. AMBIL HTML WEBSITE
// =====================================================

function fetchWebsite(string $url): string
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException(
            'Ekstensi cURL belum aktif pada PHP.'
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
        CURLOPT_USERAGENT      =>
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) ' .
            'AppleWebKit/537.36 Chrome/130 Safari/537.36',
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
            'Gagal mengambil HTML: ' . $error
        );
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        throw new RuntimeException(
            "Website mengembalikan HTTP {$httpCode}."
        );
    }

    if (file_put_contents(DEBUG_FILE, $html) === false) {
        echo "Peringatan: HTML debug tidak dapat disimpan.\n";
    }

    return $html;
}

// =====================================================
// 3. NORMALISASI TEKS
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

function normalizePhone(string $phone): string
{
    return trim(preg_replace('/\s+/u', ' ', $phone) ?? $phone);
}

// =====================================================
// 4. BANTUAN DOM
// =====================================================

function directText(DOMElement $element): string
{
    $text = '';

    foreach ($element->childNodes as $child) {
        if ($child instanceof DOMText) {
            $text .= ' ' . ($child->nodeValue ?? '');
        }
    }

    return normalizeText($text);
}

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

        foreach ($current->childNodes as $child) {
            $walk($child);
        }
    };

    $walk($node);

    $result = [];

    foreach ($lines as $line) {
        $line = normalizeText($line);

        if ($line !== '') {
            $result[] = $line;
        }
    }

    return array_values(array_unique($result));
}

function absoluteUrl(string $href): string
{
    $href = trim($href);

    if ($href === '' || $href === '#') {
        return '';
    }

    if (str_starts_with($href, '//')) {
        return 'https:' . $href;
    }

    if (preg_match('~^https?://~i', $href)) {
        return $href;
    }

    if (str_starts_with($href, '/')) {
        return 'https://rajaemasindonesia.co.id' . $href;
    }

    return 'https://rajaemasindonesia.co.id/' . $href;
}

function findLinkByText(
    DOMXPath $xpath,
    DOMNode $container,
    array $keywords
): string {
    $links = $xpath->query('.//a[@href]', $container);

    if ($links === false) {
        return '';
    }

    foreach ($links as $link) {
        if (!$link instanceof DOMElement) {
            continue;
        }

        $label = mb_strtolower(
            normalizeText($link->textContent),
            'UTF-8'
        );

        foreach ($keywords as $keyword) {
            if (str_contains(
                $label,
                mb_strtolower($keyword, 'UTF-8')
            )) {
                $url = absoluteUrl($link->getAttribute('href'));

                if ($url !== '') {
                    return $url;
                }
            }
        }
    }

    return '';
}

function countNumberMarkers(
    DOMXPath $xpath,
    DOMNode $container
): int {
    $nodes = $xpath->query('.//*', $container);

    if ($nodes === false) {
        return 0;
    }

    $count = 0;

    foreach ($nodes as $node) {
        if (
            $node instanceof DOMElement
            && extractBranchNumber(directText($node)) !== ''
        ) {
            $count++;
        }
    }

    return $count;
}

function findNumberNodes(DOMXPath $xpath): array
{
    $nodes = $xpath->query('//*');

    if ($nodes === false) {
        return [];
    }

    $results = [];

    foreach ($nodes as $node) {
        if (!$node instanceof DOMElement) {
            continue;
        }

        $text = directText($node);
        $number = extractBranchNumber($text);

        if ($number === '') {
            continue;
        }

        $results[] = [
            'node'   => $node,
            'number' => $number,
            'text'   => $text,
        ];
    }

    return $results;
}

// =====================================================
// 5. MENENTUKAN KARTU CABANG
// =====================================================

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

        $markers = countNumberMarkers($xpath, $current);

        $score = 0;
        $score += $mapUrl !== '' ? 8 : 0;
        $score += $directionUrl !== '' ? 8 : 0;
        $score += $phoneFound ? 3 : 0;
        $score += $addressFound ? 3 : 0;
        $score -= max(0, $markers - 1) * 12;
        $score -= max(
            0,
            mb_strlen($text, 'UTF-8') - 1200
        ) / 100;

        if ($score > $bestScore) {
            $bestScore = $score;
            $best = $current;
        }

        if (
            $mapUrl !== ''
            && $directionUrl !== ''
            && $markers <= 1
        ) {
            return $current;
        }

        $current = $current->parentNode;
    }

    return $best;
}

// =====================================================
// 6. EKSTRAKSI DATA CABANG
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

            if ($text !== '') {
                $cleaned = removeBranchNumber($text);

                if ($cleaned !== '') {
                    return $cleaned;
                }

                if (extractBranchNumber($text) === '') {
                    return $text;
                }
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
            $phone = normalizePhone($matches[0]);
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
            preg_match(
                '/\b(jam operasional|operasional|buka|senin|minggu)\b/iu',
                $line
            )
            && preg_match('/\d{1,2}[.:]\d{2}/u', $line)
        ) {
            return $line;
        }
    }

    return '';
}

function extractAddress(array $lines): string
{
    $candidates = [];

    foreach ($lines as $line) {
        $line = normalizeText($line);

        if ($line === '' || mb_strlen($line, 'UTF-8') < 8) {
            continue;
        }

        if (preg_match('/^\s*#?\s*\d{1,3}\s*$/u', $line)) {
            continue;
        }

        if (preg_match(
            '/^(map|menuju lokasi|petunjuk arah|lihat peta|hubungi kami)$/iu',
            $line
        )) {
            continue;
        }

        if (preg_match(
            '/(?:\+62|62|08\d{1,3})[\d\s().-]{6,}/u',
            $line
        )) {
            continue;
        }

        if (preg_match('/\d{1,2}[.:]\d{2}/u', $line)) {
            continue;
        }

        if (preg_match(
            '/\b(jalan|jl\.?|rukan|ruko|mall|mal|lantai|blok|komplek|kompleks|kawasan|gedung|plaza|no\.?)\b/iu',
            $line
        )) {
            $candidates[] = $line;
        }
    }

    return implode(', ', array_values(array_unique($candidates)));
}

function extractNotes(array $lines): string
{
    foreach ($lines as $line) {
        if (preg_match(
            '/^(catatan|keterangan|notes)\s*[:\-]\s*(.+)$/iu',
            $line,
            $matches
        )) {
            return trim($matches[2]);
        }
    }

    return '';
}

// =====================================================
// 7. PARSING SELURUH CABANG
// =====================================================

function parseBranches(string $html): array
{
    if (!class_exists(DOMDocument::class)) {
        throw new RuntimeException(
            'Ekstensi PHP DOM belum aktif.'
        );
    }

    if (!function_exists('mb_strtolower')) {
        throw new RuntimeException(
            'Ekstensi PHP mbstring belum aktif.'
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
        throw new RuntimeException('HTML gagal diproses.');
    }

    $xpath = new DOMXPath($dom);
    $numberNodes = findNumberNodes($xpath);

    echo 'Penanda nomor ditemukan: ' . count($numberNodes) . "\n";

    $branches = [];

    foreach ($numberNodes as $marker) {
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

        // Lewati elemen yang tidak memiliki informasi cabang.
        if (
            $title === ''
            && $address === ''
            && $mapUrl === ''
            && $directionUrl === ''
        ) {
            continue;
        }

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

        if (!isset($branches[$key])) {
            $branches[$key] = $branch;
            continue;
        }

        // Pilih data yang lebih lengkap jika nomor ditemukan berulang.
        foreach ($branch as $field => $value) {
            if (
                $value !== ''
                && (
                    $branches[$key][$field] === ''
                    || mb_strlen($value, 'UTF-8')
                        > mb_strlen($branches[$key][$field], 'UTF-8')
                )
            ) {
                $branches[$key][$field] = $value;
            }
        }
    }

    uasort(
        $branches,
        static fn(array $a, array $b): int =>
            (int) $a['branch_number'] <=> (int) $b['branch_number']
    );

    return array_values($branches);
}

// =====================================================
// 8. MEMASTIKAN TABEL DAN INDEKS UNIK
// =====================================================

function ensureTable(PDO $pdo): void
{
    $table = '`' . str_replace('`', '``', DB_TABLE) . '`';

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            branch_number VARCHAR(20) NOT NULL,
            title VARCHAR(255) NOT NULL DEFAULT '',
            address TEXT NOT NULL,
            phone VARCHAR(100) NOT NULL DEFAULT '',
            hours VARCHAR(255) NOT NULL DEFAULT '',
            notes TEXT NOT NULL,
            map_embed_url TEXT NOT NULL,
            map_link TEXT NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_branches_branch_number (branch_number)
        ) ENGINE=InnoDB
          DEFAULT CHARSET=utf8mb4
          COLLATE=utf8mb4_unicode_ci
    ");

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name = :table_name
          AND column_name = 'branch_number'
          AND non_unique = 0
    ");

    $stmt->execute([':table_name' => DB_TABLE]);

    if ((int) $stmt->fetchColumn() > 0) {
        return;
    }

    $duplicates = $pdo->query("
        SELECT branch_number, COUNT(*) AS total
        FROM {$table}
        GROUP BY branch_number
        HAVING COUNT(*) > 1
        LIMIT 5
    ")->fetchAll();

    if ($duplicates !== []) {
        throw new RuntimeException(
            'Nomor cabang duplikat ditemukan. Bersihkan data duplikat ' .
            'sebelum membuat indeks unik.'
        );
    }

    $pdo->exec(
        "ALTER TABLE {$table} " .
        "ADD UNIQUE KEY uq_branches_branch_number (branch_number)"
    );
}

// =====================================================
// 9. SINKRONISASI SATU CABANG
// =====================================================

function syncBranch(PDO $pdo, array $branch): string
{
    $table = '`' . str_replace('`', '``', DB_TABLE) . '`';

    $number = trim((string) ($branch['branch_number'] ?? ''));

    if ($number === '' || (int) $number < 1) {
        throw new InvalidArgumentException(
            'Nomor cabang tidak valid.'
        );
    }

    $check = $pdo->prepare(
        "SELECT * FROM {$table} " .
        'WHERE branch_number = :number LIMIT 1'
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

            // Jangan menimpa informasi lama dengan hasil kosong.
            $values[$field] = $newValue !== ''
                ? $newValue
                : $oldValue;
        } else {
            $values[$field] = $newValue;
        }
    }

    if ($existing) {
        $update = $pdo->prepare("
            UPDATE {$table}
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
        INSERT INTO {$table} (
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
// 10. PROSES UTAMA
// =====================================================

function main(): void
{
    // Mengambil PDO yang sudah dibuat oleh config.php.
    global $pdo;

    if (!$pdo instanceof PDO) {
        throw new RuntimeException(
            'Koneksi PDO tidak ditemukan. Periksa config.php.'
        );
    }

    echo "========================================\n";
    echo " SCRAPER CABANG RAJA EMAS INDONESIA\n";
    echo "========================================\n\n";

    ensureTable($pdo);

    $html = fetchWebsite(SOURCE_URL);

    echo 'Ukuran HTML: ' .
        number_format(strlen($html)) . " bytes\n\n";

    $branches = parseBranches($html);

    echo 'Cabang berhasil diparsing: ' .
        count($branches) . "\n";

    if ($branches === []) {
        throw new RuntimeException(
            'Tidak ada cabang yang berhasil diparsing. ' .
            'Periksa debug-branches.html dan struktur website sumber.'
        );
    }

    $inserted = 0;
    $updated = 0;
    $failed = 0;
    $failures = [];

    $table = '`' . str_replace('`', '``', DB_TABLE) . '`';

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

                echo sprintf(
                    "Cabang %-4s | %-35s | Alamat: %-7s | Map: %-5s | Arah: %s\n",
                    $branch['branch_number'],
                    mb_substr($branch['title'], 0, 35, 'UTF-8'),
                    $branch['address'] !== '' ? 'ADA' : 'KOSONG',
                    $branch['map_embed_url'] !== '' ? 'ADA' : 'KOSONG',
                    $branch['map_link'] !== '' ? 'ADA' : 'KOSONG'
                );

                if ($branch['address'] === '') {
                    echo "  PERINGATAN: alamat tidak ditemukan pada hasil scraping.\n";
                }
            } catch (Throwable $e) {
                $failed++;

                $failures[] = [
                    'number'  => $branch['branch_number'],
                    'message' => $e->getMessage(),
                ];

                echo "GAGAL cabang {$branch['branch_number']}: " .
                    $e->getMessage() . "\n";
            }
        }

        // Simpan semua cabang yang berhasil diproses.
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }

    $totalDb = (int) $pdo->query(
        "SELECT COUNT(*) FROM {$table}"
    )->fetchColumn();

    echo "\n========================================\n";
    echo " HASIL SINKRONISASI\n";
    echo "========================================\n";
    echo "Cabang diparsing : {$count = count($branches)}\n";
    echo "Cabang ditambah  : {$inserted}\n";
    echo "Cabang diperbarui: {$updated}\n";
    echo "Cabang gagal     : {$failed}\n";
    echo "Total di database: {$totalDb}\n";
    echo "File HTML debug  : " . DEBUG_FILE . "\n";

    if ($failures !== []) {
        echo "\nRincian kegagalan:\n";

        foreach ($failures as $failure) {
            echo '- Cabang ' . $failure['number'] .
                ': ' . $failure['message'] . "\n";
        }
    }

    echo "\nSinkronisasi selesai.\n";
}

// =====================================================
// 11. EKSEKUSI LANGSUNG DARI TERMINAL
// =====================================================

// Ketika file di-include oleh admin.php, jangan menjalankan main()
// secara otomatis. Handler tombol admin harus memanggil main().

$isDirectExecution = isset($_SERVER['SCRIPT_FILENAME'])
    && realpath((string) $_SERVER['SCRIPT_FILENAME'])
        === realpath(__FILE__);

if ($isDirectExecution) {
    try {
        main();
    } catch (Throwable $e) {
        error_log('Scraper cabang gagal: ' . $e->getMessage());

        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, "\nERROR: " . $e->getMessage() . "\n");
            exit(1);
        }

        http_response_code(500);

        echo 'Sinkronisasi gagal: ' . htmlspecialchars(
            $e->getMessage(),
            ENT_QUOTES,
            'UTF-8'
        );
    }
}
