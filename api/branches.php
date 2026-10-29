<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

function jsonResponse(int $status, array $data): never
{
    http_response_code($status);

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_INVALID_UTF8_SUBSTITUTE
    );

    exit;
}


const BRANCH_COLUMN_ALIASES = [
    'branch_number' => ['branch_number', 'nomor_cabang', 'no_cabang', 'nomor', 'number', 'no'],
    'title'         => ['title', 'name', 'nama', 'nama_cabang', 'branch_name'],
    'address'       => ['address', 'alamat'],
    'phone'         => ['phone', 'telepon', 'telp', 'hp', 'whatsapp', 'phone_whatsapp', 'hp_whatsapp', 'wa'],
    'hours'         => ['hours', 'jam', 'jam_operasional', 'jam_buka', 'opening_hours', 'open_hours', 'buka'],
    'notes'         => ['notes', 'note', 'catatan', 'keterangan'],
    'map_link'      => ['map_link', 'maps_link', 'link_peta', 'map_url'],
    'map_embed_url' => ['map_embed_url', 'map_embed', 'embed_peta', 'embed_url'],
    'sort_order'    => ['sort_order'],
    'latitude'      => ['latitude'],
    'longitude'     => ['longitude'],
    'status'        => ['status'],
    'opening_date'  => ['opening_date'],
    'city'          => ['city'],
    'province'      => ['province'],
    'district'      => ['district'],
];

const BRANCH_STATUSES = ['active', 'coming_soon', 'temp_closed', 'closed'];

function cleanText(mixed $v): string
{
    $s = trim((string) ($v ?? ''));

    if ($s === '') {
        return '';
    }

    $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $s = preg_replace('/<br\s*\/?>/i', "\n", $s) ?? $s;
    $s = strip_tags($s);
    $s = preg_replace('/[ \t\x{00A0}]+/u', ' ', $s) ?? $s;
    $s = preg_replace('/\s*\R\s*/u', "\n", $s) ?? $s;

    return trim($s);
}

function validCoordinate(mixed $value, float $minimum, float $maximum): ?float
{
    if (!is_numeric($value)) {
        return null;
    }

    $coordinate = (float) $value;

    return is_finite($coordinate) && $coordinate >= $minimum && $coordinate <= $maximum
        ? $coordinate
        : null;
}

function requestCoordinates(): array
{
    $hasLatitude = array_key_exists('latitude', $_GET);
    $hasLongitude = array_key_exists('longitude', $_GET);

    if (!$hasLatitude && !$hasLongitude) {
        return [null, null];
    }

    if (!$hasLatitude || !$hasLongitude) {
        jsonResponse(400, [
            'success' => false,
            'message' => 'Latitude dan longitude pengguna harus diberikan bersama-sama.'
        ]);
    }

    $latitude = validCoordinate($_GET['latitude'], -90, 90);
    $longitude = validCoordinate($_GET['longitude'], -180, 180);

    if ($latitude === null || $longitude === null) {
        jsonResponse(400, [
            'success' => false,
            'message' => 'Koordinat pengguna tidak valid.'
        ]);
    }

    return [$latitude, $longitude];
}

