<?php
/**
 * Buy API
 * Processes account purchase
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

// Validate input
if (!isset($_POST['product_id']) || !isset($_POST['telegram_id'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Missing required parameters'
    ]);
    exit;
}

$productId = (int)$_POST['product_id'];
$telegramId = (int)$_POST['telegram_id'];

try {
    // Start transaction
    $pdo->beginTransaction();

    // Get product details
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND status = 'active'");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();

    if (!$product) {
        throw new Exception('Product not found or inactive');
    }

    // Get available account (with row lock to prevent race condition)
    $stmt = $pdo->prepare("
        SELECT * FROM product_accounts 
        WHERE product_id = ? AND is_sold = 0 
        LIMIT 1 
        FOR UPDATE
    ");
    $stmt->execute([$productId]);
    $account = $stmt->fetch();

    if (!$account) {
        throw new Exception('No accounts available for this product');
    }

    // Mark account as sold
    $stmt = $pdo->prepare("
        UPDATE product_accounts 
        SET is_sold = 1, sold_at = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([$account['id']]);

    // Get user_id if exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE telegram_id = ?");
    $stmt->execute([$telegramId]);
    $user = $stmt->fetch();
    $userId = $user ? $user['id'] : null;

    // Create order
    $stmt = $pdo->prepare("
        INSERT INTO orders (user_id, telegram_id, product_id, account_id, price) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $userId,
        $telegramId,
        $productId,
        $account['id'],
        $product['price']
    ]);

    // Commit transaction
    $pdo->commit();

    // Return success with account data
    echo json_encode([
        'success' => true,
        'message' => 'Purchase successful',
        'account' => $account['account_data'],
        'product' => [
            'name' => $product['name'],
            'price' => $product['price']
        ]
    ]);

} catch (Exception $e) {
    // Rollback on error
    $pdo->rollBack();
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
