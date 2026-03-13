<?php
/**
 * Cron: Importar prospectos desde IMAP
 * Ejecutar: /usr/bin/php /home/gestiontictaccom/public_html/procesosautomaticos/fetch_leads_email.php
 * Frecuencia sugerida: cada 15-30 minutos
 * Formularios soportados:
 *   - NUEVO FORMULARIO DE CONTACTO (nombre, email, teléfono, web, mensaje)
 *   - NUEVA AUDITORÍA GRATUITA SOLICITADA (email, teléfono, web)
 */

// ─── Configuración ────────────────────────────────────────────────────────────
define('IMAP_HOST', '{localhost:143/imap/novalidate-cert}INBOX');
define('IMAP_USER', 'leads@gestion-tictac-comunicacion.es');
define('IMAP_PASS', 'qsfItYTT!;oJ48LM');

define('DB_HOST',   'localhost');
define('DB_USER',   'gestiontictaccom_usercron');
define('DB_PASS',   ']s+tER.&k{ew(!?^');
define('DB_NAME',   'gestiontictaccom_admin');
define('DB_PREFIX', 'crm_');

// ─── Conexión BD ──────────────────────────────────────────────────────────────
$pdo = new PDO(
    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8",
    DB_USER,
    DB_PASS,
    array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
);

$tabla = DB_PREFIX . 'prospectos';

// ─── Conexión IMAP ────────────────────────────────────────────────────────────
$inbox = imap_open(IMAP_HOST, IMAP_USER, IMAP_PASS);

if (!$inbox) {
    echo "ERROR: No se pudo conectar al buzón IMAP\n";
    exit(1);
}

// Buscar ambos tipos de formulario (UNSEEN)
// Nota: imap_search no soporta bien tildes, usamos partes del asunto sin ellas
$emails_1 = imap_search($inbox, 'UNSEEN SUBJECT "NUEVO FORMULARIO"') ?: array();
$emails_2 = imap_search($inbox, 'UNSEEN SUBJECT "NUEVA AUDIT"')      ?: array();

$emails = array_unique(array_merge($emails_1, $emails_2));

if (empty($emails)) {
    echo "Sin emails nuevos.\n";
    imap_close($inbox);
    exit(0);
}

$procesados = 0;
$errores    = 0;

