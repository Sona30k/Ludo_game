
<?php
$host = getenv('DB_HOST') ?: 'localhost';
$db   = getenv('DB_NAME') ?: 'ludo';
$user = getenv('DB_USER') ?: 'admin';
$pass = getenv('DB_PASSWORD') ?: 'P@sswORd123'; // change if you use a different password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    if (defined('API_JSON_RESPONSE') && API_JSON_RESPONSE === true) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Database connection failed']);
        exit;
    }
    die("Database connection failed: " . $e->getMessage());
}
