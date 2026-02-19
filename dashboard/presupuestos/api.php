<?php
/**
 * API Backend - Presupuestos
 * Maneja: Guardar, Eliminar, Generar PDF, Enviar Email
 * ACTUALIZADO: 
 *   - Nuevo campo detalles_propuesta
 *   - PDF con altura dinámica en cajas de texto
 *   - Secciones opcionales (no aparecen si están vacías)
 *   - Múltiples emails separados por comas
 *   - Precio original (sin rebajar) tachado en PDF
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
 * Limpia texto para PDF
 */
function pdf_text_plain($html) {
    if ($html === null) return '';
    $txt = html_entity_decode((string)$html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $txt = preg_replace('~<\s*br\s*/?\s*>~i', "\n", $txt);
    $txt = preg_replace('~<\s*/\s*p\s*>~i', "\n", $txt);
    $txt = preg_replace('~<\s*p[^>]*>~i', '', $txt);
    $txt = strip_tags($txt);
    $txt = str_replace("\xC2\xA0", ' ', $txt);
    $txt = preg_replace("/[ \t]+/", " ", $txt);
    $txt = str_replace(["\r\n", "\r"], "\n", $txt);
    $txt = preg_replace("/\n{3,}/", "\n\n", $txt);
    return trim($txt);
}

/**
 * Convierte HTML del WYSIWYG a texto con formato básico para TCPDF writeHTMLCell
 */
function pdf_html_clean($html) {
    if ($html === null || trim($html) === '' || trim($html) === '<p><br></p>') return '';
    
    $html = preg_replace('~<p>\s*<br\s*/?>\s*</p>~i', '', $html);
    $html = preg_replace('~<span[^>]*>~i', '', $html);
    $html = str_ireplace('</span>', '', $html);
    $html = strip_tags($html, '<p><br><strong><b><em><i><u><ul><ol><li>');
    $html = preg_replace('~(<(?:p|strong|b|em|i|u|ul|ol|li))\s+[^>]*>~i', '$1>', $html);
    
    return trim($html);
}

/**
 * Comprueba si un campo HTML del WYSIWYG tiene contenido real
 */
function tiene_contenido($html) {
    if ($html === null) return false;
    $texto = pdf_text_plain($html);
    return $texto !== '';
}

function crearPropuestaEnCRM($presupuesto) {
    if (empty($presupuesto['cliente_id']) || $presupuesto['cliente_id'] === '') {
        return ['success' => false, 'message' => 'No se puede sincronizar: falta cliente_id'];
    }

    $mysqli = conexionBBDD();
    if (!$mysqli) {
        return ['success' => false, 'message' => 'Error de conexión a BBDD'];
    }

    $client_id = intval($presupuesto['cliente_id']);
    $proposal_date = $presupuesto['fecha_propuesta'] ?? date('Y-m-d');
    $valid_until = $presupuesto['valido_hasta'] ?? date('Y-m-d', strtotime('+30 days'));
    $note = $mysqli->real_escape_string($presupuesto['notas'] ?? '');
    
    $public_key = substr(md5(uniqid(rand(), true)), 0, 10);
    
    $content = '<meta charset="UTF-8"><p><strong>Presupuesto: ' . htmlspecialchars($presupuesto['id'] ?? '') . '</strong></p>';
    $content .= '<p>' . nl2br(htmlspecialchars($note)) . '</p>';
    $content = $mysqli->real_escape_string($content);
    
    $sql = "INSERT INTO crm_proposals 
            (client_id, proposal_date, valid_until, note, 
             status, tax_id, tax_id2, 
             discount_type, discount_amount, discount_amount_type,
             content, public_key, accepted_by, created_by, total_views, meta_data, company_id, project_id, deleted) 
            VALUES 
            ($client_id, '$proposal_date', '$valid_until', '$note',
             'draft', 1, 0,
             'before_tax', 0, 'percentage',
             '$content', '$public_key', 0, 1, 0, '', 0, 0, 0)";
    
    if (!$mysqli->query($sql)) {
        return ['success' => false, 'message' => 'Error al insertar proposal: ' . $mysqli->error];
    }
    
    $proposal_id = $mysqli->insert_id;
    
    if (isset($presupuesto['items']) && is_array($presupuesto['items'])) {
        $sort = 0;
        foreach ($presupuesto['items'] as $item) {
            $title = $mysqli->real_escape_string($item['nombre'] ?? '');
            $description = $mysqli->real_escape_string($item['descripcion'] ?? '');
            $quantity = floatval($item['cantidad'] ?? 1);
            $unit_type = $mysqli->real_escape_string($item['unidad'] ?? '');
            $rate = floatval($item['precio'] ?? 0);
            $item_total = $quantity * $rate;
            
            $sqlItem = "INSERT INTO crm_proposal_items 
                        (proposal_id, title, description, quantity, unit_type, rate, total, sort, item_id, deleted) 
                        VALUES 
                        ($proposal_id, '$title', '$description', $quantity, '$unit_type', $rate, $item_total, $sort, 0, 0)";
            
            if (!$mysqli->query($sqlItem)) {
                error_log("Error al insertar item de proposal: " . $mysqli->error);
            }
            $sort++;
        }
    }
    
    return [
        'success' => true,
        'proposal_id' => $proposal_id,
        'message' => 'Propuesta creada en CRM con ID: ' . $proposal_id
    ];
}

function actualizarPropuestaEnCRM($presupuesto) {
    if (empty($presupuesto['crm_proposal_id'])) {
        return crearPropuestaEnCRM($presupuesto);
    }

    if (empty($presupuesto['cliente_id']) || $presupuesto['cliente_id'] === '') {
        return ['success' => false, 'message' => 'No se puede sincronizar: falta cliente_id'];
    }

    $mysqli = conexionBBDD();
    if (!$mysqli) {
        return ['success' => false, 'message' => 'Error de conexión a BBDD'];
    }

    $proposal_id = intval($presupuesto['crm_proposal_id']);
    $client_id = intval($presupuesto['cliente_id']);
    $proposal_date = $presupuesto['fecha_propuesta'] ?? date('Y-m-d');
    $valid_until = $presupuesto['valido_hasta'] ?? date('Y-m-d', strtotime('+30 days'));
    $note = $mysqli->real_escape_string($presupuesto['notas'] ?? '');
    
    $content = '<meta charset="UTF-8"><p><strong>Presupuesto: ' . htmlspecialchars($presupuesto['id'] ?? '') . '</strong></p>';
    $content .= '<p>' . nl2br(htmlspecialchars($note)) . '</p>';
    $content = $mysqli->real_escape_string($content);
    
    $sql = "UPDATE crm_proposals SET
            client_id = $client_id,
            proposal_date = '$proposal_date',
            valid_until = '$valid_until',
            note = '$note',
            content = '$content'
            WHERE id = $proposal_id";
    
    if (!$mysqli->query($sql)) {
        return ['success' => false, 'message' => 'Error al actualizar proposal: ' . $mysqli->error];
    }
    
    $mysqli->query("DELETE FROM crm_proposal_items WHERE proposal_id = $proposal_id");
    
    if (isset($presupuesto['items']) && is_array($presupuesto['items'])) {
        $sort = 0;
        foreach ($presupuesto['items'] as $item) {
            $title = $mysqli->real_escape_string($item['nombre'] ?? '');
            $description = $mysqli->real_escape_string($item['descripcion'] ?? '');
            $quantity = floatval($item['cantidad'] ?? 1);
            $unit_type = $mysqli->real_escape_string($item['unidad'] ?? '');
            $rate = floatval($item['precio'] ?? 0);
            $item_total = $quantity * $rate;
            
            $sqlItem = "INSERT INTO crm_proposal_items 
                        (proposal_id, title, description, quantity, unit_type, rate, total, sort, item_id, deleted) 
                        VALUES 
                        ($proposal_id, '$title', '$description', $quantity, '$unit_type', $rate, $item_total, $sort, 0, 0)";
            
            $mysqli->query($sqlItem);
            $sort++;
        }
    }
    
    return [
        'success' => true,
        'proposal_id' => $proposal_id,
        'message' => 'Propuesta actualizada en CRM'
    ];
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
                $precioOriginal = isset($item['precio_original']) && $item['precio_original'] !== '' 
                    ? floatval($item['precio_original']) 
                    : null;
                $items[] = [
                    'nombre' => $item['nombre'],
                    'descripcion' => $item['descripcion'] ?? '',
                    'cantidad' => floatval($item['cantidad']),
                    'precio' => floatval($item['precio']),
                    'precio_original' => $precioOriginal,
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
        'cliente_email' => $_POST['cliente_email'], // Puede ser "a@x.com,b@x.com"
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
        'detalles_propuesta' => $_POST['detalles_propuesta'] ?? '',
        'notas' => $_POST['notas'] ?? '',
        'estado' => 'borrador',
        'fecha_creacion' => date('Y-m-d H:i:s'),
        'fecha_modificacion' => date('Y-m-d H:i:s')
    ];

    $encontrado = false;
    $esActualizacion = false;
    foreach ($presupuestos as $key => $p) {
        if ($p['id'] === $id) {
            $presupuesto['fecha_creacion'] = $p['fecha_creacion'] ?? date('Y-m-d H:i:s');
            $presupuesto['crm_proposal_id'] = $p['crm_proposal_id'] ?? null;
            $presupuestos[$key] = $presupuesto;
            $encontrado = true;
            $esActualizacion = true;
            break;
        }
    }

    if (!$encontrado) {
        $presupuestos[] = $presupuesto;
    }

    // Sincronizar con CRM
    $crmResult = ['success' => false, 'message' => 'No sincronizado'];
    
    if (!empty($presupuesto['cliente_id']) && $presupuesto['cliente_id'] !== '') {
        if ($esActualizacion && !empty($presupuesto['crm_proposal_id'])) {
            $crmResult = actualizarPropuestaEnCRM($presupuesto);
        } else {
            $crmResult = crearPropuestaEnCRM($presupuesto);
        }

        if ($crmResult['success'] && isset($crmResult['proposal_id'])) {
            foreach ($presupuestos as $key => $p) {
                if ($p['id'] === $id) {
                    $presupuestos[$key]['crm_proposal_id'] = $crmResult['proposal_id'];
                    break;
                }
            }
        }
    }

    file_put_contents($presupuestosFile, json_encode($presupuestos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    $auditMessage = 'Presupuesto ' . ($esActualizacion ? 'actualizado' : 'guardado') . ': ' . $id;
    if ($crmResult['success']) {
        $auditMessage .= ' | Sincronizado con CRM (ID: ' . $crmResult['proposal_id'] . ')';
    } elseif (!empty($presupuesto['cliente_id'])) {
        $auditMessage .= ' | Error sincronización CRM: ' . $crmResult['message'];
    }

    guardarAuditoria('presupuesto_guardado', 'exitoso', $auditMessage, [
        'cliente_id' => $presupuesto['cliente_id'],
        'cliente_nombre' => $presupuesto['cliente_nombre'],
        'email' => $presupuesto['cliente_email']
    ]);

    echo json_encode([
        'success' => true,
        'id' => $id,
        'message' => 'Presupuesto guardado correctamente',
        'crm_sync' => $crmResult
    ]);
    exit;
}

/**
 * Eliminar presupuesto
 */
function eliminarPresupuesto() {
    global $presupuestosFile;

    $id = $_GET['id'] ?? '';
    if (empty($id)) { 
        header('Location: index.php?error=id_invalido'); 
        exit; 
    }

    if (file_exists($presupuestosFile)) {
        $presupuestos = json_decode(file_get_contents($presupuestosFile), true);
        
        $presupuesto_a_eliminar = null;
        foreach ($presupuestos as $p) {
            if ($p['id'] === $id) {
                $presupuesto_a_eliminar = $p;
                break;
            }
        }
        
        if ($presupuesto_a_eliminar && !empty($presupuesto_a_eliminar['crm_proposal_id'])) {
            $proposal_id = intval($presupuesto_a_eliminar['crm_proposal_id']);
            
            $mysqli = conexionBBDD();
            if ($mysqli) {
                $sql = "UPDATE crm_proposals SET deleted = 1 WHERE id = ?";
                $stmt = $mysqli->prepare($sql);
                
                if ($stmt) {
                    $stmt->bind_param("i", $proposal_id);
                    $stmt->execute();
                    $stmt->close();
                    
                    $sqlItems = "UPDATE crm_proposal_items SET deleted = 1 WHERE proposal_id = ?";
                    $stmtItems = $mysqli->prepare($sqlItems);
                    
                    if ($stmtItems) {
                        $stmtItems->bind_param("i", $proposal_id);
                        $stmtItems->execute();
                        $stmtItems->close();
                    }
                    
                    $mysqli->close();
                    
                    guardarAuditoria(
                        'propuesta_eliminada',
                        'exitoso',
                        'Presupuesto ' . $id . ' eliminado del dashboard y CRM (Proposal ID: ' . $proposal_id . ')',
                        [
                            'local_id' => $id,
                            'crm_proposal_id' => $proposal_id,
                            'cliente_id' => $presupuesto_a_eliminar['cliente_id'] ?? 0,
                            'cliente_nombre' => $presupuesto_a_eliminar['cliente_nombre'] ?? '',
                            'cliente_email' => $presupuesto_a_eliminar['cliente_email'] ?? '',
                            'total' => $presupuesto_a_eliminar['total'] ?? 0
                        ]
                    );
                } else {
                    guardarAuditoria(
                        'propuesta_eliminada',
                        'parcial',
                        'Presupuesto ' . $id . ' eliminado del dashboard pero error en SQL del CRM',
                        [
                            'local_id' => $id,
                            'crm_proposal_id' => $proposal_id,
                            'error' => 'Error al preparar statement SQL'
                        ]
                    );
                    $mysqli->close();
                }
            } else {
                guardarAuditoria(
                    'propuesta_eliminada',
                    'parcial',
                    'Presupuesto ' . $id . ' eliminado del dashboard pero no se pudo sincronizar con CRM',
                    [
                        'local_id' => $id,
                        'crm_proposal_id' => $proposal_id,
                        'error' => 'No se pudo conectar a la base de datos del CRM'
                    ]
                );
            }
        } else {
            guardarAuditoria(
                'presupuesto_eliminado',
                'exitoso',
                'Presupuesto local ' . $id . ' eliminado (no sincronizado con CRM)',
                [
                    'local_id' => $id,
                    'cliente_nombre' => $presupuesto_a_eliminar['cliente_nombre'] ?? ''
                ]
            );
        }
        
        $presupuestos = array_filter($presupuestos, function($p) use ($id) { 
            return $p['id'] !== $id; 
        });
        
        file_put_contents($presupuestosFile, json_encode(array_values($presupuestos), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    header('Location: index.php?success=eliminado');
    exit;
}

/**
 * Generar PDF - DISEÑO PROFESIONAL
 * ACTUALIZADO: Precio original tachado, múltiples emails
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

    // Clase PDF personalizada
    class TictacPDF extends TCPDF {
        public $bgEnabled = false;
        public $bgColor = array(240, 248, 255);
        public $bgMarginLeft = 15;
        public $bgWidth = 180;
        
        public function header() {
            $pageW = $this->getPageWidth();
            $this->SetFillColor(233, 30, 140);
            $this->RoundedRect(0, 0, $pageW, 42, 5, 5, 'F');
            $logo = defined('LOGO_BLANCO') ? LOGO_BLANCO : '';
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
                $logoW = 44;
                $x = ($pageW - $logoW) / 2;
                $this->Image($logo, $x, 6, $logoW, 0, '', '', '', false, 300, '', false, false, 0, false, false, false);
            }
            $this->SetTextColor(255, 255, 255);
            $this->SetFont('Helvetica', 'B', 20);
            $this->SetXY(0, 24);
            $this->Cell($pageW, 10, 'Presupuesto', 0, 1, 'C');
            $this->SetDrawColor(233, 30, 140);
            $this->SetLineWidth(0.3);
            $this->Line(15, 44, $pageW - 15, 44);
            $this->SetTextColor(51, 51, 51);
            $this->SetMargins(15, 50, 15);
            $this->SetY(50);
            
            if ($this->bgEnabled) {
                $this->SetFillColor($this->bgColor[0], $this->bgColor[1], $this->bgColor[2]);
                $this->SetDrawColor($this->bgColor[0], $this->bgColor[1], $this->bgColor[2]);
                $bgHeight = $this->getPageHeight() - 50 - 40;
                $this->Rect($this->bgMarginLeft, 50, $this->bgWidth, $bgHeight, 'F');
                $this->SetTextColor(51, 51, 51);
            }
        }
        public function footer() {
            $pageW = $this->getPageWidth();
            $this->SetY(-38);
            $this->SetDrawColor(233, 30, 140);
            $this->SetLineWidth(0.5);
            $this->Line(15, $this->GetY(), $pageW - 15, $this->GetY());
            $this->Ln(4);
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
            $this->SetFont('Helvetica', 'I', 7);
            $this->SetTextColor(150, 150, 150);
            $this->SetX(0);
            $this->Cell($pageW, 4, 'Página ' . $this->PageNo() . ' / ' . $this->getNumPages(), 0, 1, 'C');
        }

        /**
         * Dibuja texto tachado manualmente (TCPDF no soporta strikethrough nativo de forma fiable)
         */
        public function CellStrikethrough($x, $y, $w, $h, $txt, $fontSize) {
            $this->SetXY($x, $y);
            $this->Cell($w, $h, $txt, 0, 0, 'L');
            // Línea de tachado: a mitad del texto
            $strikeY = $y + ($h / 2) + 0.3;
            $txtW = $this->GetStringWidth($txt);
            $this->SetLineWidth(0.3);
            $this->Line($x, $strikeY, $x + $txtW, $strikeY);
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

    $pageW = $pdf->getPageWidth();
    $contentW = $pageW - 30;
    $colW = 85;
    $startX = 15;
    $startY = $pdf->GetY();

    // Columna izquierda: Datos de la Propuesta
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

    // Columna derecha: Info Cliente
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
    ];
    
    $direccion = trim($presupuesto['cliente_direccion'] ?? '');
    if ($direccion !== '') {
        $campos_cliente[] = ['Dirección:', $direccion];
    }
    
    $ciudad = trim($presupuesto['cliente_ciudad'] ?? '');
    $cp = trim($presupuesto['cliente_cp'] ?? '');
    $ciudadCompleta = $ciudad . ($cp !== '' ? ', ' . $cp : '');
    if (trim($ciudadCompleta) !== '' && trim($ciudadCompleta) !== ',') {
        $campos_cliente[] = ['Ciudad:', $ciudadCompleta];
    }
    
    $pais = trim($presupuesto['cliente_pais'] ?? '');
    if ($pais !== '') {
        $campos_cliente[] = ['País:', $pais];
    }
    
    $cif = trim($presupuesto['cliente_cif'] ?? '');
    if ($cif !== '') {
        $campos_cliente[] = ['CIF/NIF:', $cif];
    }

    foreach ($campos_cliente as $campo) {
        $pdf->SetX($rightX + 5);
        $pdf->SetTextColor(51, 51, 51);
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->Cell(25, 5, $campo[0], 0, 0, 'L');
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->Cell(0, 5, $campo[1] ?? '', 0, 1, 'L');
    }

    // Sección "Sobre Nosotros"
    $pdf->SetY($startY + 55);
    $sobreY = $pdf->GetY();
    $pdf->SetFillColor(255, 240, 247);
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

    // Título "Propuesta Económica"
    $pdf->Ln(12);
    $pdf->SetTextColor(51, 51, 51);
    $pdf->SetFont('Helvetica', 'B', 16);
    $pdf->Cell($contentW, 8, 'Propuesta Económica', 0, 1, 'L');
    $pdf->SetFont('Helvetica', '', 9);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->Cell($contentW, 5, 'A continuación detallamos los servicios incluidos en esta propuesta:', 0, 1, 'L');

    // Nota IVA
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

    // Tabla de artículos
    $pdf->Ln(5);
    $pdf->SetTextColor(51, 51, 51);
    $cArticulo = 75;
    $cCantidad = 25;
    $cTarifa   = 30;
    $cTotal    = 30;
    $tableW    = $cArticulo + $cCantidad + $cTarifa + $cTotal;

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

    $printTableHeader();
    $rowAlternate = false;
    $items = $presupuesto['items'] ?? [];
    if (!is_array($items)) $items = [];

    foreach ($items as $item) {
        $cantidad = floatval($item['cantidad'] ?? 0);
        $precio   = floatval($item['precio'] ?? 0);
        $unidad   = (string)($item['unidad'] ?? '');
        $total    = $cantidad * $precio;
        $nombre = pdf_text_plain($item['nombre'] ?? '');
        $desc   = pdf_text_plain($item['descripcion'] ?? '');
        
        // Precio original (sin rebajar) — si existe, ocupa línea extra en la tarifa
        $precioOriginal = isset($item['precio_original']) && $item['precio_original'] !== null && $item['precio_original'] > 0
            ? floatval($item['precio_original'])
            : null;
        $hayDescuento = $precioOriginal !== null && $precioOriginal > $precio;

        $descH = 0;
        if ($desc !== '') {
            $descH = $pdf->getStringHeight($cArticulo - 2, $desc, false, true, '', 1);
        }
        // Si hay descuento, la columna tarifa necesita una línea extra para el precio tachado
        $extraTarifaH = $hayDescuento ? 5 : 0;
        $rowH = ($desc !== '') ? (6 + $descH + 2 + $extraTarifaH) : (7 + $extraTarifaH);
        if ($rowH < 7) $rowH = 7;

        $bottomLimit = $pdf->getPageHeight() - 45;
        if (($pdf->GetY() + $rowH) > $bottomLimit) {
            $pdf->AddPage();
            $printTableHeader();
        }
        $rowY = $pdf->GetY();
        if ($rowAlternate) {
            $pdf->SetFillColor(250, 250, 250);
            $pdf->Rect(15, $rowY, $tableW, $rowH, 'F');
        }

        // Nombre artículo
        $pdf->SetXY(17, $rowY + 1);
        $pdf->SetFont('Helvetica', 'B', 8.5);
        $pdf->Cell($cArticulo - 2, 5, $nombre, 0, 0, 'L');

        // Cantidad
        $cantTexto = number_format($cantidad, 2, ',', '.') . ($unidad ? ' ' . $unidad : '');
        $pdf->SetFont('Helvetica', '', 8.5);
        $pdf->Cell($cCantidad, 5, $cantTexto, 0, 0, 'C');

        // Tarifa — con precio original tachado encima si hay descuento
        $tarifaX = 15 + $cArticulo + $cCantidad;
        if ($hayDescuento) {
            // Precio original tachado — alineado a la derecha de la columna, encima del rebajado
            $origTxt = number_format($precioOriginal, 2, ',', '.') . '€';
            $pdf->SetFont('Helvetica', '', 7.5);
            $pdf->SetTextColor(160, 160, 160);
            $origTxtW = $pdf->GetStringWidth($origTxt);
            // Posición X: pegado al borde derecho de la columna Tarifa
            $origX = $tarifaX + $cTarifa - $origTxtW;
            $pdf->SetXY($origX, $rowY + 1);
            $pdf->Cell($origTxtW, 4, $origTxt, 0, 0, 'L');
            // Línea de tachado centrada verticalmente en el texto
            $strikeY = $rowY + 1 + 2.0;
            $pdf->SetLineWidth(0.3);
            $pdf->SetDrawColor(160, 160, 160);
            $pdf->Line($origX, $strikeY, $origX + $origTxtW, $strikeY);

            // Precio con descuento (rosa/brand, negrita, justo debajo)
            $pdf->SetFont('Helvetica', 'B', 8.5);
            $pdf->SetTextColor(233, 30, 140);
            $pdf->SetXY($tarifaX, $rowY + 5);
            $pdf->Cell($cTarifa, 5, number_format($precio, 2, ',', '.') . '€', 0, 0, 'R');
            $pdf->SetTextColor(51, 51, 51);
            $pdf->SetDrawColor(230, 230, 230); // restaurar color de líneas
        } else {
            $pdf->SetFont('Helvetica', '', 8.5);
            $pdf->Cell($cTarifa, 5, number_format($precio, 2, ',', '.') . '€', 0, 0, 'R');
        }

        // Total
        $totalX = 15 + $cArticulo + $cCantidad + $cTarifa;
        $pdf->SetXY($totalX, $rowY + 1);
        $pdf->SetFont('Helvetica', 'B', 8.5);
        $pdf->SetTextColor(51, 51, 51);
        $pdf->Cell($cTotal - 2, 5, number_format($total, 2, ',', '.') . '€', 0, 1, 'R');

        if ($desc !== '') {
            $pdf->SetFont('Helvetica', '', 7.5);
            $pdf->SetTextColor(100, 100, 100);
            $descOffsetY = $hayDescuento ? ($rowY + 10) : ($rowY + 6);
            $pdf->MultiCell($cArticulo - 2, 4, $desc, 0, 'L', false, 0, 17, $descOffsetY, true, 0, false, true);
            $pdf->SetTextColor(51, 51, 51);
        }

        $pdf->SetDrawColor(230, 230, 230);
        $pdf->SetLineWidth(0.2);
        $pdf->Line(15, $rowY + $rowH, 15 + $tableW, $rowY + $rowH);
        $pdf->SetY($rowY + $rowH);
        $rowAlternate = !$rowAlternate;
    }

    // Totales
    $pdf->Ln(4);
    $pdf->SetFont('Helvetica', '', 10);
    $pdf->SetTextColor(51, 51, 51);
    $subtotal        = floatval($presupuesto['subtotal'] ?? 0);
    $iva             = floatval($presupuesto['iva'] ?? 21);
    $segundoImpuesto = floatval($presupuesto['segundo_impuesto'] ?? 0);
    $ivaAmount  = ($subtotal * $iva) / 100;
    $segAmount  = ($subtotal * $segundoImpuesto) / 100;
    $totalFinal = floatval($presupuesto['total'] ?? ($subtotal + $ivaAmount + $segAmount));
    $rightMargin = 15 + $tableW;
    $labelW = 40;
    $valW   = 25;

    // Calcular ahorro total si hay precios originales en los items
    $subtotalSinDescuento = 0;
    $hayDescuentoGlobal   = false;
    foreach (($presupuesto['items'] ?? []) as $it) {
        $cant  = floatval($it['cantidad'] ?? 0);
        $prec  = floatval($it['precio'] ?? 0);
        $orig  = isset($it['precio_original']) && $it['precio_original'] > 0 ? floatval($it['precio_original']) : $prec;
        $subtotalSinDescuento += $cant * $orig;
        if ($orig > $prec) $hayDescuentoGlobal = true;
    }
    $ahorroSubtotal  = $subtotalSinDescuento - $subtotal;
    $ivaAmountOrig   = ($subtotalSinDescuento * $iva) / 100;
    $segAmountOrig   = ($subtotalSinDescuento * $segundoImpuesto) / 100;
    $totalSinDescuento = $subtotalSinDescuento + $ivaAmountOrig + $segAmountOrig;
    $ahorroTotal     = $totalSinDescuento - $totalFinal;

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

    if ($segundoImpuesto > 0) {
        $pdf->SetX($rightMargin - $labelW - $valW);
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->Cell($labelW, 5, 'Segundo Impuesto (' . $segundoImpuesto . '%)', 0, 0, 'R');
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->Cell($valW, 5, number_format($segAmount, 2, ',', '.') . '€', 0, 1, 'R');
    }

    // Fila de ahorro (solo si hay descuentos)
    if ($hayDescuentoGlobal) {
        $pdf->Ln(1);
        $pdf->SetX($rightMargin - $labelW - $valW);
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->SetTextColor(180, 180, 180);
        $pdf->Cell($labelW, 4, 'Precio sin descuento', 0, 0, 'R');
        // Precio total sin descuento tachado
        $origTotalTxt = number_format($totalSinDescuento, 2, ',', '.') . '€';
        $origTotalW = $pdf->GetStringWidth($origTotalTxt);
        $origX = $rightMargin - $origTotalW;
        $origY = $pdf->GetY();
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->SetXY($origX, $origY);
        $pdf->Cell($origTotalW, 4, $origTotalTxt, 0, 1, 'L');
        // Línea de tachado
        $strikeY = $origY + 2.2;
        $pdf->SetLineWidth(0.3);
        $pdf->SetDrawColor(180, 180, 180);
        $pdf->Line($origX, $strikeY, $origX + $origTotalW, $strikeY);
        $pdf->SetDrawColor(230, 230, 230);

        // Fila ahorro en verde
        $pdf->SetX($rightMargin - $labelW - $valW);
        $pdf->SetFont('Helvetica', 'B', 8);
        $pdf->SetTextColor(34, 139, 34);
        $pdf->Cell($labelW, 4, 'Ahorro total', 0, 0, 'R');
        $pdf->Cell($valW, 4, '-' . number_format($ahorroTotal, 2, ',', '.') . '€', 0, 1, 'R');
        $pdf->Ln(1);
    }

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

    // ============================================
    // DETALLES DE LA PROPUESTA
    // ============================================
    $detallesHtml = $presupuesto['detalles_propuesta'] ?? '';
    $hayDetalles = tiene_contenido($detallesHtml);
    
    if ($hayDetalles) {
        $cleanHtml = pdf_html_clean($detallesHtml);
        $detallesTexto = pdf_text_plain($detallesHtml);

        $pdf->Ln(6);
        $pdf->bgEnabled = false;

        // Usar startTransaction para medir sin dejar rastro, luego hacer rollback y redibujar
        $pageAntes  = $pdf->PageNo();
        $yAntes     = $pdf->GetY();
        $bottomLimit = $pdf->getPageHeight() - 45;

        // -- Transaccion de medicion --
        $pdf->startTransaction();
        $pdf->SetAutoPageBreak(false, 0); // Desactivar salto automatico durante medicion
        $pdf->SetXY(20, $yAntes + 10);
        $pdf->SetTextColor(51, 51, 51);
        $pdf->SetFont('Helvetica', '', 8);
        if (!empty($cleanHtml)) {
            $pdf->writeHTMLCell($contentW - 10, 0, 20, $pdf->GetY(), $cleanHtml, 0, 1, false, true, 'L');
        } else {
            $pdf->SetX(20);
            $pdf->MultiCell($contentW - 10, 3.5, $detallesTexto, 0, 'L');
        }
        $yDespues = $pdf->GetY();
        $alturaContenido = $yDespues - $yAntes; // cuanto ocupa en total (titulo + contenido)
        $pdf->rollbackTransaction(true); // deshacer completamente
        $pdf->SetAutoPageBreak(true, 40); // restaurar salto automatico

        // Decidir: cabe en la pagina actual o hay que saltar?
        $espacioDisponible = $bottomLimit - $yAntes;
        if ($alturaContenido > $espacioDisponible) {
            $pdf->AddPage();
        }

        $detallesStartY = $pdf->GetY();

        // -- Escritura real: primero medir endY --
        $pdf->SetXY(20, $detallesStartY + 10);
        $pdf->SetTextColor(51, 51, 51);
        $pdf->SetFont('Helvetica', '', 8);
        if (!empty($cleanHtml)) {
            $pdf->writeHTMLCell($contentW - 10, 0, 20, $pdf->GetY(), $cleanHtml, 0, 1, false, true, 'L');
        } else {
            $pdf->SetX(20);
            $pdf->MultiCell($contentW - 10, 3.5, $detallesTexto, 0, 'L');
        }
        $detallesEndY = $pdf->GetY();
        $detallesH    = $detallesEndY - $detallesStartY + 6;

        // Pintar fondo ajustado al contenido real
        $pdf->SetFillColor(240, 248, 255);
        $pdf->SetDrawColor(240, 248, 255);
        $pdf->Rect(15, $detallesStartY, $contentW, $detallesH, 'F');

        // Reescribir titulo y contenido encima del fondo
        $pdf->SetXY(20, $detallesStartY + 4);
        $pdf->SetTextColor(233, 30, 140);
        $pdf->SetFont('Helvetica', 'B', 11);
        $pdf->Cell($contentW - 10, 5, 'Detalles de la Propuesta', 0, 1, 'L');

        $pdf->SetXY(20, $detallesStartY + 10);
        $pdf->SetTextColor(51, 51, 51);
        $pdf->SetFont('Helvetica', '', 8);
        if (!empty($cleanHtml)) {
            $pdf->writeHTMLCell($contentW - 10, 0, 20, $pdf->GetY(), $cleanHtml, 0, 1, false, true, 'L');
        } else {
            $pdf->SetX(20);
            $pdf->MultiCell($contentW - 10, 3.5, $detallesTexto, 0, 'L');
        }

        $pdf->Ln(4);
    }

    // ============================================
    // NOTAS ADICIONALES
    // ============================================
    $notasHtml = $presupuesto['notas'] ?? '';
    $hayNotas = tiene_contenido($notasHtml);
    
    if ($hayNotas) {
        $pdf->Ln(6);

        $cleanNotasHtml = pdf_html_clean($notasHtml);
        $notasTexto     = pdf_text_plain($notasHtml);

        $yAntesNotas    = $pdf->GetY();
        $bottomLimit2   = $pdf->getPageHeight() - 45;

        // Medir sin dejar rastro usando transaccion
        $pdf->startTransaction();
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->SetXY(20, $yAntesNotas + 10);
        $pdf->SetTextColor(51, 51, 51);
        $pdf->SetFont('Helvetica', '', 8);
        if (!empty($cleanNotasHtml)) {
            $pdf->writeHTMLCell($contentW - 10, 0, 20, $pdf->GetY(), $cleanNotasHtml, 0, 1, false, true, 'L');
        } else {
            $pdf->SetX(20);
            $pdf->MultiCell($contentW - 10, 3.5, $notasTexto, 0, 'L');
        }
        $alturaNotas = $pdf->GetY() - $yAntesNotas;
        $pdf->rollbackTransaction(true);
        $pdf->SetAutoPageBreak(true, 40);

        // Saltar pagina solo si no cabe
        $espacioNotas = $bottomLimit2 - $yAntesNotas;
        if ($alturaNotas > $espacioNotas) {
            $pdf->AddPage();
        }

        $notasStartY = $pdf->GetY();

        // Escritura real
        $pdf->SetXY(20, $notasStartY + 10);
        $pdf->SetTextColor(51, 51, 51);
        $pdf->SetFont('Helvetica', '', 8);
        if (!empty($cleanNotasHtml)) {
            $pdf->writeHTMLCell($contentW - 10, 0, 20, $pdf->GetY(), $cleanNotasHtml, 0, 1, false, true, 'L');
        } else {
            $pdf->SetX(20);
            $pdf->MultiCell($contentW - 10, 3.5, $notasTexto, 0, 'L');
        }
        $notasEndY = $pdf->GetY();
        $notasH    = $notasEndY - $notasStartY + 6;

        // Pintar fondo rosa ajustado
        $pdf->SetFillColor(255, 240, 247);
        $pdf->SetDrawColor(255, 240, 247);
        $pdf->Rect(15, $notasStartY, $contentW, $notasH, 'F');

        // Reescribir titulo y contenido encima del fondo
        $pdf->SetXY(20, $notasStartY + 4);
        $pdf->SetTextColor(233, 30, 140);
        $pdf->SetFont('Helvetica', 'B', 11);
        $pdf->Cell($contentW - 10, 5, 'Notas Adicionales', 0, 1, 'L');

        $pdf->SetXY(20, $notasStartY + 10);
        $pdf->SetTextColor(51, 51, 51);
        $pdf->SetFont('Helvetica', '', 8);
        if (!empty($cleanNotasHtml)) {
            $pdf->writeHTMLCell($contentW - 10, 0, 20, $pdf->GetY(), $cleanNotasHtml, 0, 1, false, true, 'L');
        } else {
            $pdf->SetX(20);
            $pdf->MultiCell($contentW - 10, 3.5, $notasTexto, 0, 'L');
        }

        $pdf->bgEnabled = false;
        $pdf->Ln(4);
    }

    // Determinar modo
    $mode = $_GET['mode'] ?? 'download';
    $filename = 'Presupuesto_' . ($presupuesto['id'] ?? 'SIN_ID') . '.pdf';

    if ($mode === 'save') {
        $tmpFile = sys_get_temp_dir() . '/presupuesto_' . ($presupuesto['id'] ?? 'temp') . '_' . time() . '.pdf';
        $pdf->Output($tmpFile, 'F');
        return $tmpFile;
    } else {
        $pdf->Output($filename, 'D');
        exit;
    }
}

/**
 * Enviar email con PDF adjunto
 * ACTUALIZADO: Soporta múltiples destinatarios separados por comas
 */
function enviarEmail() {
    global $presupuestosFile;

    $id = $_GET['id'] ?? '';
    if (empty($id)) { 
        header('Location: index.php?error=id_invalido'); 
        exit; 
    }

    if (!file_exists($presupuestosFile)) { 
        header('Location: index.php?error=no_encontrado'); 
        exit; 
    }

    $presupuestos = json_decode(file_get_contents($presupuestosFile), true);
    $presupuesto = null;
    $index = -1;

    foreach ($presupuestos as $key => $p) {
        if (($p['id'] ?? '') === $id) { 
            $presupuesto = $p; 
            $index = $key; 
            break; 
        }
    }

    if (!$presupuesto) { 
        header('Location: index.php?error=no_encontrado'); 
        exit; 
    }

    // Generar PDF
    $_GET['mode'] = 'save';
    $tmpFile = generarPDF();
    
    if (!$tmpFile || !file_exists($tmpFile)) {
        header('Location: index.php?error=pdf_no_generado');
        exit;
    }

    // Preparar destinatarios — puede ser "a@a.com,b@b.com"
    $emailRaw = $presupuesto['cliente_email'] ?? '';
    $destinatarios = array_filter(array_map('trim', explode(',', $emailRaw)));
    
    if (empty($destinatarios)) {
        header('Location: index.php?error=email_invalido');
        exit;
    }

    $subject = 'Presupuesto para ' . ($presupuesto['cliente_nombre'] ?? 'su proyecto') . ' - Tictac Comunicación';

    $message = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
        }
        .email-container {
            max-width: 600px;
            margin: 20px auto;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            background: #D72072;
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        .header img {
            max-width: 180px;
            height: auto;
            margin-bottom: 15px;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }
        .content {
            padding: 40px 30px;
        }
        .content p {
            margin: 0 0 15px 0;
        }
        .resumen-box {
            background: #fff5f9;
            border-left: 4px solid #D72072;
            padding: 20px;
            margin: 25px 0;
            border-radius: 5px;
        }
        .resumen-box strong {
            color: #D72072;
        }
        .total-destacado {
            font-size: 24px;
            color: #D72072;
            font-weight: bold;
            margin-top: 10px;
        }
        .footer {
            background: #1a1a1a;
            color: white;
            padding: 30px;
            text-align: center;
            font-size: 13px;
        }
        .footer a {
            color: #D72072;
            text-decoration: none;
        }
        .contacto-info {
            margin-top: 15px;
            line-height: 1.8;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <img src="https://tictac-comunicacion.es/wp-content/uploads/2026/02/LOGO-1-2.png" alt="Tictac Comunicación">
            <h1>Tu Presupuesto Está Listo</h1>
        </div>
        
        <div class="content">
            <p>Estimado/a <strong>' . htmlspecialchars($presupuesto['cliente_nombre'] ?? '') . '</strong>,</p>
            
            <p>Gracias por confiar en Tictac Comunicación Digital. Adjunto encontrarás el presupuesto detallado para tu proyecto con todos los servicios propuestos.</p>
            
            <div class="resumen-box">
                <strong>📋 Resumen del Presupuesto</strong><br><br>
                <strong>Fecha de emisión:</strong> ' . (!empty($presupuesto['fecha_propuesta']) ? date('d/m/Y', strtotime($presupuesto['fecha_propuesta'])) : '') . '<br>
                <strong>Válido hasta:</strong> ' . (!empty($presupuesto['valido_hasta']) ? date('d/m/Y', strtotime($presupuesto['valido_hasta'])) : '') . '<br>
                <div class="total-destacado">Total: ' . number_format(floatval($presupuesto['total'] ?? 0), 2, ',', '.') . ' €</div>
            </div>
            
            <p>Hemos diseñado esta propuesta pensando específicamente en tus necesidades y objetivos. Si tienes alguna duda o quieres comentar cualquier aspecto del presupuesto, estaremos encantados de atenderte.</p>
            
            <p><strong>¿Necesitas más información?</strong><br>
            No dudes en contactarnos. Estamos aquí para ayudarte.</p>
        </div>
        
        <div class="footer">
            <strong>Tictac Comunicación Digital SL</strong>
            <div class="contacto-info">
                📍 Plaza de los Carrillos, 5 · 14001 Córdoba<br>
                📞 <a href="tel:+34957048147">957 048 147</a><br>
                ✉ <a href="mailto:hola@tictac-comunicacion.es">hola@tictac-comunicacion.es</a><br>
                🌐 <a href="https://www.tictac-comunicacion.es" target="_blank">www.tictac-comunicacion.es</a>
            </div>
        </div>
    </div>
</body>
</html>';

    require_once __DIR__ . '/gmail_send.php';
    
    $attachments_array = array(
        array("file_path" => $tmpFile)
    );

    // Enviar a cada destinatario
    $todosEnviados = true;
    $errores = [];

    foreach ($destinatarios as $to) {
        $enviado = enviarEmailGmailAPI($to, $subject, $message, $attachments_array);
        if (!$enviado) {
            $todosEnviados = false;
            $errores[] = $to;
        }
    }
    
    @unlink($tmpFile);

    if ($todosEnviados) {
        if (is_array($presupuestos) && $index >= 0) {
            $presupuestos[$index]['estado'] = 'enviado';
            $presupuestos[$index]['fecha_envio'] = date('Y-m-d H:i:s');
            file_put_contents($presupuestosFile, json_encode($presupuestos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
        
        guardarAuditoria('presupuesto_enviado', 'exitoso', 'Presupuesto enviado vía Gmail API: ' . $id, [
            'destinatarios' => implode(', ', $destinatarios),
            'cliente_nombre' => $presupuesto['cliente_nombre'] ?? ''
        ]);
        
        header('Location: index.php?success=email_enviado');
    } else {
        guardarAuditoria('presupuesto_enviado', 'error', 'Error al enviar email vía Gmail API: ' . $id, [
            'destinatarios_con_error' => implode(', ', $errores),
            'error' => 'Revisar logs del servidor'
        ]);
        header('Location: index.php?error=email_no_enviado');
    }
    exit;
}

/**
 * HTML simple para PDF del email
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
        .precio-tachado { text-decoration: line-through; color: #aaa; font-size: 9px; }
    </style>
    <h2 style="color:#D72072; text-align:center;">PRESUPUESTO - ' . htmlspecialchars($presupuesto['id'] ?? '') . '</h2>
    <p><strong>Cliente:</strong> ' . htmlspecialchars($presupuesto['cliente_nombre'] ?? '') . '</p>
    <p><strong>Fecha:</strong> ' . (!empty($presupuesto['fecha_propuesta']) ? date('d/m/Y', strtotime($presupuesto['fecha_propuesta'])) : '') . ' | <strong>Válido hasta:</strong> ' . (!empty($presupuesto['valido_hasta']) ? date('d/m/Y', strtotime($presupuesto['valido_hasta'])) : '') . '</p>
    <table>
        <tr><th>Artículo</th><th>Cantidad</th><th style="text-align:right;">Tarifa</th><th style="text-align:right;">Total</th></tr>';

    $items = $presupuesto['items'] ?? [];
    if (!is_array($items)) $items = [];

    foreach ($items as $item) {
        $cantidad = floatval($item['cantidad'] ?? 0);
        $precio   = floatval($item['precio'] ?? 0);
        $precioOriginal = isset($item['precio_original']) && $item['precio_original'] > 0 ? floatval($item['precio_original']) : null;
        $itemTotal = $cantidad * $precio;
        $nombre = pdf_text_plain($item['nombre'] ?? '');
        $desc   = pdf_text_plain($item['descripcion'] ?? '');

        $tarifaHtml = '';
        if ($precioOriginal && $precioOriginal > $precio) {
            $tarifaHtml = '<span class="precio-tachado">' . number_format($precioOriginal, 2, ',', '.') . '€</span><br><strong style="color:#D72072;">' . number_format($precio, 2, ',', '.') . '€</strong>';
        } else {
            $tarifaHtml = number_format($precio, 2, ',', '.') . '€';
        }

        $html .= '<tr>
            <td><strong>' . htmlspecialchars($nombre) . '</strong><br><small>' . nl2br(htmlspecialchars($desc)) . '</small></td>
            <td>' . number_format($cantidad, 2, ',', '.') . (!empty($item['unidad']) ? ' ' . htmlspecialchars($item['unidad']) : '') . '</td>
            <td style="text-align:right;">' . $tarifaHtml . '</td>
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