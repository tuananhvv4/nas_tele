<?php
/**
 * SePay Webhook Endpoint
 * Receives POST requests from SePay when transactions occur
 */

// Disable output buffering
if (ob_get_level()) ob_end_clean();

// Set headers
header('Content-Type: application/json');

// Log file for debugging
$logFile = __DIR__ . '/../logs/sepay_webhook.log';

function logWebhook($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

try {
    // Get raw POST data
    $rawData = file_get_contents('php://input');
    logWebhook('Received webhook: ' . $rawData);
    
    // Parse JSON
    $data = json_decode($rawData, true);
    
    if (!$data) {
        logWebhook('Invalid JSON data');
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
        exit;
    }
    
    // Validate required fields
    if (!isset($data['id']) || !isset($data['transferAmount'])) {
        logWebhook('Missing required fields');
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }
    
    // Only process incoming transfers
    if (isset($data['transferType']) && $data['transferType'] !== 'in') {
        logWebhook('Ignoring outgoing transfer');
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Ignored outgoing transfer']);
        exit;
    }
    
    // Load database
    require_once __DIR__ . '/../config/db.php';
    require_once __DIR__ . '/../includes/sepay.php';
    
    $sepay = new SePay($pdo);
    
    // Process webhook
    $result = $sepay->processWebhook($data);
    
    logWebhook('Processing result: ' . json_encode($result));
    
    // Return success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => $result['message'] ?? 'Webhook processed'
    ]);
    
} catch (Exception $e) {
    logWebhook('Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