function distanceInKilometers(float $latitude, float $longitude, float $otherLatitude, float $otherLongitude): float
{
    $latitudeDelta = deg2rad($otherLatitude - $latitude);
    $longitudeDelta = deg2rad($otherLongitude - $longitude);
    $a = sin($latitudeDelta / 2) ** 2
        + cos(deg2rad($latitude))
        * cos(deg2rad($otherLatitude))
        * sin($longitudeDelta / 2) ** 2;
    $a = min(1.0, max(0.0, $a));

    return 6371.0088 * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

function validOpeningDate(mixed $value): ?string
{
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return null;
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    $errors = DateTimeImmutable::getLastErrors();

    if (
        $date === false
        || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
        || $date->format('Y-m-d') !== $value
    ) {
        return null;
    }

    return $value;
}

function normalizeBranchInput(array $data): array
{
    foreach (['latitude' => [-90, 90], 'longitude' => [-180, 180]] as $field => [$minimum, $maximum]) {
        if (!array_key_exists($field, $data)) {
            continue;
        }

        if ($data[$field] === null || $data[$field] === '') {
            $data[$field] = null;
            continue;
        }

        $coordinate = validCoordinate($data[$field], $minimum, $maximum);

        if ($coordinate === null) {
            jsonResponse(422, [
                'success' => false,
                'message' => "{$field} harus berupa angka dalam rentang {$minimum} sampai {$maximum}."
            ]);
        }

        $data[$field] = number_format($coordinate, 7, '.', '');
    }

    if (array_key_exists('status', $data)) {
        $status = $data['status'];

        if ($status === '') {
            $status = null;
        }

        if ($status !== null && (!is_string($status) || !in_array($status, BRANCH_STATUSES, true))) {
            jsonResponse(422, [
                'success' => false,
                'message' => 'status harus active, coming_soon, temp_closed, closed, atau null.'
            ]);
        }

        $data['status'] = $status;
    }

    if (array_key_exists('opening_date', $data)) {
        $openingDate = $data['opening_date'];

        if ($openingDate === '') {
            $openingDate = null;
        }

        if ($openingDate !== null && validOpeningDate($openingDate) === null) {
            jsonResponse(422, [
                'success' => false,
                'message' => 'opening_date harus berformat YYYY-MM-DD atau null.'
            ]);
        }

        $data['opening_date'] = $openingDate;
    }

    foreach (['city', 'province', 'district'] as $field) {
        if (array_key_exists($field, $data)) {
            $data[$field] = $data[$field] === null || trim((string) $data[$field]) === ''
                ? null
                : trim((string) $data[$field]);
        }
    }

    return $data;
}

function rejectUnavailableBranchFields(array $input, array $columnMap): void
{
    $requiredFields = ['latitude', 'longitude', 'status', 'opening_date', 'city', 'province', 'district'];
    $unavailable = array_diff(
        array_intersect(array_keys($input), $requiredFields),
        array_keys($columnMap)
    );

    if ($unavailable !== []) {
        jsonResponse(503, [
            'success' => false,
            'message' => 'Kolom cabang belum dimigrasikan: ' . implode(', ', $unavailable) . '.'
        ]);
    }
}

const REJECT_NUMERIC_TITLES = true;

function isJunkTitle(string $title): bool
{
    $t = cleanText($title);

    return $t !== '' && (bool) preg_match('/^[\d\s\-+().]+$/', $t);
}

function blockKey(string $title): string
{
    $t = cleanText($title);

    if (
        preg_match('/^(.*?)\s*No\.\s*(\d+)$/u', $t, $m)
        || preg_match('/^(.*?)\s+No\s+(\d+)$/u', $t, $m)
    ) {
        if (trim($m[1]) !== '') {
            $t = rtrim(trim($m[1]), ' -,');
        }
    }

    $t = mb_strtolower($t, 'UTF-8');
    $t = preg_replace('/\s+/u', ' ', $t) ?? $t;

    return mb_substr(trim($t), 0, 191, 'UTF-8');
}

function mapQuery(string $url): string
{
    if ($url === '') {
        return '';
    }

    $qs = parse_url($url, PHP_URL_QUERY);

    if (!is_string($qs) || $qs === '') {
        return '';
    }

    parse_str($qs, $p);

    foreach (['q', 'query'] as $key) {
        if (!empty($p[$key]) && is_string($p[$key])) {
            $q = trim($p[$key]);
            if (preg_match('/^-?\d+(?:\.\d+)?,\s*-?\d+(?:\.\d+)?$/', $q)) {
                return '';
            }

            return $q;
        }
    }

    return '';
}

function fillBranch(array $b, ?float $userLatitude = null, ?float $userLongitude = null): array
{
    $title   = cleanText($b['title'] ?? '');
    $rawTitle = $title;
    $number  = cleanText($b['branch_number'] ?? '');
    $address = cleanText($b['address'] ?? '');
    $phone   = cleanText($b['phone'] ?? '');
    $hours   = cleanText($b['hours'] ?? '');
    $notes   = cleanText($b['notes'] ?? '');
    $link    = trim((string) ($b['map_link'] ?? ''));
    $embed   = trim((string) ($b['map_embed_url'] ?? ''));

    if ($embed !== '' && preg_match('/src=["\']([^"\']+)["\']/i', $embed, $m)) {
        $embed = html_entity_decode($m[1]);
    }
    if (
        preg_match('/^(.*?)\s*No\.\s*(\d+)$/u', $title, $m)
        || preg_match('/^(.*?)\s+No\s+(\d+)$/u', $title, $m)
    ) {
        if (trim($m[1]) !== '') {
            $title = rtrim(trim($m[1]), " -,");

            if ($number === '') {
                $number = $m[2];
            }
        }
    }

    if ($number !== '' && preg_match('/(\d+)/', $number, $m)) {
        $number = $m[1];
    }

    if ($title !== '' && preg_match('/^[\d\s\-+().]+$/', $title)) {
        if ($phone === '') {
            $phone = $title;
        }

        $title = '';
    }

    if ($address === '') {
        $q = mapQuery($embed) ?: mapQuery($link);

        if ($q !== '' && $title !== '' && stripos($q, $title) === 0) {
            $rest = trim(substr($q, strlen($title)), " ,-");

            if ($rest !== '') {
                $address = $rest;
            }
        }
    }

    $haystack = $notes . ' ' . $address;

    if (
        $hours === ''
        && preg_match('/(\d{1,2}[.:]\d{2})\s*[-–]\s*(\d{1,2}[.:]\d{2})/u', $haystack, $m)
    ) {
        $hours = 'Pukul ' . $m[1] . ' - ' . $m[2] . ' WIB';
    }

    if (
        $phone === ''
        && preg_match('/(?:\+62|62|0)8\d{1,3}[\s\-]?\d{3,4}[\s\-]?\d{3,5}/', $haystack, $m)
    ) {
        $phone = trim($m[0]);
    }

    $query = trim($title . ' ' . $address);

    if ($embed === '') {
        $q = preg_match('/@(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/', $link, $m)
            ? $m[1] . ',' . $m[2]
            : $query;

        if ($q !== '') {
            $embed = 'https://www.google.com/maps?q=' . rawurlencode($q) . '&output=embed';
        }
    }

    if ($link === '' && $query !== '') {
        $link = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($query);
    }

    $b['raw_title']     = $rawTitle;
    $b['is_junk']       = isJunkTitle($rawTitle);
    $b['branch_number'] = $number === '' ? null : (int) $number;
    $b['title']         = $title;
    $b['address']       = $address;
    $b['phone']         = $phone;
    $b['hours']         = $hours;
    $b['notes']         = $notes;
    $b['map_link']      = $link;
    $b['map_embed_url'] = $embed;
    $b['sort_order'] = isset($b['sort_order']) && is_numeric($b['sort_order'])
        ? (int) $b['sort_order']
        : null;
    $latitude = validCoordinate($b['latitude'] ?? null, -90, 90);
    $longitude = validCoordinate($b['longitude'] ?? null, -180, 180);
    $b['latitude'] = $latitude;
    $b['longitude'] = $longitude;
    $b['status'] = isset($b['status']) && in_array($b['status'], BRANCH_STATUSES, true)
        ? $b['status']
        : null;
    $b['opening_date'] = validOpeningDate($b['opening_date'] ?? null);

    foreach (['city', 'province', 'district'] as $field) {
        $value = trim((string) ($b[$field] ?? ''));
        $b[$field] = $value === '' ? null : $value;
    }

    $b['distance_km'] = $userLatitude !== null
        && $userLongitude !== null
        && $latitude !== null
        && $longitude !== null
            ? round(distanceInKilometers($userLatitude, $userLongitude, $latitude, $longitude), 2)
            : null;

    $missing = [];

    foreach (['title', 'address', 'phone', 'hours', 'notes'] as $field) {
        if ($b[$field] === '') {
            $missing[] = $field;
        }
    }

    $b['missing_fields'] = $missing;

    return $b;
}


/**
 * Ambil nilai header request dari berbagai sumber.
 * Hosting (CGI/FastCGI/LiteSpeed) sering membuang header Authorization
 * dari $_SERVER, jadi semua jalur alternatif dicek satu per satu.
 */
function getRequestHeader(string $name): string
{
    $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));

    foreach ([$serverKey, 'REDIRECT_' . $serverKey, 'REDIRECT_REDIRECT_' . $serverKey] as $key) {
        if (!empty($_SERVER[$key]) && is_string($_SERVER[$key])) {
            return trim($_SERVER[$key]);
        }
    }

    $sources = [];

    if (function_exists('getallheaders')) {
        $h = getallheaders();
        if (is_array($h)) {
            $sources[] = $h;
        }
    }

    if (function_exists('apache_request_headers')) {
        $h = apache_request_headers();
        if (is_array($h)) {
            $sources[] = $h;
        }
    }

    foreach ($sources as $headers) {
        foreach ($headers as $hName => $hValue) {
            if (strcasecmp((string) $hName, $name) === 0 && is_string($hValue) && trim($hValue) !== '') {
                return trim($hValue);
            }
        }
    }

    return '';
}

