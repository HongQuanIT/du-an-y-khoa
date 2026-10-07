<?php

declare(strict_types=1);

namespace Modules\Exam\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Modules\Exam\Actions\CreateLearnerExamFromBlueprintAction;
use Modules\QuestionBank\Enums\TaxonomyStatus;
use Modules\QuestionBank\Models\ExamCatalog;

/**
 * Học viên chọn kỳ thi đã gắn ma trận → tạo đề cá nhân để làm sau.
 */
final class CreateExamFromBlueprintController extends Controller
{
    public function __construct(private readonly CreateLearnerExamFromBlueprintAction $createExam) {}

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
            $exam = $this->createExam->handle($user, $examCatalog);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        $reusedCount = (int) ($exam->matrix_snapshot['reused_question_count'] ?? 0);
        $notice = $reusedCount > 0
            ? " Có {$reusedCount}/{$exam->questionCount()} câu đã xuất hiện trong bài mẫu hoặc các đề bạn đã tạo vì ngân hàng chưa đủ câu mới."
            : '';

        return redirect()
            ->route('exam.index')
            ->with('status', 'Đã tạo đề từ kỳ thi «'.$examCatalog->name.'».'.$notice.' Bấm “Làm” trong mục “Đề thi của bạn” khi bạn muốn bắt đầu.');
    }
}
