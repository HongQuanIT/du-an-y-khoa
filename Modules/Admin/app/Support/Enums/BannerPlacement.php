<?php

declare(strict_types=1);

namespace Modules\Admin\Support\Enums;

use App\Support\Enums\Concerns\EnumValues;

enum BannerPlacement: string
{
    use EnumValues;

    case Landing = 'landing';
    case Dashboard = 'dashboard';
    case Both = 'both';

    public function label(): string
    {
        return match ($this) {
            self::Landing => 'Landing (công khai)',
            self::Dashboard => 'Dashboard học viên',
            self::Both => 'Landing + Dashboard',
        };
    }

    public function matches(self $requested): bool
    {
        return $this === self::Both || $this === $requested;
    }
}
