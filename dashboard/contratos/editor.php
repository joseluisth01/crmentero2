<?php
/**
 * Editor de Contratos - Crear/Editar
 * Sistema Tictac Comunicación
 * MODIFICADO: Una sola sección de cláusulas.
 *             Checkbox Kit Digital cambia qué cláusulas estándar se cargan.
 *             Se elimina la sección Kit Digital independiente.
 */

require_once '../config.php';

$pageTitle = 'Editor de Contratos';
$showBackButton = true;

$editId = isset($_GET['id']) ? $_GET['id'] : null;
$contrato = null;

if ($editId) {
    $contratosFile = DATA_PATH . '/contratos.json';
    if (file_exists($contratosFile)) {
        $contratos = json_decode(file_get_contents($contratosFile), true);
        foreach ($contratos as $c) {
            if ($c['id'] === $editId) { $contrato = $c; break; }
        }
    }
}

// Obtener clientes del CRM
$clientes = array();
$mysqli = conexionBBDD();
if ($mysqli) {
    $res = $mysqli->query("SELECT id, company_name, phone, address, city, zip, country, vat_number FROM crm_clients WHERE deleted = 0 ORDER BY company_name ASC");
    if ($res) { while ($row = $res->fetch_assoc()) { $clientes[] = $row; } $res->free(); }
}

// Obtener artículos del catálogo
$articulos = getArticulosCRM();

$clientesJson  = json_encode($clientes, JSON_UNESCAPED_UNICODE);
$articulosJson = json_encode($articulos, JSON_UNESCAPED_UNICODE);
$selectedClienteId = $contrato ? ($contrato['cliente_id'] ?? '') : '';

$notasInicial      = $contrato ? ($contrato['notas'] ?? '') : '';
$clausulasInicial  = $contrato ? ($contrato['clausulas_html'] ?? '') : '';
$kitDigitalActivo  = $contrato ? (!empty($contrato['kit_digital'])) : false;
$existingItems     = ($contrato && !empty($contrato['items'])) ? $contrato['items'] : [null];

