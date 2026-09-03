<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Contracts;

/** Result of a completed tutor generation. */
final class TutorReply
{
    /** @param array<int, array<string, mixed>> $citations */
    public function __construct(
        public readonly string $content,
        public readonly array $citations = [],
        public readonly ?int $tokensIn = null,
        public readonly ?int $tokensOut = null,
        public readonly bool $stopped = false,
    ) {}
}
