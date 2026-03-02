<?php
/**
 * Promo Code Helper Functions
 * Validation and application logic for discount codes
 */

/**
 * Validate promo code for a specific user and product
 * 
 * @param string $code Promo code to validate
 * @param int $userId User ID
 * @param int $productId Product ID
 * @param PDO $pdo Database connection
 * @return array ['valid' => bool, 'message' => string, 'promo' => array|null, 'discount_percent' => int]
 */
function validatePromoCode($code, $userId, $productId, $pdo) {
    $code = strtoupper(trim($code));
    
    // 1. Check if code exists and is active
    $stmt = $pdo->prepare("
        SELECT * FROM promo_codes 
        WHERE code = ? 
        AND status = 'active'
        AND (expires_at IS NULL OR expires_at > NOW())
    ");
    $stmt->execute([$code]);
    $promo = $stmt->fetch();
    
    if (!$promo) {
        return [
            'valid' => false, 
            'message' => '❌ Mã không hợp lệ hoặc đã hết hạn!',
            'promo' => null,
            'discount_percent' => 0
        ];
    }
    
    // 2. Check usage limit
    if ($promo['current_uses'] >= $promo['max_uses']) {
        return [
            'valid' => false, 
            'message' => '❌ Mã đã hết lượt sử dụng!',
            'promo' => null,
            'discount_percent' => 0
        ];
    }
    
    // 3. Check if user already used this code
    $stmt = $pdo->prepare("
        SELECT id FROM promo_code_usage 
        WHERE promo_code_id = ? AND user_id = ?
    ");
    $stmt->execute([$promo['id'], $userId]);
    if ($stmt->fetch()) {
        return [
            'valid' => false, 
            'message' => '❌ Bạn đã sử dụng mã này rồi!',
            'promo' => null,
            'discount_percent' => 0
        ];
    }
    
    // 4. Check product restriction
    if ($promo['product_id'] && $promo['product_id'] != $productId) {
        // Get product name for better error message
        $stmt = $pdo->prepare("SELECT name FROM products WHERE id = ?");
        $stmt->execute([$promo['product_id']]);
        $allowedProduct = $stmt->fetch();
        
        return [
            'valid' => false, 
            'message' => '❌ Mã chỉ áp dụng cho: ' . ($allowedProduct['name'] ?? 'sản phẩm khác'),
            'promo' => null,
            'discount_percent' => 0
        ];
    }
    
    // All checks passed!
    return [
        'valid' => true,
        'message' => '✅ Mã hợp lệ!',
        'promo' => $promo,
        'discount_percent' => $promo['discount_percent']
    ];
}

/**
 * Apply promo code to order and track usage
 * 
 * @param int $promoId Promo code ID
 * @param int $userId User ID
 * @param int $telegramId Telegram chat ID
 * @param int $orderId Order ID
 * @param float $discountAmount Discount amount in VND
 * @param PDO $pdo Database connection
 * @return bool Success
 */
function applyPromoCode($promoId, $userId, $telegramId, $orderId, $discountAmount, $pdo) {
    try {
        // Record usage
        $stmt = $pdo->prepare("
            INSERT INTO promo_code_usage (promo_code_id, user_id, telegram_id, order_id, discount_amount) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$promoId, $userId, $telegramId, $orderId, $discountAmount]);
        
        // Increment usage count
        $stmt = $pdo->prepare("
            UPDATE promo_codes 
            SET current_uses = current_uses + 1 
            WHERE id = ?
        ");
        $stmt->execute([$promoId]);
        
        return true;
    } catch (Exception $e) {
        error_log("Failed to apply promo code: " . $e->getMessage());
        return false;
    }
}

/**
 * Calculate discount amount
 * 
 * @param float $totalPrice Total price before discount
 * @param int $discountPercent Discount percentage (1-100)
 * @return float Discount amount
 */
function calculateDiscount($totalPrice, $discountPercent) {
    return round($totalPrice * ($discountPercent / 100), 2);
}

/**
 * Get promo code info by code string
 * 
 * @param string $code Promo code
 * @param PDO $pdo Database connection
 * @return array|null Promo code data or null
 */
function getPromoCodeByCode($code, $pdo) {
    $code = strtoupper(trim($code));
    $stmt = $pdo->prepare("
        SELECT pc.*, p.name as product_name
        FROM promo_codes pc
        LEFT JOIN products p ON pc.product_id = p.id
        WHERE pc.code = ?
    ");
    $stmt->execute([$code]);
    return $stmt->fetch();
}
?>
