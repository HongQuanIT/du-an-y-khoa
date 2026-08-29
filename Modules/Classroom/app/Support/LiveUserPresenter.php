<?php

declare(strict_types=1);

namespace Modules\Classroom\Support;

use App\Models\User;

final class LiveUserPresenter
{
    /**
     * @return array{
     *     id: int|null,
     *     name: string|null,
     *     avatar_url: string|null,
     *     avatar_initial: string,
     *     career_role: string|null,
     *     specialty: string|null,
     *     institution: string|null
     * }
     */
    public static function toArray(?User $user): array
    {
        if ($user === null) {
            return [
                'id' => null,
                'name' => null,
                'avatar_url' => null,
                'avatar_initial' => '?',
                'career_role' => null,
                'specialty' => null,
                'institution' => null,
            ];
        }

        return [
            'id' => (int) $user->getKey(),
            'name' => $user->name,
            'avatar_url' => $user->avatarUrl(),
            'avatar_initial' => $user->avatarInitial(),
            'career_role' => self::nullableString($user->career_role),
            'specialty' => self::nullableString($user->specialty),
            'institution' => self::nullableString($user->institution),
        ];
    }

    private static function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
