<?php

declare(strict_types=1);

namespace App\Support\Enums;

use App\Support\Enums\Concerns\EnumValues;

/**
 * System roles (single role per user). See srs/00-nen-tang/03-phan-quyen-rbac.md.
 */
enum Role: string
{
    use EnumValues;

    case Student = 'student';
    case Instructor = 'instructor';
    case ContentEditor = 'content_editor';
    case Admin = 'admin';
    case SuperAdmin = 'super_admin';

    public function label(): string
    {
        return match ($this) {
            self::Student => 'Học viên',
            self::Instructor => 'Giảng viên',
            self::ContentEditor => 'Content Editor',
            self::Admin => 'Admin',
            self::SuperAdmin => 'Super Admin',
        };
    }

    public function rank(): int
    {
        return match ($this) {
            self::Student => 1,
            self::Instructor => 2,
            self::ContentEditor => 3,
            self::Admin => 4,
            self::SuperAdmin => 5,
        };
    }

    /**
     * Roles the actor may assign to others (single system role per user).
     *
     * @return list<self>
     */
    public static function assignableBy(\App\Models\User $actor): array
    {
        if ($actor->hasRole(self::SuperAdmin->value)) {
            return self::cases();
        }

        if ($actor->hasRole(self::Admin->value)) {
            return [self::Student, self::Instructor, self::ContentEditor];
        }

        return [];
    }

    public static function tryFromName(?string $name): ?self
    {
        if ($name === null || $name === '') {
            return null;
        }

        return self::tryFrom($name);
    }
}
