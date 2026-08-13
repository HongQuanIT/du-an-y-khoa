<?php

declare(strict_types=1);

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Auth\HomePath;
use App\Support\Auth\Instructor;
use App\Support\Auth\PortalRedirect;
use App\Support\Auth\Staff;
use App\Support\Auth\StudentTwoFactorDevice;
use App\Support\Auth\TwoFactorSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Auth\Actions\VerifyTwoFactorCodeAction;
use Modules\Auth\Enums\LoginPortal;

/**
 * Learner-only TOTP challenge after password login (skipped when device is trusted).
 */
final class StudentTwoFactorController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        assert($user !== null);

        if (Staff::isStaff($user)) {
            return redirect()->route('admin.dashboard');
        }

        if (Instructor::is($user)) {
            return redirect()->route('teach.dashboard');
        }

        if (! $user->hasTwoFactorEnabled()) {
            return redirect()->to(HomePath::for($user));
        }

        if (TwoFactorSession::isConfirmed($request) || StudentTwoFactorDevice::isTrusted($request, $user)) {
            TwoFactorSession::confirm($request);

            return redirect()->to(HomePath::for($user));
        }

        return view('auth::two-factor-challenge');
    }

    public function verify(Request $request, VerifyTwoFactorCodeAction $verify): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string'],
        ]);

        $user = $request->user();
        assert($user !== null);

        if (Staff::isStaff($user) || Instructor::is($user) || ! $user->hasTwoFactorEnabled()) {
            return redirect()->to(HomePath::for($user));
        }

        $verify->handle($user, (string) $request->input('code'));

        TwoFactorSession::confirm($request);
        StudentTwoFactorDevice::queue($user);

        return PortalRedirect::afterLogin(
            $request,
            HomePath::for($user),
            LoginPortal::Student,
        );
    }
}
