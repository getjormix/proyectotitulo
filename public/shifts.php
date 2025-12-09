<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Controllers/ShiftController.php';

$controller = new ShiftController();
$controller->index();
