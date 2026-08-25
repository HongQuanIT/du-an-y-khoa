<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AuditLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class RecordAuditLogJob implements ShouldQueueAfterCommit
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [5, 30, 120];

    /** @param array<string, mixed> $attributes */
    public function __construct(public readonly array $attributes)
    {
        $this->onQueue((string) config('audit.queue', 'default'));
    }

    public function handle(): void
    {
        AuditLog::query()->firstOrCreate(
            ['event_id' => $this->attributes['event_id']],
            $this->attributes,
        );
    }

    /** @return array<int, string> */
    public function tags(): array
    {
        return ['audit', 'audit:'.$this->attributes['action']];
    }
}
