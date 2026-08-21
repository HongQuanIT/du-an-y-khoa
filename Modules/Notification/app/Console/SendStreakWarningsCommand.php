<?php

declare(strict_types=1);

namespace Modules\Notification\Console;

use Illuminate\Console\Command;
use Modules\Notification\Actions\SendStreakWarningsAction;

final class SendStreakWarningsCommand extends Command
{
    protected $signature = 'notification:streak-warnings';

    protected $description = 'Cảnh báo học viên có streak nhưng chưa đạt daily goal hôm nay';

    public function handle(SendStreakWarningsAction $send): int
    {
        $sent = $send->handle();

        $this->info(sprintf('Đã gửi %d cảnh báo streak.', $sent));

        return self::SUCCESS;
    }
}
