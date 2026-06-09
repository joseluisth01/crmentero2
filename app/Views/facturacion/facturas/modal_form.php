<?php
$is_edit = isset($model_info) && $model_info;
$title   = $is_edit ? 'Editar factura' : 'Nueva factura';
$meses_nombres = [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];
?>
<div class="modal-header">
    <h5 class="modal-title"><?php echo $title; ?></h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
    <?php if (!$is_edit): ?>
    <div class="alert alert-info py-2 small">
        <i data-feather="info" class="icon-14"></i>
        Crea primero la cabecera de la factura. Después podrás añadir las líneas con los importes desde la vista de la factura.
    </div>
    <?php endif; ?>
    <form id="form-factura">
        <input type="hidden" name="id" value="<?php echo $is_edit ? $model_info->id : ''; ?>">

        <div class="mb-3">
            <label class="form-label">Cliente *</label>
            <select class="form-select" name="cliente_id" required id="sel-cliente-fac">
                <option value="">Buscar cliente por nombre...</option>
                <?php foreach ($clientes as $c): ?>
                    <option value="<?php echo $c->id; ?>"
                        data-forma-pago="<?php echo $c->forma_pago; ?>"
                        <?php echo ($is_edit && $model_info->cliente_id == $c->id) ? 'selected' : ''; ?>>
                        <?php echo $c->nombre; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Mes *</label>
                <select class="form-select" name="mes" required>
                    <?php foreach ($meses_nombres as $n => $nombre): ?>
                        <option value="<?php echo $n; ?>" <?php echo ($is_edit ? $model_info->mes : date('n')) == $n ? 'selected' : ''; ?>><?php echo $nombre; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Año *</label>
                <select class="form-select" name="anno" required>
                    <?php foreach (range(date('Y')+1, date('Y')-3) as $a): ?>
                        <option value="<?php echo $a; ?>" <?php echo ($is_edit ? $model_info->anno : date('Y')) == $a ? 'selected' : ''; ?>><?php echo $a; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Forma de pago</label>
                <select class="form-select" name="forma_pago" id="sel-forma-pago-fac">
                    <?php
                    $formas = ['remesa'=>'🏦 Remesa','transferencia'=>'↗️ Transferencia','efectivo'=>'💵 Efectivo'];
                    $current_fp = $is_edit ? $model_info->forma_pago : 'transferencia';
                    foreach ($formas as $val => $label): ?>
                        <option value="<?php echo $val; ?>" <?php echo $current_fp == $val ? 'selected' : ''; ?>><?php echo $label; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Estado factura</label>
                <select class="form-select" name="estado_factura">
                    <?php foreach (['borrador','emitida','enviada'] as $e): ?>
                        <option value="<?php echo $e; ?>" <?php echo ($is_edit ? $model_info->estado_factura : 'emitida') == $e ? 'selected' : ''; ?>><?php echo ucfirst($e); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label">Observaciones internas</label>
            <textarea class="form-control" name="observaciones_internas" rows="2"><?php echo $is_edit ? ($model_info->observaciones_internas ?? '') : ''; ?></textarea>
        </div>

        <div class="mb-3">
            <label class="form-label">Cobro</label>
            <select class="form-select" name="quinquena">
                <option value="1" <?php echo ($is_edit && ($model_info->quinquena ?? 1) == 1) ? 'selected' : (!$is_edit ? 'selected' : ''); ?>>1ª — Primeros del mes</option>
                <option value="2" <?php echo ($is_edit && ($model_info->quinquena ?? 1) == 2) ? 'selected' : ''; ?>>2ª — Finales del mes</option>
            </select>
            <small class="text-muted">Indica si este cobro es a primeros o a finales del mes</small>
        </div>

        <hr class="my-3">
        <div class="mb-2">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="recurrente" id="chk-recurrente" value="1"
                    <?php echo ($is_edit && !empty($model_info->recurrente)) ? 'checked' : ''; ?>>
                <label class="form-check-label fw-bold" for="chk-recurrente">
                    🔄 Factura recurrente
                </label>
                <small class="text-muted ms-2">Se replicará automáticamente al pulsar "Generar mes"</small>
            </div>
        </div>
        <div id="bloque-limite" style="display:none" class="row mt-2">
            <div class="col-12 mb-1">
                <small class="text-muted"><i data-feather="info" class="icon-12"></i> Opcional: si indicas un mes límite, a partir de ese mes esta factura ya no se replicará.</small>
            </div>
            <div class="col-md-6 mb-2">
                <label class="form-label small">Mes límite (opcional)</label>
                <select class="form-select form-select-sm" name="recurrente_mes_limite">
                    <option value="">Sin límite</option>
                    <?php foreach ([1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'] as $n => $nombre): ?>
                        <option value="<?php echo $n; ?>" <?php echo ($is_edit && ($model_info->recurrente_mes_limite ?? '') == $n) ? 'selected' : ''; ?>><?php echo $nombre; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6 mb-2">
                <label class="form-label small">Año límite (opcional)</label>
                <select class="form-select form-select-sm" name="recurrente_anno_limite">
                    <option value="">Sin límite</option>
                    <?php foreach (range(date('Y'), date('Y')+5) as $a): ?>
                        <option value="<?php echo $a; ?>" <?php echo ($is_edit && ($model_info->recurrente_anno_limite ?? '') == $a) ? 'selected' : ''; ?>><?php echo $a; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </form>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
    <button type="button" class="btn btn-primary" id="btn-guardar-factura">
        <i data-feather="save" class="icon-14"></i> <?php echo $is_edit ? 'Guardar cambios' : 'Crear y añadir líneas →'; ?>
    </button>
</div>

<script>
// Mostrar/ocultar bloque límite según recurrente
$(document).on('change', '#chk-recurrente', function(){
    $(this).is(':checked') ? $('#bloque-limite').slideDown(200) : $('#bloque-limite').slideUp(200);
});
// Al editar, mostrar si ya era recurrente
if ($('#chk-recurrente').is(':checked')) $('#bloque-limite').show();

// Inicializar select2 con buscador en el selector de cliente
if (typeof $.fn.select2 !== 'undefined') {
    $('#sel-cliente-fac').select2({
        dropdownParent: $('#modal-container'),
        placeholder: 'Buscar cliente por nombre...',
        allowClear: true,
        width: '100%',
        language: { noResults: function(){ return 'No se encontraron clientes'; }, searching: function(){ return 'Buscando...'; } }
    });
}

// Auto-seleccionar forma de pago del cliente al elegirlo
$('#sel-cliente-fac').on('change', function(){
    var fp = $(this).find(':selected').data('forma-pago');
    if (fp) $('#sel-forma-pago-fac').val(fp);
});

$('#btn-guardar-factura').on('click', function(){
    var form = $('#form-factura');
    if (!form[0].checkValidity()) { form[0].reportValidity(); return; }
    var btn = $(this).prop('disabled', true).text('Guardando...');
    $.post('<?php echo get_uri("facturacion/save_factura"); ?>', form.serialize(), function(res){
        if (res.success) {
            appAlert.success('Factura creada.');
            $('#modal-container').modal('hide');
            // Si es nueva, ir directamente a la factura para añadir líneas
            if (res.id && !$('#form-factura input[name=id]').val()) {
                window.location.href = '<?php echo get_uri("facturacion/factura_view/"); ?>' + res.id;
            } else {
                if (typeof recargarFacturas === 'function') recargarFacturas();
            }
        } else {
            appAlert.danger('Error al guardar la factura.');
            btn.prop('disabled', false).text('Guardar');
        }
    }, 'json').fail(function(){
        appAlert.danger('Error del servidor.');
        btn.prop('disabled', false);
    });
});
</script>