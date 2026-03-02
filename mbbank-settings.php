<?php
require_once 'includes/auth.php';
requireLogin();

// Only admin can access
if ($_SESSION['role'] !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

require_once 'config/db.php';
require_once 'includes/encryption.php';

$pdo = getDB();
$success = '';
$error = '';

// Check for test result in session
$testResult = $_SESSION['test_result'] ?? null;
if ($testResult) {
    unset($_SESSION['test_result']); // Clear after displaying
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_settings') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $accountNumber = trim($_POST['account_number'] ?? '');
        $serviceUrl = trim($_POST['service_url'] ?? 'https://mbbank-payment-service.onrender.com');
        $isEnabled = isset($_POST['is_enabled']) ? 1 : 0;
        
        if (empty($username) || empty($password) || empty($accountNumber)) {
            $error = 'Vui lòng điền đầy đủ thông tin!';
        } else {
            try {
                // Encrypt password
                $passwordEncrypted = encryptPassword($password);
                
                // Check if settings exist
                $stmt = $pdo->query("SELECT id FROM mbbank_settings LIMIT 1");
                $existing = $stmt->fetch();
                
                if ($existing) {
                    // Update
                    $stmt = $pdo->prepare("
                        UPDATE mbbank_settings 
                        SET username = ?, password_encrypted = ?, account_number = ?, 
                            service_url = ?, is_enabled = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$username, $passwordEncrypted, $accountNumber, $serviceUrl, $isEnabled, $existing['id']]);
                } else {
                    // Insert
                    $stmt = $pdo->prepare("
                        INSERT INTO mbbank_settings (username, password_encrypted, account_number, service_url, is_enabled)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$username, $passwordEncrypted, $accountNumber, $serviceUrl, $isEnabled]);
                }
                
                $success = 'Đã lưu cấu hình MBBank thành công!';
            } catch (Exception $e) {
                $error = 'Lỗi: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'test_connection') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $serviceUrl = trim($_POST['service_url'] ?? '');
        
        // Debug log
        error_log("Test Connection - Username: $username, Service URL: $serviceUrl");
        
        if (empty($username) || empty($password) || empty($serviceUrl)) {
            $testResult = ['success' => false, 'message' => 'Vui lòng điền đầy đủ thông tin!'];
        } else {
            // Test connection to service
            $ch = curl_init($serviceUrl . '/test-connection');
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                'username' => $username,
                'password' => $password
            ]));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            // Debug log
            error_log("Test Connection Response - HTTP Code: $httpCode, Response: $response, Error: $curlError");
            
            if ($httpCode === 200 && $response) {
                $result = json_decode($response, true);
                $testResult = $result;
            } elseif ($curlError) {
                $testResult = ['success' => false, 'message' => 'Lỗi cURL: ' . $curlError];
            } else {
                $testResult = ['success' => false, 'message' => "HTTP Error $httpCode: Không thể kết nối đến service!"];
            }
            
            // Debug log result
            error_log("Test Result: " . json_encode($testResult));
            
            // Save to session and redirect
            $_SESSION['test_result'] = $testResult;
            header('Location: mbbank-settings.php');
            exit;
        }
    }
}

// Load current settings
$stmt = $pdo->query("SELECT * FROM mbbank_settings LIMIT 1");
$settings = $stmt->fetch();

