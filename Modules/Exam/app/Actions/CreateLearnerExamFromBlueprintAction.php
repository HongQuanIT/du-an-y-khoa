<?php

declare(strict_types=1);

namespace Modules\Exam\Actions;

use App\Models\User;
use App\Support\Enums\Entitlement;
use Illuminate\Auth\Access\AuthorizationException;
use Modules\Exam\Models\Exam;
use Modules\QuestionBank\Models\ExamCatalog;

final class CreateLearnerExamFromBlueprintAction
{
    public function __construct(private BuildFixedExamPaper $builder) {}

    public function handle(User $user, ExamCatalog $catalog): Exam
    {
        if (! $user->hasEntitlement(Entitlement::ExamSimulation->value)) {
            throw new AuthorizationException('Cần gói Premium để tạo đề thi mới.');
        }

        return $this->builder->handle($catalog, $user);
    }
}
