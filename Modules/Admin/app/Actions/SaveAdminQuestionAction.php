<?php

declare(strict_types=1);

namespace Modules\Admin\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use App\Support\Html\SafeHtml;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Admin\Support\Auditor;
use Modules\Admin\Support\QuestionAccess;
use Modules\QuestionBank\Enums\Difficulty;
use Modules\QuestionBank\Enums\QuestionReviewAction;
use Modules\QuestionBank\Enums\QuestionReviewStatus;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionOption;
use Modules\QuestionBank\Models\QuestionReviewRequest;

/**
 * Create or update a question + options (admin editor).
 */
final class SaveAdminQuestionAction
{
    use AsAction;

    public function __construct(
        private readonly CaptureQuestionVersionAction $captureVersion,
    ) {}

    /**
     * @param  array{
     *     stem: string,
     *     stem_image_path: ?string,
     *     explanation: ?string,
     *     key_info: array<int, string>,
     *     attending_tip: ?string,
     *     difficulty: string,
     *     topic_ids: list<int>,
     *     is_free: bool,
     *     options: list<array{id?: int|null, content: string, is_correct: bool, explanation?: ?string}>
     * }  $data
     */
    public function handle(User $actor, ?Question $question, array $data): Question
    {
        $options = $data['options'];
        $topicIds = collect($data['topic_ids'])->map(fn ($id): int => (int) $id)->unique()->values()->all();
        $this->assertOptionsValid($options);

        return DB::transaction(function () use ($actor, $question, $data, $options, $topicIds): Question {
            $before = $question ? $this->snapshot($question) : null;
            $isReviewer = QuestionAccess::isReviewer($actor);

            if ($question === null) {
                $question = new Question;
                $question->status = QuestionStatus::Draft;
                $question->created_by = $actor->getKey();
            } else {
                if (! $isReviewer && $question->status === QuestionStatus::Published) {
                    $this->queueUpdate($actor, $question, $data);

                    return $question->fresh(['options', 'topic', 'topics', 'pendingReviewRequest']);
                }

                $this->captureVersion->handle($question, null, 'baseline');
            }

            $question->fill([
                'stem' => SafeHtml::fromEditor($data['stem']),
                'stem_image_path' => $this->sanitizeStemImagePath($data['stem_image_path'] ?? null),
                'explanation' => SafeHtml::fromEditor(
                    $this->generalExplanation($data['explanation'] ?? null, $options),
                ) ?: null,
                'key_info' => $this->resolveKeyInfo($question, $data['key_info'] ?? []),
                'attending_tip' => SafeHtml::fromEditor($data['attending_tip'] ?? null) ?: null,
                'difficulty' => Difficulty::from($data['difficulty']),
                'topic_id' => $topicIds[0],
                'is_free' => $data['is_free'],
            ]);
            $question->version = ($question->version ?: 0) + 1;
            $question->save();
            $question->topics()->sync($topicIds);

            if (SafeHtml::isBlank($question->stem)) {
                throw ValidationException::withMessages([
                    'stem' => 'Vui lòng nhập nội dung câu hỏi.',
                ]);
            }
            $this->syncOptions($question, $options);

            $question->load('options', 'topic', 'topics');
            $this->captureVersion->handle($question, $actor);

            if (! $isReviewer) {
                $this->queueCreationReview($actor, $question);
            }

            Auditor::record(
                $before === null ? 'admin.question.create' : 'admin.question.update',
                $actor,
                $question,
                $before,
                $this->snapshot($question),
            );

            return $question;
        });
    }

    /** @param array<string, mixed> $data */
    private function queueUpdate(User $actor, Question $question, array $data): void
    {
        if ($question->reviewRequests()
            ->where('status', QuestionReviewStatus::Pending->value)
            ->exists()) {
            throw ValidationException::withMessages([
                'review' => 'Câu hỏi đang có một yêu cầu chờ duyệt. Vui lòng chờ admin xử lý trước khi gửi thay đổi mới.',
            ]);
        }

        QuestionReviewRequest::query()->create([
            'question_id' => $question->getKey(),
            'action' => QuestionReviewAction::Update,
            'payload' => $data,
            'status' => QuestionReviewStatus::Pending,
            'requested_by' => $actor->getKey(),
        ]);

        Auditor::record(
            'admin.question.update_requested',
            $actor,
            $question,
            null,
            ['review_action' => QuestionReviewAction::Update->value],
        );
    }

