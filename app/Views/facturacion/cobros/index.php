<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0"><i data-feather="check-circle" class="icon-16"></i> Control de Cobros</h5>
        <button class="btn btn-outline-success btn-sm" onclick="exportarExcelCobros()">
            <i data-feather="download" class="icon-14"></i> Exportar Excel
        </button>
    </div>

    <!-- FILTROS -->
    <div class="card shadow-sm mb-3">
        <div class="card-body py-2">
            <div class="row g-2 align-items-end flex-wrap">
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
                <div class="col-auto" style="min-width:200px">
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
                        <option value="no_cobradas" selected>🔔 Todos los no cobrados</option>
                        <option value="pendiente">⏳ Pendiente</option>
                        <option value="rechazado">🔴 Rechazado</option>
                        <option value="parcialmente_cobrado">🔶 Pago parcial</option>
                        <option value="vencido">🔴 Retrasado</option>
                        <option value="cobrado">✅ Pagado OK</option>
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
                    <label class="form-label small mb-1">Cobro</label>
                    <select id="c-quinquena" class="form-select form-select-sm">
                        <option value="">1ª y 2ª</option>
                        <option value="1">1ª (primeros)</option>
                        <option value="2">2ª (finales)</option>
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
    <div class="row mb-3">
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
                        <th>Concepto</th>
                        <th>Recurrente</th>
                        <th>Cobro</th>
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
                <tfoot>
                    <tr class="table-light fw-bold">
                        <td colspan="6" class="text-end">TOTALES:</td>
                        <td id="foot-total"></td>
                        <td id="foot-cobrado" class="text-success"></td>
                        <td id="foot-pendiente" class="text-warning"></td>
                        <td colspan="4"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script>
var tablaCobros = null;
var urlCambiarEstado = '<?php echo get_uri("facturacion/cambiar_estado_cobro"); ?>';
var urlMarcarCobrada = '<?php echo get_uri("facturacion/marcar_cobrada"); ?>';
var urlFacturaView   = '<?php echo get_uri("facturacion/factura_view/"); ?>';

$(document).ready(function(){ recargarCobros(); });

