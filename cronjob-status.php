<?php
/**
 * Status page for cronjob payment checker
 */

$logFile = __DIR__ . '/logs/cronjob-payment-check.log';

echo "<h2>🔍 Cronjob Payment Checker Status</h2>";
echo "<hr>";

if (file_exists($logFile)) {
    $lines = file($logFile);
    $lastLine = end($lines);
    
    preg_match('/\[(.*?)\]/', $lastLine, $matches);
    $lastUpdate = $matches[1] ?? 'Unknown';
    
    $lastUpdateTime = strtotime($lastUpdate);
    $now = time();
    $diff = $now - $lastUpdateTime;
    
    echo "<h3>Status:</h3>";
    
    if ($diff < 120) { // Within 2 minutes
        echo "<p style='color:green; font-size:20px;'>✅ <strong>RUNNING OK</strong></p>";
        echo "<p>Last activity: <strong>" . htmlspecialchars($lastUpdate) . "</strong> (" . $diff . " seconds ago)</p>";
    } else {
        echo "<p style='color:red; font-size:20px;'>❌ <strong>NOT RUNNING</strong></p>";
        echo "<p>Last activity: <strong>" . htmlspecialchars($lastUpdate) . "</strong> (" . round($diff/60) . " minutes ago)</p>";
    }
    
    echo "<h4>Recent Activity (Last 20 lines):</h4>";
    echo "<pre style='background:#f5f5f5; padding:15px; max-height:400px; overflow-y:auto;'>";
    echo htmlspecialchars(implode('', array_slice($lines, -20)));
    echo "</pre>";
    
} else {
    echo "<p style='color:red;'>❌ Log not found - Cronjob has never run</p>";
}

echo "<hr>";
echo "<p><a href='logs/cronjob-payment-check.log' target='_blank'>📄 View Full Log</a></p>";
echo "<p><em>Auto-refresh in <span id='countdown'>10</span> seconds...</em></p>";

?>

<script>
let count = 10;
setInterval(() => {
    count--;
    document.getElementById('countdown').textContent = count;
    if (count <= 0) {
        location.reload();
    }
}, 1000);
</script>
