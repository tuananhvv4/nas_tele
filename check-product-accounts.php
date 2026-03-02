<?php
require_once __DIR__ . '/config/db.php';

echo "<h2>product_accounts Table Structure</h2>";
$stmt = $pdo->query("SHOW COLUMNS FROM product_accounts");
$columns = $stmt->fetchAll();
echo "<table border='1'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
foreach ($columns as $col) {
    echo "<tr>";
    echo "<td>{$col['Field']}</td>";
    echo "<td>{$col['Type']}</td>";
    echo "<td>{$col['Null']}</td>";
    echo "<td>{$col['Key']}</td>";
    echo "<td>{$col['Default']}</td>";
    echo "</tr>";
}
echo "</table><br>";

echo "<h2>Sample Data</h2>";
$stmt = $pdo->query("SELECT * FROM product_accounts LIMIT 5");
$accounts = $stmt->fetchAll();
echo "<pre>";
print_r($accounts);
echo "</pre>";
