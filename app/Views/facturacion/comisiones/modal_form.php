<?php $is_edit = isset($model_info) && $model_info && isset($model_info->id); ?>
<div class="modal-header">
    <h5 class="modal-title"><?php echo $is_edit ? 'Editar comisión' : 'Nueva comisión'; ?></h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
    <form id="form-comision">
        <input type="hidden" name="id" value="<?php echo $is_edit ? $model_info->id : ''; ?>">
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Persona *</label>
                <select class="form-select" name="persona_id" required>
                    <option value="">Seleccionar...</option>
                    <?php foreach ($team_members as $tm): ?>
                        <option value="<?php echo $tm->id; ?>" <?php echo ($is_edit && $model_info->persona_id == $tm->id) ? 'selected' : ''; ?>><?php echo $tm->name; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Cliente *</label>
                <select class="form-select" name="cliente_id" required>
                    <option value="">Seleccionar...</option>
                    <?php foreach ($clientes as $c): ?>
                        <option value="<?php echo $c->id; ?>" <?php echo ($is_edit && $model_info->cliente_id == $c->id) ? 'selected' : ''; ?>><?php echo $c->nombre; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Tipo de comisión *</label>
                <select class="form-select" name="tipo_comision" required>
                    <?php $tipos = ['nuevo_cliente','renovacion','proyecto_puntual','kit_digital','otro'];
                    $ct = $is_edit ? $model_info->tipo_comision : 'nuevo_cliente';
                    foreach ($tipos as $t): ?>
                        <option value="<?php echo $t; ?>" <?php echo $ct==$t?'selected':''; ?>><?php echo ucfirst(str_replace('_',' ',$t)); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">% Comisión *</label>
                <div class="input-group">
                    <input type="number" class="form-control" name="porcentaje" step="0.01" required
                        value="<?php echo $is_edit ? $model_info->porcentaje : '8'; ?>">
                    <span class="input-group-text">%</span>
                </div>
                <div class="form-text">8% nuevo cliente / 4% renovación</div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Importe base <small class="text-muted">(total del contrato/factura)</small></label>
                <div class="input-group"><input type="number" class="form-control" name="importe_base" step="0.01" value="<?php echo $is_edit ? $model_info->importe_base : '0'; ?>"><span class="input-group-text">€</span></div>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Importe recibido <small class="text-muted">(base del cálculo)</small></label>
                <div class="input-group"><input type="number" class="form-control" name="importe_recibido" step="0.01" id="inp-importe-recibido" value="<?php echo $is_edit ? $model_info->importe_recibido : '0'; ?>"><span class="input-group-text">€</span></div>
            </div>
        </div>
        <div class="alert alert-info py-2 small">
            <i data-feather="info" class="icon-12"></i>
            La comisión se calcula automáticamente: <strong id="preview-comision">0,00 €</strong>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Estado de pago</label>
                <select class="form-select" name="estado_pago">
                    <?php $estados = ['pendiente','calculada','pagada','anulada'];
                    $ce = $is_edit ? $model_info->estado_pago : 'pendiente';
                    foreach ($estados as $e): ?>
                        <option value="<?php echo $e; ?>" <?php echo $ce==$e?'selected':''; ?>><?php echo ucfirst($e); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Fecha de pago</label>
                <input type="date" class="form-control" name="fecha_pago" value="<?php echo $is_edit ? $model_info->fecha_pago : ''; ?>">
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label">Observaciones</label>
            <textarea class="form-control" name="observaciones" rows="2"><?php echo $is_edit ? $model_info->observaciones : ''; ?></textarea>
        </div>
    </form>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
    <button type="button" class="btn btn-primary" id="btn-guardar-comision">
        <i data-feather="save" class="icon-14"></i> Guardar
    </button>
</div>
<script>
function actualizarPreviewComision(){
    var recibido  = parseFloat($('#inp-importe-recibido').val()) || 0;
    var pct       = parseFloat($('[name=porcentaje]').val()) || 0;
    var comision  = recibido * pct / 100;
    $('#preview-comision').text(comision.toFixed(2).replace('.',',') + ' €');
}
$('#inp-importe-recibido, [name=porcentaje]').on('input', actualizarPreviewComision);
actualizarPreviewComision();

$('#btn-guardar-comision').on('click', function(){
    var form = $('#form-comision');
    if (!form[0].checkValidity()){ form[0].reportValidity(); return; }
    $.post('<?php echo get_uri("facturacion/save_comision"); ?>', form.serialize(), function(res){
        if (res.success){ appAlert.success('Comisión guardada.'); $('#modal-container').modal('hide'); location.reload(); }
    }, 'json');
});
</script>
