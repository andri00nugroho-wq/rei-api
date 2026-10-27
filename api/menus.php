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

function menuResponse(
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

function menuAuthenticate(): void
{
    $headers = function_exists('getallheaders') ? getallheaders() : [];

    $authorization = $headers['Authorization']
        ?? $headers['authorization']
        ?? $_SERVER['HTTP_AUTHORIZATION']
        ?? '';

    if (!preg_match('/^Bearer\s+(.+)$/i', trim($authorization), $matches)) {
        menuResponse(401, false, 'Token Bearer wajib disertakan.');
    }

    if (
        !defined('REI_API_TOKEN') ||
        REI_API_TOKEN === '' ||
        !hash_equals((string) REI_API_TOKEN, trim($matches[1]))
    ) {
        menuResponse(401, false, 'Token API tidak valid.');
    }
}

function menuReadJson(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '', true);

    if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
        menuResponse(400, false, 'Body JSON tidak valid atau kosong.');
    }

    return $data;
}

function menuValidate(array $input, bool $create): array
{
    $data = [];

    foreach (['title', 'url', 'parent_id', 'sort_order'] as $field) {
        if (array_key_exists($field, $input)) {
            $data[$field] = $input[$field];
        }
    }

    if ($create && !array_key_exists('title', $data)) {
        menuResponse(422, false, 'Judul menu wajib diisi.');
    }

    if (
        array_key_exists('title', $data) &&
        (!is_string($data['title']) || trim($data['title']) === '')
    ) {
        menuResponse(422, false, 'Judul menu tidak boleh kosong.');
    }

    if (isset($data['title'])) {
        $data['title'] = trim($data['title']);

        if (mb_strlen($data['title']) > 100) {
            menuResponse(422, false, 'Judul menu maksimal 100 karakter.');
        }
    }

    if (
        $create &&
        (!isset($data['url']) || !is_string($data['url']) || trim($data['url']) === '')
    ) {
        menuResponse(422, false, 'URL menu wajib diisi.');
    }

    if (isset($data['url'])) {
        if (!is_string($data['url']) || trim($data['url']) === '') {
            menuResponse(422, false, 'URL menu tidak valid.');
        }

        $data['url'] = trim($data['url']);

        if (mb_strlen($data['url']) > 255) {
            menuResponse(422, false, 'URL menu maksimal 255 karakter.');
        }
    }

    foreach (['parent_id', 'sort_order'] as $field) {
        if (array_key_exists($field, $data)) {
            $value = filter_var($data[$field], FILTER_VALIDATE_INT);

            if ($value === false) {
                menuResponse(422, false, "{$field} harus berupa bilangan bulat.");
            }

            if ($value < 0) {
                menuResponse(422, false, "{$field} tidak boleh negatif.");
            }

            $data[$field] = $value;
        }
    }

    return $data;
}

function menuValidateParent(PDO $pdo, ?int $parentId, ?int $currentId = null): void
{
    if ($parentId === null || $parentId === 0) {
        return;
    }

    if ($currentId !== null && $parentId === $currentId) {
        menuResponse(422, false, 'Menu tidak boleh menjadi induk dirinya sendiri.');
    }

    $stmt = $pdo->prepare('SELECT id, parent_id FROM menus WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $parentId]);
    $parent = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$parent) {
        menuResponse(422, false, 'Menu induk tidak ditemukan.');
    }

    // Cegah hierarki langsung yang berpotensi membentuk siklus.
    if ($currentId !== null) {
        $ancestor = $parent;
        $visited = [];

        while ($ancestor) {
            $ancestorId = (int) $ancestor['id'];

            if ($ancestorId === $currentId) {
                menuResponse(422, false, 'Struktur menu tidak boleh membentuk siklus.');
            }

            if (isset($visited[$ancestorId])) {
                menuResponse(422, false, 'Struktur menu induk sudah tidak valid.');
            }

            $visited[$ancestorId] = true;
            $nextId = (int) ($ancestor['parent_id'] ?? 0);

            if ($nextId <= 0) {
                break;
            }

            $stmt->execute(['id' => $nextId]);
            $ancestor = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }
    }
}

