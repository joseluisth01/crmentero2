<!DOCTYPE html>
<html lang="es">
<head>
    <?php echo view('includes/head'); ?>
    <?php
    load_css(array("assets/css/invoice.css"));
    load_js(array("assets/js/signature/signature_pad.min.js"));
    ?>
    <style>
        * { box-sizing: border-box; }
        body {
            background: #f0f2f5;
            font-family: 'Helvetica Neue', Arial, sans-serif;
            color: #333;
            margin: 0;
            padding: 0;
            overflow-y: auto !important;
            height: auto !important;
        }

        .tt-topbar {
            background: #fff;
            border-bottom: 3px solid #d72173;
            padding: 0 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 64px;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 12px rgba(215,33,115,0.10);
        }
        .tt-topbar-logo { height: 36px; width: auto; }
        .tt-topbar-actions { display: flex; align-items: center; gap: 10px; }

        .tt-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 9px 20px;
            border-radius: 50px;
            font-size: 14px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
        }
        .tt-btn-outline { background: transparent; border: 2px solid #e0e0e0; color: #555; }
        .tt-btn-outline:hover { border-color: #d72173; color: #d72173; }
        .tt-btn-danger { background: #fff0f5; border: 2px solid #d72173; color: #d72173; }
        .tt-btn-danger:hover { background: #d72173; color: #fff; }
        .tt-btn-success { background: #d72173; color: #fff; border: 2px solid #d72173; }
        .tt-btn-success:hover { background: #b51860; border-color: #b51860; }

        .tt-status-badge { display: inline-flex; align-items: center; gap: 6px; padding: 8px 18px; border-radius: 50px; font-weight: 700; font-size: 14px; }
        .tt-status-accepted { background: #e8f8ef; color: #1a7f4b; }
        .tt-status-rejected  { background: #fff0f5; color: #d72173; }

        .tt-doc-wrap { max-width: 860px; margin: 36px auto 60px; padding: 0 20px; }
        .tt-doc { background: #fff; border-radius: 12px; box-shadow: 0 4px 32px rgba(0,0,0,0.10); overflow: hidden; }

        .tt-doc-header { display: flex; align-items: center; background: #fff; border-bottom: 1px solid #f0f0f0; padding: 0; }
        .tt-doc-header-logo-block {
            background: #fff;
            border-right: 4px solid #d72173;
            width: 180px;
            min-height: 90px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            padding: 20px 24px;
        }
        .tt-doc-header-logo-block img { max-width: 130px; height: auto; }
        .tt-doc-header-title { padding: 0 32px; flex: 1; }
        .tt-doc-header-title h1 { margin: 0; font-size: 26px; font-weight: 800; color: #d72173; letter-spacing: -0.5px; }
        .tt-doc-header-title p { margin: 2px 0 0; font-size: 13px; color: #aaa; }
        .tt-doc-header-company { text-align: right; padding: 0 28px; font-size: 12px; color: #888; line-height: 1.7; }
        .tt-doc-header-company strong { display: block; color: #444; font-size: 13px; margin-bottom: 2px; }
        .tt-doc-header-line { height: 3px; background: #d72173; }

        .tt-doc-body { padding: 36px 36px 0; }

        .tt-info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 32px; }
        .tt-info-box { border: 1px solid #eee; border-radius: 8px; overflow: hidden; background: #fafafa; }
        .tt-info-box-header { background: #d72173; color: #fff; padding: 7px 14px; font-size: 10px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; }
        .tt-info-box-body { padding: 14px; font-size: 13px; line-height: 1.8; color: #555; }
        .tt-info-box-body strong { color: #333; font-weight: 600; }

        .tt-section-title { display: flex; align-items: center; gap: 12px; margin: 28px 0 16px; }
        .tt-section-title h2 { margin: 0; font-size: 20px; font-weight: 700; color: #222; white-space: nowrap; }
        .tt-section-title::after { content: ''; flex: 1; height: 2px; background: linear-gradient(to right, #d72173, transparent); border-radius: 2px; }

        .tt-note-iva { background: #fffbea; border-left: 3px solid #f5a623; padding: 10px 14px; border-radius: 0 6px 6px 0; font-size: 12px; color: #7a5f00; margin-bottom: 16px; }

        .tt-table { width: 100%; border-collapse: collapse; margin-bottom: 24px; font-size: 14px; }
        .tt-table thead tr { background: #d72173; color: #fff; }
        .tt-table thead th { padding: 10px 14px; text-align: left; font-size: 11px; font-weight: 700; letter-spacing: 0.8px; text-transform: uppercase; }
        .tt-table thead th:not(:first-child) { text-align: right; }
        .tt-table tbody tr { border-bottom: 1px solid #f0f0f0; transition: background 0.1s; }
        .tt-table tbody tr:nth-child(even) { background: #fafafa; }
        .tt-table tbody tr:hover { background: #fff5f9; }
        .tt-table tbody td { padding: 12px 14px; vertical-align: top; }
        .tt-table tbody td:not(:first-child) { text-align: right; }
        .item-name { font-weight: 700; color: #222; margin-bottom: 2px; }
        .item-desc { font-size: 12px; color: #888; line-height: 1.5; }
        .item-total { color: #d72173; font-weight: 700; }

        .tt-totals { display: flex; justify-content: flex-end; margin-bottom: 32px; }
        .tt-totals-inner { min-width: 260px; }
        .tt-total-row { display: flex; justify-content: space-between; padding: 6px 0; font-size: 14px; color: #666; border-bottom: 1px solid #f5f5f5; }
        .tt-total-final { background: #d72173; color: #fff; border-radius: 8px; padding: 12px 18px; display: flex; justify-content: space-between; align-items: center; margin-top: 10px; font-weight: 700; font-size: 16px; }

        .tt-notes { background: #fff5f9; border-left: 4px solid #d72173; border-radius: 0 8px 8px 0; padding: 16px 20px; margin-bottom: 32px; font-size: 13px; color: #555; line-height: 1.7; }
        .tt-notes-title { font-weight: 700; color: #d72173; margin-bottom: 8px; font-size: 14px; }

        .tt-legal { border-top: 2px solid #d72173; padding: 20px 0 28px; font-size: 11px; color: #999; line-height: 1.6; }
        .tt-legal-title { font-weight: 700; color: #d72173; font-size: 12px; margin-bottom: 8px; letter-spacing: 0.5px; }

        .tt-doc-footer { background: #1a1a1a; color: #aaa; text-align: center; padding: 18px; font-size: 12px; line-height: 1.8; }
        .tt-doc-footer a { color: #d72173; text-decoration: none; }
        .tt-doc-footer strong { color: #fff; }

        .tt-topbar-right { position: relative; }
        .tt-menu-btn {
            display: none;
            background: none;
            border: 2px solid #d72173;
            border-radius: 8px;
            padding: 7px 12px;
            cursor: pointer;
            color: #d72173;
            font-size: 13px;
            font-weight: 700;
            align-items: center;
            gap: 6px;
        }
        .tt-dropdown-menu {
            display: none;
            position: absolute;
            top: calc(100% + 6px);
            right: 0;
            background: #fff;
            border: 1px solid #eee;
            border-radius: 10px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
            padding: 8px;
            min-width: 210px;
            z-index: 200;
            flex-direction: column;
            gap: 4px;
        }
        .tt-dropdown-menu.open { display: flex; }
        .tt-dropdown-menu .tt-btn { border-radius: 8px; justify-content: flex-start; width: 100%; }

        @media (max-width: 640px) {
            .tt-topbar { padding: 0 14px; height: 56px; }
            .tt-topbar-actions { display: none !important; }
            .tt-menu-btn { display: flex; }
            .tt-info-grid { grid-template-columns: 1fr; }
            .tt-doc-header { flex-direction: column; }
            .tt-doc-header-logo-block { width: 100%; min-height: 60px; border-right: none; border-bottom: 4px solid #d72173; }
            .tt-doc-header-title { padding: 12px 16px; }
            .tt-doc-header-company { text-align: left; padding: 10px 16px; }
            .tt-doc-body { padding: 20px 16px 0; }
            .tt-table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
            .tt-totals { overflow-x: auto; }
            .tt-totals-inner { min-width: 220px; }
        }
    </style>
</head>
<body>

<!-- ═══ BARRA SUPERIOR ═══════════════════════════════════════════ -->
<div class="tt-topbar">
    <img src="<?php echo get_logo_url(); ?>" class="tt-topbar-logo" alt="Tictac Comunicación">

    <div class="tt-topbar-actions">

        <?php if ($has_pdf_access): ?>
            <a href="<?php echo get_uri('contract/download_pdf/' . $contract_info->id . '/' . $contract_info->public_key); ?>"
               class="tt-btn tt-btn-outline" title="<?php echo app_lang('download_pdf'); ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                <span><?php echo app_lang('download_pdf'); ?></span>
            </a>
        <?php endif; ?>

        <?php if ($contract_info->status === 'accepted'): ?>
            <span class="tt-status-badge tt-status-accepted">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                Contrato firmado
            </span>
        <?php elseif ($contract_info->status === 'declined' || $contract_info->status === 'rejected'): ?>
            <span class="tt-status-badge tt-status-rejected">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                Contrato rechazado
            </span>
        <?php else: ?>
            <button type="button" class="tt-btn tt-btn-success" id="btn-firmar-sms" onclick="document.getElementById('tt-firma-panel').style.display='flex'">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
                <span>Firmar con SMS</span>
            </button>
        <?php endif; ?>

    </div>

    <!-- Móvil: menú desplegable -->
    <div class="tt-topbar-right" id="tt-mobile-wrap">
        <button class="tt-menu-btn" onclick="document.getElementById('tt-mobile-dd').classList.toggle('open')">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            Acciones
        </button>
        <div class="tt-dropdown-menu" id="tt-mobile-dd">
            <?php if ($has_pdf_access): ?>
                <a href="<?php echo get_uri('contract/download_pdf/' . $contract_info->id . '/' . $contract_info->public_key); ?>" class="tt-btn tt-btn-outline">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Descargar PDF
                </a>
            <?php endif; ?>
            <?php if ($contract_info->status === 'accepted'): ?>
                <span class="tt-status-badge tt-status-accepted" style="border-radius:8px;font-size:12px;">✓ Contrato firmado</span>
            <?php elseif ($contract_info->status !== 'declined' && $contract_info->status !== 'rejected'): ?>
                <button onclick="document.getElementById('tt-mobile-dd').classList.remove('open'); document.getElementById('tt-firma-panel').style.display='flex'" class="tt-btn tt-btn-success">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
                    Firmar con SMS
                </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('click', function(e) {
    var wrap = document.getElementById('tt-mobile-wrap');
    if (wrap && !wrap.contains(e.target)) {
        var dd = document.getElementById('tt-mobile-dd');
        if (dd) dd.classList.remove('open');
    }
});
</script>

<!-- ═══ DOCUMENTO ════════════════════════════════════════════════ -->
<div class="tt-doc-wrap">
<div class="tt-doc">

    <div class="tt-doc-header">
        <div class="tt-doc-header-logo-block">
            <img src="<?php echo get_logo_url(); ?>" alt="Tictac Comunicación">
        </div>
        <div class="tt-doc-header-title">
            <h1>CONTRATO</h1>
            <p><?php echo htmlspecialchars($contract_info->title ?? ''); ?></p>
        </div>
        <div class="tt-doc-header-company">
            <strong>Tictac Comunicación Digital SL</strong>
            C. Cruz Conde, 19, 6º 5 · 14001 Córdoba<br>
            633 33 53 90 · hola@tictac-comunicacion.es<br>
            www.tictac-comunicacion.es
        </div>
    </div>
    <div class="tt-doc-header-line"></div>

    <div class="tt-doc-body">

        <div class="tt-info-grid">
            <div class="tt-info-box">
                <div class="tt-info-box-header">Datos del Contrato</div>
                <div class="tt-info-box-body">
                    <strong>Referencia:</strong> <?php echo get_contract_id($contract_info->id); ?><br>
                    <strong>Fecha:</strong> <?php echo format_to_date($contract_info->contract_date, false); ?><br>
                    <strong>Válido hasta:</strong> <?php echo format_to_date($contract_info->valid_until, false); ?>
                </div>
            </div>
            <div class="tt-info-box">
                <div class="tt-info-box-header">Información del Cliente</div>
                <div class="tt-info-box-body">
                    <strong>Empresa:</strong> <?php echo htmlspecialchars($client_info->company_name ?? ''); ?><br>
                    <?php if (!empty($client_info->address)): ?>
                        <strong>Dirección:</strong> <?php echo htmlspecialchars($client_info->address); ?><br>
                    <?php endif; ?>
                    <?php if (!empty($client_info->city)): ?>
                        <strong>Ciudad:</strong> <?php echo htmlspecialchars($client_info->city); ?> <?php echo htmlspecialchars($client_info->zip ?? ''); ?><br>
                    <?php endif; ?>
                    <?php if (!empty($client_info->vat_number)): ?>
                        <strong>CIF/NIF:</strong> <?php echo htmlspecialchars($client_info->vat_number); ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="tt-section-title">
            <h2>Servicios Contratados</h2>
        </div>

        <div class="tt-note-iva">
            * Los precios mostrados no incluyen IVA &nbsp;·&nbsp;
            ** El contrato tendrá validez hasta la fecha indicada arriba
        </div>

        <div class="tt-table-wrap">
        <table class="tt-table">
            <thead>
                <tr>
                    <th>Servicio / Descripción</th>
                    <th>Cantidad</th>
                    <th>Precio</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($contract_items as $item): ?>
                <tr>
                    <td>
                        <div class="item-name"><?php echo htmlspecialchars($item->title); ?></div>
                        <?php if (!empty($item->description)): ?>
                            <div class="item-desc"><?php echo $item->description; ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?php echo to_decimal_format($item->quantity); ?> <?php echo htmlspecialchars($item->unit_type ?? ''); ?></td>
                    <td><?php echo to_currency($item->rate, $contract_total_summary->currency_symbol); ?></td>
                    <td class="item-total"><?php echo to_currency($item->total, $contract_total_summary->currency_symbol); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <?php $ts = $contract_total_summary; ?>
        <div class="tt-totals">
            <div class="tt-totals-inner">
                <div class="tt-total-row">
                    <span>Subtotal (sin IVA)</span>
                    <span><?php echo to_currency($ts->contract_subtotal, $ts->currency_symbol); ?></span>
                </div>
                <?php if ($ts->tax > 0): ?>
                <div class="tt-total-row">
                    <span><?php echo $ts->tax_name; ?></span>
                    <span><?php echo to_currency($ts->tax, $ts->currency_symbol); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($ts->tax2 > 0): ?>
                <div class="tt-total-row">
                    <span><?php echo $ts->tax_name2; ?></span>
                    <span><?php echo to_currency($ts->tax2, $ts->currency_symbol); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($ts->discount_total > 0): ?>
                <div class="tt-total-row" style="color:#1a7f4b;font-weight:600;">
                    <span>Descuento</span>
                    <span>- <?php echo to_currency($ts->discount_total, $ts->currency_symbol); ?></span>
                </div>
                <?php endif; ?>
                <div class="tt-total-final">
                    <span>TOTAL (IVA incluido)</span>
                    <span><?php echo to_currency($ts->contract_total, $ts->currency_symbol); ?></span>
                </div>
            </div>
        </div>

        <?php if (!empty($contract_info->note) && strip_tags($contract_info->note) !== ''): ?>
        <div class="tt-section-title"><h2>Notas Adicionales</h2></div>
        <div class="tt-notes"><?php echo custom_nl2br($contract_info->note); ?></div>
        <?php endif; ?>

        <!-- Sobre Nosotros — siempre Tictac -->
        <div class="tt-section-title" style="margin-top:28px;">
            <h2>Sobre Nosotros</h2>
        </div>
        <div class="tt-notes" style="background:#fff5f9;border-left-color:#d72173;">
            En Tictac Comunicación Digital SL desarrollamos estrategias digitales orientadas a conversión, visibilidad y crecimiento real. Cada contrato se diseña a medida, alineado con los objetivos del cliente y basado en criterios técnicos, creativos y estratégicos.
        </div>

        <!-- Cláusulas del contrato -->
        <?php
        $company_id_c = intval($contract_info->company_id ?? 2);
        $es_tress_c   = ($company_id_c === 1);
        $email_prov   = $es_tress_c ? 'info@proymer.com' : 'hola@tictac-comunicacion.es';
        $web_prov     = $es_tress_c ? 'Proyecto Tress Azafatas' : 'Tic Tac Comunicacion (www.tictac-comunicacion.es)';
        ?>
        <div class="tt-section-title" style="margin-top:28px;">
            <h2>Cláusulas del Contrato</h2>
        </div>
        <div style="font-size:11px;color:#555;line-height:1.6;text-align:justify;margin-bottom:20px;">
            <strong>1. OBJETO</strong><br>
            El objeto del Contrato consiste en la prestación de servicios por parte del Proveedor a cambio del pago de un precio por parte del Cliente, en los términos establecidos en el mismo.<br><br>
            Las solicitudes de modificación del contrato se harán siempre por escrito, remitido por correo ordinario o electrónico <?php echo $email_prov; ?>. Se ejecutarán siempre que sea posible y el cliente deberá asumir los costes en los que el Proveedor haya incurrido, tras dicha modificación del contrato.<br><br>
            El Cliente acepta que el Proveedor pueda publicar su imagen corporativa, nombre comercial y sitio web dentro de "casos de éxito" o "sección clientes" de la web de <?php echo $web_prov; ?>, así como la firma de la Empresa Tic Tac Comunicación en Footer (Pie de Página de la web del Cliente).<br><br>

            <strong>2. SERVICIOS DEL PROVEEDOR</strong><br>
            2.1. Los Servicios del proyecto vendrán descritos en la hoja de encargo adjunta que deberá ser firmada por el cliente y por la empresa que provee el servicio.<br><br>
            En relación al diseño web, si el cliente ha contratado este servicio y procede, el Proveedor presentará al cliente hasta 3 bocetos en soporte físico o digital. El Cliente ha de firmar el Boceto escogido, todos los cambios a partir del momento de la firma, conllevarán costos adicionales.<br><br>
            2.1.1. Realización de material especial tal como: tipografía no convencional, caligrafía, mapas, diagramas, gráficos, vectores o fotomontajes.<br>
            2.1.2. Preparación de material existente para su reproducción tales como: redibujo parcial o total, conversión a líneas, escaneado y retoque de imágenes, tipeados, etc.<br>
            2.1.3. Seguimiento de la producción.<br>
            2.1.4. Recuperación de información, siempre que técnicamente sea posible. El horario de trabajo de los técnicos del Proveedor será de lunes a viernes de 9:00 a 17:00, salvo en los meses de Julio y agosto que será de 9:00 a 15:00.<br>
            2.1.5. La corrección de errores imputables a la manipulación a través de los Programas de gestión de contenidos por personal no autorizado expresamente por el Proveedor.<br>
            2.1.6. Las tareas necesarias para restablecer la situación anterior derivada de operaciones incorrectas por parte del Cliente que ocasionen pérdidas de información, destrucción o desorganización de ficheros, y situaciones análogas.<br>
            2.1.7. La reparación de daños causados por virus o defectos de otros programas no relacionados en el Contrato, o en anexo posterior.<br>
            2.1.8. La reparación de daños y malfuncionamientos causados por accidentes, uso indebido, catástrofes, abusos, alteraciones, sustitución de elementos o software no suministrado y/o recomendado por el Proveedor.<br><br>

            <strong>3. VALORACIÓN DE LOS SERVICIOS, FACTURACIÓN, FORMA DE PAGO, IMPUESTOS Y GASTOS</strong><br>
            3.1. La valoración económica será actualizada anualmente por el Proveedor, en función de las nuevas tarifas que el Proveedor establezca.<br>
            3.2. El precio de los Servicios será abonado por el Cliente al Proveedor en el momento de la formalización del Contrato.<br>
            3.3. El precio expresado a continuación contiene los impuestos indirectos desglosados a la fecha de la firma actual.<br>
            3.4. Se emitirá un cobro mensual en el siguiente número de cuenta bancaria facilitado por el Cliente.<br>
            3.5. Cualquier revisión o adiciones a los servicios descritos en el contrato serán facturados como Servicios Adicionales no incluidos en el presupuesto estimado.<br><br>

            <strong>4. RESPONSABILIDAD DEL CLIENTE</strong><br>
            4.1. El Cliente proveerá información fehaciente y completa y materiales al Proveedor, y será responsable de la exactitud y completitud de toda la información y los materiales provistos.<br>
            4.2. El Cliente en caso haber realizado alguna modificación por su cuenta y por ello, haber desconfigurado la web, será el mismo Cliente quien responda por el costo del arreglo.<br>
            4.3. Todo texto e información aportado por el Cliente se entregará al Proveedor en formato digital. Este proceso será presupuestado como un servicio suplementario.<br><br>

            <strong>5. DERECHOS Y PROPIEDAD</strong><br>
            5.1. Todos los servicios provistos por el Proveedor y aprobados bajo este contrato serán para uso exclusivo del Cliente más allá de su uso promocional propio del Proveedor.<br>
            5.2. El Proveedor se compromete a almacenar los originales durante 6 meses a partir de la finalización del Proyecto.<br>
            5.3. El Dominio (dirección web) pertenecerá al Cliente, siendo éste su propietario en todo momento.<br>
            5.4. Una vez finalizado el pago total del monto acordado, el Cliente, pasará a ser propietario de la Web.<br><br>

            <strong>6. DURACIÓN DEL CONTRATO</strong><br>
            6.1. El Contrato tendrá una vigencia mínima de un (1) año, contada a partir de la fecha de la firma del presente Contrato.<br>
            6.2. El Cliente podrá rescindir el presente Contrato, notificándoselo por escrito al Proveedor con al menos treinta (30) días de antelación a la fecha de vencimiento inicial, o, en su caso, de cualquiera de sus prórrogas.<br><br>

            <strong>7. EXTINCIÓN DEL CONTRATO</strong><br>
            7.1. El Contrato se extinguirá por las causas generales establecidas en la legislación vigente.<br>
            7.2. En todo caso, la extinción del Contrato antes de la finalización del período inicial no dará lugar a devolución alguna del precio abonado al Proveedor.<br>
            7.3. La no acreditación del pago del precio será causa automática de resolución del Contrato.<br><br>

            <strong>8. NATURALEZA DE LA RELACIÓN</strong><br>
            8.1. El presente Contrato tiene carácter mercantil y se regirá por sus propias cláusulas, y en lo que en ellas no estuviere previsto, por las disposiciones del Código de Comercio, leyes especiales y usos mercantiles, y en su defecto, por el Código Civil.<br><br>

            <strong>9. PROTECCIÓN DE DATOS DE CARÁCTER PERSONAL</strong><br>
            9.1. Debido a la naturaleza de los Servicios, el Proveedor puede tener que realizar tratamientos automatizados de ficheros del Cliente que contengan datos de carácter personal. El Proveedor utilizará dichos datos única y exclusivamente para los fines que figuran en el Contrato y siempre por cuenta del Cliente.<br>
            9.2. El Cliente únicamente permitirá el acceso a datos de carácter personal al Proveedor cuando sea necesario para la ejecución del objeto del Contrato.<br>
            9.3. El Cliente afirma y garantiza que los datos han sido recogidos de acuerdo a lo establecido en la LOPD.<br><br>

            <strong>10. CONFIDENCIALIDAD</strong><br>
            10.1. El Proveedor considerará confidencial toda la información relacionada con los Servicios, y que obtenga durante la prestación de los mismos.<br><br>

            <strong>11. RESPONSABILIDAD DEL PROVEEDOR</strong><br>
            11.1. Salvo en los casos de culpa grave o dolo, la responsabilidad total del Proveedor no excederá, en su conjunto, de la cantidad correspondiente al precio abonado por los Servicios durante la última anualidad. El Proveedor no será responsable, en ningún caso, de los daños que puedan ser calificados como daños indirectos, consecuenciales, pérdida de beneficio, negocio, ingresos, clientes, datos, imagen o reputación comercial.<br><br>

            <strong>12. ACTUALIZACIÓN</strong><br>
            12.1. En el caso de que alguna o algunas de las cláusulas del Contrato pasen a ser inválidas, ilegales o inejecutables, se considerarán ineficaces en la medida que corresponda, pero en lo demás, este Contrato conservará su validez. CONTRATO ÚNICO<br><br>

            <strong>13. NOTIFICACIONES Y REQUERIMIENTOS</strong><br>
            13.1. Toda notificación o requerimiento que traiga su causa del Contrato se deberá remitir por escrito a la otra Parte, bien por E-mail, bien personalmente, o por mensajero o correo certificado con acuse de recibo.<br><br>

            <strong>14. JURISDICCIÓN Y COMPETENCIA</strong><br>
            14.1. Las Partes, con renuncia expresa a cualquier otro fuero que pudiera corresponderles, se someten a la jurisdicción y competencia de los Juzgados y Tribunales de Córdoba.<br>
            14.2. Y para que así conste, y en prueba de conformidad y aceptación de todo cuanto antecede, las Partes firman el presente Contrato por duplicado ejemplar y a un sólo efecto en la fecha y lugar indicados en el encabezamiento.

            <?php if ($es_tress_c): ?>
            <br><br>
            <strong>KIT DIGITAL</strong><br>
            El cliente es beneficiario de subvención de Kit Digital de 3000 euros que se decrementará del precio de las correspondientes partidas o soluciones digitalizadoras. El cliente está obligado a cumplir con las premisas tributarias que genera la subvención durante el año siguiente para mantener la subvención y siempre según la Orden TDF/435/2024, de 9 de mayo, por la que se modifica la Orden ETD/1498/2021, de 29 de diciembre, por la que se aprueban las bases reguladoras de la concesión de ayudas para la digitalización de pequeñas empresas, microempresas y personas en situación de autoempleo, en el marco de la Agenda España Digital 2025, el Plan de Digitalización PYMEs 2021-2025 y el Plan de Recuperación, Transformación y Resiliencia de España -Financiado por la Unión Europea- Next Generation EU (Programa Kit Digital), publicada en Boletín Oficial del Estado a fecha 11 de Mayo de 2024.<br><br>
            No serán subvencionables el Impuesto sobre el Valor Añadido que tendrá que ser abonado por el beneficiario y cuya remesa se enviará durante los tres meses siguientes a la validación del Acuerdo de Prestación de Soluciones.<br><br>
            En caso de ser desestimada la ayuda (Kit Digital) por cualquier motivo ajeno a Proyecto Tress Azafatas será el cliente el que asuma el obligado cumplimiento del pago del servicio prestado (2000 euros, IVA no incluido). La forma de pago se establece con emisión de remesa bancaria previamente autorizada mediante firma de documento SEPA por parte del cliente.<br><br>
            En el caso de que el cliente decida desistir de la ayuda dentro del plazo de los 12 meses establecidos por Kit Digital como prestación del servicio, será el cliente el que tenga el obligado cumplimiento de hacerse cargo de la cuantía de los trabajos realizados hasta dicho momento por el agente digitalizador, PROYECTO TRESS AZAFATAS en este caso. Se calculará la parte proporcional del total del servicio que irá desde el inicio del acuerdo de prestación hasta la fecha de comunicación de renuncia de la ayuda. Una vez el cliente haya abonado la citada cuantía de los trabajos realizados se procederá a la aceptación de la renuncia por parte de PROYECTO TRESS AZAFATAS como agente digitalizador.
            <?php endif; ?>
        </div>

        <!-- RGPD condicional por compañía -->
        <div class="tt-legal">
            <div class="tt-legal-title">PROTECCIÓN DE DATOS Y CLÁUSULAS LEGALES</div>
            <?php if ($es_tress_c): ?>
                <strong>Responsable:</strong> PROYECTO TRESS AZAFATAS SL · CIF: B56028293 · C/ Cruz Conde, 19, Planta 6ª, 14001 Córdoba · Tel: 957963074 · info@proymer.com<br><br>
                Tratamos la información que nos facilita con el fin de prestarles el servicio solicitado. Los datos proporcionados se conservarán durante el tiempo necesario para cumplir con las finalidades previstas. Los datos no se cederán a terceros salvo en los casos en que exista una obligación legal. Usted tiene derecho de acceso, rectificación, supresión y portabilidad de sus datos y oposición y limitación a su tratamiento en la dirección postal o correo electrónico facilitados, adjuntando copia de su DNI o documento equivalente. Asimismo, y especialmente si considera que no ha obtenido satisfacción plena en el ejercicio de sus derechos, podrá presentar una reclamación ante la autoridad nacional de control dirigiéndose a estos efectos a la Agencia Española de Protección de Datos, C/ Jorge Juan, 6 - 28001 Madrid.<br><br>
                Asimismo, solicitamos su autorización para enviarle publicidad relacionada con nuestros productos y servicios por cualquier medio (postal, email o teléfono) e invitarle a eventos organizados por la empresa.<br><br>
                <label style="margin-right:20px;"><input type="checkbox" disabled> SI Autorizo</label>
                <label><input type="checkbox" disabled> NO Autorizo</label>
            <?php else: ?>
                <strong>Responsable:</strong> TIC TAC COMUNICACION DIGITAL SL · CIF: B09912478 · C/ Cruz Conde, 19, Planta 6ª, 14001 Córdoba · Tel: 633 33 53 90 · hola@tictac-comunicacion.es<br><br>
                Tratamos la información que nos facilita con el fin de prestarles el servicio solicitado. Los datos proporcionados se conservarán durante el tiempo necesario para cumplir con las finalidades previstas. Los datos no se cederán a terceros salvo en los casos en que exista una obligación legal. Usted tiene derecho de acceso, rectificación, supresión y portabilidad de sus datos y oposición y limitación a su tratamiento en la dirección postal o correo electrónico facilitados, adjuntando copia de su DNI o documento equivalente. Asimismo, y especialmente si considera que no ha obtenido satisfacción plena en el ejercicio de sus derechos, podrá presentar una reclamación ante la autoridad nacional de control dirigiéndose a estos efectos a la Agencia Española de Protección de Datos, C/ Jorge Juan, 6 - 28001 Madrid.<br><br>
                Asimismo, solicitamos su autorización para enviarle publicidad relacionada con nuestros productos y servicios por cualquier medio (postal, email o teléfono) e invitarle a eventos organizados por la empresa.<br><br>
                <label style="margin-right:20px;"><input type="checkbox" disabled> SI Autorizo</label>
                <label><input type="checkbox" disabled> NO Autorizo</label><br><br>
                El CLIENTE es responsable de garantizar que dispone de los consentimientos y autorizaciones legales necesarias para la publicación de imágenes o datos personales de trabajadores y terceros. TIC TAC COMUNICACION DIGITAL SL quedará exonerada de cualquier responsabilidad derivada de incumplimientos en materia de protección de datos por parte del cliente.
            <?php endif; ?>
        </div>

    </div>

    <div class="tt-doc-footer">
        <strong>Tictac Comunicación Digital SL</strong><br>
        C. Cruz Conde, 19, 6º 5 · 14001 Córdoba · 633 33 53 90 ·
        <a href="mailto:hola@tictac-comunicacion.es">hola@tictac-comunicacion.es</a> ·
        <a href="https://www.tictac-comunicacion.es" target="_blank">www.tictac-comunicacion.es</a>
    </div>

</div>
</div>

<?php echo view('modal/index'); ?>
<?php echo view('contracts/print_contract_helper_js'); ?>

<?php
// Teléfono prellenado desde meta_data si se guardó al enviar el email
$meta_preview = @unserialize($contract_info->meta_data ?? '') ?: array();
$phone_prellenado = $meta_preview['contact_phone'] ?? $meta_preview['lleida_phone'] ?? '';
// Leer también del contacto principal
if (!$phone_prellenado && !empty($client_info->id)) {
    $ci_contact = model('App\Models\Users_model')->get_details(array(
        'client_id' => $client_info->id,
        'is_primary_contact' => true,
        'user_type' => 'client',
    ))->getRow();
    $phone_prellenado = $ci_contact->phone ?? '';
}
$lleida_endpoint = 'https://gestion-tictac-comunicacion.es/lleida_sign.php';
?>

<!-- ═══ PANEL FIRMA SMS ══════════════════════════════════════════ -->
<?php if ($contract_info->status !== 'accepted' && $contract_info->status !== 'declined' && $contract_info->status !== 'rejected'): ?>
<div id="tt-firma-panel" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:9999;align-items:center;justify-content:center;padding:20px;">
    <div style="background:#fff;border-radius:16px;max-width:440px;width:100%;padding:36px 32px;box-shadow:0 20px 60px rgba(0,0,0,0.25);position:relative;">
        <button onclick="document.getElementById('tt-firma-panel').style.display='none'"
            style="position:absolute;top:14px;right:16px;background:none;border:none;font-size:22px;cursor:pointer;color:#aaa;line-height:1;">×</button>

        <div style="text-align:center;margin-bottom:24px;">
            <div style="width:56px;height:56px;background:#fff0f7;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#d72173" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
            </div>
            <h2 style="margin:0 0 6px;font-size:20px;font-weight:800;color:#1a1a1a;">Firmar con SMS</h2>
            <p style="margin:0;font-size:13px;color:#888;">Confirma tu número de móvil y te enviaremos un código para firmar el contrato.</p>
        </div>

        <div id="tt-firma-step1">
            <label style="font-size:13px;font-weight:600;color:#444;display:block;margin-bottom:6px;">Tu número de móvil</label>
            <input type="tel" id="tt-firma-phone" value="<?php echo htmlspecialchars($phone_prellenado); ?>"
                placeholder="600 000 000"
                style="width:100%;padding:12px 16px;border:2px solid #e0e0e0;border-radius:10px;font-size:16px;outline:none;transition:border 0.2s;"
                onfocus="this.style.borderColor='#d72173'" onblur="this.style.borderColor='#e0e0e0'">
            <div id="tt-firma-phone-warn" style="display:none;color:#c0392b;font-size:12px;margin-top:6px;">
                ⚠️ Introduce un número de móvil español válido (empieza por 6 o 7)
            </div>

            <label style="font-size:13px;font-weight:600;color:#444;display:block;margin-top:16px;margin-bottom:6px;">Tu nombre completo</label>
            <input type="text" id="tt-firma-name" placeholder="Nombre y apellidos"
                style="width:100%;padding:12px 16px;border:2px solid #e0e0e0;border-radius:10px;font-size:15px;outline:none;transition:border 0.2s;"
                onfocus="this.style.borderColor='#d72173'" onblur="this.style.borderColor='#e0e0e0'">

            <div id="tt-firma-error" style="display:none;background:#fff0f5;border-left:3px solid #d72173;padding:10px 14px;border-radius:0 8px 8px 0;font-size:13px;color:#c0392b;margin-top:14px;"></div>

            <button id="tt-firma-enviar" onclick="ttEnviarSMS()"
                style="width:100%;margin-top:20px;padding:14px;background:#d72173;color:#fff;border:none;border-radius:50px;font-size:16px;font-weight:700;cursor:pointer;transition:background 0.2s;">
                Enviar código SMS →
            </button>
            <p style="text-align:center;font-size:11px;color:#bbb;margin-top:12px;">
                Al firmar aceptas los términos del contrato. La firma tiene validez legal.
            </p>
        </div>

        <div id="tt-firma-step2" style="display:none;text-align:center;">
            <div style="width:64px;height:64px;background:#e8f8ef;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#1a7f4b" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.62 3.38 2 2 0 0 1 3.62 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.6a16 16 0 0 0 6 6l.96-.96a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
            </div>
            <h3 style="margin:0 0 10px;font-size:18px;font-weight:800;color:#1a7f4b;">¡SMS enviado!</h3>
            <p style="font-size:14px;color:#555;margin:0 0 6px;">Hemos enviado un código a tu móvil.</p>
            <p style="font-size:13px;color:#888;margin:0;">Sigue las instrucciones del SMS para completar la firma. Una vez firmado, esta página se actualizará automáticamente.</p>
            <button onclick="location.reload()" style="margin-top:20px;padding:10px 24px;background:#f5f5f5;border:none;border-radius:50px;font-size:14px;cursor:pointer;color:#555;">Actualizar página</button>
        </div>
    </div>
</div>

<script>
function ttEnviarSMS() {
    var phone = document.getElementById('tt-firma-phone').value.trim();
    var name  = document.getElementById('tt-firma-name').value.trim();
    var clean = phone.replace(/\s+/g,'').replace(/^\+34/,'').replace(/^0034/,'');

    document.getElementById('tt-firma-phone-warn').style.display = 'none';
    document.getElementById('tt-firma-error').style.display = 'none';

    if (!/^[67]\d{8}$/.test(clean)) {
        document.getElementById('tt-firma-phone-warn').style.display = 'block';
        return;
    }

    var btn = document.getElementById('tt-firma-enviar');
    btn.disabled = true;
    btn.textContent = 'Enviando...';

    var formData = new FormData();
    formData.append('phone', phone);
    formData.append('name', name);
    formData.append('contract_id', '<?php echo $contract_info->id; ?>');
    formData.append('public_key', '<?php echo $contract_info->public_key; ?>');

    fetch('<?php echo $lleida_endpoint; ?>', {
        method: 'POST',
        body: formData
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            document.getElementById('tt-firma-step1').style.display = 'none';
            document.getElementById('tt-firma-step2').style.display = 'block';
        } else {
            var errEl = document.getElementById('tt-firma-error');
            errEl.textContent = data.message || 'Error al enviar el SMS. Inténtalo de nuevo.';
            errEl.style.display = 'block';
            btn.disabled = false;
            btn.textContent = 'Enviar código SMS →';
        }
    })
    .catch(function() {
        var errEl = document.getElementById('tt-firma-error');
        errEl.textContent = 'Error de conexión. Inténtalo de nuevo.';
        errEl.style.display = 'block';
        btn.disabled = false;
        btn.textContent = 'Enviar código SMS →';
    });
}
</script>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.body.style.height   = 'auto';
    document.body.style.overflow = 'auto';
    document.documentElement.style.height   = 'auto';
    document.documentElement.style.overflow = 'auto';
});
</script>

</body>
</html>