$additionalStyles = '
<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
<style>
    .editor-container { background:white; border-radius:10px; box-shadow:0 2px 10px rgba(0,0,0,0.05); padding:40px; margin:30px 0; }
    .form-section { margin-bottom:40px; }
    .form-section h3 { color:' . BRAND_COLOR . '; margin-bottom:20px; padding-bottom:10px; border-bottom:2px solid ' . BRAND_COLOR . '; }
    .form-row { display:grid; grid-template-columns:repeat(auto-fit,minmax(250px,1fr)); gap:20px; margin-bottom:20px; }
    .form-group { display:flex; flex-direction:column; }
    .form-group label { font-weight:600; margin-bottom:8px; color:#333; font-size:14px; }
    .form-group input, .form-group select, .form-group textarea {
        padding:12px; border:2px solid #e0e0e0; border-radius:8px; font-size:14px; transition:all .3s;
    }
    .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
        outline:none; border-color:' . BRAND_COLOR . ';
    }
    .form-group textarea { min-height:80px; resize:vertical; font-family:inherit; }
    .ss-wrapper { position:relative; width:100%; }
    .ss-main { display:flex; align-items:center; border:2px solid #e0e0e0; border-radius:8px; padding:10px 12px; cursor:pointer; background:white; min-height:44px; transition:border-color .3s; }
    .ss-main.ss-open { border-color:' . BRAND_COLOR . '; }
    .ss-selected { flex:1; color:#333; font-size:14px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .ss-selected.ss-ph { color:#999; }
    .ss-arrow { width:0; height:0; border-left:5px solid transparent; border-right:5px solid transparent; border-top:5px solid #999; margin-left:8px; transition:transform .2s; }
    .ss-main.ss-open .ss-arrow { transform:rotate(180deg); }
    .ss-dropdown { display:none; position:absolute; top:100%; left:0; right:0; background:white; border:2px solid ' . BRAND_COLOR . '; border-top:none; border-radius:0 0 8px 8px; z-index:1000; max-height:280px; box-shadow:0 4px 12px rgba(0,0,0,.15); }
    .ss-dropdown.ss-open { display:block; }
    .ss-search { padding:10px; border-bottom:1px solid #eee; background:#fafafa; }
    .ss-search input { width:100%; padding:8px 10px; border:1px solid #ddd; border-radius:5px; font-size:13px; box-sizing:border-box; }
    .ss-options { max-height:220px; overflow-y:auto; }
    .ss-option { padding:10px 12px; cursor:pointer; font-size:13px; border-bottom:1px solid #f5f5f5; transition:background .15s; }
    .ss-option:hover, .ss-option.ss-hi { background:#fff0f7; color:' . BRAND_COLOR . '; }
    .ss-option.ss-sel { background:#ffe8f3; font-weight:600; }
    .ss-option .ss-sub { display:block; font-size:11px; color:#888; margin-top:2px; }
    .ss-option .ss-price { float:right; color:' . BRAND_COLOR . '; font-weight:600; font-size:12px; }
    .ss-none { padding:12px; text-align:center; color:#999; font-style:italic; }
    .wysiwyg-container { border:2px solid #e0e0e0; border-radius:8px; overflow:hidden; transition:border-color .3s; }
    .wysiwyg-container:focus-within { border-color:' . BRAND_COLOR . '; }
    .wysiwyg-container .ql-toolbar { border:none; border-bottom:1px solid #e0e0e0; background:#fafafa; }
    .wysiwyg-container .ql-container { border:none; min-height:100px; font-size:14px; }
    .wysiwyg-container .ql-editor { min-height:100px; padding:12px; }
    .wysiwyg-container .ql-editor.ql-blank::before { font-style:normal; color:#999; }
    .items-section { background:#f9f9f9; padding:20px; border-radius:8px; margin-top:20px; }
    .item-row { background:white; padding:15px; border-radius:8px; margin-bottom:15px; display:grid; grid-template-columns:2fr 1fr 1fr 60px; gap:15px; align-items:end; }
    .item-row input { padding:10px; border:2px solid #e0e0e0; border-radius:8px; font-size:14px; }
    .btn-remove { background:#dc3545; color:white; border:none; padding:10px; border-radius:8px; cursor:pointer; font-size:18px; }
    .btn-remove:hover { background:#c82333; }
    .btn-add { background:' . ACCENT_COLOR . '; color:#333; border:none; padding:12px 25px; border-radius:8px; cursor:pointer; font-weight:600; margin-top:15px; }
    .precio-original-wrap { margin-top:8px; }
    .precio-original-wrap label { font-size:11px; color:#888; font-weight:400; margin-bottom:4px; }
    .item-precio-original { width:100%; padding:8px 10px; border:2px dashed #e0a0c0; border-radius:8px; font-size:13px; color:#999; background:#fff8fb; margin-top:2px; box-sizing:border-box; }
    .item-precio-original:focus { outline:none; border-color:#E91E8C; }
    .precio-original-preview { font-size:12px; color:#E91E8C; text-decoration:line-through; margin-top:4px; font-weight:600; }
    .btn-add:hover { opacity:.8; }
    .item-info { font-size:11px; color:#666; margin-top:5px; }
    .totals-section { background:#f0f0f0; padding:20px; border-radius:8px; margin-top:30px; }
    .total-row { display:flex; justify-content:space-between; padding:10px 0; font-size:16px; }
    .total-row.final { border-top:3px solid ' . BRAND_COLOR . '; margin-top:10px; padding-top:15px; font-size:24px; font-weight:bold; color:' . BRAND_COLOR . '; }
    /* ── Kit Digital toggle dentro de cláusulas ── */
    .kit-digital-toggle { display:flex; align-items:center; gap:12px; margin-bottom:16px; padding:14px 16px; background:#fff8e1; border:2px solid #ffc107; border-radius:8px; }
    .kit-digital-toggle input[type=checkbox] { width:20px; height:20px; cursor:pointer; accent-color:#E91E8C; flex-shrink:0; }
    .kit-digital-toggle label { font-size:14px; font-weight:600; color:#333; cursor:pointer; }
    .kit-digital-toggle .kd-badge { background:#ffc107; color:#333; font-size:11px; font-weight:700; padding:2px 8px; border-radius:10px; margin-left:6px; }
    /* ── Actions ── */
    .actions-bar { display:flex; gap:15px; justify-content:flex-end; margin-top:40px; padding-top:20px; border-top:2px solid #e0e0e0; }
    .btn { padding:15px 30px; border-radius:50px; font-weight:600; font-size:16px; border:none; cursor:pointer; transition:all .3s; text-decoration:none; display:inline-block; }
    .btn-secondary { background:#6c757d; color:white; } .btn-secondary:hover { background:#5a6268; }
    .btn-primary { background:' . BRAND_COLOR . '; color:white; } .btn-primary:hover { background:' . BRAND_COLOR_DARK . '; transform:translateY(-2px); }
    .btn-success { background:#28a745; color:white; } .btn-success:hover { background:#218838; }
    @media (max-width:768px) { .item-row { grid-template-columns:1fr; } .actions-bar { flex-direction:column; } .btn { width:100%; } }
</style>
';

include '../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1><?php echo $editId ? '✏️ Editar Contrato' : '📄 Nuevo Contrato'; ?></h1>
        <p>Introduce los datos del contrato de servicios</p>
    </div>

    <form id="contratoForm" class="editor-container">
        <?php if ($editId): ?>
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($editId); ?>">
        <?php endif; ?>

        <!-- DATOS DEL CONTRATO -->
        <div class="form-section">
            <h3>📊 Datos del Contrato</h3>
            <div class="form-row">
                <div class="form-group" style="grid-column:1/-1;">
                    <label>Título del Contrato *</label>
                    <input type="text" name="titulo" required placeholder="Ej: Contrato Mantenimiento SEO 2026"
                        value="<?php echo $contrato ? htmlspecialchars($contrato['titulo'] ?? '') : ''; ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Fecha del Contrato *</label>
                    <input type="date" name="fecha_contrato" required
                        value="<?php echo $contrato ? $contrato['fecha_contrato'] : date('Y-m-d'); ?>">
                </div>
                <div class="form-group">
                    <label>Válido Hasta *</label>
                    <input type="date" name="valido_hasta" required
                        value="<?php echo $contrato ? $contrato['valido_hasta'] : date('Y-m-d', strtotime('+1 year')); ?>">
                </div>
            </div>
        </div>

        <!-- DATOS DEL CLIENTE -->
        <div class="form-section">
            <h3>👤 Información del Cliente (El Cliente)</h3>
            <div class="form-row">
                <div class="form-group" style="grid-column:1/-1;">
                    <label>Seleccionar Cliente del CRM (opcional)</label>
                    <select name="cliente_id" id="clienteSelect" style="display:none;">
                        <option value="">-- Seleccionar --</option>
                        <?php foreach ($clientes as $cl): ?>
                            <option value="<?php echo $cl['id']; ?>"
                                data-nombre="<?php echo htmlspecialchars($cl['company_name'] ?? ''); ?>"
                                data-telefono="<?php echo htmlspecialchars($cl['phone'] ?? ''); ?>"
                                data-direccion="<?php echo htmlspecialchars($cl['address'] ?? ''); ?>"
                                data-ciudad="<?php echo htmlspecialchars($cl['city'] ?? ''); ?>"
                                data-cp="<?php echo htmlspecialchars($cl['zip'] ?? ''); ?>"
                                data-pais="<?php echo htmlspecialchars($cl['country'] ?? ''); ?>"
                                data-cif="<?php echo htmlspecialchars($cl['vat_number'] ?? ''); ?>"
                                <?php echo ($contrato && ($contrato['cliente_id'] ?? '') == $cl['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cl['company_name'] ?? ''); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="ss-wrapper" id="clienteWrapper">
                        <div class="ss-main" id="clienteMain">
                            <span class="ss-selected ss-ph" id="clienteText">-- Opcional: buscar cliente del CRM --</span>
                            <div class="ss-arrow"></div>
                        </div>
                        <div class="ss-dropdown" id="clienteDropdown">
                            <div class="ss-search"><input type="text" id="clienteSearch" placeholder="Buscar cliente..." autocomplete="off"></div>
                            <div class="ss-options" id="clienteOptions"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Denominación Social / Nombre *</label>
                    <input type="text" name="cliente_nombre" id="clienteNombre" required placeholder="Razón social del cliente"
                        value="<?php echo $contrato ? htmlspecialchars($contrato['cliente_nombre'] ?? '') : ''; ?>">
                </div>
                <div class="form-group">
                    <label>NIF/CIF</label>
                    <input type="text" name="cliente_cif" id="clienteCif" placeholder="B12345678"
                        value="<?php echo $contrato ? htmlspecialchars($contrato['cliente_cif'] ?? '') : ''; ?>">
                </div>
                <div class="form-group">
                    <label>Emails de contacto * <span style="font-weight:400;color:#888;font-size:12px;">(separa varios con coma)</span></label>
                    <input type="text" name="cliente_email" id="clienteEmail" required placeholder="email@empresa.com, otro@empresa.com"
                        value="<?php echo $contrato ? htmlspecialchars($contrato['cliente_email'] ?? '') : ''; ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group" style="grid-column:1/-1;">
                    <label>Domicilio Social</label>
                    <input type="text" name="cliente_direccion" id="clienteDireccion" placeholder="Calle, número, piso..."
                        value="<?php echo $contrato ? htmlspecialchars($contrato['cliente_direccion'] ?? '') : ''; ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Ciudad</label>
                    <input type="text" name="cliente_ciudad" id="clienteCiudad" placeholder="Ciudad"
                        value="<?php echo $contrato ? htmlspecialchars($contrato['cliente_ciudad'] ?? '') : ''; ?>">
                </div>
                <div class="form-group">
                    <label>Código Postal</label>
                    <input type="text" name="cliente_cp" id="clienteCp" placeholder="CP"
                        value="<?php echo $contrato ? htmlspecialchars($contrato['cliente_cp'] ?? '') : ''; ?>">
                </div>
                <div class="form-group">
                    <label>País</label>
                    <input type="text" name="cliente_pais" id="clientePais" placeholder="España"
                        value="<?php echo $contrato ? htmlspecialchars($contrato['cliente_pais'] ?? '') : ''; ?>">
                </div>
                <div class="form-group">
                    <label>Nombre firmante (persona)</label>
                    <input type="text" name="cliente_firmante" id="clienteFirmante" placeholder="Nombre y apellidos del firmante"
                        value="<?php echo $contrato ? htmlspecialchars($contrato['cliente_firmante'] ?? '') : ''; ?>">
                </div>
            </div>
        </div>

        <!-- ARTÍCULOS/SERVICIOS -->
        <div class="form-section">
            <h3>📦 Servicios Contratados</h3>
            <div class="items-section" id="itemsContainer">
                <?php foreach ($existingItems as $idx => $item): ?>
                <div class="item-row" data-item-index="<?php echo $idx; ?>">
                    <div class="form-group">
                        <label>Artículo/Servicio</label>
                        <div class="ss-wrapper" id="artWrapper_<?php echo $idx; ?>">
                            <div class="ss-main" id="artMain_<?php echo $idx; ?>">
                                <span class="ss-selected ss-ph" id="artText_<?php echo $idx; ?>">-- Buscar en catálogo --</span>
                                <div class="ss-arrow"></div>
                            </div>
                            <div class="ss-dropdown" id="artDropdown_<?php echo $idx; ?>">
                                <div class="ss-search"><input type="text" id="artSearch_<?php echo $idx; ?>" placeholder="Buscar artículo..." autocomplete="off"></div>
                                <div class="ss-options" id="artOptions_<?php echo $idx; ?>"></div>
                            </div>
                        </div>
                        <input type="text" name="items[<?php echo $idx; ?>][nombre]" placeholder="Nombre del servicio" required class="item-nombre"
                            style="margin-top:10px;"
                            value="<?php echo $item ? htmlspecialchars($item['nombre'] ?? '') : ''; ?>">
                        <textarea name="items[<?php echo $idx; ?>][descripcion]" placeholder="Descripción" class="item-descripcion"
                            style="margin-top:10px;padding:10px;border:2px solid #e0e0e0;border-radius:8px;font-size:14px;min-height:50px;"><?php echo $item ? htmlspecialchars($item['descripcion'] ?? '') : ''; ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>Cantidad</label>
                        <input type="number" name="items[<?php echo $idx; ?>][cantidad]" placeholder="1" step="0.01" required class="item-cantidad"
                            value="<?php echo $item ? ($item['cantidad'] ?? 1) : 1; ?>">
                        <input type="text" name="items[<?php echo $idx; ?>][unidad]" placeholder="Mensual, Único..." class="item-unidad"
                            style="margin-top:10px;padding:10px;border:2px solid #e0e0e0;border-radius:8px;font-size:13px;"
                            value="<?php echo $item ? htmlspecialchars($item['unidad'] ?? '') : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>Precio con descuento (€)</label>
                        <input type="number" name="items[<?php echo $idx; ?>][precio]" placeholder="0.00" step="0.01" required class="item-precio"
                            value="<?php echo $item ? ($item['precio'] ?? '') : ''; ?>">
                        <div class="precio-original-wrap">
                            <label>Precio original sin descuento (€) — opcional</label>
                            <input type="number" name="items[<?php echo $idx; ?>][precio_original]"
                                placeholder="Dejar vacío si no hay descuento" step="0.01" class="item-precio-original"
                                value="<?php echo $item ? htmlspecialchars($item['precio_original'] ?? '') : ''; ?>">
                            <div class="precio-original-preview" id="prevOriginal_<?php echo $idx; ?>"
                                style="display:<?php echo ($item && !empty($item['precio_original'])) ? 'block' : 'none'; ?>;">
                                Antes: <?php echo ($item && !empty($item['precio_original'])) ? number_format(floatval($item['precio_original']), 2, ',', '.') . ' €' : ''; ?>
                            </div>
                        </div>
                        <div class="item-info"><strong>Total: <span class="item-total-display"><?php echo $item ? number_format(($item['cantidad'] ?? 0) * ($item['precio'] ?? 0), 2) . ' €' : '0.00 €'; ?></span></strong></div>
                        <input type="hidden" class="item-total" value="<?php echo $item ? (($item['cantidad'] ?? 0) * ($item['precio'] ?? 0)) : 0; ?>">
                    </div>
                    <button type="button" class="btn-remove" onclick="removeItem(this)">🗑️</button>
                </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="btn-add" onclick="addItem()">+ Añadir Servicio</button>
        </div>

        <!-- IMPUESTOS -->
        <div class="form-section">
            <h3>💰 Impuestos</h3>
            <div class="form-row">
                <div class="form-group">
                    <label>IVA (%)</label>
                    <input type="number" name="iva" id="iva" step="0.01" min="0"
                        value="<?php echo $contrato ? ($contrato['iva'] ?? 21) : 21; ?>">
                </div>
                <div class="form-group">
                    <label>Segundo Impuesto (%) — Opcional</label>
                    <input type="number" name="segundo_impuesto" id="segundoImpuesto" step="0.01" min="0"
                        value="<?php echo $contrato ? ($contrato['segundo_impuesto'] ?? 0) : 0; ?>">
                </div>
                <div class="form-group">
                    <label>Descuento global (€) — Opcional <span style="font-weight:400;color:#888;font-size:12px;">Se resta del total final</span></label>
                    <input type="number" name="descuento_global" id="descuentoGlobal" step="0.01" min="0"
                        value="<?php echo $contrato ? ($contrato['descuento_global'] ?? 0) : 0; ?>">
                </div>
            </div>
        </div>

        <!-- CLÁUSULAS DEL CONTRATO — sección única -->
        <div class="form-section">
            <h3>📋 Cláusulas del Contrato</h3>

            <!-- Toggle Kit Digital dentro de la sección de cláusulas -->
            <div class="kit-digital-toggle">
                <input type="checkbox" name="kit_digital" id="kitDigitalCheck" value="1"
                    <?php echo $kitDigitalActivo ? 'checked' : ''; ?>>
                <label for="kitDigitalCheck">
                    Este contrato es de <span class="kd-badge">🇪🇺 KIT DIGITAL</span>
                    — al cargar las cláusulas estándar se cargarán las de Kit Digital
                </label>
            </div>

            <div class="form-group">
                <label>
                    Cláusulas legales — Déjalo vacío para usar las <strong>cláusulas estándar</strong> automáticamente en el PDF.
                    Si escribes aquí, <strong>reemplazarán</strong> las estándar.
                </label>
                <input type="hidden" name="clausulas_html" id="clausulasHidden" value="<?php echo $contrato ? htmlspecialchars($contrato['clausulas_html'] ?? '') : ''; ?>">
                <div style="margin:8px 0; display:flex; gap:8px; flex-wrap:wrap;">
                    <button type="button" onclick="cargarClausulasDefault()"
                        style="background:#E91E8C;color:white;border:none;padding:8px 18px;border-radius:20px;cursor:pointer;font-size:13px;font-weight:600;">
                        📋 Cargar cláusulas estándar para editar
                    </button>
                    <button type="button" onclick="limpiarClausulas()"
                        style="background:#6c757d;color:white;border:none;padding:8px 18px;border-radius:20px;cursor:pointer;font-size:13px;font-weight:600;">
                        🗑️ Usar cláusulas estándar (vaciar)
                    </button>
                </div>
                <div id="clausulasEstado" style="font-size:12px;color:#666;margin:4px 0 8px 0;">
                    <?php echo empty($clausulasInicial) ? '✅ Usando cláusulas estándar de Tictac' : '✏️ Cláusulas personalizadas activas'; ?>
                </div>
                <div class="wysiwyg-container">
                    <div id="clausulasEditor"></div>
                </div>
            </div>
        </div>

        <!-- NOTAS ADHERIDAS AL CONTRATO -->
        <div class="form-section">
            <h3>📝 Notas Adheridas al Contrato</h3>
            <div class="form-group">
                <label>Notas adicionales (opcional) — Aparecerá al final del contrato PDF</label>
                <input type="hidden" name="notas" id="notasHidden" value="<?php echo $contrato ? htmlspecialchars($contrato['notas'] ?? '') : ''; ?>">
                <div class="wysiwyg-container">
                    <div id="notasEditor"></div>
                </div>
            </div>
        </div>

        <!-- TOTALES -->
        <div class="totals-section">
            <div class="total-row"><span>Subtotal:</span><span id="subtotal">0,00 €</span></div>
            <div class="total-row"><span>IVA (<span id="ivaPercent">21</span>%):</span><span id="ivaAmount">0,00 €</span></div>
            <div class="total-row" id="segRow" style="display:none;">
                <span>Segundo Impuesto (<span id="segPercent">0</span>%):</span>
                <span id="segAmount">0,00 €</span>
            </div>
            <div class="total-row" id="dtoRow" style="display:none;color:#E91E8C;">
                <span>Descuento:</span><span id="dtoAmount">0,00 €</span>
            </div>
            <div class="total-row final"><span>TOTAL:</span><span id="total">0,00 €</span></div>
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
$additionalScripts = '
<script>
// ── NOTAS WYSIWYG ──────────────────────────────────────────────
const notasQuill = new Quill("#notasEditor", {
    theme: "snow",
    modules: { toolbar: [["bold","italic","underline"],[{"list":"ordered"},{"list":"bullet"}],["clean"]] },
    placeholder: "Añade aquí condiciones especiales, notas o cláusulas adicionales..."
});
(function(){ var v = document.getElementById("notasHidden").value; if (v && v.trim()) notasQuill.root.innerHTML = v; })();
notasQuill.on("text-change", function(){
    var h = notasQuill.root.innerHTML;
    if (notasQuill.getText().trim() === "") h = "";
    document.getElementById("notasHidden").value = h;
});

// ── CLÁUSULAS WYSIWYG ──────────────────────────────────────────
const clausulasQuill = new Quill("#clausulasEditor", {
    theme: "snow",
    modules: { toolbar: [[{"header":[1,2,3,false]}],["bold","italic","underline"],[{"list":"ordered"},{"list":"bullet"}],["clean"]]},
    placeholder: "Vacío = se usarán las cláusulas estándar en el PDF automáticamente..."
});
(function(){ var v = document.getElementById("clausulasHidden").value; if (v && v.trim()) clausulasQuill.root.innerHTML = v; })();
clausulasQuill.on("text-change", function(){
    var h = clausulasQuill.root.innerHTML;
    var vacio = clausulasQuill.getText().trim() === "";
    if (vacio) h = "";
    document.getElementById("clausulasHidden").value = h;
    document.getElementById("clausulasEstado").innerHTML = vacio ? "✅ Usando cláusulas estándar de Tictac" : "✏️ Cláusulas personalizadas activas";
});

// ── CLÁUSULAS ESTÁNDAR NORMALES ────────────────────────────────
const CLAUSULAS_NORMAL = "<p><strong>1. OBJETO</strong></p><p>El objeto del Contrato consiste en la prestación de servicios por parte del Proveedor a cambio del pago de un precio por parte del Cliente, en los términos establecidos en el mismo.</p><p>Las solicitudes de modificación del contrato se harán siempre por escrito, remitido por correo ordinario o electrónico hola@tictac-comunicacion.es. Se ejecutarán siempre que sea posible y el cliente deberá asumir los costes en los que el Proveedor haya incurrido, tras dicha modificación del contrato.</p><p>El Cliente acepta que el Proveedor pueda publicar su imagen corporativa, nombre comercial y sitio web dentro de \"casos de éxito\" o \"sección clientes\" de la web de Tic Tac Comunicación (www.tictac-comunicacion.es), así como la firma de la Empresa Tic Tac Comunicación en Footer (Pie de Página de la web del Cliente).</p><p><strong>2. SERVICIOS DEL PROVEEDOR</strong></p><p>2.1. Los Servicios del proyecto vendrán descritos en la hoja de encargo adjunta que deberá ser firmada por el cliente y por la empresa que provee el servicio.</p><p>En relación al diseño web, si el cliente ha contratado este servicio y procede, el Proveedor presentará al cliente hasta 3 bocetos en soporte físico o digital. El Cliente ha de firmar el Boceto escogido, todos los cambios a partir del momento de la firma, conllevarán costos adicionales.</p><p>2.1.1. Realización de material especial tal como: tipografía no convencional, caligrafía, mapas, diagramas, gráficos, vectores o fotomontajes.</p><p>2.1.2. Preparación de material existente para su reproducción tales como: redibujo parcial o total, conversión a líneas, escaneado y retoque de imágenes, tipeados, etc.</p><p>2.1.3. Seguimiento de la producción.</p><p>2.1.4. Recuperación de información, siempre que técnicamente sea posible. El horario de trabajo de los técnicos del Proveedor será de lunes a viernes de 9:00 a 17:00, salvo en los meses de Julio y agosto que será de 9:00 a 15:00.</p><p>2.1.5. La corrección de errores imputables a la manipulación a través de los Programas de gestión de contenidos por personal no autorizado expresamente por el Proveedor.</p><p>2.1.6. Las tareas necesarias para restablecer la situación anterior derivada de operaciones incorrectas por parte del Cliente que ocasionen pérdidas de información, destrucción o desorganización de ficheros, y situaciones análogas.</p><p>2.1.7. La reparación de daños causados por virus o defectos de otros programas no relacionados en el Contrato, o en anexo posterior.</p><p>2.1.8. La reparación de daños y malfuncionamientos causados por accidentes, uso indebido, catástrofes, abusos, alteraciones, sustitución de elementos o software no suministrado y/o recomendado por el Proveedor.</p><p><strong>3. VALORACIÓN DE LOS SERVICIOS, FACTURACIÓN, FORMA DE PAGO, IMPUESTOS Y GASTOS</strong></p><p>3.1. La valoración económica será actualizada anualmente por el Proveedor, en función de las nuevas tarifas que el Proveedor establezca.</p><p>3.2. El precio de los Servicios será abonado por el Cliente al Proveedor en el momento de la formalización del Contrato.</p><p>3.3. El precio expresado a continuación contienen los impuestos indirectos desglosados a la fecha de la firma actual.</p><p>3.4. Se emitirá un cobro mensual en el siguiente número de cuenta bancaria facilitado por el Cliente.</p><p>3.5. Cualquier revisión o adiciones a los servicios descritos en el contrato serán facturados como Servicios Adicionales no incluidos en el presupuesto estimado.</p><p><strong>4. RESPONSABILIDAD DEL CLIENTE</strong></p><p>4.1. El Cliente proveerá información fehaciente y completa y materiales al Proveedor, y será responsable de la exactitud y completitud de toda la información y los materiales provistos.</p><p>4.2. El Cliente en caso haber realizado alguna modificación por su cuenta y por ello, haber desconfigurado la web, será el mismo Cliente quien responda por el costo del arreglo.</p><p>4.3. Todo texto e información aportado por el Cliente se entregará al Proveedor en formato digital. Este proceso (escaneado, OCR, tipeado, etc.) será presupuestado como un servicio suplementario.</p><p><strong>5. DERECHOS Y PROPIEDAD</strong></p><p>5.1. Todos los servicios provistos por el Proveedor y aprobados bajo este contrato serán para uso exclusivo del Cliente más allá de su uso promocional propio del Proveedor.</p><p>5.2. El Proveedor se compromete a almacenar los originales durante 6 meses a partir de la finalización del Proyecto.</p><p>5.3. El Dominio (dirección web) pertenecerá al Cliente, siendo éste su propietario en todo momento.</p><p>5.4. Una vez finalizado el pago total del monto acordado, el Cliente, pasará a ser propietario de la Web.</p><p><strong>6. DURACIÓN DEL CONTRATO</strong></p><p>6.1. El Contrato tendrá una vigencia mínima de un (1) año, contada a partir de la fecha de la firma del presente Contrato.</p><p>6.2. El Cliente podrá rescindir el presente Contrato, notificándoselo por escrito al Proveedor con al menos treinta (30) días de antelación a la fecha de vencimiento inicial, o, en su caso, de cualquiera de sus prórrogas.</p><p><strong>7. EXTINCIÓN DEL CONTRATO</strong></p><p>7.1. El Contrato se extinguirá por las causas generales establecidas en la legislación vigente.</p><p>7.2. En todo caso, la extinción del Contrato antes de la finalización del período inicial no dará lugar a devolución alguna del precio abonado al Proveedor.</p><p>7.3. La no acreditación del pago del precio será causa automática de resolución del Contrato.</p><p><strong>8. NATURALEZA DE LA RELACIÓN</strong></p><p>8.1. El presente Contrato tiene carácter mercantil y se regirá por sus propias cláusulas, y en lo que en ellas no estuviere previsto, por las disposiciones del Código de Comercio, leyes especiales y usos mercantiles, y en su defecto, por el Código Civil.</p><p><strong>9. PROTECCIÓN DE DATOS DE CARÁCTER PERSONAL</strong></p><p>9.1. Debido a la naturaleza de los Servicios, el Proveedor puede tener que realizar tratamientos automatizados de ficheros del Cliente que contengan datos de carácter personal. El Proveedor utilizará dichos datos única y exclusivamente para los fines que figuran en el Contrato y siempre por cuenta del Cliente.</p><p>9.2. El Cliente únicamente permitirá el acceso a datos de carácter personal al Proveedor cuando sea necesario para la ejecución del objeto del Contrato.</p><p>9.3. El Cliente afirma y garantiza que los datos han sido recogidos de acuerdo a lo establecido en la LOPD.</p><p><strong>10. CONFIDENCIALIDAD</strong></p><p>10.1. El Proveedor considerará confidencial toda la información relacionada con los Servicios, y que obtenga durante la prestación de los mismos.</p><p><strong>11. RESPONSABILIDAD DEL PROVEEDOR</strong></p><p>11.1. Salvo en los casos de culpa grave o dolo, la responsabilidad total del Proveedor no excederá, en su conjunto, de la cantidad correspondiente al precio abonado por los Servicios durante la última anualidad. El Proveedor no será responsable, en ningún caso, de los daños que puedan ser calificados como daños indirectos, consecuenciales, pérdida de beneficio, negocio, ingresos, clientes, datos, imagen o reputación comercial.</p><p><strong>12. ACTUALIZACIÓN</strong></p><p>12.1. En el caso de que alguna o algunas de las cláusulas del Contrato pasen a ser inválidas, ilegales o inejecutables, se considerarán ineficaces en la medida que corresponda, pero en lo demás, este Contrato conservará su validez. CONTRATO ÚNICO</p><p><strong>13. NOTIFICACIONES Y REQUERIMIENTOS</strong></p><p>13.1. Toda notificación o requerimiento que traiga su causa del Contrato se deberá remitir por escrito a la otra Parte, bien por E-mail, bien personalmente, o por mensajero o correo certificado con acuse de recibo.</p><p><strong>14. JURISDICCIÓN Y COMPETENCIA</strong></p><p>14.1. Las Partes, con renuncia expresa a cualquier otro fuero que pudiera corresponderles, se someten a la jurisdicción y competencia de los Juzgados y Tribunales de Córdoba.</p><p>14.2. Y para que así conste, y en prueba de conformidad y aceptación de todo cuanto antecede, las Partes firman el presente Contrato por duplicado ejemplar y a un sólo efecto en la fecha y lugar indicados en el encabezamiento.</p>";

// ── CLÁUSULAS KIT DIGITAL ──────────────────────────────────────
const CLAUSULAS_KIT_DIGITAL = "<p><strong>1. OBJETO</strong></p><p>El objeto del Contrato consiste en la prestación de servicios por parte del Proveedor a cambio del pago de un precio por parte del Cliente, en los términos establecidos en el mismo.</p><p>Las solicitudes de modificación del contrato se harán siempre por escrito, remitido por correo ordinario o electrónico hola@tictac-comunicacion.es. Se ejecutarán siempre que sea posible y el cliente deberá asumir los costes en los que el Proveedor haya incurrido, tras dicha modificación del contrato.</p><p>El Cliente acepta que el Proveedor pueda publicar su imagen corporativa, nombre comercial y sitio web dentro de \"casos de éxito\" o \"sección clientes\" de la web de Tic Tac Comunicación (www.tictac-comunicacion.es), así como la firma de la Empresa Tic Tac Comunicación en Footer (Pie de Página de la web del Cliente).</p><p><strong>2. SERVICIOS DEL PROVEEDOR</strong></p><p>2.1. Los Servicios del proyecto vendrán descritos en la hoja de encargo adjunta que deberá ser firmada por el cliente y por la empresa que provee el servicio.</p><p><strong>3. VALORACIÓN DE LOS SERVICIOS, FACTURACIÓN, FORMA DE PAGO, IMPUESTOS Y GASTOS</strong></p><p>3.1. La valoración económica será actualizada anualmente por el Proveedor, en función de las nuevas tarifas que el Proveedor establezca.</p><p>3.2. El precio de los Servicios será abonado por el Cliente al Proveedor en el momento de la formalización del Contrato, con carácter previo al inicio de la prestación de los Servicios, mediante transferencia a la cuenta número que el proveedor designe para tal efecto.</p><p>3.3. El precio expresado a continuación contienen los impuestos indirectos desglosados a la fecha de la firma actual. El precio de los productos o servicios contratados vendrán desglosados a continuación:</p><p>3.4. Cronograma de pago de los servicios (de no especificarse otras condiciones particulares). Se emitirá un cobro mensual en el siguiente número de cuenta bancaria facilitado por el Cliente.</p><p>3.5. Cualquier revisión o adiciones a los servicios descritos en el contrato serán facturados como Servicios Adicionales no incluidos en el presupuesto estimado arriba especificado. Tales servicios adicionales incluirán, pero no se limitarán a, cambios en la dimensión (cantidad) del trabajo, cambios en la complejidad de cualquier elemento involucrado en los Proyectos, y cualquier cambio efectuado después de la aprobación de cada etapa del diseño, documentación, etc.</p><p><strong>4. RESPONSABILIDAD DEL CLIENTE</strong></p><p>4.1. El Cliente proveerá información fehaciente y completa y materiales al Proveedor, y será responsable de la exactitud y completitud de toda la información y los materiales provistos.</p><p>El Cliente garantiza que todo material provisto al Proveedor no afecta los derechos de autor de terceros. El Cliente indemnizará, defenderá y mantendrá fuera de todo litigio al Proveedor de y contra cualquier reclamo, juicio, daño y perjuicio, incluyendo los gastos de defensa, que surgieren de cualquier reclamo en relación con terceros cuyos derechos hayan sido o sean violados o infringidos debido al material provisto por el Cliente.</p><p>4.3. Todo texto e información aportado por el Cliente se entregará al Proveedor en formato digital, preparado para su inserción en los Proyectos. Cuando algún material fuere provisto por el Cliente en otro soporte, tal como fotografías, ilustraciones u otro material visual, textos en papel, etc. deberá ser de calidad profesional y dispuesto para su digitalización sin más preparación o alteración. Este proceso (escaneado, OCR, tipeado, etc.) será presupuestado como un servicio suplementario.</p><p><strong>5. DERECHOS Y PROPIEDAD</strong></p><p>5.1. Todos los servicios provistos por el Proveedor y aprobados bajo este contrato serán para uso exclusivo del Cliente más allá de su uso promocional propio del Proveedor.</p><p><strong>6. DURACIÓN DEL CONTRATO</strong></p><p>6.1. El Contrato tendrá una vigencia mínima de un (1) año, contada a partir de la fecha de la firma del presente Contrato.</p><p>6.2. El Cliente podrá rescindir el presente Contrato, notificándoselo por escrito al Proveedor con al menos treinta (30) días de antelación a la fecha de vencimiento inicial, o, en su caso, de cualquiera de sus prórrogas. En todo caso, la prórroga del Contrato no significará que se mantenga el mismo precio por los Servicios, sino que el precio será fijado anualmente por el Proveedor.</p><p><strong>7. EXTINCIÓN DEL CONTRATO</strong></p><p>7.1. El Contrato se extinguirá por las causas generales establecidas en la legislación vigente.</p><p>7.2. En todo caso, la extinción del Contrato antes de la finalización del período inicial o de cualquiera de sus prórrogas, no dará lugar a devolución alguna del precio abonado al Proveedor.</p><p>7.3. La no acreditación del pago del precio será causa automática de resolución del Contrato, sin perjuicio de la posible reclamación de daños y perjuicios y abono de intereses.</p><p><strong>8. NATURALEZA DE LA RELACIÓN</strong></p><p>8.1. El presente Contrato tiene carácter mercantil y se regirá por sus propias cláusulas, y en lo que en ellas no estuviere previsto, por las disposiciones del Código de Comercio, leyes especiales y usos mercantiles, y en su defecto, por el Código Civil.</p><p><strong>9. PROTECCIÓN DE DATOS DE CARÁCTER PERSONAL</strong></p><p>9.1. Debido a la naturaleza de los Servicios, el Proveedor puede tener que realizar tratamientos automatizados de ficheros del Cliente que contengan datos de carácter personal. En cualquier caso, será el Cliente quien decida sobre la finalidad, contenido y uso del tratamiento de los datos, limitándose el Proveedor a utilizar dichos datos única y exclusivamente para los fines que figuran en el Contrato y siempre por cuenta del Cliente.</p><p>9.2. El Cliente únicamente permitirá el acceso a datos de carácter personal al Proveedor cuando sea necesario para la ejecución del objeto del Contrato.</p><p>9.3. El Cliente afirma y garantiza que los datos han sido recogidos de acuerdo a lo establecido en la LOPD. El Proveedor se exonera de toda responsabilidad que pueda surgir en caso de reclamación por incumplimiento de lo anteriormente garantizado. En caso de que se declare la responsabilidad del Proveedor mediante un procedimiento judicial, administrativo o arbitral, el Cliente queda obligado a indemnizar al Proveedor por los daños y perjuicios que se le causen.</p><p><strong>10. CONFIDENCIALIDAD</strong></p><p>10.1. El Proveedor considerará confidencial toda la información relacionada con los Servicios, y que obtenga durante la prestación de los mismos, salvo que dicha información le fuera conocida previamente o hubiera sido divulgada públicamente.</p><p><strong>11. RESPONSABILIDAD DEL PROVEEDOR</strong></p><p>11.1. Salvo en los casos de culpa grave o dolo, la responsabilidad total del Proveedor en relación con el Contrato estará sujeta a las limitaciones siguientes:</p><p>§ La responsabilidad total que, por cualquier concepto, pueda ser obtenida del Proveedor por el Cliente en relación con los daños directos causados al Cliente a consecuencia de los actos u omisiones realizados por el Proveedor en el ámbito del Contrato no excederá, en su conjunto, de la cantidad correspondiente al precio abonado al Proveedor por el Cliente por los Servicios durante la última anualidad.</p><p>§ El Proveedor no será responsable, en ningún caso, de los daños que puedan ser calificados como daños indirectos, consecuenciales, pérdida de beneficio o de resultados previstos, negocio, ingresos, clientes, datos, imagen, reputación comercial en el mercado, así como de los derivados de su imposibilidad de prestar los Servicios por causas que estuvieran fuera de su control.</p><p><strong>12. ACTUALIZACIÓN</strong></p><p>12.1. En el caso de que alguna o algunas de las cláusulas del Contrato pasen a ser inválidas, ilegales o inejecutables en virtud de alguna norma jurídica, se considerarán ineficaces en la medida que corresponda, pero en lo demás, este Contrato conservará su validez.</p><p>12.2. Para ese caso, las Partes acuerdan sustituir la cláusula o cláusulas afectadas por otra u otras que tengan los efectos económicos más semejantes a los de las sustituidas. CONTRATO ÚNICO</p><p><strong>13. NOTIFICACIONES Y REQUERIMIENTOS</strong></p><p>13.1. Toda notificación o requerimiento que traiga su causa del Contrato se deberá remitir por escrito a la otra Parte, bien por E-mail, bien personalmente, o por mensajero o correo certificado con acuse de recibo a portes pagados a las personas y direcciones que aparecen en el apartado \"Reunidos\" del presente Contrato.</p><p><strong>14. JURISDICCIÓN Y COMPETENCIA</strong></p><p>14.1. Las Partes, con renuncia expresa a cualquier otro fuero que pudiera corresponderles, se someten para cuantos asuntos litigiosos pudieran derivarse en todo lo referente a la interpretación, aplicación o cumplimiento y ejecución del presente Contrato, a la jurisdicción y competencia de los Juzgados y Tribunales de Córdoba.</p><p>14.2. Y para que así conste, y en prueba de conformidad y aceptación de todo cuanto antecede, las Partes firman el presente Contrato por duplicado ejemplar y a un sólo efecto en la fecha y lugar indicados en el encabezamiento</p><p><strong>NOTAS ADHERIDAS AL CONTRATO: DETALLES FACTURACIÓN</strong></p><p>Para la facturación de la tarifa de los servicios establecidas en el presente contrato se emitirá remesa bancaria, con carácter mensual de la parte proporcional del total del servicio entre los meses contratados, al número de cuenta facilitado por el Cliente previa autorización mediante firma de documento SEPA.</p><p>Para la parte proporcional correspondiente a la parte fija de la tarifa establecida para el servicio, las remesas bancarias se emitirán por anticipado a partir de los días uno de cada mes, y de manera recurrente hasta la finalización del presente contrato.</p><p>Para la parte correspondiente a la parte variable de la tarifa (3% comisión por venta PVP, IVA incluido) establecida para el servicio, las remesas bancarias se emitirán a mes vencido a partir de los días uno de cada mes, y de manera recurrente hasta la finalización del presente contrato.</p><p><strong>KIT DIGITAL</strong></p><p>El cliente es beneficiario de subvención de Kit Digital de 2000 euros que se decrementará del precio de las correspondientes partidas o soluciones digitalizadoras. El cliente está obligado a cumplir con las premisas tributarias que genera la subvención durante el año siguiente para mantener la subvención y siempre según la Orden TDF/435/2024, de 9 de mayo, por la que se modifica la Orden ETD/1498/2021, de 29 de diciembre, por la que se aprueban las bases reguladoras de la concesión de ayudas para la digitalización de pequeñas empresas, microempresas y personas en situación de autoempleo, en el marco de la Agenda España Digital 2025, el Plan de Digitalización PYMEs 2021-2025 y el Plan de Recuperación, Transformación y Resiliencia de España -Financiado por la Unión Europea- Next Generation EU (Programa Kit Digital), publicada en Boletín Oficial del Estado a fecha 11 de Mayo de 2024.</p><p>No serán subvencionables el Impuesto sobre el Valor Añadido que tendrá que ser abonado por el beneficiario y cuya remesa se enviará durante los tres meses siguientes a la validación del Acuerdo de Prestación de Soluciones.</p><p>En caso de ser desestimada la ayuda (Kit Digital) por cualquier motivo ajeno a Proyecto Tress Azafatas será el cliente el que asuma el obligado cumplimento del pago del servicio prestado (2000 euros, IVA no incluido). La forma de pago se establece con emisión de remesa bancaria previamente autorizada mediante firma de documento SEPA por parte del cliente.</p><p>En el caso de que el cliente decida desistir de la ayuda dentro del plazo de los 12 meses establecidos por Kit Digital como prestación del servicio, será el cliente el que tenga el obligado cumplimiento de hacerse cargo de la cuantía de los trabajos realizados hasta dicho momento por el agente digitalizador, PROYECTO TRESS AZAFATAS en este caso. Se calculará la parte proporcional del total del servicio que irá desde el inicio del acuerdo de prestación hasta la fecha de comunicación de renuncia de la ayuda. Una vez el cliente haya abonado la citada cuantía de los trabajos realizados se procederá a la aceptación de la renuncia por parte de PROYECTO TRESS AZAFATAS como agente digitalizador.</p>";

function cargarClausulasDefault() {
    var esKD = document.getElementById("kitDigitalCheck").checked;
    var contenido = esKD ? CLAUSULAS_KIT_DIGITAL : CLAUSULAS_NORMAL;
    var tipo = esKD ? "Kit Digital" : "estándar";
    if (clausulasQuill.getText().trim() !== "" && !confirm("¿Cargar las cláusulas " + tipo + "? Esto reemplazará el contenido actual.")) return;
    clausulasQuill.root.innerHTML = contenido;
    document.getElementById("clausulasHidden").value = contenido;
    document.getElementById("clausulasEstado").innerHTML = "✏️ Cláusulas " + tipo + " cargadas para editar";
}

function limpiarClausulas() {
    if (!confirm("¿Vaciar el editor? El PDF usará las cláusulas estándar automáticamente.")) return;
    clausulasQuill.setText("");
    document.getElementById("clausulasHidden").value = "";
    document.getElementById("clausulasEstado").innerHTML = "✅ Usando cláusulas estándar de Tictac";
}

// ── DATA ───────────────────────────────────────────────────────
let itemCounter = ' . count($existingItems) . ';
const articulosData = ' . $articulosJson . ';
const clientesData  = ' . $clientesJson . ';
const selClienteId  = "' . $selectedClienteId . '";

// ── GENERIC SEARCHABLE SELECT ──────────────────────────────────
function makeSearchableSelect(cfg) {
    const main   = document.getElementById(cfg.mainId);
    const drop   = document.getElementById(cfg.dropId);
    const search = document.getElementById(cfg.searchId);
    const optsEl = document.getElementById(cfg.optsId);
    const txtEl  = document.getElementById(cfg.txtId);
    let open = false, filtered = [], hi = -1;
    function render(q) {
        q = (q||"").toLowerCase();
        filtered = cfg.items.filter(i => { const l=(cfg.label(i)||"").toLowerCase(); const s=(cfg.sub?cfg.sub(i):"").toLowerCase(); return l.includes(q)||s.includes(q); });
        if (!filtered.length) { optsEl.innerHTML=\'<div class="ss-none">Sin resultados</div>\'; return; }
        optsEl.innerHTML = filtered.map((item,i) => { const l=cfg.label(item); const s=cfg.sub?cfg.sub(item):""; const p=cfg.price?cfg.price(item):""; const sel=cfg.isSelected?cfg.isSelected(item):false; return `<div class="ss-option ${sel?"ss-sel":""}" data-i="${i}">${p?`<span class="ss-price">${p}</span>`:""}<strong>${esc(l)}</strong>${s?`<span class="ss-sub">${esc(s)}</span>`:""}</div>`; }).join("");
        optsEl.querySelectorAll(".ss-option").forEach((el,i)=>el.addEventListener("click",()=>pick(i))); hi=-1;
    }
    function pick(i) { const item=filtered[i]; if(!item) return; txtEl.textContent=cfg.label(item); txtEl.classList.remove("ss-ph"); close(); if(cfg.onSelect) cfg.onSelect(item); }
    function openDD() { if(open) return; open=true; main.classList.add("ss-open"); drop.classList.add("ss-open"); search.value=""; render(""); search.focus(); }
    function close() { open=false; main.classList.remove("ss-open"); drop.classList.remove("ss-open"); }
    main.addEventListener("click", e=>{e.stopPropagation(); open?close():openDD();});
    search.addEventListener("input", e=>render(e.target.value));
    search.addEventListener("keydown", e=>{
        const opts=optsEl.querySelectorAll(".ss-option");
        if(e.key==="ArrowDown"){e.preventDefault();hi=Math.min(hi+1,opts.length-1);opts.forEach((o,i)=>o.classList.toggle("ss-hi",i===hi));if(opts[hi])opts[hi].scrollIntoView({block:"nearest"});}
        else if(e.key==="ArrowUp"){e.preventDefault();hi=Math.max(hi-1,0);opts.forEach((o,i)=>o.classList.toggle("ss-hi",i===hi));}
        else if(e.key==="Enter"&&hi>=0){e.preventDefault();pick(hi);}
        else if(e.key==="Escape") close();
    });
    document.addEventListener("click",e=>{const w=document.getElementById(cfg.wrapperId);if(w&&!w.contains(e.target))close();});
    return {pick};
}
function esc(t){const d=document.createElement("div");d.textContent=t;return d.innerHTML;}

// ── CLIENTE SS ─────────────────────────────────────────────────
makeSearchableSelect({
    wrapperId:"clienteWrapper",mainId:"clienteMain",dropId:"clienteDropdown",
    searchId:"clienteSearch",optsId:"clienteOptions",txtId:"clienteText",
    items:clientesData, label:c=>c.company_name||"",
    sub:c=>(c.city||"")+(c.phone?" · "+c.phone:""),
    isSelected:c=>String(c.id)===String(selClienteId),
    onSelect:cliente=>{
        document.getElementById("clienteSelect").value=cliente.id;
        document.getElementById("clienteNombre").value=cliente.company_name||"";
        document.getElementById("clienteDireccion").value=cliente.address||"";
        document.getElementById("clienteCiudad").value=cliente.city||"";
        document.getElementById("clienteCp").value=cliente.zip||"";
        document.getElementById("clientePais").value=cliente.country||"";
        document.getElementById("clienteCif").value=cliente.vat_number||"";
        fetch("../presupuestos/get_contacto.php?client_id="+cliente.id).then(r=>r.json()).then(contactos=>{
            if(contactos&&contactos.length>0){
                const cp=contactos.find(c=>c.is_primary_contact==="1")||contactos[0];
                document.getElementById("clienteEmail").value=cp.email||"";
                const fn=((cp.first_name||"")+" "+(cp.last_name||"")).trim();
                if(fn&&document.getElementById("clienteFirmante")) document.getElementById("clienteFirmante").value=fn;
            }
        }).catch(()=>{});
    }
});
if(selClienteId){const pre=clientesData.find(c=>String(c.id)===String(selClienteId));if(pre){const t=document.getElementById("clienteText");t.textContent=pre.company_name||"";t.classList.remove("ss-ph");}}

// ── ARTÍCULOS SS PER ITEM ──────────────────────────────────────
const artSS={};
function initArtSS(idx){
    artSS[idx]=makeSearchableSelect({
        wrapperId:"artWrapper_"+idx,mainId:"artMain_"+idx,dropId:"artDropdown_"+idx,
        searchId:"artSearch_"+idx,optsId:"artOptions_"+idx,txtId:"artText_"+idx,
        items:articulosData,label:a=>a.title||"",
        sub:a=>(a.category_title||"")+(a.unit_type?" · "+a.unit_type:""),
        price:a=>a.rate!=null?parseFloat(a.rate).toFixed(2)+" €":"",
        onSelect:art=>{
            const row=document.querySelector(`[data-item-index="${idx}"]`);if(!row)return;
            row.querySelector(".item-nombre").value=art.title||"";
            row.querySelector(".item-descripcion").value=art.description||"";
            row.querySelector(".item-precio").value=parseFloat(art.rate||0).toFixed(2);
            row.querySelector(".item-unidad").value=art.unit_type||"";
            calcTotals();
        }
    });
}
document.querySelectorAll("[data-item-index]").forEach(row=>initArtSS(parseInt(row.getAttribute("data-item-index"))));

// ── ADD / REMOVE ITEMS ─────────────────────────────────────────
function addItem(){
    const idx=itemCounter;
    const div=document.createElement("div");
    div.className="item-row";div.setAttribute("data-item-index",idx);
    div.innerHTML=`<div class="form-group"><label>Artículo/Servicio</label><div class="ss-wrapper" id="artWrapper_${idx}"><div class="ss-main" id="artMain_${idx}"><span class="ss-selected ss-ph" id="artText_${idx}">-- Buscar en catálogo --</span><div class="ss-arrow"></div></div><div class="ss-dropdown" id="artDropdown_${idx}"><div class="ss-search"><input type="text" id="artSearch_${idx}" placeholder="Buscar artículo..." autocomplete="off"></div><div class="ss-options" id="artOptions_${idx}"></div></div></div><input type="text" name="items[${idx}][nombre]" placeholder="Nombre del servicio" required class="item-nombre" style="margin-top:10px;"><textarea name="items[${idx}][descripcion]" placeholder="Descripción" class="item-descripcion" style="margin-top:10px;padding:10px;border:2px solid #e0e0e0;border-radius:8px;font-size:14px;min-height:50px;"></textarea></div><div class="form-group"><label>Cantidad</label><input type="number" name="items[${idx}][cantidad]" placeholder="1" step="0.01" value="1" required class="item-cantidad"><input type="text" name="items[${idx}][unidad]" placeholder="Mensual, Único..." class="item-unidad" style="margin-top:10px;padding:10px;border:2px solid #e0e0e0;border-radius:8px;font-size:13px;"></div><div class="form-group"><label>Precio con descuento (€)</label><input type="number" name="items[${idx}][precio]" placeholder="0.00" step="0.01" required class="item-precio"><div class="precio-original-wrap"><label style="font-size:11px;color:#888;font-weight:400;">Precio original sin descuento (€) — opcional</label><input type="number" name="items[${idx}][precio_original]" placeholder="Dejar vacío si no hay descuento" step="0.01" class="item-precio-original" style="width:100%;padding:8px 10px;border:2px dashed #e0a0c0;border-radius:8px;font-size:13px;color:#999;background:#fff8fb;margin-top:2px;box-sizing:border-box;"><div class="precio-original-preview" id="prevOriginal_${idx}" style="display:none;font-size:12px;color:#E91E8C;text-decoration:line-through;margin-top:4px;font-weight:600;"></div></div><div class="item-info"><strong>Total: <span class="item-total-display">0.00 €</span></strong></div><input type="hidden" class="item-total" value="0"></div><button type="button" class="btn-remove" onclick="removeItem(this)">🗑️</button>`;
    document.getElementById("itemsContainer").appendChild(div);
    itemCounter++;initArtSS(idx);attachItemListeners(div);
}
function removeItem(btn){
    if(document.querySelectorAll(".item-row").length>1){btn.closest(".item-row").remove();calcTotals();}
    else alert("Debe haber al menos un servicio en el contrato.");
}

// ── TOTALS ─────────────────────────────────────────────────────
function fmtCurr(n){return new Intl.NumberFormat("es-ES",{style:"currency",currency:"EUR"}).format(n);}
function calcTotals(){
    let sub=0;
    document.querySelectorAll(".item-row").forEach(row=>{
        const c=parseFloat(row.querySelector(".item-cantidad").value)||0;
        const p=parseFloat(row.querySelector(".item-precio").value)||0;
        const t=c*p;
        row.querySelector(".item-total").value=t.toFixed(2);
        row.querySelector(".item-total-display").textContent=fmtCurr(t);
        sub+=t;
    });
    const iva=parseFloat(document.getElementById("iva").value)||0;
    const seg=parseFloat(document.getElementById("segundoImpuesto").value)||0;
    const dto=parseFloat(document.getElementById("descuentoGlobal").value)||0;
    const ivaA=sub*iva/100; const segA=sub*seg/100;
    const tot=Math.max(0, sub+ivaA+segA-dto);
    document.getElementById("subtotal").textContent=fmtCurr(sub);
    document.getElementById("ivaPercent").textContent=iva.toFixed(0);
    document.getElementById("ivaAmount").textContent=fmtCurr(ivaA);
    document.getElementById("segPercent").textContent=seg.toFixed(0);
    document.getElementById("segAmount").textContent=fmtCurr(segA);
    document.getElementById("dtoAmount").textContent="-"+fmtCurr(dto);
    document.getElementById("dtoRow").style.display=dto>0?"flex":"none";
    document.getElementById("total").textContent=fmtCurr(tot);
    document.getElementById("segRow").style.display=seg>0?"flex":"none";
}
function attachItemListeners(row){
    row.querySelector(".item-cantidad").addEventListener("input",calcTotals);
    row.querySelector(".item-precio").addEventListener("input",calcTotals);
    var po = row.querySelector(".item-precio-original");
    if (po) po.addEventListener("input", function(){
        var v = parseFloat(this.value);
        var idx = row.getAttribute("data-item-index");
        var prev = document.getElementById("prevOriginal_" + idx);
        if (!isNaN(v) && v > 0) {
            if (prev) { prev.style.display = "block"; prev.textContent = "Antes: " + v.toFixed(2).replace(".", ",") + " €"; }
        } else {
            if (prev) prev.style.display = "none";
        }
    });
}
document.querySelectorAll(".item-row").forEach(attachItemListeners);
document.getElementById("iva").addEventListener("input",calcTotals);
document.getElementById("segundoImpuesto").addEventListener("input",calcTotals);
document.getElementById("descuentoGlobal").addEventListener("input",calcTotals);
calcTotals();

// ── SUBMIT ─────────────────────────────────────────────────────
document.getElementById("contratoForm").addEventListener("submit", function(e){
    e.preventDefault();
    var notasHtml=notasQuill.root.innerHTML;
    if(notasQuill.getText().trim()==="") notasHtml="";
    document.getElementById("notasHidden").value=notasHtml;

    const fd=new FormData(this);
    fd.append("action",e.submitter.value);
    let sub=0;
    document.querySelectorAll(".item-total").forEach(i=>{sub+=parseFloat(i.value)||0;});
    const iva=parseFloat(document.getElementById("iva").value)||0;
    const seg=parseFloat(document.getElementById("segundoImpuesto").value)||0;
    const dto=parseFloat(document.getElementById("descuentoGlobal").value)||0;
    const tot=Math.max(0, sub+sub*iva/100+sub*seg/100-dto);
    fd.append("subtotal",sub.toFixed(2));
    fd.append("total",tot.toFixed(2));

    fetch("api.php",{method:"POST",body:fd})
        .then(r=>r.json())
        .then(data=>{
            if(data.success){
                if(e.submitter.value==="guardar_pdf") window.open("api.php?action=pdf&id="+data.id,"_blank");
                setTimeout(()=>window.location.href="index.php",500);
            } else { alert("Error: "+(data.message||"No se pudo guardar")); }
        })
        .catch(err=>{console.error(err);alert("Error al guardar el contrato.");});
});
</script>
';
include '../includes/footer.php';
?>