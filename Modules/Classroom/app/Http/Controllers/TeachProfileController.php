<?php

declare(strict_types=1);

namespace Modules\Classroom\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Audit\AuditContext;
use App\Support\Audit\Auditor;
use App\Support\Audit\Enums\AuditAction;
use App\Support\Audit\Enums\AuditPortal;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/** Instructor account hub at `/teach/profile` (Phase A). */
final class TeachProfileController extends Controller
{
    /** @var list<string> */
    private const TABS = ['profile', 'contact', 'security', 'appearance'];

    public function show(Request $request): View
    {
        $tab = $this->normalizeTab((string) $request->query('tab', 'profile'));

        return view('classroom::teach.profile.show', [
            'tab' => $tab,
            'user' => $request->user(),
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'career_role' => ['nullable', 'string', 'max:80'],
            'specialty' => ['nullable', 'string', 'max:80'],
            'institution' => ['nullable', 'string', 'max:160'],
            'headline' => ['nullable', 'string', 'max:280'],
        ], [], [
            'name' => 'tên hiển thị',
            'career_role' => 'chức danh',
            'specialty' => 'chuyên ngành',
            'institution' => 'cơ sở / trường',
            'headline' => 'giới thiệu ngắn',
        ]);

        $user = $request->user();
        $user->forceFill($validated)->save();
        $this->audit($request, AuditAction::AccountProfileUpdated, array_keys($validated));

        return redirect()
            ->route('teach.profile.show')
            ->with('status', 'Đã cập nhật hồ sơ giảng dạy.');
    }

    public function updateContact(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ], [], [
            'name' => 'tên hiển thị',
        ]);

        $request->user()->forceFill($validated)->save();
        $this->audit($request, AuditAction::AccountProfileUpdated, array_keys($validated));

        return redirect()
            ->route('teach.profile.show', ['tab' => 'contact'])
            ->with('status', 'Đã lưu thông tin liên hệ.');
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
            return redirect()
                ->route('teach.profile.show', ['tab' => 'security'])
                ->withErrors($validator);
        }

        $request->user()->forceFill([
            'password' => $validator->validated()['password'],
        ])->save();
        $this->audit($request, AuditAction::AuthPasswordChanged);

        return redirect()
            ->route('teach.profile.show', ['tab' => 'security'])
            ->with('status', 'Đã đổi mật khẩu thành công.');
    }

    public function updateAvatar(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'avatar' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
        ], [], [
            'avatar' => 'ảnh đại diện',
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('teach.profile.show')
                ->withErrors($validator);
        }

        $user = $request->user()->fresh();
        $previousPath = $user?->getRawOriginal('avatar_path');
        $this->deleteAvatarFile(is_string($previousPath) ? $previousPath : null);

        $path = $request->file('avatar')->store('avatars/'.$user->getKey(), 'public');
        $user->forceFill(['avatar_path' => $path])->save();
        $this->audit($request, AuditAction::AccountAvatarUpdated, ['avatar_path']);

        return redirect()
            ->route('teach.profile.show')
            ->with('status', 'Đã cập nhật ảnh đại diện.');
    }

    public function destroyAvatar(Request $request): RedirectResponse
    {
        $user = $request->user()->fresh();
        $previousPath = $user?->getRawOriginal('avatar_path');
        $this->deleteAvatarFile(is_string($previousPath) ? $previousPath : null);
        $user?->forceFill(['avatar_path' => null])->save();
        $this->audit($request, AuditAction::AccountAvatarDeleted, ['avatar_path']);

        return redirect()
            ->route('teach.profile.show')
            ->with('status', 'Đã xóa ảnh đại diện.');
    }

    private function normalizeTab(string $tab): string
    {
        return in_array($tab, self::TABS, true) ? $tab : 'profile';
    }

    private function deleteAvatarFile(?string $path): void
    {
        if ($path !== null && $path !== '' && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    /** @param list<string> $changedFields */
    private function audit(Request $request, AuditAction $action, array $changedFields = []): void
    {
        Auditor::record(
            $action,
            $request->user(),
            $request->user(),
            metadata: ['changed_fields' => $changedFields],
            context: new AuditContext(portal: AuditPortal::Teach),
        );
    }
}
