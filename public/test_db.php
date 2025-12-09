<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Services/DB.php';

try {
    $db = DB::getInstance()->getConnection();
    echo "Conexión a la base de datos exitosa!";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
