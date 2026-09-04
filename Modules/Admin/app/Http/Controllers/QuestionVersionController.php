<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Enums\Permission;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Modules\Admin\Actions\RestoreQuestionVersionAction;
use Modules\Admin\Support\QuestionAccess;
use Modules\QuestionBank\Models\MedicalTaxonomyNode;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionVersion;

final class QuestionVersionController extends Controller
{
    public function index(Question $question): View
    {
        abort_unless($this->actor()->can(Permission::QuestionView->value), 403);
        QuestionAccess::authorizeView($this->actor(), $question);

        $question->load('medicalTaxonomyNodes:id,name');
        $contentVersion = (int) ($question->versions()
            ->max('version') ?? $question->version);
        $versions = $question->versions()
            ->with('creator:id,name')
            ->paginate(15);
        $nodeNames = MedicalTaxonomyNode::query()
            ->whereIn(
                'id',
                $versions->getCollection()
                    ->flatMap(fn (QuestionVersion $version): array => array_map(
                        'intval',
                        (array) ($version->snapshot['medical_taxonomy_node_ids'] ?? []),
                    ))
                    ->unique()
                    ->all(),
            )
            ->pluck('name', 'id');

        return view('admin::questions.versions', [
            'question' => $question,
            'versions' => $versions,
            'nodeNames' => $nodeNames,
            'contentVersion' => $contentVersion,
            'canRestore' => $this->actor()->can(Permission::QuestionUpdate->value),
        ]);
    }

    public function restore(
        Question $question,
        QuestionVersion $version,
        RestoreQuestionVersionAction $action,
    ): RedirectResponse {
        abort_unless($this->actor()->can(Permission::QuestionUpdate->value), 403);
        QuestionAccess::authorizeView($this->actor(), $question);

        $restored = $action->handle($this->actor(), $question, $version);

        return redirect()
            ->route('admin.questions.edit', $restored)
            ->with('status', "Đã khôi phục phiên bản {$version->version} thành phiên bản {$restored->version} (bản nháp).");
    }

    private function actor(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
