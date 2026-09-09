<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Enums\Permission;
use Illuminate\View\View;
use Modules\QuestionBank\Models\Blueprint;
use Modules\QuestionBank\Models\CoreClinicalTopic;
use Modules\QuestionBank\Models\Lesson;
use Modules\QuestionBank\Models\OrganSystem;
use Modules\QuestionBank\Models\Subject;
use Modules\QuestionBank\Models\Tag;

final class TaxonomyController extends Controller
{
    public function index(): View
    {
        $this->authorizePermission(Permission::TopicView);

        return view('admin::taxonomy.index', [
            'stats' => [
                'blueprints' => Blueprint::query()->count(),
                'sections' => Blueprint::query()->withCount('sections')->get()->sum('sections_count'),
                'core_topics' => CoreClinicalTopic::query()->count(),
                'organ_systems' => OrganSystem::query()->count(),
                'subjects' => Subject::query()->count(),
                'lessons' => Lesson::query()->count(),
                'tags' => Tag::query()->count(),
            ],
            'canCreate' => $this->actor()->can(Permission::TopicCreate->value),
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
