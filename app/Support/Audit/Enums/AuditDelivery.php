<?php

declare(strict_types=1);

namespace App\Support\Audit\Enums;

enum AuditDelivery: string
{
    case Immediate = 'immediate';
    case Queued = 'queued';
}
