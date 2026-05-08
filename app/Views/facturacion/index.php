<div id="page-content" class="page-wrapper clearfix">
    <div class="clearfix grid-button">
        <ul id="facturacion-tabs" data-bs-toggle="ajax-tab" class="nav nav-tabs bg-white title" role="tablist">
            <li><a role="presentation" data-bs-toggle="tab" href="javascript:;" data-bs-target="#fac-dashboard">
                <i data-feather="bar-chart-2" class="icon-14"></i> Dashboard
            </a></li>
            <li><a role="presentation" data-bs-toggle="tab" href="<?php echo_uri("facturacion/clientes"); ?>" data-bs-target="#fac-clientes">
                <i data-feather="users" class="icon-14"></i> Clientes
            </a></li>
            <li><a role="presentation" data-bs-toggle="tab" href="<?php echo_uri("facturacion/facturas"); ?>" data-bs-target="#fac-facturas">
                <i data-feather="file-text" class="icon-14"></i> Facturas
            </a></li>
            <li><a role="presentation" data-bs-toggle="tab" href="<?php echo_uri("facturacion/cobros"); ?>" data-bs-target="#fac-cobros">
                <i data-feather="check-circle" class="icon-14"></i> Cobros
            </a></li>
            <li><a role="presentation" data-bs-toggle="tab" href="<?php echo_uri("facturacion/remesas"); ?>" data-bs-target="#fac-remesas">
                <i data-feather="layers" class="icon-14"></i> Remesas
            </a></li>
            <li><a role="presentation" data-bs-toggle="tab" href="<?php echo_uri("facturacion/renovaciones"); ?>" data-bs-target="#fac-renovaciones">
                <i data-feather="refresh-cw" class="icon-14"></i> Renovaciones
            </a></li>
            <li><a role="presentation" data-bs-toggle="tab" href="<?php echo_uri("facturacion/kit_digital"); ?>" data-bs-target="#fac-kit">
                <i data-feather="cpu" class="icon-14"></i> Kit Digital
            </a></li>
            <li><a role="presentation" data-bs-toggle="tab" href="<?php echo_uri("facturacion/comisiones"); ?>" data-bs-target="#fac-comisiones">
                <i data-feather="percent" class="icon-14"></i> Comisiones
            </a></li>
            <li><a role="presentation" data-bs-toggle="tab" href="<?php echo_uri("facturacion/servicios_catalogo"); ?>" data-bs-target="#fac-catalogo">
                <i data-feather="package" class="icon-14"></i> Catálogo
            </a></li>

            <div class="tab-title clearfix no-border">
                <div class="title-button-group">
                    <?php if ($login_user->is_admin): ?>
                        <?php echo modal_anchor(get_uri("facturacion/generar_mes_modal"), '<i data-feather="zap" class="icon-16"></i> Generar mes', ['class' => 'btn btn-default', 'title' => 'Generar facturas recurrentes del mes']); ?>
                        <?php echo modal_anchor(get_uri("facturacion/factura_modal_form"), '<i data-feather="plus-circle" class="icon-16"></i> Nueva factura', ['class' => 'btn btn-default', 'title' => 'Nueva factura']); ?>
                    <?php endif; ?>
                </div>
            </div>
        </ul>

        <div class="tab-content">
            <!-- DASHBOARD -->
            <div role="tabpanel" class="tab-pane fade" id="fac-dashboard">
                <?php echo view("facturacion/dashboard/index", [
                    'anno' => $anno, 'mes' => $mes,
                    'resumen_mes' => $resumen_mes,
                    'resumen_anno' => $resumen_anno,
                    'resumen_clientes' => $resumen_clientes,
                    'servicios_activos' => $servicios_activos,
                    'clientes_activos' => $clientes_activos,
                    'renovaciones_proximas' => $renovaciones_proximas,
                    'avisos' => $avisos,
                    'meses_nombres' => $meses_nombres,
                ]); ?>
            </div>
            <div role="tabpanel" class="tab-pane fade" id="fac-clientes"></div>
            <div role="tabpanel" class="tab-pane fade" id="fac-facturas"></div>
            <div role="tabpanel" class="tab-pane fade" id="fac-cobros"></div>
            <div role="tabpanel" class="tab-pane fade" id="fac-remesas"></div>
            <div role="tabpanel" class="tab-pane fade" id="fac-renovaciones"></div>
            <div role="tabpanel" class="tab-pane fade" id="fac-kit"></div>
            <div role="tabpanel" class="tab-pane fade" id="fac-comisiones"></div>
            <div role="tabpanel" class="tab-pane fade" id="fac-catalogo"></div>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {
    var tab = "<?php echo $tab; ?>";
    var tabMap = {
        'clientes':    '#fac-clientes',
        'facturas':    '#fac-facturas',
        'cobros':      '#fac-cobros',
        'remesas':     '#fac-remesas',
        'renovaciones':'#fac-renovaciones',
        'kit_digital': '#fac-kit',
        'comisiones':  '#fac-comisiones',
        'catalogo':    '#fac-catalogo',
    };
    if (tab && tabMap[tab]) {
        $("[data-bs-target='" + tabMap[tab] + "']").trigger("click");
    } else {
        $("[data-bs-target='#fac-dashboard']").trigger("click");
    }
});
</script>
