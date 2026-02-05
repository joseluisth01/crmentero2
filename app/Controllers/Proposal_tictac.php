<?php

/**
 * CONTROLADOR TICTAC PARA RISE CRM - VERSIÓN MEJORADA
 * Compatible con cualquier ubicación de TCPDF
 * Usa la librería PDF nativa de RISE
 * 
 * INSTALACIÓN:
 * 1. Subir a: application/controllers/
 * 2. Nombre: Proposal_tictac.php (P mayúscula)
 * 3. Acceder vía: /index.php/proposal_tictac/download_pdf/[ID]
 */

if (!defined('BASEPATH')) exit('No direct script access allowed');

class Proposal_tictac extends CI_Controller {

    public function __construct() {
        parent::__construct();
        
        // Cargar modelos
        $this->load->model('Proposals_model');
        $this->load->model('Clients_model');
        $this->load->model('Proposal_items_model');
        
        // Verificar autenticación
        if (!$this->login_user->id) {
            redirect('signin');
        }
    }

    /**
     * Generar y descargar PDF
     */
    public function download_pdf($proposal_id = 0) {
        
        if (!$proposal_id) {
            show_404();
        }
        
        // Obtener datos
        $proposal_info = $this->Proposals_model->get_one($proposal_id);
        
        if (!$proposal_info || !$proposal_info->id) {
            show_404();
        }
        
        // Verificar permisos
        if (!$this->_can_view_proposal($proposal_info)) {
            redirect('forbidden');
        }
        
        $client_info = $this->Clients_model->get_one($proposal_info->client_id);
        $proposal_items_array = $this->Proposal_items_model->get_details(array("proposal_id" => $proposal_id))->getResult();
        
        // Generar PDF usando la librería nativa de RISE
        $this->_generate_pdf($proposal_info, $client_info, $proposal_items_array);
    }
    
    /**
     * Vista previa HTML (para testing)
     */
    public function preview($proposal_id = 0) {
        
        if (!$proposal_id) {
            show_404();
        }
        
        $proposal_info = $this->Proposals_model->get_one($proposal_id);
        
        if (!$proposal_info || !$proposal_info->id) {
            show_404();
        }
        
        if (!$this->_can_view_proposal($proposal_info)) {
            redirect('forbidden');
        }
        
        $client_info = $this->Clients_model->get_one($proposal_info->client_id);
        $proposal_items_array = $this->Proposal_items_model->get_details(array("proposal_id" => $proposal_id))->getResult();
        
        // Cargar plantilla y generar HTML
        $html = $this->_generate_html($proposal_info, $client_info, $proposal_items_array);
        
        // Mostrar HTML directamente
        echo $html;
    }
    
    /**
     * Generar PDF usando método nativo de RISE
     */
    private function _generate_pdf($proposal_info, $client_info, $proposal_items) {
        
        // Cargar helper de PDF de RISE
        $this->load->helper('pdf');
        
        // Generar HTML
        $html = $this->_generate_html($proposal_info, $client_info, $proposal_items);
        
        // Preparar PDF usando la función de RISE
        $pdf_file_name = "Presupuesto_" . $proposal_info->id . ".pdf";
        
        // RISE usa prepare_pdf() que está en application/helpers/pdf_helper.php
        // Esta función maneja automáticamente TCPDF
        
        try {
            // Método 1: Usando prepare_pdf si existe
            if (function_exists('prepare_pdf')) {
                prepare_pdf($html, $pdf_file_name);
            } 
            // Método 2: Crear instancia directa
            else {
                $this->_create_pdf_direct($html, $pdf_file_name);
            }
        } catch (Exception $e) {
            // Si falla, mostrar HTML con opción de imprimir
            $this->_fallback_html_print($html, $pdf_file_name);
        }
    }
    
    /**
     * Crear PDF directamente sin helper
     */
    private function _create_pdf_direct($html, $filename) {
        
        // Cargar TCPDF
        if (file_exists(APPPATH . 'libraries/Pdf.php')) {
            require_once(APPPATH . 'libraries/Pdf.php');
            $pdf_class = 'Pdf';
        } else if (file_exists(APPPATH . 'third_party/tcpdf/tcpdf.php')) {
            require_once(APPPATH . 'third_party/tcpdf/tcpdf.php');
            $pdf_class = 'TCPDF';
        } else {
            throw new Exception('TCPDF no encontrado');
        }
        
        // Crear PDF
        $pdf = new $pdf_class('P', 'mm', 'A4', true, 'UTF-8', false);
        
        // Configuración
        $pdf->SetCreator('Tictac Comunicación Digital SL');
        $pdf->SetAuthor('Tictac Comunicación Digital SL');
        $pdf->SetTitle('Presupuesto ' . $filename);
        
        // Sin header/footer automáticos
        if (method_exists($pdf, 'setPrintHeader')) {
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
        }
        
        // Márgenes
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(TRUE, 15);
        
        // Añadir página
        $pdf->AddPage();
        
        // Escribir HTML
        $pdf->writeHTML($html, true, false, true, false, '');
        
        // Output
        $pdf->Output($filename, 'D');
    }
    
