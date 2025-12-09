<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configuración simple de BD para pruebas
$host = 'localhost';
$dbname = 'wwgetj_smart_attendance';
$username = 'wwgetj_superuser_jornix';
$password = 'pE}$(RGw#T4xaMZF';

if ($_POST['login']) {
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $email = $_POST['email'];
        $password_input = $_POST['password'];
        
        $stmt = $pdo->prepare("
            SELECT u.*, t.name as tenant_name 
            FROM users u 
            INNER JOIN tenants t ON u.tenant_id = t.id 
            WHERE u.email = ? AND u.status = 'active'
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            echo "<div style='background: #e0e0e0; padding: 10px; margin: 10px 0;'>";
            echo "<strong>DEBUG INFO:</strong><br>";
            echo "Usuario encontrado: {$user['email']}<br>";
            echo "Hash en BD: {$user['password_hash']}<br>";
            echo "Contraseña ingresada: $password_input<br>";
            
            if (password_verify($password_input, $user['password_hash'])) {
                echo "<strong style='color: green;'>✅ Contraseña VERIFICADA</strong><br>";
                $_SESSION['user'] = $user;
                header('Location: dashboard.php');
                exit;
            } else {
                echo "<strong style='color: red;'>❌ Contraseña INCORRECTA</strong><br>";
                $error = "Contraseña incorrecta";
            }
            echo "</div>";
        } else {
            $error = "Usuario no encontrado o inactivo";
        }
        
    } catch (PDOException $e) {
        $error = "Error de conexión: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Smart Attendance - Login Debug</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="bg-white p-8 rounded-lg shadow-md w-96">
        <h1 class="text-2xl font-bold text-center mb-6 text-blue-600">Smart Attendance - DEBUG</h1>
        
        <?php if (isset($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?= $error ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Email</label>
                <input type="email" name="email" value="disenoaxxioma@gmail.com" required 
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="mb-6">
                <label class="block text-gray-700 text-sm font-bold mb-2">Contraseña</label>
                <input type="password" name="password" value="pass123" required
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <button type="submit" name="login" 
                    class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                Iniciar Sesión (DEBUG)
            </button>
        </form>
        
        <div class="mt-6 p-4 bg-yellow-50 rounded">
            <p class="text-sm text-yellow-700">
                <strong>Credenciales de prueba:</strong><br>
                Email: disenoaxxioma@gmail.com<br>
                Contraseña: pass123
            </p>
        </div>
        
        <div class="mt-4 text-center">
            <a href="debug_db.php" class="text-blue-600 hover:underline text-sm">Ver diagnóstico completo de BD</a>
        </div>
    </div>
</body>
</html>
