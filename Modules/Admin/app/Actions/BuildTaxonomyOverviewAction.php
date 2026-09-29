<?php

declare(strict_types=1);

namespace Modules\Admin\Actions;

use App\Models\User;
use App\Support\Auth\PortalRoute;
use App\Support\Concerns\AsAction;
use Illuminate\Database\Eloquent\Builder;
use Modules\Admin\Support\AdminRouteAccess;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Enums\TaxonomyStatus;
use Modules\QuestionBank\Models\Blueprint;
use Modules\QuestionBank\Models\BlueprintSection;
use Modules\QuestionBank\Models\CoreClinicalTopic;
use Modules\QuestionBank\Models\ExamCatalog;
use Modules\QuestionBank\Models\Lesson;
use Modules\QuestionBank\Models\OrganSystem;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\Subject;
use Modules\QuestionBank\Models\Tag;
use Modules\QuestionBank\Support\TagType;

/**
 * Trang tổng quan phân loại: học viên tìm được câu nào, và chỗ nào đang chặn.
 *
 * @phpstan-type OverviewKpi array{
 *     label: string,
 *     value: string,
 *     hint: string,
 *     icon: string,
 *     href: ?string,
 *     severity: ?string,
 * }
 * @phpstan-type OverviewFact array{label: string, value: string}
 * @phpstan-type OverviewArea array{
 *     title: string,
 *     summary: string,
 *     icon: string,
 *     icon_class: string,
 *     href: ?string,
 *     action: string,
 *     facts: list<OverviewFact>,
 * }
 */
final class BuildTaxonomyOverviewAction
{
    use AsAction;

