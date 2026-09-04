<?php

declare(strict_types=1);

namespace Modules\Billing\Support;

use App\Support\Enums\Entitlement;

final class EntitlementLabels
{
    /** @return array<string, string> */
    public static function map(): array
    {
        return [
            Entitlement::QbankFull->value => 'Toàn bộ Q-Bank',
            Entitlement::LibraryFull->value => 'Thư viện đầy đủ',
            Entitlement::AiTutor->value => 'AI Tutor (quota Premium cao/ngày)',
            Entitlement::AnalyticsAdvanced->value => 'Phân tích nâng cao',
            Entitlement::ExamSimulation->value => 'Mô phỏng thi thật',
            Entitlement::OfflineDownload->value => 'Tải offline',
            Entitlement::ClassroomHost->value => 'Host lớp cộng đồng',
        ];
    }

    /**
     * @param  list<string>|null  $entitlements
     * @return list<string>
     */
    public static function labels(?array $entitlements): array
    {
        if ($entitlements === null || $entitlements === []) {
            return [];
        }

        $map = self::map();

        return array_values(array_filter(array_map(
            fn (string $key): ?string => $map[$key] ?? $key,
            $entitlements,
        )));
    }
}
