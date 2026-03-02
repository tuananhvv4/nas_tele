<?php
/**
 * Delivery Message Template
 * Formats order completion messages based on product delivery style
 */

class DeliveryTemplate {
    
    /**
     * Render wallet payment success message
     * 
     * @param array $order Order data
     * @param array $product Product data
     * @param array $accounts Array of account credentials
     * @param float $newBalance User's new wallet balance
     * @return string Formatted message
     */
    public static function renderWalletSuccess($order, $product, $accounts, $newBalance) {
        $style = $product['delivery_style'] ?? 'default';
        
        if ($style === 'style1') {
            return self::renderWalletStyle1($order, $product, $accounts, $newBalance);
        } elseif ($style === 'style2') {
            return self::renderWalletStyle2($order, $product, $accounts, $newBalance);
        }
        
        return self::renderWalletDefault($order, $product, $accounts, $newBalance);
    }
    
    /**
     * Render QR payment success message
     * 
     * @param array $order Order data
     * @param array $product Product data
     * @param array $accounts Array of account credentials
     * @return string Formatted message
     */
    public static function renderQRSuccess($order, $product, $accounts) {
        $style = $product['delivery_style'] ?? 'default';
        
        if ($style === 'style1') {
            return self::renderQRStyle1($order, $product, $accounts);
        } elseif ($style === 'style2') {
            return self::renderQRStyle2($order, $product, $accounts);
        }
        
        return self::renderQRDefault($order, $product, $accounts);
    }
    
    /**
     * Default wallet payment style (compact)
     */
    private static function renderWalletDefault($order, $product, $accounts, $newBalance) {
        $msg = "✅ <b>THANH TOÁN THÀNH CÔNG!</b>\n\n";
        $msg .= "📦 <b>Sản phẩm:</b> {$product['name']}\n";
        $msg .= "🔢 <b>Số lượng:</b> {$order['quantity']}\n";
        $msg .= "💰 <b>Đã thanh toán:</b> " . number_format($order['total_price'], 0, ',', '.') . " VNĐ\n";
        $msg .= "💳 <b>Số dư còn lại:</b> " . number_format($newBalance, 0, ',', '.') . " VNĐ\n\n";
        $msg .= "🔑 <b>TÀI KHOẢN CỦA BẠN:</b>\n";
        
        // Build all accounts in one code block
        $accountsText = "";
        foreach ($accounts as $index => $account) {
            if ($index > 0) $accountsText .= "\n";
            $accountsText .= "{$account['username']} | {$account['password']}";
        }
        $msg .= "<pre>{$accountsText}</pre>\n\n";
        
        // Add custom message if exists
        if (!empty($product['custom_message'])) {
            $msg .= $product['custom_message'] . "\n\n";
        }
        
        // Add login URL if exists
        if (!empty($product['login_url'])) {
            $msg .= "🔗 Đăng nhập tại: {$product['login_url']}\n\n";
        }
        
        $msg .= "📋 <b>Mã giao dịch:</b> <code>{$order['transaction_code']}</code>\n";
        $msg .= "⏰ " . date('d/m/Y H:i', strtotime($order['created_at']));
        
        return $msg;
    }
    
    /**
     * Style 1 wallet payment (enhanced)
     */
    private static function renderWalletStyle1($order, $product, $accounts, $newBalance) {
        $msg = "✅ <b>THANH TOÁN THÀNH CÔNG!</b>\n\n";
        $msg .= "📦 <b>Sản phẩm:</b> {$product['name']}\n";
        $msg .= "🔢 <b>Số lượng:</b> {$order['quantity']}\n";
        $msg .= "💰 <b>Đã thanh toán:</b> " . number_format($order['total_price'], 0, ',', '.') . " VNĐ\n";
        $msg .= "💳 <b>Số dư còn lại:</b> " . number_format($newBalance, 0, ',', '.') . " VNĐ\n\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━\n";
        $msg .= "🔑 <b>TÀI KHOẢN CỦA BẠN:</b>\n";
        
        // Build all accounts in one code block
        $accountsText = "";
        foreach ($accounts as $index => $account) {
            if ($index > 0) $accountsText .= "\n";
            $accountsText .= "{$account['username']} | {$account['password']}";
        }
        $msg .= "<pre>{$accountsText}</pre>\n";
        
        // Add login URL if exists
        if (!empty($product['login_url'])) {
            $msg .= "\nVui lòng đăng nhập vào {$product['login_url']} để tiện lấy OTP nhé!\n";
        }
        
        // Add custom message if exists
        if (!empty($product['custom_message'])) {
            $msg .= $product['custom_message'] . "\n";
        }
        
        $msg .= "━━━━━━━━━━━━━━━━━━━\n";
        $msg .= "📋 <b>Mã giao dịch:</b> <code>{$order['transaction_code']}</code>\n";
        $msg .= "⏰ " . date('d/m/Y H:i', strtotime($order['created_at']));
        
        return $msg;
    }
    
