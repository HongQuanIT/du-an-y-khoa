<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Enums\Permission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\QuestionBank\Enums\TaxonomyStatus;
use Modules\QuestionBank\Models\MedicalTaxonomy;
use Modules\QuestionBank\Models\MedicalTaxonomyNode;
use Modules\QuestionBank\Support\MedicalTaxonomyNodeTypes;

final class MedicalTaxonomyController extends Controller
{
    /** @var array<string, string> */
    public const STATUS_LABELS = [
        'active' => 'Đang dùng',
        'inactive' => 'Ngừng dùng',
    ];

    /** Loại thuộc cây phân cấp chính (Hệ → Chuyên khoa → Bệnh). */
    private const STRUCTURE_TYPES = ['system', 'specialty', 'disease', 'condition'];

    public function index(Request $request): View|RedirectResponse
    {
        $this->authorizePermission(Permission::TopicView);

        if ($request->has('taxonomy_id')) {
            return redirect()->route('admin.medical-taxonomy.index', array_filter([
                'q' => $request->query('q'),
                'node_type' => $request->query('node_type'),
                'focus' => $request->query('focus'),
            ]));
        }

        $taxonomy = MedicalTaxonomy::canonical();
        $search = trim((string) $request->query('q', ''));
        $nodeType = trim((string) $request->query('node_type', ''));
        $focusId = $request->filled('focus') ? (int) $request->query('focus') : null;

        $tree = [];
        $structureTree = [];
        $linkedTree = [];
        $flatNodes = collect();
        $typeStats = [];
        $parentOptions = collect();
        $defaultOpenIds = [];

        if ($taxonomy !== null) {
            $flatNodes = MedicalTaxonomyNode::query()
                ->where('medical_taxonomy_id', $taxonomy->id)
                ->with(['parent:id,name'])
                ->withCount(['children', 'coreClinicalTopics', 'questions'])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();

            $typeStats = $flatNodes
                ->groupBy(fn (MedicalTaxonomyNode $node): string => (string) ($node->node_type ?: 'other'))
                ->map->count()
                ->all();

            $filtered = $flatNodes;
            if ($search !== '') {
                $filtered = $filtered->filter(
                    fn (MedicalTaxonomyNode $node): bool => str_contains(
                        mb_strtolower($node->name.' '.$node->slug),
                        mb_strtolower($search),
                    ),
                );
            }
            if ($nodeType !== '') {
                $filtered = $filtered->filter(
                    fn (MedicalTaxonomyNode $node): bool => (string) $node->node_type === $nodeType,
                );
            }

            $tree = ($search !== '' || $nodeType !== '')
                ? $this->buildSearchRows($filtered, $flatNodes)
                : [];

            if ($search === '' && $nodeType === '') {
                [$structureTree, $linkedTree] = $this->buildSectionedTrees($flatNodes);
            }

            $defaultOpenIds = ($search !== '' || $nodeType !== '')
                ? []
                : $this->defaultOpenIds($structureTree, maxDepth: 2);

            if ($focusId !== null) {
                $defaultOpenIds = array_values(array_unique([
                    ...$defaultOpenIds,
                    ...$this->ancestorIds($focusId, $flatNodes),
                ]));
            }

            $parentOptions = $flatNodes
                ->sortBy('name')
                ->values()
                ->map(fn (MedicalTaxonomyNode $node): array => [
                    'id' => $node->id,
                    'label' => $this->nodePathLabel($node, $flatNodes),
                ]);
        }

        return view('admin::medical-taxonomy.index', [
            'taxonomy' => $taxonomy,
            'tree' => $tree,
            'structureTree' => $structureTree,
            'linkedTree' => $linkedTree,
            'flatNodes' => $flatNodes,
            'parentOptions' => $parentOptions,
            'typeStats' => $typeStats,
            'groupedTypeStats' => MedicalTaxonomyNodeTypes::groupedStats($typeStats),
            'typeGroups' => MedicalTaxonomyNodeTypes::GROUPS,
            'defaultOpenIds' => $defaultOpenIds,
            'nodeTypeLabels' => MedicalTaxonomyNodeTypes::LABELS,
            'statusLabels' => self::STATUS_LABELS,
            'filters' => [
                'q' => $search,
                'node_type' => $nodeType,
                'focus' => $focusId,
            ],
            'isFiltered' => $search !== '' || $nodeType !== '',
            'statuses' => TaxonomyStatus::cases(),
            'canCreate' => $this->actor()->can(Permission::TopicCreate->value),
            'canUpdate' => $this->actor()->can(Permission::TopicUpdate->value),
        ]);
    }

