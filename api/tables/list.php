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
        t.status           
    FROM tables t    
    ORDER BY t.created_at DESC
");
$stmt->execute();
$tables = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Prize pool and spots calculation
foreach ($tables as &$table) {
    $maxPlayers = $table['type'] === '2-player' ? 2 : 4;    
    
    // Prize pool = entry * max players (potential pool)
    $table['prize_pool'] = $table['entry_points'] * $maxPlayers;
    
    // Spots left = max - joined
    $table['spots_left'] = $maxPlayers;    
    
}

jsonResponse(['tables' => $tables]);
?>
