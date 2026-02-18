<?php
/**
 * debug_items.php — Ver qué items llegan al POST y qué hay en contratos.json
 * Abre: /dashboard/contratos/debug_items.php?id=CONT-20260217-0001
 * BORRAR después
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once '../config.php';

header('Content-Type: text/plain; charset=utf-8');

// 1. Ver el contrato guardado
$contratosFile = DATA_PATH . '/contratos.json';
$id = isset($_GET['id']) ? $_GET['id'] : '';
echo "=== CONTRATO EN JSON ===\n";
if (file_exists($contratosFile)) {
    $contratos = json_decode(file_get_contents($contratosFile), true);
    foreach ($contratos as $c) {
        if (!$id || $c['id'] === $id) {
            echo "ID: " . $c['id'] . "\n";
            echo "Items count: " . count($c['items'] ?? []) . "\n";
            foreach (($c['items'] ?? []) as $i => $item) {
                echo "  Item $i: nombre=" . ($item['nombre'] ?? 'VACIO') 
                   . " | cant=" . ($item['cantidad'] ?? 0) 
                   . " | precio=" . ($item['precio'] ?? 0) . "\n";
                if (!empty($item['descripcion'])) {
                    echo "    desc=" . substr($item['descripcion'], 0, 80) . "\n";
                }
            }
            echo "  subtotal=" . ($c['subtotal'] ?? 0) . "\n";
            echo "  total=" . ($c['total'] ?? 0) . "\n";
            echo "  iva=" . ($c['iva'] ?? 0) . "\n";
            echo "\n";
        }
    }
} else {
    echo "contratos.json NO existe\n";
}

// 2. Si es POST, mostrar los items recibidos
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "\n=== POST RECIBIDO ===\n";
    echo "action: " . ($_POST['action'] ?? 'N/A') . "\n";
    echo "items en POST: " . (isset($_POST['items']) ? 'SI' : 'NO') . "\n";
    if (isset($_POST['items'])) {
        echo "Tipo: " . gettype($_POST['items']) . "\n";
        echo "Count: " . count($_POST['items']) . "\n";
        foreach ($_POST['items'] as $idx => $item) {
            echo "  [$idx] nombre=" . ($item['nombre'] ?? 'VACIO') 
               . " | empty=" . (empty($item['nombre']) ? 'SI' : 'NO') . "\n";
        }
    }
    // Mostrar todos los keys del POST
    echo "\nTodos los keys POST:\n";
    foreach ($_POST as $k => $v) {
        if (!is_array($v)) echo "  $k = " . substr($v, 0, 50) . "\n";
    }
    foreach ($_POST as $k => $v) {
        if (is_array($v)) echo "  $k = [ARRAY con " . count($v) . " elementos]\n";
    }
}