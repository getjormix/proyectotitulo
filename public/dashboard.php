<?php
/**
 * Dashboard Enterprise COMPLETO con Sidebar
 * RUTA: ~/app-php/public/dashboard.php
 * VERSIÓN: 2.0 - Enlaces funcionales
 */

require_once dirname(__DIR__) . '/config/autoload.php';

$auth = new Auth();
$db = Database::getInstance();

if (!$auth->isAuthenticated()) {
    header('Location: login.php');
    exit;
}

$isEmployee = isset($_SESSION['employee_mode']) && $_SESSION['employee_mode'];
$tenantId = $_SESSION['tenant_id'];
$today = date('Y-m-d');
$userId = $_SESSION['user_id'] ?? null;
$employeeId = $_SESSION['employee_id'] ?? null;

// Obtener información del tenant
$tenant = $db->fetch("SELECT * FROM tenants WHERE id = :id", ['id' => $tenantId]);

// Obtener todos los tenants (para selector - solo si es admin)
$allTenants = [];
if (!$isEmployee) {
    try {
        $allTenants = $db->fetchAll("SELECT id, name FROM tenants WHERE status = 'active' ORDER BY name");
    } catch (Exception $e) {
        error_log("Tenants Error: " . $e->getMessage());
    }
}

// KPIs generales
$kpis = [
    'total_employees' => 0,
    'present_today' => 0,
    'absent_today' => 0,
    'late_today' => 0,
    'attendance_rate' => 0,
    'punctuality_rate' => 0,
    'cost_absenteeism' => 0,
    'shift_coverage' => 0,
    'critical_absences' => 0,
    'avg_risk_score' => 0
];

try {
    $result = $db->fetch(
        "SELECT COUNT(*) as total FROM employees WHERE tenant_id = :tenant_id AND status = 'active'",
        ['tenant_id' => $tenantId]
    );
    $kpis['total_employees'] = $result['total'];
    
    $result = $db->fetch(
        "SELECT COUNT(DISTINCT employee_id) as present 
         FROM attendance_logs 
         WHERE tenant_id = :tenant_id AND DATE(check_in) = :today",
        ['tenant_id' => $tenantId, 'today' => $today]
    );
    $kpis['present_today'] = $result['present'];
    
    $kpis['absent_today'] = $kpis['total_employees'] - $kpis['present_today'];
    
    $result = $db->fetch(
        "SELECT COUNT(*) as late 
         FROM attendance_logs 
         WHERE tenant_id = :tenant_id 
         AND DATE(check_in) = :today
         AND TIME(check_in) > '09:15:00'",
        ['tenant_id' => $tenantId, 'today' => $today]
    );
    $kpis['late_today'] = $result['late'];
    
    if ($kpis['total_employees'] > 0) {
        $kpis['attendance_rate'] = round(($kpis['present_today'] / $kpis['total_employees']) * 100, 1);
    }
    
    if ($kpis['present_today'] > 0) {
        $onTime = $kpis['present_today'] - $kpis['late_today'];
        $kpis['punctuality_rate'] = round(($onTime / $kpis['present_today']) * 100, 1);
    }
    
    $kpis['cost_absenteeism'] = $kpis['absent_today'] * 50000;
    $kpis['shift_coverage'] = $kpis['attendance_rate'];
    $kpis['critical_absences'] = max(0, $kpis['absent_today'] - floor($kpis['total_employees'] * 0.05));
    $kpis['avg_risk_score'] = round((100 - $kpis['attendance_rate']) * 2.5, 1);
    
} catch (Exception $e) {
    error_log("KPIs Error: " . $e->getMessage());
}

