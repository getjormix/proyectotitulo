<?php
$hash = '$2y$10$Xa6Xj7J2cVQ9qQ8w6Y5ZzOeBdCfEgDhIeJfGhIjKkLmNoPqRsTuVw';
$password = '123456';

echo "Hash: " . $hash . "\n";
echo "Password a verificar: " . $password . "\n";

if (password_verify($password, $hash)) {
    echo "✅ RESULTADO: Password CORRECTO\n";
} else {
    echo "❌ RESULTADO: Password INCORRECTO\n";
    
    // Probar con hash antiguo
    $old_hash = '$2y$10$gxhRLIMpV9fJoibMgdoWdO.rtCZ6/ffiHduWZ3pX9nuM6MW4lI7Ca';
    if (password_verify($password, $old_hash)) {
        echo "⚠️  Pero funciona con el hash antiguo\n";
    }
}
