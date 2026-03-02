<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đơn Hàng - Bot Shop</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php
    require_once __DIR__ . '/config/db.php';
    require_once __DIR__ . '/includes/auth.php';
    requireLogin();

    // Handle bulk delete
    if (isset($_POST['bulk_delete']) && !empty($_POST['order_ids'])) {
        $orderIds = $_POST['order_ids'];
        $placeholders = str_repeat('?,', count($orderIds) - 1) . '?';
        $stmt = $pdo->prepare("DELETE FROM orders WHERE id IN ($placeholders)");
        $stmt->execute($orderIds);
        header('Location: orders.php?deleted=' . count($orderIds));
        exit;
    }

    // Get filter parameters
    $filterProduct = $_GET['product'] ?? '';
    $filterStatus = $_GET['status'] ?? '';

    // Build query
    $query = "
        SELECT o.*, 
               p.name as product_name,
               u.username,
               u.telegram_id,
               pa.account_data
        FROM orders o
        LEFT JOIN products p ON o.product_id = p.id
        LEFT JOIN users u ON o.user_id = u.id
        LEFT JOIN product_accounts pa ON o.account_id = pa.id
        WHERE 1=1
    ";

    $params = [];

    if ($filterProduct) {
        $query .= " AND o.product_id = ?";
        $params[] = $filterProduct;
    }

    if ($filterStatus) {
        $query .= " AND o.status = ?";
        $params[] = $filterStatus;
    }

    $query .= " ORDER BY o.created_at DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $orders = $stmt->fetchAll();

    // Get products for filter
    $products = $pdo->query("SELECT id, name FROM products ORDER BY name")->fetchAll();

    // Get statistics
    $stats = [
        'total_orders' => $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn(),
        'completed_orders' => $pdo->query("SELECT COUNT(*) FROM orders WHERE payment_status = 'completed'")->fetchColumn(),
        'total_revenue' => $pdo->query("SELECT COALESCE(SUM(total_price), 0) FROM orders WHERE payment_status = 'completed'")->fetchColumn(),
    ];

    $pageTitle = 'Orders';
    include __DIR__ . '/includes/header.php';
    ?>

    <div class="page-header">
        <h2>📦 Quản Lý Đơn Hàng</h2>
        <p>Theo dõi và quản lý tất cả đơn hàng</p>
    </div>

    <!-- Stats -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon">📋</div>
                <div class="stat-value"><?= $stats['total_orders'] ?></div>
                <div class="stat-label">Tổng Đơn Hàng</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-value"><?= $stats['completed_orders'] ?></div>
                <div class="stat-label">Hoàn Thành</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon">💰</div>
                <div class="stat-value">$<?= number_format($stats['total_revenue'], 2) ?></div>
                <div class="stat-label">Doanh Thu</div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-header">
            <h5>🔍 Bộ Lọc</h5>
        </div>
        <div class="card-body">
            <form method="GET" class="d-flex gap-3">
                <div class="form-group" style="flex: 1; margin-bottom: 0;">
                    <label class="form-label">Sản Phẩm</label>
                    <select name="product" class="form-select">
                        <option value="">Tất cả</option>
                        <?php foreach ($products as $product): ?>
                            <option value="<?= $product['id'] ?>" <?= $filterProduct == $product['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($product['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="flex: 1; margin-bottom: 0;">
                    <label class="form-label">Trạng Thái</label>
                    <select name="status" class="form-select">
                        <option value="">Tất cả</option>
                        <option value="completed" <?= $filterStatus === 'completed' ? 'selected' : '' ?>>Hoàn Thành</option>
                        <option value="pending" <?= $filterStatus === 'pending' ? 'selected' : '' ?>>Đang Xử Lý</option>
                        <option value="failed" <?= $filterStatus === 'failed' ? 'selected' : '' ?>>Thất Bại</option>
                    </select>
                </div>
                <div style="display: flex; align-items: flex-end;">
                    <button type="submit" class="btn btn-primary">Lọc</button>
                    <?php if ($filterProduct || $filterStatus): ?>
                        <a href="orders.php" class="btn btn-secondary" style="margin-left: 10px;">Reset</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Orders Table -->
    <div class="card">
        <div class="card-header">
            <h5>📋 Danh Sách Đơn Hàng</h5>
        </div>
        <div class="card-body">
            <?php if (empty($orders)): ?>
                <p style="color: var(--text-secondary); text-align: center; padding: 40px;">
                    Không có đơn hàng nào
                </p>
            <?php else: ?>
                <div class="table-responsive">
                    <form method="POST" id="bulkDeleteForm" onsubmit="return confirm('Bạn có chắc muốn xóa ' + document.querySelectorAll('input[name=\"order_ids[]\"]:checked').length + ' đơn hàng?');">
                    <div style="margin-bottom: 15px;">
                        <button type="submit" name="bulk_delete" class="btn btn-danger" style="background: #dc3545; color: white; padding: 8px 16px; border: none; border-radius: 5px; cursor: pointer;">
                            🗑️ Xóa Đã Chọn
                        </button>
                        <span id="selectedCount" style="margin-left: 15px; color: #666;"></span>
                    </div>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>
                                    <input type="checkbox" id="selectAll" onchange="toggleAll(this)">
                                </th>
                                <th>Mã Đơn</th>
                                <th>Sản Phẩm</th>
                                <th>User Telegram</th>
                                <th>Account</th>
                                <th>Giá</th>
                                <th>Trạng Thái</th>
                                <th>Thời Gian</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="order_ids[]" value="<?= $order['id'] ?>" class="order-checkbox" onchange="updateCount()">
                                    </td>
                                    <td><strong>#<?= str_pad($order['id'], 6, '0', STR_PAD_LEFT) ?></strong></td>
                                    <td><?= htmlspecialchars($order['product_name']) ?></td>
                                    <td>
                                        <?php if ($order['username']): ?>
                                            <strong>@<?= htmlspecialchars($order['username']) ?></strong><br>
                                            <small style="color: var(--text-secondary);">ID: <?= $order['telegram_id'] ?></small>
                                        <?php else: ?>
                                            <span style="color: var(--text-secondary);">ID: <?= $order['telegram_id'] ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($order['account_data']): ?>
                                            <code style="font-size: 0.85rem; background: rgba(102, 126, 234, 0.1); padding: 4px 8px; border-radius: 4px;">
                                                <?= htmlspecialchars(substr($order['account_data'], 0, 30)) ?>...
                                            </code>
                                        <?php else: ?>
                                            <span style="color: var(--text-secondary);">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong>$<?= number_format($order['price'], 2) ?></strong></td>
                                    <td>
                                        <?php if ($order['status'] === 'completed'): ?>
                                            <span class="badge badge-success">✅ Hoàn Thành</span>
                                        <?php elseif ($order['status'] === 'pending'): ?>
                                            <span class="badge badge-warning">⏳ Đang Xử Lý</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">❌ Thất Bại</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= date('d/m/Y', strtotime($order['created_at'])) ?><br>
                                        <small style="color: var(--text-secondary);"><?= date('H:i:s', strtotime($order['created_at'])) ?></small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </form>
                    
                    <script>
                    function toggleAll(source) {
                        const checkboxes = document.querySelectorAll('input[name="order_ids[]"]');
                        checkboxes.forEach(cb => cb.checked = source.checked);
                        updateCount();
                    }
                    
                    function updateCount() {
                        const checked = document.querySelectorAll('input[name="order_ids[]"]:checked').length;
                        const total = document.querySelectorAll('input[name="order_ids[]"]').length;
                        document.getElementById('selectedCount').textContent = checked > 0 ? `Đã chọn: ${checked}/${total}` : '';
                        document.getElementById('selectAll').checked = checked === total && total > 0;
                    }
                    </script>
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
        .col-md-4 {
            flex: 0 0 33.333%;
            padding: 10px;
        }
        .g-4 {
            margin: -10px;
        }
        .mb-4 {
            margin-bottom: 25px;
        }
        @media (max-width: 768px) {
            .col-md-4 {
                flex: 0 0 100%;
            }
        }
    </style>
</body>
</html>
