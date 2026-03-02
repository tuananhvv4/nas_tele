<?php
/**
 * Check Webhook Errors
 * This will show exact PHP errors
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<pre>";
echo "=== CHECKING WEBHOOK ===\n\n";

try {
    require_once __DIR__ . '/config/db.php';
    echo "✓ Database loaded\n";
    
    require_once __DIR__ . '/bot/TelegramBot.php';
    echo "✓ TelegramBot loaded\n";
    
    require_once __DIR__ . '/includes/vietqr.php';
    echo "✓ VietQR loaded\n";
    
    require_once __DIR__ . '/bot/helpers/MessageFormatter.php';
    echo "✓ MessageFormatter loaded\n";
    
    require_once __DIR__ . '/bot/templates/WelcomeTemplate.php';
    echo "✓ WelcomeTemplate loaded\n";
    
    require_once __DIR__ . '/bot/templates/ProductTemplate.php';
    echo "✓ ProductTemplate loaded\n";
    
    require_once __DIR__ . '/bot/templates/OrderTemplate.php';
    echo "✓ OrderTemplate loaded\n";
    
    require_once __DIR__ . '/bot/templates/PaymentTemplate.php';
    echo "✓ PaymentTemplate loaded\n";
    
    require_once __DIR__ . '/includes/promo_helper.php';
    echo "✓ Promo helper loaded\n";
    
    require_once __DIR__ . '/includes/wallet_helper.php';
    echo "✓ Wallet helper loaded\n";
    
    echo "\n✅ All includes loaded successfully!\n";
    
    // Now try to parse webhook.php
    echo "\nChecking webhook.php syntax...\n";
    $webhookContent = file_get_contents(__DIR__ . '/bot/webhook.php');
    
    // Check for common issues
    if (strpos($webhookContent, 'function sendWithCleanup') === false) {
        echo "❌ sendWithCleanup function not found!\n";
    } else {
        echo "✓ sendWithCleanup function exists\n";
    }
    
    if (strpos($webhookContent, 'function handleStartCommand') === false) {
        echo "❌ handleStartCommand function not found!\n";
    } else {
        echo "✓ handleStartCommand function exists\n";
    }
    
    echo "\n=== TEST COMPLETE ===\n";
    echo "If all checks pass, the issue might be in webhook execution.\n";
    echo "Check error log at: logs/webhook_errors.log\n";
    
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString();
}

echo "</pre>";
