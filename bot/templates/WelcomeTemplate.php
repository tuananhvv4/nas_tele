<?php
/**
 * Welcome Message Templates
 * 5 different styles for bot welcome message
 */

class WelcomeTemplate {
    
    /**
     * Get welcome message based on style
     */
    public static function render($style, $botName, $customMessage = null) {
        $method = 'render' . ucfirst($style);
        
        if (method_exists(self::class, $method)) {
            return self::$method($botName, $customMessage);
        }
        
        // Default to modern style
        return self::renderModern($botName, $customMessage);
    }
    
    /**
     * Modern Style
     */
    private static function renderModern($botName, $customMessage) {
        $msg = "🤖 <b>Chào mừng đến với {$botName}!</b>\n\n";
        
        if ($customMessage) {
            $msg .= "{$customMessage}\n\n";
        }
        
        $msg .= "✨ <b>Hệ thống bán hàng tự động</b>\n";
        $msg .= "⚡ Giao dịch nhanh chóng\n";
        $msg .= "🔒 An toàn & bảo mật\n";
        $msg .= "💎 Giá cả hợp lý\n\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━\n";
        $msg .= "Chọn chức năng bên dưới để bắt đầu! 👇";
        
        return $msg;
    }
    
    /**
     * Minimal Style
     */
    private static function renderMinimal($botName, $customMessage) {
        $msg = "<b>{$botName}</b>\n\n";
        
        if ($customMessage) {
            $msg .= "{$customMessage}\n\n";
        } else {
            $msg .= "Mua hàng tự động 24/7\n\n";
        }
        
        $msg .= "Chọn chức năng bên dưới →";
        
        return $msg;
    }
    
    /**
     * Gradient Style
     */
    private static function renderGradient($botName, $customMessage) {
        $msg = "╔══════════════════╗\n";
        $msg .= "║  <b>{$botName}</b>  ║\n";
        $msg .= "╚══════════════════╝\n\n";
        
        if ($customMessage) {
            $msg .= "{$customMessage}\n\n";
        }
        
        $msg .= "🎁 Sản phẩm chất lượng\n";
        $msg .= "💎 Giá cả hợp lý\n";
        $msg .= "🚀 Giao hàng tức thì\n";
        $msg .= "🔐 Bảo mật tuyệt đối\n\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━\n";
        $msg .= "Bắt đầu mua sắm ngay! 🛍️";
        
        return $msg;
    }
    
    /**
     * Emoji Style
     */
    private static function renderEmoji($botName, $customMessage) {
        $msg = "👋 <b>Xin chào!</b>\n\n";
        $msg .= "🎉 Welcome to <b>{$botName}</b>\n";
        
        if ($customMessage) {
            $msg .= "\n{$customMessage}\n";
        } else {
            $msg .= "💫 Nơi mua sắm tin cậy\n";
        }
        
        $msg .= "\n🌟 <b>Tại sao chọn chúng tôi?</b>\n";
        $msg .= "✅ Uy tín hàng đầu\n";
        $msg .= "✅ Giao hàng nhanh\n";
        $msg .= "✅ Hỗ trợ 24/7\n";
        $msg .= "✅ Giá tốt nhất\n\n";
        $msg .= "🛒 Bắt đầu mua sắm thôi! 😊";
        
        return $msg;
    }
    
    /**
     * Professional Style
     */
    private static function renderProfessional($botName, $customMessage) {
        $msg = "━━━━━━━━━━━━━━━━━━━\n";
        $msg .= "<b>{$botName}</b>\n";
        $msg .= "Professional Account Store\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━\n\n";
        
        if ($customMessage) {
            $msg .= "{$customMessage}\n\n";
        }
        
        $msg .= "📊 <b>Services:</b>\n";
        $msg .= "  • Premium Accounts\n";
        $msg .= "  • Instant Delivery\n";
        $msg .= "  • 24/7 Support\n";
        $msg .= "  • Warranty Included\n\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━\n";
        $msg .= "Select an option below to continue.";
        
        return $msg;
    }
    
    /**
     * Get inline keyboard for welcome message
     */
    public static function getKeyboard($style) {
        // Modern, Gradient, Emoji styles
        if (in_array($style, ['modern', 'gradient', 'emoji'])) {
            return [
                [
                    ['text' => '🛍️ Mua hàng', 'callback_data' => 'show_products'],
                    ['text' => '📦 Đơn của tôi', 'callback_data' => 'my_orders']
                ],
                [
                    ['text' => '💰 Nạp tiền', 'callback_data' => 'topup_wallet'],
                    ['text' => '📚 Hướng dẫn', 'callback_data' => 'user_guide']
                ],
                [
                    ['text' => '🆘 Hỗ trợ', 'callback_data' => 'support']
                ]
            ];
        }
        
        // Minimal style
        if ($style === 'minimal') {
            return [
                [
                    ['text' => 'Bắt đầu mua →', 'callback_data' => 'show_products']
                ],
                [
                    ['text' => 'Đơn hàng', 'callback_data' => 'my_orders']
                ]
            ];
        }
        
        // Professional style
        return [
            [
                ['text' => '📊 Products', 'callback_data' => 'show_products'],
                ['text' => '📦 Orders', 'callback_data' => 'my_orders']
            ],
            [
                ['text' => '⚙️ Settings', 'callback_data' => 'settings']
            ]
        ];
    }
}
