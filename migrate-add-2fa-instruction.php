<?php
/**
 * Migration: Add 2FA Instruction Column
 * Adds twofa_instruction column to products table for Style 2 delivery
 */

require_once __DIR__ . '/config/db.php';

echo "<h1>🔄 Adding 2FA Instruction Support</h1>";
echo "<hr>";

try {
    // Check if column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM `products` LIKE 'twofa_instruction'");
    
    if ($stmt->rowCount() > 0) {
        echo "<div style='background: #fff3cd; border: 1px solid #ffc107; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
        echo "⏭️ Column 'twofa_instruction' already exists. Skipping...";
        echo "</div>";
    } else {
        // Add column
        $pdo->exec("
            ALTER TABLE products 
            ADD COLUMN twofa_instruction TEXT NULL 
            AFTER custom_message
        ");
        
        echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
        echo "✅ Successfully added column 'twofa_instruction' to products table";
        echo "</div>";
    }
    
    // Verify
    $stmt = $pdo->query("SHOW COLUMNS FROM `products` LIKE 'twofa_instruction'");
    $column = $stmt->fetch();
    
    if ($column) {
        echo "<div style='background: #cfe2ff; border: 1px solid #b6d4fe; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
        echo "<h3 style='margin-top: 0;'>✅ Migration Completed!</h3>";
        echo "<p><strong>Column Details:</strong></p>";
        echo "<ul>";
        echo "<li><strong>Field:</strong> {$column['Field']}</li>";
        echo "<li><strong>Type:</strong> {$column['Type']}</li>";
        echo "<li><strong>Null:</strong> {$column['Null']}</li>";
        echo "<li><strong>Default:</strong> " . ($column['Default'] ?? 'NULL') . "</li>";
        echo "</ul>";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; border: 1px solid #f5c2c7; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
    echo "<h3 style='color: #842029; margin-top: 0;'>❌ Migration Error!</h3>";
    echo "<p style='color: #842029;'>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}
?>

<style>
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        padding: 20px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
    }
    h1, h3 {
        color: #333;
    }
</style>
