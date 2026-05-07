<?php

/**
 * Editor de Presupuestos - Crear/Editar
 */

require_once '../config.php';

$pageTitle = 'Editor de Presupuestos';
$showBackButton = true;

$editId = isset($_GET['id']) ? $_GET['id'] : null;
$presupuesto = null;

if ($editId) {
    $presupuestosFile = DATA_PATH . '/presupuestos.json';
    if (file_exists($presupuestosFile)) {
        $presupuestos = json_decode(file_get_contents($presupuestosFile), true);
        foreach ($presupuestos as $p) {
            if ($p['id'] === $editId) {
                $presupuesto = $p;
                break;
            }
        }
    }
}

$clientes = array();
$mysqli = conexionBBDD();
if ($mysqli) {
    $sql = "SELECT id, company_name, phone, address, city, zip, country, vat_number 
            FROM crm_clients 
            WHERE deleted = 0 
            ORDER BY company_name ASC";
    $result = $mysqli->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $clientes[] = $row;
        }
        $result->free();
    }
}

$articulos = getArticulosCRM();

$additionalStyles = '
<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
<style>
    .editor-container {
        background: white;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        padding: 40px;
        margin: 30px 0;
    }
    .form-section { margin-bottom: 40px; }
    .form-section h3 {
        color: ' . BRAND_COLOR . ';
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid ' . BRAND_COLOR . ';
    }
    .form-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 20px;
    }
    .form-group { display: flex; flex-direction: column; }
    .form-group label { font-weight: 600; margin-bottom: 8px; color: #333; }
    .form-group input,
    .form-group select,
    .form-group textarea {
        padding: 12px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        font-size: 14px;
        transition: all 0.3s;
    }
    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: ' . BRAND_COLOR . ';
    }
    .form-group textarea {
        min-height: 100px;
        resize: vertical;
        font-family: inherit;
    }

    /* Email tags */
    .email-tags-container {
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        padding: 8px 12px;
        min-height: 48px;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 6px;
        cursor: text;
        transition: border-color 0.3s;
        background: white;
    }
    .email-tags-container:focus-within { border-color: ' . BRAND_COLOR . '; }
    .email-tag {
        background: #ffe8f3;
        color: ' . BRAND_COLOR . ';
        border: 1px solid ' . BRAND_COLOR . ';
        border-radius: 20px;
        padding: 3px 10px 3px 12px;
        font-size: 13px;
        display: flex;
        align-items: center;
        gap: 6px;
        white-space: nowrap;
    }
    .email-tag .remove-tag {
        cursor: pointer;
        font-size: 16px;
        line-height: 1;
        opacity: 0.6;
        transition: opacity 0.2s;
    }
    .email-tag .remove-tag:hover { opacity: 1; }
    .email-tags-input {
        border: none !important;
        outline: none !important;
        padding: 4px 0 !important;
        min-width: 180px;
        flex: 1;
        font-size: 14px;
        background: transparent;
    }
    .email-hint { font-size: 11px; color: #999; margin-top: 4px; }

    /* WYSIWYG global (Detalles / Notas) */
    .wysiwyg-container {
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        overflow: hidden;
        transition: border-color 0.3s;
    }
    .wysiwyg-container:focus-within { border-color: ' . BRAND_COLOR . '; }
    .wysiwyg-container .ql-toolbar {
        border: none;
        border-bottom: 1px solid #e0e0e0;
        background: #fafafa;
    }
    .wysiwyg-container .ql-container {
        border: none;
        min-height: 120px;
        font-size: 14px;
        font-family: "Helvetica Neue", Arial, sans-serif;
    }
    .wysiwyg-container .ql-editor { min-height: 120px; padding: 12px; }
    .wysiwyg-container .ql-editor.ql-blank::before { font-style: normal; color: #999; }

    /* WYSIWYG descripción de item */
    .item-desc-wysiwyg {
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        overflow: hidden;
        margin-top: 10px;
        transition: border-color 0.3s;
    }
    .item-desc-wysiwyg:focus-within { border-color: ' . BRAND_COLOR . '; }
    .item-desc-wysiwyg .ql-toolbar {
        border: none;
        border-bottom: 1px solid #e0e0e0;
        background: #fafafa;
        padding: 4px 8px;
    }
    .item-desc-wysiwyg .ql-container {
        border: none;
        min-height: 80px;
        font-size: 13px;
        font-family: "Helvetica Neue", Arial, sans-serif;
    }
    .item-desc-wysiwyg .ql-editor { min-height: 80px; padding: 8px 10px; }
    .item-desc-wysiwyg .ql-editor.ql-blank::before {
        font-style: normal;
        color: #999;
        font-size: 13px;
    }

    /* Searchable select */
    .searchable-select-wrapper { position: relative; width: 100%; }
    .searchable-select-wrapper .ss-main {
        display: flex;
        align-items: center;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        padding: 10px 12px;
        cursor: pointer;
        background: white;
        min-height: 44px;
        transition: border-color 0.3s;
        position: relative;
    }
    .searchable-select-wrapper .ss-main:focus-within,
    .searchable-select-wrapper .ss-main.ss-open { border-color: ' . BRAND_COLOR . '; }
    .searchable-select-wrapper .ss-main .ss-selected-text {
        flex: 1;
        color: #333;
        font-size: 14px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .searchable-select-wrapper .ss-main .ss-selected-text.ss-placeholder { color: #999; }
    .searchable-select-wrapper .ss-main .ss-arrow {
        width: 0; height: 0;
        border-left: 5px solid transparent;
        border-right: 5px solid transparent;
        border-top: 5px solid #999;
        margin-left: 8px;
        transition: transform 0.2s;
    }
    .searchable-select-wrapper .ss-main.ss-open .ss-arrow { transform: rotate(180deg); }
    .searchable-select-wrapper .ss-dropdown {
        display: none;
        position: absolute;
        top: 100%;
        left: 0; right: 0;
        background: white;
        border: 2px solid ' . BRAND_COLOR . ';
        border-top: none;
        border-radius: 0 0 8px 8px;
        z-index: 1000;
        max-height: 280px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    .searchable-select-wrapper .ss-dropdown.ss-open { display: block; }
    .searchable-select-wrapper .ss-search {
        padding: 10px;
        border-bottom: 1px solid #eee;
        position: sticky;
        top: 0;
        background: #fafafa;
        z-index: 1;
    }
    .searchable-select-wrapper .ss-search input {
        width: 100%;
        padding: 8px 10px;
        border: 1px solid #ddd;
        border-radius: 5px;
        font-size: 13px;
        box-sizing: border-box;
        outline: none;
    }
    .searchable-select-wrapper .ss-search input:focus { border-color: ' . BRAND_COLOR . '; }
    .searchable-select-wrapper .ss-options { max-height: 210px; overflow-y: auto; padding: 4px 0; }
    .searchable-select-wrapper .ss-option {
        padding: 10px 12px;
        cursor: pointer;
        font-size: 13px;
        color: #333;
        transition: background 0.15s;
        border-bottom: 1px solid #f5f5f5;
    }
    .searchable-select-wrapper .ss-option:last-child { border-bottom: none; }
    .searchable-select-wrapper .ss-option:hover,
    .searchable-select-wrapper .ss-option.ss-highlighted {
        background: #fff0f7;
        color: ' . BRAND_COLOR . ';
    }
    .searchable-select-wrapper .ss-option.ss-selected { background: #ffe8f3; font-weight: 600; }
    .searchable-select-wrapper .ss-option .ss-option-price {
        float: right;
        color: ' . BRAND_COLOR . ';
        font-weight: 600;
        font-size: 12px;
    }
    .searchable-select-wrapper .ss-option .ss-option-sub {
        display: block;
        font-size: 11px;
        color: #888;
        margin-top: 2px;
    }
    .searchable-select-wrapper .ss-no-results {
        padding: 12px;
        text-align: center;
        color: #999;
        font-size: 13px;
        font-style: italic;
    }

    /* Items */
    .items-section {
        background: #f9f9f9;
        padding: 20px;
        border-radius: 8px;
        margin-top: 20px;
    }
    .item-row {
        background: white;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 15px;
        display: grid;
        grid-template-columns: 2fr 1fr 1fr 60px;
        gap: 15px;
        align-items: start;
    }
    .item-row input,
    .item-row select {
        padding: 10px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        font-size: 14px;
    }
    .precio-original-wrap { position: relative; margin-top: 8px; }
    .precio-original-wrap label { font-size: 11px; color: #888; margin-bottom: 4px; }
    .precio-original-wrap input { border-color: #f0c0d0 !important; }
    .precio-original-preview { font-size: 11px; color: #999; margin-top: 3px; text-decoration: line-through; }

    .btn-remove {
        background: #dc3545;
        color: white;
        border: none;
        padding: 10px;
        border-radius: 8px;
        cursor: pointer;
        font-size: 18px;
        transition: all 0.3s;
        align-self: start;
        margin-top: 28px;
    }
    .btn-remove:hover { background: #c82333; }

    .btn-add {
        background: ' . ACCENT_COLOR . ';
        color: #333;
        border: none;
        padding: 12px 25px;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 600;
        margin-top: 15px;
        transition: all 0.3s;
    }
    .btn-add:hover { opacity: 0.8; }

    .totals-section { background: #f0f0f0; padding: 20px; border-radius: 8px; margin-top: 30px; }
    .total-row { display: flex; justify-content: space-between; padding: 10px 0; font-size: 16px; }
    .total-row.final {
        border-top: 3px solid ' . BRAND_COLOR . ';
        margin-top: 10px;
        padding-top: 15px;
        font-size: 24px;
        font-weight: bold;
        color: ' . BRAND_COLOR . ';
    }

    .actions-bar {
        display: flex;
        gap: 15px;
        justify-content: flex-end;
        margin-top: 40px;
        padding-top: 20px;
        border-top: 2px solid #e0e0e0;
    }
    .btn {
        padding: 15px 30px;
        border-radius: 50px;
        font-weight: 600;
        font-size: 16px;
        border: none;
        cursor: pointer;
        transition: all 0.3s;
        text-decoration: none;
        display: inline-block;
    }
    .btn-secondary { background: #6c757d; color: white; }
    .btn-secondary:hover { background: #5a6268; }
    .btn-primary { background: ' . BRAND_COLOR . '; color: white; }
    .btn-primary:hover { background: ' . BRAND_COLOR_DARK . '; transform: translateY(-2px); }
    .btn-success { background: #28a745; color: white; }
    .btn-success:hover { background: #218838; }
    .item-info { font-size: 11px; color: #666; margin-top: 5px; }

    @media (max-width: 768px) {
        .item-row { grid-template-columns: 1fr; }
        .actions-bar { flex-direction: column; }
        .btn { width: 100%; }
    }
</style>
';

include '../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1><?php echo $editId ? '✏️ Editar Presupuesto' : '📝 Nuevo Presupuesto'; ?></h1>
        <p>Introduce los datos manualmente o selecciona del catálogo</p>
    </div>

    <form id="presupuestoForm" class="editor-container">
        <?php if ($editId): ?>
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($editId); ?>">
        <?php endif; ?>

        <!-- DATOS DEL PRESUPUESTO -->
        <div class="form-section">
            <h3>📊 Datos de la Propuesta</h3>
            <div class="form-row">
                <div class="form-group">
                    <label>Fecha de la propuesta *</label>
                    <input type="date" name="fecha_propuesta" required
                        value="<?php echo $presupuesto ? $presupuesto['fecha_propuesta'] : date('Y-m-d'); ?>">
                </div>
                <div class="form-group">
                    <label>Válido hasta *</label>
                    <input type="date" name="valido_hasta" required
                        value="<?php echo $presupuesto ? $presupuesto['valido_hasta'] : date('Y-m-d', strtotime('+30 days')); ?>">
                </div>
            </div>
        </div>

        <!-- DATOS DEL CLIENTE -->
        <div class="form-section">
            <h3>👤 Información del Cliente</h3>
            <div class="form-row">
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label>Seleccionar Cliente del CRM (opcional)</label>
                    <select name="cliente_id" id="clienteSelect" style="display:none;">
                        <option value="">-- Opcional: buscar en CRM --</option>
                        <?php foreach ($clientes as $cliente): ?>
                            <option value="<?php echo htmlspecialchars($cliente['id']); ?>"
                                data-nombre="<?php echo htmlspecialchars($cliente['company_name'] ?? ''); ?>"
                                data-telefono="<?php echo htmlspecialchars($cliente['phone'] ?? ''); ?>"
                                data-direccion="<?php echo htmlspecialchars($cliente['address'] ?? ''); ?>"
                                data-ciudad="<?php echo htmlspecialchars($cliente['city'] ?? ''); ?>"
                                data-cp="<?php echo htmlspecialchars($cliente['zip'] ?? ''); ?>"
                                data-pais="<?php echo htmlspecialchars($cliente['country'] ?? ''); ?>"
                                data-cif="<?php echo htmlspecialchars($cliente['vat_number'] ?? ''); ?>"
                                <?php echo ($presupuesto && isset($presupuesto['cliente_id']) && $presupuesto['cliente_id'] == $cliente['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cliente['company_name'] ?? ''); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="searchable-select-wrapper" id="clienteSearchWrapper">
                        <div class="ss-main" id="clienteSSmain">
                            <span class="ss-selected-text ss-placeholder" id="clienteSStext">-- Opcional: buscar cliente del CRM --</span>
                            <div class="ss-arrow"></div>
                        </div>
                        <div class="ss-dropdown" id="clienteSS-dropdown">
                            <div class="ss-search"><input type="text" id="clienteSSsearch" placeholder="Buscar cliente..." autocomplete="off"></div>
                            <div class="ss-options" id="clienteSSoptions"></div>
                        </div>
                    </div>
                    <small style="color: #666; margin-top: 5px; display: block;">Puedes buscar un cliente del CRM o introducir los datos manualmente abajo</small>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Nombre/Empresa *</label>
                    <input type="text" name="cliente_nombre" id="clienteNombre" required
                        placeholder="Introduce el nombre del cliente"
                        value="<?php echo $presupuesto ? htmlspecialchars($presupuesto['cliente_nombre'] ?? '') : ''; ?>">
                </div>
                <div class="form-group" style="grid-column: span 2;">
                    <label>Email(s) del cliente *</label>
                    <input type="hidden" name="cliente_email" id="clienteEmailHidden"
                        value="<?php echo $presupuesto ? htmlspecialchars($presupuesto['cliente_email'] ?? '') : ''; ?>">
                    <div class="email-tags-container" id="emailTagsContainer">
                        <input type="text" class="email-tags-input" id="emailTagsInput"
                            placeholder="Escribe un email y pulsa Enter o coma..."
                            autocomplete="off">
                    </div>
                    <span class="email-hint">Puedes añadir varios emails. Pulsa <strong>Enter</strong> o <strong>,</strong> para añadir cada uno.</span>
                </div>
                <div class="form-group">
                    <label>Teléfono</label>
                    <input type="text" name="cliente_telefono" id="clienteTelefono"
                        placeholder="Teléfono del cliente"
                        value="<?php echo $presupuesto ? htmlspecialchars($presupuesto['cliente_telefono'] ?? '') : ''; ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label>Dirección</label>
                    <input type="text" name="cliente_direccion" id="clienteDireccion"
                        placeholder="Dirección completa"
                        value="<?php echo $presupuesto ? htmlspecialchars($presupuesto['cliente_direccion'] ?? '') : ''; ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Ciudad</label>
                    <input type="text" name="cliente_ciudad" id="clienteCiudad"
                        placeholder="Ciudad"
                        value="<?php echo $presupuesto ? htmlspecialchars($presupuesto['cliente_ciudad'] ?? '') : ''; ?>">
                </div>
                <div class="form-group">
                    <label>Código Postal</label>
                    <input type="text" name="cliente_cp" id="clienteCp"
                        placeholder="CP"
                        value="<?php echo $presupuesto ? htmlspecialchars($presupuesto['cliente_cp'] ?? '') : ''; ?>">
                </div>
                <div class="form-group">
                    <label>País</label>
                    <input type="text" name="cliente_pais" id="clientePais"
                        placeholder="País"
                        value="<?php echo $presupuesto ? htmlspecialchars($presupuesto['cliente_pais'] ?? '') : ''; ?>">
                </div>
                <div class="form-group">
                    <label>CIF/NIF</label>
                    <input type="text" name="cliente_cif" id="clienteCif"
                        placeholder="CIF/NIF"
                        value="<?php echo $presupuesto ? htmlspecialchars($presupuesto['cliente_cif'] ?? '') : ''; ?>">
                </div>
            </div>
        </div>

        <!-- ARTÍCULOS/SERVICIOS -->
        <div class="form-section">
            <h3>📦 Artículos y Servicios</h3>
            <div class="items-section" id="itemsContainer">
                <?php
                $existingItems = ($presupuesto && isset($presupuesto['items'])) ? $presupuesto['items'] : [null];
                foreach ($existingItems as $index => $item):
                ?>
                    <div class="item-row" data-item-index="<?php echo $index; ?>">
                        <div class="form-group">
                            <label>Artículo/Servicio</label>
                            <select class="articulo-select" data-index="<?php echo $index; ?>" style="display:none;">
                                <option value="">-- Opcional: seleccionar del catálogo --</option>
                                <?php foreach ($articulos as $art): ?>
                                    <option value="<?php echo htmlspecialchars(json_encode($art)); ?>"
                                        <?php echo ($item && isset($item['nombre']) && ($item['nombre'] ?? '') == ($art['title'] ?? '')) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($art['title'] ?? ''); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="searchable-select-wrapper" id="artSSWrapper_<?php echo $index; ?>">
                                <div class="ss-main" id="artSSmain_<?php echo $index; ?>">
                                    <span class="ss-selected-text ss-placeholder" id="artSStext_<?php echo $index; ?>">-- Opcional: buscar en catálogo --</span>
                                    <div class="ss-arrow"></div>
                                </div>
                                <div class="ss-dropdown" id="artSS-dropdown_<?php echo $index; ?>">
                                    <div class="ss-search"><input type="text" id="artSSsearch_<?php echo $index; ?>" placeholder="Buscar artículo..." autocomplete="off"></div>
                                    <div class="ss-options" id="artSSoptions_<?php echo $index; ?>"></div>
                                </div>
                            </div>
                            <input type="text" name="items[<?php echo $index; ?>][nombre]"
                                placeholder="Nombre del servicio" required class="item-nombre"
                                style="margin-top: 10px;"
                                value="<?php echo $item ? htmlspecialchars($item['nombre'] ?? '') : ''; ?>">
                            <!-- Hidden + WYSIWYG para la descripción -->
                            <input type="hidden"
                                name="items[<?php echo $index; ?>][descripcion]"
                                class="item-descripcion-hidden"
                                value="<?php echo $item ? htmlspecialchars($item['descripcion'] ?? '') : ''; ?>">
                            <div class="item-desc-wysiwyg" id="descWysiwyg_<?php echo $index; ?>"></div>
                        </div>

                        <div class="form-group">
                            <label>Cantidad</label>
                            <input type="number" name="items[<?php echo $index; ?>][cantidad]"
                                placeholder="1" step="0.01" required class="item-cantidad"
                                value="<?php echo $item ? ($item['cantidad'] ?? 1) : 1; ?>">
                            <input type="text" name="items[<?php echo $index; ?>][unidad]"
                                placeholder="Tipo (ej: Mensual, Único)" class="item-unidad"
                                style="margin-top: 10px; padding: 10px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 13px;"
                                value="<?php echo $item ? htmlspecialchars($item['unidad'] ?? '') : ''; ?>">
                        </div>

                        <div class="form-group">
                            <label>Precio con descuento (€)</label>
                            <input type="number" name="items[<?php echo $index; ?>][precio]"
                                placeholder="0.00" step="0.01" required class="item-precio"
                                value="<?php echo $item ? ($item['precio'] ?? '') : ''; ?>">
                            <div class="precio-original-wrap">
                                <label>Precio original sin descuento (€) — opcional</label>
                                <input type="number" name="items[<?php echo $index; ?>][precio_original]"
                                    placeholder="Dejar vacío si no hay descuento" step="0.01" class="item-precio-original"
                                    value="<?php echo $item ? htmlspecialchars($item['precio_original'] ?? '') : ''; ?>">
                                <div class="precio-original-preview" id="previoOriginal_<?php echo $index; ?>"
                                    style="display:<?php echo ($item && !empty($item['precio_original'])) ? 'block' : 'none'; ?>">
                                    Antes: <?php echo ($item && !empty($item['precio_original'])) ? number_format($item['precio_original'], 2, ',', '.') . ' €' : ''; ?>
                                </div>
                            </div>
                            <div class="item-info">
                                <strong>Total: <span class="item-total-display"><?php echo $item ? number_format(($item['cantidad'] ?? 0) * ($item['precio'] ?? 0), 2) . ' €' : '0.00 €'; ?></span></strong>
                            </div>
                            <input type="hidden" class="item-total" value="<?php echo $item ? (($item['cantidad'] ?? 0) * ($item['precio'] ?? 0)) : 0; ?>">
                        </div>

                        <button type="button" class="btn-remove" onclick="removeItem(this)">🗑️</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="btn-add" onclick="addItem()">+ Añadir Artículo</button>
        </div>

        <!-- IMPUESTOS -->
        <div class="form-section">
            <h3>💰 Impuestos</h3>
            <div class="form-row">
                <div class="form-group">
                    <label>IVA (%)</label>
                    <input type="number" name="iva" id="iva" step="0.01"
                        value="<?php echo $presupuesto ? ($presupuesto['iva'] ?? 21) : 21; ?>" min="0">
                </div>
                <div class="form-group">
                    <label>Segundo Impuesto (%) - Opcional</label>
                    <input type="number" name="segundo_impuesto" id="segundoImpuesto" step="0.01"
                        value="<?php echo $presupuesto ? ($presupuesto['segundo_impuesto'] ?? 0) : 0; ?>" min="0">
                </div>
            </div>
        </div>

        <!-- DETALLES PROPUESTA -->
        <div class="form-section">
            <h3>📋 Detalles de la Propuesta</h3>
            <div class="form-group">
                <label>Detalles (opcional) — Aparecerá antes de la tabla de artículos en el PDF</label>
                <input type="hidden" name="detalles_propuesta" id="detallesPropuestaHidden"
                    value="<?php echo $presupuesto ? htmlspecialchars($presupuesto['detalles_propuesta'] ?? '') : ''; ?>">
                <div class="wysiwyg-container">
                    <div id="detallesEditor"></div>
                </div>
            </div>
        </div>

        <!-- NOTAS ADICIONALES -->
        <div class="form-section">
            <h3>📝 Notas Adicionales</h3>
            <div class="form-group">
                <label>Notas (opcional) — Aparecerá al final del PDF</label>
                <input type="hidden" name="notas" id="notasHidden"
                    value="<?php echo $presupuesto ? htmlspecialchars($presupuesto['notas'] ?? '') : ''; ?>">
                <div class="wysiwyg-container">
                    <div id="notasEditor"></div>
                </div>
            </div>
        </div>

        <!-- TOTALES -->
        <div class="totals-section">
            <div class="total-row">
                <span>Subtotal:</span>
                <span id="subtotal">0,00 €</span>
            </div>
            <div class="total-row">
                <span>IVA (<span id="ivaPercent">21</span>%):</span>
                <span id="ivaAmount">0,00 €</span>
            </div>
            <div class="total-row" id="segundoImpuestoRow" style="display: none;">
                <span>Segundo Impuesto (<span id="segundoImpuestoPercent">0</span>%):</span>
                <span id="segundoImpuestoAmount">0,00 €</span>
            </div>
            <div class="total-row final">
                <span>TOTAL:</span>
                <span id="total">0,00 €</span>
            </div>
        </div>

        <!-- BOTONES -->
        <div class="actions-bar">
            <a href="index.php" class="btn btn-secondary">Cancelar</a>
            <button type="submit" name="action" value="guardar" class="btn btn-primary">💾 Guardar Borrador</button>
            <button type="submit" name="action" value="guardar_pdf" class="btn btn-success">📄 Guardar y Descargar PDF</button>
        </div>
    </form>
</div>

<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>

<?php
$articulosJson     = json_encode($articulos, JSON_UNESCAPED_UNICODE);
$clientesJson      = json_encode($clientes,  JSON_UNESCAPED_UNICODE);
$selectedClienteId = $presupuesto && isset($presupuesto['cliente_id']) ? $presupuesto['cliente_id'] : '';
$emailsIniciales   = $presupuesto ? ($presupuesto['cliente_email'] ?? '') : '';

$additionalScripts = '
<script>
// ============================================================
// WYSIWYG — Detalles y Notas (secciones globales)
// ============================================================
const quillToolbar = [
    ["bold", "italic", "underline"],
    [{ "list": "ordered" }, { "list": "bullet" }],
    ["clean"]
];

const detallesQuill = new Quill("#detallesEditor", {
    theme: "snow",
    modules: { toolbar: quillToolbar },
    placeholder: "Escribe los detalles de la propuesta..."
});

const notasQuill = new Quill("#notasEditor", {
    theme: "snow",
    modules: { toolbar: quillToolbar },
    placeholder: "Condiciones de pago, garantías, observaciones..."
});

(function() {
    var dv = document.getElementById("detallesPropuestaHidden").value;
    if (dv && dv.trim() !== "") detallesQuill.root.innerHTML = dv;
    var nv = document.getElementById("notasHidden").value;
    if (nv && nv.trim() !== "") notasQuill.root.innerHTML = nv;
})();

detallesQuill.on("text-change", function() {
    var html = detallesQuill.root.innerHTML;
    if (detallesQuill.getText().trim() === "") html = "";
    document.getElementById("detallesPropuestaHidden").value = html;
});

notasQuill.on("text-change", function() {
    var html = notasQuill.root.innerHTML;
    if (notasQuill.getText().trim() === "") html = "";
    document.getElementById("notasHidden").value = html;
});

// ============================================================
// WYSIWYG — Descripción de cada item
// ============================================================
window.itemDescQuills = {};

const itemDescToolbar = [
    ["bold", "italic", "underline"],
    [{ "list": "ordered" }, { "list": "bullet" }],
    ["clean"]
];

function initItemDescQuill(index) {
    const container = document.getElementById("descWysiwyg_" + index);
    if (!container || window.itemDescQuills[index]) return;

    const q = new Quill(container, {
        theme: "snow",
        modules: { toolbar: itemDescToolbar },
        placeholder: "Descripción del servicio..."
    });

    window.itemDescQuills[index] = q;

    // Cargar valor inicial desde el hidden
    const row = document.querySelector("[data-item-index=\"" + index + "\"]");
    if (row) {
        const hidden = row.querySelector(".item-descripcion-hidden");
        if (hidden && hidden.value && hidden.value.trim() !== "") {
            const val = hidden.value;
            if (val.indexOf("<") !== -1) {
                q.root.innerHTML = val;
            } else {
                q.setText(val);
            }
        }
    }

    // Sincronizar cambios → hidden
    q.on("text-change", function() {
        const row2 = document.querySelector("[data-item-index=\"" + index + "\"]");
        if (!row2) return;
        const hidden2 = row2.querySelector(".item-descripcion-hidden");
        if (!hidden2) return;
        let html = q.root.innerHTML;
        if (q.getText().trim() === "") html = "";
        hidden2.value = html;
    });
}

// Inicializar Quills de descripción para items ya existentes en la página
document.querySelectorAll("[data-item-index]").forEach(function(row) {
    initItemDescQuill(parseInt(row.getAttribute("data-item-index")));
});

// ============================================================
// EMAIL TAGS
// ============================================================
(function() {
    const container = document.getElementById("emailTagsContainer");
    const input     = document.getElementById("emailTagsInput");
    const hidden    = document.getElementById("clienteEmailHidden");
    let emails = [];

    const initialEmails = ' . json_encode($emailsIniciales) . ';
    if (initialEmails && initialEmails.trim() !== "") {
        initialEmails.split(",").forEach(function(e) {
            var em = e.trim();
            if (em) addEmailTag(em);
        });
    }

    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }
    function updateHidden() { hidden.value = emails.join(","); }

    function addEmailTag(email) {
        email = email.trim().toLowerCase();
        if (!email || emails.includes(email)) return;
        if (!isValidEmail(email)) {
            input.style.borderColor = "#dc3545";
            setTimeout(function() { input.style.borderColor = ""; }, 800);
            return;
        }
        emails.push(email);
        const tag = document.createElement("div");
        tag.className = "email-tag";
        tag.dataset.email = email;
        tag.innerHTML = escapeHtml(email) + \'<span class="remove-tag" title="Eliminar">\u00d7</span>\';
        tag.querySelector(".remove-tag").addEventListener("click", function() {
            emails = emails.filter(function(e) { return e !== email; });
            tag.remove();
            updateHidden();
        });
        container.insertBefore(tag, input);
        input.value = "";
        updateHidden();
    }

    input.addEventListener("keydown", function(e) {
        if (e.key === "Enter" || e.key === ",") {
            e.preventDefault();
            addEmailTag(input.value);
        } else if (e.key === "Backspace" && input.value === "" && emails.length > 0) {
            var lastEmail = emails[emails.length - 1];
            var lastTag = container.querySelector(".email-tag[data-email=\"" + lastEmail + "\"]");
            if (lastTag) { emails.pop(); lastTag.remove(); updateHidden(); }
        }
    });
    input.addEventListener("blur", function() { if (input.value.trim()) addEmailTag(input.value); });
    container.addEventListener("click", function() { input.focus(); });

    window.setClienteEmails = function(emailStr) {
        container.querySelectorAll(".email-tag").forEach(function(t) { t.remove(); });
        emails = [];
        updateHidden();
        if (emailStr) {
            emailStr.split(",").forEach(function(e) { addEmailTag(e.trim()); });
        }
    };
})();

// ============================================================
// DATOS
// ============================================================
let itemCounter = ' . count($existingItems) . ';
const articulosData   = ' . $articulosJson . ';
const clientesData    = ' . $clientesJson . ';
const selectedClienteId = "' . $selectedClienteId . '";

// ============================================================
// SEARCHABLE SELECT ENGINE
// ============================================================
function initSearchableSelect(config) {
    const main             = document.getElementById(config.mainId);
    const dropdown         = document.getElementById(config.dropdownId);
    const search           = document.getElementById(config.searchId);
    const optionsContainer = document.getElementById(config.optionsId);
    const textSpan         = document.getElementById(config.textId);

    let isOpen = false;
    let highlightedIndex = -1;
    let filteredItems = [];

    function renderOptions(filter) {
        filter = (filter || "").toLowerCase().trim();
        filteredItems = config.items.filter(function(item) {
            var label = (config.getLabel ? config.getLabel(item) : (item.label || "")).toLowerCase();
            var sub   = (config.getSub   ? config.getSub(item)   : (item.sub   || "")).toLowerCase();
            return label.includes(filter) || sub.includes(filter);
        });
        if (filteredItems.length === 0) {
            optionsContainer.innerHTML = \'<div class="ss-no-results">No se encontraron resultados</div>\';
            return;
        }
        optionsContainer.innerHTML = filteredItems.map(function(item, i) {
            var label      = config.getLabel    ? config.getLabel(item)    : item.label;
            var sub        = config.getSub      ? config.getSub(item)      : (item.sub || "");
            var price      = config.getPrice    ? config.getPrice(item)    : "";
            var isSelected = config.isSelected  ? config.isSelected(item)  : false;
            return "<div class=\"ss-option " + (isSelected ? "ss-selected" : "") + "\" data-index=\"" + i + "\" onmousedown=\"event.preventDefault()\">" +
                (price ? "<span class=\"ss-option-price\">" + price + "</span>" : "") +
                "<strong>" + escapeHtml(label) + "</strong>" +
                (sub ? "<span class=\"ss-option-sub\">" + escapeHtml(sub) + "</span>" : "") +
                "</div>";
        }).join("");
        optionsContainer.querySelectorAll(".ss-option").forEach(function(opt, i) {
            opt.addEventListener("click", function() { selectItem(i); });
        });
        highlightedIndex = -1;
    }

    function selectItem(index) {
        var item = filteredItems[index];
        if (!item) return;
        var label = config.getLabel ? config.getLabel(item) : item.label;
        textSpan.textContent = label;
        textSpan.classList.remove("ss-placeholder");
        close();
        if (config.onSelect) config.onSelect(item);
    }

    function open() {
        if (isOpen) return;
        isOpen = true;
        main.classList.add("ss-open");
        dropdown.classList.add("ss-open");
        search.value = "";
        renderOptions("");
        search.focus();
    }
    function close() {
        isOpen = false;
        main.classList.remove("ss-open");
        dropdown.classList.remove("ss-open");
    }
    function toggle() { isOpen ? close() : open(); }

    main.addEventListener("click", function(e) { e.stopPropagation(); toggle(); });
    search.addEventListener("input", function(e) { renderOptions(e.target.value); });
    search.addEventListener("keydown", function(e) {
        var opts = optionsContainer.querySelectorAll(".ss-option");
        if (e.key === "ArrowDown") {
            e.preventDefault();
            highlightedIndex = Math.min(highlightedIndex + 1, opts.length - 1);
            opts.forEach(function(o, i) { o.classList.toggle("ss-highlighted", i === highlightedIndex); });
            if (opts[highlightedIndex]) opts[highlightedIndex].scrollIntoView({block:"nearest"});
        } else if (e.key === "ArrowUp") {
            e.preventDefault();
            highlightedIndex = Math.max(highlightedIndex - 1, 0);
            opts.forEach(function(o, i) { o.classList.toggle("ss-highlighted", i === highlightedIndex); });
            if (opts[highlightedIndex]) opts[highlightedIndex].scrollIntoView({block:"nearest"});
        } else if (e.key === "Enter" && highlightedIndex >= 0) {
            e.preventDefault();
            selectItem(highlightedIndex);
        } else if (e.key === "Escape") {
            close();
        }
    });
    document.addEventListener("click", function(e) {
        if (main && !main.closest(".searchable-select-wrapper").contains(e.target)) close();
    });

    return { open: open, close: close, selectItem: selectItem, renderOptions: renderOptions };
}

// ============================================================
// CLIENTE SEARCHABLE SELECT
// ============================================================
const clienteSS = initSearchableSelect({
    mainId:    "clienteSSmain",
    dropdownId:"clienteSS-dropdown",
    searchId:  "clienteSSsearch",
    optionsId: "clienteSSoptions",
    textId:    "clienteSStext",
    items:     clientesData,
    getLabel:  function(c) { return c.company_name || ""; },
    getSub:    function(c) { return (c.city ? c.city : "") + (c.phone ? " · " + c.phone : ""); },
    isSelected:function(c) { return String(c.id) === String(selectedClienteId); },
    onSelect:  function(cliente) {
        document.getElementById("clienteSelect").value    = cliente.id;
        document.getElementById("clienteNombre").value   = cliente.company_name || "";
        document.getElementById("clienteDireccion").value = cliente.address || "";
        document.getElementById("clienteCiudad").value   = cliente.city    || "";
        document.getElementById("clienteCp").value       = cliente.zip     || "";
        document.getElementById("clientePais").value     = cliente.country || "";
        document.getElementById("clienteCif").value      = cliente.vat_number || "";
        fetch("get_contacto.php?client_id=" + cliente.id)
            .then(function(r) { return r.json(); })
            .then(function(contactos) {
                if (contactos && contactos.length > 0) {
                    var principal = contactos.find(function(c) { return c.is_primary_contact === "1"; });
                    var contacto  = principal || contactos[0];
                    if (window.setClienteEmails) window.setClienteEmails(contacto.email || "");
                    document.getElementById("clienteTelefono").value = contacto.phone || contacto.alternative_phone || "";
                } else {
                    if (window.setClienteEmails) window.setClienteEmails("");
                    document.getElementById("clienteTelefono").value = "";
                }
            })
            .catch(function() {
                if (window.setClienteEmails) window.setClienteEmails("");
            });
    }
});

if (selectedClienteId) {
    var preSelected = clientesData.find(function(c) { return String(c.id) === String(selectedClienteId); });
    if (preSelected) {
        document.getElementById("clienteSStext").textContent = preSelected.company_name || "";
        document.getElementById("clienteSStext").classList.remove("ss-placeholder");
    }
}

// ============================================================
// ARTÍCULOS SEARCHABLE SELECT
// ============================================================
const articulosSS = {};

function initArticuloSS(index) {
    articulosSS[index] = initSearchableSelect({
        mainId:    "artSSmain_"     + index,
        dropdownId:"artSS-dropdown_" + index,
        searchId:  "artSSsearch_"  + index,
        optionsId: "artSSoptions_" + index,
        textId:    "artSStext_"    + index,
        items:     articulosData,
        getLabel:  function(a) { return a.title || ""; },
        getSub:    function(a) { return (a.category_title || "") + (a.unit_type ? " · " + a.unit_type : ""); },
        getPrice:  function(a) { return a.rate != null ? parseFloat(a.rate).toFixed(2) + " €" : ""; },
        onSelect:  function(articulo) {
            var row = document.querySelector("[data-item-index=\"" + index + "\"]");
            if (!row) return;

            // Nombre y precio
            row.querySelector(".item-nombre").value = articulo.title || "";
            row.querySelector(".item-precio").value = parseFloat(articulo.rate || 0).toFixed(2);
            row.querySelector(".item-unidad").value = articulo.unit_type || "";

            // Descripción → hidden + Quill
            var descHidden = row.querySelector(".item-descripcion-hidden");
            var descTxt    = articulo.description || "";
            descHidden.value = descTxt;
            if (window.itemDescQuills && window.itemDescQuills[index]) {
                var q = window.itemDescQuills[index];
                if (descTxt.indexOf("<") !== -1) {
                    q.root.innerHTML = descTxt;
                } else {
                    if (descTxt) { q.setText(descTxt); } else { q.setText(""); }
                }
            }

            calculateTotals();
        }
    });
}

document.querySelectorAll("[data-item-index]").forEach(function(row) {
    var idx = parseInt(row.getAttribute("data-item-index"));
    initArticuloSS(idx);
    attachPrecioOriginalListener(row, idx);
});

// ============================================================
// ADD / REMOVE ITEMS
// ============================================================
function addItem() {
    const container = document.getElementById("itemsContainer");
    const idx = itemCounter;

    const newItem = document.createElement("div");
    newItem.className = "item-row";
    newItem.setAttribute("data-item-index", idx);
    newItem.innerHTML =
        "<div class=\"form-group\">" +
            "<label>Art\u00edculo/Servicio</label>" +
            "<div class=\"searchable-select-wrapper\" id=\"artSSWrapper_" + idx + "\">" +
                "<div class=\"ss-main\" id=\"artSSmain_" + idx + "\">" +
                    "<span class=\"ss-selected-text ss-placeholder\" id=\"artSStext_" + idx + "\">-- Opcional: buscar en cat\u00e1logo --</span>" +
                    "<div class=\"ss-arrow\"></div>" +
                "</div>" +
                "<div class=\"ss-dropdown\" id=\"artSS-dropdown_" + idx + "\">" +
                    "<div class=\"ss-search\"><input type=\"text\" id=\"artSSsearch_" + idx + "\" placeholder=\"Buscar art\u00edculo...\" autocomplete=\"off\"></div>" +
                    "<div class=\"ss-options\" id=\"artSSoptions_" + idx + "\"></div>" +
                "</div>" +
            "</div>" +
            "<input type=\"text\" name=\"items[" + idx + "][nombre]\" placeholder=\"Nombre del servicio\" required class=\"item-nombre\" style=\"margin-top:10px;\">" +
            "<input type=\"hidden\" name=\"items[" + idx + "][descripcion]\" class=\"item-descripcion-hidden\">" +
            "<div class=\"item-desc-wysiwyg\" id=\"descWysiwyg_" + idx + "\"></div>" +
        "</div>" +
        "<div class=\"form-group\">" +
            "<label>Cantidad</label>" +
            "<input type=\"number\" name=\"items[" + idx + "][cantidad]\" placeholder=\"1\" step=\"0.01\" value=\"1\" required class=\"item-cantidad\">" +
            "<input type=\"text\" name=\"items[" + idx + "][unidad]\" placeholder=\"Tipo (ej: Mensual)\" class=\"item-unidad\" style=\"margin-top:10px; padding:10px; border:2px solid #e0e0e0; border-radius:8px; font-size:13px;\">" +
        "</div>" +
        "<div class=\"form-group\">" +
            "<label>Precio con descuento (\u20ac)</label>" +
            "<input type=\"number\" name=\"items[" + idx + "][precio]\" placeholder=\"0.00\" step=\"0.01\" required class=\"item-precio\">" +
            "<div class=\"precio-original-wrap\">" +
                "<label>Precio original sin descuento (\u20ac) \u2014 opcional</label>" +
                "<input type=\"number\" name=\"items[" + idx + "][precio_original]\" placeholder=\"Dejar vac\u00edo si no hay descuento\" step=\"0.01\" class=\"item-precio-original\">" +
                "<div class=\"precio-original-preview\" id=\"previoOriginal_" + idx + "\" style=\"display:none;\"></div>" +
            "</div>" +
            "<div class=\"item-info\"><strong>Total: <span class=\"item-total-display\">0.00 \u20ac</span></strong></div>" +
            "<input type=\"hidden\" class=\"item-total\" value=\"0\">" +
        "</div>" +
        "<button type=\"button\" class=\"btn-remove\" onclick=\"removeItem(this)\">\uD83D\uDDD1\uFE0F</button>";

    container.appendChild(newItem);
    itemCounter++;

    initArticuloSS(idx);
    attachItemListeners(newItem);
    attachPrecioOriginalListener(newItem, idx);
    initItemDescQuill(idx);   // <-- inicializar Quill del nuevo item
}

function attachPrecioOriginalListener(row, idx) {
    var inputOrig = row.querySelector(".item-precio-original");
    var preview   = document.getElementById("previoOriginal_" + idx);
    if (!inputOrig || !preview) return;
    inputOrig.addEventListener("input", function() {
        var val = parseFloat(this.value);
        if (!isNaN(val) && val > 0) {
            preview.style.display = "block";
            preview.textContent = "Antes: " + val.toLocaleString("es-ES", {minimumFractionDigits:2, maximumFractionDigits:2}) + " \u20ac";
        } else {
            preview.style.display = "none";
            preview.textContent = "";
        }
    });
}

function removeItem(button) {
    var items = document.querySelectorAll(".item-row");
    if (items.length > 1) {
        var row = button.closest(".item-row");
        var idx = parseInt(row.getAttribute("data-item-index"));
        if (window.itemDescQuills && window.itemDescQuills[idx]) {
            delete window.itemDescQuills[idx];
        }
        row.remove();
        calculateTotals();
    } else {
        alert("Debe haber al menos un art\u00edculo en el presupuesto");
    }
}

// ============================================================
// TOTALES
// ============================================================
function calculateItemTotal(itemRow) {
    var cantidad = parseFloat(itemRow.querySelector(".item-cantidad").value) || 0;
    var precio   = parseFloat(itemRow.querySelector(".item-precio").value)   || 0;
    var total    = cantidad * precio;
    itemRow.querySelector(".item-total").value = total.toFixed(2);
    itemRow.querySelector(".item-total-display").textContent = formatCurrency(total);
}

function calculateTotals() {
    var subtotal = 0;
    document.querySelectorAll(".item-row").forEach(function(row) {
        calculateItemTotal(row);
        subtotal += parseFloat(row.querySelector(".item-total").value) || 0;
    });
    var iva    = parseFloat(document.getElementById("iva").value)             || 0;
    var seg    = parseFloat(document.getElementById("segundoImpuesto").value) || 0;
    var ivaAmt = (subtotal * iva) / 100;
    var segAmt = (subtotal * seg) / 100;
    var total  = subtotal + ivaAmt + segAmt;

    document.getElementById("subtotal").textContent            = formatCurrency(subtotal);
    document.getElementById("ivaPercent").textContent          = iva.toFixed(0);
    document.getElementById("ivaAmount").textContent           = formatCurrency(ivaAmt);
    document.getElementById("segundoImpuestoPercent").textContent = seg.toFixed(0);
    document.getElementById("segundoImpuestoAmount").textContent  = formatCurrency(segAmt);
    document.getElementById("total").textContent               = formatCurrency(total);
    document.getElementById("segundoImpuestoRow").style.display = seg > 0 ? "flex" : "none";
}

function formatCurrency(amount) {
    return new Intl.NumberFormat("es-ES", { style: "currency", currency: "EUR" }).format(amount);
}

function escapeHtml(text) {
    var div = document.createElement("div");
    div.textContent = text;
    return div.innerHTML;
}

function attachItemListeners(itemRow) {
    itemRow.querySelector(".item-cantidad").addEventListener("input", calculateTotals);
    itemRow.querySelector(".item-precio").addEventListener("input",   calculateTotals);
}

document.querySelectorAll(".item-row").forEach(function(row) { attachItemListeners(row); });
document.getElementById("iva").addEventListener("input",             calculateTotals);
document.getElementById("segundoImpuesto").addEventListener("input", calculateTotals);
calculateTotals();

// ============================================================
// SUBMIT
// ============================================================
document.getElementById("presupuestoForm").addEventListener("submit", function(e) {
    e.preventDefault();

    // Validar email
    var emailHidden = document.getElementById("clienteEmailHidden").value.trim();
    if (!emailHidden) {
        alert("Por favor, a\u00f1ade al menos un email para el cliente.");
        document.getElementById("emailTagsInput").focus();
        return;
    }

    // Sincronizar Quills globales
    var detallesHtml = detallesQuill.root.innerHTML;
    if (detallesQuill.getText().trim() === "") detallesHtml = "";
    document.getElementById("detallesPropuestaHidden").value = detallesHtml;

    var notasHtml = notasQuill.root.innerHTML;
    if (notasQuill.getText().trim() === "") notasHtml = "";
    document.getElementById("notasHidden").value = notasHtml;

    // Sincronizar Quills de descripción de items
    if (window.itemDescQuills) {
        Object.keys(window.itemDescQuills).forEach(function(idx) {
            var q   = window.itemDescQuills[idx];
            var row = document.querySelector("[data-item-index=\"" + idx + "\"]");
            if (!row) return;
            var hidden = row.querySelector(".item-descripcion-hidden");
            if (!hidden) return;
            var html = q.root.innerHTML;
            if (q.getText().trim() === "") html = "";
            hidden.value = html;
        });
    }

    var formData = new FormData(this);
    var action   = e.submitter.value;
    formData.append("action", action);

    var totalSubtotal = 0;
    document.querySelectorAll(".item-total").forEach(function(input) {
        totalSubtotal += parseFloat(input.value) || 0;
    });

    var iva   = parseFloat(document.getElementById("iva").value)             || 0;
    var seg   = parseFloat(document.getElementById("segundoImpuesto").value) || 0;
    var total = totalSubtotal + (totalSubtotal * iva / 100) + (totalSubtotal * seg / 100);

    formData.append("subtotal", totalSubtotal.toFixed(2));
    formData.append("total",    total.toFixed(2));

    fetch("api.php", { method: "POST", body: formData })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                if (action === "guardar_pdf") {
                    window.open("api.php?action=pdf&id=" + data.id, "_blank");
                }
                setTimeout(function() { window.location.href = "index.php"; }, 500);
            } else {
                alert("Error: " + (data.message || "No se pudo guardar"));
            }
        })
        .catch(function(err) { console.error(err); alert("Error al guardar el presupuesto"); });
});
</script>
';

include '../includes/footer.php';
?>