<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Controllers/AdminController.php';

$controller = new AdminController();
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="dashboard.php" class="nav-link">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="employees.php" class="nav-link">
                        <i class="fas fa-users"></i>
                        <span>Empleados</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="shifts.php" class="nav-link">
                        <i class="fas fa-clock"></i>
                        <span>Turnos</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="prediction.php" class="nav-link">
                        <i class="fas fa-brain"></i>
                        <span>Predicción IA</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="alerts.php" class="nav-link">
                        <i class="fas fa-bell"></i>
                        <span>Alertas</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="reports.php" class="nav-link">
                        <i class="fas fa-chart-bar"></i>
                        <span>Reportes</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="settings.php" class="nav-link active">
                        <i class="fas fa-cog"></i>
                        <span>Configuración</span>
                    </a>
                </li>
            </ul>
