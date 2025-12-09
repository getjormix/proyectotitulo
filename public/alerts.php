<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Controllers/AlertController.php';

$controller = new AlertController();
$controller->index();
