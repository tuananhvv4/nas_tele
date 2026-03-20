<?php
/**
 * Telegram Bot Webhook Handler - Enhanced UI/UX Version
 * Beautiful interface with customizable templates
 */

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to Telegram
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/webhook_errors.log');

// Set proper response header
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../config/db.php';
    require_once __DIR__ . '/TelegramBot.php';
    require_once __DIR__ . '/../includes/vietqr.php';
    require_once __DIR__ . '/helpers/MessageFormatter.php';
    require_once __DIR__ . '/templates/WelcomeTemplate.php';
    require_once __DIR__ . '/templates/ProductTemplate.php';
    require_once __DIR__ . '/templates/OrderTemplate.php';
    require_once __DIR__ . '/templates/PaymentTemplate.php';
    require_once __DIR__ . '/../includes/promo_helper.php';
    require_once __DIR__ . '/../includes/wallet_helper.php';
    require_once __DIR__ . '/payment_handlers.php';
    require_once __DIR__ . '/handlers/guide_handler.php';
} catch (Exception $e) {
    http_response_code(200);
    error_log("Webhook include error: " . $e->getMessage());
    exit(json_encode(['ok' => false, 'error' => 'Include error']));
}

// Create bot_settings table if not exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS bot_settings (
        id INT PRIMARY KEY DEFAULT 1,
        bot_name VARCHAR(100) DEFAULT 'Shop Bot',
        welcome_style VARCHAR(50) DEFAULT 'modern',
        welcome_message TEXT,
        show_product_images BOOLEAN DEFAULT TRUE,
        items_per_page INT DEFAULT 5,
        payment_timeout_minutes INT DEFAULT 30,
        currency_symbol VARCHAR(10) DEFAULT 'VNĐ',
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    // Insert default settings if not exists
    $pdo->exec("INSERT IGNORE INTO bot_settings (id) VALUES (1)");
} catch (PDOException $e) {
    // Table already exists or error creating
    error_log("Bot settings table error: " . $e->getMessage());
}

// Lấy bot token từ database hoặc sử dụng constant
try {
    $botData = $pdo->query("SELECT * FROM bots WHERE is_configured = 1 AND status = 'active' LIMIT 1")->fetch();
    
    // Fallback to constant nếu không có bot trong database
    if (!$botData && defined('BOT_TOKEN')) {
        $botToken = BOT_TOKEN;
    } elseif ($botData) {
        $botToken = $botData['bot_token'];
    } else {
        // Không có token khả dụng
        http_response_code(200);
        exit(json_encode(['ok' => false, 'error' => 'Bot không được cấu hình']));
    }
} catch (PDOException $e) {
    // Lỗi database, thử constant
    if (defined('BOT_TOKEN')) {
        $botToken = BOT_TOKEN;
    } else {
        http_response_code(200);
        error_log("Database error and no BOT_TOKEN constant: " . $e->getMessage());
        exit(json_encode(['ok' => false, 'error' => 'Lỗi cấu hình bot']));
    }
}

$bot = new TelegramBot($botToken);

// Lấy update từ webhook
$input = file_get_contents('php://input');
$update = json_decode($input, true);

if (!$update) {
    http_response_code(200);
    exit(json_encode(['ok' => true, 'message' => 'Không có update']));
}

// Log update cho debugging (tùy chọn, bỏ comment trong production)
// error_log("Webhook update: " . json_encode($update));

