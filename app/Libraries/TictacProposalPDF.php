<?php
/**
 * Clase TCPDF personalizada para los presupuestos de Tictac Comunicación.
 * Este archivo se incluye mediante require_once DENTRO de _generate_tictac_pdf(),
 * DESPUÉS de que tcpdf.php ya haya sido cargado, para que TCPDF esté disponible.
 *
 * Ruta en servidor: /home/gestiontictaccom/public_html/app/Libraries/TictacProposalPDF.php
 */

if (!class_exists('TictacProposalPDF')) {

    class TictacProposalPDF extends TCPDF
    {
        public $brand_r   = 215;
        public $brand_g   = 33;
        public $brand_b   = 115;
        public $logo_path = '';

        public function Header()
        {
            $pageW = $this->getPageWidth();

            // ── Fondo blanco puro ────────────────────────────────────────
            $this->SetFillColor(255, 255, 255);
            $this->Rect(0, 0, $pageW, 46, 'F');

            // ── Barra izquierda de acento (franja vertical rosa) ─────────
            $this->SetFillColor($this->brand_r, $this->brand_g, $this->brand_b);
            $this->Rect(0, 0, 6, 46, 'F');

            // ── Logo alineado a la izquierda con margen ──────────────────
            if ($this->logo_path && file_exists($this->logo_path)) {
                // Logo en versión de color (no blanco) — si solo tienes blanco,
                // lo ponemos sobre un bloque rosa pequeño a la izquierda
                $this->SetFillColor($this->brand_r, $this->brand_g, $this->brand_b);
                $this->Rect(0, 0, 52, 46, 'F');
                $logoW = 36;
                $logoX = (52 - $logoW) / 2;
                $this->Image($this->logo_path, $logoX, 5, $logoW, 0, '', '', '', false, 300, '', false, false, 0, false, false, false);
            }

            // ── Nombre empresa — tipografía grande, bold ─────────────────
            $this->SetTextColor(30, 30, 30);
            $this->SetFont('Helvetica', 'B', 18);
            $this->SetXY(58, 8);
            $this->Cell($pageW - 70, 9, 'PRESUPUESTO', 0, 1, 'L');

            // ── Tagline / subtítulo ──────────────────────────────────────
            $this->SetFont('Helvetica', '', 8);
            $this->SetTextColor(160, 160, 160);
            $this->SetX(58);
            $this->Cell($pageW - 70, 5, 'Tictac Comunicación Digital SL  ·  www.tictac-comunicacion.es', 0, 1, 'L');

            // ── Línea inferior fina en rosa ──────────────────────────────
            $this->SetDrawColor($this->brand_r, $this->brand_g, $this->brand_b);
            $this->SetLineWidth(0.6);
            $this->Line(0, 46, $pageW, 46);

            $this->SetTextColor(51, 51, 51);
            $this->SetMargins(15, 52, 15);
            $this->SetY(52);
        }

        public function Footer()
        {
            $pageW = $this->getPageWidth();

            // ── Barra izquierda de acento en pie ─────────────────────────
            $this->SetFillColor($this->brand_r, $this->brand_g, $this->brand_b);
            $this->Rect(0, $this->getPageHeight() - 14, 6, 14, 'F');

            // ── Fondo gris muy suave ──────────────────────────────────────
            $this->SetFillColor(248, 248, 248);
            $this->Rect(6, $this->getPageHeight() - 14, $pageW - 6, 14, 'F');

            // ── Línea superior del pie ────────────────────────────────────
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
            $this->Cell(0, 4, 'Plaza de los Carrillos, 5 · 14001 Córdoba · 957 048 147 · hola@tictac-comunicacion.es', 0, 0, 'C');

            $this->SetFont('Helvetica', 'B', 7);
            $this->SetTextColor($this->brand_r, $this->brand_g, $this->brand_b);
            $this->SetX(0);
            $this->Cell($pageW - 10, 4, $this->PageNo() . ' / ' . $this->getNumPages(), 0, 0, 'R');
        }
    }

}