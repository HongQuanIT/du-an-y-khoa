<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A single streaming frame pushed to the owning user's private channel.
 * One event class carries every frame type (start|delta|citation|done|error)
 * so the drawer only needs a single Echo listener.
 */
final class AiStreamEvent implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public const START = 'start';
    public const DELTA = 'delta';
    public const CITATION = 'citation';
    public const DONE = 'done';
    public const ERROR = 'error';

    /** @param array<string, mixed> $payload */
    public function __construct(
        public int $userId,
        public string $threadId,
        public string $messageId,
        public string $type,
        public array $payload = [],
    ) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.'.$this->userId)];
    }

    public function broadcastAs(): string
    {
        return 'ai.stream';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return array_merge([
            'thread_id' => $this->threadId,
            'message_id' => $this->messageId,
            'type' => $this->type,
        ], $this->payload);
    }
}
