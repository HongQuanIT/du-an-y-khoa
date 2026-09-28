<form method="post" :action="formAction" class="flex min-h-0 flex-1 flex-col">
    @csrf
    <input type="hidden" name="_method" :value="panel === 'edit' ? 'PUT' : 'POST'">

    <div class="min-h-0 flex-1 space-y-6 overflow-y-auto px-5 py-5">
    @if ($catalog === 'administrative-units')
        <div>
            <label for="catalog_country_id" class="mb-2 block text-sm font-semibold text-on-surface">Quốc gia</label>
            <select id="catalog_country_id" name="country_id" x-model="form.country_id" required class="h-12 w-full rounded-xl border border-outline-variant bg-surface-container-low px-4 text-base">
                <option value="">Chọn quốc gia</option>
                @foreach ($countries as $country)
                    <option value="{{ $country->id }}">{{ $country->name }}</option>
                @endforeach
            </select>
        </div>
    @endif

    <div>
        <label for="catalog_name" class="mb-2 block text-sm font-semibold text-on-surface">Tên hiển thị <span class="text-error">*</span></label>
        <input id="catalog_name" name="name" x-model="form.name" @input="generateCode()" required maxlength="120" placeholder="Ví dụ: {{ $config['title'] }}" class="h-12 w-full rounded-xl border border-outline-variant bg-surface-container-low px-4 text-base">
    </div>

    <div x-show="panel === 'edit'">
        <label for="catalog_code" class="mb-2 block text-sm font-semibold text-on-surface">Tên viết tắt</label>
        <input id="catalog_code" name="code" x-model="form.code" @input="form.codeTouched = true" required maxlength="50" :disabled="panel !== 'edit'" :readonly="@js($catalog === 'countries') && form.code === 'VN'" class="h-12 w-full rounded-xl border border-outline-variant bg-surface-container-low px-4 text-base read-only:opacity-60">
        <p class="mt-1.5 text-xs text-on-surface-variant">Tên viết tắt dùng để nhận diện danh mục.</p>
    </div>
    <input type="hidden" name="code" :value="form.code" :disabled="panel === 'edit'">

    @if ($catalog === 'administrative-units')
        <div>
            <label for="catalog_type" class="mb-2 block text-sm font-semibold text-on-surface">Loại địa phương</label>
            <select id="catalog_type" name="type" x-model="form.type" class="h-12 w-full rounded-xl border border-outline-variant bg-surface-container-low px-4 text-base">
                <option value="province">Tỉnh</option>
                <option value="city">Thành phố trực thuộc trung ương</option>
            </select>
        </div>
    @elseif ($catalog === 'professions')
        <label class="flex items-start gap-2 text-body-sm"><input type="checkbox" name="requires_education_stage" value="1" x-model="form.requires_education_stage" class="mt-0.5 size-4 rounded text-primary"><span>Yêu cầu học viên chọn năm học</span></label>
        <label class="flex items-start gap-2 text-body-sm"><input type="checkbox" name="defaults_to_graduated" value="1" x-model="form.defaults_to_graduated" class="mt-0.5 size-4 rounded text-primary"><span>Mặc định là đã tốt nghiệp</span></label>
    @elseif ($catalog === 'education-stages')
        <label class="flex items-start gap-2 text-body-sm"><input type="checkbox" name="is_graduated" value="1" x-model="form.is_graduated" class="mt-0.5 size-4 rounded text-primary"><span>Đây là trạng thái đã tốt nghiệp</span></label>
    @endif

    <div>
        <label for="catalog_sort_order" class="mb-2 block text-sm font-semibold text-on-surface">Thứ tự hiển thị</label>
        <input id="catalog_sort_order" type="number" name="sort_order" min="0" max="65535" x-model="form.sort_order" class="h-12 w-full rounded-xl border border-outline-variant bg-surface-container-low px-4 text-base">
    </div>
    <label class="flex items-center gap-2 text-body-sm text-on-surface"><input type="checkbox" name="is_active" value="1" x-model="form.is_active" class="mt-0.5 size-4 rounded text-primary">Hiển thị cho học viên</label>
    </div>
    <div class="flex items-center justify-end gap-3 border-t border-outline-variant px-5 py-4">
        <button type="button" @click="closePanel()" class="h-11 rounded-lg px-4 text-sm font-semibold text-on-surface-variant hover:bg-surface-container-low">Hủy</button>
        <button type="submit" class="h-11 rounded-lg bg-primary px-5 text-sm font-semibold text-on-primary" x-text="panel === 'edit' ? 'Lưu thay đổi' : 'Thêm {{ $config['singular'] }}'"></button>
    </div>
</form>
