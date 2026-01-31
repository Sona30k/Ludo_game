<?php
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function isLoggedIn() {
    session_start();
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    session_start();
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function requireLogin($adminOnly = false) {
    if (!isLoggedIn()) {
        jsonResponse(['error' => 'Unauthorized'], 401);
    }
    if ($adminOnly && !isAdmin()) {
        jsonResponse(['error' => 'Admin access required'], 403);
    }
}

/**
 * Calculate user balance from wallet_transactions
 * Balance = SUM(credits) - SUM(debits)
 * 
 * @param int $user_id User ID
 * @param PDO $pdo Database connection (optional, will use global if not provided)
 * @return float User balance
 */
function calculateUserBalance($user_id, $pdo = null) {
    // Use global $pdo if not provided
    if ($pdo === null) {
        global $pdo;
    }
    
    if (!$pdo) {
        require __DIR__ . '/db.php';
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                COALESCE(SUM(CASE WHEN type = 'credit' THEN amount ELSE 0 END), 0) -
                COALESCE(SUM(CASE WHEN type = 'debit' THEN amount ELSE 0 END), 0) as balance
            FROM wallet_transactions 
            WHERE user_id = ?
        ");
        $stmt->execute([$user_id]);
        $balance = $stmt->fetchColumn();
        
        return (float)($balance ?? 0);
    } catch (PDOException $e) {
        error_log("Error calculating user balance: " . $e->getMessage());
        return 0;
    }
}
?>