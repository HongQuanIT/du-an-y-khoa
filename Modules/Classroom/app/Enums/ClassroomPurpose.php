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

    public function description(): string
    {
        return match ($this) {
            self::CommunityReview => 'Lớp chữa đề cộng đồng (host Premium).',
            self::FeedbackReview => 'Chữa câu từ feedback / report QBank.',
            self::ExamReview => 'Chữa theo đề thi / kỳ thi.',
        };
    }

    public function isTeachPurpose(): bool
    {
        return $this === self::FeedbackReview || $this === self::ExamReview;
    }

    /** @return list<self> */
    public static function teachCases(): array
    {
        return [self::FeedbackReview, self::ExamReview];
    }
}
