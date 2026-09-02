<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AuditLog;
use App\Support\Queue\Concerns\HasQueueDisplayName;
use App\Support\Queue\QueueName;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class RecordAuditLogJob implements ShouldQueueAfterCommit
{
    use Dispatchable;
    use HasQueueDisplayName;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [5, 30, 120];

    /** @param array<string, mixed> $attributes */
    public function __construct(public readonly array $attributes)
    {
        $this->onQueue((string) config('audit.queue', QueueName::Audit->value));
    }

    public function displayName(): string
    {
        $action = (string) ($this->attributes['action'] ?? 'unknown');
        $eventId = (string) ($this->attributes['event_id'] ?? 'no-event-id');

        return sprintf('audit:log:%s:%s', $action, $eventId);
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
        $action = (string) ($this->attributes['action'] ?? 'unknown');
        $eventId = (string) ($this->attributes['event_id'] ?? 'no-event-id');

        return $this->featureTags('audit', $action, 'event:'.$eventId);
    }
}
