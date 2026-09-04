<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Jobs;

use App\Support\Queue\Concerns\HasQueueDisplayName;
use App\Support\Queue\QueueName;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Modules\QuestionBank\Actions\ScanQuestionDuplicatesAction;

final class ScanQuestionDuplicatesJob implements ShouldBeUnique, ShouldQueue
{
    use HasQueueDisplayName;
    use Queueable;

    public int $tries = 2;

    public int $timeout = 600;

    public int $uniqueFor = 600;

    public function __construct()
    {
        $this->onQueue(QueueName::Default->value);
    }

    public function displayName(): string
    {
        return 'question-bank:scan-duplicates';
    }

    public function uniqueId(): string
    {
        return 'question-bank:scan-duplicates';
    }

    public function handle(ScanQuestionDuplicatesAction $action): void
    {
        $action->handle();
    }

    /** @return array<int, string> */
    public function tags(): array
    {
        return $this->featureTags('question-bank', 'duplicates', 'scan');
    }
}
