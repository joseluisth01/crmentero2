<?php
/**
 * Generar PDF personalizado de Tictac para el CRM
 * 
 * UBICACIÓN: /dashboard/presupuestos/email_pdf.php
 * 
 * USO: El CRM llama a este endpoint para obtener el PDF personalizado
 * PARÁMETRO: proposal_id (ID de la propuesta en el CRM)
 */

require_once '../config.php';

// Validar parámetro
$proposal_id = isset($_GET['proposal_id']) ? intval($_GET['proposal_id']) : 0;

if ($proposal_id <= 0) {
    http_response_code(400);
    die('Error: ID de propuesta inválido');
}

// Buscar presupuesto en el dashboard
$presupuestosFile = '../data/presupuestos.json';
if (!file_exists($presupuestosFile)) {
    http_response_code(404);
    die('Error: No se encontró el archivo de presupuestos');
}

$presupuestos = json_decode(file_get_contents($presupuestosFile), true);
if (!is_array($presupuestos)) {
    http_response_code(500);
    die('Error: No se pudo leer presupuestos');
}

// Buscar presupuesto por crm_proposal_id
$presupuesto = null;
foreach ($presupuestos as $p) {
    if (isset($p['crm_proposal_id']) && $p['crm_proposal_id'] == $proposal_id) {
        $presupuesto = $p;
        break;
    }
}

// Si no existe, sincronizar desde CRM
if (!$presupuesto) {
    error_log("⚠️ Presupuesto CRM #$proposal_id no encontrado en dashboard, sincronizando...");
    
    $mysqli = conexionBBDD();
    if (!$mysqli) {
        http_response_code(500);
        die('Error: No se pudo conectar a la base de datos');
    }
    
    // Obtener datos de la propuesta
    $sql = "SELECT p.*, c.company_name, c.address, c.city, c.zip, c.country, c.vat_number,
                   u.email as contact_email, u.phone as contact_phone
            FROM crm_proposals p
            LEFT JOIN crm_clients c ON p.client_id = c.id
            LEFT JOIN crm_users u ON c.id = u.client_id AND u.is_primary_contact = '1' AND u.deleted = 0
            WHERE p.id = $proposal_id AND p.deleted = 0";
    
    $result = $mysqli->query($sql);
    if (!$result || $result->num_rows === 0) {
        http_response_code(404);
        die('Error: Propuesta no encontrada en CRM');
    }
    
    $propuesta_crm = $result->fetch_assoc();
    
    // Crear presupuesto temporal
    $presupuesto = [
        'id' => 'TEMP-' . $proposal_id,
        'crm_proposal_id' => $proposal_id,
        'cliente_nombre' => $propuesta_crm['company_name'] ?? '',
        'cliente_email' => $propuesta_crm['contact_email'] ?? '',
        'cliente_telefono' => $propuesta_crm['contact_phone'] ?? '',
        'cliente_direccion' => $propuesta_crm['address'] ?? '',
        'cliente_ciudad' => $propuesta_crm['city'] ?? '',
        'cliente_cp' => $propuesta_crm['zip'] ?? '',
        'cliente_pais' => $propuesta_crm['country'] ?? '',
        'cliente_cif' => $propuesta_crm['vat_number'] ?? '',
        'fecha_propuesta' => $propuesta_crm['proposal_date'] ?? date('Y-m-d'),
        'valido_hasta' => $propuesta_crm['valid_until'] ?? '',
        'subtotal' => floatval($propuesta_crm['subtotal'] ?? 0),
        'iva_porcentaje' => 21,
        'iva_total' => floatval($propuesta_crm['tax'] ?? 0),
        'total' => floatval($propuesta_crm['total'] ?? 0),
        'items' => []
    ];
    
    // Obtener items
    $sql_items = "SELECT * FROM crm_proposal_items WHERE proposal_id = $proposal_id AND deleted = 0 ORDER BY sort ASC";
    $result_items = $mysqli->query($sql_items);
    
    if ($result_items && $result_items->num_rows > 0) {
        while ($item = $result_items->fetch_assoc()) {
            $presupuesto['items'][] = [
                'nombre' => $item['title'] ?? '',
                'descripcion' => $item['description'] ?? '',
                'cantidad' => floatval($item['quantity'] ?? 1),
                'precio' => floatval($item['rate'] ?? 0),
                'subtotal' => floatval($item['total'] ?? 0)
            ];
        }
    }
}

// Generar PDF con TCPDF (usar la instalación del dashboard)
require_once __DIR__ . '/../tcpdf/tcpdf.php';

