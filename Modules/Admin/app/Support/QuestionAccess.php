<?php

declare(strict_types=1);

namespace Modules\Admin\Support;

use App\Models\User;
use App\Support\Enums\Role;
use Illuminate\Database\Eloquent\Builder;
use Modules\QuestionBank\Models\Question;

final class QuestionAccess
{
    public static function isReviewer(User $user): bool
    {
        return $user->hasAnyRole([Role::Admin->value, Role::SuperAdmin->value]);
    }

    /** @param Builder<Question> $query */
    public static function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (self::isReviewer($user)) {
            return $query;
        }

        return $query->where('created_by', $user->getKey());
    }

    public static function canView(User $user, Question $question): bool
    {
        return self::isReviewer($user)
            || (int) $question->created_by === (int) $user->getKey();
    }

    public static function authorizeView(User $user, Question $question): void
    {
        abort_unless(self::canView($user, $question), 404);
    }
}
