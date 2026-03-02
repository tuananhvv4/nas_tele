<?php
/**
 * Ultra simple test - just output HTML
 */
?>
<!DOCTYPE html>
<html>
<head>
    <title>Simple Test</title>
</head>
<body style="background: #1a1a2e; color: #fff; padding: 20px; font-family: Arial;">
    <h1>🔍 Simple Users Test</h1>
    
    <?php
    require_once __DIR__ . '/config/db.php';
    
    // Define formatVND if not already defined
    if (!function_exists('formatVND')) {
        function formatVND($amount) {
            return number_format($amount, 0, ',', '.') . ' VNĐ';
        }
    }
    
    echo "<p>✅ Database connected</p>";
    
    try {
        require_once __DIR__ . '/includes/wallet_helper.php';
        echo "<p>✅ wallet_helper.php loaded</p>";
    } catch (Exception $e) {
        echo "<p>❌ wallet_helper.php error: " . $e->getMessage() . "</p>";
    }
    
    try {
        $count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        echo "<p>✅ Users count: $count</p>";
        
        $users = $pdo->query("SELECT id, username, wallet_balance FROM users LIMIT 5")->fetchAll();
        echo "<p>✅ Query executed, got " . count($users) . " users</p>";
        
        if (count($users) > 0) {
            echo "<table border='1' style='border-collapse: collapse; margin-top: 20px;'>";
            echo "<tr><th style='padding: 10px;'>ID</th><th style='padding: 10px;'>Username</th><th style='padding: 10px;'>Balance</th></tr>";
            foreach ($users as $user) {
                echo "<tr>";
                echo "<td style='padding: 10px;'>{$user['id']}</td>";
                echo "<td style='padding: 10px;'>" . htmlspecialchars($user['username']) . "</td>";
                echo "<td style='padding: 10px;'>" . number_format($user['wallet_balance'], 0, ',', '.') . " VND</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
        
    } catch (Exception $e) {
        echo "<p>❌ Query error: " . $e->getMessage() . "</p>";
        echo "<pre>" . $e->getTraceAsString() . "</pre>";
    }
    
    echo "<hr>";
    echo "<h2>Testing formatVND function</h2>";
    
    try {
        $test = formatVND(100000);
        echo "<p>✅ formatVND(100000) = $test</p>";
    } catch (Exception $e) {
        echo "<p>❌ formatVND error: " . $e->getMessage() . "</p>";
    }
    
    echo "<hr>";
    echo "<h2>Check if users.php has errors</h2>";
    echo "<p>If users.php loads but shows nothing, there might be a fatal error.</p>";
    echo "<p>Check: <a href='users.php' style='color: #4CAF50;'>users.php</a></p>";
    ?>
</body>
</html>
