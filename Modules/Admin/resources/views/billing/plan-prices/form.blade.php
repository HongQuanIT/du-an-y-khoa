<x-layouts.admin :title="($isNew ? 'Thêm' : 'Sửa').' mức giá — '.$plan->name">
    <x-admin.page-header
        :title="($isNew ? 'Thêm mức giá' : 'Sửa mức giá').': '.$plan->name"
        description="SKU hiển thị trên /pricing (tháng, năm, prepaid…).">
        <x-slot:actions>
            <a href="{{ route('admin.billing.plans.edit', $plan) }}"
                class="rounded-lg border border-outline-variant px-4 py-2 font-label-md text-on-surface-variant hover:bg-surface-container-low">
                Quay lại
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.flash />

    <form method="post"
        action="{{ $isNew ? route('admin.billing.plans.prices.store', $plan) : route('admin.billing.plan-prices.update', $price) }}"
        class="mx-auto max-w-2xl space-y-5 rounded-xl border border-outline-variant bg-surface p-6">
        @csrf
        @if (! $isNew)
            @method('PUT')
        @endif

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block font-label-sm text-on-surface-variant" for="slug">Slug</label>
                <input id="slug" name="slug" type="text" required pattern="[a-z0-9]+(?:-[a-z0-9]+)*"
                    value="{{ old('slug', $price->slug) }}"
                    class="w-full rounded-lg border-none bg-surface-container-low px-3 py-2 font-body-sm font-mono">
            </div>
            <div>
                <label class="mb-1 block font-label-sm text-on-surface-variant" for="label">Nhãn hiển thị</label>
                <input id="label" name="label" type="text" required value="{{ old('label', $price->label) }}"
                    class="w-full rounded-lg border-none bg-surface-container-low px-3 py-2 font-body-sm">
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block font-label-sm text-on-surface-variant" for="price_cents">Giá bán (VND)</label>
                <input id="price_cents" name="price_cents" type="number" min="0" required
                    value="{{ old('price_cents', $price->price_cents) }}"
                    class="w-full rounded-lg border-none bg-surface-container-low px-3 py-2 font-body-sm">
            </div>
            <div>
                <label class="mb-1 block font-label-sm text-on-surface-variant" for="savings_percent">Tiết kiệm (%)</label>
                <input id="savings_percent" name="savings_percent" type="number" min="0" max="99"
                    value="{{ old('savings_percent', $price->savings_percent) }}"
                    placeholder="Tự tính nếu để trống"
                    class="w-full rounded-lg border-none bg-surface-container-low px-3 py-2 font-body-sm">
                <p class="mt-1 font-body-sm text-on-surface-variant">
                    Gói trả trước: nhập % hoặc để trống — hệ thống suy từ giá tháng × thời hạn.
                </p>
            </div>
        </div>

        @if (! $isNew && $price->listPriceCents())
            <p class="rounded-lg bg-surface-container-low px-3 py-2 font-body-sm text-on-surface-variant">
                Giá gạch ngang (tự tính):
                <strong class="text-on-surface">{{ number_format($price->listPriceCents(), 0, ',', '.') }}₫</strong>
                @if ($price->displaySavingsPercent() !== null)
                    · tiết kiệm {{ $price->displaySavingsPercent() }}%
                @endif
            </p>
        @endif

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block font-label-sm text-on-surface-variant" for="billing_type">Loại</label>
                <select id="billing_type" name="billing_type" class="w-full rounded-lg border-none bg-surface-container-low px-3 py-2 font-body-sm">
                    @foreach (['none' => 'Miễn phí', 'recurring' => 'Thuê bao tháng', 'prepaid' => 'Trả trước'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('billing_type', $price->billing_type) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block font-label-sm text-on-surface-variant" for="duration_days">Thời hạn (ngày)</label>
                <input id="duration_days" name="duration_days" type="number" min="1"
                    value="{{ old('duration_days', $price->duration_days) }}"
                    class="w-full rounded-lg border-none bg-surface-container-low px-3 py-2 font-body-sm">
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block font-label-sm text-on-surface-variant" for="badge_label">Badge</label>
                <input id="badge_label" name="badge_label" type="text" value="{{ old('badge_label', $price->badge_label) }}"
                    class="w-full rounded-lg border-none bg-surface-container-low px-3 py-2 font-body-sm">
            </div>
            <div>
                <label class="mb-1 block font-label-sm text-on-surface-variant" for="cta_label">Nút CTA</label>
                <input id="cta_label" name="cta_label" type="text" value="{{ old('cta_label', $price->cta_label) }}"
                    class="w-full rounded-lg border-none bg-surface-container-low px-3 py-2 font-body-sm">
            </div>
        </div>

        <div>
            <label class="mb-1 block font-label-sm text-on-surface-variant" for="sort_order">Thứ tự</label>
            <input id="sort_order" name="sort_order" type="number" min="0" required
                value="{{ old('sort_order', $price->sort_order) }}"
                class="w-full max-w-xs rounded-lg border-none bg-surface-container-low px-3 py-2 font-body-sm">
        </div>

        <div class="flex flex-wrap gap-4">
            <label class="flex items-center gap-2">
                <input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $price->is_featured))>
                <span class="font-body-sm">Nổi bật (card giữa)</span>
            </label>
            <label class="flex items-center gap-2">
                <input type="checkbox" name="is_public" value="1" @checked(old('is_public', $price->is_public))>
                <span class="font-body-sm">Hiển thị công khai</span>
            </label>
        </div>

        <div class="flex justify-end border-t border-outline-variant pt-4">
            <button type="submit" class="rounded-lg bg-primary px-5 py-2.5 font-label-md text-on-primary hover:opacity-90">
                {{ $isNew ? 'Thêm mức giá' : 'Lưu thay đổi' }}
            </button>
        </div>
    </form>

    @if (! $isNew)
        <form method="post" action="{{ route('admin.billing.plan-prices.destroy', $price) }}"
            class="mx-auto mt-4 max-w-2xl"
            onsubmit="return confirm('Xóa mức giá này?')">
            @csrf
            @method('DELETE')
            <button type="submit" class="rounded-lg border border-error/40 px-4 py-2 font-label-md text-error">
                Xóa mức giá
            </button>
        </form>
    @endif
</x-layouts.admin>
