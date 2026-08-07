<?php

declare(strict_types=1);

namespace Modules\Classroom\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Classroom\Enums\MemberRole;
use Modules\Classroom\Enums\MemberStatus;
use Modules\Classroom\Models\Classroom;
use Modules\Classroom\Models\ClassroomMember;

final class ClassroomInviteController extends Controller
{
    public function store(Request $request, Classroom $classroom): RedirectResponse
    {
        $this->authorize('update', $classroom);

        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::query()->where('email', $validated['email'])->first();

        if ($user === null) {
            return back()->withErrors(['email' => 'Không tìm thấy tài khoản với email này.']);
        }

        ClassroomMember::query()->updateOrCreate(
            [
                'classroom_id' => $classroom->getKey(),
                'user_id' => $user->getKey(),
            ],
            [
                'role_in_class' => MemberRole::Member,
                'status' => MemberStatus::Invited,
                'joined_at' => null,
            ],
        );

        return back()->with('success', 'Đã mời '.$user->name.'.');
    }
}
