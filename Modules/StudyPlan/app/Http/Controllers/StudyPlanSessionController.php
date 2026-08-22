<?php

declare(strict_types=1);

namespace Modules\StudyPlan\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Html\SafeHtml;
use App\Support\Http\Responses\ApiResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Modules\Personalization\Models\Bookmark;
use Modules\QuestionBank\Models\QuestionAttempt;
use Modules\QuestionBank\Models\QuestionSession;
use Modules\QuestionBank\Services\QuestionSessionInsights;
use Modules\QuestionBank\Services\QuestionSessionSnapshots;
use Modules\StudyPlan\Actions\AnswerPlanQuestionAction;
use Modules\StudyPlan\Actions\SavePlanSessionAnnotationAction;
use Modules\StudyPlan\Actions\StartPlanTaskAction;
use Modules\StudyPlan\Models\StudyPlan;
use Modules\StudyPlan\Models\StudyPlanTask;

/**
 * The study session a plan task runs in.
 *
 * Questions come from the Q-Bank session created by `StartPlanTaskAction`;
 * this controller only walks the learner through it and records answers.
 */
final class StudyPlanSessionController extends Controller
{
    public function __construct(
        private readonly StartPlanTaskAction $startTask,
        private readonly AnswerPlanQuestionAction $answerQuestion,
        private readonly SavePlanSessionAnnotationAction $saveAnnotation,
        private readonly QuestionSessionSnapshots $snapshots,
        private readonly QuestionSessionInsights $insights,
    ) {}

    public function show(Request $request, StudyPlan $plan, StudyPlanTask $task): View|RedirectResponse
    {
        $this->authorize('view', $plan);

        // Prefer the existing session (including completed) so the learner can
        // still jump between questions from the map after finishing the batch.
        $session = $this->sessionFor($plan, $task);

        if ($session === null) {
            if ($task->isDone()) {
                return redirect()->route('study-plan.session.summary', [$plan, $task]);
            }

            $session = $this->startTask->handle($task);
        }

        $questionIds = $session->question_ids ?? [];
        $attempts = $this->attempts($session);

        $index = $this->resolveIndex($request, $questionIds, $attempts);

        if ($index === null) {
            return redirect()->route('study-plan.session.summary', [$plan, $task]);
        }

        $question = $this->snapshots->question($session, (string) $questionIds[$index]);
        abort_if($question === null, 410, 'Nội dung câu hỏi của phiên này không còn khả dụng.');

        $annotation = ($session->annotations ?? [])[(string) $question->getKey()] ?? [];
        $flagged = (bool) ($annotation['flagged'] ?? $attempts->get($question->getKey())?->flagged ?? false);
        $flaggedIds = collect($session->annotations ?? [])
            ->filter(fn (array $item): bool => (bool) ($item['flagged'] ?? false))
            ->keys()
            ->merge(
                $attempts->filter(fn (QuestionAttempt $attempt): bool => $attempt->flagged)->keys()
            )
            ->unique()
            ->values()
            ->all();

        return view('studyplan::session', [
            'plan' => $plan,
            'task' => $task,
            'session' => $session,
            'question' => $question,
            'attempt' => $attempts->get($question->getKey()),
            'index' => $index,
            'total' => count($questionIds),
            'answeredIds' => $attempts->keys()->all(),
            'questionIds' => $questionIds,
            'note' => (string) ($annotation['note'] ?? ''),
            'noteHtml' => (string) ($annotation['note_html'] ?? nl2br(e((string) ($annotation['note'] ?? '')))),
            'stemHtml' => (string) ($annotation['stem_html'] ?? SafeHtml::forDisplay((string) $question->stem)),
            'flagged' => $flagged,
            'flaggedIds' => $flaggedIds,
            'bookmarked' => Bookmark::hasQuestion(
                (int) $request->user()->getAuthIdentifier(),
                (string) $question->getKey(),
            ),
            'bookmarkUrl' => route('bookmarks.questions.set', $question),
        ]);
    }

