<?php
/**
 * API - Obtener informaci¨®n del contacto de un cliente
 * Responde en formato JSON
 */

require_once '../config.php';

header('Content-Type: application/json');

// Obtener ID del cliente
$clientId = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;

if ($clientId <= 0) {
    mostrarError('ID de cliente inv¨¢lido');
}

// Llamar a la API del CRM
$contactos = callCrmApi('contact_by_clientid/' . $clientId);

if ($contactos === false) {
    echo json_encode(array());
} else {
    echo json_encode($contactos);
}
