<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Actions;

use App\Support\Concerns\AsAction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\QuestionBank\Data\ListQuestionsData;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Repositories\QuestionRepository;

/**
 * Use case: list published questions.
 *
 * When a free-text query is present we go through Meilisearch (fast, faceted);
 * otherwise we hit the indexed DB path directly.
 */
final class ListQuestionsAction
{
    use AsAction;

    public function __construct(private readonly QuestionRepository $questions) {}

    /**
     * @return LengthAwarePaginator<int, Question>
     */
    public function handle(ListQuestionsData $data): LengthAwarePaginator
    {
        if ($data->query !== null && $data->query !== '') {
            return Question::search($data->query)
                ->when($data->difficulty, fn ($search) => $search->where('difficulty', $data->difficulty))
                ->when($data->topicId, fn ($search) => $search->where('topic_id', $data->topicId))
                ->paginate($data->perPage);
        }

        return $this->questions->paginatePublished($data);
    }
}
