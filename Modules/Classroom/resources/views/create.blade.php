@php
    use Modules\Classroom\Enums\ClassroomVisibility;
@endphp

<x-layouts.app title="Tạo lớp">
    <div class="mx-auto max-w-xl space-y-6 p-6 md:p-8">
        <div>
            <a href="{{ route('classroom.index') }}" class="mb-4 inline-flex items-center gap-1 text-sm text-primary hover:underline">
                <span class="material-symbols-outlined text-[18px]">arrow_back</span>
                Classroom
            </a>
            <h1 class="font-headline-lg text-headline-lg text-on-surface">Tạo lớp chữa đề</h1>
            <p class="mt-2 text-on-surface-variant">Host livestream chữa đề; học viên join bằng link hoặc mã.</p>
        </div>

        <form method="post" action="{{ route('classroom.store') }}" class="space-y-5 rounded-2xl border border-outline-variant bg-surface p-6 shadow-sm">
            @csrf
            <div>
                <label for="title" class="mb-1.5 block text-sm font-medium text-on-surface">Tên lớp</label>
                <input id="title" name="title" type="text" value="{{ old('title') }}" required maxlength="200"
                    class="w-full rounded-xl border-none bg-surface-container-low px-4 py-3 text-on-surface focus:ring-2 focus:ring-primary"
                    placeholder="Ví dụ: Chữa đề Nội khoa tuần 12">
                @error('title') <p class="mt-1 text-sm text-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="description" class="mb-1.5 block text-sm font-medium text-on-surface">Mô tả</label>
                <textarea id="description" name="description" rows="4" maxlength="5000"
                    class="w-full rounded-xl border-none bg-surface-container-low px-4 py-3 text-on-surface focus:ring-2 focus:ring-primary"
                    placeholder="Nội dung buổi chữa, lịch dự kiến...">{{ old('description') }}</textarea>
                @error('description') <p class="mt-1 text-sm text-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="visibility" class="mb-1.5 block text-sm font-medium text-on-surface">Chế độ tham gia</label>
                <select id="visibility" name="visibility"
                    class="w-full rounded-xl border-none bg-surface-container-low px-4 py-3 text-on-surface focus:ring-2 focus:ring-primary">
                    @foreach ($visibilities as $vis)
                        <option value="{{ $vis->value }}" @selected(old('visibility', ClassroomVisibility::Public->value) === $vis->value)>
                            {{ $vis->label() }}
                        </option>
                    @endforeach
                </select>
                @error('visibility') <p class="mt-1 text-sm text-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="max_members" class="mb-1.5 block text-sm font-medium text-on-surface">Giới hạn thành viên (tuỳ chọn)</label>
                <input id="max_members" name="max_members" type="number" min="2" max="5000" value="{{ old('max_members') }}"
                    class="w-full rounded-xl border-none bg-surface-container-low px-4 py-3 text-on-surface focus:ring-2 focus:ring-primary"
                    placeholder="Để trống = không giới hạn">
                @error('max_members') <p class="mt-1 text-sm text-error">{{ $message }}</p> @enderror
            </div>

            <button type="submit"
                class="w-full rounded-xl bg-primary py-3 font-semibold text-white shadow-md transition hover:opacity-90">
                Tạo lớp
            </button>
        </form>
    </div>
</x-layouts.app>
