<?php echo form_open(get_uri("contracts/send_contract"), array("id" => "send-contract-form", "class" => "general-form", "role" => "form")); ?>
<div class="modal-body clearfix">
    <div class="container-fluid">
        <input type="hidden" name="id" value="<?php echo $contract_info->id; ?>" />

        <div class="form-group">
            <div class="row">
                <label for="contact_id" class=" col-md-3"><?php echo app_lang('to'); ?></label>
                <div class=" col-md-9">
                    <?php
                    echo form_dropdown("contact_id", $contacts_dropdown, array(), "class='select2 validate-hidden' id='contact_id' data-rule-required='true', data-msg-required='" . app_lang('field_required') . "'");
                    ?>
                </div>
            </div>
        </div>

        <!-- Teléfono del contacto para firma SMS -->
        <div class="form-group" id="phone-field-row">
            <div class="row">
                <label for="contact_phone" class="col-md-3">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#d72173" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom:-2px;"><rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
                    Móvil (firma SMS)
                </label>
                <div class="col-md-9">
                    <input type="tel" id="contact_phone" name="contact_phone" class="form-control"
                        placeholder="600 000 000 (para firma por SMS)">
                    <small class="text-muted">El cliente recibirá el contrato por email y podrá firmarlo por SMS desde el enlace.</small>
                    <div id="phone-fijo-warning" style="display:none;color:#c0392b;font-size:12px;margin-top:4px;">
                        ⚠️ Parece un número fijo. La firma SMS requiere móvil (empieza por 6 o 7).
                    </div>
                </div>
            </div>
        </div>

        <!-- Proyecto Tipo (opcional, múltiple) -->
        <div class="form-group">
            <div class="row">
                <label class="col-md-3" style="padding-top:6px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#d72173" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom:-2px;"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 9h6M9 12h6M9 15h4"/></svg>
                    Proyectos Tipo
                    <small class="text-muted d-block" style="font-size:10px;font-weight:400;">opcional, múltiple</small>
                </label>
                <div class="col-md-9">
                    <select name="proyecto_tipo_ids[]" id="proyecto_tipo_ids" class="select2-multi" multiple="multiple" style="width:100%">
                        <?php foreach (($proyectos_tipo ?? []) as $pt): ?>
                        <option value="<?php echo $pt->id; ?>"><?php echo htmlspecialchars($pt->title); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">Se clonará un proyecto por cada plantilla seleccionada al aceptar el contrato.</small>
                </div>
            </div>
        </div>

        <!-- Nombre personalizado por cada proyecto tipo seleccionado -->
        <div class="form-group" id="proyecto-nombre-row" style="display:none;">
            <div class="row">
                <label class="col-md-3">Nombres de proyectos</label>
                <div class="col-md-9" id="proyecto-nombres-container">
                    <!-- Se genera dinámicamente por JS -->
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="contract_cc" class=" col-md-3">CC</label>
                <div class="col-md-9">
                    <?php echo form_input(array("id" => "contract_cc", "name" => "contract_cc", "value" => "", "class" => "form-control", "placeholder" => "CC")); ?>
                </div>
            </div>
        </div>

        <!-- subject y message ocultos — se envían pero no se muestran -->
        <input type="hidden" name="contract_bcc" value="">
        <?php
        $custom_subject = 'Contrato de servicios — ' . ($contract_info->company_name ?? $contract_info->title ?? '');
        ?>
        <input type="hidden" name="subject" value="<?php echo htmlspecialchars($custom_subject); ?>">
        <textarea name="message" style="display:none;" data-encode_ajax_post_data="1"><?php echo process_images_from_content($message, false); ?></textarea>

        <?php if ($has_pdf_access) { ?>
            <div class="form-group ml15">
                <?php echo form_checkbox("attach_pdf", "1", true, "id='attach_pdf' class='form-check-input'"); ?>
                <label for="attach_pdf"><?php echo app_lang('attach_pdf') . ' ' . anchor(get_uri("contracts/download_pdf/" . $contract_info->id . "/download"), get_hyphenated_string(get_contract_id($contract_info->id)) . ".pdf", array("target" => "_blank", "id" => "attachment-url")); ?></label>
            </div>
        <?php } ?>

    </div>
