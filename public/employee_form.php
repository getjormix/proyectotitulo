<?php
require_once dirname(__DIR__) . '/config/autoload.php';

$auth = new Auth();
$db = Database::getInstance();

if (!$auth->isAuthenticated()) {
    header('Location: login.php');
    exit;
}

$tenantId = $_SESSION['tenant_id'];
$tenant = $db->fetch("SELECT * FROM tenants WHERE id = :id", ['id' => $tenantId]);

$employeeId = $_GET['id'] ?? null;
$employee = null;
$isEdit = false;

if ($employeeId) {
    $employee = $db->fetch(
        "SELECT * FROM employees WHERE id = :id AND tenant_id = :tenant_id",
        ['id' => $employeeId, 'tenant_id' => $tenantId]
    );
    $isEdit = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'tenant_id' => $tenantId,
        'employee_code' => $_POST['employee_code'],
        'rut' => $_POST['rut'],
        'first_name' => $_POST['first_name'],
        'last_name' => $_POST['last_name'],
        'email' => $_POST['email'],
        'phone' => $_POST['phone'],
        'department' => $_POST['department'],
        'position' => $_POST['position'],
        'hire_date' => $_POST['hire_date'],
        'status' => $_POST['status']
    ];
    
    try {
        if ($isEdit && $employee) {
            $db->update('employees', $data, ['id' => $employeeId, 'tenant_id' => $tenantId]);
            $message = "Empleado actualizado correctamente";
        } else {
            $db->insert('employees', $data);
            $message = "Empleado creado correctamente";
        }
        
        header('Location: employees.php?success=' . urlencode($message));
        exit;
        
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

$departments = $db->fetchAll(
    "SELECT DISTINCT department FROM employees WHERE tenant_id = :tenant_id AND department IS NOT NULL ORDER BY department",
    ['tenant_id' => $tenantId]
);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $isEdit ? 'Editar' : 'Nuevo'; ?> Empleado - <?php echo htmlspecialchars($tenant['name']); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --color-primary: #0051ff;
            --color-secondary: #0a0a0a;
            --color-bg: #ffffff;
            --color-bg-secondary: #fafafa;
            --color-border: #e5e7eb;
            --color-text: #0a0a0a;
            --color-text-secondary: #6b7280;
            --sidebar-width: 260px;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--color-bg-secondary);
            color: var(--color-text);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }
        
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 64px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: saturate(180%) blur(20px);
            border-bottom: 1px solid var(--color-border);
            z-index: 1000;
            padding-left: var(--sidebar-width);
        }
        
        .nav-container {
            height: 100%;
            padding: 0 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .nav-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .nav-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .user-menu {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 16px;
            background: var(--color-bg-secondary);
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
        }
        
        .btn-logout {
            padding: 8px 16px;
            background: var(--color-text);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: white;
            border-right: 1px solid var(--color-border);
            padding: 20px 0;
            overflow-y: auto;
            z-index: 1001;
        }
        
        .sidebar-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0 20px 20px;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--color-border);
        }
        
        .logo-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--color-primary), #667eea);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 800;
            font-size: 20px;
        }
        
        .logo-text {
            font-size: 20px;
            font-weight: 800;
            letter-spacing: -0.02em;
        }
        
        .sidebar-section {
            margin-bottom: 24px;
        }
        
        .sidebar-section-title {
            padding: 0 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--color-text-secondary);
            margin-bottom: 8px;
        }
        
        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 20px;
            color: var(--color-text-secondary);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
            border-left: 3px solid transparent;
        }
        
        .sidebar-link:hover {
            background: var(--color-bg-secondary);
            color: var(--color-text);
            border-left-color: var(--color-primary);
        }
        
        .sidebar-link.active {
            background: rgba(0, 81, 255, 0.08);
            color: var(--color-primary);
            border-left-color: var(--color-primary);
            font-weight: 600;
        }
        
        .main-content {
            margin-top: 64px;
            margin-left: var(--sidebar-width);
            padding: 32px 24px;
            min-height: calc(100vh - 64px);
        }
        
        .page-header {
            margin-bottom: 32px;
        }
        
        .page-title {
            font-size: 32px;
            font-weight: 800;
            letter-spacing: -0.02em;
            margin-bottom: 8px;
        }
        
        .page-subtitle {
            font-size: 15px;
            color: var(--color-text-secondary);
        }
        
        .form-container {
            background: white;
            border: 1px solid var(--color-border);
            border-radius: 12px;
            padding: 32px;
            max-width: 800px;
            margin: 0 auto;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 24px;
            margin-bottom: 32px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .form-label {
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--color-text);
        }
        
        .form-label.required::after {
            content: " *";
            color: #dc2626;
        }
        
        .form-input, .form-select, .form-textarea {
            padding: 10px 14px;
            border: 1px solid var(--color-border);
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            background: white;
            transition: all 0.2s;
        }
        
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(0, 81, 255, 0.1);
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 16px;
            padding-top: 24px;
            border-top: 1px solid var(--color-border);
        }
        
        .btn-primary {
            padding: 12px 24px;
            background: var(--color-primary);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary:hover {
            background: #0041cc;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            padding: 12px 24px;
            background: white;
            color: var(--color-text);
            border: 1px solid var(--color-border);
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-secondary:hover {
            background: var(--color-bg-secondary);
            border-color: var(--color-primary);
        }
        
        .alert {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            font-size: 14px;
        }
        
        .alert-success {
            background: #f0fdf4;
            border: 1px solid #dcfce7;
            color: #16a34a;
        }
        
        .alert-error {
            background: #fef2f2;
            border: 1px solid #fee2e2;
            color: #dc2626;
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            :root {
                --sidebar-width: 0px;
            }
            
            .sidebar {
                transform: translateX(-100%);
            }
            
            .navbar {
                padding-left: 0;
            }
            
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <!-- SIDEBAR -->
    <aside class="sidebar">
        <div class="sidebar-logo">
            <div class="logo-icon">J</div>
            <div class="logo-text">Jornix</div>
        </div>
        
        <div class="sidebar-section">
            <div class="sidebar-section-title">Principal</div>
            <a href="dashboard.php" class="sidebar-link">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="prediction.php" class="sidebar-link">
                <i class="fas fa-robot"></i>
                <span>IA Predictiva</span>
            </a>
            <a href="realtime.php" class="sidebar-link">
                <i class="fas fa-clock"></i>
                <span>Tiempo Real</span>
            </a>
        </div>
        
        <div class="sidebar-section">
            <div class="sidebar-section-title">Gestión</div>
            <a href="employees.php" class="sidebar-link active">
                <i class="fas fa-users"></i>
                <span>Empleados</span>
            </a>
            <a href="shifts.php" class="sidebar-link">
                <i class="fas fa-calendar-alt"></i>
                <span>Turnos</span>
            </a>
            <a href="departments.php" class="sidebar-link">
                <i class="fas fa-building"></i>
                <span>Departamentos</span>
            </a>
            <a href="locations.php" class="sidebar-link">
                <i class="fas fa-map-marker-alt"></i>
                <span>Ubicaciones</span>
            </a>
        </div>
        
        <div class="sidebar-section">
            <div class="sidebar-section-title">Sistema</div>
            <a href="alerts.php" class="sidebar-link">
                <i class="fas fa-bell"></i>
                <span>Alertas</span>
            </a>
            <a href="settings.php" class="sidebar-link">
                <i class="fas fa-cog"></i>
                <span>Configuración</span>
            </a>
        </div>
    </aside>
    
    <!-- NAVBAR -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-left">
                <div style="font-size: 16px; font-weight: 700;">
                    <?php echo htmlspecialchars($tenant['name']); ?>
                </div>
            </div>
            
            <div class="nav-right">
                <div class="user-menu">
                    👨‍💼 <?php echo htmlspecialchars($_SESSION['name']); ?>
                </div>
                
                <form method="POST" action="logout.php" style="display:inline;">
                    <button type="submit" class="btn-logout">Salir</button>
                </form>
            </div>
        </div>
    </nav>
    
    <!-- MAIN CONTENT -->
    <main class="main-content">
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <h1 class="page-title"><?php echo $isEdit ? 'Editar Empleado' : 'Nuevo Empleado'; ?></h1>
                    <p class="page-subtitle">
                        <?php echo $isEdit ? 'Actualiza la información del empleado' : 'Registra un nuevo empleado en el sistema'; ?>
                    </p>
                </div>
                <a href="employees.php" class="btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Volver a lista
                </a>
            </div>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_GET['success']); ?>
            </div>
        <?php endif; ?>
        
        <div class="form-container">
            <form method="POST" action="">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label required">Código Empleado</label>
                        <input type="text" name="employee_code" class="form-input" 
                               value="<?php echo htmlspecialchars($employee['employee_code'] ?? ''); ?>" 
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label required">RUT</label>
                        <input type="text" name="rut" class="form-input" 
                               value="<?php echo htmlspecialchars($employee['rut'] ?? ''); ?>" 
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label required">Nombres</label>
                        <input type="text" name="first_name" class="form-input" 
                               value="<?php echo htmlspecialchars($employee['first_name'] ?? ''); ?>" 
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label required">Apellidos</label>
                        <input type="text" name="last_name" class="form-input" 
                               value="<?php echo htmlspecialchars($employee['last_name'] ?? ''); ?>" 
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-input" 
                               value="<?php echo htmlspecialchars($employee['email'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Teléfono</label>
                        <input type="text" name="phone" class="form-input" 
                               value="<?php echo htmlspecialchars($employee['phone'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Departamento</label>
                        <select name="department" class="form-select">
                            <option value="">Seleccionar departamento</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo htmlspecialchars($dept['department']); ?>"
                                    <?php echo isset($employee['department']) && $employee['department'] === $dept['department'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept['department']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Cargo</label>
                        <input type="text" name="position" class="form-input" 
                               value="<?php echo htmlspecialchars($employee['position'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Fecha de Ingreso</label>
                        <input type="date" name="hire_date" class="form-input" 
                               value="<?php echo htmlspecialchars($employee['hire_date'] ?? date('Y-m-d')); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label required">Estado</label>
                        <select name="status" class="form-select" required>
                            <option value="active" <?php echo isset($employee['status']) && $employee['status'] === 'active' ? 'selected' : ''; ?>>Activo</option>
                            <option value="inactive" <?php echo isset($employee['status']) && $employee['status'] === 'inactive' ? 'selected' : ''; ?>>Inactivo</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-actions">
                    <a href="employees.php" class="btn-secondary">
                        Cancelar
                    </a>
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save"></i>
                        <?php echo $isEdit ? 'Actualizar Empleado' : 'Crear Empleado'; ?>
                    </button>
                </div>
            </form>
        </div>
        
        <footer style="margin-top: 48px; text-align: center; padding: 24px; color: var(--color-text-secondary); font-size: 12px;">
            <p>© 2024 Jornix by Getso Digital • Sistema Enterprise de Inteligencia de Asistencia</p>
            <p style="margin-top: 4px;">Multi-tenant • IA Predictiva • Análisis en Tiempo Real</p>
        </footer>
    </main>
</body>
</html>
