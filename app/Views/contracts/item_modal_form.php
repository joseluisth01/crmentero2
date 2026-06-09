<?php echo form_open(get_uri("contracts/save_item"), array("id" => "contract-item-form", "class" => "general-form", "role" => "form")); ?>
<div class="modal-body clearfix">
    <div class="container-fluid">
        <input type="hidden" name="id" value="<?php echo $model_info->id; ?>" />
        <input type="hidden" id="item_id" name="item_id" value="<?php echo $model_info->item_id; ?>" />
        <input type="hidden" name="contract_id" value="<?php echo $contract_id; ?>" />
        <input type="hidden" name="add_new_item_to_library" value="" id="add_new_item_to_library" />
        <input type="hidden" name="num_periodos" value="0" />

        <div class="form-group">
            <div class="row">
                <label for="contract_item_title" class="col-md-3"><?php echo app_lang('item'); ?></label>
                <div class="col-md-9">
                    <?php echo form_input(array(
                        "id" => "contract_item_title",
                        "name" => "contract_item_title",
                        "value" => $model_info->title,
                        "class" => "form-control validate-hidden",
                        "placeholder" => app_lang('select_or_create_new_item'),
                        "data-rule-required" => true,
                        "data-msg-required" => app_lang("field_required"),
                    )); ?>
                    <a id="contract_item_title_dropdwon_icon" tabindex="-1" href="javascript:void(0);" style="color:#B3B3B3;float:right;padding:5px 7px;margin-top:-35px;font-size:18px;"><span>×</span></a>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="contract_item_description" class="col-md-3"><?php echo app_lang('description'); ?></label>
                <div class="col-md-9">
                    <?php echo form_textarea(array(
                        "id" => "contract_item_description",
                        "name" => "contract_item_description",
                        "value" => $model_info->description ? process_images_from_content($model_info->description, false) : "",
                        "class" => "form-control",
                        "placeholder" => app_lang('description'),
                        "data-rich-text-editor" => true
                    )); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="contract_item_quantity" class="col-md-3"><?php echo app_lang('quantity'); ?></label>
                <div class="col-md-9">
                    <?php echo form_input(array(
                        "id" => "contract_item_quantity",
                        "name" => "contract_item_quantity",
                        "value" => $model_info->quantity ? to_decimal_format($model_info->quantity) : "",
                        "class" => "form-control",
                        "placeholder" => app_lang('quantity'),
                        "data-rule-required" => true,
                        "data-msg-required" => app_lang("field_required"),
                    )); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="contract_unit_type" class="col-md-3"><?php echo app_lang('unit_type'); ?></label>
                <div class="col-md-9">
                    <?php echo form_input(array(
                        "id" => "contract_unit_type",
                        "name" => "contract_unit_type",
                        "value" => $model_info->unit_type,
                        "class" => "form-control",
                        "placeholder" => app_lang('unit_type') . ' (Ex: hours, pc, etc.)'
                    )); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="contract_item_rate" class="col-md-3"><?php echo app_lang('rate'); ?></label>
                <div class="col-md-9">
                    <?php echo form_input(array(
                        "id" => "contract_item_rate",
                        "name" => "contract_item_rate",
                        "value" => $model_info->rate ? to_decimal_format($model_info->rate) : "",
                        "class" => "form-control",
                        "placeholder" => app_lang('rate'),
                        "data-rule-required" => true,
                        "data-msg-required" => app_lang("field_required"),
                    )); ?>
                </div>
            </div>
        </div>

        <!-- Tipo de pago -->
        <hr style="border-color:#f0f0f0;margin:15px 0 10px">
        <div style="background:#fff5f9;border-left:3px solid #d72173;padding:12px 15px;border-radius:4px">
            <div class="row">
                <div class="col-md-12">
                    <label class="form-label small mb-2"><strong>Tipo de pago</strong></label>
                    <div class="d-flex gap-3">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="tipo_pago" id="tipo-mensual" value="mensual"
                                <?php echo (($model_info->tipo_pago ?? 'mensual') === 'mensual') ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="tipo-mensual">
                                📅 <strong>Mensual</strong>
                                <small class="text-muted d-block">Se generará una factura cada mes indefinidamente</small>
                            </label>
                        </div>
                        <div class="form-check ms-4">
                            <input class="form-check-input" type="radio" name="tipo_pago" id="tipo-unico" value="unico"
                                <?php echo (($model_info->tipo_pago ?? '') === 'unico') ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="tipo-unico">
                                1️⃣ <strong>Pago único</strong>
                                <small class="text-muted d-block">Se genera una sola factura al aceptar el contrato</small>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<div class="modal-footer">
    <button type="button" class="btn btn-default" data-bs-dismiss="modal"><span data-feather="x" class="icon-16"></span> <?php echo app_lang('close'); ?></button>
    <button type="submit" class="btn btn-primary"><span data-feather="check-circle" class="icon-16"></span> <?php echo app_lang('save'); ?></button>
</div>
<?php echo form_close(); ?>

<script type="text/javascript">
$(document).ready(function () {
    $("#contract-item-form").appForm({
        onSuccess: function (result) {
            $("#contract-item-table").appTable({newData: result.data, dataId: result.id});
            $("#contract-total-section").html(result.contract_total_view);
        }
    });

    var isUpdate = "<?php echo $model_info->id; ?>";
    if (!isUpdate) {
        applySelect2OnItemTitle();
    }

    $("#contract_item_title_dropdwon_icon").click(function () {
        applySelect2OnItemTitle();
    });
});

function applySelect2OnItemTitle() {
    $("#contract_item_title").select2({
        showSearchBox: true,
        ajax: {
            url: "<?php echo get_uri("contracts/get_contract_item_suggestion"); ?>",
            type: 'POST',
            dataType: 'json',
            quietMillis: 250,
            data: function (term, page) { return {q: term}; },
            results: function (data, page) { return {results: data}; }
        }
    }).change(function (e) {
        if (e.val === "+") {
            $("#contract_item_title").select2("destroy").val("").focus();
            $("#add_new_item_to_library").val(1);
        } else if (e.val) {
            $("#add_new_item_to_library").val("");
            appAjaxRequest({
                url: "<?php echo get_uri("contracts/get_contract_item_info_suggestion"); ?>",
                data: {item_id: e.val},
                cache: false,
                type: 'POST',
                dataType: "json",
                success: function (response) {
                    if (response && response.success) {
                        $("#item_id").val(response.item_info.id);
                        $("#contract_item_title").val(response.item_info.title);
                        $("#contract_item_description").val(response.item_info.description);
                        $("#contract_unit_type").val(response.item_info.unit_type);
                        $("#contract_item_rate").val(response.item_info.rate);
                    }
                }
            });
        }
    });
}
</script>