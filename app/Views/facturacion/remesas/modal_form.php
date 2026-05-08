<?php $is_edit = isset($model_info) && $model_info; ?>
<div class="modal-header">
    <h5 class="modal-title"><?php echo $is_edit ? 'Editar remesa' : 'Nueva remesa'; ?></h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
    <form id="form-remesa">
        <input type="hidden" name="id" value="<?php echo $is_edit ? $model_info->id : ''; ?>">
        <div class="mb-3">
            <label class="form-label">Nombre de la remesa *</label>
            <input type="text" class="form-control" name="nombre" required value="<?php echo $is_edit ? $model_info->nombre : 'Remesa ' . date('m/Y'); ?>">
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
            <div class="col-md-4 mb-3">
                <label class="form-label">Fecha generación</label>
                <input type="date" class="form-control" name="fecha_generacion" value="<?php echo $is_edit ? $model_info->fecha_generacion : ''; ?>">
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Fecha envío al banco</label>
                <input type="date" class="form-control" name="fecha_envio" value="<?php echo $is_edit ? $model_info->fecha_envio : ''; ?>">
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Fecha de cobro</label>
                <input type="date" class="form-control" name="fecha_cobro" value="<?php echo $is_edit ? $model_info->fecha_cobro : ''; ?>">
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label">Estado</label>
            <select class="form-select" name="estado">
                <?php $estados = ['pendiente','generada','enviada','cobrada','parcialmente_rechazada','rechazada'];
                $ce = $is_edit ? $model_info->estado : 'pendiente';
                foreach ($estados as $e): ?>
                    <option value="<?php echo $e; ?>" <?php echo $ce==$e?'selected':''; ?>><?php echo ucfirst(str_replace('_',' ',$e)); ?></option>
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
    <button type="button" class="btn btn-primary" id="btn-guardar-remesa">
        <i data-feather="save" class="icon-14"></i> Guardar
    </button>
</div>
<script>
$('#btn-guardar-remesa').on('click', function(){
    var form = $('#form-remesa');
    if (!form[0].checkValidity()){ form[0].reportValidity(); return; }
    $.post('<?php echo get_uri("facturacion/save_remesa"); ?>', form.serialize(), function(res){
        if (res.success){ appAlert.success('Remesa guardada.'); $('#modal-container').modal('hide'); location.reload(); }
    }, 'json');
});
</script>
