<?php
header('Content-Type: text/plain; charset=utf-8');
echo "=== DIAGNÓSTICO BASE DE DATOS SMART ATTENDANCE ===\n\n";

try {
    // Configuración de base de datos
    $host = 'localhost';
    $dbname = 'wwgetj_smart_attendance';
    $username = 'wwgetj_superuser_jornix';
    $password = 'pE}$(RGw#T4xaMZF';
    
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✅ Conexión a BD exitosa\n\n";
    
    // Verificar usuarios
    echo "=== USUARIOS EN SISTEMA ===\n";
    $stmt = $pdo->query("SELECT id, email, name, role, status, LENGTH(password_hash) as hash_length FROM users");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($users as $user) {
        echo "ID: {$user['id']} | Email: {$user['email']} | Rol: {$user['role']} | Estado: {$user['status']} | Hash Length: {$user['hash_length']}\n";
    }
    
    echo "\n=== VERIFICACIÓN SUPER ADMIN ===\n";
    $email = 'disenoaxxioma@gmail.com';
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $superadmin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($superadmin) {
        echo "✅ Usuario encontrado: {$superadmin['email']}\n";
        echo "Hash almacenado: {$superadmin['password_hash']}\n";
        
        // Verificar contraseña
        $test_password = 'pass123';
        if (password_verify($test_password, $superadmin['password_hash'])) {
            echo "✅ Contraseña 'pass123' VERIFICADA correctamente\n";
        } else {
            echo "❌ Contraseña 'pass123' NO coincide con el hash\n";
            
            // Mostrar qué hash esperamos
            $expected_hash = password_hash($test_password, PASSWORD_DEFAULT);
            echo "Hash esperado para 'pass123': $expected_hash\n";
        }
    } else {
        echo "❌ Usuario super admin NO encontrado\n";
    }
    
    echo "\n=== TENANTS ACCESIBLES ===\n";
    $stmt = $pdo->prepare("
        SELECT t.id, t.name, t.domain 
        FROM tenants t 
        INNER JOIN user_tenants ut ON t.id = ut.tenant_id 
        INNER JOIN users u ON ut.user_id = u.id 
        WHERE u.email = ?
    ");
    $stmt->execute([$email]);
    $tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($tenants as $tenant) {
        echo "Tenant ID: {$tenant['id']} | Nombre: {$tenant['name']} | Dominio: {$tenant['domain']}\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Error de base de datos: " . $e->getMessage() . "\n";
    echo "Asegúrate de que:\n";
    echo "1. La base de datos 'wwgetj_smart_attendance' existe\n";
    echo "2. El usuario 'wwgetj_superuser_jornix' tiene permisos\n";
    echo "3. Las credenciales en este script son correctas\n";
}

echo "\n=== FIN DIAGNÓSTICO ===\n";
