<?php
class TenantMiddleware {
    public static function handle() {
        if (!isset($_SESSION['tenant_id'])) {
            header('Location: /tenant_select.php');
            exit;
        }
    }
}
