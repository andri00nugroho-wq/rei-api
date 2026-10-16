<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT, PATCH, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function sliderSettingsResponse(
    int $status,
    array $data
): void {
    http_response_code($status);
    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

// Konfigurasi API dan database.
require_once __DIR__ . '/api-config.php';
require_once dirname(__DIR__) . '/config.php';

// Ambil token dari header Authorization.
$headers = function_exists('getallheaders')
    ? getallheaders()
    : [];

$authorization = $_SERVER['HTTP_AUTHORIZATION']
    ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
    ?? $headers['Authorization']
    ?? $headers['authorization']
    ?? '';

if (
    !preg_match('/Bearer\s+(.+)/i', trim($authorization), $matches)
    || !defined('REI_API_TOKEN')
    || !hash_equals(REI_API_TOKEN, trim($matches[1]))
) {
    sliderSettingsResponse(401, [
        'success' => false,
        'message' => 'Token API tidak valid atau tidak tersedia.'
    ]);
}

$method = $_SERVER['REQUEST_METHOD'];

if (!in_array($method, ['GET', 'PUT', 'PATCH'], true)) {
    header('Allow: GET, PUT, PATCH, OPTIONS');

    sliderSettingsResponse(405, [
        'success' => false,
        'message' => 'Method tidak diizinkan.'
    ]);
}

// Pastikan config.php menyediakan koneksi PDO bernama $pdo.
if (!isset($pdo) || !($pdo instanceof PDO)) {
    sliderSettingsResponse(500, [
        'success' => false,
        'message' => 'Koneksi database PDO ($pdo) tidak tersedia. Periksa config.php.'
    ]);
}

$defaults = [
    'height' => 450,
    'border_radius' => 20,
    'transition_ms' => 500,
    'interval_ms' => 4000,
    'indicator_mode' => 'default',
    'indicator_size' => 12,
    'indicator_show' => 1
];

try {
    // Ambil satu baris pengaturan utama.
    $select = $pdo->query(
        'SELECT id, height, border_radius, transition_ms,
                interval_ms, indicator_mode, indicator_size,
                indicator_show
         FROM slider_settings
         ORDER BY id ASC
         LIMIT 1'
    );

    $current = $select->fetch(PDO::FETCH_ASSOC);

    if ($method === 'GET') {
        if (!$current) {
            sliderSettingsResponse(200, [
                'success' => true,
                'message' => 'Pengaturan slider belum tersimpan. Data berikut adalah nilai default.',
                'data' => array_merge(['id' => null], $defaults)
            ]);
        }

        sliderSettingsResponse(200, [
            'success' => true,
            'message' => 'Pengaturan slider berhasil diambil.',
            'data' => $current
        ]);
    }

    // PUT/PATCH menerima JSON.
    $rawBody = file_get_contents('php://input');
    $input = json_decode($rawBody ?: '', true);

    if (!is_array($input) || json_last_error() !== JSON_ERROR_NONE) {
        sliderSettingsResponse(400, [
            'success' => false,
            'message' => 'Body harus berupa JSON yang valid.'
        ]);
    }

    $allowedFields = [
        'height',
        'border_radius',
        'transition_ms',
        'interval_ms',
        'indicator_mode',
        'indicator_size',
        'indicator_show'
    ];

    $unknownFields = array_diff(array_keys($input), $allowedFields);

    if ($unknownFields) {
        sliderSettingsResponse(422, [
            'success' => false,
            'message' => 'Ada field yang tidak dikenali.',
            'fields' => array_values($unknownFields),
            'allowed_fields' => $allowedFields
        ]);
    }

    if (!$input) {
        sliderSettingsResponse(422, [
            'success' => false,
            'message' => 'Tidak ada pengaturan yang dikirim.'
        ]);
    }

    // PATCH mengubah hanya field yang dikirim.
    // PUT menggunakan nilai default untuk field yang tidak dikirim.
    $base = $method === 'PATCH' && $current
        ? array_intersect_key($current, $defaults)
        : $defaults;

    $settings = array_merge($base, $input);

    $integerRanges = [
        'height' => [1, 3000],
        'border_radius' => [0, 500],
        'transition_ms' => [0, 60000],
        'interval_ms' => [0, 3600000],
        'indicator_size' => [1, 200]
    ];

    $errors = [];

    foreach ($integerRanges as $field => [$min, $max]) {
        $value = $settings[$field] ?? null;

        if (
            filter_var($value, FILTER_VALIDATE_INT) === false
            || (int) $value < $min
            || (int) $value > $max
        ) {
            $errors[$field] =
                "Harus berupa bilangan bulat antara {$min} dan {$max}.";
        } else {
            $settings[$field] = (int) $value;
        }
    }

    if (
        !is_string($settings['indicator_mode'])
        || trim($settings['indicator_mode']) === ''
        || mb_strlen(trim($settings['indicator_mode'])) > 50
    ) {
        $errors['indicator_mode'] =
            'Harus berupa teks dengan panjang 1 sampai 50 karakter.';
    } else {
        $settings['indicator_mode'] =
            trim($settings['indicator_mode']);
    }

    $indicatorShow = $settings['indicator_show'];

    if (is_bool($indicatorShow)) {
        $settings['indicator_show'] = $indicatorShow ? 1 : 0;
    } elseif (
        in_array($indicatorShow, [0, 1, '0', '1'], true)
    ) {
        $settings['indicator_show'] = (int) $indicatorShow;
    } else {
        $errors['indicator_show'] =
            'Harus bernilai true, false, 1, atau 0.';
    }

    if ($errors) {
        sliderSettingsResponse(422, [
            'success' => false,
            'message' => 'Validasi pengaturan slider gagal.',
            'errors' => $errors
        ]);
    }

    if ($current) {
        $sql = 'UPDATE slider_settings
                SET height = :height,
                    border_radius = :border_radius,
                    transition_ms = :transition_ms,
                    interval_ms = :interval_ms,
                    indicator_mode = :indicator_mode,
                    indicator_size = :indicator_size,
                    indicator_show = :indicator_show
                WHERE id = :id';

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            ':height' => $settings['height'],
            ':border_radius' => $settings['border_radius'],
            ':transition_ms' => $settings['transition_ms'],
            ':interval_ms' => $settings['interval_ms'],
            ':indicator_mode' => $settings['indicator_mode'],
            ':indicator_size' => $settings['indicator_size'],
            ':indicator_show' => $settings['indicator_show'],
            ':id' => $current['id']
        ]);

        $id = (int) $current['id'];
        $message = 'Pengaturan slider berhasil diperbarui.';
    } else {
        $sql = 'INSERT INTO slider_settings
                (height, border_radius, transition_ms, interval_ms,
                 indicator_mode, indicator_size, indicator_show)
                VALUES
                (:height, :border_radius, :transition_ms, :interval_ms,
                 :indicator_mode, :indicator_size, :indicator_show)';

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            ':height' => $settings['height'],
            ':border_radius' => $settings['border_radius'],
            ':transition_ms' => $settings['transition_ms'],
            ':interval_ms' => $settings['interval_ms'],
            ':indicator_mode' => $settings['indicator_mode'],
            ':indicator_size' => $settings['indicator_size'],
            ':indicator_show' => $settings['indicator_show']
        ]);

        $id = (int) $pdo->lastInsertId();
        $message = 'Pengaturan slider berhasil dibuat.';
    }

    // Ambil data final dari database.
    $stmt = $pdo->prepare(
        'SELECT id, height, border_radius, transition_ms,
                interval_ms, indicator_mode, indicator_size,
                indicator_show
         FROM slider_settings
         WHERE id = :id
         LIMIT 1'
    );

    $stmt->execute([':id' => $id]);
    $saved = $stmt->fetch(PDO::FETCH_ASSOC);

    sliderSettingsResponse(200, [
        'success' => true,
        'message' => $message,
        'data' => $saved
    ]);

} catch (Throwable $e) {
    error_log('Slider Settings API: ' . $e->getMessage());

    sliderSettingsResponse(500, [
        'success' => false,
        'message' => 'Terjadi kesalahan saat memproses pengaturan slider.'
    ]);
}
