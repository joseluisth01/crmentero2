<div class="container-fluid p-4">

    <!-- FILTRO MES/AÑO -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card shadow-sm">
                <div class="card-body py-2">
                    <form method="get" action="<?php echo get_uri('facturacion'); ?>" class="d-flex align-items-center gap-3">
                        <label class="mb-0 fw-bold">Ver período:</label>
                        <select name="mes" class="form-select w-auto">
                            <?php foreach ($meses_nombres as $n => $nombre): ?>
                                <option value="<?php echo $n; ?>" <?php echo $mes == $n ? 'selected' : ''; ?>><?php echo $nombre; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="anno" class="form-select w-auto">
                            <?php foreach (range(date('Y'), date('Y')-5) as $a): ?>
                                <option value="<?php echo $a; ?>" <?php echo $anno == $a ? 'selected' : ''; ?>><?php echo $a; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i data-feather="search" class="icon-14"></i> Filtrar
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- KPIs PRINCIPALES -->
    <div class="row mb-4">
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card shadow-sm border-start border-5 border-success">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small">Total Facturado</div>
                            <div class="fs-4 fw-bold text-success"><?php echo number_format($resumen_mes->total_facturado ?? 0, 2, ',', '.'); ?> €</div>
                        </div>
                        <i data-feather="file-text" style="width:36px;height:36px;color:#198754;opacity:.4"></i>
                    </div>
                    <div class="small text-muted mt-1"><?php echo $meses_nombres[$mes]; ?> <?php echo $anno; ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card shadow-sm border-start border-5 border-primary">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small">Total Cobrado</div>
                            <div class="fs-4 fw-bold text-primary"><?php echo number_format($resumen_mes->total_cobrado ?? 0, 2, ',', '.'); ?> €</div>
                        </div>
                        <i data-feather="check-circle" style="width:36px;height:36px;color:#0d6efd;opacity:.4"></i>
                    </div>
                    <div class="small text-muted mt-1"><?php echo $resumen_mes->total_facturas ?? 0; ?> facturas</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card shadow-sm border-start border-5 border-warning">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small">Pendiente de Cobro</div>
                            <div class="fs-4 fw-bold text-warning"><?php echo number_format($resumen_mes->total_pendiente ?? 0, 2, ',', '.'); ?> €</div>
                        </div>
                        <i data-feather="clock" style="width:36px;height:36px;color:#ffc107;opacity:.4"></i>
                    </div>
                    <div class="small text-muted mt-1"><?php echo $resumen_mes->facturas_pendientes ?? 0; ?> facturas pendientes</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card shadow-sm border-start border-5 border-danger">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small">Rechazado / Vencido</div>
                            <div class="fs-4 fw-bold text-danger"><?php echo number_format($resumen_mes->total_rechazado ?? 0, 2, ',', '.'); ?> €</div>
                        </div>
                        <i data-feather="x-circle" style="width:36px;height:36px;color:#dc3545;opacity:.4"></i>
                    </div>
                    <div class="small text-muted mt-1"><?php echo $resumen_mes->facturas_vencidas ?? 0; ?> vencidas</div>
                </div>
            </div>
        </div>
    </div>

    <!-- SEGUNDA FILA KPIs -->
    <div class="row mb-4">
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card shadow-sm">
                <div class="card-body text-center">
                    <i data-feather="users" class="icon-28 text-info"></i>
                    <div class="fs-3 fw-bold mt-2"><?php echo $clientes_activos; ?></div>
                    <div class="text-muted small">Clientes Activos</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card shadow-sm">
                <div class="card-body text-center">
                    <i data-feather="repeat" class="icon-28 text-success"></i>
                    <div class="fs-3 fw-bold mt-2"><?php echo number_format($servicios_activos->total_recurrente ?? 0, 2, ',', '.'); ?> €</div>
                    <div class="text-muted small">Ingresos recurrentes/mes (<?php echo $servicios_activos->num_servicios ?? 0; ?> servicios)</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card shadow-sm">
                <div class="card-body text-center">
                    <i data-feather="layers" class="icon-28" style="color:#d72173"></i>
                    <div class="fs-3 fw-bold mt-2"><?php echo number_format($resumen_mes->total_remesa ?? 0, 2, ',', '.'); ?> €</div>
                    <div class="text-muted small">Por Remesa</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card shadow-sm">
                <div class="card-body text-center">
                    <i data-feather="arrow-up-right" class="icon-28 text-primary"></i>
                    <div class="fs-3 fw-bold mt-2"><?php echo number_format($resumen_mes->total_transferencia ?? 0, 2, ',', '.'); ?> €</div>
                    <div class="text-muted small">Por Transferencia</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <!-- GRÁFICO ANUAL -->
        <div class="col-md-8 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-white fw-bold">
                    <i data-feather="trending-up" class="icon-14"></i> Evolución anual <?php echo $anno; ?>
                </div>
                <div class="card-body">
                    <canvas id="chartAnual" height="100"></canvas>
                </div>
            </div>
        </div>

        <!-- TOP CLIENTES -->
        <div class="col-md-4 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-white fw-bold">
                    <i data-feather="award" class="icon-14"></i> Top clientes — <?php echo $meses_nombres[$mes]; ?> <?php echo $anno; ?>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light"><tr><th>Cliente</th><th class="text-end">Facturado</th><th class="text-end">Cobrado</th></tr></thead>
                            <tbody>
                                <?php foreach (array_slice($resumen_clientes, 0, 10) as $rc): ?>
                                <tr>
                                    <td><a href="<?php echo get_uri("facturacion/cliente_view/$rc->cliente_id"); ?>" class="text-decoration-none"><?php echo $rc->nombre; ?></a></td>
                                    <td class="text-end"><?php echo number_format($rc->total_facturado, 2, ',', '.'); ?> €</td>
                                    <td class="text-end text-success"><?php echo number_format($rc->total_cobrado, 2, ',', '.'); ?> €</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- RENOVACIONES PRÓXIMAS -->
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-white fw-bold">
                    <i data-feather="refresh-cw" class="icon-14 text-warning"></i> Renovaciones próximas (30 días)
                </div>
                <div class="card-body p-0">
                    <?php if (empty($renovaciones_proximas)): ?>
                        <p class="text-muted p-3 mb-0">No hay renovaciones próximas.</p>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light"><tr><th>Cliente</th><th>Descripción</th><th>Fecha</th><th>Precio</th></tr></thead>
                            <tbody>
                                <?php foreach ($renovaciones_proximas as $r): ?>
                                <tr>
                                    <td><?php echo $r->cliente_nombre; ?></td>
                                    <td><?php echo $r->descripcion; ?></td>
                                    <td><?php echo $r->fecha_renovacion; ?></td>
                                    <td><?php echo number_format($r->precio_venta ?? 0, 2, ',', '.'); ?> €</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- AVISOS -->
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-white fw-bold d-flex justify-content-between">
                    <span><i data-feather="bell" class="icon-14 text-danger"></i> Avisos pendientes (<?php echo count($avisos); ?>)</span>
                </div>
                <div class="card-body p-0" style="max-height:300px;overflow-y:auto">
                    <?php if (empty($avisos)): ?>
                        <p class="text-muted p-3 mb-0">Sin avisos pendientes. ✅</p>
                    <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($avisos as $av): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-start">
                                <div class="ms-2 me-auto">
                                    <div class="small text-muted"><?php echo $av->tipo; ?> — <?php echo $av->fecha_aviso; ?></div>
                                    <?php echo $av->mensaje; ?>
                                </div>
                                <button class="btn btn-sm btn-outline-secondary ms-2" onclick="marcarAvisoLeido(<?php echo $av->id; ?>, this)" title="Marcar leído">
                                    <i data-feather="check" class="icon-12"></i>
                                </button>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js para gráfico anual -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
var meses = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
var dataAnual = <?php echo json_encode($resumen_anno); ?>;
var labels = [], facturado = [], cobrado = [];
for (var i = 1; i <= 12; i++) {
    labels.push(meses[i-1]);
    var found = dataAnual.find(function(d){ return parseInt(d.mes) === i; });
    facturado.push(found ? parseFloat(found.total_facturado) : 0);
    cobrado.push(found ? parseFloat(found.total_cobrado) : 0);
}
var ctx = document.getElementById('chartAnual').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: labels,
        datasets: [
            { label: 'Facturado', data: facturado, backgroundColor: 'rgba(215,33,115,0.7)', borderRadius: 4 },
            { label: 'Cobrado',   data: cobrado,   backgroundColor: 'rgba(198,214,23,0.8)', borderRadius: 4 },
        ]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'top' } },
        scales: { y: { beginAtZero: true } }
    }
});

function marcarAvisoLeido(id, btn) {
    $.post('<?php echo get_uri("facturacion/marcar_aviso_leido"); ?>', {id: id}, function(res){
        if (res.success) $(btn).closest('li').fadeOut();
    }, 'json');
}
</script>
