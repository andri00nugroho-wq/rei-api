<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function jsonResponse(
    int $status,
    bool $success,
    string $message,
    mixed $data = null
): never {
    http_response_code($status);

    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    exit;
}

function getBearerToken(): string
{
    $header = '';

    if (function_exists('getallheaders')) {
        $headers = getallheaders();

        foreach ($headers as $key => $value) {
            if (strtolower((string) $key) === 'authorization') {
                $header = trim((string) $value);
                break;
            }
        }
    }

    if ($header === '') {
        $header = trim(
            (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '')
        );
    }

    if ($header === '' && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $header = trim((string) $_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
    }

    if (preg_match('/^Bearer\s+(\S+)$/i', $header, $matches)) {
        return trim($matches[1]);
    }

    return '';
}

function readJsonBody(): array
{
    $raw = file_get_contents('php://input');

    if ($raw === false || trim($raw) === '') {
        jsonResponse(400, false, 'Body JSON wajib diisi.');
    }

    try {
        $body = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        jsonResponse(400, false, 'Format JSON tidak valid.');
    }

    if (!is_array($body)) {
        jsonResponse(400, false, 'Body harus berupa objek JSON.');
    }

    return $body;
}

function makeSlug(string $text): string
{
    $text = trim($text);

    if (function_exists('iconv')) {
        $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);

        if ($converted !== false) {
            $text = $converted;
        }
    }

    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    return trim($text, '-');
}

function validateCategory(PDO $pdo, mixed $categoryId): ?int
{
    if ($categoryId === null || $categoryId === '') {
        return null;
    }

    $id = filter_var($categoryId, FILTER_VALIDATE_INT);

    if ($id === false || $id < 1) {
        jsonResponse(422, false, 'category_id harus berupa ID kategori yang valid.');
    }

    $stmt = $pdo->prepare(
        'SELECT id FROM post_categories WHERE id = :id LIMIT 1'
    );
    $stmt->execute(['id' => $id]);

    if (!$stmt->fetchColumn()) {
        jsonResponse(422, false, 'Kategori tidak ditemukan.');
    }

    return (int) $id;
}

function validatePostInput(PDO $pdo, array $input, bool $partial = false): array
{
    $allowed = [
        'title',
        'slug',
        'content',
        'custom_html',
        'custom_css',
        'custom_js',
        'featured_image',
        'type',
        'category_id',
    ];

    foreach ($input as $key => $_value) {
        if (!in_array($key, $allowed, true)) {
            jsonResponse(
                422,
                false,
                'Field tidak dikenal: ' . $key
            );
        }
    }

    if (!$partial && !array_key_exists('title', $input)) {
        jsonResponse(422, false, 'Field title wajib diisi.');
    }

    $result = [];

    if (array_key_exists('title', $input)) {
        if (!is_string($input['title'])) {
            jsonResponse(422, false, 'title harus berupa teks.');
        }

        $title = trim($input['title']);

        if ($title === '') {
            jsonResponse(422, false, 'title tidak boleh kosong.');
        }

        if (mb_strlen($title) > 255) {
            jsonResponse(422, false, 'title maksimal 255 karakter.');
        }

        $result['title'] = $title;
    }

    if (array_key_exists('slug', $input)) {
        if (!is_string($input['slug'])) {
            jsonResponse(422, false, 'slug harus berupa teks.');
        }

        $slug = makeSlug($input['slug']);

        if ($slug === '') {
            jsonResponse(422, false, 'slug tidak valid.');
        }

        if (mb_strlen($slug) > 255) {
            jsonResponse(422, false, 'slug maksimal 255 karakter.');
        }

        $result['slug'] = $slug;
    } elseif (isset($result['title'])) {
        $result['slug'] = makeSlug($result['title']);
    }

    foreach (
        ['content', 'custom_html', 'custom_css', 'custom_js', 'featured_image']
        as $field
    ) {
        if (!array_key_exists($field, $input)) {
            continue;
        }

        if ($input[$field] !== null && !is_string($input[$field])) {
            jsonResponse(422, false, $field . ' harus berupa teks atau null.');
        }

        $value = $input[$field];

        if ($field === 'featured_image' && is_string($value)) {
            $value = trim($value);

            if (mb_strlen($value) > 255) {
                jsonResponse(422, false, 'featured_image maksimal 255 karakter.');
            }
        }

        $result[$field] = $value;
    }

    if (array_key_exists('type', $input)) {
        $type = $input['type'];

        if (!in_array($type, ['post', 'page'], true)) {
            jsonResponse(422, false, 'type hanya boleh bernilai post atau page.');
        }

        $result['type'] = $type;
    } elseif (!$partial) {
        $result['type'] = 'post';
    }

    if (array_key_exists('category_id', $input)) {
        $result['category_id'] = validateCategory(
            $pdo,
            $input['category_id']
        );
    }

    return $result;
}

