<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Enums\Permission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Auth\Models\AdministrativeUnit;
use Modules\Auth\Models\Country;
use Modules\Auth\Models\Institution;

final class InstitutionController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can(Permission::UserView->value), 403);

        $query = Institution::query()->with(['country', 'administrativeUnit'])->withCount('learnerProfiles');
        if ($search = trim((string) $request->query('q', ''))) {
            $query->where(function ($nested) use ($search): void {
                $nested->where('name', 'like', "%{$search}%")
                    ->orWhere('short_name', 'like', "%{$search}%");
            });
        }
        if ($request->filled('administrative_unit_id')) {
            $query->where('administrative_unit_id', $request->integer('administrative_unit_id'));
        }
        if ($request->filled('country_id')) {
            $query->where('country_id', $request->integer('country_id'));
        }
        if ($request->filled('status')) {
            $query->where('is_active', $request->string('status')->toString() === 'active');
        }

        $canManage = $request->user()->can(Permission::UserManage->value);
        $editing = $canManage && $request->filled('edit')
            ? Institution::query()->findOrFail($request->integer('edit'))
            : null;

        return view('admin::learner-data.institutions.index', [
            'institutions' => $query->orderBy('name')->paginate(20)->withQueryString(),
            'countries' => Country::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'units' => AdministrativeUnit::query()->where('is_active', true)->orderBy('name')->get(),
            'filters' => $request->only(['q', 'country_id', 'administrative_unit_id', 'status']),
            'canManage' => $canManage,
            'editing' => $editing,
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can(Permission::UserManage->value), 403);

        $institution = new Institution;

        return $this->form($institution);
    }

    public function edit(Request $request, Institution $institution): View
    {
        abort_unless($request->user()->can(Permission::UserManage->value), 403);

        return $this->form($institution);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can(Permission::UserManage->value), 403);
        $data = $this->validated($request);
        Institution::query()->create($data);

        return redirect()->route('admin.institutions.index')->with('status', 'Đã thêm trường học.');
    }

    public function update(Request $request, Institution $institution): RedirectResponse
    {
        abort_unless($request->user()->can(Permission::UserManage->value), 403);
        $institution->update($this->validated($request, $institution));

        return back()->with('status', 'Đã cập nhật trường học.');
    }

    public function toggle(Request $request, Institution $institution): RedirectResponse
    {
        abort_unless($request->user()->can(Permission::UserManage->value), 403);
        $institution->update(['is_active' => ! $institution->is_active]);

        return back()->with('status', $institution->is_active ? 'Đã kích hoạt trường.' : 'Đã ngừng hiển thị trường.');
    }

    private function form(Institution $institution): View
    {
        return view('admin::learner-data.institutions.form', [
            'institution' => $institution,
            'countries' => Country::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'units' => AdministrativeUnit::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    private function validated(Request $request, ?Institution $institution = null): array
    {
        $data = $request->validate([
            'country_id' => ['required', 'integer', 'exists:countries,id'],
            'administrative_unit_id' => ['required', 'integer', 'exists:administrative_units,id'],
            'name' => ['required', 'string', 'max:180', Rule::unique('institutions')->where('country_id', $request->integer('country_id'))->ignore($institution)],
            'short_name' => ['nullable', 'string', 'max:80'],
            'search_aliases' => ['nullable', 'string', 'max:1000'],
            'type' => ['required', 'string', Rule::in(['university', 'college', 'hospital', 'training_center', 'other'])],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $unitMatches = AdministrativeUnit::query()
            ->whereKey($data['administrative_unit_id'])->where('country_id', $data['country_id'])->exists();
        abort_unless($unitMatches, 422, 'Tỉnh/thành phố không thuộc quốc gia đã chọn.');

        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] ??= 0;

        return $data;
    }
}
