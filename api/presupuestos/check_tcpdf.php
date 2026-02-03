<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config.php';
require_once BASE_PATH . '/tcpdf/tcpdf.php';

echo '<h2>Métodos de TCPDF disponibles</h2>';
echo '<pre style="background:#f5f5f5;padding:15px;overflow:auto;max-height:500px;">';

$methods = get_class_methods('TCPDF');
sort($methods);
foreach ($methods as $m) {
    echo "$m\n";
}

echo '</pre>';

// Buscar específicamente los que necesitamos
$buscar = ['getPageNo','page','getPage','PageNo','aliasNbPages','AliasNbPages','getNumPages','NumPages','getPageWidth','pageWidth','GetPageWidth'];
echo '<h2>Métodos buscados</h2><pre style="background:#f5f5f5;padding:15px;">';
foreach ($buscar as $m) {
    echo "$m: " . (method_exists('TCPDF', $m) ? '✅ EXISTS' : '❌ no existe') . "\n";
}
echo '</pre>';

// Mostrar las primeras líneas del archivo tcpdf.php para ver versión
echo '<h2>Primeras 30 líneas de tcpdf.php</h2><pre style="background:#f5f5f5;padding:15px;">';
$lines = array_slice(file(BASE_PATH . '/tcpdf/tcpdf.php'), 0, 30);
echo htmlspecialchars(implode('', $lines));
echo '</pre>';
?>