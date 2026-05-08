<div class="details-view-top-button clearfix">
    <?php echo view("includes/back_button", ['button_url' => get_uri("facturacion/facturas"), 'button_text' => 'Facturas', 'extra_class' => 'float-start dark']); ?>
</div>

<div class="clearfix page-content xs-full-width">
    <div class="container-fluid">
        <!-- CABECERA -->
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center" style="background:linear-gradient(135deg,#d72173,#a01858);color:#fff">
                        <div>
                            <h4 class="mb-0"><?php echo $factura->numero_factura; ?></h4>
                            <small><?php echo $meses_nombres[$factura->mes]; ?> <?php echo $factura->anno; ?></small>
                        </div>
                        <div class="text-end">
                            <div class="fs-3 fw-bold"><?php echo number_format($factura->total, 2, ',', '.'); ?> €</div>
                            <small>IVA incluido</small>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <strong>Cliente:</strong><br>
                                <a href="<?php echo get_uri("facturacion/cliente_view/$factura->cliente_id"); ?>"><?php echo $factura->cliente_nombre; ?></a><br>
                                <small class="text-muted"><?php echo $factura->cif_nif; ?></small>
                            </div>
                            <div class="col-md-6">
                                <strong>Emisión:</strong> <?php echo $factura->fecha_emision; ?><br>
                                <strong>Vencimiento:</strong>
                                <?php
                                $vencido = $factura->fecha_vencimiento && $factura->fecha_vencimiento < date('Y-m-d') && !in_array($factura->estado_cobro, ['cobrado','no_procede']);
                                ?>
                                <span <?php echo $vencido ? 'class="text-danger fw-bold"' : ''; ?>>
                                    <?php echo $factura->fecha_vencimiento ?: '-'; ?>
                                    <?php if ($vencido): ?> <i data-feather="alert-triangle" class="icon-14"></i><?php endif; ?>
                                </span><br>
                                <strong>Forma de pago:</strong> <?php echo ucfirst($factura->forma_pago); ?>
                            </div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-md-3">
                                <label class="small text-muted">Estado factura</label><br>
                                <span id="badge-estado-factura">
                                <?php
                                $ef_map = ['borrador'=>'secondary','emitida'=>'primary','enviada'=>'info','pagada'=>'success','cancelada'=>'danger','rectificada'=>'warning'];
                                $ef_col = $ef_map[$factura->estado_factura] ?? 'secondary';
                                echo '<span class="badge bg-'.$ef_col.'">'.ucfirst($factura->estado_factura).'</span>';
                                ?>
                                </span>
                            </div>
                            <div class="col-md-3">
                                <label class="small text-muted">Tipo de factura</label><br>
                                <select id="sel-tipo-factura" class="form-select form-select-sm" style="max-width:160px"
                                    onchange="cambiarEstadoCobro(<?php echo $factura->id; ?>, null, this.value)">
                                    <option value="">— Tipo —</option>
                                    <option value="mensual" <?php echo ($factura->observaciones_internas && strpos($factura->observaciones_internas,'tipo:mensual')!==false)?'selected':''; ?>>Mensual</option>
                                    <option value="trimestral">Trimestral</option>
                                    <option value="semestral">Semestral</option>
                                    <option value="anual">Anual</option>
                                    <option value="unico">Pago único</option>
                                    <option value="puntual">Trabajo puntual</option>
                                    <option value="kit_digital">Kit Digital</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="small text-muted">Estado cobro</label><br>
                                <select id="sel-estado-cobro-view" class="form-select form-select-sm" style="max-width:170px"
                                    onchange="cambiarEstadoCobro(<?php echo $factura->id; ?>, this.value, null)">
                                    <option value="pendiente" <?php echo $factura->estado_cobro=='pendiente'?'selected':''; ?>>⏳ Pendiente</option>
                                    <option value="cobrado" <?php echo $factura->estado_cobro=='cobrado'?'selected':''; ?>>✅ Pagado OK</option>
                                    <option value="rechazado" <?php echo $factura->estado_cobro=='rechazado'?'selected':''; ?>>🔴 Rechazado</option>
                                    <option value="vencido" <?php echo $factura->estado_cobro=='vencido'?'selected':''; ?>>🔴 Retrasado</option>
                                    <option value="parcialmente_cobrado" <?php echo $factura->estado_cobro=='parcialmente_cobrado'?'selected':''; ?>>🔶 Pago parcial</option>
                                    <option value="no_procede" <?php echo $factura->estado_cobro=='no_procede'?'selected':''; ?>>— No procede</option>
                                </select>
                                <span id="badge-estado-cobro" style="display:none">
                                <?php
                                $ec_map = ['pendiente'=>'warning','cobrado'=>'success','rechazado'=>'danger','parcialmente_cobrado'=>'info','vencido'=>'danger','no_procede'=>'secondary'];
                                $ec_col = $ec_map[$factura->estado_cobro] ?? 'secondary';
                                echo '<span class="badge bg-'.$ec_col.'">'.ucfirst(str_replace('_',' ',$factura->estado_cobro)).'</span>';
                                ?>
                                </span>
                            </div>
                            <div class="col-md-3">
                                <label class="small text-muted">Cobrado</label><br>
                                <span class="text-success fw-bold"><?php echo number_format($factura->importe_cobrado, 2, ',', '.'); ?> €</span>
                            </div>
                            <div class="col-md-3">
                                <label class="small text-muted">Pendiente</label><br>
                                <span class="<?php echo $factura->total - $factura->importe_cobrado > 0 ? 'text-warning' : 'text-success'; ?> fw-bold">
                                    <?php echo number_format($factura->total - $factura->importe_cobrado, 2, ',', '.'); ?> €
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-white d-flex gap-2">
                        <?php if ($login_user->is_admin): ?>
                            <?php echo modal_anchor(get_uri("facturacion/factura_modal_form"), '<i data-feather="edit-2" class="icon-14"></i> Editar cabecera', ['class' => 'btn btn-outline-secondary btn-sm', 'title' => 'Editar factura', 'data-post-id' => $factura->id]); ?>
                        <?php endif; ?>
                        <button class="btn btn-success btn-sm" onclick="marcarCobrada(<?php echo $factura->id; ?>)">
                            <i data-feather="check-circle" class="icon-14"></i> Marcar cobrada
                        </button>
                        <button class="btn btn-danger btn-sm" onclick="marcarRechazada(<?php echo $factura->id; ?>)">
                            <i data-feather="x-circle" class="icon-14"></i> Marcar rechazada
                        </button>
                    </div>
                </div>
            </div>

            <!-- RESUMEN IMPORTES -->
            <div class="col-md-4">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-white fw-bold">Resumen económico</div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <tr><td>Subtotal</td><td class="text-end"><?php echo number_format($factura->subtotal, 2, ',', '.'); ?> €</td></tr>
                            <tr><td>IVA</td><td class="text-end"><?php echo number_format($factura->iva_total, 2, ',', '.'); ?> €</td></tr>
                            <tr class="fw-bold border-top"><td>TOTAL</td><td class="text-end fs-5"><?php echo number_format($factura->total, 2, ',', '.'); ?> €</td></tr>
                            <tr class="text-success"><td>Cobrado</td><td class="text-end"><?php echo number_format($factura->importe_cobrado, 2, ',', '.'); ?> €</td></tr>
                            <tr class="text-warning fw-bold"><td>Pendiente</td><td class="text-end"><?php echo number_format($factura->total - $factura->importe_cobrado, 2, ',', '.'); ?> €</td></tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- LÍNEAS DE FACTURA -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-bold"><i data-feather="list" class="icon-14"></i> Líneas de factura</span>
                <button class="btn btn-primary btn-sm" onclick="mostrarFormLinea()">
                    <i data-feather="plus" class="icon-14"></i> Añadir línea
                </button>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm table-hover mb-0" id="tabla-lineas">
                    <thead class="table-light">
                        <tr>
                            <th>Descripción</th>
                            <th>Tipo</th>
                            <th class="text-center">Cantidad</th>
                            <th class="text-end">Precio unit.</th>
                            <th class="text-center">IVA %</th>
                            <th class="text-end">Subtotal</th>
                            <th class="text-end">IVA</th>
                            <th class="text-end">Total</th>
                            <th width="80"></th>
                        </tr>
                    </thead>
                    <tbody id="lineas-body">
                        <?php foreach ($lineas as $linea): ?>
                        <tr id="linea-<?php echo $linea->id; ?>">
                            <td><?php echo $linea->descripcion; ?></td>
                            <td><span class="badge bg-secondary"><?php echo $linea->tipo_linea; ?></span></td>
                            <td class="text-center"><?php echo number_format($linea->cantidad, 2, ',', '.'); ?></td>
                            <td class="text-end"><?php echo number_format($linea->precio_unitario, 2, ',', '.'); ?> €</td>
                            <td class="text-center"><?php echo $linea->iva_porcentaje; ?>%</td>
                            <td class="text-end"><?php echo number_format($linea->subtotal, 2, ',', '.'); ?> €</td>
                            <td class="text-end"><?php echo number_format($linea->iva_importe, 2, ',', '.'); ?> €</td>
                            <td class="text-end fw-bold"><?php echo number_format($linea->total, 2, ',', '.'); ?> €</td>
                            <td class="text-center">
                                <button class="btn btn-outline-danger btn-sm" onclick="eliminarLinea(<?php echo $linea->id; ?>, <?php echo $linea->factura_id; ?>)">
                                    <i data-feather="trash-2" class="icon-12"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="table-light fw-bold">
                            <td colspan="5" class="text-end">TOTALES:</td>
                            <td class="text-end" id="footer-subtotal"><?php echo number_format($factura->subtotal, 2, ',', '.'); ?> €</td>
                            <td class="text-end" id="footer-iva"><?php echo number_format($factura->iva_total, 2, ',', '.'); ?> €</td>
                            <td class="text-end fs-6" id="footer-total"><?php echo number_format($factura->total, 2, ',', '.'); ?> €</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- FORM AÑADIR LÍNEA (oculto por defecto) -->
            <div class="card-footer bg-light" id="form-linea-wrapper" style="display:none">
                <form id="form-linea">
                    <input type="hidden" name="factura_id" value="<?php echo $factura->id; ?>">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label small mb-1">Servicio del cliente (opcional)</label>
                            <select class="form-select form-select-sm" name="cliente_servicio_id" id="sel-servicio-linea">
                                <option value="">— Manual —</option>
                                <?php foreach ($cliente_servicios as $cs): ?>
                                    <option value="<?php echo $cs->id; ?>"
                                        data-concepto="<?php echo htmlspecialchars($cs->concepto); ?>"
                                        data-importe="<?php echo $cs->importe; ?>"
                                        data-iva="<?php echo $cs->iva_porcentaje; ?>"
                                        data-tipo="<?php echo $cs->es_recurrente ? 'recurrente' : 'puntual'; ?>">
                                        <?php echo $cs->concepto; ?> (<?php echo number_format($cs->importe,2,',','.'); ?> €)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small mb-1">Descripción *</label>
                            <input type="text" class="form-control form-control-sm" name="descripcion" id="inp-descripcion-linea" required>
                        </div>
                        <div class="col-md-1">
                            <label class="form-label small mb-1">Tipo</label>
                            <select class="form-select form-select-sm" name="tipo_linea" id="sel-tipo-linea">
                                <option value="recurrente">Recurrente</option>
                                <option value="puntual">Puntual</option>
                                <option value="renovacion">Renovación</option>
                                <option value="kit_digital">Kit Digital</option>
                                <option value="publicidad">Publicidad</option>
                                <option value="descuento">Descuento</option>
                                <option value="ajuste">Ajuste</option>
                                <option value="otro">Otro</option>
                            </select>
                        </div>
                        <div class="col-md-1">
                            <label class="form-label small mb-1">Cant.</label>
                            <input type="number" class="form-control form-control-sm" name="cantidad" value="1" min="0" step="0.01" id="inp-cantidad-linea">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small mb-1">Precio unit. *</label>
                            <input type="number" class="form-control form-control-sm" name="precio_unitario" step="0.01" id="inp-precio-linea" required>
                        </div>
                        <div class="col-md-1">
                            <label class="form-label small mb-1">IVA %</label>
                            <input type="number" class="form-control form-control-sm" name="iva_porcentaje" value="21" step="0.01" id="inp-iva-linea">
                        </div>
                        <div class="col-md-1">
                            <button type="button" class="btn btn-success btn-sm w-100" onclick="guardarLinea()">
                                <i data-feather="plus" class="icon-14"></i> Añadir
                            </button>
                        </div>
                        <div class="col-md-1">
                            <button type="button" class="btn btn-secondary btn-sm w-100" onclick="ocultarFormLinea()">
                                <i data-feather="x" class="icon-14"></i>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- PAGOS -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white d-flex justify-content-between">
                <span class="fw-bold"><i data-feather="credit-card" class="icon-14"></i> Registro de pagos</span>
                <button class="btn btn-success btn-sm" onclick="mostrarFormPago()">
                    <i data-feather="plus" class="icon-14"></i> Registrar pago
                </button>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0" id="tabla-pagos">
                    <thead class="table-light">
                        <tr><th>Fecha</th><th>Importe</th><th>Método</th><th>Estado</th><th>Referencia</th><th>Observaciones</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pagos as $pago): ?>
                        <tr>
                            <td><?php echo $pago->fecha_pago; ?></td>
                            <td class="fw-bold"><?php echo number_format($pago->importe, 2, ',', '.'); ?> €</td>
                            <td><?php echo ucfirst($pago->metodo_pago); ?></td>
                            <td>
                                <?php
                                $p_map = ['confirmado'=>'success','pendiente'=>'warning','rechazado'=>'danger','devuelto'=>'secondary'];
                                $p_col = $p_map[$pago->estado] ?? 'secondary';
                                echo '<span class="badge bg-'.$p_col.'">'.ucfirst($pago->estado).'</span>';
                                ?>
                            </td>
                            <td><?php echo $pago->referencia ?: '-'; ?></td>
                            <td><?php echo $pago->observaciones ?: '-'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <!-- FORM PAGO (oculto) -->
            <div class="card-footer bg-light" id="form-pago-wrapper" style="display:none">
                <form id="form-pago">
                    <input type="hidden" name="factura_id" value="<?php echo $factura->id; ?>">
                    <input type="hidden" name="cliente_id" value="<?php echo $factura->cliente_id; ?>">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-2">
                            <label class="form-label small mb-1">Importe *</label>
                            <input type="number" class="form-control form-control-sm" name="importe" step="0.01" value="<?php echo $factura->total - $factura->importe_cobrado; ?>" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small mb-1">Fecha *</label>
                            <input type="date" class="form-control form-control-sm" name="fecha_pago" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small mb-1">Método</label>
                            <select class="form-select form-select-sm" name="metodo_pago">
                                <option value="remesa" <?php echo $factura->forma_pago=='remesa'?'selected':''; ?>>Remesa</option>
                                <option value="transferencia" <?php echo $factura->forma_pago=='transferencia'?'selected':''; ?>>Transferencia</option>
                                <option value="efectivo">Efectivo</option>
                                <option value="tarjeta">Tarjeta</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small mb-1">Estado</label>
                            <select class="form-select form-select-sm" name="estado">
                                <option value="confirmado">Confirmado</option>
                                <option value="rechazado">Rechazado</option>
                                <option value="devuelto">Devuelto</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small mb-1">Referencia</label>
                            <input type="text" class="form-control form-control-sm" name="referencia">
                        </div>
                        <div class="col-md-1">
                            <button type="button" class="btn btn-success btn-sm w-100" onclick="guardarPago()">
                                <i data-feather="save" class="icon-14"></i>
                            </button>
                        </div>
                        <div class="col-md-1">
                            <button type="button" class="btn btn-secondary btn-sm w-100" onclick="$('#form-pago-wrapper').hide()">
                                <i data-feather="x" class="icon-14"></i>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- OBSERVACIONES -->
        <?php if ($factura->observaciones_internas || $factura->observaciones_cliente): ?>
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white fw-bold">Observaciones</div>
            <div class="card-body">
                <?php if ($factura->observaciones_internas): ?>
                    <p><strong>Internas:</strong><br><?php echo nl2br($factura->observaciones_internas); ?></p>
                <?php endif; ?>
                <?php if ($factura->observaciones_cliente): ?>
                    <p><strong>Para el cliente:</strong><br><?php echo nl2br($factura->observaciones_cliente); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function mostrarFormLinea(){ $('#form-linea-wrapper').slideDown(); }
