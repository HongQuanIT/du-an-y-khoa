<?php

declare(strict_types=1);

namespace Modules\Classroom\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Audit\Auditor;
use App\Support\Audit\Enums\AuditAction;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Classroom\Enums\ClassroomStatus;
use Modules\Classroom\Enums\ClassroomVisibility;
use Modules\Classroom\Models\Classroom;

final class ClassroomSettingsController extends Controller
{
    public function edit(Request $request, Classroom $classroom): View
    {
        $this->authorize('update', $classroom);

        return view('classroom::settings', [
            'classroom' => $classroom,
        ]);
    }

    public function update(Request $request, Classroom $classroom): RedirectResponse
    {
        $this->authorize('update', $classroom);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'visibility' => ['required', 'in:public,unlisted,invite_only'],
            'max_members' => ['nullable', 'integer', 'min:2', 'max:500'],
            'status' => ['nullable', 'in:active,archived'],
        ]);

        $before = [
            'visibility' => $classroom->visibility->value,
            'max_members' => $classroom->max_members,
            'status' => $classroom->status->value,
        ];
        $classroom->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'visibility' => ClassroomVisibility::from($validated['visibility']),
            'max_members' => $validated['max_members'] ?? null,
            'status' => isset($validated['status'])
                ? ClassroomStatus::from($validated['status'])
                : $classroom->status,
        ]);
        Auditor::record(
            AuditAction::ClassroomUpdated,
            $request->user(),
            $classroom,
            $before,
            [
                'visibility' => $classroom->visibility->value,
                'max_members' => $classroom->max_members,
                'status' => $classroom->status->value,
            ],
            metadata: ['changed_fields' => array_keys($validated)],
        );

        return redirect()
            ->route('classroom.settings', $classroom)
            ->with('success', 'Đã cập nhật cài đặt lớp.');
    }
}
