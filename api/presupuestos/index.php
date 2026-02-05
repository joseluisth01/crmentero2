<?php
/**
 * Presupuestos - Lista de presupuestos
 * Actualizado con sincronización CRM
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

$additionalStyles = '
<style>
    .action-bar {
        background: white;
        padding: 20px 30px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        margin-bottom: 30px;
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
    
    .stats-bar {
        background: white;
        padding: 20px 30px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        display: flex;
        justify-content: space-around;
        margin: 30px 0;
        flex-wrap: wrap;
        gap: 20px;
    }
    
    .stat-item {
        text-align: center;
    }
    
    .stat-number {
        font-size: 36px;
        font-weight: bold;
        color: ' . BRAND_COLOR . ';
    }
    
    .stat-label {
        color: #666;
        font-size: 14px;
    }
    
    .table-container {
        background: white;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        overflow-x: auto;
    }
    
    table {
        width: 100%;
        border-collapse: collapse;
    }
    
    thead {
        background: ' . BRAND_COLOR . ';
        color: white;
    }
    
    th {
        padding: 15px;
        text-align: left;
        font-weight: 600;
    }
    
    td {
        padding: 15px;
        border-bottom: 1px solid #f0f0f0;
    }
    
    tbody tr:hover {
        background: #f9f9f9;
    }
    
    .badge {
        display: inline-block;
        padding: 5px 12px;
        border-radius: 15px;
        font-size: 12px;
        font-weight: 600;
        margin: 2px;
    }
    
    .badge-enviado {
        background: #d4edda;
        color: #155724;
    }
    
    .badge-borrador {
        background: #fff3cd;
        color: #856404;
    }
    
    .badge-crm-sync {
        background: #d1ecf1;
        color: #0c5460;
    }
    
    .badge-crm-no-sync {
        background: #f8d7da;
        color: #721c24;
    }
    
    .actions {
        display: flex;
        gap: 10px;
    }
    
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
    
    .btn-edit {
        background: #007bff;
        color: white;
    }
    
    .btn-edit:hover {
        background: #0056b3;
    }
    
    .btn-pdf {
        background: #dc3545;
        color: white;
    }
    
    .btn-pdf:hover {
        background: #c82333;
    }
    
    .btn-email {
        background: #28a745;
        color: white;
    }
    
    .btn-email:hover {
        background: #218838;
    }
    
    .btn-delete {
        background: #6c757d;
        color: white;
    }
    
    .btn-delete:hover {
        background: #5a6268;
    }
    
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #999;
    }
    
    @media (max-width: 768px) {
        table {
            min-width: 900px;
        }
    }
</style>
';

include '../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>📝 Presupuestos</h1>
        <p>Crea y gestiona presupuestos profesionales para tus clientes</p>
    </div>
    
    <!-- Barra de acción -->
    <div class="action-bar">
        <h3 style="color: <?php echo BRAND_COLOR; ?>; margin: 0;">Mis Presupuestos</h3>
        <a href="editor.php" class="btn-primary">+ Nuevo Presupuesto</a>
    </div>
    
    <!-- Estadísticas -->
    <div class="stats-bar">
        <div class="stat-item">
            <div class="stat-number"><?php echo count($presupuestos); ?></div>
            <div class="stat-label">Total Presupuestos</div>
        </div>
        <div class="stat-item">
            <div class="stat-number">
                <?php 
                echo count(array_filter($presupuestos, function($p) {
                    return isset($p['estado']) && $p['estado'] === 'enviado';
                }));
                ?>
            </div>
            <div class="stat-label">Enviados</div>
        </div>
        <div class="stat-item">
            <div class="stat-number">
                <?php 
                echo count(array_filter($presupuestos, function($p) {
                    return !empty($p['crm_estimate_id']);
                }));
                ?>
            </div>
            <div class="stat-label">Sincronizados CRM</div>
        </div>
        <div class="stat-item">
            <div class="stat-number">
                <?php 
                echo count(array_filter($presupuestos, function($p) {
                    return !isset($p['estado']) || $p['estado'] === 'borrador';
                }));
                ?>
            </div>
            <div class="stat-label">Borradores</div>
        </div>
    </div>
    
    <!-- Tabla de presupuestos -->
    <div class="table-container">
        <?php if (count($presupuestos) > 0): ?>
            <table>
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
                    <?php foreach ($presupuestos as $presupuesto): ?>
                        <tr>
                            <td><strong>#<?php echo htmlspecialchars($presupuesto['id']); ?></strong></td>
                            
                            <td>
                                <strong><?php echo htmlspecialchars($presupuesto['cliente_nombre']); ?></strong><br>
                                <small style="color: #666;"><?php echo htmlspecialchars($presupuesto['cliente_email'] ?? ''); ?></small>
                            </td>
                            
                            <td><?php echo formatearFecha($presupuesto['fecha_propuesta']); ?></td>
                            
                            <td><?php echo formatearFecha($presupuesto['valido_hasta']); ?></td>
                            
                            <td><strong><?php echo number_format($presupuesto['total'], 2, ',', '.'); ?> €</strong></td>
                            
                            <td>
                                <?php if (isset($presupuesto['estado']) && $presupuesto['estado'] === 'enviado'): ?>
                                    <span class="badge badge-enviado">✓ Enviado</span>
                                <?php else: ?>
                                    <span class="badge badge-borrador">⚠ Borrador</span>
                                <?php endif; ?>
                                
                                <br>
                                
                                <?php if (!empty($presupuesto['crm_estimate_id'])): ?>
                                    <span class="badge badge-crm-sync" title="Sincronizado con CRM - ID: <?php echo $presupuesto['crm_estimate_id']; ?>">
                                        🔗 CRM #<?php echo $presupuesto['crm_estimate_id']; ?>
                                    </span>
                                <?php elseif (empty($presupuesto['cliente_id'])): ?>
                                    <span class="badge badge-crm-no-sync" title="No sincronizado: falta cliente del CRM">
                                        ⚠ Sin cliente CRM
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-crm-no-sync" title="No sincronizado con CRM">
                                        ✗ No sincronizado
                                    </span>
                                <?php endif; ?>
                            </td>
                            
                            <td>
                                <div class="actions">
                                    <a href="editor.php?id=<?php echo $presupuesto['id']; ?>" class="btn-action btn-edit" title="Editar">
                                        ✏️
                                    </a>
                                    <a href="api.php?action=pdf&id=<?php echo $presupuesto['id']; ?>" class="btn-action btn-pdf" target="_blank" title="Descargar PDF">
                                        📄
                                    </a>
                                    <button onclick="enviarEmail('<?php echo $presupuesto['id']; ?>')" class="btn-action btn-email" title="Enviar por Email">
                                        📧
                                    </button>
                                    <button onclick="eliminar('<?php echo $presupuesto['id']; ?>')" class="btn-action btn-delete" title="Eliminar">
                                        🗑️
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-state">
                <div style="font-size: 64px; margin-bottom: 20px;">📝</div>
                <h3>No hay presupuestos creados</h3>
                <p>Crea tu primer presupuesto haciendo clic en el botón "Nuevo Presupuesto"</p>
                <a href="editor.php" class="btn-primary" style="margin-top: 20px;">+ Crear Presupuesto</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
$additionalScripts = '
<script>
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
</script>
';

include '../includes/footer.php';
?>