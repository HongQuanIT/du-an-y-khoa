<?php

declare(strict_types=1);

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Auth\HomePath;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Auth\Actions\AttemptLoginAction;
use Modules\Auth\Http\Requests\LoginRequest;

/**
 * Session lifecycle for the `web` guard: log in, log out.
 */
final class AuthenticatedSessionController extends Controller
{
    public function store(LoginRequest $request, AttemptLoginAction $action): RedirectResponse
    {
        $user = $action->handle($request->toData());

        $request->session()->regenerate();

        return redirect()->intended(HomePath::for($user));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('landing.home');
    }
}
