<?php
/**
 * lleida_webhook.php — Webhook global de Click&Sign. PHP puro, sin CI4.
 */
http_response_code(200);
header('Content-Type: text/plain');

$log_file = __DIR__ . '/writable/logs/lleida_webhook_' . date('Y-m-d') . '.log';
$body     = file_get_contents('php://input');
$data     = !empty($_POST) ? $_POST : (@json_decode($body, true) ?: []);

$status        = strtolower($data['status'] ?? '');
$lleida_cid    = $data['contract_id'] ?? '';
$signature_id  = $data['signature_id'] ?? '';

// Solo procesamos signed y stamp_generated
if (!in_array($status, ['signed', 'stamp_generated'])) {
    echo 'OK'; exit;
}

// Extraer ID numérico del contrato
if (!preg_match('/#(\d+)/', $lleida_cid, $m)) {
    file_put_contents($log_file, date('Y-m-d H:i:s') . " ERROR: no contract_id en '$lleida_cid'\n", FILE_APPEND);
    echo 'OK'; exit;
}
$contract_id = intval($m[1]);

// Conexión BD
$mysqli = @new mysqli('localhost', 'gestiontictaccom_user', '.[J.1SGH^t_*j)2v', 'gestiontictaccom_admin');
if ($mysqli->connect_error) {
    file_put_contents($log_file, date('Y-m-d H:i:s') . ' BD ERROR: ' . $mysqli->connect_error . "\n", FILE_APPEND);
    echo 'OK'; exit;
}
$mysqli->set_charset('utf8mb4');

$stmt = $mysqli->prepare("SELECT id, status, client_id, meta_data FROM crm_contracts WHERE id=? AND deleted=0 LIMIT 1");
$stmt->bind_param('i', $contract_id);
$stmt->execute();
$contract = $stmt->get_result()->fetch_object();
$stmt->close();

if (!$contract) {
    file_put_contents($log_file, date('Y-m-d H:i:s') . " Contrato #$contract_id no encontrado\n", FILE_APPEND);
    $mysqli->close(); echo 'OK'; exit;
}

$meta = @unserialize($contract->meta_data) ?: [];

// ── SIGNED: marcar como aceptado + clonar proyecto tipo ──────────
if ($status === 'signed') {
    if ($contract->status === 'accepted') {
        $mysqli->close(); echo 'OK'; exit;
    }

    $meta['lleida_signed_at']    = date('Y-m-d H:i:s');
    $meta['lleida_signature_id'] = $signature_id;
    $meta_s = $mysqli->real_escape_string(serialize($meta));
    $mysqli->query("UPDATE crm_contracts SET status='accepted', meta_data='$meta_s' WHERE id=$contract_id");
    file_put_contents($log_file, date('Y-m-d H:i:s') . " Contrato #$contract_id marcado como ACEPTADO\n", FILE_APPEND);

    $proyecto_tipo_ids = $meta['proyecto_tipo_ids'] ?? array();
    if (empty($proyecto_tipo_ids) && !empty($meta['proyecto_tipo_id'])) {
        $proyecto_tipo_ids = array($meta['proyecto_tipo_id']);
    }
    $proyecto_nombres = $meta['proyecto_nombres'] ?? array();
    if (empty($proyecto_nombres) && !empty($meta['proyecto_nombre'])) {
        $first_id = reset($proyecto_tipo_ids);
        if ($first_id) $proyecto_nombres[$first_id] = $meta['proyecto_nombre'];
    }

    if (!empty($proyecto_tipo_ids) && $contract->client_id) {
        foreach ($proyecto_tipo_ids as $ptid) {
            $ptid   = intval($ptid);
            if (!$ptid) continue;
            $nombre = trim($proyecto_nombres[$ptid] ?? '');
            _clonar_proyecto($mysqli, $ptid, $nombre, $contract->client_id, $contract_id, $log_file);
        }
    }

    // Notificación
    $ch_n = curl_init('https://gestion-tictac-comunicacion.es/index.php/contracts/lleida_notify_internal/' . $contract_id);
    curl_setopt_array($ch_n, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15]);
    $nr = curl_exec($ch_n); curl_close($ch_n);
    file_put_contents($log_file, date('Y-m-d H:i:s') . " notify_internal: $nr\n", FILE_APPEND);

    // Email fallback
    $cr = $mysqli->query("SELECT company_name FROM crm_clients WHERE id=" . intval($contract->client_id) . " LIMIT 1");
    $cl = $cr ? $cr->fetch_object() : null;
    $cn = $cl->company_name ?? 'Cliente #' . $contract->client_id;
    $fp = $meta['lleida_phone'] ?? 'vía SMS';
    $cu = 'https://gestion-tictac-comunicacion.es/index.php/contracts/view/' . $contract_id;
    $subj = '=?UTF-8?B?' . base64_encode("Contrato firmado SMS: CONTRATO #$contract_id — $cn") . '?=';
    mail('hola@tictac-comunicacion.es', $subj, "Cliente: $cn\nContrato: #$contract_id\nTeléfono: $fp\nVer: $cu",
        "From: hola@tictac-comunicacion.es\r\nContent-Type: text/plain; charset=UTF-8");

    $mysqli->close(); echo 'OK'; exit;
}

