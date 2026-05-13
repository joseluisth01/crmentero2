<?php
/**
 * Clase TCPDF personalizada para los contratos de Tictac Comunicación.
 * Ruta en servidor: /home/gestiontictaccom/public_html/app/Libraries/TictacContractPDF.php
 */

if (!class_exists('TictacContractPDF')) {

    class TictacContractPDF extends TCPDF
    {
        public $brand_r   = 215;
        public $brand_g   = 33;
        public $brand_b   = 115;
        public $logo_path = '';

        public function Header()
        {
            $pageW = $this->getPageWidth();

            $this->SetFillColor($this->brand_r, $this->brand_g, $this->brand_b);
            $this->Rect(0, 0, $pageW, 2, 'F');

            $this->SetFillColor(255, 255, 255);
            $this->Rect(0, 2, $pageW, 26, 'F');

            if ($this->logo_path && file_exists($this->logo_path)) {
                $this->SetFillColor($this->brand_r, $this->brand_g, $this->brand_b);
                $this->Rect(0, 2, 48, 26, 'F');
                $logoW = 38;
                $logoX = (48 - $logoW) / 2;
                $logoH = 14;
                $logoY = 2 + (26 - $logoH) / 2;
                $this->Image($this->logo_path, $logoX, $logoY, $logoW, 0, '', '', '', false, 300, '', false, false, 0, false, false, false);
            }

            $this->SetTextColor($this->brand_r, $this->brand_g, $this->brand_b);
            $this->SetFont('Helvetica', 'B', 16);
            $this->SetXY(52, 2 + (26 - 8) / 2);
            $this->Cell(60, 8, 'CONTRATO', 0, 0, 'L');

            $lineH   = 3.8;
            $nLineas = 3;
            $bloqueH = $nLineas * $lineH;
            $inicioY = 2 + (26 - $bloqueH) / 2;

            $this->SetFont('Helvetica', 'B', 7);
            $this->SetTextColor(60, 60, 60);
            $this->SetXY($pageW - 72, $inicioY);
            $this->Cell(70, $lineH, 'Tictac Comunicación Digital SL', 0, 1, 'R');

            $this->SetFont('Helvetica', '', 6.5);
            $this->SetTextColor(130, 130, 130);
            $this->SetX($pageW - 72);
            $this->Cell(70, $lineH, 'hola@tictac-comunicacion.es  ·  633 33 53 90', 0, 1, 'R');
            $this->SetX($pageW - 72);
            $this->Cell(70, $lineH, 'www.tictac-comunicacion.es', 0, 1, 'R');

            $this->SetDrawColor($this->brand_r, $this->brand_g, $this->brand_b);
            $this->SetLineWidth(0.6);
            $this->Line(0, 28, $pageW, 28);

            $this->SetTextColor(51, 51, 51);
            $this->SetMargins(15, 34, 15);
            $this->SetY(34);
        }

        public function Footer()
        {
            $pageW = $this->getPageWidth();

            $this->SetFillColor($this->brand_r, $this->brand_g, $this->brand_b);
            $this->Rect(0, $this->getPageHeight() - 14, 6, 14, 'F');

            $this->SetFillColor(248, 248, 248);
            $this->Rect(6, $this->getPageHeight() - 14, $pageW - 6, 14, 'F');

            $this->SetDrawColor($this->brand_r, $this->brand_g, $this->brand_b);
            $this->SetLineWidth(0.4);
            $this->Line(6, $this->getPageHeight() - 14, $pageW, $this->getPageHeight() - 14);

            $this->SetY($this->getPageHeight() - 11);
            $this->SetFont('Helvetica', 'B', 7.5);
            $this->SetTextColor(80, 80, 80);
            $this->SetX(12);
            $this->Cell(80, 4, 'Tictac Comunicación Digital SL', 0, 0, 'L');

            $this->SetFont('Helvetica', '', 7);
            $this->SetTextColor(130, 130, 130);
            $this->Cell(0, 4, 'C. Cruz Conde, 19, 6º 5 · 14001 Córdoba · 633 33 53 90 · hola@tictac-comunicacion.es', 0, 0, 'C');

            $this->SetFont('Helvetica', 'B', 7);
            $this->SetTextColor($this->brand_r, $this->brand_g, $this->brand_b);
            $this->SetX(0);
            $this->Cell($pageW - 10, 4, $this->PageNo() . ' / ' . $this->getNumPages(), 0, 0, 'R');
        }
    }

}