<?php

declare(strict_types=1);

namespace App\Support\Audit;

use App\Support\Audit\Enums\AuditDelivery;

final class AuditDeliveryPolicy
{
    public static function for(string $action): AuditDelivery
    {
        if (self::startsWithAny($action, [
            'auth.', 'billing.', 'admin.', 'cms.', 'media.',
        ])) {
            return AuditDelivery::Immediate;
        }

        if (self::startsWithAny($action, [
            'learning.', 'account.',
        ])) {
            return AuditDelivery::Queued;
        }

        if (str_starts_with($action, 'classroom.') && ! self::startsWithAny($action, [
            'classroom.deleted',
            'classroom.member.kicked',
            'classroom.message.deleted',
            'classroom.chat.toggled',
        ])) {
            return AuditDelivery::Queued;
        }

        return AuditDelivery::Immediate;
    }

    /** @param array<int, string> $prefixes */
    private static function startsWithAny(string $action, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            if (str_starts_with($action, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
