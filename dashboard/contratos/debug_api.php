<?php
/**
 * debug_post.php — Hace un POST real a api.php y muestra la respuesta cruda
 * Sube a /dashboard/contratos/debug_post.php y abre en navegador
 * BORRAR después
 */
$url = 'https://gestion-tictac-comunicacion.es/dashboard/contratos/api.php';

// Datos de test
$postData = array(
    'action'            => 'guardar',
    'titulo'            => 'Test Contrato Debug',
    'fecha_contrato'    => date('Y-m-d'),
    'valido_hasta'      => date('Y-m-d', strtotime('+1 year')),
    'cliente_id'        => '',
    'cliente_nombre'    => 'Cliente Debug SA',
    'cliente_email'     => 'debug@test.com',
    'cliente_cif'       => 'B00000001',
    'cliente_direccion' => 'Calle Debug 1',
    'cliente_ciudad'    => 'Cordoba',
    'cliente_cp'        => '14001',
    'cliente_pais'      => 'Espana',
    'cliente_firmante'  => 'Debug User',
    'iva'               => '21',
    'segundo_impuesto'  => '0',
    'subtotal'          => '100.00',
    'total'             => '121.00',
    'notas'             => '',
    'items[0][nombre]'  => 'Servicio Test',
    'items[0][descripcion]' => 'Test',
    'items[0][cantidad]'    => '1',
    'items[0][precio]'      => '100',
    'items[0][unidad]'      => 'mes',
);

// Pasar cookies de sesión actuales
$cookies = '';
foreach ($_COOKIE as $k => $v) {
    $cookies .= $k . '=' . $v . '; ';
}

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_COOKIE, rtrim($cookies, '; '));
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false); // NO seguir redirects
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$curlError = curl_error($ch);
curl_close($ch);

$responseHeaders = substr($response, 0, $headerSize);
$responseBody    = substr($response, $headerSize);

header('Content-Type: text/plain; charset=utf-8');
echo "=== HTTP STATUS: $httpCode ===\n\n";
echo "=== CURL ERROR: " . ($curlError ?: 'ninguno') . " ===\n\n";
echo "=== RESPONSE HEADERS ===\n";
echo $responseHeaders . "\n";
echo "=== RESPONSE BODY (raw) ===\n";
echo $responseBody . "\n";
echo "=== FIN ===\n";