<?php
/**
 * Gestión de Clientes - Sistema Tictac
 * VERSIÓN AUTÓNOMA con consulta directa a BBDD
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once '../config.php';

$filtro = isset($_GET['filtro']) ? $_GET['filtro'] : 'activos';

// Obtener clientes
$todosLosClientes = array();
$mysqli = conexionBBDD();

if ($mysqli) {
    $result = $mysqli->query("SELECT id, company_name, phone, address, city, state, zip, 
                                     country, website, vat_number, created_date, group_ids, owner_id
                              FROM crm_clients WHERE deleted = 0 ORDER BY company_name ASC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $row['primary_contact'] = '';
            $row['client_groups'] = '';
            $row['owner_name'] = '';
            $todosLosClientes[] = $row;
        }
        $result->free();
    }

    // Contactos
    $contactosMap = array();
    $rc = $mysqli->query("SELECT client_id, first_name, last_name FROM crm_users
                          WHERE deleted = 0 AND user_type = 'client'
                          ORDER BY is_primary_contact DESC, id ASC");
    if ($rc) {
        while ($row = $rc->fetch_assoc()) {
            if (!isset($contactosMap[$row['client_id']])) {
                $contactosMap[$row['client_id']] = trim($row['first_name'] . ' ' . $row['last_name']);
            }
        }
        $rc->free();
    }

    // Grupos
    $gruposMap = array();
    $rg = @$mysqli->query("SELECT id, title FROM crm_client_groups WHERE deleted = 0");
    if ($rg) {
        while ($row = $rg->fetch_assoc()) { $gruposMap[$row['id']] = $row['title']; }
        $rg->free();
    }

    // Enriquecer
    for ($i = 0; $i < count($todosLosClientes); $i++) {
        $cid = $todosLosClientes[$i]['id'];
        if (isset($contactosMap[$cid])) {
            $todosLosClientes[$i]['primary_contact'] = $contactosMap[$cid];
        }
        if (!empty($todosLosClientes[$i]['group_ids']) && !empty($gruposMap)) {
            $ids = explode(',', $todosLosClientes[$i]['group_ids']);
            $nombres = array();
            foreach ($ids as $gid) {
                $gid = trim($gid);
                if (isset($gruposMap[$gid])) $nombres[] = $gruposMap[$gid];
            }
            $todosLosClientes[$i]['client_groups'] = implode(', ', $nombres);
        }
    }
}

// Filtrar y contar
$clientesFiltrados = array();
$totalConMantenimiento = 0;
$totalSinMantenimiento = 0;
$totalInactivos = 0;
$totalActivos = 0;

foreach ($todosLosClientes as $cliente) {
    $tipo = getTipoCliente($cliente);
    if ($tipo === 'con_mantenimiento') { $totalConMantenimiento++; $totalActivos++; }
    elseif ($tipo === 'sin_mantenimiento') { $totalSinMantenimiento++; $totalActivos++; }
    elseif ($tipo === 'inactivo') { $totalInactivos++; }

    // Aplicar filtro
    $mostrar = false;
    if ($filtro === 'todos') $mostrar = true;
    elseif ($filtro === 'activos' && $tipo !== 'inactivo') $mostrar = true;
    elseif ($filtro === $tipo) $mostrar = true;

    if ($mostrar) {
        $clientesFiltrados[] = $cliente;
    }
}
$totalGeneral = count($todosLosClientes);

// JSON seguro para JS
$jsData = array();
foreach ($clientesFiltrados as $c) { $jsData[$c['id']] = $c; }
$jsonSafe = json_encode($jsData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
if ($jsonSafe === false) { $jsonSafe = '{}'; }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Clientes - <?php echo COMPANY_NAME; ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica Neue', Arial, sans-serif; background: #f5f5f5; color: #333; }
        .header {
            background: linear-gradient(135deg, <?php echo BRAND_COLOR; ?> 0%, <?php echo BRAND_COLOR_DARK; ?> 100%);
            color: white; padding: 30px; box-shadow: 0 2px 20px rgba(0,0,0,0.1);
        }
        .header-content { max-width: 1400px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; }
        .logo img { height: 50px; width: auto; max-width: 200px; object-fit: contain; }
        .logo-text { font-size: 36px; font-weight: 300; letter-spacing: 8px; }
        .back-button {
            background: rgba(255,255,255,0.2); color: white; padding: 10px 25px;
            border-radius: 50px; text-decoration: none; font-weight: 600; transition: all 0.3s;
        }
        .back-button:hover { background: rgba(255,255,255,0.3); }
        .container { max-width: 1400px; margin: 0 auto; padding: 30px 20px; }
        .page-header h1 { color: <?php echo BRAND_COLOR; ?>; font-weight: 300; font-size: 32px; margin-bottom: 10px; }
        .page-header p { color: #666; }
        .stats-bar {
            background: white; padding: 20px 30px; border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05); display: flex;
            justify-content: space-around; margin: 30px 0; flex-wrap: wrap; gap: 20px;
        }
        .stat-item { text-align: center; }
        .stat-number { font-size: 36px; font-weight: bold; color: <?php echo BRAND_COLOR; ?>; }
        .stat-label { color: #666; font-size: 14px; }
        .filters {
            background: white; padding: 20px 30px; border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 30px;
            display: flex; gap: 15px; flex-wrap: wrap; align-items: center;
        }
        .filter-button {
            padding: 10px 25px; border: 2px solid #ddd; background: white;
            border-radius: 50px; cursor: pointer; font-weight: 600; color: #666;
            text-decoration: none; transition: all 0.3s;
        }
        .filter-button:hover { border-color: <?php echo BRAND_COLOR; ?>; color: <?php echo BRAND_COLOR; ?>; }
        .filter-button.active { background: <?php echo BRAND_COLOR; ?>; color: white; border-color: <?php echo BRAND_COLOR; ?>; }
        .table-container {
            background: white; border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05); overflow-x: auto;
        }
        table { width: 100%; border-collapse: collapse; }
        thead { background: <?php echo BRAND_COLOR; ?>; color: white; }
        th { padding: 15px; text-align: left; font-weight: 600; }
        td { padding: 15px; border-bottom: 1px solid #f0f0f0; }
        tbody tr:hover { background: #f9f9f9; }
        .client-name { color: <?php echo BRAND_COLOR; ?>; cursor: pointer; font-weight: 600; }
        .client-name:hover { color: <?php echo BRAND_COLOR_DARK; ?>; text-decoration: underline; }
        .badge { display: inline-block; padding: 5px 12px; border-radius: 15px; font-size: 12px; font-weight: 600; }
        .badge-con-mant { background: #d4edda; color: #155724; }
        .badge-sin-mant { background: #fff3cd; color: #856404; }
        .badge-inactive { background: #f8d7da; color: #721c24; }
        .modal { display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5); }
        .modal-content { background:white; margin:50px auto; border-radius:15px; width:90%; max-width:600px; box-shadow:0 10px 50px rgba(0,0,0,0.3); max-height:90vh; overflow-y:auto; }
        .modal-header { background:linear-gradient(135deg,<?php echo BRAND_COLOR; ?> 0%,<?php echo BRAND_COLOR_DARK; ?> 100%); color:white; padding:20px; border-radius:15px 15px 0 0; display:flex; justify-content:space-between; align-items:center; position:sticky; top:0; z-index:10; }
        .modal-header h2 { font-weight:300; font-size:24px; }
        .close-btn { color:white; font-size:32px; cursor:pointer; line-height:1; background:none; border:none; }
        .close-btn:hover { opacity:0.8; }
        .modal-body { padding:30px; }
        .info-row { display:flex; padding:12px 0; border-bottom:1px solid #f0f0f0; }
        .info-label { flex:0 0 150px; font-weight:600; color:#666; }
        .info-value { flex:1; color:#333; }
        .info-value a { color: <?php echo BRAND_COLOR; ?>; text-decoration:none; }
        .info-value a:hover { text-decoration:underline; }
        @media (max-width:768px) { table { min-width:700px; } .header-content { flex-direction:column; gap:15px; } }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="logo">
                <span class="logo-text"><img src="https://tictac-comunicacion.es/wp-content/uploads/2026/02/LOGO-1-2.png" alt=""></span>
            </div>
            <div>
                <a href="<?php echo BASE_URL; ?>" class="back-button">← Volver al Dashboard</a>
                <a href="https://gestion-tictac-comunicacion.es/" class="back-button">← Volver al CRM</a>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="page-header">
            <h1>👥 Gestión de Clientes</h1>
            <p>Visualiza y gestiona todos los clientes del CRM</p>
        </div>

        <div class="stats-bar">
            <div class="stat-item"><div class="stat-number"><?php echo $totalGeneral; ?></div><div class="stat-label">Total Clientes</div></div>
            <div class="stat-item"><div class="stat-number"><?php echo $totalActivos; ?></div><div class="stat-label">Activos</div></div>
            <div class="stat-item"><div class="stat-number"><?php echo $totalConMantenimiento; ?></div><div class="stat-label">Con Mantenimiento</div></div>
            <div class="stat-item"><div class="stat-number"><?php echo $totalSinMantenimiento; ?></div><div class="stat-label">Sin Mantenimiento</div></div>
            <div class="stat-item"><div class="stat-number"><?php echo $totalInactivos; ?></div><div class="stat-label">Inactivos</div></div>
        </div>

        <div class="filters">
            <span style="font-weight:600;color:#666;">Filtrar por:</span>
            <a href="?filtro=activos" class="filter-button <?php echo $filtro==='activos'?'active':''; ?>">★ Activos (<?php echo $totalActivos; ?>)</a>
            <a href="?filtro=con_mantenimiento" class="filter-button <?php echo $filtro==='con_mantenimiento'?'active':''; ?>">✓ Con Mantenimiento (<?php echo $totalConMantenimiento; ?>)</a>
            <a href="?filtro=sin_mantenimiento" class="filter-button <?php echo $filtro==='sin_mantenimiento'?'active':''; ?>">⚠ Sin Mantenimiento (<?php echo $totalSinMantenimiento; ?>)</a>
            <a href="?filtro=inactivo" class="filter-button <?php echo $filtro==='inactivo'?'active':''; ?>">✗ Inactivos (<?php echo $totalInactivos; ?>)</a>
            <a href="?filtro=todos" class="filter-button <?php echo $filtro==='todos'?'active':''; ?>">⊛ Todos (<?php echo $totalGeneral; ?>)</a>
        </div>

        <div class="table-container">
            <?php if (count($clientesFiltrados) > 0): ?>
            <table>
                <thead><tr><th>ID</th><th>Empresa</th><th>Contacto</th><th>Teléfono</th><th>Fecha Creación</th><th>Estado</th></tr></thead>
                <tbody>
                <?php foreach ($clientesFiltrados as $cliente): ?>
                    <tr>
                        <td><strong><?php echo intval($cliente['id']); ?></strong></td>
                        <td><span class="client-name" onclick="showClientModal(<?php echo intval($cliente['id']); ?>)"><?php echo htmlspecialchars($cliente['company_name']); ?></span></td>
                        <td><?php echo htmlspecialchars(!empty($cliente['primary_contact']) ? $cliente['primary_contact'] : 'Sin contacto'); ?></td>
                        <td><?php echo !empty($cliente['phone']) ? htmlspecialchars($cliente['phone']) : '-'; ?></td>
                        <td><?php echo formatearFecha($cliente['created_date']); ?></td>
                        <td>
                            <?php $tipo = getTipoCliente($cliente);
                            if ($tipo==='inactivo'): ?><span class="badge badge-inactive">✗ Inactivo</span>
                            <?php elseif ($tipo==='con_mantenimiento'): ?><span class="badge badge-con-mant">✓ Con Mantenimiento</span>
                            <?php else: ?><span class="badge badge-sin-mant">⚠ Sin Mantenimiento</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div style="text-align:center;padding:60px 20px;color:#999;">
                <div style="font-size:64px;margin-bottom:20px;">📭</div>
                <h3>No hay clientes en esta categoría</h3>
                <p>Prueba cambiando el filtro</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div id="clientModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Información del Cliente</h2>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body" id="modalBody"></div>
        </div>
    </div>

    <div style="text-align:center;padding:40px 20px;color:#666;margin-top:60px;">
        <p><strong><?php echo COMPANY_NAME; ?></strong> - Sistema de Gestión</p>
        <p style="font-size:12px;margin-top:10px;">Versión <?php echo SYSTEM_VERSION; ?></p>
    </div>

    <script>
    var clientesData = <?php echo $jsonSafe; ?>;

    function showClientModal(id) {
        var c = clientesData[id];
        if (!c) return;
        document.getElementById("modalTitle").textContent = c.company_name || "Cliente";
        document.getElementById("modalBody").innerHTML = '<div style="text-align:center;padding:40px;">⏳ Cargando...</div>';
        document.getElementById("clientModal").style.display = "block";

        fetch("get_contacto.php?client_id=" + id)
            .then(function(r){return r.json();})
            .then(function(contactos){
                var h = '<h3 style="color:<?php echo BRAND_COLOR; ?>;margin-bottom:15px;">📊 Empresa</h3>';
                h += ir("ID",c.id); h += ir("Empresa",c.company_name);
                if(c.vat_number) h+=ir("NIF/CIF",c.vat_number);
                if(c.phone) h+=ir("Teléfono",'<a href="tel:'+c.phone+'">'+c.phone+'</a>');
                if(c.address){var a=c.address;if(c.city)a+=", "+c.city;if(c.zip)a+=" - "+c.zip;if(c.country)a+=" ("+c.country+")";h+=ir("Dirección",a);}
                if(c.website) h+=ir("Web",'<a href="'+c.website+'" target="_blank">'+c.website+'</a>');
                if(c.created_date) h+=ir("Creación",c.created_date);
                if(c.client_groups) h+=ir("Grupos",c.client_groups);
                if(contactos&&contactos.length>0){
                    h+='<h3 style="color:<?php echo BRAND_COLOR; ?>;margin:25px 0 15px;">👤 Contactos</h3>';
                    for(var i=0;i<contactos.length;i++){
                        var ct=contactos[i];
                        if(i>0)h+='<hr style="border:1px solid <?php echo BRAND_COLOR; ?>;margin:15px 0;">';
                        var n=((ct.first_name||"")+" "+(ct.last_name||"")).trim();
                        if(n)h+=ir("Nombre","<strong>"+n+"</strong>");
                        if(ct.job_title)h+=ir("Cargo",ct.job_title);
                        if(ct.email)h+=ir("Email",'<a href="mailto:'+ct.email+'">'+ct.email+'</a>');
                        if(ct.phone)h+=ir("Teléfono",'<a href="tel:'+ct.phone+'">'+ct.phone+'</a>');
                        if(ct.is_primary_contact==="1")h+=ir("Tipo",'<span style="background:<?php echo ACCENT_COLOR; ?>;padding:3px 10px;border-radius:10px;font-size:11px;font-weight:bold;">★ PRINCIPAL</span>');
                    }
                }
                document.getElementById("modalBody").innerHTML=h;
            })
            .catch(function(){
                document.getElementById("modalBody").innerHTML='<p style="color:red;text-align:center;padding:20px;">Error al cargar contactos</p>';
            });
    }
    function ir(l,v){return '<div class="info-row"><div class="info-label">'+l+':</div><div class="info-value">'+(v||"-")+'</div></div>';}
    function closeModal(){document.getElementById("clientModal").style.display="none";}
    window.onclick=function(e){if(e.target==document.getElementById("clientModal"))closeModal();};
    document.addEventListener("keydown",function(e){if(e.key==="Escape")closeModal();});
    </script>
</body>
</html>