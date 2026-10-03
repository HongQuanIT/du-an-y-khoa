<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Html;

use App\Support\Html\SafeHtml;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SafeHtmlTest extends TestCase
{
    #[Test]
    public function it_preserves_a_safe_editor_image_width(): void
    {
        $html = SafeHtml::fromEditor(
            '<p><img src="/storage/question-editor/image.png" alt="ECG" width="640"></p>',
        );

        $this->assertStringContainsString('width="640"', $html);
        $this->assertStringContainsString('class="max-w-full h-auto', $html);
        $this->assertStringContainsString('src="/storage/question-editor/image.png"', $html);
    }

    #[Test]
    public function it_removes_invalid_or_unsafe_editor_image_widths(): void
    {
        $scriptWidth = SafeHtml::fromEditor(
            '<img src="/storage/question-editor/image.png" width="640px;position:fixed">',
        );
        $oversizedWidth = SafeHtml::fromEditor(
            '<img src="/storage/question-editor/image.png" width="9999">',
        );

        $this->assertStringNotContainsString('width=', $scriptWidth);
        $this->assertStringNotContainsString('position', $scriptWidth);
        $this->assertStringNotContainsString('width=', $oversizedWidth);
    }

    #[Test]
    public function it_positions_only_the_image_and_removes_paragraph_alignment(): void
    {
        $html = SafeHtml::fromEditor(
            '<p class="ql-align-right" onclick="alert(1)">Chữ<img src="/storage/question-editor/image.png" data-align="center"></p>',
        );

        $this->assertStringContainsString('<p>Chữ', $html);
        $this->assertStringContainsString('data-align="center"', $html);
        $this->assertStringNotContainsString('ql-align-right', $html);
        $this->assertStringNotContainsString('onclick', $html);
    }
}
