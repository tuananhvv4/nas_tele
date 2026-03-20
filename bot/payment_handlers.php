<?php
/**
 * Payment Handler Functions
 * Handle wallet, QR, and mixed payments
 */

/**
 * Handle Wallet Payment
 */
function handleWalletPayment($bot, $chatId, $userId, $productId, $quantity, $pdo, $messageId) {
    try {
        
        // Get product and user
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();
        
        $stmt = $pdo->prepare("SELECT id, wallet_balance, username FROM users WHERE telegram_id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$product || !$user) {
            throw new Exception("Không tìm thấy sản phẩm hoặc user!");
        }
        
        $totalPrice = $product['price'] * $quantity;
        $walletBalance = floatval($user['wallet_balance']);
        
        // Check sufficient balance
        if ($walletBalance < $totalPrice) {
            throw new Exception("Số dư ví không đủ! Cần " . formatVND($totalPrice) . ", có " . formatVND($walletBalance));
        }
        
        // Check stock
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM product_accounts WHERE product_id = ? AND is_sold = 0");
        $stmt->execute([$productId]);
        $availableStock = $stmt->fetchColumn();
        
        if ($availableStock < $quantity) {
            throw new Exception("Không đủ hàng trong kho!");
        }
        
        // Deduct from wallet (it handles its own transaction)
        $result = deductFromWallet(
            $user['id'],
            $userId,
            $totalPrice,
            'purchase',
            "Mua {$quantity}x {$product['name']}",
            null,
            $pdo
        );
        
        if (!$result['success']) {
            throw new Exception($result['message']);
        }
        
        // Start transaction for order creation
        $pdo->beginTransaction();
        
        // Create order
        $transactionCode = generateTransactionCode($pdo);
        $stmt = $pdo->prepare("
            INSERT INTO orders (
                user_id, telegram_id, product_id, quantity, price, total_price,
                payment_method, wallet_amount, qr_amount,
                transaction_code, payment_status
            ) VALUES (?, ?, ?, ?, ?, ?, 'wallet', ?, 0, ?, 'completed')
        ");
        $stmt->execute([
            $user['id'],
            $userId,
            $productId,
            $quantity,
            $product['price'],
            $totalPrice,
            $totalPrice,
            $transactionCode
        ]);
        
        $orderId = $pdo->lastInsertId();
        
        // Assign accounts
        $stmt = $pdo->prepare("
            SELECT id, account_data FROM product_accounts 
            WHERE product_id = ? AND is_sold = 0 
            LIMIT ?
        ");
        $stmt->execute([$productId, $quantity]);
        $accounts = $stmt->fetchAll();
        
        foreach ($accounts as $account) {
            $updateStmt = $pdo->prepare("
                UPDATE product_accounts 
                SET is_sold = 1, sold_to_user_id = ?, order_id = ?, sold_at = NOW() 
                WHERE id = ?
            ");
            $updateStmt->execute([$user['id'], $orderId, $account['id']]);
        }

        // Lưu tất cả account IDs vào order (chuỗi: "5,12,18")
        $accountIdsStr = implode(',', array_column($accounts, 'id'));
        $pdo->prepare("UPDATE orders SET account_id = ? WHERE id = ?")->execute([$accountIdsStr, $orderId]);

        $pdo->commit();
        
        // Prepare account data for template
        // $accountsData = [];
        // foreach ($accounts as $account) {
        //     $parts = preg_split('/\s+/', $account['account_data']);
            
        //     $accountData = [
        //         'username' => trim($parts[0] ?? ''),
        //         'password' => trim($parts[1] ?? '')
        //     ];
            
        //     // Add 2FA if exists
        //     if (isset($parts[2]) && !empty(trim($parts[2]))) {
        //         $accountData['twofa'] = trim($parts[2]);
        //     }
            
        //     $accountsData[] = $accountData;
        // }
        
        // Get order data for template
        $orderData = [
            'id' => $orderId,
            'quantity' => $quantity,
            'total_price' => $totalPrice,
            'transaction_code' => $transactionCode,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        // Use DeliveryTemplate to format message
        require_once __DIR__ . '/templates/DeliveryTemplate.php';
        $msg = DeliveryTemplate::renderWalletSuccess($orderData, $product, $accounts, $result['new_balance']);
        
        $bot->editMessage($chatId, $messageId, $msg);

        // Gửi file .txt chứa thông tin tài khoản
        require_once __DIR__ . '/../includes/telegram.php';
        sendAccountFileTelegram($bot, $chatId, $orderId, $product['name'], $quantity, $accounts);

        // Send message to admin
        $msg = "<b>Đã thanh toán cho đơn hàng #{$orderId}</b>";
        $msg .= "\n\n";
        $msg .= "Sản phẩm: " . $product['name'];
        $msg .= "\n";
        $msg .= "Số lượng: " . $quantity;
        $msg .= "\n";
        $msg .= "Tổng tiền: " . formatVND($totalPrice);
        $msg .= "\n";
        $msg .= "Mã giao dịch: " . $transactionCode;
        $msg .= "\n";
        $msg .= "User: " . $user['username'];
        $msg .= "\n";
        $msg .= "Thời gian: " . date('d/m/Y H:i:s');
        $msg .= "\n";
        $bot->sendAdminMessage($msg);

        error_log("Wallet payment completed successfully!");
        
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
