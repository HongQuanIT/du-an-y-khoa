<?php

declare(strict_types=1);

namespace Modules\Search\Services;

use App\Models\User;
use App\Support\Enums\Entitlement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Support\ServePublishedQuestion;
use Modules\Search\Contracts\ScopedSearchProvider;
use Modules\Search\Data\ScopedSearchResult;
use Modules\Search\Data\SearchQueryData;
use Modules\Search\Support\SearchText;
use Throwable;

final class QbankSearchService implements ScopedSearchProvider
{
    private const HIGHLIGHT_START = '__MEDLEARN_SEARCH_HIGHLIGHT_START__';

    private const HIGHLIGHT_END = '__MEDLEARN_SEARCH_HIGHLIGHT_END__';

    /** @var list<string> */
    private const FACETS = ['difficulty', 'medical_taxonomy_node_id', 'is_free'];

    /** @var list<string> */
    private const INDEX_FACETS = ['difficulty', 'medical_taxonomy_node_ids', 'is_free'];

    public function search(SearchQueryData $data, User $user): ScopedSearchResult
    {
        if (config('scout.driver') !== 'meilisearch') {
            return $this->databaseSearch($data, $user);
        }

        try {
            $filters = $this->effectiveFilters($data, $user);
            $raw = $this->meilisearchRaw($data, $filters, withFacets: true);
            $items = $this->safeItemsFromHits($raw['hits'] ?? [], $filters, $data->query);
            $total = (int) ($raw['totalHits'] ?? $raw['estimatedTotalHits'] ?? count($items));

            if ($items === []) {
                return $this->databaseSearch($data, $user);
            }

            return new ScopedSearchResult(
                paginator: $this->arrayPaginator($items, $total, $data),
                facets: $this->normaliseFacets((array) ($raw['facetDistribution'] ?? [])),
                degraded: false,
                engine: 'meilisearch',
            );
        } catch (Throwable $exception) {
            Log::warning('Contextual search fell back to the database.', [
                'scope' => $data->scope,
                'exception' => $exception::class,
            ]);

            return $this->databaseSearch($data, $user);
        }
    }

    /**
     * @param  array{difficulty?: string, medical_taxonomy_node_id?: int, is_free?: bool}  $filters
     * @return array<string, mixed>
     */
    private function meilisearchRaw(SearchQueryData $data, array $filters, bool $withFacets): array
    {
        $options = [
            'page' => $data->page,
            'hitsPerPage' => $data->perPage,
            'attributesToRetrieve' => ['id', 'stem', 'difficulty', 'medical_taxonomy_node_ids', 'is_free'],
            'attributesToHighlight' => ['stem'],
            'attributesToCrop' => ['stem:45'],
            'cropMarker' => '…',
            'highlightPreTag' => self::HIGHLIGHT_START,
            'highlightPostTag' => self::HIGHLIGHT_END,
        ];

        if ($withFacets) {
            $options['facets'] = self::INDEX_FACETS;
        }

        $search = Question::search($data->query)->options($options);

        foreach ($filters as $field => $value) {
            $search->where($field === 'medical_taxonomy_node_id' ? 'medical_taxonomy_node_ids' : $field, $value);
        }

        /** @var array<string, mixed> $raw */
        $raw = $search->raw();

        return $raw;
    }

    /**
     * Hydrate only current, authorised DB rows. This is deliberately separate
     * from the index filters because queued indexes are eventually consistent.
     *
     * @param  array<int, array<string, mixed>>  $hits
     * @param  array{difficulty?: string, medical_taxonomy_node_id?: int, is_free?: bool}  $filters
     * @return array<int, array<string, mixed>>
     */
    private function safeItemsFromHits(array $hits, array $filters, string $query): array
    {
        $ids = collect($hits)
            ->pluck('id')
            ->filter(fn (mixed $id): bool => is_string($id) && $id !== '')
            ->values()
            ->all();

        if ($ids === []) {
            return [];
        }

        $questions = $this->accessibleQuestionQuery($filters)
            ->whereIn('id', $ids)
            ->get()
            ->keyBy(fn (Question $question): string => (string) $question->getKey());
        ServePublishedQuestion::overlayMany($questions);

        $items = [];

        foreach ($hits as $hit) {
            $id = isset($hit['id']) ? (string) $hit['id'] : '';
            /** @var Question|null $questionModel */
            $questionModel = $questions->get($id);
            if ($questionModel === null) {
                continue;
            }

            $title = $this->plainText((string) $questionModel->stem);
            $formatted = $hit['_formatted']['stem'] ?? null;

            $items[] = $this->presentQuestion(
                $questionModel,
                $title,
                is_string($formatted)
                    ? $this->safeMeiliHighlight($formatted)
                    : $this->fallbackHighlight($title, $query),
            );
        }

        return $items;
    }

