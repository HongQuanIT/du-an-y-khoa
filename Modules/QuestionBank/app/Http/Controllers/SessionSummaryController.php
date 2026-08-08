<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Modules\QuestionBank\Enums\SessionStatus;
use Modules\QuestionBank\Models\QuestionSession;
use Modules\QuestionBank\Services\QuestionSessionInsights;

final class SessionSummaryController extends Controller
{
    public function __construct(private readonly QuestionSessionInsights $insights) {}

    public function __invoke(QuestionSession $session): View|RedirectResponse
    {
        $this->authorize('view', $session);

        if ($session->status !== SessionStatus::Completed) {
            // Avoid bouncing with StudySessionController when question_ids is empty.
            if (($session->question_ids ?? []) === []) {
                return redirect()
                    ->route('qbank.index')
                    ->with('status', 'Phiên này không còn câu hỏi để phân tích. Hãy tạo phiên mới.');
            }

            return redirect()->route('qbank.session', $session);
        }

        $summary = $this->insights->summary($session);
        $topics = collect($summary['topics'])
            ->map(function (array $topic) use ($session): array {
                $rate = (int) $topic['rate'];

                return array_merge($topic, [
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
                    'actionLabel' => $rate < 50 ? 'Cần ôn lại' : 'Ổn định',
                    'reviewUrl' => $rate < 50
                        ? route('qbank.review', [$session, 'filter' => 'needs'])
                        : null,
                ]);
            })
            ->values()
            ->all();
        $chartBars = collect($topics)
            ->sortByDesc('total')
            ->take(6)
            ->map(fn (array $topic): array => [
                'label' => (string) $topic['name'],
                'height' => max(4, (int) $topic['rate']),
                'rate' => (int) $topic['rate'],
            ])
            ->values()
            ->all();
        $modeLabel = $session->mode->value === 'exam' ? 'Phiên thi' : 'Phiên học tập';

        return view('studyplan::session-summary', [
            'session' => $session,
            'summary' => $summary,
            'total' => $summary['total'],
            'correctCount' => $summary['correct'],
            'wrongCount' => $summary['wrong'],
            'skippedCount' => $summary['skipped'],
            'flaggedCount' => $summary['flagged'],
            'accuracy' => $summary['accuracy'],
            'donutStyle' => $summary['donut_style'],
            'timeSpentSeconds' => $summary['time_spent_seconds'],
            'topics' => $topics,
            'chartBars' => $chartBars,
            'questionOverview' => $this->insights->questionOverview($session),
            'summaryConfig' => [
                'page_title' => 'Phân tích kết quả',
                'heading' => 'Phân tích kết quả',
                'subtitle' => $modeLabel.' · hoàn thành '.$summary['total'].' câu hỏi trong phiên này.',
                'breadcrumbs' => [
                    ['label' => 'Ngân hàng câu hỏi', 'url' => route('qbank.index')],
                    [
                        'label' => 'Phiên '.Str::limit((string) $session->getKey(), 8, ''),
                        'url' => route('qbank.summary', $session),
                    ],
                    ['label' => 'Phân tích', 'url' => null],
                ],
                'review_url' => route('qbank.review', $session),
                'back_url' => route('qbank.index'),
                'back_label' => 'Quay lại ngân hàng câu hỏi',
                'back_icon' => 'history',
                'progress_label' => $summary['answered'].'/'.$summary['total'].' câu đã trả lời',
                'context_message' => 'Kết quả đã được lưu vào lịch sử Ngân hàng câu hỏi.',
            ],
        ]);
    }
}
