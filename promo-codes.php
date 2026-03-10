<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

    $success = '';
    $error = '';

    // Handle actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action === 'create') {
            $code = strtoupper(trim($_POST['code'] ?? ''));
            $creditAmount = floatval($_POST['credit_amount'] ?? 0);
            $maxUses = intval($_POST['max_uses'] ?? 50);
            $description = trim($_POST['description'] ?? '');
            $status = $_POST['status'] ?? 'active';
            
            if ($code && $creditAmount > 0 && $maxUses > 0) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO promo_codes (code, credit_amount, description, max_uses, status, created_by) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$code, $creditAmount, $description, $maxUses, $status, $_SESSION['user_id']]);
                    $success = "✅ Đã tạo mã khuyến mãi: $code (+".number_format($creditAmount, 0, ',', '.')." VND)";
                } catch (PDOException $e) {
                    if ($e->getCode() == 23000) {
                        $error = "❌ Mã $code đã tồn tại!";
                    } else {
                        $error = "❌ Lỗi: " . $e->getMessage();
                    }
                }
            } else {
                $error = "❌ Vui lòng điền đầy đủ thông tin hợp lệ!";
            }
        } elseif ($action === 'delete') {
            $id = intval($_POST['id'] ?? 0);
            if ($id) {
                $pdo->prepare("DELETE FROM promo_codes WHERE id = ?")->execute([$id]);
                $success = "✅ Đã xóa mã khuyến mãi!";
            }
        } elseif ($action === 'toggle_status') {
            $id = intval($_POST['id'] ?? 0);
            $newStatus = $_POST['new_status'] ?? 'inactive';
            if ($id) {
                $pdo->prepare("UPDATE promo_codes SET status = ? WHERE id = ?")->execute([$newStatus, $id]);
                $success = "✅ Đã cập nhật trạng thái!";
            }
        }
    }

    // Get all promo codes
    $promoCodes = $pdo->query("
        SELECT pc.*,
               (SELECT COUNT(*) FROM promo_code_usage WHERE promo_code_id = pc.id) as actual_uses
        FROM promo_codes pc
        ORDER BY pc.created_at DESC
    ")->fetchAll();

    // Get products for dropdown (not needed anymore but keep for future)
    $products = $pdo->query("SELECT id, name FROM products ORDER BY name")->fetchAll();

    // Get stats
    $totalCodes = count($promoCodes);
    $activeCodes = count(array_filter($promoCodes, fn($p) => $p['status'] === 'active'));
    $totalUses = $pdo->query("SELECT COUNT(*) FROM promo_code_usage")->fetchColumn();

$pageTitle = 'Promo Codes';
include __DIR__ . '/includes/header.php';
?>

    <div class="page-header">
        <h2>🎟️ Quản Lý Mã Khuyến Mãi</h2>
        <p>Tạo mã khuyến mãi cộng tiền vào ví user</p>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon">🎟️</div>
                <div class="stat-value"><?= $totalCodes ?></div>
                <div class="stat-label">Tổng Mã</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-value"><?= $activeCodes ?></div>
                <div class="stat-label">Đang Hoạt Động</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon">📊</div>
                <div class="stat-value"><?= $totalUses ?></div>
                <div class="stat-label">Lượt Sử Dụng</div>
            </div>
        </div>
    </div>

    <!-- Create Form -->
    <div class="card mb-4">
        <div class="card-header">
            <h5>➕ Tạo Mã Khuyến Mãi Mới</h5>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="create">
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Mã Code *</label>
                        <input type="text" class="form-control" name="code" required placeholder="VD: WELCOME10K" style="text-transform: uppercase;">
                        <small style="color: var(--text-secondary);">Chữ in hoa, không dấu, không khoảng trắng</small>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Số Tiền Cộng Vào Ví (VND) *</label>
                        <input type="number" class="form-control" name="credit_amount" required min="1000" step="1000" placeholder="10000">
                        <small style="color: var(--text-secondary);">Số tiền sẽ được cộng vào ví user</small>
                    </div>
                </div>

                <div class="row g-3 mt-2">
                    <div class="col-md-12">
                        <label class="form-label">Mô Tả</label>
                        <textarea class="form-control" name="description" rows="2" placeholder="VD: Chào mừng user mới"></textarea>
                    </div>
                </div>

                <div class="row g-3 mt-2">
                    <div class="col-md-6">
                        <label class="form-label">Số Lượt Sử Dụng *</label>
                        <input type="number" class="form-control" name="max_uses" required min="1" value="50">
                        <small style="color: var(--text-secondary);">Tổng số user được sử dụng mã này</small>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Trạng Thái</label>
                        <select class="form-control" name="status">
                            <option value="active">✅ Hoạt động</option>
                            <option value="inactive">❌ Tạm dừng</option>
                        </select>
                    </div>
                </div>

                <div class="mt-3">
                    <button type="submit" class="btn btn-primary">➕ Tạo Mã</button>
                </div>
            </form>
        </div>
    </div>

    <!-- List Promo Codes -->
    <div class="card">
        <div class="card-header">
            <h5>📋 Danh Sách Mã Khuyến Mãi</h5>
        </div>
        <div class="card-body">
            <?php if (empty($promoCodes)): ?>
                <p style="color: var(--text-secondary); text-align: center; padding: 30px;">Chưa có mã khuyến mãi nào</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Mã</th>
                                <th>Số Tiền</th>
                                <th>Mô Tả</th>
                                <th>Đã Dùng</th>
                                <th>Trạng Thái</th>
                                <th>Ngày Tạo</th>
                                <th>Thao Tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($promoCodes as $promo): ?>
                                <tr>
                                    <td><strong style="font-family: monospace; font-size: 1.1em;"><?= htmlspecialchars($promo['code']) ?></strong></td>
                                    <td><span class="badge badge-success">+<?= number_format($promo['credit_amount'], 0, ',', '.') ?> VND</span></td>
                                    <td><?= $promo['description'] ? htmlspecialchars($promo['description']) : '<em style="color: var(--text-secondary);">-</em>' ?></td>
                                    <td>
                                        <strong><?= $promo['actual_uses'] ?></strong> / <?= $promo['max_uses'] ?>
                                        <?php if ($promo['actual_uses'] >= $promo['max_uses']): ?>
                                            <span style="color: red;">⚠️ Hết</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($promo['status'] === 'active'): ?>
                                            <span class="badge badge-success">✅ Active</span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary">❌ Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('d/m/Y H:i', strtotime($promo['created_at'])) ?></td>
                                    <td>
                                        <div style="display: flex; gap: 5px;">
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="id" value="<?= $promo['id'] ?>">
                                                <input type="hidden" name="new_status" value="<?= $promo['status'] === 'active' ? 'inactive' : 'active' ?>">
                                                <button type="submit" class="btn btn-secondary btn-sm" title="<?= $promo['status'] === 'active' ? 'Tạm dừng' : 'Kích hoạt' ?>">
                                                    <?= $promo['status'] === 'active' ? '⏸️' : '▶️' ?>
                                                </button>
                                            </form>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Xóa mã <?= htmlspecialchars($promo['code']) ?>?')">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= $promo['id'] ?>">
                                                <button type="submit" class="btn btn-danger btn-sm">🗑️</button>
                                            </form>
                                        </div>
                                    </td>
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
        .row { display: flex; flex-wrap: wrap; margin: -10px; }
        .col-md-3 { flex: 0 0 25%; padding: 10px; }
        .col-md-4 { flex: 0 0 33.333%; padding: 10px; }
        .col-md-6 { flex: 0 0 50%; padding: 10px; }
        .g-3 { gap: 15px; }
        .g-4 { gap: 20px; }
        @media (max-width: 768px) { 
            .col-md-3, .col-md-4, .col-md-6 { flex: 0 0 100%; } 
        }
    </style>
