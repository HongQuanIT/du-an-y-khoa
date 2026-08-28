<?php

declare(strict_types=1);

namespace Modules\Search\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Classroom\Enums\ClassroomStatus;
use Modules\Classroom\Enums\ClassroomVisibility;
use Modules\Classroom\Models\Classroom;
use Modules\Exam\Enums\ExamStatus;
use Modules\Exam\Models\Exam;
use Modules\Search\Data\GlobalSearchQueryData;
use Modules\Search\Data\GlobalSearchResult;
use Modules\Search\Models\SearchDocument;
use Modules\Search\Support\SearchText;
use Throwable;

final class GlobalSearchService
{
    private const HIGHLIGHT_START = '__MEDLEARN_SEARCH_HIGHLIGHT_START__';

    private const HIGHLIGHT_END = '__MEDLEARN_SEARCH_HIGHLIGHT_END__';

    /** @var list<string> */
    private const FACETS = ['scope', 'type', 'is_free'];

    /** @var list<string> */
    private const SEARCHABLE_SCOPES = ['library', 'classroom', 'exam'];

    public function syncExamsAndClassrooms(): void
    {
        $now = now();

        $publishedExamIds = Exam::query()
            ->where('status', ExamStatus::Published)
            ->get()
            ->each(function (Exam $exam) use ($now): void {
                $title = SearchText::normalize(SearchText::plain((string) $exam->title), 255);
                $description = SearchText::plain((string) ($exam->description ?? ''));

                SearchDocument::syncSource(
                    Exam::class,
                    $exam->getKey(),
                    [
                        'scope' => 'exam',
                        'type' => 'exam',
                        'title' => $title,
                        'summary' => $description,
                        'body' => trim($title.' '.$description),
                        'url' => route('exam.index'),
                        'is_free' => false,
                        'is_published' => true,
                        'published_at' => $exam->updated_at ?? $now,
                    ],
                );
            })
            ->pluck('id')
            ->all();

        SearchDocument::query()
            ->where('source_type', Exam::class)
            ->whereNotIn('source_id', $publishedExamIds)
            ->delete();

        $activeClassroomIds = Classroom::query()
            ->where('status', ClassroomStatus::Active)
            ->where('visibility', ClassroomVisibility::Public)
            ->get()
            ->each(function (Classroom $classroom) use ($now): void {
                $title = SearchText::normalize(SearchText::plain((string) $classroom->title), 255);
                $description = SearchText::plain((string) ($classroom->description ?? ''));

                SearchDocument::syncSource(
                    Classroom::class,
                    $classroom->getKey(),
                    [
                        'scope' => 'classroom',
                        'type' => 'classroom',
                        'title' => $title,
                        'summary' => $description,
                        'body' => trim($title.' '.$description),
                        'url' => route('classroom.show', $classroom),
                        'is_free' => true,
                        'is_published' => true,
                        'published_at' => $classroom->updated_at ?? $now,
                    ],
                );
            })
            ->pluck('id')
            ->all();

        SearchDocument::query()
            ->where('source_type', Classroom::class)
            ->whereNotIn('source_id', $activeClassroomIds)
            ->delete();
    }

    public function search(GlobalSearchQueryData $data): GlobalSearchResult
    {
        if (config('scout.driver') !== 'meilisearch') {
            return $this->databaseSearch($data);
        }

        try {
            $raw = $this->meilisearchRaw($data, withFacets: true);
            $items = $this->safeItemsFromHits($raw['hits'] ?? [], $data->query);
            $total = (int) ($raw['totalHits'] ?? $raw['estimatedTotalHits'] ?? count($items));

            if ($items === []) {
                return $this->databaseSearch($data);
            }

            return new GlobalSearchResult(
                paginator: $this->arrayPaginator($items, $total, $data),
                facets: $this->normaliseFacets((array) ($raw['facetDistribution'] ?? [])),
                degraded: false,
                engine: 'meilisearch',
            );
        } catch (Throwable $exception) {
            Log::warning('Global search fell back to the database.', [
                'exception' => $exception::class,
            ]);

            return $this->databaseSearch($data);
        }
    }

