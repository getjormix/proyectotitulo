<?php
class DashboardController {
    public function index() {
        if (!Auth::isLoggedIn()) {
            header('Location: /login.php');
            exit;
        }

        $user = Auth::getCurrentUser();
        $tenant_id = $_SESSION['tenant_id'];
        
        // Aquí irá la lógica para obtener KPIs del dashboard
        $stats = $this->getDashboardStats($tenant_id);
        
        require_once __DIR__ . '/../../views/dashboard.php';
    }

    private function getDashboardStats($tenant_id) {
        $db = DB::getInstance()->getConnection();
        
        // Estadísticas básicas - se conectarán con Python API después
        $stats = [
            'total_employees' => 0,
            'today_attendance' => 0,
            'pending_alerts' => 0,
            'risk_score' => 0
        ];

        // Total empleados
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM employees WHERE tenant_id = ? AND activo = 1");
        $stmt->execute([$tenant_id]);
        $result = $stmt->fetch();
        $stats['total_employees'] = $result['total'];

        // Asistencia de hoy
        $today = date('Y-m-d');
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM attendance_logs WHERE tenant_id = ? AND DATE(ts_registro) = ?");
        $stmt->execute([$tenant_id, $today]);
        $result = $stmt->fetch();
        $stats['today_attendance'] = $result['total'];

        // Alertas pendientes
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM alerts WHERE tenant_id = ? AND estado = 'pendiente'");
        $stmt->execute([$tenant_id]);
        $result = $stmt->fetch();
        $stats['pending_alerts'] = $result['total'];

        return $stats;
    }
}
