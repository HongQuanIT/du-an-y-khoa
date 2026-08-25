<?php

declare(strict_types=1);

namespace Modules\Admin\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Admin\Enums\AuditAction;
use Modules\Admin\Support\Auditor;
use Modules\Admin\Support\AuditSnapshot;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Models\MedicalTaxonomyNode;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionVersion;

final class RestoreQuestionVersionAction
{
    public function __construct(
        private readonly CaptureQuestionVersionAction $captureVersion,
    ) {}

    public function handle(User $actor, Question $question, QuestionVersion $version): Question
    {
        abort_unless((string) $version->question_id === (string) $question->getKey(), 404);

        return DB::transaction(function () use ($actor, $question, $version): Question {
            $question = Question::query()->lockForUpdate()->findOrFail($question->getKey());
            $before = AuditSnapshot::question($question);

            if ((int) $version->version === (int) $question->version) {
                throw ValidationException::withMessages([
                    'version' => 'Đây đang là phiên bản hiện tại.',
                ]);
            }

            $snapshot = $version->snapshot;
            $medicalNodeIds = MedicalTaxonomyNode::query()
                ->whereIn('id', array_map('intval', (array) ($snapshot['medical_taxonomy_node_ids'] ?? [])))
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->values()
                ->all();

            if ($medicalNodeIds === []) {
                throw ValidationException::withMessages([
                    'version' => 'Không thể khôi phục vì danh mục y khoa của phiên bản này không còn tồn tại.',
                ]);
            }

            $beforeVersion = (int) $question->version;
            $question->forceFill([
                'stem' => (string) ($snapshot['stem'] ?? ''),
                'stem_image_path' => $snapshot['stem_image_path'] ?? null,
                'explanation' => $snapshot['explanation'] ?? null,
                'key_info' => array_values((array) ($snapshot['key_info'] ?? [])),
                'attending_tip' => $snapshot['attending_tip'] ?? null,
                'difficulty' => (string) ($snapshot['difficulty'] ?? 'medium'),
                'status' => QuestionStatus::Draft,
                'is_free' => (bool) ($snapshot['is_free'] ?? false),
                'version' => $beforeVersion + 1,
            ])->save();

            $question->medicalTaxonomyNodes()->sync($medicalNodeIds);
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

            $question->load([
                'medicalTaxonomyNodes:id',
                'options' => fn ($query) => $query->orderBy('order'),
            ]);
            $this->captureVersion->handle(
                $question,
                $actor,
                'restore',
                (int) $version->version,
            );

            Auditor::record(
                AuditAction::QuestionVersionRestored,
                $actor,
                $question,
                $before,
                AuditSnapshot::question($question),
                metadata: [
                    'restored_from_version' => (int) $version->version,
                ],
            );

            return $question;
        });
    }
}
