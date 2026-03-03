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
    require_once __DIR__ . '/libs/helper.php';
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
    $filterStatus  = $_GET['status'] ?? '';
    $perPage       = (int) ($_GET['limit'] ?? 10); // 0 = tất cả

    // WHERE clause tái sử dụng cho cả count và query chính
    $where        = "WHERE 1=1";
    $filterParams = [];

    if ($filterProduct) {
        $where          .= " AND o.product_id = ?";
        $filterParams[]  = $filterProduct;
    }
    if ($filterStatus) {
        $where          .= " AND o.status = ?";
        $filterParams[]  = $filterStatus;
    }

    // Phân trang
    $countQuery = "SELECT COUNT(*) FROM orders o $where";
    $pagination = paginate($pdo, $countQuery, $filterParams, $perPage);

    // Build URL cơ sở giữ nguyên các filter hiện tại (không có page)
    $baseUrlParams = array_filter([
        'product' => $filterProduct,
        'status'  => $filterStatus,
        'limit'   => $perPage ?: '',
    ]);
    $paginationBaseUrl = 'orders.php' . (!empty($baseUrlParams) ? '?' . http_build_query($baseUrlParams) : '');

    // Build query chính — không JOIN product_accounts vì account_id là chuỗi nhiều IDs
    $query = "
        SELECT o.*,
               p.name as product_name,
               u.username,
               u.telegram_id
        FROM orders o
        LEFT JOIN products p ON o.product_id = p.id
        LEFT JOIN users u ON o.user_id = u.id
        $where
        ORDER BY o.created_at DESC
    ";

    $params = $filterParams;

    if ($pagination['limit'] !== null) {
        $query    .= " LIMIT ? OFFSET ?";
        $params[]  = $pagination['limit'];
        $params[]  = $pagination['offset'];
    }

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $orders = $stmt->fetchAll();

    // Lấy tất cả accounts cho các orders hiện tại (dùng order_id trong product_accounts)
    $orderIds = array_column($orders, 'id');
    $orderAccountsMap = []; // order_id => [ {id, account_data}, ... ]
    if (!empty($orderIds)) {
        $ph = implode(',', array_fill(0, count($orderIds), '?'));
        $accStmt = $pdo->prepare("
            SELECT id, order_id, account_data 
            FROM product_accounts 
            WHERE order_id IN ($ph) 
            ORDER BY id ASC
        ");
        $accStmt->execute($orderIds);
        foreach ($accStmt->fetchAll() as $acc) {
            $orderAccountsMap[$acc['order_id']][] = $acc;
        }
    }

    // Get products for filter
    $products = $pdo->query("SELECT id, name FROM products ORDER BY name")->fetchAll();

    // Get statistics
    $stats = [
        'total_orders' => $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn(),
        'completed_orders' => $pdo->query("SELECT COUNT(*) FROM orders WHERE payment_status = 'completed'")->fetchColumn(),
        'total_revenue' => $pdo->query("SELECT COALESCE(SUM(total_price), 0) FROM orders WHERE payment_status = 'completed'")->fetchColumn(),
    ];

    $limitStatements = [
        '10'  => '10',
        '20'  => '20',
        '50'  => '50',
        '100' => '100',
        '0'   => 'Tất cả',
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
                <div class="form-group" style="flex: 1; margin-bottom: 0;">
                    <label class="form-label">Số lượng hiển thị</label>
                    <select name="limit" class="form-select">
                        <?php foreach ($limitStatements as $value => $label): ?>
                            <option value="<?= $value ?>" <?= $perPage == $value ? 'selected' : '' ?>>
                                <?= $label ?>
                            </option>
                        <?php endforeach; ?>
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
                                <th>Số thứ tự</th>
                                <th>Mã Đơn</th>
                                <th>Sản Phẩm</th>
                                <th>User Telegram</th>
                                <th>Account</th>
                                <th>Số lượng</th>
                                <th>Tổng tiền</th>
                                <th>Đơn giá /1</th>
                                <th>Trạng Thái</th>
                                <th>Thời Gian</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $i = $pagination['offset'];
                            foreach ($orders as $order):
                                $i++;
                                ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="order_ids[]" value="<?= $order['id'] ?>" class="order-checkbox" onchange="updateCount()">
                                    </td>
                                    <td><?= $i ?></td>
                                    <td><strong>#<?= str_pad($order['id'], 6, '0', STR_PAD_LEFT) ?></strong></td>
                                    <td><?= htmlspecialchars($order['product_name']) ?></td>
                                    <td>
                                        <?php if ($order['username']): ?>
                                            <a href="<?= 'http://t.me/' . $order['username'] ?>" target="_blank"><strong>@<?= htmlspecialchars($order['username']) ?></strong></a>
                                            <br>
                                            <small style="color: var(--text-secondary);">ID: <?= $order['telegram_id'] ?></small>
                                        <?php else: ?>
                                            <span style="color: var(--text-secondary);">ID: <?= $order['telegram_id'] ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $accounts = $orderAccountsMap[$order['id']] ?? [];
                                        $allAccountData = implode("\n", array_column($accounts, 'account_data'));
                                        $accountIds = implode(',', array_column($accounts, 'id'));
                                        $accountCount = count($accounts);
                                        ?>
                                        <a href="javascript:void(0)" class="order-detail-link"
                                           data-order-id="<?= $order['id'] ?>"
                                           data-product-name="<?= htmlspecialchars($order['product_name'] ?? '') ?>"
                                           data-username="<?= htmlspecialchars($order['username'] ?? '') ?>"
                                           data-telegram-id="<?= htmlspecialchars($order['telegram_id'] ?? '') ?>"
                                           data-account-data="<?= htmlspecialchars($allAccountData) ?>"
                                           data-account-ids="<?= htmlspecialchars($accountIds) ?>"
                                           data-account-count="<?= $accountCount ?>"
                                           data-quantity="<?= $order['quantity'] ?? 1 ?>"
                                           data-price="<?= $order['price'] ?? 0 ?>"
                                           data-status="<?= htmlspecialchars($order['status'] ?? '') ?>"
                                           data-payment-status="<?= htmlspecialchars($order['payment_status'] ?? '') ?>"
                                           data-created-at="<?= $order['created_at'] ?? '' ?>">
                                        <?php if ($accountCount > 0): ?>
                                            <code style="font-size: 0.85rem; background: rgba(102, 126, 234, 0.1); padding: 4px 8px; border-radius: 4px; cursor: pointer; transition: background 0.2s; display: inline-block; max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                                <?= htmlspecialchars(substr($accounts[0]['account_data'], 0, 25)) ?><?= strlen($accounts[0]['account_data']) > 25 ? '...' : '' ?>
                                            </code>
                                            <?php if ($accountCount > 1): ?>
                                                <span style="font-size: 0.75rem; color: var(--warning); display: block; margin-top: 2px;">+<?= $accountCount - 1 ?> tài khoản khác</span>
                                            <?php endif; ?>
                                            <span style="font-size: 0.7rem; color: var(--primary); display: block; margin-top: 2px;">✏️ Xem / Sửa (<?= $accountCount ?> TK)</span>
                                        <?php else: ?>
                                            <span style="color: var(--text-secondary); cursor: pointer;">N/A <span style="font-size: 0.7rem;">✏️</span></span>
                                        <?php endif; ?>
                                        </a>
                                    </td>
                                    <td><?= $order['quantity'] ?></td>
                                    <td><strong><?= number_format(intval($order['price']) * intval($order['quantity'])) ?></strong> VNĐ</td>
                                    <td><strong><?= number_format(intval($order['price'])) ?></strong> VNĐ</td>
                                    <td>
                                        <?php if ($order['payment_status'] === 'completed'): ?>
                                            <span class="badge badge-success">✅ Hoàn Thành</span>
                                        <?php elseif ($order['payment_status'] === 'pending'): ?>
                                            <span class="badge badge-warning">⏳ Chờ thanh toán</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">❌ Huỷ</span>
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

                    <?= renderPagination($pagination, $paginationBaseUrl) ?>

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

    <!-- Order Detail Modal -->
    <div class="modal" id="orderDetailModal">
        <div class="modal-dialog" style="max-width: 650px;">
            <div class="modal-header">
                <h5 id="modalTitle">📋 Chi Tiết Đơn Hàng</h5>
            </div>
            <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
                <!-- Order Info (readonly) -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 20px; padding: 16px; background: rgba(0,0,0,0.2); border-radius: 12px;">
                    <div>
                        <span style="color: var(--text-secondary); font-size: 0.8rem;">Mã Đơn</span>
                        <div id="modalOrderCode" style="font-weight: 700; font-size: 1.1rem; color: var(--primary);"></div>
                    </div>
                    <div>
                        <span style="color: var(--text-secondary); font-size: 0.8rem;">Sản Phẩm</span>
                        <div id="modalProductName" style="font-weight: 600;"></div>
                    </div>
                    <div>
                        <span style="color: var(--text-secondary); font-size: 0.8rem;">Khách Hàng</span>
                        <div id="modalCustomer" style="font-weight: 600;"></div>
                    </div>
                    <div>
                        <span style="color: var(--text-secondary); font-size: 0.8rem;">Thời Gian</span>
                        <div id="modalCreatedAt" style="font-weight: 500; font-size: 0.9rem;"></div>
                    </div>
                    <div>
                        <span style="color: var(--text-secondary); font-size: 0.8rem;">Số Lượng Mua</span>
                        <div id="modalQuantity" style="font-weight: 600;"></div>
                    </div>
                    <div>
                        <span style="color: var(--text-secondary); font-size: 0.8rem;">Số TK Đã Gán</span>
                        <div id="modalAccountCount" style="font-weight: 600;"></div>
                    </div>
                </div>

                <!-- Editable fields -->
                <div class="form-group">
                    <label class="form-label">🔑 Thông Tin Tài Khoản <span id="modalAccountLabel" style="color: var(--text-secondary); font-weight: 400; font-size: 0.85rem;"></span></label>
                    <textarea id="modalAccountData" class="form-control" rows="5" placeholder="username password 2FA..."></textarea>
                    <small style="color: var(--text-secondary); font-size: 0.8rem;">Mỗi tài khoản 1 dòng. Số dòng phải khớp với số lượng đã mua.</small>
                    <div id="accountMismatchWarning" style="display: none; margin-top: 6px; padding: 8px 12px; background: rgba(245, 158, 11, 0.15); border: 1px solid rgba(245, 158, 11, 0.3); border-radius: 8px; color: var(--warning); font-size: 0.85rem;">
                        ⚠️ Số dòng tài khoản không khớp với số lượng đã mua!
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label">💰 Giá</label>
                        <input type="number" id="modalPrice" class="form-control" step="0.01" min="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">📊 Trạng Thái</label>
                        <select id="modalStatus" class="form-select">
                            <option value="completed">✅ Hoàn Thành</option>
                            <option value="pending">⏳ Đang Xử Lý</option>
                            <option value="cancelled">❌ Huỷ</option>
                        </select>
                    </div>
                </div>

                <hr style="border-color: var(--border-color); margin: 20px 0;">

                <!-- Notification section -->
                <div class="form-group">
                    <label class="form-label">📨 Tin Nhắn Thông Báo <span style="color: var(--text-secondary); font-weight: 400; font-size: 0.85rem;">(tùy chọn)</span></label>
                    <textarea id="modalNotifyMessage" class="form-control" rows="4" placeholder="Để trống sẽ gửi tin nhắn mặc định kèm thông tin tài khoản đã cập nhật..."></textarea>
                    <small style="color: var(--text-secondary); font-size: 0.8rem;">Hỗ trợ HTML: &lt;b&gt;, &lt;i&gt;, &lt;code&gt;. Để trống = tin nhắn tự động.</small>
                </div>

                <!-- Status messages -->
                <div id="modalAlert" style="display: none; padding: 12px 16px; border-radius: 10px; margin-top: 12px; font-size: 0.9rem;"></div>
            </div>
            <div class="modal-footer" style="flex-direction: row; flex-wrap: wrap;">
                <button type="button" class="btn btn-secondary" onclick="closeOrderModal()" style="width: auto;">Đóng</button>
                <button type="button" class="btn btn-primary" id="btnSaveOrder" onclick="saveOrder()" style="width: auto;">
                    💾 Lưu Thay Đổi
                </button>
                <button type="button" class="btn btn-info" id="btnNotify" onclick="notifyCustomer()" style="width: auto; background: var(--info);">
                    📨 Gửi Thông Báo
                </button>
                <button type="button" class="btn btn-success" id="btnSaveAndNotify" onclick="saveAndNotify()" style="width: auto;">
                    💾📨 Lưu & Gửi
                </button>
            </div>
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
        .order-detail-link {
            text-decoration: none;
            color: inherit;
            display: block;
        }
        .order-detail-link:hover code {
            background: rgba(102, 126, 234, 0.25) !important;
        }
        .btn-loading {
            opacity: 0.7;
            pointer-events: none;
        }
        .btn-loading::after {
            content: ' ⏳';
        }
    </style>

    <script>
    let currentOrderId = null;
    let currentQuantity = 1;

    // Validate số dòng tài khoản khớp với số lượng mua
    function validateAccountLines() {
        const textarea = document.getElementById('modalAccountData');
        const lines = textarea.value.split('\n').filter(l => l.trim() !== '');
        const warning = document.getElementById('accountMismatchWarning');
        const label = document.getElementById('modalAccountLabel');

        label.textContent = `(${lines.length}/${currentQuantity} tài khoản)`;

        if (lines.length !== currentQuantity && textarea.value.trim() !== '') {
            warning.style.display = 'block';
            warning.textContent = `⚠️ Có ${lines.length} dòng tài khoản nhưng số lượng mua là ${currentQuantity}!`;
        } else {
            warning.style.display = 'none';
        }
    }

    // Mở modal khi click vào account data
    document.querySelectorAll('.order-detail-link').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const d = this.dataset;
            currentOrderId = d.orderId;
            currentQuantity = parseInt(d.quantity) || 1;

            document.getElementById('modalTitle').textContent = '📋 Chi Tiết Đơn Hàng #' + String(d.orderId).padStart(6, '0');
            document.getElementById('modalOrderCode').textContent = '#' + String(d.orderId).padStart(6, '0');
            document.getElementById('modalProductName').textContent = d.productName || 'N/A';

            // Customer info
            let customer = '';
            if (d.username) {
                customer = '@' + d.username + ' (ID: ' + d.telegramId + ')';
            } else {
                customer = 'ID: ' + (d.telegramId || 'N/A');
            }
            document.getElementById('modalCustomer').textContent = customer;
            document.getElementById('modalCreatedAt').textContent = d.createdAt || 'N/A';

            // Quantity & account count
            const accountCount = parseInt(d.accountCount) || 0;
            document.getElementById('modalQuantity').textContent = currentQuantity;

            const countEl = document.getElementById('modalAccountCount');
            if (accountCount === currentQuantity) {
                countEl.innerHTML = `<span style="color: var(--success);">${accountCount} ✅</span>`;
            } else if (accountCount > 0) {
                countEl.innerHTML = `<span style="color: var(--warning);">${accountCount} / ${currentQuantity} ⚠️</span>`;
            } else {
                countEl.innerHTML = `<span style="color: var(--danger);">0 / ${currentQuantity} ❌</span>`;
            }

            // Editable fields
            document.getElementById('modalAccountData').value = d.accountData || '';
            document.getElementById('modalPrice').value = d.price || 0;
            document.getElementById('modalStatus').value = d.status || 'completed';
            document.getElementById('modalNotifyMessage').value = '';

            // Validate lines
            validateAccountLines();

            // Reset alert
            hideAlert();

            // Show modal
            document.getElementById('orderDetailModal').classList.add('show');
        });
    });

    // Validate khi nhập textarea
    document.getElementById('modalAccountData').addEventListener('input', validateAccountLines);

    function closeOrderModal() {
        document.getElementById('orderDetailModal').classList.remove('show');
        currentOrderId = null;
    }

    // Close modal on overlay click
    document.getElementById('orderDetailModal').addEventListener('click', function(e) {
        if (e.target === this) closeOrderModal();
    });

    // Close modal on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeOrderModal();
    });

    function showAlert(message, type = 'success') {
        const alert = document.getElementById('modalAlert');
        alert.style.display = 'block';
        alert.textContent = message;
        if (type === 'success') {
            alert.style.background = 'rgba(16, 185, 129, 0.15)';
            alert.style.color = 'var(--success)';
            alert.style.border = '1px solid rgba(16, 185, 129, 0.3)';
        } else {
            alert.style.background = 'rgba(239, 68, 68, 0.15)';
            alert.style.color = 'var(--danger)';
            alert.style.border = '1px solid rgba(239, 68, 68, 0.3)';
        }
    }

    function hideAlert() {
        document.getElementById('modalAlert').style.display = 'none';
    }

    function setButtonLoading(btnId, loading) {
        const btn = document.getElementById(btnId);
        if (loading) {
            btn.classList.add('btn-loading');
            btn.disabled = true;
        } else {
            btn.classList.remove('btn-loading');
            btn.disabled = false;
        }
    }

    function updateTableDisplay(orderId) {
        const link = document.querySelector(`.order-detail-link[data-order-id="${orderId}"]`);
        if (!link) return;

        const newData = document.getElementById('modalAccountData').value;
        const lines = newData.split('\n').filter(l => l.trim() !== '');

        link.dataset.accountData = newData;
        link.dataset.price = document.getElementById('modalPrice').value;
        link.dataset.status = document.getElementById('modalStatus').value;
        link.dataset.accountCount = lines.length;

        // Rebuild cell content
        if (lines.length > 0) {
            const firstLine = lines[0];
            const preview = firstLine.length > 25 ? firstLine.substring(0, 25) + '...' : firstLine;
            let html = `<code style="font-size: 0.85rem; background: rgba(102, 126, 234, 0.1); padding: 4px 8px; border-radius: 4px; cursor: pointer; transition: background 0.2s; display: inline-block; max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${escapeHtml(preview)}</code>`;
            if (lines.length > 1) {
                html += `<span style="font-size: 0.75rem; color: var(--warning); display: block; margin-top: 2px;">+${lines.length - 1} tài khoản khác</span>`;
            }
            html += `<span style="font-size: 0.7rem; color: var(--primary); display: block; margin-top: 2px;">✏️ Xem / Sửa (${lines.length} TK)</span>`;
            link.innerHTML = html;
        } else {
            link.innerHTML = '<span style="color: var(--text-secondary); cursor: pointer;">N/A <span style="font-size: 0.7rem;">✏️</span></span>';
        }

        // Update account count in modal
        const countEl = document.getElementById('modalAccountCount');
        if (lines.length === currentQuantity) {
            countEl.innerHTML = `<span style="color: var(--success);">${lines.length} ✅</span>`;
        } else if (lines.length > 0) {
            countEl.innerHTML = `<span style="color: var(--warning);">${lines.length} / ${currentQuantity} ⚠️</span>`;
        } else {
            countEl.innerHTML = `<span style="color: var(--danger);">0 / ${currentQuantity} ❌</span>`;
        }
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    async function apiCall(payload) {
        const res = await fetch('api/update-order.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        return await res.json();
    }

    async function saveOrder() {
        if (!currentOrderId) return;
        setButtonLoading('btnSaveOrder', true);
        hideAlert();

        try {
            const result = await apiCall({
                action: 'update',
                order_id: currentOrderId,
                account_data: document.getElementById('modalAccountData').value,
                price: document.getElementById('modalPrice').value,
                status: document.getElementById('modalStatus').value,
            });

            if (result.success) {
                showAlert('✅ ' + result.message, 'success');
                updateTableDisplay(currentOrderId);
            } else {
                showAlert('❌ ' + result.message, 'error');
            }
        } catch (err) {
            showAlert('❌ Lỗi kết nối: ' + err.message, 'error');
        }

        setButtonLoading('btnSaveOrder', false);
    }

    async function notifyCustomer() {
        if (!currentOrderId) return;
        setButtonLoading('btnNotify', true);
        hideAlert();

        try {
            const result = await apiCall({
                action: 'notify',
                order_id: currentOrderId,
                message: document.getElementById('modalNotifyMessage').value,
            });

            if (result.success) {
                showAlert('✅ ' + result.message, 'success');
            } else {
                showAlert('❌ ' + result.message, 'error');
            }
        } catch (err) {
            showAlert('❌ Lỗi kết nối: ' + err.message, 'error');
        }

        setButtonLoading('btnNotify', false);
    }

    async function saveAndNotify() {
        if (!currentOrderId) return;
        setButtonLoading('btnSaveAndNotify', true);
        hideAlert();

        try {
            // Step 1: Save
            const saveResult = await apiCall({
                action: 'update',
                order_id: currentOrderId,
                account_data: document.getElementById('modalAccountData').value,
                price: document.getElementById('modalPrice').value,
                status: document.getElementById('modalStatus').value,
            });

            if (!saveResult.success) {
                showAlert('❌ Lưu thất bại: ' + saveResult.message, 'error');
                setButtonLoading('btnSaveAndNotify', false);
                return;
            }

            // Cập nhật hiển thị table
            updateTableDisplay(currentOrderId);

            // Step 2: Notify
            const notifyResult = await apiCall({
                action: 'notify',
                order_id: currentOrderId,
                message: document.getElementById('modalNotifyMessage').value,
            });

            if (notifyResult.success) {
                showAlert('✅ Đã lưu và gửi thông báo thành công!', 'success');
            } else {
                showAlert('⚠️ Đã lưu nhưng gửi thông báo thất bại: ' + notifyResult.message, 'error');
            }
        } catch (err) {
            showAlert('❌ Lỗi: ' + err.message, 'error');
        }

        setButtonLoading('btnSaveAndNotify', false);
    }
    </script>
</body>
</html>
