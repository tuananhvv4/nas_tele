<?php
/**
 * Products API
 * Returns list of active products with stock count
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

try {
    // Get active products with stock count
    $stmt = $pdo->query("
        SELECT 
            p.id,
            p.name,
            p.description,
            p.price,
            COUNT(CASE WHEN pa.is_sold = 0 THEN 1 END) as stock
        FROM products p
        LEFT JOIN product_accounts pa ON p.id = pa.product_id
        WHERE p.status = 'active'
        GROUP BY p.id
        ORDER BY p.name ASC
    ");

    $products = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'products' => $products
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch products'
    ]);
}
