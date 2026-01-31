<?php
require '../../includes/db.php';
require '../../includes/functions.php';

session_start();
if (!isset($_SESSION['user_id'])) {
    jsonResponse(['error' => 'Unauthorized'], 401);
}

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$role = $stmt->fetchColumn();
if ($role !== 'admin') {
    jsonResponse(['error' => 'Forbidden'], 403);
}

$payload = json_decode(file_get_contents('php://input'), true);
$table_id = isset($payload['table_id']) ? (int)$payload['table_id'] : 0;
$virtual_table_id = isset($payload['virtual_table_id']) ? trim((string)$payload['virtual_table_id']) : '';
$dice_value = isset($payload['dice_value']) ? (int)$payload['dice_value'] : 0;

if ($table_id <= 0 || $virtual_table_id === '' || $dice_value < 1 || $dice_value > 6) {
    jsonResponse(['error' => 'Invalid request'], 400);
}

$stmt = $pdo->prepare("
    SELECT id 
    FROM virtual_tables 
    WHERE id = ? AND table_id = ? AND status IN ('WAITING', 'RUNNING')
    LIMIT 1
");
$stmt->execute([$virtual_table_id, $table_id]);
$virtualTable = $stmt->fetch();

if (!$virtualTable) {
    jsonResponse(['error' => 'No active match for this table'], 404);
}

$stmt = $pdo->prepare("
    INSERT INTO dice_override (virtual_table_id, table_id, dice_value, set_by)
    VALUES (?, ?, ?, ?)
");
$stmt->execute([$virtual_table_id, $table_id, $dice_value, $user_id]);

jsonResponse(['success' => true]);
?>
