<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Enums;

use App\Support\Enums\Concerns\EnumValues;

/**
 * QA outcome for content-editor submit events (working-copy quality).
 *
 * Chỉ hai nhãn vận hành: Soạn đạt / Soạn lỗi.
 * «Trả về oan» không cần nhãn riêng — suy ra khi bản gửi Soạn đạt mà pipeline vẫn trả về
 * (đồng thời cờ đỏ gắn sai / GV over_reject đã ghi trên actor kia).
 */
enum EditorSubmitOutcome: string
{
    use EnumValues;

    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case NeedsRework = 'needs_rework';
    case Inconclusive = 'inconclusive';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Chưa đánh giá',
            self::Confirmed => 'Soạn đạt',
            self::NeedsRework => 'Soạn lỗi',
            self::Inconclusive => 'Chưa rõ',
        };
    }

    public function isIncorrect(): bool
    {
        return $this === self::NeedsRework;
    }
}
