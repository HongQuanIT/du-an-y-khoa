@php
    $isNew = ! $banner->exists;
    $inputClass = 'block w-full min-w-0 rounded-lg border-none bg-surface-container-low px-3 py-2 font-body-sm text-body-sm focus:ring-2 focus:ring-primary';
@endphp

<x-layouts.admin :title="$isNew ? 'CMS — Thêm banner' : 'CMS — Sửa banner'">
    @include('admin::cms._sub-nav')

    <x-admin.page-header :title="$isNew ? 'Thêm banner' : 'Sửa banner'"
        :description="$isNew ? 'Tạo thông báo / khuyến mãi hiển thị trên landing hoặc dashboard.' : 'Cập nhật banner #'.$banner->id">
        <x-slot:actions>
            <a href="{{ route('admin.cms.banners.index') }}"
                class="rounded-lg px-3 py-2 font-label-md text-on-surface-variant hover:bg-surface-container-low">← Danh sách</a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.flash />

    <form method="post"
        action="{{ $isNew ? route('admin.cms.banners.store') : route('admin.cms.banners.update', $banner) }}"
        class="max-w-3xl space-y-6">
        @csrf
        @unless ($isNew)
            @method('PUT')
        @endunless

        <div class="overflow-hidden rounded-xl border border-outline-variant bg-surface">
            <div class="border-b border-outline-variant px-5 py-4">
                <h3 class="font-label-md text-on-surface">Nội dung</h3>
            </div>
            <div class="space-y-4 p-5">
                <div>
                    <label class="mb-1.5 block font-label-sm text-on-surface-variant" for="title">Tiêu đề nội bộ *</label>
                    <input id="title" name="title" type="text" required maxlength="255"
                        value="{{ old('title', $banner->title) }}"
                        placeholder="Chỉ hiện trong admin"
                        class="{{ $inputClass }}">
                    @error('title') <p class="mt-1 font-label-sm text-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1.5 block font-label-sm text-on-surface-variant" for="body">Nội dung hiển thị *</label>
                    <textarea id="body" name="body" rows="3" required maxlength="1000"
                        placeholder="Ví dụ: Giảm 20% Premium đến hết tháng này"
                        class="{{ $inputClass }} resize-y">{{ old('body', $banner->body) }}</textarea>
                    @error('body') <p class="mt-1 font-label-sm text-error">{{ $message }}</p> @enderror
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block font-label-sm text-on-surface-variant" for="cta_label">Nhãn nút CTA</label>
                        <input id="cta_label" name="cta_label" type="text" maxlength="100"
                            value="{{ old('cta_label', $banner->cta_label) }}"
                            placeholder="Xem bảng giá"
                            class="{{ $inputClass }}">
                        @error('cta_label') <p class="mt-1 font-label-sm text-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1.5 block font-label-sm text-on-surface-variant" for="cta_url">URL nút CTA</label>
                        <input id="cta_url" name="cta_url" type="text" maxlength="2048"
                            value="{{ old('cta_url', $banner->cta_url) }}"
                            placeholder="/pricing hoặc https://…"
                            class="{{ $inputClass }}">
                        @error('cta_url') <p class="mt-1 font-label-sm text-error">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-outline-variant bg-surface">
            <div class="border-b border-outline-variant px-5 py-4">
                <h3 class="font-label-md text-on-surface">Hiển thị &amp; đối tượng</h3>
            </div>
            <div class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block font-label-sm text-on-surface-variant" for="variant">Kiểu *</label>
                    <select id="variant" name="variant" required class="{{ $inputClass }}">
                        @foreach ($variants as $variant)
                            <option value="{{ $variant->value }}" @selected(old('variant', $banner->variant?->value) === $variant->value)>{{ $variant->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block font-label-sm text-on-surface-variant" for="placement">Vị trí *</label>
                    <select id="placement" name="placement" required class="{{ $inputClass }}">
                        @foreach ($placements as $placement)
                            <option value="{{ $placement->value }}" @selected(old('placement', $banner->placement?->value) === $placement->value)>{{ $placement->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="mb-1.5 block font-label-sm text-on-surface-variant" for="audience">Đối tượng *</label>
                    <select id="audience" name="audience" required class="{{ $inputClass }}">
                        @foreach ($audiences as $audience)
                            <option value="{{ $audience->value }}" @selected(old('audience', $banner->audience?->value) === $audience->value)>{{ $audience->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block font-label-sm text-on-surface-variant" for="sort_order">Thứ tự ưu tiên</label>
                    <input id="sort_order" name="sort_order" type="number" min="0" max="99999"
                        value="{{ old('sort_order', $banner->sort_order) }}"
                        class="{{ $inputClass }}">
                    <p class="mt-1 font-label-sm text-on-surface-variant">Số nhỏ hơn hiện trước khi nhiều banner cùng lúc.</p>
                </div>
                <div class="flex items-end pb-1">
                    <label class="inline-flex items-center gap-2 font-label-md text-on-surface">
                        <input type="checkbox" name="is_dismissible" value="1"
                            @checked(old('is_dismissible', $banner->is_dismissible))
                            class="rounded border-outline-variant text-primary focus:ring-primary">
                        Cho phép người dùng đóng banner
                    </label>
                </div>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-outline-variant bg-surface">
            <div class="border-b border-outline-variant px-5 py-4">
                <h3 class="font-label-md text-on-surface">Lịch hiển thị</h3>
                <p class="mt-1 font-label-sm text-on-surface-variant">Để trống = không giới hạn thời gian.</p>
            </div>
            <div class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block font-label-sm text-on-surface-variant" for="starts_at">Bắt đầu</label>
                    <input id="starts_at" name="starts_at" type="datetime-local"
                        value="{{ old('starts_at', optional($banner->starts_at)->format('Y-m-d\TH:i')) }}"
                        class="{{ $inputClass }}">
                    @error('starts_at') <p class="mt-1 font-label-sm text-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1.5 block font-label-sm text-on-surface-variant" for="ends_at">Kết thúc</label>
                    <input id="ends_at" name="ends_at" type="datetime-local"
                        value="{{ old('ends_at', optional($banner->ends_at)->format('Y-m-d\TH:i')) }}"
                        class="{{ $inputClass }}">
                    @error('ends_at') <p class="mt-1 font-label-sm text-error">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            @if ($banner->is_enabled)
                <button type="submit" name="action" value="save"
                    class="rounded-lg bg-primary px-4 py-2.5 font-label-md text-on-primary hover:opacity-90">
                    Lưu thay đổi
                </button>
                <button type="submit" name="action" value="disable"
                    class="rounded-lg border border-outline-variant px-4 py-2.5 font-label-md text-on-surface hover:bg-surface-container-low">
                    Tắt banner
                </button>
            @else
                <button type="submit" name="action" value="enable"
                    class="rounded-lg bg-primary px-4 py-2.5 font-label-md text-on-primary hover:opacity-90">
                    Lưu &amp; bật
                </button>
                <button type="submit" name="action" value="save"
                    class="rounded-lg border border-outline-variant px-4 py-2.5 font-label-md text-on-surface hover:bg-surface-container-low">
                    Lưu (tắt)
                </button>
            @endif
        </div>
    </form>

    @unless ($isNew)
        <form method="post" action="{{ route('admin.cms.banners.destroy', $banner) }}" class="mt-4 max-w-3xl"
            onsubmit="return confirm('Xóa banner này?')">
            @csrf
            @method('DELETE')
            <button type="submit" class="rounded-lg px-4 py-2 font-label-md text-error hover:bg-error/10">
                Xóa banner
            </button>
        </form>
    @endunless
</x-layouts.admin>
