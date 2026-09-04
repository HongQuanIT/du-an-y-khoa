<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Repositories;

use App\Support\Repositories\EloquentRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\QuestionBank\Data\ListQuestionsData;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Support\QuestionFilterBuilder;
use Modules\QuestionBank\Support\ServePublishedQuestion;

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
            ]);
        ServePublishedQuestion::scopeAvailable($query);

        if ($data->query) {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $data->query).'%';
            $query->where(function ($builder) use ($pattern): void {
                $builder->where(function ($published) use ($pattern): void {
                    $published->where('status', \Modules\QuestionBank\Enums\QuestionStatus::Published)
                        ->whereRaw("stem LIKE ? ESCAPE '!'", [$pattern]);
                })->orWhere(function ($revision) use ($pattern): void {
                    $revision->where('status', '!=', \Modules\QuestionBank\Enums\QuestionStatus::Published)
                        ->whereNotNull('published_version')
                        ->where(function ($match) use ($pattern): void {
                            // Match working-copy or published snapshot text; overlay serves snapshot.
                            $match->whereRaw("stem LIKE ? ESCAPE '!'", [$pattern])
                                ->orWhereHas(
                                    'versions',
                                    fn ($versions) => $versions
                                        ->whereColumn('question_versions.version', 'questions.published_version')
                                        ->whereRaw("json_extract(snapshot, '$.stem') LIKE ? ESCAPE '!'", [$pattern]),
                                );
                        });
                });
            });
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
            $freeOnly = $data->freeOnly;
            $query->where(function ($builder) use ($freeOnly): void {
                $builder->where(function ($published) use ($freeOnly): void {
                    $published->where('status', \Modules\QuestionBank\Enums\QuestionStatus::Published)
                        ->where('is_free', $freeOnly);
                })->orWhere(function ($revision) use ($freeOnly): void {
                    $revision->where('status', '!=', \Modules\QuestionBank\Enums\QuestionStatus::Published)
                        ->whereNotNull('published_version')
                        ->where(function ($gate) use ($freeOnly): void {
                            $gate->whereHas(
                                'versions',
                                fn ($versions) => $versions
                                    ->whereColumn('question_versions.version', 'questions.published_version')
                                    ->whereRaw(
                                        'CAST(json_extract(snapshot, \'$.is_free\') AS INTEGER) = ?',
                                        [$freeOnly ? 1 : 0],
                                    ),
                            )->orWhere(function ($fallback) use ($freeOnly): void {
                                // Fallback when snapshot missing is_free key.
                                $fallback->where('is_free', $freeOnly)
                                    ->whereDoesntHave(
                                        'versions',
                                        fn ($versions) => $versions->whereColumn(
                                            'question_versions.version',
                                            'questions.published_version',
                                        ),
                                    );
                            });
                        });
                });
            });
        }

        $paginator = $query->orderByDesc('created_at')->paginate($data->perPage);
        ServePublishedQuestion::overlayMany($paginator->getCollection());

        return $paginator;
    }
}
