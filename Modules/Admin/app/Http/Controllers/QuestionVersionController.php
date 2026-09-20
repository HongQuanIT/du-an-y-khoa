<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Modules\Admin\Actions\RestoreQuestionVersionAction;
use Modules\Admin\Support\QuestionAccess;
use Modules\QuestionBank\Models\Lesson;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionVersion;
use Modules\QuestionBank\Support\QuestionReviewTimeline;

final class QuestionVersionController extends Controller
{
    public function index(Question $question): View
    {
        QuestionAccess::authorizeWorkspace($this->actor());
        QuestionAccess::authorizeView($this->actor(), $question);

        $question->load('lessons:id,name');
        $contentVersion = (int) ($question->versions()
            ->max('version') ?? $question->version);
        $versions = $question->versions()
            ->with('creator:id,name')
            ->paginate(15);
        $lessonNames = Lesson::query()
            ->whereIn(
                'id',
                $versions->getCollection()
                    ->flatMap(fn (QuestionVersion $version): array => array_map(
                        'intval',
                        // Accept both the new key and the legacy key for old snapshots.
                        (array) ($version->snapshot['lesson_ids']
                            ?? $version->snapshot['medical_taxonomy_node_ids']
                            ?? []),
                    ))
                    ->unique()
                    ->all(),
            )
            ->pluck('name', 'id');

        $timeline = app(QuestionReviewTimeline::class)->build($question);
        $versionPipelineSummaries = collect($timeline['segments'] ?? [])
            ->where('kind', 'published')
            ->mapWithKeys(fn (array $segment): array => [
                (int) $segment['version'] => [
                    'summary' => (string) ($segment['summary'] ?? ''),
                    'cycle_count' => (int) ($segment['cycle_count'] ?? 0),
                    'reject_count' => (int) ($segment['reject_count'] ?? 0),
                    'cycle_numbers' => array_map(
                        fn (array $cycle): int => (int) $cycle['cycle'],
                        $segment['cycles'] ?? [],
                    ),
                ],
            ])
            ->all();

        return view('admin::questions.versions', [
            'question' => $question,
            'versions' => $versions,
            'lessonNames' => $lessonNames,
            'contentVersion' => $contentVersion,
            'canRestore' => $this->actor()->canAny(['question_version.restore']),
            'versionPipelineSummaries' => $versionPipelineSummaries,
        ]);
    }

    public function restore(
        Question $question,
        QuestionVersion $version,
        RestoreQuestionVersionAction $action,
    ): RedirectResponse {
        abort_unless($this->actor()->canAny(['question_version.restore']), 403);
        QuestionAccess::authorizeView($this->actor(), $question);

        $restored = $action->handle($this->actor(), $question, $version);

        return redirect()
            ->route('admin.questions.edit', $restored)
            ->with(
                'status',
                "Đã áp dụng phiên bản {$version->version} vào bản làm việc (nháp). Số phiên bản không đổi — chỉ tăng khi Admin xuất bản.",
            );
    }

    private function actor(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
