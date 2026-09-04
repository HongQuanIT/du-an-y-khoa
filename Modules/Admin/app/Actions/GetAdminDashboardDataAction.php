<?php

declare(strict_types=1);

namespace Modules\Admin\Actions;

use App\Models\SupportConversation;
use App\Models\User;
use App\Support\Concerns\AsAction;
use App\Support\Enums\Permission;
use App\Support\Enums\Role;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Modules\Admin\Models\AuditLog;
use Modules\Admin\Models\ContactInquiry;
use Modules\Admin\Support\AdminReportCache;
use Modules\Admin\Support\QuestionAccess;
use Modules\Analytics\Models\DailyLearningStat;
use Modules\Billing\Models\Payment;
use Modules\Billing\Support\AdminBillingMetrics;
use Modules\Billing\Support\BillingSubscriptionStats;
use Modules\Classroom\Enums\ClassroomStatus;
use Modules\Classroom\Models\Classroom;
use Modules\Partner\Enums\PayoutStatus;
use Modules\Partner\Models\PartnerPayout;
use Modules\QuestionBank\Enums\QuestionReviewStatus;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionFeedback;
use Modules\QuestionBank\Models\QuestionReviewRequest;

/**
 * @phpstan-type KpiCard array{
 *     label: string,
 *     value: string,
 *     hint: ?string,
 *     icon: string,
 *     delta: ?float,
 *     delta_suffix: string,
 *     delta_mode: 'percent'|'absolute',
 *     href: ?string,
 *     severity: ?string,
 * }
 * @phpstan-type DashboardAlert array{
 *     id: string,
 *     category: string,
 *     severity: 'critical'|'warning'|'info'|'ok',
 *     title: string,
 *     message: string,
 *     href: ?string,
 * }
 * @phpstan-type QuickAction array{
 *     label: string,
 *     icon: string,
 *     href: string,
 * }
 * @phpstan-type AuditFeedItem array{
 *     id: int,
 *     actor_name: ?string,
 *     action_label: string,
 *     subject_label: ?string,
 *     subject_href: ?string,
 *     occurred_at: Carbon,
 *     href: string,
 * }
 * @phpstan-type DashboardChart array{
 *     id: string,
 *     title: string,
 *     subtitle: string,
 *     type: 'line'|'bar',
 *     format?: 'number'|'vnd'|'percent',
 *     labels: list<string>,
 *     datasets: list<array{label: string, data: list<int|float>, color: string}>,
 *     full_width?: bool,
 * }
 * @phpstan-type DashboardData array{
 *     refreshed_at: Carbon,
 *     kpis: list<KpiCard>,
 *     charts: list<DashboardChart>,
 *     alerts: list<DashboardAlert>,
 *     audit_feed: list<AuditFeedItem>,
 *     quick_actions: list<QuickAction>,
 * }
 */
final class GetAdminDashboardDataAction
{
    use AsAction;

    private const CACHE_KEY = 'admin:dashboard:aggregates';

    private const CACHE_TTL_SECONDS = 900;

    /** @return DashboardData */
    public function handle(User $viewer): array
    {
        $aggregates = Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, fn (): array => $this->aggregateMetrics());

