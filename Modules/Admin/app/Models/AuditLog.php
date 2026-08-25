<?php

declare(strict_types=1);

namespace Modules\Admin\Models;

use App\Support\Audit\Enums\AuditAction as PlatformAuditAction;
use Illuminate\Database\Eloquent\Builder;
use Modules\Admin\Enums\AuditAction;

/**
 * Insert-only admin audit trail (srs module 40).
 *
 * @property int $id
 * @property int|null $actor_id
 * @property string $action
 * @property string|null $auditable_type
 * @property string|null $auditable_id
 * @property array<string, mixed>|null $before
 * @property array<string, mixed>|null $after
 * @property array<string, mixed>|null $metadata
 * @property string|null $ip
 * @property string|null $user_agent
 * @property string|null $device_type
 * @property string|null $device_name
 * @property string|null $operating_system
 * @property string|null $browser
 * @property string|null $request_id
 */
class AuditLog extends \App\Models\AuditLog
{
    /** @param Builder<self> $query */
    public function scopeVisibleToAdmin(Builder $query): Builder
    {
        return $query->whereNotIn('action', PlatformAuditAction::hiddenLearningDetailValues());
    }

    public function actionLabel(): string
    {
        return AuditAction::tryFrom($this->action)?->label()
            ?? PlatformAuditAction::tryFrom($this->action)?->label()
            ?? $this->action;
    }

    public function deviceTypeLabel(): string
    {
        return match ($this->device_type) {
            'desktop' => 'Máy tính',
            'mobile' => 'Điện thoại',
            'tablet' => 'Máy tính bảng',
            'bot' => 'Bot',
            default => 'Không xác định',
        };
    }
}
