<?php

declare(strict_types=1);

namespace Modules\Exam\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Enums\Entitlement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Exam\Enums\ExamStatus;
use Modules\Exam\Http\Requests\StartExamRequest;
use Modules\Exam\Models\Exam;
use Modules\QuestionBank\Actions\CreateQuestionSessionAction;
use Modules\QuestionBank\Data\CreateSessionData;
use Modules\QuestionBank\Enums\SessionMode;
use Modules\QuestionBank\Enums\SessionSource;
use Modules\QuestionBank\Enums\TaxonomyStatus;
use Modules\QuestionBank\Models\QuestionSession;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Tiếp tục / làm lại một bài thi đã tạo (cùng bộ câu hỏi).
 */
final class StartExamController extends Controller
{
    public function __construct(private readonly CreateQuestionSessionAction $createSession) {}

    public function __invoke(StartExamRequest $request, Exam $exam): RedirectResponse
    {
        if ($exam->status !== ExamStatus::Published) {
            throw new NotFoundHttpException;
        }

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        if ($exam->kind === 'sample') {
            $catalog = $exam->examCatalog;
            $hasAttempt = QuestionSession::where('exam_id', $exam->id)->where('user_id', $user->id)->exists();
            abort_unless($catalog && ((int) $catalog->sample_exam_id === (int) $exam->id || $hasAttempt)
                && $catalog->status === TaxonomyStatus::Active, 404);
            abort_unless($catalog->professions()->where('professions.id', $user->learnerProfile?->profession_id)->exists(), 403);
            abort_unless(in_array((int) $user->learnerProfile?->profession_id, $exam->matrix_snapshot['profession_ids'] ?? [], true), 403);
        } elseif (! $user->hasEntitlement(Entitlement::ExamSimulation->value)) {
            return redirect()->route('subscription.upgrade');
        }

        // Chỉ chủ bài thi (hoặc bài thi hệ thống cũ không có user) mới được làm lại.
        if ($exam->user_id !== null && (int) $exam->user_id !== (int) $user->id) {
            abort(403);
        }

        if (DB::table('exam_question')
            ->join('questions', 'questions.id', '=', 'exam_question.question_id')
            ->where('exam_question.exam_id', $exam->id)
            ->where('questions.code', 'like', 'EXAM-BP%')
            ->exists()) {
            throw ValidationException::withMessages([
                'exam' => 'Đề này chứa câu hỏi giả lập cũ. Quản trị viên cần tạo lại bài mẫu hoặc bạn hãy tạo đề Premium mới.',
            ]);
        }

        $questionCount = $exam->paper_snapshot ? count($exam->paper_snapshot) : $exam->questions()->count();
        if ($questionCount === 0) {
            return back()->with('error', 'Bài thi này chưa có câu hỏi nào.');
        }

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
            throw ValidationException::withMessages(['count' => $exception->getMessage()]);
        }

        return redirect()
            ->route('exam.session', $session)
            ->with('status', 'Đã bắt đầu bài thi.');
    }
}
