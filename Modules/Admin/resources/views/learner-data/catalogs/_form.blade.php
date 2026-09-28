@php
    $formRoute = $editing
        ? route($config['route'].'.update', ['item' => $editing->id])
        : route($config['route'].'.store');
@endphp

<form method="post" action="{{ $formRoute }}" class="flex min-h-0 flex-1 flex-col"
    x-data="learnerCatalogForm(@js($catalog), @js($editing?->name ?? old('name', '')), @js($editing?->code ?? old('code', '')))"
    @submit="syncCode()">
    @csrf
    @if ($editing) @method('PUT') @endif

    <div class="min-h-0 flex-1 space-y-6 overflow-y-auto px-5 py-5">
    @if ($catalog === 'administrative-units')
        <div>
            <label for="catalog_country_id" class="mb-2 block text-sm font-semibold text-on-surface">Quốc gia</label>
            <select id="catalog_country_id" name="country_id" required class="h-12 w-full rounded-xl border border-outline-variant bg-surface-container-low px-4 text-base">
                <option value="">Chọn quốc gia</option>
                @foreach ($countries as $country)
                    <option value="{{ $country->id }}" @selected((string) old('country_id', $editing?->country_id) === (string) $country->id)>{{ $country->name }}</option>
                @endforeach
            </select>
        </div>
    @endif

    <div>
        <label for="catalog_name" class="mb-2 block text-sm font-semibold text-on-surface">Tên hiển thị <span class="text-error">*</span></label>
        <input id="catalog_name" name="name" value="{{ old('name', $editing?->name) }}" required maxlength="120"
            x-model="name" @input="generateCode()"
            placeholder="Ví dụ: {{ $config['title'] }}"
            class="h-12 w-full rounded-xl border border-outline-variant bg-surface-container-low px-4 text-base">
    </div>
    @if ($editing)
    <div>
        <label for="catalog_code" class="mb-2 block text-sm font-semibold text-on-surface">Tên viết tắt</label>
        <input id="catalog_code" name="code" value="{{ old('code', $editing?->code) }}" required maxlength="50"
            x-model="code" @input="codeTouched = true"
            @readonly($catalog === 'countries' && $editing?->code === 'VN')
            class="h-12 w-full rounded-xl border border-outline-variant bg-surface-container-low px-4 text-base read-only:opacity-60">
        <p class="mt-1.5 text-xs text-on-surface-variant">Tên viết tắt dùng để nhận diện danh mục.</p>
    </div>
    @else
        <input type="hidden" name="code" x-model="code">
    @endif

    @if ($catalog === 'administrative-units')
        <div>
            <label for="catalog_type" class="mb-2 block text-sm font-semibold text-on-surface">Loại địa phương</label>
            <select id="catalog_type" name="type" class="h-12 w-full rounded-xl border border-outline-variant bg-surface-container-low px-4 text-base">
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
        <label for="catalog_sort_order" class="mb-2 block text-sm font-semibold text-on-surface">Thứ tự hiển thị</label>
        <input id="catalog_sort_order" type="number" name="sort_order" min="0" max="65535" value="{{ old('sort_order', $editing?->sort_order ?? 0) }}"
            class="h-12 w-full rounded-xl border border-outline-variant bg-surface-container-low px-4 text-base">
    </div>
    <label class="flex items-center gap-2 text-body-sm text-on-surface"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $editing ? $editing->is_active : true)) class="mt-0.5 size-4 rounded text-primary">Hiển thị cho học viên</label>
    </div>
    <div class="flex items-center justify-end gap-3 border-t border-outline-variant px-5 py-4">
        <button type="button" @click="window.location.href = '{{ route($config['route'].'.index') }}'" class="h-11 rounded-lg px-4 text-sm font-semibold text-on-surface-variant hover:bg-surface-container-low">Hủy</button>
        <button class="h-11 rounded-lg bg-primary px-5 text-sm font-semibold text-on-primary">{{ $editing ? 'Lưu thay đổi' : 'Thêm '.$config['singular'] }}</button>
    </div>
</form>

<script>
    function learnerCatalogForm(catalog, initialName, initialCode) {
        return {
            catalog,
            name: initialName,
            code: initialCode,
            codeTouched: Boolean(initialCode),
            normalize(value) {
                return value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/đ/g, 'd').replace(/Đ/g, 'D');
            },
            countryCode(value) {
                const words = this.normalize(value).toUpperCase().match(/[A-Z]+/g) || [];
                if (words.length > 1) return words.map((word) => word[0]).join('').slice(0, 2);
                return (words[0] || '').slice(0, 2);
            },
            abbreviation(value) {
                const words = this.normalize(value).toUpperCase().match(/[A-Z]+/g) || [];
                return words.map((word) => word[0]).join('').slice(0, 20);
            },
            generateCode() {
                if (this.codeTouched || ! this.name.trim()) return;
                this.code = this.catalog === 'countries' ? this.countryCode(this.name) : this.abbreviation(this.name);
            },
            syncCode() {
                if (! this.codeTouched) this.generateCode();
            },
        };
    }
</script>