    /**
     * Default QR payment style (compact)
     */
    private static function renderQRDefault($order, $product, $accounts) {
        $msg = "🎉 <b>THANH TOÁN THÀNH CÔNG!</b>\n\n";
        $msg .= "✅ Đơn hàng #{$order['id']} đã được xác nhận\n\n";
        $msg .= "📦 <b>Sản phẩm:</b> {$product['name']}\n";
        $msg .= "🔢 <b>Số lượng:</b> {$order['quantity']}\n";
        $msg .= "💰 <b>Số tiền:</b> " . number_format($order['total_price'], 0, ',', '.') . " VND\n\n";
        $msg .= "🔐 <b>TÀI KHOẢN CỦA BẠN:</b>\n";
        
        // Build all accounts in one code block
        $accountsText = "";
        foreach ($accounts as $index => $account) {
            if ($index > 0) $accountsText .= "\n";
            $accountsText .= "{$account['username']} | {$account['password']}";
        }
        $msg .= "<pre>{$accountsText}</pre>\n\n";
        
        // Add custom message if exists
        if (!empty($product['custom_message'])) {
            $msg .= $product['custom_message'] . "\n\n";
        }
        
        // Add login URL if exists
        if (!empty($product['login_url'])) {
            $msg .= "🔗 Đăng nhập tại: {$product['login_url']}\n\n";
        }
        
        $msg .= "Cảm ơn bạn đã mua hàng! 🙏";
        
        return $msg;
    }
    
    /**
     * Style 1 QR payment (enhanced)
     */
    private static function renderQRStyle1($order, $product, $accounts) {
        $msg = "🎉 <b>THANH TOÁN THÀNH CÔNG!</b>\n\n";
        $msg .= "✅ Đơn hàng #{$order['id']} đã được xác nhận\n\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━\n";
        $msg .= "📦 <b>Sản phẩm:</b> {$product['name']}\n";
        $msg .= "🔢 <b>Số lượng:</b> {$order['quantity']}\n";
        $msg .= "💰 <b>Số tiền:</b> " . number_format($order['total_price'], 0, ',', '.') . " VND\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━\n\n";
        $msg .= "🔐 <b>TÀI KHOẢN CỦA BẠN:</b>\n";
        
        // Build all accounts in one code block
        $accountsText = "";
        foreach ($accounts as $index => $account) {
            if ($index > 0) $accountsText .= "\n";
            $accountsText .= "{$account['username']} | {$account['password']}";
        }
        $msg .= "<pre>{$accountsText}</pre>\n\n";
        
        // Add login URL if exists
        if (!empty($product['login_url'])) {
            $msg .= "Vui lòng đăng nhập vào {$product['login_url']} để tiện lấy OTP nhé!\n";
        }
        
        // Add custom message if exists
        if (!empty($product['custom_message'])) {
            $msg .= $product['custom_message'] . "\n";
        }
        
        $msg .= "━━━━━━━━━━━━━━━━━━━\n\n";
        $msg .= "💡 Vui lòng đổi mật khẩu sau khi đăng nhập lần đầu\n";
        $msg .= "⚠️ Không chia sẻ thông tin tài khoản với người khác\n\n";
        $msg .= "Cảm ơn bạn đã mua hàng! 🙏";
        
        return $msg;
    }
    
