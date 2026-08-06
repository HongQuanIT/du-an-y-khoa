<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Policies;

use App\Models\User;
use App\Support\Enums\Entitlement;
use App\Support\Enums\Permission;
use App\Support\Enums\Role;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Models\Question;

/**
 * Authorization for questions. Combines RBAC permission + Premium entitlement:
 * free questions are open to any authenticated student; the rest require the
 * `qbank.full` entitlement (or staff permission).
 */
final class QuestionPolicy
{
    public function view(User $user, Question $question): bool
    {
        if ($question->status !== QuestionStatus::Published) {
            return $user->hasAnyRole([
                Role::SuperAdmin->value,
                Role::Admin->value,
                Role::ContentEditor->value,
            ]) && $user->can(Permission::QuestionView->value);
        }

        if ($question->is_free || $user->hasEntitlement(Entitlement::QbankFull->value)) {
            return true;
        }

        return $user->hasAnyRole([
            Role::SuperAdmin->value,
            Role::Admin->value,
            Role::ContentEditor->value,
        ]);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::QuestionCreate->value);
    }

    public function update(User $user): bool
    {
        return $user->can(Permission::QuestionUpdate->value);
    }

    public function delete(User $user): bool
    {
        return $user->can(Permission::QuestionDelete->value);
    }

    public function publish(User $user): bool
    {
        return $user->can(Permission::QuestionPublish->value);
    }
}
