<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Enums;

enum InstructorReviewDecision: string
{
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Approved => 'Chấp nhận',
            self::Rejected => 'Từ chối',
        };
    }
}
