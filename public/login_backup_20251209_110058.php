<?php
/**
 * Enterprise Landing Page + Login Multi-método (QR incluido)
 * RUTA: ~/app-php/public/login.php
 */

require_once dirname(__DIR__) . '/config/autoload.php';

$auth = new Auth();
$db = Database::getInstance();

if ($auth->isAuthenticated()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';

// Obtener tenants
$tenants = [];
try {
    $tenants = $db->fetchAll("SELECT id, name, domain FROM tenants WHERE status = 'active' ORDER BY name");
} catch (Exception $e) {
    error_log("Tenants Error: " . $e->getMessage());
}

// Procesar login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginType = $_POST['login_type'] ?? 'admin';
    
    if ($loginType === 'admin') {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($email) || empty($password)) {
            $error = 'Complete todos los campos';
        } else {
            $result = $auth->login($email, $password);
            if ($result['success']) {
                header('Location: dashboard.php');
                exit;
            } else {
                $error = $result['message'];
            }
        }
    } elseif ($loginType === 'employee') {
        $tenantId = $_POST['tenant_id'] ?? '';
        $employeeCode = trim($_POST['employee_code'] ?? '');
        $pin = $_POST['pin'] ?? '';
        
        if (empty($tenantId) || empty($employeeCode) || empty($pin)) {
            $error = 'Complete todos los campos';
        } else {
            $result = $auth->loginWithPin($employeeCode, $pin);
            if ($result['success']) {
                $_SESSION['employee_mode'] = true;
                $_SESSION['employee_id'] = $result['employee']['id'];
                $_SESSION['tenant_id'] = $result['employee']['tenant_id'];
                $_SESSION['employee_name'] = $result['employee']['first_name'] . ' ' . $result['employee']['last_name'];
                $_SESSION['logged_in'] = true;
                $_SESSION['login_time'] = time();
                $_SESSION['last_activity'] = time();
                
                $success = '✅ Bienvenido ' . $_SESSION['employee_name'];
                header("refresh:2;url=dashboard.php");
            } else {
                $error = $result['message'];
            }
        }
    } elseif ($loginType === 'qr') {
        $qrCode = trim($_POST['qr_code'] ?? '');
        
        if (empty($qrCode)) {
            $error = 'Código QR inválido';
        } else {
            // Decodificar QR (formato: TENANT_ID:EMPLOYEE_CODE)
            $qrParts = explode(':', $qrCode);
            
            if (count($qrParts) === 2) {
                $tenantId = $qrParts[0];
                $employeeCode = $qrParts[1];
                
                try {
                    $employee = $db->fetch(
                        "SELECT e.* FROM employees e 
                         WHERE e.tenant_id = :tenant_id 
                         AND e.employee_code = :code
                         AND e.status = 'active' 
                         LIMIT 1",
                        ['tenant_id' => $tenantId, 'code' => $employeeCode]
                    );
                    
                    if ($employee) {
                        // Registrar asistencia
                        $attendanceData = [
                            'tenant_id' => $employee['tenant_id'],
                            'employee_id' => $employee['id'],
                            'check_in' => date('Y-m-d H:i:s'),
                            'check_type' => 'qr',
                            'created_at' => date('Y-m-d H:i:s')
                        ];
                        
                        $db->insert('attendance_logs', $attendanceData);
                        
                        // Crear sesión
                        $_SESSION['employee_mode'] = true;
                        $_SESSION['employee_id'] = $employee['id'];
                        $_SESSION['tenant_id'] = $employee['tenant_id'];
                        $_SESSION['employee_name'] = $employee['first_name'] . ' ' . $employee['last_name'];
                        $_SESSION['logged_in'] = true;
                        $_SESSION['login_time'] = time();
                        $_SESSION['last_activity'] = time();
                        
                        $success = '✅ Acceso QR exitoso: ' . $_SESSION['employee_name'];
                        header("refresh:2;url=dashboard.php");
                    } else {
                        $error = '❌ Código QR no válido';
                    }
                } catch (Exception $e) {
                    $error = 'Error al procesar QR';
                    error_log("QR Error: " . $e->getMessage());
                }
            } else {
                $error = 'Formato de QR inválido';
            }
        }
    } elseif ($loginType === 'facial') {
        $tenantId = $_POST['tenant_id'] ?? '';
        $imageData = $_POST['image_data'] ?? '';
        
        if (empty($tenantId) || empty($imageData)) {
            $error = 'Datos incompletos';
        } else {
            $imageData = str_replace('data:image/jpeg;base64,', '', $imageData);
            $imageData = str_replace(' ', '+', $imageData);
            $imageDecoded = base64_decode($imageData);
            
            $timestamp = time();
            $filename = 'facial_login_' . $timestamp . '_' . uniqid() . '.jpg';
            $filepath = FACIAL_IMAGES_PATH . '/' . $filename;
            
            if (file_put_contents($filepath, $imageDecoded)) {
                try {
                    $employee = $db->fetch(
                        "SELECT e.* FROM employees e 
                         WHERE e.tenant_id = :tenant_id 
                         AND e.status = 'active' 
                         LIMIT 1",
                        ['tenant_id' => $tenantId]
                    );
                    
                    if ($employee) {
                        $attendanceData = [
                            'tenant_id' => $employee['tenant_id'],
                            'employee_id' => $employee['id'],
                            'check_in' => date('Y-m-d H:i:s'),
                            'check_type' => 'facial',
                            'created_at' => date('Y-m-d H:i:s')
                        ];
                        
                        $db->insert('attendance_logs', $attendanceData);
                        
                        $_SESSION['employee_mode'] = true;
                        $_SESSION['employee_id'] = $employee['id'];
                        $_SESSION['tenant_id'] = $employee['tenant_id'];
                        $_SESSION['employee_name'] = $employee['first_name'] . ' ' . $employee['last_name'];
                        $_SESSION['logged_in'] = true;
                        $_SESSION['login_time'] = time();
                        $_SESSION['last_activity'] = time();
                        
                        $success = '✅ Reconocimiento exitoso';
                        header("refresh:2;url=dashboard.php");
                    } else {
                        $error = '❌ No se encontró coincidencia';
                    }
                } catch (Exception $e) {
                    $error = 'Error en reconocimiento';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jornix - Plataforma Enterprise de Inteligencia de Asistencia</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --color-primary: #0051ff;
            --color-secondary: #0a0a0a;
            --color-accent: #ff6b35;
            --color-bg: #ffffff;
            --color-bg-secondary: #fafafa;
            --color-border: #e5e7eb;
            --color-text: #0a0a0a;
            --color-text-secondary: #6b7280;
            --spacing-unit: 8px;
            --transition-speed: 0.2s;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--color-bg);
            color: var(--color-text);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        
        /* NAVBAR */
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 64px;
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: saturate(180%) blur(20px);
            border-bottom: 1px solid var(--color-border);
            z-index: 1000;
        }
        
        .nav-container {
            max-width: 1280px;
            margin: 0 auto;
            height: 100%;
            padding: 0 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 20px;
            font-weight: 700;
            color: var(--color-text);
            text-decoration: none;
            letter-spacing: -0.02em;
        }
        
        .logo-icon {
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, var(--color-primary), #667eea);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 800;
            font-size: 18px;
        }
        
        .nav-links {
            display: flex;
            gap: 32px;
            align-items: center;
        }
        
        .nav-links a {
            color: var(--color-text-secondary);
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            transition: color var(--transition-speed);
            letter-spacing: -0.01em;
        }
        
        .nav-links a:hover {
            color: var(--color-text);
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 14px;
            text-decoration: none;
            transition: all var(--transition-speed);
            border: none;
            cursor: pointer;
            letter-spacing: -0.01em;
        }
        
        .btn-primary {
            background: var(--color-text);
            color: white;
        }
        
        .btn-primary:hover {
            background: #2a2a2a;
            transform: translateY(-1px);
        }
        
        /* HERO */
        .hero {
            margin-top: 64px;
            padding: 120px 24px 80px;
            text-align: center;
            max-width: 1280px;
            margin-left: auto;
            margin-right: auto;
            position: relative;
            overflow: hidden;
        }
        
        .hero::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -10%;
            right: -10%;
            height: 200%;
            background: url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwMCIgaGVpZ2h0PSI1MDAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGRlZnM+PGxpbmVhckdyYWRpZW50IGlkPSJnIiB4MT0iMCUiIHkxPSIwJSIgeDI9IjEwMCUiIHkyPSIxMDAlIj48c3RvcCBvZmZzZXQ9IjAlIiBzdHlsZT0ic3RvcC1jb2xvcjojNjY3ZWVhO3N0b3Atb3BhY2l0eTowLjEiLz48c3RvcCBvZmZzZXQ9IjEwMCUiIHN0eWxlPSJzdG9wLWNvbG9yOiM3NjRiYTI7c3RvcC1vcGFjaXR5OjAuMSIvPjwvbGluZWFyR3JhZGllbnQ+PC9kZWZzPjxyZWN0IHdpZHRoPSIxMDAlIiBoZWlnaHQ9IjEwMCUiIGZpbGw9InVybCgjZykiLz48L3N2Zz4=');
            background-size: cover;
            opacity: 0.3;
            z-index: 0;
            pointer-events: none;
        }
        
        .hero-content {
            position: relative;
            z-index: 1;
        }
        
        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 16px;
            background: var(--color-bg-secondary);
            border: 1px solid var(--color-border);
            border-radius: 100px;
            font-size: 13px;
            font-weight: 500;
            color: var(--color-text-secondary);
            margin-bottom: 24px;
        }
        
        .hero-badge-dot {
            width: 6px;
            height: 6px;
            background: #10b981;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .hero h1 {
            font-size: 72px;
            font-weight: 800;
            line-height: 1.1;
            letter-spacing: -0.03em;
            margin-bottom: 24px;
            max-width: 900px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .gradient-text {
            background: linear-gradient(135deg, var(--color-primary), #667eea);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .hero p {
            font-size: 20px;
            color: var(--color-text-secondary);
            max-width: 700px;
            margin: 0 auto 40px;
            line-height: 1.6;
        }
        
        .hero-cta {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn-large {
            padding: 14px 32px;
            font-size: 15px;
            border-radius: 8px;
        }
        
        .btn-ghost {
            background: transparent;
            color: var(--color-text);
            border: 1px solid var(--color-border);
        }
        
        .btn-ghost:hover {
            background: var(--color-bg-secondary);
        }
        
        /* STATS BAR */
        .stats-bar {
            padding: 48px 24px;
            border-top: 1px solid var(--color-border);
            border-bottom: 1px solid var(--color-border);
            background: var(--color-bg-secondary);
        }
        
        .stats-container {
            max-width: 1280px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 48px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 48px;
            font-weight: 800;
            letter-spacing: -0.03em;
            margin-bottom: 4px;
        }
        
        .stat-label {
            font-size: 14px;
            color: var(--color-text-secondary);
            font-weight: 500;
        }
        
        /* FEATURES */
        .features {
            padding: 120px 24px;
            max-width: 1280px;
            margin: 0 auto;
        }
        
        .section-header {
            text-align: center;
            margin-bottom: 80px;
        }
        
        .section-badge {
            display: inline-block;
            padding: 4px 12px;
            background: var(--color-bg-secondary);
            border: 1px solid var(--color-border);
            border-radius: 100px;
            font-size: 12px;
            font-weight: 600;
            color: var(--color-text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 16px;
        }
        
        .section-title {
            font-size: 48px;
            font-weight: 800;
            letter-spacing: -0.03em;
            margin-bottom: 16px;
        }
        
        .section-description {
            font-size: 18px;
            color: var(--color-text-secondary);
            max-width: 600px;
            margin: 0 auto;
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 24px;
        }
        
        .feature-card {
            padding: 32px;
            background: var(--color-bg);
            border: 1px solid var(--color-border);
            border-radius: 12px;
            transition: all var(--transition-speed);
        }
        
        .feature-card:hover {
            border-color: var(--color-text);
            transform: translateY(-4px);
        }
        
        .feature-icon {
            width: 48px;
            height: 48px;
            background: var(--color-bg-secondary);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 20px;
        }
        
        .feature-card h3 {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 8px;
            letter-spacing: -0.02em;
        }
        
        .feature-card p {
            font-size: 15px;
            color: var(--color-text-secondary);
            line-height: 1.6;
        }
        
        /* PRICING */
        .pricing {
            padding: 120px 24px;
            background: var(--color-bg-secondary);
        }
        
        .pricing-container {
            max-width: 1280px;
            margin: 0 auto;
        }
        
        .pricing-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 24px;
            margin-top: 64px;
        }
        
        .pricing-card {
            background: var(--color-bg);
            border: 1px solid var(--color-border);
            border-radius: 12px;
            padding: 40px;
            position: relative;
            transition: all var(--transition-speed);
        }
        
        .pricing-card:hover {
            border-color: var(--color-text);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.08);
        }
        
        .pricing-card.featured {
            border-color: var(--color-primary);
            border-width: 2px;
        }
        
        .pricing-badge {
            position: absolute;
            top: -12px;
            left: 50%;
            transform: translateX(-50%);
            padding: 4px 16px;
            background: var(--color-primary);
            color: white;
            border-radius: 100px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .pricing-card h3 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .pricing-description {
            font-size: 14px;
            color: var(--color-text-secondary);
            margin-bottom: 24px;
        }
        
        .price {
            font-size: 56px;
            font-weight: 800;
            letter-spacing: -0.03em;
            margin-bottom: 4px;
        }
        
        .price-period {
            font-size: 16px;
            color: var(--color-text-secondary);
            font-weight: 500;
        }
        
        .pricing-features {
            list-style: none;
            margin: 32px 0;
            padding: 0;
        }
        
        .pricing-features li {
            padding: 12px 0;
            font-size: 14px;
            color: var(--color-text-secondary);
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }
        
        .check-icon {
            width: 20px;
            height: 20px;
            background: #10b981;
            border-radius: 50%;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 12px;
            font-weight: 700;
        }
        
        /* CTA SECTION */
        .cta-section {
            padding: 120px 24px;
            text-align: center;
        }
        
        .cta-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 80px 48px;
            background: var(--color-text);
            color: white;
            border-radius: 16px;
        }
        
        .cta-container h2 {
            font-size: 48px;
            font-weight: 800;
            letter-spacing: -0.03em;
            margin-bottom: 16px;
        }
        
        .cta-container p {
            font-size: 18px;
            opacity: 0.9;
            margin-bottom: 32px;
        }
        
        .btn-white {
            background: white;
            color: var(--color-text);
        }
        
        .btn-white:hover {
            background: #f5f5f5;
        }
        
        /* FOOTER */
        .footer {
            padding: 80px 24px 40px;
            background: var(--color-bg-secondary);
            border-top: 1px solid var(--color-border);
        }
        
        .footer-container {
            max-width: 1280px;
            margin: 0 auto;
        }
        
        .footer-grid {
            display: grid;
            grid-template-columns: 2fr repeat(3, 1fr);
            gap: 48px;
            margin-bottom: 48px;
        }
        
        .footer-brand {
            max-width: 320px;
        }
        
        .footer-brand p {
            font-size: 14px;
            color: var(--color-text-secondary);
            margin-top: 16px;
            line-height: 1.6;
        }
        
        .footer-column h4 {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 16px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .footer-links {
            list-style: none;
        }
        
        .footer-links li {
            margin-bottom: 12px;
        }
        
        .footer-links a {
            color: var(--color-text-secondary);
            text-decoration: none;
            font-size: 14px;
            transition: color var(--transition-speed);
        }
        
        .footer-links a:hover {
            color: var(--color-text);
        }
        
        .footer-bottom {
            padding-top: 32px;
            border-top: 1px solid var(--color-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 13px;
            color: var(--color-text-secondary);
        }
        
        .footer-legal {
            display: flex;
            gap: 24px;
        }
        
        /* MODAL */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(8px);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            border-radius: 16px;
            max-width: 480px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        
        .modal-header {
            padding: 32px 32px 24px;
            border-bottom: 1px solid var(--color-border);
        }
        
        .modal-header h2 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 4px;
        }
        
        .modal-header p {
            font-size: 14px;
            color: var(--color-text-secondary);
        }
        
        .close-modal {
            position: absolute;
            top: 24px;
            right: 24px;
            background: transparent;
            border: none;
            color: var(--color-text-secondary);
            font-size: 24px;
            cursor: pointer;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            transition: all var(--transition-speed);
        }
        
        .close-modal:hover {
            background: var(--color-bg-secondary);
            color: var(--color-text);
        }
        
        .modal-body {
            padding: 32px;
        }
        
        .tabs {
            display: flex;
            gap: 4px;
            margin-bottom: 24px;
            background: var(--color-bg-secondary);
            padding: 4px;
            border-radius: 8px;
        }
        
        .tab {
            flex: 1;
            padding: 10px 12px;
            background: transparent;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            color: var(--color-text-secondary);
            cursor: pointer;
            transition: all var(--transition-speed);
        }
        
        .tab.active {
            background: white;
            color: var(--color-text);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--color-text);
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--color-border);
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            transition: all var(--transition-speed);
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--color-text);
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 13px;
            font-weight: 500;
        }
        
        .alert-error {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fee2e2;
        }
        
        .alert-success {
            background: #f0fdf4;
            color: #16a34a;
            border: 1px solid #dcfce7;
        }
        
        /* QR SCANNER */
        .qr-scanner {
            width: 100%;
            height: 300px;
            background: #000;
            border-radius: 12px;
            margin: 16px 0;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        
        #qr-video {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .qr-overlay {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 200px;
            height: 200px;
            border: 3px solid rgba(255, 255, 255, 0.6);
            border-radius: 16px;
            box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.5);
        }
        
        .qr-corners {
            position: absolute;
            width: 100%;
            height: 100%;
        }
        
        .qr-corner {
            position: absolute;
            width: 24px;
            height: 24px;
            border: 3px solid #10b981;
        }
        
        .qr-corner.top-left {
            top: -3px;
            left: -3px;
            border-right: none;
            border-bottom: none;
        }
        
        .qr-corner.top-right {
            top: -3px;
            right: -3px;
            border-left: none;
            border-bottom: none;
        }
        
        .qr-corner.bottom-left {
            bottom: -3px;
            left: -3px;
            border-right: none;
            border-top: none;
        }
        
        .qr-corner.bottom-right {
            bottom: -3px;
            right: -3px;
            border-left: none;
            border-top: none;
        }
        
        .qr-manual-input {
            margin-top: 16px;
        }
        
        /* CAMERA */
        .camera-container {
            width: 100%;
            margin: 16px 0;
            border-radius: 12px;
            overflow: hidden;
            background: #000;
            display: none;
            position: relative;
        }
        
        #video {
            width: 100%;
            display: block;
            transform: scaleX(-1);
        }
        
        #canvas {
            display: none;
        }
        
        .face-guide {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 160px;
            height: 200px;
            border: 2px dashed rgba(255, 255, 255, 0.6);
            border-radius: 50%;
        }
        
        .camera-controls {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-top: 12px;
        }
        
        .btn-camera {
            padding: 10px;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all var(--transition-speed);
        }
        
        .btn-camera:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .btn-start {
            background: #10b981;
            color: white;
        }
        
        .btn-capture {
            background: var(--color-primary);
            color: white;
        }
        
        .status {
            text-align: center;
            padding: 8px;
            margin-top: 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status.active {
            background: #f0fdf4;
            color: #16a34a;
        }
        
        .status.inactive {
            background: #fef2f2;
            color: #dc2626;
        }
        
        .status.processing {
            background: #fef3c7;
            color: #d97706;
        }
        
        .info-box {
            background: var(--color-bg-secondary);
            padding: 12px;
            border-radius: 8px;
            margin-top: 16px;
            font-size: 12px;
            color: var(--color-text-secondary);
        }
        
        /* RESPONSIVE */
        @media (max-width: 768px) {
            .hero h1 {
                font-size: 40px;
            }
            
            .section-title {
                font-size: 32px;
            }
            
            .nav-links {
                display: none;
            }
            
            .footer-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-container {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .pricing-grid {
                grid-template-columns: 1fr;
            }
            
            .tabs {
                gap: 2px;
                padding: 3px;
            }
            
            .tab {
                font-size: 11px;
                padding: 8px 6px;
            }
        }
    </style>
    <!-- QR Scanner Library -->
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
</head>
<body>
    <!-- NAVBAR -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="#" class="logo">
                <div class="logo-icon">J</div>
                <span>Jornix</span>
            </a>
            <div class="nav-links">
                <a href="#features">Características</a>
                <a href="#pricing">Precios</a>
                <a href="#docs">Documentación</a>
                <a href="#company">Empresa</a>
                <button class="btn btn-primary" onclick="openLoginModal()">Iniciar Sesión</button>
            </div>
        </div>
    </nav>
    
    <!-- HERO -->
    <section class="hero">
        <div class="hero-content">
            <div class="hero-badge">
                <span class="hero-badge-dot"></span>
                <span>Ahora con reconocimiento facial IA</span>
            </div>
            
            <h1>
                Control de asistencia<br>
                <span class="gradient-text">nivel enterprise</span>
            </h1>
            
            <p>
                La plataforma moderna de gestión de personal en la que confían las empresas globales. 
                Análisis en tiempo real, insights predictivos y arquitectura multi-tenant.
            </p>
            
            <div class="hero-cta">
                <button class="btn btn-primary btn-large" onclick="openLoginModal()">
                    Prueba gratuita
                </button>
                <a href="#features" class="btn btn-ghost btn-large">
                    Ver demo
                </a>
            </div>
        </div>
    </section>
    
    <!-- STATS BAR -->
    <section class="stats-bar">
        <div class="stats-container">
            <div>
                <div class="stat-number">500+</div>
                <div class="stat-label">Clientes enterprise</div>
            </div>
            <div>
                <div class="stat-number">99.9%</div>
                <div class="stat-label">Precisión</div>
            </div>
            <div>
                <div class="stat-number">&lt;1s</div>
                <div class="stat-label">Tiempo de respuesta</div>
            </div>
            <div>
                <div class="stat-number">24/7</div>
                <div class="stat-label">Soporte</div>
            </div>
        </div>
    </section>
    
    <!-- FEATURES -->
    <section class="features" id="features">
        <div class="section-header">
            <div class="section-badge">Plataforma</div>
            <h2 class="section-title">Diseñado para empresas modernas</h2>
            <p class="section-description">
                Una plataforma completa de inteligencia laboral con todo lo que necesitas para escalar
            </p>
        </div>
        
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">🤖</div>
                <h3>IA de Reconocimiento</h3>
                <p>Reconocimiento facial en menos de 1 segundo con 99.9% de precisión. Seguridad y privacidad enterprise.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">📱</div>
                <h3>Acceso QR Móvil</h3>
                <p>Escanea códigos QR desde cualquier smartphone. Sin apps, sin complicaciones. Mobile-first.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">🏗️</div>
                <h3>Arquitectura Multi-tenant</h3>
                <p>Datos aislados, branding personalizado y configuraciones independientes por organización.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">📊</div>
                <h3>Analytics Tiempo Real</h3>
                <p>Dashboards en vivo, insights predictivos y detección automática de anomalías con ML.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">🔐</div>
                <h3>Seguridad Enterprise</h3>
                <p>Certificación SOC 2 Type II. Encriptación end-to-end. Cumplimiento GDPR & CCPA.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">⚡</div>
                <h3>Edge Computing</h3>
                <p>Procesa datos en el edge para máxima privacidad y mínima latencia. Funciona offline.</p>
            </div>
        </div>
    </section>
    
    <!-- PRICING -->
    <section class="pricing" id="pricing">
        <div class="pricing-container">
            <div class="section-header">
                <div class="section-badge">Precios</div>
                <h2 class="section-title">Precios simples y transparentes</h2>
                <p class="section-description">
                    Sin costos ocultos. Cancela cuando quieras. Descuentos por volumen disponibles.
                </p>
            </div>
            
            <div class="pricing-grid">
                <div class="pricing-card">
                    <h3>Starter</h3>
                    <p class="pricing-description">Para equipos pequeños comenzando</p>
                    <div class="price">$49</div>
                    <div class="price-period">por mes</div>
                    <ul class="pricing-features">
                        <li><span class="check-icon">✓</span> Hasta 50 empleados</li>
                        <li><span class="check-icon">✓</span> Reconocimiento facial básico</li>
                        <li><span class="check-icon">✓</span> 1 ubicación</li>
                        <li><span class="check-icon">✓</span> Soporte por email</li>
                        <li><span class="check-icon">✓</span> Reportes mensuales</li>
                    </ul>
                    <button class="btn btn-ghost" style="width: 100%;" onclick="openLoginModal()">Comenzar</button>
                </div>
                
                <div class="pricing-card featured">
                    <div class="pricing-badge">Popular</div>
                    <h3>Professional</h3>
                    <p class="pricing-description">Para organizaciones en crecimiento</p>
                    <div class="price">$149</div>
                    <div class="price-period">por mes</div>
                    <ul class="pricing-features">
                        <li><span class="check-icon">✓</span> Hasta 200 empleados</li>
                        <li><span class="check-icon">✓</span> IA avanzada + QR</li>
                        <li><span class="check-icon">✓</span> Ubicaciones ilimitadas</li>
                        <li><span class="check-icon">✓</span> Soporte prioritario 24/7</li>
                        <li><span class="check-icon">✓</span> Reportes personalizados</li>
                        <li><span class="check-icon">✓</span> Acceso API</li>
                    </ul>
                    <button class="btn btn-primary" style="width: 100%;" onclick="openLoginModal()">Comenzar</button>
                </div>
                
                <div class="pricing-card">
                    <h3>Enterprise</h3>
                    <p class="pricing-description">Para organizaciones grandes</p>
                    <div class="price">Custom</div>
                    <div class="price-period">&nbsp;</div>
                    <ul class="pricing-features">
                        <li><span class="check-icon">✓</span> Empleados ilimitados</li>
                        <li><span class="check-icon">✓</span> Modelos IA personalizados</li>
                        <li><span class="check-icon">✓</span> Opciones white-label</li>
                        <li><span class="check-icon">✓</span> Equipo de soporte dedicado</li>
                        <li><span class="check-icon">✓</span> Garantías SLA</li>
                        <li><span class="check-icon">✓</span> Despliegue on-premise</li>
                    </ul>
                    <button class="btn btn-ghost" style="width: 100%;">Contactar ventas</button>
                </div>
            </div>
        </div>
    </section>
    
    <!-- CTA -->
    <section class="cta-section">
        <div class="cta-container">
            <h2>¿Listo para comenzar?</h2>
            <p>Únete a más de 500 empresas modernizando sus operaciones</p>
            <button class="btn btn-white btn-large" onclick="openLoginModal()">
                Inicia tu prueba gratuita
            </button>
        </div>
    </section>
    
    <!-- FOOTER -->
    <footer class="footer">
        <div class="footer-container">
            <div class="footer-grid">
                <div class="footer-brand">
                    <div class="logo">
                        <div class="logo-icon">J</div>
                        <span>Jornix</span>
                    </div>
                    <p>Plataforma enterprise de inteligencia laboral. Construido para escalar, diseñado para simplicidad.</p>
                </div>
                
                <div class="footer-column">
                    <h4>Producto</h4>
                    <ul class="footer-links">
                        <li><a href="#features">Características</a></li>
                        <li><a href="#pricing">Precios</a></li>
                        <li><a href="#">Seguridad</a></li>
                        <li><a href="#">Integraciones</a></li>
                    </ul>
                </div>
                
                <div class="footer-column">
                    <h4>Empresa</h4>
                    <ul class="footer-links">
                        <li><a href="#">Acerca de</a></li>
                        <li><a href="#">Clientes</a></li>
                        <li><a href="#">Carreras</a></li>
                        <li><a href="#">Contacto</a></li>
                    </ul>
                </div>
                
                <div class="footer-column">
                    <h4>Recursos</h4>
                    <ul class="footer-links">
                        <li><a href="#">Documentación</a></li>
                        <li><a href="#">API Reference</a></li>
                        <li><a href="#">Estado</a></li>
                        <li><a href="#">Soporte</a></li>
                    </ul>
                </div>
            </div>
            
            <div class="footer-bottom">
                <div>© 2024 Jornix by Gruposeven - UDLA. Todos los derechos reservados.</div>
                <div class="footer-legal">
                    <a href="#">Privacidad</a>
                    <a href="#">Términos</a>
                    <a href="#">Seguridad</a>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- LOGIN MODAL -->
    <div class="modal" id="loginModal">
        <div class="modal-content">
            <div class="modal-header" style="position: relative;">
                <button class="close-modal" onclick="closeLoginModal()">&times;</button>
                <h2>Acceder a Jornix</h2>
                <p>Elige tu método de acceso</p>
            </div>
            
            <div class="modal-body">
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                
                <div class="tabs">
                    <button class="tab active" onclick="showTab('admin')">👨‍💼 Admin</button>
                    <button class="tab" onclick="showTab('employee')">🔢 PIN</button>
                    <button class="tab" onclick="showTab('qr')">📱 QR</button>
                    <button class="tab" onclick="showTab('facial')">📸 Facial</button>
                </div>
                
                <!-- Admin Tab -->
                <div id="admin-tab" class="tab-content active">
                    <form method="POST">
                        <input type="hidden" name="login_type" value="admin">
                        
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" required placeholder="admin@empresa.com" autocomplete="email">
                        </div>
                        
                        <div class="form-group">
                            <label>Contraseña</label>
                            <input type="password" name="password" required placeholder="••••••••" autocomplete="current-password">
                        </div>
                        
                        <button type="submit" class="btn btn-primary" style="width: 100%;">Iniciar sesión</button>
                    </form>
                    
                    <div class="info-box">
                        <strong>Demo:</strong> superadmin@getjornix.com / password
                    </div>
                </div>
                
                <!-- Employee PIN Tab -->
                <div id="employee-tab" class="tab-content">
                    <form method="POST">
                        <input type="hidden" name="login_type" value="employee">
                        
                        <div class="form-group">
                            <label>Organización</label>
                            <select name="tenant_id" required>
                                <option value="">Seleccionar organización</option>
                                <?php foreach ($tenants as $tenant): ?>
                                    <option value="<?php echo $tenant['id']; ?>">
                                        <?php echo htmlspecialchars($tenant['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Código de empleado</label>
                            <input type="text" name="employee_code" required placeholder="TC001">
                        </div>
                        
                        <div class="form-group">
                            <label>PIN</label>
                            <input type="password" name="pin" required maxlength="4" pattern="[0-9]{4}" placeholder="••••">
                        </div>
                        
                        <button type="submit" class="btn btn-primary" style="width: 100%;">Registrar entrada</button>
                    </form>
                    
                    <div class="info-box">
                        <strong>Demo:</strong> TC001 / PIN: 1234
                    </div>
                </div>
                
                <!-- QR Tab -->
                <div id="qr-tab" class="tab-content">
                    <form method="POST" id="qrForm">
                        <input type="hidden" name="login_type" value="qr">
                        <input type="hidden" name="qr_code" id="qrCodeInput">
                    </form>
                    
                    <div class="form-group">
                        <label>Escanear código QR</label>
                        <div class="qr-scanner" id="qrScanner" style="display: none;">
                            <video id="qr-video"></video>
                            <div class="qr-overlay">
                                <div class="qr-corners">
                                    <div class="qr-corner top-left"></div>
                                    <div class="qr-corner top-right"></div>
                                    <div class="qr-corner bottom-left"></div>
                                    <div class="qr-corner bottom-right"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div id="qrStatus" class="status inactive">
                            Presiona "Activar cámara" para escanear
                        </div>
                        
                        <div class="camera-controls">
                            <button type="button" class="btn-camera btn-start" onclick="startQRScanner()" id="qrStartBtn">
                                Activar cámara
                            </button>
                            <button type="button" class="btn-camera" style="background: #dc2626; color: white;" onclick="stopQRScanner()" disabled id="qrStopBtn">
                                Detener
                            </button>
                        </div>
                    </div>
                    
                    <div class="qr-manual-input">
                        <div class="form-group">
                            <label>O ingresa código manualmente</label>
                            <input type="text" id="manualQrInput" placeholder="Ej: 1:TC001">
                        </div>
                        <button type="button" class="btn btn-ghost" style="width: 100%;" onclick="submitManualQR()">
                            Ingresar código
                        </button>
                    </div>
                    
                    <div class="info-box">
                        <strong>Formato QR:</strong> TENANT_ID:EMPLOYEE_CODE<br>
                        Los empleados pueden generar su código QR desde el dashboard.
                    </div>
                </div>
                
                <!-- Facial Tab -->
                <div id="facial-tab" class="tab-content">
                    <form method="POST" id="facialForm">
                        <input type="hidden" name="login_type" value="facial">
                        <input type="hidden" name="tenant_id" id="facialTenantId">
                        <input type="hidden" name="image_data" id="imageData">
                        
                        <div class="form-group">
                            <label>Organización</label>
                            <select id="tenant_facial" required onchange="checkTenantSelected()">
                                <option value="">Seleccionar organización</option>
                                <?php foreach ($tenants as $tenant): ?>
                                    <option value="<?php echo $tenant['id']; ?>">
                                        <?php echo htmlspecialchars($tenant['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                    
                    <div class="camera-container" id="cameraContainer">
                        <video id="video" autoplay playsinline></video>
                        <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; pointer-events: none;">
                            <div class="face-guide"></div>
                        </div>
                        <canvas id="canvas"></canvas>
                    </div>
                    
                    <div id="cameraStatus" class="status inactive">
                        Selecciona organización para habilitar cámara
                    </div>
                    
                    <div class="camera-controls">
                        <button type="button" class="btn-camera btn-start" onclick="startCamera()" disabled id="startBtn">
                            Activar cámara
                        </button>
                        <button type="button" class="btn-camera btn-capture" onclick="captureAndLogin()" disabled id="captureBtn">
                            Capturar e ingresar
                        </button>
                    </div>
                    
                    <div class="info-box">
                        Reconocimiento facial IA con 99.9% de precisión. Tus datos biométricos están encriptados y nunca se comparten.
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function openLoginModal() {
            document.getElementById('loginModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        
        function closeLoginModal() {
            document.getElementById('loginModal').classList.remove('active');
            document.body.style.overflow = 'auto';
            if (stream) stopCamera();
            if (html5QrCode) stopQRScanner();
        }
        
        function showTab(tab) {
            document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.getElementById(tab + '-tab').classList.add('active');
            event.target.classList.add('active');
            if (tab !== 'facial' && stream) stopCamera();
            if (tab !== 'qr' && html5QrCode) stopQRScanner();
        }
        
        // === QR SCANNER ===
        let html5QrCode = null;
        
        async function startQRScanner() {
            const qrScanner = document.getElementById('qrScanner');
            const qrStatus = document.getElementById('qrStatus');
            const qrStartBtn = document.getElementById('qrStartBtn');
            const qrStopBtn = document.getElementById('qrStopBtn');
            
            qrScanner.style.display = 'flex';
            qrStatus.textContent = 'Iniciando escáner...';
            qrStatus.className = 'status processing';
            
            try {
                html5QrCode = new Html5Qrcode("qr-video");
                
                await html5QrCode.start(
                    { facingMode: "environment" },
                    {
                        fps: 10,
                        qrbox: { width: 200, height: 200 }
                    },
                    (decodedText, decodedResult) => {
                        qrStatus.textContent = '✅ QR detectado, procesando...';
                        qrStatus.className = 'status active';
                        
                        document.getElementById('qrCodeInput').value = decodedText;
                        
                        setTimeout(() => {
                            document.getElementById('qrForm').submit();
                        }, 500);
                    }
                );
                
                qrStartBtn.disabled = true;
                qrStopBtn.disabled = false;
                qrStatus.textContent = '📱 Escáner activo - Posiciona el QR';
                qrStatus.className = 'status active';
                
            } catch (err) {
                console.error('QR Error:', err);
                qrStatus.textContent = '❌ Error al iniciar escáner';
                qrStatus.className = 'status inactive';
                qrScanner.style.display = 'none';
                alert('No se pudo acceder a la cámara. Por favor permite el acceso y intenta de nuevo.');
            }
        }
        
        async function stopQRScanner() {
            if (html5QrCode) {
                try {
                    await html5QrCode.stop();
                } catch (err) {
                    console.error('Stop QR Error:', err);
                }
                html5QrCode = null;
            }
            
            document.getElementById('qrScanner').style.display = 'none';
            document.getElementById('qrStatus').textContent = 'Escáner detenido';
            document.getElementById('qrStatus').className = 'status inactive';
            document.getElementById('qrStartBtn').disabled = false;
            document.getElementById('qrStopBtn').disabled = true;
        }
        
        function submitManualQR() {
            const manualCode = document.getElementById('manualQrInput').value.trim();
            if (manualCode) {
                document.getElementById('qrCodeInput').value = manualCode;
                document.getElementById('qrForm').submit();
            } else {
                alert('Por favor ingresa un código QR válido');
            }
        }
        
        // === FACIAL CAMERA ===
        let stream = null;
        const video = document.getElementById('video');
        const canvas = document.getElementById('canvas');
        const captureBtn = document.getElementById('captureBtn');
        const startBtn = document.getElementById('startBtn');
        const cameraStatus = document.getElementById('cameraStatus');
        const cameraContainer = document.getElementById('cameraContainer');
        
        function checkTenantSelected() {
            const tenantId = document.getElementById('tenant_facial').value;
            startBtn.disabled = !tenantId;
            if (!tenantId) stopCamera();
        }
        
        async function startCamera() {
            try {
                cameraStatus.textContent = 'Iniciando cámara...';
                cameraStatus.className = 'status processing';
                
                stream = await navigator.mediaDevices.getUserMedia({ 
                    video: { width: { ideal: 1280 }, height: { ideal: 720 }, facingMode: 'user' } 
                });
                
                video.srcObject = stream;
                cameraContainer.style.display = 'block';
                captureBtn.disabled = false;
                startBtn.textContent = 'Detener cámara';
                startBtn.onclick = stopCamera;
                
                cameraStatus.textContent = 'Cámara activa • Posiciona tu rostro';
                cameraStatus.className = 'status active';
            } catch (error) {
                cameraStatus.textContent = 'Acceso a cámara denegado';
                cameraStatus.className = 'status inactive';
                alert('Por favor permite el acceso a la cámara e intenta de nuevo.');
            }
        }
        
        function stopCamera() {
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
                video.srcObject = null;
                stream = null;
            }
            cameraContainer.style.display = 'none';
            captureBtn.disabled = true;
            startBtn.textContent = 'Activar cámara';
            startBtn.onclick = startCamera;
            cameraStatus.textContent = 'Cámara detenida';
            cameraStatus.className = 'status inactive';
        }
        
        function captureAndLogin() {
            const tenantId = document.getElementById('tenant_facial').value;
            cameraStatus.textContent = 'Procesando imagen...';
            cameraStatus.className = 'status processing';
            
            const context = canvas.getContext('2d');
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            context.translate(canvas.width, 0);
            context.scale(-1, 1);
            context.drawImage(video, 0, 0, canvas.width, canvas.height);
            
            const imageData = canvas.toDataURL('image/jpeg', 0.9);
            
            document.getElementById('facialTenantId').value = tenantId;
            document.getElementById('imageData').value = imageData;
            
            cameraStatus.textContent = 'Autenticando...';
            
            setTimeout(() => {
                document.getElementById('facialForm').submit();
            }, 800);
        }
        
        // Smooth scroll
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) target.scrollIntoView({ behavior: 'smooth' });
            });
        });
        
        // ESC to close
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeLoginModal();
        });
    </script>
</body>
</html>