<?php

declare(strict_types=1);

namespace Modules\Notification\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Modules\Notification\Actions\SendStudyPlanReminderEmailsAction;

final class SendStudyPlanRemindersCommand extends Command
{
    protected $signature = 'notification:study-plan-reminders {--date= : Ngày nhắc (Y-m-d), mặc định hôm nay}';

    protected $description = 'Gửi email nhắc nhiệm vụ Study Plan cho user bật email_plan';

    public function handle(SendStudyPlanReminderEmailsAction $send): int
    {
        $dateOption = $this->option('date');
        $date = is_string($dateOption) && $dateOption !== ''
            ? Carbon::parse($dateOption)->startOfDay()
            : Carbon::today();

        $sent = $send->handle($date);

        $this->info(sprintf('Đã gửi %d email nhắc kế hoạch học (%s).', $sent, $date->toDateString()));

        return self::SUCCESS;
    }
}
