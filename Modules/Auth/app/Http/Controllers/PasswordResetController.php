<?php

declare(strict_types=1);

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Audit\Auditor;
use App\Support\Audit\Enums\AuditAction;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

/**
 * Password reset form (token link from email / admin “send reset”).
 */
final class PasswordResetController extends Controller
{
    public function create(Request $request, string $token): View|RedirectResponse
    {
        $email = mb_strtolower(trim((string) $request->query('email')));
        if ($request->user() !== null && $email !== mb_strtolower($request->user()->email)) {
            return redirect()->route('password.request')
                ->withErrors(['email' => 'Bạn chỉ có thể đặt lại mật khẩu cho tài khoản đang đăng nhập.']);
        }

        return view('auth::reset-password', [
            'token' => $token,
            'email' => old('email', $email),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $email = $request->user()?->email ?? $request->input('email');
        $request->merge([
            'email' => mb_strtolower(trim((string) $email)),
        ]);

        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ], [], [
            'email' => 'email',
            'password' => 'mật khẩu mới',
        ]);

        $user = User::query()->where('email', $request->string('email')->toString())->first();
        if (! $user || $user->isSuspendedOrBanned()) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => __('passwords.token')]);
        }

        $status = Password::broker()->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                // User casts `password` => hashed — do not Hash::make again.
                $user->forceFill([
                    'password' => $password,
                    'password_set_at' => now(),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
                Auditor::record(AuditAction::AuthPasswordReset, $user, $user);
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => __($status)]);
        }

        return redirect()
            ->route('login')
            ->with('status', 'Đã đặt lại mật khẩu. Bạn có thể đăng nhập bằng mật khẩu mới.');
    }
}
