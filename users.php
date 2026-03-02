<?php
/**
 * User Management Page
 * View and manage users with wallet balances
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/wallet_helper.php';
require_once __DIR__ . '/includes/vietqr.php'; // For formatVND()
requireLogin();

$success = '';
$error = '';

// Handle balance adjustment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'adjust_balance') {
        $userId = intval($_POST['user_id']);
        $telegramId = intval($_POST['telegram_id']);
        $amount = floatval($_POST['amount']);
        $description = trim($_POST['description'] ?? 'Admin adjustment');
        
        if ($userId && $amount != 0) {
            if ($amount > 0) {
                // Add to wallet
                $result = addToWallet($userId, $telegramId, $amount, 'admin_adjust', $description, null, $pdo);
                if ($result) {
                    $success = "✅ Đã cộng " . formatVND(abs($amount)) . " vào ví user!";
                } else {
                    $error = "❌ Lỗi khi cộng tiền!";
                }
            } else {
                // Deduct from wallet
                $result = deductFromWallet($userId, $telegramId, abs($amount), 'admin_adjust', $description, null, $pdo);
                if ($result['success']) {
                    $success = "✅ Đã trừ " . formatVND(abs($amount)) . " từ ví user!";
                } else {
                    $error = "❌ " . $result['message'];
                }
            }
        }
    }
}

// Get search/filter params
$search = $_GET['search'] ?? '';
$orderBy = $_GET['order'] ?? 'created_at';
$orderDir = $_GET['dir'] ?? 'DESC';

// Build query
$where = "1=1";
$params = [];

if ($search) {
    $where .= " AND (username LIKE ? OR telegram_id LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Get users
$stmt = $pdo->prepare("
    SELECT 
        u.*,
        (SELECT COUNT(*) FROM orders WHERE user_id = u.id) as total_orders,
        (SELECT COUNT(*) FROM orders WHERE user_id = u.id AND payment_status = 'completed') as completed_orders
    FROM users u
    WHERE $where
    ORDER BY $orderBy $orderDir
");
$stmt->execute($params);
$users = $stmt->fetchAll();

// Debug
error_log("Users query: WHERE=$where, params=" . json_encode($params));
error_log("Users count: " . count($users));
if (count($users) > 0) {
    error_log("First user: " . json_encode($users[0]));
}

// Get stats
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalWalletBalance = $pdo->query("SELECT SUM(wallet_balance) FROM users")->fetchColumn();
$usersWithBalance = $pdo->query("SELECT COUNT(*) FROM users WHERE wallet_balance > 0")->fetchColumn();

$pageTitle = 'Users';
include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h2>👥 Quản Lý Users</h2>
    <p>Xem và quản lý users, wallet balance</p>
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
            <div class="stat-icon">👥</div>
            <div class="stat-value"><?= $totalUsers ?></div>
            <div class="stat-label">Tổng Users</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card">
            <div class="stat-icon">💰</div>
            <div class="stat-value"><?= formatVND($totalWalletBalance) ?></div>
            <div class="stat-label">Tổng Số Dư Ví</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card">
            <div class="stat-icon">💳</div>
            <div class="stat-value"><?= $usersWithBalance ?></div>
            <div class="stat-label">Users Có Số Dư</div>
        </div>
    </div>
</div>

<!-- Search & Filter -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-6">
                <input type="text" class="form-control" name="search" placeholder="Tìm username hoặc Telegram ID..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-3">
                <select class="form-control" name="order">
                    <option value="created_at" <?= $orderBy === 'created_at' ? 'selected' : '' ?>>Ngày tạo</option>
                    <option value="wallet_balance" <?= $orderBy === 'wallet_balance' ? 'selected' : '' ?>>Số dư ví</option>
                    <option value="username" <?= $orderBy === 'username' ? 'selected' : '' ?>>Username</option>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary" style="width: 100%;">🔍 Tìm kiếm</button>
            </div>
        </form>
    </div>
</div>

<!-- Users List -->
<div class="card">
    <div class="card-header">
        <h5>📋 Danh Sách Users (<?= count($users) ?>)</h5>
    </div>
    <div class="card-body">
        <!-- DEBUG OUTPUT -->
        <div style="background: #ff0; color: #000; padding: 10px; margin-bottom: 10px;">
            <strong>DEBUG:</strong> Users count = <?= count($users) ?><br>
            Query executed: <?= isset($users) ? 'YES' : 'NO' ?><br>
            Is array: <?= is_array($users) ? 'YES' : 'NO' ?><br>
            <?php if (count($users) > 0): ?>
                First user ID: <?= $users[0]['id'] ?? 'N/A' ?><br>
                First user username: <?= $users[0]['username'] ?? 'N/A' ?>
            <?php endif; ?>
        </div>
        
        <?php if (empty($users)): ?>
            <p style="color: var(--text-secondary); text-align: center; padding: 30px;">Không tìm thấy user nào</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Telegram ID</th>
                            <th>Số Dư Ví</th>
                            <th>Đơn Hàng</th>
                            <th>Ngày Tạo</th>
                            <th>Thao Tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?= $user['id'] ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($user['username'] ?? 'N/A') ?></strong>
                                    <?php if ($user['role'] === 'admin'): ?>
                                        <span class="badge badge-danger">Admin</span>
                                    <?php endif; ?>
                                </td>
                                <td><code><?= $user['telegram_id'] ?></code></td>
                                <td>
                                    <strong style="color: <?= $user['wallet_balance'] > 0 ? 'var(--success)' : 'var(--text-secondary)' ?>;">
                                        <?= formatVND($user['wallet_balance']) ?>
                                    </strong>
                                </td>
                                <td>
                                    <?= $user['completed_orders'] ?> / <?= $user['total_orders'] ?>
                                </td>
                                <td><?= date('d/m/Y H:i', strtotime($user['created_at'])) ?></td>
                                <td>
                                    <div style="display: flex; gap: 5px;">
                                        <button class="btn btn-primary btn-sm" onclick="showTransactions(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username']) ?>')">
                                            📊 Lịch sử
                                        </button>
                                        <button class="btn btn-secondary btn-sm" onclick="showAdjustBalance(<?= $user['id'] ?>, <?= $user['telegram_id'] ?>, '<?= htmlspecialchars($user['username']) ?>', <?= $user['wallet_balance'] ?>)">
                                            💰 Điều chỉnh
                                        </button>
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

<!-- Transaction History Modal -->
<div id="transactionModal" style="display: none; position: fixed !important; top: 0 !important; left: 0 !important; right: 0 !important; bottom: 0 !important; background: rgba(0,0,0,0.7) !important; z-index: 9999 !important; padding: 20px; overflow-y: auto;">
    <div style="max-width: 800px; margin: 50px auto; border-radius: 8px; padding: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.5);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 id="transactionTitle">Lịch Sử Giao Dịch</h3>
            <button onclick="closeModal()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: var(--text-primary);">×</button>
        </div>
        <div id="transactionContent">Loading...</div>
    </div>
</div>

<!-- Adjust Balance Modal -->
<div id="adjustModal" style="display: none; position: fixed !important; top: 0 !important; left: 0 !important; right: 0 !important; bottom: 0 !important; background: rgba(0,0,0,0.7) !important; z-index: 9999 !important; padding: 20px; overflow-y: auto;">
    <div style="max-width: 500px; margin: 100px auto; border-radius: 8px; padding: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.5);">
        <h3 id="adjustTitle">Điều Chỉnh Số Dư</h3>
        <form method="POST" style="margin-top: 20px;">
            <input type="hidden" name="action" value="adjust_balance">
            <input type="hidden" name="user_id" id="adjust_user_id">
            <input type="hidden" name="telegram_id" id="adjust_telegram_id">
            
            <div style="margin-bottom: 15px;">
                <label class="form-label">Số Dư Hiện Tại</label>
                <div id="current_balance" style="font-size: 1.5em; font-weight: bold; color: var(--success);"></div>
            </div>
            
            <div style="margin-bottom: 15px;">
                <label class="form-label">Số Tiền Thay Đổi (VND)</label>
                <input type="number" class="form-control" name="amount" step="1000" required placeholder="Dương = cộng, Âm = trừ">
                <small style="color: var(--text-secondary);">Ví dụ: 10000 (cộng) hoặc -5000 (trừ)</small>
            </div>
            
            <div style="margin-bottom: 15px;">
                <label class="form-label">Lý Do</label>
                <textarea class="form-control" name="description" rows="2" required placeholder="VD: Hoàn tiền đơn hàng #123"></textarea>
            </div>
            
            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn btn-primary" style="flex: 1;">💰 Điều Chỉnh</button>
                <button type="button" onclick="closeAdjustModal()" class="btn btn-secondary" style="flex: 1;">Hủy</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script>
function showTransactions(userId, username) {
    document.getElementById('transactionModal').style.display = 'block';
    document.getElementById('transactionTitle').textContent = 'Lịch Sử Giao Dịch - ' + username;
    document.getElementById('transactionContent').innerHTML = 'Loading...';
    
    console.log('Fetching transactions for user:', userId);
    
    // Fetch transactions via AJAX
    fetch('api/get-user-transactions.php?user_id=' + userId)
        .then(r => {
            console.log('Response status:', r.status);
            return r.text();
        })
        .then(text => {
            console.log('Response text:', text);
            try {
                const data = JSON.parse(text);
                console.log('Parsed data:', data);
                
                if (data.error) {
                    document.getElementById('transactionContent').innerHTML = '<p style="color: var(--danger);">Lỗi: ' + data.error + '</p>';
                    return;
                }
                
                if (data.transactions && data.transactions.length > 0) {
                    let html = '<table class="table"><thead><tr><th>Loại</th><th>Số Tiền</th><th>Mô Tả</th><th>Thời Gian</th></tr></thead><tbody>';
                    data.transactions.forEach(tx => {
                        const icon = tx.amount > 0 ? '➕' : '➖';
                        const color = tx.amount > 0 ? 'var(--success)' : 'var(--danger)';
                        html += `<tr>
                            <td>${tx.type}</td>
                            <td style="color: ${color}; font-weight: bold;">${icon} ${Math.abs(tx.amount).toLocaleString('vi-VN')} VND</td>
                            <td>${tx.description || '-'}</td>
                            <td>${new Date(tx.created_at).toLocaleString('vi-VN')}</td>
                        </tr>`;
                    });
                    html += '</tbody></table>';
                    document.getElementById('transactionContent').innerHTML = html;
                } else {
                    document.getElementById('transactionContent').innerHTML = '<p style="text-align: center; color: var(--text-secondary);">Chưa có giao dịch nào</p>';
                }
            } catch (e) {
                console.error('Parse error:', e);
                document.getElementById('transactionContent').innerHTML = '<p style="color: var(--danger);">Lỗi parse JSON: ' + e.message + '<br><br>Response: ' + text + '</p>';
            }
        })
        .catch(e => {
            console.error('Fetch error:', e);
            document.getElementById('transactionContent').innerHTML = '<p style="color: var(--danger);">Lỗi: ' + e.message + '</p>';
        });
}

function showAdjustBalance(userId, telegramId, username, balance) {
    document.getElementById('adjustModal').style.display = 'block';
    document.getElementById('adjustTitle').textContent = 'Điều Chỉnh Số Dư - ' + username;
    document.getElementById('adjust_user_id').value = userId;
    document.getElementById('adjust_telegram_id').value = telegramId;
    document.getElementById('current_balance').textContent = balance.toLocaleString('vi-VN') + ' VND';
}

function closeModal() {
    document.getElementById('transactionModal').style.display = 'none';
}

function closeAdjustModal() {
    document.getElementById('adjustModal').style.display = 'none';
}
</script>

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
    
    /* Modal Backgrounds - Force display */
    #transactionModal,
    #adjustModal {
        background-color: rgba(0, 0, 0, 0.8) !important;
        backdrop-filter: blur(4px) !important;
    }
    
    #transactionModal > div,
    #adjustModal > div {
        background-color: #1a1d2e !important;
        background: linear-gradient(135deg, #1a1d2e 0%, #16213e 100%) !important;
    }
</style>
