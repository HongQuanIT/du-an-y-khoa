<?php

declare(strict_types=1);

namespace App\Support\Audit\Enums;

enum AuditResult: string
{
    case Success = 'success';
    case Failed = 'failed';
    case Denied = 'denied';
}
