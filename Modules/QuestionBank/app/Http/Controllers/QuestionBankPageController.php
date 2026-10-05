<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Enums\Entitlement;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\QuestionBank\Enums\Difficulty;
use Modules\QuestionBank\Enums\SessionMode;
use Modules\QuestionBank\Enums\SessionStatus;
use Modules\QuestionBank\Models\QuestionAttempt;
use Modules\QuestionBank\Models\QuestionSession;
use Modules\QuestionBank\Models\Lesson;
use Modules\Search\Actions\SearchScopeAction;
use Modules\Search\Data\ScopedSearchResult;
use Modules\Search\Data\SearchQueryData;
use Modules\Search\Support\SearchText;

/** Student Q-Bank landing and owner-scoped session history. */
final class QuestionBankPageController extends Controller
{
    public function __invoke(Request $request, SearchScopeAction $search): View
    {
        $rawQuery = $request->query('q');
        $query = is_string($rawQuery) ? SearchText::normalize($rawQuery) : '';

        if ($query !== '') {
            return $this->searchResults($request, $search, $query);
        }

        $userId = (int) $request->user()->getKey();
        $modes = $this->modeFilters($request);
        $statuses = $this->statusFilters($request);

        $aggregate = QuestionSession::query()
            ->where('user_id', $userId)
            ->selectRaw('COUNT(*) as total_sessions')
            ->selectRaw(
                'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed_sessions',
                [SessionStatus::Completed->value],
            )
            ->selectRaw('COALESCE(SUM(answered_count), 0) as answer_attempts')
            ->selectRaw('COALESCE(SUM(correct_count), 0) as correct_answers')
            ->first();

        $answerAttempts = (int) ($aggregate?->getAttribute('answer_attempts') ?? 0);
        $correctAnswers = (int) ($aggregate?->getAttribute('correct_answers') ?? 0);
        $answeredQuestions = (int) QuestionAttempt::query()
            ->where('user_id', $userId)
            ->whereNotNull('answered_at')
            ->whereIn(
                'session_id',
                QuestionSession::query()->where('user_id', $userId)->select('id'),
            )
            ->distinct()
            ->count('question_id');

        $history = QuestionSession::query()
            ->where('user_id', $userId)
            ->with(['attempts:id,session_id,question_id,is_correct,used_hint'])
            ->when($modes !== [], fn ($query) => $query->whereIn('mode', $modes))
            ->when($statuses !== [], fn ($query) => $query->whereIn('status', $statuses))
            ->latest('created_at')
            ->paginate(10)
            ->withQueryString();

        return view('questionbank::index', [
            'sessions' => $history,
            'stats' => [
                'total_sessions' => (int) ($aggregate?->getAttribute('total_sessions') ?? 0),
                'completed_sessions' => (int) ($aggregate?->getAttribute('completed_sessions') ?? 0),
                'accuracy' => $answerAttempts > 0
                    ? round($correctAnswers / $answerAttempts * 100, 1)
                    : 0.0,
                'answered_questions' => $answeredQuestions,
            ],
            'filters' => [
                'mode' => $modes,
                'status' => $statuses,
            ],
            'modeOptions' => collect(SessionMode::cases())
                ->filter(fn ($m) => $m !== SessionMode::Exam || $request->user()->can('exam.take'))
                ->values()
                ->all(),
            'statusOptions' => SessionStatus::cases(),
        ]);
    }

    private function searchResults(Request $request, SearchScopeAction $search, string $query): View
    {
        $filterInput = $request->query('filter', []);
        $filterInput = is_array($filterInput) ? $filterInput : [];
        $filters = [];

        $difficulty = isset($filterInput['difficulty']) && is_string($filterInput['difficulty'])
            ? Difficulty::tryFrom($filterInput['difficulty'])
            : null;
        if ($difficulty !== null) {
            $filters['difficulty'] = $difficulty->value;
        }

        $lessonId = filter_var($filterInput['lesson_id'] ?? $filterInput['topic_id'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        if ($lessonId !== false && Lesson::query()->whereKey($lessonId)->exists()) {
            $filters['lesson_id'] = (int) $lessonId;
        }

        if (array_key_exists('is_free', $filterInput)) {
            $freeValue = filter_var($filterInput['is_free'], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
            if ($freeValue !== null) {
                $filters['is_free'] = $freeValue;
            }
        }

        if (! $request->user()->hasEntitlement(Entitlement::QbankFull->value)) {
            $filters['is_free'] = true;
        }

        $page = max(1, (int) $request->query('page', 1));
        $searchError = null;

        if (mb_strlen($query) < 2) {
            $searchError = 'Nhập ít nhất 2 ký tự để tìm kiếm.';
            $result = new ScopedSearchResult(
                paginator: new LengthAwarePaginator([], 0, 20, $page, [
                    'path' => route('qbank.index'),
                    'pageName' => 'page',
                ]),
                facets: ['difficulty' => [], 'lesson_id' => [], 'is_free' => []],
                degraded: false,
                engine: 'none',
            );
        } else {
            $result = $search->handle(new SearchQueryData(
                scope: 'qbank',
                query: $query,
                filters: $filters,
                page: $page,
                perPage: 20,
            ), $request->user());
        }

        $result->paginator->appends($request->query());
        $lessonIds = collect($result->facets['lesson_id'] ?? [])->pluck('value');
        if (isset($filters['lesson_id'])) {
            $lessonIds->push($filters['lesson_id']);
        }

        return view('questionbank::index', [
            'searchResult' => $result,
            'searchItems' => $result->items(),
            'searchQuery' => $query,
            'searchFilters' => $filters,
            'searchError' => $searchError,
            'canBrowsePremium' => $request->user()->hasEntitlement(Entitlement::QbankFull->value),
            'searchTopics' => Lesson::query()
                ->whereIn('id', $lessonIds->unique()->filter()->values())
                ->orderBy('name')
                ->get()
                ->keyBy('id'),
        ]);
    }

    /** @return list<string> */
    private function modeFilters(Request $request): array
    {
        $values = $request->query('mode', []);
        $values = is_array($values) ? $values : [$values];

        return collect($values)
            ->filter(static fn (mixed $value): bool => is_string($value))
            ->map(static fn (string $value): ?SessionMode => SessionMode::tryFrom($value))
            ->filter()
            ->map(static fn (SessionMode $mode): string => $mode->value)
            ->unique()
            ->values()
            ->all();
    }

    /** @return list<string> */
    private function statusFilters(Request $request): array
    {
        $values = $request->query('status', []);
        $values = is_array($values) ? $values : [$values];

        return collect($values)
            ->filter(static fn (mixed $value): bool => is_string($value))
            ->map(static fn (string $value): ?SessionStatus => SessionStatus::tryFrom($value))
            ->filter()
            ->map(static fn (SessionStatus $status): string => $status->value)
            ->unique()
            ->values()
            ->all();
    }
}
