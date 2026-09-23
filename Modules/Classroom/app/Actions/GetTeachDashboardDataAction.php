<?php

declare(strict_types=1);

namespace Modules\Classroom\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use App\Support\Enums\Permission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Modules\Classroom\Enums\ClassroomPurpose;
use Modules\Classroom\Enums\ClassroomStatus;
use Modules\Classroom\Enums\LiveSessionStatus;
use Modules\Classroom\Enums\MemberRole;
use Modules\Classroom\Enums\MemberStatus;
use Modules\Classroom\Models\Classroom;
use Modules\Classroom\Models\ClassroomMember;
use Modules\Classroom\Models\LiveSession;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Models\Question;

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
 * @phpstan-type UpcomingSessionItem array{
 *     id: int,
 *     title: string,
 *     classroom_title: string,
 *     scheduled_at: Carbon,
 *     href: string,
 * }
 * @phpstan-type PendingReviewItem array{
 *     id: int,
 *     code: ?string,
 *     stem: string,
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
 *     upcoming_sessions: list<UpcomingSessionItem>,
 *     pending_reviews: list<PendingReviewItem>,
 *     quick_actions: list<QuickAction>,
 * }
 */
final class GetTeachDashboardDataAction
{
    use AsAction;

    private const CACHE_TTL_SECONDS = 180;

    /** Bump when cached payload shape changes (avoids stale incomplete Carbon objects). */
    private const CACHE_VERSION = 'v3';

    /** @return DashboardData */
    public function handle(User $viewer): array
    {
        $cacheKey = 'teach:dashboard:'.self::CACHE_VERSION.':'.$viewer->getKey();

        /** @var array<string, mixed> $aggregates */
        $aggregates = Cache::remember(
            $cacheKey,
            self::CACHE_TTL_SECONDS,
            fn (): array => $this->aggregate($viewer),
        );

        // Legacy / corrupt entries may still hold incomplete objects — rebuild once.
        if (! $this->aggregatesAreScalar($aggregates)) {
            Cache::forget($cacheKey);
            $aggregates = $this->aggregate($viewer);
            Cache::put($cacheKey, $aggregates, self::CACHE_TTL_SECONDS);
        }

        $upcomingSessions = array_map(function (array $session): array {
            $session['scheduled_at'] = $this->carbonFrom($session['scheduled_at'] ?? null);

            return $session;
        }, $aggregates['upcoming_sessions'] ?? []);

        return [
            'refreshed_at' => $this->carbonFrom($aggregates['refreshed_at'] ?? null),
            'kpis' => $this->buildKpis($viewer, $aggregates),
            'charts' => $this->buildCharts($viewer, $aggregates),
            'alerts' => $this->buildAlerts($viewer, $aggregates),
            'upcoming_sessions' => $upcomingSessions,
            'pending_reviews' => $aggregates['pending_review_items'] ?? [],
            'quick_actions' => $this->buildQuickActions($viewer, $aggregates),
        ];
    }

    /** @param  array<string, mixed>  $aggregates */
    private function aggregatesAreScalar(array $aggregates): bool
    {
        if (! is_string($aggregates['refreshed_at'] ?? null)) {
            return false;
        }

        foreach ($aggregates['upcoming_sessions'] ?? [] as $session) {
            if (! is_array($session) || ! is_string($session['scheduled_at'] ?? null)) {
                return false;
            }
        }

        return true;
    }

