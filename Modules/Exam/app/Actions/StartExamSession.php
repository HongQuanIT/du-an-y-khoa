<?php

declare(strict_types=1);

namespace Modules\Exam\Actions;

use App\Models\User;
use Illuminate\Validation\ValidationException;
use Modules\Exam\Models\Exam;
use Modules\QuestionBank\Actions\CreateQuestionSessionAction;
use Modules\QuestionBank\Data\CreateSessionData;
use Modules\QuestionBank\Enums\SessionMode;
use Modules\QuestionBank\Enums\SessionSource;
use Modules\QuestionBank\Models\QuestionSession;
use RuntimeException;

final class StartExamSession
{
    public function __construct(private readonly CreateQuestionSessionAction $createSession) {}

    public function handle(User $user, Exam $exam): QuestionSession
    {
        $questionCount = $exam->paper_snapshot ? count($exam->paper_snapshot) : $exam->questions()->count();
        if ($questionCount === 0) {
            throw ValidationException::withMessages(['exam' => 'Bài thi này chưa có câu hỏi nào.']);
        }

        try {
            $session = $this->createSession->handle($user, new CreateSessionData(
                mode: SessionMode::Exam,
                source: SessionSource::Exam,
                count: $questionCount,
                examId: $exam->id,
            ));
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['count' => $exception->getMessage()]);
        }

        $session->update(['time_limit_seconds' => $exam->duration_minutes * 60]);

        return $session;
    }
}
