<?php
class ShiftController {
    public function index() {
        AuthMiddleware::handle();
        TenantMiddleware::handle();
        
        $db = DB::getInstance()->getConnection();
        $tenant_id = $_SESSION['tenant_id'];
        
        $stmt = $db->prepare("SELECT * FROM shifts WHERE tenant_id = ? ORDER BY nombre");
        $stmt->execute([$tenant_id]);
        $shifts = $stmt->fetchAll(PDO::FETCH_OBJ);
        
        require_once __DIR__ . '/../../views/shifts/list.php';
    }
}
