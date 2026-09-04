<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Jobs;

use App\Support\Queue\Concerns\HasQueueDisplayName;
use App\Support\Queue\QueueName;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Modules\QuestionBank\Actions\FindSimilarQuestionsAction;
use Modules\QuestionBank\Models\Question;

final class RefreshQuestionSimilarityJob implements ShouldBeUnique, ShouldQueue
{
    use HasQueueDisplayName;
    use Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    public int $uniqueFor = 120;

    public function __construct(
        public string $questionId,
    ) {
        $this->onQueue(QueueName::Default->value);
    }

    public function displayName(): string
    {
        return 'question-bank:refresh-similarity:'.$this->questionId;
    }

    public function uniqueId(): string
    {
        return 'question-bank:refresh-similarity:'.$this->questionId;
    }

    public function handle(FindSimilarQuestionsAction $action): void
    {
        $question = Question::query()->with('options')->find($this->questionId);
        if ($question === null) {
            return;
        }

        $action->refreshFor($question);
    }

    /** @return array<int, string> */
    public function tags(): array
    {
        return $this->featureTags('question-bank', 'duplicates', 'refresh', 'question:'.$this->questionId);
    }
}
