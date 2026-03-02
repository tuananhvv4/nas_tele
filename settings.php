<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cài Đặt - Bot Shop</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php
    require_once __DIR__ . '/config/db.php';
    require_once __DIR__ . '/includes/auth.php';
    requireLogin();

    $success = '';
    $error = '';

    // Create tables if not exist
    try {
        // Payment settings table
        $pdo->exec("CREATE TABLE IF NOT EXISTS payment_settings (
            id INT PRIMARY KEY DEFAULT 1,
            bank_code VARCHAR(20) NOT NULL DEFAULT 'MB',
            bank_name VARCHAR(100) NOT NULL DEFAULT 'MB Bank',
            account_number VARCHAR(50) NOT NULL DEFAULT '0123456789',
            account_holder VARCHAR(255) NOT NULL DEFAULT 'NGUYEN VAN A',
            transaction_prefix VARCHAR(20) DEFAULT 'ORDER',
            order_timeout_minutes INT DEFAULT 10,
            currency VARCHAR(10) DEFAULT 'VND',
            usd_to_vnd_rate DECIMAL(10,2) DEFAULT 25000.00,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        // Insert default if not exists
        $pdo->exec("INSERT IGNORE INTO payment_settings (id, account_number, account_holder) VALUES (1, '0123456789', 'NGUYEN VAN A')");
        
        // Maintenance mode table
        $pdo->exec("CREATE TABLE IF NOT EXISTS maintenance_mode (
            id INT PRIMARY KEY DEFAULT 1,
            is_enabled BOOLEAN DEFAULT FALSE,
            start_time DATETIME NULL,
            end_time DATETIME NULL,
            message TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        // Insert default if not exists
        $pdo->exec("INSERT IGNORE INTO maintenance_mode (id, is_enabled, message) VALUES (1, FALSE, 'Bot đang bảo trì. Vui lòng quay lại sau!')");
        
    } catch (PDOException $e) {
        // Continue if tables exist
    }

    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'update_payment') {
            $bankCode = trim($_POST['bank_code'] ?? 'MB');
            $bankName = trim($_POST['bank_name'] ?? 'MB Bank');
            $accountNumber = trim($_POST['account_number'] ?? '');
            $accountHolder = trim($_POST['account_holder'] ?? '');
            $transactionPrefix = strtoupper(trim($_POST['transaction_prefix'] ?? 'ORDER'));
            $orderTimeout = intval($_POST['order_timeout_minutes'] ?? 10);
            $usdRate = floatval($_POST['usd_to_vnd_rate'] ?? 25000);
            
            if ($accountNumber && $accountHolder) {
                $stmt = $pdo->prepare("UPDATE payment_settings SET 
                    bank_code = ?, bank_name = ?, account_number = ?, 
                    account_holder = ?, transaction_prefix = ?, order_timeout_minutes = ?, usd_to_vnd_rate = ?
                    WHERE id = 1");
                if ($stmt->execute([$bankCode, $bankName, $accountNumber, $accountHolder, $transactionPrefix, $orderTimeout, $usdRate])) {
                    $success = 'Đã cập nhật cài đặt thanh toán!';
                }
            } else {
                $error = 'Vui lòng điền đầy đủ thông tin!';
            }
        } elseif ($action === 'update_maintenance') {
            $isEnabled = isset($_POST['is_enabled']) ? 1 : 0;
            $startTime = $_POST['start_time'] ?? null;
            $endTime = $_POST['end_time'] ?? null;
            $message = trim($_POST['message'] ?? '');
            
            // Check if we're enabling maintenance
            $currentStatus = $pdo->query("SELECT is_enabled FROM maintenance_mode WHERE id = 1")->fetchColumn();
            
            $stmt = $pdo->prepare("UPDATE maintenance_mode SET 
                is_enabled = ?, start_time = ?, end_time = ?, message = ?
                WHERE id = 1");
            $stmt->execute([$isEnabled, $startTime, $endTime, $message]);
            
            // Send broadcast notifications
            if ($isEnabled && !$currentStatus) {
                // Just enabled - send maintenance notification
                $notifMsg = "🔧 *Thông Báo Bảo Trì*\n\n";
                $notifMsg .= "Bot sẽ tạm ngưng hoạt động để bảo trì.\n\n";
                if ($startTime && $endTime) {
                    $notifMsg .= "⏰ Từ: " . date('H:i d/m/Y', strtotime($startTime)) . "\n";
                    $notifMsg .= "⏰ Đến: " . date('H:i d/m/Y', strtotime($endTime)) . "\n\n";
                }
                $notifMsg .= "Vui lòng quay lại sau. Xin cảm ơn! 🙏";
                broadcastMessage($pdo, $notifMsg);
                $success = 'Đã bật chế độ bảo trì và gửi thông báo!';
            } elseif (!$isEnabled && $currentStatus) {
                // Just disabled - send success notification
                $notifMsg = "✅ *Bot Đã Hoạt Động Trở Lại!*\n\n";
                $notifMsg .= "Bot đã được cập nhật và hoạt động trở lại.\n\n";
                $notifMsg .= "Cảm ơn bạn đã kiên nhẫn chờ đợi! 🎉";
                broadcastMessage($pdo, $notifMsg);
                $success = 'Đã tắt chế độ bảo trì và gửi thông báo!';
            } else {
                $success = 'Đã cập nhật cài đặt bảo trì!';
            }
        }
    }

    // Get current settings
    $paymentSettings = $pdo->query("SELECT * FROM payment_settings WHERE id = 1")->fetch();
    $maintenanceSettings = $pdo->query("SELECT * FROM maintenance_mode WHERE id = 1")->fetch();

    // Broadcast helper function
    function broadcastMessage($pdo, $message) {
        try {
            $botConfig = $pdo->query("SELECT * FROM bots WHERE id = 1")->fetch();
            if ($botConfig && $botConfig['is_configured']) {
                $botToken = $botConfig['bot_token'];
                $users = $pdo->query("SELECT DISTINCT telegram_id FROM users WHERE telegram_id IS NOT NULL AND telegram_id != ''")->fetchAll();
                
                foreach ($users as $user) {
                    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
                    $data = [
                        'chat_id' => $user['telegram_id'],
                        'text' => $message,
                        'parse_mode' => 'Markdown'
                    ];
                    
                    $ch = curl_init($url);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_exec($ch);
                    curl_close($ch);
                    usleep(50000);
                }
            }
        } catch (Exception $e) {
            // Continue
        }
    }

    $pageTitle = 'Settings';
    include __DIR__ . '/includes/header.php';
    ?>

    <div class="page-header">
        <h2>⚙️ Cài Đặt Hệ Thống</h2>
        <p>Cấu hình thanh toán và bảo trì bot</p>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Payment Settings -->
    <div class="card mb-4">
        <div class="card-header">
            <h5>💳 Cài Đặt Thanh Toán</h5>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="update_payment">
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Ngân Hàng *</label>
                            <select class="form-control" name="bank_code" id="bank_code" required onchange="updateBankName()">
                                <option value="MB" <?= $paymentSettings['bank_code'] === 'MB' ? 'selected' : '' ?>>MB Bank (MBBank)</option>
                                <option value="VCB" <?= $paymentSettings['bank_code'] === 'VCB' ? 'selected' : '' ?>>Vietcombank</option>
                                <option value="TCB" <?= $paymentSettings['bank_code'] === 'TCB' ? 'selected' : '' ?>>Techcombank</option>
                                <option value="VTB" <?= $paymentSettings['bank_code'] === 'VTB' ? 'selected' : '' ?>>VietinBank</option>
                                <option value="ACB" <?= $paymentSettings['bank_code'] === 'ACB' ? 'selected' : '' ?>>ACB</option>
                                <option value="BIDV" <?= $paymentSettings['bank_code'] === 'BIDV' ? 'selected' : '' ?>>BIDV</option>
                                <option value="TPB" <?= $paymentSettings['bank_code'] === 'TPB' ? 'selected' : '' ?>>TPBank</option>
                                <option value="VPB" <?= $paymentSettings['bank_code'] === 'VPB' ? 'selected' : '' ?>>VPBank</option>
                            </select>
                            <input type="hidden" name="bank_name" id="bank_name" value="<?= htmlspecialchars($paymentSettings['bank_name']) ?>">
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Số Tài Khoản *</label>
                            <input type="text" class="form-control" name="account_number" value="<?= htmlspecialchars($paymentSettings['account_number']) ?>" required>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Tên Chủ Tài Khoản *</label>
                    <input type="text" class="form-control" name="account_holder" value="<?= htmlspecialchars($paymentSettings['account_holder']) ?>" required placeholder="NGUYEN VAN A">
                </div>

                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="form-label">Tiền Tố Mã Giao Dịch *</label>
                            <input type="text" class="form-control" name="transaction_prefix" value="<?= htmlspecialchars($paymentSettings['transaction_prefix']) ?>" required placeholder="QUOCCHEAI" maxlength="20" style="text-transform: uppercase;">
                            <small style="color: var(--text-secondary); margin-top: 6px; display: block;">
                                VD: QUOCCHEAI → Mã GD: QUOCCHEAI12345678
                            </small>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="form-label">⏱️ Timeout Đơn Hàng (phút) *</label>
                            <input type="number" class="form-control" name="order_timeout_minutes" value="<?= $paymentSettings['order_timeout_minutes'] ?? 10 ?>" required min="1" max="60">
                            <small style="color: var(--text-secondary); margin-top: 6px; display: block;">
                                Thời gian hết hạn đơn hàng
                            </small>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="form-label">Tỷ Giá USD → VNĐ</label>
                            <input type="number" class="form-control" name="usd_to_vnd_rate" value="<?= $paymentSettings['usd_to_vnd_rate'] ?>" step="100" min="1000">
                            <small style="color: var(--text-secondary); margin-top: 6px; display: block;">
                                Giá hiện tại trong DB sẽ được nhân với tỷ giá này
                            </small>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">💾 Lưu Cài Đặt</button>
            </form>
        </div>
    </div>

    <!-- Maintenance Mode -->
    <div class="card">
        <div class="card-header">
            <h5>🔧 Chế Độ Bảo Trì</h5>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="update_maintenance">
                
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                        <input type="checkbox" name="is_enabled" value="1" <?= $maintenanceSettings['is_enabled'] ? 'checked' : '' ?> style="width: 20px; height: 20px;">
                        <span style="font-weight: 600; font-size: 1.1rem;">Bật Chế Độ Bảo Trì</span>
                    </label>
                    <small style="color: var(--text-secondary); margin-top: 6px; display: block;">
                        Khi bật, bot sẽ ngừng hoạt động và gửi thông báo bảo trì đến tất cả users
                    </small>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Thời Gian Bắt Đầu</label>
                            <input type="datetime-local" class="form-control" name="start_time" value="<?= $maintenanceSettings['start_time'] ? date('Y-m-d\TH:i', strtotime($maintenanceSettings['start_time'])) : '' ?>">
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Thời Gian Kết Thúc</label>
                            <input type="datetime-local" class="form-control" name="end_time" value="<?= $maintenanceSettings['end_time'] ? date('Y-m-d\TH:i', strtotime($maintenanceSettings['end_time'])) : '' ?>">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Thông Báo Tùy Chỉnh</label>
                    <textarea class="form-control" name="message" rows="3" placeholder="Bot đang bảo trì. Vui lòng quay lại sau!"><?= htmlspecialchars($maintenanceSettings['message']) ?></textarea>
                </div>

                <button type="submit" class="btn btn-warning">🔧 Cập Nhật Bảo Trì</button>
            </form>
        </div>
    </div>

    <?php include __DIR__ . '/includes/footer.php'; ?>

    <style>
        .row { display: flex; flex-wrap: wrap; margin: -10px; }
        .col-md-6 { flex: 0 0 50%; padding: 10px; }
        @media (max-width: 768px) { .col-md-6 { flex: 0 0 100%; } }
    </style>

    <script>
        const bankNames = {
            'MB': 'MB Bank',
            'VCB': 'Vietcombank',
            'TCB': 'Techcombank',
            'VTB': 'VietinBank',
            'ACB': 'ACB',
            'BIDV': 'BIDV',
            'TPB': 'TPBank',
            'VPB': 'VPBank'
        };

        function updateBankName() {
            const bankCode = document.getElementById('bank_code').value;
            document.getElementById('bank_name').value = bankNames[bankCode] || bankCode;
        }
    </script>
</body>
</html>
