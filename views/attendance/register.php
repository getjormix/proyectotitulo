<?php
$title = "Registro de Asistencia - Smart Attendance";
$content = '
<div class="mb-8">
    <h1 class="text-3xl font-bold text-gray-800">Registro de Asistencia</h1>
    <p class="text-gray-600">Registra la asistencia de empleados</p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-xl font-semibold mb-4">Métodos de Registro</h2>
        
        <div class="space-y-4">
            <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-blue-500 cursor-pointer">
                <div class="text-blue-500 mb-2">
                    <svg class="w-12 h-12 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path>
                    </svg>
                </div>
                <h3 class="font-semibold">Código QR</h3>
                <p class="text-gray-600 text-sm">Escanea código QR del empleado</p>
            </div>
            
            <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-green-500 cursor-pointer">
                <div class="text-green-500 mb-2">
                    <svg class="w-12 h-12 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                    </svg>
                </div>
                <h3 class="font-semibold">PIN</h3>
                <p class="text-gray-600 text-sm">Ingreso con código PIN</p>
            </div>
            
            <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-purple-500 cursor-pointer">
                <div class="text-purple-500 mb-2">
                    <svg class="w-12 h-12 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                </div>
                <h3 class="font-semibold">Cámara</h3>
                <p class="text-gray-600 text-sm">Reconocimiento facial</p>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-xl font-semibold mb-4">Registro Manual</h2>
        <form method="POST" action="/attendance.php?action=register">
            <div class="mb-4">
                <label for="employee_id" class="block text-gray-700 text-sm font-bold mb-2">Empleado</label>
                <select id="employee_id" name="employee_id" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <option value="">Selecciona un empleado</option>
                </select>
            </div>
            <div class="mb-4">
                <label for="method" class="block text-gray-700 text-sm font-bold mb-2">Método</label>
                <select id="method" name="method" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <option value="QR">QR</option>
                    <option value="PIN">PIN</option>
                    <option value="CAM">Cámara</option>
                </select>
            </div>
            <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline w-full">
                Registrar Asistencia
            </button>
        </form>
    </div>
</div>
';
$showHeader = true;
require_once '../layout.php';
