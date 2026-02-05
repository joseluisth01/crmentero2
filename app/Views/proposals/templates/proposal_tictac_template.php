<?php
/**
 * PLANTILLA PERSONALIZADA TICTAC COMUNICACIÓN
 * Para usar en CRM RISE 3.9.6
 * 
 * INSTRUCCIONES DE INSTALACIÓN:
 * 1. Subir este archivo a: application/views/proposals/templates/
 * 2. Renombrar a: proposal_tictac.php
 * 3. Crear controlador personalizado (ver archivo proposal_pdf_controller.php)
 */

// Prevenir acceso directo
if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Función principal: Genera HTML para el PDF usando TCPDF
 * @param array $proposal_info - Datos del presupuesto
 * @param array $client_info - Datos del cliente
 * @param array $proposal_items - Artículos del presupuesto
 * @return string HTML del presupuesto
 */
function generate_tictac_proposal_html($proposal_info, $client_info, $proposal_items) {
    
    // ===================================================================
    // FUNCIÓN AUXILIAR: Limpiar HTML para texto plano en PDF
    // ===================================================================
    $pdf_text_plain = function($html) {
        if ($html === null) return '';
        
        // 1) Decodificar entidades HTML
        $txt = html_entity_decode((string)$html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // 2) Convertir tags a saltos
        $txt = preg_replace('~<\s*br\s*/?\s*>~i', "\n", $txt);
        $txt = preg_replace('~<\s*/\s*p\s*>~i', "\n", $txt);
        $txt = preg_replace('~<\s*p[^>]*>~i', '', $txt);
        
        // 3) Eliminar HTML restante
        $txt = strip_tags($txt);
        
        // 4) Normalizar espacios
        $txt = str_replace("\xC2\xA0", ' ', $txt);
        $txt = preg_replace("/[ \t]+/", " ", $txt);
        
        // 5) Normalizar saltos
        $txt = str_replace(["\r\n", "\r"], "\n", $txt);
        $txt = preg_replace("/\n{3,}/", "\n\n", $txt);
        
        return trim($txt);
    };
    
    // ===================================================================
    // CONFIGURACIÓN DE COLORES Y MARCA
    // ===================================================================
    $BRAND_COLOR = '#E91E8C';
    $BRAND_COLOR_RGB = '233, 30, 140';
    $ACCENT_COLOR = '#C6D617';
    
    // ===================================================================
    // PROCESAR DATOS
    // ===================================================================
    
    // Logo (ajustar ruta según tu instalación)
    $logo_url = base_url('files/system/tictac-logo-blanco.png');
    
    // Datos de la propuesta
    $proposal_id = $proposal_info->id ?? '';
    $proposal_date = isset($proposal_info->proposal_date) ? date('d-m-Y', strtotime($proposal_info->proposal_date)) : '';
    $valid_until = isset($proposal_info->valid_until) ? date('d-m-Y', strtotime($proposal_info->valid_until)) : '';
    
    // Datos del cliente
    $client_name = $client_info->company_name ?? '';
    $client_contact = ($client_info->contact_firstname ?? '') . ' ' . ($client_info->contact_lastname ?? '');
    $client_email = $client_info->email ?? '';
    $client_phone = $client_info->phone ?? '';
    $client_address = $client_info->address ?? '';
    $client_city = $client_info->city ?? '';
    $client_state = $client_info->state ?? '';
    $client_zip = $client_info->zip ?? '';
    $client_country = $client_info->country ?? '';
    $client_vat = $client_info->vat_number ?? '';
    
    // Totales
    $subtotal = floatval($proposal_info->subtotal ?? 0);
    $tax_percentage = floatval($proposal_info->tax ?? 21);
    $tax_amount = ($subtotal * $tax_percentage) / 100;
    $second_tax = floatval($proposal_info->tax2 ?? 0);
    $second_tax_amount = ($subtotal * $second_tax) / 100;
    $total = floatval($proposal_info->total ?? ($subtotal + $tax_amount + $second_tax_amount));
    
    // Notas
    $notes = $pdf_text_plain($proposal_info->note ?? '');
    
    // ===================================================================
    // GENERAR HTML
    // ===================================================================
    
    ob_start();
    ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @page {
            margin: 0;
        }
        
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            color: #1a1a1a;
            margin: 0;
            padding: 0;
            font-size: 10pt;
        }
        
        /* HEADER */
        .header-bg {
            background-color: <?php echo $BRAND_COLOR; ?>;
            text-align: center;
            padding: 30px 20px;
            margin-bottom: 20px;
        }
        
        .header-bg h1 {
            color: #ffffff;
            font-size: 28px;
            font-weight: bold;
            margin: 15px 0 5px 0;
        }
        
        .header-bg p {
            color: #ffffff;
            font-size: 12px;
            margin: 0;
        }
        
        /* CAJAS DE INFORMACIÓN */
        .info-section {
            width: 100%;
            margin-bottom: 20px;
        }
        
        .info-box {
            width: 48%;
            display: inline-block;
            vertical-align: top;
            border: 2px solid #e8e8e8;
            padding: 15px;
            box-sizing: border-box;
        }
        
        .info-box h3 {
            font-size: 11px;
            color: <?php echo $BRAND_COLOR; ?>;
            font-weight: bold;
            margin: 0 0 10px 0;
            padding: 0 0 5px 0;
            text-transform: uppercase;
            border-bottom: 2px solid <?php echo $BRAND_COLOR; ?>;
        }
        
        .info-box p {
            margin: 0 0 6px 0;
            font-size: 9px;
            color: #555555;
            line-height: 1.4;
        }
        
        .info-box strong {
            color: #1a1a1a;
        }
        
        /* SECCIÓN SOBRE NOSOTROS */
        .about-section {
            background-color: #fff5f9;
            border-left: 4px solid <?php echo $BRAND_COLOR; ?>;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .about-section h3 {
            color: <?php echo $BRAND_COLOR; ?>;
            font-size: 13px;
            font-weight: bold;
            margin: 0 0 8px 0;
        }
        
        .about-section p {
            color: #555555;
            font-size: 9px;
            line-height: 1.5;
            margin: 0;
            text-align: justify;
        }
        
        /* TÍTULOS DE SECCIÓN */
        .section-title {
            color: #1a1a1a;
            font-size: 16px;
            font-weight: bold;
            margin: 0 0 6px 0;
            padding: 0 0 8px 0;
            border-bottom: 3px solid <?php echo $BRAND_COLOR; ?>;
        }
        
        .section-subtitle {
            color: #777777;
            font-size: 10px;
            margin: 0 0 15px 0;
        }
        
        /* DISCLAIMER */
        .disclaimer {
            background-color: #fff9f0;
            border-left: 4px solid #ff9800;
            padding: 10px 15px;
            margin-bottom: 15px;
        }
        
        .disclaimer p {
            font-size: 8px;
            color: #6d4c00;
            font-weight: 600;
            margin: 0 0 3px 0;
            line-height: 1.3;
        }
        
        /* TABLA DE ITEMS */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        .items-table th {
            background-color: #1a1a1a;
            color: #ffffff;
            font-size: 9px;
            font-weight: bold;
            padding: 8px 6px;
            text-align: left;
        }
        
        .items-table td {
            padding: 8px 6px;
            font-size: 8px;
            border-bottom: 1px solid #e8e8e8;
            vertical-align: top;
        }
        
        .items-table .item-title {
            font-weight: bold;
            color: #1a1a1a;
            font-size: 9px;
        }
        
        .items-table .item-desc {
            color: #777777;
            font-size: 8px;
            margin-top: 3px;
            line-height: 1.3;
        }
        
        /* TOTALES */
        .totals-section {
            width: 100%;
            margin-top: 15px;
            text-align: right;
        }
        
        .totals-row {
            padding: 4px 0;
            font-size: 10px;
        }
        
        .totals-row .label {
            display: inline-block;
            width: 150px;
            text-align: right;
            padding-right: 15px;
            color: #555555;
        }
        
        .totals-row .amount {
            display: inline-block;
            width: 100px;
            text-align: right;
            font-weight: bold;
        }
        
        .total-final {
            background-color: #1a1a1a;
            color: #ffffff !important;
            padding: 8px 15px !important;
            margin-top: 5px;
            font-size: 12px !important;
        }
        
        .total-final .label,
        .total-final .amount {
            color: #ffffff !important;
        }
        
        /* NOTAS */
        .notes-section {
            background-color: #fff5f9;
            border-left: 4px solid <?php echo $BRAND_COLOR; ?>;
            padding: 15px;
            margin: 20px 0;
        }
        
        .notes-section strong {
            color: <?php echo $BRAND_COLOR; ?>;
            font-weight: bold;
            font-size: 11px;
            display: block;
            margin-bottom: 8px;
        }
        
        .notes-section div {
            color: #555555;
            font-size: 9px;
            line-height: 1.4;
        }
        
        /* OBJETIVO */
        .objective-section {
            border: 2px solid #e8e8e8;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .objective-section h3 {
            color: <?php echo $BRAND_COLOR; ?>;
            font-size: 13px;
            font-weight: bold;
            margin: 0 0 8px 0;
        }
        
        .objective-section p {
            color: #555555;
            font-size: 9px;
            line-height: 1.5;
            margin: 0;
            text-align: justify;
        }
        
        /* FOOTER */
        .footer {
            border-top: 3px solid <?php echo $BRAND_COLOR; ?>;
            padding-top: 20px;
            text-align: center;
            margin-top: 30px;
        }
        
        .footer .company-name {
            font-weight: bold;
            font-size: 12px;
            color: #1a1a1a;
            margin: 0 0 6px 0;
        }
        
        .footer .address {
            color: #555555;
            font-size: 9px;
            margin: 0 0 10px 0;
        }
        
        .footer .contact {
            color: #555555;
            font-size: 8px;
            margin: 3px 0;
        }
    </style>
</head>
<body>
    
    <!-- HEADER -->
    <div class="header-bg">
        <h1>Presupuesto</h1>
        <p>Soluciones digitales orientadas a resultados</p>
    </div>
    
    <!-- INFO CARDS -->
    <div class="info-section">
        <div class="info-box" style="margin-right: 4%;">
            <h3>DATOS DE LA PROPUESTA</h3>
            <p><strong>ID Propuesta:</strong> <?php echo htmlspecialchars($proposal_id); ?></p>
            <p><strong>Fecha emisión:</strong> <?php echo htmlspecialchars($proposal_date); ?></p>
            <p><strong>Válida hasta:</strong> <?php echo htmlspecialchars($valid_until); ?></p>
        </div><!--
        --><div class="info-box">
            <h3>INFORMACIÓN DEL CLIENTE</h3>
            <p><strong>Cliente:</strong> <?php echo htmlspecialchars($client_name); ?></p>
            <p><strong>Contacto:</strong> <?php echo htmlspecialchars($client_contact); ?></p>
            <?php if ($client_email): ?>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($client_email); ?></p>
            <?php endif; ?>
            <?php if ($client_phone): ?>
            <p><strong>Teléfono:</strong> <?php echo htmlspecialchars($client_phone); ?></p>
            <?php endif; ?>
            <?php if ($client_address): ?>
            <p><strong>Dirección:</strong> <?php echo htmlspecialchars($client_address); ?></p>
            <?php endif; ?>
            <?php if ($client_city || $client_state || $client_zip): ?>
            <p><strong>Ciudad:</strong> <?php echo htmlspecialchars($client_city . ($client_state ? ', ' . $client_state : '') . ($client_zip ? ' ' . $client_zip : '')); ?></p>
            <?php endif; ?>
            <?php if ($client_country): ?>
            <p><strong>País:</strong> <?php echo htmlspecialchars($client_country); ?></p>
            <?php endif; ?>
            <?php if ($client_vat): ?>
            <p><strong>CIF/NIF:</strong> <?php echo htmlspecialchars($client_vat); ?></p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- ABOUT SECTION -->
    <div class="about-section">
        <h3>Sobre Nosotros</h3>
        <p>
            En <strong>Tictac Comunicación Digital SL</strong> desarrollamos estrategias digitales orientadas a conversión,
            visibilidad y crecimiento real. Cada propuesta se diseña a medida, alineada con los objetivos
            del cliente y basada en criterios técnicos, creativos y estratégicos. Somos una agencia joven
            y dinámica, compuesta por un grupo de profesionales especialistas en las distintas disciplinas
            del marketing, unidos por la motivación de hacer crecer cualquier negocio.
        </p>
    </div>
    
    <!-- PROPOSAL SECTION -->
    <h2 class="section-title">Propuesta Económica</h2>
    <p class="section-subtitle">A continuación detallamos los servicios incluidos en esta propuesta:</p>
    
    <!-- PRICE DISCLAIMER -->
    <div class="disclaimer">
        <p><strong>*</strong> Los precios no incluyen el <?php echo number_format($tax_percentage, 0); ?>% de IVA</p>
        <p><strong>**</strong> El presupuesto tendrá validez hasta dos semanas después de su fecha de emisión indicada en la parte superior.</p>
    </div>
    
    <!-- ITEMS TABLE -->
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 45%;">Artículo</th>
                <th style="width: 15%; text-align: center;">Cantidad</th>
                <th style="width: 20%; text-align: right;">Tarifa</th>
                <th style="width: 20%; text-align: right;">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($proposal_items as $item): 
                $item_quantity = floatval($item->quantity ?? 0);
                $item_rate = floatval($item->rate ?? 0);
                $item_total = $item_quantity * $item_rate;
                $item_title = $pdf_text_plain($item->title ?? '');
                $item_desc = $pdf_text_plain($item->description ?? '');
                $item_unit = $item->unit_type ?? '';
            ?>
            <tr>
                <td>
                    <div class="item-title"><?php echo htmlspecialchars($item_title); ?></div>
                    <?php if ($item_desc): ?>
                    <div class="item-desc"><?php echo nl2br(htmlspecialchars($item_desc)); ?></div>
                    <?php endif; ?>
                </td>
                <td style="text-align: center;">
                    <?php echo number_format($item_quantity, 2, ',', '.'); ?>
                    <?php if ($item_unit): ?>
                        <?php echo htmlspecialchars($item_unit); ?>
                    <?php endif; ?>
                </td>
                <td style="text-align: right;">
                    <?php echo number_format($item_rate, 2, ',', '.'); ?> €
                </td>
                <td style="text-align: right;">
                    <strong><?php echo number_format($item_total, 2, ',', '.'); ?> €</strong>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <!-- TOTALES -->
    <div class="totals-section">
        <div class="totals-row">
            <span class="label">Sub Total:</span>
            <span class="amount"><?php echo number_format($subtotal, 2, ',', '.'); ?> €</span>
        </div>
        <div class="totals-row">
            <span class="label">IVA (<?php echo number_format($tax_percentage, 0); ?>%):</span>
            <span class="amount"><?php echo number_format($tax_amount, 2, ',', '.'); ?> €</span>
        </div>
        <?php if ($second_tax > 0): ?>
        <div class="totals-row">
            <span class="label">Segundo Impuesto (<?php echo number_format($second_tax, 0); ?>%):</span>
            <span class="amount"><?php echo number_format($second_tax_amount, 2, ',', '.'); ?> €</span>
        </div>
        <?php endif; ?>
        <div class="totals-row total-final">
            <span class="label">TOTAL:</span>
            <span class="amount"><?php echo number_format($total, 2, ',', '.'); ?> €</span>
        </div>
    </div>
    
    <!-- NOTES -->
    <?php if ($notes): ?>
    <div class="notes-section">
        <strong>Notas Adicionales</strong>
        <div><?php echo nl2br(htmlspecialchars($notes)); ?></div>
    </div>
    <?php endif; ?>
    
    <!-- OBJECTIVE -->
    <div class="objective-section">
        <h3>Objetivo del Proyecto</h3>
        <p>
            Nuestro objetivo es construir una solución eficaz, escalable y alineada con tu negocio,
            garantizando resultados medibles y una comunicación fluida durante todo el proceso.
            Trabajamos con pasión por la comunicación, la tecnología, las marcas y las historias
            que motivan al cambio real e impulsan la vida de las personas, diseñando estrategias
            y soluciones adaptadas a cada proyecto y/o empresa para hacer nuestros sus objetivos
            y llegar más allá de las metas necesarias.
        </p>
    </div>
    
    <!-- FOOTER -->
    <div class="footer">
        <p class="company-name">Tictac Comunicación Digital SL</p>
        <p class="address">Plaza de los Carrillos, 5 · 14001 Córdoba</p>
        <p class="contact">📞 957 048 147 | ✉ hola@tictac-comunicacion.es</p>
        <p class="contact">🌐 www.tictac-comunicacion.es</p>
    </div>
    
</body>
</html>
    <?php
    return ob_get_clean();
}

// Si se llama directamente con datos de prueba (para testing)
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    // Datos de prueba
    $proposal_info = (object)[
        'id' => 'PRES-20260205-0001',
        'proposal_date' => '2026-02-05',
        'valid_until' => '2026-03-07',
        'subtotal' => 500.00,
        'tax' => 21,
        'tax2' => 0,
        'total' => 605.00,
        'note' => 'Condiciones de pago: 50% al inicio, 50% a la entrega.'
    ];
    
    $client_info = (object)[
        'company_name' => 'Empresa de Prueba SL',
        'contact_firstname' => 'Juan',
        'contact_lastname' => 'Pérez',
        'email' => 'juan@empresa.com',
        'phone' => '123456789',
        'address' => 'Calle Principal 123',
        'city' => 'Córdoba',
        'state' => 'Andalucía',
        'zip' => '14001',
        'country' => 'España',
        'vat_number' => 'B12345678'
    ];
    
    $proposal_items = [
        (object)[
            'title' => 'Mantenimiento SEO',
            'description' => 'Mantenimiento mensual de posicionamiento en buscadores',
            'quantity' => 1,
            'rate' => 300.00,
            'unit_type' => 'Mensual'
        ],
        (object)[
            'title' => 'Diseño Web',
            'description' => 'Diseño y desarrollo de página web corporativa',
            'quantity' => 1,
            'rate' => 200.00,
            'unit_type' => 'Único'
        ]
    ];
    
    echo generate_tictac_proposal_html($proposal_info, $client_info, $proposal_items);
}
