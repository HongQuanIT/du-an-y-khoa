<?php

declare(strict_types=1);

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

final class PasswordResetLinkController extends Controller
{
    public function create(Request $request): View
    {
        return view('auth::forgot-password', [
            'email' => old('email', $request->user()?->email),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $email = $request->user()?->email ?? $request->input('email');
        $request->merge([
            'email' => mb_strtolower(trim((string) $email)),
        ]);

        $request->validate([
            'email' => ['required', 'email'],
        ], [], [
            'email' => 'email',
        ]);

        $user = User::query()->where('email', $request->string('email')->toString())->first();

        if (! $user) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Email chưa tồn tại trong hệ thống.']);
        }

        if ($user->isSuspendedOrBanned()) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Tài khoản này đang bị khóa hoặc không còn hoạt động.']);
        }

        try {
            $status = Cache::lock(
                'password-reset:'.hash('sha256', $user->email),
                30,
            )->block(
                5,
                fn () => Password::sendResetLink(['email' => $user->email]),
            );
        } catch (LockTimeoutException) {
            $status = Password::RESET_THROTTLED;
        }

        if ($status === Password::RESET_LINK_SENT) {
            return back()->with(
                'status',
                'Chúng tôi đã gửi liên kết đặt lại mật khẩu đến email của bạn.',
            );
        }

        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => __($status)]);
    }
}