// ── STAMP_GENERATED: PDF firmado listo → guardar directamente ────
if ($status === 'stamp_generated') {
    // Si ya lo descargamos, salir
    if (!empty($meta['lleida_signed_pdf_local'])) {
        $mysqli->close(); echo 'OK'; exit;
    }

    $lleida_user   = 'ticta-comunicacion';
    $lleida_apikey = 'nfb70Z9BhqgqpMnJAJRX0ga80tuTqQvW';
    $lleida_ep     = 'https://api.lleida.net/cs/v1/';

    // Obtener file_id
    $ch = curl_init($lleida_ep . 'get_file_list');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['request' => 'GET_FILE_LIST', 'user' => $lleida_user, 'signature_id' => $signature_id, 'file_group' => 'SIGNED']),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json', 'Authorization: x-api-key ' . $lleida_apikey],
        CURLOPT_TIMEOUT => 20,
    ]);
    $files_resp = @json_decode(curl_exec($ch), true); curl_close($ch);

    $file_id = null;
    foreach ($files_resp['file_list']['files'] ?? [] as $f) {
        if (strtolower($f['file_group'] ?? '') === 'signed' && ($f['status'] ?? '') === 'available') {
            $file_id = $f['file_id']; break;
        }
    }

    if (!$file_id) {
        file_put_contents($log_file, date('Y-m-d H:i:s') . " stamp_generated: sin file_id\n", FILE_APPEND);
        $mysqli->close(); echo 'OK'; exit;
    }

    // get_file devuelve el BINARIO directamente (no JSON con URL)
    $ch = curl_init($lleida_ep . 'get_file');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['request' => 'GET_FILE', 'user' => $lleida_user, 'file_id' => $file_id]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json', 'Authorization: x-api-key ' . $lleida_apikey],
        CURLOPT_TIMEOUT => 30,
    ]);
    $pdf_content = curl_exec($ch);
    $http_code   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $ctype       = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    file_put_contents($log_file, date('Y-m-d H:i:s') . " get_file HTTP=$http_code ctype=$ctype size=" . strlen($pdf_content) . "\n", FILE_APPEND);

    // Guardar si es PDF válido
    if (strlen($pdf_content) > 1000 && (str_starts_with($pdf_content, '%PDF') || $http_code === 200)) {
        $dir = __DIR__ . '/uploads/contracts/signed/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $path = $dir . 'contrato_' . $contract_id . '_firmado_lleida.pdf';
        file_put_contents($path, $pdf_content);

        $meta['lleida_signed_pdf_local'] = 'uploads/contracts/signed/contrato_' . $contract_id . '_firmado_lleida.pdf';
        $meta['lleida_signed_pdf_at']    = date('Y-m-d H:i:s');
        $ms = $mysqli->real_escape_string(serialize($meta));
        $mysqli->query("UPDATE crm_contracts SET meta_data='$ms' WHERE id=$contract_id");
        file_put_contents($log_file, date('Y-m-d H:i:s') . " PDF firmado guardado OK size=" . strlen($pdf_content) . "\n", FILE_APPEND);
    } else {
        file_put_contents($log_file, date('Y-m-d H:i:s') . " PDF no válido, content_start=" . substr($pdf_content, 0, 50) . "\n", FILE_APPEND);
    }

    $mysqli->close(); echo 'OK'; exit;
}