    private function databaseSearch(SearchQueryData $data, User $user): ScopedSearchResult
    {
        $filters = $this->effectiveFilters($data, $user);
        $matched = $this->matchedDatabaseQuery($data->query, $filters);
        $facets = $this->databaseFacets($matched);
        $paginator = $matched
            ->orderByDesc('created_at')
            ->paginate($data->perPage, ['*'], 'page', $data->page);

        ServePublishedQuestion::overlayMany($paginator->getCollection());

        $paginator->setCollection($paginator->getCollection()->map(function (Question $question) use ($data): array {
            $title = $this->plainText((string) $question->stem);

            return $this->presentQuestion(
                $question,
                $title,
                $this->fallbackHighlight($title, $data->query),
            );
        }));

        return new ScopedSearchResult(
            paginator: $paginator,
            facets: $facets,
            degraded: true,
            engine: 'database',
        );
    }

    /**
     * @param  array{difficulty?: string, medical_taxonomy_node_id?: int, is_free?: bool}  $filters
     * @return Builder<Question>
     */
    private function accessibleQuestionQuery(array $filters): Builder
    {
        $query = ServePublishedQuestion::scopeAvailable(
            Question::query()->with('medicalTaxonomyNodes:id'),
        );

        return $query
            ->when(
                array_key_exists('difficulty', $filters),
                fn (Builder $builder) => $builder->where('difficulty', $filters['difficulty']),
            )
            ->when(
                array_key_exists('medical_taxonomy_node_id', $filters),
                fn (Builder $builder) => $builder->whereHas(
                    'medicalTaxonomyNodes',
                    fn (Builder $nodes) => $nodes->where('medical_taxonomy_nodes.id', $filters['medical_taxonomy_node_id']),
                ),
            )
            ->when(
                array_key_exists('is_free', $filters),
                function (Builder $builder) use ($filters): void {
                    $freeOnly = (bool) $filters['is_free'];
                    $builder->where(function (Builder $gate) use ($freeOnly): void {
                        $gate->where(function (Builder $published) use ($freeOnly): void {
                            $published->where('status', QuestionStatus::Published)
                                ->where('is_free', $freeOnly);
                        })->orWhere(function (Builder $revision) use ($freeOnly): void {
                            $revision->where('status', '!=', QuestionStatus::Published)
                                ->whereNotNull('published_version')
                                ->whereHas(
                                    'versions',
                                    fn (Builder $versions) => $versions
                                        ->whereColumn('question_versions.version', 'questions.published_version')
                                        ->whereRaw(
                                            'CAST(json_extract(snapshot, \'$.is_free\') AS INTEGER) = ?',
                                            [$freeOnly ? 1 : 0],
                                        ),
                                );
                        });
                    });
                },
            );
    }

    /**
     * @param  array{difficulty?: string, medical_taxonomy_node_id?: int, is_free?: bool}  $filters
     * @return Builder<Question>
     */
    private function matchedDatabaseQuery(string $query, array $filters): Builder
    {
        $terms = $this->fallbackTerms($query);

        return $this->accessibleQuestionQuery($filters)
            ->where(function (Builder $builder) use ($terms): void {
                foreach ($terms as $index => $term) {
                    $pattern = SearchText::likePattern($term);
                    $clause = function (Builder $match) use ($pattern): void {
                        $match->where(function (Builder $published) use ($pattern): void {
                            $published->where('status', QuestionStatus::Published)
                                ->whereRaw("stem LIKE ? ESCAPE '!'", [$pattern]);
                        })->orWhere(function (Builder $revision) use ($pattern): void {
                            $revision->where('status', '!=', QuestionStatus::Published)
                                ->whereNotNull('published_version')
                                ->where(function (Builder $inner) use ($pattern): void {
                                    $inner->whereRaw("stem LIKE ? ESCAPE '!'", [$pattern])
                                        ->orWhereHas(
                                            'versions',
                                            fn (Builder $versions) => $versions
                                                ->whereColumn('question_versions.version', 'questions.published_version')
                                                ->whereRaw("json_extract(snapshot, '$.stem') LIKE ? ESCAPE '!'", [$pattern]),
                                        );
                                });
                        });
                    };

                    if ($index === 0) {
                        $builder->where($clause);
                    } else {
                        $builder->orWhere($clause);
                    }
                }
            });
    }

