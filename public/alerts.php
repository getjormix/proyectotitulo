<?php
session_start();
require_once __DIR__ . '/../src/Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../src/Controllers/AlertController.php';

$auth = new AuthMiddleware();
$auth->handle();

$tenant_id = $_SESSION['tenant_id'];
$user_role = $_SESSION['user_role'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alertas - FactoryPlus</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f5f7fa; margin: 0; font-family: 'Segoe UI', sans-serif; }
        .container { display: flex; min-height: 100vh; }
        .sidebar { width: 250px; background: #2c3e50; color: white; padding: 20px 0; }
        .nav-menu { list-style: none; padding: 0; }
        .nav-item { margin: 5px 0; }
        .nav-link { display: flex; align-items: center; gap: 12px; padding: 15px 20px; 
                   color: rgba(255,255,255,0.8); text-decoration: none; border-left: 3px solid transparent; }
        .nav-link:hover { background: rgba(255,255,255,0.1); color: white; }
        .nav-link.active { background: rgba(52,152,219,0.2); color: white; border-left-color: #3498db; }
        .main-content { flex: 1; margin-left: 250px; padding: 20px; }
        .header { background: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <aside class="sidebar">
            <div style="padding: 20px; border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom: 20px;">
                <h2 style="color: white;"><i class="fas fa-industry"></i> FactoryPlus</h2>
            </div>
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="dashboard.php" class="nav-link">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="employees.php" class="nav-link">
                        <i class="fas fa-users"></i>
                        <span>Empleados</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="shifts.php" class="nav-link">
                        <i class="fas fa-clock"></i>
                        <span>Turnos</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="prediction.php" class="nav-link">
                        <i class="fas fa-brain"></i>
                        <span>Predicción IA</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="alerts.php" class="nav-link active">
                        <i class="fas fa-bell"></i>
                        <span>Alertas</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="reports.php" class="nav-link">
                        <i class="fas fa-chart-bar"></i>
                        <span>Reportes</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="settings.php" class="nav-link">
                        <i class="fas fa-cog"></i>
                        <span>Configuración</span>
                    </a>
                </li>
            </ul>
        </aside>
        <main class="main-content">
            <div class="header">
                <h1><i class="fas fa-bell"></i> Sistema de Alertas</h1>
                <p>Tenant ID: <?php echo $tenant_id; ?> | Rol: <?php echo ucfirst($user_role); ?></p>
            </div>
            <p>Aquí aparecerán las alertas del sistema.</p>
        </main>
    </div>
</body>
</html>
