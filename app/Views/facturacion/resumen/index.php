<?php
$meses_nombres = [1=>'Ene',2=>'Feb',3=>'Mar',4=>'Abr',5=>'May',6=>'Jun',7=>'Jul',8=>'Ago',9=>'Sep',10=>'Oct',11=>'Nov',12=>'Dic'];
$anno_actual = $anno ?? date('Y');
?>
<style>
.resumen-tabla{border-collapse:collapse;width:100%;font-size:11px;table-layout:fixed}
.resumen-tabla th,.resumen-tabla td{border:1px solid #dee2e6;padding:3px 4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;vertical-align:middle}
.col-cliente{width:200px;min-width:120px}
.col-forma{width:75px;min-width:55px}
.col-concepto{width:220px;min-width:80px}
.col-factura{width:90px;min-width:60px;text-align:right!important}
.col-cobro{width:85px;min-width:55px;text-align:center!important}
/* Resizable columns */
.resumen-tabla th{position:relative;overflow:visible!important}
.resizer{position:absolute;right:0;top:0;height:100%;width:6px;cursor:col-resize;user-select:none;z-index:10;background:transparent}
.resizer:hover,.resizer.resizing{background:rgba(215,33,115,0.4)}
.th-mes{background:#d72173;color:white;text-align:center;font-weight:bold;font-size:12px;padding:5px 2px}
.th-sub{background:#f8d7e8;color:#555;text-align:center;font-size:10px;font-weight:600}
.tr-cliente td{background:#C6D617!important;font-weight:bold;color:#222;font-size:12px}
.tr-servicio td{background:#f0f7f0}
.tr-servicio:hover td{background:#e2f0e2!important}
.tr-total td{background:#d72173!important;color:white;font-weight:bold;text-align:right!important;font-size:11px}
.cobro-hecho{background:#28a745;color:white;border-radius:3px;padding:1px 4px;font-size:9px;font-weight:bold;display:block;text-align:center}
.cobro-pendiente{background:#ffc107;color:#333;border-radius:3px;padding:1px 4px;font-size:9px;font-weight:bold;display:block;text-align:center}
.cobro-rechazado{background:#dc3545;color:white;border-radius:3px;padding:1px 4px;font-size:9px;font-weight:bold;display:block;text-align:center}
.cobro-parcial{background:#17a2b8;color:white;border-radius:3px;padding:1px 4px;font-size:9px;font-weight:bold;display:block;text-align:center}
.cobro-vacio{color:#ddd;font-size:10px;display:block;text-align:center}
.td-ok{background-color:#d4edda!important}
.td-pend{background-color:#fff3cd!important}
.td-mal{background-color:#f8d7da!important}
.btn-anno{border:1px solid #d72173;color:#d72173;background:white;padding:3px 10px;border-radius:4px;cursor:pointer;text-decoration:none;font-size:12px}
.btn-anno.active,.btn-anno:hover{background:#d72173;color:white}
.forma-badge{font-size:9px;color:#666;background:#e9ecef;border-radius:3px;padding:1px 4px;display:inline-block}
.resumen-wrap{overflow-x:auto;width:100%}
.sep-row td{background:#e0e0e0!important;height:3px;padding:0}
@media print{.resumen-wrap{overflow:visible}.resumen-tabla{font-size:8px}.col-concepto{width:120px!important}.col-factura{width:60px!important}.col-cobro{width:60px!important}}
</style>

<div class="container-fluid p-3">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h5 class="mb-0" style="color:#d72173"><strong>TIC TAC COMUNICACIÓN DIGITAL</strong></h5>
            <small class="text-muted">Vista general de facturación — <?php echo $anno_actual; ?></small>
        </div>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            <span class="text-muted small">Año:</span>
            <?php foreach (range(date('Y')+1, date('Y')-3) as $a): ?>
                <a href="<?php echo get_uri("facturacion/resumen/$a"); ?>"
                   class="btn-anno <?php echo $a == $anno_actual ? 'active' : ''; ?>"><?php echo $a; ?></a>
            <?php endforeach; ?>
            <button class="btn btn-sm ms-2" style="background:#555;color:white;font-size:12px" onclick="window.print()">🖨️ Imprimir</button>
        </div>
    </div>

    <!-- TOTALES RÁPIDOS POR MES -->
    <div class="d-flex gap-2 mb-3 flex-wrap">
        <?php $total_anno = 0; ?>
        <?php foreach ($meses_nombres as $mes => $nombre): ?>
            <?php $tot = $totales_mes[$mes] ?? ['facturado'=>0,'cobrado'=>0,'pendiente'=>0]; $total_anno += $tot['facturado']; ?>
            <?php if (!$tot['facturado']) continue; ?>
            <div class="card shadow-sm" style="min-width:100px">
                <div class="card-body p-2 text-center">
                    <div style="font-size:11px;font-weight:bold;color:#d72173"><?php echo $nombre; ?></div>
                    <div style="font-size:13px;font-weight:bold"><?php echo number_format($tot['facturado'],0,',','.'); ?> €</div>
                    <div style="font-size:9px">
                        <span class="text-success">✓ <?php echo number_format($tot['cobrado'],0,',','.'); ?></span>
                        <?php if ($tot['pendiente'] > 0): ?>
                        <span class="text-warning ms-1">⏳ <?php echo number_format($tot['pendiente'],0,',','.'); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        <div class="card shadow-sm border-danger" style="min-width:120px">
            <div class="card-body p-2 text-center">
                <div style="font-size:11px;font-weight:bold;color:#d72173">TOTAL AÑO</div>
                <div style="font-size:15px;font-weight:bold"><?php echo number_format($total_anno,0,',','.'); ?> €</div>
            </div>
        </div>
    </div>

    <!-- TABLA MAESTRA -->
    <div class="resumen-wrap">
    <table class="resumen-tabla">
        <thead>
            <tr>
                <th class="col-cliente th-mes" rowspan="2">CLIENTES</th>
                <th class="col-forma th-mes" rowspan="2">PAGO</th>
                <?php foreach ($meses_nombres as $mes => $nombre): ?>
                    <th colspan="3" class="th-mes"><?php echo strtoupper($nombre); ?></th>
                <?php endforeach; ?>
            </tr>
            <tr>
                <?php foreach ($meses_nombres as $mes => $nombre): ?>
                    <th class="th-sub col-concepto">CONCEPTO</th>
                    <th class="th-sub col-factura">€</th>
                    <th class="th-sub col-cobro">COBRO</th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($clientes as $cliente): ?>
            <!-- CLIENTE -->
            <tr class="tr-cliente">
                <td class="col-cliente" colspan="2"><?php echo strtoupper($cliente['nombre']); ?></td>
                <?php foreach ($meses_nombres as $mes => $nombre): ?>
                    <td colspan="3"></td>
                <?php endforeach; ?>
            </tr>

            <!-- SERVICIOS DEL CLIENTE -->
            <?php foreach ($cliente['servicios'] as $servicio): ?>
            <tr class="tr-servicio">
                <td class="col-cliente ps-3" style="color:#555;font-style:italic"><?php echo $servicio['nombre']; ?></td>
                <td class="col-forma" style="text-align:center">
                    <?php if (!empty($servicio['forma_pago'])): ?>
                        <span class="forma-badge"><?php echo strtoupper(substr($servicio['forma_pago'],0,5)); ?></span>
                    <?php endif; ?>
                </td>
                <?php foreach ($meses_nombres as $mes => $nombre):
                    $d = $servicio['meses'][$mes] ?? null;
                    $concepto = $d ? $d['concepto'] : '';
                    $importe  = $d ? $d['importe']  : 0;
                    $estado   = $d ? $d['estado_cobro'] : '';
                    $tc = '';
                    if ($estado === 'cobrado') $tc = 'td-ok';
                    elseif (in_array($estado, ['rechazado','vencido'])) $tc = 'td-mal';
                    elseif ($estado === 'pendiente' && $importe > 0) $tc = 'td-pend';
                ?>
                    <td class="col-concepto <?php echo $tc; ?>" title="<?php echo htmlspecialchars($concepto); ?>">
                        <?php echo $concepto ? htmlspecialchars(mb_substr($concepto, 0, 28)) : ''; ?>
                    </td>
                    <td class="col-factura <?php echo $tc; ?>">
                        <?php echo $importe > 0 ? number_format($importe, 2, ',', '.') . ' €' : ''; ?>
                    </td>
                    <td class="col-cobro">
                        <?php if ($estado === 'cobrado'): ?>
                            <span class="cobro-hecho">✓ HECHO</span>
                        <?php elseif ($estado === 'pendiente' && $importe > 0): ?>
                            <span class="cobro-pendiente">⏳ PEND.</span>
                        <?php elseif ($estado === 'rechazado'): ?>
                            <span class="cobro-rechazado">✗ RECH.</span>
                        <?php elseif ($estado === 'vencido'): ?>
                            <span class="cobro-rechazado">⚠ RETR.</span>
                        <?php elseif ($estado === 'parcialmente_cobrado'): ?>
                            <span class="cobro-parcial">½ PARC.</span>
                        <?php else: ?>
                            <span class="cobro-vacio">—</span>
                        <?php endif; ?>
                    </td>
                <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>

            <tr class="sep-row"><td colspan="<?php echo 2 + 12*3; ?>"></td></tr>
        <?php endforeach; ?>

        <!-- TOTAL FACTURADO -->
        <tr class="tr-total">
            <td colspan="2">TOTAL FACTURADO</td>
            <?php foreach ($meses_nombres as $mes => $nombre):
                $tot = $totales_mes[$mes] ?? ['facturado'=>0,'cobrado'=>0];
            ?>
                <td><?php echo strtoupper($nombre); ?></td>
                <td><?php echo $tot['facturado'] > 0 ? number_format($tot['facturado'],2,',','.').' €' : '—'; ?></td>
                <td><?php echo $tot['cobrado'] > 0 ? number_format($tot['cobrado'],2,',','.').' €' : '—'; ?></td>
            <?php endforeach; ?>
        </tr>
        </tbody>
    </table>
    </div>

    <div class="mt-3 d-flex gap-3 align-items-center flex-wrap" style="font-size:11px">
        <span class="text-muted fw-bold">Leyenda:</span>
        <span><span class="cobro-hecho d-inline" style="display:inline!important">✓ HECHO</span> Cobrado</span>
        <span><span class="cobro-pendiente d-inline" style="display:inline!important">⏳ PEND.</span> Pendiente</span>
        <span><span class="cobro-rechazado d-inline" style="display:inline!important">✗ RECH.</span> Rechazado</span>
        <span><span class="cobro-rechazado d-inline" style="display:inline!important">⚠ RETR.</span> Retrasado</span>
        <span><span class="cobro-parcial d-inline" style="display:inline!important">½ PARC.</span> Parcial</span>
    </div>

<script>
// ====== COLUMNAS REDIMENSIONABLES (como Excel) ======
(function(){
    var table = document.querySelector('.resumen-tabla');
    if (!table) return;
    var ths = table.querySelectorAll('thead tr:first-child th');

    ths.forEach(function(th){
        var resizer = document.createElement('div');
        resizer.className = 'resizer';
        th.appendChild(resizer);

        var startX, startW, col;

        resizer.addEventListener('mousedown', function(e){
            e.preventDefault();
            startX = e.pageX;
            startW = th.offsetWidth;
            resizer.classList.add('resizing');

            // Encontrar todas las celdas de esta columna
            col = th.cellIndex;

            document.addEventListener('mousemove', onMouseMove);
            document.addEventListener('mouseup', onMouseUp);
        });

        function onMouseMove(e){
            var diff = e.pageX - startX;
            var newW = Math.max(60, startW + diff);
            th.style.width = newW + 'px';
            th.style.minWidth = newW + 'px';
        }

        function onMouseUp(){
            resizer.classList.remove('resizing');
            document.removeEventListener('mousemove', onMouseMove);
            document.removeEventListener('mouseup', onMouseUp);
        }
    });

    // Doble clic en resizer = autoajustar al contenido
    document.querySelectorAll('.resizer').forEach(function(r){
        r.addEventListener('dblclick', function(){
            var th = r.parentElement;
            var idx = th.cellIndex;
            var maxW = 60;
            table.querySelectorAll('tbody tr').forEach(function(tr){
                var td = tr.cells[idx];
                if (td) {
                    var tmp = document.createElement('span');
                    tmp.style.visibility = 'hidden';
                    tmp.style.position = 'absolute';
                    tmp.style.whiteSpace = 'nowrap';
                    tmp.style.fontSize = '11px';
                    tmp.textContent = td.textContent;
                    document.body.appendChild(tmp);
                    maxW = Math.max(maxW, tmp.offsetWidth + 12);
                    document.body.removeChild(tmp);
                }
            });
            th.style.width = Math.min(maxW, 400) + 'px';
        });
    });

    // Guardar anchos en localStorage
    function saveWidths(){
        var widths = [];
        ths.forEach(function(th){ widths.push(th.offsetWidth); });
        localStorage.setItem('fac_resumen_col_widths_' + window.location.pathname, JSON.stringify(widths));
    }

    // Restaurar anchos guardados
    var saved = localStorage.getItem('fac_resumen_col_widths_' + window.location.pathname);
    if (saved) {
        try {
            var widths = JSON.parse(saved);
            ths.forEach(function(th, i){ if(widths[i]) { th.style.width = widths[i]+'px'; } });
        } catch(e){}
    }

    // Guardar al salir
    window.addEventListener('beforeunload', saveWidths);

    // Tooltip: mostrar texto completo en celdas truncadas
    document.querySelectorAll('.resumen-tabla td').forEach(function(td){
        if (td.scrollWidth > td.clientWidth) {
            td.title = td.textContent.trim();
        }
    });
})();
</script>
</div>