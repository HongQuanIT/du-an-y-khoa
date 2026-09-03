<?php

declare(strict_types=1);

namespace Modules\Admin\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use App\Support\Html\SafeHtml;
use Illuminate\Support\Facades\DB;
use Modules\Admin\Support\Auditor;
use Modules\Admin\Support\QuestionAccess;
use Modules\QuestionBank\Enums\Difficulty;
use Modules\QuestionBank\Enums\QuestionReviewAction;
use Modules\QuestionBank\Enums\QuestionReviewStatus;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionReviewRequest;

/**
 * Clone a question into a new draft (independent lifecycle).
 */
final class CloneQuestionAction
{
    use AsAction;

    public function __construct(
        private readonly CaptureQuestionVersionAction $captureVersion,
    ) {}

    public function handle(User $actor, Question $source, ?int $fromVersion = null): Question
    {
        return DB::transaction(function () use ($actor, $source, $fromVersion): Question {
            $source->loadMissing([
                'options' => fn ($q) => $q->orderBy('order'),
                'medicalTaxonomyNodes:id',
                'coreClinicalTopics:id',
                'tags:id',
                'hints',
            ]);

            $snapshot = null;
            if ($fromVersion !== null) {
                $version = $source->versions()->where('version', $fromVersion)->firstOrFail();
                $snapshot = $version->snapshot;
            } else {
                $snapshot = $this->captureVersion->snapshot($source);
            }

            $medicalNodeIds = collect($snapshot['medical_taxonomy_node_ids'] ?? [])
                ->map(fn ($id): int => (int) $id)
                ->filter()
                ->unique()
                ->values()
                ->all();

            if ($medicalNodeIds === []) {
                $medicalNodeIds = $source->medicalTaxonomyNodes->pluck('id')
                    ->map(fn ($id): int => (int) $id)
                    ->values()
                    ->all();
            }

            $clone = new Question;
            $clone->status = QuestionStatus::Draft;
            $clone->version = 0;
            $clone->cloned_from_id = $source->id;
            $clone->cloned_from_version = $fromVersion ?? $source->version;
            $clone->created_by = $actor->getKey();
            $clone->updated_by = $actor->getKey();
            $clone->fill([
                'stem' => (string) ($snapshot['stem'] ?? $source->stem),
                'stem_image_path' => $snapshot['stem_image_path'] ?? $source->stem_image_path,
                'explanation' => $snapshot['explanation'] ?? $source->explanation,
                'key_info' => array_values((array) ($snapshot['key_info'] ?? $source->key_info ?? [])),
                'attending_tip' => $snapshot['attending_tip'] ?? $source->attending_tip,
                'difficulty' => Difficulty::from((string) ($snapshot['difficulty'] ?? $source->difficulty->value)),
                'is_free' => (bool) ($snapshot['is_free'] ?? $source->is_free),
                'exam_flag' => (bool) ($snapshot['exam_flag'] ?? $source->exam_flag ?? false),
            ]);
            $clone->save();
            $clone->medicalTaxonomyNodes()->sync($medicalNodeIds);

            $coreIds = collect($snapshot['core_clinical_topic_ids'] ?? [])
                ->map(fn ($id): int => (int) $id)
                ->filter()
                ->unique()
                ->values()
                ->all();
            if ($coreIds !== []) {
                $clone->coreClinicalTopics()->sync($coreIds);
            }

            $tagIds = collect($snapshot['tag_ids'] ?? [])
                ->map(fn ($id): int => (int) $id)
                ->filter()
                ->unique()
                ->values()
                ->all();
            if ($tagIds !== []) {
                $clone->tags()->sync($tagIds);
            }

            $this->syncOptionsFromSnapshot($clone, $snapshot);

            $clone->load('options', 'medicalTaxonomyNodes');

            if (! QuestionAccess::isReviewer($actor)) {
                QuestionReviewRequest::query()->create([
                    'question_id' => $clone->getKey(),
                    'action' => QuestionReviewAction::Create,
                    'status' => QuestionReviewStatus::Pending,
                    'requested_by' => $actor->getKey(),
                ]);
            }

            Auditor::record(
                'admin.question.clone',
                $actor,
                $clone,
                null,
                [
                    'cloned_from_id' => $source->id,
                    'cloned_from_version' => $fromVersion,
                ],
            );

            return $clone;
        });
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function syncOptionsFromSnapshot(Question $question, array $snapshot): void
    {
        $options = $snapshot['options'] ?? [];

        foreach (array_values($options) as $index => $row) {
            $question->options()->create([
                'label' => $row['label'] ?? chr(65 + $index),
                'content' => SafeHtml::fromEditor((string) ($row['content'] ?? '')),
                'is_correct' => (bool) ($row['is_correct'] ?? false),
                'explanation' => SafeHtml::fromEditor($row['explanation'] ?? null) ?: null,
                'order' => (int) ($row['order'] ?? ($index + 1)),
            ]);
        }
    }
}
