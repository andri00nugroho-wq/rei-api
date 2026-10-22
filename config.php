
<?php

$host = 'db.fr-roub1.bengt.wasmernet.com';
$port = 20184;
$dbname = 'db_58299260';
$user = 'user_e12f6fbb';
$pass = 'pw_Sfke8UNhHXh0QyKLzT9OVZWi5545M2kv'; 

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
