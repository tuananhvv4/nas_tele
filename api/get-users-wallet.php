<?php
// api/get-users-wallet.php
// Endpoint trả về danh sách user với telegram_id và wallet_balance
header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';

try {
    $pdo = getDb();
    $stmt = $pdo->query('SELECT telegram_id, wallet_balance, username FROM users');
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $users
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi: ' . $e->getMessage()
    ]);
}
