<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\View\View;
use Modules\Exam\Models\Exam;
use Modules\QuestionBank\Models\QuestionSession;
use Modules\QuestionBank\Support\BlueprintExamAllocator;

/**
 * Admin chỉ giám sát danh sách bài thi do học viên tạo từ ma trận.
 */
final class ExamController extends Controller
{
    public function index(Request $request): View
    {
        $exams = Exam::query()
            ->with(['user:id,name,email', 'blueprint:id,name,code'])
            ->withCount('questions')
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = '%'.trim((string) $request->query('q')).'%';
                $query->where(function ($builder) use ($term): void {
                    $builder->where('title', 'like', $term)
                        ->orWhereHas('user', fn ($userQuery) => $userQuery
                            ->where('name', 'like', $term)
                            ->orWhere('email', 'like', $term))
                        ->orWhereHas('blueprint', fn ($bpQuery) => $bpQuery
                            ->where('name', 'like', $term)
                            ->orWhere('code', 'like', $term));
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin::exams.index', compact('exams'));
    }

    public function show(Exam $exam): View
    {
        $matrixChanged = false;
        if ($exam->kind === 'sample' && $exam->examCatalog?->blueprint) {
            $current = app(BlueprintExamAllocator::class)->allocate($exam->examCatalog->blueprint);
            $matrixChanged = $current != Arr::except($exam->matrix_snapshot ?? [], ['difficulty_quotas', 'profession_ids']);
            $matrixChanged = $matrixChanged || $exam->examCatalog->professions()->pluck('professions.id')->sort()->values()->all()
                != collect($exam->matrix_snapshot['profession_ids'] ?? [])->sort()->values()->all();
        }
        $exam->loadCount('questions');
        $exam->load([
            'user:id,name,email',
            'blueprint:id,name,code,description',
            'examTopics.coreClinicalTopic.section',
            'questions' => fn ($q) => $q->orderBy('exam_question.order'),
        ]);

        return view('admin::exams.show', compact('exam', 'matrixChanged'));
    }

    public function destroy(Exam $exam): RedirectResponse
    {
        if ($exam->kind !== 'legacy' && QuestionSession::where('exam_id', $exam->id)->exists()) {
            return back()->withErrors(['exam' => 'Bài thi đã có lượt làm. Không thể xóa bộ đề và lịch sử đã sử dụng.']);
        }
        $exam->delete();

        return redirect()->route('admin.exams.index')->with('status', 'Đã xóa bài thi.');
    }
}
