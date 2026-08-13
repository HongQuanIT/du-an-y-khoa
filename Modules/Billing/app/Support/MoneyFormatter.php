<?php

declare(strict_types=1);

namespace Modules\Billing\Support;

final class MoneyFormatter
{
    public static function vnd(int $amount): string
    {
        return number_format($amount, 0, ',', '.').'₫';
    }
}
