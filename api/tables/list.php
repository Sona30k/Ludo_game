<?php
require '../../includes/db.php';
require '../../includes/functions.php';

session_start();
if (!isset($_SESSION['user_id'])) {
    jsonResponse(['error' => 'Unauthorized'], 401);
}

// Get open tables OR tables with waiting games (players can still join)
$stmt = $pdo->prepare("
    SELECT 
        t.id,
        t.type,
        t.time_limit,
        t.entry_points,
        t.status,
        (CASE 
            WHEN t.type = '2-player' THEN 2 
            WHEN t.type = '4-player' THEN 4 
        END) AS total_spots,
        COUNT(DISTINCT wt.user_id) as joined_players
    FROM tables t
    LEFT JOIN wallet_transactions wt ON wt.reason LIKE CONCAT('%table #', t.id, '%') AND wt.type = 'debit'
    WHERE t.status = 'open'
    GROUP BY t.id, t.type, t.time_limit, t.entry_points, t.status
    HAVING COUNT(DISTINCT wt.user_id) < (CASE WHEN t.type = '2-player' THEN 2 ELSE 4 END) 
        OR COUNT(DISTINCT wt.user_id) = 0
    ORDER BY t.created_at DESC
");
$stmt->execute();
$tables = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Prize pool and spots calculation
foreach ($tables as &$table) {
    $maxPlayers = $table['type'] === '2-player' ? 2 : 4;
    $joinedPlayers = (int)$table['joined_players'];
    
    // Prize pool = entry * max players (potential pool)
    $table['prize_pool'] = $table['entry_points'] * $maxPlayers;
    
    // Spots left = max - joined
    $table['spots_left'] = max(0, $maxPlayers - $joinedPlayers);
    
    // Remove helper field
    unset($table['joined_players']);
}

jsonResponse(['tables' => $tables]);
?>
