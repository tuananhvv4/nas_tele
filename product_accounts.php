<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Lý Accounts - Bot Shop Admin</title>
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

    // Handle account actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        if ($action === 'add_single') {
            $productId = intval($_POST['product_id'] ?? 0);
            $accountData = trim($_POST['account_data'] ?? '');

            if ($productId && $accountData) {
                $stmt = $pdo->prepare("INSERT INTO product_accounts (product_id, account_data, is_sold) VALUES (?, ?, 0)");
                if ($stmt->execute([$productId, $accountData])) {
                    $success = 'Đã thêm account thành công!';
                } else {
                    $error = 'Thêm account thất bại!';
                }
            } else {
                $error = 'Vui lòng điền đầy đủ thông tin!';
            }
        } elseif ($action === 'bulk_upload') {
            $productId = intval($_POST['product_id'] ?? 0);
            $accountsText = trim($_POST['accounts_text'] ?? '');

            if ($productId && $accountsText) {
                $accounts = explode("\n", $accountsText);
                $count = 0;

                $stmt = $pdo->prepare("INSERT INTO product_accounts (product_id, account_data, is_sold) VALUES (?, ?, 0)");
                
                foreach ($accounts as $account) {
                    $account = trim($account);
                    if ($account) {
                        if ($stmt->execute([$productId, $account])) {
                            $count++;
                        }
                    }
                }

                $success = "Đã thêm {$count} accounts thành công!";
            } else {
                $error = 'Vui lòng chọn sản phẩm và nhập accounts!';
            }
        } elseif ($action === 'delete') {
            $id = intval($_POST['id'] ?? 0);
            if ($id) {
                $stmt = $pdo->prepare("DELETE FROM product_accounts WHERE id = ?");
                if ($stmt->execute([$id])) {
                    $success = 'Đã xóa account thành công!';
                }
            }
        } elseif ($action === 'bulk_delete') {
            if (!empty($_POST['account_ids'])) {
                $accountIds = $_POST['account_ids'];
                $placeholders = str_repeat('?,', count($accountIds) - 1) . '?';
                $stmt = $pdo->prepare("DELETE FROM product_accounts WHERE id IN ($placeholders)");
                $stmt->execute($accountIds);
                $success = 'Đã xóa ' . count($accountIds) . ' accounts thành công!';
            }
        }
    }

    // Get filter
    $filterProduct = intval($_GET['product'] ?? 0);
    $filterStatus = $_GET['status'] ?? 'all';

    // Build query
    $query = "
        SELECT pa.*, p.name as product_name, u.username as buyer_username
        FROM product_accounts pa
        LEFT JOIN products p ON pa.product_id = p.id
        LEFT JOIN users u ON pa.sold_to_user_id = u.id
        WHERE 1=1
    ";

    if ($filterProduct > 0) {
        $query .= " AND pa.product_id = " . $filterProduct;
    }

    if ($filterStatus === 'available') {
        $query .= " AND pa.is_sold = 0";
    } elseif ($filterStatus === 'sold') {
        $query .= " AND pa.is_sold = 1";
    }

    $query .= " ORDER BY pa.created_at DESC LIMIT 100";

    $accounts = $pdo->query($query)->fetchAll();

    // Get all products for filter
    $products = $pdo->query("SELECT * FROM products ORDER BY name")->fetchAll();

    $pageTitle = 'Product Accounts';
    include __DIR__ . '/includes/header.php';
    ?>

    <div class="page-header d-flex justify-content-between align-items-center">
        <div>
            <h2>🔑 Quản Lý Accounts</h2>
            <p>Quản lý kho accounts cho sản phẩm</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-success" onclick="showBulkUploadModal()">📤 Bulk Upload</button>
            <button class="btn btn-primary" onclick="showAddModal()">+ Thêm Account</button>
        </div>
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

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="d-flex gap-2 align-items-center">
                <div class="form-group mb-0" style="flex: 1;">
                    <select class="form-select" name="product" onchange="this.form.submit()">
                        <option value="0">Tất Cả Sản Phẩm</option>
                        <?php foreach ($products as $product): ?>
                            <option value="<?= $product['id'] ?>" <?= $filterProduct == $product['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($product['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group mb-0" style="flex: 1;">
                    <select class="form-select" name="status" onchange="this.form.submit()">
                        <option value="all" <?= $filterStatus === 'all' ? 'selected' : '' ?>>Tất Cả Trạng Thái</option>
                        <option value="available" <?= $filterStatus === 'available' ? 'selected' : '' ?>>Còn Hàng</option>
                        <option value="sold" <?= $filterStatus === 'sold' ? 'selected' : '' ?>>Đã Bán</option>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <!-- Accounts Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <form method="POST" id="bulkDeleteForm" onsubmit="return confirm('Bạn có chắc muốn xóa ' + document.querySelectorAll('input[name=\"account_ids[]\"]:checked').length + ' accounts?');">
                    <input type="hidden" name="action" value="bulk_delete">
                    <div style="margin-bottom: 15px;">
                        <button type="submit" class="btn btn-danger" style="background: #dc3545; color: white; padding: 8px 16px; border: none; border-radius: 5px; cursor: pointer;">
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
                            <th>ID</th>
                            <th>Sản Phẩm</th>
                            <th>Account Data</th>
                            <th>Trạng Thái</th>
                            <th>Người Mua</th>
                            <th>Ngày Bán</th>
                            <th>Thao Tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($accounts)): ?>
                            <tr>
                                <td colspan="7" class="text-center" style="color: var(--text-secondary); padding: 40px;">
                                    Không có account nào
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($accounts as $account): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="account_ids[]" value="<?= $account['id'] ?>" class="account-checkbox" onchange="updateCount()">
                                    </td>
                                    <td>#<?= $account['id'] ?></td>
                                    <td><?= htmlspecialchars($account['product_name']) ?></td>
                                    <td>
                                        <code style="background: rgba(102, 126, 234, 0.1); padding: 4px 8px; border-radius: 4px; color: var(--primary);">
                                            <?= htmlspecialchars(substr($account['account_data'], 0, 30)) ?>...
                                        </code>
                                    </td>
                                    <td>
                                        <?php if ($account['is_sold']): ?>
                                            <span class="badge badge-danger">Đã Bán</span>
                                        <?php else: ?>
                                            <span class="badge badge-success">Còn Hàng</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($account['buyer_username'] ?? '-') ?></td>
                                    <td><?= $account['sold_at'] ? date('d/m/Y H:i', strtotime($account['sold_at'])) : '-' ?></td>
                                    <td>
                                        <!-- Allow delete for both sold and unsold -->
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Bạn có chắc muốn xóa account này?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $account['id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">Xóa</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                </form>
                
                <script>
                function toggleAll(source) {
                    const checkboxes = document.querySelectorAll('input[name="account_ids[]"]');
                    checkboxes.forEach(cb => cb.checked = source.checked);
                    updateCount();
                }
                
                function updateCount() {
                    const checked = document.querySelectorAll('input[name="account_ids[]"]:checked').length;
                    const total = document.querySelectorAll('input[name="account_ids[]"]').length;
                    document.getElementById('selectedCount').textContent = checked > 0 ? `Đã chọn: ${checked}/${total}` : '';
                    document.getElementById('selectAll').checked = checked === total && total > 0;
                }
                </script>
            </div>
        </div>
    </div>

    <!-- Add Single Account Modal -->
    <div class="modal" id="addAccountModal">
        <div class="modal-dialog">
            <form method="POST">
                <input type="hidden" name="action" value="add_single">
                
                <div class="modal-header">
                    <h5>Thêm Account</h5>
                </div>
                
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Sản Phẩm *</label>
                        <select class="form-select" name="product_id" required>
                            <option value="">-- Chọn Sản Phẩm --</option>
                            <?php foreach ($products as $product): ?>
                                <option value="<?= $product['id'] ?>">
                                    <?= htmlspecialchars($product['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Account Data *</label>
                        <input type="text" class="form-control" name="account_data" 
                               placeholder="email@example.com|password123" required>
                        <small style="color: var(--text-secondary);">
                            Format: email|password hoặc username|password
                        </small>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Hủy</button>
                    <button type="submit" class="btn btn-primary">Thêm</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Bulk Upload Modal -->
    <div class="modal" id="bulkUploadModal">
        <div class="modal-dialog" style="max-width: 600px;">
            <form method="POST">
                <input type="hidden" name="action" value="bulk_upload">
                
                <div class="modal-header">
                    <h5>📤 Bulk Upload Accounts</h5>
                </div>
                
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Sản Phẩm *</label>
                        <select class="form-select" name="product_id" required>
                            <option value="">-- Chọn Sản Phẩm --</option>
                            <?php foreach ($products as $product): ?>
                                <option value="<?= $product['id'] ?>">
                                    <?= htmlspecialchars($product['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Accounts (Mỗi Dòng 1 Account) *</label>
                        <textarea class="form-control" name="accounts_text" rows="10" 
                                  placeholder="email1@example.com|password1&#10;email2@example.com|password2&#10;email3@example.com|password3" required></textarea>
                        <small style="color: var(--text-secondary);">
                            Mỗi dòng 1 account, format: email|password
                        </small>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Hủy</button>
                    <button type="submit" class="btn btn-success">Upload</button>
                </div>
            </form>
        </div>
    </div>

    <?php include __DIR__ . '/includes/footer.php'; ?>

    <script>
        function showAddModal() {
            document.getElementById('addAccountModal').classList.add('show');
        }

        function showBulkUploadModal() {
            document.getElementById('bulkUploadModal').classList.add('show');
        }

        function closeModal() {
            document.querySelectorAll('.modal').forEach(m => m.classList.remove('show'));
        }

        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) closeModal();
            });
        });
    </script>
</body>
</html>
