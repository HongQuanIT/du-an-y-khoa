<?php

declare(strict_types=1);

namespace App\Support\Audit\Enums;

enum AuditCategory: string
{
    case Auth = 'auth';
    case Account = 'account';
    case Classroom = 'classroom';
    case Learning = 'learning';
    case Exam = 'exam';
    case Content = 'content';
    case Billing = 'billing';
    case Security = 'security';
    case System = 'system';
}
