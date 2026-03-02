<?php
session_start();
require_once 'config/db.php';
require_once 'includes/auth.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

require_once 'includes/sepay.php';

$message = '';
$messageType = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'save_settings') {
            $apiToken = trim($_POST['api_token'] ?? '');
            $accountNumber = trim($_POST['account_number'] ?? '');
            $bankName = trim($_POST['bank_name'] ?? 'MBBank');
            $isEnabled = isset($_POST['is_enabled']) ? 1 : 0;
            
            if (empty($apiToken) || empty($accountNumber)) {
                $message = 'Vui lòng điền đầy đủ thông tin!';
                $messageType = 'error';
            } else {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO sepay_settings (api_token, account_number, bank_name, is_enabled)
                        VALUES (?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE
                        api_token = VALUES(api_token),
                        account_number = VALUES(account_number),
                        bank_name = VALUES(bank_name),
                        is_enabled = VALUES(is_enabled)
                    ");
                    $stmt->execute([$apiToken, $accountNumber, $bankName, $isEnabled]);
                    
                    $message = 'Lưu cài đặt thành công!';
                    $messageType = 'success';
                } catch (PDOException $e) {
                    $message = 'Lỗi: ' . $e->getMessage();
                    $messageType = 'error';
                }
            }
        } elseif ($_POST['action'] === 'test_connection') {
            try {
                $sepay = new SePay($pdo);
                $result = $sepay->testConnection();
                
                $message = $result['message'];
                $messageType = $result['success'] ? 'success' : 'error';
            } catch (Exception $e) {
                $message = 'Lỗi: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
}

// Get current settings
$stmt = $pdo->query("SELECT * FROM sepay_settings ORDER BY id DESC LIMIT 1");
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$settings) {
    $settings = [
        'api_token' => '',
        'account_number' => '',
        'bank_name' => 'MBBank',
        'is_enabled' => 0,
        'last_check_time' => null
    ];
}

