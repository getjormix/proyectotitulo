<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Controllers/AuthController.php';

$controller = new AuthController();
$controller->tenantSelect();
