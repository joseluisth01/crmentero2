<div class="modal-header">
    <h5 class="modal-title">Forma de pago — <?php echo $cliente->nombre; ?></h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
    <input type="hidden" name="id" value="<?php echo $cliente->id; ?>">
    <div class="mb-3">
        <label class="form-label">Forma de pago habitual</label>
        <select name="forma_pago" class="form-select">
            <option value="transferencia" <?php echo ($cliente->forma_pago=='transferencia')?'selected':''; ?>>↗️ Transferencia</option>
            <option value="remesa" <?php echo ($cliente->forma_pago=='remesa')?'selected':''; ?>>🏦 Remesa</option>
            <option value="efectivo" <?php echo ($cliente->forma_pago=='efectivo')?'selected':''; ?>>💵 Efectivo</option>
        </select>
        <small class="text-muted">Este campo se guarda directamente en el cliente del CRM y se usará por defecto en todas sus facturas.</small>
    </div>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
    <button type="button" class="btn btn-primary" onclick="guardarFormaPago(this)">Guardar</button>
</div>
<script>
function guardarFormaPago(btn) {
    var id = $('[name=id]').val();
    var fp = $('[name=forma_pago]').val();
    $.post('<?php echo get_uri("facturacion/save_forma_pago_cliente"); ?>', {id: id, forma_pago: fp}, function(res) {
        if (res.success) {
            appAlert.success('Forma de pago actualizada');
            closeModal();
            if (typeof tablaClientes !== 'undefined') tablaClientes.ajax.reload();
        }
    }, 'json');
}
</script>