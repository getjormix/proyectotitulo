<?php
class EmployeeController {
    public function index() {
        AuthMiddleware::handle();
        TenantMiddleware::handle();
        
        $db = DB::getInstance()->getConnection();
        $tenant_id = $_SESSION['tenant_id'];
        
        $stmt = $db->prepare("SELECT * FROM employees WHERE tenant_id = ? ORDER BY nombre");
        $stmt->execute([$tenant_id]);
        $employees = $stmt->fetchAll(PDO::FETCH_OBJ);
        
        require_once __DIR__ . '/../../views/employees/list.php';
    }
}
