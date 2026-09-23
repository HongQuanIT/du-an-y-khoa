<?php

declare(strict_types=1);

namespace App\Support\Audit\Enums;

enum AuditPortal: string
{
    case Admin = 'admin';
    case Teach = 'teach';
    case Partner = 'partner';
    case Editor = 'editor';
    case Student = 'student';
    case Api = 'api';
    case System = 'system';
}