function ocultarFormLinea(){ $('#form-linea-wrapper').slideUp(); }
function mostrarFormPago() { $('#form-pago-wrapper').slideDown(); }

// Auto-rellenar al seleccionar servicio
$('#sel-servicio-linea').on('change', function(){
    var opt = $(this).find(':selected');
    if (opt.val()) {
        $('#inp-descripcion-linea').val(opt.data('concepto'));
        $('#inp-precio-linea').val(opt.data('importe'));
        $('#inp-iva-linea').val(opt.data('iva'));
        $('#sel-tipo-linea').val(opt.data('tipo'));
    }
});

function guardarLinea(){
    var form = $('#form-linea');
    if (!form[0].checkValidity()) { form[0].reportValidity(); return; }
    $.post('<?php echo get_uri("facturacion/save_linea"); ?>', form.serialize(), function(res){
        if (res.success) {
            actualizarTotalesFactura(res.factura);
            location.reload();
        }
    }, 'json');
}

function eliminarLinea(id, facturaId){
    if (!confirm('¿Eliminar esta línea?')) return;
    $.post('<?php echo get_uri("facturacion/delete_linea"); ?>', {id:id, factura_id:facturaId}, function(res){
        if (res.success) {
            $('#linea-'+id).fadeOut(300, function(){ $(this).remove(); });
            actualizarTotalesFactura(res.factura);
        }
    }, 'json');
}