/**
 * Ambil token API dari request.
 * Urutan: Authorization: Bearer ... lalu X-API-Token / X-Authorization.
 * Mengembalikan [token, headerTerbaca].
 */
function extractApiToken(): array
{
    $authorization = getRequestHeader('Authorization');
    $received = $authorization !== '';

    if ($authorization !== '' && preg_match('/^Bearer\s+(.+)$/i', $authorization, $m)) {
        return [trim($m[1]), true];
    }

    foreach (['X-API-Token', 'X-Authorization', 'X-Auth-Token'] as $custom) {
        $value = getRequestHeader($custom);

        if ($value !== '') {
            $received = true;

            if (preg_match('/^Bearer\s+(.+)$/i', $value, $m)) {
                return [trim($m[1]), true];
            }

            return [$value, true];
        }
    }

    return ['', $received];
}

/**
 * Bersihkan token dari konfigurasi (spasi, baris baru, tanda kutip nyasar).
 */
function normalizeToken(string $token): string
{
    $token = trim($token);
    $token = trim($token, "\"'");

    return trim($token);
}


$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

$allowedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

if (!in_array($method, $allowedMethods, true)) {
    header('Allow: GET, POST, PUT, PATCH, DELETE');

    jsonResponse(405, [
        'success' => false,
        'message' => 'Method tidak diizinkan.'
    ]);
}



