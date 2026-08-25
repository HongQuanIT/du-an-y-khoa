<?php

declare(strict_types=1);

namespace Modules\Admin\Support;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Audit\AuditContext;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Insert-only audit recorder for sensitive admin actions.
 */
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
        return \App\Support\Audit\Auditor::record(
            $action, $actor, $auditable, $before, $after, $request, $metadata, $context,
        );
    }
}
