<?php

declare(strict_types=1);

namespace Modules\StudyPlan\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Modules\QuestionBank\Models\Topic;
use Modules\StudyPlan\Actions\DeleteStudyPlanAction;
use Modules\StudyPlan\Actions\UpdateStudyPlanAction;
use Modules\StudyPlan\Http\Requests\StudyPlanRequest;
use Modules\StudyPlan\Models\StudyPlan;
use Modules\StudyPlan\Support\TargetExams;

/**
 * Edit or delete a plan (srs/modules/04 route `/study-plan/{id}/edit`).
 */
final class StudyPlanEditController extends Controller
{
    public function __construct(
        private readonly UpdateStudyPlanAction $updatePlan,
        private readonly DeleteStudyPlanAction $deletePlan,
    ) {}

    public function edit(StudyPlan $plan): View
    {
        $this->authorize('update', $plan);

        return view('studyplan::edit', [
            'plan' => $plan,
            'exams' => TargetExams::selectable(),
            'specialties' => Topic::query()
                ->where('type', 'specialty')
                ->orderBy('order')
                ->get(),
            'systems' => Topic::query()
                ->where('type', 'system')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function update(StudyPlanRequest $request, StudyPlan $plan): RedirectResponse
    {
        $this->authorize('update', $plan);

        $this->updatePlan->handle($plan, $request->toData());

        return redirect()
            ->route('study-plan.detail', $plan)
            ->with('status', 'Đã cập nhật kế hoạch và phân bổ lại các ngày còn lại.');
    }

    public function destroy(StudyPlan $plan): RedirectResponse
    {
        $this->authorize('delete', $plan);

        $this->deletePlan->handle($plan);

        return redirect()
            ->route('study-plan.index')
            ->with('status', 'Đã xóa kế hoạch.');
    }
}
