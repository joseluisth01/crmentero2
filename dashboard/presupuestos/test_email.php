<?php
/**
 * TEST DE EMAIL - Formulario de prueba
 * Ubicación: /dashboard/presupuestos/test_email.php
 */

// Procesar envío si viene del formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enviar_test'])) {
    $emailDestino = $_POST['email_destino'] ?? '';
    
    if (empty($emailDestino)) {
        $mensaje = "❌ Debes introducir un email";
        $tipo = "error";
    } else {
        // Incluir la función de envío
        require_once __DIR__ . '/gmail_send.php';
        
        // HTML del email de prueba
        $htmlBody = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { background: #E91E8C; color: white; padding: 40px 30px; text-align: center; }
        .header img { max-width: 180px; margin-bottom: 15px; }
        .header h1 { margin: 0; font-size: 24px; }
        .content { padding: 40px 30px; }
        .footer { background: #1a1a1a; color: white; padding: 30px; text-align: center; font-size: 13px; }
        .footer a { color: #E91E8C; text-decoration: none; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="https://tictac-comunicacion.es/wp-content/uploads/2025/12/LOGO-1.png" alt="Tictac Comunicación">
            <h1>✅ Email de Prueba</h1>
        </div>
        <div class="content">
            <h2>¡Funciona Correctamente!</h2>
            <p>Este es un email de prueba del sistema de presupuestos.</p>
            <p>Si has recibido este email, significa que la configuración de Gmail API está funcionando perfectamente.</p>
            <p><strong>Detalles técnicos:</strong></p>
            <ul>
                <li>Enviado desde: Dashboard de Presupuestos</li>
                <li>Sistema: Gmail API del CRM</li>
                <li>Fecha: ' . date('d/m/Y H:i:s') . '</li>
            </ul>
        </div>
        <div class="footer">
            <strong>Tictac Comunicación Digital SL</strong><br>
            📍 Plaza de los Carrillos, 5 · 14001 Córdoba<br>
            📞 <a href="tel:+34957048147">957 048 147</a><br>
            ✉ <a href="mailto:hola@tictac-comunicacion.es">hola@tictac-comunicacion.es</a>
        </div>
    </div>
</body>
</html>';
        
        // Intentar enviar
        $resultado = enviarEmailGmailAPI(
            $emailDestino,
            'Test - Sistema de Presupuestos Tictac',
            $htmlBody,
            array() // Sin adjuntos
        );
        
        if ($resultado) {
            $mensaje = "✅ Email enviado correctamente a: " . htmlspecialchars($emailDestino);
            $tipo = "success";
        } else {
            $mensaje = "❌ Error al enviar el email. Revisa los logs.";
            $tipo = "error";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test de Email - Tictac</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 500px;
            width: 100%;
        }
        
        h1 {
            color: #E91E8C;
            text-align: center;
            margin-bottom: 10px;
            font-size: 28px;
        }
        
        .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        
        input[type="email"] {
            width: 100%;
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s;
        }
        
        input[type="email"]:focus {
            outline: none;
            border-color: #E91E8C;
            box-shadow: 0 0 0 3px rgba(233, 30, 140, 0.1);
        }
        
        .btn {
            width: 100%;
            padding: 15px;
            background: #E91E8C;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn:hover {
            background: #c91a75;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(233, 30, 140, 0.3);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 2px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 2px solid #f5c6cb;
        }
        
        .info-box {
            background: #fff3cd;
            border: 2px solid #ffeaa7;
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
            font-size: 13px;
            color: #856404;
        }
        
        .info-box strong {
            display: block;
            margin-bottom: 5px;
        }
        
        .logs-link {
            text-align: center;
            margin-top: 20px;
        }
        
        .logs-link a {
            color: #667eea;
            text-decoration: none;
            font-size: 13px;
        }
        
        .logs-link a:hover {
            text-decoration: underline;
        }
        
        .back-btn {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #666;
            text-decoration: none;
            font-size: 14px;
        }
        
        .back-btn:hover {
            color: #E91E8C;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📧 Test de Email</h1>
        <p class="subtitle">Prueba el sistema de envío de emails</p>
        
        <?php if (isset($mensaje)): ?>
            <div class="alert alert-<?php echo $tipo; ?>">
                <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="email_destino">Email de destino:</label>
                <input 
                    type="email" 
                    id="email_destino" 
                    name="email_destino" 
                    placeholder="tu@email.com"
                    value="<?php echo isset($_POST['email_destino']) ? htmlspecialchars($_POST['email_destino']) : ''; ?>"
                    required
                    autofocus
                >
            </div>
            
            <button type="submit" name="enviar_test" class="btn">
                🚀 Enviar Email de Prueba
            </button>
        </form>
        
        <div class="info-box">
            <strong>ℹ️ Qué se enviará:</strong>
            • Email con diseño de Tictac<br>
            • Logo corporativo<br>
            • Sin adjuntos (solo prueba)<br>
            • Desde: hola@tictac-comunicacion.es
        </div>
        
        <div class="logs-link">
            <a href="logs/email_debug.log" target="_blank">📄 Ver logs de debug</a>
        </div>
        
        <a href="index.php" class="back-btn">← Volver a Presupuestos</a>
    </div>
</body>
</html>