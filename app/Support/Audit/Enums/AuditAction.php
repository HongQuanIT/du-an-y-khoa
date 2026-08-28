<?php

declare(strict_types=1);

namespace App\Support\Audit\Enums;

enum AuditAction: string
{
    case AuthRegistered = 'auth.registered';
    case AuthLogin = 'auth.login';
    case AuthLoginFailed = 'auth.login_failed';
    case AuthLogout = 'auth.logout';
    case AuthPasswordChanged = 'auth.password_changed';
    case AuthPasswordReset = 'auth.password_reset';
    case AuthTwoFactorEnabled = 'auth.2fa.enabled';
    case AuthTwoFactorDisabled = 'auth.2fa.disabled';

    case AccountProfileUpdated = 'account.profile.updated';
    case AccountPreferencesUpdated = 'account.preferences.updated';
    case AccountAvatarUpdated = 'account.avatar.updated';
    case AccountAvatarDeleted = 'account.avatar.deleted';

    case ClassroomCreated = 'classroom.created';
    case ClassroomUpdated = 'classroom.updated';
    case ClassroomClosed = 'classroom.closed';
    case ClassroomReopened = 'classroom.reopened';
    case ClassroomDeleted = 'classroom.deleted';
    case ClassroomJoined = 'classroom.joined';
    case ClassroomLeft = 'classroom.left';
    case ClassroomInviteCreated = 'classroom.invite.created';
    case ClassroomLiveScheduled = 'classroom.live.scheduled';
    case ClassroomLiveStarted = 'classroom.live.started';
    case ClassroomLiveEnded = 'classroom.live.ended';
    case ClassroomRecordingStarted = 'classroom.recording.started';
    case ClassroomRecordingStopped = 'classroom.recording.stopped';
    case ClassroomMemberKicked = 'classroom.member.kicked';
    case ClassroomChatToggled = 'classroom.chat.toggled';
    case ClassroomQuestionChanged = 'classroom.question.changed';
    case ClassroomMessageDeleted = 'classroom.message.deleted';

    case LearningSessionCreated = 'learning.session.created';
    case LearningSessionPaused = 'learning.session.paused';
    case LearningSessionResumed = 'learning.session.resumed';
    case LearningSessionCompleted = 'learning.session.completed';
    case LearningSessionDeleted = 'learning.session.deleted';
    case LearningQuestionAnswered = 'learning.question.answered';
    case LearningPlanCreated = 'learning.plan.created';
    case LearningPlanUpdated = 'learning.plan.updated';
    case LearningPlanDeleted = 'learning.plan.deleted';
    case LearningPlanReplanned = 'learning.plan.replanned';
    case LearningTaskStarted = 'learning.task.started';
    case LearningTaskCompleted = 'learning.task.completed';
    case LearningTaskSkipped = 'learning.task.skipped';
    case LearningTaskRescheduled = 'learning.task.rescheduled';
    case LearningBookmarkChanged = 'learning.bookmark.changed';
    case LearningBookmarkFolderCreated = 'learning.bookmark_folder.created';
    case LearningBookmarkFolderDeleted = 'learning.bookmark_folder.deleted';

    case ExamStarted = 'exam.started';
    case ExamCompleted = 'exam.completed';

    case BillingCodeRedeemed = 'billing.code.redeemed';
    case BillingLicenseActivated = 'billing.license.activated';
    case BillingLicenseRenewed = 'billing.license.renewed';

    /**
     * Legacy high-frequency learning events retained only to suppress and label
     * rows written before session auditing was reduced to start/finish events.
     *
     * @return array<int, string>
     */
    public static function hiddenLearningDetailValues(): array
    {
        return [
            self::LearningQuestionAnswered->value,
            self::LearningSessionPaused->value,
            self::LearningSessionResumed->value,
            self::LearningSessionDeleted->value,
        ];
    }

    public function label(): string
    {
        return match ($this) {
            self::AuthRegistered => 'Đăng ký tài khoản',
            self::AuthLogin => 'Đăng nhập',
            self::AuthLoginFailed => 'Đăng nhập thất bại',
            self::AuthLogout => 'Đăng xuất',
            self::AuthPasswordChanged => 'Đổi mật khẩu',
            self::AuthPasswordReset => 'Đặt lại mật khẩu',
            self::AuthTwoFactorEnabled => 'Bật xác thực hai lớp',
            self::AuthTwoFactorDisabled => 'Tắt xác thực hai lớp',
            self::AccountProfileUpdated => 'Cập nhật hồ sơ',
            self::AccountPreferencesUpdated => 'Cập nhật tùy chọn tài khoản',
            self::AccountAvatarUpdated => 'Cập nhật ảnh đại diện',
            self::AccountAvatarDeleted => 'Xóa ảnh đại diện',
            self::ClassroomCreated => 'Tạo lớp học',
            self::ClassroomUpdated => 'Cập nhật lớp học',
            self::ClassroomClosed => 'Đóng lớp học',
            self::ClassroomReopened => 'Mở lại lớp học',
            self::ClassroomDeleted => 'Xóa lớp học',
            self::ClassroomJoined => 'Tham gia lớp học',
            self::ClassroomLeft => 'Rời lớp học',
            self::ClassroomInviteCreated => 'Tạo lời mời lớp học',
            self::ClassroomLiveScheduled => 'Lên lịch buổi live',
            self::ClassroomLiveStarted => 'Bắt đầu buổi live',
            self::ClassroomLiveEnded => 'Kết thúc buổi live',
            self::ClassroomRecordingStarted => 'Bắt đầu ghi hình buổi live',
            self::ClassroomRecordingStopped => 'Dừng ghi hình buổi live',
            self::ClassroomMemberKicked => 'Mời học viên khỏi live',
            self::ClassroomChatToggled => 'Thay đổi trạng thái chat',
            self::ClassroomQuestionChanged => 'Thay đổi câu hỏi live',
            self::ClassroomMessageDeleted => 'Xóa tin nhắn live',
            self::LearningSessionCreated => 'Bắt đầu làm bài',
            self::LearningSessionPaused => 'Tạm dừng phiên học',
            self::LearningSessionResumed => 'Tiếp tục phiên học',
            self::LearningSessionCompleted => 'Kết thúc làm bài',
            self::LearningSessionDeleted => 'Xóa phiên học',
            self::LearningQuestionAnswered => 'Trả lời câu hỏi',
            self::LearningPlanCreated => 'Tạo kế hoạch học',
            self::LearningPlanUpdated => 'Cập nhật kế hoạch học',
            self::LearningPlanDeleted => 'Xóa kế hoạch học',
            self::LearningPlanReplanned => 'Lập lại kế hoạch học',
            self::LearningTaskStarted => 'Bắt đầu nhiệm vụ học',
            self::LearningTaskCompleted => 'Hoàn thành nhiệm vụ học',
            self::LearningTaskSkipped => 'Bỏ qua nhiệm vụ học',
            self::LearningTaskRescheduled => 'Đổi lịch nhiệm vụ học',
            self::LearningBookmarkChanged => 'Thay đổi bookmark',
            self::LearningBookmarkFolderCreated => 'Tạo thư mục bookmark',
            self::LearningBookmarkFolderDeleted => 'Xóa thư mục bookmark',
            self::ExamStarted => 'Bắt đầu kỳ thi',
            self::ExamCompleted => 'Hoàn thành kỳ thi',
            self::BillingCodeRedeemed => 'Sử dụng mã quyền lợi',
            self::BillingLicenseActivated => 'Kích hoạt giấy phép',
            self::BillingLicenseRenewed => 'Gia hạn giấy phép',
        };
    }
}
