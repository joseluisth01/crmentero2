<?php
/**
 * debug_pdf.php v3 — Ejecuta generarPDF() completo y captura el error exacto
 * Abre: /dashboard/contratos/debug_pdf.php?id=CONT-20260217-0001
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Capturar TODO output incluyendo warnings
ob_start();

require_once '../config.php';

$tcpdfPath = BASE_PATH . '/tcpdf/tcpdf.php';
require_once $tcpdfPath;

$contratosFile = DATA_PATH . '/contratos.json';
$id = isset($_GET['id']) ? $_GET['id'] : 'CONT-20260217-0001';

// Leer contrato
$contratos = json_decode(file_get_contents($contratosFile), true);
$contrato = null;
foreach ($contratos as $c) {
    if (isset($c['id']) && $c['id'] === $id) { $contrato = $c; break; }
}
if (!$contrato) die('Contrato no encontrado: ' . $id);

// ── helpers ────────────────────────────────────────────────────
function pdf_text_plain($html) {
    if ($html === null) return '';
    $txt = html_entity_decode((string)$html, ENT_QUOTES, 'UTF-8');
    $txt = preg_replace('/<br\s*\/?>/i', "\n", $txt);
    $txt = preg_replace('/<\/p>/i', "\n", $txt);
    $txt = preg_replace('/<p[^>]*>/i', '', $txt);
    $txt = strip_tags($txt);
    $txt = str_replace("\xC2\xA0", ' ', $txt);
    $txt = preg_replace('/[ \t]+/', ' ', $txt);
    return trim($txt);
}
function tiene_contenido($html) {
    return $html !== null && pdf_text_plain($html) !== '';
}

// ── Clase PDF ──────────────────────────────────────────────────
class ContratoTictacPDF extends TCPDF {
    public function Header() {
        $w = $this->getPageWidth();
        $this->SetFillColor(233, 30, 140);
        $this->RoundedRect(0, 0, $w, 38, 4, '1111', 'F');
        $logoLocal = BASE_PATH . '/assets/img/logoblanco.png';
        if (file_exists($logoLocal)) {
            $this->Image($logoLocal, ($w - 36) / 2, 5, 36, 0, '', '', '', false, 300);
        }
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Helvetica', 'B', 15);
        $this->SetXY(0, 20);
        $this->Cell($w, 10, 'Contrato de Servicios', 0, 1, 'C');
        $this->SetTextColor(51, 51, 51);
        $this->SetMargins(20, 46, 20);
        $this->SetY(46);
    }
    public function Footer() {
        $w = $this->getPageWidth();
        $this->SetY(-32);
        $this->SetDrawColor(233, 30, 140);
        $this->SetLineWidth(0.4);
        $this->Line(15, $this->GetY(), $w - 15, $this->GetY());
        $this->Ln(3);
        $this->SetFont('Helvetica', 'B', 9);
        $this->SetTextColor(51, 51, 51);
        $this->Cell($w, 4, 'Tictac Comunicacion Digital SL', 0, 1, 'C');
        $this->SetFont('Helvetica', '', 7);
        $this->SetTextColor(100, 100, 100);
        $this->Ln(1);
        $this->SetX(0);
        $this->Cell($w, 3, 'C/ Escultor Ramon Barba, 1 - Bloque F - 1-2  14012 Cordoba', 0, 1, 'C');
        $this->Ln(1);
        $this->SetFont('Helvetica', 'I', 7);
        $this->SetX(0);
        $this->Cell($w, 3, 'Pagina ' . $this->PageNo() . ' / ' . $this->getNumPages(), 0, 1, 'C');
    }
}

// ── Datos ──────────────────────────────────────────────────────
$contractTitle = isset($contrato['titulo']) ? $contrato['titulo'] : 'Contrato';
$contractDate  = !empty($contrato['fecha_contrato']) ? date('d/m/Y', strtotime($contrato['fecha_contrato'])) : date('d/m/Y');
$contractId    = $contrato['id'];
$firmanteName  = !empty($contrato['cliente_firmante']) ? $contrato['cliente_firmante'] : ($contrato['cliente_nombre'] ?? '');

$toLines = array();
$toLines[] = 'Denominacion: ' . ($contrato['cliente_nombre'] ?? '');
if (!empty($contrato['cliente_cif']))       $toLines[] = 'NIF/CIF: ' . $contrato['cliente_cif'];
if (!empty($contrato['cliente_direccion'])) $toLines[] = 'Domicilio: ' . $contrato['cliente_direccion'];
if (!empty($contrato['cliente_email']))     $toLines[] = 'Email: ' . $contrato['cliente_email'];
$contractToInfo = implode("\n", $toLines);

$items      = isset($contrato['items']) && is_array($contrato['items']) ? $contrato['items'] : array();
$subtotal   = floatval($contrato['subtotal'] ?? 0);
$iva        = floatval($contrato['iva']      ?? 21);
$seg        = floatval($contrato['segundo_impuesto'] ?? 0);
$ivaAmt     = $subtotal * $iva / 100;
$segAmt     = $subtotal * $seg / 100;
$totalFinal = floatval($contrato['total']    ?? ($subtotal + $ivaAmt + $segAmt));
$notasTexto = tiene_contenido($contrato['notas'] ?? '') ? pdf_text_plain($contrato['notas']) : '';

// ── Ejecutar en pasos con try/catch ────────────────────────────
$step = 0;
try {
    $step = 1;
    $pdf = new ContratoTictacPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetAutoPageBreak(true, 38);
    $pdf->SetCreator('Tictac');
    $pdf->SetTitle('Contrato ' . $contractId);
    
    $step = 2;
    $pdf->AddPage();
    $pdf->SetMargins(20, 46, 20);
    $pdf->SetY(46);
    
    $W  = $pdf->getPageWidth();
    $cW = $W - 40;

    $step = 3; // Cabecera negra del contrato
    $pdf->SetFillColor(30, 30, 30);
    $pdf->RoundedRect(20, $pdf->GetY(), $cW, 16, 3, '1111', 'F');
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Helvetica', 'B', 12);
    $pdf->SetX(22); $pdf->Cell($cW - 4, 8, $contractTitle, 0, 1, 'C');
    $pdf->SetFont('Helvetica', '', 8.5);
    $pdf->SetX(22); $pdf->Cell($cW - 4, 5, 'Tictac Comunicacion', 0, 1, 'C');
    $pdf->SetTextColor(51, 51, 51);
    $pdf->Ln(4);

    $step = 4; // Dos columnas
    $startY = $pdf->GetY();
    $half   = ($cW - 5) / 2;
    $pdf->SetFillColor(255, 255, 255);
    $pdf->SetDrawColor(220, 220, 220);
    $pdf->RoundedRect(20, $startY, $half, 26, 2, '1111', 'DF');
    $pdf->SetXY(23, $startY + 3);
    $pdf->SetFont('Helvetica', 'B', 8.5); $pdf->SetTextColor(233, 30, 140);
    $pdf->Cell($half - 6, 4, 'DATOS DEL CONTRATO', 0, 1, 'L');
    $pdf->SetTextColor(51, 51, 51);
    $pdf->SetX(23); $pdf->SetFont('Helvetica', 'B', 8.5); $pdf->Cell(30, 4, 'N Contrato:', 0, 0); $pdf->SetFont('Helvetica', '', 8.5); $pdf->Cell(0, 4, $contractId, 0, 1);
    $pdf->SetX(23); $pdf->SetFont('Helvetica', 'B', 8.5); $pdf->Cell(30, 4, 'Fecha:', 0, 0); $pdf->SetFont('Helvetica', '', 8.5); $pdf->Cell(0, 4, $contractDate, 0, 1);

    $rightX = 20 + $half + 5;
    $pdf->SetFillColor(255, 255, 255);
    $pdf->SetDrawColor(220, 220, 220);
    $pdf->RoundedRect($rightX, $startY, $half, 26, 2, '1111', 'DF');
    $pdf->SetXY($rightX + 3, $startY + 3);
    $pdf->SetFont('Helvetica', 'B', 8.5); $pdf->SetTextColor(233, 30, 140);
    $pdf->Cell($half - 6, 4, 'EL CLIENTE', 0, 1, 'L');
    $pdf->SetTextColor(51, 51, 51); $pdf->SetFont('Helvetica', '', 8.5);
    foreach (explode("\n", $contractToInfo) as $line) {
        $line = trim($line); if (!$line) continue;
        $pdf->SetX($rightX + 3);
        $pdf->MultiCell($half - 6, 3.8, $line, 0, 'L');
    }
    $pdf->SetY($startY + 30);
    $pdf->Ln(4);

    $step = 5; // Texto intro
    $pdf->SetFont('Helvetica', '', 8.5);
    $pdf->SetX(20);
    $pdf->MultiCell($cW, 4.3, "De una parte El Proveedor: TIC TAC COMUNICACION DIGITAL S.L. NIF B09912478.\n\nDe otra parte El Cliente:\n" . $contractToInfo . "\n\nEn virtud de todo lo expuesto, las partes suscriben el presente CONTRATO DE PRESTACION DE SERVICIOS:", 0, 'J');
    $pdf->Ln(4);

    $step = 6; // Título CLAUSULAS
    $pdf->SetFillColor(30, 30, 30);
    $pdf->RoundedRect(20, $pdf->GetY(), $cW, 8, 2, '1111', 'F');
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Helvetica', 'B', 11);
    $pdf->SetX(22); $pdf->Cell($cW - 4, 8, 'CLAUSULAS', 0, 1, 'C');
    $pdf->SetTextColor(51, 51, 51);
    $pdf->Ln(3);

    $step = 7; // Cláusula 1
    $pdf->SetFillColor(255, 240, 247);
    $pdf->RoundedRect(20, $pdf->GetY(), $cW, 7, 2, '1111', 'F');
    $pdf->SetX(22); $pdf->SetFont('Helvetica', 'B', 10); $pdf->SetTextColor(233, 30, 140);
    $pdf->Cell($cW - 4, 7, '1. OBJETO', 0, 1, 'L');
    $pdf->SetTextColor(51, 51, 51);
    $pdf->Ln(1);
    $pdf->SetFont('Helvetica', '', 8.5); $pdf->SetX(20);
    $pdf->MultiCell($cW, 4.3, 'El objeto del Contrato consiste en la prestacion de servicios por parte del Proveedor a cambio del pago de un precio por parte del Cliente.', 0, 'J');
    $pdf->Ln(3);

    $step = 8; // Tabla items
    $cArt = 70; $cCant = 25; $cTar = 30; $cTot = 30;
    $tW   = $cArt + $cCant + $cTar + $cTot;
    $pdf->SetFillColor(30, 30, 30);
    $pdf->Rect(20, $pdf->GetY(), $tW, 7, 'F');
    $pdf->SetXY(22, $pdf->GetY());
    $pdf->SetTextColor(255, 255, 255); $pdf->SetFont('Helvetica', 'B', 8.5);
    $pdf->Cell($cArt - 2, 7, 'Articulo', 0, 0, 'L');
    $pdf->Cell($cCant,    7, 'Cant',     0, 0, 'C');
    $pdf->Cell($cTar,     7, 'Precio',   0, 0, 'R');
    $pdf->Cell($cTot - 2, 7, 'Total',    0, 1, 'R');
    $pdf->SetTextColor(51, 51, 51);
    foreach ($items as $item) {
        $cant = floatval($item['cantidad'] ?? 1);
        $prec = floatval($item['precio']   ?? 0);
        $pdf->SetX(22); $pdf->SetFont('Helvetica', '', 8.5);
        $pdf->Cell($cArt - 2, 6, $item['nombre'] ?? '', 0, 0, 'L');
        $pdf->Cell($cCant,    6, number_format($cant, 2, ',', '.'), 0, 0, 'C');
        $pdf->Cell($cTar,     6, number_format($prec, 2, ',', '.') . 'E', 0, 0, 'R');
        $pdf->Cell($cTot - 2, 6, number_format($cant * $prec, 2, ',', '.') . 'E', 0, 1, 'R');
    }

    $step = 9; // Totales y notas
    $pdf->Ln(3);
    $lW = 40; $vW = 28; $rightEnd = 20 + $tW;
    $ty = $pdf->GetY();
    $pdf->SetFillColor(30, 30, 30);
    $pdf->Rect($rightEnd - $lW - $vW, $ty, $lW + $vW, 8, 'F');
    $pdf->SetXY($rightEnd - $lW - $vW, $ty);
    $pdf->SetTextColor(255, 255, 255); $pdf->SetFont('Helvetica', 'B', 10);
    $pdf->Cell($lW, 8, 'TOTAL', 0, 0, 'R');
    $pdf->Cell($vW, 8, number_format($totalFinal, 2, ',', '.') . 'E', 0, 1, 'R');
    $pdf->SetTextColor(51, 51, 51);
    $pdf->Ln(5);

    $step = 10; // Notas
    $pdf->SetFillColor(255, 248, 225);
    $pdf->RoundedRect(20, $pdf->GetY(), $cW, 7, 2, '1111', 'F');
    $pdf->SetXY(22, $pdf->GetY() + 1);
    $pdf->SetFont('Helvetica', 'B', 10); $pdf->SetTextColor(100, 80, 0);
    $pdf->Cell($cW - 4, 5, 'NOTAS ADHERIDAS:', 0, 1, 'L');
    $pdf->SetTextColor(51, 51, 51); $pdf->Ln(2);
    if ($notasTexto) {
        $pdf->SetX(22); $pdf->SetFont('Helvetica', '', 9);
        $pdf->MultiCell($cW - 4, 4.3, $notasTexto, 0, 'J');
    } else {
        $pdf->SetFont('Helvetica', 'I', 9); $pdf->SetTextColor(150, 150, 150);
        $pdf->SetX(22); $pdf->Cell($cW - 4, 5, '(Sin notas adicionales)', 0, 1);
        $pdf->SetTextColor(51, 51, 51);
    }
    $pdf->Ln(8);

    $step = 11; // Firmas
    $half2  = ($cW - 5) / 2;
    $firmaY = $pdf->GetY();
    $pdf->SetFont('Helvetica', 'B', 9); $pdf->SetTextColor(233, 30, 140);
    $pdf->SetX(20); $pdf->Cell($half2, 5, 'FIRMA PROVEEDOR', 0, 1, 'C');
    $pdf->SetTextColor(51, 51, 51); $pdf->Ln(14);
    $pdf->SetDrawColor(170, 170, 170); $pdf->SetLineWidth(0.4);
    $pdf->Line(20, $pdf->GetY(), 20 + $half2 - 5, $pdf->GetY()); $pdf->Ln(3);
    $pdf->SetFont('Helvetica', '', 8); $pdf->SetX(20);
    $pdf->Cell($half2, 4, 'Tictac Comunicacion Digital SL', 0, 1, 'C');

    $pdf->SetY($firmaY);
    $pdf->SetFont('Helvetica', 'B', 9); $pdf->SetTextColor(233, 30, 140);
    $pdf->SetX(20 + $half2 + 5); $pdf->Cell($half2, 5, 'FIRMA CLIENTE', 0, 1, 'C');
    $pdf->SetTextColor(51, 51, 51); $pdf->Ln(14);
    $pdf->Line(20 + $half2 + 5, $pdf->GetY(), 20 + $cW, $pdf->GetY()); $pdf->Ln(3);
    $pdf->SetFont('Helvetica', '', 8); $pdf->SetX(20 + $half2 + 5);
    $pdf->Cell($half2, 4, $firmanteName, 0, 1, 'C');

    $step = 12; // Output
    $warnings = ob_get_clean();
    
    // Mostrar warnings si los hay, si no generar PDF
    if ($warnings) {
        header('Content-Type: text/plain; charset=utf-8');
        echo "⚠️ WARNINGS CAPTURADOS (step $step):\n\n";
        echo $warnings;
        echo "\n\n--- FIN WARNINGS ---";
    } else {
        $pdf->Output('test_contrato.pdf', 'D');
    }

} catch (Throwable $e) {
    $warnings = ob_get_clean();
    header('Content-Type: text/plain; charset=utf-8');
    echo "❌ FATAL ERROR en step $step:\n";
    echo $e->getMessage() . "\n";
    echo "Archivo: " . $e->getFile() . "\n";
    echo "Línea: " . $e->getLine() . "\n\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n\n";
    if ($warnings) {
        echo "Warnings previos:\n" . $warnings;
    }
}