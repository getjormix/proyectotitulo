<?php
/**
 * Gestión de Empleados - CRUD Multi-tenant
 * RUTA: ~/app-php/public/employees.php
 */

require_once dirname(__DIR__) . '/config/autoload.php';

$auth = new Auth();
$db = Database::getInstance();

if (!$auth->isAuthenticated()) {
    header('Location: login.php');
    exit;
}

$tenantId = $_SESSION['tenant_id'];
$isEmployee = isset($_SESSION['employee_mode']) && $_SESSION['employee_mode'];

// Verificar permisos
if ($isEmployee) {
    header('Location: dashboard.php');
    exit;
}

// Obtener tenant
$tenant = $db->fetch("SELECT * FROM tenants WHERE id = :id", ['id' => $tenantId]);

// Parámetros
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Filtros
$search = $_GET['search'] ?? '';
$department = $_GET['department'] ?? '';
$status = $_GET['status'] ?? 'active';

// Construir WHERE
$where = "e.tenant_id = :tenant_id";
$params = ['tenant_id' => $tenantId];

if (!empty($search)) {
    $where .= " AND (e.employee_code LIKE :search OR e.first_name LIKE :search OR e.last_name LIKE :search OR e.rut LIKE :search)";
    $params['search'] = "%$search%";
}

if (!empty($department) && $department !== 'all') {
    $where .= " AND e.department = :department";
    $params['department'] = $department;
}

if ($status === 'active') {
    $where .= " AND e.status = 'active'";
} elseif ($status === 'inactive') {
    $where .= " AND e.status = 'inactive'";
}

// Obtener empleados
$employees = [];
$totalEmployees = 0;

try {
    // Total para paginación
    $countResult = $db->fetch(
        "SELECT COUNT(*) as total FROM employees e WHERE $where",
        $params
    );
    $totalEmployees = $countResult['total'];
    
    // Datos con paginación
    $employees = $db->fetchAll(
        "SELECT 
            e.*,
            (SELECT COUNT(*) FROM attendance_logs al 
             WHERE al.employee_id = e.id 
             AND DATE(al.check_in) = CURDATE()) as present_today,
            (SELECT COUNT(*) FROM attendance_logs al 
             WHERE al.employee_id = e.id 
             AND DATE(al.check_in) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) as days_present_30d,
            (SELECT COUNT(*) FROM risk_scores rs 
             WHERE rs.employee_id = e.id 
             AND rs.fecha = CURDATE()) as has_risk_score
         FROM employees e
         WHERE $where
         ORDER BY e.created_at DESC
         LIMIT :limit OFFSET :offset",
        array_merge($params, ['limit' => $limit, 'offset' => $offset])
    );
} catch (Exception $e) {
    error_log("Employees Error: " . $e->getMessage());
    $error = "Error al cargar empleados: " . $e->getMessage();
}

// Departamentos para filtro
$departments = [];
try {
    $departments = $db->fetchAll(
        "SELECT DISTINCT department 
         FROM employees 
         WHERE tenant_id = :tenant_id 
         AND department IS NOT NULL 
         AND department != ''
         ORDER BY department",
        ['tenant_id' => $tenantId]
    );
} catch (Exception $e) {
    error_log("Departments Error: " . $e->getMessage());
}

// Estadísticas
$stats = [
    'total' => 0,
    'active' => 0,
    'inactive' => 0,
    'present_today' => 0
];

try {
    $statsResult = $db->fetch(
        "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive
         FROM employees 
         WHERE tenant_id = :tenant_id",
        ['tenant_id' => $tenantId]
    );
    $stats['total'] = $statsResult['total'];
    $stats['active'] = $statsResult['active'];
    $stats['inactive'] = $statsResult['inactive'];
    
    $presentResult = $db->fetch(
        "SELECT COUNT(DISTINCT employee_id) as present 
         FROM attendance_logs 
         WHERE tenant_id = :tenant_id AND DATE(check_in) = CURDATE()",
        ['tenant_id' => $tenantId]
    );
    $stats['present_today'] = $presentResult['present'];
} catch (Exception $e) {
    error_log("Stats Error: " . $e->getMessage());
}

// Funciones helper
function getStatusBadge($status) {
    if ($status === 'active') return '<span class="badge badge-success">Activo</span>';
    return '<span class="badge badge-danger">Inactivo</span>';
}

function getAttendanceBadge($present) {
    if ($present > 0) return '<span class="badge badge-success">Presente</span>';
    return '<span class="badge badge-warning">Ausente</span>';
}

