<?php

declare(strict_types=1);

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Audit\Auditor;
use App\Support\Audit\Enums\AuditAction;
use App\Support\Auth\HomePath;
use App\Support\Auth\Instructor;
use App\Support\Auth\Partner;
use App\Support\Auth\PortalRedirect;
use App\Support\Auth\Staff;
use App\Support\Auth\TwoFactorGate;
use App\Support\Auth\TwoFactorSession;
use App\Support\Auth\WebSessionManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Modules\Auth\Actions\RegisterUserAction;
use Modules\Auth\Data\RegisterData;
use Modules\Auth\Enums\AuthenticationMethod;
use Modules\Auth\Enums\LoginPortal;
use Modules\Auth\Enums\SocialProvider;
use Modules\Auth\Models\SocialAccount;
use Modules\Auth\Support\RegistrationAttribution;
use Modules\Billing\Support\CheckoutIntent;
use Modules\Partner\Support\PartnerInviteIntent;
use Throwable;

final class SocialAuthController extends Controller
{
    private const MODE_KEY = 'social_auth.mode';

    public function redirect(Request $request, string $provider): RedirectResponse
    {
        $socialProvider = $this->provider($provider);
        $this->ensureConfigured($socialProvider);
        $validated = $request->validate([
            'mode' => ['required', 'in:login,register'],
        ]);

        RegistrationAttribution::capture($request);
        PartnerInviteIntent::capture($request);
        CheckoutIntent::capture($request);

        $request->session()->put(self::MODE_KEY, $validated['mode']);

        return Socialite::driver($socialProvider->value)->redirect();
    }

    public function callback(Request $request, string $provider, RegisterUserAction $register): RedirectResponse
    {
        $socialProvider = $this->provider($provider);
        $this->ensureConfigured($socialProvider);
        $mode = (string) $request->session()->pull(self::MODE_KEY, 'login');

        try {
            $providerUser = Socialite::driver($socialProvider->value)->user();
        } catch (Throwable $exception) {
            Log::warning('Social authentication callback failed.', [
                'provider' => $socialProvider->value,
                'exception' => $exception::class,
            ]);

            return $this->errorRedirect($mode, 'Không thể xác thực với '.$socialProvider->label().'. Vui lòng thử lại.');
        }

        $identity = $this->identity($socialProvider, $providerUser);
        if ($identity === null) {
            return $this->errorRedirect($mode, $socialProvider->label().' không cung cấp email. Vui lòng cấp quyền email hoặc đăng ký bằng email.');
        }

        $account = SocialAccount::query()
            ->with('user')
            ->where('provider', $identity['provider'])
            ->where('provider_user_id', $identity['provider_user_id'])
            ->first();

        if ($account !== null) {
            $account->update([
                'provider_email' => $identity['email'],
                'provider_name' => $identity['name'],
                'avatar_url' => $identity['avatar_url'],
                'last_login_at' => now(),
            ]);

            return $this->login($request, $account->user, $socialProvider);
        }

        $existingUser = User::query()->where('email', $identity['email'])->first();
        if ($existingUser !== null) {
            if ($existingUser->isSuspendedOrBanned()) {
                return $this->errorRedirect('login', 'Tài khoản đã bị khóa hoặc cấm. Liên hệ hỗ trợ nếu cần.');
            }
            if (Staff::isStaff($existingUser) || Instructor::is($existingUser) || Partner::is($existingUser)) {
                return $this->errorRedirect('login', 'Tài khoản này không đăng nhập tại cổng học viên.');
            }

            $providerAccount = $existingUser->socialAccounts()
                ->where('provider', $identity['provider'])
                ->first();
            if ($providerAccount !== null && $providerAccount->provider_user_id !== $identity['provider_user_id']) {
                return $this->errorRedirect($mode, 'Tài khoản đã liên kết với một tài khoản '.$socialProvider->label().' khác.');
            }

            $existingUser->socialAccounts()->updateOrCreate(
                ['provider' => $identity['provider']],
                [
                    'provider_user_id' => $identity['provider_user_id'],
                    'provider_email' => $identity['email'],
                    'provider_name' => $identity['name'],
                    'avatar_url' => $identity['avatar_url'],
                    'last_login_at' => now(),
                ],
            );

            return $this->login($request, $existingUser, $socialProvider);
        }

        if (! setting('features.registration_enabled', true)) {
            return $this->errorRedirect($mode, 'Hệ thống hiện tạm dừng đăng ký tài khoản mới.');
        }

        $user = $register->handle(new RegisterData(
            name: $identity['name'],
            email: $identity['email'],
            password: Str::password(64),
            inviteCode: PartnerInviteIntent::resolveForRegistration($request),
            attribution: RegistrationAttribution::get($request),
            registrationMethod: $socialProvider->value,
        ));
        $user->forceFill(['email_verified_at' => now()])->save();
        $user->socialAccounts()->create([
            'provider' => $identity['provider'],
            'provider_user_id' => $identity['provider_user_id'],
            'provider_email' => $identity['email'],
            'provider_name' => $identity['name'],
            'avatar_url' => $identity['avatar_url'],
            'last_login_at' => now(),
        ]);
        PartnerInviteIntent::clear($request);

        return $this->login($request, $user, $socialProvider, true);
    }

