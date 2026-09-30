<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Enums;

enum QuestionReviewAction: string
{
    case Create = 'create';
    case Update = 'update';
    case Delete = 'delete';
    case Retire = 'retire';

    public function label(): string
    {
        return match ($this) {
            self::Create => 'Tạo mới',
            self::Update => 'Chỉnh sửa',
            self::Delete => 'Xóa',
            self::Retire => 'Ngừng dùng',
        };
    }
}