    /**
     * Style 2 wallet payment (2FA support)
     */
    private static function renderWalletStyle2($order, $product, $accounts, $newBalance) {
        $msg = "✅ <b>THANH TOÁN THÀNH CÔNG!</b>\n\n";
        $msg .= "📦 <b>Sản phẩm:</b> {$product['name']}\n";
        $msg .= "🔢 <b>Số lượng:</b> {$order['quantity']}\n";
        $msg .= "💰 <b>Đã thanh toán:</b> " . number_format($order['total_price'], 0, ',', '.') . " VNĐ\n";
        $msg .= "💳 <b>Số dư còn lại:</b> " . number_format($newBalance, 0, ',', '.') . " VNĐ\n\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━\n";
        $msg .= "🔑 <b>TÀI KHOẢN CỦA BẠN:</b>\n\n";
        
        // Build all accounts in one code block with 2FA support
        $accountsText = "";
        foreach ($accounts as $index => $account) {
            if ($index > 0) $accountsText .= "\n";
            
            // Format with 2FA if available
            if (!empty($account['twofa'])) {
                $accountsText .= "{$account['username']} | {$account['password']} | {$account['twofa']}";
            } else {
                $accountsText .= "{$account['username']} | {$account['password']}";
            }
        }
        $msg .= "<pre>{$accountsText}</pre>\n\n";
        
        // Add 2FA instruction if exists
        if (!empty($product['twofa_instruction'])) {
            $msg .= "🔐 <b>Hướng dẫn 2FA:</b>\n";
            $msg .= $product['twofa_instruction'] . "\n\n";
        }
        
        // Add login URL if exists
        if (!empty($product['login_url'])) {
            $msg .= "🔗 <b>Link đăng nhập:</b> {$product['login_url']}\n\n";
        }
        
        // Add custom message if exists
        if (!empty($product['custom_message'])) {
            $msg .= $product['custom_message'] . "\n\n";
        }
        
        $msg .= "━━━━━━━━━━━━━━━━━━━\n";
        $msg .= "📋 <b>Mã giao dịch:</b> <code>{$order['transaction_code']}</code>\n";
        $msg .= "⏰ " . date('d/m/Y H:i', strtotime($order['created_at']));
        
        return $msg;
    }
    
    /**
     * Style 2 QR payment (2FA support)
     */
    private static function renderQRStyle2($order, $product, $accounts) {
        $msg = "🎉 <b>THANH TOÁN THÀNH CÔNG!</b>\n\n";
        $msg .= "✅ Đơn hàng #{$order['id']} đã được xác nhận\n\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━\n";
        $msg .= "📦 <b>Sản phẩm:</b> {$product['name']}\n";
        $msg .= "🔢 <b>Số lượng:</b> {$order['quantity']}\n";
        $msg .= "💰 <b>Số tiền:</b> " . number_format($order['total_price'], 0, ',', '.') . " VND\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━\n\n";
        $msg .= "🔐 <b>TÀI KHOẢN CỦA BẠN:</b>\n\n";
        
        // Build all accounts in one code block with 2FA support
        $accountsText = "";
        foreach ($accounts as $index => $account) {
            if ($index > 0) $accountsText .= "\n";
            
            // Format with 2FA if available
            if (!empty($account['twofa'])) {
                $accountsText .= "{$account['username']} | {$account['password']} | {$account['twofa']}";
            } else {
                $accountsText .= "{$account['username']} | {$account['password']}";
            }
        }
        $msg .= "<pre>{$accountsText}</pre>\n\n";
        
        // Add 2FA instruction if exists
        if (!empty($product['twofa_instruction'])) {
            $msg .= "🔐 <b>Hướng dẫn 2FA:</b>\n";
            $msg .= $product['twofa_instruction'] . "\n\n";
        }
        
        // Add login URL if exists
        if (!empty($product['login_url'])) {
            $msg .= "🔗 <b>Link đăng nhập:</b> {$product['login_url']}\n\n";
        }
        
        // Add custom message if exists
        if (!empty($product['custom_message'])) {
            $msg .= $product['custom_message'] . "\n\n";
        }
        
        $msg .= "━━━━━━━━━━━━━━━━━━━\n\n";
        $msg .= "💡 <b>Lưu ý:</b> Vui lòng bảo mật thông tin tài khoản\n";
        $msg .= "⚠️ Không chia sẻ 2FA code với người khác\n\n";
        $msg .= "Cảm ơn bạn đã mua hàng! 🙏";
        
        return $msg;
    }
}