    /** @return array{provider: string, provider_user_id: string, email: string, name: string, avatar_url: ?string}|null */
    private function identity(SocialProvider $provider, SocialiteUser $user): ?array
    {
        $id = trim((string) $user->getId());
        $email = mb_strtolower(trim((string) $user->getEmail()));
        if ($id === '' || $email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        $name = Str::squish((string) ($user->getName() ?: Str::before($email, '@')));

        return [
            'provider' => $provider->value,
            'provider_user_id' => $id,
            'email' => $email,
            'name' => mb_substr($name, 0, 255),
            'avatar_url' => $user->getAvatar(),
        ];
    }

    private function login(Request $request, User $user, SocialProvider $provider, bool $newUser = false): RedirectResponse
    {
        if ($user->isSuspendedOrBanned()) {
            return $this->errorRedirect('login', 'Tài khoản đã bị khóa hoặc cấm. Liên hệ hỗ trợ nếu cần.');
        }
        if (Staff::isStaff($user) || Instructor::is($user) || Partner::is($user)) {
            return $this->errorRedirect('login', 'Tài khoản này không đăng nhập tại cổng học viên.');
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        TwoFactorSession::clear($request);
        WebSessionManager::bindToUser($user, $request);
        $user->recordSuccessfulLogin(AuthenticationMethod::from($provider->value));
        Auditor::record(AuditAction::AuthLogin, $user, $user, metadata: [
            'login_portal' => LoginPortal::Student->value,
            'provider' => $provider->value,
        ]);

        if ($user->hasTwoFactorEnabled() && ! TwoFactorGate::isSatisfied($request, $user)) {
            return redirect()->route('student.2fa.challenge');
        }

        if ($newUser || $user->learnerProfile?->onboarding_completed_at === null) {
            return redirect()->route('onboarding.profile');
        }

        return PortalRedirect::afterLogin($request, HomePath::for($user), LoginPortal::Student);
    }

    private function errorRedirect(string $mode, string $message): RedirectResponse
    {
        return redirect()->route($mode === 'register' ? 'register' : 'login')
            ->withErrors(['social' => $message]);
    }

    private function provider(string $provider): SocialProvider
    {
        return SocialProvider::tryFrom($provider) ?? abort(404);
    }

    private function ensureConfigured(SocialProvider $provider): void
    {
        $config = config('services.'.$provider->value, []);
        abort_unless(
            (bool) ($config['enabled'] ?? false)
            && filled($config['client_id'] ?? null)
            && filled($config['client_secret'] ?? null),
            404,
        );
    }
}
