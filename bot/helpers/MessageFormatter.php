<?php
/**
 * Message Formatter Helper
 * Beautiful formatting for Telegram messages
 */

class BotMessageFormatter {
    
    /**
     * Format price with VND currency
     */
    public static function formatPrice($amount, $currency = 'VNĐ') {
        return number_format($amount, 0, ',', '.') . ' ' . $currency;
    }
    
    /**
     * Format date/time
     */
    public static function formatDate($timestamp) {
        return date('d/m/Y H:i', strtotime($timestamp));
    }
    
    /**
     * Create beautiful product card
     */
    public static function createProductCard($product) {
        $name = htmlspecialchars($product['name']);
        $price = self::formatPrice($product['price']);
        $stock = $product['stock'] ?? 0;
        $description = htmlspecialchars($product['description'] ?? '');
        
        $stockIcon = $stock > 10 ? '📊' : ($stock > 0 ? '⚠️' : '❌');
        $stockText = $stock > 0 ? "Còn: {$stock} tài khoản" : "Hết hàng";
        
        $card = "┏━━━━━━━━━━━━━━━━━━━┓\n";
        $card .= "┃ 📦 <b>{$name}</b>\n";
        $card .= "┃ 💰 {$price}\n";
        $card .= "┃ {$stockIcon} {$stockText}\n";
        if ($description) {
            $card .= "┃ ℹ️ {$description}\n";
        }
        $card .= "┗━━━━━━━━━━━━━━━━━━━┛\n";
        
        return $card;
    }
    
    /**
     * Create order timeline entry
     */
    public static function createOrderEntry($order) {
        $orderId = $order['id'];
        $productName = htmlspecialchars($order['product_name']);
        $quantity = $order['quantity'] ?? 1;
        $price = self::formatPrice($order['total_price'] ?? $order['price']);
        $date = self::formatDate($order['created_at']);
        $status = $order['payment_status'];
        
        // Status emoji and text
        $statusMap = [
            'completed' => ['🟢', 'Hoàn thành'],
            'pending' => ['🟡', 'Chờ thanh toán'],
            'cancelled' => ['🔴', 'Đã hủy'],
            'processing' => ['⏳', 'Đang xử lý']
        ];
        
        [$emoji, $statusText] = $statusMap[$status] ?? ['⚪', 'Không xác định'];
        
        $entry = "{$emoji} <b>Đơn #{$orderId}</b> - {$statusText}\n";
        $entry .= "┣━ {$productName}";
        if ($quantity > 1) {
            $entry .= " × {$quantity}";
        }
        $entry .= "\n";
        $entry .= "┣━ {$price}\n";
        $entry .= "┗━ {$date}\n";
        
        return $entry;
    }
    
    /**
     * Create payment info box
     */
    public static function createPaymentInfo($bankInfo, $amount, $transactionCode) {
        $info = "🏦 <b>Ngân hàng:</b> {$bankInfo['bank_name']}\n";
        $info .= "💳 <b>STK:</b> <code>{$bankInfo['account_number']}</code>\n";
        $info .= "👤 <b>Chủ TK:</b> {$bankInfo['account_holder']}\n";
        $info .= "💰 <b>Số tiền:</b> <b>" . self::formatPrice($amount) . "</b>\n";
        $info .= "📝 <b>Nội dung:</b> <code>{$transactionCode}</code>\n";
        
        return $info;
    }
    
    /**
     * Create success message
     */
    public static function createSuccessMessage($order, $accountData) {
        $msg = "🎉 <b>THANH TOÁN THÀNH CÔNG!</b>\n\n";
        $msg .= "✅ Đơn hàng #{$order['id']} đã được xác nhận\n\n";
        $msg .= "📦 <b>Sản phẩm:</b> {$order['product_name']}\n";
        $msg .= "💰 <b>Đã thanh toán:</b> " . self::formatPrice($order['total_price']) . "\n\n";
        $msg .= "🔑 <b>TÀI KHOẢN CỦA BẠN:</b>\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━\n";
        $msg .= "<code>{$accountData}</code>\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━\n\n";
        $msg .= "Cảm ơn bạn đã mua hàng! 💝";
        
        return $msg;
    }
    
    /**
     * Create countdown timer text
     */
    public static function formatCountdown($minutes) {
        if ($minutes <= 0) {
            return "⏰ <b>Hết hạn</b>";
        }
        
        $hours = floor($minutes / 60);
        $mins = $minutes % 60;
        
        if ($hours > 0) {
            return "⏱️ Hết hạn sau: <b>{$hours}h {$mins}m</b>";
        }
        
        return "⏱️ Hết hạn sau: <b>{$mins} phút</b>";
    }
    
    /**
     * Create box with title
     */
    public static function createBox($title, $content) {
        $box = "┏━━━━━━━━━━━━━━━━━━━┓\n";
        $box .= "┃ <b>{$title}</b>\n";
        $box .= "┣━━━━━━━━━━━━━━━━━━━┫\n";
        $box .= $content;
        $box .= "┗━━━━━━━━━━━━━━━━━━━┛\n";
        
        return $box;
    }
}
