<?php

declare(strict_types=1);

namespace Modules\Editor\Actions;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Models\Question;

final class GetEditorDashboardDataAction
{
    private const CACHE_TTL_SECONDS = 120;

    /** @return array<string, mixed> */
    public function handle(User $editor): array
    {
        $key = 'editor:dashboard:v1:'.$editor->getKey();

        /** @var array<string, mixed> $data */
        $data = Cache::remember($key, self::CACHE_TTL_SECONDS, fn (): array => $this->build($editor));
        $data['refreshed_at'] = Carbon::parse($data['refreshed_at']);

        return $data;
    }

    /** @return array<string, mixed> */
    private function build(User $editor): array
    {
        $questions = Question::query()->where('created_by', $editor->getKey());
        $today = Carbon::today();
        $start = $today->copy()->subDays(29)->startOfDay();
        $statusCounts = (clone $questions)->selectRaw('status, COUNT(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');

        $drafts = (int) ($statusCounts[QuestionStatus::Draft->value] ?? 0);
        $returned = (int) ($statusCounts[QuestionStatus::Rejected->value] ?? 0);
        $inReview = (int) ($statusCounts[QuestionStatus::InReview->value] ?? 0);
        $pendingPublish = (int) ($statusCounts[QuestionStatus::PendingPublish->value] ?? 0);
        return [
            'refreshed_at' => now()->toIso8601String(),
            'kpis' => [
                $this->kpi('Câu hỏi', (clone $questions)->count(), 'Tất cả bản nháp và đã xuất bản', 'quiz'),
                $this->kpi('Bản nháp', $drafts, 'Cần hoàn thiện trước khi gửi duyệt', 'edit_note', $drafts > 0 ? 'warning' : null),
                $this->kpi('Cần chỉnh sửa', $returned, 'Câu hỏi bị trả về cần xử lý lại', 'assignment_return', $returned > 0 ? 'critical' : null),
                $this->kpi('Đang chờ duyệt', $inReview + $pendingPublish, $inReview.' chờ giảng viên · '.$pendingPublish.' chờ xuất bản', 'hourglass_top'),
            ],
            'todos' => $this->todos($drafts, $returned, $inReview, $pendingPublish),
            'charts' => $this->charts($questions, $start, $statusCounts),
            'priority_questions' => (clone $questions)
                ->select(['id', 'code', 'stem', 'status', 'updated_at'])
                ->latest('updated_at')
                ->limit(12)
                ->get()
                ->sortBy(fn (Question $question): int => match ($question->status) {
                    QuestionStatus::Rejected => 1,
                    QuestionStatus::Draft => 2,
                    QuestionStatus::InReview, QuestionStatus::PendingPublish => 3,
                    default => 4,
                })
                ->take(6)
                ->map(fn (Question $question): array => [
                    'id' => (int) $question->getKey(),
                    'code' => $question->code,
                    'title' => str($question->stem)->stripTags()->squish()->limit(110)->toString(),
                    'status' => $question->status,
                    'updated_at' => $question->updated_at?->toIso8601String(),
                    'href' => route('editor.questions.edit', $question),
                ])
                ->all(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function charts($questions, Carbon $start, $statusCounts): array
    {
        $labels = [];
        $keys = [];
        for ($day = 0; $day < 30; $day++) {
            $date = $start->copy()->addDays($day);
            $labels[] = $date->format('d/m');
            $keys[] = $date->toDateString();
        }

        $created = (clone $questions)->where('created_at', '>=', $start)->get(['created_at'])
            ->countBy(fn (Question $question): string => $question->created_at->toDateString());
        $updated = (clone $questions)->where('updated_at', '>=', $start)->get(['updated_at'])
            ->countBy(fn (Question $question): string => $question->updated_at->toDateString());

        return [
            [
                'id' => 'chart-editor-output',
                'title' => 'Nhịp độ biên tập',
                'subtitle' => '30 ngày gần đây',
                'type' => 'line',
                'format' => 'number',
                'labels' => $labels,
                'datasets' => [
                    ['label' => 'Tạo mới', 'data' => array_map(fn (string $key): int => (int) ($created[$key] ?? 0), $keys), 'color' => '#0f766e'],
                    ['label' => 'Cập nhật', 'data' => array_map(fn (string $key): int => (int) ($updated[$key] ?? 0), $keys), 'color' => '#2563eb'],
                ],
            ],
            [
                'id' => 'chart-editor-status',
                'title' => 'Trạng thái nội dung',
                'subtitle' => 'Toàn bộ câu hỏi của bạn',
                'type' => 'bar',
                'format' => 'number',
                'labels' => ['Nháp', 'Chờ GV', 'Chờ XB', 'Đã XB', 'Trả về'],
                'datasets' => [[
                    'label' => 'Câu hỏi',
                    'data' => [
                        (int) ($statusCounts[QuestionStatus::Draft->value] ?? 0),
                        (int) ($statusCounts[QuestionStatus::InReview->value] ?? 0),
                        (int) ($statusCounts[QuestionStatus::PendingPublish->value] ?? 0),
                        (int) ($statusCounts[QuestionStatus::Published->value] ?? 0),
                        (int) ($statusCounts[QuestionStatus::Rejected->value] ?? 0),
                    ],
                    'color' => '#7c3aed',
                ]],
            ],
        ];
    }

    /** @return list<array{title: string, description: string, icon: string, severity: string, href: string|null}> */
    private function todos(int $drafts, int $returned, int $inReview, int $pendingPublish): array
    {
        $items = [];
        if ($returned > 0) {
            $items[] = ['title' => $returned.' câu hỏi cần chỉnh sửa', 'description' => 'Các câu hỏi đã bị trả về cần được cập nhật trước khi gửi lại.', 'icon' => 'assignment_return', 'severity' => 'critical', 'href' => route('editor.questions.index', ['status' => QuestionStatus::Rejected->value])];
        }
        if ($drafts > 0) {
            $items[] = ['title' => $drafts.' bản nháp chưa hoàn thiện', 'description' => 'Bổ sung đáp án, giải thích và phân loại trước khi gửi duyệt.', 'icon' => 'edit_note', 'severity' => 'warning', 'href' => route('editor.questions.index', ['status' => QuestionStatus::Draft->value])];
        }
        if ($inReview > 0 || $pendingPublish > 0) {
            $items[] = ['title' => ($inReview + $pendingPublish).' câu hỏi đang trong quy trình', 'description' => $inReview.' chờ giảng viên · '.$pendingPublish.' chờ xuất bản.', 'icon' => 'hourglass_top', 'severity' => 'info', 'href' => route('editor.questions.index', ['status' => [QuestionStatus::InReview->value, QuestionStatus::PendingPublish->value]])];
        }
        if ($items === []) {
            $items[] = ['title' => 'Không có việc cần xử lý ngay', 'description' => 'Các nội dung của bạn đang ở trạng thái ổn định.', 'icon' => 'task_alt', 'severity' => 'ok', 'href' => route('editor.questions.index')];
        }

        return $items;
    }

    /** @return array<string, mixed> */
    private function kpi(string $label, int $value, string $hint, string $icon, ?string $severity = null): array
    {
        return compact('label', 'value', 'hint', 'icon', 'severity');
    }
}
