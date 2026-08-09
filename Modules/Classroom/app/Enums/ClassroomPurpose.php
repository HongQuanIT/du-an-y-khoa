<?php

declare(strict_types=1);

namespace Modules\Classroom\Enums;

use App\Support\Enums\Concerns\EnumValues;

/**
 * Why the classroom exists — drives /teach vs community catalog (Module 44 §16).
 */
enum ClassroomPurpose: string
{
    use EnumValues;

    case CommunityReview = 'community_review';
    case FeedbackReview = 'feedback_review';
    case ExamReview = 'exam_review';

    public function label(): string
    {
        return match ($this) {
            self::CommunityReview => 'Cộng đồng',
            self::FeedbackReview => 'Chữa từ feedback',
            self::ExamReview => 'Chữa đề thi',
        };
    }
}
