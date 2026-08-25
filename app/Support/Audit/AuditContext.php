<?php

declare(strict_types=1);

namespace App\Support\Audit;

use App\Support\Audit\Enums\AuditCategory;
use App\Support\Audit\Enums\AuditPortal;
use App\Support\Audit\Enums\AuditResult;

final readonly class AuditContext
{
    public function __construct(
        public ?AuditPortal $portal = null,
        public ?AuditCategory $category = null,
        public AuditResult $result = AuditResult::Success,
        public ?string $sessionId = null,
        public ?string $actorRole = null,
    ) {}
}
