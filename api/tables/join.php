<?php
require '../../includes/db.php';
require '../../includes/functions.php';

session_start();
if (!isset($_SESSION['user_id'])) {
    jsonResponse(['error' => 'Unauthorized'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Invalid method'], 405);
}

$data = json_decode(file_get_contents('php://input'), true);
$table_id = $data['table_id'] ?? 0;

if ($table_id <= 0) {
    jsonResponse(['error' => 'Invalid table'], 400);
}

$user_id = $_SESSION['user_id'];

// Step 1: Get table configuration (no status check - virtual tables manage their own status)
$stmt = $pdo->prepare("
    SELECT id, entry_points, type,
           (CASE WHEN type = '2-player' THEN 2 ELSE 4 END) as max_players
    FROM tables 
    WHERE id = ?
");
$stmt->execute([$table_id]);
$table = $stmt->fetch();

if (!$table) {
    jsonResponse(['error' => 'Table not found'], 404);
}

$maxPlayers = (int)$table['max_players'];
$entryPoints = (int)$table['entry_points'];

// Calculate max real players (one slot always reserved for bot)
// 2-player: max 1 real player (1 bot)
// 4-player: max 3 real players (1 bot)
$maxRealPlayers = $maxPlayers - 1;

// Step 2: Check if user is already in an active virtual table for this table
// Priority: Check WAITING first, then RUNNING
$stmt = $pdo->prepare("
    SELECT vt.id as virtual_table_id, vt.status, vt.wait_end_time, vt.end_time
    FROM virtual_tables vt
    JOIN virtual_table_players vtp ON vt.id = vtp.virtual_table_id
    WHERE vtp.user_id = ? 
      AND vt.table_id = ?
      AND vt.status IN ('WAITING', 'RUNNING')
      AND (
        (vt.status = 'WAITING' AND vt.wait_end_time > NOW()) OR
        (vt.status = 'RUNNING' AND (vt.end_time IS NULL OR vt.end_time > NOW()))
      )
    ORDER BY 
      CASE WHEN vt.status = 'RUNNING' THEN 1 ELSE 2 END,
      vt.created_at DESC
    LIMIT 1
");
$stmt->execute([$user_id, $table_id]);
$existingVirtualTable = $stmt->fetch();

if ($existingVirtualTable) {
    // User is already in an active virtual table - allow rejoin without charging
    jsonResponse([
        'success' => true,
        'message' => 'Rejoining existing game...',
        'table_id' => $table_id,
        'virtual_table_id' => $existingVirtualTable['virtual_table_id'],
        'status' => $existingVirtualTable['status'],
        'redirect' => 'game.php?table_id=' . $table_id . '&virtual_table_id=' . $existingVirtualTable['virtual_table_id'],
        'rejoin' => true
    ]);
}

// Step 3: User is new - check if there's space in an existing WAITING virtual table
// Only allow real players up to (maxPlayers - 1), one slot always reserved for bot
$stmt = $pdo->prepare("
    SELECT vt.id as virtual_table_id, 
           COUNT(DISTINCT CASE WHEN vtp.is_bot = 0 THEN vtp.id END) as real_players,
           COUNT(DISTINCT vtp.id) as total_players
    FROM virtual_tables vt
    LEFT JOIN virtual_table_players vtp ON vt.id = vtp.virtual_table_id
    WHERE vt.table_id = ?
      AND vt.status = 'WAITING'
      AND vt.wait_end_time > NOW()
      AND NOT EXISTS (
          SELECT 1 FROM virtual_table_players 
          WHERE virtual_table_id = vt.id AND user_id = ?
      )
    GROUP BY vt.id
    HAVING real_players < ? AND total_players < ?
    ORDER BY vt.created_at ASC
    LIMIT 1
");
$stmt->execute([$table_id, $user_id, $maxRealPlayers, $maxPlayers]);
$availableVirtualTable = $stmt->fetch();

// Step 4: Calculate user balance using global function
$balance = calculateUserBalance($user_id, $pdo);

if ($balance < $entryPoints) {
    jsonResponse(['error' => 'Insufficient balance'], 400);
}

// Step 5: Check if user already paid entry fee for this table (to prevent double charge)
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM wallet_transactions 
    WHERE user_id = ? 
      AND reason LIKE ? 
      AND type = 'debit'
      AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
");
$stmt->execute([$user_id, "%table #{$table_id}%"]);
$alreadyPaid = $stmt->fetchColumn() > 0;

try {
    $pdo->beginTransaction();

    // Only charge if user hasn't paid recently for this table
    if (!$alreadyPaid) {
        // Log debit transaction (balance is calculated from transactions, no wallet table update needed)
        $stmt = $pdo->prepare("
            INSERT INTO wallet_transactions (user_id, amount, type, reason, table_id) 
            VALUES (?, ?, 'debit', ?, ?)
        ");
        $stmt->execute([$user_id, $entryPoints, 'Joined table #' . $table_id, $table_id]);
    }

    $pdo->commit();

    // Determine redirect URL
    $redirectUrl = 'game.php?table_id=' . $table_id;
    if ($availableVirtualTable) {
        // User will join existing virtual table
        $redirectUrl .= '&virtual_table_id=' . $availableVirtualTable['virtual_table_id'];
        $message = 'Joined existing game! Waiting for players...';
    } else {
        // New virtual table will be created by WebSocket
        $message = 'Joined successfully! Waiting for players...';
    }

    jsonResponse([
        'success' => true,
        'message' => $message,
        'table_id' => $table_id,
        'redirect' => $redirectUrl,
        'virtual_table_id' => $availableVirtualTable['virtual_table_id'] ?? null
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    jsonResponse(['error' => 'Join failed: ' . $e->getMessage()], 500);
}
?>