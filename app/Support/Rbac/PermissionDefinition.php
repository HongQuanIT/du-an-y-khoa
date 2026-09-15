<?php

declare(strict_types=1);

namespace App\Support\Rbac;

use App\Support\Enums\PortalGroup;

final readonly class PermissionDefinition
{
    /**
     * @param  list<PortalGroup>  $portals
     */
    public function __construct(
        public string $name,
        public string $displayName,
        public string $description,
        public string $module,
        public array $portals,
        public string $riskLevel = 'normal',
        public bool $isSensitive = false,
        public bool $isSystem = true,
    ) {}

    public function primaryPortal(): PortalGroup
    {
        return $this->portals[0];
    }

    /** @return list<string> */
    public function portalValues(): array
    {
        return array_map(
            static fn (PortalGroup $portal): string => $portal->value,
            $this->portals,
        );
    }
}
