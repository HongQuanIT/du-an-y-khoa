<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Http\Responses\ApiResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Modules\QuestionBank\Actions\AnswerQuestionAction;
use Modules\QuestionBank\Actions\CompleteQuestionSessionAction;
use Modules\QuestionBank\Actions\PauseQuestionSessionAction;
use Modules\QuestionBank\Actions\ResumeQuestionSessionAction;
use Modules\QuestionBank\Actions\SaveQuestionSessionAnnotationAction;
use Modules\QuestionBank\Enums\SessionMode;
use Modules\QuestionBank\Enums\SessionStatus;
use Modules\QuestionBank\Models\QuestionAttempt;
use Modules\QuestionBank\Models\QuestionSession;
use Modules\QuestionBank\Services\QuestionSessionSnapshots;
use Modules\QuestionBank\Services\QuestionSessionTimer;

/** Runtime player shared by custom Study and Exam sessions. */
final class StudySessionController extends Controller
{
    public function __construct(
        private readonly AnswerQuestionAction $answerQuestion,
        private readonly CompleteQuestionSessionAction $completeSession,
        private readonly PauseQuestionSessionAction $pauseSession,
        private readonly ResumeQuestionSessionAction $resumeSession,
        private readonly SaveQuestionSessionAnnotationAction $saveAnnotation,
        private readonly QuestionSessionSnapshots $snapshots,
        private readonly QuestionSessionTimer $timer,
    ) {}

    public function show(Request $request, QuestionSession $session): View|RedirectResponse
    {
        $this->authorize('view', $session);

        if ($this->finishExpiredExam($session)) {
            return redirect()->route('qbank.summary', $session);
        }

        if ($session->status === SessionStatus::Completed) {
            return redirect()->route('qbank.summary', $session);
        }

        abort_unless(
            in_array($session->status, [SessionStatus::Active, SessionStatus::Paused], true),
            409,
            'Phiên này không còn hoạt động.',
        );

        $questionIds = $session->question_ids ?? [];
        $attempts = $this->attempts($session);
        $index = $this->resolveIndex($request, $session, $questionIds, $attempts);

        // Empty question set must not redirect to summary — summary bounces
        // incomplete sessions back here and creates an infinite redirect loop.
        if ($index === null) {
            return redirect()
                ->route('qbank.index')
                ->with('status', 'Phiên này không còn câu hỏi để tiếp tục. Hãy tạo phiên mới.');
        }

        $question = $this->snapshots->question($session, (string) $questionIds[$index]);
        abort_if($question === null, 410, 'Nội dung câu hỏi của phiên này không còn khả dụng.');
        $questionKey = (string) $question->getKey();
        $attempt = $attempts->first(
            fn (QuestionAttempt $item): bool => (string) $item->question_id === $questionKey,
        );
        $annotation = ($session->annotations ?? [])[$questionKey] ?? [];
        $flaggedIds = collect($session->annotations ?? [])
            ->filter(fn (array $item): bool => (bool) ($item['flagged'] ?? false))
            ->keys()
            ->merge($attempts->filter(fn (QuestionAttempt $item): bool => $item->flagged)->keys())
            ->unique()
            ->values()
            ->all();

        $viewData = [
            'session' => $session,
            'question' => $question,
            'attempt' => $attempt,
            'index' => $index,
            'total' => count($questionIds),
            'questionIds' => $questionIds,
            'answeredIds' => $attempts->keys()->all(),
            'flaggedIds' => $flaggedIds,
            'note' => (string) ($annotation['note'] ?? ''),
            'stemHtml' => (string) ($annotation['stem_html'] ?? e((string) $question->stem)),
            'flagged' => (bool) ($annotation['flagged']
                ?? ($attempt instanceof QuestionAttempt && $attempt->flagged)),
            'remainingSeconds' => $this->remainingSeconds($session),
        ];

        if ($session->mode === SessionMode::Study) {
            $viewData['playerConfig'] = [
                'page_title' => 'Phiên học tập',
                'header_title' => 'Phiên học tập',
                'header_subtitle' => 'Ngân hàng câu hỏi',
                'saved_label' => $attempts->count().'/'.count($questionIds).' đã lưu',
                'exit_url' => route('qbank.index'),
                'pause_url' => route('qbank.session.pause', $session),
                'finish_url' => route('qbank.session.finish', $session),
                'summary_url' => route('qbank.summary', $session),
                'annotate_url' => route('qbank.session.annotate', $session),
                'answer_url' => route('qbank.session.answer', $session),
                'question_route' => 'qbank.session',
                'question_route_parameters' => ['session' => $session],
                'incomplete_label' => 'Bạn chưa hoàn thành phiên luyện tập',
                'exit_message' => 'Tiến trình đã được lưu — có thể tiếp tục sau từ Ngân hàng câu hỏi.',
            ];

            return view('studyplan::session', $viewData);
        }

        return view('questionbank::exam-session', $viewData);
    }

