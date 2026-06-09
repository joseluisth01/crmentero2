<?php
/**
 * lleida_sign.php — Endpoint público, PHP puro, sin CI4.
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$log_file = __DIR__ . '/writable/logs/lleida_sign_' . date('Y-m-d') . '.log';

// Capturar cualquier error fatal
set_error_handler(function($errno, $errstr, $errfile, $errline) use ($log_file) {
    file_put_contents($log_file, date('Y-m-d H:i:s') . " PHP_ERROR [$errno] $errstr en $errfile:$errline\n", FILE_APPEND);
    return true;
});
set_exception_handler(function($e) use ($log_file) {
    file_put_contents($log_file, date('Y-m-d H:i:s') . " EXCEPTION: " . $e->getMessage() . " en " . $e->getFile() . ":" . $e->getLine() . "\n", FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
    exit;
});
error_reporting(E_ALL);
ini_set('display_errors', 0);

file_put_contents($log_file, date('Y-m-d H:i:s') . " INICIO POST=" . json_encode($_POST) . "\n", FILE_APPEND);

// ── Parámetros ────────────────────────────────────────────────────
$contract_id = intval($_POST['contract_id'] ?? 0);
$public_key  = trim($_POST['public_key'] ?? '');
$phone       = trim($_POST['phone'] ?? '');
$name        = trim($_POST['name'] ?? '');

if (!$contract_id || !$public_key || !$phone) {
    echo json_encode(['success' => false, 'message' => 'Parámetros incompletos (contract_id, public_key, phone)']);
    exit;
}

// ── Validar móvil ─────────────────────────────────────────────────
$phone_clean = preg_replace('/[\s\-\.]/', '', $phone);
$phone_clean = preg_replace('/^\+34/', '', $phone_clean);
$phone_clean = preg_replace('/^0034/', '', $phone_clean);
if (!preg_match('/^[67]\d{8}$/', $phone_clean)) {
    echo json_encode(['success' => false, 'message' => 'Introduce un número de móvil válido (empieza por 6 o 7)']);
    exit;
}
$phone_e164 = '+34' . $phone_clean;
file_put_contents($log_file, date('Y-m-d H:i:s') . " phone_e164=$phone_e164\n", FILE_APPEND);

// ── BD ────────────────────────────────────────────────────────────
$db_pass = '.[J.1SGH^t_*j)2v';
$mysqli = @new mysqli('localhost', 'gestiontictaccom_user', $db_pass, 'gestiontictaccom_admin');
if ($mysqli->connect_error) {
    file_put_contents($log_file, date('Y-m-d H:i:s') . " BD ERROR: " . $mysqli->connect_error . "\n", FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Error de base de datos']);
    exit;
}
$mysqli->set_charset('utf8mb4');
file_put_contents($log_file, date('Y-m-d H:i:s') . " BD OK\n", FILE_APPEND);

// ── Verificar contrato ────────────────────────────────────────────
$stmt = $mysqli->prepare("SELECT id, title, public_key, status, client_id, meta_data FROM crm_contracts WHERE id=? AND deleted=0 LIMIT 1");
$stmt->bind_param('i', $contract_id);
$stmt->execute();
$contract = $stmt->get_result()->fetch_object();
$stmt->close();

if (!$contract) {
    file_put_contents($log_file, date('Y-m-d H:i:s') . " Contrato #$contract_id no encontrado\n", FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Contrato no encontrado']);
    $mysqli->close(); exit;
}
if ($contract->public_key !== $public_key) {
    file_put_contents($log_file, date('Y-m-d H:i:s') . " Clave incorrecta\n", FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    $mysqli->close(); exit;
}
if ($contract->status === 'accepted') {
    echo json_encode(['success' => false, 'message' => 'Este contrato ya está firmado']);
    $mysqli->close(); exit;
}
file_put_contents($log_file, date('Y-m-d H:i:s') . " Contrato OK: " . $contract->title . "\n", FILE_APPEND);

// ── Obtener PDF via curl interno ──────────────────────────────────
$pdf_url = 'https://gestion-tictac-comunicacion.es/index.php/contract/download_pdf/' . $contract_id . '/' . $public_key;
file_put_contents($log_file, date('Y-m-d H:i:s') . " Descargando PDF: $pdf_url\n", FILE_APPEND);

$ch_pdf = curl_init($pdf_url);
curl_setopt_array($ch_pdf, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 60,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => true,
]);
$pdf_content = curl_exec($ch_pdf);
$pdf_http    = curl_getinfo($ch_pdf, CURLINFO_HTTP_CODE);
$pdf_ctype   = curl_getinfo($ch_pdf, CURLINFO_CONTENT_TYPE);
$pdf_err     = curl_error($ch_pdf);
curl_close($ch_pdf);

file_put_contents($log_file, date('Y-m-d H:i:s') . " PDF HTTP=$pdf_http content_type=$pdf_ctype err=$pdf_err size=" . strlen($pdf_content) . "\n", FILE_APPEND);

if ($pdf_err || $pdf_http !== 200 || stripos($pdf_ctype, 'pdf') === false) {
    echo json_encode(['success' => false, 'message' => "Error generando PDF (HTTP $pdf_http, tipo: $pdf_ctype)"]);
    $mysqli->close(); exit;
}

// ── Lleida credentials ───────────────────────────────────────────
$lleida_user   = 'ticta-comunicacion';
$lleida_apikey = 'nfb70Z9BhqgqpMnJAJRX0ga80tuTqQvW';
$lleida_ep     = 'https://api.lleida.net/cs/v1/';
$webhook_url   = 'https://gestion-tictac-comunicacion.es/lleida_webhook.php';

// ── Obtener o crear config ────────────────────────────────────────
file_put_contents($log_file, date('Y-m-d H:i:s') . " Buscando config Lleida...\n", FILE_APPEND);
$ch = curl_init($lleida_ep . 'get_config_list');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode(['request' => 'GET_CONFIG_LIST', 'user' => $lleida_user, 'status' => 'enabled']),
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json', 'Authorization: x-api-key ' . $lleida_apikey],
    CURLOPT_TIMEOUT        => 20,
]);
$cfg_raw  = curl_exec($ch);
$cfg_resp = @json_decode($cfg_raw, true);
curl_close($ch);
file_put_contents($log_file, date('Y-m-d H:i:s') . " get_config_list: $cfg_raw\n", FILE_APPEND);

$config_id = null;
foreach ($cfg_resp['config'] ?? [] as $cfg) {
    if (($cfg['name'] ?? '') === 'TictacContrato') {
        $config_id = $cfg['config_id'];
        break;
    }
}

// Verificar webhook de la config existente
if ($config_id) {
    $ch = curl_init($lleida_ep . 'get_config');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode(['request' => 'GET_CONFIG', 'user' => $lleida_user, 'config_id' => $config_id]),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json', 'Authorization: x-api-key ' . $lleida_apikey],
        CURLOPT_TIMEOUT        => 15,
    ]);
    $get_raw  = curl_exec($ch);
    $get_resp = @json_decode($get_raw, true);
    curl_close($ch);
    $current_webhook = $get_resp['config']['signatory_cb_url'] ?? '';
    file_put_contents($log_file, date('Y-m-d H:i:s') . " Config $config_id webhook=$current_webhook\n", FILE_APPEND);

    if (strpos($current_webhook, 'lleida_webhook.php') === false) {
        // Desactivar config antigua con webhook incorrecto
        $ch = curl_init($lleida_ep . 'set_config_status');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode(['request' => 'SET_CONFIG_STATUS', 'user' => $lleida_user, 'config_id' => $config_id, 'status' => 'disabled']),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json', 'Authorization: x-api-key ' . $lleida_apikey],
            CURLOPT_TIMEOUT        => 15,
        ]);
        $dis_raw = curl_exec($ch);
        curl_close($ch);
        file_put_contents($log_file, date('Y-m-d H:i:s') . " Desactivada config antigua: $dis_raw\n", FILE_APPEND);
        $config_id = null;
    }
}

if (!$config_id) {
    file_put_contents($log_file, date('Y-m-d H:i:s') . " Creando nueva config...\n", FILE_APPEND);
    $ch = curl_init($lleida_ep . 'set_config');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode([
            'request' => 'SET_CONFIG', 'user' => $lleida_user,
            'config' => [
                'name'                          => 'TictacContrato',
                'expire_lapse'                  => 168,
                'default_sms_sender'            => 'Tictac',
                'signatory_cb_url'              => $webhook_url,
                'registered_company_name'       => 'Tictac Comunicacion Digital SL',
                'registered_company_vat_number' => 'B09912478',
                'registered_langs' => 'ES', 'lang' => 'ES',
                'sms' => [
                    ['registered' => 'Y', 'type' => 'start', 'sender' => 'Tictac', 'text' => 'Hola #name#, tiene un contrato pendiente de firma. Acceda aqui: #url#'],
                    ['registered' => 'Y', 'type' => 'otp',   'sender' => 'Tictac', 'text' => 'Su codigo de firma es: #otp#'],
                ],
                'landing' => [
                    'signature_type' => 'on_sign',
                    'signature_on_sign_required_elements' => ['otp' => 'Y', 'otp_length' => 6],
                    'enable_button' => 'on_open', 'landing_access_max_retries' => 5, 'declinable_signature' => 'N',
                ],
            ],
        ]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json', 'Authorization: x-api-key ' . $lleida_apikey],
        CURLOPT_TIMEOUT    => 30,
    ]);
    $set_raw  = curl_exec($ch);
    $set_resp = @json_decode($set_raw, true);
    curl_close($ch);
    file_put_contents($log_file, date('Y-m-d H:i:s') . " SET_CONFIG: $set_raw\n", FILE_APPEND);
    $config_id = $set_resp['config']['config_id'] ?? null;
}

if (!$config_id) {
    echo json_encode(['success' => false, 'message' => 'Error creando configuración en Click&Sign']);
    $mysqli->close(); exit;
}
file_put_contents($log_file, date('Y-m-d H:i:s') . " config_id=$config_id\n", FILE_APPEND);

// ── START_SIGNATURE ───────────────────────────────────────────────
$name_parts  = explode(' ', trim($name), 2);
$contract_ref = 'CONTRATO #' . $contract_id;
$payload = [
    'request' => 'START_SIGNATURE',
    'request_id' => 'crm-' . $contract_id . '-' . time(),
    'user' => $lleida_user,
    'signature' => [
        'config_id'   => $config_id,
        'contract_id' => 'TICTAC-' . $contract_ref,
        'level' => [[
            'level_order' => 0,
            'required_signatories_to_complete_level' => 1,
            'signatories' => [[
                'phone'       => $phone_e164,
                'name'        => $name_parts[0] ?? 'Cliente',
                'surname'     => $name_parts[1] ?? '',
                'external_id' => '1',
            ]],
        ]],
        'file' => [[
            'filename'        => 'Contrato_' . $contract_id . '.pdf',
            'content'         => base64_encode($pdf_content),
            'file_group'      => 'contract_files',
            'sign_on_landing' => 'Y',
        ]],
    ],
];

file_put_contents($log_file, date('Y-m-d H:i:s') . " Enviando START_SIGNATURE a Lleida...\n", FILE_APPEND);
$ch = curl_init($lleida_ep . 'start_signature');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json', 'Authorization: x-api-key ' . $lleida_apikey],
    CURLOPT_TIMEOUT        => 90,
]);
$resp      = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_err  = curl_error($ch);
curl_close($ch);

file_put_contents($log_file, date('Y-m-d H:i:s') . " START_SIGNATURE HTTP=$http_code err=$curl_err resp=" . substr($resp, 0, 500) . "\n", FILE_APPEND);

if ($curl_err) {
    echo json_encode(['success' => false, 'message' => 'Error de red: ' . $curl_err]);
    $mysqli->close(); exit;
}

$result = @json_decode($resp, true);
if (($result['code'] ?? 0) == 200) {
    $signature_id = $result['signature']['signature_id'] ?? time();
    $meta = @unserialize($contract->meta_data) ?: [];
    $meta['lleida_transaction_id'] = $signature_id;
    $meta['lleida_phone']          = $phone_e164;
    $meta['lleida_sent_at']        = date('Y-m-d H:i:s');
    $meta_s = $mysqli->real_escape_string(serialize($meta));
    $mysqli->query("UPDATE crm_contracts SET meta_data='$meta_s' WHERE id=$contract_id");
    $mysqli->close();
    file_put_contents($log_file, date('Y-m-d H:i:s') . " ÉXITO signature_id=$signature_id\n", FILE_APPEND);
    echo json_encode(['success' => true, 'message' => '¡Perfecto! Recibirás un SMS con el enlace para firmar.']);
} else {
    $err = $result['status'] ?? $result['message'] ?? ('HTTP ' . $http_code);
    file_put_contents($log_file, date('Y-m-d H:i:s') . " ERROR Lleida: $err\n", FILE_APPEND);
    $mysqli->close();
    echo json_encode(['success' => false, 'message' => 'Error Click&Sign: ' . $err]);
}