function menuBuildTree(array $rows, int $parentId = 0): array
{
    $tree = [];

    foreach ($rows as $row) {
        if ((int) ($row['parent_id'] ?? 0) !== $parentId) {
            continue;
        }

        $row['children'] = menuBuildTree($rows, (int) $row['id']);
        $tree[] = $row;
    }

    return $tree;
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    menuResponse(500, false, 'Koneksi PDO tidak ditemukan. Periksa config.php.');
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
menuAuthenticate();

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

try {
    if ($method === 'GET') {
        $stmt = $pdo->query(
            'SELECT id, title, url, parent_id, sort_order, created_at
             FROM menus
             ORDER BY sort_order ASC, id ASC'
        );

        $menus = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($menus as &$menu) {
            $menu['id'] = (int) $menu['id'];
            $menu['parent_id'] = (int) ($menu['parent_id'] ?? 0);
            $menu['sort_order'] = (int) ($menu['sort_order'] ?? 0);
        }
        unset($menu);

        if ($id !== false && $id !== null) {
            foreach ($menus as $menu) {
                if ($menu['id'] === $id) {
                    menuResponse(200, true, 'Detail menu berhasil diambil.', $menu);
                }
            }

            menuResponse(404, false, 'Menu tidak ditemukan.');
        }

        $flat = isset($_GET['flat']) && $_GET['flat'] === '1';

        menuResponse(
            200,
            true,
            'Daftar menu berhasil diambil.',
            [
                'total' => count($menus),
                'menus' => $flat ? $menus : menuBuildTree($menus),
            ]
        );
    }

    if ($method === 'POST') {
        $data = menuValidate(menuReadJson(), true);
        $parentId = (int) ($data['parent_id'] ?? 0);

        menuValidateParent($pdo, $parentId);

        $stmt = $pdo->prepare(
            'INSERT INTO menus (title, url, parent_id, sort_order)
             VALUES (:title, :url, :parent_id, :sort_order)'
        );

        $stmt->execute([
            'title' => $data['title'],
            'url' => $data['url'],
            'parent_id' => $parentId,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);

        menuResponse(
            201,
            true,
            'Menu berhasil ditambahkan.',
            ['id' => (int) $pdo->lastInsertId()]
        );
    }

    if (!in_array($method, ['PUT', 'PATCH', 'DELETE'], true)) {
        header('Allow: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        menuResponse(405, false, 'Metode HTTP tidak didukung.');
    }

    if (!$id || $id < 1) {
        menuResponse(400, false, 'Parameter id wajib berupa angka positif.');
    }

    $stmt = $pdo->prepare('SELECT * FROM menus WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$existing) {
        menuResponse(404, false, 'Menu tidak ditemukan.');
    }

    if ($method === 'DELETE') {
        $childCheck = $pdo->prepare(
            'SELECT COUNT(*) FROM menus WHERE parent_id = :id'
        );
        $childCheck->execute(['id' => $id]);

        if ((int) $childCheck->fetchColumn() > 0) {
            menuResponse(
                409,
                false,
                'Menu masih memiliki submenu. Hapus atau pindahkan submenu terlebih dahulu.'
            );
        }

        $delete = $pdo->prepare('DELETE FROM menus WHERE id = :id');
        $delete->execute(['id' => $id]);

        menuResponse(200, true, 'Menu berhasil dihapus.');
    }

    $data = menuValidate(menuReadJson(), false);

    if (!$data) {
        menuResponse(422, false, 'Tidak ada data yang diperbarui.');
    }

    $parentId = array_key_exists('parent_id', $data)
        ? (int) $data['parent_id']
        : (int) ($existing['parent_id'] ?? 0);

    menuValidateParent($pdo, $parentId, $id);

    $fields = [];
    $params = ['id' => $id];

    foreach ($data as $field => $value) {
        $fields[] = "{$field} = :{$field}";
        $params[$field] = $value;
    }

    $update = $pdo->prepare(
        'UPDATE menus SET ' . implode(', ', $fields) . ' WHERE id = :id'
    );
    $update->execute($params);

    menuResponse(200, true, 'Menu berhasil diperbarui.', ['id' => $id]);

} catch (PDOException $e) {
    error_log('REI Menus API database error: ' . $e->getMessage());
    menuResponse(500, false, 'Terjadi kesalahan database.');
} catch (Throwable $e) {
    error_log('REI Menus API error: ' . $e->getMessage());
    menuResponse(500, false, 'Terjadi kesalahan pada server.');
}