$configPath = __DIR__ . '/api-config.php';

if (!is_file($configPath)) {
    jsonResponse(500, [
        'success' => false,
        'message' => 'File konfigurasi API tidak ditemukan.'
    ]);
}

require_once $configPath;

if (
    !defined('REI_API_TOKEN')
    || !is_string(REI_API_TOKEN)
    || normalizeToken(REI_API_TOKEN) === ''
) {
    jsonResponse(500, [
        'success' => false,
        'message' => 'Konfigurasi API token belum benar.'
    ]);
}

$expectedToken = normalizeToken(REI_API_TOKEN);



[$token, $authHeaderReceived] = extractApiToken();
$token = normalizeToken($token);

if ($token === '' || !hash_equals($expectedToken, $token)) {
    header('WWW-Authenticate: Bearer realm="REI API"');

    jsonResponse(401, [
        'success' => false,
        'message' => 'API token tidak valid.',
        'hint' => !$authHeaderReceived
            ? 'Header Authorization tidak sampai ke PHP. Tambahkan aturan di .htaccess atau kirim lewat header X-API-Token.'
            : 'Token yang dikirim tidak sama dengan REI_API_TOKEN di server.'
    ]);
}



try {
    $databaseConfig = dirname(__DIR__) . '/config.php';

    if (!is_file($databaseConfig)) {
        throw new RuntimeException('File config.php tidak ditemukan.');
    }

    require_once $databaseConfig;

    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new RuntimeException('Koneksi PDO $pdo tidak ditemukan.');
    }

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    error_log('[REI API CABANG][DATABASE] ' . $e->getMessage());

    jsonResponse(500, [
        'success' => false,
        'message' => 'Koneksi database gagal.'
    ]);
}


