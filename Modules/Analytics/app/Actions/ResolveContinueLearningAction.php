<?php

declare(strict_types=1);

namespace Modules\Analytics\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use Modules\Analytics\Models\TopicMastery;
use Modules\QuestionBank\Enums\SessionMode;
use Modules\QuestionBank\Enums\SessionStatus;
use Modules\QuestionBank\Models\QuestionSession;
use Modules\StudyPlan\Actions\ListTodayTasksAction;
use Modules\StudyPlan\Models\StudyPlanTask;
use Modules\StudyPlan\Repositories\StudyPlanRepository;

/**
 * Use case: what the dashboard's "Continue learning" card should point at.
 *
 * Priority per srs/modules/03: unfinished session, today's plan task, weakest
 * topic, then a safe default practice action.
 *
 * @phpstan-type ContinueCard array{label: string, title: string, hint: string, progress: int, url: string}
 */
final class ResolveContinueLearningAction
{
    use AsAction;

    public function __construct(
        private readonly StudyPlanRepository $plans,
        private readonly ListTodayTasksAction $todayTasks,
    ) {}

    /**
     * @return ContinueCard
     */
    public function handle(User $user): ?array
    {
        return $this->fromPausedSession($user)
            ?? $this->fromPlanTask($user)
            ?? $this->fromWeakTopic($user)
            ?? $this->defaultPractice();
    }

    /**
     * @return ContinueCard|null
     */
    private function fromPausedSession(User $user): ?array
    {
        $session = QuestionSession::query()
            ->where('user_id', $user->getKey())
            ->whereIn('status', [SessionStatus::Active, SessionStatus::Paused])
            ->where('answered_count', '>', 0)
            ->latest('updated_at')
            ->first();

        if ($session === null) {
            return null;
        }

        $task = $this->taskBehind($session);

        return [
            'label' => 'Đang học dở',
            'title' => $task?->title() ?? 'Phiên luyện tập',
            'hint' => sprintf(
                'Câu %d/%d · Chế độ %s',
                $session->answered_count + 1,
                $session->total,
                $session->mode === SessionMode::Exam ? 'Thi thử' : 'Học tập',
            ),
            'progress' => $session->total > 0
                ? (int) round($session->answered_count / $session->total * 100)
                : 0,
            'url' => $task !== null
                ? route('study-plan.session', [$task->study_plan_id, $task])
                : route(
                    $session->mode === SessionMode::Exam ? 'exam.session' : 'qbank.session',
                    $session,
                ),
        ];
    }

    /**
     * @return ContinueCard|null
     */
    private function fromPlanTask(User $user): ?array
    {
        $plan = $this->plans->currentFor($user);

        if ($plan === null || ! $plan->isActive()) {
            return null;
        }

        $task = $this->todayTasks->handle($plan)
            ->first(fn (StudyPlanTask $task) => ! $task->isDone() && $task->type->isSupported());

        if ($task === null) {
            return null;
        }

        return [
            'label' => 'Nhiệm vụ hôm nay',
            'title' => $task->title(),
            'hint' => sprintf('%s · khoảng %d phút', $plan->name, $task->estimatedMinutes()),
            'progress' => $task->percent(),
            'url' => route('study-plan.session', [$plan, $task]),
        ];
    }

    private function taskBehind(QuestionSession $session): ?StudyPlanTask
    {
        $taskId = $session->filters['study_plan_task_id'] ?? null;

        if (! is_numeric($taskId)) {
            return null;
        }

        return StudyPlanTask::query()
            ->whereKey((int) $taskId)
            ->whereHas('plan', fn ($query) => $query->where('user_id', $session->user_id))
            ->first();
    }

    /** @return ContinueCard|null */
    private function fromWeakTopic(User $user): ?array
    {
        $mastery = TopicMastery::query()
            ->with('medicalTaxonomyNode:id,name')
            ->where('user_id', $user->getKey())
            ->where('attempts', '>=', 3)
            ->orderBy('correct_rate')
            ->first();

        if ($mastery === null) {
            return null;
        }

        return [
            'label' => 'Gợi ý tiếp theo',
            'title' => 'Củng cố '.($mastery->medicalTaxonomyNode?->name ?? 'chủ đề còn yếu'),
            'hint' => sprintf('%d lượt làm · chính xác %d%%', $mastery->attempts, (int) round($mastery->correct_rate)),
            'progress' => (int) round($mastery->correct_rate),
            'url' => route('qbank.create', [
                'source' => 'weak_topics',
                'medical_taxonomy_node_ids' => [$mastery->medical_taxonomy_node_id],
            ]),
        ];
    }

    /** @return ContinueCard */
    private function defaultPractice(): array
    {
        return [
            'label' => 'Bắt đầu học',
            'title' => 'Tạo phiên luyện tập của bạn',
            'hint' => 'Chọn chủ đề, độ khó và số câu phù hợp với mục tiêu hôm nay.',
            'progress' => 0,
            'url' => route('qbank.create'),
        ];
    }
}