foreach ($emails as $num) {

    try {
        // ── Cabecera ──────────────────────────────────────────────────────────
        $header = imap_headerinfo($inbox, $num);
        $asunto = isset($header->subject)
            ? imap_utf8($header->subject)
            : '';

        $fecha_recepcion = date('Y-m-d H:i:s', $header->udate);

        // ── Cuerpo HTML ───────────────────────────────────────────────────────
        $estructura = imap_fetchstructure($inbox, $num);
        $html_body  = _get_html_part($inbox, $num, $estructura);

        if (!$html_body) {
            // Fallback: texto plano
            $html_body = imap_fetchbody($inbox, $num, 1);
            $html_body = quoted_printable_decode($html_body);
        }

        // ── Parsear campos ────────────────────────────────────────────────────
        $campos = _parsear_email($html_body);

        // Validación mínima: necesitamos al menos email
        if (empty($campos['email'])) {
            echo "Email #$num sin email válido, saltando.\n";
            imap_setflag_full($inbox, (string)$num, "\\Seen");
            continue;
        }

        // ── Evitar duplicados (mismo email + fecha) ───────────────────────────
        $stmt = $pdo->prepare(
            "SELECT id FROM $tabla WHERE email = ? AND DATE(fecha_recepcion) = DATE(?)"
        );
        $stmt->execute(array($campos['email'], $fecha_recepcion));

        if ($stmt->fetch()) {
            echo "Duplicado encontrado para {$campos['email']}, saltando.\n";
            imap_setflag_full($inbox, (string)$num, "\\Seen");
            continue;
        }

        // ── Insertar ──────────────────────────────────────────────────────────
        $now = date('Y-m-d H:i:s');
        $ins = $pdo->prepare("
            INSERT INTO $tabla
                (nombre, email, telefono, web, asunto, mensaje,
                 pagina_origen, fecha_envio, ip, user_agent, privacidad,
                 estado, fecha_recepcion, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'nuevo', ?, ?)
        ");

        $ins->execute(array(
            $campos['nombre'],
            $campos['email'],
            $campos['telefono'],
            $campos['web'],
            $asunto,
            $campos['mensaje'],
            $campos['pagina_origen'],
            $campos['fecha_envio'],
            $campos['ip'],
            $campos['user_agent'],
            $campos['privacidad'],
            $fecha_recepcion,
            $now,
        ));

        // Marcar como leído
        imap_setflag_full($inbox, (string)$num, "\\Seen");
        $procesados++;

        echo "Prospecto guardado: {$campos['nombre']} <{$campos['email']}> [{$asunto}]\n";

    } catch (Exception $e) {
        echo "ERROR procesando email #$num: " . $e->getMessage() . "\n";
        $errores++;
    }
}

imap_close($inbox);
echo "Procesados: $procesados | Errores: $errores\n";


// ─── Funciones auxiliares ─────────────────────────────────────────────────────

/**
 * Extrae la parte HTML del email (multipart o directa)
 */
function _get_html_part($inbox, $num, $estructura, $prefix = '') {
    // Email simple (no multipart)
    if (!isset($estructura->parts)) {
        $encoding = $estructura->encoding;
        $body     = imap_fetchbody($inbox, $num, $prefix ?: '1');
        return _decode_part($body, $encoding);
    }

    // Multipart: buscar text/html
    foreach ($estructura->parts as $idx => $parte) {
        $part_num = ($prefix ? $prefix . '.' : '') . ($idx + 1);

        if ($parte->subtype === 'HTML') {
            $body = imap_fetchbody($inbox, $num, $part_num);
            return _decode_part($body, $parte->encoding);
        }

        // Recursivo para partes anidadas
        if (isset($parte->parts)) {
            $resultado = _get_html_part($inbox, $num, $parte, $part_num);
            if ($resultado) {
                return $resultado;
            }
        }
    }

    return '';
}

/**
 * Decodifica según el encoding de la parte
 */
function _decode_part($body, $encoding) {
    switch ($encoding) {
        case 3: return base64_decode($body);           // BASE64
        case 4: return quoted_printable_decode($body); // QP
        default: return $body;
    }
}

/**
 * Parsea el HTML del email CF7 extrayendo los campos por contexto visual.
 * Soporta dos plantillas:
 *   - Formulario de contacto (nombre, email, teléfono, web, mensaje)
 *   - Formulario de auditoría (email, teléfono, web, privacidad inline)
 */
function _parsear_email($html) {

    $campos = array(
        'nombre'        => '',
        'email'         => '',
        'telefono'      => '',
        'web'           => '',
        'mensaje'       => '',
        'pagina_origen' => '',
        'fecha_envio'   => '',
        'ip'            => '',
        'user_agent'    => '',
        'privacidad'    => '',
    );

    // Convertir etiquetas de bloque a saltos de línea antes de strip_tags
    $html = preg_replace('/<br\s*\/?>/i',  "\n", $html);
    $html = preg_replace('/<\/p>/i',       "\n", $html);
    $html = preg_replace('/<\/div>/i',     "\n", $html);
    $html = preg_replace('/<\/td>/i',      "\n", $html);
    $html = preg_replace('/<\/tr>/i',      "\n", $html);

    // Extraer texto plano
    $texto = strip_tags($html);

    // Normalizar espacios y saltos
    $texto = preg_replace('/[ \t]+/',   ' ',    $texto);
    $texto = preg_replace('/\n{3,}/',   "\n\n", $texto);
    $texto = trim($texto);

    // Dividir en líneas
    $lineas = preg_split('/\n/', $texto);
    $lineas = array_map('trim', $lineas);
    $lineas = array_filter($lineas, function($l) { return $l !== ''; });
    $lineas = array_values($lineas);

    $total = count($lineas);

    for ($i = 0; $i < $total; $i++) {
        $linea    = $lineas[$i];
        $siguiente = isset($lineas[$i + 1]) ? $lineas[$i + 1] : '';

        // Quitar emojis para comparar etiquetas
        $etiqueta = _quitar_emojis($linea);

        // ── Nombre ────────────────────────────────────────────────────────────
        if (stripos($etiqueta, 'Nombre') !== false && strlen($etiqueta) < 20) {
            $campos['nombre'] = $siguiente;
            $i++;

        // ── Email ─────────────────────────────────────────────────────────────
        } elseif (stripos($etiqueta, 'Email') !== false && strlen($etiqueta) < 20) {
            $campos['email'] = $siguiente;
            $i++;

        // ── Teléfono ──────────────────────────────────────────────────────────
        } elseif (
            (stripos($etiqueta, 'Teléfono') !== false || stripos($etiqueta, 'Telefono') !== false)
            && strlen($etiqueta) < 20
        ) {
            $campos['telefono'] = $siguiente;
            $i++;

        // ── Web ───────────────────────────────────────────────────────────────
        } elseif (stripos($etiqueta, 'Web') !== false && strlen($etiqueta) < 10) {
            $campos['web'] = $siguiente;
            $i++;

        // ── Mensaje (multilínea) ──────────────────────────────────────────────
        } elseif (stripos($etiqueta, 'Mensaje') !== false && strlen($etiqueta) < 20 && empty($campos['mensaje'])) {
            $etiquetas_conocidas = array(
                'Información del Envío', 'Informacion del Envio',
                'Página de origen', 'Pagina de origen',
                'Título', 'Titulo', 'Sitio', 'Fecha y hora',
                'IP', 'Navegador', 'Privacidad',
            );
            $msg_lines = array();
            $j = $i + 1;
            while ($j < $total) {
                $candidata   = _quitar_emojis($lineas[$j]);
                $es_etiqueta = false;
                foreach ($etiquetas_conocidas as $et) {
                    if (stripos($candidata, $et) !== false) {
                        $es_etiqueta = true;
                        break;
                    }
                }
                if ($es_etiqueta) break;
                $msg_lines[] = $lineas[$j];
                $j++;
            }
            $campos['mensaje'] = implode("\n", $msg_lines);
            $i = $j - 1;

        // ── Privacidad ────────────────────────────────────────────────────────
        // Puede venir como etiqueta sola (formulario contacto) o
        // inline en la misma línea (formulario auditoría):
        // "Política de Privacidad aceptada: Aceptado: ..."
        } elseif (stripos($etiqueta, 'Privacidad') !== false || stripos($etiqueta, 'Pol') !== false && stripos($etiqueta, 'tica') !== false) {
            if (strpos($linea, ':') !== false && strlen(trim($etiqueta)) > 25) {
                // Valor inline en la misma línea
                $pos = strpos($linea, ':');
                $campos['privacidad'] = trim(substr($linea, $pos + 1));
            } else {
                // Valor en la línea siguiente
                $campos['privacidad'] = $siguiente;
                $i++;
            }

        // ── Página de origen ──────────────────────────────────────────────────
        } elseif (stripos($etiqueta, 'Página de origen') !== false || stripos($etiqueta, 'Pagina de origen') !== false) {
            $campos['pagina_origen'] = $siguiente;
            $i++;

        // ── Fecha y hora ──────────────────────────────────────────────────────
        } elseif (stripos($etiqueta, 'Fecha y hora') !== false) {
            $campos['fecha_envio'] = $siguiente;
            $i++;

        // ── IP ────────────────────────────────────────────────────────────────
        } elseif (preg_match('/^IP\s*$/i', $etiqueta)) {
            $campos['ip'] = $siguiente;
            $i++;

        // ── Navegador ─────────────────────────────────────────────────────────
        } elseif (stripos($etiqueta, 'Navegador') !== false) {
            $campos['user_agent'] = $siguiente;
            $i++;
        }
    }

    // Limpiar valores
    foreach ($campos as $k => $v) {
        $campos[$k] = trim(html_entity_decode($v, ENT_QUOTES, 'UTF-8'));
    }

    return $campos;
}

/**
 * Elimina emojis y símbolos Unicode fuera del rango básico
 */
function _quitar_emojis($str) {
    $str = preg_replace('/[\x{1F000}-\x{1FFFF}]/u', '', $str);
    $str = preg_replace('/[\x{2600}-\x{27FF}]/u',   '', $str);
    return trim($str);
}