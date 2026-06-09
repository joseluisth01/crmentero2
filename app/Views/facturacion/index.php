<?php
$anno = $anno ?? date('Y');
$mes  = $mes  ?? date('n');
?>
<div class="sub-header">
    <div class="sub-header-title">
        <i data-feather="file-text" class="icon-20"></i> Facturación
    </div>
    <div class="sub-header-filters">
        <button class="btn btn-sm" style="background:#d72173;color:white"
            onclick="window.location.href='<?php echo get_uri("facturacion/resumen/$anno"); ?>'">
            <i data-feather="grid" class="icon-14"></i> Vista General
        </button>
        <?php echo modal_anchor(get_uri("facturacion/factura_modal_form"), '<i data-feather="plus" class="icon-14"></i> Nueva factura', ['class' => 'btn btn-outline-secondary btn-sm', 'title' => 'Nueva factura']); ?>
        <?php echo modal_anchor(get_uri("facturacion/generar_mes_modal"), '<i data-feather="zap" class="icon-14"></i> Generar mes', ['class' => 'btn btn-outline-primary btn-sm', 'title' => 'Generar mes recurrente']); ?>
    </div>
</div>

<div class="container-fluid p-3">
    <ul data-bs-toggle="ajax-tab" class="nav nav-tabs scrollable-tabs" role="tablist" id="fac-tabs">
        <li>
            <a role="presentation" data-bs-toggle="tab"
               href="<?php echo get_uri("facturacion/dashboard_tab"); ?>"
               data-bs-target="#fac-dashboard">
                <i data-feather="bar-chart-2" class="icon-14"></i> Dashboard
            </a>
        </li>
        <li>
            <a role="presentation" data-bs-toggle="tab"
               href="<?php echo get_uri("facturacion/clientes"); ?>"
               data-bs-target="#fac-clientes">
                <i data-feather="users" class="icon-14"></i> Clientes
            </a>
        </li>

        <li>
            <a role="presentation" data-bs-toggle="tab"
               href="<?php echo get_uri("facturacion/cobros"); ?>"
               data-bs-target="#fac-cobros">
                <i data-feather="check-circle" class="icon-14"></i> Cobros
            </a>
        </li>
    </ul>

    <div class="tab-content">
        <div role="tabpanel" class="tab-pane fade" id="fac-dashboard"></div>
        <div role="tabpanel" class="tab-pane fade" id="fac-clientes"></div>

        <div role="tabpanel" class="tab-pane fade" id="fac-cobros"></div>
    </div>
</div>