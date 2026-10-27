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

function respond(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

require_once __DIR__ . '/api-config.php';
require_once dirname(__DIR__) . '/config.php';

// Validasi Bearer Token.
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
    // Parameter days: 7, 30, atau 90 hari.
    $days = filter_input(INPUT_GET, 'days', FILTER_VALIDATE_INT);

    if ($days === false || $days === null) {
        $days = 7;
    }

    if (!in_array($days, [7, 30, 90], true)) {
        respond(422, [
            'success' => false,
            'message' => 'Parameter days hanya boleh 7, 30, atau 90.'
        ]);
    }

    // Rentang termasuk hari ini.
    $stmt = $pdo->prepare(
        'SELECT
            visit_date,
            COALESCE(views, 0) AS views
         FROM daily_visitors
         WHERE visit_date >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
           AND visit_date <= CURDATE()
         ORDER BY visit_date ASC'
    );

    $stmt->bindValue(':days', $days, PDO::PARAM_INT);
    $stmt->execute();

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalViews = 0;
    $dailyData = [];

    foreach ($rows as $row) {
        $views = (int) $row['views'];
        $totalViews += $views;

        $dailyData[] = [
            'date' => $row['visit_date'],
            'views' => $views
        ];
    }

    $todayStmt = $pdo->query(
        'SELECT COALESCE(SUM(views), 0)
         FROM daily_visitors
         WHERE visit_date = CURDATE()'
    );

    $todayViews = (int) $todayStmt->fetchColumn();

    $yesterdayStmt = $pdo->query(
        'SELECT COALESCE(SUM(views), 0)
         FROM daily_visitors
         WHERE visit_date = DATE_SUB(CURDATE(), INTERVAL 1 DAY)'
    );

    $yesterdayViews = (int) $yesterdayStmt->fetchColumn();

    $changePercent = null;

    if ($yesterdayViews > 0) {
        $changePercent = round(
            (($todayViews - $yesterdayViews) / $yesterdayViews) * 100,
            2
        );
    } elseif ($todayViews > 0) {
        $changePercent = 100.0;
    } else {
        $changePercent = 0.0;
    }

    respond(200, [
        'success' => true,
        'message' => 'Statistik tampilan harian berhasil diambil.',
        'summary' => [
            'today_views' => $todayViews,
            'yesterday_views' => $yesterdayViews,
            'change_percent' => $changePercent,
            'period_days' => $days,
            'period_total_views' => $totalViews,
            'days_with_records' => count($dailyData)
        ],
        'data' => $dailyData
    ]);

} catch (Throwable $e) {
    error_log('Daily Visitors API: ' . $e->getMessage());

    respond(500, [
        'success' => false,
        'message' => 'Gagal mengambil statistik pengunjung.'
    ]);
}