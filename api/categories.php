<?php
declare(strict_types=1);


header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

/**
 * Respons JSON.
 */
function categoryJsonResponse(
    int $status,
    bool $success,
    string $message,
    mixed $data = null
): never {
    http_response_code($status);

    echo json_encode(
        [
            'success' => $success,
            'message' => $message,
            'data' => $data,
        ],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
}

/**
 * Baca header Authorization dari berbagai konfigurasi server.
 */
function categoryAuthorizationHeader(): string
{
    $candidates = [
        $_SERVER['HTTP_AUTHORIZATION'] ?? '',
        $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '',
        $_SERVER['REDIRECT_REDIRECT_HTTP_AUTHORIZATION'] ?? '',
    ];

    if (function_exists('getallheaders')) {
        foreach (getallheaders() as $name => $value) {
            if (strcasecmp((string) $name, 'Authorization') === 0) {
                $candidates[] = (string) $value;
            }
        }
    }

    if (function_exists('apache_request_headers')) {
        foreach (apache_request_headers() as $name => $value) {
            if (strcasecmp((string) $name, 'Authorization') === 0) {
                $candidates[] = (string) $value;
            }
        }
    }

    foreach ($candidates as $candidate) {
        $candidate = trim((string) $candidate);

        if (preg_match('/^Bearer\s+(\S+)$/i', $candidate)) {
            return $candidate;
        }
    }

    return '';
}

/**
 * 1. Muat konfigurasi API.
 */
$apiConfig = __DIR__ . '/api-config.php';

if (!is_file($apiConfig)) {
    categoryJsonResponse(
        500,
        false,
        'File api-config.php tidak ditemukan.'
    );
}

require_once $apiConfig;

if (!defined('REI_API_TOKEN') || trim((string) REI_API_TOKEN) === '') {
    categoryJsonResponse(
        500,
        false,
        'REI_API_TOKEN belum dikonfigurasi.'
    );
}

/**
 * 2. Validasi token.
 */
$authorization = categoryAuthorizationHeader();

if (
    $authorization === ''
    || !preg_match('/^Bearer\s+(\S+)$/i', $authorization, $matches)
) {
    categoryJsonResponse(
        401,
        false,
        'Bearer Token tidak ditemukan. Gunakan Authorization: Bearer TOKEN.'
    );
}

$providedToken = trim($matches[1]);
$expectedToken = trim((string) REI_API_TOKEN);

if (!hash_equals($expectedToken, $providedToken)) {
    categoryJsonResponse(
        401,
        false,
        'Token API tidak valid.'
    );
}

/**
 * 3. Muat koneksi database.
 *
 * Default: config.php di folder utama proyek.
 * Sesuaikan jika aplikasi menggunakan file koneksi lain.
 */
$dbConfig = dirname(__DIR__) . '/config.php';

if (!is_file($dbConfig)) {
    categoryJsonResponse(
        500,
        false,
        'File config.php tidak ditemukan.'
    );
}

require_once $dbConfig;

if (!isset($pdo) || !($pdo instanceof PDO)) {
    categoryJsonResponse(
        500,
        false,
        'Koneksi PDO $pdo tidak ditemukan di config.php.'
    );
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

/**
 * 4. Baca JSON dari request.
 */
function categoryReadBody(): array
{
    $raw = file_get_contents('php://input');

    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $data = json_decode($raw, true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
        categoryJsonResponse(
            400,
            false,
            'Body request harus berupa JSON yang valid.'
        );
    }

    return $data;
}

/**
 * 5. Buat slug.
 */
function categoryCreateSlug(string $value): string
{
    $value = trim($value);

    if (function_exists('iconv')) {
        $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

        if ($converted !== false) {
            $value = $converted;
        }
    }

    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';

    return trim($value, '-');
}

/**
 * 6. Validasi input.
 */
function categoryValidateInput(array $input, bool $partial = false): array
{
    $data = [];

    if (!$partial || array_key_exists('name', $input)) {
        $name = trim((string) ($input['name'] ?? ''));

        if ($name === '') {
            categoryJsonResponse(
                422,
                false,
                'Nama kategori wajib diisi.'
            );
        }

        if (mb_strlen($name) > 150) {
            categoryJsonResponse(
                422,
                false,
                'Nama kategori maksimal 150 karakter.'
            );
        }

        $data['name'] = $name;
    }

    if (array_key_exists('slug', $input)) {
        $slugValue = trim((string) ($input['slug'] ?? ''));

        if ($slugValue === '' && isset($data['name'])) {
            $slugValue = $data['name'];
        }

        $slug = categoryCreateSlug($slugValue);

        if ($slug === '') {
            categoryJsonResponse(
                422,
                false,
                'Slug kategori tidak valid.'
            );
        }

        if (mb_strlen($slug) > 180) {
            categoryJsonResponse(
                422,
                false,
                'Slug maksimal 180 karakter.'
            );
        }

        $data['slug'] = $slug;
    } elseif (isset($data['name'])) {
        $data['slug'] = categoryCreateSlug($data['name']);
    }

    return $data;
}

/**
 * 7. Ambil ID kategori.
 */
function categoryRequestId(): ?int
{
    if (!isset($_GET['id']) || $_GET['id'] === '') {
        return null;
    }

    $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);

    if ($id === false || $id < 1) {
        categoryJsonResponse(
            400,
            false,
            'ID kategori tidak valid.'
        );
    }

    return (int) $id;
}

/**
 * 8. Cari kategori berdasarkan ID.
 */
function categoryFindById(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, name, slug, created_at
         FROM post_categories
         WHERE id = :id
         LIMIT 1'
    );

    $stmt->execute(['id' => $id]);

    $row = $stmt->fetch();

    return $row ?: null;
}

