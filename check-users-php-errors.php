<?php
/**
 * Check if users.php has PHP errors
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing users.php loading...\n\n";

ob_start();
try {
    include __DIR__ . '/users.php';
    $output = ob_get_clean();
    
    echo "✅ users.php loaded without fatal errors\n";
    echo "Output length: " . strlen($output) . " bytes\n\n";
    
    if (strlen($output) < 100) {
        echo "⚠️ WARNING: Output is very short! Might be blank.\n";
        echo "Output:\n";
        echo $output;
    } else {
        echo "✅ Output looks normal\n";
        echo "First 500 chars:\n";
        echo substr($output, 0, 500) . "...\n";
    }
    
} catch (Exception $e) {
    ob_end_clean();
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
