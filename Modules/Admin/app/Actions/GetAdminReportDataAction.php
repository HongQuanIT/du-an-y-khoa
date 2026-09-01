<?php

declare(strict_types=1);

namespace Modules\Admin\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use App\Support\Enums\Role;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Analytics\Models\DailyLearningStat;
use Modules\Analytics\Models\TopicMastery;
use Modules\Admin\Support\AdminReportCache;
use Modules\Billing\Models\Payment;
use Modules\Billing\Models\Plan;
use Modules\Billing\Models\Subscription;
use Modules\Billing\Support\AdminBillingMetrics;
use Modules\Billing\Support\BillingSubscriptionStats;
use Modules\Billing\Support\MoneyFormatter;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionFeedback;

/**
 * @phpstan-type ReportKpi array{label: string, value: string, hint: ?string, icon: string, delta: ?float}
 * @phpstan-type ReportChart array{
 *     id: string,
 *     title: string,
 *     subtitle: string,
 *     type: 'line'|'bar',
 *     format: 'number'|'vnd'|'percent',
 *     labels: list<string>,
 *     datasets: list<array{label: string, data: list<int|float>, color: string}>,
 *     full_width?: bool,
 * }
 * @phpstan-type ReportColumn array{key: string, label: string, align?: 'left'|'right'}
 * @phpstan-type ReportRow array<string, string|int|float|null>
 * @phpstan-type ReportPayload array{
 *     range: string,
 *     from: Carbon,
 *     to: Carbon,
 *     kpis: list<ReportKpi>,
 *     charts: list<ReportChart>,
 *     columns: list<ReportColumn>,
 *     rows: list<ReportRow>,
 *     empty_message: ?string,
 * }
 */
final class GetAdminReportDataAction
{
    use AsAction;

    private const RANGES = [
        '7d' => 7,
        '30d' => 30,
        '90d' => 90,
        '365d' => 365,
    ];

    /**
     * @return ReportPayload&array{cached_at?: \Illuminate\Support\Carbon}
     */
    public function handle(string $category, string $report, string $range = '30d', bool $forceFresh = false): array
    {
        [$from, $to, $resolvedRange] = $this->resolveRange($range);

        if (! $forceFresh) {
            $cached = AdminReportCache::get($category, $report, $resolvedRange);
            if ($cached !== null) {
                return AdminReportCache::hydrate($cached);
            }
        }

        $payload = match ("{$category}.{$report}") {
            'users.dau-mau' => $this->usersDauMau($from, $to),
            'users.signups' => $this->usersSignups($from, $to),
            'users.retention' => $this->usersRetention($from, $to),
            'engagement.sessions' => $this->engagementMetric($from, $to, 'completed_sessions', 'Phiên hoàn thành', 'school'),
            'engagement.questions' => $this->engagementMetric($from, $to, 'questions_answered', 'Câu đã làm', 'quiz'),
            'engagement.study-time' => $this->engagementStudyTime($from, $to),
            'revenue.mrr' => $this->revenueMrr($from, $to),
            'revenue.churn' => $this->revenueChurn($from, $to),
            'revenue.funnel' => $this->revenueFunnel(),
            'content.accuracy' => $this->contentAccuracy(),
            'content.flags' => $this->contentFlags($from, $to),
            'content.coverage' => $this->contentCoverage(),
            'learning.mastery' => $this->learningMastery(),
            'learning.exam-scores' => $this->learningExamScoresUnavailable(),
            'learning.weak-topics' => $this->learningWeakTopics(),
            default => [
                'kpis' => [],
                'charts' => [],
                'columns' => [],
                'rows' => [],
                'empty_message' => 'Báo cáo này chưa có nguồn dữ liệu.',
            ],
        };

        $result = [
            'range' => $resolvedRange,
            'from' => $from,
            'to' => $to,
            'kpis' => $payload['kpis'],
            'charts' => $payload['charts'],
            'columns' => $payload['columns'],
            'rows' => $payload['rows'],
            'empty_message' => $payload['empty_message'] ?? null,
        ];

        AdminReportCache::put($category, $report, $resolvedRange, $result);

        return [
            ...$result,
            'cached_at' => now(),
        ];
    }

