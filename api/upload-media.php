<?php
declare(strict_types=1);


header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function mediaResponse(int $status, array $data): never
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

/**
 * Membaca header Authorization.
 * Mendukung Apache, Laragon, dan beberapa konfigurasi PHP.
 */
function mediaGetAuthorization(): string
{
    $authorization = trim(
        (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '')
    );

    if ($authorization !== '') {
        return $authorization;
    }

    $authorization = trim(
        (string) ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '')
    );

    if ($authorization !== '') {
        return $authorization;
    }

    if (function_exists('getallheaders')) {
        foreach (getallheaders() as $name => $value) {
            if (strtolower((string) $name) === 'authorization') {
                return trim((string) $value);
            }
        }
    }

    return '';
}

/**
 * Validasi Bearer Token.
 */
function mediaAuthorize(): void
{
    if (!defined('REI_API_TOKEN')) {
        mediaResponse(500, [
            'success' => false,
            'message' => 'REI_API_TOKEN belum didefinisikan di api-config.php.'
        ]);
    }

    $expectedToken = trim((string) REI_API_TOKEN);

    if ($expectedToken === '') {
        mediaResponse(500, [
            'success' => false,
            'message' => 'Token API belum dikonfigurasi.'
        ]);
    }

    $authorization = mediaGetAuthorization();

    if (
        !preg_match(
            '/^Bearer\s+(\S+)$/i',
            $authorization,
            $matches
        )
    ) {
        mediaResponse(401, [
            'success' => false,
            'message' => 'Header Authorization tidak ditemukan atau formatnya salah. Gunakan Bearer Token.'
        ]);
    }

    $providedToken = $matches[1];

    if (!hash_equals($expectedToken, $providedToken)) {
        mediaResponse(401, [
            'success' => false,
            'message' => 'Token API tidak cocok dengan konfigurasi.'
        ]);
    }
}

/**
 * Format ukuran file agar mudah dibaca.
 */
function mediaFormatSize(int $bytes): string
{
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 2) . ' MB';
    }

    if ($bytes >= 1024) {
        return round($bytes / 1024, 2) . ' KB';
    }

    return $bytes . ' B';
}

/**
 * Autentikasi dilakukan sebelum operasi API.
 */
require_once __DIR__ . '/api-config.php';

mediaAuthorize();

$method = strtoupper(
    (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')
);

/**
 * GET digunakan untuk memeriksa endpoint dan autentikasi.
 * GET tidak mengunggah gambar.
 */
if ($method === 'GET') {
    mediaResponse(200, [
        'success' => true,
        'message' => 'API Upload Media aktif dan autentikasi berhasil.',
        'data' => [
            'endpoint' => '/api/upload-media.php',
            'upload_method' => 'POST',
            'field_name' => 'file',
            'allowed_formats' => ['jpg', 'jpeg', 'png', 'webp', 'gif'],
            'max_size_mb' => 5
        ]
    ]);
}

/**
 * Hanya POST yang diizinkan untuk upload.
 */
if ($method !== 'POST') {
    header('Allow: GET, POST, OPTIONS');

    mediaResponse(405, [
        'success' => false,
        'message' => 'Method tidak diizinkan. Gunakan POST untuk upload.'
    ]);
}

/**
 * Pastikan konfigurasi PHP mengizinkan file upload.
 */
if (!isset($_FILES['file'])) {
    mediaResponse(422, [
        'success' => false,
        'message' => 'File tidak ditemukan. Kirim gambar melalui form-data dengan key file.'
    ]);
}

$file = $_FILES['file'];

/**
 * Validasi struktur data file.
 */
if (
    !is_array($file) ||
    !isset(
        $file['name'],
        $file['tmp_name'],
        $file['error'],
        $file['size']
    ) ||
    is_array($file['error'])
) {
    mediaResponse(400, [
        'success' => false,
        'message' => 'Struktur file upload tidak valid.'
    ]);
}

/**
 * Validasi error upload PHP.
 */
if ((int) $file['error'] !== UPLOAD_ERR_OK) {
    $uploadMessages = [
        UPLOAD_ERR_INI_SIZE => 'Ukuran file melebihi upload_max_filesize.',
        UPLOAD_ERR_FORM_SIZE => 'Ukuran file melebihi batas form.',
        UPLOAD_ERR_PARTIAL => 'File hanya terunggah sebagian.',
        UPLOAD_ERR_NO_FILE => 'Tidak ada file yang dipilih.',
        UPLOAD_ERR_NO_TMP_DIR => 'Folder sementara PHP tidak tersedia.',
        UPLOAD_ERR_CANT_WRITE => 'PHP gagal menulis file ke disk.',
        UPLOAD_ERR_EXTENSION => 'Upload dihentikan oleh ekstensi PHP.'
    ];

    $errorCode = (int) $file['error'];

    mediaResponse(400, [
        'success' => false,
        'message' => $uploadMessages[$errorCode]
            ?? 'Upload gagal karena kesalahan yang tidak diketahui.',
        'error_code' => $errorCode
    ]);
}

/**
 * Validasi file sementara.
 */
$tmpName = (string) $file['tmp_name'];
$fileSize = (int) $file['size'];

if (!is_uploaded_file($tmpName)) {
    mediaResponse(400, [
        'success' => false,
        'message' => 'File upload tidak valid.'
    ]);
}

/**
 * Batas ukuran maksimal 5 MB.
 */
$maxSize = 5 * 1024 * 1024;

if ($fileSize <= 0) {
    mediaResponse(422, [
        'success' => false,
        'message' => 'File kosong tidak dapat diunggah.'
    ]);
}

if ($fileSize > $maxSize) {
    mediaResponse(413, [
        'success' => false,
        'message' => 'Ukuran file maksimal 5 MB.',
        'max_size_mb' => 5,
        'file_size' => mediaFormatSize($fileSize)
    ]);
}

/**
 * Validasi gambar menggunakan isi file.
 * Tidak mempercayai ekstensi atau MIME dari browser.
 */
$imageInfo = @getimagesize($tmpName);

if ($imageInfo === false || empty($imageInfo['mime'])) {
    mediaResponse(422, [
        'success' => false,
        'message' => 'File bukan gambar yang valid.'
    ]);
}

$mime = (string) $imageInfo['mime'];

$allowedTypes = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'image/gif'  => 'gif'
];

