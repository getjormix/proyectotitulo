<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Controllers/AdminController.php';

$controller = new AdminController();
$controller->index();
