<?php
/**
 * Auth Page - Login/Register with Output Buffering
 */

// Start output buffering to prevent any output before redirect
ob_start();

// Start session FIRST
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/db.php';

$message = '';
$messageType = '';

// Handle Login
if (isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND is_active = 1");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                // Regenerate session ID for security
                session_regenerate_id(true);
                
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['package'] = $user['package'];

                // Clear output buffer before redirect
                ob_end_clean();
                
                // Redirect to dashboard (no more admin folder)
                header('Location: dashboard.php', true, 302);
                exit();
            } else {
                $message = 'Tên đăng nhập hoặc mật khẩu không đúng!';
                $messageType = 'error';
            }
        } catch (Exception $e) {
            $message = 'Lỗi hệ thống! Vui lòng thử lại.';
            $messageType = 'error';
            error_log("Login error: " . $e->getMessage());
        }
    } else {
        $message = 'Vui lòng điền đầy đủ thông tin!';
        $messageType = 'error';
    }
}

// Handle Register
if (isset($_POST['register'])) {
    $username = trim($_POST['reg_username'] ?? '');
    $email = trim($_POST['reg_email'] ?? '');
    $password = $_POST['reg_password'] ?? '';
    $confirm_password = $_POST['reg_confirm_password'] ?? '';

    if ($username && $email && $password && $confirm_password) {
        if ($password !== $confirm_password) {
            $message = 'Mật khẩu xác nhận không khớp!';
            $messageType = 'error';
        } elseif (strlen($password) < 6) {
            $message = 'Mật khẩu phải có ít nhất 6 ký tự!';
            $messageType = 'error';
        } else {
            // Check if username or email exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            
            if ($stmt->fetch()) {
                $message = 'Tên đăng nhập hoặc email đã tồn tại!';
                $messageType = 'error';
            } else {
                $passwordHash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, role, package) VALUES (?, ?, ?, 'user', 'free')");
                
                if ($stmt->execute([$username, $email, $passwordHash])) {
                    $message = 'Đăng ký thành công! Vui lòng đăng nhập.';
                    $messageType = 'success';
                } else {
                    $message = 'Đăng ký thất bại! Vui lòng thử lại.';
                    $messageType = 'error';
                }
            }
        }
    } else {
        $message = 'Vui lòng điền đầy đủ thông tin!';
        $messageType = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng Nhập / Đăng Ký - Bot Shop</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0f0c29 0%, #302b63 50%, #24243e 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: rgba(30, 30, 46, 0.95);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            overflow: hidden;
            max-width: 900px;
            width: 100%;
            display: flex;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .left-panel {
            flex: 1;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 60px 40px;
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .left-panel h1 {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 20px;
        }

        .left-panel p {
            font-size: 1.1rem;
            opacity: 0.9;
            line-height: 1.6;
        }

        .feature-list {
            margin-top: 30px;
        }

        .feature-item {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            font-size: 1rem;
        }

        .feature-item::before {
            content: '✓';
            background: rgba(255, 255, 255, 0.2);
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-weight: bold;
        }

        .right-panel {
            flex: 1;
            padding: 60px 40px;
            background: #1e1e2e;
        }

        .form-container {
            max-width: 400px;
        }

        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
        }

        .tab {
            flex: 1;
            padding: 12px;
            background: rgba(255, 255, 255, 0.05);
            border: 2px solid transparent;
            border-radius: 10px;
            color: #8b8b9a;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
        }

        .tab.active {
            background: rgba(102, 126, 234, 0.1);
            border-color: #667eea;
            color: #667eea;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            color: #b4b4c6;
            font-weight: 500;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }

        input {
            width: 100%;
            padding: 14px 18px;
            background: rgba(255, 255, 255, 0.05);
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            color: white;
            font-size: 1rem;
            transition: all 0.3s;
        }

        input:focus {
            outline: none;
            border-color: #667eea;
            background: rgba(255, 255, 255, 0.08);
        }

        input::placeholder {
            color: #6b6b7a;
        }

        .btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            color: white;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }

        .alert {
            padding: 12px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }

        .alert-success {
            background: rgba(40, 167, 69, 0.2);
            border: 1px solid rgba(40, 167, 69, 0.5);
            color: #4ade80;
        }

        .alert-error {
            background: rgba(220, 53, 69, 0.2);
            border: 1px solid rgba(220, 53, 69, 0.5);
            color: #f87171;
        }

        .form-content {
            display: none;
        }

        .form-content.active {
            display: block;
        }

        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }

            .left-panel {
                padding: 40px 30px;
            }

            .left-panel h1 {
                font-size: 2rem;
            }

            .right-panel {
                padding: 40px 30px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="left-panel">
            <h1>🤖 Bot Shop</h1>
            <p>Hệ thống bán account tự động qua Telegram Bot</p>
            
            <div class="feature-list">
                <div class="feature-item">Giao hàng tức thì</div>
                <div class="feature-item">An toàn & bảo mật</div>
                <div class="feature-item">Giá cả hợp lý</div>
                <div class="feature-item">Hỗ trợ 24/7</div>
            </div>
        </div>

        <div class="right-panel">
            <div class="form-container">
                <div class="tabs">
                    <div class="tab active" onclick="switchTab('login')">Đăng Nhập</div>
                    <div class="tab" onclick="switchTab('register')">Đăng Ký</div>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?= $messageType ?>">
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                <!-- Login Form -->
                <div class="form-content active" id="login-form">
                    <form method="POST">
                        <div class="form-group">
                            <label>Tên đăng nhập hoặc Email</label>
                            <input type="text" name="username" placeholder="Nhập tên đăng nhập hoặc email" required>
                        </div>
                        <div class="form-group">
                            <label>Mật khẩu</label>
                            <input type="password" name="password" placeholder="Nhập mật khẩu" required>
                        </div>
                        <button type="submit" name="login" class="btn">Đăng Nhập</button>
                    </form>
                </div>

                <!-- Register Form -->
                <div class="form-content" id="register-form">
                    <form method="POST">
                        <div class="form-group">
                            <label>Tên đăng nhập</label>
                            <input type="text" name="reg_username" placeholder="Chọn tên đăng nhập" required>
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="reg_email" placeholder="Nhập email của bạn" required>
                        </div>
                        <div class="form-group">
                            <label>Mật khẩu</label>
                            <input type="password" name="reg_password" placeholder="Tạo mật khẩu (tối thiểu 6 ký tự)" required>
                        </div>
                        <div class="form-group">
                            <label>Xác nhận mật khẩu</label>
                            <input type="password" name="reg_confirm_password" placeholder="Nhập lại mật khẩu" required>
                        </div>
                        <button type="submit" name="register" class="btn">Đăng Ký</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function switchTab(tab) {
            // Update tabs
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            event.target.classList.add('active');

            // Update forms
            document.querySelectorAll('.form-content').forEach(f => f.classList.remove('active'));
            document.getElementById(tab + '-form').classList.add('active');
        }
    </script>
</body>
</html>
