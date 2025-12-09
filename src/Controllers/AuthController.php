<?php
require_once __DIR__ . '/../Services/Auth.php';

class AuthController {
    public function login() {
        if ($_POST) {
            $email = $_POST['email'] ?? '';
            $password = $_POST['password'] ?? '';
            
            $user = Auth::login($email, $password);
            
            if ($user) {
                $tenants = $user->getTenants();
                
                // Si no tiene tenants asignados, usar el tenant_id por defecto
                if (empty($tenants)) {
                    $_SESSION['tenant_id'] = $user->tenant_id;
                    header('Location: /dashboard.php');
                    exit;
                }
                
                if (count($tenants) > 1) {
                    header('Location: /tenant_select.php');
                } else {
                    $_SESSION['tenant_id'] = $tenants[0]->id;
                    header('Location: /dashboard.php');
                }
                exit;
            } else {
                $error = "Credenciales incorrectas";
            }
        }
        
        require_once __DIR__ . '/../../views/login_form.php';
    }

    public function logout() {
        Auth::logout();
        header('Location: /login.php');
        exit;
    }

    public function tenantSelect() {
        if (!Auth::isLoggedIn()) {
            header('Location: /login.php');
            exit;
        }

        $user = Auth::getCurrentUser();
        $tenants = $user->getTenants();

        if ($_POST) {
            $tenant_id = (int)$_POST['tenant_id'];
            $_SESSION['tenant_id'] = $tenant_id;
            header('Location: /dashboard.php');
            exit;
        }

        require_once __DIR__ . '/../../views/tenant_select.php';
    }
}