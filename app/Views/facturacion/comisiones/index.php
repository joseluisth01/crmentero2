<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0"><i data-feather="percent" class="icon-16"></i> Comisiones</h5>
        <?php echo modal_anchor(get_uri("facturacion/comision_modal_form"), '<i data-feather="plus-circle" class="icon-14"></i> Nueva comisión', ['class' => 'btn btn-primary btn-sm', 'title' => 'Nueva comisión']); ?>
    </div>

    <?php
    $pendiente = []; $calculada = []; $pagada = [];
    $total_pendiente = 0; $total_pagada = 0;
    foreach ($comisiones as $c) {
        if ($c->estado_pago == 'pendiente') { $pendiente[] = $c; $total_pendiente += $c->comision_calculada; }
        if ($c->estado_pago == 'calculada') { $calculada[] = $c; }
        if ($c->estado_pago == 'pagada')    { $pagada[] = $c;    $total_pagada    += $c->comision_calculada; }
    }
    ?>

    <div class="row mb-3">
        <div class="col-md-4"><div class="card shadow-sm text-center py-2">
            <div class="fs-4 fw-bold text-warning"><?php echo number_format($total_pendiente, 2, ',', '.'); ?> €</div>
            <div class="small text-muted">Pendiente de pagar (<?php echo count($pendiente); ?>)</div>
        </div></div>
        <div class="col-md-4"><div class="card shadow-sm text-center py-2">
            <div class="fs-4 fw-bold text-info"><?php echo count($calculada); ?></div>
            <div class="small text-muted">Calculadas pendientes de validar</div>
        </div></div>
        <div class="col-md-4"><div class="card shadow-sm text-center py-2">
            <div class="fs-4 fw-bold text-success"><?php echo number_format($total_pagada, 2, ',', '.'); ?> €</div>
            <div class="small text-muted">Total pagado</div>
        </div></div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <table class="table table-hover table-sm w-100 mb-0" id="tabla-comisiones">
                <thead class="table-light">
                    <tr>
                        <th>Persona</th><th>Cliente</th><th>Tipo</th>
                        <th>% Comisión</th><th>Base</th><th>Recibido</th><th>Comisión calculada</th>
                        <th>Estado</th><th>Fecha pago</th><th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($comisiones as $c):
                        $col = ['pendiente'=>'warning','calculada'=>'info','pagada'=>'success','anulada'=>'danger'][$c->estado_pago] ?? 'secondary';
                    ?>
                    <tr>
                        <td><?php echo $c->persona_nombre; ?></td>
                        <td><?php echo $c->cliente_nombre; ?></td>
                        <td><span class="badge bg-secondary"><?php echo ucfirst(str_replace('_',' ',$c->tipo_comision)); ?></span></td>
                        <td><?php echo $c->porcentaje; ?>%</td>
                        <td><?php echo number_format($c->importe_base, 2, ',', '.'); ?> €</td>
                        <td><?php echo number_format($c->importe_recibido, 2, ',', '.'); ?> €</td>
                        <td class="fw-bold text-<?php echo $col; ?>"><?php echo number_format($c->comision_calculada, 2, ',', '.'); ?> €</td>
                        <td><span class="badge bg-<?php echo $col; ?>"><?php echo ucfirst($c->estado_pago); ?></span></td>
                        <td><?php echo $c->fecha_pago ?: '-'; ?></td>
                        <td>
                            <?php echo modal_anchor(get_uri("facturacion/comision_modal_form"), '<i data-feather="edit-2" class="icon-12"></i>', ['class' => 'btn btn-outline-secondary btn-sm', 'title' => 'Editar', 'data-post-id' => $c->id]); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script>
$(document).ready(function(){
    $('#tabla-comisiones').DataTable({ language: { search: 'Buscar:', lengthMenu: 'Mostrar _MENU_ registros', info: 'Mostrando _START_ a _END_ de _TOTAL_ registros', infoEmpty: 'Sin resultados', zeroRecords: 'No se encontraron resultados', paginate: { first: '«', last: '»', next: '›', previous: '‹' } }, drawCallback: function(){ feather.replace(); } });
});
</script>