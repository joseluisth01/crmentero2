<?php
require_once '../config.php';
header('Content-Type: text/plain; charset=utf-8');

$contratosFile = DATA_PATH . '/contratos.json';
$id = isset($_GET['id']) ? $_GET['id'] : 'CONT-20260217-0001';

if (file_exists($contratosFile)) {
    $contratos = json_decode(file_get_contents($contratosFile), true);
    foreach ($contratos as $c) {
        if ($c['id'] === $id) {
            echo "=== CONTRATO {$id} ===\n";
            echo "Tiene clausulas_html: " . (empty($c['clausulas_html']) ? 'NO' : 'SI') . "\n\n";
            if (!empty($c['clausulas_html'])) {
                $html = $c['clausulas_html'];
                echo "Longitud: " . strlen($html) . " chars\n\n";
                echo "Primeros 500 chars:\n";
                echo substr($html, 0, 500) . "\n\n";
                echo "Últimos 300 chars:\n";
                echo substr($html, -300);
            }
            break;
        }
    }
}