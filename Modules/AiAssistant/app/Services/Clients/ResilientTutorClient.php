<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Services\Clients;

use Illuminate\Support\Facades\Log;
use Modules\AiAssistant\Contracts\AiTutorClient;
use Modules\AiAssistant\Contracts\TutorReply;
use Throwable;

/**
 * Tries the primary (OpenAI) client first; on auth/network failure falls back to
 * the deterministic FakeTutorClient so the drawer still works in local/dev when
 * the API key is missing, expired, or mistyped.
 */
final class ResilientTutorClient implements AiTutorClient
{
    public function __construct(
        private readonly AiTutorClient $primary,
        private readonly AiTutorClient $fallback,
    ) {}

    public function stream(string $system, array $messages, callable $onDelta, ?callable $shouldStop = null): TutorReply
    {
        try {
            return $this->primary->stream($system, $messages, $onDelta, $shouldStop);
        } catch (Throwable $e) {
            Log::warning('AI Tutor primary client failed; using fallback.', [
                'error' => $e->getMessage(),
                'primary' => $this->primary::class,
            ]);

            return $this->fallback->stream($system, $messages, $onDelta, $shouldStop);
        }
    }
}