$mysqli->close();
echo 'OK';

// ── Función de clonación de proyecto tipo ─────────────────────────
function _clonar_proyecto($mysqli, $proyecto_tipo_id, $nombre_nuevo, $client_id, $contract_id, $log_file) {
    // Delegar la clonación al endpoint interno del CRM que tiene acceso a todos los modelos
    $ch = curl_init('https://gestion-tictac-comunicacion.es/index.php/contract/clonar_proyecto_interno');
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query(array(
            'proyecto_tipo_id' => $proyecto_tipo_id,
            'nombre_nuevo'     => $nombre_nuevo,
            'client_id'        => $client_id,
            'contract_id'      => $contract_id,
            'key'              => 'ea088539d42bf7e87dc7d4b171dfdcf7be3416322cb88eec6a504f701c4bd7dc',
        )),
        CURLOPT_TIMEOUT        => 60,
    ));
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    file_put_contents($log_file, date('Y-m-d H:i:s') . " clonar_proyecto_interno HTTP=$http resp=$resp\n", FILE_APPEND);
}

// Fallback SQL si no hay sesión disponible
function _clonar_proyecto_sql($mysqli, $proyecto_tipo_id, $title, $client_id, $contract_id, $log_file, $orig, $today) {
    $now    = gmdate('Y-m-d H:i:s');
    $t_e    = $mysqli->real_escape_string($title);
    $desc_e = $mysqli->real_escape_string($orig->description ?? '');
    $type_e = $mysqli->real_escape_string($orig->project_type ?? 'client_project');
    $labels = $mysqli->real_escape_string($orig->labels ?? '');
    $price  = floatval($orig->price ?? 0);
    $deadline = 'NULL';
    if ($orig->deadline && $orig->start_date) {
        $diff     = max(0, floor((strtotime($orig->deadline) - strtotime($orig->start_date)) / 86400));
        $deadline = "'" . date('Y-m-d', strtotime($today . ' +' . $diff . ' days')) . "'";
    }
    $mysqli->query("INSERT INTO crm_projects
        (title, description, client_id, start_date, deadline, project_type, price, labels,
         status_id, starred_by, estimate_id, order_id, created_date, created_by, deleted)
        VALUES
        ('$t_e', '$desc_e', $client_id, '$today', $deadline, '$type_e', $price, '$labels',
         1, '', 0, 0, '$now', 1, 0)");
    $new_id = $mysqli->insert_id;
    if ($new_id) {
        $cr_r = $mysqli->query("SELECT meta_data FROM crm_contracts WHERE id=$contract_id LIMIT 1");
        $cr   = $cr_r ? $cr_r->fetch_object() : null;
        $mr   = @unserialize($cr->meta_data ?? '') ?: [];
        $mr['proyecto_clonado_id'] = $new_id;
        $ms2  = $mysqli->real_escape_string(serialize($mr));
        $mysqli->query("UPDATE crm_contracts SET meta_data='$ms2' WHERE id=$contract_id");
        file_put_contents($log_file, date('Y-m-d H:i:s') . " Proyecto fallback SQL #$new_id creado (sin tareas)\n", FILE_APPEND);
    }
}