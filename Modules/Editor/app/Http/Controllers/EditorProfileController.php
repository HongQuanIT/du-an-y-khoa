<?php

declare(strict_types=1);

namespace Modules\Editor\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Audit\Auditor;
use App\Support\Audit\Enums\AuditAction;
use App\Support\Auth\TwoFactorSession;
use App\Support\Auth\TwoFactorTrustedDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Modules\Auth\Actions\BeginTwoFactorSetupAction;
use Modules\Auth\Actions\ConfirmTwoFactorSetupAction;
use Modules\Auth\Actions\DisableTwoFactorAction;

final class EditorProfileController extends Controller
{
    /** @var list<string> */
    private const TABS = ['profile', 'security', 'appearance'];

    public function show(Request $request): View
    {
        $tab = (string) $request->query('tab', 'profile');
        $user = $request->user()->loadMissing(['socialAccounts', 'twoFactorSecret']);

        return view('editor::profile.show', [
            'tab' => in_array($tab, self::TABS, true) ? $tab : 'profile',
            'user' => $user,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);
        $user = $request->user();
        $user->forceFill($data)->save();
        Auditor::record(AuditAction::AccountProfileUpdated, $user, $user, metadata: ['changed_fields' => array_keys($data)]);

        return redirect()->route('editor.profile.show')->with('status', 'Đã cập nhật thông tin tài khoản.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ], [], [
            'current_password' => 'mật khẩu hiện tại',
            'password' => 'mật khẩu mới',
        ]);

        if ($validator->fails()) {
            return redirect()->route('editor.profile.show', ['tab' => 'security'])->withErrors($validator);
        }

        $user = $request->user();
        $user->forceFill(['password' => $validator->validated()['password'], 'password_set_at' => now()])->save();
        Auditor::record(AuditAction::AuthPasswordChanged, $user, $user);

        return redirect()->route('editor.profile.show', ['tab' => 'security'])->with('status', 'Đã đổi mật khẩu thành công.');
    }

    public function updateAppearance(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate(['theme' => ['required', Rule::in(['light', 'dark', 'system'])]]);
        $user = $request->user();
        $before = ['theme' => $user->theme];
        $user->forceFill($data)->save();
        Auditor::record(AuditAction::AccountPreferencesUpdated, $user, $user, $before, ['theme' => $user->theme]);

        if ($request->expectsJson()) {
            return response()->json(['theme' => $user->theme]);
        }

        return redirect()->route('editor.profile.show', ['tab' => 'appearance'])->with('status', 'Đã lưu giao diện.');
    }

    public function updateAvatar(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), ['avatar' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048']], [], ['avatar' => 'ảnh đại diện']);
        if ($validator->fails()) {
            return redirect()->route('editor.profile.show')->withErrors($validator);
        }

        $user = $request->user()->fresh();
        $previousPath = $user?->getRawOriginal('avatar_path');
        $this->deleteAvatarFile(is_string($previousPath) ? $previousPath : null);
        $path = $request->file('avatar')->store('avatars/'.$user->getKey(), 'public');
        $user->forceFill(['avatar_path' => $path])->save();
        Auditor::record(AuditAction::AccountAvatarUpdated, $user, $user, ['has_avatar' => $previousPath !== null], ['has_avatar' => true]);

        return redirect()->route('editor.profile.show')->with('status', 'Đã cập nhật ảnh đại diện.');
    }

    public function destroyAvatar(Request $request): RedirectResponse
    {
        $user = $request->user()->fresh();
        $previousPath = $user?->getRawOriginal('avatar_path');
        $this->deleteAvatarFile(is_string($previousPath) ? $previousPath : null);
        $user?->forceFill(['avatar_path' => null])->save();
        if ($user !== null) {
            Auditor::record(AuditAction::AccountAvatarDeleted, $user, $user, ['has_avatar' => $previousPath !== null], ['has_avatar' => false]);
        }

        return redirect()->route('editor.profile.show')->with('status', 'Đã xóa ảnh đại diện.');
    }

    public function showTwoFactorSetup(Request $request, BeginTwoFactorSetupAction $begin): View|RedirectResponse
    {
        $user = $request->user();
        if ($user->hasTwoFactorEnabled()) {
            return redirect()->route('editor.profile.show', ['tab' => 'security'])->with('status', 'Xác thực hai bước đã được bật.');
        }

        $setup = $begin->handle($user);

        return view('editor::profile.two-factor-setup', $setup);
    }

    public function confirmTwoFactorSetup(Request $request, ConfirmTwoFactorSetupAction $confirm): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string']]);
        $codes = $confirm->handle($request->user(), (string) $request->input('code'));
        TwoFactorSession::confirm($request);
        TwoFactorTrustedDevice::queue($request->user());
        $request->session()->flash('editor_two_factor_recovery_codes', $codes);

        return redirect()->route('editor.profile.2fa.recovery');
    }

    public function showTwoFactorRecovery(Request $request): View|RedirectResponse
    {
        $codes = $request->session()->get('editor_two_factor_recovery_codes');
        if (! is_array($codes) || $codes === []) {
            return redirect()->route('editor.profile.show', ['tab' => 'security']);
        }

        return view('editor::profile.two-factor-recovery', ['codes' => $codes]);
    }

    public function finishTwoFactorRecovery(Request $request): RedirectResponse
    {
        $request->session()->forget('editor_two_factor_recovery_codes');

        return redirect()->route('editor.profile.show', ['tab' => 'security'])->with('status', 'Đã bật xác thực hai bước.');
    }

    public function disableTwoFactor(Request $request, DisableTwoFactorAction $disable): RedirectResponse
    {
        $request->validate(['current_password' => ['required', 'string']]);
        $disable->handle($request->user(), (string) $request->input('current_password'), $request);

        return redirect()->route('editor.profile.show', ['tab' => 'security'])->with('status', 'Đã tắt xác thực hai bước.');
    }

    private function deleteAvatarFile(?string $path): void
    {
        if ($path !== null && $path !== '' && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
