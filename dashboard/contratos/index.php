<?php
/**
 * Contratos - Lista de contratos
 * Sistema Tictac Comunicación
 * MODIFICADO: Filtro de tipo contrato (Kit Digital / SEPA / Normal) por título o campo kit_digital
 *             Botón "+ Nuevo SEPA" en action-bar
 */

require_once '../config.php';

$pageTitle = 'Contratos';
$showBackButton = true;

$contratosFile = DATA_PATH . '/contratos.json';
$contratos = array();

if (file_exists($contratosFile)) {
    $contratos = json_decode(file_get_contents($contratosFile), true);
    if (!is_array($contratos)) $contratos = array();

    usort($contratos, function($a, $b) {
        return strtotime($b['fecha_creacion']) - strtotime($a['fecha_creacion']);
    });
}

// Contadores
$totalEnviados   = 0;
$totalSincCRM    = 0;
$totalBorradores = 0;
foreach ($contratos as $c) {
    if (isset($c['estado']) && $c['estado'] === 'enviado') $totalEnviados++;
    if (!empty($c['crm_contract_id'])) $totalSincCRM++;
    if (!isset($c['estado']) || $c['estado'] === 'borrador') $totalBorradores++;
}

// Clientes únicos para filtro
$clientesUnicos = array();
foreach ($contratos as $c) {
    $nom = isset($c['cliente_nombre']) ? $c['cliente_nombre'] : '';
    if ($nom && !in_array($nom, $clientesUnicos)) $clientesUnicos[] = $nom;
}
sort($clientesUnicos);

// Helper: detectar tipo de contrato por campo kit_digital o título
function detectarTipoContrato($c) {
    if (!empty($c['kit_digital'])) return 'kit_digital';
    $titulo = strtolower($c['titulo'] ?? '');
    if (strpos($titulo, 'kit digital') !== false) return 'kit_digital';
    if (strpos($titulo, 'sepa') !== false) return 'sepa';
    return 'normal';
}

// URL base CRM
define('CRM_BASE_URL', 'https://gestion-tictac-comunicacion.es/index.php');

