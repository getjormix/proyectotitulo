<?php
// Prueba directa de integración PHP-FastAPI
session_start();
echo "<h1>🔄 Prueba Integración PHP-FastAPI</h1>";

// Incluir PythonClient
require_once __DIR__ . '/../src/Services/PythonClient.php';

try {
    $client = new PythonClient();
    echo "<p>✅ PythonClient instanciado</p>";
    
    // Probar con cURL directo (fallback)
    $ch = curl_init('http://localhost:8000/health');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $data = json_decode($response, true);
        echo "<p>✅ FastAPI responde (HTTP $http_code)</p>";
        echo "<pre>Status: " . htmlspecialchars($data['status'] ?? 'N/A') . "</pre>";
        echo "<pre>Python: " . htmlspecialchars($data['python_version'] ?? 'N/A') . "</pre>";
    } else {
        echo "<p>❌ FastAPI no responde (HTTP $http_code)</p>";
    }
    
    // Probar endpoint de predicción
    $ch = curl_init('http://localhost:8000/predict/test');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        echo "<p>✅ Endpoint /predict/test funciona</p>";
    }
    
} catch(Exception $e) {
    echo "<p>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr>";
echo "<p><a href='prediction.php'>Ir a Prediction.php normal</a></p>";
echo "<p><a href='dashboard.php'>Volver al Dashboard</a></p>";
