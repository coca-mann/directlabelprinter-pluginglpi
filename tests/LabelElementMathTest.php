<?php

namespace GlpiPlugin\Directlabelprinter\Tests;

use GlpiPlugin\Directlabelprinter\Pdf\LabelElementMath;
use PHPUnit\Framework\TestCase;

final class LabelElementMathTest extends TestCase
{
    public function testTruncateTextReturnsUnchangedWhenItFits(): void
    {
        $measure = fn(string $text) => strlen($text) * 2.0; // 2mm per char, fake measurer
        $result = LabelElementMath::truncateText('ABC', 10.0, $measure);
        $this->assertSame('ABC', $result);
    }

    public function testTruncateTextAddsEllipsisWhenTooLong(): void
    {
        $measure = fn(string $text) => strlen($text) * 2.0;
        // "ABCDEFGHIJ" is 20mm wide at 2mm/char, box is only 10mm -> must truncate.
        $result = LabelElementMath::truncateText('ABCDEFGHIJ', 10.0, $measure);
        $this->assertStringEndsWith('...', $result);
        $this->assertLessThan(10, strlen($result));
    }

    public function testTruncateTextHandlesEmptyString(): void
    {
        $measure = fn(string $text) => strlen($text) * 2.0;
        $this->assertSame('', LabelElementMath::truncateText('', 10.0, $measure));
    }

    public function testVerticalOffsetTop(): void
    {
        $this->assertSame(0.0, LabelElementMath::verticalOffset('top', 20.0, 8.0));
    }

    public function testVerticalOffsetMiddleCenters(): void
    {
        $this->assertSame(6.0, LabelElementMath::verticalOffset('middle', 20.0, 8.0));
    }

    public function testVerticalOffsetBottom(): void
    {
        $this->assertSame(12.0, LabelElementMath::verticalOffset('bottom', 20.0, 8.0));
    }
}
