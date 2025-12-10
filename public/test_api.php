<?php
// Test simple sin autoload
$ch = curl_init('http://api.getjornix.com/health');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<h2>API Test</h2>";
echo "<p>HTTP Code: $httpCode</p>";
echo "<p>Response: <pre>" . htmlspecialchars($response) . "</pre></p>";

// Test con tenant
$ch = curl_init('http://api.getjornix.com/predict/risk?tenant_id=1&days=7');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

echo "<h2>Prediction Test</h2>";
echo "<p>HTTP Code: $httpCode</p>";
echo "<p>Response: <pre>" . htmlspecialchars($response) . "</pre></p>";
