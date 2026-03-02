<?php
/**
 * Payment QR Templates
 * Beautiful payment interface with QR code
 */

class PaymentTemplate {
    
    /**
     * Render QR payment screen
     */
    public static function renderQR($order, $qrUrl, $bankInfo) {
        require_once __DIR__ . '/../helpers/MessageFormatter.php';
        
        $orderId = $order['id'];
        $productName = htmlspecialchars($order['product_name']);
        $amount = $order['total_price'] ?? $order['price'];
        $transactionCode = $order['transaction_code'];
        
        // Calculate remaining time - get timeout from payment settings
        global $pdo;
        $stmt = $pdo->query("SELECT order_timeout_minutes FROM payment_settings WHERE id = 1");
        $timeoutMinutes = $stmt->fetchColumn() ?: 10;
        
        $createdTime = strtotime($order['created_at']);
        $remainingMinutes = $timeoutMinutes - floor((time() - $createdTime) / 60);
        
        $msg = "💳 <b>THANH TOÁN ĐƠN HÀNG #{$orderId}</b>\n\n";
        
        // Payment info box
        $msg .= "┏━━━━━━━━━━━━━━━━━━━┓\n";
        $msg .= "┃ 📦 {$productName}\n";
        $msg .= "┃ 💰 " . BotMessageFormatter::formatPrice($amount) . "\n";
        $msg .= "┗━━━━━━━━━━━━━━━━━━━┛\n\n";
        
        $msg .= "📱 <b>Quét mã QR bên dưới để thanh toán</b>\n\n";
        
        // Bank info
        $msg .= BotMessageFormatter::createPaymentInfo($bankInfo, $amount, $transactionCode);
        $msg .= "\n";
        
        // Countdown
        if ($remainingMinutes > 0) {
            $msg .= BotMessageFormatter::formatCountdown($remainingMinutes) . "\n\n";
        } else {
            $msg .= "⏰ <b>Đơn hàng đã hết hạn!</b>\n\n";
        }
        
        $msg .= "━━━━━━━━━━━━━━━━━━━\n";
        $msg .= "⚠️ <i>Vui lòng chuyển khoản ĐÚNG nội dung để được xử lý tự động</i>";
        
        // Create keyboard
        $keyboard = self::getQRKeyboard($orderId, $remainingMinutes > 0);
        
        return [
            'message' => $msg,
            'keyboard' => $keyboard,
            'qr_url' => $qrUrl
        ];
    }
    
    /**
     * Render payment success message
     */
    public static function renderSuccess($order, $accountData) {
        require_once __DIR__ . '/../helpers/MessageFormatter.php';
        
        $msg = BotMessageFormatter::createSuccessMessage($order, $accountData);
        
        // Create keyboard
        $keyboard = [
            [
                ['text' => '🛍️ Mua tiếp', 'callback_data' => 'show_products'],
                ['text' => '📋 Đơn hàng', 'callback_data' => 'my_orders']
            ],
            [
                ['text' => '🏠 Trang chủ', 'callback_data' => 'start']
            ]
        ];
        
        return ['message' => $msg, 'keyboard' => $keyboard];
    }
    
    /**
     * Render payment pending message
     */
    public static function renderPending($order) {
        require_once __DIR__ . '/../helpers/MessageFormatter.php';
        
        $orderId = $order['id'];
        $transactionCode = $order['transaction_code'];
        
        $msg = "⏳ <b>ĐANG CHỜ THANH TOÁN</b>\n\n";
        $msg .= "Đơn hàng #{$orderId} đang chờ xác nhận thanh toán.\n\n";
        $msg .= "📝 Mã giao dịch: <code>{$transactionCode}</code>\n\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━\n";
        $msg .= "💡 <i>Hệ thống sẽ tự động kiểm tra và gửi tài khoản cho bạn sau khi nhận được thanh toán.</i>\n\n";
        $msg .= "⏱️ Thời gian xử lý: 1-5 phút";
        
        $keyboard = [
            [
                ['text' => '🔄 Kiểm tra lại', 'callback_data' => 'check_payment_' . $orderId]
            ],
            [
                ['text' => '💳 Xem QR', 'callback_data' => 'show_qr_' . $orderId],
                ['text' => '📋 Đơn hàng', 'callback_data' => 'my_orders']
            ]
        ];
        
        return ['message' => $msg, 'keyboard' => $keyboard];
    }
    
    /**
     * Render order confirmation before payment
     */
    public static function renderConfirmation($product, $quantity) {
        require_once __DIR__ . '/../helpers/MessageFormatter.php';
        
        $name = htmlspecialchars($product['name']);
        $price = $product['price'];
        $total = $price * $quantity;
        
        $msg = "✅ <b>XÁC NHẬN ĐƠN HÀNG</b>\n\n";
        $msg .= "┏━━━━━━━━━━━━━━━━━━━┓\n";
        $msg .= "┃ 📦 Sản phẩm: <b>{$name}</b>\n";
        $msg .= "┃ 🔢 Số lượng: <b>{$quantity}</b>\n";
        $msg .= "┃ 💰 Đơn giá: " . BotMessageFormatter::formatPrice($price) . "\n";
        $msg .= "┣━━━━━━━━━━━━━━━━━━━┫\n";
        $msg .= "┃ 💵 <b>TỔNG TIỀN: " . BotMessageFormatter::formatPrice($total) . "</b>\n";
        $msg .= "┗━━━━━━━━━━━━━━━━━━━┛\n\n";
        $msg .= "Xác nhận đặt hàng?";
        
        $keyboard = [
            [
                [
                    'text' => '✅ Xác nhận & Thanh toán',
                    'callback_data' => "create_order_{$product['id']}_{$quantity}"
                ]
            ],
            [
                ['text' => '◀️ Quay lại', 'callback_data' => 'buy_' . $product['id']],
                ['text' => '❌ Hủy', 'callback_data' => 'show_products']
            ]
        ];
        
        return ['message' => $msg, 'keyboard' => $keyboard];
    }
    
    /**
     * Get keyboard for QR payment screen
     */
    private static function getQRKeyboard($orderId, $isActive = true) {
        $keyboard = [
            [
                ['text' => '❌ Hủy đơn', 'callback_data' => "cancel_order_{$orderId}"],
                ['text' => '📋 Đơn hàng', 'callback_data' => 'my_orders']
            ],
            [
                ['text' => '🏠 Trang chủ', 'callback_data' => 'start']
            ]
        ];
        
        return $keyboard;
    }
}
