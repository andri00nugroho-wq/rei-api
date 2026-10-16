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

/*
|--------------------------------------------------------------------------
| 1. Validasi HTTP method
|--------------------------------------------------------------------------
*/

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

$allowedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

if (!in_array($method, $allowedMethods, true)) {
    header('Allow: GET, POST, PUT, PATCH, DELETE');

    jsonResponse(405, [
        'success' => false,
        'message' => 'Method tidak diizinkan.'
    ]);
}

/*
|--------------------------------------------------------------------------
| 2. Konfigurasi API
|--------------------------------------------------------------------------
*/

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
    || REI_API_TOKEN === ''
) {
    jsonResponse(500, [
        'success' => false,
        'message' => 'Konfigurasi API token belum benar.'
    ]);
}

/*
|--------------------------------------------------------------------------
| 3. Autentikasi Bearer Token
|--------------------------------------------------------------------------
*/

$authorization = $_SERVER['HTTP_AUTHORIZATION']
    ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
    ?? '';

if ($authorization === '' && function_exists('getallheaders')) {
    foreach (getallheaders() as $name => $value) {
        if (strcasecmp($name, 'Authorization') === 0) {
            $authorization = $value;
            break;
        }
    }
}

$token = '';

if (
    preg_match(
        '/^Bearer\s+(.+)$/i',
        trim($authorization),
        $matches
    )
) {
    $token = trim($matches[1]);
}

if ($token === '' || !hash_equals(REI_API_TOKEN, $token)) {
    jsonResponse(401, [
        'success' => false,
        'message' => 'API token tidak valid.'
    ]);
}

/*
|--------------------------------------------------------------------------
| 4. Koneksi database
|--------------------------------------------------------------------------
*/

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

/*
|--------------------------------------------------------------------------
| 5. Periksa tabel dan kolom
|--------------------------------------------------------------------------
*/

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

    /*
     * Kolom yang boleh dibaca atau ditulis melalui API.
     * Kolom yang tidak ada di database akan diabaikan.
     */
    $allowedFields = [
        'branch_number',
        'title',
        'address',
        'phone',
        'hours',
        'notes',
        'map_link',
        'map_embed_url'
    ];

    $writableColumns = array_values(
        array_intersect($allowedFields, $availableColumns)
    );

    $selectColumns = array_values(
        array_intersect(
            ['id', ...$allowedFields],
            $availableColumns
        )
    );

    if (empty($writableColumns)) {
        jsonResponse(500, [
            'success' => false,
            'message' => 'Kolom cabang yang bisa ditulis tidak ditemukan.'
        ]);
    }

    $selectSQL = implode(
        ', ',
        array_map(
            static fn(string $column): string => '`' . $column . '`',
            $selectColumns
        )
    );

    /*
    |--------------------------------------------------------------------------
    | 6. Ambil ID dari query string
    |--------------------------------------------------------------------------
    */

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

    /*
    |--------------------------------------------------------------------------
    | 7. Baca dan validasi JSON request body
    |--------------------------------------------------------------------------
    */

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

    /*
    |--------------------------------------------------------------------------
    | 8. GET - daftar atau detail cabang
    |--------------------------------------------------------------------------
    */

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
                'data' => $branch
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

        $total = (int) $pdo
            ->query('SELECT COUNT(*) FROM `branches`')
            ->fetchColumn();

        $orderBy = in_array(
            'branch_number',
            $availableColumns,
            true
        )
            ? '`branch_number` ASC, `id` ASC'
            : '`id` DESC';

        $stmt = $pdo->prepare(
            "SELECT {$selectSQL}
             FROM `branches`
             ORDER BY {$orderBy}
             LIMIT :limit OFFSET :offset"
        );

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $branches = $stmt->fetchAll();

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

    /*
    |--------------------------------------------------------------------------
    | 9. POST - tambah cabang
    |--------------------------------------------------------------------------
    */

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

        $fields = array_keys($data);

        $quotedFields = implode(
            ', ',
            array_map(
                static fn(string $field): string => '`' . $field . '`',
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
                ? $branch
                : ['id' => $newId]
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | 10. PUT / PATCH - edit cabang
    |--------------------------------------------------------------------------
    */

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
            $sets[] = '`' . $field . '` = :v' . $index;
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
            'data' => $branch
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | 11. DELETE - hapus cabang
    |--------------------------------------------------------------------------
    */

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

        $stmt = $pdo->prepare(
            'DELETE FROM `branches` WHERE `id` = :id'
        );

        $stmt->execute(['id' => $id]);

        jsonResponse(200, [
            'success' => true,
            'message' => 'Cabang berhasil dihapus.',
            'deleted_id' => $id
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
