<?php
$title = "Administración - Smart Attendance";
$content = '
<div class="mb-8">
    <h1 class="text-3xl font-bold text-gray-800">Administración</h1>
    <p class="text-gray-600">Gestión de usuarios y configuración del sistema</p>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold mb-4">Gestión de Usuarios</h3>
        <p class="text-gray-600 mb-4">Administra los usuarios del sistema</p>
        <a href="#" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded inline-block">
            Gestionar Usuarios
        </a>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold mb-4">Roles y Permisos</h3>
        <p class="text-gray-600 mb-4">Configura roles y permisos de acceso</p>
        <a href="#" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded inline-block">
            Gestionar Roles
        </a>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold mb-4">Configuración</h3>
        <p class="text-gray-600 mb-4">Ajustes generales del sistema</p>
        <a href="#" class="bg-purple-500 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded inline-block">
            Configuración
        </a>
    </div>
</div>

<div class="bg-white rounded-lg shadow p-6">
    <h2 class="text-xl font-semibold mb-4">Resumen del Sistema</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="border-l-4 border-blue-500 pl-4">
            <h3 class="font-semibold">Base de Datos</h3>
            <p class="text-gray-600">31 tablas configuradas</p>
        </div>
        <div class="border-l-4 border-green-500 pl-4">
            <h3 class="font-semibold">API Python</h3>
            <p class="text-gray-600">Servicio de analítica</p>
        </div>
        <div class="border-l-4 border-yellow-500 pl-4">
            <h3 class="font-semibold">Multitenant</h3>
            <p class="text-gray-600">3 empresas configuradas</p>
        </div>
        <div class="border-l-4 border-red-500 pl-4">
            <h3 class="font-semibold">Seguridad</h3>
            <p class="text-gray-600">Autenticación activa</p>
        </div>
    </div>
</div>
';
$showHeader = true;
require_once '../layout.php';
