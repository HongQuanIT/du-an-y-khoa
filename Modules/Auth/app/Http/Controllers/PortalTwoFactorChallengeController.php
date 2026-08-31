<?php

declare(strict_types=1);

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Auth\HomePath;
use App\Support\Auth\PortalRedirect;
use App\Support\Auth\TwoFactorGate;
use App\Support\Auth\TwoFactorTrustedDevice;
use App\Support\Auth\TwoFactorSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Auth\Actions\VerifyTwoFactorCodeAction;
use Modules\Auth\Enums\LoginPortal;

/**
 * Shared TOTP challenge UI for instructor and partner portals.
 */
final class PortalTwoFactorChallengeController extends Controller
{
    public function showTeach(Request $request): View|RedirectResponse
    {
        return $this->show($request, LoginPortal::Instructor, route('teach.dashboard', absolute: false));
    }

    public function verifyTeach(Request $request, VerifyTwoFactorCodeAction $verify): RedirectResponse
    {
        return $this->verify($request, $verify, LoginPortal::Instructor, route('teach.dashboard', absolute: false));
    }

    public function showPartner(Request $request): View|RedirectResponse
    {
        return $this->show($request, LoginPortal::Partner, route('partner.dashboard', absolute: false));
    }

    public function verifyPartner(Request $request, VerifyTwoFactorCodeAction $verify): RedirectResponse
    {
        return $this->verify($request, $verify, LoginPortal::Partner, route('partner.dashboard', absolute: false));
    }

    private function show(Request $request, LoginPortal $portal, string $home): View|RedirectResponse
    {
        $user = $request->user();
        assert($user !== null);

        if (! $user->hasTwoFactorEnabled()) {
            return PortalRedirect::afterLogin($request, $home, $portal);
        }

        if (TwoFactorGate::isSatisfied($request, $user)) {
            TwoFactorGate::confirmIfTrusted($request, $user);

            return PortalRedirect::afterLogin($request, $home, $portal);
        }

        return view('auth::two-factor-challenge', [
            'verifyUrl' => match ($portal) {
                LoginPortal::Instructor => route('teach.2fa.challenge.verify'),
                LoginPortal::Partner => route('partner.2fa.challenge.verify'),
                default => route('student.2fa.challenge.verify'),
            },
            'logoutUrl' => match ($portal) {
                LoginPortal::Instructor => route('teach.logout'),
                LoginPortal::Partner => route('partner.logout'),
                default => route('logout'),
            },
        ]);
    }

    private function verify(
        Request $request,
        VerifyTwoFactorCodeAction $verify,
        LoginPortal $portal,
        string $home,
    ): RedirectResponse {
        $request->validate([
            'code' => ['required', 'string'],
        ]);

        $user = $request->user();
        assert($user !== null);

        if (! $user->hasTwoFactorEnabled()) {
            return redirect()->to($home);
        }

        $verify->handle($user, (string) $request->input('code'));

        TwoFactorSession::confirm($request);
        TwoFactorTrustedDevice::queue($user);

        return PortalRedirect::afterLogin($request, $home, $portal);
    }
}