// Estadísticas por departamento
$deptStats = [];
try {
    $deptStats = $db->fetchAll(
        "SELECT 
            e.department,
            COUNT(DISTINCT e.id) as total_employees,
            COUNT(DISTINCT al.employee_id) as present_today,
            COUNT(CASE WHEN TIME(al.check_in) > '09:15:00' THEN 1 END) as late_count
         FROM employees e
         LEFT JOIN attendance_logs al ON e.id = al.employee_id AND DATE(al.check_in) = :today
         WHERE e.tenant_id = :tenant_id AND e.status = 'active'
         GROUP BY e.department
         ORDER BY total_employees DESC",
        ['tenant_id' => $tenantId, 'today' => $today]
    );
    
    foreach ($deptStats as &$dept) {
        $absentRate = $dept['total_employees'] > 0 
            ? (($dept['total_employees'] - $dept['present_today']) / $dept['total_employees']) * 100 
            : 0;
        $lateRate = $dept['present_today'] > 0 
            ? ($dept['late_count'] / $dept['present_today']) * 100 
            : 0;
        $dept['risk_score'] = round($absentRate + ($lateRate * 0.5));
        $dept['predicted_absences'] = round($dept['total_employees'] * ($absentRate / 100));
    }
    
} catch (Exception $e) {
    error_log("Dept Stats Error: " . $e->getMessage());
}

// Empleados de alto riesgo
$highRiskEmployees = [];
try {
    $highRiskEmployees = $db->fetchAll(
        "SELECT 
            e.employee_code,
            e.first_name,
            e.last_name,
            e.department,
            e.position,
            (SELECT COUNT(*) FROM attendance_logs al2 
             WHERE al2.employee_id = e.id 
             AND al2.check_in >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) as days_present,
            (20 - (SELECT COUNT(*) FROM attendance_logs al2 
             WHERE al2.employee_id = e.id 
             AND al2.check_in >= DATE_SUB(CURDATE(), INTERVAL 30 DAY))) as absences_30d
         FROM employees e
         WHERE e.tenant_id = :tenant_id AND e.status = 'active'
         HAVING absences_30d > 2
         ORDER BY absences_30d DESC
         LIMIT 10",
        ['tenant_id' => $tenantId]
    );
    
    foreach ($highRiskEmployees as &$emp) {
        $emp['risk_score'] = min(100, $emp['absences_30d'] * 15);
    }
    
} catch (Exception $e) {
    error_log("High Risk Error: " . $e->getMessage());
}

// Últimos registros
$recentLogs = [];
try {
    $recentLogs = $db->fetchAll(
        "SELECT 
            al.*,
            e.employee_code,
            e.first_name,
            e.last_name,
            e.department,
            TIME(al.check_in) as check_in_time
         FROM attendance_logs al
         INNER JOIN employees e ON al.employee_id = e.id
         WHERE al.tenant_id = :tenant_id
         AND DATE(al.check_in) = :today
         ORDER BY al.check_in DESC
         LIMIT 10",
        ['tenant_id' => $tenantId, 'today' => $today]
    );
} catch (Exception $e) {
    error_log("Recent Logs Error: " . $e->getMessage());
}

// Tendencia 30 días
$trendData = [];
try {
    $trendData = $db->fetchAll(
        "SELECT 
            DATE(check_in) as date,
            COUNT(DISTINCT employee_id) as present_count,
            (SELECT COUNT(*) FROM employees WHERE tenant_id = :tenant_id AND status = 'active') as total_employees
         FROM attendance_logs
         WHERE tenant_id = :tenant_id
         AND check_in >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
         GROUP BY DATE(check_in)
         ORDER BY date ASC",
        ['tenant_id' => $tenantId]
    );
    
    foreach ($trendData as &$day) {
        $day['attendance_rate'] = $day['total_employees'] > 0 
            ? round(($day['present_count'] / $day['total_employees']) * 100, 1) 
            : 0;
    }
} catch (Exception $e) {
    error_log("Trend Error: " . $e->getMessage());
}

// Alertas activas
$activeAlerts = [];
try {
    $activeAlerts = $db->fetchAll(
        "SELECT a.*, e.employee_code, e.first_name, e.last_name
         FROM alerts a
         LEFT JOIN employees e ON a.employee_id = e.id
         WHERE a.tenant_id = :tenant_id 
         AND a.resolved = 0
         AND a.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
         ORDER BY 
             CASE a.severity 
                 WHEN 'high' THEN 1
                 WHEN 'medium' THEN 2
                 WHEN 'low' THEN 3
             END,
             a.created_at DESC
         LIMIT 10",
        ['tenant_id' => $tenantId]
    );
} catch (Exception $e) {
    error_log("Alerts Error: " . $e->getMessage());
}