    /**
     * @return array{kpis: list<OverviewKpi>, areas: list<OverviewArea>}
     */
    public function handle(User $user): array
    {
        $published = $this->publishedQuestions();
        $activeCatalogs = ExamCatalog::query()->where('status', TaxonomyStatus::Active);
        $activeLessons = Lesson::query()->where('status', TaxonomyStatus::Active);

        $publishedCount = (clone $published)->count();
        $missingProfession = (clone $published)->whereDoesntHave('professions')->count();
        $missingLesson = (clone $published)->whereDoesntHave('lessons')->count();
        $visibleQuestions = $publishedCount - $missingProfession;

        $activeCatalogCount = (clone $activeCatalogs)->count();
        $catalogsWithoutAudience = (clone $activeCatalogs)->whereDoesntHave('professions')->count();
        $visibleCatalogs = $activeCatalogCount - $catalogsWithoutAudience;
        $catalogsWithMatrix = (clone $activeCatalogs)->whereNotNull('blueprint_id')->count();
        $visibleEmptyCatalogs = (clone $activeCatalogs)
            ->whereHas('professions')
            ->whereDoesntHave('questions', fn (Builder $questions) => $questions->where('questions.status', QuestionStatus::Published))
            ->count();
        $catalogsWithPublishedQuestions = (clone $activeCatalogs)
            ->whereHas('questions', fn (Builder $questions) => $questions->where('questions.status', QuestionStatus::Published))
            ->count();

        $activeLessonCount = (clone $activeLessons)->count();
        $lessonsWithQuestions = (clone $activeLessons)
            ->whereHas('questions', fn (Builder $questions) => $questions->where('questions.status', QuestionStatus::Published))
            ->count();
        $orphanedLessons = (clone $activeLessons)
            ->whereHas('questions', fn (Builder $questions) => $questions->where('questions.status', QuestionStatus::Published))
            ->whereDoesntHave('subjects')
            ->whereDoesntHave('organSystems')
            ->count();
        $organSystems = OrganSystem::query()->where('status', TaxonomyStatus::Active)->count();
        $subjects = Subject::query()->where('status', TaxonomyStatus::Active)->count();

        $usedBlueprintIds = (clone $activeCatalogs)
            ->whereNotNull('blueprint_id')
            ->pluck('blueprint_id');
        $topicScope = CoreClinicalTopic::query()->when(
            $usedBlueprintIds->isNotEmpty(),
            fn (Builder $query) => $query->whereHas(
                'section',
                fn (Builder $sections) => $sections->whereIn('blueprint_id', $usedBlueprintIds),
            ),
        );
        $topicTotal = (clone $topicScope)->count();
        $topicMapped = (clone $topicScope)->whereHas('lessons')->count();
        $activeBlueprints = Blueprint::query()->where('status', TaxonomyStatus::Active)->count();
        $sections = BlueprintSection::query()
            ->whereHas('blueprint', fn (Builder $blueprints) => $blueprints->where('status', TaxonomyStatus::Active))
            ->count();

        $tags = $this->tagCoverage();

        $canQuestions = AdminRouteAccess::allows($user, 'admin.questions.index');
        $canExams = AdminRouteAccess::allows($user, 'admin.exam-catalogs.index');
        $canCurriculum = AdminRouteAccess::allows($user, 'admin.curriculum.index');
        $canTags = AdminRouteAccess::allows($user, 'admin.tags.index');
        $canMatrix = AdminRouteAccess::allows($user, 'admin.blueprints.index');

        $questionHref = $canQuestions
            ? route(PortalRoute::content('questions.index'), ['status' => QuestionStatus::Published->value])
            : null;

        $kpis = [];
        $areas = [];

        $kpis[] = [
            'label' => 'Câu xuất bản học viên thấy',
            'value' => $this->count($visibleQuestions),
            'hint' => $this->questionHint($publishedCount, $missingProfession, $missingLesson),
            'icon' => 'visibility',
            'href' => $questionHref,
            'severity' => $missingProfession > 0 ? 'critical' : ($missingLesson > 0 ? 'warning' : null),
        ];

        if ($canExams) {
            $examHref = route(PortalRoute::content('exam-catalogs.index'));
            $kpis[] = [
                'label' => 'Kỳ thi học viên chọn được',
                'value' => $this->count($visibleCatalogs),
                'hint' => $this->catalogHint($activeCatalogCount, $catalogsWithoutAudience, $visibleEmptyCatalogs, $catalogsWithMatrix),
                'icon' => 'quiz',
                'href' => $examHref,
                'severity' => $catalogsWithoutAudience > 0 ? 'critical' : ($visibleEmptyCatalogs > 0 ? 'warning' : null),
            ];

            $areas[] = [
                'title' => 'Kỳ thi',
                'summary' => 'Học viên chọn kỳ thi theo chức danh. Kỳ thi gắn ma trận thì lập được đề.',
                'icon' => 'quiz',
                'icon_class' => 'bg-primary-container text-on-primary-container',
                'href' => $examHref,
                'action' => 'Mở kỳ thi',
                'facts' => [
                    ['label' => 'Đang dùng', 'value' => $this->count($activeCatalogCount)],
                    ['label' => 'Học viên thấy', 'value' => $this->count($visibleCatalogs)],
                    ['label' => 'Có câu xuất bản', 'value' => $this->count($catalogsWithPublishedQuestions)],
                    ['label' => 'Gắn ma trận', 'value' => $this->count($catalogsWithMatrix)],
                ],
            ];
        }

        if ($canCurriculum) {
            $curriculumHref = route(PortalRoute::content('curriculum.index'));
            $kpis[] = [
                'label' => 'Bài học có câu xuất bản',
                'value' => $this->count($lessonsWithQuestions),
                'hint' => $activeLessonCount === 0
                    ? 'Chưa có bài học đang dùng'
                    : 'trên '.$this->count($activeLessonCount).' bài đang dùng · '.$this->count($organSystems).' hệ · '.$this->count($subjects).' môn',
                'icon' => 'account_tree',
                'href' => $curriculumHref,
                'severity' => $orphanedLessons > 0 ? 'warning' : null,
            ];

            $areas[] = [
                'title' => 'Danh mục kiến thức',
                'summary' => 'Câu gắn vào bài học. Học viên đi từ hệ cơ quan hoặc môn học xuống bài đó.',
                'icon' => 'account_tree',
                'icon_class' => 'bg-secondary-container text-on-secondary-container',
                'href' => $curriculumHref,
                'action' => 'Mở danh mục',
                'facts' => [
                    ['label' => 'Hệ cơ quan', 'value' => $this->count($organSystems)],
                    ['label' => 'Môn học', 'value' => $this->count($subjects)],
                    ['label' => 'Bài đang dùng', 'value' => $this->count($activeLessonCount)],
                    ['label' => 'Bài có câu', 'value' => $this->count($lessonsWithQuestions)],
                ],
            ];
        }

        if ($canTags) {
            $tagHref = route(PortalRoute::content('tags.index'));
            $kpis[] = [
                'label' => 'Thẻ đang gắn câu',
                'value' => $this->count($tags['used']),
                'hint' => $tags['active'] === 0
                    ? 'Chưa có thẻ đang dùng'
                    : $this->count($tags['active'] - $tags['used']).' thẻ chưa gắn câu trên '.$this->count($tags['active']).' thẻ',
                'icon' => 'sell',
                'href' => $tagHref,
                'severity' => null,
            ];

            $facts = [
                ['label' => 'Đang dùng', 'value' => $this->count($tags['active'])],
                ['label' => 'Đã gắn câu', 'value' => $this->count($tags['used'])],
            ];
            foreach ($tags['groups'] as $group) {
                $facts[] = [
                    'label' => $group['label'],
                    'value' => $this->count($group['used']).' / '.$this->count($group['total']),
                ];
            }

            $areas[] = [
                'title' => 'Thẻ',
                'summary' => 'Lọc thêm theo triệu chứng, khái niệm, cận lâm sàng và trọng tâm.',
                'icon' => 'sell',
                'icon_class' => 'bg-tertiary-container text-on-tertiary-container',
                'href' => $tagHref,
                'action' => 'Mở thẻ',
                'facts' => $facts,
            ];
        }

        if ($canMatrix) {
            $matrixHref = route(PortalRoute::content('blueprints.index'));

            $areas[] = [
                'title' => 'Ma trận đề thi',
                'summary' => 'Lập hạn mức câu theo chủ đề khi kỳ thi đã gắn ma trận.',
                'icon' => 'assignment',
                'icon_class' => 'bg-surface-container-high text-on-surface',
                'href' => $matrixHref,
                'action' => 'Mở ma trận',
                'facts' => [
                    ['label' => 'Ma trận đang dùng', 'value' => $this->count($activeBlueprints)],
                    ['label' => 'Phần', 'value' => $this->count($sections)],
                    [
                        'label' => $usedBlueprintIds->isNotEmpty() ? 'Chủ đề của kỳ thi đã gắn bài' : 'Chủ đề đã gắn bài',
                        'value' => $this->count($topicMapped).' / '.$this->count($topicTotal),
                    ],
                ],
            ];
        }

        return [
            'kpis' => $kpis,
            'areas' => $areas,
        ];
    }

