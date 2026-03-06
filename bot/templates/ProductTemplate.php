<?php
/**
 * Product Display Templates
 * Beautiful product listing and detail views
 */

class ProductTemplate {
    
    /**
     * Render product list with pagination
     */
    public static function renderList($products, $page = 1, $totalPages = 1) {
        require_once __DIR__ . '/../helpers/MessageFormatter.php';
        
        $msg = "🛍️ <b>DANH SÁCH SẢN PHẨM</b>\n\n";
        
        if (empty($products)) {
            $msg .= "❌ Hiện tại chưa có sản phẩm nào.\n";
            $msg .= "Vui lòng quay lại sau! 🙏";
            return ['message' => $msg, 'keyboard' => []];
        }
        
        $msg .= "Chọn sản phẩm bên dưới 👇\n";
        
        if ($totalPages > 1) {
            $msg .= "📄 Trang {$page}/{$totalPages}";
        }
        
        // Create keyboard
        $keyboard = self::getProductListKeyboard($products, $page, $totalPages);
        
        return ['message' => $msg, 'keyboard' => $keyboard];
    }
    
    /**
     * Render product detail
     */
    public static function renderDetail($product) {
        require_once __DIR__ . '/../helpers/MessageFormatter.php';
        
        $name = htmlspecialchars($product['name']);
        $price = BotMessageFormatter::formatPrice($product['price']);
        $stock = $product['stock'] ?? 0;
        $description = htmlspecialchars($product['description'] ?? 'Không có mô tả');
        $category = htmlspecialchars($product['category'] ?? 'Khác');
        
        $msg = "📦 <b>CHI TIẾT SẢN PHẨM</b>\n\n";
        $msg .= "┏━━━━━━━━━━━━━━━━━━━┓\n";
        $msg .= "┃ <b>{$name}</b>\n";
        $msg .= "┣━━━━━━━━━━━━━━━━━━━┫\n";
        $msg .= "┃ 💰 Giá: <b>{$price}</b>\n";
        $msg .= "┃ 📊 Còn lại: <b>{$stock}</b> tài khoản\n";
        $msg .= "┃ 🏷️ Danh mục: {$category}\n";
        $msg .= "┗━━━━━━━━━━━━━━━━━━━┛\n\n";
        $msg .= "📝 <b>Mô tả:</b>\n{$description}\n\n";
        
        if ($stock > 0) {
            $msg .= "━━━━━━━━━━━━━━━━━━━\n";
            $msg .= "✅ Sẵn sàng giao hàng ngay!";
        } else {
            $msg .= "━━━━━━━━━━━━━━━━━━━\n";
            $msg .= "❌ Tạm hết hàng";
        }
        
        // Create keyboard
        $keyboard = self::getProductDetailKeyboard($product);
        
        return ['message' => $msg, 'keyboard' => $keyboard];
    }
    
    /**
     * Render quantity selection
     */
    public static function renderQuantitySelector($product, $selectedQty = 1) {
        require_once __DIR__ . '/../helpers/MessageFormatter.php';
        
        $name = htmlspecialchars($product['name']);
        $price = $product['price'];
        $stock = $product['stock'] ?? 0;
        $maxQty = $stock;
        $selectedQty = max(1, min($selectedQty, $maxQty));
        
        $msg = "🔢 <b>Chọn số lượng bạn muốn mua:</b>\n\n";
        $msg .= "📦 Sản phẩm: <b>{$name}</b>\n";
        $msg .= "💰 Giá: " . BotMessageFormatter::formatPrice($price) . "/cái\n";
        $msg .= "📊 Kho: <b>{$stock}</b> cái\n\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━\n";
        $msg .= "🛒 Số lượng: <b>{$selectedQty}</b>\n";
        $msg .= "💵 Tổng tiền: <b>" . BotMessageFormatter::formatPrice($price * $selectedQty) . "</b>\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━\n";
        
        $keyboard = self::getQuantityKeyboard($product['id'], $maxQty, $selectedQty);
        
        return ['message' => $msg, 'keyboard' => $keyboard];
    }
    
