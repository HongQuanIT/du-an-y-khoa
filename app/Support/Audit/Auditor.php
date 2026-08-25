<?php

declare(strict_types=1);

namespace App\Support\Audit;

use App\Jobs\RecordAuditLogJob;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\Audit\Enums\AuditCategory;
use App\Support\Audit\Enums\AuditDelivery;
use App\Support\Audit\Enums\AuditPortal;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class Auditor
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     * @param  array<string, mixed>|null  $metadata
     */
    public static function record(
        string|BackedEnum $action,
        ?User $actor = null,
        ?Model $auditable = null,
        ?array $before = null,
        ?array $after = null,
        ?Request $request = null,
        ?array $metadata = null,
        ?AuditContext $context = null,
    ): ?AuditLog {
        $request ??= request();
        $context ??= new AuditContext;
        $actionValue = trim((string) ($action instanceof BackedEnum ? $action->value : $action));

        if ($actionValue === '' || strlen($actionValue) > 100) {
            throw new InvalidArgumentException('Audit action must contain between 1 and 100 characters.');
        }

        $actor ??= $request->user();
        $userAgent = $request->userAgent();
        $client = UserAgentParser::parse($userAgent);

        $attributes = [
            'event_id' => (string) Str::uuid(),
            'actor_id' => $actor?->getKey(),
            'actor_role' => $context->actorRole ?? $actor?->primaryRoleName(),
            'portal' => ($context->portal ?? self::inferPortal($request))->value,
            'category' => ($context->category ?? self::inferCategory($actionValue))->value,
            'result' => $context->result->value,
            'session_id' => $context->sessionId ?? self::sessionId($metadata),
            'action' => $actionValue,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable !== null ? (string) $auditable->getKey() : null,
            'before' => self::sanitize($before),
            'after' => self::sanitize($after),
            'metadata' => self::sanitize($metadata),
            'ip' => $request->ip(),
            'user_agent' => $userAgent,
            ...$client,
            'request_id' => $request->attributes->get('request_id'),
            'created_at' => now(),
        ];

        $shouldQueue = config('audit.queue_enabled', true)
            && config('queue.default') !== 'sync'
            && AuditDeliveryPolicy::for($actionValue) === AuditDelivery::Queued;

        if ($shouldQueue) {
            RecordAuditLogJob::dispatch($attributes);

            return null;
        }

        return AuditLog::query()->create($attributes);
    }

    private static function inferPortal(Request $request): AuditPortal
    {
        $routeName = (string) $request->route()?->getName();
        $path = trim($request->path(), '/');

        return match (true) {
            str_starts_with($routeName, 'admin.'), str_starts_with($path, 'admin') => AuditPortal::Admin,
            str_starts_with($routeName, 'teach.'), str_starts_with($path, 'teach') => AuditPortal::Teach,
            str_starts_with($path, 'api/') => AuditPortal::Api,
            app()->runningInConsole() && $request->route() === null => AuditPortal::System,
            default => AuditPortal::Student,
        };
    }

    private static function inferCategory(string $action): AuditCategory
    {
        return match (true) {
            str_starts_with($action, 'auth.'), str_contains($action, '.2fa.') => AuditCategory::Auth,
            str_starts_with($action, 'account.'), str_starts_with($action, 'admin.user.') => AuditCategory::Account,
            str_starts_with($action, 'classroom.'), str_contains($action, '.classroom.') => AuditCategory::Classroom,
            str_starts_with($action, 'learning.') => AuditCategory::Learning,
            str_starts_with($action, 'exam.') => AuditCategory::Exam,
            str_starts_with($action, 'billing.') => AuditCategory::Billing,
            str_starts_with($action, 'admin.question.'), str_starts_with($action, 'admin.topic.'),
            str_starts_with($action, 'cms.'), str_starts_with($action, 'media.') => AuditCategory::Content,
            str_starts_with($action, 'admin.role.'), str_starts_with($action, 'admin.login') => AuditCategory::Security,
            default => AuditCategory::System,
        };
    }

    /** @param array<string, mixed>|null $metadata */
    private static function sessionId(?array $metadata): ?string
    {
        foreach (['session_id', 'live_session_id', 'question_session_id'] as $key) {
            if (isset($metadata[$key]) && is_scalar($metadata[$key])) {
                return mb_substr((string) $metadata[$key], 0, 64);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>|null
     */
    private static function sanitize(?array $payload): ?array
    {
        if ($payload === null) {
            return null;
        }

        $sensitiveKeys = [
            'authorization', 'cookie', 'email', 'password', 'password_confirmation',
            'remember_token', 'recovery_codes', 'account_notes', 'address',
            'date_of_birth', 'phone', 'secret', 'token', 'two_factor_secret',
        ];

        $sanitizeValue = function (mixed $value, string|int $key = '') use (&$sanitizeValue, $sensitiveKeys): mixed {
            $normalizedKey = strtolower((string) $key);
            if (in_array($normalizedKey, $sensitiveKeys, true)
                || str_ends_with($normalizedKey, '_password')
                || str_ends_with($normalizedKey, '_token')
                || str_ends_with($normalizedKey, '_secret')) {
                return '[REDACTED]';
            }

            if (is_array($value)) {
                foreach ($value as $childKey => $childValue) {
                    $value[$childKey] = $sanitizeValue($childValue, $childKey);
                }
            }

            return $value;
        };

        return $sanitizeValue($payload);
    }
}
