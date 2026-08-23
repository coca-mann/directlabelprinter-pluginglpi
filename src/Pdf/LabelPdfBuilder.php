<?php
// src/Pdf/LabelPdfBuilder.php

namespace GlpiPlugin\Directlabelprinter\Pdf;

use Com\Tecnick\Barcode\Barcode;
use Document;
use GlpiPlugin\Directlabelprinter\Layout;
use TCPDF;
use TCPDF_FONTS;

class LabelPdfBuilder
{
    private Layout $layout;
    private TCPDF $pdf;
    private ?string $custom_font_name = null;

    public function __construct(Layout $layout)
    {
        $this->layout = $layout;
    }

    public function render(array $items): string
    {
        $width_mm = (float) $this->layout->fields['width_mm'];
        $height_mm = (float) $this->layout->fields['height_mm'];

        $orientation = $width_mm >= $height_mm ? 'L' : 'P';
        $this->pdf = new TCPDF($orientation, 'mm', [$width_mm, $height_mm], true, 'UTF-8', false);
        $this->pdf->SetMargins(0, 0, 0);
        $this->pdf->setPrintHeader(false);
        $this->pdf->setPrintFooter(false);
        $this->pdf->SetAutoPageBreak(false);

        $font_name = $this->resolveFontName();
        $elements = $this->layout->getElements();

        foreach ($items as $item) {
            $this->pdf->AddPage();
            $this->pdf->SetFont($font_name, '', 10);
            foreach ($elements as $element) {
                if (($element['type'] ?? '') === 'text') {
                    $this->drawText($element, $item, $font_name, $width_mm, $height_mm);
                } elseif (($element['type'] ?? '') === 'qrcode') {
                    $this->drawQrCode($element, $item, $height_mm);
                }
            }
        }

        return $this->pdf->Output('', 'S');
    }

    private function resolveFontName(): string
    {
        if (($this->layout->fields['font_choice'] ?? '') !== 'custom') {
            return $this->layout->fields['font_choice'] ?: 'dejavusans';
        }
        if ($this->custom_font_name !== null) {
            return $this->custom_font_name;
        }
        $document_id = (int) ($this->layout->fields['custom_font_documents_id'] ?? 0);
        if (!$document_id) {
            return 'dejavusans'; // fallback if no font was actually uploaded
        }
        $document = new Document();
        $document->getFromDB($document_id);
        $filepath = $document->fields['filepath'] ?? '';
        $path = $filepath !== '' ? GLPI_DOC_DIR . '/' . $filepath : null;
        if (!$path || !file_exists($path)) {
            return 'dejavusans';
        }
        $this->custom_font_name = TCPDF_FONTS::addTTFfont($path, 'TrueTypeUnicode', '', 96);
        return $this->custom_font_name ?: 'dejavusans';
    }

    private function resolveDataSource(array $element, array $item): string
    {
        $data_source = $element['data_source'] ?? 'titulo';
        if ($data_source === 'custom') {
            return (string) ($element['custom_text'] ?? '');
        }
        return (string) ($item[$data_source] ?? '');
    }

    private function drawText(array $element, array $item, string $font_name, float $width_mm, float $height_mm): void
    {
        $x = (float) ($element['x'] ?? 0);
        $y = (float) ($element['y'] ?? 0);
        $w = (float) ($element['width'] ?? 40);
        $h = (float) ($element['height'] ?? 8);
        $font_size = (float) ($element['font_size'] ?? 12);
        $align = match ($element['text_align'] ?? 'left') {
            'center' => 'C',
            'right'  => 'R',
            default  => 'L',
        };
        $valign = $element['text_valign'] ?? 'top';
        $wrap = (bool) ($element['allow_wrap'] ?? false);
        $has_background = (bool) ($element['has_background'] ?? false);

        $text = $this->resolveDataSource($element, $item);

        $this->pdf->SetFont($font_name, ($element['font_weight'] ?? false) ? 'B' : '', $font_size);

        if (!$wrap) {
            $measure = fn(string $s) => $this->pdf->GetStringWidth($s);
            $text = LabelElementMath::truncateText($text, $w, $measure);
        }

        if ($has_background) {
            $this->pdf->SetFillColor(0, 0, 0);
            $this->pdf->SetXY($x, $y);
            $this->pdf->Cell($w, $h, '', 0, 0, '', true);
            $this->pdf->SetTextColor(255, 255, 255);
        } else {
            $this->pdf->SetTextColor(0, 0, 0);
        }

        // Single-line boxes ('!$wrap', the common case for short asset names/refs) get their
        // vertical position from LabelElementMath::verticalOffset() (Task 9) so 'top'/
        // 'middle'/'bottom' behave exactly like the Django reference. Wrapped multi-line text
        // just top-aligns within the box via MultiCell's own flow — centering a multi-line
        // block isn't needed for this plugin's itemtypes, so it's out of scope (YAGNI). This
        // also avoids relying on MultiCell's own vertical-alignment parameter, whose exact
        // behavior can't be verified against the deployed TCPDF version from this environment
        // (see Global Constraints).
        $line_height_mm = $font_size * 0.3528 * 1.15; // pt -> mm, with a 15% line-height margin
        $content_height = $wrap ? $h : $line_height_mm;
        $y_offset = $wrap ? 0 : LabelElementMath::verticalOffset($valign, $h, $content_height);

        $this->pdf->SetXY($x, $y + $y_offset);
        $this->pdf->MultiCell($w, $content_height, $text, 0, $align, false, 1);
    }

    private function drawQrCode(array $element, array $item, float $height_mm): void
    {
        $x = (float) ($element['x'] ?? 0);
        $y_from_top = (float) ($element['y'] ?? 0);
        $size = (float) ($element['size'] ?? 25);
        $has_background = (bool) ($element['has_background'] ?? false);

        $data = $this->resolveDataSource($element, $item);
        if ($data === '') {
            return;
        }

        $barcode = new Barcode();
        $model = $barcode->getBarcodeObj(
            'QRCODE,L',
            $data,
            (int) ($size * 10),
            (int) ($size * 10),
            $has_background ? 'white' : 'black',
            [0, 0, 0, 0]
        )->setBackgroundColor($has_background ? 'black' : 'white');

        $png_data = $model->getPngData();
        $this->pdf->Image('@' . $png_data, $x, $y_from_top, $size, $size, 'PNG');
    }
}
