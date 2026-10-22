<?php

$host = getenv('REI_DB_HOST') ?: 'localhost';
$port = 3306;
$dbname = getenv('REI_DB_NAME') ?: 'dev';
$user = getenv('REI_DB_USER') ?: 'admin_api';
$pass = getenv('REI_DB_PASSWORD') ?: 'Majuterusrajaemas';

try {
    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

} catch (PDOException $e) {
    die('Koneksi gagal: ' . $e->getMessage());
}
?>
