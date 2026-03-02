<?php
/**
 * Update product display order via AJAX
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid request method']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$productIds = $data['product_ids'] ?? [];

if (empty($productIds) || !is_array($productIds)) {
    echo json_encode(['error' => 'Invalid product IDs']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Update display_order for each product
    $stmt = $pdo->prepare("UPDATE products SET display_order = ? WHERE id = ?");
    
    foreach ($productIds as $index => $productId) {
        $displayOrder = $index + 1; // 1-based ordering
        $stmt->execute([$displayOrder, $productId]);
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Product order updated successfully',
        'updated_count' => count($productIds)
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Update product order error: " . $e->getMessage());
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}
