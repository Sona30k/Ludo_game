<?php
require_once 'includes/db.php'; // Adjust path if needed

// First, check if table exists and its structure
try {
    $stmt = $pdo->query("DESCRIBE dice_override");
    echo "Current dice_override table structure:\n";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "- " . $row['Field'] . " " . $row['Type'] . "\n";
    }
    echo "\n";
} catch (PDOException $e) {
    echo "Table doesn't exist or error: " . $e->getMessage() . "\n";
}

// Alter table to add missing column
$sql = "ALTER TABLE dice_override ADD COLUMN virtual_table_id VARCHAR(36) NOT NULL AFTER id";
try {
    $pdo->exec($sql);
    echo "Added virtual_table_id column successfully.\n";
} catch (PDOException $e) {
    echo "Error adding column: " . $e->getMessage() . "\n";
}

$sql2 = "ALTER TABLE dice_override ADD COLUMN used TINYINT(1) DEFAULT 0 AFTER dice_value";
try {
    $pdo->exec($sql2);
    echo "Added used column successfully.\n";
} catch (PDOException $e) {
    echo "Error adding used column: " . $e->getMessage() . "\n";
}

$sql3 = "ALTER TABLE dice_override ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP AFTER used";
try {
    $pdo->exec($sql3);
    echo "Added created_at column successfully.\n";
} catch (PDOException $e) {
    echo "Error adding created_at column: " . $e->getMessage() . "\n";
}
?>