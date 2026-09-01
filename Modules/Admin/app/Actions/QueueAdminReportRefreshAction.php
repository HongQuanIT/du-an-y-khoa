<?php

declare(strict_types=1);

namespace Modules\Admin\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Admin\Jobs\RefreshAdminReportCacheJob;
use Modules\Admin\Support\AdminReportCache;
use Modules\Admin\Support\AdminReportCatalog;

final class QueueAdminReportRefreshAction
{
    use AsAction;

    /**
     * @return array{queued: bool, status: string, message: string}
     */
    public function handle(User $actor, string $category, string $report, string $range = '30d'): array
    {
        $validated = Validator::make([
            'category' => $category,
            'report' => $report,
            'range' => $range,
        ], [
            'category' => ['required', 'string', 'max:40'],
            'report' => ['required', 'string', 'max:40'],
            'range' => ['required', 'string', Rule::in(['7d', '30d', '90d', '365d'])],
        ])->validate();

        $match = AdminReportCatalog::findReport($validated['category'], $validated['report']);
        if ($match === null) {
            throw ValidationException::withMessages([
                'report' => 'Báo cáo không tồn tại.',
            ]);
        }

        $permission = $match['category']['permission'];
        if ($permission !== null && ! $actor->can($permission->value)) {
            abort(403);
        }

        if (AdminReportCache::isRefreshInFlight($validated['category'], $validated['report'], $validated['range'])) {
            return [
                'queued' => false,
                'status' => AdminReportCache::refreshStatus($validated['category'], $validated['report'], $validated['range'])['status'],
                'message' => 'Báo cáo đang được xử lý trong hàng đợi.',
            ];
        }

        AdminReportCache::markRefreshQueued(
            $validated['category'],
            $validated['report'],
            $validated['range'],
            $actor->id,
        );

        RefreshAdminReportCacheJob::dispatch(
            $validated['category'],
            $validated['report'],
            $validated['range'],
        );

        // sync driver: job already finished → status ready
        $status = AdminReportCache::refreshStatus(
            $validated['category'],
            $validated['report'],
            $validated['range'],
        )['status'];

        return [
            'queued' => true,
            'status' => $status,
            'message' => $status === 'ready'
                ? 'Báo cáo mới đã được tạo xong.'
                : 'Đã đưa tạo báo cáo mới vào hàng đợi.',
        ];
    }
}
