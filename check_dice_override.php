<?php
require_once '../includes/db.php'; // Adjust path if needed

try {
    // Check if table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'dice_override'");
    if ($stmt->rowCount() > 0) {
        echo "Table 'dice_override' exists.\n";
        // Show structure
        $stmt = $pdo->query("DESCRIBE dice_override");
        echo "Structure:\n";
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "- " . $row['Field'] . " " . $row['Type'] . " " . ($row['Null'] == 'NO' ? 'NOT NULL' : 'NULL') . "\n";
        }
    } else {
        echo "Table 'dice_override' does not exist.\n";
    }

    // Test the query that fails
    $testQuery = "SELECT dice_value FROM dice_override WHERE virtual_table_id = ? AND table_id = ? AND used = 0 ORDER BY created_at DESC LIMIT 1";
    echo "Test query: $testQuery\n";
    $stmt = $pdo->prepare($testQuery);
    $stmt->execute(['test-vt-id', 1]);
    $result = $stmt->fetchAll();
    echo "Query executed successfully, rows: " . count($result) . "\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>