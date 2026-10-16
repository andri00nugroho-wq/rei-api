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

require_once __DIR__ . '/api-config.php';
require_once dirname(__DIR__) . '/config.php';

function themeJson(int $status, array $data): never
{
    http_response_code($status);
    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES |
        JSON_INVALID_UTF8_SUBSTITUTE
    );
    exit;
}

function themeAuthorize(): void
{
    $authorization = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

    if ($authorization === '' && function_exists('getallheaders')) {
        foreach (getallheaders() as $key => $value) {
            if (strtolower((string) $key) === 'authorization') {
                $authorization = (string) $value;
                break;
            }
        }
    }

    $token = preg_replace('/^Bearer\s+/i', '', trim($authorization));

    if (
        !defined('REI_API_TOKEN') ||
        trim((string) REI_API_TOKEN) === '' ||
        !$token ||
        !hash_equals((string) REI_API_TOKEN, $token)
    ) {
        themeJson(401, [
            'success' => false,
            'message' => 'Token API tidak valid atau tidak tersedia.'
        ]);
    }
}

function themeInput(): array
{
    $raw = file_get_contents('php://input');

    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $data = json_decode($raw, true);

    if (!is_array($data)) {
        themeJson(400, [
            'success' => false,
            'message' => 'Body harus berupa JSON yang valid.'
        ]);
    }

    return $data;
}

function validateTheme(array $input): array
{
    $allowed = [
        'bg_color_1',
        'bg_color_2',
        'glass_opacity',
        'blur_px',
        'text_color'
    ];

    $result = [];

    foreach ($allowed as $field) {
        if (!array_key_exists($field, $input)) {
            continue;
        }

        $value = $input[$field];

        if (in_array($field, ['bg_color_1', 'bg_color_2', 'text_color'], true)) {
            if (!is_string($value) || trim($value) === '') {
                themeJson(422, [
                    'success' => false,
                    'message' => "$field harus berupa teks warna."
                ]);
            }

            $value = trim($value);

            if (strlen($value) > 50) {
                themeJson(422, [
                    'success' => false,
                    'message' => "$field maksimal 50 karakter."
                ]);
            }

            // Mendukung format HEX, RGB/RGBA, HSL/HSLA, dan nama warna CSS.
            $validColor =
                preg_match('/^#[0-9a-fA-F]{3,8}$/', $value) ||
                preg_match('/^(rgb|rgba|hsl|hsla)\([0-9.,%\s\/+-]+\)$/i', $value) ||
                preg_match('/^[a-zA-Z]{1,30}$/', $value);

            if (!$validColor) {
                themeJson(422, [
                    'success' => false,
                    'message' => "$field bukan format warna CSS yang valid."
                ]);
            }

            $result[$field] = $value;
            continue;
        }

        if ($field === 'glass_opacity') {
            if (!is_numeric($value) || (float) $value < 0 || (float) $value > 1) {
                themeJson(422, [
                    'success' => false,
                    'message' => 'glass_opacity harus bernilai antara 0 dan 1.'
                ]);
            }

            $result[$field] = number_format((float) $value, 2, '.', '');
            continue;
        }

        if ($field === 'blur_px') {
            if (
                filter_var($value, FILTER_VALIDATE_INT) === false ||
                (int) $value < 0 ||
                (int) $value > 100
            ) {
                themeJson(422, [
                    'success' => false,
                    'message' => 'blur_px harus berupa bilangan bulat antara 0 dan 100.'
                ]);
            }

            $result[$field] = (int) $value;
        }
    }

    return $result;
}

themeAuthorize();

if (!isset($pdo) || !($pdo instanceof PDO)) {
    themeJson(500, [
        'success' => false,
        'message' => 'Koneksi PDO $pdo tidak ditemukan di config.php.'
    ]);
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

try {
    if (!in_array($method, ['GET', 'PUT', 'PATCH'], true)) {
        header('Allow: GET, PUT, PATCH, OPTIONS');

        themeJson(405, [
            'success' => false,
            'message' => 'Method tidak diizinkan.'
        ]);
    }

    if ($method === 'GET') {
        $stmt = $pdo->query(
            'SELECT id, bg_color_1, bg_color_2, glass_opacity,
                    blur_px, text_color
             FROM theme_settings
             ORDER BY id ASC
             LIMIT 1'
        );

        $theme = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$theme) {
            themeJson(404, [
                'success' => false,
                'message' => 'Pengaturan tema belum tersedia.'
            ]);
        }

        themeJson(200, [
            'success' => true,
            'data' => $theme
        ]);
    }

    $input = themeInput();
    $data = validateTheme($input);

    if (!$data) {
        themeJson(422, [
            'success' => false,
            'message' => 'Tidak ada pengaturan tema yang dikirim.'
        ]);
    }

    $pdo->beginTransaction();

    $stmt = $pdo->query(
        'SELECT id FROM theme_settings ORDER BY id ASC LIMIT 1 FOR UPDATE'
    );

    $themeId = $stmt->fetchColumn();

    if ($themeId === false) {
        $defaults = [
            'bg_color_1' => '#4A00E0',
            'bg_color_2' => '#8E2DE2',
            'glass_opacity' => '0.15',
            'blur_px' => 15,
            'text_color' => '#ffffff'
        ];

        $values = array_merge($defaults, $data);

        $insert = $pdo->prepare(
            'INSERT INTO theme_settings
                (bg_color_1, bg_color_2, glass_opacity, blur_px, text_color)
             VALUES
                (:bg_color_1, :bg_color_2, :glass_opacity, :blur_px, :text_color)'
        );

        $insert->execute($values);
        $themeId = (int) $pdo->lastInsertId();
    } else {
        $sets = [];
        $params = ['id' => (int) $themeId];

        foreach ($data as $field => $value) {
            $sets[] = "$field = :$field";
            $params[$field] = $value;
        }

        $update = $pdo->prepare(
            'UPDATE theme_settings SET ' .
            implode(', ', $sets) .
            ' WHERE id = :id'
        );

        $update->execute($params);
    }

    $read = $pdo->prepare(
        'SELECT id, bg_color_1, bg_color_2, glass_opacity,
                blur_px, text_color
         FROM theme_settings
         WHERE id = :id
         LIMIT 1'
    );

    $read->execute(['id' => (int) $themeId]);
    $updatedTheme = $read->fetch(PDO::FETCH_ASSOC);

    $pdo->commit();

    themeJson(200, [
        'success' => true,
        'message' => 'Pengaturan tema berhasil disimpan.',
        'data' => $updatedTheme
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('API Theme Settings Error: ' . $e->getMessage());

    themeJson(500, [
        'success' => false,
        'message' => 'Terjadi kesalahan saat memproses pengaturan tema.'
    ]);
}
