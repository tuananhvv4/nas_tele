<?php
/**
 * Payment Handler Functions
 * Handle wallet, QR, and mixed payments
 */

/**
 * Handle Wallet Payment
 *
 * Toàn bộ luồng (kiểm tra số dư → trừ ví → tạo đơn → gán account) nằm trong
 * MỘT transaction duy nhất với SELECT ... FOR UPDATE để tránh race condition
 * khi Telegram retry webhook hoặc user double-click.
 */
function handleWalletPayment($bot, $chatId, $userId, $productId, $quantity, $pdo, $messageId) {
    // Ngay lập tức xoá keyboard và hiện "đang xử lý" để chặn double-click.
    // Nếu message đã bị chỉnh (duplicate callback), Telegram trả lỗi → thoát sớm.
    try {
        $bot->editMessage($chatId, $messageId, "⏳ <b>Đang xử lý thanh toán...</b>\n\nVui lòng chờ trong giây lát.", []);
    } catch (Exception $e) {
        // "message is not modified" hoặc lỗi khác → callback trùng, bỏ qua
        error_log("Wallet payment: duplicate callback ignored for message $messageId");
        return;
    }

    try {
        $pdo->beginTransaction();

        // Lock user row: chặn concurrent transaction đọc balance cũ
        $stmt = $pdo->prepare("SELECT id, username, wallet_balance FROM users WHERE telegram_id = ? FOR UPDATE");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();

        if (!$product || !$user) {
            throw new Exception("Không tìm thấy sản phẩm hoặc user!");
        }

        $totalPrice    = $product['price'] * $quantity;
        $walletBalance = floatval($user['wallet_balance']);

        if ($walletBalance < $totalPrice) {
            throw new Exception("Số dư ví không đủ! Cần " . formatVND($totalPrice) . ", có " . formatVND($walletBalance));
        }

        // Lock + đếm stock để tránh oversell
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM product_accounts WHERE product_id = ? AND is_sold = 0 FOR UPDATE");
        $stmt->execute([$productId]);
        $availableStock = $stmt->fetchColumn();

        if ($availableStock < $quantity) {
            throw new Exception("Không đủ hàng trong kho!");
        }

        // Trừ ví (inline trong cùng transaction, không gọi deductFromWallet riêng)
        $newBalance = $walletBalance - $totalPrice;
        $pdo->prepare("UPDATE users SET wallet_balance = ? WHERE id = ?")->execute([$newBalance, $user['id']]);

        $transactionCode = generateTransactionCode($pdo);

        $pdo->prepare("
            INSERT INTO wallet_transactions
                (user_id, telegram_id, type, amount, balance_before, balance_after, description, reference_id)
            VALUES (?, ?, 'purchase', ?, ?, ?, ?, NULL)
        ")->execute([$user['id'], $userId, -$totalPrice, $walletBalance, $newBalance, "Mua {$quantity}x {$product['name']}"]);

        // Tạo đơn hàng
        $stmt = $pdo->prepare("
            INSERT INTO orders (
                user_id, telegram_id, product_id, quantity, price, total_price,
                payment_method, wallet_amount, qr_amount,
                transaction_code, payment_status
            ) VALUES (?, ?, ?, ?, ?, ?, 'wallet', ?, 0, ?, 'completed')
        ");
        $stmt->execute([
            $user['id'], $userId, $productId, $quantity,
            $product['price'], $totalPrice, $totalPrice, $transactionCode,
        ]);
        $orderId = $pdo->lastInsertId();

        // Gán account với lock để không bị gán trùng
        $stmt = $pdo->prepare("
            SELECT id, account_data FROM product_accounts
            WHERE product_id = ? AND is_sold = 0
            LIMIT ? FOR UPDATE
        ");
        $stmt->execute([$productId, $quantity]);
        $accounts = $stmt->fetchAll();

        if (count($accounts) < $quantity) {
            throw new Exception("Không đủ hàng trong kho (kiểm tra lại)!");
        }

        foreach ($accounts as $account) {
            $pdo->prepare("
                UPDATE product_accounts
                SET is_sold = 1, sold_to_user_id = ?, order_id = ?, sold_at = NOW()
                WHERE id = ?
            ")->execute([$user['id'], $orderId, $account['id']]);
        }

        $accountIdsStr = implode(',', array_column($accounts, 'id'));
        $pdo->prepare("UPDATE orders SET account_id = ? WHERE id = ?")->execute([$accountIdsStr, $orderId]);

        $pdo->commit();

        // ── Gửi tin nhắn cho user ──────────────────────────────────────────────
        $accountsData = [];
        foreach ($accounts as $account) {
            $parts       = preg_split('/\s+/', $account['account_data']);
            $accountData = [
                'username' => trim($parts[0] ?? ''),
                'password' => trim($parts[1] ?? ''),
            ];
            if (!empty(trim($parts[2] ?? ''))) {
                $accountData['twofa'] = trim($parts[2]);
            }
            $accountsData[] = $accountData;
        }

        $orderData = [
            'id'               => $orderId,
            'quantity'         => $quantity,
            'total_price'      => $totalPrice,
            'transaction_code' => $transactionCode,
            'created_at'       => date('Y-m-d H:i:s'),
        ];

        require_once __DIR__ . '/templates/DeliveryTemplate.php';
        $msg = DeliveryTemplate::renderWalletSuccess($orderData, $product, $accountsData, $newBalance);

        $bot->editMessage($chatId, $messageId, $msg);

        require_once __DIR__ . '/../includes/telegram.php';
        sendAccountFileTelegram($bot, $chatId, $orderId, $product['name'], $quantity, $accountsData);

        error_log("Wallet payment completed: Order #$orderId, User $userId");

        // ── Thông báo admin ────────────────────────────────────────────────────
        $adminMsg  = "✅ Đơn hàng mới\n";
        $adminMsg .= "User: "      . ($user['username'] ?? 'Unknown') . "\n";
        $adminMsg .= "Đã mua: "    . $product['name'] . "\n";
        $adminMsg .= "Số lượng: "  . $quantity . "\n";
        $adminMsg .= "Tổng tiền: " . formatVND($totalPrice) . "\n";
        $adminMsg .= "Mã đơn: "   . $orderId . "\n";
        $adminMsg .= "Thời gian: " . date('Y-m-d H:i:s') . "\n";

        sendMessTelegram($adminMsg);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Wallet payment error: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        $bot->editMessage($chatId, $messageId, "❌ Lỗi: " . $e->getMessage());
    }
}

/**
 * Handle QR Payment
 */
function handleQRPayment($bot, $chatId, $userId, $productId, $quantity, $pdo, $messageId) {
    try {
        $pdo->beginTransaction();
        
        // Get product and user
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();
        
        $stmt = $pdo->prepare("SELECT id FROM users WHERE telegram_id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$product || !$user) {
            throw new Exception("Không tìm thấy sản phẩm hoặc user!");
        }
        
        $totalPrice = $product['price'] * $quantity;
        
        // Check stock
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM product_accounts WHERE product_id = ? AND is_sold = 0");
        $stmt->execute([$productId]);
        $availableStock = $stmt->fetchColumn();
        
        if ($availableStock < $quantity) {
            throw new Exception("Không đủ hàng trong kho!");
        }
        
        // Generate transaction code and QR
        $transactionCode = generateTransactionCode($pdo);
        $paymentSettings = $pdo->query("SELECT * FROM payment_settings WHERE id = 1")->fetch();
        
        $qrUrl = generateVietQRUrl(
            $paymentSettings['bank_code'],
            $paymentSettings['account_number'],
            $totalPrice,
            $transactionCode,
            $paymentSettings['account_holder']
        );
        
        // Create order
        $stmt = $pdo->prepare("
            INSERT INTO orders (
                user_id, telegram_id, product_id, quantity, price, total_price,
                payment_method, wallet_amount, qr_amount,
                transaction_code, qr_code_url, payment_status
            ) VALUES (?, ?, ?, ?, ?, ?, 'qr', 0, ?, ?, ?, 'pending')
        ");
        $stmt->execute([
            $user['id'],
            $userId,
            $productId,
            $quantity,
            $product['price'],
            $totalPrice,
            $totalPrice,
            $transactionCode,
            $qrUrl
        ]);
        
        $orderId = $pdo->lastInsertId();
        
        // Add to payment check queue
        $queueStmt = $pdo->prepare("
            INSERT INTO payment_check_queue (order_id, transaction_code, amount, max_checks)
            VALUES (?, ?, ?, 40)
        ");
        $queueStmt->execute([$orderId, $transactionCode, $totalPrice]);
        
        $pdo->commit();
        
        // Send QR code
        handleShowQR($bot, $chatId, $orderId, $pdo, $messageId, true);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $bot->editMessage($chatId, $messageId, "❌ Lỗi: " . $e->getMessage());
    }
}

/**
 * Handle Mixed Payment (Wallet + QR)
 */
function handleMixedPayment($bot, $chatId, $userId, $productId, $quantity, $pdo, $messageId) {
    try {
        $pdo->beginTransaction();
        
        // Get product and user
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();
        
        $stmt = $pdo->prepare("SELECT id, wallet_balance FROM users WHERE telegram_id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$product || !$user) {
            throw new Exception("Không tìm thấy sản phẩm hoặc user!");
        }
        
        $totalPrice = $product['price'] * $quantity;
        $walletBalance = floatval($user['wallet_balance']);
        
        if ($walletBalance <= 0) {
            throw new Exception("Ví không có tiền! Vui lòng chọn thanh toán QR.");
        }
        
        if ($walletBalance >= $totalPrice) {
            throw new Exception("Ví đủ tiền! Vui lòng chọn thanh toán bằng ví.");
        }
        
        // Check stock
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM product_accounts WHERE product_id = ? AND is_sold = 0");
        $stmt->execute([$productId]);
        $availableStock = $stmt->fetchColumn();
        
        if ($availableStock < $quantity) {
            throw new Exception("Không đủ hàng trong kho!");
        }
        
        // Calculate amounts
        $walletAmount = $walletBalance;
        $qrAmount = $totalPrice - $walletBalance;
        
        // Deduct wallet balance
        $result = deductFromWallet(
            $user['id'],
            $userId,
            $walletAmount,
            'purchase',
            "Thanh toán 1 phần: {$quantity}x {$product['name']}",
            null,
            $pdo
        );
        
        if (!$result['success']) {
            throw new Exception($result['message']);
        }
        
        // Generate transaction code and QR for remaining amount
        $transactionCode = generateTransactionCode($pdo);
        $paymentSettings = $pdo->query("SELECT * FROM payment_settings WHERE id = 1")->fetch();
        
        $qrUrl = generateVietQRUrl(
            $paymentSettings['bank_code'],
            $paymentSettings['account_number'],
            $qrAmount,
            $transactionCode,
            $paymentSettings['account_holder']
        );
        
        // Create order
        $stmt = $pdo->prepare("
            INSERT INTO orders (
                user_id, telegram_id, product_id, quantity, price, total_price,
                payment_method, wallet_amount, qr_amount,
                transaction_code, qr_code_url, payment_status
            ) VALUES (?, ?, ?, ?, ?, ?, 'mixed', ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([
            $user['id'],
            $userId,
            $productId,
            $quantity,
            $product['price'],
            $totalPrice,
            $walletAmount,
            $qrAmount,
            $transactionCode,
            $qrUrl
        ]);
        
        $orderId = $pdo->lastInsertId();
        
        // Add to payment check queue (only for QR amount)
        $queueStmt = $pdo->prepare("
            INSERT INTO payment_check_queue (order_id, transaction_code, amount, max_checks)
            VALUES (?, ?, ?, 40)
        ");
        $queueStmt->execute([$orderId, $transactionCode, $qrAmount]);
        
        $pdo->commit();
        
        // Send QR code with mixed payment info
        $msg = "🔀 <b>THANH TOÁN KẾT HỢP</b>\n\n";
        $msg .= "📦 Sản phẩm: <b>" . htmlspecialchars($product['name']) . "</b>\n";
        $msg .= "🔢 Số lượng: <b>$quantity</b>\n";
        $msg .= "💰 Tổng tiền: <b>" . formatVND($totalPrice) . "</b>\n\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━\n";
        $msg .= "✅ Đã trừ ví: <b>" . formatVND($walletAmount) . "</b>\n";
        $msg .= "📱 Cần thanh toán QR: <b>" . formatVND($qrAmount) . "</b>\n";
        $msg .= "💳 Số dư còn lại: <b>" . formatVND($result['new_balance']) . "</b>\n\n";
        $msg .= "Vui lòng quét mã QR bên dưới để hoàn tất thanh toán!";
        
        $bot->editMessage($chatId, $messageId, $msg);
        
        // Send QR code
        handleShowQR($bot, $chatId, $orderId, $pdo, null, true);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $bot->editMessage($chatId, $messageId, "❌ Lỗi: " . $e->getMessage());
    }
}
