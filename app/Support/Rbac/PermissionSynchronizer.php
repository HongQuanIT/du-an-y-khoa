<?php

declare(strict_types=1);

namespace App\Support\Rbac;

use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Additive synchronizer: creates missing permissions and refreshes metadata,
 * but never removes permissions or changes role assignments.
 */
final class PermissionSynchronizer
{
    public function __construct(private readonly PermissionRegistry $registry) {}

    /**
     * @return array{created: int, updated: int, total: int}
     */
    public function sync(): array
    {
        $created = 0;
        $updated = 0;
        $guard = (string) config('rbac.guard', 'web');

        DB::transaction(function () use ($guard, &$created, &$updated): void {
            foreach ($this->registry->all() as $definition) {
                $permission = Permission::query()
                    ->where('guard_name', $guard)
                    ->where('name', $definition->name)
                    ->first();

                if ($permission === null) {
                    $permission = Permission::findOrCreate($definition->name, $guard);
                    $created++;
                }

                $permission->forceFill([
                    'display_name' => $definition->displayName,
                    'description' => $definition->description,
                    'module' => $definition->module,
                    'portal' => $definition->primaryPortal()->value,
                    'portals' => json_encode($definition->portalValues(), JSON_THROW_ON_ERROR),
                    'risk_level' => $definition->riskLevel,
                    'is_sensitive' => $definition->isSensitive,
                    'is_system' => $definition->isSystem,
                ]);

                if ($permission->isDirty()) {
                    $permission->save();
                    $updated++;
                }
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return [
            'created' => $created,
            'updated' => $updated,
            'total' => count($this->registry->all()),
        ];
    }
}
