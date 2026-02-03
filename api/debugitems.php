<?php
/**
 * Debug Items v6 - Leer config.php y funcionesbbdd.php
 * Subir a /api/debugitems.php → ELIMINAR después ⚠️
 */

$docRoot = $_SERVER['DOCUMENT_ROOT'];

$archivos = [
    $docRoot . '/api/config.php',
    $docRoot . '/api/presupuestos/editor.php',
    $docRoot . '/procesosautomaticos/funcionesbbdd.php',
];

?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Debug Items v6</title>
<style>
    body { font-family: Arial; max-width: 1200px; margin: 40px auto; padding: 0 20px; background: #f5f5f5; }
    h3 { color: #333; margin-top: 30px; font-size: 16px; background: #fff; padding: 10px 15px; border-left: 4px solid #E91E8C; }
    pre { background: #1e1e1e; color: #d4d4d4; padding: 15px; border-radius: 8px; overflow-x: auto; font-size: 13px; white-space: pre-wrap; }
    .fail { color: #dc3545; font-weight: bold; }
</style>
</head>
<body>
<?php
foreach ($archivos as $archivo) {
    echo '<h3>📄 ' . str_replace($docRoot, '', $archivo) . '</h3>';
    if (!file_exists($archivo)) {
        echo '<span class="fail">✗ No existe</span><br>';
        continue;
    }
    $contenido = file_get_contents($archivo);
    echo '<pre>' . htmlspecialchars($contenido) . '</pre>';
}
?>
</body>
</html>