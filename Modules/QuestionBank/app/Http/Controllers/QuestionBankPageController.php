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
use Modules\QuestionBank\Models\QuestionSession;
use Modules\QuestionBank\Models\Topic;
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
        $mode = $this->modeFilter($request);
        $status = $this->statusFilter($request);

        $aggregate = QuestionSession::query()
            ->where('user_id', $userId)
            ->selectRaw('COUNT(*) as total_sessions')
            ->selectRaw(
                'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed_sessions',
                [SessionStatus::Completed->value],
            )
            ->selectRaw('COALESCE(SUM(answered_count), 0) as answered_questions')
            ->selectRaw('COALESCE(SUM(correct_count), 0) as correct_answers')
            ->first();

        $answeredQuestions = (int) ($aggregate?->getAttribute('answered_questions') ?? 0);
        $correctAnswers = (int) ($aggregate?->getAttribute('correct_answers') ?? 0);

        $history = QuestionSession::query()
            ->where('user_id', $userId)
            ->with(['attempts:id,session_id,question_id,is_correct,used_hint'])
            ->when($mode !== null, fn ($query) => $query->where('mode', $mode))
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->latest('created_at')
            ->paginate(10)
            ->withQueryString();

        return view('questionbank::index', [
            'sessions' => $history,
            'stats' => [
                'total_sessions' => (int) ($aggregate?->getAttribute('total_sessions') ?? 0),
                'completed_sessions' => (int) ($aggregate?->getAttribute('completed_sessions') ?? 0),
                'accuracy' => $answeredQuestions > 0
                    ? round($correctAnswers / $answeredQuestions * 100, 1)
                    : 0.0,
                'answered_questions' => $answeredQuestions,
            ],
            'filters' => [
                'mode' => $mode?->value,
                'status' => $status?->value,
            ],
            'modeOptions' => SessionMode::cases(),
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

        $topicId = filter_var($filterInput['topic_id'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        if ($topicId !== false && Topic::query()->whereKey($topicId)->exists()) {
            $filters['topic_id'] = (int) $topicId;
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
                facets: ['difficulty' => [], 'topic_id' => [], 'is_free' => []],
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
        $topicIds = collect($result->facets['topic_id'] ?? [])->pluck('value');
        if (isset($filters['topic_id'])) {
            $topicIds->push($filters['topic_id']);
        }

        return view('questionbank::index', [
            'searchResult' => $result,
            'searchItems' => $result->items(),
            'searchQuery' => $query,
            'searchFilters' => $filters,
            'searchError' => $searchError,
            'searchTopics' => Topic::query()
                ->whereIn('id', $topicIds->unique()->filter()->values())
                ->orderBy('name')
                ->get()
                ->keyBy('id'),
        ]);
    }

    private function modeFilter(Request $request): ?SessionMode
    {
        $value = $request->query('mode');

        return is_string($value) ? SessionMode::tryFrom($value) : null;
    }

    private function statusFilter(Request $request): ?SessionStatus
    {
        $value = $request->query('status');

        return is_string($value) ? SessionStatus::tryFrom($value) : null;
    }
}
