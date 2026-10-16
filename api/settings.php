<?php

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('Access-Control-Allow-Methods: GET, PUT, PATCH, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Vary: Authorization, Cookie');

function settingsResponse(int $status, array $data): void
{
    http_response_code($status);

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
}

// Preflight request.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if (!in_array($method, ['GET', 'PUT', 'PATCH'], true)) {
    header('Allow: GET, PUT, PATCH, OPTIONS');

    settingsResponse(405, [
        'success' => false,
        'message' => 'Method tidak diizinkan.'
    ]);
}

// ======================================================
// AUTENTIKASI: SESI ADMIN ATAU BEARER TOKEN
// ======================================================

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$isAuthenticated =
    !empty($_SESSION['admin_user']) ||
    !empty($_SESSION['admin_logged_in']);

$headers = function_exists('getallheaders')
    ? getallheaders()
    : [];

$authorization = $_SERVER['HTTP_AUTHORIZATION']
    ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
    ?? $headers['Authorization']
    ?? $headers['authorization']
    ?? '';

$tokenValid = false;

if (
    preg_match('/^Bearer\s+(.+)$/i', trim($authorization), $matches)
) {
    require_once __DIR__ . '/api-config.php';

    if (
        defined('REI_API_TOKEN') &&
        REI_API_TOKEN !== ''
    ) {
        $tokenValid = hash_equals(
            (string) REI_API_TOKEN,
            trim($matches[1])
        );
    }
}

if (!$isAuthenticated && !$tokenValid) {
    settingsResponse(401, [
        'success' => false,
        'message' => 'Autentikasi diperlukan. Silakan login atau gunakan Bearer Token yang valid.'
    ]);
}

// ======================================================
// KONEKSI DATABASE
// ======================================================

require_once dirname(__DIR__) . '/config.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    settingsResponse(500, [
        'success' => false,
        'message' => 'Koneksi database tidak tersedia.'
    ]);
}

try {
    // ==================================================
    // GET: AMBIL PENGATURAN
    // ==================================================

    if ($method === 'GET') {
        $key = trim((string) ($_GET['key'] ?? ''));

        // Ambil satu pengaturan.
        if ($key !== '') {
            $stmt = $pdo->prepare(
                'SELECT setting_key, setting_value, updated_at
                 FROM site_settings
                 WHERE setting_key = :setting_key
                 LIMIT 1'
            );

            $stmt->execute([
                'setting_key' => $key
            ]);

            $setting = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$setting) {
                settingsResponse(404, [
                    'success' => false,
                    'message' => 'Pengaturan tidak ditemukan.'
                ]);
            }

            settingsResponse(200, [
                'success' => true,
                'data' => [
                    'key' => $setting['setting_key'],
                    'value' => $setting['setting_value'],
                    'updated_at' => $setting['updated_at']
                ]
            ]);
        }

        // Ambil seluruh pengaturan.
        $stmt = $pdo->query(
            'SELECT setting_key, setting_value
             FROM site_settings
             ORDER BY setting_key ASC'
        );

        $settings = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }

        settingsResponse(200, [
            'success' => true,
            'data' => $settings
        ]);
    }

    // ==================================================
    // PUT / PATCH: SIMPAN PENGATURAN
    // ==================================================

    $rawBody = file_get_contents('php://input');
    $body = json_decode($rawBody ?: '', true);

    if (!is_array($body) || $body === []) {
        settingsResponse(400, [
            'success' => false,
            'message' => 'Body JSON kosong atau format JSON tidak valid.'
        ]);
    }

    // Mendukung format {"data": {...}}.
    if (
        isset($body['data']) &&
        is_array($body['data']) &&
        count($body) === 1
    ) {
        $body = $body['data'];
    }

    // Hanya kunci yang diizinkan yang dapat disimpan.
    $allowedKeys = [
        'site_name',
        'site_description',
        'site_logo',
        'site_favicon',
        'contact_email',
        'contact_phone',
        'whatsapp_number',
        'social_instagram',
        'social_facebook',
        'social_tiktok',
        'social_youtube',
        'site_address',
        'footer_text'
    ];

    $validated = [];

    foreach ($body as $key => $value) {
        if (
            !is_string($key) ||
            !in_array($key, $allowedKeys, true)
        ) {
            settingsResponse(422, [
                'success' => false,
                'message' => 'Kunci pengaturan tidak diizinkan.',
                'invalid_key' => $key
            ]);
        }

        if (
            !is_string($value) &&
            !is_numeric($value) &&
            $value !== null
        ) {
            settingsResponse(422, [
                'success' => false,
                'message' => 'Nilai pengaturan harus berupa teks, angka, atau null.',
                'invalid_key' => $key
            ]);
        }

        $value = $value === null
            ? null
            : (string) $value;

        if (
            $value !== null &&
            strlen($value) > 20000
        ) {
            settingsResponse(422, [
                'success' => false,
                'message' => 'Nilai pengaturan terlalu panjang.',
                'invalid_key' => $key
            ]);
        }

        if (
            $key === 'contact_email' &&
            $value !== null &&
            $value !== '' &&
            !filter_var($value, FILTER_VALIDATE_EMAIL)
        ) {
            settingsResponse(422, [
                'success' => false,
                'message' => 'Format email tidak valid.'
            ]);
        }

        $validated[$key] = $value;
    }

    if ($validated === []) {
        settingsResponse(400, [
            'success' => false,
            'message' => 'Tidak ada pengaturan yang dapat disimpan.'
        ]);
    }

    // ==================================================
    // SIMPAN KE DATABASE
    // ==================================================

    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'INSERT INTO site_settings
            (setting_key, setting_value)
         VALUES
            (:setting_key, :setting_value)
         ON DUPLICATE KEY UPDATE
            setting_value = VALUES(setting_value)'
    );

    foreach ($validated as $key => $value) {
        $stmt->execute([
            'setting_key' => $key,
            'setting_value' => $value
        ]);
    }

    $pdo->commit();

    settingsResponse(200, [
        'success' => true,
        'message' => 'Pengaturan website berhasil disimpan.',
        'updated_keys' => array_keys($validated)
    ]);

} catch (Throwable $e) {
    if (
        isset($pdo) &&
        $pdo instanceof PDO &&
        $pdo->inTransaction()
    ) {
        $pdo->rollBack();
    }

    error_log('REI Settings API: ' . $e->getMessage());

    settingsResponse(500, [
        'success' => false,
        'message' => 'Terjadi kesalahan saat memproses pengaturan.'
    ]);
}
