<?php
$title = "Dispositivos - Smart Attendance";
$content = '
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">Dispositivos</h1>
            <p class="text-gray-600">Gestión de dispositivos de registro</p>
        </div>
        <button class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
            Nuevo Dispositivo
        </button>
    </div>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Descripción</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ubicación</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">';
        
        foreach($devices as $device) {
            $statusClass = $device->activo ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
            $statusText = $device->activo ? 'Activo' : 'Inactivo';
            
            $content .= '
            <tr>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm font-medium text-gray-900">' . htmlspecialchars($device->descripcion) . '</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-gray-900">' . htmlspecialchars($device->ubicacion) . '</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-gray-900">' . ucfirst($device->tipo) . '</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ' . $statusClass . '">
                        ' . $statusText . '
                    </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                    <a href="#" class="text-blue-600 hover:text-blue-900 mr-3">Editar</a>
                    <a href="#" class="text-red-600 hover:text-red-900">Eliminar</a>
                </td>
            </tr>';
        }
        
$content .= '
        </tbody>
    </table>
</div>
';
$showHeader = true;
require_once '../layout.php';
