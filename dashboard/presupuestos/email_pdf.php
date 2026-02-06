<?php
/**
 * Generar PDF para envío desde CRM
 * Este archivo genera el PDF de Tictac cuando se envía una propuesta desde el CRM
 * 
 * URL de uso: /dashboard/presupuestos/email_pdf.php?proposal_id=38
 */

require_once '../config.php';

$proposal_id = $_GET['proposal_id'] ?? '';

if (empty($proposal_id)) {
    http_response_code(404);
    die('ID de propuesta no válido');
}

// Buscar presupuesto por crm_proposal_id
$presupuestosFile = DATA_PATH . '/presupuestos.json';
$presupuesto_id = null;
$presupuesto = null;

if (file_exists($presupuestosFile)) {
    $presupuestos = json_decode(file_get_contents($presupuestosFile), true);
    
    if (is_array($presupuestos)) {
        foreach ($presupuestos as $p) {
            if (isset($p['crm_proposal_id']) && $p['crm_proposal_id'] == $proposal_id) {
                $presupuesto_id = $p['id'];
                $presupuesto = $p;
                break;
            }
        }
    }
}

if (!$presupuesto_id) {
    http_response_code(404);
    die('Presupuesto no encontrado en dashboard. Esta propuesta debe crearse desde el dashboard para usar el PDF de Tictac.');
}

// Log de auditoría
guardarAuditoria(
    'pdf_generado_email',
    'exitoso',
    'PDF generado para envío de email desde CRM - Proposal ID: ' . $proposal_id . ' → Presupuesto: ' . $presupuesto_id,
    [
        'crm_proposal_id' => $proposal_id,
        'presupuesto_id' => $presupuesto_id,
        'cliente_nombre' => $presupuesto['cliente_nombre'] ?? ''
    ]
);

// Establecer parámetros para la generación de PDF
$_GET['id'] = $presupuesto_id;
$_GET['action'] = 'pdf';

// Incluir y ejecutar la generación de PDF
require_once 'api.php';
?>