    public function annotate(Request $request, StudyPlan $plan, StudyPlanTask $task): JsonResponse
    {
        $this->authorize('update', $plan);

        $validated = $request->validate([
            'question_id' => ['required', 'string'],
            'note' => ['nullable', 'string', 'max:5000'],
            'note_html' => ['nullable', 'string', 'max:20000'],
            'stem_html' => ['nullable', 'string', 'max:20000'],
            'flagged' => ['nullable', 'boolean'],
            'key_info_used' => ['nullable', 'boolean'],
            'attending_tip_used' => ['nullable', 'boolean'],
        ]);

        $session = $this->sessionFor($plan, $task);
        abort_if($session === null, 404, 'Phiên làm bài không tồn tại.');

        $questionIds = $session->question_ids ?? [];
        abort_unless(in_array($validated['question_id'], $questionIds, true), 422, 'Câu hỏi không thuộc phiên này.');

        $question = $this->snapshots->question($session, $validated['question_id']);
        abort_if($question === null, 410, 'Nội dung câu hỏi của phiên này không còn khả dụng.');

        $annotation = $this->saveAnnotation->handle(
            $session,
            $question,
            array_key_exists('note', $validated) ? ($validated['note'] ?? '') : null,
            array_key_exists('note_html', $validated) ? ($validated['note_html'] ?? '') : null,
            array_key_exists('stem_html', $validated) ? ($validated['stem_html'] ?? null) : null,
            array_key_exists('flagged', $validated) ? (bool) $validated['flagged'] : null,
            array_key_exists('key_info_used', $validated) ? (bool) $validated['key_info_used'] : null,
            array_key_exists('attending_tip_used', $validated) ? (bool) $validated['attending_tip_used'] : null,
        );

        return ApiResponse::item($annotation);
    }

