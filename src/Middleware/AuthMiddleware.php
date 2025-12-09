<?php
class AuthMiddleware {
    public static function handle() {
        if (!Auth::isLoggedIn()) {
            header('Location: /login.php');
            exit;
        }
    }
}
