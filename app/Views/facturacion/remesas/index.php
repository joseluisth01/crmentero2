<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0"><i data-feather="layers" class="icon-16"></i> Remesas bancarias</h5>
        <?php echo modal_anchor(get_uri("facturacion/remesa_modal_form"), '<i data-feather="plus-circle" class="icon-14"></i> Nueva remesa', ['class' => 'btn btn-primary btn-sm', 'title' => 'Nueva remesa']); ?>
    </div>

    <div class="row mb-3">
        <div class="col-auto">
            <form class="d-flex gap-2 align-items-center" method="get" action="<?php echo get_uri('facturacion/remesas'); ?>">
                <select name="anno" class="form-select form-select-sm">
                    <?php foreach ($annos as $a): ?>
                        <option value="<?php echo $a; ?>" <?php echo $anno==$a?'selected':''; ?>><?php echo $a; ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-secondary btn-sm"><i data-feather="search" class="icon-14"></i></button>
            </form>
        </div>
    </div>

    <div class="row">
        <!-- LISTA DE REMESAS -->
        <div class="col-md-5">
            <div class="card shadow-sm">
                <div class="card-header bg-white fw-bold">Remesas <?php echo $anno; ?></div>
                <div class="list-group list-group-flush" id="lista-remesas">
                    <?php if (empty($remesas)): ?>
                        <div class="list-group-item text-muted">Sin remesas para <?php echo $anno; ?></div>
                    <?php endif; ?>
                    <?php foreach ($remesas as $r):
                        $col = ['pendiente'=>'secondary','generada'=>'info','enviada'=>'primary','cobrada'=>'success','parcialmente_rechazada'=>'warning','rechazada'=>'danger'][$r->estado] ?? 'secondary';
                    ?>
                    <a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center remesa-item" data-id="<?php echo $r->id; ?>">
                        <div>
                            <div class="fw-bold"><?php echo $r->nombre; ?></div>
                            <div class="small text-muted">Mes <?php echo $r->mes; ?>/<?php echo $r->anno; ?> — <?php echo number_format($r->total_importe, 2, ',', '.'); ?> €</div>
                        </div>
                        <span class="badge bg-<?php echo $col; ?>"><?php echo ucfirst($r->estado); ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- FACTURAS DE REMESA (panel derecho) -->
        <div class="col-md-7">
            <div class="card shadow-sm" id="panel-facturas-remesa" style="display:none">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-bold" id="titulo-remesa-activa">Facturas de la remesa</span>
                    <?php echo modal_anchor(get_uri("facturacion/remesa_modal_form"), '<i data-feather="edit-2" class="icon-12"></i>', ['class' => 'btn btn-outline-secondary btn-sm', 'title' => 'Editar remesa', 'id' => 'btn-editar-remesa-activa']); ?>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm table-hover mb-0" id="tabla-facturas-remesa">
                        <thead class="table-light">
                            <tr><th>Nº Factura</th><th>Cliente</th><th>CIF</th><th>Total</th><th>Estado cobro</th></tr>
                        </thead>
                        <tbody id="tbody-facturas-remesa"></tbody>
                        <tfoot><tr><td colspan="3" class="text-end fw-bold">TOTAL</td><td class="fw-bold" id="total-remesa-activa"></td><td></td></tr></tfoot>
                    </table>
                </div>
                <div class="card-footer bg-white">
                    <div class="alert alert-info small py-2 mb-2">
                        Para añadir facturas a esta remesa, edita cada factura y cambia su forma de pago a <strong>Remesa</strong> y asigna la remesa correspondiente.
                    </div>
                </div>
            </div>
            <div id="panel-remesa-vacio" class="text-muted p-4">
                <i data-feather="arrow-left" class="icon-16"></i> Selecciona una remesa para ver sus facturas
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function(){
    feather.replace();
    $(document).on('click', '.remesa-item', function(e){
        e.preventDefault();
        var id = $(this).data('id');
        $('.remesa-item').removeClass('active');
        $(this).addClass('active');
        cargarFacturasRemesa(id);
    });
});

function cargarFacturasRemesa(remesaId){
    $.get('<?php echo get_uri("facturacion/facturas_list_data"); ?>', {remesa_id: remesaId}, function(res){
        var rows = '';
        var total = 0;
        if (res.data) {
            $.each(res.data, function(i, row){
                rows += '<tr><td>'+row[0]+'</td><td>'+row[1]+'</td><td>'+row[2]+'</td><td>'+row[5]+'</td><td>'+row[10]+'</td></tr>';
            });
        }
        $('#tbody-facturas-remesa').html(rows || '<tr><td colspan="5" class="text-muted text-center">Sin facturas asignadas</td></tr>');
        $('#panel-facturas-remesa').show();
        $('#panel-remesa-vacio').hide();
        feather.replace();
    }, 'json');
}
</script>
