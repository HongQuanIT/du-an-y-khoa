@php
    use Modules\Classroom\Enums\ClassroomStatus;

    $wasApproved = in_array($classroom->status, [ClassroomStatus::Active, ClassroomStatus::Closed], true);
@endphp

<x-layouts.teach
    :title="'Chỉnh sửa ' . $classroom->title"
    :description="'Cập nhật thông tin lớp ' . $classroom->title . ' trên MedLearn.'">
    <main class="mx-auto max-w-3xl">
        <nav aria-label="Điều hướng lớp học" class="text-sm text-on-surface-variant">
            <a href="{{ route('teach.classes.show', $classroom) }}" class="hover:text-primary hover:underline">← Quay lại chi tiết lớp</a>
        </nav>

        <header class="mt-5 border-b border-outline-variant pb-5">
            <p class="text-sm font-semibold uppercase tracking-wide text-primary">Quản lý lớp học</p>
            <h1 class="mt-1 font-headline-md text-headline-md font-bold text-on-surface">Chỉnh sửa thông tin lớp</h1>
            <p class="mt-2 text-sm leading-6 text-on-surface-variant">
                Thay đổi tên, mô tả, loại buổi hoặc chế độ tham gia được xem là thay đổi quan trọng và lớp sẽ cần quản trị viên duyệt lại.
                Thay đổi riêng giới hạn thành viên không làm mất trạng thái đã duyệt.
            </p>
        </header>

        @if ($errors->any())
            <section role="alert" class="mt-5 rounded-lg border border-error/30 bg-error-container p-4 text-on-error-container">
                <h2 class="font-semibold">Chưa thể lưu thay đổi</h2>
                <ul class="mt-2 list-disc pl-5 text-sm">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </section>
        @endif

        @if ($wasApproved)
            <aside class="mt-5 rounded-lg border border-tertiary/30 bg-tertiary/10 p-4 text-sm text-on-surface">
                Lớp này đã từng được duyệt. Nếu chỉ muốn tiếp tục giảng dạy, hãy dùng “Mở lại lớp” thay vì thay đổi nội dung quan trọng.
            </aside>
        @endif

        <form method="post" action="{{ route('teach.classes.update', $classroom) }}" class="mt-6 space-y-5 rounded-xl border border-outline-variant bg-surface p-5 md:p-6">
            @csrf
            @method('PUT')

            <div>
                <label for="title" class="block text-sm font-semibold text-on-surface">Tên lớp <span class="text-error">*</span></label>
                <input id="title" name="title" required maxlength="200" value="{{ old('title', $classroom->title) }}"
                    class="mt-1.5 w-full rounded-lg border border-outline-variant bg-surface px-3 py-2.5 focus:border-primary focus:ring-primary">
            </div>

            <div>
                <label for="description" class="block text-sm font-semibold text-on-surface">Mô tả</label>
                <textarea id="description" name="description" rows="6" maxlength="5000"
                    class="mt-1.5 w-full rounded-lg border border-outline-variant bg-surface px-3 py-2.5 focus:border-primary focus:ring-primary">{{ old('description', $classroom->description) }}</textarea>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="purpose" class="block text-sm font-semibold text-on-surface">Loại buổi</label>
                    <select id="purpose" name="purpose" required class="mt-1.5 w-full rounded-lg border border-outline-variant bg-surface px-3 py-2.5">
                        @foreach ($purposes as $purpose)
                            <option value="{{ $purpose->value }}" @selected(old('purpose', $classroom->purpose->value) === $purpose->value)>{{ $purpose->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="visibility" class="block text-sm font-semibold text-on-surface">Chế độ tham gia</label>
                    <select id="visibility" name="visibility" required class="mt-1.5 w-full rounded-lg border border-outline-variant bg-surface px-3 py-2.5">
                        @foreach ($visibilities as $visibility)
                            <option value="{{ $visibility->value }}" @selected(old('visibility', $classroom->visibility->value) === $visibility->value)>{{ $visibility->label() }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label for="max_members" class="block text-sm font-semibold text-on-surface">Giới hạn thành viên</label>
                <input id="max_members" name="max_members" type="number" min="2" max="5000"
                    value="{{ old('max_members', $classroom->max_members) }}"
                    class="mt-1.5 w-full rounded-lg border border-outline-variant bg-surface px-3 py-2.5 sm:max-w-xs">
                <p class="mt-1 text-xs text-on-surface-variant">Để trống nếu không giới hạn.</p>
            </div>

            <div class="flex flex-wrap justify-end gap-3 border-t border-outline-variant pt-5">
                <a href="{{ route('teach.classes.show', $classroom) }}" class="rounded-lg border border-outline-variant px-4 py-2.5 text-sm font-semibold text-on-surface">Hủy</a>
                <button type="submit" class="rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-on-primary">Lưu thay đổi</button>
            </div>
        </form>
    </main>
</x-layouts.teach>
