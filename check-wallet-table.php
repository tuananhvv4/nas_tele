<?php
/**
 * Check Wallet Top-up Table Status
 */

require_once __DIR__ . '/config/db.php';

header('Content-Type: text/plain');

echo "=== WALLET TOP-UP TABLE CHECK ===\n\n";

try {
    // Check if table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'wallet_topup_requests'");
    $tableExists = $stmt->fetch();
    
    if (!$tableExists) {
        echo "❌ Table 'wallet_topup_requests' DOES NOT EXIST!\n";
        echo "\nPlease run: https://mrmista.online/run-wallet-migration.php\n";
        exit;
    }
    
    echo "✓ Table 'wallet_topup_requests' exists\n\n";
    
    // Check columns
    echo "Checking columns...\n";
    $columns = $pdo->query("DESCRIBE wallet_topup_requests")->fetchAll();
    
    $requiredColumns = ['id', 'user_id', 'telegram_id', 'amount', 'transaction_code', 'qr_code_url', 'status', 'created_at', 'completed_at'];
    $existingColumns = array_column($columns, 'Field');
    
    $missingColumns = array_diff($requiredColumns, $existingColumns);
    
    if (empty($missingColumns)) {
        echo "✓ All required columns exist\n\n";
        
        echo "Table structure:\n";
        foreach ($columns as $col) {
            echo "  - {$col['Field']} ({$col['Type']})\n";
        }
        
        echo "\n✅ Table is ready to use!\n";
    } else {
        echo "❌ Missing columns: " . implode(', ', $missingColumns) . "\n";
        echo "\nPlease run: https://mrmista.online/run-wallet-migration.php\n";
    }
    
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
}
