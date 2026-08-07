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
     * @param  array<string, mixed>|null  $question
     */
    public function __construct(
        public LiveSession $session,
        public int $index,
        public bool $showAnswer,
        public ?array $question = null,
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
            'question' => $this->question,
        ];
    }
}
