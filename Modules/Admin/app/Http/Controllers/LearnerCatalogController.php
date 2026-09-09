<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Enums\Permission;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Auth\Models\AdministrativeUnit;
use Modules\Auth\Models\Country;
use Modules\Auth\Models\EducationStage;
use Modules\Auth\Models\Profession;

final class LearnerCatalogController extends Controller
{
    public function index(Request $request, string $catalog): View
    {
        abort_unless($request->user()->can(Permission::UserView->value), 403);
        $config = $this->config($catalog);
        $query = $config['model']::query()->withCount($config['counts']);

        if ($catalog === 'administrative-units') {
            $query->with('country');
            if ($request->filled('country_id')) {
                $query->where('country_id', $request->integer('country_id'));
            }
        }

        if ($search = trim((string) $request->query('q', ''))) {
            $query->where(function ($nested) use ($search): void {
                $nested->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }
        if (in_array($request->query('status'), ['active', 'inactive'], true)) {
            $query->where('is_active', $request->query('status') === 'active');
        }

        $editing = null;
        $canManage = $request->user()->can(Permission::UserManage->value);
        if ($canManage && $request->filled('edit')) {
            $editing = $config['model']::query()->findOrFail($request->integer('edit'));
        }

        return view('admin::learner-data.catalogs.index', [
            'catalog' => $catalog,
            'config' => $config,
            'items' => $query->orderBy('sort_order')->orderBy('name')->paginate(20)->withQueryString(),
            'editing' => $editing,
            'countries' => Country::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'filters' => $request->only(['q', 'status', 'country_id']),
            'canManage' => $canManage,
        ]);
    }

    public function store(Request $request, string $catalog): RedirectResponse
    {
        abort_unless($request->user()->can(Permission::UserManage->value), 403);
        $config = $this->config($catalog);
        $config['model']::query()->create($this->validated($request, $catalog));

        return redirect()->route($config['route'].'.index')->with('status', 'Đã thêm '.$config['singular'].'.');
    }

    public function update(Request $request, int $item, string $catalog): RedirectResponse
    {
        abort_unless($request->user()->can(Permission::UserManage->value), 403);
        $config = $this->config($catalog);
        $model = $config['model']::query()->findOrFail($item);
        $data = $this->validated($request, $catalog, $model);

        if ($catalog === 'countries' && $model->getAttribute('code') === 'VN') {
            $data['code'] = 'VN';
        }
        $model->update($data);

        return redirect()->route($config['route'].'.index')->with('status', 'Đã cập nhật '.$config['singular'].'.');
    }

    public function toggle(Request $request, int $item, string $catalog): RedirectResponse
    {
        abort_unless($request->user()->can(Permission::UserManage->value), 403);
        $config = $this->config($catalog);
        $model = $config['model']::query()->findOrFail($item);
        $model->update(['is_active' => ! $model->getAttribute('is_active')]);

        return back()->with('status', $model->getAttribute('is_active') ? 'Đã kích hoạt.' : 'Đã ngừng hiển thị.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, string $catalog, ?Model $item = null): array
    {
        $table = $item?->getTable() ?? $this->config($catalog)['table'];
        $request->merge([
            'code' => $catalog === 'countries'
                ? strtoupper(trim((string) $request->input('code')))
                : strtolower(trim((string) $request->input('code'))),
        ]);
        $common = [
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:50', Rule::unique($table, 'code')->ignore($item?->getKey())],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['nullable', 'boolean'],
        ];

        $rules = match ($catalog) {
            'countries' => [
                ...$common,
                'name' => ['required', 'string', 'max:100'],
                'code' => ['required', 'string', 'size:2', Rule::unique($table, 'code')->ignore($item?->getKey())],
            ],
            'administrative-units' => [
                ...$common,
                'country_id' => ['required', 'integer', 'exists:countries,id'],
                'code' => [
                    'required', 'string', 'max:20',
                    Rule::unique($table, 'code')
                        ->where(fn ($query) => $query->where('country_id', $request->integer('country_id')))
                        ->ignore($item?->getKey()),
                ],
                'type' => ['required', Rule::in(['province', 'city'])],
            ],
            'professions' => [
                ...$common,
                'name' => ['required', 'string', 'max:100'],
                'requires_education_stage' => ['nullable', 'boolean'],
                'defaults_to_graduated' => ['nullable', 'boolean'],
            ],
            'education-stages' => [
                ...$common,
                'name' => ['required', 'string', 'max:80'],
                'is_graduated' => ['nullable', 'boolean'],
            ],
            default => abort(404),
        };

        $data = $request->validate($rules);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['is_active'] = $request->boolean('is_active');

        if ($catalog === 'professions') {
            $data['requires_education_stage'] = $request->boolean('requires_education_stage');
            $data['defaults_to_graduated'] = $request->boolean('defaults_to_graduated');
        }
        if ($catalog === 'education-stages') {
            $data['is_graduated'] = $request->boolean('is_graduated');
        }

        return $data;
    }

    /** @return array{model: class-string<Model>, table: string, title: string, singular: string, route: string, counts: list<string>} */
    private function config(string $catalog): array
    {
        return match ($catalog) {
            'countries' => [
                'model' => Country::class, 'table' => 'countries', 'title' => 'Quốc gia',
                'singular' => 'quốc gia', 'route' => 'admin.countries',
                'counts' => ['administrativeUnits', 'institutions', 'learnerProfiles'],
            ],
            'administrative-units' => [
                'model' => AdministrativeUnit::class, 'table' => 'administrative_units', 'title' => 'Tỉnh/Thành phố',
                'singular' => 'tỉnh/thành phố', 'route' => 'admin.administrative-units',
                'counts' => ['institutions', 'learnerProfiles'],
            ],
            'professions' => [
                'model' => Profession::class, 'table' => 'professions', 'title' => 'Chức danh',
                'singular' => 'chức danh', 'route' => 'admin.professions', 'counts' => ['learnerProfiles'],
            ],
            'education-stages' => [
                'model' => EducationStage::class, 'table' => 'education_stages', 'title' => 'Năm học',
                'singular' => 'năm học', 'route' => 'admin.education-stages', 'counts' => ['learnerProfiles'],
            ],
            default => abort(404),
        };
    }
}
