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

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? 'active';

$where = "s.tenant_id = :tenant_id";
$params = ['tenant_id' => $tenantId];

if (!empty($search)) {
    $where .= " AND (s.name LIKE :search OR s.description LIKE :search)";
    $params['search'] = "%$search%";
}

if ($status === 'active') {
    $where .= " AND s.status = 'active'";
} elseif ($status === 'inactive') {
    $where .= " AND s.status = 'inactive'";
}

$shifts = [];
$totalShifts = 0;

try {
    $countResult = $db->fetch(
        "SELECT COUNT(*) as total FROM shifts s WHERE $where",
        $params
    );
    $totalShifts = $countResult['total'];
    
    $shifts = $db->fetchAll(
        "SELECT s.*, 
                (SELECT COUNT(*) FROM employee_shifts es WHERE es.shift_id = s.id) as employee_count
         FROM shifts s
         WHERE $where
         ORDER BY s.start_time ASC
         LIMIT :limit OFFSET :offset",
        array_merge($params, ['limit' => $limit, 'offset' => $offset])
    );
} catch (Exception $e) {
    error_log("Shifts Error: " . $e->getMessage());
    $error = "Error al cargar turnos: " . $e->getMessage();
}

$stats = [
    'total' => 0,
    'active' => 0,
    'inactive' => 0
];

try {
    $statsResult = $db->fetch(
        "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive
         FROM shifts 
         WHERE tenant_id = :tenant_id",
        ['tenant_id' => $tenantId]
    );
    $stats = $statsResult;
} catch (Exception $e) {
    error_log("Shifts Stats Error: " . $e->getMessage());
}

function getStatusBadge($status) {
    if ($status === 'active') return '<span class="badge badge-success">Activo</span>';
    return '<span class="badge badge-danger">Inactivo</span>';
}

function formatTime($time) {
    return date('H:i', strtotime($time));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Turnos - <?php echo htmlspecialchars($tenant['name']); ?></title>
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
        
        .badge-warning {
            background: #fef3c7;
            color: #d97706;
            border: 1px solid #fde68a;
        }
        
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
        
        .btn-action-edit {
            background: #f0fdf4;
            color: #16a34a;
            border: 1px solid #dcfce7;
        }
        
        .btn-action-delete {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fee2e2;
        }
        
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
        }
    </style>
</head>
<body>
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
        </div>
        
        <div class="sidebar-section">
            <div class="sidebar-section-title">Gestión</div>
            <a href="employees.php" class="sidebar-link">
                <i class="fas fa-users"></i>
                <span>Empleados</span>
            </a>
            <a href="shifts.php" class="sidebar-link active">
                <i class="fas fa-calendar-alt"></i>
                <span>Turnos</span>
            </a>
            <a href="departments.php" class="sidebar-link">
                <i class="fas fa-building"></i>
                <span>Departamentos</span>
            </a>
        </div>
    </aside>
    
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
    
    <main class="main-content">
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <h1 class="page-title">Gestión de Turnos</h1>
                    <p class="page-subtitle">
                        Administra los turnos laborales de <?php echo htmlspecialchars($tenant['name']); ?>
                    </p>
                </div>
                <a href="shift_form.php" class="btn-primary">
                    <i class="fas fa-calendar-plus"></i>
                    Nuevo Turno
                </a>
            </div>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-label">Total Turnos</div>
                    <div class="stat-icon">📅</div>
                </div>
                <div class="stat-value"><?php echo $stats['total']; ?></div>
                <div style="font-size: 12px; color: var(--color-text-secondary);">
                    <?php echo $stats['active']; ?> activos • <?php echo $stats['inactive']; ?> inactivos
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-label">Horarios</div>
                    <div class="stat-icon">⏰</div>
                </div>
                <div class="stat-value">Configurados</div>
                <div style="font-size: 12px; color: var(--color-text-secondary);">
                    Mañana, Tarde, Noche
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-label">Asignaciones</div>
                    <div class="stat-icon">👥</div>
                </div>
                <div class="stat-value">Activas</div>
                <div style="font-size: 12px; color: var(--color-text-secondary);">
                    Empleados por turno
                </div>
            </div>
        </div>
        
        <div class="filters-card">
            <form method="GET" action="">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label class="filter-label">Buscar</label>
                        <input type="text" name="search" class="filter-input" placeholder="Nombre turno..." 
                               value="<?php echo htmlspecialchars($search); ?>">
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
                            <a href="shifts.php" class="btn-secondary">
                                <i class="fas fa-undo"></i>
                                Limpiar
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        
        <?php if (isset($error)): ?>
            <div style="padding: 16px; background: #fef2f2; border: 1px solid #fee2e2; border-radius: 8px; color: #dc2626; margin-bottom: 24px;">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <div class="table-container">
            <?php if (empty($shifts)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📅</div>
                    <div class="empty-state-title">No hay turnos configurados</div>
                    <div style="margin-top: 16px;">
                        <a href="shift_form.php" class="btn-primary">
                            <i class="fas fa-calendar-plus"></i>
                            Crear primer turno
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Horario</th>
                            <th>Duración</th>
                            <th>Empleados</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($shifts as $shift): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($shift['name']); ?></strong>
                                    <?php if (!empty($shift['description'])): ?>
                                        <div style="font-size: 11px; color: var(--color-text-secondary); margin-top: 2px;">
                                            <?php echo htmlspecialchars($shift['description']); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="font-weight: 600;"><?php echo formatTime($shift['start_time']); ?> - <?php echo formatTime($shift['end_time']); ?></div>
                                    <div style="font-size: 11px; color: var(--color-text-secondary);">
                                        <?php echo $shift['break_minutes'] ? $shift['break_minutes'] . ' min descanso' : 'Sin descanso'; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php
                                    $start = new DateTime($shift['start_time']);
                                    $end = new DateTime($shift['end_time']);
                                    $diff = $start->diff($end);
                                    echo $diff->h . 'h ' . $diff->i . 'm';
                                    ?>
                                </td>
                                <td>
                                    <span class="badge badge-primary">
                                        <?php echo $shift['employee_count']; ?> empleados
                                    </span>
                                </td>
                                <td>
                                    <?php echo getStatusBadge($shift['status']); ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="shift_form.php?id=<?php echo $shift['id']; ?>" class="btn-action btn-action-edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="shift_delete.php?id=<?php echo $shift['id']; ?>" 
                                           class="btn-action btn-action-delete"
                                           onclick="return confirm('¿Eliminar turno <?php echo htmlspecialchars(addslashes($shift['name'])); ?>?')">
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
        
        <footer style="margin-top: 48px; text-align: center; padding: 24px; color: var(--color-text-secondary); font-size: 12px;">
            <p>© 2024 Jornix by Getso Digital • Sistema Enterprise de Inteligencia de Asistencia</p>
        </footer>
    </main>
</body>
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
                    <a href="shifts.php" class="nav-link active">
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
                    <a href="alerts.php" class="nav-link">
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
