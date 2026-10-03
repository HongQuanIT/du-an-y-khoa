<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use RuntimeException;

/**
 * Public learner code: two uppercase Latin letters plus four digits.
 */
final class LearnerCode
{
    public static function isValid(string $code): bool
    {
        return preg_match('/^[A-Z]{2}\d{4}$/', self::normalize($code)) === 1;
    }

    public static function normalize(string $code): string
    {
        return strtoupper(trim($code));
    }

    public static function random(): string
    {
        $letters = chr(random_int(65, 90)).chr(random_int(65, 90));
        $digits = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);

        return $letters.$digits;
    }

    /**
     * @param  (callable(): string)|null  $source
     */
    public static function generate(?callable $source = null): string
    {
        $source ??= [self::class, 'random'];

        for ($attempt = 0; $attempt < 30; $attempt++) {
            $code = $source();

            if (self::taken($code)) {
                continue;
            }

            return $code;
        }

        throw new RuntimeException('Không tạo được mã học viên chưa trùng.');
    }

    public static function taken(string $code): bool
    {
        return User::withTrashed()->where('learner_code', $code)->exists();
    }
}
