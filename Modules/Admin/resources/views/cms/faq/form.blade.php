@php
    $isNew = ! $faq->exists;
@endphp

<x-layouts.admin :title="$isNew ? 'CMS — Thêm FAQ' : 'CMS — Sửa FAQ'">
    @include('admin::cms._sub-nav')

    <x-admin.page-header :title="$isNew ? 'Thêm FAQ' : 'Sửa FAQ'"
        :description="$isNew ? 'Tạo câu hỏi thường gặp mới.' : 'Cập nhật nội dung FAQ #'.$faq->id">
        <x-slot:actions>
            <a href="{{ route('admin.cms.faq.index') }}"
                class="rounded-lg px-3 py-2 font-label-md text-on-surface-variant hover:bg-surface-container-low">← Danh sách</a>
            @if (! $isNew && $faq->is_published)
                <a href="{{ route('landing.faq') }}#faq-{{ $faq->id }}" target="_blank" rel="noopener noreferrer"
                    class="rounded-lg border border-outline-variant px-3 py-2 font-label-md text-on-surface hover:bg-surface-container-low">
                    Xem trên web ↗
                </a>
            @endif
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.flash />

    <form method="post"
        action="{{ $isNew ? route('admin.cms.faq.store') : route('admin.cms.faq.update', $faq) }}"
        class="max-w-3xl space-y-6">
        @csrf
        @unless ($isNew)
            @method('PUT')
        @endunless

        <div class="rounded-xl border border-outline-variant bg-surface p-5 space-y-4">
            <div>
                <label class="mb-1 block font-label-sm text-label-sm text-on-surface-variant" for="category">Danh mục *</label>
                <select id="category" name="category" required
                    class="w-full rounded-lg border-none bg-surface-container-low px-3 py-2 font-body-sm text-body-sm focus:ring-2 focus:ring-primary">
                    @foreach ($categories as $cat)
                        <option value="{{ $cat->value }}" @selected(old('category', $faq->category?->value) === $cat->value)>
                            {{ $cat->label() }}
                        </option>
                    @endforeach
                </select>
                @error('category')
                    <p class="mt-1 font-label-sm text-error">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="mb-1 block font-label-sm text-label-sm text-on-surface-variant" for="question">Câu hỏi *</label>
                <input id="question" name="question" type="text" required maxlength="500"
                    value="{{ old('question', $faq->question) }}"
                    placeholder="Ví dụ: Làm sao để đăng ký tài khoản?"
                    class="w-full rounded-lg border-none bg-surface-container-low px-3 py-2 font-body-sm text-body-sm focus:ring-2 focus:ring-primary">
                @error('question')
                    <p class="mt-1 font-label-sm text-error">{{ $message }}</p>
                @enderror
            </div>

            <x-admin.rich-editor name="answer" label="Câu trả lời" :value="old('answer', $faq->answer)" required
                placeholder="Nhập câu trả lời chi tiết..." />
            @error('answer')
                <p class="font-label-sm text-error">{{ $message }}</p>
            @enderror

            <div>
                <label class="mb-1 block font-label-sm text-label-sm text-on-surface-variant" for="sort_order">Thứ tự hiển thị</label>
                <input id="sort_order" name="sort_order" type="number" min="0" max="99999"
                    value="{{ old('sort_order', $faq->sort_order) }}"
                    class="w-32 rounded-lg border-none bg-surface-container-low px-3 py-2 font-body-sm text-body-sm focus:ring-2 focus:ring-primary">
                <p class="mt-1 font-label-sm text-on-surface-variant">Số nhỏ hơn hiển thị trước trong cùng danh mục.</p>
                @error('sort_order')
                    <p class="mt-1 font-label-sm text-error">{{ $message }}</p>
                @enderror
            </div>

            @if ($faq->is_published)
                <p class="rounded-lg bg-primary/10 px-3 py-2 font-label-sm text-primary">
                    FAQ đang xuất bản — lần đầu: {{ $faq->published_at?->format('d/m/Y H:i') ?? '—' }}
                </p>
            @endif
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <button type="submit" name="action" value="draft"
                class="rounded-lg border border-outline-variant px-4 py-2 font-label-md text-on-surface hover:bg-surface-container-low">
                Lưu nháp
            </button>
            <button type="submit" name="action" value="publish"
                class="rounded-lg bg-primary px-4 py-2 font-label-md text-on-primary hover:opacity-90">
                Lưu &amp; xuất bản
            </button>
        </div>
    </form>

    @unless ($isNew)
        <form method="post" action="{{ route('admin.cms.faq.destroy', $faq) }}" class="mt-4 max-w-3xl"
            onsubmit="return confirm('Xóa FAQ này?')">
            @csrf
            @method('DELETE')
            <button type="submit"
                class="rounded-lg px-4 py-2 font-label-md text-error hover:bg-error/10">
                Xóa FAQ
            </button>
        </form>
    @endunless
</x-layouts.admin>
