<?php

declare(strict_types=1);

namespace Modules\StudyPlan\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Modules\QuestionBank\Models\OrganSystem;
use Modules\QuestionBank\Models\Subject;
use Modules\StudyPlan\Actions\CreateStudyPlanAction;
use Modules\StudyPlan\Http\Requests\StudyPlanRequest;
use Modules\StudyPlan\Support\TargetExams;

/**
 * Create wizard: pick exam, date, scope and intensity, then generate the plan
 * (srs/modules/04 §3 StudyPlanWizard).
 */
final class StudyPlanCreateController extends Controller
{
    public function __construct(private readonly CreateStudyPlanAction $createPlan) {}

    public function create(Request $request): View
    {
        return view('studyplan::create', [
            'exams' => TargetExams::selectable(),
            'specialties' => $this->specialties(),
            'systems' => $this->systems(),
            'defaultDate' => now()->addMonths(3)->toDateString(),
        ]);
    }

    public function store(StudyPlanRequest $request): RedirectResponse
    {
        $plan = $this->createPlan->handle($request->user(), $request->toData());

        return redirect()
            ->route('study-plan.detail', $plan)
            ->with('status', 'Đã tạo lộ trình học của bạn.');
    }

    /**
     * @return Collection<int, Subject>
     */
    private function specialties()
    {
        return Subject::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, OrganSystem>
     */
    private function systems()
    {
        return OrganSystem::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }
}
