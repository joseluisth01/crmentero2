<?php

/**
 * API Backend - Contratos
 * Compatible PHP 7.0+ / PHP 8.x
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once '../config.php';

$_tcpdfPath = BASE_PATH . '/tcpdf/tcpdf.php';
if (file_exists($_tcpdfPath)) {
    require_once $_tcpdfPath;
    define('TCPDF_LOADED', true);
} else {
    define('TCPDF_LOADED', false);
}

class ContratoTictacPDF extends TCPDF
{
    public function Header()
    {
        $w = $this->getPageWidth();
        $this->SetFillColor(215, 33, 115);
        $this->Rect(0, 0, $w, 38, 'F');
        $logoLocal = defined('BASE_PATH') ? BASE_PATH . '/assets/img/logoblanco.png' : '';
        if ($logoLocal && file_exists($logoLocal)) {
            $lw = 36;
            $this->Image($logoLocal, ($w - $lw) / 2, 5, $lw, 0, '', '', '', false, 300);
        }
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Helvetica', 'B', 15);
        $this->SetXY(0, 20);
        $this->Cell($w, 10, 'Contrato de Servicios', 0, 1, 'C');
        $this->SetDrawColor(215, 33, 115);
        $this->SetLineWidth(0.3);
        $this->Line(15, 40, $w - 15, 40);
        $this->SetTextColor(51, 51, 51);
        $this->SetMargins(20, 46, 20);
        $this->SetY(46);
    }
    public function Footer()
    {
        $w = $this->getPageWidth();
        $this->SetY(-32);
        $this->SetDrawColor(215, 33, 115);
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

if (!file_exists($contratosFile)) {
    file_put_contents($contratosFile, '[]');
}

$action = isset($_GET['action'])  ? $_GET['action']  : (isset($_POST['action']) ? $_POST['action']  : '');

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
        if (!TCPDF_LOADED) {
            die('Error: TCPDF no instalado en ' . $_tcpdfPath);
        }
        generarPDF($contratosFile);
        break;
    case 'email':
        enviarEmail($contratosFile);
        break;
    case 'pdf_by_crm_id':
        generarPdfPorCrmId($contratosFile);
        break;
    case 'preview_by_crm_id':
        previewPorCrmId($contratosFile);
        break;
    case 'sync_from_crm':
        sincronizarDesdeCRM($contratosFile);
        break;
    case 'sepa_pdf':
    case 'sepa_email':
        if (!TCPDF_LOADED) {
            die('Error: TCPDF no instalado en ' . $_tcpdfPath);
        }
        $soloEmail = ($action === 'sepa_email');
        generarSepaPDF($soloEmail);
        break;
    default:
        http_response_code(400);
        echo json_encode(array('success' => false, 'message' => 'Accion no valida: ' . htmlspecialchars($action)));
}

// ==============================================================
// HELPERS
// ==============================================================

function pdf_text_plain($html)
{
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

function tiene_contenido($html)
{
    return $html !== null && pdf_text_plain($html) !== '';
}

// ==============================================================
// GUARDAR CONTRATO
// ==============================================================

function guardarContrato($contratosFile)
{
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

    if (!empty($_POST['id'])) {
        $id = $_POST['id'];
    } else {
        $id = 'CONT-' . date('Ymd') . '-' . str_pad(count($contratos) + 1, 4, '0', STR_PAD_LEFT);
    }

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
        'descuento_global'  => floatval(isset($_POST['descuento_global'])  ? $_POST['descuento_global']  : 0),
        'total'             => floatval(isset($_POST['total'])            ? $_POST['total']            : 0),
        'notas'             => isset($_POST['notas'])             ? (string)$_POST['notas']             : '',
        'clausulas_html'             => isset($_POST['clausulas_html'])             ? (string)$_POST['clausulas_html']             : '',
        'kit_digital'                => !empty($_POST['kit_digital']) ? 1 : 0,
        'clausulas_kit_digital_html' => isset($_POST['clausulas_kit_digital_html']) ? (string)$_POST['clausulas_kit_digital_html'] : '',
        'estado'            => 'borrador',
        'fecha_creacion'    => date('Y-m-d H:i:s'),
        'fecha_modificacion' => date('Y-m-d H:i:s')
    );

    $crmResult = array('success' => false);
    if (!empty($contrato['cliente_id'])) {
        try {
            $crmResult = sincronizarConCRM($contrato, $contratos, $id);
        } catch (Exception $e) {
            error_log('CRM sync error: ' . $e->getMessage());
        }
    }

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
        echo json_encode(array('success' => false, 'message' => 'Error al escribir contratos.json en ' . $contratosFile));
        exit;
    }

    guardarAuditoria('contrato_guardado', 'exitoso', 'Contrato guardado: ' . $id, array(
        'cliente_id'      => $contrato['cliente_id'],
        'cliente_nombre'  => $contrato['cliente_nombre'],
        'email'           => $contrato['cliente_email'],
        'crm_contract_id' => isset($crmResult['contract_id']) ? $crmResult['contract_id'] : null
    ));

    echo json_encode(array(
        'success'  => true,
        'id'       => $id,
        'message'  => 'Contrato guardado correctamente',
        'crm_sync' => $crmResult
    ));
    exit;
}

// ==============================================================
// SINCRONIZAR CON CRM
// ==============================================================

function sincronizarConCRM($contrato, $contratos, $id)
{
    $mysqli = conexionBBDD();
    if (!$mysqli) return array('success' => false, 'message' => 'Sin conexion a BBDD');

    $crmId = null;
    foreach ($contratos as $c) {
        if (isset($c['id']) && $c['id'] === $id && !empty($c['crm_contract_id'])) {
            $crmId = intval($c['crm_contract_id']);
            break;
        }
    }

    $client_id     = intval($contrato['cliente_id']);
    $title         = $mysqli->real_escape_string($contrato['titulo'] ?? 'Contrato de Servicios');
    $contract_date = $contrato['fecha_contrato'] ?? date('Y-m-d');
    $valid_until   = !empty($contrato['valido_hasta']) ? $contrato['valido_hasta'] : date('Y-m-d', strtotime('+1 year'));
    $note          = $mysqli->real_escape_string(pdf_text_plain($contrato['notas'] ?? ''));

    $contentHtml  = '<meta charset="UTF-8">';
    $contentHtml .= '<p><strong>Referencia dashboard:</strong> ' . htmlspecialchars($contrato['id'] ?? '') . '</p>';
    $contentHtml .= '<p><strong>Cliente:</strong> ' . htmlspecialchars($contrato['cliente_nombre'] ?? '') . '</p>';
    if (!empty($contrato['items'])) {
        $contentHtml .= '<table border="1" cellpadding="4" style="border-collapse:collapse;width:100%">';
        $contentHtml .= '<tr style="background:#eee"><th>Servicio</th><th>Cant.</th><th>Precio</th><th>Total</th></tr>';
        foreach ($contrato['items'] as $item) {
            $cant  = floatval($item['cantidad'] ?? 0);
            $prec  = floatval($item['precio'] ?? 0);
            $contentHtml .= '<tr>';
            $contentHtml .= '<td>' . htmlspecialchars($item['nombre'] ?? '') . '</td>';
            $contentHtml .= '<td style="text-align:center">' . number_format($cant, 2, ',', '.') . '</td>';
            $contentHtml .= '<td style="text-align:right">' . number_format($prec, 2, ',', '.') . ' €</td>';
            $contentHtml .= '<td style="text-align:right">' . number_format($cant * $prec, 2, ',', '.') . ' €</td>';
            $contentHtml .= '</tr>';
        }
        $contentHtml .= '</table>';
        $contentHtml .= '<p><strong>Total: ' . number_format(floatval($contrato['total'] ?? 0), 2, ',', '.') . ' €</strong></p>';
    }
    $content = $mysqli->real_escape_string($contentHtml);

    if ($crmId) {
        $sql = "UPDATE crm_contracts SET
                    title         = '$title',
                    client_id     = $client_id,
                    contract_date = '$contract_date',
                    valid_until   = '$valid_until',
                    note          = '$note',
                    content       = '$content'
                WHERE id = $crmId AND deleted = 0";

        if ($mysqli->query($sql)) {
            return array('success' => true, 'contract_id' => $crmId, 'action' => 'updated');
        }
        return array('success' => false, 'message' => 'Error UPDATE: ' . $mysqli->error);
    } else {
        $public_key = substr(md5(uniqid(rand(), true)), 0, 10);

        $sql = "INSERT INTO crm_contracts
                    (title, client_id, project_id, contract_date, valid_until,
                     note, status, tax_id, tax_id2,
                     discount_type, discount_amount, discount_amount_type,
                     content, public_key, accepted_by, staff_signed_by,
                     meta_data, files, company_id, deleted)
                VALUES
                    ('$title', $client_id, 0, '$contract_date', '$valid_until',
                     '$note', 'draft', 1, 0,
                     'before_tax', 0, 'percentage',
                     '$content', '$public_key', 0, 0,
                     '', '', 0, 0)";

        if ($mysqli->query($sql)) {
            $newId = $mysqli->insert_id;
            return array('success' => true, 'contract_id' => $newId, 'action' => 'created');
        }
        return array('success' => false, 'message' => 'Error INSERT: ' . $mysqli->error);
    }
}

// ==============================================================
// ELIMINAR CONTRATO
// ==============================================================

function eliminarContrato($contratosFile)
{
    $id = isset($_GET['id']) ? $_GET['id'] : '';
    if (empty($id)) {
        header('Location: index.php?error=id_invalido');
        exit;
    }

    if (file_exists($contratosFile)) {
        $contratos = json_decode(file_get_contents($contratosFile), true);
        if (!is_array($contratos)) $contratos = array();

        $contratoEliminar = null;
        foreach ($contratos as $c) {
            if (isset($c['id']) && $c['id'] === $id) {
                $contratoEliminar = $c;
                break;
            }
        }

        if ($contratoEliminar && !empty($contratoEliminar['crm_contract_id'])) {
            $crmId = intval($contratoEliminar['crm_contract_id']);
            $mysqli = conexionBBDD();
            if ($mysqli) {
                $mysqli->query("UPDATE crm_contracts SET deleted = 1 WHERE id = $crmId");
                $mysqli->close();
                guardarAuditoria(
                    'contrato_eliminado',
                    'exitoso',
                    'Contrato ' . $id . ' eliminado del dashboard y CRM (ID: ' . $crmId . ')',
                    array('local_id' => $id, 'crm_contract_id' => $crmId)
                );
            }
        } else {
            guardarAuditoria(
                'contrato_eliminado',
                'exitoso',
                'Contrato local ' . $id . ' eliminado (sin CRM)',
                array('local_id' => $id)
            );
        }

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
// HELPER: dibujar tabla de items
// ==============================================================

function dibujarTablaItems($pdf, $items, $stX, $cArt, $cCant, $cTar, $cTot, $tW)
{
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
        $cant    = floatval(isset($item['cantidad']) ? $item['cantidad'] : 0);
        $prec    = floatval(isset($item['precio'])   ? $item['precio']   : 0);
        $iTotal  = $cant * $prec;
        $uni     = isset($item['unidad'])       ? $item['unidad']      : '';
        $nom     = isset($item['nombre'])       ? $item['nombre']      : '';
        $descRaw = isset($item['descripcion'])  ? $item['descripcion'] : '';
        $desc    = trim(pdf_text_plain($descRaw));

        $pdf->SetFont('Helvetica', '', 7.5);
        if ($desc !== '') {
            $descH = $pdf->getStringHeight($cArt - 6, $desc, false, true, '', 1);
            $rowH  = 7 + $descH + 4;
        } else {
            $rowH = 7;
        }
        if ($rowH < 7) $rowH = 7;

        $precOrig_pre = floatval(isset($item['precio_original']) ? $item['precio_original'] : 0);
        if ($precOrig_pre > 0 && $precOrig_pre > floatval($item['precio'] ?? 0)) $rowH = max($rowH, 12);

        if (($pdf->GetY() + $rowH) > $pdf->getPageHeight() - 42) {
            $pdf->AddPage();
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
        }

        $rowY = $pdf->GetY();

        if ($alt) {
            $pdf->SetFillColor(250, 250, 250);
            $pdf->Rect($stX, $rowY, $tW, $rowH, 'F');
        }

        $precOrig    = floatval(isset($item['precio_original']) ? $item['precio_original'] : 0);
        $hayDescuento = ($precOrig > 0 && $precOrig > $prec);

        if ($hayDescuento) $rowH = max($rowH, 12);

        $pdf->SetXY($stX + 2, $rowY + 1);
        $pdf->SetFont('Helvetica', 'B', 8.5);
        $pdf->Cell($cArt - 2, 5, $nom, 0, 0, 'L');

        $pdf->SetFont('Helvetica', '', 8.5);
        $pdf->Cell($cCant, 5, number_format($cant, 2, ',', '.') . ($uni ? ' ' . $uni : ''), 0, 0, 'C');

        if ($hayDescuento) {
            $tarifaX = $stX + $cArt + $cCant;
            $pdf->SetFont('Helvetica', '', 7);
            $pdf->SetTextColor(160, 160, 160);
            $origTxt = number_format($precOrig, 2, ',', '.') . 'E';
            $tw = $pdf->GetStringWidth($origTxt);
            $tx = $stX + $cArt + $cCant + $cTar - $tw - 1;
            $ty = $rowY + 0.5;
            $pdf->SetXY($tx, $ty);
            $pdf->Cell($tw, 4, $origTxt, 0, 0, 'L');
            $pdf->SetDrawColor(160, 160, 160);
            $pdf->SetLineWidth(0.3);
            $pdf->Line($tx, $ty + 2.2, $tx + $tw, $ty + 2.2);
            $pdf->SetLineWidth(0.2);
            $pdf->SetFont('Helvetica', 'B', 8.5);
            $pdf->SetTextColor(215, 33, 115);
            $pdf->SetXY($tarifaX, $rowY + 5);
            $pdf->Cell($cTar, 5, number_format($prec, 2, ',', '.') . 'E', 0, 0, 'R');
            $pdf->SetTextColor(51, 51, 51);
        } else {
            $pdf->Cell($cTar, 5, number_format($prec, 2, ',', '.') . 'E', 0, 0, 'R');
        }

        $pdf->SetFont('Helvetica', 'B', 8.5);
        $pdf->SetTextColor(51, 51, 51);
        $pdf->Cell($cTot - 2, 5, number_format($iTotal, 2, ',', '.') . 'E', 0, 1, 'R');

        if ($desc !== '') {
            $pdf->SetFont('Helvetica', '', 7.5);
            $pdf->SetTextColor(100, 100, 100);
            $pdf->SetXY($stX + 4, $rowY + 7);
            $pdf->MultiCell($cArt - 6, 4, $desc, 0, 'L');
            $pdf->SetTextColor(51, 51, 51);
        }

        $pdf->SetDrawColor(230, 230, 230);
        $pdf->SetLineWidth(0.2);
        $pdf->Line($stX, $rowY + $rowH, $stX + $tW, $rowY + $rowH);
        $pdf->SetY($rowY + $rowH);

        $alt = !$alt;
    }
}

// ==============================================================
// HELPER: dibujar totales
// ==============================================================

function dibujarTotalesTabla($pdf, $stX, $tW, $cTot, $subtotal, $iva, $ivaAmt, $seg, $segAmt, $totalFinal, $items = array(), $descuentoGlobal = 0)
{
    $lW = 40;
    $vW = 28;
    $rightEnd = $stX + $tW;
    $pdf->Ln(3);
    $pdf->SetX($rightEnd - $lW - $vW);
    $pdf->SetFont('Helvetica', '', 9);
    $pdf->Cell($lW, 5, 'Sub Total', 0, 0, 'R');
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->Cell($vW, 5, number_format($subtotal, 2, ',', '.') . 'E', 0, 1, 'R');

    if ($iva > 0) {
        $pdf->SetX($rightEnd - $lW - $vW);
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->Cell($lW, 5, 'IVA (' . $iva . '%)', 0, 0, 'R');
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->Cell($vW, 5, number_format($ivaAmt, 2, ',', '.') . 'E', 0, 1, 'R');
    }
    if ($seg > 0) {
        $pdf->SetX($rightEnd - $lW - $vW);
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->Cell($lW, 5, 'Impuesto 2 (' . $seg . '%)', 0, 0, 'R');
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->Cell($vW, 5, number_format($segAmt, 2, ',', '.') . 'E', 0, 1, 'R');
    }

    if ($descuentoGlobal > 0) {
        $pdf->SetX($rightEnd - $lW - $vW);
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->SetTextColor(215, 33, 115);
        $pdf->Cell($lW, 5, 'Descuento', 0, 0, 'R');
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->Cell($vW, 5, '-' . number_format($descuentoGlobal, 2, ',', '.') . 'E', 0, 1, 'R');
        $pdf->SetTextColor(51, 51, 51);
    }

    $subtotalSinDescuento = 0;
    $hayAlgunDescuento = false;
    if (!empty($items)) {
        foreach ($items as $it) {
            $cant = floatval($it['cantidad'] ?? 1);
            $prec = floatval($it['precio'] ?? 0);
            $orig = floatval($it['precio_original'] ?? 0);
            if ($orig > 0 && $orig > $prec) {
                $subtotalSinDescuento += $cant * $orig;
                $hayAlgunDescuento = true;
            } else {
                $subtotalSinDescuento += $cant * $prec;
            }
        }
    }

    if ($hayAlgunDescuento) {
        $totalSinDesc = $subtotalSinDescuento + ($subtotalSinDescuento * $iva / 100) + ($subtotalSinDescuento * $seg / 100);
        $ahorro = $totalSinDesc - $totalFinal;
        $pdf->SetX($rightEnd - $lW - $vW);
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->SetTextColor(160, 160, 160);
        $pdf->Cell($lW, 5, 'Sin descuento', 0, 0, 'R');
        $xVal = $rightEnd - $vW;
        $yVal = $pdf->GetY();
        $pdf->SetX($xVal);
        $origTxt = number_format($totalSinDesc, 2, ',', '.') . 'E';
        $pdf->Cell($vW - 1, 5, $origTxt, 0, 1, 'R');
        $tw = $pdf->GetStringWidth($origTxt);
        $pdf->SetDrawColor(160, 160, 160);
        $pdf->SetLineWidth(0.4);
        $pdf->Line($xVal + $vW - 1 - $tw - 1, $yVal + 3, $xVal + $vW - 1, $yVal + 3);
        $pdf->SetLineWidth(0.2);
        $pdf->SetX($rightEnd - $lW - $vW);
        $pdf->SetFont('Helvetica', 'B', 8.5);
        $pdf->SetTextColor(39, 174, 96);
        $pdf->Cell($lW, 5, 'Ahorro', 0, 0, 'R');
        $pdf->Cell($vW, 5, '-' . number_format($ahorro, 2, ',', '.') . 'E', 0, 1, 'R');
        $pdf->SetTextColor(51, 51, 51);
    }

    $pdf->Ln(1);
    $ty = $pdf->GetY();
    $pdf->SetFillColor(30, 30, 30);
    $pdf->Rect($rightEnd - $lW - $vW, $ty, $lW + $vW, 8, 'F');
    $pdf->SetXY($rightEnd - $lW - $vW, $ty);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Helvetica', 'B', 10);
    $pdf->Cell($lW, 8, 'TOTAL', 0, 0, 'R');
    $pdf->Cell($vW, 8, number_format($totalFinal, 2, ',', '.') . 'E', 0, 1, 'R');
    $pdf->SetTextColor(51, 51, 51);
    $pdf->Ln(4);
}

// ==============================================================
// GENERAR PDF
// ==============================================================

function generarPDF($contratosFile)
{
    $id = isset($_GET['id']) ? $_GET['id'] : '';
    if (empty($id)) die('ID de contrato no valido');
    if (!file_exists($contratosFile)) die('No se encontro el archivo de contratos');

    $contratos = json_decode(file_get_contents($contratosFile), true);
    $contrato  = null;
    foreach ($contratos as $c) {
        if (isset($c['id']) && $c['id'] === $id) {
            $contrato = $c;
            break;
        }
    }
    if (!$contrato) die('Contrato no encontrado: ' . htmlspecialchars($id));

    $contractTitle = isset($contrato['titulo'])          ? $contrato['titulo']          : 'Contrato de Servicios';
    $contractDate  = !empty($contrato['fecha_contrato']) ? date('d/m/Y', strtotime($contrato['fecha_contrato'])) : date('d/m/Y');
    $contractId    = isset($contrato['id'])              ? $contrato['id']              : '';
    $firmanteName  = !empty($contrato['cliente_firmante'])
        ? $contrato['cliente_firmante']
        : (isset($contrato['cliente_nombre']) ? $contrato['cliente_nombre'] : '');

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

    $items         = isset($contrato['items']) && is_array($contrato['items']) ? $contrato['items'] : array();
    $subtotal      = floatval(isset($contrato['subtotal'])         ? $contrato['subtotal']         : 0);
    $iva           = floatval(isset($contrato['iva'])              ? $contrato['iva']              : 21);
    $seg           = floatval(isset($contrato['segundo_impuesto']) ? $contrato['segundo_impuesto'] : 0);
    $descuentoGlob = floatval(isset($contrato['descuento_global']) ? $contrato['descuento_global'] : 0);
    $ivaAmt        = $subtotal * $iva / 100;
    $segAmt        = $subtotal * $seg / 100;
    $totalFinal    = floatval(isset($contrato['total']) ? $contrato['total'] : max(0, $subtotal + $ivaAmt + $segAmt - $descuentoGlob));
    $notasTexto    = tiene_contenido(isset($contrato['notas']) ? $contrato['notas'] : '') ? pdf_text_plain($contrato['notas']) : '';

    $clausulasPersonalizadas = isset($contrato['clausulas_html']) && trim(strip_tags($contrato['clausulas_html'])) !== '';
    $esKitDigital            = !empty($contrato['kit_digital']);

    $cArt  = 70;
    $cCant = 25;
    $cTar  = 30;
    $cTot  = 30;
    $tW    = $cArt + $cCant + $cTar + $cTot;
    $stX   = 20;

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

    $sTitle = function ($text) use ($pdf, $cW) {
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->SetTextColor(215, 33, 115);
        $pdf->SetFillColor(255, 240, 247);
        $pdf->SetDrawColor(255, 240, 247);
        $pdf->RoundedRect(20, $pdf->GetY(), $cW, 7, 2, '1111', 'F');
        $pdf->SetX(22);
        $pdf->Cell($cW - 4, 7, $text, 0, 1, 'L');
        $pdf->SetTextColor(51, 51, 51);
        $pdf->Ln(1);
    };

    $body = function ($text) use ($pdf, $cW) {
        $pdf->SetFont('Helvetica', '', 8.5);
        $pdf->SetTextColor(51, 51, 51);
        $pdf->SetX(20);
        $pdf->MultiCell($cW, 4.5, $text, 0, 'J');
        $pdf->Ln(3);
    };

    $bodyBlocks = function ($text) use ($pdf, $cW) {
        $blocks = explode("\n\n", $text);
        foreach ($blocks as $i => $block) {
            $block = trim($block);
            if (!$block) continue;

            $isPoint = preg_match('/^[\d]+\.[\d]*\.?/', $block);
            if ($isPoint) {
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

    $subClause = function ($num, $text) use ($pdf, $cW) {
        $pdf->SetFont('Helvetica', 'B', 8.5);
        $pdf->SetTextColor(51, 51, 51);
        $pdf->SetX(20);
        $pdf->Write(4.5, $num . '  ');
        $pdf->SetFont('Helvetica', '', 8.5);
        $pdf->MultiCell($cW - ($pdf->GetX() - 20), 4.5, $text, 0, 'J');
        $pdf->Ln(2);
    };

    $divider = function () use ($pdf, $cW) {
        $pdf->SetDrawColor(220, 220, 220);
        $pdf->SetLineWidth(0.2);
        $pdf->Line(20, $pdf->GetY(), 20 + $cW, $pdf->GetY());
        $pdf->Ln(3);
    };

    // ── Cabecera del contrato ──────────────────────────────────
    $pdf->SetFillColor(30, 30, 30);
    $pdf->RoundedRect(20, $pdf->GetY(), $cW, 16, 3, '1111', 'F');
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Helvetica', 'B', 12);
    $pdf->SetX(22);
    $pdf->Cell($cW - 4, 8, $contractTitle, 0, 1, 'C');
    $pdf->SetFont('Helvetica', '', 8.5);
    $pdf->SetX(22);
    $pdf->Cell($cW - 4, 5, 'Tictac Comunicacion - Hacemos el Marketing que funciona', 0, 1, 'C');
    $pdf->SetTextColor(51, 51, 51);
    $pdf->Ln(4);

    $startY = $pdf->GetY();
    $half   = ($cW - 5) / 2;

    $pdf->SetFillColor(255, 255, 255);
    $pdf->SetDrawColor(220, 220, 220);
    $pdf->RoundedRect(20, $startY, $half, 26, 2, '1111', 'DF');
    $pdf->SetXY(23, $startY + 3);
    $pdf->SetFont('Helvetica', 'B', 8.5);
    $pdf->SetTextColor(215, 33, 115);
    $pdf->Cell($half - 6, 4, 'DATOS DEL CONTRATO', 0, 1, 'L');
    $pdf->SetDrawColor(215, 33, 115);
    $pdf->SetLineWidth(0.3);
    $pdf->Line(23, $pdf->GetY(), 23 + $half - 6, $pdf->GetY());
    $pdf->Ln(2);

    $pdf->SetTextColor(51, 51, 51);
    $pdf->SetX(23);
    $pdf->SetFont('Helvetica', 'B', 8.5);
    $pdf->Cell(30, 4, 'N Contrato:', 0, 0);
    $pdf->SetFont('Helvetica', '', 8.5);
    $pdf->Cell(0, 4, $contractId, 0, 1);

    $pdf->SetX(23);
    $pdf->SetFont('Helvetica', 'B', 8.5);
    $pdf->Cell(30, 4, 'Fecha:', 0, 0);
    $pdf->SetFont('Helvetica', '', 8.5);
    $pdf->Cell(0, 4, 'En Cordoba a ' . $contractDate, 0, 1);

    $pdf->SetX(23);
    $pdf->SetFont('Helvetica', 'B', 8.5);
    $pdf->Cell(30, 4, 'Valido hasta:', 0, 0);
    $pdf->SetFont('Helvetica', '', 8.5);
    $pdf->Cell(0, 4, !empty($contrato['valido_hasta']) ? date('d/m/Y', strtotime($contrato['valido_hasta'])) : '', 0, 1);

    $rightX = 20 + $half + 5;
    $pdf->SetFillColor(255, 255, 255);
    $pdf->SetDrawColor(220, 220, 220);
    $pdf->RoundedRect($rightX, $startY, $half, 26, 2, '1111', 'DF');
    $pdf->SetXY($rightX + 3, $startY + 3);
    $pdf->SetFont('Helvetica', 'B', 8.5);
    $pdf->SetTextColor(215, 33, 115);
    $pdf->Cell($half - 6, 4, 'EL CLIENTE', 0, 1, 'L');
    $pdf->SetDrawColor(215, 33, 115);
    $pdf->SetLineWidth(0.3);
    $pdf->Line($rightX + 3, $pdf->GetY(), $rightX + 3 + $half - 6, $pdf->GetY());
    $pdf->Ln(2);

    $pdf->SetTextColor(51, 51, 51);
    $pdf->SetFont('Helvetica', '', 8.5);
    foreach (explode("\n", $contractToInfo) as $line) {
        $line = trim($line);
        if (!$line) continue;
        $pdf->SetX($rightX + 3);
        $pdf->MultiCell($half - 6, 3.8, $line, 0, 'L');
    }

    $pdf->SetY($startY + 30);
    $pdf->Ln(4);

    // ── Datos del proveedor según tipo de contrato ─────────────
    if ($esKitDigital) {
        $provNombre    = 'PROYECTO TRESS AZAFATAS, S.L.';
        $provNif       = 'B56028293';
        $provDomicilio = 'C/ Conquistador Benito de Banos, 13 3 2. 14007, Cordoba.';
        $provEmail     = 'hola@tictac-comunicacion.es';
    } else {
        $provNombre    = 'TIC TAC COMUNICACION DIGITAL, S.L.';
        $provNif       = 'B09912478';
        $provDomicilio = 'C/ Escultor Ramon Barba, N 1 - Bloque F - 1-2  14012 (Cordoba)';
        $provEmail     = 'hola@tictac-comunicacion.es';
    }

    $introText  = "De una parte, \"El Proveedor\", actualmente y sin perjuicio de otras actividades que ahora o en el futuro pudiera acometer por si o a traves de empresas filiales o participadas, se dedica a la prestacion de servicios relacionados con las tecnologias de la informacion y la informatica.\n\n";
    $introText .= "Sus datos identificativos son los siguientes:\n\n";
    $introText .= "  Denominacion social: " . $provNombre . "\n";
    $introText .= "  NIF: " . $provNif . "\n";
    $introText .= "  Domicilio: " . $provDomicilio . "\n";
    $introText .= "  Correo: " . $provEmail . "\n\n";
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

    $pdf->SetFillColor(30, 30, 30);
    $pdf->RoundedRect(20, $pdf->GetY(), $cW, 8, 2, '1111', 'F');
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Helvetica', 'B', 11);
    $pdf->SetX(22);
    $pdf->Cell($cW - 4, 8, 'CLAUSULAS', 0, 1, 'C');
    $pdf->SetTextColor(51, 51, 51);
    $pdf->Ln(3);

    // ==============================================================
    // DECISIÓN: qué cláusulas mostrar
    // ==============================================================

    if ($clausulasPersonalizadas) {

        // ── CLÁUSULAS PERSONALIZADAS (editor WYSIWYG) ──────────────
        $html = $contrato['clausulas_html'];
        $html = str_replace(["\r\n", "\r"], "\n", $html);
        $html = preg_replace('/<\/h[1-6]>/i', "</h_>\n", $html);
        $html = preg_replace('/<\/p>/i', "</p>\n", $html);
        $html = preg_replace('/<\/li>/i', "</li>\n", $html);
        $html = preg_replace('/<br\s*\/?>/i', "\n", $html);

        $blocks = preg_split('/\n+/', $html);
        $tablaInsertada = false;

        foreach ($blocks as $block) {
            $block = trim($block);
            if ($block === '' || $block === '&nbsp;') continue;

            $rawTextClean = html_entity_decode(strip_tags($block), ENT_QUOTES, 'UTF-8');
            $rawTextClean = trim($rawTextClean);

            if (!$tablaInsertada && preg_match('/^4\.\s+/i', $rawTextClean)) {
                dibujarTablaItems($pdf, $items, $stX, $cArt, $cCant, $cTar, $cTot, $tW);
                dibujarTotalesTabla($pdf, $stX, $tW, $cTot, $subtotal, $iva, $ivaAmt, $seg, $segAmt, $totalFinal, $items, $descuentoGlob);
                $tablaInsertada = true;
            }

            $isHeading    = (bool) preg_match('/<h[1-3][^>]*>/i', $block);
            $startsStrong = (bool) preg_match('/^\s*(<[^>]+>)*\s*<(strong|b)>/i', $block);
            $isListItem   = (bool) preg_match('/<li[^>]*>/i', $block);

            $rawText = html_entity_decode(strip_tags($block), ENT_QUOTES, 'UTF-8');
            $rawText = str_replace("\xC2\xA0", ' ', $rawText);
            $rawText = preg_replace('/[ \t]+/', ' ', $rawText);
            $rawText = trim($rawText);
            if ($rawText === '') continue;

            $remaining = preg_replace('/<\/?(?:p|li|ul|ol|h[1-6])[^>]*>/i', '', $block);
            $remaining = trim($remaining);

            $pattern = '/(<(?:strong|b)[^>]*>)(.*?)(<\/(?:strong|b)>)/is';
            preg_match_all($pattern, $remaining, $boldMatches, PREG_OFFSET_CAPTURE);

            if (empty($boldMatches[0])) {
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
                $segs = array();
                $pos = 0;
                $fullText = $remaining;

                foreach ($boldMatches[0] as $idx => $match) {
                    $matchStart = $match[1];
                    if ($matchStart > $pos) {
                        $before = html_entity_decode(strip_tags(substr($fullText, $pos, $matchStart - $pos)), ENT_QUOTES, 'UTF-8');
                        $before = preg_replace('/[ \t]+/', ' ', str_replace("\xC2\xA0", ' ', $before));
                        if (trim($before) !== '') $segs[] = array('text' => $before, 'bold' => false);
                    }
                    $boldText = html_entity_decode(strip_tags($boldMatches[2][$idx][0]), ENT_QUOTES, 'UTF-8');
                    $boldText = preg_replace('/[ \t]+/', ' ', str_replace("\xC2\xA0", ' ', $boldText));
                    if (trim($boldText) !== '') $segs[] = array('text' => $boldText, 'bold' => true);
                    $pos = $matchStart + strlen($match[0]);
                }

                if ($pos < strlen($fullText)) {
                    $after = html_entity_decode(strip_tags(substr($fullText, $pos)), ENT_QUOTES, 'UTF-8');
                    $after = preg_replace('/[ \t]+/', ' ', str_replace("\xC2\xA0", ' ', $after));
                    if (trim($after) !== '') $segs[] = array('text' => $after, 'bold' => false);
                }

                $allBold = count($segs) === 1 && $segs[0]['bold'];

                if ($allBold) {
                    $pdf->SetFont('Helvetica', 'B', 9.5);
                    $pdf->SetTextColor(33, 33, 33);
                    $pdf->SetX(20);
                    $pdf->MultiCell($cW, 5, $segs[0]['text'], 0, 'L');
                    $pdf->Ln(1);
                } else {
                    $pdf->SetTextColor(51, 51, 51);
                    foreach ($segs as $seg) {
                        $pdf->SetFont('Helvetica', $seg['bold'] ? 'B' : '', 8.5);
                        $pdf->Write(4.5, $seg['text']);
                    }
                    $pdf->Ln(0);
                    $pdf->MultiCell($cW, 4.5, '', 0, 'L');
                }
                $pdf->Ln(2);
            }
        }
        $pdf->Ln(2);

    } elseif ($esKitDigital) {

        // ── CLÁUSULAS KIT DIGITAL ──────────────────────────────────

        $sTitle('1. OBJETO');
        $bodyBlocks("El objeto del Contrato consiste en la prestacion de servicios por parte del Proveedor a cambio del pago de un precio por parte del Cliente, en los terminos establecidos en el mismo.\n\nLas solicitudes de modificacion del contrato se haran siempre por escrito, remitido por correo ordinario o electronico hola@tictac-comunicacion.es. Se ejecutaran siempre que sea posible y el cliente debera asumir los costes en los que el Proveedor haya incurrido, tras dicha modificacion del contrato.\n\nEl Cliente acepta que el Proveedor pueda publicar su imagen corporativa, nombre comercial y sitio web dentro de \"casos de exito\" o \"seccion clientes\" de la web de Tic Tac Comunicacion (www.tictac-comunicacion.es), asi como la firma de la Empresa Tic Tac Comunicacion en Footer (Pie de Pagina de la web del Cliente).");
        $divider();

        $sTitle('2. SERVICIOS DEL PROVEEDOR');
        $bodyBlocks("2.1. Los Servicios del proyecto vendran descritos en la hoja de encargo adjunta que debera ser firmada por el cliente y por la empresa que provee el servicio.");
        $divider();

        $sTitle('3. VALORACION DE LOS SERVICIOS, FACTURACION, FORMA DE PAGO, IMPUESTOS Y GASTOS');
        $bodyBlocks("3.1. La valoracion economica sera actualizada anualmente por el Proveedor, en funcion de las nuevas tarifas que el Proveedor establezca.\n\n3.2. El precio de los Servicios sera abonado por el Cliente al Proveedor en el momento de la formalizacion del Contrato, con caracter previo al inicio de la prestacion de los Servicios, mediante transferencia a la cuenta numero que el proveedor designe para tal efecto.\n\n3.3. El precio expresado a continuacion contienen los impuestos indirectos desglosados a la fecha de la firma actual. El precio de los productos o servicios contratados vendran desglosados a continuacion:");
        dibujarTablaItems($pdf, $items, $stX, $cArt, $cCant, $cTar, $cTot, $tW);
        dibujarTotalesTabla($pdf, $stX, $tW, $cTot, $subtotal, $iva, $ivaAmt, $seg, $segAmt, $totalFinal, $items, $descuentoGlob);
        $bodyBlocks("3.4. Cronograma de pago de los servicios (de no especificarse otras condiciones particulares). Se emitira un cobro mensual en el siguiente numero de cuenta bancaria facilitado por el Cliente.\n\n3.5. Cualquier revision o adiciones a los servicios descritos en el contrato seran facturados como Servicios Adicionales no incluidos en el presupuesto estimado arriba especificado. Tales servicios adicionales incluiran, pero no se limitaran a, cambios en la dimension (cantidad) del trabajo, cambios en la complejidad de cualquier elemento involucrado en los Proyectos, y cualquier cambio efectuado despues de la aprobacion de cada etapa del diseno, documentacion, etc.\n\nEl Proveedor debera mantener informado al Cliente de los servicios adicionales requeridos y solicitara la aprobacion del Cliente para aquellos servicios adicionales que afecten y excedan los honorarios estimados y reflejados anteriormente.");
        $divider();

        $sTitle('4. RESPONSABILIDAD DEL CLIENTE');
        $bodyBlocks("4.1. El Cliente proveera informacion fehaciente y completa y materiales al Proveedor, y sera responsable de la exactitud y completitud de toda la informacion y los materiales provistos.\n\nEl Cliente garantiza que todo material provisto al Proveedor no afecta los derechos de autor de terceros. El Cliente indemnizara, defendera y mantendra fuera de todo litigio al Proveedor de y contra cualquier reclamo, juicio, dano y perjuicio, incluyendo los gastos de defensa, que surgieren de cualquier reclamo en relacion con terceros cuyos derechos hayan sido o sean violados o infringidos debido al material provisto por el Cliente.\n\n4.3. Todo texto e informacion aportado por el Cliente se entregara al Proveedor en formato digital, preparado para su insercion en los Proyectos. Cuando algun material fuere provisto por el Cliente en otro soporte, tal como fotografias, ilustraciones u otro material visual, textos en papel, etc. debera ser de calidad profesional y dispuesto para su digitalizacion sin mas preparacion o alteracion. Este proceso (escaneado, OCR, tipeado, etc.) sera presupuestado como un servicio suplementario. El Cliente abonara todos los gastos que surgieren en relacion con los materiales entregados que no cumplan con tales estandares.");
        $divider();

        $sTitle('5. DERECHOS Y PROPIEDAD');
        $bodyBlocks("5.1. Todos los servicios provistos por el Proveedor y aprobados bajo este contrato seran para uso exclusivo del Cliente mas alla de su uso promocional propio del Proveedor.");
        $divider();

        $sTitle('6. DURACION DEL CONTRATO');
        $bodyBlocks("6.1. El Contrato tendra una vigencia minima de un (1) ano, contada a partir de la fecha de la firma del presente Contrato.\n\n6.2. El Cliente podra rescindir el presente Contrato, notificandoselo por escrito al Proveedor con al menos treinta (30) dias de antelacion a la fecha de vencimiento inicial, o, en su caso, de cualquiera de sus prorrogas.\n\nEn todo caso, la prorroga del Contrato no significara que se mantenga el mismo precio por los Servicios, sino que el precio sera fijado anualmente por el Proveedor, segun las tarifas que el mismo establezca para cada ano, que se pondran oportunamente en conocimiento del Cliente.");
        $divider();

        $sTitle('7. EXTINCION DEL CONTRATO');
        $bodyBlocks("7.1. El Contrato se extinguira por las causas generales establecidas en la legislacion vigente.\n\n7.2. En todo caso, la extincion del Contrato antes de la finalizacion del periodo inicial o de cualquiera de sus prorrogas, no dara lugar a devolucion alguna del precio abonado al Proveedor.\n\n7.3. La no acreditacion del pago del precio sera causa automatica de resolucion del Contrato, sin perjuicio de la posible reclamacion de danos y perjuicios y abono de intereses, que podra ejercitar el Proveedor si lo estima conveniente.");
        $divider();

        $sTitle('8. NATURALEZA DE LA RELACION');
        $bodyBlocks("8.1. El presente Contrato tiene caracter mercantil y se regira por sus propias clausulas, y en lo que en ellas no estuviere previsto, por las disposiciones del Codigo de Comercio, leyes especiales y usos mercantiles, y en su defecto, por el Codigo Civil.");
        $divider();

        $sTitle('9. PROTECCION DE DATOS DE CARACTER PERSONAL');
        $bodyBlocks("9.1. Debido a la naturaleza de los Servicios, el Proveedor puede tener que realizar tratamientos automatizados de ficheros del Cliente que contengan datos de caracter personal. En cualquier caso, sera el Cliente quien decida sobre la finalidad, contenido y uso del tratamiento de los datos, limitandose el Proveedor a utilizar dichos datos, unica y exclusivamente para los fines que figuran en el Contrato y siempre por cuenta del Cliente.\n\n9.2. El Cliente unicamente permitira el acceso a datos de caracter personal al Proveedor cuando sea necesario para la ejecucion del objeto del Contrato.\n\n9.3. El Cliente afirma y garantiza que los datos han sido recogidos de acuerdo a lo establecido en la LOPD, asi como que cumple todas las obligaciones establecidas en la LOPD. El Proveedor se exonera de toda responsabilidad que pueda surgir en caso de reclamacion por incumplimiento de lo anteriormente garantizado. En caso de que se declare la responsabilidad del Proveedor mediante un procedimiento judicial, administrativo o arbitral, el Cliente queda obligado a indemnizar al Proveedor por los danos y perjuicios que se le causen.");
        $divider();

        $sTitle('10. CONFIDENCIALIDAD');
        $bodyBlocks("10.1. El Proveedor considerara confidencial toda la informacion relacionada con los Servicios, y que obtenga durante la prestacion de los mismos, salvo que dicha informacion le fuera conocida previamente o hubiera sido divulgada publicamente, bien con anterioridad a la realizacion de los trabajos, o posteriormente a esta.");
        $divider();

        $sTitle('11. RESPONSABILIDAD DEL PROVEEDOR');
        $bodyBlocks("11.1. Salvo en los casos de culpa grave o dolo, la responsabilidad total del Proveedor en relacion con el Contrato estara sujeta a las limitaciones siguientes:\n\n- La responsabilidad total que, por cualquier concepto, pueda ser obtenida del Proveedor por el Cliente en relacion con los danos directos causados al Cliente a consecuencia de los actos u omisiones realizados por el Proveedor en el ambito del Contrato no excedara, en su conjunto, de la cantidad correspondiente al precio abonado al Proveedor por el Cliente por los Servicios durante la ultima anualidad.\n\n- El Proveedor no sera responsable, en ningun caso, de los danos que puedan ser calificados como danos indirectos, consecuenciales, perdida de beneficio o de resultados previstos, negocio, ingresos, clientes, datos, imagen, reputacion comercial en el mercado, asi como de los derivados de su imposibilidad de prestar los Servicios por causas que estuvieran fuera de su control, como, a modo de ejemplo, equipos descatalogados y rotura de existencias de proveedores.");
        $divider();

        $sTitle('12. ACTUALIZACION');
        $bodyBlocks("12.1. En el caso de que alguna o algunas de las clausulas del Contrato pasen a ser invalidas, ilegales o inejecutables en virtud de alguna norma juridica, se consideraran ineficaces en la medida que corresponda, pero en lo demas, este Contrato conservara su validez.\n\n12.2. Para ese caso, las Partes acuerdan sustituir la clausula o clausulas afectadas por otra u otras que tengan los efectos economicos mas semejantes a los de las sustituidas. CONTRATO UNICO");
        $divider();

        $sTitle('13. NOTIFICACIONES Y REQUERIMIENTOS');
        $bodyBlocks("13.1. Toda notificacion o requerimiento que traiga su causa del Contrato se debera remitir por escrito a la otra Parte, bien por E-mail, bien personalmente, o por mensajero o correo certificado con acuse de recibo a portes pagados a las personas y direcciones que aparecen en el apartado \"Reunidos\" del presente Contrato, que actuaran de interlocutores, a estos efectos, o a cualesquiera otras que, en su caso, se determinen y comuniquen en el futuro.");
        $divider();

        $sTitle('14. JURISDICCION Y COMPETENCIA');
        $bodyBlocks("14.1. Las Partes, con renuncia expresa a cualquier otro fuero que pudiera corresponderles, se someten para cuantos asuntos litigiosos pudieran derivarse en todo lo referente a la interpretacion, aplicacion o cumplimiento y ejecucion del presente Contrato, a la jurisdiccion y competencia de los Juzgados y Tribunales de Cordoba.\n\n14.2. Y para que asi conste, y en prueba de conformidad y aceptacion de todo cuanto antecede, las Partes firman el presente Contrato por duplicado ejemplar y a un solo efecto en la fecha y lugar indicados en el encabezamiento");
        $divider();

        // ── NOTAS ADHERIDAS (Kit Digital) ──────────────────────────
        $pdf->Ln(2);
        $notaKDY = $pdf->GetY();
        $pdf->SetFillColor(255, 248, 225);
        $pdf->SetDrawColor(255, 248, 225);
        $pdf->RoundedRect(20, $notaKDY, $cW, 7, 2, '1111', 'F');
        $pdf->SetXY(22, $notaKDY + 1.5);
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->SetTextColor(100, 80, 0);
        $pdf->Cell($cW - 4, 5, 'NOTAS ADHERIDAS AL CONTRATO: DETALLES FACTURACION', 0, 1, 'L');
        $pdf->SetTextColor(51, 51, 51);
        $pdf->Ln(2);

        $detallesFacturacion = "Para la facturacion de la tarifa de los servicios establecidas en el presente contrato se emitira remesa bancaria, con caracter mensual de la parte proporcional del total del servicio entre los meses contratados, al numero de cuenta facilitado por el Cliente previa autorizacion mediante firma de documento SEPA.\n\n" .
            "Para la parte proporcional correspondiente a la parte fija de la tarifa establecida para el servicio, las remesas bancarias se emitiran por anticipado a partir de los dias uno de cada mes, y de manera recurrente hasta la finalizacion del presente contrato.\n\n" .
            "Para la parte correspondiente a la parte variable de la tarifa (3% comision por venta PVP, IVA incluido) establecida para el servicio, las remesas bancarias se emitiran a mes vencido a partir de los dias uno de cada mes, y de manera recurrente hasta la finalizacion del presente contrato.";

        $pdf->SetX(22);
        $pdf->SetFont('Helvetica', '', 8.5);
        $pdf->MultiCell($cW - 4, 4.3, $detallesFacturacion, 0, 'J');
        $pdf->Ln(6);

        // ── BLOQUE KIT DIGITAL ─────────────────────────────────────
        if (($pdf->GetY() + 20) > $pdf->getPageHeight() - 40) $pdf->AddPage();

        $kdTitleY = $pdf->GetY();
        $pdf->SetFillColor(255, 193, 7);
        $pdf->RoundedRect(20, $kdTitleY, $cW, 8, 2, '1111', 'F');
        $pdf->SetXY(22, $kdTitleY + 1.5);
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->SetTextColor(51, 40, 0);
        $pdf->Cell($cW - 4, 5, 'KIT DIGITAL', 0, 1, 'L');
        $pdf->SetTextColor(51, 51, 51);
        $pdf->Ln(3);

        $kdHtml = (isset($contrato['clausulas_kit_digital_html']) && trim(strip_tags($contrato['clausulas_kit_digital_html'])) !== '')
            ? $contrato['clausulas_kit_digital_html']
            : null;

        if ($kdHtml) {
            $kdText = pdf_text_plain($kdHtml);
            $pdf->SetX(20);
            $pdf->SetFont('Helvetica', '', 8.5);
            $pdf->MultiCell($cW, 4.5, $kdText, 0, 'J');
        } else {
            $pdf->SetX(20);
            $pdf->SetFont('Helvetica', '', 8.5);
            $pdf->MultiCell($cW, 4.3,
                "El cliente es beneficiario de subvencion de Kit Digital de 2000 euros que se decrementara del precio de las correspondientes partidas o soluciones digitalizadoras. El cliente esta obligado a cumplir con las premisas tributarias que genera la subvencion durante el ano siguiente para mantener la subvencion y siempre segun la Orden TDF/435/2024, de 9 de mayo, por la que se modifica la Orden ETD/1498/2021, de 29 de diciembre, por la que se aprueban las bases reguladoras de la concesion de ayudas para la digitalizacion de pequenas empresas, microempresas y personas en situacion de autoempleo, en el marco de la Agenda Espana Digital 2025, el Plan de Digitalizacion PYMEs 2021-2025 y el Plan de Recuperacion, Transformacion y Resiliencia de Espana -Financiado por la Union Europea- Next Generation EU (Programa Kit Digital), publicada en Boletin Oficial del Estado a fecha 11 de Mayo de 2024.",
                0, 'J');
            $pdf->Ln(3);

            $pdf->SetX(20);
            $pdf->MultiCell($cW, 4.3,
                "No seran subvencionables el Impuesto sobre el Valor Anadido que tendra que ser abonado por el beneficiario y cuya remesa se enviara durante los tres meses siguientes a la validacion del Acuerdo de Prestacion de Soluciones.",
                0, 'J');
            $pdf->Ln(3);

            $pdf->SetX(20);
            $pdf->MultiCell($cW, 4.3,
                "En caso de ser desestimada la ayuda (Kit Digital) por cualquier motivo ajeno a Proyecto Tress Azafatas sera el cliente el que asuma el obligado cumplimento del pago del servicio prestado (2000 euros, IVA no incluido). La forma de pago se establece con emision de remesa bancaria previamente autorizada mediante firma de documento SEPA por parte del cliente.",
                0, 'J');
            $pdf->Ln(3);

            $pdf->SetX(20);
            $pdf->MultiCell($cW, 4.3,
                "En el caso de que el cliente decida desistir de la ayuda dentro del plazo de los 12 meses establecidos por Kit Digital como prestacion del servicio, sera el cliente el que tenga el obligado cumplimiento de hacerse cargo de la cuantia de los trabajos realizados hasta dicho momento por el agente digitalizador, PROYECTO TRESS AZAFATAS en este caso. Se calculara la parte proporcional del total del servicio que ira desde el inicio del acuerdo de prestacion hasta la fecha de comunicacion de renuncia de la ayuda. Una vez el cliente haya abonado la citada cuantia de los trabajos realizados se procedera a la aceptacion de la renuncia por parte de PROYECTO TRESS AZAFATAS como agente digitalizador.",
                0, 'J');
        }
        $pdf->Ln(8);

    } else {

        // ── CLÁUSULAS ESTÁNDAR NORMALES ────────────────────────────
        $sTitle('1. OBJETO');
        $bodyBlocks("El objeto del Contrato consiste en la prestacion de servicios por parte del Proveedor a cambio del pago de un precio por parte del Cliente, en los terminos establecidos en el mismo.\n\nLas solicitudes de modificacion del contrato se haran siempre por escrito, remitido por correo ordinario o electronico hola@tictac-comunicacion.es. Se ejecutaran siempre que sea posible y el cliente debera asumir los costes en los que el Proveedor haya incurrido, tras dicha modificacion del contrato.\n\nEl Cliente acepta que el Proveedor pueda publicar su imagen corporativa, nombre comercial y sitio web dentro de \"casos de exito\" o \"seccion clientes\" de la web de Tic Tac Comunicacion (www.tictac-comunicacion.es), asi como la firma de la Empresa Tic Tac Comunicacion en Footer (Pie de Pagina de la web del Cliente).");
        $divider();

        $sTitle('2. SERVICIOS DEL PROVEEDOR');
        $subClause('2.1.', "Los Servicios del proyecto vendran descritos en la hoja de encargo adjunta que debera ser firmada por el cliente y por la empresa que provee el servicio.\n\nEn relacion al diseno web, si el cliente ha contratado este servicio y procede, el Proveedor presentara al cliente hasta 3 bocetos en soporte fisico o digital, con el objeto de definir el diseno de forma y apariencia generica y de estructura general del Proyecto y/o sus piezas accesorias si las hubiese.\n\nEl Cliente ha de firmar el Boceto escogido, todos los cambios a partir del momento de la firma, conllevaran costos adicionales.");
        $subClause('2.1.1.', 'Realizacion de material especial tal como: tipografia no convencional, caligrafia, mapas, diagramas, graficos, vectores o fotomontajes.');
        $subClause('2.1.2.', 'Preparacion de material existente para su reproduccion tales como: redibujo parcial o total, conversion a lineas, escaneado y retoque de imagenes, tipeados, etc.');
        $subClause('2.1.3.', 'Seguimiento de la produccion.');
        $subClause('2.1.4.', "Recuperacion de informacion, siempre que tecnicamente sea posible.\n\nTodas estas actuaciones se realizaran siempre dentro de horas habiles de trabajo, segun el calendario laboral del Proveedor. El horario de trabajo de los tecnicos del Proveedor sera de lunes a viernes de 9:00 a 17:00, salvo en los meses de Julio y agosto que sera de 9:00 a 15:00.\n\nEl Proveedor facilitara los telefonos y direcciones de correo electronico necesarias para el reporte de las incidencias.");
        $subClause('2.1.5.', 'La correccion de errores imputables a la manipulacion a traves de los Programas de gestion de contenidos por personal no autorizado expresamente por el Proveedor.');
        $subClause('2.1.6.', 'Las tareas necesarias para restablecer la situacion anterior derivada de operaciones incorrectas por parte del Cliente (o de sus dependientes o colaboradores) que ocasionen perdidas de informacion, destruccion o desorganizacion de ficheros, y situaciones analogas.');
        $subClause('2.1.7.', 'La reparacion de danos causados por virus o defectos de otros programas no relacionados en el Contrato, o en anexo posterior.');
        $subClause('2.1.8.', 'La reparacion de danos y malfuncionamientos o el aumento de duracion de los Servicios causados por accidentes, uso indebido, catastrofes, abusos, alteraciones, conexiones, sustitucion de elementos o software no suministrado y/o recomendado por el Proveedor, o el empleo de los Equipos para trabajos distintos de los que fueron disenados.');
        $divider();

        $sTitle('3. VALORACION DE LOS SERVICIOS, FACTURACION, FORMA DE PAGO, IMPUESTOS Y GASTOS');
        $bodyBlocks("3.1. La valoracion economica sera actualizada anualmente por el Proveedor, en funcion de las nuevas tarifas que el Proveedor establezca.\n\n3.2. El precio de los Servicios sera abonado por el Cliente al Proveedor en el momento de la formalizacion del Contrato, con caracter previo al inicio de la prestacion de los Servicios, mediante transferencia a la cuenta numero que el proveedor designe para tal efecto.\n\n3.3. El precio expresado a continuacion contienen los impuestos indirectos desglosados a la fecha de la firma actual:");
        dibujarTablaItems($pdf, $items, $stX, $cArt, $cCant, $cTar, $cTot, $tW);
        dibujarTotalesTabla($pdf, $stX, $tW, $cTot, $subtotal, $iva, $ivaAmt, $seg, $segAmt, $totalFinal, $items, $descuentoGlob);
        $bodyBlocks("3.4. Se emitira un cobro mensual en el siguiente numero de cuenta bancaria facilitado por el Cliente.\n\n3.5. Cualquier revision o adiciones a los servicios descritos en el contrato seran facturados como Servicios Adicionales no incluidos en el presupuesto estimado arriba especificado.\n\nTales servicios adicionales incluiran, pero no se limitaran a, cambios en la dimension (cantidad) del trabajo, cambios en la complejidad de cualquier elemento involucrado en los Proyectos, y cualquier cambio efectuado despues de la aprobacion de cada etapa del diseno, documentacion, etc.\n\nEl Proveedor debera mantener informado al Cliente de los servicios adicionales requeridos y solicitara la aprobacion del Cliente para aquellos servicios adicionales que afecten y excedan los honorarios estimados y reflejados anteriormente.");
        $divider();

        $sTitle('4. RESPONSABILIDAD DEL CLIENTE');
        $bodyBlocks("4.1. El Cliente proveera informacion fehaciente y completa y materiales al Proveedor, y sera responsable de la exactitud y completitud de toda la informacion y los materiales provistos.\n\nEl Cliente garantiza que todo material provisto al Proveedor no afecta los derechos de autor de terceros.\n\nEl Cliente indemnizara, defendera y mantendra fuera de todo litigio al Proveedor de y contra cualquier reclamo, juicio, dano y perjuicio, incluyendo los gastos de defensa, que surgieren de cualquier reclamo en relacion con terceros cuyos derechos hayan sido o sean violados o infringidos debido al material provisto por el Cliente.\n\n4.2. El Cliente en caso haber realizado alguna modificacion por su cuenta y por ello, haber desconfigurado la web, sera el mismo Cliente quien responda por el costo del arreglo que le sera presupuestado por el tecnico del Proveedor.\n\n4.3. Todo texto e informacion aportado por el Cliente se entregara al Proveedor en formato digital, preparado para su insercion en los Proyectos. Cuando algun material fuere provisto por el Cliente en otro soporte, debera ser de calidad profesional y dispuesto para su digitalizacion sin mas preparacion o alteracion. Este proceso (escaneado, OCR, tipeado, etc.) sera presupuestado como un servicio suplementario.");
        $divider();

        $sTitle('5. DERECHOS Y PROPIEDAD');
        $bodyBlocks("5.1. Todos los servicios provistos por el Proveedor y aprobados bajo este contrato seran para uso exclusivo del Cliente mas alla de su uso promocional propio del Proveedor.\n\n5.2. El Proveedor se compromete a almacenar los originales durante 6 meses a partir de la finalizacion del Proyecto. Una vez concluido dicho periodo, no garantizara su manutension.\n\n5.3. El Dominio (direccion web) pertenecera al Cliente, siendo este su propietario en todo momento, por lo que podra ser solicitado en cualquier momento.\n\n5.4. Una vez finalizado el pago total del monto acordado, el Cliente, pasara a ser propietario de la Web.");
        $divider();

        $sTitle('6. DURACION DEL CONTRATO');
        $bodyBlocks("6.1. El Contrato tendra una vigencia minima de un (1) ano, contada a partir de la fecha de la firma del presente Contrato.\n\n6.2. El Cliente podra rescindir el presente Contrato, notificandoselo por escrito al Proveedor con al menos treinta (30) dias de antelacion a la fecha de vencimiento inicial, o, en su caso, de cualquiera de sus prorrogas.\n\nEn todo caso, la prorroga del Contrato no significara que se mantenga el mismo precio por los Servicios, sino que el precio sera fijado anualmente por el Proveedor, segun las tarifas que el mismo establezca para cada ano, que se pondran oportunamente en conocimiento del Cliente.");
        $divider();

        $sTitle('7. EXTINCION DEL CONTRATO');
        $bodyBlocks("7.1. El Contrato se extinguira por las causas generales establecidas en la legislacion vigente.\n\n7.2. En todo caso, la extincion del Contrato antes de la finalizacion del periodo inicial o de cualquiera de sus prorrogas, no dara lugar a devolucion alguna del precio abonado al Proveedor.\n\n7.3. La no acreditacion del pago del precio sera causa automatica de resolucion del Contrato, sin perjuicio de la posible reclamacion de danos y perjuicios y abono de intereses, que podra ejercitar el Proveedor si lo estima conveniente.");
        $divider();

        $sTitle('8. NATURALEZA DE LA RELACION');
        $bodyBlocks("8.1. El presente Contrato tiene caracter mercantil y se regira por sus propias clausulas, y en lo que en ellas no estuviere previsto, por las disposiciones del Codigo de Comercio, leyes especiales y usos mercantiles, y en su defecto, por el Codigo Civil.");
        $divider();

        $sTitle('9. PROTECCION DE DATOS DE CARACTER PERSONAL');
        $bodyBlocks("9.1. Debido a la naturaleza de los Servicios, el Proveedor puede tener que realizar tratamientos automatizados de ficheros del Cliente que contengan datos de caracter personal. En cualquier caso, sera el Cliente quien decida sobre la finalidad, contenido y uso del tratamiento de los datos, limitandose el Proveedor a utilizar dichos datos, unica y exclusivamente para los fines que figuran en el Contrato y siempre por cuenta del Cliente.\n\n9.2. El Cliente unicamente permitira el acceso a datos de caracter personal al Proveedor cuando sea necesario para la ejecucion del objeto del Contrato.\n\n9.3. El Cliente afirma y garantiza que los datos han sido recogidos de acuerdo a lo establecido en la LOPD, asi como que cumple todas las obligaciones establecidas en la LOPD. El Proveedor se exonera de toda responsabilidad que pueda surgir en caso de reclamacion por incumplimiento de lo anteriormente garantizado.");
        $divider();

        $sTitle('10. CONFIDENCIALIDAD');
        $bodyBlocks("10.1. El Proveedor considerara confidencial toda la informacion relacionada con los Servicios, y que obtenga durante la prestacion de los mismos, salvo que dicha informacion le fuera conocida previamente o hubiera sido divulgada publicamente, bien con anterioridad a la realizacion de los trabajos, o posteriormente a esta.");
        $divider();

        $sTitle('11. RESPONSABILIDAD DEL PROVEEDOR');
        $bodyBlocks("11.1. Salvo en los casos de culpa grave o dolo, la responsabilidad total del Proveedor en relacion con el Contrato estara sujeta a las limitaciones siguientes:\n\n- La responsabilidad total que, por cualquier concepto, pueda ser obtenida del Proveedor por el Cliente en relacion con los danos directos causados al Cliente a consecuencia de los actos u omisiones realizados por el Proveedor en el ambito del Contrato no excedara, en su conjunto, de la cantidad correspondiente al precio abonado al Proveedor por el Cliente por los Servicios durante la ultima anualidad.\n\n- El Proveedor no sera responsable, en ningun caso, de los danos que puedan ser calificados como danos indirectos, consecuenciales, perdida de beneficio o de resultados previstos, negocio, ingresos, clientes, datos, imagen, reputacion comercial en el mercado, asi como de los derivados de su imposibilidad de prestar los Servicios por causas que estuvieran fuera de su control.");
        $divider();

        $sTitle('12. ACTUALIZACION');
        $bodyBlocks("12.1. En el caso de que alguna o algunas de las clausulas del Contrato pasen a ser invalidas, ilegales o inejecutables en virtud de alguna norma juridica, se consideraran ineficaces en la medida que corresponda, pero en lo demas, este Contrato conservara su validez.\n\n12.2. Para ese caso, las Partes acuerdan sustituir la clausula o clausulas afectadas por otra u otras que tengan los efectos economicos mas semejantes a los de las sustituidas. CONTRATO UNICO");
        $divider();

        $sTitle('13. NOTIFICACIONES Y REQUERIMIENTOS');
        $bodyBlocks("13.1. Toda notificacion o requerimiento que traiga su causa del Contrato se debera remitir por escrito a la otra Parte, bien por E-mail, bien personalmente, o por mensajero o correo certificado con acuse de recibo a portes pagados a las personas y direcciones que aparecen en el apartado \"Reunidos\" del presente Contrato, que actuaran de interlocutores, a estos efectos, o a cualesquiera otras que, en su caso, se determinen y comuniquen en el futuro.");
        $divider();

        $sTitle('14. JURISDICCION Y COMPETENCIA');
        $bodyBlocks("14.1. Las Partes, con renuncia expresa a cualquier otro fuero que pudiera corresponderles, se someten para cuantos asuntos litigiosos pudieran derivarse en todo lo referente a la interpretacion, aplicacion o cumplimiento y ejecucion del presente Contrato, a la jurisdiccion y competencia de los Juzgados y Tribunales de Cordoba.\n\n14.2. Y para que asi conste, y en prueba de conformidad y aceptacion de todo cuanto antecede, las Partes firman el presente Contrato por duplicado ejemplar y a un solo efecto en la fecha y lugar indicados en el encabezamiento");
        $divider();
    }

    // ── NOTAS ADHERIDAS (solo para contratos no-Kit Digital) ──────
    if (!$esKitDigital && $notasTexto) {
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

    // ── CLÁUSULA LOPD ──────────────────────────────────────────────
    if (($pdf->GetY() + 60) > $pdf->getPageHeight() - 40) $pdf->AddPage();

    $pdf->Ln(4);
    $lopdY = $pdf->GetY();
    $pdf->SetFillColor(248, 248, 248);
    $pdf->SetDrawColor(220, 220, 220);
    $pdf->RoundedRect(20, $lopdY, $cW, 7, 2, '1100', 'F');
    $pdf->SetXY(22, $lopdY + 1.5);
    $pdf->SetFont('Helvetica', 'B', 8.5);
    $pdf->SetTextColor(51, 51, 51);
    $pdf->Cell($cW - 4, 4.5, 'PROTECCION DE DATOS - INFORMACION BASICA', 0, 1, 'L');
    $pdf->SetTextColor(51, 51, 51);
    $pdf->Ln(2);

    if ($esKitDigital) {
        $lopdResponsable = 'PROYECTO TRESS AZAFATAS SL - CIF: B56028293  Dir. postal: C/ Cruz Conde, 19, Planta 6, 14001 de Cordoba.  Telefono: 957963074  E-mail: info@proymer.com';
    } else {
        $lopdResponsable = 'TIC TAC COMUNICACION DIGITAL SL - CIF: B09912478  Dir. postal: C/ Cruz Conde, 19, Planta 6, 14001 de Cordoba.  Telefono: 957786914  E-mail: hola@tictac-comunicacion.es';
    }

    $pdf->SetFont('Helvetica', 'B', 7.5);
    $pdf->SetX(20);
    $pdf->Write(4.2, 'Responsable: ');
    $pdf->SetFont('Helvetica', '', 7.5);
    $pdf->Write(4.2, $lopdResponsable);
    $pdf->Ln(5);

    $lopdTexto  = "Tratamos la informacion que nos facilita con el fin de prestarles el servicio solicitado. Los datos proporcionados se conservaran durante el tiempo necesario para cumplir con las finalidades previstas. Los datos no se cedaran a terceros salvo en los casos en que exista una obligacion legal. Usted tiene derecho de acceso, rectificacion, supresion y portabilidad de sus datos y oposicion y limitacion a su tratamiento en la direccion postal o correo electronico facilitados, adjuntando copia de su DNI o documento equivalente. Asimismo, y especialmente si considera que no ha obtenido satisfaccion plena en el ejercicio de sus derechos, podra presentar una reclamacion ante la autoridad nacional de control dirigiendose a estos efectos a la Agencia Espanola de Proteccion de Datos, C/ Jorge Juan, 6 - 28001 Madrid.\n\n";
    $lopdTexto .= "Asimismo, solicitamos su autorizacion para enviarle publicidad relacionada con nuestros productos y servicios por cualquier medio (postal, email o telefono) e invitarle a eventos organizados por la empresa.";

    $pdf->SetFont('Helvetica', '', 7.5);
    $pdf->SetX(20);
    $pdf->MultiCell($cW, 4.2, $lopdTexto, 0, 'J');
    $pdf->Ln(3);

    // Checkboxes SI / NO Autorizo
    $chkY = $pdf->GetY();
    $pdf->SetFont('Helvetica', '', 8.5);
    $pdf->SetX(20);
    $pdf->SetDrawColor(100, 100, 100);
    $pdf->SetLineWidth(0.3);
    $pdf->Rect(20, $chkY, 4.5, 4.5);
    $pdf->SetX(26);
    $pdf->Cell(25, 4.5, 'SI Autorizo', 0, 0, 'L');
    $pdf->Rect(60, $chkY, 4.5, 4.5);
    $pdf->SetX(66);
    $pdf->Cell(30, 4.5, 'NO Autorizo', 0, 1, 'L');
    $pdf->Ln(4);

    if (!$esKitDigital) {
        $lopdExtra = "El CLIENTE es responsable de garantizar que dispone de los consentimientos y autorizaciones legales necesarias para la publicacion de imagenes o datos personales de trabajadores y terceros. TIC TAC COMUNICACION DIGITAL SL quedara exonerada de cualquier responsabilidad derivada de incumplimientos en materia de proteccion de datos por parte del cliente.";
        $pdf->SetFont('Helvetica', '', 7.5);
        $pdf->SetX(20);
        $pdf->MultiCell($cW, 4.2, $lopdExtra, 0, 'J');
        $pdf->Ln(4);
    }


    // ── FIRMAS ─────────────────────────────────────────────────────
    if (($pdf->GetY() + 45) > $pdf->getPageHeight() - 40) $pdf->AddPage();

    $pdf->SetDrawColor(200, 200, 200);
    $pdf->SetLineWidth(0.3);
    $pdf->Line(20, $pdf->GetY(), 20 + $cW, $pdf->GetY());
    $pdf->Ln(6);

    $half2  = ($cW - 5) / 2;
    $firmaY = $pdf->GetY();

    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->SetTextColor(215, 33, 115);
    $pdf->SetX(20);
    $pdf->Cell($half2, 5, 'FIRMA Y SELLO DEL PROVEEDOR', 0, 1, 'C');
    $pdf->SetTextColor(51, 51, 51);
    $pdf->Ln(14);
    $pdf->SetDrawColor(170, 170, 170);
    $pdf->SetLineWidth(0.4);
    $pdf->Line(20, $pdf->GetY(), 20 + $half2 - 5, $pdf->GetY());
    $pdf->Ln(3);
    $pdf->SetFont('Helvetica', '', 8);
    $pdf->SetX(20);
    $pdf->Cell($half2, 4, $esKitDigital ? 'Proyecto Tress Azafatas SL' : 'Tictac Comunicacion Digital SL', 0, 1, 'C');

    $pdf->SetY($firmaY);
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->SetTextColor(215, 33, 115);
    $pdf->SetX(20 + $half2 + 5);
    $pdf->Cell($half2, 5, 'FIRMA Y SELLO DEL CLIENTE', 0, 1, 'C');
    $pdf->SetTextColor(51, 51, 51);
    $pdf->Ln(14);
    $pdf->SetDrawColor(170, 170, 170);
    $pdf->SetLineWidth(0.4);
    $pdf->Line(20 + $half2 + 5, $pdf->GetY(), 20 + $cW, $pdf->GetY());
    $pdf->Ln(3);
    $pdf->SetFont('Helvetica', '', 8);
    $pdf->SetX(20 + $half2 + 5);
    $pdf->Cell($half2, 4, $firmanteName, 0, 1, 'C');

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

function enviarEmail($contratosFile)
{
    $id = isset($_GET['id']) ? $_GET['id'] : '';
    if (empty($id)) {
        header('Location: index.php?error=id_invalido');
        exit;
    }
    if (!file_exists($contratosFile)) {
        header('Location: index.php?error=no_encontrado');
        exit;
    }

    $contratos = json_decode(file_get_contents($contratosFile), true);
    $contrato = null;
    $index = -1;
    foreach ($contratos as $k => $c) {
        if (isset($c['id']) && $c['id'] === $id) {
            $contrato = $c;
            $index = $k;
            break;
        }
    }
    if (!$contrato) {
        header('Location: index.php?error=no_encontrado');
        exit;
    }

    $_GET['mode'] = 'save';
    $tmpFile = generarPDF($contratosFile);

    if (!$tmpFile || !file_exists($tmpFile)) {
        header('Location: index.php?error=pdf_no_generado');
        exit;
    }

    $to      = isset($contrato['cliente_email']) ? $contrato['cliente_email'] : '';
    $titulo  = isset($contrato['titulo'])        ? $contrato['titulo']        : 'Tictac Comunicacion';
    $subject = 'Contrato de Servicios - ' . $titulo;
    $total_fmt = number_format(floatval(isset($contrato['total']) ? $contrato['total'] : 0), 2, ',', '.');
    $fecha_fmt = !empty($contrato['fecha_contrato']) ? date('d/m/Y', strtotime($contrato['fecha_contrato'])) : '';
    $valid_fmt  = !empty($contrato['valido_hasta'])  ? date('d/m/Y', strtotime($contrato['valido_hasta']))  : '';
    $nombre = isset($contrato['cliente_nombre']) ? $contrato['cliente_nombre'] : '';

    $emailsRaw = array_map('trim', explode(',', $to));
    $emailsValidos = array();
    foreach ($emailsRaw as $em) {
        if (filter_var($em, FILTER_VALIDATE_EMAIL)) $emailsValidos[] = $em;
    }
    if (empty($emailsValidos)) {
        @unlink($tmpFile);
        header('Location: index.php?error=email_invalido');
        exit;
    }

    $message = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>body{font-family:Arial,sans-serif;background:#f5f5f5;margin:0;}.w{max-width:600px;margin:20px auto;background:white;border-radius:10px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,.1);}.h{background:#a8005a;padding:35px 30px;text-align:center;color:white;}.h img{max-width:150px;margin-bottom:12px;}.h h1{margin:0;font-size:20px;}.b{padding:35px 30px;}.box{background:#fff5f9;border-left:4px solid #a8005a;padding:18px;margin:20px 0;border-radius:5px;}.box strong{color:#a8005a;}.tot{font-size:20px;color:#a8005a;font-weight:bold;margin-top:8px;}.f{background:#1a1a1a;color:white;padding:22px 30px;text-align:center;font-size:13px;}.f a{color:#a8005a;text-decoration:none;}</style></head><body>
<div class="w"><div class="h"><img src="https://tictac-comunicacion.es/wp-content/uploads/2026/02/LOGO-1-2.png" alt="Tictac"><h1>Contrato de Servicios</h1></div>
<div class="b"><p>Estimado/a <strong>' . htmlspecialchars($nombre) . '</strong>,</p><p>Adjunto encontraras el contrato de servicios para tu revision y firma. Por favor leelo detenidamente y contacta con nosotros si tienes cualquier pregunta.</p>
<div class="box"><strong>Resumen del Contrato</strong><br><br><strong>Contrato:</strong> ' . htmlspecialchars($titulo) . '<br><strong>Fecha:</strong> ' . $fecha_fmt . '<br><strong>Valido hasta:</strong> ' . $valid_fmt . '<br><div class="tot">Total: ' . $total_fmt . ' E</div></div>
<p>Una vez revisado, devuelvenos el contrato firmado a <strong>hola@tictac-comunicacion.es</strong>.</p></div>
<div class="f"><strong>Tictac Comunicacion Digital SL</strong><br>C/ Escultor Ramon Barba, 1 - Bloque F - 1-2  14012 Cordoba<br><a href="tel:+34957048147">957 048 147</a> &nbsp; <a href="mailto:hola@tictac-comunicacion.es">hola@tictac-comunicacion.es</a></div></div></body></html>';

    $gmailPath = BASE_PATH . '/presupuestos/gmail_send.php';
    if (!file_exists($gmailPath)) {
        @unlink($tmpFile);
        header('Location: index.php?error=email_no_enviado');
        exit;
    }
    require_once $gmailPath;

    $enviadoOk = false;
    foreach ($emailsValidos as $emailDest) {
        $result = enviarEmailGmailAPI($emailDest, $subject, $message, array(array('file_path' => $tmpFile)));
        if ($result) $enviadoOk = true;
    }
    @unlink($tmpFile);

    if ($enviadoOk) {
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


// ==============================================================
// PDF POR CRM ID (llamado desde Contracts.php del CRM)
// ==============================================================

function generarPdfPorCrmId($contratosFile)
{
    // Verificar clave interna
    $key = isset($_GET['key']) ? $_GET['key'] : '';
    if ($key !== TICTAC_INTERNAL_KEY) {
        http_response_code(403);
        die('Acceso no autorizado');
    }

    if (!TCPDF_LOADED) {
        http_response_code(500);
        die('Error: TCPDF no disponible');
    }

    $crmId = isset($_GET['crm_id']) ? intval($_GET['crm_id']) : 0;
    if (!$crmId) {
        http_response_code(400);
        die('crm_id no valido');
    }

    // Buscar el contrato en contratos.json por crm_contract_id
    if (!file_exists($contratosFile)) {
        http_response_code(404);
        die('No hay contratos');
    }

    $contratos = json_decode(file_get_contents($contratosFile), true);
    $localId = null;
    foreach ($contratos as $c) {
        if (isset($c['crm_contract_id']) && intval($c['crm_contract_id']) === $crmId) {
            $localId = $c['id'];
            break;
        }
    }

    if (!$localId) {
        http_response_code(404);
        die('Contrato no encontrado para crm_id=' . $crmId);
    }

    // Reutilizar generarPDF() exactamente igual que siempre
    // Solo hay que simular que $_GET['id'] es el id local
    $_GET['id'] = $localId;
    // El mode viene del CRM: 'download' o 'save'
    // $_GET['mode'] ya viene en la URL, no hay que tocarlo

    generarPDF($contratosFile);
    // generarPDF() hace exit() internamente, no llega aquí
}

// ==============================================================
// PREVIEW HTML POR CRM ID (llamado desde Contracts.php del CRM)
// ==============================================================

function previewPorCrmId($contratosFile)
{
    $key = isset($_GET['key']) ? $_GET['key'] : '';
    if ($key !== TICTAC_INTERNAL_KEY) {
        http_response_code(403);
        die('Acceso no autorizado');
    }

    $crmId = isset($_GET['crm_id']) ? intval($_GET['crm_id']) : 0;
    if (!$crmId) {
        http_response_code(400);
        echo '<p>crm_id no valido</p>';
        exit;
    }

    if (!file_exists($contratosFile)) {
        http_response_code(404);
        echo '<p>No hay contratos</p>';
        exit;
    }

    $contratos = json_decode(file_get_contents($contratosFile), true);
    $contrato = null;
    foreach ($contratos as $c) {
        if (isset($c['crm_contract_id']) && intval($c['crm_contract_id']) === $crmId) {
            $contrato = $c;
            break;
        }
    }

    if (!$contrato) {
        http_response_code(404);
        echo '<p style="color:red">Contrato no encontrado para crm_id=' . intval($crmId) . '. 
              Puede que este contrato no tenga version en el dashboard.</p>';
        exit;
    }

    // Generar HTML de preview
    header('Content-Type: text/html; charset=utf-8');
    echo generarPreviewHTML($contrato);
    exit;
}

// ==============================================================
// SINCRONIZAR DESDE CRM → contratos.json
// ==============================================================

function sincronizarDesdeCRM($contratosFile)
{
    header('Content-Type: application/json; charset=utf-8');

    $key = isset($_GET['key']) ? $_GET['key'] : '';
    if ($key !== TICTAC_INTERNAL_KEY) {
        http_response_code(403);
        echo json_encode(array('success' => false, 'message' => 'No autorizado'));
        exit;
    }

    $crmId = isset($_GET['crm_id']) ? intval($_GET['crm_id']) : 0;
    if (!$crmId) {
        echo json_encode(array('success' => false, 'message' => 'crm_id no valido'));
        exit;
    }

    if (!file_exists($contratosFile)) {
        echo json_encode(array('success' => false, 'message' => 'No hay contratos'));
        exit;
    }

    $contratos = json_decode(file_get_contents($contratosFile), true);
    if (!is_array($contratos)) {
        echo json_encode(array('success' => false, 'message' => 'Error leyendo contratos.json'));
        exit;
    }

    $encontrado = false;
    foreach ($contratos as $key_idx => $c) {
        if (isset($c['crm_contract_id']) && intval($c['crm_contract_id']) === $crmId) {
            // Actualizar solo los campos que pueden cambiar desde el CRM
            if (!empty($_GET['titulo']))         $contratos[$key_idx]['titulo']         = (string)$_GET['titulo'];
            if (!empty($_GET['fecha_contrato'])) $contratos[$key_idx]['fecha_contrato'] = (string)$_GET['fecha_contrato'];
            if (!empty($_GET['valido_hasta']))   $contratos[$key_idx]['valido_hasta']   = (string)$_GET['valido_hasta'];
            $contratos[$key_idx]['fecha_modificacion'] = date('Y-m-d H:i:s');
            $encontrado = true;
            break;
        }
    }

    if (!$encontrado) {
        echo json_encode(array('success' => false, 'message' => 'Contrato no encontrado para crm_id=' . $crmId));
        exit;
    }

    $written = file_put_contents(
        $contratosFile,
        json_encode($contratos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );

    if ($written === false) {
        echo json_encode(array('success' => false, 'message' => 'Error al escribir contratos.json'));
        exit;
    }

    echo json_encode(array('success' => true, 'message' => 'Sincronizado correctamente', 'crm_id' => $crmId));
    exit;
}

// ==============================================================
// HELPER: generar HTML de preview del contrato
// ==============================================================

function generarPreviewHTML($contrato)
{
    $titulo        = htmlspecialchars($contrato['titulo'] ?? 'Contrato de Servicios');
    $id            = htmlspecialchars($contrato['id'] ?? '');
    $fecha         = !empty($contrato['fecha_contrato']) ? date('d/m/Y', strtotime($contrato['fecha_contrato'])) : '';
    $valido        = !empty($contrato['valido_hasta'])   ? date('d/m/Y', strtotime($contrato['valido_hasta']))   : '';
    $clienteNombre = htmlspecialchars($contrato['cliente_nombre'] ?? '');
    $clienteEmail  = htmlspecialchars($contrato['cliente_email']  ?? '');
    $clienteCif    = htmlspecialchars($contrato['cliente_cif']    ?? '');
    $clienteDir    = htmlspecialchars($contrato['cliente_direccion'] ?? '');
    $total         = number_format(floatval($contrato['total'] ?? 0), 2, ',', '.');
    $subtotal      = number_format(floatval($contrato['subtotal'] ?? 0), 2, ',', '.');
    $iva           = floatval($contrato['iva'] ?? 21);
    $ivaAmt        = number_format(floatval($contrato['subtotal'] ?? 0) * $iva / 100, 2, ',', '.');
    $esKitDigital  = !empty($contrato['kit_digital']);
    $items         = $contrato['items'] ?? array();
    $estado        = htmlspecialchars($contrato['estado'] ?? 'borrador');

    $estadoColor = '#6c757d';
    if ($estado === 'enviado')   $estadoColor = '#0d6efd';
    if ($estado === 'aceptado')  $estadoColor = '#198754';
    if ($estado === 'rechazado') $estadoColor = '#dc3545';

    $html = '<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: Arial, sans-serif; font-size: 13px; color: #333; background: #f8f8f8; padding: 20px; }
  .contrato-wrap { max-width: 820px; margin: 0 auto; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 12px rgba(0,0,0,.1); }
  .header { background: #d72173; color: white; padding: 20px 30px; text-align: center; }
  .header h1 { font-size: 18px; margin-bottom: 4px; }
  .header p { font-size: 12px; opacity: .85; }
  .meta-bar { display: flex; gap: 16px; padding: 16px 30px; background: #fafafa; border-bottom: 1px solid #eee; flex-wrap: wrap; align-items: center; }
  .meta-bar .badge { background: ' . $estadoColor . '; color: white; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
  .meta-bar .ref { font-size: 12px; color: #888; }
  .meta-bar .ref strong { color: #333; }
  .body { padding: 24px 30px; }
  .two-col { display: flex; gap: 16px; margin-bottom: 20px; }
  .box { flex: 1; border: 1px solid #e0e0e0; border-radius: 6px; padding: 14px; }
  .box-title { font-size: 11px; font-weight: bold; color: #d72173; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 10px; padding-bottom: 6px; border-bottom: 2px solid #d72173; }
  .box p { font-size: 12px; margin-bottom: 4px; color: #555; }
  .box p strong { color: #333; }
  table.items { width: 100%; border-collapse: collapse; margin: 16px 0; font-size: 12px; }
  table.items thead tr { background: #1e1e1e; color: white; }
  table.items thead th { padding: 7px 10px; text-align: left; font-weight: bold; }
  table.items thead th:last-child, table.items tbody td:last-child { text-align: right; }
  table.items tbody tr:nth-child(even) { background: #fafafa; }
  table.items tbody td { padding: 7px 10px; border-bottom: 1px solid #eee; }
  .totales { text-align: right; margin-top: 8px; }
  .totales table { display: inline-table; }
  .totales td { padding: 3px 8px; font-size: 12px; }
  .totales td:first-child { color: #666; }
  .totales td:last-child { font-weight: bold; min-width: 80px; }
  .total-final { background: #1e1e1e; color: white; }
  .total-final td { padding: 6px 8px; font-size: 14px; }
  .kit-badge { display: inline-block; background: #ffc107; color: #333; font-size: 11px; font-weight: bold; padding: 3px 10px; border-radius: 4px; margin-bottom: 12px; }
  .dashboard-link { display: block; text-align: center; margin-top: 16px; padding: 10px; background: #fff0f7; border: 1px dashed #d72173; border-radius: 6px; font-size: 12px; color: #d72173; text-decoration: none; }
  .dashboard-link:hover { background: #ffe0f0; }
</style>
</head>
<body>
<div class="contrato-wrap">
  <div class="header">
    <h1>' . $titulo . '</h1>
    <p>Tictac Comunicacion Digital SL &mdash; Hacemos el Marketing que funciona</p>
  </div>
  <div class="meta-bar">
    <span class="badge">' . $estado . '</span>
    <span class="ref"><strong>Ref:</strong> ' . $id . '</span>
    <span class="ref"><strong>Fecha:</strong> ' . $fecha . '</span>
    <span class="ref"><strong>V&aacute;lido hasta:</strong> ' . $valido . '</span>';

    if ($esKitDigital) {
        $html .= '<span class="kit-badge">KIT DIGITAL</span>';
    }

    $html .= '
  </div>
  <div class="body">
    <div class="two-col">
      <div class="box">
        <div class="box-title">Datos del Contrato</div>
        <p><strong>N&ordm; Contrato:</strong> ' . $id . '</p>
        <p><strong>Fecha:</strong> En C&oacute;rdoba a ' . $fecha . '</p>
        <p><strong>V&aacute;lido hasta:</strong> ' . $valido . '</p>
      </div>
      <div class="box">
        <div class="box-title">El Cliente</div>
        <p><strong>' . $clienteNombre . '</strong></p>';

    if ($clienteCif)   $html .= '<p>NIF/CIF: ' . $clienteCif . '</p>';
    if ($clienteDir)   $html .= '<p>' . $clienteDir . '</p>';
    if ($clienteEmail) $html .= '<p>' . $clienteEmail . '</p>';

    $html .= '
      </div>
    </div>';

    // Tabla de items
    if (!empty($items)) {
        $html .= '<table class="items">
          <thead><tr>
            <th>Artículo</th>
            <th style="text-align:center">Cantidad</th>
            <th style="text-align:right">Tarifa</th>
            <th style="text-align:right">Total</th>
          </tr></thead>
          <tbody>';
        foreach ($items as $item) {
            $cant  = floatval($item['cantidad'] ?? 0);
            $prec  = floatval($item['precio'] ?? 0);
            $html .= '<tr>
              <td>' . htmlspecialchars($item['nombre'] ?? '') . '</td>
              <td style="text-align:center">' . number_format($cant, 2, ',', '.') . ($item['unidad'] ? ' ' . htmlspecialchars($item['unidad']) : '') . '</td>
              <td style="text-align:right">' . number_format($prec, 2, ',', '.') . ' &euro;</td>
              <td style="text-align:right">' . number_format($cant * $prec, 2, ',', '.') . ' &euro;</td>
            </tr>';
        }
        $html .= '</tbody></table>
        <div class="totales">
          <table>
            <tr><td>Subtotal</td><td>' . $subtotal . ' &euro;</td></tr>
            <tr><td>IVA (' . $iva . '%)</td><td>' . $ivaAmt . ' &euro;</td></tr>
            <tr class="total-final"><td>TOTAL</td><td>' . $total . ' &euro;</td></tr>
          </table>
        </div>';
    }

    $dashboardUrl = 'https://gestion-tictac-comunicacion.es/dashboard/contratos/index.php?edit=' . urlencode($contrato['id'] ?? '');
    $html .= '
    <a href="' . $dashboardUrl . '" target="_blank" class="dashboard-link">
      ✏️ Editar este contrato en el Dashboard
    </a>
  </div>
</div>
</body>
</html>';

    return $html;
}



// ==============================================================
// GENERADOR PDF SEPA
// ==============================================================

function generarSepaPDF($enviarPorEmail = false)
{
    $deudorCif    = isset($_POST['deudor_cif'])    ? trim($_POST['deudor_cif'])    : '';
    $deudorNombre = isset($_POST['deudor_nombre']) ? trim($_POST['deudor_nombre']) : '';

    $rRosa = 168; $gRosa = 0; $bRosa = 90;
    $rGris = 245; $gGris = 245; $bGris = 245;

    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('Tictac Comunicacion Digital SL');
    $pdf->SetTitle('Orden de Domiciliacion SEPA');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetAutoPageBreak(false);
    $pdf->SetMargins(0, 0, 0);
    $pdf->AddPage();

    $W = $pdf->getPageWidth();
    $H = $pdf->getPageHeight();
    $ml = 15; $mr = 15; $cw = $W - $ml - $mr;

    // ─── CABECERA ────────────────────────────────────────────────
    $pdf->SetFillColor($rRosa, $gRosa, $bRosa);
    $pdf->RoundedRect(0, 0, $W, 28, 3, '0011', 'F');

    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Helvetica', 'B', 17);
    $pdf->SetXY($ml, 4);
    $pdf->Cell(120, 9, 'Orden de Domiciliacion de', 0, 1, 'L');
    $pdf->SetX($ml);
    $pdf->Cell(120, 8, 'Adeudo Directo SEPA', 0, 0, 'L');

    $logoPath = defined('BASE_PATH') ? BASE_PATH . '/assets/img/logoblanco.png' : '';
    if ($logoPath && file_exists($logoPath)) {
        $pdf->Image($logoPath, $W - 58, 5, 42, 0, '', '', '', false, 300);
    }

    $pdf->SetTextColor(51, 51, 51);

    // ─── BLOQUE ACREEDOR ──────────────────────────────────────────
    $yB1 = 34;
    $hB1 = 57;

    $pdf->SetFillColor($rGris, $gGris, $bGris);
    $pdf->RoundedRect($ml, $yB1, $cw, $hB1, 3, '1111', 'F');

    $pdf->SetFillColor($rRosa, $gRosa, $bRosa);
    $pdf->RoundedRect($ml, $yB1, $cw, 9, 2, '1100', 'F');
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Helvetica', 'B', 8.5);
    $pdf->SetXY($ml + 5, $yB1 + 1.8);
    $pdf->Cell($cw - 20, 5.5, 'INFORMACION CUMPLIMENTADA POR EL ACREEDOR', 0, 0, 'L');
    $pdf->SetFont('Helvetica', '', 11);
    $pdf->SetXY($W - $mr - 12, $yB1 + 1);
    $pdf->Cell(10, 7, chr(118), 0, 0, 'C');

    $pdf->SetTextColor(51, 51, 51);

    $camposAcreedor = array(
        array('IDENTIFICADOR DEL ACREEDOR CIF:', 'B09912478'),
        array('NOMBRE DEL ACREEDOR:', 'TIC TAC COMUNICACION'),
        array('DIRECCION:', 'Plaza de los Carrillos No 5'),
        array('CODIGO POSTAL - POBLACION - PROVINCIA:', '14007 - Cordoba'),
        array('PAIS:', 'Espana'),
    );

    $yFila = $yB1 + 11;
    $xBullet = $ml + 7;
    $xLabel  = $ml + 12;
    $xValue  = $ml + 82;

    foreach ($camposAcreedor as $campo) {
        $pdf->SetFillColor($rRosa, $gRosa, $bRosa);
        $pdf->Circle($xBullet, $yFila + 2.2, 1.3, 0, 360, 'F');
        $pdf->SetFont('Helvetica', 'B', 8);
        $pdf->SetTextColor(40, 40, 40);
        $pdf->SetXY($xLabel, $yFila);
        $pdf->Cell(72, 5, $campo[0], 0, 0, 'L');
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->SetXY($xValue, $yFila);
        $pdf->Cell(90, 5, $campo[1], 0, 0, 'L');
        $yFila += 8.5;
    }

    // ─── TEXTO LEGAL ──────────────────────────────────────────────
    $yTexto = $yB1 + $hB1 + 5;
    $pdf->SetFont('Helvetica', '', 7.5);
    $pdf->SetTextColor(70, 70, 70);
    $pdf->SetXY($ml, $yTexto);
    $pdf->MultiCell($cw, 4.5,
        'Mediante la firma de esta orden de domiciliacion, el deudor autoriza (A) al acreedor a enviar instrucciones a la entidad del deudor para adeudar su cuenta y (B) a la entidad para efectuar los adeudos en su cuenta siguiendo las instrucciones del acreedor. Como parte de sus derechos, el deudor esta legitimado al reembolso por su entidad en los terminos y condiciones del contrato suscrito con la misma.',
        0, 'J');

    // ─── BLOQUE DEUDOR ────────────────────────────────────────────
    $yB2 = $pdf->GetY() + 5;
    $hB2 = 115;

    $pdf->SetFillColor($rGris, $gGris, $bGris);
    $pdf->RoundedRect($ml, $yB2, $cw, $hB2, 3, '1111', 'F');

    $pdf->SetFillColor($rRosa, $gRosa, $bRosa);
    $pdf->RoundedRect($ml, $yB2, $cw, 9, 2, '1100', 'F');
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Helvetica', 'B', 8.5);
    $pdf->SetXY($ml + 5, $yB2 + 1.8);
    $pdf->Cell($cw - 20, 5.5, 'A CUMPLIMENTAR POR EL DEUDOR', 0, 0, 'L');
    $pdf->SetFont('Helvetica', '', 11);
    $pdf->SetXY($W - $mr - 12, $yB2 + 1);
    $pdf->Cell(10, 7, chr(118), 0, 0, 'C');

    $pdf->SetTextColor(51, 51, 51);

    $camposDeudor = array(
        array('IDENTIFICADOR DEL ACREEDOR CIF:', $deudorCif),
        array('NOMBRE DEL DEUDOR/ES:', $deudorNombre),
        array('DIRECCION DEL DEUDOR:', ''),
        array('CODIGO POSTAL - POBLACION - PROVINCIA:', ''),
        array('PAIS DEL DEUDOR:', ''),
        array('SWIFT BIC:', ''),
        array('NUMERO DE CUENTA:', ''),
    );

    $hints = array(
        5 => 'Puede contener 8 u 11 posiciones',
        6 => 'En Espana consta de 24 posiciones comenzando siempre por ES',
    );

    $yFila = $yB2 + 11;
    foreach ($camposDeudor as $idx => $campo) {
        $pdf->SetFillColor($rRosa, $gRosa, $bRosa);
        $pdf->Circle($xBullet, $yFila + 2.2, 1.3, 0, 360, 'F');

        $pdf->SetFont('Helvetica', 'B', 8);
        $pdf->SetTextColor(40, 40, 40);
        $pdf->SetXY($xLabel, $yFila);
        $pdf->Cell(72, 5, $campo[0], 0, 0, 'L');

        if (!empty($campo[1])) {
            $pdf->SetFont('Helvetica', '', 8);
            $pdf->SetTextColor(51, 51, 51);
            $pdf->SetXY($xValue, $yFila);
            $pdf->Cell(100, 5, $campo[1], 0, 0, 'L');
        } else {
            $lineX1 = $xValue;
            $lineX2 = $W - $mr - 3;
            $lineY  = $yFila + 4.5;
            $pdf->SetDrawColor(200, 100, 160);
            $pdf->SetLineWidth(0.25);
            $pdf->Line($lineX1, $lineY, $lineX2, $lineY);

            if (isset($hints[$idx])) {
                $pdf->SetFont('Helvetica', 'I', 7);
                $pdf->SetTextColor(190, 130, 165);
                $pdf->SetXY($xValue, $yFila);
                $pdf->Cell(100, 4.5, $hints[$idx], 0, 0, 'L');
            }
        }

        $pdf->SetTextColor(51, 51, 51);
        $yFila += 8.5;
    }

    // TIPO DE PAGO con checkbox
    $pdf->SetFillColor($rRosa, $gRosa, $bRosa);
    $pdf->Circle($xBullet, $yFila + 2.2, 1.3, 0, 360, 'F');
    $pdf->SetFont('Helvetica', 'B', 8);
    $pdf->SetTextColor(40, 40, 40);
    $pdf->SetXY($xLabel, $yFila);
    $pdf->Cell(28, 5, 'TIPO DE PAGO:', 0, 0, 'L');

    $cbX = $xLabel + 30; $cbY = $yFila + 0.3;
    $pdf->SetDrawColor($rRosa, $gRosa, $bRosa);
    $pdf->SetFillColor(255, 255, 255);
    $pdf->RoundedRect($cbX, $cbY, 5.5, 5, 0.8, '1111', 'DF');
    $pdf->SetDrawColor($rRosa, $gRosa, $bRosa);
    $pdf->SetLineWidth(0.9);
    $pdf->Line($cbX + 1,   $cbY + 2.8, $cbX + 2.3, $cbY + 4.0);
    $pdf->Line($cbX + 2.3, $cbY + 4.0, $cbX + 4.5, $cbY + 1.2);
    $pdf->SetLineWidth(0.2);
    $pdf->SetFont('Helvetica', 'B', 8.5);
    $pdf->SetTextColor(40, 40, 40);
    $pdf->SetXY($cbX + 8, $yFila);
    $pdf->Cell(50, 5, 'TIPO  RECURRENTE', 0, 0, 'L');

    $yFila += 13;

    // FECHA y LOCALIDAD
    $pdf->SetFont('Helvetica', 'B', 8.5);
    $pdf->SetTextColor(40, 40, 40);
    $pdf->SetXY($ml + 10, $yFila);
    $pdf->Cell(22, 5, 'FECHA:', 0, 0, 'L');
    $pdf->SetDrawColor(200, 100, 160); $pdf->SetLineWidth(0.25);
    $pdf->Line($ml + 33, $yFila + 4.5, $ml + 80, $yFila + 4.5);

    $pdf->SetXY($W / 2 - 5, $yFila);
    $pdf->Cell(28, 5, 'LOCALIDAD:', 0, 0, 'L');
    $pdf->Line($W / 2 + 23, $yFila + 4.5, $W - $mr - 5, $yFila + 4.5);

    $yFila += 18;

    // FIRMA DEL DEUDOR
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->SetTextColor(40, 40, 40);
    $pdf->SetXY(0, $yFila);
    $pdf->Cell($W, 5, 'FIRMA DEL DEUDOR:', 0, 1, 'C');

    // ─── NOTA FINAL ───────────────────────────────────────────────
    $yNota = $yB2 + $hB2 + 5;
    $pdf->SetFont('Helvetica', 'B', 7.5);
    $pdf->SetTextColor($rRosa, $gRosa, $bRosa);
    $pdf->SetXY($ml, $yNota);
    $pdf->MultiCell($cw, 4.5,
        '* TODOS LOS CAMPOS HAN DE SER CUMPLIMENTADOS OBLIGATORIAMENTE. UNA VEZ FIRMADA ESTA ORDEN DE DOMICILIACION DEBE SER ENVIADA AL ACREEDOR PARA SU CUSTODIA.',
        0, 'L');

    // ─── PIE ──────────────────────────────────────────────────────
    $yPie = $H - 14;
    $pdf->SetDrawColor($rRosa, $gRosa, $bRosa);
    $pdf->SetLineWidth(0.4);
    $pdf->Line($ml, $yPie, $W - $mr, $yPie);
    $pdf->SetFont('Helvetica', '', 7);
    $pdf->SetTextColor(140, 140, 140);
    $pdf->SetXY(0, $yPie + 2);
    $pdf->Cell($W, 4, 'Tictac Comunicacion Digital SL  ·  C/ Escultor Ramon Barba, 1 - Bloque F - 1-2  ·  14012 Cordoba  ·  hola@tictac-comunicacion.es', 0, 0, 'C');

    // ─── OUTPUT ───────────────────────────────────────────────────
    $nombre    = preg_replace('/[^A-Za-z0-9_\-]/', '_', $deudorNombre ?: 'cliente');
    $pdfNombre = 'SEPA_' . $nombre . '.pdf';

    if ($enviarPorEmail) {
        $tmpFile = sys_get_temp_dir() . '/' . $pdfNombre;
        $pdf->Output($tmpFile, 'F');

        $clienteEmail = isset($_POST['cliente_email']) ? trim($_POST['cliente_email']) : '';
        $emailsRaw    = array_map('trim', explode(',', $clienteEmail));
        $emailsOk     = array();
        foreach ($emailsRaw as $em) {
            if (filter_var($em, FILTER_VALIDATE_EMAIL)) $emailsOk[] = $em;
        }

        if (!empty($emailsOk)) {
            $gmailPath = BASE_PATH . '/presupuestos/gmail_send.php';
            if (file_exists($gmailPath)) {
                require_once $gmailPath;
                $subject = 'Orden de Domiciliacion SEPA - Tictac Comunicacion';
                $nombreCliente = $deudorNombre ?: 'cliente';
                $message = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>body{font-family:Arial,sans-serif;background:#f5f5f5;margin:0;}.w{max-width:600px;margin:20px auto;background:white;border-radius:10px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,.1);}.h{background:#a8005a;padding:35px 30px;text-align:center;color:white;}.h img{max-width:150px;margin-bottom:12px;}.h h1{margin:0;font-size:20px;}.b{padding:35px 30px;}.box{background:#fff5f9;border-left:4px solid #a8005a;padding:18px;margin:20px 0;border-radius:5px;}.f{background:#1a1a1a;color:white;padding:22px 30px;text-align:center;font-size:13px;}.f a{color:#a8005a;text-decoration:none;}</style></head><body><div class="w"><div class="h"><img src="https://tictac-comunicacion.es/wp-content/uploads/2026/02/LOGO-1-2.png" alt="Tictac"><h1>Orden de Domiciliacion SEPA</h1></div><div class="b"><p>Estimado/a <strong>' . htmlspecialchars($nombreCliente) . '</strong>,</p><p>Adjunto encontraras la Orden de Domiciliacion de Adeudo Directo SEPA para que la cumplimentes con tus datos bancarios, la firmes y nos la devuelvas a <strong>hola@tictac-comunicacion.es</strong>.</p><div class="box">Por favor rellena todos los campos del documento, firmalo y envialo de vuelta lo antes posible para activar la domiciliacion bancaria de tus pagos.</div><p>Si tienes cualquier duda no dudes en contactar con nosotros.</p></div><div class="f"><strong>Tictac Comunicacion Digital SL</strong><br>C/ Escultor Ramon Barba, 1 - Bloque F - 1-2  14012 Cordoba<br><a href="tel:+34957048147">957 048 147</a> &nbsp; <a href="mailto:hola@tictac-comunicacion.es">hola@tictac-comunicacion.es</a></div></div></body></html>';
                $enviadoOk = false;
                foreach ($emailsOk as $emailDest) {
                    $res = enviarEmailGmailAPI($emailDest, $subject, $message, array(array('file_path' => $tmpFile)));
                    if ($res) $enviadoOk = true;
                }
                @unlink($tmpFile);
                if ($enviadoOk) {
                    echo json_encode(array('success' => true, 'message' => 'SEPA enviado a ' . implode(', ', $emailsOk)));
                } else {
                    echo json_encode(array('success' => false, 'message' => 'Error al enviar el email'));
                }
                exit;
            }
        }
        @unlink($tmpFile);
        echo json_encode(array('success' => false, 'message' => 'No hay email valido para enviar'));
        exit;
    }

    $pdf->Output($pdfNombre, 'D');
    exit;
}