</div>

<div class="modal-footer">
    <button type="button" class="btn btn-default" data-bs-dismiss="modal"><span data-feather="x" class="icon-16"></span> <?php echo app_lang('close'); ?></button>
    <button type="submit" class="btn btn-primary"><span data-feather="send" class="icon-16"></span> <?php echo app_lang('send'); ?></button>
</div>
<?php echo form_close(); ?>

<script>
// Datos de teléfono por contacto
var contactPhones = <?php
    $phones = array();
    foreach ($contacts_dropdown as $cid => $cname) {
        // Buscar teléfono del contacto
        $phones[$cid] = '';
    }
    // Pasamos los contactos con teléfono desde el controlador
    echo json_encode($contact_phones ?? array());
?>;

$(document).ready(function() {
    $('#send-contract-form .select2').select2();
    $('#proyecto_tipo_ids').select2({
        placeholder: '— Sin proyecto tipo —',
        allowClear: true,
        width: '100%'
    });

    function updatePhone(contactId) {
        var phone = contactPhones[contactId] || '';
        $('#contact_phone').val(phone);
        checkPhone(phone);
    }

    function checkPhone(val) {
        var clean = val.replace(/\s+/g,'').replace(/^\+34/,'').replace(/^0034/,'');
        if (clean.length > 0 && !/^[67]/.test(clean)) {
            $('#phone-fijo-warning').show();
        } else {
            $('#phone-fijo-warning').hide();
        }
    }

    $('#contact_id').select2().on('change', function() {
        var contactId = $(this).val();
        updatePhone(contactId);

        if (contactId) {
            appLoader.show();
            appAjaxRequest({
                url: "<?php echo get_uri('contracts/get_send_contract_template/' . $contract_info->id) ?>" + "/" + contactId + "/json",
                dataType: "json",
                success: function(result) {
                    if (result.success) {
                        setWYSIWYGEditorHTML("#message", result.message_view);
                        appLoader.hide();
                    }
                }
            });
        }
    });

    $('#contact_phone').on('input', function() { checkPhone($(this).val()); });

    // Proyecto tipo multi-select — un campo de nombre por cada seleccionado
    var proyectoTipoNombres = <?php
        $pt_names = array();
        foreach (($proyectos_tipo ?? []) as $pt) {
            $pt_names[$pt->id] = $pt->title;
        }
        echo json_encode($pt_names);
    ?>;

    function ttLimpiarNombre(titulo) {
        return titulo.replace(/^Proyecto Tipo\s*/i, '').replace(/\[.*?\]/g, '').trim();
    }

    $('#proyecto_tipo_ids').on('change', function() {
        var selected = $(this).val() || [];
        var container = $('#proyecto-nombres-container');
        container.empty();

        if (selected.length === 0) {
            $('#proyecto-nombre-row').slideUp(150);
            return;
        }

        $('#proyecto-nombre-row').slideDown(150);

        selected.forEach(function(id, idx) {
            var nombre = ttLimpiarNombre(proyectoTipoNombres[id] || '');
            var label  = selected.length > 1 ? '<small class="text-muted d-block mb-1">Plantilla: ' + (proyectoTipoNombres[id] || '') + '</small>' : '';
            container.append(
                '<div class="mb-2">' + label +
                '<input type="text" name="proyecto_nombres[' + id + ']" class="form-control" ' +
                'value="' + $('<div>').text(nombre).html() + '" ' +
                'placeholder="Nombre del proyecto nuevo">' +
                '</div>'
            );
        });
    });

    // Inicializar con el primero seleccionado
    updatePhone($('#contact_id').val());

    $("#send-contract-form").appForm({
        onSuccess: function(result) {
            if (result.success) {
                appAlert.success(result.message, { duration: 10000 });
            } else {
                appAlert.error(result.message);
            }
        }
    });
});
</script>