<?php
/**
 * Verify Payment Handlers - Check if files are updated correctly
 */

header('Content-Type: text/plain; charset=utf-8');

echo "=== PAYMENT HANDLERS VERIFICATION ===\n\n";

// Check if payment_handlers.php exists
$paymentHandlersFile = __DIR__ . '/bot/payment_handlers.php';
if (!file_exists($paymentHandlersFile)) {
    echo "❌ payment_handlers.php NOT FOUND!\n";
    exit;
}

echo "✓ payment_handlers.php exists\n\n";

// Read file content
$content = file_get_contents($paymentHandlersFile);

// Check for old method name
$oldMethodCount = substr_count($content, 'editMessageText');
if ($oldMethodCount > 0) {
    echo "❌ FOUND $oldMethodCount instances of 'editMessageText' (should be 0)\n";
    echo "   File needs to be re-uploaded!\n\n";
} else {
    echo "✓ No 'editMessageText' found (correct)\n\n";
}

// Check for new method name
$newMethodCount = substr_count($content, 'editMessage');
echo "✓ Found $newMethodCount instances of 'editMessage'\n\n";

// Check webhook.php
echo "--- Checking webhook.php ---\n";
$webhookFile = __DIR__ . '/bot/webhook.php';
$webhookContent = file_get_contents($webhookFile);
$webhookOldCount = substr_count($webhookContent, 'editMessageText');

if ($webhookOldCount > 0) {
    echo "❌ FOUND $webhookOldCount instances of 'editMessageText' in webhook.php\n";
    echo "   File needs to be re-uploaded!\n\n";
} else {
    echo "✓ No 'editMessageText' in webhook.php\n\n";
}

// Check if payment_handlers is included
if (strpos($webhookContent, "payment_handlers.php") !== false) {
    echo "✓ payment_handlers.php is included in webhook.php\n\n";
} else {
    echo "❌ payment_handlers.php NOT included in webhook.php\n\n";
}

// Summary
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "SUMMARY:\n";
if ($oldMethodCount == 0 && $webhookOldCount == 0) {
    echo "✅ ALL FILES ARE CORRECT!\n";
    echo "   You can test wallet payment now.\n";
} else {
    echo "❌ FILES NEED TO BE RE-UPLOADED!\n";
    echo "   Please upload:\n";
    if ($oldMethodCount > 0) {
        echo "   - bot/payment_handlers.php\n";
    }
    if ($webhookOldCount > 0) {
        echo "   - bot/webhook.php\n";
    }
}

echo "\n";
echo "Last modified times:\n";
echo "payment_handlers.php: " . date('Y-m-d H:i:s', filemtime($paymentHandlersFile)) . "\n";
echo "webhook.php: " . date('Y-m-d H:i:s', filemtime($webhookFile)) . "\n";
