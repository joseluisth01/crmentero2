<?php
/**
 * API Vencimientos - Sistema Tictac
 * Devuelve proyectos Y contratos con vencimiento próximo
 */
require_once 'config.php';
header('Content-Type: application/json; charset=utf-8');

$mysqli = conexionBBDD();
if (!$mysqli) {
    echo json_encode(['error' => 'Sin conexión a BBDD']);
    exit;
}

$hoy = date('Y-m-d');

// ── Proyectos ────────────────────────────────────────────────
$sqlProyectos = "SELECT 
        p.id,
        p.title,
        p.deadline        AS fecha_fin,
        c.company_name,
        DATEDIFF(p.deadline, CURDATE()) AS dias_restantes,
        'proyecto'        AS tipo
    FROM crm_projects p
    LEFT JOIN crm_clients c ON c.id = p.client_id
    WHERE p.deleted = 0
      AND p.status = 'open'
      AND p.deadline IS NOT NULL
      AND p.deadline >= '$hoy'
    ORDER BY p.deadline ASC
    LIMIT 50";

// ── Contratos ────────────────────────────────────────────────
$sqlContratos = "SELECT 
        ct.id,
        ct.title,
        ct.valid_until    AS fecha_fin,
        c.company_name,
        DATEDIFF(ct.valid_until, CURDATE()) AS dias_restantes,
        'contrato'        AS tipo
    FROM crm_contracts ct
    LEFT JOIN crm_clients c ON c.id = ct.client_id
    WHERE ct.deleted = 0
      AND ct.status != 'declined'
      AND ct.valid_until IS NOT NULL
      AND ct.valid_until >= '$hoy'
    ORDER BY ct.valid_until ASC
    LIMIT 50";

$proyectos = [];
$contratos = [];

$r1 = $mysqli->query($sqlProyectos);
if ($r1) {
    while ($row = $r1->fetch_assoc()) $proyectos[] = $row;
    $r1->free();
}

$r2 = $mysqli->query($sqlContratos);
if ($r2) {
    while ($row = $r2->fetch_assoc()) $contratos[] = $row;
    $r2->free();
}

echo json_encode([
    'proyectos' => $proyectos,
    'contratos' => $contratos,
], JSON_UNESCAPED_UNICODE);