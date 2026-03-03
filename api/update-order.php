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
        $orderId      = (int)($input['order_id'] ?? 0);
        $accountData  = trim($input['account_data'] ?? '');
        $status       = $input['status'] ?? '';
        $price        = $input['price'] ?? null;

        if (!$orderId) {
            echo json_encode(['success' => false, 'message' => 'Thiếu order_id']);
            exit;
        }

        // Lấy đơn hàng hiện tại
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();

        if (!$order) {
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy đơn hàng']);
            exit;
        }

        $pdo->beginTransaction();

        try {
            // ---- Cập nhật tất cả accounts ----
            if ($accountData !== '') {
                // Parse từng dòng = 1 account
                $newLines = array_values(array_filter(
                    array_map('trim', explode("\n", $accountData)),
                    fn($l) => $l !== ''
                ));

                // Lấy accounts hiện tại gán cho order này
                $accStmt = $pdo->prepare("
                    SELECT id FROM product_accounts 
                    WHERE order_id = ? 
                    ORDER BY id ASC
                ");
                $accStmt->execute([$orderId]);
                $existingIds = $accStmt->fetchAll(PDO::FETCH_COLUMN);

                $newCount      = count($newLines);
                $existingCount = count($existingIds);

                // Cập nhật accounts đã có
                $updateStmt = $pdo->prepare("UPDATE product_accounts SET account_data = ? WHERE id = ?");
                for ($i = 0; $i < min($newCount, $existingCount); $i++) {
                    $updateStmt->execute([$newLines[$i], $existingIds[$i]]);
                }

                // Nếu có thêm dòng mới → insert thêm product_accounts
                if ($newCount > $existingCount) {
                    $insertStmt = $pdo->prepare("
                        INSERT INTO product_accounts (product_id, account_data, is_sold, sold_at, order_id) 
                        VALUES (?, ?, 1, NOW(), ?)
                    ");
                    for ($i = $existingCount; $i < $newCount; $i++) {
                        $insertStmt->execute([$order['product_id'], $newLines[$i], $orderId]);
                    }
                }

                // Nếu bớt dòng → xoá accounts thừa (giải phóng lại)
                if ($newCount < $existingCount) {
                    $removeIds = array_slice($existingIds, $newCount);
                    $ph = implode(',', array_fill(0, count($removeIds), '?'));
                    $pdo->prepare("
                        UPDATE product_accounts 
                        SET is_sold = 0, order_id = NULL, sold_at = NULL 
                        WHERE id IN ($ph)
                    ")->execute($removeIds);
                }

                // Cập nhật lại account_id trong orders = chuỗi tất cả IDs
                $accStmt->execute([$orderId]);
                $allIds = $accStmt->fetchAll(PDO::FETCH_COLUMN);
                // Nếu có insert mới, cần query lại
                if ($newCount > $existingCount) {
                    $accStmt2 = $pdo->prepare("SELECT id FROM product_accounts WHERE order_id = ? ORDER BY id ASC");
                    $accStmt2->execute([$orderId]);
                    $allIds = $accStmt2->fetchAll(PDO::FETCH_COLUMN);
                }
                $accountIdsStr = implode(',', $allIds);
                $pdo->prepare("UPDATE orders SET account_id = ? WHERE id = ?")->execute([$accountIdsStr, $orderId]);
            }

            // ---- Cập nhật status & price ----
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

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Đã cập nhật đơn hàng #' . $orderId]);

        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
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
                   u.telegram_id
            FROM orders o
            LEFT JOIN products p ON o.product_id = p.id
            LEFT JOIN users u ON o.user_id = u.id
            WHERE o.id = ?
        ");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();

        if (!$order) {
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy đơn hàng']);
            exit;
        }

        // Lấy tất cả accounts theo order_id
        $accStmt = $pdo->prepare("SELECT account_data FROM product_accounts WHERE order_id = ? ORDER BY id ASC");
        $accStmt->execute([$orderId]);
        $allAccounts = $accStmt->fetchAll(PDO::FETCH_COLUMN);

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
            $message .= "🔢 <b>Số lượng:</b> " . ($order['quantity'] ?? 1) . "\n";
            $message .= "💰 <b>Giá:</b> " . number_format($order['price'], 0, ',', '.') . " VNĐ\n\n";

            if (!empty($allAccounts)) {
                $message .= "🔑 <b>Thông tin tài khoản đã cập nhật:</b>\n";
                $message .= "<code>" . htmlspecialchars(implode("\n", $allAccounts)) . "</code>\n\n";
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

