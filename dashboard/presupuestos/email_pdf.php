<?php
/**
 * Generar PDF personalizado de Tictac para el CRM
 * REDIRIGE A LA FUNCIÓN REAL DE GENERACIÓN DE PDF
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Obtener proposal_id del CRM
$crm_proposal_id = isset($_GET['proposal_id']) ? intval($_GET['proposal_id']) : 0;

if ($crm_proposal_id <= 0) {
    error_log("❌ email_pdf.php: ID inválido");
    http_response_code(400);
    die('Error: ID de propuesta inválido');
}

error_log("📥 email_pdf.php: Solicitud para CRM proposal_id = $crm_proposal_id");

// Buscar el ID local del presupuesto
require_once '../config.php';

$presupuestosFile = DATA_PATH . '/presupuestos.json';
if (!file_exists($presupuestosFile)) {
    error_log("❌ email_pdf.php: presupuestos.json no existe");
    http_response_code(404);
    die('Error: No se encontró el archivo de presupuestos');
}

$presupuestos = json_decode(file_get_contents($presupuestosFile), true);
if (!is_array($presupuestos)) {
    error_log("❌ email_pdf.php: presupuestos.json no es array");
    http_response_code(500);
    die('Error: Formato inválido');
}

// Buscar presupuesto por crm_proposal_id
$local_id = null;
foreach ($presupuestos as $p) {
    if (isset($p['crm_proposal_id']) && $p['crm_proposal_id'] == $crm_proposal_id) {
        $local_id = $p['id'];
        error_log("✅ email_pdf.php: Encontrado local_id = $local_id para CRM #$crm_proposal_id");
        break;
    }
}

// Si no existe, intentar importar desde el CRM
if (!$local_id) {
    error_log("⚠️ email_pdf.php: No encontrado localmente, importando CRM #$crm_proposal_id...");
    
    // Llamar al importador
    $import_url = 'https://gestion-tictac-comunicacion.es/dashboard/presupuestos/import_proposal.php?proposal_id=' . $crm_proposal_id;
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'ignore_errors' => true
        ]
    ]);
    
    $import_response = @file_get_contents($import_url, false, $context);
    
    if ($import_response) {
        $import_data = json_decode($import_response, true);
        if (isset($import_data['success']) && $import_data['success'] && isset($import_data['local_id'])) {
            $local_id = $import_data['local_id'];
            error_log("✅ email_pdf.php: Importado exitosamente, local_id = $local_id");
        } else {
            error_log("❌ email_pdf.php: Error importando - " . ($import_data['message'] ?? 'desconocido'));
        }
    } else {
        error_log("❌ email_pdf.php: No se pudo conectar al importador");
    }
}

// Si aún no tenemos local_id, error
if (!$local_id) {
    error_log("❌ email_pdf.php: CRÍTICO - No se pudo obtener local_id para CRM #$crm_proposal_id");
    http_response_code(404);
    die('Error: Propuesta no encontrada');
}

// ============================================
// 🎨 REDIRIGIR A LA FUNCIÓN REAL DE PDF
// ============================================

error_log("🔀 email_pdf.php: Redirigiendo a api.php con local_id = $local_id");

// Cargar la función de generación de PDF
$_GET['action'] = 'pdf';
$_GET['id'] = $local_id;
$_GET['mode'] = 'save'; // Modo save para devolver el contenido

// Incluir api.php para ejecutar la función generarPDF()
require_once __DIR__ . '/api.php';

// api.php ya maneja la salida del PDF, no hace falta nada más
exit;
?>