    /**
     * @return array<int, array{id: string, text: string, type: string, scope: string, highlight: string, url: string|null}>
     */
    public function suggest(string $query, int $limit = 5): array
    {
        $query = SearchText::normalize($query, 100);

        if ($query === '') {
            return $this->popularSuggestions($limit);
        }

        if (config('scout.driver') === 'meilisearch') {
            try {
                $raw = $this->meilisearchRaw(new GlobalSearchQueryData($query, 1, $limit), withFacets: false);
                $items = $this->suggestionsFromHits($raw['hits'] ?? [], $query, $limit);

                if ($items !== []) {
                    return $items;
                }
            } catch (Throwable $exception) {
                Log::warning('Global search suggestions fell back to the database.', [
                    'exception' => $exception::class,
                ]);
            }
        }

        return $this->matchedDatabaseQuery($query, null)
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get()
            ->map(fn (SearchDocument $document): array => $this->presentSuggestion($document, $query))
            ->all();
    }

    private function databaseSearch(GlobalSearchQueryData $data): GlobalSearchResult
    {
        $matched = $this->matchedDatabaseQuery($data->query, $data->type);
        $facets = $this->databaseFacets($matched);
        $paginator = $matched
            ->orderByDesc('published_at')
            ->paginate($data->perPage, ['*'], 'page', $data->page);

        $paginator->setCollection($paginator->getCollection()->map(function (SearchDocument $document) use ($data): array {
            return $this->presentDocument($document, $data->query);
        }));

        return new GlobalSearchResult(
            paginator: $paginator,
            facets: $facets,
            degraded: true,
            engine: 'database',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function meilisearchRaw(GlobalSearchQueryData $data, bool $withFacets): array
    {
        $options = [
            'page' => $data->page,
            'hitsPerPage' => $data->perPage,
            'attributesToRetrieve' => ['id', 'scope', 'type', 'title', 'summary', 'body', 'url', 'is_free'],
            'attributesToHighlight' => ['title', 'summary', 'body'],
            'attributesToCrop' => ['summary:120', 'body:120'],
            'cropMarker' => '…',
            'highlightPreTag' => self::HIGHLIGHT_START,
            'highlightPostTag' => self::HIGHLIGHT_END,
        ];

        if ($withFacets) {
            $options['facets'] = self::FACETS;
        }

        $search = SearchDocument::search($data->query)->options($options);
        $search->whereIn('scope', self::SEARCHABLE_SCOPES);

        if ($data->type !== null && $data->type !== '') {
            $search->where('type', $data->type);
        }

        /** @var array<string, mixed> $raw */
        $raw = $search->raw();

        return $raw;
    }

    /**
     * @param  array<int, array<string, mixed>>  $hits
     * @return array<int, array<string, mixed>>
     */
    private function safeItemsFromHits(array $hits, string $query): array
    {
        $ids = collect($hits)
            ->pluck('id')
            ->filter(fn (mixed $id): bool => is_string($id) && $id !== '')
            ->values()
            ->all();

        if ($ids === []) {
            return [];
        }

        $documents = SearchDocument::query()
            ->whereIn('id', $ids)
            ->where('is_published', true)
            ->whereIn('scope', self::SEARCHABLE_SCOPES)
            ->orderByDesc('published_at')
            ->get()
            ->keyBy(fn (SearchDocument $document): string => (string) $document->getKey());

        $items = [];

        foreach ($hits as $hit) {
            $id = isset($hit['id']) ? (string) $hit['id'] : '';
            $document = $documents->get($id);
            if ($document === null) {
                continue;
            }

            $items[] = $this->presentDocument(
                $document,
                $query,
                $hit['_formatted'] ?? [],
            );
        }

        return $items;
    }

    private function presentDocument(SearchDocument $document, string $query, array $formatted = []): array
    {
        $title = $document->title;
        $highlightSource = (string) ($formatted['title'] ?? $formatted['summary'] ?? $formatted['body'] ?? '');

        return [
            'id' => (string) $document->getKey(),
            'type' => $document->type,
            'scope' => $document->scope,
            'title' => $title,
            'highlight' => $highlightSource !== ''
                ? $this->safeMeiliHighlight($highlightSource)
                : $this->fallbackHighlight($document->summary ?: $title, $query),
            'url' => $document->url,
            'attributes' => [
                'is_free' => (bool) $document->is_free,
                'source_type' => $document->source_type,
            ],
        ];
    }

    private function presentSuggestion(SearchDocument $document, string $query): array
    {
        return [
            'id' => (string) $document->getKey(),
            'text' => $document->title,
            'type' => $document->type,
            'scope' => $document->scope,
            'highlight' => $this->fallbackHighlight($document->title, $query),
            'url' => $document->url,
        ];
    }

    /**
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
                ->filter(fn (SearchDocument $row): bool => $row->getAttribute($facet) !== null)
                ->map(fn (SearchDocument $row): array => [
                    'value' => match ($facet) {
                        'is_free' => (bool) $row->getAttribute($facet),
                        default => (string) $row->getAttribute($facet),
                    },
                    'count' => (int) $row->getAttribute('aggregate'),
                ])
                ->values()
                ->all();
        }

        return $facets;
    }

    private function matchedDatabaseQuery(string $query, ?string $type): Builder
    {
        $terms = $this->fallbackTerms($query);

        return SearchDocument::query()
            ->where('is_published', true)
            ->whereIn('scope', self::SEARCHABLE_SCOPES)
            ->when($type !== null && $type !== '', fn (Builder $builder) => $builder->where('type', $type))
            ->where(function (Builder $builder) use ($terms): void {
                foreach ($terms as $index => $term) {
                    $sql = "(title LIKE ? ESCAPE '!' OR summary LIKE ? ESCAPE '!' OR body LIKE ? ESCAPE '!')";
                    $bindings = [
                        SearchText::likePattern($term),
                        SearchText::likePattern($term),
                        SearchText::likePattern($term),
                    ];

                    if ($index === 0) {
                        $builder->whereRaw($sql, $bindings);
                    } else {
                        $builder->orWhereRaw($sql, $bindings);
                    }
                }
            });
    }

    /** @return list<string> */
    private function fallbackTerms(string $query): array
    {
        $terms = [$query];
        $normalisedQuery = mb_strtolower($query);
        $synonyms = config('scout.meilisearch.index-settings.'.SearchDocument::class.'.synonyms', []);

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
                    'value' => $facet === 'is_free'
                        ? filter_var($value, FILTER_VALIDATE_BOOL)
                        : (string) $value,
                    'count' => (int) $count,
                ];
            }
        }

