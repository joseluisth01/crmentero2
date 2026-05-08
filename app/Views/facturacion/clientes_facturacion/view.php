<div class="details-view-top-button clearfix">
    <?php echo view("includes/back_button", ['button_url' => get_uri("facturacion/clientes"), 'button_text' => 'Clientes facturación', 'extra_class' => 'float-start dark']); ?>
</div>

<div class="clearfix page-content xs-full-width">
    <div class="container-fluid">
        <div class="row mb-4">
            <div class="col-12">
                <div class="page-title clearfix no-border no-bg">
                    <h1 class="pl0">
                        <i data-feather="briefcase" class="icon"></i> <?php echo $cliente->nombre; ?>
                        <?php if ($cliente->cif_nif): ?>
                            <small class="text-muted fs-5 ms-2"><?php echo $cliente->cif_nif; ?></small>
                        <?php endif; ?>
                    </h1>
                </div>
            </div>
        </div>

        <ul id="cliente-fac-tabs" data-bs-toggle="ajax-tab" data-do-not-save-state="1" class="nav nav-tabs scrollable-tabs rounded mb20" role="tablist">
            <li><a role="presentation" data-bs-toggle="tab" href="javascript:;" data-bs-target="#cf-resumen">Resumen</a></li>
            <li><a role="presentation" data-bs-toggle="tab" href="javascript:;" data-bs-target="#cf-servicios">Servicios (<?php echo count($servicios); ?>)</a></li>
            <li><a role="presentation" data-bs-toggle="tab" href="javascript:;" data-bs-target="#cf-facturas">Facturas (<?php echo count($facturas); ?>)</a></li>
            <li><a role="presentation" data-bs-toggle="tab" href="javascript:;" data-bs-target="#cf-renovaciones">Renovaciones (<?php echo count($renovaciones); ?>)</a></li>
            <li><a role="presentation" data-bs-toggle="tab" href="javascript:;" data-bs-target="#cf-kit">Kit Digital (<?php echo count($kit_digital); ?>)</a></li>
        </ul>

        <div class="tab-content">
            <!-- RESUMEN -->
            <div role="tabpanel" class="tab-pane fade" id="cf-resumen">
                <div class="row mt-3">
                    <div class="col-md-4">
                        <div class="card shadow-sm">
                            <div class="card-header bg-white fw-bold">Datos del cliente</div>
                            <div class="card-body">
                                <table class="table table-sm table-borderless">
                                    <tr><td class="text-muted">Nombre fiscal</td><td><?php echo $cliente->nombre_fiscal ?: '-'; ?></td></tr>
                                    <tr><td class="text-muted">CIF/NIF</td><td><?php echo $cliente->cif_nif ?: '-'; ?></td></tr>
                                    <tr><td class="text-muted">Email</td><td><?php echo $cliente->email_facturacion ?: '-'; ?></td></tr>
                                    <tr><td class="text-muted">Teléfono</td><td><?php echo $cliente->telefono ?: '-'; ?></td></tr>
                                    <tr><td class="text-muted">Dirección</td><td><?php echo $cliente->direccion ?: '-'; ?><br><?php echo $cliente->codigo_postal ? $cliente->codigo_postal . ' ' . $cliente->ciudad : $cliente->ciudad; ?></td></tr>
                                    <tr><td class="text-muted">Contacto</td><td><?php echo $cliente->persona_contacto ?: '-'; ?></td></tr>
                                    <tr><td class="text-muted">Forma de pago</td><td><?php echo ucfirst($cliente->forma_pago_default); ?></td></tr>
                                    <tr><td class="text-muted">Estado</td><td>
                                        <?php $col=['activo'=>'success','inactivo'=>'secondary','finalizado'=>'dark','suspendido'=>'warning','cancelado'=>'danger'][$cliente->estado]??'secondary'; ?>
                                        <span class="badge bg-<?php echo $col; ?>"><?php echo ucfirst($cliente->estado); ?></span>
                                    </td></tr>
                                    <?php if ($cliente->etiquetas): ?><tr><td class="text-muted">Etiquetas</td><td><?php echo $cliente->etiquetas; ?></td></tr><?php endif; ?>
                                </table>
                                <?php echo modal_anchor(get_uri("facturacion/cliente_modal_form"), '<i data-feather="edit-2" class="icon-14"></i> Editar cliente', ['class' => 'btn btn-outline-secondary btn-sm', 'title' => 'Editar', 'data-post-id' => $cliente->id]); ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <!-- KPIs rápidos -->
                        <?php
                        $total_recurrente = array_sum(array_map(function($s){ return $s->es_recurrente && $s->estado=='activo' ? $s->importe : 0; }, $servicios));
                        $total_facturado = array_sum(array_column($facturas, 'total'));
                        $total_cobrado = array_sum(array_column($facturas, 'importe_cobrado'));
                        $total_pendiente = $total_facturado - $total_cobrado;
                        ?>
                        <div class="row mb-3">
                            <div class="col-md-3"><div class="card shadow-sm text-center py-2">
                                <div class="fs-5 fw-bold text-success"><?php echo number_format($total_recurrente, 2, ',', '.'); ?> €</div>
                                <div class="small text-muted">Recurrente/mes</div>
                            </div></div>
                            <div class="col-md-3"><div class="card shadow-sm text-center py-2">
                                <div class="fs-5 fw-bold text-info"><?php echo number_format($total_facturado, 2, ',', '.'); ?> €</div>
                                <div class="small text-muted">Total facturado</div>
                            </div></div>
                            <div class="col-md-3"><div class="card shadow-sm text-center py-2">
                                <div class="fs-5 fw-bold text-primary"><?php echo number_format($total_cobrado, 2, ',', '.'); ?> €</div>
                                <div class="small text-muted">Total cobrado</div>
                            </div></div>
                            <div class="col-md-3"><div class="card shadow-sm text-center py-2">
                                <div class="fs-5 fw-bold <?php echo $total_pendiente > 0 ? 'text-warning' : 'text-success'; ?>"><?php echo number_format($total_pendiente, 2, ',', '.'); ?> €</div>
                                <div class="small text-muted">Pendiente</div>
                            </div></div>
                        </div>
                        <?php if ($cliente->observaciones): ?>
                        <div class="card shadow-sm">
                            <div class="card-header bg-white fw-bold">Observaciones internas</div>
                            <div class="card-body"><?php echo nl2br($cliente->observaciones); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- SERVICIOS -->
            <div role="tabpanel" class="tab-pane fade" id="cf-servicios">
                <div class="d-flex justify-content-end mt-3 mb-2">
                    <?php echo modal_anchor(get_uri("facturacion/cliente_servicio_modal_form"), '<i data-feather="plus-circle" class="icon-14"></i> Añadir servicio', ['class' => 'btn btn-primary btn-sm', 'title' => 'Añadir servicio', 'data-post-cliente_id' => $cliente->id]); ?>
                </div>
                <table class="table table-hover table-sm" id="tabla-servicios-cf">
                    <thead class="table-light"><tr><th>Concepto</th><th>Importe</th><th>Periodicidad</th><th>Tipo</th><th>Inicio</th><th>Fin</th><th>Estado</th><th>Acciones</th></tr></thead>
                    <tbody>
                        <?php foreach ($servicios as $s):
                            $col=['activo'=>'success','pausado'=>'warning','finalizado'=>'dark','cancelado'=>'danger','pendiente_revision'=>'info'][$s->estado]??'secondary';
                        ?>
                        <tr>
                            <td><?php echo $s->concepto; ?></td>
                            <td><?php echo number_format($s->importe, 2, ',', '.'); ?> €</td>
                            <td><?php echo ucfirst($s->periodicidad); ?></td>
                            <td><?php echo $s->es_recurrente ? '<span class="badge bg-success">Recurrente</span>' : '<span class="badge bg-info">Puntual</span>'; ?></td>
                            <td><?php echo $s->fecha_inicio ?: '-'; ?></td>
                            <td><?php echo $s->fecha_fin ?: '-'; ?></td>
                            <td><span class="badge bg-<?php echo $col; ?>"><?php echo ucfirst(str_replace('_',' ',$s->estado)); ?></span></td>
                            <td>
                                <?php echo modal_anchor(get_uri("facturacion/cliente_servicio_modal_form"), '<i data-feather="edit-2" class="icon-12"></i>', ['class' => 'btn btn-outline-secondary btn-sm', 'data-post-id' => $s->id, 'data-post-cliente_id' => $cliente->id]); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- FACTURAS -->
            <div role="tabpanel" class="tab-pane fade" id="cf-facturas">
                <div class="d-flex justify-content-end mt-3 mb-2">
                    <?php echo modal_anchor(get_uri("facturacion/factura_modal_form"), '<i data-feather="plus-circle" class="icon-14"></i> Nueva factura', ['class' => 'btn btn-primary btn-sm', 'title' => 'Nueva factura']); ?>
                </div>
                <table class="table table-hover table-sm" id="tabla-facturas-cf">
                    <thead class="table-light"><tr><th>Nº Factura</th><th>Período</th><th>Fecha</th><th>Total</th><th>Cobrado</th><th>Pendiente</th><th>Estado cobro</th></tr></thead>
                    <tbody>
                        <?php foreach ($facturas as $f):
                            $col=['pendiente'=>'warning','cobrado'=>'success','rechazado'=>'danger','parcialmente_cobrado'=>'info','vencido'=>'danger','no_procede'=>'secondary'][$f->estado_cobro]??'secondary';
                        ?>
                        <tr>
                            <td><a href="<?php echo get_uri("facturacion/factura_view/$f->id"); ?>"><?php echo $f->numero_factura; ?></a></td>
                            <td><?php echo $meses_nombres[$f->mes]; ?> <?php echo $f->anno; ?></td>
                            <td><?php echo $f->fecha_emision; ?></td>
                            <td><?php echo number_format($f->total, 2, ',', '.'); ?> €</td>
                            <td class="text-success"><?php echo number_format($f->importe_cobrado, 2, ',', '.'); ?> €</td>
                            <td class="<?php echo ($f->total-$f->importe_cobrado)>0?'text-warning':'text-success'; ?>"><?php echo number_format($f->total-$f->importe_cobrado, 2, ',', '.'); ?> €</td>
                            <td><span class="badge bg-<?php echo $col; ?>"><?php echo ucfirst(str_replace('_',' ',$f->estado_cobro)); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- RENOVACIONES -->
            <div role="tabpanel" class="tab-pane fade" id="cf-renovaciones">
                <div class="d-flex justify-content-end mt-3 mb-2">
                    <?php echo modal_anchor(get_uri("facturacion/renovacion_modal_form"), '<i data-feather="plus-circle" class="icon-14"></i> Nueva renovación', ['class' => 'btn btn-primary btn-sm', 'data-post-cliente_id' => $cliente->id]); ?>
                </div>
                <table class="table table-hover table-sm">
                    <thead class="table-light"><tr><th>Tipo</th><th>Descripción</th><th>Fecha</th><th>Precio venta</th><th>Estado</th></tr></thead>
                    <tbody>
                        <?php foreach ($renovaciones as $r):
                            $col=['pendiente'=>'warning','renovado'=>'success','cancelado'=>'danger'][$r->estado]??'secondary';
                        ?>
                        <tr>
                            <td><span class="badge bg-secondary"><?php echo ucfirst($r->tipo); ?></span></td>
                            <td><?php echo $r->descripcion; ?></td>
                            <td><?php echo $r->fecha_renovacion; ?></td>
                            <td><?php echo $r->precio_venta ? number_format($r->precio_venta,2,',','.') . ' €' : '-'; ?></td>
                            <td><span class="badge bg-<?php echo $col; ?>"><?php echo ucfirst($r->estado); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- KIT DIGITAL -->
            <div role="tabpanel" class="tab-pane fade" id="cf-kit">
                <div class="d-flex justify-content-end mt-3 mb-2">
                    <?php echo modal_anchor(get_uri("facturacion/kit_digital_modal_form"), '<i data-feather="plus-circle" class="icon-14"></i> Nuevo Kit Digital', ['class' => 'btn btn-primary btn-sm']); ?>
                </div>
                <table class="table table-hover table-sm">
                    <thead class="table-light"><tr><th>Solución</th><th>Bono</th><th>Facturado</th><th>Recibido</th><th>Estado</th></tr></thead>
                    <tbody>
                        <?php foreach ($kit_digital as $k):
                            $col=['cobrado'=>'success','cancelado'=>'danger','en_ejecucion'=>'primary'][$k->estado_proyecto]??'secondary';
                        ?>
                        <tr>
                            <td><?php echo $k->solucion; ?></td>
                            <td><?php echo number_format($k->importe_bono,2,',','.'); ?> €</td>
                            <td><?php echo number_format($k->importe_facturado,2,',','.'); ?> €</td>
                            <td class="text-success fw-bold"><?php echo number_format($k->importe_recibido,2,',','.'); ?> €</td>
                            <td><span class="badge bg-<?php echo $col; ?>"><?php echo ucfirst(str_replace('_',' ',$k->estado_proyecto)); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function(){
    $("[data-bs-target='#cf-resumen']").trigger("click");
    feather.replace();
});
</script>
