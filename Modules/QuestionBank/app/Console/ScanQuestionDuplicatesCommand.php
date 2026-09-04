<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Console;

use Illuminate\Console\Command;
use Modules\QuestionBank\Actions\ScanQuestionDuplicatesAction;

final class ScanQuestionDuplicatesCommand extends Command
{
    protected $signature = 'question-bank:scan-duplicates';

    protected $description = 'Scan the question bank for exact and near-duplicate pairs';

    public function handle(ScanQuestionDuplicatesAction $action): int
    {
        $this->info('Scanning question duplicates…');

        $result = $action->handle();

        $this->info(sprintf(
            'Done. Questions: %d · Pairs ≥60%%: %d',
            $result['questions'],
            $result['pairs'],
        ));

        return self::SUCCESS;
    }
}
