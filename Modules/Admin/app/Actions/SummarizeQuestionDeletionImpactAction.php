<?php

declare(strict_types=1);

namespace Modules\Admin\Actions;

use Modules\Classroom\Enums\LiveSessionStatus;
use Modules\Classroom\Models\LiveSession;
use Modules\Exam\Models\Exam;
use Modules\Personalization\Models\Bookmark;
use Modules\QuestionBank\Enums\SessionStatus;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionSession;
use Modules\StudyPlan\Models\StudyPlanTask;

/**
 * Shared usages an admin should see before approving a soft delete.
 *
 * Exams, pinned plan tasks, bookmarks and open live sessions break or go
 * stale after the question disappears from default queries. Sessions already
 * snapshotted keep working, so they are counted as information only.
 */
final class SummarizeQuestionDeletionImpactAction
{
    private const SAMPLE = 6;

    /**
     * @return array{
     *     exam_count: int,
     *     exams: list<array{title: string, status: string}>,
     *     plan_count: int,
     *     task_count: int,
     *     plans: list<array{name: string, owner: string, status: string, task_count: int, next_date: string|null}>,
     *     bookmark_count: int,
     *     live_count: int,
     *     live_sessions: list<array{title: string, classroom: string, status: string}>,
     *     open_session_count: int,
     *     has_shared_usage: bool,
     *     summary: string
     * }
     */
    public function handle(Question $question): array
    {
        $questionId = (string) $question->getKey();

        $exams = Exam::query()
            ->whereHas('questions', fn ($query) => $query->where('questions.id', $questionId))
            ->orderBy('title')
            ->get(['id', 'title', 'status']);

        $tasks = StudyPlanTask::query()
            ->with(['plan.user:id,name'])
            ->whereJsonContains('ref->question_ids', $questionId)
            ->orderBy('date')
            ->get(['id', 'study_plan_id', 'date', 'status']);

        $plans = $tasks
            ->groupBy('study_plan_id')
            ->map(function ($group): array {
                $plan = $group->first()?->plan;

                return [
                    'name' => (string) ($plan->name ?? 'Kế hoạch'),
                    'owner' => (string) ($plan?->user?->name ?? 'Học viên'),
                    'status' => $plan?->status?->label() ?? '',
                    'task_count' => $group->count(),
                    'next_date' => $group->first()?->date?->format('d/m/Y'),
                ];
            })
            ->values();

        $bookmarkCount = Bookmark::query()
            ->where('bookmarkable_type', Bookmark::TYPE_QUESTION)
            ->where('bookmarkable_id', $questionId)
            ->count();

        $liveSessions = LiveSession::query()
            ->with('classroom:id,title')
            ->whereIn('status', [
                LiveSessionStatus::Scheduled->value,
                LiveSessionStatus::Starting->value,
                LiveSessionStatus::Live->value,
            ])
            ->whereJsonContains('question_set->question_ids', $questionId)
            ->orderBy('scheduled_at')
            ->get(['id', 'classroom_id', 'title', 'status']);

        $openSessionCount = QuestionSession::query()
            ->whereIn('status', [SessionStatus::Active->value, SessionStatus::Paused->value])
            ->whereJsonContains('question_ids', $questionId)
            ->count();

        $examCount = $exams->count();
        $taskCount = $tasks->count();
        $planCount = $plans->count();
        $liveCount = $liveSessions->count();
        $hasSharedUsage = $examCount > 0 || $taskCount > 0 || $bookmarkCount > 0 || $liveCount > 0;

        return [
            'exam_count' => $examCount,
            'exams' => $exams->take(self::SAMPLE)->map(fn (Exam $exam): array => [
                'title' => (string) $exam->title,
                'status' => $exam->status->label(),
            ])->values()->all(),
            'plan_count' => $planCount,
            'task_count' => $taskCount,
            'plans' => $plans->take(self::SAMPLE)->all(),
            'bookmark_count' => $bookmarkCount,
            'live_count' => $liveCount,
            'live_sessions' => $liveSessions->take(self::SAMPLE)->map(fn (LiveSession $session): array => [
                'title' => (string) $session->title,
                'classroom' => (string) ($session->classroom?->title ?? 'Lớp học'),
                'status' => $session->status->label(),
            ])->values()->all(),
            'open_session_count' => $openSessionCount,
            'has_shared_usage' => $hasSharedUsage,
            'summary' => $this->summary($examCount, $planCount, $taskCount, $bookmarkCount, $liveCount),
        ];
    }

    private function summary(int $exams, int $plans, int $tasks, int $bookmarks, int $liveSessions): string
    {
        if ($exams === 0 && $tasks === 0 && $bookmarks === 0 && $liveSessions === 0) {
            return 'Không thấy câu này trong đề thi, nhiệm vụ kế hoạch đã ghim, bookmark hay buổi live chưa kết thúc.';
        }

        $parts = [];
        if ($exams > 0) {
            $parts[] = $exams.' đề thi';
        }
        if ($tasks > 0) {
            $parts[] = $tasks.' nhiệm vụ trong '.$plans.' kế hoạch học';
        }
        if ($bookmarks > 0) {
            $parts[] = $bookmarks.' bookmark';
        }
        if ($liveSessions > 0) {
            $parts[] = $liveSessions.' buổi live chưa kết thúc';
        }

        return 'Câu đang nằm trong '.implode(', ', $parts).'.';
    }
}
