<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Audit;

use App\Models\UserActivitySession;
use App\Support\Audit\UserActivityPresenter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class UserActivityPresenterTest extends TestCase
{
    #[Test]
    public function it_describes_user_detail_not_generic_admin(): void
    {
        $activity = new UserActivitySession([
            'area' => '/admin/users/{id}',
            'portal' => 'admin',
            'started_at' => now()->subMinutes(5),
            'last_seen_at' => now()->subMinutes(2),
            'duration_seconds' => 0,
            'ip' => '127.0.0.1',
            'device_name' => 'Mac',
            'operating_system' => 'macOS',
            'browser' => 'Chrome',
        ]);

        $row = UserActivityPresenter::present($activity);

        $this->assertSame('2 phút trước', $row['when']);
        $this->assertSame('Đã mở chi tiết người dùng', $row['summary']);
        $this->assertStringContainsString('Cổng quản trị', $row['detail']);
        $this->assertStringContainsString('Chrome trên macOS', $row['detail']);
    }

    #[Test]
    public function it_maps_teach_studio_paths(): void
    {
        $this->assertSame(
            'studio live',
            UserActivityPresenter::placeLabel(
                '/teach/classes/01M1DZKHPVFQBVRSW07RTGKQPV/sessions/01M1KEZ3SGMNZVQ0Y1P26D09CX/studio',
            ),
        );
    }
}
