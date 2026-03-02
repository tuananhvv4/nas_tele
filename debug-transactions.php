<?php
/**
 * Debug transaction query
 */
require_once __DIR__ . '/config/db.php';

// Get user info
$userId = 3; // london_DCz from screenshot
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

echo "<h2>User Info (ID: $userId)</h2>";
echo "<pre>";
print_r($user);
echo "</pre>";

if ($user) {
    $telegramId = $user['telegram_id'];
    
    echo "<h2>Wallet Transactions</h2>";
    $stmt = $pdo->prepare("SELECT * FROM wallet_transactions WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$userId]);
    $walletTx = $stmt->fetchAll();
    echo "<pre>";
    print_r($walletTx);
    echo "</pre>";
    echo "Count: " . count($walletTx) . "<br><br>";
    
    echo "<h2>Orders (by telegram_id)</h2>";
    $stmt = $pdo->prepare("
        SELECT o.*, p.name as product_name 
        FROM orders o 
        LEFT JOIN products p ON o.product_id = p.id 
        WHERE o.telegram_id = ? 
        ORDER BY o.created_at DESC
    ");
    $stmt->execute([$telegramId]);
    $orders = $stmt->fetchAll();
    echo "<pre>";
    print_r($orders);
    echo "</pre>";
    echo "Count: " . count($orders) . "<br><br>";
    
    echo "<h2>Check if orders has quantity/total_price columns</h2>";
    $stmt = $pdo->query("SHOW COLUMNS FROM orders");
    $columns = $stmt->fetchAll();
    echo "<table border='1'><tr><th>Field</th><th>Type</th></tr>";
    foreach ($columns as $col) {
        echo "<tr><td>{$col['Field']}</td><td>{$col['Type']}</td></tr>";
    }
    echo "</table><br>";
    
    echo "<h2>Test Query (what API uses)</h2>";
    $stmt = $pdo->prepare("
        SELECT 
            'Ví' as type,
            amount,
            description,
            created_at
        FROM wallet_transactions
        WHERE user_id = ?
        
        UNION ALL
        
        SELECT 
            'Đơn hàng' as type,
            -COALESCE(total_price, price * quantity, price) as amount,
            CONCAT('Mua ', p.name, ' x', COALESCE(o.quantity, 1)) as description,
            o.created_at
        FROM orders o
        JOIN products p ON o.product_id = p.id
        WHERE o.telegram_id = ? AND o.status = 'completed'
        
        ORDER BY created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$userId, $telegramId]);
    $transactions = $stmt->fetchAll();
    echo "<pre>";
    print_r($transactions);
    echo "</pre>";
    echo "Count: " . count($transactions) . "<br><br>";
}
