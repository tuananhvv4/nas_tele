<?php
/**
 * Telegram Helper for Payment Notifications
 * Wrapper around TelegramBot class for sending payment confirmations
 */

require_once __DIR__ . '/../bot/TelegramBot.php';
require_once __DIR__ . '/../config/db.php';

class TelegramNotifier {
    private $bot;
    private $db;
    
    public function __construct() {
        global $pdo;
        $this->db = $pdo;
        
        // Get bot token from database
        $stmt = $this->db->query("SELECT bot_token FROM bots WHERE id = 1 LIMIT 1");
        $botData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$botData || empty($botData['bot_token'])) {
            throw new Exception('Bot token not configured');
        }
        
        // Initialize bot
        require_once __DIR__ . '/../bot/TelegramBot.php';
        $this->bot = new \TelegramBot($botData['bot_token']);
    }
    
    /**
     * Send account details to user after successful payment
     */
    public function sendAccountToUser($order) {
        try {
            // Get order details
            $stmt = $this->db->prepare("
                SELECT 
                    o.*,
                    p.name as product_name,
                    p.delivery_style,
                    p.custom_message,
                    p.login_url,
                    p.twofa_instruction
                FROM orders o
                JOIN products p ON o.product_id = p.id
                WHERE o.id = ?
            ");
            $stmt->execute([$order['id']]);
            $orderData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$orderData) {
                error_log("Order not found: " . $order['id']);
                return false;
            }
            
            // DELETE old QR and payment messages first
            $this->deletePaymentMessages($orderData);
            
            // Get product details
            $product = [
                'name' => $orderData['product_name'],
                'delivery_style' => $orderData['delivery_style'],
                'custom_message' => $orderData['custom_message'],
                'login_url' => $orderData['login_url'],
                'twofa_instruction' => $orderData['twofa_instruction']
            ];
            
            // Get ALL accounts for this order
            // Method 1: Try to get by order_id (new method)
            $stmt = $this->db->prepare("
                SELECT account_data 
                FROM product_accounts 
                WHERE order_id = ?
                ORDER BY id ASC
            ");
            $stmt->execute([$order['id']]);
            $accountRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Method 2: Fallback for old orders without order_id
            if (empty($accountRecords)) {
                $quantity = $orderData['quantity'] ?? 1;
                $orderTime = $orderData['payment_verified_at'] ?? $orderData['created_at'];
                
                // Get accounts sold around the same time for this product
                $stmt = $this->db->prepare("
                    SELECT account_data 
                    FROM product_accounts 
                    WHERE product_id = ? 
                    AND is_sold = 1 
                    AND (order_id IS NULL OR order_id = ?)
                    AND sold_at BETWEEN DATE_SUB(?, INTERVAL 2 MINUTE) 
                                    AND DATE_ADD(?, INTERVAL 2 MINUTE)
                    ORDER BY sold_at ASC
                    LIMIT ?
                ");
                $stmt->execute([$orderData['product_id'], $order['id'], $orderTime, $orderTime, $quantity]);
                $accountRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                error_log("Order #{$order['id']}: Using fallback method, found " . count($accountRecords) . " accounts");
            }
            
            // Parse all account data
            $accountsData = [];
            foreach ($accountRecords as $record) {
                $accountData = $record['account_data'];
                
                // Try to parse as JSON first
                $parsed = json_decode($accountData, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
                    // Valid JSON
                    $accountsData[] = $parsed;
                } else {
                    // Plain text - parse with 2FA support
                    // Split by space (handles all formats: user pass, user|pass, user:pass)
                    $parts = preg_split('/\s+/', $accountData);
                    
                    $account = [
                        'username' => trim($parts[0] ?? ''),
                        'password' => trim($parts[1] ?? '')
                    ];
                    
                    // Check if 2FA exists (3rd part)
                    if (isset($parts[2]) && !empty(trim($parts[2]))) {
                        $account['twofa'] = trim($parts[2]);
                    }
                    
                    $accountsData[] = $account;
                }
            }
            
            // Fallback: if no accounts found, log error
            if (empty($accountsData)) {
                error_log("WARNING: No accounts found for Order #{$order['id']}, Product #{$orderData['product_id']}, Quantity: " . ($orderData['quantity'] ?? 1));
                return false;
            }
            
            // Use DeliveryTemplate to format message
            require_once __DIR__ . '/../bot/templates/DeliveryTemplate.php';
            $message = DeliveryTemplate::renderQRSuccess($orderData, $product, $accountsData);
            
            // Create keyboard
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '🛍️ Mua tiếp', 'callback_data' => 'show_products'],
                        ['text' => '📋 Đơn hàng', 'callback_data' => 'my_orders']
                    ],
                    [
                        ['text' => '🏠 Trang chủ', 'callback_data' => 'start']
                    ]
                ]
            ];
            
            
            $chatId = $orderData['telegram_id'];
            
            // Delete BOTH QR messages (image + text)
            // 1. Delete QR image
            if (!empty($orderData['qr_message_id'])) {
                try {
                    $this->bot->deleteMessage($chatId, $orderData['qr_message_id']);
                    error_log("Deleted QR image for Order #{$order['id']}");
                } catch (Exception $e) {
                    error_log("Could not delete QR image: " . $e->getMessage());
                }
            }
            
            // 2. Delete payment text message
            if (!empty($orderData['payment_message_id'])) {
                try {
                    $this->bot->deleteMessage($chatId, $orderData['payment_message_id']);
                    error_log("Deleted payment text for Order #{$order['id']}");
                } catch (Exception $e) {
                    error_log("Could not delete payment text: " . $e->getMessage());
                }
            }
            
            // Then send account message
            $options = [
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode($keyboard)
            ];
            $this->bot->sendMessage($chatId, $message, $options);

            // Gửi file .txt chứa thông tin tài khoản
            $this->sendAccountFile($chatId, $orderData, $product, $accountsData, $keyboard);

            error_log("Account sent to user: Order #{$order['id']}, Telegram ID: {$chatId}");
            return true;
            
        } catch (Exception $e) {
            error_log("Error sending account to user: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Gửi file .txt chứa thông tin tài khoản cho khách
     */
    private function sendAccountFile($chatId, $orderData, $product, $accountsData, $keyboard = []) {
        try {
            $orderCode = str_pad($orderData['id'], 6, '0', STR_PAD_LEFT);
            $tmpFile = sys_get_temp_dir() . "/order_{$orderCode}_accounts.txt";

            $content  = "═══════════════════════════════════\n";
            $content .= "  ĐƠN HÀNG #{$orderCode}\n";
            $content .= "  Sản phẩm: " . ($product['name'] ?? 'N/A') . "\n";
            $content .= "  Số lượng: " . ($orderData['quantity'] ?? 1) . "\n";
            $content .= "  Ngày: " . date('d/m/Y H:i:s') . "\n";
            $content .= "═══════════════════════════════════\n\n";

            foreach ($accountsData as $idx => $acc) {
                $line = ($idx + 1) . ". ";
                if (is_array($acc)) {
                    $line .= ($acc['username'] ?? '') . ' ' . ($acc['password'] ?? '');
                    if (!empty($acc['twofa'])) {
                        $line .= ' ' . $acc['twofa'];
                    }
                } else {
                    $line .= $acc;
                }
                $content .= "Tài khoản " . $line . "\n";
            }

            $content .= "\n═══════════════════════════════════\n";

            file_put_contents($tmpFile, $content);

            $caption  = "📎 <b>File tài khoản đơn hàng #{$orderCode}</b>\n";
            $caption .= "📦 " . ($product['name'] ?? '') . " × " . ($orderData['quantity'] ?? 1);

            $options = ['parse_mode' => 'HTML'];
            if (!empty($keyboard)) {
                $options['reply_markup'] = json_encode(['inline_keyboard' => $keyboard]);
            }

            $this->bot->sendDocument($chatId, $tmpFile, $caption, $options);
            @unlink($tmpFile);

            error_log("Account file sent: Order #{$orderCode}, chat: {$chatId}");
        } catch (Exception $e) {
            error_log("Error sending account file: " . $e->getMessage());
        }
    }

    /**
     * Delete QR and payment messages after payment success
     * Note: We don't actually delete anymore, just edit the payment message
     */
    private function deletePaymentMessages($order) {
        // Skip deletion - we'll edit the payment message instead
        // This keeps chat cleaner
        return true;
    }
    
    /**
     * Send payment reminder to user
     */
    public function sendPaymentReminder($orderId) {
        try {
            $stmt = $this->db->prepare("
                SELECT o.*, p.name as product_name
                FROM orders o
                JOIN products p ON o.product_id = p.id
                WHERE o.id = ?
            ");
            $stmt->execute([$orderId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$order) {
                return false;
            }
            
            $message = "⏰ <b>NHẮC NHỞ THANH TOÁN</b>\n\n";
            $message .= "Đơn hàng #{$order['id']} chưa được thanh toán\n\n";
            $message .= "📦 Sản phẩm: {$order['product_name']}\n";
            $message .= "💰 Số tiền: " . number_format($order['total_price'] ?? $order['price']) . " VND\n";
            $message .= "📝 Mã GD: <code>{$order['transaction_code']}</code>\n\n";
            $message .= "Vui lòng hoàn tất thanh toán trong 30 phút!";
            
            $keyboard = [
                [
                    ['text' => '💳 Xem QR', 'callback_data' => 'show_qr_' . $orderId],
                    ['text' => '✅ Đã thanh toán', 'callback_data' => 'check_payment_' . $orderId]
                ]
            ];
            
            $this->bot->sendMessage($order['telegram_id'], $message, $keyboard);
            return true;
            
        } catch (Exception $e) {
            error_log("Error sending payment reminder: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Standalone helper: Gửi file .txt tài khoản qua Telegram
 *
 * @param TelegramBot $bot       Instance TelegramBot
 * @param int|string  $chatId    Telegram chat ID
 * @param int         $orderId   ID đơn hàng
 * @param string      $productName Tên sản phẩm
 * @param int         $quantity  Số lượng
 * @param array       $accounts  Mảng accounts — mỗi phần tử là string hoặc array {username, password, twofa?}
 * @param array       $keyboard  Inline keyboard (optional)
 */
function sendAccountFileTelegram($bot, $chatId, $orderId, $productName, $quantity, $accounts, $keyboard = []) {
    try {
        $orderCode = str_pad($orderId, 6, '0', STR_PAD_LEFT);
        $tmpFile = sys_get_temp_dir() . "/order_{$orderCode}_accounts.txt";

        $content  = "═══════════════════════════════════\n";
        $content .= "  ĐƠN HÀNG #{$orderCode}\n";
        $content .= "  Sản phẩm: {$productName}\n";
        $content .= "  Số lượng: {$quantity}\n";
        $content .= "  Ngày: " . date('d/m/Y H:i:s') . "\n";
        $content .= "═══════════════════════════════════\n\n";

        foreach ($accounts as $idx => $acc) {
            $line = "";
            if (is_array($acc)) {
                $line .= ($acc['username'] ?? '') . ' ' . ($acc['password'] ?? '');
                if (!empty($acc['twofa'])) {
                    $line .= ' ' . $acc['twofa'];
                }
            } else {
                $line .= $acc;
            }
            $content .= "Tài khoản " . ($idx + 1) . ": " . $line . "\n";
        }

        $content .= "\n═══════════════════════════════════\n";

        file_put_contents($tmpFile, $content);

        $caption  = "📎 <b>File tài khoản đơn hàng #{$orderCode}</b>\n";
        $caption .= "📦 {$productName} × {$quantity}";

        $options = ['parse_mode' => 'HTML'];
        if (!empty($keyboard)) {
            $options['reply_markup'] = json_encode(['inline_keyboard' => $keyboard]);
        }

        $bot->sendDocument($chatId, $tmpFile, $caption, $options);
        @unlink($tmpFile);

        error_log("Account file sent: Order #{$orderCode}, chat: {$chatId}");
        return true;
    } catch (Exception $e) {
        error_log("Error sending account file: " . $e->getMessage());
        return false;
    }
}
