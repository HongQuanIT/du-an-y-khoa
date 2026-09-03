<x-layouts.admin title="Tạo lớp cho giảng viên">
    <div class="mx-auto max-w-2xl">
        <a href="{{ route('admin.classrooms.index') }}" class="mb-4 inline-flex items-center gap-1 text-sm text-primary hover:underline">
            <span class="material-symbols-outlined text-[18px]">arrow_back</span>
            Danh sách lớp
        </a>
        <h1 class="font-headline-lg text-headline-lg text-on-surface">Tạo lớp cho giảng viên</h1>
        <p class="mt-2 text-on-surface-variant">Lớp sẽ được duyệt ngay và gán cho giảng viên đã chọn.</p>

        <form method="post" action="{{ route('admin.classrooms.store') }}" class="mt-6 space-y-5 rounded-xl border border-outline-variant bg-surface p-6 shadow-sm">
            @csrf
            <div>
                <label class="mb-1.5 block text-sm font-medium" for="host_user_id">Giảng viên</label>
                <select id="host_user_id" name="host_user_id" required class="w-full rounded-lg border border-outline-variant px-3 py-2.5">
                    <option value="">Chọn giảng viên</option>
                    @foreach ($instructors as $instructor)
                        <option value="{{ $instructor->id }}" @selected((string) old('host_user_id') === (string) $instructor->id)>{{ $instructor->name }} · {{ $instructor->email }}</option>
                    @endforeach
                </select>
                @error('host_user_id')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium" for="title">Tên lớp</label>
                <input id="title" name="title" value="{{ old('title') }}" required maxlength="200" class="w-full rounded-lg border border-outline-variant px-3 py-2.5">
                @error('title')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium" for="description">Mô tả</label>
                <textarea id="description" name="description" rows="4" class="w-full rounded-lg border border-outline-variant px-3 py-2.5">{{ old('description') }}</textarea>
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-medium" for="purpose">Loại lớp</label>
                    <select id="purpose" name="purpose" required class="w-full rounded-lg border border-outline-variant px-3 py-2.5">
                        @foreach ($purposes as $purpose)<option value="{{ $purpose->value }}" @selected(old('purpose') === $purpose->value)>{{ $purpose->label() }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium" for="visibility">Hiển thị</label>
                    <select id="visibility" name="visibility" required class="w-full rounded-lg border border-outline-variant px-3 py-2.5">
                        @foreach ($visibilities as $visibility)<option value="{{ $visibility->value }}" @selected(old('visibility', 'public') === $visibility->value)>{{ $visibility->label() }}</option>@endforeach
                    </select>
                </div>
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium" for="max_members">Số học viên tối đa (không bắt buộc)</label>
                <input id="max_members" name="max_members" type="number" min="2" max="5000" value="{{ old('max_members') }}" class="w-full rounded-lg border border-outline-variant px-3 py-2.5">
            </div>
            <button class="rounded-lg bg-primary px-5 py-2.5 font-semibold text-on-primary">Tạo lớp</button>
        </form>
    </div>
</x-layouts.admin>
