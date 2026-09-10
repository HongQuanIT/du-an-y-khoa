@php
    $formRoute = $editing
        ? route($config['route'].'.update', ['item' => $editing->id])
        : route($config['route'].'.store');
@endphp

<form method="post" action="{{ $formRoute }}" class="space-y-4">
    @csrf
    @if ($editing) @method('PUT') @endif

    @if ($catalog === 'administrative-units')
        <div>
            <label for="catalog_country_id" class="mb-1 block text-label-sm text-on-surface-variant">Quốc gia</label>
            <select id="catalog_country_id" name="country_id" required class="h-10 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3">
                <option value="">Chọn quốc gia</option>
                @foreach ($countries as $country)
                    <option value="{{ $country->id }}" @selected((string) old('country_id', $editing?->country_id) === (string) $country->id)>{{ $country->name }}</option>
                @endforeach
            </select>
        </div>
    @endif

    <div>
        <label for="catalog_name" class="mb-1 block text-label-sm text-on-surface-variant">Tên hiển thị</label>
        <input id="catalog_name" name="name" value="{{ old('name', $editing?->name) }}" required maxlength="120"
            class="h-10 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3">
    </div>
    <div>
        <label for="catalog_code" class="mb-1 block text-label-sm text-on-surface-variant">Mã</label>
        <input id="catalog_code" name="code" value="{{ old('code', $editing?->code) }}" required maxlength="50"
            @readonly($catalog === 'countries' && $editing?->code === 'VN')
            class="h-10 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3 font-mono read-only:opacity-60">
    </div>

    @if ($catalog === 'administrative-units')
        <div>
            <label for="catalog_type" class="mb-1 block text-label-sm text-on-surface-variant">Loại địa phương</label>
            <select id="catalog_type" name="type" class="h-10 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3">
                <option value="province" @selected(old('type', $editing?->type) === 'province')>Tỉnh</option>
                <option value="city" @selected(old('type', $editing?->type) === 'city')>Thành phố trực thuộc trung ương</option>
            </select>
        </div>
    @elseif ($catalog === 'professions')
        <label class="flex items-start gap-2 text-body-sm"><input type="checkbox" name="requires_education_stage" value="1" @checked(old('requires_education_stage', $editing?->requires_education_stage)) class="mt-0.5 size-4 rounded text-primary"><span>Yêu cầu học viên chọn năm học</span></label>
        <label class="flex items-start gap-2 text-body-sm"><input type="checkbox" name="defaults_to_graduated" value="1" @checked(old('defaults_to_graduated', $editing?->defaults_to_graduated)) class="mt-0.5 size-4 rounded text-primary"><span>Mặc định là đã tốt nghiệp</span></label>
    @elseif ($catalog === 'education-stages')
        <label class="flex items-start gap-2 text-body-sm"><input type="checkbox" name="is_graduated" value="1" @checked(old('is_graduated', $editing?->is_graduated)) class="mt-0.5 size-4 rounded text-primary"><span>Đây là trạng thái đã tốt nghiệp</span></label>
    @endif

    <div>
        <label for="catalog_sort_order" class="mb-1 block text-label-sm text-on-surface-variant">Thứ tự hiển thị</label>
        <input id="catalog_sort_order" type="number" name="sort_order" min="0" max="65535" value="{{ old('sort_order', $editing?->sort_order ?? 0) }}"
            class="h-10 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3">
    </div>
    <label class="flex items-center gap-2 text-body-sm"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $editing ? $editing->is_active : true)) class="size-4 rounded text-primary">Hiển thị cho học viên</label>
    <button class="w-full rounded-lg bg-primary px-4 py-2.5 font-label-md font-semibold text-on-primary">{{ $editing ? 'Lưu thay đổi' : 'Thêm '.$config['singular'] }}</button>
</form>

@if ($editing)
    <form method="post" action="{{ route($config['route'].'.toggle', ['item' => $editing->id]) }}" class="mt-3 text-center">
        @csrf @method('PATCH')
        <button class="text-label-sm font-semibold text-on-surface-variant hover:text-primary hover:underline">{{ $editing->is_active ? 'Ngừng hiển thị' : 'Kích hoạt lại' }}</button>
    </form>
@endif
