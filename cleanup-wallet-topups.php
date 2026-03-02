<?php
/**
 * Cleanup Invalid Wallet Topup Requests
 * Remove pending requests with invalid data (amount = 0)
 */

require_once __DIR__ . '/config/db.php';

echo "=== CLEANUP INVALID TOPUP REQUESTS ===\n\n";

// Find invalid pending requests
echo "1. Finding invalid pending requests (amount = 0)...\n";
$stmt = $pdo->query("
    SELECT * FROM wallet_topup_requests 
    WHERE payment_status = 'pending' 
    AND amount = 0
    ORDER BY created_at DESC
");
$invalidRequests = $stmt->fetchAll();

if (empty($invalidRequests)) {
    echo "   ✅ No invalid requests found\n\n";
} else {
    echo "   Found " . count($invalidRequests) . " invalid requests:\n\n";
    
    foreach ($invalidRequests as $req) {
        echo "   - ID: {$req['id']}, User: {$req['user_id']}, Code: {$req['transaction_code']}, Created: {$req['created_at']}\n";
    }
    
    echo "\n2. Deleting invalid requests...\n";
    $stmt = $pdo->prepare("
        DELETE FROM wallet_topup_requests 
        WHERE payment_status = 'pending' 
        AND amount = 0
    ");
    $stmt->execute();
    
    echo "   ✅ Deleted " . $stmt->rowCount() . " invalid requests\n\n";
}

// Find old expired requests (> 24 hours)
echo "3. Finding expired requests (> 24 hours old)...\n";
$stmt = $pdo->query("
    SELECT * FROM wallet_topup_requests 
    WHERE payment_status = 'pending' 
    AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ORDER BY created_at DESC
");
$expiredRequests = $stmt->fetchAll();

if (empty($expiredRequests)) {
    echo "   ✅ No expired requests found\n\n";
} else {
    echo "   Found " . count($expiredRequests) . " expired requests:\n\n";
    
    foreach ($expiredRequests as $req) {
        echo "   - ID: {$req['id']}, Amount: " . number_format($req['amount'], 0, ',', '.') . " VNĐ, Code: {$req['transaction_code']}, Created: {$req['created_at']}\n";
    }
    
    echo "\n   Do you want to mark them as 'expired'? (y/n): ";
    // For web execution, auto-mark as expired
    echo "yes (auto)\n";
    
    $stmt = $pdo->prepare("
        UPDATE wallet_topup_requests 
        SET payment_status = 'expired'
        WHERE payment_status = 'pending' 
        AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $stmt->execute();
    
    echo "   ✅ Marked " . $stmt->rowCount() . " requests as expired\n\n";
}

echo "=== CLEANUP COMPLETE ===\n";
