<?php

declare(strict_types=1);

namespace Modules\StudyPlan\Console;

use Illuminate\Console\Command;
use Modules\StudyPlan\Jobs\ReplanActivePlansJob;

/**
 * Manual trigger for the nightly replan (also useful in staging).
 */
final class ReplanStudyPlansCommand extends Command
{
    protected $signature = 'study-plan:replan';

    protected $description = 'Dồn nhiệm vụ bị lỡ và ưu tiên chủ đề yếu cho các kế hoạch adaptive';

    public function handle(): int
    {
        ReplanActivePlansJob::dispatchSync();

        $this->info('Đã chạy replan cho các kế hoạch adaptive đang hoạt động.');

        return self::SUCCESS;
    }
}