include 'includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h2>⚙️ Cài Đặt SePay</h2>
        <p>Cấu hình SePay API để tự động xác minh thanh toán</p>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h3>🔑 Thông Tin API</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <input type="hidden" name="action" value="save_settings">
                
                <div class="form-group">
                    <label for="api_token">API Token <span class="required">*</span></label>
                    <input type="text" 
                           id="api_token" 
                           name="api_token" 
                           class="form-control" 
                           value="<?php echo htmlspecialchars($settings['api_token']); ?>"
                           required>
                    <small class="form-text">Lấy tại: <a href="https://my.sepay.vn" target="_blank">my.sepay.vn → API Access</a></small>
                </div>

                <div class="form-group">
                    <label for="account_number">Số Tài Khoản <span class="required">*</span></label>
                    <input type="text" 
                           id="account_number" 
                           name="account_number" 
                           class="form-control" 
                           value="<?php echo htmlspecialchars($settings['account_number']); ?>"
                           required>
                    <small class="form-text">Số tài khoản ngân hàng đã liên kết với SePay</small>
                </div>

                <div class="form-group">
                    <label for="bank_name">Ngân Hàng</label>
                    <select id="bank_name" name="bank_name" class="form-control">
                        <option value="MBBank" <?php echo $settings['bank_name'] === 'MBBank' ? 'selected' : ''; ?>>MBBank</option>
                        <option value="TPBank" <?php echo $settings['bank_name'] === 'TPBank' ? 'selected' : ''; ?>>TPBank</option>
                        <option value="VietcomBank" <?php echo $settings['bank_name'] === 'VietcomBank' ? 'selected' : ''; ?>>VietcomBank</option>
                        <option value="Techcombank" <?php echo $settings['bank_name'] === 'Techcombank' ? 'selected' : ''; ?>>Techcombank</option>
                        <option value="ACB" <?php echo $settings['bank_name'] === 'ACB' ? 'selected' : ''; ?>>ACB</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" 
                               name="is_enabled" 
                               value="1" 
                               <?php echo $settings['is_enabled'] ? 'checked' : ''; ?>>
                        Bật tự động xác minh thanh toán
                    </label>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">💾 Lưu Cài Đặt</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>🧪 Kiểm Tra Kết Nối</h3>
        </div>
        <div class="card-body">
            <p>Kiểm tra xem API Token có hoạt động không</p>
            <form method="POST" action="">
                <input type="hidden" name="action" value="test_connection">
                <button type="submit" class="btn btn-secondary">🔍 Test Connection</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>📊 Thông Tin</h3>
        </div>
        <div class="card-body">
            <table class="info-table">
                <tr>
                    <td><strong>Trạng thái:</strong></td>
                    <td>
                        <?php if ($settings['is_enabled']): ?>
                            <span class="badge badge-success">✅ Đang hoạt động</span>
                        <?php else: ?>
                            <span class="badge badge-danger">❌ Tắt</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td><strong>Webhook URL:</strong></td>
                    <td>
                        <code><?php echo 'https://' . $_SERVER['HTTP_HOST'] . '/webhook/sepay.php'; ?></code>
                        <button onclick="copyWebhookUrl()" class="btn btn-sm">📋 Copy</button>
                    </td>
                </tr>
                <tr>
                    <td><strong>Lần check cuối:</strong></td>
                    <td><?php echo $settings['last_check_time'] ? date('d/m/Y H:i:s', strtotime($settings['last_check_time'])) : 'Chưa có'; ?></td>
                </tr>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>📖 Hướng Dẫn</h3>
        </div>
        <div class="card-body">
            <h4>Bước 1: Đăng ký SePay</h4>
            <ol>
                <li>Truy cập <a href="https://my.sepay.vn/register" target="_blank">my.sepay.vn/register</a></li>
                <li>Đăng ký tài khoản</li>
                <li>Thêm tài khoản ngân hàng (MBBank, TPBank, etc.)</li>
            </ol>

            <h4>Bước 2: Tạo API Token</h4>
            <ol>
                <li>Vào <a href="https://my.sepay.vn" target="_blank">my.sepay.vn</a> → API Access</li>
                <li>Tạo API Token mới</li>
                <li>Copy token và paste vào form trên</li>
            </ol>

            <h4>Bước 3: Cấu hình Webhook</h4>
            <ol>
                <li>Vào my.sepay.vn → WebHook</li>
                <li>Thêm webhook mới</li>
                <li>URL: <code><?php echo 'https://' . $_SERVER['HTTP_HOST'] . '/webhook/sepay.php'; ?></code></li>
                <li>Sự kiện: <strong>Có tiền vào</strong></li>
                <li>Chứng thực: <strong>Không cần chứng thực</strong></li>
                <li>Request Content Type: <strong>application/json</strong></li>
            </ol>

            <h4>Bước 4: Test</h4>
            <ol>
                <li>Lưu cài đặt</li>
                <li>Click "Test Connection"</li>
                <li>Chuyển khoản thử để test webhook</li>
            </ol>
        </div>
    </div>
</div>

<script>
function copyWebhookUrl() {
    const url = '<?php echo 'https://' . $_SERVER['HTTP_HOST'] . '/webhook/sepay.php'; ?>';
    navigator.clipboard.writeText(url).then(() => {
        alert('Đã copy webhook URL!');
    });
}
</script>

<style>
.info-table {
    width: 100%;
    border-collapse: collapse;
}

.info-table td {
    padding: 10px;
    border-bottom: 1px solid #eee;
}

.info-table td:first-child {
    width: 200px;
}

.badge {
    padding: 5px 10px;
    border-radius: 4px;
    font-size: 12px;
}

.badge-success {
    background: #d4edda;
    color: #155724;
}

.badge-danger {
    background: #f8d7da;
    color: #721c24;
}

code {
    background: #f5f5f500;
    padding: 2px 6px;
    border-radius: 3px;
    font-family: monospace;
}

.btn-sm {
    padding: 2px 8px;
    font-size: 12px;
}

.card {
    margin-bottom: 20px;
}

.card-header h3 {
    margin: 0;
}

h4 {
    margin-top: 20px;
    color: #00f1ff;
}

ol {
    margin-left: 20px;
}

ol li {
    margin-bottom: 8px;
}
</style>

<?php include 'includes/footer.php'; ?>