    /**
     * Fallback: Mostrar HTML con botón de imprimir
     */
    private function _fallback_html_print($html, $filename) {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title><?php echo htmlspecialchars($filename); ?></title>
            <style>
                @media print {
                    .no-print { display: none !important; }
                }
                .print-button {
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    padding: 15px 30px;
                    background: #E91E8C;
                    color: white;
                    border: none;
                    border-radius: 50px;
                    font-size: 16px;
                    font-weight: bold;
                    cursor: pointer;
                    box-shadow: 0 5px 20px rgba(0,0,0,0.2);
                    z-index: 9999;
                }
                .print-button:hover {
                    background: #C91E82;
                }
            </style>
            <script>
                function imprimirPDF() {
                    window.print();
                }
            </script>
        </head>
        <body>
            <button onclick="imprimirPDF()" class="print-button no-print">
                🖨️ Imprimir / Guardar PDF
            </button>
            <?php echo $html; ?>
        </body>
        </html>
        <?php
        exit;
    }
    
    /**
     * Generar HTML del presupuesto
     */
    private function _generate_html($proposal_info, $client_info, $proposal_items) {
        
        // Verificar si existe la plantilla
        $template_path = APPPATH . 'views/proposals/templates/proposal_tictac_template.php';
        
        if (!file_exists($template_path)) {
            // Si no existe, usar plantilla embebida
            return $this->_generate_html_embedded($proposal_info, $client_info, $proposal_items);
        }
        
        // Cargar plantilla externa
        require_once($template_path);
        
        // Generar HTML
        return generate_tictac_proposal_html($proposal_info, $client_info, $proposal_items);
    }
    
