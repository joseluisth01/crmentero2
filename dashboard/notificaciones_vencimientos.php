<?php
/**
 * ================================================================
 * NOTIFICACIONES DE VENCIMIENTOS - Tictac Comunicación
 * ================================================================
 *
 * Envía alertas por email 7 días antes del vencimiento de:
 *   - Proyectos (status = open)
 *   - Contratos (status != declined)
 *
 * DESTINATARIOS:
 *   hola@tictac-comunicacion.es         → SIEMPRE (todos, Juan del Castillo)
 *   seo@tictac-comunicacion.es          → Proyectos con Pastora García (36) o Equipo SEO LIBRE (261)
 *   produccion@tictac-comunicacion.es   → Proyectos con Carlos Alberto (94) o José Luis Tirado (41)
 *   comunicacion@tictac-comunicacion.es → Proyectos con Natalia RRHH (233)
 *   creativo@tictac-comunicacion.es     → Proyectos con Fernando Ariza (35)
 *
 * INSTALAR EN: /dashboard/notificaciones_vencimientos.php
 *
 * CRON (cada día a las 9:00):
 *   0 9 * * * php /home/gestiontictaccom/public_html/dashboard/notificaciones_vencimientos.php
 * ================================================================
 */

require_once __DIR__ . '/config.php';

// ── CONFIGURACIÓN ─────────────────────────────────────────────────────────

// Email que recibe TODOS los avisos siempre (Juan del Castillo / account manager)
define('EMAIL_GLOBAL', 'hola@tictac-comunicacion.es');

// Días de antelación para el aviso
define('DIAS_AVISO', 7);

// Mapa user_id → email(s) de aviso SOLO para proyectos
$MEMBER_EMAIL_MAP = [
    36  => ['seo@tictac-comunicacion.es'],           // Pastora García
    261 => ['seo@tictac-comunicacion.es'],           // Equipo SEO LIBRE
    94  => ['produccion@tictac-comunicacion.es'],    // Carlos Alberto
    41  => ['produccion@tictac-comunicacion.es'],    // José Luis Tirado
    233 => ['comunicacion@tictac-comunicacion.es'],  // Natalia RRHH
    35  => ['creativo@tictac-comunicacion.es'],      // Fernando Ariza
    // Juan del Castillo (10) ya está cubierto por EMAIL_GLOBAL
];

// ── INICIO ────────────────────────────────────────────────────────────────

$log      = [];
$enviados = 0;

function logMsg(string $msg): void {
    global $log;
    $line = '[' . date('H:i:s') . '] ' . $msg;
    $log[] = $line;
    echo $line . PHP_EOL;
}

logMsg('=== Notificaciones Vencimientos === ' . date('Y-m-d H:i:s'));

$mysqli = conexionBBDD();
if (!$mysqli) {
    logMsg('ERROR CRÍTICO: Sin conexión a BBDD');
    exit(1);
}

$hoy          = date('Y-m-d');
$fechaLimite  = date('Y-m-d', strtotime('+' . DIAS_AVISO . ' days'));
logMsg('Rango: desde ' . $hoy . ' hasta ' . $fechaLimite . ' (próximos ' . DIAS_AVISO . ' días)');

// ── PROYECTOS ─────────────────────────────────────────────────────────────

logMsg('');
logMsg('--- PROYECTOS ---');

$sqlProyectos = "
    SELECT
        p.id,
        p.title,
        p.deadline,
        c.company_name AS cliente
    FROM crm_projects p
    LEFT JOIN crm_clients c ON c.id = p.client_id
    WHERE p.deleted  = 0
      AND p.status   = 'open'
      AND p.deadline >= '{$hoy}'
      AND p.deadline <= '{$fechaLimite}'
    ORDER BY p.title ASC
";

$proyectos = [];
$r = $mysqli->query($sqlProyectos);
if ($r) {
    while ($row = $r->fetch_assoc()) $proyectos[] = $row;
    $r->free();
}
logMsg('Proyectos que vencen en ' . DIAS_AVISO . ' días: ' . count($proyectos));

