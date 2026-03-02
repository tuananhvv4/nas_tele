<?php
/**
 * Debug Script: Check Product Delivery Style
 */

require_once __DIR__ . '/config/db.php';

echo "<h1>🔍 Debug: Product Delivery Styles</h1>";
echo "<hr>";

try {
    // Get all products
    $stmt = $pdo->query("
        SELECT id, name, delivery_style, twofa_instruction 
        FROM products 
        ORDER BY id DESC
        LIMIT 10
    ");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<div style='background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);'>";
    echo "<h3>📦 Recent Products (Last 10)</h3>";
    echo "<table border='1' cellpadding='10' cellspacing='0' style='width: 100%; border-collapse: collapse;'>";
    echo "<thead>";
    echo "<tr style='background: #667eea; color: white;'>";
    echo "<th>ID</th>";
    echo "<th>Product Name</th>";
    echo "<th>Delivery Style</th>";
    echo "<th>2FA Instruction</th>";
    echo "</tr>";
    echo "</thead>";
    echo "<tbody>";
    
    foreach ($products as $product) {
        $styleColor = 'black';
        if ($product['delivery_style'] === 'style1') $styleColor = 'blue';
        if ($product['delivery_style'] === 'style2') $styleColor = 'green';
        
        echo "<tr>";
        echo "<td>{$product['id']}</td>";
        echo "<td><strong>{$product['name']}</strong></td>";
        echo "<td style='color: {$styleColor}; font-weight: bold;'>" . ($product['delivery_style'] ?? 'NULL') . "</td>";
        echo "<td>" . (empty($product['twofa_instruction']) ? '<em style="color: #999;">Empty</em>' : htmlspecialchars(substr($product['twofa_instruction'], 0, 50)) . '...') . "</td>";
        echo "</tr>";
    }
    
    echo "</tbody>";
    echo "</table>";
    echo "</div>";
    
    // Check specific product if ID provided
    if (isset($_GET['id'])) {
        $id = intval($_GET['id']);
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($product) {
            echo "<div style='background: #e7f3ff; padding: 20px; border-radius: 8px; margin-top: 20px;'>";
            echo "<h3>🔎 Product #{$id} Details</h3>";
            echo "<pre style='background: #fff; padding: 15px; border-radius: 5px; overflow-x: auto;'>";
            print_r($product);
            echo "</pre>";
            echo "</div>";
        }
    }
    
    echo "<div style='background: #fff3cd; padding: 15px; border-radius: 8px; margin-top: 20px;'>";
    echo "<h4>💡 How to use:</h4>";
    echo "<p>Add <code>?id=X</code> to URL to see full details of product ID X</p>";
    echo "<p>Example: <code>debug-delivery-style.php?id=1</code></p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 8px;'>";
    echo "<h3 style='color: #842029;'>❌ Error!</h3>";
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
    h1, h3, h4 {
        color: #333;
    }
    table {
        background: white;
    }
    th {
        text-align: left;
    }
</style>
