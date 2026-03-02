<?php
/**
 * VietQR Helper Functions
 */

/**
 * Generate unique transaction code
 */
function generateTransactionCode($pdo, $prefix = null) {
    // Get prefix from payment settings if not provided
    if ($prefix === null) {
        $stmt = $pdo->query("SELECT transaction_prefix FROM payment_settings WHERE id = 1");
        $prefix = $stmt->fetchColumn() ?: 'ORDER';
    }
    
    $maxAttempts = 10;
    $attempt = 0;
    
    while ($attempt < $maxAttempts) {
        // Generate 8 random digits
        $randomDigits = str_pad(rand(0, 99999999), 8, '0', STR_PAD_LEFT);
        $transactionCode = strtoupper($prefix) . $randomDigits;
        
        // Check if exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE transaction_code = ?");
        $stmt->execute([$transactionCode]);
        
        if ($stmt->fetchColumn() == 0) {
            return $transactionCode;
        }
        
        $attempt++;
    }
    
    // Fallback with timestamp
    return strtoupper($prefix) . time();
}

/**
 * Generate VietQR URL
 */
function generateVietQRUrl($bankCode, $accountNumber, $amount, $transactionCode, $accountHolder = '') {
    // VietQR API format
    $baseUrl = "https://img.vietqr.io/image";
    
    // Clean account holder name (remove special chars, uppercase)
    $cleanAccountHolder = strtoupper(removeVietnameseTones($accountHolder));
    
    // Build URL
    $url = "{$baseUrl}/{$bankCode}-{$accountNumber}-compact.jpg";
    $url .= "?amount={$amount}";
    $url .= "&addInfo=" . urlencode($transactionCode);
    
    if ($cleanAccountHolder) {
        $url .= "&accountName=" . urlencode($cleanAccountHolder);
    }
    
    return $url;
}

/**
 * Remove Vietnamese tones for account holder name
 */
function removeVietnameseTones($str) {
    $vietnameseTones = [
        'à', 'á', 'ạ', 'ả', 'ã', 'â', 'ầ', 'ấ', 'ậ', 'ẩ', 'ẫ', 'ă', 'ằ', 'ắ', 'ặ', 'ẳ', 'ẵ',
        'è', 'é', 'ẹ', 'ẻ', 'ẽ', 'ê', 'ề', 'ế', 'ệ', 'ể', 'ễ',
        'ì', 'í', 'ị', 'ỉ', 'ĩ',
        'ò', 'ó', 'ọ', 'ỏ', 'õ', 'ô', 'ồ', 'ố', 'ộ', 'ổ', 'ỗ', 'ơ', 'ờ', 'ớ', 'ợ', 'ở', 'ỡ',
        'ù', 'ú', 'ụ', 'ủ', 'ũ', 'ư', 'ừ', 'ứ', 'ự', 'ử', 'ữ',
        'ỳ', 'ý', 'ỵ', 'ỷ', 'ỹ',
        'đ',
        'À', 'Á', 'Ạ', 'Ả', 'Ã', 'Â', 'Ầ', 'Ấ', 'Ậ', 'Ẩ', 'Ẫ', 'Ă', 'Ằ', 'Ắ', 'Ặ', 'Ẳ', 'Ẵ',
        'È', 'É', 'Ẹ', 'Ẻ', 'Ẽ', 'Ê', 'Ề', 'Ế', 'Ệ', 'Ể', 'Ễ',
        'Ì', 'Í', 'Ị', 'Ỉ', 'Ĩ',
        'Ò', 'Ó', 'Ọ', 'Ỏ', 'Õ', 'Ô', 'Ồ', 'Ố', 'Ộ', 'Ổ', 'Ỗ', 'Ơ', 'Ờ', 'Ớ', 'Ợ', 'Ở', 'Ỡ',
        'Ù', 'Ú', 'Ụ', 'Ủ', 'Ũ', 'Ư', 'Ừ', 'Ứ', 'Ự', 'Ử', 'Ữ',
        'Ỳ', 'Ý', 'Ỵ', 'Ỷ', 'Ỹ',
        'Đ'
    ];
    
    $replacements = [
        'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a',
        'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e',
        'i', 'i', 'i', 'i', 'i',
        'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o',
        'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u',
        'y', 'y', 'y', 'y', 'y',
        'd',
        'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A',
        'E', 'E', 'E', 'E', 'E', 'E', 'E', 'E', 'E', 'E', 'E',
        'I', 'I', 'I', 'I', 'I',
        'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O',
        'U', 'U', 'U', 'U', 'U', 'U', 'U', 'U', 'U', 'U', 'U',
        'Y', 'Y', 'Y', 'Y', 'Y',
        'D'
    ];
    
    return str_replace($vietnameseTones, $replacements, $str);
}

/**
 * Format VND currency
 */
function formatVND($amount) {
    return number_format($amount, 0, ',', '.') . ' VNĐ';
}

/**
 * Convert USD to VND
 */
function convertToVND($usdAmount, $pdo) {
    try {
        $rate = $pdo->query("SELECT usd_to_vnd_rate FROM payment_settings WHERE id = 1")->fetchColumn();
        return round($usdAmount * ($rate ?: 25000));
    } catch (Exception $e) {
        return round($usdAmount * 25000); // Default rate
    }
}

/**
 * Check if maintenance mode is active
 */
function isMaintenanceMode($pdo) {
    try {
        $maintenance = $pdo->query("SELECT * FROM maintenance_mode WHERE id = 1")->fetch();
        
        if (!$maintenance || !$maintenance['is_enabled']) {
            return false;
        }
        
        // Check time range if set
        if ($maintenance['start_time'] && $maintenance['end_time']) {
            $now = time();
            $start = strtotime($maintenance['start_time']);
            $end = strtotime($maintenance['end_time']);
            
            return ($now >= $start && $now <= $end);
        }
        
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Get maintenance message
 */
function getMaintenanceMessage($pdo) {
    try {
        $maintenance = $pdo->query("SELECT * FROM maintenance_mode WHERE id = 1")->fetch();
        
        $message = "🔧 *Bot Đang Bảo Trì*\n\n";
        
        if ($maintenance['message']) {
            $message .= $maintenance['message'] . "\n\n";
        }
        
        if ($maintenance['start_time'] && $maintenance['end_time']) {
            $message .= "⏰ *Thời gian bảo trì:*\n";
            $message .= "Từ: " . date('H:i d/m/Y', strtotime($maintenance['start_time'])) . "\n";
            $message .= "Đến: " . date('H:i d/m/Y', strtotime($maintenance['end_time'])) . "\n\n";
        }
        
        $message .= "Vui lòng quay lại sau. Xin cảm ơn! 🙏";
        
        return $message;
    } catch (Exception $e) {
        return "🔧 Bot đang bảo trì. Vui lòng quay lại sau!";
    }
}
