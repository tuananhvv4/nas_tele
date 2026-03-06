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

// Get bot token from database or use constant
try {
    $botData = $pdo->query("SELECT * FROM bots WHERE is_configured = 1 AND status = 'active' LIMIT 1")->fetch();
    
    // Fallback to constant if no bot in database
    if (!$botData && defined('BOT_TOKEN')) {
        $botToken = BOT_TOKEN;
    } elseif ($botData) {
        $botToken = $botData['bot_token'];
    } else {
        // No token available
        http_response_code(200);
        exit(json_encode(['ok' => false, 'error' => 'Bot not configured']));
    }
} catch (PDOException $e) {
    // Database error, try constant
    if (defined('BOT_TOKEN')) {
        $botToken = BOT_TOKEN;
    } else {
        http_response_code(200);
        error_log("Database error and no BOT_TOKEN constant: " . $e->getMessage());
        exit(json_encode(['ok' => false, 'error' => 'Bot configuration error']));
    }
}

$bot = new TelegramBot($botToken);

// Get webhook update
$input = file_get_contents('php://input');
$update = json_decode($input, true);

if (!$update) {
    http_response_code(200);
    exit(json_encode(['ok' => true, 'message' => 'No update']));
}

// Log update for debugging (optional, comment out in production)
// error_log("Webhook update: " . json_encode($update));

