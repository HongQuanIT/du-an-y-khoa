<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Data;

/**
 * Domain event emitted after persisted session progress changes.
 *
 * Analytics listens to this contract without QuestionBank depending on an
 * Analytics action or rollup model.
 */
final readonly class QuestionSessionProgressed
{
    public function __construct(
        public int $userId,
        public string $sessionId,
        public bool $completed,
    ) {}
}
