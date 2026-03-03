<?php
/**
 * Cronjob Payment Checker
 * Runs for 60 seconds, checking every 3 seconds
 * Perfect for cronjob that runs every minute
 */

set_time_limit(70); // 70 seconds max
ini_set('max_execution_time', 70);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/sepay.php';
require_once __DIR__ . '/includes/telegram.php';

$logFile = __DIR__ . '/logs/cronjob-payment-check.log';

function logMessage($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

logMessage("🚀 Cronjob started - Will check for 55 seconds");

$startTime = time();
$checkCount = 0;
$paymentsProcessed = 0;

// Run for 55 seconds (leave 5s buffer for next cronjob)
while ((time() - $startTime) < 55) {
    try {
        $checkCount++;
        
        // Get pending orders
        $stmt = $pdo->query("
            SELECT * FROM orders 
            WHERE payment_status = 'pending' 
            AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ORDER BY created_at DESC
        ");
        
        $pendingOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Log every check
        if (!empty($pendingOrders)) {
            logMessage("Check #{$checkCount}: Found " . count($pendingOrders) . " pending orders");
            
            $sepay = new SePay($pdo);
            
            foreach ($pendingOrders as $order) {
                try {
                    // Check payment
                    $result = $sepay->checkPayment($order['transaction_code'], $order['total_price']);
                    
                    if ($result['success'] && $result['verified']) {
                        logMessage("✅ Payment verified for Order #{$order['id']}");
                        
                        // Assign account if needed
                        if (empty($order['account_id'])) {
                            $quantity = $order['quantity'] ?? 1;
                            $productId = $order['product_id'];
                            
                            $accountStmt = $pdo->prepare("
                                SELECT id FROM product_accounts 
                                WHERE product_id = ? AND is_sold = 0 
                                LIMIT ?
                            ");
                            $accountStmt->execute([$productId, $quantity]);
                            $accounts = $accountStmt->fetchAll(PDO::FETCH_COLUMN);
                            
                            if (count($accounts) >= $quantity) {
                                // Lưu tất cả account IDs vào order (chuỗi: "5,12,18")
                                $accountIdsStr = implode(',', $accounts);
                                $pdo->prepare("UPDATE orders SET account_id = ? WHERE id = ?")->execute([$accountIdsStr, $order['id']]);

                                // Đánh dấu sold + gán order_id cho từng product_account
                                $placeholders = implode(',', array_fill(0, count($accounts), '?'));
                                $updateStmt = $pdo->prepare("
                                    UPDATE product_accounts 
                                    SET is_sold = 1, sold_at = NOW(), order_id = ? 
                                    WHERE id IN ($placeholders)
                                ");
                                $updateStmt->execute(array_merge([$order['id']], $accounts));

                                logMessage("   → " . count($accounts) . " account(s) assigned [IDs: $accountIdsStr], order_id={$order['id']}");
                            }
                        }
                        
                        // Update order
                        $pdo->prepare("
                            UPDATE orders 
                            SET payment_status = 'completed', 
                                status = 'completed',
                                payment_verified_at = NOW(), 
                                payment_reference = ? 
                            WHERE id = ?
                        ")->execute([
                            $result['transaction']['id'] ?? 'SEPAY_' . time(),
                            $order['id']
                        ]);
                        
                        logMessage("   → Order updated to completed");
                        
                        // Get order with account data
                        $orderStmt = $pdo->prepare("
                            SELECT o.*, p.name as product_name, pa.account_data
                            FROM orders o
                            JOIN products p ON o.product_id = p.id
                            LEFT JOIN product_accounts pa ON o.account_id = pa.id
                            WHERE o.id = ?
                        ");
                        $orderStmt->execute([$order['id']]);
                        $orderData = $orderStmt->fetch(PDO::FETCH_ASSOC);
                        
                        // Send to user
                        if ($orderData && $orderData['telegram_id']) {
                            $telegram = new TelegramNotifier();
                            $telegram->sendAccountToUser($orderData);
                            logMessage("   → Account sent to Telegram user");
                        }
                        
                        $paymentsProcessed++;
                    }
                } catch (Exception $e) {
                    logMessage("❌ Error processing Order #{$order['id']}: " . $e->getMessage());
                }
            }
        } else {
            logMessage("Check #{$checkCount}: No pending orders");
        }
        
        // Sleep for 3 seconds before next check
        sleep(3);
        
    } catch (Exception $e) {
        logMessage("❌ Error: " . $e->getMessage());
        sleep(5);
    }
}

$duration = time() - $startTime;
logMessage("✅ Cronjob finished - Ran for {$duration}s, {$checkCount} checks, {$paymentsProcessed} payments processed");
?>
