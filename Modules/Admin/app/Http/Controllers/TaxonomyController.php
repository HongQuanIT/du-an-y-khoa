<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Enums\Permission;
use Illuminate\View\View;
use Modules\QuestionBank\Models\Blueprint;
use Modules\QuestionBank\Models\CoreClinicalTopic;
use Modules\QuestionBank\Models\MedicalTaxonomy;
use Modules\QuestionBank\Models\MedicalTaxonomyNode;
use Modules\QuestionBank\Models\Tag;

final class TaxonomyController extends Controller
{
    public function index(): View
    {
        $this->authorizePermission(Permission::TopicView);

        $canonical = MedicalTaxonomy::canonical();

        return view('admin::taxonomy.index', [
            'stats' => [
                'blueprints' => Blueprint::query()->count(),
                'sections' => Blueprint::query()->withCount('sections')->get()->sum('sections_count'),
                'core_topics' => CoreClinicalTopic::query()->count(),
                'medical_nodes' => $canonical
                    ? MedicalTaxonomyNode::query()->where('medical_taxonomy_id', $canonical->id)->count()
                    : 0,
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