    /**
     * Plantilla HTML embebida (backup si no existe archivo externo)
     */
    private function _generate_html_embedded($proposal_info, $client_info, $proposal_items) {
        
        // Función auxiliar para limpiar texto
        $clean_text = function($html) {
            if ($html === null) return '';
            $txt = html_entity_decode((string)$html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $txt = preg_replace('~<\s*br\s*/?\s*>~i', "\n", $txt);
            $txt = preg_replace('~<\s*/\s*p\s*>~i', "\n", $txt);
            $txt = preg_replace('~<\s*p[^>]*>~i', '', $txt);
            $txt = strip_tags($txt);
            $txt = str_replace("\xC2\xA0", ' ', $txt);
            $txt = preg_replace("/[ \t]+/", " ", $txt);
            $txt = str_replace(["\r\n", "\r"], "\n", $txt);
            $txt = preg_replace("/\n{3,}/", "\n\n", $txt);
            return trim($txt);
        };
        
        // Procesar datos
        $proposal_id = $proposal_info->id ?? '';
        $proposal_date = isset($proposal_info->proposal_date) ? date('d-m-Y', strtotime($proposal_info->proposal_date)) : '';
        $valid_until = isset($proposal_info->valid_until) ? date('d-m-Y', strtotime($proposal_info->valid_until)) : '';
        
        $client_name = $client_info->company_name ?? '';
        $client_contact = trim(($client_info->contact_firstname ?? '') . ' ' . ($client_info->contact_lastname ?? ''));
        $client_email = $client_info->email ?? '';
        $client_phone = $client_info->phone ?? '';
        $client_address = $client_info->address ?? '';
        $client_city = $client_info->city ?? '';
        $client_zip = $client_info->zip ?? '';
        $client_country = $client_info->country ?? '';
        $client_vat = $client_info->vat_number ?? '';
        
        $subtotal = floatval($proposal_info->subtotal ?? 0);
        $tax = floatval($proposal_info->tax ?? 21);
        $tax_amount = ($subtotal * $tax) / 100;
        $total = floatval($proposal_info->total ?? ($subtotal + $tax_amount));
        
        $notes = $clean_text($proposal_info->note ?? '');
        
        // Generar HTML
        ob_start();
        ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; color: #333; margin: 0; padding: 20px; }
        .header { background: #E91E8C; color: white; text-align: center; padding: 30px; margin-bottom: 30px; }
        .header h1 { margin: 0; font-size: 28px; }
        .info-box { border: 2px solid #E91E8C; padding: 20px; margin: 20px 0; }
        .info-box h3 { color: #E91E8C; margin: 0 0 15px 0; border-bottom: 2px solid #E91E8C; padding-bottom: 8px; }
        .info-box p { margin: 8px 0; font-size: 14px; }
        .info-box strong { color: #E91E8C; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        thead { background: #1a1a1a; color: white; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { font-weight: bold; }
        .item-desc { color: #666; font-size: 12px; margin-top: 5px; }
        .totals { text-align: right; margin: 20px 0; }
        .totals div { padding: 8px 0; font-size: 16px; }
        .totals .final { background: #1a1a1a; color: white; padding: 15px; font-size: 20px; font-weight: bold; margin-top: 10px; }
        .notes { background: #fff5f9; border-left: 4px solid #E91E8C; padding: 20px; margin: 30px 0; }
        .footer { text-align: center; margin-top: 50px; padding-top: 20px; border-top: 2px solid #E91E8C; color: #666; font-size: 12px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>PRESUPUESTO</h1>
        <p>Tictac Comunicación Digital SL</p>
    </div>
    
    <div class="info-box">
        <h3>DATOS DE LA PROPUESTA</h3>
        <p><strong>ID:</strong> <?php echo htmlspecialchars($proposal_id); ?></p>
        <p><strong>Fecha:</strong> <?php echo htmlspecialchars($proposal_date); ?></p>
        <p><strong>Válido hasta:</strong> <?php echo htmlspecialchars($valid_until); ?></p>
    </div>
    
    <div class="info-box">
        <h3>INFORMACIÓN DEL CLIENTE</h3>
        <p><strong>Cliente:</strong> <?php echo htmlspecialchars($client_name); ?></p>
        <?php if ($client_contact): ?><p><strong>Contacto:</strong> <?php echo htmlspecialchars($client_contact); ?></p><?php endif; ?>
        <?php if ($client_email): ?><p><strong>Email:</strong> <?php echo htmlspecialchars($client_email); ?></p><?php endif; ?>
        <?php if ($client_phone): ?><p><strong>Teléfono:</strong> <?php echo htmlspecialchars($client_phone); ?></p><?php endif; ?>
        <?php if ($client_address): ?><p><strong>Dirección:</strong> <?php echo htmlspecialchars($client_address); ?></p><?php endif; ?>
        <?php if ($client_city || $client_zip): ?><p><strong>Ciudad:</strong> <?php echo htmlspecialchars($client_city . ($client_zip ? ', ' . $client_zip : '')); ?></p><?php endif; ?>
        <?php if ($client_country): ?><p><strong>País:</strong> <?php echo htmlspecialchars($client_country); ?></p><?php endif; ?>
        <?php if ($client_vat): ?><p><strong>CIF/NIF:</strong> <?php echo htmlspecialchars($client_vat); ?></p><?php endif; ?>
    </div>
    
    <h2 style="color: #E91E8C; border-bottom: 3px solid #E91E8C; padding-bottom: 10px;">PROPUESTA ECONÓMICA</h2>
    
    <table>
        <thead>
            <tr>
                <th>Artículo</th>
                <th style="width: 15%; text-align: center;">Cantidad</th>
                <th style="width: 20%; text-align: right;">Tarifa</th>
                <th style="width: 20%; text-align: right;">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($proposal_items as $item): 
                $item_qty = floatval($item->quantity ?? 0);
                $item_rate = floatval($item->rate ?? 0);
                $item_total = $item_qty * $item_rate;
                $item_title = $clean_text($item->title ?? '');
                $item_desc = $clean_text($item->description ?? '');
            ?>
            <tr>
                <td>
                    <strong><?php echo htmlspecialchars($item_title); ?></strong>
                    <?php if ($item_desc): ?>
                        <div class="item-desc"><?php echo nl2br(htmlspecialchars($item_desc)); ?></div>
                    <?php endif; ?>
                </td>
                <td style="text-align: center;"><?php echo number_format($item_qty, 2, ',', '.'); ?></td>
                <td style="text-align: right;"><?php echo number_format($item_rate, 2, ',', '.'); ?> €</td>
                <td style="text-align: right;"><strong><?php echo number_format($item_total, 2, ',', '.'); ?> €</strong></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <div class="totals">
        <div>Subtotal: <strong><?php echo number_format($subtotal, 2, ',', '.'); ?> €</strong></div>
        <div>IVA (<?php echo number_format($tax, 0); ?>%): <strong><?php echo number_format($tax_amount, 2, ',', '.'); ?> €</strong></div>
        <div class="final">TOTAL: <?php echo number_format($total, 2, ',', '.'); ?> €</div>
    </div>
    
    <?php if ($notes): ?>
    <div class="notes">
        <strong style="color: #E91E8C; display: block; margin-bottom: 10px;">NOTAS ADICIONALES</strong>
        <?php echo nl2br(htmlspecialchars($notes)); ?>
    </div>
    <?php endif; ?>
    
    <div class="footer">
        <p><strong>Tictac Comunicación Digital SL</strong></p>
        <p>Plaza de los Carrillos, 5 · 14001 Córdoba</p>
        <p>📞 957 048 147 · ✉ hola@tictac-comunicacion.es · 🌐 www.tictac-comunicacion.es</p>
    </div>
</body>
</html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Verificar permisos
     */
    private function _can_view_proposal($proposal_info) {
        
        $user_type = $this->login_user->user_type;
        
        if ($user_type == "staff") {
            return true;
        }
        
        if ($user_type == "client" && $proposal_info->client_id == $this->login_user->client_id) {
            return true;
        }
        
        return false;
    }
}

/* End of file Proposal_tictac.php */