$additionalStyles = '
<style>
    .action-bar { background:white;padding:20px 30px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.05);margin-bottom:20px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:15px; }
    .btn-primary { background:' . BRAND_COLOR . ';color:white;padding:12px 30px;border-radius:50px;text-decoration:none;font-weight:600;transition:all 0.3s;display:inline-block;border:none;cursor:pointer; }
    .btn-primary:hover { background:' . BRAND_COLOR_DARK . ';transform:translateY(-2px); }
    .stats-bar { background:white;padding:20px 30px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.05);display:flex;justify-content:space-around;margin:0 0 20px;flex-wrap:wrap;gap:20px; }
    .stat-item { text-align:center; }
    .stat-number { font-size:36px;font-weight:bold;color:' . BRAND_COLOR . '; }
    .stat-label { color:#666;font-size:14px; }
    .filter-bar { background:white;padding:16px 20px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.05);margin-bottom:20px;display:flex;gap:12px;flex-wrap:wrap;align-items:center; }
    .filter-bar input, .filter-bar select { padding:8px 12px;border:1px solid #ddd;border-radius:6px;font-size:13px;color:#333;background:white; }
    .filter-bar input { min-width:200px; }
    .filter-bar select { min-width:150px; }
    .btn-clear { background:#f0f0f0;color:#555;border:none;padding:8px 16px;border-radius:6px;cursor:pointer;font-size:13px;font-weight:600; }
    .btn-clear:hover { background:#e0e0e0; }
    .filter-count { color:#666;font-size:13px;margin-left:auto; }
    .table-container { background:white;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.05);overflow-x:auto; }
    table { width:100%;border-collapse:collapse; }
    thead { background:' . BRAND_COLOR . ';color:white; }
    th { padding:15px;text-align:left;font-weight:600; }
    td { padding:15px;border-bottom:1px solid #f0f0f0; }
    tbody tr:hover { background:#f9f9f9; }
    tbody tr.fila-oculta { display:none; }
    .badge { display:inline-block;padding:5px 12px;border-radius:15px;font-size:12px;font-weight:600;margin:2px; }
    .badge-enviado    { background:#d4edda;color:#155724; }
    .badge-borrador   { background:#fff3cd;color:#856404; }
    .badge-crm-sync   { background:#d1ecf1;color:#0c5460; }
    .badge-crm-no     { background:#f8d7da;color:#721c24; }
    .badge-crm-sync a { color:#0c5460;text-decoration:none;font-weight:600; }
    .badge-crm-sync a:hover { text-decoration:underline; }
    .badge-tipo-kd   { background:#fff3cd;color:#856404;border:1px solid #ffc107; }
    .badge-tipo-sepa { background:#cfe2ff;color:#084298;border:1px solid #9ec5fe; }
    .actions { display:flex;gap:8px;flex-wrap:wrap; }
    .btn-action { padding:7px 12px;border-radius:5px;text-decoration:none;font-size:13px;font-weight:600;transition:all 0.3s;border:none;cursor:pointer;display:inline-block; }
    .btn-edit   { background:#007bff;color:white; } .btn-edit:hover   { background:#0056b3; }
    .btn-pdf    { background:#dc3545;color:white; } .btn-pdf:hover    { background:#c82333; }
    .btn-email  { background:#28a745;color:white; } .btn-email:hover  { background:#218838; }
    .btn-sepa   { background:#17a2b8;color:white; } .btn-sepa:hover   { background:#138496; }
    .btn-delete { background:#6c757d;color:white; } .btn-delete:hover { background:#5a6268; }
    .empty-state { text-align:center;padding:60px 20px;color:#999; }
    @media(max-width:768px){ table{min-width:900px;} .filter-bar{flex-direction:column;} .filter-bar input,.filter-bar select{width:100%;} }
</style>
';

$success = isset($_GET['success']) ? $_GET['success'] : '';
$error   = isset($_GET['error'])   ? $_GET['error']   : '';

include '../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>📄 Contratos</h1>
        <p>Crea y gestiona contratos de servicios para tus clientes</p>
    </div>

    <?php if ($success === 'email_enviado'): ?>
        <div style="background:#d4edda;color:#155724;padding:15px 20px;border-radius:8px;margin-bottom:20px;">✅ Contrato enviado por email correctamente.</div>
    <?php elseif ($success === 'eliminado'): ?>
        <div style="background:#d4edda;color:#155724;padding:15px 20px;border-radius:8px;margin-bottom:20px;">✅ Contrato eliminado correctamente.</div>
    <?php elseif ($error): ?>
        <div style="background:#f8d7da;color:#721c24;padding:15px 20px;border-radius:8px;margin-bottom:20px;">❌ Error: <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="action-bar">
        <h3 style="color:<?php echo BRAND_COLOR; ?>;margin:0;">Mis Contratos</h3>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <a href="editor.php" class="btn-primary">+ Nuevo Contrato</a>
            <a href="sepa.php" class="btn-primary" style="background:#084298;">📋 Nuevo SEPA</a>
        </div>
    </div>

    <div class="stats-bar">
        <div class="stat-item"><div class="stat-number"><?php echo count($contratos); ?></div><div class="stat-label">Total Contratos</div></div>
        <div class="stat-item"><div class="stat-number"><?php echo $totalEnviados; ?></div><div class="stat-label">Enviados</div></div>
        <div class="stat-item"><div class="stat-number"><?php echo $totalSincCRM; ?></div><div class="stat-label">Sync CRM</div></div>
        <div class="stat-item"><div class="stat-number"><?php echo $totalBorradores; ?></div><div class="stat-label">Borradores</div></div>
    </div>

    <!-- Barra de filtros -->
    <div class="filter-bar">
        <input type="text" id="filtro-buscar" placeholder="🔍 Buscar cliente, email, título, ID..." oninput="aplicarFiltros()">
        <select id="filtro-cliente" onchange="aplicarFiltros()">
            <option value="">👤 Todos los clientes</option>
            <?php foreach ($clientesUnicos as $cli): ?>
                <option value="<?php echo htmlspecialchars($cli); ?>"><?php echo htmlspecialchars($cli); ?></option>
            <?php endforeach; ?>
        </select>
        <select id="filtro-tipo" onchange="aplicarFiltros()">
            <option value="">📁 Todos los tipos</option>
            <option value="normal">📄 Normal</option>
            <option value="kit_digital">🇪🇺 Kit Digital</option>
            <option value="sepa">🏦 SEPA</option>
        </select>
        <select id="filtro-estado" onchange="aplicarFiltros()">
            <option value="">📬 Todos los estados</option>
            <option value="enviado">Enviados</option>
            <option value="borrador">Borradores</option>
        </select>
        <select id="filtro-crm" onchange="aplicarFiltros()">
            <option value="">🔗 Todos</option>
            <option value="con">Con cliente CRM</option>
            <option value="sin">Sin cliente CRM</option>
        </select>
        <button class="btn-clear" onclick="resetFiltros()">✕ Limpiar</button>
        <span class="filter-count" id="filter-count"></span>
    </div>

    <div class="table-container">
        <?php if (count($contratos) > 0): ?>
            <table id="tabla-contratos">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Título</th>
                        <th>Cliente</th>
                        <th>Fecha contrato</th>
                        <th>Válido hasta</th>
                        <th>Total</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($contratos as $contrato):
                        $esCRM  = !empty($contrato['crm_contract_id']) ? 'con' : 'sin';
                        $estado = (isset($contrato['estado']) && $contrato['estado'] === 'enviado') ? 'enviado' : 'borrador';
                        $tipo   = detectarTipoContrato($contrato);
                        $textoData = strtolower(
                            ($contrato['cliente_nombre'] ?? '') . ' ' .
                            ($contrato['cliente_email']  ?? '') . ' ' .
                            ($contrato['titulo']         ?? '') . ' ' .
                            ($contrato['id']             ?? '')
                        );
                        // Badge de tipo
                        $tipoBadge = '';
                        if ($tipo === 'kit_digital') $tipoBadge = '<span class="badge badge-tipo-kd">🇪🇺 Kit Digital</span>';
                        elseif ($tipo === 'sepa')     $tipoBadge = '<span class="badge badge-tipo-sepa">🏦 SEPA</span>';

                        // URL SEPA pre-seleccionando cliente
                        $clienteId = $contrato['cliente_id'] ?? '';
                        $urlSepa   = 'sepa.php' . ($clienteId ? '?cliente_id=' . urlencode($clienteId) : '');
                    ?>
                        <tr
                            data-cliente="<?php echo htmlspecialchars($contrato['cliente_nombre'] ?? ''); ?>"
                            data-estado="<?php echo $estado; ?>"
                            data-crm="<?php echo $esCRM; ?>"
                            data-tipo="<?php echo $tipo; ?>"
                            data-texto="<?php echo htmlspecialchars($textoData); ?>"
                        >
                            <td><strong>#<?php echo htmlspecialchars($contrato['id']); ?></strong></td>
                            <td>
                                <strong><?php echo htmlspecialchars(isset($contrato['titulo']) ? $contrato['titulo'] : 'Sin título'); ?></strong>
                                <?php if ($tipoBadge) echo '<br>' . $tipoBadge; ?>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($contrato['cliente_nombre']); ?></strong><br>
                                <small style="color:#666;"><?php echo htmlspecialchars(isset($contrato['cliente_email']) ? $contrato['cliente_email'] : ''); ?></small>
                            </td>
                            <td><?php echo formatearFecha($contrato['fecha_contrato']); ?></td>
                            <td><?php echo formatearFecha($contrato['valido_hasta']); ?></td>
                            <td><strong><?php echo number_format(isset($contrato['total']) ? floatval($contrato['total']) : 0, 2, ',', '.'); ?> €</strong></td>
                            <td>
                                <?php if ($estado === 'enviado'): ?>
                                    <span class="badge badge-enviado">✓ Enviado</span>
                                <?php else: ?>
                                    <span class="badge badge-borrador">⚠ Borrador</span>
                                <?php endif; ?>
                                <br>
                                <?php if (!empty($contrato['crm_contract_id'])): ?>
                                    <span class="badge badge-crm-sync">
                                        🔗 <a href="<?php echo CRM_BASE_URL; ?>/contracts/view/<?php echo intval($contrato['crm_contract_id']); ?>" target="_blank" title="Ver contrato en CRM">CRM #<?php echo $contrato['crm_contract_id']; ?></a>
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-crm-no">✗ Sin CRM</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="actions">
                                    <a href="editor.php?id=<?php echo $contrato['id']; ?>" class="btn-action btn-edit" title="Editar">✏️</a>
                                    <a href="api.php?action=pdf&id=<?php echo $contrato['id']; ?>" class="btn-action btn-pdf" target="_blank" title="PDF">📄</a>
                                    <button onclick="enviarEmail('<?php echo $contrato['id']; ?>')" class="btn-action btn-email" title="Enviar por email">📧</button>
                                    <a href="<?php echo htmlspecialchars($urlSepa); ?>" class="btn-action btn-sepa" title="Generar SEPA para este cliente">🏦</a>
                                    <button onclick="eliminar('<?php echo $contrato['id']; ?>')" class="btn-action btn-delete" title="Eliminar">🗑️</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div id="empty-filtered" style="display:none;text-align:center;padding:40px;color:#999;">
                <div style="font-size:48px;margin-bottom:12px;">🔍</div>
                <p>No hay contratos que coincidan con los filtros aplicados.</p>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div style="font-size:64px;margin-bottom:20px;">📄</div>
                <h3>No hay contratos creados</h3>
                <p>Crea tu primer contrato haciendo clic en "Nuevo Contrato"</p>
                <a href="editor.php" class="btn-primary" style="margin-top:20px;">+ Crear Contrato</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
$additionalScripts = '
<script>
    function enviarEmail(id) {
        if (confirm("¿Enviar este contrato por email al cliente?")) {
            window.location.href = "api.php?action=email&id=" + id;
        }
    }
    function eliminar(id) {
        if (confirm("¿Estás seguro de eliminar este contrato?")) {
            window.location.href = "api.php?action=delete&id=" + id;
        }
    }

    function aplicarFiltros() {
        var buscar  = document.getElementById("filtro-buscar").value.toLowerCase().trim();
        var cliente = document.getElementById("filtro-cliente").value;
        var tipo    = document.getElementById("filtro-tipo").value;
        var estado  = document.getElementById("filtro-estado").value;
        var crm     = document.getElementById("filtro-crm").value;
        var filas   = document.querySelectorAll("#tabla-contratos tbody tr");
        var visibles = 0;

        filas.forEach(function(fila) {
            var okBuscar  = !buscar  || fila.dataset.texto.indexOf(buscar) !== -1;
            var okCliente = !cliente || fila.dataset.cliente === cliente;
            var okTipo    = !tipo    || fila.dataset.tipo === tipo;
            var okEstado  = !estado  || fila.dataset.estado === estado;
            var okCrm     = !crm     || fila.dataset.crm === crm;

            if (okBuscar && okCliente && okTipo && okEstado && okCrm) {
                fila.classList.remove("fila-oculta");
                visibles++;
            } else {
                fila.classList.add("fila-oculta");
            }
        });

        var total   = filas.length;
        var countEl = document.getElementById("filter-count");
        var emptyEl = document.getElementById("empty-filtered");

        countEl.textContent = (buscar || cliente || tipo || estado || crm)
            ? "Mostrando " + visibles + " de " + total
            : "";

        if (emptyEl) emptyEl.style.display = (visibles === 0 && total > 0) ? "block" : "none";
    }

    function resetFiltros() {
        document.getElementById("filtro-buscar").value  = "";
        document.getElementById("filtro-cliente").value = "";
        document.getElementById("filtro-tipo").value    = "";
        document.getElementById("filtro-estado").value  = "";
        document.getElementById("filtro-crm").value     = "";
        aplicarFiltros();
    }
</script>
';
include "../includes/footer.php";
?>