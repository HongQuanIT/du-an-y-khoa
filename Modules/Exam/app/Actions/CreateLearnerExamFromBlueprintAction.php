<?php

declare(strict_types=1);

namespace Modules\Exam\Actions;

use App\Models\User;
use Modules\Exam\Models\Exam;
use Modules\QuestionBank\Models\ExamCatalog;

final class CreateLearnerExamFromBlueprintAction
{
    public function __construct(private BuildFixedExamPaper $builder) {}

    public function handle(User $user, ExamCatalog $catalog): Exam
    {
        return $this->builder->handle($catalog, $user);
    }
}
