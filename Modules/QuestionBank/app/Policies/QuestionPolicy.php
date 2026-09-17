<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Policies;

use App\Models\User;
use App\Support\Enums\Entitlement;
use App\Support\Enums\Permission;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Support\ServePublishedQuestion;

/**
 * Authorization for questions. Combines RBAC permission + Premium entitlement:
 * free questions are open to any authenticated student; the rest require the
 * `qbank.full` entitlement (or staff permission).
 */
final class QuestionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::QuestionView->value);
    }

    public function view(User $user, Question $question): bool
    {
        if (! $user->can(Permission::QuestionView->value)) {
            return false;
        }

        if (! ServePublishedQuestion::isAvailable($question)) {
            return $this->canManageWorkingCopy($user);
        }

        $isFree = ServePublishedQuestion::publishedIsFree($question);

        if ($isFree || $user->hasEntitlement(Entitlement::QbankFull->value)) {
            return true;
        }

        return $this->canManageWorkingCopy($user);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::QuestionView->value)
            && $user->can(Permission::QuestionCreate->value);
    }

    public function update(User $user): bool
    {
        return $user->can(Permission::QuestionView->value)
            && $user->can(Permission::QuestionUpdate->value);
    }

    public function delete(User $user): bool
    {
        return $user->can(Permission::QuestionView->value)
            && $user->can(Permission::QuestionDelete->value);
    }

    public function publish(User $user): bool
    {
        return $user->can(Permission::QuestionView->value)
            && $user->can(Permission::QuestionPublish->value);
    }

    public function submit(User $user): bool
    {
        return $user->can(Permission::QuestionView->value)
            && $user->can(Permission::QuestionSubmit->value);
    }

    public function retire(User $user): bool
    {
        return $user->can(Permission::QuestionView->value)
            && $user->can(Permission::QuestionRetire->value);
    }

    private function canManageWorkingCopy(User $user): bool
    {
        return $user->can(Permission::QuestionView->value)
            && $user->canAny([
                Permission::QuestionUpdate->value,
                Permission::QuestionPublish->value,
                Permission::QuestionReview->value,
            ]);
    }
}
