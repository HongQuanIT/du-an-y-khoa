<?php

declare(strict_types=1);

namespace Modules\Admin\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Admin\Enums\AuditAction;
use Modules\Admin\Support\Auditor;
use Modules\Admin\Support\AuditSnapshot;
use Modules\QuestionBank\Models\Question;

/** Soft-delete after an admin has confirmed the shared-usage check. */
final class RequestQuestionDeletionAction
{
    public function handle(User $actor, Question $question): void
    {
        DB::transaction(function () use ($actor, $question): void {
            $question = Question::query()->lockForUpdate()->findOrFail($question->getKey());
            $before = AuditSnapshot::question($question);

            Auditor::record(AuditAction::QuestionDeleted, $actor, $question, $before, null);
            $question->delete();
        });
    }
}