    /** @return Builder<Question> */
    private function publishedQuestions(): Builder
    {
        return Question::query()->where('status', QuestionStatus::Published);
    }

    /**
     * @return array{active: int, used: int, groups: list<array{label: string, total: int, used: int}>}
     */
    private function tagCoverage(): array
    {
        $rows = Tag::query()
            ->where('status', TaxonomyStatus::Active)
            ->select('type')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN EXISTS (SELECT 1 FROM question_tags WHERE question_tags.tag_id = tags.id) THEN 1 ELSE 0 END) as used_count')
            ->groupBy('type')
            ->get();

        $byType = [];
        foreach ($rows as $row) {
            $type = is_string($row->type) && $row->type !== '' ? $row->type : 'other';
            $byType[$type]['total'] = ($byType[$type]['total'] ?? 0) + (int) $row->total;
            $byType[$type]['used'] = ($byType[$type]['used'] ?? 0) + (int) $row->used_count;
        }

        $groups = [];
        $seen = [];
        foreach (TagType::GROUPS as $group) {
            $total = 0;
            $used = 0;
            foreach ($group['types'] as $type) {
                $seen[$type] = true;
                $total += $byType[$type]['total'] ?? 0;
                $used += $byType[$type]['used'] ?? 0;
            }
            if ($total === 0) {
                continue;
            }
            $groups[] = ['label' => $group['label'], 'total' => $total, 'used' => $used];
        }

        $extraTotal = 0;
        $extraUsed = 0;
        foreach ($byType as $type => $counts) {
            if (isset($seen[$type])) {
                continue;
            }
            $extraTotal += $counts['total'];
            $extraUsed += $counts['used'];
        }
        if ($extraTotal > 0) {
            $groups[] = ['label' => 'Chưa xếp nhóm', 'total' => $extraTotal, 'used' => $extraUsed];
        }

        return [
            'active' => array_sum(array_column($byType, 'total')),
            'used' => array_sum(array_column($byType, 'used')),
            'groups' => $groups,
        ];
    }

    private function questionHint(int $published, int $missingProfession, int $missingLesson): string
    {
        if ($published === 0) {
            return 'Chưa có câu xuất bản';
        }

        $parts = [];
        if ($missingProfession > 0) {
            $parts[] = $this->count($missingProfession).' câu chưa gắn chức danh trên '.$this->count($published).' câu xuất bản';
        } else {
            $parts[] = 'Mọi câu xuất bản đã gắn chức danh';
        }
        if ($missingLesson > 0) {
            $parts[] = $this->count($missingLesson).' câu chưa gắn bài học';
        }

        return implode(' · ', $parts);
    }

    private function catalogHint(int $active, int $withoutAudience, int $visibleEmpty, int $withMatrix): string
    {
        if ($active === 0) {
            return 'Chưa có kỳ thi đang dùng';
        }
        if ($withoutAudience > 0) {
            return $this->count($withoutAudience).' kỳ thi đang dùng chưa gắn đối tượng';
        }
        if ($visibleEmpty > 0) {
            return $this->count($visibleEmpty).' kỳ thi đang hiện nhưng chưa có câu xuất bản';
        }

        return $this->count($withMatrix).' kỳ thi gắn ma trận để lập đề';
    }

    private function count(int $value): string
    {
        return number_format($value);
    }
}
