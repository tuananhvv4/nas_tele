<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thông Báo - Bot Shop</title>
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

    // Create notifications table (drop old one if incompatible)
    try {
        // Check if table exists and has correct structure
        $tableExists = $pdo->query("SHOW TABLES LIKE 'notifications'")->fetch();
        
        if ($tableExists) {
            // Check if message column exists
            $hasMessage = $pdo->query("SHOW COLUMNS FROM notifications LIKE 'message'")->fetch();
            if (!$hasMessage) {
                // Old incompatible table, drop it
                $pdo->exec("DROP TABLE notifications");
            }
        }
        
        // Create table with correct structure
        $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            type VARCHAR(50) DEFAULT 'announcement',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            sent_at TIMESTAMP NULL,
            sent_count INT DEFAULT 0,
            status VARCHAR(50) DEFAULT 'draft'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
    } catch (PDOException $e) {
        // Continue anyway
    }

    // Handle broadcast
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action === 'send_broadcast') {
            $title = trim($_POST['title'] ?? '');
            $message = trim($_POST['message'] ?? '');
            $type = $_POST['type'] ?? 'announcement';
            
            if ($title && $message) {
                try {
                    // Handle image upload
                    $imageUrl = null;
                    if (isset($_FILES['notification_image']) && $_FILES['notification_image']['error'] === UPLOAD_ERR_OK) {
                        $uploadDir = __DIR__ . '/uploads/notifications/';
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0755, true);
                        }
                        
                        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                        $fileType = $_FILES['notification_image']['type'];
                        $fileSize = $_FILES['notification_image']['size'];
                        
                        if (in_array($fileType, $allowedTypes) && $fileSize <= 5 * 1024 * 1024) { // Max 5MB
                            $fileExt = pathinfo($_FILES['notification_image']['name'], PATHINFO_EXTENSION);
                            $fileName = 'notif_' . time() . '_' . uniqid() . '.' . $fileExt;
                            $filePath = $uploadDir . $fileName;
                            
                            if (move_uploaded_file($_FILES['notification_image']['tmp_name'], $filePath)) {
                                $imageUrl = 'uploads/notifications/' . $fileName;
                            }
                        }
                    }
                    
                    // Save notification
                    $stmt = $pdo->prepare("INSERT INTO notifications (title, message, image_url, type, status) VALUES (?, ?, ?, ?, 'draft')");
                    $stmt->execute([$title, $message, $imageUrl, $type]);
                    $notificationId = $pdo->lastInsertId();
                    
                    // Get bot config
                    $botConfig = $pdo->query("SELECT * FROM bots WHERE id = 1")->fetch();
                    
                    if ($botConfig && $botConfig['is_configured']) {
                        $botToken = $botConfig['bot_token'];
                        
                        // Get all users with telegram_id
                        $stmt = $pdo->query("SELECT DISTINCT telegram_id FROM users WHERE telegram_id IS NOT NULL AND telegram_id != ''");
                        $users = $stmt->fetchAll();
                        
                        $sentCount = 0;
                        $failedCount = 0;
                        
                        // Format message
                        $emoji = [
                            'announcement' => '📢',
                            'promotion' => '🎉',
                            'update' => '🔔',
                            'alert' => '⚠️'
                        ];
                        
                        $formattedMessage = ($emoji[$type] ?? '📢') . " *{$title}*\n\n{$message}";
                        
                        // Get full image URL if exists
                        $fullImageUrl = null;
                        if ($imageUrl) {
                            $fullImageUrl = 'https://mrmista.online/' . $imageUrl;
                        }
                        
                        foreach ($users as $user) {
                            try {
                                $chatId = $user['telegram_id'];
                                
                                if ($fullImageUrl) {
                                    // Send with image using sendPhoto
                                    $url = "https://api.telegram.org/bot{$botToken}/sendPhoto";
                                    $data = [
                                        'chat_id' => $chatId,
                                        'photo' => $fullImageUrl,
                                        'caption' => $formattedMessage,
                                        'parse_mode' => 'Markdown'
                                    ];
                                } else {
                                    // Send text-only using sendMessage
                                    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
                                    $data = [
                                        'chat_id' => $chatId,
                                        'text' => $formattedMessage,
                                        'parse_mode' => 'Markdown'
                                    ];
                                }
                                
                                $ch = curl_init($url);
                                curl_setopt($ch, CURLOPT_POST, 1);
                                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                                $result = curl_exec($ch);
                                curl_close($ch);
                                
                                $response = json_decode($result, true);
                                if ($response && isset($response['ok']) && $response['ok']) {
                                    $sentCount++;
                                } else {
                                    $failedCount++;
                                }
                                
                                usleep(50000); // 50ms delay
                            } catch (Exception $e) {
                                $failedCount++;
                            }
                        }
                        
                        // Update notification
                        $stmt = $pdo->prepare("UPDATE notifications SET sent_at = NOW(), sent_count = ?, status = 'sent' WHERE id = ?");
                        $stmt->execute([$sentCount, $notificationId]);
                        
                        $success = "Đã gửi thành công đến {$sentCount} người dùng!" . ($failedCount > 0 ? " ({$failedCount} thất bại)" : "");
                    } else {
                        $error = 'Bot chưa được cấu hình!';
                    }
                } catch (Exception $e) {
                    $error = 'Lỗi: ' . $e->getMessage();
                }
            } else {
                $error = 'Vui lòng điền đầy đủ thông tin!';
            }
        } elseif ($action === 'delete') {
            $id = intval($_POST['id'] ?? 0);
            if ($id) {
                $pdo->prepare("DELETE FROM notifications WHERE id = ?")->execute([$id]);
                $success = 'Đã xóa thông báo!';
            }
        }
    }

    // Get stats
    try {
        $totalUsers = $pdo->query("SELECT COUNT(DISTINCT telegram_id) FROM users WHERE telegram_id IS NOT NULL AND telegram_id != ''")->fetchColumn();
    } catch (Exception $e) {
        $totalUsers = 0;
    }
    
    try {
        $totalNotifications = $pdo->query("SELECT COUNT(*) FROM notifications WHERE status = 'sent'")->fetchColumn();
        $totalSent = $pdo->query("SELECT COALESCE(SUM(sent_count), 0) FROM notifications")->fetchColumn();
        $notifications = $pdo->query("SELECT * FROM notifications ORDER BY created_at DESC LIMIT 20")->fetchAll();
    } catch (Exception $e) {
        $totalNotifications = 0;
        $totalSent = 0;
        $notifications = [];
    }

    $pageTitle = 'Notifications';
    include __DIR__ . '/includes/header.php';
    ?>

    <div class="page-header">
        <h2>📢 Thông Báo Người Dùng</h2>
        <p>Gửi thông báo đến tất cả người dùng qua Telegram Bot</p>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-value"><?= $totalUsers ?></div>
                <div class="stat-label">Tổng Users</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon">📨</div>
                <div class="stat-value"><?= $totalNotifications ?></div>
                <div class="stat-label">Thông Báo Đã Gửi</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon">✉️</div>
                <div class="stat-value"><?= $totalSent ?></div>
                <div class="stat-label">Tin Nhắn Đã Gửi</div>
            </div>
        </div>
    </div>

    <!-- Create Notification -->
    <div class="card mb-4">
        <div class="card-header">
            <h5>✍️ Tạo Thông Báo Mới</h5>
        </div>
        <div class="card-body">
            <form method="POST" id="notificationForm" enctype="multipart/form-data">
                <input type="hidden" name="action" value="send_broadcast">
                
                <div class="form-group">
                    <label class="form-label">Loại Thông Báo</label>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 10px; margin-bottom: 20px;">
                        <label class="template-card active">
                            <input type="radio" name="type" value="announcement" checked style="display: none;">
                            <div class="template-content">
                                <div style="font-size: 1.8rem;">📢</div>
                                <div style="font-weight: 600; margin-top: 6px; font-size: 0.9rem;">Thông Báo</div>
                            </div>
                        </label>
                        <label class="template-card">
                            <input type="radio" name="type" value="promotion" style="display: none;">
                            <div class="template-content">
                                <div style="font-size: 1.8rem;">🎉</div>
                                <div style="font-weight: 600; margin-top: 6px; font-size: 0.9rem;">Khuyến Mãi</div>
                            </div>
                        </label>
                        <label class="template-card">
                            <input type="radio" name="type" value="update" style="display: none;">
                            <div class="template-content">
                                <div style="font-size: 1.8rem;">🔔</div>
                                <div style="font-weight: 600; margin-top: 6px; font-size: 0.9rem;">Cập Nhật</div>
                            </div>
                        </label>
                        <label class="template-card">
                            <input type="radio" name="type" value="alert" style="display: none;">
                            <div class="template-content">
                                <div style="font-size: 1.8rem;">⚠️</div>
                                <div style="font-weight: 600; margin-top: 6px; font-size: 0.9rem;">Cảnh Báo</div>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Tiêu Đề *</label>
                    <input type="text" class="form-control" name="title" id="notif_title" required placeholder="VD: Khuyến mãi đặc biệt!">
                </div>

                <div class="form-group">
                    <label class="form-label">Nội Dung *</label>
                    <textarea class="form-control" name="message" id="notif_message" rows="5" required placeholder="Nhập nội dung thông báo..."></textarea>
                    <small style="color: var(--text-secondary); margin-top: 6px; display: block; font-size: 0.85rem;">
                        💡 Hỗ trợ Markdown: <code>*đậm*</code>, <code>_nghiêng_</code>
                    </small>
                </div>

                <div class="form-group">
                    <label class="form-label">🖼️ Hình Ảnh (Tùy chọn)</label>
                    <input type="file" name="notification_image" id="notif_image" accept="image/*" class="form-control" onchange="previewImage(this)">
                    <small style="color: var(--text-secondary); margin-top: 6px; display: block; font-size: 0.85rem;">
                        📎 Hỗ trợ: JPG, PNG, GIF, WebP (Max 5MB)
                    </small>
                    <div id="imagePreviewContainer" style="display:none; margin-top:12px;">
                        <img id="imagePreview" style="max-width:100%; max-height:300px; border-radius:8px; border: 2px solid var(--primary);">
                        <button type="button" onclick="removeImage()" class="btn btn-danger btn-sm" style="margin-top:8px;">🗑️ Xóa ảnh</button>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">👁️ Xem Trước</label>
                    <div id="messagePreview" style="background: rgba(102, 126, 234, 0.1); border-left: 3px solid var(--primary); padding: 14px; border-radius: 8px; min-height: 70px; font-size: 0.9rem;">
                        <div style="color: var(--text-secondary); font-style: italic;">Nhập nội dung để xem trước...</div>
                    </div>
                </div>

                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-primary" onclick="return confirm('Gửi thông báo đến <?= $totalUsers ?> người dùng?')">
                        📤 Gửi Đến <?= $totalUsers ?> Users
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="resetForm()">🔄 Reset</button>
                </div>
            </form>
        </div>
    </div>

    <!-- History -->
    <div class="card">
        <div class="card-header">
            <h5>📋 Lịch Sử Thông Báo</h5>
        </div>
        <div class="card-body">
            <?php if (empty($notifications)): ?>
                <p style="color: var(--text-secondary); text-align: center; padding: 30px;">Chưa có thông báo nào</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Loại</th>
                                <th>Tiêu Đề</th>
                                <th>Đã Gửi</th>
                                <th>Trạng Thái</th>
                                <th>Thời Gian</th>
                                <th>Thao Tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($notifications as $notif): ?>
                                <tr>
                                    <td>
                                        <?php
                                        $typeEmoji = ['announcement' => '📢', 'promotion' => '🎉', 'update' => '🔔', 'alert' => '⚠️'];
                                        echo $typeEmoji[$notif['type']] ?? '📢';
                                        ?>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($notif['title']) ?></strong>
                                        <?php if (!empty($notif['image_url'])): ?>
                                            <span style="color: var(--primary); margin-left: 5px;" title="Có hình ảnh">🖼️</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong><?= $notif['sent_count'] ?></strong> users</td>
                                    <td>
                                        <?php if ($notif['status'] === 'sent'): ?>
                                            <span class="badge badge-success">✅ Đã Gửi</span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary">📝 Nháp</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('d/m/Y H:i', strtotime($notif['sent_at'] ?? $notif['created_at'])) ?></td>
                                    <td>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Xóa?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $notif['id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">🗑️</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php include __DIR__ . '/includes/footer.php'; ?>

    <style>
        .template-card {
            background: rgba(42, 42, 62, 0.6);
            border: 2px solid transparent;
            border-radius: 10px;
            padding: 16px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        .template-card:hover, .template-card.active {
            border-color: var(--primary);
            background: rgba(102, 126, 234, 0.15);
        }
        .row { display: flex; flex-wrap: wrap; margin: -10px; }
        .col-md-4 { flex: 0 0 33.333%; padding: 10px; }
        @media (max-width: 768px) { .col-md-4 { flex: 0 0 100%; } }
    </style>

    <script>
        document.querySelectorAll('.template-card').forEach(card => {
            card.addEventListener('click', function() {
                document.querySelectorAll('.template-card').forEach(c => c.classList.remove('active'));
                this.classList.add('active');
                this.querySelector('input').checked = true;
                updatePreview();
            });
        });

        function updatePreview() {
            const title = document.getElementById('notif_title').value;
            const message = document.getElementById('notif_message').value;
            const type = document.querySelector('input[name="type"]:checked').value;
            const emoji = {'announcement': '📢', 'promotion': '🎉', 'update': '🔔', 'alert': '⚠️'};
            const preview = document.getElementById('messagePreview');
            
            if (title || message) {
                let formatted = message
                    .replace(/\*([^*]+)\*/g, '<strong>$1</strong>')
                    .replace(/_([^_]+)_/g, '<em>$1</em>')
                    .replace(/\n/g, '<br>');
                
                preview.innerHTML = `
                    <div style="font-size: 1.1rem; margin-bottom: 6px;">
                        ${emoji[type]} <strong>${title || 'Tiêu đề'}</strong>
                    </div>
                    <div style="color: var(--text-primary); line-height: 1.5;">
                        ${formatted || '<span style="color: var(--text-secondary); font-style: italic;">Nội dung...</span>'}
                    </div>
                `;
            } else {
                preview.innerHTML = '<div style="color: var(--text-secondary); font-style: italic;">Nhập nội dung để xem trước...</div>';
            }
        }

        function resetForm() {
            document.getElementById('notificationForm').reset();
            document.querySelectorAll('.template-card')[0].click();
        }

        document.getElementById('notif_title').addEventListener('input', updatePreview);
        document.getElementById('notif_message').addEventListener('input', updatePreview);
        
        function previewImage(input) {
            const preview = document.getElementById('imagePreview');
            const container = document.getElementById('imagePreviewContainer');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    container.style.display = 'block';
                };
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        function removeImage() {
            document.getElementById('notif_image').value = '';
            document.getElementById('imagePreviewContainer').style.display = 'none';
        }
    </script>
</body>
</html>