    public function storeNode(Request $request): RedirectResponse
    {
        $this->authorizePermission(Permission::TopicCreate);

        $canonical = MedicalTaxonomy::canonical();
        abort_if($canonical === null, 404, 'Chưa có danh mục y khoa. Vui lòng chạy seeder.');

        $data = $request->validate([
            'medical_taxonomy_id' => ['required', 'integer', Rule::in([$canonical->id])],
            'parent_id' => ['nullable', 'integer', 'exists:medical_taxonomy_nodes,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:191'],
            'node_type' => ['nullable', 'string', 'max:50'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $slug = trim((string) ($data['slug'] ?? ''));
        if ($slug === '') {
            $slug = Str::slug($data['name']);
        }

        $slug = $this->uniqueNodeSlug($canonical->id, $slug);

        $node = MedicalTaxonomyNode::query()->create([
            'medical_taxonomy_id' => $canonical->id,
            'parent_id' => isset($data['parent_id']) && $data['parent_id'] !== ''
                ? (int) $data['parent_id']
                : null,
            'name' => $data['name'],
            'slug' => $slug,
            'node_type' => filled($data['node_type'] ?? null) ? (string) $data['node_type'] : null,
            'description' => $data['description'] ?? null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'status' => TaxonomyStatus::Active,
        ]);

        return redirect()
            ->route('admin.medical-taxonomy.index', ['focus' => $node->id])
            ->with('status', 'Đã thêm mục «'.$node->name.'».');
    }

    public function updateNode(Request $request, MedicalTaxonomyNode $node): RedirectResponse
    {
        $this->authorizePermission(Permission::TopicUpdate);

        $canonical = MedicalTaxonomy::canonical();
        abort_if($canonical === null || $node->medical_taxonomy_id !== $canonical->id, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:191'],
            'node_type' => ['nullable', 'string', 'max:50'],
            'parent_id' => [
                'nullable',
                'integer',
                'exists:medical_taxonomy_nodes,id',
                'not_in:'.$node->id,
            ],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', Rule::in(TaxonomyStatus::values())],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $newParentId = array_key_exists('parent_id', $data) && $data['parent_id'] !== null && $data['parent_id'] !== ''
            ? (int) $data['parent_id']
            : null;

        if ($newParentId !== null && $this->wouldCreateCycle($node, $newParentId)) {
            return back()->withErrors([
                'parent_id' => 'Không thể chọn mục con làm mục cha (tạo vòng lặp).',
            ])->withInput();
        }

        $slug = trim((string) ($data['slug'] ?? ''));
        if ($slug === '') {
            $slug = Str::slug($data['name']);
        }

        if ($slug !== $node->slug) {
            $slug = $this->uniqueNodeSlug($node->medical_taxonomy_id, $slug, $node->id);
        }

        $node->update([
            'name' => $data['name'],
            'slug' => $slug,
            'node_type' => filled($data['node_type'] ?? null) ? (string) $data['node_type'] : null,
            'parent_id' => $newParentId,
            'description' => $data['description'] ?? null,
            'sort_order' => (int) ($data['sort_order'] ?? $node->sort_order),
            'status' => $data['status'],
        ]);

        return redirect()
            ->route('admin.medical-taxonomy.index', ['focus' => $node->id])
            ->with('status', 'Đã cập nhật «'.$node->name.'».');
    }

    /**
     * @param  Collection<int, MedicalTaxonomyNode>  $nodes
     * @return list<array{node: MedicalTaxonomyNode, children: list<mixed>, depth: int}>
     */
    private function buildTree(Collection $nodes): array
    {
        /** @var Collection<int|string, Collection<int, MedicalTaxonomyNode>> $byParent */
        $byParent = $nodes->groupBy(fn (MedicalTaxonomyNode $node) => $node->parent_id ?? 0);

        $walk = function ($parentKey, int $depth) use (&$walk, $byParent): array {
            return ($byParent->get($parentKey) ?? collect())
                ->values()
                ->map(fn (MedicalTaxonomyNode $node): array => [
                    'node' => $node,
                    'depth' => $depth,
                    'children' => $walk($node->id, $depth + 1),
                ])
                ->all();
        };

        return $walk(0, 0);
    }

    /**
     * @param  Collection<int, MedicalTaxonomyNode>  $nodes
     * @return array{0: list<array>, 1: list<array>}
     */
    private function buildSectionedTrees(Collection $nodes): array
    {
        $fullTree = $this->buildTree($nodes);
        $structureTree = [];
        $linkedTree = [];

        foreach ($fullTree as $item) {
            $type = (string) ($item['node']->node_type ?? '');

            if (in_array($type, self::STRUCTURE_TYPES, true) || $type === '') {
                $structureTree[] = $item;
            } else {
                $linkedTree[] = $item;
            }
        }

        return [$structureTree, $linkedTree];
    }

    /**
     * @param  list<array{node: MedicalTaxonomyNode, children: list<mixed>, depth: int}>  $tree
     * @return list<int>
     */
    private function defaultOpenIds(array $tree, int $maxDepth = 1): array
    {
        $ids = [];

        $walk = function (array $items) use (&$walk, &$ids, $maxDepth): void {
            foreach ($items as $item) {
                if (($item['depth'] ?? 0) <= $maxDepth && ($item['children'] ?? []) !== []) {
                    $ids[] = (int) $item['node']->id;
                    $walk($item['children']);
                }
            }
        };

        $walk($tree);

        return $ids;
    }

    /**
     * @param  Collection<int, MedicalTaxonomyNode>  $all
     * @return list<int>
     */
    private function ancestorIds(int $nodeId, Collection $all): array
    {
        $ids = [];
        $current = $all->firstWhere('id', $nodeId);
        $guard = 0;

        while ($current?->parent_id && $guard < 30) {
            $ids[] = (int) $current->parent_id;
            $current = $all->firstWhere('id', $current->parent_id);
            $guard++;
        }

        return $ids;
    }

    /**
     * @param  Collection<int, MedicalTaxonomyNode>  $matched
     * @param  Collection<int, MedicalTaxonomyNode>  $all
     * @return list<array{node: MedicalTaxonomyNode, children: list<mixed>, depth: int, path: string}>
     */
    private function buildSearchRows(Collection $matched, Collection $all): array
    {
        return $matched
            ->sortBy([
                ['sort_order', 'asc'],
                ['name', 'asc'],
            ])
            ->values()
            ->map(fn (MedicalTaxonomyNode $node): array => [
                'node' => $node,
                'depth' => 0,
                'children' => [],
                'path' => $this->nodePathLabel($node, $all),
            ])
            ->all();
    }

    /**
     * @param  Collection<int, MedicalTaxonomyNode>  $all
     */
    private function nodePathLabel(MedicalTaxonomyNode $node, Collection $all): string
    {
        $parts = [];
        $current = $node;
        $guard = 0;

        while ($current !== null && $guard < 20) {
            array_unshift($parts, $current->name);
            $parentId = $current->parent_id;
            $current = $parentId ? $all->firstWhere('id', $parentId) : null;
            $guard++;
        }

        return implode(' › ', $parts);
    }

    private function wouldCreateCycle(MedicalTaxonomyNode $node, int $newParentId): bool
    {
        $currentId = $newParentId;
        $guard = 0;

        while ($currentId > 0 && $guard < 50) {
            if ($currentId === $node->id) {
                return true;
            }

            $currentId = (int) (MedicalTaxonomyNode::query()->whereKey($currentId)->value('parent_id') ?? 0);
            $guard++;
        }

        return false;
    }

    private function uniqueNodeSlug(int $taxonomyId, string $slug, ?int $ignoreId = null): string
    {
        $base = Str::slug($slug !== '' ? $slug : 'node');
        if ($base === '') {
            $base = 'node';
        }

        $candidate = $base;
        $suffix = 1;

        while (MedicalTaxonomyNode::query()
            ->where('medical_taxonomy_id', $taxonomyId)
            ->where('slug', $candidate)
            ->when($ignoreId !== null, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
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
