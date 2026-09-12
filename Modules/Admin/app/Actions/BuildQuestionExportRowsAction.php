<?php

declare(strict_types=1);

namespace Modules\Admin\Actions;

use App\Support\Concerns\AsAction;
use Illuminate\Support\Collection;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Support\QuestionImportSchema;

final class BuildQuestionExportRowsAction
{
    use AsAction;

    /**
     * @param  Collection<int, Question>  $questions
     * @return array{headers: list<string>, rows: list<list<string>>}
     */
    public function handle(Collection $questions): array
    {
        $headers = array_merge(QuestionImportSchema::headers(), ['id', 'status']);
        $rows = [];

        foreach ($questions as $question) {
            $question->loadMissing(['options', 'lessons', 'tags', 'hints']);
            $options = $question->options->sortBy('order')->values();
            $correct = '';
            $row = array_fill_keys(QuestionImportSchema::headers(), '');

            $row['code'] = (string) $question->code;
            $row['stem'] = trim(strip_tags((string) $question->stem));
            $row['explanation'] = trim(strip_tags((string) $question->explanation));
            $row['difficulty'] = $question->difficulty->value;
            $row['lesson_slugs'] = $question->lessons
                ->map(fn ($lesson): string => (string) ($lesson->slug ?: $lesson->code))
                ->filter()
                ->implode('; ');
            $row['tag_slugs'] = $question->tags->pluck('slug')->filter()->implode('; ');
            $row['is_free'] = $question->is_free ? '1' : '0';
            $row['exam_flag'] = $question->exam_flag ? '1' : '0';
            $row['attending_tip'] = trim(strip_tags((string) $question->attending_tip));
            $row['hints'] = $question->hints->pluck('content')->filter()->implode(' | ');

            foreach ($options->take(QuestionImportSchema::MAX_OPTIONS) as $index => $option) {
                $letter = chr(65 + $index);
                $key = 'option_'.strtolower($letter);
                $row[$key] = trim(strip_tags((string) $option->content));
                $row[$key.'_explanation'] = trim(strip_tags((string) $option->explanation));
                if ($option->is_correct) {
                    $correct = $letter;
                }
            }

            $row['correct'] = $correct;
            $rows[] = array_merge(array_values($row), [
                (string) $question->getKey(),
                $question->status->value,
            ]);
        }

        return ['headers' => $headers, 'rows' => $rows];
    }
}
