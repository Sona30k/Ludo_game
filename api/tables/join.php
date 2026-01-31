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
$debug = !empty($data['debug']);
$debugInfo = [];

if ($table_id <= 0) {
    jsonResponse(['error' => 'Invalid table'], 400);
}

$user_id = $_SESSION['user_id'];

// Block admin users from joining as players (spectator only)
$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$userRole = $stmt->fetchColumn();
if ($userRole === 'admin') {
    jsonResponse(['error' => 'Admins cannot join as players. Use spectator mode.'], 403);
}

// Cleanup stale tables to avoid ghost players
$cleanupMinutes = 3;
$stmt = $pdo->prepare("
    UPDATE virtual_tables
    SET status = 'ENDED', end_time = NOW()
    WHERE table_id = ?
      AND status = 'WAITING'
      AND wait_end_time <= DATE_SUB(NOW(), INTERVAL ? MINUTE)
");
$stmt->execute([$table_id, $cleanupMinutes]);

$stmt = $pdo->prepare("
    UPDATE virtual_tables vt
    LEFT JOIN virtual_table_players vtp 
        ON vt.id = vtp.virtual_table_id AND vtp.is_bot = 0 AND vtp.is_connected = 1
    SET vt.status = 'ENDED', vt.end_time = NOW()
    WHERE vt.table_id = ?
      AND vt.status = 'RUNNING'
      AND vtp.id IS NULL
");
$stmt->execute([$table_id]);

$stmt = $pdo->prepare("
    DELETE vtp
    FROM virtual_table_players vtp
    JOIN virtual_tables vt ON vt.id = vtp.virtual_table_id
    WHERE vt.table_id = ?
      AND vt.status = 'ENDED'
");
$stmt->execute([$table_id]);

// Step 1: Get table configuration (no status check - virtual tables manage their own status)
$stmt = $pdo->prepare("
    SELECT id, entry_points, type, time_limit,
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

// Allow full human seats; bots will only fill remaining seats at start if needed
$maxRealPlayers = $maxPlayers;

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
if ($debug) {
    $debugInfo['existingVirtualTable'] = $existingVirtualTable;
}

if ($existingVirtualTable) {
    $stmt = $pdo->prepare("
        SELECT 
            SUM(is_bot = 0) as real_players,
            SUM(is_bot = 1) as bot_count
        FROM virtual_table_players
        WHERE virtual_table_id = ?
    ");
    $stmt->execute([$existingVirtualTable['virtual_table_id']]);
    $existingCounts = $stmt->fetch();
    $realPlayersInExisting = (int)($existingCounts['real_players'] ?? 0);
    $botCountInExisting = (int)($existingCounts['bot_count'] ?? 0);

    if ($realPlayersInExisting <= 1 && $botCountInExisting > 0) {
        $stmt = $pdo->prepare("
            SELECT vt.id
            FROM virtual_tables vt
            JOIN virtual_table_players vtp ON vt.id = vtp.virtual_table_id AND vtp.is_bot = 0
            WHERE vt.table_id = ?
              AND vt.id <> ?
              AND vt.status IN ('WAITING', 'RUNNING')
              AND (
                (vt.status = 'WAITING' AND vt.wait_end_time > NOW()) OR
                (vt.status = 'RUNNING' AND (vt.end_time IS NULL OR vt.end_time > NOW()))
              )
            GROUP BY vt.id
            HAVING COUNT(vtp.id) > 0
            ORDER BY COUNT(vtp.id) DESC, vt.created_at ASC
            LIMIT 1
        ");
        $stmt->execute([$table_id, $existingVirtualTable['virtual_table_id']]);
        $betterTable = $stmt->fetch();

        if ($betterTable) {
            $stmt = $pdo->prepare("
                DELETE FROM virtual_table_players
                WHERE virtual_table_id = ? AND user_id = ? AND is_bot = 0
            ");
            $stmt->execute([$existingVirtualTable['virtual_table_id'], $user_id]);
            $existingVirtualTable = null;
            if ($debug) {
                $debugInfo['existingVirtualTable_released'] = true;
            }
        }
    }
}

// If user is stuck alone in a WAITING table, allow switching to a more populated WAITING table
if ($existingVirtualTable && $existingVirtualTable['status'] === 'WAITING') {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM virtual_table_players 
        WHERE virtual_table_id = ? AND is_bot = 0
    ");
    $stmt->execute([$existingVirtualTable['virtual_table_id']]);
    $realPlayersInExisting = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT vt.id as virtual_table_id
        FROM virtual_tables vt
        JOIN virtual_table_players vtp ON vt.id = vtp.virtual_table_id AND vtp.is_bot = 0
        WHERE vt.table_id = ?
          AND vt.status = 'WAITING'
          AND vt.id <> ?
        GROUP BY vt.id
        HAVING COUNT(vtp.id) > 0
        ORDER BY COUNT(vtp.id) DESC, vt.created_at ASC
        LIMIT 1
    ");
    $stmt->execute([$table_id, $existingVirtualTable['virtual_table_id']]);
    $betterWaiting = $stmt->fetch();

    if ($realPlayersInExisting <= 1 && $betterWaiting) {
        $stmt = $pdo->prepare("
            DELETE FROM virtual_table_players 
            WHERE virtual_table_id = ? AND user_id = ? AND is_bot = 0
        ");
        $stmt->execute([$existingVirtualTable['virtual_table_id'], $user_id]);
        $existingVirtualTable = null;
    }
}

if ($existingVirtualTable) {
    // User is already in an active virtual table - allow rejoin without charging
    $response = [
        'success' => true,
        'message' => 'Rejoining existing game...',
        'table_id' => $table_id,
        'virtual_table_id' => $existingVirtualTable['virtual_table_id'],
        'status' => $existingVirtualTable['status'],
        'redirect' => 'game.php?table_id=' . $table_id . '&virtual_table_id=' . $existingVirtualTable['virtual_table_id'],
        'rejoin' => true
    ];
    if ($debug) {
        $response['debug'] = $debugInfo;
        error_log('join.php debug: ' . json_encode($debugInfo));
    }
    jsonResponse($response);
}

// Step 3: User is new - check if there's space in an existing WAITING virtual table
// Only allow real players up to (maxPlayers - 1), one slot always reserved for bot
$stmt = $pdo->prepare("
    SELECT vt.id as virtual_table_id, 
           COUNT(DISTINCT CASE WHEN vtp.is_bot = 0 THEN vtp.id END) as real_players,
           COUNT(DISTINCT vtp.id) as total_players,
           COUNT(DISTINCT CASE WHEN vtp.is_bot = 1 THEN vtp.id END) as bot_count
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
    HAVING real_players > 0 AND real_players < ? AND (total_players < ? OR bot_count > 0)
    ORDER BY real_players DESC, vt.created_at ASC
    LIMIT 1
");
$stmt->execute([$table_id, $user_id, $maxRealPlayers, $maxPlayers]);
$availableVirtualTable = $stmt->fetch();
if ($debug) {
    $debugInfo['availableVirtualTable_initial'] = $availableVirtualTable;
}

if ($availableVirtualTable) {
    $totalPlayers = (int)$availableVirtualTable['total_players'];
    $botCount = (int)$availableVirtualTable['bot_count'];
    if ($totalPlayers >= $maxPlayers && $botCount > 0) {
        $stmt = $pdo->prepare("
            DELETE FROM virtual_table_players
            WHERE virtual_table_id = ? AND is_bot = 1
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([$availableVirtualTable['virtual_table_id']]);
    }
}

// Fallback: reuse most recent WAITING table even if wait_end_time already passed (time sync issues)
if (!$availableVirtualTable) {
    $stmt = $pdo->prepare("
        SELECT vt.id as virtual_table_id,
               COUNT(DISTINCT CASE WHEN vtp.is_bot = 0 THEN vtp.id END) as real_players,
               COUNT(DISTINCT vtp.id) as total_players,
               COUNT(DISTINCT CASE WHEN vtp.is_bot = 1 THEN vtp.id END) as bot_count
        FROM virtual_tables vt
        LEFT JOIN virtual_table_players vtp ON vt.id = vtp.virtual_table_id
        WHERE vt.table_id = ?
          AND vt.status = 'WAITING'
        GROUP BY vt.id
        HAVING real_players > 0 AND real_players < ? AND (total_players < ? OR bot_count > 0)
        ORDER BY real_players DESC, vt.created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$table_id, $maxRealPlayers, $maxPlayers]);
    $availableVirtualTable = $stmt->fetch();
    if ($debug) {
        $debugInfo['availableVirtualTable_fallback'] = $availableVirtualTable;
    }

    if ($availableVirtualTable) {
        $totalPlayers = (int)$availableVirtualTable['total_players'];
        $botCount = (int)$availableVirtualTable['bot_count'];
        if ($totalPlayers >= $maxPlayers && $botCount > 0) {
            $stmt = $pdo->prepare("
                DELETE FROM virtual_table_players
                WHERE virtual_table_id = ? AND is_bot = 1
                ORDER BY id DESC
                LIMIT 1
            ");
            $stmt->execute([$availableVirtualTable['virtual_table_id']]);
        }

        $stmt = $pdo->prepare("
            UPDATE virtual_tables
            SET wait_end_time = DATE_ADD(NOW(), INTERVAL 60 SECOND)
            WHERE id = ? AND wait_end_time <= NOW()
        ");
        $stmt->execute([$availableVirtualTable['virtual_table_id']]);
    }
}


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

// Helper function to generate UUID v4
function generateUUID() {
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

// Helper function to parse time limit (e.g., "3-min" to seconds)
function parseTimeLimit($timeLimit) {
    if (preg_match('/(\d+)-min/', $timeLimit, $matches)) {
        return (int)$matches[1] * 60;
    }
    return 180; // Default 3 minutes
}

try {
    $pdo->beginTransaction();

    // Determine virtual table ID first (before wallet transaction)
    $virtualTableId = null;
    $redirectUrl = 'game.php?table_id=' . $table_id;
    
    if ($availableVirtualTable) {
        // User will join existing virtual table
        $virtualTableId = $availableVirtualTable['virtual_table_id'];
        $redirectUrl .= '&virtual_table_id=' . $virtualTableId;
        $message = 'Joined existing game! Waiting for players...';
    } else {
        // Create new virtual table
        $virtualTableId = generateUUID();
        
        // Create virtual table
        $stmt = $pdo->prepare("
            INSERT INTO virtual_tables 
            (id, table_id, status, start_time, wait_end_time, dice_mode, created_at) 
            VALUES (?, ?, 'WAITING', NOW(), DATE_ADD(NOW(), INTERVAL 60 SECOND), 'FAIR', NOW())
        ");
        $stmt->execute([$virtualTableId, $table_id]);
        
        // Add user to virtual table (seat 0)
        $stmt = $pdo->prepare("
            INSERT INTO virtual_table_players 
            (virtual_table_id, user_id, bot_id, is_bot, seat_no, is_connected, created_at) 
            VALUES (?, ?, NULL, 0, 0, 1, NOW())
        ");
        $stmt->execute([$virtualTableId, $user_id]);
        
        $redirectUrl .= '&virtual_table_id=' . $virtualTableId;
        $message = 'Joined successfully! Waiting for players...';
    }

    // Only charge if user hasn't paid recently for this table
    // Now we have virtual_table_id to include in the transaction
    if (!$alreadyPaid) {
        // Log debit transaction with virtual_table_id
        $stmt = $pdo->prepare("
            INSERT INTO wallet_transactions (user_id, amount, type, reason, table_id, virtual_table_id) 
            VALUES (?, ?, 'debit', ?, ?, ?)
        ");
        $stmt->execute([
            $user_id, 
            $entryPoints, 
            'Joined table #' . $table_id, 
            $table_id,
            $virtualTableId
        ]);
    }

    $pdo->commit();

    $response = [
        'success' => true,
        'message' => $message,
        'table_id' => $table_id,
        'redirect' => $redirectUrl,
        'virtual_table_id' => $virtualTableId
    ];
    if ($debug) {
        $response['debug'] = $debugInfo;
        error_log('join.php debug: ' . json_encode($debugInfo));
    }
    jsonResponse($response);

} catch (Exception $e) {
    $pdo->rollBack();
    jsonResponse(['error' => 'Join failed: ' . $e->getMessage()], 500);
}
?>
