<?php

declare(strict_types=1);

namespace Modules\Classroom\Support;

use App\Models\User;

final class LiveUserPresenter
{
    /**
     * @return array{id: int|null, name: string|null, avatar_url: string|null, avatar_initial: string}
     */
    public static function toArray(?User $user): array
    {
        if ($user === null) {
            return [
                'id' => null,
                'name' => null,
                'avatar_url' => null,
                'avatar_initial' => '?',
            ];
        }

        return [
            'id' => (int) $user->getKey(),
            'name' => $user->name,
            'avatar_url' => $user->avatarUrl(),
            'avatar_initial' => $user->avatarInitial(),
        ];
    }
}
