<?php

declare(strict_types=1);

namespace Modules\Notification\Console;

use Illuminate\Console\Command;
use Modules\Notification\Actions\SendLiveUpcomingRemindersAction;

final class SendLiveUpcomingRemindersCommand extends Command
{
    protected $signature = 'notification:live-upcoming';

    protected $description = 'Nhắc thành viên lớp khi buổi live sắp bắt đầu (lead window trong config)';

    public function handle(SendLiveUpcomingRemindersAction $send): int
    {
        $sent = $send->handle();

        $this->info(sprintf('Đã gửi %d thông báo live upcoming.', $sent));

        return self::SUCCESS;
    }
}
