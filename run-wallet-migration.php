<?php
/**
 * Run Wallet Top-up Migration
 */

require_once __DIR__ . '/config/db.php';

header('Content-Type: text/plain');

echo "=== WALLET TOP-UP MIGRATION ===\n\n";

try {
    // Check if wallet_topup_requests table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'wallet_topup_requests'");
    $tableExists = $stmt->fetch();
    
    if (!$tableExists) {
        echo "Creating wallet_topup_requests table...\n";
        $pdo->exec("
            CREATE TABLE wallet_topup_requests (
                id INT PRIMARY KEY AUTO_INCREMENT,
                user_id INT NOT NULL,
                telegram_id BIGINT NOT NULL,
                amount DECIMAL(15,2) NOT NULL,
                transaction_code VARCHAR(50) NOT NULL UNIQUE,
                qr_code_url TEXT,
                status ENUM('pending', 'completed', 'cancelled', 'expired') DEFAULT 'pending',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                completed_at TIMESTAMP NULL,
                
                INDEX idx_telegram_id (telegram_id),
                INDEX idx_transaction_code (transaction_code),
                INDEX idx_status (status),
                INDEX idx_created_at (created_at),
                
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "✓ Table created\n\n";
    } else {
        echo "✓ Table wallet_topup_requests already exists\n\n";
    }
    
    // Check if topup_id column exists in payment_check_queue
    $stmt = $pdo->query("SHOW COLUMNS FROM payment_check_queue LIKE 'topup_id'");
    $columnExists = $stmt->fetch();
    
    if (!$columnExists) {
        echo "Adding topup_id column to payment_check_queue...\n";
        $pdo->exec("
            ALTER TABLE payment_check_queue 
            ADD COLUMN topup_id INT NULL AFTER order_id,
            ADD INDEX idx_topup_id (topup_id)
        ");
        echo "✓ Column added\n\n";
    } else {
        echo "✓ Column topup_id already exists\n\n";
    }
    
    // Check if foreign key exists
    echo "Checking foreign key...\n";
    try {
        $pdo->exec("
            ALTER TABLE payment_check_queue
            ADD CONSTRAINT fk_topup_id 
            FOREIGN KEY (topup_id) REFERENCES wallet_topup_requests(id) ON DELETE CASCADE
        ");
        echo "✓ Foreign key added\n\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'already exists') !== false || strpos($e->getMessage(), 'Duplicate') !== false) {
            echo "✓ Foreign key already exists\n\n";
        } else {
            throw $e;
        }
    }
    
    // Make order_id nullable
    echo "Making order_id nullable...\n";
    try {
        $pdo->exec("ALTER TABLE payment_check_queue MODIFY COLUMN order_id INT NULL");
        echo "✓ order_id is now nullable\n\n";
    } catch (PDOException $e) {
        echo "⚠ order_id modification: " . $e->getMessage() . "\n\n";
    }
    
    echo "✅ MIGRATION COMPLETE!\n\n";
    
    // Verify tables
    echo "Verifying tables...\n";
    $tables = $pdo->query("SHOW TABLES LIKE 'wallet_topup_requests'")->fetchAll();
    if (count($tables) > 0) {
        echo "✓ wallet_topup_requests table exists\n";
        
        // Show structure
        $columns = $pdo->query("DESCRIBE wallet_topup_requests")->fetchAll();
        echo "\nTable structure:\n";
        foreach ($columns as $col) {
            echo "  - {$col['Field']} ({$col['Type']})\n";
        }
    } else {
        echo "✗ wallet_topup_requests table NOT found!\n";
    }
    
    echo "\n✅ All done! You can now use /naptien command.\n";
    
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "\nFile: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
