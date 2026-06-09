<!DOCTYPE html>
<html lang="es">
<head>
    <?php echo view('includes/head'); ?>
    <?php
    load_css(array(
        "assets/css/invoice.css",
    ));
    load_js(array(
        "assets/js/signature/signature_pad.min.js",
    ));
    ?>
    <style>
        /* ── Reset y base ─────────────────────────────────────────── */
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

        /* ── Barra superior ───────────────────────────────────────── */
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

        /* Menú desplegable móvil */
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
            top: 100%;
            right: 0;
            background: #fff;
            border: 1px solid #eee;
            border-radius: 10px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
            padding: 8px;
            min-width: 200px;
            z-index: 200;
            flex-direction: column;
            gap: 4px;
        }
        .tt-dropdown-menu.open { display: flex; }
        .tt-dropdown-menu .tt-btn {
            border-radius: 8px;
            justify-content: flex-start;
            width: 100%;
        }
        .tt-topbar-right { position: relative; }

        @media (max-width: 640px) {
            .tt-topbar { padding: 0 14px; height: 56px; }
            .tt-topbar-actions { display: none; }
            .tt-menu-btn { display: flex; }
            .tt-info-grid { grid-template-columns: 1fr; }
            .tt-doc-header { flex-direction: column; }
            .tt-doc-header-logo-block { width: 100%; min-height: 60px; }
            .tt-doc-header-company { text-align: left; padding: 10px 16px; }
            .tt-doc-body { padding: 20px 16px 0; }
            .tt-table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
            .tt-totals { overflow-x: auto; }
        }

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

        .tt-btn-outline {
            background: transparent;
            border: 2px solid #e0e0e0;
            color: #555;
        }
        .tt-btn-outline:hover { border-color: #d72173; color: #d72173; }

        .tt-btn-danger {
            background: #fff0f5;
            border: 2px solid #d72173;
            color: #d72173;
        }
        .tt-btn-danger:hover { background: #d72173; color: #fff; }

        .tt-btn-success {
            background: #d72173;
            color: #fff;
            border: 2px solid #d72173;
        }
        .tt-btn-success:hover { background: #b51860; border-color: #b51860; }

        /* Estado aceptado/rechazado */
        .tt-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 18px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 14px;
        }
        .tt-status-accepted { background: #e8f8ef; color: #1a7f4b; }
        .tt-status-rejected  { background: #fff0f5; color: #d72173; }

        /* ── Documento ────────────────────────────────────────────── */
        .tt-doc-wrap {
            max-width: 860px;
            margin: 36px auto 60px;
            padding: 0 20px;
        }

        .tt-doc {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 32px rgba(0,0,0,0.10);
            overflow: hidden;
        }

        /* ── Header del documento ─────────────────────────────────── */
        .tt-doc-header {
            display: flex;
            align-items: center;
            background: #fff;
            border-bottom: 1px solid #f0f0f0;
            padding: 0;
        }

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

        .tt-doc-header-logo-block img {
            max-width: 130px;
            height: auto;
        }

        .tt-doc-header-title {
            padding: 0 32px;
            flex: 1;
        }

        .tt-doc-header-title h1 {
            margin: 0;
            font-size: 26px;
            font-weight: 800;
            color: #d72173;
            letter-spacing: -0.5px;
        }

        .tt-doc-header-title p {
            margin: 2px 0 0;
            font-size: 13px;
            color: #aaa;
        }

        .tt-doc-header-company {
            text-align: right;
            padding: 0 28px;
            font-size: 12px;
            color: #888;
            line-height: 1.7;
        }

        .tt-doc-header-company strong {
            display: block;
            color: #444;
            font-size: 13px;
            margin-bottom: 2px;
        }

        .tt-doc-header-line {
            height: 3px;
            background: #d72173;
        }

        /* ── Cuerpo del documento ─────────────────────────────────── */
        .tt-doc-body { padding: 36px 36px 0; }

        /* Info boxes */
        .tt-info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 32px;
        }

        .tt-info-box {
            border: 1px solid #eee;
            border-radius: 8px;
            overflow: hidden;
            background: #fafafa;
        }

        .tt-info-box-header {
            background: #d72173;
            color: #fff;
            padding: 7px 14px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .tt-info-box-body {
            padding: 14px;
            font-size: 13px;
            line-height: 1.8;
            color: #555;
        }

        .tt-info-box-body strong {
            color: #333;
            font-weight: 600;
        }

        /* Separador con título */
        .tt-section-title {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 28px 0 16px;
        }

        .tt-section-title h2 {
            margin: 0;
            font-size: 20px;
            font-weight: 700;
            color: #222;
            white-space: nowrap;
        }

        .tt-section-title::after {
            content: '';
            flex: 1;
            height: 2px;
            background: linear-gradient(to right, #d72173, transparent);
            border-radius: 2px;
        }

        /* Nota IVA */
        .tt-note-iva {
            background: #fffbea;
            border-left: 3px solid #f5a623;
            padding: 10px 14px;
            border-radius: 0 6px 6px 0;
            font-size: 12px;
            color: #7a5f00;
            margin-bottom: 16px;
        }

        /* ── Tabla de artículos ───────────────────────────────────── */
        .tt-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
            font-size: 14px;
        }

        .tt-table thead tr {
            background: #d72173;
            color: #fff;
        }

        .tt-table thead th {
            padding: 10px 14px;
            text-align: left;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.8px;
            text-transform: uppercase;
        }

        .tt-table thead th:not(:first-child) { text-align: right; }

        .tt-table tbody tr {
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.1s;
        }

        .tt-table tbody tr:nth-child(even) { background: #fafafa; }
        .tt-table tbody tr:hover { background: #fff5f9; }

        .tt-table tbody td {
            padding: 12px 14px;
            vertical-align: top;
        }

        .tt-table tbody td:not(:first-child) { text-align: right; }

        .tt-table .item-name {
            font-weight: 700;
            color: #222;
            margin-bottom: 2px;
        }

        .tt-table .item-desc {
            font-size: 12px;
            color: #888;
            line-height: 1.5;
        }

        .tt-table .item-total {
            color: #d72173;
            font-weight: 700;
        }

        /* ── Totales ──────────────────────────────────────────────── */
        .tt-totals {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 32px;
        }

        .tt-totals-inner {
            min-width: 260px;
        }

        .tt-total-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            font-size: 14px;
            color: #666;
            border-bottom: 1px solid #f5f5f5;
        }

        .tt-total-row:last-child { border-bottom: none; }

        .tt-total-final {
            background: #d72173;
            color: #fff;
            border-radius: 8px;
            padding: 12px 18px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 10px;
            font-weight: 700;
            font-size: 16px;
        }

        /* ── Notas ────────────────────────────────────────────────── */
        .tt-notes {
            background: #fff5f9;
            border-left: 4px solid #d72173;
            border-radius: 0 8px 8px 0;
            padding: 16px 20px;
            margin-bottom: 32px;
            font-size: 13px;
            color: #555;
            line-height: 1.7;
        }

        .tt-notes-title {
            font-weight: 700;
            color: #d72173;
            margin-bottom: 8px;
            font-size: 14px;
        }

        /* ── RGPD ─────────────────────────────────────────────────── */
        .tt-legal {
            border-top: 2px solid #d72173;
            padding: 20px 0 28px;
            font-size: 11px;
            color: #999;
            line-height: 1.6;
        }

        .tt-legal-title {
            font-weight: 700;
            color: #d72173;
            font-size: 12px;
            margin-bottom: 8px;
            letter-spacing: 0.5px;
        }

        /* ── Footer del documento ─────────────────────────────────── */
        .tt-doc-footer {
            background: #1a1a1a;
            color: #aaa;
            text-align: center;
            padding: 18px;
            font-size: 12px;
            line-height: 1.8;
        }

        .tt-doc-footer a { color: #d72173; text-decoration: none; }
        .tt-doc-footer strong { color: #fff; }

        /* ── Responsivo ───────────────────────────────────────────── */
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
        }
        </style>
</head>
<body>

<!-- ═══ BARRA SUPERIOR ═══════════════════════════════════════════ -->
<div class="tt-topbar">
    <img src="<?php echo get_logo_url(); ?>" class="tt-topbar-logo" alt="Tictac Comunicación">

    <!-- Desktop: botones inline -->
    <div class="tt-topbar-actions">
        <?php if ($has_pdf_access): ?>
            <a href="<?php echo get_uri('offer/download_pdf/' . $proposal_info->id . '/' . $proposal_info->public_key); ?>"
               class="tt-btn tt-btn-outline">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                <span><?php echo app_lang('download_pdf'); ?></span>
            </a>
        <?php endif; ?>
        <?php
        $tiene_firma = false;
        if (!empty($proposal_info->meta_data)) {
            $meta_check = @unserialize($proposal_info->meta_data);
            $tiene_firma = $meta_check && !empty($meta_check['signature']);
        }
        if ($tiene_firma && $has_pdf_access):
        ?>
            <a href="<?php echo get_uri('offer/download_signed_pdf/' . $proposal_info->id . '/' . $proposal_info->public_key); ?>"
               class="tt-btn tt-btn-outline" style="color:#1a7f4b;border-color:#1a7f4b;">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                <span>PDF Firmado</span>
            </a>
        <?php endif; ?>
        <?php if ($proposal_info->status === 'accepted'): ?>
            <span class="tt-status-badge tt-status-accepted">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                Propuesta aceptada
            </span>
        <?php elseif ($proposal_info->status === 'declined'): ?>
            <span class="tt-status-badge tt-status-rejected">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                Propuesta rechazada
            </span>
        <?php else: ?>
            <?php echo ajax_anchor(get_uri("offer/update_proposal_status/{$proposal_info->id}/{$proposal_info->public_key}/declined"), '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg> <span>Rechazar</span>', array("class" => "tt-btn tt-btn-danger", "data-reload-on-success" => "1")); ?>
            <?php echo modal_anchor(get_uri("offer/accept_proposal_modal_form/{$proposal_info->id}/{$proposal_info->public_key}"), '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg> <span>Aceptar</span>', array("class" => "tt-btn tt-btn-success")); ?>
        <?php endif; ?>
    </div>

    <!-- Móvil: botón con menú desplegable -->
    <div class="tt-topbar-right" style="display:none;" id="tt-mobile-menu-wrap">
        <button class="tt-menu-btn" onclick="document.getElementById('tt-mobile-dropdown').classList.toggle('open')">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            Acciones
        </button>
        <div class="tt-dropdown-menu" id="tt-mobile-dropdown">
            <?php if ($has_pdf_access): ?>
                <a href="<?php echo get_uri('offer/download_pdf/' . $proposal_info->id . '/' . $proposal_info->public_key); ?>" class="tt-btn tt-btn-outline">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Descargar PDF
                </a>
            <?php endif; ?>
            <?php if ($tiene_firma && $has_pdf_access): ?>
                <a href="<?php echo get_uri('offer/download_signed_pdf/' . $proposal_info->id . '/' . $proposal_info->public_key); ?>" class="tt-btn tt-btn-outline" style="color:#1a7f4b;border-color:#1a7f4b;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    PDF Firmado
                </a>
            <?php endif; ?>
            <?php if ($proposal_info->status === 'accepted'): ?>
                <span class="tt-status-badge tt-status-accepted" style="border-radius:8px;">✓ Propuesta aceptada</span>
            <?php elseif ($proposal_info->status === 'declined'): ?>
                <span class="tt-status-badge tt-status-rejected" style="border-radius:8px;">✗ Propuesta rechazada</span>
            <?php else: ?>
                <?php echo ajax_anchor(get_uri("offer/update_proposal_status/{$proposal_info->id}/{$proposal_info->public_key}/declined"), '✗ Rechazar', array("class" => "tt-btn tt-btn-danger", "data-reload-on-success" => "1")); ?>
                <?php echo modal_anchor(get_uri("offer/accept_proposal_modal_form/{$proposal_info->id}/{$proposal_info->public_key}"), '✓ Aceptar propuesta', array("class" => "tt-btn tt-btn-success")); ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Mostrar menú móvil solo en móvil
if (window.innerWidth <= 640) {
    document.querySelector('.tt-topbar-actions').style.display = 'none';
    document.getElementById('tt-mobile-menu-wrap').style.display = 'block';
}
// Cerrar dropdown al hacer click fuera
document.addEventListener('click', function(e) {
    var wrap = document.getElementById('tt-mobile-menu-wrap');
    if (wrap && !wrap.contains(e.target)) {
        var dd = document.getElementById('tt-mobile-dropdown');
        if (dd) dd.classList.remove('open');
    }
});
</script>

<!-- ═══ DOCUMENTO ════════════════════════════════════════════════ -->
<div class="tt-doc-wrap">
<div class="tt-doc">

    <!-- Header -->
    <div class="tt-doc-header">
        <div class="tt-doc-header-logo-block">
            <img src="<?php echo get_logo_url(); ?>" alt="Tictac Comunicación">
        </div>
        <div class="tt-doc-header-title">
            <h1>PRESUPUESTO</h1>
            <p>Propuesta económica personalizada</p>
        </div>
        <div class="tt-doc-header-company">
            <strong>Tictac Comunicación Digital SL</strong>
            C. Cruz Conde, 19, 6º 5 · 14001 Córdoba<br>
            633 33 53 90 · hola@tictac-comunicacion.es<br>
            www.tictac-comunicacion.es
        </div>
    </div>
    <div class="tt-doc-header-line"></div>

    <!-- Cuerpo -->
    <div class="tt-doc-body">

        <!-- Info grid -->
        <div class="tt-info-grid">
            <div class="tt-info-box">
                <div class="tt-info-box-header">Datos de la Propuesta</div>
                <div class="tt-info-box-body">
                    <strong>Referencia:</strong> <?php echo get_proposal_id($proposal_info->id); ?><br>
                    <strong>Fecha:</strong> <?php echo format_to_date($proposal_info->proposal_date, false); ?><br>
                    <strong>Válida hasta:</strong> <?php echo format_to_date($proposal_info->valid_until, false); ?>
                </div>
            </div>
            <div class="tt-info-box">
                <div class="tt-info-box-header">Información del Cliente</div>
                <div class="tt-info-box-body">
                    <?php
                    // Si no hay cliente real, leer de meta_data (pending_client)
                    $display_client = $client_info;
                    if (empty($client_info->id) && !empty($proposal_info->meta_data)) {
                        $meta_tmp = @unserialize($proposal_info->meta_data);
                        if ($meta_tmp && !empty($meta_tmp['pending_client'])) {
                            $pc = $meta_tmp['pending_client'];
                            $display_client = (object)[
                                'company_name' => $pc['company'] ?? '',
                                'address'      => $pc['address'] ?? '',
                                'city'         => $pc['city'] ?? '',
                                'zip'          => $pc['zip'] ?? '',
                                'vat_number'   => $pc['vat'] ?? '',
                                'country'      => '',
                            ];
                        }
                    }
                    ?>
                    <strong>Empresa:</strong> <?php echo htmlspecialchars($display_client->company_name ?? ''); ?><br>
                    <?php if (!empty($display_client->address)): ?>
                        <strong>Dirección:</strong> <?php echo htmlspecialchars($display_client->address); ?><br>
                    <?php endif; ?>
                    <?php if (!empty($display_client->city)): ?>
                        <strong>Ciudad:</strong> <?php echo htmlspecialchars($display_client->city); ?> <?php echo htmlspecialchars($display_client->zip ?? ''); ?><br>
                    <?php endif; ?>
                    <?php if (!empty($display_client->vat_number)): ?>
                        <strong>CIF/NIF:</strong> <?php echo htmlspecialchars($display_client->vat_number); ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Título sección -->
        <div class="tt-section-title">
            <h2>Propuesta Económica</h2>
        </div>

        <!-- Nota IVA -->
        <div class="tt-note-iva">
            * Los precios mostrados no incluyen IVA &nbsp;·&nbsp;
            ** El presupuesto tendrá validez hasta la fecha indicada arriba
        </div>

        <!-- Tabla artículos -->
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
                <?php foreach ($proposal_items as $item): ?>
                <tr>
                    <td>
                        <div class="item-name"><?php echo htmlspecialchars($item->title); ?></div>
                        <?php if (!empty($item->description)): ?>
                            <div class="item-desc"><?php echo $item->description; ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?php echo to_decimal_format($item->quantity); ?> <?php echo htmlspecialchars($item->unit_type ?? ''); ?></td>
                    <td><?php echo to_currency($item->rate, $proposal_total_summary->currency_symbol); ?></td>
                    <td class="item-total"><?php echo to_currency($item->total, $proposal_total_summary->currency_symbol); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <!-- Totales -->
        <?php $ts = $proposal_total_summary; ?>
        <div class="tt-totals">
            <div class="tt-totals-inner">
                <div class="tt-total-row">
                    <span>Subtotal (sin IVA)</span>
                    <span><?php echo to_currency($ts->proposal_subtotal, $ts->currency_symbol); ?></span>
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
                    <span><?php echo to_currency($ts->proposal_total, $ts->currency_symbol); ?></span>
                </div>
            </div>
        </div>

        <!-- Notas -->
        <?php if (!empty($proposal_info->note) && strip_tags($proposal_info->note) !== ''): ?>
        <div class="tt-section-title">
            <h2>Notas Adicionales</h2>
        </div>
        <div class="tt-notes">
            <?php echo custom_nl2br($proposal_info->note); ?>
        </div>
        <?php endif; ?>

        <!-- Sobre Nosotros — siempre Tictac -->
        <div class="tt-section-title" style="margin-top:28px;">
            <h2>Sobre Nosotros</h2>
        </div>
        <div class="tt-notes" style="background:#fff5f9;border-left-color:#d72173;">
            En Tictac Comunicación Digital SL desarrollamos estrategias digitales orientadas a conversión, visibilidad y crecimiento real. Cada propuesta se diseña a medida, alineada con los objetivos del cliente y basada en criterios técnicos, creativos y estratégicos.
        </div>

        <!-- RGPD — condicional por compañía -->
        <?php
        $company_id_view = intval($proposal_info->company_id ?? 2);
        $es_tress_view   = ($company_id_view === 1);
        ?>
        <div class="tt-legal">
            <div class="tt-legal-title">PROTECCIÓN DE DATOS Y CLÁUSULAS LEGALES</div>
            <?php if ($es_tress_view): ?>
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

    </div><!-- /tt-doc-body -->

    <!-- Footer -->
    <div class="tt-doc-footer">
        <strong>Tictac Comunicación Digital SL</strong><br>
        C. Cruz Conde, 19, 6º 5 · 14001 Córdoba · 633 33 53 90 ·
        <a href="mailto:hola@tictac-comunicacion.es">hola@tictac-comunicacion.es</a> ·
        <a href="https://www.tictac-comunicacion.es" target="_blank">www.tictac-comunicacion.es</a>
    </div>

</div><!-- /tt-doc -->
</div><!-- /tt-doc-wrap -->

<?php echo view('modal/index'); ?>
<?php echo view('proposals/print_proposal_helper_js'); ?>

<script>
// El CRM fija el height del body con initScrollbar — lo anulamos
document.addEventListener('DOMContentLoaded', function () {
    document.body.style.height    = 'auto';
    document.body.style.overflow  = 'auto';
    document.documentElement.style.height   = 'auto';
    document.documentElement.style.overflow = 'auto';

    // Por si hay un wrapper con scroll fijo del CRM
    var scrollbar = document.getElementById('proposal-preview-scrollbar');
    if (scrollbar) {
        scrollbar.style.height   = 'auto';
        scrollbar.style.overflow = 'visible';
    }
});
</script>

</body>
</html>