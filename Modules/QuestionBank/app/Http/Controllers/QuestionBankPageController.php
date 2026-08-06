<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Modules\QuestionBank\Enums\SessionMode;
use Modules\QuestionBank\Enums\SessionStatus;
use Modules\QuestionBank\Models\QuestionSession;

/** Student Q-Bank landing and owner-scoped session history. */
final class QuestionBankPageController extends Controller
{
    public function __invoke(Request $request): View
    {
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
