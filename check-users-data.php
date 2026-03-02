<?php
/**
 * Quick test to check if users table has data
 */
require_once __DIR__ . '/config/db.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== USERS TABLE CHECK ===\n\n";

try {
    // Count users
    $count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    echo "Total users: $count\n\n";
    
    if ($count > 0) {
        // Get all users
        $users = $pdo->query("SELECT id, telegram_id, username, wallet_balance, created_at FROM users ORDER BY id")->fetchAll();
        
        echo "Users list:\n";
        echo str_repeat("-", 80) . "\n";
        foreach ($users as $user) {
            echo "ID: {$user['id']}\n";
            echo "Telegram ID: {$user['telegram_id']}\n";
            echo "Username: {$user['username']}\n";
            echo "Wallet: " . number_format($user['wallet_balance'], 0, ',', '.') . " VND\n";
            echo "Created: {$user['created_at']}\n";
            echo str_repeat("-", 80) . "\n";
        }
    } else {
        echo "No users found!\n";
    }
    
    // Check if orders table exists
    echo "\nChecking orders table...\n";
    try {
        $orderCount = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
        echo "Total orders: $orderCount\n";
    } catch (Exception $e) {
        echo "Orders table error: " . $e->getMessage() . "\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
