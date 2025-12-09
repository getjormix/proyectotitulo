<?php
/**
 * Index - Redirección
 * RUTA: ~/app-php/public/index.php
 */

require_once dirname(__DIR__) . '/config/autoload.php';

$auth = new Auth();

if ($auth->isAuthenticated()) {
    header('Location: dashboard.php');
} else {
    header('Location: login.php');
}
exit;