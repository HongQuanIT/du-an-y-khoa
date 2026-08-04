<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Enums;

use App\Support\Enums\Concerns\EnumValues;

enum SessionSource: string
{
    use EnumValues;

    case Custom = 'custom';
    case WeakTopics = 'weak_topics';
    case StudyPlan = 'study_plan';
    case Exam = 'exam';
    case SelfAssessment = 'self_assessment';
}
