<?php
require_once '../includes/db.php'; // Adjust path if needed

$sql = "
CREATE TABLE IF NOT EXISTS dice_override (
    id INT AUTO_INCREMENT PRIMARY KEY,
    virtual_table_id VARCHAR(36) NOT NULL,
    table_id INT NOT NULL,
    dice_value TINYINT NOT NULL CHECK (dice_value BETWEEN 1 AND 6),
    used TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_vt_table (virtual_table_id, table_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

try {
    $pdo->exec($sql);
    echo "Table 'dice_override' created successfully.\n";
} catch (PDOException $e) {
    echo "Error creating table: " . $e->getMessage() . "\n";
}
?>