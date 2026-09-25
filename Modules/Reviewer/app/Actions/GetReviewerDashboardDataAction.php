<?php

declare(strict_types=1);

namespace Modules\Reviewer\Actions;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Enums\ReviewerFlag;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionReviewerFlag;

final class GetReviewerDashboardDataAction
{
    /** @return array<string, mixed> */
    public function handle(User $reviewer): array
    {
        $now = now();
        $start = $now->copy()->subDays(29)->startOfDay();
        $pending = $this->pendingQuery($reviewer);
        $history = QuestionReviewerFlag::query()->where('reviewer_id', $reviewer->getKey());
        $counts = (clone $history)->selectRaw('flag, COUNT(*) as total')->groupBy('flag')->pluck('total', 'flag');
        $green = (int) ($counts[ReviewerFlag::Green->value] ?? 0);
        $red = (int) ($counts[ReviewerFlag::Red->value] ?? 0);
        $total = $green + $red;
        $canViewQuestions = $reviewer->can('question_flag.view');
        $pendingCount = $canViewQuestions ? (clone $pending)->count() : 0;
        $staleCount = $canViewQuestions ? (clone $pending)->where('updated_at', '<', $now->copy()->subDay())->count() : 0;
        $todayCount = (clone $history)->whereDate('reviewed_at', $now->toDateString())->count();

        $daily = (clone $history)->where('reviewed_at', '>=', $start)
            ->selectRaw('DATE(reviewed_at) as day, flag, COUNT(*) as total')
            ->groupByRaw('DATE(reviewed_at), flag')->get();
        $dailyCounts = [];
        foreach ($daily as $row) {
            $dailyCounts[$row->day][$row->getRawOriginal('flag')] = (int) $row->total;
        }
        $dates = [];
        $labels = [];
        for ($day = 0; $day < 30; $day++) {
            $date = $start->copy()->addDays($day);
            $dates[] = $date->toDateString();
            $labels[] = $date->format('d/m');
        }

        return [
            'refreshedAt' => $now,
            'pendingCount' => $pendingCount,
            'canViewQuestions' => $canViewQuestions,
            'canFlag' => $reviewer->can('question.flag'),
            'kpis' => [
                ['label' => 'Chờ gắn cờ', 'value' => $pendingCount, 'hint' => 'Câu hỏi đang chờ bạn review', 'icon' => 'flag', 'severity' => $pendingCount ? 'warning' : null],
                ['label' => 'Đã gắn cờ', 'value' => $total, 'hint' => 'Tổng lượt review của bạn', 'icon' => 'task_alt', 'severity' => null],
                ['label' => 'Gắn cờ hôm nay', 'value' => $todayCount, 'hint' => 'Lượt review trong ngày', 'icon' => 'today', 'severity' => null],
                ['label' => 'Tỷ lệ cờ xanh', 'value' => $total ? round($green / $total * 100).'%' : '—', 'hint' => $green.' xanh · '.$red.' đỏ', 'icon' => 'verified', 'severity' => null],
            ],
            'charts' => [
                ['id' => 'chart-reviewer-activity', 'title' => 'Nhịp độ review', 'subtitle' => '30 ngày gần đây', 'type' => 'line', 'labels' => $labels, 'datasets' => [
                    ['label' => 'Cờ xanh', 'data' => array_map(fn (string $date): int => $dailyCounts[$date][ReviewerFlag::Green->value] ?? 0, $dates), 'color' => '#0f766e'],
                    ['label' => 'Cờ đỏ', 'data' => array_map(fn (string $date): int => $dailyCounts[$date][ReviewerFlag::Red->value] ?? 0, $dates), 'color' => '#dc2626'],
                ]],
                ['id' => 'chart-reviewer-flags', 'title' => 'Phân bố cờ', 'subtitle' => 'Toàn bộ lượt review của bạn', 'type' => 'bar', 'labels' => ['Cờ xanh', 'Cờ đỏ'], 'datasets' => [
                    ['label' => 'Lượt review', 'data' => [$green, $red], 'color' => '#0f766e'],
                ]],
            ],
            'todos' => $this->todos($pendingCount, $staleCount, $todayCount, $canViewQuestions),
            'priorityQuestions' => $canViewQuestions ? (clone $pending)
                ->with(['lessons:id,name', 'creator:id,name'])->orderBy('updated_at')->limit(6)->get() : collect(),
            'recentFlags' => $canViewQuestions ? (clone $history)
                ->with(['question:id,code,stem'])->orderByDesc('reviewed_at')->limit(5)->get() : collect(),
        ];
    }

    /** @return Builder<Question> */
    private function pendingQuery(User $reviewer): Builder
    {
        $id = (int) $reviewer->getKey();

        return Question::query()->where('status', QuestionStatus::InFlagReview->value)
            ->where(fn (Builder $query) => $query->whereNull('created_by')->orWhere('created_by', '!=', $id))
            ->where(fn (Builder $query) => $query->whereNull('reviewer_1_id')->orWhere('reviewer_1_id', '!=', $id))
            ->where(fn (Builder $query) => $query->whereNull('reviewer_2_id')->orWhere('reviewer_2_id', '!=', $id));
    }

    /** @return list<array<string, mixed>> */
    private function todos(int $pending, int $stale, int $today, bool $canViewQuestions): array
    {
        if (! $canViewQuestions) {
            return [['title' => 'Chưa có quyền xem câu hỏi', 'description' => 'Liên hệ quản trị viên để xem hàng đợi review.', 'icon' => 'lock', 'severity' => 'info', 'href' => null]];
        }

        $href = route('reviewer.questions.flags.index');
        $items = [];
        if ($stale) {
            $items[] = ['title' => $stale.' câu hỏi chờ trên 24 giờ', 'description' => 'Ưu tiên xử lý các câu hỏi chờ lâu.', 'icon' => 'schedule', 'severity' => 'warning', 'href' => $href];
        }
        if ($pending) {
            $items[] = ['title' => $pending.' câu hỏi cần review', 'description' => 'Đọc và gắn cờ cho câu hỏi trong hàng đợi.', 'icon' => 'flag', 'severity' => 'info', 'href' => $href];
        }
        if ($today === 0 && $pending) {
            $items[] = ['title' => 'Hôm nay chưa có lượt review', 'description' => 'Bắt đầu từ câu hỏi chờ lâu nhất.', 'icon' => 'today', 'severity' => 'info', 'href' => $href];
        }
        if ($items === []) {
            $items[] = ['title' => 'Đã xử lý hết hàng đợi', 'description' => 'Hiện không có câu hỏi cần review.', 'icon' => 'task_alt', 'severity' => 'ok', 'href' => $href];
        }

        return $items;
    }
}
