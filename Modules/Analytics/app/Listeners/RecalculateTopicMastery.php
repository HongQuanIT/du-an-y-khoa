<?php

declare(strict_types=1);

namespace Modules\Analytics\Listeners;

use Modules\Analytics\Actions\RecalculateTopicMasteryAction;
use Modules\QuestionBank\Data\QuestionSessionProgressed;

/** Keeps analytics outside the QuestionBank write-side dependency graph. */
final class RecalculateTopicMastery
{
    public function __construct(private readonly RecalculateTopicMasteryAction $recalculate) {}

    public function handle(QuestionSessionProgressed $event): void
    {
        $this->recalculate->handle($event->userId);
    }
}
