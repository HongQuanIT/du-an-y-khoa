<?php

declare(strict_types=1);

namespace Modules\Personalization\Actions;

use App\Models\User;
use App\Support\Audit\Auditor;
use App\Support\Audit\Enums\AuditAction;
use App\Support\Concerns\AsAction;
use Modules\Personalization\Models\Bookmark;
use Modules\QuestionBank\Models\Question;

final class SetQuestionBookmarkAction
{
    use AsAction;

    public function handle(User $user, Question $question, bool $bookmarked): bool
    {
        $target = [
            'user_id' => $user->getKey(),
            'bookmarkable_type' => Bookmark::TYPE_QUESTION,
            'bookmarkable_id' => (string) $question->getKey(),
        ];

        if ($bookmarked) {
            Bookmark::query()->firstOrCreate($target);
        } else {
            Bookmark::query()->where($target)->delete();
        }

        Auditor::record(
            AuditAction::LearningBookmarkChanged,
            $user,
            $question,
            metadata: ['bookmarked' => $bookmarked],
        );

        return $bookmarked;
    }
}
