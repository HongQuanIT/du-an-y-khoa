<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use App\Support\Enums\Entitlement;
use Illuminate\Support\Facades\DB;
use Modules\Personalization\Models\Bookmark;
use Modules\QuestionBank\Enums\SessionMode;
use Modules\QuestionBank\Enums\SessionSource;
use Modules\QuestionBank\Enums\SessionStatus;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionSession;
use Modules\QuestionBank\Services\QuestionSessionSnapshots;
use Modules\QuestionBank\Support\ServePublishedQuestion;
use RuntimeException;

/** Start a study session from the learner's saved questions. */
final class CreateSessionFromBookmarksAction
{
    use AsAction;

    public function __construct(private readonly QuestionSessionSnapshots $snapshots) {}

    /**
     * @param  array<int, string>  $questionIds
     */
    public function handle(User $user, array $questionIds): QuestionSession
    {
        $maxQuestions = $user->hasEntitlement(Entitlement::QbankFull->value) ? 100 : 20;
        $requested = array_values(array_unique(array_filter(
            array_map('strval', $questionIds),
            static fn (string $id): bool => $id !== '',
        )));

        if ($requested === []) {
            throw new RuntimeException('Hãy chọn ít nhất một câu hỏi đã lưu.');
        }

        $owned = array_flip(Bookmark::questionIdsForUser((int) $user->getKey()));
        $ownedIds = array_values(array_filter(
            $requested,
            static fn (string $id): bool => isset($owned[$id]),
        ));

        $available = ServePublishedQuestion::scopeAvailable(Question::query())
            ->whereIn('id', $ownedIds)
            ->pluck('id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();
        $availableLookup = array_fill_keys($available, true);

        $questionIds = collect($ownedIds)
            ->filter(static fn (string $id): bool => isset($availableLookup[$id]))
            ->take($maxQuestions)
            ->values()
            ->all();

        if ($questionIds === []) {
            throw new RuntimeException('Không còn câu hỏi đã lưu nào khả dụng để tạo phiên.');
        }

        return DB::transaction(function () use ($user, $questionIds): QuestionSession {
            $session = QuestionSession::create([
                'user_id' => $user->getKey(),
                'mode' => SessionMode::Study,
                'status' => SessionStatus::Active,
                'source' => SessionSource::Custom,
                'filters' => [
                    'name' => 'Câu hỏi đã lưu',
                    'saved_only' => true,
                    'count' => count($questionIds),
                ],
                'question_ids' => $questionIds,
                'total' => count($questionIds),
                'answered_count' => 0,
                'correct_count' => 0,
                'time_limit_seconds' => null,
                'paused_state' => null,
                'annotations' => [],
            ]);
            $this->snapshots->capture($session);

            return $session;
        });
    }
}