        return [
            'refreshed_at' => Carbon::parse($aggregates['refreshed_at']),
            'kpis' => $this->buildKpis($viewer, $aggregates),
            'charts' => $this->buildCharts($viewer, $aggregates),
            'alerts' => $this->buildAlerts($viewer, $aggregates),
            'audit_feed' => $this->buildAuditFeed($viewer),
            'quick_actions' => $this->buildQuickActions($viewer),
        ];
    }

    /** @return array<string, mixed> */
    private function aggregateMetrics(): array
    {
        $today = Carbon::today();
        $yesterday = $today->copy()->subDay();

        $activeScope = fn ($query) => $query->where(function ($builder): void {
            $builder->where('questions_answered', '>', 0)
                ->orWhere('study_seconds', '>', 0);
        });

        $dauToday = (int) DailyLearningStat::query()
            ->whereDate('date', $today)
            ->tap($activeScope)
            ->distinct('user_id')
            ->count('user_id');

        $dauYesterday = (int) DailyLearningStat::query()
            ->whereDate('date', $yesterday)
            ->tap($activeScope)
            ->distinct('user_id')
            ->count('user_id');

        $mauCurrentStart = $today->copy()->subDays(29);
        $mauPreviousStart = $today->copy()->subDays(59);
        $mauPreviousEnd = $today->copy()->subDays(30);

        $mauCurrent = (int) DailyLearningStat::query()
            ->whereDate('date', '>=', $mauCurrentStart)
            ->tap($activeScope)
            ->distinct('user_id')
            ->count('user_id');

        $mauPrevious = (int) DailyLearningStat::query()
            ->whereDate('date', '>=', $mauPreviousStart)
            ->whereDate('date', '<', $mauPreviousEnd)
            ->tap($activeScope)
            ->distinct('user_id')
            ->count('user_id');

        $signupsCurrent = (int) User::role(Role::Student->value)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        $signupsPrevious = (int) User::role(Role::Student->value)
            ->whereBetween('created_at', [now()->subDays(14), now()->subDays(7)])
            ->count();

        $signupsToday = (int) User::role(Role::Student->value)
            ->whereDate('created_at', $today)
            ->count();

        $billing = BillingSubscriptionStats::overview();

        $failedPayments24h = (int) Payment::query()
            ->where('status', 'failed')
            ->where('created_at', '>=', now()->subDay())
            ->count();

        $succeededPayments24h = (int) Payment::query()
            ->where('status', 'succeeded')
            ->where('created_at', '>=', now()->subDay())
            ->count();

        $failedPaymentsDailyAvg = (int) round(
            Payment::query()
                ->where('status', 'failed')
                ->where('created_at', '>=', now()->subDays(7))
                ->where('created_at', '<', now()->subDay())
                ->count() / 6,
        );

        $failedJobs24h = 0;
        if (Schema::hasTable('failed_jobs')) {
            $failedJobs24h = (int) DB::table('failed_jobs')
                ->where('failed_at', '>=', now()->subDay())
                ->count();
        }

        return [
            'refreshed_at' => now()->toIso8601String(),
            'dau_today' => $dauToday,
            'dau_delta' => $this->percentDelta($dauToday, $dauYesterday),
            'mau_current' => $mauCurrent,
            'mau_delta' => $this->percentDelta($mauCurrent, $mauPrevious),
            'signups_7d' => $signupsCurrent,
            'signups_7d_delta' => $this->percentDelta($signupsCurrent, $signupsPrevious),
            'signups_today' => $signupsToday,
            'billing' => $billing,
            'questions_published' => (int) Question::query()->where('status', QuestionStatus::Published)->count(),
            'questions_in_review' => (int) Question::query()->where('status', QuestionStatus::InReview)->count(),
            'feedback_pending' => (int) QuestionFeedback::query()
                ->whereIn('status', [QuestionFeedback::STATUS_PENDING, QuestionFeedback::STATUS_REVIEWING])
                ->count(),
            'question_reviews_pending' => (int) QuestionReviewRequest::query()
                ->where('status', QuestionReviewStatus::Pending->value)
                ->count(),
            'contacts_new' => ContactInquiry::newCount(),
            'classrooms_pending' => (int) Classroom::query()
                ->where('status', ClassroomStatus::PendingApproval->value)
                ->count(),
            'partner_payouts_pending' => (int) PartnerPayout::query()
                ->where('status', PayoutStatus::Approved)
                ->count(),
            'failed_payments_24h' => $failedPayments24h,
            'succeeded_payments_24h' => $succeededPayments24h,
            'failed_payments_daily_avg' => $failedPaymentsDailyAvg,
            'failed_jobs_24h' => $failedJobs24h,
            'billing_metrics' => [
                'mrr_cents' => AdminBillingMetrics::mrrCents(),
                'revenue_month_cents' => AdminBillingMetrics::revenueMonthCents(),
                'revenue_month_delta' => AdminBillingMetrics::revenueMonthDeltaPercent(),
            ],
            'chart_series' => [
                'user_growth' => $this->userGrowthSeries(30),
                'revenue' => AdminBillingMetrics::monthlyRevenueSeries(6),
                'engagement' => $this->engagementSeries(30),
            ],
        ];
    }

    /** @return list<array{label: string, dau: int, signups: int}> */
    private function userGrowthSeries(int $days): array
    {
        $start = Carbon::today()->subDays($days - 1);

        /** @var array<string, int> $dauByDate */
        $dauByDate = DailyLearningStat::query()
            ->whereDate('date', '>=', $start)
            ->where(function ($builder): void {
                $builder->where('questions_answered', '>', 0)
                    ->orWhere('study_seconds', '>', 0);
            })
            ->select('date', DB::raw('COUNT(DISTINCT user_id) as aggregate'))
            ->groupBy('date')
            ->pluck('aggregate', 'date')
            ->map(fn ($count): int => (int) $count)
            ->all();

        /** @var array<string, int> $signupsByDate */
        $signupsByDate = User::role(Role::Student->value)
            ->where('created_at', '>=', $start->startOfDay())
            ->selectRaw('DATE(created_at) as signup_date, COUNT(*) as aggregate')
            ->groupBy('signup_date')
            ->pluck('aggregate', 'signup_date')
            ->map(fn ($count): int => (int) $count)
            ->all();

        $series = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $start->copy()->addDays($i);
            $key = $date->toDateString();
            $series[] = [
                'label' => $date->format('d/m'),
                'dau' => $dauByDate[$key] ?? 0,
                'signups' => $signupsByDate[$key] ?? 0,
            ];
        }

        return $series;
    }

    /** @return list<array{label: string, questions: int, sessions: int}> */
    private function engagementSeries(int $days): array
    {
        $start = Carbon::today()->subDays($days - 1);

        $rows = DailyLearningStat::query()
            ->whereDate('date', '>=', $start)
            ->select('date')
            ->selectRaw('SUM(questions_answered) as questions_total')
            ->selectRaw('SUM(completed_sessions) as sessions_total')
            ->groupBy('date')
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->date)->toDateString());

        $series = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $start->copy()->addDays($i);
            $key = $date->toDateString();
            $row = $rows->get($key);
            $series[] = [
                'label' => $date->format('d/m'),
                'questions' => $row ? (int) $row->questions_total : 0,
                'sessions' => $row ? (int) $row->sessions_total : 0,
            ];
        }

        return $series;
    }

    /**
     * @param  array<string, mixed>  $aggregates
     * @return list<DashboardChart>
     */
    private function buildCharts(User $viewer, array $aggregates): array
    {
        $charts = [];
        /** @var array<string, mixed> $chartSeries */
        $chartSeries = $aggregates['chart_series'];

        if ($viewer->can(Permission::UserView->value)) {
            /** @var list<array{label: string, dau: int, signups: int}> $userGrowth */
            $userGrowth = $chartSeries['user_growth'];
            $charts[] = [
                'id' => 'chart-user-growth',
                'title' => 'Tăng trưởng người dùng',
                'subtitle' => '30 ngày qua · DAU và đăng ký mới',
                'type' => 'line',
                'format' => 'number',
                'labels' => array_column($userGrowth, 'label'),
                'datasets' => [
                    [
                        'label' => 'DAU',
                        'data' => array_column($userGrowth, 'dau'),
                        'color' => '#0f766e',
                    ],
                    [
                        'label' => 'Đăng ký mới',
                        'data' => array_column($userGrowth, 'signups'),
                        'color' => '#0891b2',
                    ],
                ],
            ];

        }

        if ($viewer->can(Permission::BillingManage->value)) {
            /** @var list<array{label: string, value: int}> $revenue */
            $revenue = $chartSeries['revenue'];
            $charts[] = [
                'id' => 'chart-revenue',
                'title' => 'Doanh thu theo tháng',
                'subtitle' => '6 tháng gần nhất · Thanh toán thành công',
                'type' => 'bar',
                'format' => 'vnd',
                'labels' => array_column($revenue, 'label'),
                'datasets' => [
                    [
                        'label' => 'Doanh thu (₫)',
                        'data' => array_column($revenue, 'value'),
                        'color' => '#0f766e',
                    ],
                ],
            ];
        }

        if ($viewer->can(Permission::UserView->value)) {
            /** @var list<array{label: string, questions: int, sessions: int}> $engagement */
            $engagement = $chartSeries['engagement'];
            $charts[] = [
                'id' => 'chart-engagement',
                'title' => 'Mức độ tương tác',
                'subtitle' => '30 ngày qua · Câu đã làm và phiên hoàn thành',
                'type' => 'line',
                'format' => 'number',
                'labels' => array_column($engagement, 'label'),
                'datasets' => [
                    [
                        'label' => 'Câu đã làm',
                        'data' => array_column($engagement, 'questions'),
                        'color' => '#0f766e',
                    ],
                    [
                        'label' => 'Phiên hoàn thành',
                        'data' => array_column($engagement, 'sessions'),
                        'color' => '#7c3aed',
                    ],
                ],
                'full_width' => true,
            ];
        }

        return $charts;
    }

    /**
     * @param  array<string, mixed>  $aggregates
     * @return list<KpiCard>
     */
    private function buildKpis(User $viewer, array $aggregates): array
    {
        $kpis = [];

        if ($viewer->can(Permission::UserView->value)) {
            $kpis[] = $this->kpi(
                label: 'DAU',
                value: number_format((int) $aggregates['dau_today']),
                hint: 'Người học có hoạt động hôm nay',
                icon: 'groups',
                delta: $aggregates['dau_delta'],
            );

            $kpis[] = $this->kpi(
                label: 'MAU',
                value: number_format((int) $aggregates['mau_current']),
                hint: 'Người học active 30 ngày qua',
                icon: 'group',
                delta: $aggregates['mau_delta'],
            );

            $kpis[] = $this->kpi(
                label: 'Đăng ký mới (7 ngày)',
                value: number_format((int) $aggregates['signups_7d']),
                hint: 'Học viên mới · hôm nay: '.number_format((int) $aggregates['signups_today']),
                icon: 'person_add',
                delta: $aggregates['signups_7d_delta'],
                href: $this->routeIfExists('admin.users.index'),
            );
        }

        if ($viewer->can(Permission::BillingManage->value)) {
            /** @var array{total_students: int, premium_students: int, free_students: int, expiring_premium_students: int} $billing */
            $billing = $aggregates['billing'];
            /** @var array{mrr_cents: int, revenue_month_cents: int, revenue_month_delta: ?float} $billingMetrics */
            $billingMetrics = $aggregates['billing_metrics'];

            $kpis[] = $this->kpi(
                label: 'MRR (ước tính)',
                value: AdminBillingMetrics::formatCompactVnd($billingMetrics['mrr_cents']),
                hint: 'Tổng doanh thu định kỳ tháng từ Premium đang dùng',
                icon: 'trending_up',
                href: $this->routeIfExists('admin.reports.show-category', ['category' => 'revenue']),
            );

            $kpis[] = $this->kpi(
                label: 'Doanh thu tháng',
                value: AdminBillingMetrics::formatCompactVnd($billingMetrics['revenue_month_cents']),
                hint: 'Thanh toán thành công · '.now()->translatedFormat('F Y'),
                icon: 'payments',
                delta: $billingMetrics['revenue_month_delta'],
                href: $this->routeIfExists('admin.billing.payments.index'),
            );

            $kpis[] = $this->kpi(
                label: 'Học viên Premium',
                value: number_format($billing['premium_students']),
                hint: 'Free: '.number_format($billing['free_students']).' · Tổng: '.number_format($billing['total_students']),
                icon: 'workspace_premium',
                href: $this->routeIfExists('admin.billing.subscriptions.index'),
            );

            $expiring = $billing['expiring_premium_students'];
            $kpis[] = $this->kpi(
                label: 'Premium sắp hết hạn',
                value: number_format($expiring),
                hint: 'Hết hạn trong 30 ngày tới',
                icon: 'schedule',
                href: $this->routeIfExists('admin.billing.subscriptions.index'),
                severity: $expiring >= 50 ? 'warning' : null,
            );
        }

        if ($viewer->can(Permission::QuestionView->value)) {
            $feedbackPending = (int) $aggregates['feedback_pending'];

            $kpis[] = $this->kpi(
                label: 'Câu hỏi published',
                value: number_format((int) $aggregates['questions_published']),
                hint: 'Chờ duyệt: '.number_format((int) $aggregates['questions_in_review']),
                icon: 'quiz',
                href: $this->routeIfExists('admin.questions.index'),
            );

            $kpis[] = $this->kpi(
                label: 'Feedback chờ xử lý',
                value: number_format($feedbackPending),
                hint: 'Báo lỗi / góp ý từ học viên',
                icon: 'flag',
                href: $this->routeIfExists('admin.question-feedback.index'),
                severity: $feedbackPending > 0 ? 'warning' : null,
            );

            if (QuestionAccess::isReviewer($viewer)) {
                $reviewsPending = (int) $aggregates['question_reviews_pending'];
                $kpis[] = $this->kpi(
                    label: 'Duyệt câu hỏi chờ',
                    value: number_format($reviewsPending),
                    hint: 'Yêu cầu xuất bản / thay đổi',
                    icon: 'rate_review',
                    href: $this->routeIfExists('admin.questions.index'),
                    severity: $reviewsPending > 0 ? 'warning' : null,
                );
            }
        }

        if ($viewer->can(Permission::ContactView->value)) {
            $contactsNew = (int) $aggregates['contacts_new'];
            $kpis[] = $this->kpi(
                label: 'Liên hệ mới',
                value: number_format($contactsNew),
                hint: 'Chưa xử lý',
                icon: 'mail',
                href: $this->routeIfExists('admin.contacts.index'),
                severity: $contactsNew > 0 ? 'warning' : null,
            );
        }

        if ($viewer->can(Permission::SystemManage->value)) {
            $supportPending = SupportConversation::pendingAdminAttentionCountFor($viewer);
            if ($supportPending > 0) {
                $kpis[] = $this->kpi(
                    label: 'Support chờ xử lý',
                    value: number_format($supportPending),
                    hint: 'Hộp thư hỗ trợ nội bộ',
                    icon: 'support_agent',
                    href: $this->routeIfExists('admin.support.index'),
                    severity: 'warning',
                );
            }
        }

        if ($viewer->can(Permission::ClassroomOversee->value)) {
            $classroomsPending = (int) $aggregates['classrooms_pending'];
            if ($classroomsPending > 0) {
                $kpis[] = $this->kpi(
                    label: 'Lớp chờ duyệt',
                    value: number_format($classroomsPending),
                    hint: 'Cần phê duyệt trước khi công khai',
                    icon: 'school',
                    href: $this->routeIfExists('admin.classrooms.index', ['status' => ClassroomStatus::PendingApproval->value]),
                    severity: 'warning',
                );
            }
        }

        if ($viewer->can(Permission::AdminPartnersPayouts->value)) {
            $payoutsPending = (int) $aggregates['partner_payouts_pending'];
            if ($payoutsPending > 0) {
                $kpis[] = $this->kpi(
                    label: 'Chi trả CTV chờ',
                    value: number_format($payoutsPending),
                    hint: 'Đã duyệt, chưa chi',
                    icon: 'account_balance_wallet',
                    href: $this->routeIfExists('admin.partners.payouts.index'),
                    severity: 'warning',
                );
            }
        }

        return $kpis;
    }

    /**
     * @param  array<string, mixed>  $aggregates
     * @return list<DashboardAlert>
     */
    /**
     * Checklist sức khỏe theo hạng mục — luôn có mục ok/info/warning/critical.
     *
     * @param  array<string, mixed>  $aggregates
     * @return list<DashboardAlert>
     */
    private function buildAlerts(User $viewer, array $aggregates): array
    {
        $alerts = [];

        if ($viewer->can(Permission::UserView->value)) {
            $dau = (int) $aggregates['dau_today'];
            $signups = (int) $aggregates['signups_7d'];
            $alerts[] = $this->alert(
                id: 'users_activity',
                category: 'Người dùng',
                severity: $dau === 0 && $signups === 0 ? 'info' : 'ok',
                title: $dau === 0 && $signups === 0
                    ? 'Chưa có hoạt động học hôm nay'
                    : 'Hoạt động người dùng bình thường',
                message: sprintf(
                    'DAU hôm nay %s · Đăng ký 7 ngày %s · Đăng ký hôm nay %s.',
                    number_format($dau),
                    number_format($signups),
                    number_format((int) $aggregates['signups_today']),
                ),
                href: $this->routeIfExists('admin.users.index'),
            );
        }

        if ($viewer->can(Permission::BillingManage->value)) {
            $failed24h = (int) $aggregates['failed_payments_24h'];
            $succeeded24h = (int) $aggregates['succeeded_payments_24h'];
            $failedAvg = (int) $aggregates['failed_payments_daily_avg'];
            $spikeThreshold = max(2, $failedAvg * 2);
            $expiring = (int) ($aggregates['billing']['expiring_premium_students'] ?? 0);
            $premium = (int) ($aggregates['billing']['premium_students'] ?? 0);

            if ($failed24h >= $spikeThreshold && $failed24h > 0) {
                $alerts[] = $this->alert(
                    id: 'payment_failed_spike',
                    category: 'Thanh toán',
                    severity: 'critical',
                    title: 'Thanh toán thất bại tăng đột biến',
                    message: "{$failed24h} thất bại / {$succeeded24h} thành công trong 24 giờ (trung bình ~{$failedAvg} thất bại/ngày).",
                    href: $this->routeIfExists('admin.billing.payments.index', ['status' => 'failed']),
                );
            } elseif ($failed24h > 0) {
                $alerts[] = $this->alert(
                    id: 'payment_failed_present',
                    category: 'Thanh toán',
                    severity: 'warning',
                    title: 'Có giao dịch thanh toán thất bại',
                    message: "{$failed24h} thất bại · {$succeeded24h} thành công trong 24 giờ qua.",
                    href: $this->routeIfExists('admin.billing.payments.index', ['status' => 'failed']),
                );
            } else {
                $alerts[] = $this->alert(
                    id: 'payment_healthy',
                    category: 'Thanh toán',
                    severity: 'ok',
                    title: 'Thanh toán ổn định',
                    message: $succeeded24h > 0
                        ? "{$succeeded24h} giao dịch thành công trong 24 giờ · 0 thất bại."
                        : 'Không có giao dịch thất bại trong 24 giờ qua.',
                    href: $this->routeIfExists('admin.billing.payments.index'),
                );
            }

            if ($expiring >= 50) {
                $alerts[] = $this->alert(
                    id: 'premium_expiring',
                    category: 'Thuê bao',
                    severity: 'warning',
                    title: 'Nhiều Premium sắp hết hạn',
                    message: "{$expiring} / {$premium} học viên Premium hết hạn trong 30 ngày tới.",
                    href: $this->routeIfExists('admin.billing.subscriptions.index'),
                );
            } else {
                $alerts[] = $this->alert(
                    id: 'premium_stable',
                    category: 'Thuê bao',
                    severity: 'ok',
                    title: 'Thuê bao Premium trong ngưỡng',
                    message: "{$premium} Premium đang hoạt động · {$expiring} sắp hết hạn (30 ngày).",
                    href: $this->routeIfExists('admin.billing.subscriptions.index'),
                );
            }
        }

        if ($viewer->can(Permission::QuestionView->value)) {
            $published = (int) $aggregates['questions_published'];
            $inReview = (int) $aggregates['questions_in_review'];
            $feedbackPending = (int) $aggregates['feedback_pending'];
            $reviewsPending = (int) $aggregates['question_reviews_pending'];

            $alerts[] = $this->alert(
                id: 'content_catalog',
                category: 'Nội dung',
                severity: 'ok',
                title: 'Ngân hàng câu hỏi',
                message: number_format($published).' câu published · '.number_format($inReview).' đang In Review.',
                href: $this->routeIfExists('admin.questions.index'),
            );

            if ($feedbackPending >= 20) {
                $alerts[] = $this->alert(
                    id: 'feedback_backlog_high',
                    category: 'Phản hồi câu hỏi',
                    severity: 'warning',
                    title: 'Hàng đợi feedback cao',
                    message: "{$feedbackPending} phản hồi đang chờ xử lý tại QBank.",
                    href: $this->routeIfExists('admin.question-feedback.index'),
                );
            } elseif ($feedbackPending > 0) {
                $alerts[] = $this->alert(
                    id: 'feedback_backlog',
                    category: 'Phản hồi câu hỏi',
                    severity: 'info',
                    title: 'Feedback chờ xử lý',
                    message: "{$feedbackPending} phản hồi cần xem xét tại module Câu hỏi.",
                    href: $this->routeIfExists('admin.question-feedback.index'),
                );
            } else {
                $alerts[] = $this->alert(
                    id: 'feedback_clear',
                    category: 'Phản hồi câu hỏi',
                    severity: 'ok',
                    title: 'Không còn feedback tồn',
                    message: 'Hàng đợi feedback câu hỏi đang trống.',
                    href: $this->routeIfExists('admin.question-feedback.index'),
                );
            }

            if (QuestionAccess::isReviewer($viewer)) {
                if ($reviewsPending >= 10) {
                    $alerts[] = $this->alert(
                        id: 'question_reviews_high',
                        category: 'Duyệt câu hỏi',
                        severity: 'warning',
                        title: 'Nhiều câu hỏi chờ duyệt',
                        message: "{$reviewsPending} yêu cầu duyệt đang chờ reviewer.",
                        href: $this->routeIfExists('admin.questions.index'),
                    );
                } elseif ($reviewsPending > 0) {
                    $alerts[] = $this->alert(
                        id: 'question_reviews_pending',
                        category: 'Duyệt câu hỏi',
                        severity: 'info',
                        title: 'Có câu hỏi chờ duyệt',
                        message: "{$reviewsPending} yêu cầu duyệt đang chờ.",
                        href: $this->routeIfExists('admin.questions.index'),
                    );
                } else {
                    $alerts[] = $this->alert(
                        id: 'question_reviews_clear',
                        category: 'Duyệt câu hỏi',
                        severity: 'ok',
                        title: 'Không còn yêu cầu duyệt tồn',
                        message: 'Hàng đợi duyệt câu hỏi đang trống.',
                        href: $this->routeIfExists('admin.questions.index'),
                    );
                }
            }
        }

        if ($viewer->can(Permission::ContactView->value)) {
            $contactsNew = (int) $aggregates['contacts_new'];
            if ($contactsNew >= 10) {
                $alerts[] = $this->alert(
                    id: 'contacts_backlog_high',
                    category: 'Liên hệ',
                    severity: 'warning',
                    title: 'Nhiều liên hệ mới chưa xử lý',
                    message: "{$contactsNew} yêu cầu ở trạng thái mới trên form liên hệ.",
                    href: $this->routeIfExists('admin.contacts.index'),
                );
            } elseif ($contactsNew > 0) {
                $alerts[] = $this->alert(
                    id: 'contacts_new',
                    category: 'Liên hệ',
                    severity: 'info',
                    title: 'Liên hệ mới cần phản hồi',
                    message: "{$contactsNew} yêu cầu liên hệ chưa xử lý.",
                    href: $this->routeIfExists('admin.contacts.index'),
                );
            } else {
                $alerts[] = $this->alert(
                    id: 'contacts_clear',
                    category: 'Liên hệ',
                    severity: 'ok',
                    title: 'Hộp thư liên hệ trống',
                    message: 'Không có yêu cầu liên hệ mới đang chờ.',
                    href: $this->routeIfExists('admin.contacts.index'),
                );
            }
        }

        if ($viewer->can(Permission::SystemManage->value)) {
            $supportPending = SupportConversation::pendingAdminAttentionCountFor($viewer);
            if ($supportPending > 0) {
                $alerts[] = $this->alert(
                    id: 'support_pending',
                    category: 'Hỗ trợ chat',
                    severity: $supportPending >= 10 ? 'warning' : 'info',
                    title: 'Support cần phản hồi',
                    message: "{$supportPending} hội thoại đang chờ admin tại Hỗ trợ khách hàng.",
                    href: $this->routeIfExists('admin.support.index'),
                );
            } else {
                $alerts[] = $this->alert(
                    id: 'support_clear',
                    category: 'Hỗ trợ chat',
                    severity: 'ok',
                    title: 'Support không còn tồn',
                    message: 'Không có hội thoại đang chờ phản hồi.',
                    href: $this->routeIfExists('admin.support.index'),
                );
            }

            $failedJobs = (int) ($aggregates['failed_jobs_24h'] ?? 0);
            if ($failedJobs >= 10) {
                $alerts[] = $this->alert(
                    id: 'failed_jobs_high',
                    category: 'Hàng đợi / Horizon',
                    severity: 'critical',
                    title: 'Nhiều job thất bại',
                    message: "{$failedJobs} failed jobs trong 24 giờ — kiểm tra Horizon / failed_jobs.",
                    href: $this->routeIfExists('horizon.index') ?? '/horizon',
                );
            } elseif ($failedJobs > 0) {
                $alerts[] = $this->alert(
                    id: 'failed_jobs_present',
                    category: 'Hàng đợi / Horizon',
                    severity: 'warning',
                    title: 'Có job thất bại gần đây',
                    message: "{$failedJobs} failed jobs trong 24 giờ qua.",
                    href: $this->routeIfExists('horizon.index') ?? '/horizon',
                );
            } else {
                $alerts[] = $this->alert(
                    id: 'queue_healthy',
                    category: 'Hàng đợi / Horizon',
                    severity: 'ok',
                    title: 'Hàng đợi ổn định',
                    message: 'Không có failed jobs mới trong 24 giờ.',
                    href: $this->routeIfExists('horizon.index') ?? '/horizon',
                );
            }

            $reportMeta = AdminReportCache::meta();
            $intervalDays = AdminReportCache::warmIntervalDays();
            if ($reportMeta['warmed_at'] === null) {
                $alerts[] = $this->alert(
                    id: 'report_cache_empty',
                    category: 'Báo cáo',
                    severity: 'info',
                    title: 'Cache báo cáo chưa warm',
                    message: "Chưa có snapshot cache · chu kỳ cron {$intervalDays} ngày. Có thể làm mới thủ công tại Trung tâm báo cáo.",
                    href: $this->routeIfExists('admin.reports.index'),
                );
            } elseif (AdminReportCache::shouldAutoWarm()) {
                $alerts[] = $this->alert(
                    id: 'report_cache_stale',
                    category: 'Báo cáo',
                    severity: 'warning',
                    title: 'Cache báo cáo đã quá hạn chu kỳ',
                    message: 'Lần warm gần nhất '.$reportMeta['warmed_at']->format('d/m/Y H:i')." · chu kỳ {$intervalDays} ngày.",
                    href: $this->routeIfExists('admin.reports.index'),
                );
            } else {
                $alerts[] = $this->alert(
                    id: 'report_cache_fresh',
                    category: 'Báo cáo',
                    severity: 'ok',
                    title: 'Cache báo cáo còn hạn',
                    message: sprintf(
                        'Warm lúc %s · %d snapshot · chu kỳ %d ngày.',
                        $reportMeta['warmed_at']->format('d/m/Y H:i'),
                        $reportMeta['count'],
                        $intervalDays,
                    ),
                    href: $this->routeIfExists('admin.reports.index'),
                );
            }
        }

        if ($viewer->can(Permission::ClassroomOversee->value)) {
            $classroomsPending = (int) $aggregates['classrooms_pending'];
            if ($classroomsPending > 0) {
                $alerts[] = $this->alert(
                    id: 'classrooms_pending',
                    category: 'Classroom',
                    severity: 'info',
                    title: 'Lớp học chờ phê duyệt',
                    message: "{$classroomsPending} lớp đang chờ duyệt tại giám sát Classroom.",
                    href: $this->routeIfExists('admin.classrooms.index', ['status' => ClassroomStatus::PendingApproval->value]),
                );
            } else {
                $alerts[] = $this->alert(
                    id: 'classrooms_clear',
                    category: 'Classroom',
                    severity: 'ok',
                    title: 'Không có lớp chờ duyệt',
                    message: 'Hàng đợi phê duyệt lớp học đang trống.',
                    href: $this->routeIfExists('admin.classrooms.index'),
                );
            }
        }

        if ($viewer->can(Permission::AdminPartnersPayouts->value)) {
            $payoutsPending = (int) $aggregates['partner_payouts_pending'];
            if ($payoutsPending > 0) {
                $alerts[] = $this->alert(
                    id: 'partner_payouts_pending',
                    category: 'Đối tác / CTV',
                    severity: 'info',
                    title: 'Chi trả CTV chờ thực hiện',
                    message: "{$payoutsPending} đợt đã duyệt, chưa chi tại Partner payouts.",
                    href: $this->routeIfExists('admin.partners.payouts.index'),
                );
            } else {
                $alerts[] = $this->alert(
                    id: 'partner_payouts_clear',
                    category: 'Đối tác / CTV',
                    severity: 'ok',
                    title: 'Không còn payout CTV tồn',
                    message: 'Không có đợt chi trả đã duyệt đang chờ chi.',
                    href: $this->routeIfExists('admin.partners.payouts.index'),
                );
            }
        }

        if ($viewer->can(Permission::ReportExport->value) && ! $viewer->can(Permission::SystemManage->value)) {
            $reportMeta = AdminReportCache::meta();
            $alerts[] = $this->alert(
                id: 'report_cache_visibility',
                category: 'Báo cáo',
                severity: $reportMeta['warmed_at'] ? 'ok' : 'info',
                title: $reportMeta['warmed_at'] ? 'Có snapshot báo cáo' : 'Chưa có snapshot báo cáo',
                message: $reportMeta['warmed_at']
                    ? 'Cập nhật '.$reportMeta['warmed_at']->format('d/m/Y H:i').'.'
                    : 'Vào Trung tâm báo cáo để xem / làm mới cache.',
                href: $this->routeIfExists('admin.reports.index'),
            );
        }

        return $this->sortAlerts($alerts);
    }

    /**
     * @return DashboardAlert
     */
    private function alert(
        string $id,
        string $category,
        string $severity,
        string $title,
        string $message,
        ?string $href,
    ): array {
        return [
            'id' => $id,
            'category' => $category,
            'severity' => $severity,
            'title' => $title,
            'message' => $message,
            'href' => $href,
        ];
    }

    /** @return list<AuditFeedItem> */
    private function buildAuditFeed(User $viewer): array
    {
        if (! $viewer->can(Permission::AuditView->value)) {
            return [];
        }

        $userMorphClass = (new User)->getMorphClass();
        $questionMorphClass = (new \Modules\QuestionBank\Models\Question)->getMorphClass();

        return AuditLog::query()
            ->visibleToAdmin()
            ->with('actor:id,name')
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(function (AuditLog $log) use ($userMorphClass, $questionMorphClass): array {
                [$subjectLabel, $subjectHref] = $this->resolveAuditSubject($log, $userMorphClass, $questionMorphClass);

                return [
                    'id' => $log->id,
                    'actor_name' => $log->actor?->name,
                    'action_label' => $log->actionLabel(),
                    'subject_label' => $subjectLabel,
                    'subject_href' => $subjectHref,
                    'occurred_at' => $log->created_at ?? now(),
                    'href' => route('admin.audit.show', $log),
                ];
            })
            ->all();
    }

    /** @return list<QuickAction> */
    private function buildQuickActions(User $viewer): array
    {
        $actions = [];

        if ($viewer->can(Permission::QuestionCreate->value) && Route::has('admin.questions.create')) {
            $actions[] = [
                'label' => 'Tạo câu hỏi',
                'icon' => 'add_circle',
                'href' => route('admin.questions.create'),
            ];
        }

        if ($viewer->can(Permission::UserManage->value) && Route::has('admin.users.create')) {
            $actions[] = [
                'label' => 'Mời quản trị viên',
                'icon' => 'person_add',
                'href' => route('admin.users.create'),
            ];
        }

        if ($viewer->can(Permission::BillingManage->value) && Route::has('admin.billing.plans.index')) {
            $actions[] = [
                'label' => 'Xem gói & bảng giá',
                'icon' => 'sell',
                'href' => route('admin.billing.plans.index'),
            ];
        }

        if ($viewer->can(Permission::AuditView->value) && Route::has('admin.audit.index')) {
            $actions[] = [
                'label' => 'Xem nhật ký audit',
                'icon' => 'history',
                'href' => route('admin.audit.index'),
            ];
        }

        if ($viewer->can(Permission::ReportExport->value) && Route::has('admin.reports.index')) {
            $actions[] = [
                'label' => 'Trung tâm báo cáo',
                'icon' => 'analytics',
                'href' => route('admin.reports.index'),
            ];
        }

        if ($viewer->can(Permission::QuestionView->value) && Route::has('admin.question-feedback.index')) {
            $actions[] = [
                'label' => 'Xử lý feedback',
                'icon' => 'rate_review',
                'href' => route('admin.question-feedback.index'),
            ];
        }

        return $actions;
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function resolveAuditSubject(AuditLog $log, string $userMorphClass, string $questionMorphClass): array
    {
        if ($log->auditable_type === null || $log->auditable_id === null) {
            return [null, null];
        }

        if ($log->auditable_type === $userMorphClass && Route::has('admin.users.show')) {
            return [
                'Người dùng #'.$log->auditable_id,
                route('admin.users.show', $log->auditable_id),
            ];
        }

        if ($log->auditable_type === $questionMorphClass && Route::has('admin.questions.edit')) {
            return [
                'Câu hỏi #'.$log->auditable_id,
                route('admin.questions.edit', $log->auditable_id),
            ];
        }

        return [
            class_basename($log->auditable_type).' #'.$log->auditable_id,
            null,
        ];
    }

    /**
     * @param  list<DashboardAlert>  $alerts
     * @return list<DashboardAlert>
     */
    private function sortAlerts(array $alerts): array
    {
        $order = ['critical' => 0, 'warning' => 1, 'info' => 2, 'ok' => 3];

        usort($alerts, function (array $a, array $b) use ($order): int {
            $bySeverity = ($order[$a['severity']] ?? 99) <=> ($order[$b['severity']] ?? 99);
            if ($bySeverity !== 0) {
                return $bySeverity;
            }

            return strcmp($a['category'], $b['category']);
        });

        return $alerts;
    }

    private function percentDelta(int|float $current, int|float $previous): ?float
    {
        if ($previous === 0) {
            return $current > 0 ? 100.0 : null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * @return KpiCard
     */
    private function kpi(
        string $label,
        string $value,
        ?string $hint,
        string $icon,
        ?float $delta = null,
        ?string $href = null,
        ?string $severity = null,
    ): array {
        return [
            'label' => $label,
            'value' => $value,
            'hint' => $hint,
            'icon' => $icon,
            'delta' => $delta,
            'delta_suffix' => '%',
            'delta_mode' => 'percent',
            'href' => $href,
            'severity' => $severity,
        ];
    }

    /** @param  array<string, mixed>  $parameters */
    private function routeIfExists(string $name, array $parameters = []): ?string
    {
        return Route::has($name) ? route($name, $parameters) : null;
    }
}
