<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<pre>";
echo "=== DIAGNÓSTICO SISTEMA ===\n\n";

// 1. Sesión
echo "1. SESIÓN:\n";
echo "   Session ID: " . session_id() . "\n";
echo "   Session status: " . session_status() . " (2=ACTIVE)\n";
print_r($_SESSION);
echo "\n";

// 2. Archivos incluidos
echo "2. ARCHIVOS INCLUIDOS:\n";
$included = get_included_files();
foreach ($included as $i => $file) {
    echo "   [$i] " . $file . "\n";
}
echo "\n";

// 3. Variables globales
echo "3. VARIABLES GLOBALES:\n";
echo "   Tenant ID en sesión: " . ($_SESSION['tenant_id'] ?? 'NO SET') . "\n";
echo "   User ID en sesión: " . ($_SESSION['user_id'] ?? 'NO SET') . "\n";
echo "   Role en sesión: " . ($_SESSION['role'] ?? 'NO SET') . "\n";
echo "\n";

// 4. Constantes/Config
echo "4. CONFIGURACIÓN:\n";
defined('BASE_PATH') && echo "   BASE_PATH: " . BASE_PATH . "\n";
defined('APP_ENV') && echo "   APP_ENV: " . APP_ENV . "\n";
echo "</pre>";
