<?php $is_edit = isset($model_info) && $model_info && isset($model_info->id); ?>
<div class="modal-header">
    <h5 class="modal-title"><?php echo $is_edit ? 'Editar servicio contratado' : 'Añadir servicio al cliente'; ?></h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
    <form id="form-cliente-servicio">
        <input type="hidden" name="id" value="<?php echo $is_edit ? $model_info->id : ''; ?>">

        <div class="row">
            <div class="col-md-8 mb-3">
                <label class="form-label">Cliente *</label>
                <select class="form-select" name="cliente_id" required>
                    <option value="">Seleccionar...</option>
                    <?php foreach ($clientes as $c): ?>
                        <option value="<?php echo $c->id; ?>"
                            <?php echo (($is_edit && $model_info->cliente_id==$c->id) || (!$is_edit && $cliente_id==$c->id)) ? 'selected' : ''; ?>>
                            <?php echo $c->nombre; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Estado *</label>
                <select class="form-select" name="estado" required>
                    <?php $estados = ['activo','pausado','finalizado','cancelado','pendiente_revision'];
                    $ce = $is_edit ? $model_info->estado : 'activo';
                    foreach ($estados as $e): ?>
                        <option value="<?php echo $e; ?>" <?php echo $ce==$e?'selected':''; ?>><?php echo ucfirst(str_replace('_',' ',$e)); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Autorellenar desde catálogo -->
        <div class="mb-3">
            <label class="form-label">Servicio del catálogo <small class="text-muted">(opcional, autoellena campos)</small></label>
            <select class="form-select" id="sel-catalogo-cs">
                <option value="">— Introducir manualmente —</option>
                <?php foreach ($catalogo as $s): ?>
                    <option value="<?php echo $s->id; ?>"
                        data-concepto="<?php echo htmlspecialchars($s->nombre); ?>"
                        data-importe="<?php echo $s->importe_base; ?>"
                        data-iva="<?php echo $s->iva_porcentaje; ?>"
                        data-periodicidad="<?php echo $s->periodicidad; ?>"
                        data-recurrente="<?php echo $s->es_recurrente; ?>"
                        data-comision="<?php echo $s->genera_comision; ?>"
                        <?php echo ($is_edit && $model_info->servicio_catalogo_id==$s->id) ? 'selected' : ''; ?>>
                        <?php echo $s->nombre; ?> — <?php echo ucfirst($s->categoria); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Concepto (aparece en la factura) *</label>
            <input type="text" class="form-control" name="concepto" required id="inp-concepto-cs" value="<?php echo $is_edit ? $model_info->concepto : ''; ?>">
            <input type="hidden" name="servicio_catalogo_id" id="inp-catalogo-id-cs" value="<?php echo $is_edit ? $model_info->servicio_catalogo_id : ''; ?>">
        </div>

        <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label">Importe *</label>
                <div class="input-group"><input type="number" class="form-control" name="importe" step="0.01" required id="inp-importe-cs" value="<?php echo $is_edit ? $model_info->importe : ''; ?>"><span class="input-group-text">€</span></div>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">IVA %</label>
                <div class="input-group"><input type="number" class="form-control" name="iva_porcentaje" step="0.01" id="inp-iva-cs" value="<?php echo $is_edit ? $model_info->iva_porcentaje : '21'; ?>"><span class="input-group-text">%</span></div>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Periodicidad</label>
                <select class="form-select" name="periodicidad" id="sel-periodicidad-cs">
                    <?php $periodos = ['mensual','trimestral','semestral','anual','puntual'];
                    $cp = $is_edit ? $model_info->periodicidad : 'mensual';
                    foreach ($periodos as $p): ?>
                        <option value="<?php echo $p; ?>" <?php echo $cp==$p?'selected':''; ?>><?php echo ucfirst($p); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <div class="form-check mt-3">
                    <input type="checkbox" class="form-check-input" name="es_recurrente" value="1" id="chk-recurrente-cs"
                        <?php echo (!$is_edit || $model_info->es_recurrente) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="chk-recurrente-cs">Servicio recurrente</label>
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Forma de pago <small class="text-muted">(vacío = heredar del cliente)</small></label>
                <select class="form-select" name="forma_pago">
                    <option value="">Heredar del cliente</option>
                    <option value="remesa" <?php echo ($is_edit && $model_info->forma_pago=='remesa') ? 'selected' : ''; ?>>🏦 Remesa</option>
                    <option value="transferencia" <?php echo ($is_edit && $model_info->forma_pago=='transferencia') ? 'selected' : ''; ?>>↗️ Transferencia</option>
                    <option value="efectivo" <?php echo ($is_edit && $model_info->forma_pago=='efectivo') ? 'selected' : ''; ?>>💵 Efectivo</option>
                </select>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Fecha inicio</label>
                <input type="date" class="form-control" name="fecha_inicio" value="<?php echo $is_edit ? $model_info->fecha_inicio : ''; ?>">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Fecha fin prevista</label>
                <input type="date" class="form-control" name="fecha_fin" value="<?php echo $is_edit ? $model_info->fecha_fin : ''; ?>">
            </div>
        </div>

        <div id="bloque-baja" class="row <?php echo ($is_edit && in_array($model_info->estado,['cancelado','finalizado'])) ? '' : 'd-none'; ?>">
            <div class="col-md-6 mb-3">
                <label class="form-label">Fecha de baja</label>
                <input type="date" class="form-control" name="fecha_baja" value="<?php echo $is_edit ? $model_info->fecha_baja : ''; ?>">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Motivo de baja</label>
                <select class="form-select" name="motivo_baja">
                    <option value="">Sin especificar</option>
                    <?php $motivos = ['cancelacion_cliente','fin_proyecto','impago','cambio_servicio','pausa_temporal','otro'];
                    $cm = $is_edit ? $model_info->motivo_baja : '';
                    foreach ($motivos as $m): ?>
                        <option value="<?php echo $m; ?>" <?php echo $cm==$m?'selected':''; ?>><?php echo ucfirst(str_replace('_',' ',$m)); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" name="genera_comision" value="1" id="chk-comision-cs"
                        <?php echo ($is_edit && $model_info->genera_comision) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="chk-comision-cs">Genera comisión</label>
                </div>
            </div>
            <div class="col-md-6 mb-3" id="bloque-comercial">
                <label class="form-label">Comercial</label>
                <select class="form-select" name="comercial_id">
                    <option value="">Sin asignar</option>
                    <?php foreach ($team_members as $tm): ?>
                        <option value="<?php echo $tm->id; ?>" <?php echo ($is_edit && $model_info->comercial_id==$tm->id) ? 'selected' : ''; ?>><?php echo $tm->name; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label">Observaciones internas</label>
            <textarea class="form-control" name="observaciones" rows="2"><?php echo $is_edit ? $model_info->observaciones : ''; ?></textarea>
        </div>
    </form>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
    <button type="button" class="btn btn-primary" id="btn-guardar-cliente-servicio">
        <i data-feather="save" class="icon-14"></i> Guardar servicio
    </button>
