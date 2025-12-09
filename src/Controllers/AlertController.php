<?php
class AlertController {
    public function index() {
        AuthMiddleware::handle();
        TenantMiddleware::handle();
        
        $db = DB::getInstance()->getConnection();
        $tenant_id = $_SESSION['tenant_id'];
        
        $stmt = $db->prepare("
            SELECT a.*, e.nombre as employee_name 
            FROM alerts a 
            JOIN employees e ON a.employee_id = e.id 
            WHERE a.tenant_id = ? 
            ORDER BY a.fecha DESC, a.created_at DESC
        ");
        $stmt->execute([$tenant_id]);
        $alerts = $stmt->fetchAll(PDO::FETCH_OBJ);
        
        require_once __DIR__ . '/../../views/alerts/list.php';
    }
}
