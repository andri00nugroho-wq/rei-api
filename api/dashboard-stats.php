<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function respond(int $status, array $data): void
{
    http_response_code($status);
    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

require_once __DIR__ . '/api-config.php';
require_once dirname(__DIR__) . '/config.php';

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
    respond(401, [
        'success' => false,
        'message' => 'Token API tidak valid atau tidak tersedia.'
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET, OPTIONS');

    respond(405, [
        'success' => false,
        'message' => 'Method tidak diizinkan.'
    ]);
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    respond(500, [
        'success' => false,
        'message' => 'Koneksi database PDO tidak tersedia.'
    ]);
}

try {
    // Total kumulatif dari visitor_stats.
    $totalStmt = $pdo->query(
        'SELECT COALESCE(SUM(total_views), 0)
         FROM visitor_stats'
    );
    $totalViews = (int) $totalStmt->fetchColumn();

    // Tampilan hari ini.
    $todayStmt = $pdo->query(
        'SELECT COALESCE(SUM(views), 0)
         FROM daily_visitors
         WHERE visit_date = CURDATE()'
    );
    $todayViews = (int) $todayStmt->fetchColumn();

    // Tampilan kemarin.
    $yesterdayStmt = $pdo->query(
        'SELECT COALESCE(SUM(views), 0)
         FROM daily_visitors
         WHERE visit_date = DATE_SUB(CURDATE(), INTERVAL 1 DAY)'
    );
    $yesterdayViews = (int) $yesterdayStmt->fetchColumn();

    // Persentase perubahan dibandingkan kemarin.
    if ($yesterdayViews > 0) {
        $changePercent = round(
            (($todayViews - $yesterdayViews) / $yesterdayViews) * 100,
            2
        );
    } else {
        $changePercent = $todayViews > 0 ? 100.0 : 0.0;
    }

    // Ringkasan 7 hari terakhir, termasuk hari ini.
    $weekStmt = $pdo->query(
        'SELECT
            visit_date,
            COALESCE(views, 0) AS views
         FROM daily_visitors
         WHERE visit_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
           AND visit_date <= CURDATE()
         ORDER BY visit_date ASC'
    );

    $weekData = [];
    $weekViews = 0;

    foreach ($weekStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $views = (int) $row['views'];
        $weekViews += $views;

        $weekData[] = [
            'date' => $row['visit_date'],
            'views' => $views
        ];
    }

    respond(200, [
        'success' => true,
        'message' => 'Statistik dashboard berhasil diambil.',
        'data' => [
            'total_views' => $totalViews,
            'today_views' => $todayViews,
            'yesterday_views' => $yesterdayViews,
            'change_percent' => $changePercent,
            'last_7_days_views' => $weekViews,
            'last_7_days' => $weekData
        ]
    ]);

} catch (Throwable $e) {
    error_log('Dashboard Stats API: ' . $e->getMessage());

    respond(500, [
        'success' => false,
        'message' => 'Gagal mengambil statistik dashboard.'
    ]);
}
