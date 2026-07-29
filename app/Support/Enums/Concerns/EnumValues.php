<?php

declare(strict_types=1);

namespace App\Support\Enums\Concerns;

/**
 * Helper for backed enums: value lists and select options.
 *
 * @mixin \BackedEnum
 */
trait EnumValues
{
    /**
     * All backing values of the enum.
     *
     * @return array<int, string|int>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case) => $case->value, self::cases());
    }

    /**
     * All case names of the enum.
     *
     * @return array<int, string>
     */
    public static function names(): array
    {
        return array_map(static fn (self $case) => $case->name, self::cases());
    }

    /**
     * Map of value => label for building select inputs.
     *
     * @return array<string|int, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = method_exists($case, 'label')
                ? $case->label()
                : $case->name;
        }

        return $options;
    }
}
