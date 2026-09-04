<?php

declare(strict_types=1);

namespace App\Support\Audit;

/**
 * Path helpers for user presence: collapse IDs and map to Vietnamese screen names.
 */
final class ActivityArea
{
    /**
     * Collapse numeric / UUID / ULID segments so refreshes of the same screen share one area key.
     */
    public static function normalize(string $area): string
    {
        $path = '/'.trim((string) (parse_url($area, PHP_URL_PATH) ?: $area), '/');
        if ($path === '/') {
            return '/';
        }

        $segments = array_values(array_filter(explode('/', trim($path, '/')), static fn (string $s): bool => $s !== ''));
        $normalized = array_map(
            static fn (string $segment): string => self::isDynamicSegment($segment) ? '{id}' : $segment,
            $segments,
        );

        return '/'.implode('/', $normalized);
    }

    public static function shouldIgnore(string $area): bool
    {
        $path = self::normalize($area);

        return str_contains($path, '/api/')
            || str_ends_with($path, '/badge')
            || str_ends_with($path, '/heartbeat')
            || str_contains($path, '/lookups/');
    }

    public static function label(string $area): string
    {
        $path = self::normalize($area);

        $exact = self::exactLabels();
        if (isset($exact[$path])) {
            return $exact[$path];
        }

        // Longest prefix among known screens (never use bare /admin as catch-all for children).
        $best = null;
        $bestLen = -1;
        foreach (self::prefixLabels() as $prefix => $label) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                $len = strlen($prefix);
                if ($len > $bestLen) {
                    $best = $label;
                    $bestLen = $len;
                }
            }
        }

        if ($best !== null) {
            return $best;
        }

        return self::fallbackLabel($path);
    }

    public static function isDynamicSegment(string $segment): bool
    {
        if ($segment === '' || $segment === '{id}') {
            return $segment === '{id}';
        }

        if (ctype_digit($segment)) {
            return true;
        }

        // UUID
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $segment) === 1) {
            return true;
        }

        // ULID
        if (preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/i', $segment) === 1) {
            return true;
        }

        return false;
    }

    /** @return array<string, string> */
    private static function exactLabels(): array
    {
        return [
            '/' => 'trang chủ',
            '/dashboard' => 'trang chủ học viên',
            '/login' => 'đăng nhập học viên',
            '/admin' => 'bảng điều khiển quản trị',
            '/admin/login' => 'đăng nhập quản trị',
            '/admin/users' => 'danh sách người dùng',
            '/admin/users/create' => 'tạo người dùng',
            '/admin/users/{id}' => 'chi tiết người dùng',
            '/admin/roles' => 'vai trò & quyền',
            '/admin/roles/create' => 'tạo vai trò',
            '/admin/roles/{id}' => 'chi tiết vai trò',
            '/admin/permissions' => 'danh mục quyền',
            '/admin/settings' => 'cài đặt hệ thống',
            '/admin/support' => 'hộp thư hỗ trợ',
            '/admin/support/{id}' => 'chi tiết hội thoại hỗ trợ',
            '/admin/contacts' => 'liên hệ từ landing',
            '/admin/contacts/{id}' => 'chi tiết liên hệ',
            '/admin/notifications' => 'thông báo quản trị',
            '/admin/notifications/broadcast' => 'gửi thông báo broadcast',
            '/admin/audit' => 'nhật ký audit',
            '/admin/audit/{id}' => 'chi tiết bản ghi audit',
            '/admin/classrooms' => 'giám sát lớp học',
            '/admin/classrooms/create' => 'tạo lớp học (admin)',
            '/admin/classrooms/{id}' => 'chi tiết lớp học (admin)',
            '/admin/classrooms/{id}/live/{id}' => 'xem live lớp học (admin)',
            '/admin/questions' => 'danh sách câu hỏi',
            '/admin/questions/create' => 'tạo câu hỏi',
            '/admin/questions/{id}' => 'sửa câu hỏi',
            '/admin/questions/{id}/edit' => 'sửa câu hỏi',
            '/admin/questions/{id}/stats' => 'thống kê câu hỏi',
            '/admin/questions/{id}/versions' => 'phiên bản câu hỏi',
            '/admin/question-feedback' => 'phản hồi câu hỏi',
            '/admin/question-reviews/{id}' => 'duyệt câu hỏi',
            '/admin/exams' => 'danh sách đề thi',
            '/admin/exams/create' => 'tạo đề thi',
            '/admin/exams/{id}/edit' => 'sửa đề thi',
            '/admin/taxonomy' => 'taxonomy đề thi',
            '/admin/blueprints' => 'blueprints',
            '/admin/blueprints/create' => 'tạo blueprint',
            '/admin/blueprints/{id}/edit' => 'sửa blueprint',
            '/admin/medical-taxonomy' => 'phân loại y khoa',
            '/admin/tags' => 'thẻ (tags)',
            '/admin/tags/create' => 'tạo thẻ',
            '/admin/tags/{id}/edit' => 'sửa thẻ',
            '/admin/media' => 'thư viện media',
            '/admin/media/{id}' => 'chi tiết media',
            '/admin/cms' => 'CMS',
            '/admin/cms/pages' => 'trang CMS',
            '/admin/cms/pages/{id}/edit' => 'sửa trang CMS',
            '/admin/cms/faq' => 'FAQ',
            '/admin/cms/faq/create' => 'tạo FAQ',
            '/admin/cms/faq/{id}/edit' => 'sửa FAQ',
            '/admin/cms/banners' => 'banner',
            '/admin/cms/banners/create' => 'tạo banner',
            '/admin/cms/banners/{id}/edit' => 'sửa banner',
            '/admin/cms/menus' => 'menu CMS',
            '/admin/cms/menus/{id}/edit' => 'sửa menu CMS',
            '/admin/reports' => 'trung tâm báo cáo',
            '/admin/reports/{id}' => 'chi tiết báo cáo',
            '/admin/billing' => 'thanh toán (admin)',
            '/admin/partners' => 'quản lý CTV',
            '/admin/partners/{id}' => 'chi tiết CTV',
            '/admin/2fa/setup' => 'thiết lập 2FA',
            '/admin/2fa/challenge' => 'xác thực 2FA',
            '/teach' => 'bảng điều khiển giảng viên',
            '/teach/login' => 'đăng nhập giảng viên',
            '/teach/classes' => 'danh sách lớp (giảng viên)',
            '/teach/classes/create' => 'tạo lớp',
            '/teach/classes/{id}' => 'chi tiết lớp (giảng viên)',
            '/teach/classes/{id}/edit' => 'sửa lớp',
            '/teach/classes/{id}/sessions/{id}/studio' => 'studio live',
            '/teach/classes/{id}/sessions/{id}/studio/presenter' => 'cửa sổ presenter',
            '/teach/profile' => 'hồ sơ giảng viên',
            '/teach/notifications' => 'thông báo giảng viên',
            '/partner' => 'cổng cộng tác viên',
            '/classes' => 'lớp học',
            '/classes/{id}' => 'chi tiết lớp học',
            '/qbank' => 'ngân hàng câu hỏi',
            '/qbank/session/{id}' => 'phiên làm bài QBank',
            '/study-plan' => 'lộ trình học',
            '/plans' => 'lộ trình học',
            '/flashcards' => 'flashcards',
            '/review' => 'ôn tập',
            '/exams' => 'đề thi / thi thử',
            '/analytics' => 'thống kê học tập',
            '/library' => 'thư viện',
            '/ai' => 'AI Tutor',
            '/profile' => 'hồ sơ cá nhân',
            '/settings' => 'cài đặt tài khoản',
            '/billing' => 'thanh toán',
            '/subscription' => 'gói đăng ký',
            '/pricing' => 'bảng giá',
            '/notifications' => 'thông báo',
        ];
    }

    /**
     * Prefix labels for nested screens not listed exactly (longest match wins).
     *
     * @return array<string, string>
     */
    private static function prefixLabels(): array
    {
        return [
            '/admin/billing' => 'thanh toán (admin)',
            '/admin/cms' => 'CMS',
            '/admin/partners' => 'quản lý CTV',
            '/admin/reports' => 'báo cáo',
            '/admin/questions' => 'câu hỏi',
            '/admin/exams' => 'đề thi',
            '/admin/classrooms' => 'lớp học (admin)',
            '/admin/support' => 'hỗ trợ',
            '/admin/users' => 'người dùng',
            '/teach/classes' => 'lớp / studio giảng viên',
            '/qbank/session' => 'phiên làm bài QBank',
            '/qbank' => 'ngân hàng câu hỏi',
            '/partner' => 'cổng cộng tác viên',
            '/classes' => 'lớp học',
        ];
    }

    private static function fallbackLabel(string $normalizedPath): string
    {
        $segments = array_values(array_filter(explode('/', trim($normalizedPath, '/'))));
        if ($segments === []) {
            return 'trang chủ';
        }

        $dictionary = [
            'admin' => 'quản trị',
            'teach' => 'giảng viên',
            'partner' => 'CTV',
            'users' => 'người dùng',
            'roles' => 'vai trò',
            'questions' => 'câu hỏi',
            'exams' => 'đề thi',
            'reports' => 'báo cáo',
            'settings' => 'cài đặt',
            'support' => 'hỗ trợ',
            'audit' => 'audit',
            'classrooms' => 'lớp học',
            'billing' => 'thanh toán',
            'cms' => 'CMS',
            'media' => 'media',
            'create' => 'tạo mới',
            'edit' => 'chỉnh sửa',
            'studio' => 'studio live',
            'qbank' => 'QBank',
            'session' => 'phiên học',
            '{id}' => 'chi tiết',
        ];

        $parts = [];
        foreach ($segments as $segment) {
            $parts[] = $dictionary[$segment] ?? str_replace('-', ' ', $segment);
        }

        return implode(' · ', $parts);
    }
}
