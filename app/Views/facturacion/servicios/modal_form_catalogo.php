<?php $is_edit = isset($model_info) && $model_info; ?>
<div class="modal-header">
    <h5 class="modal-title"><?php echo $is_edit ? 'Editar servicio del catálogo' : 'Nuevo servicio en catálogo'; ?></h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
    <form id="form-servicio-catalogo">
        <input type="hidden" name="id" value="<?php echo $is_edit ? $model_info->id : ''; ?>">
        <div class="row">
            <div class="col-md-8 mb-3">
                <label class="form-label">Nombre del servicio *</label>
                <input type="text" class="form-control" name="nombre" required value="<?php echo $is_edit ? $model_info->nombre : ''; ?>">
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Categoría *</label>
                <select class="form-select" name="categoria" required>
                    <?php $cats = ['seo','rrss','web','hosting','dominio','ads','kit_digital','mantenimiento','diseno','consultoria','renovacion','otro'];
                    $cc = $is_edit ? $model_info->categoria : '';
                    foreach ($cats as $c): ?>
                        <option value="<?php echo $c; ?>" <?php echo $cc==$c?'selected':''; ?>><?php echo ucfirst($c); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label">Descripción</label>
            <textarea class="form-control" name="descripcion" rows="2"><?php echo $is_edit ? $model_info->descripcion : ''; ?></textarea>
        </div>
        <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label">Importe base <small class="text-muted">(0 = variable)</small></label>
                <div class="input-group"><input type="number" class="form-control" name="importe_base" step="0.01" value="<?php echo $is_edit ? $model_info->importe_base : '0'; ?>"><span class="input-group-text">€</span></div>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">IVA %</label>
                <div class="input-group"><input type="number" class="form-control" name="iva_porcentaje" step="0.01" value="<?php echo $is_edit ? $model_info->iva_porcentaje : '21'; ?>"><span class="input-group-text">%</span></div>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Periodicidad</label>
                <select class="form-select" name="periodicidad">
                    <?php $periodos = ['mensual','trimestral','semestral','anual','puntual'];
                    $cp = $is_edit ? $model_info->periodicidad : 'mensual';
                    foreach ($periodos as $p): ?>
                        <option value="<?php echo $p; ?>" <?php echo $cp==$p?'selected':''; ?>><?php echo ucfirst($p); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="row">
            <div class="col-md-4 mb-3">
                <div class="form-check mt-3">
                    <input type="checkbox" class="form-check-input" name="es_recurrente" value="1" id="chk-recurrente-cat"
                        <?php echo (!$is_edit || $model_info->es_recurrente) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="chk-recurrente-cat">Recurrente</label>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="form-check mt-3">
                    <input type="checkbox" class="form-check-input" name="genera_comision" value="1" id="chk-comision-cat"
                        <?php echo ($is_edit && $model_info->genera_comision) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="chk-comision-cat">Genera comisión</label>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Estado</label>
                <select class="form-select" name="estado">
                    <option value="activo" <?php echo (!$is_edit || $model_info->estado=='activo') ? 'selected' : ''; ?>>Activo</option>
                    <option value="inactivo" <?php echo ($is_edit && $model_info->estado=='inactivo') ? 'selected' : ''; ?>>Inactivo</option>
                </select>
            </div>
        </div>
    </form>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
    <button type="button" class="btn btn-primary" id="btn-guardar-servicio-cat">
        <i data-feather="save" class="icon-14"></i> Guardar
    </button>
</div>
<script>
$('#btn-guardar-servicio-cat').on('click', function(){
    var form = $('#form-servicio-catalogo');
    if (!form[0].checkValidity()){ form[0].reportValidity(); return; }
    $.post('<?php echo get_uri("facturacion/save_servicio_catalogo"); ?>', form.serialize(), function(res){
        if (res.success){ appAlert.success('Servicio guardado.'); $('#modal-container').modal('hide'); location.reload(); }
    }, 'json');
});
</script>
