<?php
// prediction.php - Integrado con diseño existente
session_start();
require_once '../config/config.php';

// Verificar login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$tenantId = $_SESSION['tenant_id'] ?? 1;

// Función para llamar API Python (mantener igual)
function callFastAPI($endpoint) {
    $url = 'http://api.getjornix.com' . $endpoint;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Content-Type: application/json'
    ]);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        return json_decode($response, true);
    }
    return ['success' => false, 'error' => "HTTP $httpCode"];
}

// Obtener predicciones
$predictionsData = callFastAPI("/predict/risk?tenant_id={$tenantId}&days=30");
$predictions = $predictionsData['predictions'] ?? [];
$stats = $predictionsData['stats'] ?? [];
$error = $predictionsData['error'] ?? null;

// Entrenar modelo si se solicita
$trainMessage = null;
if (isset($_POST['train_model'])) {
    $trainResult = callFastAPI("/train/model?tenant_id={$tenantId}&days=90");
    $trainMessage = $trainResult['success'] ?? false ? 
        'Model trained successfully' : 
        'Training failed: ' . ($trainResult['error'] ?? 'Unknown error');
}

// Configurar variables para la vista
$pageTitle = "Risk Prediction";
$breadcrumbs = [
    ['name' => 'Dashboard', 'url' => 'dashboard.php'],
    ['name' => 'Prediction', 'url' => 'prediction.php']
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - Smart Attendance</title>
    <!-- Incluir CSS del sistema existente -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .risk-high { background-color: #fef2f2; color: #dc2626; }
        .risk-medium { background-color: #fffbeb; color: #d97706; }
        .risk-low { background-color: #f0fdf4; color: #16a34a; }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Incluir sidebar si existe -->
    <?php if (file_exists('includes/sidebar.php')): ?>
        <?php include 'includes/sidebar.php'; ?>
    <?php endif; ?>
    
    <!-- Main Content -->
    <div class="ml-0 md:ml-64">
        <!-- Header -->
        <?php if (file_exists('includes/header.php')): ?>
            <?php include 'includes/header.php'; ?>
        <?php else: ?>
            <header class="bg-white shadow">
                <div class="px-6 py-4">
                    <div class="flex justify-between items-center">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-800"><?= $pageTitle ?></h1>
                            <nav class="flex space-x-2 text-sm text-gray-600 mt-1">
                                <?php foreach ($breadcrumbs as $crumb): ?>
                                    <a href="<?= $crumb['url'] ?>" class="hover:text-blue-600"><?= $crumb['name'] ?></a>
                                    <span class="text-gray-400">/</span>
                                <?php endforeach; ?>
                            </nav>
                        </div>
                        <div class="flex items-center space-x-4">
                            <span class="text-gray-700">Tenant <?= $tenantId ?></span>
                            <a href="logout.php" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700">Logout</a>
                        </div>
                    </div>
                </div>
            </header>
        <?php endif; ?>
        
        <!-- Content -->
        <main class="p-6">
            <!-- Mensajes -->
            <?php if ($trainMessage): ?>
            <div class="mb-6 p-4 <?= strpos($trainMessage, 'successfully') ? 'bg-green-100 text-green-800 border border-green-200' : 'bg-red-100 text-red-800 border border-red-200' ?> rounded-lg">
                <i class="fas <?= strpos($trainMessage, 'successfully') ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-2"></i>
                <?= htmlspecialchars($trainMessage) ?>
            </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
            <div class="mb-6 p-4 bg-red-100 text-red-800 border border-red-200 rounded-lg">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                Error: <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>
            
            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-xl shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 bg-blue-100 text-blue-600 rounded-lg mr-4">
                            <i class="fas fa-users text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Total Employees</p>
                            <p class="text-2xl font-bold text-gray-800"><?= $stats['total'] ?? 0 ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 bg-red-100 text-red-600 rounded-lg mr-4">
                            <i class="fas fa-exclamation-triangle text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">High Risk</p>
                            <p class="text-2xl font-bold text-red-600"><?= $stats['high_risk'] ?? 0 ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 bg-yellow-100 text-yellow-600 rounded-lg mr-4">
                            <i class="fas fa-exclamation-circle text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Medium Risk</p>
                            <p class="text-2xl font-bold text-yellow-600"><?= $stats['medium_risk'] ?? 0 ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 bg-green-100 text-green-600 rounded-lg mr-4">
                            <i class="fas fa-check-circle text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Low Risk</p>
                            <p class="text-2xl font-bold text-green-600"><?= $stats['low_risk'] ?? 0 ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Actions Bar -->
            <div class="bg-white rounded-xl shadow p-4 mb-6">
                <div class="flex justify-between items-center">
                    <h2 class="text-lg font-semibold text-gray-800">Risk Predictions</h2>
                    <div class="flex space-x-3">
                        <a href="test_api.php" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                            <i class="fas fa-bolt mr-2"></i>Test API
                        </a>
                        <form method="POST">
                            <button type="submit" name="train_model" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                <i class="fas fa-brain mr-2"></i>Train Model
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Predictions Table -->
            <div class="bg-white rounded-xl shadow overflow-hidden mb-8">
                <div class="px-6 py-4 border-b">
                    <h3 class="text-lg font-semibold text-gray-800">Top 20 High Risk Employees</h3>
                    <p class="text-sm text-gray-600">Based on attendance patterns from last 30 days</p>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Risk Score</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Risk Level</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tardiness Rate</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Avg Check-in</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php if (!empty($predictions)): ?>
                                <?php foreach ($predictions as $pred): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="w-8 h-8 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center mr-3">
                                                <i class="fas fa-user"></i>
                                            </div>
                                            <span class="font-medium"><?= htmlspecialchars($pred['employee_id']) ?></span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="w-16 bg-gray-200 rounded-full h-2 mr-3">
                                                <div class="h-2 rounded-full <?= $pred['risk_score'] > 0.7 ? 'bg-red-500' : ($pred['risk_score'] > 0.3 ? 'bg-yellow-500' : 'bg-green-500') ?>" 
                                                     style="width: <?= $pred['risk_score'] * 100 ?>%"></div>
                                            </div>
                                            <span class="font-medium"><?= number_format($pred['risk_score'], 3) ?></span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-3 py-1 rounded-full text-xs font-medium 
                                            <?= $pred['risk_level'] == 'high' ? 'risk-high' : 
                                               ($pred['risk_level'] == 'medium' ? 'risk-medium' : 'risk-low') ?>">
                                            <i class="fas fa-<?= $pred['risk_level'] == 'high' ? 'exclamation-triangle' : ($pred['risk_level'] == 'medium' ? 'exclamation-circle' : 'check-circle') ?> mr-1"></i>
                                            <?= ucfirst($pred['risk_level']) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="w-20 bg-gray-200 rounded-full h-2 mr-3">
                                                <div class="bg-red-500 h-2 rounded-full" style="width: <?= min($pred['tardiness_rate'] * 100, 100) ?>%"></div>
                                            </div>
                                            <span><?= number_format($pred['tardiness_rate'] * 100, 1) ?>%</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-gray-600">
                                        <i class="fas fa-clock mr-2"></i>
                                        <?= sprintf('%02d:%02d', floor($pred['avg_checkin_hour']), round(($pred['avg_checkin_hour'] - floor($pred['avg_checkin_hour'])) * 60)) ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                                        <div class="text-4xl mb-4 text-gray-300">
                                            <i class="fas fa-chart-bar"></i>
                                        </div>
                                        <div class="text-lg mb-2">No predictions available</div>
                                        <p class="text-sm">Try training the model first</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- API Info -->
            <div class="bg-blue-50 border border-blue-200 rounded-xl p-6">
                <h3 class="text-lg font-semibold text-blue-800 mb-3">
                    <i class="fas fa-server mr-2"></i>ML API Status
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-blue-700 mb-1">FastAPI Service:</p>
                        <code class="bg-white border border-blue-200 px-3 py-1 rounded text-sm">http://api.getjornix.com</code>
                    </div>
                    <div>
                        <p class="text-sm text-blue-700 mb-1">Endpoints:</p>
                        <div class="flex flex-wrap gap-2">
                            <a href="http://api.getjornix.com/health" target="_blank" 
                               class="px-3 py-1 bg-white border border-blue-200 rounded text-sm text-blue-600 hover:bg-blue-50">
                                /health
                            </a>
                            <a href="http://api.getjornix.com/predict/risk?tenant_id=1" target="_blank"
                               class="px-3 py-1 bg-white border border-blue-200 rounded text-sm text-blue-600 hover:bg-blue-50">
                                /predict/risk
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        // Script para actualizar automáticamente cada 5 minutos
        setTimeout(function() {
            window.location.reload();
        }, 300000); // 5 minutos
    </script>
</body>
</html>
