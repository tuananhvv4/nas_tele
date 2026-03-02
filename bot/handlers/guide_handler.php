<?php
/**
 * Handle /hdsd command - User Guide
 */
function handleUserGuide($bot, $chatId, $pdo) {
    // Get bot settings
    $settings = $pdo->query("SELECT * FROM bot_settings WHERE id = 1")->fetch();
    $botName = $settings['bot_name'] ?? 'Shop';
    
    $guide = "📚 <b>HƯỚNG DẪN SỬ DỤNG BOT</b>\n\n";
    $guide .= "Chào mừng bạn đến với <b>$botName</b>! 🎉\n";
    $guide .= "Dưới đây là hướng dẫn chi tiết các tính năng:\n\n";
    
    $guide .= "━━━━━━━━━━━━━━━━━━━\n";
    $guide .= "💰 <b>QUẢN LÝ VÍ TIỀN</b>\n";
    $guide .= "━━━━━━━━━━━━━━━━━━━\n\n";
    
    $guide .= "🔹 <b>Xem số dư ví:</b>\n";
    $guide .= "   Lệnh: <code>/sodu</code> hoặc <code>/wallet</code>\n";
    $guide .= "   → Hiển thị số dư hiện tại trong ví của bạn\n\n";
    
    $guide .= "🔹 <b>Nạp tiền vào ví:</b>\n";
    $guide .= "   Lệnh: <code>/naptien</code> hoặc <code>/topup</code>\n";
    $guide .= "   → Chọn mức tiền hoặc nhập số tiền tùy chỉnh\n";
    $guide .= "   → Quét mã QR để thanh toán\n";
    $guide .= "   → Tiền sẽ tự động vào ví sau khi thanh toán\n\n";
    
    $guide .= "💡 <b>Lưu ý:</b>\n";
    $guide .= "   • Số tiền tối thiểu: <b>10.000 VNĐ</b>\n";
    $guide .= "   • Số tiền tối đa: <b>50.000.000 VNĐ</b>\n";
    $guide .= "   • Thanh toán qua QRCODE\n\n";
    
    $guide .= "━━━━━━━━━━━━━━━━━━━\n";
    $guide .= "🎟️ <b>MÃ KHUYẾN MÃI</b>\n";
    $guide .= "━━━━━━━━━━━━━━━━━━━\n\n";
    
    $guide .= "🔹 <b>Kích hoạt mã khuyến mãi:</b>\n";
    $guide .= "   Lệnh: <code>/promo [MÃ]</code>\n";
    $guide .= "   Ví dụ: <code>/promo WELCOME2026</code>\n";
    $guide .= "   → Tiền thưởng sẽ được cộng vào ví ngay lập tức\n\n";
    
    $guide .= "💡 <b>Lưu ý:</b>\n";
    $guide .= "   • Mỗi mã chỉ sử dụng được 1 lần\n";
    $guide .= "   • Kiểm tra hạn sử dụng của mã\n";
    $guide .= "   • Mã không phân biệt chữ hoa/thường\n\n";
    
    $guide .= "━━━━━━━━━━━━━━━━━━━\n";
    $guide .= "🛒 <b>MUA SẢN PHẨM</b>\n";
    $guide .= "━━━━━━━━━━━━━━━━━━━\n\n";
    
    $guide .= "🔹 <b>Xem danh sách sản phẩm:</b>\n";
    $guide .= "   Lệnh: <code>/mua</code>\n";
    $guide .= "   → Hiển thị tất cả sản phẩm có sẵn\n";
    $guide .= "   → Chọn sản phẩm và số lượng\n";
    $guide .= "   → Thanh toán bằng số dư ví\n\n";
    
    $guide .= "💡 <b>Quy trình mua hàng:</b>\n";
    $guide .= "   1️⃣ Gửi lệnh <code>/mua</code>\n";
    $guide .= "   2️⃣ Chọn sản phẩm muốn mua\n";
    $guide .= "   3️⃣ Chọn số lượng\n";
    $guide .= "   4️⃣ Xác nhận thanh toán\n";
    $guide .= "   5️⃣ Nhận thông tin tài khoản ngay lập tức\n\n";
    
    $guide .= "━━━━━━━━━━━━━━━━━━━\n";
    $guide .= "📋 <b>CÁC LỆNH KHÁC</b>\n";
    $guide .= "━━━━━━━━━━━━━━━━━━━\n\n";
    
    $guide .= "• <code>/start</code> - Khởi động bot\n";
    $guide .= "• <code>/hdsd</code> hoặc <code>/help</code> - Xem hướng dẫn này\n";
    $guide .= "• <code>/sodu</code> - Kiểm tra số dư ví\n";
    $guide .= "• <code>/naptien</code> - Nạp tiền vào ví\n";
    $guide .= "• <code>/mua</code> - Mua sản phẩm\n";
    $guide .= "• <code>/promo [MÃ]</code> - Kích hoạt mã khuyến mãi\n\n";
    
    $guide .= "━━━━━━━━━━━━━━━━━━━\n";
    $guide .= "💬 <b>HỖ TRỢ</b>\n";
    $guide .= "━━━━━━━━━━━━━━━━━━━\n\n";
    
    // Get support info from settings
    if (!empty($settings['telegram_admin'])) {
        $guide .= "📱 Telegram: @{$settings['telegram_admin']}\n";
    }
    if (!empty($settings['zalo_phone'])) {
        $guide .= "📞 Zalo: {$settings['zalo_phone']}\n";
    }
    if (empty($settings['telegram_admin']) && empty($settings['zalo_phone'])) {
        $guide .= "📞 Liên hệ admin để được hỗ trợ\n";
    }
    
    $guide .= "\n━━━━━━━━━━━━━━━━━━━\n";
    $guide .= "✨ Chúc bạn mua sắm vui vẻ! ✨";
    
    $bot->sendMessage($chatId, $guide, ['parse_mode' => 'HTML']);
}
