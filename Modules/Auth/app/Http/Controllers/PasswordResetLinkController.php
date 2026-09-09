<?php

declare(strict_types=1);

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $request->merge([
            'email' => mb_strtolower(trim((string) $request->input('email'))),
        ]);

        $request->validate([
            'email' => ['required', 'email'],
        ], [], [
            'email' => 'email',
        ]);

        $status = Password::sendResetLink(
            $request->only('email'),
        );

        // Do not reveal whether an email address is registered.
        if (in_array($status, [Password::RESET_LINK_SENT, Password::INVALID_USER], true)) {
            return back()->with(
                'status',
                'Nếu email tồn tại trong hệ thống, chúng tôi đã gửi liên kết đặt lại mật khẩu.',
            );
        }

        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => __($status)]);
    }
}
