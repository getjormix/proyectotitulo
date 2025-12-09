<?php
$title = "Alertas - Smart Attendance";
$content = '
<div class="mb-8">
    <h1 class="text-3xl font-bold text-gray-800">Alertas</h1>
    <p class="text-gray-600">Sistema de alertas y notificaciones</p>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Empleado</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Score</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">';
        
        foreach($alerts as $alert) {
            $statusClass = $alert->estado == 'pendiente' ? 'bg-yellow-100 text-yellow-800' : 
                          ($alert->estado == 'enviado' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800');
            
            $content .= '
            <tr>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-gray-900">' . $alert->fecha . '</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm font-medium text-gray-900">' . htmlspecialchars($alert->employee_name) . '</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-gray-900">' . htmlspecialchars($alert->tipo) . '</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-gray-900">' . number_format($alert->score, 3) . '</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ' . $statusClass . '">
                        ' . ucfirst($alert->estado) . '
                    </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                    <a href="#" class="text-blue-600 hover:text-blue-900 mr-3">Ver</a>
                    <a href="#" class="text-green-600 hover:text-green-900">Resolver</a>
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
