<?php

declare(strict_types=1);

namespace Modules\Admin\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use App\Support\Html\SafeHtml;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Admin\Support\Auditor;
use Modules\QuestionBank\Enums\Difficulty;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionOption;

/**
 * Create or update a question + options (admin editor).
 */
final class SaveAdminQuestionAction
{
    use AsAction;

    /**
     * @param  array{
     *     stem: string,
     *     stem_image_path: ?string,
     *     explanation: ?string,
     *     key_info: array<int, string>,
     *     attending_tip: ?string,
     *     difficulty: string,
     *     topic_id: int,
     *     is_free: bool,
     *     options: list<array{id?: int|null, content: string, is_correct: bool, explanation?: ?string}>
     * }  $data
     */
    public function handle(User $actor, ?Question $question, array $data): Question
    {
        $options = $data['options'];
        $this->assertOptionsValid($options);

        return DB::transaction(function () use ($actor, $question, $data, $options): Question {
            $before = $question ? $this->snapshot($question) : null;

            if ($question === null) {
                $question = new Question;
                $question->status = QuestionStatus::Draft;
            }

            $question->fill([
                'stem' => SafeHtml::fromEditor($data['stem']),
                'stem_image_path' => $this->sanitizeStemImagePath($data['stem_image_path'] ?? null),
                'explanation' => SafeHtml::fromEditor($data['explanation'] ?? null) ?: null,
                'key_info' => $this->sanitizeKeyInfo($data['key_info'] ?? []),
                'attending_tip' => SafeHtml::fromEditor($data['attending_tip'] ?? null) ?: null,
                'difficulty' => Difficulty::from($data['difficulty']),
                'topic_id' => $data['topic_id'],
                'is_free' => $data['is_free'],
            ]);
            $question->version = ($question->version ?: 0) + 1;
            $question->save();

            if (SafeHtml::isBlank($question->stem)) {
                throw ValidationException::withMessages([
                    'stem' => 'Vui lòng nhập nội dung câu hỏi.',
                ]);
            }
            $this->syncOptions($question, $options);

            $question->load('options', 'topic');

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
        $question->loadMissing('options');

        return [
            'id' => $question->id,
            'stem' => mb_substr(SafeHtml::plainText($question->stem), 0, 200),
            'status' => $question->status->value,
            'difficulty' => $question->difficulty->value,
            'topic_id' => $question->topic_id,
            'is_free' => $question->is_free,
            'version' => $question->version,
            'options_count' => $question->options->count(),
            'correct_option_ids' => $question->options->where('is_correct', true)->pluck('id')->all(),
        ];
    }
}
