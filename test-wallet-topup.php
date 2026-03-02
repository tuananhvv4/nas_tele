<?php
/**
 * Test Wallet Topup System
 * Run this to check if wallet topup is working
 */

require_once __DIR__ . '/config/db.php';

echo "=== WALLET TOPUP SYSTEM TEST ===\n\n";

// 1. Check if wallet_topup_requests table exists
echo "1. Checking wallet_topup_requests table...\n";
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'wallet_topup_requests'");
    if ($stmt->rowCount() > 0) {
        echo "   ✅ Table exists\n\n";
        
        // Show columns
        $columns = $pdo->query("DESCRIBE wallet_topup_requests")->fetchAll();
        echo "   Columns:\n";
        foreach ($columns as $col) {
            echo "   - {$col['Field']} ({$col['Type']})\n";
        }
        echo "\n";
    } else {
        echo "   ❌ Table NOT found!\n";
        echo "   Run: php run-wallet-migration.php\n\n";
        exit;
    }
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n\n";
    exit;
}

// 2. Check pending topup requests
echo "2. Checking pending topup requests...\n";
$stmt = $pdo->query("
    SELECT wtr.*, u.username 
    FROM wallet_topup_requests wtr
    JOIN users u ON wtr.user_id = u.id
    WHERE wtr.payment_status = 'pending'
    ORDER BY wtr.created_at DESC
    LIMIT 5
");
$pending = $stmt->fetchAll();

if (empty($pending)) {
    echo "   ℹ️  No pending topup requests\n\n";
} else {
    echo "   Found " . count($pending) . " pending requests:\n\n";
    foreach ($pending as $topup) {
        echo "   ID: {$topup['id']}\n";
        echo "   User: {$topup['username']} (Telegram: {$topup['telegram_id']})\n";
        echo "   Amount: " . number_format($topup['amount'], 0, ',', '.') . " VNĐ\n";
        echo "   Transaction Code: {$topup['transaction_code']}\n";
        echo "   Created: {$topup['created_at']}\n";
        echo "   ---\n";
    }
    echo "\n";
}

// 3. Check SePay settings
echo "3. Checking SePay settings...\n";
try {
    $stmt = $pdo->query("SELECT * FROM sepay_settings WHERE id = 1");
    $sepaySettings = $stmt->fetch();
    
    if ($sepaySettings) {
        echo "   ✅ SePay configured\n";
        echo "   API Key: " . (strlen($sepaySettings['api_key']) > 0 ? "***" . substr($sepaySettings['api_key'], -4) : "NOT SET") . "\n";
        echo "   Account Number: {$sepaySettings['account_number']}\n\n";
    } else {
        echo "   ❌ SePay NOT configured\n\n";
    }
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n\n";
}

// 4. Check recent webhook logs
echo "4. Checking recent SePay webhook logs...\n";
try {
    $stmt = $pdo->query("
        SELECT * FROM sepay_webhook_logs 
        ORDER BY created_at DESC 
        LIMIT 3
    ");
    $logs = $stmt->fetchAll();
    
    if (empty($logs)) {
        echo "   ℹ️  No webhook logs found\n";
        echo "   This means SePay webhook hasn't received any transactions yet\n\n";
    } else {
        echo "   Found " . count($logs) . " recent webhooks:\n\n";
        foreach ($logs as $log) {
            echo "   ID: {$log['sepay_transaction_id']}\n";
            echo "   Amount: " . number_format($log['amount'], 0, ',', '.') . " VNĐ\n";
            echo "   Content: {$log['content']}\n";
            echo "   Status: {$log['status']}\n";
            echo "   Created: {$log['created_at']}\n";
            echo "   ---\n";
        }
        echo "\n";
    }
} catch (Exception $e) {
    echo "   ℹ️  Webhook logs table not found (this is OK)\n\n";
}

// 5. Test SePay connection
echo "5. Testing SePay API connection...\n";
try {
    require_once __DIR__ . '/includes/sepay.php';
    $sepay = new SePay($pdo);
    $result = $sepay->testConnection();
    
    if ($result['success']) {
        echo "   ✅ " . $result['message'] . "\n\n";
    } else {
        echo "   ❌ " . $result['message'] . "\n\n";
    }
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n\n";
}

// 6. Cronjob instructions
echo "6. Cronjob Setup Instructions:\n";
echo "   Add this to cPanel Cron Jobs:\n";
echo "   */2 * * * * /usr/bin/php " . __DIR__ . "/cron-check-wallet-topups.php\n";
echo "   (Runs every 2 minutes)\n\n";

echo "=== TEST COMPLETE ===\n";
