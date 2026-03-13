<?php
/**
 * Cron: Importar prospectos desde IMAP
 * Ejecutar: /usr/bin/php /home/gestiontictaccom/public_html/procesosautomaticos/fetch_leads_email.php
 * Frecuencia sugerida: cada 15-30 minutos
 */

define('IMAP_HOST', '{localhost:143/imap/novalidate-cert}INBOX');
define('IMAP_USER', 'leads@gestion-tictac-comunicacion.es');
define('IMAP_PASS', 'qsfItYTT!;oJ48LM');

define('DB_HOST',   'localhost');
define('DB_USER',   'gestiontictaccom_usercron');
define('DB_PASS',   ']s+tER.&k{ew(!?^');
define('DB_NAME',   'gestiontictaccom_admin');
define('DB_PREFIX', 'crm_');

$pdo = new PDO(
    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8",
    DB_USER,
    DB_PASS,
    array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
);

$tabla = DB_PREFIX . 'prospectos';

$inbox = imap_open(IMAP_HOST, IMAP_USER, IMAP_PASS);
if (!$inbox) {
    echo "ERROR: No se pudo conectar al buzón IMAP\n";
    exit(1);
}

$emails_1 = imap_search($inbox, 'UNSEEN SUBJECT "NUEVO FORMULARIO"') ?: array();
$emails_2 = imap_search($inbox, 'UNSEEN SUBJECT "NUEVA AUDIT"')      ?: array();
$emails   = array_unique(array_merge($emails_1, $emails_2));

if (empty($emails)) {
    echo "Sin emails nuevos.\n";
    imap_close($inbox);
    exit(0);
}

$procesados = 0;
$errores    = 0;

foreach ($emails as $num) {
    try {
        $header          = imap_headerinfo($inbox, $num);
        $asunto          = isset($header->subject) ? imap_utf8($header->subject) : '';
        $fecha_recepcion = date('Y-m-d H:i:s', $header->udate);

        $estructura = imap_fetchstructure($inbox, $num);
        $html_body  = _get_html_part($inbox, $num, $estructura);
        if (!$html_body) {
            $html_body = quoted_printable_decode(imap_fetchbody($inbox, $num, 1));
        }

        $campos = _parsear_email($html_body);

        // Log para depuración
        echo "--- Campos parseados para email #$num ---\n";
        foreach ($campos as $k => $v) {
            echo "  $k: " . substr($v, 0, 80) . "\n";
        }
        echo "---\n";

        if (empty($campos['email'])) {
            echo "Email #$num sin email válido, saltando.\n";
            imap_setflag_full($inbox, (string)$num, "\\Seen");
            continue;
        }

        $stmt = $pdo->prepare(
            "SELECT id FROM $tabla WHERE email = ? AND DATE(fecha_recepcion) = DATE(?)"
        );
        $stmt->execute(array($campos['email'], $fecha_recepcion));
        if ($stmt->fetch()) {
            echo "Duplicado para {$campos['email']}, saltando.\n";
            imap_setflag_full($inbox, (string)$num, "\\Seen");
            continue;
        }

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

        imap_setflag_full($inbox, (string)$num, "\\Seen");
        $procesados++;
        echo "Prospecto guardado: {$campos['nombre']} <{$campos['email']}>\n";

    } catch (Exception $e) {
        echo "ERROR procesando email #$num: " . $e->getMessage() . "\n";
        $errores++;
    }
}

imap_close($inbox);
echo "Procesados: $procesados | Errores: $errores\n";


// ─── Funciones ────────────────────────────────────────────────────────────────

function _get_html_part($inbox, $num, $estructura, $prefix = '') {
    if (!isset($estructura->parts)) {
        return _decode_part(imap_fetchbody($inbox, $num, $prefix ?: '1'), $estructura->encoding);
    }
    foreach ($estructura->parts as $idx => $parte) {
        $part_num = ($prefix ? $prefix . '.' : '') . ($idx + 1);
        if ($parte->subtype === 'HTML') {
            return _decode_part(imap_fetchbody($inbox, $num, $part_num), $parte->encoding);
        }
        if (isset($parte->parts)) {
            $r = _get_html_part($inbox, $num, $parte, $part_num);
            if ($r) return $r;
        }
    }
    return '';
}

function _decode_part($body, $encoding) {
    switch ($encoding) {
        case 3: return base64_decode($body);
        case 4: return quoted_printable_decode($body);
        default: return $body;
    }
}

