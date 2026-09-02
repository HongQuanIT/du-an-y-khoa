<?php

declare(strict_types=1);

namespace Modules\Billing\Jobs;

use App\Support\Queue\Concerns\HasQueueDisplayName;
use App\Support\Queue\QueueName;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Billing\Actions\ExpireStaleCheckoutSessionsAction;

final class ReconcilePendingCheckoutsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use HasQueueDisplayName;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    /** Chỉ một lần reconcile mỗi 4 phút (schedule 5 phút). */
    public int $uniqueFor = 240;

    public function __construct()
    {
        $this->onQueue(QueueName::Billing->value);
    }

    public function displayName(): string
    {
        return 'billing:reconcile-pending-checkouts';
    }

    public function uniqueId(): string
    {
        return 'reconcile-pending-checkouts';
    }

    public function handle(ExpireStaleCheckoutSessionsAction $expire): void
    {
        $expire->handle();
    }

    /** @return array<int, string> */
    public function tags(): array
    {
        return $this->featureTags('billing', 'reconcile', 'checkouts');
    }
}
