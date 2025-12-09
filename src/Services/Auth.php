<?php
/**
 * Servicio de Autenticaci¨®n
 * RUTA: ~/app-php/src/Services/Auth.php
 */

class Auth {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function login($email, $password) {
        try {
            $sql = "SELECT u.*, t.name as tenant_name, t.domain as tenant_domain
                    FROM users u
                    INNER JOIN tenants t ON u.tenant_id = t.id
                    WHERE u.email = :email 
                    AND u.status = 'active'
                    AND t.status = 'active'
                    LIMIT 1";
            
            $user = $this->db->fetch($sql, ['email' => $email]);
            
            if (!$user) {
                $this->logFailedAttempt($email);
                return ['success' => false, 'message' => 'Credenciales inv¨¢lidas'];
            }
            
            if (!password_verify($password, $user['password_hash'])) {
                $this->logFailedAttempt($email, $user['id']);
                return ['success' => false, 'message' => 'Credenciales inv¨¢lidas'];
            }
            
            $this->db->update('users', 
                ['last_login' => date('Y-m-d H:i:s')],
                'id = :id',
                ['id' => $user['id']]
            );
            
            $this->createSession($user);
            
            return ['success' => true, 'message' => 'Login exitoso', 'user' => $user];
            
        } catch (Exception $e) {
            error_log("Login Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error en el sistema. Intente nuevamente.'];
        }
    }
    
    public function loginWithPin($employeeCode, $pin) {
        try {
            $sql = "SELECT e.*, epc.pin_hash, epc.is_locked, epc.attempts_count,
                    t.name as tenant_name, t.id as tenant_id
                    FROM employees e
                    INNER JOIN employee_pin_codes epc ON e.id = epc.employee_id
                    INNER JOIN tenants t ON e.tenant_id = t.id
                    WHERE e.employee_code = :code 
                    AND e.status = 'active'
                    AND epc.is_locked = 0
                    LIMIT 1";
            
            $employee = $this->db->fetch($sql, ['code' => $employeeCode]);
            
            if (!$employee) {
                return ['success' => false, 'message' => 'C¨®digo de empleado no v¨¢lido'];
            }
            
            if ($employee['is_locked']) {
                return ['success' => false, 'message' => 'PIN bloqueado. Contacte al administrador.'];
            }
            
            if (!password_verify($pin, $employee['pin_hash'])) {
                $this->incrementPinAttempts($employee['id']);
                return ['success' => false, 'message' => 'PIN incorrecto'];
            }
            
            $this->resetPinAttempts($employee['id']);
            
            return ['success' => true, 'message' => 'Autenticaci¨®n exitosa', 'employee' => $employee];
            
        } catch (Exception $e) {
            error_log("PIN Login Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error en el sistema'];
        }
    }
    
    private function createSession($user) {
        if (session_status() === PHP_SESSION_NONE) {
            session_name(SESSION_NAME);
            session_start();
        }
        
        session_regenerate_id(true);
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['tenant_id'] = $user['tenant_id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['tenant_name'] = $user['tenant_name'];
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
    }
    
    public function isAuthenticated() {
        if (session_status() === PHP_SESSION_NONE) {
            session_name(SESSION_NAME);
            session_start();
        }
        
        if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
            return false;
        }
        
        if (isset($_SESSION['last_activity']) && 
            (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
            $this->logout();
            return false;
        }
        
        $_SESSION['last_activity'] = time();
        return true;
    }
    
    public function getCurrentUser() {
        if (!$this->isAuthenticated()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['user_id'] ?? null,
            'tenant_id' => $_SESSION['tenant_id'] ?? null,
            'email' => $_SESSION['email'] ?? null,
            'name' => $_SESSION['name'] ?? null,
            'role' => $_SESSION['role'] ?? null,
            'tenant_name' => $_SESSION['tenant_name'] ?? null
        ];
    }
    
    public function logout() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $_SESSION = [];
        
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
        
        session_destroy();
    }
    
    private function incrementPinAttempts($employeeId) {
        $sql = "UPDATE employee_pin_codes 
                SET attempts_count = attempts_count + 1,
                    last_attempt = NOW(),
                    is_locked = CASE WHEN attempts_count >= 4 THEN 1 ELSE 0 END
                WHERE employee_id = :id";
        
        $this->db->query($sql, ['id' => $employeeId]);
    }
    
    private function resetPinAttempts($employeeId) {
        $sql = "UPDATE employee_pin_codes 
                SET attempts_count = 0,
                    last_attempt = NULL
                WHERE employee_id = :id";
        
        $this->db->query($sql, ['id' => $employeeId]);
    }
    
    private function logFailedAttempt($email, $userId = null) {
        error_log("Failed login attempt for: " . $email);
    }
}