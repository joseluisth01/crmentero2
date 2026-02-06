<?php
/**
 * API Backend - Presupuestos
 * Maneja: Guardar, Eliminar, Generar PDF, Enviar Email
 * ACTUALIZADO: Sincroniza propuestas directamente en BBDD del CRM
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
    
    // Generar public_key único (10 caracteres alfanuméricos)
    $public_key = substr(md5(uniqid(rand(), true)), 0, 10);
    
    // Generar contenido HTML simple para la propuesta
    $content = '<meta charset="UTF-8"><p><strong>Presupuesto: ' . htmlspecialchars($presupuesto['id'] ?? '') . '</strong></p>';
    $content .= '<p>' . nl2br(htmlspecialchars($note)) . '</p>';
    $content = $mysqli->real_escape_string($content);
    
    // CONSULTA SQL EXACTA QUE FUNCIONA PARA PROPUESTAS (probada en phpMyAdmin)
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
    
    // Insertar items en crm_proposal_items
    if (isset($presupuesto['items']) && is_array($presupuesto['items'])) {
        $sort = 0;
        foreach ($presupuesto['items'] as $item) {
            $title = $mysqli->real_escape_string($item['nombre'] ?? '');
            $description = $mysqli->real_escape_string($item['descripcion'] ?? '');
            $quantity = floatval($item['cantidad'] ?? 1);
            $unit_type = $mysqli->real_escape_string($item['unidad'] ?? '');
            $rate = floatval($item['precio'] ?? 0);
            $item_total = $quantity * $rate;
            
            // CONSULTA SQL EXACTA QUE FUNCIONA PARA ITEMS (probada en phpMyAdmin)
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

/**
 * Actualizar propuesta en el CRM
 */
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
    
    // Actualizar content
    $content = '<meta charset="UTF-8"><p><strong>Presupuesto: ' . htmlspecialchars($presupuesto['id'] ?? '') . '</strong></p>';
    $content .= '<p>' . nl2br(htmlspecialchars($note)) . '</p>';
    $content = $mysqli->real_escape_string($content);
    
    // Actualizar proposal
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
    
    // Eliminar items antiguos
    $mysqli->query("DELETE FROM crm_proposal_items WHERE proposal_id = $proposal_id");
    
    // Insertar items actualizados
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

    // ============================================
    // SINCRONIZAR CON EL CRM
    // ============================================
    $crmResult = ['success' => false, 'message' => 'No sincronizado'];
    
    // Solo sincronizar si hay un cliente_id válido
    if (!empty($presupuesto['cliente_id']) && $presupuesto['cliente_id'] !== '') {
        if ($esActualizacion && !empty($presupuesto['crm_proposal_id'])) {
            $crmResult = actualizarPropuestaEnCRM($presupuesto);
        } else {
            $crmResult = crearPropuestaEnCRM($presupuesto);
        }

        // Si se creó correctamente, guardar el ID del CRM
        if ($crmResult['success'] && isset($crmResult['proposal_id'])) {
            foreach ($presupuestos as $key => $p) {
                if ($p['id'] === $id) {
                    $presupuestos[$key]['crm_proposal_id'] = $crmResult['proposal_id'];
                    break;
                }
            }
        }
    }

    // Guardar en JSON
    file_put_contents($presupuestosFile, json_encode($presupuestos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    // Log de auditoría
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
        
        // Buscar el presupuesto para obtener el crm_proposal_id
        $presupuesto_a_eliminar = null;
        foreach ($presupuestos as $p) {
            if ($p['id'] === $id) {
                $presupuesto_a_eliminar = $p;
                break;
            }
        }
        
        // Si tiene crm_proposal_id, eliminarlo también del CRM
        if ($presupuesto_a_eliminar && !empty($presupuesto_a_eliminar['crm_proposal_id'])) {
            $proposal_id = intval($presupuesto_a_eliminar['crm_proposal_id']);
            
            $mysqli = conexionBBDD();
            if ($mysqli) {
                // Marcar como eliminado en el CRM (soft delete)
                $sql = "UPDATE crm_proposals SET deleted = 1 WHERE id = ?";
                $stmt = $mysqli->prepare($sql);
                
                if ($stmt) {
                    $stmt->bind_param("i", $proposal_id);
                    $stmt->execute();
                    $stmt->close();
                    
                    // También marcar items como eliminados
                    $sqlItems = "UPDATE crm_proposal_items SET deleted = 1 WHERE proposal_id = ?";
                    $stmtItems = $mysqli->prepare($sqlItems);
                    
                    if ($stmtItems) {
                        $stmtItems->bind_param("i", $proposal_id);
                        $stmtItems->execute();
                        $stmtItems->close();
                    }
                    
                    $mysqli->close();
                    
                    // Log de auditoría
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
                    // Error en prepared statement
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
                // Si no se puede conectar al CRM, registrar error pero continuar con eliminación local
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
            // Presupuesto solo local (sin sincronización CRM)
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
        
        // Eliminar del JSON local
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
 * (Código completo incluido - 500+ líneas)
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
    }

    $pdf = new TictacPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetAutoPageBreak(true, 40);
    $pdf->SetCreator('Tictac Comunicación');
    $pdf->SetAuthor('Tictac Comunicación Digital SL');
    $pdf->SetTitle('Presupuesto ' . ($presupuesto['id'] ?? ''));
    $pdf->AddPage();
    $pdf->SetMargins(15, 50, 15);
    $pdf->SetY(50);

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

    // Sección "Sobre Nosotros"
    $pdf->SetY($startY + 55);
    $pageW = $pdf->getPageWidth();
    $contentW = $pageW - 30;
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
        $descH = 0;
        if ($desc !== '') {
            $descH = $pdf->getStringHeight($cArticulo - 2, $desc, false, true, '', 1);
        }
        $rowH = ($desc !== '') ? (6 + $descH + 2) : 7;
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
        $pdf->SetXY(17, $rowY + 1);
        $pdf->SetFont('Helvetica', 'B', 8.5);
        $pdf->Cell($cArticulo - 2, 5, $nombre, 0, 0, 'L');
        $cantTexto = number_format($cantidad, 2, ',', '.') . ($unidad ? ' ' . $unidad : '');
        $pdf->SetFont('Helvetica', '', 8.5);
        $pdf->Cell($cCantidad, 5, $cantTexto, 0, 0, 'C');
        $pdf->Cell($cTarifa, 5, number_format($precio, 2, ',', '.') . '€', 0, 0, 'R');
        $pdf->SetFont('Helvetica', 'B', 8.5);
        $pdf->Cell($cTotal - 2, 5, number_format($total, 2, ',', '.') . '€', 0, 1, 'R');
        if ($desc !== '') {
            $pdf->SetFont('Helvetica', '', 7.5);
            $pdf->SetTextColor(100, 100, 100);
            $pdf->MultiCell($cArticulo - 2, 4, $desc, 0, 'L', false, 0, 17, $rowY + 6, true, 0, false, true);
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
    $subtotal       = floatval($presupuesto['subtotal'] ?? 0);
    $iva            = floatval($presupuesto['iva'] ?? 21);
    $segundoImpuesto= floatval($presupuesto['segundo_impuesto'] ?? 0);
    $ivaAmount = ($subtotal * $iva) / 100;
    $segAmount = ($subtotal * $segundoImpuesto) / 100;
    $totalFinal = floatval($presupuesto['total'] ?? ($subtotal + $ivaAmount + $segAmount));
    $rightMargin = 15 + $tableW;
    $labelW = 40;
    $valW   = 25;
    $pdf->SetX($rightMargin - $labelW - $valW);
    $pdf->SetFont('Helvetica', '', 9);
    $pdf->Cell($labelW, 5, 'Sub Total', 0, 0, 'R');
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->Cell($valW, 5, number_format($subtotal, 2, ',', '.') . '€', 0, 1, 'R');
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

    // Notas adicionales
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

    // Determinar modo: descargar o guardar archivo
$mode = $_GET['mode'] ?? 'download';
$filename = 'Presupuesto_' . ($presupuesto['id'] ?? 'SIN_ID') . '.pdf';

if ($mode === 'save') {
    // Modo: guardar archivo temporal para email
    $tmpFile = sys_get_temp_dir() . '/presupuesto_' . ($presupuesto['id'] ?? 'temp') . '_' . time() . '.pdf';
    $pdf->Output($tmpFile, 'F');
    return $tmpFile; // Devolver ruta del archivo
} else {
    // Modo: descargar (comportamiento por defecto)
    $pdf->Output($filename, 'D');
    exit;
}
}

/**
 * Enviar email con PDF adjunto
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

    // ============================================
    // GENERAR PDF PROFESIONAL (reutilizar generarPDF)
    // ============================================
    $_GET['mode'] = 'save'; // Indicar que queremos guardar archivo
    $tmpFile = generarPDF(); // Llamar a la función que ya genera el PDF bonito
    
    if (!$tmpFile || !file_exists($tmpFile)) {
        header('Location: index.php?error=pdf_no_generado');
        exit;
    }

    // ============================================
    // PREPARAR Y ENVIAR EMAIL
    // ============================================
    
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

    $headers = "From: Tictac Comunicación <noreply@" . ($_SERVER['HTTP_HOST'] ?? 'gestion-tictac-comunicacion.es') . ">\r\n";
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
        guardarAuditoria('presupuesto_enviado', 'exitoso', 'Presupuesto enviado: ' . $id, [
            'cliente_email' => $to
        ]);
        header('Location: index.php?success=email_enviado');
    } else {
        guardarAuditoria('presupuesto_enviado', 'error', 'Error al enviar email: ' . $id, [
            'cliente_email' => $to
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