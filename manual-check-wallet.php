<?php
/**
 * Manual Wallet Topup Check
 * Run this manually to process pending wallet topups immediately
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/sepay.php';
require_once __DIR__ . '/includes/wallet_helper.php';
require_once __DIR__ . '/bot/TelegramBot.php';

echo "=== MANUAL WALLET TOPUP CHECK ===\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

// Initialize SePay client
try {
    $sepay = new SePay($pdo);
    echo "✅ SePay initialized\n\n";
} catch (Exception $e) {
    echo "❌ Failed to initialize SePay: " . $e->getMessage() . "\n";
    exit;
}

// Get all pending top-up requests from last 24 hours
echo "🔍 Checking for pending topup requests...\n";
$stmt = $pdo->prepare("
    SELECT wtr.*, u.telegram_id, u.wallet_balance, u.username
    FROM wallet_topup_requests wtr
    JOIN users u ON wtr.user_id = u.id
    WHERE wtr.payment_status = 'pending'
    AND wtr.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ORDER BY wtr.created_at DESC
");
$stmt->execute();
$pendingTopups = $stmt->fetchAll();

if (empty($pendingTopups)) {
    echo "ℹ️  No pending topup requests found\n";
    echo "=== DONE ===\n";
    exit;
}

echo "Found " . count($pendingTopups) . " pending topup(s)\n\n";

$totalVerified = 0;

// Check each top-up request
foreach ($pendingTopups as $topup) {
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "Checking Topup #{$topup['id']}\n";
    echo "User: {$topup['username']} (ID: {$topup['user_id']})\n";
    echo "Amount: " . number_format($topup['amount'], 0, ',', '.') . " VNĐ\n";
    echo "Transaction Code: {$topup['transaction_code']}\n";
    echo "Created: {$topup['created_at']}\n";
    
    try {
        // Check payment with SePay
        echo "Checking SePay API...\n";
        $result = $sepay->checkPayment($topup['transaction_code'], $topup['amount']);
        
        if (!$result['success']) {
            echo "❌ SePay API error: " . $result['message'] . "\n";
            continue;
        }
        
        if (!$result['verified']) {
            echo "⏳ Payment not found yet\n";
            continue;
        }
        
        echo "✅ Payment verified!\n";
        
        $pdo->beginTransaction();
        
        // Credit user wallet
        echo "💰 Crediting wallet...\n";
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
        
        echo "✅ Wallet credited\n";
        
        // Update top-up request status
        $stmt = $pdo->prepare("
            UPDATE wallet_topup_requests 
            SET payment_status = 'completed', 
                completed_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$topup['id']]);
        
        echo "✅ Status updated to completed\n";
        
        $pdo->commit();
        
        // Send success notification to user via Telegram
        if ($topup['telegram_id']) {
            echo "📤 Sending Telegram notification...\n";
            $botConfig = $pdo->query("SELECT * FROM bots WHERE id = 1")->fetch();
            if ($botConfig && $botConfig['is_configured']) {
                $bot = new TelegramBot($botConfig['bot_token']);
                
                // Delete old QR and payment messages
                if (!empty($topup['qr_message_id'])) {
                    try {
                        $bot->deleteMessage($topup['telegram_id'], $topup['qr_message_id']);
                        echo "✅ Deleted QR message\n";
                    } catch (Exception $e) {
                        echo "⚠️  Failed to delete QR message: " . $e->getMessage() . "\n";
                    }
                }
                
                if (!empty($topup['payment_message_id'])) {
                    try {
                        $bot->deleteMessage($topup['telegram_id'], $topup['payment_message_id']);
                        echo "✅ Deleted payment message\n";
                    } catch (Exception $e) {
                        echo "⚠️  Failed to delete payment message: " . $e->getMessage() . "\n";
                    }
                }
                
                // Get new balance
                $stmt = $pdo->prepare("SELECT wallet_balance FROM users WHERE id = ?");
                $stmt->execute([$topup['user_id']]);
                $newBalance = $stmt->fetchColumn();
                
                $message = "✅ <b>NẠP TIỀN THÀNH CÔNG!</b>\n\n";
                $message .= "💰 <b>Số tiền:</b> " . number_format($topup['amount'], 0, ',', '.') . " VNĐ\n";
                $message .= "💳 <b>Số dư mới:</b> " . number_format($newBalance, 0, ',', '.') . " VNĐ\n\n";
                $message .= "📋 <b>Mã GD:</b> <code>{$topup['transaction_code']}</code>\n";
                $message .= "⏰ " . date('d/m/Y H:i') . "\n\n";
                $message .= "━━━━━━━━━━━━━━━━━━━\n";
                $message .= "🎉 Tiền đã được cộng vào ví của bạn!\n";
                $message .= "Sử dụng /sodu để kiểm tra số dư.";
                
                $bot->sendMessage($topup['telegram_id'], $message);
                echo "✅ Notification sent to user {$topup['telegram_id']}\n";
            }
        }
        
        $totalVerified++;
        echo "🎉 Topup #{$topup['id']} completed successfully!\n";
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo "❌ Error processing topup #{$topup['id']}: " . $e->getMessage() . "\n";
    }
    
    // Sleep 2 seconds between requests to avoid rate limit
    if (count($pendingTopups) > 1) {
        echo "⏳ Waiting 2 seconds before next check...\n";
        sleep(2);
    }
}

echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "=== SUMMARY ===\n";
echo "Total pending: " . count($pendingTopups) . "\n";
echo "Successfully verified: {$totalVerified}\n";
echo "=== DONE ===\n";
