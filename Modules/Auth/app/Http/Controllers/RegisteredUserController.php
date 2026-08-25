<?php

declare(strict_types=1);

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Auth\HomePath;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Modules\Auth\Actions\RegisterUserAction;
use Modules\Auth\Http\Requests\RegisterRequest;
use Modules\Billing\Support\CheckoutIntent;

/**
 * Self-service sign up for the `web` guard (learners only).
 */
final class RegisteredUserController extends Controller
{
    public function create(Request $request): View
    {
        abort_unless(setting('features.registration_enabled', true), 404);

        CheckoutIntent::capture($request);

        return view('auth::register', [
            'planPriceId' => CheckoutIntent::peek($request),
        ]);
    }

    public function store(RegisterRequest $request, RegisterUserAction $action): RedirectResponse
    {
        abort_unless(setting('features.registration_enabled', true), 404);

        CheckoutIntent::capture($request);

        $user = $action->handle($request->toData());

        Auth::guard('web')->login($user);

        $request->session()->regenerate();

        return redirect()->intended(HomePath::for($user));
    }
}
