<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

const NOTIF_BATCH_SIZE = 25; // Safe under Telegram's 30 msg/sec limit

// ── Table setup ────────────────────────────────────────────────────────────
try {
    $tableExists = $pdo->query("SHOW TABLES LIKE 'notifications'")->fetch();
    if ($tableExists) {
        $hasMessage = $pdo->query("SHOW COLUMNS FROM notifications LIKE 'message'")->fetch();
        if (!$hasMessage) {
            $pdo->exec("DROP TABLE notifications");
            $tableExists = false;
        }
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        title        VARCHAR(255) NOT NULL,
        message      TEXT NOT NULL,
        image_url    VARCHAR(500) NULL,
        type         VARCHAR(50)  DEFAULT 'announcement',
        status       VARCHAR(50)  DEFAULT 'draft',
        total_users  INT          DEFAULT 0,
        sent_count   INT          DEFAULT 0,
        failed_count INT          DEFAULT 0,
        created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
        sent_at      TIMESTAMP    NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Migrate: add columns that may be missing in older installs
    if ($tableExists) {
        $migrations = [
            'image_url'    => 'VARCHAR(500) NULL',
            'total_users'  => 'INT DEFAULT 0',
            'failed_count' => 'INT DEFAULT 0',
        ];
        foreach ($migrations as $col => $def) {
            if (!$pdo->query("SHOW COLUMNS FROM notifications LIKE '$col'")->fetch()) {
                $pdo->exec("ALTER TABLE notifications ADD COLUMN $col $def");
            }
        }
    }
} catch (PDOException $e) { /* continue */ }

// ── Batch-send endpoint — called by JS, returns JSON ──────────────────────
if (isset($_GET['send_batch'])) {
    header('Content-Type: application/json');

    $notifId = intval($_GET['id']     ?? 0);
    $offset  = intval($_GET['offset'] ?? 0);

    try {
        $notifStmt = $pdo->prepare("SELECT * FROM notifications WHERE id = ?");
        $notifStmt->execute([$notifId]);
        $notification = $notifStmt->fetch(PDO::FETCH_ASSOC);

        if (!$notification) {
            echo json_encode(['error' => 'Không tìm thấy thông báo']);
            exit;
        }

        $botConfig = $pdo->query("SELECT * FROM bots WHERE id = 1")->fetch();
        if (!$botConfig || !$botConfig['is_configured']) {
            echo json_encode(['error' => 'Bot chưa được cấu hình']);
            exit;
        }
        $botToken = $botConfig['bot_token'];

        // First batch: record total and mark as sending
        $totalUsers = intval($notification['total_users']);
        if ($offset === 0) {
            $totalUsers = intval($pdo->query(
                "SELECT COUNT(DISTINCT telegram_id) FROM users WHERE telegram_id IS NOT NULL AND telegram_id != ''"
            )->fetchColumn());
            $pdo->prepare("UPDATE notifications SET status = 'sending', total_users = ? WHERE id = ?")
                ->execute([$totalUsers, $notifId]);
        }

        // Fetch this batch (avoid fetchAll for large datasets)
        $stmt = $pdo->prepare("
            SELECT DISTINCT telegram_id FROM users
            WHERE telegram_id IS NOT NULL AND telegram_id != ''
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([NOTIF_BATCH_SIZE, $offset]);
        $users = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Build Telegram message
        $typeEmoji = ['announcement' => '📢', 'promotion' => '🎉', 'update' => '🔔', 'alert' => '⚠️'];
        $emoji     = $typeEmoji[$notification['type']] ?? '📢';
        $msgText   = "{$emoji} *{$notification['title']}*\n\n{$notification['message']}";
        $imgUrl    = !empty($notification['image_url'])
            ? 'https://mrmista.online/' . $notification['image_url']
            : null;

        // Send batch in parallel using cURL multi-handle
        $batchStart  = microtime(true);
        $sentCount   = 0;
        $failedCount = 0;
        $mh          = curl_multi_init();
        $handles     = [];

        foreach ($users as $chatId) {
            if ($imgUrl) {
                $url  = "https://api.telegram.org/bot{$botToken}/sendPhoto";
                $data = ['chat_id' => $chatId, 'photo' => $imgUrl, 'caption' => $msgText, 'parse_mode' => 'Markdown'];
            } else {
                $url  = "https://api.telegram.org/bot{$botToken}/sendMessage";
                $data = ['chat_id' => $chatId, 'text'  => $msgText, 'parse_mode' => 'Markdown'];
            }

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST           => 1,
                CURLOPT_POSTFIELDS     => http_build_query($data),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_TIMEOUT        => 10,
            ]);
            $handles[(int)$ch] = $ch;
            curl_multi_add_handle($mh, $ch);
        }

        // Execute all handles in parallel
        do {
            $status = curl_multi_exec($mh, $active);
            if ($active) curl_multi_select($mh);
        } while ($active && $status == CURLM_OK);

        // Collect results
        foreach ($handles as $ch) {
            $response = json_decode(curl_multi_getcontent($ch), true);
            if ($response && ($response['ok'] ?? false)) {
                $sentCount++;
            } else {
                $failedCount++;
            }
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }
        curl_multi_close($mh);

        // Rate limit guard: ensure we stay under 30 msg/sec
        $elapsed = microtime(true) - $batchStart;
        if ($elapsed < 1.0 && !empty($users)) {
            usleep((int)((1.0 - $elapsed) * 1_000_000));
        }

        // Persist counts
        $pdo->prepare("
            UPDATE notifications SET sent_count = sent_count + ?, failed_count = failed_count + ? WHERE id = ?
        ")->execute([$sentCount, $failedCount, $notifId]);

        $done = count($users) < NOTIF_BATCH_SIZE;
        if ($done) {
            $pdo->prepare("UPDATE notifications SET status = 'sent', sent_at = NOW() WHERE id = ?")
                ->execute([$notifId]);
        }

        // Return updated totals
        $totals = $pdo->prepare("SELECT sent_count, failed_count FROM notifications WHERE id = ?");
        $totals->execute([$notifId]);
        $t = $totals->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'done'         => $done,
            'next_offset'  => $offset + NOTIF_BATCH_SIZE,
            'batch_sent'   => $sentCount,
            'batch_failed' => $failedCount,
            'total_sent'   => intval($t['sent_count']),
            'total_failed' => intval($t['failed_count']),
            'total_users'  => $totalUsers,
        ]);

    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// ── POST handlers ──────────────────────────────────────────────────────────
$isAjax  = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'send_broadcast') {
        $title   = trim($_POST['title']   ?? '');
        $message = trim($_POST['message'] ?? '');
        $type    = $_POST['type']         ?? 'announcement';

        if ($title && $message) {
            try {
                // Image upload
                $imageUrl = null;
                if (isset($_FILES['notification_image']) && $_FILES['notification_image']['error'] === UPLOAD_ERR_OK) {
                    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                    if (
                        in_array($_FILES['notification_image']['type'], $allowedTypes)
                        && $_FILES['notification_image']['size'] <= 5 * 1024 * 1024
                    ) {
                        $uploadDir = __DIR__ . '/uploads/notifications/';
                        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                        $ext      = pathinfo($_FILES['notification_image']['name'], PATHINFO_EXTENSION);
                        $fileName = 'notif_' . time() . '_' . uniqid() . '.' . $ext;
                        if (move_uploaded_file($_FILES['notification_image']['tmp_name'], $uploadDir . $fileName)) {
                            $imageUrl = 'uploads/notifications/' . $fileName;
                        }
                    }
                }

                $botConfig = $pdo->query("SELECT * FROM bots WHERE id = 1")->fetch();
                if (!$botConfig || !$botConfig['is_configured']) {
                    throw new Exception('Bot chưa được cấu hình!');
                }

                $totalUsers = intval($pdo->query(
                    "SELECT COUNT(DISTINCT telegram_id) FROM users WHERE telegram_id IS NOT NULL AND telegram_id != ''"
                )->fetchColumn());

                // Save as 'queued' — JS will handle actual sending via batch endpoint
                $stmt = $pdo->prepare("
                    INSERT INTO notifications (title, message, image_url, type, status, total_users)
                    VALUES (?, ?, ?, ?, 'queued', ?)
                ");
                $stmt->execute([$title, $message, $imageUrl, $type, $totalUsers]);
                $notifId = $pdo->lastInsertId();

                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'id' => (int)$notifId, 'total' => $totalUsers]);
                    exit;
                }
                $success = "Thông báo đã được tạo (ID: #{$notifId})";

            } catch (Exception $e) {
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['error' => $e->getMessage()]);
                    exit;
                }
                $error = 'Lỗi: ' . $e->getMessage();
            }
        } else {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Vui lòng điền đầy đủ thông tin!']);
                exit;
            }
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

