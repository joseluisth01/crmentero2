<?php
/**
 * Gestión de Clientes - Sistema Tictac
 * Lista completa de clientes con filtros por grupo
 */

require_once '../config.php';

$pageTitle = 'Gestión de Clientes';
$showBackButton = true;

// Obtener filtro (por defecto: con mantenimiento)
$filtro = isset($_GET['filtro']) ? $_GET['filtro'] : 'con_mantenimiento';

// Función para obtener clientes
function obtenerClientes($limite = 200) {
    $clientes = array();
    
    for ($id = 1; $id <= $limite; $id++) {
        $cliente = callCrmApi('clients/' . $id);
        if ($cliente) {
            $clientes[] = $cliente;
        }
    }
    
    return $clientes;
}

// Obtener todos los clientes
$todosLosClientes = obtenerClientes(200);

// Filtrar clientes y calcular estadísticas
$clientesFiltrados = array();
$totalConMantenimiento = 0;
$totalSinMantenimiento = 0;
$totalInactivos = 0;

foreach ($todosLosClientes as $cliente) {
    $tipo = getTipoCliente($cliente);
    
    if ($tipo === 'con_mantenimiento') {
        $totalConMantenimiento++;
    } elseif ($tipo === 'sin_mantenimiento') {
        $totalSinMantenimiento++;
    } elseif ($tipo === 'inactivo') {
        $totalInactivos++;
    }
    
    // Aplicar filtro
    if ($filtro === 'todos' || $filtro === $tipo) {
        $clientesFiltrados[] = $cliente;
    }
}

$totalGeneral = count($todosLosClientes);

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
    
    .client-name {
        color: ' . BRAND_COLOR . ';
        cursor: pointer;
        font-weight: 600;
    }
    
    .client-name:hover {
        color: ' . BRAND_COLOR_DARK . ';
        text-decoration: underline;
    }
    
    .badge {
        display: inline-block;
        padding: 5px 12px;
        border-radius: 15px;
        font-size: 12px;
        font-weight: 600;
    }
    
    .badge-con-mant {
        background: #d4edda;
        color: #155724;
    }
    
    .badge-sin-mant {
        background: #fff3cd;
        color: #856404;
    }
    
    .badge-inactive {
        background: #f8d7da;
        color: #721c24;
    }
    
    /* Modal */
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
    }
    
    .modal-content {
        background: white;
        margin: 50px auto;
        padding: 0;
        border-radius: 15px;
        width: 90%;
        max-width: 600px;
        box-shadow: 0 10px 50px rgba(0,0,0,0.3);
        max-height: 90vh;
        overflow-y: auto;
    }
    
    .modal-header {
        background: linear-gradient(135deg, ' . BRAND_COLOR . ' 0%, ' . BRAND_COLOR_DARK . ' 100%);
        color: white;
        padding: 20px;
        border-radius: 15px 15px 0 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        position: sticky;
        top: 0;
        z-index: 10;
    }
    
    .modal-header h2 {
        font-weight: 300;
        font-size: 24px;
    }
    
    .close {
        color: white;
        font-size: 32px;
        cursor: pointer;
        line-height: 1;
    }
    
    .close:hover {
        opacity: 0.8;
    }
    
    .modal-body {
        padding: 30px;
    }
    
    .info-row {
        display: flex;
        padding: 15px 0;
        border-bottom: 1px solid #f0f0f0;
    }
    
    .info-label {
        flex: 0 0 150px;
        font-weight: 600;
        color: #666;
    }
    
    .info-value {
        flex: 1;
        color: #333;
    }
    
    .info-value a {
        color: ' . BRAND_COLOR . ';
        text-decoration: none;
    }
    
    .info-value a:hover {
        text-decoration: underline;
    }
    
    @media (max-width: 768px) {
        table {
            min-width: 700px;
        }
    }
</style>
';

