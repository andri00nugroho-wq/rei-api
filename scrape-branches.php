<?php
declare(strict_types=1);


const SOURCE_URL = 'https://rajaemasindonesia.co.id/#lokasi';
const SITE_ORIGIN = 'https://rajaemasindonesia.co.id';

// Sesuaikan dengan konfigurasi database Laragon Anda.
// PENTING: harus database yang sama dengan yang dibaca halaman admin/CMS.
const DB_HOST = '127.0.0.1';
const DB_PORT = '3306';
const DB_NAME = 'rei_cms_v1';
const DB_USER = 'admin_cms';
const DB_PASS = 'M@juterusrajaemas';

const DB_TABLE = 'branches';

const DELETE_MISSING = true;


const DELETE_MIN_KEEP_RATIO = 0.5;
const DELETE_GUARD_MIN_ROWS = 4;

// Kolom yang TIDAK boleh dikosongkan jika hasil scraping kosong.
const PRESERVE_WHEN_EMPTY = ['title', 'address', 'map_embed_url', 'map_link'];

// Tag yang diabaikan saat membaca halaman.
const SKIP_TAGS = [
    'script', 'style', 'noscript', 'svg', 'path', 'template',
    'head', 'title', 'option', 'select', 'textarea', 'canvas',
];

// Tag yang memutus baris teks.
const BLOCK_TAGS = [
    'address', 'article', 'aside', 'blockquote', 'br', 'button',
    'dd', 'div', 'dl', 'dt', 'fieldset', 'figcaption', 'figure',
    'footer', 'form', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'header',
    'hr', 'li', 'main', 'nav', 'ol', 'p', 'section', 'table',
    'tbody', 'thead', 'td', 'th', 'tr', 'ul',
];

const PHONE_LABEL_REGEX =
    '/(?<!\p{L})(?:hp|h\.p|wa|whatsapp|whtasapp|whatsap|telp|tlp|telepon|telephone|phone)(?!\p{L})/iu';

const TIME_RANGE_REGEX =
    '/\d{1,2}[.:]\d{2}\s*(?:-|–|—|s\/d|sd|sampai|hingga)\s*\d{1,2}[.:]\d{2}/iu';

const HOURS_LABEL_ONLY_REGEX =
    '/^(?:buka|jam\s+operasional|jam\s+buka|operasional)\s*:?\s*$/iu';

const DIRECTION_LABEL_REGEX =
    '/menuju\s+lokasi|petunjuk\s+arah|directions?/iu';

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

function curlGet(string $url, bool $verifySsl): array
{
    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_ENCODING       => '',
        CURLOPT_SSL_VERIFYPEER => $verifySsl,
        CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36',
        CURLOPT_HTTPHEADER     => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: id-ID,id;q=0.9,en;q=0.8',
            'Cache-Control: no-cache, no-store, max-age=0',
            'Pragma: no-cache',
        ],
    ]);

    $body = curl_exec($ch);

    return [
        'body'  => $body,
        'error' => curl_error($ch),
        'errno' => curl_errno($ch),
        'code'  => (int) curl_getinfo($ch, CURLINFO_HTTP_CODE),
        'final' => (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL),
    ];
}

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

    $result = curlGet($requestUrl, true);

    // Laragon/Windows sering belum punya sertifikat CA (cURL error 60/77).
    // Karena yang diambil hanya halaman publik, ulangi tanpa verifikasi SSL.
    // Perbaikan permanen: set curl.cainfo di php.ini ke file cacert.pem.
    if ($result['body'] === false && in_array($result['errno'], [60, 77], true)) {
        $log('PERINGATAN: verifikasi SSL gagal (' . $result['error'] . '), mencoba ulang tanpa verifikasi.');
        $log('Perbaikan permanen: isi curl.cainfo di php.ini dengan path cacert.pem.');
        $result = curlGet($requestUrl, false);
    }

    $log("URL sumber : {$url}");
    $log('URL akhir  : ' . $result['final']);
    $log('HTTP       : ' . $result['code']);

    $html = $result['body'];

    if (!is_string($html) || $html === '') {
        throw new RuntimeException('Gagal mengambil HTML website. ' . $result['error']);
    }

    if ($result['code'] < 200 || $result['code'] >= 300) {
        throw new RuntimeException(
            "Website mengembalikan HTTP {$result['code']}. "
            . 'Jika 403/503, kemungkinan server memblokir request otomatis (Cloudflare/WAF).'
        );
    }

    if (
        stripos($html, 'Just a moment...') !== false
        || stripos($html, 'cf-browser-verification') !== false
    ) {
        throw new RuntimeException(
            'Website menampilkan halaman verifikasi bot (Cloudflare), bukan daftar cabang.'
        );
    }

    return $html;
}

