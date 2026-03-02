<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Admin' ?> - Bot Shop</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
</head>
<body>
    <div class="wrapper">
        <!-- Mobile Menu Toggle -->
        <button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Toggle Menu">
            <span></span>
            <span></span>
            <span></span>
        </button>
        
        <!-- Sidebar Overlay (for mobile) -->
        <div class="sidebar-overlay" id="sidebarOverlay"></div>
        
        <!-- Sidebar -->
        <nav class="sidebar">
            <div class="sidebar-header">
                <h3>🤖 Bot Shop</h3>
                <p>Admin Panel</p>
            </div>

            <ul>
                <!-- Dashboard -->
                <li class="<?= $pageTitle === 'Dashboard' ? 'active' : '' ?>">
                    <a href="dashboard.php">
                        <span>📊</span> Dashboard
                    </a>
                </li>
                
                <!-- Bot Management -->
                <li class="menu-section">Bot Management</li>
                <li class="<?= $pageTitle === 'Bot Configuration' ? 'active' : '' ?>">
                    <a href="bot-config.php">
                        <span>🤖</span> Cấu Hình Bot
                    </a>
                </li>
                <li class="<?= $pageTitle === 'Bot Customization' ? 'active' : '' ?>">
                    <a href="bot-customization.php">
                        <span>🎨</span> Tùy Chỉnh Bot
                    </a>
                </li>
                
                <!-- Products & Orders -->
                <li class="menu-section">Sản Phẩm & Đơn Hàng</li>
                <li class="<?= $pageTitle === 'Products' ? 'active' : '' ?>">
                    <a href="products">
                        <span>📦</span> Sản Phẩm
                    </a>
                </li>
                <li class="<?= $pageTitle === 'Product Accounts' ? 'active' : '' ?>">
                    <a href="product_accounts">
                        <span>🔑</span> Accounts
                    </a>
                </li>
                <li class="<?= $pageTitle === 'User Management' ? 'active' : '' ?>">
                    <a href="orders.php">
                        <span>📋</span> Đơn Hàng
                    </a>
                </li>
                
                <!-- Users & Promotions -->
                <li class="menu-section">Users & Khuyến Mãi</li>
                <li class="<?= $pageTitle === 'Users' ? 'active' : '' ?>">
                    <a href="users">
                        <span>👥</span> Users
                    </a>
                </li>
                <li class="<?= $pageTitle === 'Guide' ? 'active' : '' ?>">
                    <a href="guide">
                        <span>📚</span> Hướng Dẫn
                    </a>
                </li>
                <li class="<?= $pageTitle === 'Promo Codes' ? 'active' : '' ?>">
                    <a href="promo-codes">
                        <span>🎟️</span> Mã Khuyến Mãi
                    </a>
                </li>
                
                <!-- Communications -->
                <li class="menu-section">Thông Báo</li>
                <li class="<?= $pageTitle === 'Notifications' ? 'active' : '' ?>">
                    <a href="notifications">
                        <span>📢</span> Thông Báo
                    </a>
                </li>
                
                <!-- Settings -->
                <li class="menu-section">Cài Đặt</li>
                <li class="<?= $pageTitle === 'SePay Settings' ? 'active' : '' ?>">
                    <a href="sepay-settings">
                        <span>💳</span> Thanh Toán
                    </a>
                </li>
                <li class="<?= $pageTitle === 'Settings' ? 'active' : '' ?>">
                    <a href="settings">
                        <span>⚙️</span> Cài Đặt
                    </a>
                </li>
            </ul>

            <div class="sidebar-footer">
                <!-- Logout Button -->
                <div style="padding: 10px 10px 15px 10px; border-bottom: 1px solid var(--border-color);">
                    <a href="logout" onclick="return confirm('Bạn có chắc muốn đăng xuất?')" style="display: flex; align-items: center; padding: 12px 18px; color: var(--text-secondary); text-decoration: none; border-radius: 10px; transition: all 0.3s; font-weight: 500; background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3);">
                        <span style="margin-right: 10px; font-size: 1.2rem;">🚪</span> Đăng Xuất
                    </a>
                </div>
                
                <!-- User Info -->
                <div class="user-info">
                    <div class="user-avatar">
                        <?= strtoupper(substr(getAdminUsername(), 0, 1)) ?>
                    </div>
                    <div class="user-details">
                        <div class="user-name"><?= htmlspecialchars(getAdminUsername()) ?></div>
                        <div class="user-role"><?= ucfirst($_SESSION['role']) ?></div>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Page Content -->
        <div id="content">
