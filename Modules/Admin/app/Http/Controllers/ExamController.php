<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Exam\Enums\ExamStatus;
use Modules\Exam\Models\Exam;
use Modules\QuestionBank\Models\Question;

final class ExamController extends Controller
{
    public function index(): View
    {
        $exams = Exam::query()
            ->withCount('questions')
            ->latest()
            ->paginate(20);

        return view('admin::exams.index', compact('exams'));
    }

    public function create(): View
    {
        $exam = new Exam();
        $exam->duration_minutes = 90;
        $exam->status = ExamStatus::Draft;
        $exam->is_published = false;
        $exam->setAttribute('questions_count', 0);

        $availableQuestions = $this->availableQuestions();

        return view('admin::exams.form', compact('exam', 'availableQuestions'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'duration_minutes' => 'required|integer|min:1',
            'icon' => 'nullable|file|image|max:2048',
            'status' => ['required', 'string', Rule::in(ExamStatus::values())],
            'questions' => 'nullable|array',
            'questions.*' => 'exists:questions,id',
        ]);

        $syncData = $this->questionSyncData($validated['questions'] ?? []);

        $status = ExamStatus::from($validated['status']);

        if ($status === ExamStatus::Published && count($syncData) === 0) {
            return back()
                ->withErrors(['status' => 'Phải thêm ít nhất 1 câu hỏi trước khi xuất bản.'])
                ->withInput();
        }

        $iconPath = null;
        if ($request->hasFile('icon')) {
            $iconPath = $request->file('icon')->store('exams', 'public');
        }

        $exam = Exam::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'duration_minutes' => $validated['duration_minutes'],
            'icon' => $iconPath,
            'status' => $status,
            'is_published' => $status === ExamStatus::Published,
        ]);

        $exam->questions()->sync($syncData);

        return redirect()->route('admin.exams.edit', $exam)->with('status', 'Kỳ thi đã được tạo.');
    }

    public function edit(Exam $exam): View
    {
        $exam->loadCount('questions');
        $exam->load(['questions' => fn ($q) => $q->orderBy('exam_question.order')]);
        
        $availableQuestions = $this->availableQuestions();
            
        return view('admin::exams.form', compact('exam', 'availableQuestions'));
    }

    public function update(Request $request, Exam $exam): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'duration_minutes' => 'required|integer|min:1',
            'icon' => 'nullable|file|image|max:2048',
            'status' => ['required', 'string', Rule::in(ExamStatus::values())],
            'questions' => 'nullable|array',
            'questions.*' => 'exists:questions,id',
        ]);

        $syncData = $this->questionSyncData($validated['questions'] ?? []);

        $status = ExamStatus::from($validated['status']);

        if ($status === ExamStatus::Published && count($syncData) === 0) {
            return back()
                ->withErrors(['status' => 'Phải thêm ít nhất 1 câu hỏi trước khi xuất bản.'])
                ->withInput();
        }

        $data = [
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'duration_minutes' => $validated['duration_minutes'],
            'status' => $status,
            'is_published' => $status === ExamStatus::Published,
        ];

        if ($request->hasFile('icon')) {
            $data['icon'] = $request->file('icon')->store('exams', 'public');
        }

        $exam->update($data);

        $exam->questions()->sync($syncData);

        return redirect()->route('admin.exams.index')->with('status', 'Đã cập nhật kỳ thi.');
    }

    public function destroy(Exam $exam): RedirectResponse
    {
        $exam->delete();
        return redirect()->route('admin.exams.index')->with('status', 'Đã xóa kỳ thi.');
    }

    private function availableQuestions()
    {
        return Question::query()
            ->latest()
            ->limit(100)
            ->get();
    }

    /**
     * @param array<int, string> $questionIds
     * @return array<string, array{order: int}>
     */
    private function questionSyncData(array $questionIds): array
    {
        $syncData = [];

        foreach ($questionIds as $index => $questionId) {
            $syncData[$questionId] = ['order' => $index + 1];
        }

        return $syncData;
    }
}
