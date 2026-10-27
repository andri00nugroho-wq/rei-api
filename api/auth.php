<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function authResponse(int $status, array $data): void
{
    http_response_code($status);

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
}

// Database.
require_once dirname(__DIR__) . '/config.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    authResponse(500, [
        'success' => false,
        'message' => 'Koneksi database tidak tersedia.'
    ]);
}

// Konfigurasi sesi.
$isHttps = !empty($_SERVER['HTTPS'])
    && strtolower((string) $_SERVER['HTTPS']) !== 'off';

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/rei/',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax'
]);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$method = $_SERVER['REQUEST_METHOD'];

// Baca JSON request.
$rawBody = file_get_contents('php://input');
$input = [];

if ($rawBody !== false && trim($rawBody) !== '') {
    $decoded = json_decode($rawBody, true);

    if (!is_array($decoded)) {
        authResponse(400, [
            'success' => false,
            'message' => 'Format JSON tidak valid.'
        ]);
    }

    $input = $decoded;
}

try {
    /*
     * POST: LOGIN
     */
    if ($method === 'POST') {
        $username = trim((string) ($input['username'] ?? ''));
        $password = (string) ($input['password'] ?? '');

        if ($username === '' || $password === '') {
            authResponse(422, [
                'success' => false,
                'message' => 'Username dan password wajib diisi.'
            ]);
        }

        if (strlen($username) > 50 || strlen($password) > 4096) {
            authResponse(422, [
                'success' => false,
                'message' => 'Username atau password terlalu panjang.'
            ]);
        }

        $stmt = $pdo->prepare(
            'SELECT id, username, password
             FROM users
             WHERE username = :username
             LIMIT 1'
        );

        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Password wajib tersimpan dalam bentuk hash.
        if (
            !$user
            || !password_verify($password, (string) $user['password'])
        ) {
            authResponse(401, [
                'success' => false,
                'message' => 'Username atau password salah.'
            ]);
        }

        // Ganti ID sesi untuk mencegah session fixation.
        if (!session_regenerate_id(true)) {
            authResponse(500, [
                'success' => false,
                'message' => 'Gagal membuat sesi login.'
            ]);
        }

        $_SESSION['admin_user'] = [
            'id' => $user['id'],
            'username' => $user['username']
        ];

        authResponse(200, [
            'success' => true,
            'message' => 'Login berhasil.',
            'data' => [
                'id' => $user['id'],
                'username' => $user['username']
            ]
        ]);
    }

    /*
     * GET: CEK STATUS LOGIN
     */
    if ($method === 'GET') {
        if (empty($_SESSION['admin_user'])) {
            authResponse(401, [
                'success' => false,
                'authenticated' => false,
                'message' => 'Silakan login terlebih dahulu.'
            ]);
        }

        authResponse(200, [
            'success' => true,
            'authenticated' => true,
            'data' => $_SESSION['admin_user']
        ]);
    }

    /*
     * DELETE: LOGOUT
     */
    if ($method === 'DELETE') {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();

            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax'
            ]);
        }

        session_destroy();

        authResponse(200, [
            'success' => true,
            'message' => 'Logout berhasil.'
        ]);
    }

    header('Allow: GET, POST, DELETE');

    authResponse(405, [
        'success' => false,
        'message' => 'Method tidak diizinkan.'
    ]);

} catch (Throwable $e) {
    error_log('Auth API: ' . $e->getMessage());

    authResponse(500, [
        'success' => false,
        'message' => 'Terjadi kesalahan pada autentikasi.'
    ]);
}