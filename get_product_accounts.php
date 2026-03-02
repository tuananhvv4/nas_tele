<?php
/**
 * Get product accounts API
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

header('Content-Type: application/json');

$productId = intval($_GET['product_id'] ?? 0);

if ($productId <= 0) {
    echo json_encode(['error' => 'Invalid product ID']);
    exit;
}

try {
    // Get all accounts for this product
    $stmt = $pdo->prepare("
        SELECT 
            pa.*,
            o.id as order_id,
            o.telegram_id,
            o.created_at as sold_at,
            u.username as buyer_username
        FROM product_accounts pa
        LEFT JOIN orders o ON pa.order_id = o.id
        LEFT JOIN users u ON o.user_id = u.id
        WHERE pa.product_id = ?
        ORDER BY pa.is_sold ASC, pa.created_at DESC
    ");
    
    $stmt->execute([$productId]);
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format accounts for display
    $formatted = [];
    foreach ($accounts as $acc) {
        // Parse account_data: "username|password" or "username password"
        $accountData = $acc['account_data'];
        $parts = [];
        
        if (strpos($accountData, '|') !== false) {
            $parts = explode('|', $accountData, 2);
        } elseif (strpos($accountData, ' ') !== false) {
            $parts = explode(' ', $accountData, 2);
        } else {
            $parts = [$accountData, ''];
        }
        
        $username = trim($parts[0] ?? '');
        $password = trim($parts[1] ?? '');
        
        $formatted[] = [
            'id' => $acc['id'],
            'username' => $username,
            'password' => $password,
            'account_data' => $accountData,
            'is_sold' => (bool)$acc['is_sold'],
            'order_id' => $acc['order_id'],
            'buyer_username' => $acc['buyer_username'],
            'sold_at' => $acc['sold_at'],
            'created_at' => $acc['created_at']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'accounts' => $formatted,
        'total' => count($formatted),
        'available' => count(array_filter($formatted, fn($a) => !$a['is_sold'])),
        'sold' => count(array_filter($formatted, fn($a) => $a['is_sold']))
    ]);
    
} catch (Exception $e) {
    error_log("Get product accounts error: " . $e->getMessage());
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}
