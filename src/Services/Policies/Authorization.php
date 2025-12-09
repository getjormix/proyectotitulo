<?php
class Authorization {
    public static function canAccessModule($userRole, $module) {
        $permissions = [
            'admin' => ['dashboard', 'employees', 'shifts', 'alerts', 'devices', 'admin'],
            'supervisor' => ['dashboard', 'employees', 'shifts', 'alerts'],
            'operador' => ['dashboard', 'attendance']
        ];
        
        return isset($permissions[$userRole]) && in_array($module, $permissions[$userRole]);
    }
    
    public static function canManageUsers($userRole) {
        return $userRole === 'admin';
    }
    
    public static function canManageSettings($userRole) {
        return $userRole === 'admin';
    }
}
