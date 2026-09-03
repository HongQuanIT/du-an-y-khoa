<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Policies;

use App\Models\User;
use Modules\AiAssistant\Models\AiThread;

final class AiThreadPolicy
{
    public function view(User $user, AiThread $thread): bool
    {
        return $thread->isOwnedBy($user);
    }

    public function update(User $user, AiThread $thread): bool
    {
        return $thread->isOwnedBy($user);
    }
}
