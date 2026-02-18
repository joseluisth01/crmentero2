<?php
/**
 * API Backend - Contratos
 * Compatible PHP 7.0+ / PHP 8.x
 *
 * FIX: TCPDF se carga al inicio del archivo para que la clase
 *      ContratoTictacPDF pueda extender TCPDF correctamente.
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once '../config.php';

// ── Cargar TCPDF UNA SOLA VEZ al inicio ──────────────────────────
$_tcpdfPath = BASE_PATH . '/tcpdf/tcpdf.php';
if (file_exists($_tcpdfPath)) {
    require_once $_tcpdfPath;
    define('TCPDF_LOADED', true);
} else {
    define('TCPDF_LOADED', false);
}

// ==============================================================
// CLASE PDF — debe estar DESPUÉS de require_once tcpdf.php
// PHP 8.x: NO se pueden definir clases con extends dentro de if()
// ==============================================================

class ContratoTictacPDF extends TCPDF {
        public function Header() {
            $w = $this->getPageWidth();
            $this->SetFillColor(233, 30, 140);
            $this->RoundedRect(0, 0, $w, 38, 4, '1111', 'F');
            $logoLocal = defined('BASE_PATH') ? BASE_PATH . '/assets/img/logoblanco.png' : '';
            if ($logoLocal && file_exists($logoLocal)) {
                $lw = 36;
                $this->Image($logoLocal, ($w - $lw) / 2, 5, $lw, 0, '', '', '', false, 300);
            }
            $this->SetTextColor(255, 255, 255);
            $this->SetFont('Helvetica', 'B', 15);
            $this->SetXY(0, 20);
            $this->Cell($w, 10, 'Contrato de Servicios', 0, 1, 'C');
            $this->SetDrawColor(233, 30, 140);
            $this->SetLineWidth(0.3);
            $this->Line(15, 40, $w - 15, 40);
            $this->SetTextColor(51, 51, 51);
            $this->SetMargins(20, 46, 20);
            $this->SetY(46);
        }
        public function Footer() {
            $w = $this->getPageWidth();
            $this->SetY(-32);
            $this->SetDrawColor(233, 30, 140);
            $this->SetLineWidth(0.4);
            $this->Line(15, $this->GetY(), $w - 15, $this->GetY());
            $this->Ln(3);
            $this->SetTextColor(51, 51, 51);
            $this->SetFont('Helvetica', 'B', 9);
            $this->Cell($w, 4, 'Tictac Comunicacion Digital SL', 0, 1, 'C');
            $this->SetFont('Helvetica', '', 7);
            $this->SetTextColor(100, 100, 100);
            $this->Ln(1);
            $this->SetX(0);
            $this->Cell($w, 3, 'C/ Escultor Ramon Barba, 1 - Bloque F - 1-2  14012 Cordoba  .  957 048 147  .  hola@tictac-comunicacion.es', 0, 1, 'C');
            $this->Ln(1);
            $this->SetFont('Helvetica', 'I', 7);
            $this->SetTextColor(150, 150, 150);
            $this->SetX(0);
            $this->Cell($w, 3, 'Pagina ' . $this->PageNo() . ' / ' . $this->getNumPages(), 0, 1, 'C');
        }
}


$contratosFile = DATA_PATH . '/contratos.json';

// Asegurar que el archivo contratos.json existe
if (!file_exists($contratosFile)) {
    file_put_contents($contratosFile, '[]');
}

$action = isset($_GET['action'])  ? $_GET['action']  :
         (isset($_POST['action']) ? $_POST['action']  : '');

// Siempre devolver JSON para acciones de guardado
if ($action === 'guardar' || $action === 'guardar_pdf') {
    header('Content-Type: application/json; charset=utf-8');
}

switch ($action) {
    case 'guardar':
    case 'guardar_pdf':
        guardarContrato($contratosFile);
        break;
    case 'delete':
        eliminarContrato($contratosFile);
        break;
    case 'pdf':
        if (!TCPDF_LOADED) { die('Error: TCPDF no instalado en ' . $_tcpdfPath); }
        generarPDF($contratosFile);
        break;
    case 'email':
        enviarEmail($contratosFile);
        break;
    default:
        http_response_code(400);
        echo json_encode(array('success' => false, 'message' => 'Accion no valida: ' . htmlspecialchars($action)));
}

// ==============================================================
// HELPERS
// ==============================================================

function pdf_text_plain($html) {
    if ($html === null) return '';
    $txt = html_entity_decode((string)$html, ENT_QUOTES, 'UTF-8');
    $txt = preg_replace('/<br\s*\/?>/i', "\n", $txt);
    $txt = preg_replace('/<\/p>/i', "\n", $txt);
    $txt = preg_replace('/<p[^>]*>/i', '', $txt);
    $txt = strip_tags($txt);
    $txt = str_replace("\xC2\xA0", ' ', $txt);
    $txt = preg_replace('/[ \t]+/', ' ', $txt);
    $txt = str_replace(array("\r\n", "\r"), "\n", $txt);
    $txt = preg_replace('/\n{3,}/', "\n\n", $txt);
    return trim($txt);
}

function tiene_contenido($html) {
    return $html !== null && pdf_text_plain($html) !== '';
}

// ==============================================================
// GUARDAR CONTRATO
// ==============================================================

function guardarContrato($contratosFile) {
    if (empty($_POST['cliente_nombre']) || empty($_POST['cliente_email']) || empty($_POST['fecha_contrato'])) {
        echo json_encode(array('success' => false, 'message' => 'Faltan datos requeridos (cliente_nombre, cliente_email o fecha_contrato)'));
        exit;
    }

    $contratos = array();
    if (file_exists($contratosFile)) {
        $raw = file_get_contents($contratosFile);
        $contratos = json_decode($raw, true);
        if (!is_array($contratos)) $contratos = array();
    }

    // Generar ID
    if (!empty($_POST['id'])) {
        $id = $_POST['id'];
    } else {
        $id = 'CONT-' . date('Ymd') . '-' . str_pad(count($contratos) + 1, 4, '0', STR_PAD_LEFT);
    }

    // Items
    $items = array();
    if (isset($_POST['items']) && is_array($_POST['items'])) {
        foreach ($_POST['items'] as $item) {
            if (!empty($item['nombre'])) {
                $items[] = array(
                    'nombre'      => (string)$item['nombre'],
                    'descripcion' => isset($item['descripcion']) ? trim(strip_tags((string)$item['descripcion'])) : '',
                    'cantidad'    => floatval(isset($item['cantidad']) ? $item['cantidad'] : 1),
                    'precio'      => floatval(isset($item['precio'])   ? $item['precio']   : 0),
                    'unidad'      => isset($item['unidad']) ? (string)$item['unidad'] : ''
                );
            }
        }
    }

    $contrato = array(
        'id'                => $id,
        'titulo'            => isset($_POST['titulo'])            ? (string)$_POST['titulo']            : '',
        'fecha_contrato'    => (string)$_POST['fecha_contrato'],
        'valido_hasta'      => isset($_POST['valido_hasta'])      ? (string)$_POST['valido_hasta']      : '',
        'cliente_id'        => isset($_POST['cliente_id'])        ? (string)$_POST['cliente_id']        : '',
        'cliente_nombre'    => (string)$_POST['cliente_nombre'],
        'cliente_email'     => (string)$_POST['cliente_email'],
        'cliente_cif'       => isset($_POST['cliente_cif'])       ? (string)$_POST['cliente_cif']       : '',
        'cliente_direccion' => isset($_POST['cliente_direccion']) ? (string)$_POST['cliente_direccion'] : '',
        'cliente_ciudad'    => isset($_POST['cliente_ciudad'])    ? (string)$_POST['cliente_ciudad']    : '',
        'cliente_cp'        => isset($_POST['cliente_cp'])        ? (string)$_POST['cliente_cp']        : '',
        'cliente_pais'      => isset($_POST['cliente_pais'])      ? (string)$_POST['cliente_pais']      : '',
        'cliente_firmante'  => isset($_POST['cliente_firmante'])  ? (string)$_POST['cliente_firmante']  : '',
        'items'             => $items,
        'iva'               => floatval(isset($_POST['iva'])              ? $_POST['iva']              : 21),
        'segundo_impuesto'  => floatval(isset($_POST['segundo_impuesto']) ? $_POST['segundo_impuesto'] : 0),
        'subtotal'          => floatval(isset($_POST['subtotal'])         ? $_POST['subtotal']         : 0),
        'total'             => floatval(isset($_POST['total'])            ? $_POST['total']            : 0),
        'notas'             => isset($_POST['notas'])             ? (string)$_POST['notas']             : '',
        'clausulas_html'    => isset($_POST['clausulas_html'])    ? (string)$_POST['clausulas_html']    : '',
        'estado'            => 'borrador',
        'fecha_creacion'    => date('Y-m-d H:i:s'),
        'fecha_modificacion'=> date('Y-m-d H:i:s')
    );

    // Sincronizar con CRM (no bloquea si falla)
    $crmResult = array('success' => false);
    if (!empty($contrato['cliente_id'])) {
        try {
            $crmResult = sincronizarConCRM($contrato, $contratos, $id);
        } catch (Exception $e) {
            error_log('CRM sync error: ' . $e->getMessage());
        }
    }

    // Actualizar o insertar en el array
    $encontrado = false;
    foreach ($contratos as $key => $c) {
        if (isset($c['id']) && $c['id'] === $id) {
            $contrato['fecha_creacion']  = isset($c['fecha_creacion'])  ? $c['fecha_creacion']  : date('Y-m-d H:i:s');
            $contrato['crm_contract_id'] = isset($c['crm_contract_id']) ? $c['crm_contract_id'] : null;
            $contratos[$key] = $contrato;
            $encontrado = true;
            break;
        }
    }
    if (!$encontrado) {
        $contratos[] = $contrato;
    }

    // Guardar crm_contract_id si se creó
    if (!empty($crmResult['success']) && isset($crmResult['contract_id'])) {
        foreach ($contratos as $key => $c) {
            if (isset($c['id']) && $c['id'] === $id) {
                $contratos[$key]['crm_contract_id'] = $crmResult['contract_id'];
                break;
            }
        }
    }

    $written = file_put_contents(
        $contratosFile,
        json_encode($contratos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );

    if ($written === false) {
        echo json_encode(array(
            'success' => false,
            'message' => 'Error al escribir contratos.json en ' . $contratosFile
        ));
        exit;
    }

    guardarAuditoria('contrato_guardado', 'exitoso', 'Contrato guardado: ' . $id, array(
        'cliente_id'     => $contrato['cliente_id'],
        'cliente_nombre' => $contrato['cliente_nombre'],
        'email'          => $contrato['cliente_email']
    ));

    echo json_encode(array('success' => true, 'id' => $id, 'message' => 'Contrato guardado correctamente'));
    exit;
}

function sincronizarConCRM($contrato, $contratos, $id) {
    $mysqli = conexionBBDD();
    if (!$mysqli) return array('success' => false);

    $crmId = null;
    foreach ($contratos as $c) {
        if (isset($c['id']) && $c['id'] === $id && !empty($c['crm_contract_id'])) {
            $crmId = intval($c['crm_contract_id']);
            break;
        }
    }

    $client_id = intval($contrato['cliente_id']);
    $title     = $mysqli->real_escape_string($contrato['titulo'] ?? '');
    $dateStart = $contrato['fecha_contrato'] ?? date('Y-m-d');
    $dateEnd   = $contrato['valido_hasta']   ?? date('Y-m-d', strtotime('+1 year'));
    $note      = $mysqli->real_escape_string(pdf_text_plain($contrato['notas'] ?? ''));

    if ($crmId) {
        $sql = "UPDATE crm_contracts SET title='$title', date_start='$dateStart', date_end='$dateEnd', description='$note' WHERE id=$crmId";
        if ($mysqli->query($sql)) return array('success' => true, 'contract_id' => $crmId);
    } else {
        $sql = "INSERT INTO crm_contracts (client_id, title, description, date_start, date_end, deleted)
                VALUES ($client_id, '$title', '$note', '$dateStart', '$dateEnd', 0)";
        if ($mysqli->query($sql)) return array('success' => true, 'contract_id' => $mysqli->insert_id);
    }
    return array('success' => false, 'message' => $mysqli->error);
}

// ==============================================================
// ELIMINAR CONTRATO
// ==============================================================

function eliminarContrato($contratosFile) {
    $id = isset($_GET['id']) ? $_GET['id'] : '';
    if (empty($id)) { header('Location: index.php?error=id_invalido'); exit; }

    if (file_exists($contratosFile)) {
        $contratos = json_decode(file_get_contents($contratosFile), true);
        if (!is_array($contratos)) $contratos = array();
        $nuevos = array();
        foreach ($contratos as $c) {
            if (isset($c['id']) && $c['id'] !== $id) $nuevos[] = $c;
        }
        file_put_contents($contratosFile, json_encode($nuevos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    header('Location: index.php?success=eliminado');
    exit;
}

// ==============================================================
// GENERAR PDF
// ==============================================================

function generarPDF($contratosFile) {

    $id = isset($_GET['id']) ? $_GET['id'] : '';
    if (empty($id)) die('ID de contrato no valido');
    if (!file_exists($contratosFile)) die('No se encontro el archivo de contratos');

    $contratos = json_decode(file_get_contents($contratosFile), true);
    $contrato  = null;
    foreach ($contratos as $c) {
        if (isset($c['id']) && $c['id'] === $id) { $contrato = $c; break; }
    }
    if (!$contrato) die('Contrato no encontrado: ' . htmlspecialchars($id));

    // ── Datos ──────────────────────────────────────────────────────
    $contractTitle = isset($contrato['titulo'])          ? $contrato['titulo']          : 'Contrato de Servicios';
    $contractDate  = !empty($contrato['fecha_contrato']) ? date('d/m/Y', strtotime($contrato['fecha_contrato'])) : date('d/m/Y');
    $contractId    = isset($contrato['id'])              ? $contrato['id']              : '';
    $firmanteName  = !empty($contrato['cliente_firmante']) ? $contrato['cliente_firmante'] : (isset($contrato['cliente_nombre']) ? $contrato['cliente_nombre'] : '');

    $toLines = array();
    $toLines[] = 'Denominacion social: ' . (isset($contrato['cliente_nombre']) ? $contrato['cliente_nombre'] : '');
    if (!empty($contrato['cliente_cif']))       $toLines[] = 'NIF/CIF: ' . $contrato['cliente_cif'];
    if (!empty($contrato['cliente_direccion'])) {
        $dir = $contrato['cliente_direccion'];
        if (!empty($contrato['cliente_cp']))     $dir .= ' - ' . $contrato['cliente_cp'];
        if (!empty($contrato['cliente_ciudad'])) $dir .= ' (' . $contrato['cliente_ciudad'] . ')';
        if (!empty($contrato['cliente_pais']))   $dir .= ' - ' . $contrato['cliente_pais'];
        $toLines[] = 'Domicilio: ' . $dir;
    }
    if (!empty($contrato['cliente_email'])) $toLines[] = 'Correo: ' . $contrato['cliente_email'];
    $contractToInfo = implode("\n", $toLines);

    $items      = isset($contrato['items']) && is_array($contrato['items']) ? $contrato['items'] : array();
    $subtotal   = floatval(isset($contrato['subtotal']) ? $contrato['subtotal'] : 0);
    $iva        = floatval(isset($contrato['iva'])      ? $contrato['iva']      : 21);
    $seg        = floatval(isset($contrato['segundo_impuesto']) ? $contrato['segundo_impuesto'] : 0);
    $ivaAmt     = $subtotal * $iva  / 100;
    $segAmt     = $subtotal * $seg  / 100;
    $totalFinal = floatval(isset($contrato['total'])    ? $contrato['total']    : ($subtotal + $ivaAmt + $segAmt));
    $notasTexto = tiene_contenido(isset($contrato['notas']) ? $contrato['notas'] : '') ? pdf_text_plain($contrato['notas']) : '';

    // ── Crear PDF ──────────────────────────────────────────────────
    $pdf = new ContratoTictacPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetAutoPageBreak(true, 38);
    $pdf->SetCreator('Tictac Comunicacion');
    $pdf->SetAuthor('Tictac Comunicacion Digital SL');
    $pdf->SetTitle('Contrato ' . $contractId);
    $pdf->AddPage();
    $pdf->SetMargins(20, 46, 20);
    $pdf->SetY(46);

    $W  = $pdf->getPageWidth();
    $cW = $W - 40;

    // Helpers locales
    $sTitle = function($text) use ($pdf, $cW) {
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->SetTextColor(233, 30, 140);
        $pdf->SetFillColor(255, 240, 247);
        $pdf->SetDrawColor(255, 240, 247);
        $pdf->RoundedRect(20, $pdf->GetY(), $cW, 7, 2, '1111', 'F');
        $pdf->SetX(22);
        $pdf->Cell($cW - 4, 7, $text, 0, 1, 'L');
        $pdf->SetTextColor(51, 51, 51);
        $pdf->Ln(1);
    };

    $body = function($text) use ($pdf, $cW) {
        $pdf->SetFont('Helvetica', '', 8.5);
        $pdf->SetTextColor(51, 51, 51);
        $pdf->SetX(20);
        $pdf->MultiCell($cW, 4.5, $text, 0, 'J');
        $pdf->Ln(3);
    };

    // Renderiza parrafos separados por \n\n con mas espacio visual entre ellos
    $bodyBlocks = function($text) use ($pdf, $cW) {
        $blocks = explode("\n\n", $text);
        foreach ($blocks as $i => $block) {
            $block = trim($block);
            if (!$block) continue;
            // Detectar si empieza con número de punto (ej: "3.1." o "3.4.")
            $isPoint = preg_match('/^[\d]+\.[\d]*\.?/', $block);
            if ($isPoint) {
                // Extraer el número del punto y el resto
                preg_match('/^([\d]+\.[\d]*\.?\s*)(.*)$/s', $block, $m);
                $num  = isset($m[1]) ? trim($m[1]) : '';
                $rest = isset($m[2]) ? trim($m[2]) : $block;
                $pdf->SetFont('Helvetica', 'B', 8.5);
                $pdf->SetTextColor(51, 51, 51);
                $pdf->SetX(20);
                $pdf->Write(4.5, $num . ' ');
                $pdf->SetFont('Helvetica', '', 8.5);
                $pdf->MultiCell($cW - ($pdf->GetX() - 20), 4.5, $rest, 0, 'J');
            } else {
                $pdf->SetFont('Helvetica', '', 8.5);
                $pdf->SetTextColor(51, 51, 51);
                $pdf->SetX(20);
                $pdf->MultiCell($cW, 4.5, $block, 0, 'J');
            }
            $pdf->Ln(2);
        }
        $pdf->Ln(1);
    };

    $subClause = function($num, $text) use ($pdf, $cW) {
        $pdf->SetFont('Helvetica', 'B', 8.5);
        $pdf->SetTextColor(51, 51, 51);
        $pdf->SetX(20);
        $pdf->Write(4.5, $num . '  ');
        $pdf->SetFont('Helvetica', '', 8.5);
        $pdf->MultiCell($cW - ($pdf->GetX() - 20), 4.5, $text, 0, 'J');
        $pdf->Ln(2);
    };

    $divider = function() use ($pdf, $cW) {
        $pdf->SetDrawColor(220, 220, 220);
        $pdf->SetLineWidth(0.2);
        $pdf->Line(20, $pdf->GetY(), 20 + $cW, $pdf->GetY());
        $pdf->Ln(3);
    };

    // ── Cabecera del contrato ──────────────────────────────────────
    $pdf->SetFillColor(30, 30, 30);
    $pdf->RoundedRect(20, $pdf->GetY(), $cW, 16, 3, '1111', 'F');
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Helvetica', 'B', 12);
    $pdf->SetX(22); $pdf->Cell($cW - 4, 8, $contractTitle, 0, 1, 'C');
    $pdf->SetFont('Helvetica', '', 8.5);
    $pdf->SetX(22); $pdf->Cell($cW - 4, 5, 'Tictac Comunicacion - Hacemos el Marketing que funciona', 0, 1, 'C');
    $pdf->SetTextColor(51, 51, 51);
    $pdf->Ln(4);

    // Dos columnas
    $startY = $pdf->GetY();
    $half   = ($cW - 5) / 2;

    $pdf->SetFillColor(255, 255, 255);
    $pdf->SetDrawColor(220, 220, 220);
    $pdf->RoundedRect(20, $startY, $half, 26, 2, '1111', 'DF');
    $pdf->SetXY(23, $startY + 3);
    $pdf->SetFont('Helvetica', 'B', 8.5); $pdf->SetTextColor(233, 30, 140);
    $pdf->Cell($half - 6, 4, 'DATOS DEL CONTRATO', 0, 1, 'L');
    $pdf->SetDrawColor(233, 30, 140); $pdf->SetLineWidth(0.3);
    $pdf->Line(23, $pdf->GetY(), 23 + $half - 6, $pdf->GetY()); $pdf->Ln(2);
    $pdf->SetTextColor(51, 51, 51);
    $pdf->SetX(23); $pdf->SetFont('Helvetica', 'B', 8.5); $pdf->Cell(30, 4, 'N Contrato:', 0, 0); $pdf->SetFont('Helvetica', '', 8.5); $pdf->Cell(0, 4, $contractId, 0, 1);
    $pdf->SetX(23); $pdf->SetFont('Helvetica', 'B', 8.5); $pdf->Cell(30, 4, 'Fecha:', 0, 0); $pdf->SetFont('Helvetica', '', 8.5); $pdf->Cell(0, 4, 'En Cordoba a ' . $contractDate, 0, 1);
    $pdf->SetX(23); $pdf->SetFont('Helvetica', 'B', 8.5); $pdf->Cell(30, 4, 'Valido hasta:', 0, 0); $pdf->SetFont('Helvetica', '', 8.5); $pdf->Cell(0, 4, !empty($contrato['valido_hasta']) ? date('d/m/Y', strtotime($contrato['valido_hasta'])) : '', 0, 1);

    $rightX = 20 + $half + 5;
    $pdf->SetFillColor(255, 255, 255);
    $pdf->SetDrawColor(220, 220, 220);
    $pdf->RoundedRect($rightX, $startY, $half, 26, 2, '1111', 'DF');
    $pdf->SetXY($rightX + 3, $startY + 3);
    $pdf->SetFont('Helvetica', 'B', 8.5); $pdf->SetTextColor(233, 30, 140);
    $pdf->Cell($half - 6, 4, 'EL CLIENTE', 0, 1, 'L');
    $pdf->SetDrawColor(233, 30, 140); $pdf->SetLineWidth(0.3);
    $pdf->Line($rightX + 3, $pdf->GetY(), $rightX + 3 + $half - 6, $pdf->GetY()); $pdf->Ln(2);
    $pdf->SetTextColor(51, 51, 51); $pdf->SetFont('Helvetica', '', 8.5);
    foreach (explode("\n", $contractToInfo) as $line) {
        $line = trim($line); if (!$line) continue;
        $pdf->SetX($rightX + 3);
        $pdf->MultiCell($half - 6, 3.8, $line, 0, 'L');
    }

    $pdf->SetY($startY + 30);
    $pdf->Ln(4);

    // ── Texto Exponen ──────────────────────────────────────────────
    $introText  = "De una parte, \"El Proveedor\", actualmente y sin perjuicio de otras actividades que ahora o en el futuro pudiera acometer por si o a traves de empresas filiales o participadas, se dedica a la prestacion de servicios relacionados con las tecnologias de la informacion y la informatica.\n\n";
    $introText .= "Sus datos identificativos son los siguientes:\n\n";
    $introText .= "  Denominacion social: TIC TAC COMUNICACION DIGITAL, S.L.\n";
    $introText .= "  NIF: B09912478\n";
    $introText .= "  Domicilio: C/ Escultor Ramon Barba, N 1 - Bloque F - 1-2  14012 (Cordoba)\n";
    $introText .= "  Correo: hola@tictac-comunicacion.es\n\n";
    $introText .= "De otra parte, de ahora en adelante (El Cliente):\n\n";
    $introText .= $contractToInfo . "\n\n";
    $introText .= "EXPONEN\n\n";
    $introText .= "1. Que \"El Proveedor\" es una empresa que tiene por objeto la prestacion de servicios en el ambito del marketing tradicional y online, diseno de sitios y paginas web, campanas de publicidad, entre ellos el de soporte, implementacion, mantenimiento, desarrollo y programacion de aplicaciones web.\n\n";
    $introText .= "2. Que \"El Cliente\" esta interesado en la contratacion de los servicios que se exponen en la hoja(s) de encargo adjunta a continuacion y tambien firmada.\n\n";
    $introText .= "3. Que \"El Proveedor\" tiene la capacidad precisa para la prestacion de los referidos servicios durante la vigencia del presente contrato, disponiendo de la organizacion y medios necesarios para ello.\n\n";
    $introText .= "En virtud de todo lo expuesto, las partes convienen en suscribir el presente CONTRATO DE PRESTACION DE SERVICIOS (en adelante, el \"Contrato\"), con sujecion a las siguientes:";

    $pdf->SetFont('Helvetica', '', 8.5);
    $pdf->SetTextColor(51, 51, 51);
    $pdf->SetX(20);
    $pdf->MultiCell($cW, 4.3, $introText, 0, 'J');
    $pdf->Ln(4);

    // ── CLAUSULAS ──────────────────────────────────────────────────
    $pdf->SetFillColor(30, 30, 30);
    $pdf->RoundedRect(20, $pdf->GetY(), $cW, 8, 2, '1111', 'F');
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Helvetica', 'B', 11);
    $pdf->SetX(22); $pdf->Cell($cW - 4, 8, 'CLAUSULAS', 0, 1, 'C');
    $pdf->SetTextColor(51, 51, 51);
    $pdf->Ln(3);

    // Cláusulas: si el contrato tiene texto personalizado en clausulas_html, lo usa.
    // Si no, renderiza las cláusulas legales por defecto.
    $clausulasPersonalizadas = isset($contrato['clausulas_html']) && trim(strip_tags($contrato['clausulas_html'])) !== '';

    if ($clausulasPersonalizadas) {
        // Renderizador HTML→PDF: respeta <strong>, <b>, <p>, <br>, <li>, <h1-h3>
        $html = $contrato['clausulas_html'];

        // Normalizar saltos de línea
        $html = str_replace(["\r\n", "\r"], "\n", $html);

        // Separar en bloques por etiqueta de párrafo/bloque
        $html = preg_replace('/<\/h[1-6]>/i',     "</h_>\n", $html);
        $html = preg_replace('/<\/p>/i',           "</p>\n",  $html);
        $html = preg_replace('/<\/li>/i',          "</li>\n", $html);
        $html = preg_replace('/<br\s*\/?>/i',      "\n",      $html);

        $blocks = preg_split('/\n+/', $html);

        $tablaInsertada = false;
        foreach ($blocks as $block) {
            $block = trim($block);
            if ($block === '' || $block === '&nbsp;') continue;

            // DETECTAR INICIO DE CLÁUSULA 4 para insertar tabla antes
            $rawTextClean = html_entity_decode(strip_tags($block), ENT_QUOTES, 'UTF-8');
            $rawTextClean = trim($rawTextClean);
            if (!$tablaInsertada && preg_match('/^4\.\s+/i', $rawTextClean)) {
                // ═══ INSERTAR TABLA DE ÍTEMS AQUÍ (al final de la cláusula 3) ═══
                $cArt = 70; $cCant = 25; $cTar = 30; $cTot = 30;
                $tW   = $cArt + $cCant + $cTar + $cTot;
                $stX  = 20;

                $pdf->SetFillColor(30, 30, 30);
                $pdf->Rect($stX, $pdf->GetY(), $tW, 7, 'F');
                $pdf->SetXY($stX + 2, $pdf->GetY());
                $pdf->SetTextColor(255, 255, 255);
                $pdf->SetFont('Helvetica', 'B', 8.5);
                $pdf->Cell($cArt - 2, 7, 'Articulo', 0, 0, 'L');
                $pdf->Cell($cCant,    7, 'Cantidad', 0, 0, 'C');
                $pdf->Cell($cTar,     7, 'Tarifa',   0, 0, 'R');
                $pdf->Cell($cTot - 2, 7, 'Total',    0, 1, 'R');
                $pdf->SetTextColor(51, 51, 51);

                $alt = false;
                foreach ($items as $item) {
                    $cant   = floatval(isset($item['cantidad']) ? $item['cantidad'] : 0);
                    $prec   = floatval(isset($item['precio'])   ? $item['precio']   : 0);
                    $iTotal = $cant * $prec;
                    $uni    = isset($item['unidad'])      ? $item['unidad']      : '';
                    $nom    = isset($item['nombre'])      ? $item['nombre']      : '';
                    $descRaw = isset($item['descripcion']) ? $item['descripcion'] : '';
                    $desc    = trim(pdf_text_plain($descRaw));
                    $rowH   = $desc ? 12 : 7;
                    if (($pdf->GetY() + $rowH) > $pdf->getPageHeight() - 42) { $pdf->AddPage(); }
                    $rowY = $pdf->GetY();
                    if ($alt) { $pdf->SetFillColor(250, 250, 250); $pdf->Rect($stX, $rowY, $tW, $rowH, 'F'); }
                    $pdf->SetXY($stX + 2, $rowY + 1);
                    $pdf->SetFont('Helvetica', 'B', 8.5);
                    $pdf->Cell($cArt - 2, 5, $nom, 0, 0, 'L');
                    $pdf->SetFont('Helvetica', '', 8.5);
                    $pdf->Cell($cCant, 5, number_format($cant, 2, ',', '.') . ($uni ? ' ' . $uni : ''), 0, 0, 'C');
                    $pdf->Cell($cTar,  5, number_format($prec, 2, ',', '.') . 'E', 0, 0, 'R');
                    $pdf->SetFont('Helvetica', 'B', 8.5);
                    $pdf->Cell($cTot - 2, 5, number_format($iTotal, 2, ',', '.') . 'E', 0, 1, 'R');
                    if ($desc) {
                        $pdf->SetFont('Helvetica', '', 7.5);
                        $pdf->SetTextColor(100, 100, 100);
                        $pdf->SetX($stX + 4);
                        $pdf->MultiCell($cArt - 6, 4, $desc, 0, 'L');
                        $pdf->SetTextColor(51, 51, 51);
                    }
                    $pdf->SetDrawColor(230, 230, 230); $pdf->SetLineWidth(0.2);
                    $pdf->Line($stX, $rowY + $rowH, $stX + $tW, $rowY + $rowH);
                    $pdf->SetY($rowY + $rowH);
                    $alt = !$alt;
                }

                $pdf->Ln(3);
                $lW = 40; $vW = 28; $rightEnd = $stX + $tW;
                $pdf->SetX($rightEnd - $lW - $vW);
                $pdf->SetFont('Helvetica', '', 9);  $pdf->Cell($lW, 5, 'Sub Total', 0, 0, 'R');
                $pdf->SetFont('Helvetica', 'B', 9); $pdf->Cell($vW, 5, number_format($subtotal, 2, ',', '.') . 'E', 0, 1, 'R');
                if ($iva > 0) {
                    $pdf->SetX($rightEnd - $lW - $vW);
                    $pdf->SetFont('Helvetica', '', 9);  $pdf->Cell($lW, 5, 'IVA (' . $iva . '%)', 0, 0, 'R');
                    $pdf->SetFont('Helvetica', 'B', 9); $pdf->Cell($vW, 5, number_format($ivaAmt, 2, ',', '.') . 'E', 0, 1, 'R');
                }
                if ($seg > 0) {
                    $pdf->SetX($rightEnd - $lW - $vW);
                    $pdf->SetFont('Helvetica', '', 9);  $pdf->Cell($lW, 5, 'Impuesto 2 (' . $seg . '%)', 0, 0, 'R');
                    $pdf->SetFont('Helvetica', 'B', 9); $pdf->Cell($vW, 5, number_format($segAmt, 2, ',', '.') . 'E', 0, 1, 'R');
                }
                $pdf->Ln(1);
                $ty = $pdf->GetY();
                $pdf->SetFillColor(30, 30, 30);
                $pdf->Rect($rightEnd - $lW - $vW, $ty, $lW + $vW, 8, 'F');
                $pdf->SetXY($rightEnd - $lW - $vW, $ty);
                $pdf->SetTextColor(255, 255, 255); $pdf->SetFont('Helvetica', 'B', 10);
                $pdf->Cell($lW, 8, 'TOTAL', 0, 0, 'R');
                $pdf->Cell($vW, 8, number_format($totalFinal, 2, ',', '.') . 'E', 0, 1, 'R');
                $pdf->SetTextColor(51, 51, 51);
                $pdf->Ln(4);
                // ═══ FIN TABLA ═══
                $tablaInsertada = true;
            }

            // Detectar si el bloque es un encabezado <h1-h3> o tiene <strong> al inicio
            $isHeading  = (bool) preg_match('/<h[1-3][^>]*>/i', $block);
            $startsStrong = (bool) preg_match('/^\s*(<[^>]+>)*\s*<(strong|b)>/i', $block);
            $isListItem = (bool) preg_match('/<li[^>]*>/i', $block);

            // Extraer texto limpio del bloque
            $rawText = html_entity_decode(strip_tags($block), ENT_QUOTES, 'UTF-8');
            $rawText = str_replace("\xC2\xA0", ' ', $rawText);
            $rawText = preg_replace('/[ \t]+/', ' ', $rawText);
            $rawText = trim($rawText);

            if ($rawText === '') continue;

            // Detectar si hay partes en negrita dentro del bloque (mixed bold/normal)
            // Dividir el bloque en segmentos bold/normal
            $segments = [];
            $remaining = $block;
            // Quitar tags de bloque para trabajar el inline
            $remaining = preg_replace('/<\/?(?:p|li|ul|ol|h[1-6])[^>]*>/i', '', $remaining);
            $remaining = trim($remaining);

            // Parsear segmentos: texto normal y texto en negrita
            $pattern = '/(<(?:strong|b)[^>]*>)(.*?)(<\/(?:strong|b)>)/is';
            preg_match_all($pattern, $remaining, $boldMatches, PREG_OFFSET_CAPTURE);

            if (empty($boldMatches[0])) {
                // Sin negrita: bloque normal o encabezado
                if ($isHeading || $startsStrong) {
                    $pdf->SetFont('Helvetica', 'B', 9.5);
                    $pdf->SetTextColor(33, 33, 33);
                    $pdf->SetX(20);
                    $pdf->MultiCell($cW, 5, $rawText, 0, 'L');
                    $pdf->Ln(1);
                } elseif ($isListItem) {
                    $pdf->SetFont('Helvetica', '', 8.5);
                    $pdf->SetTextColor(51, 51, 51);
                    $pdf->SetX(24);
                    $pdf->MultiCell($cW - 4, 4.5, chr(149) . ' ' . $rawText, 0, 'J');
                } else {
                    $pdf->SetFont('Helvetica', '', 8.5);
                    $pdf->SetTextColor(51, 51, 51);
                    $pdf->SetX(20);
                    $pdf->MultiCell($cW, 4.5, $rawText, 0, 'J');
                }
                $pdf->Ln(2);
            } else {
                // Bloque con negrita inline: renderizar segmento a segmento en la misma línea
                $pdf->SetX(20);
                $cursor = 0;
                $lineStartY = $pdf->GetY();

                // Reconstruir lista de segmentos: [texto, esBold]
                $segs = [];
                $pos = 0;
                $fullText = $remaining;
                foreach ($boldMatches[0] as $idx => $match) {
                    $matchStart = $match[1];
                    if ($matchStart > $pos) {
                        $before = html_entity_decode(strip_tags(substr($fullText, $pos, $matchStart - $pos)), ENT_QUOTES, 'UTF-8');
                        $before = preg_replace('/[ \t]+/', ' ', str_replace("\xC2\xA0", ' ', $before));
                        if (trim($before) !== '') $segs[] = ['text' => $before, 'bold' => false];
                    }
                    $boldText = html_entity_decode(strip_tags($boldMatches[2][$idx][0]), ENT_QUOTES, 'UTF-8');
                    $boldText = preg_replace('/[ \t]+/', ' ', str_replace("\xC2\xA0", ' ', $boldText));
                    if (trim($boldText) !== '') $segs[] = ['text' => $boldText, 'bold' => true];
                    $pos = $matchStart + strlen($match[0]);
                }
                if ($pos < strlen($fullText)) {
                    $after = html_entity_decode(strip_tags(substr($fullText, $pos)), ENT_QUOTES, 'UTF-8');
                    $after = preg_replace('/[ \t]+/', ' ', str_replace("\xC2\xA0", ' ', $after));
                    if (trim($after) !== '') $segs[] = ['text' => $after, 'bold' => false];
                }

                // Si el primer segmento es bold y ocupa todo el párrafo → encabezado
                $allBold = count($segs) === 1 && $segs[0]['bold'];
                if ($allBold) {
                    $pdf->SetFont('Helvetica', 'B', 9.5);
                    $pdf->SetTextColor(33, 33, 33);
                    $pdf->SetX(20);
                    $pdf->MultiCell($cW, 5, $segs[0]['text'], 0, 'L');
                    $pdf->Ln(1);
                } else {
                    // Mixto: usar Write para segmentos en la misma línea
                    $pdf->SetTextColor(51, 51, 51);
                    foreach ($segs as $seg) {
                        $pdf->SetFont('Helvetica', $seg['bold'] ? 'B' : '', 8.5);
                        $pdf->Write(4.5, $seg['text']);
                    }
                    $pdf->Ln(0);
                    $pdf->MultiCell($cW, 4.5, '', 0, 'L'); // forzar salto de línea
                }
                $pdf->Ln(2);
            }
        }
        $pdf->Ln(2);
    } else {

    // 1
    $sTitle('1. OBJETO');
    $bodyBlocks("El objeto del Contrato consiste en la prestación de servicios por parte del Proveedor a cambio del pago de un precio por parte del Cliente, en los términos establecidos en el mismo.\n\nLas solicitudes de modificación del contrato se harán siempre por escrito, remitido por correo ordinario o electrónico hola@tictac-comunicacion.es. Se ejecutarán siempre que sea posible y el cliente deberá asumir los costes en los que el Proveedor haya incurrido, tras dicha modificación del contrato.\n\nEl Cliente acepta que el Proveedor pueda publicar su imagen corporativa, nombre comercial y sitio web dentro de \"casos de éxito\" o \"sección clientes\" de la web de Tic Tac Comunicación (www.tictac-comunicacion.es), así como la firma de la Empresa Tic Tac Comunicación en Footer (Pie de Página de la web del Cliente).");
    $divider();

    // 2
    $sTitle('2. SERVICIOS DEL PROVEEDOR');
    $subClause('2.1.', "Los Servicios del proyecto vendrán descritos en la hoja de encargo adjunta que deberá ser firmada por el cliente y por la empresa que provee el servicio.\n\nEn relación al diseño web, si el cliente ha contratado este servicio y procede, el Proveedor presentará al cliente hasta 3 bocetos en soporte físico o digital, con el objeto de definir el diseño de forma y apariencia genérica y de estructura general del Proyecto y/o sus piezas accesorias si las hubiese.\n\nEl Cliente ha de firmar el Boceto escogido, todos los cambios a partir del momento de la firma, conllevarán costos adicionales.");
    $subClause('2.1.1.', 'Realización de material especial tal como: tipografía no convencional, caligrafía, mapas, diagramas, gráficos, vectores o fotomontajes.');
    $subClause('2.1.2.', 'Preparación de material existente para su reproducción tales como: redibujo parcial o total, conversión a líneas, escaneado y retoque de imágenes, tipeados, etc.');
    $subClause('2.1.3.', 'Seguimiento de la producción.');
    $subClause('2.1.4.', "Recuperación de información, siempre que técnicamente sea posible.\n\nTodas estas actuaciones se realizarán siempre dentro de horas hábiles de trabajo, según el calendario laboral del Proveedor. El horario de trabajo de los técnicos del Proveedor será de lunes a viernes de 9:00 a 17:00, salvo en los meses de Julio y agosto que será de 9:00 a 15:00.\n\nEl Proveedor facilitará los teléfonos y direcciones de correo electrónico necesarias para el reporte de las incidencias.");
    $subClause('2.1.5.', 'La corrección de errores imputables a la manipulación a través de los Programas de gestión de contenidos por personal no autorizado expresamente por el Proveedor.');
    $subClause('2.1.6.', 'Las tareas necesarias para restablecer la situación anterior derivada de operaciones incorrectas por parte del Cliente (o de sus dependientes o colaboradores) que ocasionen pérdidas de información, destrucción o desorganización de ficheros, y situaciones análogas.');
    $subClause('2.1.7.', 'La reparación de daños causados por virus o defectos de otros programas no relacionados en el Contrato, o en anexo posterior.');
    $subClause('2.1.8.', 'La reparación de daños y malfuncionamientos o el aumento de duración de los Servicios causados por accidentes, uso indebido, catástrofes, abusos, alteraciones, conexiones, sustitución de elementos o software no suministrado y/o recomendado por el Proveedor, o el empleo de los Equipos para trabajos distintos de los que fueron diseñados.');
    $divider();

    } // fin else clausulas por defecto

    // ══════════════════════════════════════════════════════════════
    // TABLA DE SERVICIOS (solo cuando se usan cláusulas estándar)
    // Si hay cláusulas personalizadas, la tabla ya se insertó dentro del HTML
    // ══════════════════════════════════════════════════════════════
    if (!$clausulasPersonalizadas) {
    $sTitle('3. VALORACIÓN DE LOS SERVICIOS, FACTURACIÓN, FORMA DE PAGO, IMPUESTOS Y GASTOS');
    $bodyBlocks("3.1. La valoración económica será actualizada anualmente por el Proveedor, en función de las nuevas tarifas que el Proveedor establezca.\n\n3.2. El precio de los Servicios será abonado por el Cliente al Proveedor en el momento de la formalización del Contrato, con carácter previo al inicio de la prestación de los Servicios, mediante transferencia a la cuenta número que el proveedor designe para tal efecto.\n\n3.3. El precio expresado a continuación contienen los impuestos indirectos desglosados a la fecha de la firma actual:");

    $cArt = 70; $cCant = 25; $cTar = 30; $cTot = 30;
    $tW   = $cArt + $cCant + $cTar + $cTot;
    $stX  = 20;

    $pdf->SetFillColor(30, 30, 30);
    $pdf->Rect($stX, $pdf->GetY(), $tW, 7, 'F');
    $pdf->SetXY($stX + 2, $pdf->GetY());
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Helvetica', 'B', 8.5);
    $pdf->Cell($cArt - 2, 7, 'Articulo', 0, 0, 'L');
    $pdf->Cell($cCant,    7, 'Cantidad', 0, 0, 'C');
    $pdf->Cell($cTar,     7, 'Tarifa',   0, 0, 'R');
    $pdf->Cell($cTot - 2, 7, 'Total',    0, 1, 'R');
    $pdf->SetTextColor(51, 51, 51);

    $alt = false;
    foreach ($items as $item) {
        $cant   = floatval(isset($item['cantidad']) ? $item['cantidad'] : 0);
        $prec   = floatval(isset($item['precio'])   ? $item['precio']   : 0);
        $iTotal = $cant * $prec;
        $uni    = isset($item['unidad'])      ? $item['unidad']      : '';
        $nom    = isset($item['nombre'])      ? $item['nombre']      : '';
        // Limpiar HTML de la descripción (puede venir del CRM con etiquetas)
        $descRaw = isset($item['descripcion']) ? $item['descripcion'] : '';
        $desc    = trim(pdf_text_plain($descRaw));
        $rowH   = $desc ? 12 : 7;
        if (($pdf->GetY() + $rowH) > $pdf->getPageHeight() - 42) { $pdf->AddPage(); }
        $rowY = $pdf->GetY();
        if ($alt) { $pdf->SetFillColor(250, 250, 250); $pdf->Rect($stX, $rowY, $tW, $rowH, 'F'); }
        $pdf->SetXY($stX + 2, $rowY + 1);
        $pdf->SetFont('Helvetica', 'B', 8.5);
        $pdf->Cell($cArt - 2, 5, $nom, 0, 0, 'L');
        $pdf->SetFont('Helvetica', '', 8.5);
        $pdf->Cell($cCant, 5, number_format($cant, 2, ',', '.') . ($uni ? ' ' . $uni : ''), 0, 0, 'C');
        $pdf->Cell($cTar,  5, number_format($prec, 2, ',', '.') . 'E', 0, 0, 'R');
        $pdf->SetFont('Helvetica', 'B', 8.5);
        $pdf->Cell($cTot - 2, 5, number_format($iTotal, 2, ',', '.') . 'E', 0, 1, 'R');
        if ($desc) {
            $pdf->SetFont('Helvetica', '', 7.5);
            $pdf->SetTextColor(100, 100, 100);
            $pdf->SetX($stX + 4);
            $pdf->MultiCell($cArt - 6, 4, $desc, 0, 'L');
            $pdf->SetTextColor(51, 51, 51);
        }
        $pdf->SetDrawColor(230, 230, 230); $pdf->SetLineWidth(0.2);
        $pdf->Line($stX, $rowY + $rowH, $stX + $tW, $rowY + $rowH);
        $pdf->SetY($rowY + $rowH);
        $alt = !$alt;
    }

    $pdf->Ln(3);
    $lW = 40; $vW = 28; $rightEnd = $stX + $tW;
    $pdf->SetX($rightEnd - $lW - $vW);
    $pdf->SetFont('Helvetica', '', 9);  $pdf->Cell($lW, 5, 'Sub Total', 0, 0, 'R');
    $pdf->SetFont('Helvetica', 'B', 9); $pdf->Cell($vW, 5, number_format($subtotal, 2, ',', '.') . 'E', 0, 1, 'R');
    if ($iva > 0) {
        $pdf->SetX($rightEnd - $lW - $vW);
        $pdf->SetFont('Helvetica', '', 9);  $pdf->Cell($lW, 5, 'IVA (' . $iva . '%)', 0, 0, 'R');
        $pdf->SetFont('Helvetica', 'B', 9); $pdf->Cell($vW, 5, number_format($ivaAmt, 2, ',', '.') . 'E', 0, 1, 'R');
    }
    if ($seg > 0) {
        $pdf->SetX($rightEnd - $lW - $vW);
        $pdf->SetFont('Helvetica', '', 9);  $pdf->Cell($lW, 5, 'Impuesto 2 (' . $seg . '%)', 0, 0, 'R');
        $pdf->SetFont('Helvetica', 'B', 9); $pdf->Cell($vW, 5, number_format($segAmt, 2, ',', '.') . 'E', 0, 1, 'R');
    }
    $pdf->Ln(1);
    $ty = $pdf->GetY();
    $pdf->SetFillColor(30, 30, 30);
    $pdf->Rect($rightEnd - $lW - $vW, $ty, $lW + $vW, 8, 'F');
    $pdf->SetXY($rightEnd - $lW - $vW, $ty);
    $pdf->SetTextColor(255, 255, 255); $pdf->SetFont('Helvetica', 'B', 10);
    $pdf->Cell($lW, 8, 'TOTAL', 0, 0, 'R');
    $pdf->Cell($vW, 8, number_format($totalFinal, 2, ',', '.') . 'E', 0, 1, 'R');
    $pdf->SetTextColor(51, 51, 51);
    $pdf->Ln(3);

    } // fin if tabla standalone (solo cláusulas estándar)

    if (!$clausulasPersonalizadas) {
    // Texto 3.4, 3.5 y cláusulas 4-14 (solo cuando NO hay cláusulas personalizadas) de los servicios será (de no especificarse otras condiciones particulares).\n\nSe emitirá un cobro mensual en el siguiente número de cuenta bancaria facilitado por el Cliente.\n\n3.5. Cualquier revisión o adiciones a los servicios descritos en el contrato serán facturados como Servicios Adicionales no incluidos en el presupuesto estimado arriba especificado.\n\nTales servicios adicionales incluirán, pero no se limitarán a, cambios en la dimensión (cantidad) del trabajo, cambios en la complejidad de cualquier elemento involucrado en los Proyectos, y cualquier cambio efectuado después de la aprobación de cada etapa del diseño, documentación, etc.\n\nEl Proveedor deberá mantener informado al Cliente de los servicios adicionales requeridos y solicitará la aprobación del Cliente para aquellos servicios adicionales que afecten y excedan los honorarios estimados y reflejados anteriormente.");
    $divider();

    // 4
    $sTitle('4. RESPONSABILIDAD DEL CLIENTE');
    $bodyBlocks("4.1. El Cliente proveerá información fehaciente y completa y materiales al Proveedor, y será responsable de la exactitud y completitud de toda la información y los materiales provistos.\n\nEl Cliente garantiza que todo material provisto al Proveedor no afecta los derechos de autor de terceros.\n\nEl Cliente indemnizará, defenderá y mantendrá fuera de todo litigio al Proveedor de y contra cualquier reclamo, juicio, daño y perjuicio, incluyendo los gastos de defensa, que surgieren de cualquier reclamo en relación con terceros cuyos derechos hayan sido o sean violados o infringidos debido al material provisto por el Cliente.\n\n4.2. El Cliente en caso haber realizado alguna modificación por su cuenta y por ello, haber desconfigurado la web, será el mismo Cliente quien responda por el costo del arreglo que le será presupuestado por el técnico del Proveedor.\n\n4.3. Todo texto e información aportado por el Cliente se entregará al Proveedor en formato digital, preparado para su inserción en los Proyectos. Cuando algún material fuere provisto por el Cliente en otro soporte, deberá ser de calidad profesional y dispuesto para su digitalización sin más preparación o alteración. Este proceso (escaneado, OCR, tipeado, etc.) será presupuestado como un servicio suplementario.");
    $divider();

    // 5
    $sTitle('5. DERECHOS Y PROPIEDAD');
    $bodyBlocks("5.1. Todos los servicios provistos por el Proveedor y aprobados bajo este contrato serán para uso exclusivo del Cliente más allá de su uso promocional propio del Proveedor.\n\n5.2. El Proveedor se compromete a almacenar los originales durante 6 meses a partir de la finalización del Proyecto. Una vez concluido dicho período, no garantizará su manutención.\n\n5.3. El Dominio (dirección web) pertenecerá al Cliente, siendo éste su propietario en todo momento, por lo que podrá ser solicitado en cualquier momento.\n\n5.4. Una vez finalizado el pago total del monto acordado, el Cliente, pasará a ser propietario de la Web.");
    $divider();

    // 6
    $sTitle('6. DURACIÓN DEL CONTRATO');
    $bodyBlocks("6.1. El Contrato tendrá una vigencia mínima de un (1) año, contada a partir de la fecha de la firma del presente Contrato.\n\n6.2. El Cliente podrá rescindir el presente Contrato, notificándoselo por escrito al Proveedor con al menos treinta (30) días de antelación a la fecha de vencimiento inicial, o, en su caso, de cualquiera de sus prórrogas.\n\nEn todo caso, la prórroga del Contrato no significará que se mantenga el mismo precio por los Servicios, sino que el precio será fijado anualmente por el Proveedor, según las tarifas que el mismo establezca para cada año, que se pondrán oportunamente en conocimiento del Cliente.");
    $divider();

    // 7
    $sTitle('7. EXTINCIÓN DEL CONTRATO');
    $bodyBlocks("7.1. El Contrato se extinguirá por las causas generales establecidas en la legislación vigente.\n\n7.2. En todo caso, la extinción del Contrato antes de la finalización del período inicial o de cualquiera de sus prórrogas, no dará lugar a devolución alguna del precio abonado al Proveedor.\n\n7.3. La no acreditación del pago del precio será causa automática de resolución del Contrato, sin perjuicio de la posible reclamación de daños y perjuicios y abono de intereses, que podrá ejercitar el Proveedor si lo estima conveniente.");
    $divider();

    // 8
    $sTitle('8. NATURALEZA DE LA RELACIÓN');
    $bodyBlocks("8.1. El presente Contrato tiene carácter mercantil y se regirá por sus propias cláusulas, y en lo que en ellas no estuviere previsto, por las disposiciones del Código de Comercio, leyes especiales y usos mercantiles, y en su defecto, por el Código Civil.");
    $divider();

    // 9
    $sTitle('9. PROTECCIÓN DE DATOS DE CARÁCTER PERSONAL');
    $bodyBlocks("9.1. Debido a la naturaleza de los Servicios, el Proveedor puede tener que realizar tratamientos automatizados de ficheros del Cliente que contengan datos de carácter personal. En cualquier caso, será el Cliente quien decida sobre la finalidad, contenido y uso del tratamiento de los datos, limitándose el Proveedor a utilizar dichos datos, única y exclusivamente para los fines que figuran en el Contrato y siempre por cuenta del Cliente.\n\n9.2. El Cliente únicamente permitirá el acceso a datos de carácter personal al Proveedor cuando sea necesario para la ejecución del objeto del Contrato.\n\n9.3. El Cliente afirma y garantiza que los datos han sido recogidos de acuerdo a lo establecido en la LOPD, así como que cumple todas las obligaciones establecidas en la LOPD. El Proveedor se exonera de toda responsabilidad que pueda surgir en caso de reclamación por incumplimiento de lo anteriormente garantizado.");
    $divider();

    // 10
    $sTitle('10. CONFIDENCIALIDAD');
    $bodyBlocks("10.1. El Proveedor considerará confidencial toda la información relacionada con los Servicios, y que obtenga durante la prestación de los mismos, salvo que dicha información le fuera conocida previamente o hubiera sido divulgada públicamente, bien con anterioridad a la realización de los trabajos, o posteriormente a ésta.");
    $divider();

    // 11
    $sTitle('11. RESPONSABILIDAD DEL PROVEEDOR');
    $bodyBlocks("11.1. Salvo en los casos de culpa grave o dolo, la responsabilidad total del Proveedor en relación con el Contrato estará sujeta a las limitaciones siguientes:\n\n- La responsabilidad total que, por cualquier concepto, pueda ser obtenida del Proveedor por el Cliente en relación con los daños directos causados al Cliente a consecuencia de los actos u omisiones realizados por el Proveedor en el ámbito del Contrato no excederá, en su conjunto, de la cantidad correspondiente al precio abonado al Proveedor por el Cliente por los Servicios durante la última anualidad.\n\n- El Proveedor no será responsable, en ningún caso, de los daños que puedan ser calificados como daños indirectos, consecuenciales, pérdida de beneficio o de resultados previstos, negocio, ingresos, clientes, datos, imagen, reputación comercial en el mercado, así como de los derivados de su imposibilidad de prestar los Servicios por causas que estuvieran fuera de su control.");
    $divider();

    // 12
    $sTitle('12. ACTUALIZACIÓN');
    $bodyBlocks("12.1. En el caso de que alguna o algunas de las cláusulas del Contrato pasen a ser inválidas, ilegales o inejecutables en virtud de alguna norma jurídica, se considerarán ineficaces en la medida que corresponda, pero en lo demás, este Contrato conservará su validez.\n\n12.2. Para ese caso, las Partes acuerdan sustituir la cláusula o cláusulas afectadas por otra u otras que tengan los efectos económicos más semejantes a los de las sustituidas. CONTRATO ÚNICO");
    $divider();

    // 13
    $sTitle('13. NOTIFICACIONES Y REQUERIMIENTOS');
    $bodyBlocks("13.1. Toda notificación o requerimiento que traiga su causa del Contrato se deberá remitir por escrito a la otra Parte, bien por E-mail, bien personalmente, o por mensajero o correo certificado con acuse de recibo a portes pagados a las personas y direcciones que aparecen en el apartado \"Reunidos\" del presente Contrato, que actuarán de interlocutores, a estos efectos, o a cualesquiera otras que, en su caso, se determinen y comuniquen en el futuro.");
    $divider();

    // 14
    $sTitle('14. JURISDICCIÓN Y COMPETENCIA');
    $bodyBlocks("14.1. Las Partes, con renuncia expresa a cualquier otro fuero que pudiera corresponderles, se someten para cuantos asuntos litigiosos pudieran derivarse en todo lo referente a la interpretación, aplicación o cumplimiento y ejecución del presente Contrato, a la jurisdicción y competencia de los Juzgados y Tribunales de Córdoba.\n\n14.2. Y para que así conste, y en prueba de conformidad y aceptación de todo cuanto antecede, las Partes firman el presente Contrato por duplicado ejemplar y a un sólo efecto en la fecha y lugar indicados en el encabezamiento");
    $divider();

    } // fin if(!clausulasPersonalizadas) cláusulas 3.4-14

    // ── Notas adheridas ────────────────────────────────────────────
    if ($notasTexto) {
        $pdf->Ln(1);
        $notaY = $pdf->GetY();
        $pdf->SetFillColor(255, 248, 225);
        $pdf->SetDrawColor(255, 248, 225);
        $pdf->RoundedRect(20, $notaY, $cW, 7, 2, '1111', 'F');
        $pdf->SetXY(22, $notaY + 1.5);
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->SetTextColor(100, 80, 0);
        $pdf->Cell($cW - 4, 5, 'NOTAS ADHERIDAS AL CONTRATO:', 0, 1, 'L');
        $pdf->SetTextColor(51, 51, 51);
        $pdf->Ln(2);
        $pdf->SetX(22);
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->MultiCell($cW - 4, 4.3, $notasTexto, 0, 'J');
        $pdf->Ln(8);
    }

    // ── Firmas ─────────────────────────────────────────────────────
    if (($pdf->GetY() + 45) > $pdf->getPageHeight() - 40) $pdf->AddPage();
    $pdf->SetDrawColor(200, 200, 200); $pdf->SetLineWidth(0.3);
    $pdf->Line(20, $pdf->GetY(), 20 + $cW, $pdf->GetY());
    $pdf->Ln(6);
    $half2  = ($cW - 5) / 2;
    $firmaY = $pdf->GetY();

    $pdf->SetFont('Helvetica', 'B', 9); $pdf->SetTextColor(233, 30, 140);
    $pdf->SetX(20); $pdf->Cell($half2, 5, 'FIRMA Y SELLO DEL PROVEEDOR', 0, 1, 'C');
    $pdf->SetTextColor(51, 51, 51); $pdf->Ln(14);
    $pdf->SetDrawColor(170, 170, 170); $pdf->SetLineWidth(0.4);
    $pdf->Line(20, $pdf->GetY(), 20 + $half2 - 5, $pdf->GetY()); $pdf->Ln(3);
    $pdf->SetFont('Helvetica', '', 8);
    $pdf->SetX(20); $pdf->Cell($half2, 4, 'Tictac Comunicacion Digital SL', 0, 1, 'C');

    $pdf->SetY($firmaY);
    $pdf->SetFont('Helvetica', 'B', 9); $pdf->SetTextColor(233, 30, 140);
    $pdf->SetX(20 + $half2 + 5); $pdf->Cell($half2, 5, 'FIRMA Y SELLO DEL CLIENTE', 0, 1, 'C');
    $pdf->SetTextColor(51, 51, 51); $pdf->Ln(14);
    $pdf->SetDrawColor(170, 170, 170); $pdf->SetLineWidth(0.4);
    $pdf->Line(20 + $half2 + 5, $pdf->GetY(), 20 + $cW, $pdf->GetY()); $pdf->Ln(3);
    $pdf->SetFont('Helvetica', '', 8);
    $pdf->SetX(20 + $half2 + 5); $pdf->Cell($half2, 4, $firmanteName, 0, 1, 'C');

    // ── Output ─────────────────────────────────────────────────────
    $mode     = isset($_GET['mode']) ? $_GET['mode'] : 'download';
    $filename = 'Contrato_' . $contractId . '.pdf';

    if ($mode === 'save') {
        $tmpFile = sys_get_temp_dir() . '/contrato_' . $contractId . '_' . time() . '.pdf';
        $pdf->Output($tmpFile, 'F');
        return $tmpFile;
    } else {
        $pdf->Output($filename, 'D');
        exit;
    }
}

// ==============================================================
// ENVIAR EMAIL
// ==============================================================

function enviarEmail($contratosFile) {
    $id = isset($_GET['id']) ? $_GET['id'] : '';
    if (empty($id)) { header('Location: index.php?error=id_invalido'); exit; }
    if (!file_exists($contratosFile)) { header('Location: index.php?error=no_encontrado'); exit; }

    $contratos = json_decode(file_get_contents($contratosFile), true);
    $contrato = null; $index = -1;
    foreach ($contratos as $k => $c) {
        if (isset($c['id']) && $c['id'] === $id) { $contrato = $c; $index = $k; break; }
    }
    if (!$contrato) { header('Location: index.php?error=no_encontrado'); exit; }

    $_GET['mode'] = 'save';
    $tmpFile = generarPDF($contratosFile);

    if (!$tmpFile || !file_exists($tmpFile)) { header('Location: index.php?error=pdf_no_generado'); exit; }

    $to      = isset($contrato['cliente_email']) ? $contrato['cliente_email'] : '';
    $titulo  = isset($contrato['titulo'])        ? $contrato['titulo']        : 'Tictac Comunicacion';
    $subject = 'Contrato de Servicios - ' . $titulo;
    $total_fmt = number_format(floatval(isset($contrato['total']) ? $contrato['total'] : 0), 2, ',', '.');
    $fecha_fmt = !empty($contrato['fecha_contrato']) ? date('d/m/Y', strtotime($contrato['fecha_contrato'])) : '';
    $valid_fmt  = !empty($contrato['valido_hasta'])  ? date('d/m/Y', strtotime($contrato['valido_hasta']))  : '';
    $nombre = isset($contrato['cliente_nombre']) ? $contrato['cliente_nombre'] : '';

    // Soporte para múltiples emails separados por coma
    $emailsRaw = array_map('trim', explode(',', $to));
    $emailsValidos = array();
    foreach ($emailsRaw as $em) {
        if (filter_var($em, FILTER_VALIDATE_EMAIL)) $emailsValidos[] = $em;
    }
    if (empty($emailsValidos)) { @unlink($tmpFile); header('Location: index.php?error=email_invalido'); exit; }

    $message = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>body{font-family:Arial,sans-serif;background:#f5f5f5;margin:0;}.w{max-width:600px;margin:20px auto;background:white;border-radius:10px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,.1);}.h{background:#E91E8C;padding:35px 30px;text-align:center;color:white;}.h img{max-width:150px;margin-bottom:12px;}.h h1{margin:0;font-size:20px;}.b{padding:35px 30px;}.box{background:#fff5f9;border-left:4px solid #E91E8C;padding:18px;margin:20px 0;border-radius:5px;}.box strong{color:#E91E8C;}.tot{font-size:20px;color:#E91E8C;font-weight:bold;margin-top:8px;}.f{background:#1a1a1a;color:white;padding:22px 30px;text-align:center;font-size:13px;}.f a{color:#E91E8C;text-decoration:none;}</style></head><body>
<div class="w"><div class="h"><img src="https://tictac-comunicacion.es/wp-content/uploads/2026/02/LOGO-1-2.png" alt="Tictac"><h1>Contrato de Servicios</h1></div>
<div class="b"><p>Estimado/a <strong>' . htmlspecialchars($nombre) . '</strong>,</p><p>Adjunto encontraras el contrato de servicios para tu revision y firma. Por favor leelo detenidamente y contacta con nosotros si tienes cualquier pregunta.</p>
<div class="box"><strong>Resumen del Contrato</strong><br><br><strong>Contrato:</strong> ' . htmlspecialchars($titulo) . '<br><strong>Fecha:</strong> ' . $fecha_fmt . '<br><strong>Valido hasta:</strong> ' . $valid_fmt . '<br><div class="tot">Total: ' . $total_fmt . ' E</div></div>
<p>Una vez revisado, devuelvenos el contrato firmado a <strong>hola@tictac-comunicacion.es</strong>.</p></div>
<div class="f"><strong>Tictac Comunicacion Digital SL</strong><br>C/ Escultor Ramon Barba, 1 - Bloque F - 1-2  14012 Cordoba<br><a href="tel:+34957048147">957 048 147</a> &nbsp; <a href="mailto:hola@tictac-comunicacion.es">hola@tictac-comunicacion.es</a></div></div></body></html>';

    $gmailPath = BASE_PATH . '/presupuestos/gmail_send.php';
    if (!file_exists($gmailPath)) {
        @unlink($tmpFile);
        header('Location: index.php?error=email_no_enviado'); exit;
    }
    require_once $gmailPath;

    // Enviar a cada email de la lista
    $enviadoOk = false;
    foreach ($emailsValidos as $emailDest) {
        $result = enviarEmailGmailAPI($emailDest, $subject, $message, array(array('file_path' => $tmpFile)));
        if ($result) $enviadoOk = true;
    }
    $enviado = $enviadoOk;
    @unlink($tmpFile);

    if ($enviado) {
        if ($index >= 0) {
            $contratos[$index]['estado']      = 'enviado';
            $contratos[$index]['fecha_envio'] = date('Y-m-d H:i:s');
            file_put_contents($contratosFile, json_encode($contratos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
        guardarAuditoria('contrato_enviado', 'exitoso', 'Contrato enviado: ' . $id, array('cliente_email' => $to));
        header('Location: index.php?success=email_enviado');
    } else {
        guardarAuditoria('contrato_enviado', 'error', 'Error enviando: ' . $id, array('cliente_email' => $to));
        header('Location: index.php?error=email_no_enviado');
    }
    exit;
}