<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0"><i data-feather="check-circle" class="icon-16"></i> Control de Cobros</h5>
    </div>

    <!-- FILTROS -->
    <div class="card shadow-sm mb-3">
        <div class="card-body py-2">
            <div class="row g-2 align-items-end">
                <div class="col-auto">
                    <label class="form-label small mb-1">Mes</label>
                    <select id="c-mes" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <?php foreach ($meses_nombres as $n => $nombre): ?>
                            <option value="<?php echo $n; ?>" <?php echo date('n') == $n ? 'selected' : ''; ?>><?php echo $nombre; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label small mb-1">Año</label>
                    <select id="c-anno" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <?php foreach ($annos as $a): ?>
                            <option value="<?php echo $a; ?>" <?php echo date('Y') == $a ? 'selected' : ''; ?>><?php echo $a; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label small mb-1">Cliente</label>
                    <select id="c-cliente" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <?php foreach ($clientes as $c): ?>
                            <option value="<?php echo $c->id; ?>"><?php echo $c->nombre; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label small mb-1">Estado cobro</label>
                    <select id="c-estado" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <option value="pendiente" selected>⏳ Pendiente</option>
                        <option value="cobrado">✅ Pagado OK</option>
                        <option value="rechazado">🔴 Rechazado</option>
                        <option value="parcialmente_cobrado">🔶 Pago parcial</option>
                        <option value="vencido">🔴 Retrasado</option>
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label small mb-1">Forma de pago</label>
                    <select id="c-forma" class="form-select form-select-sm">
                        <option value="">Todas</option>
                        <option value="remesa">🏦 Remesa</option>
                        <option value="transferencia">↗️ Transferencia</option>
                        <option value="efectivo">💵 Efectivo</option>
                    </select>
                </div>
                <div class="col-auto">
                    <div class="form-check mt-4">
                        <input type="checkbox" class="form-check-input" id="c-vencidas">
                        <label class="form-check-label small" for="c-vencidas">Solo vencidas</label>
                    </div>
                </div>
                <div class="col-auto">
                    <button class="btn btn-secondary btn-sm" onclick="recargarCobros()">
                        <i data-feather="search" class="icon-14"></i> Filtrar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- TOTALES RÁPIDOS -->
    <div class="row mb-3" id="totales-cobros">
        <div class="col-md-3"><div class="card shadow-sm text-center py-2 border-warning">
            <div class="fs-5 fw-bold text-warning" id="tot-pendiente">0,00 €</div>
            <div class="small text-muted">⏳ Pendiente</div>
        </div></div>
        <div class="col-md-3"><div class="card shadow-sm text-center py-2 border-success">
            <div class="fs-5 fw-bold text-success" id="tot-cobrado">0,00 €</div>
            <div class="small text-muted">✅ Pagado OK</div>
        </div></div>
        <div class="col-md-3"><div class="card shadow-sm text-center py-2 border-danger">
            <div class="fs-5 fw-bold text-danger" id="tot-rechazado">0,00 €</div>
            <div class="small text-muted">🔴 Rechazado / Retrasado</div>
        </div></div>
        <div class="col-md-3"><div class="card shadow-sm text-center py-2">
            <div class="fs-5 fw-bold" id="tot-total">0,00 €</div>
            <div class="small text-muted">Total filtrado</div>
        </div></div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <table id="tabla-cobros" class="table table-hover table-sm w-100">
                <thead class="table-light">
                    <tr>
                        <th>Nº Factura</th>
                        <th>Cliente</th>
                        <th>Mes</th>
                        <th>Vencimiento</th>
                        <th>Total</th>
                        <th>Cobrado</th>
                        <th>Pendiente</th>
                        <th>Forma pago</th>
                        <th>Estado cobro</th>
                        <th>Cambiar estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<script>
var tablaCobros = null;
var urlCambiarEstado = '<?php echo get_uri("facturacion/cambiar_estado_cobro"); ?>';
var urlMarcarCobrada = '<?php echo get_uri("facturacion/marcar_cobrada"); ?>';
var urlFacturaView = '<?php echo get_uri("facturacion/factura_view/"); ?>';

$(document).ready(function(){ recargarCobros(); });

