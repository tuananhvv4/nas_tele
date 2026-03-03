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

require_once __DIR__ . '/libs/helper.php';
include_once(__DIR__ . '/vendor/autoload.php');
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$acbInfo = [
    'account_username' => $_ENV['acb_username'],
    'account_password' => $_ENV['acb_password'],
    'account_number' => $_ENV['acb_number']
];
 
function logMessage($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

/**
 * Verify payment for order 
 * @param $order
 * @param $acbTransactions
 * return bool
 */

 function checkPayment($order, $acbTransactions) {
    if (empty($order['transaction_code']) || !is_array($acbTransactions)) {
        return false;
    }

    $transactionCode = trim((string)$order['transaction_code']);

    foreach ($acbTransactions as $trans) {
        if (empty($trans['description'])) {
            continue;
        }

        $description = (string)$trans['description'];

        // kiểm tra xem transaction_code có nằm trong description không, nếu có => true
        if (stripos($description, $transactionCode) !== false && $order['total_price'] == $trans['amount']) {
            return true;
        }
    }

    return false;
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
            $acbTransactions = getAcbTransaction($acbInfo)['data']['data'];
            
            foreach ($pendingOrders as $order) {
                try {
                    // Check payment
                    $result = checkPayment($order, $acbTransactions);
                    
                    if ($result) {
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
                                $pdo->prepare("UPDATE orders SET account_id = ? WHERE id = ?")->execute([$accounts[0], $order['id']]);
                                $accountIds = implode(',', $accounts);
                                $pdo->exec("UPDATE product_accounts SET is_sold = 1, sold_at = NOW() WHERE id IN ($accountIds)");
                                logMessage("   → Account assigned");
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
                            'ACB',
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