if (!isset($allowedTypes[$mime])) {
    mediaResponse(415, [
        'success' => false,
        'message' => 'Format tidak didukung. Gunakan JPG, PNG, WEBP, atau GIF.',
        'detected_mime' => $mime
    ]);
}

/**
 * Tentukan folder project dan folder media.
 */
$projectRoot = dirname(__DIR__);

$uploadDir = $projectRoot
    . DIRECTORY_SEPARATOR . 'uploads'
    . DIRECTORY_SEPARATOR . 'media';

/**
 * Buat folder apabila belum tersedia.
 */
if (!is_dir($uploadDir)) {
    if (
        !mkdir($uploadDir, 0755, true) &&
        !is_dir($uploadDir)
    ) {
        mediaResponse(500, [
            'success' => false,
            'message' => 'Folder uploads/media gagal dibuat.'
        ]);
    }
}

/**
 * Pastikan folder dapat ditulisi.
 */
if (!is_writable($uploadDir)) {
    mediaResponse(500, [
        'success' => false,
        'message' => 'Folder uploads/media tidak dapat ditulisi oleh PHP.'
    ]);
}

/**
 * Nama file acak agar tidak menimpa file sebelumnya
 * dan tidak menggunakan nama file kiriman pengguna.
 */
try {
    $filename = bin2hex(random_bytes(16))
        . '.'
        . $allowedTypes[$mime];
} catch (Throwable $e) {
    error_log('Media random filename error: ' . $e->getMessage());

    mediaResponse(500, [
        'success' => false,
        'message' => 'Gagal membuat nama file.'
    ]);
}

$destination = $uploadDir
    . DIRECTORY_SEPARATOR
    . $filename;

/**
 * Pindahkan file upload ke folder media.
 */
if (!move_uploaded_file($tmpName, $destination)) {
    mediaResponse(500, [
        'success' => false,
        'message' => 'Gagal menyimpan gambar ke folder media.'
    ]);
}

/**
 * Path relatif untuk disimpan di database.
 */
$relativePath = 'uploads/media/' . $filename;

/**
 * Bentuk URL berdasarkan alamat aplikasi.
 */
$https = (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
    strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https'
);

$scheme = $https ? 'https' : 'http';
$host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');

$scriptName = str_replace(
    '\\',
    '/',
    (string) ($_SERVER['SCRIPT_NAME'] ?? '/rei/api/upload-media.php')
);

/**
 * SCRIPT_NAME biasanya /rei/api/upload-media.php.
 * Dua level di atas endpoint adalah root aplikasi /rei.
 */
$basePath = rtrim(
    dirname(dirname($scriptName)),
    '/'
);

if ($basePath === '.' || $basePath === '/') {
    $basePath = '';
}

$imageUrl = $scheme
    . '://'
    . $host
    . $basePath
    . '/'
    . $relativePath;

/**
 * Respons sukses.
 */
mediaResponse(201, [
    'success' => true,
    'message' => 'Gambar berhasil diunggah.',
    'data' => [
        'filename' => $filename,
        'path' => $relativePath,
        'url' => $imageUrl,
        'mime_type' => $mime,
        'size' => $fileSize,
        'size_formatted' => mediaFormatSize($fileSize),
        'width' => (int) ($imageInfo[0] ?? 0),
        'height' => (int) ($imageInfo[1] ?? 0)
    ]
]);
