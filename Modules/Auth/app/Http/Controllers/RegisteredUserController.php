<?php

declare(strict_types=1);

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Auth\HomePath;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Modules\Auth\Actions\RegisterUserAction;
use Modules\Auth\Http\Requests\RegisterRequest;

/**
 * Self-service sign up for the `web` guard (learners only).
 */
final class RegisteredUserController extends Controller
{
    public function create(): View
    {
        abort_unless(setting('features.registration_enabled', true), 404);

        return view('auth::register');
    }

    public function store(RegisterRequest $request, RegisterUserAction $action): RedirectResponse
    {
        abort_unless(setting('features.registration_enabled', true), 404);

        $user = $action->handle($request->toData());

        Auth::guard('web')->login($user);

        $request->session()->regenerate();

        return redirect()->intended(HomePath::for($user));
    }
}
