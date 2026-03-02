<?php
/**
 * Update items_per_page to 10
 */
require_once __DIR__ . '/config/db.php';

try {
    $pdo->exec("UPDATE bot_settings SET items_per_page = 10 WHERE id = 1");
    echo "✅ Updated items_per_page to 10\n";
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
