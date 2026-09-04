<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Audit;

use App\Support\Audit\ActivityArea;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ActivityAreaTest extends TestCase
{
    #[Test]
    public function it_normalizes_ids_and_labels_admin_screens_precisely(): void
    {
        $this->assertSame('/admin/users/{id}', ActivityArea::normalize('/admin/users/1'));
        $this->assertSame('/admin/users/{id}', ActivityArea::normalize('/admin/users/01M1DZKHPVFQBVRSW07RTGKQPV'));
        $this->assertSame('chi tiết người dùng', ActivityArea::label('/admin/users/1'));
        $this->assertSame('danh sách người dùng', ActivityArea::label('/admin/users'));
        $this->assertSame('trung tâm báo cáo', ActivityArea::label('/admin/reports'));
        $this->assertSame('bảng điều khiển quản trị', ActivityArea::label('/admin'));
        $this->assertSame('sửa câu hỏi', ActivityArea::label('/admin/questions/99/edit'));
        $this->assertNotSame('trang quản trị', ActivityArea::label('/admin/reports'));
        $this->assertNotSame('trang quản trị', ActivityArea::label('/admin/users/1'));
    }

    #[Test]
    public function it_ignores_api_noise_paths(): void
    {
        $this->assertTrue(ActivityArea::shouldIgnore('/admin/classrooms/1/live/2/api/bootstrap'));
        $this->assertFalse(ActivityArea::shouldIgnore('/admin/users/1'));
    }
}
