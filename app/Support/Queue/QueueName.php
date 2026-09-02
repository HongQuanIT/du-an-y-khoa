<?php

declare(strict_types=1);

namespace App\Support\Queue;

/**
 * Tên queue theo luồng tính năng — dùng thống nhất khi dispatch job/mail và cấu hình Horizon.
 *
 * @see config/horizon.php
 */
enum QueueName: string
{
    /** Ghi audit log bất đồng bộ (volume cao, ưu tiên thấp). */
    case Audit = 'audit';

    /** Webhook thanh toán và đối soát checkout — ưu tiên cao. */
    case Billing = 'billing';

    /** Fan-out thông báo hệ thống / in-app. */
    case Notifications = 'notifications';

    /** Email (Mailable ShouldQueue). */
    case Mail = 'mail';

    /** Warm/refresh cache báo cáo admin — job dài. */
    case AdminReports = 'admin-reports';

    /** Replan kế hoạch học adaptive. */
    case StudyPlan = 'study-plan';

    /** Scout / Meilisearch index sync. */
    case Search = 'search';

    /** Fallback cho job chưa gán queue riêng. */
    case Default = 'default';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
