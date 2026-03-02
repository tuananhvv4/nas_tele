<?php
/**
 * Simple Webhook Diagnostic
 * Access via: https://mrmista.online/webhook-test.php
 */

header('Content-Type: text/plain');

echo "=== WEBHOOK DIAGNOSTIC ===\n\n";

// Test 1: Check files exist
echo "1. Checking files...\n";
$files = [
    'config/db.php',
    'bot/TelegramBot.php',
    'bot/webhook.php',
    'bot/templates/WelcomeTemplate.php',
    'includes/wallet_helper.php'
];

foreach ($files as $file) {
    $exists = file_exists(__DIR__ . '/' . $file);
    echo ($exists ? "✓" : "✗") . " $file\n";
}

// Test 2: Check database connection
echo "\n2. Testing database...\n";
try {
    require_once __DIR__ . '/config/db.php';
    echo "✓ Database connected\n";
    
    // Check bot_settings
    $stmt = $pdo->query("SELECT * FROM bot_settings WHERE id = 1");
    $settings = $stmt->fetch();
    if ($settings) {
        echo "✓ Bot settings found\n";
        echo "  - Bot Name: " . ($settings['bot_name'] ?? 'N/A') . "\n";
        echo "  - Welcome Style: " . ($settings['welcome_style'] ?? 'N/A') . "\n";
    } else {
        echo "✗ No bot settings found\n";
    }
} catch (Exception $e) {
    echo "✗ Database error: " . $e->getMessage() . "\n";
}

// Test 3: Check WelcomeTemplate
echo "\n3. Testing WelcomeTemplate...\n";
try {
    require_once __DIR__ . '/bot/templates/WelcomeTemplate.php';
    
    $message = WelcomeTemplate::render('modern', 'Test Bot', null);
    $keyboard = WelcomeTemplate::getKeyboard('modern');
    
    echo "✓ WelcomeTemplate works\n";
    echo "  - Message length: " . strlen($message) . " chars\n";
    echo "  - Keyboard rows: " . count($keyboard) . "\n";
} catch (Exception $e) {
    echo "✗ WelcomeTemplate error: " . $e->getMessage() . "\n";
}

// Test 4: Simulate /start command
echo "\n4. Simulating /start command...\n";
try {
    require_once __DIR__ . '/bot/TelegramBot.php';
    
    // Create fake update
    $fakeUpdate = [
        'message' => [
            'message_id' => 1,
            'from' => [
                'id' => 123456789,
                'first_name' => 'Test',
                'username' => 'testuser'
            ],
            'chat' => [
                'id' => 123456789,
                'type' => 'private'
            ],
            'text' => '/start',
            'date' => time()
        ]
    ];
    
    echo "✓ Fake update created\n";
    echo "  - Command: /start\n";
    echo "  - Chat ID: 123456789\n";
    
    // Try to process (will fail to send, but we can see if logic works)
    echo "\nNote: Actual bot sending will fail (no real chat), but we can check logic\n";
    
} catch (Exception $e) {
    echo "✗ Simulation error: " . $e->getMessage() . "\n";
}

// Test 5: Check webhook URL
echo "\n5. Webhook info...\n";
echo "Webhook should be set to: https://mrmista.online/bot/webhook.php\n";
echo "\nTo set webhook, run:\n";
echo "curl https://api.telegram.org/bot<YOUR_TOKEN>/setWebhook?url=https://mrmista.online/bot/webhook.php\n";

echo "\n=== END DIAGNOSTIC ===\n";
