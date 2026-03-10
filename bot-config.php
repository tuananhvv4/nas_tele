<?php
// define('IN_SITE', true);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/libs/db.php';
requireLogin();

    $dbHelper = new DB();

    $success = '';
    $error = '';
    $botInfo = null;

    // Handle bot configuration
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        if ($action === 'save_bot') {
            $botToken = trim($_POST['bot_token'] ?? '');
            $botName = trim($_POST['bot_name'] ?? 'Bot Shop');

            if ($botToken) {
                // Test bot token
                $apiUrl = "https://api.telegram.org/bot{$botToken}/getMe";
                $response = @file_get_contents($apiUrl);
                $result = json_decode($response, true);

                if ($result && $result['ok']) {
                    $botUsername = $result['result']['username'];
                    $botId = $result['result']['id'];

                    // Save to database
                    $stmt = $pdo->prepare("
                        UPDATE bots 
                        SET bot_token = ?, bot_name = ?, bot_username = ?, is_configured = 1, status = 'active'
                        WHERE id = 1
                    ");
                    
                    if ($stmt->execute([$botToken, $botName, $botUsername])) {
                        $success = 'Đã lưu cấu hình bot thành công!';
                        $botInfo = $result['result'];
                    } else {
                        $error = 'Lưu cấu hình thất bại!';
                    }
                } else {
                    $error = 'Bot token không hợp lệ! Vui lòng kiểm tra lại.';
                }
            } else {
                $error = 'Vui lòng nhập bot token!';
            }
        } elseif ($action === 'set_webhook') {
            $bot = $pdo->query("SELECT * FROM bots WHERE id = 1")->fetch();
            
            if ($bot && $bot['bot_token']) {
                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'];
                $webhookUrl = $protocol . '://' . $host . '/bot/webhook.php';

                $apiUrl = "https://api.telegram.org/bot{$bot['bot_token']}/setWebhook?url=" . urlencode($webhookUrl);
                $response = @file_get_contents($apiUrl);
                $result = json_decode($response, true);

                if ($result && $result['ok']) {
                    // Update webhook URL in database
                    $stmt = $pdo->prepare("UPDATE bots SET webhook_url = ? WHERE id = 1");
                    $stmt->execute([$webhookUrl]);
                    
                    $success = 'Đã thiết lập webhook thành công!';
                } else {
                    $error = 'Thiết lập webhook thất bại! ' . 'data: ' . json_encode($result);
                }
            } else {
                $error = 'Vui lòng cấu hình bot token trước!';
            }
        }
    }

    // Get current bot config
    $bot = $pdo->query("SELECT * FROM bots WHERE id = 1")->fetch();

    // Get webhook info if bot is configured
    if ($bot && $bot['bot_token'] && $bot['is_configured']) {
        $apiUrl = "https://api.telegram.org/bot{$bot['bot_token']}/getWebhookInfo";
        $response = @file_get_contents($apiUrl);
        $webhookInfo = json_decode($response, true);
    }

