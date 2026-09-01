<?php

declare(strict_types=1);

namespace Modules\Admin\Support;

use App\Models\User;
use App\Support\Enums\Permission;

/**
 * Danh mục báo cáo dựng sẵn (Module 41).
 * Phase 2: viewer + filter range + CSV export.
 *
 * @phpstan-type ReportItem array{
 *     slug: string,
 *     title: string,
 *     description: string,
 * }
 * @phpstan-type ReportCategory array{
 *     slug: string,
 *     title: string,
 *     description: string,
 *     icon: string,
 *     permission: ?Permission,
 *     reports: list<ReportItem>,
 * }
 */
final class AdminReportCatalog
{
    /** @return list<ReportCategory> */
    public static function categories(): array
    {
        return [
            [
                'slug' => 'users',
                'title' => 'Người dùng & Tăng trưởng',
                'description' => 'DAU, MAU, đăng ký mới, tỷ lệ giữ chân theo nhóm đăng ký (cohort).',
                'icon' => 'group',
                'permission' => Permission::UserView,
                'reports' => [
                    ['slug' => 'dau-mau', 'title' => 'DAU / MAU', 'description' => 'Người dùng hoạt động theo ngày và tháng.'],
                    ['slug' => 'signups', 'title' => 'Đăng ký mới', 'description' => 'Số học viên mới theo thời gian.'],
                    ['slug' => 'retention', 'title' => 'Giữ chân theo nhóm đăng ký (cohort)', 'description' => 'Đo % học viên còn quay lại học sau ngày đăng ký.'],
                ],
            ],
            [
                'slug' => 'engagement',
                'title' => 'Tương tác & Hoạt động',
                'description' => 'Phiên học, câu đã làm, thời lượng học.',
                'icon' => 'insights',
                'permission' => Permission::UserView,
                'reports' => [
                    ['slug' => 'sessions', 'title' => 'Phiên học', 'description' => 'Số phiên hoàn thành theo thời gian.'],
                    ['slug' => 'questions', 'title' => 'Câu đã làm', 'description' => 'Khối lượng luyện tập toàn hệ thống.'],
                    ['slug' => 'study-time', 'title' => 'Thời lượng học', 'description' => 'Tổng thời gian học trung bình.'],
                ],
            ],
            [
                'slug' => 'revenue',
                'title' => 'Doanh thu & Churn',
                'description' => 'MRR, ARPU, churn, phễu chuyển đổi Free → Premium.',
                'icon' => 'payments',
                'permission' => Permission::BillingManage,
                'reports' => [
                    ['slug' => 'mrr', 'title' => 'MRR & Doanh thu', 'description' => 'Doanh thu định kỳ và theo tháng.'],
                    ['slug' => 'churn', 'title' => 'Churn & Rời bỏ', 'description' => 'Tỷ lệ hủy và hết hạn không gia hạn.'],
                    ['slug' => 'funnel', 'title' => 'Phễu chuyển đổi', 'description' => 'Free → Premium theo gói và nguồn.'],
                ],
            ],
            [
                'slug' => 'content',
                'title' => 'Hiệu quả nội dung',
                'description' => 'Tỷ lệ đúng, báo lỗi, chất lượng câu hỏi.',
                'icon' => 'quiz',
                'permission' => Permission::QuestionView,
                'reports' => [
                    ['slug' => 'accuracy', 'title' => 'Tỷ lệ đúng theo chủ đề', 'description' => 'Hiệu quả nội dung ngân hàng câu hỏi.'],
                    ['slug' => 'flags', 'title' => 'Báo lỗi câu hỏi', 'description' => 'Phản hồi học viên và chất lượng biên tập.'],
                    ['slug' => 'coverage', 'title' => 'Độ phủ taxonomy', 'description' => 'Phân bổ câu hỏi theo chuyên ngành.'],
                ],
            ],
            [
                'slug' => 'learning',
                'title' => 'Kết quả học tập',
                'description' => 'Mức độ nắm chủ đề (mastery), điểm thi, chủ đề yếu.',
                'icon' => 'school',
                'permission' => Permission::UserView,
                'reports' => [
                    ['slug' => 'mastery', 'title' => 'Mức độ nắm chủ đề (mastery)', 'description' => 'Mức độ nắm chủ đề trung bình.'],
                    ['slug' => 'exam-scores', 'title' => 'Điểm thi thử', 'description' => 'Phân phối điểm bài thi thử.'],
                    ['slug' => 'weak-topics', 'title' => 'Chủ đề yếu phổ biến', 'description' => 'Các chủ đề cần cải thiện nhiều nhất.'],
                ],
            ],
        ];
    }

    /** @return list<ReportCategory> */
    public static function forUser(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        return array_values(array_filter(
            self::categories(),
            fn (array $category): bool => $category['permission'] === null
                || $user->can($category['permission']->value),
        ));
    }

    /** @return ReportCategory|null */
    public static function findCategory(string $slug): ?array
    {
        foreach (self::categories() as $category) {
            if ($category['slug'] === $slug) {
                return $category;
            }
        }

        return null;
    }

    /**
     * @return array{category: ReportCategory, report: ReportItem}|null
     */
    public static function findReport(string $categorySlug, string $reportSlug): ?array
    {
        $category = self::findCategory($categorySlug);
        if ($category === null) {
            return null;
        }

        foreach ($category['reports'] as $report) {
            if ($report['slug'] === $reportSlug) {
                return ['category' => $category, 'report' => $report];
            }
        }

        return null;
    }
}
