<?php

declare(strict_types=1);

namespace Modules\Classroom\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Enums\Entitlement;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Modules\Classroom\Models\Classroom;
use Modules\QuestionBank\Enums\SessionStatus;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionSession;

final class ClassroomShowController extends Controller
{
    public function __invoke(Request $request, Classroom $classroom): View
    {
        $this->authorize('view', $classroom);

        $classroom->load([
            'host',
            'sessions' => fn ($q) => $q->limit(20),
            'activeMembers.user',
        ]);

        $user = $request->user();
        $membership = $classroom->memberFor($user);

        $qbankSessions = $user->can('manageLive', $classroom)
            ? QuestionSession::query()
                ->where('user_id', $user->getKey())
                ->where('status', SessionStatus::Completed)
                ->latest()
                ->limit(20)
                ->get(['id', 'total', 'mode', 'created_at'])
            : collect();

        $sampleQuestions = $user->can('manageLive', $classroom)
            ? Question::query()
                ->where('status', 'published')
                ->latest()
                ->limit(30)
                ->get(['id', 'stem'])
            : collect();

        return view('classroom::show', [
            'classroom' => $classroom,
            'membership' => $membership,
            'isMember' => $classroom->isActiveMember($user),
            'canManage' => $user->can('update', $classroom),
            'canHostLive' => $user->can('manageLive', $classroom),
            'canStartLive' => $user->can('startLive', $classroom),
            'canHostEntitlement' => $user->hasEntitlement(Entitlement::ClassroomHost->value),
            'qbankSessions' => $qbankSessions,
            'sampleQuestions' => $sampleQuestions,
        ]);
    }
}
