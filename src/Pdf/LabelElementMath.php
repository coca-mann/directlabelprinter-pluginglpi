<?php
// src/Pdf/LabelElementMath.php

namespace GlpiPlugin\Directlabelprinter\Pdf;

class LabelElementMath
{
    /**
     * Truncates $text with a trailing "..." so that measureWidthMm($result) <= $maxWidthMm.
     * Uses a caller-supplied width measurer so this stays testable without a real font engine
     * (production usage injects a closure wrapping TCPDF::GetStringWidth()).
     */
    public static function truncateText(string $text, float $maxWidthMm, callable $measureWidthMm): string
    {
        if ($text === '' || $measureWidthMm($text) <= $maxWidthMm) {
            return $text;
        }

        $low = 0;
        $high = mb_strlen($text);
        $best = '';

        while ($low <= $high) {
            $mid = intdiv($low + $high, 2);
            $candidate = mb_substr($text, 0, $mid) . '...';
            if ($measureWidthMm($candidate) <= $maxWidthMm) {
                $best = $candidate;
                $low = $mid + 1;
            } else {
                $high = $mid - 1;
            }
        }

        return $best !== '' ? $best : '...';
    }

    /**
     * Vertical distance (mm) from the bottom of a $boxHeightMm-tall box to where
     * $contentHeightMm-tall content should start drawing, for a given alignment.
     */
    public static function verticalOffset(string $valign, float $boxHeightMm, float $contentHeightMm): float
    {
        return match ($valign) {
            'middle' => ($boxHeightMm - $contentHeightMm) / 2,
            'top'    => 0.0,
            default  => $boxHeightMm - $contentHeightMm, // 'bottom'
        };
    }
}
