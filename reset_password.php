<?php
/**
 * Reset Admin Password
 * Truy cập file này để reset password admin về 123123
 * Sau khi reset xong, XÓA FILE NÀY đi để bảo mật!
 */

require_once __DIR__ . '/config/db.php';

// Password mới
$newPassword = '123123';

// Tạo hash
$passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);

try {
    // Update password cho user admin
    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE username = 'admin'");
    $stmt->execute([$passwordHash]);
    
    if ($stmt->rowCount() > 0) {
        echo "<!DOCTYPE html>
<html lang='vi'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Reset Password Success</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 500px;
            text-align: center;
        }
        .success-icon {
            font-size: 80px;
            margin-bottom: 20px;
        }
        h1 {
            color: #28a745;
            margin-bottom: 20px;
        }
        .info-box {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            border-left: 4px solid #667eea;
        }
        .credential {
            font-size: 18px;
            font-weight: bold;
            color: #667eea;
            margin: 10px 0;
        }
        .warning {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            border-left: 4px solid #ffc107;
        }
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            margin-top: 20px;
            font-weight: bold;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
    </style>
</head>
<body>
    <div class='container'>
        <div class='success-icon'>✅</div>
        <h1>Password Đã Reset Thành Công!</h1>
        
        <div class='info-box'>
            <p>Thông tin đăng nhập mới:</p>
            <div class='credential'>Username: admin</div>
            <div class='credential'>Password: 123123</div>
        </div>
        
        <div class='warning'>
            <strong>⚠️ QUAN TRỌNG:</strong><br>
            Vui lòng XÓA file <code>reset_password.php</code> ngay sau khi đăng nhập để bảo mật!
        </div>
        
        <a href='admin/login.php' class='btn'>Đăng Nhập Admin</a>
    </div>
</body>
</html>";
    } else {
        echo "<!DOCTYPE html>
<html lang='vi'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Reset Password Error</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 500px;
            text-align: center;
        }
        .error-icon {
            font-size: 80px;
            margin-bottom: 20px;
        }
        h1 {
            color: #dc3545;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class='container'>
        <div class='error-icon'>❌</div>
        <h1>Lỗi!</h1>
        <p>Không tìm thấy user admin trong database.</p>
        <p>Vui lòng kiểm tra lại database.</p>
    </div>
</body>
</html>";
    }
    
} catch (Exception $e) {
    echo "<!DOCTYPE html>
<html lang='vi'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Reset Password Error</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 500px;
            text-align: center;
        }
        .error-icon {
            font-size: 80px;
            margin-bottom: 20px;
        }
        h1 {
            color: #dc3545;
            margin-bottom: 20px;
        }
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class='container'>
        <div class='error-icon'>❌</div>
        <h1>Lỗi Database!</h1>
        <p>Không thể kết nối hoặc cập nhật database.</p>
        <div class='error-message'>
            <strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "
        </div>
    </div>
</body>
</html>";
}
?>
