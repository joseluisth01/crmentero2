<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h5 class="mb-0">Generación de facturas desde contrato</h5>
            <small class="text-muted">Contrato: <strong><?php echo $contrato->title; ?></strong> — <?php echo $contrato->company_name; ?></small>
        </div>
        <a href="<?php echo get_uri('contracts/view/' . $contrato->id); ?>" class="btn btn-outline-secondary btn-sm">
            ← Volver al contrato
        </a>
    </div>

    <?php if ($ya_generadas > 0): ?>
    <div class="alert alert-warning">
        <strong>⚠️ Ya existen <?php echo $ya_generadas; ?> facturas generadas</strong> desde este contrato.
        Si continúas se generarán facturas adicionales. Comprueba que no hay duplicados.
    </div>
    <?php endif; ?>

    <div class="card shadow-sm mb-4">
        <div class="card-header" style="background:#d72173;color:white">
            <strong>Líneas del contrato y facturas que se generarán</strong>
        </div>
        <div class="card-body p-0">
            <table class="table table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Concepto</th>
                        <th>Precio</th>
                        <th>Tipo de pago</th>
                        <th>Períodos</th>
                        <th>Total</th>
                        <th>Facturas a generar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lineas as $l):
                        $tipo = $l->tipo_pago ?? 'mensual';
                        $num  = intval($l->num_periodos ?? 1);
                        $tipos_label = ['unico'=>'Pago único','mensual'=>'Mensual','trimestral'=>'Trimestral','semestral'=>'Semestral','anual'=>'Anual'];
                        $label = $tipos_label[$tipo] ?? $tipo;
                        $importe_por_periodo = $tipo === 'mensual' ? $l->rate : $l->total;
                    ?>
                    <tr>
                        <td><?php echo $l->title; ?></td>
                        <td><?php echo number_format($importe_por_periodo, 2, ',', '.'); ?> €</td>
                        <td><span class="badge bg-info"><?php echo $label; ?></span></td>
                        <td><?php echo $tipo === 'unico' ? '1' : $num; ?></td>
                        <td><?php echo number_format($importe_por_periodo * ($tipo==='unico'?1:$num), 2, ',', '.'); ?> €</td>
                        <td>
                            <?php if ($tipo === 'unico'): ?>
                                <span class="badge bg-secondary">1 factura de <?php echo number_format($l->total,2,',','.'); ?>€</span>
                            <?php else: ?>
                                <span class="badge bg-primary"><?php echo $num; ?> facturas de <?php echo number_format($l->rate,2,',','.'); ?>€/mes</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="d-flex gap-3 align-items-center">
        <button class="btn btn-lg" style="background:#d72173;color:white" onclick="generarFacturas(<?php echo $contrato->id; ?>)">
            ⚡ Generar facturas automáticamente
        </button>
        <span class="text-muted small">Se generarán facturas en estado "Emitida / Pendiente de cobro"</span>
    </div>

    <div id="resultado-generacion" class="mt-3" style="display:none"></div>
</div>

<script>
function generarFacturas(contractId) {
    if (!confirm('¿Generar las facturas automáticamente desde este contrato?')) return;
    var btn = event.target;
    btn.disabled = true;
    btn.textContent = '⏳ Generando...';
    $.post('<?php echo get_uri("facturacion/generar_facturas_contrato"); ?>', {contract_id: contractId}, function(res) {
        if (res.success) {
            $('#resultado-generacion').show().html(
                '<div class="alert alert-success">✅ Se han generado <strong>' + res.facturas_creadas + ' facturas</strong> correctamente. ' +
                '<a href="<?php echo get_uri("facturacion"); ?>?tab=facturas" class="alert-link">Ver facturas →</a></div>'
            );
            btn.textContent = '✅ Generadas';
        } else {
            $('#resultado-generacion').show().html('<div class="alert alert-danger">❌ Error: ' + res.message + '</div>');
            btn.disabled = false;
            btn.textContent = '⚡ Generar facturas automáticamente';
        }
    }, 'json');
}
</script>