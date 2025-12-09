<?php
/**
 * Sistema de Registro con Reconocimiento Facial
 * RUTA: ~/app-php/public/attendance.php
 */

require_once dirname(__DIR__) . '/config/autoload.php';

$auth = new Auth();
$db = Database::getInstance();

$message = '';
$messageType = '';

// Obtener tenants para selección
$tenants = [];
try {
    $tenants = $db->fetchAll("SELECT id, name FROM tenants WHERE status = 'active' ORDER BY name");
} catch (Exception $e) {
    error_log("Tenants Error: " . $e->getMessage());
}

// Procesar registro por PIN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    if ($_POST['action'] === 'check_in_pin') {
        $tenantId = $_POST['tenant_id'] ?? '';
        $employeeCode = trim($_POST['employee_code'] ?? '');
        $pin = trim($_POST['pin'] ?? '');
        
        if (empty($tenantId) || empty($employeeCode) || empty($pin)) {
            $message = 'Por favor complete todos los campos';
            $messageType = 'error';
        } else {
            $result = $auth->loginWithPin($employeeCode, $pin);
            
            if ($result['success']) {
                $employee = $result['employee'];
                
                // Registrar asistencia
                $attendanceData = [
                    'tenant_id' => $employee['tenant_id'],
                    'employee_id' => $employee['id'],
                    'check_in' => date('Y-m-d H:i:s'),
                    'check_type' => 'pin',
                    'created_at' => date('Y-m-d H:i:s')
                ];
                
                try {
                    $attendanceId = $db->insert('attendance_logs', $attendanceData);
                    $message = '✅ Asistencia registrada: ' . $employee['first_name'] . ' ' . $employee['last_name'];
                    $messageType = 'success';
                } catch (Exception $e) {
                    $message = 'Error al registrar asistencia';
                    $messageType = 'error';
                    error_log("Attendance Error: " . $e->getMessage());
                }
            } else {
                $message = $result['message'];
                $messageType = 'error';
            }
        }
    }
    
    // Procesar registro facial
    if ($_POST['action'] === 'check_in_facial') {
        $tenantId = $_POST['tenant_id'] ?? '';
        $imageData = $_POST['image_data'] ?? '';
        
        if (empty($tenantId) || empty($imageData)) {
            $message = 'Datos incompletos para reconocimiento facial';
            $messageType = 'error';
        } else {
            // Decodificar y guardar imagen
            $imageData = str_replace('data:image/jpeg;base64,', '', $imageData);
            $imageData = str_replace(' ', '+', $imageData);
            $imageDecoded = base64_decode($imageData);
            
            $timestamp = time();
            $filename = 'facial_' . $timestamp . '_' . uniqid() . '.jpg';
            $filepath = FACIAL_IMAGES_PATH . '/' . $filename;
            
            if (file_put_contents($filepath, $imageDecoded)) {
                
                // SIMULACIÓN DE RECONOCIMIENTO FACIAL (MVP)
                // En producción, aquí llamarías a tu servicio Python
                
                // Por ahora, buscar un empleado del tenant para simular coincidencia
                try {
                    $employee = $db->fetch(
                        "SELECT e.* FROM employees e 
                         WHERE e.tenant_id = :tenant_id 
                         AND e.status = 'active' 
                         LIMIT 1",
                        ['tenant_id' => $tenantId]
                    );
                    
                    if ($employee) {
                        // Registrar asistencia
                        $attendanceData = [
                            'tenant_id' => $employee['tenant_id'],
                            'employee_id' => $employee['id'],
                            'check_in' => date('Y-m-d H:i:s'),
                            'check_type' => 'facial',
                            'created_at' => date('Y-m-d H:i:s')
                        ];
                        
                        $attendanceId = $db->insert('attendance_logs', $attendanceData);
                        
                        // Registrar log de validación facial
                        $validationData = [
                            'tenant_id' => $employee['tenant_id'],
                            'employee_id' => $employee['id'],
                            'attempt_image_path' => $filepath,
                            'embedding_match_score' => 0.95, // Simulado
                            'confidence_threshold' => FACIAL_CONFIDENCE_THRESHOLD,
                            'is_successful' => 1,
                            'device_type' => 'web_camera',
                            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                            'created_at' => date('Y-m-d H:i:s')
                        ];
                        
                        $db->insert('facial_validation_logs', $validationData);
                        
                        $message = '✅ Reconocimiento facial exitoso: ' . $employee['first_name'] . ' ' . $employee['last_name'];
                        $message .= '<br><small>📸 Imagen guardada: ' . $filename . '</small>';
                        $message .= '<br><small>🎯 Confianza: 95% (Simulado - MVP)</small>';
                        $messageType = 'success';
                    } else {
                        $message = '❌ No se encontró coincidencia facial en este tenant';
                        $messageType = 'error';
                    }
                } catch (Exception $e) {
                    $message = 'Error en el reconocimiento facial';
                    $messageType = 'error';
                    error_log("Facial Recognition Error: " . $e->getMessage());
                }
            } else {
                $message = 'Error al guardar la imagen';
                $messageType = 'error';
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
    <title>Registro de Asistencia - Jornix</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { background: white; border-radius: 15px; padding: 20px 30px; margin-bottom: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; }
        .header h1 { color: #667eea; font-size: 24px; }
        .header .time { font-size: 16px; color: #666; font-weight: 600; }
        .methods-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(380px, 1fr)); gap: 20px; margin-bottom: 20px; }
        .method-card { background: white; border-radius: 15px; padding: 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        .method-card h2 { color: #333; margin-bottom: 20px; font-size: 20px; display: flex; align-items: center; gap: 10px; }
        .form-group { margin-bottom: 18px; }
        label { display: block; margin-bottom: 8px; color: #555; font-weight: 500; font-size: 14px; }
        input, select { width: 100%; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 15px; transition: border-color 0.3s; }
        input:focus, select:focus { outline: none; border-color: #667eea; }
        .btn { width: 100%; padding: 14px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; transition: transform 0.2s; }
        .btn:hover { transform: translateY(-2px); }
        .btn:disabled { opacity: 0.6; cursor: not-allowed; }
        .camera-container { position: relative; width: 100%; max-width: 100%; margin: 0 auto 20px; border-radius: 12px; overflow: hidden; background: #000; }
        #video { width: 100%; display: block; transform: scaleX(-1); }
        #canvas { display: none; }
        .camera-overlay { position: absolute; top: 0; left: 0; right: 0; bottom: 0; pointer-events: none; }
        .face-guide { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 250px; height: 300px; border: 3px dashed rgba(255,255,255,0.5); border-radius: 50%; animation: pulse-border 2s infinite; }
        @keyframes pulse-border { 0%, 100% { opacity: 0.5; } 50% { opacity: 1; } }
        .camera-controls { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-top: 15px; }
        .btn-camera { padding: 12px; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.3s; }
        .btn-start { background: #4CAF50; color: white; }
        .btn-capture { background: #667eea; color: white; }
        .btn-stop { background: #f44336; color: white; }
        .btn-camera:hover { opacity: 0.9; transform: scale(1.02); }
        .btn-camera:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
        .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; font-size: 15px; animation: slideDown 0.3s ease; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .status { text-align: center; padding: 10px; margin-top: 10px; border-radius: 6px; font-size: 14px; font-weight: 500; }
        .status.active { background: #d4edda; color: #155724; }
        .status.inactive { background: #f8d7da; color: #721c24; }
        .status.processing { background: #fff3cd; color: #856404; }
        .preview-container { margin-top: 15px; text-align: center; }
        .preview-image { max-width: 100%; border-radius: 8px; border: 2px solid #667eea; }
        .instructions { background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 15px; }
        .instructions ul { padding-left: 20px; line-height: 1.8; color: #666; font-size: 13px; }
        .mvp-badge { background: #ffc107; color: #000; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; margin-left: 8px; }
        .back-link { display: inline-block; margin-bottom: 15px; color: white; text-decoration: none; padding: 10px 20px; background: rgba(255,255,255,0.2); border-radius: 8px; font-size: 14px; transition: all 0.3s; }
        .back-link:hover { background: rgba(255,255,255,0.3); }
    </style>
</head>
<body>
    <div class="container">
        <a href="login.php" class="back-link">← Volver al Login</a>
        
        <div class="header">
            <h1>🎯 Registro de Asistencia</h1>
            <div class="time" id="currentTime"></div>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <div class="methods-container">
            <!-- Método 1: PIN -->
            <div class="method-card">
                <h2>🔢 Registro con PIN</h2>
                
                <form method="POST" action="">
                    <input type="hidden" name="action" value="check_in_pin">
                    
                    <div class="form-group">
                        <label for="tenant_select_pin">🏢 Empresa</label>
                        <select id="tenant_select_pin" name="tenant_id" required>
                            <option value="">-- Seleccione empresa --</option>
                            <?php foreach ($tenants as $tenant): ?>
                                <option value="<?php echo $tenant['id']; ?>">
                                    <?php echo htmlspecialchars($tenant['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="employee_code">👤 Código de Empleado</label>
                        <input type="text" id="employee_code" name="employee_code" required placeholder="Ej: TC001" autocomplete="off">
                    </div>
                    
                    <div class="form-group">
                        <label for="pin">🔒 PIN (4 dígitos)</label>
                        <input type="password" id="pin" name="pin" required maxlength="4" pattern="[0-9]{4}" placeholder="••••" autocomplete="off">
                    </div>
                    
                    <button type="submit" class="btn">Registrar Entrada</button>
                </form>
            </div>
            
            <!-- Método 2: Reconocimiento Facial -->
            <div class="method-card">
                <h2>
                    📸 Reconocimiento Facial
                    <span class="mvp-badge">MVP</span>
                </h2>
                
                <div class="instructions">
                    <ul>
                        <li>✅ Selecciona tu empresa</li>
                        <li>✅ Presiona "Iniciar Cámara"</li>
                        <li>✅ Posiciona tu rostro en el óvalo</li>
                        <li>✅ Presiona "Capturar" cuando estés listo</li>
                    </ul>
                </div>
                
                <div class="form-group">
                    <label for="tenant_select_facial">🏢 Empresa</label>
                    <select id="tenant_select_facial" required>
                        <option value="">-- Seleccione empresa --</option>
                        <?php foreach ($tenants as $tenant): ?>
                            <option value="<?php echo $tenant['id']; ?>">
                                <?php echo htmlspecialchars($tenant['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="camera-container" id="cameraContainer" style="display: none;">
                    <video id="video" autoplay playsinline></video>
                    <div class="camera-overlay">
                        <div class="face-guide"></div>
                    </div>
                    <canvas id="canvas"></canvas>
                </div>
                
                <div id="cameraStatus" class="status inactive">
                    📷 Cámara inactiva - Selecciona empresa primero
                </div>
                
                <div class="camera-controls">
                    <button type="button" class="btn-camera btn-start" onclick="startCamera()" id="startBtn">
                        🎥 Iniciar
                    </button>
                    <button type="button" class="btn-camera btn-capture" onclick="captureAndRecognize()" disabled id="captureBtn">
                        📸 Capturar
                    </button>
                    <button type="button" class="btn-camera btn-stop" onclick="stopCamera()" disabled id="stopBtn">
                        ⏹️ Detener
                    </button>
                </div>
                
                <div id="previewContainer" class="preview-container" style="display: none;">
                    <p style="margin-bottom: 10px; font-weight: 600; color: #667eea;">Vista previa capturada:</p>
                    <img id="previewImage" class="preview-image" alt="Preview">
                </div>
                
                <form method="POST" id="facialForm" style="display:none;">
                    <input type="hidden" name="action" value="check_in_facial">
                    <input type="hidden" name="tenant_id" id="facialTenantId">
                    <input type="hidden" name="image_data" id="imageData">
                </form>
            </div>
        </div>
        
        <div class="method-card">
            <h2>ℹ️ Información del Sistema</h2>
            <div style="line-height: 1.8; color: #666;">
                <p>✅ <strong>Multi-tenant:</strong> Cada empresa tiene sus propios empleados</p>
                <p>✅ <strong>Registro por PIN:</strong> Código empleado + PIN de 4 dígitos</p>
                <p>✅ <strong>Reconocimiento Facial MVP:</strong> Captura y guarda imágenes</p>
                <p>⚠️ <strong>Nota:</strong> El reconocimiento facial actual es simulado (MVP). La imagen se guarda en <code>/uploads/facial/</code> para entrenamiento posterior con Python.</p>
                <p>🚀 <strong>Próximo paso:</strong> Integrar servicio Python con OpenCV/face_recognition para reconocimiento real</p>
            </div>
        </div>
    </div>
    
    <script>
        // Actualizar hora
        function updateTime() {
            const now = new Date();
            const options = { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            };
            document.getElementById('currentTime').textContent = 
                now.toLocaleDateString('es-CL', options);
        }
        
        updateTime();
        setInterval(updateTime, 1000);
        
        // Variables de cámara
        let stream = null;
        const video = document.getElementById('video');
        const canvas = document.getElementById('canvas');
        const captureBtn = document.getElementById('captureBtn');
        const stopBtn = document.getElementById('stopBtn');
        const startBtn = document.getElementById('startBtn');
        const cameraStatus = document.getElementById('cameraStatus');
        const cameraContainer = document.getElementById('cameraContainer');
        const previewContainer = document.getElementById('previewContainer');
        const previewImage = document.getElementById('previewImage');
        
        // Habilitar botón de inicio solo si hay tenant seleccionado
        document.getElementById('tenant_select_facial').addEventListener('change', function() {
            if (this.value) {
                startBtn.disabled = false;
                cameraStatus.textContent = '📷 Listo para iniciar cámara';
                cameraStatus.className = 'status inactive';
            } else {
                startBtn.disabled = true;
                cameraStatus.textContent = '📷 Selecciona empresa primero';
                cameraStatus.className = 'status inactive';
            }
        });
        
        // Iniciar cámara
        async function startCamera() {
            const tenantId = document.getElementById('tenant_select_facial').value;
            
            if (!tenantId) {
                alert('Por favor selecciona una empresa primero');
                return;
            }
            
            try {
                cameraStatus.textContent = '⏳ Iniciando cámara...';
                cameraStatus.className = 'status processing';
                
                stream = await navigator.mediaDevices.getUserMedia({ 
                    video: { 
                        width: { ideal: 1280 },
                        height: { ideal: 720 },
                        facingMode: 'user'
                    } 
                });
                
                video.srcObject = stream;
                cameraContainer.style.display = 'block';
                captureBtn.disabled = false;
                stopBtn.disabled = false;
                startBtn.disabled = true;
                
                cameraStatus.textContent = '✅ Cámara activa - Posiciona tu rostro';
                cameraStatus.className = 'status active';
                
            } catch (error) {
                console.error('Error al acceder a la cámara:', error);
                cameraStatus.textContent = '❌ Error: No se pudo acceder a la cámara';
                cameraStatus.className = 'status inactive';
                
                if (error.name === 'NotAllowedError') {
                    alert('⚠️ Permiso de cámara denegado.\n\nPor favor:\n1. Permite el acceso a la cámara\n2. Recarga la página\n3. Intenta nuevamente');
                } else if (error.name === 'NotFoundError') {
                    alert('⚠️ No se detectó ninguna cámara.\n\nVerifica que tu dispositivo tenga cámara disponible.');
                } else {
                    alert('⚠️ Error al acceder a la cámara: ' + error.message);
                }
            }
        }
        
        // Detener cámara
        function stopCamera() {
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
                video.srcObject = null;
                stream = null;
                
                cameraContainer.style.display = 'none';
                captureBtn.disabled = true;
                stopBtn.disabled = true;
                startBtn.disabled = false;
                
                cameraStatus.textContent = '📷 Cámara detenida';
                cameraStatus.className = 'status inactive';
                
                previewContainer.style.display = 'none';
            }
        }
        
        // Capturar y reconocer
        function captureAndRecognize() {
            const tenantId = document.getElementById('tenant_select_facial').value;
            
            if (!tenantId) {
                alert('Error: No se ha seleccionado empresa');
                return;
            }
            
            cameraStatus.textContent = '📸 Capturando imagen...';
            cameraStatus.className = 'status processing';
            
            const context = canvas.getContext('2d');
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            
            // Dibujar el frame actual (espejado para que se vea natural)
            context.translate(canvas.width, 0);
            context.scale(-1, 1);
            context.drawImage(video, 0, 0, canvas.width, canvas.height);
            
            // Obtener imagen en base64
            const imageData = canvas.toDataURL('image/jpeg', 0.9);
            
            // Mostrar preview
            previewImage.src = imageData;
            previewContainer.style.display = 'block';
            
            cameraStatus.textContent = '🔍 Procesando reconocimiento facial...';
            cameraStatus.className = 'status processing';
            
            // Enviar formulario
            document.getElementById('facialTenantId').value = tenantId;
            document.getElementById('imageData').value = imageData;
            
            // Simular delay de procesamiento (realista)
            setTimeout(() => {
                document.getElementById('facialForm').submit();
            }, 1500);
        }
        
        // Limpiar al salir
        window.addEventListener('beforeunload', () => {
            stopCamera();
        });
    </script>
</body>
</html>