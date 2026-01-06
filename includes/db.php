<?php
$host = 'localhost';
$db = 'ludo_platform';
$user = 'root';  // Change in production
$pass = '';      // Change in production

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>