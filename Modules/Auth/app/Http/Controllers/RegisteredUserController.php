<?php

declare(strict_types=1);

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Auth\HomePath;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Modules\Auth\Actions\RegisterUserAction;
use Modules\Auth\Http\Requests\RegisterRequest;

/**
 * Self-service sign up for the `web` guard (learners only).
 */
final class RegisteredUserController extends Controller
{
    public function store(RegisterRequest $request, RegisterUserAction $action): RedirectResponse
    {
        $user = $action->handle($request->toData());

        Auth::guard('web')->login($user);

        $request->session()->regenerate();

        return redirect()->intended(HomePath::for($user));
    }
}
