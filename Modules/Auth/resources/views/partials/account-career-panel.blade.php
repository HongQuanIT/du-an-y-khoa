@php
    use App\Support\TargetExams;

    $roleLabel = $user->career_role ?: null;
    $year = $user->graduation_year;
    $country = $user->country ?: 'Việt Nam';
    $university = $user->institution;
    $specialty = $user->specialty;
    $objectiveKey = $user->study_objective;
    $objectiveTitle = $objectiveKey ? TargetExams::title($objectiveKey) : null;
    $objectiveMeta = $objectiveKey ? (TargetExams::all()[$objectiveKey] ?? null) : null;

    $careerRoles = [
        'Sinh viên Y khoa',
        'Bác sĩ nội trú',
        'Bác sĩ',
        'Giảng viên',
        'Khác',
    ];
    $years = range((int) now()->year + 8, (int) now()->year - 5);
    $countries = ['Việt Nam', 'United States', 'Germany', 'Italy', 'Australia', 'Canada', 'United Kingdom', 'Khác'];

    $completionFields = [
        filled($user->name),
        filled($user->avatar_path),
        filled($user->career_role),
        filled($user->institution),
        filled($user->study_objective),
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

    <section class="overflow-hidden rounded-xl border border-outline-variant bg-surface shadow-sm">
        <div class="flex flex-col gap-3 border-b border-outline-variant bg-surface-container-lowest/60 px-5 py-4 sm:flex-row sm:items-center sm:justify-between md:px-6">
            <div>
                <h2 class="font-title-md text-title-md text-on-surface">Thông tin nghề nghiệp</h2>
                <p class="mt-0.5 font-body-sm text-body-sm text-on-surface-variant">Giúp hệ thống hiểu bối cảnh học tập và luyện thi của bạn.</p>
            </div>
            <button type="button" x-show="panel !== 'career'" @click="open('career')"
                class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-outline-variant px-4 py-2 font-label-md text-label-md text-on-surface transition-colors hover:bg-surface-container-low">
                <span class="material-symbols-outlined text-[18px]">edit</span>
                Chỉnh sửa
            </button>
        </div>
        <dl x-show="panel !== 'career'" class="grid grid-cols-1 divide-y divide-outline-variant/60 sm:grid-cols-2 sm:divide-y-0">
            @foreach ([
                ['label' => 'Vai trò', 'value' => $roleLabel],
                ['label' => 'Năm tốt nghiệp', 'value' => $year],
                ['label' => 'Quốc gia', 'value' => $country],
                ['label' => 'Trường / Cơ sở', 'value' => $university],
                ['label' => 'Chuyên ngành', 'value' => $specialty],
            ] as $field)
                <div class="px-5 py-4 md:px-6 {{ $loop->last && $loop->iteration % 2 === 1 ? 'sm:col-span-2' : '' }}">
                    <dt class="{{ $labelClass }}">{{ $field['label'] }}</dt>
                    <dd class="mt-1 font-body-md text-body-md text-on-surface">
                        @if (filled($field['value']))
                            {{ $field['value'] }}
                        @else
                            <span class="text-on-surface-variant">Chưa cập nhật</span>
                        @endif
                    </dd>
                </div>
            @endforeach
        </dl>
        <form x-show="panel === 'career'" x-cloak method="post" action="{{ route('settings.profile') }}" class="space-y-5 p-5 md:p-6">
            @csrf
            @method('PUT')
            <input type="hidden" name="_form" value="career">
            <input type="hidden" name="name" value="{{ $user->name }}">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="flex flex-col gap-1.5">
                    <label for="career_role" class="{{ $labelClass }}">Vai trò</label>
                    <select id="career_role" name="career_role" class="{{ $inputClass }}">
                        @foreach ($careerRoles as $role)
                            <option value="{{ $role }}" @selected(old('career_role', $user->career_role ?: 'Sinh viên Y khoa') === $role)>{{ $role }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex flex-col gap-1.5">
                    <label for="graduation_year" class="{{ $labelClass }}">Năm tốt nghiệp</label>
                    <select id="graduation_year" name="graduation_year" class="{{ $inputClass }}">
                        <option value="">Chưa xác định</option>
                        @foreach ($years as $y)
                            <option value="{{ $y }}" @selected((string) old('graduation_year', $user->graduation_year) === (string) $y)>{{ $y }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex flex-col gap-1.5">
                    <label for="country" class="{{ $labelClass }}">Quốc gia</label>
                    <select id="country" name="country" class="{{ $inputClass }}">
                        @foreach ($countries as $c)
                            <option value="{{ $c }}" @selected(old('country', $user->country ?: 'Việt Nam') === $c)>{{ $c }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex flex-col gap-1.5">
                    <label for="institution" class="{{ $labelClass }}">Trường / Cơ sở</label>
                    <input id="institution" name="institution" type="text" value="{{ old('institution', $user->institution) }}" placeholder="VD: ĐH Y Dược TP.HCM" class="{{ $inputClass }}">
                </div>
                <div class="flex flex-col gap-1.5 sm:col-span-2">
                    <label for="specialty" class="{{ $labelClass }}">Chuyên ngành <span class="font-normal text-on-surface-variant">(tuỳ chọn)</span></label>
                    <input id="specialty" name="specialty" type="text" value="{{ old('specialty', $user->specialty) }}" placeholder="VD: Nội tổng hợp, Ngoại khoa..." class="{{ $inputClass }}">
                </div>
            </div>
            <div class="flex justify-end gap-3 border-t border-outline-variant pt-4">
                <button type="button" @click="panel = null" class="rounded-lg border border-outline-variant px-4 py-2.5 font-label-md text-label-md text-on-surface hover:bg-surface-container-low">Huỷ</button>
                <button type="submit" class="rounded-lg bg-primary px-5 py-2.5 font-label-md text-label-md font-semibold text-on-primary hover:opacity-90">Lưu thay đổi</button>
            </div>
        </form>
    </section>

    <section class="overflow-hidden rounded-xl border border-outline-variant bg-surface shadow-sm">
        <div class="flex flex-col gap-3 border-b border-outline-variant bg-surface-container-lowest/60 px-5 py-4 sm:flex-row sm:items-center sm:justify-between md:px-6">
            <div>
                <h2 class="font-title-md text-title-md text-on-surface">Mục tiêu học tập</h2>
                <p class="mt-0.5 font-body-sm text-body-sm text-on-surface-variant">Nội dung và gợi ý ôn tập sẽ được tối ưu theo mục tiêu bạn chọn.</p>
            </div>
            <button type="button" x-show="panel !== 'objective'" @click="open('objective')"
                class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-outline-variant px-4 py-2 font-label-md text-label-md text-on-surface transition-colors hover:bg-surface-container-low">
                <span class="material-symbols-outlined text-[18px]">edit</span>
                {{ $objectiveTitle ? 'Thay đổi' : 'Chọn mục tiêu' }}
            </button>
        </div>
        <div x-show="panel !== 'objective'" class="p-5 md:p-6">
            @if ($objectiveTitle && $objectiveMeta)
                <div class="flex items-start gap-4 rounded-xl border border-primary/20 bg-primary/5 p-4">
                    <div class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                        <span class="material-symbols-outlined text-[24px]">{{ $objectiveMeta['icon'] }}</span>
                    </div>
                    <div class="min-w-0">
                        <p class="font-label-md text-label-md font-semibold text-on-surface">{{ $objectiveTitle }}</p>
                        <p class="mt-1 font-body-sm text-body-sm text-on-surface-variant">{{ $objectiveMeta['hint'] }}</p>
                    </div>
                    <span class="ml-auto hidden shrink-0 items-center rounded-full bg-primary px-2.5 py-0.5 font-label-sm text-label-sm font-medium text-on-primary sm:inline-flex">Đang chọn</span>
                </div>
            @else
                <div class="flex flex-col items-center rounded-xl border border-dashed border-outline-variant bg-surface-container-lowest/50 px-6 py-10 text-center">
                    <div class="mb-3 flex size-12 items-center justify-center rounded-full bg-surface-container-high text-on-surface-variant">
                        <span class="material-symbols-outlined text-[28px]">flag</span>
                    </div>
                    <p class="font-body-md text-body-md font-medium text-on-surface">Chưa chọn mục tiêu học tập</p>
                    <p class="mt-1 max-w-sm font-body-sm text-body-sm text-on-surface-variant">Chọn kỳ thi hoặc hướng ôn luyện để nhận gợi ý phù hợp.</p>
                    <button type="button" @click="open('objective')" class="mt-4 rounded-lg bg-primary px-5 py-2.5 font-label-md text-label-md font-semibold text-on-primary hover:opacity-90">Chọn mục tiêu</button>
                </div>
            @endif
        </div>
        <form x-show="panel === 'objective'" x-cloak method="post" action="{{ route('settings.objective') }}" class="space-y-5 p-5 md:p-6">
            @csrf
            @method('PUT')
            <input type="hidden" name="_form" value="objective">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                @foreach (TargetExams::selectable() as $key => $exam)
                    <label @class([
                        'group relative flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition-all',
                        'border-primary bg-primary/5 ring-1 ring-primary/20' => old('study_objective', $user->study_objective) === $key,
                        'border-outline-variant hover:border-primary/40 hover:bg-surface-container-lowest' => old('study_objective', $user->study_objective) !== $key,
                    ])>
                        <input type="radio" name="study_objective" value="{{ $key }}" class="mt-1 text-primary focus:ring-primary" @checked(old('study_objective', $user->study_objective) === $key)>
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-surface-container-high text-on-surface-variant transition-colors group-has-[:checked]:bg-primary/10 group-has-[:checked]:text-primary">
                            <span class="material-symbols-outlined text-[20px]">{{ $exam['icon'] }}</span>
                        </span>
                        <span class="min-w-0">
                            <span class="block font-label-md text-label-md font-semibold text-on-surface">{{ $exam['title'] }}</span>
                            <span class="mt-0.5 block font-body-sm text-body-sm text-on-surface-variant">{{ $exam['hint'] }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
            <div class="flex justify-end gap-3 border-t border-outline-variant pt-4">
                <button type="button" @click="panel = null" class="rounded-lg border border-outline-variant px-4 py-2.5 font-label-md text-label-md text-on-surface hover:bg-surface-container-low">Huỷ</button>
                <button type="submit" class="rounded-lg bg-primary px-5 py-2.5 font-label-md text-label-md font-semibold text-on-primary hover:opacity-90">Lưu mục tiêu</button>
            </div>
        </form>
    </section>
</div>
