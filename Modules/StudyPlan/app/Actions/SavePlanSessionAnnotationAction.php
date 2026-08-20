<?php

declare(strict_types=1);

namespace Modules\StudyPlan\Actions;

use Modules\QuestionBank\Actions\SaveQuestionSessionAnnotationAction;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionSession;

/** Thin StudyPlan adapter around the Q-Bank annotation engine. */
final class SavePlanSessionAnnotationAction
{
    public function __construct(
        private readonly SaveQuestionSessionAnnotationAction $saveAnnotation,
    ) {}

    /**
     * @return array{note: string, note_html: string, stem_html: string, flagged: bool, key_info_used: bool, attending_tip_used: bool}
     */
    public function handle(
        QuestionSession $session,
        Question $question,
        ?string $note = null,
        ?string $noteHtml = null,
        ?string $stemHtml = null,
        ?bool $flagged = null,
        ?bool $keyInfoUsed = null,
        ?bool $attendingTipUsed = null,
    ): array {
        return $this->saveAnnotation->handle(
            $session,
            $question,
            $note,
            $noteHtml,
            $stemHtml,
            $flagged,
            $keyInfoUsed,
            $attendingTipUsed,
        );
    }
}