// =====================================================
// 4. BANTUAN PEMROSESAN TEKS
// =====================================================

function normalizeText(string $text): string
{
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    $text = str_replace(["\xc2\xa0", "\u{200B}", "\u{FEFF}"], ' ', $text);

    $text = preg_replace('/[\t\r ]+/u', ' ', $text) ?? $text;
    $text = preg_replace('/\s*\n\s*/u', "\n", $text) ?? $text;

    return trim($text);
}

/**
 * Mendapatkan nomor dari format:
 * # 01 Jakarta Utara | 01 Jakarta Utara | Cabang 01 | 1. Jakarta Utara
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

/**
 * Penanda cabang yang ketat (untuk teks biasa non-heading).
 * Lolos : "# 01 Jakarta Utara", "01 Jakarta Utara", "Cabang 01 Bekasi",
 *         "1. Surabaya", "01"
 * Ditolak: teks panjang, "24 jam", "1 gram", "08.00 - 17.00", nomor telepon
 */
function isBranchMarkerText(string $text): bool
{
    $text = normalizeText($text);

    if ($text === '' || mb_strlen($text, 'UTF-8') > 150 || str_contains($text, "\n")) {
        return false;
    }

    if (preg_match('/^\s*#\s*\d{1,3}(?=\s|[-.:]|$)/u', $text)) {
        return extractBranchNumber($text) !== '';
    }

    if (preg_match('/^\s*0\d{1,2}\s*$/u', $text)) {
        return true;
    }

    return preg_match(
        '/^\s*(?:cabang\s*)?\d{1,3}(?=\s|[-.:])\s*[-.:]?\s*'
        . '(?!(?:jam|gram|gr|kg|kilo|hari|tahun|bulan|menit|detik|persen|karat|wib|wita|wit)\b)'
        . '\p{L}{3,}/iu',
        $text
    ) === 1;
}

/**
 * Penanda untuk teks yang berada di dalam heading (h1-h6): lebih longgar
 * karena heading jarang berisi teks lain yang diawali angka.
 */
function isHeadingMarkerText(string $text): bool
{
    $text = normalizeText($text);

    if ($text === '' || mb_strlen($text, 'UTF-8') > 150 || str_contains($text, "\n")) {
        return false;
    }

    if (extractBranchNumber($text) === '') {
        return false;
    }

    // Tolak "24 Jam", "1 Gram", dst.
    if (preg_match(
        '/^\s*(?:#\s*)?(?:cabang\s*)?\d{1,3}\s*[-.:]?\s*'
        . '(?:jam|gram|gr|kg|kilo|hari|tahun|bulan|menit|detik|persen|karat|wib|wita|wit)\b/iu',
        $text
    )) {
        return false;
    }

    return true;
}