try {
    $tableCheck = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
         AND table_name = ?'
    );

    $tableCheck->execute(['branches']);

    if ((int) $tableCheck->fetchColumn() === 0) {
        jsonResponse(404, [
            'success' => false,
            'message' => 'Tabel branches tidak ditemukan.'
        ]);
    }

    $columnQuery = $pdo->query('SHOW COLUMNS FROM `branches`');
    $columnInfo = $columnQuery->fetchAll();

    $availableColumns = array_column($columnInfo, 'Field');

    if (!in_array('id', $availableColumns, true)) {
        jsonResponse(500, [
            'success' => false,
            'message' => 'Tabel branches tidak memiliki kolom id.'
        ]);
    }

    $allowedFields = array_keys(BRANCH_COLUMN_ALIASES);
    $lowerColumns = [];

    foreach ($availableColumns as $col) {
        $lowerColumns[strtolower((string) $col)] = $col;
    }

    $columnMap = [];

    foreach (BRANCH_COLUMN_ALIASES as $canonical => $aliases) {
        foreach ($aliases as $alias) {
            if (isset($lowerColumns[$alias])) {
                $columnMap[$canonical] = $lowerColumns[$alias];
                break;
            }
        }
    }

    $writableColumns = array_keys($columnMap);

    if (empty($writableColumns)) {
        jsonResponse(500, [
            'success' => false,
            'message' => 'Kolom cabang yang bisa ditulis tidak ditemukan.'
        ]);
    }

    $blocklistReady = false;

    try {
        $blCheck = $pdo->prepare(
            'SELECT COUNT(*)
             FROM information_schema.tables
             WHERE table_schema = DATABASE()
             AND table_name = ?'
        );

        $blCheck->execute(['branches_blocklist']);

        if ((int) $blCheck->fetchColumn() === 0) {
            $pdo->exec(
                'CREATE TABLE `branches_blocklist` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `block_key` VARCHAR(191) NOT NULL,
                    `title` VARCHAR(255) NULL,
                    `branch_number` VARCHAR(50) NULL,
                    `reason` VARCHAR(255) NULL,
                    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uq_block_key` (`block_key`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            );
        }

        $blocklistReady = true;
    } catch (Throwable $e) {
        error_log('[REI API CABANG][BLOCKLIST] ' . $e->getMessage());
    }

    $selectParts = ['`id`'];

    foreach ($columnMap as $canonical => $actual) {
        $selectParts[] = '`' . $actual . '` AS `' . $canonical . '`';
    }

    $selectSQL = implode(', ', $selectParts);


    $id = null;

    if (isset($_GET['id'])) {
        $parsedId = filter_var(
            $_GET['id'],
            FILTER_VALIDATE_INT
        );

        if ($parsedId === false || $parsedId < 1) {
            jsonResponse(400, [
                'success' => false,
                'message' => 'ID cabang tidak valid.'
            ]);
        }

        $id = $parsedId;
    }

    [$userLatitude, $userLongitude] = requestCoordinates();


    $readJsonBody = static function (): array {
        $raw = file_get_contents('php://input');

        if ($raw === false || trim($raw) === '') {
            jsonResponse(400, [
                'success' => false,
                'message' => 'Body JSON tidak boleh kosong.'
            ]);
        }

        try {
            $data = json_decode(
                $raw,
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException $e) {
            jsonResponse(400, [
                'success' => false,
                'message' => 'Format JSON tidak valid.'
            ]);
        }

        if (!is_array($data) || array_is_list($data)) {
            jsonResponse(400, [
                'success' => false,
                'message' => 'Body harus berupa objek JSON.'
            ]);
        }

        return $data;
    };


    if (strtolower((string) ($_GET['resource'] ?? '')) === 'blocklist') {
        if (!$blocklistReady) {
            jsonResponse(500, [
                'success' => false,
                'message' => 'Tabel branches_blocklist tidak tersedia dan tidak bisa dibuat otomatis.'
            ]);
        }

        if ($method === 'GET') {
            $rows = $pdo->query(
                'SELECT `id`, `block_key`, `title`, `branch_number`, `reason`, `created_at`
                 FROM `branches_blocklist`
                 ORDER BY `id` DESC'
            )->fetchAll();

            jsonResponse(200, [
                'success' => true,
                'message' => 'Daftar blokir berhasil diambil.',
                'data' => $rows
            ]);
        }

        if ($method === 'POST') {
            $input = $readJsonBody();
            $title = trim((string) ($input['title'] ?? ''));
            $key = blockKey($title);

            if ($key === '') {
                jsonResponse(422, [
                    'success' => false,
                    'message' => 'Judul wajib diisi untuk diblokir.'
                ]);
            }

            $stmt = $pdo->prepare(
                'INSERT IGNORE INTO `branches_blocklist` (`block_key`, `title`, `reason`)
                 VALUES (:k, :t, :r)'
            );

            $stmt->execute([
                'k' => $key,
                't' => mb_substr($title, 0, 255, 'UTF-8'),
                'r' => mb_substr((string) ($input['reason'] ?? 'Diblokir manual'), 0, 255, 'UTF-8'),
            ]);

            jsonResponse(201, [
                'success' => true,
                'message' => $stmt->rowCount() > 0
                    ? 'Judul ditambahkan ke daftar blokir.'
                    : 'Judul sudah ada di daftar blokir.',
                'block_key' => $key
            ]);
        }

        if ($method === 'DELETE') {
            if ($id === null) {
                jsonResponse(400, [
                    'success' => false,
                    'message' => 'ID blokir wajib diisi.'
                ]);
            }

            $stmt = $pdo->prepare('DELETE FROM `branches_blocklist` WHERE `id` = :id');
            $stmt->execute(['id' => $id]);

            jsonResponse($stmt->rowCount() > 0 ? 200 : 404, [
                'success' => $stmt->rowCount() > 0,
                'message' => $stmt->rowCount() > 0
                    ? 'Blokir dibuka.'
                    : 'Data blokir tidak ditemukan.'
            ]);
        }

        header('Allow: GET, POST, DELETE');

        jsonResponse(405, [
            'success' => false,
            'message' => 'Method tidak diizinkan untuk blocklist.'
        ]);
    }


    if ($method === 'GET') {
        if ($id !== null) {
            $stmt = $pdo->prepare(
                "SELECT {$selectSQL}
                 FROM `branches`
                 WHERE `id` = :id
                 LIMIT 1"
            );

            $stmt->execute(['id' => $id]);
            $branch = $stmt->fetch();

            if ($branch === false) {
                jsonResponse(404, [
                    'success' => false,
                    'message' => 'Cabang tidak ditemukan.'
                ]);
            }

            jsonResponse(200, [
                'success' => true,
                'message' => 'Detail cabang berhasil diambil.',
                'data' => fillBranch($branch, $userLatitude, $userLongitude)
            ]);
        }

        $limit = 50;
        $offset = 0;

        if (isset($_GET['limit'])) {
            $value = filter_var(
                $_GET['limit'],
                FILTER_VALIDATE_INT
            );

            if ($value === false || $value < 1) {
                jsonResponse(400, [
                    'success' => false,
                    'message' => 'Limit harus berupa angka minimal 1.'
                ]);
            }

            $limit = min($value, 100);
        }

        if (isset($_GET['offset'])) {
            $value = filter_var(
                $_GET['offset'],
                FILTER_VALIDATE_INT
            );

            if ($value === false || $value < 0) {
                jsonResponse(400, [
                    'success' => false,
                    'message' => 'Offset harus berupa angka minimal 0.'
                ]);
            }

            $offset = $value;
        }

        $whereSQL = '';
        $queryParams = [];

        if (isset($_GET['status'])) {
            $status = $_GET['status'];

            if (!is_string($status) || !in_array($status, BRANCH_STATUSES, true)) {
                jsonResponse(400, [
                    'success' => false,
                    'message' => 'Filter status harus active, coming_soon, temp_closed, atau closed.'
                ]);
            }

            if (!isset($columnMap['status'])) {
                jsonResponse(503, [
                    'success' => false,
                    'message' => 'Filter status belum tersedia karena kolom status belum dimigrasikan.'
                ]);
            }

            $whereSQL = ' WHERE `' . $columnMap['status'] . '` = :status';
            $queryParams['status'] = $status;
        }

        $countStatement = $pdo->prepare('SELECT COUNT(*) FROM `branches`' . $whereSQL);
        $countStatement->execute($queryParams);
        $total = (int) $countStatement->fetchColumn();

        $orderBy = isset($columnMap['branch_number'])
            ? 'CAST(`' . $columnMap['branch_number'] . '` AS UNSIGNED) ASC, `id` ASC'
            : '`id` DESC';

        if ($userLatitude !== null && $userLongitude !== null) {
            $stmt = $pdo->prepare(
                "SELECT {$selectSQL}
                 FROM `branches`{$whereSQL}
                 ORDER BY {$orderBy}"
            );
            $stmt->execute($queryParams);

            $branches = array_map(
                static fn(array $branch): array => fillBranch($branch, $userLatitude, $userLongitude),
                $stmt->fetchAll()
            );

            usort(
                $branches,
                static fn(array $left, array $right): int =>
                    ($left['distance_km'] ?? PHP_FLOAT_MAX) <=> ($right['distance_km'] ?? PHP_FLOAT_MAX)
            );

            $branches = array_slice($branches, $offset, $limit);
        } else {
            $stmt = $pdo->prepare(
                "SELECT {$selectSQL}
                 FROM `branches`{$whereSQL}
                 ORDER BY {$orderBy}
                 LIMIT :limit OFFSET :offset"
            );
            foreach ($queryParams as $name => $value) {
                $stmt->bindValue(':' . $name, $value, PDO::PARAM_STR);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            $branches = array_map('fillBranch', $stmt->fetchAll());
        }

        jsonResponse(200, [
            'success' => true,
            'message' => 'Data cabang berhasil diambil.',
            'data' => $branches,
            'pagination' => [
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
                'count' => count($branches),
                'has_more' => ($offset + count($branches)) < $total
            ]
        ]);
    }


    if ($method === 'POST') {
        $input = $readJsonBody();

        $unknownFields = array_diff(
            array_keys($input),
            $allowedFields
        );

        if (!empty($unknownFields)) {
            jsonResponse(422, [
                'success' => false,
                'message' => 'Terdapat field yang tidak diizinkan.',
                'fields' => array_values($unknownFields)
            ]);
        }

        rejectUnavailableBranchFields($input, $columnMap);

        $data = array_intersect_key(
            $input,
            array_flip($writableColumns)
        );

        if (empty($data)) {
            jsonResponse(422, [
                'success' => false,
                'message' => 'Masukkan minimal satu field cabang yang valid.'
            ]);
        }

        foreach ($data as $field => $value) {
            if (is_array($value) || is_object($value)) {
                jsonResponse(422, [
                    'success' => false,
                    'message' => "Field {$field} harus berupa teks atau null."
                ]);
            }
        }

        $data = normalizeBranchInput($data);

        $incomingTitle = (string) ($data['title'] ?? '');
        $skipReason = null;

        if (REJECT_NUMERIC_TITLES && isJunkTitle($incomingTitle)) {
            $skipReason = 'judul hanya berupa angka (bukan nama cabang).';
        } elseif ($blocklistReady && ($incomingKey = blockKey($incomingTitle)) !== '') {
            $blCheck = $pdo->prepare(
                'SELECT COUNT(*) FROM `branches_blocklist` WHERE `block_key` = :k'
            );

            $blCheck->execute(['k' => $incomingKey]);

            if ((int) $blCheck->fetchColumn() > 0) {
                $skipReason = 'cabang ini ada di daftar blokir (pernah dihapus).';
            }
        }

        if ($skipReason !== null) {
            jsonResponse(200, [
                'success' => true,
                'skipped' => true,
                'inserted' => false,
                'message' => 'Dilewati: ' . $skipReason,
                'title' => $incomingTitle
            ]);
        }

        $fields = array_keys($data);

        $quotedFields = implode(
            ', ',
            array_map(
                static fn(string $field): string => '`' . $columnMap[$field] . '`',
                $fields
            )
        );

        $placeholders = [];
        foreach (array_keys($data) as $index => $field) {
            $placeholders[] = ':v' . $index;
        }

        $sql = sprintf(
            'INSERT INTO `branches` (%s) VALUES (%s)',
            $quotedFields,
            implode(', ', $placeholders)
        );

        $stmt = $pdo->prepare($sql);

        foreach (array_values($data) as $index => $value) {
            $stmt->bindValue(
                ':v' . $index,
                $value,
                $value === null ? PDO::PARAM_NULL : PDO::PARAM_STR
            );
        }

        $stmt->execute();

        $newId = (int) $pdo->lastInsertId();

        $stmt = $pdo->prepare(
            "SELECT {$selectSQL}
             FROM `branches`
             WHERE `id` = :id
             LIMIT 1"
        );

        $stmt->execute(['id' => $newId]);
        $branch = $stmt->fetch();

        jsonResponse(201, [
            'success' => true,
            'message' => 'Cabang berhasil ditambahkan.',
            'data' => $branch !== false
                ? fillBranch($branch)
                : ['id' => $newId]
        ]);
    }


    if ($method === 'PUT' || $method === 'PATCH') {
        if ($id === null) {
            jsonResponse(400, [
                'success' => false,
                'message' => 'ID cabang wajib diisi untuk edit.'
            ]);
        }

        $input = $readJsonBody();

        $unknownFields = array_diff(
            array_keys($input),
            $allowedFields
        );

        if (!empty($unknownFields)) {
            jsonResponse(422, [
                'success' => false,
                'message' => 'Terdapat field yang tidak diizinkan.',
                'fields' => array_values($unknownFields)
            ]);
        }

        rejectUnavailableBranchFields($input, $columnMap);

        $data = array_intersect_key(
            $input,
            array_flip($writableColumns)
        );

        if (empty($data)) {
            jsonResponse(422, [
                'success' => false,
                'message' => 'Masukkan minimal satu field yang ingin diedit.'
            ]);
        }

        foreach ($data as $field => $value) {
            if (is_array($value) || is_object($value)) {
                jsonResponse(422, [
                    'success' => false,
                    'message' => "Field {$field} harus berupa teks atau null."
                ]);
            }
        }

        $data = normalizeBranchInput($data);

        $check = $pdo->prepare(
            'SELECT COUNT(*) FROM `branches` WHERE `id` = :id'
        );

        $check->execute(['id' => $id]);

        if ((int) $check->fetchColumn() === 0) {
            jsonResponse(404, [
                'success' => false,
                'message' => 'Cabang yang ingin diedit tidak ditemukan.'
            ]);
        }

        $sets = [];

        foreach (array_keys($data) as $index => $field) {
            $sets[] = '`' . $columnMap[$field] . '` = :v' . $index;
        }

        $sql = sprintf(
            'UPDATE `branches` SET %s WHERE `id` = :id',
            implode(', ', $sets)
        );

        $stmt = $pdo->prepare($sql);

        foreach (array_values($data) as $index => $value) {
            $stmt->bindValue(
                ':v' . $index,
                $value,
                $value === null ? PDO::PARAM_NULL : PDO::PARAM_STR
            );
        }

        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $stmt = $pdo->prepare(
            "SELECT {$selectSQL}
             FROM `branches`
             WHERE `id` = :id
             LIMIT 1"
        );

        $stmt->execute(['id' => $id]);
        $branch = $stmt->fetch();

        jsonResponse(200, [
            'success' => true,
            'message' => 'Data cabang berhasil diperbarui.',
            'data' => $branch !== false ? fillBranch($branch) : $branch
        ]);
    }


    if ($method === 'DELETE') {
        if ($id === null) {
            jsonResponse(400, [
                'success' => false,
                'message' => 'ID cabang wajib diisi untuk menghapus.'
            ]);
        }

        $check = $pdo->prepare(
            'SELECT COUNT(*) FROM `branches` WHERE `id` = :id'
        );

        $check->execute(['id' => $id]);

        if ((int) $check->fetchColumn() === 0) {
            jsonResponse(404, [
                'success' => false,
                'message' => 'Cabang tidak ditemukan.'
            ]);
        }

        $shouldBlock = !in_array(
            strtolower((string) ($_GET['block'] ?? '1')),
            ['0', 'false', 'no'],
            true
        );

        $stmt = $pdo->prepare(
            "SELECT {$selectSQL} FROM `branches` WHERE `id` = :id LIMIT 1"
        );

        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        if ($row !== false && ($row['status'] ?? null) === 'closed') {
            jsonResponse(409, [
                'success' => false,
                'message' => 'Cabang berstatus closed harus tetap disimpan. Ubah statusnya, bukan hapus record.'
            ]);
        }

        $key = $row !== false ? blockKey((string) ($row['title'] ?? '')) : '';
        $blocked = false;

        if ($shouldBlock && $key !== '' && !$blocklistReady) {
            jsonResponse(500, [
                'success' => false,
                'message' => 'Tabel branches_blocklist tidak tersedia. Buat tabelnya atau hapus dengan ?block=0.'
            ]);
        }

        $pdo->beginTransaction();

        try {
            if ($shouldBlock && $key !== '') {
                $ins = $pdo->prepare(
                    'INSERT IGNORE INTO `branches_blocklist`
                        (`block_key`, `title`, `branch_number`, `reason`)
                     VALUES (:k, :t, :n, :r)'
                );

                $ins->execute([
                    'k' => $key,
                    't' => mb_substr((string) ($row['title'] ?? ''), 0, 255, 'UTF-8'),
                    'n' => isset($row['branch_number']) ? (string) $row['branch_number'] : null,
                    'r' => 'Dihapus dari admin cabang',
                ]);

                $blocked = true;
            }

            $del = $pdo->prepare('DELETE FROM `branches` WHERE `id` = :id');
            $del->execute(['id' => $id]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }

        jsonResponse(200, [
            'success' => true,
            'message' => $blocked
                ? 'Cabang dihapus dan diblokir dari input ulang.'
                : 'Cabang berhasil dihapus.',
            'deleted_id' => $id,
            'blocked' => $blocked
        ]);
    }

} catch (PDOException $e) {
    error_log('[REI API CABANG][SQL] ' . $e->getMessage());

    jsonResponse(500, [
        'success' => false,
        'message' => 'Operasi database gagal. Periksa field wajib dan struktur tabel.'
    ]);

} catch (Throwable $e) {
    error_log('[REI API CABANG][ERROR] ' . $e->getMessage());

    jsonResponse(500, [
        'success' => false,
        'message' => 'Terjadi kesalahan pada API cabang.'
    ]);
}
