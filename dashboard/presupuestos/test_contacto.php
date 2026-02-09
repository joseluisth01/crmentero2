<?php
/**
 * DIAGNÓSTICO: Probar get_contacto.php
 * URL: /dashboard/presupuestos/test_contacto.php?client_id=6
 */

require_once '../config.php';

$clientId = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;

if ($clientId <= 0) {
    die('❌ Debes proporcionar un client_id. Ejemplo: test_contacto.php?client_id=6');
}

echo "<h1>🔍 Diagnóstico de Contactos - Cliente ID: $clientId</h1>";
echo "<hr>";

// Obtener contactos
$mysqli = conexionBBDD();
if (!$mysqli) {
    die('❌ Error de conexión a BBDD');
}

$sql = "SELECT id, first_name, last_name, job_title, email, phone, alternative_phone, is_primary_contact
        FROM crm_users
        WHERE client_id = $clientId AND deleted = 0 AND user_type = 'client'
        ORDER BY is_primary_contact DESC, id ASC";

echo "<h2>📋 Consulta SQL:</h2>";
echo "<pre>" . htmlspecialchars($sql) . "</pre>";
echo "<hr>";

$result = $mysqli->query($sql);

if (!$result) {
    die('❌ Error en la consulta: ' . $mysqli->error);
}

echo "<h2>📊 Resultados:</h2>";

if ($result->num_rows === 0) {
    echo "<p style='color: red;'>❌ No se encontraron contactos para este cliente.</p>";
} else {
    echo "<p style='color: green;'>✅ Se encontraron " . $result->num_rows . " contacto(s)</p>";
    
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
    echo "<tr style='background: #E91E8C; color: white;'>";
    echo "<th>ID</th><th>Nombre Completo</th><th>Cargo</th><th>Email</th><th>Teléfono</th><th>Tel. Alternativo</th><th>Principal</th>";
    echo "</tr>";
    
    while ($row = $result->fetch_assoc()) {
        $isPrimary = $row['is_primary_contact'] === '1';
        $bgColor = $isPrimary ? '#d4edda' : '#fff';
        
        echo "<tr style='background: $bgColor;'>";
        echo "<td>" . htmlspecialchars($row['id']) . "</td>";
        echo "<td><strong>" . htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . "</strong></td>";
        echo "<td>" . htmlspecialchars($row['job_title'] ?? '-') . "</td>";
        echo "<td>" . htmlspecialchars($row['email'] ?? '-') . "</td>";
        echo "<td>" . htmlspecialchars($row['phone'] ?? '-') . "</td>";
        echo "<td>" . htmlspecialchars($row['alternative_phone'] ?? '-') . "</td>";
        echo "<td>" . ($isPrimary ? '⭐ SÍ' : 'No') . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    $result->free();
}

echo "<hr>";
echo "<h2>🧪 Simulación del JavaScript:</h2>";

// Reiniciar consulta
$result = $mysqli->query($sql);
$contactos = [];
while ($row = $result->fetch_assoc()) {
    $contactos[] = $row;
}
$result->free();

if (count($contactos) > 0) {
    echo "<h3>Paso 1: Buscar contacto principal</h3>";
    $contactoPrincipal = null;
    foreach ($contactos as $c) {
        if ($c['is_primary_contact'] === '1') {
            $contactoPrincipal = $c;
            break;
        }
    }
    
    if ($contactoPrincipal) {
        echo "<p style='color: green;'>✅ Contacto principal encontrado: " . htmlspecialchars($contactoPrincipal['first_name'] . ' ' . $contactoPrincipal['last_name']) . "</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ No hay contacto principal, se usará el primero</p>";
    }
    
    echo "<h3>Paso 2: Contacto seleccionado</h3>";
    $contactoFinal = $contactoPrincipal ? $contactoPrincipal : $contactos[0];
    
    echo "<pre>";
    echo "Email que se asignará: " . htmlspecialchars($contactoFinal['email'] ?? 'VACÍO') . "\n";
    echo "Teléfono que se asignará: " . htmlspecialchars($contactoFinal['phone'] ?? $contactoFinal['alternative_phone'] ?? 'VACÍO') . "\n";
    echo "</pre>";
    
    echo "<h3>Paso 3: JSON que devolvería get_contacto.php</h3>";
    echo "<pre>";
    echo json_encode($contactos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    echo "</pre>";
}

echo "<hr>";
echo "<h2>🔗 Prueba Real de get_contacto.php</h2>";
echo "<p>URL: <a href='get_contacto.php?client_id=$clientId' target='_blank'>get_contacto.php?client_id=$clientId</a></p>";
?>