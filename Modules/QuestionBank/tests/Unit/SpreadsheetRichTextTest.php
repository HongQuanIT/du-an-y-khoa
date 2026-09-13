<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Tests\Unit;

use Modules\QuestionBank\Support\SpreadsheetRichText;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SpreadsheetRichTextTest extends TestCase
{
    #[Test]
    public function it_keeps_plain_vietnamese_text(): void
    {
        $runs = SpreadsheetRichText::fromHtml('Bệnh nhân 55 tuổi đau ngực.');

        $this->assertFalse(SpreadsheetRichText::hasInlineStyle($runs));
        $this->assertSame('Bệnh nhân 55 tuổi đau ngực.', SpreadsheetRichText::plainText($runs));
    }

    #[Test]
    public function it_extracts_bold_italic_and_line_breaks(): void
    {
        $runs = SpreadsheetRichText::fromHtml(
            '<p>Bệnh nhân <strong>55 tuổi</strong> đau <em>ngực</em>.</p><p>Hướng xử trí?</p>',
        );

        $this->assertTrue(SpreadsheetRichText::hasInlineStyle($runs));
        $this->assertSame(
            'Bệnh nhân 55 tuổi đau ngực.'."\n".'Hướng xử trí?',
            SpreadsheetRichText::plainText($runs),
        );

        $html = SpreadsheetRichText::toHtml($runs);
        $this->assertStringContainsString('<strong>55 tuổi</strong>', $html);
        $this->assertStringContainsString('<em>ngực</em>', $html);
        $this->assertStringContainsString('<br>', $html);
    }

    #[Test]
    public function it_keeps_image_tags_for_reimport(): void
    {
        $runs = SpreadsheetRichText::fromHtml(
            '<p>Xem <img src="/storage/q/ecg.png" alt="ECG"></p>',
        );

        $this->assertStringContainsString(
            '<img src="/storage/q/ecg.png" alt="ECG">',
            SpreadsheetRichText::plainText($runs),
        );
        $this->assertStringContainsString(
            '<img src="/storage/q/ecg.png" alt="ECG">',
            SpreadsheetRichText::toHtml($runs),
        );
    }
}
