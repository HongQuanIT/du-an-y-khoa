<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Repositories;

use App\Support\Repositories\EloquentRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\QuestionBank\Data\ListQuestionsData;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Models\Question;

/**
 * @extends EloquentRepository<Question>
 */
final class QuestionRepository extends EloquentRepository
{
    protected function model(): string
    {
        return Question::class;
    }

    /**
     * Paginated, published questions matching the given filters.
     *
     * @return LengthAwarePaginator<int, Question>
     */
    public function paginatePublished(ListQuestionsData $data): LengthAwarePaginator
    {
        return $this->query()
            ->where('status', QuestionStatus::Published)
            ->when($data->query, function ($query, string $search): void {
                $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
                $query->whereRaw("stem LIKE ? ESCAPE '!'", [$pattern]);
            })
            ->when($data->difficulty, fn ($q, $difficulty) => $q->where('difficulty', $difficulty))
            ->when($data->topicId, fn ($q, $topicId) => $q->where('topic_id', $topicId))
            ->when(
                $data->freeOnly !== null,
                fn ($q) => $q->where('is_free', $data->freeOnly),
            )
            ->orderByDesc('created_at')
            ->paginate($data->perPage);
    }
}
