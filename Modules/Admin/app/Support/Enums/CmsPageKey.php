<?php

declare(strict_types=1);

namespace Modules\Admin\Support\Enums;

use App\Support\Enums\Concerns\EnumValues;

enum CmsPageKey: string
{
    use EnumValues;

    case Home = 'home';
    case Features = 'features';
    case About = 'about';
    case Contact = 'contact';
    case Terms = 'terms';
    case Privacy = 'privacy';

    public function label(): string
    {
        return match ($this) {
            self::Home => 'Trang chủ (Landing)',
            self::Features => 'Tính năng',
            self::About => 'Về chúng tôi',
            self::Contact => 'Liên hệ',
            self::Terms => 'Điều khoản sử dụng',
            self::Privacy => 'Chính sách bảo mật',
        };
    }

    public function slug(): string
    {
        return match ($this) {
            self::Home => '/',
            self::Features => '/features',
            self::About => '/about',
            self::Contact => '/contact',
            self::Terms => '/terms',
            self::Privacy => '/privacy',
        };
    }

    public function routeName(): string
    {
        return match ($this) {
            self::Home => 'landing.home',
            self::Features => 'landing.features',
            self::About => 'landing.about',
            self::Contact => 'landing.contact',
            self::Terms => 'landing.terms',
            self::Privacy => 'landing.privacy',
        };
    }

    public function defaultTitle(): string
    {
        return match ($this) {
            self::Home => 'Nền tảng ôn thi Y khoa',
            default => $this->label(),
        };
    }

    public function defaultSeoDescription(): string
    {
        return match ($this) {
            self::Home => 'Học hiệu quả hơn — hiểu bản chất, nhớ lâu, luyện thi đúng trọng tâm với ngân hàng câu hỏi chuẩn hóa và AI Tutor.',
            self::Features => 'Ngân hàng câu hỏi, lộ trình học, AI Tutor và công cụ ôn thi Y khoa trên một nền tảng.',
            self::About => 'Tìm hiểu sứ mệnh, giá trị cốt lõi và đội ngũ chuyên gia đồng hành cùng sinh viên Y khoa.',
            self::Contact => 'Liên hệ đội ngũ hỗ trợ MedLearn qua email, hotline hoặc form gửi tin nhắn.',
            self::Terms => 'Điều khoản sử dụng nền tảng MedLearn — quyền và nghĩa vụ của người dùng.',
            self::Privacy => 'Chính sách bảo mật MedLearn — cách chúng tôi thu thập và bảo vệ dữ liệu cá nhân.',
        };
    }

    /** Landing marketing pages always render (draft falls back to defaults). */
    public function alwaysPublic(): bool
    {
        return match ($this) {
            self::Home, self::Features => true,
            default => false,
        };
    }

    public function isLandingBlock(): bool
    {
        return $this->alwaysPublic();
    }
}
