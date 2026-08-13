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
}