    private function queueCreationReview(User $actor, Question $question): void
    {
        $pending = $question->reviewRequests()
            ->where('status', QuestionReviewStatus::Pending->value)
            ->latest('id')
            ->first();

        if ($pending !== null) {
            if ($pending->action !== QuestionReviewAction::Create) {
                throw ValidationException::withMessages([
                    'review' => 'Câu hỏi đang có một yêu cầu khác chờ duyệt.',
                ]);
            }

            $pending->forceFill(['updated_at' => now()])->save();

            return;
        }

        QuestionReviewRequest::query()->create([
            'question_id' => $question->getKey(),
            'action' => QuestionReviewAction::Create,
            'status' => QuestionReviewStatus::Pending,
            'requested_by' => $actor->getKey(),
        ]);
    }

    private function sanitizeStemImagePath(?string $path): ?string
    {
        $path = trim((string) $path);

        if ($path === '') {
            return null;
        }

        $parsed = parse_url($path, PHP_URL_PATH) ?: $path;

        if (str_starts_with($parsed, '/storage/')) {
            $parsed = substr($parsed, 9); // strlen('/storage/')
        } elseif (str_starts_with($parsed, 'storage/')) {
            $parsed = substr($parsed, 8); // strlen('storage/')
        }

        return $parsed !== '' ? $parsed : null;
    }

    /**
     * Older editor forms only submitted explanations for individual options.
     * Preserve that useful content as the general explanation when needed.
     *
     * @param  list<array{id?: int|null, content: string, is_correct: bool, explanation?: ?string}>  $options
     */
    private function generalExplanation(?string $explanation, array $options): ?string
    {
        if (! SafeHtml::isBlank($explanation)) {
            return $explanation;
        }

        foreach ($options as $option) {
            if (($option['is_correct'] ?? false) && ! SafeHtml::isBlank($option['explanation'] ?? null)) {
                return $option['explanation'];
            }
        }

        return $explanation;
    }

    /**
     * @param  array<int, string>  $keyInfo
     * @return array<int, string>
     */
    private function sanitizeKeyInfo(array $keyInfo): array
    {
        return collect($keyInfo)
            ->map(fn (mixed $item): string => trim(strip_tags((string) $item)))
            ->filter(fn (string $item): bool => $item !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Keep existing key info when the editor no longer posts that field.
     *
     * @param  array<int, string>  $keyInfo
     * @return array<int, string>
     */
    private function resolveKeyInfo(?Question $question, array $keyInfo): array
    {
        $sanitized = $this->sanitizeKeyInfo($keyInfo);

        if ($sanitized !== [] || $question === null) {
            return $sanitized;
        }

        return array_values((array) $question->key_info);
    }

    /**
     * @param  list<array{id?: int|null, content: string, is_correct: bool, explanation?: ?string}>  $options
     */
    private function assertOptionsValid(array $options): void
    {
        if (count($options) < 2) {
            throw ValidationException::withMessages([
                'options' => 'Cần ít nhất 2 đáp án.',
            ]);
        }

        $correct = collect($options)->where('is_correct', true)->count();

        if ($correct !== 1) {
            throw ValidationException::withMessages([
                'options' => 'Phải có đúng 1 đáp án đúng (single best answer).',
            ]);
        }
    }

    /**
     * @param  list<array{id?: int|null, content: string, is_correct: bool, explanation?: ?string}>  $options
     */
    private function syncOptions(Question $question, array $options): void
    {
        $keepIds = [];
        $labels = range('A', 'Z');

        foreach (array_values($options) as $index => $row) {
            $payload = [
                'label' => $labels[$index] ?? (string) ($index + 1),
                'content' => $row['content'],
                'is_correct' => (bool) $row['is_correct'],
                'explanation' => SafeHtml::fromEditor($row['explanation'] ?? null) ?: null,
                'order' => $index + 1,
            ];

            $id = isset($row['id']) ? (int) $row['id'] : null;

            if ($id) {
                $option = QuestionOption::query()
                    ->where('question_id', $question->id)
                    ->whereKey($id)
                    ->first();

                if ($option) {
                    $option->fill($payload)->save();
                    $keepIds[] = $option->id;

                    continue;
                }
            }

            $created = $question->options()->create($payload);
            $keepIds[] = $created->id;
        }

        $question->options()->whereNotIn('id', $keepIds)->delete();
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Question $question): array
    {
        $question->loadMissing('options', 'topics');

        return [
            'id' => $question->id,
            'stem' => mb_substr(SafeHtml::plainText($question->stem), 0, 200),
            'status' => $question->status->value,
            'difficulty' => $question->difficulty->value,
            'topic_id' => $question->topic_id,
            'topic_ids' => $question->topics->pluck('id')->map(fn ($id): int => (int) $id)->values()->all(),
            'is_free' => $question->is_free,
            'version' => $question->version,
            'options_count' => $question->options->count(),
            'correct_option_ids' => $question->options->where('is_correct', true)->pluck('id')->all(),
        ];
    }
}
