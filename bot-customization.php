<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tùy Chỉnh Bot - Bot Shop</title>
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

    // Create bot_settings table if not exists
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS bot_settings (
            id INT PRIMARY KEY DEFAULT 1,
            bot_name VARCHAR(100) DEFAULT 'Shop Bot',
            welcome_style VARCHAR(50) DEFAULT 'modern',
            welcome_message TEXT,
            show_product_images BOOLEAN DEFAULT TRUE,
            items_per_page INT DEFAULT 5,
            payment_timeout_minutes INT DEFAULT 30,
            currency_symbol VARCHAR(10) DEFAULT 'VNĐ',
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        // Insert default
        $pdo->exec("INSERT IGNORE INTO bot_settings (id) VALUES (1)");
    } catch (PDOException $e) {
        // Table exists
    }

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $botName = trim($_POST['bot_name'] ?? 'Shop Bot');
        $welcomeStyle = $_POST['welcome_style'] ?? 'modern';
        $welcomeMessage = trim($_POST['welcome_message'] ?? '');
        $itemsPerPage = intval($_POST['items_per_page'] ?? 5);
        $paymentTimeout = intval($_POST['payment_timeout'] ?? 30);
        $supportTelegram = trim($_POST['support_telegram'] ?? '');
        $supportZalo = trim($_POST['support_zalo'] ?? '');
        $supportZaloName = trim($_POST['support_zalo_name'] ?? '');

        try {
            $stmt = $pdo->prepare("
                UPDATE bot_settings 
                SET bot_name = ?, 
                    welcome_style = ?, 
                    welcome_message = ?,
                    items_per_page = ?,
                    payment_timeout_minutes = ?,
                    support_telegram = ?,
                    support_zalo = ?,
                    support_zalo_name = ?
                WHERE id = 1
            ");
            
            if ($stmt->execute([$botName, $welcomeStyle, $welcomeMessage, $itemsPerPage, $paymentTimeout, $supportTelegram, $supportZalo, $supportZaloName])) {
                $success = 'Đã lưu cài đặt thành công!';
            }
        } catch (PDOException $e) {
            $error = 'Lỗi: ' . $e->getMessage();
        }
    }

    // Get current settings
    $settings = $pdo->query("SELECT * FROM bot_settings WHERE id = 1")->fetch();

    $pageTitle = 'Bot Customization';
    include __DIR__ . '/includes/header.php';
    ?>

    <div class="page-header">
        <h2>🎨 Tùy Chỉnh Giao Diện Bot</h2>
        <p>Cá nhân hóa trải nghiệm người dùng trên Telegram Bot</p>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <!-- Bot Name -->
        <div class="card mb-4">
            <div class="card-header">
                <h5>🤖 Tên Bot</h5>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label">Tên hiển thị</label>
                    <input type="text" class="form-control" name="bot_name" 
                           value="<?= htmlspecialchars($settings['bot_name']) ?>" 
                           placeholder="Shop Bot" required>
                    <small style="color: var(--text-secondary); margin-top: 6px; display: block;">
                        Tên này sẽ hiển thị trong tin nhắn chào mừng
                    </small>
                </div>
            </div>
        </div>

        <!-- Welcome Style -->
        <div class="card mb-4">
            <div class="card-header">
                <h5>✨ Phong Cách Chào Mừng</h5>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label">Chọn mẫu</label>
                    <div class="welcome-styles">
                        <?php
                        $styles = [
                            'modern' => ['🎨', 'Modern', 'Hiện đại, đầy đủ tính năng'],
                            'minimal' => ['🎯', 'Minimal', 'Tối giản, gọn gàng'],
                            'gradient' => ['🌈', 'Gradient', 'Màu sắc gradient đẹp mắt'],
                            'emoji' => ['😊', 'Emoji', 'Nhiều emoji vui nhộn'],
                            'professional' => ['💼', 'Professional', 'Chuyên nghiệp, lịch sự']
                        ];
                        
                        foreach ($styles as $key => $style):
                            $checked = $settings['welcome_style'] === $key ? 'checked' : '';
                        ?>
                            <label class="style-card <?= $checked ? 'active' : '' ?>">
                                <input type="radio" name="welcome_style" value="<?= $key ?>" <?= $checked ?>>
                                <div class="style-content">
                                    <div class="style-icon"><?= $style[0] ?></div>
                                    <div class="style-name"><?= $style[1] ?></div>
                                    <div class="style-desc"><?= $style[2] ?></div>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Tin nhắn tùy chỉnh (tùy chọn)</label>
                    <textarea class="form-control" name="welcome_message" rows="3" 
                              placeholder="Thêm tin nhắn tùy chỉnh của bạn..."><?= htmlspecialchars($settings['welcome_message'] ?? '') ?></textarea>
                    <small style="color: var(--text-secondary); margin-top: 6px; display: block;">
                        Để trống nếu muốn dùng tin nhắn mặc định
                    </small>
                </div>
            </div>
        </div>

        <!-- Display Settings -->
        <div class="card mb-4">
            <div class="card-header">
                <h5>⚙️ Cài Đặt Hiển Thị</h5>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label">Số sản phẩm mỗi trang</label>
                    <input type="number" class="form-control" name="items_per_page" 
                           value="<?= $settings['items_per_page'] ?>" 
                           min="3" required>
                    <small style="color: var(--text-secondary); margin-top: 6px; display: block;">
                        Khuyến nghị: 5 sản phẩm
                    </small>
                </div>

                <div class="form-group">
                    <label class="form-label">Thời gian thanh toán (phút)</label>
                    <input type="number" class="form-control" name="payment_timeout" 
                           value="<?= $settings['payment_timeout_minutes'] ?>" 
                           min="10" max="60" required>
                    <small style="color: var(--text-secondary); margin-top: 6px; display: block;">
                        Thời gian tối đa để hoàn tất thanh toán
                    </small>
                </div>
            </div>
        </div>

        <!-- Support Settings -->
        <div class="card mb-4">
            <div class="card-header">
                <h5>🆘 Hỗ Trợ Khách Hàng</h5>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label">Telegram Admin</label>
                    <input type="text" class="form-control" name="support_telegram" 
                           value="<?= htmlspecialchars($settings['support_telegram'] ?? '') ?>" 
                           placeholder="@youradmin">
                    <small style="color: var(--text-secondary); margin-top: 6px; display: block;">
                        Username Telegram của admin (bắt đầu bằng @)
                    </small>
                </div>

                <div class="form-group">
                    <label class="form-label">Số Zalo</label>
                    <input type="text" class="form-control" name="support_zalo" 
                           value="<?= htmlspecialchars($settings['support_zalo'] ?? '') ?>" 
                           placeholder="0123456789">
                    <small style="color: var(--text-secondary); margin-top: 6px; display: block;">
                        Số điện thoại Zalo hỗ trợ
                    </small>
                </div>

                <div class="form-group">
                    <label class="form-label">Tên Zalo</label>
                    <input type="text" class="form-control" name="support_zalo_name" 
                           value="<?= htmlspecialchars($settings['support_zalo_name'] ?? '') ?>" 
                           placeholder="Admin Support">
                    <small style="color: var(--text-secondary); margin-top: 6px; display: block;">
                        Tên hiển thị cho Zalo
                    </small>
                </div>
            </div>
        </div>

        <!-- Preview -->
        <div class="card mb-4">
            <div class="card-header">
                <h5>👁️ Xem Trước</h5>
            </div>
            <div class="card-body">
                <div id="preview" class="preview-box">
                    <div class="preview-label">Tin nhắn chào mừng sẽ hiển thị như sau:</div>
                    <div id="preview-content" class="preview-content">
                        <!-- Preview will be rendered here -->
                    </div>
                </div>
            </div>
        </div>

        <!-- Save Button -->
        <div class="card">
            <div class="card-body">
                <button type="submit" class="btn btn-primary">💾 Lưu Cài Đặt</button>
                <a href="dashboard.php" class="btn btn-secondary">◀️ Quay lại</a>
            </div>
        </div>
    </form>

    <?php include __DIR__ . '/includes/footer.php'; ?>

    <style>
        .welcome-styles {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .style-card {
            background: rgba(42, 42, 62, 0.6);
            border: 2px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
        }

        .style-card:hover {
            border-color: var(--primary);
            background: rgba(102, 126, 234, 0.1);
            transform: translateY(-3px);
        }

        .style-card.active {
            border-color: var(--primary);
            background: rgba(102, 126, 234, 0.15);
            box-shadow: 0 8px 24px rgba(102, 126, 234, 0.3);
        }

        .style-card input[type="radio"] {
            display: none;
        }

        .style-icon {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }

        .style-name {
            font-weight: 700;
            font-size: 1.1rem;
            margin-bottom: 5px;
            color: var(--text-primary);
        }

        .style-desc {
            font-size: 0.85rem;
            color: var(--text-secondary);
        }

        .preview-box {
            background: rgba(0, 0, 0, 0.3);
            border-radius: 12px;
            padding: 20px;
        }

        .preview-label {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-bottom: 15px;
        }

        .preview-content {
            background: rgba(42, 42, 62, 0.8);
            border-left: 4px solid var(--primary);
            border-radius: 8px;
            padding: 20px;
            color: var(--text-primary);
            line-height: 1.8;
            font-family: monospace;
            white-space: pre-wrap;
        }
    </style>

    <script>
        // Preview functionality
        const botNameInput = document.querySelector('input[name="bot_name"]');
        const welcomeMessageInput = document.querySelector('textarea[name="welcome_message"]');
        const styleInputs = document.querySelectorAll('input[name="welcome_style"]');
        const previewContent = document.getElementById('preview-content');

        // Style templates
        const templates = {
            modern: (name, msg) => `🤖 Chào mừng đến với ${name}!\n\n${msg || '✨ Hệ thống bán hàng tự động\n⚡ Giao dịch nhanh chóng\n🔒 An toàn & bảo mật\n💎 Giá cả hợp lý'}\n\n━━━━━━━━━━━━━━━━━━━\nChọn chức năng bên dưới để bắt đầu! 👇`,
            
            minimal: (name, msg) => `${name}\n\n${msg || 'Mua hàng tự động 24/7'}\n\nChọn chức năng bên dưới →`,
            
            gradient: (name, msg) => `╔══════════════════╗\n║  ${name}  ║\n╚══════════════════╝\n\n${msg || '🎁 Sản phẩm chất lượng\n💎 Giá cả hợp lý\n🚀 Giao hàng tức thì\n🔐 Bảo mật tuyệt đối'}\n\n━━━━━━━━━━━━━━━━━━━\nBắt đầu mua sắm ngay! 🛍️`,
            
            emoji: (name, msg) => `👋 Xin chào!\n\n🎉 Welcome to ${name}\n${msg || '💫 Nơi mua sắm tin cậy'}\n\n🌟 Tại sao chọn chúng tôi?\n✅ Uy tín hàng đầu\n✅ Giao hàng nhanh\n✅ Hỗ trợ 24/7\n✅ Giá tốt nhất\n\n🛒 Bắt đầu mua sắm thôi! 😊`,
            
            professional: (name, msg) => `━━━━━━━━━━━━━━━━━━━\n${name}\nProfessional Account Store\n━━━━━━━━━━━━━━━━━━━\n\n${msg || ''}\n📊 Services:\n  • Premium Accounts\n  • Instant Delivery\n  • 24/7 Support\n  • Warranty Included\n\n━━━━━━━━━━━━━━━━━━━\nSelect an option below to continue.`
        };

        function updatePreview() {
            const botName = botNameInput.value || 'Shop Bot';
            const customMsg = welcomeMessageInput.value;
            const selectedStyle = document.querySelector('input[name="welcome_style"]:checked').value;
            
            previewContent.textContent = templates[selectedStyle](botName, customMsg);
        }

        // Event listeners
        botNameInput.addEventListener('input', updatePreview);
        welcomeMessageInput.addEventListener('input', updatePreview);
        styleInputs.forEach(input => {
            input.addEventListener('change', () => {
                // Update active class
                document.querySelectorAll('.style-card').forEach(card => {
                    card.classList.remove('active');
                });
                input.closest('.style-card').classList.add('active');
                
                updatePreview();
            });
        });

        // Initial preview
        updatePreview();
    </script>
</body>
</html>
