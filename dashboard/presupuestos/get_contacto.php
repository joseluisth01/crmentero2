<?php
/**
 * Obtener contactos de un cliente desde el CRM
 * UBICACIÓN: /dashboard/presupuestos/get_contacto.php
 */

require_once '../config.php';

header('Content-Type: application/json');

$clientId = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;

if ($clientId <= 0) {
    echo json_encode([]);
    exit;
}

// Obtener contactos del cliente desde la BBDD del CRM
$mysqli = conexionBBDD();
if (!$mysqli) {
    echo json_encode([]);
    exit;
}

$sql = "SELECT id, first_name, last_name, job_title, email, phone, alternative_phone, is_primary_contact
        FROM crm_users
        WHERE client_id = $clientId AND deleted = 0 AND user_type = 'client'
        ORDER BY is_primary_contact DESC, id ASC";

$result = $mysqli->query($sql);

$contactos = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $contactos[] = $row;
    }
    $result->free();
}

echo json_encode($contactos);
?>