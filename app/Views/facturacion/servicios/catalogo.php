<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0"><i data-feather="package" class="icon-16"></i> Catálogo de servicios</h5>
        <?php echo modal_anchor(get_uri("facturacion/servicio_catalogo_modal_form"), '<i data-feather="plus-circle" class="icon-14"></i> Nuevo servicio', ['class' => 'btn btn-primary btn-sm', 'title' => 'Nuevo servicio en catálogo']); ?>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <table class="table table-hover table-sm w-100 mb-0" id="tabla-catalogo">
                <thead class="table-light">
                    <tr>
                        <th>Nombre</th><th>Categoría</th><th>Importe base</th><th>IVA</th>
                        <th>Periodicidad</th><th>Recurrente</th><th>Genera comisión</th><th>Estado</th><th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($servicios as $s):
                        $col_cat = ['seo'=>'success','rrss'=>'primary','web'=>'info','hosting'=>'secondary','ads'=>'warning','kit_digital'=>'danger','otro'=>'dark'][$s->categoria] ?? 'secondary';
                    ?>
                    <tr>
                        <td><?php echo $s->nombre; ?></td>
                        <td><span class="badge bg-<?php echo $col_cat; ?>"><?php echo ucfirst($s->categoria); ?></span></td>
                        <td><?php echo $s->importe_base > 0 ? number_format($s->importe_base, 2, ',', '.') . ' €' : '<span class="text-muted">Variable</span>'; ?></td>
                        <td><?php echo $s->iva_porcentaje; ?>%</td>
                        <td><?php echo ucfirst($s->periodicidad); ?></td>
                        <td><?php echo $s->es_recurrente ? '<span class="badge bg-success">Sí</span>' : '<span class="badge bg-secondary">No</span>'; ?></td>
                        <td><?php echo $s->genera_comision ? '<span class="badge bg-warning">Sí</span>' : '-'; ?></td>
                        <td><span class="badge bg-<?php echo $s->estado=='activo'?'success':'secondary'; ?>"><?php echo ucfirst($s->estado); ?></span></td>
                        <td>
                            <?php echo modal_anchor(get_uri("facturacion/servicio_catalogo_modal_form"), '<i data-feather="edit-2" class="icon-12"></i>', ['class' => 'btn btn-outline-secondary btn-sm', 'title' => 'Editar', 'data-post-id' => $s->id]); ?>
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
    $('#tabla-catalogo').DataTable({ language: { search: 'Buscar:', lengthMenu: 'Mostrar _MENU_ registros', info: 'Mostrando _START_ a _END_ de _TOTAL_ registros', infoEmpty: 'Sin resultados', zeroRecords: 'No se encontraron resultados', paginate: { first: '«', last: '»', next: '›', previous: '‹' } }, order: [[0,'asc']], drawCallback: function(){ feather.replace(); } });
});
</script>