$pageTitle = 'Bot Configuration';
include __DIR__ . '/includes/header.php';
?>

    <div class="page-header">
        <h2>🤖 Cấu Hình Bot Telegram</h2>
        <p>Thiết lập và quản lý bot bán hàng tự động</p>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- Bot Configuration Form -->
    <div class="card mb-4">
        <div class="card-header">
            <h5>⚙️ Cấu Hình Bot</h5>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="save_bot">
                
                <div class="form-group">
                    <label class="form-label">Tên Bot</label>
                    <input type="text" class="form-control" name="bot_name" 
                           value="<?= htmlspecialchars($bot['bot_name'] ?? 'Bot Shop') ?>" 
                           placeholder="Bot Shop">
                </div>

                <div class="form-group">
                    <label class="form-label">Bot Token</label>
                    <input type="text" class="form-control" name="bot_token" 
                           value="<?= htmlspecialchars($bot['bot_token'] ?? '') ?>" 
                           placeholder="123456789:ABCdefGHIjklMNOpqrsTUVwxyz" required>
                    <small style="color: var(--text-secondary);">
                        Lấy token từ <a href="https://t.me/BotFather" target="_blank" style="color: var(--primary);">@BotFather</a>
                    </small>
                </div>

                <button type="submit" class="btn btn-primary">💾 Lưu Cấu Hình</button>
            </form>
        </div>
    </div>

    <?php if ($bot && $bot['is_configured']): ?>
        <!-- Bot Status -->
        <div class="card mb-4">
            <div class="card-header">
                <h5>📊 Trạng Thái Bot</h5>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="stat-card">
                            <div class="stat-icon">🤖</div>
                            <div class="stat-value">@<?= htmlspecialchars($bot['bot_username']) ?></div>
                            <div class="stat-label">Bot Username</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <?= $bot['status'] === 'active' ? '✅' : '❌' ?>
                            </div>
                            <div class="stat-value"><?= ucfirst($bot['status']) ?></div>
                            <div class="stat-label">Trạng Thái</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Webhook Configuration -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5>🔗 Webhook</h5>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="action" value="set_webhook">
                    <button type="submit" class="btn btn-success btn-sm">Thiết Lập Webhook</button>
                </form>
            </div>
            <div class="card-body">
                <?php if (isset($webhookInfo) && $webhookInfo['ok']): ?>
                    <div class="form-group">
                        <label class="form-label">Webhook URL</label>
                        <input type="text" class="form-control" 
                               value="<?= htmlspecialchars($webhookInfo['result']['url'] ?? 'Chưa thiết lập') ?>" 
                               readonly>
                    </div>
                    
                    <?php if (!empty($webhookInfo['result']['url'])): ?>
                        <div class="alert alert-success">
                            ✅ Webhook đã được thiết lập thành công!
                            <br><small>Cập nhật lần cuối: <?= date('d/m/Y H:i:s', $webhookInfo['result']['last_error_date'] ?? time()) ?></small>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            ⚠️ Webhook chưa được thiết lập. Vui lòng click "Thiết Lập Webhook" ở trên.
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Guide -->
        <div class="card">
            <div class="card-header">
                <h5>📖 Hướng Dẫn Sử Dụng</h5>
            </div>
            <div class="card-body">
                <h6 style="margin-bottom: 15px;">Các lệnh bot:</h6>
                <ul style="color: var(--text-secondary); line-height: 2;">
                    <li><code style="color: var(--primary);">/start</code> - Hiển thị menu chính</li>
                    <li><code style="color: var(--primary);">/mua</code> - Xem danh sách sản phẩm</li>
                    <li><code style="color: var(--primary);">Đơn hàng của tôi</code> - Xem lịch sử mua hàng</li>
                </ul>

                <h6 style="margin: 20px 0 15px;">Link bot:</h6>
                <div class="form-group">
                    <input type="text" class="form-control" 
                           value="https://t.me/<?= htmlspecialchars($bot['bot_username']) ?>" 
                           readonly onclick="this.select()">
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- Setup Guide -->
        <div class="card">
            <div class="card-header">
                <h5>📖 Hướng Dẫn Thiết Lập</h5>
            </div>
            <div class="card-body">
                <h6 style="margin-bottom: 15px;">Các bước thiết lập bot:</h6>
                <ol style="color: var(--text-secondary); line-height: 2;">
                    <li>Mở Telegram và tìm <a href="https://t.me/BotFather" target="_blank" style="color: var(--primary);">@BotFather</a></li>
                    <li>Gửi lệnh <code style="color: var(--primary);">/newbot</code></li>
                    <li>Làm theo hướng dẫn để tạo bot mới</li>
                    <li>Copy <strong>Bot Token</strong> mà BotFather gửi cho bạn</li>
                    <li>Paste token vào ô "Bot Token" ở trên</li>
                    <li>Click "Lưu Cấu Hình"</li>
                    <li>Sau đó click "Thiết Lập Webhook"</li>
                </ol>
            </div>
        </div>
    <?php endif; ?>

    <?php include __DIR__ . '/includes/footer.php'; ?>

    <style>
        .row {
            display: flex;
            flex-wrap: wrap;
            margin: -10px;
        }
        .col-md-6 {
            flex: 0 0 50%;
            padding: 10px;
        }
        .g-4 {
            margin: -10px;
        }
        .mb-4 {
            margin-bottom: 25px;
        }
        code {
            background: rgba(102, 126, 234, 0.1);
            padding: 2px 6px;
            border-radius: 4px;
            color: var(--primary);
        }
        @media (max-width: 768px) {
            .col-md-6 {
                flex: 0 0 100%;
        }
    }
    </style>
