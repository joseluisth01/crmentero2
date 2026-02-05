<?php
/**
 * Sincronizar presupuestos existentes con el CRM
 * Ejecutar una sola vez: https://gestion-tictac-comunicacion.es/api/presupuestos/sync_old.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(300);

require_once '../config.php';

echo "<h1>🔄 Sincronización de Presupuestos con CRM</h1>";
echo "<style>body{font-family:Arial;padding:20px;} .success{color:green;} .error{color:red;} .info{color:blue;} pre{background:#f5f5f5;padding:15px;border-radius:5px;overflow:auto;}</style>";

$presupuestosFile = DATA_PATH . '/presupuestos.json';

if (!file_exists($presupuestosFile)) {
    echo "<p class='error'>❌ No se encontró el archivo presupuestos.json</p>";
    exit;
}

$presupuestos = json_decode(file_get_contents($presupuestosFile), true);

if (!is_array($presupuestos)) {
    echo "<p class='error'>❌ Error al leer presupuestos.json</p>";
    exit;
}

echo "<p class='info'>Total de presupuestos encontrados: " . count($presupuestos) . "</p>";

$mysqli = conexionBBDD();
if (!$mysqli) {
    echo "<p class='error'>❌ Error de conexión a BBDD</p>";
    exit;
}

$sincronizados = 0;
$errores = 0;
$yaSincronizados = 0;

foreach ($presupuestos as $key => $presupuesto) {
    $id = $presupuesto['id'] ?? 'SIN_ID';
    
    echo "<hr><h3>Procesando: $id</h3>";
    
    // Ya está sincronizado?
    if (!empty($presupuesto['crm_estimate_id'])) {
        echo "<p class='info'>⚠️ Ya está sincronizado (CRM ID: " . $presupuesto['crm_estimate_id'] . ")</p>";
        $yaSincronizados++;
        continue;
    }
    
    // Tiene cliente_id?
    if (empty($presupuesto['cliente_id']) || $presupuesto['cliente_id'] === '') {
        echo "<p class='error'>❌ No tiene cliente_id, no se puede sincronizar</p>";
        $errores++;
        continue;
    }
    
    $clientId = intval($presupuesto['cliente_id']);
    echo "<p class='info'>Cliente ID: $clientId</p>";
    
    // Preparar datos
    $estimateDate = $presupuesto['fecha_propuesta'] ?? date('Y-m-d');
    $validUntil = $presupuesto['valido_hasta'] ?? date('Y-m-d', strtotime('+30 days'));
    $note = $mysqli->real_escape_string($presupuesto['notas'] ?? '');
    
    // CONSULTA SQL EXACTA QUE FUNCIONA (probada)
    $sql = "INSERT INTO crm_estimates 
            (client_id, estimate_request_id, estimate_date, valid_until, note, 
             status, tax_id, tax_id2, 
             discount_type, discount_amount, discount_amount_type,
             project_id, accepted_by, meta_data, created_by, signature, public_key, company_id, deleted) 
            VALUES 
            ($clientId, 0, '$estimateDate', '$validUntil', '$note',
             'draft', 1, 0,
             'before_tax', 0, 'percentage',
             0, 0, '', 1, '', '', 0, 0)";
    
    if (!$mysqli->query($sql)) {
        echo "<p class='error'>❌ Error al insertar: " . $mysqli->error . "</p>";
        echo "<p class='error'>SQL: " . htmlspecialchars($sql) . "</p>";
        $errores++;
        continue;
    }
    
    $estimateId = $mysqli->insert_id;
    echo "<p class='success'>✅ Estimate creado con ID: $estimateId</p>";
    
    // Insertar items
    if (isset($presupuesto['items']) && is_array($presupuesto['items'])) {
        $itemsInsertados = 0;
        $sort = 0;
        
        foreach ($presupuesto['items'] as $item) {
            $title = $mysqli->real_escape_string($item['nombre'] ?? '');
            $description = $mysqli->real_escape_string($item['descripcion'] ?? '');
            $quantity = floatval($item['cantidad'] ?? 1);
            $unitType = $mysqli->real_escape_string($item['unidad'] ?? '');
            $rate = floatval($item['precio'] ?? 0);
            $itemTotal = $quantity * $rate;

            // CONSULTA SQL EXACTA QUE FUNCIONA (probada)
            $itemSql = "INSERT INTO crm_estimate_items 
                        (estimate_id, title, description, quantity, unit_type, rate, total, sort, item_id, deleted) 
                        VALUES 
                        ($estimateId, '$title', '$description', $quantity, '$unitType', $rate, $itemTotal, $sort, 0, 0)";

            if ($mysqli->query($itemSql)) {
                $itemsInsertados++;
            } else {
                echo "<p class='error'>⚠️ Error insertando item: " . $mysqli->error . "</p>";
            }
            
            $sort++;
        }
        echo "<p class='info'>Items insertados: $itemsInsertados</p>";
    }
    
    // Actualizar el JSON con el crm_estimate_id
    $presupuestos[$key]['crm_estimate_id'] = $estimateId;
    $sincronizados++;
}

// Guardar JSON actualizado
if ($sincronizados > 0) {
    file_put_contents($presupuestosFile, json_encode($presupuestos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "<p class='success'>✅ Archivo presupuestos.json actualizado</p>";
}

echo "<hr><h2>📊 Resumen</h2>";
echo "<p class='success'>✅ Sincronizados: $sincronizados</p>";
echo "<p class='info'>⚠️ Ya estaban sincronizados: $yaSincronizados</p>";
echo "<p class='error'>❌ Errores: $errores</p>";

echo "<hr><p><strong>Proceso completado</strong>. Ahora puedes verificar en el CRM.</p>";
echo "<p><a href='index.php'>← Volver a Presupuestos</a></p>";
?>