    public function answer(Request $request, StudyPlan $plan, StudyPlanTask $task): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $plan);

        $validated = $request->validate([
            'question_id' => ['required', 'string'],
            'option_ids' => ['required', 'array', 'min:1'],
            'option_ids.*' => ['integer', 'distinct'],
            'time_spent_seconds' => ['nullable', 'integer', 'min:0', 'max:7200'],
            'index' => ['nullable', 'integer', 'min:0'],
        ]);

        $session = $this->startTask->handle($task);
        abort_unless(
            in_array($validated['question_id'], $session->question_ids ?? [], true),
            422,
            'Câu hỏi không thuộc phiên này.',
        );
        $question = $this->snapshots->question($session, $validated['question_id']);
        abort_if($question === null, 410, 'Nội dung câu hỏi của phiên này không còn khả dụng.');
        $optionIds = array_map('intval', $validated['option_ids']);
        $validOptionIds = $question->options->pluck('id')->map(fn ($id): int => (int) $id)->all();
        abort_unless(
            collect($optionIds)->every(fn (int $id): bool => in_array($id, $validOptionIds, true)),
            422,
            'Đáp án không thuộc câu hỏi này.',
        );

        $attempt = $this->answerQuestion->handle(
            $task,
            $session,
            $question,
            $optionIds,
            (int) ($validated['time_spent_seconds'] ?? 0),
        );

        $attempts = $this->attempts($session->refresh());
        $questionIds = $session->question_ids ?? [];
        $allDone = $questionIds !== [] && collect($questionIds)->every(
            fn (string $id) => $attempts->has($id),
        );

        if ($request->expectsJson()) {
            return ApiResponse::item([
                'is_correct' => (bool) $attempt->is_correct,
                'task_done' => $task->refresh()->done,
                'task_status' => $task->status->value,
                'all_done' => $allDone,
                'summary_url' => route('study-plan.session.summary', [$plan, $task]),
                'review_url' => route('study-plan.session.review', [$plan, $task]),
            ]);
        }

        if ($allDone) {
            return redirect()->route('study-plan.session.summary', [$plan, $task]);
        }

        return redirect()->route('study-plan.session', [
            'plan' => $plan,
            'task' => $task,
            'index' => $validated['index'] ?? null,
        ]);
    }

    public function summary(StudyPlan $plan, StudyPlanTask $task): View|RedirectResponse
    {
        $this->authorize('view', $plan);

        $session = $this->sessionFor($plan, $task);

        if ($session === null) {
            return redirect()
                ->route('study-plan.detail', $plan)
                ->with('status', 'Nhiệm vụ này chưa có phiên làm bài để phân tích.');
        }

        $questionIds = $session->question_ids ?? [];

        if ($questionIds === []) {
            return redirect()
                ->route('study-plan.detail', $plan)
                ->with('status', 'Chưa có câu hỏi để phân tích.');
        }

        $attempts = $this->attempts($session);
        $questions = $this->snapshots->questionMap($session);
        $questionIds = array_values(array_filter(
            $questionIds,
            fn (string $questionId): bool => isset($questions[$questionId]),
        ));

        $total = count($questionIds);
        $correctCount = 0;
        $wrongCount = 0;
        $skippedCount = 0;
        $flaggedCount = 0;
        $timeSpent = 0;
        /** @var array<string, array{name: string, correct: int, wrong: int, skipped: int, total: int}> $byTopic */
        $byTopic = [];

        foreach ($questionIds as $questionId) {
            $question = $questions[$questionId] ?? null;
            $attempt = $attempts->get($questionId);
            $topicNames = $question?->topics
                ->pluck('name')
                ->map(fn ($name): string => (string) $name)
                ->all() ?? [];
            if ($topicNames === []) {
                $topicNames = [$question?->topic?->name ?? 'Tổng hợp'];
            }
            foreach ($topicNames as $topicName) {
                $byTopic[$topicName] ??= [
                    'name' => $topicName,
                    'correct' => 0,
                    'wrong' => 0,
                    'skipped' => 0,
                    'total' => 0,
                ];
                $byTopic[$topicName]['total']++;
            }

            $annotation = ($session->annotations ?? [])[(string) $questionId] ?? [];
            $flagged = (bool) ($annotation['flagged'] ?? $attempt?->flagged ?? false);

            if ($flagged) {
                $flaggedCount++;
            }

            if ($attempt === null) {
                $skippedCount++;
                foreach ($topicNames as $topicName) {
                    $byTopic[$topicName]['skipped']++;
                }

                continue;
            }

            $timeSpent += (int) $attempt->time_spent_seconds;

            if ($attempt->is_correct) {
                $correctCount++;
                foreach ($topicNames as $topicName) {
                    $byTopic[$topicName]['correct']++;
                }
            } else {
                $wrongCount++;
                foreach ($topicNames as $topicName) {
                    $byTopic[$topicName]['wrong']++;
                }
            }
        }

        $accuracy = $total > 0 ? (int) round(($correctCount / $total) * 100) : 0;
        $correctShare = $total > 0 ? ($correctCount / $total) * 100 : 0;
        $wrongShare = $total > 0 ? ($wrongCount / $total) * 100 : 0;
        $donutStyle = sprintf(
            'conic-gradient(#16A34A 0%% %.2f%%, #DC2626 %.2f%% %.2f%%, #BDC9C6 %.2f%% 100%%)',
            $correctShare,
            $correctShare,
            $correctShare + $wrongShare,
            $correctShare + $wrongShare,
        );

        $topics = collect($byTopic)
            ->map(function (array $row) use ($plan, $task) {
                $answered = $row['correct'] + $row['wrong'];
                $rate = $row['total'] > 0
                    ? (int) round(($row['correct'] / $row['total']) * 100)
                    : 0;

                return [
                    'name' => $row['name'],
                    'rate' => $rate,
                    'count' => $row['correct'].'/'.$row['total'],
                    'correct' => $row['correct'],
                    'wrong' => $row['wrong'],
                    'skipped' => $row['skipped'],
                    'total' => $row['total'],
                    'barClass' => match (true) {
                        $rate >= 70 => 'bg-[#16A34A]',
                        $rate >= 50 => 'bg-primary',
                        default => 'bg-error',
                    },
                    'rateClass' => match (true) {
                        $rate >= 70 => 'text-[#16A34A]',
                        $rate >= 50 => 'text-primary',
                        default => 'text-error',
                    },
                    'nameClass' => $rate < 50 ? 'text-error' : '',
                    'rowClass' => $rate < 50 ? 'bg-error-container/5' : '',
                    'action' => $rate < 50 ? 'urgent' : 'review',
                    'actionLabel' => $rate < 50 ? 'Cần ôn lại' : 'Ổn định',
                    'reviewUrl' => $rate < 50
                        ? route('study-plan.session.review', [
                            $plan,
                            $task,
                            'filter' => 'needs',
                            'topic' => $row['name'],
                        ])
                        : null,
                ];
            })
            ->sortBy('rate')
            ->values()
            ->all();

        $chartBars = collect($topics)
            ->sortByDesc('total')
            ->take(6)
            ->map(fn (array $topic) => [
                'label' => $topic['name'],
                'height' => max(4, $topic['rate']),
                'rate' => $topic['rate'],
            ])
            ->values()
            ->all();

        return view('studyplan::session-summary', [
            'plan' => $plan,
            'task' => $task,
            'session' => $session,
            'total' => $total,
            'correctCount' => $correctCount,
            'wrongCount' => $wrongCount,
            'skippedCount' => $skippedCount,
            'flaggedCount' => $flaggedCount,
            'accuracy' => $accuracy,
            'donutStyle' => $donutStyle,
            'timeSpentSeconds' => $timeSpent,
            'topics' => $topics,
            'chartBars' => $chartBars,
            'questionOverview' => $this->insights->questionOverview($session),
        ]);
    }

    public function review(StudyPlan $plan, StudyPlanTask $task): View|RedirectResponse
    {
        $this->authorize('view', $plan);

        $session = $this->sessionFor($plan, $task);

        if ($session === null) {
            return redirect()
                ->route('study-plan.detail', $plan)
                ->with('status', 'Nhiệm vụ này chưa có phiên làm bài để xem lại.');
        }

        $questionIds = $session->question_ids ?? [];

        if ($questionIds === []) {
            return redirect()
                ->route('study-plan.detail', $plan)
                ->with('status', 'Chưa có câu hỏi để xem lại.');
        }

        $attempts = $this->attempts($session);
        $questions = $this->snapshots->questionMap($session);

        $items = [];

        foreach ($questionIds as $position => $questionId) {
            $question = $questions[$questionId] ?? null;

            if ($question === null) {
                continue;
            }

            $attempt = $attempts->get($questionId);
            $options = $question->getRelation('options');
            $selectedIds = array_map('intval', $attempt?->selected_option_ids ?? []);
            $correctOption = $options->firstWhere('is_correct', true);
            $selectedOption = $options->first(fn ($option) => in_array((int) $option->id, $selectedIds, true));

            $result = match (true) {
                $attempt === null => 'skipped',
                (bool) $attempt->is_correct => 'correct',
                default => 'wrong',
            };

            $optionPayload = $options->map(function ($option) use ($selectedIds) {
                $id = (int) $option->id;
                $selected = in_array($id, $selectedIds, true);
                $correct = (bool) $option->is_correct;

                $state = match (true) {
                    $correct && $selected => 'correct_selected',
                    $correct => 'correct',
                    $selected => 'wrong_selected',
                    default => 'dimmed',
                };

                return [
                    'id' => $id,
                    'key' => $option->label,
                    'text' => $option->content,
                    'explanation' => $option->explanation,
                    'state' => $state,
                ];
            })->values()->all();

            $pick = match ($result) {
                'skipped' => 'Chưa trả lời • Đúng <strong class="text-green-600">'.e($correctOption?->label ?? '—').'</strong>',
                'correct' => 'Bạn chọn <strong class="text-primary">'.e($selectedOption?->label ?? '—').'</strong> • Đúng <strong class="text-green-600">'.e($correctOption?->label ?? '—').'</strong>',
                default => 'Bạn chọn <strong class="text-error">'.e($selectedOption?->label ?? '—').'</strong> • Đúng <strong class="text-green-600">'.e($correctOption?->label ?? '—').'</strong>',
            };

            $annotation = ($session->annotations ?? [])[(string) $questionId] ?? [];
            $stemHtml = (string) ($annotation['stem_html'] ?? SafeHtml::forDisplay((string) $question->stem));
            $note = (string) ($annotation['note'] ?? '');
            $noteHtml = (string) ($annotation['note_html'] ?? nl2br(e($note)));
            $flagged = (bool) ($annotation['flagged'] ?? $attempt?->flagged ?? false);

            $items[] = [
                'id' => 'Q'.($position + 1),
                'index' => $position,
                'result' => $result,
                'icon' => match ($result) {
                    'correct' => 'check_circle',
                    'wrong' => 'cancel',
                    default => 'horizontal_rule',
                },
                'iconClass' => match ($result) {
                    'correct' => 'text-green-600',
                    'wrong' => 'text-error',
                    default => 'text-outline',
                },
                'topic' => $question->topics->pluck('name')->join(', ') ?: ($question->topic?->name ?? 'Tổng hợp'),
                'topics' => $question->topics->pluck('name')->values()->all(),
                'excerpt' => Str::limit(strip_tags($question->stem), 140),
                'stem' => $question->stem,
                'stemHtml' => $stemHtml,
                'note' => $note,
                'noteHtml' => $noteHtml,
                'hasNote' => trim($note) !== '',
                'explanation' => $question->explanation,
                'pick' => $pick,
                'flagged' => $flagged,
                'options' => $optionPayload,
            ];
        }

        $answered = collect($items)->where('result', '!=', 'skipped')->count();

        $allowedFilters = ['all', 'correct', 'wrong', 'skipped', 'needs'];
        $initialFilter = (string) request()->query('filter', 'all');
        if (! in_array($initialFilter, $allowedFilters, true)) {
            $initialFilter = 'all';
        }

        $topicNames = collect($items)->pluck('topic')->unique()->values()->all();
        $initialTopic = (string) request()->query('topic', '');
        if ($initialTopic !== '' && ! in_array($initialTopic, $topicNames, true)) {
            $initialTopic = '';
        }

        $initialActive = collect($items)
            ->first(function (array $item) use ($initialFilter, $initialTopic): bool {
                if ($initialTopic !== '' && $item['topic'] !== $initialTopic) {
                    return false;
                }

                return match ($initialFilter) {
                    'needs' => in_array($item['result'], ['wrong', 'skipped'], true),
                    'all' => true,
                    default => $item['result'] === $initialFilter,
                };
            });

        return view('studyplan::session-review', [
            'plan' => $plan,
            'task' => $task,
            'session' => $session,
            'items' => $items,
            'answered' => $answered,
            'total' => count($items),
            'correctCount' => collect($items)->where('result', 'correct')->count(),
            'wrongCount' => collect($items)->where('result', 'wrong')->count(),
            'skippedCount' => collect($items)->where('result', 'skipped')->count(),
            'initialFilter' => $initialFilter,
            'initialTopic' => $initialTopic,
            'initialActive' => (int) ($initialActive['index'] ?? 0),
        ]);
    }

    /**
     * Attempts already recorded in the session, keyed by question.
     *
     * @return Collection<string, QuestionAttempt>
     */
    private function attempts(QuestionSession $session)
    {
        return QuestionAttempt::query()
            ->where('session_id', $session->getKey())
            ->get()
            ->keyBy('question_id');
    }

    private function sessionFor(StudyPlan $plan, StudyPlanTask $task): ?QuestionSession
    {
        $sessionId = $task->sessionId();

        if ($sessionId === null) {
            return null;
        }

        return QuestionSession::query()
            ->whereKey($sessionId)
            ->where('user_id', $plan->user_id)
            ->first();
    }

    /**
     * Honour an explicit `?index=`, otherwise jump to the first unanswered
     * question. Null means the whole batch is done.
     *
     * @param  array<int, string>  $questionIds
     * @param  Collection<string, QuestionAttempt>  $attempts
     */
    private function resolveIndex(Request $request, array $questionIds, $attempts): ?int
    {
        if ($questionIds === []) {
            return null;
        }

        if ($request->has('index')) {
            $index = (int) $request->integer('index');

            if ($index >= 0 && $index < count($questionIds)) {
                return $index;
            }
        }

        foreach ($questionIds as $position => $questionId) {
            if (! $attempts->has($questionId)) {
                return $position;
            }
        }

        return null;
    }
}
