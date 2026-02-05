<?php
/**
 * Editor de Presupuestos - Crear/Editar
 * VERSIÓN ACTUALIZADA: Campos manuales para clientes y artículos
 */

require_once '../config.php';

$pageTitle = 'Editor de Presupuestos';
$showBackButton = true;

// Obtener ID si estamos editando
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

// Obtener lista de clientes del CRM
$clientes = array();
for ($id = 1; $id <= 200; $id++) {
    $cliente = callCrmApi('clients/' . $id);
    if ($cliente && (!isset($cliente['deleted']) || $cliente['deleted'] == 0)) {
        $clientes[] = $cliente;
    }
}

// Obtener artículos/servicios del catálogo (consulta directa a BBDD)
$articulos = getArticulosCRM();

$additionalStyles = '
<style>
    .editor-container {
        background: white;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        padding: 40px;
        margin: 30px 0;
    }
    
    .form-section {
        margin-bottom: 40px;
    }
    
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
    
    .form-group {
        display: flex;
        flex-direction: column;
    }
    
    .form-group label {
        font-weight: 600;
        margin-bottom: 8px;
        color: #333;
    }
    
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
    
    /* ===== SEARCHABLE SELECT STYLES ===== */
    .searchable-select-wrapper {
        position: relative;
        width: 100%;
    }
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
    .searchable-select-wrapper .ss-main.ss-open {
        border-color: ' . BRAND_COLOR . ';
    }
    .searchable-select-wrapper .ss-main .ss-selected-text {
        flex: 1;
        color: #333;
        font-size: 14px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .searchable-select-wrapper .ss-main .ss-selected-text.ss-placeholder {
        color: #999;
    }
    .searchable-select-wrapper .ss-main .ss-arrow {
        width: 0; height: 0;
        border-left: 5px solid transparent;
        border-right: 5px solid transparent;
        border-top: 5px solid #999;
        margin-left: 8px;
        transition: transform 0.2s;
    }
    .searchable-select-wrapper .ss-main.ss-open .ss-arrow {
        transform: rotate(180deg);
    }
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
    .searchable-select-wrapper .ss-dropdown.ss-open {
        display: block;
    }
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
    .searchable-select-wrapper .ss-search input:focus {
        border-color: ' . BRAND_COLOR . ';
    }
    .searchable-select-wrapper .ss-options {
        max-height: 210px;
        overflow-y: auto;
        padding: 4px 0;
    }
    .searchable-select-wrapper .ss-option {
        padding: 10px 12px;
        cursor: pointer;
        font-size: 13px;
        color: #333;
        transition: background 0.15s;
        border-bottom: 1px solid #f5f5f5;
    }
    .searchable-select-wrapper .ss-option:last-child {
        border-bottom: none;
    }
    .searchable-select-wrapper .ss-option:hover,
    .searchable-select-wrapper .ss-option.ss-highlighted {
        background: #fff0f7;
        color: ' . BRAND_COLOR . ';
    }
    .searchable-select-wrapper .ss-option.ss-selected {
        background: #ffe8f3;
        font-weight: 600;
    }
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
    /* ===== END SEARCHABLE SELECT ===== */

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
        align-items: end;
    }
    
    .item-row input,
    .item-row select {
        padding: 10px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        font-size: 14px;
    }
    
    .btn-remove {
        background: #dc3545;
        color: white;
        border: none;
        padding: 10px;
        border-radius: 8px;
        cursor: pointer;
        font-size: 18px;
        transition: all 0.3s;
    }
    
    .btn-remove:hover {
        background: #c82333;
    }
    
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
    
    .btn-add:hover {
        opacity: 0.8;
    }
    
    .totals-section {
        background: #f0f0f0;
        padding: 20px;
        border-radius: 8px;
        margin-top: 30px;
    }
    
    .total-row {
        display: flex;
        justify-content: space-between;
        padding: 10px 0;
        font-size: 16px;
    }
    
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
    
    .btn-secondary {
        background: #6c757d;
        color: white;
    }
    .btn-secondary:hover { background: #5a6268; }
    
    .btn-primary {
        background: ' . BRAND_COLOR . ';
        color: white;
    }
    .btn-primary:hover {
        background: ' . BRAND_COLOR_DARK . ';
        transform: translateY(-2px);
    }
    
    .btn-success {
        background: #28a745;
        color: white;
    }
    .btn-success:hover { background: #218838; }
    
    .item-info {
        font-size: 11px;
        color: #666;
        margin-top: 5px;
    }
    
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
        
        <!-- DATOS DEL CLIENTE (editable manualmente o desde CRM) -->
        <div class="form-section">
            <h3>👤 Información del Cliente</h3>
            
            <div class="form-row">
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label>Seleccionar Cliente del CRM (opcional)</label>
                    <!-- Hidden select real para el form -->
                    <select name="cliente_id" id="clienteSelect" style="display:none;">
                        <option value="">-- Opcional: buscar en CRM --</option>
                        <?php foreach ($clientes as $cliente): ?>
                            <option value="<?php echo $cliente['id']; ?>" 
                                    data-nombre="<?php echo htmlspecialchars($cliente['company_name'] ?? ''); ?>"
                                    data-email="<?php echo htmlspecialchars($cliente['email'] ?? ''); ?>"
                                    data-telefono="<?php echo htmlspecialchars($cliente['phone'] ?? ''); ?>"
                                    data-direccion="<?php echo htmlspecialchars($cliente['address'] ?? ''); ?>"
                                    data-ciudad="<?php echo htmlspecialchars($cliente['city'] ?? ''); ?>"
                                    data-cp="<?php echo htmlspecialchars($cliente['zip'] ?? ''); ?>"
                                    data-pais="<?php echo htmlspecialchars($cliente['country'] ?? ''); ?>"
                                    data-cif="<?php echo htmlspecialchars($cliente['vat_number'] ?? ''); ?>"
                                    <?php echo ($presupuesto && $presupuesto['cliente_id'] == $cliente['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cliente['company_name'] ?? ''); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <!-- Searchable select visible -->
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
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="cliente_email" id="clienteEmail" required
                           placeholder="email@ejemplo.com"
                           value="<?php echo $presupuesto ? htmlspecialchars($presupuesto['cliente_email'] ?? '') : ''; ?>">
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
                            <!-- Hidden select real -->
                            <select class="articulo-select" data-index="<?php echo $index; ?>" style="display:none;">
                                <option value="">-- Opcional: seleccionar del catálogo --</option>
                                <?php foreach ($articulos as $art): ?>
                                    <option value="<?php echo htmlspecialchars(json_encode($art)); ?>"
                                            <?php echo ($item && ($item['nombre'] ?? '') == ($art['title'] ?? '')) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($art['title'] ?? ''); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <!-- Searchable select visible -->
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
                            <textarea name="items[<?php echo $index; ?>][descripcion]" 
                                      placeholder="Descripción del servicio" class="item-descripcion" 
                                      style="margin-top: 10px; padding: 10px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px; min-height: 60px;"><?php echo $item ? htmlspecialchars($item['descripcion'] ?? '') : ''; ?></textarea>
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
                            <label>Precio Unitario (€)</label>
                            <input type="number" name="items[<?php echo $index; ?>][precio]" 
                                   placeholder="0.00" step="0.01" required class="item-precio"
                                   value="<?php echo $item ? ($item['precio'] ?? 0) : ''; ?>">
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
                    <input type="number" name="iva" id="iva" step="0.01" value="<?php echo $presupuesto ? ($presupuesto['iva'] ?? 21) : 21; ?>" min="0">
                </div>
                <div class="form-group">
                    <label>Segundo Impuesto (%) - Opcional</label>
                    <input type="number" name="segundo_impuesto" id="segundoImpuesto" step="0.01" value="<?php echo $presupuesto ? ($presupuesto['segundo_impuesto'] ?? 0) : 0; ?>" min="0">
                </div>
            </div>
        </div>
        
        <!-- NOTAS -->
        <div class="form-section">
            <h3>📝 Notas Adicionales</h3>
            <div class="form-group">
                <label>Notas (opcional)</label>
                <textarea name="notas" rows="4" placeholder="Condiciones de pago, garantías, etc."><?php echo $presupuesto ? htmlspecialchars($presupuesto['notas'] ?? '') : ''; ?></textarea>
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

<?php
$articulosJson = json_encode($articulos, JSON_UNESCAPED_UNICODE);
$clientesJson = json_encode($clientes, JSON_UNESCAPED_UNICODE);
$selectedClienteId = $presupuesto ? ($presupuesto['cliente_id'] ?? '') : '';

$additionalScripts = '
<script>
// ============================================================
// DATOS
// ============================================================
let itemCounter = ' . count($existingItems) . ';
const articulosData = ' . $articulosJson . ';
const clientesData = ' . $clientesJson . ';
const selectedClienteId = "' . $selectedClienteId . '";

// ============================================================
// SEARCHABLE SELECT ENGINE
// ============================================================
function initSearchableSelect(config) {
    // config: { mainId, dropdownId, searchId, optionsId, textId, items, renderItem, onSelect, selectedValue }
    const main = document.getElementById(config.mainId);
    const dropdown = document.getElementById(config.dropdownId);
    const search = document.getElementById(config.searchId);
    const optionsContainer = document.getElementById(config.optionsId);
    const textSpan = document.getElementById(config.textId);

    let isOpen = false;
    let highlightedIndex = -1;
    let filteredItems = [];
    let currentSelected = config.selectedValue || null;

    function renderOptions(filter) {
        filter = (filter || "").toLowerCase().trim();
        filteredItems = config.items.filter(item => {
            const label = (config.getLabel ? config.getLabel(item) : item.label || "").toLowerCase();
            const sub = (config.getSub ? config.getSub(item) : item.sub || "").toLowerCase();
            return label.includes(filter) || sub.includes(filter);
        });

        if (filteredItems.length === 0) {
            optionsContainer.innerHTML = \'<div class="ss-no-results">No se encontraron resultados</div>\';
            return;
        }

        optionsContainer.innerHTML = filteredItems.map((item, i) => {
            const label = config.getLabel ? config.getLabel(item) : item.label;
            const sub = config.getSub ? config.getSub(item) : (item.sub || "");
            const price = config.getPrice ? config.getPrice(item) : "";
            const isSelected = config.isSelected ? config.isSelected(item) : false;
            return `<div class="ss-option ${isSelected ? "ss-selected" : ""}" data-index="${i}" onmousedown="event.preventDefault()">
                ${price ? `<span class="ss-option-price">${price}</span>` : ""}
                <strong>${escapeHtml(label)}</strong>
                ${sub ? `<span class="ss-option-sub">${escapeHtml(sub)}</span>` : ""}
            </div>`;
        }).join("");

        optionsContainer.querySelectorAll(".ss-option").forEach((opt, i) => {
            opt.addEventListener("click", () => selectItem(i));
        });
        highlightedIndex = -1;
    }

    function selectItem(index) {
        const item = filteredItems[index];
        if (!item) return;
        currentSelected = item;
        const label = config.getLabel ? config.getLabel(item) : item.label;
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

    // Events
    main.addEventListener("click", (e) => { e.stopPropagation(); toggle(); });
    search.addEventListener("input", (e) => { renderOptions(e.target.value); });
    search.addEventListener("keydown", (e) => {
        const opts = optionsContainer.querySelectorAll(".ss-option");
        if (e.key === "ArrowDown") {
            e.preventDefault();
            highlightedIndex = Math.min(highlightedIndex + 1, opts.length - 1);
            opts.forEach((o, i) => o.classList.toggle("ss-highlighted", i === highlightedIndex));
            if (opts[highlightedIndex]) opts[highlightedIndex].scrollIntoView({block:"nearest"});
        } else if (e.key === "ArrowUp") {
            e.preventDefault();
            highlightedIndex = Math.max(highlightedIndex - 1, 0);
            opts.forEach((o, i) => o.classList.toggle("ss-highlighted", i === highlightedIndex));
            if (opts[highlightedIndex]) opts[highlightedIndex].scrollIntoView({block:"nearest"});
        } else if (e.key === "Enter" && highlightedIndex >= 0) {
            e.preventDefault();
            selectItem(highlightedIndex);
        } else if (e.key === "Escape") {
            close();
        }
    });

    // Close on outside click
    document.addEventListener("click", (e) => {
        const wrapper = document.getElementById(config.mainId.replace("main","Wrapper") || config.mainId);
        if (wrapper && !wrapper.contains(e.target)) {
            close();
        }
    });

    return { open, close, selectItem, renderOptions };
}

// ============================================================
// CLIENTE SEARCHABLE SELECT
// ============================================================
const clienteSS = initSearchableSelect({
    mainId: "clienteSSmain",
    dropdownId: "clienteSS-dropdown",
    searchId: "clienteSSsearch",
    optionsId: "clienteSSoptions",
    textId: "clienteSStext",
    items: clientesData,
    getLabel: (c) => c.company_name || "",
    getSub: (c) => (c.email || "") + (c.city ? " · " + c.city : ""),
    isSelected: (c) => String(c.id) === String(selectedClienteId),
    selectedValue: clientesData.find(c => String(c.id) === String(selectedClienteId)) || null,
    onSelect: (cliente) => {
        document.getElementById("clienteSelect").value = cliente.id;
        document.getElementById("clienteNombre").value = cliente.company_name || "";
        document.getElementById("clienteTelefono").value = cliente.phone || "";
        document.getElementById("clienteDireccion").value = cliente.address || "";
        document.getElementById("clienteCiudad").value = cliente.city || "";
        document.getElementById("clienteCp").value = cliente.zip || "";
        document.getElementById("clientePais").value = cliente.country || "";
        document.getElementById("clienteCif").value = cliente.vat_number || "";
        
        // Obtener email del contacto principal
        fetch("get_contacto.php?client_id=" + cliente.id)
            .then(response => response.json())
            .then(contactos => {
                if (contactos && contactos.length > 0) {
                    // Buscar contacto principal
                    const contactoPrincipal = contactos.find(c => c.is_primary_contact === "1");
                    const email = contactoPrincipal ? contactoPrincipal.email : (contactos[0].email || cliente.email || "");
                    document.getElementById("clienteEmail").value = email;
                } else {
                    // Fallback al email del cliente si no hay contactos
                    document.getElementById("clienteEmail").value = cliente.email || "";
                }
            })
            .catch(error => {
                console.error("Error obteniendo contacto:", error);
                // Fallback al email del cliente
                document.getElementById("clienteEmail").value = cliente.email || "";
            });
    }
});

// Si hay cliente preseleccionado, mostrar nombre
if (selectedClienteId) {
    const preSelected = clientesData.find(c => String(c.id) === String(selectedClienteId));
    if (preSelected) {
        document.getElementById("clienteSStext").textContent = preSelected.company_name || "";
        document.getElementById("clienteSStext").classList.remove("ss-placeholder");
    }
}

// ============================================================
// ARTÍCULOS SEARCHABLE SELECT (por cada item-row)
// ============================================================
const articulosSS = {};

function initArticuloSS(index) {
    const wrapperId = "artSSWrapper_" + index;
    const mainId = "artSSmain_" + index;
    const dropdownId = "artSS-dropdown_" + index;
    const searchId = "artSSsearch_" + index;
    const optionsId = "artSSoptions_" + index;
    const textId = "artSStext_" + index;

    // Solo artículos del catálogo (sin opción "Personalizado")
    const items = articulosData;

    articulosSS[index] = initSearchableSelect({
        mainId: mainId,
        dropdownId: dropdownId,
        searchId: searchId,
        optionsId: optionsId,
        textId: textId,
        items: items,
        getLabel: (a) => a.title || "",
        getSub: (a) => (a.category_title || "") + (a.unit_type ? " · " + a.unit_type : ""),
        getPrice: (a) => a.rate != null ? parseFloat(a.rate).toFixed(2) + " €" : "",
        onSelect: (articulo) => {
            const row = document.querySelector(`[data-item-index="${index}"]`);
            if (!row) return;
            const nombreInput = row.querySelector(".item-nombre");
            const descripcionInput = row.querySelector(".item-descripcion");
            const precioInput = row.querySelector(".item-precio");
            const unidadInput = row.querySelector(".item-unidad");

            // Rellenar campos con datos del artículo seleccionado
            nombreInput.value = articulo.title || "";
            descripcionInput.value = articulo.description || "";
            precioInput.value = parseFloat(articulo.rate || 0).toFixed(2);
            unidadInput.value = articulo.unit_type || "";
            
            calculateTotals();
        }
    });
}

// Init all existing article selects
document.querySelectorAll("[data-item-index]").forEach(row => {
    const idx = row.getAttribute("data-item-index");
    initArticuloSS(parseInt(idx));
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
    newItem.innerHTML = `
        <div class="form-group">
            <label>Artículo/Servicio</label>
            <select class="articulo-select" data-index="${idx}" style="display:none;">
                <option value="">-- Opcional: seleccionar del catálogo --</option>
            </select>
            <div class="searchable-select-wrapper" id="artSSWrapper_${idx}">
                <div class="ss-main" id="artSSmain_${idx}">
                    <span class="ss-selected-text ss-placeholder" id="artSStext_${idx}">-- Opcional: buscar en catálogo --</span>
                    <div class="ss-arrow"></div>
                </div>
                <div class="ss-dropdown" id="artSS-dropdown_${idx}">
                    <div class="ss-search"><input type="text" id="artSSsearch_${idx}" placeholder="Buscar artículo..." autocomplete="off"></div>
                    <div class="ss-options" id="artSSoptions_${idx}"></div>
                </div>
            </div>
            <input type="text" name="items[${idx}][nombre]" placeholder="Nombre del servicio" required class="item-nombre" style="margin-top:10px;">
            <textarea name="items[${idx}][descripcion]" placeholder="Descripción del servicio" class="item-descripcion" style="margin-top:10px; padding:10px; border:2px solid #e0e0e0; border-radius:8px; font-size:14px; min-height:60px;"></textarea>
        </div>
        <div class="form-group">
            <label>Cantidad</label>
            <input type="number" name="items[${idx}][cantidad]" placeholder="1" step="0.01" value="1" required class="item-cantidad">
            <input type="text" name="items[${idx}][unidad]" placeholder="Tipo (ej: Mensual)" class="item-unidad" style="margin-top:10px; padding:10px; border:2px solid #e0e0e0; border-radius:8px; font-size:13px;">
        </div>
        <div class="form-group">
            <label>Precio Unitario (€)</label>
            <input type="number" name="items[${idx}][precio]" placeholder="0.00" step="0.01" required class="item-precio">
            <div class="item-info"><strong>Total: <span class="item-total-display">0.00 €</span></strong></div>
            <input type="hidden" class="item-total" value="0">
        </div>
        <button type="button" class="btn-remove" onclick="removeItem(this)">🗑️</button>
    `;
    container.appendChild(newItem);
    itemCounter++;
    
    // Init searchable select for new item
    initArticuloSS(idx);
    attachItemListeners(newItem);
}

function removeItem(button) {
    const items = document.querySelectorAll(".item-row");
    if (items.length > 1) {
        button.closest(".item-row").remove();
        calculateTotals();
    } else {
        alert("Debe haber al menos un artículo en el presupuesto");
    }
}

// ============================================================
// TOTALES
// ============================================================
function calculateItemTotal(itemRow) {
    const cantidad = parseFloat(itemRow.querySelector(".item-cantidad").value) || 0;
    const precio = parseFloat(itemRow.querySelector(".item-precio").value) || 0;
    const total = cantidad * precio;
    itemRow.querySelector(".item-total").value = total.toFixed(2);
    itemRow.querySelector(".item-total-display").textContent = formatCurrency(total);
}

function calculateTotals() {
    let subtotal = 0;
    document.querySelectorAll(".item-row").forEach(row => {
        calculateItemTotal(row);
        subtotal += parseFloat(row.querySelector(".item-total").value) || 0;
    });

    const iva = parseFloat(document.getElementById("iva").value) || 0;
    const seg = parseFloat(document.getElementById("segundoImpuesto").value) || 0;
    const ivaAmt = (subtotal * iva) / 100;
    const segAmt = (subtotal * seg) / 100;
    const total = subtotal + ivaAmt + segAmt;

    document.getElementById("subtotal").textContent = formatCurrency(subtotal);
    document.getElementById("ivaPercent").textContent = iva.toFixed(0);
    document.getElementById("ivaAmount").textContent = formatCurrency(ivaAmt);
    document.getElementById("segundoImpuestoPercent").textContent = seg.toFixed(0);
    document.getElementById("segundoImpuestoAmount").textContent = formatCurrency(segAmt);
    document.getElementById("total").textContent = formatCurrency(total);
    document.getElementById("segundoImpuestoRow").style.display = seg > 0 ? "flex" : "none";
}

function formatCurrency(amount) {
    return new Intl.NumberFormat("es-ES", { style: "currency", currency: "EUR" }).format(amount);
}

function escapeHtml(text) {
    const div = document.createElement("div");
    div.textContent = text;
    return div.innerHTML;
}

function attachItemListeners(itemRow) {
    itemRow.querySelector(".item-cantidad").addEventListener("input", calculateTotals);
    itemRow.querySelector(".item-precio").addEventListener("input", calculateTotals);
}

// Init listeners on existing items
document.querySelectorAll(".item-row").forEach(attachItemListeners);
document.getElementById("iva").addEventListener("input", calculateTotals);
document.getElementById("segundoImpuesto").addEventListener("input", calculateTotals);
calculateTotals();

// ============================================================
// SUBMIT
// ============================================================
document.getElementById("presupuestoForm").addEventListener("submit", function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const action = e.submitter.value;
    formData.append("action", action);

    let totalSubtotal = 0;
    document.querySelectorAll(".item-total").forEach(input => {
        totalSubtotal += parseFloat(input.value) || 0;
    });

    const iva = parseFloat(document.getElementById("iva").value) || 0;
    const seg = parseFloat(document.getElementById("segundoImpuesto").value) || 0;
    const total = totalSubtotal + (totalSubtotal * iva / 100) + (totalSubtotal * seg / 100);

    formData.append("subtotal", totalSubtotal.toFixed(2));
    formData.append("total", total.toFixed(2));

    fetch("api.php", { method: "POST", body: formData })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            if (action === "guardar_pdf") {
                window.open("api.php?action=pdf&id=" + data.id, "_blank");
            }
            setTimeout(() => { window.location.href = "index.php"; }, 500);
        } else {
            alert("Error: " + (data.message || "No se pudo guardar"));
        }
    })
    .catch(err => { console.error(err); alert("Error al guardar el presupuesto"); });
});
</script>
';

include '../includes/footer.php';
?>