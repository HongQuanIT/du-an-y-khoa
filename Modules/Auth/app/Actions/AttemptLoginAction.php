<?php

declare(strict_types=1);

namespace Modules\Auth\Actions;

use App\Models\User;
use App\Support\Audit\AuditContext;
use App\Support\Audit\Auditor;
use App\Support\Audit\Enums\AuditAction;
use App\Support\Audit\Enums\AuditCategory;
use App\Support\Audit\Enums\AuditPortal;
use App\Support\Audit\Enums\AuditResult;
use App\Support\Auth\Instructor;
use App\Support\Auth\Staff;
use App\Support\Concerns\AsAction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Modules\Auth\Data\LoginData;
use Modules\Auth\Enums\LoginPortal;

/**
 * Use case: authenticate a user on the `web` guard for a specific portal.
 *
 * Session regeneration stays in the HTTP layer; this action owns credential
 * checking, per-account lockout, and cross-portal rejection.
 */
final class AttemptLoginAction
{
    use AsAction;

    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 60;

    /**
     * @throws ValidationException when locked out, credentials are wrong, or portal mismatch
     */
    public function handle(LoginData $data, LoginPortal $portal = LoginPortal::Student): User
    {
        $key = $data->throttleKey();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $this->auditFailure($portal, AuditResult::Denied, 'rate_limited');
            throw ValidationException::withMessages([
                'email' => 'Bạn đã thử quá nhiều lần. Vui lòng thử lại sau '
                    .RateLimiter::availableIn($key).' giây.',
            ]);
        }

        $credentials = ['email' => $data->email, 'password' => $data->password];
        $remember = $portal === LoginPortal::Student ? $data->remember : false;

        if (! Auth::attempt($credentials, $remember)) {
            RateLimiter::hit($key, self::DECAY_SECONDS);
            $this->auditFailure($portal, AuditResult::Failed, 'invalid_credentials');

            throw ValidationException::withMessages([
                'email' => 'Email hoặc mật khẩu không đúng.',
            ]);
        }

        /** @var User $user */
        $user = Auth::user();

        if ($user->isSuspendedOrBanned()) {
            $this->auditFailure($portal, AuditResult::Denied, 'account_blocked', $user);
            Auth::logout();
            RateLimiter::hit($key, self::DECAY_SECONDS);

            throw ValidationException::withMessages([
                'email' => 'Tài khoản đã bị khóa hoặc cấm. Liên hệ hỗ trợ nếu cần.',
            ]);
        }

        match ($portal) {
            LoginPortal::Admin => $this->assertStaffPortal($user, $key),
            LoginPortal::Instructor => $this->assertInstructorPortal($user, $key),
            LoginPortal::Student => $this->assertStudentPortal($user, $key),
        };

        RateLimiter::clear($key);

        Auditor::record(
            AuditAction::AuthLogin,
            $user,
            $user,
            metadata: ['login_portal' => $portal->value],
            context: new AuditContext(
                portal: $this->auditPortal($portal),
                category: AuditCategory::Auth,
            ),
        );

        return $user;
    }

    /**
     * @throws ValidationException
     */
    private function assertStaffPortal(User $user, string $key): void
    {
        if (! Staff::isStaff($user)) {
            $this->rejectPortalMismatch($key, $user, LoginPortal::Admin);
        }
    }

    /**
     * @throws ValidationException
     */
    private function assertInstructorPortal(User $user, string $key): void
    {
        if (! Instructor::is($user)) {
            $this->rejectPortalMismatch($key, $user, LoginPortal::Instructor);
        }
    }

    /**
     * @throws ValidationException
     */
    private function assertStudentPortal(User $user, string $key): void
    {
        if (Staff::isStaff($user)) {
            $this->auditFailure(LoginPortal::Student, AuditResult::Denied, 'portal_mismatch', $user);
            Auth::logout();
            RateLimiter::hit($key, self::DECAY_SECONDS);

            throw ValidationException::withMessages([
                'email' => 'Tài khoản quản trị vui lòng đăng nhập tại '.route('admin.login').'.',
            ]);
        }

        if (Instructor::is($user)) {
            $this->auditFailure(LoginPortal::Student, AuditResult::Denied, 'portal_mismatch', $user);
            Auth::logout();
            RateLimiter::hit($key, self::DECAY_SECONDS);

            throw ValidationException::withMessages([
                'email' => 'Tài khoản giảng viên vui lòng đăng nhập tại '.route('teach.login').'.',
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function rejectPortalMismatch(string $key, User $user, LoginPortal $portal): never
    {
        $this->auditFailure($portal, AuditResult::Denied, 'portal_mismatch', $user);
        Auth::logout();
        RateLimiter::hit($key, self::DECAY_SECONDS);

        throw ValidationException::withMessages([
            'email' => 'Email hoặc mật khẩu không đúng.',
        ]);
    }

    private function auditFailure(
        LoginPortal $portal,
        AuditResult $result,
        string $reason,
        ?User $user = null,
    ): void {
        Auditor::record(
            AuditAction::AuthLoginFailed,
            $user,
            $user,
            metadata: ['login_portal' => $portal->value, 'reason' => $reason],
            context: new AuditContext(
                portal: $this->auditPortal($portal),
                category: AuditCategory::Auth,
                result: $result,
            ),
        );
    }

    private function auditPortal(LoginPortal $portal): AuditPortal
    {
        return match ($portal) {
            LoginPortal::Admin => AuditPortal::Admin,
            LoginPortal::Instructor => AuditPortal::Teach,
            LoginPortal::Student => AuditPortal::Student,
        };
    }
}
