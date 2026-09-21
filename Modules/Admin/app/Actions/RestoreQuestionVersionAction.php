<?php

declare(strict_types=1);

namespace Modules\Admin\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Admin\Enums\AuditAction;
use Modules\Admin\Support\Auditor;
use Modules\Admin\Support\AuditSnapshot;
use Modules\QuestionBank\Enums\QuestionReviewAction;
use Modules\QuestionBank\Enums\QuestionReviewStatus;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Models\Lesson;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionVersion;
use Modules\QuestionBank\Support\QuestionInstructorReviewCycle;

/**
 * Apply a published snapshot onto the working copy as draft.
 * Does NOT increment version — only Admin publish creates question_versions.
 */
final class RestoreQuestionVersionAction
{
    public function __construct(
        private readonly QuestionInstructorReviewCycle $reviewCycle,
    ) {}

    public function handle(User $actor, Question $question, QuestionVersion $version): Question
    {
        abort_unless((string) $version->question_id === (string) $question->getKey(), 404);

        return DB::transaction(function () use ($actor, $question, $version): Question {
            $question = Question::query()->lockForUpdate()->findOrFail($question->getKey());
            $before = AuditSnapshot::question($question);
            $fromStatus = $question->status instanceof QuestionStatus
                ? $question->status
                : QuestionStatus::tryFrom((string) $question->status);

            $snapshot = $version->snapshot;
            // Accept both the new key and the legacy key for old snapshots.
            $snapshotLessonIds = (array) ($snapshot['lesson_ids'] ?? $snapshot['medical_taxonomy_node_ids'] ?? []);
            $lessonIds = Lesson::query()
                ->whereIn('id', array_map('intval', $snapshotLessonIds))
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->values()
                ->all();

            if ($lessonIds === []) {
                throw ValidationException::withMessages([
                    'version' => 'Không thể khôi phục vì bài học của phiên bản này không còn tồn tại.',
                ]);
            }

            $keyInfo = array_values((array) ($snapshot['key_info'] ?? []));

            $question->forceFill([
                'stem' => (string) ($snapshot['stem'] ?? ''),
                'stem_image_path' => $snapshot['stem_image_path'] ?? null,
                'explanation' => $snapshot['explanation'] ?? null,
                'key_info' => $keyInfo,
                'attending_tip' => $snapshot['attending_tip'] ?? null,
                'difficulty' => (string) ($snapshot['difficulty'] ?? 'medium'),
                'status' => QuestionStatus::Draft,
                'is_free' => (bool) ($snapshot['is_free'] ?? false),
                'exam_flag' => (bool) ($snapshot['exam_flag'] ?? false),
                'updated_by' => $actor->getKey(),
                // Keep questions.version / published_version unchanged — bump only on publish.
            ])->save();

            $question->lessons()->sync($lessonIds);

            $tagIds = collect((array) ($snapshot['tag_ids'] ?? []))
                ->map(fn ($id): int => (int) $id)
                ->filter(fn (int $id): bool => $id > 0)
                ->unique()
                ->values()
                ->all();
            $question->tags()->sync($tagIds);

            $question->options()->delete();
            foreach (array_values((array) ($snapshot['options'] ?? [])) as $index => $option) {
                if (! is_array($option)) {
                    continue;
                }

                $question->options()->create([
                    'label' => (string) ($option['label'] ?? chr(65 + $index)),
                    'content' => (string) ($option['content'] ?? ''),
                    'is_correct' => (bool) ($option['is_correct'] ?? false),
                    'explanation' => $option['explanation'] ?? null,
                    'order' => (int) ($option['order'] ?? $index + 1),
                ]);
            }

            $this->syncHintsFromKeyInfo($question, $keyInfo);

            if (in_array($fromStatus, [
                QuestionStatus::InReview,
                QuestionStatus::InFlagReview,
                QuestionStatus::PendingPublish,
                QuestionStatus::Rejected,
            ], true)) {
                $question->reviewRequests()
                    ->where('status', QuestionReviewStatus::Pending->value)
                    ->where('action', QuestionReviewAction::Create->value)
                    ->delete();
                $this->reviewCycle->clearSlots($question);
            }

            Auditor::record(
                AuditAction::QuestionVersionRestored,
                $actor,
                $question,
                $before,
                AuditSnapshot::question($question->fresh(['options', 'lessons', 'tags'])),
                metadata: [
                    'restored_from_version' => (int) $version->version,
                    'working_copy_only' => true,
                ],
            );

            return $question->refresh();
        });
    }

    /**
     * @param  list<mixed>  $keyInfo
     */
    private function syncHintsFromKeyInfo(Question $question, array $keyInfo): void
    {
        $question->hints()->delete();

        $sort = 0;
        foreach ($keyInfo as $content) {
            $text = trim(is_scalar($content) ? (string) $content : '');
            if ($text === '') {
                continue;
            }
            $question->hints()->create([
                'content' => $text,
                'sort_order' => $sort++,
            ]);
        }
    }
}