// Incluir header
include '../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>👥 Gestión de Clientes</h1>
        <p>Visualiza y gestiona todos los clientes del CRM</p>
    </div>
    
    <!-- Estadísticas -->
    <div class="stats-bar">
        <div class="stat-item">
            <div class="stat-number"><?php echo $totalGeneral; ?></div>
            <div class="stat-label">Total Clientes</div>
        </div>
        <div class="stat-item">
            <div class="stat-number"><?php echo $totalConMantenimiento; ?></div>
            <div class="stat-label">Con Mantenimiento</div>
        </div>
        <div class="stat-item">
            <div class="stat-number"><?php echo $totalSinMantenimiento; ?></div>
            <div class="stat-label">Sin Mantenimiento</div>
        </div>
        <div class="stat-item">
            <div class="stat-number"><?php echo $totalInactivos; ?></div>
            <div class="stat-label">Inactivos</div>
        </div>
    </div>
    
    <!-- Filtros -->
    <div class="filters">
        <span style="font-weight: 600; color: #666;">Filtrar por:</span>
        <a href="?filtro=con_mantenimiento" class="filter-button <?php echo $filtro === 'con_mantenimiento' ? 'active' : ''; ?>">
            ✓ Con Mantenimiento (<?php echo $totalConMantenimiento; ?>)
        </a>
        <a href="?filtro=sin_mantenimiento" class="filter-button <?php echo $filtro === 'sin_mantenimiento' ? 'active' : ''; ?>">
            ⚠ Sin Mantenimiento (<?php echo $totalSinMantenimiento; ?>)
        </a>
        <a href="?filtro=inactivo" class="filter-button <?php echo $filtro === 'inactivo' ? 'active' : ''; ?>">
            ✗ Inactivos (<?php echo $totalInactivos; ?>)
        </a>
        <a href="?filtro=todos" class="filter-button <?php echo $filtro === 'todos' ? 'active' : ''; ?>">
            ⊛ Todos (<?php echo $totalGeneral; ?>)
        </a>
    </div>
    
    <!-- Tabla -->
    <div class="table-container">
        <?php if (count($clientesFiltrados) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Empresa</th>
                        <th>Contacto</th>
                        <th>Teléfono</th>
                        <th>Fecha Creación</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clientesFiltrados as $cliente): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($cliente['id']); ?></strong></td>
                            
                            <td>
                                <span class="client-name" onclick='showClientModal(<?php echo json_encode($cliente); ?>)'>
                                    <?php echo htmlspecialchars($cliente['company_name']); ?>
                                </span>
                            </td>
                            
                            <td><?php echo htmlspecialchars(isset($cliente['primary_contact']) ? $cliente['primary_contact'] : 'Sin contacto'); ?></td>
                            
                            <td><?php echo !empty($cliente['phone']) ? htmlspecialchars($cliente['phone']) : '-'; ?></td>
                            
                            <td><?php echo formatearFecha($cliente['created_date'] ?? ''); ?></td>
                            
                            <td>
                                <?php 
                                $tipo = getTipoCliente($cliente);
                                if ($tipo === 'inactivo'): 
                                ?>
                                    <span class="badge badge-inactive">✗ Inactivo</span>
                                <?php elseif ($tipo === 'con_mantenimiento'): ?>
                                    <span class="badge badge-con-mant">✓ Con Mantenimiento</span>
                                <?php else: ?>
                                    <span class="badge badge-sin-mant">⚠ Sin Mantenimiento</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div style="text-align: center; padding: 60px 20px; color: #999;">
                <div style="font-size: 64px; margin-bottom: 20px;">📭</div>
                <h3>No hay clientes en esta categoría</h3>
                <p>Prueba cambiando el filtro</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal -->
<div id="clientModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">Información del Cliente</h2>
            <span class="close" onclick="closeModal()">&times;</span>
        </div>
        <div class="modal-body" id="modalBody"></div>
    </div>
</div>

