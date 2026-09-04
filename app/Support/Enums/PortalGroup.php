<?php

declare(strict_types=1);

namespace App\Support\Enums;

use App\Support\Enums\Concerns\EnumValues;

/**
 * Four product portals used to group roles and permissions in admin UI.
 * See srs/00-nen-tang/03-phan-quyen-rbac.md §1.1.
 */
enum PortalGroup: string
{
    use EnumValues;

    case Learner = 'learner';
    case Instructor = 'instructor';
    case Admin = 'admin';
    case Partner = 'partner';

    public function label(): string
    {
        return match ($this) {
            self::Learner => 'Học viên',
            self::Instructor => 'Giảng viên',
            self::Admin => 'Quản trị',
            self::Partner => 'Cộng tác viên',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Learner => 'Cổng học tập (/login): làm bài, quản lý phiên học và tham gia lớp.',
            self::Instructor => 'Cổng giảng dạy (/teach): tổ chức và vận hành lớp chữa đề.',
            self::Admin => 'Cổng quản trị (/admin): quản lý nội dung, người dùng, phân quyền và giám sát.',
            self::Partner => 'Cổng cộng tác viên (/partner): mã mời, giới thiệu và hoa hồng.',
        };
    }

    public function loginPath(): string
    {
        return match ($this) {
            self::Learner => '/login',
            self::Instructor => '/teach/login',
            self::Admin => '/admin/login',
            self::Partner => '/partner/login',
        };
    }
}
