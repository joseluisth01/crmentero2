<?php

/**
 * CONTROLADOR PERSONALIZADO PARA PRESUPUESTOS TICTAC
 * Para CRM RISE 3.9.6
 * 
 * INSTRUCCIONES DE INSTALACIÓN:
 * 1. Subir a: application/controllers/
 * 2. Renombrar a: Proposal_tictac.php
 * 3. Acceder vía: https://tudominio.com/index.php/proposal_tictac/download_pdf/[ID]
 * 
 * INTEGRACIÓN EN RISE:
 * - Modificar application/controllers/Proposals.php
 * - En el método view(), añadir botón: "Descargar PDF Tictac"
 */

if (!defined('BASEPATH')) exit('No direct script access allowed');

class Proposal_tictac extends CI_Controller {

    public function __construct() {
        parent::__construct();
        
        // Cargar modelos necesarios
        $this->load->model('Proposals_model');
        $this->load->model('Clients_model');
        $this->load->model('Proposal_items_model');
        
        // Verificar login
        if (!$this->login_user->id) {
            redirect('signin');
        }
    }

    /**
     * Generar y descargar PDF del presupuesto
     * @param int $proposal_id - ID del presupuesto
     */
    public function download_pdf($proposal_id = 0) {
        
        if (!$proposal_id) {
            show_404();
        }
        
        // Obtener datos del presupuesto
        $proposal_info = $this->Proposals_model->get_one($proposal_id);
        
        if (!$proposal_info || !$proposal_info->id) {
            show_404();
        }
        
        // Verificar permisos
        if (!$this->_can_view_proposal($proposal_info)) {
            redirect('forbidden');
        }
        
        // Obtener cliente
        $client_info = $this->Clients_model->get_one($proposal_info->client_id);
        
        // Obtener items del presupuesto
        $proposal_items_array = $this->Proposal_items_model->get_details(array("proposal_id" => $proposal_id))->getResult();
        
        // Cargar TCPDF
        $this->load->library('Pdf');
        
        // Crear instancia de PDF con configuración personalizada
        $pdf = new TictacProposalPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        
        // Configuración del documento
        $pdf->SetCreator('Tictac Comunicación Digital SL');
        $pdf->SetAuthor('Tictac Comunicación Digital SL');
        $pdf->SetTitle('Presupuesto ' . $proposal_info->id);
        $pdf->SetSubject('Propuesta Comercial');
        
        // No header/footer automáticos (los haremos custom)
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        
        // Configuración de página
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(TRUE, 15);
        $pdf->AddPage();
        
        // Cargar la plantilla
        require_once(APPPATH . 'views/proposals/templates/proposal_tictac_template.php');
        
        // Generar HTML
        $html = generate_tictac_proposal_html($proposal_info, $client_info, $proposal_items_array);
        
        // Escribir HTML al PDF
        $pdf->writeHTML($html, true, false, true, false, '');
        
        // Nombre del archivo
        $proposal_filename = "Presupuesto_" . $proposal_info->id . ".pdf";
        
        // Output del PDF
        $pdf->Output($proposal_filename, 'D'); // 'D' = descarga, 'I' = mostrar en navegador
    }
    
    /**
     * Ver presupuesto en HTML (para testing)
     * @param int $proposal_id - ID del presupuesto
     */
    public function preview($proposal_id = 0) {
        
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
        
        // Cargar plantilla
        require_once(APPPATH . 'views/proposals/templates/proposal_tictac_template.php');
        
        // Mostrar HTML directamente
        echo generate_tictac_proposal_html($proposal_info, $client_info, $proposal_items_array);
    }
    
    /**
     * Verificar si el usuario puede ver este presupuesto
     * @param object $proposal_info - Información del presupuesto
     * @return bool
     */
    private function _can_view_proposal($proposal_info) {
        
        $user_id = $this->login_user->id;
        $user_type = $this->login_user->user_type;
        
        // Staff siempre puede ver
        if ($user_type == "staff") {
            return true;
        }
        
        // Cliente solo puede ver sus propios presupuestos
        if ($user_type == "client") {
            if ($proposal_info->client_id == $this->login_user->client_id) {
                return true;
            }
        }
        
        return false;
    }
}

/**
 * CLASE PDF PERSONALIZADA PARA TICTAC
 * Extiende TCPDF para añadir header y footer personalizados
 */
class TictacProposalPDF extends TCPDF {
    
    private $brand_color = '#E91E8C';
    private $brand_color_rgb = array(233, 30, 140);
    
    /**
     * Header personalizado
     */
    public function Header() {
        
        $pageW = $this->getPageWidth();
        
        // Fondo rosa en la parte superior
        $this->SetFillColor($this->brand_color_rgb[0], $this->brand_color_rgb[1], $this->brand_color_rgb[2]);
        $this->Rect(0, 0, $pageW, 42, 'F');
        
        // Logo (si existe)
        $logo_path = FCPATH . 'files/system/tictac-logo-blanco.png';
        if (file_exists($logo_path)) {
            $logoW = 36;
            $x = ($pageW - $logoW) / 2;
            $this->Image($logo_path, $x, 6, $logoW, 0, '', '', '', false, 300, '', false, false, 0, false, false, false);
        }
        
        // Título "Presupuesto"
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('helvetica', 'B', 20);
        $this->SetXY(0, 24);
        $this->Cell($pageW, 10, 'Presupuesto', 0, 1, 'C');
        
        // Línea separadora
        $this->SetDrawColor($this->brand_color_rgb[0], $this->brand_color_rgb[1], $this->brand_color_rgb[2]);
        $this->SetLineWidth(0.3);
        $this->Line(15, 44, $pageW - 15, 44);
        
        // Reset
        $this->SetTextColor(51, 51, 51);
        $this->SetMargins(15, 50, 15);
        $this->SetY(50);
    }
    
    /**
     * Footer personalizado
     */
    public function Footer() {
        
        $pageW = $this->getPageWidth();
        $this->SetY(-38);
        
        // Línea separadora
        $this->SetDrawColor($this->brand_color_rgb[0], $this->brand_color_rgb[1], $this->brand_color_rgb[2]);
        $this->SetLineWidth(0.5);
        $this->Line(15, $this->GetY(), $pageW - 15, $this->GetY());
        
        $this->Ln(4);
        
        // Datos de la empresa
        $this->SetTextColor(51, 51, 51);
        $this->SetFont('helvetica', 'B', 10);
        $this->Cell($pageW, 5, 'Tictac Comunicación Digital SL', 0, 1, 'C');
        
        $this->SetFont('helvetica', '', 8);
        $this->SetTextColor(100, 100, 100);
        $this->Ln(2);
        $this->SetX(0);
        $this->Cell($pageW, 4, 'Plaza de los Carrillos, 5 · 14001 - Córdoba', 0, 1, 'C');
        $this->SetX(0);
        $this->Cell($pageW, 4, '957 048 147 · hola@tictac-comunicacion.es · www.tictac-comunicacion.es', 0, 1, 'C');
        
        $this->Ln(3);
        
        // Número de página
        $this->SetFont('helvetica', 'I', 7);
        $this->SetTextColor(150, 150, 150);
        $this->SetX(0);
        $this->Cell($pageW, 4, 'Página ' . $this->getAliasNumPage() . ' / ' . $this->getAliasNbPages(), 0, 1, 'C');
    }
}

/* End of file Proposal_tictac.php */
/* Location: ./application/controllers/Proposal_tictac.php */
