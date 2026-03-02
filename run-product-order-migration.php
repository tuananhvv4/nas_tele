<?php
require_once __DIR__ . '/../config/db.php';

echo "<h2>Running migration: add_product_display_order.sql</h2>";

try {
    // Read SQL file
    $sql = file_get_contents(__DIR__ . '/add_product_display_order.sql');
    
    // Split by semicolon and execute each statement
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    foreach ($statements as $statement) {
        if (empty($statement) || strpos($statement, '--') === 0) {
            continue;
        }
        
        echo "<p>Executing: " . htmlspecialchars(substr($statement, 0, 100)) . "...</p>";
        $pdo->exec($statement);
        echo "<p style='color: green;'>✓ Success</p>";
    }
    
    echo "<h3 style='color: green;'>✅ Migration completed successfully!</h3>";
    
    // Show current products with display_order
    echo "<h3>Current Products:</h3>";
    $stmt = $pdo->query("SELECT id, name, display_order FROM products ORDER BY display_order ASC");
    $products = $stmt->fetchAll();
    
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Name</th><th>Display Order</th></tr>";
    foreach ($products as $p) {
        echo "<tr><td>{$p['id']}</td><td>{$p['name']}</td><td>{$p['display_order']}</td></tr>";
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "<h3 style='color: red;'>❌ Migration failed!</h3>";
    echo "<p style='color: red;'>" . htmlspecialchars($e->getMessage()) . "</p>";
}
