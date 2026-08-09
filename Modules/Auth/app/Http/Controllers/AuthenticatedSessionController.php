<?php

declare(strict_types=1);

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Auth\HomePath;
use App\Support\Auth\PortalRedirect;
use App\Support\Auth\TwoFactorSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Auth\Actions\AttemptLoginAction;
use Modules\Auth\Enums\LoginPortal;
use Modules\Auth\Http\Requests\LoginRequest;

/**
 * Session lifecycle for the `web` guard: student, instructor, admin portals.
 */
final class AuthenticatedSessionController extends Controller
{
    public function store(LoginRequest $request, AttemptLoginAction $action): RedirectResponse
    {
        $user = $action->handle($request->toData(), LoginPortal::Student);

        $request->session()->regenerate();
        TwoFactorSession::clear($request);

        return PortalRedirect::afterLogin(
            $request,
            HomePath::for($user),
            LoginPortal::Student,
        );
    }

    public function storeTeach(LoginRequest $request, AttemptLoginAction $action): RedirectResponse
    {
        $user = $action->handle($request->toData(), LoginPortal::Instructor);

        $request->session()->regenerate();
        TwoFactorSession::clear($request);

        return PortalRedirect::afterLogin(
            $request,
            HomePath::for($user),
            LoginPortal::Instructor,
        );
    }

    public function storeAdmin(LoginRequest $request, AttemptLoginAction $action): RedirectResponse
    {
        $user = $action->handle($request->toData(), LoginPortal::Admin);

        $request->session()->regenerate();
        TwoFactorSession::clear($request);

        if (! $user->hasTwoFactorEnabled()) {
            return redirect()->route('admin.2fa.setup');
        }

        return redirect()->route('admin.2fa.challenge');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('landing.home');
    }

    public function destroyTeach(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('teach.login');
    }

    public function destroyAdmin(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
