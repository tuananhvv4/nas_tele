<?php
/**
 * Debug: Check POST data when editing product
 */

require_once __DIR__ . '/config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    echo "<h1>🔍 Debug: POST Data Received</h1>";
    echo "<hr>";
    
    echo "<div style='background: #fff; padding: 20px; border-radius: 8px;'>";
    echo "<h3>📨 $_POST Data:</h3>";
    echo "<pre style='background: #f5f5f5; padding: 15px; border-radius: 5px; overflow-x: auto;'>";
    print_r($_POST);
    echo "</pre>";
    echo "</div>";
    
    echo "<div style='background: #e7f3ff; padding: 20px; border-radius: 8px; margin-top: 20px;'>";
    echo "<h3>🔎 Extracted Values:</h3>";
    echo "<ul>";
    echo "<li><strong>ID:</strong> " . intval($_POST['id'] ?? 0) . "</li>";
    echo "<li><strong>Name:</strong> " . htmlspecialchars(trim($_POST['name'] ?? '')) . "</li>";
    echo "<li><strong>Price:</strong> " . intval($_POST['price'] ?? 0) . "</li>";
    echo "<li><strong>Delivery Style:</strong> <span style='color: red; font-weight: bold;'>" . htmlspecialchars($_POST['delivery_style'] ?? 'NOT SET') . "</span></li>";
    echo "<li><strong>2FA Instruction:</strong> " . htmlspecialchars(trim($_POST['twofa_instruction'] ?? '')) . "</li>";
    echo "</ul>";
    echo "</div>";
    
    // Now actually process the update
    $id = intval($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = intval($_POST['price'] ?? 0);
    $category = trim($_POST['category'] ?? '');
    $delivery_style = $_POST['delivery_style'] ?? 'default';
    $custom_message = trim($_POST['custom_message'] ?? '');
    $login_url = trim($_POST['login_url'] ?? '');
    $twofa_instruction = trim($_POST['twofa_instruction'] ?? '');
    
    echo "<div style='background: #fff3cd; padding: 20px; border-radius: 8px; margin-top: 20px;'>";
    echo "<h3>💾 SQL Query:</h3>";
    echo "<pre style='background: #fff; padding: 15px; border-radius: 5px;'>";
    echo "UPDATE products SET \n";
    echo "  name = '$name',\n";
    echo "  description = '$description',\n";
    echo "  price = $price,\n";
    echo "  category = '$category',\n";
    echo "  delivery_style = '<span style='color: red; font-weight: bold;'>$delivery_style</span>',\n";
    echo "  custom_message = '$custom_message',\n";
    echo "  login_url = '$login_url',\n";
    echo "  twofa_instruction = '$twofa_instruction'\n";
    echo "WHERE id = $id";
    echo "</pre>";
    echo "</div>";
    
    if ($id && $name && $price > 0) {
        try {
            $stmt = $pdo->prepare("UPDATE products SET name = ?, description = ?, price = ?, category = ?, delivery_style = ?, custom_message = ?, login_url = ?, twofa_instruction = ? WHERE id = ?");
            $result = $stmt->execute([$name, $description, $price, $category, $delivery_style, $custom_message, $login_url, $twofa_instruction, $id]);
            
            echo "<div style='background: #d4edda; padding: 20px; border-radius: 8px; margin-top: 20px;'>";
            echo "<h3>✅ Update Result:</h3>";
            echo "<p><strong>Success:</strong> " . ($result ? 'YES' : 'NO') . "</p>";
            echo "<p><strong>Rows Affected:</strong> " . $stmt->rowCount() . "</p>";
            echo "</div>";
            
            // Verify in database
            $stmt = $pdo->prepare("SELECT delivery_style, twofa_instruction FROM products WHERE id = ?");
            $stmt->execute([$id]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo "<div style='background: #cfe2ff; padding: 20px; border-radius: 8px; margin-top: 20px;'>";
            echo "<h3>🔍 Verify in Database:</h3>";
            echo "<p><strong>Delivery Style in DB:</strong> <span style='color: " . ($product['delivery_style'] === 'style2' ? 'green' : 'red') . "; font-weight: bold;'>" . ($product['delivery_style'] ?? 'NULL') . "</span></p>";
            echo "<p><strong>2FA Instruction in DB:</strong> " . htmlspecialchars($product['twofa_instruction'] ?? 'NULL') . "</p>";
            echo "</div>";
            
        } catch (Exception $e) {
            echo "<div style='background: #f8d7da; padding: 20px; border-radius: 8px; margin-top: 20px;'>";
            echo "<h3>❌ Error:</h3>";
            echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
            echo "</div>";
        }
    } else {
        echo "<div style='background: #f8d7da; padding: 20px; border-radius: 8px; margin-top: 20px;'>";
        echo "<h3>❌ Validation Failed!</h3>";
        echo "<p>ID: $id, Name: $name, Price: $price</p>";
        echo "</div>";
    }
    
    exit;
}

echo "<h1>⚠️ No POST Data</h1>";
echo "<p>This page should be accessed via POST request from the edit form.</p>";
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