// ── Stats ──────────────────────────────────────────────────────────────────
try {
    $totalUsers = $pdo->query(
        "SELECT COUNT(DISTINCT telegram_id) FROM users WHERE telegram_id IS NOT NULL AND telegram_id != ''"
    )->fetchColumn();
} catch (Exception $e) { $totalUsers = 0; }

try {
    $totalNotifications = $pdo->query("SELECT COUNT(*) FROM notifications WHERE status = 'sent'")->fetchColumn();
    $totalSent          = $pdo->query("SELECT COALESCE(SUM(sent_count), 0) FROM notifications")->fetchColumn();
    $notifications      = $pdo->query("SELECT * FROM notifications ORDER BY created_at DESC LIMIT 20")->fetchAll();
} catch (Exception $e) {
    $totalNotifications = 0;
    $totalSent          = 0;
    $notifications      = [];
}

$pageTitle = 'Notifications';
include __DIR__ . '/includes/header.php';
?>
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

    <div class="page-header">
        <h2>📢 Thông Báo Người Dùng</h2>
        <p>Gửi thông báo đến tất cả người dùng qua Telegram Bot</p>
    </div>

    <div id="alertContainer">
        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
    </div>

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
            <form id="notificationForm" enctype="multipart/form-data">

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
                    <button type="submit" id="submitBtn" class="btn btn-primary">
                        📤 Gửi Đến <?= $totalUsers ?> Users
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="resetForm()">🔄 Reset</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Progress Section (shown while sending) -->
    <div id="sendingProgress" class="card mb-4" style="display:none;">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h5 id="progressTitle">📤 Đang Gửi Thông Báo...</h5>
            <span id="progressPct" style="font-weight: 700; color: var(--primary);">0%</span>
        </div>
        <div class="card-body">
            <div style="background: rgba(255,255,255,0.1); border-radius: 20px; height: 14px; overflow: hidden; margin-bottom: 14px;">
                <div id="progressBar" style="width:0%; height:100%; background: linear-gradient(90deg, #667eea, #a78bfa); border-radius: 20px; transition: width 0.4s ease;"></div>
            </div>
            <div style="display: flex; gap: 24px; font-size: 0.88rem;">
                <span>✅ Thành công: <strong id="statSent">0</strong></span>
                <span>❌ Thất bại: <strong id="statFailed">0</strong></span>
                <span>📊 Tổng: <strong id="statTotal">0</strong></span>
                <span id="progressEta" style="color: var(--text-secondary);"></span>
            </div>
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
                                <th>Thành Công</th>
                                <th>Thất Bại</th>
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
                                    <td><strong style="color: #22c55e;"><?= intval($notif['sent_count']) ?></strong></td>
                                    <td>
                                        <?php $fc = intval($notif['failed_count'] ?? 0); ?>
                                        <strong style="color: <?= $fc > 0 ? '#ef4444' : 'var(--text-secondary)' ?>;"><?= $fc ?></strong>
                                    </td>
                                    <td>
                                        <?php
                                        $statusMap = [
                                            'sent'    => '<span class="badge badge-success">✅ Đã Gửi</span>',
                                            'sending' => '<span class="badge badge-warning">⏳ Đang Gửi</span>',
                                            'queued'  => '<span class="badge badge-info">🕐 Hàng Chờ</span>',
                                            'draft'   => '<span class="badge badge-secondary">📝 Nháp</span>',
                                        ];
                                        echo $statusMap[$notif['status']] ?? '<span class="badge badge-secondary">' . htmlspecialchars($notif['status']) . '</span>';
                                        ?>
                                    </td>
                                    <td><?= date('d/m/Y H:i', strtotime($notif['sent_at'] ?? $notif['created_at'])) ?></td>
                                    <td>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Xóa thông báo này?')">
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
        .badge-warning { background: rgba(234, 179, 8, 0.2); color: #eab308; }
        .badge-info    { background: rgba(99, 102, 241, 0.2); color: #818cf8; }
        @media (max-width: 768px) { .col-md-4 { flex: 0 0 100%; } }
    </style>

    <script>
        const TOTAL_USERS = <?= intval($totalUsers) ?>;
        let isSending = false;
        let sendStartTime = 0;

        // ── Type card selection ──────────────────────────────────────────
        document.querySelectorAll('.template-card').forEach(card => {
            card.addEventListener('click', function () {
                document.querySelectorAll('.template-card').forEach(c => c.classList.remove('active'));
                this.classList.add('active');
                this.querySelector('input').checked = true;
                updatePreview();
            });
        });

        // ── Live preview ─────────────────────────────────────────────────
        function updatePreview() {
            const title   = document.getElementById('notif_title').value;
            const message = document.getElementById('notif_message').value;
            const type    = document.querySelector('input[name="type"]:checked').value;
            const emoji   = { announcement: '📢', promotion: '🎉', update: '🔔', alert: '⚠️' };
            const preview = document.getElementById('messagePreview');

            if (title || message) {
                const formatted = message
                    .replace(/\*([^*]+)\*/g, '<strong>$1</strong>')
                    .replace(/_([^_]+)_/g, '<em>$1</em>')
                    .replace(/\n/g, '<br>');
                preview.innerHTML = `
                    <div style="font-size:1.1rem; margin-bottom:6px;">
                        ${emoji[type]} <strong>${title || 'Tiêu đề'}</strong>
                    </div>
                    <div style="color:var(--text-primary); line-height:1.5;">
                        ${formatted || '<span style="color:var(--text-secondary);font-style:italic;">Nội dung...</span>'}
                    </div>`;
            } else {
                preview.innerHTML = '<div style="color:var(--text-secondary);font-style:italic;">Nhập nội dung để xem trước...</div>';
            }
        }
        document.getElementById('notif_title').addEventListener('input', updatePreview);
        document.getElementById('notif_message').addEventListener('input', updatePreview);

        // ── Image preview ────────────────────────────────────────────────
        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = e => {
                    document.getElementById('imagePreview').src = e.target.result;
                    document.getElementById('imagePreviewContainer').style.display = 'block';
                };
                reader.readAsDataURL(input.files[0]);
            }
        }
        function removeImage() {
            document.getElementById('notif_image').value = '';
            document.getElementById('imagePreviewContainer').style.display = 'none';
        }

        function resetForm() {
            document.getElementById('notificationForm').reset();
            document.querySelectorAll('.template-card')[0].click();
            document.getElementById('imagePreviewContainer').style.display = 'none';
            updatePreview();
        }

        // ── AJAX form submit ─────────────────────────────────────────────
        document.getElementById('notificationForm').addEventListener('submit', async function (e) {
            e.preventDefault();
            if (isSending) return;

            const btn      = document.getElementById('submitBtn');
            const formData = new FormData(this);
            formData.append('action', 'send_broadcast');

            if (!formData.get('title') || !formData.get('message')) {
                showAlert('danger', '❌ Vui lòng điền đầy đủ thông tin!');
                return;
            }

            btn.disabled    = true;
            btn.textContent = '⏳ Đang tạo...';

            try {
                const res  = await fetch('notifications.php', {
                    method:  'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body:    formData,
                });
                const data = await res.json();

                if (data.error) {
                    showAlert('danger', '❌ ' + data.error);
                    btn.disabled    = false;
                    btn.textContent = `📤 Gửi Đến ${TOTAL_USERS} Users`;
                    return;
                }

                // Save successful — start batch sending
                document.getElementById('statTotal').textContent = data.total;
                document.getElementById('sendingProgress').style.display = '';
                document.getElementById('sendingProgress').scrollIntoView({ behavior: 'smooth' });

                isSending    = true;
                sendStartTime = Date.now();
                await processBatches(data.id, data.total, 0);

            } catch (err) {
                showAlert('danger', '❌ Lỗi kết nối: ' + err.message);
                btn.disabled    = false;
                btn.textContent = `📤 Gửi Đến ${TOTAL_USERS} Users`;
            }
        });

        // ── Batch processing loop ────────────────────────────────────────
        async function processBatches(notifId, total, offset) {
            try {
                const res  = await fetch(`notifications.php?send_batch=1&id=${notifId}&offset=${offset}`);
                const data = await res.json();

                if (data.error) {
                    showAlert('danger', '❌ ' + data.error);
                    isSending = false;
                    resetSendButton();
                    return;
                }

                // Update progress bar
                const processed = data.total_sent + data.total_failed;
                const pct       = total > 0 ? Math.min(100, Math.round(processed / total * 100)) : 100;

                document.getElementById('progressBar').style.width  = pct + '%';
                document.getElementById('progressPct').textContent  = pct + '%';
                document.getElementById('statSent').textContent     = data.total_sent;
                document.getElementById('statFailed').textContent   = data.total_failed;

                // ETA estimate
                if (processed > 0 && !data.done) {
                    const elapsed   = (Date.now() - sendStartTime) / 1000;
                    const rate      = processed / elapsed;
                    const remaining = Math.max(0, total - processed);
                    const etaSec    = Math.round(remaining / rate);
                    document.getElementById('progressEta').textContent =
                        `⏱ Còn khoảng ${etaSec}s`;
                }

                if (!data.done) {
                    await processBatches(notifId, total, data.next_offset);
                } else {
                    // All done
                    isSending = false;
                    document.getElementById('progressBar').style.background = '#22c55e';
                    document.getElementById('progressTitle').textContent     = '✅ Gửi Hoàn Tất!';
                    document.getElementById('progressEta').textContent       = '';

                    const failMsg = data.total_failed > 0
                        ? ` (${data.total_failed} thất bại — thường do user đã block bot)`
                        : '';
                    showAlert('success', `✅ Đã gửi thành công đến <strong>${data.total_sent}</strong> người dùng!${failMsg}`);

                    resetForm();
                    resetSendButton();

                    // Reload history after 2s
                    setTimeout(() => location.reload(), 2000);
                }

            } catch (err) {
                showAlert('danger', '❌ Lỗi kết nối: ' + err.message);
                isSending = false;
                resetSendButton();
            }
        }

        function resetSendButton() {
            const btn       = document.getElementById('submitBtn');
            btn.disabled    = false;
            btn.textContent = `📤 Gửi Đến ${TOTAL_USERS} Users`;
        }

        function showAlert(type, msg) {
            const container = document.getElementById('alertContainer');
            container.innerHTML = `<div class="alert alert-${type}">${msg}</div>`;
            container.scrollIntoView({ behavior: 'smooth' });
        }
    </script>
</body>
</html>