// Bao bọc mọi thứ trong try-catch
try {
    // Kiểm tra maintenance mode
    if (isMaintenanceMode($pdo)) {
        if (isset($update['message'])) {
            $chatId = $update['message']['chat']['id'];
            $bot->sendMessage($chatId, getMaintenanceMessage($pdo));
        }
        http_response_code(200);
        exit(json_encode(['ok' => true, 'message' => 'Bot đang bảo trì, vui lòng quay lại sau!']));
    }

    // Xử lý message
    if (isset($update['message'])) {
        $message = $update['message'];
        $chatId = $message['chat']['id'];
        $text = $message['text'] ?? '';
        $userId = $message['from']['id'];
        $username = $message['from']['username'] ?? $message['from']['first_name'] ?? 'User';

        // Lưu/cập nhật user
        $stmt = $pdo->prepare("
            INSERT INTO users (telegram_id, username, email, role) 
            VALUES (?, ?, ?, 'user')
            ON DUPLICATE KEY UPDATE username = ?, email = ?
        ");
        $email = $userId . '@telegram.bot';
        $stmt->execute([$userId, $username, $email, $username, $email]);

        // Xử lý lệnh
        if ($text === '/start') {
            handleStartCommand($bot, $chatId, $pdo, null, $username);
        } elseif ($text === '/mua') {
            handleProductsCommand($bot, $chatId, $pdo);
        } elseif ($text === '/hdsd' || $text === '/help') {
            // Hướng dẫn sử dụng
            handleUserGuide($bot, $chatId, $pdo);
        } elseif (strpos($text, '/promo ') === 0) {
            // Xử lý kích hoạt mã khuyến mãi
            $code = strtoupper(trim(substr($text, 7))); // Remove "/promo "
            error_log("DEBUG: Promo command matched - code='$code'");
            handlePromoActivation($bot, $chatId, $userId, $code, $pdo, $username);
        } elseif ($text === '/sodu' || $text === '/wallet') {
            // Kiểm tra số dư ví
            handleSoDu($bot, $chatId, $userId, $pdo);
        } elseif ($text === '/naptien' || $text === '/topup') {
            // Nạp tiền vào ví
            handleNapTien($bot, $chatId, $userId, $pdo);
        } elseif (is_numeric($text) && intval($text) > 0) {
            // Kiểm tra nếu user gửi một số - xử lý như là số tiền nạp tùy chỉnh
            $amount = intval($text);
            
            // Kiểm tra số tiền
            if ($amount < 10000) {
                $bot->sendMessage($chatId, "❌ Số tiền tối thiểu là 10,000 VND!");
            } elseif ($amount > 50000000) {
                $bot->sendMessage($chatId, "❌ Số tiền tối đa là 50,000,000 VND!");
            } else {
                // Số tiền hợp lệ - tạo yêu cầu nạp tiền
                error_log("DEBUG: Custom amount from text - amount=$amount, user=$userId");
                handleTopupRequest($bot, $chatId, $userId, $amount, $pdo, null);
            }
        }
    }

// Xử lý callback query
if (isset($update['callback_query'])) {
    $callbackQuery = $update['callback_query'];
    $chatId = $callbackQuery['message']['chat']['id'];
    $messageId = $callbackQuery['message']['message_id'];
    $data = $callbackQuery['data'];
    $userId = $callbackQuery['from']['id'];
    $callbackAnswered = false;

    // Định tuyến callback
    if ($data === 'start') {
        handleStartCommand($bot, $chatId, $pdo, $messageId);
    } elseif ($data === 'show_products') {
        handleProductsCommand($bot, $chatId, $pdo, $messageId);
    } elseif ($data === 'refresh_products') {
        handleProductsCommand($bot, $chatId, $pdo, $messageId);
        $bot->answerCallbackQuery($callbackQuery['id'], '✅ Đã cập nhật danh sách sản phẩm!');
        $callbackAnswered = true;
    } elseif (strpos($data, 'products_page_') === 0) {
        $page = intval(str_replace('products_page_', '', $data));
        handleProductsCommand($bot, $chatId, $pdo, $messageId, $page);
    } elseif (strpos($data, 'product_') === 0) {
        $productId = intval(str_replace('product_', '', $data));
        handleProductDetail($bot, $chatId, $productId, $pdo, $messageId);
    } elseif (strpos($data, 'buy_') === 0) {
        $productId = intval(str_replace('buy_', '', $data));
        handleQuantitySelection($bot, $chatId, $productId, $pdo, $messageId);
    } elseif (strpos($data, 'qty_') === 0) {
        list(, $productId, $quantity) = explode('_', $data);
        handleQuantitySelection($bot, $chatId, intval($productId), $pdo, $messageId, intval($quantity));
    } elseif (strpos($data, 'confirm_buy_') === 0) {
        // Bỏ qua màn hình xác nhận - tạo đơn hàng trực tiếp
        list(, , $productId, $quantity) = explode('_', $data);
        handleCreateOrder($bot, $chatId, $userId, intval($productId), intval($quantity), $pdo, $messageId);
    } elseif (strpos($data, 'create_order_') === 0) {
        list(, , $productId, $quantity) = explode('_', $data);
        handleCreateOrder($bot, $chatId, $userId, intval($productId), intval($quantity), $pdo, $messageId);
    } elseif ($data === 'my_orders') {
        handleMyOrders($bot, $chatId, $userId, $pdo, $messageId);
    } elseif ($data === 'user_guide') {
        // Xóa tin nhắn cũ và gửi hướng dẫn
        $bot->deleteMessage($chatId, $messageId);
        handleUserGuide($bot, $chatId, $pdo);
    } elseif (strpos($data, 'order_detail_') === 0) {
        $orderId = intval(str_replace('order_detail_', '', $data));
        handleOrderDetail($bot, $chatId, $orderId, $pdo, $messageId);
    } elseif (strpos($data, 'show_qr_') === 0) {
        $orderId = intval(str_replace('show_qr_', '', $data));
        handleShowQR($bot, $chatId, $orderId, $pdo, $messageId);
    } elseif (strpos($data, 'check_payment_') === 0) {
        $orderId = intval(str_replace('check_payment_', '', $data));
        handleCheckPayment($bot, $chatId, $orderId, $pdo, $messageId);
    } elseif (strpos($data, 'cancel_order_') === 0) {
        $orderId = intval(str_replace('cancel_order_', '', $data));
        handleCancelOrder($bot, $chatId, $orderId, $pdo, $messageId);
    } elseif ($data === 'cancel_order') {
        // Hủy đơn hàng từ màn hình thanh toán
        $bot->deleteMessage($chatId, $messageId);
        $bot->sendMessage($chatId, "❌ Đơn hàng đã bị hủy.");
    } elseif (strpos($data, 'pay_wallet_') === 0) {
        // Thanh toán bằng ví
        list(, , $productId, $quantity) = explode('_', $data);
        handleWalletPayment($bot, $chatId, $userId, intval($productId), intval($quantity), $pdo, $messageId);
    } elseif (strpos($data, 'pay_qr_') === 0) {
        // Thanh toán bằng QR Code
        list(, , $productId, $quantity) = explode('_', $data);
        handleQRPayment($bot, $chatId, $userId, intval($productId), intval($quantity), $pdo, $messageId);
    } elseif (strpos($data, 'pay_mixed_') === 0) {
        // Thanh toán kết hợp (Ví + QR)
        list(, , $productId, $quantity) = explode('_', $data);
        handleMixedPayment($bot, $chatId, $userId, intval($productId), intval($quantity), $pdo, $messageId);
    } elseif ($data === 'topup_wallet') {
        // Nạp tiền vào ví từ menu chính - CHECK BEFORE topup_ pattern!
        error_log("DEBUG: topup_wallet callback triggered for user $userId");
        handleNapTien($bot, $chatId, $userId, $pdo);
    } elseif ($data === 'topup_custom') {
        // Nhập số tiền tùy chỉnh - CHECK BEFORE topup_ pattern!
        handleCustomTopup($bot, $chatId, $messageId, $pdo);
    } elseif (strpos($data, 'topup_') === 0) {
        // Nạp tiền vào ví với số tiền cụ thể (ví dụ: topup_50000)
        $amount = intval(str_replace('topup_', '', $data));
        error_log("DEBUG: topup amount callback - amount=$amount from data=$data");
        handleTopupRequest($bot, $chatId, $userId, $amount, $pdo, $messageId);
    } elseif ($data === 'cancel_topup') {
        $bot->deleteMessage($chatId, $messageId);
        $bot->sendMessage($chatId, "❌ Nạp tiền đã bị hủy.");
    } elseif (strpos($data, 'cancel_topup_') === 0) {
        // Hủy yêu cầu nạp tiền cụ thể
        $bot->deleteMessage($chatId, $messageId);
        $bot->sendMessage($chatId, "❌ Nạp tiền đã bị hủy.");
    } elseif ($data === 'support') {
        // Thông tin hỗ trợ
        handleSupport($bot, $chatId, $pdo, $messageId);
    }

    // Trả lời callback query (nếu chưa được trả lời thủ công ở trên)
    if (!$callbackAnswered) {
        $bot->answerCallbackQuery($callbackQuery['id']);
    }
}

    // Trả lời thành công
    http_response_code(200);
    echo json_encode(['ok' => true]);
    
} catch (Exception $e) {
    // Log lỗi
    error_log("Webhook error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    
    // Trả lời thành công cho Telegram (để tránh retries)
    http_response_code(200);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}

exit;

// ==================== HÀM XỬ LÝ ====================

/**
 * Hỗ trợ: Gửi hoặc chỉnh sửa tin nhắn (an toàn hơn việc xóa)
 */
function sendWithCleanup($bot, $chatId, $message, $keyboard = null, $oldMessageId = null) {
    // Chuẩn bị các tùy chọn
    $options = ['parse_mode' => 'HTML'];
    if ($keyboard) {
        $options['keyboard'] = $keyboard;
    }
    
    if ($oldMessageId) {
        // Thử chỉnh sửa tin nhắn cũ trước
        try {
            return $bot->editMessage($chatId, $oldMessageId, $message, $keyboard);
        } catch (Exception $e) {
            // Nếu chỉnh sửa thất bại (tin nhắn đã bị xóa/quá cũ), gửi tin nhắn mới
            return $bot->sendMessage($chatId, $message, $options);
        }
    } else {
        // Gửi tin nhắn mới
        return $bot->sendMessage($chatId, $message, $options);
    }
}

/**
 * Hỗ trợ: Gửi tin nhắn với bàn phím
 */
function sendMessageWithKeyboard($bot, $chatId, $message, $keyboard = null) {
    $options = ['parse_mode' => 'HTML'];
    if ($keyboard) {
        $options['keyboard'] = $keyboard;
    }
    return $bot->sendMessage($chatId, $message, $options);
}

/**
 * Hỗ trợ: Chỉnh sửa tin nhắn với bàn phím
 */
function editMessageWithKeyboard($bot, $chatId, $messageId, $message, $keyboard = null) {
    return $bot->editMessage($chatId, $messageId, $message, $keyboard);
}

/**
 * Xử lý lệnh /start
 */
function handleStartCommand($bot, $chatId, $pdo, $messageId = null, $username = null) {
    // Lấy cấu hình bot với xử lý lỗi
    try {
        $settings = $pdo->query("SELECT * FROM bot_settings WHERE id = 1")->fetch();
    } catch (PDOException $e) {
        $settings = false;
    }
    
    $botName = $settings['bot_name'] ?? 'Shop Bot';
    $welcomeStyle = $settings['welcome_style'] ?? 'modern';
    $customMessage = $settings['welcome_message'] ?? null;
    
    // QUAN TRỌNG: Đảm bảo user tồn tại trong database trước
    // Điều này quan trọng cho mã khuyến mãi và các tính năng khác
    try {
        $stmt = $pdo->prepare("
            INSERT INTO users (telegram_id, username, email, role) 
            VALUES (?, ?, ?, 'user')
            ON DUPLICATE KEY UPDATE 
                username = VALUES(username),
                email = VALUES(email)
        ");
        // Sử dụng username cung cấp hoặc fallback
        if (!$username) {
            $username = 'user_' . $chatId;
        }
        $email = $chatId . '@telegram.bot';
        $stmt->execute([$chatId, $username, $email]);
    } catch (PDOException $e) {
        error_log("Error saving user in /start: " . $e->getMessage());
    }
    
    // Lấy số dư ví của user
    $stmt = $pdo->prepare("SELECT id, wallet_balance FROM users WHERE telegram_id = ?");
    $stmt->execute([$chatId]);
    $user = $stmt->fetch();
    $walletBalance = $user ? floatval($user['wallet_balance']) : 0;
    
    // Render tin nhắn chào mừng
    $message = WelcomeTemplate::render($welcomeStyle, $botName, $customMessage);
    
    // Thêm thông tin số dư ví
    $message .= "\n\n💰 <b>Số dư ví:</b> " . formatVND($walletBalance);
    
    $keyboard = WelcomeTemplate::getKeyboard($welcomeStyle);
    
    sendWithCleanup($bot, $chatId, $message, $keyboard, $messageId);
}

/**
 * Xử lý danh sách sản phẩm
 */
function handleProductsCommand($bot, $chatId, $pdo, $messageId = null, $page = 1) {
    // Lấy cấu hình với xử lý lỗi
    try {
        $settings = $pdo->query("SELECT * FROM bot_settings WHERE id = 1")->fetch();
        $itemsPerPage = $settings['items_per_page'] ?? 10;
    } catch (PDOException $e) {
        $itemsPerPage = 10;
    }
    
    // Lấy tổng số sản phẩm
    $total = $pdo->query("SELECT COUNT(*) FROM products WHERE status = 'active'")->fetchColumn();
    $totalPages = ceil($total / $itemsPerPage);
    
    // Lấy sản phẩm cho trang hiện tại
    $offset = ($page - 1) * $itemsPerPage;
    $stmt = $pdo->prepare("
        SELECT p.*, COUNT(CASE WHEN pa.is_sold = 0 THEN 1 END) as stock
        FROM products p
        LEFT JOIN product_accounts pa ON p.id = pa.product_id
        WHERE p.status = 'active'
        GROUP BY p.id
        ORDER BY p.display_order ASC, p.id ASC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$itemsPerPage, $offset]);
    $products = $stmt->fetchAll();
    
    // Render danh sách sản phẩm
    $result = ProductTemplate::renderList($products, $page, $totalPages);
    
    sendWithCleanup($bot, $chatId, $result['message'], $result['keyboard'], $messageId);
}

/**
 * Xử lý chi tiết sản phẩm
 */
function handleProductDetail($bot, $chatId, $productId, $pdo, $messageId) {
    // Lấy sản phẩm với số lượng
    $stmt = $pdo->prepare("
        SELECT p.*, COUNT(CASE WHEN pa.is_sold = 0 THEN 1 END) as stock
        FROM products p
        LEFT JOIN product_accounts pa ON p.id = pa.product_id
        WHERE p.id = ?
        GROUP BY p.id
    ");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    
    if (!$product) {
        $bot->sendMessage($chatId, "❌ Sản phẩm không tồn tại!");
        return;
    }
    
    // Render chi tiết sản phẩm
    $result = ProductTemplate::renderDetail($product);
    sendWithCleanup($bot, $chatId, $result['message'], $result['keyboard'], $messageId);
}

/**
 * Xử lý chọn số lượng
 */
function handleQuantitySelection($bot, $chatId, $productId, $pdo, $messageId, $selectedQty = 1) {
    // Lấy sản phẩm
    $stmt = $pdo->prepare("
        SELECT p.*, COUNT(CASE WHEN pa.is_sold = 0 THEN 1 END) as stock
        FROM products p
        LEFT JOIN product_accounts pa ON p.id = pa.product_id
        WHERE p.id = ?
        GROUP BY p.id
    ");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    
    if (!$product || $product['stock'] == 0) {
        $bot->answerCallbackQuery($callbackQuery['id'], "❌ Sản phẩm hết hàng!", true);
        return;
    }
    
    $selectedQty = max(1, min($selectedQty, $product['stock']));

    // Render chọn số lượng
    $result = ProductTemplate::renderQuantitySelector($product, $selectedQty);
    sendWithCleanup($bot, $chatId, $result['message'], $result['keyboard'], $messageId);
}

/**
 * Xử lý xác nhận đơn hàng
 */
function handleOrderConfirmation($bot, $chatId, $productId, $quantity, $pdo, $messageId) {
    // Lấy sản phẩm
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    
    if (!$product) {
        return;
    }
    
    // Render xác nhận
    $result = PaymentTemplate::renderConfirmation($product, $quantity);
    $bot->editMessage($chatId, $messageId, $result['message'], $result['keyboard']);
}

/**
 * Xử lý tạo đơn hàng
 */
function handleCreateOrder($bot, $chatId, $userId, $productId, $quantity, $pdo, $messageId) {
    try {
        $pdo->beginTransaction();
        
        // Lấy sản phẩm
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? FOR UPDATE");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();
        
        if (!$product) {
            throw new Exception("Sản phẩm không tồn tại!");
        }
        
        // Kiểm tra số lượng
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM product_accounts 
            WHERE product_id = ? AND is_sold = 0
        ");
        $stmt->execute([$productId]);
        $availableStock = $stmt->fetchColumn();
        
        if ($availableStock < $quantity) {
            throw new Exception("Không đủ hàng trong kho!");
        }
        
        
        // Lấy hoặc tạo user
        $stmt = $pdo->prepare("SELECT id, username, wallet_balance FROM users WHERE telegram_id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            // User không tồn tại, tạo user mới
            $telegramUsername = $update['message']['from']['username'] ?? null;
            $firstName = $update['message']['from']['first_name'] ?? 'User';
            
            // Tạo username và email duy nhất cho database
            $username = $telegramUsername ?: 'user_' . $userId;
            $email = $userId . '@telegram.user'; // Fake email for Telegram users
            $passwordHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT); // Random password
            
            $insertStmt = $pdo->prepare("
                INSERT INTO users (telegram_id, username, email, password_hash, role, created_at) 
                VALUES (?, ?, ?, ?, 'user', NOW())
            ");
            $insertStmt->execute([$userId, $username, $email, $passwordHash]);
            
            $user = [
                'id' => $pdo->lastInsertId(),
                'username' => $username,
                'wallet_balance' => 0
            ];
            
            error_log("Created new user: telegram_id=$userId, username=$username");
        }
        
        
        // Tính tổng (giá đã được tính bằng VND)
        $totalPrice = $product['price'] * $quantity;
        $walletBalance = floatval($user['wallet_balance']);
        
        $pdo->commit();
        
        // Hiển thị lựa chọn phương thức thanh toán
        $msg = "🛒 <b>XÁC NHẬN ĐƠN HÀNG</b>\n\n";
        $msg .= "📦 Sản phẩm: <b>" . htmlspecialchars($product['name']) . "</b>\n";
        $msg .= "🔢 Số lượng: <b>$quantity</b>\n";
        $msg .= "💰 Tổng tiền: <b>" . formatVND($totalPrice) . "</b>\n\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━\n";
        $msg .= "💳 Số dư ví: <b>" . formatVND($walletBalance) . "</b>\n\n";
        $msg .= "Chọn phương thức thanh toán:";
        
        $keyboard = [];
        
        // Lựa chọn thanh toán bằng ví (nếu đủ số dư)
        if ($walletBalance >= $totalPrice) {
            $keyboard[] = [
                ['text' => '💰 Thanh toán bằng Ví', 'callback_data' => "pay_wallet_{$productId}_{$quantity}"]
            ];
        }
        
        // Lựa chọn thanh toán bằng QR Code (luôn có sẵn)
        $keyboard[] = [
            ['text' => '📱 Thanh toán QR Code', 'callback_data' => "pay_qr_{$productId}_{$quantity}"]
        ];
        
        // Lựa chọn thanh toán kết hợp (Ví + QR) (nếu ví có số dư nhưng không đủ)
        if ($walletBalance > 0 && $walletBalance < $totalPrice) {
            $remaining = $totalPrice - $walletBalance;
            $keyboard[] = [
                ['text' => '🔀 Kết hợp (Ví + QR)', 'callback_data' => "pay_mixed_{$productId}_{$quantity}"]
            ];
        }
        
        $keyboard[] = [
            ['text' => '❌ Hủy', 'callback_data' => 'cancel_order']
        ];
        
        // Xóa tin nhắn chọn số lượng nếu tồn tại
        if ($messageId) {
            $bot->deleteMessage($chatId, $messageId);
        }
        
        sendMessageWithKeyboard($bot, $chatId, $msg, $keyboard);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $bot->sendMessage($chatId, "❌ Lỗi: " . $e->getMessage());
    }
}

/**
 * Xử lý hiển thị QR Code
 */
function handleShowQR($bot, $chatId, $orderId, $pdo, $messageId = null, $isNew = false) {
    // Lấy đơn hàng
    $stmt = $pdo->prepare("
        SELECT o.*, p.name as product_name
        FROM orders o
        JOIN products p ON o.product_id = p.id
        WHERE o.id = ?
    ");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    
    if (!$order) {
        $bot->sendMessage($chatId, "❌ Đơn hàng không tồn tại!");
        return;
    }
    
    // Lấy cấu hình thanh toán
    $paymentSettings = $pdo->query("SELECT * FROM payment_settings WHERE id = 1")->fetch();
    
    // Render thanh toán QR Code
    $result = PaymentTemplate::renderQR($order, $order['qr_code_url'], $paymentSettings);
    
    if ($isNew || !$messageId) {
        // Xóa tin nhắn chọn số lượng nếu tồn tại
        if (!empty($order['quantity_message_id'])) {
            try {
                $bot->deleteMessage($chatId, $order['quantity_message_id']);
                error_log("Deleted quantity selector message for Order #{$orderId}");
            } catch (Exception $e) {
                error_log("Could not delete quantity selector: " . $e->getMessage());
            }
        }
        
        // Gửi hình ảnh QR Code ONLY (không có caption) - giống như ảnh chụp màn hình của user
        $qrResponse = $bot->sendPhoto($chatId, $result['qr_url'], '');
        $qrMessageId = $qrResponse['result']['message_id'] ?? null;
        
        // Sau đó gửi thông tin thanh toán với bàn phím
        $paymentResponse = sendMessageWithKeyboard($bot, $chatId, $result['message'], $result['keyboard']);
        $paymentMessageId = $paymentResponse['result']['message_id'] ?? null;
        
        // Lưu ID tin nhắn vào database để xóa sau
        if ($qrMessageId && $paymentMessageId) {
            $stmt = $pdo->prepare("
                UPDATE orders 
                SET qr_message_id = ?, payment_message_id = ? 
                WHERE id = ?
            ");
            $stmt->execute([$qrMessageId, $paymentMessageId, $orderId]);
        }
    } else {
        $bot->editMessage($chatId, $messageId, $result['message'], $result['keyboard']);
    }
}

/**
 * Xử lý danh sách đơn hàng của user
 */
function handleMyOrders($bot, $chatId, $userId, $pdo, $messageId = null) {
    // Lấy user
    $stmt = $pdo->prepare("SELECT id FROM users WHERE telegram_id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        return;
    }
    
    // Lấy đơn hàng
    $stmt = $pdo->prepare("
        SELECT o.*, p.name as product_name
        FROM orders o
        JOIN products p ON o.product_id = p.id
        WHERE o.user_id = ?
        ORDER BY o.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$user['id']]);
    $orders = $stmt->fetchAll();
    
    // Render lịch sử đơn hàng
    $result = OrderTemplate::renderHistory($orders);
    
    if ($messageId) {
        editMessageWithKeyboard($bot, $chatId, $messageId, $result['message'], $result['keyboard']);
    } else {
        sendMessageWithKeyboard($bot, $chatId, $result['message'], $result['keyboard']);
    }
}

/**
 * Xử lý chi tiết đơn hàng
 */
function handleOrderDetail($bot, $chatId, $orderId, $pdo, $messageId) {
    // Lấy đơn hàng
    $stmt = $pdo->prepare("
        SELECT o.*, p.name as product_name
        FROM orders o
        JOIN products p ON o.product_id = p.id
        WHERE o.id = ?
    ");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    
    if (!$order) {
        return;
    }

    // Lấy tất cả account theo order_id
    $accStmt = $pdo->prepare("SELECT account_data FROM product_accounts WHERE order_id = ? ORDER BY id ASC");
    $accStmt->execute([$orderId]);
    $accounts = $accStmt->fetchAll(PDO::FETCH_COLUMN);
    $order['account_data'] = !empty($accounts) ? implode("\n", $accounts) : '';

    // Render chi tiết đơn hàng
    $result = OrderTemplate::renderDetail($order);
    $bot->editMessage($chatId, $messageId, $result['message'], $result['keyboard']);
}

/**
 * Xử lý kiểm tra thanh toán (kiểm tra thủ công)
 */
function handleCheckPayment($bot, $chatId, $orderId, $pdo, $messageId) {
    // Lấy đơn hàng
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    
    if (!$order) {
        return;
    }
    
    if ($order['payment_status'] === 'completed') {
        // Đã thanh toán
        $bot->answerCallbackQuery($callbackQuery['id'], "✅ Đơn hàng đã được thanh toán!", true);
        handleOrderDetail($bot, $chatId, $orderId, $pdo, $messageId);
    } else {
        // Hiển thị tin nhắn chờ thanh toán
        $result = PaymentTemplate::renderPending($order);
        $bot->editMessage($chatId, $messageId, $result['message'], $result['keyboard']);
    }
}

/**
 * Xử lý hủy đơn hàng
 */
function handleCancelOrder($bot, $chatId, $orderId, $pdo, $messageId) {
    // Cập nhật trạng thái đơn hàng
    $stmt = $pdo->prepare("
        UPDATE orders 
        SET payment_status = 'cancelled' 
        WHERE id = ? AND payment_status = 'pending'
    ");
    $stmt->execute([$orderId]);
    
    if ($stmt->rowCount() > 0) {
        $bot->sendMessage($chatId, "✅ Đã hủy đơn hàng #{$orderId}");
        handleMyOrders($bot, $chatId, $userId, $pdo, $messageId);
    } else {
        $bot->answerCallbackQuery($callbackQuery['id'], "❌ Không thể hủy đơn hàng này!", true);
    }
}

/**
 * Xử lý kích hoạt mã khuyến mãi qua lệnh /promo
 * Cộng tiền vào ví thay vì kích hoạt phiên giảm giá
 */
function handlePromoActivation($bot, $chatId, $telegramId, $code, $pdo, $telegramUsername = null) {
    error_log("DEBUG: handlePromoActivation called - code=$code, chatId=$chatId, telegramId=$telegramId");
    
    try {
        // Lấy user - nếu không tồn tại, tạo nó
        $stmt = $pdo->prepare("SELECT id, wallet_balance, username FROM users WHERE telegram_id = ?");
        $stmt->execute([$telegramId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            
            // Tạo user với username thực tế của Telegram
            $username = $telegramUsername ?? 'user_' . $telegramId;
            $email = $telegramId . '@telegram.bot';
            $stmt = $pdo->prepare("
                INSERT INTO users (telegram_id, username, email, role, wallet_balance) 
                VALUES (?, ?, ?, 'user', 0)
            ");
            $stmt->execute([$telegramId, $username, $email]);
            
            // Lấy user mới đã tạo
            $stmt = $pdo->prepare("SELECT id, wallet_balance, username FROM users WHERE telegram_id = ?");
            $stmt->execute([$telegramId]);
            $user = $stmt->fetch();
            
            if (!$user) {
                $bot->sendMessage($chatId, "❌ Lỗi: Không thể tạo tài khoản! Vui lòng thử lại sau.");
                return;
            }
        }

        $userId = $user['id'];
        $currentBalance = floatval($user['wallet_balance']);
        
        // Lấy mã khuyến mãi
        $stmt = $pdo->prepare("
            SELECT * FROM promo_codes 
            WHERE code = ? AND status = 'active'
        ");
        $stmt->execute([$code]);
        $promo = $stmt->fetch();
        
        if (!$promo) {
            $bot->sendMessage($chatId, "❌ Mã <b>$code</b> không tồn tại hoặc đã hết hạn!", ['parse_mode' => 'HTML']);
            return;
        }
        
        // Kiểm tra nếu mã đã đạt đến số lần sử dụng tối đa
        $actualUses = $pdo->prepare("SELECT COUNT(*) FROM promo_code_usage WHERE promo_code_id = ?");
        $actualUses->execute([$promo['id']]);
        $usedCount = $actualUses->fetchColumn();
        
        if ($usedCount >= $promo['max_uses']) {
            $bot->sendMessage($chatId, "❌ Mã <b>$code</b> đã hết lượt sử dụng!", ['parse_mode' => 'HTML']);
            return;
        }
        
        // Kiểm tra nếu user đã sử dụng mã này trước đó
        $stmt = $pdo->prepare("
            SELECT * FROM promo_code_usage 
            WHERE promo_code_id = ? AND user_id = ?
        ");
        $stmt->execute([$promo['id'], $userId]);
        if ($stmt->fetch()) {
            $bot->sendMessage($chatId, "❌ Bạn đã sử dụng mã <b>$code</b> rồi!", ['parse_mode' => 'HTML']);
            return;
        }
        
        // Cộng tiền vào ví
        $creditAmount = floatval($promo['credit_amount']);
        
        $success = addToWallet(
            $userId,
            $telegramId,
            $creditAmount,
            'promo',
            "Mã khuyến mãi: $code",
            $promo['id'],
            $pdo
        );
        
        if (!$success) {
            $bot->sendMessage($chatId, "❌ Lỗi khi cộng tiền vào ví!");
            return;
        }
        
        // Ghi nhận sử dụng
        $stmt = $pdo->prepare("
            INSERT INTO promo_code_usage 
            (promo_code_id, user_id, telegram_id, credit_amount) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$promo['id'], $userId, $telegramId, $creditAmount]);
        
        // Cập nhật số lần sử dụng mã khuyến mãi
        $pdo->prepare("UPDATE promo_codes SET used_count = used_count + 1 WHERE id = ?")
            ->execute([$promo['id']]);
        
        // Lấy tên bot/shop
        $botSettings = $pdo->query("SELECT bot_name FROM bot_settings WHERE id = 1")->fetch();
        $shopName = $botSettings['bot_name'] ?? 'Shop';
        
        // Lấy thông tin user từ database
        $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $userDb = $stmt->fetch();
        $username = $userDb['username'] ?? 'User';
        
        // Tin nhắn thành công
        $newBalance = $currentBalance + $creditAmount;
        $msg = "✅ <b>NHẬP MÃ KHUYẾN MÃI THÀNH CÔNG!</b>\n\n";
        $msg .= "👤 User: <b>$username</b>\n";
        $msg .= "🎟️ Mã: <b>$code</b>\n\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━\n";
        $msg .= "💰 Cộng: <b>+" . formatVND($creditAmount) . "</b>\n";
        $msg .= "📝 Được sử dụng vào việc mua hàng trực tiếp tại <b>$shopName</b>\n";
        if ($promo['description']) {
            $msg .= "\n💬 " . htmlspecialchars($promo['description']) . "\n";
        }
        $msg .= "\n━━━━━━━━━━━━━━━━━━━\n";
        $msg .= "💳 Số dư mới: <b>" . formatVND($newBalance) . "</b>";
        $bot->sendMessage($chatId, $msg, ['parse_mode' => 'HTML']);
        
    } catch (Exception $e) {
        error_log("Promo activation error: " . $e->getMessage());
        $bot->sendMessage($chatId, "❌ Lỗi khi kích hoạt mã: " . $e->getMessage());
    }
}

/**
 * Xử lý lệnh /naptien - Nạp tiền vào ví
 */
function handleNapTien($bot, $chatId, $telegramId, $pdo) {
    // Lấy số dư hiện tại
    $stmt = $pdo->prepare("SELECT wallet_balance FROM users WHERE telegram_id = ?");
    $stmt->execute([$telegramId]);
    $user = $stmt->fetch();
    $currentBalance = $user ? floatval($user['wallet_balance']) : 0;
    
    $msg = "💰 <b>NẠP TIỀN VÀO VÍ</b>\n\n";
    $msg .= "💳 Số dư hiện tại: <b>" . formatVND($currentBalance) . "</b>\n\n";
    $msg .= "Chọn số tiền muốn nạp:";
    
    $keyboard = [
        [
            ['text' => '30,000 VND', 'callback_data' => 'topup_30000'],
            ['text' => '50,000 VND', 'callback_data' => 'topup_50000']
        ],
        [
            ['text' => '100,000 VND', 'callback_data' => 'topup_100000'],
            ['text' => '200,000 VND', 'callback_data' => 'topup_200000']
        ],
        [
            ['text' => '500,000 VND', 'callback_data' => 'topup_500000'],
            ['text' => '1,000,000 VND', 'callback_data' => 'topup_1000000']
        ],
        [
            ['text' => '3,000,000 VND', 'callback_data' => 'topup_3000000'],
            ['text' => '5,000,000 VND', 'callback_data' => 'topup_5000000']
        ],
        [
            ['text' => '❌ Hủy', 'callback_data' => 'cancel_topup']
        ]
    ];
    
    sendMessageWithKeyboard($bot, $chatId, $msg, $keyboard);
}

/**
 * Xử lý nhập số tiền tùy chỉnh cho nạp tiền vào ví
 */
function handleCustomTopup($bot, $chatId, $messageId, $pdo) {
    $msg = "💰 <b>NHẬP SỐ TIỀN TÙY CHỈNH</b>\n\n";
    $msg .= "Vui lòng <b>gửi tin nhắn</b> với số tiền bạn muốn nạp (VND)\n\n";
    $msg .= "📝 <b>Lưu ý:</b>\n";
    $msg .= "• Tối thiểu: <b>10,000 VND</b>\n";
    $msg .= "• Tối đa: <b>50,000,000 VND</b>\n";
    $msg .= "• Chỉ nhập số, không cần dấu phẩy\n\n";
    $msg .= "Ví dụ: Gửi tin nhắn <code>150000</code> để nạp 150,000 VND\n\n";
    $msg .= "⚡ Bot sẽ tự động tạo QR code khi nhận được số tiền!";
    
    $keyboard = [
        [
            ['text' => '🔙 Quay lại', 'callback_data' => 'topup_wallet']
        ]
    ];
    
    if ($messageId) {
        $bot->deleteMessage($chatId, $messageId);
    }
    
    sendMessageWithKeyboard($bot, $chatId, $msg, $keyboard);
}

/**
 * Xử lý yêu cầu nạp tiền vào ví
 */
function handleTopupRequest($bot, $chatId, $telegramId, $amount, $pdo, $messageId) {
    try {
        $pdo->beginTransaction();
        
        // Lấy hoặc tạo user
        $stmt = $pdo->prepare("SELECT id FROM users WHERE telegram_id = ?");
        $stmt->execute([$telegramId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            throw new Exception("User not found!");
        }
        
        // Tạo mã giao dịch
        $transactionCode = generateTransactionCode($pdo, 'TOPUP');
        
        // Lấy cấu hình thanh toán
        $paymentSettings = $pdo->query("SELECT * FROM payment_settings WHERE id = 1")->fetch();
        
        // Tạo URL VietQR
        $qrUrl = generateVietQRUrl(
            $paymentSettings['bank_code'],
            $paymentSettings['account_number'],
            $amount,
            $transactionCode,
            $paymentSettings['account_holder']
        );
        
        // Tạo yêu cầu nạp tiền
        $stmt = $pdo->prepare("
            INSERT INTO wallet_topup_requests (
                user_id, telegram_id, amount, transaction_code, qr_code_url, payment_status
            ) VALUES (?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([$user['id'], $telegramId, $amount, $transactionCode, $qrUrl]);
        
        $topupId = $pdo->lastInsertId();
        
        // Thêm vào hàng đợi kiểm tra thanh toán
        $queueStmt = $pdo->prepare("
            INSERT INTO payment_check_queue (topup_id, transaction_code, amount, max_checks)
            VALUES (?, ?, ?, 40)
        ");
        $queueStmt->execute([$topupId, $transactionCode, $amount]);
        
        $pdo->commit();
        
        // Gửi QR Code
        $msg = "💰 <b>NẠP TIỀN VÀO VÍ</b>\n\n";
        $msg .= "💵 Số tiền: <b>" . formatVND($amount) . "</b>\n";
        $msg .= "📋 Mã GD: <code>$transactionCode</code>\n\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━\n";
        $msg .= "🏦 <b>Thông tin chuyển khoản:</b>\n";
        $msg .= "• Ngân hàng: <b>" . $paymentSettings['bank_code'] . "</b>\n";
        $msg .= "• STK: <code>" . $paymentSettings['account_number'] . "</code>\n";
        $msg .= "• Chủ TK: <b>" . $paymentSettings['account_holder'] . "</b>\n";
        $msg .= "• Số tiền: <b>" . formatVND($amount) . "</b>\n";
        $msg .= "• Nội dung: <code>$transactionCode</code>\n\n";
        $msg .= "⚠️ <i>Vui lòng chuyển khoản ĐÚNG nội dung để được cộng tiền tự động!</i>";
        
        $keyboard = [
            [
                ['text' => '🔄 Kiểm tra thanh toán', 'callback_data' => "check_topup_$topupId"]
            ],
            [
                ['text' => '❌ Hủy', 'callback_data' => "cancel_topup_$topupId"]
            ]
        ];
        
        // Gửi hình ảnh QR Code và lấy ID tin nhắn
        $qrResponse = $bot->sendPhoto($chatId, $qrUrl, "Quét mã QR để chuyển khoản");
        $qrMessageId = $qrResponse['result']['message_id'] ?? null;
        
        // Gửi thông tin thanh toán và lấy ID tin nhắn
        $paymentResponse = $bot->sendMessage($chatId, $msg, ['reply_markup' => ['inline_keyboard' => $keyboard]]);
        $paymentMessageId = $paymentResponse['result']['message_id'] ?? null;
        
        // Lưu ID tin nhắn vào database
        if ($qrMessageId || $paymentMessageId) {
            $stmt = $pdo->prepare("
                UPDATE wallet_topup_requests 
                SET qr_message_id = ?, payment_message_id = ?
                WHERE id = ?
            ");
            $stmt->execute([$qrMessageId, $paymentMessageId, $topupId]);
        }
        
        // Xóa tin nhắn gốc
        if ($messageId) {
            $bot->deleteMessage($chatId, $messageId);
        }
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $bot->editMessage($chatId, $messageId, "❌ Lỗi: " . $e->getMessage());
    }
}

/**
 * Xử lý lệnh /sodu - Kiểm tra số dư ví
 */
function handleSoDu($bot, $chatId, $telegramId, $pdo) {
    try {
        // Lấy số dư ví của user
        $stmt = $pdo->prepare("SELECT wallet_balance FROM users WHERE telegram_id = ?");
        $stmt->execute([$telegramId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            // User không tồn tại, hiển thị số dư 0
            $msg = "💰 <b>SỐ DƯ VÍ</b>\n\n";
            $msg .= "💳 Số dư hiện tại: <b>" . formatVND(0) . "</b>\n\n";
            $msg .= "ℹ️ <i>Bạn chưa có giao dịch nào.</i>\n\n";
            $msg .= "Sử dụng /naptien để nạp tiền vào ví!";
            $bot->sendMessage($chatId, $msg, ['parse_mode' => 'HTML']);
            return;
        }
        
        $balance = floatval($user['wallet_balance']);
        
        // Lấy giao dịch gần đây
        $stmt = $pdo->prepare("
            SELECT type, amount, description, created_at 
            FROM wallet_transactions 
            WHERE telegram_id = ? 
            ORDER BY created_at DESC 
            LIMIT 5
        ");
        $stmt->execute([$telegramId]);
        $transactions = $stmt->fetchAll();
        
        $msg = "💰 <b>SỐ DƯ VÍ</b>\n\n";
        $msg .= "💳 Số dư hiện tại: <b>" . formatVND($balance) . "</b>\n\n";
        
        if (!empty($transactions)) {
            $msg .= "📊 <b>Giao dịch gần đây:</b>\n";
            foreach ($transactions as $tx) {
                $icon = $tx['amount'] > 0 ? '➕' : '➖';
                $msg .= "\n$icon " . formatVND(abs($tx['amount']));
                $msg .= " - " . htmlspecialchars($tx['description']);
                $msg .= "\n<i>" . date('d/m H:i', strtotime($tx['created_at'])) . "</i>";
            }
        } else {
            $msg .= "ℹ️ <i>Chưa có giao dịch nào.</i>";
        }
        
        $msg .= "\n\n━━━━━━━━━━━━━━━━━━━\n";
        $msg .= "💡 Dùng /naptien để nạp tiền\n";
        $msg .= "🛒 Dùng /mua để mua hàng";
        
        $bot->sendMessage($chatId, $msg, ['parse_mode' => 'HTML']);
        
    } catch (Exception $e) {
        error_log("Check balance error: " . $e->getMessage());
        $bot->sendMessage($chatId, "❌ Lỗi khi kiểm tra số dư!");
    }
}

/**
 * Xử lý hỗ trợ - Hiển thị thông tin liên hệ
 */
function handleSupport($bot, $chatId, $pdo, $messageId = null) {
    try {
        // Lấy cấu hình hỗ trợ
        $stmt = $pdo->query("SELECT bot_name, support_telegram, support_zalo, support_zalo_name FROM bot_settings WHERE id = 1");
        $settings = $stmt->fetch();
        
        $shopName = $settings['bot_name'] ?? 'Shop';
        $telegram = $settings['support_telegram'] ?? '@admin';
        $zalo = $settings['support_zalo'] ?? '0123456789';
        $zaloName = $settings['support_zalo_name'] ?? 'Admin Support';
        
        $msg = "🆘 <b>HỖ TRỢ KHÁCH HÀNG</b>\n\n";
        $msg .= "📞 <b>Liên hệ Admin:</b>\n";
        $msg .= "• Telegram: <b>$telegram</b>\n\n";
        $msg .= "💬 <b>Zalo:</b>\n";
        $msg .= "• SĐT: <b>$zalo</b>\n";
        $msg .= "• Tên: <b>$zaloName</b>\n\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━\n";
        $msg .= "⏰ <b>Hỗ trợ bảo hành đơn hàng 24/7</b>\n\n";
        $msg .= "💡 <i>Chúng tôi luôn sẵn sàng hỗ trợ bạn!</i>";
        
        $keyboard = [
            [
                ['text' => '🏠 Trang chủ', 'callback_data' => 'start']
            ]
        ];
        
        if ($messageId) {
            $bot->deleteMessage($chatId, $messageId);
        }
        
        sendMessageWithKeyboard($bot, $chatId, $msg, $keyboard);
        
    } catch (Exception $e) {
        error_log("Support error: " . $e->getMessage());
        $bot->sendMessage($chatId, "❌ Lỗi khi lấy thông tin hỗ trợ!");
    }
}


