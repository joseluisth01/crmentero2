<?php echo form_open(get_uri("proposals/send_proposal"), array("id" => "send-proposal-form", "class" => "general-form", "role" => "form")); ?>
<div class="modal-body clearfix">
    <div class="container-fluid">
        <input type="hidden" name="id" value="<?php echo $proposal_info->id; ?>" />

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
        <div class="form-group">
            <div class="row">
                <label for="proposal_cc" class=" col-md-3">CC</label>
                <div class="col-md-9">
                    <?php
                    echo form_input(array(
                        "id" => "proposal_cc",
                        "name" => "proposal_cc",
                        "value" => "",
                        "class" => "form-control",
                        "placeholder" => "CC"
                    ));
                    ?>
                </div>
            </div>
        </div>
        <div class="form-group">
            <div class="row">
                <label for="proposal_bcc" class=" col-md-3">BCC</label>
                <div class="col-md-9">
                    <?php
                    echo form_input(array(
                        "id" => "proposal_bcc",
                        "name" => "proposal_bcc",
                        "value" => "",
                        "class" => "form-control",
                        "placeholder" => "BCC"
                    ));
                    ?>
                </div>
            </div>
        </div>

        <!-- ✅ INFORMACIÓN: Email se enviará con diseño Tictac -->
        <div class="alert alert-info" style="margin-top: 20px;">
            <strong>ℹ️ Información</strong><br>
            El email se enviará con el diseño corporativo de Tictac Comunicación y el PDF personalizado adjunto.
        </div>

        <?php if ($has_pdf_access) { ?>
            <div class="form-group ml15">
                <?php
                echo form_checkbox("attach_pdf", "1", true, "id='attach_pdf' class='form-check-input'");
                ?>
                <label for="attach_pdf"><?php echo app_lang('attach_pdf'); ?> (Diseño Tictac)</label>
            </div>
        <?php } ?>

    </div>
</div>


<div class="modal-footer">
    <button type="button" class="btn btn-default" data-bs-dismiss="modal"><span data-feather="x" class="icon-16"></span> <?php echo app_lang('close'); ?></button>
    <button type="submit" class="btn btn-primary"><span data-feather="send" class="icon-16"></span> <?php echo app_lang('send'); ?></button>
</div>
<?php echo form_close(); ?>

<script type="text/javascript">
    $(document).ready(function () {

        $('#send-proposal-form .select2').select2();
        $("#send-proposal-form").appForm({
            onSuccess: function (result) {
                if (result.success) {
                    appAlert.success(result.message, {duration: 10000});
                } else {
                    appAlert.error(result.message);
                }
            }
        });
    });
</script>