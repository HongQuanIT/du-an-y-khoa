<?php

declare(strict_types=1);

namespace Modules\Admin\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/** Signed detail links for accounts that may look up a learner but not browse the directory. */
final class UserDetailLink
{
    public static function to(?User $actor, string $route, User $target): string
    {
        if ($actor?->can('user.view')) {
            return route($route, $target);
        }

        return URL::temporarySignedRoute($route, now()->addHours(2), $target);
    }

    public static function assert(Request $request, User $actor): void
    {
        if ($actor->can('user.view')) {
            return;
        }

        abort_unless($request->hasValidSignature(), 403);
    }
}
