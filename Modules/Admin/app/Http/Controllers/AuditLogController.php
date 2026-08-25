<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Enums\Permission;
use App\Support\Enums\Role;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Admin\Models\AuditLog;
use Modules\QuestionBank\Models\Question;

final class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizePermission(Permission::AuditView);

        $query = AuditLog::query()
            ->visibleToAdmin()
            ->with('actor:id,name')
            ->latest('id');

        if ($action = trim((string) $request->query('action', ''))) {
            $query->where('action', 'like', "{$action}%");
        }

        $actor = trim((string) $request->query('actor', $request->query('actor_id', '')));
        if ($actor !== '') {
            if (ctype_digit($actor) && (int) $actor > 0) {
                $query->where('actor_id', (int) $actor);
            } else {
                $actorName = mb_substr($actor, 0, 100);
                $query->whereHas('actor', function ($users) use ($actorName): void {
                    $users->where('name', 'like', '%'.$actorName.'%');
                });
            }
        }

        if ($relatedUserId = $request->integer('related_user_id')) {
            $userMorphClass = (new User)->getMorphClass();
            $query->where(function ($related) use ($relatedUserId, $userMorphClass): void {
                $related->where('actor_id', $relatedUserId)
                    ->orWhere(function ($subject) use ($relatedUserId, $userMorphClass): void {
                        $subject->where('auditable_type', $userMorphClass)
                            ->where('auditable_id', (string) $relatedUserId);
                    });
            });
        }

        if ($actorRole = Role::tryFrom((string) $request->query('actor_role', ''))) {
            $query->where('actor_role', $actorRole->value);
        }

        $subjectTypes = [
            'user' => (new User)->getMorphClass(),
            'question' => (new Question)->getMorphClass(),
        ];

        $subjectType = (string) $request->query('subject_type', '');
        if (isset($subjectTypes[$subjectType])) {
            $query->where('auditable_type', $subjectTypes[$subjectType]);
        }

        if ($subjectId = trim((string) $request->query('subject_id', ''))) {
            $query->where('auditable_id', mb_substr($subjectId, 0, 36));
        }

        if ($ip = trim((string) $request->query('ip', ''))) {
            $query->where('ip', mb_substr($ip, 0, 45));
        }

        $logs = $query->cursorPaginate(30)->withQueryString();

        return view('admin::audit.index', [
            'logs' => $logs,
            'filters' => [
                'action' => $request->query('action'),
                'actor' => $actor,
                'actor_role' => $request->query('actor_role'),
                'ip' => $request->query('ip'),
            ],
            'roles' => Role::cases(),
            'userMorphClass' => $subjectTypes['user'],
            'questionMorphClass' => $subjectTypes['question'],
        ]);
    }

    public function show(AuditLog $audit): View
    {
        $this->authorizePermission(Permission::AuditView);

        $audit->load(['actor', 'auditable']);

        return view('admin::audit.show', [
            'log' => $audit,
        ]);
    }

    private function authorizePermission(Permission $permission): void
    {
        abort_unless($this->actor()->can($permission->value), 403);
    }

    private function actor(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