$pageTitle = 'MBBank Settings';
require_once 'includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>🏦 Cấu Hình MBBank Auto-Verification</h1>
        <p>Cấu hình thông tin tài khoản MBBank để tự động xác minh thanh toán</p>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($testResult): ?>
        <div class="alert alert-<?= $testResult['success'] ? 'success' : 'danger' ?>">
            <?= $testResult['success'] ? '✅' : '❌' ?> 
            <?= htmlspecialchars($testResult['message'] ?? 'Unknown error') ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h2>⚙️ Thông Tin Tài Khoản MBBank</h2>
        </div>
        <div class="card-body">
            <form method="POST" id="settingsForm">
                <input type="hidden" name="action" value="save_settings">
                
                <div class="form-group">
                    <label for="username">Số điện thoại đăng ký MB:</label>
                    <input type="text" 
                           class="form-control" 
                           id="username" 
                           name="username" 
                           value="<?= htmlspecialchars($settings['username'] ?? '') ?>"
                           placeholder="0123456789"
                           required>
                    <small class="form-text">Số điện thoại bạn dùng để đăng nhập MB Bank</small>
                </div>

                <div class="form-group">
                    <label for="password">Mật khẩu MB Bank:</label>
                    <div style="position: relative;">
                        <input type="password" 
                               class="form-control" 
                               id="password" 
                               name="password" 
                               placeholder="••••••••"
                               required
                               style="padding-right: 40px;">
                        <button type="button" 
                                onclick="togglePassword()" 
                                style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; font-size: 1.2rem;">
                            👁️
                        </button>
                    </div>
                    <small class="form-text">Mật khẩu sẽ được mã hóa trước khi lưu</small>
                </div>

                <div class="form-group">
                    <label for="account_number">Số tài khoản:</label>
                    <input type="text" 
                           class="form-control" 
                           id="account_number" 
                           name="account_number" 
                           value="<?= htmlspecialchars($settings['account_number'] ?? '') ?>"
                           placeholder="1234567890"
                           required>
                    <small class="form-text">Số tài khoản MB để check giao dịch</small>
                </div>

                <div class="form-group">
                    <label for="service_url">Service URL:</label>
                    <input type="url" 
                           class="form-control" 
                           id="service_url" 
                           name="service_url" 
                           value="<?= htmlspecialchars($settings['service_url'] ?? 'https://mbbank-payment-service.onrender.com') ?>"
                           required>
                    <small class="form-text">URL của Node.js service trên Render.com</small>
                </div>

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" 
                               name="is_enabled" 
                               <?= ($settings['is_enabled'] ?? 0) ? 'checked' : '' ?>>
                        <span>✅ Bật tự động verify thanh toán</span>
                    </label>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="testConnection()">
                        🔍 Test Connection
                    </button>
                    <button type="submit" class="btn btn-primary">
                        💾 Lưu Cấu Hình
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($settings && $settings['last_check_at']): ?>
    <div class="card" style="margin-top: 20px;">
        <div class="card-header">
            <h2>📊 Trạng Thái</h2>
        </div>
        <div class="card-body">
            <p><strong>Last check:</strong> <?= date('d/m/Y H:i:s', strtotime($settings['last_check_at'])) ?></p>
            <p><strong>Status:</strong> 
                <span class="badge badge-<?= $settings['last_check_status'] === 'success' ? 'success' : 'danger' ?>">
                    <?= $settings['last_check_status'] === 'success' ? '✅ Hoạt động bình thường' : '❌ Có lỗi' ?>
                </span>
            </p>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
function togglePassword() {
    const passwordInput = document.getElementById('password');
    passwordInput.type = passwordInput.type === 'password' ? 'text' : 'password';
}

function testConnection() {
    const username = document.getElementById('username').value;
    const password = document.getElementById('password').value;
    const serviceUrl = document.getElementById('service_url').value;
    
    if (!username || !password || !serviceUrl) {
        alert('Vui lòng điền đầy đủ thông tin!');
        return;
    }
    
    const btn = event.target;
    const originalText = btn.textContent;
    btn.disabled = true;
    btn.textContent = '⏳ Testing...';
    
    // Create form data
    const formData = new FormData();
    formData.append('action', 'test_connection');
    formData.append('username', username);
    formData.append('password', password);
    formData.append('service_url', serviceUrl);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(() => {
        // Reload to show result
        window.location.reload();
    })
    .catch(error => {
        alert('Lỗi kết nối: ' + error.message);
        btn.disabled = false;
        btn.textContent = originalText;
    });
}
</script>

<style>
.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
    color: var(--text-primary);
}

.form-control {
    width: 100%;
    padding: 10px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.05);
    color: var(--text-primary);
    font-size: 0.95rem;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary);
    background: rgba(255, 255, 255, 0.08);
}

.form-text {
    display: block;
    margin-top: 5px;
    font-size: 0.85rem;
    color: var(--text-secondary);
}

.checkbox-label {
    display: flex;
    align-items: center;
    cursor: pointer;
}

.checkbox-label input[type="checkbox"] {
    margin-right: 10px;
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.form-actions {
    display: flex;
    gap: 10px;
    margin-top: 30px;
}

.alert {
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.alert-success {
    background: rgba(76, 175, 80, 0.1);
    border: 1px solid rgba(76, 175, 80, 0.3);
    color: #4CAF50;
}

.alert-danger {
    background: rgba(244, 67, 54, 0.1);
    border: 1px solid rgba(244, 67, 54, 0.3);
    color: #f44336;
}

.badge {
    padding: 5px 10px;
    border-radius: 4px;
    font-size: 0.85rem;
}

.badge-success {
    background: rgba(76, 175, 80, 0.2);
    color: #4CAF50;
}

.badge-danger {
    background: rgba(244, 67, 54, 0.2);
    color: #f44336;
}
</style>

<?php require_once 'includes/footer.php'; ?>
