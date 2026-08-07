<?php

declare(strict_types=1);

namespace App\Support\Enums;

use App\Support\Enums\Concerns\EnumValues;

/**
 * Subscription entitlements — independent from roles, drive Premium gating.
 * See srs/00-nen-tang/03-phan-quyen-rbac.md §5.
 */
enum Entitlement: string
{
    use EnumValues;

    case QbankFull = 'qbank.full';
    case LibraryFull = 'library.full';
    case AiTutor = 'ai.tutor';
    case AnalyticsAdvanced = 'analytics.advanced';
    case ExamSimulation = 'exam.simulation';
    case OfflineDownload = 'offline.download';
    case ClassroomHost = 'classroom.host';
}
