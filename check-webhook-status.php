<?php
/**
 * Test if webhook is receiving updates
 */
require_once __DIR__ . '/config/db.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== WEBHOOK TEST ===\n\n";

// Get bot token
$stmt = $pdo->query("SELECT token FROM bots WHERE id = 1");
$bot = $stmt->fetch();

if (!$bot) {
    die("ERROR: No bot found in database!\n");
}

$token = $bot['token'];
echo "Bot token: " . substr($token, 0, 10) . "...\n\n";

// Get webhook info
$url = "https://api.telegram.org/bot$token/getWebhookInfo";
$response = file_get_contents($url);
$data = json_decode($response, true);

if ($data['ok']) {
    $info = $data['result'];
    echo "Webhook URL: " . ($info['url'] ?? 'NOT SET') . "\n";
    echo "Pending updates: " . ($info['pending_update_count'] ?? 0) . "\n";
    echo "Last error: " . ($info['last_error_message'] ?? 'None') . "\n";
    echo "Last error date: " . ($info['last_error_date'] ? date('Y-m-d H:i:s', $info['last_error_date']) : 'N/A') . "\n";
    echo "Max connections: " . ($info['max_connections'] ?? 'N/A') . "\n";
    
    if (isset($info['last_synchronization_error_date'])) {
        echo "Last sync error: " . date('Y-m-d H:i:s', $info['last_synchronization_error_date']) . "\n";
    }
} else {
    echo "ERROR: " . ($data['description'] ?? 'Unknown error') . "\n";
}

echo "\n=== RECENT ERROR LOG ===\n";
$logFile = __DIR__ . '/bot/error.log';
if (file_exists($logFile)) {
    $lines = file($logFile);
    $recent = array_slice($lines, -20); // Last 20 lines
    echo implode('', $recent);
} else {
    echo "No error log file found\n";
}
