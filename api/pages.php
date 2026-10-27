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
require_once dirname(__DIR__) . '/site-pages.php';

/**
 * Mengirim respons JSON.
 */
function pageJsonResponse(
    int $status,
    bool $success,
    string $message,
    mixed $data = null
): never {
    http_response_code($status);

    $response = [
        'success' => $success,
        'message' => $message,
    ];

    if ($data !== null) {
        $response['data'] = $data;
    }

    echo json_encode(
        $response,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
}

/**
 * Validasi token Bearer.
 * Menggunakan REI_API_TOKEN dari api/api-config.php.
 */
function pageAuthenticate(): void
{
    $headers = function_exists('getallheaders')
        ? getallheaders()
        : [];

    $authorization = $headers['Authorization']
        ?? $headers['authorization']
        ?? $_SERVER['HTTP_AUTHORIZATION']
        ?? '';

    if (!preg_match('/^Bearer\s+(.+)$/i', trim($authorization), $matches)) {
        pageJsonResponse(
            401,
            false,
            'Token Bearer wajib disertakan.'
        );
    }

    $providedToken = trim($matches[1]);

    if (
        !defined('REI_API_TOKEN') ||
        REI_API_TOKEN === '' ||
        !hash_equals((string) REI_API_TOKEN, $providedToken)
    ) {
        pageJsonResponse(
            401,
            false,
            'Token API tidak valid.'
        );
    }
}

/**
 * Membaca JSON request.
 */
function pageReadJson(): array
{
    $raw = file_get_contents('php://input');

    if ($raw === false || trim($raw) === '') {
        pageJsonResponse(400, false, 'Body JSON wajib diisi.');
    }

    $data = json_decode($raw, true);

    if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
        pageJsonResponse(400, false, 'Format JSON tidak valid.');
    }

    return $data;
}

/**
 * Membuat slug sederhana.
 */
function pageCreateSlug(string $value): string
{
    $value = trim(mb_strtolower($value, 'UTF-8'));

    $slug = preg_replace('/[^\p{L}\p{N}]+/u', '-', $value);

    return trim((string) $slug, '-');
}

/**
 * Mengambil dan memvalidasi data halaman.
 */
function pageValidateInput(array $input, bool $isCreate): array
{
    $allowed = [
        'title',
        'slug',
        'content',
        'custom_html',
        'custom_css',
        'custom_js',
        'featured_image',
    ];

    $data = [];

    foreach ($allowed as $field) {
        if (array_key_exists($field, $input)) {
            $data[$field] = $input[$field];
        }
    }

    if ($isCreate && empty(trim((string) ($data['title'] ?? '')))) {
        pageJsonResponse(422, false, 'Judul halaman wajib diisi.');
    }

    if (
        isset($data['title']) &&
        (!is_string($data['title']) || trim($data['title']) === '')
    ) {
        pageJsonResponse(422, false, 'Judul halaman tidak valid.');
    }

    if (isset($data['title'])) {
        $data['title'] = trim($data['title']);
    }

    if (isset($data['slug'])) {
        if (!is_string($data['slug'])) {
            pageJsonResponse(422, false, 'Slug harus berupa teks.');
        }

        $data['slug'] = pageCreateSlug($data['slug']);
    } elseif ($isCreate && isset($data['title'])) {
        $data['slug'] = pageCreateSlug($data['title']);
    }

    if ($isCreate && empty($data['slug'])) {
        pageJsonResponse(422, false, 'Slug halaman wajib diisi.');
    }

    foreach (
        ['content', 'custom_html', 'custom_css', 'custom_js', 'featured_image']
        as $field
    ) {
        if (
            array_key_exists($field, $data) &&
            $data[$field] !== null &&
            !is_string($data[$field])
        ) {
            pageJsonResponse(
                422,
                false,
                "Field {$field} harus berupa teks atau null."
            );
        }
    }

    return $data;
}

/**
 * Pastikan koneksi database tersedia.
 */
