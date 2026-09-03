<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Contracts;

interface AiTutorClient
{
    /**
     * Stream a tutor reply.
     *
     * @param  string  $system  System prompt (server-owned, never exposed to FE).
     * @param  array<int, array{role: string, content: string}>  $messages  Conversation so far.
     * @param  callable(string): void  $onDelta  Invoked with each text chunk as it arrives.
     * @param  callable(): bool|null  $shouldStop  Optional cancellation probe checked between chunks.
     */
    public function stream(string $system, array $messages, callable $onDelta, ?callable $shouldStop = null): TutorReply;
}
