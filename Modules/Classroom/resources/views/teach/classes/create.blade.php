@php
    use Modules\Classroom\Enums\ClassroomPurpose;
    use Modules\Classroom\Enums\ClassroomVisibility;

    $selectedPurpose = old('purpose', ClassroomPurpose::FeedbackReview->value);
    $selectedVisibility = old('visibility', ClassroomVisibility::Unlisted->value);

    $visibilityDescriptions = [
        ClassroomVisibility::Public->value => 'Sau khi được duyệt, lớp có thể xuất hiện trong danh mục để học viên tìm và tham gia.',
        ClassroomVisibility::Unlisted->value => 'Lớp không xuất hiện trong danh mục. Học viên tham gia bằng đường dẫn hoặc mã lớp.',
        ClassroomVisibility::InviteOnly->value => 'Chỉ những học viên được giảng viên mời mới có thể tham gia lớp.',
    ];

    $visibilityIcons = [
        ClassroomVisibility::Public->value => 'public',
        ClassroomVisibility::Unlisted->value => 'link',
        ClassroomVisibility::InviteOnly->value => 'lock',
    ];
@endphp

<x-layouts.teach
    title="Tạo lớp học"
    description="Tạo lớp chữa đề dành cho giảng viên, thiết lập nội dung, hình thức tham gia và giới hạn học viên.">
    <div class="mx-auto max-w-6xl">
        <nav aria-label="Điều hướng lớp học" class="text-sm text-on-surface-variant">
            <ol class="flex flex-wrap items-center gap-2">
                <li><a href="{{ route('teach.dashboard') }}" class="hover:text-primary hover:underline">Tổng quan</a></li>
                <li aria-hidden="true">/</li>
                <li><a href="{{ route('teach.classes.index') }}" class="hover:text-primary hover:underline">Lớp của tôi</a></li>
                <li aria-hidden="true">/</li>
                <li aria-current="page" class="font-semibold text-on-surface">Tạo lớp</li>
            </ol>
        </nav>

        <header class="mt-5 border-b border-outline-variant pb-6">
            <p class="font-label-sm font-semibold uppercase tracking-wide text-primary">Quản lý lớp học</p>
            <h1 class="mt-1 font-headline-md text-headline-md font-bold text-on-surface">Tạo lớp chữa đề mới</h1>
            <p class="mt-2 max-w-3xl font-body-md text-body-md text-on-surface-variant">
                Khai báo mục đích, nội dung và cách học viên tham gia. Sau khi tạo, lớp được gửi cho admin duyệt trước khi hiển thị cho học viên.
            </p>
        </header>

        <section aria-labelledby="creation-progress-title" class="mt-6">
            <h2 id="creation-progress-title" class="sr-only">Quy trình thiết lập lớp học</h2>
            <ol class="grid gap-2 text-sm sm:grid-cols-3">
                <li aria-current="step" class="flex items-center gap-3 rounded-lg border border-primary bg-primary/5 px-4 py-3">
                    <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-primary text-xs font-bold text-on-primary">1</span>
                    <span><strong class="block text-on-surface">Thông tin lớp</strong><span class="text-on-surface-variant">Bạn đang thực hiện</span></span>
                </li>
                <li class="flex items-center gap-3 rounded-lg border border-outline-variant bg-surface px-4 py-3">
                    <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-surface-container text-xs font-bold text-on-surface-variant">2</span>
                    <span><strong class="block text-on-surface">Admin phê duyệt</strong><span class="text-on-surface-variant">Kiểm tra nội dung lớp</span></span>
                </li>
                <li class="flex items-center gap-3 rounded-lg border border-outline-variant bg-surface px-4 py-3">
                    <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-surface-container text-xs font-bold text-on-surface-variant">3</span>
                    <span><strong class="block text-on-surface">Thiết lập buổi học</strong><span class="text-on-surface-variant">Gắn đề và lên lịch live</span></span>
                </li>
            </ol>
        </section>

        @if ($errors->any())
            <section role="alert" aria-labelledby="form-errors-title"
                class="mt-6 rounded-xl border border-error/30 bg-error-container px-5 py-4 text-on-error-container">
                <h2 id="form-errors-title" class="font-title-sm font-semibold">Chưa thể tạo lớp học</h2>
                <p class="mt-1 text-sm">Vui lòng kiểm tra lại các thông tin sau:</p>
                <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        <form method="post" action="{{ route('teach.classes.store') }}" class="mt-6" novalidate>
            @csrf

            <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
                <div class="space-y-6">
                    <fieldset class="rounded-xl border border-outline-variant bg-surface p-5 md:p-6">
                        <legend class="px-1 font-title-md text-title-md font-semibold text-on-surface">1. Loại buổi</legend>
                        <p class="mt-1 text-sm leading-6 text-on-surface-variant">
                            Loại buổi quyết định luồng chọn câu hỏi hoặc đề thi ở bước thiết lập sau khi lớp được tạo.
                        </p>

                        <div class="mt-4 grid gap-3 sm:grid-cols-2">
                            @foreach ($purposes as $purpose)
                                <label
                                    class="relative flex cursor-pointer gap-3 rounded-lg border border-outline-variant p-4 transition hover:border-primary/60 has-[:checked]:border-primary has-[:checked]:bg-primary/5 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-primary">
                                    <input type="radio" name="purpose" value="{{ $purpose->value }}" class="sr-only"
                                        aria-describedby="purpose-{{ $purpose->value }}-description"
                                        @checked($selectedPurpose === $purpose->value) required>
                                    <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                        <span class="material-symbols-outlined" aria-hidden="true">{{ $purpose->coverIcon() }}</span>
                                    </span>
                                    <span>
                                        <span class="block font-semibold text-on-surface">{{ $purpose->label() }}</span>
                                        <span id="purpose-{{ $purpose->value }}-description" class="mt-1 block text-sm leading-5 text-on-surface-variant">
                                            {{ $purpose->description() }}
                                        </span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        @error('purpose')
                            <p class="mt-2 text-sm text-error">{{ $message }}</p>
                        @enderror
                    </fieldset>

                    <fieldset class="rounded-xl border border-outline-variant bg-surface p-5 md:p-6">
                        <legend class="px-1 font-title-md text-title-md font-semibold text-on-surface">2. Thông tin lớp học</legend>
                        <p class="mt-1 text-sm leading-6 text-on-surface-variant">
                            Thông tin rõ ràng giúp admin duyệt nhanh và học viên hiểu đúng nội dung lớp.
                        </p>

                        <div class="mt-5 space-y-5">
                            <div>
                                <div class="flex items-center justify-between gap-3">
                                    <label for="title" class="font-label-sm font-medium text-on-surface">Tên lớp <span class="text-error" aria-hidden="true">*</span></label>
                                    <span class="text-xs text-on-surface-variant">Tối đa 200 ký tự</span>
                                </div>
                                <input id="title" name="title" type="text" value="{{ old('title') }}" required maxlength="200"
                                    aria-describedby="title-help @error('title') title-error @enderror"
                                    @class([
                                        'mt-1.5 w-full rounded-lg border bg-surface px-4 py-3 text-on-surface outline-none transition focus:ring-2 focus:ring-primary/20',
                                        'border-error' => $errors->has('title'),
                                        'border-outline-variant focus:border-primary' => ! $errors->has('title'),
                                    ])
                                    placeholder="Ví dụ: Chữa feedback Nội khoa — tuần 12">
                                <p id="title-help" class="mt-1.5 text-sm text-on-surface-variant">Nên gồm chủ đề, hình thức chữa và mốc thời gian để dễ nhận biết.</p>
                                @error('title')
                                    <p id="title-error" class="mt-1 text-sm text-error">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <div class="flex items-center justify-between gap-3">
                                    <label for="description" class="font-label-sm font-medium text-on-surface">Mô tả lớp</label>
                                    <span class="text-xs text-on-surface-variant">Không bắt buộc · tối đa 5.000 ký tự</span>
                                </div>
                                <textarea id="description" name="description" rows="6" maxlength="5000"
                                    aria-describedby="description-help @error('description') description-error @enderror"
                                    @class([
                                        'mt-1.5 w-full resize-y rounded-lg border bg-surface px-4 py-3 text-on-surface outline-none transition focus:ring-2 focus:ring-primary/20',
                                        'border-error' => $errors->has('description'),
                                        'border-outline-variant focus:border-primary' => ! $errors->has('description'),
                                    ])
                                    placeholder="Nêu nội dung sẽ chữa, đối tượng học viên, kiến thức cần chuẩn bị và kết quả mong đợi...">{{ old('description') }}</textarea>
                                <p id="description-help" class="mt-1.5 text-sm text-on-surface-variant">
                                    Không nhập thông tin cá nhân, số điện thoại hoặc dữ liệu bệnh nhân trong mô tả công khai.
                                </p>
                                @error('description')
                                    <p id="description-error" class="mt-1 text-sm text-error">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </fieldset>

                    <fieldset class="rounded-xl border border-outline-variant bg-surface p-5 md:p-6">
                        <legend class="px-1 font-title-md text-title-md font-semibold text-on-surface">3. Quyền tham gia</legend>
                        <p class="mt-1 text-sm leading-6 text-on-surface-variant">
                            Chọn cách học viên tìm thấy và tham gia lớp sau khi admin phê duyệt.
                        </p>

                        <div class="mt-4 space-y-3">
                            @foreach ($visibilities as $visibility)
                                <label class="flex cursor-pointer gap-3 rounded-lg border border-outline-variant p-4 transition hover:border-primary/60 has-[:checked]:border-primary has-[:checked]:bg-primary/5 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-primary">
                                    <input type="radio" name="visibility" value="{{ $visibility->value }}"
                                        class="mt-1 size-4 shrink-0 border-outline text-primary focus:ring-primary"
                                        @checked($selectedVisibility === $visibility->value) required>
                                    <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-surface-container text-on-surface-variant">
                                        <span class="material-symbols-outlined text-[20px]" aria-hidden="true">{{ $visibilityIcons[$visibility->value] }}</span>
                                    </span>
                                    <span>
                                        <span class="block font-semibold text-on-surface">{{ $visibility->label() }}</span>
                                        <span class="mt-1 block text-sm leading-5 text-on-surface-variant">{{ $visibilityDescriptions[$visibility->value] }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        @error('visibility')
                            <p class="mt-2 text-sm text-error">{{ $message }}</p>
                        @enderror

                        <div class="mt-5 border-t border-outline-variant pt-5">
                            <label for="max_members" class="font-label-sm font-medium text-on-surface">Giới hạn thành viên</label>
                            <div class="mt-1.5 flex max-w-md items-center gap-3">
                                <input id="max_members" name="max_members" type="number" min="2" max="5000"
                                    value="{{ old('max_members') }}" inputmode="numeric"
                                    aria-describedby="max-members-help @error('max_members') max-members-error @enderror"
                                    @class([
                                        'w-full rounded-lg border bg-surface px-4 py-3 text-on-surface outline-none transition focus:ring-2 focus:ring-primary/20',
                                        'border-error' => $errors->has('max_members'),
                                        'border-outline-variant focus:border-primary' => ! $errors->has('max_members'),
                                    ])
                                    placeholder="Ví dụ: 50">
                                <span class="shrink-0 text-sm text-on-surface-variant">học viên</span>
                            </div>
                            <p id="max-members-help" class="mt-1.5 text-sm text-on-surface-variant">
                                Từ 2 đến 5.000. Để trống nếu chưa muốn giới hạn số người tham gia.
                            </p>
                            @error('max_members')
                                <p id="max-members-error" class="mt-1 text-sm text-error">{{ $message }}</p>
                            @enderror
                        </div>
                    </fieldset>

                    <div class="rounded-xl border border-outline-variant bg-surface p-4 sm:flex sm:items-center sm:justify-between sm:gap-4">
                        <p class="text-sm leading-5 text-on-surface-variant">
                            Khi gửi, lớp được tạo ở trạng thái <strong class="text-on-surface">Chờ duyệt</strong>. Bạn vẫn có thể chuẩn bị nội dung và lịch live trong thời gian chờ.
                        </p>
                        <div class="mt-4 flex shrink-0 flex-col-reverse gap-3 sm:mt-0 sm:flex-row">
                            <a href="{{ route('teach.classes.index') }}"
                                class="inline-flex min-h-11 items-center justify-center rounded-lg border border-outline-variant px-5 py-2.5 font-semibold text-on-surface hover:bg-surface-container-low">
                                Hủy
                            </a>
                            <button type="submit"
                                class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-primary px-5 py-2.5 font-semibold text-on-primary hover:opacity-90 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
                                Tạo và gửi duyệt
                                <span class="material-symbols-outlined text-[19px]" aria-hidden="true">arrow_forward</span>
                            </button>
                        </div>
                    </div>
                </div>

                <aside aria-label="Hướng dẫn tạo lớp" class="space-y-4 lg:sticky lg:top-24">
                    <section class="rounded-xl border border-outline-variant bg-surface p-5">
                        <h2 class="font-title-md font-semibold text-on-surface">Sau khi tạo lớp</h2>
                        <ol class="mt-3 space-y-3 text-sm leading-5 text-on-surface-variant">
                            <li class="flex gap-3"><span class="font-semibold text-primary">1.</span><span>Hệ thống tạo mã lớp và thêm bạn làm giảng viên chủ trì.</span></li>
                            <li class="flex gap-3"><span class="font-semibold text-primary">2.</span><span>Admin kiểm tra nội dung, mục đích và chế độ tham gia.</span></li>
                            <li class="flex gap-3"><span class="font-semibold text-primary">3.</span><span>Bạn chọn câu hỏi, đặt lịch và chuẩn bị Studio cho buổi live.</span></li>
                            <li class="flex gap-3"><span class="font-semibold text-primary">4.</span><span>Học viên có thể thấy hoặc tham gia theo chế độ đã chọn.</span></li>
                        </ol>
                    </section>

                    <section class="rounded-xl border border-outline-variant bg-surface p-5">
                        <h2 class="font-title-md font-semibold text-on-surface">Thông tin cần chuẩn bị</h2>
                        <ul class="mt-3 space-y-2 text-sm text-on-surface-variant">
                            <li class="flex gap-2"><span class="material-symbols-outlined text-[18px] text-primary" aria-hidden="true">check</span><span>Tên lớp phản ánh đúng nội dung.</span></li>
                            <li class="flex gap-2"><span class="material-symbols-outlined text-[18px] text-primary" aria-hidden="true">check</span><span>Mục tiêu và đối tượng học viên.</span></li>
                            <li class="flex gap-2"><span class="material-symbols-outlined text-[18px] text-primary" aria-hidden="true">check</span><span>Loại buổi phù hợp với nguồn câu hỏi.</span></li>
                            <li class="flex gap-2"><span class="material-symbols-outlined text-[18px] text-primary" aria-hidden="true">check</span><span>Quy mô lớp phù hợp với cách tổ chức.</span></li>
                        </ul>
                    </section>

                    <section class="rounded-xl border border-tertiary/25 bg-tertiary/5 p-5">
                        <div class="flex items-start gap-3">
                            <span class="material-symbols-outlined text-tertiary" aria-hidden="true">info</span>
                            <div>
                                <h2 class="font-semibold text-on-surface">Lưu ý kiểm duyệt</h2>
                                <p class="mt-1 text-sm leading-5 text-on-surface-variant">
                                    Tạo lớp không đồng nghĩa lớp được công khai ngay. Học viên chỉ truy cập theo chính sách sau khi lớp đạt trạng thái phù hợp.
                                </p>
                            </div>
                        </div>
                    </section>
                </aside>
            </div>
        </form>
    </div>
</x-layouts.teach>
