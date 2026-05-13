<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0"><i data-feather="refresh-cw" class="icon-16"></i> Renovaciones (Hosting, Dominio, SSL…)</h5>
        <?php echo modal_anchor(get_uri("facturacion/renovacion_modal_form"), '<i data-feather="plus-circle" class="icon-14"></i> Nueva renovación', ['class' => 'btn btn-primary btn-sm', 'title' => 'Nueva renovación']); ?>
    </div>

    <!-- KPIs RENOVACIONES -->
    <div class="row mb-3">
        <?php
        $proximas = array_filter($renovaciones, function($r){ return $r->fecha_renovacion <= date('Y-m-d', strtotime('+30 days')) && $r->fecha_renovacion >= date('Y-m-d') && $r->estado == 'pendiente'; });
        $pendientes = array_filter($renovaciones, function($r){ return $r->estado == 'pendiente'; });
        $renovadas = array_filter($renovaciones, function($r){ return $r->estado == 'renovado' || $r->estado == 'facturado'; });
        ?>
        <div class="col-md-3"><div class="card shadow-sm text-center py-2">
            <div class="fs-3 fw-bold text-warning"><?php echo count($proximas); ?></div>
            <div class="small text-muted">Próximas 30 días</div>
        </div></div>
        <div class="col-md-3"><div class="card shadow-sm text-center py-2">
            <div class="fs-3 fw-bold text-danger"><?php echo count($pendientes); ?></div>
            <div class="small text-muted">Pendientes</div>
        </div></div>
        <div class="col-md-3"><div class="card shadow-sm text-center py-2">
            <div class="fs-3 fw-bold text-success"><?php echo count($renovadas); ?></div>
            <div class="small text-muted">Renovadas</div>
        </div></div>
        <div class="col-md-3"><div class="card shadow-sm text-center py-2">
            <div class="fs-3 fw-bold"><?php echo count($renovaciones); ?></div>
            <div class="small text-muted">Total</div>
        </div></div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0 w-100" id="tabla-renovaciones">
                    <thead class="table-light">
                        <tr>
                            <th>Cliente</th>
                            <th>Tipo</th>
                            <th>Descripción</th>
                            <th>Fecha renovación</th>
                            <th>Vencimiento</th>
                            <th>Proveedor</th>
                            <th>Coste</th>
                            <th>Precio venta</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($renovaciones as $r):
                            $hoy = date('Y-m-d');
                            $proxima = $r->fecha_renovacion <= date('Y-m-d', strtotime('+30 days')) && $r->fecha_renovacion >= $hoy;
                            $estado_badge = [
                                'pendiente' => 'warning', 'avisado' => 'info', 'renovado' => 'success',
                                'facturado' => 'primary', 'cobrado' => 'success', 'cancelado' => 'danger'
                            ][$r->estado] ?? 'secondary';
                        ?>
                        <tr class="<?php echo $proxima ? 'table-warning' : ''; ?>">
                            <td><?php echo $r->cliente_nombre; ?></td>
                            <td><span class="badge bg-secondary"><?php echo ucfirst($r->tipo); ?></span></td>
                            <td><?php echo $r->descripcion; ?></td>
                            <td><?php echo $r->fecha_renovacion; ?><?php echo $proxima ? ' <i data-feather="alert-circle" class="icon-12 text-warning"></i>' : ''; ?></td>
                            <td><?php echo $r->fecha_vencimiento ?: '-'; ?></td>
                            <td><?php echo $r->proveedor ?: '-'; ?></td>
                            <td><?php echo $r->coste ? number_format($r->coste, 2, ',', '.') . ' €' : '-'; ?></td>
                            <td><?php echo $r->precio_venta ? number_format($r->precio_venta, 2, ',', '.') . ' €' : '-'; ?></td>
                            <td><span class="badge bg-<?php echo $estado_badge; ?>"><?php echo ucfirst($r->estado); ?></span></td>
                            <td>
                                <?php echo modal_anchor(get_uri("facturacion/renovacion_modal_form"), '<i data-feather="edit-2" class="icon-12"></i>', ['class' => 'btn btn-outline-secondary btn-sm', 'title' => 'Editar', 'data-post-id' => $r->id, 'data-post-cliente_id' => $r->cliente_id]); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script>
$(document).ready(function(){
    $('#tabla-renovaciones').DataTable({
        language: { search: 'Buscar:', lengthMenu: 'Mostrar _MENU_ registros', info: 'Mostrando _START_ a _END_ de _TOTAL_ registros', infoEmpty: 'Sin resultados', zeroRecords: 'No se encontraron resultados', paginate: { first: '«', last: '»', next: '›', previous: '‹' } },
        order: [[3,'asc']],
        drawCallback: function(){ feather.replace(); }
    });
});
</script>