        return $facets;
    }

    /** @return array<int, array{id: string, text: string, type: string, scope: string, highlight: string, url: string|null}> */
    private function popularSuggestions(int $limit): array
    {
        $popular = DB::table('search_histories')
            ->select('query')
            ->selectRaw('COUNT(*) as aggregate')
            ->whereNotNull('query')
            ->where('query', '!=', '')
            ->groupBy('query')
            ->orderByDesc('aggregate')
            ->orderByDesc('query')
            ->limit($limit)
            ->pluck('query')
            ->all();

        if ($popular === []) {
            $popular = SearchDocument::query()
                ->where('is_published', true)
                ->whereIn('scope', self::SEARCHABLE_SCOPES)
                ->latest('published_at')
                ->limit($limit)
                ->pluck('title')
                ->all();
        }

        return array_map(function (string $query): array {
            return [
                'id' => sha1($query),
                'text' => $query,
                'type' => 'keyword',
                'scope' => 'global',
                'highlight' => htmlspecialchars($query, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                'url' => route('search.index', ['q' => $query]),
            ];
        }, array_slice($popular, 0, $limit));
    }

    /**
     * @param  array<int, array<string, mixed>>  $hits
     * @return array<int, array{id: string, text: string, type: string, scope: string, highlight: string, url: string|null}>
     */
    private function suggestionsFromHits(array $hits, string $query, int $limit): array
    {
        $ids = collect($hits)
            ->pluck('id')
            ->filter(fn (mixed $id): bool => is_string($id) && $id !== '')
            ->values()
            ->all();

        if ($ids === []) {
            return [];
        }

        $documents = SearchDocument::query()
            ->whereIn('id', $ids)
            ->where('is_published', true)
            ->whereIn('scope', self::SEARCHABLE_SCOPES)
            ->limit($limit)
            ->get()
            ->keyBy(fn (SearchDocument $document): string => (string) $document->getKey());

        $items = [];

        foreach ($hits as $hit) {
            if (count($items) >= $limit) {
                break;
            }

            $id = isset($hit['id']) ? (string) $hit['id'] : '';
            $document = $documents->get($id);
            if ($document === null) {
                continue;
            }

            $items[] = $this->presentSuggestion($document, $query);
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $hit
     * @return array<string, mixed>
     */
    private function safeMeiliHighlight(string $formatted): string
    {
        $html = str_replace(
            [self::HIGHLIGHT_START, self::HIGHLIGHT_END],
            ['<mark>', '</mark>'],
            htmlspecialchars($formatted, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        );

        return $html;
    }

    private function fallbackHighlight(string $text, string $query): string
    {
        return SearchText::highlight($text, $query);
    }

    private function arrayPaginator(array $items, int $total, GlobalSearchQueryData $data): LengthAwarePaginator
    {
        return new LengthAwarePaginator($items, $total, $data->perPage, $data->page, [
            'path' => route('search.index'),
            'pageName' => 'page',
        ]);
    }
}
