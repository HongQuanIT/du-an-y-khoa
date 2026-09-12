<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Admin\Actions\CaptureQuestionVersionAction;
use Modules\QuestionBank\Enums\Difficulty;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Support\QuestionReviewComparison;
use Tests\Support\CreatesMedicalTaxonomy;
use Tests\TestCase;

final class QuestionReviewComparisonTest extends TestCase
{
    use CreatesMedicalTaxonomy;
    use RefreshDatabase;

    public function test_returns_null_when_question_has_never_been_published(): void
    {
        $question = Question::factory()->create([
            'status' => QuestionStatus::InReview,
            'version' => 0,
            'published_version' => null,
        ]);

        $this->assertNull(app(QuestionReviewComparison::class)->compare($question));
    }

    public function test_highlights_working_copy_changes_against_published_snapshot(): void
    {
        $oldLesson = $this->makeLesson([
            'name' => 'Nội tiết',
            'slug' => 'noi-tiet-compare',
            'sort_order' => 1,
        ]);
        $newLesson = $this->makeLesson([
            'name' => 'Tim mạch',
            'slug' => 'tim-mach-compare',
            'sort_order' => 2,
        ]);

        $question = Question::factory()->create([
            'stem' => 'Bệnh nhân sốt cao 3 ngày. Chẩn đoán phù hợp?',
            'explanation' => 'Giải thích bản xuất bản.',
            'key_info' => ['Sốt', 'Nhiễm khuẩn'],
            'attending_tip' => 'Nhớ cấy máu.',
            'difficulty' => Difficulty::Medium,
            'status' => QuestionStatus::Published,
            'version' => 1,
            'published_version' => 1,
        ]);
        $question->lessons()->sync([$oldLesson->id]);
        $keep = $question->options()->create([
            'label' => 'A',
            'content' => 'Virus',
            'is_correct' => true,
            'explanation' => 'Đúng',
            'order' => 1,
        ]);
        $removed = $question->options()->create([
            'label' => 'B',
            'content' => 'Vi khuẩn',
            'is_correct' => false,
            'explanation' => 'Sai',
            'order' => 2,
        ]);
        $question = $question->fresh(['options', 'lessons']);
        app(CaptureQuestionVersionAction::class)->handle($question, null, 'publish');

        $keep->forceFill(['content' => 'Virus hợp bào hô hấp'])->save();
        $removed->delete();
        $question->options()->create([
            'label' => 'C',
            'content' => 'Nấm',
            'is_correct' => false,
            'explanation' => 'Nhiễu mới',
            'order' => 3,
        ]);
        $question->forceFill([
            'stem' => 'Bệnh nhân sốt nhẹ 5 ngày. Chẩn đoán phù hợp?',
            'explanation' => 'Giải thích bản gửi duyệt.',
            'key_info' => ['Sốt', 'Cúm'],
            'attending_tip' => 'Nhớ PCR.',
            'difficulty' => Difficulty::Hard,
            'status' => QuestionStatus::InReview,
        ])->save();
        $question->lessons()->sync([$newLesson->id]);

        $comparison = app(QuestionReviewComparison::class)->compare($question->fresh(['options', 'lessons']));

        $this->assertNotNull($comparison);
        $this->assertTrue($comparison['can_compare']);
        $this->assertSame(1, $comparison['published_version']);
        $this->assertTrue($comparison['has_changes']);
        $this->assertContains('Đề bài', $comparison['changed_labels']);
        $this->assertContains('Đáp án', $comparison['changed_labels']);
        $this->assertContains('Bài học', $comparison['changed_labels']);
        $this->assertStringContainsString('<del', $comparison['stem']['published_html']);
        $this->assertStringContainsString('cao', $comparison['stem']['published_html']);
        $this->assertStringContainsString('<ins', $comparison['stem']['proposed_html']);
        $this->assertStringContainsString('nhẹ', $comparison['stem']['proposed_html']);
        $this->assertTrue($comparison['difficulty']['changed']);
        $this->assertSame('Trung bình', $comparison['difficulty']['published']);
        $this->assertSame('Khó', $comparison['difficulty']['proposed']);
        $this->assertSame('removed', $comparison['lessons']['published'][0]['change']);
        $this->assertSame('added', $comparison['lessons']['proposed'][0]['change']);

        $changes = collect($comparison['options'])->pluck('change')->all();
        $this->assertContains('modified', $changes);
        $this->assertContains('removed', $changes);
        $this->assertContains('added', $changes);
    }
}
