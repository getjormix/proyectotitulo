<?php
/**
 * Script de Diagnóstico de Conexión
 * Prueba diferentes configuraciones de charset
 */

// Configuración
$host = 'localhost';
$dbname = 'wwgetj_smart_attendance';
$user = 'wwgetj_superuser_jornix';
$pass = 'pE}$(RGw#T4xaMZF';

echo "═══════════════════════════════════════════════════\n";
echo "  DIAGNÓSTICO DE CONEXIÓN - JORNIX\n";
echo "═══════════════════════════════════════════════════\n\n";

echo "Probando conexión a base de datos...\n\n";

// Prueba 1: Sin charset en DSN
echo "1️⃣ Prueba: Sin charset en DSN\n";
try {
    $dsn = "mysql:host={$host};dbname={$dbname}";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->exec("SET NAMES utf8");
    echo "   ✅ Conexión exitosa\n";
    $pdo = null;
} catch (PDOException $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}
echo "\n";

// Prueba 2: Con charset=utf8
echo "2️⃣ Prueba: Con charset=utf8\n";
try {
    $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8";
    $pdo = new PDO($dsn, $user, $pass);
    echo "   ✅ Conexión exitosa\n";
    $pdo = null;
} catch (PDOException $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}
echo "\n";

// Prueba 3: Con charset=utf8mb4
echo "3️⃣ Prueba: Con charset=utf8mb4\n";
try {
    $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass);
    echo "   ✅ Conexión exitosa\n";
    $pdo = null;
} catch (PDOException $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}
echo "\n";

// Prueba 4: Usando mysqli
echo "4️⃣ Prueba: Usando MySQLi\n";
try {
    $mysqli = new mysqli($host, $user, $pass, $dbname);
    if ($mysqli->connect_error) {
        throw new Exception($mysqli->connect_error);
    }
    echo "   ✅ Conexión exitosa\n";
    echo "   Versión MySQL: " . $mysqli->server_info . "\n";
    echo "   Charset actual: " . $mysqli->character_set_name() . "\n";
    $mysqli->close();
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}
echo "\n";

// Prueba 5: Consulta de charsets disponibles
echo "5️⃣ Charsets disponibles en MySQL:\n";
try {
    $dsn = "mysql:host={$host};dbname={$dbname}";
    $pdo = new PDO($dsn, $user, $pass);
    
    $stmt = $pdo->query("SHOW CHARACTER SET LIKE 'utf8%'");
    $charsets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($charsets as $charset) {
        echo "   • " . $charset['Charset'] . " - " . $charset['Description'] . "\n";
    }
    
    $pdo = null;
} catch (PDOException $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}
echo "\n";

// Prueba 6: Variables de MySQL relacionadas con charset
echo "6️⃣ Variables de MySQL:\n";
try {
    $dsn = "mysql:host={$host};dbname={$dbname}";
    $pdo = new PDO($dsn, $user, $pass);
    
    $stmt = $pdo->query("SHOW VARIABLES LIKE '%character%'");
    $variables = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($variables as $var) {
        echo "   • " . $var['Variable_name'] . " = " . $var['Value'] . "\n";
    }
    
    $pdo = null;
} catch (PDOException $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}
echo "\n";

echo "═══════════════════════════════════════════════════\n";
echo "RECOMENDACIÓN:\n";
echo "Usa la configuración que mostró ✅ en las pruebas.\n";
echo "═══════════════════════════════════════════════════\n";