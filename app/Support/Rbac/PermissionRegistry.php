<?php

declare(strict_types=1);

namespace App\Support\Rbac;

use App\Support\Enums\Permission as LegacyPermission;
use App\Support\Enums\PortalGroup;
use Illuminate\Support\Str;
use LogicException;

/**
 * Canonical, code-owned catalog for every application permission.
 */
final class PermissionRegistry
{
    /** @var array<string, PermissionDefinition>|null */
    private ?array $definitions = null;

    /** @return array<string, PermissionDefinition> */
    public function all(): array
    {
        if ($this->definitions !== null) {
            return $this->definitions;
        }

        $definitions = [];

        // Keep legacy names available while route checks migrate incrementally.
        foreach (LegacyPermission::cases() as $permission) {
            $definitions[$permission->value] = $this->definition(
                name: $permission->value,
                module: explode('.', $permission->value)[0],
                portals: [$permission->portal()],
            );
        }

        /** @var array<string, array<string, array<string, list<string>>>> $catalog */
        $catalog = config('rbac.catalog', []);

        foreach ($catalog as $portalValue => $modules) {
            $portal = PortalGroup::tryFrom($portalValue);
            if ($portal === null) {
                throw new LogicException("RBAC catalog contains unknown portal [{$portalValue}].");
            }

            foreach ($modules as $module => $resources) {
                foreach ($resources as $resource => $actions) {
                    foreach ($actions as $action) {
                        $name = "{$resource}.{$action}";
                        $existing = $definitions[$name] ?? null;
                        $portals = $existing?->portals ?? [];

                        if (! in_array($portal, $portals, true)) {
                            $portals[] = $portal;
                        }

                        $definitions[$name] = $this->definition(
                            name: $name,
                            module: $module,
                            portals: $portals,
                        );
                    }
                }
            }
        }

        ksort($definitions);

        foreach ($definitions as $name => $definition) {
            if (! preg_match('/^[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)+$/', $name)) {
                throw new LogicException("Permission [{$name}] must use resource.action naming.");
            }

            if ($definition->portals === []) {
                throw new LogicException("Permission [{$name}] does not declare a portal.");
            }
        }

        return $this->definitions = $definitions;
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_keys($this->all());
    }

    public function find(string $name): ?PermissionDefinition
    {
        return $this->all()[$name] ?? null;
    }

    /** @param list<PortalGroup> $portals */
    private function definition(string $name, string $module, array $portals): PermissionDefinition
    {
        $critical = in_array($name, config('rbac.critical', []), true);
        $sensitive = $critical || in_array($name, config('rbac.sensitive', []), true);
        [$resource, $action] = array_pad(explode('.', $name, 2), 2, 'access');

        return new PermissionDefinition(
            name: $name,
            displayName: Str::headline($resource).' · '.Str::headline($action),
            description: 'Cho phép '.Str::lower(Str::headline($action)).' '.Str::lower(Str::headline($resource)).'.',
            module: $module,
            portals: $portals,
            riskLevel: $critical ? 'critical' : ($sensitive ? 'sensitive' : 'normal'),
            isSensitive: $sensitive,
        );
    }
}
