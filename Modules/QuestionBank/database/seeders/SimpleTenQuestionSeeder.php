<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\QuestionBank\Enums\Difficulty;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Models\Question;

/** Ten minimal published questions for manually testing QBank sessions. */
final class SimpleTenQuestionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (range(1, 10) as $number) {
            $question = Question::withTrashed()->firstOrNew([
                'code' => sprintf('DEMO-SIMPLE-%03d', $number),
            ]);

            if ($question->exists && $question->trashed()) {
                $question->restore();
            }

            $question->fill([
                'stem' => 'Câu '.$number,
                'explanation' => 'Đáp án đúng là đáp án A.',
                'difficulty' => Difficulty::Easy,
                'status' => QuestionStatus::Published,
                'is_free' => true,
                'exam_flag' => false,
            ]);
            $question->version = max(1, (int) $question->version);
            $question->save();

            $options = [
                ['label' => 'A', 'content' => 'Đáp án đúng', 'is_correct' => true, 'order' => 1],
                ['label' => 'B', 'content' => 'Đáp án sai 1', 'is_correct' => false, 'order' => 2],
                ['label' => 'C', 'content' => 'Đáp án sai 2', 'is_correct' => false, 'order' => 3],
                ['label' => 'D', 'content' => 'Đáp án sai 3', 'is_correct' => false, 'order' => 4],
            ];
            $keptOptionIds = [];

            foreach ($options as $optionData) {
                $option = $question->options()->updateOrCreate(
                    ['label' => $optionData['label']],
                    $optionData,
                );
                $keptOptionIds[] = $option->getKey();
            }

            $question->options()->whereNotIn('id', $keptOptionIds)->delete();
        }
    }
}
