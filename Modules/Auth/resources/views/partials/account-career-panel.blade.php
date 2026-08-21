@php
    $roleLabel = $user->career_role ?: null;

    $completionFields = [
        filled($user->name),
        filled($user->avatar_path),
    ];
    $profileCompletion = (int) round(collect($completionFields)->filter()->count() / count($completionFields) * 100);

    $inputClass = 'h-10 w-full rounded-lg border border-outline-variant bg-surface px-3 font-body-md text-body-md text-on-surface transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20';
    $labelClass = 'font-label-sm text-label-sm font-medium text-on-surface-variant';
@endphp

<div x-data="{
    panel: {{ $errors->any() && old('_form') === 'career' ? "'career'" : ($errors->any() && old('_form') === 'objective' ? "'objective'" : 'null') }},
    open(next) { this.panel = this.panel === next ? null : next; }
}" class="space-y-6">
    @if ($profileCompletion < 100)
        <div class="rounded-xl border border-outline-variant bg-surface p-4 md:p-5">
            <div class="mb-2 flex items-center justify-between gap-3">
                <p class="font-label-md text-label-md font-semibold text-on-surface">Hoàn thiện hồ sơ</p>
                <span class="font-label-sm text-label-sm font-semibold text-primary">{{ $profileCompletion }}%</span>
            </div>
            <div class="h-2 overflow-hidden rounded-full bg-surface-container-high">
                <div class="h-full rounded-full bg-primary transition-all duration-500" style="width: {{ $profileCompletion }}%"></div>
            </div>
            <p class="mt-2 font-body-sm text-body-sm text-on-surface-variant">
                @if (! $user->study_objective)
                    Chọn mục tiêu học tập để hệ thống gợi ý nội dung phù hợp.
                @elseif (! $user->institution)
                    Thêm trường / cơ sở đào tạo để nhận gợi ý chính xác hơn.
                @elseif (! $user->avatar_path)
                    Tải ảnh đại diện để hoàn thiện hồ sơ.
                @else
                    Bổ sung thông tin còn thiếu để tối ưu trải nghiệm học tập.
                @endif
            </p>
        </div>
    @endif

    <section class="overflow-hidden rounded-xl border border-outline-variant bg-surface shadow-sm">
        <div class="border-b border-outline-variant bg-surface-container-lowest/60 px-5 py-4 md:px-6">
            <h2 class="font-title-md text-title-md text-on-surface">Thông tin cơ bản</h2>
            <p class="mt-0.5 font-body-sm text-body-sm text-on-surface-variant">Ảnh đại diện và tên hiển thị trên nền tảng.</p>
        </div>
        <div class="space-y-6 p-5 md:p-6">
            <div class="flex flex-col gap-5 sm:flex-row sm:items-start">
                @include('auth::partials.avatar', ['user' => $user, 'size' => 'lg'])
                <div class="min-w-0 flex-1 space-y-3">
                    <div>
                        <p class="font-headline-sm text-headline-sm text-on-surface">{{ $user->name }}</p>
                        <p class="mt-0.5 font-body-md text-body-md text-on-surface-variant">{{ $user->email }}</p>
                    </div>
                    @if ($roleLabel)
                        <span class="inline-flex items-center rounded-full bg-primary/10 px-3 py-1 font-label-sm text-label-sm font-medium text-primary">{{ $roleLabel }}</span>
                    @endif
                    <a href="{{ route('profile.show', ['tab' => 'contact']) }}"
                        class="inline-flex items-center gap-1.5 font-label-md text-label-md text-primary hover:underline">
                        Chỉnh sửa tên &amp; liên hệ
                        <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
                    </a>
                </div>
            </div>
            <div class="rounded-lg border border-dashed border-outline-variant bg-surface-container-lowest/50 p-4">
                <form method="post" action="{{ route('settings.avatar') }}" enctype="multipart/form-data" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    @csrf
                    @method('PUT')
                    <div class="min-w-0 flex-1">
                        <label for="avatar" class="{{ $labelClass }}">Ảnh đại diện</label>
                        <p class="mb-2 font-body-sm text-body-sm text-on-surface-variant">JPG, PNG hoặc WebP — tối đa 2 MB</p>
                        <input id="avatar" name="avatar" type="file" required accept="image/jpeg,image/png,image/webp"
                            class="block w-full text-body-sm file:mr-3 file:rounded-lg file:border-0 file:bg-primary file:px-4 file:py-2 file:font-label-md file:text-label-md file:text-on-primary hover:file:opacity-90">
                        @error('avatar')
                            <p class="mt-1 font-body-sm text-body-sm text-error">{{ $message }}</p>
                        @enderror
                    </div>
                    <button type="submit" class="shrink-0 rounded-lg bg-primary px-5 py-2.5 font-label-md text-label-md font-semibold text-on-primary hover:opacity-90">Tải lên</button>
                </form>
                @if ($user->avatar_path)
                    <form method="post" action="{{ route('settings.avatar.destroy') }}" class="mt-3">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="font-label-sm text-label-sm text-on-surface-variant underline-offset-2 hover:text-error hover:underline">Xóa ảnh hiện tại</button>
                    </form>
                @endif
            </div>
        </div>
    </section>
</div>
