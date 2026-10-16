<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Vary: Cookie, Authorization');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function dashboardResponse(int $status, array $data): void
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

/**
 * Validasi metode HTTP.
 */
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET, OPTIONS');

    dashboardResponse(405, [
        'success' => false,
        'message' => 'Method tidak diizinkan.',
    ]);
}

/**
 * Autentikasi sesi admin PHP.
 */
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$isAdminSession =
    !empty($_SESSION['admin_user'])
    || !empty($_SESSION['admin_logged_in']);

/**
 * Autentikasi Bearer Token untuk komunikasi server-to-server.
 */
$authorization = $_SERVER['HTTP_AUTHORIZATION']
    ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
    ?? '';

if ($authorization === '' && function_exists('getallheaders')) {
    $headers = getallheaders();

    foreach ($headers as $name => $value) {
        if (strcasecmp($name, 'Authorization') === 0) {
            $authorization = $value;
            break;
        }
    }
}

$isValidToken = false;

if (
    preg_match('/^Bearer\s+(\S+)$/i', trim($authorization), $matches)
) {
    require_once __DIR__ . '/api-config.php';

    if (
        defined('REI_API_TOKEN')
        && is_string(REI_API_TOKEN)
        && REI_API_TOKEN !== ''
    ) {
        $isValidToken = hash_equals(
            REI_API_TOKEN,
            $matches[1]
        );
    }
}

if (!$isAdminSession && !$isValidToken) {
    header('WWW-Authenticate: Bearer');

    dashboardResponse(401, [
        'success' => false,
        'message' => 'Autentikasi diperlukan.',
    ]);
}

/**
 * Koneksi database.
 */
require_once dirname(__DIR__) . '/config.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    dashboardResponse(500, [
        'success' => false,
        'message' => 'Koneksi database tidak tersedia.',
    ]);
}

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    /**
     * 1. Statistik konten.
     */
    $contentStmt = $pdo->query(
        "SELECT
            COALESCE(SUM(CASE WHEN type = 'post' THEN 1 ELSE 0 END), 0)
                AS total_posts,
            COALESCE(SUM(CASE WHEN type = 'page' THEN 1 ELSE 0 END), 0)
                AS total_pages
         FROM posts"
    );

    $content = $contentStmt->fetch() ?: [];

    /**
     * 2. Jumlah kategori, cabang, dan slider.
     */
    $categories = (int) $pdo
        ->query('SELECT COUNT(*) FROM post_categories')
        ->fetchColumn();

    $branches = (int) $pdo
        ->query('SELECT COUNT(*) FROM branches')
        ->fetchColumn();

    $sliders = (int) $pdo
        ->query('SELECT COUNT(*) FROM sliders')
        ->fetchColumn();

    /**
     * 3. Total tampilan kumulatif.
     *
     * Mempertahankan sumber statistik yang digunakan kode sebelumnya.
     * Pastikan visitor_stats memang menyimpan total kumulatif.
     */
    $totalViews = (int) $pdo
        ->query(
            'SELECT COALESCE(SUM(total_views), 0)
             FROM visitor_stats'
        )
        ->fetchColumn();

    /**
     * 4. Statistik pengunjung hari ini dan kemarin.
     */
    $visitorStmt = $pdo->query(
        'SELECT
            COALESCE(SUM(
                CASE WHEN visit_date = CURDATE()
                THEN views ELSE 0 END
            ), 0) AS today_views,
            COALESCE(SUM(
                CASE WHEN visit_date = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
                THEN views ELSE 0 END
            ), 0) AS yesterday_views
         FROM daily_visitors
         WHERE visit_date IN (
             CURDATE(),
             DATE_SUB(CURDATE(), INTERVAL 1 DAY)
         )'
    );

    $visitorData = $visitorStmt->fetch() ?: [];

    $todayViews = (int) ($visitorData['today_views'] ?? 0);
    $yesterdayViews = (int) ($visitorData['yesterday_views'] ?? 0);

    $changePercent = $yesterdayViews > 0
        ? round(
            (($todayViews - $yesterdayViews) / $yesterdayViews) * 100,
            2
        )
        : ($todayViews > 0 ? 100.0 : 0.0);

    /**
     * 5. Grafik tujuh hari, termasuk tanggal yang belum memiliki data.
     */
    $chartStmt = $pdo->query(
        'SELECT visit_date, COALESCE(views, 0) AS views
         FROM daily_visitors
         WHERE visit_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
           AND visit_date <= CURDATE()
         ORDER BY visit_date ASC'
    );

    $viewsByDate = [];

    foreach ($chartStmt->fetchAll() as $row) {
        $viewsByDate[$row['visit_date']] = (int) $row['views'];
    }

    $chart = [];
    $lastSevenDaysViews = 0;

    $today = new DateTimeImmutable('today');

    for ($i = 6; $i >= 0; $i--) {
        $date = $today->modify("-{$i} days")->format('Y-m-d');
        $views = $viewsByDate[$date] ?? 0;

        $chart[] = [
            'date' => $date,
            'views' => $views,
        ];

        $lastSevenDaysViews += $views;
    }

    /**
     * 6. Respons dashboard.
     */
    dashboardResponse(200, [
        'success' => true,
        'message' => 'Data dashboard berhasil diambil.',
        'data' => [
            'content' => [
                'total_posts' => (int) ($content['total_posts'] ?? 0),
                'total_pages' => (int) ($content['total_pages'] ?? 0),
                'total_categories' => $categories,
            ],
            'branches' => [
                'total' => $branches,
            ],
            'sliders' => [
                'total' => $sliders,
            ],
            'visitors' => [
                'total_views' => $totalViews,
                'today_views' => $todayViews,
                'yesterday_views' => $yesterdayViews,
                'change_percent' => $changePercent,
                'last_7_days_views' => $lastSevenDaysViews,
                'chart' => $chart,
            ],
            'generated_at' => date(DATE_ATOM),
        ],
    ]);
} catch (Throwable $e) {
    error_log('Dashboard API: ' . $e->getMessage());

    dashboardResponse(500, [
        'success' => false,
        'message' => 'Gagal mengambil data dashboard.',
    ]);
}