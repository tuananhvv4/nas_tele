<?php
/**
 * Smart Migration Script
 * Automatically checks and adds missing columns
 */

require_once __DIR__ . '/config/db.php';

echo "<h1>🔄 Smart Database Migration</h1>";
echo "<hr>";

$success = 0;
$skipped = 0;
$errors = 0;

try {
    // Helper function to check if column exists
    function columnExists($pdo, $table, $column) {
        $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
        return $stmt->rowCount() > 0;
    }

    // Helper function to check if index exists
    function indexExists($pdo, $table, $index) {
        $stmt = $pdo->query("SHOW INDEX FROM `{$table}` WHERE Key_name = '{$index}'");
        return $stmt->rowCount() > 0;
    }

    echo "<h2>📋 Migration Steps:</h2>";
    echo "<ul style='list-style: none; padding: 0;'>";

    // USERS TABLE
    echo "<li><strong>👥 Users Table:</strong></li>";
    
    if (!columnExists($pdo, 'users', 'package')) {
        $pdo->exec("ALTER TABLE users ADD COLUMN package ENUM('free', 'basic', 'premium', 'vip') DEFAULT 'free' AFTER role");
        echo "<li>✅ Added column: package</li>";
        $success++;
    } else {
        echo "<li>⏭️ Column already exists: package</li>";
        $skipped++;
    }

    if (!columnExists($pdo, 'users', 'balance')) {
        $pdo->exec("ALTER TABLE users ADD COLUMN balance DECIMAL(10, 2) DEFAULT 0.00 AFTER package");
        echo "<li>✅ Added column: balance</li>";
        $success++;
    } else {
        echo "<li>⏭️ Column already exists: balance</li>";
        $skipped++;
    }

    if (!columnExists($pdo, 'users', 'is_active')) {
        $pdo->exec("ALTER TABLE users ADD COLUMN is_active TINYINT(1) DEFAULT 1 AFTER balance");
        echo "<li>✅ Added column: is_active</li>";
        $success++;
    } else {
        echo "<li>⏭️ Column already exists: is_active</li>";
        $skipped++;
    }

    if (!columnExists($pdo, 'users', 'updated_at')) {
        $pdo->exec("ALTER TABLE users ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at");
        echo "<li>✅ Added column: updated_at</li>";
        $success++;
    } else {
        echo "<li>⏭️ Column already exists: updated_at</li>";
        $skipped++;
    }

    if (!indexExists($pdo, 'users', 'idx_package')) {
        $pdo->exec("ALTER TABLE users ADD INDEX idx_package (package)");
        echo "<li>✅ Added index: idx_package</li>";
        $success++;
    } else {
        echo "<li>⏭️ Index already exists: idx_package</li>";
        $skipped++;
    }

    // BOTS TABLE
    echo "<li><strong>🤖 Bots Table:</strong></li>";
    
    if (!columnExists($pdo, 'bots', 'bot_username')) {
        $pdo->exec("ALTER TABLE bots ADD COLUMN bot_username VARCHAR(100) NULL AFTER bot_name");
        echo "<li>✅ Added column: bot_username</li>";
        $success++;
    } else {
        echo "<li>⏭️ Column already exists: bot_username</li>";
        $skipped++;
    }

    if (!columnExists($pdo, 'bots', 'is_configured')) {
        $pdo->exec("ALTER TABLE bots ADD COLUMN is_configured TINYINT(1) DEFAULT 0 AFTER status");
        echo "<li>✅ Added column: is_configured</li>";
        $success++;
    } else {
        echo "<li>⏭️ Column already exists: is_configured</li>";
        $skipped++;
    }

    if (!columnExists($pdo, 'bots', 'updated_at')) {
        $pdo->exec("ALTER TABLE bots ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at");
        echo "<li>✅ Added column: updated_at (bots)</li>";
        $success++;
    } else {
        echo "<li>⏭️ Column already exists: updated_at (bots)</li>";
        $skipped++;
    }

    // PRODUCTS TABLE
    echo "<li><strong>📦 Products Table:</strong></li>";
    
    if (!columnExists($pdo, 'products', 'image_url')) {
        $pdo->exec("ALTER TABLE products ADD COLUMN image_url VARCHAR(255) NULL AFTER price");
        echo "<li>✅ Added column: image_url</li>";
        $success++;
    } else {
        echo "<li>⏭️ Column already exists: image_url</li>";
        $skipped++;
    }

    if (!columnExists($pdo, 'products', 'category')) {
        $pdo->exec("ALTER TABLE products ADD COLUMN category VARCHAR(100) NULL AFTER image_url");
        echo "<li>✅ Added column: category</li>";
        $success++;
    } else {
        echo "<li>⏭️ Column already exists: category</li>";
        $skipped++;
    }

    if (!columnExists($pdo, 'products', 'updated_at')) {
        $pdo->exec("ALTER TABLE products ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at");
        echo "<li>✅ Added column: updated_at (products)</li>";
        $success++;
    } else {
        echo "<li>⏭️ Column already exists: updated_at (products)</li>";
        $skipped++;
    }

    if (!indexExists($pdo, 'products', 'idx_category')) {
        $pdo->exec("ALTER TABLE products ADD INDEX idx_category (category)");
        echo "<li>✅ Added index: idx_category</li>";
        $success++;
    } else {
        echo "<li>⏭️ Index already exists: idx_category</li>";
        $skipped++;
    }

    // PRODUCT_ACCOUNTS TABLE
    echo "<li><strong>🔑 Product Accounts Table:</strong></li>";
    
    if (!columnExists($pdo, 'product_accounts', 'sold_to_user_id')) {
        $pdo->exec("ALTER TABLE product_accounts ADD COLUMN sold_to_user_id INT NULL AFTER sold_at");
        echo "<li>✅ Added column: sold_to_user_id</li>";
        $success++;
    } else {
        echo "<li>⏭️ Column already exists: sold_to_user_id</li>";
        $skipped++;
    }

    // ORDERS TABLE
    echo "<li><strong>📋 Orders Table:</strong></li>";
    
    if (!columnExists($pdo, 'orders', 'payment_method')) {
        $pdo->exec("ALTER TABLE orders ADD COLUMN payment_method ENUM('balance', 'telegram', 'manual') DEFAULT 'telegram' AFTER price");
        echo "<li>✅ Added column: payment_method</li>";
        $success++;
    } else {
        echo "<li>⏭️ Column already exists: payment_method</li>";
        $skipped++;
    }

    if (!columnExists($pdo, 'orders', 'status')) {
        $pdo->exec("ALTER TABLE orders ADD COLUMN status ENUM('pending', 'completed', 'cancelled') DEFAULT 'completed' AFTER payment_method");
        echo "<li>✅ Added column: status</li>";
        $success++;
    } else {
        echo "<li>⏭️ Column already exists: status</li>";
        $skipped++;
    }

    if (!indexExists($pdo, 'orders', 'idx_user_id')) {
        $pdo->exec("ALTER TABLE orders ADD INDEX idx_user_id (user_id)");
        echo "<li>✅ Added index: idx_user_id</li>";
        $success++;
    } else {
        echo "<li>⏭️ Index already exists: idx_user_id</li>";
        $skipped++;
    }

    if (!indexExists($pdo, 'orders', 'idx_status')) {
        $pdo->exec("ALTER TABLE orders ADD INDEX idx_status (status)");
        echo "<li>✅ Added index: idx_status</li>";
        $success++;
    } else {
        echo "<li>⏭️ Index already exists: idx_status</li>";
        $skipped++;
    }

    // Update admin user
    echo "<li><strong>👤 Admin User:</strong></li>";
    $pdo->exec("UPDATE users SET is_active = 1, package = 'vip', password_hash = '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' WHERE username = 'admin'");
    echo "<li>✅ Updated admin user (password reset to 123123)</li>";
    $success++;

    echo "</ul>";

    echo "<hr>";
    echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; padding: 20px; border-radius: 10px; margin: 20px 0;'>";
    echo "<h2 style='color: #155724; margin-top: 0;'>🎉 Migration Completed!</h2>";
    echo "<p style='color: #155724;'><strong>✅ Added:</strong> {$success} items</p>";
    echo "<p style='color: #155724;'><strong>⏭️ Skipped:</strong> {$skipped} items (already exist)</p>";
    echo "</div>";

    // Verify
    $admin = $pdo->query("SELECT username, role, package, is_active FROM users WHERE username = 'admin'")->fetch();
    
    if ($admin) {
        echo "<div style='background: #cfe2ff; border: 1px solid #b6d4fe; padding: 20px; border-radius: 10px; margin: 20px 0;'>";
        echo "<h3 style='color: #084298; margin-top: 0;'>✅ Admin Account Ready!</h3>";
        echo "<p style='font-size: 1.2rem; color: #084298;'><strong>Username:</strong> admin</p>";
        echo "<p style='font-size: 1.2rem; color: #084298;'><strong>Password:</strong> 123123</p>";
        echo "<p style='font-size: 1.2rem; color: #084298;'><strong>Package:</strong> {$admin['package']}</p>";
        echo "<p style='font-size: 1.2rem; color: #084298;'><strong>Status:</strong> " . ($admin['is_active'] ? 'Active ✅' : 'Inactive ❌') . "</p>";
        echo "</div>";
        
        echo "<a href='auth.php' style='display: inline-block; padding: 15px 40px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; border-radius: 10px; font-weight: bold; font-size: 1.1rem; margin-right: 10px;'>🚀 Đăng Nhập Ngay</a>";
        echo "<a href='debug_login.php' style='display: inline-block; padding: 15px 30px; background: #6c757d; color: white; text-decoration: none; border-radius: 10px; font-weight: bold;'>🔍 Test Login</a>";
    }

} catch (Exception $e) {
    echo "</ul>";
    echo "<hr>";
    echo "<div style='background: #f8d7da; border: 1px solid #f5c2c7; padding: 20px; border-radius: 10px;'>";
    echo "<h2 style='color: #842029;'>❌ Migration Error!</h2>";
    echo "<p style='color: #842029;'>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}

?>

<style>
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        padding: 20px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
    }
    h1, h2, h3 {
        color: #333;
    }
    ul {
        background: white;
        padding: 20px 30px;
        border-radius: 10px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
    li {
        margin: 8px 0;
        padding: 5px 0;
    }
</style>
