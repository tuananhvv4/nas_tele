<?php
/**
 * Quick Bot Diagnostic
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/db.php';

echo "<h2>🤖 Bot Diagnostic</h2>";
echo "<hr>";

// 1. Check bot token
echo "<h3>1. Bot Token</h3>";
$stmt = $pdo->query("SELECT bot_token FROM bots WHERE id = 1");
$bot = $stmt->fetch(PDO::FETCH_ASSOC);
if ($bot && !empty($bot['bot_token'])) {
    echo "✅ Bot token configured: " . substr($bot['bot_token'], 0, 10) . "...<br>";
} else {
    echo "❌ Bot token NOT configured!<br>";
}

// 2. Check webhook.php exists
echo "<h3>2. Webhook File</h3>";
if (file_exists('webhook.php')) {
    echo "✅ webhook.php exists<br>";
    echo "Size: " . filesize('webhook.php') . " bytes<br>";
} else {
    echo "❌ webhook.php NOT FOUND!<br>";
}

// 3. Check webhook URL
echo "<h3>3. Webhook URL</h3>";
$webhookUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/bot/webhook.php';
echo "Expected URL: <code>$webhookUrl</code><br>";

// 4. Test webhook with Telegram
if ($bot && !empty($bot['bot_token'])) {
    $apiUrl = "https://api.telegram.org/bot{$bot['bot_token']}/getWebhookInfo";
    $response = @file_get_contents($apiUrl);
    
    if ($response) {
        $data = json_decode($response, true);
        if ($data['ok']) {
            $info = $data['result'];
            echo "<h3>4. Telegram Webhook Info</h3>";
            echo "URL: " . ($info['url'] ?: '❌ Not set') . "<br>";
            echo "Pending updates: " . ($info['pending_update_count'] ?? 0) . "<br>";
            if (!empty($info['last_error_message'])) {
                echo "❌ Last error: " . $info['last_error_message'] . "<br>";
                echo "Error date: " . date('Y-m-d H:i:s', $info['last_error_date']) . "<br>";
            } else {
                echo "✅ No errors<br>";
            }
        }
    }
}

echo "<hr>";
echo "<h3>✅ Diagnostic Complete</h3>";
echo "<p>If webhook URL is not set or has errors, run: <code>setup-webhook.php</code></p>";
?>
