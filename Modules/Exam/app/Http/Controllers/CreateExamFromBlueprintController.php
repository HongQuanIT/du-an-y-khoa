<?php

declare(strict_types=1);

namespace Modules\Exam\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Modules\Exam\Actions\CreateLearnerExamFromBlueprintAction;
use Modules\QuestionBank\Actions\CreateQuestionSessionAction;
use Modules\QuestionBank\Data\CreateSessionData;
use Modules\QuestionBank\Enums\SessionMode;
use Modules\QuestionBank\Enums\SessionSource;
use Modules\QuestionBank\Enums\TaxonomyStatus;
use Modules\QuestionBank\Models\Blueprint;
use RuntimeException;

/**
 * Học viên chọn kỳ thi (ma trận) → tạo bài thi cá nhân → vào phòng thi.
 */
final class CreateExamFromBlueprintController extends Controller
{
    public function __construct(
        private readonly CreateLearnerExamFromBlueprintAction $createExam,
        private readonly CreateQuestionSessionAction $createSession,
    ) {}

    public function __invoke(Blueprint $blueprint): RedirectResponse
    {
        abort_unless($blueprint->status === TaxonomyStatus::Active, 404);

        $user = request()->user();
        abort_unless($user instanceof User, 403);

        try {
            $exam = $this->createExam->handle($user, $blueprint);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        $questionCount = $exam->questions()->count();

        try {
            $session = $this->createSession->handle($user, new CreateSessionData(
                mode: SessionMode::Exam,
                source: SessionSource::Exam,
                count: $questionCount,
                examId: $exam->id,
            ));

            $session->update([
                'time_limit_seconds' => $exam->duration_minutes * 60,
            ]);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['blueprint' => $exception->getMessage()]);
        }

        return redirect()
            ->route('exam.session', $session)
            ->with('status', 'Đã tạo bài thi từ ma trận «'.$blueprint->name.'».');
    }
}
