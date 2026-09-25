<?php

declare(strict_types=1);

namespace Modules\Reviewer\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Audit\Auditor;
use App\Support\Audit\Enums\AuditAction;
use App\Support\Auth\TwoFactorSession;
use App\Support\Auth\TwoFactorTrustedDevice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Modules\Auth\Actions\BeginTwoFactorSetupAction;
use Modules\Auth\Actions\ConfirmTwoFactorSetupAction;
use Modules\Auth\Actions\DisableTwoFactorAction;

final class ReviewerProfileController extends Controller
{
    public function show(Request $request): View
    {
        return view('reviewer::profile.show', ['user' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);
        $user = $request->user();
        $user->forceFill($data)->save();
        Auditor::record(AuditAction::AccountProfileUpdated, $user, $user, metadata: ['changed_fields' => ['name']]);

        return redirect()->route('reviewer.profile.show')->with('status', 'Đã cập nhật thông tin tài khoản.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);
        $user = $request->user();
        $user->forceFill(['password' => $data['password'], 'password_set_at' => now()])->save();
        Auditor::record(AuditAction::AuthPasswordChanged, $user, $user);

        return redirect()->route('reviewer.profile.show')->with('status', 'Đã đổi mật khẩu thành công.');
    }

    public function updateAvatar(Request $request): RedirectResponse
    {
        $request->validate(['avatar' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048']]);
        $user = $request->user();
        $previous = $user->getRawOriginal('avatar_path');
        $path = $request->file('avatar')->store('avatars/'.$user->getKey(), 'public');
        $user->forceFill(['avatar_path' => $path])->save();
        if (is_string($previous) && $previous !== '') {
            Storage::disk('public')->delete($previous);
        }
        Auditor::record(AuditAction::AccountAvatarUpdated, $user, $user);

        return redirect()->route('reviewer.profile.show')->with('status', 'Đã cập nhật ảnh đại diện.');
    }

    public function destroyAvatar(Request $request): RedirectResponse
    {
        $user = $request->user();
        $previous = $user->getRawOriginal('avatar_path');
        $user->forceFill(['avatar_path' => null])->save();
        if (is_string($previous) && $previous !== '') {
            Storage::disk('public')->delete($previous);
        }
        Auditor::record(AuditAction::AccountAvatarDeleted, $user, $user);

        return redirect()->route('reviewer.profile.show')->with('status', 'Đã xóa ảnh đại diện.');
    }

    public function showTwoFactorSetup(Request $request, BeginTwoFactorSetupAction $begin): View|RedirectResponse
    {
        if ($request->user()->hasTwoFactorEnabled()) {
            return redirect()->route('reviewer.profile.show');
        }

        return view('reviewer::profile.two-factor-setup', $begin->handle($request->user()));
    }

    public function confirmTwoFactorSetup(Request $request, ConfirmTwoFactorSetupAction $confirm): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string']]);
        $codes = $confirm->handle($request->user(), (string) $request->input('code'));
        TwoFactorSession::confirm($request);
        TwoFactorTrustedDevice::queue($request->user());
        $request->session()->flash('reviewer_two_factor_recovery_codes', $codes);

        return redirect()->route('reviewer.profile.2fa.recovery');
    }

    public function showTwoFactorRecovery(Request $request): View|RedirectResponse
    {
        $codes = $request->session()->get('reviewer_two_factor_recovery_codes');
        if (! is_array($codes) || $codes === []) {
            return redirect()->route('reviewer.profile.show');
        }

        return view('reviewer::profile.two-factor-recovery', ['codes' => $codes]);
    }

    public function finishTwoFactorRecovery(Request $request): RedirectResponse
    {
        $request->session()->forget('reviewer_two_factor_recovery_codes');

        return redirect()->route('reviewer.profile.show')->with('status', 'Đã bật xác thực hai bước.');
    }

    public function disableTwoFactor(Request $request, DisableTwoFactorAction $disable): RedirectResponse
    {
        $request->validate(['current_password' => ['required', 'string']]);
        $disable->handle($request->user(), (string) $request->input('current_password'), $request);

        return redirect()->route('reviewer.profile.show')->with('status', 'Đã tắt xác thực hai bước.');
    }
}