    /**
     * Flat rows for CSV export (header + values).
     *
     * @return array{headers: list<string>, rows: list<list<string>>}
     */
    public function exportRows(string $category, string $report, string $range = '30d'): array
    {
        $payload = $this->handle($category, $report, $range);
        $headers = array_map(fn (array $col): string => $col['label'], $payload['columns']);
        $keys = array_map(fn (array $col): string => $col['key'], $payload['columns']);

        $rows = [];
        foreach ($payload['rows'] as $row) {
            $rows[] = array_map(
                fn (string $key): string => (string) ($row[$key] ?? ''),
                $keys,
            );
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    /** @return array{0: Carbon, 1: Carbon, 2: string} */
    private function resolveRange(string $range): array
    {
        $resolved = array_key_exists($range, self::RANGES) ? $range : '30d';
        $days = self::RANGES[$resolved];
        $to = Carbon::today()->endOfDay();
        $from = Carbon::today()->subDays($days - 1)->startOfDay();

        return [$from, $to, $resolved];
    }

    /** @return array{kpis: list<ReportKpi>, charts: list<ReportChart>, columns: list<ReportColumn>, rows: list<ReportRow>, empty_message?: ?string} */
    private function usersDauMau(Carbon $from, Carbon $to): array
    {
        $daily = $this->dailyActiveUsers($from, $to);
        $dauToday = (int) ($daily[Carbon::today()->toDateString()] ?? 0);
        $mau = (int) DailyLearningStat::query()
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString())
            ->where(fn ($q) => $q->where('questions_answered', '>', 0)->orWhere('study_seconds', '>', 0))
            ->distinct('user_id')
            ->count('user_id');

        $avgDau = count($daily) > 0 ? (int) round(array_sum($daily) / count($daily)) : 0;

        $labels = [];
        $values = [];
        $rows = [];
        foreach ($this->eachDate($from, $to) as $date) {
            $key = $date->toDateString();
            $value = $daily[$key] ?? 0;
            $labels[] = $date->format('d/m');
            $values[] = $value;
            $rows[] = [
                'date' => $date->format('d/m/Y'),
                'dau' => $value,
            ];
        }

        return [
            'kpis' => [
                $this->kpi('DAU hôm nay', number_format($dauToday), 'Người học có hoạt động', 'groups'),
                $this->kpi('MAU (trong kỳ)', number_format($mau), 'Số người học khác nhau trong khoảng đã chọn', 'group'),
                $this->kpi('DAU trung bình', number_format($avgDau), 'Trung bình theo ngày trong kỳ', 'trending_up'),
            ],
            'charts' => [
                $this->chart('report-dau', 'DAU theo ngày', 'Người học có hoạt động mỗi ngày', 'line', 'number', $labels, [
                    ['label' => 'DAU', 'data' => $values, 'color' => '#0f766e'],
                ], true),
            ],
            'columns' => [
                ['key' => 'date', 'label' => 'Ngày'],
                ['key' => 'dau', 'label' => 'DAU', 'align' => 'right'],
            ],
            'rows' => array_reverse($rows),
        ];
    }

    /** @return array{kpis: list<ReportKpi>, charts: list<ReportChart>, columns: list<ReportColumn>, rows: list<ReportRow>} */
    private function usersSignups(Carbon $from, Carbon $to): array
    {
        $byDate = $this->studentSignupsByDate($from, $to);
        $total = array_sum($byDate);
        $today = (int) ($byDate[Carbon::today()->toDateString()] ?? 0);
        $prevFrom = $from->copy()->subDays($from->diffInDays($to) + 1);
        $prevTo = $from->copy()->subDay();
        $prevTotal = array_sum($this->studentSignupsByDate($prevFrom, $prevTo));

        $labels = [];
        $values = [];
        $rows = [];
        foreach ($this->eachDate($from, $to) as $date) {
            $key = $date->toDateString();
            $value = $byDate[$key] ?? 0;
            $labels[] = $date->format('d/m');
            $values[] = $value;
            $rows[] = [
                'date' => $date->format('d/m/Y'),
                'signups' => $value,
            ];
        }

        return [
            'kpis' => [
                $this->kpi('Đăng ký trong kỳ', number_format($total), 'Học viên mới', 'person_add', $this->percentDelta($total, $prevTotal)),
                $this->kpi('Hôm nay', number_format($today), 'Đăng ký trong ngày', 'today'),
                $this->kpi('Trung bình/ngày', number_format(count($byDate) > 0 ? (int) round($total / max(1, $from->diffInDays($to) + 1)) : 0), null, 'calendar_month'),
            ],
            'charts' => [
                $this->chart('report-signups', 'Đăng ký mới theo ngày', 'Học viên role Student', 'bar', 'number', $labels, [
                    ['label' => 'Đăng ký', 'data' => $values, 'color' => '#0f766e'],
                ], true),
            ],
            'columns' => [
                ['key' => 'date', 'label' => 'Ngày'],
                ['key' => 'signups', 'label' => 'Đăng ký', 'align' => 'right'],
            ],
            'rows' => array_reverse($rows),
        ];
    }

    /** @return array{kpis: list<ReportKpi>, charts: list<ReportChart>, columns: list<ReportColumn>, rows: list<ReportRow>} */
    private function usersRetention(Carbon $from, Carbon $to): array
    {
        $users = User::role(Role::Student->value)
            ->whereBetween('created_at', [$from, $to])
            ->get(['id', 'created_at']);

        $cohortSize = $users->count();
        $returned = 0;
        $d1 = 0;
        $d7 = 0;

        if ($cohortSize > 0) {
            $activity = DailyLearningStat::query()
                ->whereIn('user_id', $users->pluck('id'))
                ->where(fn ($q) => $q->where('questions_answered', '>', 0)->orWhere('study_seconds', '>', 0))
                ->get(['user_id', 'date'])
                ->groupBy('user_id');

            foreach ($users as $user) {
                $signup = $user->created_at->toDateString();
                $dates = ($activity->get($user->id) ?? collect())
                    ->map(fn ($row) => Carbon::parse($row->date)->toDateString())
                    ->unique();

                if ($dates->contains(fn (string $d): bool => $d > $signup)) {
                    $returned++;
                }
                if ($dates->contains(Carbon::parse($signup)->addDay()->toDateString())) {
                    $d1++;
                }
                if ($dates->contains(Carbon::parse($signup)->addDays(7)->toDateString())) {
                    $d7++;
                }
            }
        }

        $rate = $cohortSize > 0 ? round($returned / $cohortSize * 100, 1) : 0.0;
        $d1Rate = $cohortSize > 0 ? round($d1 / $cohortSize * 100, 1) : 0.0;
        $d7Rate = $cohortSize > 0 ? round($d7 / $cohortSize * 100, 1) : 0.0;

        return [
            'kpis' => [
                $this->kpi('Quy mô nhóm đăng ký', number_format($cohortSize), 'Học viên đăng ký trong kỳ (cohort)', 'group'),
                $this->kpi('Quay lại (≥1 ngày sau)', $rate.'%', "{$returned}/{$cohortSize} học viên", 'replay'),
                $this->kpi('Giữ chân D1', $d1Rate.'%', 'Còn hoạt động đúng ngày sau đăng ký', 'looks_one'),
                $this->kpi('Giữ chân D7', $d7Rate.'%', 'Còn hoạt động đúng ngày thứ 7', 'looks_7'),
            ],
            'charts' => [
                $this->chart(
                    'report-retention',
                    'Tỷ lệ giữ chân theo nhóm đăng ký',
                    'Cohort = học viên đăng ký trong kỳ đã chọn',
                    'bar',
                    'percent',
                    ['D1', 'D7', 'Bất kỳ ngày sau'],
                    [
                        ['label' => 'Tỷ lệ giữ chân (%)', 'data' => [$d1Rate, $d7Rate, $rate], 'color' => '#0f766e'],
                    ],
                    true,
                ),
            ],
            'columns' => [
                ['key' => 'metric', 'label' => 'Chỉ số'],
                ['key' => 'value', 'label' => 'Giá trị', 'align' => 'right'],
                ['key' => 'detail', 'label' => 'Chi tiết'],
            ],
            'rows' => [
                ['metric' => 'Quy mô nhóm đăng ký (cohort)', 'value' => $cohortSize, 'detail' => 'Đăng ký trong kỳ đã chọn'],
                ['metric' => 'Giữ chân D1', 'value' => $d1Rate.'%', 'detail' => "{$d1} học viên"],
                ['metric' => 'Giữ chân D7', 'value' => $d7Rate.'%', 'detail' => "{$d7} học viên"],
                ['metric' => 'Quay lại bất kỳ ngày sau', 'value' => $rate.'%', 'detail' => "{$returned} học viên"],
            ],
        ];
    }

    /** @return array{kpis: list<ReportKpi>, charts: list<ReportChart>, columns: list<ReportColumn>, rows: list<ReportRow>} */
    private function engagementMetric(Carbon $from, Carbon $to, string $field, string $label, string $icon): array
    {
        $byDate = $this->sumDailyField($from, $to, $field);
        $total = array_sum($byDate);
        $activeDays = count(array_filter($byDate, fn (int $v): bool => $v > 0));
        $avg = $activeDays > 0 ? (int) round($total / max(1, $from->diffInDays($to) + 1)) : 0;

        $labels = [];
        $values = [];
        $rows = [];
        foreach ($this->eachDate($from, $to) as $date) {
            $key = $date->toDateString();
            $value = $byDate[$key] ?? 0;
            $labels[] = $date->format('d/m');
            $values[] = $value;
            $rows[] = ['date' => $date->format('d/m/Y'), 'value' => $value];
        }

        return [
            'kpis' => [
                $this->kpi("Tổng {$label}", number_format($total), 'Trong kỳ đã chọn', $icon),
                $this->kpi('Trung bình/ngày', number_format($avg), null, 'trending_up'),
                $this->kpi('Ngày có hoạt động', number_format($activeDays), null, 'event_available'),
            ],
            'charts' => [
                $this->chart('report-engagement', $label.' theo ngày', 'Tổng hợp toàn hệ thống', 'line', 'number', $labels, [
                    ['label' => $label, 'data' => $values, 'color' => '#0f766e'],
                ], true),
            ],
            'columns' => [
                ['key' => 'date', 'label' => 'Ngày'],
                ['key' => 'value', 'label' => $label, 'align' => 'right'],
            ],
            'rows' => array_reverse($rows),
        ];
    }

    /** @return array{kpis: list<ReportKpi>, charts: list<ReportChart>, columns: list<ReportColumn>, rows: list<ReportRow>} */
    private function engagementStudyTime(Carbon $from, Carbon $to): array
    {
        $byDate = $this->sumDailyField($from, $to, 'study_seconds');
        $totalMinutes = (int) round(array_sum($byDate) / 60);
        $days = max(1, $from->diffInDays($to) + 1);
        $avgMinutes = (int) round($totalMinutes / $days);

        $labels = [];
        $values = [];
        $rows = [];
        foreach ($this->eachDate($from, $to) as $date) {
            $key = $date->toDateString();
            $minutes = (int) round(($byDate[$key] ?? 0) / 60);
            $labels[] = $date->format('d/m');
            $values[] = $minutes;
            $rows[] = ['date' => $date->format('d/m/Y'), 'minutes' => $minutes];
        }

        return [
            'kpis' => [
                $this->kpi('Tổng phút học', number_format($totalMinutes), 'Trong kỳ đã chọn', 'schedule'),
                $this->kpi('TB phút/ngày', number_format($avgMinutes), null, 'timelapse'),
                $this->kpi('Tổng giờ', number_format((int) round($totalMinutes / 60)), null, 'hourglass'),
            ],
            'charts' => [
                $this->chart('report-study-time', 'Thời lượng học theo ngày', 'Phút học toàn hệ thống', 'line', 'number', $labels, [
                    ['label' => 'Phút', 'data' => $values, 'color' => '#7c3aed'],
                ], true),
            ],
            'columns' => [
                ['key' => 'date', 'label' => 'Ngày'],
                ['key' => 'minutes', 'label' => 'Phút học', 'align' => 'right'],
            ],
            'rows' => array_reverse($rows),
        ];
    }

    /** @return array{kpis: list<ReportKpi>, charts: list<ReportChart>, columns: list<ReportColumn>, rows: list<ReportRow>} */
    private function revenueMrr(Carbon $from, Carbon $to): array
    {
        $mrr = AdminBillingMetrics::mrrCents();
        $billing = BillingSubscriptionStats::overview();
        $arpu = $billing['premium_students'] > 0
            ? (int) round($mrr / $billing['premium_students'])
            : 0;

        $revenueInRange = (int) Payment::query()
            ->where('status', 'succeeded')
            ->whereBetween('paid_at', [$from, $to])
            ->sum('amount_cents');

        $series = $this->revenueByDay($from, $to);
        $labels = [];
        $values = [];
        $rows = [];
        foreach ($this->eachDate($from, $to) as $date) {
            $key = $date->toDateString();
            $value = $series[$key] ?? 0;
            $labels[] = $date->format('d/m');
            $values[] = $value;
            $rows[] = [
                'date' => $date->format('d/m/Y'),
                'revenue' => MoneyFormatter::vnd($value),
                'amount_cents' => $value,
            ];
        }

        $skuRows = [];
        $premiumPlan = Plan::query()->where('slug', 'premium')->with(['prices' => fn ($q) => $q->ordered()])->first();
        if ($premiumPlan !== null) {
            foreach (BillingSubscriptionStats::premiumSkuBreakdown($premiumPlan) as $row) {
                /** @var \Modules\Billing\Models\PlanPrice $price */
                $price = $row['price'];
                $skuRows[] = [
                    'sku' => $price->label,
                    'active' => $row['active_users'],
                    'share' => $row['share_percent'].'%',
                    'price' => MoneyFormatter::vnd((int) $price->price_cents),
                ];
            }
        }

        return [
            'kpis' => [
                $this->kpi('MRR (ước tính)', AdminBillingMetrics::formatCompactVnd($mrr), 'Học viên Premium đang dùng × giá quy đổi tháng', 'trending_up'),
                $this->kpi('Doanh thu kỳ', AdminBillingMetrics::formatCompactVnd($revenueInRange), 'Thanh toán thành công trong kỳ', 'payments'),
                $this->kpi('ARPU', MoneyFormatter::vnd($arpu), 'MRR chia cho số học viên Premium', 'person'),
                $this->kpi('Premium đang dùng', number_format($billing['premium_students']), 'Học viên đang có gói Premium', 'workspace_premium'),
            ],
            'charts' => [
                $this->chart('report-revenue', 'Doanh thu theo ngày', 'Thanh toán thành công', 'bar', 'vnd', $labels, [
                    ['label' => 'Doanh thu (₫)', 'data' => $values, 'color' => '#0f766e'],
                ], true),
            ],
            'columns' => $skuRows !== []
                ? [
                    ['key' => 'sku', 'label' => 'SKU'],
                    ['key' => 'active', 'label' => 'Đang dùng', 'align' => 'right'],
                    ['key' => 'share', 'label' => 'Tỷ trọng', 'align' => 'right'],
                    ['key' => 'price', 'label' => 'Giá', 'align' => 'right'],
                ]
                : [
                    ['key' => 'date', 'label' => 'Ngày'],
                    ['key' => 'revenue', 'label' => 'Doanh thu', 'align' => 'right'],
                ],
            'rows' => $skuRows !== [] ? $skuRows : array_reverse($rows),
        ];
    }

    /** @return array{kpis: list<ReportKpi>, charts: list<ReportChart>, columns: list<ReportColumn>, rows: list<ReportRow>} */
    private function revenueChurn(Carbon $from, Carbon $to): array
    {
        $premiumPlanId = Plan::query()->where('slug', 'premium')->value('id');
        $active = BillingSubscriptionStats::countPremiumStudents($premiumPlanId !== null ? (int) $premiumPlanId : null);
        $expiring = BillingSubscriptionStats::overview()['expiring_premium_students'];

        $expiredInRange = 0;
        $canceledInRange = 0;
        if ($premiumPlanId !== null) {
            $expiredInRange = (int) Subscription::query()
                ->forStudents()
                ->where('plan_id', $premiumPlanId)
                ->whereNotNull('ends_at')
                ->whereBetween('ends_at', [$from, $to])
                ->where(function ($q): void {
                    $q->where('status', '!=', 'active')
                        ->orWhere('ends_at', '<=', now());
                })
                ->distinct('user_id')
                ->count('user_id');

            $canceledInRange = (int) Subscription::query()
                ->forStudents()
                ->where('plan_id', $premiumPlanId)
                ->whereNotNull('canceled_at')
                ->whereBetween('canceled_at', [$from, $to])
                ->distinct('user_id')
                ->count('user_id');
        }

        $churnDenom = max(1, $active + $expiredInRange);
        $churnRate = round($expiredInRange / $churnDenom * 100, 1);

        return [
            'kpis' => [
                $this->kpi('Churn (ước tính)', $churnRate.'%', 'Hết hạn trong kỳ / (đang dùng + hết hạn)', 'trending_down'),
                $this->kpi('Hết hạn trong kỳ', number_format($expiredInRange), null, 'event_busy'),
                $this->kpi('Hủy trong kỳ', number_format($canceledInRange), null, 'cancel'),
                $this->kpi('Sắp hết hạn 30 ngày', number_format($expiring), 'Nguy cơ không gia hạn', 'schedule'),
            ],
            'charts' => [
                $this->chart('report-churn', 'Rời bỏ Premium', 'Số lượng trong kỳ', 'bar', 'number', ['Hết hạn', 'Hủy', 'Sắp hết hạn'], [
                    ['label' => 'Số học viên', 'data' => [$expiredInRange, $canceledInRange, $expiring], 'color' => '#dc2626'],
                ], true),
            ],
            'columns' => [
                ['key' => 'metric', 'label' => 'Chỉ số'],
                ['key' => 'value', 'label' => 'Giá trị', 'align' => 'right'],
            ],
            'rows' => [
                ['metric' => 'Premium đang dùng', 'value' => $active],
                ['metric' => 'Hết hạn trong kỳ', 'value' => $expiredInRange],
                ['metric' => 'Hủy trong kỳ', 'value' => $canceledInRange],
                ['metric' => 'Sắp hết hạn (30 ngày)', 'value' => $expiring],
                ['metric' => 'Churn ước tính', 'value' => $churnRate.'%'],
            ],
        ];
    }

    /** @return array{kpis: list<ReportKpi>, charts: list<ReportChart>, columns: list<ReportColumn>, rows: list<ReportRow>} */
    private function revenueFunnel(): array
    {
        $billing = BillingSubscriptionStats::overview();
        $total = max(1, $billing['total_students']);
        $conversion = round($billing['premium_students'] / $total * 100, 1);

        $bySource = Subscription::query()
            ->forStudents()
            ->active()
            ->select('source', DB::raw('count(distinct user_id) as aggregate'))
            ->groupBy('source')
            ->pluck('aggregate', 'source')
            ->map(fn ($v): int => (int) $v)
            ->all();

        $rows = [];
        $labels = [];
        $values = [];
        foreach ($bySource as $source => $count) {
            $label = BillingSubscriptionStats::sourceLabel((string) $source);
            $labels[] = $label;
            $values[] = $count;
            $rows[] = [
                'source' => $label,
                'users' => $count,
                'share' => $billing['premium_students'] > 0
                    ? round($count / $billing['premium_students'] * 100, 1).'%'
                    : '0%',
            ];
        }

        return [
            'kpis' => [
                $this->kpi('Học viên Free', number_format($billing['free_students']), null, 'person'),
                $this->kpi('Học viên Premium', number_format($billing['premium_students']), null, 'workspace_premium'),
                $this->kpi('Tỷ lệ chuyển đổi', $conversion.'%', 'Premium / Tổng học viên', 'filter_alt'),
            ],
            'charts' => [
                $this->chart('report-funnel', 'Phễu Free → Premium', 'Số học viên hiện tại', 'bar', 'number', ['Free', 'Premium'], [
                    ['label' => 'Học viên', 'data' => [$billing['free_students'], $billing['premium_students']], 'color' => '#0f766e'],
                ]),
                $this->chart('report-funnel-source', 'Premium theo nguồn', 'Gói đang dùng theo nguồn kích hoạt', 'bar', 'number', $labels, [
                    ['label' => 'Học viên', 'data' => $values, 'color' => '#0891b2'],
                ]),
            ],
            'columns' => [
                ['key' => 'source', 'label' => 'Nguồn'],
                ['key' => 'users', 'label' => 'Đang dùng', 'align' => 'right'],
                ['key' => 'share', 'label' => 'Tỷ trọng', 'align' => 'right'],
            ],
            'rows' => $rows,
        ];
    }

    /** @return array{kpis: list<ReportKpi>, charts: list<ReportChart>, columns: list<ReportColumn>, rows: list<ReportRow>} */
    private function contentAccuracy(): array
    {
        $rows = TopicMastery::query()
            ->select('medical_taxonomy_node_id')
            ->selectRaw('SUM(attempts) as attempts_total')
            ->selectRaw('SUM(correct) as correct_total')
            ->groupBy('medical_taxonomy_node_id')
            ->havingRaw('SUM(attempts) > 0')
            ->orderByDesc('attempts_total')
            ->limit(30)
            ->with('medicalTaxonomyNode:id,name')
            ->get();

        $totalAttempts = (int) $rows->sum('attempts_total');
        $totalCorrect = (int) $rows->sum('correct_total');
        $avgRate = $totalAttempts > 0 ? round($totalCorrect / $totalAttempts * 100, 1) : 0.0;

        $table = [];
        $labels = [];
        $values = [];
        foreach ($rows->take(12) as $row) {
            $name = $row->medicalTaxonomyNode?->name ?? '#'.$row->medical_taxonomy_node_id;
            $rate = round(((int) $row->correct_total) / max(1, (int) $row->attempts_total) * 100, 1);
            $labels[] = mb_strimwidth($name, 0, 18, '…');
            $values[] = $rate;
            $table[] = [
                'topic' => $name,
                'attempts' => (int) $row->attempts_total,
                'correct' => (int) $row->correct_total,
                'rate' => $rate.'%',
            ];
        }

        return [
            'kpis' => [
                $this->kpi('Tỷ lệ đúng TB', $avgRate.'%', 'Trên các topic có dữ liệu', 'analytics'),
                $this->kpi('Tổng lượt làm', number_format($totalAttempts), null, 'quiz'),
                $this->kpi('Topics có data', number_format($rows->count()), 'Top theo attempts', 'category'),
            ],
            'charts' => [
                $this->chart('report-accuracy', 'Tỷ lệ đúng theo chủ đề', 'Top 12 theo số lượt làm', 'bar', 'percent', $labels, [
                    ['label' => 'Tỷ lệ đúng (%)', 'data' => $values, 'color' => '#0f766e'],
                ], true),
            ],
            'columns' => [
                ['key' => 'topic', 'label' => 'Chủ đề'],
                ['key' => 'attempts', 'label' => 'Lượt làm', 'align' => 'right'],
                ['key' => 'correct', 'label' => 'Trả lời đúng', 'align' => 'right'],
                ['key' => 'rate', 'label' => 'Tỷ lệ đúng', 'align' => 'right'],
            ],
            'rows' => $table,
            'empty_message' => $table === [] ? 'Chưa có dữ liệu mức độ nắm chủ đề (mastery).' : null,
        ];
    }

    /** @return array{kpis: list<ReportKpi>, charts: list<ReportChart>, columns: list<ReportColumn>, rows: list<ReportRow>} */
    private function contentFlags(Carbon $from, Carbon $to): array
    {
        $query = QuestionFeedback::query()->whereBetween('created_at', [$from, $to]);
        $total = (int) (clone $query)->count();
        $pending = (int) (clone $query)->whereIn('status', [
            QuestionFeedback::STATUS_PENDING,
            QuestionFeedback::STATUS_REVIEWING,
        ])->count();
        $resolved = (int) (clone $query)->where('status', QuestionFeedback::STATUS_RESOLVED)->count();

        $byCategory = (clone $query)
            ->select('category', DB::raw('count(*) as aggregate'))
            ->groupBy('category')
            ->pluck('aggregate', 'category')
            ->map(fn ($v): int => (int) $v)
            ->all();

        $labels = [];
        $values = [];
        $rows = [];
        $categoryLabels = QuestionFeedback::categoryLabels();
        foreach ($byCategory as $category => $count) {
            $label = $categoryLabels[$category] ?? (string) $category;
            $labels[] = mb_strimwidth($label, 0, 20, '…');
            $values[] = $count;
            $rows[] = [
                'category' => $label,
                'count' => $count,
                'share' => $total > 0 ? round($count / $total * 100, 1).'%' : '0%',
            ];
        }

        return [
            'kpis' => [
                $this->kpi('Feedback trong kỳ', number_format($total), null, 'flag'),
                $this->kpi('Đang chờ', number_format($pending), 'Chờ xử lý + đang xem xét', 'pending'),
                $this->kpi('Đã xử lý', number_format($resolved), null, 'task_alt'),
            ],
            'charts' => [
                $this->chart('report-flags', 'Phản hồi theo lý do', 'Trong kỳ đã chọn', 'bar', 'number', $labels, [
                    ['label' => 'Số phản hồi', 'data' => $values, 'color' => '#d97706'],
                ], true),
            ],
            'columns' => [
                ['key' => 'category', 'label' => 'Lý do'],
                ['key' => 'count', 'label' => 'Số lượng', 'align' => 'right'],
                ['key' => 'share', 'label' => 'Tỷ trọng', 'align' => 'right'],
            ],
            'rows' => $rows,
            'empty_message' => $rows === [] ? 'Không có phản hồi trong kỳ đã chọn.' : null,
        ];
    }

    /** @return array{kpis: list<ReportKpi>, charts: list<ReportChart>, columns: list<ReportColumn>, rows: list<ReportRow>} */
    private function contentCoverage(): array
    {
        $byStatus = Question::query()
            ->select('status', DB::raw('count(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($v): int => (int) $v)
            ->all();

        $total = array_sum($byStatus);
        $published = (int) ($byStatus[QuestionStatus::Published->value] ?? 0);

        $labels = [];
        $values = [];
        $rows = [];
        foreach (QuestionStatus::cases() as $status) {
            $count = (int) ($byStatus[$status->value] ?? 0);
            if ($count === 0 && ! in_array($status, [QuestionStatus::Published, QuestionStatus::Draft, QuestionStatus::InReview], true)) {
                continue;
            }
            $labels[] = $status->label();
            $values[] = $count;
            $rows[] = [
                'status' => $status->label(),
                'count' => $count,
                'share' => $total > 0 ? round($count / $total * 100, 1).'%' : '0%',
            ];
        }

        return [
            'kpis' => [
                $this->kpi('Tổng câu hỏi', number_format($total), null, 'quiz'),
                $this->kpi('Đã xuất bản', number_format($published), null, 'published_with_changes'),
                $this->kpi('Tỷ lệ published', ($total > 0 ? round($published / $total * 100, 1) : 0).'%', null, 'pie_chart'),
            ],
            'charts' => [
                $this->chart('report-coverage', 'Phân bổ theo trạng thái', 'Toàn bộ ngân hàng câu hỏi', 'bar', 'number', $labels, [
                    ['label' => 'Số câu', 'data' => $values, 'color' => '#0f766e'],
                ], true),
            ],
            'columns' => [
                ['key' => 'status', 'label' => 'Trạng thái'],
                ['key' => 'count', 'label' => 'Số câu', 'align' => 'right'],
                ['key' => 'share', 'label' => 'Tỷ trọng', 'align' => 'right'],
            ],
            'rows' => $rows,
        ];
    }

    /** @return array{kpis: list<ReportKpi>, charts: list<ReportChart>, columns: list<ReportColumn>, rows: list<ReportRow>} */
    private function learningMastery(): array
    {
        $avgMastery = (float) (TopicMastery::query()->avg('mastery_level') ?? 0);
        $avgRate = (float) (TopicMastery::query()->avg('correct_rate') ?? 0);
        $learners = (int) TopicMastery::query()->distinct('user_id')->count('user_id');
        $records = (int) TopicMastery::query()->count();

        $distribution = TopicMastery::query()
            ->select('mastery_level', DB::raw('count(*) as aggregate'))
            ->groupBy('mastery_level')
            ->orderBy('mastery_level')
            ->pluck('aggregate', 'mastery_level')
            ->map(fn ($v): int => (int) $v)
            ->all();

        $labels = [];
        $values = [];
        $rows = [];
        foreach ($distribution as $level => $count) {
            $labels[] = 'Level '.$level;
            $values[] = $count;
            $rows[] = [
                'level' => (string) $level,
                'count' => $count,
                'share' => $records > 0 ? round($count / $records * 100, 1).'%' : '0%',
            ];
        }

        return [
            'kpis' => [
                $this->kpi('Mastery trung bình', number_format($avgMastery, 1), 'Mức độ nắm chủ đề trung bình', 'school'),
                $this->kpi('Tỷ lệ đúng TB', number_format($avgRate, 1).'%', 'Trung bình tỷ lệ trả lời đúng', 'analytics'),
                $this->kpi('Học viên có dữ liệu', number_format($learners), null, 'group'),
            ],
            'charts' => [
                $this->chart('report-mastery', 'Phân phối mức mastery', 'Số bản ghi theo mức độ nắm chủ đề', 'bar', 'number', $labels, [
                    ['label' => 'Số bản ghi', 'data' => $values, 'color' => '#0f766e'],
                ], true),
            ],
            'columns' => [
                ['key' => 'level', 'label' => 'Mức mastery'],
                ['key' => 'count', 'label' => 'Số bản ghi', 'align' => 'right'],
                ['key' => 'share', 'label' => 'Tỷ trọng', 'align' => 'right'],
            ],
            'rows' => $rows,
            'empty_message' => $rows === [] ? 'Chưa có dữ liệu mức độ nắm chủ đề (mastery).' : null,
        ];
    }

    /** @return array{kpis: list<ReportKpi>, charts: list<ReportChart>, columns: list<ReportColumn>, rows: list<ReportRow>, empty_message: string} */
    private function learningExamScoresUnavailable(): array
    {
        return [
            'kpis' => [],
            'charts' => [],
            'columns' => [],
            'rows' => [],
            'empty_message' => 'Báo cáo điểm thi thử sẽ có khi dữ liệu thi được tổng hợp hệ thống. Hiện có thể xem mức độ nắm chủ đề (mastery) hoặc chủ đề yếu.',
        ];
    }

    /** @return array{kpis: list<ReportKpi>, charts: list<ReportChart>, columns: list<ReportColumn>, rows: list<ReportRow>} */
    private function learningWeakTopics(): array
    {
        $rows = TopicMastery::query()
            ->select('medical_taxonomy_node_id')
            ->selectRaw('SUM(attempts) as attempts_total')
            ->selectRaw('SUM(correct) as correct_total')
            ->selectRaw('AVG(mastery_level) as mastery_avg')
            ->groupBy('medical_taxonomy_node_id')
            ->havingRaw('SUM(attempts) >= 5')
            ->orderByRaw('(SUM(correct) * 1.0 / SUM(attempts)) asc')
            ->limit(20)
            ->with('medicalTaxonomyNode:id,name')
            ->get();

        $table = [];
        $labels = [];
        $values = [];
        foreach ($rows as $row) {
            $name = $row->medicalTaxonomyNode?->name ?? '#'.$row->medical_taxonomy_node_id;
            $rate = round(((int) $row->correct_total) / max(1, (int) $row->attempts_total) * 100, 1);
            $labels[] = mb_strimwidth($name, 0, 18, '…');
            $values[] = $rate;
            $table[] = [
                'topic' => $name,
                'attempts' => (int) $row->attempts_total,
                'rate' => $rate.'%',
                'mastery' => number_format((float) $row->mastery_avg, 1),
            ];
        }

        return [
            'kpis' => [
                $this->kpi('Chủ đề yếu', number_format(count($table)), 'Từ 5 lượt làm trở lên, tỷ lệ đúng thấp nhất', 'warning'),
                $this->kpi('Tỷ lệ đúng thấp nhất', ($table[0]['rate'] ?? '—'), null, 'trending_down'),
            ],
            'charts' => [
                $this->chart('report-weak-topics', 'Top chủ đề yếu', 'Tỷ lệ đúng thấp nhất', 'bar', 'percent', $labels, [
                    ['label' => 'Tỷ lệ đúng (%)', 'data' => $values, 'color' => '#dc2626'],
                ], true),
            ],
            'columns' => [
                ['key' => 'topic', 'label' => 'Chủ đề'],
                ['key' => 'attempts', 'label' => 'Lượt làm', 'align' => 'right'],
                ['key' => 'rate', 'label' => 'Tỷ lệ đúng', 'align' => 'right'],
                ['key' => 'mastery', 'label' => 'Mastery TB', 'align' => 'right'],
            ],
            'rows' => $table,
            'empty_message' => $table === [] ? 'Chưa đủ dữ liệu để xác định chủ đề yếu.' : null,
        ];
    }

    /** @return array<string, int> */
    private function dailyActiveUsers(Carbon $from, Carbon $to): array
    {
        return DailyLearningStat::query()
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString())
            ->where(fn ($q) => $q->where('questions_answered', '>', 0)->orWhere('study_seconds', '>', 0))
            ->select('date', DB::raw('COUNT(DISTINCT user_id) as aggregate'))
            ->groupBy('date')
            ->get()
            ->mapWithKeys(fn ($row): array => [Carbon::parse($row->date)->toDateString() => (int) $row->aggregate])
            ->all();
    }

    /** @return array<string, int> */
    private function studentSignupsByDate(Carbon $from, Carbon $to): array
    {
        return User::role(Role::Student->value)
            ->whereBetween('created_at', [$from, $to])
            ->get(['created_at'])
            ->groupBy(fn (User $user): string => $user->created_at->toDateString())
            ->map(fn (Collection $group): int => $group->count())
            ->all();
    }

    /** @return array<string, int> */
    private function sumDailyField(Carbon $from, Carbon $to, string $field): array
    {
        $allowed = ['questions_answered', 'correct_answers', 'study_seconds', 'completed_sessions'];
        if (! in_array($field, $allowed, true)) {
            return [];
        }

        return DailyLearningStat::query()
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString())
            ->select('date', DB::raw("SUM({$field}) as aggregate"))
            ->groupBy('date')
            ->get()
            ->mapWithKeys(fn ($row): array => [Carbon::parse($row->date)->toDateString() => (int) $row->aggregate])
            ->all();
    }

    /** @return array<string, int> */
    private function revenueByDay(Carbon $from, Carbon $to): array
    {
        return Payment::query()
            ->where('status', 'succeeded')
            ->whereBetween('paid_at', [$from, $to])
            ->get(['paid_at', 'amount_cents'])
            ->groupBy(fn (Payment $payment): string => $payment->paid_at?->toDateString() ?? 'unknown')
            ->map(fn (Collection $group): int => (int) $group->sum('amount_cents'))
            ->all();
    }

    /** @return \Generator<int, Carbon> */
    private function eachDate(Carbon $from, Carbon $to): \Generator
    {
        $cursor = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();
        while ($cursor->lte($end)) {
            yield $cursor->copy();
            $cursor->addDay();
        }
    }

    private function percentDelta(int|float $current, int|float $previous): ?float
    {
        if ($previous == 0) {
            return $current > 0 ? 100.0 : null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    /** @return ReportKpi */
    private function kpi(string $label, string $value, ?string $hint, string $icon, ?float $delta = null): array
    {
        return [
            'label' => $label,
            'value' => $value,
            'hint' => $hint,
            'icon' => $icon,
            'delta' => $delta,
        ];
    }

    /**
     * @param  list<string>  $labels
     * @param  list<array{label: string, data: list<int|float>, color: string}>  $datasets
     * @return ReportChart
     */
    private function chart(
        string $id,
        string $title,
        string $subtitle,
        string $type,
        string $format,
        array $labels,
        array $datasets,
        bool $fullWidth = false,
    ): array {
        return [
            'id' => $id,
            'title' => $title,
            'subtitle' => $subtitle,
            'type' => $type,
            'format' => $format,
            'labels' => $labels,
            'datasets' => $datasets,
            'full_width' => $fullWidth,
        ];
    }
}
