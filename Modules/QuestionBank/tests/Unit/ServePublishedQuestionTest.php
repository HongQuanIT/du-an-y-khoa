<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Admin\Actions\CaptureQuestionVersionAction;
use Modules\QuestionBank\Data\ListQuestionsData;
use Modules\QuestionBank\Enums\Difficulty;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Models\MedicalTaxonomyNode;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Repositories\QuestionRepository;
use Modules\QuestionBank\Support\ServePublishedQuestion;
use Tests\Support\CreatesMedicalTaxonomy;
use Tests\TestCase;

final class ServePublishedQuestionTest extends TestCase
{
    use CreatesMedicalTaxonomy;
    use RefreshDatabase;

    public function test_overlay_and_repository_serve_published_snapshot_while_in_review(): void
    {
        $topic = $this->makeMedicalNode([
            'name' => 'Overlay topic',
            'slug' => 'overlay-topic',
            'node_type' => 'specialty',
            'sort_order' => 1,
        ]);

        $question = Question::factory()->create([
            'stem' => 'Stem bản đã xuất bản.',
            'difficulty' => Difficulty::Medium,
            'status' => QuestionStatus::Published,
            'is_free' => true,
            'version' => 1,
            'published_version' => 1,
        ]);
        $question->medicalTaxonomyNodes()->sync([$topic->id]);
        $question->options()->create([
            'label' => 'A',
            'content' => 'Đúng',
            'is_correct' => true,
            'order' => 1,
        ]);
        $question->options()->create([
            'label' => 'B',
            'content' => 'Sai',
            'is_correct' => false,
            'order' => 2,
        ]);
        $question = $question->fresh(['options', 'medicalTaxonomyNodes']);
        app(CaptureQuestionVersionAction::class)->handle($question, null, 'publish');

        $question->forceFill([
            'stem' => 'Stem working copy đang chờ duyệt.',
            'status' => QuestionStatus::InReview,
        ])->save();

        $this->assertTrue(ServePublishedQuestion::needsOverlay($question->fresh()));
        $served = ServePublishedQuestion::overlay($question->fresh(['options']));
        $this->assertSame('Stem bản đã xuất bản.', strip_tags($served->stem));

        $page = app(QuestionRepository::class)->paginatePublished(new ListQuestionsData(
            query: 'xuất bản',
            freeOnly: true,
            perPage: 20,
        ));

        $this->assertSame(1, $page->count());
        $this->assertSame('Stem bản đã xuất bản.', strip_tags($page->first()->stem));
        $this->assertStringNotContainsString('working copy', strip_tags($page->first()->stem));
    }
}
