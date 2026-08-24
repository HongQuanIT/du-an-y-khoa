<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Repositories;

use App\Support\Repositories\EloquentRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\QuestionBank\Data\ListQuestionsData;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Support\QuestionFilterBuilder;

/**
 * @extends EloquentRepository<Question>
 */
final class QuestionRepository extends EloquentRepository
{
    public function __construct(
        private readonly QuestionFilterBuilder $filters,
    ) {}

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
        $query = $this->query()
            ->with([
                'coreClinicalTopics:id,name',
                'medicalTaxonomyNodes:id,name',
                'tags:id,name',
            ])
            ->where('status', QuestionStatus::Published);

        if ($data->query) {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $data->query).'%';
            $query->whereRaw("stem LIKE ? ESCAPE '!'", [$pattern]);
        }

        $this->filters->apply(
            $query,
            blueprintId: $data->blueprintId,
            blueprintSectionId: $data->blueprintSectionId,
            coreClinicalTopicIds: $data->coreClinicalTopicIds,
            medicalTaxonomyNodeIds: $data->medicalTaxonomyNodeIds,
            tagIds: $data->tagIds,
                        difficulty: $data->difficulty,
        );

        if ($data->freeOnly !== null) {
            $query->where('is_free', $data->freeOnly);
        }

        return $query->orderByDesc('created_at')->paginate($data->perPage);
    }
}
