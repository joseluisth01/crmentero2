<?php
/**
 * Presupuestos - Lista con filtros
 */

require_once '../config.php';

$pageTitle = 'Presupuestos';
$showBackButton = true;

// Obtener presupuestos guardados
$presupuestosFile = DATA_PATH . '/presupuestos.json';
$presupuestos = array();

if (file_exists($presupuestosFile)) {
    $presupuestos = json_decode(file_get_contents($presupuestosFile), true);
    if (!is_array($presupuestos)) $presupuestos = array();

    // Ordenar por fecha descendente
    usort($presupuestos, function($a, $b) {
        return strtotime($b['fecha_creacion']) - strtotime($a['fecha_creacion']);
    });
}

// Extraer clientes únicos para el filtro
$clientesUnicos = [];
foreach ($presupuestos as $p) {
    $nombre = trim($p['cliente_nombre'] ?? '');
    if ($nombre && !in_array($nombre, $clientesUnicos)) {
        $clientesUnicos[] = $nombre;
    }
}
sort($clientesUnicos);

$additionalStyles = '
<style>
    .action-bar {
        background: white;
        padding: 20px 30px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        margin-bottom: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
    }

    .btn-primary {
        background: ' . BRAND_COLOR . ';
        color: white;
        padding: 12px 30px;
        border-radius: 50px;
        text-decoration: none;
        font-weight: 600;
        transition: all 0.3s;
        display: inline-block;
        border: none;
        cursor: pointer;
    }
    .btn-primary:hover {
        background: ' . BRAND_COLOR_DARK . ';
        transform: translateY(-2px);
    }

    /* ── FILTROS ───────────────────────────────────── */
    .filtros-bar {
        background: white;
        padding: 18px 24px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        margin-bottom: 20px;
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        align-items: flex-end;
    }
    .filtro-grupo {
        display: flex;
        flex-direction: column;
        gap: 4px;
        flex: 1;
        min-width: 160px;
    }
    .filtro-grupo label {
        font-size: 11px;
        font-weight: 700;
        color: #888;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .filtro-grupo select,
    .filtro-grupo input[type="text"] {
        padding: 9px 12px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        font-size: 14px;
        color: #333;
        background: white;
        transition: border-color 0.2s;
        cursor: pointer;
    }
    .filtro-grupo select:focus,
    .filtro-grupo input[type="text"]:focus {
        outline: none;
        border-color: ' . BRAND_COLOR . ';
    }
    .btn-reset-filtros {
        padding: 9px 20px;
        background: #f0f0f0;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
        color: #666;
        cursor: pointer;
        transition: all 0.2s;
        white-space: nowrap;
        align-self: flex-end;
    }
    .btn-reset-filtros:hover {
        background: #e0e0e0;
        color: #333;
    }
    .filtros-resultado {
        font-size: 13px;
        color: #888;
        align-self: center;
        white-space: nowrap;
    }
    .filtros-resultado strong {
        color: ' . BRAND_COLOR . ';
    }

    /* ── STATS ─────────────────────────────────────── */
    .stats-bar {
        background: white;
        padding: 20px 30px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        display: flex;
        justify-content: space-around;
        margin-bottom: 20px;
        flex-wrap: wrap;
        gap: 20px;
    }
    .stat-item { text-align: center; }
    .stat-number { font-size: 36px; font-weight: bold; color: ' . BRAND_COLOR . '; }
    .stat-label { color: #666; font-size: 14px; }

    /* ── TABLA ─────────────────────────────────────── */
    .table-container {
        background: white;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        overflow-x: auto;
    }
    table { width: 100%; border-collapse: collapse; }
    thead { background: ' . BRAND_COLOR . '; color: white; }
    th { padding: 15px; text-align: left; font-weight: 600; }
    td { padding: 15px; border-bottom: 1px solid #f0f0f0; }
    tbody tr:hover { background: #f9f9f9; }
    tbody tr.fila-oculta { display: none; }

    .badge {
        display: inline-block;
        padding: 5px 12px;
        border-radius: 15px;
        font-size: 12px;
        font-weight: 600;
        margin: 2px;
    }
    .badge-enviado   { background: #d4edda; color: #155724; }
    .badge-borrador  { background: #fff3cd; color: #856404; }
    .badge-crm-sync  { background: #d1ecf1; color: #0c5460; }
    .badge-crm-no-sync { background: #f8d7da; color: #721c24; }

    .actions { display: flex; gap: 10px; }
    .btn-action {
        padding: 8px 15px;
        border-radius: 5px;
        text-decoration: none;
        font-size: 13px;
        font-weight: 600;
        transition: all 0.3s;
        border: none;
        cursor: pointer;
    }
    .btn-edit  { background: #007bff; color: white; }
    .btn-edit:hover  { background: #0056b3; }
    .btn-pdf   { background: #dc3545; color: white; }
    .btn-pdf:hover   { background: #c82333; }
    .btn-email { background: #28a745; color: white; }
    .btn-email:hover { background: #218838; }
    .btn-delete { background: #6c757d; color: white; }
    .btn-delete:hover { background: #5a6268; }

    .empty-state { text-align: center; padding: 60px 20px; color: #999; }
    .empty-filtered {
        text-align: center; padding: 50px 20px; color: #aaa;
        display: none;
        background: white;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        margin-top: 0;
    }

    @media (max-width: 768px) {
        table { min-width: 900px; }
        .filtros-bar { flex-direction: column; }
        .filtro-grupo { min-width: 100%; }
    }
</style>
';

include '../includes/header.php';

$total          = count($presupuestos);
$totalEnviados  = count(array_filter($presupuestos, fn($p) => ($p['estado'] ?? '') === 'enviado'));
$totalCRM       = count(array_filter($presupuestos, fn($p) => !empty($p['crm_proposal_id'])));
$totalBorradores= count(array_filter($presupuestos, fn($p) => ($p['estado'] ?? '') !== 'enviado'));
?>

<div class="container">
    <div class="page-header">
        <h1>📝 Presupuestos</h1>
        <p>Crea y gestiona presupuestos profesionales para tus clientes</p>
    </div>

    <!-- Barra de acción -->
    <div class="action-bar">
        <h3 style="color:<?php echo BRAND_COLOR;?>;margin:0;">Mis Presupuestos</h3>
        <a href="editor.php" class="btn-primary">+ Nuevo Presupuesto</a>
    </div>

    <!-- Estadísticas -->
    <div class="stats-bar">
        <div class="stat-item">
            <div class="stat-number"><?php echo $total; ?></div>
            <div class="stat-label">Total</div>
        </div>
        <div class="stat-item">
            <div class="stat-number"><?php echo $totalEnviados; ?></div>
            <div class="stat-label">Enviados</div>
        </div>
        <div class="stat-item">
            <div class="stat-number"><?php echo $totalBorradores; ?></div>
            <div class="stat-label">Borradores</div>
        </div>
        <div class="stat-item">
            <div class="stat-number"><?php echo $totalCRM; ?></div>
            <div class="stat-label">Sync CRM</div>
        </div>
    </div>

    <!-- ── FILTROS ──────────────────────────────────────── -->
    <div class="filtros-bar">

        <!-- Búsqueda libre -->
        <div class="filtro-grupo">
            <label>🔍 Buscar</label>
            <input type="text" id="filtroTexto" placeholder="Nombre, email, ID..." oninput="aplicarFiltros()">
        </div>

        <!-- Por cliente -->
        <div class="filtro-grupo">
            <label>👤 Cliente</label>
            <select id="filtroCliente" onchange="aplicarFiltros()">
                <option value="">Todos los clientes</option>
                <?php foreach ($clientesUnicos as $c): ?>
                    <option value="<?php echo htmlspecialchars($c); ?>">
                        <?php echo htmlspecialchars($c); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Por estado (enviado / borrador) -->
        <div class="filtro-grupo">
            <label>📬 Estado</label>
            <select id="filtroEstado" onchange="aplicarFiltros()">
                <option value="">Todos</option>
                <option value="enviado">✓ Enviados</option>
                <option value="borrador">⚠ Borradores</option>
            </select>
        </div>

        <!-- Por sincronización CRM -->
        <div class="filtro-grupo">
            <label>🔗 CRM</label>
            <select id="filtroCRM" onchange="aplicarFiltros()">
                <option value="">Todos</option>
                <option value="sync">Con cliente CRM</option>
                <option value="nosync">Sin cliente CRM</option>
            </select>
        </div>

        <button class="btn-reset-filtros" onclick="resetFiltros()">✕ Limpiar</button>
        <span class="filtros-resultado" id="filtrosResultado"></span>
    </div>

    <!-- Tabla -->
    <div class="table-container">
        <?php if ($total > 0): ?>
            <table id="tablaPresupuestos">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Cliente</th>
                        <th>Fecha</th>
                        <th>Válido hasta</th>
                        <th>Total</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($presupuestos as $p):
                        $estado    = $p['estado'] ?? 'borrador';
                        $tieneCRM  = !empty($p['crm_proposal_id']) ? 'sync' : 'nosync';
                        $cliente   = $p['cliente_nombre'] ?? '';
                        $email     = $p['cliente_email'] ?? '';
                        $id        = $p['id'] ?? '';
                    ?>
                    <tr
                        data-cliente="<?php echo htmlspecialchars($cliente); ?>"
                        data-estado="<?php echo $estado; ?>"
                        data-crm="<?php echo $tieneCRM; ?>"
                        data-texto="<?php echo htmlspecialchars(strtolower($cliente . ' ' . $email . ' ' . $id)); ?>"
                    >
                        <td><strong>#<?php echo htmlspecialchars($id); ?></strong></td>

                        <td>
                            <strong><?php echo htmlspecialchars($cliente); ?></strong><br>
                            <small style="color:#666;"><?php echo htmlspecialchars($email); ?></small>
                        </td>

                        <td><?php echo formatearFecha($p['fecha_propuesta']); ?></td>
                        <td><?php echo formatearFecha($p['valido_hasta']); ?></td>

                        <td><strong><?php echo number_format($p['total'], 2, ',', '.'); ?> €</strong></td>

                        <td>
                            <?php if ($estado === 'enviado'): ?>
                                <span class="badge badge-enviado">✓ Enviado</span>
                            <?php else: ?>
                                <span class="badge badge-borrador">⚠ Borrador</span>
                            <?php endif; ?>
                            <br>
                            <?php if (!empty($p['crm_proposal_id'])): ?>
                                <span class="badge badge-crm-sync" title="CRM ID: <?php echo $p['crm_proposal_id']; ?>">
                                    🔗 CRM #<?php echo $p['crm_proposal_id']; ?>
                                </span>
                            <?php elseif (empty($p['cliente_id'])): ?>
                                <span class="badge badge-crm-no-sync" title="Sin cliente CRM">⚠ Sin cliente CRM</span>
                            <?php else: ?>
                                <span class="badge badge-crm-no-sync">✗ No sincronizado</span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <div class="actions">
                                <a href="editor.php?id=<?php echo $id; ?>" class="btn-action btn-edit" title="Editar">✏️</a>
                                <a href="api.php?action=pdf&id=<?php echo $id; ?>" class="btn-action btn-pdf" target="_blank" title="PDF">📄</a>
                                <button onclick="enviarEmail('<?php echo $id; ?>')" class="btn-action btn-email" title="Enviar Email">📧</button>
                                <button onclick="eliminar('<?php echo $id; ?>')" class="btn-action btn-delete" title="Eliminar">🗑️</button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Mensaje sin resultados de filtro -->
            <div class="empty-filtered" id="emptyFiltered">
                <div style="font-size:48px;margin-bottom:12px;">🔍</div>
                <strong>Ningún presupuesto coincide con los filtros</strong><br>
                <span style="font-size:13px;margin-top:6px;display:block;">Prueba cambiando o limpiando los filtros</span>
            </div>

        <?php else: ?>
            <div class="empty-state">
                <div style="font-size:64px;margin-bottom:20px;">📝</div>
                <h3>No hay presupuestos creados</h3>
                <p>Crea tu primer presupuesto haciendo clic en el botón "Nuevo Presupuesto"</p>
                <a href="editor.php" class="btn-primary" style="margin-top:20px;">+ Crear Presupuesto</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
$additionalScripts = '
<script>
function aplicarFiltros() {
    const texto   = document.getElementById("filtroTexto").value.toLowerCase().trim();
    const cliente = document.getElementById("filtroCliente").value;
    const estado  = document.getElementById("filtroEstado").value;
    const crm     = document.getElementById("filtroCRM").value;

    const filas = document.querySelectorAll("#tablaPresupuestos tbody tr");
    let visibles = 0;

    filas.forEach(fila => {
        const okTexto   = !texto   || fila.dataset.texto.includes(texto);
        const okCliente = !cliente || fila.dataset.cliente === cliente;
        const okEstado  = !estado  || fila.dataset.estado === estado;
        const okCRM     = !crm     || fila.dataset.crm === crm;

        const visible = okTexto && okCliente && okEstado && okCRM;
        fila.classList.toggle("fila-oculta", !visible);
        if (visible) visibles++;
    });

    // Mostrar/ocultar mensaje vacío
    const emptyEl = document.getElementById("emptyFiltered");
    const hayFiltro = texto || cliente || estado || crm;
    if (emptyEl) emptyEl.style.display = (hayFiltro && visibles === 0) ? "block" : "none";

    // Contador
    const total = filas.length;
    const resultado = document.getElementById("filtrosResultado");
    if (resultado) {
        if (hayFiltro) {
            resultado.innerHTML = "Mostrando <strong>" + visibles + "</strong> de " + total;
        } else {
            resultado.innerHTML = "";
        }
    }
}

function resetFiltros() {
    document.getElementById("filtroTexto").value = "";
    document.getElementById("filtroCliente").value = "";
    document.getElementById("filtroEstado").value = "";
    document.getElementById("filtroCRM").value = "";
    aplicarFiltros();
}

function enviarEmail(id) {
    if (confirm("¿Enviar este presupuesto por email al cliente?")) {
        window.location.href = "api.php?action=email&id=" + id;
    }
}

function eliminar(id) {
    if (confirm("¿Estás seguro de eliminar este presupuesto?")) {
        window.location.href = "api.php?action=delete&id=" + id;
    }
}

// Aplicar filtros al cargar (por si acaso)
aplicarFiltros();
</script>
';

include "../includes/footer.php";
?>