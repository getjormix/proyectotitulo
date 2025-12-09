<?php
/**
 * Configuraci車n de Base de Datos - Smart Attendance System
 * RUTA: ~/app-php/config/config.php
 */

// Configuraci車n de Base de Datos
define('DB_HOST', 'localhost');
define('DB_NAME', 'wwgetj_smart_attendance');
define('DB_USER', 'wwgetj_superuser_jornix');
define('DB_PASS', 'pE}$(RGw#T4xaMZF');
define('DB_CHARSET', 'utf8');

// Configuraci車n de Sesi車n
define('SESSION_TIMEOUT', 1800); // 30 minutos
define('SESSION_NAME', 'JORNIX_SESSION');

// Configuraci車n de Seguridad
define('HASH_ALGO', PASSWORD_BCRYPT);
define('HASH_COST', 12);

// Configuraci車n de Facial Recognition
define('FACIAL_CONFIDENCE_THRESHOLD', 0.85);
define('FACIAL_MODEL_VERSION', 'v1');

// Rutas del sistema
define('BASE_PATH', dirname(__DIR__));
define('PUBLIC_PATH', BASE_PATH . '/public');
define('UPLOAD_PATH', BASE_PATH . '/uploads');
define('FACIAL_IMAGES_PATH', UPLOAD_PATH . '/facial');

// URL Base
define('BASE_URL', 'https://' . $_SERVER['HTTP_HOST']);

// Timezone
date_default_timezone_set('America/Santiago');

// Error Reporting (cambiar a 0 en producci車n)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Crear directorios necesarios si no existen
$directories = [
    UPLOAD_PATH,
    FACIAL_IMAGES_PATH,
    BASE_PATH . '/logs'
];

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}