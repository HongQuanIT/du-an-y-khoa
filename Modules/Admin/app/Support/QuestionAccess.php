<?php

declare(strict_types=1);

namespace Modules\Admin\Support;

use App\Models\User;
use App\Support\Enums\Permission;
use Illuminate\Database\Eloquent\Builder;
use Modules\QuestionBank\Models\Question;

final class QuestionAccess
{
    /** Publisher / final approver on admin portal (question.publish). */
    public static function canPublish(User $user): bool
    {
        return $user->can(Permission::QuestionPublish->value);
    }

    public static function canEdit(User $user): bool
    {
        return $user->can(Permission::QuestionUpdate->value);
    }

    public static function canCreate(User $user): bool
    {
        return $user->can(Permission::QuestionCreate->value);
    }

    /**
     * Open the admin question workspace (list, form, stats).
     * `question.update` is enough to edit — do not require `question.view` or `topic.view`.
     */
    public static function canAccessWorkspace(User $user): bool
    {
        return $user->can(Permission::QuestionView->value)
            || $user->can(Permission::QuestionUpdate->value)
            || $user->can(Permission::QuestionCreate->value)
            || $user->can(Permission::QuestionPublish->value);
    }

    public static function authorizeWorkspace(User $user): void
    {
        abort_unless(self::canAccessWorkspace($user), 403);
    }

    /** Spatie `permission:a|b` — any of these opens GET question admin routes. */
    public static function workspacePermissionMiddleware(): string
    {
        return implode('|', [
            Permission::QuestionView->value,
            Permission::QuestionUpdate->value,
            Permission::QuestionCreate->value,
            Permission::QuestionPublish->value,
        ]);
    }

    public static function canSubmit(User $user): bool
    {
        return $user->can(Permission::QuestionSubmit->value);
    }

    public static function canRetire(User $user): bool
    {
        return $user->can(Permission::QuestionRetire->value);
    }

    /**
     * @deprecated Use canPublish() — kept for blade/controllers still naming "reviewer".
     */
    public static function isReviewer(User $user): bool
    {
        return self::canPublish($user);
    }

    /** @param Builder<Question> $query */
    public static function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (self::canPublish($user)) {
            return $query;
        }

        return $query->where('created_by', $user->getKey());
    }

    public static function canView(User $user, Question $question): bool
    {
        return self::canPublish($user)
            || (int) $question->created_by === (int) $user->getKey();
    }

    public static function authorizeView(User $user, Question $question): void
    {
        abort_unless(self::canView($user, $question), 404);
    }

    public static function authorizeEdit(User $user, Question $question): void
    {
        self::authorizeView($user, $question);
        abort_unless(self::canEdit($user), 403, 'Cần quyền question.update để chỉnh sửa câu hỏi.');
    }
}
