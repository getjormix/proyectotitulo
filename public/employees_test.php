<?php
session_start();
echo "<h1>Test Employees</h1>";
echo "SESSION: <pre>" . print_r($_SESSION, true) . "</pre>";
echo "GET: <pre>" . print_r($_GET, true) . "</pre>";
