<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Enums;

/** Auto-start presets — server picks these, never the client (see 08-ai-tutor-drawer.md §3.3). */
enum TutorPreset: string
{
    case ExplainMistake = 'explain_mistake';
    case ExplainDeeper = 'explain_deeper';
    case AnalyzeWithoutSpoiler = 'analyze_without_spoiler';
    case ExplainArticle = 'explain_article';
    case ExplainSelection = 'explain_selection';

    /** Whether this preset is allowed to see the correct answer / official explanation. */
    public function allowsSpoiler(): bool
    {
        return $this !== self::AnalyzeWithoutSpoiler;
    }
}
