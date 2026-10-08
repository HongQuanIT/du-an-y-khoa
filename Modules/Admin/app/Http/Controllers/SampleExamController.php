<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Exam\Actions\BuildFixedExamPaper;
use Modules\Exam\Enums\ExamStatus;
use Modules\Exam\Models\Exam;
use Modules\QuestionBank\Enums\TaxonomyStatus;
use Modules\QuestionBank\Models\ExamCatalog;
use Modules\QuestionBank\Support\BlueprintExamAllocator;

final class SampleExamController extends Controller
{
    public function store(ExamCatalog $examCatalog, BuildFixedExamPaper $builder)
    {
        $exam = $builder->handle($examCatalog);

        return redirect()->route('admin.exams.show', $exam)->with('status', 'Đã tạo bản nháp bài thi mẫu. Kiểm tra bộ câu trước khi xuất bản.');
    }

    public function publish(Exam $exam)
    {
        abort_unless($exam->kind === 'sample' && $exam->paper_snapshot, 422);
        DB::transaction(function () use ($exam): void {
            $catalog = ExamCatalog::query()->lockForUpdate()->findOrFail($exam->exam_catalog_id);
            abort_unless($catalog->status === TaxonomyStatus::Active && $catalog->blueprint?->status === TaxonomyStatus::Active, 422);
            abort_unless((int) $catalog->blueprint_id === (int) $exam->blueprint_id, 422);
            $current = app(BlueprintExamAllocator::class)->allocate($catalog->blueprint);
            abort_unless($current['ready'] && count($exam->paper_snapshot) === $current['total_questions'], 422);
            if ($current != Arr::except($exam->matrix_snapshot, ['difficulty_quotas', 'profession_ids'])
                || $catalog->professions()->pluck('professions.id')->sort()->values()->all() != collect($exam->matrix_snapshot['profession_ids'] ?? [])->sort()->values()->all()) {
                throw ValidationException::withMessages(['sample' => 'Ma trận hoặc chức danh đã thay đổi. Hãy tạo bản nháp mới trước khi xuất bản.']);
            }
            $exam->update(['status' => ExamStatus::Published, 'is_published' => true]);
            $catalog->forceFill(['sample_exam_id' => $exam->id])->save();
        });

        return back()->with('status', 'Đã xuất bản bài thi mẫu. Các lượt làm trước vẫn giữ nguyên.');
    }
}
