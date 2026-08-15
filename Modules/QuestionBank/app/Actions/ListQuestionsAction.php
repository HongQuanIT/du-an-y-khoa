<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use App\Support\Enums\Entitlement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Modules\QuestionBank\Data\ListQuestionsData;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Repositories\QuestionRepository;
use Throwable;

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
    public function handle(ListQuestionsData $data, ?User $user = null): LengthAwarePaginator
    {
        if ($user !== null && ! $user->hasEntitlement(Entitlement::QbankFull->value)) {
            $data = new ListQuestionsData(
                query: $data->query,
                difficulty: $data->difficulty,
                topicId: $data->topicId,
                freeOnly: true,
                perPage: $data->perPage,
            );
        }

        if ($data->query !== null && $data->query !== '') {
            if (config('scout.driver') !== 'meilisearch') {
                return $this->questions->paginatePublished($data);
            }

            try {
                $paginator = Question::search($data->query)
                    ->when($data->difficulty, fn ($search) => $search->where('difficulty', $data->difficulty))
                    ->when($data->topicId, fn ($search) => $search->where('topic_id', $data->topicId))
                    ->when(
                        $data->freeOnly !== null,
                        fn ($search) => $search->where('is_free', $data->freeOnly),
                    )
                    // Search-index values are eventually consistent. Re-apply
                    // access boundaries to current DB rows before serialising.
                    ->query(fn (EloquentBuilder $query) => $query
                        ->where('status', QuestionStatus::Published)
                        ->when(
                            $data->freeOnly !== null,
                            fn (EloquentBuilder $query) => $query->where('is_free', $data->freeOnly),
                        ))
                    ->paginate($data->perPage);

                if ($paginator->count() === 0) {
                    return $this->questions->paginatePublished($data);
                }

                return $paginator;
            } catch (Throwable $exception) {
                report($exception);

                return $this->questions->paginatePublished($data);
            }
        }

        return $this->questions->paginatePublished($data);
    }
}
