<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'Smart Attendance' ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <?php if (isset($showHeader) && $showHeader): ?>
    <header class="bg-white shadow-sm">
        <div class="max-w-7xl mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <h1 class="text-xl font-semibold">Smart Attendance</h1>
                <nav>
                    <a href="/dashboard" class="text-blue-600 hover:text-blue-800 mx-2">Dashboard</a>
                    <a href="/logout" class="text-red-600 hover:text-red-800 mx-2">Cerrar Sesión</a>
                </nav>
            </div>
        </div>
    </header>
    <?php endif; ?>
    
    <main class="max-w-7xl mx-auto px-4 py-6">
        <?= $content ?>
    </main>
</body>
</html>
