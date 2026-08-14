@php
    use Modules\Classroom\Enums\ClassroomPurpose;
    use Modules\Classroom\Enums\ClassroomVisibility;
@endphp

<x-layouts.teach title="Tạo lớp">
    <div class="mx-auto max-w-2xl">
        <a href="{{ route('teach.classes.index') }}"
            class="mb-4 inline-flex items-center gap-1 font-label-sm text-label-sm text-primary hover:underline">
            <span class="material-symbols-outlined text-[18px]">arrow_back</span>
            Lớp của tôi
        </a>

        <header class="mb-6 space-y-1">
            <h2 class="font-headline-sm text-headline-sm text-on-surface">Tạo lớp chữa đề</h2>
            <p class="font-body-sm text-body-sm text-on-surface-variant">
                Chọn loại buổi (feedback QBank hoặc chữa exam), rồi cấu hình tham gia. Gắn đề và lịch live ở bước sau.
            </p>
        </header>

        @if ($errors->any())
            <div role="alert"
                class="mb-6 rounded-xl border border-error/30 bg-error-container px-4 py-3 font-body-sm text-body-sm text-on-error-container">
                <ul class="list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="post" action="{{ route('teach.classes.store') }}" class="space-y-6">
            @csrf

            <section class="rounded-xl border border-outline-variant bg-surface p-5 md:p-6">
                <h3 class="font-title-md text-title-md text-on-surface">Loại buổi</h3>
                <p class="mt-1 font-body-sm text-body-sm text-on-surface-variant">Bắt buộc — quyết định luồng gắn đề sau này.</p>

                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    @foreach ($purposes as $purpose)
                        <label
                            class="relative flex cursor-pointer flex-col rounded-xl border border-outline-variant p-4 transition has-[:checked]:border-primary has-[:checked]:bg-primary/5">
                            <input type="radio" name="purpose" value="{{ $purpose->value }}" class="sr-only"
                                @checked(old('purpose', ClassroomPurpose::FeedbackReview->value) === $purpose->value)
                                required>
                            <span class="font-label-md text-label-md font-semibold text-on-surface">{{ $purpose->label() }}</span>
                            <span class="mt-1 font-body-sm text-body-sm text-on-surface-variant">{{ $purpose->description() }}</span>
                        </label>
                    @endforeach
                </div>
            </section>

            <section class="space-y-5 rounded-xl border border-outline-variant bg-surface p-5 md:p-6">
                <div>
                    <h3 class="font-title-md text-title-md text-on-surface">Thông tin lớp</h3>
                    <p class="mt-1 font-body-sm text-body-sm text-on-surface-variant">Tên và cách học viên tham gia.</p>
                </div>

                <div>
                    <label for="title" class="mb-1.5 block font-label-sm text-label-sm font-medium text-on-surface-variant">Tên lớp</label>
                    <input id="title" name="title" type="text" value="{{ old('title') }}" required maxlength="200"
                        class="w-full rounded-lg border-none bg-surface-container-low px-4 py-3 font-body-md text-body-md text-on-surface focus:ring-2 focus:ring-primary"
                        placeholder="Ví dụ: Chữa feedback Nội khoa tuần 12">
                </div>

                <div>
                    <label for="description" class="mb-1.5 block font-label-sm text-label-sm font-medium text-on-surface-variant">Mô tả</label>
                    <textarea id="description" name="description" rows="4" maxlength="5000"
                        class="w-full rounded-lg border-none bg-surface-container-low px-4 py-3 font-body-md text-body-md text-on-surface focus:ring-2 focus:ring-primary"
                        placeholder="Nội dung buổi chữa, đối tượng học viên...">{{ old('description') }}</textarea>
                </div>

                <div>
                    <label for="visibility" class="mb-1.5 block font-label-sm text-label-sm font-medium text-on-surface-variant">Chế độ tham gia</label>
                    <select id="visibility" name="visibility"
                        class="w-full rounded-lg border-none bg-surface-container-low px-4 py-3 font-body-md text-body-md text-on-surface focus:ring-2 focus:ring-primary">
                        @foreach ($visibilities as $vis)
                            <option value="{{ $vis->value }}" @selected(old('visibility', ClassroomVisibility::Unlisted->value) === $vis->value)>
                                {{ $vis->label() }}
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-1.5 font-body-sm text-body-sm text-on-surface-variant">
                        Mặc định “Không liệt kê” — học viên vào bằng link hoặc mã, không hiện catalog cộng đồng.
                    </p>
                </div>

                <div>
                    <label for="max_members" class="mb-1.5 block font-label-sm text-label-sm font-medium text-on-surface-variant">Giới hạn thành viên (tuỳ chọn)</label>
                    <input id="max_members" name="max_members" type="number" min="2" max="5000" value="{{ old('max_members') }}"
                        class="w-full rounded-lg border-none bg-surface-container-low px-4 py-3 font-body-md text-body-md text-on-surface focus:ring-2 focus:ring-primary"
                        placeholder="Để trống = không giới hạn">
                </div>
            </section>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <a href="{{ route('teach.classes.index') }}"
                    class="inline-flex items-center justify-center rounded-lg border border-outline-variant px-5 py-2.5 font-label-md text-label-md text-on-surface-variant hover:bg-surface-container-low">
                    Hủy
                </a>
                <button type="submit"
                    class="inline-flex items-center justify-center rounded-lg bg-primary px-5 py-2.5 font-label-md text-label-md font-semibold text-on-primary hover:opacity-90">
                    Tạo lớp
                </button>
            </div>
        </form>
    </div>
</x-layouts.teach>