// Wrap everything in try-catch
try {
    // Check maintenance mode
    if (isMaintenanceMode($pdo)) {
        if (isset($update['message'])) {
            $chatId = $update['message']['chat']['id'];
            $bot->sendMessage($chatId, getMaintenanceMessage($pdo));
        }
        http_response_code(200);
        exit(json_encode(['ok' => true, 'message' => 'Maintenance mode']));
    }

    // Handle message
    if (isset($update['message'])) {
        $message = $update['message'];
        $chatId = $message['chat']['id'];
        $text = $message['text'] ?? '';
        $userId = $message['from']['id'];
        $username = $message['from']['username'] ?? $message['from']['first_name'] ?? 'User';

        // Save/update user
        $stmt = $pdo->prepare("
            INSERT INTO users (telegram_id, username, email, role) 
            VALUES (?, ?, ?, 'user')
            ON DUPLICATE KEY UPDATE username = ?, email = ?
        ");
        $email = $userId . '@telegram.bot';
        $stmt->execute([$userId, $username, $email, $username, $email]);

        // Handle commands
        if ($text === '/start') {
            handleStartCommand($bot, $chatId, $pdo, null, $username);
        } elseif ($text === '/mua') {
            handleProductsCommand($bot, $chatId, $pdo);
        } elseif ($text === '/hdsd' || $text === '/help') {
            // User guide
            handleUserGuide($bot, $chatId, $pdo);
        } elseif (strpos($text, '/promo ') === 0) {
            // Handle promo code activation
            $code = strtoupper(trim(substr($text, 7))); // Remove "/promo "
            error_log("DEBUG: Promo command matched - code='$code'");
            handlePromoActivation($bot, $chatId, $userId, $code, $pdo, $username);
        } elseif ($text === '/sodu' || $text === '/wallet') {
            // Check wallet balance
            handleSoDu($bot, $chatId, $userId, $pdo);
        } elseif ($text === '/naptien' || $text === '/topup') {
            // Wallet top-up
            handleNapTien($bot, $chatId, $userId, $pdo);
        } elseif (is_numeric($text) && intval($text) > 0) {
            // Check if user sent a number - treat as custom top-up amount
            $amount = intval($text);
            
            // Validate amount
            if ($amount < 10000) {
                $bot->sendMessage($chatId, "❌ Số tiền tối thiểu là 10,000 VND!");
            } elseif ($amount > 50000000) {
                $bot->sendMessage($chatId, "❌ Số tiền tối đa là 50,000,000 VND!");
            } else {
                // Valid amount - create top-up request
                error_log("DEBUG: Custom amount from text - amount=$amount, user=$userId");
                handleTopupRequest($bot, $chatId, $userId, $amount, $pdo, null);
            }
        }
    }

// Handle callback query
if (isset($update['callback_query'])) {
    $callbackQuery = $update['callback_query'];
    $chatId = $callbackQuery['message']['chat']['id'];
    $messageId = $callbackQuery['message']['message_id'];
    $data = $callbackQuery['data'];
    $userId = $callbackQuery['from']['id'];
    $callbackAnswered = false;

    // Route callbacks
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
        // Skip confirmation screen - create order directly
        list(, , $productId, $quantity) = explode('_', $data);
        handleCreateOrder($bot, $chatId, $userId, intval($productId), intval($quantity), $pdo, $messageId);
    } elseif (strpos($data, 'create_order_') === 0) {
        list(, , $productId, $quantity) = explode('_', $data);
        handleCreateOrder($bot, $chatId, $userId, intval($productId), intval($quantity), $pdo, $messageId);
    } elseif ($data === 'my_orders') {
        handleMyOrders($bot, $chatId, $userId, $pdo, $messageId);
    } elseif ($data === 'user_guide') {
        // Delete old message and send guide
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
        // Cancel order from payment selection
        $bot->deleteMessage($chatId, $messageId);
        $bot->sendMessage($chatId, "❌ Đơn hàng đã bị hủy.");
    } elseif (strpos($data, 'pay_wallet_') === 0) {
        // Wallet payment
        list(, , $productId, $quantity) = explode('_', $data);
        handleWalletPayment($bot, $chatId, $userId, intval($productId), intval($quantity), $pdo, $messageId);
    } elseif (strpos($data, 'pay_qr_') === 0) {
        // QR payment
        list(, , $productId, $quantity) = explode('_', $data);
        handleQRPayment($bot, $chatId, $userId, intval($productId), intval($quantity), $pdo, $messageId);
    } elseif (strpos($data, 'pay_mixed_') === 0) {
        // Mixed payment
        list(, , $productId, $quantity) = explode('_', $data);
        handleMixedPayment($bot, $chatId, $userId, intval($productId), intval($quantity), $pdo, $messageId);
    } elseif ($data === 'topup_wallet') {
        // Wallet top-up from main menu - CHECK BEFORE topup_ pattern!
        error_log("DEBUG: topup_wallet callback triggered for user $userId");
        handleNapTien($bot, $chatId, $userId, $pdo);
    } elseif ($data === 'topup_custom') {
        // Custom amount input - CHECK BEFORE topup_ pattern!
        handleCustomTopup($bot, $chatId, $messageId, $pdo);
    } elseif (strpos($data, 'topup_') === 0) {
        // Wallet top-up with specific amount (e.g., topup_50000)
        $amount = intval(str_replace('topup_', '', $data));
        error_log("DEBUG: topup amount callback - amount=$amount from data=$data");
        handleTopupRequest($bot, $chatId, $userId, $amount, $pdo, $messageId);
    } elseif ($data === 'cancel_topup') {
        $bot->deleteMessage($chatId, $messageId);
        $bot->sendMessage($chatId, "❌ Nạp tiền đã bị hủy.");
    } elseif (strpos($data, 'cancel_topup_') === 0) {
        // Cancel specific topup request
        $bot->deleteMessage($chatId, $messageId);
        $bot->sendMessage($chatId, "❌ Nạp tiền đã bị hủy.");
    } elseif ($data === 'support') {
        // Support info
        handleSupport($bot, $chatId, $pdo, $messageId);
    }

    // Answer callback query (nếu chưa được answer thủ công ở trên)
    if (!$callbackAnswered) {
        $bot->answerCallbackQuery($callbackQuery['id']);
    }
}

    // Success response
    http_response_code(200);
    echo json_encode(['ok' => true]);
    
} catch (Exception $e) {
    // Log error
    error_log("Webhook error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    
    // Return success to Telegram anyway (to avoid retries)
    http_response_code(200);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}

exit;

// ==================== HANDLER FUNCTIONS ====================

/**
 * Helper: Send or edit message (safer than delete)
 */
function sendWithCleanup($bot, $chatId, $message, $keyboard = null, $oldMessageId = null) {
    // Prepare options
    $options = ['parse_mode' => 'HTML'];
    if ($keyboard) {
        $options['keyboard'] = $keyboard;
    }
    
    if ($oldMessageId) {
        // Try to edit existing message first
        try {
            return $bot->editMessage($chatId, $oldMessageId, $message, $keyboard);
        } catch (Exception $e) {
            // If edit fails (message deleted/too old), send new
            return $bot->sendMessage($chatId, $message, $options);
        }
    } else {
        // Send new message
        return $bot->sendMessage($chatId, $message, $options);
    }
}

/**
 * Helper: Send message with keyboard
 */
function sendMessageWithKeyboard($bot, $chatId, $message, $keyboard = null) {
    $options = ['parse_mode' => 'HTML'];
    if ($keyboard) {
        $options['keyboard'] = $keyboard;
    }
    return $bot->sendMessage($chatId, $message, $options);
}

/**
 * Helper: Edit message with keyboard
 */
function editMessageWithKeyboard($bot, $chatId, $messageId, $message, $keyboard = null) {
    return $bot->editMessage($chatId, $messageId, $message, $keyboard);
}

/**
 * Handle /start command
 */
function handleStartCommand($bot, $chatId, $pdo, $messageId = null, $username = null) {
    // Get bot settings with error handling
    try {
        $settings = $pdo->query("SELECT * FROM bot_settings WHERE id = 1")->fetch();
    } catch (PDOException $e) {
        $settings = false;
    }
    
    $botName = $settings['bot_name'] ?? 'Shop Bot';
    $welcomeStyle = $settings['welcome_style'] ?? 'modern';
    $customMessage = $settings['welcome_message'] ?? null;
    
    // IMPORTANT: Ensure user exists in database first
    // This is critical for promo codes and other features
    try {
        $stmt = $pdo->prepare("
            INSERT INTO users (telegram_id, username, email, role) 
            VALUES (?, ?, ?, 'user')
            ON DUPLICATE KEY UPDATE 
                username = VALUES(username),
                email = VALUES(email)
        ");
        // Use provided username or fallback
        if (!$username) {
            $username = 'user_' . $chatId;
        }
        $email = $chatId . '@telegram.bot';
        $stmt->execute([$chatId, $username, $email]);
    } catch (PDOException $e) {
        error_log("Error saving user in /start: " . $e->getMessage());
    }
    
    // Get user wallet balance
    $stmt = $pdo->prepare("SELECT id, wallet_balance FROM users WHERE telegram_id = ?");
    $stmt->execute([$chatId]);
    $user = $stmt->fetch();
    $walletBalance = $user ? floatval($user['wallet_balance']) : 0;
    
    // Render welcome message
    $message = WelcomeTemplate::render($welcomeStyle, $botName, $customMessage);
    
    // Add wallet balance info
    $message .= "\n\n💰 <b>Số dư ví:</b> " . formatVND($walletBalance);
    
    $keyboard = WelcomeTemplate::getKeyboard($welcomeStyle);
    
    sendWithCleanup($bot, $chatId, $message, $keyboard, $messageId);
}

/**
 * Handle products list
 */
function handleProductsCommand($bot, $chatId, $pdo, $messageId = null, $page = 1) {
    // Get settings with error handling
    try {
        $settings = $pdo->query("SELECT * FROM bot_settings WHERE id = 1")->fetch();
        $itemsPerPage = $settings['items_per_page'] ?? 10;
    } catch (PDOException $e) {
        $itemsPerPage = 10;
    }
    
    // Get total products
    $total = $pdo->query("SELECT COUNT(*) FROM products WHERE status = 'active'")->fetchColumn();
    $totalPages = ceil($total / $itemsPerPage);
    
    // Get products for current page
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
    
    // Render product list
    $result = ProductTemplate::renderList($products, $page, $totalPages);
    
    sendWithCleanup($bot, $chatId, $result['message'], $result['keyboard'], $messageId);
}

/**
 * Handle product detail
 */
function handleProductDetail($bot, $chatId, $productId, $pdo, $messageId) {
    // Get product with stock
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
    
    // Render product detail
    $result = ProductTemplate::renderDetail($product);
    sendWithCleanup($bot, $chatId, $result['message'], $result['keyboard'], $messageId);
}

/**
 * Handle quantity selection
 */
function handleQuantitySelection($bot, $chatId, $productId, $pdo, $messageId, $selectedQty = 1) {
    // Get product
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

    // Render quantity selector
    $result = ProductTemplate::renderQuantitySelector($product, $selectedQty);
    sendWithCleanup($bot, $chatId, $result['message'], $result['keyboard'], $messageId);
}

/**
 * Handle order confirmation
 */
function handleOrderConfirmation($bot, $chatId, $productId, $quantity, $pdo, $messageId) {
    // Get product
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    
    if (!$product) {
        return;
    }
    
    // Render confirmation
    $result = PaymentTemplate::renderConfirmation($product, $quantity);
    $bot->editMessage($chatId, $messageId, $result['message'], $result['keyboard']);
}

/**
 * Handle create order
 */
function handleCreateOrder($bot, $chatId, $userId, $productId, $quantity, $pdo, $messageId) {
    try {
        $pdo->beginTransaction();
        
        // Get product
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? FOR UPDATE");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();
        
        if (!$product) {
            throw new Exception("Sản phẩm không tồn tại!");
        }
        
        // Check stock
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM product_accounts 
            WHERE product_id = ? AND is_sold = 0
        ");
        $stmt->execute([$productId]);
        $availableStock = $stmt->fetchColumn();
        
        if ($availableStock < $quantity) {
            throw new Exception("Không đủ hàng trong kho!");
        }
        
        
        // Get or create user
        $stmt = $pdo->prepare("SELECT id, username, wallet_balance FROM users WHERE telegram_id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            // User doesn't exist, create new user
            $telegramUsername = $update['message']['from']['username'] ?? null;
            $firstName = $update['message']['from']['first_name'] ?? 'User';
            
            // Generate unique username and email for database
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
        
        
        // Calculate total (prices are already in VND)
        $totalPrice = $product['price'] * $quantity;
        $walletBalance = floatval($user['wallet_balance']);
        
        $pdo->commit();
        
        // Show payment method selection
        $msg = "🛒 <b>XÁC NHẬN ĐƠN HÀNG</b>\n\n";
        $msg .= "📦 Sản phẩm: <b>" . htmlspecialchars($product['name']) . "</b>\n";
        $msg .= "🔢 Số lượng: <b>$quantity</b>\n";
        $msg .= "💰 Tổng tiền: <b>" . formatVND($totalPrice) . "</b>\n\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━\n";
        $msg .= "💳 Số dư ví: <b>" . formatVND($walletBalance) . "</b>\n\n";
        $msg .= "Chọn phương thức thanh toán:";
        
        $keyboard = [];
        
        // Wallet payment option (if sufficient balance)
        if ($walletBalance >= $totalPrice) {
            $keyboard[] = [
                ['text' => '💰 Thanh toán bằng Ví', 'callback_data' => "pay_wallet_{$productId}_{$quantity}"]
            ];
        }
        
        // QR payment option (always available)
        $keyboard[] = [
            ['text' => '📱 Thanh toán QR Code', 'callback_data' => "pay_qr_{$productId}_{$quantity}"]
        ];
        
        // Mixed payment option (if wallet has some balance but not enough)
        if ($walletBalance > 0 && $walletBalance < $totalPrice) {
            $remaining = $totalPrice - $walletBalance;
            $keyboard[] = [
                ['text' => '🔀 Kết hợp (Ví + QR)', 'callback_data' => "pay_mixed_{$productId}_{$quantity}"]
            ];
        }
        
        $keyboard[] = [
            ['text' => '❌ Hủy', 'callback_data' => 'cancel_order']
        ];
        
        // Delete quantity selector message if exists
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
 * Handle show QR code
 */
function handleShowQR($bot, $chatId, $orderId, $pdo, $messageId = null, $isNew = false) {
    // Get order
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
    
    // Get payment settings
    $paymentSettings = $pdo->query("SELECT * FROM payment_settings WHERE id = 1")->fetch();
    
    // Render QR payment
    $result = PaymentTemplate::renderQR($order, $order['qr_code_url'], $paymentSettings);
    
    if ($isNew || !$messageId) {
        // Delete quantity selector message if exists
        if (!empty($order['quantity_message_id'])) {
            try {
                $bot->deleteMessage($chatId, $order['quantity_message_id']);
                error_log("Deleted quantity selector message for Order #{$orderId}");
            } catch (Exception $e) {
                error_log("Could not delete quantity selector: " . $e->getMessage());
            }
        }
        
        // Send QR image ONLY (no caption) - like user's screenshot
        $qrResponse = $bot->sendPhoto($chatId, $result['qr_url'], '');
        $qrMessageId = $qrResponse['result']['message_id'] ?? null;
        
        // Then send payment info with keyboard
        $paymentResponse = sendMessageWithKeyboard($bot, $chatId, $result['message'], $result['keyboard']);
        $paymentMessageId = $paymentResponse['result']['message_id'] ?? null;
        
        // Save message IDs to database for later deletion
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
 * Handle my orders
 */
function handleMyOrders($bot, $chatId, $userId, $pdo, $messageId = null) {
    // Get user
    $stmt = $pdo->prepare("SELECT id FROM users WHERE telegram_id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        return;
    }
    
    // Get orders
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
    
    // Render order history
    $result = OrderTemplate::renderHistory($orders);
    
    if ($messageId) {
        editMessageWithKeyboard($bot, $chatId, $messageId, $result['message'], $result['keyboard']);
    } else {
        sendMessageWithKeyboard($bot, $chatId, $result['message'], $result['keyboard']);
    }
}

/**
 * Handle order detail
 */
function handleOrderDetail($bot, $chatId, $orderId, $pdo, $messageId) {
    // Get order
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

    // Lấy tất cả accounts theo order_id
    $accStmt = $pdo->prepare("SELECT account_data FROM product_accounts WHERE order_id = ? ORDER BY id ASC");
    $accStmt->execute([$orderId]);
    $accounts = $accStmt->fetchAll(PDO::FETCH_COLUMN);
    $order['account_data'] = !empty($accounts) ? implode("\n", $accounts) : '';

    // Render order detail
    $result = OrderTemplate::renderDetail($order);
    $bot->editMessage($chatId, $messageId, $result['message'], $result['keyboard']);
}

/**
 * Handle check payment (manual check)
 */
function handleCheckPayment($bot, $chatId, $orderId, $pdo, $messageId) {
    // Get order
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    
    if (!$order) {
        return;
    }
    
    if ($order['payment_status'] === 'completed') {
        // Already paid
        $bot->answerCallbackQuery($callbackQuery['id'], "✅ Đơn hàng đã được thanh toán!", true);
        handleOrderDetail($bot, $chatId, $orderId, $pdo, $messageId);
    } else {
        // Show pending message
        $result = PaymentTemplate::renderPending($order);
        $bot->editMessage($chatId, $messageId, $result['message'], $result['keyboard']);
    }
}

/**
 * Handle cancel order
 */
function handleCancelOrder($bot, $chatId, $orderId, $pdo, $messageId) {
    // Update order status
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
 * Handle promo code activation via /promo command
 * Credits wallet instead of activating discount session
 */
function handlePromoActivation($bot, $chatId, $telegramId, $code, $pdo, $telegramUsername = null) {
    error_log("DEBUG: handlePromoActivation called - code=$code, chatId=$chatId, telegramId=$telegramId");
    
    try {
        // Get user - if not exists, create it
        $stmt = $pdo->prepare("SELECT id, wallet_balance, username FROM users WHERE telegram_id = ?");
        $stmt->execute([$telegramId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            
            // Create user with real Telegram username
            $username = $telegramUsername ?? 'user_' . $telegramId;
            $email = $telegramId . '@telegram.bot';
            $stmt = $pdo->prepare("
                INSERT INTO users (telegram_id, username, email, role, wallet_balance) 
                VALUES (?, ?, ?, 'user', 0)
            ");
            $stmt->execute([$telegramId, $username, $email]);
            
            // Get the newly created user
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
        
        // Get promo code
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
        
        // Check if code has reached max uses
        $actualUses = $pdo->prepare("SELECT COUNT(*) FROM promo_code_usage WHERE promo_code_id = ?");
        $actualUses->execute([$promo['id']]);
        $usedCount = $actualUses->fetchColumn();
        
        if ($usedCount >= $promo['max_uses']) {
            $bot->sendMessage($chatId, "❌ Mã <b>$code</b> đã hết lượt sử dụng!", ['parse_mode' => 'HTML']);
            return;
        }
        
        // Check if user already used this code
        $stmt = $pdo->prepare("
            SELECT * FROM promo_code_usage 
            WHERE promo_code_id = ? AND user_id = ?
        ");
        $stmt->execute([$promo['id'], $userId]);
        if ($stmt->fetch()) {
            $bot->sendMessage($chatId, "❌ Bạn đã sử dụng mã <b>$code</b> rồi!", ['parse_mode' => 'HTML']);
            return;
        }
        
        // Credit wallet
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
        
        // Record usage
        $stmt = $pdo->prepare("
            INSERT INTO promo_code_usage 
            (promo_code_id, user_id, telegram_id, credit_amount) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$promo['id'], $userId, $telegramId, $creditAmount]);
        
        // Update promo used_count
        $pdo->prepare("UPDATE promo_codes SET used_count = used_count + 1 WHERE id = ?")
            ->execute([$promo['id']]);
        
        // Get bot/shop name
        $botSettings = $pdo->query("SELECT bot_name FROM bot_settings WHERE id = 1")->fetch();
        $shopName = $botSettings['bot_name'] ?? 'Shop';
        
        // Get user info from database
        $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $userDb = $stmt->fetch();
        $username = $userDb['username'] ?? 'User';
        
        // Success message
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
 * Handle /naptien command - Wallet top-up
 */
function handleNapTien($bot, $chatId, $telegramId, $pdo) {
    // Get current balance
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
 * Handle custom amount input for wallet top-up
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
 * Handle wallet top-up request
 */
function handleTopupRequest($bot, $chatId, $telegramId, $amount, $pdo, $messageId) {
    try {
        $pdo->beginTransaction();
        
        // Get or create user
        $stmt = $pdo->prepare("SELECT id FROM users WHERE telegram_id = ?");
        $stmt->execute([$telegramId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            throw new Exception("User not found!");
        }
        
        // Generate transaction code
        $transactionCode = generateTransactionCode($pdo, 'TOPUP');
        
        // Get payment settings
        $paymentSettings = $pdo->query("SELECT * FROM payment_settings WHERE id = 1")->fetch();
        
        // Generate VietQR URL
        $qrUrl = generateVietQRUrl(
            $paymentSettings['bank_code'],
            $paymentSettings['account_number'],
            $amount,
            $transactionCode,
            $paymentSettings['account_holder']
        );
        
        // Create topup request
        $stmt = $pdo->prepare("
            INSERT INTO wallet_topup_requests (
                user_id, telegram_id, amount, transaction_code, qr_code_url, payment_status
            ) VALUES (?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([$user['id'], $telegramId, $amount, $transactionCode, $qrUrl]);
        
        $topupId = $pdo->lastInsertId();
        
        // Add to payment check queue
        $queueStmt = $pdo->prepare("
            INSERT INTO payment_check_queue (topup_id, transaction_code, amount, max_checks)
            VALUES (?, ?, ?, 40)
        ");
        $queueStmt->execute([$topupId, $transactionCode, $amount]);
        
        $pdo->commit();
        
        // Send QR code
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
        
        // Send QR image and get message ID
        $qrResponse = $bot->sendPhoto($chatId, $qrUrl, "Quét mã QR để chuyển khoản");
        $qrMessageId = $qrResponse['result']['message_id'] ?? null;
        
        // Send payment info and get message ID
        $paymentResponse = $bot->sendMessage($chatId, $msg, ['reply_markup' => ['inline_keyboard' => $keyboard]]);
        $paymentMessageId = $paymentResponse['result']['message_id'] ?? null;
        
        // Save message IDs to database
        if ($qrMessageId || $paymentMessageId) {
            $stmt = $pdo->prepare("
                UPDATE wallet_topup_requests 
                SET qr_message_id = ?, payment_message_id = ?
                WHERE id = ?
            ");
            $stmt->execute([$qrMessageId, $paymentMessageId, $topupId]);
        }
        
        // Delete the original message
        if ($messageId) {
            $bot->deleteMessage($chatId, $messageId);
        }
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $bot->editMessage($chatId, $messageId, "❌ Lỗi: " . $e->getMessage());
    }
}

/**
 * Handle /sodu command - Check wallet balance
 */
function handleSoDu($bot, $chatId, $telegramId, $pdo) {
    try {
        // Get user balance
        $stmt = $pdo->prepare("SELECT wallet_balance FROM users WHERE telegram_id = ?");
        $stmt->execute([$telegramId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            // User not found, show 0 balance
            $msg = "💰 <b>SỐ DƯ VÍ</b>\n\n";
            $msg .= "💳 Số dư hiện tại: <b>" . formatVND(0) . "</b>\n\n";
            $msg .= "ℹ️ <i>Bạn chưa có giao dịch nào.</i>\n\n";
            $msg .= "Sử dụng /naptien để nạp tiền vào ví!";
            $bot->sendMessage($chatId, $msg, ['parse_mode' => 'HTML']);
            return;
        }
        
        $balance = floatval($user['wallet_balance']);
        
        // Get recent transactions
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
 * Handle support - Show contact information
 */
function handleSupport($bot, $chatId, $pdo, $messageId = null) {
    try {
        // Get support settings
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


