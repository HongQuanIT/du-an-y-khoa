<?php

declare(strict_types=1);

namespace Modules\Personalization\Actions;

use App\Models\User;
use App\Support\Audit\Auditor;
use App\Support\Audit\Enums\AuditAction;
use App\Support\Concerns\AsAction;
use Modules\Personalization\Models\Note;

final class DeleteNoteAction
{
    use AsAction;

    public function handle(User $user, Note $note): void
    {
        abort_unless((int) $note->user_id === (int) $user->getKey(), 403);

        $note->delete();

        Auditor::record(AuditAction::LearningNoteDeleted, $user, $note);
    }
}
