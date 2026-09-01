<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Enums\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Modules\Admin\Actions\DeleteReportScheduleAction;
use Modules\Admin\Actions\GetAdminReportDataAction;
use Modules\Admin\Actions\QueueAdminReportRefreshAction;
use Modules\Admin\Actions\SaveReportScheduleAction;
use Modules\Admin\Actions\ToggleReportScheduleAction;
use Modules\Admin\Actions\ToggleReportScheduleEmailAction;
use Modules\Admin\Enums\ReportScheduleFrequency;
use Modules\Admin\Jobs\WarmAllAdminReportCachesJob;
use Modules\Admin\Models\ReportSchedule;
use Modules\Admin\Support\AdminReportCache;
use Modules\Admin\Support\AdminReportCatalog;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ReportController extends Controller
{
    public function index(): View
    {
        $this->authorizeReportAccess();

        $schedules = ReportSchedule::query()
            ->with('creator:id,name,email')
            ->latest('id')
            ->limit(50)
            ->get()
            ->filter(function (ReportSchedule $schedule): bool {
                $match = AdminReportCatalog::findReport($schedule->category_slug, $schedule->report_slug);
                if ($match === null) {
                    return false;
                }
                $permission = $match['category']['permission'];

                return $permission === null || $this->actor()->can($permission->value);
            })
            ->values();

        return view('admin::reports.index', [
            'categories' => AdminReportCatalog::forUser($this->actor()),
            'schedules' => $schedules,
            'cacheMeta' => AdminReportCache::meta(),
            'warmAllStatus' => $this->warmAllStatus(),
        ]);
    }

    public function queueWarmAll(): RedirectResponse
    {
        $this->authorizeReportAccess();

        $status = $this->warmAllStatus();

        // Status cache có thể kẹt ở queued/processing dù Horizon đã trống.
        if ($this->isWarmAllInFlight($status) && ! $this->isWarmAllStale($status)) {
            return back()->with('status', 'Đang làm mới toàn bộ cache báo cáo trong hàng đợi.');
        }

        Cache::put('admin:report:warm-all:status', [
            'status' => 'queued',
            'queued_at' => now()->toIso8601String(),
        ], 1800);

        try {
            WarmAllAdminReportCachesJob::dispatch();
        } catch (\Throwable $e) {
            Cache::put('admin:report:warm-all:status', [
                'status' => 'failed',
                'finished_at' => now()->toIso8601String(),
                'error' => $e->getMessage(),
            ], 1800);

            return back()->with('status', 'Không đưa được job vào hàng đợi: '.$e->getMessage());
        }

        return back()->with('status', 'Đã đưa làm mới toàn bộ cache báo cáo vào hàng đợi.');
    }

    public function resetWarmAllStatus(): RedirectResponse
    {
        $this->authorizeReportAccess();

        Cache::forget('admin:report:warm-all:status');

        return back()->with('status', 'Đã xóa trạng thái làm mới cache (cho phép chạy lại).');
    }

    public function warmAllStatusJson(): JsonResponse
    {
        $this->authorizeReportAccess();

        return response()->json($this->warmAllStatus());
    }

    public function showCategory(string $category): View
    {
        $this->authorizeReportAccess();

        $definition = AdminReportCatalog::findCategory($category);
        abort_if($definition === null, 404);
        $this->authorizeCategory($definition);

        return view('admin::reports.category', [
            'category' => $definition,
        ]);
    }

    public function showReport(Request $request, string $category, string $report, GetAdminReportDataAction $action): View
    {
        $this->authorizeReportAccess();

        $match = AdminReportCatalog::findReport($category, $report);
        abort_if($match === null, 404);
        $this->authorizeCategory($match['category']);

        $range = (string) $request->query('range', '30d');
        $data = $action->handle($category, $report, $range);

        $schedules = ReportSchedule::query()
            ->where('category_slug', $category)
            ->where('report_slug', $report)
            ->with('creator:id,name')
            ->latest('id')
            ->get();

        return view('admin::reports.show', [
            'category' => $match['category'],
            'report' => $match['report'],
            'data' => $data,
            'schedules' => $schedules,
            'refreshStatus' => AdminReportCache::refreshStatus($category, $report, $data['range']),
            'frequencies' => ReportScheduleFrequency::cases(),
            'ranges' => [
                '7d' => '7 ngày',
                '30d' => '30 ngày',
                '90d' => '90 ngày',
                '365d' => '12 tháng',
            ],
            'weekdays' => [
                1 => 'Thứ Hai',
                2 => 'Thứ Ba',
                3 => 'Thứ Tư',
                4 => 'Thứ Năm',
                5 => 'Thứ Sáu',
                6 => 'Thứ Bảy',
                7 => 'Chủ Nhật',
            ],
        ]);
    }

    public function refresh(
        Request $request,
        string $category,
        string $report,
        QueueAdminReportRefreshAction $action,
    ): RedirectResponse|JsonResponse {
        $this->authorizeReportAccess();

        $match = AdminReportCatalog::findReport($category, $report);
        abort_if($match === null, 404);
        $this->authorizeCategory($match['category']);

        $range = (string) $request->input('range', $request->query('range', '30d'));
        $result = $action->handle($this->actor(), $category, $report, $range);

        if ($request->wantsJson()) {
            return response()->json([
                ...$result,
                'status_url' => route('admin.reports.refresh-status', [
                    'category' => $category,
                    'report' => $report,
                    'range' => $range,
                ]),
            ]);
        }

        return redirect()
            ->route('admin.reports.show', [
                'category' => $category,
                'report' => $report,
                'range' => $range,
            ])
            ->with('status', $result['message']);
    }

    public function refreshStatus(Request $request, string $category, string $report): JsonResponse
    {
        $this->authorizeReportAccess();

        $match = AdminReportCatalog::findReport($category, $report);
        abort_if($match === null, 404);
        $this->authorizeCategory($match['category']);

        $range = (string) $request->query('range', '30d');
        $status = AdminReportCache::refreshStatus($category, $report, $range);
        $cached = AdminReportCache::get($category, $report, $range);

        return response()->json([
            ...$status,
            'cached_at' => $cached['cached_at'] ?? null,
            'show_url' => route('admin.reports.show', [
                'category' => $category,
                'report' => $report,
                'range' => $range,
            ]),
        ]);
    }

    public function export(Request $request, string $category, string $report, GetAdminReportDataAction $action): StreamedResponse
    {
        $this->authorizeReportAccess();

        $match = AdminReportCatalog::findReport($category, $report);
        abort_if($match === null, 404);
        $this->authorizeCategory($match['category']);

        $range = (string) $request->query('range', '30d');
        $export = $action->exportRows($category, $report, $range);

        $filename = sprintf(
            'report-%s-%s-%s.csv',
            $category,
            $report,
            now()->format('Ymd-His'),
        );

        return response()->streamDownload(function () use ($export): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $export['headers']);
            foreach ($export['rows'] as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function storeSchedule(
        Request $request,
        string $category,
        string $report,
        SaveReportScheduleAction $action,
    ): RedirectResponse {
        $this->authorizeReportAccess();

        $match = AdminReportCatalog::findReport($category, $report);
        abort_if($match === null, 404);
        $this->authorizeCategory($match['category']);

        $action->handle($this->actor(), [
            'category_slug' => $category,
            'report_slug' => $report,
            'range_key' => (string) $request->input('range_key', '30d'),
            'frequency' => (string) $request->input('frequency'),
            'weekday' => $request->input('weekday'),
            'day_of_month' => $request->input('day_of_month'),
            'send_time' => (string) $request->input('send_time', '08:00'),
            'recipients' => (string) $request->input('recipients', ''),
            'send_email' => $request->has('send_email')
                ? $request->boolean('send_email')
                : true,
        ]);

        return redirect()
            ->route('admin.reports.show', [
                'category' => $category,
                'report' => $report,
                'range' => $request->input('range_key', '30d'),
            ])
            ->with('status', 'Đã tạo lịch báo cáo.');
    }

    public function toggleSchedule(ReportSchedule $schedule, ToggleReportScheduleAction $action): RedirectResponse
    {
        $this->authorizeReportAccess();
        $this->authorizeSchedule($schedule);

        $action->handle($schedule);

        return back()->with('status', $schedule->fresh()?->is_active
            ? 'Đã bật lịch báo cáo.'
            : 'Đã tắt lịch báo cáo (không chạy / không gửi).');
    }

    public function toggleScheduleEmail(ReportSchedule $schedule, ToggleReportScheduleEmailAction $action): RedirectResponse
    {
        $this->authorizeReportAccess();
        $this->authorizeSchedule($schedule);

        $action->handle($schedule);

        return back()->with('status', $schedule->fresh()?->send_email
            ? 'Đã bật gửi email cho lịch này.'
            : 'Đã tắt gửi email — lịch vẫn chạy để cập nhật cache, không gửi mail.');
    }

    public function destroySchedule(ReportSchedule $schedule, DeleteReportScheduleAction $action): RedirectResponse
    {
        $this->authorizeReportAccess();
        $this->authorizeSchedule($schedule);

        $action->handle($schedule);

        return back()->with('status', 'Đã xóa lịch báo cáo.');
    }

    private function authorizeReportAccess(): void
    {
        abort_unless($this->actor()->can(Permission::ReportExport->value), 403);
    }

    /** @param array{permission: ?\App\Support\Enums\Permission} $category */
    private function authorizeCategory(array $category): void
    {
        $permission = $category['permission'];
        if ($permission !== null) {
            abort_unless($this->actor()->can($permission->value), 403);
        }
    }

    private function authorizeSchedule(ReportSchedule $schedule): void
    {
        $match = AdminReportCatalog::findReport($schedule->category_slug, $schedule->report_slug);
        abort_if($match === null, 404);
        $this->authorizeCategory($match['category']);
    }

    private function actor(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }

    /** @return array{status: string, queued_at?: ?string, started_at?: ?string, finished_at?: ?string, error?: ?string, count?: int, stale?: bool} */
    private function warmAllStatus(): array
    {
        $raw = Cache::get('admin:report:warm-all:status');

        if (! is_array($raw)) {
            return ['status' => 'idle'];
        }

        $status = [
            'status' => (string) ($raw['status'] ?? 'idle'),
            'queued_at' => isset($raw['queued_at']) ? (string) $raw['queued_at'] : null,
            'started_at' => isset($raw['started_at']) ? (string) $raw['started_at'] : null,
            'finished_at' => isset($raw['finished_at']) ? (string) $raw['finished_at'] : null,
            'error' => isset($raw['error']) ? (string) $raw['error'] : null,
            'count' => isset($raw['count']) ? (int) $raw['count'] : null,
        ];

        if ($this->isWarmAllStale($status)) {
            Cache::forget('admin:report:warm-all:status');

            return [
                'status' => 'failed',
                'error' => 'Trạng thái làm mới bị kẹt (không thấy job hoàn tất). Đã tự reset — bấm làm mới lại.',
                'stale' => true,
                'finished_at' => now()->toIso8601String(),
            ];
        }

        return $status;
    }

    /** @param array{status: string} $status */
    private function isWarmAllInFlight(array $status): bool
    {
        return in_array($status['status'], ['queued', 'processing'], true);
    }

    /**
     * queued/processing quá lâu → coi như kẹt (Horizon trống / job mất).
     *
     * @param  array{status: string, queued_at?: ?string, started_at?: ?string}  $status
     */
    private function isWarmAllStale(array $status): bool
    {
        if (! $this->isWarmAllInFlight($status)) {
            return false;
        }

        $anchor = $status['started_at'] ?? $status['queued_at'] ?? null;
        if (! is_string($anchor) || $anchor === '') {
            return true;
        }

        try {
            return \Illuminate\Support\Carbon::parse($anchor)->lt(now()->subSeconds(120));
        } catch (\Throwable) {
            return true;
        }
    }
}
