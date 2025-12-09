<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Controllers/DeviceController.php';

$controller = new DeviceController();
$controller->index();
