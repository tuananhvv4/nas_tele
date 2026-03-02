<?php
/**
 * Get user transactions API
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: application/json');

$userId = intval($_GET['user_id'] ?? 0);

if ($userId <= 0) {
    echo json_encode(['error' => 'Invalid user ID']);
    exit;
}

try {
    // First, get the telegram_id for this user
    $stmt = $pdo->prepare("SELECT telegram_id FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo json_encode(['error' => 'User not found']);
        exit;
    }
    
    $telegramId = $user['telegram_id'];
    
    // Get wallet transactions
    $stmt = $pdo->prepare("
        SELECT 
            'Ví' as type,
            amount,
            CAST(description AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci as description,
            created_at
        FROM wallet_transactions
        WHERE user_id = ?
        
        UNION ALL
        
        SELECT 
            'Đơn hàng' as type,
            -total_price as amount,
            CAST(CONCAT('Mua ', p.name, ' x', o.quantity) AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci as description,
            o.created_at
        FROM orders o
        JOIN products p ON o.product_id = p.id
        WHERE o.telegram_id = ? AND o.payment_status = 'completed'
        
        ORDER BY created_at DESC
        LIMIT 50
    ");
    
    $stmt->execute([$userId, $telegramId]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("Transactions for user $userId (telegram $telegramId): " . count($transactions));
    
    echo json_encode([
        'success' => true,
        'transactions' => $transactions
    ]);
    
} catch (Exception $e) {
    error_log("Get transactions error: " . $e->getMessage());
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}
