<?php
header('Content-Type: text/plain; charset=utf-8');
echo "=== DIAGNÓSTICO COMPLETO SMART ATTENDANCE ===\n\n";

// 1. Verificar que estamos en el entorno correcto
echo "1. Entorno del servidor:\n";
echo "   PHP version: " . phpversion() . "\n";
echo "   Servidor: " . $_SERVER['SERVER_SOFTWARE'] . "\n";
echo "   Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
echo "   Script actual: " . $_SERVER['SCRIPT_FILENAME'] . "\n\n";

// 2. Verificar conexión a la base de datos
echo "2. Verificación de base de datos:\n";
$host = 'localhost';
$dbname = 'wwgetj_smart_attendance';
$username = 'wwgetj_superuser_jornix';
$password = 'pE}$(RGw#T4xaMZF';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "   ✅ Conexión a BD exitosa\n";
} catch (PDOException $e) {
    echo "   ❌ Error de conexión: " . $e->getMessage() . "\n";
    exit;
}

// 3. Verificar que el usuario existe y tiene la contraseña correcta
echo "3. Verificación de usuario 'disenoaxxioma@gmail.com':\n";
$email = 'disenoaxxioma@gmail.com';
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    echo "   ✅ Usuario encontrado:\n";
    echo "      ID: " . $user['id'] . "\n";
    echo "      Nombre: " . $user['name'] . "\n";
    echo "      Rol: " . $user['role'] . "\n";
    echo "      Estado: " . $user['status'] . "\n";
    
    // Verificar contraseña
    $password_input = 'pass123';
    if (password_verify($password_input, $user['password_hash'])) {
        echo "   ✅ Contraseña 'pass123' es correcta\n";
    } else {
        echo "   ❌ Contraseña 'pass123' no coincide\n";
        echo "      Hash en BD: " . $user['password_hash'] . "\n";
        echo "      Hash esperado para 'pass123': " . password_hash($password_input, PASSWORD_DEFAULT) . "\n";
    }
} else {
    echo "   ❌ Usuario no encontrado\n";
}

// 4. Verificar asignación a tenants
echo "4. Verificación de asignación a tenants:\n";
$stmt = $pdo->prepare("
    SELECT t.id, t.name, t.domain 
    FROM tenants t 
    INNER JOIN user_tenants ut ON t.id = ut.tenant_id 
    WHERE ut.user_id = ?
");
$stmt->execute([$user['id']]);
$tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($tenants) {
    echo "   ✅ Usuario asignado a los siguientes tenants:\n";
    foreach ($tenants as $tenant) {
        echo "      Tenant ID: " . $tenant['id'] . " | Nombre: " . $tenant['name'] . " | Dominio: " . $tenant['domain'] . "\n";
    }
} else {
    echo "   ❌ Usuario no asignado a ningún tenant\n";
}

// 5. Verificar que la sesión puede iniciarse
echo "5. Verificación de sesión:\n";
session_start();
$_SESSION['user'] = $user;
if (isset($_SESSION['user'])) {
    echo "   ✅ Sesión iniciada correctamente\n";
} else {
    echo "   ❌ Error al iniciar sesión\n";
}

echo "\n=== FIN DEL DIAGNÓSTICO ===\n";
