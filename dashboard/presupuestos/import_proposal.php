<?php
/**
 * Importar Propuesta del CRM al Dashboard
 * 
 * Lee una propuesta existente del CRM y la convierte en un presupuesto
 * del dashboard para poder editarla y generar PDFs.
 */

require_once '../config.php';

header('Content-Type: application/json');

$proposal_id = isset($_GET['proposal_id']) ? intval($_GET['proposal_id']) : 0;

if ($proposal_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de propuesta inválido']);
    exit;
}

// Conectar a la BBDD del CRM
$mysqli = conexionBBDD();
if (!$mysqli) {
    echo json_encode(['success' => false, 'message' => 'Error de conexión a BBDD']);
    exit;
}

// Obtener datos de la propuesta
$sql = "SELECT * FROM crm_proposals WHERE id = $proposal_id AND deleted = 0";
$result = $mysqli->query($sql);

if (!$result || $result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Propuesta no encontrada en el CRM']);
    exit;
}

$proposal = $result->fetch_assoc();
$result->free();

// Obtener cliente
$client_id = intval($proposal['client_id']);
$sqlClient = "SELECT * FROM crm_clients WHERE id = $client_id";
$resultClient = $mysqli->query($sqlClient);
$client = $resultClient ? $resultClient->fetch_assoc() : null;
if ($resultClient) $resultClient->free();

// Obtener contacto principal para el email
$email = '';
$sqlContact = "SELECT email FROM crm_users 
               WHERE client_id = $client_id 
               AND deleted = 0 
               AND user_type = 'client'
               AND is_primary_contact = 1
               LIMIT 1";
$resultContact = $mysqli->query($sqlContact);
if ($resultContact && $resultContact->num_rows > 0) {
    $contact = $resultContact->fetch_assoc();
    $email = $contact['email'];
    $resultContact->free();
}

// Si no hay contacto principal, buscar el primero disponible
if (empty($email)) {
    $sqlContact = "SELECT email FROM crm_users 
                   WHERE client_id = $client_id 
                   AND deleted = 0 
                   AND user_type = 'client'
                   LIMIT 1";
    $resultContact = $mysqli->query($sqlContact);
    if ($resultContact && $resultContact->num_rows > 0) {
        $contact = $resultContact->fetch_assoc();
        $email = $contact['email'];
        $resultContact->free();
    }
}

// Obtener items de la propuesta
$items = [];
$sqlItems = "SELECT * FROM crm_proposal_items 
             WHERE proposal_id = $proposal_id 
             AND deleted = 0 
             ORDER BY sort ASC";
$resultItems = $mysqli->query($sqlItems);
if ($resultItems) {
    while ($row = $resultItems->fetch_assoc()) {
        $items[] = [
            'nombre' => $row['title'],
            'descripcion' => $row['description'] ?? '',
            'cantidad' => floatval($row['quantity']),
            'precio' => floatval($row['rate']),
            'unidad' => $row['unit_type'] ?? ''
        ];
    }
    $resultItems->free();
}

// Calcular totales
$subtotal = 0;
foreach ($items as $item) {
    $subtotal += $item['cantidad'] * $item['precio'];
}

// Obtener IVA (tax_id) del CRM
$tax_id = intval($proposal['tax_id'] ?? 1);
$tax_percentage = 21; // Por defecto 21%

if ($tax_id > 0) {
    $sqlTax = "SELECT percentage FROM crm_taxes WHERE id = $tax_id AND deleted = 0";
    $resultTax = $mysqli->query($sqlTax);
    if ($resultTax && $resultTax->num_rows > 0) {
        $tax = $resultTax->fetch_assoc();
        $tax_percentage = floatval($tax['percentage']);
        $resultTax->free();
    }
}

$iva_amount = ($subtotal * $tax_percentage) / 100;
$total = $subtotal + $iva_amount;

// Generar ID local para el presupuesto
$local_id = 'PRES-' . date('Ymd') . '-' . str_pad($proposal_id, 4, '0', STR_PAD_LEFT);

// Crear presupuesto en formato del dashboard
$presupuesto = [
    'id' => $local_id,
    'fecha_propuesta' => $proposal['proposal_date'] ?? date('Y-m-d'),
    'valido_hasta' => $proposal['valid_until'] ?? date('Y-m-d', strtotime('+30 days')),
    'cliente_id' => $client_id,
    'cliente_nombre' => $client ? $client['company_name'] : 'Cliente #' . $client_id,
    'cliente_email' => $email,
    'cliente_telefono' => $client ? ($client['phone'] ?? '') : '',
    'cliente_direccion' => $client ? ($client['address'] ?? '') : '',
    'cliente_ciudad' => $client ? ($client['city'] ?? '') : '',
    'cliente_cp' => $client ? ($client['zip'] ?? '') : '',
    'cliente_pais' => $client ? ($client['country'] ?? '') : '',
    'cliente_cif' => $client ? ($client['vat_number'] ?? '') : '',
    'items' => $items,
    'iva' => $tax_percentage,
    'segundo_impuesto' => 0,
    'subtotal' => $subtotal,
    'total' => $total,
    'notas' => strip_tags($proposal['note'] ?? ''),
    'estado' => 'importado',
    'fecha_creacion' => date('Y-m-d H:i:s'),
    'fecha_modificacion' => date('Y-m-d H:i:s'),
    'crm_proposal_id' => $proposal_id
];

// Guardar en presupuestos.json
$presupuestosFile = DATA_PATH . '/presupuestos.json';
$presupuestos = [];

if (file_exists($presupuestosFile)) {
    $presupuestos = json_decode(file_get_contents($presupuestosFile), true);
    if (!is_array($presupuestos)) $presupuestos = [];
}

// Verificar si ya existe
$existe = false;
foreach ($presupuestos as $key => $p) {
    if (isset($p['crm_proposal_id']) && $p['crm_proposal_id'] == $proposal_id) {
        // Ya existe, actualizar
        $presupuesto['fecha_creacion'] = $p['fecha_creacion'];
        $presupuestos[$key] = $presupuesto;
        $existe = true;
        break;
    }
}

if (!$existe) {
    $presupuestos[] = $presupuesto;
}

file_put_contents($presupuestosFile, json_encode($presupuestos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// Log de auditoría
guardarAuditoria(
    'propuesta_importada',
    'exitoso',
    'Propuesta #' . $proposal_id . ' importada del CRM como ' . $local_id,
    [
        'cliente_id' => $client_id,
        'cliente_nombre' => $presupuesto['cliente_nombre'],
        'email' => $email
    ]
);

echo json_encode([
    'success' => true,
    'local_id' => $local_id,
    'message' => 'Propuesta importada correctamente'
]);
?>