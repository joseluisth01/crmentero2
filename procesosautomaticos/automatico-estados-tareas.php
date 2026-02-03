<?php
// Script Automatico - SOLO CLIENTES DE LOS ULTIMOS 10 MINUTOS

error_reporting(E_ERROR);
require('funcionesbbdd.php');

// CONFIGURACION
$FECHA_CORTE = '2025-01-28';

// PROTECCION MAXIMA: Solo clientes creados en los ultimos 10 MINUTOS
$MINUTOS_LIMITE = 10;

// Email
$FROM_EMAIL = 'hola@tictac-comunicacion.es';
$FROM_NAME = 'Tictac Comunicación';

// Archivos
$PROCESSED_FILE = __DIR__ . '/clientes_procesados.json';
$AUDIT_FILE = __DIR__ . '/auditoria_bienvenida.json';

function guardarAuditoria($tipo, $clienteId, $clienteNombre, $email, $estado, $mensaje = '') {
    global $AUDIT_FILE;
    
    $auditoria = array();
    if (file_exists($AUDIT_FILE)) {
        $contenido = file_get_contents($AUDIT_FILE);
        $auditoria = json_decode($contenido, true);
        if (!is_array($auditoria)) $auditoria = array();
    }
    
    $registro = array(
        'id' => uniqid(),
        'fecha' => date('Y-m-d H:i:s'),
        'tipo' => $tipo,
        'cliente_id' => $clienteId,
        'cliente_nombre' => $clienteNombre,
        'email' => $email,
        'estado' => $estado,
        'mensaje' => $mensaje
    );
    
    array_unshift($auditoria, $registro);
    $auditoria = array_slice($auditoria, 0, 500);
    file_put_contents($AUDIT_FILE, json_encode($auditoria, JSON_PRETTY_PRINT));
}

function obtenerProcesados() {
    global $PROCESSED_FILE;
    
    if (file_exists($PROCESSED_FILE)) {
        $contenido = file_get_contents($PROCESSED_FILE);
        $data = json_decode($contenido, true);
        return is_array($data) ? $data : array();
    }
    return array();
}

function marcarProcesado($clienteId) {
    global $PROCESSED_FILE;
    $procesados = obtenerProcesados();
    
    if (!in_array($clienteId, $procesados)) {
        $procesados[] = $clienteId;
        file_put_contents($PROCESSED_FILE, json_encode($procesados));
    }
}

