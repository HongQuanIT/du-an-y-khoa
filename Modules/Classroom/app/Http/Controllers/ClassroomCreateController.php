<?php

declare(strict_types=1);

namespace Modules\Classroom\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Enums\Entitlement;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Modules\Classroom\Actions\CreateClassroomAction;
use Modules\Classroom\Enums\ClassroomVisibility;
use Modules\Classroom\Http\Requests\StoreClassroomRequest;
use Modules\Classroom\Models\Classroom;

final class ClassroomCreateController extends Controller
{
    public function create(): View|RedirectResponse
    {
        $this->authorize('create', Classroom::class);

        return view('classroom::create', [
            'visibilities' => ClassroomVisibility::cases(),
        ]);
    }

    public function store(StoreClassroomRequest $request, CreateClassroomAction $action): RedirectResponse
    {
        $this->authorize('create', Classroom::class);

        if (! $request->user()->hasEntitlement(Entitlement::ClassroomHost->value)) {
            return redirect()
                ->route('landing.pricing')
                ->with('error', 'Cần Premium để tạo lớp chữa đề.');
        }

        $classroom = $action->handle($request->user(), $request->validated());

        return redirect()
            ->route('classroom.show', $classroom)
            ->with('success', 'Đã tạo lớp học.');
    }
}
