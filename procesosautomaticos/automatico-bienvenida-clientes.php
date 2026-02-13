<?php
// SISTEMA BIENVENIDA AUTOMATICA
// Genera 2 archivos JSON: clientes_nuevos.json y auditoria_emails.json

error_reporting(E_ERROR);
require('funcionesbbdd.php');

// CONFIGURACION
$MINUTOS_LIMITE = 30;
$DESFASE_HORAS = 1;
$FROM_EMAIL = 'hola@tictac-comunicacion.es';
$FROM_NAME = 'Tictac Comunicación';

// ARCHIVOS JSON
$CLIENTES_NUEVOS_FILE = __DIR__ . '/clientes_nuevos.json';
$AUDITORIA_EMAILS_FILE = __DIR__ . '/auditoria_emails.json';

// Cargar clientes nuevos
function obtenerClientesNuevos() {
    global $CLIENTES_NUEVOS_FILE;
    if (file_exists($CLIENTES_NUEVOS_FILE)) {
        $contenido = file_get_contents($CLIENTES_NUEVOS_FILE);
        $data = json_decode($contenido, true);
        return is_array($data) ? $data : array();
    }
    return array();
}

// Guardar cliente nuevo
function guardarClienteNuevo($clienteId, $clienteNombre, $fechaCreacion, $email) {
    global $CLIENTES_NUEVOS_FILE;
    $clientes = obtenerClientesNuevos();
    
    $clientes[] = array(
        'id' => $clienteId,
        'nombre' => $clienteNombre,
        'fecha_creacion' => $fechaCreacion,
        'email' => $email,
        'fecha_deteccion' => date('Y-m-d H:i:s')
    );
    
    file_put_contents($CLIENTES_NUEVOS_FILE, json_encode($clientes, JSON_PRETTY_PRINT));
}

// Guardar en auditoria de emails
function guardarAuditoriaEmail($clienteId, $clienteNombre, $email, $estado, $mensaje) {
    global $AUDITORIA_EMAILS_FILE;
    
    $auditoria = array();
    if (file_exists($AUDITORIA_EMAILS_FILE)) {
        $contenido = file_get_contents($AUDITORIA_EMAILS_FILE);
        $auditoria = json_decode($contenido, true);
        if (!is_array($auditoria)) $auditoria = array();
    }
    
    $registro = array(
        'fecha' => date('Y-m-d H:i:s'),
        'cliente_id' => $clienteId,
        'cliente_nombre' => $clienteNombre,
        'email' => $email,
        'estado' => $estado,
        'mensaje' => $mensaje
    );
    
    array_unshift($auditoria, $registro);
    $auditoria = array_slice($auditoria, 0, 500);
    
    file_put_contents($AUDITORIA_EMAILS_FILE, json_encode($auditoria, JSON_PRETTY_PRINT));
}

// Verificar si cliente ya fue procesado
function yaFueProcesado($clienteId) {
    $clientes = obtenerClientesNuevos();
    foreach ($clientes as $cliente) {
        if ($cliente['id'] == $clienteId) {
            return true;
        }
    }
    return false;
}

// Enviar email
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
                <img src="https://tictac-comunicacion.es/wp-content/uploads/2026/02/LOGO-1-2.png" alt="Tictac Comunicación" class="logo-img">
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
                <img src="https://tictac-comunicacion.es/wp-content/uploads/2026/02/LOGO-1-2.png" alt="Tictac" class="footer-logo">
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
echo "[" . date('Y-m-d H:i:s') . "] Iniciando...\n";

$mysqli = conexion();
$mysqli->select_db("gestiontictaccom_admin");

$minutosTotal = $MINUTOS_LIMITE + ($DESFASE_HORAS * 60);
$fechaLimite = date('Y-m-d H:i:s', strtotime('-' . $minutosTotal . ' minutes'));

$sql = "SELECT 
            c.id, 
            c.company_name, 
            c.created_date,
            DATE_ADD(c.created_date, INTERVAL $DESFASE_HORAS HOUR) as created_date_ajustada
        FROM crm_clients c
        WHERE c.deleted = 0 
        AND DATE_ADD(c.created_date, INTERVAL $DESFASE_HORAS HOUR) > '$fechaLimite'
        ORDER BY c.id DESC";

if (!$resultado = $mysqli->query($sql)) {
    echo "ERROR SQL\n";
    exit;
}

$clientesEncontrados = $resultado->num_rows;
echo "Encontrados: $clientesEncontrados\n";

if ($clientesEncontrados === 0) {
    echo "Sin clientes nuevos\n";
    $mysqli->close();
    exit;
}

$nuevos = 0;
$enviados = 0;

while ($cliente = $resultado->fetch_array()) {
    $clienteId = $cliente['id'];
    $clienteNombre = $cliente['company_name'];
    $fechaCreacion = $cliente['created_date_ajustada'];
    
    if (yaFueProcesado($clienteId)) {
        continue;
    }
    
    echo "\n[NUEVO] $clienteNombre (ID: $clienteId)\n";
    
    // Buscar contacto (sin filtro de is_primary_contact para asegurar que lo encuentra)
    $sql2 = "SELECT first_name, last_name, email
             FROM crm_users
             WHERE client_id = $clienteId AND deleted = 0
             AND email IS NOT NULL AND email != ''
             ORDER BY is_primary_contact DESC, id ASC
             LIMIT 1";
    
    if (!$resultado2 = $mysqli->query($sql2)) {
        guardarClienteNuevo($clienteId, $clienteNombre, $fechaCreacion, '');
        guardarAuditoriaEmail($clienteId, $clienteNombre, '', 'error', 'Error al buscar contacto');
        continue;
    }
    
    if ($resultado2->num_rows === 0) {
        echo "  Sin contacto con email\n";
        guardarClienteNuevo($clienteId, $clienteNombre, $fechaCreacion, '');
        guardarAuditoriaEmail($clienteId, $clienteNombre, '', 'sin_email', 'Cliente sin contacto con email');
        continue;
    }
    
    $contacto = $resultado2->fetch_array();
    $email = $contacto['email'];
    $nombreContacto = trim($contacto['first_name'] . ' ' . $contacto['last_name']);
    
    if (empty($nombreContacto)) $nombreContacto = 'Estimado/a';
    
    echo "  Enviando a: $email\n";
    
    // Guardar cliente nuevo
    guardarClienteNuevo($clienteId, $clienteNombre, $fechaCreacion, $email);
    $nuevos++;
    
    // Enviar email
    if (enviarEmail($email, $nombreContacto, $clienteNombre)) {
        echo "  OK\n";
        guardarAuditoriaEmail($clienteId, $clienteNombre, $email, 'enviado', 'Email enviado correctamente');
        $enviados++;
    } else {
        echo "  ERROR\n";
        guardarAuditoriaEmail($clienteId, $clienteNombre, $email, 'error', 'Error al enviar email');
    }
}

$mysqli->close();

echo "\n=== RESUMEN ===\n";
echo "Nuevos: $nuevos\n";
echo "Emails enviados: $enviados\n";
echo "Archivos generados:\n";
echo "  - clientes_nuevos.json\n";
echo "  - auditoria_emails.json\n";
?>