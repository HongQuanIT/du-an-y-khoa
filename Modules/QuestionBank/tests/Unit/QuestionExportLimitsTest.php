<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Tests\Unit;

use Modules\QuestionBank\Support\QuestionExportLimits;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class QuestionExportLimitsTest extends TestCase
{
    #[Test]
    public function it_describes_truncated_exports(): void
    {
        $this->assertNull(QuestionExportLimits::notice(12, 12));
        $this->assertNull(QuestionExportLimits::banner(2000));

        $this->assertSame(
            'Đã xuất 2,000/3,500 câu (ưu tiên mới cập nhật). Thu hẹp bộ lọc hoặc chọn từng dòng để xuất đúng tập cần lấy.',
            QuestionExportLimits::notice(2000, 3500),
        );
        $this->assertSame(
            'Bộ lọc khớp 3,500 câu. Xuất Excel/CSV chỉ lấy 2,000 câu mới cập nhật nhất — thu hẹp lọc hoặc chọn từng dòng.',
            QuestionExportLimits::banner(3500),
        );
    }
}