/**
 * Parsea el HTML del email usando la estructura de spans del template:
 *
 * Cada campo tiene:
 *   <span style="...">👤 Nombre</span>      <- etiqueta (font-size:11px, color:#999)
 *   <span style="...">VALOR AQUI</span>      <- valor   (font-size:16px, color:#222)
 *
 * El mensaje y la sección de info de envío usan estructura de tabla con <td>.
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

    // ── Estrategia 1: extraer desde los <span> del template HTML ─────────────
    // El template tiene pares: span etiqueta (11px) + span valor (16px)
    // Extraemos todos los spans con su contenido
    if (preg_match_all('/<span[^>]*>(.*?)<\/span>/is', $html, $m)) {
        $spans = array();
        foreach ($m[1] as $contenido) {
            $texto = trim(strip_tags($contenido));
            $texto = html_entity_decode($texto, ENT_QUOTES, 'UTF-8');
            $texto = trim(preg_replace('/\s+/', ' ', $texto));
            if ($texto !== '') $spans[] = $texto;
        }

        // Buscar pares etiqueta → valor en la lista de spans
        $etiquetas_map = array(
            'Nombre'    => 'nombre',
            'Email'     => 'email',
            'Teléfono'  => 'telefono',
            'Telefono'  => 'telefono',
            'Web'       => 'web',
        );

        for ($i = 0; $i < count($spans) - 1; $i++) {
            $limpio = trim(_quitar_emojis($spans[$i]));
            foreach ($etiquetas_map as $etiqueta => $campo) {
                if (strcasecmp($limpio, $etiqueta) === 0) {
                    // El valor es el siguiente span no vacío
                    $valor = isset($spans[$i + 1]) ? $spans[$i + 1] : '';
                    // Verificar que el valor no sea otra etiqueta conocida
                    $valor_limpio = trim(_quitar_emojis($valor));
                    $es_otra_etiqueta = false;
                    foreach (array_keys($etiquetas_map) as $et) {
                        if (strcasecmp($valor_limpio, $et) === 0) {
                            $es_otra_etiqueta = true;
                            break;
                        }
                    }
                    if (!$es_otra_etiqueta && $valor !== '') {
                        $campos[$campo] = $valor;
                    }
                    break;
                }
            }
        }
    }

    // ── Estrategia 2: extraer mensaje del div de mensaje ─────────────────────
    // El mensaje está en: <div style="background:#fafafa;...">MENSAJE</div>
    // También puede estar en tabla de "Mensaje" 
    if (empty($campos['mensaje'])) {
        // Buscar el div de mensaje (tiene background:#fafafa y font-size:15px)
        if (preg_match('/<div[^>]*font-size:15px[^>]*>(.*?)<\/div>/is', $html, $m)) {
            $msg = strip_tags($m[1]);
            $msg = html_entity_decode($msg, ENT_QUOTES, 'UTF-8');
            $campos['mensaje'] = trim(preg_replace('/\s+/', ' ', $msg));
        }
    }

    // ── Estrategia 3: extraer info de envío desde las filas de tabla ─────────
    // La sección de envío tiene <td>etiqueta</td><td>valor</td>
    if (preg_match('/<tr[^>]*>.*?<\/tr>/is', $html)) {
        // Extraer todas las filas <tr>
        preg_match_all('/<tr[^>]*>(.*?)<\/tr>/is', $html, $filas);
        foreach ($filas[1] as $fila) {
            // Extraer las celdas <td>
            preg_match_all('/<td[^>]*>(.*?)<\/td>/is', $fila, $celdas);
            if (count($celdas[1]) >= 2) {
                $etiqueta = trim(strip_tags($celdas[1][0]));
                $etiqueta = html_entity_decode($etiqueta, ENT_QUOTES, 'UTF-8');
                $etiqueta = trim(_quitar_emojis($etiqueta));

                $valor = trim(strip_tags($celdas[1][1]));
                $valor = html_entity_decode($valor, ENT_QUOTES, 'UTF-8');
                $valor = trim(preg_replace('/\s+/', ' ', $valor));

                if (empty($valor)) continue;

                if (stripos($etiqueta, 'Página de origen') !== false || stripos($etiqueta, 'Pagina de origen') !== false) {
                    $campos['pagina_origen'] = $valor;
                } elseif (stripos($etiqueta, 'Fecha y hora') !== false) {
                    $campos['fecha_envio'] = $valor;
                } elseif (preg_match('/^IP$/i', $etiqueta)) {
                    $campos['ip'] = $valor;
                } elseif (stripos($etiqueta, 'Navegador') !== false) {
                    $campos['user_agent'] = $valor;
                } elseif (stripos($etiqueta, 'Privacidad') !== false || stripos($etiqueta, 'Política') !== false) {
                    $campos['privacidad'] = $valor;
                }
            }
        }
    }

    // ── Fallback: si los spans no funcionaron, intentar texto plano ───────────
    $campos_vacios = array_filter($campos, function($v) { return $v === ''; });
    $campos_criticos_vacios = empty($campos['nombre']) || empty($campos['email']);

    if ($campos_criticos_vacios) {
        $campos = array_merge($campos, _parsear_texto_plano($html, $campos));
    }

    // Limpiar todos los valores finales
    foreach ($campos as $k => $v) {
        $campos[$k] = trim(html_entity_decode($v, ENT_QUOTES, 'UTF-8'));
    }

    return $campos;
}

/**
 * Fallback: parsear como texto plano cuando la estrategia HTML falla.
 * Útil para formularios con plantilla diferente.
 */
