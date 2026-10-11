<?php
$host = 'localhost';
$dbname = 'rei_cms';
$user = 'rei_user';         // Ubah ini
$pass = 'rahasia123';       // Ubah ini

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Koneksi gagal: " . $e->getMessage());
}
?>
