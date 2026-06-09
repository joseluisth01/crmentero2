<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0"><i data-feather="file-text" class="icon-16"></i> Facturas</h5>
        <div>
            <?php echo modal_anchor(get_uri("facturacion/generar_mes_modal"), '<i data-feather="zap" class="icon-14"></i> Generar mes', ['class' => 'btn btn-warning btn-sm me-2', 'title' => 'Generar facturas recurrentes']); ?>
            <?php echo modal_anchor(get_uri("facturacion/factura_modal_form"), '<i data-feather="plus-circle" class="icon-14"></i> Nueva factura', ['class' => 'btn btn-primary btn-sm', 'title' => 'Nueva factura']); ?>
        </div>
    </div>

    <!-- FILTROS -->
    <div class="card shadow-sm mb-3">
        <div class="card-body py-2">
            <div class="row g-2 align-items-end">
                <div class="col-auto">
                    <label class="form-label small mb-1">Mes</label>
                    <select id="f-mes" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <?php foreach ($meses_nombres as $n => $nombre): ?>
                            <option value="<?php echo $n; ?>" <?php echo date('n') == $n ? 'selected' : ''; ?>><?php echo $nombre; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label small mb-1">Año</label>
                    <select id="f-anno" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <?php foreach ($annos as $a): ?>
                            <option value="<?php echo $a; ?>" <?php echo date('Y') == $a ? 'selected' : ''; ?>><?php echo $a; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label small mb-1">Cliente</label>
                    <select id="f-cliente" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <?php foreach ($clientes as $c): ?>
                            <option value="<?php echo $c->id; ?>"><?php echo $c->nombre; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label small mb-1">Estado factura</label>
                    <select id="f-estado-fac" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <option value="borrador">Borrador</option>
                        <option value="emitida">Emitida</option>
                        <option value="enviada">Enviada</option>
                        <option value="pagada">Pagada</option>
                        <option value="cancelada">Cancelada</option>
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label small mb-1">Estado cobro</label>
                    <select id="f-estado-cobro" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <option value="pendiente">Pendiente</option>
                        <option value="cobrado">Cobrado</option>
                        <option value="rechazado">Rechazado</option>
                        <option value="parcialmente_cobrado">Parcialmente cobrado</option>
                        <option value="vencido">Vencido</option>
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label small mb-1">Forma de pago</label>
                    <select id="f-forma-pago" class="form-select form-select-sm">
                        <option value="">Todas</option>
                        <option value="remesa">Remesa</option>
                        <option value="transferencia">Transferencia</option>
                        <option value="efectivo">Efectivo</option>
                    </select>
                </div>
                <div class="col-auto">
                    <div class="form-check mt-4">
                        <input type="checkbox" class="form-check-input" id="f-vencidas">
                        <label class="form-check-label small" for="f-vencidas">Solo vencidas</label>
                    </div>
                </div>
                <div class="col-auto">
                    <button class="btn btn-secondary btn-sm" id="btn-filtrar-facturas">
                        <i data-feather="search" class="icon-14"></i> Filtrar
                    </button>
                    <button class="btn btn-outline-secondary btn-sm" id="btn-limpiar-facturas">
                        <i data-feather="x" class="icon-14"></i> Limpiar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <table id="tabla-facturas" class="table table-hover table-sm w-100">
                <thead class="table-light">
                    <tr>
                        <th>Nº Factura</th>
                        <th>Cliente</th>
                        <th>Concepto</th>
                        <th>Período</th>
                        <th>Emisión</th>
                        <th>Vencimiento</th>
                        <th>Total</th>
                        <th>Cobrado</th>
                        <th>Pendiente</th>
                        <th>Forma pago</th>
                        <th>Estado</th>
                        <th>Cobro</th>
                        <th width="100">Acciones</th>
                    </tr>
                </thead>
                <tbody></tbody>
                <tfoot>
                    <tr>
                        <td colspan="6" class="text-end fw-bold">TOTALES:</td>
                        <td id="total-facturado-footer" class="fw-bold"></td>
                        <td id="total-cobrado-footer" class="fw-bold text-success"></td>
                        <td id="total-pendiente-footer" class="fw-bold text-warning"></td>
                        <td colspan="4"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<script>
var tablaFacturas = null;
$(document).ready(function(){
    cargarTablaFacturas();
    $('#btn-filtrar-facturas').on('click', function(){ recargarFacturas(); });
    $('#btn-limpiar-facturas').on('click', function(){
        $('#f-mes, #f-anno, #f-cliente, #f-estado-fac, #f-estado-cobro, #f-forma-pago').val('');
        $('#f-vencidas').prop('checked', false);
        recargarFacturas();
    });
});
function recargarFacturas(){
    if (tablaFacturas) { tablaFacturas.destroy(); tablaFacturas = null; }
    cargarTablaFacturas();
}
function cargarTablaFacturas(){
    tablaFacturas = $('#tabla-facturas').DataTable({
        processing: true,
        ajax: {
            url: '<?php echo get_uri("facturacion/facturas_list_data"); ?>',
            type: 'POST',
            data: {
                mes: $('#f-mes').val(),
                anno: $('#f-anno').val(),
                cliente_id: $('#f-cliente').val(),
                estado_factura: $('#f-estado-fac').val(),
                estado_cobro: $('#f-estado-cobro').val(),
                forma_pago: $('#f-forma-pago').val(),
                vencidas: $('#f-vencidas').is(':checked') ? 1 : '',
            },
        },
        columns: [
            {data:0},{data:1},{data:2,orderable:false},{data:3},{data:4},{data:5},
            {data:6},{data:7},{data:8},{data:9},
            {data:10,orderable:false},{data:11,orderable:false},{data:12,orderable:false}
        ],
        language: { search: 'Buscar:', lengthMenu: 'Mostrar _MENU_ registros', info: 'Mostrando _START_ a _END_ de _TOTAL_ registros', infoEmpty: 'Sin resultados', zeroRecords: 'No se encontraron resultados', paginate: { first: '«', last: '»', next: '›', previous: '‹' } },
        order: [[0,'desc']],
        drawCallback: function(){ feather.replace(); calcularTotalesFacturas(); }
    });
}
function calcularTotalesFacturas(){
    if (!tablaFacturas) return;
    var total = 0, cobrado = 0, pendiente = 0;
    tablaFacturas.rows({search:'applied'}).data().each(function(row){
        total    += parseMoney(row[6]);
        cobrado  += parseMoney(row[7]);
        pendiente+= parseMoney(row[8]);
    });
    $('#total-facturado-footer').text(formatMoney(total) + ' €');
    $('#total-cobrado-footer').text(formatMoney(cobrado) + ' €');
    $('#total-pendiente-footer').text(formatMoney(pendiente) + ' €');
}
function parseMoney(str){ return parseFloat(str.replace(/\./g,'').replace(',','.')) || 0; }
function formatMoney(n){ return n.toFixed(2).replace('.',',').replace(/\B(?=(\d{3})+(?!\d))/g,'.'); }

function borrarFactura(id) {
    if (!confirm('¿Borrar esta factura? Esta acción no se puede deshacer.')) return;
    $.post('<?php echo get_uri("facturacion/delete_factura"); ?>', {id: id}, function(res){
        if (res.success) {
            appAlert.success('Factura eliminada.');
            if (typeof recargarFacturas === 'function') recargarFacturas();
        } else {
            appAlert.danger('No se pudo eliminar la factura.');
        }
    }, 'json');
}
</script>