try {
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Configuración
    $pdf->SetCreator('Tictac Comunicación Digital SL');
    $pdf->SetAuthor('Tictac Comunicación');
    $pdf->SetTitle('Presupuesto - ' . ($presupuesto['cliente_nombre'] ?? ''));
    
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(true, 15);
    
    $pdf->AddPage();
    
    // Header rosa
    $pdf->SetFillColor(233, 30, 140);
    $pdf->Rect(0, 0, 210, 8, 'F');
    
    // Logo (si existe)
    $logoPath = __DIR__ . '/../assets/logo-tictac.png';
    if (file_exists($logoPath)) {
        $pdf->Image($logoPath, 15, 15, 50, '', 'PNG');
    }
    
    $pdf->Ln(20);
    
    // Título
    $pdf->SetFont('helvetica', 'B', 24);
    $pdf->SetTextColor(233, 30, 140);
    $pdf->Cell(0, 10, 'PRESUPUESTO', 0, 1, 'R');
    
    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetTextColor(100, 100, 100);
    
    // Número
    $numero = 'PRES-' . str_pad($proposal_id, 4, '0', STR_PAD_LEFT);
    $pdf->Cell(0, 5, 'Nº ' . $numero, 0, 1, 'R');
    
    // Fecha
    $fecha = !empty($presupuesto['fecha_propuesta']) 
        ? date('d/m/Y', strtotime($presupuesto['fecha_propuesta']))
        : date('d/m/Y');
    $pdf->Cell(0, 5, 'Fecha: ' . $fecha, 0, 1, 'R');
    
    $pdf->Ln(5);
    
    // Cliente
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 7, 'CLIENTE', 0, 1, 'L');
    
    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetTextColor(60, 60, 60);
    
    if (!empty($presupuesto['cliente_nombre'])) {
        $pdf->Cell(0, 5, $presupuesto['cliente_nombre'], 0, 1, 'L');
    }
    if (!empty($presupuesto['cliente_direccion'])) {
        $pdf->Cell(0, 5, $presupuesto['cliente_direccion'], 0, 1, 'L');
    }
    if (!empty($presupuesto['cliente_ciudad']) || !empty($presupuesto['cliente_cp'])) {
        $pdf->Cell(0, 5, ($presupuesto['cliente_cp'] ?? '') . ' ' . ($presupuesto['cliente_ciudad'] ?? ''), 0, 1, 'L');
    }
    if (!empty($presupuesto['cliente_cif'])) {
        $pdf->Cell(0, 5, 'CIF/NIF: ' . $presupuesto['cliente_cif'], 0, 1, 'L');
    }
    
    $pdf->Ln(8);
    
    // Tabla
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFillColor(233, 30, 140);
    
    $pdf->Cell(100, 7, 'DESCRIPCIÓN', 1, 0, 'L', true);
    $pdf->Cell(20, 7, 'CANT.', 1, 0, 'C', true);
    $pdf->Cell(30, 7, 'PRECIO', 1, 0, 'R', true);
    $pdf->Cell(30, 7, 'TOTAL', 1, 1, 'R', true);
    
    // Items
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor(0, 0, 0);
    
    if (!empty($presupuesto['items']) && is_array($presupuesto['items'])) {
        foreach ($presupuesto['items'] as $item) {
            $nombre = $item['nombre'] ?? '';
            $descripcion = $item['descripcion'] ?? '';
            
            // Si hay descripción, mostrarla
            if (!empty($descripcion)) {
                $nombre .= "\n" . $descripcion;
            }
            
            $pdf->Cell(100, 6, $nombre, 1, 0, 'L');
            $pdf->Cell(20, 6, $item['cantidad'] ?? 1, 1, 0, 'C');
            $pdf->Cell(30, 6, number_format(floatval($item['precio'] ?? 0), 2, ',', '.') . ' €', 1, 0, 'R');
            $pdf->Cell(30, 6, number_format(floatval($item['subtotal'] ?? 0), 2, ',', '.') . ' €', 1, 1, 'R');
        }
    }
    
    $pdf->Ln(3);
    
    // Totales
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(150, 6, 'Subtotal:', 0, 0, 'R');
    $pdf->Cell(30, 6, number_format(floatval($presupuesto['subtotal'] ?? 0), 2, ',', '.') . ' €', 0, 1, 'R');
    
    $pdf->Cell(150, 6, 'IVA (21%):', 0, 0, 'R');
    $pdf->Cell(30, 6, number_format(floatval($presupuesto['iva_total'] ?? 0), 2, ',', '.') . ' €', 0, 1, 'R');
    
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->SetTextColor(233, 30, 140);
    $pdf->Cell(150, 8, 'TOTAL:', 0, 0, 'R');
    $pdf->Cell(30, 8, number_format(floatval($presupuesto['total'] ?? 0), 2, ',', '.') . ' €', 0, 1, 'R');
    
    // Footer
    $pdf->SetY(-30);
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->MultiCell(0, 4, 
        "Tictac Comunicación Digital SL\n" .
        "Plaza de los Carrillos, 5 · 14001 Córdoba\n" .
        "Tel: 957 048 147 · Email: hola@tictac-comunicacion.es · www.tictac-comunicacion.es",
        0, 'C'
    );
    
    // Output
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="Presupuesto_' . $proposal_id . '.pdf"');
    echo $pdf->Output('', 'S');
    
} catch (Exception $e) {
    error_log("❌ Error generando PDF: " . $e->getMessage());
    http_response_code(500);
    die('Error al generar PDF: ' . $e->getMessage());
}