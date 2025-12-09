<?php
$title = "Seleccionar Tenant - Smart Attendance";
$content = '
<div class="min-h-screen flex items-center justify-center">
    <div class="bg-white p-8 rounded-lg shadow-md w-96">
        <h1 class="text-2xl font-bold text-center mb-6">Seleccionar Empresa</h1>
        <form method="POST">
            <div class="mb-4">
                <label for="tenant_id" class="block text-gray-700 text-sm font-bold mb-2">Selecciona tu empresa:</label>
                <select id="tenant_id" name="tenant_id" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <option value="">-- Selecciona una empresa --</option>';
                    
                    foreach($tenants as $tenant) {
                        $content .= '<option value="' . $tenant->id . '">' . htmlspecialchars($tenant->name) . '</option>';
                    }
                    
$content .= '
                </select>
            </div>
            <div class="flex items-center justify-between">
                <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline w-full">
                    Continuar
                </button>
            </div>
        </form>
    </div>
</div>
';
$showHeader = false;
require_once 'layout.php';
