<?php

declare(strict_types=1);

namespace Modules\Admin\Enums;

/**
 * Canonical audit event names for User and Question administration.
 *
 * Auditor still accepts strings so existing modules remain backwards compatible.
 */
enum AuditAction: string
{
    case UserCreated = 'admin.user.create';
    case UserRoleChanged = 'admin.user.role_change';
    case UserStatusChanged = 'admin.user.status_change';
    case UserEmailVerified = 'admin.user.email_verified';
    case UserPasswordResetRequested = 'admin.user.password_reset';

    case QuestionCreated = 'admin.question.create';
    case QuestionUpdated = 'admin.question.update';
    case QuestionUpdateRequested = 'admin.question.update_requested';
    case QuestionDeleted = 'admin.question.delete';
    case QuestionDeleteRequested = 'admin.question.delete_requested';
    case QuestionStatusChanged = 'admin.question.status_change';
    case QuestionVersionRestored = 'admin.question.version_restore';
    case QuestionReviewApproved = 'admin.question.review_approved';
    case QuestionReviewRejected = 'admin.question.review_rejected';

    public function group(): string
    {
        return str_starts_with($this->value, 'admin.user.') ? 'user' : 'question';
    }

    public function label(): string
    {
        return match ($this) {
            self::UserCreated => 'Tạo người dùng',
            self::UserRoleChanged => 'Đổi vai trò người dùng',
            self::UserStatusChanged => 'Đổi trạng thái người dùng',
            self::UserEmailVerified => 'Xác minh email người dùng',
            self::UserPasswordResetRequested => 'Gửi yêu cầu đặt lại mật khẩu',
            self::QuestionCreated => 'Tạo câu hỏi',
            self::QuestionUpdated => 'Cập nhật câu hỏi',
            self::QuestionUpdateRequested => 'Gửi yêu cầu sửa câu hỏi',
            self::QuestionDeleted => 'Xóa câu hỏi',
            self::QuestionDeleteRequested => 'Gửi yêu cầu xóa câu hỏi',
            self::QuestionStatusChanged => 'Đổi trạng thái câu hỏi',
            self::QuestionVersionRestored => 'Khôi phục phiên bản câu hỏi',
            self::QuestionReviewApproved => 'Phê duyệt thay đổi câu hỏi',
            self::QuestionReviewRejected => 'Từ chối thay đổi câu hỏi',
        };
    }
}