// Heatmap por turno (simulado)
$heatmapData = [
    'Mañana' => ['Operaciones' => 15, 'Administración' => 5, 'Logística' => 10, 'Mantención' => 12],
    'Tarde' => ['Operaciones' => 45, 'Administración' => 8, 'Logística' => 30, 'Mantención' => 25],
    'Noche' => ['Operaciones' => 75, 'Administración' => 2, 'Logística' => 50, 'Mantención' => 60]
];

// Funciones helper
function getRiskColor($score) {
    if ($score >= 70) return 'bg-red-500';
    if ($score >= 40) return 'bg-yellow-500';
    return 'bg-green-500';
}

function getRiskTextColor($score) {
    if ($score >= 70) return 'text-red-600';
    if ($score >= 40) return 'text-yellow-600';
    return 'text-green-600';
}

function getHeatmapColor($value) {
    if ($value >= 70) return 'bg-red-500';
    if ($value >= 50) return 'bg-orange-500';
    if ($value >= 30) return 'bg-yellow-500';
    if ($value >= 15) return 'bg-blue-400';
    return 'bg-green-500';
}

function formatCurrency($amount) {
    return '$' . number_format($amount, 0, ',', '.');
}

function timeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    
    if ($diff < 60) return 'Hace unos segundos';
    if ($diff < 3600) return 'Hace ' . round($diff/60) . ' minutos';
    if ($diff < 86400) return 'Hace ' . round($diff/3600) . ' horas';
    return 'Hace ' . round($diff/86400) . ' días';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($tenant['name']); ?> - Dashboard Jornix</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        
        /* KPI CARDS */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }
        
        .kpi-card {
            background: white;
            border: 1px solid var(--color-border);
            border-radius: 12px;
            padding: 20px;
            transition: all 0.2s;
            cursor: pointer;
        }
        
        .kpi-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.08);
        }
        
        .kpi-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }
        
        .kpi-label {
            font-size: 12px;
            font-weight: 600;
            color: var(--color-text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .kpi-icon {
            font-size: 24px;
        }
        
        .kpi-value {
            font-size: 32px;
            font-weight: 800;
            letter-spacing: -0.02em;
            margin-bottom: 4px;
        }
        
        .kpi-change {
            font-size: 12px;
            font-weight: 600;
        }
        
        .kpi-change.positive { color: #16a34a; }
        .kpi-change.negative { color: #dc2626; }
        
        /* GRID */
        .grid-2 {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
            margin-bottom: 24px;
        }
        
        .grid-3 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
            margin-bottom: 24px;
        }
        
        .card {
            background: white;
            border: 1px solid var(--color-border);
            border-radius: 12px;
            padding: 24px;
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .card-title {
            font-size: 18px;
            font-weight: 700;
            letter-spacing: -0.01em;
        }
        
        .card-action {
            font-size: 13px;
            font-weight: 600;
            color: var(--color-primary);
            text-decoration: none;
            transition: color 0.2s;
        }
        
        .card-action:hover {
            color: #0041cc;
        }
        
        /* TABLE */
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table thead {
            background: var(--color-bg-secondary);
        }
        
        .table th {
            padding: 10px 12px;
            text-align: left;
            font-size: 11px;
            font-weight: 700;
            color: var(--color-text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .table td {
            padding: 14px 12px;
            border-bottom: 1px solid var(--color-border);
            font-size: 13px;
        }
        
        .table tbody tr:hover {
            background: var(--color-bg-secondary);
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
        
        .badge-pin { background: #e0e7ff; color: #4338ca; }
        .badge-qr { background: #fae8ff; color: #a21caf; }
        .badge-facial { background: #d1fae5; color: #065f46; }
        
        /* RISK SCORE */
        .risk-score {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .risk-bar {
            width: 80px;
            height: 6px;
            background: var(--color-bg-secondary);
            border-radius: 3px;
            overflow: hidden;
        }
        
        .risk-bar-fill {
            height: 100%;
            border-radius: 3px;
            transition: width 0.3s;
        }
        
        /* ALERT */
        .alert {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 12px;
            border-left: 4px solid;
            font-size: 13px;
        }
        
        .alert-danger {
            background: #fef2f2;
            border-color: #dc2626;
            color: #991b1b;
        }
        
        .alert-warning {
            background: #fffbeb;
            border-color: #f59e0b;
            color: #92400e;
        }
        
        .alert-info {
            background: #eff6ff;
            border-color: #3b82f6;
            color: #1e40af;
        }
        
        .alert-title {
            font-weight: 700;
            margin-bottom: 4px;
        }
        
        .alert-time {
            font-size: 11px;
            opacity: 0.7;
            margin-top: 6px;
        }
        
        /* HEATMAP */
        .heatmap-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
        }
        
        .heatmap-cell {
            aspect-ratio: 1;
            border-radius: 8px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            transition: all 0.2s;
            cursor: pointer;
        }
        
        .heatmap-cell:hover {
            transform: scale(1.05);
        }
        
        .heatmap-label {
            font-size: 11px;
            opacity: 0.9;
        }
        
        .heatmap-value {
            font-size: 20px;
        }
        
        /* CHART */
        .chart-container {
            position: relative;
            height: 300px;
        }
        
        /* QUICK ACTIONS */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 12px;
        }
        
        .quick-action-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: white;
            border: 2px solid var(--color-border);
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            color: var(--color-text);
        }
        
        .quick-action-btn:hover {
            border-color: var(--color-primary);
            background: rgba(0, 81, 255, 0.02);
        }
        
        .quick-action-btn i {
            font-size: 28px;
            margin-bottom: 8px;
            color: var(--color-primary);
        }
        
        .quick-action-btn span {
            font-size: 12px;
            font-weight: 600;
            text-align: center;
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
            
            .grid-2, .grid-3 {
                grid-template-columns: 1fr;
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
            <a href="dashboard.php" class="sidebar-link active">
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
            <a href="employees.php" class="sidebar-link">
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
                <?php if (!$isEmployee && count($allTenants) > 1): ?>
                <select class="tenant-selector" onchange="window.location.href='dashboard.php?tenant='+this.value">
                    <?php foreach ($allTenants as $t): ?>
                        <option value="<?php echo $t['id']; ?>" <?php echo $t['id'] == $tenantId ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($t['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php else: ?>
                <div style="font-size: 16px; font-weight: 700;">
                    <?php echo htmlspecialchars($tenant['name']); ?>
                </div>
                <?php endif; ?>
                
                <div class="live-indicator">
                    <span class="pulse-dot"></span>
                    En vivo
                </div>
            </div>
            
            <div class="nav-right">
                <div class="user-menu">
                    <?php if ($isEmployee): ?>
                        👷 <?php echo htmlspecialchars($_SESSION['employee_name']); ?>
                    <?php else: ?>
                        👨‍💼 <?php echo htmlspecialchars($_SESSION['name']); ?>
                    <?php endif; ?>
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
            <h1 class="page-title">Dashboard de Control</h1>
            <p class="page-subtitle">
                <?php 
                $days = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
                $months = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
                echo $days[date('w')] . ', ' . date('d') . ' de ' . $months[date('n')-1] . ' ' . date('Y'); 
                ?> • Actualizado hace unos segundos
            </p>
        </div>
        
        <!-- KPI CARDS -->
        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-header">
                    <div class="kpi-label">Asistencia Hoy</div>
                    <div class="kpi-icon">📊</div>
                </div>
                <div class="kpi-value" style="color: <?php echo $kpis['attendance_rate'] >= 90 ? '#16a34a' : ($kpis['attendance_rate'] >= 75 ? '#f59e0b' : '#dc2626'); ?>">
                    <?php echo $kpis['attendance_rate']; ?>%
                </div>
                <div class="kpi-change positive">Meta: 90%</div>
            </div>
            
            <div class="kpi-card">
                <div class="kpi-header">
                    <div class="kpi-label">Ausencias Críticas</div>
                    <div class="kpi-icon">⚠️</div>
                </div>
                <div class="kpi-value" style="color: #dc2626;">
                    <?php echo $kpis['critical_absences']; ?>
                </div>
                <div class="kpi-change negative">Requieren atención</div>
            </div>
            
            <div class="kpi-card">
                <div class="kpi-header">
                    <div class="kpi-label">Puntualidad 7d</div>
                    <div class="kpi-icon">⏰</div>
                </div>
                <div class="kpi-value" style="color: #f59e0b;">
                    <?php echo $kpis['punctuality_rate']; ?>%
                </div>
                <div class="kpi-change">Últimos 7 días</div>
            </div>
            
            <div class="kpi-card">
                <div class="kpi-header">
                    <div class="kpi-label">Score Riesgo Prom.</div>
                    <div class="kpi-icon">🎯</div>
                </div>
                <div class="kpi-value" style="color: #667eea;">
                    <?php echo $kpis['avg_risk_score']; ?>
                </div>
                <div class="kpi-change">Predicción IA</div>
            </div>
            
            <div class="kpi-card">
                <div class="kpi-header">
                    <div class="kpi-label">Costo Ausentismo</div>
                    <div class="kpi-icon">💰</div>
                </div>
                <div class="kpi-value" style="font-size: 24px; color: #dc2626;">
                    <?php echo formatCurrency($kpis['cost_absenteeism']); ?>
                </div>
                <div class="kpi-change negative">Estimado hoy</div>
            </div>
            
            <div class="kpi-card">
                <div class="kpi-header">
                    <div class="kpi-label">Cobertura Turnos</div>
                    <div class="kpi-icon">🔄</div>
                </div>
                <div class="kpi-value" style="color: #16a34a;">
                    <?php echo $kpis['shift_coverage']; ?>%
                </div>
                <div class="kpi-change positive">Operativo</div>
            </div>
        </div>
        
        <!-- GRID 2 COLUMNS -->
        <div class="grid-2">
            <!-- TREND CHART -->
            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title">Tendencia de Asistencia (30 días)</div>
                        <p style="font-size: 12px; color: var(--color-text-secondary); margin-top: 4px;">
                            Histórico real y proyección IA
                        </p>
                    </div>
                    <a href="reports.php" class="card-action">Ver reportes <i class="fas fa-arrow-right ml-1"></i></a>
                </div>
                <div class="chart-container">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>
            
            <!-- ALERTS -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title">🚨 Alertas Activas</div>
                    <a href="alerts.php" class="card-action">Ver todas →</a>
                </div>
                
                <?php if (empty($activeAlerts)): ?>
                    <div style="text-align: center; padding: 60px 20px; color: var(--color-text-secondary);">
                        <div style="font-size: 56px; margin-bottom: 16px;">✅</div>
                        <p style="font-weight: 700; font-size: 16px; margin-bottom: 4px;">Sin alertas activas</p>
                        <p style="font-size: 13px;">Todo funcionando correctamente</p>
                    </div>
                <?php else: ?>
                    <div style="max-height: 400px; overflow-y: auto;">
                        <?php foreach ($activeAlerts as $alert): ?>
                            <div class="alert alert-<?php echo $alert['severity'] === 'high' ? 'danger' : ($alert['severity'] === 'medium' ? 'warning' : 'info'); ?>">
                                <div class="alert-title">
                                    <?php 
                                    if ($alert['severity'] === 'high') echo '🔴 ';
                                    elseif ($alert['severity'] === 'medium') echo '🟡 ';
                                    else echo '🔵 ';
                                    echo htmlspecialchars($alert['alert_type']); 
                                    ?>
                                </div>
                                <div><?php echo htmlspecialchars($alert['alert_message']); ?></div>
                                <div class="alert-time"><?php echo timeAgo($alert['created_at']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- DEPARTMENT PREDICTIONS -->
        <div class="card" style="margin-bottom: 24px;">
            <div class="card-header">
                <div class="card-title">🤖 Predicción IA por Departamento</div>
                <a href="prediction.php" class="card-action">Análisis completo →</a>
            </div>
            
            <table class="table">
                <thead>
                    <tr>
                        <th>Departamento</th>
                        <th>Empleados</th>
                        <th>Presentes</th>
                        <th>Score Riesgo</th>
                        <th>Ausencias Pred.</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($deptStats)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 40px; color: var(--color-text-secondary);">
                                No hay datos de departamentos
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($deptStats as $dept): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($dept['department'] ?: 'Sin departamento'); ?></strong></td>
                                <td><?php echo $dept['total_employees']; ?></td>
                                <td><?php echo $dept['present_today']; ?></td>
                                <td>
                                    <div class="risk-score">
                                        <div class="risk-bar">
                                            <div class="risk-bar-fill <?php echo getRiskColor($dept['risk_score']); ?>" 
                                                 style="width: <?php echo min(100, $dept['risk_score']); ?>%;">
                                            </div>
                                        </div>
                                        <span class="<?php echo getRiskTextColor($dept['risk_score']); ?>" style="font-weight: 700;">
                                            <?php echo $dept['risk_score']; ?>%
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo $dept['predicted_absences'] > 5 ? 'danger' : ($dept['predicted_absences'] > 2 ? 'warning' : 'success'); ?>">
                                        <?php echo $dept['predicted_absences']; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- HEATMAP + HIGH RISK -->
        <div class="grid-2">
            <!-- HEATMAP -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title">🗺️ Heatmap: Riesgo por Turno</div>
                    <a href="analytics.php" class="card-action">Ver detalle →</a>
                </div>
                
                <div style="margin-bottom: 16px;">
                    <p style="font-size: 12px; color: var(--color-text-secondary);">
                        Intensidad de riesgo por departamento y turno
                    </p>
                </div>
                
                <?php foreach ($heatmapData as $turno => $depts): ?>
                    <div style="margin-bottom: 20px;">
                        <div style="font-size: 13px; font-weight: 700; margin-bottom: 8px; color: var(--color-text-secondary);">
                            <?php echo $turno; ?>
                        </div>
                        <div class="heatmap-grid">
                            <?php foreach ($depts as $dept => $value): ?>
                                <div class="heatmap-cell <?php echo getHeatmapColor($value); ?>" 
                                     title="<?php echo $dept; ?>: <?php echo $value; ?>% riesgo">
                                    <div class="heatmap-label"><?php echo $dept; ?></div>
                                    <div class="heatmap-value"><?php echo $value; ?>%</div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- HIGH RISK EMPLOYEES -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title">⚠️ Empleados Alto Riesgo</div>
                    <a href="employees.php?filter=high_risk" class="card-action">Ver todos →</a>
                </div>
                
                <?php if (empty($highRiskEmployees)): ?>
                    <div style="text-align: center; padding: 60px 20px; color: var(--color-text-secondary);">
                        <div style="font-size: 56px; margin-bottom: 16px;">✅</div>
                        <p style="font-weight: 700; font-size: 14px;">Sin empleados de alto riesgo</p>
                    </div>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Empleado</th>
                                <th>Depto</th>
                                <th>Aus. 30d</th>
                                <th>Score</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($highRiskEmployees, 0, 8) as $emp): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($emp['employee_code']); ?></strong><br>
                                        <small style="color: var(--color-text-secondary);">
                                            <?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?>
                                        </small>
                                    </td>
                                    <td><?php echo htmlspecialchars($emp['department'] ?: 'N/A'); ?></td>
                                    <td>
                                        <span class="badge badge-danger">
                                            <?php echo $emp['absences_30d']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="<?php echo getRiskTextColor($emp['risk_score']); ?>" style="font-weight: 700;">
                                            <?php echo $emp['risk_score']; ?>%
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- RECENT LOGS -->
        <div class="card" style="margin-bottom: 24px;">
            <div class="card-header">
                <div class="card-title">⏱️ Registros Recientes de Hoy</div>
                <a href="attendance_logs.php" class="card-action">Ver historial completo →</a>
            </div>
            
            <table class="table">
                <thead>
                    <tr>
                        <th>Hora</th>
                        <th>Empleado</th>
                        <th>Departamento</th>
                        <th>Método</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentLogs)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 40px; color: var(--color-text-secondary);">
                                Sin registros hoy
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recentLogs as $log): ?>
                            <?php $isLate = strtotime($log['check_in_time']) > strtotime('09:15:00'); ?>
                            <tr>
                                <td><strong><?php echo $log['check_in_time']; ?></strong></td>
                                <td>
                                    <?php echo htmlspecialchars($log['employee_code']); ?><br>
                                    <small style="color: var(--color-text-secondary);">
                                        <?php echo htmlspecialchars($log['first_name'] . ' ' . $log['last_name']); ?>
                                    </small>
                                </td>
                                <td><?php echo htmlspecialchars($log['department'] ?: 'N/A'); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $log['check_type']; ?>">
                                        <?php echo strtoupper($log['check_type']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo $isLate ? 'warning' : 'success'; ?>">
                                        <?php echo $isLate ? '⏰ Tarde' : '✓ A Tiempo'; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- QUICK ACTIONS -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">⚡ Acciones Rápidas</div>
            </div>
            
            <div class="quick-actions">
                <a href="employee_form.php" class="quick-action-btn">
                    <i class="fas fa-user-plus"></i>
                    <span>Nuevo Empleado</span>
                </a>
                <a href="shift_form.php" class="quick-action-btn">
                    <i class="fas fa-calendar-plus"></i>
                    <span>Crear Turno</span>
                </a>
                <a href="export.php" class="quick-action-btn">
                    <i class="fas fa-file-export"></i>
                    <span>Exportar Datos</span>
                </a>
                <a href="report_absence.php" class="quick-action-btn">
                    <i class="fas fa-exclamation"></i>
                    <span>Reportar Ausencia</span>
                </a>
                <a href="reports.php" class="quick-action-btn">
                    <i class="fas fa-chart-bar"></i>
                    <span>Ver Reportes</span>
                </a>
                <a href="settings.php" class="quick-action-btn">
                    <i class="fas fa-cog"></i>
                    <span>Configurar</span>
                </a>
            </div>
        </div>
        
        <!-- FOOTER -->
        <footer style="margin-top: 48px; text-align: center; padding: 24px; color: var(--color-text-secondary); font-size: 12px;">
            <p>© 2024 Jornix by Getso Digital • Sistema Enterprise de Inteligencia de Asistencia</p>
            <p style="margin-top: 4px;">Multi-tenant • IA Predictiva • Análisis en Tiempo Real</p>
        </footer>
    </main>
    
    <script>
        const trendLabels = <?php echo json_encode(array_column($trendData, 'date')); ?>;
        const trendValues = <?php echo json_encode(array_column($trendData, 'attendance_rate')); ?>;
        
        const ctx = document.getElementById('trendChart').getContext('2d');
        const trendChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: trendLabels,
                datasets: [{
                    label: 'Asistencia (%)',
                    data: trendValues,
                    borderColor: '#0051ff',
                    backgroundColor: 'rgba(0, 81, 255, 0.08)',
                    tension: 0.4,
                    fill: true,
                    borderWidth: 3,
                    pointRadius: 0,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 12,
                        titleFont: { size: 13, weight: '600' },
                        bodyFont: { size: 12 },
                        displayColors: false,
                        callbacks: {
                            label: function(context) {
                                return 'Asistencia: ' + context.parsed.y + '%';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: false,
                        min: 60,
                        max: 100,
                        ticks: {
                            callback: function(value) {
                                return value + '%';
                            },
                            font: { size: 11 }
                        },
                        grid: { color: '#f3f4f6' }
                    },
                    x: {
                        ticks: {
                            maxTicksLimit: 10,
                            font: { size: 11 }
                        },
                        grid: { display: false }
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index'
                }
            }
        });
        
        // Auto-refresh cada 30 segundos
        setTimeout(() => location.reload(), 30000);
        
        console.log('Dashboard Enterprise cargado');
        console.log('Tenant:', '<?php echo $tenant['name']; ?>');
        console.log('KPIs:', <?php echo json_encode($kpis); ?>);
    </script>
</body>
</html>