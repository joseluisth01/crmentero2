<?php
/**
 * API Backend - Presupuestos
 * Maneja: Guardar, Eliminar, Generar PDF, Enviar Email
 */

require_once '../config.php';

// Archivo de almacenamiento
$presupuestosFile = DATA_PATH . '/presupuestos.json';

// Determinar acción
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'guardar':
    case 'guardar_pdf':
        guardarPresupuesto();
        break;
    
    case 'delete':
        eliminarPresupuesto();
        break;
    
    case 'pdf':
        generarPDF();
        break;
    
    case 'email':
        enviarEmail();
        break;
    
    default:
        mostrarError('Acción no válida');
}

/**
 * Guardar presupuesto
 */
function guardarPresupuesto() {
    global $presupuestosFile;
    
    // Validar datos requeridos
    if (empty($_POST['cliente_nombre']) || empty($_POST['cliente_email']) || empty($_POST['fecha_propuesta'])) {
        echo json_encode(['success' => false, 'message' => 'Faltan datos requeridos']);
        exit;
    }
    
    // Cargar presupuestos existentes
    $presupuestos = [];
    if (file_exists($presupuestosFile)) {
        $presupuestos = json_decode(file_get_contents($presupuestosFile), true);
        if (!is_array($presupuestos)) $presupuestos = [];
    }
    
    // Generar ID si es nuevo
    $id = $_POST['id'] ?? 'PRES-' . date('Ymd') . '-' . str_pad(count($presupuestos) + 1, 4, '0', STR_PAD_LEFT);
    
    // Procesar items
    $items = [];
    if (isset($_POST['items']) && is_array($_POST['items'])) {
        foreach ($_POST['items'] as $item) {
            if (!empty($item['nombre'])) {
                $items[] = [
                    'nombre' => $item['nombre'],
                    'descripcion' => $item['descripcion'] ?? '',
                    'cantidad' => floatval($item['cantidad']),
                    'precio' => floatval($item['precio'])
                ];
            }
        }
    }
    
    // Crear objeto presupuesto
    $presupuesto = [
        'id' => $id,
        'fecha_propuesta' => $_POST['fecha_propuesta'],
        'valido_hasta' => $_POST['valido_hasta'],
        'cliente_id' => $_POST['cliente_id'] ?? '',
        'cliente_nombre' => $_POST['cliente_nombre'],
        'cliente_email' => $_POST['cliente_email'],
        'cliente_telefono' => $_POST['cliente_telefono'] ?? '',
        'cliente_direccion' => $_POST['cliente_direccion'] ?? '',
        'cliente_ciudad' => $_POST['cliente_ciudad'] ?? '',
        'cliente_cp' => $_POST['cliente_cp'] ?? '',
        'cliente_pais' => $_POST['cliente_pais'] ?? '',
        'cliente_cif' => $_POST['cliente_cif'] ?? '',
        'items' => $items,
        'iva' => floatval($_POST['iva'] ?? 21),
        'segundo_impuesto' => floatval($_POST['segundo_impuesto'] ?? 0),
        'subtotal' => floatval($_POST['subtotal'] ?? 0),
        'total' => floatval($_POST['total'] ?? 0),
        'notas' => $_POST['notas'] ?? '',
        'estado' => 'borrador',
        'fecha_creacion' => isset($_POST['id']) ? ($presupuesto['fecha_creacion'] ?? date('Y-m-d H:i:s')) : date('Y-m-d H:i:s'),
        'fecha_modificacion' => date('Y-m-d H:i:s')
    ];
    
    // Actualizar o añadir
    $encontrado = false;
    foreach ($presupuestos as $key => $p) {
        if ($p['id'] === $id) {
            $presupuestos[$key] = $presupuesto;
            $encontrado = true;
            break;
        }
    }
    
    if (!$encontrado) {
        $presupuestos[] = $presupuesto;
    }
    
    // Guardar
    file_put_contents($presupuestosFile, json_encode($presupuestos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    // Registrar en auditoría
    guardarAuditoria('presupuesto_guardado', 'exitoso', 'Presupuesto guardado: ' . $id, [
        'cliente_id' => $presupuesto['cliente_id'],
        'cliente_nombre' => $presupuesto['cliente_nombre'],
        'email' => $presupuesto['cliente_email']
    ]);
    
    echo json_encode(['success' => true, 'id' => $id, 'message' => 'Presupuesto guardado correctamente']);
    exit;
}

/**
 * Eliminar presupuesto
 */
function eliminarPresupuesto() {
    global $presupuestosFile;
    
    $id = $_GET['id'] ?? '';
    
    if (empty($id)) {
        header('Location: index.php?error=id_invalido');
        exit;
    }
    
    if (file_exists($presupuestosFile)) {
        $presupuestos = json_decode(file_get_contents($presupuestosFile), true);
        
        $presupuestos = array_filter($presupuestos, function($p) use ($id) {
            return $p['id'] !== $id;
        });
        
        file_put_contents($presupuestosFile, json_encode(array_values($presupuestos), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    
    header('Location: index.php?success=eliminado');
    exit;
}

/**
 * Generar PDF
 */
function generarPDF() {
    global $presupuestosFile;
    
    $id = $_GET['id'] ?? '';
    
    if (empty($id)) {
        die('ID de presupuesto no válido');
    }
    
    // Cargar presupuesto
    if (!file_exists($presupuestosFile)) {
        die('No se encontró el archivo de presupuestos');
    }
    
    $presupuestos = json_decode(file_get_contents($presupuestosFile), true);
    $presupuesto = null;
    
    foreach ($presupuestos as $p) {
        if ($p['id'] === $id) {
            $presupuesto = $p;
            break;
        }
    }
    
    if (!$presupuesto) {
        die('Presupuesto no encontrado');
    }
    
    // Verificar si TCPDF está instalado
    if (!file_exists(BASE_PATH . '/tcpdf/tcpdf.php')) {
        die('Error: TCPDF no está instalado. Por favor, instala TCPDF en la carpeta /api/tcpdf/');
    }
    
    require_once(BASE_PATH . '/tcpdf/tcpdf.php');
    
    // Crear PDF
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Configuración del documento
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor(COMPANY_NAME);
    $pdf->SetTitle('Presupuesto ' . $presupuesto['id']);
    $pdf->SetSubject('Presupuesto');
    
    // Eliminar header y footer por defecto
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Márgenes
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(TRUE, 15);
    
    // Añadir página
    $pdf->AddPage();
    
    // Generar contenido HTML
    $html = generarHTMLPresupuesto($presupuesto);
    
    // Escribir HTML
    $pdf->writeHTML($html, true, false, true, false, '');
    
    // Salida del PDF
    $pdf->Output('Presupuesto_' . $presupuesto['id'] . '.pdf', 'D');
    exit;
}

/**
 * Generar HTML del presupuesto
 */
function generarHTMLPresupuesto($presupuesto) {
    $html = '
    <style>
        body { font-family: helvetica, sans-serif; }
        .header { 
            background: linear-gradient(135deg, #E91E8C 0%, #C91E82 100%);
            color: white;
            padding: 30px;
            text-align: center;
            margin-bottom: 30px;
        }
        .header h1 { font-size: 32px; margin: 0; font-weight: 300; letter-spacing: 3px; }
        .header p { margin: 10px 0 0 0; font-size: 14px; }
        
        .section { margin-bottom: 25px; }
        .section-title { 
            color: #E91E8C; 
            font-size: 16px; 
            font-weight: bold; 
            border-bottom: 2px solid #E91E8C; 
            padding-bottom: 8px; 
            margin-bottom: 15px;
        }
        
        .info-row { margin-bottom: 8px; font-size: 12px; }
        .info-label { font-weight: bold; color: #666; }
        .info-value { color: #333; }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .items-table th {
            background-color: #E91E8C;
            color: white;
            padding: 10px;
            text-align: left;
            font-size: 11px;
        }
        .items-table td {
            padding: 10px;
            border-bottom: 1px solid #eee;
            font-size: 11px;
        }
        
        .totals {
            margin-top: 20px;
            text-align: right;
        }
        .totals-row {
            padding: 5px 0;
            font-size: 13px;
        }
        .totals-final {
            font-size: 18px;
            font-weight: bold;
            color: #E91E8C;
            border-top: 3px solid #E91E8C;
            padding-top: 10px;
            margin-top: 10px;
        }
        
        .notes {
            background: #f9f9f9;
            padding: 15px;
            border-left: 4px solid #E91E8C;
            margin-top: 25px;
            font-size: 11px;
        }
        
        .footer {
            text-align: center;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            font-size: 10px;
            color: #999;
        }
    </style>
    
    <!-- Header -->
    <div class="header">
        <h1>PRESUPUESTO</h1>
        <p>Soluciones digitales orientadas a resultados</p>
    </div>
    
    <!-- Información del Presupuesto -->
    <div class="section">
        <div class="section-title">DATOS DE LA PROPUESTA</div>
        <div class="info-row">
            <span class="info-label">ID Presupuesto:</span> 
            <span class="info-value">' . htmlspecialchars($presupuesto['id']) . '</span>
        </div>
        <div class="info-row">
            <span class="info-label">Fecha emisión:</span> 
            <span class="info-value">' . date('d/m/Y', strtotime($presupuesto['fecha_propuesta'])) . '</span>
        </div>
        <div class="info-row">
            <span class="info-label">Válida hasta:</span> 
            <span class="info-value">' . date('d/m/Y', strtotime($presupuesto['valido_hasta'])) . '</span>
        </div>
    </div>
    
    <!-- Información del Cliente -->
    <div class="section">
        <div class="section-title">INFORMACIÓN DEL CLIENTE</div>
        <div class="info-row">
            <span class="info-label">Cliente:</span> 
            <span class="info-value">' . htmlspecialchars($presupuesto['cliente_nombre']) . '</span>
        </div>';
    
    if (!empty($presupuesto['cliente_cif'])) {
        $html .= '<div class="info-row">
            <span class="info-label">CIF/NIF:</span> 
            <span class="info-value">' . htmlspecialchars($presupuesto['cliente_cif']) . '</span>
        </div>';
    }
    
    if (!empty($presupuesto['cliente_direccion'])) {
        $html .= '<div class="info-row">
            <span class="info-label">Dirección:</span> 
            <span class="info-value">' . htmlspecialchars($presupuesto['cliente_direccion']) . '</span>
        </div>';
    }
    
    if (!empty($presupuesto['cliente_ciudad'])) {
        $direccionCompleta = $presupuesto['cliente_ciudad'];
        if (!empty($presupuesto['cliente_cp'])) {
            $direccionCompleta .= ', ' . $presupuesto['cliente_cp'];
        }
        if (!empty($presupuesto['cliente_pais'])) {
            $direccionCompleta .= ' - ' . $presupuesto['cliente_pais'];
        }
        $html .= '<div class="info-row">
            <span class="info-label">Ciudad:</span> 
            <span class="info-value">' . htmlspecialchars($direccionCompleta) . '</span>
        </div>';
    }
    
    if (!empty($presupuesto['cliente_email'])) {
        $html .= '<div class="info-row">
            <span class="info-label">Email:</span> 
            <span class="info-value">' . htmlspecialchars($presupuesto['cliente_email']) . '</span>
        </div>';
    }
    
    if (!empty($presupuesto['cliente_telefono'])) {
        $html .= '<div class="info-row">
            <span class="info-label">Teléfono:</span> 
            <span class="info-value">' . htmlspecialchars($presupuesto['cliente_telefono']) . '</span>
        </div>';
    }
    
    $html .= '</div>';
    
    // Items
    $html .= '
    <div class="section">
        <div class="section-title">DESCRIPCIÓN DE SERVICIOS</div>
        <table class="items-table">
            <thead>
                <tr>
                    <th width="35%">Artículo/Servicio</th>
                    <th width="35%">Descripción</th>
                    <th width="10%" style="text-align: center;">Cantidad</th>
                    <th width="10%" style="text-align: right;">Precio Unit.</th>
                    <th width="10%" style="text-align: right;">Total</th>
                </tr>
            </thead>
            <tbody>';
    
    foreach ($presupuesto['items'] as $item) {
        $total = $item['cantidad'] * $item['precio'];
        $html .= '
                <tr>
                    <td><strong>' . htmlspecialchars($item['nombre']) . '</strong></td>
                    <td>' . htmlspecialchars($item['descripcion']) . '</td>
                    <td style="text-align: center;">' . number_format($item['cantidad'], 2, ',', '.') . '</td>
                    <td style="text-align: right;">' . number_format($item['precio'], 2, ',', '.') . ' €</td>
                    <td style="text-align: right;"><strong>' . number_format($total, 2, ',', '.') . ' €</strong></td>
                </tr>';
    }
    
    $html .= '
            </tbody>
        </table>
    </div>';
    
    // Totales
    $subtotal = $presupuesto['subtotal'];
    $iva = $presupuesto['iva'];
    $segundoImpuesto = $presupuesto['segundo_impuesto'] ?? 0;
    $ivaAmount = ($subtotal * $iva) / 100;
    $segundoImpuestoAmount = ($subtotal * $segundoImpuesto) / 100;
    $total = $presupuesto['total'];
    
    $html .= '
    <div class="totals">
        <div class="totals-row">
            <strong>Subtotal:</strong> ' . number_format($subtotal, 2, ',', '.') . ' €
        </div>
        <div class="totals-row">
            <strong>IVA (' . number_format($iva, 0) . '%):</strong> ' . number_format($ivaAmount, 2, ',', '.') . ' €
        </div>';
    
    if ($segundoImpuesto > 0) {
        $html .= '
        <div class="totals-row">
            <strong>Segundo Impuesto (' . number_format($segundoImpuesto, 0) . '%):</strong> ' . number_format($segundoImpuestoAmount, 2, ',', '.') . ' €
        </div>';
    }
    
    $html .= '
        <div class="totals-final">
            <strong>TOTAL:</strong> ' . number_format($total, 2, ',', '.') . ' €
        </div>
    </div>';
    
    // Notas
    if (!empty($presupuesto['notas'])) {
        $html .= '
    <div class="notes">
        <strong>NOTAS:</strong><br>
        ' . nl2br(htmlspecialchars($presupuesto['notas'])) . '
    </div>';
    }
    
    // Footer
    $html .= '
    <div class="footer">
        <p><strong>' . COMPANY_NAME . '</strong></p>
        <p>Agencia de Marketing Digital</p>
        <p>Este presupuesto fue generado el ' . date('d/m/Y H:i') . '</p>
    </div>';
    
    return $html;
}

/**
 * Enviar email con PDF adjunto
 */
function enviarEmail() {
    global $presupuestosFile;
    
    $id = $_GET['id'] ?? '';
    
    if (empty($id)) {
        header('Location: index.php?error=id_invalido');
        exit;
    }
    
    // Cargar presupuesto
    if (!file_exists($presupuestosFile)) {
        header('Location: index.php?error=no_encontrado');
        exit;
    }
    
    $presupuestos = json_decode(file_get_contents($presupuestosFile), true);
    $presupuesto = null;
    $index = -1;
    
    foreach ($presupuestos as $key => $p) {
        if ($p['id'] === $id) {
            $presupuesto = $p;
            $index = $key;
            break;
        }
    }
    
    if (!$presupuesto) {
        header('Location: index.php?error=no_encontrado');
        exit;
    }
    
    // Generar PDF temporal
    if (!file_exists(BASE_PATH . '/tcpdf/tcpdf.php')) {
        header('Location: index.php?error=tcpdf_no_instalado');
        exit;
    }
    
    require_once(BASE_PATH . '/tcpdf/tcpdf.php');
    
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor(COMPANY_NAME);
    $pdf->SetTitle('Presupuesto ' . $presupuesto['id']);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(TRUE, 15);
    $pdf->AddPage();
    
    $html = generarHTMLPresupuesto($presupuesto);
    $pdf->writeHTML($html, true, false, true, false, '');
    
    // Guardar PDF temporal
    $tmpFile = sys_get_temp_dir() . '/presupuesto_' . $id . '.pdf';
    $pdf->Output($tmpFile, 'F');
    
    // Preparar email
    $to = $presupuesto['cliente_email'];
    $subject = 'Presupuesto ' . $presupuesto['id'] . ' - ' . COMPANY_NAME;
    
    $message = '
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .header { background: linear-gradient(135deg, #E91E8C 0%, #C91E82 100%); color: white; padding: 30px; text-align: center; }
            .content { padding: 30px; }
            .footer { background: #f5f5f5; padding: 20px; text-align: center; font-size: 12px; color: #666; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>Presupuesto ' . htmlspecialchars($presupuesto['id']) . '</h1>
        </div>
        <div class="content">
            <p>Estimado/a <strong>' . htmlspecialchars($presupuesto['cliente_nombre']) . '</strong>,</p>
            
            <p>Adjunto encontrará el presupuesto solicitado con el detalle de los servicios propuestos.</p>
            
            <p><strong>Resumen del presupuesto:</strong></p>
            <ul>
                <li>ID: ' . htmlspecialchars($presupuesto['id']) . '</li>
                <li>Fecha: ' . date('d/m/Y', strtotime($presupuesto['fecha_propuesta'])) . '</li>
                <li>Válido hasta: ' . date('d/m/Y', strtotime($presupuesto['valido_hasta'])) . '</li>
                <li>Total: ' . number_format($presupuesto['total'], 2, ',', '.') . ' €</li>
            </ul>
            
            <p>Quedamos a su disposición para cualquier consulta o aclaración que pueda necesitar.</p>
            
            <p>Atentamente,<br>
            <strong>' . COMPANY_NAME . '</strong></p>
        </div>
        <div class="footer">
            <p>' . COMPANY_NAME . ' - Agencia de Marketing Digital</p>
            <p>Este email fue generado automáticamente. Por favor, no responda a este mensaje.</p>
        </div>
    </body>
    </html>
    ';
    
    // Headers del email
    $headers = "From: " . COMPANY_NAME . " <noreply@" . $_SERVER['HTTP_HOST'] . ">\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    
    // Boundary para adjuntos
    $boundary = md5(time());
    $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";
    
    // Cuerpo del mensaje
    $body = "--{$boundary}\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $body .= $message . "\r\n";
    
    // Adjuntar PDF
    $pdfContent = file_get_contents($tmpFile);
    $pdfEncoded = chunk_split(base64_encode($pdfContent));
    
    $body .= "--{$boundary}\r\n";
    $body .= "Content-Type: application/pdf; name=\"Presupuesto_{$id}.pdf\"\r\n";
    $body .= "Content-Transfer-Encoding: base64\r\n";
    $body .= "Content-Disposition: attachment; filename=\"Presupuesto_{$id}.pdf\"\r\n\r\n";
    $body .= $pdfEncoded . "\r\n";
    $body .= "--{$boundary}--";
    
    // Enviar email
    $enviado = mail($to, $subject, $body, $headers);
    
    // Eliminar archivo temporal
    unlink($tmpFile);
    
    if ($enviado) {
        // Actualizar estado a "enviado"
        $presupuestos[$index]['estado'] = 'enviado';
        $presupuestos[$index]['fecha_envio'] = date('Y-m-d H:i:s');
        file_put_contents($presupuestosFile, json_encode($presupuestos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        // Registrar en auditoría
        guardarAuditoria('presupuesto_enviado', 'enviado', 'Presupuesto enviado por email: ' . $id, [
            'cliente_id' => $presupuesto['cliente_id'],
            'cliente_nombre' => $presupuesto['cliente_nombre'],
            'email' => $presupuesto['cliente_email']
        ]);
        
        header('Location: index.php?success=email_enviado');
    } else {
        header('Location: index.php?error=email_no_enviado');
    }
    
    exit;
}