if (!isset($pdo) || !($pdo instanceof PDO)) {
    pageJsonResponse(
        500,
        false,
        'Koneksi database PDO tidak ditemukan. Periksa config.php.'
    );
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

pageAuthenticate();

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$slug = trim((string) ($_GET['slug'] ?? ''));

try {
    /*
     * GET: daftar halaman atau detail halaman.
     */
    if ($method === 'GET') {
        $select = "
            SELECT
                id,
                title,
                slug,
                content,
                custom_html,
                custom_css,
                custom_js,
                featured_image,
                type,
                created_at,
                updated_at
            FROM posts
            WHERE type = 'page'
        ";

        if ($id !== false && $id !== null) {
            $stmt = $pdo->prepare($select . ' AND id = :id LIMIT 1');
            $stmt->execute(['id' => $id]);
            $page = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$page) {
                pageJsonResponse(404, false, 'Halaman tidak ditemukan.');
            }

            pageJsonResponse(200, true, 'Detail halaman berhasil diambil.', $page);
        }

        if ($slug !== '') {
            $stmt = $pdo->prepare($select . ' AND slug = :slug LIMIT 1');
            $stmt->execute(['slug' => $slug]);
            $page = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$page) {
                pageJsonResponse(404, false, 'Halaman tidak ditemukan.');
            }

            pageJsonResponse(200, true, 'Detail halaman berhasil diambil.', $page);
        }

        $search = trim((string) ($_GET['search'] ?? ''));
        $limit = filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT);
        $pageNumber = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);

        $limit = max(1, min(100, $limit ?: 20));
        $pageNumber = max(1, $pageNumber ?: 1);
        $offset = ($pageNumber - 1) * $limit;

        $where = " WHERE type = 'page' ";
        $params = [];

        if ($search !== '') {
            $where .= ' AND (title LIKE :search OR slug LIKE :search) ';
            $params['search'] = '%' . $search . '%';
        }

        $countStmt = $pdo->prepare(
            'SELECT COUNT(*) FROM posts ' . $where
        );
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = $select . ' AND 1=1 ';

        if ($search !== '') {
            $sql .= ' AND (title LIKE :search OR slug LIKE :search) ';
        }

        $sql .= ' ORDER BY updated_at DESC, id DESC LIMIT :limit OFFSET :offset';

        $stmt = $pdo->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
        }

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        pageJsonResponse(
            200,
            true,
            'Daftar halaman berhasil diambil.',
            [
                'total' => $total,
                'page' => $pageNumber,
                'limit' => $limit,
                'total_pages' => (int) ceil($total / $limit),
                'pages' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            ]
        );
    }

    /*
     * POST: buat halaman baru.
     */
    if ($method === 'POST') {
        $input = pageReadJson();
        $data = pageValidateInput($input, true);

        if (rei_site_page_key($data['slug']) !== null) {
            pageJsonResponse(409, false, 'Slug sudah digunakan halaman website.');
        }

        $check = $pdo->prepare(
            'SELECT id FROM posts WHERE slug = :slug LIMIT 1'
        );
        $check->execute(['slug' => $data['slug']]);

        if ($check->fetch()) {
            pageJsonResponse(409, false, 'Slug sudah digunakan.');
        }

        $fields = [
            'title',
            'slug',
            'content',
            'custom_html',
            'custom_css',
            'custom_js',
            'featured_image',
        ];

        $insertFields = $fields;
        $insertFields[] = 'type';

        $columns = implode(', ', $insertFields);
        $placeholders = implode(
            ', ',
            array_map(
                static fn(string $field): string => ':' . $field,
                $insertFields
            )
        );

        $sql = "INSERT INTO posts ({$columns}) VALUES ({$placeholders})";
        $stmt = $pdo->prepare($sql);

        $values = [];

        foreach ($fields as $field) {
            $values[$field] = $data[$field] ?? null;
        }

        $values['type'] = 'page';
        $stmt->execute($values);

        $newId = (int) $pdo->lastInsertId();

        pageJsonResponse(
            201,
            true,
            'Halaman berhasil dibuat.',
            ['id' => $newId, 'slug' => $data['slug']]
        );
    }

    /*
     * PUT/PATCH/DELETE memerlukan ID.
     */
    if (!in_array($method, ['PUT', 'PATCH', 'DELETE'], true)) {
        header('Allow: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        pageJsonResponse(405, false, 'Metode HTTP tidak didukung.');
    }

    if (!$id || $id < 1) {
        pageJsonResponse(400, false, 'Parameter id wajib berupa angka positif.');
    }

    $find = $pdo->prepare(
        "SELECT id, slug FROM posts WHERE id = :id AND type = 'page' LIMIT 1"
    );
    $find->execute(['id' => $id]);
    $existingPage = $find->fetch();

    if (!$existingPage) {
        pageJsonResponse(404, false, 'Halaman tidak ditemukan.');
    }

    // Halaman website bawaan (Beranda, Tentang Kami, dst.) tidak boleh dihapus atau diganti slug-nya.
    $isSitePage = rei_site_page_key((string) $existingPage['slug']) !== null;

    /*
     * DELETE: hapus halaman.
     */
    if ($method === 'DELETE') {
        if ($isSitePage) {
            pageJsonResponse(409, false, 'Halaman website tidak dapat dihapus, hanya diedit.');
        }

        $delete = $pdo->prepare(
            "DELETE FROM posts WHERE id = :id AND type = 'page'"
        );
        $delete->execute(['id' => $id]);

        pageJsonResponse(200, true, 'Halaman berhasil dihapus.');
    }

    /*
     * PUT/PATCH: perbarui halaman.
     */
    $input = pageReadJson();
    $data = pageValidateInput($input, false);

    if (isset($data['slug']) && $data['slug'] !== $existingPage['slug']) {
        if ($isSitePage) {
            pageJsonResponse(409, false, 'Slug halaman website tidak dapat diubah.');
        }
        if (rei_site_page_key($data['slug']) !== null) {
            pageJsonResponse(409, false, 'Slug sudah digunakan halaman website.');
        }
    }

    if (isset($data['slug'])) {
        $check = $pdo->prepare(
            'SELECT id FROM posts WHERE slug = :slug AND id <> :id LIMIT 1'
        );
        $check->execute([
            'slug' => $data['slug'],
            'id' => $id,
        ]);

        if ($check->fetch()) {
            pageJsonResponse(409, false, 'Slug sudah digunakan halaman lain.');
        }
    }

    if (!$data) {
        pageJsonResponse(422, false, 'Tidak ada field yang diperbarui.');
    }

    $setParts = [];

    foreach (array_keys($data) as $field) {
        $setParts[] = $field . ' = :' . $field;
    }

    $data['id'] = $id;

    $update = $pdo->prepare(
        'UPDATE posts SET ' . implode(', ', $setParts) .
        ' WHERE id = :id AND type = \'page\''
    );
    $update->execute($data);

    pageJsonResponse(200, true, 'Halaman berhasil diperbarui.', ['id' => $id]);

} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        pageJsonResponse(409, false, 'Data bentrok dengan data yang sudah ada.');
    }

    error_log('REI Pages API PDO error: ' . $e->getMessage());

    pageJsonResponse(500, false, 'Terjadi kesalahan database.');
} catch (Throwable $e) {
    error_log('REI Pages API error: ' . $e->getMessage());

    pageJsonResponse(500, false, 'Terjadi kesalahan pada server.');
}