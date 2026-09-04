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

    public function userActivityLabel(): string
    {
        $label = $this->actionLabel();

        if ($label !== $this->action) {
            return $label;
        }

        return match (true) {
            str_starts_with($this->action, 'media.') => 'Thao tác với tệp nội dung',
            str_starts_with($this->action, 'notification.') => 'Thao tác với thông báo',
            str_starts_with($this->action, 'cms.') => 'Thao tác với nội dung trang',
            str_starts_with($this->action, 'admin.') => 'Thao tác trong trang quản trị',
            default => 'Thực hiện thao tác trên hệ thống',
        };
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

    public function portalLabel(): string
    {
        return match ($this->portal) {
            'admin' => 'Admin',
            'teach' => 'Giảng viên',
            'partner' => 'Cộng tác viên',
            'api' => 'Ứng dụng',
            'system' => 'Hệ thống',
            default => 'Học viên',
        };
    }

    public function categoryLabel(): string
    {
        return match ($this->category) {
            'auth' => 'Tài khoản & đăng nhập',
            'account' => 'Hồ sơ cá nhân',
            'classroom' => 'Lớp học',
            'learning' => 'Học tập',
            'exam' => 'Bài thi',
            'content' => 'Nội dung',
            'billing' => 'Quyền lợi',
            'security' => 'Bảo mật',
            'system' => 'Hệ thống',
            default => 'Hoạt động khác',
        };
    }
}
