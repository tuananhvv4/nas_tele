<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Bot Shop Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php
    require_once __DIR__ . '/config/db.php';
    require_once __DIR__ . '/includes/auth.php';
    requireLogin();

    // Get statistics
    $stats = [
        'total_users' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn(),
        'total_products' => $pdo->query("SELECT COUNT(*) FROM products WHERE status = 'active'")->fetchColumn(),
        'total_accounts' => $pdo->query("SELECT COUNT(*) FROM product_accounts WHERE is_sold = 0")->fetchColumn(),
        'total_orders' => $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn(),
        'total_revenue' => $pdo->query("SELECT COALESCE(SUM(price), 0) FROM orders")->fetchColumn(),
    ];

    // Get recent orders
    $recentOrders = $pdo->query("
        SELECT o.*, p.name as product_name, u.username 
        FROM orders o
        LEFT JOIN products p ON o.product_id = p.id
        LEFT JOIN users u ON o.user_id = u.id
        ORDER BY o.created_at DESC
        LIMIT 10
    ")->fetchAll();

    // Get bot status
    $bot = $pdo->query("SELECT * FROM bots WHERE id = 1")->fetch();

    $pageTitle = 'Dashboard';
    include __DIR__ . '/includes/header.php';
    ?>

    <div class="page-header">
        <h2>📊 Dashboard</h2>
        <p>Tổng quan hệ thống</p>
    </div>

    <!-- Stats Grid -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <a href="/users">
                <div class="stat-card">
                    <div class="stat-icon">👥</div>
                    <div class="stat-value"><?= $stats['total_users'] ?></div>
                    <div class="stat-label">Người Dùng</div>
                </div>
            </a>
        </div>
        <div class="col-md-3">
            <a href="/products">
                <div class="stat-card">
                    <div class="stat-icon">📦</div>
                    <div class="stat-value"><?= $stats['total_products'] ?></div>
                    <div class="stat-label">Sản Phẩm</div>
                </div>
            </a>
        </div>
        <div class="col-md-3">
            <a href="/product_accounts">
                <div class="stat-card">
                    <div class="stat-icon">🔑</div>
                    <div class="stat-value"><?= $stats['total_accounts'] ?></div>
                    <div class="stat-label">Accounts Còn</div>
                </div>
            </a>
        </div>
        <div class="col-md-3">
            <a href="/orders">
                <div class="stat-card">
                    <div class="stat-icon">💰</div>
                    <div class="stat-value"><?= number_format($stats['total_revenue'], 2) ?> VNĐ</div>
                    <div class="stat-label">Tổng Doanh Thu</div>
                </div>
            </a>
        </div>
    </div>

    <!-- Bot Status -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5>🤖 Trạng Thái Bot</h5>
            <a href="bot-config.php" class="btn btn-primary btn-sm">Cấu Hình</a>
        </div>
        <div class="card-body">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon" style="font-size: 3rem;">🤖</div>
                <div>
                    <h4><?= htmlspecialchars($bot['bot_name'] ?? 'Chưa Cấu Hình') ?></h4>
                    <?php if ($bot && $bot['is_configured']): ?>
                        <span class="badge badge-success">✅ Đã Kết Nối</span>
                        <span class="badge badge-info">@<?= htmlspecialchars($bot['bot_username'] ?? 'N/A') ?></span>
                    <?php else: ?>
                        <span class="badge badge-danger">❌ Chưa Cấu Hình</span>
                        <p class="mt-2 mb-0" style="color: var(--text-secondary);">
                            Vui lòng vào <a href="bot-config.php" style="color: var(--primary);">Cấu Hình Bot</a> để thiết lập
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Orders -->
    <div class="card">
        <div class="card-header">
            <h5>📋 Đơn Hàng Gần Đây</h5>
        </div>
        <div class="card-body">
            <?php if (empty($recentOrders)): ?>
                <p style="color: var(--text-secondary); text-align: center; padding: 20px;">
                    Chưa có đơn hàng nào
                </p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Sản Phẩm</th>
                                <th>Người Mua</th>
                                <th>Giá</th>
                                <th>Trạng Thái</th>
                                <th>Thời Gian</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentOrders as $order): ?>
                                <tr>
                                    <td>#<?= $order['id'] ?></td>
                                    <td><?= htmlspecialchars($order['product_name']) ?></td>
                                    <td><?= htmlspecialchars($order['username'] ?? 'User #' . $order['telegram_id']) ?></td>
                                    <td><?= number_format($order['price'], 2) ?> VNĐ</td>
                                    <td>
                                        <?php if ($order['status'] === 'completed'): ?>
                                            <span class="badge badge-success">Hoàn Thành</span>
                                        <?php else: ?>
                                            <span class="badge badge-warning">Đang Xử Lý</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></td>
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
        .row {
            display: flex;
            flex-wrap: wrap;
            margin: -10px;
        }
        .col-md-3 {
            flex: 0 0 25%;
            padding: 10px;
        }
        .g-4 {
            margin: -10px;
        }
        .mb-4 {
            margin-bottom: 25px;
        }
        .gap-3 {
            gap: 15px;
        }
        @media (max-width: 768px) {
            .col-md-3 {
                flex: 0 0 100%;
            }
        }
    </style>
</body>
</html>
