<div class="details-view-top-button clearfix">
    <?php echo view("includes/back_button", ['button_url' => get_uri("facturacion/clientes"), 'button_text' => 'Clientes', 'extra_class' => 'float-start dark']); ?>
</div>

<div class="clearfix page-content xs-full-width">
    <div class="container-fluid">

        <div class="row mb-4">
            <div class="col-12">
                <div class="page-title clearfix no-border no-bg">
                    <h1 class="pl0">
                        <i data-feather="briefcase" class="icon"></i> <?php echo $cliente->nombre; ?>
                        <?php if ($cliente->vat_number): ?>
                            <small class="text-muted fs-5 ms-2"><?php echo $cliente->vat_number; ?></small>
                        <?php endif; ?>
                        <span class="ms-3"><?php
                            $fp = $cliente->forma_pago ?: 'transferencia';
                            $fp_map = ['remesa'=>['info','🏦 Remesa'],'transferencia'=>['primary','↗️ Transferencia'],'efectivo'=>['success','💵 Efectivo']];
                            $fpd = $fp_map[$fp] ?? ['secondary', ucfirst($fp)];
                            echo '<span class="badge bg-'.$fpd[0].'">'.$fpd[1].'</span>';
                        ?></span>
                    </h1>
                </div>
            </div>
        </div>

        <!-- Info del cliente -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card shadow-sm">
                    <div class="card-header fw-bold"><i data-feather="info" class="icon-14"></i> Datos</div>
                    <div class="card-body">
                        <?php if ($cliente->address): ?><p class="mb-1"><strong>Dirección:</strong> <?php echo $cliente->address; ?>, <?php echo $cliente->city; ?> <?php echo $cliente->zip; ?></p><?php endif; ?>
                        <?php if ($cliente->phone): ?><p class="mb-1"><strong>Teléfono:</strong> <?php echo $cliente->phone; ?></p><?php endif; ?>
                        <hr>
                        <?php echo modal_anchor(get_uri("facturacion/cliente_forma_pago_modal"), '<i data-feather="credit-card" class="icon-14"></i> Cambiar forma de pago', ['class' => 'btn btn-sm btn-outline-secondary', 'title' => 'Forma de pago', 'data-post-id' => $cliente->id]); ?>
                        <a href="<?php echo get_uri('clients/view/' . $cliente->id); ?>" class="btn btn-sm btn-outline-info ms-2">
                            <i data-feather="external-link" class="icon-14"></i> Ver en CRM
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-8">
                <!-- Resumen facturas del cliente -->
                <div class="card shadow-sm">
                    <div class="card-header fw-bold"><i data-feather="file-text" class="icon-14"></i> Facturas (<?php echo count($facturas); ?>)</div>
                    <div class="card-body p-0">
                        <?php if (empty($facturas)): ?>
                            <p class="text-muted p-3 mb-0">Sin facturas.</p>
                        <?php else: ?>
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr><th>Nº Factura</th><th>Período</th><th>Total</th><th>Estado cobro</th><th></th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($facturas as $f): ?>
                                <tr>
                                    <td><?php echo $f->numero_factura; ?></td>
                                    <td><?php echo $meses_nombres[$f->mes] ?? $f->mes; ?>/<?php echo $f->anno; ?></td>
                                    <td><?php echo number_format($f->total, 2, ',', '.'); ?> €</td>
                                    <td>
                                        <?php
                                        $ec_map = ['cobrado'=>['success','✅ Pagado'],'pendiente'=>['warning','⏳ Pendiente'],'rechazado'=>['danger','🔴 Rechazado'],'vencido'=>['danger','🔴 Retrasado'],'parcialmente_cobrado'=>['info','🔶 Parcial']];
                                        $ecd = $ec_map[$f->estado_cobro] ?? ['secondary', $f->estado_cobro];
                                        echo '<span class="badge bg-'.$ecd[0].'">'.$ecd[1].'</span>';
                                        ?>
                                    </td>
                                    <td><a href="<?php echo get_uri("facturacion/factura_view/$f->id"); ?>" class="btn btn-outline-info btn-sm"><i data-feather="eye" class="icon-12"></i></a></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>feather.replace();</script>
