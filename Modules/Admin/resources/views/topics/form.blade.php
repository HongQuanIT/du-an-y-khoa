@php
    $isNew = ! $topic->exists;
@endphp

<x-layouts.admin :title="$isNew ? 'Thêm chủ đề' : 'Sửa chủ đề'">
    <x-admin.page-header :title="$isNew ? 'Thêm chủ đề' : 'Sửa chủ đề'"
        :description="$isNew ? 'Thêm chủ đề mới vào ngân hàng câu hỏi.' : 'Cập nhật chủ đề #'.$topic->id.'.'">
        <x-slot:actions>
            <a href="{{ route('admin.topics.index') }}" class="rounded-lg px-3 py-2 font-label-md text-on-surface-variant hover:bg-surface-container-low">← Danh sách</a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.flash />

    <form method="post" action="{{ $isNew ? route('admin.topics.store') : route('admin.topics.update', $topic) }}" class="max-w-3xl space-y-6">
        @csrf
        @unless ($isNew) @method('PUT') @endunless

        <div class="space-y-5 rounded-xl border border-outline-variant bg-surface p-5">
            <div>
                <label class="mb-1 block font-label-sm text-on-surface-variant" for="name">Tên chủ đề *</label>
                <input id="name" name="name" value="{{ old('name', $topic->name) }}" type="text" required maxlength="255" @disabled(! $canUpdate)
                    class="w-full rounded-lg border-none bg-surface-container-low px-3 py-2.5 font-body-sm focus:ring-2 focus:ring-primary disabled:opacity-60">
                @error('name') <p class="mt-1 font-label-sm text-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-1 block font-label-sm text-on-surface-variant" for="slug">Slug</label>
                <input id="slug" name="slug" value="{{ old('slug', $topic->slug) }}" type="text" maxlength="191" @disabled(! $canUpdate)
                    placeholder="Để trống để tự sinh từ tên"
                    class="w-full rounded-lg border-none bg-surface-container-low px-3 py-2.5 font-mono text-sm focus:ring-2 focus:ring-primary disabled:opacity-60">
                <p class="mt-1 font-label-sm text-on-surface-variant">Slug phải duy nhất và chỉ gồm chữ thường, số, dấu gạch ngang.</p>
                @error('slug') <p class="mt-1 font-label-sm text-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-1 block font-label-sm text-on-surface-variant" for="order">Thứ tự *</label>
                <input id="order" name="order" value="{{ old('order', $topic->order ?? 0) }}" type="number" min="0" required @disabled(! $canUpdate)
                    class="w-40 rounded-lg border-none bg-surface-container-low px-3 py-2.5 font-body-sm focus:ring-2 focus:ring-primary disabled:opacity-60">
                @error('order') <p class="mt-1 font-label-sm text-error">{{ $message }}</p> @enderror
            </div>

        </div>

        @if ($canUpdate)
            <button type="submit" class="rounded-lg bg-primary px-5 py-2.5 font-label-md font-semibold text-on-primary hover:opacity-90">{{ $isNew ? 'Tạo chủ đề' : 'Lưu thay đổi' }}</button>
        @endif
    </form>

    @if ($canDelete)
        <div class="mt-8 max-w-3xl border-t border-outline-variant pt-5">
            <form method="post" action="{{ route('admin.topics.destroy', $topic) }}" onsubmit="return confirm('Bạn chắc chắn muốn xóa chủ đề này?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="rounded-lg px-4 py-2 font-label-md text-error hover:bg-error/10">Xóa chủ đề</button>
            </form>
            <p class="mt-1 font-label-sm text-on-surface-variant">Chỉ có thể xóa chủ đề chưa được sử dụng bởi câu hỏi hoặc dữ liệu học tập.</p>
        </div>
    @endif
</x-layouts.admin>
