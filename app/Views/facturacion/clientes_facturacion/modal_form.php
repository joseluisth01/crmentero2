<?php
$is_edit = isset($model_info) && $model_info;
$title = $is_edit ? 'Editar cliente de facturación' : 'Nuevo cliente de facturación';
?>
<div class="modal-header">
    <h5 class="modal-title"><?php echo $title; ?></h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
    <form id="form-cliente-fac">
        <input type="hidden" name="id" value="<?php echo $is_edit ? $model_info->id : ''; ?>">

        <div class="mb-3">
            <label class="form-label fw-bold">Cliente del CRM *</label>
            <select class="form-select" name="crm_client_id" id="sel-crm-cliente">
                <option value="">— Selecciona un cliente del CRM —</option>
                <?php foreach ($crm_clients_dropdown as $cc): ?>
                    <option value="<?php echo $cc->id; ?>"
                        <?php echo ($is_edit && $model_info->crm_client_id == $cc->id) ? 'selected' : ''; ?>>
                        <?php echo $cc->company_name; ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="form-text text-info">Al seleccionar un cliente se autorellenan sus datos automáticamente.</div>
        </div>

        <div id="crm-loading" style="display:none" class="text-center py-2 text-muted">
            <div class="spinner-border spinner-border-sm me-2"></div> Cargando datos...
        </div>

        <div id="bloque-datos-cliente" <?php echo (!$is_edit) ? 'style="opacity:0.4;pointer-events:none"' : ''; ?>>
            <hr class="my-3">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Nombre del cliente *</label>
                    <input type="text" class="form-control" name="nombre" required value="<?php echo $is_edit ? $model_info->nombre : ''; ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Nombre fiscal</label>
                    <input type="text" class="form-control" name="nombre_fiscal" value="<?php echo $is_edit ? $model_info->nombre_fiscal : ''; ?>">
                </div>
            </div>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">CIF/NIF</label>
                    <input type="text" class="form-control" name="cif_nif" value="<?php echo $is_edit ? $model_info->cif_nif : ''; ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Teléfono</label>
                    <input type="text" class="form-control" name="telefono" value="<?php echo $is_edit ? $model_info->telefono : ''; ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Email de facturación</label>
                    <input type="email" class="form-control" name="email_facturacion" value="<?php echo $is_edit ? $model_info->email_facturacion : ''; ?>">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Dirección fiscal</label>
                <input type="text" class="form-control" name="direccion" value="<?php echo $is_edit ? $model_info->direccion : ''; ?>">
            </div>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Ciudad</label>
                    <input type="text" class="form-control" name="ciudad" value="<?php echo $is_edit ? $model_info->ciudad : ''; ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Código postal</label>
                    <input type="text" class="form-control" name="codigo_postal" value="<?php echo $is_edit ? $model_info->codigo_postal : ''; ?>">
                </div>
                <div class="col-md-5 mb-3">
                    <label class="form-label">País</label>
                    <input type="text" class="form-control" name="pais" value="<?php echo $is_edit ? ($model_info->pais ?: 'España') : 'España'; ?>">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Persona de contacto</label>
                <input type="text" class="form-control" name="persona_contacto" value="<?php echo $is_edit ? $model_info->persona_contacto : ''; ?>">
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Forma de pago habitual *</label>
                    <select class="form-select" name="forma_pago_default" required>
                        <?php
                        $formas = ['remesa'=>'🏦 Remesa','transferencia'=>'↗️ Transferencia','efectivo'=>'💵 Efectivo','tarjeta'=>'💳 Tarjeta','otro'=>'❓ Otro'];
                        $current_fp = $is_edit ? $model_info->forma_pago_default : 'transferencia';
                        foreach ($formas as $val => $label): ?>
                            <option value="<?php echo $val; ?>" <?php echo $current_fp==$val?'selected':''; ?>><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Estado *</label>
                    <select class="form-select" name="estado" required>
                        <?php
                        $estados = ['activo','inactivo','finalizado','suspendido','cancelado'];
                        $current_est = $is_edit ? $model_info->estado : 'activo';
                        foreach ($estados as $e): ?>
                            <option value="<?php echo $e; ?>" <?php echo $current_est==$e?'selected':''; ?>><?php echo ucfirst($e); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Etiquetas <small class="text-muted">(CSV: SEO,Web,Ads...)</small></label>
                <input type="text" class="form-control" name="etiquetas" value="<?php echo $is_edit ? $model_info->etiquetas : ''; ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Observaciones internas</label>
                <textarea class="form-control" name="observaciones" rows="2"><?php echo $is_edit ? $model_info->observaciones : ''; ?></textarea>
            </div>
        </div>
    </form>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
    <button type="button" class="btn btn-primary" id="btn-guardar-cliente">
        <i data-feather="save" class="icon-14"></i> Guardar
    </button>
</div>
<script>
$(document).ready(function(){
    feather.replace();
    <?php if ($is_edit): ?>
    $('#bloque-datos-cliente').css({opacity:1,'pointer-events':'auto'});
    <?php endif; ?>

    $('#sel-crm-cliente').on('change', function(){
        var id = $(this).val();
        if (!id) {
            $('#bloque-datos-cliente').css({opacity:0.4,'pointer-events':'none'});
            return;
        }
        $('#crm-loading').show();
        $.post('<?php echo get_uri("facturacion/get_crm_client_data"); ?>', {id: id}, function(res){
            $('#crm-loading').hide();
            if (!res || !res.nombre) return;
            $('[name=nombre]').val(res.nombre);
            $('[name=nombre_fiscal]').val(res.nombre_fiscal);
            $('[name=cif_nif]').val(res.cif_nif);
            $('[name=telefono]').val(res.telefono);
            $('[name=email_facturacion]').val(res.email_facturacion);
            $('[name=direccion]').val(res.direccion);
            $('[name=ciudad]').val(res.ciudad);
            $('[name=codigo_postal]').val(res.codigo_postal);
            $('[name=pais]').val(res.pais || 'España');
            $('[name=persona_contacto]').val(res.persona_contacto);
            $('#bloque-datos-cliente').css({opacity:1,'pointer-events':'auto'});
        }, 'json');
    });
});

$('#btn-guardar-cliente').on('click', function(){
    if (!$('#sel-crm-cliente').val()) {
        appAlert.error('Debes seleccionar un cliente del CRM.');
        return;
    }
    var form = $('#form-cliente-fac');
    if (!form[0].checkValidity()) { form[0].reportValidity(); return; }
    $.post('<?php echo get_uri("facturacion/save_cliente"); ?>', form.serialize(), function(res){
        if (res.success) {
            appAlert.success('Cliente guardado correctamente.');
            $('#modal-container').modal('hide');
            if (typeof tablaClientes !== 'undefined' && tablaClientes) {
                tablaClientes.ajax.reload();
            }
        }
    }, 'json');
});
</script>