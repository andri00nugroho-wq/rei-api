<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once dirname(__DIR__) . '/config.php';

$checks = [
    'server' => true,
    'database' => false,
    'tables' => false
];

$requiredTables = [
    'branches',
    'menus',
    'posts',
    'post_categories',
    'sliders',
    'slider_settings',
    'theme_settings',
    'daily_visitors',
    'visitor_stats'
];

try {
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new RuntimeException('PDO connection unavailable');
    }

    $pdo->query('SELECT 1');
    $checks['database'] = true;

    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = :table_name'
    );

    $missingTables = [];

    foreach ($requiredTables as $table) {
        $stmt->execute([':table_name' => $table]);

        if ((int) $stmt->fetchColumn() === 0) {
            $missingTables[] = $table;
        }
    }

    $checks['tables'] = count($missingTables) === 0;

    $healthy = $checks['database'] && $checks['tables'];

    http_response_code($healthy ? 200 : 503);

    echo json_encode([
        'success' => $healthy,
        'status' => $healthy ? 'healthy' : 'degraded',
        'checks' => $checks,
        'missing_tables' => $missingTables,
        'timestamp' => date(DATE_ATOM)
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log('Health Check: ' . $e->getMessage());

    http_response_code(503);

    echo json_encode([
        'success' => false,
        'status' => 'unhealthy',
        'checks' => $checks,
        'message' => 'Server atau database tidak siap.'
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}