<?php
/**
 * Auto Wallet Top-up Verification Cronjob
 * Check pending wallet top-ups and credit user wallets when payment is verified
 * Run every 2-3 minutes via cPanel cron
 */

set_time_limit(25);
ini_set('max_execution_time', 25);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/wallet_helper.php';
require_once __DIR__ . '/bot/TelegramBot.php';
require_once __DIR__ . '/libs/helper.php';

include_once __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$acbInfo = [
    'account_username' => $_ENV['acb_username'],
    'account_password' => $_ENV['acb_password'],
    'account_number'   => $_ENV['acb_number'],
];

function checkTopupPayment($topup, $acbTransactions) {
    if (empty($topup['transaction_code']) || !is_array($acbTransactions)) {
        return false;
    }

    $transactionCode = trim((string)$topup['transaction_code']);
    $expectedAmount  = floatval($topup['amount']);

    foreach ($acbTransactions as $trans) {
        if (empty($trans['description'])) {
            continue;
        }

        $description = (string)$trans['description'];
        $transAmount = floatval($trans['amount'] ?? 0);

        if (stripos($description, $transactionCode) !== false && abs($expectedAmount - $transAmount) < 1) {
            return true;
        }
    }

    return false;
}

echo "[" . date('Y-m-d H:i:s') . "] 🔄 Starting wallet top-up verification cronjob...\n";

// Run for 60 seconds, checking every second
$startTime = time();
$totalVerified = 0;

while ((time() - $startTime) < 25) {
    // Get all pending top-up requests from last 2 hours
    $stmt = $pdo->prepare("
        SELECT wtr.*, u.telegram_id, u.wallet_balance
        FROM wallet_topup_requests wtr
        JOIN users u ON wtr.user_id = u.id
        WHERE wtr.payment_status = 'pending'
        AND wtr.created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
        ORDER BY wtr.created_at DESC
    ");
    $stmt->execute();
    $pendingTopups = $stmt->fetchAll();

    if (!empty($pendingTopups)) {
        echo "[" . date('Y-m-d H:i:s') . "] 🔍 Found " . count($pendingTopups) . " pending top-ups. Checking payments...\n";

        $acbTransactions = getAcbTransaction($acbInfo)['data']['data'] ?? [];

        // Check each top-up request
        foreach ($pendingTopups as $topup) {
            try {
                $verified = checkTopupPayment($topup, $acbTransactions);

                if (!$verified) {
                    continue;
                }
                
                echo "✅ Payment verified for top-up #{$topup['id']} - {$topup['transaction_code']}\n";
                
                $pdo->beginTransaction();
                
                // Credit user wallet
                $creditResult = addToWallet(
                    $topup['user_id'],
                    $topup['telegram_id'],
                    $topup['amount'],
                    'topup',
                    "Nạp tiền vào ví - Mã GD: {$topup['transaction_code']}",
                    $topup['id'],
                    $pdo
                );
                
                if (!$creditResult) {
                    throw new Exception("Failed to credit wallet");
                }
                
                // Update top-up request status
                $stmt = $pdo->prepare("
                    UPDATE wallet_topup_requests 
                    SET payment_status = 'completed', 
                        completed_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$topup['id']]);
                
                $pdo->commit();
                
                // Send success notification to user via Telegram
                if ($topup['telegram_id']) {
                    $botConfig = $pdo->query("SELECT * FROM bots WHERE id = 1")->fetch();
                    if ($botConfig && $botConfig['is_configured']) {
                        $bot = new TelegramBot($botConfig['bot_token']);
                        
                        // Delete old QR and payment messages
                        if (!empty($topup['qr_message_id'])) {
                            try {
                                $bot->deleteMessage($topup['telegram_id'], $topup['qr_message_id']);
                            } catch (Exception $e) {
                                error_log("Failed to delete QR message: " . $e->getMessage());
                            }
                        }
                        
                        if (!empty($topup['payment_message_id'])) {
                            try {
                                $bot->deleteMessage($topup['telegram_id'], $topup['payment_message_id']);
                            } catch (Exception $e) {
                                error_log("Failed to delete payment message: " . $e->getMessage());
                            }
                        }
                        
                        // Get new balance
                        $stmt = $pdo->prepare("
                            SELECT wallet_balance, username 
                            FROM users 
                            WHERE id = ?
                        ");
                        $stmt->execute([$topup['user_id']]);
                        $userData = $stmt->fetch(PDO::FETCH_ASSOC);

                        $newBalance = $userData['wallet_balance'];
                        $userName   = $userData['username'];

                        $createdAt = date('d/m/Y H:i:s');

                        $message = "✅ <b>NẠP TIỀN THÀNH CÔNG!</b>\n\n";
                        $message .= "💰 <b>Số tiền:</b> " . number_format($topup['amount'], 0, ',', '.') . " VNĐ\n";
                        $message .= "💳 <b>Số dư mới:</b> " . number_format($newBalance, 0, ',', '.') . " VNĐ\n\n";
                        $message .= "📋 <b>Mã GD:</b> <code>{$topup['transaction_code']}</code>\n";
                        $message .= "⏰ " . $createdAt . "\n\n";
                        $message .= "━━━━━━━━━━━━━━━━━━━\n";
                        $message .= "🎉 Tiền đã được cộng vào ví của bạn!\n";
                        $message .= "Sử dụng /sodu để kiểm tra số dư.";

                        $bot->sendMessage($topup['telegram_id'], $message);

                        $msg = "<b>User: {$userName}</b>\n";
                        $msg .= "<b>Đã nạp " . number_format($topup['amount'], 0, ',', '.') . " VNĐ vào ví.</b>\n";
                        $msg .= "Thời gian: {$createdAt}";

                        $telegram->sendAdminMessage($msg);

                        echo "📤 Sent notification to user {$topup['telegram_id']}\n";
                    }
                }
                
                $totalVerified++;
                
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                echo "❌ Error processing top-up #{$topup['id']}: " . $e->getMessage() . "\n";
            }
            
            // Small delay to avoid rate limit
            usleep(500000); // 0.5 seconds
        }
    }
    
    // Sleep 1 second before next check
    sleep(1);
}

echo "\n✅ Cronjob completed. Verified {$totalVerified} top-ups in 60 seconds.\n";
