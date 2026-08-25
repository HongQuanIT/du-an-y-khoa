<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\QuestionBank\Actions\CreateQuestionSessionAction;
use Modules\QuestionBank\Actions\RepeatQuestionSessionAction;
use Modules\QuestionBank\Data\CreateSessionData;
use Modules\QuestionBank\Enums\SessionMode;
use Modules\QuestionBank\Enums\SessionSource;
use Modules\QuestionBank\Enums\SessionStatus;
use Modules\QuestionBank\Enums\UserQuestionStatus;
use Modules\QuestionBank\Models\MedicalTaxonomyNode;
use Modules\QuestionBank\Models\QuestionSession;
use Modules\QuestionBank\Support\QuestionFilterBuilder;
use RuntimeException;

final class WeakTopicSessionController extends Controller
{
    public function __construct(
        private readonly CreateQuestionSessionAction $createSession,
        private readonly RepeatQuestionSessionAction $repeatSession,
        private readonly QuestionFilterBuilder $filters,
    ) {}

    public function __invoke(
        Request $request,
        MedicalTaxonomyNode $medicalTaxonomyNode,
    ): RedirectResponse {
        $validated = $request->validate([
            'count' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);
        $count = (int) ($validated['count'] ?? 10);

        $examOrigin = $this->latestExamOrigin(
            (int) $request->user()->getKey(),
            (int) $medicalTaxonomyNode->getKey(),
        );

        if ($examOrigin !== null) {
            try {
                $session = $this->repeatSession->handle(
                    $request->user(),
                    $examOrigin['session'],
                    ['incorrect'],
                    $count,
                    $examOrigin['question_ids'],
                );
            } catch (RuntimeException $exception) {
                throw ValidationException::withMessages(['weak_topic' => $exception->getMessage()]);
            }

            return redirect()
                ->route('exam.session', $session)
                ->with('status', 'Đã tạo phiên làm lại các câu sai trong bài thi.');
        }

        try {
            $session = $this->createSession->handle($request->user(), new CreateSessionData(
                mode: SessionMode::Study,
                source: SessionSource::WeakTopics,
                count: $count,
                medicalTaxonomyNodeIds: [(int) $medicalTaxonomyNode->getKey()],
            ));
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages([
                'weak_topic' => $exception->getMessage(),
            ]);
        }

        return redirect()
            ->route('qbank.session', $session)
            ->with('status', 'Đã tạo phiên luyện các câu cần cải thiện trong chủ đề '.$medicalTaxonomyNode->name.'.');
    }

    /** @return array{session: QuestionSession, question_ids: list<string>}|null */
    private function latestExamOrigin(int $userId, int $medicalTaxonomyNodeId): ?array
    {
        $nodeIds = $this->filters->expandMedicalTaxonomyNodes([$medicalTaxonomyNodeId]);
        $rows = DB::table('question_attempts')
            ->join('question_sessions', 'question_sessions.id', '=', 'question_attempts.session_id')
            ->join('question_medical_topics', 'question_medical_topics.question_id', '=', 'question_attempts.question_id')
            ->join('question_status', function ($join): void {
                $join->on('question_status.question_id', '=', 'question_attempts.question_id')
                    ->on('question_status.user_id', '=', 'question_attempts.user_id');
            })
            ->where('question_attempts.user_id', $userId)
            ->where('question_attempts.is_correct', false)
            ->where('question_sessions.mode', SessionMode::Exam->value)
            ->where('question_sessions.status', SessionStatus::Completed->value)
            ->where('question_status.status', UserQuestionStatus::Incorrect->value)
            ->whereColumn('question_status.last_attempt_at', 'question_attempts.answered_at')
            ->whereIn('question_medical_topics.medical_taxonomy_node_id', $nodeIds)
            ->orderByDesc('question_attempts.answered_at')
            ->orderByDesc('question_attempts.id')
            ->get(['question_attempts.session_id', 'question_attempts.question_id']);

        $originSessionId = $rows->first()?->session_id;
        if (! is_string($originSessionId)) {
            return null;
        }

        $session = QuestionSession::query()
            ->where('user_id', $userId)
            ->find($originSessionId);
        if (! $session instanceof QuestionSession) {
            return null;
        }

        return [
            'session' => $session,
            'question_ids' => $rows
                ->where('session_id', $originSessionId)
                ->pluck('question_id')
                ->map(static fn ($id): string => (string) $id)
                ->unique()
                ->values()
                ->all(),
        ];
    }
}