function recargarCobros(){
    if (tablaCobros){ tablaCobros.destroy(); tablaCobros = null; }
    tablaCobros = $('#tabla-cobros').DataTable({
        processing: true,
        ajax: {
            url: '<?php echo get_uri("facturacion/cobros_list_data"); ?>',
            type: 'POST',
            data: function(d) {
                return {
                    mes:        $('#c-mes').val(),
                    anno:       $('#c-anno').val(),
                    cliente_id: $('#c-cliente').val(),
                    estado_cobro: $('#c-estado').val(),
                    forma_pago: $('#c-forma').val(),
                    quinquena:  $('#c-quinquena').val(),
                    vencidas:   $('#c-vencidas').is(':checked') ? 1 : '',
                };
            },
        },
        columns: [
            {data:0},  // Nº factura
            {data:1},  // Cliente
            {data:2, orderable:false}, // Concepto
            {data:3, orderable:false}, // Recurrente
            {data:4, orderable:false}, // Quinquena
            {data:5},  // Vencimiento
            {data:6},  // Total
            {data:7},  // Cobrado
            {data:8},  // Pendiente
            {data:9, orderable:false}, // Forma pago
            {data:10, orderable:false}, // Estado cobro
            // Cambiar estado
            {data:0, orderable:false, render: function(d, t, row){
                var fid = getFid(row[0]);
                var curEstado = getEstadoFromBadge(row[10]);
                return '<select class="form-select form-select-sm sel-estado-cobro" data-id="'+fid+'" style="min-width:140px">' +
                    '<option value="pendiente"'+(curEstado==='pendiente'?' selected':'')+'>⏳ Pendiente</option>' +
                    '<option value="cobrado"'+(curEstado==='cobrado'?' selected':'')+'>✅ Pagado OK</option>' +
                    '<option value="rechazado"'+(curEstado==='rechazado'?' selected':'')+'>🔴 Rechazado</option>' +
                    '<option value="vencido"'+(curEstado==='vencido'?' selected':'')+'>🔴 Retrasado</option>' +
                    '<option value="parcialmente_cobrado"'+(curEstado==='parcialmente_cobrado'?' selected':'')+'>🔶 Parcial</option>' +
                    '<option value="no_procede"'+(curEstado==='no_procede'?' selected':'')+'>— No procede</option>' +
                    '</select>';
            }},
            // Acciones
            {data:0, orderable:false, render: function(d, t, row){
                var fid = getFid(row[0]);
                return '<button class="btn btn-success btn-sm me-1" onclick="cobrarRapido('+fid+')" title="Marcar cobrado">' +
                    '<i data-feather="check" class="icon-12"></i></button>' +
                    '<a href="'+urlFacturaView+fid+'" class="btn btn-outline-info btn-sm" title="Ver">' +
                    '<i data-feather="eye" class="icon-12"></i></a>';
            }}
        ],
        language: { search:'Buscar:', lengthMenu:'Mostrar _MENU_ registros', info:'_START_-_END_ de _TOTAL_', infoEmpty:'Sin resultados', zeroRecords:'Sin resultados', paginate:{first:'«',last:'»',next:'›',previous:'‹'} },
        order: [[5,'asc']],
        drawCallback: function(){
            feather.replace();
            calcularTotalesCobros();
        }
    });

    $(document).off('change','.sel-estado-cobro').on('change','.sel-estado-cobro', function(){
        var fid   = $(this).data('id');
        var estado = $(this).val();
        $.post(urlCambiarEstado, {factura_id:fid, estado_cobro:estado}, function(res){
            if (res.success){
                var msg = estado==='cobrado' ? '✅ Marcado como Pagado OK' :
                          estado==='rechazado' ? '🔴 Rechazado — email enviado' :
                          estado==='vencido' ? '🔴 Retrasado — email enviado' :
                          'Estado actualizado';
                appAlert.success(msg);
                calcularTotalesCobros();
            }
        }, 'json');
    });
}

function getFid(cellHtml){
    var m = String(cellHtml).match(/data-fid="(\d+)"/);
    if (m) return m[1];
    var m2 = String(cellHtml).match(/factura_view\/?(\d+)/);
    return m2 ? m2[1] : '';
}

function getEstadoFromBadge(badgeHtml){
    if (!badgeHtml) return 'pendiente';
    var t = String(badgeHtml);
    if (t.includes('Pagado') || t.includes('cobrado')) return 'cobrado';
    if (t.includes('Rechazado')) return 'rechazado';
    if (t.includes('Retrasado') || t.includes('vencido')) return 'vencido';
    if (t.includes('Parcial') || t.includes('parcial')) return 'parcialmente_cobrado';
    if (t.includes('procede')) return 'no_procede';
    return 'pendiente';
}

function cobrarRapido(id){
    if (!id || !confirm('¿Marcar como completamente cobrada?')) return;
    $.post(urlMarcarCobrada, {factura_id:id}, function(res){
        if (res.success){ appAlert.success('✅ Marcada como Pagado OK.'); recargarCobros(); }
    }, 'json');
}

function calcularTotalesCobros(){
    if (!tablaCobros) return;
    var pend=0, cobr=0, rech=0, tot=0;
    tablaCobros.rows({search:'applied'}).data().each(function(row){
        var total = parseMoney(row[6]);
        var cobrado = parseMoney(row[7]);
        var pdte  = parseMoney(row[8]);
        tot += total;
        var estado = getEstadoFromBadge(row[10]);
        if (estado==='cobrado') cobr += total;
        else if (estado==='rechazado'||estado==='vencido') rech += total;
        else pend += pdte;
    });
    $('#tot-pendiente').text(formatMoney(pend)+' €');
    $('#tot-cobrado').text(formatMoney(cobr)+' €');
    $('#tot-rechazado').text(formatMoney(rech)+' €');
    $('#tot-total').text(formatMoney(tot)+' €');
    $('#foot-total').text(formatMoney(tot)+' €');
    $('#foot-cobrado').text(formatMoney(cobr)+' €');
    $('#foot-pendiente').text(formatMoney(pend)+' €');
}

