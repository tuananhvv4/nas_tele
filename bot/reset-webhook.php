<?php
/**
 * Reset Telegram Webhook
 * This will re-register the webhook URL with Telegram
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/db.php';

echo "<h2>🔄 Reset Telegram Webhook</h2>";
echo "<hr>";

// Get bot token
$stmt = $pdo->query("SELECT bot_token FROM bots WHERE id = 1");
$bot = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$bot || empty($bot['bot_token'])) {
    die("❌ Bot token not configured!");
}

$botToken = $bot['bot_token'];
$webhookUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/bot/webhook.php';

echo "<h3>Step 1: Delete old webhook</h3>";
$deleteUrl = "https://api.telegram.org/bot{$botToken}/deleteWebhook";
$response = file_get_contents($deleteUrl);
$data = json_decode($response, true);

if ($data['ok']) {
    echo "✅ Old webhook deleted<br>";
} else {
    echo "❌ Error: " . $data['description'] . "<br>";
}

echo "<h3>Step 2: Set new webhook</h3>";
$setUrl = "https://api.telegram.org/bot{$botToken}/setWebhook?url=" . urlencode($webhookUrl);
$response = file_get_contents($setUrl);
$data = json_decode($response, true);

if ($data['ok']) {
    echo "✅ Webhook set successfully!<br>";
    echo "URL: <code>$webhookUrl</code><br>";
} else {
    echo "❌ Error: " . $data['description'] . "<br>";
}

echo "<h3>Step 3: Verify webhook</h3>";
$infoUrl = "https://api.telegram.org/bot{$botToken}/getWebhookInfo";
$response = file_get_contents($infoUrl);
$data = json_decode($response, true);

if ($data['ok']) {
    $info = $data['result'];
    echo "URL: " . ($info['url'] ?: '❌ Not set') . "<br>";
    echo "Pending updates: " . ($info['pending_update_count'] ?? 0) . "<br>";
    
    if (!empty($info['last_error_message'])) {
        echo "⚠️ Last error: " . $info['last_error_message'] . "<br>";
        echo "Error date: " . date('Y-m-d H:i:s', $info['last_error_date']) . "<br>";
    } else {
        echo "✅ No errors!<br>";
    }
}

echo "<hr>";
echo "<h3>✅ Done!</h3>";
echo "<p>Try sending /start to your bot now!</p>";
?>
