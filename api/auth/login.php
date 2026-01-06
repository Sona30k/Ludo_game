<?php
require '../../includes/db.php';
require '../../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Invalid method'], 405);
}

$data = json_decode(file_get_contents('php://input'), true);
$mobile   = preg_replace('/\D/', '', $data['mobile'] ?? '');
$password = $data['password'] ?? '';

if (empty($mobile) || empty($password)) {
    jsonResponse(['error' => 'Mobile and password required'], 400);
}

if (strlen($mobile) !== 10) {
    jsonResponse(['error' => 'Invalid mobile number'], 400);
}

$stmt = $pdo->prepare("SELECT id, username, mobile, password_hash, role, status FROM users WHERE mobile = ?");
$stmt->execute([$mobile]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    jsonResponse(['error' => 'Invalid mobile or password'], 401);
}

if ($user['status'] === 'blocked') {
    jsonResponse(['error' => 'Account is blocked'], 403);
}

// Start session
session_start();
$_SESSION['user_id'] = $user['id'];
$_SESSION['username'] = $user['username'];
$_SESSION['mobile'] = $user['mobile'];
$_SESSION['role'] = $user['role'];

jsonResponse([
    'success' => true,
    'message' => 'Login successful',
    'user' => [
        'id' => $user['id'],
        'username' => $user['username'],
        'mobile' => $user['mobile'],
        'role' => $user['role']
    ]
]);
?>