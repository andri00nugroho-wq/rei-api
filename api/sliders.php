<?php
declare(strict_types=1);



header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/api-config.php';
require_once dirname(__DIR__) . '/config.php';

/*
 * Pastikan api-config.php menyediakan REI_API_TOKEN
 * dan config.php menyediakan koneksi PDO bernama $pdo.
 */

function sliderResponse(
    int $status,
    array $data
): never {
    http_response_code($status);
    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES |
        JSON_INVALID_UTF8_SUBSTITUTE
    );
    exit;
}

function sliderInput(): array
{
    $raw = file_get_contents('php://input');

    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $data = json_decode($raw, true);

    if (!is_array($data)) {
        sliderResponse(400, [
            'success' => false,
            'message' => 'Body harus berupa JSON yang valid.'
        ]);
    }

    return $data;
}

function sliderRequireToken(): void
{
    $headers = function_exists('getallheaders')
        ? getallheaders()
        : [];

    $authorization = '';

    foreach ($headers as $key => $value) {
        if (strtolower((string) $key) === 'authorization') {
            $authorization = (string) $value;
            break;
        }
    }

    if ($authorization === '' && isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $authorization = (string) $_SERVER['HTTP_AUTHORIZATION'];
    }

    if (
        !defined('REI_API_TOKEN') ||
        trim((string) REI_API_TOKEN) === ''
    ) {
        sliderResponse(500, [
            'success' => false,
            'message' => 'Token API belum dikonfigurasi.'
        ]);
    }

    $token = preg_replace('/^Bearer\s+/i', '', trim($authorization));

    if (
        $token === null ||
        $token === '' ||
        !hash_equals((string) REI_API_TOKEN, $token)
    ) {
        sliderResponse(401, [
            'success' => false,
            'message' => 'Token API tidak valid atau tidak tersedia.'
        ]);
    }
}

function sliderValidate(array $data, bool $partial = false): array
{
    $allowed = [
        'title',
        'caption',
        'image_path',
        'show_on_page',
        'sort_order'
    ];

    $result = [];

    foreach ($allowed as $field) {
        if (!array_key_exists($field, $data)) {
            continue;
        }

        $value = $data[$field];

        if ($field === 'sort_order') {
            if ($value === null || $value === '') {
                $result[$field] = 0;
            } elseif (filter_var($value, FILTER_VALIDATE_INT) === false) {
                sliderResponse(422, [
                    'success' => false,
                    'message' => 'sort_order harus berupa bilangan bulat.'
                ]);
            } else {
                $result[$field] = (int) $value;
            }

            continue;
        }

        if ($value !== null && !is_string($value)) {
            sliderResponse(422, [
                'success' => false,
                'message' => "$field harus berupa teks atau null."
            ]);
        }

        if ($field === 'image_path') {
            if ($value === null || trim($value) === '') {
                sliderResponse(422, [
                    'success' => false,
                    'message' => 'image_path wajib diisi.'
                ]);
            }

            if (mb_strlen($value) > 255) {
                sliderResponse(422, [
                    'success' => false,
                    'message' => 'image_path maksimal 255 karakter.'
                ]);
            }

            $result[$field] = trim($value);
            continue;
        }

        if ($field === 'title' && $value !== null && mb_strlen($value) > 255) {
            sliderResponse(422, [
                'success' => false,
                'message' => 'title maksimal 255 karakter.'
            ]);
        }

        if ($field === 'show_on_page' && $value !== null && mb_strlen($value) > 100) {
            sliderResponse(422, [
                'success' => false,
                'message' => 'show_on_page maksimal 100 karakter.'
            ]);
        }

        $result[$field] = $value;
    }

    if (!$partial && !array_key_exists('image_path', $result)) {
        sliderResponse(422, [
            'success' => false,
            'message' => 'image_path wajib diisi.'
        ]);
    }

    return $result;
}

sliderRequireToken();

