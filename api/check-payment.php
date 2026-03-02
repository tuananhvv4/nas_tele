<?php
/**
 * API endpoint to check payment status
 * Used for real-time polling when user views QR code
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/sepay.php';

try {
    // Get parameters
    $transactionCode = $_GET['transaction_code'] ?? '';
    $amount = $_GET['amount'] ?? 0;
    
    if (empty($transactionCode) || empty($amount)) {
        echo json_encode([
            'success' => false,
            'verified' => false,
            'message' => 'Missing parameters'
        ]);
        exit;
    }
    
    // Check payment
    $sepay = new SePay($pdo);
    $result = $sepay->checkPayment($transactionCode, $amount);
    
    echo json_encode($result);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'verified' => false,
        'message' => $e->getMessage()
    ]);
}
