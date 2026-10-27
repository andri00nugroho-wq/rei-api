<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function mediaResponse(int $status, array $data): void
{
    http_response_code($status);

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
}

// Autentikasi dengan token API yang sudah digunakan proyek REI.
require_once __DIR__ . '/api-config.php';

$headers = function_exists('getallheaders') ? getallheaders() : [];

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
    mediaResponse(401, [
        'success' => false,
        'message' => 'Token API tidak valid atau tidak tersedia.'
    ]);
}

$method = $_SERVER['REQUEST_METHOD'];

if (!in_array($method, ['GET', 'DELETE'], true)) {
    header('Allow: GET, DELETE, OPTIONS');

    mediaResponse(405, [
        'success' => false,
        'message' => 'Method tidak diizinkan.'
    ]);
}

// Folder media berada di root project: /rei/uploads/media/
$mediaDirectory = dirname(__DIR__)
    . DIRECTORY_SEPARATOR . 'uploads'
    . DIRECTORY_SEPARATOR . 'media';

$allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

if (!is_dir($mediaDirectory)) {
    if (!mkdir($mediaDirectory, 0755, true) && !is_dir($mediaDirectory)) {
        mediaResponse(500, [
            'success' => false,
            'message' => 'Folder media tidak dapat dibuat.'
        ]);
    }
}

/**
 * Validasi nama file:
 * hanya nama file, bukan path atau URL.
 */
function validMediaFilename(string $filename): bool
{
    return $filename !== ''
        && $filename === basename($filename)
        && !str_contains($filename, '..')
        && preg_match('/\A[a-zA-Z0-9_-][a-zA-Z0-9._-]*\z/', $filename) === 1;
}

function isAllowedImage(string $filename, array $extensions): bool
{
    return in_array(
        strtolower(pathinfo($filename, PATHINFO_EXTENSION)),
        $extensions,
        true
    );
}

try {
    /*
     * GET: DAFTAR DAN PENCARIAN MEDIA
     */
    if ($method === 'GET') {
        $query = trim((string) ($_GET['q'] ?? ''));
        $page = filter_var(
            $_GET['page'] ?? 1,
            FILTER_VALIDATE_INT
        );
        $limit = filter_var(
            $_GET['limit'] ?? 20,
            FILTER_VALIDATE_INT
        );

        $page = $page !== false && $page !== null
            ? max(1, $page)
            : 1;

        $limit = $limit !== false && $limit !== null
            ? max(1, min(100, $limit))
            : 20;

        $files = new DirectoryIterator($mediaDirectory);
        $media = [];

        foreach ($files as $file) {
            if ($file->isDot() || !$file->isFile()) {
                continue;
            }

            $filename = $file->getFilename();

            if (!isAllowedImage($filename, $allowedExtensions)) {
                continue;
            }

            if (
                $query !== ''
                && stripos($filename, $query) === false
            ) {
                continue;
            }

            $media[] = [
                'filename' => $filename,
                'url' => '/rei/uploads/media/' . rawurlencode($filename),
                'mime_type' => match (
                    strtolower(pathinfo($filename, PATHINFO_EXTENSION))
                ) {
                    'jpg', 'jpeg' => 'image/jpeg',
                    'png' => 'image/png',
                    'webp' => 'image/webp',
                    'gif' => 'image/gif',
                    default => 'application/octet-stream'
                },
                'size_bytes' => $file->getSize(),
                'modified_at' => date(
                    DATE_ATOM,
                    $file->getMTime()
                )
            ];
        }

        // File terbaru ditampilkan lebih dahulu.
        usort(
            $media,
            fn(array $a, array $b): int =>
                strcmp($b['modified_at'], $a['modified_at'])
        );

        $total = count($media);
        $totalPages = (int) ceil($total / $limit);
        $offset = ($page - 1) * $limit;

        mediaResponse(200, [
            'success' => true,
            'message' => 'Daftar media berhasil diambil.',
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total_items' => $total,
                'total_pages' => $totalPages
            ],
            'data' => array_slice($media, $offset, $limit)
        ]);
    }

    /*
     * DELETE: HAPUS FILE MEDIA
     */
    $filename = trim((string) ($_GET['filename'] ?? ''));

    if (!validMediaFilename($filename)) {
        mediaResponse(422, [
            'success' => false,
            'message' => 'Parameter filename tidak valid.'
        ]);
    }

    if (!isAllowedImage($filename, $allowedExtensions)) {
        mediaResponse(422, [
            'success' => false,
            'message' => 'Format file tidak diizinkan.'
        ]);
    }

    $filePath = $mediaDirectory
        . DIRECTORY_SEPARATOR
        . $filename;

    if (!is_file($filePath) || is_link($filePath)) {
        mediaResponse(404, [
            'success' => false,
            'message' => 'File media tidak ditemukan.'
        ]);
    }

    /*
     * Cegah penghapusan gambar yang masih dipakai konten.
     * Periksa referensi pada posts.featured_image dan
     * sliders.image_path.
     */
    require_once dirname(__DIR__) . '/config.php';

    if (!isset($pdo) || !($pdo instanceof PDO)) {
        mediaResponse(500, [
            'success' => false,
            'message' => 'Koneksi database tidak tersedia; penghapusan dibatalkan.'
        ]);
    }

    $relativePath = 'uploads/media/' . $filename;
    $publicPath = '/rei/' . $relativePath;

    $referenceChecks = [
        [
            'sql' => 'SELECT COUNT(*) FROM posts
                      WHERE featured_image IN (:filename, :relative, :public)',
            'label' => 'gambar unggulan postingan atau halaman'
        ],
        [
            'sql' => 'SELECT COUNT(*) FROM sliders
                      WHERE image_path IN (:filename, :relative, :public)',
            'label' => 'gambar slider'
        ]
    ];

    foreach ($referenceChecks as $check) {
        $stmt = $pdo->prepare($check['sql']);

        $stmt->execute([
            ':filename' => $filename,
            ':relative' => $relativePath,
            ':public' => $publicPath
        ]);

        if ((int) $stmt->fetchColumn() > 0) {
            mediaResponse(409, [
                'success' => false,
                'message' => 'File tidak dihapus karena masih digunakan.',
                'used_by' => $check['label']
            ]);
        }
    }

    if (!unlink($filePath)) {
        mediaResponse(500, [
            'success' => false,
            'message' => 'File gagal dihapus. Periksa izin folder.'
        ]);
    }

    mediaResponse(200, [
        'success' => true,
        'message' => 'File media berhasil dihapus.',
        'data' => [
            'filename' => $filename
        ]
    ]);

} catch (Throwable $e) {
    error_log('Media API: ' . $e->getMessage());

    mediaResponse(500, [
        'success' => false,
        'message' => 'Terjadi kesalahan saat memproses media.'
    ]);
}