if (!isset($pdo) || !($pdo instanceof PDO)) {
    sliderResponse(500, [
        'success' => false,
        'message' => 'Koneksi PDO $pdo tidak ditemukan di config.php.'
    ]);
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

try {
    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                if (!$id || $id < 1) {
                    sliderResponse(400, [
                        'success' => false,
                        'message' => 'ID slider tidak valid.'
                    ]);
                }

                $stmt = $pdo->prepare(
                    'SELECT id, title, caption, image_path,
                            show_on_page, sort_order, created_at
                     FROM sliders
                     WHERE id = :id
                     LIMIT 1'
                );
                $stmt->execute(['id' => $id]);
                $slider = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$slider) {
                    sliderResponse(404, [
                        'success' => false,
                        'message' => 'Slider tidak ditemukan.'
                    ]);
                }

                sliderResponse(200, [
                    'success' => true,
                    'data' => $slider
                ]);
            }

            $page = max(1, (int) ($_GET['page'] ?? 1));
            $limit = (int) ($_GET['limit'] ?? 50);
            $limit = max(1, min(100, $limit));
            $offset = ($page - 1) * $limit;

            $where = [];
            $params = [];

            if (isset($_GET['show_on_page']) && $_GET['show_on_page'] !== '') {
                $where[] = 'show_on_page = :show_on_page';
                $params['show_on_page'] = (string) $_GET['show_on_page'];
            }

            $sqlWhere = $where
                ? 'WHERE ' . implode(' AND ', $where)
                : '';

            $countStmt = $pdo->prepare(
                "SELECT COUNT(*) FROM sliders $sqlWhere"
            );
            $countStmt->execute($params);
            $total = (int) $countStmt->fetchColumn();

            $sql = "SELECT id, title, caption, image_path,
                           show_on_page, sort_order, created_at
                    FROM sliders
                    $sqlWhere
                    ORDER BY sort_order ASC, id DESC
                    LIMIT :limit OFFSET :offset";

            $stmt = $pdo->prepare($sql);

            foreach ($params as $key => $value) {
                $stmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
            }

            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            sliderResponse(200, [
                'success' => true,
                'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'total_pages' => (int) ceil($total / $limit)
                ]
            ]);

        case 'POST':
            $data = sliderValidate(sliderInput());

            $stmt = $pdo->prepare(
                'INSERT INTO sliders
                    (title, caption, image_path, show_on_page, sort_order)
                 VALUES
                    (:title, :caption, :image_path, :show_on_page, :sort_order)'
            );

            $stmt->execute([
                'title' => $data['title'] ?? null,
                'caption' => $data['caption'] ?? null,
                'image_path' => $data['image_path'],
                'show_on_page' => $data['show_on_page'] ?? 'all',
                'sort_order' => $data['sort_order'] ?? 0
            ]);

            $newId = (int) $pdo->lastInsertId();

            sliderResponse(201, [
                'success' => true,
                'message' => 'Slider berhasil ditambahkan.',
                'id' => $newId
            ]);

        case 'PUT':
        case 'PATCH':
            $input = sliderInput();

            if (!$id || $id < 1) {
                $idFromBody = filter_var(
                    $input['id'] ?? null,
                    FILTER_VALIDATE_INT
                );
                $id = $idFromBody ?: null;
            }

            if (!$id || $id < 1) {
                sliderResponse(400, [
                    'success' => false,
                    'message' => 'ID slider wajib disertakan melalui ?id=1 atau field id.'
                ]);
            }

            $check = $pdo->prepare('SELECT id FROM sliders WHERE id = :id');
            $check->execute(['id' => $id]);

            if (!$check->fetchColumn()) {
                sliderResponse(404, [
                    'success' => false,
                    'message' => 'Slider tidak ditemukan.'
                ]);
            }

            $data = sliderValidate(
                $input,
                $method === 'PATCH'
            );

            unset($data['id']);

            if (!$data) {
                sliderResponse(422, [
                    'success' => false,
                    'message' => 'Tidak ada field yang dapat diperbarui.'
                ]);
            }

            $sets = [];
            $params = ['id' => $id];

            foreach ($data as $field => $value) {
                $sets[] = "$field = :$field";
                $params[$field] = $value;
            }

            $stmt = $pdo->prepare(
                'UPDATE sliders SET ' . implode(', ', $sets) . ' WHERE id = :id'
            );
            $stmt->execute($params);

            sliderResponse(200, [
                'success' => true,
                'message' => 'Slider berhasil diperbarui.',
                'id' => (int) $id
            ]);

        case 'DELETE':
            if (!$id || $id < 1) {
                sliderResponse(400, [
                    'success' => false,
                    'message' => 'ID slider wajib disertakan, contoh ?id=1.'
                ]);
            }

            $stmt = $pdo->prepare('DELETE FROM sliders WHERE id = :id');
            $stmt->execute(['id' => $id]);

            if ($stmt->rowCount() === 0) {
                sliderResponse(404, [
                    'success' => false,
                    'message' => 'Slider tidak ditemukan.'
                ]);
            }

            sliderResponse(200, [
                'success' => true,
                'message' => 'Slider berhasil dihapus.'
            ]);

        default:
            header('Allow: GET, POST, PUT, PATCH, DELETE, OPTIONS');

            sliderResponse(405, [
                'success' => false,
                'message' => 'Method tidak diizinkan.'
            ]);
    }
} catch (PDOException $e) {
    error_log('API Slider PDO Error: ' . $e->getMessage());

    sliderResponse(500, [
        'success' => false,
        'message' => 'Terjadi kesalahan database saat memproses slider.'
    ]);
} catch (Throwable $e) {
    error_log('API Slider Error: ' . $e->getMessage());

    sliderResponse(500, [
        'success' => false,
        'message' => 'Terjadi kesalahan internal server.'
    ]);
}