<?php

declare(strict_types=1);

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Audit\AuditContext;
use App\Support\Audit\Auditor;
use App\Support\Audit\Enums\AuditAction;
use App\Support\Audit\Enums\AuditPortal;
use App\Support\Auth\HomePath;
use App\Support\Auth\PortalRedirect;
use App\Support\Auth\StudentTwoFactorDevice;
use App\Support\Auth\TwoFactorSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Modules\Auth\Actions\AttemptLoginAction;
use Modules\Auth\Enums\LoginPortal;
use Modules\Auth\Http\Requests\LoginRequest;
use Modules\Billing\Support\CheckoutIntent;

/**
 * Session lifecycle for the `web` guard: student, instructor, admin portals.
 */
final class AuthenticatedSessionController extends Controller
{
    public function create(Request $request): View
    {
        CheckoutIntent::capture($request);

        return view('auth::login', [
            'planPriceId' => CheckoutIntent::peek($request),
        ]);
    }

    public function store(LoginRequest $request, AttemptLoginAction $action): RedirectResponse
    {
        CheckoutIntent::capture($request);

        $user = $action->handle($request->toData(), LoginPortal::Student);

        $request->session()->regenerate();
        TwoFactorSession::clear($request);

        if ($user->hasTwoFactorEnabled()) {
            if (StudentTwoFactorDevice::isTrusted($request, $user)) {
                TwoFactorSession::confirm($request);
            } else {
                return redirect()->route('student.2fa.challenge');
            }
        }

        return PortalRedirect::afterLogin(
            $request,
            HomePath::for($user),
            LoginPortal::Student,
        );
    }

    public function storeTeach(LoginRequest $request, AttemptLoginAction $action): RedirectResponse
    {
        $user = $action->handle($request->toData(), LoginPortal::Instructor);

        $request->session()->regenerate();
        TwoFactorSession::clear($request);

        return PortalRedirect::afterLogin(
            $request,
            HomePath::for($user),
            LoginPortal::Instructor,
        );
    }

    public function storeAdmin(LoginRequest $request, AttemptLoginAction $action): RedirectResponse
    {
        $user = $action->handle($request->toData(), LoginPortal::Admin);

        $request->session()->regenerate();
        TwoFactorSession::clear($request);

        if (! $user->hasTwoFactorEnabled()) {
            return redirect()->route('admin.2fa.setup');
        }

        return redirect()->route('admin.2fa.challenge');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->auditLogout($user, AuditPortal::Student);
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('landing.home');
    }

    public function destroyTeach(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->auditLogout($user, AuditPortal::Teach);
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('teach.login');
    }

    public function destroyAdmin(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->auditLogout($user, AuditPortal::Admin);
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }

    private function auditLogout(?User $user, AuditPortal $portal): void
    {
        if ($user === null) {
            return;
        }

        Auditor::record(
            AuditAction::AuthLogout,
            $user,
            $user,
            context: new AuditContext(portal: $portal),
        );
    }
}
