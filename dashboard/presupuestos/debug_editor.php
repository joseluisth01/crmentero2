<?php
/**
 * Debug - Ver errores de editor.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 Debug - Editor de Presupuestos</h1>";
echo "<style>body{font-family:Arial;padding:20px;} .success{color:green;} .error{color:red;} .info{color:blue;} pre{background:#f5f5f5;padding:15px;border-radius:5px;overflow:auto;}</style>";

echo "<h2>1️⃣ Verificando config.php</h2>";
if (file_exists('../config.php')) {
    echo "<p class='success'>✅ config.php existe</p>";
    require_once '../config.php';
    echo "<p class='success'>✅ config.php cargado correctamente</p>";
} else {
    echo "<p class='error'>❌ config.php NO existe</p>";
    exit;
}

echo "<h2>2️⃣ Verificando conexión a BBDD</h2>";
$mysqli = conexionBBDD();
if ($mysqli) {
    echo "<p class='success'>✅ Conexión a BBDD exitosa</p>";
} else {
    echo "<p class='error'>❌ Error de conexión a BBDD</p>";
    exit;
}

echo "<h2>3️⃣ Intentando obtener clientes</h2>";
$clientes = array();
$sql = "SELECT id, company_name, email, phone, address, city, zip, country, vat_number 
        FROM crm_clients 
        WHERE deleted = 0 
        ORDER BY company_name ASC";

echo "<p class='info'>SQL: <code>$sql</code></p>";

$result = $mysqli->query($sql);
if ($result) {
    echo "<p class='success'>✅ Query ejecutado correctamente</p>";
    $count = 0;
    while ($row = $result->fetch_assoc()) {
        $clientes[] = $row;
        $count++;
        if ($count <= 5) {
            echo "<pre>" . print_r($row, true) . "</pre>";
        }
    }
    echo "<p class='success'>✅ Total clientes obtenidos: <strong>$count</strong></p>";
    $result->free();
} else {
    echo "<p class='error'>❌ Error en query: " . $mysqli->error . "</p>";
}

echo "<h2>4️⃣ Intentando obtener artículos</h2>";
$articulos = getArticulosCRM();
if (is_array($articulos)) {
    echo "<p class='success'>✅ Artículos obtenidos: <strong>" . count($articulos) . "</strong></p>";
    if (count($articulos) > 0) {
        echo "<p class='info'>Primeros 3 artículos:</p>";
        for ($i = 0; $i < min(3, count($articulos)); $i++) {
            echo "<pre>" . print_r($articulos[$i], true) . "</pre>";
        }
    }
} else {
    echo "<p class='error'>❌ Error obteniendo artículos</p>";
}

echo "<h2>5️⃣ Verificando constantes</h2>";
$constants = ['BRAND_COLOR', 'BRAND_COLOR_DARK', 'ACCENT_COLOR', 'DATA_PATH', 'BASE_URL'];
foreach ($constants as $const) {
    if (defined($const)) {
        echo "<p class='success'>✅ $const = " . constant($const) . "</p>";
    } else {
        echo "<p class='error'>❌ $const NO está definida</p>";
    }
}

echo "<h2>6️⃣ Verificando archivo presupuestos.json</h2>";
$presupuestosFile = DATA_PATH . '/presupuestos.json';
if (file_exists($presupuestosFile)) {
    echo "<p class='success'>✅ presupuestos.json existe en: $presupuestosFile</p>";
    $content = file_get_contents($presupuestosFile);
    $data = json_decode($content, true);
    if (is_array($data)) {
        echo "<p class='success'>✅ JSON válido con " . count($data) . " presupuestos</p>";
    } else {
        echo "<p class='error'>❌ JSON inválido o corrupto</p>";
    }
} else {
    echo "<p class='info'>⚠️ presupuestos.json NO existe (se creará al guardar el primer presupuesto)</p>";
}

echo "<hr><h2>✅ Debug completado</h2>";
echo "<p><strong>Si todo está en verde arriba, el problema podría ser:</strong></p>";
echo "<ul>";
echo "<li>Error en el HTML/JavaScript del editor.php</li>";
echo "<li>Timeout por demasiados clientes/artículos</li>";
echo "<li>Problema con las constantes de estilo</li>";
echo "</ul>";

echo "<p><a href='editor.php'>← Volver a intentar cargar editor.php</a></p>";
?>