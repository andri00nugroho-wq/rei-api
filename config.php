
<?php

$host = '127.0.0.1';
$port = 3306;
$dbname = 'rei_cms_v1';
$user = 'root';
$pass = ''; 

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
