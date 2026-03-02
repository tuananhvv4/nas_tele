<?php
/**
 * Test Cronjob - Simple version to debug 500 error
 */

// Enable error display
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "Starting cronjob test...\n";

try {
    echo "1. Loading config/db.php...\n";
    require_once __DIR__ . '/config/db.php';
    echo "   ✓ DB loaded\n";
    
    echo "2. Loading includes/sepay.php...\n";
    require_once __DIR__ . '/includes/sepay.php';
    echo "   ✓ SePay loaded\n";
    
    echo "3. Loading includes/telegram.php...\n";
    require_once __DIR__ . '/includes/telegram.php';
    echo "   ✓ Telegram loaded\n";
    
    echo "4. Testing database query...\n";
    $stmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE payment_status = 'pending'");
    $count = $stmt->fetchColumn();
    echo "   ✓ Found $count pending orders\n";
    
    echo "\n✅ All checks passed! Cronjob should work.\n";
    
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
}
?>
