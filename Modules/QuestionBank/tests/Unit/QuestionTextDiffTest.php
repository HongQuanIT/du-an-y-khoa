<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Tests\Unit;

use Modules\QuestionBank\Support\QuestionTextDiff;
use Tests\TestCase;

final class QuestionTextDiffTest extends TestCase
{
    public function test_identical_text_is_not_highlighted(): void
    {
        $diff = (new QuestionTextDiff)->highlight('Sốt cao 3 ngày.', 'Sốt cao 3 ngày.');

        $this->assertFalse($diff['changed']);
        $this->assertSame('Sốt cao 3 ngày.', html_entity_decode(strip_tags($diff['published_html'])));
        $this->assertStringNotContainsString('<del', $diff['published_html']);
        $this->assertStringNotContainsString('<ins', $diff['proposed_html']);
    }

    public function test_highlights_removed_and_added_words(): void
    {
        $diff = (new QuestionTextDiff)->highlight(
            'Bệnh nhân sốt cao 3 ngày.',
            'Bệnh nhân sốt nhẹ 5 ngày.',
        );

        $this->assertTrue($diff['changed']);
        $this->assertStringContainsString('<del', $diff['published_html']);
        $this->assertStringContainsString('cao', $diff['published_html']);
        $this->assertStringContainsString('3', $diff['published_html']);
        $this->assertStringContainsString('<ins', $diff['proposed_html']);
        $this->assertStringContainsString('nhẹ', $diff['proposed_html']);
        $this->assertStringContainsString('5', $diff['proposed_html']);
        $this->assertStringContainsString('Bệnh nhân sốt', html_entity_decode(strip_tags($diff['published_html'])));
    }

    public function test_strips_html_before_comparing(): void
    {
        $diff = (new QuestionTextDiff)->highlight('<p>ABC</p>', '<strong>ABC</strong>');

        $this->assertFalse($diff['changed']);
        $this->assertSame('ABC', strip_tags($diff['published_html']));
    }
}
