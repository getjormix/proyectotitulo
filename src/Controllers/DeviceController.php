<?php
class DeviceController {
    public function index() {
        AuthMiddleware::handle();
        TenantMiddleware::handle();
        
        $db = DB::getInstance()->getConnection();
        $tenant_id = $_SESSION['tenant_id'];
        
        $stmt = $db->prepare("SELECT * FROM devices WHERE tenant_id = ? ORDER BY descripcion");
        $stmt->execute([$tenant_id]);
        $devices = $stmt->fetchAll(PDO::FETCH_OBJ);
        
        require_once __DIR__ . '/../../views/devices/list.php';
    }
}
