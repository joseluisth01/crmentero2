<?php echo form_open(get_uri("contracts/send_to_lleida"), array("id" => "lleida-form", "class" => "general-form", "role" => "form")); ?>
<div class="modal-body clearfix">
    <div class="container-fluid">
        <input type="hidden" name="contract_id" value="<?php echo $contract_info->id; ?>">

        <div style="background:#fff5f9;border-left:3px solid #d72173;padding:14px 18px;border-radius:0 6px 6px 0;margin-bottom:18px;">
            <strong style="color:#d72173;">Contrato <?php echo $contract_ref; ?></strong><br>
            <small style="color:#666;">Cliente: <?php echo htmlspecialchars($client_info->company_name ?? '-'); ?></small>
        </div>

        <div class="form-group">
            <div class="row">
                <label class="col-md-3">Nombre firmante</label>
                <div class="col-md-9">
                    <input type="text" name="signer_name" class="form-control"
                        value="<?php echo htmlspecialchars(($contact->first_name ?? '') . ' ' . ($contact->last_name ?? '')); ?>"
                        placeholder="Nombre completo del firmante" required>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label class="col-md-3">Email firmante</label>
                <div class="col-md-9">
                    <input type="email" name="signer_email" class="form-control"
                        value="<?php echo htmlspecialchars($contact->email ?? ''); ?>"
                        placeholder="email@empresa.com" required>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label class="col-md-3">Móvil firmante</label>
                <div class="col-md-9">
                    <input type="tel" name="phone" id="lleida-phone" class="form-control"
                        value="<?php echo htmlspecialchars($contact->phone ?? ''); ?>"
                        placeholder="600000000" required>
                    <small class="text-muted">Debe ser un número de móvil español (empieza por 6 o 7). El cliente recibirá un SMS con el enlace para firmar.</small>
                    <div id="phone-warning" style="display:none;color:#c0392b;margin-top:6px;font-size:12px;">
                        ⚠️ Este número parece ser un fijo. Click&Sign requiere un número de móvil.
                    </div>
                </div>
            </div>
        </div>

        <div style="background:#f8f9fa;border-radius:6px;padding:12px;font-size:12px;color:#666;margin-top:10px;">
            <strong>¿Qué ocurrirá?</strong><br>
            1. Se generará el PDF del contrato<br>
            2. Se enviará a Click&Sign<br>
            3. El cliente recibirá un SMS con un enlace para firmar<br>
            4. Al firmar, el contrato se marcará automáticamente como aceptado
        </div>
    </div>
</div>

<div class="modal-footer">
    <button type="button" class="btn btn-default" data-bs-dismiss="modal">
        <span data-feather="x" class="icon-16"></span> Cancelar
    </button>
    <button type="submit" id="lleida-submit" class="btn btn-primary" style="background:#d72173;border-color:#d72173;">
        <span data-feather="smartphone" class="icon-16"></span> Enviar SMS para firma
    </button>
</div>
<?php echo form_close(); ?>

<script>
$(document).ready(function () {
    // Advertencia si parece fijo
    $("#lleida-phone").on("input change", function () {
        var val = $(this).val().replace(/\s+/g, '').replace(/^\+34/, '').replace(/^0034/, '');
        if (val.length > 0 && !/^[67]/.test(val)) {
            $("#phone-warning").show();
            $("#lleida-submit").prop("disabled", true);
        } else {
            $("#phone-warning").hide();
            $("#lleida-submit").prop("disabled", false);
        }
    }).trigger("change");

    $("#lleida-form").appForm({
        onSuccess: function (result) {
            appAlert.success(result.message || "Enviado correctamente", { duration: 5000 });
            setTimeout(function () { location.reload(); }, 2000);
        }
    });
});
</script>