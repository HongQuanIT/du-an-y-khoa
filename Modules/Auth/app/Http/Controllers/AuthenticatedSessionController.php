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
use App\Support\Auth\TwoFactorGate;
use App\Support\Auth\TwoFactorSession;
use App\Support\Auth\WebSessionManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Modules\Auth\Actions\AttemptLoginAction;
use Modules\Auth\Enums\LoginPortal;
use Modules\Auth\Http\Requests\LoginRequest;
use Modules\Billing\Support\CheckoutIntent;

/**
 * Session lifecycle for the `web` guard: student, instructor, partner, admin portals.
 */
final class AuthenticatedSessionController extends Controller
{
    public function create(Request $request): View
    {
        CheckoutIntent::capture($request);
        \Modules\Partner\Support\PartnerInviteIntent::capture($request);

        return view('auth::login', [
            'planPriceId' => CheckoutIntent::peek($request),
        ]);
    }

    public function store(LoginRequest $request, AttemptLoginAction $action): RedirectResponse
    {
        CheckoutIntent::capture($request);
        \Modules\Partner\Support\PartnerInviteIntent::capture($request);

        $user = $action->handle($request->toData(), LoginPortal::Student);

        return $this->finishLogin($request, $user, LoginPortal::Student, HomePath::for($user));
    }

    public function storeTeach(LoginRequest $request, AttemptLoginAction $action): RedirectResponse
    {
        $user = $action->handle($request->toData(), LoginPortal::Instructor);

        return $this->finishLogin($request, $user, LoginPortal::Instructor, HomePath::for($user));
    }

    public function storePartner(LoginRequest $request, AttemptLoginAction $action): RedirectResponse
    {
        $user = $action->handle($request->toData(), LoginPortal::Partner);

        return $this->finishLogin($request, $user, LoginPortal::Partner, HomePath::for($user));
    }

    public function storeAdmin(LoginRequest $request, AttemptLoginAction $action): RedirectResponse
    {
        $user = $action->handle($request->toData(), LoginPortal::Admin);

        return $this->finishLogin(
            $request,
            $user,
            LoginPortal::Admin,
            route('admin.dashboard', absolute: false),
        );
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->auditLogout($user, AuditPortal::Student);
        $this->logout($request);

        return redirect()->route('landing.home');
    }

    public function destroyTeach(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->auditLogout($user, AuditPortal::Teach);
        $this->logout($request);

        return redirect()->route('teach.login');
    }

    public function destroyPartner(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->auditLogout($user, AuditPortal::Partner);
        $this->logout($request);

        return redirect()->route('partner.login');
    }

    public function destroyAdmin(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->auditLogout($user, AuditPortal::Admin);
        $this->logout($request);

        return redirect()->route('admin.login');
    }

    private function finishLogin(
        Request $request,
        User $user,
        LoginPortal $portal,
        string $home,
    ): RedirectResponse {
        $request->session()->regenerate();
        TwoFactorSession::clear($request);
        WebSessionManager::bindToUser($user, $request);

        if ($user->hasTwoFactorEnabled()) {
            if (TwoFactorGate::isSatisfied($request, $user)) {
                TwoFactorGate::confirmIfTrusted($request, $user);
            } else {
                return $this->redirectToTwoFactorChallenge($portal);
            }
        }

        return PortalRedirect::afterLogin($request, $home, $portal);
    }

    private function redirectToTwoFactorChallenge(LoginPortal $portal): RedirectResponse
    {
        return match ($portal) {
            LoginPortal::Student => redirect()->route('student.2fa.challenge'),
            LoginPortal::Instructor => redirect()->route('teach.2fa.challenge'),
            LoginPortal::Partner => redirect()->route('partner.2fa.challenge'),
            LoginPortal::Admin => redirect()->route('admin.2fa.challenge'),
        };
    }

    private function logout(Request $request): void
    {
        $user = $request->user();

        if ($user !== null) {
            WebSessionManager::clearForUser($user);
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();
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