function absoluteUrl(string $href): string
{
    $href = trim(html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

    if ($href === '' || $href === '#') {
        return '';
    }

    if (preg_match('/^(?:tel|mailto|javascript|sms):/i', $href)) {
        return '';
    }

    if (str_starts_with($href, '//')) {
        return 'https:' . $href;
    }

    if (preg_match('/^https?:\/\//i', $href)) {
        return $href;
    }

    if (str_starts_with($href, '/')) {
        return SITE_ORIGIN . $href;
    }

    return SITE_ORIGIN . '/' . $href;
}

function isEmbedUrl(string $value): bool
{
    return preg_match('~(?:output=embed|/maps/embed)~i', $value) === 1
        && preg_match('~(?:google|goo\.gl)~i', $value) === 1;
}

// =====================================================
// 5. MENGUBAH HTML MENJADI ALIRAN TOKEN BERURUTAN
// =====================================================

/**
 * Hasil: daftar token berurutan sesuai urutan dokumen.
 *  - ['type' => 'line',  'text' => string, 'heading' => ?string]
 *  - ['type' => 'link',  'href' => string, 'label' => string]
 *  - ['type' => 'frame', 'src'  => string]
 */
function tokenizeDom(DOMNode $root): array
{
    $state = ['tokens' => [], 'buffer' => '', 'heading' => null];

    tokenizeNode($root, $state);
    flushLine($state);

    return $state['tokens'];
}

function flushLine(array &$state): void
{
    $text = normalizeText((string) $state['buffer']);
    $state['buffer'] = '';

    if ($text === '') {
        return;
    }

    // Di dalam heading, gabungkan jadi satu baris. Di luar heading,
    // pisahkan per baris baru.
    $parts = $state['heading'] !== null
        ? [preg_replace('/\s*\n\s*/u', ' ', $text) ?? $text]
        : explode("\n", $text);

    foreach ($parts as $part) {
        $part = trim($part);

        if ($part !== '') {
            $state['tokens'][] = [
                'type'    => 'line',
                'text'    => $part,
                'heading' => $state['heading'],
            ];
        }
    }
}

function tokenizeNode(DOMNode $node, array &$state): void
{
    if ($node instanceof DOMText) {
        $state['buffer'] .= (string) $node->nodeValue;

        return;
    }

    if ($node instanceof DOMDocument) {
        foreach ($node->childNodes as $child) {
            tokenizeNode($child, $state);
        }

        return;
    }

    if (!$node instanceof DOMElement) {
        return; // komentar, processing instruction, dll.
    }

    $tag = strtolower($node->tagName);

    if (in_array($tag, SKIP_TAGS, true)) {
        return;
    }

    // Tautan peta embed bisa berada di iframe atau atribut data-*.
    foreach ($node->attributes ?? [] as $attribute) {
        $value = (string) $attribute->value;

        if (
            isEmbedUrl($value)
            || ($tag === 'iframe' && preg_match('~(?:google\.[a-z.]+/maps|maps\.google)~i', $value))
        ) {
            $state['tokens'][] = ['type' => 'frame', 'src' => $value];
        }
    }

    if ($tag === 'iframe') {
        return;
    }

    $label = '';

    if ($tag === 'a') {
        $href = trim($node->getAttribute('href'));
        $label = normalizeText($node->textContent);

        if ($href !== '' && $href !== '#') {
            $state['tokens'][] = ['type' => 'link', 'href' => $href, 'label' => $label];
        }
    }

    $isHeading = preg_match('/^h[1-6]$/', $tag) === 1;

    // Tombol "Menuju Lokasi" dijadikan baris sendiri agar tidak menempel
    // ke baris jam operasional.
    $isBlock = in_array($tag, BLOCK_TAGS, true)
        || ($tag === 'a' && $label !== '' && preg_match(DIRECTION_LABEL_REGEX, $label) === 1);

    $previousHeading = $state['heading'];

    if ($isBlock) {
        flushLine($state);
    }

    if ($isHeading) {
        $state['heading'] = $tag;
    }

    $state['buffer'] .= ' ';

    foreach ($node->childNodes as $child) {
        tokenizeNode($child, $state);
    }

    $state['buffer'] .= ' ';

    if ($isBlock) {
        flushLine($state);
    }

    if ($isHeading) {
        $state['heading'] = $previousHeading;
    }
}

// =====================================================
// 6. MENCARI PENANDA NOMOR CABANG
// =====================================================

/**
 * Mengembalikan daftar penanda: ['index' => int, 'number' => string, 'text' => string].
 * Jika ada minimal 3 heading bernomor, hanya heading yang dipakai
 * (lebih aman dari teks lain yang kebetulan diawali angka).
 */
function findMarkers(array $tokens, callable $log): array
{
    $fromHeadings = [];
    $fromText = [];

    foreach ($tokens as $index => $token) {
        if ($token['type'] !== 'line') {
            continue;
        }

        $text = $token['text'];

        if ($token['heading'] !== null && isHeadingMarkerText($text)) {
            $fromHeadings[] = [
                'index'  => $index,
                'number' => extractBranchNumber($text),
                'text'   => $text,
            ];
        }

        if (isBranchMarkerText($text)) {
            $number = extractBranchNumber($text);

            if ($number !== '') {
                $fromText[] = ['index' => $index, 'number' => $number, 'text' => $text];
            }
        }
    }

    if (count($fromHeadings) >= 3) {
        $log('Mode penanda: heading (' . count($fromHeadings) . ' ditemukan)');
        $chosen = $fromHeadings;
    } else {
        $log('Mode penanda: teks biasa (' . count($fromText) . ' ditemukan)');
        $chosen = $fromText;
    }

    // Tambahan: nomor yang berdiri sendiri di barisnya ("119" lalu nama di baris
    // berikutnya) diterima jika nomornya melanjutkan urutan (lebih besar dari
    // penanda sebelumnya, maksimal selisih 10). Urutan ini mencegah angka lain
    // di dalam kartu salah dianggap penanda.
    $byIndex = [];

    foreach ($chosen as $marker) {
        $byIndex[$marker['index']] = $marker;
    }

    $merged = [];
    $previous = 0;

    foreach ($tokens as $index => $token) {
        if (isset($byIndex[$index])) {
            $merged[] = $byIndex[$index];
            $previous = (int) $byIndex[$index]['number'];
            continue;
        }

        if ($previous === 0 || $token['type'] !== 'line') {
            continue;
        }

        if (preg_match('/^\s*(?:#\s*)?(\d{1,3})\s*$/u', $token['text'], $m)) {
            $n = (int) $m[1];

            if ($n > $previous && $n <= $previous + 10) {
                $merged[] = [
                    'index'  => $index,
                    'number' => (string) $n,
                    'text'   => $token['text'],
                ];
                $previous = $n;
                $log('Penanda nomor berdiri sendiri diterima: ' . $token['text']);
            }
        }
    }

    return $merged;
}

// =====================================================
// 7. EKSTRAKSI DATA DARI SATU SEGMEN CABANG
// =====================================================

function extractPhoneNumbers(string $text): array
{
    $text = preg_replace('~https?://\S+~iu', ' ', $text) ?? $text;

    if (!preg_match_all(
        '/(?<!\d)(?:\+?62|0)[\d\s().-]{7,}\d/u',
        $text,
        $matches
    )) {
        return [];
    }

    $numbers = [];

    foreach ($matches[0] as $raw) {
        $number = trim(preg_replace('/\s+/u', ' ', $raw) ?? $raw);
        $digits = preg_replace('/\D+/', '', $number) ?? '';

        if (strlen($digits) >= 9 && strlen($digits) <= 15) {
            $numbers[] = $number;
        }
    }

    return $numbers;
}

function pickMapEmbedUrl(array $frames, array $links, array $lines): string
{
    foreach ($frames as $frame) {
        $url = absoluteUrl($frame);

        if ($url !== '') {
            return $url;
        }
    }

    foreach ($links as $link) {
        if (isEmbedUrl($link['href'])) {
            return absoluteUrl($link['href']);
        }
    }

    foreach ($links as $link) {
        if (isEmbedUrl($link['label'])) {
            return absoluteUrl($link['label']);
        }
    }

    foreach ($lines as $line) {
        if (preg_match('~https?://\S+~iu', $line, $m) && isEmbedUrl($m[0])) {
            return absoluteUrl(rtrim($m[0], '.,;)'));
        }
    }

    // Cadangan: tautan yang berlabel "Map".
    foreach ($links as $link) {
        if (preg_match('/^\s*(?:lihat\s+)?map\b/iu', $link['label'])) {
            $url = absoluteUrl($link['href']);

            if ($url !== '') {
                return $url;
            }
        }
    }

    return '';
}

function pickDirectionUrl(array $links): string
{
    foreach ($links as $link) {
        if (preg_match(DIRECTION_LABEL_REGEX, $link['label'])) {
            $url = absoluteUrl($link['href']);

            if ($url !== '' && !isEmbedUrl($url)) {
                return $url;
            }
        }
    }

    foreach ($links as $link) {
        if (preg_match(
            '~(?:maps\.app\.goo\.gl|goo\.gl/maps|share\.google|google\.[a-z.]+/maps/(?:place|dir)|maps\.google\.[a-z.]+/maps/place)~i',
            $link['href']
        )) {
            $url = absoluteUrl($link['href']);

            if ($url !== '' && !isEmbedUrl($url)) {
                return $url;
            }
        }
    }

    return '';
}

/**
 * Mengubah token satu segmen (antara dua judul cabang) menjadi data cabang.
 */
function parseSegment(array $marker, array $slice): array
{
    $lines = [];
    $links = [];
    $frames = [];

    foreach ($slice as $token) {
        if ($token['type'] === 'line') {
            if ($lines === [] || end($lines) !== $token['text']) {
                $lines[] = $token['text'];
            }
        } elseif ($token['type'] === 'link') {
            $links[] = $token;
        } elseif ($token['type'] === 'frame') {
            $frames[] = $token['src'];
        }
    }

    $title = removeBranchNumber($marker['text']);

    $phones = [];
    $hours = [];
    $notes = '';
    $addressParts = [];
    $expectPhone = false;

    $count = count($lines);

    for ($i = 0; $i < $count; $i++) {
        $line = $lines[$i];
        $clean = trim(preg_replace('~https?://\S+~iu', '', $line) ?? $line);

        if ($clean === '') {
            continue; // baris yang isinya hanya URL
        }

        // Jam operasional (jika "Catatan:" menempel di baris yang sama, pisahkan).
        if (preg_match(TIME_RANGE_REGEX, $clean)) {
            if (preg_match('/^(.*?)\s+(?:catatan|keterangan)\s*:\s*(.+)$/iu', $clean, $split)) {
                $clean = trim($split[1]);

                if ($notes === '') {
                    $notes = trim($split[2]);
                }
            }

            $prefix = ($i > 0 && preg_match(HOURS_LABEL_ONLY_REGEX, $lines[$i - 1])) ? 'Buka : ' : '';
            $hours[] = $prefix . $clean;
            $expectPhone = false;
            continue;
        }

        if (preg_match(HOURS_LABEL_ONLY_REGEX, $clean)) {
            continue;
        }

        // Catatan.
        if (preg_match('/^(?:catatan|keterangan|notes?)\s*[:\-]\s*(.+)$/iu', $clean, $m)) {
            $notes = trim($m[1]);
            $expectPhone = false;
            continue;
        }

        if (preg_match('/^buka\s+setiap\s+hari\.?$/iu', $clean)) {
            if ($notes === '') {
                $notes = $clean;
            }

            $expectPhone = false;
            continue;
        }

        // Telepon / WhatsApp.
        $numbers = extractPhoneNumbers($clean);
        $isLabel = preg_match(PHONE_LABEL_REGEX, $clean) === 1;
        $digits = preg_replace('/\D+/', '', $clean) ?? '';
        $isNumberOnly = preg_match('/^\+?[\d\s().-]{8,}$/u', $clean) === 1 && strlen($digits) >= 9;

        if ($isLabel || $isNumberOnly || ($expectPhone && $numbers !== [])) {
            if ($numbers !== []) {
                array_push($phones, ...$numbers);
                $expectPhone = false;
            } elseif ($isLabel) {
                $expectPhone = true; // nomor ada di baris berikutnya
            }

            continue;
        }

        $expectPhone = false;

        // Sisanya dianggap bagian alamat.
        $clean = preg_replace('/^\s*alamat\s*[:\-]\s*/iu', '', $clean) ?? $clean;
        $clean = trim($clean);

        if (
            mb_strlen($clean, 'UTF-8') < 3
            || preg_match('/^[^\p{L}\d]+$/u', $clean)
            || preg_match('/^(?:map|menuju lokasi|petunjuk arah|lihat peta|hubungi kami|online|offline|mulai chat)$/iu', $clean)
        ) {
            continue;
        }

        $addressParts[] = $clean;
    }

    // Judul kosong (nomor berdiri sendiri): pakai baris pertama sebagai judul.
    if ($title === '' && $addressParts !== []) {
        $title = (string) array_shift($addressParts);
    }

    $address = '';

    foreach ($addressParts as $part) {
        if ($address === '') {
            $address = $part;
        } else {
            $glue = (preg_match('/[,;.\-]$/u', $address) || str_starts_with($part, '(')) ? ' ' : ', ';
            $address .= $glue . $part;
        }
    }

    // Cadangan nomor telepon dari tautan wa.me.
    if ($phones === []) {
        foreach ($links as $link) {
            if (preg_match('~wa\.me/\+?\s*(\d{8,15})~i', $link['href'], $m)) {
                $digits = $m[1];
                $phones[] = str_starts_with($digits, '62') ? '0' . substr($digits, 2) : $digits;
            }
        }
    }

    $phones = array_values(array_unique($phones));

    return [
        'branch_number' => (string) ((int) $marker['number']),
        'title'         => $title !== '' ? $title : 'Cabang ' . (int) $marker['number'],
        'address'       => trim($address),
        'phone'         => implode(' / ', $phones),
        'hours'         => implode('; ', $hours),
        'notes'         => $notes,
        'map_embed_url' => pickMapEmbedUrl($frames, $links, $lines),
        'map_link'      => pickDirectionUrl($links),
    ];
}

// =====================================================
// 8. PARSING SEMUA CABANG
// =====================================================

function parseBranches(string $html, callable $log): array
{
    if (!class_exists(DOMDocument::class)) {
        throw new RuntimeException(
            'Ekstensi PHP DOM belum aktif. Aktifkan extension=dom di php.ini.'
        );
    }

    $previous = libxml_use_internal_errors(true);

    $dom = new DOMDocument('1.0', 'UTF-8');

    $loaded = $dom->loadHTML(
        '<?xml encoding="UTF-8">' . $html,
        LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_PARSEHUGE
    );

    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    if (!$loaded) {
        throw new RuntimeException('HTML website gagal diproses.');
    }

    $body = $dom->getElementsByTagName('body')->item(0);
    $tokens = tokenizeDom($body ?? $dom);

    $log('Token halaman : ' . count($tokens));

    $markers = findMarkers($tokens, $log);

    $branches = [];
    $markerCount = count($markers);

    // Heading di tengah daftar cabang yang TIDAK bernomor: kemungkinan cabang
    // baru yang belum diberi nomor di website, sehingga tidak terbaca.
    if ($markerCount >= 2) {
        $markerIndexes = array_column($markers, 'index');
        $first = min($markerIndexes);
        $lastIndex = max($markerIndexes);

        for ($i = $first; $i <= $lastIndex; $i++) {
            if (
                $tokens[$i]['type'] === 'line'
                && $tokens[$i]['heading'] !== null
                && !in_array($i, $markerIndexes, true)
                && !in_array($i - 1, $markerIndexes, true)
            ) {
                $log('PERINGATAN: heading tanpa nomor di antara daftar cabang: "' . $tokens[$i]['text'] . '"');
            }
        }
    }

    foreach ($markers as $position => $marker) {
        $start = $marker['index'] + 1;
        $end = $position + 1 < $markerCount
            ? $markers[$position + 1]['index']
            : count($tokens);

        $slice = array_slice($tokens, $start, $end - $start);

        // Segmen terakhir: berhenti di heading pertama (bagian lain halaman).
        if ($position + 1 === $markerCount) {
            // Heading pendek tepat setelah nomor adalah nama cabang
            // (mis. "119" lalu "Cipulir"), bukan bagian lain halaman.
            $leading = 0;

            while (
                $leading < 2
                && isset($slice[$leading])
                && $slice[$leading]['type'] === 'line'
                && $slice[$leading]['heading'] !== null
                && mb_strlen($slice[$leading]['text'], 'UTF-8') <= 80
            ) {
                $leading++;
            }

            foreach ($slice as $offset => $token) {
                if ($offset < $leading) {
                    continue;
                }

                if ($token['type'] === 'line' && $token['heading'] !== null) {
                    $slice = array_slice($slice, 0, $offset);
                    break;
                }
            }
        }

        $branch = parseSegment($marker, $slice);

        // Segmen tanpa data apa pun bukan kartu cabang.
        if (
            $branch['address'] === ''
            && $branch['phone'] === ''
            && $branch['map_embed_url'] === ''
            && $branch['map_link'] === ''
        ) {
            $log(
                'PERINGATAN: judul "' . $marker['text'] . '" dilewati karena tidak ada '
                . 'alamat/telepon/peta di bawahnya (' . count($slice) . ' token).'
            );
            continue;
        }

        $key = $branch['branch_number'];

        if (!isset($branches[$key])) {
            $branches[$key] = $branch;
            continue;
        }

        $log(
            "CATATAN: nomor {$key} muncul lebih dari sekali (\""
            . $branches[$key]['title'] . '" dan "' . $branch['title']
            . '"). Dipakai data yang terakhir; kolom yang kosong dilengkapi dari yang pertama.'
        );

        foreach ($branch as $field => $value) {
            if ($value !== '') {
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

function diagnoseBranches(array $branches, callable $log): void
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

    $missing = array_values(array_diff(range(1, $max), $numbers));

    if ($missing !== []) {
        $log(
            'PERINGATAN: nomor tidak ditemukan di HTML: '
            . implode(', ', array_slice($missing, 0, 30))
            . (count($missing) > 30 ? ' ...' : '')
        );
    }

    $noAddress = 0;
    $noMap = 0;

    foreach ($branches as $branch) {
        if ($branch['address'] === '') {
            $noAddress++;
        }

        if ($branch['map_embed_url'] === '') {
            $noMap++;
        }
    }

    if ($noAddress > 0 || $noMap > 0) {
        $log("CATATAN: {$noAddress} cabang tanpa alamat terbaca, {$noMap} cabang tanpa URL peta embed.");
    }
}

// =====================================================
// 9. PASTIKAN TABEL TERSEDIA
// =====================================================

function ensureTable(PDO $pdo): void
{
    // Skema sama dengan tabel yang dibuat admin.php. Sengaja TANPA indeks unik
    // dan tanpa mengubah struktur tabel yang sudah ada.
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
// 10. SINKRONISASI DATABASE
// =====================================================

/**
 * Menambah atau memperbarui satu cabang.
 * Mengembalikan: 'inserted' | 'updated' | 'unchanged'
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
// 11. PROSES UTAMA
// =====================================================

/**
 * Ambil dari website -> parse -> sinkron ke DB (tambah, ubah, hapus).
 *
 * @param callable|null $logger fn(string $line): void, opsional
 * @param PDO|null      $pdo    koneksi dari pemanggil (admin.php), opsional
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

    $pdo = $pdo ?? connectDatabase();

    $log('Database     : ' . (string) $pdo->query('SELECT DATABASE()')->fetchColumn());
    $log('Tabel        : ' . DB_TABLE);

    ensureTable($pdo);

    $html = fetchWebsite(SOURCE_URL, $log);

    $log('Ukuran HTML  : ' . number_format(strlen($html)) . ' bytes');

    $branches = parseBranches($html, $log);

    $log('Jumlah cabang berhasil diparsing: ' . count($branches));

    if ($branches === []) {
        throw new RuntimeException(
            'Tidak ada cabang yang berhasil diparsing, DB tidak diubah. ' .
            'Kemungkinan daftar cabang dimuat lewat JavaScript atau struktur HTML berubah.'
        );
    }

    diagnoseBranches($branches, $log);

    $dupes = $pdo->query(
        'SELECT CAST(branch_number AS UNSIGNED) AS n, COUNT(*) AS c FROM `' . DB_TABLE . '`
         GROUP BY CAST(branch_number AS UNSIGNED) HAVING COUNT(*) > 1'
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
        . str_pad('TELP', 8)
        . str_pad('MAP', 8)
        . 'MENUJU LOKASI'
    );
    $log(str_repeat('-', 118));

    $inserted = 0;
    $updated = 0;
    $unchanged = 0;
    $deleted = 0;
    $failed = 0;
    $failures = [];

    $ownTransaction = !$pdo->inTransaction();

    if ($ownTransaction) {
        $pdo->beginTransaction();
    }

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
                    . str_pad($branch['phone'] !== '' ? 'YA' : 'KOSONG', 8)
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

        $deleted = deleteMissingBranches(
            $pdo,
            array_column($branches, 'branch_number'),
            $log
        );

        if ($ownTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $e) {
        if ($ownTransaction && $pdo->inTransaction()) {
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

// =====================================================
// 12. PENJALAN OTOMATIS (hanya jika file ini dipanggil langsung)
// =====================================================

function isDirectExecution(): bool
{
    $script = $_SERVER['SCRIPT_FILENAME'] ?? ($_SERVER['argv'][0] ?? '');

    if (!is_string($script) || $script === '') {
        return false;
    }

    $a = realpath($script);
    $b = realpath(__FILE__);

    return $a !== false && $b !== false && $a === $b;
}

if (isDirectExecution()) {
    if (PHP_SAPI !== 'cli') {
        // Akses langsung lewat browser hanya diizinkan dari komputer lokal,
        // karena proses ini bisa menghapus data. Di server publik, panggil
        // runSync() dari admin.php setelah cek login + CSRF.
        $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

        if (!in_array($remote, ['127.0.0.1', '::1'], true)) {
            http_response_code(403);
            exit('Forbidden');
        }

        if (!headers_sent()) {
            header('Content-Type: text/plain; charset=utf-8');
        }
    }

    try {
        main();
    } catch (Throwable $e) {
        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, "\nERROR: " . $e->getMessage() . "\n");
            exit(1);
        }

        http_response_code(500);
        echo 'ERROR: ' . $e->getMessage();
    }
}
