<?php
/**
 * Editor de Presupuestos - Crear/Editar
 * VERSIÓN ACTUALIZADA: Carga artículos del CRM
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
    
    .item-row .articulo-select {
        grid-column: 1 / 2;
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
    
    .btn-secondary:hover {
        background: #5a6268;
    }
    
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
    
    .btn-success:hover {
        background: #218838;
    }
    
    .item-info {
        font-size: 11px;
        color: #666;
        margin-top: 5px;
    }
    
    @media (max-width: 768px) {
        .item-row {
            grid-template-columns: 1fr;
        }
        
        .actions-bar {
            flex-direction: column;
        }
        
        .btn {
            width: 100%;
        }
    }
</style>
';

include '../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1><?php echo $editId ? '✏️ Editar Presupuesto' : '📝 Nuevo Presupuesto'; ?></h1>
        <p>Selecciona servicios del catálogo o añade personalizados</p>
    </div>
    
    <form id="presupuestoForm" class="editor-container">
        <!-- ID oculto para edición -->
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
                    <label>Seleccionar Cliente del CRM *</label>
                    <select name="cliente_id" id="clienteSelect" required>
                        <option value="">-- Seleccionar cliente --</option>
                        <?php foreach ($clientes as $cliente): ?>
                            <option value="<?php echo $cliente['id']; ?>" 
                                    data-nombre="<?php echo htmlspecialchars($cliente['company_name']); ?>"
                                    data-email="<?php echo htmlspecialchars($cliente['email'] ?? ''); ?>"
                                    data-telefono="<?php echo htmlspecialchars($cliente['phone'] ?? ''); ?>"
                                    data-direccion="<?php echo htmlspecialchars($cliente['address'] ?? ''); ?>"
                                    data-ciudad="<?php echo htmlspecialchars($cliente['city'] ?? ''); ?>"
                                    data-cp="<?php echo htmlspecialchars($cliente['zip'] ?? ''); ?>"
                                    data-pais="<?php echo htmlspecialchars($cliente['country'] ?? ''); ?>"
                                    data-cif="<?php echo htmlspecialchars($cliente['vat_number'] ?? ''); ?>"
                                    <?php echo ($presupuesto && $presupuesto['cliente_id'] == $cliente['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cliente['company_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Nombre/Empresa *</label>
                    <input type="text" name="cliente_nombre" id="clienteNombre" required readonly
                           value="<?php echo $presupuesto ? htmlspecialchars($presupuesto['cliente_nombre']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="cliente_email" id="clienteEmail" required
                           value="<?php echo $presupuesto ? htmlspecialchars($presupuesto['cliente_email']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label>Teléfono</label>
                    <input type="text" name="cliente_telefono" id="clienteTelefono"
                           value="<?php echo $presupuesto ? htmlspecialchars($presupuesto['cliente_telefono'] ?? '') : ''; ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label>Dirección</label>
                    <input type="text" name="cliente_direccion" id="clienteDireccion"
                           value="<?php echo $presupuesto ? htmlspecialchars($presupuesto['cliente_direccion'] ?? '') : ''; ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Ciudad</label>
                    <input type="text" name="cliente_ciudad" id="clienteCiudad"
                           value="<?php echo $presupuesto ? htmlspecialchars($presupuesto['cliente_ciudad'] ?? '') : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label>Código Postal</label>
                    <input type="text" name="cliente_cp" id="clienteCp"
                           value="<?php echo $presupuesto ? htmlspecialchars($presupuesto['cliente_cp'] ?? '') : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label>País</label>
                    <input type="text" name="cliente_pais" id="clientePais"
                           value="<?php echo $presupuesto ? htmlspecialchars($presupuesto['cliente_pais'] ?? '') : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label>CIF/NIF</label>
                    <input type="text" name="cliente_cif" id="clienteCif"
                           value="<?php echo $presupuesto ? htmlspecialchars($presupuesto['cliente_cif'] ?? '') : ''; ?>">
                </div>
            </div>
        </div>
        
        <!-- ARTÍCULOS/SERVICIOS -->
        <div class="form-section">
            <h3>📦 Artículos y Servicios</h3>
            
            <div class="items-section" id="itemsContainer">
                <?php if ($presupuesto && isset($presupuesto['items'])): ?>
                    <?php foreach ($presupuesto['items'] as $index => $item): ?>
                        <div class="item-row">
                            <div class="form-group">
                                <label>Artículo/Servicio del Catálogo</label>
                                <select class="articulo-select" data-index="<?php echo $index; ?>">
                                    <option value="">-- Seleccionar o escribir personalizado --</option>
                                    <?php foreach ($articulos as $art): ?>
                                        <option value="<?php echo htmlspecialchars(json_encode($art)); ?>"
                                                <?php echo ($item['nombre'] == $art['title']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($art['title']); ?> - <?php echo number_format($art['rate'] ?? 0, 2); ?>€
                                        </option>
                                    <?php endforeach; ?>
                                    <option value="custom">✏️ Personalizado</option>
                                </select>
                                <input type="text" name="items[<?php echo $index; ?>][nombre]" 
                                       placeholder="Nombre del servicio" required class="item-nombre" style="display: none; margin-top: 10px;"
                                       value="<?php echo htmlspecialchars($item['nombre']); ?>">
                                <textarea name="items[<?php echo $index; ?>][descripcion]" 
                                          placeholder="Descripción" class="item-descripcion" style="margin-top: 10px; padding: 10px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px; min-height: 60px;"><?php echo htmlspecialchars($item['descripcion'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label>Cantidad</label>
                                <input type="number" name="items[<?php echo $index; ?>][cantidad]" 
                                       placeholder="1" step="0.01" required class="item-cantidad"
                                       value="<?php echo $item['cantidad']; ?>">
                                <input type="text" name="items[<?php echo $index; ?>][unidad]" 
                                       placeholder="Tipo (ej: Mensual, Único)" class="item-unidad" style="margin-top: 10px; padding: 10px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 13px;"
                                       value="<?php echo htmlspecialchars($item['unidad'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label>Precio Unitario (€)</label>
                                <input type="number" name="items[<?php echo $index; ?>][precio]" 
                                       placeholder="0.00" step="0.01" required class="item-precio"
                                       value="<?php echo $item['precio']; ?>">
                                <div class="item-info">
                                    <strong>Total: <span class="item-total-display"><?php echo number_format($item['cantidad'] * $item['precio'], 2); ?> €</span></strong>
                                </div>
                                <input type="hidden" class="item-total" value="<?php echo $item['cantidad'] * $item['precio']; ?>">
                            </div>
                            
                            <button type="button" class="btn-remove" onclick="removeItem(this)">🗑️</button>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="item-row">
                        <div class="form-group">
                            <label>Artículo/Servicio del Catálogo</label>
                            <select class="articulo-select" data-index="0">
                                <option value="">-- Seleccionar del catálogo --</option>
                                <?php foreach ($articulos as $art): ?>
                                    <option value="<?php echo htmlspecialchars(json_encode($art)); ?>">
                                        <?php echo htmlspecialchars($art['title']); ?> - <?php echo number_format($art['rate'] ?? 0, 2); ?>€
                                    </option>
                                <?php endforeach; ?>
                                <option value="custom">✏️ Personalizado</option>
                            </select>
                            <input type="text" name="items[0][nombre]" placeholder="Nombre del servicio" required class="item-nombre" style="display: none; margin-top: 10px;">
                            <textarea name="items[0][descripcion]" placeholder="Descripción" class="item-descripcion" style="margin-top: 10px; padding: 10px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px; min-height: 60px;"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>Cantidad</label>
                            <input type="number" name="items[0][cantidad]" placeholder="1" step="0.01" value="1" required class="item-cantidad">
                            <input type="text" name="items[0][unidad]" placeholder="Tipo (ej: Mensual)" class="item-unidad" style="margin-top: 10px; padding: 10px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 13px;">
                        </div>
                        
                        <div class="form-group">
                            <label>Precio Unitario (€)</label>
                            <input type="number" name="items[0][precio]" placeholder="0.00" step="0.01" required class="item-precio">
                            <div class="item-info">
                                <strong>Total: <span class="item-total-display">0.00 €</span></strong>
                            </div>
                            <input type="hidden" class="item-total" value="0">
                        </div>
                        
                        <button type="button" class="btn-remove" onclick="removeItem(this)">🗑️</button>
                    </div>
                <?php endif; ?>
            </div>
            
            <button type="button" class="btn-add" onclick="addItem()">+ Añadir Artículo</button>
        </div>
        
        <!-- IMPUESTOS -->
        <div class="form-section">
            <h3>💰 Impuestos</h3>
            
            <div class="form-row">
                <div class="form-group">
                    <label>IVA (%)</label>
                    <input type="number" name="iva" id="iva" step="0.01" value="21" min="0">
                </div>
                
                <div class="form-group">
                    <label>Segundo Impuesto (%) - Opcional</label>
                    <input type="number" name="segundo_impuesto" id="segundoImpuesto" step="0.01" value="0" min="0">
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
        
        <!-- BOTONES DE ACCIÓN -->
        <div class="actions-bar">
            <a href="index.php" class="btn btn-secondary">Cancelar</a>
            <button type="submit" name="action" value="guardar" class="btn btn-primary">💾 Guardar Borrador</button>
            <button type="submit" name="action" value="guardar_pdf" class="btn btn-success">📄 Guardar y Descargar PDF</button>
        </div>
    </form>
</div>

<?php
$articulosJson = json_encode($articulos);

$additionalScripts = '
<script>
let itemCounter = ' . ($presupuesto && isset($presupuesto['items']) ? count($presupuesto['items']) : 1) . ';
const articulosData = ' . $articulosJson . ';

// Autocompletar datos del cliente
document.getElementById("clienteSelect").addEventListener("change", function() {
    const option = this.options[this.selectedIndex];
    document.getElementById("clienteNombre").value = option.dataset.nombre || "";
    document.getElementById("clienteEmail").value = option.dataset.email || "";
    document.getElementById("clienteTelefono").value = option.dataset.telefono || "";
    document.getElementById("clienteDireccion").value = option.dataset.direccion || "";
    document.getElementById("clienteCiudad").value = option.dataset.ciudad || "";
    document.getElementById("clienteCp").value = option.dataset.cp || "";
    document.getElementById("clientePais").value = option.dataset.pais || "";
    document.getElementById("clienteCif").value = option.dataset.cif || "";
});

// Manejar selección de artículo del catálogo
function handleArticuloSelect(select) {
    const row = select.closest(".item-row");
    const nombreInput = row.querySelector(".item-nombre");
    const descripcionInput = row.querySelector(".item-descripcion");
    const precioInput = row.querySelector(".item-precio");
    const unidadInput = row.querySelector(".item-unidad");
    
    if (select.value === "custom") {
        // Modo personalizado
        nombreInput.style.display = "block";
        nombreInput.required = true;
        nombreInput.value = "";
        descripcionInput.value = "";
        precioInput.value = "";
        unidadInput.value = "";
    } else if (select.value) {
        // Artículo del catálogo seleccionado
        try {
            const articulo = JSON.parse(select.value);
            nombreInput.style.display = "none";
            nombreInput.required = false;
            nombreInput.value = articulo.title || "";
            descripcionInput.value = articulo.description || "";
            precioInput.value = parseFloat(articulo.rate || 0).toFixed(2);
            unidadInput.value = articulo.unit_type || "";
        } catch (e) {
            console.error("Error parsing articulo:", e);
        }
    } else {
        // No seleccionado
        nombreInput.style.display = "none";
        nombreInput.required = false;
        nombreInput.value = "";
        descripcionInput.value = "";
        precioInput.value = "";
        unidadInput.value = "";
    }
    
    calculateTotals();
}

// Añadir listeners a todos los selects de artículos existentes
document.querySelectorAll(".articulo-select").forEach(select => {
    select.addEventListener("change", function() {
        handleArticuloSelect(this);
    });
});

// Añadir nuevo artículo
function addItem() {
    const container = document.getElementById("itemsContainer");
    const newItem = document.createElement("div");
    newItem.className = "item-row";
    
    let articulosOptions = `<option value="">-- Seleccionar del catálogo --</option>`;
    articulosData.forEach(art => {
        articulosOptions += `<option value="${escapeHtml(JSON.stringify(art))}">${escapeHtml(art.title)} - ${parseFloat(art.rate || 0).toFixed(2)}€</option>`;
    });
    articulosOptions += `<option value="custom">✏️ Personalizado</option>`;
    
    newItem.innerHTML = `
        <div class="form-group">
            <label>Artículo/Servicio del Catálogo</label>
            <select class="articulo-select" data-index="${itemCounter}">
                ${articulosOptions}
            </select>
            <input type="text" name="items[${itemCounter}][nombre]" placeholder="Nombre del servicio" required class="item-nombre" style="display: none; margin-top: 10px;">
            <textarea name="items[${itemCounter}][descripcion]" placeholder="Descripción" class="item-descripcion" style="margin-top: 10px; padding: 10px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px; min-height: 60px;"></textarea>
        </div>
        
        <div class="form-group">
            <label>Cantidad</label>
            <input type="number" name="items[${itemCounter}][cantidad]" placeholder="1" step="0.01" value="1" required class="item-cantidad">
            <input type="text" name="items[${itemCounter}][unidad]" placeholder="Tipo (ej: Mensual)" class="item-unidad" style="margin-top: 10px; padding: 10px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 13px;">
        </div>
        
        <div class="form-group">
            <label>Precio Unitario (€)</label>
            <input type="number" name="items[${itemCounter}][precio]" placeholder="0.00" step="0.01" required class="item-precio">
            <div class="item-info">
                <strong>Total: <span class="item-total-display">0.00 €</span></strong>
            </div>
            <input type="hidden" class="item-total" value="0">
        </div>
        
        <button type="button" class="btn-remove" onclick="removeItem(this)">🗑️</button>
    `;
    container.appendChild(newItem);
    itemCounter++;
    
    // Añadir listeners al nuevo item
    const newSelect = newItem.querySelector(".articulo-select");
    newSelect.addEventListener("change", function() {
        handleArticuloSelect(this);
    });
    
    attachItemListeners(newItem);
}

// Eliminar artículo
function removeItem(button) {
    const items = document.querySelectorAll(".item-row");
    if (items.length > 1) {
        button.closest(".item-row").remove();
        calculateTotals();
    } else {
        alert("Debe haber al menos un artículo en el presupuesto");
    }
}

// Calcular totales de un item
function calculateItemTotal(itemRow) {
    const cantidad = parseFloat(itemRow.querySelector(".item-cantidad").value) || 0;
    const precio = parseFloat(itemRow.querySelector(".item-precio").value) || 0;
    const total = cantidad * precio;
    itemRow.querySelector(".item-total").value = total.toFixed(2);
    itemRow.querySelector(".item-total-display").textContent = formatCurrency(total);
}

// Calcular totales generales
function calculateTotals() {
    let subtotal = 0;
    
    document.querySelectorAll(".item-row").forEach(row => {
        calculateItemTotal(row);
        const itemTotal = parseFloat(row.querySelector(".item-total").value) || 0;
        subtotal += itemTotal;
    });
    
    const iva = parseFloat(document.getElementById("iva").value) || 0;
    const segundoImpuesto = parseFloat(document.getElementById("segundoImpuesto").value) || 0;
    
    const ivaAmount = (subtotal * iva) / 100;
    const segundoImpuestoAmount = (subtotal * segundoImpuesto) / 100;
    const total = subtotal + ivaAmount + segundoImpuestoAmount;
    
    // Actualizar UI
    document.getElementById("subtotal").textContent = formatCurrency(subtotal);
    document.getElementById("ivaPercent").textContent = iva.toFixed(0);
    document.getElementById("ivaAmount").textContent = formatCurrency(ivaAmount);
    document.getElementById("segundoImpuestoPercent").textContent = segundoImpuesto.toFixed(0);
    document.getElementById("segundoImpuestoAmount").textContent = formatCurrency(segundoImpuestoAmount);
    document.getElementById("total").textContent = formatCurrency(total);
    
    // Mostrar/ocultar segundo impuesto
    const segundoImpuestoRow = document.getElementById("segundoImpuestoRow");
    if (segundoImpuesto > 0) {
        segundoImpuestoRow.style.display = "flex";
    } else {
        segundoImpuestoRow.style.display = "none";
    }
}

// Formatear moneda
function formatCurrency(amount) {
    return new Intl.NumberFormat("es-ES", {
        style: "currency",
        currency: "EUR"
    }).format(amount);
}

// Escape HTML
function escapeHtml(text) {
    const div = document.createElement("div");
    div.textContent = text;
    return div.innerHTML;
}

// Adjuntar listeners a los inputs de items
function attachItemListeners(itemRow) {
    itemRow.querySelector(".item-cantidad").addEventListener("input", calculateTotals);
    itemRow.querySelector(".item-precio").addEventListener("input", calculateTotals);
}

// Inicializar listeners
document.querySelectorAll(".item-row").forEach(attachItemListeners);
document.getElementById("iva").addEventListener("input", calculateTotals);
document.getElementById("segundoImpuesto").addEventListener("input", calculateTotals);

// Calcular totales iniciales
calculateTotals();

// Enviar formulario
document.getElementById("presupuestoForm").addEventListener("submit", function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const action = e.submitter.value;
    formData.append("action", action);
    
    // Añadir totales calculados
    let totalSubtotal = 0;
    document.querySelectorAll(".item-total").forEach(input => {
        totalSubtotal += parseFloat(input.value) || 0;
    });
    
    const iva = parseFloat(document.getElementById("iva").value) || 0;
    const segundoImpuesto = parseFloat(document.getElementById("segundoImpuesto").value) || 0;
    const ivaAmount = (totalSubtotal * iva) / 100;
    const segundoImpuestoAmount = (totalSubtotal * segundoImpuesto) / 100;
    const total = totalSubtotal + ivaAmount + segundoImpuestoAmount;
    
    formData.append("subtotal", totalSubtotal.toFixed(2));
    formData.append("total", total.toFixed(2));
    
    // Enviar al backend
    fetch("api.php", {
        method: "POST",
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (action === "guardar_pdf") {
                // Descargar PDF
                window.open("api.php?action=pdf&id=" + data.id, "_blank");
            }
            // Redirigir a lista
            setTimeout(() => {
                window.location.href = "index.php";
            }, 500);
        } else {
            alert("Error: " + (data.message || "No se pudo guardar el presupuesto"));
        }
    })
    .catch(error => {
        console.error("Error:", error);
        alert("Error al guardar el presupuesto");
    });
});
</script>
';

include '../includes/footer.php';
?>