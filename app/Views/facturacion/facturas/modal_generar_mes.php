<div class="modal-header">
    <h5 class="modal-title"><i data-feather="zap" class="icon-16"></i> Generar facturas recurrentes del mes</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
    <div class="alert alert-info">
        <i data-feather="info" class="icon-14"></i>
        Este proceso genera automáticamente las facturas de todos los servicios recurrentes activos para el mes seleccionado.
        Los servicios pausados, cancelados o finalizados <strong>no se incluyen</strong>.
        Si ya existe una factura en borrador para ese cliente/mes, se añaden las líneas a esa factura.
    </div>
    <form id="form-generar-mes">
        <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label">Mes *</label>
                <select class="form-select" name="mes" required>
                    <?php foreach ($meses_nombres as $n => $nombre): ?>
                        <option value="<?php echo $n; ?>" <?php echo $mes_actual == $n ? 'selected' : ''; ?>><?php echo $nombre; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Año *</label>
                <select class="form-select" name="anno" required>
                    <?php foreach (range($anno_actual+1, $anno_actual-2) as $a): ?>
                        <option value="<?php echo $a; ?>" <?php echo $anno_actual == $a ? 'selected' : ''; ?>><?php echo $a; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Estado inicial de las facturas</label>
                <select class="form-select" name="estado_inicial">
                    <option value="borrador">Borrador (revisar antes de enviar)</option>
                    <option value="emitida">Emitida directamente</option>
                </select>
            </div>
        </div>
    </form>
    <div id="resultado-generacion" style="display:none" class="mt-3"></div>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
    <button type="button" class="btn btn-warning" id="btn-ejecutar-generacion">
        <i data-feather="zap" class="icon-14"></i> Generar facturas
    </button>
</div>
<script>
$('#btn-ejecutar-generacion').on('click', function(){
    if (!confirm('¿Confirmar generación de facturas recurrentes?')) return;
    var btn = $(this);
    btn.prop('disabled', true).html('<i data-feather="loader" class="icon-14"></i> Generando...');
    feather.replace();
    $.post('<?php echo get_uri("facturacion/generar_mes"); ?>', $('#form-generar-mes').serialize(), function(res){
        btn.prop('disabled', false).html('<i data-feather="zap" class="icon-14"></i> Generar facturas');
        feather.replace();
        if (res.success) {
            var r = res.resultado;
            $('#resultado-generacion').removeClass('d-none').show().html(
                '<div class="alert alert-success">' +
                '<strong>✅ Proceso completado</strong><br>' +
                'Líneas generadas: <strong>' + r.generadas + '</strong><br>' +
                (r.errores.length ? 'Errores: ' + r.errores.join(', ') : '') +
                '</div>'
            );
        }
    }, 'json');
});
</script>
