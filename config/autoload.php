<?php
/**
 * Autoloader - Carga automática de clases
 * RUTA: ~/app-php/config/autoload.php
 */

// Cargar configuración si no está cargada
if (!defined('DB_HOST')) {
    require_once __DIR__ . '/config.php';
}

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

// Autoloader de clases
spl_autoload_register(function ($class) {
    $baseDir = dirname(__DIR__);
    
    // Mapeo de clases a archivos
    $classMap = [
        'Database' => $baseDir . '/src/Services/DB.php',
        'Auth' => $baseDir . '/src/Services/Auth.php',
    ];
    
    if (isset($classMap[$class])) {
        require_once $classMap[$class];
        return true;
    }
    
    // Buscar en Services
    $servicesFile = $baseDir . '/src/Services/' . $class . '.php';
    if (file_exists($servicesFile)) {
        require_once $servicesFile;
        return true;
    }
    
    // Buscar en Models
    $modelsFile = $baseDir . '/src/Models/' . $class . '.php';
    if (file_exists($modelsFile)) {
        require_once $modelsFile;
        return true;
    }
    
    // Buscar en Controllers
    $controllersFile = $baseDir . '/src/Controllers/' . $class . '.php';
    if (file_exists($controllersFile)) {
        require_once $controllersFile;
        return true;
    }
    
    return false;
});

// Función helper para obtener la instancia de Database
function db() {
    return Database::getInstance();
}

// Función helper para obtener usuario actual
function currentUser() {
    $auth = new Auth();
    return $auth->getCurrentUser();
}

// Función helper para verificar autenticación
function isAuth() {
    $auth = new Auth();
    return $auth->isAuthenticated();
}