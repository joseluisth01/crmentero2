<?php
$totales = $totales_mes ?? null;
$anno = $anno ?? date('Y');
$mes  = $mes  ?? date('n');
$meses_nombres = $meses_nombres ?? [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];
$totales_anno  = $totales_anno ?? [];
?>
<div class="container-fluid p-4">

    <!-- FILTRO MES/AÑO -->
    <div class="card shadow-sm mb-4">
        <div class="card-body py-2">
            <form method="get" action="<?php echo get_uri('facturacion'); ?>" class="d-flex align-items-center gap-3 flex-wrap">
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
                <button type="submit" class="btn btn-primary btn-sm"><i data-feather="search" class="icon-14"></i> Filtrar</button>
            </form>
        </div>
    </div>

    <!-- KPIs -->
    <div class="row mb-4">
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card shadow-sm border-start border-5 border-success">
                <div class="card-body">
                    <div class="text-muted small">Total Facturado</div>
                    <div class="fs-4 fw-bold text-success"><?php echo number_format($totales->total_facturado ?? 0, 2, ',', '.'); ?> €</div>
                    <div class="small text-muted"><?php echo $meses_nombres[$mes]; ?> <?php echo $anno; ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card shadow-sm border-start border-5 border-primary">
                <div class="card-body">
                    <div class="text-muted small">Total Cobrado</div>
                    <div class="fs-4 fw-bold text-primary"><?php echo number_format($totales->total_cobrado ?? 0, 2, ',', '.'); ?> €</div>
                    <div class="small text-muted"><?php echo $totales->num_cobradas ?? 0; ?> facturas cobradas</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card shadow-sm border-start border-5 border-warning">
                <div class="card-body">
                    <div class="text-muted small">Pendiente de Cobro</div>
                    <div class="fs-4 fw-bold text-warning"><?php echo number_format($totales->total_pendiente ?? 0, 2, ',', '.'); ?> €</div>
                    <div class="small text-muted"><?php echo $totales->num_pendientes ?? 0; ?> facturas pendientes</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card shadow-sm border-start border-5 border-danger">
                <div class="card-body">
                    <div class="text-muted small">Rechazado / Vencido</div>
                    <div class="fs-4 fw-bold text-danger"><?php echo number_format(0, 2, ',', '.'); ?> €</div>
                    <div class="small text-muted"><?php echo $totales->num_rechazadas ?? 0; ?> rechazadas</div>
                </div>
            </div>
        </div>
    </div>

    <!-- GRÁFICO ANUAL -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white fw-bold">
            <i data-feather="trending-up" class="icon-14"></i> Evolución anual <?php echo $anno; ?>
        </div>
        <div class="card-body">
            <canvas id="chartAnual" height="80"></canvas>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
var mesesLabels = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
var dataAnual = <?php echo json_encode($totales_anno); ?>;
var facturado = [], cobrado = [];
for (var i = 1; i <= 12; i++) {
    var found = dataAnual.find(function(d){ return parseInt(d.mes) === i; });
    facturado.push(found ? parseFloat(found.total_facturado) : 0);
    cobrado.push(found ? parseFloat(found.total_cobrado) : 0);
}
var ctx = document.getElementById('chartAnual').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: mesesLabels,
        datasets: [
            { label: 'Facturado', data: facturado, backgroundColor: 'rgba(215,33,115,0.7)', borderRadius: 4 },
            { label: 'Cobrado',   data: cobrado,   backgroundColor: 'rgba(198,214,23,0.8)', borderRadius: 4 },
        ]
    },
    options: { responsive: true, plugins: { legend: { position: 'top' } }, scales: { y: { beginAtZero: true } } }
});
</script>