    private function carbonFrom(mixed $value): Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance(\DateTimeImmutable::createFromInterface($value));
        }

        if (is_string($value) && $value !== '') {
            return Carbon::parse($value);
        }

        return now();
    }

    /** @return array<string, mixed> */
    private function aggregate(User $viewer): array
    {
        $base = $this->teachClassroomsQuery($viewer);

        $total = (clone $base)->count();
        $live = (clone $base)->whereHas('liveSession')->count();
        $upcoming = (clone $base)->whereHas('upcomingSession')->count();
        $pendingApproval = (clone $base)
            ->where('status', ClassroomStatus::PendingApproval->value)
            ->count();

        $pendingReviews = $this->pendingReviewsQuery($viewer)->count();

        $upcomingSoon = LiveSession::query()
            ->where('status', LiveSessionStatus::Scheduled->value)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '>', now())
            ->where('scheduled_at', '<=', now()->addDay())
            ->whereIn('classroom_id', (clone $base)->select('classrooms.id'))
            ->count();

        $upcomingSessions = LiveSession::query()
            ->with(['classroom:id,uuid,title'])
            ->where('status', LiveSessionStatus::Scheduled->value)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '>', now())
            ->whereIn('classroom_id', (clone $base)->select('classrooms.id'))
            ->orderBy('scheduled_at')
            ->limit(5)
            ->get()
            ->map(function (LiveSession $session): array {
                $classroom = $session->classroom;
                $href = $classroom !== null && Route::has('teach.classes.show')
                    ? route('teach.classes.show', $classroom)
                    : '#';

                return [
                    'id' => (int) $session->getKey(),
                    'title' => (string) $session->title,
                    'classroom_title' => (string) ($classroom?->title ?? 'Lớp'),
                    'scheduled_at' => ($session->scheduled_at ?? now())->toIso8601String(),
                    'href' => $href,
                ];
            })
            ->all();

        $pendingReviewItems = $this->pendingReviewsQuery($viewer)
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get(['id', 'code', 'stem'])
            ->map(function (Question $question): array {
                $stem = trim(strip_tags((string) $question->stem));
                if (mb_strlen($stem) > 100) {
                    $stem = mb_substr($stem, 0, 97).'…';
                }

                return [
                    'id' => (int) $question->getKey(),
                    'code' => $question->code,
                    'stem' => $stem !== '' ? $stem : 'Câu hỏi #'.$question->getKey(),
                    'href' => Route::has('teach.questions.reviews.show')
                        ? route('teach.questions.reviews.show', $question)
                        : '#',
                ];
            })
            ->all();

        $liveSession = LiveSession::query()
            ->with(['classroom:id,uuid,title'])
            ->where('status', LiveSessionStatus::Live->value)
            ->whereIn('classroom_id', (clone $base)->select('classrooms.id'))
            ->latest('started_at')
            ->first();

        $studioHref = null;
        if ($liveSession !== null && $liveSession->classroom !== null && Route::has('teach.classes.sessions.studio')) {
            $studioHref = route('teach.classes.sessions.studio', [$liveSession->classroom, $liveSession]);
        }

        return [
            'refreshed_at' => now()->toIso8601String(),
            'total_classes' => $total,
            'live_classes' => $live,
            'upcoming_classes' => $upcoming,
            'pending_approval' => $pendingApproval,
            'pending_reviews' => $pendingReviews,
            'upcoming_soon' => $upcomingSoon,
            'upcoming_sessions' => $upcomingSessions,
            'pending_review_items' => $pendingReviewItems,
            'studio_href' => $studioHref,
            'chart_series' => [
                'activity' => $this->activitySeries($viewer, 30),
                'reviews' => $this->reviewSeries($viewer, 30),
            ],
        ];
    }

    /**
     * @return list<array{label: string, lives: int, members: int}>
     */
    private function activitySeries(User $viewer, int $days): array
    {
        $start = Carbon::today()->subDays($days - 1);
        $classroomIds = $this->teachClassroomsQuery($viewer)->pluck('classrooms.id');

        /** @var array<string, int> $livesByDate */
        $livesByDate = [];
        /** @var array<string, int> $membersByDate */
        $membersByDate = [];

        if ($classroomIds->isNotEmpty()) {
            $livesByDate = LiveSession::query()
                ->whereIn('classroom_id', $classroomIds)
                ->whereNotNull('started_at')
                ->where('started_at', '>=', $start->copy()->startOfDay())
                ->selectRaw('DATE(started_at) as day_key, COUNT(*) as aggregate')
                ->groupBy('day_key')
                ->pluck('aggregate', 'day_key')
                ->map(fn ($count): int => (int) $count)
                ->all();

            $membersByDate = ClassroomMember::query()
                ->whereIn('classroom_id', $classroomIds)
                ->where('role_in_class', MemberRole::Member->value)
                ->where('status', MemberStatus::Active->value)
                ->whereNotNull('joined_at')
                ->where('joined_at', '>=', $start->copy()->startOfDay())
                ->selectRaw('DATE(joined_at) as day_key, COUNT(*) as aggregate')
                ->groupBy('day_key')
                ->pluck('aggregate', 'day_key')
                ->map(fn ($count): int => (int) $count)
                ->all();
        }

        $series = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $start->copy()->addDays($i);
            $key = $date->toDateString();
            $series[] = [
                'label' => $date->format('d/m'),
                'lives' => $livesByDate[$key] ?? 0,
                'members' => $membersByDate[$key] ?? 0,
            ];
        }

        return $series;
    }

    /**
     * @return list<array{label: string, approved: int, rejected: int}>
     */
    private function reviewSeries(User $viewer, int $days): array
    {
        $start = Carbon::today()->subDays($days - 1);
        $actorId = (int) $viewer->getKey();

        $rows = Question::query()
            ->whereNotNull('instructor_reviewed_at')
            ->where('instructor_reviewed_at', '>=', $start->copy()->startOfDay())
            ->where(function ($query) use ($actorId): void {
                $query->where('instructor_id', $actorId)
                    ->orWhere('assigned_instructor_id', $actorId)
                    ->orWhere('instructor_1_id', $actorId)
                    ->orWhere('instructor_2_id', $actorId);
            })
            ->whereNotNull('instructor_decision')
            ->selectRaw('DATE(instructor_reviewed_at) as day_key')
            ->selectRaw("SUM(CASE WHEN instructor_decision = 'approved' THEN 1 ELSE 0 END) as approved_total")
            ->selectRaw("SUM(CASE WHEN instructor_decision = 'rejected' THEN 1 ELSE 0 END) as rejected_total")
            ->groupBy('day_key')
            ->get()
            ->keyBy('day_key');

        $series = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $start->copy()->addDays($i);
            $key = $date->toDateString();
            $row = $rows->get($key);
            $series[] = [
                'label' => $date->format('d/m'),
                'approved' => $row ? (int) $row->approved_total : 0,
                'rejected' => $row ? (int) $row->rejected_total : 0,
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
        $chartSeries = $aggregates['chart_series'] ?? [];

        if ($viewer->can('classroom.view') && isset($chartSeries['activity'])) {
            /** @var list<array{label: string, lives: int, members: int}> $activity */
            $activity = $chartSeries['activity'];
            $charts[] = [
                'id' => 'chart-teach-activity',
                'title' => 'Hoạt động lớp học',
                'subtitle' => '30 ngày qua · Buổi live đã bắt đầu và thành viên mới',
                'type' => 'line',
                'format' => 'number',
                'labels' => array_column($activity, 'label'),
                'datasets' => [
                    [
                        'label' => 'Buổi live',
                        'data' => array_column($activity, 'lives'),
                        'color' => '#0f766e',
                    ],
                    [
                        'label' => 'Thành viên mới',
                        'data' => array_column($activity, 'members'),
                        'color' => '#0891b2',
                    ],
                ],
            ];
        }

        if ($viewer->can(Permission::QuestionView->value) && isset($chartSeries['reviews'])) {
            /** @var list<array{label: string, approved: int, rejected: int}> $reviews */
            $reviews = $chartSeries['reviews'];
            $charts[] = [
                'id' => 'chart-teach-reviews',
                'title' => 'Duyệt câu hỏi',
                'subtitle' => '30 ngày qua · Quyết định chuyên môn của bạn',
                'type' => 'bar',
                'format' => 'number',
                'labels' => array_column($reviews, 'label'),
                'datasets' => [
                    [
                        'label' => 'Đã duyệt',
                        'data' => array_column($reviews, 'approved'),
                        'color' => '#0f766e',
                    ],
                    [
                        'label' => 'Từ chối',
                        'data' => array_column($reviews, 'rejected'),
                        'color' => '#b45309',
                    ],
                ],
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
        $canViewClasses = $viewer->can('classroom.view');
        $canViewReviews = $viewer->can(Permission::QuestionView->value);

        $kpis = [];

        $kpis[] = $this->kpi(
            'Lớp của tôi',
            number_format((int) $aggregates['total_classes']),
            'Lớp bạn host / co-host',
            'school',
            href: $canViewClasses && Route::has('teach.classes.index')
                ? route('teach.classes.index')
                : null,
        );

        $liveCount = (int) $aggregates['live_classes'];
        $kpis[] = $this->kpi(
            'Đang live',
            number_format($liveCount),
            'Buổi đang phát trực tiếp',
            'podcasts',
            href: $canViewClasses && Route::has('teach.classes.index')
                ? route('teach.classes.index')
                : null,
            severity: $liveCount > 0 ? 'warning' : null,
        );

        $kpis[] = $this->kpi(
            'Sắp live',
            number_format((int) $aggregates['upcoming_classes']),
            'Có buổi đã lên lịch',
            'event_upcoming',
            href: $canViewClasses && Route::has('teach.classes.index')
                ? route('teach.classes.index')
                : null,
        );

        $pendingReviews = (int) $aggregates['pending_reviews'];
        $kpis[] = $this->kpi(
            'Câu chờ duyệt',
            number_format($pendingReviews),
            'Gán cho bạn · In review',
            'rate_review',
            href: $canViewReviews && Route::has('teach.questions.reviews.index')
                ? route('teach.questions.reviews.index')
                : null,
            severity: $pendingReviews > 0 ? 'warning' : null,
        );

        return $kpis;
    }

    /**
     * @param  array<string, mixed>  $aggregates
     * @return list<DashboardAlert>
     */
    private function buildAlerts(User $viewer, array $aggregates): array
    {
        $alerts = [];

        $pendingApproval = (int) $aggregates['pending_approval'];
        if ($pendingApproval > 0) {
            $alerts[] = [
                'id' => 'pending-approval',
                'category' => 'Lớp học',
                'severity' => 'info',
                'title' => $pendingApproval.' lớp đang chờ Admin duyệt',
                'message' => 'Học viên chưa thấy lớp cho đến khi được phê duyệt.',
                'href' => $viewer->can('classroom.view') && Route::has('teach.classes.index')
                    ? route('teach.classes.index')
                    : null,
            ];
        }

        $upcomingSoon = (int) $aggregates['upcoming_soon'];
        if ($upcomingSoon > 0) {
            $alerts[] = [
                'id' => 'live-soon',
                'category' => 'Live',
                'severity' => 'warning',
                'title' => $upcomingSoon.' buổi live trong 24 giờ tới',
                'message' => 'Kiểm tra lịch và chuẩn bị studio trước giờ phát.',
                'href' => $viewer->can('classroom.view') && Route::has('teach.classes.index')
                    ? route('teach.classes.index')
                    : null,
            ];
        }

        $pendingReviews = (int) $aggregates['pending_reviews'];
        if ($pendingReviews > 0) {
            $alerts[] = [
                'id' => 'review-backlog',
                'category' => 'Duyệt câu hỏi',
                'severity' => 'warning',
                'title' => $pendingReviews.' câu đang chờ bạn duyệt',
                'message' => 'Duyệt chuyên môn để câu hỏi tiếp tục quy trình xuất bản.',
                'href' => $viewer->can(Permission::QuestionView->value) && Route::has('teach.questions.reviews.index')
                    ? route('teach.questions.reviews.index')
                    : null,
            ];
        }

        if ($alerts === []) {
            $alerts[] = [
                'id' => 'all-clear',
                'category' => 'Tổng quan',
                'severity' => 'ok',
                'title' => 'Không có việc cần xử lý ngay',
                'message' => 'Không có lớp chờ duyệt, live sắp tới hay câu hỏi backlog.',
                'href' => null,
            ];
        }

        return $alerts;
    }

    /**
     * @param  array<string, mixed>  $aggregates
     * @return list<QuickAction>
     */
    private function buildQuickActions(User $viewer, array $aggregates): array
    {
        $actions = [];

        if ($viewer->can(Permission::ClassroomCreate->value) && Route::has('teach.classes.create')) {
            $actions[] = [
                'label' => 'Tạo lớp',
                'icon' => 'add',
                'href' => route('teach.classes.create'),
            ];
        }

        if ($viewer->can('classroom.view') && Route::has('teach.classes.index')) {
            $actions[] = [
                'label' => 'Lớp của tôi',
                'icon' => 'school',
                'href' => route('teach.classes.index'),
            ];
        }

        if ($viewer->can(Permission::QuestionView->value) && Route::has('teach.questions.reviews.index')) {
            $actions[] = [
                'label' => 'Duyệt câu hỏi',
                'icon' => 'rate_review',
                'href' => route('teach.questions.reviews.index'),
            ];
        }

        if (is_string($aggregates['studio_href'] ?? null) && $aggregates['studio_href'] !== '') {
            $actions[] = [
                'label' => 'Vào studio đang live',
                'icon' => 'videocam',
                'href' => $aggregates['studio_href'],
            ];
        }

        return $actions;
    }

    /** @return Builder<Classroom> */
    private function teachClassroomsQuery(User $viewer): Builder
    {
        return Classroom::query()
            ->whereIn('purpose', array_map(
                static fn (ClassroomPurpose $purpose): string => $purpose->value,
                ClassroomPurpose::teachCases(),
            ))
            ->whereHas('members', function ($query) use ($viewer): void {
                $query->where('user_id', $viewer->getKey())
                    ->where('status', MemberStatus::Active->value)
                    ->whereIn('role_in_class', [MemberRole::Host->value, MemberRole::Cohost->value]);
            });
    }

    /** @return Builder<Question> */
    private function pendingReviewsQuery(User $viewer): Builder
    {
        return Question::query()
            ->where('status', QuestionStatus::InReview->value)
            ->where('assigned_instructor_id', $viewer->getKey());
    }

    /** @return KpiCard */
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
}
