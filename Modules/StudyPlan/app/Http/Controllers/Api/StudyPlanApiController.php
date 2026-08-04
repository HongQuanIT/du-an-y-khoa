<?php

declare(strict_types=1);

namespace Modules\StudyPlan\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\StudyPlan\Actions\CreateStudyPlanAction;
use Modules\StudyPlan\Actions\DeleteStudyPlanAction;
use Modules\StudyPlan\Actions\UpdateStudyPlanAction;
use Modules\StudyPlan\Http\Requests\StudyPlanRequest;
use Modules\StudyPlan\Http\Resources\StudyPlanResource;
use Modules\StudyPlan\Models\StudyPlan;
use Modules\StudyPlan\Repositories\StudyPlanRepository;

/**
 * REST surface for plans (srs/modules/04 §7). Same actions as the Blade flow,
 * only the presentation differs.
 */
final class StudyPlanApiController extends Controller
{
    public function __construct(
        private readonly StudyPlanRepository $plans,
        private readonly CreateStudyPlanAction $createPlan,
        private readonly UpdateStudyPlanAction $updatePlan,
        private readonly DeleteStudyPlanAction $deletePlan,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::item(
            StudyPlanResource::collection($this->plans->allFor($request->user()))->resolve(),
        );
    }

    public function store(StudyPlanRequest $request): JsonResponse
    {
        $plan = $this->createPlan->handle($request->user(), $request->toData());

        return ApiResponse::item(new StudyPlanResource($plan), 201);
    }

    public function show(StudyPlan $plan): JsonResponse
    {
        $this->authorize('view', $plan);

        return ApiResponse::item(new StudyPlanResource($plan));
    }

    public function update(StudyPlanRequest $request, StudyPlan $plan): JsonResponse
    {
        $this->authorize('update', $plan);

        return ApiResponse::item(
            new StudyPlanResource($this->updatePlan->handle($plan, $request->toData())),
        );
    }

    public function destroy(StudyPlan $plan): JsonResponse
    {
        $this->authorize('delete', $plan);

        $this->deletePlan->handle($plan);

        return ApiResponse::item(['deleted' => true]);
    }
}
