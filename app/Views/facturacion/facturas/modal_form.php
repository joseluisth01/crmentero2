<?php
$is_edit = isset($model_info) && $model_info;
$title = $is_edit ? 'Editar factura' : 'Nueva factura';
?>
<div class="modal-header">
    <h5 class="modal-title"><?php echo $title; ?></h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
    <form id="form-factura">
        <input type="hidden" name="id" value="<?php echo $is_edit ? $model_info->id : ''; ?>">
        <div class="row">
            <div class="col-md-8 mb-3">
                <label class="form-label">Cliente *</label>
                <select class="form-select" name="cliente_id" required id="sel-cliente-fac">
                    <option value="">Seleccionar cliente...</option>
                    <?php foreach ($clientes as $c): ?>
                        <option value="<?php echo $c->id; ?>"
                            data-forma-pago="<?php echo $c->forma_pago_default; ?>"
                            <?php echo ($is_edit && $model_info->cliente_id == $c->id) ? 'selected' : ''; ?>>
                            <?php echo $c->nombre; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Estado factura</label>
                <select class="form-select" name="estado_factura">
                    <?php
                    $est_fac = ['borrador','emitida','enviada','pagada','cancelada','rectificada'];
                    $current = $is_edit ? $model_info->estado_factura : 'borrador';
                    foreach ($est_fac as $e): ?>
                        <option value="<?php echo $e; ?>" <?php echo $current == $e ? 'selected' : ''; ?>><?php echo ucfirst($e); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="row">
            <div class="col-md-3 mb-3">
                <label class="form-label">Mes *</label>
                <select class="form-select" name="mes" required>
                    <?php foreach ($meses_nombres as $n => $nombre): ?>
                        <option value="<?php echo $n; ?>" <?php echo ($is_edit ? $model_info->mes : date('n')) == $n ? 'selected' : ''; ?>><?php echo $nombre; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label">Año *</label>
                <select class="form-select" name="anno" required>
                    <?php foreach (range(date('Y')+1, date('Y')-5) as $a): ?>
                        <option value="<?php echo $a; ?>" <?php echo ($is_edit ? $model_info->anno : date('Y')) == $a ? 'selected' : ''; ?>><?php echo $a; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label">Fecha emisión *</label>
                <input type="date" class="form-control" name="fecha_emision" required value="<?php echo $is_edit ? $model_info->fecha_emision : date('Y-m-d'); ?>">
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label">Fecha vencimiento</label>
                <input type="date" class="form-control" name="fecha_vencimiento" value="<?php echo $is_edit ? $model_info->fecha_vencimiento : date('Y-m-d', strtotime('+30 days')); ?>">
            </div>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Forma de pago *</label>
                <select class="form-select" name="forma_pago" required id="sel-forma-pago-fac">
                    <?php
                    $formas = ['remesa' => '🏦 Remesa','transferencia' => '↗️ Transferencia','efectivo' => '💵 Efectivo','tarjeta' => '💳 Tarjeta','otro' => '❓ Otro'];
                    $current_fp = $is_edit ? $model_info->forma_pago : 'transferencia';
                    foreach ($formas as $val => $label): ?>
                        <option value="<?php echo $val; ?>" <?php echo $current_fp == $val ? 'selected' : ''; ?>><?php echo $label; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Estado de cobro</label>
                <select class="form-select" name="estado_cobro">
                    <?php
                    $est_cobro = ['pendiente','cobrado','rechazado','parcialmente_cobrado','vencido','no_procede'];
                    $current_cobro = $is_edit ? $model_info->estado_cobro : 'pendiente';
                    foreach ($est_cobro as $e): ?>
                        <option value="<?php echo $e; ?>" <?php echo $current_cobro == $e ? 'selected' : ''; ?>><?php echo ucfirst(str_replace('_',' ',$e)); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="mb-3">
            <div class="form-check">
                <input type="checkbox" class="form-check-input" name="es_kit_digital" id="chk-kit-digital" value="1"
                    <?php echo ($is_edit && $model_info->es_kit_digital) ? 'checked' : ''; ?>>
                <label class="form-check-label" for="chk-kit-digital">Factura Kit Digital</label>
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label">Observaciones internas <small class="text-muted">(no aparecen en la factura)</small></label>
            <textarea class="form-control" name="observaciones_internas" rows="2"><?php echo $is_edit ? $model_info->observaciones_internas : ''; ?></textarea>
        </div>
        <div class="mb-3">
            <label class="form-label">Observaciones para el cliente</label>
            <textarea class="form-control" name="observaciones_cliente" rows="2"><?php echo $is_edit ? $model_info->observaciones_cliente : ''; ?></textarea>
        </div>
    </form>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
    <button type="button" class="btn btn-primary" id="btn-guardar-factura">
        <i data-feather="save" class="icon-14"></i> Guardar
    </button>
</div>

<script>
// Auto-seleccionar forma de pago del cliente
$('#sel-cliente-fac').on('change', function(){
    var fp = $(this).find(':selected').data('forma-pago');
    if (fp) $('#sel-forma-pago-fac').val(fp);
});

$('#btn-guardar-factura').on('click', function(){
    var form = $('#form-factura');
    if (!form[0].checkValidity()) { form[0].reportValidity(); return; }
    $.post('<?php echo get_uri("facturacion/save_factura"); ?>', form.serialize(), function(res){
        if (res.success) {
            appAlert.success('Factura guardada.');
            $('#modal-container').modal('hide');
            if (typeof recargarFacturas === 'function') recargarFacturas();
            if (res.id && !$('#form-factura input[name=id]').val()) {
                window.location.href = '<?php echo get_uri("facturacion/factura_view/"); ?>' + res.id;
            }
        }
    }, 'json');
});
</script>
