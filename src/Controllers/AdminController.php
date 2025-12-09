<?php
class AdminController {
    public function index() {
        AuthMiddleware::handle();
        
        if (!Auth::checkRole(['admin'])) {
            header('Location: /dashboard.php');
            exit;
        }
        
        TenantMiddleware::handle();
        
        require_once __DIR__ . '/../../views/admin/users.php';
    }
}