function exportarExcelCobros(){
    if (!tablaCobros) return;
    var headers = ['Nº Factura','Cliente','Concepto','Recurrente','Cobro','Vencimiento','Total €','Cobrado €','Pendiente €','Forma pago','Estado cobro'];
    var rows = [headers];
    var totTotal=0, totCobrado=0, totPendiente=0;
    tablaCobros.rows({search:'applied'}).data().each(function(row){
        var total    = parseMoney(row[6]);
        var cobrado  = parseMoney(row[7]);
        var pendiente= parseMoney(row[8]);
        totTotal    += total;
        totCobrado  += cobrado;
        totPendiente+= pendiente;
        rows.push([
            $(row[0]).text().trim() || row[0],
            row[1],
            $(row[2]).text().trim() || row[2],
            $(row[3]).text().trim() || row[3],
            $(row[4]).text().trim() || row[4],
            row[5],
            total, cobrado, pendiente,
            $(row[9]).text().trim() || row[9],
            $(row[10]).text().trim() || row[10],
        ]);
    });
    // Fila de totales
    rows.push(['','','','','','TOTALES', totTotal, totCobrado, totPendiente,'','']);

    var wb = XLSX.utils.book_new();
    var ws = XLSX.utils.aoa_to_sheet(rows);

    // Anchos de columna
    ws['!cols'] = [{wch:14},{wch:30},{wch:35},{wch:12},{wch:8},{wch:14},{wch:12},{wch:12},{wch:12},{wch:16},{wch:16}];

    // Estilo cabecera (fila 0) — rosa Tictac
    var range = XLSX.utils.decode_range(ws['!ref']);
    for (var C = 0; C <= range.e.c; C++) {
        var hdr = XLSX.utils.encode_cell({r:0, c:C});
        if (ws[hdr]) ws[hdr].s = {
            font: {bold:true, color:{rgb:'FFFFFF'}},
            fill: {patternType:'solid', fgColor:{rgb:'D72173'}},
            alignment: {horizontal:'center', vertical:'center'},
            border: {bottom:{style:'medium', color:{rgb:'A01858'}}}
        };
    }
    // Estilo fila totales (última)
    var lastRow = rows.length - 1;
    for (var C2 = 0; C2 <= range.e.c; C2++) {
        var cell = XLSX.utils.encode_cell({r:lastRow, c:C2});
        if (ws[cell]) ws[cell].s = {
            font: {bold:true},
            fill: {patternType:'solid', fgColor:{rgb:'F5F5F5'}},
            border: {top:{style:'medium', color:{rgb:'D72173'}}}
        };
    }
    // Formato numérico para columnas 6,7,8
    for (var R = 1; R < rows.length; R++) {
        ['F','G','H'].forEach(function(col, idx){
            var addr = XLSX.utils.encode_cell({r:R, c:6+idx});
            if (ws[addr] && typeof ws[addr].v === 'number') ws[addr].z = '#,##0.00 "€"';
        });
    }

    var mes  = document.getElementById('c-mes')?.options[document.getElementById('c-mes')?.selectedIndex]?.text || '';
    var anno = document.getElementById('c-anno')?.value || new Date().getFullYear();
    var filename = 'cobros_tictac' + (mes ? '_'+mes : '') + '_' + anno + '.xlsx';

    XLSX.utils.book_append_sheet(wb, ws, 'Cobros');
    XLSX.writeFile(wb, filename);
}

function parseMoney(str){
    if (typeof str !== 'string') return parseFloat(str)||0;
    return parseFloat(str.replace(/\./g,'').replace(',','.'))||0;
}
function formatMoney(n){
    return n.toFixed(2).replace('.',',').replace(/\B(?=(\d{3})+(?!\d))/g,'.');
}
</script>