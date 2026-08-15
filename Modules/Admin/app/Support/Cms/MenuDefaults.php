<?php

declare(strict_types=1);

namespace Modules\Admin\Support\Cms;

use Modules\Admin\Support\Enums\MenuKey;

/** Default menu payloads for header / footer (public landing). */
final class MenuDefaults
{
    /**
     * @return array<string, mixed>
     */
    public static function for(MenuKey $key): array
    {
        return match ($key) {
            MenuKey::Header => self::header(),
            MenuKey::Footer => self::footer(),
        };
    }

    /**
     * @return array{links: list<array{label: string, type: string, value: string, enabled: bool}>}
     */
    public static function header(): array
    {
        return [
            'links' => [
                self::routeLink('Tính năng', 'landing.features'),
                self::routeLink('Bảng giá', 'landing.pricing'),
                self::routeLink('Về chúng tôi', 'landing.about'),
                self::routeLink('Liên hệ', 'landing.contact'),
                self::routeLink('FAQ', 'landing.faq'),
            ],
        ];
    }

    /**
     * @return array{
     *     brand_blurb: string,
     *     columns: list<array{title: string, links: list<array{label: string, type: string, value: string, enabled: bool}>}>,
     *     bottom_links: list<array{label: string, type: string, value: string, enabled: bool}>
     * }
     */
    public static function footer(): array
    {
        return [
            'brand_blurb' => 'Nền tảng ôn thi y khoa hàng đầu Việt Nam, giúp tối ưu hóa kết quả học tập thông qua công nghệ AI và phương pháp khoa học.',
            'columns' => [
                [
                    'title' => 'Sản phẩm',
                    'links' => [
                        self::routeLink('Tính năng chính', 'landing.features'),
                        self::routeLink('Bảng giá linh hoạt', 'landing.pricing'),
                        self::urlLink('Cập nhật mới', '#'),
                        self::urlLink('Mobile App', '#'),
                    ],
                ],
                [
                    'title' => 'Tài nguyên',
                    'links' => [
                        self::urlLink('Blog Y khoa', '#'),
                        self::urlLink('Tài liệu miễn phí', '#'),
                        self::urlLink('Cộng đồng học tập', '#'),
                        self::urlLink('Video hướng dẫn', '#'),
                    ],
                ],
                [
                    'title' => 'Hỗ trợ',
                    'links' => [
                        self::routeLink('Trung tâm trợ giúp', 'landing.faq'),
                        self::routeLink('Liên hệ hỗ trợ', 'landing.contact'),
                        self::urlLink('Góp ý sản phẩm', '#'),
                        self::urlLink('Báo lỗi', '#'),
                    ],
                ],
                [
                    'title' => 'Công ty',
                    'links' => [
                        self::routeLink('Về chúng tôi', 'landing.about'),
                        self::routeLink('Điều khoản sử dụng', 'landing.terms'),
                        self::routeLink('Chính sách bảo mật', 'landing.privacy'),
                    ],
                ],
            ],
            'bottom_links' => [
                self::urlLink('Cookie Settings', '#cookie-settings'),
                self::routeLink('Sitemap', 'sitemap'),
            ],
        ];
    }

    /**
     * @return array{label: string, type: string, value: string, enabled: bool}
     */
    private static function routeLink(string $label, string $route): array
    {
        return [
            'label' => $label,
            'type' => 'route',
            'value' => $route,
            'enabled' => true,
        ];
    }

    /**
     * @return array{label: string, type: string, value: string, enabled: bool}
     */
    private static function urlLink(string $label, string $url): array
    {
        return [
            'label' => $label,
            'type' => 'url',
            'value' => $url,
            'enabled' => true,
        ];
    }
}
