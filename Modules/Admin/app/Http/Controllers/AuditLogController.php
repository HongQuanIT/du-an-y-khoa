<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Enums\Permission;
use App\Support\Enums\Role;
use Illuminate\Database\Eloquent\Builder;
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
            $this->applyActionSearch($query, mb_substr($action, 0, 255));
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
            'actionSuggestions' => $this->actionSuggestions(),
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

    /** @param Builder<AuditLog> $query */
    private function applyActionSearch(Builder $query, string $term): void
    {
        $matchingActions = AuditLog::query()
            ->visibleToAdmin()
            ->select('action')
            ->distinct()
            ->pluck('action')
            ->filter(function (string $action) use ($term): bool {
                $log = new AuditLog;
                $log->action = $action;

                return str_contains(mb_strtolower($action), mb_strtolower($term))
                    || str_contains(mb_strtolower($log->actionLabel()), mb_strtolower($term));
            })
            ->values()
            ->all();

        $query->where(function ($builder) use ($term, $matchingActions): void {
            $builder->where('action', 'like', '%'.$term.'%');

            if ($matchingActions !== []) {
                $builder->orWhereIn('action', $matchingActions);
            }
        });
    }

    /** @return list<array{value: string, label: string}> */
    private function actionSuggestions(): array
    {
        return AuditLog::query()
            ->visibleToAdmin()
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->limit(150)
            ->pluck('action')
            ->map(function (string $action): array {
                $log = new AuditLog;
                $log->action = $action;

                return [
                    'value' => $action,
                    'label' => $log->actionLabel(),
                ];
            })
            ->values()
            ->all();
    }
}