</div>
<script>
// Autorellenar al seleccionar del catálogo
$('#sel-catalogo-cs').on('change', function(){
    var opt = $(this).find(':selected');
    if (!opt.val()) return;
    $('#inp-concepto-cs').val(opt.data('concepto'));
    $('#inp-importe-cs').val(opt.data('importe'));
    $('#inp-iva-cs').val(opt.data('iva'));
    $('#sel-periodicidad-cs').val(opt.data('periodicidad'));
    if (opt.data('recurrente') == 1) $('#chk-recurrente-cs').prop('checked', true);
    if (opt.data('comision') == 1) $('#chk-comision-cs').prop('checked', true);
    $('#inp-catalogo-id-cs').val(opt.val());
});

// Mostrar bloque de baja si se selecciona cancelado/finalizado
$('[name=estado]').on('change', function(){
    if ($(this).val() === 'cancelado' || $(this).val() === 'finalizado') {
        $('#bloque-baja').removeClass('d-none');
    } else {
        $('#bloque-baja').addClass('d-none');
    }
});

$('#btn-guardar-cliente-servicio').on('click', function(){
    var form = $('#form-cliente-servicio');
    if (!form[0].checkValidity()){ form[0].reportValidity(); return; }
    $.post('<?php echo get_uri("facturacion/save_cliente_servicio"); ?>', form.serialize(), function(res){
        if (res.success){
            appAlert.success('Servicio guardado correctamente.');
            $('#modal-container').modal('hide');
            location.reload();
        }
    }, 'json');
});
</script>
