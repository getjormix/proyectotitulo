<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Controllers/EmployeeController.php';

$controller = new EmployeeController();
$controller->index();
