<?php
/**
 * Auditoría - Sistema Tictac
 * Registro de todas las acciones automáticas del sistema
 */

require_once '../config.php';

$pageTitle = 'Auditoría del Sistema';
$showBackButton = true;

// Cargar auditoría
$LOG_FILE = DATA_PATH . '/auditoria.json';
$logs = array();

if (file_exists($LOG_FILE)) {
    $logs = json_decode(file_get_contents($LOG_FILE), true);
    if (!is_array($logs)) $logs = array();
    
    // Ordenar por fecha descendente (más reciente primero)
    $logs = array_reverse($logs);
}

// Filtros
$filtroTipo = isset($_GET['tipo']) ? $_GET['tipo'] : 'todos';
$filtroEstado = isset($_GET['estado']) ? $_GET['estado'] : 'todos';

// Aplicar filtros
$logsFiltrados = array_filter($logs, function($log) use ($filtroTipo, $filtroEstado) {
    $pasaTipo = ($filtroTipo === 'todos' || $log['tipo'] === $filtroTipo);
    $pasaEstado = ($filtroEstado === 'todos' || $log['estado'] === $filtroEstado);
    return $pasaTipo && $pasaEstado;
});

// Estadísticas
$totalRegistros = count($logs);
$emailsEnviados = count(array_filter($logs, function($log) {
    return $log['tipo'] === 'email_bienvenida' && $log['estado'] === 'enviado';
}));
$errores = count(array_filter($logs, function($log) {
    return $log['estado'] === 'error';
}));

// Estilos adicionales
$additionalStyles = '
<style>
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
    
    .filters {
        background: white;
        padding: 20px 30px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        margin-bottom: 30px;
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
        align-items: center;
    }
    
    .filter-button {
        padding: 10px 25px;
        border: 2px solid #ddd;
        background: white;
        border-radius: 50px;
        cursor: pointer;
        font-weight: 600;
        color: #666;
        text-decoration: none;
        transition: all 0.3s;
    }
    
    .filter-button:hover {
        border-color: ' . BRAND_COLOR . ';
        color: ' . BRAND_COLOR . ';
    }
    
    .filter-button.active {
        background: ' . BRAND_COLOR . ';
        color: white;
        border-color: ' . BRAND_COLOR . ';
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
    }
    
    .badge-enviado {
        background: #d4edda;
        color: #155724;
    }
    
    .badge-error {
        background: #f8d7da;
        color: #721c24;
    }
    
    .badge-sistema {
        background: #d1ecf1;
        color: #0c5460;
    }
    
    .badge-ejecutado {
        background: #fff3cd;
        color: #856404;
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
        <h1>📊 Auditoría del Sistema</h1>
        <p>Registro de todas las acciones automáticas</p>
    </div>
    
    <!-- Estadísticas -->
    <div class="stats-bar">
        <div class="stat-item">
            <div class="stat-number"><?php echo $totalRegistros; ?></div>
            <div class="stat-label">Total Registros</div>
        </div>
        <div class="stat-item">
            <div class="stat-number"><?php echo $emailsEnviados; ?></div>
            <div class="stat-label">Emails Enviados</div>
        </div>
        <div class="stat-item">
            <div class="stat-number"><?php echo $errores; ?></div>
            <div class="stat-label">Errores</div>
        </div>
        <div class="stat-item">
            <div class="stat-number"><?php echo count($logsFiltrados); ?></div>
            <div class="stat-label">Mostrando</div>
        </div>
    </div>
    
    <!-- Filtros -->
    <div class="filters">
        <span style="font-weight: 600; color: #666;">Filtrar por tipo:</span>
        <a href="?tipo=todos&estado=<?php echo $filtroEstado; ?>" class="filter-button <?php echo $filtroTipo === 'todos' ? 'active' : ''; ?>">
            Todos
        </a>
        <a href="?tipo=email_bienvenida&estado=<?php echo $filtroEstado; ?>" class="filter-button <?php echo $filtroTipo === 'email_bienvenida' ? 'active' : ''; ?>">
            Emails Bienvenida
        </a>
        <a href="?tipo=sistema&estado=<?php echo $filtroEstado; ?>" class="filter-button <?php echo $filtroTipo === 'sistema' ? 'active' : ''; ?>">
            Sistema
        </a>
        
        <span style="margin-left: 20px; font-weight: 600; color: #666;">Estado:</span>
        <a href="?tipo=<?php echo $filtroTipo; ?>&estado=todos" class="filter-button <?php echo $filtroEstado === 'todos' ? 'active' : ''; ?>">
            Todos
        </a>
        <a href="?tipo=<?php echo $filtroTipo; ?>&estado=enviado" class="filter-button <?php echo $filtroEstado === 'enviado' ? 'active' : ''; ?>">
            Enviados
        </a>
        <a href="?tipo=<?php echo $filtroTipo; ?>&estado=error" class="filter-button <?php echo $filtroEstado === 'error' ? 'active' : ''; ?>">
            Errores
        </a>
    </div>
    
    <!-- Tabla -->
    <div class="table-container">
        <?php if (count($logsFiltrados) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Fecha y Hora</th>
                        <th>Tipo</th>
                        <th>Cliente</th>
                        <th>Email</th>
                        <th>Estado</th>
                        <th>Mensaje</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logsFiltrados as $log): ?>
                        <tr>
                            <td style="white-space: nowrap;">
                                <strong><?php echo date('d/m/Y', strtotime($log['fecha'])); ?></strong><br>
                                <small style="color: #666;"><?php echo date('H:i:s', strtotime($log['fecha'])); ?></small>
                            </td>
                            
                            <td>
                                <?php if ($log['tipo'] === 'email_bienvenida'): ?>
                                    <span class="badge badge-sistema">📧 Email Bienvenida</span>
                                <?php elseif ($log['tipo'] === 'sistema'): ?>
                                    <span class="badge badge-ejecutado">⚙️ Sistema</span>
                                <?php else: ?>
                                    <?php echo htmlspecialchars($log['tipo']); ?>
                                <?php endif; ?>
                            </td>
                            
                            <td>
                                <?php if ($log['cliente_id'] > 0): ?>
                                    <strong>ID <?php echo htmlspecialchars($log['cliente_id']); ?></strong><br>
                                    <?php echo htmlspecialchars($log['cliente_nombre']); ?>
                                <?php else: ?>
                                    <span style="color: #999;">-</span>
                                <?php endif; ?>
                            </td>
                            
                            <td>
                                <?php if (!empty($log['email'])): ?>
                                    <?php echo htmlspecialchars($log['email']); ?>
                                <?php else: ?>
                                    <span style="color: #999;">-</span>
                                <?php endif; ?>
                            </td>
                            
                            <td>
                                <?php if ($log['estado'] === 'enviado'): ?>
                                    <span class="badge badge-enviado">✓ Enviado</span>
                                <?php elseif ($log['estado'] === 'error'): ?>
                                    <span class="badge badge-error">✗ Error</span>
                                <?php elseif ($log['estado'] === 'ejecutado'): ?>
                                    <span class="badge badge-ejecutado">⚙ Ejecutado</span>
                                <?php else: ?>
                                    <?php echo htmlspecialchars($log['estado']); ?>
                                <?php endif; ?>
                            </td>
                            
                            <td>
                                <small><?php echo htmlspecialchars($log['mensaje']); ?></small>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-state">
                <div style="font-size: 64px; margin-bottom: 20px;">📋</div>
                <h3>No hay registros de auditoría</h3>
                <p>Los registros aparecerán cuando el sistema se ejecute automáticamente</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