    /**
     * Get keyboard for product list
     */
    private static function getProductListKeyboard($products, $page, $totalPages) {
        require_once __DIR__ . '/../helpers/MessageFormatter.php';
        $keyboard = [];
        
        // Product buttons (1 per row with name, stock, and price)
        foreach ($products as $product) {
            $stock = $product['stock'] ?? 0;
            $emoji = $stock > 0 ? '🛒' : '❌';
            $name = $product['name'];
            $price = BotMessageFormatter::formatPrice($product['price']);
            
            // Format: "🛒 Product Name (Stock) - 50.000 VNĐ"
            $text = $emoji . ' ' . $name . ' (' . $stock . ') - ' . $price;
            
            $keyboard[] = [
                [
                    'text' => $text,
                    'callback_data' => 'product_' . $product['id']
                ]
            ];
        }
        
        // Pagination buttons
        if ($totalPages > 1) {
            $navRow = [];
            
            if ($page > 1) {
                $navRow[] = [
                    'text' => '◀️ Trước',
                    'callback_data' => 'products_page_' . ($page - 1)
                ];
            }
            
            $navRow[] = [
                'text' => "📄 {$page}/{$totalPages}",
                'callback_data' => 'noop'
            ];
            
            if ($page < $totalPages) {
                $navRow[] = [
                    'text' => 'Sau ▶️',
                    'callback_data' => 'products_page_' . ($page + 1)
                ];
            }
            
            $keyboard[] = $navRow;
        }
        
        // Refresh + back buttons
        $keyboard[] = [
            ['text' => '🔄 Cập nhật sản phẩm', 'callback_data' => 'show_products']
        ];
        $keyboard[] = [
            ['text' => '🏠 Trang chủ', 'callback_data' => 'start']
        ];
        
        return $keyboard;
    }
    
    /**
     * Get keyboard for product detail
     */
    private static function getProductDetailKeyboard($product) {
        $keyboard = [];
        
        if ($product['stock'] > 0) {
            $keyboard[] = [
                [
                    'text' => '🛒 Mua ngay',
                    'callback_data' => 'buy_' . $product['id']
                ]
            ];
        }
        
        $keyboard[] = [
            ['text' => '◀️ Quay lại', 'callback_data' => 'show_products'],
            ['text' => '🏠 Trang chủ', 'callback_data' => 'start']
        ];
        
        return $keyboard;
    }
    
    /**
     * Get keyboard for quantity selection
     */
    private static function getQuantityKeyboard($productId, $maxQty, $selectedQty) {
        $keyboard = [];

        $decQty = max(1, $selectedQty - 1);
        $incQty = min($maxQty, $selectedQty + 1);

        // Row 1: [-] [qty] [+]
        $keyboard[] = [
            ['text' => '➖', 'callback_data' => "qty_{$productId}_{$decQty}"],
            ['text' => "[ {$selectedQty} ]", 'callback_data' => 'noop'],
            ['text' => '➕', 'callback_data' => "qty_{$productId}_{$incQty}"],
        ];

        // Row 2: quick-add buttons
        $quickAdds = [10, 20, 50, 100];
        $quickRow = [];
        foreach ($quickAdds as $amount) {
            $newQty = min($maxQty, $selectedQty + $amount);
            $quickRow[] = [
                'text' => "+{$amount}",
                'callback_data' => "qty_{$productId}_{$newQty}",
            ];
        }
        $keyboard[] = $quickRow;

        // Row 3: confirm
        $keyboard[] = [
            ['text' => '✅ Xác nhận', 'callback_data' => "confirm_buy_{$productId}_{$selectedQty}"]
        ];

        // Row 4: cancel
        $keyboard[] = [
            ['text' => '❌ Hủy', 'callback_data' => 'show_products']
        ];

        return $keyboard;
    }
}
