<?php

declare(strict_types=1);

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Auth\PortalRedirect;
use App\Support\Auth\TwoFactorSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Admin\Support\Auditor;
use Modules\Auth\Actions\BeginTwoFactorSetupAction;
use Modules\Auth\Actions\ConfirmTwoFactorSetupAction;
use Modules\Auth\Actions\VerifyTwoFactorCodeAction;
use Modules\Auth\Enums\LoginPortal;

/**
 * Admin-portal TOTP setup + challenge (mandatory for staff).
 */
final class AdminTwoFactorController extends Controller
{
    public function showSetup(Request $request, BeginTwoFactorSetupAction $begin): View|RedirectResponse
    {
        $user = $request->user();
        assert($user !== null);

        if ($user->hasTwoFactorEnabled() && TwoFactorSession::isConfirmed($request)) {
            return redirect()->route('admin.dashboard');
        }

        if ($user->hasTwoFactorEnabled()) {
            return redirect()->route('admin.2fa.challenge');
        }

        $setup = $begin->handle($user);

        return view('admin::auth.two-factor-setup', [
            'qr' => $setup['qr'],
            'secret' => $setup['secret'],
        ]);
    }

    public function confirmSetup(Request $request, ConfirmTwoFactorSetupAction $confirm): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string'],
        ]);

        $user = $request->user();
        assert($user !== null);

        $recoveryCodes = $confirm->handle($user, (string) $request->input('code'));

        TwoFactorSession::confirm($request);
        Auditor::record('admin.2fa.enabled', $user, $user);

        $request->session()->flash('two_factor_recovery_codes', $recoveryCodes);

        return redirect()->route('admin.2fa.recovery');
    }

    public function showRecovery(Request $request): View|RedirectResponse
    {
        $codes = $request->session()->get('two_factor_recovery_codes');

        if (! is_array($codes) || $codes === []) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin::auth.two-factor-recovery', [
            'codes' => $codes,
        ]);
    }

    public function finishRecovery(Request $request): RedirectResponse
    {
        $request->session()->forget('two_factor_recovery_codes');

        return PortalRedirect::afterLogin(
            $request,
            route('admin.dashboard', absolute: false),
            LoginPortal::Admin,
        );
    }

    public function showChallenge(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        assert($user !== null);

        if (! $user->hasTwoFactorEnabled()) {
            return redirect()->route('admin.2fa.setup');
        }

        if (TwoFactorSession::isConfirmed($request)) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin::auth.two-factor-challenge');
    }

    public function verifyChallenge(Request $request, VerifyTwoFactorCodeAction $verify): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string'],
        ]);

        $user = $request->user();
        assert($user !== null);

        $verify->handle($user, (string) $request->input('code'));

        TwoFactorSession::confirm($request);
        Auditor::record('admin.login', $user, $user);

        return PortalRedirect::afterLogin(
            $request,
            route('admin.dashboard', absolute: false),
            LoginPortal::Admin,
        );
    }
}
