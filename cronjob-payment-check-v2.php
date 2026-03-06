<?php
/**
 * Cronjob Payment Checker
 * Runs for ~35 seconds, checking every 5 seconds
 * Perfect for cronjob that runs every minute
 */

set_time_limit(50);
ini_set('max_execution_time', 50);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/sepay.php';
require_once __DIR__ . '/includes/telegram.php';
require_once __DIR__ . '/libs/helper.php';
include_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$logFile = __DIR__ . '/logs/cronjob-payment-check.log';

// Ensure log directory exists once at startup
$logDir = dirname($logFile);
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

$acbInfo = [
    'account_username' => $_ENV['acb_username'],
    'account_password' => $_ENV['acb_password'],
    'account_number'   => $_ENV['acb_number'],
];

function logMessage(string $message): void
{
    global $logFile;
    file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n", FILE_APPEND);
}

/**
 * Kiểm tra giao dịch ACB có khớp với đơn hàng không.
 * - Mixed payment: so khớp qr_amount (phần còn lại sau khi trừ ví)
 * - QR payment:    so khớp total_price
 */
function checkPayment(array $order, array $acbTransactions): bool
{
    if (empty($order['transaction_code'])) {
        return false;
    }

    $transactionCode = trim((string)$order['transaction_code']);
    $expectedAmount  = floatval($order['qr_amount'] ?? $order['total_price']);

    foreach ($acbTransactions as $trans) {
        if (empty($trans['description'])) {
            continue;
        }

        $transAmount = floatval($trans['amount'] ?? 0);

        if (
            stripos((string)$trans['description'], $transactionCode) !== false
            && abs($expectedAmount - $transAmount) < 1
        ) {
            logMessage("Payment success: Found matching transaction for Order #{$order['id']} with amount {$transAmount} VND");
            return true;
        }
    }

    return false;
}

// ─── Main loop ───────────────────────────────────────────────────────────────

logMessage("🚀 Cronjob started");

$startTime         = time();
$checkCount        = 0;
$paymentsProcessed = 0;
$telegram          = new TelegramNotifier();

while ((time() - $startTime) < 35) {
    try {
        $checkCount++;

        $stmt = $pdo->query("
            SELECT id, product_id, user_id, telegram_id, quantity,
                   total_price, qr_amount, transaction_code, account_id
            FROM orders
            WHERE payment_status = 'pending'
              AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ORDER BY created_at DESC
        ");
        $pendingOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($pendingOrders)) {
            logMessage("Check #{$checkCount}: No pending orders");
            sleep(5);
            continue;
        }

        logMessage("Check #{$checkCount}: Found " . count($pendingOrders) . " pending orders");

        // Lấy giao dịch ACB một lần cho cả batch
        $acbResult       = getAcbTransaction($acbInfo);
        $acbTransactions = $acbResult['data']['data'] ?? null;

        if (!is_array($acbTransactions)) {
            logMessage("❌ Check #{$checkCount}: Could not fetch ACB transactions");
            sleep(5);
            continue;
        }

        foreach ($pendingOrders as $order) {
            try {
                if (!checkPayment($order, $acbTransactions)) {
                    continue;
                }

                logMessage("✅ Payment verified for Order #{$order['id']}");

                // Gán tài khoản nếu chưa có
                if (empty($order['account_id'])) {
                    $quantity  = $order['quantity'] ?? 1;
                    $productId = $order['product_id'];

                    $accountStmt = $pdo->prepare("
                        SELECT id FROM product_accounts
                        WHERE product_id = ? AND is_sold = 0
                        LIMIT ?
                    ");
                    $accountStmt->execute([$productId, $quantity]);
                    $accounts = $accountStmt->fetchAll(PDO::FETCH_COLUMN);

                    if (count($accounts) >= $quantity) {
                        $accountIdsStr = implode(',', $accounts);
                        $pdo->prepare("UPDATE orders SET account_id = ? WHERE id = ?")
                            ->execute([$accountIdsStr, $order['id']]);

                        $placeholders = implode(',', array_fill(0, count($accounts), '?'));
                        $pdo->prepare("
                            UPDATE product_accounts
                            SET is_sold = 1, sold_at = NOW(), order_id = ?
                            WHERE id IN ($placeholders)
                        ")->execute(array_merge([$order['id']], $accounts));

                        logMessage("   → " . count($accounts) . " account(s) assigned [IDs: $accountIdsStr]");
                    } else {
                        logMessage("   ⚠️ Not enough accounts for Order #{$order['id']} (need {$quantity}, have " . count($accounts) . ")");
                    }
                }

                // Cập nhật trạng thái đơn
                $pdo->prepare("
                    UPDATE orders
                    SET payment_status      = 'completed',
                        status              = 'completed',
                        payment_verified_at = NOW(),
                        payment_reference   = 'ACB'
                    WHERE id = ?
                ")->execute([$order['id']]);

                logMessage("   → Order #{$order['id']} updated to completed");

                // Lấy đầy đủ thông tin đơn hàng
                $orderStmt = $pdo->prepare("
                    SELECT o.*, p.name AS product_name
                    FROM orders o
                    JOIN products p ON o.product_id = p.id
                    WHERE o.id = ?
                ");
                $orderStmt->execute([$order['id']]);
                $orderData = $orderStmt->fetch(PDO::FETCH_ASSOC);

                if (!$orderData) {
                    logMessage("   ⚠️ Could not reload Order #{$order['id']} after update");
                    $paymentsProcessed++;
                    continue;
                }

                // Gửi tài khoản cho user qua Telegram
                if (!empty($orderData['telegram_id'])) {
                    $telegram->sendAccountToUser($orderData);
                    logMessage("   → Account sent to Telegram user");
                }

                $paymentsProcessed++;

                // Thông báo cho admin
                $userStmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
                $userStmt->execute([$order['user_id']]);
                $user = $userStmt->fetch(PDO::FETCH_ASSOC);

                $msg  = "✅ Đơn hàng mới\n";
                $msg .= "User: "      . ($user['username'] ?? 'Unknown') . "\n";
                $msg .= "Đã mua: "    . $orderData['product_name'] . "\n";
                $msg .= "Số lượng: "  . $orderData['quantity'] . "\n";
                $msg .= "Tổng tiền: " . formatVND($orderData['total_price']) . "\n";
                $msg .= "Mã đơn: "   . $orderData['id'] . "\n";
                $msg .= "Thời gian: " . date('Y-m-d H:i:s') . "\n";

                sendMessTelegram($msg);

            } catch (Exception $e) {
                logMessage("❌ Error processing Order #{$order['id']}: " . $e->getMessage());
            }
        }

    } catch (Exception $e) {
        logMessage("❌ Error: " . $e->getMessage());
    }

    sleep(5);
}

$duration = time() - $startTime;
logMessage("✅ Cronjob finished — {$duration}s, {$checkCount} checks, {$paymentsProcessed} payments processed");
