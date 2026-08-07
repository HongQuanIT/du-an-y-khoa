<x-layouts.app :title="'Cài đặt — '.$classroom->title">
    <div class="mx-auto max-w-xl space-y-6 p-6 md:p-8">
        <a href="{{ route('classroom.show', $classroom) }}" class="text-sm text-primary hover:underline">← {{ $classroom->title }}</a>

        @if (session('success'))
            <div class="rounded-xl border border-primary/20 bg-primary/10 px-4 py-3 text-sm text-primary">{{ session('success') }}</div>
        @endif

        <h1 class="font-headline-lg text-headline-lg text-on-surface">Cài đặt lớp</h1>

        <form method="post" action="{{ route('classroom.settings.update', $classroom) }}" class="space-y-4 rounded-2xl border border-outline-variant bg-surface p-6">
            @csrf
            @method('PATCH')

            <div>
                <label class="mb-1 block text-sm font-medium text-on-surface">Tên lớp</label>
                <input type="text" name="title" required value="{{ old('title', $classroom->title) }}"
                    class="w-full rounded-xl border-none bg-surface-container-low px-3 py-2 text-sm focus:ring-2 focus:ring-primary">
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-on-surface">Mô tả</label>
                <textarea name="description" rows="4"
                    class="w-full rounded-xl border-none bg-surface-container-low px-3 py-2 text-sm focus:ring-2 focus:ring-primary">{{ old('description', $classroom->description) }}</textarea>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-on-surface">Hiển thị</label>
                <select name="visibility" class="w-full rounded-xl border-none bg-surface-container-low px-3 py-2 text-sm focus:ring-2 focus:ring-primary">
                    @foreach (['public' => 'Công khai', 'unlisted' => 'Không liệt kê', 'invite_only' => 'Chỉ mời'] as $val => $label)
                        <option value="{{ $val }}" @selected(old('visibility', $classroom->visibility->value) === $val)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-on-surface">Số thành viên tối đa</label>
                <input type="number" name="max_members" min="2" max="500"
                    value="{{ old('max_members', $classroom->max_members) }}"
                    class="w-full rounded-xl border-none bg-surface-container-low px-3 py-2 text-sm focus:ring-2 focus:ring-primary"
                    placeholder="Không giới hạn">
            </div>

            <button type="submit" class="rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white hover:opacity-90">
                Lưu thay đổi
            </button>
        </form>
    </div>
</x-layouts.app>
