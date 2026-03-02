<?php
/**
 * Order History Templates
 * Beautiful order listing and detail views
 */

class OrderTemplate {
    
    /**
     * Render order history
     */
    public static function renderHistory($orders) {
        require_once __DIR__ . '/../helpers/MessageFormatter.php';
        
        $msg = "📋 <b>LỊCH SỬ ĐƠN HÀNG</b>\n\n";
        
        if (empty($orders)) {
            $msg .= "📭 Bạn chưa có đơn hàng nào.\n\n";
            $msg .= "🛍️ Hãy bắt đầu mua sắm ngay!";
            
            return [
                'message' => $msg,
                'keyboard' => [
                    [['text' => '🛍️ Mua hàng', 'callback_data' => 'show_products']],
                    [['text' => '🏠 Trang chủ', 'callback_data' => 'start']]
                ]
            ];
        }
        
        foreach ($orders as $order) {
            $msg .= BotMessageFormatter::createOrderEntry($order);
            $msg .= "\n";
        }
        
        $msg .= "━━━━━━━━━━━━━━━━━━━\n";
        $msg .= "📊 Tổng: <b>" . count($orders) . "</b> đơn hàng";
        
        // Create keyboard
        $keyboard = self::getOrderHistoryKeyboard($orders);
        
        return ['message' => $msg, 'keyboard' => $keyboard];
    }
    
    /**
     * Render order detail
     */
    public static function renderDetail($order) {
        require_once __DIR__ . '/../helpers/MessageFormatter.php';
        
        $orderId = $order['id'];
        $productName = htmlspecialchars($order['product_name']);
        $quantity = $order['quantity'] ?? 1;
        $price = BotMessageFormatter::formatPrice($order['total_price'] ?? $order['price']);
        $date = BotMessageFormatter::formatDate($order['created_at']);
        $status = $order['payment_status'];
        $transactionCode = $order['transaction_code'] ?? '';
        
        // Status info
        $statusMap = [
            'completed' => ['🟢', 'Hoàn thành', 'success'],
            'pending' => ['🟡', 'Chờ thanh toán', 'warning'],
            'cancelled' => ['🔴', 'Đã hủy', 'danger'],
            'processing' => ['⏳', 'Đang xử lý', 'info']
        ];
        
        [$emoji, $statusText, $statusType] = $statusMap[$status] ?? ['⚪', 'Không xác định', 'secondary'];
        
        $msg = "📦 <b>CHI TIẾT ĐƠN HÀNG #{$orderId}</b>\n\n";
        $msg .= "┏━━━━━━━━━━━━━━━━━━━┓\n";
        $msg .= "┃ Trạng thái: {$emoji} <b>{$statusText}</b>\n";
        $msg .= "┣━━━━━━━━━━━━━━━━━━━┫\n";
        $msg .= "┃ 📦 Sản phẩm: {$productName}\n";
        $msg .= "┃ 🔢 Số lượng: {$quantity}\n";
        $msg .= "┃ 💰 Tổng tiền: <b>{$price}</b>\n";
        $msg .= "┃ 📅 Ngày đặt: {$date}\n";
        
        if ($transactionCode) {
            $msg .= "┃ 📝 Mã GD: <code>{$transactionCode}</code>\n";
        }
        
        $msg .= "┗━━━━━━━━━━━━━━━━━━━┛\n\n";
        
        // Additional info based on status
        if ($status === 'completed' && !empty($order['account_data'])) {
            $msg .= "🔑 <b>Tài khoản:</b>\n";
            $msg .= "<code>{$order['account_data']}</code>\n\n";
        } elseif ($status === 'pending') {
            $createdTime = strtotime($order['created_at']);
            $timeoutMinutes = 30; // From settings
            $remainingMinutes = $timeoutMinutes - floor((time() - $createdTime) / 60);
            
            if ($remainingMinutes > 0) {
                $msg .= BotMessageFormatter::formatCountdown($remainingMinutes) . "\n\n";
            } else {
                $msg .= "⏰ <b>Đơn hàng đã hết hạn</b>\n\n";
            }
        }
        
        // Create keyboard
        $keyboard = self::getOrderDetailKeyboard($order);
        
        return ['message' => $msg, 'keyboard' => $keyboard];
    }
    
    /**
     * Get keyboard for order history
     */
    private static function getOrderHistoryKeyboard($orders) {
        $keyboard = [];
        
        // Show detail buttons for recent orders (max 5)
        $recentOrders = array_slice($orders, 0, 5);
        foreach ($recentOrders as $order) {
            $status = $order['payment_status'];
            $emoji = $status === 'completed' ? '✅' : ($status === 'pending' ? '⏳' : '❌');
            
            $keyboard[] = [
                [
                    'text' => "{$emoji} Đơn #{$order['id']}",
                    'callback_data' => 'order_detail_' . $order['id']
                ]
            ];
        }
        
        // Action buttons
        $keyboard[] = [
            ['text' => '🔄 Làm mới', 'callback_data' => 'my_orders'],
            ['text' => '🏠 Trang chủ', 'callback_data' => 'start']
        ];
        
        return $keyboard;
    }
    
    /**
     * Get keyboard for order detail
     */
    private static function getOrderDetailKeyboard($order) {
        $keyboard = [];
        
        $status = $order['payment_status'];
        
        if ($status === 'pending') {
            $keyboard[] = [
                ['text' => '💳 Xem QR thanh toán', 'callback_data' => 'show_qr_' . $order['id']],
                ['text' => '❌ Hủy đơn', 'callback_data' => 'cancel_order_' . $order['id']]
            ];
        } elseif ($status === 'completed') {
            // Show account again button
            $keyboard[] = [
                [
                    'text' => '🔑 Xem lại tài khoản',
                    'callback_data' => 'show_account_' . $order['id']
                ]
            ];
        }
        
        // Back buttons
        $keyboard[] = [
            ['text' => '◀️ Đơn hàng', 'callback_data' => 'my_orders'],
            ['text' => '🏠 Trang chủ', 'callback_data' => 'start']
        ];
        
        return $keyboard;
    }
}
