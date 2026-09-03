<?php

declare(strict_types=1);

namespace Modules\Classroom\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Classroom\Models\LiveSession;

final class LiveQuestionChanged implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Broadcast metadata only — full question HTML routinely exceeds Reverb's
     * default 10KB max_message_size and fails the initiating PATCH with
     * "Pusher error: Payload too large". Clients with a moderator deck render
     * locally; others refetch the question panel over HTTP.
     *
     * @param  list<int>  $revealedOptionIds
     */
    public function __construct(
        public LiveSession $session,
        public int $index,
        public bool $showAnswer,
        public array $revealedOptionIds = [],
        public ?int $actorUserId = null,
    ) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('live-session.'.$this->session->uuid),
        ];
    }

    public function broadcastAs(): string
    {
        return 'question.changed';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'index' => $this->index,
            'show_answer' => $this->showAnswer,
            'total' => count($this->session->questionIds()),
            'revealed_option_ids' => $this->revealedOptionIds,
            'actor_user_id' => $this->actorUserId,
        ];
    }
}
