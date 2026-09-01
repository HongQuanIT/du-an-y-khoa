<?php

declare(strict_types=1);

namespace Modules\Admin\Actions;

use App\Support\Concerns\AsAction;
use Modules\Admin\Support\AdminReportCache;
use Modules\Admin\Support\AdminReportCatalog;

final class WarmAdminReportCachesAction
{
    use AsAction;

    private const RANGES = ['7d', '30d', '90d', '365d'];

    public function handle(): int
    {
        $action = GetAdminReportDataAction::make();
        $warmed = 0;

        foreach (AdminReportCatalog::categories() as $category) {
            foreach ($category['reports'] as $report) {
                foreach (self::RANGES as $range) {
                    $action->handle($category['slug'], $report['slug'], $range, forceFresh: true);
                    $warmed++;
                }
            }
        }

        AdminReportCache::markWarmed($warmed);

        return $warmed;
    }
}
