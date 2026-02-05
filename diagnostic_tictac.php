<?php
/**
 * SCRIPT DE DIAGNÓSTICO
 * Para verificar compatibilidad del sistema con el generador de PDFs
 * 
 * INSTRUCCIONES:
 * 1. Subir a la raíz de tu CRM RISE
 * 2. Acceder vía navegador: https://tudominio.com/diagnostic_tictac.php
 * 3. Revisar los resultados
 * 4. ELIMINAR este archivo después de usarlo (seguridad)
 */

// Seguridad básica - cambiar esta contraseña
define('DIAGNOSTIC_PASSWORD', 'tictac2026');

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if ($_POST['password'] === DIAGNOSTIC_PASSWORD) {
        $_SESSION['diagnostic_auth'] = true;
    } else {
        die('❌ Contraseña incorrecta');
    }
}

if (!isset($_SESSION['diagnostic_auth'])) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Diagnóstico - Autenticación</title>
        <style>
            body { font-family: Arial, sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; background: #f5f5f5; }
            .login-box { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 2px 20px rgba(0,0,0,0.1); }
            input { padding: 12px; border: 2px solid #ddd; border-radius: 5px; width: 250px; font-size: 14px; }
            button { padding: 12px 30px; background: #E91E8C; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; margin-top: 15px; }
            button:hover { background: #C91E82; }
        </style>
    </head>
    <body>
        <div class="login-box">
            <h2 style="color: #E91E8C; margin-bottom: 20px;">🔐 Autenticación Requerida</h2>
            <form method="POST">
                <input type="password" name="password" placeholder="Contraseña" autofocus required>
                <br>
                <button type="submit">Acceder</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnóstico del Sistema - Tictac PDF Generator</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            color: #333;
        }
        .container { 
            max-width: 1000px; 
            margin: 0 auto; 
            background: white; 
            border-radius: 15px; 
            box-shadow: 0 10px 50px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #E91E8C 0%, #C91E82 100%);
            color: white;
            padding: 40px;
            text-align: center;
        }
        .header h1 { font-size: 32px; margin-bottom: 10px; }
        .header p { opacity: 0.9; }
        .content { padding: 40px; }
        .section { 
            margin-bottom: 30px; 
            padding: 25px; 
            border: 2px solid #f0f0f0; 
            border-radius: 10px;
            background: #fafafa;
        }
        .section h2 { 
            color: #E91E8C; 
            margin-bottom: 20px; 
            font-size: 20px;
            border-bottom: 2px solid #E91E8C;
            padding-bottom: 10px;
        }
        .check-item {
            display: flex;
            align-items: center;
            padding: 12px;
            margin: 8px 0;
            background: white;
            border-radius: 8px;
            border-left: 4px solid #ddd;
        }
        .check-item.success { border-left-color: #28a745; }
        .check-item.warning { border-left-color: #ffc107; background: #fff9e6; }
        .check-item.error { border-left-color: #dc3545; background: #ffe6e6; }
        .icon { 
            width: 30px; 
            height: 30px; 
            border-radius: 50%; 
            display: flex; 
            align-items: center; 
            justify-content: center;
            margin-right: 15px;
            font-weight: bold;
            font-size: 16px;
        }
        .success .icon { background: #28a745; color: white; }
        .warning .icon { background: #ffc107; color: #333; }
        .error .icon { background: #dc3545; color: white; }
        .info { 
            flex: 1;
            font-size: 14px;
        }
        .label { font-weight: 600; margin-bottom: 5px; }
        .value { 
            color: #666; 
            font-family: 'Courier New', monospace; 
            font-size: 13px;
        }
        .code-block {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 20px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            overflow-x: auto;
            margin-top: 15px;
        }
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: #E91E8C;
            color: white;
            text-decoration: none;
            border-radius: 50px;
            font-weight: 600;
            margin-top: 20px;
            transition: all 0.3s;
        }
        .btn:hover {
            background: #C91E82;
            transform: translateY(-2px);
        }
        .footer {
            text-align: center;
            padding: 30px;
            background: #f9f9f9;
            border-top: 1px solid #e0e0e0;
            color: #666;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔍 Diagnóstico del Sistema</h1>
            <p>Verificación de compatibilidad para Tictac PDF Generator</p>
        </div>
        
        <div class="content">
            
            <!-- PHP VERSION -->
            <div class="section">
                <h2>🐘 Versión de PHP</h2>
                <?php
                $php_version = phpversion();
                $php_ok = version_compare($php_version, '7.2.0', '>=');
                $class = $php_ok ? 'success' : 'error';
                $icon = $php_ok ? '✓' : '✗';
                ?>
                <div class="check-item <?php echo $class; ?>">
                    <div class="icon"><?php echo $icon; ?></div>
                    <div class="info">
                        <div class="label">Versión de PHP</div>
                        <div class="value"><?php echo $php_version; ?> 
                            <?php echo $php_ok ? '(Compatible ✓)' : '(Se requiere >= 7.2.0)'; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- TCPDF -->
            <div class="section">
                <h2>📄 TCPDF - Librería de PDFs</h2>
                <?php
                // Buscar TCPDF
                $tcpdf_paths = [
                    'application/libraries/Pdf.php',
                    'application/third_party/tcpdf/tcpdf.php',
                    'system/libraries/Pdf.php'
                ];
                
                $tcpdf_found = false;
                $tcpdf_path = '';
                $tcpdf_version = 'Desconocida';
                
                foreach ($tcpdf_paths as $path) {
                    if (file_exists($path)) {
                        $tcpdf_found = true;
                        $tcpdf_path = $path;
                        
                        // Intentar obtener versión
                        $content = file_get_contents($path);
                        if (preg_match('/define\s*\(\s*[\'"]TCPDF_VERSION[\'"]\s*,\s*[\'"]([0-9\.]+)[\'"]\s*\)/i', $content, $matches)) {
                            $tcpdf_version = $matches[1];
                        }
                        break;
                    }
                }
                
                $tcpdf_version_ok = version_compare($tcpdf_version, '6.0.0', '>=');
                $class = $tcpdf_found ? ($tcpdf_version_ok ? 'success' : 'warning') : 'error';
                $icon = $tcpdf_found ? '✓' : '✗';
                ?>
                
                <div class="check-item <?php echo $class; ?>">
                    <div class="icon"><?php echo $icon; ?></div>
                    <div class="info">
                        <div class="label">TCPDF Encontrado</div>
                        <div class="value">
                            <?php if ($tcpdf_found): ?>
                                ✓ Sí - Ubicación: <?php echo $tcpdf_path; ?>
                            <?php else: ?>
                                ✗ No encontrado
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <?php if ($tcpdf_found): ?>
                <div class="check-item <?php echo $tcpdf_version_ok ? 'success' : 'warning'; ?>">
                    <div class="icon"><?php echo $tcpdf_version_ok ? '✓' : '⚠'; ?></div>
                    <div class="info">
                        <div class="label">Versión de TCPDF</div>
                        <div class="value">
                            <?php echo $tcpdf_version; ?>
                            <?php if (!$tcpdf_version_ok): ?>
                                <br>⚠️ Versión antigua detectada. Recomendado: >= 6.0.0
                                <br>💡 Sugerencia: Usar controlador alternativo con mPDF
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- ARCHIVOS DEL SISTEMA -->
            <div class="section">
                <h2>📁 Archivos de Tictac PDF Generator</h2>
                <?php
                $files_to_check = [
                    'application/controllers/Proposal_tictac.php' => 'Controlador Principal',
                    'application/views/proposals/templates/proposal_tictac_template.php' => 'Plantilla HTML',
                    'files/system/tictac-logo-blanco.png' => 'Logo (Opcional)'
                ];
                
                foreach ($files_to_check as $file => $description) {
                    $exists = file_exists($file);
                    $class = $exists ? 'success' : 'error';
                    $icon = $exists ? '✓' : '✗';
                    ?>
                    <div class="check-item <?php echo $class; ?>">
                        <div class="icon"><?php echo $icon; ?></div>
                        <div class="info">
                            <div class="label"><?php echo $description; ?></div>
                            <div class="value">
                                <?php echo $exists ? '✓ Encontrado' : '✗ No encontrado'; ?>
                                - <?php echo $file; ?>
                            </div>
                        </div>
                    </div>
                    <?php
                }
                ?>
            </div>
            
            <!-- PERMISOS -->
            <div class="section">
                <h2>🔐 Permisos de Directorios</h2>
                <?php
                $dirs_to_check = [
                    'application/controllers/',
                    'application/views/proposals/templates/',
                    'files/system/'
                ];
                
                foreach ($dirs_to_check as $dir) {
                    if (file_exists($dir)) {
                        $writable = is_writable($dir);
                        $perms = substr(sprintf('%o', fileperms($dir)), -4);
                        $class = $writable ? 'success' : 'warning';
                        $icon = $writable ? '✓' : '⚠';
                        ?>
                        <div class="check-item <?php echo $class; ?>">
                            <div class="icon"><?php echo $icon; ?></div>
                            <div class="info">
                                <div class="label"><?php echo $dir; ?></div>
                                <div class="value">
                                    Permisos: <?php echo $perms; ?> 
                                    <?php echo $writable ? '(Escribible ✓)' : '(No escribible ⚠)'; ?>
                                </div>
                            </div>
                        </div>
                        <?php
                    }
                }
                ?>
            </div>
            
            <!-- EXTENSIONES PHP -->
            <div class="section">
                <h2>🔧 Extensiones PHP Requeridas</h2>
                <?php
                $required_extensions = [
                    'gd' => 'Procesamiento de imágenes',
                    'mbstring' => 'Soporte multi-byte strings',
                    'zlib' => 'Compresión',
                    'curl' => 'Transferencia de datos'
                ];
                
                foreach ($required_extensions as $ext => $desc) {
                    $loaded = extension_loaded($ext);
                    $class = $loaded ? 'success' : 'error';
                    $icon = $loaded ? '✓' : '✗';
                    ?>
                    <div class="check-item <?php echo $class; ?>">
                        <div class="icon"><?php echo $icon; ?></div>
                        <div class="info">
                            <div class="label"><?php echo $ext; ?></div>
                            <div class="value">
                                <?php echo $loaded ? '✓ Cargada' : '✗ No cargada'; ?>
                                - <?php echo $desc; ?>
                            </div>
                        </div>
                    </div>
                    <?php
                }
                ?>
            </div>
            
            <!-- RECOMENDACIONES -->
            <div class="section">
                <h2>💡 Recomendaciones</h2>
                <?php
                $recommendations = [];
                
                if (!$php_ok) {
                    $recommendations[] = "🔴 CRÍTICO: Actualizar PHP a versión >= 7.2";
                }
                
                if (!$tcpdf_found) {
                    $recommendations[] = "🔴 CRÍTICO: TCPDF no encontrado. Verificar instalación de RISE CRM.";
                }
                
                if ($tcpdf_found && !$tcpdf_version_ok) {
                    $recommendations[] = "🟡 IMPORTANTE: Versión de TCPDF antigua. Considerar usar el controlador alternativo con mPDF.";
                }
                
                if (!file_exists('application/controllers/Proposal_tictac.php')) {
                    $recommendations[] = "🔴 CRÍTICO: Falta el controlador principal. Subir Proposal_tictac.php";
                }
                
                if (!file_exists('application/views/proposals/templates/proposal_tictac_template.php')) {
                    $recommendations[] = "🔴 CRÍTICO: Falta la plantilla. Subir proposal_tictac_template.php";
                }
                
                if (!file_exists('files/system/tictac-logo-blanco.png')) {
                    $recommendations[] = "🟡 OPCIONAL: Subir logo para que aparezca en los PDFs.";
                }
                
                if (empty($recommendations)) {
                    $recommendations[] = "✅ ¡Todo está correcto! El sistema está listo para generar PDFs.";
                }
                
                foreach ($recommendations as $rec) {
                    echo "<div class='check-item'>";
                    echo "<div class='info'>{$rec}</div>";
                    echo "</div>";
                }
                ?>
            </div>
            
            <!-- PRÓXIMOS PASOS -->
            <div class="section">
                <h2>📋 Próximos Pasos</h2>
                <div class="info">
                    <?php if (empty(array_filter($recommendations, function($r) { return strpos($r, '🔴') !== false; }))): ?>
                        <p style="margin-bottom: 15px;">✅ <strong>El sistema está listo.</strong> Puedes probar el generador:</p>
                        <ol style="margin-left: 20px; line-height: 2;">
                            <li>Ve a un presupuesto en tu CRM RISE</li>
                            <li>Accede a: <code style="background: #f0f0f0; padding: 2px 8px; border-radius: 3px;">https://tudominio.com/index.php/proposal_tictac/preview/[ID]</code></li>
                            <li>Verifica que se ve correctamente</li>
                            <li>Genera el PDF: <code style="background: #f0f0f0; padding: 2px 8px; border-radius: 3px;">https://tudominio.com/index.php/proposal_tictac/download_pdf/[ID]</code></li>
                        </ol>
                    <?php else: ?>
                        <p style="margin-bottom: 15px;">⚠️ <strong>Hay problemas que resolver:</strong></p>
                        <ol style="margin-left: 20px; line-height: 2;">
                            <li>Revisa las recomendaciones en rojo (🔴) arriba</li>
                            <li>Soluciona cada problema</li>
                            <li>Vuelve a ejecutar este diagnóstico</li>
                        </ol>
                    <?php endif; ?>
                </div>
            </div>
            
            <div style="text-align: center; margin-top: 40px;">
                <a href="?logout=1" class="btn" onclick="return confirm('¿Cerrar sesión de diagnóstico?');">
                    Cerrar Diagnóstico
                </a>
            </div>
            
        </div>
        
        <div class="footer">
            <p><strong>Tictac Comunicación Digital SL</strong></p>
            <p>Herramienta de diagnóstico para generador de PDFs profesionales</p>
            <p style="margin-top: 10px; color: #dc3545; font-weight: bold;">
                ⚠️ IMPORTANTE: Eliminar este archivo (diagnostic_tictac.php) después de usarlo por seguridad
            </p>
        </div>
    </div>
</body>
</html>

<?php
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}
?>