function fetchPost(PDO $pdo, int $id): array|false
{
    $stmt = $pdo->prepare(
        'SELECT
            p.id,
            p.title,
            p.slug,
            p.content,
            p.custom_html,
            p.custom_css,
            p.custom_js,
            p.featured_image,
            p.type,
            p.category_id,
            c.name AS category_name,
            c.slug AS category_slug,
            p.created_at,
            p.updated_at
         FROM posts p
         LEFT JOIN post_categories c ON c.id = p.category_id
         WHERE p.id = :id
         LIMIT 1'
    );

    $stmt->execute(['id' => $id]);

    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/* ---------------------------------------------------------
 * AUTHENTICATION
 * --------------------------------------------------------- */

require_once __DIR__ . '/api-config.php';

$providedToken = getBearerToken();

if (
    !defined('REI_API_TOKEN') ||
    $providedToken === '' ||
    !hash_equals((string) REI_API_TOKEN, $providedToken)
) {
    jsonResponse(401, false, 'Token API tidak valid atau tidak tersedia.');
}

/* ---------------------------------------------------------
 * DATABASE
 * --------------------------------------------------------- */

try {
    require_once dirname(__DIR__) . '/config.php';

    if (!isset($pdo) || !$pdo instanceof PDO) {
        jsonResponse(500, false, 'Koneksi database PDO tidak tersedia.');
    }

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('Posts API database error: ' . $e->getMessage());
    jsonResponse(500, false, 'Gagal menghubungkan database.');
}

/* ---------------------------------------------------------
 * ROUTING
 * --------------------------------------------------------- */

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$idRaw = $_GET['id'] ?? null;
$id = null;

if ($idRaw !== null) {
    $validatedId = filter_var($idRaw, FILTER_VALIDATE_INT);

    if ($validatedId === false || $validatedId < 1) {
        jsonResponse(400, false, 'Parameter id tidak valid.');
    }

    $id = (int) $validatedId;
}

try {
    switch ($method) {
        case 'GET':
            if ($id !== null) {
                $post = fetchPost($pdo, $id);

                if ($post === false) {
                    jsonResponse(404, false, 'Artikel tidak ditemukan.');
                }

                jsonResponse(200, true, 'Detail artikel berhasil diambil.', [
                    'post' => $post,
                ]);
            }

            $type = $_GET['type'] ?? null;
            $categoryId = $_GET['category_id'] ?? null;
            $search = trim((string) ($_GET['search'] ?? ''));
            $limit = filter_var(
                $_GET['limit'] ?? 20,
                FILTER_VALIDATE_INT
            );
            $page = filter_var(
                $_GET['page'] ?? 1,
                FILTER_VALIDATE_INT
            );

            if ($limit === false || $limit < 1) {
                $limit = 20;
            }

            $limit = min($limit, 100);

            if ($page === false || $page < 1) {
                $page = 1;
            }

            $where = [];
            $params = [];

            if ($type !== null && $type !== '') {
                if (!in_array($type, ['post', 'page'], true)) {
                    jsonResponse(422, false, 'Filter type harus post atau page.');
                }

                $where[] = 'p.type = :type';
                $params['type'] = $type;
            }

            if ($categoryId !== null && $categoryId !== '') {
                $validCategoryId = filter_var(
                    $categoryId,
                    FILTER_VALIDATE_INT
                );

                if ($validCategoryId === false || $validCategoryId < 1) {
                    jsonResponse(422, false, 'Filter category_id tidak valid.');
                }

                $where[] = 'p.category_id = :category_id';
                $params['category_id'] = (int) $validCategoryId;
            }

            if ($search !== '') {
                $where[] = '(p.title LIKE :search_title OR p.content LIKE :search_content)';
                $params['search_title'] = '%' . $search . '%';
                $params['search_content'] = '%' . $search . '%';
            }

            $whereSql = $where
                ? ' WHERE ' . implode(' AND ', $where)
                : '';

            $countStmt = $pdo->prepare(
                'SELECT COUNT(*) FROM posts p' . $whereSql
            );
            $countStmt->execute($params);
            $total = (int) $countStmt->fetchColumn();

            $offset = ($page - 1) * $limit;

            $sql = 'SELECT
                        p.id,
                        p.title,
                        p.slug,
                        p.content,
                        p.custom_html,
                        p.custom_css,
                        p.custom_js,
                        p.featured_image,
                        p.type,
                        p.category_id,
                        c.name AS category_name,
                        c.slug AS category_slug,
                        p.created_at,
                        p.updated_at
                    FROM posts p
                    LEFT JOIN post_categories c ON c.id = p.category_id'
                    . $whereSql .
                    ' ORDER BY p.created_at DESC, p.id DESC
                      LIMIT ' . (int) $limit .
                    ' OFFSET ' . (int) $offset;

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            jsonResponse(200, true, 'Daftar artikel berhasil diambil.', [
                'total' => $total,
                'page' => (int) $page,
                'limit' => (int) $limit,
                'total_pages' => (int) ceil($total / $limit),
                'posts' => $stmt->fetchAll(),
            ]);

        case 'POST':
            $input = readJsonBody();
            $data = validatePostInput($pdo, $input);

            $columns = array_keys($data);
            $quotedColumns = implode(', ', $columns);
            $placeholders = implode(
                ', ',
                array_map(
                    static fn(string $column): string => ':' . $column,
                    $columns
                )
            );

            $stmt = $pdo->prepare(
                'INSERT INTO posts (' . $quotedColumns . ')
                 VALUES (' . $placeholders . ')'
            );
            $stmt->execute($data);

            $newId = (int) $pdo->lastInsertId();
            $post = fetchPost($pdo, $newId);

            jsonResponse(201, true, 'Artikel berhasil dibuat.', [
                'post' => $post,
            ]);

        case 'PUT':
        case 'PATCH':
            if ($id === null) {
                jsonResponse(400, false, 'Parameter id wajib diisi untuk mengedit artikel.');
            }

            if (fetchPost($pdo, $id) === false) {
                jsonResponse(404, false, 'Artikel tidak ditemukan.');
            }

            $input = readJsonBody();

            if ($method === 'PUT') {
                if (!array_key_exists('title', $input)) {
                    jsonResponse(422, false, 'Field title wajib diisi untuk PUT.');
                }
            }

            $data = validatePostInput(
                $pdo,
                $input,
                $method === 'PATCH'
            );

            if (!$data) {
                jsonResponse(422, false, 'Tidak ada field yang dapat diperbarui.');
            }

            $sets = [];

            foreach (array_keys($data) as $column) {
                $sets[] = $column . ' = :' . $column;
            }

            $data['__id'] = $id;

            $stmt = $pdo->prepare(
                'UPDATE posts SET ' . implode(', ', $sets) . ' WHERE id = :__id'
            );
            $stmt->execute($data);

            jsonResponse(200, true, 'Artikel berhasil diperbarui.', [
                'post' => fetchPost($pdo, $id),
            ]);

        case 'DELETE':
            if ($id === null) {
                jsonResponse(400, false, 'Parameter id wajib diisi untuk menghapus artikel.');
            }

            if (fetchPost($pdo, $id) === false) {
                jsonResponse(404, false, 'Artikel tidak ditemukan.');
            }

            $stmt = $pdo->prepare('DELETE FROM posts WHERE id = :id');
            $stmt->execute(['id' => $id]);

            jsonResponse(200, true, 'Artikel berhasil dihapus.', [
                'id' => $id,
            ]);

        default:
            header('Allow: GET, POST, PUT, PATCH, DELETE');
            jsonResponse(405, false, 'Method tidak diizinkan.');
    }
} catch (PDOException $e) {
    error_log('Posts API query error: ' . $e->getMessage());

    if ($e->getCode() === '23000') {
        jsonResponse(
            409,
            false,
            'Data bertabrakan dengan data yang sudah ada. Periksa slug atau relasi kategori.'
        );
    }

    jsonResponse(500, false, 'Terjadi kesalahan saat memproses data artikel.');
} catch (Throwable $e) {
    error_log('Posts API error: ' . $e->getMessage());
    jsonResponse(500, false, 'Terjadi kesalahan pada Posts API.');
}
