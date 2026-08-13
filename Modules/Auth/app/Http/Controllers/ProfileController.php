<?php

declare(strict_types=1);

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\TargetExams;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Modules\Billing\Actions\ActivateInstitutionLicenseAction;
use Modules\Billing\Actions\RedeemCodeAction;
use Modules\Billing\Actions\RenewInstitutionLicenseAction;
use Modules\Billing\Models\InstitutionMember;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Support\CurrentSubscription;
use Modules\Billing\Support\MembershipSummary;

/** Unified account hub at `/profile` (profile + settings tabs). */
final class ProfileController extends Controller
{
    /** @var list<string> */
    private const TABS = [
        'career', 'contact', 'security', 'notifications',
        'membership', 'invoices', 'redeem', 'notes', 'org-license',
    ];

    public function show(Request $request): View
    {
        $tab = $this->normalizeTab((string) $request->query('tab', 'career'));
        $user = $request->user();

        $prefs = $user->notification_prefs ?? [
            'email_session' => true,
            'email_plan' => true,
            'email_product' => false,
            'push_reminders' => true,
        ];

        $orgMembers = InstitutionMember::query()
            ->with('institution')
            ->where('user_id', $user->getKey())
            ->where('status', 'verified')
            ->get()
            ->filter(fn (InstitutionMember $member): bool => $member->institution?->isValid() ?? false);

        return view('auth::profile', [
            'tab' => $tab,
            'user' => $user,
            'prefs' => $prefs,
            'membership' => MembershipSummary::for($user),
            'currentSubscription' => CurrentSubscription::for($user),
            'invoices' => Invoice::query()
                ->where('user_id', $user->getKey())
                ->orderByDesc('issued_at')
                ->get(),
            'orgMembers' => $orgMembers,
        ]);
    }

