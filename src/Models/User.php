<?php
require_once __DIR__ . '/../Services/DB.php';

class User {
    private $db;

    public $id;
    public $tenant_id;
    public $email;
    public $password_hash; // CORREGIDO: era pass_hash
    public $role;
    public $twofa_secret;
    public $active;
    public $created_at;

    public function __construct() {
        $this->db = DB::getInstance()->getConnection();
    }

    public static function findByEmail($email) {
        $db = DB::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetchObject('User');
    }

    public static function findById($id) {
        $db = DB::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetchObject('User');
    }

    public function getTenants() {
        // CORREGIDO: Usar la tabla user_tenants para usuarios multi-tenant
        $stmt = $this->db->prepare("
            SELECT t.* FROM tenants t 
            INNER JOIN user_tenants ut ON t.id = ut.tenant_id 
            WHERE ut.user_id = ?
        ");
        $stmt->execute([$this->id]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function verifyPassword($password) {
        return password_verify($password, $this->password_hash); // CORREGIDO
    }
}