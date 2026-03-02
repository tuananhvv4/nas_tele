<?php
/**
 * Set Telegram Webhook
 * Run this once to configure webhook
 */

$botToken = '8590274330:AAEnEaJOpKb8fDZK0QY0OXBOBl31hmTWLFU';
$webhookUrl = 'https://mrmista.online/bot/webhook.php';

echo "Setting webhook...\n\n";

// Set webhook
$url = "https://api.telegram.org/bot{$botToken}/setWebhook?url=" . urlencode($webhookUrl);
$response = file_get_contents($url);
$result = json_decode($response, true);

echo "Response:\n";
print_r($result);

if ($result['ok']) {
    echo "\n✅ Webhook set successfully!\n";
    echo "URL: $webhookUrl\n";
} else {
    echo "\n❌ Failed to set webhook\n";
    echo "Error: " . ($result['description'] ?? 'Unknown error') . "\n";
}

// Get webhook info
echo "\n\nGetting webhook info...\n";
$infoUrl = "https://api.telegram.org/bot{$botToken}/getWebhookInfo";
$infoResponse = file_get_contents($infoUrl);
$info = json_decode($infoResponse, true);

echo "\nWebhook Info:\n";
print_r($info['result']);

echo "\n\nDone! Now test /start in your bot.\n";