    public function answer(Request $request, QuestionSession $session): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $session);
        abort_unless(
            in_array($session->status, [SessionStatus::Active, SessionStatus::Paused], true),
            409,
            'Phiên này không còn nhận câu trả lời.',
        );

        if ($this->finishExpiredExam($session)) {
            return $request->expectsJson()
                ? ApiResponse::error('SESSION_EXPIRED', 'Phiên thi đã hết giờ.', 409)
                : redirect()->route('qbank.summary', $session);
        }

        if ($session->status === SessionStatus::Paused) {
            $session = $this->resumeSession->handle($session);
        }

        $validated = $request->validate([
            'question_id' => ['required', 'string'],
            'option_ids' => ['required', 'array', 'min:1'],
            'option_ids.*' => ['integer', 'distinct'],
            'time_spent_seconds' => ['nullable', 'integer', 'min:0', 'max:7200'],
            'index' => ['nullable', 'integer', 'min:0'],
        ]);

        abort_unless(
            in_array($validated['question_id'], $session->question_ids ?? [], true),
            422,
            'Câu hỏi không thuộc phiên này.',
        );

        $question = $this->snapshots->question($session, $validated['question_id']);
        abort_if($question === null, 410, 'Nội dung câu hỏi của phiên này không còn khả dụng.');
        $optionIds = array_map('intval', $validated['option_ids']);
        $validOptionIds = $question->options->pluck('id')->map(fn ($id) => (int) $id)->all();
        abort_unless(
            collect($optionIds)->every(fn (int $id): bool => in_array($id, $validOptionIds, true)),
            422,
            'Đáp án không thuộc câu hỏi này.',
        );
        abort_if(
            $session->mode === SessionMode::Study
                && QuestionAttempt::query()
                    ->where('session_id', $session->getKey())
                    ->where('question_id', $question->getKey())
                    ->exists(),
            409,
            'Câu hỏi này đã được chấm trong chế độ học tập.',
        );

        $attempt = $this->answerQuestion->handle(
            $session,
            $question,
            $optionIds,
            (int) ($validated['time_spent_seconds'] ?? 0),
            autoComplete: false,
        );

        $session->refresh();
        $isExam = $session->mode === SessionMode::Exam;
        $nextIndex = $this->nextIndex($session, (int) ($validated['index'] ?? 0));

        if ($request->expectsJson()) {
            $payload = [
                'saved' => true,
                'answered_count' => $session->answered_count,
                'completed' => $session->status === SessionStatus::Completed,
                'next_url' => route('qbank.session', [$session, 'index' => $nextIndex]),
                'summary_url' => route('qbank.summary', $session),
            ];

            if (! $isExam) {
                $payload['is_correct'] = (bool) $attempt->is_correct;
            }

            return ApiResponse::item($payload);
        }

        if ($session->status === SessionStatus::Completed) {
            return redirect()->route('qbank.summary', $session);
        }

        $targetIndex = $isExam ? $nextIndex : (int) ($validated['index'] ?? 0);

        return redirect()->route('qbank.session', [$session, 'index' => $targetIndex]);
    }

    public function annotate(Request $request, QuestionSession $session): JsonResponse
    {
        $this->authorize('update', $session);
        abort_unless(
            in_array($session->status, [SessionStatus::Active, SessionStatus::Paused], true),
            409,
            'Phiên này không còn nhận thay đổi.',
        );

        $validated = $request->validate([
            'question_id' => ['required', 'string'],
            'note' => ['nullable', 'string', 'max:5000'],
            'stem_html' => ['nullable', 'string', 'max:20000'],
            'flagged' => ['nullable', 'boolean'],
        ]);

        abort_unless(
            in_array($validated['question_id'], $session->question_ids ?? [], true),
            422,
            'Câu hỏi không thuộc phiên này.',
        );

        $question = $this->snapshots->question($session, $validated['question_id']);
        abort_if($question === null, 410, 'Nội dung câu hỏi của phiên này không còn khả dụng.');

        $annotation = $this->saveAnnotation->handle(
            $session,
            $question,
            array_key_exists('note', $validated) ? ($validated['note'] ?? '') : null,
            array_key_exists('stem_html', $validated) ? ($validated['stem_html'] ?? null) : null,
            array_key_exists('flagged', $validated) ? (bool) $validated['flagged'] : null,
        );

        return ApiResponse::item($annotation);
    }

    public function pause(Request $request, QuestionSession $session): RedirectResponse
    {
        $this->authorize('update', $session);
        abort_unless(
            in_array($session->status, [SessionStatus::Active, SessionStatus::Paused], true),
            409,
            'Phiên này không thể tạm dừng.',
        );
        $validated = $request->validate(['current_index' => ['nullable', 'integer', 'min:0']]);

        $this->pauseSession->handle($session, [
            'current_index' => (int) ($validated['current_index'] ?? 0),
        ]);

        return redirect()->route('qbank.index')->with('status', 'Đã lưu và tạm dừng phiên luyện tập.');
    }

    public function resume(QuestionSession $session): RedirectResponse
    {
        $this->authorize('update', $session);
        abort_unless(
            in_array($session->status, [SessionStatus::Active, SessionStatus::Paused], true),
            409,
            'Phiên này không thể tiếp tục.',
        );
        $index = (int) ($session->paused_state['current_index'] ?? 0);
        $this->resumeSession->handle($session);

        return redirect()->route('qbank.session', [$session, 'index' => $index]);
    }

    public function finish(QuestionSession $session): RedirectResponse
    {
        $this->authorize('update', $session);
        abort_unless(
            in_array($session->status, [SessionStatus::Active, SessionStatus::Paused, SessionStatus::Completed], true),
            409,
            'Phiên này không thể hoàn thành.',
        );
        $this->completeSession->handle($session);

        return redirect()->route('qbank.summary', $session);
    }

    /** @return Collection<string, QuestionAttempt> */
    private function attempts(QuestionSession $session): Collection
    {
        return QuestionAttempt::query()
            ->where('session_id', $session->getKey())
            ->get()
            ->keyBy('question_id');
    }

    /**
     * @param  array<int, string>  $questionIds
     * @param  Collection<string, QuestionAttempt>  $attempts
     */
    private function resolveIndex(
        Request $request,
        QuestionSession $session,
        array $questionIds,
        Collection $attempts,
    ): ?int {
        if ($questionIds === []) {
            return null;
        }

        if ($request->filled('index')) {
            return min(max(0, $request->integer('index')), count($questionIds) - 1);
        }

        $pausedIndex = $session->paused_state['current_index'] ?? null;
        if (is_numeric($pausedIndex)) {
            return min(max(0, (int) $pausedIndex), count($questionIds) - 1);
        }

        foreach ($questionIds as $index => $questionId) {
            if (! $attempts->has($questionId)) {
                return $index;
            }
        }

        // Keep the final answered question open so Study users can read its
        // explanation and explicitly finish; Exam users can review/submit.
        return count($questionIds) - 1;
    }

    private function nextIndex(QuestionSession $session, int $current): int
    {
        $last = max(0, count($session->question_ids ?? []) - 1);

        return min($current + 1, $last);
    }

    private function finishExpiredExam(QuestionSession $session): bool
    {
        if ($session->status === SessionStatus::Completed || $this->remainingSeconds($session) !== 0) {
            return false;
        }

        $this->completeSession->handle($session);

        return true;
    }

    private function remainingSeconds(QuestionSession $session): ?int
    {
        return $this->timer->remainingSeconds($session);
    }
}