function recargarCobros(){
    if (tablaCobros){ tablaCobros.destroy(); tablaCobros = null; }
    tablaCobros = $('#tabla-cobros').DataTable({
        processing: true,
        ajax: {
            url: '<?php echo get_uri("facturacion/facturas_list_data"); ?>',
            type: 'POST',
            data: function(d) {
                return {
                    mes: $('#c-mes').val(),
                    anno: $('#c-anno').val(),
                    cliente_id: $('#c-cliente').val(),
                    estado_cobro: $('#c-estado').val(),
                    forma_pago: $('#c-forma').val(),
                    vencidas: $('#c-vencidas').is(':checked') ? 1 : '',
                };
            },
        },
        columns: [
            {data:0}, {data:1}, {data:2}, {data:4},
            {data:5}, {data:6}, {data:7}, {data:8},
            {data:10, orderable:false},
            // Columna cambiar estado (dropdown rápido)
            {data:0, orderable:false, render: function(d, t, row){
                var tmp2 = document.createElement('div');
                tmp2.innerHTML = row[0];
                var a2 = tmp2.querySelector('a[data-fid]');
                var fid = a2 ? a2.getAttribute('data-fid') : extractId(row[0]);
                return '<select class="form-select form-select-sm sel-estado-cobro" data-id="'+fid+'" style="min-width:140px">' +
                    '<option value="pendiente">⏳ Pendiente</option>' +
                    '<option value="cobrado">✅ Pagado OK</option>' +
                    '<option value="rechazado">🔴 Rechazado</option>' +
                    '<option value="vencido">🔴 Retrasado</option>' +
                    '<option value="parcialmente_cobrado">🔶 Parcial</option>' +
                    '<option value="no_procede">— No procede</option>' +
                    '</select>';
            }},
            // Acciones
            {data:0, orderable:false, render: function(d, t, row){
                // Extraer id del atributo data-fid del enlace en columna 0
                var tmp = document.createElement('div');
                tmp.innerHTML = row[0];
                var a = tmp.querySelector('a[data-fid]');
                var fid = a ? a.getAttribute('data-fid') : extractId(row[0]);
                return '<button class="btn btn-success btn-sm" onclick="cobrarRapido('+fid+')" title="Marcar cobrado">' +
                    '<i data-feather="check" class="icon-12"></i></button> ' +
                    '<a href="'+urlFacturaView+fid+'" class="btn btn-outline-info btn-sm" title="Ver">' +
                    '<i data-feather="eye" class="icon-12"></i></a>';
            }}
        ],
        language: { search: 'Buscar:', lengthMenu: 'Mostrar _MENU_ registros', info: 'Mostrando _START_ a _END_ de _TOTAL_ registros', infoEmpty: 'Sin resultados', zeroRecords: 'No se encontraron resultados', paginate: { first: '«', last: '»', next: '›', previous: '‹' } },
        order: [[3,'asc']],
        drawCallback: function(){
            feather.replace();
            calcularTotalesCobros();
            // Inicializar dropdowns de estado con valor actual
            $('#tabla-cobros tbody tr').each(function(){
                var badge = $(this).find('td:eq(8)').text().trim();
                var sel = $(this).find('.sel-estado-cobro');
                if (badge.includes('Pendiente')) sel.val('pendiente');
                else if (badge.includes('Pagado') || badge.includes('cobrado')) sel.val('cobrado');
                else if (badge.includes('Rechazado')) sel.val('rechazado');
                else if (badge.includes('Retrasado') || badge.includes('vencido')) sel.val('vencido');
                else if (badge.includes('parcial') || badge.includes('Parcial')) sel.val('parcialmente_cobrado');
                else if (badge.includes('procede')) sel.val('no_procede');
            });
        }
    });

    // Cambio de estado desde dropdown
    $(document).off('change', '.sel-estado-cobro').on('change', '.sel-estado-cobro', function(){
        var fid = $(this).data('id');
        var estado = $(this).val();
        var sel = $(this);
        $.post(urlCambiarEstado, {factura_id: fid, estado_cobro: estado}, function(res){
            if (res.success) {
                var msg = estado === 'cobrado' ? '✅ Marcado como Pagado OK' :
                          estado === 'rechazado' ? '🔴 Marcado como Rechazado — email enviado al equipo' :
                          estado === 'vencido' ? '🔴 Marcado como Retrasado — email enviado al equipo' :
                          estado === 'pendiente' ? '⏳ Marcado como Pendiente — email enviado al equipo' :
                          'Estado actualizado';
                appAlert.success(msg);
                calcularTotalesCobros();
            }
        }, 'json');
    });
}

function extractId(linkHtml){
    // Primero intenta data-fid (más fiable)
    var fid = $(linkHtml).data('fid');
    if (fid) return fid;
    // Fallback: extraer de la URL
    var match = linkHtml.match(/factura_view\/?(\d+)/);
    return match ? match[1] : '';
}

function cobrarRapido(id){
    if (!id || !confirm('¿Marcar como completamente cobrada?')) return;
    $.post(urlMarcarCobrada, {factura_id:id}, function(res){
        if (res.success){ appAlert.success('✅ Factura marcada como Pagado OK.'); recargarCobros(); }
    }, 'json');
}

function calcularTotalesCobros(){
    var pend = 0, cobr = 0, rech = 0, tot = 0;
    tablaCobros.rows({search:'applied'}).data().each(function(row){
        var total = parseMoney(row[5]);
        var cobrado = parseMoney(row[6]);
        var pdte = parseMoney(row[7]);
        var badge = $(row[10]).text ? '' : '';
        tot += total;
        // Aproximación por estado en badge col 10
        var estadoCell = row[10];
        if (typeof estadoCell === 'string') {
            if (estadoCell.includes('Pagado') || estadoCell.includes('cobrado')) cobr += total;
            else if (estadoCell.includes('Rechazado') || estadoCell.includes('Retrasado')) rech += total;
            else pend += pdte;
        }
    });
    $('#tot-pendiente').text(formatMoney(pend) + ' €');
    $('#tot-cobrado').text(formatMoney(cobr) + ' €');
    $('#tot-rechazado').text(formatMoney(rech) + ' €');
    $('#tot-total').text(formatMoney(tot) + ' €');
}

function parseMoney(str){ 
    if (typeof str !== 'string') return 0;
    return parseFloat(str.replace(/\./g,'').replace(',','.')) || 0; 
}
function formatMoney(n){ 
    return n.toFixed(2).replace('.',',').replace(/\B(?=(\d{3})+(?!\d))/g,'.'); 
}
</script>