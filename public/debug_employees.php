<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>DEBUG EMPLOYEES</h1>";

session_start();
echo "<h3>1. SESION:</h3>";
echo "<pre>" . print_r($_SESSION, true) . "</pre>";

echo "<h3>2. AUTOLOAD:</h3>";
$autoloadPath = dirname(__DIR__) . '/config/autoload.php';
if (file_exists($autoloadPath)) {
    echo "OK autoload.php existe<br>";
    
    require_once $autoloadPath;
    echo "OK autoload cargado<br>";
    
    echo "<h3>3. BASE DE DATOS:</h3>";
    try {
        $db = Database::getInstance();
        echo "OK Database class existe<br>";
        
        $result = $db->fetch("SELECT 1 as test");
        echo "OK Query test: " . print_r($result, true) . "<br>";
        
    } catch (Exception $e) {
        echo "ERROR DB: " . $e->getMessage() . "<br>";
    }
    
} else {
    echo "ERROR autoload.php no existe en: " . $autoloadPath . "<br>";
}

echo "<h3>4. ESTRUCTURA:</h3>";
echo "Ruta actual: " . __DIR__ . "<br>";

echo "<hr><a href='dashboard.php'>Volver Dashboard</a>";