function _parsear_texto_plano($html, $campos_actuales) {

    $campos = $campos_actuales;

    $html = preg_replace('/<br\s*\/?>/i', "\n", $html);
    $html = preg_replace('/<\/(?:p|div|td|tr|li)>/i', "\n", $html);
    $texto = strip_tags($html);
    $texto = html_entity_decode($texto, ENT_QUOTES, 'UTF-8');
    $texto = preg_replace('/[ \t]+/', ' ', $texto);
    $texto = preg_replace('/\n{3,}/', "\n\n", $texto);

    $lineas = preg_split('/\n/', $texto);
    $lineas = array_map('trim', $lineas);
    $lineas = array_filter($lineas, function($l) { return $l !== ''; });
    $lineas = array_values($lineas);
    $total  = count($lineas);

    // Etiquetas conocidas para no confundirlas con valores
    $etiquetas_conocidas = array(
        'Nombre','Email','Teléfono','Telefono','Web','Mensaje',
        'Página de origen','Pagina de origen',
        'Información del Envío','Informacion del Envio',
        'Título','Titulo','Sitio','Fecha y hora','IP','Navegador','Privacidad',
        'Nueva Solicitud de Contacto','Datos del Cliente',
    );

    $es_etiqueta = function($str) use ($etiquetas_conocidas) {
        $s = trim(_quitar_emojis($str));
        foreach ($etiquetas_conocidas as $et) {
            if (strcasecmp($s, $et) === 0) return true;
        }
        return false;
    };

    for ($i = 0; $i < $total; $i++) {
        $etiqueta  = trim(_quitar_emojis($lineas[$i]));
        $siguiente = isset($lineas[$i + 1]) ? $lineas[$i + 1] : '';

        $valor = (!empty($siguiente) && !$es_etiqueta($siguiente)) ? $siguiente : '';

        if (strcasecmp($etiqueta, 'Nombre') === 0 && empty($campos['nombre'])) {
            $campos['nombre'] = $valor; if ($valor) $i++;
        } elseif (strcasecmp($etiqueta, 'Email') === 0 && empty($campos['email'])) {
            $campos['email'] = $valor; if ($valor) $i++;
        } elseif (preg_match('/^Telé?fono$/i', $etiqueta) && empty($campos['telefono'])) {
            $campos['telefono'] = $valor; if ($valor) $i++;
        } elseif (strcasecmp($etiqueta, 'Web') === 0 && empty($campos['web'])) {
            // Web puede estar vacía: no consumir si el siguiente es etiqueta
            if ($valor) { $campos['web'] = $valor; $i++; }
        } elseif (strcasecmp($etiqueta, 'Mensaje') === 0 && empty($campos['mensaje'])) {
            $msg_lines = array();
            $j = $i + 1;
            while ($j < $total && !$es_etiqueta($lineas[$j])) {
                $msg_lines[] = $lineas[$j];
                $j++;
            }
            $campos['mensaje'] = implode("\n", $msg_lines);
            $i = $j - 1;
        } elseif ((stripos($etiqueta, 'Página de origen') !== false) && empty($campos['pagina_origen'])) {
            if (!empty($siguiente)) { $campos['pagina_origen'] = $siguiente; $i++; }
        } elseif (stripos($etiqueta, 'Fecha y hora') !== false && empty($campos['fecha_envio'])) {
            $campos['fecha_envio'] = $valor; if ($valor) $i++;
        } elseif (strcasecmp($etiqueta, 'IP') === 0 && empty($campos['ip'])) {
            $campos['ip'] = $valor; if ($valor) $i++;
        } elseif (stripos($etiqueta, 'Navegador') !== false && empty($campos['user_agent'])) {
            // User agent es muy largo, tomarlo siempre
            if (!empty($siguiente)) { $campos['user_agent'] = $siguiente; $i++; }
        } elseif ((stripos($etiqueta, 'Privacidad') !== false || stripos($etiqueta, 'Política') !== false) && empty($campos['privacidad'])) {
            if (!empty($siguiente)) { $campos['privacidad'] = $siguiente; $i++; }
        }
    }

    return $campos;
}

function _quitar_emojis($str) {
    $str = preg_replace('/[\x{1F000}-\x{1FFFF}]/u', '', $str);
    $str = preg_replace('/[\x{2600}-\x{27FF}]/u',   '', $str);
    return trim($str);
}