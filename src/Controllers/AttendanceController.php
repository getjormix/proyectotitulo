<?php
class AttendanceController {
    public function index() {
        AuthMiddleware::handle();
        TenantMiddleware::handle();
        
        require_once __DIR__ . '/../../views/attendance/register.php';
    }
    
    public function register() {
        AuthMiddleware::handle();
        TenantMiddleware::handle();
        
        if ($_POST) {
            // Lógica para registrar asistencia
            $employee_id = $_POST['employee_id'] ?? null;
            $method = $_POST['method'] ?? 'QR';
            
            if ($employee_id) {
                $this->saveAttendance($employee_id, $method);
            }
        }
        
        header('Location: /attendance.php');
        exit;
    }
    
    private function saveAttendance($employee_id, $method) {
        $db = DB::getInstance()->getConnection();
        $tenant_id = $_SESSION['tenant_id'];
        
        $stmt = $db->prepare("
            INSERT INTO attendance_logs (tenant_id, employee_id, ts_registro, metodo) 
            VALUES (?, ?, NOW(), ?)
        ");
        
        return $stmt->execute([$tenant_id, $employee_id, $method]);
    }
}
