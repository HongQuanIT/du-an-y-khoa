<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Support;

/**
 * Shared export cap + copy so the list and the downloaded file stay in sync.
 */
final class QuestionExportLimits
{
    public const MAX_ROWS = 2000;

    public static function notice(int $exported, int $matched): ?string
    {
        if ($matched <= self::MAX_ROWS) {
            return null;
        }

        return sprintf(
            'Đã xuất %s/%s câu (ưu tiên mới cập nhật). Thu hẹp bộ lọc hoặc chọn từng dòng để xuất đúng tập cần lấy.',
            number_format($exported),
            number_format($matched),
        );
    }

    public static function banner(int $matched): ?string
    {
        if ($matched <= self::MAX_ROWS) {
            return null;
        }

        return sprintf(
            'Bộ lọc khớp %s câu. Xuất Excel/CSV chỉ lấy %s câu mới cập nhật nhất — thu hẹp lọc hoặc chọn từng dòng.',
            number_format($matched),
            number_format(self::MAX_ROWS),
        );
    }
}