<?php
$additionalScripts = '
<script>
    function showClientModal(cliente) {
        const modal = document.getElementById("clientModal");
        const modalTitle = document.getElementById("modalTitle");
        const modalBody = document.getElementById("modalBody");
        
        modalTitle.textContent = cliente.company_name || "Cliente";
        
        // Mostrar loading
        modalBody.innerHTML = \'<div style="text-align: center; padding: 40px;"><div style="font-size: 48px;">⏳</div><p>Cargando información del contacto...</p></div>\';
        modal.style.display = "block";
        
        // Cargar información del contacto
        fetch("get_contacto.php?client_id=" + cliente.id)
            .then(response => response.json())
            .then(contactos => {
                let html = "";
                
                // INFORMACIÓN DE LA EMPRESA
                html += \'<h3 style="color: ' . BRAND_COLOR . '; margin-bottom: 15px; font-size: 18px;">📊 Información de la Empresa</h3>\';
                
                html += \'<div class="info-row"><div class="info-label">ID:</div><div class="info-value"><strong>\' + cliente.id + \'</strong></div></div>\';
                html += \'<div class="info-row"><div class="info-label">Empresa:</div><div class="info-value"><strong>\' + cliente.company_name + \'</strong></div></div>\';
                
                if (cliente.vat_number) {
                    html += \'<div class="info-row"><div class="info-label">NIF/CIF:</div><div class="info-value">\' + cliente.vat_number + \'</div></div>\';
                }
                
                if (cliente.phone) {
                    html += \'<div class="info-row"><div class="info-label">Teléfono:</div><div class="info-value"><a href="tel:\' + cliente.phone + \'">\' + cliente.phone + \'</a></div></div>\';
                }
                
                if (cliente.address) {
                    let address = cliente.address;
                    if (cliente.city) address += "<br>" + cliente.city;
                    if (cliente.state) address += ", " + cliente.state;
                    if (cliente.zip) address += " - " + cliente.zip;
                    if (cliente.country) address += "<br>" + cliente.country;
                    html += \'<div class="info-row"><div class="info-label">Dirección:</div><div class="info-value">\' + address + \'</div></div>\';
                }
                
                if (cliente.website) {
                    html += \'<div class="info-row"><div class="info-label">Website:</div><div class="info-value"><a href="\' + cliente.website + \'" target="_blank">\' + cliente.website + \'</a></div></div>\';
                }
                
                if (cliente.created_date) {
                    html += \'<div class="info-row"><div class="info-label">Fecha Creación:</div><div class="info-value">\' + cliente.created_date + \'</div></div>\';
                }
                
                if (cliente.client_groups) {
                    html += \'<div class="info-row"><div class="info-label">Grupos:</div><div class="info-value">\' + cliente.client_groups + \'</div></div>\';
                }
                
                if (cliente.owner_name) {
                    html += \'<div class="info-row"><div class="info-label">Propietario CRM:</div><div class="info-value">\' + cliente.owner_name + \'</div></div>\';
                }
                
                // INFORMACIÓN DE CONTACTOS
                if (contactos && contactos.length > 0) {
                    html += \'<h3 style="color: ' . BRAND_COLOR . '; margin: 30px 0 15px 0; font-size: 18px;">👤 Contactos</h3>\';
                    
                    contactos.forEach(function(contacto, index) {
                        if (index > 0) {
                            html += \'<div style="border-top: 2px solid ' . BRAND_COLOR . '; margin: 20px 0; padding-top: 20px;"></div>\';
                        }
                        
                        const nombreCompleto = (contacto.first_name || "") + " " + (contacto.last_name || "");
                        if (nombreCompleto.trim()) {
                            html += \'<div class="info-row"><div class="info-label">Nombre:</div><div class="info-value"><strong>\' + nombreCompleto + \'</strong></div></div>\';
                        }
                        
                        if (contacto.job_title) {
                            html += \'<div class="info-row"><div class="info-label">Cargo:</div><div class="info-value">\' + contacto.job_title + \'</div></div>\';
                        }
                        
                        if (contacto.email) {
                            html += \'<div class="info-row"><div class="info-label">Email:</div><div class="info-value"><a href="mailto:\' + contacto.email + \'">\' + contacto.email + \'</a></div></div>\';
                        }
                        
                        if (contacto.phone) {
                            html += \'<div class="info-row"><div class="info-label">Teléfono:</div><div class="info-value"><a href="tel:\' + contacto.phone + \'">\' + contacto.phone + \'</a></div></div>\';
                        }
                        
                        if (contacto.alternative_phone) {
                            html += \'<div class="info-row"><div class="info-label">Teléfono Alt.:</div><div class="info-value"><a href="tel:\' + contacto.alternative_phone + \'">\' + contacto.alternative_phone + \'</a></div></div>\';
                        }
                        
                        if (contacto.is_primary_contact === "1") {
                            html += \'<div class="info-row"><div class="info-label">Tipo:</div><div class="info-value"><span style="background: ' . ACCENT_COLOR . '; padding: 3px 10px; border-radius: 10px; font-size: 11px; font-weight: bold;">★ CONTACTO PRINCIPAL</span></div></div>\';
                        }
                    });
                } else {
                    html += \'<div style="text-align: center; padding: 20px; color: #999; margin-top: 20px;"><p>No hay contactos registrados para este cliente</p></div>\';
                }
                
                modalBody.innerHTML = html;
            })
            .catch(error => {
                console.error("Error:", error);
                modalBody.innerHTML = \'<div style="text-align: center; padding: 20px; color: #e74c3c;"><p>⚠️ No se pudo cargar la información del contacto</p></div>\';
            });
    }
    
    function closeModal() {
        document.getElementById("clientModal").style.display = "none";
    }
    
    window.onclick = function(event) {
        if (event.target == document.getElementById("clientModal")) {
            closeModal();
        }
    }
    
    document.addEventListener("keydown", function(event) {
        if (event.key === "Escape") {
            closeModal();
        }
    });
</script>
';

include '../includes/footer.php';
?>
