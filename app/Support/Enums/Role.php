<?php

declare(strict_types=1);

namespace App\Support\Enums;

use App\Support\Enums\Concerns\EnumValues;

/**
 * System roles (single role per user). See srs/00-nen-tang/03-phan-quyen-rbac.md.
 * Phase 2 roles (instructor, org_admin) are intentionally omitted.
 */
enum Role: string
{
    use EnumValues;

    case Student = 'student';
    case ContentEditor = 'content_editor';
    case Admin = 'admin';
    case SuperAdmin = 'super_admin';

    public function label(): string
    {
        return match ($this) {
            self::Student => 'Student',
            self::ContentEditor => 'Content Editor',
            self::Admin => 'Admin',
            self::SuperAdmin => 'Super Admin',
        };
    }
}
