<?php
/**
 * Ejecutar este archivo en el navegador:
 * https://gestion-tictac-comunicacion.es/api/presupuestos/debug_pdf.php
 * 
 * Muestra el error exacto del PDF
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config.php';

// Leer error_log del servidor
$logPaths = [
    '/home/gestiontictaccom/public_html/api/presupuestos/error_log',
    '/home/gestiontictaccom/logs/error_log',
    '/var/log/apache2/error.log',
    ini_get('error_log')
];

echo '<h2>Error Log (últimas 30 líneas)</h2><pre style="background:#f5f5f5;padding:15px;overflow:auto;max-height:300px;">';
foreach ($logPaths as $path) {
    if (file_exists($path)) {
        $lines = file($path);
        $last30 = array_slice($lines, -30);
        echo "📄 $path:\n" . implode('', $last30);
        break;
    }
}
echo '</pre>';

// Intentar generar PDF paso a paso
echo '<h2>Test paso a paso</h2><pre style="background:#f5f5f5;padding:15px;overflow:auto;">';

// 1. Verificar TCPDF
$tcpdfPath = BASE_PATH . '/tcpdf/tcpdf.php';
echo "1. TCPDF path: $tcpdfPath\n";
echo "   Existe: " . (file_exists($tcpdfPath) ? 'SÍ' : 'NO') . "\n";

if (file_exists($tcpdfPath)) {
    require_once($tcpdfPath);
    echo "2. TCPDF cargado OK\n";
    echo "   Versión: " . (defined('TCPDF_VERSION') ? TCPDF_VERSION : 'desconocida') . "\n";
    
    // 3. Intentar crear instancia simple
    try {
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        echo "3. Instancia TCPDF creada OK\n";
        
        // 4. Probar RoundedRect
        $pdf->AddPage();
        $pdf->SetFillColor(233, 30, 140);
        $pdf->RoundedRect(0, 0, 210, 42, 5, 5, 'F');
        echo "4. RoundedRect OK\n";
        
        // 5. Probar Cell centrada
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('Helvetica', 'B', 28);
        $pdf->SetXY(0, 7);
        $pdf->Cell(210, 10, 'tictac', 0, 1, 'C');
        echo "5. Cell centrada OK\n";
        
        // 6. Probar AliasNbPages
        $pdf->AliasNbPages();
        echo "6. AliasNbPages OK\n";
        
        // 7. Cargar presupuesto
        $presupuestosFile = DATA_PATH . '/presupuestos.json';
        echo "7. Presupuestos file: $presupuestosFile\n";
        echo "   Existe: " . (file_exists($presupuestosFile) ? 'SÍ' : 'NO') . "\n";
        
        if (file_exists($presupuestosFile)) {
            $presupuestos = json_decode(file_get_contents($presupuestosFile), true);
            echo "   Total presupuestos: " . count($presupuestos) . "\n";
            if (count($presupuestos) > 0) {
                $ultimo = end($presupuestos);
                echo "   Último ID: " . $ultimo['id'] . "\n";
                echo "   Items count: " . count($ultimo['items'] ?? []) . "\n";
                echo "   Datos: " . json_encode($ultimo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
            }
        }
        
        echo "\n✅ Todos los tests básicos pasaron. El error debe estar en la lógica de dibujo del PDF.\n";
        
    } catch (Exception $e) {
        echo "❌ ERROR: " . $e->getMessage() . "\n";
        echo "   Línea: " . $e->getLine() . "\n";
        echo "   File: " . $e->getFile() . "\n";
        echo "   Trace: " . $e->getTraceAsString() . "\n";
    }
}

echo '</pre>';
?>