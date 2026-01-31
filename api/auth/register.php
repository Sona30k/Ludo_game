<?php
require '../../includes/db.php';
require '../../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Invalid method'], 405);
}

$data = json_decode(file_get_contents('php://input'), true);

$username = trim($data['username'] ?? '');
$mobile   = preg_replace('/\D/', '', $data['mobile'] ?? ''); // only digits
$password = $data['password'] ?? '';

if (empty($username) || empty($mobile) || empty($password)) {
    jsonResponse(['error' => 'All fields are required'], 400);
}

if (strlen($mobile) !== 10) {
    jsonResponse(['error' => 'Mobile number must be 10 digits'], 400);
}

if (strlen($password) < 6) {
    jsonResponse(['error' => 'Password must be at least 6 characters'], 400);
}

$hash = password_hash($password, PASSWORD_DEFAULT);

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("INSERT INTO users (username, mobile, password_hash) VALUES (?, ?, ?)");
    $stmt->execute([$username, $mobile, $hash]);
    $userId = $pdo->lastInsertId();

    // Create wallet
    $stmt = $pdo->prepare("INSERT INTO wallets (user_id, balance) VALUES (?, 100)"); // 100 free points
    $stmt->execute([$userId]);

    $pdo->commit();

    jsonResponse(['success' => true, 'message' => 'Registration successful', 'user_id' => $userId]);
} catch (Exception $e) {
    $pdo->rollBack();
    if ($e->getCode() == 23000) { // Duplicate entry
        jsonResponse(['error' => 'Mobile number already registered'], 409);
    }
    jsonResponse(['error' => 'Registration failed'], 500);
}
?>