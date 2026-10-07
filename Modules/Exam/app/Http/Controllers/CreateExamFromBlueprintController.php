<?php

declare(strict_types=1);

namespace Modules\Exam\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Exam\Actions\CreateLearnerExamFromBlueprintAction;
use Modules\QuestionBank\Actions\CreateQuestionSessionAction;
use Modules\QuestionBank\Data\CreateSessionData;
use Modules\QuestionBank\Enums\SessionMode;
use Modules\QuestionBank\Enums\SessionSource;
use Modules\QuestionBank\Enums\TaxonomyStatus;
use Modules\QuestionBank\Models\ExamCatalog;
use RuntimeException;

/**
 * Học viên chọn kỳ thi đã gắn ma trận → tạo bài thi cá nhân → vào phòng thi.
 */
final class CreateExamFromBlueprintController extends Controller
{
    public function __construct(
        private readonly CreateLearnerExamFromBlueprintAction $createExam,
        private readonly CreateQuestionSessionAction $createSession,
    ) {}

    public function __invoke(ExamCatalog $examCatalog): RedirectResponse
    {
        abort_unless($examCatalog->status === TaxonomyStatus::Active, 404);
        $examCatalog->loadMissing('blueprint');
        abort_unless($examCatalog->blueprint_id !== null, 404);

        $user = request()->user();
        abort_unless($user instanceof User, 403);

        $professionId = $user->learnerProfile?->profession_id;
        if ($professionId === null) {
            throw ValidationException::withMessages([
                'profession' => 'Hãy chọn chức danh trên hồ sơ trước khi tạo phiên đề thi.',
            ]);
        }

        $allowed = $examCatalog->professions()->where('professions.id', (int) $professionId)->exists();
        abort_unless($allowed, 404);

        try {
            $session = DB::transaction(function () use ($user, $examCatalog) {
                $exam = $this->createExam->handle($user, $examCatalog);
                $session = $this->createSession->handle($user, new CreateSessionData(
                    mode: SessionMode::Exam,
                    source: SessionSource::Exam,
                    count: $exam->questionCount(),
                    examId: $exam->id,
                ));

                $session->update([
                    'time_limit_seconds' => $exam->duration_minutes * 60,
                ]);

                return $session;
            });
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['blueprint' => $exception->getMessage()]);
        }

        return redirect()
            ->route('exam.session', $session)
            ->with('status', 'Đã tạo bài thi từ kỳ thi «'.$examCatalog->name.'».');
    }
}
