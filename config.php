<?php
$dbname = 'rei_cms_v1';
$user   = 'admin_cms';
$pass   = 'M@juterusrajaemas';

$pdo = null;
$lastError = null;

foreach (['localhost', '127.0.0.1'] as $host) {
    try {
        $pdo = new PDO(
            "mysql:host={$host};port=3306;dbname={$dbname};charset=utf8mb4",
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
        break;
    } catch (PDOException $e) {
        $lastError = $e;
    }
}

if (!($pdo instanceof PDO)) {
    error_log('[REI CONFIG] Koneksi gagal: ' . $lastError->getMessage());
    throw new RuntimeException('Koneksi database gagal: ' . $lastError->getMessage());
}
