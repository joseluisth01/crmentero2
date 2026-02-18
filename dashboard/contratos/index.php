<?php
/**
 * Contratos - Lista de contratos
 * Sistema Tictac Comunicación
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

// Contadores compatibles con PHP 5.6+
$totalEnviados   = 0;
$totalSincCRM    = 0;
$totalBorradores = 0;
foreach ($contratos as $c) {
    if (isset($c['estado']) && $c['estado'] === 'enviado') $totalEnviados++;
    if (!empty($c['crm_contract_id'])) $totalSincCRM++;
    if (!isset($c['estado']) || $c['estado'] === 'borrador') $totalBorradores++;
}

$additionalStyles = '
<style>
    .action-bar { background:white;padding:20px 30px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.05);margin-bottom:30px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:15px; }
    .btn-primary { background:' . BRAND_COLOR . ';color:white;padding:12px 30px;border-radius:50px;text-decoration:none;font-weight:600;transition:all 0.3s;display:inline-block;border:none;cursor:pointer; }
    .btn-primary:hover { background:' . BRAND_COLOR_DARK . ';transform:translateY(-2px); }
    .stats-bar { background:white;padding:20px 30px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.05);display:flex;justify-content:space-around;margin:30px 0;flex-wrap:wrap;gap:20px; }
    .stat-item { text-align:center; }
    .stat-number { font-size:36px;font-weight:bold;color:' . BRAND_COLOR . '; }
    .stat-label { color:#666;font-size:14px; }
    .table-container { background:white;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.05);overflow-x:auto; }
    table { width:100%;border-collapse:collapse; }
    thead { background:' . BRAND_COLOR . ';color:white; }
    th { padding:15px;text-align:left;font-weight:600; }
    td { padding:15px;border-bottom:1px solid #f0f0f0; }
    tbody tr:hover { background:#f9f9f9; }
    .badge { display:inline-block;padding:5px 12px;border-radius:15px;font-size:12px;font-weight:600;margin:2px; }
    .badge-enviado  { background:#d4edda;color:#155724; }
    .badge-borrador { background:#fff3cd;color:#856404; }
    .badge-crm-sync { background:#d1ecf1;color:#0c5460; }
    .badge-crm-no   { background:#f8d7da;color:#721c24; }
    .actions { display:flex;gap:8px;flex-wrap:wrap; }
    .btn-action { padding:7px 12px;border-radius:5px;text-decoration:none;font-size:13px;font-weight:600;transition:all 0.3s;border:none;cursor:pointer;display:inline-block; }
    .btn-edit   { background:#007bff;color:white; } .btn-edit:hover   { background:#0056b3; }
    .btn-pdf    { background:#dc3545;color:white; } .btn-pdf:hover    { background:#c82333; }
    .btn-email  { background:#28a745;color:white; } .btn-email:hover  { background:#218838; }
    .btn-delete { background:#6c757d;color:white; } .btn-delete:hover { background:#5a6268; }
    .empty-state { text-align:center;padding:60px 20px;color:#999; }
    @media(max-width:768px){ table{min-width:900px;} }
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
        <a href="editor.php" class="btn-primary">+ Nuevo Contrato</a>
    </div>

    <div class="stats-bar">
        <div class="stat-item"><div class="stat-number"><?php echo count($contratos); ?></div><div class="stat-label">Total Contratos</div></div>
        <div class="stat-item"><div class="stat-number"><?php echo $totalEnviados; ?></div><div class="stat-label">Enviados</div></div>
        <div class="stat-item"><div class="stat-number"><?php echo $totalSincCRM; ?></div><div class="stat-label">Sincronizados CRM</div></div>
        <div class="stat-item"><div class="stat-number"><?php echo $totalBorradores; ?></div><div class="stat-label">Borradores</div></div>
    </div>

    <div class="table-container">
        <?php if (count($contratos) > 0): ?>
            <table>
                <thead>
                    <tr><th>ID</th><th>Título</th><th>Cliente</th><th>Fecha contrato</th><th>Válido hasta</th><th>Total</th><th>Estado</th><th>Acciones</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($contratos as $contrato): ?>
                        <tr>
                            <td><strong>#<?php echo htmlspecialchars($contrato['id']); ?></strong></td>
                            <td><strong><?php echo htmlspecialchars(isset($contrato['titulo']) ? $contrato['titulo'] : 'Sin título'); ?></strong></td>
                            <td>
                                <strong><?php echo htmlspecialchars($contrato['cliente_nombre']); ?></strong><br>
                                <small style="color:#666;"><?php echo htmlspecialchars(isset($contrato['cliente_email']) ? $contrato['cliente_email'] : ''); ?></small>
                            </td>
                            <td><?php echo formatearFecha($contrato['fecha_contrato']); ?></td>
                            <td><?php echo formatearFecha($contrato['valido_hasta']); ?></td>
                            <td><strong><?php echo number_format(isset($contrato['total']) ? floatval($contrato['total']) : 0, 2, ',', '.'); ?> €</strong></td>
                            <td>
                                <?php if (isset($contrato['estado']) && $contrato['estado'] === 'enviado'): ?>
                                    <span class="badge badge-enviado">✓ Enviado</span>
                                <?php else: ?>
                                    <span class="badge badge-borrador">⚠ Borrador</span>
                                <?php endif; ?>
                                <br>
                                <?php if (!empty($contrato['crm_contract_id'])): ?>
                                    <span class="badge badge-crm-sync">🔗 CRM #<?php echo $contrato['crm_contract_id']; ?></span>
                                <?php else: ?>
                                    <span class="badge badge-crm-no">✗ Sin CRM</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="actions">
                                    <a href="editor.php?id=<?php echo $contrato['id']; ?>" class="btn-action btn-edit" title="Editar">✏️</a>
                                    <a href="api.php?action=pdf&id=<?php echo $contrato['id']; ?>" class="btn-action btn-pdf" target="_blank" title="PDF">📄</a>
                                    <button onclick="enviarEmail('<?php echo $contrato['id']; ?>')" class="btn-action btn-email" title="Email">📧</button>
                                    <button onclick="eliminar('<?php echo $contrato['id']; ?>')" class="btn-action btn-delete" title="Eliminar">🗑️</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
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
</script>
';
include '../includes/footer.php';
?>