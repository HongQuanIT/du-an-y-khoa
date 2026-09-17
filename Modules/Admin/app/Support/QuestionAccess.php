<?php

declare(strict_types=1);

namespace Modules\Admin\Support;

use App\Models\User;
use App\Support\Enums\Permission;
use Illuminate\Database\Eloquent\Builder;
use Modules\QuestionBank\Enums\QuestionStatus;
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

    public static function canFlag(User $user): bool
    {
        return $user->can(Permission::QuestionFlag->value);
    }

    /** Open the admin question workspace (list, form, stats). */
    public static function canAccessWorkspace(User $user): bool
    {
        return $user->can(Permission::QuestionView->value);
    }

    public static function authorizeWorkspace(User $user): void
    {
        abort_unless(self::canAccessWorkspace($user), 403);
    }

    /** Spatie `permission:a|b` — any of these opens GET question admin routes. */
    public static function workspacePermissionMiddleware(): string
    {
        return Permission::QuestionView->value;
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
        if ($user->can(Permission::QuestionView->value)) {
            if ($user->can('question.view_any') || self::canPublish($user)) {
                return $query;
            }

            return $query->where('created_by', $user->getKey());
        }

        // Reviewer: chỉ question.flag — hàng đợi gắn cờ + câu mình đã gắn.
        if (self::canFlag($user) && ! self::canEdit($user)) {
            $userId = (int) $user->getKey();

            return $query->where(function (Builder $builder) use ($userId): void {
                $builder
                    ->where('status', QuestionStatus::InFlagReview->value)
                    ->orWhere('reviewer_1_id', $userId)
                    ->orWhere('reviewer_2_id', $userId);
            });
        }

        return $query->whereRaw('1 = 0');
    }

    public static function canView(User $user, Question $question): bool
    {
        if ($user->can(Permission::QuestionView->value)) {
            if ($user->can('question.view_any') || self::canPublish($user)) {
                return true;
            }

            if ((int) $question->created_by === (int) $user->getKey()
                && $user->canAny([
                    Permission::QuestionView->value,
                    Permission::QuestionUpdate->value,
                    'question_version.view',
                    'question.export',
                ])) {
                return true;
            }
        }

        // Reviewer có question.flag (không cần question.view) xem câu chờ gắn cờ / đã gắn.
        if (self::canFlag($user)) {
            $userId = (int) $user->getKey();

            return $question->status === QuestionStatus::InFlagReview
                || (int) $question->reviewer_1_id === $userId
                || (int) $question->reviewer_2_id === $userId;
        }

        return false;
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
