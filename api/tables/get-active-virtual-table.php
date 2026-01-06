<?php
require '../../includes/db.php';
require '../../includes/functions.php';

session_start();
if (!isset($_SESSION['user_id'])) {
    jsonResponse(['error' => 'Unauthorized'], 401);
}

$table_id = isset($_GET['table_id']) ? (int)$_GET['table_id'] : 0;
$user_id = $_SESSION['user_id'];

if ($table_id <= 0) {
    jsonResponse(['error' => 'Invalid table'], 400);
}

// Find active virtual table for this user and table
// Priority: Check user's existing virtual table first
$stmt = $pdo->prepare("
    SELECT vt.id as virtual_table_id, vt.status, vt.end_time, vt.wait_end_time
    FROM virtual_tables vt
    JOIN virtual_table_players vtp ON vt.id = vtp.virtual_table_id
    WHERE vtp.user_id = ? 
      AND vt.table_id = ?
      AND vt.status IN ('WAITING', 'RUNNING')
      AND (
        (vt.status = 'WAITING' AND vt.wait_end_time > NOW()) OR
        (vt.status = 'RUNNING' AND (vt.end_time IS NULL OR vt.end_time > NOW()))
      )
    ORDER BY vt.created_at DESC
    LIMIT 1
");
$stmt->execute([$user_id, $table_id]);
$virtualTable = $stmt->fetch();

if ($virtualTable) {
    jsonResponse([
        'success' => true,
        'virtualTableId' => $virtualTable['virtual_table_id'],
        'status' => $virtualTable['status']
    ]);
} else {
    jsonResponse([
        'success' => false,
        'message' => 'No active virtual table found'
    ]);
}
?>

