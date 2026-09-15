<?php

declare(strict_types=1);

namespace App\Support\Enums;

use App\Models\User;
use App\Support\Enums\Concerns\EnumValues;

/**
 * System roles (single role per user). See srs/00-nen-tang/03-phan-quyen-rbac.md.
 */
enum Role: string
{
    use EnumValues;

    case Student = 'student';
    case Instructor = 'instructor';
    case Partner = 'partner';
    case ContentEditor = 'content_editor';
    case Admin = 'admin';
    case SuperAdmin = 'super_admin';

    public function label(): string
    {
        return match ($this) {
            self::Student => 'Học viên',
            self::Instructor => 'Giảng viên',
            self::Partner => 'Cộng tác viên',
            self::ContentEditor => 'Biên tập viên nội dung',
            self::Admin => 'Quản trị viên',
            self::SuperAdmin => 'Quản trị viên cấp cao',
        };
    }

    public function portal(): PortalGroup
    {
        return match ($this) {
            self::Student => PortalGroup::Learner,
            self::Instructor => PortalGroup::Instructor,
            self::Partner => PortalGroup::Partner,
            self::ContentEditor, self::Admin, self::SuperAdmin => PortalGroup::Admin,
        };
    }

    public function rank(): int
    {
        return match ($this) {
            self::Student => 1,
            self::Instructor => 2,
            self::Partner => 2,
            self::ContentEditor => 3,
            self::Admin => 4,
            self::SuperAdmin => 5,
        };
    }

    /**
     * Roles belonging to a portal group (admin UI portal → role picker).
     *
     * @return list<self>
     */
    public static function rolesIn(PortalGroup $portal): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn (self $role): bool => $role->portal() === $portal,
        ));
    }

    /**
     * Roles the actor may assign to others (single system role per user).
     *
     * @return list<self>
     */
    public static function assignableBy(User $actor): array
    {
        if ($actor->hasRole(self::SuperAdmin->value)) {
            return self::cases();
        }

        if ($actor->hasRole(self::Admin->value)) {
            return [self::Student, self::Instructor, self::Partner, self::ContentEditor];
        }

        return [];
    }

    /**
     * Assignable roles filtered to a portal (empty if actor cannot assign any in that portal).
     *
     * @return list<self>
     */
    public static function assignableInPortal(User $actor, PortalGroup $portal): array
    {
        return array_values(array_filter(
            self::assignableBy($actor),
            static fn (self $role): bool => $role->portal() === $portal,
        ));
    }

    public static function tryFromName(?string $name): ?self
    {
        if ($name === null || $name === '') {
            return null;
        }

        return self::tryFrom($name);
    }
}
