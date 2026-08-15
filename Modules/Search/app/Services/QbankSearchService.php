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
    private const FACETS = ['difficulty', 'topic_id', 'is_free'];

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
     * @param  array{difficulty?: string, topic_id?: int, is_free?: bool}  $filters
     * @return array<string, mixed>
     */
    private function meilisearchRaw(SearchQueryData $data, array $filters, bool $withFacets): array
    {
        $options = [
            'page' => $data->page,
            'hitsPerPage' => $data->perPage,
            'attributesToRetrieve' => ['id', 'stem', 'difficulty', 'topic_id', 'is_free'],
            'attributesToHighlight' => ['stem'],
            'attributesToCrop' => ['stem:45'],
            'cropMarker' => '…',
            'highlightPreTag' => self::HIGHLIGHT_START,
            'highlightPostTag' => self::HIGHLIGHT_END,
        ];

        if ($withFacets) {
            $options['facets'] = self::FACETS;
        }

        $search = Question::search($data->query)->options($options);

        foreach ($filters as $field => $value) {
            $search->where($field, $value);
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
     * @param  array{difficulty?: string, topic_id?: int, is_free?: bool}  $filters
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
            ->get(['id', 'stem', 'difficulty', 'topic_id', 'is_free'])
            ->keyBy(fn (Question $question): string => (string) $question->getKey());

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
            ->paginate($data->perPage, ['id', 'stem', 'difficulty', 'topic_id', 'is_free'], 'page', $data->page);

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
     * @param  array{difficulty?: string, topic_id?: int, is_free?: bool}  $filters
     * @return Builder<Question>
     */
    private function accessibleQuestionQuery(array $filters): Builder
    {
        return Question::query()
            ->where('status', QuestionStatus::Published)
            ->when(
                array_key_exists('difficulty', $filters),
                fn (Builder $query) => $query->where('difficulty', $filters['difficulty']),
            )
            ->when(
                array_key_exists('topic_id', $filters),
                fn (Builder $query) => $query->where('topic_id', $filters['topic_id']),
            )
            ->when(
                array_key_exists('is_free', $filters),
                fn (Builder $query) => $query->where('is_free', $filters['is_free']),
            );
    }

    /**
     * @param  array{difficulty?: string, topic_id?: int, is_free?: bool}  $filters
     * @return Builder<Question>
     */
    private function matchedDatabaseQuery(string $query, array $filters): Builder
    {
        $terms = $this->fallbackTerms($query);

        return $this->accessibleQuestionQuery($filters)
            ->where(function (Builder $builder) use ($terms): void {
                foreach ($terms as $index => $term) {
                    $sql = "stem LIKE ? ESCAPE '!'";
                    $bindings = [SearchText::likePattern($term)];

                    if ($index === 0) {
                        $builder->whereRaw($sql, $bindings);
                    } else {
                        $builder->orWhereRaw($sql, $bindings);
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
            $rows = (clone $matched)
                ->reorder()
                ->select($facet)
                ->selectRaw('COUNT(*) as aggregate')
                ->groupBy($facet)
                ->orderByDesc('aggregate')
                ->get();

            $facets[$facet] = $rows
                ->filter(fn (Question $row): bool => $row->getAttribute($facet) !== null)
                ->map(fn (Question $row): array => [
                    'value' => match ($facet) {
                        'topic_id' => (int) $row->getAttribute($facet),
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
     * @return array{difficulty?: string, topic_id?: int, is_free?: bool}
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
            $values = (array) ($raw[$facet] ?? []);
            $facets[$facet] = [];

            foreach ($values as $value => $count) {
                $facets[$facet][] = [
                    'value' => match ($facet) {
                        'topic_id' => (int) $value,
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
                'topic_id' => $question->topic_id,
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