function getRiskLevel($daysPresent) {
    $attendanceRate = ($daysPresent / 20) * 100;
    if ($attendanceRate >= 90) return '<span class="text-green-600 font-bold">Bajo</span>';
    if ($attendanceRate >= 70) return '<span class="text-yellow-600 font-bold">Medio</span>';
    return '<span class="text-red-600 font-bold">Alto</span>';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($tenant['name']); ?> - Empleados</title>
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
        
        /* NAVBAR */
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
        
        .tenant-selector {
            padding: 8px 16px;
            border: 1px solid var(--color-border);
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            background: white;
            cursor: pointer;
            font-family: inherit;
        }
        
        .live-indicator {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            background: #f0fdf4;
            border: 1px solid #dcfce7;
            border-radius: 100px;
            font-size: 12px;
            font-weight: 600;
            color: #16a34a;
        }
        
        .pulse-dot {
            width: 6px;
            height: 6px;
            background: #16a34a;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
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
        
        .btn-logout:hover {
            background: #2a2a2a;
        }
        
        /* SIDEBAR */
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
        
        .sidebar-link i {
            width: 20px;
            text-align: center;
            font-size: 16px;
        }
        
        /* MAIN CONTENT */
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
        
        /* STATS CARDS */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }
        
        .stat-card {
            background: white;
            border: 1px solid var(--color-border);
            border-radius: 12px;
            padding: 20px;
            transition: all 0.2s;
        }
        
        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }
        
        .stat-label {
            font-size: 12px;
            font-weight: 600;
            color: var(--color-text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .stat-icon {
            font-size: 24px;
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: 800;
            letter-spacing: -0.02em;
            margin-bottom: 4px;
        }
        
        /* FILTERS */
        .filters-card {
            background: white;
            border: 1px solid var(--color-border);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
        }
        
        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            align-items: end;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        
        .filter-label {
            font-size: 12px;
            font-weight: 600;
            color: var(--color-text-secondary);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .filter-input, .filter-select {
            padding: 10px 14px;
            border: 1px solid var(--color-border);
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            background: white;
        }
        
        .filter-input:focus, .filter-select:focus {
            outline: none;
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(0, 81, 255, 0.1);
        }
        
        .btn-primary {
            padding: 10px 24px;
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
            padding: 10px 24px;
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
        
        /* TABLE */
        .table-container {
            background: white;
            border: 1px solid var(--color-border);
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 24px;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table thead {
            background: var(--color-bg-secondary);
        }
        
        .table th {
            padding: 14px 16px;
            text-align: left;
            font-size: 11px;
            font-weight: 700;
            color: var(--color-text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 1px solid var(--color-border);
        }
        
        .table td {
            padding: 16px;
            border-bottom: 1px solid var(--color-border);
            font-size: 13px;
            vertical-align: middle;
        }
        
        .table tbody tr:hover {
            background: var(--color-bg-secondary);
        }
        
        .table tbody tr:last-child td {
            border-bottom: none;
        }
        
        /* BADGES */
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .badge-success {
            background: #f0fdf4;
            color: #16a34a;
            border: 1px solid #dcfce7;
        }
        
        .badge-warning {
            background: #fef3c7;
            color: #d97706;
            border: 1px solid #fde68a;
        }
        
        .badge-danger {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fee2e2;
        }
        
        .badge-primary {
            background: #eff6ff;
            color: #2563eb;
            border: 1px solid #dbeafe;
        }
        
        .badge-info {
            background: #f0f9ff;
            color: #0369a1;
            border: 1px solid #bae6fd;
        }
        
        /* ACTION BUTTONS */
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        
        .btn-action {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .btn-action-view {
            background: #eff6ff;
            color: #2563eb;
            border: 1px solid #dbeafe;
        }
        
        .btn-action-view:hover {
            background: #dbeafe;
        }
        
        .btn-action-edit {
            background: #f0fdf4;
            color: #16a34a;
            border: 1px solid #dcfce7;
        }
        
        .btn-action-edit:hover {
            background: #dcfce7;
        }
        
        .btn-action-delete {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fee2e2;
        }
        
        .btn-action-delete:hover {
            background: #fee2e2;
        }
        
        /* PAGINATION */
        .pagination {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 24px;
            background: white;
            border: 1px solid var(--color-border);
            border-radius: 12px;
        }
        
        .pagination-info {
            font-size: 13px;
            color: var(--color-text-secondary);
        }
        
        .pagination-controls {
            display: flex;
            gap: 8px;
        }
        
        .pagination-btn {
            padding: 8px 16px;
            background: white;
            border: 1px solid var(--color-border);
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            color: var(--color-text);
            text-decoration: none;
            transition: all 0.2s;
        }
        
        .pagination-btn:hover {
            background: var(--color-bg-secondary);
            border-color: var(--color-primary);
        }
        
        .pagination-btn.active {
            background: var(--color-primary);
            color: white;
            border-color: var(--color-primary);
        }
        
        .pagination-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        /* EMPTY STATE */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--color-text-secondary);
        }
        
        .empty-state-icon {
            font-size: 56px;
            margin-bottom: 16px;
            opacity: 0.5;
        }
        
        .empty-state-title {
            font-weight: 700;
            font-size: 18px;
            margin-bottom: 8px;
        }
        
        .empty-state-description {
            font-size: 14px;
            margin-bottom: 24px;
        }
        
        /* RESPONSIVE */
        @media (max-width: 1024px) {
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
            
            .table {
                display: block;
                overflow-x: auto;
            }
        }
        
        /* ERROR */
        .error-alert {
            padding: 16px;
            background: #fef2f2;
            border: 1px solid #fee2e2;
            border-radius: 8px;
            color: #dc2626;
            margin-bottom: 24px;
            font-size: 14px;
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
            <div class="sidebar-section-title">Reportes</div>
            <a href="reports.php" class="sidebar-link">
                <i class="fas fa-file-alt"></i>
                <span>Informes</span>
            </a>
            <a href="export.php" class="sidebar-link">
                <i class="fas fa-download"></i>
                <span>Exportar</span>
            </a>
            <a href="analytics.php" class="sidebar-link">
                <i class="fas fa-chart-bar"></i>
                <span>Estadísticas</span>
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
            <a href="prediction.php" class="sidebar-link">
                <i class="fas fa-robot"></i>
                <span>IA Predictiva</span>
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
                
                <div class="live-indicator">
                    <span class="pulse-dot"></span>
                    En vivo
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
                    <h1 class="page-title">Gestión de Empleados</h1>
                    <p class="page-subtitle">
                        Administra el personal de <?php echo htmlspecialchars($tenant['name']); ?>
                    </p>
                </div>
                <a href="employee_form.php" class="btn-primary">
                    <i class="fas fa-user-plus"></i>
                    Nuevo Empleado
                </a>
            </div>
        </div>
        
        <!-- STATS -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-label">Total Empleados</div>
                    <div class="stat-icon">👥</div>
                </div>
                <div class="stat-value"><?php echo $stats['total']; ?></div>
                <div style="font-size: 12px; color: var(--color-text-secondary);">
                    <?php echo $stats['active']; ?> activos • <?php echo $stats['inactive']; ?> inactivos
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-label">Presentes Hoy</div>
                    <div class="stat-icon">✅</div>
                </div>
                <div class="stat-value"><?php echo $stats['present_today']; ?></div>
                <div style="font-size: 12px; color: var(--color-text-secondary);">
                    <?php echo $stats['total'] > 0 ? round(($stats['present_today'] / $stats['total']) * 100, 1) : 0; ?>% asistencia
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-label">Departamentos</div>
                    <div class="stat-icon">🏢</div>
                </div>
                <div class="stat-value"><?php echo count($departments); ?></div>
                <div style="font-size: 12px; color: var(--color-text-secondary);">
                    Organización por áreas
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-label">Acciones</div>
                    <div class="stat-icon">⚡</div>
                </div>
                <div class="stat-value">Rápidas</div>
                <div style="font-size: 12px; color: var(--color-text-secondary);">
                    <a href="employee_form.php" style="color: var(--color-primary); text-decoration: none;">Nuevo</a> • 
                    <a href="export.php?type=employees" style="color: var(--color-primary); text-decoration: none;">Exportar</a>
                </div>
            </div>
        </div>
        
        <!-- FILTERS -->
        <div class="filters-card">
            <form method="GET" action="">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label class="filter-label">Buscar</label>
                        <input type="text" name="search" class="filter-input" placeholder="Código, nombre, RUT..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Departamento</label>
                        <select name="department" class="filter-select">
                            <option value="all">Todos los departamentos</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo htmlspecialchars($dept['department']); ?>" 
                                    <?php echo $department === $dept['department'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept['department']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Estado</label>
                        <select name="status" class="filter-select">
                            <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Activos</option>
                            <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactivos</option>
                            <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>Todos</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">&nbsp;</label>
                        <div style="display: flex; gap: 12px;">
                            <button type="submit" class="btn-primary">
                                <i class="fas fa-search"></i>
                                Filtrar
                            </button>
                            <a href="employees.php" class="btn-secondary">
                                <i class="fas fa-undo"></i>
                                Limpiar
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- ERROR MESSAGE -->
        <?php if (isset($error)): ?>
            <div class="error-alert">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <!-- TABLE -->
        <div class="table-container">
            <?php if (empty($employees)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">👥</div>
                    <div class="empty-state-title">No hay empleados</div>
                    <div class="empty-state-description">
                        <?php if (!empty($search) || !empty($department) || $status !== 'active'): ?>
                            No se encontraron empleados con los filtros aplicados.
                        <?php else: ?>
                            Aún no hay empleados registrados en este tenant.
                        <?php endif; ?>
                    </div>
                    <a href="employee_form.php" class="btn-primary">
                        <i class="fas fa-user-plus"></i>
                        Registrar primer empleado
                    </a>
                </div>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Nombre</th>
                            <th>RUT</th>
                            <th>Departamento</th>
                            <th>Estado</th>
                            <th>Hoy</th>
                            <th>Riesgo</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($employees as $emp): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($emp['employee_code']); ?></strong>
                                </td>
                                <td>
                                    <div style="font-weight: 600;"><?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?></div>
                                    <div style="font-size: 11px; color: var(--color-text-secondary);">
                                        <?php echo htmlspecialchars($emp['position'] ?? 'Sin cargo'); ?>
                                    </div>
                                </td>
                                <td>
                                    <code style="font-family: monospace; background: var(--color-bg-secondary); padding: 2px 6px; border-radius: 4px;">
                                        <?php echo htmlspecialchars($emp['rut']); ?>
                                    </code>
                                </td>
                                <td>
                                    <?php if (!empty($emp['department'])): ?>
                                        <span class="badge badge-info"><?php echo htmlspecialchars($emp['department']); ?></span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo getStatusBadge($emp['status']); ?>
                                </td>
                                <td>
                                    <?php echo getAttendanceBadge($emp['present_today']); ?>
                                </td>
                                <td>
                                    <?php echo getRiskLevel($emp['days_present_30d']); ?>
                                    <div style="font-size: 11px; color: var(--color-text-secondary); margin-top: 2px;">
                                        <?php echo $emp['days_present_30d']; ?>/20 días
                                    </div>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="employee_history.php?id=<?php echo $emp['id']; ?>" class="btn-action btn-action-view" title="Ver historial">
                                            <i class="fas fa-history"></i>
                                        </a>
                                        <a href="employee_form.php?id=<?php echo $emp['id']; ?>" class="btn-action btn-action-edit" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="employee_delete.php?id=<?php echo $emp['id']; ?>" 
                                           class="btn-action btn-action-delete" 
                                           title="Eliminar"
                                           onclick="return confirm('¿Estás seguro de eliminar a <?php echo htmlspecialchars(addslashes($emp['first_name'] . ' ' . $emp['last_name'])); ?>?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <!-- PAGINATION -->
        <?php if ($totalEmployees > 0): ?>
            <div class="pagination">
                <div class="pagination-info">
                    Mostrando <?php echo min($limit, count($employees)); ?> de <?php echo $totalEmployees; ?> empleados
                </div>
                
                <div class="pagination-controls">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&department=<?php echo urlencode($department); ?>&status=<?php echo $status; ?>" 
                           class="pagination-btn">
                            <i class="fas fa-chevron-left"></i> Anterior
                        </a>
                    <?php else: ?>
                        <span class="pagination-btn disabled">
                            <i class="fas fa-chevron-left"></i> Anterior
                        </span>
                    <?php endif; ?>
                    
                    <?php
                    $totalPages = ceil($totalEmployees / $limit);
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    
                    for ($i = $startPage; $i <= $endPage; $i++):
                    ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&department=<?php echo urlencode($department); ?>&status=<?php echo $status; ?>" 
                           class="pagination-btn <?php echo $i == $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&department=<?php echo urlencode($department); ?>&status=<?php echo $status; ?>" 
                           class="pagination-btn">
                            Siguiente <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php else: ?>
                        <span class="pagination-btn disabled">
                            Siguiente <i class="fas fa-chevron-right"></i>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- FOOTER -->
        <footer style="margin-top: 48px; text-align: center; padding: 24px; color: var(--color-text-secondary); font-size: 12px;">
            <p>© 2024 Jornix by Getso Digital • Sistema Enterprise de Inteligencia de Asistencia</p>
            <p style="margin-top: 4px;">Multi-tenant • IA Predictiva • Análisis en Tiempo Real</p>
        </footer>
    </main>
</body>
</html>
