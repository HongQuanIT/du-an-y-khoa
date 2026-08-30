<?php

declare(strict_types=1);

namespace App\Support\Enums;

use App\Support\Enums\Concerns\EnumValues;

/**
 * Fine-grained abilities in `{resource}.{action}` form.
 * See srs/00-nen-tang/03-phan-quyen-rbac.md §4.
 */
enum Permission: string
{
    use EnumValues;

    // Question bank
    case QuestionView = 'question.view';
    case QuestionCreate = 'question.create';
    case QuestionUpdate = 'question.update';
    case QuestionDelete = 'question.delete';
    case QuestionPublish = 'question.publish';
    case TopicView = 'topic.view';
    case TopicCreate = 'topic.create';
    case TopicUpdate = 'topic.update';
    case TopicDelete = 'topic.delete';

    // Sessions
    case SessionStart = 'session.start';
    case SessionSubmit = 'session.submit';
    case SessionReview = 'session.review';

    // Library
    case LibraryView = 'library.view';
    case LibraryEdit = 'library.edit';
    case LibraryPublish = 'library.publish';

    // Users & platform admin
    case UserView = 'user.view';
    case UserManage = 'user.manage';
    case UserImpersonate = 'user.impersonate';
    case RoleManage = 'role.manage';
    case PermissionManage = 'permission.manage';
    case AuditView = 'audit.view';
    case ReportExport = 'report.export';
    case CmsManage = 'cms.manage';
    case MediaView = 'media.view';
    case MediaManage = 'media.manage';
    case ContactView = 'contact.view';
    case ContactManage = 'contact.manage';
    case SystemManage = 'system.manage';
    case FeatureFlagManage = 'feature_flag.manage';

    // Classroom / live review (Module 44)
    case ClassroomCreate = 'classroom.create';
    case ClassroomManage = 'classroom.manage';
    case ClassroomJoin = 'classroom.join';
    case ClassroomModerate = 'classroom.moderate';
    case ClassroomOversee = 'classroom.oversee';
    case LiveStart = 'live.start';
    case LiveJoin = 'live.join';
    case LiveForceEnd = 'live.force_end';
    case InstructorAssign = 'instructor.assign';

    // Feature-gated capabilities
    case AiUse = 'ai.use';
    case AnalyticsAdvanced = 'analytics.advanced';
    case ExamTake = 'exam.take';
    case ExamManage = 'exam.manage';

    // Billing / subscription admin
    case BillingManage = 'billing.manage';

    // Partner / affiliate (Module 46)
    case PartnerPortal = 'partner.portal';
    case PartnerCodesManage = 'partner.codes.manage';
    case PartnerReferralsView = 'partner.referrals.view';
    case PartnerCommissionsView = 'partner.commissions.view';
    case AdminPartnersManage = 'admin.partners.manage';
    case AdminPartnersPayouts = 'admin.partners.payouts';

    /**
     * Primary portal for catalog grouping. Shared abilities still have one home group.
     */
    public function portal(): PortalGroup
    {
        return match ($this) {
            self::SessionStart,
            self::SessionSubmit,
            self::SessionReview,
            self::QuestionView,
            self::LibraryView,
            self::ClassroomJoin,
            self::LiveJoin,
            self::AiUse,
            self::AnalyticsAdvanced,
            self::ExamTake => PortalGroup::Learner,

            self::ClassroomCreate,
            self::ClassroomManage,
            self::ClassroomModerate,
            self::LiveStart => PortalGroup::Instructor,

            self::PartnerPortal,
            self::PartnerCodesManage,
            self::PartnerReferralsView,
            self::PartnerCommissionsView => PortalGroup::Partner,

            default => PortalGroup::Admin,
        };
    }
}