/**
 * 9. Eksekusi API.
 */
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($method === 'OPTIONS') {
    header('Allow: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    http_response_code(204);
    exit;
}

try {
    $id = categoryRequestId();

    switch ($method) {
        /**
         * GET: daftar atau detail kategori.
         */
        case 'GET':
            if ($id !== null) {
                $category = categoryFindById($pdo, $id);

                if ($category === null) {
                    categoryJsonResponse(
                        404,
                        false,
                        'Kategori tidak ditemukan.'
                    );
                }

                categoryJsonResponse(
                    200,
                    true,
                    'Detail kategori berhasil diambil.',
                    $category
                );
            }

            $stmt = $pdo->query(
                'SELECT id, name, slug, created_at
                 FROM post_categories
                 ORDER BY id DESC'
            );

            $categories = $stmt->fetchAll();

            categoryJsonResponse(
                200,
                true,
                'Daftar kategori berhasil diambil.',
                [
                    'total' => count($categories),
                    'categories' => $categories,
                ]
            );

        /**
         * POST: tambah kategori.
         */
        case 'POST':
            $input = categoryReadBody();
            $data = categoryValidateInput($input);

            $stmt = $pdo->prepare(
                'INSERT INTO post_categories (name, slug)
                 VALUES (:name, :slug)'
            );

            $stmt->execute([
                'name' => $data['name'],
                'slug' => $data['slug'],
            ]);

            $newId = (int) $pdo->lastInsertId();

            categoryJsonResponse(
                201,
                true,
                'Kategori berhasil ditambahkan.',
                categoryFindById($pdo, $newId)
            );

        /**
         * PUT/PATCH: perbarui kategori.
         */
        case 'PUT':
        case 'PATCH':
            if ($id === null) {
                categoryJsonResponse(
                    400,
                    false,
                    'Parameter id wajib diisi.'
                );
            }

            if (categoryFindById($pdo, $id) === null) {
                categoryJsonResponse(
                    404,
                    false,
                    'Kategori tidak ditemukan.'
                );
            }

            $input = categoryReadBody();
            $data = categoryValidateInput(
                $input,
                $method === 'PATCH'
            );

            if ($data === []) {
                categoryJsonResponse(
                    422,
                    false,
                    'Tidak ada data untuk diperbarui.'
                );
            }

            // Saat nama berubah melalui PATCH, perbarui slug otomatis.
            if (
                $method === 'PATCH'
                && array_key_exists('name', $data)
                && !array_key_exists('slug', $data)
            ) {
                $data['slug'] = categoryCreateSlug($data['name']);
            }

            $sets = [];
            $params = ['id' => $id];

            foreach ($data as $field => $value) {
                if (!in_array($field, ['name', 'slug'], true)) {
                    continue;
                }

                $sets[] = "$field = :$field";
                $params[$field] = $value;
            }

            $stmt = $pdo->prepare(
                'UPDATE post_categories SET '
                . implode(', ', $sets)
                . ' WHERE id = :id'
            );

            $stmt->execute($params);

            categoryJsonResponse(
                200,
                true,
                'Kategori berhasil diperbarui.',
                categoryFindById($pdo, $id)
            );

        /**
         * DELETE: hapus kategori yang tidak digunakan artikel.
         */
        case 'DELETE':
            if ($id === null) {
                categoryJsonResponse(
                    400,
                    false,
                    'Parameter id wajib diisi.'
                );
            }

            if (categoryFindById($pdo, $id) === null) {
                categoryJsonResponse(
                    404,
                    false,
                    'Kategori tidak ditemukan.'
                );
            }

            $check = $pdo->prepare(
                'SELECT COUNT(*)
                 FROM posts
                 WHERE category_id = :id'
            );

            $check->execute(['id' => $id]);

            if ((int) $check->fetchColumn() > 0) {
                categoryJsonResponse(
                    409,
                    false,
                    'Kategori masih digunakan artikel dan tidak dapat dihapus.'
                );
            }

            $stmt = $pdo->prepare(
                'DELETE FROM post_categories WHERE id = :id'
            );

            $stmt->execute(['id' => $id]);

            categoryJsonResponse(
                200,
                true,
                'Kategori berhasil dihapus.',
                ['id' => $id]
            );

        default:
            header('Allow: GET, POST, PUT, PATCH, DELETE, OPTIONS');

            categoryJsonResponse(
                405,
                false,
                'Method tidak diizinkan.'
            );
    }
} catch (PDOException $e) {
    error_log('REI Categories API PDO: ' . $e->getMessage());

    $mysqlCode = (string) ($e->errorInfo[1] ?? '');

    if ($mysqlCode === '1062') {
        categoryJsonResponse(
            409,
            false,
            'Nama atau slug kategori sudah digunakan.'
        );
    }

    categoryJsonResponse(
        500,
        false,
        'Terjadi kesalahan database. Periksa log PHP.'
    );
} catch (Throwable $e) {
    error_log('REI Categories API: ' . $e->getMessage());

    categoryJsonResponse(
        500,
        false,
        'Terjadi kesalahan internal pada API kategori.'
    );
}