function actualizarTotalesFactura(f){
    $('#footer-subtotal').text(formatMoney(f.subtotal) + ' €');
    $('#footer-iva').text(formatMoney(f.iva_total) + ' €');
    $('#footer-total').text(formatMoney(f.total) + ' €');
}

function guardarPago(){
    var form = $('#form-pago');
    if (!form[0].checkValidity()) { form[0].reportValidity(); return; }
    $.post('<?php echo get_uri("facturacion/save_pago"); ?>', form.serialize(), function(res){
        if (res.success) { appAlert.success('Pago registrado.'); location.reload(); }
    }, 'json');
}

function marcarCobrada(id){
    if (!confirm('¿Marcar como completamente cobrada?')) return;
    $.post('<?php echo get_uri("facturacion/marcar_cobrada"); ?>', {factura_id:id}, function(res){
        if (res.success) { appAlert.success('Factura marcada como cobrada.'); location.reload(); }
    }, 'json');
}

function marcarRechazada(id){
    var motivo = prompt('Motivo del rechazo (opcional):');
    if (motivo === null) return;
    $.post('<?php echo get_uri("facturacion/marcar_rechazada"); ?>', {factura_id:id, motivo:motivo}, function(res){
        if (res.success) { appAlert.success('Factura marcada como rechazada.'); location.reload(); }
    }, 'json');
}

function formatMoney(n){ return parseFloat(n).toFixed(2).replace('.',',').replace(/\B(?=(\d{3})+(?!\d))/g,'.'); }

function cambiarEstadoCobro(facturaId, estadoCobro, tipoPago) {
    if (estadoCobro) {
        $.post('<?php echo get_uri("facturacion/cambiar_estado_cobro"); ?>', 
            {factura_id: facturaId, estado_cobro: estadoCobro}, 
            function(res){
                if (res.success) {
                    var msgs = {
                        'cobrado': '✅ Factura marcada como Pagado OK',
                        'rechazado': '🔴 Rechazado — email de alerta enviado al equipo',
                        'vencido': '🔴 Retrasado — email de alerta enviado al equipo',
                        'pendiente': '⏳ Pendiente — email de aviso enviado al equipo',
                    };
                    appAlert.success(msgs[estadoCobro] || 'Estado actualizado');
                }
            }, 'json');
    }
}
</script>