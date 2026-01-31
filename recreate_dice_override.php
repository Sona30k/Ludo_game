<?php
require_once '../includes/db.php'; // Adjust path if needed

// Drop and recreate the table
$sql = "
DROP TABLE IF EXISTS dice_override;
CREATE TABLE dice_override (
    id INT AUTO_INCREMENT PRIMARY KEY,
    virtual_table_id VARCHAR(36) NOT NULL,
    table_id INT NOT NULL,
    dice_value TINYINT NOT NULL,
    used TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_vt_table (virtual_table_id, table_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

try {
    $pdo->exec($sql);
    echo "Table 'dice_override' dropped and recreated successfully.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>