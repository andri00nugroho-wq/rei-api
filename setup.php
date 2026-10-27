<?php
require 'config.php';
$password = password_hash('admin123', PASSWORD_DEFAULT);
try {
    $stmt = $pdo->prepare("INSERT INTO users (username, password) VALUES ('admin', :password)");
    $stmt->execute(['password' => $password]);
    echo "Akun admin berhasil dibuat!<br>Username: <b>admin</b><br>Password: <b>admin123</b><br><br><a href='login.php'>Login</a>";
} catch (PDOException $e) {
    echo "Akun sudah ada atau error: " . $e->getMessage();
}
?>
