<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Lý Sản Phẩm - Bot Shop Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php
    require_once __DIR__ . '/config/db.php';
    require_once __DIR__ . '/includes/auth.php';
    requireLogin();

    $success = $_GET['success'] ?? '';
    $error = '';

    // Handle product actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        if ($action === 'add') {
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $price = intval($_POST['price'] ?? 0);
            $category = trim($_POST['category'] ?? '');
            $delivery_style = $_POST['delivery_style'] ?? 'default';
            $custom_message = trim($_POST['custom_message'] ?? '');
            $login_url = trim($_POST['login_url'] ?? '');
            $twofa_instruction = trim($_POST['twofa_instruction'] ?? '');

            if ($name && $price > 0) {
                $stmt = $pdo->prepare("INSERT INTO products (name, description, price, category, delivery_style, custom_message, login_url, twofa_instruction, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')");
                if ($stmt->execute([$name, $description, $price, $category, $delivery_style, $custom_message, $login_url, $twofa_instruction])) {
                    $success = 'Đã thêm sản phẩm thành công!';
                } else {
                    $error = 'Thêm sản phẩm thất bại!';
                }
            } else {
                $error = 'Vui lòng điền đầy đủ thông tin!';
            }
        } elseif ($action === 'edit') {
            $id = intval($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $price = intval($_POST['price'] ?? 0);
            $category = trim($_POST['category'] ?? '');
            $delivery_style = $_POST['delivery_style'] ?? 'default';
            $custom_message = trim($_POST['custom_message'] ?? '');
            $login_url = trim($_POST['login_url'] ?? '');
            $twofa_instruction = trim($_POST['twofa_instruction'] ?? '');
            
            // DEBUG: Log the values
            error_log("=== EDIT PRODUCT DEBUG ===");
            error_log("Product ID: $id");
            error_log("Delivery Style from POST: " . ($_POST['delivery_style'] ?? 'NOT SET'));
            error_log("Delivery Style variable: $delivery_style");
            error_log("2FA Instruction: $twofa_instruction");

            if ($id && $name && $price > 0) {
                $stmt = $pdo->prepare("UPDATE products SET name = ?, description = ?, price = ?, category = ?, delivery_style = ?, custom_message = ?, login_url = ?, twofa_instruction = ? WHERE id = ?");
                $result = $stmt->execute([$name, $description, $price, $category, $delivery_style, $custom_message, $login_url, $twofa_instruction, $id]);
                
                error_log("SQL Execute Result: " . ($result ? 'SUCCESS' : 'FAILED'));
                error_log("Rows Affected: " . $stmt->rowCount());
                
                if ($result) {
                    // Verify what was saved
                    $verifyStmt = $pdo->prepare("SELECT delivery_style FROM products WHERE id = ?");
                    $verifyStmt->execute([$id]);
                    $saved = $verifyStmt->fetchColumn();
                    error_log("Verified in DB: delivery_style = " . ($saved ?? 'NULL'));
                    
                    // Redirect to refresh page and show updated data
                    header("Location: products.php?success=" . urlencode('Đã cập nhật sản phẩm thành công!'));
                    exit;
                } else {
                    $error = 'Cập nhật sản phẩm thất bại! ' . implode(', ', $stmt->errorInfo());
                    error_log("SQL Error: $error");
                }
            } else {
                $error = "Validation failed: ID=$id, Name=$name, Price=$price";
                error_log($error);
            }
        } elseif ($action === 'delete') {
            $id = intval($_POST['id'] ?? 0);
            if ($id) {
                $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
                if ($stmt->execute([$id])) {
                    $success = 'Đã xóa sản phẩm thành công!';
                }
            }
        } elseif ($action === 'toggle_status') {
            $id = intval($_POST['id'] ?? 0);
            if ($id) {
                $stmt = $pdo->prepare("UPDATE products SET status = IF(status = 'active', 'inactive', 'active') WHERE id = ?");
                if ($stmt->execute([$id])) {
                    $success = 'Đã cập nhật trạng thái!';
                }
            }
        } elseif ($action === 'add_accounts') {
            $productId = intval($_POST['product_id'] ?? 0);
            $accountsText = trim($_POST['accounts_text'] ?? '');
            
            if ($productId && $accountsText) {
                $lines = explode("\n", $accountsText);
                $added = 0;
                
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line)) continue;
                    
                    // Support multiple formats: user pass, user:pass, user|pass
                    $accountData = $line;
                    
                    // Normalize to consistent format
                    if (strpos($line, '|') !== false) {
                        $accountData = str_replace('|', ' ', $line);
                    } elseif (strpos($line, ':') !== false) {
                        $accountData = str_replace(':', ' ', $line);
                    }
                    
                    $stmt = $pdo->prepare("INSERT INTO product_accounts (product_id, account_data) VALUES (?, ?)");
                    if ($stmt->execute([$productId, $accountData])) {
                        $added++;
                    }
                }
                
                $success = "Đã thêm {$added} accounts thành công!";
            }
        } elseif ($action === 'upload_accounts_file') {
            $productId = intval($_POST['product_id'] ?? 0);
            
            if ($productId && isset($_FILES['accounts_file']) && $_FILES['accounts_file']['error'] === 0) {
                $fileContent = file_get_contents($_FILES['accounts_file']['tmp_name']);
                $lines = explode("\n", $fileContent);
                $added = 0;
                
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line)) continue;
                    
                    // Support multiple formats
                    $accountData = $line;
                    if (strpos($line, '|') !== false) {
                        $accountData = str_replace('|', ' ', $line);
                    } elseif (strpos($line, ':') !== false) {
                        $accountData = str_replace(':', ' ', $line);
                    }
                    
                    $stmt = $pdo->prepare("INSERT INTO product_accounts (product_id, account_data) VALUES (?, ?)");
                    if ($stmt->execute([$productId, $accountData])) {
                        $added++;
                    }
                }
                
                $success = "Đã upload {$added} accounts thành công!";
            }
        } elseif ($action === 'delete_account') {
            $accountId = intval($_POST['account_id'] ?? 0);
            if ($accountId) {
                $stmt = $pdo->prepare("DELETE FROM product_accounts WHERE id = ? AND is_sold = 0");
                if ($stmt->execute([$accountId])) {
                    $success = 'Đã xóa account!';
                } else {
                    $error = 'Không thể xóa account đã bán!';
                }
            }
        }
    }

    // Get all products with stock count
    $products = $pdo->query("
        SELECT p.*, 
               COUNT(CASE WHEN pa.is_sold = 0 THEN 1 END) as stock_count,
               COUNT(pa.id) as total_accounts
        FROM products p
        LEFT JOIN product_accounts pa ON p.id = pa.product_id
        GROUP BY p.id
        ORDER BY p.display_order ASC, p.id ASC
    ")->fetchAll();

    $pageTitle = 'Products';
    include __DIR__ . '/includes/header.php';
    ?>

    <div class="page-header d-flex justify-content-between align-items-center">
        <div>
            <h2>📦 Quản Lý Sản Phẩm</h2>
            <p>Quản lý danh mục sản phẩm</p>
        </div>
        <button class="btn btn-primary" onclick="showAddModal()">+ Thêm Sản Phẩm</button>
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

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table" id="productsTable">
                    <thead>
                        <tr>
                            <th style="width: 40px;"></th>
                            <th>ID</th>
                            <th>Tên Sản Phẩm</th>
                            <th>Danh Mục</th>
                            <th>Giá</th>
                            <th>Kho</th>
                            <th>Trạng Thái</th>
                            <th>Thao Tác</th>
                        </tr>
                    </thead>
                    <tbody id="productsList">
                        <?php foreach ($products as $product): ?>
                            <tr data-product-id="<?= $product['id'] ?>" style="cursor: move;">
                                <td style="cursor: grab; text-align: center; color: var(--text-secondary);">
                                    <span class="drag-handle">⋮⋮</span>
                                </td>
                                <td>#<?= $product['id'] ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($product['name']) ?></strong>
                                    <?php if ($product['description']): ?>
                                        <br><small style="color: var(--text-secondary);"><?= htmlspecialchars(substr($product['description'], 0, 50)) ?>...</small>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($product['category'] ?? 'N/A') ?></td>
                                <td><strong><?= number_format($product['price'], 0, ',', '.') ?> VNĐ</strong></td>
                                <td>
                                    <?php
                                    $stockClass = 'success';
                                    if ($product['stock_count'] == 0) $stockClass = 'danger';
                                    elseif ($product['stock_count'] < 5) $stockClass = 'warning';
                                    ?>
                                    <span class="badge badge-<?= $stockClass ?>">
                                        <?= $product['stock_count'] ?> / <?= $product['total_accounts'] ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($product['status'] === 'active'): ?>
                                        <span class="badge badge-success">Hoạt Động</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">Tạm Dừng</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-info btn-sm" onclick='editProduct(<?= json_encode($product) ?>)'>Sửa</button>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="id" value="<?= $product['id'] ?>">
                                        <button type="submit" class="btn btn-warning btn-sm">
                                            <?= $product['status'] === 'active' ? 'Tắt' : 'Bật' ?>
                                        </button>
                                    </form>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Bạn có chắc muốn xóa sản phẩm này?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $product['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">Xóa</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add Product Modal -->
    <div class="modal" id="addProductModal">
        <div class="modal-dialog">
            <form method="POST">
                <input type="hidden" name="action" value="add">
                
                <div class="modal-header">
                    <h5>Thêm Sản Phẩm Mới</h5>
                </div>
                
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Tên Sản Phẩm *</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Mô Tả</label>
                        <textarea class="form-control" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Danh Mục</label>
                        <input type="text" class="form-control" name="category" placeholder="VD: Streaming, Music, Design...">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Giá (VND) *</label>
                        <input type="number" class="form-control" name="price" min="0" placeholder="Ví dụ: 50000" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Phong Cách Trả Hàng</label>
                        <select class="form-control" name="delivery_style" id="add_delivery_style">
                            <option value="default">Mặc định</option>
                            <option value="style1">Phong cách 1</option>
                            <option value="style2">Phong cách 2 (2FA)</option>
                        </select>
                    </div>
                    
                    <div class="form-group" id="add_twofa_instruction_group" style="display: none;">
                        <label class="form-label">Hướng Dẫn 2FA</label>
                        <textarea class="form-control" name="twofa_instruction" rows="2" placeholder="Đăng nhập Chat GPT bằng Email và sử dụng 2fa để nhận mã OTP."></textarea>
                        <small class="text-muted">Chỉ hiển thị khi chọn Phong cách 2 (2FA)</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Link Đăng Nhập</label>
                        <input type="url" class="form-control" name="login_url" placeholder="https://outlook.office.com/">
                        <small class="text-muted">Link sẽ hiển thị trong tin nhắn trả hàng</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Tin Nhắn Tùy Chỉnh</label>
                        <textarea class="form-control" name="custom_message" rows="2" placeholder="Vui lòng đổi mật khẩu sau khi đăng nhập..."></textarea>
                        <small class="text-muted">Tin nhắn bổ sung sẽ hiển thị khi trả hàng</small>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Hủy</button>
                    <button type="submit" class="btn btn-primary">Thêm</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Product Modal -->
    <div class="modal" id="editProductModal">
        <div class="modal-dialog" style="max-width: 700px; max-height: 90vh; overflow-y: auto;">
            <form method="POST" id="editProductForm">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                
                <div class="modal-header">
                    <h5>✏️ Chỉnh Sửa Sản Phẩm</h5>
                </div>
                
                <div class="modal-body" style="max-height: calc(90vh - 140px); overflow-y: auto; padding: 20px;">
                    <!-- Product Info Section -->
                    <div style="margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid var(--border-color);">
                        <h6 style="margin-bottom: 12px; color: var(--primary); font-weight: 600; font-size: 0.95rem;">📝 Thông Tin Sản Phẩm</h6>
                        
                        <div class="form-group" style="margin-bottom: 12px;">
                            <label class="form-label" style="font-size: 0.85rem;">Tên Sản Phẩm *</label>
                            <input type="text" class="form-control" name="name" id="edit_name" required style="padding: 8px 12px; font-size: 0.9rem;">
                        </div>
                        
                        <div class="form-group" style="margin-bottom: 12px;">
                            <label class="form-label" style="font-size: 0.85rem;">Mô Tả</label>
                            <textarea class="form-control" name="description" id="edit_description" rows="2" style="padding: 8px 12px; font-size: 0.9rem;"></textarea>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label" style="font-size: 0.85rem;">Danh Mục</label>
                                <input type="text" class="form-control" name="category" id="edit_category" style="padding: 8px 12px; font-size: 0.9rem;">
                            </div>
                            
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label" style="font-size: 0.85rem;">Giá (VND) *</label>
                                <input type="number" class="form-control" name="price" id="edit_price" min="0" placeholder="Ví dụ: 50000" required style="padding: 8px 12px; font-size: 0.9rem;">
                            </div>
                        </div>
                        
                        <div class="form-group" style="margin-top: 12px;">
                            <label class="form-label" style="font-size: 0.85rem;">Phong Cách Trả Hàng</label>
                            <select class="form-control" name="delivery_style" id="edit_delivery_style" style="padding: 8px 12px; font-size: 0.9rem;">
                                <option value="default">Mặc định</option>
                                <option value="style1">Phong cách 1</option>
                                <option value="style2">Phong cách 2 (2FA)</option>
                            </select>
                        </div>
                        
                        <div class="form-group" id="edit_twofa_instruction_group" style="margin-top: 12px; display: none;">
                            <label class="form-label" style="font-size: 0.85rem;">Hướng Dẫn 2FA</label>
                            <textarea class="form-control" name="twofa_instruction" id="edit_twofa_instruction" rows="2" placeholder="Đăng nhập Chat GPT bằng Email và sử dụng 2fa để nhận mã OTP." style="padding: 8px 12px; font-size: 0.9rem;"></textarea>
                            <small class="text-muted">Chỉ hiển thị khi chọn Phong cách 2 (2FA)</small>
                        </div>
                        
                        <div class="form-group" style="margin-top: 12px;">
                            <label class="form-label" style="font-size: 0.85rem;">Link Đăng Nhập</label>
                            <input type="url" class="form-control" name="login_url" id="edit_login_url" placeholder="https://outlook.office.com/" style="padding: 8px 12px; font-size: 0.9rem;">
                            <small class="text-muted">Link sẽ hiển thị trong tin nhắn trả hàng</small>
                        </div>
                        
                        <div class="form-group" style="margin-top: 12px;">
                            <label class="form-label" style="font-size: 0.85rem;">Tin Nhắn Tùy Chỉnh</label>
                            <textarea class="form-control" name="custom_message" id="edit_custom_message" rows="2" placeholder="Vui lòng đổi mật khẩu sau khi đăng nhập..." style="padding: 8px 12px; font-size: 0.9rem;"></textarea>
                            <small class="text-muted">Tin nhắn bổ sung sẽ hiển thị khi trả hàng</small>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer" style="padding: 12px 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()" style="padding: 8px 16px; font-size: 0.9rem;">Hủy</button>
                    <button type="submit" class="btn btn-primary" style="padding: 8px 16px; font-size: 0.9rem;">💾 Lưu</button>
                </div>
            </form>
            
            <!-- Account Management Section (Outside edit form to avoid nesting) -->
            <div style="padding: 0 20px 20px;">
                <div id="accountManagementSection">
                    <h6 style="margin-bottom: 12px; color: var(--primary); font-weight: 600; font-size: 0.95rem; display: flex; align-items: center; gap: 8px;">
                        🔑 Quản Lý Accounts
                        <span class="badge badge-info" id="accountCounter" style="font-size: 0.8rem;">0 accounts</span>
                    </h6>
                    
                    <!-- Add Accounts Form -->
                    <div style="background: rgba(102, 126, 234, 0.05); padding: 12px; border-radius: 8px; margin-bottom: 12px;">
                        <form method="POST" id="addAccountsForm" style="margin-bottom: 0;">
                            <input type="hidden" name="action" value="add_accounts">
                            <input type="hidden" name="product_id" id="add_accounts_product_id">
                            
                            <div class="form-group" style="margin-bottom: 10px;">
                                <label class="form-label" style="font-size: 0.85rem;">Thêm Accounts (mỗi dòng 1 acc)</label>
                                <textarea class="form-control" name="accounts_text" id="accounts_text" rows="3" placeholder="user pass&#10;user:pass&#10;user|pass" style="padding: 8px 12px; font-size: 0.85rem;"></textarea>
                                <small style="color: var(--text-secondary); font-size: 0.75rem; margin-top: 4px; display: block;">
                                    💡 <code style="font-size: 0.75rem;">user pass</code>, <code style="font-size: 0.75rem;">user:pass</code>, <code style="font-size: 0.75rem;">user|pass</code>
                                </small>
                            </div>
                            
                            <button type="submit" class="btn btn-success btn-sm" style="padding: 6px 12px; font-size: 0.85rem;">➕ Thêm</button>
                        </form>
                    </div>

                    <!-- Upload File Form -->
                    <div style="background: rgba(102, 126, 234, 0.05); padding: 12px; border-radius: 8px; margin-bottom: 12px;">
                        <form method="POST" enctype="multipart/form-data" id="uploadAccountsForm" style="margin-bottom: 0;">
                            <input type="hidden" name="action" value="upload_accounts_file">
                            <input type="hidden" name="product_id" id="upload_accounts_product_id">
                            
                            <div class="form-group" style="margin-bottom: 10px;">
                                <label class="form-label" style="font-size: 0.85rem;">📁 Upload File .txt</label>
                                <input type="file" class="form-control" name="accounts_file" accept=".txt" required style="padding: 6px 10px; font-size: 0.85rem;">
                            </div>
                            
                            <button type="submit" class="btn btn-info btn-sm" style="padding: 6px 12px; font-size: 0.85rem;">📤 Upload</button>
                        </form>
                    </div>

                    <!-- Current Accounts List -->
                    <div>
                        <h6 style="margin-bottom: 10px; font-weight: 600; font-size: 0.9rem;">📋 Accounts Hiện Tại</h6>
                        <div id="currentAccountsList" style="max-height: 200px; overflow-y: auto; background: rgba(0, 0, 0, 0.2); border-radius: 8px; padding: 10px;">
                            <!-- Will be populated by JavaScript -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/includes/footer.php'; ?>

    <script>
        function showAddModal() {
            document.getElementById('addProductModal').classList.add('show');
        }

        async function editProduct(product) {
            document.getElementById('edit_id').value = product.id;
            document.getElementById('edit_name').value = product.name;
            document.getElementById('edit_description').value = product.description || '';
            document.getElementById('edit_category').value = product.category || '';
            document.getElementById('edit_price').value = product.price;
            document.getElementById('edit_delivery_style').value = product.delivery_style || 'default';
            document.getElementById('edit_login_url').value = product.login_url || '';
            document.getElementById('edit_custom_message').value = product.custom_message || '';
            document.getElementById('edit_twofa_instruction').value = product.twofa_instruction || '';
            
            // Show/hide 2FA instruction field based on delivery_style
            toggleTwoFAField('edit', product.delivery_style || 'default');
            
            // Set product ID for account forms
            document.getElementById('add_accounts_product_id').value = product.id;
            document.getElementById('upload_accounts_product_id').value = product.id;
            
            // Load accounts for this product
            await loadProductAccounts(product.id);
            
            document.getElementById('editProductModal').classList.add('show');
        }
        
        // Toggle 2FA instruction field visibility
        function toggleTwoFAField(prefix, style) {
            const twoFAGroup = document.getElementById(prefix + '_twofa_instruction_group');
            if (twoFAGroup) {
                twoFAGroup.style.display = (style === 'style2') ? 'block' : 'none';
            }
        }
        
        // Add event listeners for delivery_style changes
        document.addEventListener('DOMContentLoaded', function() {
            // For add modal
            const addStyleSelect = document.getElementById('add_delivery_style');
            if (addStyleSelect) {
                addStyleSelect.addEventListener('change', function(e) {
                    toggleTwoFAField('add', e.target.value);
                });
            }
            
            // For edit modal
            const editStyleSelect = document.getElementById('edit_delivery_style');
            if (editStyleSelect) {
                editStyleSelect.addEventListener('change', function(e) {
                    toggleTwoFAField('edit', e.target.value);
                });
            }
        });

        async function loadProductAccounts(productId) {
            try {
                const response = await fetch(`get_product_accounts.php?product_id=${productId}`);
                const data = await response.json();
                
                if (data.error) {
                    throw new Error(data.error);
                }
                
                const accounts = data.accounts || [];
                
                // Update counter
                const availableCount = data.available || 0;
                const totalCount = data.total || 0;
                document.getElementById('accountCounter').textContent = `${availableCount}/${totalCount} accounts`;
                
                // Display accounts list
                const accountsList = document.getElementById('currentAccountsList');
                if (accounts.length === 0) {
                    accountsList.innerHTML = '<p style="color: var(--text-secondary); text-align: center; padding: 15px; margin: 0; font-size: 0.85rem;">Chưa có account nào</p>';
                } else {
                    accountsList.innerHTML = accounts.map(account => `
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 8px 10px; background: rgba(255, 255, 255, 0.03); border-radius: 6px; margin-bottom: 6px; border-left: 2px solid ${account.is_sold ? 'var(--danger)' : 'var(--success)'};">
                            <div style="flex: 1; min-width: 0;">
                                <code style="font-size: 0.8rem; color: var(--text-primary); word-break: break-all;">${escapeHtml(account.username + ' ' + account.password)}</code>
                                ${account.is_sold ? `<br><small style="color: var(--danger); font-size: 0.75rem;">❌ Đã bán cho ${account.buyer_username || 'N/A'}</small>` : ''}
                            </div>
                            ${!account.is_sold ? `
                                <form method="POST" style="margin: 0; margin-left: 8px;" onsubmit="return confirm('Xóa account này?')">
                                    <input type="hidden" name="action" value="delete_account">
                                    <input type="hidden" name="account_id" value="${account.id}">
                                    <button type="submit" class="btn btn-danger btn-sm" style="padding: 4px 8px; font-size: 0.75rem;">🗑️</button>
                                </form>
                            ` : ''}
                        </div>
                    `).join('');
                }
            } catch (error) {
                console.error('Error loading accounts:', error);
                document.getElementById('currentAccountsList').innerHTML = '<p style="color: var(--danger); font-size: 0.85rem;">Lỗi tải accounts: ' + error.message + '</p>';
            }
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
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
    
    <!-- SortableJS for drag-and-drop -->
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <script>
        // Initialize drag-and-drop
        const productsList = document.getElementById('productsList');
        
        if (productsList) {
            const sortable = new Sortable(productsList, {
                animation: 150,
                handle: '.drag-handle',
                ghostClass: 'sortable-ghost',
                chosenClass: 'sortable-chosen',
                dragClass: 'sortable-drag',
                onEnd: function(evt) {
                    // Get new order
                    const rows = productsList.querySelectorAll('tr');
                    const productIds = Array.from(rows).map(row => row.dataset.productId);
                    
                    // Save to server
                    fetch('api/update_product_order.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            product_ids: productIds
                        })
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            console.log('✅ Product order updated');
                        } else {
                            console.error('❌ Failed to update order:', data.error);
                            alert('Lỗi: ' + data.error);
                        }
                    })
                    .catch(e => {
                        console.error('❌ Error:', e);
                        alert('Lỗi khi cập nhật thứ tự!');
                    });
                }
            });
        }
    </script>
    
    <style>
        .sortable-ghost {
            opacity: 0.4;
            background: var(--primary) !important;
        }
        
        .sortable-chosen {
            background: rgba(102, 126, 234, 0.1);
        }
        
        .sortable-drag {
            opacity: 1;
        }
        
        .drag-handle {
            font-size: 18px;
            line-height: 1;
            user-select: none;
        }
        
        .drag-handle:hover {
            color: var(--primary);
        }
    </style>
</body>
</html>

