<?php

declare(strict_types=1);

namespace Modules\Admin\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Modules\Admin\Models\AuditLog;

/**
 * Insert-only audit recorder for sensitive admin actions.
 */
final class Auditor
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public static function record(
        string $action,
        ?User $actor = null,
        ?Model $auditable = null,
        ?array $before = null,
        ?array $after = null,
        ?Request $request = null,
    ): AuditLog {
        $request ??= request();

        return AuditLog::query()->create([
            'actor_id' => $actor?->id ?? $request->user()?->id,
            'action' => $action,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'before' => $before,
            'after' => $after,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_id' => $request->attributes->get('request_id'),
            'created_at' => now(),
        ]);
    }
}