foreach ($proyectos as $p) {
    $diasReales = (int) ceil((strtotime($p['deadline']) - strtotime('today')) / 86400);
    logMsg('  [' . $p['id'] . '] ' . $p['title'] . ' (' . ($p['cliente'] ?? 'sin cliente') . ') — vence en ' . $diasReales . ' días');

    // Siempre empieza con EMAIL_GLOBAL
    $emailsDestino = [EMAIL_GLOBAL];

    // Añadir emails según miembros asignados al proyecto
    $pid = intval($p['id']);
    $rMiembros = $mysqli->query("
        SELECT user_id
        FROM crm_project_members
        WHERE project_id = $pid AND deleted = 0
    ");
    if ($rMiembros) {
        while ($m = $rMiembros->fetch_assoc()) {
            $uid = intval($m['user_id']);
            if (isset($MEMBER_EMAIL_MAP[$uid])) {
                foreach ($MEMBER_EMAIL_MAP[$uid] as $emailExtra) {
                    if (!in_array($emailExtra, $emailsDestino)) {
                        $emailsDestino[] = $emailExtra;
                    }
                }
            }
        }
        $rMiembros->free();
    }

    logMsg('    Destinatarios: ' . implode(', ', $emailsDestino));

    $asunto = '⚠️ Proyecto vence en ' . $diasReales . ' días: ' . $p['title'];
    $html   = buildEmailHTML('proyecto', $p, $diasReales);

    foreach ($emailsDestino as $to) {
        $ok = enviarEmail($to, $asunto, $html);
        logMsg('      → ' . $to . ': ' . ($ok ? '✅ enviado' : '❌ error'));
        if ($ok) $enviados++;
    }
}

// ── CONTRATOS ─────────────────────────────────────────────────────────────

logMsg('');
logMsg('--- CONTRATOS ---');

$sqlContratos = "
    SELECT
        ct.id,
        ct.title,
        ct.valid_until AS deadline,
        c.company_name AS cliente
    FROM crm_contracts ct
    LEFT JOIN crm_clients c ON c.id = ct.client_id
    WHERE ct.deleted      = 0
      AND ct.status      != 'declined'
      AND ct.valid_until >= '{$hoy}'
      AND ct.valid_until <= '{$fechaLimite}'
    ORDER BY ct.title ASC
";

$contratos = [];
$r = $mysqli->query($sqlContratos);
if ($r) {
    while ($row = $r->fetch_assoc()) $contratos[] = $row;
    $r->free();
}
logMsg('Contratos que vencen en ' . DIAS_AVISO . ' días: ' . count($contratos));

foreach ($contratos as $ct) {
    $diasReales = (int) ceil((strtotime($ct['deadline']) - strtotime('today')) / 86400);
    logMsg('  [' . $ct['id'] . '] ' . $ct['title'] . ' (' . ($ct['cliente'] ?? 'sin cliente') . ') — vence en ' . $diasReales . ' días');

    // Contratos: solo EMAIL_GLOBAL
    $emailsDestino = [EMAIL_GLOBAL];
    logMsg('    Destinatarios: ' . implode(', ', $emailsDestino));

    $asunto = '⚠️ Contrato vence en ' . $diasReales . ' días: ' . $ct['title'];
    $html   = buildEmailHTML('contrato', $ct, $diasReales);

    foreach ($emailsDestino as $to) {
        $ok = enviarEmail($to, $asunto, $html);
        logMsg('      → ' . $to . ': ' . ($ok ? '✅ enviado' : '❌ error'));
        if ($ok) $enviados++;
    }
}

// ── RESUMEN Y LOG ─────────────────────────────────────────────────────────

logMsg('');
logMsg('=== RESUMEN ===');
logMsg('Proyectos procesados : ' . count($proyectos));
logMsg('Contratos procesados : ' . count($contratos));
logMsg('Emails enviados      : ' . $enviados);
logMsg('Fin: ' . date('Y-m-d H:i:s'));

// Guardar log acumulativo en /dashboard/data/log_vencimientos.txt
$logFile = __DIR__ . '/data/log_vencimientos.txt';
file_put_contents(
    $logFile,
    implode(PHP_EOL, $log) . PHP_EOL . str_repeat('-', 60) . PHP_EOL,
    FILE_APPEND
);

// ── FUNCIONES ─────────────────────────────────────────────────────────────

function buildEmailHTML(string $tipo, array $item, int $diasAviso): string
{
    $tipoLabel = $tipo === 'proyecto' ? 'Proyecto' : 'Contrato';
    $tipoEmoji = $tipo === 'proyecto' ? '📁' : '📄';
    $colorTipo = $tipo === 'proyecto' ? '#1a56db' : '#7c3aed';
    $bgTipo    = $tipo === 'proyecto' ? '#e8f0fe' : '#f3e8ff';

    $fechaFmt = !empty($item['deadline'])
        ? (new DateTime($item['deadline']))->format('d/m/Y')
        : 'N/D';

    $cliente = htmlspecialchars($item['cliente'] ?? 'Sin cliente');
    $titulo  = htmlspecialchars($item['title']   ?? 'Sin título');
    $id      = intval($item['id']);

    $urlCRM = $tipo === 'proyecto'
        ? "https://gestion-tictac-comunicacion.es/index.php/projects/view/{$id}"
        : "https://gestion-tictac-comunicacion.es/index.php/contracts/view/{$id}";

    return '<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin:0;padding:0;background:#f5f5f5;font-family:Arial,sans-serif;">
<div style="max-width:600px;margin:30px auto;background:white;border-radius:12px;
            overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.1);">

    <!-- CABECERA -->
    <div style="background:linear-gradient(135deg,#E91E8C 0%,#C91E82 100%);padding:32px 30px;text-align:center;">
        <img src="https://tictac-comunicacion.es/wp-content/uploads/2026/02/LOGO-1-2.png"
             alt="Tictac Comunicación"
             style="max-width:155px;height:auto;display:block;margin:0 auto 12px;">
        <span style="color:rgba(255,255,255,0.85);font-size:12px;letter-spacing:2px;text-transform:uppercase;">
            Sistema de Gestión · Aviso Automático
        </span>
    </div>

    <!-- ALERTA -->
    <div style="background:#fff8e1;border-left:5px solid #f59e0b;padding:16px 28px;">
        <table style="width:100%;border-collapse:collapse;"><tr>
            <td style="width:44px;vertical-align:middle;font-size:28px;">⚠️</td>
            <td style="vertical-align:middle;padding-left:12px;">
                <strong style="color:#92400e;font-size:15px;">Vencimiento próximo — ' . $diasAviso . ' días</strong><br>
                <span style="color:#b45309;font-size:13px;">Contacta con el cliente para gestionar la renovación.</span>
            </td>
        </tr></table>
    </div>

    <!-- CONTENIDO -->
    <div style="padding:28px 30px 22px;">

        <span style="display:inline-block;background:' . $bgTipo . ';color:' . $colorTipo . ';
                     padding:4px 14px;border-radius:20px;font-size:11px;font-weight:700;
                     text-transform:uppercase;letter-spacing:1px;margin-bottom:16px;">
            ' . $tipoEmoji . ' ' . $tipoLabel . '
        </span>

        <h2 style="margin:0 0 5px 0;color:#1f2937;font-size:20px;line-height:1.3;">' . $titulo . '</h2>
        <p style="margin:0 0 22px 0;color:#6b7280;font-size:14px;">' . $cliente . '</p>

        <!-- DATOS -->
        <table style="width:100%;border-collapse:collapse;margin-bottom:26px;
                      border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;">
            <tr>
                <td style="padding:12px 16px;background:#f9fafb;border-bottom:1px solid #e5e7eb;
                           font-size:13px;color:#6b7280;font-weight:600;width:45%;">📅 Fecha vencimiento</td>
                <td style="padding:12px 16px;background:#f9fafb;border-bottom:1px solid #e5e7eb;
                           font-size:16px;color:#dc2626;font-weight:700;">' . $fechaFmt . '</td>
            </tr>
            <tr>
                <td style="padding:12px 16px;background:#fff;border-bottom:1px solid #e5e7eb;
                           font-size:13px;color:#6b7280;font-weight:600;">⏰ Días restantes</td>
                <td style="padding:12px 16px;background:#fff;border-bottom:1px solid #e5e7eb;
                           font-size:16px;color:#dc2626;font-weight:700;">' . $diasAviso . ' días</td>
            </tr>
            <tr>
                <td style="padding:12px 16px;background:#f9fafb;font-size:13px;color:#6b7280;font-weight:600;">🏢 Cliente</td>
                <td style="padding:12px 16px;background:#f9fafb;font-size:15px;color:#1f2937;font-weight:600;">' . $cliente . '</td>
            </tr>
        </table>

        <!-- BOTÓN -->
        <div style="text-align:center;">
            <a href="' . $urlCRM . '"
               style="display:inline-block;background:#E91E8C;color:white;text-decoration:none;
                      padding:13px 34px;border-radius:50px;font-weight:700;font-size:14px;letter-spacing:0.5px;">
                Ver en el CRM →
            </a>
        </div>

    </div>

    <!-- PIE -->
    <div style="background:#111827;color:white;padding:20px 30px;text-align:center;margin-top:10px;">
        <strong style="color:#E91E8C;font-size:13px;">Tictac Comunicación Digital SL</strong><br>
        <span style="color:#9ca3af;font-size:12px;line-height:2.2;">
            Plaza de los Carrillos, 5 · 14001 Córdoba<br>
            957 048 147 · hola@tictac-comunicacion.es · www.tictac-comunicacion.es
        </span>
    </div>

</div>
</body>
</html>';
}

function enviarEmail(string $to, string $subject, string $htmlBody): bool
{
    $fromEmail = 'hola@tictac-comunicacion.es';
    $fromName  = 'Tictac Comunicación';

    $headers  = "From: {$fromName} <{$fromEmail}>\r\n";
    $headers .= "Reply-To: {$fromEmail}\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "X-Mailer: Tictac-Vencimientos/1.0\r\n";

    return mail($to, $subject, $htmlBody, $headers);
}