function enviarEmail($destinatario, $nombre, $empresa) {
    global $FROM_EMAIL, $FROM_NAME;
    
    $asunto = '¡Bienvenido/a a Tictac Comunicación!';
    
    $mensaje = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
        body{font-family:Arial,sans-serif;background:#f5f5f5;margin:0;padding:0}
        .container{max-width:600px;margin:0 auto;background:white}
        .header{background:linear-gradient(135deg,#E91E8C 0%,#C91E82 100%);color:white;padding:40px 30px;text-align:center}
        .logo-img{max-width:250px;height:auto}
        .content{padding:40px 30px}
        .greeting{font-size:24px;color:#333;margin-bottom:20px}
        .message{font-size:16px;line-height:1.6;color:#555;margin-bottom:20px}
        .highlight{color:#E91E8C;font-weight:bold}
        .benefits{background:#f9f9f9;padding:25px;border-left:4px solid #E91E8C;margin:25px 0}
        .benefits ul{margin:15px 0;padding-left:20px}
        .benefits li{margin:10px 0;color:#555}
        .footer{background:#333;color:white;padding:30px;text-align:center}
        .footer-logo{max-width:180px;height:auto;margin-bottom:15px}
    </style></head><body>
        <div class="container">
            <div class="header">
                <img src="https://tictac-comunicacion.es/wp-content/uploads/2025/12/LOGO-1.png" alt="Tictac Comunicación" class="logo-img">
            </div>
            <div class="content">
                <div class="greeting">¡Hola ' . htmlspecialchars($nombre) . '! 👋</div>
                <p class="message">
                    Es un placer darte la bienvenida a <span class="highlight">' . htmlspecialchars($empresa) . '</span> 
                    como nuevo cliente de <strong>Tictac Comunicación</strong>.
                </p>
                <p class="message">
                    Somos tu agencia de marketing digital en Córdoba, especializada en impulsar negocios 
                    como el tuyo a través de estrategias personalizadas y resultados medibles.
                </p>
                <div class="benefits">
                    <strong style="color:#E91E8C;font-size:18px">¿Qué puedes esperar de nosotros?</strong>
                    <ul>
                        <li><strong>Estrategias personalizadas</strong> adaptadas a tus objetivos</li>
                        <li><strong>Comunicación directa</strong> con nuestro equipo</li>
                        <li><strong>Reportes detallados</strong> del progreso de tus campañas</li>
                        <li><strong>Resultados medibles</strong> en cada campaña</li>
                        <li><strong>Soporte prioritario</strong> cuando lo necesites</li>
                    </ul>
                </div>
                <p class="message" style="margin-top:30px">
                    <strong>Próximos pasos:</strong><br>
                    Nuestro equipo se pondrá en contacto contigo en las próximas 24-48 horas 
                    para coordinar una reunión inicial y definir la mejor estrategia para tu negocio.
                </p>
                <p class="message">Si tienes alguna pregunta, no dudes en contactarnos.</p>
                <p class="message" style="margin-top:40px">
                    <strong>¡Estamos emocionados de trabajar contigo!</strong><br>
                    <span style="color:#E91E8C">El equipo de Tictac Comunicación</span>
                </p>
            </div>
            <div class="footer">
                <img src="https://tictac-comunicacion.es/wp-content/uploads/2025/12/LOGO-1.png" alt="Tictac" class="footer-logo">
                <p><strong>Tictac Comunicación</strong></p>
                <p style="margin-top:10px;font-size:14px">
                    📧 hola@tictac-comunicacion.es<br>
                    🌐 www.tictac-comunicacion.es
                </p>
                <p style="margin-top:20px;font-size:11px;color:#999">
                    © ' . date('Y') . ' Tictac Comunicación. Todos los derechos reservados.
                </p>
            </div>
        </div>
    </body></html>';
    
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=utf-8\r\n";
    $headers .= "From: " . $FROM_NAME . " <" . $FROM_EMAIL . ">\r\n";
    $headers .= "Reply-To: " . $FROM_EMAIL . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
    
    return mail($destinatario, $asunto, $mensaje, $headers);
}

// PROCESO PRINCIPAL
echo "[" . date('Y-m-d H:i:s') . "] Bienvenida - SOLO ULTIMOS $MINUTOS_LIMITE MINUTOS\n";

$mysqli = conexion();
$mysqli->select_db("gestiontictaccom_admin");

$procesados = obtenerProcesados();
echo "Clientes procesados: " . count($procesados) . "\n";

// FECHA LIMITE: Ultimos X minutos
$fechaLimite = date('Y-m-d H:i:s', strtotime('-' . $MINUTOS_LIMITE . ' minutes'));
echo "LIMITE ESTRICTO: Solo clientes creados despues de $fechaLimite\n";

// CONSULTA MUY RESTRICTIVA
$sql = "SELECT c.id, c.company_name, c.created_date, c.deleted
        FROM crm_clients c
        WHERE c.deleted = 0 
        AND c.created_date > '" . $fechaLimite . "'
        ORDER BY c.id DESC";

if (!$resultado = $mysqli->query($sql)) {
    echo "Error: " . $mysqli->error . "\n";
    guardarAuditoria('sistema', 0, 'Sistema', '', 'error', 'Error SQL');
    exit;
}

$clientesEncontrados = $resultado->num_rows;
echo "Clientes en ultimos $MINUTOS_LIMITE min: $clientesEncontrados\n";

if ($clientesEncontrados === 0) {
    echo "Sin clientes nuevos.\n";
    guardarAuditoria('sistema', 0, 'Sistema', '', 'ejecutado', "Sin clientes en ultimos $MINUTOS_LIMITE min");
    $mysqli->close();
    exit;
}

$nuevosClientes = 0;
$emailsEnviados = 0;

while ($cliente = $resultado->fetch_array()) {
    $clienteId = $cliente['id'];
    $clienteNombre = $cliente['company_name'];
    $fechaCreacion = $cliente['created_date'];
    
    if (in_array($clienteId, $procesados)) {
        echo "  YA PROCESADO: $clienteId\n";
        continue;
    }
    
    $nuevosClientes++;
    echo "\n[NUEVO] ID: $clienteId - $clienteNombre\n";
    echo "  Creado: $fechaCreacion\n";
    
    $sql2 = "SELECT first_name, last_name, email, is_primary_contact
             FROM crm_users
             WHERE client_id = $clienteId AND deleted = 0
             ORDER BY is_primary_contact DESC, id ASC
             LIMIT 1";
    
    if (!$resultado2 = $mysqli->query($sql2)) {
        guardarAuditoria('bienvenida', $clienteId, $clienteNombre, '', 'error', 'Error contacto');
        marcarProcesado($clienteId);
        continue;
    }
    
    if ($resultado2->num_rows === 0) {
        echo "  SIN CONTACTO\n";
        guardarAuditoria('bienvenida', $clienteId, $clienteNombre, '', 'sin_contacto', 'Sin contacto');
        marcarProcesado($clienteId);
        continue;
    }
    
    $contacto = $resultado2->fetch_array();
    $email = $contacto['email'];
    $nombreContacto = trim($contacto['first_name'] . ' ' . $contacto['last_name']);
    
    if (empty($nombreContacto)) $nombreContacto = 'Estimado/a';
    
    if (empty($email)) {
        echo "  SIN EMAIL\n";
        guardarAuditoria('bienvenida', $clienteId, $clienteNombre, '', 'sin_email', 'Sin email');
        marcarProcesado($clienteId);
        continue;
    }
    
    echo "  Enviando a: $email\n";
    
    if (enviarEmail($email, $nombreContacto, $clienteNombre)) {
        echo "  OK: Enviado\n";
        guardarAuditoria('bienvenida', $clienteId, $clienteNombre, $email, 'enviado', 'Email enviado (ultimos ' . $MINUTOS_LIMITE . ' min)');
        $emailsEnviados++;
    } else {
        echo "  ERROR\n";
        guardarAuditoria('bienvenida', $clienteId, $clienteNombre, $email, 'error', 'Error enviar');
    }
    
    marcarProcesado($clienteId);
}

$mysqli->close();

echo "\n=== RESUMEN ===\n";
echo "Ventana: Ultimos $MINUTOS_LIMITE minutos\n";
echo "Nuevos: $nuevosClientes\n";
echo "Enviados: $emailsEnviados\n";

guardarAuditoria('sistema', 0, 'Sistema', '', 'ejecutado', "Ultimos {$MINUTOS_LIMITE}min: $nuevosClientes nuevos, $emailsEnviados enviados");
?>