<?php
/**
 * API: Cập nhật đơn hàng + gửi thông báo Telegram
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

try {
    // ===================== CẬP NHẬT ĐƠN HÀNG =====================
    if ($action === 'update') {
        $orderId     = (int)($input['order_id'] ?? 0);
        $accountData = trim($input['account_data'] ?? $_POST['account_data'] ?? '');
        $status      = $input['status'] ?? '';
        $price       = $input['price'] ?? null;

        if (!$orderId) {
            echo json_encode(['success' => false, 'message' => 'Thiếu order_id']);
            exit;
        }

        // Lấy đơn hàng hiện tại
        $stmt = $pdo->prepare("
            SELECT o.*, pa.id as pa_id, pa.account_data as old_account_data
            FROM orders o
            LEFT JOIN product_accounts pa ON o.account_id = pa.id
            WHERE o.id = ?
        ");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();

        if (!$order) {
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy đơn hàng']);
            exit;
        }

        // Cập nhật account_data trong product_accounts
        if ($accountData !== '' && $order['pa_id']) {
            $stmt = $pdo->prepare("UPDATE product_accounts SET account_data = ? WHERE id = ?");
            $stmt->execute([$accountData, $order['pa_id']]);
        }

        // Cập nhật status & price trong orders
        $updates = [];
        $params  = [];

        if ($status && in_array($status, ['pending', 'completed', 'cancelled'])) {
            $updates[] = "status = ?";
            $params[]  = $status;
        }

        if ($price !== null && $price !== '') {
            $updates[] = "price = ?";
            $params[]  = (float)$price;
            $updates[] = "total_price = ?";
            $params[]  = (float)$price;
        }

        if (!empty($updates)) {
            $params[] = $orderId;
            $stmt = $pdo->prepare("UPDATE orders SET " . implode(', ', $updates) . " WHERE id = ?");
            $stmt->execute($params);
        }

        echo json_encode(['success' => true, 'message' => 'Đã cập nhật đơn hàng #' . $orderId]);
        exit;
    }

    // ===================== GỬI THÔNG BÁO TELEGRAM =====================
    if ($action === 'notify') {
        $orderId = (int)($input['order_id'] ?? 0);
        $message = trim($input['message'] ?? '');

        if (!$orderId) {
            echo json_encode(['success' => false, 'message' => 'Thiếu order_id']);
            exit;
        }

        // Lấy thông tin đơn hàng
        $stmt = $pdo->prepare("
            SELECT o.*, 
                   p.name as product_name,
                   u.username,
                   u.telegram_id,
                   pa.account_data
            FROM orders o
            LEFT JOIN products p ON o.product_id = p.id
            LEFT JOIN users u ON o.user_id = u.id
            LEFT JOIN product_accounts pa ON o.account_id = pa.id
            WHERE o.id = ?
        ");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();

        if (!$order) {
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy đơn hàng']);
            exit;
        }

        if (empty($order['telegram_id'])) {
            echo json_encode(['success' => false, 'message' => 'Không có Telegram ID cho đơn hàng này']);
            exit;
        }

        // Lấy bot token
        $botStmt = $pdo->query("SELECT bot_token FROM bots WHERE id = 1 LIMIT 1");
        $botData = $botStmt->fetch();

        if (!$botData || empty($botData['bot_token'])) {
            echo json_encode(['success' => false, 'message' => 'Bot token chưa được cấu hình']);
            exit;
        }

        require_once __DIR__ . '/../bot/TelegramBot.php';
        $bot = new TelegramBot($botData['bot_token']);

        // Build message nếu không tùy chỉnh
        if (empty($message)) {
            $message  = "📢 <b>THÔNG BÁO CẬP NHẬT ĐƠN HÀNG</b>\n\n";
            $message .= "📋 <b>Mã đơn:</b> #" . str_pad($order['id'], 6, '0', STR_PAD_LEFT) . "\n";
            $message .= "📦 <b>Sản phẩm:</b> " . ($order['product_name'] ?? 'N/A') . "\n";
            $message .= "💰 <b>Giá:</b> " . number_format($order['price'], 0, ',', '.') . " VNĐ\n\n";

            if (!empty($order['account_data'])) {
                $message .= "🔑 <b>Thông tin tài khoản đã cập nhật:</b>\n";
                $message .= "<code>" . htmlspecialchars($order['account_data']) . "</code>\n\n";
            }

            $message .= "✅ Đơn hàng của bạn đã được cập nhật bởi Admin.\n";
            $message .= "Nếu cần hỗ trợ, vui lòng liên hệ Admin!";
        }

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🛍️ Mua tiếp', 'callback_data' => 'show_products'],
                    ['text' => '📋 Đơn hàng', 'callback_data' => 'my_orders']
                ]
            ]
        ];

        $result = $bot->sendMessage($order['telegram_id'], $message, [
            'parse_mode'   => 'HTML',
            'reply_markup' => json_encode($keyboard),
        ]);

        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Đã gửi thông báo đến Telegram ID: ' . $order['telegram_id']]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Không thể gửi tin nhắn Telegram']);
        }
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Action không hợp lệ']);

} catch (Exception $e) {
    error_log("update-order API error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống: ' . $e->getMessage()]);
}

