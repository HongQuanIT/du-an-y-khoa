<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\AiAssistant\Actions\RunTutorReplyAction;
use Modules\AiAssistant\Models\AiMessage;
use Modules\AiAssistant\Models\AiThread;

/**
 * Runs the tutor generation on a Horizon worker and streams deltas over Reverb,
 * so no PHP-FPM request worker is held open for the whole generation.
 */
final class StreamAiTutorReplyJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(
        public string $threadId,
        public string $assistantMessageId,
    ) {
        $this->onQueue((string) config('aiassistant.queue', 'default'));
    }

    public function handle(RunTutorReplyAction $action): void
    {
        $thread = AiThread::query()->find($this->threadId);
        $assistant = AiMessage::query()->find($this->assistantMessageId);

        if ($thread === null || $assistant === null) {
            return;
        }

        $action->handle($thread, $assistant, broadcast: true);
    }
}
