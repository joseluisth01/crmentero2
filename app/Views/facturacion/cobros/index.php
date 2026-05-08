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
                        <option value="pendiente" selected>Pendiente</option>
                        <option value="">Todos</option>
                        <option value="cobrado">Cobrado</option>
                        <option value="rechazado">Rechazado</option>
                        <option value="parcialmente_cobrado">Parcialmente cobrado</option>
                        <option value="vencido">Vencido</option>
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label small mb-1">Forma de pago</label>
                    <select id="c-forma" class="form-select form-select-sm">
                        <option value="">Todas</option>
                        <option value="remesa">Remesa</option>
                        <option value="transferencia">Transferencia</option>
                        <option value="efectivo">Efectivo</option>
                    </select>
                </div>
                <div class="col-auto">
                    <div class="form-check mt-4">
                        <input type="checkbox" class="form-check-input" id="c-vencidas" checked>
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
$(document).ready(function(){ recargarCobros(); });

function recargarCobros(){
    if (tablaCobros){ tablaCobros.destroy(); tablaCobros = null; }
    tablaCobros = $('#tabla-cobros').DataTable({
        processing: true,
        ajax: {
            url: '<?php echo get_uri("facturacion/facturas_list_data"); ?>',
            type: 'POST',
            data: {
                mes: $('#c-mes').val(),
                anno: $('#c-anno').val(),
                cliente_id: $('#c-cliente').val(),
                estado_cobro: $('#c-estado').val(),
                forma_pago: $('#c-forma').val(),
                vencidas: $('#c-vencidas').is(':checked') ? 1 : '',
            },
        },
        columns: [
            {data:0},{data:1},{data:2},{data:4},
            {data:5},{data:6},{data:7},{data:8},{data:10},
            {data:11, orderable:false, render: function(d, t, row){
                var id = row[0].match(/factura_view\/(\d+)/);
                var fid = id ? id[1] : '';
                return '<button class="btn btn-success btn-xs btn-sm" onclick="marcarCobradaRapido('+fid+')" title="Cobrada"><i data-feather="check" class="icon-12"></i></button> ' +
                       '<a href="<?php echo get_uri("facturacion/factura_view/"); ?>' + fid + '" class="btn btn-outline-info btn-sm btn-xs"><i data-feather="eye" class="icon-12"></i></a>';
            }}
        ],
        language: { url: '<?php echo get_uri("assets/datatables/Spanish.json"); ?>' },
        order: [[3,'asc']],
        drawCallback: function(){ feather.replace(); }
    });
}

function marcarCobradaRapido(id){
    if (!id || !confirm('¿Marcar como cobrada?')) return;
    $.post('<?php echo get_uri("facturacion/marcar_cobrada"); ?>', {factura_id:id}, function(res){
        if (res.success){ appAlert.success('Marcada como cobrada.'); recargarCobros(); }
    }, 'json');
}
</script>
