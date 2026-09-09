<x-layouts.admin :title="$institution->exists ? 'Chỉnh sửa trường' : 'Thêm trường'">
    <x-admin.page-header :title="$institution->exists ? 'Chỉnh sửa trường' : 'Thêm trường/cơ sở đào tạo'" description="Học viên chỉ có thể chọn những trường đang hiển thị.">
        <x-slot:actions><a href="{{ route('admin.institutions.index') }}" class="rounded-lg px-3 py-2 text-on-surface-variant hover:bg-surface-container-low">← Danh sách</a></x-slot:actions>
    </x-admin.page-header>
    @include('admin::learner-data._tabs')
    <x-admin.flash />
    <x-auth.errors />

    <form method="post" action="{{ $institution->exists ? route('admin.institutions.update', $institution) : route('admin.institutions.store') }}"
        class="max-w-3xl space-y-5 rounded-xl border border-outline-variant bg-surface p-5 md:p-6"
        x-data="{country: @js((string) old('country_id', $institution->country_id ?? $countries->firstWhere('code', 'VN')?->id)), units: @js($units->map(fn ($unit) => ['id' => (string) $unit->id, 'country_id' => (string) $unit->country_id, 'name' => $unit->name]))}">
        @csrf
        @if ($institution->exists) @method('PUT') @endif
        <div class="grid gap-5 md:grid-cols-2">
            <div><label class="mb-1.5 block text-label-sm text-on-surface-variant">Quốc gia</label><select name="country_id" x-model="country" required class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3">@foreach($countries as $country)<option value="{{ $country->id }}">{{ $country->name }}</option>@endforeach</select></div>
            <div><label class="mb-1.5 block text-label-sm text-on-surface-variant">Tỉnh/Thành phố</label><select name="administrative_unit_id" required class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3"><option value="">Chọn địa phương</option><template x-for="unit in units.filter(item => item.country_id === country)" :key="unit.id"><option :value="unit.id" x-text="unit.name" :selected="unit.id === @js((string) old('administrative_unit_id', $institution->administrative_unit_id))"></option></template></select></div>
        </div>
        <div><label class="mb-1.5 block text-label-sm text-on-surface-variant">Tên đầy đủ</label><input name="name" value="{{ old('name', $institution->name) }}" required maxlength="180" class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3"></div>
        <div class="grid gap-5 md:grid-cols-2">
            <div><label class="mb-1.5 block text-label-sm text-on-surface-variant">Tên viết tắt</label><input name="short_name" value="{{ old('short_name', $institution->short_name) }}" maxlength="80" class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3"></div>
            <div><label class="mb-1.5 block text-label-sm text-on-surface-variant">Loại cơ sở</label><select name="type" class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3">@foreach(['university' => 'Đại học', 'college' => 'Cao đẳng', 'hospital' => 'Bệnh viện', 'training_center' => 'Trung tâm đào tạo', 'other' => 'Khác'] as $value => $label)<option value="{{ $value }}" @selected(old('type', $institution->type ?: 'university') === $value)>{{ $label }}</option>@endforeach</select></div>
        </div>
        <div><label class="mb-1.5 block text-label-sm text-on-surface-variant">Tên khác/từ khóa tìm kiếm</label><textarea name="search_aliases" rows="3" maxlength="1000" class="w-full rounded-lg border border-outline-variant bg-surface-container-low px-3 py-2" placeholder="Phân cách bằng dấu phẩy">{{ old('search_aliases', $institution->search_aliases) }}</textarea></div>
        <div class="flex flex-wrap items-center justify-between gap-4">
            <label class="flex items-center gap-2 text-body-sm"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $institution->exists ? $institution->is_active : true)) class="size-4 rounded text-primary">Đang hiển thị cho học viên</label>
            <button class="rounded-lg bg-primary px-5 py-2.5 font-label-md font-semibold text-on-primary">Lưu trường</button>
        </div>
    </form>

    @if ($institution->exists)
        <form method="post" action="{{ route('admin.institutions.toggle', $institution) }}" class="mt-4 max-w-3xl text-right">@csrf @method('PATCH')<button class="text-label-sm font-semibold text-on-surface-variant hover:text-primary hover:underline">{{ $institution->is_active ? 'Ngừng hiển thị' : 'Kích hoạt lại' }}</button></form>
    @endif
</x-layouts.admin>
