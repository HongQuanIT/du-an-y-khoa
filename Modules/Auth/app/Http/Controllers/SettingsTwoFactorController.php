<?php

declare(strict_types=1);

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Auth\Instructor;
use App\Support\Auth\Staff;
use App\Support\Auth\StudentTwoFactorDevice;
use App\Support\Auth\TwoFactorSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Auth\Actions\BeginTwoFactorSetupAction;
use Modules\Auth\Actions\ConfirmTwoFactorSetupAction;
use Modules\Auth\Actions\DisableTwoFactorAction;

/**
 * Learner/instructor opt-in TOTP from Settings (not mandatory).
 */
final class SettingsTwoFactorController extends Controller
{
    public function showSetup(Request $request, BeginTwoFactorSetupAction $begin): View|RedirectResponse
    {
        $user = $this->nonStaffUser($request);

        if ($user->hasTwoFactorEnabled()) {
            return redirect()
                ->route('profile.show', ['tab' => 'security'])
                ->with('status', 'Xác thực hai bước đã được bật.');
        }

        $setup = $begin->handle($user);

        return view('auth::two-factor-setup', [
            'qr' => $setup['qr'],
            'secret' => $setup['secret'],
        ]);
    }

    public function confirmSetup(Request $request, ConfirmTwoFactorSetupAction $confirm): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string'],
        ]);

        $user = $this->nonStaffUser($request);

        $recoveryCodes = $confirm->handle($user, (string) $request->input('code'));

        TwoFactorSession::confirm($request);

        if (! Instructor::is($user)) {
            StudentTwoFactorDevice::queue($user);
        }

        $request->session()->flash('two_factor_recovery_codes', $recoveryCodes);

        return redirect()->route('settings.2fa.recovery');
    }

    public function showRecovery(Request $request): View|RedirectResponse
    {
        $this->nonStaffUser($request);

        $codes = $request->session()->get('two_factor_recovery_codes');

        if (! is_array($codes) || $codes === []) {
            return redirect()->route('profile.show', ['tab' => 'security']);
        }

        return view('auth::two-factor-recovery', [
            'codes' => $codes,
        ]);
    }

    public function finishRecovery(Request $request): RedirectResponse
    {
        $this->nonStaffUser($request);

        $request->session()->forget('two_factor_recovery_codes');

        return redirect()
            ->route('profile.show', ['tab' => 'security'])
            ->with('status', 'Đã bật xác thực hai bước.');
    }

    public function disable(Request $request, DisableTwoFactorAction $disable): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'string'],
        ]);

        $user = $this->nonStaffUser($request);

        $disable->handle($user, (string) $request->input('current_password'), $request);

        return redirect()
            ->route('profile.show', ['tab' => 'security'])
            ->with('status', 'Đã tắt xác thực hai bước.');
    }

    /**
     * @return \App\Models\User
     */
    private function nonStaffUser(Request $request)
    {
        $user = $request->user();
        assert($user !== null);

        if (Staff::isStaff($user)) {
            abort(403);
        }

        return $user;
    }
}
