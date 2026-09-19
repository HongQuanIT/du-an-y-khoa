<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Policies;

use App\Models\User;
use Modules\QuestionBank\Models\QuestionSession;

/** Owner-only access to a learner's question session. */
final class QuestionSessionPolicy
{
    public function view(User $user, QuestionSession $session): bool
    {
        return $user->canAny(['session.start', 'session.submit', 'session.review', 'session.repeat'])
            && (int) $session->user_id === (int) $user->getKey();
    }

    public function update(User $user, QuestionSession $session): bool
    {
        return $user->can('session.submit')
            && (int) $session->user_id === (int) $user->getKey();
    }

    public function delete(User $user, QuestionSession $session): bool
    {
        return $user->can('session.delete')
            && (int) $session->user_id === (int) $user->getKey();
    }
}
