<?php
// Test final de integración PHP-FastAPI
session_start();
require_once __DIR__ . '/../src/Services/PythonClient.php';

echo "<h1>🧪 Test Final Integración PHP-FastAPI</h1>";

try {
    $client = new PythonClient();
    echo "<p>✅ PythonClient creado</p>";
    
    // Test 1: Health check
    $health = $client->healthCheck();
    echo "<p>Health Check: " . ($health ? "✅ OK" : "❌ Falló") . "</p>";
    
    // Test 2: Predict
    $prediction = $client->predict(1);
    echo "<p>Predicción para tenant 1: " . ($prediction['error'] ?? "✅ OK") . "</p>";
    
    if (!isset($prediction['error'])) {
        echo "<pre>" . json_encode($prediction, JSON_PRETTY_PRINT) . "</pre>";
    }
    
    // Test 3: Base URL
    $reflection = new ReflectionClass($client);
    $property = $reflection->getProperty('baseUrl');
    $property->setAccessible(true);
    $baseUrl = $property->getValue($client);
    echo "<p>Base URL: " . htmlspecialchars($baseUrl) . "</p>";
    
} catch(Exception $e) {
    echo "<p>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr>";
echo "<h3>📊 Estado FastAPI:</h3>";
$ch = curl_init('http://localhost:8000/health');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$data = json_decode($response, true);
echo "<pre>" . json_encode($data, JSON_PRETTY_PRINT) . "</pre>";

echo "<p><a href='prediction.php'>Ir a Prediction.php real</a></p>";
echo "<p><a href='dashboard.php'>Volver al Dashboard</a></p>";