    /**
     * @param  Builder<Question>  $matched
     * @return array<string, array<int, array{value: mixed, count: int}>>
     */
    private function databaseFacets(Builder $matched): array
    {
        $facets = [];

        foreach (self::FACETS as $facet) {
            if ($facet === 'medical_taxonomy_node_id') {
                $rows = (clone $matched)
                    ->withoutEagerLoads()
                    ->reorder()
                    ->join('question_medical_topics', 'question_medical_topics.question_id', '=', 'questions.id')
                    ->selectRaw('question_medical_topics.medical_taxonomy_node_id as medical_taxonomy_node_id, COUNT(DISTINCT questions.id) as aggregate')
                    ->groupBy('question_medical_topics.medical_taxonomy_node_id')
                    ->orderByDesc('aggregate')
                    ->limit(20)
                    ->get();
            } else {
                $rows = (clone $matched)
                    ->withoutEagerLoads()
                    ->reorder()
                    ->select($facet)
                    ->selectRaw('COUNT(*) as aggregate')
                    ->groupBy($facet)
                    ->orderByDesc('aggregate')
                    ->get();
            }

            $facets[$facet] = $rows
                ->filter(fn (Question $row): bool => $row->getAttribute($facet) !== null)
                ->map(fn (Question $row): array => [
                    'value' => match ($facet) {
                        'medical_taxonomy_node_id' => (int) $row->getAttribute($facet),
                        'is_free' => (bool) $row->getAttribute($facet),
                        default => $row->getAttribute($facet) instanceof \BackedEnum
                            ? $row->getAttribute($facet)->value
                            : (string) $row->getAttribute($facet),
                    },
                    'count' => (int) $row->getAttribute('aggregate'),
                ])
                ->values()
                ->all();
        }

        return $facets;
    }

    /**
     * @return array{difficulty?: string, medical_taxonomy_node_id?: int, is_free?: bool}
     */
    private function effectiveFilters(SearchQueryData $data, User $user): array
    {
        $filters = $data->filters;

        if (! $user->hasEntitlement(Entitlement::QbankFull->value)) {
            $filters['is_free'] = true;
        }

        return $filters;
    }

    /** @return list<string> */
    private function fallbackTerms(string $query): array
    {
        $terms = [$query];
        $normalisedQuery = mb_strtolower($query);
        $synonyms = config('scout.meilisearch.index-settings.'.Question::class.'.synonyms', []);

        foreach ((array) $synonyms as $source => $values) {
            if (str_contains($normalisedQuery, mb_strtolower((string) $source))) {
                foreach ((array) $values as $value) {
                    if (is_string($value) && $value !== '') {
                        $terms[] = $value;
                    }
                }
            }
        }

        return array_values(array_unique($terms));
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, array<int, array{value: mixed, count: int}>>
     */
    private function normaliseFacets(array $raw): array
    {
        $facets = [];

        foreach (self::FACETS as $facet) {
            $indexFacet = $facet === 'medical_taxonomy_node_id' ? 'medical_taxonomy_node_ids' : $facet;
            $values = (array) ($raw[$indexFacet] ?? []);
            $facets[$facet] = [];

            foreach ($values as $value => $count) {
                $facets[$facet][] = [
                    'value' => match ($facet) {
                        'medical_taxonomy_node_id' => (int) $value,
                        'is_free' => filter_var($value, FILTER_VALIDATE_BOOL),
                        default => (string) $value,
                    },
                    'count' => (int) $count,
                ];
            }
        }

        return $facets;
    }

    /** @return array<string, mixed> */
    private function presentQuestion(Question $question, string $title, string $highlight): array
    {
        return [
            'id' => (string) $question->getKey(),
            'type' => 'question',
            'title' => $title,
            'highlight' => $highlight,
            'attributes' => [
                'difficulty' => $question->difficulty->value,
                'medical_taxonomy_node_ids' => $question->medicalTaxonomyNodes
                    ->pluck('id')
                    ->map(fn ($id): int => (int) $id)
                    ->values()
                    ->all(),
                'is_free' => (bool) $question->is_free,
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function arrayPaginator(array $items, int $total, SearchQueryData $data): LengthAwarePaginator
    {
        return new LengthAwarePaginator(
            $items,
            $total,
            $data->perPage,
            $data->page,
            ['path' => LengthAwarePaginator::resolveCurrentPath(), 'pageName' => 'page'],
        );
    }

    private function plainText(string $value): string
    {
        $plain = strip_tags(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return trim(preg_replace('/\s+/u', ' ', $plain) ?? $plain);
    }

    private function safeMeiliHighlight(string $formatted): string
    {
        $escaped = htmlspecialchars($formatted, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');

        return str_replace(
            [self::HIGHLIGHT_START, self::HIGHLIGHT_END],
            ['<mark>', '</mark>'],
            $escaped,
        );
    }

    private function fallbackHighlight(string $title, string $query): string
    {
        $safeTitle = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
        $matchedTerm = collect($this->fallbackTerms($query))
            ->first(fn (string $term): bool => mb_stripos($title, $term) !== false) ?? $query;
        $safeMatchedTerm = htmlspecialchars($matchedTerm, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');

        return preg_replace(
            '/('.preg_quote($safeMatchedTerm, '/').')/iu',
            '<mark>$1</mark>',
            $safeTitle,
        ) ?? $safeTitle;
    }
}
