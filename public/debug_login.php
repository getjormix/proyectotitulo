<?php
require_once dirname(__DIR__) . '/config/autoload.php';

$email = 'disenoaxxioma@gmail.com';
$password = '123456';

echo "=== DEBUG LOGIN ===\n";
echo "Email: $email\n";
echo "Password: $password\n\n";

// Conectar directamente a DB
$db = Database::getInstance();

// Buscar usuario
$sql = "SELECT u.*, t.name as tenant_name 
        FROM users u
        INNER JOIN tenants t ON u.tenant_id = t.id
        WHERE u.email = :email
        AND u.status = 'active'
        AND t.status = 'active'
        LIMIT 1";

$user = $db->fetch($sql, ['email' => $email]);

if (!$user) {
    echo "❌ Usuario NO encontrado en DB\n";
    exit;
}

echo "✅ Usuario encontrado:\n";
echo "ID: " . $user['id'] . "\n";
echo "Email: " . $user['email'] . "\n";
echo "Tenant ID: " . $user['tenant_id'] . "\n";
echo "Status: " . $user['status'] . "\n";
echo "Password hash (first 30): " . substr($user['password_hash'], 0, 30) . "...\n\n";

// Verificar password
echo "Verificando password...\n";
if (password_verify($password, $user['password_hash'])) {
    echo "✅ password_verify() RETORNA TRUE\n";
    
    // Probar hash específico
    $correct_hash = '$2y$10$temvpTqfzweCOru1lJ8cH.MfAb9hzdP5lg1YdJUgC3Zg26hZB1ROG';
    echo "\nHash actual en DB: " . $user['password_hash'] . "\n";
    echo "Hash esperado:     " . $correct_hash . "\n";
    
    if ($user['password_hash'] === $correct_hash) {
        echo "✅ Hash en DB COINCIDE EXACTAMENTE\n";
    } else {
        echo "❌ Hash en DB NO COINCIDE\n";
        echo "Longitud actual: " . strlen($user['password_hash']) . "\n";
        echo "Longitud esperada: " . strlen($correct_hash) . "\n";
    }
} else {
    echo "❌ password_verify() RETORNA FALSE\n";
}
