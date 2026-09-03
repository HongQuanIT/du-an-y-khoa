<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Console;

use Illuminate\Console\Command;
use Modules\QuestionBank\Actions\SyncQuestionStatsAction;
use Modules\QuestionBank\Models\Question;

final class SyncQuestionStatsCommand extends Command
{
    protected $signature = 'question-bank:sync-stats {question? : UUID câu hỏi cụ thể} {--limit=500 : Số câu tối đa khi sync hàng loạt}';

    protected $description = 'Rollup lượt làm và tỷ lệ đúng từ question_attempts vào stats_cache';

    public function handle(SyncQuestionStatsAction $syncStats): int
    {
        $questionId = $this->argument('question');

        if (is_string($questionId) && $questionId !== '') {
            $question = Question::query()->findOrFail($questionId);
            $syncStats->syncForQuestion($question);
            $question->refresh();
            $stats = $question->listStats();

            $this->info(sprintf(
                'Đã cập nhật câu %s: %d lượt làm, %s%% đúng.',
                $question->code ?: $question->getKey(),
                $stats['total_attempts'],
                $stats['correct_rate'] !== null ? number_format($stats['correct_rate'] * 100, 1) : '—',
            ));

            return self::SUCCESS;
        }

        $limit = max(1, (int) $this->option('limit'));
        $updated = $syncStats->syncStaleOrMissing($limit);

        $this->info(sprintf('Đã rollup stats_cache cho %d câu hỏi.', $updated));

        return self::SUCCESS;
    }
}
