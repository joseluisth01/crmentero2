<?php $is_edit = isset($model_info) && $model_info && isset($model_info->id); ?>
<div class="modal-header">
    <h5 class="modal-title"><?php echo $is_edit ? 'Editar proyecto Kit Digital' : 'Nuevo proyecto Kit Digital'; ?></h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
    <form id="form-kit-digital">
        <input type="hidden" name="id" value="<?php echo $is_edit ? $model_info->id : ''; ?>">
        <div class="row">
            <div class="col-md-8 mb-3">
                <label class="form-label">Cliente *</label>
                <select class="form-select" name="cliente_id" required>
                    <option value="">Seleccionar...</option>
                    <?php foreach ($clientes as $c): ?>
                        <option value="<?php echo $c->id; ?>" <?php echo ($is_edit && $model_info->cliente_id == $c->id) ? 'selected' : ''; ?>><?php echo $c->nombre; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Comercial</label>
                <select class="form-select" name="comercial_id">
                    <option value="">Sin asignar</option>
                    <?php foreach ($team_members as $tm): ?>
                        <option value="<?php echo $tm->id; ?>" <?php echo ($is_edit && $model_info->comercial_id == $tm->id) ? 'selected' : ''; ?>><?php echo $tm->name; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label">Solución / Categoría *</label>
            <input type="text" class="form-control" name="solucion" required value="<?php echo $is_edit ? $model_info->solucion : ''; ?>" placeholder="Ej: Presencia en internet, Comercio electrónico...">
        </div>
        <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label">Importe bono</label>
                <div class="input-group"><input type="number" class="form-control" name="importe_bono" step="0.01" value="<?php echo $is_edit ? $model_info->importe_bono : '0'; ?>"><span class="input-group-text">€</span></div>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Importe facturado</label>
                <div class="input-group"><input type="number" class="form-control" name="importe_facturado" step="0.01" value="<?php echo $is_edit ? $model_info->importe_facturado : '0'; ?>"><span class="input-group-text">€</span></div>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Importe recibido</label>
                <div class="input-group"><input type="number" class="form-control" name="importe_recibido" step="0.01" value="<?php echo $is_edit ? $model_info->importe_recibido : '0'; ?>"><span class="input-group-text">€</span></div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Fecha inicio</label>
                <input type="date" class="form-control" name="fecha_inicio" value="<?php echo $is_edit ? $model_info->fecha_inicio : ''; ?>">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Fecha fin</label>
                <input type="date" class="form-control" name="fecha_fin" value="<?php echo $is_edit ? $model_info->fecha_fin : ''; ?>">
            </div>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Estado del proyecto</label>
                <select class="form-select" name="estado_proyecto">
                    <?php
                    $estados_proy = ['pendiente_inicio','en_ejecucion','justificado','pendiente_cobro','cobrado','finalizado','cancelado'];
                    $cp = $is_edit ? $model_info->estado_proyecto : 'pendiente_inicio';
                    foreach ($estados_proy as $e): ?>
                        <option value="<?php echo $e; ?>" <?php echo $cp==$e?'selected':''; ?>><?php echo ucfirst(str_replace('_',' ',$e)); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Estado del cobro</label>
                <select class="form-select" name="estado_cobro">
                    <?php
                    $estados_cobro = ['pendiente','cobrado','rechazado','parcialmente_cobrado'];
                    $cc = $is_edit ? $model_info->estado_cobro : 'pendiente';
                    foreach ($estados_cobro as $e): ?>
                        <option value="<?php echo $e; ?>" <?php echo $cc==$e?'selected':''; ?>><?php echo ucfirst(str_replace('_',' ',$e)); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="mb-3">
            <div class="form-check">
                <input type="checkbox" class="form-check-input" name="genera_comision" value="1" id="chk-comision-kit"
                    <?php echo ($is_edit && $model_info->genera_comision) ? 'checked' : ''; ?>>
                <label class="form-check-label" for="chk-comision-kit">Genera comisión</label>
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label">Observaciones</label>
            <textarea class="form-control" name="observaciones" rows="3"><?php echo $is_edit ? $model_info->observaciones : ''; ?></textarea>
        </div>
    </form>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
    <button type="button" class="btn btn-primary" id="btn-guardar-kit">
        <i data-feather="save" class="icon-14"></i> Guardar
    </button>
</div>
<script>
$('#btn-guardar-kit').on('click', function(){
    var form = $('#form-kit-digital');
    if (!form[0].checkValidity()){ form[0].reportValidity(); return; }
    $.post('<?php echo get_uri("facturacion/save_kit_digital"); ?>', form.serialize(), function(res){
        if (res.success){ appAlert.success('Proyecto Kit Digital guardado.'); $('#modal-container').modal('hide'); location.reload(); }
    }, 'json');
});
</script>
