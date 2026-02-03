<?php
/**
 * API Backend - Presupuestos
 * Maneja: Guardar, Eliminar, Generar PDF, Enviar Email
 */

require_once '../config.php';

// Archivo de almacenamiento
$presupuestosFile = DATA_PATH . '/presupuestos.json';

// Determinar acción
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'guardar':
    case 'guardar_pdf':
        guardarPresupuesto();
        break;

    case 'delete':
        eliminarPresupuesto();
        break;

    case 'pdf':
        generarPDF();
        break;

    case 'email':
        enviarEmail();
        break;

    default:
        mostrarError('Acción no válida');
}

/**
 * Limpia texto para PDF:
 * - Decodifica entidades (&lt;p&gt;, &amp;nbsp;...)
 * - Convierte <p>, <br> a saltos
 * - Quita HTML y normaliza espacios/saltos
 */
function pdf_text_plain($html) {
    if ($html === null) return '';

    // 1) Decodificar entidades HTML (&lt;p&gt; -> <p>, &amp;nbsp; -> &nbsp;)
    $txt = html_entity_decode((string)$html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    // 2) Convertir tags comunes a saltos de línea
    $txt = preg_replace('~<\s*br\s*/?\s*>~i', "\n", $txt);
    $txt = preg_replace('~<\s*/\s*p\s*>~i', "\n", $txt);
    $txt = preg_replace('~<\s*p[^>]*>~i', '', $txt);

    // 3) Eliminar el resto de HTML
    $txt = strip_tags($txt);

    // 4) Normalizar espacios (incluye NBSP)
    $txt = str_replace("\xC2\xA0", ' ', $txt); // NBSP UTF-8
    $txt = preg_replace("/[ \t]+/", " ", $txt);

    // 5) Normalizar saltos (máx 2 seguidos)
    $txt = str_replace(["\r\n", "\r"], "\n", $txt);
    $txt = preg_replace("/\n{3,}/", "\n\n", $txt);

    return trim($txt);
}

/**
 * Guardar presupuesto
 */
function guardarPresupuesto() {
    global $presupuestosFile;

    if (empty($_POST['cliente_nombre']) || empty($_POST['cliente_email']) || empty($_POST['fecha_propuesta'])) {
        echo json_encode(['success' => false, 'message' => 'Faltan datos requeridos']);
        exit;
    }

    $presupuestos = [];
    if (file_exists($presupuestosFile)) {
        $presupuestos = json_decode(file_get_contents($presupuestosFile), true);
        if (!is_array($presupuestos)) $presupuestos = [];
    }

    $id = $_POST['id'] ?? 'PRES-' . date('Ymd') . '-' . str_pad(count($presupuestos) + 1, 4, '0', STR_PAD_LEFT);

    $items = [];
    if (isset($_POST['items']) && is_array($_POST['items'])) {
        foreach ($_POST['items'] as $item) {
            if (!empty($item['nombre'])) {
                $items[] = [
                    'nombre' => $item['nombre'],
                    'descripcion' => $item['descripcion'] ?? '',
                    'cantidad' => floatval($item['cantidad']),
                    'precio' => floatval($item['precio']),
                    'unidad' => $item['unidad'] ?? ''
                ];
            }
        }
    }

    $presupuesto = [
        'id' => $id,
        'fecha_propuesta' => $_POST['fecha_propuesta'],
        'valido_hasta' => $_POST['valido_hasta'],
        'cliente_id' => $_POST['cliente_id'] ?? '',
        'cliente_nombre' => $_POST['cliente_nombre'],
        'cliente_email' => $_POST['cliente_email'],
        'cliente_telefono' => $_POST['cliente_telefono'] ?? '',
        'cliente_direccion' => $_POST['cliente_direccion'] ?? '',
        'cliente_ciudad' => $_POST['cliente_ciudad'] ?? '',
        'cliente_cp' => $_POST['cliente_cp'] ?? '',
        'cliente_pais' => $_POST['cliente_pais'] ?? '',
        'cliente_cif' => $_POST['cliente_cif'] ?? '',
        'items' => $items,
        'iva' => floatval($_POST['iva'] ?? 21),
        'segundo_impuesto' => floatval($_POST['segundo_impuesto'] ?? 0),
        'subtotal' => floatval($_POST['subtotal'] ?? 0),
        'total' => floatval($_POST['total'] ?? 0),
        'notas' => $_POST['notas'] ?? '',
        'estado' => 'borrador',
        'fecha_creacion' => date('Y-m-d H:i:s'),
        'fecha_modificacion' => date('Y-m-d H:i:s')
    ];

    $encontrado = false;
    foreach ($presupuestos as $key => $p) {
        if ($p['id'] === $id) {
            $presupuesto['fecha_creacion'] = $p['fecha_creacion'] ?? date('Y-m-d H:i:s');
            $presupuestos[$key] = $presupuesto;
            $encontrado = true;
            break;
        }
    }

    if (!$encontrado) {
        $presupuestos[] = $presupuesto;
    }

    file_put_contents($presupuestosFile, json_encode($presupuestos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    guardarAuditoria('presupuesto_guardado', 'exitoso', 'Presupuesto guardado: ' . $id, [
        'cliente_id' => $presupuesto['cliente_id'],
        'cliente_nombre' => $presupuesto['cliente_nombre'],
        'email' => $presupuesto['cliente_email']
    ]);

    echo json_encode(['success' => true, 'id' => $id, 'message' => 'Presupuesto guardado correctamente']);
    exit;
}

/**
 * Eliminar presupuesto
 */
function eliminarPresupuesto() {
    global $presupuestosFile;

    $id = $_GET['id'] ?? '';
    if (empty($id)) { header('Location: index.php?error=id_invalido'); exit; }

    if (file_exists($presupuestosFile)) {
        $presupuestos = json_decode(file_get_contents($presupuestosFile), true);
        $presupuestos = array_filter($presupuestos, function($p) use ($id) { return $p['id'] !== $id; });
        file_put_contents($presupuestosFile, json_encode(array_values($presupuestos), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    header('Location: index.php?success=eliminado');
    exit;
}

/**
 * Generar PDF - DISEÑO PROFESIONAL
 */
function generarPDF() {
    global $presupuestosFile;

    $id = $_GET['id'] ?? '';
    if (empty($id)) { die('ID de presupuesto no válido'); }

    if (!file_exists($presupuestosFile)) { die('No se encontró el archivo de presupuestos'); }

    $presupuestos = json_decode(file_get_contents($presupuestosFile), true);
    $presupuesto = null;

    foreach ($presupuestos as $p) {
        if (($p['id'] ?? '') === $id) { $presupuesto = $p; break; }
    }

    if (!$presupuesto) { die('Presupuesto no encontrado'); }

    if (!file_exists(BASE_PATH . '/tcpdf/tcpdf.php')) {
        die('Error: TCPDF no está instalado.');
    }

    require_once(BASE_PATH . '/tcpdf/tcpdf.php');

    // =====================================================
    // CLASE PDF PERSONALIZADA CON HEADER Y FOOTER
    // =====================================================
    class TictacPDF extends TCPDF {
        public function header() {
            $pageW = $this->getPageWidth();

            // Fondo rosa redondeado arriba
            $this->SetFillColor(233, 30, 140);
            $this->RoundedRect(0, 0, $pageW, 42, 5, 5, 'F');

            // LOGO (blanco) centrado
            $logo = defined('LOGO_BLANCO') ? LOGO_BLANCO : '';

            // Fallback a ruta local (TCPDF a veces no carga bien por URL)
            if ($logo) {
                $logoLocal = defined('BASE_PATH') ? (BASE_PATH . '/assets/img/logoblanco.png') : '';
                $isUrl = filter_var($logo, FILTER_VALIDATE_URL);

                if ($isUrl && $logoLocal && file_exists($logoLocal)) {
                    $logo = $logoLocal;
                } elseif (!$isUrl && !file_exists($logo) && $logoLocal && file_exists($logoLocal)) {
                    $logo = $logoLocal;
                }
            }

            if (!empty($logo)) {
                $logoW = 36; // ancho en mm (ajusta si quieres)
                $x = ($pageW - $logoW) / 2;
                $this->Image($logo, $x, 6, $logoW, 0, '', '', '', false, 300, '', false, false, 0, false, false, false);
            }

            // "Presupuesto" grande
            $this->SetTextColor(255, 255, 255);
            $this->SetFont('Helvetica', 'B', 20);
            $this->SetXY(0, 24);
            $this->Cell($pageW, 10, 'Presupuesto', 0, 1, 'C');

            // Línea separadora inferior del header
            $this->SetDrawColor(233, 30, 140);
            $this->SetLineWidth(0.3);
            $this->Line(15, 44, $pageW - 15, 44);

            // Reset
            $this->SetTextColor(51, 51, 51);
            $this->SetMargins(15, 50, 15);
            $this->SetY(50);
        }

        public function footer() {
            $pageW = $this->getPageWidth();
            $this->SetY(-38);

            // Línea separadora
            $this->SetDrawColor(233, 30, 140);
            $this->SetLineWidth(0.5);
            $this->Line(15, $this->GetY(), $pageW - 15, $this->GetY());

            $this->Ln(4);

            // Datos empresa centrados
            $this->SetTextColor(51, 51, 51);
            $this->SetFont('Helvetica', 'B', 10);
            $this->Cell($pageW, 5, 'Tictac Comunicación Digital SL', 0, 1, 'C');

            $this->SetFont('Helvetica', '', 8);
            $this->SetTextColor(100, 100, 100);
            $this->Ln(2);
            $this->SetX(0);
            $this->Cell($pageW, 4, 'Plaza de los Carrillos, 5  ·  14001 - Córdoba', 0, 1, 'C');
            $this->SetX(0);
            $this->Cell($pageW, 4, '957 048 147  ·  hola@tictac-comunicacion.es  ·  www.tictac-comunicacion.es', 0, 1, 'C');

            $this->Ln(3);
            // Número de página
            $this->SetFont('Helvetica', 'I', 7);
            $this->SetTextColor(150, 150, 150);
            $this->SetX(0);
            $this->Cell($pageW, 4, 'Página ' . $this->PageNo() . ' / ' . $this->getNumPages(), 0, 1, 'C');
        }
    }

    $pdf = new TictacPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetAutoPageBreak(true, 40);
    $pdf->SetCreator('Tictac Comunicación');
    $pdf->SetAuthor('Tictac Comunicación Digital SL');
    $pdf->SetTitle('Presupuesto ' . ($presupuesto['id'] ?? ''));
    $pdf->AddPage();

    $pdf->SetMargins(15, 50, 15);
    $pdf->SetY(50);

    // =====================================================
    // DOS COLUMNAS: DATOS PROPUESTA | INFO CLIENTE
    // =====================================================
    $colW = 85; // cada columna ~85mm con gap
    $startX = 15;
    $startY = $pdf->GetY();

    // --- COLUMNA IZQUIERDA: Datos de la Propuesta ---
    $pdf->SetXY($startX, $startY);

    $pdf->SetDrawColor(220, 220, 220);
    $pdf->SetFillColor(255, 255, 255);
    $pdf->RoundedRect($startX, $startY, $colW, 48, 3, 3, 'DF');

    $pdf->SetXY($startX + 5, $startY + 4);
    $pdf->SetTextColor(233, 30, 140);
    $pdf->SetFont('Helvetica', 'B', 11);
    $pdf->Cell($colW - 10, 6, 'DATOS DE LA PROPUESTA', 0, 1, 'L');

    $pdf->SetDrawColor(233, 30, 140);
    $pdf->SetLineWidth(0.4);
    $pdf->Line($startX + 5, $pdf->GetY() + 1, $startX + $colW - 5, $pdf->GetY() + 1);
    $pdf->Ln(4);

    $pdf->SetTextColor(51, 51, 51);

    $pdf->SetX($startX + 5);
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->Cell(30, 5, 'ID Propuesta:', 0, 0, 'L');
    $pdf->SetFont('Helvetica', '', 9);
    $pdf->Cell(0, 5, $presupuesto['id'] ?? '', 0, 1, 'L');

    $pdf->SetX($startX + 5);
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->Cell(30, 5, 'Fecha emisión:', 0, 0, 'L');
    $pdf->SetFont('Helvetica', '', 9);
    $pdf->Cell(0, 5, !empty($presupuesto['fecha_propuesta']) ? date('d-m-Y', strtotime($presupuesto['fecha_propuesta'])) : '', 0, 1, 'L');

    $pdf->SetX($startX + 5);
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->Cell(30, 5, 'Válida hasta:', 0, 0, 'L');
    $pdf->SetFont('Helvetica', '', 9);
    $pdf->Cell(0, 5, !empty($presupuesto['valido_hasta']) ? date('d-m-Y', strtotime($presupuesto['valido_hasta'])) : '', 0, 1, 'L');

    // --- COLUMNA DERECHA: Info Cliente ---
    $rightX = $startX + $colW + 5;
    $pdf->SetXY($rightX, $startY);

    $pdf->SetDrawColor(220, 220, 220);
    $pdf->SetFillColor(255, 255, 255);
    $pdf->RoundedRect($rightX, $startY, $colW, 48, 3, 3, 'DF');

    $pdf->SetXY($rightX + 5, $startY + 4);
    $pdf->SetTextColor(233, 30, 140);
    $pdf->SetFont('Helvetica', 'B', 11);
    $pdf->Cell($colW - 10, 6, 'INFORMACIÓN DEL CLIENTE', 0, 1, 'L');

    $pdf->SetDrawColor(233, 30, 140);
    $pdf->SetLineWidth(0.4);
    $pdf->Line($rightX + 5, $pdf->GetY() + 1, $rightX + $colW - 5, $pdf->GetY() + 1);
    $pdf->Ln(4);

    $campos_cliente = [
        ['Cliente:', $presupuesto['cliente_nombre'] ?? ''],
        ['Contacto:', $presupuesto['cliente_nombre'] ?? ''],
        ['Dirección:', $presupuesto['cliente_direccion'] ?? ''],
        ['Ciudad:', ($presupuesto['cliente_ciudad'] ?? '') . (!empty($presupuesto['cliente_cp']) ? ', ' . $presupuesto['cliente_cp'] : '')],
        ['País:', $presupuesto['cliente_pais'] ?? ''],
        ['CIF/NIF:', $presupuesto['cliente_cif'] ?? ''],
    ];

    foreach ($campos_cliente as $campo) {
        $pdf->SetX($rightX + 5);
        $pdf->SetTextColor(51, 51, 51);
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->Cell(25, 5, $campo[0], 0, 0, 'L');
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->Cell(0, 5, $campo[1] ?? '', 0, 1, 'L');
    }

    // =====================================================
    // SECCIÓN "SOBRE NOSOTROS" (bloque rosa claro)
    // =====================================================
    $pdf->SetY($startY + 55);

    $pageW = $pdf->getPageWidth();
    $contentW = $pageW - 30;
    $sobreY = $pdf->GetY();

    $pdf->SetFillColor(255, 240, 247); // rosa muy claro
    $pdf->SetDrawColor(255, 240, 247);
    $pdf->RoundedRect(15, $sobreY, $contentW, 28, 3, 3, 'F');

    $pdf->SetXY(20, $sobreY + 4);
    $pdf->SetTextColor(233, 30, 140);
    $pdf->SetFont('Helvetica', 'B', 11);
    $pdf->Cell($contentW - 10, 6, 'Sobre Nosotros', 0, 1, 'L');

    $pdf->SetX(20);
    $pdf->SetTextColor(51, 51, 51);
    $pdf->SetFont('Helvetica', '', 8);
    $sobreTexto = 'En Tictac Comunicación Digital SL desarrollamos estrategias digitales orientadas a conversión, visibilidad y crecimiento real. Cada propuesta se diseña a medida, alineada con los objetivos del cliente y basada en criterios técnicos, creativos y estratégicos.';
    $pdf->MultiCell($contentW - 10, 3.5, $sobreTexto, 0, 'J');

    // =====================================================
    // TÍTULO "Propuesta Económica" (MÁS MARGEN)
    // =====================================================
    $pdf->Ln(12); // <<--- margen aumentado

    $pdf->SetTextColor(51, 51, 51);
    $pdf->SetFont('Helvetica', 'B', 16);
    $pdf->Cell($contentW, 8, 'Propuesta Económica', 0, 1, 'L');

    $pdf->SetFont('Helvetica', '', 9);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->Cell($contentW, 5, 'A continuación detallamos los servicios incluidos en esta propuesta:', 0, 1, 'L');

    // Nota IVA (bloque beige)
    $pdf->Ln(3);
    $notaY = $pdf->GetY();
    $pdf->SetFillColor(255, 248, 225);
    $pdf->SetDrawColor(255, 248, 225);
    $pdf->RoundedRect(15, $notaY, $contentW, 14, 2, 2, 'F');

    $pdf->SetXY(20, $notaY + 3);
    $pdf->SetTextColor(100, 80, 0);
    $pdf->SetFont('Helvetica', '', 7.5);
    $pdf->Cell($contentW - 10, 4, '* Los precios no incluyen el 21% de IVA', 0, 1, 'L');
    $pdf->SetX(20);
    $pdf->Cell($contentW - 10, 4, '** El presupuesto tendrá validez hasta dos semanas después de su fecha de emisión indicada en la parte superior.', 0, 1, 'L');

    // =====================================================
    // TABLA DE ARTÍCULOS
    // =====================================================
    $pdf->Ln(5);
    $pdf->SetTextColor(51, 51, 51);

    // Anchos columnas
    $cArticulo = 75;
    $cCantidad = 25;
    $cTarifa   = 30;
    $cTotal    = 30;
    $tableW    = $cArticulo + $cCantidad + $cTarifa + $cTotal;

    // Helper: imprime cabecera de tabla (para reimprimir tras saltos de página)
    $printTableHeader = function() use ($pdf, $cArticulo, $cCantidad, $cTarifa, $cTotal, $tableW) {
        $headerY = $pdf->GetY();

        $pdf->SetFillColor(30, 30, 30);
        $pdf->Rect(15, $headerY, $tableW, 8, 'F');

        $pdf->SetXY(17, $headerY);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->Cell($cArticulo - 2, 8, 'Artículo', 0, 0, 'L');
        $pdf->Cell($cCantidad, 8, 'Cantidad', 0, 0, 'C');
        $pdf->Cell($cTarifa, 8, 'Tarifa', 0, 0, 'R');
        $pdf->Cell($cTotal - 2, 8, 'Total', 0, 1, 'R');

        $pdf->SetTextColor(51, 51, 51);
    };

    // Cabecera inicial
    $printTableHeader();

    // FILAS
    $rowAlternate = false;
    $items = $presupuesto['items'] ?? [];
    if (!is_array($items)) $items = [];

    foreach ($items as $item) {
        $cantidad = floatval($item['cantidad'] ?? 0);
        $precio   = floatval($item['precio'] ?? 0);
        $unidad   = (string)($item['unidad'] ?? '');
        $total    = $cantidad * $precio;

        // Limpieza de textos (sin HTML escapado)
        $nombre = pdf_text_plain($item['nombre'] ?? '');
        $desc   = pdf_text_plain($item['descripcion'] ?? '');

        // Calcula altura dinámica si hay descripción
        $descH = 0;
        if ($desc !== '') {
            // Alto real que necesita el texto para el ancho de la columna artículo
            $descH = $pdf->getStringHeight($cArticulo - 2, $desc, false, true, '', 1);
        }
        $rowH = ($desc !== '') ? (6 + $descH + 2) : 7; // 6 (offset) + alto texto + padding
        if ($rowH < 7) $rowH = 7;

        // Control salto de página (deja espacio para el footer)
        $bottomLimit = $pdf->getPageHeight() - 45;
        if (($pdf->GetY() + $rowH) > $bottomLimit) {
            $pdf->AddPage();
            $printTableHeader();
        }

        $rowY = $pdf->GetY();

        // Fondo alternante
        if ($rowAlternate) {
            $pdf->SetFillColor(250, 250, 250);
            $pdf->Rect(15, $rowY, $tableW, $rowH, 'F');
        }

        // Nombre artículo (bold)
        $pdf->SetXY(17, $rowY + 1);
        $pdf->SetFont('Helvetica', 'B', 8.5);
        $pdf->Cell($cArticulo - 2, 5, $nombre, 0, 0, 'L');

        // Cantidad con unidad
        $cantTexto = number_format($cantidad, 2, ',', '.') . ($unidad ? ' ' . $unidad : '');
        $pdf->SetFont('Helvetica', '', 8.5);
        $pdf->Cell($cCantidad, 5, $cantTexto, 0, 0, 'C');

        // Tarifa
        $pdf->Cell($cTarifa, 5, number_format($precio, 2, ',', '.') . '€', 0, 0, 'R');

        // Total (bold)
        $pdf->SetFont('Helvetica', 'B', 8.5);
        $pdf->Cell($cTotal - 2, 5, number_format($total, 2, ',', '.') . '€', 0, 1, 'R');

        // Descripción (MultiCell para saltos de línea reales)
        if ($desc !== '') {
            $pdf->SetFont('Helvetica', '', 7.5);
            $pdf->SetTextColor(100, 100, 100);
            // MultiCell sin mover el cursor (ln=0) y posicionando por XY
            $pdf->MultiCell(
                $cArticulo - 2,
                4,
                $desc,
                0,
                'L',
                false,
                0,
                17,
                $rowY + 6,
                true,
                0,
                false,
                true
            );
            $pdf->SetTextColor(51, 51, 51);
        }

        // Línea inferior fila
        $pdf->SetDrawColor(230, 230, 230);
        $pdf->SetLineWidth(0.2);
        $pdf->Line(15, $rowY + $rowH, 15 + $tableW, $rowY + $rowH);

        // Mueve Y al final real de la fila
        $pdf->SetY($rowY + $rowH);

        $rowAlternate = !$rowAlternate;
    }

    // =====================================================
    // TOTALES (alineados a la derecha)
    // =====================================================
    $pdf->Ln(4);
    $pdf->SetFont('Helvetica', '', 10);
    $pdf->SetTextColor(51, 51, 51);

    $subtotal       = floatval($presupuesto['subtotal'] ?? 0);
    $iva            = floatval($presupuesto['iva'] ?? 21);
    $segundoImpuesto= floatval($presupuesto['segundo_impuesto'] ?? 0);

    $ivaAmount = ($subtotal * $iva) / 100;
    $segAmount = ($subtotal * $segundoImpuesto) / 100;
    $totalFinal = floatval($presupuesto['total'] ?? ($subtotal + $ivaAmount + $segAmount));

    $rightMargin = 15 + $tableW;
    $labelW = 40;
    $valW   = 25;

    // Sub Total
    $pdf->SetX($rightMargin - $labelW - $valW);
    $pdf->SetFont('Helvetica', '', 9);
    $pdf->Cell($labelW, 5, 'Sub Total', 0, 0, 'R');
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->Cell($valW, 5, number_format($subtotal, 2, ',', '.') . '€', 0, 1, 'R');

    // IVA
    $pdf->SetX($rightMargin - $labelW - $valW);
    $pdf->SetFont('Helvetica', '', 9);
    $pdf->Cell($labelW, 5, 'IVA', 0, 0, 'R');
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->Cell($valW, 5, number_format($ivaAmount, 2, ',', '.') . '€', 0, 1, 'R');

    // Segundo impuesto si existe
    if ($segundoImpuesto > 0) {
        $pdf->SetX($rightMargin - $labelW - $valW);
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->Cell($labelW, 5, 'Segundo Impuesto (' . $segundoImpuesto . '%)', 0, 0, 'R');
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->Cell($valW, 5, number_format($segAmount, 2, ',', '.') . '€', 0, 1, 'R');
    }

    // Total final (negro con fondo)
    $pdf->Ln(2);
    $totalY = $pdf->GetY();
    $pdf->SetFillColor(30, 30, 30);
    $pdf->Rect($rightMargin - $labelW - $valW, $totalY, $labelW + $valW, 8, 'F');

    $pdf->SetXY($rightMargin - $labelW - $valW, $totalY);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Helvetica', 'B', 10);
    $pdf->Cell($labelW, 8, 'Total', 0, 0, 'R');
    $pdf->Cell($valW, 8, number_format($totalFinal, 2, ',', '.') . '€', 0, 1, 'R');

    $pdf->SetTextColor(51, 51, 51);

    // =====================================================
    // NOTAS ADICIONALES (si las hay)
    // =====================================================
    if (!empty($presupuesto['notas'])) {
        $pdf->Ln(6);
        $notasY = $pdf->GetY();
        $contentW = $pageW - 30;

        $pdf->SetFillColor(255, 240, 247);
        $pdf->RoundedRect(15, $notasY, $contentW, 22, 3, 3, 'F');

        $pdf->SetXY(20, $notasY + 4);
        $pdf->SetTextColor(233, 30, 140);
        $pdf->SetFont('Helvetica', 'B', 11);
        $pdf->Cell($contentW - 10, 5, 'Notas Adicionales', 0, 1, 'L');

        $pdf->SetX(20);
        $pdf->SetTextColor(51, 51, 51);
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->MultiCell($contentW - 10, 3.5, pdf_text_plain($presupuesto['notas']), 0, 'L');
    }

    // =====================================================
    // OUTPUT
    // =====================================================
    $pdf->Output('Presupuesto_' . ($presupuesto['id'] ?? 'SIN_ID') . '.pdf', 'D');
    exit;
}

/**
 * Enviar email con PDF adjunto
 */
function enviarEmail() {
    global $presupuestosFile;

    $id = $_GET['id'] ?? '';
    if (empty($id)) { header('Location: index.php?error=id_invalido'); exit; }

    if (!file_exists($presupuestosFile)) { header('Location: index.php?error=no_encontrado'); exit; }

    $presupuestos = json_decode(file_get_contents($presupuestosFile), true);
    $presupuesto = null;
    $index = -1;

    foreach ($presupuestos as $key => $p) {
        if (($p['id'] ?? '') === $id) { $presupuesto = $p; $index = $key; break; }
    }

    if (!$presupuesto) { header('Location: index.php?error=no_encontrado'); exit; }

    if (!file_exists(BASE_PATH . '/tcpdf/tcpdf.php')) { header('Location: index.php?error=tcpdf_no_instalado'); exit; }

    // Generar PDF temporal para email (simple)
    require_once(BASE_PATH . '/tcpdf/tcpdf.php');

    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('Tictac Comunicación');
    $pdf->SetAuthor('Tictac Comunicación Digital SL');
    $pdf->SetTitle('Presupuesto ' . ($presupuesto['id'] ?? ''));
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(TRUE, 15);
    $pdf->AddPage();

    $html = generarHTMLPresupuestoSimple($presupuesto);
    $pdf->writeHTML($html, true, false, true, false, '');

    $tmpFile = sys_get_temp_dir() . '/presupuesto_' . $id . '.pdf';
    $pdf->Output($tmpFile, 'F');

    // Email
    $to = $presupuesto['cliente_email'] ?? '';
    $subject = 'Presupuesto ' . ($presupuesto['id'] ?? '') . ' - Tictac Comunicación Digital SL';

    $message = '<html><head><style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .header { background: #E91E8C; color: white; padding: 25px; text-align: center; }
        .content { padding: 30px; }
        .footer { background: #f5f5f5; padding: 15px; text-align: center; font-size: 12px; color: #666; }
    </style></head><body>
        <div class="header"><h2 style="margin:0;">Presupuesto ' . htmlspecialchars($presupuesto['id'] ?? '') . '</h2></div>
        <div class="content">
            <p>Estimado/a <strong>' . htmlspecialchars($presupuesto['cliente_nombre'] ?? '') . '</strong>,</p>
            <p>Adjunto encontrará el presupuesto solicitado con el detalle de los servicios propuestos.</p>
            <p><strong>Resumen:</strong><br>
            ID: ' . htmlspecialchars($presupuesto['id'] ?? '') . '<br>
            Fecha: ' . (!empty($presupuesto['fecha_propuesta']) ? date('d/m/Y', strtotime($presupuesto['fecha_propuesta'])) : '') . '<br>
            Válido hasta: ' . (!empty($presupuesto['valido_hasta']) ? date('d/m/Y', strtotime($presupuesto['valido_hasta'])) : '') . '<br>
            Total: ' . number_format(floatval($presupuesto['total'] ?? 0), 2, ',', '.') . ' €</p>
            <p>Quedamos a su disposición para cualquier consulta.</p>
            <p>Atentamente,<br><strong>Tictac Comunicación Digital SL</strong></p>
        </div>
        <div class="footer">Tictac Comunicación Digital SL · Plaza de los Carrillos, 5 · 14001 Córdoba</div>
    </body></html>';

    $headers = "From: Tictac Comunicación <noreply@" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ">\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $boundary = md5(time());
    $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";

    $body = "--{$boundary}\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $body .= $message . "\r\n";

    $pdfContent = file_get_contents($tmpFile);
    $pdfEncoded = chunk_split(base64_encode($pdfContent));

    $body .= "--{$boundary}\r\n";
    $body .= "Content-Type: application/pdf; name=\"Presupuesto_{$id}.pdf\"\r\n";
    $body .= "Content-Transfer-Encoding: base64\r\n";
    $body .= "Content-Disposition: attachment; filename=\"Presupuesto_{$id}.pdf\"\r\n\r\n";
    $body .= $pdfEncoded . "\r\n";
    $body .= "--{$boundary}--";

    $enviado = mail($to, $subject, $body, $headers);
    @unlink($tmpFile);

    if ($enviado) {
        if (is_array($presupuestos) && $index >= 0) {
            $presupuestos[$index]['estado'] = 'enviado';
            $presupuestos[$index]['fecha_envio'] = date('Y-m-d H:i:s');
            file_put_contents($presupuestosFile, json_encode($presupuestos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
        guardarAuditoria('presupuesto_enviado', 'enviado', 'Presupuesto enviado: ' . $id, []);
        header('Location: index.php?success=email_enviado');
    } else {
        header('Location: index.php?error=email_no_enviado');
    }
    exit;
}

/**
 * HTML simple para PDF del email (fallback)
 */
function generarHTMLPresupuestoSimple($presupuesto) {
    $subtotal = floatval($presupuesto['subtotal'] ?? 0);
    $iva      = floatval($presupuesto['iva'] ?? 21);
    $ivaAmt   = ($subtotal * $iva) / 100;
    $total    = floatval($presupuesto['total'] ?? ($subtotal + $ivaAmt));

    $html = '<style>
        table { width: 100%; border-collapse: collapse; }
        th { background: #1e1e1e; color: white; padding: 8px; text-align: left; font-size: 10px; }
        td { padding: 8px; border-bottom: 1px solid #eee; font-size: 10px; vertical-align: top; }
        .totals-row { text-align: right; padding: 4px 0; }
        small { color: #666; }
    </style>
    <h2 style="color:#E91E8C; text-align:center;">PRESUPUESTO - ' . htmlspecialchars($presupuesto['id'] ?? '') . '</h2>
    <p><strong>Cliente:</strong> ' . htmlspecialchars($presupuesto['cliente_nombre'] ?? '') . '</p>
    <p><strong>Fecha:</strong> ' . (!empty($presupuesto['fecha_propuesta']) ? date('d/m/Y', strtotime($presupuesto['fecha_propuesta'])) : '') . ' | <strong>Válido hasta:</strong> ' . (!empty($presupuesto['valido_hasta']) ? date('d/m/Y', strtotime($presupuesto['valido_hasta'])) : '') . '</p>
    <table>
        <tr><th>Artículo</th><th>Cantidad</th><th style="text-align:right;">Tarifa</th><th style="text-align:right;">Total</th></tr>';

    $items = $presupuesto['items'] ?? [];
    if (!is_array($items)) $items = [];

    foreach ($items as $item) {
        $cantidad = floatval($item['cantidad'] ?? 0);
        $precio   = floatval($item['precio'] ?? 0);
        $itemTotal = $cantidad * $precio;

        $nombre = pdf_text_plain($item['nombre'] ?? '');
        $desc   = pdf_text_plain($item['descripcion'] ?? '');

        $html .= '<tr>
            <td><strong>' . htmlspecialchars($nombre) . '</strong><br><small>' . nl2br(htmlspecialchars($desc)) . '</small></td>
            <td>' . number_format($cantidad, 2, ',', '.') . (!empty($item['unidad']) ? ' ' . htmlspecialchars($item['unidad']) : '') . '</td>
            <td style="text-align:right;">' . number_format($precio, 2, ',', '.') . '€</td>
            <td style="text-align:right;"><strong>' . number_format($itemTotal, 2, ',', '.') . '€</strong></td>
        </tr>';
    }

    $html .= '</table>
    <div class="totals-row"><strong>Sub Total:</strong> ' . number_format($subtotal, 2, ',', '.') . '€</div>
    <div class="totals-row"><strong>IVA (' . $iva . '%):</strong> ' . number_format($ivaAmt, 2, ',', '.') . '€</div>
    <div class="totals-row" style="font-size:14px; border-top:2px solid #1e1e1e; padding-top:4px; margin-top:4px;"><strong>TOTAL: ' . number_format($total, 2, ',', '.') . '€</strong></div>
    <br><p style="text-align:center; color:#666; font-size:9px;">Tictac Comunicación Digital SL · Plaza de los Carrillos, 5 · 14001 Córdoba</p>';

    return $html;
}
