<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0"><i data-feather="cpu" class="icon-16"></i> Proyectos Kit Digital</h5>
        <?php echo modal_anchor(get_uri("facturacion/kit_digital_modal_form"), '<i data-feather="plus-circle" class="icon-14"></i> Nuevo proyecto Kit Digital', ['class' => 'btn btn-primary btn-sm', 'title' => 'Nuevo Kit Digital']); ?>
    </div>

    <!-- KPIs -->
    <?php
    $total_bono = array_sum(array_column($proyectos, 'importe_bono'));
    $total_facturado = array_sum(array_column($proyectos, 'importe_facturado'));
    $total_recibido = array_sum(array_column($proyectos, 'importe_recibido'));
    ?>
    <div class="row mb-3">
        <div class="col-md-4"><div class="card shadow-sm text-center py-2">
            <div class="fs-4 fw-bold text-primary"><?php echo number_format($total_bono, 2, ',', '.'); ?> €</div>
            <div class="small text-muted">Total bonos</div>
        </div></div>
        <div class="col-md-4"><div class="card shadow-sm text-center py-2">
            <div class="fs-4 fw-bold text-info"><?php echo number_format($total_facturado, 2, ',', '.'); ?> €</div>
            <div class="small text-muted">Facturado</div>
        </div></div>
        <div class="col-md-4"><div class="card shadow-sm text-center py-2">
            <div class="fs-4 fw-bold text-success"><?php echo number_format($total_recibido, 2, ',', '.'); ?> €</div>
            <div class="small text-muted">Recibido</div>
        </div></div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <table class="table table-hover table-sm w-100 mb-0" id="tabla-kit">
                <thead class="table-light">
                    <tr>
                        <th>Cliente</th><th>Solución</th><th>Bono</th><th>Facturado</th><th>Recibido</th>
                        <th>Inicio</th><th>Fin</th><th>Estado</th><th>Cobro</th><th>Comercial</th><th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($proyectos as $p):
                        $col_proy = ['pendiente_inicio'=>'secondary','en_ejecucion'=>'primary','justificado'=>'info','pendiente_cobro'=>'warning','cobrado'=>'success','finalizado'=>'dark','cancelado'=>'danger'][$p->estado_proyecto] ?? 'secondary';
                        $col_cobro = ['pendiente'=>'warning','cobrado'=>'success','rechazado'=>'danger','parcialmente_cobrado'=>'info'][$p->estado_cobro] ?? 'secondary';
                    ?>
                    <tr>
                        <td><?php echo $p->cliente_nombre; ?></td>
                        <td><?php echo $p->solucion; ?></td>
                        <td><?php echo number_format($p->importe_bono, 2, ',', '.'); ?> €</td>
                        <td><?php echo number_format($p->importe_facturado, 2, ',', '.'); ?> €</td>
                        <td class="text-success fw-bold"><?php echo number_format($p->importe_recibido, 2, ',', '.'); ?> €</td>
                        <td><?php echo $p->fecha_inicio ?: '-'; ?></td>
                        <td><?php echo $p->fecha_fin ?: '-'; ?></td>
                        <td><span class="badge bg-<?php echo $col_proy; ?>"><?php echo ucfirst(str_replace('_',' ',$p->estado_proyecto)); ?></span></td>
                        <td><span class="badge bg-<?php echo $col_cobro; ?>"><?php echo ucfirst($p->estado_cobro); ?></span></td>
                        <td><?php echo $p->comercial_nombre ?: '-'; ?></td>
                        <td>
                            <?php echo modal_anchor(get_uri("facturacion/kit_digital_modal_form"), '<i data-feather="edit-2" class="icon-12"></i>', ['class' => 'btn btn-outline-secondary btn-sm', 'title' => 'Editar', 'data-post-id' => $p->id]); ?>
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
    $('#tabla-kit').DataTable({ language: { search: 'Buscar:', lengthMenu: 'Mostrar _MENU_ registros', info: 'Mostrando _START_ a _END_ de _TOTAL_ registros', infoEmpty: 'Sin resultados', zeroRecords: 'No se encontraron resultados', paginate: { first: '«', last: '»', next: '›', previous: '‹' } }, drawCallback: function(){ feather.replace(); } });
});
</script>