    public function redirectLegacySettings(Request $request): RedirectResponse
    {
        $tab = $request->has('tab')
            ? $this->normalizeTab((string) $request->query('tab'))
            : 'contact';

        return redirect()->route('profile.show', $this->tabRouteParams($tab), 301);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $form = (string) $request->input('_form', 'career');

        if ($form === 'contact') {
            $validator = Validator::make($request->all(), [
                'name' => ['required', 'string', 'max:120'],
            ], [], [
                'name' => 'tên hiển thị',
            ]);

            if ($validator->fails()) {
                return redirect()
                    ->route('profile.show', $this->tabRouteParams('contact'))
                    ->withErrors($validator)
                    ->withInput();
            }

            $request->user()->forceFill($validator->validated())->save();

            return redirect()
                ->route('profile.show', $this->tabRouteParams('contact'))
                ->with('status', 'Đã lưu thông tin liên hệ.');
        }

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'career_role' => ['nullable', 'string', 'max:80'],
            'graduation_year' => ['nullable', 'integer', 'min:1990', 'max:2040'],
            'country' => ['nullable', 'string', 'max:80'],
            'institution' => ['nullable', 'string', 'max:160'],
            'specialty' => ['nullable', 'string', 'max:80'],
            'headline' => ['nullable', 'string', 'max:180'],
        ], [], [
            'career_role' => 'vai trò',
            'graduation_year' => 'năm tốt nghiệp',
            'country' => 'quốc gia',
            'institution' => 'trường / cơ sở',
            'specialty' => 'chuyên ngành',
        ]);

        if (empty($validated['name'])) {
            unset($validated['name']);
        }

        $request->user()->forceFill($validated)->save();

        return redirect()
            ->route('profile.show')
            ->with('status', 'Đã cập nhật hồ sơ nghề nghiệp.');
    }

    public function updateObjective(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'study_objective' => ['required', 'string', Rule::in(array_keys(TargetExams::selectable()))],
        ], [], [
            'study_objective' => 'mục tiêu học',
        ]);

        $request->user()->forceFill($validated)->save();

        return redirect()
            ->route('profile.show')
            ->with('status', 'Đã cập nhật mục tiêu học.');
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
                ->route('profile.show', $this->tabRouteParams('security'))
                ->withErrors($validator);
        }

        $validated = $validator->validated();

        $request->user()->forceFill([
            'password' => $validated['password'],
        ])->save();

        return redirect()
            ->route('profile.show', $this->tabRouteParams('security'))
            ->with('status', 'Đã đổi mật khẩu thành công.');
    }

    public function updateNotifications(Request $request): RedirectResponse
    {
        $request->validate([
            'email_session' => ['nullable', 'boolean'],
            'email_plan' => ['nullable', 'boolean'],
            'email_product' => ['nullable', 'boolean'],
            'push_reminders' => ['nullable', 'boolean'],
        ]);

        $request->user()->forceFill([
            'notification_prefs' => [
                'email_session' => $request->boolean('email_session'),
                'email_plan' => $request->boolean('email_plan'),
                'email_product' => $request->boolean('email_product'),
                'push_reminders' => $request->boolean('push_reminders'),
            ],
        ])->save();

        return redirect()
            ->route('profile.show', $this->tabRouteParams('notifications'))
            ->with('status', 'Đã cập nhật tùy chọn thông báo.');
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
                ->route('profile.show')
                ->withErrors($validator);
        }

        $user = $request->user()->fresh();
        $previousPath = $user?->getRawOriginal('avatar_path');
        $this->deleteAvatarFile(is_string($previousPath) ? $previousPath : null);

        $path = $request->file('avatar')->store('avatars/'.$user->getKey(), 'public');
        $user->forceFill(['avatar_path' => $path])->save();

        return redirect()
            ->route('profile.show')
            ->with('status', 'Đã cập nhật ảnh đại diện.');
    }

    public function destroyAvatar(Request $request): RedirectResponse
    {
        $user = $request->user()->fresh();
        $previousPath = $user?->getRawOriginal('avatar_path');
        $this->deleteAvatarFile(is_string($previousPath) ? $previousPath : null);
        $user?->forceFill(['avatar_path' => null])->save();

        return redirect()
            ->route('profile.show')
            ->with('status', 'Đã xóa ảnh đại diện.');
    }

    public function redeemCode(Request $request, RedeemCodeAction $redeem): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'max:64'],
        ], [], [
            'code' => 'mã kích hoạt',
        ]);

        try {
            $subscription = $redeem->handle($request->user(), (string) $request->input('code'));
        } catch (\Illuminate\Validation\ValidationException $exception) {
            return redirect()
                ->route('profile.show', $this->tabRouteParams('redeem'))
                ->withErrors($exception->errors())
                ->withInput();
        }

        $planName = $subscription->plan?->name ?? 'Premium';

        return redirect()
            ->route('profile.show', $this->tabRouteParams('membership'))
            ->with('status', "Đã kích hoạt gói {$planName} thành công.");
    }

    public function activateOrgLicense(Request $request, ActivateInstitutionLicenseAction $activate): RedirectResponse
    {
        $request->validate([
            'institution_email' => ['required', 'email', 'max:190'],
        ], [], [
            'institution_email' => 'email tổ chức',
        ]);

        try {
            $activate->handle($request->user(), (string) $request->input('institution_email'));
        } catch (\Illuminate\Validation\ValidationException $exception) {
            return redirect()
                ->route('profile.show', $this->tabRouteParams('org-license'))
                ->withErrors($exception->errors())
                ->withInput();
        }

        return redirect()
            ->route('profile.show', $this->tabRouteParams('org-license'))
            ->with('status', 'Đã kích hoạt giấy phép tổ chức.');
    }

    public function renewOrgLicense(Request $request, RenewInstitutionLicenseAction $renew): RedirectResponse
    {
        $request->validate([
            'member_id' => ['required', 'integer'],
        ]);

        try {
            $renew->handle($request->user(), (int) $request->input('member_id'));
        } catch (\Illuminate\Validation\ValidationException $exception) {
            return redirect()
                ->route('profile.show', $this->tabRouteParams('org-license'))
                ->withErrors($exception->errors());
        }

        return redirect()
            ->route('profile.show', $this->tabRouteParams('org-license'))
            ->with('status', 'Đã gia hạn giấy phép tổ chức.');
    }

    public function updateNotes(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'account_notes' => ['nullable', 'string', 'max:5000'],
        ], [], [
            'account_notes' => 'ghi chú',
        ]);

        $request->user()->forceFill([
            'account_notes' => $validated['account_notes'] ?? null,
        ])->save();

        return redirect()
            ->route('profile.show', $this->tabRouteParams('notes'))
            ->with('status', 'Đã lưu ghi chú.');
    }

    private function normalizeTab(string $tab): string
    {
        $tab = match ($tab) {
            '', 'career' => 'career',
            'account' => 'contact',
            'billing' => 'membership',
            default => $tab,
        };

        return in_array($tab, self::TABS, true) ? $tab : 'career';
    }

    /** @return array<string, string> */
    private function tabRouteParams(string $tab): array
    {
        return $tab === 'career' ? [] : ['tab' => $tab];
    }

    private function deleteAvatarFile(?string $path): void
    {
        if ($path !== null && $path !== '' && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
