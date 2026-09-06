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

    public function test_publish_snapshot_includes_option_ids_and_overlay_preserves_them(): void
    {
        $question = Question::factory()->create([
            'stem' => 'Stem published with option ids.',
            'status' => QuestionStatus::Published,
            'is_free' => true,
            'version' => 1,
            'published_version' => 1,
        ]);
        $correct = $question->options()->create([
            'label' => 'A',
            'content' => 'Đúng',
            'is_correct' => true,
            'order' => 1,
        ]);
        $wrong = $question->options()->create([
            'label' => 'B',
            'content' => 'Sai',
            'is_correct' => false,
            'order' => 2,
        ]);

        $question = $question->fresh(['options']);
        $version = app(CaptureQuestionVersionAction::class)->handle($question, null, 'publish');
        $snapshotOptions = $version->snapshot['options'] ?? [];

        $this->assertSame((int) $correct->id, (int) ($snapshotOptions[0]['id'] ?? 0));
        $this->assertSame((int) $wrong->id, (int) ($snapshotOptions[1]['id'] ?? 0));

        $question->forceFill([
            'stem' => 'Working copy stem',
            'status' => QuestionStatus::InReview,
        ])->save();

        $served = ServePublishedQuestion::overlay($question->fresh(['options']));
        $servedIds = $served->options->pluck('id')->map(fn ($id): int => (int) $id)->all();

        $this->assertEqualsCanonicalizing([(int) $correct->id, (int) $wrong->id], $servedIds);
        $this->assertSame('Stem published with option ids.', strip_tags($served->stem));
    }

    public function test_overlay_resolves_missing_option_ids_via_content_and_order(): void
    {
        $question = Question::factory()->create([
            'stem' => 'Legacy snapshot stem.',
            'status' => QuestionStatus::Published,
            'version' => 1,
            'published_version' => 1,
        ]);
        $correct = $question->options()->create([
            'label' => 'A',
            'content' => 'Match me',
            'is_correct' => true,
            'order' => 1,
        ]);
        $wrong = $question->options()->create([
            'label' => 'B',
            'content' => 'Other',
            'is_correct' => false,
            'order' => 2,
        ]);

        $question = $question->fresh(['options']);
        $version = app(CaptureQuestionVersionAction::class)->handle($question, null, 'publish');
        $snapshot = $version->snapshot;
        unset($snapshot['options'][0]['id'], $snapshot['options'][1]['id']);
        $version->forceFill(['snapshot' => $snapshot])->save();

        $question->forceFill(['status' => QuestionStatus::InReview])->save();

        $served = ServePublishedQuestion::overlay($question->fresh(['options']));
        $this->assertSame((int) $correct->id, (int) $served->options->firstWhere('content', 'Match me')->getKey());
        $this->assertSame((int) $wrong->id, (int) $served->options->firstWhere('content', 'Other')->getKey());
    }
}
