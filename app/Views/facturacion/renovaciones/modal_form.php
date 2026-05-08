<?php $is_edit = isset($model_info) && $model_info && isset($model_info->id); ?>
<div class="modal-header">
    <h5 class="modal-title"><?php echo $is_edit ? 'Editar renovación' : 'Nueva renovación'; ?></h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
    <form id="form-renovacion">
        <input type="hidden" name="id" value="<?php echo $is_edit ? $model_info->id : ''; ?>">
        <div class="row">
            <div class="col-md-8 mb-3">
                <label class="form-label">Cliente *</label>
                <select class="form-select" name="cliente_id" required>
                    <option value="">Seleccionar...</option>
                    <?php foreach ($clientes as $c): ?>
                        <option value="<?php echo $c->id; ?>"
                            <?php echo (($is_edit && $model_info->cliente_id == $c->id) || (!$is_edit && $cliente_id == $c->id)) ? 'selected' : ''; ?>>
                            <?php echo $c->nombre; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Tipo *</label>
                <select class="form-select" name="tipo" required>
                    <?php $tipos = ['dominio','hosting','ssl','correo','licencia','plugin','mantenimiento_anual','otro'];
                    $ct = $is_edit ? $model_info->tipo : '';
                    foreach ($tipos as $t): ?>
                        <option value="<?php echo $t; ?>" <?php echo $ct==$t?'selected':''; ?>><?php echo ucfirst(str_replace('_',' ',$t)); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label">Descripción / Dominio *</label>
            <input type="text" class="form-control" name="descripcion" required value="<?php echo $is_edit ? $model_info->descripcion : ''; ?>">
        </div>
        <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label">Fecha de renovación *</label>
                <input type="date" class="form-control" name="fecha_renovacion" required value="<?php echo $is_edit ? $model_info->fecha_renovacion : ''; ?>">
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Fecha de vencimiento</label>
                <input type="date" class="form-control" name="fecha_vencimiento" value="<?php echo $is_edit ? $model_info->fecha_vencimiento : ''; ?>">
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Aviso previo (días)</label>
                <input type="number" class="form-control" name="dias_aviso_previo" value="<?php echo $is_edit ? $model_info->dias_aviso_previo : 30; ?>">
            </div>
        </div>
        <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label">Proveedor</label>
                <input type="text" class="form-control" name="proveedor" value="<?php echo $is_edit ? $model_info->proveedor : ''; ?>">
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Coste (para la agencia)</label>
                <div class="input-group"><input type="number" class="form-control" name="coste" step="0.01" value="<?php echo $is_edit ? $model_info->coste : ''; ?>"><span class="input-group-text">€</span></div>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Precio de venta</label>
                <div class="input-group"><input type="number" class="form-control" name="precio_venta" step="0.01" value="<?php echo $is_edit ? $model_info->precio_venta : ''; ?>"><span class="input-group-text">€</span></div>
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label">Estado</label>
            <select class="form-select" name="estado">
                <?php $estats = ['pendiente','avisado','renovado','facturado','cobrado','cancelado'];
                $ce = $is_edit ? $model_info->estado : 'pendiente';
                foreach ($estats as $e): ?>
                    <option value="<?php echo $e; ?>" <?php echo $ce==$e?'selected':''; ?>><?php echo ucfirst($e); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">Observaciones</label>
            <textarea class="form-control" name="observaciones" rows="2"><?php echo $is_edit ? $model_info->observaciones : ''; ?></textarea>
        </div>
    </form>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
    <button type="button" class="btn btn-primary" id="btn-guardar-renovacion">
        <i data-feather="save" class="icon-14"></i> Guardar
    </button>
</div>
<script>
$('#btn-guardar-renovacion').on('click', function(){
    var form = $('#form-renovacion');
    if (!form[0].checkValidity()){ form[0].reportValidity(); return; }
    $.post('<?php echo get_uri("facturacion/save_renovacion"); ?>', form.serialize(), function(res){
        if (res.success){ appAlert.success('Renovación guardada.'); $('#modal-container').modal('hide'); location.reload(); }
    }, 'json');
});
</script>
