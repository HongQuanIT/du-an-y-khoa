@php $isNew = ! $tag->exists; @endphp
<x-layouts.admin :title="$isNew ? 'Tạo tag' : 'Sửa tag'">
    <x-admin.page-header :title="$isNew ? 'Tạo tag' : $tag->name">
        <x-slot:actions>
            <a href="{{ route('admin.tags.index') }}" class="rounded-lg border border-outline-variant px-4 py-2 text-sm font-semibold text-on-surface-variant hover:bg-surface-container-low">← Danh sách</a>
        </x-slot:actions>
    </x-admin.page-header>

    @include('admin::taxonomy._sub-nav', ['active' => 'tags'])

    <x-admin.flash />

    <form method="post" action="{{ $isNew ? route('admin.tags.store') : route('admin.tags.update', $tag) }}" class="max-w-xl space-y-4 rounded-xl border border-outline-variant bg-surface p-5">
        @csrf @unless($isNew) @method('PUT') @endunless
        <div>
            <label class="mb-1 block text-xs font-semibold">Tên *</label>
            <input name="name" value="{{ old('name', $tag->name) }}" required class="w-full rounded-lg bg-surface-container-low px-3 py-2" @disabled(! $canUpdate)>
        </div>
        <div>
            <label class="mb-1 block text-xs font-semibold">Slug</label>
            <input name="slug" value="{{ old('slug', $tag->slug) }}" class="w-full rounded-lg bg-surface-container-low px-3 py-2" @disabled(! $canUpdate)>
        </div>
        <div>
            <label class="mb-1 block text-xs font-semibold">Type</label>
            <input name="type" value="{{ old('type', $tag->type) }}" placeholder="clinical, skill, exam…" class="w-full rounded-lg bg-surface-container-low px-3 py-2" @disabled(! $canUpdate)>
        </div>
        <div>
            <label class="mb-1 block text-xs font-semibold">Mô tả</label>
            <textarea name="description" rows="3" class="w-full rounded-lg bg-surface-container-low px-3 py-2" @disabled(! $canUpdate)>{{ old('description', $tag->description) }}</textarea>
        </div>
        <div>
            <label class="mb-1 block text-xs font-semibold">Trạng thái</label>
            <select name="status" class="w-full rounded-lg bg-surface-container-low px-3 py-2" @disabled(! $canUpdate)>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(old('status', $tag->status?->value) === $status->value)>{{ $status->value }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex flex-wrap gap-2 pt-2">
            @if ($canUpdate)
                <button type="submit" class="rounded-lg bg-primary px-4 py-2 font-semibold text-on-primary">Lưu</button>
            @endif
        </div>
    </form>

    @if ($canDelete && ! $isNew)
        <form method="post" action="{{ route('admin.tags.destroy', $tag) }}" class="mt-4"
            onsubmit="return confirm('Xóa hoặc vô hiệu hóa tag này?')">
            @csrf @method('DELETE')
            <button type="submit" class="rounded-lg border border-error/30 px-4 py-2 text-sm font-semibold text-error hover:bg-error/5">Xóa tag</button>
        </form>
    @endif
</x-layouts.admin>
