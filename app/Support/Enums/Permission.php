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
    case QuestionSubmit = 'question.submit';
    case QuestionReview = 'question.review';
    case QuestionFlag = 'question.flag';
    case QuestionPublish = 'question.publish';
    case QuestionAdjudicate = 'question.adjudicate';


    // Sessions
    case SessionCreate = 'session.create';
    case SessionStart = 'session.start';
    case SessionSubmit = 'session.submit';
    case SessionReview = 'session.review';

    // Users & platform admin
    case UserView = 'user.view';
    case ReportView = 'report.view';
    case ReportExport = 'report.export';

    case MediaView = 'media.view';

    // Classroom / live review (Module 44)
    case ClassroomCreate = 'classroom.create';
    case ClassroomManage = 'classroom.manage';
    case ClassroomJoin = 'classroom.join';

    // Feature-gated capabilities
    case ExamTake = 'exam.take';


    // Partner / affiliate (Module 46)
    case PartnerPortal = 'partner.portal';

    /**
     * Primary portal for catalog grouping. Shared abilities still have one home group.
     */
    public function portal(): PortalGroup
    {
        return match ($this) {
            self::SessionCreate,
            self::SessionStart,
            self::SessionSubmit,
            self::SessionReview,
            self::QuestionView,
            self::ClassroomJoin,
            self::ExamTake => PortalGroup::Learner,

            self::ClassroomCreate,
            self::ClassroomManage => PortalGroup::Instructor,

            self::PartnerPortal => PortalGroup::Partner,

            default => PortalGroup::Admin,
        };
    }
}
