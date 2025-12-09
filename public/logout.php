<?php
/**
 * Logout - Cerrar Sesión
 * RUTA: ~/app-php/public/logout.php
 */

require_once dirname(__DIR__) . '/config/autoload.php';

$auth = new Auth();
$auth->logout();

header('Location: login.php');
exit;